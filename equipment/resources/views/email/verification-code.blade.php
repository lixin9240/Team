<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>验证码</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #007bff;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .code {
            font-size: 36px;
            font-weight: bold;
            color: #007bff;
            text-align: center;
            letter-spacing: 8px;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            color: #666;
            font-size: 12px;
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>设备借用系统</h2>
            <p>邮箱验证码</p>
        </div>
        
        <p>您好，</p>
        <p>您的验证码为：</p>
        
        <div class="code">{{ $code }}</div>
        
        <p>验证码有效期为 <strong>5分钟</strong>，请尽快使用。</p>
        <p>如非本人操作，请忽略此邮件。</p>
        
        <div class="footer">
            <p>此邮件由系统自动发送，请勿回复</p>
            <p>&copy; {{ date('Y') }} 设备借用系统</p>
        </div>
    </div>
</body>
</html>