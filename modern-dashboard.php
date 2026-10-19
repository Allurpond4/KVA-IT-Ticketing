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
    <!-- Updated to modern Tailwind CSS v3 and added Google Fonts -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'sans': ['Inter', 'system-ui', 'sans-serif'],
                    },
                    colors: {
                        primary: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                            800: '#075985',
                            900: '#0c4a6e',
                        },
                        accent: {
                            50: '#fef3c7',
                            100: '#fde68a',
                            500: '#f59e0b',
                            600: '#d97706',
                        },
                        success: {
                            50: '#f0fdf4',
                            100: '#dcfce7',
                            500: '#22c55e',
                            600: '#16a34a',
                        },
                        warning: {
                            50: '#fffbeb',
                            100: '#fef3c7',
                            500: '#f59e0b',
                            600: '#d97706',
                        },
                        danger: {
                            50: '#fef2f2',
                            100: '#fee2e2',
                            500: '#ef4444',
                            600: '#dc2626',
                        }
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-in-out',
                        'slide-up': 'slideUp 0.3s ease-out',
                        'bounce-subtle': 'bounceSubtle 0.6s ease-in-out',
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0', transform: 'translateY(10px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' }
                        },
                        slideUp: {
                            '0%': { transform: 'translateY(20px)', opacity: '0' },
                            '100%': { transform: 'translateY(0)', opacity: '1' }
                        },
                        bounceSubtle: {
                            '0%, 100%': { transform: 'translateY(0)' },
                            '50%': { transform: 'translateY(-5px)' }
                        }
                    }
                }
            }
        }
    </script>
    <style>
        /* Added modern glassmorphism and gradient effects */
        .glass-card {
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.18);
        }
        
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .chart-container {
            background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .metric-card {
            background: linear-gradient(145deg, #ffffff 0%, #f1f5f9 100%);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .metric-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }
        
        .table-row:hover {
            background: linear-gradient(90deg, rgba(59, 130, 246, 0.05) 0%, rgba(147, 51, 234, 0.05) 100%);
        }
        
        .filter-card {
            background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }
    </style>
</head>

<body class="bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 font-sans antialiased min-h-screen">
    <?php include 'nav.php'; ?>

    <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Modern header with gradient and improved typography -->
        <div class="text-center mb-12 animate-fade-in">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-gradient-to-r from-primary-500 to-purple-600 rounded-2xl mb-6 shadow-lg">
                <i class="fas fa-chart-line text-3xl text-white"></i>
            </div>
            <h1 class="text-5xl font-bold bg-gradient-to-r from-gray-900 via-primary-800 to-purple-800 bg-clip-text text-transparent mb-4">
                IT Dashboard Analytics
            </h1>
            <p class="text-xl text-gray-600 font-medium">
                <?php echo htmlspecialchars($dept_name ?? 'ระบบจัดการคำขอซ่อม'); ?> • Site: <?php echo htmlspecialchars($user_site ?? 'N/A'); ?>
            </p>
        </div>

        <!-- Modern filter section with glassmorphism -->
        <div class="mb-10 flex justify-center animate-slide-up">
            <form method="GET" class="filter-card p-6 rounded-2xl border border-white/20">
                <div class="flex flex-wrap items-center gap-4">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-filter text-primary-600"></i>
                        <label for="scope" class="text-gray-700 font-semibold">ขอบเขตข้อมูล:</label>
                    </div>
                    <select name="scope" id="scope" onchange="toggleDateFields(this.value)" 
                            class="border-0 bg-white/80 backdrop-blur-sm rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-500 shadow-sm min-w-[150px]">
                        <option value="all" <?php echo $scope === 'all' ? 'selected' : ''; ?>>ทั้งหมด</option>
                        <option value="today" <?php echo $scope === 'today' ? 'selected' : ''; ?>>วันนี้</option>
                        <option value="7days" <?php echo $scope === '7days' ? 'selected' : ''; ?>>7 วันล่าสุด</option>
                        <option value="30days" <?php echo $scope === '30days' ? 'selected' : ''; ?>>30 วันล่าสุด</option>
                        <option value="custom" <?php echo $scope === 'custom' ? 'selected' : ''; ?>>กำหนดเอง</option>
                    </select>
                    <div id="customDateFields" class="flex gap-3 <?php echo $scope !== 'custom' ? 'hidden' : ''; ?>">
                        <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date ?? ''); ?>" 
                               class="border-0 bg-white/80 backdrop-blur-sm rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-500 shadow-sm">
                        <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date ?? ''); ?>" 
                               class="border-0 bg-white/80 backdrop-blur-sm rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-500 shadow-sm">
                    </div>
                    <button type="submit" class="bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 text-white font-semibold py-3 px-8 rounded-xl transition-all duration-300 shadow-lg hover:shadow-xl transform hover:-translate-y-1">
                        <i class="fas fa-search mr-2"></i>กรอง
                    </button>
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </form>
        </div>
            
        <!-- Modern metric cards with improved design and animations -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-6 mb-12">
            <div class="metric-card p-6 rounded-2xl border border-white/20 text-center animate-bounce-subtle">
                <div class="inline-flex items-center justify-center w-12 h-12 bg-gradient-to-r from-primary-500 to-primary-600 rounded-xl mb-4">
                    <i class="fas fa-clipboard-list text-white text-xl"></i>
                </div>
                <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wide mb-2">คำขอทั้งหมด</h3>
                <p class="text-3xl font-bold text-primary-700"><?php echo number_format($total_requests); ?></p>
            </div>
            <div class="metric-card p-6 rounded-2xl border border-white/20 text-center animate-bounce-subtle" style="animation-delay: 0.1s">
                <div class="inline-flex items-center justify-center w-12 h-12 bg-gradient-to-r from-warning-500 to-warning-600 rounded-xl mb-4">
                    <i class="fas fa-clock text-white text-xl"></i>
                </div>
                <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wide mb-2">รอดำเนินการ</h3>
                <p class="text-3xl font-bold text-warning-700"><?php echo number_format($pending_requests); ?></p>
            </div>
            <div class="metric-card p-6 rounded-2xl border border-white/20 text-center animate-bounce-subtle" style="animation-delay: 0.2s">
                <div class="inline-flex items-center justify-center w-12 h-12 bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl mb-4">
                    <i class="fas fa-cogs text-white text-xl"></i>
                </div>
                <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wide mb-2">กำลังดำเนินการ</h3>
                <p class="text-3xl font-bold text-blue-700"><?php echo number_format($in_progress_requests); ?></p>
            </div>
            <div class="metric-card p-6 rounded-2xl border border-white/20 text-center animate-bounce-subtle" style="animation-delay: 0.3s">
                <div class="inline-flex items-center justify-center w-12 h-12 bg-gradient-to-r from-purple-500 to-purple-600 rounded-xl mb-4">
                    <i class="fas fa-shopping-cart text-white text-xl"></i>
                </div>
                <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wide mb-2">รอซื้อ/ย้าย</h3>
                <p class="text-3xl font-bold text-purple-700"><?php echo number_format($waiting_relocate_requests); ?></p>
            </div>
            <div class="metric-card p-6 rounded-2xl border border-white/20 text-center animate-bounce-subtle" style="animation-delay: 0.4s">
                <div class="inline-flex items-center justify-center w-12 h-12 bg-gradient-to-r from-success-500 to-success-600 rounded-xl mb-4">
                    <i class="fas fa-check-circle text-white text-xl"></i>
                </div>
                <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wide mb-2">เสร็จสิ้น</h3>
                <p class="text-3xl font-bold text-success-700"><?php echo number_format($completed_requests); ?></p>
            </div>
        </div>

        <!-- Modern chart section with improved layout and styling -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-12">
            <div class="chart-container p-8 rounded-2xl border border-white/20">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-3 h-3 bg-gradient-to-r from-primary-500 to-purple-600 rounded-full"></div>
                    <h2 class="text-xl font-bold text-gray-800">สรุปสถานะคำขอซ่อม</h2>
                </div>
                <div class="flex justify-center">
                    <canvas id="statusChart" class="max-w-[280px] max-h-[280px]"></canvas>
                </div>
            </div>
            <div class="chart-container p-8 rounded-2xl border border-white/20">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-3 h-3 bg-gradient-to-r from-accent-500 to-warning-600 rounded-full"></div>
                    <h2 class="text-xl font-bold text-gray-800">คำขอซ่อมแยกตามแผนก</h2>
                </div>
                <canvas id="deptChart" height="200"></canvas>
            </div>
            <div class="chart-container p-8 rounded-2xl border border-white/20">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-3 h-3 bg-gradient-to-r from-success-500 to-emerald-600 rounded-full"></div>
                    <h2 class="text-xl font-bold text-gray-800">การให้คะแนนความพึงพอใจ</h2>
                </div>
                <canvas id="rateChart" height="200"></canvas>
            </div>
            <div class="chart-container p-8 rounded-2xl border border-white/20">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-3 h-3 bg-gradient-to-r from-pink-500 to-rose-600 rounded-full"></div>
                    <h2 class="text-xl font-bold text-gray-800">ค่าเฉลี่ยการให้คะแนนรายเดือน</h2>
                </div>
                <canvas id="monthlyRateChart" height="200"></canvas>
            </div>
        </div>

        <!-- Modern search section -->
        <div class="mb-8 flex justify-center">
            <form method="GET" class="filter-card p-6 rounded-2xl border border-white/20">
                <div class="flex flex-wrap items-center gap-4">
                    <div class="relative flex-1 min-w-[300px]">
                        <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="ค้นหา (ผู้แจ้ง, Issue ID, หัวข้อ)" 
                               class="w-full pl-12 pr-4 py-3 border-0 bg-white/80 backdrop-blur-sm rounded-xl focus:ring-2 focus:ring-primary-500 shadow-sm">
                    </div>
                    <button type="submit" class="bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 text-white font-semibold py-3 px-6 rounded-xl transition-all duration-300 shadow-lg hover:shadow-xl">
                        <i class="fas fa-search mr-2"></i>ค้นหา
                    </button>
                    <?php if (!empty($search)): ?>
                        <a href="dashboard.php?scope=<?php echo $scope; ?><?php echo $scope === 'custom' ? "&start_date=$start_date&end_date=$end_date" : ''; ?>" 
                           class="bg-gray-500 hover:bg-gray-600 text-white font-semibold py-3 px-6 rounded-xl transition-all duration-300">
                            <i class="fas fa-times mr-2"></i>ล้าง
                        </a>
                    <?php endif; ?>
                    <input type="hidden" name="scope" value="<?php echo $scope; ?>">
                    <?php if ($scope === 'custom'): ?>
                        <input type="hidden" name="start_date" value="<?php echo $start_date; ?>">
                        <input type="hidden" name="end_date" value="<?php echo $end_date; ?>">
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Modern table with improved styling -->
        <div class="chart-container rounded-2xl border border-white/20 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-gradient-to-r from-gray-800 to-gray-900 text-white">
                        <tr>
                            <th class="py-4 px-6 text-left font-semibold">Issue ID</th>
                            <th class="py-4 px-6 text-left font-semibold">หัวข้อ</th>
                            <th class="py-4 px-6 text-left font-semibold">ผู้แจ้ง</th>
                            <th class="py-4 px-6 text-left font-semibold">Site</th>
                            <th class="py-4 px-6 text-left font-semibold">แผนก</th>
                            <th class="py-4 px-6 text-left font-semibold">ความสำคัญ</th>
                            <th class="py-4 px-6 text-left font-semibold">IT</th>
                            <th class="py-4 px-6 text-left font-semibold">สถานะ</th>
                            <th class="py-4 px-6 text-left font-semibold">วันที่วางแผน</th>
                            <?php if ($user_type == 'admin'): ?>
                                <th class="py-4 px-6 text-center font-semibold">ยืนยัน</th>
                                <th class="py-4 px-6 text-center font-semibold">ดำเนินการ</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody class="bg-white/50 backdrop-blur-sm">
                        <?php if (empty($repairRequests)): ?>
                            <tr>
                                <td colspan="<?php echo ($user_type == 'admin') ? 11 : 9; ?>" class="py-12 px-6 text-center">
                                    <div class="flex flex-col items-center gap-4">
                                        <i class="fas fa-inbox text-4xl text-gray-300"></i>
                                        <p class="text-gray-500 text-lg">ไม่มีคำขอที่รอดำเนินการหรือกำลังดำเนินการ</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($repairRequests as $request): ?>
                                <tr class="table-row border-b border-gray-100 transition-all duration-200">
                                    <td class="py-4 px-6 font-medium text-primary-700"><?php echo htmlspecialchars($request['issue_id'] ?? ''); ?></td>
                                    <td class="py-4 px-6"><?php echo htmlspecialchars($request['title'] ?? ''); ?></td>
                                    <td class="py-4 px-6"><?php echo htmlspecialchars($request['requester'] ?? ''); ?></td>
                                    <td class="py-4 px-6"><?php echo htmlspecialchars($request['site'] ?? ''); ?></td>
                                    <td class="py-4 px-6"><?php echo htmlspecialchars($request['department'] ?? ''); ?></td>
                                    <td class="py-4 px-6">
                                        <span class="inline-flex px-3 py-1 rounded-full text-xs font-semibold
                                            <?php echo ($request['priority'] ?? '') === 'High' ? 'bg-danger-100 text-danger-700' : 
                                                     (($request['priority'] ?? '') === 'Medium' ? 'bg-warning-100 text-warning-700' : 'bg-success-100 text-success-700'); ?>">
                                            <?php echo htmlspecialchars($request['priority'] ?? ''); ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-6"><?php echo htmlspecialchars($request['operator'] ?? ''); ?></td>
                                    <td class="py-4 px-6">
                                        <span class="inline-flex px-3 py-1 rounded-full text-xs font-semibold
                                            <?php echo ($request['status'] ?? '') === 'Pending' ? 'bg-warning-100 text-warning-700' : 
                                                     (($request['status'] ?? '') === 'In Progress' ? 'bg-primary-100 text-primary-700' : 'bg-success-100 text-success-700'); ?>">
                                            <?php echo htmlspecialchars($request['status'] ?? ''); ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-6"><?php echo htmlspecialchars($request['plan_date'] ?? ''); ?></td>
                                    <?php if ($user_type == 'admin'): ?>
                                        <td class="py-4 px-6 text-center">
                                            <?php if (is_null($request['accept_date']) && $request['status'] == 'Pending'): ?>
                                                <button class="bg-gradient-to-r from-warning-500 to-warning-600 hover:from-warning-600 hover:to-warning-700 text-white font-semibold py-2 px-4 rounded-lg transition-all duration-200 shadow-md hover:shadow-lg" onclick="acceptRequest('<?php echo $request['issue_id']; ?>')">
                                                    <i class="fas fa-check mr-1"></i>รับ
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-4 px-6 text-center space-x-2">
                                            <?php if ($request['status'] == 'In Progress'): ?>
                                                <button class="bg-gradient-to-r from-success-500 to-success-600 hover:from-success-600 hover:to-success-700 text-white font-semibold py-2 px-4 rounded-lg transition-all duration-200 shadow-md hover:shadow-lg" onclick="completeTask('<?php echo $request['issue_id']; ?>')">
                                                    <i class="fas fa-check-circle mr-1"></i>เสร็จ
                                                </button>
                                            <?php endif; ?>
                                            <button class="bg-gradient-to-r from-primary-500 to-primary-600 hover:from-primary-600 hover:to-primary-700 text-white font-semibold py-2 px-4 rounded-lg transition-all duration-200 shadow-md hover:shadow-lg" onclick="showDetails('<?php echo $request['issue_id']; ?>')">
                                                <i class="fas fa-info-circle mr-1"></i>รายละเอียด
                                            </button>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modern pagination -->
        <div class="mt-8 flex justify-center">
            <div class="flex items-center space-x-2">
                <?php if ($current_page > 1): ?>
                    <a href="?page=<?php echo $current_page - 1; ?>&search=<?php echo htmlspecialchars($search); ?>&scope=<?php echo $scope; ?><?php echo $scope === 'custom' ? "&start_date=$start_date&end_date=$end_date" : ''; ?>" 
                       class="bg-white/80 backdrop-blur-sm hover:bg-primary-500 hover:text-white text-gray-700 font-semibold py-3 px-4 rounded-xl transition-all duration-200 border border-gray-200 shadow-sm">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo htmlspecialchars($search); ?>&scope=<?php echo $scope; ?><?php echo $scope === 'custom' ? "&start_date=$start_date&end_date=$end_date" : ''; ?>" 
                       class="<?php echo $i == $current_page ? 'bg-primary-500 text-white' : 'bg-white/80 backdrop-blur-sm hover:bg-primary-500 hover:text-white text-gray-700'; ?> font-semibold py-3 px-4 rounded-xl transition-all duration-200 border border-gray-200 shadow-sm min-w-[48px] text-center">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                <?php if ($current_page < $total_pages): ?>
                    <a href="?page=<?php echo $current_page + 1; ?>&search=<?php echo htmlspecialchars($search); ?>&scope=<?php echo $scope; ?><?php echo $scope === 'custom' ? "&start_date=$start_date&end_date=$end_date" : ''; ?>" 
                       class="bg-white/80 backdrop-blur-sm hover:bg-primary-500 hover:text-white text-gray-700 font-semibold py-3 px-4 rounded-xl transition-all duration-200 border border-gray-200 shadow-sm">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function toggleDateFields(scope) {
            const customDateFields = document.getElementById('customDateFields');
            customDateFields.classList.toggle('hidden', scope !== 'custom');
        }

        document.addEventListener('DOMContentLoaded', function() {
        const modernChartOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: {
                        usePointStyle: true,
                        padding: 20,
                        font: {
                            family: 'Inter',
                            size: 12,
                            weight: '500'
                        }
                    }
                }
            }
        };

        try {
            const ctxStatus = document.getElementById('statusChart');
            if (ctxStatus) {
                new Chart(ctxStatus.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: ['รอดำเนินการ', 'กำลังดำเนินการ', 'เสร็จสิ้น'],
                        datasets: [{
                            data: [<?php echo $pending_requests; ?>, <?php echo $in_progress_requests; ?>, <?php echo $completed_requests; ?>],
                            backgroundColor: [
                                '#f59e0b',
                                '#3b82f6',
                                '#22c55e'
                            ],
                            borderColor: [
                                '#d97706',
                                '#2563eb',
                                '#16a34a'
                            ],
                            borderWidth: 2,
                            hoverOffset: 8
                        }]
                    },
                    options: {
                        ...modernChartOptions,
                        cutout: '60%',
                        plugins: {
                            ...modernChartOptions.plugins,
                            legend: {
                                position: 'bottom',
                                labels: {
                                    ...modernChartOptions.plugins.legend.labels,
                                    padding: 20
                                }
                            }
                        }
                    }
                });
            }

            const ctxDept = document.getElementById('deptChart');
            if (ctxDept) {
                new Chart(ctxDept.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: [<?php echo "'" . implode("','", $dept_labels) . "'"; ?>],
                        datasets: [{
                            label: 'จำนวนคำขอ',
                            data: [<?php echo implode(',', $dept_counts); ?>],
                            backgroundColor: '#9333ea',
                            borderColor: '#7c3aed',
                            borderWidth: 1,
                            borderRadius: 8,
                            borderSkipped: false,
                        }]
                    },
                    options: {
                        ...modernChartOptions,
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.05)'
                                },
                                ticks: {
                                    font: {
                                        family: 'Inter'
                                    }
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    font: {
                                        family: 'Inter'
                                    }
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });
            }

            const ctxRate = document.getElementById('rateChart');
            if (ctxRate) {
                new Chart(ctxRate.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: ['⭐', '⭐⭐', '⭐⭐⭐', '⭐⭐⭐⭐', '⭐⭐⭐⭐⭐'],
                        datasets: [{
                            label: 'จำนวนการให้คะแนน',
                            data: [<?php echo implode(',', $rate_counts); ?>],
                            backgroundColor: [
                                '#ef4444',
                                '#f59e0b',
                                '#3b82f6',
                                '#10b981',
                                '#22c55e'
                            ],
                            borderColor: [
                                '#dc2626',
                                '#d97706',
                                '#2563eb',
                                '#059669',
                                '#16a34a'
                            ],
                            borderWidth: 1,
                            borderRadius: 8,
                            borderSkipped: false,
                        }]
                    },
                    options: {
                        ...modernChartOptions,
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.05)'
                                },
                                ticks: {
                                    font: {
                                        family: 'Inter'
                                    }
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    font: {
                                        family: 'Inter'
                                    }
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });
            }

            const ctxMonthlyRate = document.getElementById('monthlyRateChart');
            if (ctxMonthlyRate) {
                new Chart(ctxMonthlyRate.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: [<?php echo "'" . implode("','", $monthly_labels) . "'"; ?>],
                        datasets: [{
                            label: 'ค่าเฉลี่ยการให้คะแนน',
                            data: [<?php echo implode(',', array_map(fn($val) => $val ?? 'null', $monthly_avg_rates)); ?>],
                            fill: true,
                            backgroundColor: 'rgba(236, 72, 153, 0.1)',
                            borderColor: '#ec4899',
                            borderWidth: 3,
                            tension: 0.4,
                            pointBackgroundColor: '#ec4899',
                            pointBorderColor: '#ffffff',
                            pointBorderWidth: 2,
                            pointRadius: 6,
                            pointHoverRadius: 8
                        }, {
                            label: 'เป้าหมาย (3.0)',
                            data: Array(12).fill(3),
                            fill: false,
                            borderColor: '#9ca3af',
                            borderWidth: 2,
                            borderDash: [8, 4],
                            pointRadius: 0,
                            pointHoverRadius: 0
                        }]
                    },
                    options: {
                        ...modernChartOptions,
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 5,
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.05)'
                                },
                                ticks: {
                                    font: {
                                        family: 'Inter'
                                    }
                                },
                                title: {
                                    display: true,
                                    text: 'ค่าเฉลี่ย (1-5)',
                                    font: {
                                        family: 'Inter',
                                        weight: '600'
                                    }
                                }
                            },
                            x: {
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.05)'
                                },
                                ticks: {
                                    font: {
                                        family: 'Inter'
                                    }
                                },
                                title: {
                                    display: true,
                                    text: 'เดือน',
                                    font: {
                                        family: 'Inter',
                                        weight: '600'
                                    }
                                }
                            }
                        }
                    }
                });
            }
        } catch (error) {
            console.error('Chart initialization error:', error);
        }
    });

        function acceptRequest(issue_id) {
            Swal.fire({
                title: 'ยืนยันการรับงาน?',
                text: `คุณต้องการรับงาน Issue ID: ${issue_id}`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#0ea5e9',
                cancelButtonColor: '#6b7280',
                confirmButtonText: '<i class="fas fa-check mr-2"></i>ยืนยัน',
                cancelButtonText: '<i class="fas fa-times mr-2"></i>ยกเลิก',
                customClass: {
                    popup: 'rounded-2xl',
                    confirmButton: 'rounded-xl',
                    cancelButton: 'rounded-xl'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'accept_request.php?issue_id=' + issue_id;
                }
            });
        }

        function completeTask(issue_id) {
            Swal.fire({
                title: 'ยืนยันการเสร็จสิ้นงาน?',
                text: `คุณต้องการทำเครื่องหมายว่าเสร็จสิ้นสำหรับ Issue ID: ${issue_id}`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#22c55e',
                cancelButtonColor: '#6b7280',
                confirmButtonText: '<i class="fas fa-check-circle mr-2"></i>เสร็จสิ้น',
                cancelButtonText: '<i class="fas fa-times mr-2"></i>ยกเลิก',
                customClass: {
                    popup: 'rounded-2xl',
                    confirmButton: 'rounded-xl',
                    cancelButton: 'rounded-xl'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'complete_request.php?issue_id=' + issue_id;
                }
            });
        }

        function showDetails(issue_id) {
            Swal.fire({
                title: 'รายละเอียดคำขอ',
                html: `
                    <div class="text-left space-y-3">
                        <p><strong>Issue ID:</strong> ${issue_id}</p>
                        <p class="text-gray-600">กำลังพัฒนาฟีเจอร์แสดงรายละเอียดเพิ่มเติม...</p>
                        <div class="bg-blue-50 p-4 rounded-lg">
                            <p class="text-sm text-blue-700">
                                <i class="fas fa-info-circle mr-2"></i>
                                ฟีเจอร์นี้จะแสดงข้อมูลครบถ้วนของคำขอซ่อมในเร็วๆ นี้
                            </p>
                        </div>
                    </div>
                `,
                icon: 'info',
                confirmButtonColor: '#0ea5e9',
                confirmButtonText: '<i class="fas fa-check mr-2"></i>ตกลง',
                customClass: {
                    popup: 'rounded-2xl',
                    confirmButton: 'rounded-xl'
                }
            });
        }
    </script>
</body>

</html>
