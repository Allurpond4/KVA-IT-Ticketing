<?php
session_start();
ob_start(); // เริ่ม buffer เพื่อป้องกัน header error

include 'connect.php';
include 'auth.php';
include 'function.php';

require_once('../libs/TCPDF-main/tcpdf.php');

// Function to get department name
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

// Function to get user site
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

// Get department name and site of the logged-in user (ย้ายมาที่นี่)
$user_id = $_SESSION['user_id'];
$dept_name = get_department_name($conn, $user_id);
$user_site = get_user_site($conn, $user_id);
$user_type = $_SESSION['user_type'];

// Handle PDF generation for a single request
function generateSinglePDF($request, $dept_name, $user_site, $user_type) {
    ob_end_clean(); // ล้าง buffer ก่อนสร้าง PDF
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('Your Name');
    $pdf->SetTitle('ฟอร์มคำขอซ่อม - ' . $request['issue_id']);
    $pdf->SetSubject('IT Request Form');
    $pdf->SetKeywords('TCPDF, PDF, IT request');
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(TRUE, 15);
    $pdf->SetFont('thsarabunnew', '', 14);
    $pdf->AddPage();

    // หัวฟอร์ม
    $pdf->SetFont('thsarabunnew', 'B', 16);
    $pdf->Cell(0, 10, "แบบฟอร์มร้องขอดำเนินการด้าน IT (IT Request Form)", 0, 1, 'C');
    $pdf->Ln(5);

    // วัตถุประสงค์ (ใช้ title)
    $pdf->SetFont('thsarabunnew', 'B', 14);
    $pdf->Cell(0, 10, "วัตถุประสงค์/ Objective", 0, 1);
    $pdf->SetFont('thsarabunnew', '', 12);
    $pdf->MultiCell(0, 10, htmlspecialchars($request['title'] ?? ''), 0, 'L');
    $pdf->Ln(5);

    // รายละเอียดการร้องขอ (ใช้ description)
    $pdf->SetFont('thsarabunnew', 'B', 14);
    $pdf->Cell(0, 10, "รายละเอียดการร้องขอ/ Request details", 0, 1);
    $pdf->SetFont('thsarabunnew', '', 12);
    $pdf->MultiCell(0, 40, htmlspecialchars($request['description'] ?? ''), 1, 'L');
    $pdf->Ln(5);

    // เหตุผล วัตถุประสงค์ หรือ ความจำเป็น (ว่าง)
    $pdf->SetFont('thsarabunnew', 'B', 14);
    $pdf->Cell(0, 10, "เหตุผล วัตถุประสงค์ หรือ ความจำเป็น/ Reason, purpose or necessity", 0, 1);
    $pdf->SetFont('thsarabunnew', '', 12);
    $pdf->MultiCell(0, 40, "", 1, 'L');
    $pdf->Ln(5);

    // การลงนามโดยแผนกที่ร้องขอ (ใช้ requester)
    $pdf->SetFont('thsarabunnew', 'B', 14);
    $pdf->Cell(0, 10, "การลงนามโดยแผนกที่ร้องขอ/ Requesting Department Approval", 0, 1);
    $pdf->SetFont('thsarabunnew', '', 12);
    $pdf->Cell(60, 10, "ชื่อผู้ร้องขอ/ Requester:", 0, 0);
    $pdf->Cell(60, 10, htmlspecialchars($request['requester'] ?? ''), 0, 0);
    $pdf->Cell(40, 10, "หน่วยงาน/ Department:", 0, 0);
    $pdf->Cell(0, 10, htmlspecialchars($request['department'] ?? ''), 0, 1);
    $pdf->Cell(60, 10, "วันที่ร้องขอ/ Request Date:", 0, 0);
    $pdf->Cell(0, 10, date('d/m/Y', strtotime($request['create_at'] ?? 'now')), 0, 1);
    $pdf->Ln(5);

    // การลงนามโดยหน่วยงาน IT (ใช้ it_accept และ accept_date)
    $pdf->SetFont('thsarabunnew', 'B', 14);
    $pdf->Cell(0, 10, "การลงนามโดยหน่วยงาน IT/ IT Department Approval", 0, 1);
    $pdf->SetFont('thsarabunnew', '', 12);
    $pdf->Cell(60, 10, "ผู้จัดการ IT / IT Manager:", 0, 0);
    $pdf->Cell(0, 10, htmlspecialchars($request['it_accept'] ?? ''), 0, 1);
    $pdf->Cell(60, 10, "วันที่อนุมัติ/ Accept Date:", 0, 0);
    $pdf->Cell(0, 10, $request['accept_date'] ? date('d/m/Y', strtotime($request['accept_date'])) : '', 0, 1);
    $pdf->Ln(5);

    // Process Flow
    $pdf->SetFont('thsarabunnew', 'B', 12);
    $pdf->Cell(0, 10, "Process Flow", 0, 1);
    $pdf->SetFont('thsarabunnew', '', 10);
    $pdf->MultiCell(0, 10, "ผู้ร้องขอ/Requester --> ผู้บังคับบัญชา/Department Manager --> ผู้จัดการทั่วไป/General Manager --> หน่วยงาน IT / IT Department", 0, 'L');
    $pdf->MultiCell(0, 10, "(กรณีที่ไม่อนุมัติ ให้ทำการชี้แจงให้ผู้ร้องขอทราบทุกครั้ง/ In case of disapproval, please explain to the requester every time.)", 0, 'L');

    $pdf->Output('it_request_' . $request['issue_id'] . '_preview.pdf', 'I');
    exit;
}

// Preview PDF สำหรับรายการเดี่ยว
if (isset($_GET['preview']) && $_GET['preview'] === 'pdf' && isset($_GET['issue_id'])) {
    $issue_id = $_GET['issue_id'];
    $sql_single = "SELECT * FROM repair_requests WHERE issue_id = :issue_id";
    $stmt_single = $conn->prepare($sql_single);
    $stmt_single->bindParam(':issue_id', $issue_id, PDO::PARAM_STR);
    $stmt_single->execute();
    $request = $stmt_single->fetch(PDO::FETCH_ASSOC);
    if ($request) {
        generateSinglePDF($request, $dept_name, $user_site, $user_type);
    }
}

// Get search value and date range
$search = isset($_GET['search']) ? $_GET['search'] : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Pagination variables
$limit = 5; // Number of entries per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Create SQL query for search
if ($user_type == 'admin') {
    $sql = "SELECT * FROM repair_requests WHERE status = 'Completed'";
} else {
    $sql = "SELECT * FROM repair_requests WHERE status = 'Completed' AND department = :dept_name AND site = :user_site";
}

// Add search condition for Requester and date range
if (!empty($search)) {
    $search_term = "%$search%";
    $sql .= " AND requester LIKE :search";
}
if (!empty($start_date)) {
    $sql .= " AND DATE(create_at) >= :start_date";
}
if (!empty($end_date)) {
    $sql .= " AND DATE(create_at) <= :end_date";
}

$sql .= " ORDER BY issue_id DESC OFFSET :offset ROWS FETCH NEXT :limit ROWS ONLY";
$stmt = $conn->prepare($sql);

// Bind parameters
if ($user_type != 'admin') {
    $stmt->bindParam(':dept_name', $dept_name, PDO::PARAM_STR);
    $stmt->bindParam(':user_site', $user_site, PDO::PARAM_STR);
}
if (!empty($search)) {
    $stmt->bindParam(':search', $search_term, PDO::PARAM_STR);
}
if (!empty($start_date)) {
    $stmt->bindParam(':start_date', $start_date, PDO::PARAM_STR);
}
if (!empty($end_date)) {
    $stmt->bindParam(':end_date', $end_date, PDO::PARAM_STR);
}
$stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);

try {
    $stmt->execute();
    $repairRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error executing query: " . $e->getMessage());
    $repairRequests = [];
}

// Update query for total count
if ($user_type == 'admin') {
    $total_sql = "SELECT COUNT(*) as total FROM repair_requests WHERE status = 'Completed'";
} else {
    $total_sql = "SELECT COUNT(*) as total FROM repair_requests WHERE status = 'Completed' AND department = :dept_name AND site = :user_site";
}

if (!empty($search)) {
    $total_sql .= " AND requester LIKE :search";
}
if (!empty($start_date)) {
    $total_sql .= " AND DATE(create_at) >= :start_date";
}
if (!empty($end_date)) {
    $total_sql .= " AND DATE(create_at) <= :end_date";
}

$stmt_total = $conn->prepare($total_sql);
if ($user_type != 'admin') {
    $stmt_total->bindParam(':dept_name', $dept_name, PDO::PARAM_STR);
    $stmt_total->bindParam(':user_site', $user_site, PDO::PARAM_STR);
}
if (!empty($search)) {
    $stmt_total->bindParam(':search', $search_term, PDO::PARAM_STR);
}
if (!empty($start_date)) {
    $stmt_total->bindParam(':start_date', $start_date, PDO::PARAM_STR);
}
if (!empty($end_date)) {
    $stmt_total->bindParam(':end_date', $end_date, PDO::PARAM_STR);
}

try {
    $stmt_total->execute();
    $total_items = $stmt_total->fetch(PDO::FETCH_ASSOC)['total'];
} catch (PDOException $e) {
    error_log("Error executing total count query: " . $e->getMessage());
    $total_items = 0;
}

// Calculate total pages
$total_pages = ceil($total_items / $limit);

// Pagination logic: แสดงแค่ 5 หน้า
$max_pages_to_show = 5;
$half_pages = floor($max_pages_to_show / 2);

// คำนวณหน้าเริ่มต้นและหน้าสิ้นสุด
$start_page = max(1, $page - $half_pages);
$end_page = min($total_pages, $start_page + $max_pages_to_show - 1);

// ปรับหน้าเริ่มต้นถ้าจำนวนหน้าที่แสดงไม่ครบ 5 หน้า
if ($end_page - $start_page + 1 < $max_pages_to_show) {
    $start_page = max(1, $end_page - $max_pages_to_show + 1);
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>รายการแจ้งซ่อม</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="styles.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
</head>
<body>
<?php include 'nav.php'; ?>
<div class="flex justify-center items-center min-h-screen font-mono pt-16">
    <div class="bg-white shadow-lg rounded-lg p-6 relative w-full max-w-8xl">
        <div class="text-2xl font-bold mb-4">รายการแจ้งซ่อม/Repair Requests <?php echo htmlspecialchars($dept_name ?? ''); ?> - Site: <?php echo htmlspecialchars($user_site ?? ''); ?></div>
        
        <!-- Search form -->
        <div class="mb-4">
            <form method="GET" class="flex items-center space-x-2">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="ค้นหา..." class="border border-gray-300 rounded-lg px-4 py-2 w-64">
                <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" class="border border-gray-300 rounded-lg px-4 py-2">
                <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" class="border border-gray-300 rounded-lg px-4 py-2">
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">ค้นหา</button>
                <?php if (!empty($search) || !empty($start_date) || !empty($end_date)): ?>
                    <a href="?" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg">ล้างการค้นหา</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full bg-white">
                <thead>
                    <tr class="bg-red-700 text-white">
                        <th class="w-1/8 py-2 px-4 text-left">Issue ID</th>
                        <th class="w-1/8 py-2 px-4 text-left">Requester</th>
                        <th class="w-1/8 py-2 px-4 text-left">Request</th>
                        <th class="w-1/8 py-2 px-4 text-left">Issue</th>
                        <th class="w-1/8 py-2 px-4 text-left">Priority</th>
                        <th class="w-1/8 py-2 px-4 text-left">Operator</th>
                        <th class="w-1/8 py-2 px-4 text-left">Status</th>
                        <th class="w-1/8 py-2 px-4 text-left">Accept Date</th>
                        <th class="w-1/8 py-2 px-4 text-left">IT Accept</th>
                        <th class="w-1/8 py-2 px-4 text-left">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($repairRequests as $itRequest): ?>
                        <tr class="bg-gray-100 border-b">
                            <td class="py-2 px-4"><?php echo htmlspecialchars($itRequest['issue_id'] ?? ''); ?></td>
                            <td class="py-2 px-4"><?php echo htmlspecialchars($itRequest['requester'] ?? ''); ?></td>
                            <td class="py-2 px-4"><?php echo htmlspecialchars($itRequest['title'] ?? ''); ?></td>
                            <td class="py-2 px-4"><?php echo htmlspecialchars($itRequest['description'] ?? ''); ?></td>
                            <td class="py-2 px-4"><?php echo htmlspecialchars($itRequest['priority'] ?? ''); ?></td>
                            <td class="py-2 px-4"><?php echo htmlspecialchars($itRequest['operator'] ?? ''); ?></td>
                            <td class="py-2 px-4"><?php echo htmlspecialchars($itRequest['status'] ?? ''); ?></td>
                            <td class="py-2 px-4"><?php echo $itRequest['accept_date'] ? date('d/m/Y', strtotime($itRequest['accept_date'])) : ''; ?></td>
                            <td class="py-2 px-4"><?php echo htmlspecialchars($itRequest['it_accept'] ?? ''); ?></td>
                            <td class="py-2 px-4 text-center">
                                <div class="mb-2">
                                    <button class="bg-green-500 hover:bg-green-700 text-white font-bold py-1 px-2 rounded w-full" onclick="showDetails('<?php echo $itRequest['issue_id']; ?>')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="mb-2">
                                    <a href="?preview=pdf&issue_id=<?php echo $itRequest['issue_id']; ?>" 
                                       class="bg-yellow-500 hover:bg-yellow-700 text-white font-bold py-1 px-2 rounded w-full block text-center">
                                        Preview PDF
                                    </a>
                                </div>
                                <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'admin'): ?>
                                    <div class="mb-2">
                                        <form action="edit_request.php" method="get" style="display:inline;">
                                            <input type="hidden" name="id" value="<?php echo $itRequest['issue_id']; ?>">
                                            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-1 px-2 rounded w-full">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        </form>
                                    </div>
                                    <div class="mb-2">
                                        <button class="bg-red-500 hover:bg-red-700 text-white font-bold py-1 px-2 rounded w-full" onclick="confirmDelete('<?php echo $itRequest['issue_id']; ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination controls -->
        <div class="mt-4 flex justify-center space-x-1">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo htmlspecialchars($search); ?>&start_date=<?php echo htmlspecialchars($start_date); ?>&end_date=<?php echo htmlspecialchars($end_date); ?>" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded-l">
                    Previous
                </a>
            <?php endif; ?>
            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                <a href="?page=<?php echo $i; ?>&search=<?php echo htmlspecialchars($search); ?>&start_date=<?php echo htmlspecialchars($start_date); ?>&end_date=<?php echo htmlspecialchars($end_date); ?>" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 <?php if ($i == $page) echo 'bg-gray-500'; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo htmlspecialchars($search); ?>&start_date=<?php echo htmlspecialchars($start_date); ?>&end_date=<?php echo htmlspecialchars($end_date); ?>" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded-r">
                    Next
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    function showDetails(issue_id) {
        Swal.fire({
            title: 'รายละเอียดคำขอ',
            text: 'กำลังพัฒนา: จะแสดงข้อมูลเพิ่มเติมสำหรับคำขอหมายเลข ' + issue_id,
            icon: 'info',
            confirmButtonText: 'ตกลง'
        });
    }

    function confirmDelete(issue_id) {
        Swal.fire({
            title: 'ยืนยันการลบ?',
            text: "คุณต้องการลบคำขอหมายเลข " + issue_id + " ใช่หรือไม่?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'ใช่',
            cancelButtonText: 'ไม่'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'delete_request.php?id=' + issue_id;
            }
        });
    }
</script>
</body>
</html>
<?php ob_end_flush(); // ส่ง output ที่เก็บไว้ใน buffer ?>