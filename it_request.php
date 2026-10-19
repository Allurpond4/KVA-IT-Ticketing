<?php
session_start(); // เริ่มต้น session ก่อนที่จะมีการส่งข้อมูลใดๆ

// ตรวจสอบ CSRF token
if (!isset($_POST['_token']) || $_POST['_token'] !== $_SESSION['_token']) {
    die('Invalid CSRF token');
}

include 'connect.php';
include 'auth.php'; // ตรวจสอบการล็อกอิน

// ฟังก์ชันสร้าง ID อัตโนมัติ
function generateIssueId($conn) {
    $sql = "SELECT issue_id FROM repair_requests ORDER BY issue_id DESC OFFSET 0 ROWS FETCH NEXT 1 ROWS ONLY";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $lastId = $stmt->fetch(PDO::FETCH_ASSOC)['issue_id'] ?? 'ISN_00000';

    // แยกส่วนตัวเลขออกจาก ID ล่าสุด
    $number = (int)substr($lastId, 4);
    $newNumber = str_pad($number + 1, 5, '0', STR_PAD_LEFT);

    return 'ISN_' . $newNumber;
}

// สร้างค่า issue_id อัตโนมัติ
$issue_id = generateIssueId($conn);

// เตรียมข้อมูลสำหรับการเพิ่มลงในฐานข้อมูล
$title = $_POST['title'];
$description = $_POST['description'];
$requester = $_POST['requester'];
$priority = $_POST['priority'];
$site = $_POST['site'];
$department = $_POST['department'];
$attachment = $_FILES['attachment']['name'];
$contact = $_POST['phone_number']; // เพิ่มข้อมูลเบอร์ติดต่อ
$status = 'Pending'; // ตั้งค่าสถานะเป็น Pending

// อัพโหลดไฟล์ต้นฉบับ
if ($attachment) {
    $target_dir = "uploads/";
    $target_file = $target_dir . basename($_FILES["attachment"]["name"]);
    move_uploaded_file($_FILES["attachment"]["tmp_name"], $target_file);
}

// เพิ่มข้อมูลลงในฐานข้อมูล
$sql = "INSERT INTO repair_requests (issue_id, title, description, requester, priority, site, department, attachment, contact, status, created_at, updated_at)
        VALUES (:issue_id, :title, :description, :requester, :priority, :site, :department, :attachment, :contact, :status, GETDATE(), GETDATE())";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':issue_id', $issue_id);
$stmt->bindParam(':title', $title);
$stmt->bindParam(':description', $description);
$stmt->bindParam(':requester', $requester);
$stmt->bindParam(':priority', $priority);
$stmt->bindParam(':site', $site);
$stmt->bindParam(':department', $department);
$stmt->bindParam(':attachment', $attachment);
$stmt->bindParam(':contact', $contact);
$stmt->bindParam(':status', $status);

if ($stmt->execute()) {
    $_SESSION['success'] = "บันทึกข้อมูลเรียบร้อยแล้ว";

    // ✅ เพิ่มโค้ดแจ้งเตือนเข้า Microsoft Teams ✅
    $webhookUrl = "https://defaultf79edb239a2f4d67a6fef6a55a6a34.60.environment.api.powerplatform.com:443/powerautomate/automations/direct/workflows/5ed97e924fa446a5a867fbe583acf438/triggers/manual/paths/invoke?api-version=1&sp=%2Ftriggers%2Fmanual%2Frun&sv=1.0&sig=JtNIhmwk0VNlCnC3cdXewoOTdCZFKBn-RKp3CEl5Kss"; // ใส่ URL Webhook ของคุณ

    $message = [
    "type" => "AdaptiveCard",
    "version" => "1.4",
    "body" => [
        [
            "type" => "TextBlock",
            "text" => "🔧🔧 มีคำขอแจ้งซ่อมใหม่! 🔧🔧",
            "weight" => "Bolder",
            "size" => "Large",
            "color" => "Attention",
            "wrap" => true
        ],
        [
            "type" => "FactSet",
            "facts" => [
                [
                    "title" => "เลขที่แจ้งซ่อม:",
                    "value" => $issue_id
                ],
                [
                    "title" => "ชื่อเรื่อง:",
                    "value" => $title
                ],
                [
                    "title" => "รายละเอียด:",
                    "value" => $description
                ],
                [
                    "title" => "ผู้แจ้ง:",
                    "value" => $requester
                ],
                [
                    "title" => "ความเร่งด่วน:",
                    "value" => $priority
                ],
                [
                    "title" => "แผนก:",
                    "value" => $department
                ],
                [
                    "title" => "สถานที่:",
                    "value" => $site
                ],
                [
                    "title" => "เบอร์ติดต่อ:",
                    "value" => $contact
                ],
                [
                    "title" => "สถานะ:",
                    "value" => $status
                ]
            ]
        ],
        [
            "type" => "TextBlock",
            "text" => "http://eweb.krugerfan.com/",
            "wrap" => true,
            "spacing" => "Medium"
        ]
    ]
];


    // กำหนดเส้นทางไฟล์ใบรับรอง SSL (ถ้าจำเป็น)
    $certPath = $_SERVER['DOCUMENT_ROOT'] . '\it_request\it\certificate\cacert.pem';

    // ส่งข้อมูลไปยัง Webhook
    $ch = curl_init($webhookUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
    curl_setopt($ch, CURLOPT_CAINFO, $certPath); // ตรวจสอบ SSL (ถ้าใช้ใบรับรอง)

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        error_log("cURL Error: " . curl_error($ch));
    }
    curl_close($ch);

} else {
    $_SESSION['error'] = "Error: " . $stmt->errorInfo()[2];
}

$conn = null;

// เปลี่ยนเส้นทางกลับไปยังไฟล์ repair_requests.php
header("Location: repair_requests.php");
exit();
?>
