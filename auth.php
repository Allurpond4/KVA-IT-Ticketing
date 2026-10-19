<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// ตรวจสอบว่าผู้ใช้ล็อกอินหรือไม่
if (!isset($_SESSION['user_id'])) {
    // ถ้าไม่ได้ล็อกอิน ให้แสดงข้อความแจ้งเตือนและเปลี่ยนเส้นทางไปยังหน้า login
    $_SESSION['error'] = 'กรุณาล็อกอินก่อนเข้าถึงหน้านี้';
    header('Location: http://eweb.krugerfan.com/');
        exit();
}

?>