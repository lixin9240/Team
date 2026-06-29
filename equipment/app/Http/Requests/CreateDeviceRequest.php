<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateDeviceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'category' => 'required|string|max:50',
            'description' => 'nullable|string',
            'total_qty' => 'required|integer|min:1',
            'available_qty' => 'required|integer|min:0',
            'status' => 'required|in:available,maintenance',
        ];
    }
}
