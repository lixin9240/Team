<?php

namespace App\Http\Controllers;

use App\Http\Requests\AuditBookingRequest;
use App\Http\Requests\AuditReturnRequest;
use App\Http\Requests\CreateCategoryRequest;
use App\Http\Requests\CreateDeviceRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Requests\UpdateDeviceRequest;
use App\Services\AdminService;
use App\Support\Result;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class AdminController extends Controller
{
    private AdminService $adminService;

    public function __construct(AdminService $adminService)
    {
        $this->adminService = $adminService;
    }

    // ========== 借用审核 ==========

    public function getPendingBookings(Request $request): JsonResponse
    {
        $this->adminService->requireAdmin();
        return Result::success('获取成功', $this->adminService->getPendingBookings(
            (int) $request->input('page', 1),
            (int) $request->input('pageSize', 10)
        ));
    }

    public function getReturningBookings(Request $request): JsonResponse
    {
        $this->adminService->requireAdmin();
        return Result::success('获取成功', $this->adminService->getReturningBookings(
            (int) $request->input('page', 1),
            (int) $request->input('pageSize', 10)
        ));
    }

    public function getReturnedBookings(Request $request): JsonResponse
    {
        $this->adminService->requireAdmin();
        return Result::success('获取成功', $this->adminService->getReturnedBookings(
            (int) $request->input('page', 1),
            (int) $request->input('pageSize', 10)
        ));
    }

    public function getUnreturnedBookings(Request $request): JsonResponse
    {
        $this->adminService->requireAdmin();
        return Result::success('获取成功', $this->adminService->getUnreturnedBookings(
            (int) $request->input('page', 1),
            (int) $request->input('pageSize', 10)
        ));
    }

    public function getReturnRejectedBookings(Request $request): JsonResponse
    {
        $this->adminService->requireAdmin();
        return Result::success('获取成功', $this->adminService->getReturnRejectedBookings(
            (int) $request->input('page', 1),
            (int) $request->input('pageSize', 10)
        ));
    }

    public function getRejectedBookings(Request $request): JsonResponse
    {
        $this->adminService->requireAdmin();
        return Result::success('获取成功', $this->adminService->getRejectedBookings(
            (int) $request->input('page', 1),
            (int) $request->input('pageSize', 10)
        ));
    }

    public function auditBooking(AuditBookingRequest $request, $id): JsonResponse
    {
        $this->adminService->requireAdmin();
        $data = $this->adminService->auditBooking(
            (int) $id,
            $request->validated('action'),
            $request->validated('reason'),
            $request->validated('reason_type')
        );
        $msg = $request->validated('action') === 'approve' ? '申请已通过' : '申请已拒绝';
        return Result::success($msg, $data);
    }

    public function auditReturnBooking(AuditReturnRequest $request, $id): JsonResponse
    {
        $this->adminService->requireAdmin();
        $data = $this->adminService->auditReturnBooking(
            (int) $id,
            $request->validated('action'),
            $request->validated('reason')
        );
        $msg = $request->validated('action') === 'approve' ? '归还申请已通过' : '归还申请已拒绝';
        return Result::success($msg, $data);
    }

    // ========== 设备管理 ==========

    public function createDevice(CreateDeviceRequest $request): JsonResponse
    {
        $this->adminService->requireAdmin();
        $device = $this->adminService->createDevice($request->validated());
        return Result::success('设备新增成功', $device);
    }

    public function updateDevice(UpdateDeviceRequest $request, $id): JsonResponse
    {
        $this->adminService->requireAdmin();
        $device = $this->adminService->updateDevice((int) $id, $request->validated());
        return Result::success('设备更新成功', $device);
    }

    public function deleteDevice($id): JsonResponse
    {
        $this->adminService->requireAdmin();
        $this->adminService->deleteDevice((int) $id);
        return Result::success('设备已下架', ['id' => (int) $id]);
    }

    // ========== 分类管理 ==========

    public function getCategories(Request $request): JsonResponse
    {
        return Result::success('获取成功', $this->adminService->getCategories($request, Auth::user()));
    }

    public function getCategory($id): JsonResponse
    {
        return Result::success('获取成功', $this->adminService->getCategory((int) $id));
    }

    public function getCategoryStatistics(): JsonResponse
    {
        return Result::success('获取成功', $this->adminService->getCategoryStatistics());
    }

    public function createCategory(CreateCategoryRequest $request): JsonResponse
    {
        $this->adminService->requireAdmin();
        $category = $this->adminService->createCategory($request->validated());
        return Result::success('分类创建成功', $category);
    }

    public function updateCategory(UpdateCategoryRequest $request, $id): JsonResponse
    {
        $this->adminService->requireAdmin();
        $category = $this->adminService->updateCategory((int) $id, $request->validated());
        return Result::success('分类更新成功', $category);
    }

    public function deleteCategory($id): JsonResponse
    {
        $this->adminService->requireAdmin();
        $this->adminService->deleteCategory((int) $id);
        return Result::success('分类删除成功');
    }

    public function toggleCategoryStatus($id): JsonResponse
    {
        $this->adminService->requireAdmin();
        $data = $this->adminService->toggleCategoryStatus((int) $id);
        return Result::success("分类已{$data['status_text']}", $data);
    }

    // ========== 用户管理 ==========

    public function deactivateUser($id): JsonResponse
    {
        $currentUser = $this->adminService->requireAdmin();
        $this->adminService->deactivateUser((int) $id, $currentUser);
        return Result::success('用户账号已注销');
    }
}
