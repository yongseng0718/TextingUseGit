<?php
session_start();

include "db_connect.php"; // 连接数据库
$conn = open_connection();

$user_id = $_SESSION['user_id'] ?? null; // 获取用户 ID（如果已登录）
$cart_items = [];
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

    // | id | name    | price | quantity | image_url         |
    // |----|---------|-------|----------|-------------------|
    // | 1  | Apple   | 3     | 2        | images/table1.jpg |
    // | 2  | Banana  | 2     | 3        | images/table1.jpg |

    // `fetch_assoc()` 依次获取：
    // $row = ['id' => 1, 'name' => 'Apple', 'price' => 3, 'quantity' => 2];
    // $cart_items[1] = $row;

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>购物车</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }

        body {
            background-color: #f5f5f5;
            padding: 20px;
        }

        .cart-container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .cart-header {
            display: grid;
            padding: 15px 0;
            font-weight: bold;
            color: #666;
            border-bottom: 2px solid #eee;
            margin-bottom: 20px;
        }

        .header-item {
            padding: 0 10px;
        }

        .cart-item {
            display: grid;
            align-items: center;
            padding: 20px 0;
            border-bottom: 3px solid #f5f5f5;
        }

        .product-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 8px;
        }

        .product-name {
            font-weight: 600;
            color: #333;
            padding: 0 15px;
        }

        .product-category {
            color: #666;
            font-size: 0.9em;
            padding: 0 15px;
        }

        .quantity-control {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0 15px;
        }

        .quantity-btn {
            width: 28px;
            height: 28px;
            border: 1px solid #ddd;
            background: transparent;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.3s;
        }

        .quantity-btn:hover {
            border-color: #999;
        }

        .price-column {
            padding: 0 15px;
            text-align: right;
            color: #333;
        }

        .total-price {
            color: #e91e63;
            font-weight: 600;
        }

        .delete-btn {
            background: none;
            border: none;
            color: #999;
            cursor: pointer;
            padding: 8px;
            transition: all 0.3s;
        }

        .delete-btn:hover {
            color: #ff4444;
        }

        .cart-footer {
            margin-top: 30px;
            text-align: right;
            padding-top: 20px;
        }

        .grand-total {
            font-size: 20px;
            color: #333;
            margin-bottom: 20px;
        }

        .checkout-btn {
            padding: 12px 40px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s;
        }

        .checkout-btn:hover {
            background: #45a049;
        }

        /* 新增/修改的样式 */
        .cart-header,
        .cart-item {
            grid-template-columns: 120px 2fr 1fr 150px 1fr 1fr 80px;
            justify-items: center; /* 新增水平居中 */
            align-items: center;   /* 新增垂直居中 */
        }

        .header-item,
        .cart-item > * {
            width: 100%;           /* 新增宽度限制 */
            text-align: center;    /* 强制文本居中 */
            padding: 0 5px;       /* 调整内边距 */
        }

        .quantity-control {
            justify-content: center; /* 按钮组居中 */
        }

        /* 移除原有特定边距 */
        .product-name,
        .product-category {
            padding: 0;
        }

        /* 价格列居中修正 */
        .price-column {
            text-align: center !important;
        }

        /* 隐藏Chrome/Safari的上下箭头 */
        .quantity-input::-webkit-outer-spin-button,
        .quantity-input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        } 
        
        .quantity-input {
            width: 50px;
            height: 30px;
            text-align: center;
            border: 1px solid #ddd;
            margin: 0 5px;
            font-size: 16px;
        }
    </style>

</head>
<body>


    <div class="cart-container">
<?php if (!empty($cart_items)): ?>        
        <!-- 表头保持不变 -->
        <div class="cart-header">
            <div class="header-item">图片</div>
            <div class="header-item">商品名称</div>
            <div class="header-item">类型</div>
            <div class="header-item">数量</div>
            <div class="header-item">单价</div>
            <div class="header-item">总价</div>
            <div class="header-item">操作</div>
        </div>

        <!-- 动态生成商品项 -->
        <?php foreach ($cart_items as $product_id => $item): ?>
            <div class="cart-item" data-product-id="<?= $product_id ?>">

                <img src="<?= htmlspecialchars($item['image_url']) ?>" class="product-image" alt="商品">
                <div class="product-name"><?= htmlspecialchars($item['name']) ?></div>
                <div class="product-category"><?= htmlspecialchars($item['category']) ?></div>
                
                <div class="quantity-control">
                    <button class="quantity-btn" 
                            onclick="updateQuantity(this, -1, <?= $product_id ?>)">&minus;</button>
                    <input  type="number" class="quantity-input"
                            value="<?= $item['quantity'] ?>" min="1"
                            title="Enter quantity"  
                            onchange="handleManualInput(this, <?= $product_id ?>)">
                    <button class="quantity-btn" 
                            onclick="updateQuantity(this, 1, <?= $product_id ?>)">+</button>
                </div>

                <div class="price-column unit-price">¥
                    <span class="unit-price-value"><?= number_format($item['price'], 2, '.', ',') ?></span>
                </div>

                <div class="price-column total-price">¥
                    <span class="total-price-value"><?= number_format($item['price'] * $item['quantity'], 2, '.', ',') ?></span>
                </div>

                <button class="btn btn-danger remove-from-cart">❌ 删除</button>

            </div>
        <?php endforeach; ?>

        <div class="cart-footer">
            <div class="grand-total">总计：¥
                <span id="grandTotal"><?= number_format($total_price, 2) ?></span></div>
            <button id="checkout-btn" class="btn btn-success mt-2">去结算</button>
        </div>
    </div>
<?php else: ?>
    <p>Your shopping cart is empty!</p>
<?php endif; ?>    


  <!-- 遮罩层 -->
  <div id="overlay" class="fixed inset-0 bg-black bg-opacity-50 hidden"></div>

  <!-- 侧边栏 -->
  <div id="drawer"
       class="fixed top-0 right-0 h-full w-full sm:w-1/3 bg-white shadow-lg transform translate-x-full transition-transform p-8 rounded-tl-xl rounded-bl-xl">
      
      <!-- 关闭按钮 -->
      <button id="closeDrawer" class="absolute top-4 right-4 text-gray-500 hover:text-gray-700 text-3xl font-light">×</button>

      <!-- 内容 -->
      <div class="pt-8">
          <h2 class="text-3xl font-bold mb-6 text-gray-900 tracking-tight">Guest Checkout</h2>
          <p class="mb-8 text-gray-600 leading-relaxed">You'll have the opportunity to create a personal account after checkout.</p>
          
          <button id="continueGuest" class="w-full py-3 bg-gray-900 hover:bg-gray-800 text-white rounded-lg transition-colors duration-200 font-medium">
              Continue as Guest
          </button>

          <div class="flex items-center my-8">
            <div class="flex-grow border-t border-gray-400"></div>
            <span class="px-4 text-gray-500">OR</span>
            <div class="flex-grow border-t border-gray-400"></div>
          </div>
          

          <h3 class="text-3xl font-semibold mb-6 text-gray-900">Log in to your account</h3>
          
          <div class="space-y-5">
              <div>
                  <label class="block text-sm font-medium text-gray-800 mb-2">Email</label>
                  <input type="email" placeholder="Enter your email"
                      style="border: 2px solid rgb(230, 230, 230);"
                      class="w-full px-4 py-2.5 rounded-lg focus:ring-2 focus:ring-gray-400 focus:border-gray-500 outline-none transition-all">
              </div>

              <div>
                  <label class="block text-sm font-medium text-gray-800 mb-2">Password</label>
                  <input type="password" placeholder="Enter your password"
                      style="border: 2px solid rgb(230, 230, 230);"
                      class="w-full px-4 py-2.5 rounded-lg focus:ring-2 focus:ring-gray-400 focus:border-gray-500 outline-none transition-all">
              </div>

              <button style="border: 2px solid rgb(230, 230, 230);"
                      onmouseover="this.style.backgroundColor='#e8e8e8'" 
                      onmouseout="this.style.backgroundColor='white'"
                      class="w-full py-3 text-gray-900 rounded-lg transition-all duration-200 font-medium">
                  Log In
              </button>
          </div>
      </div>
  </div>


<!-- 移除商品 -->
<script>
    document.querySelectorAll('.remove-from-cart').forEach(button => {
        button.addEventListener('click', function () {

            // ✅ 获取最近的 .cart-item 父级元素
            let cartItem = this.closest('.cart-item');
            if (!cartItem) return;
            
            let product_id = cartItem.dataset.productId;
            
            fetch('remove_from_cart.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `product_id=${product_id}`
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                window.location.reload();  // 重新加载页面
            });
        });
    });
</script>



<!-- 更改商品数量 -->
<script>
// 加减按钮操作
async function updateQuantity(button, change, productId) {
    const input = button.parentElement.querySelector('.quantity-input');
    let newValue = parseInt(input.value) + change;
    
    // 最小值限制
    if (newValue < 1) newValue = 1;
    
    input.value = newValue;
    await submitQuantityChange(productId, newValue);
}

// 处理手动输入
async function handleManualInput(input, productId) {
    let newValue = parseInt(input.value);
    
    // 输入验证
    if (isNaN(newValue) || newValue < 1) {
        input.value = 1; // 重置为合法值
        newValue = 1;
    }
    
    await submitQuantityChange(productId, newValue);
}

// 统一提交函数（核心逻辑）
async function submitQuantityChange(productId, quantity) {
    try {
        const response = await fetch('update_cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                product_id: productId,
                quantity: quantity
            })
        });

        if (!response.ok) throw new Error('请求失败');

        // ✅ 解析服务器返回的 JSON
        const data = await response.json();

        // 🚨 处理库存不足的情况
        if (data.status === "error") {
            alert(data.message);

            // 获取对应的 input 并锁定最大库存
            const item = document.querySelector(`.cart-item[data-product-id="${productId}"]`);
            if (item) {
                const input = item.querySelector('.quantity-input');
                if (input) {
                    input.value = data.max_stock; // 限制最大库存
                }
                
            }

            quantity = data.max_stock; // 继续让代码执行，确保 UI 更新
        }        
        
        // 更新价格显示
        const item = document.querySelector(`.cart-item[data-product-id="${productId}"]`);
        if (item) {
            // 关键修改点1：解析价格时需移除逗号
            const priceText = item.querySelector('.unit-price .unit-price-value')
                            .textContent
                            .replace(/,/g, ''); // 移除所有逗号
            const price = parseFloat(priceText);

            // 关键修改点2：使用 toLocaleString 格式化总价
            const total = price * quantity;
            item.querySelector('.total-price .total-price-value').textContent = 
                total.toLocaleString('zh-CN', { minimumFractionDigits: 2 });
            
            calculateTotal(); // 更新全局总价
        }
    } catch (error) {
        console.error('更新失败:', error);
        alert('操作失败: ' + error.message);
    }
}

function calculateTotal() {
    let grandTotal = 0;
    document.querySelectorAll('.total-price .total-price-value').forEach(element => {
        let priceText = element.textContent
            .trim()
            .replace(/,/g, ''); // 关键：移除逗号
        let priceValue = parseFloat(priceText);
        if (!isNaN(priceValue)) {
            grandTotal += priceValue;
        }
    });
    
    // 显示总价时自动添加千位分隔符（推荐）
    document.getElementById('grandTotal').textContent = 
        grandTotal.toLocaleString('zh-CN', { minimumFractionDigits: 2 });
}




/*
function calculateTotal() {
    let grandTotal = 0;
    
    document.querySelectorAll('.total-price').forEach(element => {
        let priceText = element.textContent.trim().replace('¥', ''); // 移除¥符号
        let priceValue = parseFloat(priceText); // 转换成浮点数

        if (!isNaN(priceValue)) { // ✅ 确保不是 NaN 才加入
            grandTotal += priceValue;
        }

        //grandTotal += parseFloat(element.textContent.replace('¥',''));
    });
    document.getElementById('grandTotal').textContent = grandTotal.toFixed(2);
}
*/
</script>


<!-- 订单结算 -->
<script>
    document.getElementById('checkout-btn').addEventListener('click', function () {
        let btn = this;
        btn.disabled = true; // 禁用按钮，防止重复提交

        fetch('checkout.php', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === "error") {
                if (data.items) {
                    let message = data.message + "\n";
                    data.items.forEach(item => {
                        message += `商品: ${item.product_name} - ${item.message}\n`;
                    });
                    alert(message);
                } else {
                    alert(data.message);
                }
                window.location.reload(); // 只有错误时刷新
            } else if (data.status === "success") {
                openFunction();
            }
        })
        .finally(() => btn.disabled = false);
    });
</script>


<!-- side panel -->
<script>
    const closeDrawer = document.getElementById("closeDrawer");
    const drawer = document.getElementById("drawer");
    const overlay = document.getElementById("overlay");

    const openFunction = () => {
        drawer.classList.remove("translate-x-full");
        overlay.classList.remove("hidden");
    };

    const closeFunction = () => {
        drawer.classList.add("translate-x-full");
        overlay.classList.add("hidden");
    };

    closeDrawer.addEventListener("click", closeFunction);
    overlay.addEventListener("click", closeFunction);

    document.getElementById("continueGuest").addEventListener("click", () => {
        window.location.href = 'payment.php';
    });

</script>


</body>
</html>
