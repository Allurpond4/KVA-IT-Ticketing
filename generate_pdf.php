<?php
session_start();
include 'connect.php';
include 'auth.php';

// ตรวจสอบการล็อกอิน
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
// ตรวจสอบสิทธิ์
if ($_SESSION['user_type'] !== 'admin') {
    header('Location: index.php');
    exit();
}

require_once 'C:/inetpub/wwwroot/it_request/libs/TCPDF-main/tcpdf.php';

// รับ user_id จาก URL
$user_id = $_GET['id'];

// ดึงข้อมูลผู้ใช้
$sql = "SELECT * FROM users WHERE id = :user_id";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("ไม่พบข้อมูลผู้ใช้");
}

// สร้าง PDF
class MYPDF extends TCPDF {
    public function Header() {
        $this->SetFont('thsarabunnew', '', 16);
        $this->Cell(0, 10, 'รายละเอียดผู้ใช้ / User Details', 0, 1, 'C');
        $this->SetLineWidth(0.5);
        $this->Line(10, 20, 200, 20); // เส้นขีดด้านบน
    }

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('thsarabunnew', '', 10);
        $this->Cell(0, 10, 'หน้า ' . $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}

$pdf = new MYPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('User Management System');
$pdf->SetAuthor('Admin');
$pdf->SetTitle('User Details');
$pdf->SetMargins(15, 30, 15);
$pdf->SetAutoPageBreak(true, 25);
$pdf->setFontSubsetting(false);
$pdf->AddFont('thsarabunnew', '', 'thsarabunnew.php');
$pdf->SetFont('thsarabunnew', '', 12);

$pdf->AddPage();

// ตรวจสอบ GD หรือ Imagick
$hasImageSupport = extension_loaded('gd') || extension_loaded('imagick');

if ($hasImageSupport) {
    // เนื้อหา PDF
    $html = '<h2>ข้อมูลผู้ใช้ / User Information</h2>';
    $html .= '<table border="1" cellpadding="5" cellspacing="0" style="border-collapse: collapse;">';
    $html .= '<tr><td><strong>ชื่อ-นามสกุล / Full Name</strong></td><td>' . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . '</td></tr>';
    $html .= '<tr><td><strong>อีเมล / Email</strong></td><td>' . htmlspecialchars($user['email']) . '</td></tr>';
    $html .= '<tr><td><strong>เบอร์โทร / Phone</strong></td><td>' . htmlspecialchars($user['phone_number']) . '</td></tr>';
    $html .= '<tr><td><strong>รหัสพนักงาน / Employee ID</strong></td><td>' . htmlspecialchars($user['employee_id']) . '</td></tr>';
    $html .= '<tr><td><strong>แผนก / Department</strong></td><td>' . htmlspecialchars(getDepartmentName($conn, $user['department'])) . '</td></tr>';
    $html .= '<tr><td><strong>บริษัท / Site</strong></td><td>' . htmlspecialchars($user['site']) . '</td></tr>';
    $html .= '<tr><td><strong>สถานะ / Status</strong></td><td>' . htmlspecialchars($user['status']) . '</td></tr>';
    $html .= '</table>';

    if (!empty($user['signature_path']) && file_exists($user['signature_path'])) {
        $html .= '<h3>ลายเซ็น / Signature</h3>';
        // ใช้พาธไฟล์จริงแทนการโหลดเนื้อหาดิบ
        $signaturePath = realpath($user['signature_path']); // แปลงเป็นพาธสัมบูรณ์
        if ($signaturePath) {
            $pdf->Image($signaturePath, 15, $pdf->GetY() + 5, 60, 0, 'PNG', '', '', false, 300, '', false, false, 0, false, false, false);
        } else {
            $html .= '<p>ไม่พบไฟล์ลายเซ็น / Signature file not found</p>';
        }
    } else {
        $html .= '<p>ไม่มีลายเซ็น / No signature available</p>';
    }
} else {
    // หากไม่มี GD หรือ Imagick
    $html = '<h2>ข้อมูลผู้ใช้ / User Information</h2>';
    $html .= '<table border="1" cellpadding="5" cellspacing="0" style="border-collapse: collapse;">';
    $html .= '<tr><td><strong>ชื่อ-นามสกุล / Full Name</strong></td><td>' . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . '</td></tr>';
    $html .= '<tr><td><strong>อีเมล / Email</strong></td><td>' . htmlspecialchars($user['email']) . '</td></tr>';
    $html .= '<tr><td><strong>เบอร์โทร / Phone</strong></td><td>' . htmlspecialchars($user['phone_number']) . '</td></tr>';
    $html .= '<tr><td><strong>รหัสพนักงาน / Employee ID</strong></td><td>' . htmlspecialchars($user['employee_id']) . '</td></tr>';
    $html .= '<tr><td><strong>แผนก / Department</strong></td><td>' . htmlspecialchars(getDepartmentName($conn, $user['department'])) . '</td></tr>';
    $html .= '<tr><td><strong>บริษัท / Site</strong></td><td>' . htmlspecialchars($user['site']) . '</td></tr>';
    $html .= '<tr><td><strong>สถานะ / Status</strong></td><td>' . htmlspecialchars($user['status']) . '</td></tr>';
    $html .= '</table>';
    $html .= '<p><strong>หมายเหตุ:</strong> ไม่สามารถแสดงลายเซ็นได้ กรุณาติดตั้งส่วนขยาย GD หรือ Imagick บนเซิร์ฟเวอร์</p>';
}

$pdf->writeHTML($html, true, false, true, false, '');

// ฟังก์ชันดึงชื่อแผนก
function getDepartmentName($conn, $dept_id) {
    $sql = "SELECT dept_name FROM departments WHERE id = :dept_id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':dept_id', $dept_id, PDO::PARAM_INT);
    $stmt->execute();
    $dept = $stmt->fetch(PDO::FETCH_ASSOC);
    return $dept ? $dept['dept_name'] : 'ไม่ระบุ';
}

$pdf->Output('user_details_' . $user_id . '.pdf', 'I');
?>