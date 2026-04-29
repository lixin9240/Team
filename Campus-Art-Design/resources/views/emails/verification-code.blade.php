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
            max-width: 500px;
            margin: 0 auto;
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .code {
            font-size: 32px;
            font-weight: bold;
            color: #333;
            text-align: center;
            padding: 20px;
            background-color: #f0f0f0;
            border-radius: 5px;
            letter-spacing: 5px;
        }
        .footer {
            margin-top: 30px;
            font-size: 12px;
            color: #999;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>校园艺术设计平台</h2>
            <p>您正在进行邮箱验证</p>
        </div>
        
        <p>您好！</p>
        <p>您的验证码是：</p>
        
        <div class="code">{{ $code }}</div>
        
        <p>验证码将在 5 分钟后过期，请及时使用。</p>
        <p>如非本人操作，请忽略此邮件。</p>
        
        <div class="footer">
            <p>此邮件由系统自动发送，请勿回复</p>
            <p>校园艺术设计平台</p>
        </div>
    </div>
</body>
</html>