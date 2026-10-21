<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\Content\Services;

use Illuminate\Support\Collection;
use MultiTenantSaas\Context\TenantContext;
use MultiTenantSaas\Modules\Content\Models\Material;

class MaterialCategoryService
{
    public function createCategory($tenantId, array $data): array
    {
        TenantContext::setTenantId((string) $tenantId);

        return [
            'name' => $data['name'],
            'type' => $data['type'] ?? 'material',
            'status' => 'active',
        ];
    }

    public function getCategories($tenantId): Collection
    {
        TenantContext::setTenantId((string) $tenantId);

        return Material::where('tenant_id', $tenantId)
            ->whereNotNull('category')
            ->select('category')
            ->distinct()
            ->get()
            ->pluck('category');
    }

    public function moveToCategory($tenantId, $materialId, $categoryId): bool
    {
        TenantContext::setTenantId((string) $tenantId);

        return Material::where('material_id', $materialId)
            ->where('tenant_id', $tenantId)
            ->update(['category' => (string) $categoryId]) > 0;
    }
}
