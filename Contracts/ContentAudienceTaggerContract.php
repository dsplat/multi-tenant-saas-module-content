<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\Content\Contracts;

/**
 * 内容行为标签扩展点。
 *
 * 框架只负责判定「该给用户打什么标签」，标签的落库与归属由下游标签系统实现；
 * 未接线时默认 Null 实现（返回 null，跳过打标），保证模块可独立安装。
 */
interface ContentAudienceTaggerContract
{
    /**
     * 将标签应用到指定用户（标签不存在时由实现方创建）。
     *
     * @return int|string|null 标签 ID；null 表示当前环境未接线标签系统
     */
    public function applyTag(int $tenantId, int $userId, string $tagName): int|string|null;
}
