<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\Content;

use Illuminate\Support\Facades\Route;
use MultiTenantSaas\Authorization\CapabilityDefinition;
use MultiTenantSaas\Context\ExecutionIdentity;
use MultiTenantSaas\Contracts\AuthorizationCapabilityRegistryContract;
use MultiTenantSaas\Contracts\CapabilityRequirementResolverContract;
use MultiTenantSaas\Contracts\ToolRegistryContract;
use MultiTenantSaas\Modules\Ai\Services\Agent\Dto\Tool;
use MultiTenantSaas\Modules\Content\Contracts\ContentAudienceTaggerContract;
use MultiTenantSaas\Modules\Content\Services\NullContentAudienceTagger;
use MultiTenantSaas\Modules\Content\Services\Tools\AiGenerateScriptHandler;
use MultiTenantSaas\Modules\Content\Services\Tools\AiQualityScoreHandler;
use MultiTenantSaas\Modules\Content\Services\Tools\GetScriptRecommendationsHandler;
use MultiTenantSaas\Modules\Content\Services\Tools\SearchScriptsHandler;
use MultiTenantSaas\Modules\Contracts\ModuleServiceProvider;
use MultiTenantSaas\Support\OptionalModule;

/**
 * 内容中心模块（素材/话术/敏感词/质检，自 scrm 物理归位）
 *
 * 路由/迁移/配置交给模块基类；覆写 loadModuleRoutes() 把前缀换成
 * config('content.route_prefix')，供下游沿用既有 api/v1/biz 契约。
 *
 * 行为标签的落库由 ContentAudienceTaggerContract 扩展点承接；未接线时
 * Null 实现跳过打标。AI 增强与 L1 工具只在 AI 模块（ToolRegistryContract）
 * 存在时注册，模块本身不硬依赖 AI。
 */
class ContentServiceProvider extends ModuleServiceProvider
{
    protected string $moduleName = 'content';

    private const CAPABILITY_READ = 'common.content.read';

    private const READ_PERMISSION = 'material.view';

    private const MW_VERIFY_OPERATOR_TENANT = 'MultiTenantSaas\Modules\Infrastructure\Http\Middleware\VerifyOperatorTenant';

    private const MW_OPTIONAL_SANCTUM_AUTH = 'MultiTenantSaas\Modules\Infrastructure\Http\Middleware\OptionalSanctumAuth';

    protected function registerModuleBindings(): void
    {
        $this->app->bindIf(ContentAudienceTaggerContract::class, NullContentAudienceTagger::class);
    }

    protected function bootModule(): void
    {
        $this->registerAuthorizationCapabilities();
        $this->registerTools();
    }

    /**
     * 内容读能力键（ADR v2.5 §6.1）：本模块全部 L1 只读工具共用。
     *
     * operator requirement 映射到既有权限名 `material.view`——内容中心
     * （素材/话术）菜单与其读端点同口径，不新增权限名。
     */
    private function registerAuthorizationCapabilities(): void
    {
        if (! $this->app->bound(AuthorizationCapabilityRegistryContract::class)) {
            return;
        }

        $this->app->make(AuthorizationCapabilityRegistryContract::class)->register(new CapabilityDefinition(
            key: self::CAPABILITY_READ,
            subjectTypes: [ExecutionIdentity::TYPE_OPERATOR],
            targetScope: CapabilityDefinition::TARGET_TENANT,
            admissionPolicy: [ExecutionIdentity::TYPE_OPERATOR => ['member']],
        ));

        if ($this->app->bound(CapabilityRequirementResolverContract::class)) {
            $this->app->make(CapabilityRequirementResolverContract::class)
                ->register(self::CAPABILITY_READ, ExecutionIdentity::TYPE_OPERATOR, ['permission' => self::READ_PERMISSION]);
        }
    }

    private function registerTools(): void
    {
        if (! $this->app->bound(ToolRegistryContract::class)) {
            return;
        }

        $registry = $this->app->make(ToolRegistryContract::class);

        $registry->register(
            slug: 'search_scripts',
            name: '搜索话术',
            description: '搜索话术库',
            handlerClass: SearchScriptsHandler::class,
            schema: [
                'type' => 'object',
                'properties' => [
                    'keyword' => ['type' => 'string', 'description' => '搜索关键词'],
                    'category' => ['type' => 'string', 'description' => '话术分类'],
                    'per_page' => ['type' => 'integer', 'description' => '每页数量', 'default' => 20],
                ],
                'required' => ['keyword'],
            ],
            category: 'content',
            risk: 'L1',
            requiredCapability: self::CAPABILITY_READ,
            dataAction: Tool::DATA_ACTION_READ,
        );

        $registry->register(
            slug: 'get_script_recommendations',
            name: '话术推荐',
            description: '获取话术推荐',
            handlerClass: GetScriptRecommendationsHandler::class,
            schema: [
                'type' => 'object',
                'properties' => [
                    'context' => ['type' => 'string', 'description' => '对话上下文'],
                    'user_id' => ['type' => 'integer', 'description' => '客户 ID'],
                ],
                'required' => ['context'],
            ],
            category: 'content',
            risk: 'L1',
            requiredCapability: self::CAPABILITY_READ,
            dataAction: Tool::DATA_ACTION_READ,
        );

        $registry->register(
            slug: 'ai_generate_script',
            name: 'AI生成话术',
            description: 'AI 生成话术/文案',
            handlerClass: AiGenerateScriptHandler::class,
            schema: [
                'type' => 'object',
                'properties' => [
                    'scene' => ['type' => 'string', 'description' => '场景: greeting/follow_up/promotion/apology/farewell'],
                    'context' => ['type' => 'object', 'description' => '上下文（产品、客户特征）'],
                ],
                'required' => ['scene'],
            ],
            category: 'content',
            risk: 'L1',
            requiredCapability: self::CAPABILITY_READ,
            dataAction: Tool::DATA_ACTION_READ,
        );

        $registry->register(
            slug: 'ai_quality_score',
            name: 'AI质检评分',
            description: 'AI 内容质检评分',
            handlerClass: AiQualityScoreHandler::class,
            schema: [
                'type' => 'object',
                'properties' => [
                    'content' => ['type' => 'string', 'description' => '待质检的会话/内容文本'],
                ],
                'required' => ['content'],
            ],
            category: 'content',
            risk: 'L1',
            requiredCapability: self::CAPABILITY_READ,
            dataAction: Tool::DATA_ACTION_READ,
        );
    }

    protected function loadModuleRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        OptionalModule::ensure(self::MW_VERIFY_OPERATOR_TENANT, '内容模块路由加载', 'Infrastructure');

        $moduleDir = $this->getModulePath();
        $moduleGate = 'module.enabled:' . $this->moduleGateName();
        $prefix = (string) config('content.route_prefix', 'api/v1');

        $apiRoute = $moduleDir . '/Routes/api.php';
        if (file_exists($apiRoute)) {
            Route::middleware(['api', 'auth:sanctum', 'throttle:api', 'tenant.identify', self::MW_VERIFY_OPERATOR_TENANT, $moduleGate])
                ->prefix($prefix)
                ->group($apiRoute);
        }

        $optionalRoute = $moduleDir . '/Routes/optional.php';
        if (file_exists($optionalRoute)) {
            Route::middleware(['api', 'throttle:api', 'tenant.identify', self::MW_OPTIONAL_SANCTUM_AUTH, $moduleGate])
                ->prefix($prefix)
                ->group($optionalRoute);
        }
    }
}
