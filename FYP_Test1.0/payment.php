<?php
session_start();

include "db_connect.php"; // 连接数据库
$conn = open_connection();

$user_id = $_SESSION['user_id'] ?? null; // 获取用户 ID（如果已登录）
$total_price = 0;

if ($user_id) {
    // ✅ 用户已登录，从数据库获取购物车
    $sql = "SELECT products.id, products.name, products.price, products.image_url, 
                   categories.name AS category, cart.quantity 
            FROM cart 
            JOIN products ON cart.product_id = products.id
            JOIN categories ON products.category_id = categories.id
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
    $updated_cart = [];

    foreach ($cart_items as $product_id => $item) {
        // 额外查询数据库，获取 image_url 和 category
        $sql = "SELECT image_url, categories.name AS category 
                FROM products 
                JOIN categories ON products.category_id = categories.id 
                WHERE products.id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $extra_data = $result->fetch_assoc();

        // 合并数据
        $updated_cart[$product_id] = array_merge($item, [
            'image_url' => $extra_data['image_url'] ?? 'default.jpg',
            'category' => $extra_data['category'] ?? 'Uncategorized'
        ]);        


        $total_price += $updated_cart[$product_id]['price'] * $updated_cart[$product_id]['quantity'];
    }

    $cart_items = $updated_cart;

}

?>

<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <title>自定义 Stripe 支付</title>
    <script src="https://js.stripe.com/v3/"></script>
<style>
    body { font-family: Arial, sans-serif; }
    .container {
        display: flex;
        width: 80%;
        margin: auto;
        padding: 20px;
        border: 1px solid #ddd;
        gap: 20px;
    }
    .form-container {
        display: flex;
        width: 100%;
        gap: 20px; /* 保持原有间距 */
    }
    .left, .right {
        flex: 1;
        padding: 20px;
        border-radius: 5px;
    }
    .left {
        background-color: #f9f9f9;
    }
    .right {
        background-color: #fff;
        border-left: 1px solid #ddd;
        
    }
    #payment-element { margin: 20px 0; }
    button { 
        background-color: #5469d4; 
        color: white; 
        padding: 15px; 
        border: none; 
        cursor: pointer; 
        width: 100%;
        font-size: 16px;
        border-radius: 8px;
    }
    .address-row {
        display: flex;
        gap: 25px;
        margin: 10px 0;
    }
    .address-group {
        flex: 1;
    }
    input, select {
        width: 100%;
        padding: 8px;
        margin: 5px 0 15px;
        border: 1px solid #ddd;
        border-radius: 4px;
        box-sizing: border-box;
    }
    label {
        font-weight: bold;
        font-size: 14px;
    }
    input[type="checkbox"] {
        width: auto; /* 避免 checkbox 占据整行 */
    }
    h2 { 
        margin-top: 0;
        color: #333;
        border-bottom: 2px solid #eee;
        padding-bottom: 10px;
    }
    .hidden {
        display: none;
    }

    /* 添加到原有的style中 右边 cart 商品的部分*/
    .cart-item {
        padding: 15px;
        margin-bottom: 20px;
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        transition: transform 0.2s ease;
    }
    .cart-item:hover {
        transform: translateY(-2px);
    }
    .product-image {
        width: 80px;
        height: 80px;
        object-fit: cover;
        border-radius: 4px;
        margin-right: 15px;
        float: left;
    }
    .cart-item h5 {
        font-size: 16px;
        color: #333;
        margin: 5px 0 8px;
        padding-top: 8px;
    }
    .cart-item p {
        clear: both;
        margin: 8px 0;
        color: #666;
        font-size: 14px;
        line-height: 1.6;
    }
    .price-details {
        display: flex;
        gap: 15px;
        margin: 10px 0;
    }
    .price-item {
        background: #f8f9fa;
        padding: 8px 12px;
        border-radius: 4px;
        flex: 1;
    }
    .price-item span {
        display: block;
        font-size: 13px;
        color: #666;
    }
    .price-item strong {
        font-size: 14px;
        color: #333;
    }
    .total-price {
        font-size: 18px;
        color: #d32f2f;
        font-weight: 700;
        margin: 15px 0;
        padding-top: 15px;
        border-top: 1px solid #eee;
    }

    /* 添加/修改以下样式到原有CSS中  右边付款的部分*/
    .total-wrapper {
        margin: 25px 0 15px;
        padding: 20px;
        background: #ffffff;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        border-top: 2px solid #5469d4;
    }

    .total-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 18px;
    }

    .total-label {
        color: #666;
        font-weight: 600;
    }

    .total-amount {
        color: #d32f2f;
        font-weight: 700;
        font-size: 24px;
        letter-spacing: -0.5px;
    }

    .total-currency {
        font-size: 0.8em;
        margin-right: 2px;
        vertical-align: super;
    }

    .input-hint {
    display: block;
    color: gray;
    font-size: 13px;
    margin-top: -5px; /* 让提示文字稍微上移，避免离输入框太远 */
    margin-bottom: 10px; /* 和下一个输入框保持适当间距 */
    }

    #submit-button:hover {
    background: #4054b2;
    }

</style>
</head>
<body>

<div class="container">
    <!-- 左边：账单信息表单 -->
    <form id="payment-form" class="form-container">
    <div class="left">
        <h2>账单信息</h2>
        
        <label>电子邮件地址：</label>
        <input type="email" name="email" required>
        <small class="input-hint">Please make sure your email address is correct. </small>

        <label>全名：</label>
        <input type="text" name="full_name" required>

        <label>电话号码：</label>
        <input type="tel" name="phone" required>

        <label>街道地址：</label>
        <input type="text" name="address_line" required>

        <div class="address-row">
            <div class="address-group">
                <label>邮政编码：</label>
                <input type="text" name="postal_code" required>
            </div>

            <div class="address-group">
                <label>县/地区：</label>
                <input type="text" name="city" required>
            </div>
        </div>
    
        <div class="address-row">
            <div class="address-group">
                <label for="state">州/省：</label>
                    <select name="state" id="state" required>
                        <option value="" disabled selected>请选择州/省</option>
                        <option value="Johor">Johor</option>
                        <option value="Kedah">Kedah</option>
                        <option value="Kelantan">Kelantan</option>
                        <!-- ... -->
                    </select>
            </div>
        </div>

        <label>
            <input type="checkbox" id="ship-different"> 送货至不同地址？
        </label>

        <div id="shipping-address" class="hidden">

        <h2>送货地址</h2>

            <label>全名：</label>
            <input type="text" name="shipping_full_name">

            <label>电话号码：</label>
            <input type="tel" name="shipping_phone">

            <label>街道地址：</label>
            <input type="text" name="shipping_address_line">

            <div class="address-row">
                <div class="address-group">
                    <label>邮政编码：</label>
                    <input type="text" name="shipping_postal_code">
                </div>
                <div class="address-group">
                    <label>县/地区：</label>
                    <input type="text" name="shipping_city">
                </div>
            </div>

            <div class="address-row">
                <div class="address-group">
                    <label for="shipping_state">州/省：</label>
                    <select name="shipping_state" id="shipping_state">
                        <option value="" disabled selected>请选择州/省</option>
                        <option value="Johor">Johor</option>
                        <option value="Kedah">Kedah</option>
                        <option value="Kelantan">Kelantan</option>
                    </select>
                </div>
            </div>
        </div>

    </div>

    <!-- 右边：支付详情 -->
    <div class="right">

        <h2>付款详情</h2>

        <?php if (!empty($cart_items)): ?>
        <div class="list-group">
            <?php foreach ($cart_items as $id => $item): ?>
                <?php $product_total = $item['price'] * $item['quantity']; ?>
                    <div class="cart-item">
                        <img src="<?= htmlspecialchars($item['image_url']) ?>" class="product-image" alt="商品">
                        <h5><?= htmlspecialchars($item['name']); ?></h5>
                        <div class="price-details">
                            <div class="price-item">
                                <span>单价</span>
                                <strong>$<?= number_format($item['price'], 2); ?></strong>
                            </div>
                            <div class="price-item">
                                <span>数量</span>
                                <strong><?= $item['quantity']; ?></strong>
                            </div>
                            <div class="price-item">
                                <span>总价</span>
                                <strong>$<?= number_format($product_total, 2); ?></strong>
                            </div>
                        </div>
                    </div>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($_SESSION['cart']) || !empty($cart_items)): ?>
            <div class="total-wrapper">
                <div class="total-container">
                    <span class="total-label">订单总计：</span>
                    <span class="total-amount">
                        <span class="total-currency">RM</span>
                        <?= number_format($total_price, 2); ?>
                    </span>
                </div>
            </div>
        <?php endif; ?>
        
        <?php else: ?>
            <p>🛍 购物车空空如也，无法支付，快去添加商品吧！</p>
        <?php endif; ?>

        <h3>支付信息</h3>

            <div id="link-authentication-element"></div>
            <div id="payment-element"></div>
            <button id="submit-button">立即支付</button>
            <div id="error-message" style="color: #dc3545; margin-top: 10px;"></div>
        
    </div>
    </form>
</div>


<!-- Shipping 按钮 -->
<script>
    document.getElementById('ship-different').addEventListener('change', function() {
        var shippingSection = document.getElementById('shipping-address');
        if (this.checked) {
            shippingSection.classList.remove('hidden');
        } else {
            shippingSection.classList.add('hidden');
        }
    });
</script>

<script>
    const stripe = Stripe("pk_test_51QwzM8JTwYbzm823Kpk5rppfcNI5rYopYmSQ39GZ6GbT0JhVnURzl2DOSUTsBhpEIbEPBro7dds7uF1MnSkHeCjZ00LRHtnJg9");
    
    fetch("create-payment-intent.php")
        .then(res => res.json())
        .then(data => {
            const elements = stripe.elements({ 
                clientSecret: data.clientSecret,
                appearance: {
                    theme: 'stripe',
                    variables: {
                        colorPrimary: '#5469d4',
                        borderRadius: '4px'
                    }
                }
            });

            // 创建支付元素并隐藏国家字段
            const paymentElement = elements.create("payment", {
                fields: {
                    billingDetails: {
                        address: {
                            country: 'never' // 隐藏国家字段
                        }
                    }
                },
                defaultValues: {
                    billingDetails: {
                        address: {
                            country: 'MY' // 默认设置为马来西亚
                        }
                    }
                }
            });

            paymentElement.mount("#payment-element");

            document.getElementById("payment-form").addEventListener("submit", async (e) => {
                e.preventDefault();
                

                // 禁用提交按钮，防止重复提交
                const submitButton = document.getElementById("submit-button");
                submitButton.disabled = true;
                submitButton.innerText = "处理中...";

                
                // 收集账单信息并强制设置国家为马来西亚
                const billingDetails = {
                    name: document.querySelector('[name="full_name"]').value,
                    email: document.querySelector('[name="email"]').value,
                    phone: document.querySelector('[name="phone"]').value,
                    address: {
                        line1: document.querySelector('[name="address_line"]').value,
                        city: document.querySelector('[name="city"]').value,
                        state: document.querySelector('[name="state"]').value,
                        postal_code: document.querySelector('[name="postal_code"]').value,
                        country: 'MY' // 强制设置为马来西亚
                    }
                };  
                

                // 检查 "送货至不同地址"
                const shipDifferentChecked = document.getElementById("ship-different").checked;

                // 先声明变量，避免作用域问题
                let shippingFullName = "";
                let shippingPhone = "";
                let shippingDetails = null;

                if (shipDifferentChecked) {
                    shippingFullName = document.querySelector('[name="shipping_full_name"]').value.trim();
                    shippingPhone = document.querySelector('[name="shipping_phone"]').value.trim();
                    const shippingAddress = document.querySelector('[name="shipping_address_line"]').value.trim();
                    const shippingPostalCode = document.querySelector('[name="shipping_postal_code"]').value.trim();
                    const shippingCity = document.querySelector('[name="shipping_city"]').value.trim();
                    const shippingState = document.querySelector('[name="shipping_state"]').value.trim();

                    // 验证送货地址是否填写完整
                    if (!shippingFullName || !shippingPhone || !shippingAddress || !shippingPostalCode || !shippingCity || !shippingState) {
                        document.getElementById("error-message").innerText = "请填写完整的送货地址信息！";
                        submitButton.disabled = false;
                        submitButton.innerText = "立即支付";
                        return;
                    }

                    // 送货地址（shippingDetails）
                    shippingDetails = {
                        address: {
                            line1: shippingAddress,
                            city: shippingCity,
                            state: shippingState,
                            postal_code: shippingPostalCode,
                            country: 'MY'
                        }
                    };
                }    

                const { error } = await stripe.confirmPayment({
                    elements,
                    confirmParams: {
                        return_url: `http://localhost/FYP_Test1.0/check_payment.php?payment_intent=${data.paymentIntentId}`,
                        payment_method_data: {
                            billing_details: billingDetails
                        },
                        shipping: shipDifferentChecked ? {
                            name: shippingFullName,  // 直接使用提前声明的变量
                            phone: shippingPhone,
                            address: {
                                line1: document.querySelector('[name="shipping_address_line"]').value,
                                city: document.querySelector('[name="shipping_city"]').value,
                                state: document.querySelector('[name="shipping_state"]').value,
                                postal_code: document.querySelector('[name="shipping_postal_code"]').value,
                                country: 'MY'
                            }
                        } : { // 🚨 不要直接使用 billingDetails，而是提取需要的字段
                            name: billingDetails.name,
                            phone: billingDetails.phone,
                            address: billingDetails.address
                        }
                    }
                });

                if (error) {
                    document.getElementById("error-message").innerText = error.message;
                    submitButton.disabled = false;  // ✅ 重新启用按钮
                    submitButton.innerText = "立即支付"; // ✅ 还原按钮文字
                }
            });
        });

</script>

</body>
</html>