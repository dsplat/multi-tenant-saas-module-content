<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\Content\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use MultiTenantSaas\Context\TenantContext;
use MultiTenantSaas\Contracts\IdGeneratorContract;
use MultiTenantSaas\Modules\Content\Contracts\ContentAudienceTaggerContract;
use MultiTenantSaas\Modules\Content\Models\MaterialEvent;

/**
 * 内容埋点追踪和客户行为分析服务
 */
class MaterialRadarService
{
    public function __construct(
        protected IdGeneratorContract $idGenerator,
        protected ContentAudienceTaggerContract $audienceTagger,
    ) {}

    /**
     * 追踪素材打开事件
     *
     * @return array<string, mixed>
     */
    public function trackView($tenantId, $materialId, $userId, array $metadata = []): array
    {
        TenantContext::setTenantId((string) $tenantId);

        $event = MaterialEvent::create([
            'event_id' => $this->idGenerator->generate(),
            'tenant_id' => $tenantId,
            'material_id' => $materialId,
            'user_id' => $userId,
            'event_type' => 'viewed',
            'duration' => 0,
            'occurred_at' => now(),
            'metadata' => $metadata,
        ]);

        return $event->toArray();
    }

    /**
     * 追踪阅读时长
     *
     * @return array<string, mixed>
     */
    public function trackReadDuration($tenantId, $materialId, $userId, $duration, array $metadata = []): array
    {
        TenantContext::setTenantId((string) $tenantId);

        $event = MaterialEvent::create([
            'event_id' => $this->idGenerator->generate(),
            'tenant_id' => $tenantId,
            'material_id' => $materialId,
            'user_id' => $userId,
            'event_type' => 'read',
            'duration' => $duration,
            'occurred_at' => now(),
            'metadata' => $metadata,
        ]);

        return $event->toArray();
    }

    /**
     * 追踪分享行为
     *
     * @return array<string, mixed>
     */
    public function trackShare($tenantId, $materialId, $userId, array $metadata = []): array
    {
        TenantContext::setTenantId((string) $tenantId);

        $event = MaterialEvent::create([
            'event_id' => $this->idGenerator->generate(),
            'tenant_id' => $tenantId,
            'material_id' => $materialId,
            'user_id' => $userId,
            'event_type' => 'shared',
            'duration' => 0,
            'occurred_at' => now(),
            'metadata' => $metadata,
        ]);

        return $event->toArray();
    }

    /**
     * 获取素材分析数据
     *
     * @param  array<string, mixed>  $dateRange
     * @return array<string, mixed>
     */
    public function getMaterialAnalytics($tenantId, $materialId, array $dateRange = []): array
    {
        TenantContext::setTenantId((string) $tenantId);

        $startDate = $this->parseDate($dateRange['start'] ?? null, now()->subDays(30));
        $endDate = $this->parseDate($dateRange['end'] ?? null, now(), true);

        $baseQuery = MaterialEvent::where('tenant_id', $tenantId)
            ->where('material_id', $materialId)
            ->whereBetween('occurred_at', [$startDate, $endDate]);

        // 按事件类型统计
        $eventCounts = (clone $baseQuery)
            ->select('event_type', DB::raw('COUNT(*) as count'))
            ->groupBy('event_type')
            ->pluck('count', 'event_type')
            ->toArray();

        // 平均阅读时长
        $avgDuration = (clone $baseQuery)
            ->where('event_type', 'read')
            ->where('duration', '>', 0)
            ->avg('duration');

        // 独立访客数
        $uniqueViewers = (clone $baseQuery)
            ->where('event_type', 'viewed')
            ->distinct('user_id')
            ->count('user_id');

        // 每日趋势
        $dailyTrend = (clone $baseQuery)
            ->select(
                DB::raw('DATE(occurred_at) as date'),
                'event_type',
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('date', 'event_type')
            ->orderBy('date')
            ->get()
            ->toArray();

        // 客户行为明细（最近50条）
        $recentEvents = (clone $baseQuery)
            ->with(['user:user_id,name,phone'])
            ->orderByDesc('occurred_at')
            ->orderByDesc('event_id')
            ->limit(50)
            ->get()
            ->toArray();

        return [
            'material_id' => $materialId,
            'date_range' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'summary' => [
                'view_count' => $eventCounts['viewed'] ?? 0,
                'read_count' => $eventCounts['read'] ?? 0,
                'share_count' => $eventCounts['shared'] ?? 0,
                'unique_viewers' => $uniqueViewers,
                'avg_read_duration' => round((float) ($avgDuration ?? 0), 1),
            ],
            'daily_trend' => $dailyTrend,
            'recent_events' => $recentEvents,
        ];
    }

    /**
     * 根据阅读行为自动打标签
     *
     * @param  array<string, mixed>  $rules  标签规则
     * @return array<int, array<string, mixed>> 打标签结果
     */
    public function autoTagCustomer($tenantId, $userId, array $rules = []): array
    {
        TenantContext::setTenantId((string) $tenantId);

        // 默认规则
        $defaultRules = [
            ['tag_name' => '活跃阅读者', 'condition' => 'total_reads', 'threshold' => 10],
            ['tag_name' => '深度阅读者', 'condition' => 'avg_duration', 'threshold' => 120],
            ['tag_name' => '内容传播者', 'condition' => 'share_count', 'threshold' => 3],
        ];

        $rules = empty($rules) ? $defaultRules : $rules;
        $appliedTags = [];

        $stats = $this->getCustomerBehaviorStats($tenantId, $userId);

        foreach ($rules as $rule) {
            $condition = $rule['condition'] ?? '';
            $threshold = $rule['threshold'] ?? 0;
            $tagName = $rule['tag_name'] ?? '';

            $match = match ($condition) {
                'total_reads' => ($stats['total_reads'] ?? 0) >= $threshold,
                'avg_duration' => ($stats['avg_duration'] ?? 0) >= $threshold,
                'share_count' => ($stats['share_count'] ?? 0) >= $threshold,
                'total_views' => ($stats['total_views'] ?? 0) >= $threshold,
                default => false,
            };

            if ($match && $tagName !== '') {
                $tagId = $this->audienceTagger->applyTag((int) $tenantId, (int) $userId, $tagName);

                if ($tagId === null) {
                    continue;
                }

                $appliedTags[] = [
                    'tag_name' => $tagName,
                    'tag_id' => $tagId,
                    'condition' => $condition,
                    'threshold' => $threshold,
                    'actual_value' => $stats[$condition] ?? 0,
                ];
            }
        }

        return $appliedTags;
    }

    /**
     * 获取客户行为统计
     *
     * @return array<string, mixed>
     */
    protected function getCustomerBehaviorStats($tenantId, $userId): array
    {
        $stats = MaterialEvent::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->select(
                'event_type',
                DB::raw('COUNT(*) as count'),
                DB::raw('AVG(duration) as avg_duration')
            )
            ->groupBy('event_type')
            ->get()
            ->pluck(null, 'event_type');

        return [
            'total_views' => $stats['viewed']->count ?? 0,
            'total_reads' => $stats['read']->count ?? 0,
            'share_count' => $stats['shared']->count ?? 0,
            'avg_duration' => round((float) ($stats['read']->avg_duration ?? 0), 1),
        ];
    }

    /**
     * 解析日期
     */
    protected function parseDate(?string $date, Carbon $default, bool $endOfDay = false): Carbon
    {
        if ($date === null) {
            return $endOfDay ? $default->copy()->endOfDay() : $default;
        }

        try {
            $parsed = Carbon::parse($date);

            return $endOfDay ? $parsed->endOfDay() : $parsed;
        } catch (\Throwable $e) {
            return $endOfDay ? $default->copy()->endOfDay() : $default;
        }
    }
}
