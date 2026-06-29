<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ForgetPasswordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'account' => 'required|string',
            'email' => 'required|email|regex:/^[a-zA-Z0-9._%+-]+@qq\.com$/i',
            'code' => 'required|string|size:6',
            'password' => 'required|string|min:6|regex:/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]+$/',
            'password_confirmation' => 'required|string|same:password',
        ];
    }

    public function messages(): array
    {
        return [
            'email.regex' => '仅支持QQ邮箱（@qq.com）',
            'code.size' => '验证码必须是6位数字',
            'password.min' => '密码至少6个字符',
            'password.regex' => '密码必须同时包含英文字母和数字',
            'password_confirmation.same' => '两次输入的密码不一致',
        ];
    }
}
