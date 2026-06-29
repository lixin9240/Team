<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'account' => 'required|string|min:4|max:20|unique:users',
            'name' => 'required|string|min:2|max:20',
            'password' => 'required|string|min:6|regex:/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]+$/',
            'password_confirmation' => 'required|string|same:password',
            'email' => 'required|email|max:100|regex:/^[a-zA-Z0-9._%+-]+@qq\.com$/i',
            'email_code' => 'required|string|size:6',
        ];
    }

    public function messages(): array
    {
        return [
            'account.min' => '账号至少4个字符',
            'account.max' => '账号最多20个字符',
            'name.min' => '姓名至少2个字符',
            'name.max' => '姓名最多20个字符',
            'password.min' => '密码至少6个字符',
            'password.regex' => '密码必须同时包含英文字母和数字',
            'password_confirmation.same' => '两次输入的密码不一致',
            'email.regex' => '仅支持QQ邮箱（@qq.com）',
            'email_code.size' => '邮箱验证码必须是6位',
        ];
    }
}
