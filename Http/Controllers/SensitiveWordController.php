<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\Content\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use MultiTenantSaas\Context\TenantContext;
use MultiTenantSaas\Http\Controllers\BaseController;
use MultiTenantSaas\Modules\Content\Models\SensitiveWord;
use MultiTenantSaas\Modules\Content\Services\SensitiveWordService;

class SensitiveWordController extends BaseController
{
    public function __construct(
        protected SensitiveWordService $sensitiveWordService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tenantId = (int) TenantContext::getId();
        $query = SensitiveWord::where('tenant_id', $tenantId);

        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }
        if ($level = $request->input('level')) {
            $query->where('severity', SensitiveWordService::LEVEL_SEVERITY[(int) $level] ?? 'medium');
        } elseif ($severity = $request->input('severity')) {
            $query->where('severity', $severity);
        }

        $perPage = (int) $request->input('per_page', 20);
        $words = $query->orderByDesc('sensitive_word_id')->paginate($perPage);

        return response()->json(['success' => true, 'data' => $words]);
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = (int) TenantContext::getId();
        $validated = $request->validate([
            'word' => 'required|string|max:100',
            'category' => 'required|string|max:50',
            'level' => 'required|integer|in:1,2,3',
        ]);

        $word = SensitiveWord::create([
            'tenant_id' => $tenantId,
            'word' => $validated['word'],
            'category' => $validated['category'],
            'severity' => SensitiveWordService::LEVEL_SEVERITY[$validated['level']],
        ]);

        return response()->json(['success' => true, 'data' => $word], 201);
    }

    public function show($id): JsonResponse
    {
        $tenantId = (int) TenantContext::getId();
        $word = SensitiveWord::where('tenant_id', $tenantId)->where('sensitive_word_id', $id)->firstOrFail();

        return response()->json(['success' => true, 'data' => $word]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $tenantId = (int) TenantContext::getId();
        $word = SensitiveWord::where('tenant_id', $tenantId)->where('sensitive_word_id', $id)->firstOrFail();

        $validated = $request->validate([
            'word' => 'sometimes|string|max:100',
            'category' => 'sometimes|string|max:50',
            'level' => 'sometimes|integer|in:1,2,3',
        ]);

        if (isset($validated['level'])) {
            $validated['severity'] = SensitiveWordService::LEVEL_SEVERITY[$validated['level']];
            unset($validated['level']);
        }

        $word->update($validated);

        return response()->json(['success' => true, 'data' => $word]);
    }

    public function destroy($id): JsonResponse
    {
        $tenantId = (int) TenantContext::getId();
        SensitiveWord::where('tenant_id', $tenantId)->where('sensitive_word_id', $id)->delete();

        return response()->json(null, 204);
    }

    /**
     * 批量导入
     */
    public function import(Request $request): JsonResponse
    {
        $tenantId = (int) TenantContext::getId();
        $validated = $request->validate([
            'words' => 'required|array',
            'words.*.word' => 'required|string|max:100',
            'words.*.category' => 'required|string|max:50',
            'words.*.level' => 'required|integer|in:1,2,3',
        ]);

        $count = $this->sensitiveWordService->batchImport($tenantId, $validated['words']);

        return response()->json(['success' => true, 'message' => '导入成功', 'count' => $count]);
    }
}
