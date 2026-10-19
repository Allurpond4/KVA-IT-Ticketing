<?php
if (isset($_GET['file'])) {
    $file = urldecode($_GET['file']); // รับค่าไฟล์จาก URL

    // ตรวจสอบว่าไฟล์มีอยู่จริง
    if (file_exists($file)) {
        // กำหนด headers สำหรับการดาวน์โหลดไฟล์
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file));
        readfile($file); // อ่านไฟล์และส่งไปยังผู้ใช้
        exit;
    } else {
        echo 'ไฟล์ไม่พบ';
    }
} else {
    echo 'ไม่มีไฟล์ที่ระบุ';
}
?>