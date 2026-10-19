<?php
session_start(); // เริ่มต้น session
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
    $sql_current = "SELECT a.id AS asset_id, a.asset_tag, a.serial_number, a.brand, a.model,
                           aa.assigned_date, aa.condition_on_assign, aa.remarks
                    FROM asset_assignments aa
                    JOIN pc_assets a ON aa.asset_id = a.id
                    WHERE aa.user_id = :user_id AND aa.returned_date IS NULL
                    ORDER BY aa.assigned_date DESC";
    $stmt_current = $conn->prepare($sql_current);
    $stmt_current->bindParam(':user_id', $user_info_id, PDO::PARAM_INT);
    $stmt_current->execute();
    $current_assets = $stmt_current->fetchAll(PDO::FETCH_ASSOC);

    // ประวัติการ assign
    $sql_history = "SELECT a.asset_tag, a.brand, a.model,
                           aa.assigned_date, aa.returned_date
                    FROM asset_assignments aa
                    JOIN pc_assets a ON aa.asset_id = a.id
                    WHERE aa.user_id = :user_id AND aa.returned_date IS NOT NULL
                    ORDER BY aa.assigned_date DESC";
    $stmt_history = $conn->prepare($sql_history);
    $stmt_history->bindParam(':user_id', $user_info_id, PDO::PARAM_INT);
    $stmt_history->execute();
    $history_assets = $stmt_history->fetchAll(PDO::FETCH_ASSOC);
}

// อัปเดตข้อมูลผู้ใช้
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['user_update'])) {
        $user_infoname = $_POST['username'];
        $first_name = $_POST['first_name'];
        $last_name = $_POST['last_name'];
        $department = $_POST['department'];
        $site = $_POST['site'];
        $status = $_POST['status'];
        $password = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : $user_info['password'];
        $user_info_type = $_POST['user_type'] ?? $user_info['user_type'];
        $phone_number = $_POST['phone_number'];
        $employee_id = $_POST['employee_id'];
        $email = $_POST['email'];
        $position = $_POST['position'];

        $sql = "UPDATE users SET username = :username, first_name = :first_name, email = :email, last_name = :last_name, department = :department, site = :site, status = :status, phone_number = :phone_number, password = :password, user_type = :user_type, employee_id = :employee_id, position = :position WHERE id = :user_id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':username', $user_infoname);
        $stmt->bindParam(':first_name', $first_name);
        $stmt->bindParam(':last_name', $last_name);
        $stmt->bindParam(':department', $department);
        $stmt->bindParam(':site', $site);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':phone_number', $phone_number);
        $stmt->bindParam(':password', $password);
        $stmt->bindParam(':user_type', $user_info_type);
        $stmt->bindParam(':employee_id', $employee_id);
        $stmt->bindParam(':position', $position);
        $stmt->bindParam(':user_id', $user_info_id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            $_SESSION['success'] = "อัปเดตข้อมูลเรียบร้อยแล้ว";
            header('Location: edit_user.php?id=' . $user_info_id);
            exit();
        } else {
            $_SESSION['error'] = "เกิดข้อผิดพลาดในการอัปเดตข้อมูล";
        }
    } elseif (isset($_POST['signature_upload']) && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
        $uploadDir = 'C:/inetpub/wwwroot/it_request/qa/uploads/signatures/';
        $allowedTypes = ['image/png', 'image/jpeg'];
        $maxFileSize = 2 * 1024 * 1024; // 2MB
        $errors = [];
        $success = false;

        try {
            $document_type = $_POST['document_type'] ?? '';
            $validDocumentTypes = ['DAR', 'OT'];

            if (!in_array($document_type, $validDocumentTypes)) {
                $errors[] = "ประเภทเอกสารไม่ถูกต้อง";
            }

            if (isset($_FILES['signature']) && $_FILES['signature']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['signature'];
                $fileType = mime_content_type($file['tmp_name']);
                $fileSize = $file['size'];

                if (!getimagesize($file['tmp_name'])) {
                    $errors[] = "ไฟล์ไม่ใช่ภาพที่ถูกต้อง";
                }
                if (!in_array($fileType, $allowedTypes)) {
                    $errors[] = "อนุญาตเฉพาะไฟล์ PNG หรือ JPEG เท่านั้น";
                }
                if ($fileSize > $maxFileSize) {
                    $errors[] = "ขนาดไฟล์ต้องไม่เกิน 2MB";
                }

                if (empty($errors)) {
                    $column = strtolower($document_type) . '_signature_path';
                    date_default_timezone_set('Asia/Bangkok');
                    $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $sessionUsername = $_SESSION['username'] ?? 'unknown';
                    $date = date('dmy');
                    $time = date('His');
                    $newFileName = $user_info_id . '_' . $document_type . '_' . $sessionUsername . '_' . $date . '_' . $time . '.' . $fileExtension;
                    $filePath = $uploadDir . $newFileName;

                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                        shell_exec('icacls "' . $uploadDir . '" /grant "IIS_IUSRS:(OI)(CI)(M)" /grant "IUSR:(OI)(CI)(M)"');
                    }

                    if (move_uploaded_file($file['tmp_name'], $filePath)) {
                        shell_exec('icacls "' . $filePath . '" /grant "IIS_IUSRS:(OI)(CI)(M)" /grant "IUSR:(OI)(CI)(M)"');
                        $sql = "UPDATE [kva_it].[dbo].[users] SET [$column] = :signature_path WHERE id = :user_id";
                        $stmt = $conn->prepare($sql);
                        $stmt->bindParam(':signature_path', $filePath);
                        $stmt->bindParam(':user_id', $user_info_id, PDO::PARAM_INT);
                        $stmt->execute();
                        $success = true;
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    } else {
                        $errors[] = "ไม่สามารถอัปโหลดไฟล์ได้ ตรวจสอบสิทธิ์โฟลเดอร์";
                    }
                }
            } elseif (!empty($_POST['signature_data'])) {
                $signature_data = $_POST['signature_data'];
                $data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $signature_data));
                $sessionUsername = $_SESSION['username'] ?? 'unknown';
                $date = date('dmy');
                $newFileName = $user_info_id . '_' . $document_type . '_' . $sessionUsername . '_' . $date . '.png';
                $filePath = $uploadDir . $newFileName;

                $column = strtolower($document_type) . '_signature_path';

                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                    shell_exec('icacls "' . $uploadDir . '" /grant "IIS_IUSRS:(OI)(CI)(M)" /grant "IUSR:(OI)(CI)(M)"');
                }

                if (file_put_contents($filePath, $data)) {
                    shell_exec('icacls "' . $filePath . '" /grant "IIS_IUSRS:(OI)(CI)(M)" /grant "IUSR:(OI)(CI)(M)"');
                    $sql = "UPDATE [kva_it].[dbo].[users] SET [$column] = :signature_path WHERE id = :user_id";
                    $stmt = $conn->prepare($sql);
                    $stmt->bindParam(':signature_path', $filePath);
                    $stmt->bindParam(':user_id', $user_info_id, PDO::PARAM_INT);
                    $stmt->execute();
                    $success = true;
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                } else {
                    $errors[] = "ไม่สามารถบันทึกลายเซ็นจาก canvas ได้ ตรวจสอบพื้นที่ดิสก์";
                }
            }

            header('Content-Type: application/json');
            echo json_encode(['success' => $success, 'errors' => $errors]);
            exit();
        } catch (Exception $e) {
            $errors[] = "เกิดข้อผิดพลาด: " . $e->getMessage();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'errors' => $errors]);
            exit();
        }
    }
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
                        <input type="hidden" name="user_update" value="1">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32)); ?>">
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
                                <div>
                                    <label class="block text-gray-700 font-semibold mb-2">ลายเซ็น DAR</label>
                                    <?php
                                    $dar_signature_path = $user_info['dar_signature_path'] ?? '';
                                    $dar_signature = !empty($dar_signature_path) && file_exists($dar_signature_path) ? '/it_request/qa/uploads/signatures/' . basename($dar_signature_path) : '';
                                    ?>
                                    <?php if ($dar_signature && file_exists($_SERVER['DOCUMENT_ROOT'] . $dar_signature)): ?>
                                        <img src="<?php echo htmlspecialchars($dar_signature); ?>" alt="DAR Signature" class="signature-preview">
                                        <button type="button" onclick="deleteSignature('DAR', '<?php echo htmlspecialchars($user_info_id); ?>')" class="delete-signature-btn mt-2">
                                            <i class="fas fa-trash mr-2"></i>ลบลายเซ็น
                                        </button>
                                    <?php else: ?>
                                        <p class="text-gray-600 mt-4">ยังไม่มีลายเซ็น DAR ที่อัปโหลด</p>
                                        <button type="button" onclick="openSignaturePopup('DAR')" class="btn-primary mt-2 w-full">
                                            <i class="fas fa-upload mr-2"></i>อัปโหลดลายเซ็น DAR
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <label class="block text-gray-700 font-semibold mb-2">ลายเซ็น OT</label>
                                    <?php
                                    $ot_signature_path = $user_info['ot_signature_path'] ?? '';
                                    $ot_signature = !empty($ot_signature_path) && file_exists($ot_signature_path) ? '/it_request/qa/uploads/signatures/' . basename($ot_signature_path) : '';
                                    ?>
                                    <?php if ($ot_signature && file_exists($_SERVER['DOCUMENT_ROOT'] . $ot_signature)): ?>
                                        <img src="<?php echo htmlspecialchars($ot_signature); ?>" alt="OT Signature" class="signature-preview">
                                        <button type="button" onclick="deleteSignature('OT', '<?php echo htmlspecialchars($user_info_id); ?>')" class="delete-signature-btn mt-2">
                                            <i class="fas fa-trash mr-2"></i>ลบลายเซ็น
                                        </button>
                                    <?php else: ?>
                                        <p class="text-gray-600 mt-4">ยังไม่มีลายเซ็น OT ที่อัปโหลด</p>
                                        <button type="button" onclick="openSignaturePopup('OT')" class="btn-primary mt-2 w-full">
                                            <i class="fas fa-upload mr-2"></i>อัปโหลดลายเซ็น OT
                                        </button>
                                    <?php endif; ?>
                                </div>
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
                                                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-6 rounded-xl border border-blue-200">
                                                    <div class="flex justify-between items-start">
                                                        <div>
                                                            <p class="font-bold text-xl text-blue-900"><?php echo htmlspecialchars($asset['asset_tag']); ?></p>
                                                            <p class="text-gray-700"><?php echo htmlspecialchars($asset['brand'] . ' ' . $asset['model']); ?></p>
                                                            <p class="text-sm text-gray-600">SN: <?php echo htmlspecialchars($asset['serial_number']); ?></p>
                                                            <p class="text-sm text-gray-600 mt-2">มอบเมื่อ: <?php echo date('d/m/Y', strtotime($asset['assigned_date'])); ?></p>
                                                            <?php if (!empty($asset['condition_on_assign'])): ?>
                                                                <p class="text-sm text-gray-600">สภาพ: <?php echo htmlspecialchars($asset['condition_on_assign']); ?></p>
                                                            <?php endif; ?>
                                                            <?php if (!empty($asset['remarks'])): ?>
                                                                <p class="text-sm text-gray-600 mt-1">หมายเหตุ: <?php echo htmlspecialchars($asset['remarks']); ?></p>
                                                            <?php endif; ?>
                                                        </div>
                                                        <button type="button" onclick="returnAsset(<?php echo $asset['asset_id']; ?>, <?php echo $user_info_id; ?>)"
                                                                class="text-red-600 hover:text-red-800 font-medium text-sm">
                                                            <i class="fas fa-undo mr-1"></i>คืนเครื่อง
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
                                        <summary class="font-semibold text-gray-800 cursor-pointer">
                                            <i class="fas fa-history mr-2"></i>
                                            ประวัติการมอบอุปกรณ์ (<?php echo count($history_assets); ?> รายการ)
                                        </summary>
                                        <div class="mt-4 space-y-3">
                                            <?php foreach ($history_assets as $hist): ?>
                                                <div class="bg-white p-4 rounded-lg border">
                                                    <p class="font-medium"><?php echo htmlspecialchars($hist['asset_tag'] . ' - ' . $hist['brand'] . ' ' . $hist['model']); ?></p>
                                                    <p class="text-sm text-gray-600">
                                                        มอบ: <?php echo date('d/m/Y', strtotime($hist['assigned_date'])); ?>
                                                        → คืน: <?php echo date('d/m/Y', strtotime($hist['returned_date'])); ?>
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

        function deleteSignature(type, userId) {
            // โค้ดเดิมทั้งหมด
        }

        function openSignaturePopup(documentType) {
            // โค้ดเดิมทั้งหมด
        }

        function uploadSignature(formData, documentType) {
            // โค้ดเดิมทั้งหมด
        }

        function clearCanvas() {
            // โค้ดเดิมทั้งหมด
        }

        // ส่วน Asset Management
        function openAssignAssetModal(userId) {
            Swal.fire({
                title: 'Assign อุปกรณ์ IT ใหม่',
                html: `
                    <div class="text-left">
                        <label class="block font-medium mb-2">เลือกอุปกรณ์</label>
                        <select id="availableAsset" class="form-input w-full mb-4"><option>-- กำลังโหลด... --</option></select>
                        <label class="block font-medium mb-2">สภาพตอนมอบ</label>
                        <select id="conditionAssign" class="form-input w-full mb-4">
                            <option value="Good">Good</option>
                            <option value="Fair">Fair</option>
                            <option value="Need Repair">Need Repair</option>
                        </select>
                        <label class="block font-medium mb-2">หมายเหตุ</label>
                        <textarea id="assignRemarks" class="form-input w-full" rows="3"></textarea>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Assign',
                didOpen: () => {
                    fetch('get_available_assets.php')
                        .then(res => res.json())
                        .then(data => {
                            const select = document.getElementById('availableAsset');
                            select.innerHTML = '<option value="">-- เลือกอุปกรณ์ --</option>';
                            data.forEach(asset => {
                                const opt = document.createElement('option');
                                opt.value = asset.id;
                                opt.textContent = `${asset.asset_tag} - ${asset.brand} ${asset.model} (SN: ${asset.serial_number})`;
                                select.appendChild(opt);
                            });
                        });
                },
                preConfirm: () => {
                    const assetId = document.getElementById('availableAsset').value;
                    if (!assetId) {
                        Swal.showValidationMessage('กรุณาเลือกอุปกรณ์');
                        return false;
                    }
                    return {
                        asset_id: assetId,
                        condition: document.getElementById('conditionAssign').value,
                        remarks: document.getElementById('assignRemarks').value
                    };
                }
            }).then(result => {
                if (result.isConfirmed) {
                    fetch('assign_asset.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({
                            user_id: userId,
                            asset_id: result.value.asset_id,
                            condition: result.value.condition,
                            remarks: result.value.remarks
                        })
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('สำเร็จ!', 'Assign เครื่องเรียบร้อยแล้ว', 'success').then(() => location.reload());
                        } else {
                            Swal.fire('ผิดพลาด!', data.message || 'เกิดข้อผิดพลาด', 'error');
                        }
                    });
                }
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
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({asset_id: assetId})
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