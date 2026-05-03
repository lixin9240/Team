<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Mail\VerificationCodeMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Collection;
use App\Models\Order;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LXController extends \Illuminate\Routing\Controller
{
    //注册
    //验证注册信息
    public function register(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'account' => 'required|string|max:255|unique:users',
                'phone' => 'nullable|string|max:20',
                'password' => ['required', 'string', 'min:6', 'confirmed', 'regex:/^(?=.*[A-Za-z])(?=.*\d)(?=.*[@$!%*#?&])[A-Za-z\d@$!%*#?&]+$/'],
                'verification_code' => 'required|string|size:6',
            ]);

            $email = $request->email;
            $verificationCode = $request->verification_code;
            $type = 'register';

            $cacheKey = 'verification_code_' . $type . '_' . $email;
            $cachedCode = Cache::get($cacheKey);

            if (!$cachedCode || (string)$cachedCode !== (string)$verificationCode) {
                return response()->json([
                    'message' => '验证码错误或已过期',
                    'errors' => [
                        'verification_code' => ['验证码错误或已过期']
                    ]
                ], 422);
            }

            Cache::forget($cacheKey);

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'account' => $request->account,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'email_verified_at' => now(),
                'role' => 'user',
            ]);

            return response()->json([
                'message' => '注册成功，请登录',
                'data' => [
                    'user' => $user,
                ],
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => '验证失败',
                'errors' => $e->errors()
            ], 422);
        }
    }
//发送验证码
    public function sendVerificationCode(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|string|email|max:255',
                'type' => 'required|string|in:register,reset_password,delete_account,bind_email,change_email',
                //验证码类型，比如注册、重置密码、注销账户、绑定邮箱、修改邮箱
            ]);

            $email = $request->email;
            $type = $request->type;

            // 验证邮箱域名是否有效
            $emailDomain = substr(strrchr($email, '@'), 1);
            if (!checkdnsrr($emailDomain, 'MX')) {
                return response()->json([
                    'message' => '请输入正确的邮箱',
                    'errors' => [
                        'email' => ['请输入正确的邮箱，该邮箱域名不存在']
                    ]
                ], 422);
            }

            $allowedTypes = [
                'register' => '注册',
                'reset_password' => '重置密码',
                'delete_account' => '注销账户',
                'bind_email' => '绑定邮箱',
                'change_email' => '修改邮箱',
            ];

            if ($type === 'delete_account' || $type === 'bind_email' || $type === 'change_email') {
                if (!auth('api')->check()) {
                    return response()->json([
                        'message' => '该操作需要先登录',
                        'errors' => [
                            'type' => ['该操作需要先登录']
                        ]
                    ], 401);
                }
            }

            if ($type === 'change_email') {
                $user = auth('api')->user();
                if ($user->email === $email) {
                    return response()->json([
                        'message' => '新邮箱不能与当前邮箱相同',
                        'errors' => [
                            'email' => ['新邮箱不能与当前邮箱相同']
                        ]
                    ], 422);
                }
            }

            if ($type === 'bind_email') {
                $user = auth('api')->user();
                if ($user->email) {
                    return response()->json([
                        'message' => '您已绑定邮箱，如需更换请使用修改邮箱功能',
                        'errors' => [
                            'email' => ['您已绑定邮箱，如需更换请使用修改邮箱功能']
                        ]
                    ], 422);
                }
            }

            $code = rand(100000, 999999);

            $cacheKey = 'verification_code_' . $type . '_' . $email;
            Cache::put($cacheKey, $code, 300);

            try {
                Mail::to($email)->send(new VerificationCodeMail($code));

                return response()->json([
                    'message' => '验证码发送成功，请查收邮件',
                    'data' => [
                        'email' => $email,
                        'type' => $type,
                        'type_label' => $allowedTypes[$type],
                        'expires_in' => 300,
                    ],
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'message' => '验证码发送失败，请稍后重试',
                    'error' => $e->getMessage(),
                ], 500);
            }
        } catch (ValidationException $e) {
            return response()->json([
                'message' => '验证失败',
                'errors' => $e->errors()
            ], 422);
        }
    }
//登录
    public function login(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|string|email',
                'password' => 'required|string',
            ]);

            $user = User::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json([
                    'message' => '登录失败',
                    'errors' => [
                        'email' => ['邮箱或密码错误']
                    ]
                ], 401);
            }

            $token = JWTAuth::fromUser($user);

            return response()->json([
                'message' => '登录成功',
                'data' => [
                    'user' => $user,
                    'token' => $token,
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => '验证失败',
                'errors' => $e->errors()
            ], 422);
        }
    }
//注销
    public function logout(Request $request)
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());

            return response()->json([
                'message' => '退出登录成功',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => '退出登录失败',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
//获取用户信息
    public function me()
    {
        try {
            return response()->json([
                'message' => '获取用户信息成功',
                'data' => [
                    'user' => auth('api')->user(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => '获取用户信息失败',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 批量导入商品（Excel）
     * POST /api/products/import
     */
    public function importProducts(Request $request)
    {
        // 设置执行时间和内存限制
        set_time_limit(300); // 5分钟
        ini_set('memory_limit', '512M');
        
        try {
            // 1. 验证上传文件
            $validator = Validator::make($request->all(), [
                'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'code' => 422,
                    'message' => '文件验证失败',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $file = $request->file('file');

            // 2. 读取Excel数据（不使用表头，直接按列索引读取）
            $data = Excel::toCollection(new class implements ToCollection {
                public function collection(Collection $rows)
                {
                    return $rows;
                }
            }, $file);

            $rows = $data->first();
            
            if ($rows->isEmpty()) {
                return response()->json([
                    'code' => 422,
                    'message' => 'Excel文件为空',
                ], 422);
            }

            // 3. 初始化结果统计
            $result = [
                'total' => 0,
                'success' => 0,
                'failed' => 0,
                'errors' => [],
            ];

            // 4. 使用事务批量处理
            DB::beginTransaction();

            foreach ($rows as $index => $row) {
                $rowNumber = $index + 1; // Excel行号

                // 跳过空行（第一行可能是表头或空行）
                if ($this->isEmptyRow($row)) {
                    continue;
                }

                $result['total']++;

                // 按列索引获取数据（支持无表头的Excel）
                $rowData = [
                    '商品名称' => $row[0] ?? $row['商品名称'] ?? null,
                    '分类名称' => $row[1] ?? $row['分类名称'] ?? null,
                    '类型' => $row[2] ?? $row['类型'] ?? null,
                    '单价' => $row[3] ?? $row['单价'] ?? null,
                    '库存' => $row[4] ?? $row['库存'] ?? null,
                    '规格' => $row[5] ?? $row['规格'] ?? null,
                    '定制要求' => $row[6] ?? $row['定制要求'] ?? null,
                    '状态' => $row[7] ?? $row['状态'] ?? '上架',
                ];

                // 行级校验
                $rowValidator = Validator::make($rowData, [
                    '商品名称' => 'required|string|max:200',
                    '分类名称' => 'required|string',
                    '类型' => 'required|in:文创,物料',
                    '单价' => 'required|numeric|min:0',
                    '库存' => 'required|integer|min:0',
                    '规格' => 'nullable|string|max:500',
                    '定制要求' => 'nullable|string',
                    '状态' => 'nullable',
                ], [
                    '商品名称.required' => '商品名称不能为空',
                    '分类名称.required' => '分类名称不能为空',
                    '类型.required' => '类型不能为空',
                    '类型.in' => '类型必须是"文创"或"物料"',
                    '单价.required' => '单价不能为空',
                    '单价.numeric' => '单价必须是数字',
                    '库存.required' => '库存不能为空',
                    '库存.integer' => '库存必须是整数',
                ]);

                if ($rowValidator->fails()) {
                    $result['failed']++;
                    $result['errors'][] = [
                        'row' => $rowNumber,
                        'data' => $rowData,
                        'errors' => $rowValidator->errors()->toArray(),
                    ];
                    continue;
                }

                // 转换状态
                $status = $this->convertProductStatus($rowData['状态']);

                try {
                    // 查找或创建分类
                    $category = ProductCategory::firstOrCreate(
                        ['name' => $rowData['分类名称']],
                        ['name' => $rowData['分类名称']]
                    );

                    // 创建或更新商品
                    Product::updateOrCreate(
                        ['name' => $rowData['商品名称']],
                        [
                            'category_id' => $category->id,
                            'type' => $rowData['类型'],
                            'spec' => $rowData['规格'] ?? null,
                            'price' => $rowData['单价'],
                            'stock' => $rowData['库存'],
                            'reserved_qty' => 0,
                            'custom_rule' => $rowData['定制要求'] ?? null,
                            'status' => $status,
                            'version' => 0,
                        ]
                    );

                    $result['success']++;

                } catch (\Exception $e) {
                    $result['failed']++;
                    $result['errors'][] = [
                        'row' => $rowNumber,
                        'data' => $row->toArray(),
                        'errors' => ['数据库错误: ' . $e->getMessage()],
                    ];
                }
            }

            DB::commit();

            return response()->json([
                'code' => 200,
                'message' => '导入完成',
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'code' => 500,
                'message' => '导入失败：' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 转换商品状态
     */
    private function convertProductStatus(string|int|null $status): int
    {
        if (is_numeric($status)) {
            return in_array((int)$status, [0, 1, 2]) ? (int)$status : 1;
        }
        
        return match ($status) {
            '下架' => 0,
            '上架' => 1,
            '售罄' => 2,
            default => 1,
        };
    }

    /**
     * 判断是否为空白行
     */
    private function isEmptyRow(array|Collection $row): bool
    {
        // 如果第一列（商品名称）为空，认为是空行或表头行
        $firstValue = $row[0] ?? $row['商品名称'] ?? null;
        return empty($firstValue) || in_array($firstValue, ['商品名称', '名称', 'name', 'Name']);
    }

    /**
     * 报表导出 - 按筛选条件导出订单Excel
     * GET /api/orders/export
     */
    public function exportOrders(Request $request)
    {
        try {
            // 设置执行时间和内存限制
            set_time_limit(300);
            ini_set('memory_limit', '512M');

            // 构建查询
            $query = Order::with(['user', 'product', 'attachments']);

            // 按订单状态筛选
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // 按设计状态筛选
            if ($request->has('design_status')) {
                $query->where('design_status', $request->design_status);
            }

            // 按用户筛选
            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            // 按商品筛选
            if ($request->has('product_id')) {
                $query->where('product_id', $request->product_id);
            }

            // 按时间范围筛选
            if ($request->has('start_date')) {
                $query->whereDate('created_at', '>=', $request->start_date);
            }
            if ($request->has('end_date')) {
                $query->whereDate('created_at', '<=', $request->end_date);
            }

            // 关键词搜索（订单号）
            if ($request->has('keyword')) {
                $query->where('order_no', 'like', '%' . $request->keyword . '%');
            }

            // 分批查询防止OOM，每次处理1000条
            $orders = $query->orderBy('created_at', 'desc')->get();
            
            if ($orders->isEmpty()) {
                return response()->json([
                    'code' => 404,
                    'message' => '没有符合条件的订单数据',
                ], 404);
            }

            // 定义Excel表头
            $headings = [
                '订单编号',
                '下单时间',
                '用户账号',
                '用户邮箱',
                '商品名称',
                '商品类型',
                '预订数量',
                '尺寸偏好',
                '颜色偏好',
                '订单总价',
                '订单状态',
                '设计状态',
                '定制稿链接',
                '备注',
                '实付金额',
                '支付时间',
                '完成时间',
            ];

            // 准备数据
            $data = $orders->map(function ($order) {
                // 获取最新的定制稿链接（假设附件中有设计稿）
                $designUrl = $order->attachments
                    ->where('is_deleted', 0)
                    ->sortByDesc('created_at')
                    ->first()?->file_url ?? '';

                return [
                    'order_no' => $order->order_no,
                    'created_at' => $order->created_at?->format('Y-m-d H:i:s'),
                    'user_account' => $order->user?->account ?? '',
                    'user_email' => $order->user?->email ?? '',
                    'product_name' => $order->product?->name ?? '',
                    'product_type' => $order->product?->type ?? '',
                    'quantity' => $order->quantity,
                    'size_pref' => $order->size_pref ?? '',
                    'color_pref' => $order->color_pref ?? '',
                    'total_price' => $order->total_price,
                    'status' => $this->getOrderStatusLabel($order->status),
                    'design_status' => $this->getDesignStatusLabel($order->design_status),
                    'design_url' => $designUrl,
                    'remark' => $order->remark ?? '',
                    'paid_amount' => $order->paid_amount ?? '',
                    'paid_at' => $order->paid_at?->format('Y-m-d H:i:s') ?? '',
                    'completed_at' => $order->completed_at?->format('Y-m-d H:i:s') ?? '',
                ];
            });

            // 生成文件名
            $filename = 'orders_export_' . date('YmdHis') . '.xlsx';

            // 使用流式输出，防止大文件OOM
            $response = new StreamedResponse(function () use ($data, $headings) {
                $excelData = $data->toArray();
                array_unshift($excelData, $headings);
                
                $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();
                
                // 分批写入数据，每批1000行
                $rowIndex = 1;
                foreach ($excelData as $row) {
                    $colIndex = 1;
                    foreach ($row as $value) {
                        $sheet->setCellValueByColumnAndRow($colIndex++, $rowIndex, $value);
                    }
                    $rowIndex++;
                    
                    // 每1000行刷新一次缓冲区
                    if ($rowIndex % 1000 === 0) {
                        flush();
                    }
                }
                
                $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
                $writer->save('php://output');
            });

            $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
            $response->headers->set('Cache-Control', 'max-age=0');

            return $response;

        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => '导出失败：' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 获取订单状态标签
     */
    private function getOrderStatusLabel(string $status): string
    {
        return match ($status) {
            'draft' => '草稿',
            'pending' => '待处理',
            'paid' => '已支付',
            'designing' => '设计中',
            'producing' => '生产中',
            'shipped' => '已发货',
            'completed' => '已完成',
            'cancelled' => '已取消',
            default => $status,
        };
    }

    /**
     * 获取设计状态标签
     */
    private function getDesignStatusLabel(int $designStatus): string
    {
        return match ($designStatus) {
            0 => '无需定制',
            1 => '待上传',
            2 => '已上传',
            3 => '审核通过',
            4 => '审核驳回',
            default => '未知',
        };
    }

    /**
     * 订单审核 - 管理员审核定制稿
     * PUT /api/admin/orders/{id}/review
     */
    public function reviewOrder(Request $request, int $id): JsonResponse
    {
        try {
            // 验证请求数据
            $validator = Validator::make($request->all(), [
                'action' => 'required|in:approve,reject,update',
                'design_status' => 'nullable|integer|in:0,1,2,3,4',
                'quantity' => 'nullable|integer|min:1',
                'status' => 'nullable|string|in:pending,paid,designing,producing,shipped,completed,cancelled',
                'remark' => 'nullable|string|max:500',
            ], [
                'action.required' => '审核动作不能为空',
                'action.in' => '审核动作必须是 approve(通过)、reject(驳回) 或 update(修改)',
                'design_status.in' => '设计状态无效',
                'quantity.min' => '数量至少为1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'code' => 422,
                    'message' => '参数验证失败',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // 获取当前管理员
            $operator = auth('api')->user();
            if (!$operator || $operator->role !== 'admin') {
                return response()->json([
                    'code' => 403,
                    'message' => '无权限操作',
                ], 403);
            }

            // 查询订单
            $order = Order::with('product')->find($id);
            if (!$order) {
                return response()->json([
                    'code' => 404,
                    'message' => '订单不存在',
                ], 404);
            }

            // 状态流转校验
            $validTransitions = [
                'draft' => ['pending', 'cancelled'],
                'pending' => ['paid', 'cancelled'],
                'paid' => ['designing', 'cancelled'],
                'designing' => ['producing', 'cancelled'],
                'producing' => ['shipped', 'cancelled'],
                'shipped' => ['completed'],
                'completed' => [],
                'cancelled' => [],
            ];

            $action = $request->action;
            $fromStatus = $order->status;
            $fromDesignStatus = $order->design_status;
            $updates = [];
            $logAction = '';
            $logRemark = $request->remark ?? '';

            DB::beginTransaction();

            try {
                switch ($action) {
                    case 'approve':
                        // 通过审核：设计状态变为审核通过
                        if ($order->design_status !== 2) { // 2=已上传
                            throw new \Exception('只能审核已上传设计稿的订单');
                        }
                        $updates['design_status'] = 3; // 审核通过
                        $logAction = '审核通过';
                        break;

                    case 'reject':
                        // 驳回审核：设计状态变为审核驳回
                        if ($order->design_status !== 2) {
                            throw new \Exception('只能审核已上传设计稿的订单');
                        }
                        $updates['design_status'] = 4; // 审核驳回
                        $logAction = '审核驳回';
                        break;

                    case 'update':
                        // 修改订单信息
                        if ($request->has('quantity')) {
                            // 修改数量需要重新计算总价
                            $oldQuantity = $order->quantity;
                            $newQuantity = $request->quantity;
                            
                            if ($oldQuantity !== $newQuantity) {
                                // 更新库存（先释放旧数量，再预留新数量）
                                $product = $order->product;
                                $availableStock = $product->stock - $product->reserved_qty + $oldQuantity;
                                
                                if ($availableStock < $newQuantity) {
                                    throw new \Exception('库存不足，当前可用库存：' . $availableStock);
                                }
                                
                                // 使用乐观锁更新库存
                                $currentVersion = $product->version;
                                $affected = Product::where('id', $product->id)
                                    ->where('version', $currentVersion)
                                    ->update([
                                        'reserved_qty' => $product->reserved_qty - $oldQuantity + $newQuantity,
                                        'version' => $currentVersion + 1,
                                    ]);
                                
                                if ($affected === 0) {
                                    throw new \Exception('商品信息已变更，请重试');
                                }
                                
                                $updates['quantity'] = $newQuantity;
                                $updates['total_price'] = $product->price * $newQuantity;
                                $logAction .= '修改数量(' . $oldQuantity . '->' . $newQuantity . ');';
                            }
                        }

                        if ($request->has('design_status')) {
                            $updates['design_status'] = $request->design_status;
                            $logAction .= '修改设计状态(' . $fromDesignStatus . '->' . $request->design_status . ');';
                        }

                        if ($request->has('status')) {
                            $newStatus = $request->status;
                            // 校验状态流转是否合法
                            if (!in_array($newStatus, $validTransitions[$fromStatus] ?? [])) {
                                throw new \Exception('非法的状态流转：' . $fromStatus . ' -> ' . $newStatus);
                            }
                            $updates['status'] = $newStatus;
                            $logAction .= '修改订单状态(' . $fromStatus . '->' . $newStatus . ');';
                        }
                        break;
                }

                // 如果有更新，执行更新
                if (!empty($updates)) {
                    // 幂等性控制：检查是否重复操作
                    $lastLog = AuditLog::where('order_id', $order->id)
                        ->where('action', $logAction)
                        ->where('created_at', '>=', now()->subMinutes(1))
                        ->first();
                    
                    if ($lastLog) {
                        throw new \Exception('操作过于频繁，请稍后再试');
                    }

                    $order->update($updates);
                }

                // 记录操作日志
                AuditLog::create([
                    'order_id' => $order->id,
                    'operator_id' => $operator->id,
                    'action' => $logAction,
                    'from_status' => $fromStatus,
                    'to_status' => $updates['status'] ?? $fromStatus,
                    'remark' => $logRemark,
                ]);

                DB::commit();

                return response()->json([
                    'code' => 200,
                    'message' => '操作成功',
                    'data' => [
                        'order_id' => $order->id,
                        'order_no' => $order->order_no,
                        'action' => $action,
                        'updates' => $updates,
                    ],
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
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
