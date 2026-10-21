<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\Content\Services;

use Illuminate\Database\Eloquent\Collection;
use MultiTenantSaas\Context\TenantContext;
use MultiTenantSaas\Contracts\IdGeneratorContract;
use MultiTenantSaas\Modules\Content\Models\Script;

class ScriptService
{
    public function __construct(
        protected IdGeneratorContract $idGenerator,
    ) {}

    public function create($tenantId, array $data): Script
    {
        TenantContext::setTenantId((string) $tenantId);

        return Script::create([
            'script_id' => $this->idGenerator->generate(),
            'tenant_id' => $tenantId,
            'title' => $data['title'],
            'category' => $data['category'] ?? 'general',
            'content' => $data['content'] ?? '',
            'tags' => $data['tags'] ?? [],
            'variables' => $data['variables'] ?? [],
            'usage_count' => 0,
            'status' => $data['status'] ?? 'draft',
            // 通用实体绑定（activity/course/product…），可空=通用话术
            'entity_type' => $data['entity_type'] ?? null,
            'entity_id' => $data['entity_id'] ?? null,
        ]);
    }

    public function search($tenantId, string $keyword): Collection
    {
        TenantContext::setTenantId((string) $tenantId);

        return Script::where('tenant_id', $tenantId)
            ->where(function ($q) use ($keyword) {
                $q->where('title', 'like', "%{$keyword}%")
                    ->orWhere('content', 'like', "%{$keyword}%");
            })
            ->get();
    }

    public function incrementUsage($tenantId, $scriptId): void
    {
        TenantContext::setTenantId((string) $tenantId);

        Script::where('script_id', $scriptId)
            ->where('tenant_id', $tenantId)
            ->increment('usage_count');
    }

    public function getScripts($tenantId, ?string $category = null, $perPage = 20, ?string $status = null, ?string $keyword = null): array
    {
        TenantContext::setTenantId((string) $tenantId);

        $query = Script::where('tenant_id', $tenantId);
        if ($category !== null) {
            $query->where('category', $category);
        }
        if ($status !== null) {
            $query->where('status', $status);
        }
        if ($keyword !== null && $keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('title', 'like', "%{$keyword}%")
                    ->orWhere('content', 'like', "%{$keyword}%");
            });
        }

        $paginator = $query->orderByDesc('created_at')
            ->orderByDesc('script_id')
            ->paginate($perPage);

        return [
            'data' => $paginator->items(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    /**
     * 复制话术为草稿副本（内容照抄、新 ID、usage_count 清零、status 重置 draft、保留实体绑定）
     */
    public function duplicate($tenantId, $scriptId): Script
    {
        TenantContext::setTenantId((string) $tenantId);

        $script = Script::where('script_id', $scriptId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        return Script::create([
            'script_id' => $this->idGenerator->generate(),
            'tenant_id' => $tenantId,
            'title' => $script->title . '（副本）',
            'category' => $script->category,
            'content' => $script->content,
            'tags' => $script->tags,
            'variables' => $script->variables,
            'usage_count' => 0,
            'status' => 'draft',
            'entity_type' => $script->entity_type,
            'entity_id' => $script->entity_id,
        ]);
    }

    public function getScript($tenantId, $scriptId): Script
    {
        TenantContext::setTenantId((string) $tenantId);

        return Script::where('script_id', $scriptId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();
    }

    public function update($tenantId, $scriptId, array $data): Script
    {
        TenantContext::setTenantId((string) $tenantId);

        $script = Script::where('script_id', $scriptId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $fillable = ['title', 'category', 'content', 'tags', 'variables', 'status'];
        $script->update(array_intersect_key($data, array_flip($fillable)));

        return $script->fresh();
    }

    public function delete($tenantId, $scriptId): bool
    {
        TenantContext::setTenantId((string) $tenantId);

        $script = Script::where('script_id', $scriptId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        return (bool) $script->delete();
    }

    public function getCategories($tenantId): array
    {
        TenantContext::setTenantId((string) $tenantId);

        return Script::where('tenant_id', $tenantId)
            ->selectRaw('category, COUNT(*) as count')
            ->groupBy('category')
            ->orderByDesc('count')
            ->get()
            ->toArray();
    }

    public function getUsageStats($tenantId): array
    {
        TenantContext::setTenantId((string) $tenantId);

        $stats = Script::where('tenant_id', $tenantId)
            ->selectRaw('COUNT(*) as total, SUM(usage_count) as total_usage, AVG(usage_count) as avg_usage')
            ->first();

        $topScripts = Script::where('tenant_id', $tenantId)
            ->orderByDesc('usage_count')
            ->orderByDesc('script_id')
            ->limit(10)
            ->get(['script_id', 'title', 'category', 'usage_count'])
            ->toArray();

        return [
            'total_scripts' => (int) ($stats->total ?? 0),
            'total_usage' => (int) ($stats->total_usage ?? 0),
            'avg_usage' => round((float) ($stats->avg_usage ?? 0), 1),
            'top_scripts' => $topScripts,
        ];
    }

    public function recommend($tenantId, string $context, $limit = 5): array
    {
        TenantContext::setTenantId((string) $tenantId);

        // 分词提取关键词（按空格/标点分割，取长度>=2的词）
        $words = preg_split('/[\s,，。.!！?？;；：:]+/u', $context);
        $keywords = array_filter($words, fn ($w) => mb_strlen($w) >= 2);

        if (empty($keywords)) {
            // 无有效关键词时，返回最常用话术
            $scripts = Script::where('tenant_id', $tenantId)
                ->orderByDesc('usage_count')
                ->orderByDesc('script_id')
                ->limit($limit)
                ->get();
        } else {
            // 对每个关键词做 LIKE 匹配，按匹配数+使用次数排序
            $query = Script::where('tenant_id', $tenantId);
            $query->where(function ($q) use ($keywords) {
                foreach (array_slice($keywords, 0, 5) as $keyword) {
                    $q->orWhere('content', 'like', "%{$keyword}%")
                        ->orWhere('title', 'like', "%{$keyword}%");
                }
            });
            $scripts = $query->orderByDesc('usage_count')
                ->orderByDesc('script_id')
                ->limit($limit)
                ->get();
        }

        return [
            'context' => $context,
            'keywords' => array_values(array_slice($keywords, 0, 5)),
            'recommendations' => $scripts->toArray(),
        ];
    }
}
