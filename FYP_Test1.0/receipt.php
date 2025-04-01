<?php 
session_start();
include "db_connect.php"; // 连接数据库
$conn = open_connection();

$_SESSION['order_id'] = 17;

// 查询多个字段
$sql = "SELECT *
        FROM orders 
        WHERE order_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['order_id']);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

// 提取数据
$user_id = $row['user_id'];
$email = $row['email'];
$full_name = $row['full_name'];
$phone = $row['phone'];
$address_line = $row['address_line'];
$postal_code = $row['postal_code'];
$city = $row['city'];
$state = $row['state'];
$total_price = $row['total_price'];
$status = $row['status'];

//$created_at = isset($row['created_at']) ? date("d F Y, g:i:s A", strtotime($row['created_at'])) : 'unknown time';
$created_date = isset($row['created_at']) ? date("d F Y", strtotime($row['created_at'])) : 'unknown date';
$created_time = isset($row['created_at']) ? date("g:i:s A", strtotime($row['created_at'])) : 'unknown time';

$shipping_full_name = $row['shipping_full_name'];
$shipping_phone = $row['shipping_phone'];
$shipping_address_line = $row['shipping_address_line'];
$shipping_postal_code = $row['shipping_postal_code'];
$shipping_city = $row['shipping_city'];
$shipping_state = $row['shipping_state'];
$payment_method = $row['payment_method'];
$stripe_payment_intent = $row['stripe_payment_intent'];


// 获取 orders_item 表的数据
$order_items = [];
$sql_items = "SELECT order_items.product_id, order_items.quantity, order_items.price, products.name
              FROM order_items 
              JOIN products ON order_items.product_id = products.id
              WHERE order_items.order_id = ?";

$stmt_items = $conn->prepare($sql_items);
$stmt_items->bind_param("i", $_SESSION['order_id']);
$stmt_items->execute();
$result_items = $stmt_items->get_result();

while ($row_item = $result_items->fetch_assoc()) {
    $order_items[] = $row_item;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <!-- 增加字体图标 -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <style>
    :root {
      --primary-color: #2c5f2d; /* 更深的品牌绿色 */
      --secondary-color: #97bc62; /* 辅助绿色 */
      --accent-color: #f07167; /* 强调色 */
      --text-color: #333;
    }

    body {
      font-family: 'Segoe UI', system-ui, sans-serif;
      line-height: 1.6;
      background: #f8f9fa;
      margin: 0;
      padding: 0;
    }

    /* 设置容器宽度为80%，并使其居中显示 */
    .container {
      width: 80%;
      margin: 20px auto;
      background:rgb(255, 255, 255);
      box-shadow: 0 4px 12px rgba(0,0,0,0.08);
      border-radius: 10px;
      overflow: hidden;
    }

    /* 头部区块 */
    .receipt-header {
      background: var(--primary-color);
      color: white;
      padding: 2rem;
      text-align: center;
      border-bottom: 4px solid var(--secondary-color);
    }

    .store-name {
      font-size: 2.2rem;
      font-weight: 700;
      margin-bottom: 0.5rem;
    }

    .store-contact {
      opacity: 0.9;
      font-size: 0.9em;
    }

    /* 感谢信息 */
    .thank-you {
      padding: 2rem;
      background: #f8f9fa;
      border: 2px dashed var(--secondary-color);
      margin: 2rem;
      border-radius: 8px;
      position: relative;
    }

    .thank-you::before {
      content: "\f058"; /* FontAwesome check icon */
      font-family: "Font Awesome 5 Free";
      font-weight: 900;
      color: var(--secondary-color);
      font-size: 3rem;
      position: absolute;
      left: 50%;
      transform: translateX(-50%);
      top: -28px;
      padding: 0rem 1rem;
      border-radius: 50%; /* 圆形背景 */
    }

    /* 订单信息网格布局 */
    .order-meta {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1.5rem;
      padding: 1.5rem;
      background: #f8f9fa;
      margin: 1rem;
      border-radius: 8px;
    }

    .meta-item {
      text-align: center;
    }

    .meta-label {
      color: var(--primary-color);
      font-weight: 600;
      margin-bottom: 0.5rem;
      font-size: 1.1em;
    }

    .meta-value {
      font-weight: 700;
      color: var(--text-color);
    }

    /* 产品表格优化 */
    .product-table {
      border: 1px solid #dee2e6;
      width: 100%;
      border-collapse: collapse;
    }

    .product-table th {
      background: var(--primary-color);
      color: white;
      padding: 1rem;
      text-transform: uppercase;
      font-size: 0.9em;
      border-right: 1px solid white; /* 添加白色分割线 */
      box-sizing: border-box;
      text-align: center; /* 让所有内容居中对齐 */
    }

    .product-table td {
      padding: 1rem;
      vertical-align: middle;
      border-bottom: 1px solid #dee2e6;
      text-align: center; /* 让所有内容居中对齐 */
    }
    
    .product-table th:last-child {
    border-right: none; /* 最后一个 th 不需要右侧分割线 */
    }

    .product-table tr:nth-child(even) {
      background: #f8f9fa;
    }

    /* 总计区块 */
    .total-section {
      background: #f8f9fa;
      padding: 1.5rem;
      margin: 1rem;
      border-radius: 8px;
    }

    .total-line {
      display: flex;
      justify-content: space-between;
      max-width: 300px;
      margin-left: auto;
      padding: 0.5rem 0;
    }

    .grand-total {
      font-size: 1.4em;
      color: var(--primary-color);
      border-top: 2px solid var(--primary-color);
      padding-top: 1rem;
    }

    /* 地址信息分栏 */
    .address-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 2rem;
      padding: 2rem;
      background: #f8f9fa;
      margin: 1rem;
      border-radius: 8px;
    }

    .address-card {
      background: white;
      padding: 1.5rem;
      border-radius: 8px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.05);
    }

    .address-title {
      color: var(--primary-color);
      border-bottom: 2px solid var(--secondary-color);
      padding-bottom: 0.5rem;
      margin-bottom: 1rem;
      font-weight: 600;
    }

    /* 支付说明 */
    .payment-notice {
      padding: 1.5rem;
      background: #fff3cd;
      color: #856404;
      border-radius: 8px;
      margin: 1rem;
      border-left: 4px solid #ffe082;
    }
  </style>
</head>
<body>

<div class="container">
  <!-- 新增头部 -->
  <header class="receipt-header">
    <div class="store-name">Furniture Gallery</div>
    <div class="store-contact">
      <p>contact@organicstore.com<br>+60 3-1234 5678</p>
    </div>
  </header>

  <div class="thank-you">
    Your order is confirmed!<br>
    <small>We've sent the receipt to <?php echo $email; ?></small>
  </div>

  <!-- 优化后的订单信息 -->
  <section class="order-meta">
    <div class="meta-item">
      <div class="meta-label">Order ID</div>
      <div class="meta-value"><?php echo $stripe_payment_intent; ?></div> <!--  style="margin:0 70px; word-break: break-word;" -->
    </div>
    <div class="meta-item">
      <div class="meta-label">Order Date</div>
      <div class="meta-value">
        <?php echo $created_date; ?>,<br>
        <?php echo $created_time; ?>  
      </div>
    </div>
    <div class="meta-item">
      <div class="meta-label">Total Amount</div>
      <div class="meta-value">RM <?php echo number_format($total_price, 2); ?></div>
    </div>
    <div class="meta-item">
      <div class="meta-label">Payment Method</div>
      <div class="meta-value"><?php echo ucwords(str_replace('_', ' ', $payment_method)); ?></div>
    </div>
  </section>

  <!-- 产品表格 -->
  <section class="order-details"  style="padding: 30px 0 0 0;">
    <table class="product-table">
      <thead>
        <tr>
          <th>Product</th>
          <th>Price</th>
          <th>Qty</th>
          <th>Total</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($order_items as $item): 
            $product_total = $item['quantity'] * $item['price'];
        ?>
          <tr>
            <td data-label="Product"><?php echo $item['name']; ?></td>
            <td data-label="Price">RM <?php echo number_format($item['price'], 2); ?></td>
            <td data-label="Quantity"><?php echo $item['quantity']; ?></td>
            <td data-label="Total">RM <?php echo number_format($product_total, 2); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <!-- 总计区块 -->
  <section class="total-section">
    <div class="total-line">
      <span>Subtotal:</span>
      <span>RM <?php echo number_format($total_price, 2); ?></span>
    </div>
    <div class="total-line">
      <span>Shipping:</span>
      <span>RM15.00</span>
    </div>
    <div class="total-line grand-total">
      <span>Grand Total:</span>
      <span>RM <?php echo number_format($total_price, 2); ?></span>
    </div>
  </section>

  <!-- 地址信息分栏 -->
  <section class="address-grid">
    <div class="address-card">

      <h3 class="address-title">Billing Address</h3>
        <p>
          <?php echo $full_name; ?><br>
          <?php echo $phone; ?><br>
          <?php echo $address_line; ?>,<br>
          <?php echo $postal_code; ?> <?php echo $city; ?>, <?php echo $state; ?>
        </p>
    </div>
    
    <div class="address-card">
      
      <h3 class="address-title">Shipping Address</h3>
      <p>
        <?php if (!empty($shipping_full_name)): ?>
          <?php echo $shipping_full_name; ?><br>
          <?php echo $shipping_phone; ?><br>
          <?php echo $shipping_address_line; ?>,<br>
          <?php echo $shipping_postal_code; ?> <?php echo $shipping_city; ?>, <?php echo $shipping_state; ?>
        <?php else: ?>
          Same as Billing Address
        <?php endif; ?>
      </p>
    
    </div>
  </section>

  <!-- 支付说明 -->
  <div class="payment-notice">
    <i class="fas fa-info-circle"></i> Payment successful! Thank you for your purchase.<br>
    Your order is now being processed, and we will notify you once it is shipped.
  </div>
</div>

</body>
</html>
