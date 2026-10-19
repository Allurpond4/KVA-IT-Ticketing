<?php
session_start(); // เริ่มต้น session ก่อนที่จะมีการส่งข้อมูลใดๆ
include 'auth.php'; // ตรวจสอบการล็อกอิน
include 'connect.php'; // เชื่อมต่อกับฐานข้อมูล

// สร้าง CSRF token หากยังไม่มี
if (empty($_SESSION['_token'])) {
    $_SESSION['_token'] = bin2hex(random_bytes(32));
}

// ดึงข้อมูลผู้ใช้จากฐานข้อมูล
$user_id = $_SESSION['user_id'];
$sql = "SELECT first_name, last_name, phone_number, department, site FROM users WHERE id = :user_id"; // เพิ่มการดึงข้อมูล site
$stmt = $conn->prepare($sql);
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$_SESSION['first_name'] = $user['first_name'] ?? ''; // เก็บ first_name ใน session
$_SESSION['last_name'] = $user['last_name'] ?? ''; // เก็บ last_name ใน session
$_SESSION['site'] = $user['site'] ?? ''; // เก็บ site ใน session
$_SESSION['department'] = $user['department'] ?? ''; // เก็บ department ใน session
$phone_number = $user['phone_number'] ?? '';
$department_id = $user['department'] ?? '';

// ดึงชื่อแผนกจากฐานข้อมูล
$dept_sql = "SELECT dept_name FROM departments WHERE id = :department_id";
$dept_stmt = $conn->prepare($dept_sql);
$dept_stmt->bindParam(':department_id', $department_id, PDO::PARAM_INT);
$dept_stmt->execute();
$department_name = $dept_stmt->fetch(PDO::FETCH_ASSOC)['dept_name'] ?? '';

// ดึงข้อมูลแผนกทั้งหมดจากฐานข้อมูล
$all_depts_sql = "SELECT id, dept_name FROM departments";
$all_depts_stmt = $conn->prepare($all_depts_sql);
$all_depts_stmt->execute();
$departments = $all_depts_stmt->fetchAll(PDO::FETCH_ASSOC);

// ดึงข้อมูล site และ department จาก session
$user_site = $_SESSION['site'] ?? '';
$user_department = $department_name;

// ตรวจสอบการส่งฟอร์ม
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $requester = $_POST['requester'];
    $priority = $_POST['priority'];
    $site = $_SESSION['site']; // ใช้ค่า site จาก session
    $department = ($_SESSION['user_type'] == 'admin') ? $_POST['department'] : $_SESSION['department']; // ใช้ค่า department จาก select ถ้าเป็น admin
    $contact = $phone_number; // ใช้เบอร์โทรจากผู้ใช้

    // ตรวจสอบและย่อขนาดรูปภาพ
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
        $fileTmpPath = $_FILES['attachment']['tmp_name'];
        $fileName = $_FILES['attachment']['name'];
        $fileSize = $_FILES['attachment']['size'];
        $fileType = $_FILES['attachment']['type'];
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));

        $allowedfileExtensions = array('jpg', 'gif', 'png', 'jpeg');
        if (in_array($fileExtension, $allowedfileExtensions)) {
            $uploadFileDir = './uploads/';
            $dest_path = $uploadFileDir . $fileName;

            list($width, $height) = getimagesize($fileTmpPath);
            $newWidth = 800; // กำหนดความกว้างใหม่
            $newHeight = ($height / $width) * $newWidth; // คำนวณความสูงใหม่ตามอัตราส่วน

            $image_p = imagecreatetruecolor($newWidth, $newHeight); // สร้างภาพใหม่

            if ($fileExtension == 'jpg' || $fileExtension == 'jpeg') {
                $image = imagecreatefromjpeg($fileTmpPath);
            } elseif ($fileExtension == 'png') {
                $image = imagecreatefrompng($fileTmpPath);
            } elseif ($fileExtension == 'gif') {
                $image = imagecreatefromgif($fileTmpPath);
            }

            imagecopyresampled($image_p, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

            if ($fileExtension == 'jpg' || $fileExtension == 'jpeg') {
                imagejpeg($image_p, $dest_path, 90);
            } elseif ($fileExtension == 'png') {
                imagepng($image_p, $dest_path, 9);
            } elseif ($fileExtension == 'gif') {
                imagegif($image_p, $dest_path);
            }

            imagedestroy($image_p);
            imagedestroy($image);

            // ตั้งค่าสิทธิ์การเข้าถึงไฟล์
            chmod($dest_path, 0644);
            // บันทึกเส้นทางไฟล์ไปยังฐานข้อมูล
            $sql = "INSERT INTO repair_requests (title, description, requester, priority, site, department, contact, attachment) VALUES (:title, :description, :requester, :priority, :site, :department, :contact, :attachment)";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':requester', $requester);
            $stmt->bindParam(':priority', $priority);
            $stmt->bindParam(':site', $site);
            $stmt->bindParam(':department', $department);
            $stmt->bindParam(':contact', $contact);
            $stmt->bindParam(':attachment', $fileName);

            if ($stmt->execute()) {
                $_SESSION['success'] = "บันทึกข้อมูลเรียบร้อยแล้ว!";
                header('Location: repair_requeste.php');
                exit();
            } else {
                echo "Error: " . $stmt->errorInfo()[2];
            }
        } else {
            echo "Upload failed. Allowed file types: " . implode(',', $allowedfileExtensions);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IT Request Form</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">

    <style>
  

        .form-container {
            max-width: 600px;
            margin: 0 auto;
        }

        .form-header {
            background: linear-gradient(135deg,rgb(230, 2, 2) 0%,rgb(228, 88, 88) 100%);
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
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
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
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25);
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
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25);
        }

        .btn-primary {
            background-color: #2563eb;
            color: white;
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            border-radius: 0.375rem;
            transition: all 0.15s ease-in-out;
            box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2), 0 2px 4px -1px rgba(37, 99, 235, 0.1);
        }

        .btn-primary:hover {
            background-color: #1d4ed8;
            transform: translateY(-1px);
            box-shadow: 0 6px 8px -1px rgba(37, 99, 235, 0.2), 0 3px 5px -1px rgba(37, 99, 235, 0.1);
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

        .site-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background-color: #dbeafe;
            color: #1e40af;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 0.875rem;
            margin-right: 0.5rem;
        }

        .dept-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background-color: #e0e7ff;
            color: #4338ca;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 0.875rem;
        }

        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
        }

        .file-input-wrapper input[type=file] {
            position: absolute;
            inset: 0;
            /* ขยายให้ครอบคลุมทั้ง div */
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        .file-input-button {
            display: inline-block;
            padding: 0.5rem 1rem;
            background-color: #f3f4f6;
            border: 1px dashed #d1d5db;
            border-radius: 0.375rem;
            color: #4b5563;
            font-weight: 500;
            text-align: center;
            transition: all 0.15s ease-in-out;
        }

        .file-input-wrapper:hover .file-input-button {
            background-color: #e5e7eb;
            border-color: #9ca3af;
        }

        .file-name {
            margin-top: 0.5rem;
            font-size: 0.875rem;
            color: #6b7280;
        }
    </style>
</head>

<body>
    <?php include 'nav.php'; ?>

    <div class="container mx-auto px-4 py-8 mt-16">
        <div class="form-container">
            <div class="form-header">
                <h1 class="text-2xl font-bold mb-2">IT Request Form</h1>
                <div class="flex justify-center items-center mt-3">
                    <span class="site-badge"><?php echo htmlspecialchars($user_site); ?></span>
                    <span class="dept-badge"><?php echo htmlspecialchars($user_department); ?></span>
                </div>
            </div>

            <div class="form-body">
                <form method="POST" action="it_request.php" enctype="multipart/form-data" onsubmit="return validateForm()">
                    <input type="hidden" name="_token" value="<?php echo $_SESSION['_token']; ?>">

                    <div class="form-group">
                        <label for="title" class="form-label">ปัญหาที่เจอ / Issue <span class="text-red-500">*</span></label>
                        <input id="title" type="text" class="form-input" name="title" required>
                    </div>

                    <div class="form-group">
                        <label for="description" class="form-label">รายละเอียดของปัญหา / Issue Detail</label>
                        <textarea id="description" class="form-input" name="description" rows="4"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="requester" class="form-label">ผู้แจ้งซ่อม / Requester</label>
                        <input id="requester" type="text" class="form-input" name="requester" value="<?php echo htmlspecialchars($_SESSION['first_name'] ?? '') . ' ' . htmlspecialchars($_SESSION['last_name'] ?? ''); ?>" <?php echo ($_SESSION['user_type'] != 'admin') ? 'readonly' : ''; ?>>
                    </div>

                    <div class="form-group">
                        <label for="priority" class="form-label">ความเร่งด่วน / Priority <span class="text-red-500">*</span></label>
                        <select id="priority" class="form-select" name="priority" required>
                            <option value="Low">Low (ภายใน 3 วัน)</option>
                            <option value="Normal">Normal (ภายใน 7 วัน)</option>
                            <option value="High">High (ภายใน 2 วัน)</option>
                            <option value="Critical">Critical (ภายใน 1 วัน)</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="form-group">
                            <label for="site" class="form-label">บริษัท / Site</label>
                            <input type="hidden" name="site" value="<?php echo htmlspecialchars($_SESSION['site']); ?>">
                            <select id="site" class="form-select" name="site" <?php echo ($_SESSION['user_type'] != 'admin') ? 'disabled' : ''; ?>>
                                <option value="KVA" <?php echo ($_SESSION['site'] == 'KVA') ? 'selected' : ''; ?>>KVA</option>
                                <option value="KVT" <?php echo ($_SESSION['site'] == 'KVT') ? 'selected' : ''; ?>>KVT</option>
                                <option value="SPT" <?php echo ($_SESSION['site'] == 'SPT') ? 'selected' : ''; ?>>SPT</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="department" class="form-label">แผนก / Department</label>
                            <?php if ($_SESSION['user_type'] != 'admin'): ?>
                                <input type="hidden" name="department" value="<?php echo htmlspecialchars($user_department); ?>">
                            <?php endif; ?>
                            <select id="department" class="form-select" name="department" <?php echo ($_SESSION['user_type'] != 'admin') ? 'disabled' : ''; ?>>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept['dept_name']); ?>" <?php echo ($dept['id'] == $department_id) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['dept_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="phone_number" class="form-label">เบอร์ติดต่อ / Phone Number</label>
                        <input id="phone_number" type="text" class="form-input" name="phone_number" value="<?php echo htmlspecialchars($phone_number); ?>" <?php echo ($_SESSION['user_type'] != 'admin') ? 'readonly' : ''; ?>>
                    </div>

                    <!-- แก้ไขในส่วน form-group ของ attachment -->
                    <div class="form-group">
                        <label for="attachment" class="form-label">ไฟล์แนบ / Attachment</label>
                        <div class="file-input-wrapper w-full">
                            <div class="file-input-button w-full py-8 flex flex-col items-center justify-center cursor-pointer" onclick="document.getElementById('attachment').click()">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-gray-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                </svg>
                                <span>คลิกเพื่อเลือกไฟล์ หรือลากไฟล์มาวาง</span>
                                <span class="text-xs text-gray-500 mt-1">รองรับไฟล์ JPG, PNG, GIF ขนาดไม่เกิน 5MB</span>
                            </div>
                            <input id="attachment" type="file" name="attachment" accept=".jpg,.jpeg,.png,.gif" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
                        </div>
                        <div id="file-name" class="file-name hidden"></div>
                    </div>

                    <div class="flex items-center justify-between mt-8">
                        <button type="submit" class="btn-primary">ส่งคำขอ</button>
                        <a href="repair_requests.php" class="btn-secondary">ยกเลิก</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // แสดงชื่อไฟล์ที่เลือก
        document.getElementById('attachment').addEventListener('change', function() {
            const fileName = this.files[0]?.name;
            const fileNameElement = document.getElementById('file-name');

            if (fileName) {
                fileNameElement.textContent = 'ไฟล์ที่เลือก: ' + fileName;
                fileNameElement.classList.remove('hidden');
            } else {
                fileNameElement.classList.add('hidden');
            }
        });

        // ตรวจสอบฟอร์มก่อนส่ง
        function validateForm() {
            const title = document.getElementById('title').value.trim();
            const priority = document.getElementById('priority').value;
            const attachment = document.getElementById('attachment').files[0];

            if (!title) {
                Swal.fire({
                    icon: 'error',
                    title: 'กรุณาระบุปัญหา',
                    text: 'โปรดระบุปัญหาที่ต้องการแจ้ง',
                });
                return false;
            }

            // ตรวจสอบขนาดไฟล์
            if (attachment && attachment.size > 5 * 1024 * 1024) {
                Swal.fire({
                    icon: 'error',
                    title: 'ไฟล์มีขนาดใหญ่เกินไป',
                    text: 'กรุณาอัปโหลดไฟล์ที่มีขนาดไม่เกิน 5MB',
                });
                return false;
            }

            // ตรวจสอบประเภทไฟล์
            if (attachment) {
                const fileType = attachment.type.toLowerCase();
                const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];

                if (!validTypes.includes(fileType)) {
                    Swal.fire({
                        icon: 'error',
                        title: 'ประเภทไฟล์ไม่ถูกต้อง',
                        text: 'กรุณาอัปโหลดไฟล์ประเภท JPG, PNG หรือ GIF เท่านั้น',
                    });
                    return false;
                }
            }

            return true;
        }

        // เพิ่มความสามารถในการลากและวางไฟล์
        const dropArea = document.querySelector('.file-input-button');
        const fileInput = document.getElementById('attachment');

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            dropArea.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, unhighlight, false);
        });

        function highlight() {
            dropArea.classList.add('bg-blue-50', 'border-blue-300');
        }

        function unhighlight() {
            dropArea.classList.remove('bg-blue-50', 'border-blue-300');
        }

        dropArea.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            fileInput.files = files;

            // ทริกเกอร์อีเวนต์ change เพื่อแสดงชื่อไฟล์
            const event = new Event('change');
            fileInput.dispatchEvent(event);
        }
    </script>
</body>

</html>