<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\Content\Services;

use Illuminate\Database\Eloquent\Collection;
use MultiTenantSaas\Context\TenantContext;
use MultiTenantSaas\Modules\Content\Models\Material;

class MaterialStatsService
{
    public function getUsageStats($tenantId): array
    {
        TenantContext::setTenantId((string) $tenantId);

        $totalCount = Material::where('tenant_id', $tenantId)->count();
        $activeCount = Material::where('tenant_id', $tenantId)->where('status', 'active')->count();

        return [
            'total_materials' => $totalCount,
            'active_materials' => $activeCount,
        ];
    }

    public function getTopMaterials($tenantId, $limit = 10): Collection
    {
        TenantContext::setTenantId((string) $tenantId);

        return Material::where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->orderByDesc('material_id')
            ->limit($limit)
            ->get();
    }
}
