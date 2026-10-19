<?php
session_start();
include 'connect.php';
include 'auth.php';
include 'function.php';

if (!isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['admin', 'it'])) {
    header("Location: login.php");
    exit();
}

function get_department_name($conn, $user_id) {
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

$user_id = $_SESSION['user_id'];
$dept_name = get_department_name($conn, $user_id);
$user_type = $_SESSION['user_type'];

$year = $_GET['year'] ?? date('Y'); // ค่าเริ่มต้นเป็นปีปัจจุบัน
$previous_year = $year - 1; // ปีก่อนหน้า

try {
    // ดึงข้อมูลจากตาราง sales_data สำหรับปีที่เลือก
    $sql = "SELECT * FROM sales_data WHERE year = :year ORDER BY month ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':year', $year, PDO::PARAM_INT);
    $stmt->execute();
    $sales_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ดึงข้อมูลจากตาราง sales_data สำหรับปีก่อนหน้า
    $sql_previous = "SELECT * FROM sales_data WHERE year = :previous_year ORDER BY month ASC";
    $stmt_previous = $conn->prepare($sql_previous);
    $stmt_previous->bindParam(':previous_year', $previous_year, PDO::PARAM_INT);
    $stmt_previous->execute();
    $sales_data_previous = $stmt_previous->fetchAll(PDO::FETCH_ASSOC);

    // คำนวณผลรวมและค่าเฉลี่ยสำหรับปีที่เลือก
    $totals = [
        'target_sale' => 0,
        'total_sales' => 0,
        'total_consumption' => 0,
        'direct_labour' => 0,
        'indirect_labour' => 0,
        'technical_personnel' => 0,
        'various_industrial_costs' => 0,
        'depreciation_industrial' => 0,
        'salaries_commercial_staff' => 0,
        'fixed_commercial_costs' => 0,
        'variable_commercial_costs' => 0
    ];

    // คำนวณ YTD สำหรับ total_sales และ target_sale (ปีที่เลือก)
    $ytd_data = [];
    $running_total_sales = 0;
    $running_target_sale = 0;
    $ytd_row = [
        'year' => $year,
        'total_sales_ytd' => array_fill(1, 12, 0),
        'target_sale_ytd' => array_fill(1, 12, 0),
        'consumption_to_sales_ratio_monthly' => array_fill(1, 12, 0) // เก็บอัตราส่วนรายเดือน
    ];

    $last_target_sale = 0;
    $last_total_sales = 0;
    $last_ratio = 0; // ตัวแปรเก็บอัตราส่วนล่าสุด
    $last_month_with_data = 0;
    foreach ($sales_data as $data) {
        $running_total_sales += $data['total_sales'];
        $running_target_sale += $data['target_sale'];
        $ytd_row['total_sales_ytd'][$data['month']] = $running_total_sales;
        $ytd_row['target_sale_ytd'][$data['month']] = $running_target_sale;
        // คำนวณ Consumption-to-Sales Ratio รายเดือน (ไม่ใช้ YTD)
        $ytd_row['consumption_to_sales_ratio_monthly'][$data['month']] = $data['total_sales'] > 0 ? ($data['total_consumption'] / $data['total_sales']) * 100 : 0;
        $last_target_sale = $data['target_sale'];
        $last_total_sales = $running_total_sales;
        $last_ratio = $ytd_row['consumption_to_sales_ratio_monthly'][$data['month']]; // อัปเดตอัตราส่วนล่าสุด
        $last_month_with_data = $data['month'];
    }

    // เติมค่า YTD สำหรับเดือนที่เหลือ (ปีที่เลือก)
    for ($month = $last_month_with_data + 1; $month <= 12; $month++) {
        $running_target_sale += $last_target_sale;
        $ytd_row['target_sale_ytd'][$month] = $running_target_sale;
        $ytd_row['total_sales_ytd'][$month] = $last_total_sales;
        $ytd_row['consumption_to_sales_ratio_monthly'][$month] = $last_ratio; // ใช้ค่า ratio ล่าสุด
    }

    $ytd_data[] = $ytd_row;

    // คำนวณ YTD สำหรับ total_sales และ target_sale (ปีก่อนหน้า)
    $ytd_data_previous = [];
    $running_total_sales_previous = 0;
    $running_target_sale_previous = 0;
    $ytd_row_previous = [
        'year' => $previous_year,
        'total_sales_ytd' => array_fill(1, 12, 0),
        'target_sale_ytd' => array_fill(1, 12, 0),
        'consumption_to_sales_ratio_monthly' => array_fill(1, 12, 0) // เก็บอัตราส่วนรายเดือน
    ];

    $last_total_sales_previous = 0;
    $last_target_sale_previous = 0;
    $last_ratio_previous = 0; // ตัวแปรเก็บอัตราส่วนล่าสุด
    $last_month_with_data_previous = 0;
    foreach ($sales_data_previous as $data) {
        $running_total_sales_previous += $data['total_sales'];
        $running_target_sale_previous += $data['target_sale'];
        $ytd_row_previous['total_sales_ytd'][$data['month']] = $running_total_sales_previous;
        $ytd_row_previous['target_sale_ytd'][$data['month']] = $running_target_sale_previous;
        // คำนวณ Consumption-to-Sales Ratio รายเดือน (ไม่ใช้ YTD)
        $ytd_row_previous['consumption_to_sales_ratio_monthly'][$data['month']] = $data['total_sales'] > 0 ? ($data['total_consumption'] / $data['total_sales']) * 100 : 0;
        $last_total_sales_previous = $running_total_sales_previous;
        $last_target_sale_previous = $data['target_sale'];
        $last_ratio_previous = $ytd_row_previous['consumption_to_sales_ratio_monthly'][$data['month']]; // อัปเดตอัตราส่วนล่าสุด
        $last_month_with_data_previous = $data['month'];
    }

    // เติมค่า YTD สำหรับเดือนที่เหลือ (ปีก่อนหน้า)
    for ($month = $last_month_with_data_previous + 1; $month <= 12; $month++) {
        $running_target_sale_previous += $last_target_sale_previous;
        $ytd_row_previous['target_sale_ytd'][$month] = $running_target_sale_previous;
        $ytd_row_previous['total_sales_ytd'][$month] = $last_total_sales_previous;
        $ytd_row_previous['consumption_to_sales_ratio_monthly'][$month] = $last_ratio_previous; // ใช้ค่า ratio ล่าสุด
    }

    $ytd_data_previous[] = $ytd_row_previous;

    // เตรียมข้อมูลสำหรับกราฟ (ทั้งสองปี)
    $target_sale_ytd_data = array_fill(1, 12, 0);
    $total_sales_ytd_data = array_fill(1, 12, 0);
    $consumption_to_sales_ratio_data = array_fill(1, 12, 0);
    $target_sale_ytd_data_previous = array_fill(1, 12, 0);
    $total_sales_ytd_data_previous = array_fill(1, 12, 0);
    $consumption_to_sales_ratio_data_previous = array_fill(1, 12, 0);

    if (!empty($ytd_data)) {
        $target_sale_ytd_data = $ytd_data[0]['target_sale_ytd'];
        $total_sales_ytd_data = $ytd_data[0]['total_sales_ytd'];
        $consumption_to_sales_ratio_data = $ytd_data[0]['consumption_to_sales_ratio_monthly'];
    }

    if (!empty($ytd_data_previous)) {
        $target_sale_ytd_data_previous = $ytd_data_previous[0]['target_sale_ytd'];
        $total_sales_ytd_data_previous = $ytd_data_previous[0]['total_sales_ytd'];
        $consumption_to_sales_ratio_data_previous = $ytd_data_previous[0]['consumption_to_sales_ratio_monthly'];
    }

    $count = count($sales_data);
    if ($count > 0) {
        foreach ($sales_data as $data) {
            $totals['target_sale'] += $data['target_sale'];
            $totals['total_sales'] += $data['total_sales'];
            $totals['total_consumption'] += $data['total_consumption'];
            $totals['direct_labour'] += $data['direct_labour'];
            $totals['indirect_labour'] += $data['indirect_labour'];
            $totals['technical_personnel'] += $data['technical_personnel'];
            $totals['various_industrial_costs'] += $data['various_industrial_costs'];
            $totals['depreciation_industrial'] += $data['depreciation_industrial'];
            $totals['salaries_commercial_staff'] += $data['salaries_commercial_staff'];
            $totals['fixed_commercial_costs'] += $data['fixed_commercial_costs'];
            $totals['variable_commercial_costs'] += $data['variable_commercial_costs'];
        }
    }

    // คำนวณค่าเฉลี่ย
    $averages = array_map(function ($value) use ($count) {
        return $count > 0 ? $value / $count : 0;
    }, $totals);
} catch (PDOException $e) {
    error_log("Error fetching sales data: " . $e->getMessage());
    $sales_data = [];
    $ytd_data = [];
    $ytd_data_previous = [];
    $totals = array_fill_keys(array_keys($totals), 0);
    $averages = array_fill_keys(array_keys($totals), 0);
    $target_sale_ytd_data = array_fill(1, 12, 0);
    $total_sales_ytd_data = array_fill(1, 12, 0);
    $consumption_to_sales_ratio_data = array_fill(1, 12, 0);
    $target_sale_ytd_data_previous = array_fill(1, 12, 0);
    $total_sales_ytd_data_previous = array_fill(1, 12, 0);
    $consumption_to_sales_ratio_data_previous = array_fill(1, 12, 0);
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .table-container { overflow-x: auto; }
        th, td { text-align: center; vertical-align: middle; white-space: nowrap; }
        th { background-color: #b91c1c; color: white; }
        .hover\:bg-gray-50:hover { background-color: #f9fafb; }
        .summary-table th { background-color: #1f2937; color: white; }
        .ytd-table th { background-color: #15803d; color: white; }
        .chart-container { max-width: 1000px; margin: 0 auto; padding: 20px; }
    </style>
</head>
<body>
    <?php include 'nav.php'; ?>

    <div class="container mx-auto px-4 py-8 font-mono">
        <h1 class="text-3xl font-bold mb-6 text-center">Sales Dashboard - Year <?php echo htmlspecialchars($year); ?></h1>

        <!-- ตัวกรองปี -->
        <div class="mb-6 flex justify-center">
            <form method="GET" class="flex items-center space-x-4 bg-white p-4 rounded-lg shadow-md">
                <label for="year" class="text-gray-700 font-semibold">เลือกปี:</label>
                <select name="year" id="year" class="border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500">
                    <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">กรอง</button>
            </form>
        </div>

        <!-- ตารางข้อมูลการขาย -->
        <div class="table-container mb-8">
            <table class="min-w-full bg-white shadow-lg rounded-lg border border-gray-200">
                <thead>
                    <tr>
                        <th class="py-3 px-4">Month</th>
                        <th class="py-3 px-4">Year</th>
                        <th class="py-3 px-4">Target Sale</th>
                        <th class="py-3 px-4">Total Sales</th>
                        <th class="py-3 px-4">Total Consumption</th>
                        <th class="py-3 px-4">Direct Labour</th>
                        <th class="py-3 px-4">Indirect Labour</th>
                        <th class="py-3 px-4">Technical Personnel</th>
                        <th class="py-3 px-4">Various Industrial Costs</th>
                        <th class="py-3 px-4">Depreciation - Industrial</th>
                        <th class="py-3 px-4">Salaries - Commercial Staff</th>
                        <th class="py-3 px-4">Fixed Commercial Costs</th>
                        <th class="py-3 px-4">Variable Commercial Costs</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sales_data)): ?>
                        <tr>
                            <td colspan="13" class="py-4 px-4 text-center text-gray-500">ไม่มีข้อมูลในปีที่เลือก</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($sales_data as $data): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="py-3 px-4"><?php echo htmlspecialchars($data['month']); ?></td>
                                <td class="py-3 px-4"><?php echo htmlspecialchars($data['year']); ?></td>
                                <td class="py-3 px-4"><?php echo number_format($data['target_sale'], 0); ?></td>
                                <td class="py-3 px-4"><?php echo number_format($data['total_sales'], 0); ?></td>
                                <td class="py-3 px-4"><?php echo number_format($data['total_consumption'], 0); ?></td>
                                <td class="py-3 px-4"><?php echo number_format($data['direct_labour'], 0); ?></td>
                                <td class="py-3 px-4"><?php echo number_format($data['indirect_labour'], 0); ?></td>
                                <td class="py-3 px-4"><?php echo number_format($data['technical_personnel'], 0); ?></td>
                                <td class="py-3 px-4"><?php echo number_format($data['various_industrial_costs'], 0); ?></td>
                                <td class="py-3 px-4"><?php echo number_format($data['depreciation_industrial'], 0); ?></td>
                                <td class="py-3 px-4"><?php echo number_format($data['salaries_commercial_staff'], 0); ?></td>
                                <td class="py-3 px-4"><?php echo number_format($data['fixed_commercial_costs'], 0); ?></td>
                                <td class="py-3 px-4"><?php echo number_format($data['variable_commercial_costs'], 0); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- ตาราง YTD สำหรับ Total Sales -->
        <h2 class="text-2xl font-bold mb-4 text-center">Year-to-Date (YTD) Summary - Total Sales</h2>
        <div class="table-container mb-8">
            <table class="min-w-full bg-white shadow-lg rounded-lg border border-gray-200 ytd-table">
                <thead>
                    <tr>
                        <th class="py-3 px-4">Topic</th>
                        <th class="py-3 px-4">Year</th>
                        <th class="py-3 px-4">Jan</th>
                        <th class="py-3 px-4">Feb</th>
                        <th class="py-3 px-4">Mar</th>
                        <th class="py-3 px-4">Apr</th>
                        <th class="py-3 px-4">May</th>
                        <th class="py-3 px-4">Jun</th>
                        <th class="py-3 px-4">Jul</th>
                        <th class="py-3 px-4">Aug</th>
                        <th class="py-3 px-4">Sep</th>
                        <th class="py-3 px-4">Oct</th>
                        <th class="py-3 px-4">Nov</th>
                        <th class="py-3 px-4">Dec</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ytd_data)): ?>
                        <tr>
                            <td colspan="14" class="py-4 px-4 text-center text-gray-500">ไม่มีข้อมูล YTD ในปีที่เลือก</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($ytd_data as $data): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="py-3 px-4">Total Sales (YTD)</td>
                                <td class="py-3 px-4"><?php echo htmlspecialchars($data['year']); ?></td>
                                <td class="py-3 px-4"><?php echo $data['total_sales_ytd'][1] > 0 ? number_format($data['total_sales_ytd'][1], 0) : '-'; ?></td>
                                <td class="py-3 px-4"><?php echo $data['total_sales_ytd'][2] > 0 ? number_format($data['total_sales_ytd'][2], 0) : '-'; ?></td>
                                <td class="py-3 px-4"><?php echo $data['total_sales_ytd'][3] > 0 ? number_format($data['total_sales_ytd'][3], 0) : '-'; ?></td>
                                <td class="py-3 px-4"><?php echo $data['total_sales_ytd'][4] > 0 ? number_format($data['total_sales_ytd'][4], 0) : '-'; ?></td>
                                <td class="py-3 px-4"><?php echo $data['total_sales_ytd'][5] > 0 ? number_format($data['total_sales_ytd'][5], 0) : '-'; ?></td>
                                <td class="py-3 px-4"><?php echo $data['total_sales_ytd'][6] > 0 ? number_format($data['total_sales_ytd'][6], 0) : '-'; ?></td>
                                <td class="py-3 px-4"><?php echo $data['total_sales_ytd'][7] > 0 ? number_format($data['total_sales_ytd'][7], 0) : '-'; ?></td>
                                <td class="py-3 px-4"><?php echo $data['total_sales_ytd'][8] > 0 ? number_format($data['total_sales_ytd'][8], 0) : '-'; ?></td>
                                <td class="py-3 px-4"><?php echo $data['total_sales_ytd'][9] > 0 ? number_format($data['total_sales_ytd'][9], 0) : '-'; ?></td>
                                <td class="py-3 px-4"><?php echo $data['total_sales_ytd'][10] > 0 ? number_format($data['total_sales_ytd'][10], 0) : '-'; ?></td>
                                <td class="py-3 px-4"><?php echo $data['total_sales_ytd'][11] > 0 ? number_format($data['total_sales_ytd'][11], 0) : '-'; ?></td>
                                <td class="py-3 px-4"><?php echo $data['total_sales_ytd'][12] > 0 ? number_format($data['total_sales_ytd'][12], 0) : '-'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!empty($ytd_data_previous)): ?>
                            <?php foreach ($ytd_data_previous as $data_previous): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="py-3 px-4">Total Sales (YTD) - Previous Year</td>
                                    <td class="py-3 px-4"><?php echo htmlspecialchars($data_previous['year']); ?></td>
                                    <td class="py-3 px-4"><?php echo $data_previous['total_sales_ytd'][1] > 0 ? number_format($data_previous['total_sales_ytd'][1], 0) : '-'; ?></td>
                                    <td class="py-3 px-4"><?php echo $data_previous['total_sales_ytd'][2] > 0 ? number_format($data_previous['total_sales_ytd'][2], 0) : '-'; ?></td>
                                    <td class="py-3 px-4"><?php echo $data_previous['total_sales_ytd'][3] > 0 ? number_format($data_previous['total_sales_ytd'][3], 0) : '-'; ?></td>
                                    <td class="py-3 px-4"><?php echo $data_previous['total_sales_ytd'][4] > 0 ? number_format($data_previous['total_sales_ytd'][4], 0) : '-'; ?></td>
                                    <td class="py-3 px-4"><?php echo $data_previous['total_sales_ytd'][5] > 0 ? number_format($data_previous['total_sales_ytd'][5], 0) : '-'; ?></td>
                                    <td class="py-3 px-4"><?php echo $data_previous['total_sales_ytd'][6] > 0 ? number_format($data_previous['total_sales_ytd'][6], 0) : '-'; ?></td>
                                    <td class="py-3 px-4"><?php echo $data_previous['total_sales_ytd'][7] > 0 ? number_format($data_previous['total_sales_ytd'][7], 0) : '-'; ?></td>
                                    <td class="py-3 px-4"><?php echo $data_previous['total_sales_ytd'][8] > 0 ? number_format($data_previous['total_sales_ytd'][8], 0) : '-'; ?></td>
                                    <td class="py-3 px-4"><?php echo $data_previous['total_sales_ytd'][9] > 0 ? number_format($data_previous['total_sales_ytd'][9], 0) : '-'; ?></td>
                                    <td class="py-3 px-4"><?php echo $data_previous['total_sales_ytd'][10] > 0 ? number_format($data_previous['total_sales_ytd'][10], 0) : '-'; ?></td>
                                    <td class="py-3 px-4"><?php echo $data_previous['total_sales_ytd'][11] > 0 ? number_format($data_previous['total_sales_ytd'][11], 0) : '-'; ?></td>
                                    <td class="py-3 px-4"><?php echo $data_previous['total_sales_ytd'][12] > 0 ? number_format($data_previous['total_sales_ytd'][12], 0) : '-'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- กราฟเปรียบเทียบ Target Sale YTD และ Total Sales YTD (ทั้งสองปี) -->
        <h2 class="text-2xl font-bold mb-4 text-center">Comparison: Target Sale YTD vs Total Sales YTD (Year <?php echo htmlspecialchars($year); ?> vs <?php echo htmlspecialchars($previous_year); ?>)</h2>
        <div class="chart-container">
            <canvas id="totalSalesComparisonChart"></canvas>
        </div>

        <!-- ตาราง YTD สำหรับ Consumption-to-Sales Ratio -->
        <h2 class="text-2xl font-bold mb-4 text-center">Monthly Consumption-to-Sales Ratio (%)</h2>
        <div class="table-container mb-8">
            <table class="min-w-full bg-white shadow-lg rounded-lg border border-gray-200 ytd-table">
                <thead>
                    <tr>
                        <th class="py-3 px-4">Topic</th>
                        <th class="py-3 px-4">Year</th>
                        <th class="py-3 px-4">Jan</th>
                        <th class="py-3 px-4">Feb</th>
                        <th class="py-3 px-4">Mar</th>
                        <th class="py-3 px-4">Apr</th>
                        <th class="py-3 px-4">May</th>
                        <th class="py-3 px-4">Jun</th>
                        <th class="py-3 px-4">Jul</th>
                        <th class="py-3 px-4">Aug</th>
                        <th class="py-3 px-4">Sep</th>
                        <th class="py-3 px-4">Oct</th>
                        <th class="py-3 px-4">Nov</th>
                        <th class="py-3 px-4">Dec</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ytd_data)): ?>
                        <tr>
                            <td colspan="14" class="py-4 px-4 text-center text-gray-500">ไม่มีข้อมูลในปีที่เลือก</td>
                        </tr>
                    <?php else: ?>
                        <?php if (!empty($ytd_data_previous)): ?>
                            <?php foreach ($ytd_data_previous as $data_previous): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="py-3 px-4">Consumption-to-Sales Ratio (%) - Previous Year</td>
                                    <td class="py-3 px-4"><?php echo htmlspecialchars($data_previous['year']); ?></td>
                                    <td class="py-3 px-4"><?php echo $data_previous['consumption_to_sales_ratio_monthly'][1] > 0 ? number_format($data_previous['consumption_to_sales_ratio_monthly'][1], 2) . '%' : '-'; ?></td>
                                    <td class="py-3 px-4"><?php echo $data_previous['consumption_to_sales_ratio_monthly'][2] > 0 ? number_format($data_previous['consumption_to_sales_ratio_monthly'][2], 2) . '%' : '-'; ?></td>
                                    <td class="py-3 px-4"><?php echo $data_previous['consumption_to_sales_ratio_monthly'][3] > 0 ? number_format($data_previous['consumption_to_sales_ratio_monthly'][3], 2) . '%' : '-'; ?></td>
                                    <td class="py-3 px-4"><?php echo $data_previous['consumption_to_sales_ratio_monthly'][4] > 0 ? number_format($data_previous['consumption_to_sales_ratio_monthly'][4], 2) . '%' : '-'; ?></td>
                                    <td class="py-3 px-4"><?php echo $data_previous['consumption_to_sales_ratio_monthly'][5] > 0 ? number_format($data_previous['consumption_to_sales_ratio_monthly'][5], 2) . '%' : '-'; ?></td>
                                    <td class="py-3 px-4"><?php echo $data_previous['consumption_to_sales_ratio_monthly'][6] > 0 ? number_format($data_previous['consumption_to_sales_ratio_monthly'][6], 2) . '%' : '-'; ?></td>
                                    <td class="py-3 px-4"><?php echo $data_previous['consumption_to_sales_ratio_monthly'][7] > 0 ? number_format($data_previous['consumption_to_sales_ratio_monthly'][7], 2) . '%' : '-'; ?></td>
                                    <td class="py-3 px-4"><?php echo $data_previous['consumption_to_sales_ratio_monthly'][8] > 0 ? number_format($data_previous['consumption_to_sales_ratio_monthly'][8], 2) . '%' : '-'; ?></td>
                                    <td class="py-3 px-4"><?php echo $data_previous['consumption_to_sales_ratio_monthly'][9] > 0 ? number_format($data_previous['consumption_to_sales_ratio_monthly'][9], 2) . '%' : '-'; ?></td>
                                    <td class="py-3 px-4"><?php echo $data_previous['consumption_to_sales_ratio_monthly'][10] > 0 ? number_format($data_previous['consumption_to_sales_ratio_monthly'][10], 2) . '%' : '-'; ?></td>
                                    <td class="py-3 px-4"><?php echo $data_previous['consumption_to_sales_ratio_monthly'][11] > 0 ? number_format($data_previous['consumption_to_sales_ratio_monthly'][11], 2) . '%' : '-'; ?></td>
                                    <td class="py-3 px-4"><?php echo $data_previous['consumption_to_sales_ratio_monthly'][12] > 0 ? number_format($data_previous['consumption_to_sales_ratio_monthly'][12], 2) . '%' : '-'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php foreach ($ytd_data as $data): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="py-3 px-4">Consumption-to-Sales Ratio (%)</td>
                                    <td class="py-3 px-4"><?php echo htmlspecialchars($data['year']); ?></td>
                                    <td class="py-3 px-4"><?php echo $data['consumption_to_sales_ratio_monthly'][1] > 0 ? number_format($data['consumption_to_sales_ratio_monthly'][1], 2) . '%' : '-'; ?></td>
                                    <td class="py-3 px-4"><?php echo $data['consumption_to_sales_ratio_monthly'][2] > 0 ? number_format($data['consumption_to_sales_ratio_monthly'][2], 2) . '%' : '-'; ?></td>
                                    <td class="py-3 px-4"><?php echo $data['consumption_to_sales_ratio_monthly'][3] > 0 ? number_format($data['consumption_to_sales_ratio_monthly'][3], 2) . '%' : '-'; ?></td>
                                    <td class="py-3 px-4"><?php echo $data['consumption_to_sales_ratio_monthly'][4] > 0 ? number_format($data['consumption_to_sales_ratio_monthly'][4], 2) . '%' : '-'; ?></td>
                                    <td class="py-3 px-4"><?php echo $data['consumption_to_sales_ratio_monthly'][5] > 0 ? number_format($data['consumption_to_sales_ratio_monthly'][5], 2) . '%' : '-'; ?></td>
                                    <td class="py-3 px-4"><?php echo $data['consumption_to_sales_ratio_monthly'][6] > 0 ? number_format($data['consumption_to_sales_ratio_monthly'][6], 2) . '%' : '-'; ?></td>
                                    <td class="py-3 px-4"><?php echo $data['consumption_to_sales_ratio_monthly'][7] > 0 ? number_format($data['consumption_to_sales_ratio_monthly'][7], 2) . '%' : '-'; ?></td>
                                    <td class="py-3 px-4"><?php echo $data['consumption_to_sales_ratio_monthly'][8] > 0 ? number_format($data['consumption_to_sales_ratio_monthly'][8], 2) . '%' : '-'; ?></td>
                                    <td class="py-3 px-4"><?php echo $data['consumption_to_sales_ratio_monthly'][9] > 0 ? number_format($data['consumption_to_sales_ratio_monthly'][9], 2) . '%' : '-'; ?></td>
                                    <td class="py-3 px-4"><?php echo $data['consumption_to_sales_ratio_monthly'][10] > 0 ? number_format($data['consumption_to_sales_ratio_monthly'][10], 2) . '%' : '-'; ?></td>
                                    <td class="py-3 px-4"><?php echo $data['consumption_to_sales_ratio_monthly'][11] > 0 ? number_format($data['consumption_to_sales_ratio_monthly'][11], 2) . '%' : '-'; ?></td>
                                    <td class="py-3 px-4"><?php echo $data['consumption_to_sales_ratio_monthly'][12] > 0 ? number_format($data['consumption_to_sales_ratio_monthly'][12], 2) . '%' : '-'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- กราฟเปรียบเทียบ Consumption-to-Sales Ratio (ทั้งสองปี) -->
        <h2 class="text-2xl font-bold mb-4 text-center">Comparison: Monthly Consumption-to-Sales Ratio (%) (Year <?php echo htmlspecialchars($year); ?> vs <?php echo htmlspecialchars($previous_year); ?>)</h2>
        <div class="chart-container">
            <canvas id="consumptionToSalesRatioChart"></canvas>
        </div>

        <script>
            // เตรียมข้อมูลสำหรับกราฟ
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            const targetSaleYtdData = <?php echo json_encode(array_values($target_sale_ytd_data)); ?>;
            const totalSalesYtdData = <?php echo json_encode(array_values($total_sales_ytd_data)); ?>;
            const consumptionToSalesRatioData = <?php echo json_encode(array_values($consumption_to_sales_ratio_data)); ?>;
            const targetSaleYtdDataPrevious = <?php echo json_encode(array_values($target_sale_ytd_data_previous)); ?>;
            const totalSalesYtdDataPrevious = <?php echo json_encode(array_values($total_sales_ytd_data_previous)); ?>;
            const consumptionToSalesRatioDataPrevious = <?php echo json_encode(array_values($consumption_to_sales_ratio_data_previous)); ?>;

            // กราฟสำหรับ Target Sale YTD และ Total Sales YTD
            const ctxSales = document.getElementById('totalSalesComparisonChart').getContext('2d');
            new Chart(ctxSales, {
                type: 'line',
                data: {
                    labels: months,
                    datasets: [
                        {
                            label: 'Target Sale YTD (<?php echo htmlspecialchars($year); ?>)',
                            data: targetSaleYtdData,
                            borderColor: 'rgba(255, 99, 132, 1)',
                            backgroundColor: 'rgba(255, 99, 132, 0.2)',
                            fill: false,
                            tension: 0.1
                        },
                        {
                            label: 'Target Sale YTD (<?php echo htmlspecialchars($previous_year); ?>)',
                            data: targetSaleYtdDataPrevious,
                            borderColor: 'rgba(255, 99, 132, 0.5)',
                            backgroundColor: 'rgba(255, 99, 132, 0.1)',
                            fill: false,
                            tension: 0.1,
                            borderDash: [5, 5]
                        },
                        {
                            label: 'Total Sales YTD (<?php echo htmlspecialchars($year); ?>)',
                            data: totalSalesYtdData,
                            borderColor: 'rgba(75, 192, 192, 1)',
                            backgroundColor: 'rgba(75, 192, 192, 0.2)',
                            fill: false,
                            tension: 0.1
                        },
                        {
                            label: 'Total Sales YTD (<?php echo htmlspecialchars($previous_year); ?>)',
                            data: totalSalesYtdDataPrevious,
                            borderColor: 'rgba(75, 192, 192, 0.5)',
                            backgroundColor: 'rgba(75, 192, 192, 0.1)',
                            fill: false,
                            tension: 0.1,
                            borderDash: [5, 5]
                        }
                    ]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Amount'
                            },
                            ticks: {
                                callback: function(value) {
                                    return value.toLocaleString();
                                }
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Month'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        title: {
                            display: true,
                            text: 'Target Sale YTD vs Total Sales YTD - Year <?php echo htmlspecialchars($year); ?> vs <?php echo htmlspecialchars($previous_year); ?>'
                        }
                    }
                }
            });

            // กราฟสำหรับ Consumption-to-Sales Ratio (%) - เปลี่ยนเป็น Bar Chart
            const ctxRatio = document.getElementById('consumptionToSalesRatioChart').getContext('2d');
            new Chart(ctxRatio, {
                type: 'bar', // เปลี่ยนจาก 'line' เป็น 'bar'
                data: {
                    labels: months,
                    datasets: [
                        {
                            label: 'Consumption-to-Sales Ratio (%) (<?php echo htmlspecialchars($year); ?>)',
                            data: consumptionToSalesRatioData,
                            backgroundColor: 'rgba(153, 102, 255, 0.8)', // สีม่วงทึบสำหรับแท่ง
                            borderColor: 'rgba(153, 102, 255, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Consumption-to-Sales Ratio (%) (<?php echo htmlspecialchars($previous_year); ?>)',
                            data: consumptionToSalesRatioDataPrevious,
                            backgroundColor: 'rgba(153, 102, 255, 0.4)', // สีม่วงอ่อนสำหรับแท่ง
                            borderColor: 'rgba(153, 102, 255, 0.8)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Ratio (%)'
                            },
                            ticks: {
                                callback: function(value) {
                                    return value.toFixed(2) + '%'; // เพิ่มเครื่องหมาย %
                                }
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Month'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        title: {
                            display: true,
                            text: 'Monthly Consumption-to-Sales Ratio (%) - Year <?php echo htmlspecialchars($year); ?> vs <?php echo htmlspecialchars($previous_year); ?>'
                        }
                    }
                }
            });
        </script>
    </div>
</body>
</html>