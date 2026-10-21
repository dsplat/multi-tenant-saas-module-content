<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\Content\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use MultiTenantSaas\Modules\Order\Support\EntityTypes;

class UpdateMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Order 模块未安装时退化为自由字符串，保持内容中心可独立安装
        $entityTypeRules = class_exists(EntityTypes::class)
            ? ['nullable', 'string', Rule::in(EntityTypes::ALL)]
            : ['nullable', 'string', 'max:32'];

        return [
            'title' => 'sometimes|string|max:200',
            'type' => 'sometimes|string|in:text,image,video,file,link',
            'content' => 'nullable|string|max:10000',
            'file_url' => 'nullable|string|max:500',
            'file_size' => 'nullable|integer|min:0',
            'mime_type' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:50',
            'status' => 'nullable|string|in:active,inactive,archived',
            // 通用实体绑定（activity/activity_plan/course/product…），可空=通用素材
            'entity_type' => $entityTypeRules,
            'entity_id' => 'nullable|string|max:32',
        ];
    }
}
