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

        <label>全名：</label>
        <input type="text" name="full_name" required>

        <label>电话号码：</label>
        <input type="tel" name="phone" required>

        <label>公司名称（可选）：</label>
        <input type="text" name="company">

        <label>街道地址：</label>
        <input type="text" name="address_line" required>

        <div class="address-row">
            <div class="address-group">
                <label>邮政编码：</label>
                <input type="text" name="postal_code" required>
            </div>

            <div class="address-group">
                <label>县/地区：</label>
                <input type="district" name="district" required>
            </div>
        </div>
    
        <div class="address-row">
            <div class="address-group">
                <label for="state">州/省：</label>
                    <select name="state" id="state" required>
                        <option value="">请选择州/省</option>
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
            <label>街道地址：</label>
            <input type="text" name="shipping_address_line">

            <div class="address-row">
                <div class="address-group">
                    <label>邮政编码：</label>
                    <input type="text" name="shipping_postal_code">
                </div>
                <div class="address-group">
                    <label>县/地区：</label>
                    <input type="text" name="shipping_district">
                </div>
            </div>
            <div class="address-row">
                <div class="address-group">
                    <label for="shipping_state">州/省：</label>
                    <select name="shipping_state" id="shipping_state">
                        <option value="">请选择州/省</option>
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
        <p>商品名称: <span id="item-name"></span></p>
        <p>价格: RM <span id="price"></span></p>

        <h3>支付信息</h3>

            <div id="link-authentication-element"></div>
            <div id="payment-element"></div>
            <button id="submit-button">立即支付</button>
            <div id="error-message" style="color: #dc3545; margin-top: 10px;"></div>
        
    </div>
    </form>
</div>


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
    
    fetch("create-payment-intent.php?order_id=<?php echo $_GET['order_id']; ?>")
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

            document.getElementById("item-name").innerText = data.item_name;
            document.getElementById("price").innerText = (data.price / 100).toFixed(2);

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
                        city: document.querySelector('[name="state"]').value,
                        state: document.querySelector('[name="district"]').value,
                        postal_code: document.querySelector('[name="postal_code"]').value,
                        country: 'MY' // 强制设置为马来西亚
                    }
                };

                const { error } = await stripe.confirmPayment({
                    elements,
                    confirmParams: {
                        return_url: `http://localhost/FYP_Test2.0_Stripe/success.php?payment_intent=${data.paymentIntentId}`,
                        payment_method_data: {
                            billing_details: billingDetails
                        }
                    }
                });

                if (error) {
                    document.getElementById("error-message").innerText = error.message;
                }
            });
        });


</script>

</body>
</html>