<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeleteAccountRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'account' => 'required|string|min:4|max:20',
            'email' => 'required|email',
            'email_code' => 'required|string|size:6',
        ];
    }

    public function messages(): array
    {
        return [
            'account.required' => '账号不能为空',
            'account.min' => '账号至少4个字符',
            'email_code.size' => '邮箱验证码必须是6位',
        ];
    }
}
