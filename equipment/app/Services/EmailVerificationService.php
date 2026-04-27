<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;

class EmailVerificationService
{
    /**
     * 验证码有效期（分钟）
     */
    const CODE_EXPIRE_MINUTES = 5;

    /**
     * 发送间隔（秒）
     */
    const SEND_INTERVAL = 10;

    /**
     * 每日发送上限
     */
    const DAILY_LIMIT = 10;

    /**
     * 发送验证码邮件
     */
    public function sendCode(string $email, string $type = 'register'): array
    {
        // 验证邮箱格式
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => '邮箱格式不正确'];
        }

        // 检查发送间隔
        $intervalKey = "email_interval:{$email}";
        if (Cache::has($intervalKey)) {
            $remaining = Cache::get($intervalKey) - time();
            return ['success' => false, 'message' => "发送过于频繁，请{$remaining}秒后再试"];
        }

        // 每日发送上限限制已移除（可无限发送）
        // $dailyKey = "email_daily:{$email}:" . date('Y-m-d');
        // $dailyCount = Cache::get($dailyKey, 0);
        // if ($dailyCount >= self::DAILY_LIMIT) {
        //     return ['success' => false, 'message' => '今日发送次数已达上限'];
        // }

        // 生成6位数字验证码
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // 存储验证码到缓存
        $codeKey = "email_code:{$email}:{$type}";
        Cache::put($codeKey, $code, now()->addMinutes(self::CODE_EXPIRE_MINUTES));

        // 设置发送间隔限制
        Cache::put($intervalKey, time() + self::SEND_INTERVAL, now()->addSeconds(self::SEND_INTERVAL));

        // 每日发送计数已移除
        // Cache::put($dailyKey, $dailyCount + 1, now()->endOfDay());

        // 发送邮件
        try {
            Mail::send('email.verification-code', ['code' => $code], function ($message) use ($email) {
                $message->to($email)->subject('【设备借用系统】您的验证码');
            });

            return [
                'success' => true,
                'message' => '验证码已发送，请查收邮件',
                'expire_minutes' => self::CODE_EXPIRE_MINUTES
            ];
        } catch (\Exception $e) {
            Cache::forget($codeKey);
            Cache::forget($intervalKey);
            \Illuminate\Support\Facades\Log::error('邮件发送失败: ' . $e->getMessage());
            return ['success' => false, 'message' => '邮件发送失败: ' . $e->getMessage()];
        }
    }

    /**
     * 验证验证码
     */
    public function verifyCode(string $email, string $code, string $type = 'register'): bool
    {
        $codeKey = "email_code:{$email}:{$type}";
        $cachedCode = Cache::get($codeKey);

        if ($cachedCode === $code) {
            Cache::forget($codeKey); // 验证成功后删除
            return true;
        }

        return false;
    }
}