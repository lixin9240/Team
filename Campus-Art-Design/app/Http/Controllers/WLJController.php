<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class WLJController extends \Illuminate\Routing\Controller
{
    public function index(Request $request)
    {
        try {
            $validated = $request->validate([
                'category_id' => 'nullable|integer|exists:product_categories,id',
                'type' => 'nullable|string|in:文创,物料',
                'status' => 'nullable|integer|in:0,1,2',
                'min_price' => 'nullable|numeric|min:0',
                'max_price' => 'nullable|numeric|min:0|gte:min_price',
                'keyword' => 'nullable|string|max:100',
                'page' => 'nullable|integer|min:1',
                'per_page' => 'nullable|integer|min:1|max:50',
            ]);

            $perPage = $request->input('per_page', 15);

            $query = Product::with('category:id,name')
                ->when($request->filled('category_id'), function ($q) use ($request) {
                    $q->where('category_id', $request->category_id);
                })
                ->when($request->filled('type'), function ($q) use ($request) {
                    $q->where('type', $request->type);
                })
                ->when($request->filled('status'), function ($q) use ($request) {
                    $q->where('status', $request->status);
                }, function ($q) {
                    $q->where('status', 1);
                })
                ->when($request->filled('min_price'), function ($q) use ($request) {
                    $q->where('price', '>=', $request->min_price);
                })
                ->when($request->filled('max_price'), function ($q) use ($request) {
                    $q->where('price', '<=', $request->max_price);
                })
                ->when($request->filled('keyword'), function ($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->keyword . '%');
                })
                ->orderBy('created_at', 'desc');

            $products = $query->paginate($perPage);

            $products->getCollection()->transform(function ($product) {
                $product->available_stock = $product->stock - $product->reserved_qty;
                $product->is_low_stock = $product->available_stock < 10;
                return $product;
            });

            return response()->json([
                'code' => 200,
                'message' => '获取商品列表成功',
                'data' => [
                    'list' => $products->items(),
                    'pagination' => [
                        'current_page' => $products->currentPage(),
                        'per_page' => $products->perPage(),
                        'total' => $products->total(),
                        'last_page' => $products->lastPage(),
                    ],
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'code' => 422,
                'message' => '参数验证失败',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Order Store Error: ' . $e->getMessage() . ' | Line: ' . $e->getLine());
            return response()->json([
                'code' => 500,
                'message' => '服务器错误',
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $product = Product::with('category:id,name')
                ->find($id);

            if (!$product) {
                return response()->json([
                    'code' => 404,
                    'message' => '商品不存在',
                ], 404);
            }

            $soldCount = Order::where('product_id', $id)
                ->whereIn('status', ['booked', 'design_pending', 'ready', 'completed'])
                ->sum('quantity');

            $product->available_stock = $product->stock - $product->reserved_qty;
            $product->is_low_stock = $product->available_stock < 10;
            $product->sold_count = $soldCount;

            return response()->json([
                'code' => 200,
                'message' => '获取商品详情成功',
                'data' => $product,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => '服务器错误',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'product_id' => 'required|integer|exists:products,id',
                'quantity' => 'required|integer|min:1',
                'size_pref' => 'nullable|string|max:50',
                'color_pref' => 'nullable|string|max:50',
                'remark' => 'nullable|string|max:500',
            ]);

            $user = auth('api')->user();
            if (!$user) {
                return response()->json([
                    'code' => 401,
                    'message' => '请先登录',
                ], 401);
            }

            return DB::transaction(function () use ($request, $user) {
                $product = Product::find($request->product_id);

                if (!$product) {
                    return response()->json([
                        'code' => 404,
                        'message' => '商品不存在',
                    ], 404);
                }

                if ($product->status !== 1) {
                    return response()->json([
                        'code' => 422,
                        'message' => '商品已下架或售罄',
                    ], 422);
                }

                $availableStock = $product->stock - $product->reserved_qty;
                if ($availableStock < $request->quantity) {
                    return response()->json([
                        'code' => 4220,
                        'message' => '库存不足',
                        'data' => [
                            'available_stock' => $availableStock,
                            'requested_quantity' => $request->quantity,
                        ],
                    ], 422);
                }

                $product->reserved_qty += $request->quantity;
                $product->save();

                $orderNo = 'M' . date('YmdHi') . str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);

                $totalPrice = bcmul($product->price, $request->quantity, 2);

                $designStatus = 0;
                if ($product->custom_rule) {
                    $designStatus = 1;
                }

                $order = Order::create([
                    'order_no' => $orderNo,
                    'user_id' => $user->id,
                    'product_id' => $product->id,
                    'quantity' => $request->quantity,
                    'size_pref' => $request->size_pref,
                    'color_pref' => $request->color_pref,
                    'remark' => $request->remark,
                    'total_price' => $totalPrice,
                    'status' => 'draft',
                    'design_status' => $designStatus,
                ]);

                $designDeadline = null;
                if ($designStatus === 1) {
                    $designDeadline = now()->addDays(3)->toDateTimeString();
                }

                return response()->json([
                    'code' => 200,
                    'message' => '预订成功',
                    'data' => [
                        'order_id' => $order->id,
                        'order_no' => $order->order_no,
                        'total_price' => $order->total_price,
                        'status' => $order->status,
                        'design_status' => $order->design_status,
                        'design_deadline' => $designDeadline,
                        'created_at' => $order->created_at->toDateTimeString(),
                    ],
                ], 201);
            });
        } catch (ValidationException $e) {
            Log::error('Validation Error: ' . $e->getMessage());
            return response()->json([
                'code' => 422,
                'message' => '参数验证失败',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Order Store Error: ' . $e->getMessage() . ' | Line: ' . $e->getLine());
            return response()->json([
                'code' => 500,
                'message' => '服务器错误',
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
            ], 500);
        }
    }
}
