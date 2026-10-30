<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\Content\Services;

use Illuminate\Database\Eloquent\Collection;
use MultiTenantSaas\Context\TenantContext;
use MultiTenantSaas\Contracts\IdGeneratorContract;
use MultiTenantSaas\Modules\Content\Models\Material;
use MultiTenantSaas\Modules\Content\Models\SensitiveWord;

class MaterialService
{
    public function __construct(
        protected IdGeneratorContract $idGenerator,
    ) {}

    public function create($tenantId, array $data): Material
    {
        TenantContext::setTenantId((string) $tenantId);

        return Material::create([
            'material_id' => $this->idGenerator->generate(),
            'tenant_id' => $tenantId,
            'title' => $data['title'],
            'type' => $data['type'] ?? 'text',
            'content' => $data['content'] ?? '',
            'file_url' => $data['file_url'] ?? null,
            'file_size' => $data['file_size'] ?? 0,
            'mime_type' => $data['mime_type'] ?? null,
            'category' => $data['category'] ?? 'general',
            'status' => $data['status'] ?? 'active',
            // 通用实体绑定（activity/course/product…），可空=通用素材
            'entity_type' => $data['entity_type'] ?? null,
            'entity_id' => $data['entity_id'] ?? null,
        ]);
    }

    /**
     * 素材列表（分页；供侧边栏等只读场景复用）
     *
     * @param  array<string, mixed>  $filters  type/category/status/entity_type/entity_id/search
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    public function list($tenantId, array $filters = [], int $perPage = 20): array
    {
        TenantContext::setTenantId((string) $tenantId);

        $query = Material::where('tenant_id', $tenantId);

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['entity_type'])) {
            $query->where('entity_type', $filters['entity_type']);
        }
        if (! empty($filters['entity_id'])) {
            $query->where('entity_id', (string) $filters['entity_id']);
        }
        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        $paginator = $query->orderByDesc('created_at')
            ->orderByDesc('material_id')
            ->paginate(max(1, $perPage));

        return [
            'items' => array_map(fn (Material $m) => $m->toArray(), $paginator->items()),
            'total' => $paginator->total(),
        ];
    }

    public function update($tenantId, $materialId, array $data): Material
    {
        TenantContext::setTenantId((string) $tenantId);

        $material = Material::where('material_id', $materialId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $updateFields = [];
        foreach (['title', 'type', 'content', 'file_url', 'file_size', 'mime_type', 'category', 'status', 'entity_type', 'entity_id'] as $field) {
            if (array_key_exists($field, $data)) {
                $updateFields[$field] = $data[$field];
            }
        }

        $material->update($updateFields);

        return $material->fresh();
    }

    /**
     * 复制素材为草稿副本（内容照抄、新 ID、status 重置 draft、保留实体绑定）
     */
    public function duplicate($tenantId, $materialId): Material
    {
        TenantContext::setTenantId((string) $tenantId);

        $material = Material::where('material_id', $materialId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        return Material::create([
            'material_id' => $this->idGenerator->generate(),
            'tenant_id' => $tenantId,
            'title' => $material->title . '（副本）',
            'type' => $material->type,
            'content' => $material->content,
            'file_url' => $material->file_url,
            'file_size' => $material->file_size,
            'mime_type' => $material->mime_type,
            'category' => $material->category,
            'status' => 'draft',
            'entity_type' => $material->entity_type,
            'entity_id' => $material->entity_id,
        ]);
    }

    public function categorize($tenantId, $materialId, string $category): Material
    {
        TenantContext::setTenantId((string) $tenantId);

        $material = Material::where('material_id', $materialId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $material->update(['category' => $category]);

        return $material->fresh();
    }

    /**
     * 合规检查 - 检测素材中的敏感词
     *
     * @return array<string, mixed>
     */
    public function checkCompliance($tenantId, $materialId): array
    {
        TenantContext::setTenantId((string) $tenantId);

        $material = Material::where('material_id', $materialId)
            ->where('tenant_id', $tenantId)
            ->first();

        if ($material === null) {
            return [
                'material_id' => $materialId,
                'compliant' => true,
                'violations' => [],
                'violation_count' => 0,
                'checked_at' => now()->toIso8601String(),
            ];
        }

        $content = ($material->title ?? '') . ' ' . ($material->content ?? '');

        $sensitiveWords = SensitiveWord::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->get(['word', 'severity']);

        $violations = [];
        foreach ($sensitiveWords as $sensitiveWord) {
            if (mb_strpos($content, $sensitiveWord->word) !== false) {
                $violations[] = [
                    'word' => $sensitiveWord->word,
                    'severity' => $sensitiveWord->severity,
                ];
            }
        }

        return [
            'material_id' => $materialId,
            'compliant' => empty($violations),
            'violations' => $violations,
            'violation_count' => count($violations),
            'checked_at' => now()->toIso8601String(),
        ];
    }

    public function search($tenantId, string $keyword): Collection
    {
        TenantContext::setTenantId((string) $tenantId);

        return Material::where('tenant_id', $tenantId)
            ->where(function ($q) use ($keyword) {
                $q->where('title', 'like', "%{$keyword}%")
                    ->orWhere('content', 'like', "%{$keyword}%");
            })
            ->get();
    }

    public function delete($tenantId, $materialId): bool
    {
        TenantContext::setTenantId((string) $tenantId);

        $material = Material::where('material_id', $materialId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        return $material->delete() !== false;
    }
}
