<?php
session_start();
include 'connect.php'; // เชื่อมต่อกับฐานข้อมูล
include 'auth.php'; // ตรวจสอบการล็อกอิน

// ตรวจสอบว่ามีการส่งค่า id มาหรือไม่
if (isset($_GET['id'])) {
    $issue_id = $_GET['id'];

    // ดึงข้อมูลจากฐานข้อมูล
    $sql = "SELECT * FROM repair_requests WHERE issue_id = :issue_id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':issue_id', $issue_id, PDO::PARAM_STR);
    $stmt->execute();
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        $_SESSION['error'] = "ไม่พบข้อมูลที่ต้องการแก้ไข";
        header("Location: repair_requests.php");
        exit();
    }
} else {
    $_SESSION['error'] = "ไม่พบข้อมูลที่ต้องการแก้ไข";
    header("Location: repair_requests.php");
    exit();
}

// ตรวจสอบว่ามีการส่งฟอร์มมา
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plan_date = isset($_POST['plan_date']) ? $_POST['plan_date'] : NULL;
    $completed_at = isset($_POST['completed_at']) ? $_POST['completed_at'] : NULL;
    $status = isset($_POST['status']) ? $_POST['status'] : NULL;
    $priority = isset($_POST['priority']) ? $_POST['priority'] : NULL;
    $detail = isset($_POST['detail']) ? $_POST['detail'] : NULL;
    $title = isset($_POST['title']) ? $_POST['title'] : NULL;
    $description = isset($_POST['description']) ? $_POST['description'] : NULL;

    // ตรวจสอบค่าว่างของ plan_date และ completed_at
    if (empty($plan_date)) {
        $plan_date = NULL;
    } else {
        $plan_date = date('Y-m-d H:i:s', strtotime($plan_date));
    }
    
    if (empty($completed_at)) {
        $completed_at = NULL;
    } else {
        $completed_at = date('Y-m-d H:i:s', strtotime($completed_at));
    }

    // อัปเดตข้อมูลในฐานข้อมูล
    $sql = "UPDATE repair_requests SET plan_date = :plan_date, completed_at = :completed_at, status = :status, priority = :priority, detail = :detail, title = :title, description = :description WHERE issue_id = :issue_id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':plan_date', $plan_date);
    $stmt->bindParam(':completed_at', $completed_at);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':priority', $priority);
    $stmt->bindParam(':detail', $detail);
    $stmt->bindParam(':title', $title);
    $stmt->bindParam(':description', $description);
    $stmt->bindParam(':issue_id', $issue_id);

    if ($stmt->execute()) {
        $_SESSION['success'] = "แก้ไขข้อมูลสำเร็จ";
    } else {
        $_SESSION['error'] = "เกิดข้อผิดพลาดในการแก้ไขข้อมูล";
    }

    header("Location: repair_requests.php");
    exit();
}

// ตรวจสอบว่ามีการค้นหาหรือไม่
$search_query = isset($_GET['search_query']) ? $_GET['search_query'] : '';

// ตั้งค่าจำนวนรายการต่อหน้า
$items_per_page = 10;

// ตรวจสอบหน้าปัจจุบัน
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;

// ดึงข้อมูลจากฐานข้อมูล
$sql = "SELECT * FROM repair_requests WHERE 1=1";
if ($search_query) {
    $sql .= " AND (issue_id LIKE :search_query OR requester LIKE :search_query)";
}
$sql .= " ORDER BY issue_id DESC OFFSET :offset ROWS FETCH NEXT :items_per_page ROWS ONLY";
$stmt = $conn->prepare($sql);
if ($search_query) {
    $search_query = "%$search_query%";
    $stmt->bindParam(':search_query', $search_query, PDO::PARAM_STR);
}
$stmt->bindParam(':items_per_page', $items_per_page, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$repairRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ดึงจำนวนรายการทั้งหมด
$total_sql = "SELECT COUNT(*) as total FROM repair_requests WHERE 1=1";
if ($search_query) {
    $total_sql .= " AND (issue_id LIKE :search_query OR requester LIKE :search_query)";
}
$stmt_total = $conn->prepare($total_sql);
if ($search_query) {
    $stmt_total->bindParam(':search_query', $search_query, PDO::PARAM_STR);
}
$stmt_total->execute();
$total_items = $stmt_total->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_items / $items_per_page);

$user_type = $request['user_type'] ?? 'user'; // กำหนดค่าเริ่มต้นเป็น 'user'
$status = htmlspecialchars($request['status'] ?? '');
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขรายการแจ้งซ่อม</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
    <style>
        
        .form-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .form-header {
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            color: white;
            border-radius: 0.5rem 0.5rem 0 0;
            padding: 1.5rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .form-header::before {
            content: "";
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            transform: rotate(30deg);
        }
        
        .form-body {
            background-color: white;
            border-radius: 0 0 0.5rem 0.5rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            padding: 2rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #374151;
        }
        
        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            transition: all 0.15s ease-in-out;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #f97316;
            box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.25);
        }
        
        .form-select {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            background-color: white;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.5rem center;
            background-repeat: no-repeat;
            background-size: 1.5em 1.5em;
        }
        
        .form-select:focus {
            outline: none;
            border-color: #f97316;
            box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.25);
        }
        
        .btn-primary {
            background-color: #f97316;
            color: white;
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            border-radius: 0.375rem;
            transition: all 0.15s ease-in-out;
            box-shadow: 0 4px 6px -1px rgba(249, 115, 22, 0.2), 0 2px 4px -1px rgba(249, 115, 22, 0.1);
        }
        
        .btn-primary:hover {
            background-color: #ea580c;
            transform: translateY(-1px);
            box-shadow: 0 6px 8px -1px rgba(249, 115, 22, 0.2), 0 3px 5px -1px rgba(249, 115, 22, 0.1);
        }
        
        .btn-secondary {
            background-color: #f3f4f6;
            color: #4b5563;
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            border-radius: 0.375rem;
            transition: all 0.15s ease-in-out;
            border: 1px solid #d1d5db;
        }
        
        .btn-secondary:hover {
            background-color: #e5e7eb;
            color: #374151;
        }
        
        .issue-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background-color: #ffedd5;
            color: #c2410c;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 0.875rem;
            margin-right: 0.5rem;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background-color: #dbeafe;
            color: #1e40af;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 0.875rem;
        }
        
        .readonly-field {
            background-color: #f9fafb;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <?php include 'nav.php'; ?>
    
    <div class="container mx-auto px-4 py-8 mt-16">
        <div class="form-container">
            <div class="form-header">
                <h1 class="text-2xl font-bold mb-2">แก้ไขรายการแจ้งซ่อม</h1>
                <div class="flex justify-center items-center mt-3">
                    <span class="issue-badge">Issue ID: <?php echo htmlspecialchars($request['issue_id'] ?? ''); ?></span>
                    <span class="status-badge">สถานะ: <?php echo htmlspecialchars($request['status'] ?? 'รอดำเนินการ'); ?></span>
                </div>
            </div>
            
            <div class="form-body">
                <form method="POST" action="edit_request.php?id=<?php echo htmlspecialchars($issue_id ?? ''); ?>" onsubmit="return validateForm()">
                    <input type="hidden" name="_token" value="<?php echo $_SESSION['_token'] ?? ''; ?>">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="form-group">
                            <label for="requester" class="form-label">ผู้แจ้งซ่อม / Requester</label>
                            <input id="requester" type="text" class="form-input readonly-field" name="requester" value="<?php echo htmlspecialchars($request['requester'] ?? ''); ?>" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label for="priority" class="form-label">ความเร่งด่วน / Priority <span class="text-red-500">*</span></label>
                            <select id="priority" class="form-select" name="priority" required>
                                <option value="Low" <?php echo ($request['priority'] == 'Low') ? 'selected' : ''; ?>>Low (ภายใน 2-3 วัน)</option>
                                <option value="Normal" <?php echo ($request['priority'] == 'Normal') ? 'selected' : ''; ?>>Normal (ภายใน 1 วัน)</option>
                                <option value="High" <?php echo ($request['priority'] == 'High') ? 'selected' : ''; ?>>High (ภายใน 1 ชั่วโมง)</option>
                                <option value="Critical" <?php echo ($request['priority'] == 'Critical') ? 'selected' : ''; ?>>Critical (มีผลกระทบการผลิต)</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="title" class="form-label">ปัญหาที่เจอ / Issue <span class="text-red-500">*</span></label>
                            <input id="title" type="text" class="form-input" name="title" value="<?php echo htmlspecialchars($request['title'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="status" class="form-label">สถานะ / Status <span class="text-red-500">*</span></label>
                            <select id="status" name="status" class="form-select" required>
                                <option value="Pending" <?php echo ($request['status'] == 'Pending') ? 'selected' : ''; ?>>รอดำเนินการ (Pending)</option>
                                <option value="In Progress" <?php echo ($request['status'] == 'In Progress') ? 'selected' : ''; ?>>กำลังดำเนินการ (In Progress)</option>
                                <option value="Waiting for buy" <?php echo ($request['status'] == 'Waiting for buy') ? 'selected' : ''; ?>>รอซื้ออุปกรณ์ (Waiting for buy)</option>
                                <option value="Relocate" <?php echo ($request['status'] == 'Relocate') ? 'selected' : ''; ?>>ย้ายตำแหน่ง (Relocate)</option>
                                <option value="Completed" <?php echo ($request['status'] == 'Completed') ? 'selected' : ''; ?>>เสร็จสิ้น (Completed)</option>
                                <option value="Cancelled" <?php echo ($request['status'] == 'Cancelled') ? 'selected' : ''; ?>>ยกเลิก (Cancelled)</option>
                            </select>
                        </div>
                        
                        <div class="form-group md:col-span-2">
                            <label for="description" class="form-label">รายละเอียดของปัญหา / Issue Detail</label>
                            <textarea id="description" class="form-input" name="description" rows="3"><?php echo htmlspecialchars($request['description'] ?? ''); ?></textarea>
                        </div>
                        
                        <?php if ($_SESSION['user_type'] == 'admin'): ?>
                            <div class="form-group">
                                <label for="plan_date" class="form-label">วันที่คาดว่าจะเสร็จ / Planned Date</label>
                                <input id="plan_date" type="datetime-local" class="form-input" name="plan_date" value="<?php echo isset($request['plan_date']) ? htmlspecialchars(date('Y-m-d\TH:i', strtotime($request['plan_date']))) : ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="completed_at" class="form-label">วันที่เสร็จ / Completion Date</label>
                                <input id="completed_at" type="datetime-local" class="form-input" name="completed_at" value="<?php echo isset($request['completed_at']) ? htmlspecialchars(date('Y-m-d\TH:i', strtotime($request['completed_at']))) : ''; ?>">
                            </div>
                            
                            <div class="form-group md:col-span-2">
                                <label for="detail" class="form-label">รายละเอียดการแก้ไข / Solution Detail</label>
                                <textarea id="detail" class="form-input" name="detail" rows="4"><?php echo htmlspecialchars($request['detail'] ?? ''); ?></textarea>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (isset($request['attachment']) && !empty($request['attachment'])): ?>
                        <div class="form-group mt-4">
                            <label class="form-label">ไฟล์แนบ / Attachment</label>
                            <div class="mt-2 p-4 bg-gray-50 rounded-lg border border-gray-200">
                                <div class="flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-400 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                    </svg>
                                    <a href="uploads/<?php echo htmlspecialchars($request['attachment']); ?>" target="_blank" class="text-blue-600 hover:underline">
                                        <?php echo htmlspecialchars($request['attachment']); ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="flex items-center justify-between mt-8">
                        <button type="submit" class="btn-primary">บันทึกการแก้ไข</button>
                        <a href="repair_requests.php" class="btn-secondary">ยกเลิก</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function validateForm() {
            const title = document.getElementById('title').value.trim();
            const status = document.getElementById('status').value;
            
            if (!title) {
                Swal.fire({
                    icon: 'error',
                    title: 'กรุณาระบุปัญหา',
                    text: 'โปรดระบุปัญหาที่ต้องการแจ้ง',
                });
                return false;
            }
            
            // ถ้าสถานะเป็น Completed แต่ไม่ได้ระบุวันที่เสร็จ (สำหรับ admin)
            if (status === 'Completed' && <?php echo ($_SESSION['user_type'] == 'admin') ? 'true' : 'false'; ?>) {
                const completedAt = document.getElementById('completed_at').value;
                if (!completedAt) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'กรุณาระบุวันที่เสร็จ',
                        text: 'เมื่อสถานะเป็น "เสร็จสิ้น" กรุณาระบุวันที่เสร็จด้วย',
                    });
                    return false;
                }
            }
            
            return true;
        }
        
        // ตั้งค่าเริ่มต้นสำหรับ priority
        document.addEventListener('DOMContentLoaded', function() {
            const prioritySelect = document.getElementById('priority');
            if (!prioritySelect.value) {
                prioritySelect.value = 'Normal';
            }
            
            // ถ้าสถานะเปลี่ยนเป็น Completed ให้ตั้งค่าวันที่เสร็จเป็นวันที่ปัจจุบัน (สำหรับ admin)
            const statusSelect = document.getElementById('status');
            const completedAtInput = document.getElementById('completed_at');
            
            if (statusSelect && completedAtInput) {
                statusSelect.addEventListener('change', function() {
                    if (this.value === 'Completed' && !completedAtInput.value) {
                        const now = new Date();
                        const year = now.getFullYear();
                        const month = String(now.getMonth() + 1).padStart(2, '0');
                        const day = String(now.getDate()).padStart(2, '0');
                        const hours = String(now.getHours()).padStart(2, '0');
                        const minutes = String(now.getMinutes()).padStart(2, '0');
                        
                        completedAtInput.value = `${year}-${month}-${day}T${hours}:${minutes}`;
                    }
                });
            }
        });
    </script>
</body>
</html>