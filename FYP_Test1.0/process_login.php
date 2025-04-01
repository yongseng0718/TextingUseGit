<?php
session_start();
include "db_connect.php";  // 连接数据库

$conn = open_connection();
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST["email"];
    $password = $_POST["password"];

    // 查询用户信息
    $sql = "SELECT id, password FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        $user_id = $row["id"];

        // 验证密码
        if (password_verify($password, $row["password"])) {
            $_SESSION["user_id"] = $user_id;  // 登录成功

            // **STEP 1: 检查是否有购物车数据**
            if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
                foreach ($_SESSION['cart'] as $product_id => $item) {
                    $product_name = $item['name'];
                    $product_price = $item['price'];
                    $quantity = $item['quantity'];

                    // **STEP 1.1: 获取产品库存**
                    $sql = "SELECT stock FROM products WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $product_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $product = $result->fetch_assoc();

                    if (!$product) {
                        continue; // 商品不存在，跳过
                    }

                    $stock = $product['stock'];

                    // **STEP 2: 检查数据库是否已有相同商品**
                    $sql = "SELECT * FROM cart WHERE user_id = ? AND product_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ii", $user_id, $product_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $cartProduct = $result->fetch_assoc();

                    $existing_quantity = $cartProduct ? $cartProduct['quantity'] : 0;

                    $new_quantity = $existing_quantity + $quantity; // 购物车 + SESSION 当中的

                    if ($new_quantity > $stock) {
                        $new_quantity = $stock;  // 不能超过库存
                    }

                    if ($existing_quantity > 0) {
                        // **STEP 3: 已存在商品 -> 只更新数量**
                        $sql = "UPDATE cart 
                                SET quantity = ?
                                WHERE user_id = ? AND product_id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("iii", $new_quantity, $user_id, $product_id);
                        $stmt->execute();
                    } else {
                        // **STEP 4: 新增商品到数据库**
                        $sql = "INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("iii", $user_id, $product_id, $new_quantity);
                        $stmt->execute();
                    }
                }

                // **STEP 5: 清空 Session 购物车**
                unset($_SESSION['cart']);
            }

            header("Location: cart.php");
            exit();
            echo json_encode(["message" => "登录成功，购物车已同步"]);
        } else {
            echo "<script>alert('邮箱或密码错误！'); window.location.href='login.php';</script>";
        }
    } else {
        echo json_encode(["error" => "用户不存在"]);
    }
}

?>