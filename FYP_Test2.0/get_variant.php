<?php
include "db_connect.php";
$conn = open_connection();
header('Content-Type: application/json');

$variant_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($variant_id <= 0) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid variant ID']));
}

// 获取变体基础信息
$stmt = $conn->prepare("
    SELECT 
        pv.*, 
        GROUP_CONCAT(vi.image_url SEPARATOR '||') AS extra_images 
    FROM product_variants pv
    LEFT JOIN variant_images vi 
        ON pv.product_variant_id = vi.product_variant_id
    WHERE pv.product_variant_id = ?
    GROUP BY pv.product_variant_id
");
$stmt->bind_param("i", $variant_id);
$stmt->execute();
$result = $stmt->get_result();
$variant = $result->fetch_assoc();
$stmt->close();

if (!$variant) {
    http_response_code(404);
    die(json_encode(['error' => 'Variant not found']));
}

// 处理额外图片（兼容没有额外图片的情况）
$variant['extra_images'] = $variant['extra_images'] ? explode('||', $variant['extra_images']) : [];

echo json_encode([
    'price' => $variant['price'],
    'discount' => $variant['discount'],
    'stock' => $variant['stock'],
    'main_image' => $variant['image_url'],
    'extra_images' => $variant['extra_images'], // $variant['extra_images'] = ["red-shirt1.jpg", "red-shirt2.jpg"];
    'color' => $variant['color']
]);