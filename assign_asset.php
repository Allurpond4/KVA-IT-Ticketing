<?php
session_start();
header('Content-Type: application/json');
include 'connect.php';

if ($_SESSION['user_type'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'ไม่มีสิทธิ์']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

$user_id    = $input['user_id'] ?? 0;
$asset_id   = $input['asset_id'] ?? 0;
$condition  = $input['condition'] ?? 'Good';
$remarks    = $input['remarks'] ?? '';

if (!$user_id || !$asset_id) {
    echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ครบถ้วน']);
    exit();
}

try {
    $conn->beginTransaction();

    // INSERT การ assign โดยใช้ GETDATE() แทน NOW()
    $sql = "INSERT INTO asset_assignments 
            (asset_id, user_id, assigned_by, assigned_date, condition_on_assign, remarks)
            VALUES (:asset_id, :user_id, :assigned_by, GETDATE(), :condition, :remarks)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':asset_id', $asset_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':assigned_by', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->bindParam(':condition', $condition);
    $stmt->bindParam(':remarks', $remarks);
    $stmt->execute();

    // อัปเดตสถานะเครื่องเป็น Assigned
    $sql_update = "UPDATE pc_assets SET status = 'Assigned' WHERE id = :asset_id";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bindParam(':asset_id', $asset_id, PDO::PARAM_INT);
    $stmt_update->execute();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Assign เครื่องสำเร็จ']);
} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
?>