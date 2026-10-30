<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\Content\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CategorizeMaterialRequest extends FormRequest
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
        return [
            'category' => 'required|string|max:50',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category.required' => '分类不能为空',
            'category.max' => '分类名称不能超过50个字符',
        ];
    }
}
