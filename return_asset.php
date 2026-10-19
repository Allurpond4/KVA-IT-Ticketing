<?php
session_start();
header('Content-Type: application/json');
include 'connect.php';

if ($_SESSION['user_type'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'ไม่มีสิทธิ์']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$asset_id = $input['asset_id'] ?? 0;

if (!$asset_id) {
    echo json_encode(['success' => false, 'message' => 'ไม่พบ ID อุปกรณ์']);
    exit();
}

try {
    $conn->beginTransaction();

    // อัปเดตวันที่คืน โดยใช้ GETDATE() แทน NOW()
    $sql = "UPDATE asset_assignments 
            SET returned_date = GETDATE() 
            WHERE asset_id = :asset_id AND returned_date IS NULL";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':asset_id', $asset_id, PDO::PARAM_INT);
    $stmt->execute();

    // อัปเดตสถานะเครื่องกลับเป็น Available
    $sql_update = "UPDATE pc_assets SET status = 'Available' WHERE id = :asset_id";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bindParam(':asset_id', $asset_id, PDO::PARAM_INT);
    $stmt_update->execute();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'คืนเครื่องสำเร็จ']);
} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
?>