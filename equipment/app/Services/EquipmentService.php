<?php

namespace App\Services;

use App\Enums\ResponseCode;
use App\Exceptions\BusinessException;
use App\Models\Booking;
use App\Models\Category;
use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EquipmentService
{
    /**
     * 获取设备列表（分页+筛选，实时库存视图）
     */
    public function getDevices(Request $request): array
    {
        $query = DB::table('device_realtime_stock');

        if ($request->has('name')) {
            $keyword = $request->input('name');
            $query->where('device_name', 'like', '%' . $keyword . '%');
        }

        if ($request->has('category')) {
            $categoryValue = $request->input('category');
            $categoryCode = Category::where('code', $categoryValue)
                ->orWhere('name', $categoryValue)
                ->value('code');
            $query->where('category', $categoryCode ?: $categoryValue);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $page = $request->input('page', 1);
        $pageSize = $request->input('pageSize', 10);

        $devices = $query->paginate($pageSize, ['*'], 'page', $page);
        $categories = Category::pluck('name', 'code')->toArray();

        $list = collect($devices->items())->map(function ($device) use ($categories) {
            $realtimeQty = (int) $device->realtime_available_qty;
            return [
                'id' => $device->id,
                'name' => $device->device_name,
                'category' => $categories[$device->category] ?? $device->category,
                'status' => $realtimeQty <= 0 ? 'unavailable' : $device->status,
                'available_qty' => $realtimeQty,
            ];
        });

        return [
            'total' => $devices->total(),
            'page' => $devices->currentPage(),
            'pageSize' => $devices->perPage(),
            'list' => $list,
        ];
    }

    /**
     * 获取设备详情（含实时库存计算）
     */
    public function getDevice(int $id): array
    {
        $device = Device::find($id);
        if (!$device) {
            throw new BusinessException('设备不存在', ResponseCode::DATA_NOT_FOUND);
        }

        $category = Category::where('code', $device->category)->first();

        $borrowedCount = Booking::where('device_id', $id)
            ->whereIn('status', ['approved', 'pending', 'returning'])
            ->count();
        $brokenCount = Booking::where('device_id', $id)
            ->where('status', 'rejected')
            ->where('reason_type', 'device_unavailable')
            ->count();
        $realAvailableQty = $device->total_qty - $borrowedCount - $brokenCount;

        $relatedDevices = Device::where('category', $device->category)
            ->where('id', '!=', $id)
            ->where('status', 'available')
            ->limit(5)
            ->get(['id', 'name', 'total_qty'])
            ->map(function ($rd) {
                $rdBorrowed = Booking::where('device_id', $rd->id)
                    ->whereIn('status', ['approved', 'pending', 'returning'])
                    ->count();
                $rdBroken = Booking::where('device_id', $rd->id)
                    ->where('status', 'rejected')
                    ->where('reason_type', 'device_unavailable')
                    ->count();
                $rd->available_qty = $rd->total_qty - $rdBorrowed - $rdBroken;
                return $rd;
            });

        return [
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
            'available_qty' => $realAvailableQty,
            'status' => $device->status,
            'related_devices' => $relatedDevices,
            'created_at' => $device->created_at,
            'updated_at' => $device->updated_at,
        ];
    }

    /**
     * 发起借用申请
     */
    public function createBooking(array $data, int $userId): Booking
    {
        $device = Device::find($data['device_id']);
        if (!$device) {
            throw new BusinessException('该设备已下架，无法借用', ResponseCode::DATA_NOT_FOUND);
        }

        $borrowedCount = Booking::where('device_id', $device->id)
            ->whereIn('status', ['approved', 'pending', 'returning'])
            ->count();
        $brokenCount = Booking::where('device_id', $device->id)
            ->where('status', 'rejected')
            ->where('reason_type', 'device_unavailable')
            ->count();
        $availableQty = $device->total_qty - $borrowedCount - $brokenCount;

        if ($availableQty <= 0) {
            throw new BusinessException('该设备当前无可用库存，请选择其他时间或设备', ResponseCode::STOCK_INSUFFICIENT);
        }

        $booking = Booking::create([
            'user_id' => $userId,
            'device_id' => $data['device_id'],
            'device_name' => $device->name,
            'borrow_start' => $data['borrow_start'],
            'borrow_end' => $data['borrow_end'],
            'purpose' => $data['purpose'],
            'status' => 'pending',
        ]);

        Log::channel('business')->info('借用申请已提交', [
            'booking_id' => $booking->id,
            'device_id' => $device->id,
            'device_name' => $device->name,
            'user_id' => $userId,
        ]);

        return $booking;
    }

    /**
     * 获取个人借用记录（分页+状态筛选）
     */
    public function getMyBookings(Request $request): array
    {
        $query = Booking::where('user_id', Auth::id())->with('device:id,name,category');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $page = $request->input('page', 1);
        $pageSize = $request->input('pageSize', 10);
        $bookings = $query->paginate($pageSize, ['*'], 'page', $page);

        $list = $bookings->map(function ($booking) {
            $device = $booking->device;
            $category = null;
            if ($device) {
                $category = Category::where('code', $device->category)->first();
            }

            return [
                'id' => $booking->id,
                'device_name' => $device ? $device->name : $booking->device_name,
                'borrow_start' => $booking->borrow_start,
                'borrow_end' => $booking->borrow_end,
                'purpose' => $booking->purpose,
                'status' => $booking->status,
                'reason' => $booking->reason,
                'reason_type' => $booking->reason_type,
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
                ] : null,
            ];
        });

        return [
            'total' => $bookings->total(),
            'page' => $bookings->currentPage(),
            'pageSize' => $bookings->perPage(),
            'list' => $list,
        ];
    }

    /**
     * 申请归还设备
     */
    public function returnBooking(int $id, int $userId): array
    {
        $booking = Booking::where('id', $id)->where('user_id', $userId)->first();
        if (!$booking) {
            throw new BusinessException('借用记录不存在', ResponseCode::DATA_NOT_FOUND);
        }

        if ($booking->status != 'approved') {
            throw new BusinessException('仅已通过的申请可发起归还', ResponseCode::STATUS_NOT_ALLOWED);
        }

        $booking->update(['status' => 'returning']);

        Log::channel('business')->info('归还申请已提交', [
            'booking_id' => $booking->id,
            'device_id' => $booking->device_id,
            'user_id' => $userId,
        ]);

        return [
            'id' => $booking->id,
            'status' => $booking->status,
            'updated_at' => $booking->updated_at,
        ];
    }
}
