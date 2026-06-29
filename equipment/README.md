
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
  │   │   ├── TraceIdMiddleware.php   # 链路追踪
  │   │   └── ApiLogMiddleware.php    # API请求日志
  │   └── Requests/
  │       ├── RegisterRequest.php
  │       ├── LoginRequest.php
  │       ├── ForgetPasswordRequest.php
  │       ├── UpdateProfileRequest.php
  │       ├── SendEmailCodeRequest.php
  │       ├── DeleteAccountRequest.php
  │       ├── AuditBookingRequest.php
  │       ├── AuditReturnRequest.php
  │       ├── CreateDeviceRequest.php
  │       ├── UpdateDeviceRequest.php
  │       ├── CreateCategoryRequest.php
  │       ├── UpdateCategoryRequest.php
  │       └── CreateBookingRequest.php
  ├── Models/
  │   ├── User.php
  │   ├── Device.php
  │   ├── Booking.php
  │   └── Category.php
  ├── Services/
  │   ├── AuthService.php            # 认证业务逻辑
  │   ├── AdminService.php           # 管理业务逻辑
  │   └── EquipmentService.php       # 设备/借用业务逻辑
  └── Support/
      └── Result.php                 # 统一响应