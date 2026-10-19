<?php
session_start();
include 'connect.php';
include 'auth.php';
include 'function.php';

// ฟังก์ชันเพื่อดึงชื่อแผนก
function get_department_name($conn, $user_id) {
    try {
        $sql = "SELECT d.dept_name FROM users u
                JOIN departments d ON u.department = d.id
                WHERE u.id = :user_id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['dept_name'] : null;
    } catch (PDOException $e) {
        error_log("Error in get_department_name: " . $e->getMessage());
        return null;
    }
}

// ฟังก์ชันเพื่อดึงไซต์ของผู้ใช้
function get_user_site($conn, $user_id) {
    try {
        $sql = "SELECT site FROM users WHERE id = :user_id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['site'] : null;
    } catch (PDOException $e) {
        error_log("Error in get_user_site: " . $e->getMessage());
        return null;
    }
}

// ฟังก์ชันสำหรับกำหนดสี Priority
function getPriorityColor($priority) {
    switch (strtolower($priority)) {
        case 'high':
        case 'สูง':
            return 'bg-red-100 text-red-800 border-red-200';
        case 'medium':
        case 'กลาง':
            return 'bg-yellow-100 text-yellow-800 border-yellow-200';
        case 'low':
        case 'ต่ำ':
            return 'bg-green-100 text-green-800 border-green-200';
        default:
            return 'bg-gray-100 text-gray-800 border-gray-200';
    }
}

// ฟังก์ชันสำหรับกำหนดสี Status
function getStatusColor($status) {
    switch (strtolower($status)) {
        case 'pending':
            return 'bg-orange-100 text-orange-800 border-orange-200';
        case 'in progress':
            return 'bg-blue-100 text-blue-800 border-blue-200';
        case 'completed':
            return 'bg-green-100 text-green-800 border-green-200';
        case 'cancelled':
            return 'bg-red-100 text-red-800 border-red-200';
        default:
            return 'bg-gray-100 text-gray-800 border-gray-200';
    }
}

// ตั้งค่าจำนวนรายการต่อหน้า
$items_per_page = 10;

// ตรวจสอบหน้าปัจจุบัน
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;

// ดึงชื่อแผนกและไซต์ของผู้ใช้ที่ล็อกอิน (ใช้ session caching)
$user_id = $_SESSION['user_id'];
if (!isset($_SESSION['dept_name'])) {
    $_SESSION['dept_name'] = get_department_name($conn, $user_id);
}
if (!isset($_SESSION['user_site'])) {
    $_SESSION['user_site'] = get_user_site($conn, $user_id);
}
$dept_name = $_SESSION['dept_name'];
$user_site = $_SESSION['user_site'];

// ตรวจสอบประเภทผู้ใช้
$user_type = $_SESSION['user_type'];

$search = isset($_GET['search']) ? $_GET['search'] : '';
$operator_filter = isset($_GET['operator_filter']) ? $_GET['operator_filter'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

function get_all_operators($conn, $user_type, $dept_name, $user_site) {
    try {
        if ($user_type == 'admin') {
            $sql = "SELECT DISTINCT operator FROM repair_requests WHERE operator IS NOT NULL AND operator != '' ORDER BY operator";
            $stmt = $conn->prepare($sql);
        } else {
            $sql = "SELECT DISTINCT operator FROM repair_requests WHERE operator IS NOT NULL AND operator != '' AND department = :dept_name AND site = :user_site ORDER BY operator";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':dept_name', $dept_name, PDO::PARAM_STR);
            $stmt->bindParam(':user_site', $user_site, PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        error_log("Error in get_all_operators: " . $e->getMessage());
        return [];
    }
}

// ดึงรายชื่อ operator ทั้งหมด
$operators = get_all_operators($conn, $user_type, $dept_name, $user_site);

if ($user_type == 'admin') {
    $sql = "SELECT * FROM repair_requests WHERE (status = 'Pending' OR status = 'In Progress')";
} else {
    $sql = "SELECT * FROM repair_requests WHERE (status = 'Pending' OR status = 'In Progress') AND department = :dept_name AND site = :user_site";
}

// เพิ่มเงื่อนไขการค้นหา
if (!empty($search)) {
    $search_term = "%$search%";
    $sql .= " AND requester LIKE :search";
}

if (!empty($operator_filter)) {
    $sql .= " AND operator = :operator_filter";
}

if (!empty($date_from)) {
    $sql .= " AND DATE(request_date) >= :date_from";
}
if (!empty($date_to)) {
    $sql .= " AND DATE(request_date) <= :date_to";
}

$sql .= " ORDER BY issue_id DESC OFFSET :offset ROWS FETCH NEXT :items_per_page ROWS ONLY";
$stmt = $conn->prepare($sql);

// ผูกพารามิเตอร์
if ($user_type != 'admin') {
    $stmt->bindParam(':dept_name', $dept_name, PDO::PARAM_STR);
    $stmt->bindParam(':user_site', $user_site, PDO::PARAM_STR);
}
if (!empty($search)) {
    $stmt->bindParam(':search', $search_term, PDO::PARAM_STR);
}
// เพิ่มพารามิเตอร์การกรอง
if (!empty($operator_filter)) {
    $stmt->bindParam(':operator_filter', $operator_filter, PDO::PARAM_STR);
}
if (!empty($date_from)) {
    $stmt->bindParam(':date_from', $date_from, PDO::PARAM_STR);
}
if (!empty($date_to)) {
    $stmt->bindParam(':date_to', $date_to, PDO::PARAM_STR);
}
$stmt->bindParam(':items_per_page', $items_per_page, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);

try {
    $stmt->execute();
    $repairRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error executing query: " . $e->getMessage());
    $repairRequests = [];
}

if ($user_type == 'admin') {
    $total_sql = "SELECT COUNT(*) as total FROM repair_requests WHERE (status = 'Pending' OR status = 'In Progress')";
} else {
    $total_sql = "SELECT COUNT(*) as total FROM repair_requests WHERE (status = 'Pending' OR status = 'In Progress') AND department = :dept_name AND site = :user_site";
}

if (!empty($search)) {
    $total_sql .= " AND requester LIKE :search";
}
if (!empty($operator_filter)) {
    $total_sql .= " AND operator = :operator_filter";
}
if (!empty($date_from)) {
    $total_sql .= " AND DATE(request_date) >= :date_from";
}
if (!empty($date_to)) {
    $total_sql .= " AND DATE(request_date) <= :date_to";
}

$stmt_total = $conn->prepare($total_sql);
if ($user_type != 'admin') {
    $stmt_total->bindParam(':dept_name', $dept_name, PDO::PARAM_STR);
    $stmt_total->bindParam(':user_site', $user_site, PDO::PARAM_STR);
}
if (!empty($search)) {
    $stmt_total->bindParam(':search', $search_term, PDO::PARAM_STR);
}
if (!empty($operator_filter)) {
    $stmt_total->bindParam(':operator_filter', $operator_filter, PDO::PARAM_STR);
}
if (!empty($date_from)) {
    $stmt_total->bindParam(':date_from', $date_from, PDO::PARAM_STR);
}
if (!empty($date_to)) {
    $stmt_total->bindParam(':date_to', $date_to, PDO::PARAM_STR);
}

try {
    $stmt_total->execute();
    $total_items = $stmt_total->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_items / $items_per_page);
} catch (PDOException $e) {
    error_log("Error executing total count query: " . $e->getMessage());
    $total_items = 0;
    $total_pages = 0;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการแจ้งซ่อม - Repair Request System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
            padding: 2px 6px;
            border-radius: 12px;
            font-size: 0.65rem;
            font-weight: 600;
            border: 1px solid;
            display: inline-block;
            text-align: center;
            min-width: 50px;
            line-height: 1.2;
        }
        .action-btn {
            transition: all 0.2s ease;
            padding: 4px 6px;
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
        .search-container {
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 15px;
            border: 1px solid #cbd5e1;
        }
        .compact-table {
            font-size: 0.8rem;
        }
        .compact-table th {
            padding: 8px 4px;
            font-size: 0.7rem;
        }
        .compact-table td {
            padding: 6px 4px;
            font-size: 0.75rem;
        }
        .user-avatar {
            width: 24px;
            height: 24px;
            font-size: 0.6rem;
        }
        .compact-title {
            max-width: 120px;
        }
        .compact-name {
            max-width: 80px;
        }
        /* เพิ่ม CSS สำหรับ filter section */
        .filter-section {
            background: linear-gradient(135deg, #e0f2fe 0%, #f3e5f5 100%);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            border: 1px solid #b39ddb;
        }
        .filter-input {
            border: 2px solid #e1bee7;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 0.875rem;
            transition: all 0.3s ease;
        }
        .filter-input:focus {
            border-color: #9c27b0;
            box-shadow: 0 0 0 3px rgba(156, 39, 176, 0.1);
            outline: none;
        }
        .filter-label {
            font-weight: 600;
            color: #4a148c;
            margin-bottom: 5px;
            display: block;
            font-size: 0.875rem;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <?php include 'nav.php'; ?>
    
    <div class="container mx-auto px-2 py-6">
        <!-- Header Section -->
        <div class="text-center mb-6">
            <div class="header-gradient text-white rounded-xl p-4 shadow-xl">
                <h1 class="text-2xl font-bold mb-2">
                    <i class="fas fa-tools mr-2"></i>
                    ระบบจัดการแจ้งซ่อม
                </h1>
                <p class="text-sm opacity-90">
                    <?php echo htmlspecialchars($dept_name ?? 'ทุกแผนก'); ?> - Site: <?php echo htmlspecialchars($user_site ?? 'ทุกไซต์'); ?>
                </p>
                <div class="mt-2 text-xs opacity-80">
                    <i class="fas fa-list mr-1"></i>
                    รายการทั้งหมด: <?php echo $total_items; ?> รายการ
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" class="space-y-4">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-lg font-bold text-purple-800">
                        <i class="fas fa-filter mr-2"></i>ตัวกรองข้อมูล
                    </h3>
                    <?php if (!empty($search) || !empty($operator_filter) || !empty($date_from) || !empty($date_to)): ?>
                        <a href="?" class="bg-gradient-to-r from-gray-500 to-gray-600 hover:from-gray-600 hover:to-gray-700 text-white font-semibold py-2 px-4 rounded-lg transition-all duration-300 text-sm">
                            <i class="fas fa-times mr-1"></i>ล้างตัวกรอง
                        </a>
                    <?php endif; ?>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Search Input -->
                    <div>
                        <label class="filter-label">
                            <i class="fas fa-search mr-1"></i>ค้นหาผู้แจ้ง
                        </label>
                        <input type="text" 
                               name="search" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="ชื่อผู้แจ้งหรือเลขที่แจ้งซ่อม" 
                               class="filter-input w-full">
                    </div>

                    <!-- Operator Filter -->
                    <div>
                        <label class="filter-label">
                            <i class="fas fa-user-cog mr-1"></i>ผู้รับผิดชอบ
                        </label>
                        <select name="operator_filter" class="filter-input w-full">
                            <option value="">-- ทั้งหมด --</option>
                            <?php foreach ($operators as $operator): ?>
                                <option value="<?php echo htmlspecialchars($operator); ?>" 
                                        <?php echo ($operator_filter == $operator) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($operator); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Date From -->
                    <div>
                        <label class="filter-label">
                            <i class="fas fa-calendar-alt mr-1"></i>วันที่เริ่มต้น
                        </label>
                        <input type="date" 
                               name="date_from" 
                               value="<?php echo htmlspecialchars($date_from); ?>" 
                               class="filter-input w-full">
                    </div>

                    <!-- Date To -->
                    <div>
                        <label class="filter-label">
                            <i class="fas fa-calendar-check mr-1"></i>วันที่สิ้นสุด
                        </label>
                        <input type="date" 
                               name="date_to" 
                               value="<?php echo htmlspecialchars($date_to); ?>" 
                               class="filter-input w-full">
                    </div>
                </div>

                <div class="flex justify-center pt-3">
                    <button type="submit" 
                            class="bg-gradient-to-r from-purple-500 to-pink-600 hover:from-purple-600 hover:to-pink-700 text-white font-semibold py-2 px-6 rounded-lg transition-all duration-300 text-sm shadow-lg">
                        <i class="fas fa-search mr-2"></i>ค้นหาและกรอง
                    </button>
                </div>
            </form>
        </div>

        <!-- Search Section (เก็บไว้เพื่อความเข้ากันได้) -->
        <div class="search-container">
            <form method="GET" class="flex flex-wrap items-center gap-3">
                <div class="flex-1 min-w-48">
                    <div class="relative">
                        <i class="fas fa-search absolute left-2 top-1/2 transform -translate-y-1/2 text-gray-400 text-sm"></i>
                        <input type="text" 
                               name="search" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="ค้นหาจากชื่อผู้แจ้งหรือเลขที่แจ้งซ่อม" 
                               class="w-full pl-8 pr-3 py-2 text-sm border-2 border-gray-200 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-200 transition-all duration-300">
                        <!-- เพิ่ม hidden inputs เพื่อรักษาค่า filter -->
                        <input type="hidden" name="operator_filter" value="<?php echo htmlspecialchars($operator_filter); ?>">
                        <input type="hidden" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                        <input type="hidden" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                </div>
                <button type="submit" 
                        class="bg-gradient-to-r from-blue-500 to-purple-600 hover:from-blue-600 hover:to-purple-700 text-white font-semibold py-2 px-4 rounded-lg transition-all duration-300 text-sm">
                    <i class="fas fa-search mr-1"></i>ค้นหา
                </button>
                <?php if (!empty($search)): ?>
                    <a href="?operator_filter=<?php echo htmlspecialchars($operator_filter); ?>&date_from=<?php echo htmlspecialchars($date_from); ?>&date_to=<?php echo htmlspecialchars($date_to); ?>" class="bg-gradient-to-r from-gray-500 to-gray-600 hover:from-gray-600 hover:to-gray-700 text-white font-semibold py-2 px-4 rounded-lg transition-all duration-300 text-sm">
                        <i class="fas fa-times mr-1"></i>ล้าง
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Table Section -->
        <div class="table-container shadow-xl">
            <div class="table-inner">
                <div class="overflow-x-auto">
                    <table class="min-w-full compact-table">
                        <thead>
                            <tr class="bg-gradient-to-r from-red-600 to-pink-600 text-white">
                                <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'admin'): ?>
                                    <th class="text-left font-semibold">
                                        <i class="fas fa-check-circle"></i><br>
                                        <span class="text-xs">ยืนยัน</span>
                                    </th>
                                <?php endif; ?>
                                <th class="text-left font-semibold">
                                    <i class="fas fa-hashtag"></i><br>
                                    <span class="text-xs">รหัส</span>
                                </th>
                                <th class="text-left font-semibold">
                                    <i class="fas fa-map-marker-alt"></i><br>
                                    <span class="text-xs">ไซต์</span>
                                </th>
                                <th class="text-left font-semibold">
                                    <i class="fas fa-building"></i><br>
                                    <span class="text-xs">แผนก</span>
                                </th>
                                <th class="text-left font-semibold">
                                    <i class="fas fa-file-alt"></i><br>
                                    <span class="text-xs">หัวข้อ</span>
                                </th>
                                <th class="text-left font-semibold">
                                    <i class="fas fa-user"></i><br>
                                    <span class="text-xs">ผู้แจ้ง</span>
                                </th>
                                <th class="text-left font-semibold">
                                    <i class="fas fa-exclamation-triangle"></i><br>
                                    <span class="text-xs">ความสำคัญ</span>
                                </th>
                                <th class="text-left font-semibold">
                                    <i class="fas fa-user-cog"></i><br>
                                    <span class="text-xs">ผู้รับผิดชอบ</span>
                                </th>
                                <th class="text-left font-semibold">
                                    <i class="fas fa-info-circle"></i><br>
                                    <span class="text-xs">สถานะ</span>
                                </th>
                                <th class="text-left font-semibold">
                                    <i class="fas fa-calendar"></i><br>
                                    <span class="text-xs">วันกำหนด</span>
                                </th>
                                <th class="text-center font-semibold">
                                    <i class="fas fa-cogs"></i><br>
                                    <span class="text-xs">จัดการ</span>
                                </th>
                                <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'admin'): ?>
                                    <th class="text-center font-semibold">
                                        <i class="fas fa-check"></i><br>
                                        <span class="text-xs">เสร็จสิ้น</span>
                                    </th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if (empty($repairRequests)): ?>
                                <tr>
                                    <td colspan="<?php echo (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'admin') ? '12' : '10'; ?>" 
                                        class="py-8 text-center text-gray-500">
                                        <div class="flex flex-col items-center">
                                            <i class="fas fa-inbox text-4xl text-gray-300 mb-2"></i>
                                            <p class="text-lg font-medium">ไม่พบข้อมูลรายการแจ้งซ่อม</p>
                                            <p class="text-xs mt-1">ลองค้นหาด้วยคำค้นอื่น หรือเพิ่มรายการใหม่</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($repairRequests as $index => $itRequest): ?>
                                    <tr class="table-row transition-all duration-200 <?php echo $index % 2 == 0 ? 'bg-white' : 'bg-gray-50'; ?>">
                                        <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'admin'): ?>
                                            <td class="text-center">
                                                <?php if (is_null($itRequest['accept_date'])): ?>
                                                    <button class="action-btn bg-yellow-500 hover:bg-yellow-600 text-white font-semibold rounded-full shadow-sm" 
                                                            onclick="acceptRequest('<?php echo $itRequest['issue_id']; ?>')">
                                                        <i class="fas fa-hand-paper"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-green-600 text-xs font-semibold">
                                                        <i class="fas fa-check-circle"></i>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        <?php endif; ?>
                                        <td class="font-mono font-bold text-blue-600 text-xs">
                                            #<?php echo htmlspecialchars($itRequest['issue_id'] ?? ''); ?>
                                        </td>
                                        <td>
                                            <span class="compact-badge bg-blue-100 text-blue-800">
                                                <?php echo htmlspecialchars(substr($itRequest['site'] ?? '', 0, 6)); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="compact-badge bg-purple-100 text-purple-800">
                                                <?php echo htmlspecialchars(substr($itRequest['department'] ?? '', 0, 8)); ?>
                                            </span>
                                        </td>
                                        <td class="compact-title">
                                            <div class="truncate font-medium text-xs" title="<?php echo htmlspecialchars($itRequest['title'] ?? ''); ?>">
                                                <?php echo htmlspecialchars($itRequest['title'] ?? ''); ?>
                                            </div>
                                        </td>
                                        <td class="compact-name">
                                            <div class="flex items-center">
                                                <div class="user-avatar bg-gradient-to-r from-blue-400 to-purple-500 rounded-full flex items-center justify-center text-white font-bold mr-1">
                                                    <?php echo strtoupper(substr($itRequest['requester'] ?? '', 0, 1)); ?>
                                                </div>
                                                <span class="font-medium text-xs truncate"><?php echo htmlspecialchars(substr($itRequest['requester'] ?? '', 0, 10)); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="compact-badge <?php echo getPriorityColor($itRequest['priority'] ?? ''); ?>">
                                                <?php 
                                                $priority = $itRequest['priority'] ?? '';
                                                $priorityIcon = '';
                                                $priorityText = '';
                                                switch (strtolower($priority)) {
                                                    case 'high':
                                                    case 'สูง':
                                                        $priorityIcon = 'fas fa-exclamation-triangle';
                                                        $priorityText = 'สูง';
                                                        break;
                                                    case 'medium':
                                                    case 'กลาง':
                                                        $priorityIcon = 'fas fa-minus-circle';
                                                        $priorityText = 'กลาง';
                                                        break;
                                                    case 'low':
                                                    case 'ต่ำ':
                                                        $priorityIcon = 'fas fa-arrow-down';
                                                        $priorityText = 'ต่ำ';
                                                        break;
                                                    default:
                                                        $priorityIcon = 'fas fa-question-circle';
                                                        $priorityText = substr($priority, 0, 4);
                                                }
                                                ?>
                                                <i class="<?php echo $priorityIcon; ?>"></i>
                                                <?php echo $priorityText; ?>
                                            </span>
                                        </td>
                                        <td class="compact-name">
                                            <?php if (!empty($itRequest['operator'])): ?>
                                                <div class="flex items-center">
                                                    <div class="user-avatar bg-gradient-to-r from-green-400 to-blue-500 rounded-full flex items-center justify-center text-white font-bold mr-1">
                                                        <?php echo strtoupper(substr($itRequest['operator'], 0, 1)); ?>
                                                    </div>
                                                    <span class="font-medium text-xs truncate"><?php echo htmlspecialchars(substr($itRequest['operator'], 0, 8)); ?></span>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-gray-400 italic text-xs">ยังไม่ได้มอบหมาย</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="compact-badge <?php echo getStatusColor($itRequest['status'] ?? ''); ?>">
                                                <?php 
                                                $status = $itRequest['status'] ?? '';
                                                $statusIcon = '';
                                                $statusText = '';
                                                switch (strtolower($status)) {
                                                    case 'pending':
                                                        $statusIcon = 'fas fa-clock';
                                                        $statusText = 'รอ';
                                                        break;
                                                    case 'in progress':
                                                        $statusIcon = 'fas fa-spinner';
                                                        $statusText = 'ดำเนินการ';
                                                        break;
                                                    case 'completed':
                                                        $statusIcon = 'fas fa-check-circle';
                                                        $statusText = 'เสร็จ';
                                                        break;
                                                    case 'cancelled':
                                                        $statusIcon = 'fas fa-times-circle';
                                                        $statusText = 'ยกเลิก';
                                                        break;
                                                    default:
                                                        $statusIcon = 'fas fa-question-circle';
                                                        $statusText = substr($status, 0, 6);
                                                }
                                                ?>
                                                <i class="<?php echo $statusIcon; ?>"></i>
                                                <?php echo $statusText; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($itRequest['plan_date'])): ?>
                                                <div class="text-xs">
                                                    <?php echo htmlspecialchars(date('d/m', strtotime($itRequest['plan_date']))); ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-gray-400 text-xs">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="flex space-x-1">
                                                <button class="action-btn bg-green-500 hover:bg-green-600 text-white rounded shadow-sm" 
                                                        onclick="showDetails('<?php echo $itRequest['issue_id']; ?>', '<?php echo $_SESSION['user_type']; ?>')"
                                                        title="ดูรายละเอียด">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if ($itRequest['status'] != 'In Progress' || (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'admin')): ?>
                                                    <form action="edit_request.php" method="get" style="display:inline;">
                                                        <input type="hidden" name="id" value="<?php echo $itRequest['issue_id']; ?>">
                                                        <button type="submit" 
                                                                class="action-btn bg-blue-500 hover:bg-blue-600 text-white rounded shadow-sm"
                                                                title="แก้ไข">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                    </form>
                                                    <button class="action-btn bg-red-500 hover:bg-red-600 text-white rounded shadow-sm" 
                                                            onclick="confirmDelete('<?php echo $itRequest['issue_id']; ?>')"
                                                            title="ลบ">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'admin'): ?>
                                            <td class="text-center">
                                                <?php if ($itRequest['status'] == 'In Progress'): ?>
                                                    <button class="action-btn bg-green-500 hover:bg-green-600 text-white rounded-full shadow-sm" 
                                                            onclick="completeTask('<?php echo $itRequest['issue_id']; ?>')"
                                                            title="ทำเครื่องหมายเสร็จสิ้น">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-gray-400 text-xs">-</span>
                                                <?php endif; ?>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Pagination Section -->
        <?php if ($total_pages > 1): ?>
            <div class="mt-6 flex justify-center">
                <nav class="flex items-center space-x-1">
                    <?php if ($current_page > 1): ?>
                        <a href="?page=<?php echo $current_page - 1; ?>&search=<?php echo htmlspecialchars($search); ?>&operator_filter=<?php echo htmlspecialchars($operator_filter); ?>&date_from=<?php echo htmlspecialchars($date_from); ?>&date_to=<?php echo htmlspecialchars($date_to); ?>" 
                           class="bg-white hover:bg-gray-50 text-gray-700 font-medium py-1 px-3 rounded-l-lg border border-gray-300 transition-all duration-300 text-sm">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo htmlspecialchars($search); ?>&operator_filter=<?php echo htmlspecialchars($operator_filter); ?>&date_from=<?php echo htmlspecialchars($date_from); ?>&date_to=<?php echo htmlspecialchars($date_to); ?>" 
                           class="<?php echo $i == $current_page ? 'bg-gradient-to-r from-blue-500 to-purple-600 text-white' : 'bg-white hover:bg-gray-50 text-gray-700'; ?> font-medium py-1 px-3 border border-gray-300 transition-all duration-300 text-sm">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($current_page < $total_pages): ?>
                        <a href="?page=<?php echo $current_page + 1; ?>&search=<?php echo htmlspecialchars($search); ?>&operator_filter=<?php echo htmlspecialchars($operator_filter); ?>&date_from=<?php echo htmlspecialchars($date_from); ?>&date_to=<?php echo htmlspecialchars($date_to); ?>" 
                           class="bg-white hover:bg-gray-50 text-gray-700 font-medium py-1 px-3 rounded-r-lg border border-gray-300 transition-all duration-300 text-sm">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </nav>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>
