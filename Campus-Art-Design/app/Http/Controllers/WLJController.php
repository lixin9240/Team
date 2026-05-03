<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class WLJController extends \Illuminate\Routing\Controller
{
    /**
     * 商品列表查询
     * GET /api/products
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // 构建查询
            $query = Product::query();

            // 按分类筛选
            if ($request->filled('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            // 按状态筛选
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            // 按价格区间筛选
            if ($request->filled('min_price')) {
                $query->where('price', '>=', $request->min_price);
            }
            if ($request->filled('max_price')) {
                $query->where('price', '<=', $request->max_price);
            }

            // 关键词搜索（商品名称模糊匹配）
            if ($request->filled('keyword')) {
                $query->where('name', 'like', '%' . $request->keyword . '%');
            }

            // 按类型筛选（文创/物料）
            if ($request->filled('type')) {
                $query->where('type', $request->type);
            }

            // 排序
            $sortField = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortField, $sortOrder);

            // 分页
            $perPage = $request->get('per_page', 10);
            $products = $query->with('category')->paginate($perPage);

            // 处理响应数据（添加库存预警标签）
            $list = $products->through(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'category_id' => $product->category_id,
                    'category_name' => $product->category?->name,
                    'type' => $product->type,
                    'spec' => $product->spec,
                    'price' => $product->price,
                    'stock' => $product->stock,
                    'reserved_qty' => $product->reserved_qty,//已售库存
                    'available_stock' => $product->stock - $product->reserved_qty, // 可用库存
                    'status' => $product->status,
                    'status_label' => $this->getStatusLabel($product->status),//状态标签
                    'cover_url' => $product->cover_url,
                    'custom_rule' => $product->custom_rule,//自定义规则
                    'stock_warning' => $this->getStockWarning($product), // 库存预警
                    'created_at' => $product->created_at,
                    'updated_at' => $product->updated_at,
                ];
            });

            // 简化分页数据结构
            $data = [
                'list' => $list->items(),
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                    'last_page' => $products->lastPage(),
                ],
            ];

            return response()->json([
                'code' => 200,
                'message' => '获取商品列表成功',
                'data' => $data,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => '获取商品列表失败：' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 获取状态标签
     */
    private function getStatusLabel(int $status): string
    {
        return match ($status) {
            0 => '下架',
            1 => '上架',
            2 => '售罄',
            default => '未知',
        };
    }

    /**
     * 获取库存预警
     */
    private function getStockWarning(Product $product): ?string
    {
        $availableStock = $product->stock - $product->reserved_qty;
        
        if ($availableStock <= 0) {
            return '已售罄';
        }
        
        if ($availableStock < 10) {
            return '库存紧张';
        }
        
        if ($availableStock < 50) {
            return '库存偏低';
        }
        
        return null; // 库存充足，无预警
    }

    /**
     * 商品详情
     * GET /api/products/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $product = Product::with('category')->find($id);

            if (!$product) {
                return response()->json([
                    'code' => 404,
                    'message' => '商品不存在',
                ], 404);
            }

            // 检查商品状态（已下架的商品普通用户不可见）
            if ($product->status === 0 && (!auth('api')->check() || auth('api')->user()->role !== 'admin')) {
                return response()->json([
                    'code' => 404,
                    'message' => '商品不存在或已下架',
                ], 404);
            }

            $availableStock = $product->stock - $product->reserved_qty;

            return response()->json([
                'code' => 200,
                'message' => '获取商品详情成功',
                'data' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'category_id' => $product->category_id,
                    'category_name' => $product->category?->name,
                    'type' => $product->type,
                    'spec' => $product->spec,
                    'price' => $product->price,
                    'stock' => $product->stock,
                    'reserved_qty' => $product->reserved_qty,
                    'available_stock' => $availableStock,
                    'sold_qty' => $product->stock - $availableStock, // 已售量
                    'status' => $product->status,
                    'status_label' => $this->getStatusLabel($product->status),
                    'cover_url' => $product->cover_url,
                    'custom_rule' => $product->custom_rule,
                    'stock_warning' => $this->getStockWarning($product),
                    'created_at' => $product->created_at?->setTimezone('Asia/Shanghai')?->format('Y-m-d H:i:s'),
                    'updated_at' => $product->updated_at?->setTimezone('Asia/Shanghai')?->format('Y-m-d H:i:s'),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => '获取商品详情失败：' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 提交预订订单
     * POST /api/orders
     */
    public function createOrder(Request $request): JsonResponse
    {
        try {
            // 验证请求数据
            $validator = Validator::make($request->all(), [
                'product_id' => 'required|integer|exists:products,id',
                'quantity' => 'required|integer|min:1',
                'size_pref' => 'nullable|string|max:50', // 尺寸偏好
                'color_pref' => 'nullable|string|max:50', // 颜色偏好
                'remark' => 'nullable|string|max:500', // 备注
            ], [
                'product_id.required' => '商品ID不能为空',
                'product_id.exists' => '商品不存在',
                'quantity.required' => '数量不能为空',
                'quantity.integer' => '数量必须是整数',
                'quantity.min' => '数量至少为1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'code' => 422,
                    'message' => '参数验证失败',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $user = auth('api')->user();
            $productId = $request->product_id;
            $quantity = $request->quantity;

            // 使用乐观锁处理库存预扣
            $maxRetries = 3; // 最大重试次数
            $order = null;
            $lastError = null;
            
            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                try {
                    $order = DB::transaction(function () use ($user, $productId, $quantity, $request) {
                        // 查询商品（不使用悲观锁）
                        $product = Product::find($productId);

                        if (!$product) {
                            throw new \Exception('商品不存在');
                        }

                        // 检查商品状态
                        if ($product->status !== 1) {
                            throw new \Exception('商品已下架或售罄，无法预订');
                        }

                        // 检查库存
                        $availableStock = $product->stock - $product->reserved_qty;
                        if ($availableStock < $quantity) {
                            throw new \Exception('库存不足，当前可用库存：' . $availableStock);
                        }

                        // 使用乐观锁更新库存
                        $currentVersion = $product->version;
                        $affected = Product::where('id', $productId)
                            ->where('version', $currentVersion)
                            ->update([
                                'reserved_qty' => $product->reserved_qty + $quantity,
                                'version' => $currentVersion + 1,
                            ]);

                        if ($affected === 0) {
                            // 乐观锁冲突，抛出异常进行重试
                            throw new \Exception('OPTIMISTIC_LOCK_CONFLICT');
                        }

                        // 生成订单编号：M + 北京时间年月日时分秒 + 4位随机数
                        $orderNo = 'M' . now()->setTimezone('Asia/Shanghai')->format('YmdHis') . str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);

                        // 判断是否需要定制：如果商品有定制要求说明，则需要上传设计稿
                        $designStatus = $product->custom_rule ? 1 : 0; // 1=待上传, 0=无需定制

                        // 创建订单
                        $order = Order::create([
                            'order_no' => $orderNo,
                            'user_id' => $user->id,
                            'product_id' => $productId,
                            'quantity' => $quantity,
                            'total_price' => $product->price * $quantity,
                            'size_pref' => $request->size_pref,
                            'color_pref' => $request->color_pref,
                            'remark' => $request->remark,
                            'status' => 'pending', // 待处理
                            'design_status' => $designStatus,
                        ]);

                        return $order;
                    });

                    // 成功创建订单，跳出重试循环
                    break;

                } catch (\Exception $e) {
                    $lastError = $e;
                    if ($e->getMessage() === 'OPTIMISTIC_LOCK_CONFLICT' && $attempt < $maxRetries) {
                        // 乐观锁冲突，继续重试
                        continue;
                    }
                    
                    // 其他错误，直接抛出
                    throw $e;
                }
            }

            // 如果重试次数用尽仍然是乐观锁冲突
            if ($lastError && $lastError->getMessage() === 'OPTIMISTIC_LOCK_CONFLICT') {
                return response()->json([
                    'code' => 500,
                    'message' => '订单提交失败：系统繁忙，请稍后重试',
                ], 500);
            }

            return response()->json([
                'code' => 200,
                'message' => '订单提交成功',
                'data' => [
                    'order_id' => $order->id,
                    'order_no' => $order->order_no ?? $order->id,
                    'status' => $order->status,
                    'total_price' => $order->total_price,
                    'created_at' => $order->created_at,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => '订单提交失败：' . $e->getMessage(),
            ], 500);
        }
    }
}