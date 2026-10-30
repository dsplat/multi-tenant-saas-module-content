<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\Content\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MultiTenantSaas\Concerns\BelongsToTenant;
use MultiTenantSaas\Concerns\HasGlobalId;
use MultiTenantSaas\Concerns\SerializesFriendlyDates;
use MultiTenantSaas\Modules\Auth\Models\User;

class MaterialEvent extends Model
{
    use BelongsToTenant, HasGlobalId;
    use SerializesFriendlyDates;

    protected $primaryKey = 'event_id';

    protected $fillable = [
        'tenant_id',
        'material_id',
        'user_id',
        'event_type',
        'duration',
        'occurred_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'duration' => 'integer',
            'occurred_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * 关联素材
     */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id', 'material_id');
    }

    /**
     * 关联行为主体（框架 User；下游客户档案经 user_id 对齐）
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    /**
     * Scope: 按事件类型
     */
    public function scopeByEventType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }
}
