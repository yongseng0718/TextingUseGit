<?php
session_start();
include "db_connect.php"; // 连接数据库

$conn = open_connection();

$user_id = $_SESSION['user_id'] ?? null; // 获取用户 ID（如果已登录）
$cart_items = [];
$total_price = 0;

if ($user_id) {
    // ✅ 用户已登录，从数据库获取购物车
    $sql = "SELECT products.id, products.name, products.price, cart.quantity 
            FROM cart 
            JOIN products ON cart.product_id = products.id 
            WHERE cart.user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // | id | name     | price | quantity |
    // |----|---------|------|---------|
    // | 1  | Apple   | 3    | 2       |
    // | 2  | Banana  | 2    | 3       |

    // `fetch_assoc()` 依次获取：
    // $row = ['id' => 1, 'name' => 'Apple', 'price' => 3, 'quantity' => 2];
    // $cart_items[1] = $row;

    while ($row = $result->fetch_assoc()) {
        $cart_items[$row['id']] = $row;
        $total_price += $row['price'] * $row['quantity'];
    }
} else {
    // 🚀 用户未登录，使用 SESSION 购物车
    $cart_items = $_SESSION['cart'] ?? [];
    foreach ($cart_items as $item) {
        $total_price += $item['price'] * $item['quantity'];
    }
}
?>

<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>购物车</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        .cart-item {
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 10px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .cart-item img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 10px;
        }
    </style>
</head>
<body>

<div class="container mt-4">
    <h2>🛒 我的购物车</h2>

    <?php if (!empty($cart_items)): ?>
        <div class="list-group">
            <?php foreach ($cart_items as $id => $item): ?>
                <?php $product_total = $item['price'] * $item['quantity']; ?>
                <div class="cart-item">
                    <div>
                        <h5><?= htmlspecialchars($item['name']); ?></h5>
                        <p>单价: $<?= number_format($item['price'], 2); ?> | 数量: <?= $item['quantity']; ?> | 该商品总价: $<?= number_format($product_total, 2); ?></p>
                        <label>数量：</label>
                        <input type="number" class="quantity-input" data-id="<?= $id; ?>" value="<?= $item['quantity']; ?>" min="1" max="10">
                    </div>
                    <div>
                        <button class="btn btn-danger remove-from-cart" data-id="<?= $id; ?>">❌ 删除</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($_SESSION['cart']) || !empty($cart_items)): ?>
            <h4 class="mt-3">总价: $<?= number_format($total_price, 2); ?></h4>
            <button id="checkout-btn" class="btn btn-success mt-2">结算</button>
        <?php endif; ?>
        
    <?php else: ?>
        <p>🛍 购物车空空如也，快去添加商品吧！</p>
    <?php endif; ?>
</div>


<!-- 移除商品 -->
<script>
    document.querySelectorAll('.remove-from-cart').forEach(button => {
        button.addEventListener('click', function () {
            let product_id = this.dataset.id;

            fetch('remove_from_cart.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `product_id=${product_id}`
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                window.location.reload();  // 重新加载页面
            });
        });
    });
</script>

<!-- 更改商品数量 -->
<script>
    document.querySelectorAll('.quantity-input').forEach(input => {
        input.addEventListener('change', function () {
            let product_id = this.dataset.id;
            let quantity = this.value;

            fetch('update_cart.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `product_id=${product_id}&quantity=${quantity}`
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                window.location.reload();  // 重新加载页面
            });
        });
    });
</script>

<!-- 订单结算 -->
<script>
    document.getElementById('checkout-btn').addEventListener('click', function () {
        let btn = this;
        btn.disabled = true; // 禁用按钮，防止重复提交

        fetch('checkout.php', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === "error") {
                if (data.items) {
                    let message = data.message + "\n";
                    data.items.forEach(item => {
                        message += `商品: ${item.product_name} - ${item.message}\n`;
                    });
                    alert(message);
                } else {
                    alert(data.message);
                }
                window.location.reload(); // 只有错误时刷新
            } else if (data.status === "success") {
                window.location.href = 'payment.php';
            }
        })
        .finally(() => btn.disabled = false);
    });
</script>



</body>
</html>
