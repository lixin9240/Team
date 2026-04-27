<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Booking;
use App\Models\User;
use App\Services\EmailVerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class WLJController extends \Illuminate\Routing\Controller
{
    protected EmailVerificationService $emailVerificationService;

    public function __construct(EmailVerificationService $emailVerificationService)
    {
        $this->emailVerificationService = $emailVerificationService;
    }
    // 获取设备列表（分页+筛选）- 使用实时库存视图
    public function getDevices(Request $request)
    {
        // 从实时库存视图查询
        $query = \Illuminate\Support\Facades\DB::table('device_realtime_stock');

        // 筛选条件
        if ($request->has('name')) {
            $keyword = $request->input('name');
            $query->where(function ($q) use ($keyword) {
                $q->where('device_name', 'like', '%' . $keyword . '%');
            });
        }

        // 支持按分类编码或分类名称筛选
        if ($request->has('category')) {
            $categoryValue = $request->input('category');
            // 先尝试按code匹配，如果没有再尝试按name匹配
            $categoryCode = \App\Models\Category::where('code', $categoryValue)
                ->orWhere('name', $categoryValue)
                ->value('code');
            $query->where('category', $categoryCode ?: $categoryValue);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // 分页
        $page = $request->input('page', 1);
        $pageSize = $request->input('pageSize', 10);

        $devices = $query->paginate($pageSize, ['*'], 'page', $page);

        // 获取分类信息并格式化数据（只返回必要字段）
        $categories = \App\Models\Category::pluck('name', 'code')->toArray();
        
        $list = collect($devices->items())->map(function ($device) use ($categories) {
            // 使用视图中的实时库存
            $realtimeQty = (int) $device->realtime_available_qty;

            // 根据实时库存判断状态：库存为0时显示无空闲
            $status = $realtimeQty <= 0 ? 'unavailable' : $device->status;

            return [
                'id' => $device->id,  // 设备ID，用于前端提交借用申请
                'name' => $device->device_name,  // 视图中字段名为 device_name
                'category' => $categories[$device->category] ?? $device->category,
                'status' => $status,  // 根据实时库存计算的状态
                'available_qty' => $realtimeQty,  // 实时库存（可借数量）
            ];
        });

        return response()->json([
            'code' => 200,
            'message' => '获取成功',
            'data' => [
                'total' => $devices->total(),
                'page' => $devices->currentPage(),
                'pageSize' => $devices->perPage(),
                'list' => $list
            ]
        ]);
    }

    // 获取设备详情
    public function getDevice($id)
    {
        $device = Device::find($id);

        if (!$device) {
            return response()->json([
                'code' => 404,
                'message' => '设备不存在',
                'data' => null
            ]);
        }

        // 获取分类信息
        $category = \App\Models\Category::where('code', $device->category)->first();
        
        // 实时计算可用库存
        // 1. 已借出 = pending + approved + returning
        $borrowedCount = Booking::where('device_id', $device->id)
            ->whereIn('status', ['approved', 'pending', 'returning'])
            ->count();
        
        // 2. 损坏/不可用 = 被拒绝且 reason_type = device_unavailable
        $brokenCount = Booking::where('device_id', $device->id)
            ->where('status', 'rejected')
            ->where('reason_type', 'device_unavailable')
            ->count();
        
        // 3. 可用数量 = 总数量 - 已借出 - 损坏/不可用
        $realAvailableQty = $device->total_qty - $borrowedCount - $brokenCount;
        
        // 获取相关设备（同分类）
        $relatedDevices = Device::where('category', $device->category)
            ->where('id', '!=', $device->id)
            ->where('status', 'available')
            ->limit(5)
            ->get(['id', 'name', 'total_qty'])
            ->map(function ($relatedDevice) {
                // 实时计算相关设备的可用库存（占用库存的状态：pending + approved + returning）
                $relatedBorrowedCount = Booking::where('device_id', $relatedDevice->id)
                    ->whereIn('status', ['approved', 'pending', 'returning'])
                    ->count();
                $relatedBrokenCount = Booking::where('device_id', $relatedDevice->id)
                    ->where('status', 'rejected')
                    ->where('reason_type', 'device_unavailable')
                    ->count();
                $relatedDevice->available_qty = $relatedDevice->total_qty - $relatedBorrowedCount - $relatedBrokenCount;
                return $relatedDevice;
            });

        return response()->json([
            'code' => 200,
            'message' => '获取成功',
            'data' => [
                'id' => $device->id,
                'name' => $device->name,
                'category' => $device->category,
                'category_info' => $category ? [
                    'id' => $category->id,
                    'name' => $category->name,
                    'code' => $category->code,
                    'description' => $category->description,
                ] : null,
                'description' => $device->description,
                'total_qty' => $device->total_qty,
                'available_qty' => $realAvailableQty,  // 实时计算的可用数量
                'status' => $device->status,
                'related_devices' => $relatedDevices,
                'created_at' => $device->created_at,
                'updated_at' => $device->updated_at,
            ]
        ]);
    }

    // 发起借用申请
    public function createBooking(Request $request)
    {
        $request->validate([
            'device_id' => 'required|exists:devices,id',
            'borrow_start' => 'required|date',
            'borrow_end' => 'required|date|after_or_equal:borrow_start',
            'purpose' => 'nullable|string'
        ]);

        $device = Device::find($request->device_id);

        // 检查设备是否存在（未下架）
        if (!$device) {
            return response()->json([
                'code' => 400,
                'message' => '该设备已下架，无法借用',
                'data' => null
            ], 400);
        }

        // 实时计算可用库存（占用库存的状态：pending + approved + returning）
        $borrowedCount = Booking::where('device_id', $device->id)
            ->whereIn('status', ['approved', 'pending', 'returning'])
            ->count();
        $brokenCount = Booking::where('device_id', $device->id)
            ->where('status', 'rejected')
            ->where('reason_type', 'device_unavailable')
            ->count();
        $availableQty = $device->total_qty - $borrowedCount - $brokenCount;

        // 检查设备是否有可用库存
        if ($availableQty <= 0) {
            return response()->json([
                'code' => 400,
                'message' => '该设备当前无可用库存，请选择其他时间或设备',
                'data' => null
            ]);
        }

        // 创建借用申请（保存设备名称冗余字段）
        $booking = Booking::create([
            'user_id' => Auth::id(),
            'device_id' => $request->device_id,
            'device_name' => $device->name,  // 冗余存储设备名称
            'borrow_start' => $request->borrow_start,
            'borrow_end' => $request->borrow_end,
            'purpose' => $request->purpose,
            'status' => 'pending'
        ]);

        return response()->json([
            'code' => 200,
            'message' => '申请已提交，等待审核',
            'data' => $booking
        ]);
    }

    // 获取个人借用记录
    public function getMyBookings(Request $request)
    {
        $query = Booking::where('user_id', Auth::id())->with('device:id,name,category');

        // 状态筛选
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // 分页
        $page = $request->input('page', 1);
        $pageSize = $request->input('pageSize', 10);

        $bookings = $query->paginate($pageSize, ['*'], 'page', $page);

        // 格式化数据
        $list = $bookings->map(function ($booking) {
            // 设备可能已被删除（软删除），需要做空值检查
            $device = $booking->device;
            $category = null;

            if ($device) {
                $category = \App\Models\Category::where('code', $device->category)->first();
            }

            return [
                'id' => $booking->id,
                'device_name' => $device ? $device->name : $booking->device_name, // 使用冗余字段或显示已删除
                'borrow_start' => $booking->borrow_start,
                'borrow_end' => $booking->borrow_end,
                'purpose' => $booking->purpose,
                'status' => $booking->status,
                'reason' => $booking->reason,           // 拒绝原因
                'reason_type' => $booking->reason_type, // 拒绝原因类型
                'created_at' => $booking->created_at,
                'device' => $device ? [
                    'id' => $device->id,
                    'name' => $device->name,
                    'category_code' => $device->category,
                    'category' => $category ? [
                        'id' => $category->id,
                        'name' => $category->name,
                        'code' => $category->code,
                        'description' => $category->description,
                    ] : null,
                ] : null, // 设备已删除时返回 null
            ];
        });

        return response()->json([
            'code' => 200,
            'message' => '获取成功',
            'data' => [
                'total' => $bookings->total(),
                'page' => $bookings->currentPage(),
                'pageSize' => $bookings->perPage(),
                'list' => $list
            ]
        ]);
    }

    // 申请归还设备（用户发起，需要管理员审核）
    public function returnBooking($id)
    {
        $booking = Booking::where('id', $id)->where('user_id', Auth::id())->first();

        if (!$booking) {
            return response()->json([
                'code' => 404,
                'message' => '借用记录不存在',
                'data' => null
            ]);
        }

        if ($booking->status != 'approved') {
            return response()->json([
                'code' => 400,
                'message' => '仅已通过的申请可发起归还',
                'data' => null
            ]);
        }

        // 更新状态为申请归还（待管理员审核）
        $booking->update(['status' => 'returning']);

        return response()->json([
            'code' => 200,
            'message' => '归还申请已提交，等待管理员审核',
            'data' => [
                'id' => $booking->id,
                'status' => $booking->status,
                'updated_at' => $booking->updated_at
            ]
        ]);
    }

    // 注销账号（硬删除）
    public function deleteAccount(Request $request)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'code' => 401,
                'message' => '未登录',
                'data' => null
            ]);
        }

        // 验证请求参数
        try {
            $validated = $request->validate([
                'account' => 'required|string|min:4|max:20',
                'email' => 'required|email',
                'email_code' => 'required|string|size:6',
            ], [
                'account.required' => '账号不能为空',
                'account.min' => '账号至少4个字符',
                'account.max' => '账号最多20个字符',
                'email.required' => '邮箱不能为空',
                'email.email' => '邮箱格式不正确',
                'email_code.required' => '邮箱验证码不能为空',
                'email_code.size' => '邮箱验证码必须是6位',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'code' => 422,
                'message' => '验证失败',
                'data' => $e->errors()
            ], 422);
        }

        // 验证账号是否匹配当前用户
        if ($validated['account'] !== $user->account) {
            return response()->json([
                'code' => 400,
                'message' => '账号与当前登录账号不匹配',
                'data' => null
            ]);
        }

        // 验证邮箱是否匹配当前用户
        if ($validated['email'] !== $user->email) {
            return response()->json([
                'code' => 400,
                'message' => '邮箱与当前账号不匹配',
                'data' => null
            ]);
        }

        // 验证邮箱验证码
        $isValid = $this->emailVerificationService->verifyCode(
            $validated['email'],
            $validated['email_code'],
            'delete_account'
        );

        if (!$isValid) {
            return response()->json([
                'code' => 400,
                'message' => '邮箱验证码无效或已过期',
                'data' => null
            ]);
        }

        $user->forceDelete();

        return response()->json([
            'code' => 200,
            'message' => '账号已注销',
            'data' => null
        ]);
    }
}
