<?php
session_start();
include 'connect.php';
include 'auth.php';
include 'function.php';

// Check if user is IT or admin
if (!isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['admin', 'it'])) {
    header("Location: login.php");
    exit();
}

// Function to get department name
function get_department_name($conn, $user_id)
{
    try {
        $sql = "SELECT d.dept_name FROM users u JOIN departments d ON u.department = d.id WHERE u.id = :user_id";
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

// Function to get user site
function get_user_site($conn, $user_id)
{
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

// Fetch user data
$user_id = $_SESSION['user_id'];
$dept_name = get_department_name($conn, $user_id);
$user_site = get_user_site($conn, $user_id);
$user_type = $_SESSION['user_type'];

// Handle time scope filtering (สำหรับส่วนอื่นๆ ยกเว้น monthly rate)
$scope = $_GET['scope'] ?? 'all';
$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;

$time_condition = "";
if ($scope === 'today') {
    $time_condition = " AND CAST(r.created_at AS DATE) = CAST(GETDATE() AS DATE)";
} elseif ($scope === '7days') {
    $time_condition = " AND r.created_at >= DATEADD(day, -7, GETDATE())";
} elseif ($scope === '30days') {
    $time_condition = " AND r.created_at >= DATEADD(day, -30, GETDATE())";
} elseif ($scope === 'custom' && $start_date && $end_date) {
    $start_date = $start_date . ' 00:00:00.000'; // เพิ่มเวลาเริ่มต้น
    $end_date = $end_date . ' 23:59:59.999';     // เพิ่มเวลาสิ้นสุด
    $time_condition = " AND r.created_at BETWEEN :start_date AND :end_date";
}

// Fetch dashboard summary data
try {
    $total_sql = ($user_type == 'admin') ?
        "SELECT COUNT(*) as total FROM repair_requests r WHERE 1=1" . $time_condition :
        "SELECT COUNT(*) as total FROM repair_requests r WHERE department = :dept_name AND site = :user_site" . $time_condition;
    $stmt_total = $conn->prepare($total_sql);
    if ($user_type != 'admin') {
        $stmt_total->bindParam(':dept_name', $dept_name, PDO::PARAM_STR);
        $stmt_total->bindParam(':user_site', $user_site, PDO::PARAM_STR);
    }
    if ($scope === 'custom' && $start_date && $end_date) {
        $stmt_total->bindParam(':start_date', $start_date, PDO::PARAM_STR);
        $stmt_total->bindParam(':end_date', $end_date, PDO::PARAM_STR);
    }
    $stmt_total->execute();
    $total_requests = $stmt_total->fetch(PDO::FETCH_ASSOC)['total'];

    $pending_sql = ($user_type == 'admin') ?
        "SELECT COUNT(*) as pending FROM repair_requests r WHERE status = 'Pending'" . $time_condition :
        "SELECT COUNT(*) as pending FROM repair_requests r WHERE status = 'Pending' AND department = :dept_name AND site = :user_site" . $time_condition;
    $stmt_pending = $conn->prepare($pending_sql);
    if ($user_type != 'admin') {
        $stmt_pending->bindParam(':dept_name', $dept_name, PDO::PARAM_STR);
        $stmt_pending->bindParam(':user_site', $user_site, PDO::PARAM_STR);
    }
    if ($scope === 'custom' && $start_date && $end_date) {
        $stmt_pending->bindParam(':start_date', $start_date, PDO::PARAM_STR);
        $stmt_pending->bindParam(':end_date', $end_date, PDO::PARAM_STR);
    }
    $stmt_pending->execute();
    $pending_requests = $stmt_pending->fetch(PDO::FETCH_ASSOC)['pending'];

    $in_progress_sql = ($user_type == 'admin') ?
        "SELECT COUNT(*) as in_progress FROM repair_requests r WHERE status = 'In Progress'" . $time_condition :
        "SELECT COUNT(*) as in_progress FROM repair_requests r WHERE status = 'In Progress' AND department = :dept_name AND site = :user_site" . $time_condition;
    $stmt_in_progress = $conn->prepare($in_progress_sql);
    if ($user_type != 'admin') {
        $stmt_in_progress->bindParam(':dept_name', $dept_name, PDO::PARAM_STR);
        $stmt_in_progress->bindParam(':user_site', $user_site, PDO::PARAM_STR);
    }
    if ($scope === 'custom' && $start_date && $end_date) {
        $stmt_in_progress->bindParam(':start_date', $start_date, PDO::PARAM_STR);
        $stmt_in_progress->bindParam(':end_date', $end_date, PDO::PARAM_STR);
    }
    $stmt_in_progress->execute();
    $in_progress_requests = $stmt_in_progress->fetch(PDO::FETCH_ASSOC)['in_progress'];

    $waiting_relocate_sql = ($user_type == 'admin') ?
        "SELECT COUNT(*) as waiting_relocate FROM repair_requests r WHERE status IN ('Waiting for buy', 'Relocate')" . $time_condition :
        "SELECT COUNT(*) as waiting_relocate FROM repair_requests r WHERE status IN ('Waiting for buy', 'Relocate') AND department = :dept_name AND site = :user_site" . $time_condition;
    $stmt_waiting_relocate = $conn->prepare($waiting_relocate_sql);
    if ($user_type != 'admin') {
        $stmt_waiting_relocate->bindParam(':dept_name', $dept_name, PDO::PARAM_STR);
        $stmt_waiting_relocate->bindParam(':user_site', $user_site, PDO::PARAM_STR);
    }
    if ($scope === 'custom' && $start_date && $end_date) {
        $stmt_waiting_relocate->bindParam(':start_date', $start_date, PDO::PARAM_STR);
        $stmt_waiting_relocate->bindParam(':end_date', $end_date, PDO::PARAM_STR);
    }
    $stmt_waiting_relocate->execute();
    $waiting_relocate_requests = $stmt_waiting_relocate->fetch(PDO::FETCH_ASSOC)['waiting_relocate'];

    $completed_sql = ($user_type == 'admin') ?
        "SELECT COUNT(*) as completed FROM repair_requests r WHERE status = 'Completed'" . $time_condition :
        "SELECT COUNT(*) as completed FROM repair_requests r WHERE status = 'Completed' AND department = :dept_name AND site = :user_site" . $time_condition;
    $stmt_completed = $conn->prepare($completed_sql);
    if ($user_type != 'admin') {
        $stmt_completed->bindParam(':dept_name', $dept_name, PDO::PARAM_STR);
        $stmt_completed->bindParam(':user_site', $user_site, PDO::PARAM_STR);
    }
    if ($scope === 'custom' && $start_date && $end_date) {
        $stmt_completed->bindParam(':start_date', $start_date, PDO::PARAM_STR);
        $stmt_completed->bindParam(':end_date', $end_date, PDO::PARAM_STR);
    }
    $stmt_completed->execute();
    $completed_requests = $stmt_completed->fetch(PDO::FETCH_ASSOC)['completed'];

    $dept_sql = ($user_type == 'admin') ?
        "SELECT d.dept_name, COUNT(r.id) as count FROM repair_requests r 
         LEFT JOIN departments d ON r.department = d.dept_name WHERE 1=1" . $time_condition . " 
         GROUP BY d.dept_name" :
        "SELECT d.dept_name, COUNT(r.id) as count FROM repair_requests r 
         LEFT JOIN departments d ON r.department = d.dept_name 
         WHERE r.department = :dept_name AND r.site = :user_site" . $time_condition . " 
         GROUP BY d.dept_name";
    $stmt_dept = $conn->prepare($dept_sql);
    if ($user_type != 'admin') {
        $stmt_dept->bindParam(':dept_name', $dept_name, PDO::PARAM_STR);
        $stmt_dept->bindParam(':user_site', $user_site, PDO::PARAM_STR);
    }
    if ($scope === 'custom' && $start_date && $end_date) {
        $stmt_dept->bindParam(':start_date', $start_date, PDO::PARAM_STR);
        $stmt_dept->bindParam(':end_date', $end_date, PDO::PARAM_STR);
    }
    $stmt_dept->execute();
    $dept_data = $stmt_dept->fetchAll(PDO::FETCH_ASSOC);

    $dept_labels = array_map(fn($row) => $row['dept_name'] ?? 'Unknown', $dept_data);
    $dept_counts = array_map(fn($row) => $row['count'], $dept_data);

    $rate_sql = ($user_type == 'admin') ?
        "SELECT rate, COUNT(*) as count FROM repair_requests r WHERE rate IS NOT NULL" . $time_condition . " GROUP BY rate" :
        "SELECT rate, COUNT(*) as count FROM repair_requests r WHERE rate IS NOT NULL AND department = :dept_name AND site = :user_site" . $time_condition . " GROUP BY rate";
    $stmt_rate = $conn->prepare($rate_sql);
    if ($user_type != 'admin') {
        $stmt_rate->bindParam(':dept_name', $dept_name, PDO::PARAM_STR);
        $stmt_rate->bindParam(':user_site', $user_site, PDO::PARAM_STR);
    }
    if ($scope === 'custom' && $start_date && $end_date) {
        $stmt_rate->bindParam(':start_date', $start_date, PDO::PARAM_STR);
        $stmt_rate->bindParam(':end_date', $end_date, PDO::PARAM_STR);
    }
    $stmt_rate->execute();
    $rate_data = $stmt_rate->fetchAll(PDO::FETCH_ASSOC);

    $rate_counts = array_fill(1, 5, 0);
    foreach ($rate_data as $row) {
        $rate = (int)$row['rate'];
        if ($rate >= 1 && $rate <= 5) $rate_counts[$rate] = (int)$row['count'];
    }

    // New Query: Average Rate per Month (ไม่มีการกรองเวลา)
    $monthly_rate_sql = ($user_type == 'admin') ?
        "SELECT 
            DATEPART(YEAR, r.created_at) AS year, 
            DATEPART(MONTH, r.created_at) AS month, 
            AVG(CAST(r.rate AS FLOAT)) AS avg_rate 
         FROM repair_requests r 
         WHERE r.rate IS NOT NULL 
         GROUP BY DATEPART(YEAR, r.created_at), DATEPART(MONTH, r.created_at) 
         ORDER BY year, month" :
        "SELECT 
            DATEPART(YEAR, r.created_at) AS year, 
            DATEPART(MONTH, r.created_at) AS month, 
            AVG(CAST(r.rate AS FLOAT)) AS avg_rate 
         FROM repair_requests r 
         WHERE r.rate IS NOT NULL AND r.department = :dept_name AND r.site = :user_site 
         GROUP BY DATEPART(YEAR, r.created_at), DATEPART(MONTH, r.created_at) 
         ORDER BY year, month";

    $stmt_monthly_rate = $conn->prepare($monthly_rate_sql);
    if ($user_type != 'admin') {
        $stmt_monthly_rate->bindParam(':dept_name', $dept_name, PDO::PARAM_STR);
        $stmt_monthly_rate->bindParam(':user_site', $user_site, PDO::PARAM_STR);
    }
    $stmt_monthly_rate->execute();
    $monthly_rate_data = $stmt_monthly_rate->fetchAll(PDO::FETCH_ASSOC);

    // สร้าง array สำหรับ 12 เดือนของปีล่าสุด
    $monthly_labels = [];
    $monthly_avg_rates = array_fill(0, 12, null); // เติมค่า null 12 ช่อง
    $thai_months = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];

    // หาปีล่าสุดจากข้อมูล
    $latest_year = !empty($monthly_rate_data) ? max(array_column($monthly_rate_data, 'year')) : date('Y');

    foreach ($monthly_rate_data as $row) {
        if ($row['year'] == $latest_year) { // เฉพาะปีล่าสุด
            $month_index = $row['month'] - 1; // เดือน 1-12 -> index 0-11
            $monthly_avg_rates[$month_index] = round($row['avg_rate'], 2);
        }
    }

    // สร้าง labels สำหรับปีล่าสุด
    for ($i = 0; $i < 12; $i++) {
        $monthly_labels[$i] = $thai_months[$i] . ' ' . $latest_year;
    }
} catch (PDOException $e) {
    error_log("Error fetching dashboard data: " . $e->getMessage());
    $total_requests = $pending_requests = $in_progress_requests = $completed_requests = 0;
    $dept_labels = $dept_counts = [];
    $rate_counts = array_fill(1, 5, 0);
    $monthly_labels = $monthly_avg_rates = [];
}

// Pagination setup
$items_per_page = 5;
$current_page = (int)($_GET['page'] ?? 1);
$offset = ($current_page - 1) * $items_per_page;
$search = $_GET['search'] ?? '';

// Fetch repair requests for table
$sql = ($user_type == 'admin') ?
    "SELECT issue_id, title, requester, site, department, priority, operator, status, plan_date, accept_date FROM repair_requests r WHERE status IN ('Pending', 'In Progress')" . $time_condition :
    "SELECT issue_id, title, requester, site, department, priority, operator, status, plan_date, accept_date FROM repair_requests r WHERE status IN ('Pending', 'In Progress') AND department = :dept_name AND site = :user_site" . $time_condition;
if (!empty($search)) {
    $search_term = "%$search%";
    $sql .= " AND (r.requester LIKE :search OR r.issue_id LIKE :search)";
}
$sql .= " ORDER BY r.issue_id DESC OFFSET :offset ROWS FETCH NEXT :items_per_page ROWS ONLY";
$stmt = $conn->prepare($sql);
if ($user_type != 'admin') {
    $stmt->bindParam(':dept_name', $dept_name, PDO::PARAM_STR);
    $stmt->bindParam(':user_site', $user_site, PDO::PARAM_STR);
}
if ($scope === 'custom' && $start_date && $end_date) {
    $stmt->bindParam(':start_date', $start_date, PDO::PARAM_STR);
    $stmt->bindParam(':end_date', $end_date, PDO::PARAM_STR);
}
if (!empty($search)) {
    $stmt->bindParam(':search', $search_term, PDO::PARAM_STR);
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

// Calculate total items for pagination
$total_sql = ($user_type == 'admin') ?
    "SELECT COUNT(*) as total FROM repair_requests r WHERE status IN ('Pending', 'In Progress')" . $time_condition :
    "SELECT COUNT(*) as total FROM repair_requests r WHERE status IN ('Pending', 'In Progress') AND department = :dept_name AND site = :user_site" . $time_condition;
if (!empty($search)) {
    $total_sql .= " AND (r.requester LIKE :search OR r.issue_id LIKE :search)";
}
$stmt_total = $conn->prepare($total_sql);
if ($user_type != 'admin') {
    $stmt_total->bindParam(':dept_name', $dept_name, PDO::PARAM_STR);
    $stmt_total->bindParam(':user_site', $user_site, PDO::PARAM_STR);
}
if ($scope === 'custom' && $start_date && $end_date) {
    $stmt_total->bindParam(':start_date', $start_date, PDO::PARAM_STR);
    $stmt_total->bindParam(':end_date', $end_date, PDO::PARAM_STR);
}
if (!empty($search)) {
    $stmt_total->bindParam(':search', $search_term, PDO::PARAM_STR);
}
try {
    $stmt_total->execute();
    $total_items = $stmt_total->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_items / $items_per_page);
} catch (PDOException $e) {
    error_log("Error executing total count query: " . $e->getMessage());
    $total_items = $total_pages = 0;
}

// Handle time scope filtering
$scope = $_GET['scope'] ?? 'all';
$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;

$time_condition = "";
if ($scope === 'today') {
    $time_condition = " AND CAST(start_date AS DATE) = CAST(GETDATE() AS DATE)";
} elseif ($scope === '7days') {
    $time_condition = " AND start_date >= DATEADD(day, -7, GETDATE())";
} elseif ($scope === '30days') {
    $time_condition = " AND start_date >= DATEADD(day, -30, GETDATE())";
} elseif ($scope === 'custom' && $start_date && $end_date) {
    $start_date = $start_date . ' 00:00:00.000'; // เพิ่มเวลาเริ่มต้น
    $end_date = $end_date . ' 23:59:59.999';     // เพิ่มเวลาสิ้นสุด
    $time_condition = " AND start_date BETWEEN :start_date AND :end_date";
}

// การแบ่งหน้าสำหรับตาราง
$items_per_page = 5;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;
$search = isset($_GET['search']) ? $_GET['search'] : '';

// คิวรีสำหรับตารางคำขอที่รออนุมัติ
$sql = "SELECT id, requested_by, company_name, main_location, sub_location, start_date, end_date, 
        approve_ehs_status, approve_location_status, approve_gm_status, floorman_name 
        FROM ehs_requests 
        WHERE approve_ehs_status = 'Waiting for approval' 
        OR approve_location_status = 'Waiting for approval' 
        OR approve_gm_status = 'Waiting for approval'" . $time_condition;
if (!empty($search)) {
    $search_term = "%$search%";
    $sql .= " AND (requested_by LIKE :search OR company_name LIKE :search OR floorman_name LIKE :search)";
}
$sql .= " ORDER BY id DESC OFFSET :offset ROWS FETCH NEXT :items_per_page ROWS ONLY";
$stmt = $conn->prepare($sql);
if (!empty($search)) {
    $stmt->bindParam(':search', $search_term, PDO::PARAM_STR);
}
if ($scope === 'custom' && $start_date && $end_date) {
    $stmt->bindParam(':start_date', $start_date, PDO::PARAM_STR);
    $stmt->bindParam(':end_date', $end_date, PDO::PARAM_STR);
}
$stmt->bindParam(':items_per_page', $items_per_page, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);

try {
    $stmt->execute();
    $pendingRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error executing query: " . $e->getMessage());
    $pendingRequests = [];
}

// นับจำนวนรายการทั้งหมดสำหรับ pagination
$total_sql = "SELECT COUNT(*) as total FROM ehs_requests 
              WHERE approve_ehs_status = 'Waiting for approval' 
              OR approve_location_status = 'Waiting for approval' 
              OR approve_gm_status = 'Waiting for approval'" . $time_condition;
if (!empty($search)) {
    $total_sql .= " AND (requested_by LIKE :search OR company_name LIKE :search OR floorman_name LIKE :search)";
}
$stmt_total = $conn->prepare($total_sql);
if (!empty($search)) {
    $stmt_total->bindParam(':search', $search_term, PDO::PARAM_STR);
}
if ($scope === 'custom' && $start_date && $end_date) {
    $stmt_total->bindParam(':start_date', $start_date, PDO::PARAM_STR);
    $stmt_total->bindParam(':end_date', $end_date, PDO::PARAM_STR);
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
    <title>IT Dashboard - Repair Requests</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <style>
        .table-container {
            overflow-x: auto;
        }

        th,
        td {
            text-align: center;
            vertical-align: middle;
        }

        .card {
            transition: transform 0.2s;
        }

        .card:hover {
            transform: scale(1.05);
        }
    </style>
</head>

<body class="bg-gray-50 font-sans antialiased">
    <?php include 'nav.php'; ?>

    <div class="container mx-auto px-6 py-10">
        <h1 class="text-4xl font-extrabold text-gray-800 mb-8 text-center">
            IT Dashboard - Repair Requests
            <span class="text-lg font-medium block mt-2 text-gray-600">
                (<?php echo htmlspecialchars($dept_name ?? ''); ?> - Site: <?php echo htmlspecialchars($user_site ?? ''); ?>)
            </span>
        </h1>

        <!-- Scope Filter -->
        <div class="mb-8 flex justify-center">
            <form method="GET" class="flex items-center space-x-4 bg-white p-4 rounded-lg shadow-md">
                <label for="scope" class="text-gray-700 font-semibold">ขอบเขตข้อมูล:</label>
                <select name="scope" id="scope" onchange="toggleDateFields(this.value)" class="border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500">
                    <option value="all" <?php echo $scope === 'all' ? 'selected' : ''; ?>>ทั้งหมด</option>
                    <option value="today" <?php echo $scope === 'today' ? 'selected' : ''; ?>>วันนี้</option>
                    <option value="7days" <?php echo $scope === '7days' ? 'selected' : ''; ?>>7 วันล่าสุด</option>
                    <option value="30days" <?php echo $scope === '30days' ? 'selected' : ''; ?>>30 วันล่าสุด</option>
                    <option value="custom" <?php echo $scope === 'custom' ? 'selected' : ''; ?>>กำหนดเอง</option>
                </select>
                <div id="customDateFields" class="flex space-x-2 <?php echo $scope !== 'custom' ? 'hidden' : ''; ?>">
                    <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date ?? ''); ?>" class="border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500">
                    <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date ?? ''); ?>" class="border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500">
                </div>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg transition duration-200">กรอง</button>
                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
            </form>
        </div>
            
        <!-- Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-6 mb-10">
            <div class="card bg-blue-50 p-6 rounded-xl shadow-lg text-center">
                <h2 class="text-xl font-semibold text-blue-700">คำขอทั้งหมด</h2>
                <p class="text-3xl font-bold text-blue-800 mt-2"><?php echo $total_requests; ?></p>
            </div>
            <div class="card bg-yellow-50 p-6 rounded-xl shadow-lg text-center">
                <h2 class="text-xl font-semibold text-yellow-700">รอดำเนินการ</h2>
                <p class="text-3xl font-bold text-yellow-800 mt-2"><?php echo $pending_requests; ?></p>
            </div>
            <div class="card bg-orange-50 p-6 rounded-xl shadow-lg text-center">
                <h2 class="text-xl font-semibold text-orange-700">กำลังดำเนินการ</h2>
                <p class="text-3xl font-bold text-orange-800 mt-2"><?php echo $in_progress_requests; ?></p>
            </div>
            <div class="card bg-purple-50 p-6 rounded-xl shadow-lg text-center">
                <h2 class="text-xl font-semibold text-purple-700">รอซื้อ/ย้าย</h2>
                <p class="text-3xl font-bold text-purple-800 mt-2"><?php echo $waiting_relocate_requests; ?></p>
            </div>
            <div class="card bg-green-50 p-6 rounded-xl shadow-lg text-center">
                <h2 class="text-xl font-semibold text-green-700">เสร็จสิ้น</h2>
                <p class="text-3xl font-bold text-green-800 mt-2"><?php echo $completed_requests; ?></p>
            </div>
        </div>

        <!-- Charts -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 gap-6 mb-10">
            <div class="bg-white p-6 rounded-xl shadow-lg">
                <h2 class="text-xl font-semibold text-gray-800 mb-4 text-center">สรุปสถานะคำขอซ่อม</h2>
                <canvas id="statusChart" class="max-w-[300px] mx-auto" height="100"></canvas>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-lg">
                <h2 class="text-xl font-semibold text-gray-800 mb-4 text-center">คำขอซ่อมแยกตามแผนก</h2>
                <canvas id="deptChart" height="200"></canvas>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-lg">
                <h2 class="text-xl font-semibold text-gray-800 mb-4 text-center">การให้คะแนนความพึงพอใจ</h2>
                <canvas id="rateChart" height="200"></canvas>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-lg">
                <h2 class="text-xl font-semibold text-gray-800 mb-4 text-center">ค่าเฉลี่ยการให้คะแนนรายเดือน</h2>
                <canvas id="monthlyRateChart" height="200"></canvas>
            </div>
        </div>

        <!-- ช่องค้นหา -->
<div class="mb-6 flex justify-center">
    <form method="GET" class="flex items-center space-x-2">
        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="ค้นหา (ผู้แจ้ง, บริษัท, ผู้ควบคุม)" class="border border-gray-300 rounded-lg px-4 py-2 w-80">
        <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">ค้นหา</button>
        <?php if (!empty($search)): ?>
            <a href="dashboard.php?scope=<?php echo $scope; ?><?php echo $scope === 'custom' ? "&start_date=$start_date&end_date=$end_date" : ''; ?>" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg">ล้างการค้นหา</a>
        <?php endif; ?>
        <input type="hidden" name="scope" value="<?php echo $scope; ?>">
        <?php if ($scope === 'custom'): ?>
            <input type="hidden" name="start_date" value="<?php echo $start_date; ?>">
            <input type="hidden" name="end_date" value="<?php echo $end_date; ?>">
        <?php endif; ?>
    </form>
</div>

        <!-- Requests Table -->
        <div class="table-container bg-white rounded-xl shadow-lg overflow-hidden">
            <table class="min-w-full">
                <thead class="bg-red-600 text-white">
                    <tr>
                        <th class="py-4 px-6">Issue ID</th>
                        <th class="py-4 px-6">หัวข้อ</th>
                        <th class="py-4 px-6">ผู้แจ้ง</th>
                        <th class="py-4 px-6">Site</th>
                        <th class="py-4 px-6">แผนก</th>
                        <th class="py-4 px-6">ความสำคัญ</th>
                        <th class="py-4 px-6">IT</th>
                        <th class="py-4 px-6">สถานะ</th>
                        <th class="py-4 px-6">วันที่วางแผน</th>
                        <?php if ($user_type == 'admin'): ?>
                            <th class="py-4 px-6">ยืนยัน</th>
                            <th class="py-4 px-6">ดำเนินการ</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody class="text-gray-700">
                    <?php if (empty($repairRequests)): ?>
                        <tr>
                            <td colspan="<?php echo ($user_type == 'admin') ? 11 : 9; ?>" class="py-6 px-6 text-center text-gray-500">ไม่มีคำขอที่รอดำเนินการหรือกำลังดำเนินการ</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($repairRequests as $request): ?>
                            <tr class="hover:bg-gray-100 transition duration-150">
                                <td class="py-4 px-6"><?php echo htmlspecialchars($request['issue_id'] ?? ''); ?></td>
                                <td class="py-4 px-6"><?php echo htmlspecialchars($request['title'] ?? ''); ?></td>
                                <td class="py-4 px-6"><?php echo htmlspecialchars($request['requester'] ?? ''); ?></td>
                                <td class="py-4 px-6"><?php echo htmlspecialchars($request['site'] ?? ''); ?></td>
                                <td class="py-4 px-6"><?php echo htmlspecialchars($request['department'] ?? ''); ?></td>
                                <td class="py-4 px-6"><?php echo htmlspecialchars($request['priority'] ?? ''); ?></td>
                                <td class="py-4 px-6"><?php echo htmlspecialchars($request['operator'] ?? ''); ?></td>
                                <td class="py-4 px-6"><?php echo htmlspecialchars($request['status'] ?? ''); ?></td>
                                <td class="py-4 px-6"><?php echo htmlspecialchars($request['plan_date'] ?? ''); ?></td>
                                <?php if ($user_type == 'admin'): ?>
                                    <td class="py-4 px-6">
                                        <?php if (is_null($request['accept_date']) && $request['status'] == 'Pending'): ?>
                                            <button class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-4 rounded-lg transition duration-200" onclick="acceptRequest('<?php echo $request['issue_id']; ?>')">รับ</button>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 px-6 space-x-2">
                                        <?php if ($request['status'] == 'In Progress'): ?>
                                            <button class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-lg transition duration-200" onclick="completeTask('<?php echo $request['issue_id']; ?>')">✓</button>
                                        <?php endif; ?>
                                        <button class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-lg transition duration-200" onclick="showDetails('<?php echo $request['issue_id']; ?>')">รายละเอียด</button>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="mt-8 flex justify-center space-x-2">
            <?php if ($current_page > 1): ?>
                <a href="?page=<?php echo $current_page - 1; ?>&search=<?php echo htmlspecialchars($search); ?>&scope=<?php echo $scope; ?><?php echo $scope === 'custom' ? "&start_date=$start_date&end_date=$end_date" : ''; ?>" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-lg transition duration-200">Previous</a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?php echo $i; ?>&search=<?php echo htmlspecialchars($search); ?>&scope=<?php echo $scope; ?><?php echo $scope === 'custom' ? "&start_date=$start_date&end_date=$end_date" : ''; ?>" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-lg transition duration-200 <?php echo $i == $current_page ? 'bg-gray-400' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
            <?php if ($current_page < $total_pages): ?>
                <a href="?page=<?php echo $current_page + 1; ?>&search=<?php echo htmlspecialchars($search); ?>&scope=<?php echo $scope; ?><?php echo $scope === 'custom' ? "&start_date=$start_date&end_date=$end_date" : ''; ?>" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-lg transition duration-200">Next</a>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function toggleDateFields(scope) {
            const customDateFields = document.getElementById('customDateFields');
            customDateFields.classList.toggle('hidden', scope !== 'custom');
        }

        const ctxStatus = document.getElementById('statusChart').getContext('2d');
        new Chart(ctxStatus, {
            type: 'pie',
            data: {
                labels: ['รอดำเนินการ', 'กำลังดำเนินการ', 'เสร็จสิ้น'],
                datasets: [{
                    data: [<?php echo $pending_requests; ?>, <?php echo $in_progress_requests; ?>, <?php echo $completed_requests; ?>],
                    backgroundColor: ['#3700FF', '#FA8500', '#03FF03'],
                    borderWidth: 1
                }]
            },
            options: {
                plugins: {
                    legend: {
                        position: 'top'
                    }
                }
            }
        });

        const ctxDept = document.getElementById('deptChart').getContext('2d');
        new Chart(ctxDept, {
            type: 'bar',
            data: {
                labels: [<?php echo "'" . implode("','", $dept_labels) . "'"; ?>],
                datasets: [{
                    label: 'จำนวนคำขอ',
                    data: [<?php echo implode(',', $dept_counts); ?>],
                    backgroundColor: 'rgba(229, 54, 235, 0.6)'
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        const ctxRate = document.getElementById('rateChart').getContext('2d');
        new Chart(ctxRate, {
            type: 'bar',
            data: {
                labels: ['1', '2', '3', '4', '5'],
                datasets: [{
                    label: 'จำนวนการให้คะแนน',
                    data: [<?php echo implode(',', $rate_counts); ?>],
                    backgroundColor: '#15FF00',
                    borderColor: '#000000',
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        const ctxMonthlyRate = document.getElementById('monthlyRateChart').getContext('2d');
        new Chart(ctxMonthlyRate, {
            type: 'line',
            data: {
                labels: [<?php echo "'" . implode("','", $monthly_labels) . "'"; ?>],
                datasets: [{
                    label: 'ค่าเฉลี่ยการให้คะแนน',
                    data: [<?php echo implode(',', array_map(fn($val) => $val ?? 'null', $monthly_avg_rates)); ?>],
                    fill: false,
                    borderColor: '#FF6384',
                    tension: 0.1
                }, {
                    label: 'เป้าหมาย (3)',
                    data: Array(12).fill(3),
                    fill: false,
                    borderColor: '#36A2EB',
                    borderDash: [5, 5],
                    pointRadius: 0
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 5,
                        title: {
                            display: true,
                            text: 'ค่าเฉลี่ย (1-5)'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'เดือน'
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true
                    }
                }
            }
        });

        function acceptRequest(issue_id) {
            Swal.fire({
                title: 'ยืนยันการรับงาน?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'ใช่',
                cancelButtonText: 'ไม่'
            }).then((result) => {
                if (result.isConfirmed) window.location.href = 'accept_request.php?issue_id=' + issue_id;
            });
        }

        function completeTask(issue_id) {
            Swal.fire({
                title: 'ยืนยันการเสร็จสิ้นงาน?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'ใช่',
                cancelButtonText: 'ไม่'
            }).then((result) => {
                if (result.isConfirmed) window.location.href = 'complete_request.php?issue_id=' + issue_id;
            });
        }

        function showDetails(issue_id) {
            Swal.fire({
                title: 'รายละเอียดคำขอ',
                text: 'กำลังพัฒนา: จะแสดงข้อมูลเพิ่มเติมสำหรับคำขอหมายเลข ' + issue_id,
                icon: 'info',
                confirmButtonText: 'ตกลง'
            });
        }
    </script>
</body>

</html>
