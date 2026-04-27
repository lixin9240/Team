<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Category;
use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class LXController extends \Illuminate\Routing\Controller
{
    /**
     * 获取当前登录用户（JWT）
     */
    protected function getCurrentUser()
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return null;
            }
            return $user;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 检查是否是管理员
     */
    protected function isAdmin(): bool
    {
        $user = $this->getCurrentUser();
        return $user && $user->role === 'admin';
    }

    /**
     * 获取待审核申请列表（管理员功能）
     * GET /api/admin/bookings/pending
     */
    public function getPendingBookings(Request $request): JsonResponse
    {
        // JWT 认证检查
        $user = $this->getCurrentUser();
        if (!$user) {
            return response()->json([
                'code' => 401,
                'message' => '未登录或token已过期'
            ], 401);
        }

        // 检查是否是管理员
        if (!$this->isAdmin()) {
            return response()->json([
                'code' => 403,
                'message' => '无权限访问，只有管理员可以查看待审核列表'
            ], 403);
        }

        // 获取分页参数
        $page = $request->input('page', 1);
        $pageSize = $request->input('pageSize', 10);

        // 获取待审核列表，关联用户和设备信息
        $query = Booking::with(['user', 'device'])
            ->where('status', Booking::STATUS_PENDING)
            ->orderBy('created_at', 'desc');

        $total = $query->count();
        $bookings = $query->forPage($page, $pageSize)->get();

        // 格式化返回数据
        $list = $bookings->map(function ($booking) {
            // 获取分类详细信息
            $category = \App\Models\Category::where('code', $booking->device->category ?? '')->first();

            return [
                'id' => $booking->id,
                'user_name' => $booking->user->name ?? '',
                'device_name' => $booking->device->name ?? '',
                'borrow_start' => $booking->borrow_start,
                'borrow_end' => $booking->borrow_end,
                'status' => $booking->status,
                'created_at' => $booking->created_at,
                'user' => [
                    'id' => $booking->user->id ?? null,
                    'account' => $booking->user->account ?? '',
                    'name' => $booking->user->name ?? ''
                ],
                'device' => [
                    'id' => $booking->device->id ?? null,
                    'name' => $booking->device->name ?? '',
                    'available_qty' => $booking->device->available_qty ?? 0,
                    'category_code' => $booking->device->category ?? '',
                    'category' => $category ? [
                        'id' => $category->id,
                        'name' => $category->name,
                        'code' => $category->code,
                    ] : null,
                ]
            ];
        });

        return response()->json([
            'code' => 200,
            'message' => '获取成功',
            'data' => [
                'total' => $total,
                'page' => (int) $page,
                'pageSize' => (int) $pageSize,
                'list' => $list
            ]
        ]);
    }

    /**
     * 获取待审核归还列表（管理员功能）
     * GET /api/admin/bookings/returning
     */
    public function getReturningBookings(Request $request): JsonResponse
    {
        // JWT 认证检查
        $user = $this->getCurrentUser();
        if (!$user) {
            return response()->json([
                'code' => 401,
                'message' => '未登录或token已过期'
            ], 401);
        }

        // 检查是否是管理员
        if (!$this->isAdmin()) {
            return response()->json([
                'code' => 403,
                'message' => '无权限访问，只有管理员可以查看待审核归还列表'
            ], 403);
        }

        // 获取分页参数
        $page = $request->input('page', 1);
        $pageSize = $request->input('pageSize', 10);

        // 获取待审核归还列表，关联用户和设备信息（包含软删除的）
        $query = Booking::with(['user' => function($q) {
                $q->withTrashed();
            }, 'device' => function($q) {
                $q->withTrashed();
            }])
            ->where('status', Booking::STATUS_RETURNING)
            ->orderBy('updated_at', 'desc');

        $total = $query->count();
        $bookings = $query->forPage($page, $pageSize)->get();

        // 格式化返回数据
        $list = $bookings->map(function ($booking) {
            // 获取分类详细信息
            $category = \App\Models\Category::where('code', $booking->device->category ?? '')->first();

            return [
                'id' => $booking->id,
                'user_name' => $booking->user->name ?? '',
                'device_name' => $booking->device->name ?? '',
                'borrow_start' => $booking->borrow_start,
                'borrow_end' => $booking->borrow_end,
                'status' => $booking->status,
                'created_at' => $booking->created_at,
                'updated_at' => $booking->updated_at,
                'user' => [
                    'id' => $booking->user->id ?? null,
                    'account' => $booking->user->account ?? '',
                    'name' => $booking->user->name ?? ''
                ],
                'device' => [
                    'id' => $booking->device->id ?? null,
                    'name' => $booking->device->name ?? '',
                    'available_qty' => $booking->device->available_qty ?? 0,
                    'category_code' => $booking->device->category ?? '',
                    'category' => $category ? [
                        'id' => $category->id,
                        'name' => $category->name,
                        'code' => $category->code,
                    ] : null,
                ]
            ];
        });

        return response()->json([
            'code' => 200,
            'message' => '获取成功',
            'data' => [
                'total' => $total,
                'page' => (int) $page,
                'pageSize' => (int) $pageSize,
                'list' => $list
            ]
        ]);
    }

    /**
     * 审核借用申请（管理员功能）
     * PATCH /api/admin/bookings/{id}/audit
     */
    public function auditBooking(Request $request, $id): JsonResponse
    {
        // JWT 认证检查
        $user = $this->getCurrentUser();
        if (!$user) {
            return response()->json([
                'code' => 401,
                'message' => '未登录或token已过期'
            ], 401);
        }

        // 检查是否是管理员
        if (!$this->isAdmin()) {
            return response()->json([
                'code' => 403,
                'message' => '无权限访问，只有管理员可以审核申请'
            ], 403);
        }

        // 验证参数
        $request->validate([
            'action' => 'required|in:approve,reject',
            'reason' => 'required_if:action,reject|string|max:255',
            'reason_type' => 'nullable|in:device_unavailable,insufficient_stock,invalid_purpose,time_conflict,other',
            // 拒绝原因类型，比如：设备不可用、库存不足、目的无效、时间冲突、其他
        ]);

        // 查找申请记录
        $booking = Booking::find($id);
        if (!$booking) {
            return response()->json([
                'code' => 404,
                'message' => '申请记录不存在'
            ], 404);
        }

        // 检查是否已经是待审核状态
        if ($booking->status !== Booking::STATUS_PENDING) {
            return response()->json([
                'code' => 400,
                'message' => '该申请已处理，无法重复审核'
            ], 400);
        }

        $action = $request->input('action');

        // 获取设备信息
        $device = \App\Models\Device::find($booking->device_id);
        $deviceName = $device ? $device->name : '未知设备';
        $deviceCategory = $device ? $device->category : null;

        if ($action === 'approve') {
            // 批准申请
            $booking->status = Booking::STATUS_APPROVED;
            $booking->save();

            return response()->json([
                'code' => 200,
                'message' => '申请已通过',
                'data' => [
                    'id' => $booking->id,
                    'device_id' => $booking->device_id,
                    'device_name' => $deviceName,
                    'device_category' => $deviceCategory,  // 设备类型/分类
                    'status' => $booking->status,
                    'borrow_start' => $booking->borrow_start,
                    'borrow_end' => $booking->borrow_end,
                    'purpose' => $booking->purpose
                ]
            ]);
        } else {
            // 拒绝申请（库存通过实时计算，无需手动维护）
            $reasonType = $request->input('reason_type', 'other');

            $booking->status = Booking::STATUS_REJECTED;
            $booking->reason = $request->input('reason');
            $booking->reason_type = $reasonType;
            $booking->save();

            return response()->json([
                'code' => 200,
                'message' => '申请已拒绝',
                'data' => [
                    'id' => $booking->id,
                    'device_id' => $booking->device_id,
                    'device_name' => $deviceName,
                    'device_category' => $deviceCategory,  // 设备类型/分类
                    'status' => $booking->status,
                    'reason' => $booking->reason,
                    'reason_type' => $reasonType,
                    'device_affected' => $reasonType === 'device_unavailable'
                ]
            ]);
        }
    }

    /**
     * 审核归还申请（管理员功能）
     * PATCH /api/admin/bookings/{id}/return-audit
     */
    public function auditReturnBooking(Request $request, $id): JsonResponse
    {
        // JWT 认证检查
        $user = $this->getCurrentUser();
        if (!$user) {
            return response()->json([
                'code' => 401,
                'message' => '未登录或token已过期'
            ], 401);
        }

        // 检查是否是管理员
        if (!$this->isAdmin()) {
            return response()->json([
                'code' => 403,
                'message' => '无权限访问，只有管理员可以审核归还申请'
            ], 403);
        }

        // 验证参数
        $request->validate([
            'action' => 'required|in:approve,reject',
            'reason' => 'required_if:action,reject|string|max:255',
        ]);

        // 查找申请记录
        $booking = Booking::find($id);
        if (!$booking) {
            return response()->json([
                'code' => 404,
                'message' => '归还记录不存在'
            ], 404);
        }

        // 检查是否是申请归还状态
        if ($booking->status !== Booking::STATUS_RETURNING) {
            return response()->json([
                'code' => 400,
                'message' => '该记录不是申请归还状态，无法审核'
            ], 400);
        }

        $action = $request->input('action');

        if ($action === 'approve') {
            // 批准归还
            $booking->status = Booking::STATUS_RETURNED;
            $booking->save();

            return response()->json([
                'code' => 200,
                'message' => '归还申请已通过',
                'data' => $booking
            ]);
        } else {
            // 拒绝归还申请
            $booking->status = Booking::STATUS_RETURN_REJECTED;  // 设置为拒绝归还状态
            $booking->reason = $request->input('reason');  // 拒绝原因（使用 reason 字段）
            $booking->save();

            return response()->json([
                'code' => 200,
                'message' => '归还申请已拒绝',
                'data' => [
                    'id' => $booking->id,
                    'device_name' => $booking->device_name,
                    'borrow_start' => $booking->borrow_start,
                    'borrow_end' => $booking->borrow_end,
                    'status' => $booking->status,
                    'reason' => $booking->reason,
                    'updated_at' => $booking->updated_at
                ]
            ]);
        }
    }

    /**
     * 获取已归还列表（管理员功能）
     * GET /api/admin/bookings/returned
     */
    public function getReturnedBookings(Request $request): JsonResponse
    {
        // JWT 认证检查
        $user = $this->getCurrentUser();
        if (!$user) {
            return response()->json([
                'code' => 401,
                'message' => '未登录或token已过期'
            ], 401);
        }

        // 检查是否是管理员
        if (!$this->isAdmin()) {
            return response()->json([
                'code' => 403,
                'message' => '无权限访问，只有管理员可以查看已归还列表'
            ], 403);
        }

        // 获取分页参数
        $page = $request->input('page', 1);
        $pageSize = $request->input('pageSize', 10);

        // 获取已归还列表
        $query = Booking::with(['user' => function($q) {
                $q->withTrashed();
            }, 'device' => function($q) {
                $q->withTrashed();
            }])
            ->where('status', Booking::STATUS_RETURNED)
            ->orderBy('updated_at', 'desc');

        $total = $query->count();
        $bookings = $query->forPage($page, $pageSize)->get();

        // 格式化返回数据
        $list = $bookings->map(function ($booking) {
            return [
                'id' => $booking->id,
                'user_name' => $booking->user->name ?? '',
                'device_name' => $booking->device->name ?? '',
                'borrow_start' => $booking->borrow_start,
                'borrow_end' => $booking->borrow_end,
                'status' => $booking->status,
                'created_at' => $booking->created_at,
                'updated_at' => $booking->updated_at,
                'user' => [
                    'id' => $booking->user->id ?? null,
                    'account' => $booking->user->account ?? '',
                    'name' => $booking->user->name ?? ''
                ],
                'device' => [
                    'id' => $booking->device->id ?? null,
                    'name' => $booking->device->name ?? '',
                ]
            ];
        });

        return response()->json([
            'code' => 200,
            'message' => '获取成功',
            'data' => [
                'total' => $total,
                'page' => (int) $page,
                'pageSize' => (int) $pageSize,
                'list' => $list
            ]
        ]);
    }

    /**
     * 获取未归还列表（管理员功能）
     * GET /api/admin/bookings/unreturned
     */
    public function getUnreturnedBookings(Request $request): JsonResponse
    {
        // JWT 认证检查
        $user = $this->getCurrentUser();
        if (!$user) {
            return response()->json([
                'code' => 401,
                'message' => '未登录或token已过期'
            ], 401);
        }

        // 检查是否是管理员
        if (!$this->isAdmin()) {
            return response()->json([
                'code' => 403,
                'message' => '无权限访问，只有管理员可以查看未归还列表'
            ], 403);
        }

        // 获取分页参数
        $page = $request->input('page', 1);
        $pageSize = $request->input('pageSize', 10);

        // 获取未归还列表（已通过但未归还）
        $query = Booking::with(['user' => function($q) {
                $q->withTrashed();
            }, 'device' => function($q) {
                $q->withTrashed();
            }])
            ->where('status', Booking::STATUS_APPROVED)
            ->orderBy('borrow_end', 'asc');

        $total = $query->count();
        $bookings = $query->forPage($page, $pageSize)->get();

        // 格式化返回数据
        $list = $bookings->map(function ($booking) {
            return [
                'id' => $booking->id,
                'user_name' => $booking->user->name ?? '',
                'device_name' => $booking->device->name ?? '',
                'borrow_start' => $booking->borrow_start,
                'borrow_end' => $booking->borrow_end,
                'status' => $booking->status,
                'created_at' => $booking->created_at,
                'updated_at' => $booking->updated_at,
                'user' => [
                    'id' => $booking->user->id ?? null,
                    'account' => $booking->user->account ?? '',
                    'name' => $booking->user->name ?? ''
                ],
                'device' => [
                    'id' => $booking->device->id ?? null,
                    'name' => $booking->device->name ?? '',
                ]
            ];
        });

        return response()->json([
            'code' => 200,
            'message' => '获取成功',
            'data' => [
                'total' => $total,
                'page' => (int) $page,
                'pageSize' => (int) $pageSize,
                'list' => $list
            ]
        ]);
    }

    /**
     * 获取拒绝归还列表（管理员功能）
     * GET /api/admin/bookings/return-rejected
     */
    public function getReturnRejectedBookings(Request $request): JsonResponse
    {
        // JWT 认证检查
        $user = $this->getCurrentUser();
        if (!$user) {
            return response()->json([
                'code' => 401,
                'message' => '未登录或token已过期'
            ], 401);
        }

        // 检查是否是管理员
        if (!$this->isAdmin()) {
            return response()->json([
                'code' => 403,
                'message' => '无权限访问，只有管理员可以查看拒绝归还列表'
            ], 403);
        }

        // 获取分页参数
        $page = $request->input('page', 1);
        $pageSize = $request->input('pageSize', 10);

        // 获取拒绝归还列表
        $query = Booking::with(['user' => function($q) {
                $q->withTrashed();
            }, 'device' => function($q) {
                $q->withTrashed();
            }])
            ->where('status', Booking::STATUS_RETURN_REJECTED)
            ->orderBy('updated_at', 'desc');

        $total = $query->count();
        $bookings = $query->forPage($page, $pageSize)->get();

        // 格式化返回数据
        $list = $bookings->map(function ($booking) {
            return [
                'id' => $booking->id,
                'user_name' => $booking->user->name ?? '',
                'device_name' => $booking->device->name ?? '',
                'borrow_start' => $booking->borrow_start,
                'borrow_end' => $booking->borrow_end,
                'status' => $booking->status,
                'reason' => $booking->reason,
                'created_at' => $booking->created_at,
                'updated_at' => $booking->updated_at,
                'user' => [
                    'id' => $booking->user->id ?? null,
                    'account' => $booking->user->account ?? '',
                    'name' => $booking->user->name ?? ''
                ],
                'device' => [
                    'id' => $booking->device->id ?? null,
                    'name' => $booking->device->name ?? '',
                ]
            ];
        });

        return response()->json([
            'code' => 200,
            'message' => '获取成功',
            'data' => [
                'total' => $total,
                'page' => (int) $page,
                'pageSize' => (int) $pageSize,
                'list' => $list
            ]
        ]);
    }

    /**
     * 获取拒绝借用申请列表（管理员功能）
     * GET /api/admin/bookings/rejected
     */
    public function getRejectedBookings(Request $request): JsonResponse
    {
        // JWT 认证检查
        $user = $this->getCurrentUser();
        if (!$user) {
            return response()->json([
                'code' => 401,
                'message' => '未登录或token已过期'
            ], 401);
        }

        // 检查是否是管理员
        if (!$this->isAdmin()) {
            return response()->json([
                'code' => 403,
                'message' => '无权限访问，只有管理员可以查看拒绝借用列表'
            ], 403);
        }

        // 获取分页参数
        $page = $request->input('page', 1);
        $pageSize = $request->input('pageSize', 10);

        // 获取拒绝借用列表
        $query = Booking::with(['user' => function($q) {
                $q->withTrashed();
            }, 'device' => function($q) {
                $q->withTrashed();
            }])
            ->where('status', Booking::STATUS_REJECTED)
            ->orderBy('updated_at', 'desc');

        $total = $query->count();
        $bookings = $query->forPage($page, $pageSize)->get();

        // 格式化返回数据
        $list = $bookings->map(function ($booking) {
            return [
                'id' => $booking->id,
                'user_name' => $booking->user->name ?? '',
                'device_name' => $booking->device->name ?? '',
                'borrow_start' => $booking->borrow_start,
                'borrow_end' => $booking->borrow_end,
                'status' => $booking->status,
                'reason' => $booking->reason,
                'reason_type' => $booking->reason_type,
                'created_at' => $booking->created_at,
                'updated_at' => $booking->updated_at,
                'user' => [
                    'id' => $booking->user->id ?? null,
                    'account' => $booking->user->account ?? '',
                    'name' => $booking->user->name ?? ''
                ],
                'device' => [
                    'id' => $booking->device->id ?? null,
                    'name' => $booking->device->name ?? '',
                ]
            ];
        });

        return response()->json([
            'code' => 200,
            'message' => '获取成功',
            'data' => [
                'total' => $total,
                'page' => (int) $page,
                'pageSize' => (int) $pageSize,
                'list' => $list
            ]
        ]);
    }

    /**
     * 新增设备（管理员功能）
     * POST /api/admin/devices
     */
    public function createDevice(Request $request): JsonResponse
    {
        // JWT 认证检查
        $user = $this->getCurrentUser();
        if (!$user) {
            return response()->json([
                'code' => 401,
                'message' => '未登录或token已过期'
            ], 401);
        }

        // 检查是否是管理员
        if (!$this->isAdmin()) {
            return response()->json([
                'code' => 403,
                'message' => '无权限访问，只有管理员可以新增设备'
            ], 403);
        }

        // 验证参数
        $request->validate([
            'name' => 'required|string|max:100',
            'category' => 'required|string|max:50',
            'description' => 'nullable|string',
            'total_qty' => 'required|integer|min:1',
            'available_qty' => 'required|integer|min:0',
            'status' => 'required|in:available,maintenance',
        ]);

        // 检查分类是否存在（支持通过 name 或 code 查找）
        $categoryInput = $request->input('category');
        $category = \App\Models\Category::where('name', $categoryInput)
            ->orWhere('code', $categoryInput)
            ->first();
        if (!$category) {
            return response()->json([
                'code' => 400,
                'message' => '设备分类不存在，请先创建分类或使用现有分类',
                'data' => null
            ], 400);
        }

        // 检查是否已存在相同名称和分类的设备
        $existingDevice = Device::where('name', $request->input('name'))
            ->where('category', $category->code)
            ->first();

        if ($existingDevice) {
            return response()->json([
                'code' => 400,
                'message' => '该设备已存在，请勿重复添加',
                'data' => null
            ], 400);
        }

        // 创建设备（存储分类 code）
        $device = Device::create([
            'name' => $request->input('name'),
            'category' => $category->code,
            'description' => $request->input('description'),
            'total_qty' => $request->input('total_qty'),
            'available_qty' => $request->input('available_qty'),
            'status' => $request->input('status'),
        ]);

        return response()->json([
            'code' => 200,
            'message' => '设备新增成功',
            'data' => $device
        ]);
    }

    /**
     * 编辑设备（管理员功能）
     * PUT /api/admin/devices/{id}
     */
    public function updateDevice(Request $request, $id): JsonResponse
    {
        // JWT 认证检查
        $user = $this->getCurrentUser();
        if (!$user) {
            return response()->json([
                'code' => 401,
                'message' => '未登录或token已过期'
            ], 401);
        }

        // 检查是否是管理员
        if (!$this->isAdmin()) {
            return response()->json([
                'code' => 403,
                'message' => '无权限访问，只有管理员可以编辑设备'
            ], 403);
        }

        // 查找设备
        $device = Device::find($id);
        if (!$device) {
            return response()->json([
                'code' => 404,
                'message' => '设备不存在'
            ], 404);
        }

        // 验证参数（均为可选）
        $request->validate([
            'name' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'total_qty' => 'nullable|integer|min:1',
            'available_qty' => 'nullable|integer|min:0',
            'status' => 'nullable|in:available,maintenance',
        ]);

        // 如果更新了分类，检查分类是否存在（支持通过 name 或 code 查找）
        $category = null;
        if ($request->has('category')) {
            $categoryInput = $request->input('category');
            $category = \App\Models\Category::where('name', $categoryInput)
                ->orWhere('code', $categoryInput)
                ->first();
            if (!$category) {
                return response()->json([
                    'code' => 400,
                    'message' => '设备分类不存在，请先创建分类或使用现有分类',
                    'data' => null
                ], 400);
            }
        }

        // 更新设备（只更新传了的字段）
        if ($request->has('name')) {
            $device->name = $request->input('name');
        }
        if ($request->has('category') && $category) {
            $device->category = $category->code;
        }
        if ($request->has('description')) {
            $device->description = $request->input('description');
        }
        if ($request->has('total_qty')) {
            $device->total_qty = $request->input('total_qty');
        }
        if ($request->has('available_qty')) {
            $device->available_qty = $request->input('available_qty');
        }
        if ($request->has('status')) {
            $device->status = $request->input('status');
        }

        $device->save();

        return response()->json([
            'code' => 200,
            'message' => '设备更新成功',
            'data' => $device
        ]);
    }

    /**
     * 下架设备（软删除，管理员功能）
     * DELETE /api/admin/devices/{id}
     */
    public function deleteDevice(Request $request, $id): JsonResponse
    {
        // JWT 认证检查
        $user = $this->getCurrentUser();
        if (!$user) {
            return response()->json([
                'code' => 401,
                'message' => '未登录或token已过期'
            ], 401);
        }

        // 检查是否是管理员
        if (!$this->isAdmin()) {
            return response()->json([
                'code' => 403,
                'message' => '无权限访问，只有管理员可以下架设备'
            ], 403);
        }

        // 查找设备
        $device = Device::find($id);
        if (!$device) {
            return response()->json([
                'code' => 404,
                'message' => '设备不存在'
            ], 404);
        }

        // 软删除设备
        $device->delete();

        return response()->json([
            'code' => 200,
            'message' => '设备已下架',
            'data' => [
                'id' => $device->id
            ]
        ]);
    }

    // =======================================
    // 设备分类管理模块
    // =======================================

    /**
     * 获取分类列表（管理员和学生均可访问）
     * GET /api/categories
     */
    public function getCategories(Request $request): JsonResponse
    {
        $query = Category::query();

        // 非管理员只能看到启用的分类
        if (!$this->isAdmin()) {
            $query->where('is_active', true);
        }

        // 搜索
        if ($request->has('keyword')) {
            $keyword = $request->input('keyword');
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                  ->orWhere('code', 'like', "%{$keyword}%");
            });
        }

        // 状态筛选
        if ($request->has('is_active')) {
            $query->where('is_active', $request->input('is_active'));
        }

        // 排序
        $query->ordered();

        // 分页
        $page = $request->input('page', 1);
        $pageSize = $request->input('pageSize', 10);

        $categories = $query->paginate($pageSize, ['*'], 'page', $page);

        // 获取每个分类的基本信息（简化版，不包含设备列表）
        $list = $categories->map(function ($category) {
            // 只统计设备数量
            $totalDevices = Device::where('category', $category->code)->count();

            return [
                'id' => $category->id,
                'name' => $category->name,
                'code' => $category->code,
                'description' => $category->description,
                'sort_order' => $category->sort_order,
                'is_active' => $category->is_active,
                'device_count' => $totalDevices,  // 仅返回设备数量
                'created_at' => $category->created_at,
                'updated_at' => $category->updated_at,
            ];
        });

        return response()->json([
            'code' => 200,
            'message' => '获取成功',
            'data' => [
                'total' => $categories->total(),
                'page' => $categories->currentPage(),
                'pageSize' => $categories->perPage(),
                'list' => $list
            ]
        ]);
    }

    /**
     * 获取分类详情
     * GET /api/categories/{id}
     */
    public function getCategory($id): JsonResponse
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'code' => 404,
                'message' => '分类不存在'
            ], 404);
        }

        // 获取该分类下的设备
        $devices = Device::where('category', $category->code)->get();

        // 格式化设备数据，添加实时库存计算
        $deviceList = $devices->map(function ($device) {
            // 实时计算库存：总库存 - 已批准但未归还的借用数量
            $borrowedCount = \App\Models\Booking::where('device_id', $device->id)
                ->whereIn('status', ['approved', 'pending'])
                ->count();
            $realAvailableQty = $device->total_qty - $borrowedCount;

            return [
                'id' => $device->id,
                'name' => $device->name,
                'status' => $device->status,
                'total_qty' => $device->total_qty,
                'available_qty' => $device->available_qty,
                'real_available_qty' => $realAvailableQty,  // 实时计算的可用数量
                'borrowed_count' => $borrowedCount,         // 已借出数量
            ];
        });

        return response()->json([
            'code' => 200,
            'message' => '获取成功',
            'data' => [
                'id' => $category->id,
                'name' => $category->name,
                'code' => $category->code,
                'description' => $category->description,
                'sort_order' => $category->sort_order,//排序顺序
                'is_active' => $category->is_active,//是否启用
                'device_count' => $devices->count(),//设备数量
                'devices' => $deviceList,//设备列表（包含实时库存）
                'created_at' => $category->created_at,
                'updated_at' => $category->updated_at,
            ]
        ]);
    }

    /**
     * 创建分类（管理员）
     * POST /api/admin/categories
     */
    public function createCategory(Request $request): JsonResponse
    {
        // 权限检查
        if (!$this->isAdmin()) {
            return response()->json([
                'code' => 403,
                'message' => '无权限，只有管理员可以创建分类'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:50',
            'code' => 'required|string|max:50|unique:categories,code',//分类编码
            'description' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',//是否启用
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'message' => '参数验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $category = Category::create([
            'name' => $request->input('name'),
            'code' => $request->input('code'),
            'description' => $request->input('description'),
            'sort_order' => $request->input('sort_order', 0),
            'is_active' => $request->input('is_active', true),
        ]);

        return response()->json([
            'code' => 200,
            'message' => '分类创建成功',
            'data' => $category
        ]);
    }

    /**
     * 更新分类（管理员）
     * PUT /api/admin/categories/{id}
     */
    public function updateCategory(Request $request, $id): JsonResponse
    {
        // 权限检查
        if (!$this->isAdmin()) {
            return response()->json([
                'code' => 403,
                'message' => '无权限，只有管理员可以更新分类'
            ], 403);
        }

        $category = Category::find($id);
        if (!$category) {
            return response()->json([
                'code' => 404,
                'message' => '分类不存在'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:50',
            'code' => 'nullable|string|max:50|unique:categories,code,' . $id,
            'description' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'message' => '参数验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        // 如果修改了code，需要同步更新devices表中的category字段
        $oldCode = $category->code;
        $newCode = $request->input('code');

        $category->fill($request->only(['name', 'code', 'description', 'sort_order', 'is_active']));
        $category->save();

        // 同步更新设备表中的分类编码
        if ($newCode && $newCode !== $oldCode) {
            Device::where('category', $oldCode)->update(['category' => $newCode]);
        }

        return response()->json([
            'code' => 200,
            'message' => '分类更新成功',
            'data' => $category
        ]);
    }

    /**
     * 删除分类（管理员，软删除）
     * DELETE /api/admin/categories/{id}
     */
    public function deleteCategory($id): JsonResponse
    {
        // 权限检查
        if (!$this->isAdmin()) {
            return response()->json([
                'code' => 403,
                'message' => '无权限，只有管理员可以删除分类'
            ], 403);
        }

        $category = Category::find($id);
        if (!$category) {
            return response()->json([
                'code' => 404,
                'message' => '分类不存在'
            ], 404);
        }

        // 检查该分类下是否有设备
        $deviceCount = Device::where('category', $category->code)->count();
        if ($deviceCount > 0) {
            return response()->json([
                'code' => 400,
                'message' => '该分类下存在设备，无法删除，请先移除或转移设备'
            ], 400);
        }

        $category->delete();

        return response()->json([
            'code' => 200,
            'message' => '分类删除成功'
        ]);
    }

    /**
     * 切换分类启用/禁用状态
     * PATCH /api/admin/categories/{id}/toggle-status
     */
    public function toggleCategoryStatus($id): JsonResponse
    {
        // 权限检查
        if (!$this->isAdmin()) {
            return response()->json([
                'code' => 403,
                'message' => '无权限，只有管理员可以修改分类状态'
            ], 403);
        }

        $category = Category::find($id);
        if (!$category) {
            return response()->json([
                'code' => 404,
                'message' => '分类不存在'
            ], 404);
        }

        // 切换状态
        $category->is_active = !$category->is_active;
        $category->save();

        $statusText = $category->is_active ? '启用' : '禁用';

        return response()->json([
            'code' => 200,
            'message' => "分类已{$statusText}",
            'data' => [
                'id' => $category->id,
                'name' => $category->name,
                'is_active' => $category->is_active,
                'status_text' => $statusText
            ]
        ]);
    }

    /**
     * 获取分类统计信息
     * GET /api/categories/statistics
     */
    public function getCategoryStatistics(): JsonResponse
    {
        $categories = Category::active()->ordered()->get();

        $stats = $categories->map(function ($category) {
            $devices = Device::where('category', $category->code);
            return [
                'id' => $category->id,
                'name' => $category->name,
                'code' => $category->code,
                'device_count' => $devices->count(),
                'total_qty' => $devices->sum('total_qty'),
                'available_qty' => $devices->sum('available_qty'),
            ];
        });

        return response()->json([
            'code' => 200,
            'message' => '获取成功',
            'data' => [
                'categories' => $stats,
                'total_categories' => $categories->count(),
                'total_devices' => Device::count(),
            ]
        ]);
    }
    /**
     * 注销用户账号（管理员功能）
     * DELETE /api/admin/users/{id}
     */
    public function deactivateUser($id): JsonResponse
    {
        // JWT 认证检查
        $user = $this->getCurrentUser();
        if (!$user) {
            return response()->json([
                'code' => 401,
                'message' => '未登录或token已过期'
            ], 401);
        }

        // 检查是否是管理员
        if (!$this->isAdmin()) {
            return response()->json([
                'code' => 403,
                'message' => '无权限访问，只有管理员可以注销用户账号'
            ], 403);
        }

        // 查找用户
        $targetUser = \App\Models\User::find($id);
        if (!$targetUser) {
            return response()->json([
                'code' => 404,
                'message' => '用户不存在'
            ], 404);
        }

        // 防止管理员注销自己
        if ($targetUser->id === $user->id) {
            return response()->json([
                'code' => 400,
                'message' => '不能注销自己的账号'
            ], 400);
        }

        // 删除用户（软删除）
        $targetUser->delete();

        return response()->json([
            'code' => 200,
            'message' => '用户账号已注销'
        ]);
    }
}
