<?php

namespace App\Services;

use App\Enums\ResponseCode;
use App\Exceptions\BusinessException;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthService
{
    const CODE_EXPIRE_MINUTES = 5;
    const SEND_INTERVAL = 10;

    /**
     * 用户注册
     */
    public function register(array $data): User
    {
        $this->verifyEmailCode($data['email'], $data['email_code'], 'register');

        $existingUser = User::withTrashed()->where('account', $data['account'])->first();

        if ($existingUser) {
            if (is_null($existingUser->deleted_at)) {
                throw new BusinessException('账号已存在', ResponseCode::DATA_DUPLICATE);
            }

            $existingUser->restore();
            $existingUser->update([
                'name' => $data['name'],
                'password' => $data['password'],
                'email' => $data['email'] ?? null,
                'role' => 'student',
            ]);

            Log::channel('business')->info('用户恢复并更新信息', [
                'user_id' => $existingUser->id,
                'account' => $existingUser->account,
            ]);

            return $existingUser;
        }

        $user = User::create([
            'account' => $data['account'],
            'name' => $data['name'],
            'password' => $data['password'],
            'email' => $data['email'] ?? null,
            'role' => 'student',
        ]);

        Log::channel('business')->info('用户注册成功', [
            'user_id' => $user->id,
            'account' => $user->account,
        ]);

        return $user;
    }

    /**
     * 用户登录
     */
    public function login(string $account, string $password): array
    {
        $user = User::where('account', $account)->first();
        if (!$user) {
            throw new BusinessException('账号不存在', ResponseCode::DATA_NOT_FOUND);
        }

        if (!Hash::check($password, $user->password)) {
            throw new BusinessException('密码错误', ResponseCode::PASSWORD_ERROR);
        }

        $token = JWTAuth::fromUser($user);

        Log::channel('business')->info('用户登录成功', [
            'user_id' => $user->id,
            'account' => $user->account,
        ]);

        return [
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL() * 60,
            'user' => [
                'id' => $user->id,
                'account' => $user->account,
                'name' => $user->name,
                'role' => $user->role,
            ],
        ];
    }

    /**
     * 退出登录
     */
    public function logout(): void
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());

            Log::channel('business')->info('用户退出登录', [
                'user_id' => Auth::id(),
            ]);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            throw new BusinessException('退出失败', ResponseCode::BUSINESS_ERROR);
        }
    }

    /**
     * 重置密码
     */
    public function forgetPassword(array $data): void
    {
        $user = User::where('account', $data['account'])->first();
        if (!$user) {
            throw new BusinessException('账号不存在', ResponseCode::DATA_NOT_FOUND);
        }

        if ($user->email !== $data['email']) {
            throw new BusinessException('邮箱与账号不匹配', ResponseCode::PARAM_ERROR);
        }

        $this->verifyEmailCode($data['email'], $data['code'], 'reset_password');

        $user->update(['password' => $data['password']]);

        Log::channel('business')->warning('用户重置密码', [
            'user_id' => $user->id,
            'account' => $user->account,
        ]);
    }

    /**
     * 修改个人信息
     */
    public function updateProfile(User $user, array $data): User
    {
        $fillable = ['name', 'account', 'email', 'phone', 'password'];
        foreach ($fillable as $field) {
            if (!empty($data[$field])) {
                $user->{$field} = $data[$field];
            }
        }

        $user->save();

        Log::channel('business')->info('用户更新资料', [
            'user_id' => $user->id,
            'changed_fields' => array_keys(array_filter($data)),
        ]);

        return $user;
    }

    /**
     * 上传头像
     */
    public function uploadAvatar(User $user, UploadedFile $file): string
    {
        if ($user->avatar && !str_contains($user->avatar, 'default')) {
            $oldPath = str_replace('/storage/', '', $user->avatar);
            if (Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        $fileName = 'avatars/' . $user->id . '_' . time() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('', $fileName, 'public');
        $avatarUrl = '/storage/' . $path;

        $user->update(['avatar' => $avatarUrl]);

        Log::channel('business')->info('用户上传头像', [
            'user_id' => $user->id,
            'avatar' => $avatarUrl,
        ]);

        return $avatarUrl;
    }

    /**
     * 发送邮箱验证码
     */
    public function sendEmailCode(string $email, string $type): array
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new BusinessException('邮箱格式不正确', ResponseCode::PARAM_FORMAT_ERROR);
        }

        $intervalKey = "email_interval:{$email}";
        if (Cache::has($intervalKey)) {
            $remaining = Cache::get($intervalKey) - time();
            throw new BusinessException("发送过于频繁，请{$remaining}秒后再试", ResponseCode::RULE_RESTRICTION);
        }

        switch ($type) {
            case 'register':
                if (User::where('email', $email)->exists()) {
                    throw new BusinessException('该邮箱已被注册', ResponseCode::DATA_DUPLICATE);
                }
                break;
            case 'reset_password':
            case 'delete_account':
                if (!User::where('email', $email)->exists()) {
                    throw new BusinessException('该邮箱未注册', ResponseCode::DATA_NOT_FOUND);
                }
                break;
        }

        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put("email_code:{$email}:{$type}", $code, now()->addMinutes(self::CODE_EXPIRE_MINUTES));
        Cache::put($intervalKey, time() + self::SEND_INTERVAL, now()->addSeconds(self::SEND_INTERVAL));

        try {
            Mail::send('email.verification-code', ['code' => $code], function ($message) use ($email) {
                $message->to($email)->subject('【设备借用系统】您的验证码');
            });

            Log::channel('business')->info('验证码邮件已发送', [
                'email' => $email,
                'type' => $type,
            ]);

            return [
                'message' => '验证码已发送，请查收邮件',
                'expire_minutes' => self::CODE_EXPIRE_MINUTES,
            ];
        } catch (\Exception $e) {
            Cache::forget("email_code:{$email}:{$type}");
            Cache::forget($intervalKey);

            Log::channel('exception')->error('邮件发送失败', [
                'email' => $email,
                'message' => $e->getMessage(),
            ]);

            throw new BusinessException('邮件发送失败', ResponseCode::EMAIL_SEND_FAILED);
        }
    }

    /**
     * 管理员获取用户列表
     */
    public function adminUsers(User $currentUser): array
    {
        if (!$currentUser->isAdmin()) {
            throw new BusinessException('权限不足，仅管理员可访问', ResponseCode::FORBIDDEN);
        }

        return User::select('id', 'account', 'name', 'email', 'role', 'created_at')
            ->get()
            ->toArray();
    }

    /**
     * 注销账号（硬删除）
     */
    public function deleteAccount(User $user, array $data): void
    {
        if ($data['account'] !== $user->account) {
            throw new BusinessException('账号与当前登录账号不匹配', ResponseCode::PARAM_ERROR);
        }

        if ($data['email'] !== $user->email) {
            throw new BusinessException('邮箱与当前账号不匹配', ResponseCode::PARAM_ERROR);
        }

        $this->verifyEmailCode($data['email'], $data['email_code'], 'delete_account');

        Log::channel('business')->warning('用户注销账号', [
            'user_id' => $user->id,
            'account' => $user->account,
        ]);

        $user->forceDelete();
    }

    /**
     * 验证邮箱验证码
     */
    private function verifyEmailCode(string $email, string $code, string $type): void
    {
        $cacheKey = "email_code:{$email}:{$type}";
        $cachedCode = Cache::get($cacheKey);

        if (is_null($cachedCode) || $cachedCode !== $code) {
            throw new BusinessException('邮箱验证码无效或已过期', ResponseCode::PARAM_ERROR);
        }

        Cache::forget($cacheKey);
    }
}
