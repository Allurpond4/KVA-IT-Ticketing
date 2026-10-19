<?php
session_start();
include 'connect.php'; // เชื่อมต่อกับฐานข้อมูล
include 'auth.php'; // ตรวจสอบการล็อกอิน

// ตรวจสอบว่ามีการค้นหาหรือไม่
$search_query = isset($_GET['search_query']) ? $_GET['search_query'] : '';

// ตั้งค่าจำนวนรายการต่อหน้า
$items_per_page = 10;

// ตรวจสอบหน้าปัจจุบัน
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;

// ดึงข้อมูลจากฐานข้อมูล
$sql = "SELECT * FROM repair_requests WHERE status = 'In Progress'";
if ($search_query) {
    $sql .= " AND (issue_id LIKE '%$search_query%' OR requester LIKE '%$search_query%')";
}
$sql .= " ORDER BY issue_id DESC LIMIT $items_per_page OFFSET $offset";
$result = $conn->query($sql);

$repairRequests = []; // กำหนดค่าเริ่มต้นให้กับตัวแปร $repairRequests
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $repairRequests[] = $row;
    }
}

// ดึงจำนวนรายการทั้งหมด
$total_sql = "SELECT COUNT(*) as total FROM repair_requests WHERE status = 'Pending'";
if ($search_query) {
    $total_sql .= " AND (issue_id LIKE '%$search_query%' OR requester LIKE '%$search_query%')";
}
$total_result = $conn->query($total_sql);
$total_items = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_items / $items_per_page);
?>

<script>
    function confirmDelete(issueId) {
        Swal.fire({
            title: 'คุณแน่ใจหรือไม่?',
            text: "คุณต้องการลบรายการนี้หรือไม่?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'ใช่, ลบเลย!',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'delete_request.php?id=' + issueId;
            }
        });
    }
</script>
<!DOCTYPE html>
<html>

<head>
    <title>รายการแจ้งซ่อม</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="styles.css" rel="stylesheet"> <!-- ลิงก์ไฟล์ CSS -->
</head>
<?php include 'navbar.php'; ?>

<body class="bg-gray-100 ">

    <div class="container mx-auto p-4 font-mono">
        <div class="bg-white shadow-lg rounded-lg p-6 relative">
            <div class="text-2xl font-bold mb-4">Repair Requests</div>
            <?php if (isset($_SESSION['success'])): ?>
                <script>
                    Swal.fire({
                        title: 'สำเร็จ!',
                        text: '<?php echo $_SESSION['success']; ?>',
                        icon: 'success',
                        confirmButtonText: 'ตกลง'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            <?php unset($_SESSION['success']); ?>
                        }
                    });
                </script>
            <?php elseif (isset($_SESSION['error'])): ?>
                <script>
                    Swal.fire({
                        title: 'เกิดข้อผิดพลาด!',
                        text: '<?php echo $_SESSION['error']; ?>',
                        icon: 'error',
                        confirmButtonText: 'ตกลง'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            <?php unset($_SESSION['error']); ?>
                        }
                    });
                </script>
            <?php endif; ?>
            <form method="GET" action="repair_requests.php" class="mb-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="col-span-2">
                        <input type="text" name="search_query" class="form-input mt-1 block w-full" placeholder="Search by Issue ID or Requester"
                            value="<?php echo htmlspecialchars($search_query); ?>">
                    </div>
                    <div>
                        <button type="submit" class="w-full bg-red-700 hover:bg-red-400 text-white font-bold py-2 px-4 rounded">ค้นหา</button>
                    </div>
                </div>
            </form>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead>
                        <tr class="bg-red-700 text-white">
                            <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'admin'): ?>
                                <th class="w-1/8 py-2 px-4 text-left">Confirm</th><?php endif; ?>
                            <th class="w-1/8 py-2 px-4 text-left">Issue ID</th>
                            <th class="w-1/8 py-2 px-4 text-left">Requester</th>
                            <th class="w-1/8 py-2 px-4 text-left">Request</th>
                            <th class="w-1/8 py-2 px-4 text-left">Issue</th>
                            <th class="w-1/8 py-2 px-4 text-left">Priority</th>
                            <th class="w-1/8 py-2 px-4 text-left">ผู้ดำเนินการ</th>
                            <th class="w-1/8 py-2 px-4 text-left">Status</th>
                            <th class="w-1/8 py-2 px-4 text-left">Completion Date</th>
                            <th class="w-1/8 py-2 px-4 text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($repairRequests as $itRequest): ?>
                            <tr class="bg-gray-100 border-b">
                                <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'admin'): ?>
                                    <td>
                                        <?php if (is_null($itRequest['accept_date'])): ?>
                                            <button class="bg-yellow-500 hover:bg-yellow-700 text-white font-bold mx-5 py-1 px-5 rounded-full" onclick="acceptRequest('<?php echo $itRequest['issue_id']; ?>')">รับ</button>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                                <td class="py-2 px-4"><?php echo htmlspecialchars($itRequest['issue_id'] ?? ''); ?></td>
                                <td class="py-2 px-4"><?php echo htmlspecialchars($itRequest['requester'] ?? ''); ?></td>
                                <td class="py-2 px-4"><?php echo htmlspecialchars($itRequest['title'] ?? ''); ?></td>
                                <td class="py-2 px-4"><?php echo htmlspecialchars($itRequest['description'] ?? ''); ?></td>
                                <td class="py-2 px-4"><?php echo htmlspecialchars($itRequest['priority'] ?? ''); ?></td>
                                <td class="py-2 px-4"><?php echo htmlspecialchars($itRequest['operator'] ?? ''); ?></td>
                                <td class="py-2 px-4"><?php echo htmlspecialchars($itRequest['status'] ?? ''); ?></td>
                                <td class="py-2 px-4"><?php echo htmlspecialchars($itRequest['completion_date'] ?? ''); ?></td>
                                <td class="py-2 px-4">
                                    <button class="bg-green-500 hover:bg-green-700 text-white font-bold py-1 px-2 rounded" onclick="showDetails('<?php echo $itRequest['issue_id']; ?>')">แสดงรายละเอียด</button>
                                    <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'admin'): ?>
                                        <form action="edit_request.php" method="get" style="display:inline;">
                                            <input type="hidden" name="id" value="<?php echo $itRequest['issue_id']; ?>">
                                            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-1 px-2 rounded">แก้ไข</button>
                                        </form>
                                        <button class="bg-red-500 hover:bg-red-700 text-white font-bold py-1 px-2 rounded" onclick="confirmDelete('<?php echo $itRequest['issue_id']; ?>')">ลบ</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-4 flex justify-center">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>&search_query=<?php echo urlencode($search_query); ?>" class="mx-1 px-3 py-1 bg-blue-500 text-white rounded"><?php echo $i; ?></a>
                <?php endfor; ?>
            </div>
        </div>
        <div class="absolute bottom-7 right-0 mt-4 mr-4 p-2 border border-gray-300 rounded-lg bg-red-700 ">
                <p class="text-white">Logged in as: <strong><?php echo htmlspecialchars($_SESSION['first_name'] ?? '') . ' ' . htmlspecialchars($_SESSION['last_name'] ?? ''); ?></strong> (<?php echo htmlspecialchars($_SESSION['user_type'] ?? ''); ?>)</p>
            </div>
    </div>
</body>

</html>
<script>
    function showDetails(issueId) {
        console.log('Fetching details for issue ID:', issueId); // เพิ่มการตรวจสอบ
        fetch('get_request_details.php?id=' + issueId)
            .then(response => response.json())
            .then(data => {
                console.log('Response data:', data); // เพิ่มการตรวจสอบ
                if (data.error) {
                    Swal.fire({
                        title: 'เกิดข้อผิดพลาด!',
                        text: data.error,
                        icon: 'error',
                        confirmButtonText: 'ตกลง'
                    });
                } else {
                    // ตรวจสอบค่าที่เป็น null และแทนที่ด้วย "รอดำเนินการ"
                    const accept_date = data.accept_date || 'รอดำเนินการ';
                    const requester = data.requester;
                    const title = data.title;
                    const description = data.description || 'ไม่มีรายละเอียด';
                    const priority = data.priority;
                    const operator = data.operator || 'รอดำเนินการ';
                    const status = data.status || 'รอดำเนินการ';
                    const completion_date = data.completion_date || 'รอดำเนินการ';
                    const completion_at = data.completion_at || 'รอดำเนินการ';
                    const detail = data.detail || 'ไม่มีรายละเอียด';

                    Swal.fire({
                        title: 'รายละเอียดการแจ้งซ่อม',
                        html: `
                        <p><strong>วันที่ยืนยันการแจ้งซ่อม:</strong> ${accept_date}</p>
                        <p><strong>Requester:</strong> ${requester}</p>
                        <p><strong>Request:</strong> ${title}</p>
                        <p><strong>Issue:</strong> ${description}</p>
                        <p><strong>Priority:</strong> ${priority}</p>
                        <p><strong>Operator:</strong> ${operator}</p>
                        <p><strong>Status:</strong> ${status}</p>
                        <p><strong>Completion Date:</strong> ${completion_date}</p>
                        <p><strong>Completion at:</strong> ${completion_at}</p>
                        <p><strong>Detail:</strong> ${detail}</p>
                    `,
                        icon: 'info',
                        confirmButtonText: 'ตกลง'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    title: 'เกิดข้อผิดพลาด!',
                    text: 'ไม่สามารถดึงข้อมูลได้',
                    icon: 'error',
                    confirmButtonText: 'ตกลง'
                });
            });
    }
</script>
<script>
    function acceptRequest(issueId) {
        fetch('accept.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    issue_id: issueId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'สำเร็จ!',
                        text: 'การแจ้งซ่อมได้รับการยอมรับแล้ว',
                        icon: 'success',
                        confirmButtonText: 'ตกลง'
                    }).then(() => {
                        location.reload(); // รีเฟรชหน้าเพื่ออัปเดตข้อมูล
                    });
                } else {
                    Swal.fire({
                        title: 'เกิดข้อผิดพลาด!',
                        text: data.error,
                        icon: 'error',
                        confirmButtonText: 'ตกลง'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    title: 'เกิดข้อผิดพลาด!',
                    text: 'ไม่สามารถบันทึกข้อมูลได้',
                    icon: 'error',
                    confirmButtonText: 'ตกลง'
                });
            });
    }
</script>