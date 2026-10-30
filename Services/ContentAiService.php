<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\Content\Services;

use MultiTenantSaas\Contracts\AiTextServiceContract;
use MultiTenantSaas\Modules\Ai\DTOs\AiResult;
use MultiTenantSaas\Modules\Ai\Services\AiOptional;

/**
 * 内容模块 AI 增强服务
 *
 * 话术生成、质检评分、敏感词检测统一经 AiOptional（fail-open）。
 * 降级兜底：模板库推荐 / 规则评分 / 本地词库匹配。
 */
class ContentAiService
{
    public function __construct(
        private readonly AiOptional $aiOptional,
    ) {}

    /**
     * 话术/文案生成
     *
     * @param  int  $tenantId  租户 ID
     * @param  string  $scene  场景（greeting/follow_up/promotion/apology/farewell）
     * @param  array  $context  上下文（产品、客户特征、风格偏好）
     * @return AiResult output: ['candidates' => [string, ...], 'style' => string]
     */
    public function generateScript(int $tenantId, string $scene, array $context = []): AiResult
    {
        $fallback = $this->templateScript($scene);

        return $this->aiOptional->invoke(
            category: 'content.script_gen',
            fallback: $fallback,
            aiCall: function () use ($scene, $context) {
                $ai = app(AiTextServiceContract::class);
                $prompt = $this->buildScriptPrompt($scene, $context);
                $response = $ai->complete($prompt, [
                    'temperature' => 0.8,
                    'response_format' => ['type' => 'json_object'],
                ]);
                $content = $response->content ?? '{}';

                return json_decode($content, true) ?: $fallback;
            },
            options: ['timeout_ms' => 10000],
        );
    }

    /**
     * 内容质检评分
     *
     * @param  int  $tenantId  租户 ID
     * @param  string  $content  待质检的会话/内容文本
     * @param  array  $rules  质检规则配置
     * @return AiResult output: ['score' => int, 'deductions' => [...], 'suggestions' => [...]]
     */
    public function qualityScore(int $tenantId, string $content, array $rules = []): AiResult
    {
        $fallback = $this->ruleBasedScore($content, $rules);

        return $this->aiOptional->invoke(
            category: 'content.quality_score',
            fallback: $fallback,
            aiCall: function () use ($content, $rules) {
                $ai = app(AiTextServiceContract::class);
                $prompt = $this->buildQualityPrompt($content, $rules);
                $response = $ai->complete($prompt, [
                    'temperature' => 0.2,
                    'response_format' => ['type' => 'json_object'],
                ]);
                $content = $response->content ?? '{}';

                return json_decode($content, true) ?: $fallback;
            },
            options: ['timeout_ms' => 8000, 'confidence_threshold' => 0.5],
        );
    }

    /**
     * 敏感词/合规检测
     *
     * @param  int  $tenantId  租户 ID
     * @param  string  $text  待检测文本
     * @return AiResult output: ['hits' => [...], 'risk_level' => string, 'suggestion' => string]
     */
    public function sensitiveCheck(int $tenantId, string $text): AiResult
    {
        $fallback = $this->localWordCheck($text);

        return $this->aiOptional->invoke(
            category: 'content.sensitive_check',
            fallback: $fallback,
            aiCall: function () use ($text) {
                $ai = app(AiTextServiceContract::class);
                $prompt = $this->buildSensitivePrompt($text);
                $response = $ai->complete($prompt, [
                    'temperature' => 0.1,
                    'response_format' => ['type' => 'json_object'],
                ]);
                $content = $response->content ?? '{}';

                return json_decode($content, true) ?: $fallback;
            },
            options: ['timeout_ms' => 5000],
        );
    }

    // ─── 确定性兜底 ─────────────────────────────────────────────────

    /**
     * 模板库兜底：按场景返回预设话术
     */
    protected function templateScript(string $scene): array
    {
        $templates = [
            'greeting' => ['您好！很高兴为您服务，请问有什么可以帮到您的？'],
            'follow_up' => ['您好，上次沟通的内容我这边已经跟进处理了，给您同步一下进展。'],
            'promotion' => ['您好！我们最近有一个专属优惠活动，想第一时间通知您。'],
            'apology' => ['非常抱歉给您带来不便，我们会尽快处理并给您一个满意的答复。'],
            'farewell' => ['感谢您的信任与支持，后续有任何需要随时联系我，祝您生活愉快！'],
        ];

        return [
            'candidates' => $templates[$scene] ?? ['您好，请问有什么可以帮到您的？'],
            'style' => 'standard',
        ];
    }

    /**
     * 规则评分兜底：基于关键词/长度/格式
     */
    protected function ruleBasedScore(string $content, array $rules): array
    {
        $score = 100;
        $deductions = [];

        // 长度检查
        if (mb_strlen($content) < 10) {
            $score -= 20;
            $deductions[] = ['item' => '回复过短', 'points' => -20];
        }

        // 礼貌用语检查
        $politeWords = ['您好', '请', '谢谢', '感谢', '抱歉'];
        $hasPolite = false;
        foreach ($politeWords as $word) {
            if (str_contains($content, $word)) {
                $hasPolite = true;
                break;
            }
        }
        if (! $hasPolite) {
            $score -= 10;
            $deductions[] = ['item' => '缺少礼貌用语', 'points' => -10];
        }

        return [
            'score' => max(0, $score),
            'deductions' => $deductions,
            'suggestions' => empty($deductions) ? [] : ['建议增加礼貌用语，回复更详细'],
        ];
    }

    /**
     * 本地词库兜底：简单关键词匹配
     */
    protected function localWordCheck(string $text): array
    {
        // 基础敏感词列表（实际应从 SensitiveWordService 加载）
        $sensitiveWords = ['赌博', '色情', '暴力', '诈骗', '违禁'];
        $hits = [];

        foreach ($sensitiveWords as $word) {
            if (str_contains($text, $word)) {
                $hits[] = ['word' => $word, 'position' => mb_strpos($text, $word)];
            }
        }

        return [
            'hits' => $hits,
            'risk_level' => empty($hits) ? 'safe' : 'high',
            'suggestion' => empty($hits) ? '内容合规' : '包含敏感词，建议修改后发送',
        ];
    }

    // ─── Prompt 构建 ─────────────────────────────────────────────────

    protected function buildScriptPrompt(string $scene, array $context): string
    {
        $contextJson = json_encode($context, JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
你是 SCRM 话术专家。为以下场景生成 3 条候选话术。

场景: {$scene}
上下文: {$contextJson}

要求：
- 语气自然、专业、有温度
- 每条话术 50-150 字
- 适合微信/企微一对一沟通场景
- 避免过度营销感

输出 JSON：{"candidates": ["话术1", "话术2", "话术3"], "style": "风格描述"}
PROMPT;
    }

    protected function buildQualityPrompt(string $content, array $rules): string
    {
        $rulesJson = json_encode($rules, JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
你是 SCRM 质检专家。对以下客服会话内容进行质量评分。

会话内容:
{$content}

质检规则: {$rulesJson}

评分维度：服务态度、专业度、响应完整性、合规性。满分 100 分。

输出 JSON：{"score": 85, "deductions": [{"item": "扣分项", "points": -5}], "suggestions": ["改进建议"]}
PROMPT;
    }

    protected function buildSensitivePrompt(string $text): string
    {
        return <<<PROMPT
你是内容合规审核专家。检查以下文本是否包含敏感/违规内容。

待检测文本:
{$text}

检查维度：政治敏感、色情低俗、暴力恐怖、虚假宣传、隐私泄露、违禁品。

输出 JSON：{"hits": [{"word": "命中词", "category": "类别", "severity": "high/medium/low"}], "risk_level": "safe/low/medium/high", "suggestion": "处理建议"}
PROMPT;
    }
}
