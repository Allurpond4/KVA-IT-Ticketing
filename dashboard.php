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

// Handle time scope filtering
$scope = $_GET['scope'] ?? 'all';
$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;

$time_condition = "";
$params = [];

if ($scope === 'today') {
    $time_condition = " AND CAST(r.created_at AS DATE) = CAST(GETDATE() AS DATE)";
} elseif ($scope === '7days') {
    $time_condition = " AND r.created_at >= DATEADD(day, -7, GETDATE())";
} elseif ($scope === '30days') {
    $time_condition = " AND r.created_at >= DATEADD(day, -30, GETDATE())";
} elseif ($scope === 'custom' && $start_date && $end_date) {
    $time_condition = " AND CAST(r.created_at AS DATE) BETWEEN :start_date AND :end_date";
    $params['start_date'] = $start_date;
    $params['end_date'] = $end_date;
}

// Fetch dashboard summary data
try {
    // Total requests
    $total_sql = ($user_type == 'admin') ?
        "SELECT COUNT(*) as total FROM repair_requests r WHERE 1=1" . $time_condition :
        "SELECT COUNT(*) as total FROM repair_requests r WHERE r.department = :dept_name AND r.site = :user_site" . $time_condition;

    $stmt_total = $conn->prepare($total_sql);
    if ($user_type != 'admin') {
        $stmt_total->bindParam(':dept_name', $dept_name, PDO::PARAM_STR);
        $stmt_total->bindParam(':user_site', $user_site, PDO::PARAM_STR);
    }
    foreach ($params as $key => $value) {
        $stmt_total->bindParam(":$key", $params[$key], PDO::PARAM_STR);
    }
    $stmt_total->execute();
    $total_requests = $stmt_total->fetch(PDO::FETCH_ASSOC)['total'];

    // Pending requests
    $pending_sql = ($user_type == 'admin') ?
        "SELECT COUNT(*) as pending FROM repair_requests r WHERE r.status = 'Pending'" . $time_condition :
        "SELECT COUNT(*) as pending FROM repair_requests r WHERE r.status = 'Pending' AND r.department = :dept_name AND r.site = :user_site" . $time_condition;

    $stmt_pending = $conn->prepare($pending_sql);
    if ($user_type != 'admin') {
        $stmt_pending->bindParam(':dept_name', $dept_name, PDO::PARAM_STR);
        $stmt_pending->bindParam(':user_site', $user_site, PDO::PARAM_STR);
    }
    foreach ($params as $key => $value) {
        $stmt_pending->bindParam(":$key", $params[$key], PDO::PARAM_STR);
    }
    $stmt_pending->execute();
    $pending_requests = $stmt_pending->fetch(PDO::FETCH_ASSOC)['pending'];

    // In Progress requests
    $in_progress_sql = ($user_type == 'admin') ?
        "SELECT COUNT(*) as in_progress FROM repair_requests r WHERE r.status = 'In Progress'" . $time_condition :
        "SELECT COUNT(*) as in_progress FROM repair_requests r WHERE r.status = 'In Progress' AND r.department = :dept_name AND r.site = :user_site" . $time_condition;

    $stmt_in_progress = $conn->prepare($in_progress_sql);
    if ($user_type != 'admin') {
        $stmt_in_progress->bindParam(':dept_name', $dept_name, PDO::PARAM_STR);
        $stmt_in_progress->bindParam(':user_site', $user_site, PDO::PARAM_STR);
    }
    foreach ($params as $key => $value) {
        $stmt_in_progress->bindParam(":$key", $params[$key], PDO::PARAM_STR);
    }
    $stmt_in_progress->execute();
    $in_progress_requests = $stmt_in_progress->fetch(PDO::FETCH_ASSOC)['in_progress'];

    // Waiting/Relocate requests
    $waiting_relocate_sql = ($user_type == 'admin') ?
        "SELECT COUNT(*) as waiting_relocate FROM repair_requests r WHERE r.status IN ('Waiting for buy', 'Relocate')" . $time_condition :
        "SELECT COUNT(*) as waiting_relocate FROM repair_requests r WHERE r.status IN ('Waiting for buy', 'Relocate') AND r.department = :dept_name AND r.site = :user_site" . $time_condition;

    $stmt_waiting_relocate = $conn->prepare($waiting_relocate_sql);
    if ($user_type != 'admin') {
        $stmt_waiting_relocate->bindParam(':dept_name', $dept_name, PDO::PARAM_STR);
        $stmt_waiting_relocate->bindParam(':user_site', $user_site, PDO::PARAM_STR);
    }
    foreach ($params as $key => $value) {
        $stmt_waiting_relocate->bindParam(":$key", $params[$key], PDO::PARAM_STR);
    }
    $stmt_waiting_relocate->execute();
    $waiting_relocate_requests = $stmt_waiting_relocate->fetch(PDO::FETCH_ASSOC)['waiting_relocate'];

    // Completed requests
    $completed_sql = ($user_type == 'admin') ?
        "SELECT COUNT(*) as completed FROM repair_requests r WHERE r.status = 'Completed'" . $time_condition :
        "SELECT COUNT(*) as completed FROM repair_requests r WHERE r.status = 'Completed' AND r.department = :dept_name AND r.site = :user_site" . $time_condition;

    $stmt_completed = $conn->prepare($completed_sql);
    if ($user_type != 'admin') {
        $stmt_completed->bindParam(':dept_name', $dept_name, PDO::PARAM_STR);
        $stmt_completed->bindParam(':user_site', $user_site, PDO::PARAM_STR);
    }
    foreach ($params as $key => $value) {
        $stmt_completed->bindParam(":$key", $params[$key], PDO::PARAM_STR);
    }
    $stmt_completed->execute();
    $completed_requests = $stmt_completed->fetch(PDO::FETCH_ASSOC)['completed'];

    // Department breakdown
    $dept_sql = ($user_type == 'admin') ?
        "SELECT r.department, COUNT(r.id) as count FROM repair_requests r WHERE 1=1" . $time_condition . " GROUP BY r.department ORDER BY count DESC" :
        "SELECT r.department, COUNT(r.id) as count FROM repair_requests r WHERE r.department = :dept_name AND r.site = :user_site" . $time_condition . " GROUP BY r.department";

    $stmt_dept = $conn->prepare($dept_sql);
    if ($user_type != 'admin') {
        $stmt_dept->bindParam(':dept_name', $dept_name, PDO::PARAM_STR);
        $stmt_dept->bindParam(':user_site', $user_site, PDO::PARAM_STR);
    }
    foreach ($params as $key => $value) {
        $stmt_dept->bindParam(":$key", $params[$key], PDO::PARAM_STR);
    }
    $stmt_dept->execute();
    $dept_data = $stmt_dept->fetchAll(PDO::FETCH_ASSOC);

    $dept_labels = [];
    $dept_counts = [];
    foreach ($dept_data as $row) {
        $dept_labels[] = $row['department'] ?? 'Unknown';
        $dept_counts[] = (int)$row['count'];
    }

    // Rating distribution (ความพึงพอใจ)
    $rate_sql = ($user_type == 'admin') ?
        "SELECT r.rate, COUNT(*) as count FROM repair_requests r WHERE r.rate IS NOT NULL" . $time_condition . " GROUP BY r.rate ORDER BY r.rate" :
        "SELECT r.rate, COUNT(*) as count FROM repair_requests r WHERE r.rate IS NOT NULL AND r.department = :dept_name AND r.site = :user_site" . $time_condition . " GROUP BY r.rate ORDER BY r.rate";

    $stmt_rate = $conn->prepare($rate_sql);
    if ($user_type != 'admin') {
        $stmt_rate->bindParam(':dept_name', $dept_name, PDO::PARAM_STR);
        $stmt_rate->bindParam(':user_site', $user_site, PDO::PARAM_STR);
    }
    foreach ($params as $key => $value) {
        $stmt_rate->bindParam(":$key", $params[$key], PDO::PARAM_STR);
    }
    $stmt_rate->execute();
    $rate_data = $stmt_rate->fetchAll(PDO::FETCH_ASSOC);

    $rate_counts = array_fill(1, 5, 0);
    foreach ($rate_data as $row) {
        $rate = (int)$row['rate'];
        if ($rate >= 1 && $rate <= 5) {
            $rate_counts[$rate] = (int)$row['count'];
        }
    }

    // Monthly average rating (ไม่มีการกรองเวลา - แสดงทุกเดือน)
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

    $monthly_labels = [];
    $monthly_avg_rates = [];
    $thai_months = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];

    foreach ($monthly_rate_data as $row) {
        $month_index = $row['month'] - 1;
        $monthly_labels[] = $thai_months[$month_index] . ' ' . $row['year'];
        $monthly_avg_rates[] = round($row['avg_rate'], 2);
    }

    $ontime_sql = ($user_type == 'admin') ?
        "SELECT 
            SUM(CASE WHEN completed_at IS NOT NULL AND plan_date IS NOT NULL AND CONVERT(DATE, completed_at) <= CONVERT(DATE, plan_date) THEN 1 ELSE 0 END) as ontime_count,
            SUM(CASE WHEN completed_at IS NOT NULL AND plan_date IS NOT NULL AND CONVERT(DATE, completed_at) > CONVERT(DATE, plan_date) THEN 1 ELSE 0 END) as late_count,
            COUNT(CASE WHEN completed_at IS NOT NULL AND plan_date IS NOT NULL THEN 1 END) as total_count
         FROM repair_requests r WHERE completed_at IS NOT NULL AND plan_date IS NOT NULL" . $time_condition :
        "SELECT 
            SUM(CASE WHEN completed_at IS NOT NULL AND plan_date IS NOT NULL AND CONVERT(DATE, completed_at) <= CONVERT(DATE, plan_date) THEN 1 ELSE 0 END) as ontime_count,
            SUM(CASE WHEN completed_at IS NOT NULL AND plan_date IS NOT NULL AND CONVERT(DATE, completed_at) > CONVERT(DATE, plan_date) THEN 1 ELSE 0 END) as late_count,
            COUNT(CASE WHEN completed_at IS NOT NULL AND plan_date IS NOT NULL THEN 1 END) as total_count
         FROM repair_requests r WHERE completed_at IS NOT NULL AND plan_date IS NOT NULL AND r.department = :dept_name AND r.site = :user_site" . $time_condition;

    $stmt_ontime = $conn->prepare($ontime_sql);
    if ($user_type != 'admin') {
        $stmt_ontime->bindParam(':dept_name', $dept_name, PDO::PARAM_STR);
        $stmt_ontime->bindParam(':user_site', $user_site, PDO::PARAM_STR);
    }
    foreach ($params as $key => $value) {
        $stmt_ontime->bindParam(":$key", $params[$key], PDO::PARAM_STR);
    }
    $stmt_ontime->execute();
    $ontime_data = $stmt_ontime->fetch(PDO::FETCH_ASSOC);

    $ontime_count = (int)($ontime_data['ontime_count'] ?? 0);
    $late_count = (int)($ontime_data['late_count'] ?? 0);
    $total_ontime_tasks = (int)($ontime_data['total_count'] ?? 0);

    $ontime_percentage = $total_ontime_tasks > 0 ? round(($ontime_count / $total_ontime_tasks) * 100, 2) : 0;
    $late_percentage = $total_ontime_tasks > 0 ? round(($late_count / $total_ontime_tasks) * 100, 2) : 0;

    // SLA compliance calculation based on priority and completion time
    $sla_sql = "SELECT 
        COUNT(*) as total,
        SUM(CASE 
            WHEN (priority = 'Low' AND DATEDIFF(MINUTE, created_at, completed_at) <= 10080) THEN 1
            WHEN (priority = 'Normal' AND DATEDIFF(MINUTE, created_at, completed_at) <= 4320) THEN 1
            WHEN (priority = 'High' AND DATEDIFF(MINUTE, created_at, completed_at) <= 1440) THEN 1
            WHEN (priority = 'Critical' AND DATEDIFF(MINUTE, created_at, completed_at) <= 240) THEN 1
            ELSE 0
        END) as sla_met
    FROM repair_requests r
    WHERE r.status = 'Completed' 
    AND r.completed_at IS NOT NULL 
    AND r.created_at IS NOT NULL";

    $where_clause = "";
    $sla_params = [];

    if ($user_type != 'admin') {
        $where_clause .= " AND r.department = :dept_name AND r.site = :user_site";
        $sla_params['dept_name'] = $dept_name;
        $sla_params['user_site'] = $user_site;
    }

    $sla_sql .= $time_condition . $where_clause;

    $stmt_sla = $conn->prepare($sla_sql);

    // Bind user-specific parameters
    if ($user_type != 'admin') {
        $stmt_sla->bindValue(':dept_name', $sla_params['dept_name'], PDO::PARAM_STR);
        $stmt_sla->bindValue(':user_site', $sla_params['user_site'], PDO::PARAM_STR);
    }

    // Bind date parameters from the main params array
    foreach ($params as $key => $value) {
        $stmt_sla->bindValue(":$key", $value, PDO::PARAM_STR);
    }

    $stmt_sla->execute();
    $sla_data = $stmt_sla->fetch(PDO::FETCH_ASSOC);

    $sla_total = (int)($sla_data['total'] ?? 0);
    $sla_met_count = (int)($sla_data['sla_met'] ?? 0);
    $sla_not_met_count = $sla_total - $sla_met_count;
    $sla_percentage = $sla_total > 0 ? round(($sla_met_count / $sla_total) * 100, 2) : 0;
    $sla_not_met_percentage = $sla_total > 0 ? round(($sla_not_met_count / $sla_total) * 100, 2) : 0;
} catch (PDOException $e) {
    error_log("Error fetching dashboard data: " . $e->getMessage());
    $total_requests = $pending_requests = $in_progress_requests = $waiting_relocate_requests = $completed_requests = 0;
    $dept_labels = $dept_counts = [];
    $rate_counts = array_fill(1, 5, 0);
    $monthly_labels = $monthly_avg_rates = [];
    $ontime_count = $late_count = $total_ontime_tasks = 0;
    $ontime_percentage = $late_percentage = 0;
    $sla_total = $sla_met_count = $sla_not_met_count = 0;
    $sla_percentage = $sla_not_met_percentage = 0;
}

// Pagination for table
$items_per_page = 10;
$current_page = (int)($_GET['page'] ?? 1);
$offset = ($current_page - 1) * $items_per_page;
$search = $_GET['search'] ?? '';

// Fetch repair requests for table
$table_sql = ($user_type == 'admin') ?
    "SELECT r.issue_id, r.title, r.requester, r.site, r.department, r.priority, r.operator, r.status, r.plan_date, r.accept_date 
     FROM repair_requests r 
     WHERE r.status IN ('Pending', 'In Progress')" . $time_condition :
    "SELECT r.issue_id, r.title, r.requester, r.site, r.department, r.priority, r.operator, r.status, r.plan_date, r.accept_date 
     FROM repair_requests r 
     WHERE r.status IN ('Pending', 'In Progress') AND r.department = :dept_name AND r.site = :user_site" . $time_condition;

if (!empty($search)) {
    $search_term = "%$search%";
    $table_sql .= " AND (r.requester LIKE :search OR r.issue_id LIKE :search OR r.title LIKE :search)";
}
$table_sql .= " ORDER BY r.issue_id DESC OFFSET :offset ROWS FETCH NEXT :items_per_page ROWS ONLY";

$stmt_table = $conn->prepare($table_sql);
if ($user_type != 'admin') {
    $stmt_table->bindParam(':dept_name', $dept_name, PDO::PARAM_STR);
    $stmt_table->bindParam(':user_site', $user_site, PDO::PARAM_STR);
}
foreach ($params as $key => $value) {
    $stmt_table->bindParam(":$key", $params[$key], PDO::PARAM_STR);
}
if (!empty($search)) {
    $stmt_table->bindParam(':search', $search_term, PDO::PARAM_STR);
}
$stmt_table->bindParam(':items_per_page', $items_per_page, PDO::PARAM_INT);
$stmt_table->bindParam(':offset', $offset, PDO::PARAM_INT);

try {
    $stmt_table->execute();
    $repairRequests = $stmt_table->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error executing query: " . $e->getMessage());
    $repairRequests = [];
}

// Total items for pagination
$count_sql = ($user_type == 'admin') ?
    "SELECT COUNT(*) as total FROM repair_requests r WHERE r.status IN ('Pending', 'In Progress')" . $time_condition :
    "SELECT COUNT(*) as total FROM repair_requests r WHERE r.status IN ('Pending', 'In Progress') AND r.department = :dept_name AND r.site = :user_site" . $time_condition;

if (!empty($search)) {
    $count_sql .= " AND (r.requester LIKE :search OR r.issue_id LIKE :search OR r.title LIKE :search)";
}

$stmt_count = $conn->prepare($count_sql);
if ($user_type != 'admin') {
    $stmt_count->bindParam(':dept_name', $dept_name, PDO::PARAM_STR);
    $stmt_count->bindParam(':user_site', $user_site, PDO::PARAM_STR);
}
foreach ($params as $key => $value) {
    $stmt_count->bindParam(":$key", $params[$key], PDO::PARAM_STR);
}
if (!empty($search)) {
    $stmt_count->bindParam(':search', $search_term, PDO::PARAM_STR);
}

try {
    $stmt_count->execute();
    $total_items = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_items / $items_per_page);
} catch (PDOException $e) {
    error_log("Error executing total count query: " . $e->getMessage());
    $total_items = $total_pages = 0;
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IT Dashboard - Repair Requests</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <style>
        .card { transition: transform 0.2s, box-shadow 0.2s; }
        .card:hover { transform: translateY(-2px); box-shadow: 0 12px 24px rgba(15, 23, 42, 0.1); }
    </style>
</head>

<body class="dashboard-page bg-gray-50">
    <?php include 'nav.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <h1 class="text-4xl font-bold text-gray-800 mb-2 text-center">IT Dashboard - Repair Requests</h1>
        <p class="text-center text-gray-600 mb-8">
            <?php echo htmlspecialchars($dept_name ?? 'All Departments'); ?> - Site: <?php echo htmlspecialchars($user_site ?? 'All Sites'); ?>
        </p>

        <!-- Date Filter -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <form method="GET" class="flex flex-wrap items-end gap-4">
                <div class="flex-1 min-w-[200px]">
                    <label for="scope" class="block text-sm font-medium text-gray-700 mb-2">ขอบเขตข้อมูล</label>
                    <select name="scope" id="scope" onchange="toggleDateFields(this.value)"
                        class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="all" <?php echo $scope === 'all' ? 'selected' : ''; ?>>ทั้งหมด</option>
                        <option value="today" <?php echo $scope === 'today' ? 'selected' : ''; ?>>วันนี้</option>
                        <option value="7days" <?php echo $scope === '7days' ? 'selected' : ''; ?>>7 วันที่ผ่านมา</option>
                        <option value="30days" <?php echo $scope === '30days' ? 'selected' : ''; ?>>30 วันที่ผ่านมา</option>
                        <option value="custom" <?php echo $scope === 'custom' ? 'selected' : ''; ?>>กำหนดเอง</option>
                    </select>
                </div>

                <div id="customDateFields" class="flex gap-4 flex-1" style="display: <?php echo $scope === 'custom' ? 'flex' : 'none'; ?>;">
                    <div class="flex-1">
                        <label for="start_date" class="block text-sm font-medium text-gray-700 mb-2">วันที่เริ่มต้น</label>
                        <input type="date" name="start_date" id="start_date" value="<?php echo htmlspecialchars($start_date ?? ''); ?>"
                            class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div class="flex-1">
                        <label for="end_date" class="block text-sm font-medium text-gray-700 mb-2">วันที่สิ้นสุด</label>
                        <input type="date" name="end_date" id="end_date" value="<?php echo htmlspecialchars($end_date ?? ''); ?>"
                            class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>

                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium transition-colors">
                    <i class="fas fa-filter mr-2"></i>กรองข้อมูล
                </button>
            </form>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-6 mb-8">
            <div class="card bg-gradient-to-br from-blue-500 to-blue-600 text-white rounded-lg shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm opacity-90 mb-1">คำขอทั้งหมด</p>
                        <p class="text-3xl font-bold"><?php echo number_format($total_requests); ?></p>
                    </div>
                    <i class="fas fa-clipboard-list text-4xl opacity-80"></i>
                </div>
            </div>

            <div class="card bg-gradient-to-br from-yellow-500 to-yellow-600 text-white rounded-lg shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm opacity-90 mb-1">รออนุมัติ</p>
                        <p class="text-3xl font-bold"><?php echo number_format($pending_requests); ?></p>
                    </div>
                    <i class="fas fa-clock text-4xl opacity-80"></i>
                </div>
            </div>

            <div class="card bg-gradient-to-br from-yellow-500 via-purple-500 to-purple-600 text-white rounded-lg shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <?php
                        $total_waiting_next_step = $in_progress_requests + $waiting_relocate_requests;
                        ?>

                        <p class="text-sm opacity-90 mb-1">รอดำเนินการต่อ</p>
                        <p class="text-4xl font-bold"><?php echo number_format($total_waiting_next_step); ?></p>

                        <!-- รายละเอียดย่อย (ช่วยให้เห็นภาพชัดขึ้น) -->
                        <div class="text-xs opacity-80 mt-3 space-y-1">
                            <div class="flex justify-between">
                                <span>• กำลังดำเนินการ</span>
                                <span class="font-semibold"><?php echo number_format($in_progress_requests); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span>• รอจัดซื้อ/ย้าย</span>
                                <span class="font-semibold"><?php echo number_format($waiting_relocate_requests); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- ไอคอนรวม 2 อันซ้อนกันให้ดูสวย -->
                    <div class="relative">
                        <i class="fas fa-tools absolute top-0 right-0 text-3xl opacity-90"></i>
                        <i class="fas fa-shopping-cart absolute -bottom-2 -right-2 text-3xl opacity-90"></i>
                    </div>
                </div>
            </div>

            <div class="card bg-gradient-to-br from-green-500 to-green-600 text-white rounded-lg shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm opacity-90 mb-1">เสร็จสิ้น</p>
                        <p class="text-3xl font-bold"><?php echo number_format($completed_requests); ?></p>
                    </div>
                    <i class="fas fa-check-circle text-4xl opacity-80"></i>
                </div>
            </div>

            <div class="card bg-gradient-to-br from-green-500 to-green-600 text-white rounded-lg shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm opacity-90 mb-1">เสร็จตรงเวลา</p>
                        <p class="text-3xl font-bold"><?php echo number_format($ontime_percentage); ?>%</p>
                    </div>
                    <i class="fas fa-calendar-check text-4xl opacity-80"></i>
                </div>
            </div>

            <div class="card bg-gradient-to-br from-green-500 to-green-600 text-white rounded-lg shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm opacity-90 mb-1">ผ่าน SLA</p>
                        <p class="text-3xl font-bold"><?php echo number_format($sla_percentage); ?>%</p>
                    </div>
                    <i class="fas fa-shield-check text-4xl opacity-80"></i>
                </div>
            </div>
        </div>

        <!-- Charts Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Department Chart -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">คำขอแยกตามแผนก</h2>
                <canvas id="deptChart"></canvas>
            </div>

            <!-- Rating Distribution Chart -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">การให้คะแนนความพึงพอใจ</h2>
                <canvas id="ratingChart"></canvas>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- On-Time Completion Chart -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">เปรียบเทียบการทำงานตามแผน</h2>
                <canvas id="ontimeChart"></canvas>
                <div class="mt-4 text-center">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="p-3 bg-green-50 rounded-lg">
                            <div class="text-sm text-gray-600">ทำงานตามแผน</div>
                            <div class="text-2xl font-bold text-green-600"><?php echo $ontime_percentage; ?>%</div>
                            <div class="text-xs text-gray-500"><?php echo $ontime_count; ?> รายการ</div>
                        </div>
                        <div class="p-3 bg-red-50 rounded-lg">
                            <div class="text-sm text-gray-600">ช้ากว่าแผน</div>
                            <div class="text-2xl font-bold text-red-600"><?php echo $late_percentage; ?>%</div>
                            <div class="text-xs text-gray-500"><?php echo $late_count; ?> รายการ</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SLA Compliance Chart -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">การปฏิบัติตาม SLA</h2>
                <canvas id="slaChart"></canvas>
                <div class="mt-4 text-center">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="p-3 bg-green-50 rounded-lg">
                            <div class="text-sm text-gray-600">ผ่าน SLA</div>
                            <div class="text-2xl font-bold text-green-600"><?php echo $sla_percentage; ?>%</div>
                            <div class="text-xs text-gray-500"><?php echo $sla_met_count; ?> รายการ</div>
                        </div>
                        <div class="p-3 bg-red-50 rounded-lg">
                            <div class="text-sm text-gray-600">ไม่ผ่าน SLA</div>
                            <div class="text-2xl font-bold text-red-600"><?php echo $sla_not_met_percentage; ?>%</div>
                            <div class="text-xs text-gray-500"><?php echo $sla_not_met_count; ?> รายการ</div>
                        </div>
                    </div>
                </div>
                <div class="mt-4 p-3 bg-blue-50 rounded-lg">
                    <div class="text-xs text-gray-600 text-left">
                        <div class="font-semibold mb-2">เกณฑ์ SLA:</div>
                        <div>• Critical: 4 ชั่วโมง (240 นาที)</div>
                        <div>• High: 1 วัน (1,440 นาที)</div>
                        <div>• Normal: 3 วัน (4,320 นาที)</div>
                        <div>• Low: 7 วัน (10,080 นาที)</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Monthly Average Rating Chart -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-xl font-bold text-gray-800 mb-4">ค่าเฉลี่ยการให้คะแนนรายเดือน</h2>
            <canvas id="monthlyRatingChart"></canvas>
        </div>

        <!-- Requests Table -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-bold text-gray-800">คำขอที่รอดำเนินการ</h2>
                <form method="GET" class="flex gap-2">
                    <input type="hidden" name="scope" value="<?php echo htmlspecialchars($scope); ?>">
                    <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($start_date ?? ''); ?>">
                    <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($end_date ?? ''); ?>">
                    <input type="text" name="search" placeholder="ค้นหา..." value="<?php echo htmlspecialchars($search); ?>"
                        class="border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Issue ID</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">หัวข้อ</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">ผู้แจ้ง</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Site</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">แผนก</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">ความสำคัญ</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">ผู้รับผิดชอบ</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">สถานะ</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">กำหนดแก้ไข</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if (empty($repairRequests)): ?>
                            <tr>
                                <td colspan="9" class="px-4 py-8 text-center text-gray-500">ไม่พบข้อมูล</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($repairRequests as $request): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($request['issue_id']); ?></td>
                                    <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($request['title']); ?></td>
                                    <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($request['requester']); ?></td>
                                    <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($request['site']); ?></td>
                                    <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($request['department']); ?></td>
                                    <td class="px-4 py-3 text-sm">
                                        <span class="px-2 py-1 rounded-full text-xs font-medium
                                            <?php echo $request['priority'] == 'High' ? 'bg-red-100 text-red-800' : ($request['priority'] == 'Medium' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'); ?>">
                                            <?php echo htmlspecialchars($request['priority']); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($request['operator'] ?? '-'); ?></td>
                                    <td class="px-4 py-3 text-sm">
                                        <span class="px-2 py-1 rounded-full text-xs font-medium
                                            <?php echo $request['status'] == 'Pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800'; ?>">
                                            <?php echo htmlspecialchars($request['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm"><?php echo $request['plan_date'] ? date('d/m/Y', strtotime($request['plan_date'])) : '-'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="flex justify-center items-center gap-2 mt-6">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&scope=<?php echo htmlspecialchars($scope); ?>&start_date=<?php echo htmlspecialchars($start_date ?? ''); ?>&end_date=<?php echo htmlspecialchars($end_date ?? ''); ?>&search=<?php echo htmlspecialchars($search); ?>"
                            class="px-4 py-2 rounded-lg <?php echo $i == $current_page ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function toggleDateFields(value) {
            const customFields = document.getElementById('customDateFields');
            customFields.style.display = value === 'custom' ? 'flex' : 'none';
        }

        // Department Chart
        const deptCtx = document.getElementById('deptChart').getContext('2d');
        new Chart(deptCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($dept_labels); ?>,
                datasets: [{
                    label: 'จำนวนคำขอ',
                    data: <?php echo json_encode($dept_counts); ?>,
                    backgroundColor: 'rgba(59, 130, 246, 0.8)',
                    borderColor: 'rgba(59, 130, 246, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
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

        // Rating Chart
        const ratingCtx = document.getElementById('ratingChart').getContext('2d');
        new Chart(ratingCtx, {
            type: 'doughnut',
            data: {
                labels: ['1 ดาว', '2 ดาว', '3 ดาว', '4 ดาว', '5 ดาว'],
                datasets: [{
                    data: <?php echo json_encode(array_values($rate_counts)); ?>,
                    backgroundColor: [
                        'rgba(239, 68, 68, 0.8)',
                        'rgba(251, 146, 60, 0.8)',
                        'rgba(234, 179, 8, 0.8)',
                        'rgba(132, 204, 22, 0.8)',
                        'rgba(34, 197, 94, 0.8)'
                    ],
                    borderColor: [
                        'rgba(239, 68, 68, 1)',
                        'rgba(251, 146, 60, 1)',
                        'rgba(234, 179, 8, 1)',
                        'rgba(132, 204, 22, 1)',
                        'rgba(34, 197, 94, 1)'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'right'
                    }
                }
            }
        });

        // Monthly Average Rating Chart
        const monthlyRatingCtx = document.getElementById('monthlyRatingChart').getContext('2d');
        new Chart(monthlyRatingCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($monthly_labels); ?>,
                datasets: [{
                    label: 'ค่าเฉลี่ยคะแนน',
                    data: <?php echo json_encode($monthly_avg_rates); ?>,
                    backgroundColor: 'rgba(34, 197, 94, 0.1)',
                    borderColor: 'rgba(34, 197, 94, 1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointBackgroundColor: 'rgba(34, 197, 94, 1)'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 5,
                        ticks: {
                            stepSize: 0.5
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

        // On-Time Completion Chart
        const ontimeCtx = document.getElementById('ontimeChart').getContext('2d');
        new Chart(ontimeCtx, {
            type: 'pie',
            data: {
                labels: ['ทำงานตามแผน', 'ช้ากว่าแผน'],
                datasets: [{
                    data: [<?php echo $ontime_count; ?>, <?php echo $late_count; ?>],
                    backgroundColor: [
                        'rgba(34, 197, 94, 0.8)',
                        'rgba(239, 68, 68, 0.8)'
                    ],
                    borderColor: [
                        'rgba(34, 197, 94, 1)',
                        'rgba(239, 68, 68, 1)'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = <?php echo $total_ontime_tasks; ?>;
                                const percentage = total > 0 ? ((value / total) * 100).toFixed(2) : 0;
                                return label + ': ' + value + ' รายการ (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });

        // SLA Compliance Chart
        const slaCtx = document.getElementById('slaChart').getContext('2d');
        new Chart(slaCtx, {
            type: 'pie',
            data: {
                labels: ['ผ่าน SLA', 'ไม่ผ่าน SLA'],
                datasets: [{
                    data: [<?php echo $sla_met_count; ?>, <?php echo $sla_not_met_count; ?>],
                    backgroundColor: [
                        'rgba(34, 197, 94, 0.8)',
                        'rgba(239, 68, 68, 0.8)'
                    ],
                    borderColor: [
                        'rgba(34, 197, 94, 1)',
                        'rgba(239, 68, 68, 1)'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = <?php echo $sla_total; ?>;
                                const percentage = total > 0 ? ((value / total) * 100).toFixed(2) : 0;
                                return label + ': ' + value + ' รายการ (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>

</html>
