### 2. 外键关系图
```
users (用户表)
  │
  ├──< orders.user_id (一个用户有多个
  订单)
  │
  └──< audit_logs.operator_id (一个
  用户有多条操作日志)


product_categories (商品分类表)
  │
  └──< products.category_id (一个分类
  有多个商品)


products (商品表)
  │
  └──< orders.product_id (一个商品有
  多个订单)


orders (订单表)
  │
  ├──< order_attachments.order_id 
  (一个订单有多个附件)
  │
  └──< audit_logs.order_id (一个订单
  有多条操作日志，可为空)

1. 乐观锁（Optimistic Locking）✅ 已使用
2. 悲观锁（Pessimistic Locking）❌ 未使用
实现方式 ：通过 version 字段实现
    使用场景1：创建订单时预扣库存
文件 ： WLJController.php (第239-318行)
    使用场景2：订单审核时修改数量
文件 ： LXController.php (第780-786行)
    使用场景3：商品维护更新
文件 ： LXController.php (第1044-1077行)
    使用场景4：确认收货时扣减库存
文件 ： LZWController.php (第285-292行)