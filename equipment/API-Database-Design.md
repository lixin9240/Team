# 设备借用系统 — 数据库设计与接口文档

> 本文档基于 Laravel + JWT 鉴权的设备借用 Demo 项目整理，涵盖数据库表结构、视图、关联关系及全部 RESTful 接口定义。

---

## 一、数据库设计

### 1.1 表清单

| 表名 | 说明 | 引擎特性 |
|------|------|----------|
| `users` | 用户表 | 软删除 |
| `devices` | 设备表 | 软删除 |
| `bookings` | 借用记录表 | 软删除 + 外键级联 |
| `categories` | 设备分类表 | 软删除 |
| `device_realtime_stock` | **数据库视图** | 实时库存计算 |

### 1.2 ER 关系

```
users (1) ───────< (N) bookings >────── (1) devices
                              |
                              | N:1
                         categories
```

- `bookings.user_id` → `users.id`（级联删除）
- `bookings.device_id` → `devices.id`（级联删除）
- `devices.category` → `categories.code`（业务关联，非外键约束）

---

### 1.3 表结构详情

#### `users` — 用户表

| 字段 | 类型 | 约束 | 说明 |
|------|------|------|------|
| `id` | `BIGINT UNSIGNED` | PK / AI | 主键 |
| `account` | `VARCHAR(50)` | UNIQUE / NOT NULL | 登录账号 |
| `name` | `VARCHAR(30)` | NOT NULL | 真实姓名 |
| `password` | `VARCHAR(255)` | NOT NULL | 哈希密码 |
| `role` | `ENUM` | NOT NULL / INDEX | `student` / `admin` |
| `phone` | `VARCHAR(20)` | UNIQUE / NULL | 手机号 |
| `email` | `VARCHAR(100)` | NULL | 邮箱（仅支持 QQ 邮箱） |
| `avatar` | `VARCHAR(255)` | NULL | 头像 URL |
| `remember_token` | `VARCHAR(100)` | NULL | Laravel 记住我 |
| `created_at` | `TIMESTAMP` | DEFAULT CURRENT_TIMESTAMP | 创建时间 |
| `updated_at` | `TIMESTAMP` | ON UPDATE CURRENT_TIMESTAMP | 更新时间 |
| `deleted_at` | `TIMESTAMP` | NULL | 软删除时间 |

#### `devices` — 设备表

| 字段 | 类型 | 约束 | 说明 |
|------|------|------|------|
| `id` | `BIGINT UNSIGNED` | PK / AI | 主键 |
| `name` | `VARCHAR(100)` | NOT NULL / INDEX | 设备名称 |
| `category` | `VARCHAR(50)` | NOT NULL / INDEX | 分类编码（关联 categories.code） |
| `description` | `TEXT` | NULL | 设备描述 |
| `total_qty` | `INT UNSIGNED` | NOT NULL / DEFAULT 1 | 总库存 |
| `available_qty` | `INT UNSIGNED` | NOT NULL / DEFAULT 1 | 可借数量（基准值） |
| `status` | `ENUM` | NOT NULL / DEFAULT `available` / INDEX | `available` / `borrowed` / `maintenance` |
| `created_at` | `TIMESTAMP` | DEFAULT CURRENT_TIMESTAMP | 创建时间 |
| `updated_at` | `TIMESTAMP` | ON UPDATE CURRENT_TIMESTAMP | 更新时间 |
| `deleted_at` | `TIMESTAMP` | NULL | 软删除时间 |

> 注：`status` 在实际业务中使用的是 `available` / `maintenance`，`borrowed` 由实时库存视图计算得出。

#### `bookings` — 借用记录表

| 字段 | 类型 | 约束 | 说明 |
|------|------|------|------|
| `id` | `BIGINT UNSIGNED` | PK / AI | 主键 |
| `user_id` | `BIGINT UNSIGNED` | NOT NULL / FK / INDEX | 用户 ID |
| `device_id` | `BIGINT UNSIGNED` | NOT NULL / FK / INDEX | 设备 ID |
| `device_name` | `VARCHAR(100)` | NULL | 设备名称快照（冗余字段） |
| `borrow_start` | `DATE` | NOT NULL | 借用开始日期 |
| `borrow_end` | `DATE` | NOT NULL | 借用结束日期 |
| `purpose` | `TEXT` | NULL | 借用目的 |
| `status` | `VARCHAR(20)` | NOT NULL / DEFAULT `pending` / INDEX | 状态（见下方枚举） |
| `reason` | `VARCHAR(255)` | NULL | 拒绝原因 |
| `reason_type` | `ENUM` | NULL | 拒绝类型（见下方枚举） |
| `created_at` | `TIMESTAMP` | DEFAULT CURRENT_TIMESTAMP | 创建时间 |
| `updated_at` | `TIMESTAMP` | ON UPDATE CURRENT_TIMESTAMP | 更新时间 |
| `deleted_at` | `TIMESTAMP` | NULL | 软删除时间 |

**复合索引**：`[user_id, device_id, status]` — 用于借用冲突校验。

#### `categories` — 设备分类表

| 字段 | 类型 | 约束 | 说明 |
|------|------|------|------|
| `id` | `BIGINT UNSIGNED` | PK / AI | 主键 |
| `name` | `VARCHAR(50)` | NOT NULL | 分类名称 |
| `code` | `VARCHAR(50)` | UNIQUE / NOT NULL / INDEX | 分类编码 |
| `description` | `VARCHAR(255)` | NULL | 分类描述 |
| `sort_order` | `INT UNSIGNED` | DEFAULT 0 / INDEX | 排序值 |
| `is_active` | `BOOLEAN` | DEFAULT true / INDEX | 是否启用 |
| `created_at` | `TIMESTAMP` | DEFAULT CURRENT_TIMESTAMP | 创建时间 |
| `updated_at` | `TIMESTAMP` | ON UPDATE CURRENT_TIMESTAMP | 更新时间 |
| `deleted_at` | `TIMESTAMP` | NULL | 软删除时间 |

#### `device_realtime_stock` — 实时库存视图

该视图为 Service 层查询使用，核心计算逻辑：

```sql
-- 伪代码，等价逻辑
SELECT
    d.*,
    d.total_qty
      - (SELECT COUNT(*) FROM bookings WHERE device_id = d.id AND status IN ('approved','pending','returning'))
      - (SELECT COUNT(*) FROM bookings WHERE device_id = d.id AND status = 'rejected' AND reason_type = 'device_unavailable')
    AS realtime_available_qty
FROM devices d
```

---

### 1.4 枚举值定义

#### 借用状态 `bookings.status`

| 常量 | 值 | 说明 |
|------|-----|------|
| `STATUS_PENDING` | `pending` | 待审核 |
| `STATUS_APPROVED` | `approved` | 已通过（借用中） |
| `STATUS_REJECTED` | `rejected` | 已拒绝 |
| `STATUS_RETURNING` | `returning` | 申请归还（待管理员审核） |
| `STATUS_RETURNED` | `returned` | 已归还 |
| `STATUS_RETURN_REJECTED` | `return_rejected` | 归还申请被拒绝 |

#### 拒绝类型 `bookings.reason_type`

| 值 | 说明 |
|-----|------|
| `device_unavailable` | 设备不可用（会减少可用数量） |
| `insufficient_stock` | 库存不足 |
| `invalid_purpose` | 借用目的不合理 |
| `time_conflict` | 时间冲突 |
| `other` | 其他原因 |

#### 设备状态 `devices.status`

| 值 | 说明 |
|-----|------|
| `available` | 可借用 |
| `maintenance` | 维护中 |

#### 用户角色 `users.role`

| 值 | 说明 |
|-----|------|
| `student` | 学生 |
| `admin` | 管理员 |

---

## 二、接口文档

### 2.1 统一响应格式

```json
{
  "code": 200,
  "message": "操作成功",
  "data": {}
}
```

| 字段 | 类型 | 说明 |
|------|------|------|
| `code` | `int` | 业务状态码，`200` 为成功 |
| `message` | `string` | 提示信息 |
| `data` | `mixed` | 响应数据 |

### 2.2 认证方式

- 登录成功后返回 `JWT Token`
- 后续请求在 Header 中携带：`Authorization: Bearer {token}`
- 受保护接口统一使用 `jwt.auth` 中间件

---

### 2.3 公开接口（无需认证）

#### 1. 用户注册

```http
POST /api/auth/register
```

**请求参数**

| 字段 | 类型 | 必填 | 规则 |
|------|------|------|------|
| `account` | `string` | 是 | 4-20 字符，唯一 |
| `name` | `string` | 是 | 2-20 字符 |
| `password` | `string` | 是 | ≥6 位，必须同时包含字母和数字 |
| `password_confirmation` | `string` | 是 | 必须与 password 一致 |
| `email` | `string` | 是 | 仅支持 QQ 邮箱（`@qq.com`） |
| `email_code` | `string` | 是 | 6 位数字验证码 |

**响应示例**

```json
{
  "code": 200,
  "message": "注册成功",
  "data": {
    "id": 1,
    "account": "zhangsan",
    "name": "张三",
    "role": "student"
  }
}
```

---

#### 2. 用户登录

```http
POST /api/auth/login
```

**请求参数**

| 字段 | 类型 | 必填 |
|------|------|------|
| `account` | `string` | 是 |
| `password` | `string` | 是 |

**响应示例**

```json
{
  "code": 200,
  "message": "登录成功",
  "data": {
    "token": "eyJ0eXAiOiJKV1Qi...",
    "token_type": "bearer",
    "expires_in": 3600,
    "user": {
      "id": 1,
      "account": "zhangsan",
      "name": "张三",
      "role": "student"
    }
  }
}
```

---

#### 3. 忘记密码 / 重置密码

```http
POST /api/auth/forget-password
```

**请求参数**

| 字段 | 类型 | 必填 | 规则 |
|------|------|------|------|
| `account` | `string` | 是 | 登录账号 |
| `email` | `string` | 是 | QQ 邮箱 |
| `code` | `string` | 是 | 6 位验证码 |
| `password` | `string` | 是 | ≥6 位，字母+数字 |
| `password_confirmation` | `string` | 是 | 确认密码 |

---

#### 4. 发送邮箱验证码

```http
POST /api/auth/send-email-code
```

**请求参数**

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `email` | `string` | 是 | 邮箱地址 |
| `type` | `string` | 是 | `register` / `reset_password` / `bind` / `delete_account` |

**响应示例**

```json
{
  "code": 200,
  "message": "验证码已发送，请查收邮件",
  "data": {
    "expire_minutes": 5
  }
}
```

---

### 2.4 认证接口（需携带 JWT Token）

#### 用户模块

##### 5. 获取当前用户信息

```http
GET /api/auth/me
```

**响应示例**

```json
{
  "code": 200,
  "message": "获取成功",
  "data": {
    "id": 1,
    "account": "zhangsan",
    "name": "张三",
    "role": "student",
    "email": "123456@qq.com",
    "avatar": "/storage/avatars/1_1234567890.jpg"
  }
}
```

---

##### 6. 退出登录

```http
POST /api/auth/logout
```

---

##### 7. 修改个人信息

```http
PUT /api/auth/profile
```

**请求参数**（全部可选）

| 字段 | 类型 | 规则 |
|------|------|------|
| `account` | `string` | 唯一 |
| `email` | `string` | 邮箱，唯一 |
| `phone` | `string` | 手机号，唯一，1 开头 11 位 |
| `password` | `string` | ≥6 位 |

---

##### 8. 上传头像

```http
POST /api/auth/avatar
Content-Type: multipart/form-data
```

**请求参数**

| 字段 | 类型 | 必填 | 规则 |
|------|------|------|------|
| `avatar` | `file` | 是 | jpg / jpeg / png / gif，最大 2MB |

**响应示例**

```json
{
  "code": 200,
  "message": "头像上传成功",
  "data": {
    "avatar": "/storage/avatars/1_1234567890.jpg"
  }
}
```

---

##### 9. 注销账号

```http
DELETE /api/account
```

**请求参数**

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `account` | `string` | 是 | 当前登录账号 |
| `email` | `string` | 是 | 当前绑定邮箱 |
| `email_code` | `string` | 是 | 6 位验证码（`type=delete_account`） |

---

##### 10. 获取所有用户列表（管理员）

```http
GET /api/admin/users
```

> 权限：仅 `admin` 角色可访问。

**响应示例**

```json
{
  "code": 200,
  "message": "获取用户列表成功",
  "data": [
    { "id": 1, "account": "zhangsan", "name": "张三", "email": "123@qq.com", "role": "student", "created_at": "2025-01-01 10:00:00" }
  ]
}
```

---

#### 设备大厅模块

##### 11. 获取设备列表

```http
GET /api/devices
```

**查询参数**

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `name` | `string` | 否 | 按设备名称模糊搜索 |
| `category` | `string` | 否 | 按分类编码或名称筛选 |
| `status` | `string` | 否 | `available` / `maintenance` |
| `page` | `int` | 否 | 页码，默认 1 |
| `pageSize` | `int` | 否 | 每页条数，默认 10 |

**响应示例**

```json
{
  "code": 200,
  "message": "获取成功",
  "data": {
    "total": 50,
    "page": 1,
    "pageSize": 10,
    "list": [
      {
        "id": 1,
        "name": "单反相机",
        "category": "摄影器材",
        "status": "available",
        "available_qty": 3
      }
    ]
  }
}
```

---

##### 12. 获取设备详情

```http
GET /api/devices/{id}
```

**响应示例**

```json
{
  "code": 200,
  "message": "获取成功",
  "data": {
    "id": 1,
    "name": "单反相机",
    "category": "photography",
    "category_info": {
      "id": 1,
      "name": "摄影器材",
      "code": "photography",
      "description": "相机、镜头等设备"
    },
    "description": "佳能 EOS R5",
    "total_qty": 5,
    "available_qty": 3,
    "status": "available",
    "related_devices": [
      { "id": 2, "name": "微单相机", "available_qty": 2 }
    ],
    "created_at": "2025-01-01 10:00:00",
    "updated_at": "2025-01-01 10:00:00"
  }
}
```

---

#### 借用申请模块

##### 13. 发起借用申请

```http
POST /api/bookings
```

**请求参数**

| 字段 | 类型 | 必填 | 规则 |
|------|------|------|------|
| `device_id` | `int` | 是 | 设备 ID，须存在 |
| `borrow_start` | `date` | 是 | 借用开始日期 |
| `borrow_end` | `date` | 是 | 必须 ≥ borrow_start |
| `purpose` | `string` | 否 | 借用目的 |

---

##### 14. 获取个人借用记录

```http
GET /api/bookings/my
```

**查询参数**

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `status` | `string` | 否 | 按状态筛选 |
| `page` | `int` | 否 | 页码，默认 1 |
| `pageSize` | `int` | 否 | 每页条数，默认 10 |

**响应示例**

```json
{
  "code": 200,
  "message": "获取成功",
  "data": {
    "total": 10,
    "page": 1,
    "pageSize": 10,
    "list": [
      {
        "id": 1,
        "device_name": "单反相机",
        "borrow_start": "2025-04-01",
        "borrow_end": "2025-04-07",
        "purpose": "毕业设计拍摄",
        "status": "pending",
        "reason": null,
        "reason_type": null,
        "created_at": "2025-03-25 10:00:00",
        "device": {
          "id": 1,
          "name": "单反相机",
          "category_code": "photography",
          "category": { "id": 1, "name": "摄影器材", "code": "photography" }
        }
      }
    ]
  }
}
```

---

##### 15. 申请归还设备

```http
PATCH /api/bookings/{id}/return
```

> 仅 `status = approved` 的记录可操作。

**响应示例**

```json
{
  "code": 200,
  "message": "归还申请已提交，等待管理员审核",
  "data": {
    "id": 1,
    "status": "returning",
    "updated_at": "2025-04-07 10:00:00"
  }
}
```

---

#### 分类模块（公开查看）

##### 16. 获取分类列表

```http
GET /api/categories
```

**查询参数**

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `keyword` | `string` | 否 | 按名称或编码模糊搜索 |
| `is_active` | `boolean` | 否 | 是否启用（管理员可传，学生默认只看启用） |
| `page` | `int` | 否 | 页码，默认 1 |
| `pageSize` | `int` | 否 | 每页条数，默认 10 |

**响应示例**

```json
{
  "code": 200,
  "message": "获取成功",
  "data": {
    "total": 5,
    "page": 1,
    "pageSize": 10,
    "list": [
      {
        "id": 1,
        "name": "摄影器材",
        "code": "photography",
        "description": "相机、镜头等",
        "sort_order": 0,
        "is_active": true,
        "device_count": 10,
        "created_at": "2025-01-01 10:00:00",
        "updated_at": "2025-01-01 10:00:00"
      }
    ]
  }
}
```

---

##### 17. 获取分类统计

```http
GET /api/categories/statistics
```

**响应示例**

```json
{
  "code": 200,
  "message": "获取成功",
  "data": {
    "categories": [
      {
        "id": 1,
        "name": "摄影器材",
        "code": "photography",
        "device_count": 10,
        "total_qty": 50,
        "available_qty": 30
      }
    ],
    "total_categories": 5,
    "total_devices": 50
  }
}
```

---

##### 18. 获取分类详情

```http
GET /api/categories/{id}
```

**响应示例**

```json
{
  "code": 200,
  "message": "获取成功",
  "data": {
    "id": 1,
    "name": "摄影器材",
    "code": "photography",
    "description": "相机、镜头等",
    "sort_order": 0,
    "is_active": true,
    "device_count": 2,
    "devices": [
      {
        "id": 1,
        "name": "单反相机",
        "status": "available",
        "total_qty": 5,
        "available_qty": 3,
        "real_available_qty": 3,
        "borrowed_count": 2
      }
    ],
    "created_at": "2025-01-01 10:00:00",
    "updated_at": "2025-01-01 10:00:00"
  }
}
```

---

### 2.5 管理员接口（需 `admin` 角色）

#### 借用审核模块

##### 19. 获取待审核借用列表

```http
GET /api/admin/bookings/pending
```

**查询参数**：`page`, `pageSize`

---

##### 20. 获取已拒绝借用列表

```http
GET /api/admin/bookings/rejected
```

**查询参数**：`page`, `pageSize`

---

##### 21. 审核借用申请

```http
PATCH /api/admin/bookings/{id}/audit
```

**请求参数**

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `action` | `string` | 是 | `approve`（通过）/ `reject`（拒绝） |
| `reason` | `string` | 拒绝时必填 | 拒绝原因，最大 255 字符 |
| `reason_type` | `string` | 否 | `device_unavailable` / `insufficient_stock` / `invalid_purpose` / `time_conflict` / `other` |

**响应示例**（通过）

```json
{
  "code": 200,
  "message": "申请已通过",
  "data": {
    "id": 1,
    "device_id": 1,
    "device_name": "单反相机",
    "device_category": "photography",
    "status": "approved",
    "borrow_start": "2025-04-01",
    "borrow_end": "2025-04-07",
    "purpose": "毕业设计拍摄"
  }
}
```

---

##### 22. 获取申请归还列表

```http
GET /api/admin/bookings/returning
```

**查询参数**：`page`, `pageSize`

---

##### 23. 审核归还申请

```http
PATCH /api/admin/bookings/{id}/return-audit
```

**请求参数**

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `action` | `string` | 是 | `approve` / `reject` |
| `reason` | `string` | 拒绝时必填 | 拒绝原因 |

---

##### 24. 获取已归还列表

```http
GET /api/admin/bookings/returned
```

**查询参数**：`page`, `pageSize`

---

##### 25. 获取未归还列表

```http
GET /api/admin/bookings/unreturned
```

> 筛选 `status = approved` 的借用记录。

**查询参数**：`page`, `pageSize`

---

##### 26. 获取归还被拒绝列表

```http
GET /api/admin/bookings/return-rejected
```

**查询参数**：`page`, `pageSize`

---

#### 设备管理模块

##### 27. 新增设备

```http
POST /api/admin/devices
```

**请求参数**

| 字段 | 类型 | 必填 | 规则 |
|------|------|------|------|
| `name` | `string` | 是 | 最大 100 字符 |
| `category` | `string` | 是 | 分类编码或名称，须存在 |
| `description` | `string` | 否 | 设备描述 |
| `total_qty` | `int` | 是 | ≥1 |
| `available_qty` | `int` | 是 | ≥0 |
| `status` | `string` | 是 | `available` / `maintenance` |

---

##### 28. 更新设备

```http
PUT /api/admin/devices/{id}
```

**请求参数**（全部可选）

| 字段 | 类型 | 规则 |
|------|------|------|
| `name` | `string` | 最大 100 字符 |
| `category` | `string` | 分类编码或名称 |
| `description` | `string` | 设备描述 |
| `total_qty` | `int` | ≥1 |
| `available_qty` | `int` | ≥0 |
| `status` | `string` | `available` / `maintenance` |

---

##### 29. 删除设备（下架）

```http
DELETE /api/admin/devices/{id}
```

**响应示例**

```json
{
  "code": 200,
  "message": "设备已下架",
  "data": { "id": 1 }
}
```

---

#### 分类管理模块

##### 30. 创建分类

```http
POST /api/admin/categories
```

**请求参数**

| 字段 | 类型 | 必填 | 规则 |
|------|------|------|------|
| `name` | `string` | 是 | 最大 50 字符 |
| `code` | `string` | 是 | 最大 50 字符，唯一 |
| `description` | `string` | 否 | 最大 255 字符 |
| `is_active` | `boolean` | 否 | 默认 `true` |

---

##### 31. 更新分类

```http
PUT /api/admin/categories/{id}
```

**请求参数**（全部可选）

| 字段 | 类型 | 规则 |
|------|------|------|
| `name` | `string` | 最大 50 字符 |
| `code` | `string` | 最大 50 字符，唯一（当前记录除外） |
| `description` | `string` | 最大 255 字符 |
| `sort_order` | `int` | ≥0 |
| `is_active` | `boolean` | |

> 若修改 `code`，系统会自动同步 `devices` 表中的关联分类编码。

---

##### 32. 删除分类

```http
DELETE /api/admin/categories/{id}
```

> 若分类下存在设备，则禁止删除。

---

##### 33. 切换分类启用状态

```http
PATCH /api/admin/categories/{id}/toggle-status
```

**响应示例**

```json
{
  "code": 200,
  "message": "分类已禁用",
  "data": {
    "id": 1,
    "name": "摄影器材",
    "is_active": false,
    "status_text": "禁用"
  }
}
```

---

#### 用户管理模块

##### 34. 注销用户账号

```http
DELETE /api/admin/users/{id}
```

> 管理员不能注销自己的账号。

---

## 三、附录

### 3.1 状态流转图

```
                    ┌─────────────┐
        ┌──────────│   pending   │◄──────────────┐
        │          │  （待审核）  │                │
        │          └──────┬──────┘                │
        │                 │                       │
   approve            reject                      │
        │                 │                       │
        ▼                 ▼                       │
┌─────────────┐    ┌─────────────┐                │
│  approved   │    │  rejected   │                │
│ （借用中）   │    │  （已拒绝）  │                │
└──────┬──────┘    └─────────────┘                │
       │                                          │
       │ return（申请归还）                         │
       ▼                                          │
┌─────────────┐    ┌─────────────┐                │
│  returning  │───►│   returned  │                │
│（归还待审）  │approve│  （已归还）  │                │
└──────┬──────┘    └─────────────┘                │
       │ reject                                   │
       ▼                                           │
┌─────────────┐                                    │
│return_rejected│──────────────────────────────────┘
│（归还拒绝）  │        可重新申请归还
└─────────────┘
```

### 3.2 常用 Model 作用域速查

| 模型 | 作用域 | 说明 |
|------|--------|------|
| `Booking` | `byUser($userId)` | 按用户筛选 |
| `Booking` | `byDevice($deviceId)` | 按设备筛选 |
| `Booking` | `pending()` / `approved()` / `rejected()` / `returning()` / `returned()` / `returnRejected()` | 按状态筛选 |
| `Booking` | `betweenDates($start, $end)` | 按借用日期范围筛选 |
| `Booking` | `overdue()` | 逾期未归还 |
| `Device` | `available()` / `maintenance()` | 按设备状态筛选 |
| `Device` | `inStock()` | 有库存 |
| `Device` | `byCategory($code)` | 按分类编码筛选 |
| `Category` | `active()` | 启用的分类 |
| `Category` | `ordered()` | 按排序值升序 |
| `Category` | `byCode($code)` | 按编码筛选 |
| `User` | `student()` / `admin()` | 按角色筛选 |

### 3.3 环境依赖

- **PHP**: ≥ 8.1
- **Laravel**: 10.x / 11.x
- **认证**: `tymon/jwt-auth` (JWT)
- **缓存**: Redis（验证码限流）
- **邮件**: QQ 邮箱 SMTP（发送验证码）
- **存储**: `storage/public`（头像文件）
