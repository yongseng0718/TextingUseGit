<?php
session_start();
include "db_connect.php";  // 连接数据库

$conn = open_connection();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST["name"];
    $email = $_POST["email"];
    $password = $_POST["password"];

    // 1️⃣ 检查邮箱是否已存在
    $sql = "SELECT id FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        die("❌ 该邮箱已被注册！<a href='register.php'>返回注册</a>");
    }

    $stmt->close();

    // 2️⃣ 使用 `password_hash()` 加密密码
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // 3️⃣ 插入用户数据
    $sql = "INSERT INTO users (name, email, password) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $name, $email, $hashedPassword);

    if ($stmt->execute()) {
        echo "✅ 注册成功！<a href='login.php'>立即登录</a>";
    } else {
        echo "❌ 注册失败，请重试。";
    }

    $stmt->close();
    $conn->close();
}
?>
