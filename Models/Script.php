<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\Content\Models;

use Illuminate\Database\Eloquent\Model;
use MultiTenantSaas\Concerns\BelongsToTenant;
use MultiTenantSaas\Concerns\HasGlobalId;
use MultiTenantSaas\Concerns\SerializesFriendlyDates;

class Script extends Model
{
    use BelongsToTenant, HasGlobalId;
    use SerializesFriendlyDates;

    protected $primaryKey = 'script_id';

    protected $fillable = [
        'tenant_id',
        'title',
        'category',
        'content',
        'tags',
        'variables',
        'usage_count',
        'status',
        'entity_type',
        'entity_id',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'variables' => 'array',
            'usage_count' => 'integer',
        ];
    }

    /**
     * Scope: 按分类筛选
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope: 按标签筛选
     */
    public function scopeByTag($query, string $tag)
    {
        return $query->whereJsonContains('tags', $tag);
    }
}
