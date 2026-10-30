<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\Content\Services\Tools;

use MultiTenantSaas\Modules\Ai\Services\Agent\Contracts\ToolHandlerContract;
use MultiTenantSaas\Modules\Content\Services\ScriptService;

class GetScriptRecommendationsHandler implements ToolHandlerContract
{
    public function __construct(private readonly ScriptService $service) {}

    public function __invoke(array $arguments, int $tenantId): mixed
    {
        return $this->service->recommend($tenantId, (string) $arguments['context']);
    }
}
