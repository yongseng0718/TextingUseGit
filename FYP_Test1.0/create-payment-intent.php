<?php
require 'vendor/autoload.php';

session_start();

include "db_connect.php"; // 连接数据库
$conn = open_connection();

$user_id = $_SESSION['user_id'] ?? null; // 获取用户 ID（如果已登录）
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

\Stripe\Stripe::setApiKey("sk_test_51QwzM8JTwYbzm823JnGtzMAi6pPgXkyPdPc030ZHx7AlhDR8gIQ77hlNkDEJ3uYwN0liBa5fCc53o1yPniF2yHYF00Kg8Jzq0B");

$paymentIntent = \Stripe\PaymentIntent::create([
    'amount' => $total_price * 100, // Stripe 需要知道你要收多少钱
    'currency' => 'myr', //：必须指定，例如 "myr"
    'payment_method_types' => ['card', 'fpx', 'grabpay','link']
]);

echo json_encode([
    'clientSecret' => $paymentIntent->client_secret,
    'paymentIntentId' => $paymentIntent->id // 返回 PaymentIntent ID
]);
?>
