<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\Content\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MultiTenantSaas\Concerns\BelongsToTenant;
use MultiTenantSaas\Concerns\HasGlobalId;
use MultiTenantSaas\Concerns\SerializesFriendlyDates;

class InspectionAlert extends Model
{
    use BelongsToTenant, HasGlobalId;
    use SerializesFriendlyDates;

    protected $primaryKey = 'alert_id';

    protected $fillable = [
        'tenant_id', 'inspection_result_id', 'notify_channel',
        'notify_to', 'notified_at', 'status', 'handled_by', 'handled_at',
    ];

    protected function casts(): array
    {
        return [
            'notify_to' => 'array',
            'notified_at' => 'datetime',
            'handled_at' => 'datetime',
        ];
    }

    public function result(): BelongsTo
    {
        return $this->belongsTo(InspectionResult::class, 'inspection_result_id', 'inspection_result_id');
    }
}
