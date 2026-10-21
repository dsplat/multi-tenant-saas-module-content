<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\Content\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use MultiTenantSaas\Concerns\BelongsToTenant;
use MultiTenantSaas\Concerns\HasGlobalId;
use MultiTenantSaas\Concerns\SerializesFriendlyDates;

class InspectionRule extends Model
{
    use BelongsToTenant, HasGlobalId, SoftDeletes;
    use SerializesFriendlyDates;

    protected $primaryKey = 'inspection_rule_id';

    protected $fillable = [
        'tenant_id', 'name', 'type', 'conditions',
        'action', 'severity', 'priority', 'status',
    ];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'priority' => 'integer',
        ];
    }

    public function results(): HasMany
    {
        return $this->hasMany(InspectionResult::class, 'rule_id', 'inspection_rule_id');
    }
}
