<?php
session_start();
ob_start(); // เริ่ม buffer

include 'connect.php';
include 'auth.php';
include 'function.php';

// ตรวจสอบว่าได้รับ issue_id หรือไม่
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: test.php"); // กลับไปหน้า test.php ถ้าไม่มี id
    exit;
}

$issue_id = $_GET['id'];

// ดึงข้อมูลจาก repair_requests ตาม issue_id
$sql = "SELECT * FROM repair_requests WHERE issue_id = :issue_id";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':issue_id', $issue_id, PDO::PARAM_STR);

try {
    $stmt->execute();
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$request) {
        header("Location: test.php"); // ถ้าไม่พบข้อมูล กลับไปหน้า test.php
        exit;
    }
} catch (PDOException $e) {
    error_log("Error fetching request: " . $e->getMessage());
    header("Location: test.php");
    exit;
}

// ถ้ามีการ submit ฟอร์ม
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $priority = $_POST['priority'];
    $plan_date = $_POST['plan_date'];

    // อัปเดตข้อมูลใน DB
    $update_sql = "UPDATE repair_requests SET title = :title, priority = :priority, plan_date = :plan_date WHERE issue_id = :issue_id";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bindParam(':title', $title, PDO::PARAM_STR);
    $update_stmt->bindParam(':priority', $priority, PDO::PARAM_STR);
    $update_stmt->bindParam(':plan_date', $plan_date, PDO::PARAM_STR);
    $update_stmt->bindParam(':issue_id', $issue_id, PDO::PARAM_STR);

    try {
        $update_stmt->execute();
        header("Location: test.php?success=Updated successfully"); // กลับไปหน้า test.php พร้อมข้อความสำเร็จ
        exit;
    } catch (PDOException $e) {
        error_log("Error updating request: " . $e->getMessage());
        $error = "Failed to update request.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>แก้ไขคำขอซ่อม</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="../styles.css" rel="stylesheet">
</head>
<body>
<div class="flex justify-center items-center h-screen font-mono">
    <div class="bg-white shadow-lg rounded-lg p-6 w-full max-w-md">
        <h2 class="text-2xl font-bold mb-4">แก้ไขคำขอซ่อม (Issue ID: <?php echo htmlspecialchars($request['issue_id']); ?>)</h2>
        
        <?php if (isset($error)): ?>
            <p class="text-red-500 mb-4"><?php echo $error; ?></p>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-gray-700">หัวข้อ (Title):</label>
                <input type="text" name="title" value="<?php echo htmlspecialchars($request['title'] ?? ''); ?>" 
                       class="w-full border border-gray-300 rounded-lg px-4 py-2" required>
            </div>
            <div>
                <label class="block text-gray-700">ความสำคัญ (Priority):</label>
                <select name="priority" class="w-full border border-gray-300 rounded-lg px-4 py-2" required>
                    <option value="Low" <?php echo ($request['priority'] === 'Low') ? 'selected' : ''; ?>>Low</option>
                    <option value="Medium" <?php echo ($request['priority'] === 'Medium') ? 'selected' : ''; ?>>Medium</option>
                    <option value="High" <?php echo ($request['priority'] === 'High') ? 'selected' : ''; ?>>High</option>
                </select>
            </div>
            <div>
                <label class="block text-gray-700">วันที่วางแผน (Plan Date):</label>
                <input type="date" name="plan_date" value="<?php echo htmlspecialchars($request['plan_date'] ?? ''); ?>" 
                       class="w-full border border-gray-300 rounded-lg px-4 py-2">
            </div>
            <div class="flex justify-between">
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">
                    บันทึก
                </button>
                <a href="test.php" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg">
                    ยกเลิก
                </a>
            </div>
        </form>
    </div>
</div>
</body>
</html>
<?php ob_end_flush(); ?>