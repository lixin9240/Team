<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateBookingRequest;
use App\Services\EquipmentService;
use App\Support\Result;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class EquipmentController extends Controller
{
    private EquipmentService $equipmentService;

    public function __construct(EquipmentService $equipmentService)
    {
        $this->equipmentService = $equipmentService;
    }

    /**
     * 获取设备列表
     */
    public function getDevices(Request $request): JsonResponse
    {
        return Result::success('获取成功', $this->equipmentService->getDevices($request));
    }

    /**
     * 获取设备详情
     */
    public function getDevice($id): JsonResponse
    {
        return Result::success('获取成功', $this->equipmentService->getDevice((int) $id));
    }

    /**
     * 发起借用申请
     */
    public function createBooking(CreateBookingRequest $request): JsonResponse
    {
        $booking = $this->equipmentService->createBooking($request->validated(), Auth::id());
        return Result::success('申请已提交，等待审核', $booking);
    }

    /**
     * 获取个人借用记录
     */
    public function getMyBookings(Request $request): JsonResponse
    {
        return Result::success('获取成功', $this->equipmentService->getMyBookings($request));
    }

    /**
     * 申请归还设备
     */
    public function returnBooking($id): JsonResponse
    {
        $data = $this->equipmentService->returnBooking((int) $id, Auth::id());
        return Result::success('归还申请已提交，等待管理员审核', $data);
    }
}
