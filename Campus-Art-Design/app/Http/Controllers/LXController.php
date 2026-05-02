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
    private function convertProductStatus($status): int
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
    private function isEmptyRow($row): bool
    {
        // 如果第一列（商品名称）为空，认为是空行或表头行
        $firstValue = $row[0] ?? $row['商品名称'] ?? null;
        return empty($firstValue) || in_array($firstValue, ['商品名称', '名称', 'name', 'Name']);
    }
}
