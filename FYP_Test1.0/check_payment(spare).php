<?php
session_start();
include "db_connect.php"; // 连接数据库
$conn = open_connection();

if (isset($_SESSION['user_id'])) {

    $user_id = $_SESSION['user_id'];
    $total_price = 0;

    // 获取购物车数据（优先从数据库）
    $cart_items = [];
    $sql = "SELECT cart.product_id, cart.quantity, products.price, products.stock 
            FROM cart 
            JOIN products ON cart.product_id = products.id
            WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        if ($row['quantity'] > $row['stock']) {
            echo json_encode(["status" => "error", "message" => "库存不足，结算失败！"]);
            exit;
        }
        $cart_items[] = $row;
        $total_price += $row['price'] * $row['quantity'];
    }

    // 如果购物车为空，返回错误
    if (empty($cart_items)) {
        echo json_encode(["status" => "error", "message" => "购物车为空，无法结算！"]);
        exit;
    }

    // **1️⃣ 创建订单**
    $sql = "INSERT INTO orders (user_id, total_price) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("id", $user_id, $total_price);
    $stmt->execute();
    $order_id = $stmt->insert_id; // 获取新订单 ID

    // **2️⃣ 把购物车商品存入订单详情**
    $sql = "INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)";
    $update_stock_sql = "UPDATE products SET stock = stock - ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $update_stock_stmt = $conn->prepare($update_stock_sql);

    foreach ($cart_items as $item) {
        $stmt->bind_param("iiid", $order_id, $item['product_id'], $item['quantity'], $item['price']);
        $stmt->execute();

        // 扣减库存
        $update_stock_stmt->bind_param("ii", $item['quantity'], $item['product_id']);
        $update_stock_stmt->execute();    
    }

    // **3️⃣ 清空购物车**
    $sql = "DELETE FROM cart WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    // 清除 Session 购物车（如果用户未登录时加入的）
    unset($_SESSION['cart']);

    echo json_encode(["status" => "success", "message" => "订单已提交！"]);

// 代表用户未登录，执行以下逻辑
} else {
    if (isset($_SESSION['cart'][$product_id])) {

        if ($_SESSION['cart'][$product_id]['quantity'] >= $product['stock']) {
            echo json_encode(["message" => "您的购物车已有{$_SESSION['cart'][$product_id]['quantity']}件商品，无法再添加至购物车，因为已超出您的购买限制。"]);
            exit;
        }

        $_SESSION['cart'][$product_id]['quantity'] += 1;

    } else {
        $_SESSION['cart'][$product_id] = [
            'name' => $product_name,
            'price' => $product_price,
            'quantity' => 1
        ];
    }

    echo json_encode(["message" => "商品已加入会话购物车"]);

}
?>
