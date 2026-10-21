<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\Content\Services;

use MultiTenantSaas\Context\TenantContext;
use MultiTenantSaas\Modules\Content\Models\Material;

class MaterialShareService
{
    public function createShareLink($tenantId, $materialId): string
    {
        TenantContext::setTenantId((string) $tenantId);

        Material::where('material_id', $materialId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $token = substr(md5($materialId . $tenantId . time()), 0, 16);

        return "https://scrm.example.com/share/{$token}";
    }

    public function getShareStats($tenantId, $materialId): array
    {
        TenantContext::setTenantId((string) $tenantId);

        Material::where('material_id', $materialId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        return [
            'material_id' => $materialId,
            'share_count' => 0,
            'view_count' => 0,
        ];
    }
}
