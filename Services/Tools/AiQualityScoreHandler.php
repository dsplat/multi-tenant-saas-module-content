<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\Content\Services\Tools;

use MultiTenantSaas\Modules\Ai\Services\Agent\Contracts\ToolHandlerContract;
use MultiTenantSaas\Modules\Content\Services\ContentAiService;

class AiQualityScoreHandler implements ToolHandlerContract
{
    public function __construct(private readonly ContentAiService $service) {}

    public function __invoke(array $arguments, int $tenantId): mixed
    {
        return $this->service->qualityScore($tenantId, $arguments['content'])->output;
    }
}
