<?php
session_start();
include 'connect.php';
include 'auth.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// ---------------- PAGINATION ----------------
$items_per_page = 8;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;
$offset = ($current_page - 1) * $items_per_page;

// ---------------- FILTER ----------------
$status = $_GET['status'] ?? '';
$type = $_GET['asset_type'] ?? '';
$search = trim($_GET['search'] ?? '');

// ---------------- WHERE ----------------
$where = "WHERE 1=1";

// TYPE
if (!empty($type)) {
    $where .= " AND a.asset_type = :type";
}

// SEARCH
if (!empty($search)) {
    $where .= " AND (
        a.asset_tag LIKE :search1 OR 
        a.serial_number LIKE :search2 OR 
        a.brand LIKE :search3 OR 
        a.model LIKE :search4 OR
        a.pc_name LIKE :search5 OR
        (u.first_name + ' ' + u.last_name) LIKE :search6 OR
        (
            CASE 
                WHEN EXISTS (
                    SELECT 1 FROM asset_assignments aa
                    WHERE aa.asset_id = a.id 
                    AND aa.returned_date IS NULL
                )
                THEN 'In Use'
                ELSE 'Available'
            END
        ) LIKE :search7
    )";
}

// STATUS FILTER
if (!empty($status)) {
    if ($status == 'In Use') {
        $where .= " AND EXISTS (
            SELECT 1 FROM asset_assignments aa2
            WHERE aa2.asset_id = a.id AND aa2.returned_date IS NULL
        )";
    } else if ($status == 'Available') {
        $where .= " AND NOT EXISTS (
            SELECT 1 FROM asset_assignments aa2
            WHERE aa2.asset_id = a.id AND aa2.returned_date IS NULL
        )";
    }
}

// ---------------- SQL ----------------
$sql = "SELECT 
            a.*,
            u.first_name,
            u.last_name,
            CASE 
                WHEN EXISTS (
                    SELECT 1 FROM asset_assignments aa
                    WHERE aa.asset_id = a.id 
                    AND aa.returned_date IS NULL
                )
                THEN 'In Use'
                ELSE 'Available'
            END AS real_status
        FROM pc_assets a
        LEFT JOIN asset_assignments aa 
            ON aa.asset_id = a.id AND aa.returned_date IS NULL
        LEFT JOIN users u 
            ON aa.user_id = u.id
        $where
        ORDER BY a.pc_name ASC
        OFFSET $offset ROWS FETCH NEXT $items_per_page ROWS ONLY";

$stmt = $conn->prepare($sql);

// BIND TYPE
if (!empty($type)) {
    $stmt->bindValue(':type', $type);
}

// BIND SEARCH
if (!empty($search)) {
    $searchValue = "%" . $search . "%";

    $stmt->bindValue(':search1', $searchValue);
    $stmt->bindValue(':search2', $searchValue);
    $stmt->bindValue(':search3', $searchValue);
    $stmt->bindValue(':search4', $searchValue);
    $stmt->bindValue(':search5', $searchValue);
    $stmt->bindValue(':search6', $searchValue);
    $stmt->bindValue(':search7', $searchValue);
}

$stmt->execute();
$assets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---------------- TOTAL ----------------
$total_sql = "SELECT COUNT(*) as total
              FROM pc_assets a
              LEFT JOIN asset_assignments aa 
                ON aa.asset_id = a.id AND aa.returned_date IS NULL
              LEFT JOIN users u 
                ON aa.user_id = u.id
              $where";

$total_stmt = $conn->prepare($total_sql);

// BIND TYPE
if (!empty($type)) {
    $total_stmt->bindValue(':type', $type);
}

// BIND SEARCH
if (!empty($search)) {
    $total_stmt->bindValue(':search1', $searchValue);
    $total_stmt->bindValue(':search2', $searchValue);
    $total_stmt->bindValue(':search3', $searchValue);
    $total_stmt->bindValue(':search4', $searchValue);
    $total_stmt->bindValue(':search5', $searchValue);
    $total_stmt->bindValue(':search6', $searchValue);
    $total_stmt->bindValue(':search7', $searchValue);
}

$total_stmt->execute();
$total_items = $total_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_items / $items_per_page);

// ---------------- PAGINATION ----------------
$max_pages_to_show = 5;
$half_pages = floor($max_pages_to_show / 2);

$start_page = max(1, $current_page - $half_pages);
$end_page = min($total_pages, $start_page + $max_pages_to_show - 1);

if ($end_page - $start_page + 1 < $max_pages_to_show) {
    $start_page = max(1, $end_page - $max_pages_to_show + 1);
}

// ---------------- COLOR ----------------
function getStatusColor($status)
{
    switch (strtolower($status)) {
        case 'available':
            return 'bg-green-100 text-green-800 border-green-200';
        case 'in use':
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
    <title>Asset Management</title>

    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">

    <style>
        .table-container {
            background: linear-gradient(135deg, #667eea, #764ba2);
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
            border: 1px solid;
        }

        .table-row:hover {
            background: linear-gradient(90deg, #f8fafc, #e2e8f0);
        }

        .header-gradient {
            background: linear-gradient(135deg, #1e40af, #7c3aed);
        }

        .filter-container {
            background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
            border-radius: 12px;
            padding: 20px;
        }
    </style>

</head>

<body class="bg-gray-50">
    <?php include 'nav.php'; ?>

    <div class="container mx-auto px-4 py-8">

        <!-- HEADER -->
        <div class="text-center mb-8">
            <div class="header-gradient text-white rounded-xl p-6 shadow-xl">
                <h1 class="text-3xl font-bold">Asset Management</h1>
                <div class="text-sm mt-2">ทั้งหมด: <?= $total_items ?> รายการ</div>
            </div>
        </div>

        <!-- FILTER -->
        <div class="filter-container mb-6">
            <form method="GET" class="grid md:grid-cols-4 gap-4">

                <select name="status" class="border rounded px-3 py-2">
                    <option value="">-- สถานะ --</option>
                    <option value="Available" <?= $status == 'Available' ? 'selected' : '' ?>>Available</option>
                    <option value="In Use" <?= $status == 'In Use' ? 'selected' : '' ?>>In Use</option>
                </select>

                <select name="asset_type" class="border rounded px-3 py-2">
                    <option value="">-- ประเภท --</option>
                    <option value="Laptop" <?= $type == 'Laptop' ? 'selected' : '' ?>>Laptop</option>
                    <option value="PC" <?= $type == 'PC' ? 'selected' : '' ?>>PC</option>
                </select>

                <input type="text" name="search"
                    value="<?= htmlspecialchars($search) ?>"
                    placeholder="ค้นหา Asset / Serial / User..."
                    class="border rounded px-3 py-2">

                <div class="flex gap-2 items-center">

                    <!-- SEARCH -->
                    <button type="submit"
                        class="flex items-center justify-center gap-2 bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg shadow transition duration-200">
                        <i class="fas fa-search"></i>
                        <span>ค้นหา</span>
                    </button>

                    <!-- RESET -->
                    <a href="asset_list.php"
                        class="flex items-center justify-center gap-2 bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg shadow transition duration-200">
                        <i class="fas fa-rotate-right"></i>
                        <span>รีเซ็ต</span>
                    </a>

                    <!-- ADD -->
                    <a href="add_asset.php"
                        class="flex items-center justify-center gap-2 bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg shadow transition duration-200">
                        <i class="fas fa-plus"></i>
                        <span>เพิ่ม</span>
                    </a>

                </div>

            </form>
        </div>

        <!-- TABLE -->
        <div class="table-container shadow-2xl">
            <div class="table-inner">
                <table class="min-w-full text-sm">

                    <thead class="bg-gradient-to-r from-red-600 to-pink-600 text-white">
                        <tr>
                            <th class="p-2">Asset</th>
                            <th class="p-2">Brand</th>
                            <th class="p-2">Model</th>
                            <th class="p-2">Status</th>
                            <th class="p-2">PC</th>
                            <th class="p-2 text-center">จัดการ</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (empty($assets)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-6">ไม่พบข้อมูล</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($assets as $a): ?>
                                <tr class="table-row border-b">

                                    <td class="p-2 font-semibold text-blue-600">
                                        <?= htmlspecialchars($a['asset_tag']) ?><br>
                                        <span class="text-xs text-gray-500"><?= htmlspecialchars($a['serial_number']) ?></span>
                                    </td>

                                    <td><?= htmlspecialchars($a['brand']) ?></td>
                                    <td><?= htmlspecialchars($a['model']) ?></td>

                                    <td>
                                        <span class="compact-badge <?= getStatusColor($a['real_status']) ?>">
                                            <?= htmlspecialchars($a['real_status']) ?>
                                        </span>

                                        <?php if ($a['real_status'] == 'In Use'): ?>
                                            <div class="text-xs text-red-500">
                                                <?= htmlspecialchars($a['first_name'] . ' ' . $a['last_name']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <td><?= htmlspecialchars($a['pc_name']) ?></td>

                                    <td class="text-center">
                                        <a href="edit_asset.php?id=<?= $a['id'] ?>" class="text-blue-500">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    </td>

                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>

                </table>
            </div>
        </div>

        <!-- PAGINATION -->
        <?php if ($total_pages > 1): ?>
            <div class="mt-8 flex justify-center">
                <nav class="flex items-center space-x-1">

                    <?php if ($current_page > 1): ?>
                        <a href="?page=<?= $current_page - 1 ?>&status=<?= $status ?>&asset_type=<?= $type ?>&search=<?= $search ?>"
                            class="px-3 py-2 border rounded-l bg-white">ก่อนหน้า</a>
                    <?php endif; ?>

                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <a href="?page=<?= $i ?>&status=<?= $status ?>&asset_type=<?= $type ?>&search=<?= $search ?>"
                            class="px-3 py-2 border <?= $i == $current_page ? 'bg-blue-500 text-white' : 'bg-white' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($current_page < $total_pages): ?>
                        <a href="?page=<?= $current_page + 1 ?>&status=<?= $status ?>&asset_type=<?= $type ?>&search=<?= $search ?>"
                            class="px-3 py-2 border rounded-r bg-white">ถัดไป</a>
                    <?php endif; ?>

                </nav>
            </div>
        <?php endif; ?>

    </div>

</body>

</html>