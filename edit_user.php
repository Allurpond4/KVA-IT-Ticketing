<?php
session_start(); // เริ่มต้น session

// สร้าง CSRF token และเก็บลง session เพียงครั้งเดียว
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
include 'connect.php';
include 'auth.php'; // ตรวจสอบการล็อกอิน

// ตรวจสอบการล็อกอิน
if (!isset($_SESSION['user_id'])) {
?>
    <!DOCTYPE html>
    <html lang="th">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>ข้อผิดพลาด - กรุณาล็อกอิน</title>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    </head>

    <body class="bg-gray-100 flex items-center justify-center min-h-screen">
        <div class="text-center">
            <script>
                Swal.fire({
                    title: 'ข้อผิดพลาด!',
                    text: 'กรุณาล็อกอินเพื่อเข้าถึงหน้านี้',
                    icon: 'error',
                    confirmButtonText: 'ตกลง'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = 'login.php';
                    }
                });
            </script>
            <p class="text-gray-600 mt-4">กรุณา<a href="login.php" class="text-blue-600 underline">ล็อกอิน</a>เพื่อเข้าถึงหน้านี้</p>
        </div>
    </body>

    </html>
<?php
    exit();
}

// รับค่า user_id จาก URL
$user_info_id = $_GET['id'] ?? null;

// ตรวจสอบ user_id
if ($user_info_id === null) {
    $_SESSION['error'] = "ไม่มี ID ผู้ใช้ที่ระบุ";
    header('Location: user_list.php');
    exit();
}

// ดึงข้อมูลผู้ใช้จากฐานข้อมูล
$sql = "SELECT * FROM users WHERE id = :user_id";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':user_id', $user_info_id, PDO::PARAM_INT);
$stmt->execute();
$user_info = $stmt->fetch(PDO::FETCH_ASSOC);

// ตรวจสอบว่าพบข้อมูลผู้ใช้หรือไม่
if (!$user_info) {
    $_SESSION['error'] = "ไม่พบผู้ใช้ที่ระบุ";
    header('Location: user_list.php');
    exit();
}

// ตรวจสอบว่าผู้ใช้ที่กำลังแก้ไขเป็นผู้ใช้ที่ล็อกอินอยู่หรือไม่ (ถ้าไม่ใช่ admin)
if ($_SESSION['user_type'] !== 'admin' && $_SESSION['user_id'] != $user_info_id) {
?>
    <!DOCTYPE html>
    <html lang="th">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>ข้อผิดพลาด - ไม่มีสิทธิ์</title>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    </head>

    <body class="bg-gray-100 flex items-center justify-center min-h-screen">
        <div class="text-center">
            <script>
                Swal.fire({
                    title: 'ข้อผิดพลาด!',
                    text: 'คุณไม่มีสิทธิ์แก้ไขข้อมูลผู้ใช้อื่น',
                    icon: 'error',
                    confirmButtonText: 'ตกลง'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = 'index.php';
                    }
                });
            </script>
            <p class="text-gray-600 mt-4">คุณไม่มีสิทธิ์แก้ไขข้อมูลผู้ใช้อื่น กรุณา<a href="index.php" class="text-blue-600 underline">กลับไปที่หน้าแรก</a></p>
        </div>
    </body>

    </html>
<?php
    exit();
}

// ดึงข้อมูลแผนกจากฐานข้อมูล
$sql = "SELECT id, dept_name FROM departments";
$departments_result = $conn->query($sql);

// ดึงข้อมูล Asset PC สำหรับ Admin (เฉพาะ admin เท่านั้นที่เห็น)
$current_assets = [];
$history_assets = [];
if ($_SESSION['user_type'] == 'admin') {
    // เครื่องที่กำลัง assign อยู่
    $sql_current = "SELECT 
    a.id AS asset_id,
    a.asset_tag,
    a.pc_name,
    a.asset_type,
    a.serial_number,
    a.brand,
    a.model,
    aa.assigned_date,
    aa.condition_on_assign,
    aa.remarks
FROM asset_assignments aa
JOIN pc_assets a ON aa.asset_id = a.id
WHERE aa.user_id = :user_id AND aa.returned_date IS NULL
ORDER BY aa.assigned_date DESC";
    $stmt_current = $conn->prepare($sql_current);
    $stmt_current->bindParam(':user_id', $user_info_id, PDO::PARAM_INT);
    $stmt_current->execute();
    $current_assets = $stmt_current->fetchAll(PDO::FETCH_ASSOC);

    // ประวัติการ assign
    $sql_history = "SELECT 
    a.asset_tag,
    a.pc_name,
    a.asset_type,
    a.brand,
    a.model,
    aa.assigned_date,
    aa.returned_date
FROM asset_assignments aa
JOIN pc_assets a ON aa.asset_id = a.id
WHERE aa.user_id = :user_id AND aa.returned_date IS NOT NULL
ORDER BY aa.assigned_date DESC";
    $stmt_history = $conn->prepare($sql_history);
    $stmt_history->bindParam(':user_id', $user_info_id, PDO::PARAM_INT);
    $stmt_history->execute();
    $history_assets = $stmt_history->fetchAll(PDO::FETCH_ASSOC);
}

// แยกคำสั่ง POST ให้ชัดเจน เพื่อไม่ให้การอัปโหลดลายเซ็นไปอัปเดตข้อมูลโปรไฟล์
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $postedToken = $_POST['csrf_token'] ?? '';
    $csrfValid = is_string($postedToken) && hash_equals($_SESSION['csrf_token'], $postedToken);

    // คำขออัปโหลดลายเซ็นต้องตอบกลับเป็น JSON เสมอ
    if ($action === 'signature_upload') {
        header('Content-Type: application/json; charset=utf-8');
        $errors = [];
        $savedFilePath = null;

        try {
            if (!$csrfValid) {
                throw new RuntimeException('CSRF token ไม่ถูกต้องหรือหมดอายุ กรุณารีเฟรชหน้าแล้วลองใหม่');
            }

            $signatureColumns = [
                'DAR' => 'dar_signature_path',
                'OT'  => 'ot_signature_path',
                'EHS' => 'ehs_signature_path',
                'FTS' => 'fts_signature_path',
                'NCR' => 'ncr_signature_path',
            ];
            $documentType = strtoupper(trim((string)($_POST['document_type'] ?? '')));
            if (!isset($signatureColumns[$documentType])) {
                throw new RuntimeException('ประเภทเอกสารไม่ถูกต้อง');
            }

            $uploadDir = 'C:/inetpub/wwwroot/it_request/qa/uploads/signatures/';
            $maxFileSize = 2 * 1024 * 1024;
            $binaryData = null;
            $extension = null;

            if (isset($_FILES['signature']) && $_FILES['signature']['error'] !== UPLOAD_ERR_NO_FILE) {
                $file = $_FILES['signature'];
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    throw new RuntimeException('การอัปโหลดไฟล์ล้มเหลว รหัสข้อผิดพลาด: ' . (int)$file['error']);
                }
                if ((int)$file['size'] <= 0 || (int)$file['size'] > $maxFileSize) {
                    throw new RuntimeException('ขนาดไฟล์ต้องมากกว่า 0 และไม่เกิน 2MB');
                }

                $imageInfo = @getimagesize($file['tmp_name']);
                $mime = $imageInfo['mime'] ?? '';
                $mimeExtensions = ['image/png' => 'png', 'image/jpeg' => 'jpg'];
                if (!isset($mimeExtensions[$mime])) {
                    throw new RuntimeException('อนุญาตเฉพาะภาพ PNG หรือ JPEG ที่ถูกต้องเท่านั้น');
                }
                $extension = $mimeExtensions[$mime];
            } elseif (!empty($_POST['signature_data'])) {
                $signatureData = (string)$_POST['signature_data'];
                if (!preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $signatureData, $matches)) {
                    throw new RuntimeException('ข้อมูลลายเซ็นจาก canvas ไม่ถูกต้อง');
                }
                $binaryData = base64_decode($matches[1], true);
                if ($binaryData === false || strlen($binaryData) <= 0 || strlen($binaryData) > $maxFileSize) {
                    throw new RuntimeException('ข้อมูลลายเซ็นจาก canvas ไม่ถูกต้องหรือมีขนาดเกิน 2MB');
                }
                $imageInfo = @getimagesizefromstring($binaryData);
                if (($imageInfo['mime'] ?? '') !== 'image/png') {
                    throw new RuntimeException('ลายเซ็นจาก canvas ต้องเป็นภาพ PNG ที่ถูกต้อง');
                }
                $extension = 'png';
            } else {
                throw new RuntimeException('กรุณาเลือกไฟล์หรือวาดลายเซ็น');
            }

            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
                throw new RuntimeException('ไม่สามารถสร้างโฟลเดอร์เก็บลายเซ็นได้');
            }
            if (!is_writable($uploadDir)) {
                throw new RuntimeException('โฟลเดอร์เก็บลายเซ็นไม่มีสิทธิ์เขียน');
            }

            date_default_timezone_set('Asia/Bangkok');
            $sessionUsername = preg_replace('/[^A-Za-z0-9_-]/', '_', (string)($_SESSION['username'] ?? 'unknown'));
            $newFileName = sprintf('%d_%s_%s_%s_%s.%s', (int)$user_info_id, $documentType, $sessionUsername, date('Ymd_His'), bin2hex(random_bytes(4)), $extension);
            $savedFilePath = $uploadDir . $newFileName;

            $fileSaved = $binaryData !== null
                ? file_put_contents($savedFilePath, $binaryData, LOCK_EX) !== false
                : move_uploaded_file($_FILES['signature']['tmp_name'], $savedFilePath);
            if (!$fileSaved) {
                throw new RuntimeException('ไม่สามารถบันทึกไฟล์ลายเซ็นได้');
            }

            // ชื่อคอลัมน์มาจาก whitelist เท่านั้น และอัปเดตเฉพาะคอลัมน์ลายเซ็น
            $column = $signatureColumns[$documentType];
            $sql = "UPDATE [kva_it].[dbo].[users] SET [$column] = :signature_path WHERE id = :user_id";
            $stmt = $conn->prepare($sql);
            $updated = $stmt->execute([
                ':signature_path' => $savedFilePath,
                ':user_id' => (int)$user_info_id,
            ]);
            if (!$updated) {
                throw new RuntimeException('ฐานข้อมูลไม่สามารถบันทึกพาธลายเซ็นได้');
            }

            echo json_encode(['success' => true, 'errors' => []], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            if ($savedFilePath && is_file($savedFilePath)) {
                @unlink($savedFilePath);
            }
            http_response_code(400);
            echo json_encode(['success' => false, 'errors' => [$e->getMessage()]], JSON_UNESCAPED_UNICODE);
        }
        exit();
    }

    if ($action === 'user_update') {
        if (!$csrfValid) {
            $_SESSION['error'] = 'CSRF token ไม่ถูกต้องหรือหมดอายุ กรุณาลองใหม่';
            header('Location: edit_user.php?id=' . urlencode((string)$user_info_id));
            exit();
        }

        // อ่านและตรวจสอบค่าที่ส่งมาจากฟอร์มข้อมูลผู้ใช้เท่านั้น
        $requiredFields = ['username', 'first_name', 'last_name', 'department', 'site', 'phone_number', 'employee_id', 'email'];
        foreach ($requiredFields as $field) {
            if (!isset($_POST[$field]) || trim((string)$_POST[$field]) === '') {
                $_SESSION['error'] = 'กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน';
                header('Location: edit_user.php?id=' . urlencode((string)$user_info_id));
                exit();
            }
        }

        $username = trim((string)$_POST['username']);
        $firstName = trim((string)$_POST['first_name']);
        $lastName = trim((string)$_POST['last_name']);
        $department = (int)$_POST['department'];
        $site = trim((string)$_POST['site']);
        $phoneNumber = trim((string)$_POST['phone_number']);
        $employeeId = trim((string)$_POST['employee_id']);
        $email = trim((string)$_POST['email']);
        $password = !empty($_POST['password']) ? password_hash((string)$_POST['password'], PASSWORD_DEFAULT) : $user_info['password'];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = 'รูปแบบอีเมลไม่ถูกต้อง';
            header('Location: edit_user.php?id=' . urlencode((string)$user_info_id));
            exit();
        }

        // ฟิลด์สิทธิ์สูงใช้ค่าปัจจุบันเป็นค่าเริ่มต้น และเปลี่ยนได้เฉพาะ admin
        $status = $user_info['status'];
        $position = $user_info['position'];
        $userType = $user_info['user_type'];
        if ($_SESSION['user_type'] === 'admin') {
            $allowedStatuses = ['active', 'inactive'];
            $allowedPositions = ['Worker', 'Manager'];
            $allowedUserTypes = ['user', 'admin', 'EHS Admin', 'E-Leave Admin', 'PE Admin', 'QA Admin', 'FTS Admin'];
            $status = in_array($_POST['status'] ?? '', $allowedStatuses, true) ? $_POST['status'] : $user_info['status'];
            $position = in_array($_POST['position'] ?? '', $allowedPositions, true) ? $_POST['position'] : $user_info['position'];
            $userType = in_array($_POST['user_type'] ?? '', $allowedUserTypes, true) ? $_POST['user_type'] : $user_info['user_type'];
        }

        $sql = "UPDATE users SET username = :username, first_name = :first_name, email = :email, last_name = :last_name, department = :department, site = :site, status = :status, phone_number = :phone_number, password = :password, user_type = :user_type, employee_id = :employee_id, position = :position WHERE id = :user_id";
        $stmt = $conn->prepare($sql);
        $ok = $stmt->execute([
            ':username' => $username,
            ':first_name' => $firstName,
            ':email' => $email,
            ':last_name' => $lastName,
            ':department' => $department,
            ':site' => $site,
            ':status' => $status,
            ':phone_number' => $phoneNumber,
            ':password' => $password,
            ':user_type' => $userType,
            ':employee_id' => $employeeId,
            ':position' => $position,
            ':user_id' => (int)$user_info_id,
        ]);

        $_SESSION[$ok ? 'success' : 'error'] = $ok ? 'อัปเดตข้อมูลเรียบร้อยแล้ว' : 'เกิดข้อผิดพลาดในการอัปเดตข้อมูล';
        header('Location: edit_user.php?id=' . urlencode((string)$user_info_id));
        exit();
    }

    http_response_code(400);
    exit('Invalid action');
}

$conn = null;
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขรายละเอียดผู้ใช้ - User Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="styles.css" rel="stylesheet">
    <style>
        .header-gradient {
            background: linear-gradient(135deg, #1e40af 0%, #7c3aed 100%);
        }

        .form-container {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            padding: 3px;
        }

        .form-inner {
            background: white;
            border-radius: 17px;
            overflow: hidden;
        }

        .form-section {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #e2e8f0;
        }

        .form-input {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px 16px;
            padding-left: 45px;
        }

        .form-input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            transform: translateY(-2px);
        }

        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
            transition: color 0.3s ease;
        }

        .input-group:focus-within .input-icon {
            color: #3b82f6;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.4);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(107, 114, 128, 0.3);
        }

        .user-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: bold;
            margin: 0 auto 20px;
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.3);
        }

        .section-title {
            color: #1f2937;
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }

        .section-icon {
            margin-right: 10px;
            color: #3b82f6;
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6b7280;
            transition: color 0.3s ease;
        }

        .password-toggle:hover {
            color: #3b82f6;
        }

        .signature-download {
            color: #3b82f6;
            text-decoration: underline;
            font-size: 0.9rem;
            margin-top: 10px;
            display: inline-block;
        }

        .signature-download:hover {
            color: #1e40af;
        }

        .delete-signature-btn {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            padding: 6px 12px;
            border-radius: 8px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .delete-signature-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.4);
        }

        .signature-preview {
            max-width: 150px;
            max-height: 50px;
            object-fit: contain;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-top: 10px;
        }
    </style>
</head>

<body class="bg-gray-50 min-h-screen">
    <?php include 'nav.php'; ?>
    <div class="container mx-auto px-4 py-8 mt-16">
        <!-- Header Section -->
        <div class="text-center mb-8">
            <div class="header-gradient text-white rounded-xl p-6 shadow-xl">
                <h1 class="text-3xl font-bold mb-2">
                    <i class="fas fa-user-edit mr-3"></i>
                    แก้ไขรายละเอียดผู้ใช้ <?php echo htmlspecialchars($user_info['username'] ?? ''); ?>
                </h1>
                <p class="text-lg opacity-90">
                    Edit User Details
                </p>
                <div class="mt-4 text-sm opacity-80">
                    <i class="fas fa-info-circle mr-2"></i>
                    กรุณากรอกข้อมูลให้ครบถ้วนและถูกต้อง
                </div>
            </div>
        </div>
        <!-- Form Container -->
        <div class="form-container shadow-2xl max-w-6xl mx-auto">
            <div class="form-inner">
                <div class="p-8">
                    <!-- User Avatar -->
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($user_info['first_name'] ?? 'U', 0, 1) . substr($user_info['last_name'] ?? 'S', 0, 1)); ?>
                    </div>
                    <!-- Error Message -->
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                            <span class="block sm:inline"><?php echo $_SESSION['error'];
                                                            unset($_SESSION['error']); ?></span>
                        </div>
                    <?php endif; ?>
                    <!-- Success Message -->
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                            <span class="block sm:inline"><?php echo $_SESSION['success'];
                                                            unset($_SESSION['success']); ?></span>
                        </div>
                    <?php endif; ?>
                    <!-- Edit User Form -->
                    <form id="editUserForm" method="POST" action="" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="user_update">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                        <!-- Personal Information Section -->
                        <div class="form-section">
                            <h3 class="section-title">
                                <i class="fas fa-user section-icon"></i>
                                ข้อมูลส่วนตัว / Personal Information
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="input-group relative">
                                    <label for="username" class="block text-gray-700 font-semibold mb-2">
                                        <i class="fas fa-user-circle mr-2 text-blue-600"></i>
                                        ชื่อผู้ใช้ / Username
                                    </label>
                                    <div class="relative">
                                        <i class="fas fa-user-circle input-icon"></i>
                                        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user_info['username'] ?? ''); ?>" class="form-input w-full" required>
                                    </div>
                                </div>
                                <div class="input-group relative">
                                    <label for="employee_id" class="block text-gray-700 font-semibold mb-2">
                                        <i class="fas fa-id-card mr-2 text-blue-600"></i>
                                        รหัสพนักงาน / Employee ID
                                    </label>
                                    <div class="relative">
                                        <i class="fas fa-id-card input-icon"></i>
                                        <input type="text" id="employee_id" name="employee_id" value="<?php echo htmlspecialchars($user_info['employee_id'] ?? ''); ?>" class="form-input w-full" required>
                                    </div>
                                </div>
                                <div class="input-group relative">
                                    <label for="first_name" class="block text-gray-700 font-semibold mb-2">
                                        <i class="fas fa-user-tie mr-2 text-blue-600"></i>
                                        ชื่อ / First Name
                                    </label>
                                    <div class="relative">
                                        <i class="fas fa-user-tie input-icon"></i>
                                        <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user_info['first_name'] ?? ''); ?>" class="form-input w-full" required>
                                    </div>
                                </div>
                                <div class="input-group relative">
                                    <label for="last_name" class="block text-gray-700 font-semibold mb-2">
                                        <i class="fas fa-user-tie mr-2 text-blue-600"></i>
                                        นามสกุล / Last Name
                                    </label>
                                    <div class="relative">
                                        <i class="fas fa-user-tie input-icon"></i>
                                        <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user_info['last_name'] ?? ''); ?>" class="form-input w-full" required>
                                    </div>
                                </div>
                                <div class="input-group relative">
                                    <label for="email" class="block text-gray-700 font-semibold mb-2">
                                        <i class="fas fa-envelope mr-2 text-blue-600"></i>
                                        อีเมล / Email
                                    </label>
                                    <div class="relative">
                                        <i class="fas fa-envelope input-icon"></i>
                                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user_info['email'] ?? ''); ?>" class="form-input w-full" required>
                                    </div>
                                </div>
                                <div class="input-group relative">
                                    <label for="phone_number" class="block text-gray-700 font-semibold mb-2">
                                        <i class="fas fa-phone mr-2 text-blue-600"></i>
                                        เบอร์โทรศัพท์ / Phone Number
                                    </label>
                                    <div class="relative">
                                        <i class="fas fa-phone input-icon"></i>
                                        <input type="text" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($user_info['phone_number'] ?? ''); ?>" class="form-input w-full" required>
                                    </div>
                                </div>
                                <div class="input-group relative">
                                    <label for="department" class="block text-gray-700 font-semibold mb-2">
                                        <i class="fas fa-building mr-2 text-blue-600"></i>
                                        แผนก / Department
                                    </label>
                                    <div class="relative">
                                        <i class="fas fa-building input-icon"></i>
                                        <select id="department" name="department" class="form-input w-full" required>
                                            <?php
                                            $departments_result->execute();
                                            while ($dept = $departments_result->fetch(PDO::FETCH_ASSOC)): ?>
                                                <option value="<?php echo $dept['id']; ?>" <?php echo ($user_info['department'] == $dept['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($dept['dept_name']); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="input-group relative">
                                    <label for="site" class="block text-gray-700 font-semibold mb-2">
                                        <i class="fas fa-map-marker-alt mr-2 text-blue-600"></i>
                                        สถานที่ / Site
                                    </label>
                                    <div class="relative">
                                        <i class="fas fa-map-marker-alt input-icon"></i>
                                        <input type="text" id="site" name="site" value="<?php echo htmlspecialchars($user_info['site'] ?? ''); ?>" class="form-input w-full" required>
                                    </div>
                                </div>
                                <?php if ($_SESSION['user_type'] == 'admin'): ?>
                                    <div class="input-group relative">
                                        <label for="position" class="block text-gray-700 font-semibold mb-2">
                                            <i class="fas fa-briefcase mr-2 text-blue-600"></i>
                                            ตำแหน่ง / Position
                                        </label>
                                        <div class="relative">
                                            <i class="fas fa-briefcase input-icon"></i>
                                            <select id="position" name="position" class="form-input w-full" required>
                                                <option value="Worker" <?php echo ($user_info['position'] == 'Worker') ? 'selected' : ''; ?>>Worker</option>
                                                <option value="Manager" <?php echo ($user_info['position'] == 'Manager') ? 'selected' : ''; ?>>Manager</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="input-group relative">
                                        <label for="status" class="block text-gray-700 font-semibold mb-2">
                                            <i class="fas fa-toggle-on mr-2 text-blue-600"></i>
                                            สถานะ / Status
                                        </label>
                                        <div class="relative">
                                            <i class="fas fa-toggle-on input-icon"></i>
                                            <select id="status" name="status" class="form-input w-full" required>
                                                <option value="active" <?php echo ($user_info['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                                <option value="inactive" <?php echo ($user_info['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                            </select>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <div class="input-group relative col-span-2">
                                    <label for="password" class="block text-gray-700 font-semibold mb-2">
                                        <i class="fas fa-lock mr-2 text-blue-600"></i>
                                        รหัสผ่านใหม่ / New Password (ปล่อยว่างไว้ถ้าไม่เปลี่ยน)
                                    </label>
                                    <div class="relative">
                                        <i class="fas fa-lock input-icon"></i>
                                        <input type="password" id="password" name="password" class="form-input w-full" placeholder="กรุณากรอกรหัสผ่านใหม่">
                                        <i class="fas fa-eye password-toggle" onclick="togglePassword('password')"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Signature Management Section -->
                        <div class="form-section">
                            <h3 class="section-title">
                                <i class="fas fa-signature section-icon"></i>
                                การจัดการลายเซ็น / Signature Management
                            </h3>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                                <?php
                                $signatureTypes = [
                                    'DAR' => 'dar_signature_path',
                                    'OT'  => 'ot_signature_path',
                                    'EHS' => 'ehs_signature_path',
                                    'FTS' => 'fts_signature_path',
                                    'NCR' => 'ncr_signature_path',
                                ];

                                foreach ($signatureTypes as $label => $column):
                                    $signature_path = $user_info[$column] ?? '';
                                    $signature = (!empty($signature_path) && file_exists($signature_path))
                                        ? '/it_request/qa/uploads/signatures/' . rawurlencode(basename($signature_path))
                                        : '';

                                    $labelJson = json_encode($label, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT);
                                    $signatureJson = json_encode($signature, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT);
                                ?>

                                    <div>
                                        <label class="block text-gray-700 font-semibold mb-2">
                                            ลายเซ็น <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                        </label>

                                        <?php if ($signature !== ''): ?>
                                            <img src="<?= htmlspecialchars($signature, ENT_QUOTES, 'UTF-8') ?>"
                                                alt="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?> Signature"
                                                class="signature-preview">

                                            <button type="button"
                                                onclick='deleteSignature(<?= $labelJson ?>, <?= json_encode((string) $user_info_id) ?>)'
                                                class="delete-signature-btn mt-2">
                                                <i class="fas fa-trash mr-2"></i>ลบลายเซ็น
                                            </button>
                                        <?php else: ?>
                                            <p class="text-gray-600 mt-4">
                                                ยังไม่มีลายเซ็น <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?> ที่อัปโหลด
                                            </p>
                                        <?php endif; ?>

                                        <button type="button"
                                            onclick='openSignaturePopup(<?= $labelJson ?>, <?= $signatureJson ?>)'
                                            class="btn-primary mt-2 w-full">
                                            <i class="fas fa-upload mr-2"></i>
                                            <?= $signature !== '' ? 'เปลี่ยน' : 'อัปโหลด' ?>ลายเซ็น <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                        </button>
                                    </div>

                                <?php endforeach; ?>

                            </div>
                        </div>
                        <!-- System Information Section (เฉพาะ Admin) -->
                        <?php if ($_SESSION['user_type'] == 'admin'): ?>
                            <div class="form-section">
                                <h3 class="section-title">
                                    <i class="fas fa-cog section-icon"></i>
                                    ข้อมูลระบบ / System Information
                                </h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="input-group relative">
                                        <label for="user_type" class="block text-gray-700 font-semibold mb-2">
                                            <i class="fas fa-user-tag mr-2 text-blue-600"></i>
                                            ประเภทผู้ใช้ / User Type
                                        </label>
                                        <div class="relative">
                                            <i class="fas fa-user-tag input-icon"></i>
                                            <select id="user_type" name="user_type" class="form-input w-full" required>
                                                <option value="user" <?php echo ($user_info['user_type'] == 'user') ? 'selected' : ''; ?>>User</option>
                                                <option value="admin" <?php echo ($user_info['user_type'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
                                                <option value="EHS Admin" <?php echo ($user_info['user_type'] == 'EHS Admin') ? 'selected' : ''; ?>>EHS Admin</option>
                                                <option value="E-Leave Admin" <?php echo ($user_info['user_type'] == 'E-Leave Admin') ? 'selected' : ''; ?>>E-Leave Admin</option>
                                                <option value="PE Admin" <?php echo ($user_info['user_type'] == 'PE Admin') ? 'selected' : ''; ?>>PE Admin</option>
                                                <option value="QA Admin" <?php echo ($user_info['user_type'] == 'QA Admin') ? 'selected' : ''; ?>>QA Admin</option>
                                                <option value="FTS Admin" <?php echo ($user_info['user_type'] == 'FTS Admin') ? 'selected' : ''; ?>>FTS Admin</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- ส่วน Asset Management (เฉพาะ Admin) -->
                        <?php if ($_SESSION['user_type'] == 'admin'): ?>
                            <div class="form-section">
                                <h3 class="section-title">
                                    <i class="fas fa-laptop section-icon"></i>
                                    การจัดการอุปกรณ์ IT (PC/Notebook)
                                </h3>
                                <div class="mb-8">
                                    <div class="flex justify-between items-center mb-4">
                                        <h4 class="text-lg font-semibold text-gray-800">
                                            <i class="fas fa-desktop mr-2 text-green-600"></i>
                                            อุปกรณ์ที่ใช้งานอยู่ปัจจุบัน
                                        </h4>
                                        <button type="button" onclick="openAssignAssetModal(<?php echo $user_info_id; ?>)"
                                            class="btn-primary text-sm py-2 px-4">
                                            <i class="fas fa-plus mr-2"></i>Assign เครื่องใหม่
                                        </button>
                                    </div>

                                    <?php if (!empty($current_assets)): ?>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                            <?php foreach ($current_assets as $asset): ?>
                                                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-6 rounded-xl border border-blue-200 hover:shadow-lg transition-all duration-200">

                                                    <div class="flex justify-between items-start">

                                                        <!-- 🔹 LEFT CONTENT -->
                                                        <div>

                                                            <!-- 🖥 PC Name -->
                                                            <p class="font-bold text-lg text-indigo-900">
                                                                <?php echo htmlspecialchars($asset['pc_name'] ?? '-'); ?>
                                                            </p>

                                                            <!-- 🏷 Asset Type -->
                                                            <span class="inline-block bg-indigo-100 text-indigo-700 text-xs px-2 py-1 rounded mb-1">
                                                                <?php echo htmlspecialchars($asset['asset_type'] ?? '-'); ?>
                                                            </span>

                                                            <!-- 📦 Asset Tag -->
                                                            <p class="text-sm text-gray-500">
                                                                Tag: <?php echo htmlspecialchars($asset['asset_tag']); ?>
                                                            </p>

                                                            <!-- 💻 Brand + Model -->
                                                            <p class="text-gray-700 font-medium mt-1">
                                                                <?php echo htmlspecialchars($asset['brand'] . ' ' . $asset['model']); ?>
                                                            </p>

                                                            <!-- 🔢 Serial -->
                                                            <p class="text-sm text-gray-600">
                                                                SN: <?php echo htmlspecialchars($asset['serial_number']); ?>
                                                            </p>

                                                            <!-- 📅 Assign Date -->
                                                            <p class="text-sm text-gray-600 mt-2">
                                                                <i class="fas fa-calendar-alt mr-1 text-blue-500"></i>
                                                                มอบเมื่อ: <?php echo date('d/m/Y', strtotime($asset['assigned_date'])); ?>
                                                            </p>

                                                            <!-- 📌 Condition -->
                                                            <?php if (!empty($asset['condition_on_assign'])): ?>
                                                                <p class="text-sm text-gray-600">
                                                                    <i class="fas fa-check-circle mr-1 text-green-500"></i>
                                                                    สภาพ: <?php echo htmlspecialchars($asset['condition_on_assign']); ?>
                                                                </p>
                                                            <?php endif; ?>

                                                            <!-- 📝 Remarks -->
                                                            <?php if (!empty($asset['remarks'])): ?>
                                                                <p class="text-sm text-gray-600 mt-1">
                                                                    <i class="fas fa-sticky-note mr-1 text-yellow-500"></i>
                                                                    <?php echo htmlspecialchars($asset['remarks']); ?>
                                                                </p>
                                                            <?php endif; ?>

                                                        </div>

                                                        <!-- 🔹 RIGHT ACTION -->
                                                        <button type="button"
                                                            onclick="returnAsset(<?php echo $asset['asset_id']; ?>, <?php echo $user_info_id; ?>)"
                                                            class="text-red-600 hover:text-red-800 font-medium text-sm flex items-center gap-1">
                                                            <i class="fas fa-undo"></i>
                                                            คืนเครื่อง
                                                        </button>

                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-gray-600 italic">ยังไม่มีอุปกรณ์ที่ assign ให้ผู้ใช้นี้</p>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($history_assets)): ?>
                                    <details class="bg-gray-100 rounded-xl p-4">
                                        <summary class="font-semibold text-gray-800 cursor-pointer flex items-center gap-2">
                                            <i class="fas fa-history text-blue-600"></i>
                                            ประวัติการมอบอุปกรณ์ (<?php echo count($history_assets); ?> รายการ)
                                        </summary>

                                        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">

                                            <?php foreach ($history_assets as $hist): ?>
                                                <div class="bg-white p-4 rounded-lg border hover:shadow-md transition">

                                                    <!-- 🖥 PC Name -->
                                                    <p class="font-semibold text-indigo-900">
                                                        <?php echo htmlspecialchars($hist['pc_name'] ?? '-'); ?>
                                                    </p>

                                                    <!-- 🏷 Type -->
                                                    <span class="inline-block bg-indigo-100 text-indigo-700 text-xs px-2 py-1 rounded mb-1">
                                                        <?php echo htmlspecialchars($hist['asset_type'] ?? '-'); ?>
                                                    </span>

                                                    <!-- 📦 Tag -->
                                                    <p class="text-sm text-gray-500">
                                                        Tag: <?php echo htmlspecialchars($hist['asset_tag']); ?>
                                                    </p>

                                                    <!-- 💻 Spec -->
                                                    <p class="text-gray-700">
                                                        <?php echo htmlspecialchars($hist['brand'] . ' ' . $hist['model']); ?>
                                                    </p>

                                                    <!-- 📅 Timeline -->
                                                    <p class="text-sm text-gray-600 mt-2">
                                                        <i class="fas fa-arrow-right text-blue-500 mr-1"></i>
                                                        <?php echo date('d/m/Y', strtotime($hist['assigned_date'])); ?>
                                                        →
                                                        <?php echo date('d/m/Y', strtotime($hist['returned_date'])); ?>
                                                    </p>

                                                </div>
                                            <?php endforeach; ?>

                                        </div>
                                    </details>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Action Buttons -->
                        <div class="flex flex-col sm:flex-row items-center justify-between gap-4 mt-8">
                            <button type="submit" class="btn-primary text-white font-bold py-3 px-8 rounded-xl focus:outline-none focus:ring-4 focus:ring-blue-300 w-full sm:w-auto">
                                <i class="fas fa-save mr-2"></i>
                                บันทึกการเปลี่ยนแปลง / Update
                            </button>
                            <a href="user_list.php" class="btn-secondary text-white font-bold py-3 px-8 rounded-xl focus:outline-none focus:ring-4 focus:ring-gray-300 w-full sm:w-auto text-center">
                                <i class="fas fa-times mr-2"></i>
                                ยกเลิก / Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling;
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        document.getElementById('editUserForm').addEventListener('submit', function(e) {
            e.preventDefault();
            Swal.fire({
                title: 'ยืนยันการแก้ไข?',
                text: "คุณต้องการบันทึกการเปลี่ยนแปลงหรือไม่?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3b82f6',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'ใช่, บันทึก!',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'กำลังบันทึก...',
                        allowOutsideClick: false,
                        didOpen: () => Swal.showLoading()
                    });
                    this.submit();
                }
            });
        });

        // Delete signature with confirmation
        // Delete signature with confirmation
        function deleteSignature(type, userId) {
            type = String(type || '').trim().toUpperCase();

            const deleteUrls = {
                DAR: `delete_dar_signature.php?id=${encodeURIComponent(userId)}`,
                OT: `delete_ot_signature.php?id=${encodeURIComponent(userId)}`,
                EHS: `delete_ehs_signature.php?id=${encodeURIComponent(userId)}`,
                FTS: `delete_fts_signature.php?id=${encodeURIComponent(userId)}`,
                NCR: `delete_ncr_signature.php?id=${encodeURIComponent(userId)}`
            };

            if (!deleteUrls[type]) {
                Swal.fire({
                    title: 'ผิดพลาด!',
                    text: `ไม่รองรับประเภทลายเซ็น ${type}`,
                    icon: 'error',
                    confirmButtonColor: '#ef4444'
                });

                return;
            }

            Swal.fire({
                title: `ยืนยันการลบลายเซ็น ${type}?`,
                text: 'การกระทำนี้จะลบข้อมูลลายเซ็นออกจากฐานข้อมูล!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'ใช่, ลบ!',
                cancelButtonText: 'ยกเลิก',
                background: '#fff',
                customClass: {
                    popup: 'rounded-xl shadow-2xl',
                    title: 'text-gray-800',
                    content: 'text-gray-600'
                }
            }).then((result) => {
                if (!result.isConfirmed) {
                    return;
                }

                Swal.fire({
                    title: 'กำลังลบ...',
                    text: 'กรุณารอสักครู่',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                /*
                 * กำหนด URL ใน scope เดียวกับ fetch()
                 */
                const url = deleteUrls[type];

                fetch(url, {
                        method: 'GET',
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json'
                        }
                    })
                    .then(async (response) => {
                        const responseText = await response.text();

                        let data;

                        try {
                            data = JSON.parse(responseText);
                        } catch (error) {
                            throw new Error(
                                'เซิร์ฟเวอร์ตอบกลับไม่ใช่ JSON: ' +
                                responseText.substring(0, 200)
                            );
                        }

                        if (!response.ok) {
                            throw new Error(
                                data.message ||
                                `HTTP Error ${response.status}`
                            );
                        }

                        if (data.status !== 'success') {
                            throw new Error(
                                data.message ||
                                `ไม่สามารถลบลายเซ็น ${type} ได้`
                            );
                        }

                        return data;
                    })
                    .then((data) => {
                        Swal.fire({
                            title: 'สำเร็จ!',
                            text: data.message ||
                                `ลบลายเซ็น ${type} เรียบร้อยแล้ว`,
                            icon: 'success',
                            confirmButtonColor: '#10b981',
                            customClass: {
                                popup: 'rounded-xl shadow-2xl'
                            }
                        }).then(() => {
                            location.reload();
                        });
                    })
                    .catch((error) => {
                        Swal.fire({
                            title: 'ผิดพลาด!',
                            text: error.message,
                            icon: 'error',
                            confirmButtonColor: '#ef4444',
                            customClass: {
                                popup: 'rounded-xl shadow-2xl'
                            }
                        });
                    });
            });
        }

        // Signature upload popup
        function openSignaturePopup(documentType, currentSignature = '') {
            documentType = String(documentType || '').trim().toUpperCase();
            currentSignature = String(currentSignature || '').trim();

            console.log('Signature popup:', {
                documentType: documentType,
                currentSignature: currentSignature
            });
            Swal.fire({
                title: `จัดการลายเซ็น ${documentType}`,
                html: `
                    <div class="p-4">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700">ลายเซ็นปัจจุบัน</label>
                            ${currentSignature ? `<img src="${currentSignature}" alt="${documentType} Signature" class="signature-preview mx-auto">` : '<p class="text-sm text-gray-500 text-center">ไม่มีลายเซ็น</p>'}
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700">เลือกไฟล์ลายเซ็น (PNG หรือ JPEG, สูงสุด 2MB)</label>
                            <input type="file" id="signatureFile" accept="image/png,image/jpeg" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:bg-red-50 file:text-red-700 hover:file:bg-red-100">
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700">หรือวาดลายเซ็น</label>
                            <canvas id="signatureCanvas" class="signature-canvas mt-1 mx-auto" width="300" height="100"></canvas>
                            <input type="hidden" id="signatureData">
                            <button type="button" onclick="clearCanvas()" class="btn-secondary mt-2">ล้างลายเซ็น</button>
                        </div>
                        <button id="uploadSignatureBtn" class="btn-primary w-full mt-4">อัปโหลดลายเซ็น</button>
                    </div>
                `,
                showConfirmButton: false,
                showCancelButton: true,
                cancelButtonText: 'ปิด',
                cancelButtonColor: '#6b7280',
                didOpen: () => {
                    const canvas = document.getElementById('signatureCanvas');
                    const ctx = canvas.getContext('2d');
                    let drawing = false;
                    let prevX, prevY;

                    canvas.addEventListener('mousedown', (e) => {
                        drawing = true;
                        prevX = e.offsetX;
                        prevY = e.offsetY;
                    });

                    canvas.addEventListener('mousemove', (e) => {
                        if (!drawing) return;
                        ctx.beginPath();
                        ctx.moveTo(prevX, prevY);
                        ctx.lineTo(e.offsetX, e.offsetY);
                        ctx.strokeStyle = 'black';
                        ctx.lineWidth = 2;
                        ctx.stroke();
                        ctx.closePath();
                        prevX = e.offsetX;
                        prevY = e.offsetY;
                        document.getElementById('signatureData').value = canvas.toDataURL();
                    });

                    canvas.addEventListener('mouseup', () => {
                        drawing = false;
                        prevX = prevY = null;
                    });

                    canvas.addEventListener('mouseout', () => {
                        drawing = false;
                        prevX = prevY = null;
                    });

                    document.getElementById('uploadSignatureBtn').addEventListener('click', () => {
                        const fileInput = document.getElementById('signatureFile');
                        const signatureData = document.getElementById('signatureData').value;
                        const formData = new FormData();

                        formData.append('action', 'signature_upload');
                        formData.append('csrf_token', <?php echo json_encode($_SESSION['csrf_token']); ?>);
                        formData.append('document_type', documentType);

                        if (fileInput.files[0]) {
                            formData.append('signature', fileInput.files[0]);
                            uploadSignature(formData, documentType);
                        } else if (signatureData) {
                            formData.append('signature_data', signatureData);
                            uploadSignature(formData, documentType);
                        } else {
                            Swal.showValidationMessage('กรุณาเลือกไฟล์หรือวาดลายเซ็น');
                        }
                    });
                },
                preConfirm: () => false,
                customClass: {
                    popup: 'rounded-xl shadow-2xl',
                    title: 'text-gray-800',
                    content: 'text-gray-600'
                }
            });
        }

        function uploadSignature(formData, documentType) {
            // ส่งกลับมายังไฟล์/URL ปัจจุบัน ไม่ hard-code เป็น edit_user.php
            // จึงทำงานได้ทั้งตอนทดสอบชื่อ edit_user_fixed_v2.php และตอนเปลี่ยนชื่อเป็น edit_user.php
            const uploadUrl = new URL(window.location.href);

            fetch(uploadUrl.toString(), {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(async response => {
                    const responseText = await response.text();
                    let data;

                    try {
                        data = JSON.parse(responseText);
                    } catch (error) {
                        const preview = responseText.replace(/\s+/g, ' ').trim().substring(0, 300);
                        throw new Error(
                            'เซิร์ฟเวอร์ไม่ได้ส่ง JSON กลับมา (HTTP ' + response.status + '). ' +
                            'อาจเรียกผิดไฟล์, Session หมดอายุ หรือ PHP เกิด Fatal Error. Response: ' + preview
                        );
                    }

                    if (!response.ok || !data.success) {
                        const messages = Array.isArray(data.errors) ? data.errors.join('\n') : (data.message || 'อัปโหลดไม่สำเร็จ');
                        throw new Error(messages);
                    }

                    return data;
                })
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            title: 'สำเร็จ!',
                            text: `อัปโหลดลายเซ็น ${documentType} เรียบร้อยแล้ว`,
                            icon: 'success',
                            confirmButtonColor: '#10b981',
                            customClass: {
                                popup: 'rounded-xl shadow-2xl'
                            }
                        }).then(() => location.reload());
                    } else {
                        Swal.fire({
                            title: 'ผิดพลาด!',
                            html: data.errors.join('<br>'),
                            icon: 'error',
                            confirmButtonColor: '#ef4444',
                            customClass: {
                                popup: 'rounded-xl shadow-2xl'
                            }
                        });
                    }
                })
                .catch(error => {
                    Swal.fire({
                        title: 'ผิดพลาด!',
                        text: `เกิดข้อผิดพลาด: ${error.message}`,
                        icon: 'error',
                        confirmButtonColor: '#ef4444',
                        customClass: {
                            popup: 'rounded-xl shadow-2xl'
                        }
                    });
                });
        }


        function clearCanvas() {
            const canvas = document.getElementById('signatureCanvas');
            const ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            document.getElementById('signatureData').value = '';
        }

        // ส่วน Asset Management
        // ส่วน Asset Management
        function openAssignAssetModal(userId) {
            Swal.fire({
                title: 'มอบอุปกรณ์ IT ให้ผู้ใช้',
                html: `
            <div class="text-left space-y-4">
                <div>
                    <label class="block font-medium mb-1">เลือกอุปกรณ์</label>
                    <select id="availableAsset" class="form-input w-full">
                        <option value="">-- กำลังโหลดรายการ... --</option>
                    </select>
                </div>
                <div>
                    <label class="block font-medium mb-1">สภาพอุปกรณ์ตอนมอบ</label>
                    <select id="conditionAssign" class="form-input w-full">
                        <option value="Good">Good (สภาพดีมาก)</option>
                        <option value="Fair">Fair (สภาพดี มีรอยเล็กน้อย)</option>
                        <option value="Poor">Poor (ชำรุดเล็กน้อย ยังใช้ได้)</option>
                        <option value="Damaged">Damaged (ชำรุด ต้องซ่อม)</option>
                        <option value="Obsolete">Obsolete (ล้าสมัย / ปลดระวาง)</option>
                    </select>
                </div>
                <div>
                    <label class="block font-medium mb-1">หมายเหตุ (ถ้ามี)</label>
                    <textarea id="assignRemarks" class="form-input w-full" rows="3" 
                        placeholder="เช่น มอบพร้อมเมาส์/คีย์บอร์ด, มีรอยถลอกที่ตัวเครื่อง..."></textarea>
                </div>
            </div>
        `,
                focusConfirm: false,
                showCancelButton: true,
                confirmButtonText: 'มอบอุปกรณ์',
                cancelButtonText: 'ยกเลิก',
                showLoaderOnConfirm: true,
                allowOutsideClick: () => !Swal.isLoading(),

                didOpen: () => {
                    fetch('get_available_assets.php')
                        .then(res => {
                            if (!res.ok) throw new Error('Network response was not ok');
                            return res.json();
                        })
                        .then(data => {
                            const select = document.getElementById('availableAsset');
                            select.innerHTML = '<option value="">-- เลือกอุปกรณ์ --</option>';

                            if (!Array.isArray(data) || data.length === 0) {
                                select.innerHTML = '<option value="">-- ไม่มีอุปกรณ์ว่างในขณะนี้ --</option>';
                                return;
                            }

                            data.forEach(asset => {
                                const opt = document.createElement('option');
                                opt.value = asset.id;

                                // จัดรูปแบบให้ pc_name อยู่ด้านหน้า + อ่านง่าย
                                let display = [];
                                if (asset.pc_name && asset.pc_name.trim()) {
                                    display.push(asset.pc_name.trim());
                                }
                                if (asset.asset_tag && asset.asset_tag.trim()) {
                                    display.push(asset.asset_tag.trim());
                                }

                                let mainText = display.length > 0 ? display.join(' • ') : '(ไม่มีชื่อเครื่อง/asset tag)';

                                let spec = `${asset.brand || ''} ${asset.model || ''}`.trim();
                                if (spec) {
                                    mainText += ` (${spec})`;
                                }

                                if (asset.serial_number && asset.serial_number.trim()) {
                                    mainText += ` - SN: ${asset.serial_number.trim()}`;
                                }

                                opt.textContent = mainText;
                                select.appendChild(opt);
                            });
                        })
                        .catch(err => {
                            console.error('Error loading assets:', err);
                            document.getElementById('availableAsset').innerHTML =
                                '<option value="">-- โหลดรายการอุปกรณ์ไม่สำเร็จ --</option>';
                        });
                },

                preConfirm: () => {
                    const assetId = document.getElementById('availableAsset').value;
                    if (!assetId) {
                        Swal.showValidationMessage('กรุณาเลือกอุปกรณ์ก่อน');
                        return false;
                    }

                    return {
                        asset_id: assetId,
                        condition: document.getElementById('conditionAssign').value,
                        remarks: document.getElementById('assignRemarks').value.trim()
                    };
                }
            }).then(result => {
                if (!result.isConfirmed) return;

                Swal.showLoading();

                fetch('assign_asset.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            // ถ้ามี CSRF token สามารถเพิ่มตรงนี้ได้ เช่น:
                            // 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({
                            user_id: userId,
                            ...result.value
                        })
                    })
                    .then(res => {
                        if (!res.ok) throw new Error('Server responded with error');
                        return res.json();
                    })
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'มอบอุปกรณ์สำเร็จ',
                                text: data.message || 'บันทึกเรียบร้อยแล้ว',
                                timer: 1800,
                                showConfirmButton: false
                            }).then(() => {
                                location.reload(); // หรือจะใช้ ajax refresh ตารางแทนก็ได้
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'เกิดข้อผิดพลาด',
                                text: data.message || 'ไม่สามารถมอบอุปกรณ์ได้ กรุณาลองใหม่'
                            });
                        }
                    })
                    .catch(err => {
                        console.error('Assign error:', err);
                        Swal.fire({
                            icon: 'error',
                            title: 'เกิดข้อผิดพลาด',
                            text: 'ไม่สามารถติดต่อเซิร์ฟเวอร์ได้ กรุณาตรวจสอบการเชื่อมต่อ'
                        });
                    });
            });
        }

        function returnAsset(assetId, userId) {
            Swal.fire({
                title: 'ยืนยันการคืนเครื่อง?',
                text: 'เครื่องจะกลับสู่สถานะ Available',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'คืนเครื่อง'
            }).then(result => {
                if (result.isConfirmed) {
                    fetch('return_asset.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                asset_id: assetId
                            })
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire('สำเร็จ!', 'คืนเครื่องเรียบร้อยแล้ว', 'success').then(() => location.reload());
                            }
                        });
                }
            });
        }
    </script>
</body>

</html>