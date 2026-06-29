
  app/
  ├── Enums/
  │   └── ResponseCode.php           # 响应码枚举
  ├── Exceptions/
  │   └── BusinessException.php      # 业务异常
  ├── Http/
  │   ├── Controllers/
  │   │   ├── AuthController.php     # 认证（~150行，含注入）
  │   │   ├── AdminController.php    # 管理后台（~170行）
  │   │   └── EquipmentController.php # 设备借用（~65行）
  │   ├── Middleware/
  │   │   ├── TraceIdMiddleware.php   # 链路追踪中间件，记录请求ID
  │   │   └── ApiLogMiddleware.php    # API请求日志中间件，记录请求参数、响应、耗时
  │   └── Requests/
  │       ├── RegisterRequest.php// 注册请求
  │       ├── LoginRequest.php// 登录请求
  │       ├── ForgetPasswordRequest.php// 忘记密码请求
  │       ├── UpdateProfileRequest.php// 更新用户信息请求
  │       ├── SendEmailCodeRequest.php// 发送邮箱验证码请求
  │       ├── DeleteAccountRequest.php// 删除账号请求
  │       ├── AuditBookingRequest.php// 审核借用请求
  │       ├── AuditReturnRequest.php// 审核归还请求
  │       ├── CreateDeviceRequest.php//创建设备
  │       ├── UpdateDeviceRequest.php//更新设备
  │       ├── CreateCategoryRequest.php//创建分类
  │       ├── UpdateCategoryRequest.php//更新分类
  │       └── CreateBookingRequest.php//创建借用
  ├── Models/
  │   ├── User.php//用户
  │   ├── Device.php//设备
  │   ├── Booking.php//借用
  │   └── Category.php//分类
  ├── Services/
  │   ├── AuthService.php            # 认证业务逻辑
  │   ├── AdminService.php           # 管理业务逻辑
  │   └── EquipmentService.php       # 设备/借用业务逻辑
  └── Support/
      └── Result.php                 # 统一响应