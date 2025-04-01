<?php
include "db_connect.php";  // 连接数据库

$conn = open_connection();
$sql = "SELECT * FROM products"; // 获取所有产品
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>产品列表</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        .product-card {
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            box-shadow: 2px 2px 10px rgba(0, 0, 0, 0.1);
        }
        .product-card img {
            max-width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="row">
            <?php while ($row = $result->fetch_assoc()): ?>
                <div class="col-md-4 col-lg-3 mb-4"> <!--md（medium）表示 在中等屏幕（≥768px）时，这个列占 4 份宽度（总共 12 份）。 -->
                    <div class="product-card">
                        <img src="<?= $row['image_url']; ?>" alt="<?= $row['name']; ?>">
                        <h5 class="mt-2"><?= $row['name']; ?></h5>
                        <p>$<?= number_format($row['price'], 2); ?></p>
                        <button class="btn btn-primary add-to-cart" 
                                data-id="<?= $row['id']; ?>" 
                                data-name="<?= $row['name']; ?>" 
                                data-price="<?= $row['price']; ?>">
                            加入购物车
                        </button>
                    </div>
                </div>

                <!--                
                <div class="col-md-4 col-lg-3 mb-4">
                    <div class="product-card">
                        <img src="apple.jpg" alt="苹果">
                        <h5 class="mt-2">苹果</h5>
                        <p>$3.50</p>
                        <button class="btn btn-primary add-to-cart" data-id="1" data-name="苹果" data-price="3.50">
                            加入购物车
                        </button>
                    </div>
                </div>                
                -->
            <?php endwhile; ?>
        </div>
    </div>

    <script>
        document.querySelectorAll('.add-to-cart').forEach(button => {
            button.addEventListener('click', function () {
                let product_id = this.dataset.id;
                let product_name = this.dataset.name;
                let product_price = this.dataset.price;

                // 发送请求给 PHP 服务器
                fetch('add_to_cart.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `product_id=${product_id}&product_name=${product_name}&product_price=${product_price}`
                })
                .then(response => response.json())
                .then(data => alert(data.message));
            });
        });
    </script>
</body>
</html>
