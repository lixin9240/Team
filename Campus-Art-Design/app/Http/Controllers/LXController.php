<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Mail\VerificationCodeMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tymon\JWTAuth\Facades\JWTAuth;

class LXController extends \Illuminate\Routing\Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'account' => 'required|string|max:255|unique:users',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:8|confirmed',
            'verification_code' => 'required|string|size:6',
        ]);

        $email = $request->email;
        $verificationCode = $request->verification_code;
        $type = 'register';

        $cacheKey = 'verification_code_' . $type . '_' . $email;
        $cachedCode = Cache::get($cacheKey);

        if (!$cachedCode || $cachedCode !== $verificationCode) {
            throw ValidationException::withMessages([
                'verification_code' => ['验证码错误或已过期'],
            ]);
        }

        Cache::forget($cacheKey);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'account' => $request->account,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
        ]);

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'message' => '注册成功',
            'data' => [
                'user' => $user,
                'token' => $token,
            ],
        ], 201);
    }

    public function sendVerificationCode(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email|max:255',
            'type' => 'required|string|in:register,reset_password,delete_account,bind_email,change_email',
        ]);

        $email = $request->email;
        $type = $request->type;

        $allowedTypes = [
            'register' => '注册',
            'reset_password' => '重置密码',
            'delete_account' => '注销账户',
            'bind_email' => '绑定邮箱',
            'change_email' => '修改邮箱',
        ];

        if ($type === 'delete_account' || $type === 'bind_email' || $type === 'change_email') {
            if (!auth('api')->check()) {
                throw ValidationException::withMessages([
                    'type' => ['该操作需要先登录'],
                ]);
            }
        }

        if ($type === 'change_email') {
            $user = auth('api')->user();
            if ($user->email === $email) {
                throw ValidationException::withMessages([
                    'email' => ['新邮箱不能与当前邮箱相同'],
                ]);
            }
        }

        if ($type === 'bind_email') {
            $user = auth('api')->user();
            if ($user->email) {
                throw ValidationException::withMessages([
                    'email' => ['您已绑定邮箱，如需更换请使用修改邮箱功能'],
                ]);
            }
        }

        $code = rand(100000, 999999);

        $cacheKey = 'verification_code_' . $type . '_' . $email;
        Cache::put($cacheKey, $code, 300);

        try {
            Mail::to($email)->send(new VerificationCodeMail($code));

            return response()->json([
                'message' => '验证码发送成功，请查收邮件',
                'data' => [
                    'email' => $email,
                    'type' => $type,
                    'type_label' => $allowedTypes[$type],
                    'expires_in' => 300,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => '验证码发送失败，请稍后重试',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function login(Request $request)
    {
        $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        $credentials = request(['login', 'password']);

        $loginField = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 
                      (preg_match('/^1[3-9]\d{9}$/', $request->login) ? 'phone' : 'account');

        $user = User::where($loginField, $request->login)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['邮箱、账户名或密码错误'],
            ]);
        }

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'message' => '登录成功',
            'data' => [
                'user' => $user,
                'token' => $token,
            ],
        ]);
    }
//退出登录
    public function logout(Request $request)
    {
        JWTAuth::invalidate(JWTAuth::getToken());

        return response()->json([
            'message' => '退出登录成功',
        ]);
    }
    
    //获取个人信息
    public function me()
    {
        return response()->json([
            'message' => '获取用户信息成功',
            'data' => [
                'user' => auth('api')->user(),
            ],
        ]);
    }
}
