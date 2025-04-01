<?php
session_start();
session_unset();  // 清除所有 session 变量
session_destroy(); // 销毁 session

// 跳转到登录页面
header("Location: login.php");
exit();
?>
