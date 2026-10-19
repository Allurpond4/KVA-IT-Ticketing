<?php
session_start();
include 'connect.php'; // เชื่อมต่อกับฐานข้อมูล

if (isset($_GET['id'])) {
    $issue_id = $_GET['id'];

    try {
        $sql = "DELETE FROM repair_requests WHERE issue_id = :issue_id";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['issue_id' => $issue_id]);
        
        if ($stmt->rowCount() > 0) {
            $_SESSION['success'] = "ลบรายการสำเร็จ";
        } else {
            $_SESSION['error'] = "ไม่พบรายการที่ต้องการลบ";
        }

    } catch (PDOException $e) {
        $_SESSION['error'] = "เกิดข้อผิดพลาดในการลบรายการ: " . $e->getMessage();
    }

    $stmt = null;
    $conn = null;
}

header("Location: repair_requests.php");
exit();
?>