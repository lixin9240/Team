<?php

return [
    // 短信服务商：aliyun-阿里云，tencent-腾讯云，mock-模拟（开发测试用）
    'driver' => env('SMS_DRIVER', 'mock'),
    
    // 阿里云短信配置
    'aliyun' => [
        'access_key_id' => env('ALIYUN_ACCESS_KEY_ID'),
        'access_key_secret' => env('ALIYUN_ACCESS_KEY_SECRET'),
        'sign_name' => env('ALIYUN_SMS_SIGN_NAME'), // 短信签名
        'template_code' => env('ALIYUN_SMS_TEMPLATE_CODE'), // 验证码模板CODE
    ],
    
    // 验证码有效期（分钟）
    'code_expire' => 5,
    
    // 发送间隔（秒）
    'send_interval' => 60,
];