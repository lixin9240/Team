<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendEmailCodeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => 'required|email',
            'type' => 'required|string|in:register,reset_password,bind,delete_account',
        ];
    }
}
