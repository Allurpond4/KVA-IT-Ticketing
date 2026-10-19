<?php
session_start();
date_default_timezone_set('Asia/Bangkok'); // ตั้งค่าโซนเวลา
$servername = "localhost";
$username = "root";
$password = "[K]=30159&";
$dbname = "kva_app";
include 'auth.php'; // ตรวจสอบการล็อกอิน

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// รับข้อมูลจากคำขอ
$data = json_decode(file_get_contents('php://input'), true);
$issue_id = $data['issue_id'] ?? '';
$user_id = $_SESSION['user_id'] ?? '';

if ($issue_id && $user_id) {
    // ดึงข้อมูลชื่อผู้ใช้จากตาราง users
    $user_sql = "SELECT CONCAT(first_name, ' ', last_name) AS full_name FROM users WHERE id = ?";
    if ($user_stmt = $conn->prepare($user_sql)) {
        $user_stmt->bind_param('i', $user_id);
        $user_stmt->execute();
        $user_stmt->bind_result($full_name);
        $user_stmt->fetch();
        $user_stmt->close();
    } else {
        echo json_encode(['success' => false, 'error' => 'ไม่สามารถดึงข้อมูลผู้ใช้ได้: ' . $conn->error]);
        exit();
    }

    $accept_date = date('Y-m-d H:i:s');
    $status = 'Completed';
    $completion_date = date('Y-m-d H:i:s');
    $sql = "UPDATE repair_requests SET accept_date = ?, status = ?, it_report_complete = ?, completion_date = ? WHERE issue_id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('sssss', $accept_date, $status, $full_name, $completion_date, $issue_id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'ไม่สามารถบันทึกข้อมูลได้: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'error' => 'ไม่สามารถเตรียมคำสั่ง SQL ได้: ' . $conn->error]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'ไม่มีข้อมูล issue_id หรือ user_id']);
}

$conn->close();
?>