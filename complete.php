<?php
session_start();
date_default_timezone_set('Asia/Bangkok'); // ตั้งค่าโซนเวลา
include 'auth.php'; // ตรวจสอบการล็อกอิน
include 'connect.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'C:/inetpub/wwwroot/it_request/libs/PHPMailer/src/Exception.php';
require 'C:/inetpub/wwwroot/it_request/libs/PHPMailer/src/PHPMailer.php';
require 'C:/inetpub/wwwroot/it_request/libs/PHPMailer/src/SMTP.php';

// รับข้อมูลจากคำขอ
$data = json_decode(file_get_contents('php://input'), true);
$issue_id = $data['issue_id'] ?? '';
$detail = $data['detail'] ?? '';
$user_id = $_SESSION['user_id'] ?? '';

if ($issue_id && $user_id) {
    try {
        // ดึงข้อมูลชื่อผู้ใช้และอีเมลจากตาราง users
        $user_sql = "SELECT CONCAT(first_name, ' ', last_name) AS full_name, email FROM users WHERE id = :user_id";
        $user_stmt = $conn->prepare($user_sql);
        $user_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $user_stmt->execute();
        $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);

        if ($user_data) {
            $full_name = $user_data['full_name'];
            $user_email = $user_data['email'];
            $completed_at = date('Y-m-d H:i:s');
            $status = 'Completed';

            // ดึงข้อมูลอีเมลของผู้แจ้งและ title ของ issue
            $reporter_sql = "SELECT u.email AS reporter_email, r.title FROM repair_requests r JOIN users u ON r.requester = CONCAT(u.first_name, ' ', u.last_name) WHERE r.issue_id = :issue_id";
            $reporter_stmt = $conn->prepare($reporter_sql);
            $reporter_stmt->bindParam(':issue_id', $issue_id, PDO::PARAM_INT);
            $reporter_stmt->execute();
            $reporter_data = $reporter_stmt->fetch(PDO::FETCH_ASSOC);
            $reporter_email = $reporter_data['reporter_email'];
            $issue_title = $reporter_data['title'];

            if (!$reporter_email) {
                echo json_encode(['success' => false, 'error' => 'ไม่พบอีเมลของผู้แจ้ง']);
                exit;
            }

            $sql = "UPDATE repair_requests SET status = :status, it_report_complete = :it_report_complete, completed_at = :completed_at, detail = :detail WHERE issue_id = :issue_id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':it_report_complete', $full_name);
            $stmt->bindParam(':completed_at', $completed_at);
            $stmt->bindParam(':detail', $detail);
            $stmt->bindParam(':issue_id', $issue_id);
            if ($stmt->execute()) {
                // ส่งอีเมลแจ้งเตือน
                sendEmailNotification($issue_id, $issue_title, $detail, $full_name, $reporter_email);
                echo json_encode(['success' => true, 'email' => $reporter_email]);
            } else {
                echo json_encode(['success' => false, 'error' => 'ไม่สามารถบันทึกข้อมูลได้: ' . implode(", ", $stmt->errorInfo())]);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'ไม่พบข้อมูลผู้ใช้']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'ไม่มีข้อมูล issue_id หรือ user_id']);
}

$conn = null;

function sendEmailNotification($issue_id, $issue_title, $detail, $full_name, $user_email)
{
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->SMTPDebug = 0;                                       // Disable verbose debug output
        $mail->isSMTP();                                            // Set mailer to use SMTP
        $mail->Host       = 'smtp.gmail.com';                       // Specify main and backup SMTP servers
        $mail->SMTPAuth   = true;                                   //
        $mail->Username   = 'it.kruger.th@gmail.com';               // SMTP username
        $mail->Password   = 'omhjnmumurxzzigk';                     // SMTP password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         // Enable TLS encryption
        $mail->Port       = 587;                                    // TCP port to connect to
        $mail->CharSet    = 'utf-8';

        // Recipients
        $mail->setFrom('it.kruger.th@gmail.com', 'IT Kruger Auto Message');
        $mail->addAddress($user_email, $full_name); // Add a recipient

        // Attach images
        $mail->addEmbeddedImage('C:/inetpub/wwwroot/it_request/it/img/5.png', 'rating5');
        $mail->addEmbeddedImage('C:/inetpub/wwwroot/it_request/it/img/4.png', 'rating4');
        $mail->addEmbeddedImage('C:/inetpub/wwwroot/it_request/it/img/3.png', 'rating3');
        $mail->addEmbeddedImage('C:/inetpub/wwwroot/it_request/it/img/2.png', 'rating2');
        $mail->addEmbeddedImage('C:/inetpub/wwwroot/it_request/it/img/1.png', 'rating1');

        // Content
        $mail->isHTML(true);                                        // Set email format to HTML
        $mail->Subject = 'Issue Completed: ' . $issue_title;

        // HTML Body ที่ปรับให้สวยงาม
        $mail->Body = '
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 10px; background-color: #f9f9f9;">
                <div style="text-align: center; padding-bottom: 20px;">
                    <h2 style="color: #2c3e50; margin: 0;">แจ้งสถานะคำขอเสร็จสมบูรณ์</h2>
                    <p style="color: #7f8c8d; font-size: 14px;">IT Kruger Auto Message</p>
                </div>
                <div style="background-color: #ffffff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <p style="font-size: 16px; color: #34495e;"><strong>เรื่อง:</strong> ' . htmlspecialchars($issue_title) . '</p>
                    <p style="font-size: 16px; color: #34495e;"><strong>หมายเลขคำขอ:</strong> ' . htmlspecialchars($issue_id) . '</p>
                    <p style="font-size: 16px; color: #34495e;"><strong>ดำเนินการโดย:</strong> ' . htmlspecialchars($full_name) . '</p>
                    <p style="font-size: 16px; color: #34495e;"><strong>รายละเอียด:</strong></p>
                    <p style="font-size: 14px; color: #7f8c8d; background-color: #f1f1f1; padding: 10px; border-radius: 5px;">' . nl2br(htmlspecialchars($detail)) . '</p>
                    <p style="font-size: 16px; color: #34495e;">หากมีข้อสงสัย โปรดติดต่อแผนกไอที/p>

                </div>
                <div style="text-align: center; padding-top: 20px;">
                    <p style="font-size: 16px; color: #34495e;">กรุณาให้คะแนนการบริการของเรา:</p>
                    <div style="display: flex; justify-content: center; gap: 10px; padding: 10px 0;">
                        <a href="http://eweb.krugerfan.com/it_request/it/rate.php?issue_id=' . $issue_id . '&rating=1"><img src="cid:rating1" alt="1 star" width="40" height="40" style="border-radius: 5px;"></a>
                        <a href="http://eweb.krugerfan.com/it_request/it/rate.php?issue_id=' . $issue_id . '&rating=2"><img src="cid:rating2" alt="2 stars" width="40" height="40" style="border-radius: 5px;"></a>
                        <a href="http://eweb.krugerfan.com/it_request/it/rate.php?issue_id=' . $issue_id . '&rating=3"><img src="cid:rating3" alt="3 stars" width="40" height="40" style="border-radius: 5px;"></a>
                        <a href="http://eweb.krugerfan.com/it_request/it/rate.php?issue_id=' . $issue_id . '&rating=4"><img src="cid:rating4" alt="4 stars" width="40" height="40" style="border-radius: 5px;"></a>
                        <a href="http://eweb.krugerfan.com/it_request/it/rate.php?issue_id=' . $issue_id . '&rating=5"><img src="cid:rating5" alt="5 stars" width="40" height="40" style="border-radius: 5px;"></a>
                    </div>
                    <p style="font-size: 12px; color: #95a5a6;">คลิกที่ดาวเพื่อให้คะแนน</p>
                </div>
                <div style="text-align: center; padding-top: 20px; border-top: 1px solid #e0e0e0; margin-top: 20px;">
                    <p style="font-size: 12px; color: #7f8c8d;">อีเมลนี้ถูกสร้างโดยระบบอัตโนมัติ กรุณาอย่าตอบกลับ</p>
                    <p style="font-size: 12px; color: #7f8c8d;">© ' . date('Y') . ' IT Kruger Team</p>
                </div>
            </div>';

        // Alt Body (สำหรับ客户端ที่ไม่รองรับ HTML)
        $mail->AltBody = 'The issue with ID ' . $issue_id . ' (' . $issue_title . ') has been completed by ' . $full_name . '. Details: ' . $detail;

        $mail->send();
    } catch (Exception $e) {
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
    }
}
