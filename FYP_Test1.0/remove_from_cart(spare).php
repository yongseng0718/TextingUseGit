<?php
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $product_id = $_POST['product_id'];

    if (isset($_SESSION['cart'][$product_id])) {
        unset($_SESSION['cart'][$product_id]);  // 删除该商品
        echo json_encode(["message" => "商品已移除"]);
    } else {
        echo json_encode(["message" => "商品不存在"]);
    }
}
