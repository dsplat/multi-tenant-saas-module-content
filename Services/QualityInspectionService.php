<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\Content\Services;

use MultiTenantSaas\Context\TenantContext;
use MultiTenantSaas\Contracts\IdGeneratorContract;
use MultiTenantSaas\Modules\Content\Models\InspectionAlert;
use MultiTenantSaas\Modules\Content\Models\InspectionResult;
use MultiTenantSaas\Modules\Content\Models\InspectionRule;

class QualityInspectionService
{
    public function __construct(
        protected IdGeneratorContract $idGenerator,
    ) {}

    // ========== 质检规则 CRUD ==========

    public function listRules(int $tenantId, array $filters): array
    {
        TenantContext::setTenantId((string) $tenantId);

        $query = InspectionRule::where('tenant_id', $tenantId);

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (! empty($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $paginator = $query->orderByDesc('priority')
            ->orderByDesc('created_at')
            ->orderByDesc('inspection_rule_id')
            ->paginate($filters['per_page'] ?? 20);

        return [
            'data' => $paginator->items(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
        ];
    }

    public function createRule(int $tenantId, array $data): InspectionRule
    {
        TenantContext::setTenantId((string) $tenantId);

        return InspectionRule::create([
            'inspection_rule_id' => $this->idGenerator->generate(),
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'type' => $data['type'],
            'conditions' => $data['conditions'],
            'action' => $data['action'],
            'severity' => $data['severity'],
            'priority' => $data['priority'] ?? 0,
            'status' => 'active',
        ]);
    }

    public function updateRule(int $tenantId, int $ruleId, array $data): InspectionRule
    {
        TenantContext::setTenantId((string) $tenantId);

        $rule = InspectionRule::where('inspection_rule_id', $ruleId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $rule->update($data);

        return $rule->fresh();
    }

    public function deleteRule(int $tenantId, int $ruleId): bool
    {
        TenantContext::setTenantId((string) $tenantId);

        $rule = InspectionRule::where('inspection_rule_id', $ruleId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        return (bool) $rule->delete();
    }

    public function toggleRule(int $tenantId, int $ruleId): InspectionRule
    {
        TenantContext::setTenantId((string) $tenantId);

        $rule = InspectionRule::where('inspection_rule_id', $ruleId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $rule->update(['status' => $rule->status === 'active' ? 'inactive' : 'active']);

        return $rule->fresh();
    }

    // ========== 质检结果 ==========

    public function listResults(int $tenantId, array $filters): array
    {
        TenantContext::setTenantId((string) $tenantId);

        $query = InspectionResult::where('tenant_id', $tenantId)->with('rule');

        if (! empty($filters['source_type'])) {
            $query->where('source_type', $filters['source_type']);
        }
        if (! empty($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date']);
        }
        if (! empty($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date'] . ' 23:59:59');
        }

        $paginator = $query->orderByDesc('created_at')
            ->orderByDesc('inspection_result_id')
            ->paginate($filters['per_page'] ?? 20);

        return [
            'data' => $paginator->items(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
        ];
    }

    public function getResult(int $tenantId, int $resultId): InspectionResult
    {
        TenantContext::setTenantId((string) $tenantId);

        return InspectionResult::where('inspection_result_id', $resultId)
            ->where('tenant_id', $tenantId)
            ->with(['rule', 'alerts'])
            ->firstOrFail();
    }

    public function reviewResult(int $tenantId, int $resultId, string $action): InspectionResult
    {
        TenantContext::setTenantId((string) $tenantId);

        $result = InspectionResult::where('inspection_result_id', $resultId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $result->update([
            'status' => $action === 'confirm' ? 'confirmed' : 'dismissed',
            'reviewed_at' => now(),
        ]);

        return $result->fresh();
    }

    public function resultStats(int $tenantId, ?string $startDate = null, ?string $endDate = null): array
    {
        TenantContext::setTenantId((string) $tenantId);

        $query = InspectionResult::where('tenant_id', $tenantId);

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('created_at', '<=', $endDate . ' 23:59:59');
        }

        $stats = $query->selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
            SUM(CASE WHEN status = 'dismissed' THEN 1 ELSE 0 END) as dismissed,
            SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) as critical,
            SUM(CASE WHEN severity = 'high' THEN 1 ELSE 0 END) as high,
            SUM(CASE WHEN severity = 'medium' THEN 1 ELSE 0 END) as medium,
            SUM(CASE WHEN severity = 'low' THEN 1 ELSE 0 END) as low
        ")->first();

        // 按规则类型分布
        $byType = InspectionResult::where('inspection_results.tenant_id', $tenantId)
            ->when($startDate, fn ($q) => $q->where('inspection_results.created_at', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->where('inspection_results.created_at', '<=', $endDate . ' 23:59:59'))
            ->join('inspection_rules', 'inspection_results.rule_id', '=', 'inspection_rules.inspection_rule_id')
            ->selectRaw('inspection_rules.type, COUNT(*) as count')
            ->groupBy('inspection_rules.type')
            ->pluck('count', 'type')
            ->toArray();

        return [
            'total' => (int) ($stats->total ?? 0),
            'pending' => (int) ($stats->pending ?? 0),
            'confirmed' => (int) ($stats->confirmed ?? 0),
            'dismissed' => (int) ($stats->dismissed ?? 0),
            'critical' => (int) ($stats->critical ?? 0),
            'high' => (int) ($stats->high ?? 0),
            'medium' => (int) ($stats->medium ?? 0),
            'low' => (int) ($stats->low ?? 0),
            'by_type' => $byType,
        ];
    }

    // ========== 预警 ==========

    public function listAlerts(int $tenantId, array $filters): array
    {
        TenantContext::setTenantId((string) $tenantId);

        $query = InspectionAlert::where('tenant_id', $tenantId)->with('result.rule');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $paginator = $query->orderByDesc('created_at')
            ->orderByDesc('alert_id')
            ->paginate($filters['per_page'] ?? 20);

        return [
            'data' => $paginator->items(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
        ];
    }

    public function handleAlert(int $tenantId, int $alertId): InspectionAlert
    {
        TenantContext::setTenantId((string) $tenantId);

        $alert = InspectionAlert::where('alert_id', $alertId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $alert->update([
            'status' => 'handled',
            'handled_at' => now(),
        ]);

        return $alert->fresh();
    }
}
