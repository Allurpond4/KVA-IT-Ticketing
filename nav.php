<?php
ob_start(); // เริ่ม output buffering เพื่อป้องกันการส่งออกก่อน header
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include 'connect.php';
include 'function.php';

// สร้าง CSRF token หากยังไม่มี
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ดึงข้อมูล profile_picture และลายเซ็นจากฐานข้อมูล
$profile_picture = 'img/user.jpg'; // Default รูปภาพ
$dar_signature = null;
$ot_signature = null;
$base_url = 'http://eweb.krugerfan.com/it_request/qa/uploads/signatures/'; // URL ฐานสำหรับแสดงรูปภาพ
if (isset($_SESSION['user_id'])) {
    try {
        $sql = "SELECT [profile_picture], [dar_signature_path], [ot_signature_path] FROM [kva_it].[dbo].[users] WHERE id = :user_id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            if (!empty($user['profile_picture']) && file_exists('img/profiles/' . $user['profile_picture'])) {
                $profile_picture = 'img/profiles/' . $user['profile_picture'];
            }
            if (!empty($user['dar_signature_path']) && file_exists($user['dar_signature_path'])) {
                $dar_signature = $base_url . basename($user['dar_signature_path']); // ใช้เฉพาะชื่อไฟล์
            }
            if (!empty($user['ot_signature_path']) && file_exists($user['ot_signature_path'])) {
                $ot_signature = $base_url . basename($user['ot_signature_path']); // ใช้เฉพาะชื่อไฟล์
            }
        }
    } catch (PDOException $e) {
        error_log('Error fetching user data: ' . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device=device-width, initial-scale=1.0">
    <title>Kruger IT - ระบบบริการงานไอที</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Anuphan:wght@100..700&family=K2D:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdn.jsdelivr.net/npm/flowbite@3.0.0/dist/flowbite.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/flowbite@3.0.0/dist/flowbite.min.js"></script>
    <link rel="icon" type="image/png" href="img/KrugerLogo.png">
    <style>
        body {
            font-family: 'K2D', 'Anuphan', sans-serif;
        }

        .navbar-gradient {
            background: linear-gradient(135deg, #c41e3a 0%, #b31217 50%, #8b0000 100%);
            box-shadow: 0 4px 20px rgba(196, 30, 58, 0.3);
        }

        .nav-link {
            position: relative;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: 8px;
            padding: 8px 16px;
        }

        .nav-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
            border-radius: 8px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .nav-link:hover::before {
            opacity: 1;
        }

        .nav-link::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: 2px;
            left: 50%;
            background: linear-gradient(90deg, #fff, #ffcccb);
            transition: all 0.3s ease;
            transform: translateX(-50%);
            border-radius: 1px;
        }

        .nav-link:hover::after {
            width: 80%;
        }

        .dropdown-item {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: 6px;
            margin: 2px 8px;
        }

        .dropdown-item:hover {
            transform: translateX(8px);
            background: linear-gradient(90deg, #fef2f2, #fee2e2);
            box-shadow: 0 2px 8px rgba(196, 30, 58, 0.1);
        }

        .user-menu-button {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .user-menu-button:hover {
            transform: scale(1.05) rotate(2deg);
            box-shadow: 0 4px 16px rgba(196, 30, 58, 0.2);
        }

        .logo-container {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .logo-container:hover {
            transform: translateY(-3px) scale(1.02);
        }

        .logo-glow {
            box-shadow: 0 0 20px rgba(255, 255, 255, 0.3);
        }

        .dropdown-menu-animation {
            animation: slideDown 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(196, 30, 58, 0.1);
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px) scale(0.95);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .mobile-menu-button {
            transition: all 0.3s ease;
        }

        .mobile-menu-button:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: rotate(90deg);
        }

        .brand-text {
            background: linear-gradient(45deg, #fff, #ffcccb);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .user-info-card {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-bottom: 2px solid #c41e3a;
        }

        .nav-icon {
            transition: all 0.3s ease;
        }

        .nav-link:hover .nav-icon {
            transform: scale(1.2) rotate(5deg);
        }

        .dropdown-icon {
            transition: transform 0.3s ease;
        }

        .dropdown-open .dropdown-icon {
            transform: rotate(180deg);
        }

        .glass-effect {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }

        .notification-badge {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.7;
            }
        }

        .signature-preview {
            max-width: 100px;
            max-height: 50px;
            object-fit: contain;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            padding: 0.5rem;
        }

        .swal2-popup {
            border-radius: 1rem !important;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2) !important;
        }

        .swal2-html-container {
            font-family: 'K2D', 'Anuphan', sans-serif !important;
        }

        canvas.signature-canvas {
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            max-width: 100%;
        }
    </style>
</head>

<body class="bg-gray-50">
    <nav class="navbar-gradient border-b-2 border-red-900 sticky top-0 z-50">
        <div class="max-w-screen-xl flex flex-wrap items-center justify-between mx-auto p-4">
            <!-- Enhanced Logo and Brand -->
            <a href="http://eweb.krugerfan.com/main.php" class="flex items-center space-x-4 rtl:space-x-reverse logo-container">
                <div class="relative">
                    <img src="img/KrugerLogo.png" class="h-12 rounded-full shadow-lg p-1 bg-white logo-glow" alt="Kruger Logo" />
                    <div class="absolute -top-1 -right-1 w-4 h-4 bg-green-400 rounded-full border-2 border-white notification-badge"></div>
                </div>
                <div class="flex flex-col">
                    <span class="text-2xl font-bold whitespace-nowrap brand-text">Kruger IT</span>
                    <span class="text-sm text-red-100 opacity-90">ระบบบริการงานไอที</span>
                </div>
            </a>

            <!-- Enhanced User Menu -->
            <div class="flex items-center md:order-2 space-x-4 md:space-x-0 rtl:space-x-reverse">
                <div class="flex items-center gap-3">
                    <!-- User Dropdown -->
                    <button type="button" class="flex text-sm bg-white rounded-full md:me-0 focus:ring-4 focus:ring-red-300 user-menu-button relative" id="user-menu-button" aria-expanded="false" data-dropdown-toggle="user-dropdown" data-dropdown-placement="bottom">
                        <span class="sr-only">Open user menu</span>
                        <img class="w-12 h-12 rounded-full p-0.5 border-3 border-white shadow-lg" src="<?php echo htmlspecialchars($profile_picture); ?>" alt="user photo">
                        <div class="absolute -bottom-1 -right-1 w-4 h-4 bg-green-500 rounded-full border-2 border-white"></div>
                    </button>

                    <!-- Enhanced User Dropdown Menu -->
                    <div class="z-50 hidden my-4 text-base list-none glass-effect divide-y divide-gray-100 rounded-xl shadow-2xl dropdown-menu-animation" id="user-dropdown">
                        <div class="px-6 py-4 user-info-card rounded-t-xl">
                            <div class="flex items-center space-x-3">
                                <img class="w-10 h-10 rounded-full" src="<?php echo htmlspecialchars($profile_picture); ?>" alt="user photo">
                                <div>
                                    <span class="block text-sm font-bold text-gray-900">
                                        <?php echo htmlspecialchars($_SESSION['first_name'] ?? '') . ' ' . htmlspecialchars($_SESSION['last_name'] ?? ''); ?>
                                    </span>
                                    <span class="block text-xs text-gray-600 font-medium">
                                        <i class="fas fa-map-marker-alt mr-1 text-red-600"></i>
                                        <?php echo htmlspecialchars($_SESSION['site'] ?? '') ?>
                                    </span>
                                </div>
                            </div>
                            <div class="mt-4">
                                <p class="text-sm font-medium text-gray-600">ลายเซ็นปัจจุบัน</p>
                                <div class="flex space-x-4 mt-2">
                                    <div>
                                        <span class="text-xs text-gray-500">DAR:</span>
                                        <?php if ($dar_signature): ?>
                                            <img src="<?php echo htmlspecialchars($dar_signature); ?>" alt="DAR Signature" class="signature-preview">
                                        <?php else: ?>
                                            <span class="text-xs text-gray-500">ไม่มี</span>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <span class="text-xs text-gray-500">OT:</span>
                                        <?php if ($ot_signature): ?>
                                            <img src="<?php echo htmlspecialchars($ot_signature); ?>" alt="OT Signature" class="signature-preview">
                                        <?php else: ?>
                                            <span class="text-xs text-gray-500">ไม่มี</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <ul class="py-3" aria-labelledby="user-menu-button">
                            <li>
                                <a href="#" class="dropdown-item flex items-center px-6 py-3 text-sm text-gray-700 hover:text-red-600 transition-all duration-200" onclick="resetPassword()">
                                    <div class="w-8 h-8 bg-red-100 rounded-lg flex items-center justify-center mr-3">
                                        <i class="fas fa-key text-red-600"></i>
                                    </div>
                                    <div>
                                        <span class="font-medium">เปลี่ยนรหัสผ่าน</span>
                                        <p class="text-xs text-gray-500">อัปเดตรหัสผ่านของคุณ</p>
                                    </div>
                                </a>
                            </li>
                            <li>
                                <a href="http://eweb.krugerfan.com/it_request/it/edit_user.php?id=<?php echo htmlspecialchars($_SESSION['user_id']); ?>" class="dropdown-item flex items-center px-6 py-3 text-sm text-gray-700 hover:text-green-600 transition-all duration-200">
                                    <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                                        <i class="fas fa-user-edit text-green-600"></i>
                                    </div>
                                    <div>
                                        <span class="font-medium">แก้ไขโปรไฟล์</span>
                                        <p class="text-xs text-gray-500">แก้ไขข้อมูลส่วนตัวของคุณ</p>
                                    </div>
                                </a>
                            </li>
                            <li>
                                <a href="http://eweb.krugerfan.com/it_request/components/logout.php" class="dropdown-item flex items-center px-6 py-3 text-sm text-red-600 hover:text-red-700 transition-all duration-200">
                                    <div class="w-8 h-8 bg-red-100 rounded-lg flex items-center justify-center mr-3">
                                        <i class="fas fa-sign-out-alt text-red-600"></i>
                                    </div>
                                    <div>
                                        <span class="font-medium">ออกจากระบบ</span>
                                        <p class="text-xs text-gray-500">ออกจากบัญชีของคุณ</p>
                                    </div>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Enhanced Mobile menu button -->
                <button data-collapse-toggle="navbar-user" type="button" class="mobile-menu-button inline-flex items-center p-3 w-12 h-12 justify-center text-sm text-white rounded-xl md:hidden hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-300 transition-all duration-200" aria-controls="navbar-user" aria-expanded="false">
                    <span class="sr-only">Open main menu</span>
                    <i class="fas fa-bars text-lg"></i>
                </button>
            </div>

            <!-- Enhanced Main Navigation Menu -->
            <div class="items-center justify-between hidden w-full md:flex md:w-auto md:order-1" id="navbar-user">
                <ul class="flex flex-col font-medium p-4 md:p-0 mt-4 border border-red-800 rounded-lg bg-red-700 md:space-x-2 rtl:space-x-reverse md:flex-row md:mt-0 md:border-0 md:bg-transparent">
                    <!-- แจ้งซ่อม Link -->
                    <li>
                        <a href="form.php" class="nav-link flex items-center text-white hover:text-red-100 font-medium">
                            <i class="fas fa-tools mr-2 nav-icon"></i>
                            <span>แจ้งซ่อม</span>
                        </a>
                    </li>
                    <!-- Enhanced รายการแจ้งซ่อม Dropdown -->
                    <li class="relative">
                        <button id="dropdownNavbarLink" data-dropdown-toggle="dropdownNavbar" class="nav-link flex items-center justify-between w-full text-white hover:text-red-100 font-medium dropdown-toggle">
                            <span class="flex items-center">
                                <i class="fas fa-clipboard-list mr-2 nav-icon"></i>
                                <span>รายการแจ้งซ่อม</span>
                            </span>
                            <i class="fas fa-chevron-down ml-2 text-xs dropdown-icon"></i>
                        </button>

                        <!-- Enhanced Dropdown menu -->
                        <div id="dropdownNavbar" class="z-10 hidden font-normal glass-effect divide-y divide-gray-100 rounded-xl shadow-2xl w-72 dropdown-menu-animation">
                            <div class="py-3">
                                <div class="px-4 py-2">
                                    <h3 class="text-sm font-bold text-gray-800 mb-1">รายการแจ้งซ่อม</h3>
                                    <p class="text-xs text-gray-500">เลือกประเภทรายการที่ต้องการดู</p>
                                </div>
                            </div>
                            <ul class="py-3 text-sm text-gray-700" aria-labelledby="dropdownLargeButton">
                                <li>
                                    <a href="repair_requests.php" class="dropdown-item flex items-center px-4 py-4 hover:bg-gradient-to-r hover:from-red-50 hover:to-pink-50">
                                        <div class="w-10 h-10 bg-red-100 rounded-xl flex items-center justify-center mr-4">
                                            <i class="fas fa-clipboard text-red-600"></i>
                                        </div>
                                        <div class="flex-1">
                                            <span class="font-semibold text-gray-800">รายการที่แจ้ง</span>
                                            <p class="text-xs text-gray-500 mt-1">รายการแจ้งซ่อมทั้งหมดของคุณ</p>
                                        </div>
                                        <i class="fas fa-arrow-right text-gray-400 text-xs"></i>
                                    </a>
                                </li>
                                <li>
                                    <a href="changing_requests.php" class="dropdown-item flex items-center px-4 py-4 hover:bg-gradient-to-r hover:from-red-50 hover:to-pink-50">
                                        <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center mr-4">
                                            <i class="fas fa-sync-alt text-blue-600"></i>
                                        </div>
                                        <div class="flex-1">
                                            <span class="font-semibold text-gray-800">รายการที่เปลี่ยนแปลง</span>
                                            <p class="text-xs text-gray-500 mt-1">รายการที่อยู่ระหว่างดำเนินการ</p>
                                        </div>
                                        <i class="fas fa-arrow-right text-gray-400 text-xs"></i>
                                    </a>
                                </li>
                                <li>
                                    <a href="completed_requests.php" class="dropdown-item flex items-center px-4 py-4 hover:bg-gradient-to-r hover:from-red-50 hover:to-pink-50">
                                        <div class="w-10 h-10 bg-green-100 rounded-xl flex items-center justify-center mr-4">
                                            <i class="fas fa-check-circle text-green-600"></i>
                                        </div>
                                        <div class="flex-1">
                                            <span class="font-semibold text-gray-800">รายการที่เสร็จ</span>
                                            <p class="text-xs text-gray-500 mt-1">รายการที่ดำเนินการเสร็จสิ้น</p>
                                        </div>
                                        <i class="fas fa-arrow-right text-gray-400 text-xs"></i>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                    <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'admin'): ?>
                        <!-- ===================== -->
                        <!-- Printer Dropdown -->
                        <!-- ===================== -->
                        <li class="relative group">
                            <!-- 🔹 Button -->
                            <button id="dropdownPrinter" data-dropdown-toggle="dropdownPrinterMenu"
                                class="nav-link flex items-center justify-between w-full text-white hover:text-red-200 font-medium transition-all duration-200">

                                <span class="flex items-center gap-2">
                                    <i class="fas fa-user-shield nav-icon text-red-300"></i>
                                    <span class="tracking-wide">Admin</span>
                                </span>

                                <i class="fas fa-chevron-down text-xs transition-transform duration-200 group-hover:rotate-180"></i>
                            </button>

                            <div id="dropdownPrinterMenu"
                                class="absolute left-0 mt-3 w-72 hidden rounded-2xl shadow-xl bg-white border border-gray-200 overflow-hidden dropdown-menu-animation">

                                <ul class="py-2 text-sm text-gray-700">

                                    <!-- 👤 Users -->
                                    <li>
                                        <a href="user_list.php"
                                            class="flex items-center gap-3 px-5 py-3 hover:bg-blue-50 transition-all duration-150">
                                            <i class="fas fa-users text-blue-500 text-lg"></i>
                                            <span class="font-medium">รายการผู้ใช้</span>
                                        </a>
                                    </li>

                                    <!-- 💻 Assets -->
                                    <li>
                                        <a href="asset_list.php"
                                            class="flex items-center gap-3 px-5 py-3 hover:bg-indigo-50 transition-all duration-150">
                                            <i class="fas fa-laptop text-indigo-500 text-lg"></i>
                                            <span class="font-medium">รายการทรัพย์สิน</span>
                                        </a>
                                    </li>

                                    <!-- 🖨 Printer -->
                                    <li>
                                        <a href="upload_printer.php"
                                            class="flex items-center gap-3 px-5 py-3 hover:bg-yellow-50 transition-all duration-150">
                                            <i class="fas fa-print text-yellow-500 text-lg"></i>
                                            <span class="font-medium">Printer Upload</span>
                                        </a>
                                    </li>

                                    <!-- 📊 Report -->
                                    <li>
                                        <a href="dashboard.php"
                                            class="flex items-center gap-3 px-5 py-3 hover:bg-green-50 transition-all duration-150">
                                            <i class="fas fa-chart-line text-green-500 text-lg"></i>
                                            <span class="font-medium">Report</span>
                                        </a>
                                    </li>

                                </ul>
                            </div>
                        </li>





                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const dropdownToggle = document.getElementById('dropdownNavbarLink');
            const dropdownMenu = document.getElementById('dropdownNavbar');

            if (dropdownToggle) {
                dropdownToggle.addEventListener('click', function() {
                    this.classList.toggle('dropdown-open');
                });
            }

            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function(e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });
        });

        function toggleDropdown() {
            var dropdownMenu = document.getElementById('dropdownMenu');
            dropdownMenu.classList.toggle('hidden');
        }

        function toggleMobileMenu() {
            var mobileMenu = document.getElementById('navbar-user');
            mobileMenu.classList.toggle('hidden');
        }

        function confirmLogout() {
            Swal.fire({
                title: 'คุณแน่ใจหรือไม่?',
                text: "คุณต้องการออกจากระบบหรือไม่?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#c41e3a',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'ใช่, ออกจากระบบ!',
                cancelButtonText: 'ยกเลิก',
                background: '#fff',
                customClass: {
                    popup: 'rounded-xl shadow-2xl',
                    title: 'text-gray-800',
                    content: 'text-gray-600'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'http://eweb.krugerfan.com/it_request/components/logout.php';
                }
            });
        }

        function resetPassword() {
            Swal.fire({
                title: 'เปลี่ยนรหัสผ่าน',
                input: 'password',
                inputLabel: 'กรุณาใส่รหัสผ่านใหม่',
                inputPlaceholder: 'ใส่รหัสผ่านใหม่',
                showCancelButton: true,
                confirmButtonText: 'เปลี่ยนรหัสผ่าน',
                cancelButtonText: 'ยกเลิก',
                confirmButtonColor: '#c41e3a',
                cancelButtonColor: '#6b7280',
                background: '#fff',
                customClass: {
                    popup: 'rounded-xl shadow-2xl',
                    title: 'text-gray-800',
                    input: 'rounded-lg border-2 border-gray-200 focus:border-red-500'
                },
                preConfirm: (newPassword) => {
                    if (!newPassword) {
                        Swal.showValidationMessage('กรุณาใส่รหัสผ่านใหม่');
                        return false;
                    }
                    if (newPassword.length < 6) {
                        Swal.showValidationMessage('รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร');
                        return false;
                    }
                    return fetch(`change_password.php`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                newPassword: newPassword
                            })
                        })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error(response.statusText)
                            }
                            return response.json()
                        })
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    title: 'สำเร็จ!',
                                    text: 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว',
                                    icon: 'success',
                                    confirmButtonColor: '#c41e3a',
                                    customClass: {
                                        popup: 'rounded-xl shadow-2xl'
                                    }
                                });
                            } else {
                                Swal.fire({
                                    title: 'ผิดพลาด!',
                                    text: 'ไม่สามารถเปลี่ยนรหัสผ่านได้',
                                    icon: 'error',
                                    confirmButtonColor: '#c41e3a',
                                    customClass: {
                                        popup: 'rounded-xl shadow-2xl'
                                    }
                                });
                            }
                        })
                        .catch(error => {
                            Swal.fire({
                                title: 'ผิดพลาด!',
                                text: error.message,
                                icon: 'error',
                                confirmButtonColor: '#c41e3a',
                                customClass: {
                                    popup: 'rounded-xl shadow-2xl'
                                }
                            });
                        })
                }
            })
        }
    </script>
</body>

</html>