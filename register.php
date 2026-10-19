<?php
session_start();
include 'connect.php'; // เชื่อมต่อกับฐานข้อมูล

// ดึงข้อมูลแผนกจากฐานข้อมูล
$sql = "SELECT id, dept_name FROM departments";
$result = $conn->query($sql);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // รับข้อมูลจากฟอร์ม
    $username = $_POST['reg_username'];
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $department = $_POST['department'];
    $site = $_POST['site'];
    $password = password_hash($_POST['reg_password'], PASSWORD_DEFAULT); // แฮชรหัสผ่าน
    $user_type = 'users'; // ตั้งค่าเริ่มต้นเป็น member
    $status = 'Inactive'; // ตั้งค่าเริ่มต้นเป็น Inactive
    $email = $_POST['email'];
    $phone_number = $_POST['phone_number'];
    $employee_id = $_POST['employee_id'];

    // ตรวจสอบว่าชื่อผู้ใช้หรือรหัสพนักงานมีอยู่ในฐานข้อมูลแล้วหรือไม่
    $check_sql = "SELECT id FROM users WHERE username = :username OR employee_id = :employee_id";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bindParam(':username', $username);
    $check_stmt->bindParam(':employee_id', $employee_id);
    $check_stmt->execute();
    $existing_user = $check_stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing_user) {
        $_SESSION['error'] = "ชื่อผู้ใช้หรือรหัสพนักงานนี้มีอยู่ในระบบแล้ว";
    } else {
        // เตรียมคำสั่ง SQL สำหรับการเพิ่มข้อมูล
        $sql = "INSERT INTO users (username, first_name, last_name, department, site, password, user_type, status,email, phone_number, employee_id) VALUES (:username, :first_name, :last_name, :department, :site, :password, :user_type, :status, :email,:phone_number, :employee_id)";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':first_name', $first_name);
        $stmt->bindParam(':last_name', $last_name);
        $stmt->bindParam(':department', $department);
        $stmt->bindParam(':site', $site);
        $stmt->bindParam(':password', $password);
        $stmt->bindParam(':user_type', $user_type);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':phone_number', $phone_number);
        $stmt->bindParam(':employee_id', $employee_id);

        // ดำเนินการคำสั่ง SQL
        if ($stmt->execute()) {
            $_SESSION['success'] = "ลงทะเบียนสำเร็จ!";

            // ดึงค่า id ที่เพิ่ง insert
            $id = $conn->lastInsertId(); // เพิ่มบรรทัดนี้

            // ✅ เพิ่มโค้ดแจ้งเตือนเข้า Microsoft Teams ✅
            $webhookUrl = "https://krugerfan.webhook.office.com/webhookb2/bb3ad2dc-8361-42a1-8568-1021283a6590@f79edb23-9a2f-4d67-a6fe-f6a55a6a3460/IncomingWebhook/7408181056044722aca236242fcfdeaa/bc816e01-a01a-464f-a537-86b2bb6697ea/V26Fy7aKSbFLIfSSv33Dfk5VT-Qz0OlMEvVOgDCWJIJ3E1"; // ใส่ URL Webhook ของคุณ

            $message = [
                "text" => " **Register Report **\n\n" .
                    "#️⃣**ชื่อจริง - นามสกุล : **\n$first_name $last_name\n\n" .
                    "⚙️**แผนก:**\n$department\n\n" .
                    "️**สถานที่:**\n$site\n\n" .
                    "⏱️**รหัสพนักงาน:**\n$employee_id\n\n" . // แก้ไขบรรทัดนี้
                    "🆔**ID:**\n$id\n\n" . // แก้ไขบรรทัดนี้
                    "**Link:**\nhttp://eweb.krugerfan.com/it_request/it/edit_user.php?id=$id" // แก้ไขบรรทัดนี้
            ];

            // กำหนดเส้นทางไฟล์ใบรับรอง SSL (ถ้าจำเป็น)
            $certPath = $_SERVER['DOCUMENT_ROOT'] . '\it_request\it\certificate\cacert.pem';

            // ส่งข้อมูลไปยัง Webhook
            $ch = curl_init($webhookUrl);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
            curl_setopt($ch, CURLOPT_CAINFO, $certPath); // ตรวจสอบ SSL (ถ้าใช้ใบรับรอง)
            $response = curl_exec($ch);
            if (curl_errno($ch)) {
                error_log("cURL Error: " . curl_error($ch));
            }
            curl_close($ch);
        } else {
            echo "Error: " . $stmt->errorInfo()[2];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สมัครสมาชิก - Registration</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3b82f6',
                        secondary: '#1e40af',
                    }
                }
            }
        }
    </script>
    <style>
        .glass-effect {
            backdrop-filter: blur(15px);
            background: rgba(255, 255, 255, 0.92);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('bg1.jpg');
            background-size: cover;
            background-position: center;
            opacity: 0.5;
            z-index: -1;
        }

        .input-focus:focus {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        /* Enhanced glass effect for better visibility */
        .glass-card {
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.4);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.1);
        }
    </style>
    <script>
        function validatePassword(password) {
            const regex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?])[A-Za-z\d!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]{8,20}$/;
            const conditions = {
                length: password.length >= 8 && password.length <= 20,
                lowercase: /[a-z]/.test(password),
                uppercase: /[A-Z]/.test(password),
                number: /\d/.test(password),
                specialChar: /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password),
                noSpaces: !/\s/.test(password),
                noRepeating: !/(.)\1{2,}/.test(password)
            };

            const isValid = regex.test(password) && Object.values(conditions).every(condition => condition === true);

            if (!isValid) {
                let errorMessage = "รหัสผ่านไม่ผ่านเงื่อนไข: ";
                if (!conditions.length) errorMessage += "ต้องมีความยาว 8-20 ตัวอักษร, ";
                if (!conditions.lowercase) errorMessage += "ต้องมีตัวพิมพ์เล็กอย่างน้อย 1 ตัว, ";
                if (!conditions.uppercase) errorMessage += "ต้องมีตัวพิมพ์ใหญ่อย่างน้อย 1 ตัว, ";
                if (!conditions.number) errorMessage += "ต้องมีตัวเลขอย่างน้อย 1 ตัว, ";
                if (!conditions.specialChar) errorMessage += "ต้องมีอักขระพิเศษอย่างน้อย 1 ตัว, ";
                if (!conditions.noSpaces) errorMessage += "ห้ามมีช่องว่าง, ";
                if (!conditions.noRepeating) errorMessage += "ห้ามมีตัวอักษรซ้ำกันเกิน 2 ตัวติดกัน, ";

                errorMessage = errorMessage.slice(0, -2);
                return {
                    isValid: false,
                    message: errorMessage
                };
            }
            return {
                isValid: true,
                message: ""
            };
        }

        function validateForm() {
            const password = document.getElementById('reg_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            const passwordValidation = validatePassword(password);
            if (!passwordValidation.isValid) {
                Swal.fire({
                    title: 'ข้อผิดพลาด!',
                    text: passwordValidation.message,
                    icon: 'error',
                    confirmButtonText: 'ตกลง'
                });
                return false;
            }

            if (password !== confirmPassword) {
                Swal.fire({
                    title: 'ข้อผิดพลาด!',
                    text: 'รหัสผ่านไม่ตรงกัน',
                    icon: 'error',
                    confirmButtonText: 'ตกลง'
                });
                return false;
            }

            return true;
        }

        // Add loading animation
        function showLoading() {
            Swal.fire({
                title: 'กำลังดำเนินการ...',
                html: 'กรุณารอสักครู่',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading()
                }
            });
        }
    </script>
</head>

<body class="gradient-bg min-h-screen py-8 px-4">
    <div class="container mx-auto max-w-2xl">
        <!-- Header Section -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-white rounded-full shadow-lg mb-4">
                <i class="fas fa-user-plus text-3xl text-blue-600"></i>
            </div>
            <h1 class="text-4xl font-bold text-black mb-2">
                สมัครสมาชิก (User Registration)
            </h1>
            <p class="text-black-100 text-lg">
                กรุณากรอกข้อมูลให้ครบถ้วนเพื่อสมัครสมาชิก (Please complete the form to register)
            </p>
        </div>

        <!-- Main Form Card -->
        <div class="glass-effect rounded-2xl shadow-2xl p-8 border border-white/20">
            <?php if (isset($_SESSION['error'])): ?>
                <script>
                    Swal.fire({
                        title: 'ข้อผิดพลาด!',
                        text: '<?php echo $_SESSION['error']; ?>',
                        icon: 'error',
                        confirmButtonText: 'ตกลง'
                    });
                    <?php unset($_SESSION['error']); ?>
                </script>
            <?php endif; ?>

            <?php if (isset($_SESSION['success'])): ?>
                <script>
                    Swal.fire({
                        title: 'สำเร็จ!',
                        text: '<?php echo $_SESSION['success']; ?>',
                        icon: 'success',
                        confirmButtonText: 'ตกลง'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = 'http://eweb.krugerfan.com/';
                        }
                    });
                    <?php unset($_SESSION['success']); ?>
                </script>
            <?php endif; ?>

            <form method="POST" action="register.php" onsubmit="return validateForm()" class="space-y-6">
                <input type="hidden" name="_token" value="<?php echo $_SESSION['_token'] ?? ''; ?>">

                <!-- Account Information Section -->
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl p-6 border-l-4 border-blue-500">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-user-circle mr-3 text-blue-600"></i>
                        ข้อมูลบัญชีผู้ใช้ (Account Information)
                    </h3>

                    <div class="grid md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label class="block text-sm font-semibold text-gray-700">
                                <i class="fas fa-user mr-2"></i>
                                Username (ชื่อผู้ใช้)
                            </label>
                            <input id="reg_username" placeholder="ตัวอย่าง: Somsak.S" type="text" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-300 input-focus" name="reg_username" required>
                            <p class="text-xs text-gray-500">
                                รูปแบบ: ชื่อ.นามสกุลย่อ (Format: firstname.lastname)
                            </p>
                        </div>

                        <div class="space-y-2">
                            <label class="block text-sm font-semibold text-gray-700">
                                <i class="fas fa-id-badge mr-2"></i>
                                รหัสพนักงาน (Employee ID)
                            </label>
                            
                            <input id="employee_id"
                                placeholder="รหัสพนักงาน"
                                type="text"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-300 input-focus"
                                name="employee_id"
                                required>
                        </div>
                    </div>

                    <div class="grid md:grid-cols-2 gap-6 mt-6">
                        <div class="space-y-2">
                            <label class="block text-sm font-semibold text-gray-700">
                                <i class="fas fa-lock mr-2"></i>
                                รหัสผ่าน (Password)
                            </label>
                            <input id="reg_password" placeholder="รหัสผ่านที่แข็งแกร่ง" type="password" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-300 input-focus" name="reg_password" required>
                            <p class="text-xs text-gray-500">
                                ต้องมี A-Z, a-z, 0-9, อักขระพิเศษ (8–20 ตัวอักษร)<br>
                                Must include uppercase, lowercase, number, and special character (8–20 characters)
                            </p>
                        </div>

                        <div class="space-y-2">
                            <label class="block text-sm font-semibold text-gray-700">
                                <i class="fas fa-lock mr-2"></i>
                                ยืนยันรหัสผ่าน (Confirm Password)
                            </label>
                            <input id="confirm_password"
                                placeholder="ยืนยันรหัสผ่านอีกครั้ง"
                                type="password"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-300 input-focus"
                                name="confirm_password"
                                required>
                        </div>
                    </div>
                </div>

                <!-- Personal Information Section -->
                <div class="bg-gradient-to-r from-green-50 to-emerald-50 rounded-xl p-6 border-l-4 border-green-500">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-address-card mr-3 text-green-600"></i>
                        ข้อมูลส่วนตัว (Personal Information)
                    </h3>

                    <div class="grid md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label class="block text-sm font-semibold text-gray-700">
                                <i class="fas fa-user mr-2"></i>
                                ชื่อจริง (First Name)
                            </label>
                            <input id="first_name"
                                placeholder="ชื่อจริงภาษาไทยหรืออังกฤษ"
                                type="text"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all duration-300 input-focus"
                                name="first_name"
                                required>
                        </div>

                        <div class="space-y-2">
                            <label class="block text-sm font-semibold text-gray-700">
                                <i class="fas fa-user mr-2"></i>
                                นามสกุล (Last Name)
                            </label>
                            <input id="last_name"
                                placeholder="นามสกุลภาษาไทยหรืออังกฤษ"
                                type="text"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all duration-300 input-focus"
                                name="last_name"
                                required>
                        </div>
                    </div>

                    <div class="grid md:grid-cols-2 gap-6 mt-6">
                        <div class="space-y-2">
                            <label class="block text-sm font-semibold text-gray-700">
                                <i class="fas fa-envelope mr-2"></i>
                                อีเมล (Email Address)
                            </label>

                            <input id="email"
                                placeholder="example@company.com"
                                type="email"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all duration-300 input-focus"
                                name="email"
                                required>
                            <p class="text-xs text-gray-500">
                                หากไม่มีอีเมลบริษัท ให้ใช้อีเมลหัวหน้าหรือผู้ติดต่อ
                                (If unavailable, please use your supervisor’s email)
                            </p>
                        </div>

                        <div class="space-y-2">
                            <label class="block text-sm font-semibold text-gray-700">
                                <i class="fas fa-phone mr-2"></i>
                                เบอร์ติดต่อ (Phone Number)
                            </label>
                            <input id="phone_number"
                                placeholder="เบอร์โทรศัพท์"
                                type="tel"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all duration-300 input-focus"
                                name="phone_number"
                                required>
                        </div>
                    </div>
                </div>

                <!-- Work Information Section -->
                <div class="bg-gradient-to-r from-purple-50 to-pink-50 rounded-xl p-6 border-l-4 border-purple-500">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-building mr-3 text-purple-600"></i>
                        ข้อมูลการทำงาน (Work Information)
                    </h3>

                    <div class="grid md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label for="department" class="block text-sm font-semibold text-gray-700">
                                <i class="fas fa-users mr-2"></i>แผนก (Department)
                            </label>
                            <select id="department"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all duration-300 input-focus"
                                name="department"
                                required>
                                <option value="">เลือกแผนก (Select Department)</option>
                                <?php if ($result): ?>
                                    <?php while ($row = $result->fetch(PDO::FETCH_ASSOC)): ?>
                                        <option value="<?php echo htmlspecialchars($row['id']); ?>">
                                            <?php echo htmlspecialchars($row['dept_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="space-y-2">
                            <label for="site" class="block text-sm font-semibold text-gray-700">
                                <i class="fas fa-map-marker-alt mr-2"></i>บริษัท/สาขา (Company / Site)
                            </label>
                            <select id="site"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all duration-300 input-focus"
                                name="site"
                                required>
                                <option value="">เลือกบริษัท (Select Company)</option>
                                <option value="KVA">KVA</option>
                                <option value="KVT">KVT</option>
                                <option value="SPT">SPT</option>
                                <option value="KAG">KAG</option>
                                <option value="KNI">KNI</option>
                                <option value="KSH">KSH</option>
                                <option value="KTJ">KTJ</option>
                                <option value="KPH">KPH</option>
                                <option value="KVM">KVM</option>
                                <option value="KVN">KVN</option>
                                <option value="KJK">KJK</option>
                                <option value="Chay sol">Chay sol</option>
                                <option value="KTW">KTW</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-center pt-6">
                    <button type="submit"
                        onclick="showLoading()"
                        class="bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 text-white font-bold py-4 px-12 rounded-xl shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-300 flex items-center space-x-3">
                        <i class="fas fa-user-plus"></i>
                        <span>สมัครสมาชิก (Register)</span>
                    </button>
                </div>

                <!-- Login Link -->
                <div class="text-center pt-4">
                    <p class="text-gray-600">
                        มีบัญชีอยู่แล้ว? (Already have an account?)
                        <a href="http://eweb.krugerfan.com/" class="text-blue-600 hover:text-blue-800 font-semibold hover:underline transition-colors duration-300">
                            เข้าสู่ระบบที่นี่ (Sign in here)
                        </a>
                    </p>
                </div>
            </form>
        </div>

        <!-- Footer -->
        <div class="text-center mt-8 text-white/80">
            <p>&copy; 2024 Registration System. All rights reserved.</p>
        </div>
    </div>
</body>

</html>