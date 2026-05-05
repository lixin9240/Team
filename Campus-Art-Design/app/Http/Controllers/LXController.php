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
    //注册
    //验证注册信息
    public function register(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'account' => 'required|string|max:255|unique:users',
                'phone' => 'nullable|string|max:20',
                'password' => ['required', 'string', 'min:6', 'confirmed', 'regex:/^(?=.*[A-Za-z])(?=.*\d)(?=.*[@$!%*#?&])[A-Za-z\d@$!%*#?&]+$/'],
                'verification_code' => 'required|string|size:6',
            ]);

            $email = $request->email;
            $verificationCode = $request->verification_code;
            $type = 'register';

            $cacheKey = 'verification_code_' . $type . '_' . $email;
            $cachedCode = Cache::get($cacheKey);

            if (!$cachedCode || (string)$cachedCode !== (string)$verificationCode) {
                return response()->json([
                    'message' => '验证码错误或已过期',
                    'errors' => [
                        'verification_code' => ['验证码错误或已过期']
                    ]
                ], 422);
            }

            Cache::forget($cacheKey);

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'account' => $request->account,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'email_verified_at' => now(),
            ]);

            return response()->json([
                'message' => '注册成功，请登录',
                'data' => [
                    'user' => $user,
                ],
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => '验证失败',
                'errors' => $e->errors()
            ], 422);
        }
    }
//发送验证码
    public function sendVerificationCode(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|string|email|max:255',
                'type' => 'required|string|in:register,reset_password,delete_account,bind_email,change_email',
                //验证码类型，比如注册、重置密码、注销账户、绑定邮箱、修改邮箱
            ]);

            $email = $request->email;
            $type = $request->type;

            // 验证邮箱域名是否有效
            $emailDomain = substr(strrchr($email, '@'), 1);
            if (!checkdnsrr($emailDomain, 'MX')) {
                return response()->json([
                    'message' => '请输入正确的邮箱',
                    'errors' => [
                        'email' => ['请输入正确的邮箱，该邮箱域名不存在']
                    ]
                ], 422);
            }

            $allowedTypes = [
                'register' => '注册',
                'reset_password' => '重置密码',
                'delete_account' => '注销账户',
                'bind_email' => '绑定邮箱',
                'change_email' => '修改邮箱',
            ];

            if ($type === 'delete_account' || $type === 'bind_email' || $type === 'change_email') {
                if (!auth('api')->check()) {
                    return response()->json([
                        'message' => '该操作需要先登录',
                        'errors' => [
                            'type' => ['该操作需要先登录']
                        ]
                    ], 401);
                }
            }

            if ($type === 'change_email') {
                $user = auth('api')->user();
                if ($user->email === $email) {
                    return response()->json([
                        'message' => '新邮箱不能与当前邮箱相同',
                        'errors' => [
                            'email' => ['新邮箱不能与当前邮箱相同']
                        ]
                    ], 422);
                }
            }

            if ($type === 'bind_email') {
                $user = auth('api')->user();
                if ($user->email) {
                    return response()->json([
                        'message' => '您已绑定邮箱，如需更换请使用修改邮箱功能',
                        'errors' => [
                            'email' => ['您已绑定邮箱，如需更换请使用修改邮箱功能']
                        ]
                    ], 422);
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
        } catch (ValidationException $e) {
            return response()->json([
                'message' => '验证失败',
                'errors' => $e->errors()
            ], 422);
        }
    }
//登录
    public function login(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|string|email',
                'password' => 'required|string',
            ]);

            $user = User::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json([
                    'message' => '登录失败',
                    'errors' => [
                        'email' => ['邮箱或密码错误']
                    ]
                ], 401);
            }

            $token = JWTAuth::fromUser($user);

            return response()->json([
                'message' => '登录成功',
                'data' => [
                    'user' => $user,
                    'token' => $token,
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => '验证失败',
                'errors' => $e->errors()
            ], 422);
        }
    }
//注销
    public function logout(Request $request)
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());

            return response()->json([
                'message' => '退出登录成功',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => '退出登录失败',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
//获取用户信息
    public function me()
    {
        try {
            return response()->json([
                'message' => '获取用户信息成功',
                'data' => [
                    'user' => auth('api')->user(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => '获取用户信息失败',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
