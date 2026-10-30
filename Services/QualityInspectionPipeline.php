<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\Content\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use MultiTenantSaas\Context\TenantContext;
use MultiTenantSaas\Contracts\IdGeneratorContract;
use MultiTenantSaas\Modules\Content\Models\InspectionAlert;
use MultiTenantSaas\Modules\Content\Models\InspectionResult;
use MultiTenantSaas\Modules\Content\Models\InspectionRule;

/**
 * 质检流水线
 *
 * 对单条消息执行所有 active 规则的匹配检测。
 * 规则按优先级降序执行，命中后写入结果 + 生成预警。
 */
class QualityInspectionPipeline
{
    public function __construct(
        protected IdGeneratorContract $idGenerator,
        protected SensitiveWordService $sensitiveWordService,
    ) {}

    /**
     * 执行质检
     *
     * @param  int  $tenantId  租户ID
     * @param  string  $sourceType  来源类型 (chat_message/mass_push/moment)
     * @param  int  $sourceId  来源消息ID
     * @param  string  $content  待检测文本
     * @return array 命中结果列表
     */
    public function inspect(int $tenantId, string $sourceType, int $sourceId, string $content): array
    {
        TenantContext::setTenantId((string) $tenantId);

        if (empty(trim($content))) {
            return [];
        }

        // 加载租户所有 active 规则，按优先级降序
        $rules = InspectionRule::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->orderByDesc('priority')
            ->get();

        $hits = [];

        foreach ($rules as $rule) {
            $matched = $this->matchRule($rule, $content, $tenantId);

            if ($matched !== null) {
                $result = $this->recordHit($tenantId, $sourceType, $sourceId, $rule, $matched);
                $hits[] = $result;

                // 严重级别 >= high 时生成实时预警
                if (in_array($rule->severity, ['high', 'critical'], true)) {
                    $this->createAlert($tenantId, $result);
                }
            }
        }

        if (! empty($hits)) {
            Log::info("[QualityInspection] Tenant {$tenantId}: {$sourceType}#{$sourceId} hit " . count($hits) . ' rule(s)');
        }

        return $hits;
    }

    /**
     * 单条规则匹配
     *
     * @return string|null 命中的内容片段，未命中返回 null
     */
    protected function matchRule(InspectionRule $rule, string $content, int $tenantId): ?string
    {
        return match ($rule->type) {
            'sensitive_word' => $this->matchSensitiveWord($rule, $content, $tenantId),
            'over_promise' => $this->matchOverPromise($rule, $content),
            'compliance' => $this->matchCompliance($rule, $content),
            'custom' => $this->matchCustom($rule, $content),
            default => null,
        };
    }

    /**
     * 敏感词匹配（复用 SensitiveWordService）
     */
    protected function matchSensitiveWord(InspectionRule $rule, string $content, int $tenantId): ?string
    {
        // 如果规则 conditions 中指定了特定敏感词，使用规则内的词库
        $words = $rule->conditions['words'] ?? null;

        if ($words && is_array($words)) {
            foreach ($words as $word) {
                $isRegex = $rule->conditions['is_regex'] ?? false;
                if ($isRegex) {
                    if (@preg_match($word, $content)) {
                        return $word;
                    }
                } elseif (mb_stripos($content, $word) !== false) {
                    return $word;
                }
            }

            return null;
        }

        // 否则使用全局敏感词库
        $hits = $this->sensitiveWordService->checkContent($tenantId, $content);

        return ! empty($hits) ? implode(', ', array_column($hits, 'word')) : null;
    }

    /**
     * 过度承诺检测
     *
     * 匹配模式：承诺类关键词 + 业务词
     */
    protected function matchOverPromise(InspectionRule $rule, string $content): ?string
    {
        $promisePatterns = $rule->conditions['patterns'] ?? [
            '保证.*效果', '承诺.*回报', '一定.*赚', '绝对.*安全',
            '百分百.*有效', '无效.*退款', '假一赔十',
        ];

        foreach ($promisePatterns as $pattern) {
            if (@preg_match('/' . $pattern . '/u', $content, $matches)) {
                return $matches[0] ?? $pattern;
            }
        }

        return null;
    }

    /**
     * 合规检测
     *
     * 检测隐私信息泄露、资质承诺等
     */
    protected function matchCompliance(InspectionRule $rule, string $content): ?string
    {
        $compliancePatterns = $rule->conditions['patterns'] ?? [
            // 手机号
            '1[3-9]\d{9}',
            // 身份证号
            '\d{17}[\dXx]',
            // 银行卡号
            '\d{16,19}',
        ];

        foreach ($compliancePatterns as $pattern) {
            if (@preg_match('/' . $pattern . '/u', $content, $matches)) {
                return '[隐私信息] ' . substr($matches[0], 0, 4) . '****';
            }
        }

        return null;
    }

    /**
     * 自定义规则匹配
     *
     * conditions 格式: {"keywords": ["词1","词2"], "logic": "any|all", "regex": ["正则1"]}
     */
    protected function matchCustom(InspectionRule $rule, string $content): ?string
    {
        $conditions = $rule->conditions ?? [];
        $logic = $conditions['logic'] ?? 'any';

        // 关键词匹配
        $keywords = $conditions['keywords'] ?? [];
        $keywordHits = [];
        foreach ($keywords as $kw) {
            if (mb_stripos($content, $kw) !== false) {
                $keywordHits[] = $kw;
            }
        }

        // 正则匹配
        $regexes = $conditions['regex'] ?? [];
        $regexHits = [];
        foreach ($regexes as $regex) {
            if (@preg_match('/' . $regex . '/u', $content, $m)) {
                $regexHits[] = $m[0] ?? $regex;
            }
        }

        $allHits = array_merge($keywordHits, $regexHits);

        if ($logic === 'all') {
            $expectedCount = count($keywords) + count($regexes);

            return count($allHits) >= $expectedCount && $expectedCount > 0
                ? implode(', ', $allHits)
                : null;
        }

        return ! empty($allHits) ? implode(', ', $allHits) : null;
    }

    /**
     * 记录命中结果
     */
    protected function recordHit(int $tenantId, string $sourceType, int $sourceId, InspectionRule $rule, string $matched): InspectionResult
    {
        return DB::transaction(function () use ($tenantId, $sourceType, $sourceId, $rule, $matched) {
            return InspectionResult::create([
                'inspection_result_id' => $this->idGenerator->generate(),
                'tenant_id' => $tenantId,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'rule_id' => $rule->inspection_rule_id,
                'matched_content' => mb_substr($matched, 0, 500),
                'severity' => $rule->severity,
                'action_taken' => $rule->action,
                'status' => 'pending',
            ]);
        });
    }

    /**
     * 创建预警通知
     */
    protected function createAlert(int $tenantId, InspectionResult $result): InspectionAlert
    {
        return InspectionAlert::create([
            'alert_id' => $this->idGenerator->generate(),
            'tenant_id' => $tenantId,
            'inspection_result_id' => $result->inspection_result_id,
            'notify_channel' => 'system',
            'notify_to' => [],
            'status' => 'pending',
        ]);
    }
}
