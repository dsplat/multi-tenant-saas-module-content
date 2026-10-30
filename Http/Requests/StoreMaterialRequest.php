<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\Content\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use MultiTenantSaas\Modules\Order\Support\EntityTypes;

class StoreMaterialRequest extends FormRequest
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
            'title' => 'required|string|max:200',
            'type' => 'required|string|in:text,image,video,file,link',
            'content' => 'nullable|string|max:10000',
            'file_url' => 'nullable|string|max:500',
            'file_size' => 'nullable|integer|min:0',
            'mime_type' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:50',
            // 通用实体绑定（activity/activity_plan/course/product…），可空=通用素材
            'entity_type' => $entityTypeRules,
            'entity_id' => 'nullable|string|max:32',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => '素材标题不能为空',
            'type.required' => '素材类型不能为空',
            'type.in' => '素材类型只能是 text、image、video、file 或 link',
            'content.max' => '内容长度不能超过10000个字符',
        ];
    }
}
