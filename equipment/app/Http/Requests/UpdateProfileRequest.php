<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateProfileRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'account' => 'nullable|string|unique:users,account,' . Auth::id(),
            'email' => 'nullable|email|unique:users,email,' . Auth::id(),
            'phone' => 'nullable|string|regex:/^1[3-9]\d{9}$/|unique:users,phone,' . Auth::id(),
            'password' => 'nullable|string|min:6',
        ];
    }
}
