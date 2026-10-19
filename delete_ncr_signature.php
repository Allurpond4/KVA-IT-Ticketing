<?php
session_start(); // เริ่มต้น session (ถ้ายังใช้ session อื่นๆ)
require_once 'connect.php'; // รวมไฟล์การเชื่อมต่อฐานข้อมูล



if (isset($_GET['id'])) {
    $user_id = $_GET['id'];



    try {
        // เริ่ม Transaction
        $conn->beginTransaction();



        // อัปเดต ot_signature_path เป็น NULL โดยไม่ลบไฟล์
        $stmt = $conn->prepare("UPDATE users SET ncr_signature_path = NULL WHERE id = :user_id");
        $stmt->execute(['user_id' => $user_id]);



        $conn->commit();
        echo json_encode(["status" => "success", "message" => "ลบลายเซ็น OT สำเร็จ"]);
    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(["status" => "error", "message" => "เกิดข้อผิดพลาด: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "ไม่พบ ID ผู้ใช้"]);
}
?>