<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\Content\Services\Tools;

use MultiTenantSaas\Modules\Ai\Services\Agent\Contracts\ToolHandlerContract;
use MultiTenantSaas\Modules\Content\Services\ScriptService;

class SearchScriptsHandler implements ToolHandlerContract
{
    public function __construct(private readonly ScriptService $service) {}

    public function __invoke(array $arguments, int $tenantId): mixed
    {
        return $this->service->getScripts(
            $tenantId,
            $arguments['category'] ?? null,
            (int) ($arguments['per_page'] ?? 20),
            null,
            (string) $arguments['keyword'],
        );
    }
}
