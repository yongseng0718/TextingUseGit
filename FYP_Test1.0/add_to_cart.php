<?php
session_start();
include "db_connect.php";  // 连接数据库
$conn = open_connection();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $product_id = $_POST['product_id'];
    $product_name = $_POST['product_name'];
    $product_price = $_POST['product_price'];

    // 查询商品库存
    $sql = "SELECT stock FROM products WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();    

    if ($product['stock'] == 0) {
        echo json_encode(["message" => "该商品当前缺货，请稍后再尝试"]);
        exit;
    }

    if (!$product) {
        echo json_encode(["message" => "商品不存在"]);
        exit;
    }

    // 检查用户是否已登录
    if (isset($_SESSION['user_id'])) {
        // 用户已登录，将商品存入数据库
        $user_id = $_SESSION['user_id'];

        // 检查购物车中是否已有该商品
        $sql = "SELECT * FROM cart WHERE user_id = ? AND product_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $user_id, $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $cartProduct = $result->fetch_assoc(); 

        if ($cartProduct && $cartProduct['quantity'] >= $product['stock']) {
            echo json_encode(["message" => "您的购物车已有{$cartProduct['quantity']}件商品，无法再添加至购物车，因为已超出您的购买限制。"]);
            exit;
        }
        
        if ($result->num_rows > 0) {
            // 商品已存在，更新数量
            $sql = "UPDATE cart SET quantity = quantity + 1 WHERE user_id = ? AND product_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $user_id, $product_id);
            $stmt->execute();
        } else {
            // 商品不存在，插入新记录
            $sql = "INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, 1)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $user_id, $product_id);
            $stmt->execute();
        }

        $stmt->close();
        $conn->close();

        echo json_encode(["message" => "商品已加入数据库购物车"]);
    } else {
        // 用户未登录，使用 Session 购物车
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        if (isset($_SESSION['cart'][$product_id])) {

            if ($_SESSION['cart'][$product_id]['quantity'] >= $product['stock']) {
                echo json_encode(["message" => "您的购物车已有{$_SESSION['cart'][$product_id]['quantity']}件商品，无法再添加至购物车，因为已超出您的购买限制。"]);
                exit;
            }

            $_SESSION['cart'][$product_id]['quantity'] += 1;

        } else {
            $_SESSION['cart'][$product_id] = [
                'id' => $product_id,
                'name' => $product_name,
                'price' => $product_price,
                'quantity' => 1
            ];
        }

        echo json_encode(["message" => "商品已加入会话购物车"]);
    }
}
?>
