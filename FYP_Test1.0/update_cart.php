<?php
session_start();
include "db_connect.php"; // 连接数据库
$conn = open_connection();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $product_id = $_POST['product_id'];
    $quantity = intval($_POST['quantity']); // 确保数量是整数
    $user_id = $_SESSION['user_id'] ?? null; // 获取用户 ID

    if ($quantity <= 0) {
        echo json_encode(["status" => "error", "message" => "数量必须大于 0"]);
        exit;
    }

    // 查询商品库存
    $sql = "SELECT stock FROM products WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();

    if (!$product) {
        echo json_encode(["status" => "error", "message" => "商品不存在"]);
        exit;
    }

    $exceededStock = false; // 记录是否超过库存

    if ($quantity > $product['stock']) {
        $quantity = $product['stock'];
        $exceededStock = true; // 标记库存超出
    }

    if ($user_id) {
        // ✅ 用户已登录，更新数据库中的数量
        $sql = "UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iii", $quantity, $user_id, $product_id);

        if ($stmt->execute()) {
            if ($exceededStock) {
                echo json_encode([
                    "status" => "error",
                    "message" => "库存不足，仅剩 {$product['stock']} 件， 数量已调整为剩余库存。",
                    "max_stock" => $product['stock']
                ]);
            } else {
                echo json_encode(["status" => "success", "message" => "购物车已更新"]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "更新失败"]);
        }

    } else {
        // 🚀 用户未登录，更新 Session 购物车数量
        if (isset($_SESSION['cart'][$product_id])) {
            $_SESSION['cart'][$product_id]['quantity'] = $quantity;
            
            if ($exceededStock) {
                $response = [
                    "status" => "error",
                    "message" => "库存不足，仅剩 {$product['stock']} 件，数量已调整为剩余库存。",
                    "max_stock" => $product['stock']
                ];
            } else {
                $response = ["status" => "success", "message" => "购物车已更新"];
            }
        } else {
            $response = ["status" => "error", "message" => "商品不存在于购物车中"];
        }
        echo json_encode($response);
    }
}
?>
