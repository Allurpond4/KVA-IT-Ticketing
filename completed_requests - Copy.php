<?php
session_start();
include 'connect.php';
include 'auth.php';
include 'function.php';

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

// Get department name and site of the logged-in user
$user_id = $_SESSION['user_id'];
$dept_name = get_department_name($conn, $user_id);
$user_site = get_user_site($conn, $user_id);

// Check user type
$user_type = $_SESSION['user_type'];

// Get search value
$search = isset($_GET['search']) ? $_GET['search'] : '';

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

// Add search condition for Requester only
if (!empty($search)) {
    $search_term = "%$search%";
    $sql .= " AND requester LIKE :search";
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

$stmt_total = $conn->prepare($total_sql);
if ($user_type != 'admin') {
    $stmt_total->bindParam(':dept_name', $dept_name, PDO::PARAM_STR);
    $stmt_total->bindParam(':user_site', $user_site, PDO::PARAM_STR);
}
if (!empty($search)) {
    $stmt_total->bindParam(':search', $search_term, PDO::PARAM_STR);
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
?>
<!DOCTYPE html>
<html>
<head>
    <title>รายการแจ้งซ่อม</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="styles.css" rel="stylesheet">
</head>
<body>
<?php include 'nav.php'; ?>
<div class="flex justify-center items-center min-h-screen font-mono pt-16">
    <div class="bg-white shadow-lg rounded-lg p-6 relative w-full max-w-8xl">
        <div class="text-2xl font-bold mb-4">รายการแจ้งซ่อม/Repair Requests <?php echo htmlspecialchars($dept_name ?? ''); ?> - Site: <?php echo htmlspecialchars($user_site ?? ''); ?></div>
        
        <!-- Search form -->
        <div class="mb-4">
            <form method="GET" class="flex items-center space-x-2">
                <input type="text" 
                       name="search" 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="ค้นหา..." 
                       class="border border-gray-300 rounded-lg px-4 py-2 w-64">
                <button type="submit" 
                        class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">
                    ค้นหา
                </button>
                <?php if (!empty($search)): ?>
                    <a href="?" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg">
                        ล้างการค้นหา
                    </a>
                <?php endif; ?>
                
            </form>
        </div>

        <!-- Rest of the code remains the same as in your original file -->
        <?php if (isset($_SESSION['success'])): ?>
            <!-- ... Success alert ... -->
        <?php elseif (isset($_SESSION['error'])): ?>
            <!-- ... Error alert ... -->
        <?php endif; ?>
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
                        <th class="w-1/8 py-2 px-4 text-left">Complete Date</th>
                        <th class="w-1/8 py-2 px-4 text-left">Reportor</th>
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
                            <td class="py-2 px-4"><?php echo htmlspecialchars($itRequest['completed_at'] ?? ''); ?></td>
                            <td class="py-2 px-4"><?php echo htmlspecialchars($itRequest['it_report_complete'] ?? ''); ?></td>
                            <td class="py-2 px-4">
                                <button class="bg-green-500 hover:bg-green-700 text-white font-bold py-1 px-2 rounded w-full" onclick="showDetails('<?php echo $itRequest['issue_id']; ?>')">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'admin'): ?>
                                    <form action="edit_request.php" method="get" style="display:inline;">
                                        <input type="hidden" name="id" value="<?php echo $itRequest['issue_id']; ?>">
                                        <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-1 px-2 rounded w-full mt-2">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </form>
                                    <button class="bg-red-500 hover:bg-red-700 text-white font-bold py-1 px-2 rounded w-full mt-2" onclick="confirmDelete('<?php echo $itRequest['issue_id']; ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination controls -->
        <div class="mt-4 flex justify-center">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo htmlspecialchars($search); ?>" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded-l">
                    Previous
                </a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?php echo $i; ?>&search=<?php echo htmlspecialchars($search); ?>" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 <?php if ($i == $page) echo 'bg-gray-500'; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo htmlspecialchars($search); ?>" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded-r">
                    Next
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>