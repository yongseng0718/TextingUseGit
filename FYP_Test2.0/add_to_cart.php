<?php
session_start();
header('Content-Type: application/json');

include "db_connect.php";
$conn = open_connection();

// 获取 POST 数据
if (!isset($_POST['variant_id']) || !isset($_POST['quantity'])) {
    echo json_encode(["error" => "缺少必要参数"]);
    exit;
}

$product_variant_id = intval($_POST['variant_id']);

// 查询商品变体库存及存在性
$sql = "SELECT pv.stock, pv.price AS product_price, p.name AS product_name,  pv.color
        FROM product_variants pv
        JOIN products p ON pv.product_id = p.product_id 
        WHERE pv.product_variant_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $product_variant_id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();

// 判断商品是否存在以及库存状态
if (!$product) {
    echo json_encode(["error" => "商品不存在"]);
    exit;
}

if ($product['stock'] == 0) {
    echo json_encode(["error" => "该商品当前缺货，请稍后再尝试"]);
    exit;
}


if(isset($_SESSION['user_id'])) {

    $user_id = $_SESSION['user_id'];

    // 检查购物车中是否已有该商品变体(针对已登录用户)
    $sql = "SELECT * FROM cart WHERE user_id = ? AND product_variant_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $product_variant_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $cartProduct = $result->fetch_assoc();

    if ($cartProduct && $cartProduct['quantity'] >= $product['stock']) {
        echo json_encode(["error" => "您的购物车已有{$cartProduct['quantity']}件商品，无法再添加至购物车，因为已超出您的购买限制。"]);
        exit;
    }

    if ($cartProduct) {
        // 商品已存在，更新数量（每次增加1）
        $sql = "UPDATE cart SET quantity = quantity + 1 WHERE user_id = ? AND product_variant_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $user_id, $product_variant_id);
        $stmt->execute();
    } else {
        // 商品不存在，插入新记录，数量初始化为1
        $sql = "INSERT INTO cart (user_id, product_variant_id, quantity, created_at) VALUES (?, ?, 1, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $user_id, $product_variant_id);
        $stmt->execute();
    }

    $stmt->close();
    $conn->close();

    echo json_encode(['success' => true]);

} else {
    // 用户未登录，使用 Session 购物车
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    if (isset($_SESSION['cart'][$product_variant_id])) {

        if ($_SESSION['cart'][$product_variant_id]['quantity'] >= $product['stock']) {
            echo json_encode(["error" => "您的购物车已有{$_SESSION['cart'][$product_variant_id]['quantity']}件商品，无法再添加至购物车，因为已超出您的购买限制。"]);
            exit;
        }

        $_SESSION['cart'][$product_variant_id]['quantity'] += 1;

    } else {
        $_SESSION['cart'][$product_variant_id] = [
            'product_variant_id' => $product_variant_id,
            'name' => $product_name,
            'price' => $product_price,
            'quantity' => 1
        ];
    }

    echo json_encode(["success" => "商品已加入会话购物车"]);

}

?>
