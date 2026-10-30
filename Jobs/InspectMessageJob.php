<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\Content\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use MultiTenantSaas\Concerns\HasTaskAuthEnvelope;
use MultiTenantSaas\Context\CarriesTaskAuthEnvelope;
use MultiTenantSaas\Modules\Content\Services\QualityInspectionPipeline;

/**
 * 异步质检任务
 *
 * 由 ChatArchive 同步、群发助手、朋友圈发布等场景触发。
 * 对单条消息内容执行质检流水线。
 * 当前 inspection_rules=0，触发点已停用。重新启用时必须由派发点携带信封：
 * 定时/系统路径用 TaskAuthEnvelope::forService('scrm.inspect-message', $tenantId, ...)
 * （该 service 名已在 AppServiceProvider trustService），用户/操作员路径用 fromIdentity(current)。
 */
class InspectMessageJob implements CarriesTaskAuthEnvelope, ShouldQueue
{
    use Dispatchable, HasTaskAuthEnvelope, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 10;

    public function __construct(
        protected int $tenantId,
        protected string $sourceType,
        protected int $sourceId,
        protected string $content,
    ) {}

    public function handle(QualityInspectionPipeline $pipeline): void
    {
        $pipeline->inspect(
            $this->tenantId,
            $this->sourceType,
            $this->sourceId,
            $this->content
        );
    }
}
