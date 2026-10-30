<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\Content\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use MultiTenantSaas\Concerns\BelongsToTenant;
use MultiTenantSaas\Concerns\HasGlobalId;
use MultiTenantSaas\Concerns\SerializesFriendlyDates;

class InspectionResult extends Model
{
    use BelongsToTenant, HasGlobalId;
    use SerializesFriendlyDates;

    protected $primaryKey = 'inspection_result_id';

    protected $fillable = [
        'tenant_id', 'source_type', 'source_id', 'rule_id',
        'matched_content', 'severity', 'action_taken',
        'reviewed_by', 'reviewed_at', 'status',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(InspectionRule::class, 'rule_id', 'inspection_rule_id');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(InspectionAlert::class, 'inspection_result_id', 'inspection_result_id');
    }
}
