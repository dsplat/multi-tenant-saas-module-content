<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\Content\Services;

use Illuminate\Database\Eloquent\Collection;
use MultiTenantSaas\Context\TenantContext;
use MultiTenantSaas\Contracts\IdGeneratorContract;
use MultiTenantSaas\Modules\Content\Models\SensitiveWord;

class ContentComplianceService
{
    public function __construct(
        protected IdGeneratorContract $idGenerator,
    ) {}

    public function check($tenantId, string $content, string $type = 'text'): array
    {
        TenantContext::setTenantId((string) $tenantId);

        $words = SensitiveWord::where('tenant_id', $tenantId)->get();
        $hits = [];

        foreach ($words as $word) {
            if (str_contains($content, $word->word)) {
                $hits[] = $word->word;
            }
        }

        return [
            'passed' => empty($hits),
            'hits' => $hits,
            'hit_count' => count($hits),
            'type' => $type,
        ];
    }

    public function getRules($tenantId): Collection
    {
        TenantContext::setTenantId((string) $tenantId);

        return SensitiveWord::where('tenant_id', $tenantId)->get();
    }

    public function addRule($tenantId, array $rule): int
    {
        TenantContext::setTenantId((string) $tenantId);

        $word = SensitiveWord::create([
            'sensitive_word_id' => $this->idGenerator->generate(),
            'tenant_id' => $tenantId,
            'word' => $rule['word'],
            'category' => $rule['category'] ?? 'default',
            'severity' => $rule['severity'] ?? 'medium',
        ]);

        return (int) $word->sensitive_word_id;
    }
}
