<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\EmailVerificationService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class LZWController extends Controller
{
    protected EmailVerificationService $emailVerificationService;

    public function __construct(EmailVerificationService $emailVerificationService)
    {
        $this->emailVerificationService = $emailVerificationService;
    }
    /**
     * 用户注册
     * 接口: POST /api/auth/register
     */
    public function register(Request $request)
    {
        // 确保返回 JSON
        $request->headers->set('Accept', 'application/json');

        try {
            $validated = $request->validate([
                'account' => 'required|string|min:4|max:20|unique:users',
                'name' => 'required|string|min:2|max:20',
                'password' => 'required|string|min:6|regex:/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]+$/',
                'password_confirmation' => 'required|string|same:password',
                'email' => 'required|email|max:100|regex:/^[a-zA-Z0-9._%+-]+@qq\.com$/i',
                'email_code' => 'required|string|size:6',

                // ###########################
                // 第1处修改：直接删掉 role 验证！不让前端传！
                // ###########################
            ], [
                'account.min' => '账号至少4个字符',
                'account.max' => '账号最多20个字符',
                'account.alpha_num' => '账号只能由字母和数字组成',
                'name.min' => '姓名至少2个字符',
                'name.max' => '姓名最多20个字符',
                'password.min' => '密码至少6个字符',
                'password.regex' => '密码必须同时包含英文字母和数字',
                'password_confirmation.same' => '两次输入的密码不一致',
                'email.required' => '邮箱不能为空',
                'email.email' => '邮箱格式不正确',
                'email.regex' => '仅支持QQ邮箱（@qq.com）',
                'email.max' => '邮箱最多100个字符',
                'email_code.required' => '邮箱验证码不能为空',
                'email_code.size' => '邮箱验证码必须是6位',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'code' => 422,
                'message' => '验证失败',
                'data' => $e->errors()
            ], 422);
        }

        // 验证邮箱验证码
        $cacheKey = "email_code:{$validated['email']}:register";
        $cachedCode = cache()->get($cacheKey);

        if (is_null($cachedCode) || $cachedCode !== $validated['email_code']) {
            return response()->json([
                'code' => 400,
                'message' => '邮箱验证码无效或已过期',
                'data' => null
            ], 400);
        }
        cache()->forget($cacheKey);

        // 检查账号是否已存在（包括软删除的）
        $existingUser = User::withTrashed()->where('account', $validated['account'])->first();

        if ($existingUser) {
            if (is_null($existingUser->deleted_at)) {
                // 账号存在且未删除
                return response()->json([
                    'code' => 400,
                    'message' => '账号已存在',
                    'data' => null
                ]);
            }

            // 账号已被软删除，恢复并更新信息
            $existingUser->restore();
            $existingUser->update([
                'name' => $validated['name'],
                'password' => $validated['password'],
                'email' => $validated['email'] ?? null,
                'role' => 'student',
            ]);
            $user = $existingUser;
        } else {
            // 创建新用户
            $user = User::create([
                'account' => $validated['account'],
                'name' => $validated['name'],
                'password' => $validated['password'],
                'email' => $validated['email'] ?? null,
                'role' => 'student',
            ]);
        }

        return response()->json([
            'code' => 200,
            'message' => '注册成功',
            'data' => [
                'id' => $user->id,
                'account' => $user->account,
                'name' => $user->name,
                'role' => $user->role,
            ]
        ]);
    }

    /**
     * 用户登录
     * 接口: POST /api/auth/login
     * 说明: 使用账号和密码登录
     */
    public function login(Request $request)
    {
        // 验证请求参数
        $validated = $request->validate([
            'account' => 'required|string',
            'password' => 'required|string',
        ]);

        // 1. 检查用户是否存在
        $user = \App\Models\User::where('account', $validated['account'])->first();
        if (!$user) {
            return response()->json([
                'code' => 401,
                'message' => '账号不存在',
                'data' => null
            ], 401);
        }

        // 2. 验证密码（使用 Hash::check 直接验证）
        if (!Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'code' => 401,
                'message' => '密码错误',
                'data' => null
            ], 401);
        }

        // 3. 生成 JWT Token
        $token = JWTAuth::fromUser($user);

        // 3. 返回登录成功信息和token
        return response()->json([
            'code' => 200,
            'message' => '登录成功',
            'data' => [
                'token' => $token,
                'token_type' => 'bearer',
                'expires_in' => JWTAuth::factory()->getTTL() * 60,
                'user' => [
                    'id' => $user->id,
                    'account' => $user->account,
                    'name' => $user->name,
                    'role' => $user->role,
                ]
            ]
        ]);
    }

    /**
     * 获取当前用户信息
     * 接口: /api/auth/me
     */
    public function me(Request $request)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'code' => 401,
                'message' => '未登录或token无效',
                'data' => null
            ], 401);
        }

        return response()->json([
            'code' => 200,
            'message' => '获取成功',
            'data' => [
                'id' => $user->id,
                'account' => $user->account,
                'name' => $user->name,
                'role' => $user->role,
                'email' => $user->email,
                'avatar' => $user->avatar,
            ]
        ]);
    }

    /**
     * 退出登录
     * 接口: /api/auth/logout
     */
    public function logout(Request $request)
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());

            return response()->json([
                'code' => 200,
                'message' => '退出成功',
                'data' => null
            ]);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json([
                'code' => 500,
                'message' => '退出失败：' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * 管理员获取所有用户列表
     * 接口: /api/admin/users
     */
    public function adminUsers(Request $request)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'code' => 401,
                'message' => '未登录或token无效',
                'data' => null
            ], 401);
        }

        if ($user->role !== 'admin') {
            return response()->json([
                'code' => 403,
                'message' => '权限不足，仅管理员可访问',
                'data' => null
            ], 403);
        }

        $users = User::select('id', 'account', 'name', 'email', 'role', 'created_at')->get();

        return response()->json([
            'code' => 200,
            'message' => '获取用户列表成功',
            'data' => $users
        ]);
    }
    /**
     * 忘记密码 / 重置密码
     * 接口: /api/auth/forget-password
     */
    public function forgetPassword(Request $request)
    {
        // 1. 验证参数
        $validated = $request->validate([
            'account' => 'required|string',
            'email' => 'required|email|regex:/^[a-zA-Z0-9._%+-]+@qq\.com$/i',
            'code' => 'required|string|size:6',
            'password' => 'required|string|min:6|regex:/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]+$/',
            'password_confirmation' => 'required|string|same:password',
        ], [
            'email.regex' => '仅支持QQ邮箱（@qq.com）',
            'code.size' => '验证码必须是6位数字',
            'password.min' => '密码至少6个字符',
            'password.regex' => '密码必须同时包含英文字母和数字',
            'password_confirmation.same' => '两次输入的密码不一致',
        ]);

        // 2. 查询用户是否存在
        $user = User::where('account', $validated['account'])->first();

        if (!$user) {
            return response()->json([
                'code' => 400,
                'message' => '账号不存在',
                'data' => null
            ]);
        }

        // 3. 校验邮箱是否一致
        if ($user->email !== $validated['email']) {
            return response()->json([
                'code' => 400,
                'message' => '邮箱与账号不匹配',
                'data' => null
            ]);
        }

        // 4. 验证邮箱验证码
        if (!$this->emailVerificationService->verifyCode($validated['email'], $validated['code'], 'reset_password')) {
            return response()->json([
                'code' => 400,
                'message' => '验证码错误或已过期',
                'data' => null
            ]);
        }

        // 5. 重置密码
        $user->update([
            'password' => $validated['password']
        ]);

        // 6. 返回成功
        return response()->json([
            'code' => 200,
            'message' => '密码重置成功',
            'data' => null
        ]);
    }
    /**
     * 修改个人信息
     * 接口: /api/auth/profile
     */
    public function updateProfile(Request $request)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'code' => 401,
                'message' => '未登录或token无效',
                'data' => null
            ], 401);
        }

        $validated = $request->validate([
            'name' => 'nullable|string',
            'account' => 'nullable|string|unique:users,account,' . $user->id,
            'email' => 'nullable|email|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|regex:/^1[3-9]\d{9}$/|unique:users,phone,' . $user->id,
            'password' => 'nullable|string|min:6',
        ]);

        // 如果传了name就更新
        if (!empty($validated['name'])) {
            $user->name = $validated['name'];
        }

        // 如果传了account就更新
        if (!empty($validated['account'])) {
            $user->account = $validated['account'];
        }

        // 如果传了email就更新
        if (!empty($validated['email'])) {
            $user->email = $validated['email'];
        }

        // 如果传了手机号就更新
        if (!empty($validated['phone'])) {
            $user->phone = $validated['phone'];
        }

        // 如果传了密码就更新
        if (!empty($validated['password'])) {
            $user->password = $validated['password'];
        }

        $user->save();

        return response()->json([
            'code' => 200,
            'message' => '修改成功',
            'data' => [
                'id' => $user->id,
                'account' => $user->account,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
            ]
        ]);
    }

    /**
     * 发送邮箱验证码
     * 接口: POST /api/auth/send-email-code
     */
    public function sendEmailCode(Request $request)
    {
        // 确保返回 JSON
        $request->headers->set('Accept', 'application/json');

        try {
            $validated = $request->validate([
                'email' => 'required|email',
                'type' => 'nullable|string|in:register,reset_password,bind,delete_account',//验证码类型，注册，重置密码，绑定新邮箱，注销账号等
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'code' => 422,
                'message' => '验证失败',
                'data' => $e->errors()
            ], 422);
        }

        $email = $validated['email'];
        $type = $validated['type'] ?? 'register';

        // 根据不同类型进行额外验证
        switch ($type) {
            case 'register':
                // 注册时检查邮箱是否已被使用
                if (User::where('email', $email)->exists()) {
                    return response()->json([
                        'code' => 400,
                        'message' => '该邮箱已被注册',
                        'data' => null
                    ]);
                }
                break;

            case 'reset_password':
                // 重置密码时检查邮箱是否存在
                if (!User::where('email', $email)->exists()) {
                    return response()->json([
                        'code' => 400,
                        'message' => '该邮箱未注册',
                        'data' => null
                    ]);
                }
                break;

            case 'delete_account':
                // 注销账号时检查邮箱是否存在
                if (!User::where('email', $email)->exists()) {
                    return response()->json([
                        'code' => 400,
                        'message' => '该邮箱未注册',
                        'data' => null
                    ]);
                }
                break;
        }

        // 发送验证码
        try {
            $result = $this->emailVerificationService->sendCode($email, $type);

            if ($result['success']) {
                return response()->json([
                    'code' => 200,
                    'message' => $result['message'],
                    'data' => [
                        'expire_minutes' => $result['expire_minutes']
                    ]
                ]);
            }

            return response()->json([
                'code' => 400,
                'message' => $result['message'],
                'data' => null
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => '服务器错误: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * 上传头像
     * 接口: POST /api/auth/avatar
     */
    public function uploadAvatar(Request $request)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'code' => 401,
                'message' => '未登录或token无效',
                'data' => null
            ], 401);
        }

        // 验证上传的文件
        try {
            $validated = $request->validate([
                'avatar' => 'required|image|mimes:jpg,jpeg,png,gif|max:2048',
            ], [
                'avatar.required' => '请上传头像文件',
                'avatar.image' => '上传的文件必须是图片',
                'avatar.mimes' => '头像仅支持 jpg、jpeg、png、gif 格式',
                'avatar.max' => '头像文件大小不能超过2MB',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'code' => 422,
                'message' => '验证失败',
                'data' => $e->errors()
            ], 422);
        }

        try {
            // 删除旧头像（如果存在且不是默认头像）
            if ($user->avatar && !str_contains($user->avatar, 'default')) {
                $oldPath = str_replace('/storage/', '', $user->avatar);
                if (\Illuminate\Support\Facades\Storage::disk('public')->exists($oldPath)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($oldPath);
                }
            }

            // 存储新头像
            $file = $request->file('avatar');
            $fileName = 'avatars/' . $user->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('', $fileName, 'public');

            // 生成访问URL
            $avatarUrl = '/storage/' . $path;

            // 更新用户头像
            $user->update(['avatar' => $avatarUrl]);

            return response()->json([
                'code' => 200,
                'message' => '头像上传成功',
                'data' => [
                    'avatar' => $avatarUrl
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => '头像上传失败: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

}
