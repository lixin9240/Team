<?php

namespace App\Http\Controllers;

use App\Mail\VerificationCodeMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class LXController extends \Illuminate\Routing\Controller
{
    /**
     * 发送验证码
     */
    public function sendVerificationCode(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|string|email',
        ]);

        $email = $validated['email'];

        // 生成6位随机验证码
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // 使用缓存存储验证码，5分钟过期
        Cache::put('verification_code:' . $email, $code, 300);

        // 发送邮件
        Mail::to($email)->send(new VerificationCodeMail($code));

        return response()->json([
            'message' => '验证码已发送',
            'email' => $email,
        ]);
    }

    /**
     * 用户注册
     * 需要验证邮箱验证码
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users',
            'account' => 'required|string|unique:users',
            'phone' => 'required|string|unique:users',
            'password' => 'required|string|min:6',
            'code' => 'required|string|size:6',
        ]);

        $email = $validated['email'];
        $cacheKey = 'verification_code:' . $email;

        // 验证邮箱验证码
        $cachedCode = Cache::get($cacheKey);

        if (!$cachedCode) {
            return response()->json(['error' => '验证码已过期或不存在'], 400);
        }

        if ($cachedCode !== $validated['code']) {
            return response()->json(['error' => '验证码错误'], 400);
        }

        // 创建用户
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'account' => $validated['account'],
            'phone' => $validated['phone'],
            'password' => $validated['password'],
        ]);

        // 删除已使用的验证码
        Cache::forget($cacheKey);

        // 生成token，有效期一周（10080分钟）
        $token = auth('api')->claims(['exp' => now()->addMinutes(10080)->timestamp])->login($user);

        return response()->json([
            'message' => '注册成功',
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => 10080 * 60,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'account' => $user->account,
            ],
        ]);
    }

    /**
     * 用户登录
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $credentials = [
            'email' => $validated['email'],
            'password' => $validated['password'],
        ];

        if (!$token = auth('api')->setTTL(10080)->attempt($credentials)) {
            return response()->json(['error' => '邮箱或密码错误'], 401);
        }

        $user = auth('api')->user();

        return response()->json([
            'message' => '登录成功',
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => 10080 * 60,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'account' => $user->account,
            ],
        ]);
    }
}