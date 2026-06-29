<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCategoryRequest extends FormRequest
{
    public function rules(): array
    {
        $id = $this->route('id');
        return [
            'name' => 'nullable|string|max:50',
            'code' => 'nullable|string|max:50|unique:categories,code,' . $id,
            'description' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ];
    }
}
