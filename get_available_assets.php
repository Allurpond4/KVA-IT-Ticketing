<?php
header('Content-Type: application/json');
include 'connect.php'; // ไฟล์เชื่อมต่อฐานข้อมูลเดิมของคุณ

try {
    $stmt = $conn->prepare("
        SELECT id, asset_tag, serial_number, brand, model ,pc_name
        FROM pc_assets 
        WHERE status = 'Available' 
        ORDER BY pc_name ASC
    ");
    $stmt->execute();
    $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($assets);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>