<?php
session_start();
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

    <?php if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])): ?>
        <div class="list-group">
            <?php 
                $total_price = 0;
                foreach ($_SESSION['cart'] as $id => $item): 
                    $total_price += $item['price'] * $item['quantity'];
            ?>
                <div class="cart-item">
                    <div>
                        <h5><?= $item['name']; ?></h5>
                        <p>单价: $<?= number_format($item['price'], 2); ?> | 数量: <?= $item['quantity']; ?></p>
                    </div>
                    <div>
                        <button class="btn btn-danger remove-from-cart" data-id="<?= $id; ?>">❌ 删除</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <h4 class="mt-3">总价: $<?= number_format($total_price, 2); ?></h4>
        <button class="btn btn-success mt-2">结算</button>

    <?php else: ?>
        <p>🛍 购物车空空如也，快去添加商品吧！</p>
    <?php endif; ?>
</div>

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

</body>
</html>
