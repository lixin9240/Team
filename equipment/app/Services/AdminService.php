<?php

namespace App\Services;

use App\Enums\ResponseCode;
use App\Exceptions\BusinessException;
use App\Models\Booking;
use App\Models\Category;
use App\Models\Device;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AdminService
{
    /**
     * 确保管理员权限
     */
    public function requireAdmin(): User
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (!$user) {
            throw new BusinessException('未登录', ResponseCode::UNAUTHORIZED);
        }
        if (!$user->isAdmin()) {
            throw new BusinessException('无权限访问', ResponseCode::FORBIDDEN);
        }
        return $user;
    }

    // ========== 借用审核 ==========

    public function getPendingBookings(int $page, int $pageSize): array
    {
        $query = Booking::with(['user', 'device'])
            ->where('status', Booking::STATUS_PENDING)
            ->orderBy('created_at', 'desc');

        $total = $query->count();
        $bookings = $query->forPage($page, $pageSize)->get();

        $list = $bookings->map(fn ($b) => $this->formatBookingWithCategory($b));

        return compact('total', 'page', 'pageSize', 'list');
    }

    public function getReturningBookings(int $page, int $pageSize): array
    {
        $query = Booking::with([
                'user' => fn ($q) => $q->withTrashed(),
                'device' => fn ($q) => $q->withTrashed(),
            ])
            ->where('status', Booking::STATUS_RETURNING)
            ->orderBy('updated_at', 'desc');

        $total = $query->count();
        $bookings = $query->forPage($page, $pageSize)->get();

        $list = $bookings->map(fn ($b) => $this->formatBookingWithCategory($b, true));

        return compact('total', 'page', 'pageSize', 'list');
    }

    public function getReturnedBookings(int $page, int $pageSize): array
    {
        $query = Booking::with([
                'user' => fn ($q) => $q->withTrashed(),
                'device' => fn ($q) => $q->withTrashed(),
            ])
            ->returned()
            ->orderBy('updated_at', 'desc');

        $total = $query->count();
        $bookings = $query->forPage($page, $pageSize)->get();

        $list = $bookings->map(fn ($b) => $this->formatBookingSimple($b));

        return compact('total', 'page', 'pageSize', 'list');
    }

    public function getUnreturnedBookings(int $page, int $pageSize): array
    {
        $query = Booking::with([
                'user' => fn ($q) => $q->withTrashed(),
                'device' => fn ($q) => $q->withTrashed(),
            ])
            ->where('status', Booking::STATUS_APPROVED)
            ->orderBy('borrow_end', 'asc');

        $total = $query->count();
        $bookings = $query->forPage($page, $pageSize)->get();

        $list = $bookings->map(fn ($b) => $this->formatBookingSimple($b));

        return compact('total', 'page', 'pageSize', 'list');
    }

    public function getReturnRejectedBookings(int $page, int $pageSize): array
    {
        $query = Booking::with([
                'user' => fn ($q) => $q->withTrashed(),
                'device' => fn ($q) => $q->withTrashed(),
            ])
            ->where('status', Booking::STATUS_RETURN_REJECTED)
            ->orderBy('updated_at', 'desc');

        $total = $query->count();
        $bookings = $query->forPage($page, $pageSize)->get();

        $list = $bookings->map(function ($b) {
            $data = $this->formatBookingSimple($b);
            $data['reason'] = $b->reason;
            return $data;
        });

        return compact('total', 'page', 'pageSize', 'list');
    }

    public function getRejectedBookings(int $page, int $pageSize): array
    {
        $query = Booking::with([
                'user' => fn ($q) => $q->withTrashed(),
                'device' => fn ($q) => $q->withTrashed(),
            ])
            ->where('status', Booking::STATUS_REJECTED)
            ->orderBy('updated_at', 'desc');

        $total = $query->count();
        $bookings = $query->forPage($page, $pageSize)->get();

        $list = $bookings->map(function ($b) {
            $data = $this->formatBookingSimple($b);
            $data['reason'] = $b->reason;
            $data['reason_type'] = $b->reason_type;
            return $data;
        });

        return compact('total', 'page', 'pageSize', 'list');
    }

    public function auditBooking(int $id, string $action, ?string $reason, ?string $reasonType): array
    {
        $booking = Booking::find($id);
        if (!$booking) {
            throw new BusinessException('申请记录不存在', ResponseCode::DATA_NOT_FOUND);
        }

        if ($booking->status !== Booking::STATUS_PENDING) {
            throw new BusinessException('该申请已处理，无法重复审核', ResponseCode::STATUS_NOT_ALLOWED);
        }

        $device = Device::find($booking->device_id);
        $deviceName = $device ? $device->name : '未知设备';
        $deviceCategory = $device ? $device->category : null;

        if ($action === 'approve') {
            $booking->status = Booking::STATUS_APPROVED;
            $booking->save();

            Log::channel('business')->info('借用申请通过', [
                'booking_id' => $booking->id,
                'device_id' => $booking->device_id,
                'user_id' => $booking->user_id,
                'auditor_id' => Auth::id(),
            ]);

            return [
                'id' => $booking->id,
                'device_id' => $booking->device_id,
                'device_name' => $deviceName,
                'device_category' => $deviceCategory,
                'status' => $booking->status,
                'borrow_start' => $booking->borrow_start,
                'borrow_end' => $booking->borrow_end,
                'purpose' => $booking->purpose,
            ];
        }

        $reasonType = $reasonType ?? 'other';
        $booking->status = Booking::STATUS_REJECTED;
        $booking->reason = $reason;
        $booking->reason_type = $reasonType;
        $booking->save();

        Log::channel('business')->info('借用申请拒绝', [
            'booking_id' => $booking->id,
            'reason' => $reason,
            'reason_type' => $reasonType,
            'auditor_id' => Auth::id(),
        ]);

        return [
            'id' => $booking->id,
            'device_id' => $booking->device_id,
            'device_name' => $deviceName,
            'device_category' => $deviceCategory,
            'status' => $booking->status,
            'reason' => $booking->reason,
            'reason_type' => $reasonType,
            'device_affected' => $reasonType === 'device_unavailable',
        ];
    }

    public function auditReturnBooking(int $id, string $action, ?string $reason): array
    {
        $booking = Booking::find($id);
        if (!$booking) {
            throw new BusinessException('归还记录不存在', ResponseCode::DATA_NOT_FOUND);
        }

        if ($booking->status !== Booking::STATUS_RETURNING) {
            throw new BusinessException('该记录不是申请归还状态，无法审核', ResponseCode::STATUS_NOT_ALLOWED);
        }

        if ($action === 'approve') {
            $booking->status = Booking::STATUS_RETURNED;
            $booking->save();

            Log::channel('business')->info('归还申请通过', [
                'booking_id' => $booking->id,
                'device_id' => $booking->device_id,
                'auditor_id' => Auth::id(),
            ]);

            return $booking->toArray();
        }

        $booking->status = Booking::STATUS_RETURN_REJECTED;
        $booking->reason = $reason;
        $booking->save();

        Log::channel('business')->info('归还申请拒绝', [
            'booking_id' => $booking->id,
            'reason' => $reason,
            'auditor_id' => Auth::id(),
        ]);

        return [
            'id' => $booking->id,
            'device_name' => $booking->device_name,
            'borrow_start' => $booking->borrow_start,
            'borrow_end' => $booking->borrow_end,
            'status' => $booking->status,
            'reason' => $booking->reason,
            'updated_at' => $booking->updated_at,
        ];
    }

    // ========== 设备管理 ==========

    public function createDevice(array $data): Device
    {
        $category = Category::where('name', $data['category'])
            ->orWhere('code', $data['category'])
            ->first();
        if (!$category) {
            throw new BusinessException('设备分类不存在，请先创建分类或使用现有分类', ResponseCode::DATA_NOT_FOUND);
        }

        $existing = Device::where('name', $data['name'])
            ->where('category', $category->code)
            ->first();
        if ($existing) {
            throw new BusinessException('该设备已存在，请勿重复添加', ResponseCode::DATA_DUPLICATE);
        }

        $device = Device::create([
            'name' => $data['name'],
            'category' => $category->code,
            'description' => $data['description'],
            'total_qty' => $data['total_qty'],
            'available_qty' => $data['available_qty'],
            'status' => $data['status'],
        ]);

        Log::channel('business')->info('设备新增', [
            'device_id' => $device->id,
            'device_name' => $device->name,
            'operator_id' => Auth::id(),
        ]);

        return $device;
    }

    public function updateDevice(int $id, array $data): Device
    {
        $device = Device::find($id);
        if (!$device) {
            throw new BusinessException('设备不存在', ResponseCode::DATA_NOT_FOUND);
        }

        if (!empty($data['category'])) {
            $category = Category::where('name', $data['category'])
                ->orWhere('code', $data['category'])
                ->first();
            if (!$category) {
                throw new BusinessException('设备分类不存在，请先创建分类或使用现有分类', ResponseCode::DATA_NOT_FOUND);
            }
            $device->category = $category->code;
            unset($data['category']);
        }

        $fillable = ['name', 'description', 'total_qty', 'available_qty', 'status'];
        foreach ($fillable as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null) {
                $device->{$field} = $data[$field];
            }
        }

        $device->save();

        Log::channel('business')->info('设备更新', [
            'device_id' => $device->id,
            'operator_id' => Auth::id(),
        ]);

        return $device;
    }

    public function deleteDevice(int $id): void
    {
        $device = Device::find($id);
        if (!$device) {
            throw new BusinessException('设备不存在', ResponseCode::DATA_NOT_FOUND);
        }

        $device->delete();

        Log::channel('business')->warning('设备下架', [
            'device_id' => $id,
            'device_name' => $device->name,
            'operator_id' => Auth::id(),
        ]);
    }

    // ========== 分类管理 ==========

    public function getCategories(Request $request, ?User $user): array
    {
        $query = Category::query();

        if (!$user?->isAdmin()) {
            $query->where('is_active', true);
        }

        if ($request->has('keyword')) {
            $keyword = $request->input('keyword');
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                  ->orWhere('code', 'like', "%{$keyword}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->input('is_active'));
        }

        $query->ordered();

        $page = $request->input('page', 1);
        $pageSize = $request->input('pageSize', 10);
        $categories = $query->paginate($pageSize, ['*'], 'page', $page);

        $list = $categories->map(function ($category) {
            return [
                'id' => $category->id,
                'name' => $category->name,
                'code' => $category->code,
                'description' => $category->description,
                'sort_order' => $category->sort_order,
                'is_active' => $category->is_active,
                'device_count' => Device::where('category', $category->code)->count(),
                'created_at' => $category->created_at,
                'updated_at' => $category->updated_at,
            ];
        });

        return [
            'total' => $categories->total(),
            'page' => $categories->currentPage(),
            'pageSize' => $categories->perPage(),
            'list' => $list,
        ];
    }

    public function getCategory(int $id): array
    {
        $category = Category::find($id);
        if (!$category) {
            throw new BusinessException('分类不存在', ResponseCode::DATA_NOT_FOUND);
        }

        $devices = Device::where('category', $category->code)->get();

        $deviceList = $devices->map(function ($device) {
            $borrowedCount = Booking::where('device_id', $device->id)
                ->whereIn('status', ['approved', 'pending'])
                ->count();
            return [
                'id' => $device->id,
                'name' => $device->name,
                'status' => $device->status,
                'total_qty' => $device->total_qty,
                'available_qty' => $device->available_qty,
                'real_available_qty' => $device->total_qty - $borrowedCount,
                'borrowed_count' => $borrowedCount,
            ];
        });

        return [
            'id' => $category->id,
            'name' => $category->name,
            'code' => $category->code,
            'description' => $category->description,
            'sort_order' => $category->sort_order,
            'is_active' => $category->is_active,
            'device_count' => $devices->count(),
            'devices' => $deviceList,
            'created_at' => $category->created_at,
            'updated_at' => $category->updated_at,
        ];
    }

    public function createCategory(array $data): Category
    {
        $category = Category::create([
            'name' => $data['name'],
            'code' => $data['code'],
            'description' => $data['description'],
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ]);

        Log::channel('business')->info('分类创建', [
            'category_id' => $category->id,
            'category_name' => $category->name,
            'operator_id' => Auth::id(),
        ]);

        return $category;
    }

    public function updateCategory(int $id, array $data): Category
    {
        $category = Category::find($id);
        if (!$category) {
            throw new BusinessException('分类不存在', ResponseCode::DATA_NOT_FOUND);
        }

        $oldCode = $category->code;

        $category->fill($data);
        $category->save();

        if (!empty($data['code']) && $data['code'] !== $oldCode) {
            Device::where('category', $oldCode)->update(['category' => $data['code']]);
        }

        Log::channel('business')->info('分类更新', [
            'category_id' => $category->id,
            'operator_id' => Auth::id(),
        ]);

        return $category;
    }

    public function deleteCategory(int $id): void
    {
        $category = Category::find($id);
        if (!$category) {
            throw new BusinessException('分类不存在', ResponseCode::DATA_NOT_FOUND);
        }

        if (Device::where('category', $category->code)->count() > 0) {
            throw new BusinessException('该分类下存在设备，无法删除，请先移除或转移设备', ResponseCode::DATA_RELATION_EXISTS);
        }

        $category->delete();

        Log::channel('business')->warning('分类删除', [
            'category_id' => $id,
            'category_name' => $category->name,
            'operator_id' => Auth::id(),
        ]);
    }

    public function toggleCategoryStatus(int $id): array
    {
        $category = Category::find($id);
        if (!$category) {
            throw new BusinessException('分类不存在', ResponseCode::DATA_NOT_FOUND);
        }

        $category->is_active = !$category->is_active;
        $category->save();

        $statusText = $category->is_active ? '启用' : '禁用';

        Log::channel('business')->info("分类{$statusText}", [
            'category_id' => $category->id,
            'operator_id' => Auth::id(),
        ]);

        return [
            'id' => $category->id,
            'name' => $category->name,
            'is_active' => $category->is_active,
            'status_text' => $statusText,
        ];
    }

    public function getCategoryStatistics(): array
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

        return [
            'categories' => $stats,
            'total_categories' => $categories->count(),
            'total_devices' => Device::count(),
        ];
    }

    // ========== 用户管理 ==========

    public function deactivateUser(int $id, User $currentUser): void
    {
        $targetUser = User::find($id);
        if (!$targetUser) {
            throw new BusinessException('用户不存在', ResponseCode::DATA_NOT_FOUND);
        }

        if ($targetUser->id === $currentUser->id) {
            throw new BusinessException('不能注销自己的账号', ResponseCode::STATUS_NOT_ALLOWED);
        }

        $targetUser->delete();

        Log::channel('business')->warning('管理员注销用户', [
            'target_user_id' => $id,
            'target_account' => $targetUser->account,
            'operator_id' => $currentUser->id,
        ]);
    }

    // ========== 格式化方法 ==========

    private function formatBookingWithCategory(Booking $b, bool $withTimestamps = false): array
    {
        $category = Category::where('code', $b->device->category ?? '')->first();
        $data = [
            'id' => $b->id,
            'user_name' => $b->user->name ?? '',
            'device_name' => $b->device->name ?? '',
            'borrow_start' => $b->borrow_start,
            'borrow_end' => $b->borrow_end,
            'status' => $b->status,
            'created_at' => $b->created_at,
            'user' => [
                'id' => $b->user->id ?? null,
                'account' => $b->user->account ?? '',
                'name' => $b->user->name ?? '',
            ],
            'device' => [
                'id' => $b->device->id ?? null,
                'name' => $b->device->name ?? '',
                'available_qty' => $b->device->available_qty ?? 0,
                'category_code' => $b->device->category ?? '',
                'category' => $category ? [
                    'id' => $category->id,
                    'name' => $category->name,
                    'code' => $category->code,
                ] : null,
            ],
        ];
        if ($withTimestamps) {
            $data['updated_at'] = $b->updated_at;
        }
        return $data;
    }

    private function formatBookingSimple(Booking $b): array
    {
        return [
            'id' => $b->id,
            'user_name' => $b->user->name ?? '',
            'device_name' => $b->device->name ?? '',
            'borrow_start' => $b->borrow_start,
            'borrow_end' => $b->borrow_end,
            'status' => $b->status,
            'created_at' => $b->created_at,
            'updated_at' => $b->updated_at,
            'user' => [
                'id' => $b->user->id ?? null,
                'account' => $b->user->account ?? '',
                'name' => $b->user->name ?? '',
            ],
            'device' => [
                'id' => $b->device->id ?? null,
                'name' => $b->device->name ?? '',
            ],
        ];
    }
}
