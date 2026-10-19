<?php
session_start();
include 'connect.php';
include 'auth.php'; // ตรวจสอบการล็อกอิน
// ตรวจสอบสิทธิ์การเข้าถึง
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้';
    header('Location: index.php');
    exit();
}

// ตั้งค่าจำนวนรายการต่อหน้า
$items_per_page = 8;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;

// รับค่าค้นหาจากฟอร์ม
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$dept_name_filter = isset($_GET['dept_name']) ? $_GET['dept_name'] : '';
$search_filter = isset($_GET['search']) ? $_GET['search'] : '';

// ป้องกัน SQL Injection โดยใช้ PDO quote
$status_filter = !empty($status_filter) ? $conn->quote($status_filter) : '';
$dept_name_filter = !empty($dept_name_filter) ? $conn->quote($dept_name_filter) : '';
$search_filter = !empty($search_filter) ? $conn->quote("%$search_filter%") : '';

// ดึงข้อมูลแผนกจากฐานข้อมูล
$dept_sql = "SELECT id, dept_name FROM departments";
$dept_result = $conn->query($dept_sql);
$departments = $dept_result->fetchAll(PDO::FETCH_ASSOC);

// สร้างเงื่อนไขค้นหา (แทนค่าโดยตรง)
$whereClause = " WHERE 1=1 ";
if (!empty($status_filter)) {
    $whereClause .= " AND users.status = $status_filter ";
}
if (!empty($dept_name_filter)) {
    $whereClause .= " AND departments.dept_name = $dept_name_filter ";
}
if (!empty($search_filter)) {
    $whereClause .= " AND (
        users.username LIKE $search_filter
        OR users.employee_id LIKE $search_filter 
        OR users.first_name LIKE $search_filter 
        OR users.last_name LIKE $search_filter
    ) ";
}

// SQL ดึงข้อมูล
$sql = "SELECT users.*, departments.dept_name FROM users 
        LEFT JOIN departments ON users.department = departments.id
        $whereClause
        ORDER BY users.id DESC
        OFFSET $offset ROWS FETCH NEXT $items_per_page ROWS ONLY";

$stmt = $conn->prepare($sql);

try {
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Query failed: " . $e->getMessage());
}

// คำนวณจำนวนหน้าทั้งหมด
$total_sql = "SELECT COUNT(*) as total FROM users 
              LEFT JOIN departments ON users.department = departments.id
              $whereClause";

$total_stmt = $conn->prepare($total_sql);

try {
    $total_stmt->execute();
    $total_items = $total_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_items / $items_per_page);
} catch (PDOException $e) {
    die("Total Query failed: " . $e->getMessage());
}

// Pagination logic: แสดงแค่ 5 หน้า
$max_pages_to_show = 5;
$half_pages = floor($max_pages_to_show / 2);

// คำนวณหน้าเริ่มต้นและหน้าสิ้นสุด
$start_page = max(1, $current_page - $half_pages);
$end_page = min($total_pages, $start_page + $max_pages_to_show - 1);

// ปรับหน้าเริ่มต้นถ้าจำนวนหน้าที่แสดงไม่ครบ 5 หน้า
if ($end_page - $start_page + 1 < $max_pages_to_show) {
    $start_page = max(1, $end_page - $max_pages_to_show + 1);
}

// ฟังก์ชันสำหรับกำหนดสี Status
function getStatusColor($status) {
    switch (strtolower($status)) {
        case 'active':
            return 'bg-green-100 text-green-800 border-green-200';
        case 'inactive':
            return 'bg-red-100 text-red-800 border-red-200';
        default:
            return 'bg-gray-100 text-gray-800 border-gray-200';
    }
}

// ฟังก์ชันสำหรับกำหนดสี User Type
function getUserTypeColor($userType) {
    switch (strtolower($userType)) {
        case 'admin':
            return 'bg-purple-100 text-purple-800 border-purple-200';
        case 'user':
            return 'bg-blue-100 text-blue-800 border-blue-200';
        default:
            return 'bg-gray-100 text-gray-800 border-gray-200';
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการผู้ใช้ - User Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="styles.css" rel="stylesheet">
    <style>
        .table-container {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            padding: 2px;
        }
        .table-inner {
            background: white;
            border-radius: 13px;
            overflow: hidden;
        }
        .compact-badge {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.65rem;
            font-weight: 600;
            border: 1px solid;
            display: inline-block;
            text-align: center;
            min-width: 60px;
            line-height: 1.2;
        }
        .action-btn {
            transition: all 0.2s ease;
            padding: 4px 8px;
            font-size: 0.75rem;
        }
        .action-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        .table-row:hover {
            background: linear-gradient(90deg, #f8fafc 0%, #e2e8f0 100%);
        }
        .header-gradient {
            background: linear-gradient(135deg, #1e40af 0%, #7c3aed 100%);
        }
        .filter-container {
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #cbd5e1;
        }
        .compact-table {
            font-size: 0.8rem;
        }
        .compact-table th {
            padding: 10px 6px;
            font-size: 0.7rem;
        }
        .compact-table td {
            padding: 8px 6px;
            font-size: 0.75rem;
        }
        .user-avatar {
            width: 32px;
            height: 32px;
            font-size: 0.7rem;
        }
        .compact-name {
            max-width: 100px;
        }
        .filter-input {
            transition: all 0.3s ease;
        }
        .filter-input:focus {
            transform: scale(1.02);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
    </style>
</head>

<body class="bg-gray-50 min-h-screen">
    <?php include 'nav.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <!-- Header Section -->
        <div class="text-center mb-8">
            <div class="header-gradient text-white rounded-xl p-6 shadow-xl">
                <h1 class="text-3xl font-bold mb-2">
                    <i class="fas fa-users mr-3"></i>
                    ระบบจัดการผู้ใช้
                </h1>
                <p class="text-lg opacity-90">
                    จัดการข้อมูลผู้ใช้ในระบบ
                </p>
                <div class="mt-4 text-sm opacity-80">
                    <i class="fas fa-list mr-2"></i>
                    รายการทั้งหมด: <?php echo $total_items; ?> ผู้ใช้
                </div>
            </div>
        </div>

        <!-- Enhanced Filter Section -->
        <div class="filter-container">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <div>
                    <label for="status" class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-toggle-on mr-1 text-red-600"></i>
                        สถานะ
                    </label>
                    <select name="status" id="status" class="filter-input w-full border-2 border-gray-200 rounded-lg px-3 py-2 focus:border-blue-500 focus:ring-1 focus:ring-blue-200 transition-all duration-300">
                        <option value="">-- ทั้งหมด --</option>
                        <option value="active" <?= ($status_filter == "'active'") ? 'selected' : '' ?>>
                            <i class="fas fa-check-circle"></i> Active
                        </option>
                        <option value="inactive" <?= ($status_filter == "'inactive'") ? 'selected' : '' ?>>
                            <i class="fas fa-times-circle"></i> Inactive
                        </option>
                    </select>
                </div>

                <div>
                    <label for="dept_name" class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-building mr-1 text-red-600"></i>
                        แผนก
                    </label>
                    <select name="dept_name" id="dept_name" class="filter-input w-full border-2 border-gray-200 rounded-lg px-3 py-2 focus:border-blue-500 focus:ring-1 focus:ring-blue-200 transition-all duration-300">
                        <option value="">-- ทั้งหมด --</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['dept_name']; ?>" <?php if ($dept['dept_name'] == trim($dept_name_filter, "'")) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($dept['dept_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="search" class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-search mr-1 text-red-600"></i>
                        ค้นหา
                    </label>
                    <input type="text" name="search" id="search" 
                           class="filter-input w-full border-2 border-gray-200 rounded-lg px-3 py-2 focus:border-blue-500 focus:ring-1 focus:ring-blue-200 transition-all duration-300" 
                           value="<?= htmlspecialchars(trim($search_filter, "'%")) ?>" 
                           placeholder="ชื่อ, นามสกุล, หรือรหัสพนักงาน">
                </div>

                <div class="flex gap-2">
                    <button type="submit" 
                            class="bg-gradient-to-r from-blue-500 to-purple-600 hover:from-blue-600 hover:to-purple-700 text-white font-semibold py-2 px-6 rounded-lg transition-all duration-300 transform hover:scale-105 shadow-lg">
                        <i class="fas fa-search mr-2"></i>ค้นหา
                    </button>
                    <a href="?" 
                       class="bg-gradient-to-r from-gray-500 to-gray-600 hover:from-gray-600 hover:to-gray-700 text-white font-semibold py-2 px-4 rounded-lg transition-all duration-300 transform hover:scale-105 shadow-lg">
                        <i class="fas fa-refresh mr-1"></i>ล้าง
                    </a>
                </div>
            </form>
        </div>

        <!-- Enhanced Table Section -->
        <div class="table-container shadow-2xl">
            <div class="table-inner">
                <div class="overflow-x-auto">
                    <table class="min-w-full compact-table">
                        <thead>
                            <tr class="bg-gradient-to-r from-red-600 to-pink-600 text-white">
                                <th class="text-left font-semibold">
                                    <i class="fas fa-user"></i><br>
                                    <span class="text-xs">ผู้ใช้</span>
                                </th>
                                <th class="text-left font-semibold">
                                    <i class="fas fa-id-card"></i><br>
                                    <span class="text-xs">ชื่อจริง</span>
                                </th>
                                <th class="text-left font-semibold">
                                    <i class="fas fa-id-badge"></i><br>
                                    <span class="text-xs">นามสกุล</span>
                                </th>
                                <th class="text-left font-semibold">
                                    <i class="fas fa-hashtag"></i><br>
                                    <span class="text-xs">รหัสพนักงาน</span>
                                </th>
                                <th class="text-left font-semibold">
                                    <i class="fas fa-building"></i><br>
                                    <span class="text-xs">แผนก</span>
                                </th>
                                <th class="text-left font-semibold">
                                    <i class="fas fa-map-marker-alt"></i><br>
                                    <span class="text-xs">บริษัท</span>
                                </th>
                                <th class="text-left font-semibold">
                                    <i class="fas fa-toggle-on"></i><br>
                                    <span class="text-xs">สถานะ</span>
                                </th>
                                <th class="text-left font-semibold">
                                    <i class="fas fa-user-tag"></i><br>
                                    <span class="text-xs">ประเภท</span>
                                </th>
                                <th class="text-center font-semibold">
                                    <i class="fas fa-cogs"></i><br>
                                    <span class="text-xs">จัดการ</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if (empty($result)): ?>
                                <tr>
                                    <td colspan="9" class="py-12 text-center text-gray-500">
                                        <div class="flex flex-col items-center">
                                            <i class="fas fa-users text-6xl text-gray-300 mb-4"></i>
                                            <p class="text-xl font-medium">ไม่พบข้อมูลผู้ใช้</p>
                                            <p class="text-sm mt-2">ลองค้นหาด้วยคำค้นอื่น หรือเพิ่มผู้ใช้ใหม่</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($result as $index => $user): ?>
                                    <tr class="table-row transition-all duration-200 <?php echo $index % 2 == 0 ? 'bg-white' : 'bg-gray-50'; ?>">
                                        <td>
                                            <div class="flex items-center">
                                                <div class="user-avatar bg-gradient-to-r from-blue-400 to-purple-500 rounded-full flex items-center justify-center text-white font-bold mr-3">
                                                    <?php echo strtoupper(substr($user['username'], 0, 2)); ?>
                                                </div>
                                                <div class="compact-name">
                                                    <div class="font-semibold text-gray-800 truncate"><?= htmlspecialchars($user['username']); ?></div>
                                                    <div class="text-xs text-gray-500">@<?= htmlspecialchars(substr($user['username'], 0, 8)); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="font-medium text-gray-800"><?= htmlspecialchars($user['first_name']); ?></span>
                                        </td>
                                        <td>
                                            <span class="font-medium text-gray-800"><?= htmlspecialchars($user['last_name']); ?></span>
                                        </td>
                                        <td>
                                            <span class="font-mono text-blue-600 font-semibold"><?= htmlspecialchars($user['employee_id']); ?></span>
                                        </td>
                                        <td>
                                            <span class="compact-badge bg-purple-100 text-purple-800">
                                                <?= htmlspecialchars(substr($user['dept_name'], 0, 10)); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="compact-badge bg-blue-100 text-blue-800">
                                                <i class="fas fa-building mr-1"></i>
                                                <?= htmlspecialchars(substr($user['site'], 0, 8)); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="compact-badge <?php echo getStatusColor($user['status']); ?>">
                                                <?php 
                                                $status = $user['status'];
                                                $statusIcon = '';
                                                $statusText = '';
                                                switch (strtolower($status)) {
                                                    case 'active':
                                                        $statusIcon = 'fas fa-check-circle';
                                                        $statusText = 'Active';
                                                        break;
                                                    case 'inactive':
                                                        $statusIcon = 'fas fa-times-circle';
                                                        $statusText = 'Inactive';
                                                        break;
                                                    default:
                                                        $statusIcon = 'fas fa-question-circle';
                                                        $statusText = $status;
                                                }
                                                ?>
                                                <i class="<?php echo $statusIcon; ?> mr-1"></i>
                                                <?php echo $statusText; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="compact-badge <?php echo getUserTypeColor($user['user_type']); ?>">
                                                <?php 
                                                $userType = $user['user_type'];
                                                $typeIcon = '';
                                                switch (strtolower($userType)) {
                                                    case 'admin':
                                                        $typeIcon = 'fas fa-crown';
                                                        break;
                                                    case 'user':
                                                        $typeIcon = 'fas fa-user';
                                                        break;
                                                    default:
                                                        $typeIcon = 'fas fa-question-circle';
                                                }
                                                ?>
                                                <i class="<?php echo $typeIcon; ?> mr-1"></i>
                                                <?= htmlspecialchars($userType); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="flex space-x-2 justify-center">
                                                <a href="edit_user.php?id=<?= $user['id']; ?>" 
                                                   class="action-btn bg-blue-500 hover:bg-blue-600 text-white rounded-lg shadow-sm transition-all duration-200"
                                                   title="แก้ไขผู้ใช้">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button onclick="confirmDelete_user(<?= $user['id']; ?>, '<?= $_SESSION['_token'] ?? ''; ?>')" 
                                                        class="action-btn bg-red-500 hover:bg-red-600 text-white rounded-lg shadow-sm transition-all duration-200"
                                                        title="ลบผู้ใช้">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Enhanced Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="mt-8 flex justify-center">
                <nav class="flex items-center space-x-1">
                    <?php if ($current_page > 1): ?>
                        <a href="?page=<?php echo $current_page - 1; ?>&status=<?php echo htmlspecialchars(trim($status_filter, "'")); ?>&dept_name=<?php echo htmlspecialchars(trim($dept_name_filter, "'")); ?>&search=<?php echo htmlspecialchars(trim($search_filter, "'%")); ?>" 
                           class="bg-white hover:bg-gray-50 text-gray-700 font-medium py-2 px-4 rounded-l-lg border border-gray-300 transition-all duration-300 hover:shadow-md">
                            <i class="fas fa-chevron-left mr-1"></i>ก่อนหน้า
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&status=<?php echo htmlspecialchars(trim($status_filter, "'")); ?>&dept_name=<?php echo htmlspecialchars(trim($dept_name_filter, "'")); ?>&search=<?php echo htmlspecialchars(trim($search_filter, "'%")); ?>" 
                           class="<?php echo $i == $current_page ? 'bg-gradient-to-r from-blue-500 to-purple-600 text-white' : 'bg-white hover:bg-gray-50 text-gray-700'; ?> font-medium py-2 px-4 border border-gray-300 transition-all duration-300 hover:shadow-md">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($current_page < $total_pages): ?>
                        <a href="?page=<?php echo $current_page + 1; ?>&status=<?php echo htmlspecialchars(trim($status_filter, "'")); ?>&dept_name=<?php echo htmlspecialchars(trim($dept_name_filter, "'")); ?>&search=<?php echo htmlspecialchars(trim($search_filter, "'%")); ?>" 
                           class="bg-white hover:bg-gray-50 text-gray-700 font-medium py-2 px-4 rounded-r-lg border border-gray-300 transition-all duration-300 hover:shadow-md">
                            ถัดไป<i class="fas fa-chevron-right ml-1"></i>
                        </a>
                    <?php endif; ?>
                </nav>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function confirmDelete_user(userId, token) {
            console.log("Function called with userId:", userId, "and token:", token);
            Swal.fire({
                title: 'คุณแน่ใจหรือไม่?',
                text: "คุณต้องการลบผู้ใช้นี้หรือไม่?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'ใช่, ลบเลย!',
                cancelButtonText: 'ยกเลิก',
                background: '#fff',
                customClass: {
                    popup: 'rounded-xl shadow-2xl',
                    title: 'text-gray-800',
                    content: 'text-gray-600'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'delete_user.php?id=' + userId + '&token=' + token;
                }
            });
        }

        // Add smooth transitions for filter inputs
        document.addEventListener('DOMContentLoaded', function() {
            const filterInputs = document.querySelectorAll('.filter-input');
            filterInputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.classList.add('transform', 'scale-102');
                });
                input.addEventListener('blur', function() {
                    this.parentElement.classList.remove('transform', 'scale-102');
                });
            });
        });
    </script>
</body>
</html>