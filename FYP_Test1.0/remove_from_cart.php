<?php
session_start();
include "db_connect.php"; // 连接数据库
$conn = open_connection(); 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $product_id = $_POST['product_id'];
    $user_id = $_SESSION['user_id'] ?? null; // 获取用户 ID

    if ($user_id) {
        // ✅ 用户已登录，从数据库删除购物车商品
        $sql = "DELETE FROM cart WHERE user_id = ? AND product_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $user_id, $product_id);

        if ($stmt->execute()) {
            echo json_encode(["message" => "商品已从数据库移除"]);
        } else {
            echo json_encode(["message" => "删除失败"]);
        }
    } else {
        // 🚀 用户未登录，从 Session 删除
        if (isset($_SESSION['cart'][$product_id])) {
            unset($_SESSION['cart'][$product_id]); // 删除该商品
            echo json_encode(["message" => "商品已从 Session 移除"]);
        } else {
            echo json_encode(["message" => "商品不存在"]);
        }
    }
}
?>
