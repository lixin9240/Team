<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDeviceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'total_qty' => 'nullable|integer|min:1',
            'available_qty' => 'nullable|integer|min:0',
            'status' => 'nullable|in:available,maintenance',
        ];
    }
}
