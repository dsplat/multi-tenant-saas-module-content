<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\Content\Services;

use MultiTenantSaas\Modules\Content\Contracts\ContentAudienceTaggerContract;

/**
 * 未接线标签系统时的空实现：打标结果为空，不影响行为统计与素材分析。
 */
class NullContentAudienceTagger implements ContentAudienceTaggerContract
{
    public function applyTag(int $tenantId, int $userId, string $tagName): int|string|null
    {
        return null;
    }
}
