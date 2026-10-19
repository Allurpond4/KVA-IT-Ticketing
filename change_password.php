<?php
session_start();
include 'connect.php'; // เชื่อมต่อกับฐานข้อมูล

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $newPassword = $data['newPassword'];
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    // ตรวจสอบว่าเซสชันมีการตั้งค่า employee_id หรือไม่
    if (!isset($_SESSION['employee_id'])) {
        echo json_encode(['success' => false, 'message' => 'Employee ID not set in session']);
        exit;
    }

    try {
        // เปลี่ยนรหัสผ่านในฐานข้อมูลโดยใช้รหัสพนักงาน
        $sql = "UPDATE users SET password = :password WHERE employee_id = :employee_id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':employee_id', $_SESSION['employee_id']);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update password']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>