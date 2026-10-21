<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\Content\Services;

use Illuminate\Support\Facades\DB;
use MultiTenantSaas\Context\TenantContext;
use MultiTenantSaas\Contracts\IdGeneratorContract;
use MultiTenantSaas\Modules\Content\Models\SensitiveWord;

class SensitiveWordService
{
    /**
     * 数字等级（1/2/3）与存量 severity 字符串的双向映射。
     */
    public const LEVEL_SEVERITY = [
        1 => 'low',
        2 => 'medium',
        3 => 'high',
    ];

    public function __construct(
        protected IdGeneratorContract $idGenerator,
    ) {}

    /**
     * 批量导入敏感词
     *
     * 输入兼容控制台的 `level`（1/2/3）与既有 `severity`（low/medium/high）。
     * 主键由 IdGenerator 生成，不能依赖数据库自增。
     *
     * @param  array<int, array<string, mixed>>  $words
     */
    public function batchImport(int $tenantId, array $words): int
    {
        TenantContext::setTenantId((string) $tenantId);

        $now = now();
        $rows = [];

        foreach ($words as $word) {
            $rows[] = [
                'sensitive_word_id' => $this->idGenerator->generate(),
                'tenant_id' => $tenantId,
                'word' => $word['word'],
                'category' => $word['category'] ?? 'general',
                'severity' => $this->severity($word),
                'is_regex' => (bool) ($word['is_regex'] ?? false),
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows === []) {
            return 0;
        }

        DB::table('sensitive_words')->insert($rows);

        return count($rows);
    }

    /**
     * 按租户词库检测文本（质检流水线的全局词库分支）。
     *
     * @return array<int, array{word: string, severity: string}>
     */
    public function checkContent(int $tenantId, string $content): array
    {
        TenantContext::setTenantId((string) $tenantId);

        $words = SensitiveWord::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->orderBy('word')
            ->orderBy('sensitive_word_id')
            ->get(['sensitive_word_id', 'word', 'severity', 'is_regex']);

        $hits = [];

        foreach ($words as $word) {
            $matched = $word->is_regex
                ? @preg_match($word->word, $content) === 1
                : mb_stripos($content, $word->word) !== false;

            if ($matched) {
                $hits[] = ['word' => $word->word, 'severity' => $word->severity];
            }
        }

        return $hits;
    }

    /**
     * @param  array<string, mixed>  $word
     */
    private function severity(array $word): string
    {
        if (isset($word['severity']) && $word['severity'] !== '') {
            return (string) $word['severity'];
        }

        return self::LEVEL_SEVERITY[(int) ($word['level'] ?? 2)] ?? 'medium';
    }
}
