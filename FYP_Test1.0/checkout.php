<?php
session_start();
include "db_connect.php"; // 连接数据库
$conn = open_connection();

if (isset($_SESSION['user_id'])) {

    $user_id = $_SESSION['user_id'];
    $total_price = 0;
    
    // 获取购物车数据（优先从数据库）
    $cart_items = [];
    $insufficient_stock_items = [];
    $sql = "SELECT cart.product_id, cart.quantity, products.price, products.stock, products.name
            FROM cart 
            JOIN products ON cart.product_id = products.id
            WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // 预先准备更新购物车的 SQL 语句
    $update_cart_sql = "UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?";
    $delete_cart_sql = "DELETE FROM cart WHERE user_id = ? AND product_id = ?";
    $update_stmt = $conn->prepare($update_cart_sql);
    $delete_stmt = $conn->prepare($delete_cart_sql);
    
    while ($row = $result->fetch_assoc()) {
        if ($row['quantity'] > $row['stock']) {
            if ($row['stock'] > 0) {
                // 库存不足但仍有货，更新购物车数量
                $update_stmt->bind_param("iii", $row['stock'], $user_id, $row['product_id']);
                $update_stmt->execute();
                $insufficient_stock_items[] = [
                    "product_name" => $row['name'],
                    "available_stock" => $row['stock'],
                    "message" => "库存不足，数量已调整为剩余库存。"
                ];
            } else {
                // 库存为 0，直接删除该商品
                $delete_stmt->bind_param("ii", $user_id, $row['product_id']);
                $delete_stmt->execute();
                $insufficient_stock_items[] = [
                    "product_name" => $row['name'],
                    "available_stock" => 0,
                    "message" => "库存为 0，商品已从购物车移除。"
                ];
            }
        } else {
            $cart_items[] = $row;
            $total_price += $row['price'] * $row['quantity'];
        }
    }
    
    // 如果有库存不足的商品，返回错误信息
    if (!empty($insufficient_stock_items)) {
        echo json_encode(["status" => "error", "message" => "部分商品库存不足，购物车已更新！", "items" => $insufficient_stock_items]);
        exit;
    }
    
    // 如果购物车为空，返回错误
    if (empty($cart_items)) {
        echo json_encode(["status" => "error", "message" => "购物车为空，无法结算！"]);
        exit;
    }
    
    echo json_encode(["status" => "success"]);
    

// 代表用户未登录，执行以下逻辑
} else {

    // ✅ 用户未登录，检查 SESSION 购物车
    if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
        echo json_encode(["status" => "error", "message" => "购物车为空，无法结算！"]);
        exit;
    }

    $updated_cart = $_SESSION['cart'];
    $insufficient_stock_items = []; // 存储所有库存不足的商品
    $total_price = 0;

    foreach ($updated_cart as $product_id => &$item) {
        // 查询库存
        $sql = "SELECT name, stock, price  FROM products WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        if (!$row) {
            // 如果产品不存在，删除它
            $insufficient_stock_items[] = [
                "product_name" => $item['name'],
                "message" => "此商品目前已下架，商品已从购物车移除。"
            ];
            unset($updated_cart[$product_id]);
            continue;
        }

        // 同步数据库最新价格到购物车
        $item['price'] = $row['price']; 
        $updated_cart[$product_id]['price'] = $row['price']; // 确保更新后的购物车也同步价格

        if ($item['quantity'] > $row['stock']) {
            if ($row['stock'] > 0) {
                // 更新 SESSION 购物车的数量
                $item['quantity'] = $row['stock'];
                $insufficient_stock_items[] = [
                    "product_name" => $row['name'],
                    "available_stock" => $row['stock'],
                    "message" => "库存不足，数量已调整为剩余库存。"
                ];
            } else {
                // 如果库存为 0，删除商品
                unset($updated_cart[$product_id]);
                $insufficient_stock_items[] = [
                    "product_name" => $row['name'],
                    "available_stock" => 0,
                    "message" => "库存为 0，商品已从购物车移除。"
                ];
            }
        }

        $total_price += $item['price'] * $item['quantity'];
    }

    // 释放引用
    unset($item); // 避免后续变量污染

    // 更新 SESSION 购物车
    $_SESSION['cart'] = $updated_cart;

    // 如果有库存不足的商品，统一返回给前端
    if (!empty($insufficient_stock_items)) {
        echo json_encode(["status" => "error", "message" => "部分商品库存不足，购物车已更新！", "items" => $insufficient_stock_items]);
        exit;
    }

    // 如果购物车为空
    if (empty($_SESSION['cart'])) {
        echo json_encode(["status" => "error", "message" => "购物车为空，无法结算！"]);
        exit;
    }

    // 购物车正常
    echo json_encode(["status" => "success"]);


}
?>
