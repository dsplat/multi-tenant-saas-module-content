<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\Content\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use MultiTenantSaas\Context\TenantContext;
use MultiTenantSaas\Http\Controllers\BaseController;
use MultiTenantSaas\Modules\Content\Http\Requests\CategorizeMaterialRequest;
use MultiTenantSaas\Modules\Content\Http\Requests\StoreMaterialRequest;
use MultiTenantSaas\Modules\Content\Http\Requests\UpdateMaterialRequest;
use MultiTenantSaas\Modules\Content\Models\Material;
use MultiTenantSaas\Modules\Content\Services\MaterialService;

class MaterialController extends BaseController
{
    public function __construct(
        protected MaterialService $materialService,
    ) {}

    /**
     * 素材列表 - 分页、类型筛选、分类筛选
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = (int) TenantContext::getId();

        $query = Material::where('tenant_id', $tenantId);

        // 类型筛选
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        // 分类筛选
        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        // 实体绑定筛选（activity_plan/activity/course/product…）
        if ($request->filled('entity_type')) {
            $query->where('entity_type', $request->input('entity_type'));
        }
        if ($request->filled('entity_id')) {
            $query->where('entity_id', (string) $request->input('entity_id'));
        }

        // 状态筛选
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // 搜索
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        $materials = $query->orderByDesc('created_at')
            ->orderByDesc('material_id')
            ->paginate($request->input('per_page', 20));

        return response()->json(['success' => true, 'data' => $materials]);
    }

    /**
     * 上传素材
     */
    public function store(StoreMaterialRequest $request): JsonResponse
    {
        $tenantId = (int) TenantContext::getId();

        $material = $this->materialService->create($tenantId, $request->validated());

        Log::info('MaterialController: 创建素材', [
            'tenant_id' => $tenantId,
            'material_id' => $material->material_id,
            'type' => $material->type,
        ]);

        return response()->json(['success' => true, 'data' => $material], 201);
    }

    /**
     * 素材详情
     */
    public function show($id): JsonResponse
    {
        $tenantId = (int) TenantContext::getId();

        $material = Material::where('material_id', $id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        return response()->json(['success' => true, 'data' => $material]);
    }

    /**
     * 更新素材
     */
    public function update(UpdateMaterialRequest $request, $id): JsonResponse
    {
        $tenantId = (int) TenantContext::getId();

        $material = $this->materialService->update($tenantId, $id, $request->validated());

        Log::info('MaterialController: 更新素材', [
            'tenant_id' => $tenantId,
            'material_id' => $id,
        ]);

        return response()->json(['success' => true, 'data' => $material]);
    }

    /**
     * 删除素材
     */
    public function destroy($id): JsonResponse
    {
        $tenantId = (int) TenantContext::getId();

        $material = Material::where('material_id', $id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $material->delete();

        Log::info('MaterialController: 删除素材', [
            'tenant_id' => $tenantId,
            'material_id' => $id,
        ]);

        return response()->json(['success' => true, 'message' => '素材已删除']);
    }

    /**
     * 复制素材（副本重置为草稿）
     */
    public function duplicate($id): JsonResponse
    {
        $tenantId = (int) TenantContext::getId();

        $material = $this->materialService->duplicate($tenantId, $id);

        Log::info('MaterialController: 复制素材', [
            'tenant_id' => $tenantId,
            'material_id' => $id,
            'copy_id' => $material->material_id,
        ]);

        return response()->json(['success' => true, 'data' => $material], 201);
    }

    /**
     * 分类管理
     */
    public function categorize(CategorizeMaterialRequest $request, $id): JsonResponse
    {
        $tenantId = (int) TenantContext::getId();

        $material = $this->materialService->categorize($tenantId, $id, $request->validated('category'));

        Log::info('MaterialController: 更新分类', [
            'tenant_id' => $tenantId,
            'material_id' => $id,
            'category' => $request->validated('category'),
        ]);

        return response()->json(['success' => true, 'data' => $material]);
    }

    /**
     * 合规检查
     */
    public function checkCompliance($id): JsonResponse
    {
        $tenantId = (int) TenantContext::getId();

        $result = $this->materialService->checkCompliance($tenantId, $id);

        return response()->json(['success' => true, 'data' => $result]);
    }
}
