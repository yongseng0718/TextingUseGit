<?php
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $product_id = $_POST['product_id'];
    $product_name = $_POST['product_name'];
    $product_price = $_POST['product_price'];
    
    // 初始化购物车 Session
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    // 检查商品是否已在购物车
    if (isset($_SESSION['cart'][$product_id])) {
        $_SESSION['cart'][$product_id]['quantity'] += 1;  // 数量 +1
    } else {
        $_SESSION['cart'][$product_id] = [
            'name' => $product_name,
            'price' => $product_price,
            'quantity' => 1
        ];
    }

    echo json_encode(["message" => "商品已加入购物车"]);
}
?>