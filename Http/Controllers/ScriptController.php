<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\Content\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use MultiTenantSaas\Context\TenantContext;
use MultiTenantSaas\Http\Controllers\BaseController;
use MultiTenantSaas\Modules\Content\Services\ScriptService;

class ScriptController extends BaseController
{
    public function __construct(
        protected ScriptService $scriptService,
    ) {}

    /**
     * 话术列表
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category' => 'nullable|string|max:100',
            'status' => 'nullable|string|in:draft,approved,archived',
            'keyword' => 'nullable|string|max:200',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $tenantId = (int) TenantContext::getId();
        $scripts = $this->scriptService->getScripts(
            $tenantId,
            $validated['category'] ?? null,
            $validated['per_page'] ?? 20,
            $validated['status'] ?? null,
            $validated['keyword'] ?? null,
        );

        return response()->json(['success' => true, 'data' => $scripts]);
    }

    /**
     * 搜索话术
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'keyword' => 'required|string|max:200',
        ]);

        $tenantId = (int) TenantContext::getId();
        $scripts = $this->scriptService->search($tenantId, $validated['keyword']);

        return response()->json(['success' => true, 'data' => $scripts]);
    }

    /**
     * 话术详情
     */
    public function show($id): JsonResponse
    {
        $tenantId = (int) TenantContext::getId();
        $script = $this->scriptService->getScript($tenantId, $id);

        return response()->json(['success' => true, 'data' => $script]);
    }

    /**
     * 创建话术
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'string|max:100',
            'content' => 'string|max:5000',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'variables' => 'nullable|array',
            'status' => 'nullable|string|in:draft,approved,archived',
        ]);

        $tenantId = (int) TenantContext::getId();
        $script = $this->scriptService->create($tenantId, $validated);

        return response()->json(['success' => true, 'data' => $script], 201);
    }

    /**
     * 更新话术
     */
    public function update(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'string|max:255',
            'category' => 'string|max:100',
            'content' => 'string|max:5000',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'variables' => 'nullable|array',
        ]);

        $tenantId = (int) TenantContext::getId();
        $script = $this->scriptService->update($tenantId, $id, $validated);

        return response()->json(['success' => true, 'data' => $script]);
    }

    /**
     * 删除话术
     */
    public function destroy($id): JsonResponse
    {
        $tenantId = (int) TenantContext::getId();
        $this->scriptService->delete($tenantId, $id);

        return response()->json(['success' => true, 'message' => '话术已删除']);
    }

    /**
     * 复制话术（副本重置为草稿）
     */
    public function duplicate($id): JsonResponse
    {
        $tenantId = (int) TenantContext::getId();
        $script = $this->scriptService->duplicate($tenantId, $id);

        return response()->json(['success' => true, 'data' => $script], 201);
    }

    /**
     * 话术分类列表
     */
    public function categories(): JsonResponse
    {
        $tenantId = (int) TenantContext::getId();
        $categories = $this->scriptService->getCategories($tenantId);

        return response()->json(['success' => true, 'data' => $categories]);
    }

    /**
     * 使用统计
     */
    public function stats(): JsonResponse
    {
        $tenantId = (int) TenantContext::getId();
        $stats = $this->scriptService->getUsageStats($tenantId);

        return response()->json(['success' => true, 'data' => $stats]);
    }

    /**
     * 记录使用
     */
    public function recordUsage($id): JsonResponse
    {
        $tenantId = (int) TenantContext::getId();
        $this->scriptService->incrementUsage($tenantId, $id);

        return response()->json(['success' => true, 'message' => '使用已记录']);
    }

    /**
     * AI 推荐话术
     */
    public function recommend(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'context' => 'required|string|max:500',
            'limit' => 'nullable|integer|min:1|max:20',
        ]);

        $tenantId = (int) TenantContext::getId();
        $result = $this->scriptService->recommend($tenantId, $validated['context'], $validated['limit'] ?? 5);

        return response()->json(['success' => true, 'data' => $result]);
    }
}
