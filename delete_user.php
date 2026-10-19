<?php
session_start();
include 'auth.php'; // ตรวจสอบการล็อกอินและสิทธิ์ของผู้ใช้
include 'connect.php'; // เชื่อมต่อกับฐานข้อมูล

if (isset($_GET['id']) && isset($_GET['token'])) {
    $user_id = $_GET['id'];
    $token = $_GET['token'];

    // ตรวจสอบ CSRF token
    if (!isset($_SESSION['_token']) || $token !== $_SESSION['_token']) {
        $_SESSION['error'] = "Invalid CSRF token";
        header("Location: user_list.php");
        exit();
    }

    try {
        // ลบข้อมูลจากฐานข้อมูล
        $sql = "DELETE FROM users WHERE id = :user_id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            $_SESSION['success'] = "ลบรายการสำเร็จ";
        } else {
            $errorInfo = $stmt->errorInfo();
            $_SESSION['error'] = "เกิดข้อผิดพลาดในการลบรายการ: " . $errorInfo[2];
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "เกิดข้อผิดพลาดในการลบรายการ: " . $e->getMessage();
    }

    $conn = null;
}

header("Location: user_list.php");
exit();
?>