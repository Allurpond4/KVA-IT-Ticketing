<?php
session_start();
include 'connect.php';
include 'auth.php';
include 'function.php';

// === ฟังก์ชันเดิมทั้งหมด (ไม่เปลี่ยน) ===
function get_department_name($conn, $user_id) {
    try {
        $sql = "SELECT d.dept_name FROM users u JOIN departments d ON u.department = d.id WHERE u.id = :user_id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['dept_name'] : 'ทุกแผนก';
    } catch (PDOException $e) {
        return 'ทุกแผนก';
    }
}

function get_user_site($conn, $user_id) {
    try {
        $sql = "SELECT site FROM users WHERE id = :user_id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['site'] : 'ทุกไซต์';
    } catch (PDOException $e) {
        return 'ทุกไซต์';
    }
}

function getPriorityColor($priority) {
    switch (strtolower($priority)) {
        case 'high': case 'สูง':   return 'bg-red-100 text-red-800 border-red-200';
        case 'medium': case 'กลาง': return 'bg-yellow-100 text-yellow-800 border-yellow-200';
        case 'low': case 'ต่ำ':    return 'bg-green-100 text-green-800 border-green-200';
        default:                    return 'bg-gray-100 text-gray-800 border-gray-200';
    }
}

function getStatusColor($status) {
    switch (strtolower($status)) {
        case 'waiting for buy': return 'bg-purple-100 text-purple-800 border-purple-200';
        case 'relocate':        return 'bg-indigo-100 text-indigo-800 border-indigo-200';
        default:                return 'bg-gray-100 text-gray-800 border-gray-200';
    }
}

// === ตั้งค่า ===
$items_per_page = 10;
$current_page   = max(1, (int)($_GET['page'] ?? 1));
$offset         = ($current_page - 1) * $items_per_page;

$user_id    = $_SESSION['user_id'];
$user_type  = $_SESSION['user_type'] ?? 'user';
$dept_name  = get_department_name($conn, $user_id);
$user_site  = get_user_site($conn, $user_id);
$search     = trim($_GET['search'] ?? '');

// === Query ===
$where = "(status = 'Waiting for buy' OR status = 'Relocate')";
$params = [];

if ($user_type !== 'admin') {
    $where .= " AND department = :dept_name AND site = :user_site";
    $params[':dept_name'] = $dept_name;
    $params[':user_site'] = $user_site;
}
if ($search !== '') {
    $where .= " AND requester LIKE :search";
    $params[':search'] = "%$search%";
}

// ดึงข้อมูล
$sql = "SELECT * FROM repair_requests WHERE $where ORDER BY issue_id DESC OFFSET :offset ROWS FETCH NEXT :items_per_page ROWS ONLY";
$stmt = $conn->prepare($sql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':items_per_page', $items_per_page, PDO::PARAM_INT);
$stmt->execute();
$repairRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// นับจำนวน
$count_sql = "SELECT COUNT(*) FROM repair_requests WHERE $where";
$stmtc = $conn->prepare($count_sql);
foreach ($params as $k => $v) $stmtc->bindValue($k, $v);
$stmtc->execute();
$total_items = $stmtc->fetchColumn();
$total_pages = ceil($total_items / $items_per_page);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รอจัดซื้อ / ย้ายอุปกรณ์</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
        .compact-table { font-size: 0.8rem; }
        .compact-table th { padding: 8px 4px; font-size: 0.7rem; }
        .compact-table td { padding: 6px 4px; font-size: 0.75rem; }
        .user-avatar { width: 24px; height: 24px; font-size: 0.6rem; }
        .compact-title { max-width: 120px; }
        .compact-name { max-width: 80px; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <?php include 'nav.php'; ?>

    <div class="container mx-auto px-2 py-6">

        <!-- Header -->
        <div class="text-center mb-6">
            <div class="header-gradient text-white rounded-xl p-4 shadow-xl">
                <h1 class="text-2xl font-bold mb-2">
                    รายการรอจัดซื้อ / ย้ายอุปกรณ์
                </h1>
                <p class="text-sm opacity-90">
                    <?php echo htmlspecialchars($dept_name); ?> - Site: <?php echo htmlspecialchars($user_site); ?>
                </p>
                <div class="mt-2 text-xs opacity-80">
                    รายการทั้งหมด: <?php echo number_format($total_items); ?> รายการ
                </div>
            </div>
        </div>

        <!-- Search -->
        <div class="search-container">
            <form method="GET" class="flex flex-wrap items-center gap-3">
                <div class="flex-1 min-w-48">
                    <div class="relative">
                        <i class="fas fa-search absolute left-2 top-1/2 transform -translate-y-1/2 text-gray-400 text-sm"></i>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                               placeholder="ค้นหาผู้แจ้ง หรือรหัสงาน..."
                               class="w-full pl-8 pr-3 py-2 text-sm border-2 border-gray-200 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-200 transition-all">
                    </div>
                </div>
                <button type="submit" class="bg-gradient-to-r from-blue-500 to-purple-600 hover:from-blue-600 hover:to-purple-700 text-white font-semibold py-2 px-4 rounded-lg text-sm">
                    ค้นหา
                </button>
                <?php if ($search !== ''): ?>
                    <a href="?" class="bg-gradient-to-r from-gray-500 to-gray-600 hover:from-gray-600 hover:to-gray-700 text-white font-semibold py-2 px-4 rounded-lg text-sm">
                        ล้าง
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Table -->
        <div class="table-container shadow-xl">
            <div class="table-inner">
                <div class="overflow-x-auto">
                    <table class="min-w-full compact-table">
                        <thead>
                            <tr class="bg-gradient-to-r from-red-600 to-pink-600 text-white">
                                <th class="text-left font-semibold">รหัส</th>
                                <th class="text-left font-semibold">ไซต์</th>
                                <th class="text-left font-semibold">แผนก</th>
                                <th class="text-left font-semibold">หัวข้อ</th>
                                <th class="text-left font-semibold">ผู้แจ้ง</th>
                                <th class="text-left font-semibold">ความสำคัญ</th>
                                <th class="text-left font-semibold">ผู้รับผิดชอบ</th>
                                <th class="text-left font-semibold">สถานะ</th>
                                <th class="text-left font-semibold">วันกำหนด</th>
                                <th class="text-center font-semibold">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if (empty($repairRequests)): ?>
                                <tr>
                                    <td colspan="10" class="py-8 text-center text-gray-500">
                                        <i class="fas fa-inbox text-4xl text-gray-300 mb-2"></i>
                                        <p class="text-lg font-medium">ไม่พบรายการรอจัดซื้อหรือย้ายอุปกรณ์</p>
                                    </td>
                                </tr>
                            <?php else: foreach ($repairRequests as $i => $r): ?>
                                <tr class="table-row <?php echo $i % 2 == 0 ? 'bg-white' : 'bg-gray-50'; ?>">
                                    <td class="font-mono font-bold text-blue-600 text-xs">#<?php echo $r['issue_id']; ?></td>
                                    <td><span class="compact-badge bg-blue-100 text-blue-800"><?php echo htmlspecialchars(substr($r['site'] ?? '', 0, 6)); ?></span></td>
                                    <td><span class="compact-badge bg-purple-100 text-purple-800"><?php echo htmlspecialchars(substr($r['department'] ?? '', 0, 8)); ?></span></td>
                                    <td class="compact-title">
                                        <div class="truncate font-medium text-xs" title="<?php echo htmlspecialchars($r['title'] ?? ''); ?>">
                                            <?php echo htmlspecialchars($r['title'] ?? ''); ?>
                                        </div>
                                    </td>
                                    <td class="compact-name">
                                        <div class="flex items-center">
                                            <div class="user-avatar bg-gradient-to-r from-blue-400 to-purple-500 rounded-full flex items-center justify-center text-white font-bold mr-1">
                                                <?php echo strtoupper(substr($r['requester'] ?? '', 0, 1)); ?>
                                            </div>
                                            <span class="text-xs truncate"><?php echo htmlspecialchars(substr($r['requester'] ?? '', 0, 10)); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="compact-badge <?php echo getPriorityColor($r['priority'] ?? ''); ?>">
                                            <?php 
                                            $p = strtolower($r['priority'] ?? '');
                                            echo $p=='high'||$p=='สูง' ? 'สูง' : ($p=='medium'||$p=='กลาง' ? 'กลาง' : 'ต่ำ');
                                            ?>
                                        </span>
                                    </td>
                                    <td class="compact-name">
                                        <?php echo !empty($r['operator']) 
                                            ? '<div class="flex items-center"><div class="user-avatar bg-gradient-to-r from-green-400 to-blue-500 rounded-full flex items-center justify-center text-white font-bold mr-1">'.strtoupper(substr($r['operator'],0,1)).'</div><span class="text-xs truncate">'.htmlspecialchars(substr($r['operator'],0,8)).'</span></div>'
                                            : '<span class="text-gray-400 italic text-xs">ยังไม่มี</span>'; 
                                        ?>
                                    </td>
                                    <td>
                                        <span class="compact-badge <?php echo getStatusColor($r['status'] ?? ''); ?>">
                                            <?php echo $r['status']=='Waiting for buy' ? 'รอจัดซื้อ' : 'รอย้าย'; ?>
                                        </span>
                                    </td>
                                    <td class="text-xs">
                                        <?php echo !empty($r['plan_date']) ? date('d/m', strtotime($r['plan_date'])) : '-'; ?>
                                    </td>
                                    <td>
                                        <div class="flex space-x-1 justify-center">
                                            <button onclick="location.href='view_request.php?id=<?php echo $r['issue_id']; ?>'"
                                                    class="action-btn bg-green-500 hover:bg-green-600 text-white rounded shadow-sm">
                                                View
                                            </button>
                                            <?php if ($r['status'] != 'In Progress' || $user_type == 'admin'): ?>
                                                <form action="edit_request.php" method="get" style="display:inline;">
                                                    <input type="hidden" name="id" value="<?php echo $r['issue_id']; ?>">
                                                    <button type="submit" class="action-btn bg-blue-500 hover:bg-blue-600 text-white rounded shadow-sm">
                                                        Edit
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="mt-6 flex justify-center">
                <nav class="flex items-center space-x-1">
                    <?php 
                    $link = "?page=%d" . ($search ? "&search=".urlencode($search) : '');
                    if ($current_page > 1): ?>
                        <a href="<?php echo sprintf($link, $current_page-1); ?>" class="bg-white hover:bg-gray-50 text-gray-700 font-medium py-1 px-3 rounded-l-lg border border-gray-300 text-sm">Previous</a>
                    <?php endif; ?>
                    <?php for ($i = max(1, $current_page-2); $i <= min($total_pages, $current_page+2); $i++): ?>
                        <a href="<?php echo sprintf($link, $i); ?>"
                           class="<?php echo $i==$current_page ? 'bg-gradient-to-r from-blue-500 to-purple-600 text-white' : 'bg-white hover:bg-gray-50 text-gray-700'; ?> font-medium py-1 px-3 border border-gray-300 text-sm">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    <?php if ($current_page < $total_pages): ?>
                        <a href="<?php echo sprintf($link, $current_page+1); ?>" class="bg-white hover:bg-gray-50 text-gray-700 font-medium py-1 px-3 rounded-r-lg border border-gray-300 text-sm">Next</a>
                    <?php endif; ?>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>