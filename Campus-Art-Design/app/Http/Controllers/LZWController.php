<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderAttachment;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LZWController extends \Illuminate\Routing\Controller
{
    /**
     * 允许上传的文件类型白名单
     */
    private array $allowedMimeTypes = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'application/pdf',
        'application/zip',
        'application/x-zip-compressed',
    ];

    /**
     * 允许上传的文件扩展名白名单
     */
    private array $allowedExtensions = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'zip',
    ];

    /**
     * 最大文件大小（10MB）
     */
    private int $maxFileSize = 10 * 1024 * 1024;

    /**
     * 上传定制稿 - 为已提交订单上传设计稿/图案
     * POST /api/orders/{id}/design
     */
    public function uploadDesign(Request $request, int $id): JsonResponse
    {
        try {
            $user = auth('api')->user();
            if (!$user) {
                return response()->json([
                    'code' => 401,
                    'message' => '请先登录',
                ], 401);
            }

            // 验证请求
            $validator = Validator::make($request->all(), [
                'file' => 'required|file|max:10240', // 最大10MB
                'description' => 'nullable|string|max:500',
            ], [
                'file.required' => '请上传设计稿文件',
                'file.max' => '文件大小不能超过10MB',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'code' => 422,
                    'message' => '参数验证失败',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // 查询订单
            $order = Order::with('product')->find($id);
            if (!$order) {
                return response()->json([
                    'code' => 404,
                    'message' => '订单不存在',
                ], 404);
            }

            // 检查权限（只能上传自己的订单，管理员可以上传任何订单）
            if ($order->user_id !== $user->id && $user->role !== 'admin') {
                return response()->json([
                    'code' => 403,
                    'message' => '无权限操作此订单',
                ], 403);
            }

            // 检查订单状态（只有待上传或审核驳回的订单可以上传）
            if (!in_array($order->design_status, [1, 4])) { // 1=待上传, 4=审核驳回
                return response()->json([
                    'code' => 422,
                    'message' => '当前订单状态不允许上传设计稿',
                ], 422);
            }

            $file = $request->file('file');

            // 校验文件MIME类型
            $mimeType = $file->getMimeType();
            if (!in_array($mimeType, $this->allowedMimeTypes)) {
                return response()->json([
                    'code' => 422,
                    'message' => '不支持的文件类型，只允许：jpg, png, gif, webp, pdf, zip',
                ], 422);
            }

            // 校验文件扩展名
            $extension = strtolower($file->getClientOriginalExtension());
            if (!in_array($extension, $this->allowedExtensions)) {
                return response()->json([
                    'code' => 422,
                    'message' => '不支持的文件格式，只允许：jpg, png, gif, webp, pdf, zip',
                ], 422);
            }

            // 校验文件大小
            if ($file->getSize() > $this->maxFileSize) {
                return response()->json([
                    'code' => 422,
                    'message' => '文件大小不能超过10MB',
                ], 422);
            }

            // 获取图片尺寸（如果是图片）
            $width = null;
            $height = null;
            if (str_starts_with($mimeType, 'image/')) {
                $imageInfo = getimagesize($file->getPathname());
                if ($imageInfo) {
                    $width = $imageInfo[0];
                    $height = $imageInfo[1];
                }
            }

            DB::beginTransaction();

            try {
                // 生成文件名：order_{订单ID}_{时间戳}_{随机字符串}.{扩展名}
                $fileName = sprintf(
                    'order_%d_%s_%s.%s',
                    $order->id,
                    now()->format('YmdHis'),
                    Str::random(8),
                    $extension
                );

                // 上传路径：designs/2026/05/03/
                $directory = 'designs/' . now()->format('Y/m/d');
                
                // 存储文件（使用本地存储，实际项目可改为OSS）
                $filePath = $file->storeAs($directory, $fileName, 'public');
                
                // 生成访问URL
                $fileUrl = asset('storage/' . $filePath);

                // 保存附件记录
                $attachment = OrderAttachment::create([
                    'order_id' => $order->id,
                    'file_url' => $fileUrl,
                    'file_name' => $file->getClientOriginalName(),
                    'file_size' => $file->getSize(),
                    'mime_type' => $mimeType,
                    'width' => $width,
                    'height' => $height,
                    'is_deleted' => 0,
                ]);

                // 更新订单设计状态为"已上传"
                $order->update([
                    'design_status' => 2, // 已上传
                ]);

                DB::commit();

                return response()->json([
                    'code' => 200,
                    'message' => '设计稿上传成功',
                    'data' => [
                        'order_id' => $order->id,
                        'order_no' => $order->order_no,
                        'attachment_id' => $attachment->id,
                        'file_url' => $fileUrl,
                        'file_name' => $attachment->file_name,
                        'file_size' => $attachment->file_size,
                        'width' => $width,
                        'height' => $height,
                        'design_status' => 2,
                        'design_status_label' => '已上传',
                    ],
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                // 删除已上传的文件
                if (isset($filePath) && Storage::disk('public')->exists($filePath)) {
                    Storage::disk('public')->delete($filePath);
                }
                throw $e;
            }

        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => '上传失败：' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 确认收货/核销 - 用户确认收货或管理员核销
     * POST /api/orders/{id}/complete
     */
    public function completeOrder(Request $request, int $id): JsonResponse
    {
        try {
            $user = auth('api')->user();
            if (!$user) {
                return response()->json([
                    'code' => 401,
                    'message' => '请先登录',
                ], 401);
            }

            // 查询订单
            $order = Order::with(['product', 'user'])->find($id);
            if (!$order) {
                return response()->json([
                    'code' => 404,
                    'message' => '订单不存在',
                ], 404);
            }

            // 权限检查
            $isAdmin = $user->role === 'admin';
            $isOwner = $order->user_id === $user->id;

            if (!$isAdmin && !$isOwner) {
                return response()->json([
                    'code' => 403,
                    'message' => '无权限操作此订单',
                ], 403);
            }

            // 状态校验：只有已发货的订单可以确认收货
            if ($order->status !== 'shipped') {
                return response()->json([
                    'code' => 422,
                    'message' => '订单状态不正确，只有已发货的订单可以确认收货',
                ], 422);
            }

            // 防重复点击：检查是否已经在处理中
            $lockKey = 'order_complete_lock_' . $id;
            if (cache()->has($lockKey)) {
                return response()->json([
                    'code' => 429,
                    'message' => '操作过于频繁，请稍后再试',
                ], 429);
            }

            // 设置锁，防止重复提交
            cache()->put($lockKey, true, 10); // 10秒锁

            DB::beginTransaction();

            try {
                // 再次检查订单状态（双重检查）
                $order->refresh();
                if ($order->status !== 'shipped') {
                    throw new \Exception('订单状态已变更，请刷新后重试');
                }

                // 更新订单状态为已完成
                $order->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);

                // 扣减实际库存（将预留库存转为实际销售）
                $product = $order->product;
                if ($product) {
                    // 使用乐观锁更新库存
                    $currentVersion = $product->version;
                    $affected = Product::where('id', $product->id)
                        ->where('version', $currentVersion)
                        ->update([
                            'reserved_qty' => $product->reserved_qty - $order->quantity,
                            'stock' => $product->stock - $order->quantity,
                            'version' => $currentVersion + 1,
                        ]);

                    if ($affected === 0) {
                        throw new \Exception('商品库存信息已变更，请重试');
                    }
                }

                // 记录操作日志
                \App\Models\AuditLog::create([
                    'order_id' => $order->id,
                    'operator_id' => $user->id,
                    'action' => $isAdmin ? '管理员核销订单' : '用户确认收货',
                    'from_status' => 'shipped',
                    'to_status' => 'completed',
                    'remark' => $request->remark ?? '',
                ]);

                DB::commit();

                // 清除锁
                cache()->forget($lockKey);

                return response()->json([
                    'code' => 200,
                    'message' => '订单已完成',
                    'data' => [
                        'order_id' => $order->id,
                        'order_no' => $order->order_no,
                        'status' => 'completed',
                        'status_label' => '已完成',
                        'completed_at' => $order->completed_at?->setTimezone('Asia/Shanghai')?->format('Y-m-d H:i:s'),
                        'completed_by' => $isAdmin ? 'admin' : 'user',
                    ],
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                cache()->forget($lockKey);
                throw $e;
            }

        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => '操作失败：' . $e->getMessage(),
            ], 500);
        }
    }
}