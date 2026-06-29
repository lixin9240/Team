<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessException;
use App\Enums\ResponseCode;
use App\Http\Requests\DeleteAccountRequest;
use App\Http\Requests\ForgetPasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\SendEmailCodeRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\User;
use App\Services\AuthService;
use App\Support\Result;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    private AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * 用户注册
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->authService->register($request->validated());
        return Result::success('注册成功', [
            'id' => $user->id,
            'account' => $user->account,
            'name' => $user->name,
            'role' => $user->role,
        ]);
    }

    /**
     * 用户登录
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $data = $this->authService->login(
            $request->validated('account'),
            $request->validated('password')
        );
        return Result::success('登录成功', $data);
    }

    /**
     * 获取当前用户信息
     */
    public function me(): JsonResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (!$user) {
            throw new BusinessException('未登录或token无效', ResponseCode::UNAUTHORIZED);
        }

        return Result::success('获取成功', [
            'id' => $user->id,
            'account' => $user->account,
            'name' => $user->name,
            'role' => $user->role,
            'email' => $user->email,
            'avatar' => $user->avatar,
        ]);
    }

    /**
     * 退出登录
     */
    public function logout(): JsonResponse
    {
        $this->authService->logout();
        return Result::success('退出成功');
    }

    /**
     * 管理员获取所有用户列表
     */
    public function adminUsers(): JsonResponse
    {
        $users = $this->authService->adminUsers(Auth::user());
        return Result::success('获取用户列表成功', $users);
    }

    /**
     * 忘记密码 / 重置密码
     */
    public function forgetPassword(ForgetPasswordRequest $request): JsonResponse
    {
        $this->authService->forgetPassword($request->validated());
        return Result::success('密码重置成功');
    }

    /**
     * 修改个人信息
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->authService->updateProfile(Auth::user(), $request->validated());
        return Result::success('修改成功', [
            'id' => $user->id,
            'account' => $user->account,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role,
        ]);
    }

    /**
     * 发送邮箱验证码
     */
    public function sendEmailCode(SendEmailCodeRequest $request): JsonResponse
    {
        $result = $this->authService->sendEmailCode(
            $request->validated('email'),
            $request->validated('type', 'register')
        );
        return Result::success($result['message'], [
            'expire_minutes' => $result['expire_minutes'],
        ]);
    }

    /**
     * 上传头像
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpg,jpeg,png,gif|max:2048',
        ], [
            'avatar.required' => '请上传头像文件',
            'avatar.image' => '上传的文件必须是图片',
            'avatar.mimes' => '头像仅支持 jpg、jpeg、png、gif 格式',
            'avatar.max' => '头像文件大小不能超过2MB',
        ]);

        $avatarUrl = $this->authService->uploadAvatar(Auth::user(), $request->file('avatar'));
        return Result::success('头像上传成功', ['avatar' => $avatarUrl]);
    }

    /**
     * 注销账号
     */
    public function deleteAccount(DeleteAccountRequest $request): JsonResponse
    {
        $this->authService->deleteAccount(Auth::user(), $request->validated());
        return Result::success('账号已注销');
    }
}
