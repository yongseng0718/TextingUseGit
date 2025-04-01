<?php
require 'vendor/autoload.php';

session_start();
include "db_connect.php"; // 连接数据库
$conn = open_connection();

if (!isset($_GET['payment_intent'])) {
    die("<h2 style='color: red; text-align: center;'>支付信息错误！</h2>");
}

$payment_intent_id = $_GET['payment_intent'];

\Stripe\Stripe::setApiKey("sk_test_51QwzM8JTwYbzm823JnGtzMAi6pPgXkyPdPc030ZHx7AlhDR8gIQ77hlNkDEJ3uYwN0liBa5fCc53o1yPniF2yHYF00Kg8Jzq0B");

$paymentIntent = \Stripe\PaymentIntent::retrieve($payment_intent_id);
$status = $paymentIntent->status;
$amount = $paymentIntent->amount / 100;

// 检查 latest_charge 是否存在
if (!empty($paymentIntent->latest_charge)) {
    $charges = \Stripe\Charge::retrieve($paymentIntent->latest_charge);
} else {
    die("<h2 style='color: red; text-align: center;'>未找到支付 Charge 记录！</h2>");
}

$email = $charges->billing_details->email ?? 'N/A';
$full_name = $charges->billing_details->name ?? 'N/A';
$phone = $charges->billing_details->phone ?? 'N/A';
$address_line = $charges->billing_details->address->line1 ?? 'N/A';
$postal_code = $charges->billing_details->address->postal_code ?? 'N/A';
$city = $charges->billing_details->address->city ?? 'N/A';
$state = $charges->billing_details->address->state ?? 'N/A';

// 获取送货地址（如果存在）
$shipping_full_name = $charges->shipping->name ?? 'N/A';
$shipping_phone = $charges->shipping->phone ?? 'N/A';
$shipping_address_line = $charges->shipping->address->line1 ?? 'N/A';
$shipping_postal_code = $charges->shipping->address->postal_code ?? 'N/A';
$shipping_city = $charges->shipping->address->city ?? 'N/A';
$shipping_state = $charges->shipping->address->state ?? 'N/A';

// Stripe 的 order ID
$stripePaymentIntent = $paymentIntent->id; // pi_xxx...
// 付款类型
$payment_method_type = $charges->payment_method_details->type; // 'card', 'grabpay', 'fpx'


if ($status === 'succeeded') {
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
            $cart_items[] = $row;
        }

        $statusPaid = "paid";
        // **1️⃣ 创建订单**
        $sql = "INSERT INTO orders (user_id, email, full_name, phone, address_line, postal_code, city, state, total_price, status, shipping_full_name, shipping_phone, shipping_address_line, shipping_postal_code, shipping_city, shipping_state, stripe_payment_intent, payment_method) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isssssssssssssssss", $user_id, $email, $full_name, $phone, $address_line, $postal_code, $city, $state, $amount, $statusPaid, $shipping_full_name, $shipping_phone, $shipping_address_line, $shipping_postal_code, $shipping_city, $shipping_state, $stripePaymentIntent, $payment_method_type);
        $stmt->execute();
        $order_id = $stmt->insert_id; // 获取新订单 ID

        $_SESSION['order_id'] = $order_id; // 获取 order_id 过后使用

                
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
    
    // 代表用户未登录，执行以下逻辑
    } else {

        if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
            exit;
        }

        $cart_items = $_SESSION['cart'];
        $total_price = 0;

        // 计算总价
        foreach ($cart_items as $item) {
            $total_price += $item['price'] * $item['quantity'];
        }

        $statusPaid = "paid";

    
        // **1️⃣ 创建订单（未登录用户）**
        $sql = "INSERT INTO orders (email, full_name, phone, address_line, postal_code, city, state, total_price, status, shipping_full_name, shipping_phone, shipping_address_line, shipping_postal_code, shipping_city, shipping_state, stripe_payment_intent, payment_method) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssssssssssssss", $email, $full_name, $phone, $address_line, $postal_code, $city, $state, $total_price, $statusPaid, $shipping_full_name, $shipping_phone, $shipping_address_line, $shipping_postal_code, $shipping_city, $shipping_state, $stripePaymentIntent, $payment_method_type);
        $stmt->execute();
        $order_id = $stmt->insert_id;

        $_SESSION['order_id'] = $order_id; // 获取 order_id 过后使用


        // **2️⃣ 把购物车商品存入订单详情**
        $sql = "INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)";
        $update_stock_sql = "UPDATE products SET stock = stock - ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $update_stock_stmt = $conn->prepare($update_stock_sql);

        foreach ($cart_items as $product_id => $item) {
            $stmt->bind_param("iiid", $order_id, $product_id, $item['quantity'], $item['price']);
            $stmt->execute();

            // 扣减库存
            $update_stock_stmt->bind_param("ii", $item['quantity'], $product_id);
            $update_stock_stmt->execute();
        }

        // **3️⃣ 清空 Session 购物车**
        unset($_SESSION['cart']);
    
    }
} else {
    echo "<h2 class='error'> </h2>";
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Success</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>
<body>
    
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<?php if ($status === 'succeeded') : ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        title: '付款成功！',
        html: '您的付款已成功处理。5秒后将自动跳转到您的收据页面',
        icon: 'success',
        timer: 5000, // 5秒后自动关闭
        timerProgressBar: true, // 显示进度条
        showConfirmButton: true, // 显示确认按钮
        confirmButtonText: '立刻跳转', // 按钮文字
        willClose: () => {
            // 5秒后跳转到指定页面
            window.location.href = 'receipt.php'; // 替换为你想要跳转的URL
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // 如果用户点击了“立刻跳转”按钮
            window.location.href = 'receipt.php'; // 替换为你想要跳转的URL
        }
    });
});
</script>

<?php else : ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        title: '付款失败',
        html: '抱歉，您的付款未能成功处理。请检查您的支付信息并重试。',
        icon: 'error', // 使用错误图标
        timer: 5000, // 5秒后自动关闭
        timerProgressBar: true, // 显示进度条
        showConfirmButton: true, // 显示确认按钮
        confirmButtonText: '返回支付页面', // 按钮文字
        willClose: () => {
            // 5秒后跳转到指定页面
            window.location.href = 'payment.php'; // 替换为你想要跳转的URL
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // 如果用户点击了“立刻跳转”按钮
            window.location.href = 'payment.php'; // 替换为你想要跳转的URL
        }
    });
});
</script>

<?php endif; ?>

</body>
</html>
