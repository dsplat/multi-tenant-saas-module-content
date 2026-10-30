<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\Content\Models;

use Illuminate\Database\Eloquent\Model;
use MultiTenantSaas\Concerns\BelongsToTenant;
use MultiTenantSaas\Concerns\HasGlobalId;
use MultiTenantSaas\Concerns\SerializesFriendlyDates;

class Material extends Model
{
    use BelongsToTenant, HasGlobalId;
    use SerializesFriendlyDates;

    protected $primaryKey = 'material_id';

    protected $fillable = ['tenant_id', 'title', 'type', 'content', 'file_url', 'file_size', 'mime_type', 'category', 'status', 'entity_type', 'entity_id'];

    protected function casts(): array
    {
        return ['file_size' => 'integer'];
    }
}
