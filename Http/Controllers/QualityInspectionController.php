<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\Content\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use MultiTenantSaas\Context\TenantContext;
use MultiTenantSaas\Http\Controllers\BaseController;
use MultiTenantSaas\Modules\Content\Services\QualityInspectionService;

class QualityInspectionController extends BaseController
{
    public function __construct(
        protected QualityInspectionService $inspectionService,
    ) {}

    // ========== 质检规则 ==========

    public function rules(Request $request): JsonResponse
    {
        $tenantId = (int) TenantContext::getId();

        $validated = $request->validate([
            'type' => 'nullable|string|in:sensitive_word,over_promise,compliance,custom',
            'severity' => 'nullable|string|in:low,medium,high,critical',
            'status' => 'nullable|string|in:active,inactive',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $result = $this->inspectionService->listRules($tenantId, $validated);

        return response()->json(['success' => true, 'data' => $result]);
    }

    public function storeRule(Request $request): JsonResponse
    {
        $tenantId = (int) TenantContext::getId();

        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'type' => 'required|string|in:sensitive_word,over_promise,compliance,custom',
            'conditions' => 'required|array',
            'action' => 'required|string|in:warn,block,notify',
            'severity' => 'required|string|in:low,medium,high,critical',
            'priority' => 'nullable|integer|min:0|max:100',
        ]);

        $rule = $this->inspectionService->createRule($tenantId, $validated);

        return response()->json(['success' => true, 'data' => $rule], 201);
    }

    public function updateRule(Request $request, string $id): JsonResponse
    {
        $tenantId = (int) TenantContext::getId();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:200',
            'type' => 'sometimes|string|in:sensitive_word,over_promise,compliance,custom',
            'conditions' => 'sometimes|array',
            'action' => 'sometimes|string|in:warn,block,notify',
            'severity' => 'sometimes|string|in:low,medium,high,critical',
            'priority' => 'nullable|integer|min:0|max:100',
        ]);

        $rule = $this->inspectionService->updateRule($tenantId, (int) $id, $validated);

        return response()->json(['success' => true, 'data' => $rule]);
    }

    public function destroyRule(string $id): JsonResponse
    {
        $tenantId = (int) TenantContext::getId();
        $this->inspectionService->deleteRule($tenantId, (int) $id);

        return response()->json(['success' => true, 'message' => '已删除']);
    }

    public function toggleRule(string $id): JsonResponse
    {
        $tenantId = (int) TenantContext::getId();
        $rule = $this->inspectionService->toggleRule($tenantId, (int) $id);

        return response()->json(['success' => true, 'data' => $rule]);
    }

    // ========== 质检结果 ==========

    public function results(Request $request): JsonResponse
    {
        $tenantId = (int) TenantContext::getId();

        $validated = $request->validate([
            'source_type' => 'nullable|string|in:chat_message,mass_push,moment',
            'severity' => 'nullable|string|in:low,medium,high,critical',
            'status' => 'nullable|string|in:pending,confirmed,dismissed',
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $result = $this->inspectionService->listResults($tenantId, $validated);

        return response()->json(['success' => true, 'data' => $result]);
    }

    public function showResult(string $id): JsonResponse
    {
        $tenantId = (int) TenantContext::getId();
        $result = $this->inspectionService->getResult($tenantId, (int) $id);

        return response()->json(['success' => true, 'data' => $result]);
    }

    public function reviewResult(Request $request, string $id): JsonResponse
    {
        $tenantId = (int) TenantContext::getId();

        $validated = $request->validate([
            'action' => 'required|string|in:confirm,dismiss',
        ]);

        $result = $this->inspectionService->reviewResult($tenantId, (int) $id, $validated['action']);

        return response()->json(['success' => true, 'data' => $result]);
    }

    public function resultStats(Request $request): JsonResponse
    {
        $tenantId = (int) TenantContext::getId();

        $validated = $request->validate([
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d',
        ]);

        $stats = $this->inspectionService->resultStats($tenantId, $validated['start_date'] ?? null, $validated['end_date'] ?? null);

        return response()->json(['success' => true, 'data' => $stats]);
    }

    // ========== 预警 ==========

    public function alerts(Request $request): JsonResponse
    {
        $tenantId = (int) TenantContext::getId();

        $validated = $request->validate([
            'status' => 'nullable|string|in:pending,sent,handled',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $result = $this->inspectionService->listAlerts($tenantId, $validated);

        return response()->json(['success' => true, 'data' => $result]);
    }

    public function handleAlert(Request $request, string $id): JsonResponse
    {
        $tenantId = (int) TenantContext::getId();
        $alert = $this->inspectionService->handleAlert($tenantId, (int) $id);

        return response()->json(['success' => true, 'data' => $alert]);
    }
}
