<?php
session_start();
include 'connect.php';
include 'auth.php';

// ตรวจสอบสิทธิ์ admin เท่านั้น
if ($_SESSION['user_type'] !== 'admin') {
    header('Location: index.php');
    exit();
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $asset_tag = strtoupper(trim($_POST['asset_tag']));
    $serial_number = trim($_POST['serial_number']);
    $asset_type = $_POST['asset_type'];
    $brand = $_POST['brand'];
    $model = $_POST['model'];
    $cpu = $_POST['cpu'];
    $ram_gb = !empty($_POST['ram_gb']) ? (int)$_POST['ram_gb'] : null;
    $storage = $_POST['storage'];
    $os = $_POST['os'];
    $purchase_date = $_POST['purchase_date'] ?: null;
    $warranty_end_date = $_POST['warranty_end_date'] ?: null;
    $location = $_POST['location'];
    $notes = $_POST['notes'];
    $pc_name = $_POST['pc_name'];
    // ตรวจสอบ asset_tag ซ้ำ
    try {
        $check = $conn->prepare("SELECT id FROM pc_assets WHERE asset_tag = :asset_tag");
        $check->bindParam(':asset_tag', $asset_tag);
        $check->execute();
        if ($check->rowCount() > 0) {
            $error = "Asset Tag นี้มีในระบบแล้ว";
        } else {
            $sql = "INSERT INTO pc_assets (
                asset_tag, serial_number, asset_type, brand, model, cpu, ram_gb, storage, os,
                purchase_date, warranty_end_date, location, status, notes,pc_name
            ) VALUES (
                :asset_tag, :serial_number, :asset_type, :brand, :model, :cpu, :ram_gb, :storage, :os,
                :purchase_date, :warranty_end_date, :location, 'Available', :notes, :pc_name
            )";

            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':asset_tag', $asset_tag);
            $stmt->bindParam(':serial_number', $serial_number);
            $stmt->bindParam(':asset_type', $asset_type);
            $stmt->bindParam(':brand', $brand);
            $stmt->bindParam(':model', $model);
            $stmt->bindParam(':cpu', $cpu);
            $stmt->bindParam(':ram_gb', $ram_gb, PDO::PARAM_INT);
            $stmt->bindParam(':storage', $storage);
            $stmt->bindParam(':os', $os);
            $stmt->bindParam(':purchase_date', $purchase_date);
            $stmt->bindParam(':warranty_end_date', $warranty_end_date);
            $stmt->bindParam(':location', $location);
            $stmt->bindParam(':notes', $notes);
            $stmt->bindParam(':pc_name', $pc_name);

            if ($stmt->execute()) {
                $success = "เพิ่มอุปกรณ์เรียบร้อยแล้ว (Asset Tag: $asset_tag)";
            } else {
                $error = "เกิดข้อผิดพลาดในการบันทึกข้อมูล";
            }
        }
    } catch (Exception $e) {
        $error = "ข้อผิดพลาดฐานข้อมูล: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เพิ่มอุปกรณ์ IT ใหม่ - Asset Management</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="styles.css" rel="stylesheet">
    <style>
        .header-gradient {
            background: linear-gradient(135deg, #1e40af 0%, #7c3aed 100%);
        }
        .form-container {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            padding: 3px;
        }
        .form-inner {
            background: white;
            border-radius: 17px;
            overflow: hidden;
        }
        .form-section {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #e2e8f0;
        }
        .form-input {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px 16px;
            width: 100%;
        }
        .form-input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            transform: translateY(-2px);
        }
        .btn-primary {
            background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.4);
        }
        .btn-secondary {
            background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(107, 114, 128, 0.3);
        }
        .section-title {
            color: #1f2937;
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        .section-icon {
            margin-right: 10px;
            color: #3b82f6;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <?php include 'nav.php'; ?>
    <div class="container mx-auto px-4 py-8 mt-16">
        <!-- Header Section -->
        <div class="text-center mb-8">
            <div class="header-gradient text-white rounded-xl p-6 shadow-xl">
                <h1 class="text-3xl font-bold mb-2">
                    <i class="fas fa-laptop mr-3"></i>
                    เพิ่มอุปกรณ์ IT ใหม่
                </h1>
                <p class="text-lg opacity-90">Add New IT Asset</p>
                <div class="mt-4 text-sm opacity-80">
                    <i class="fas fa-info-circle mr-2"></i>
                    กรุณากรอกข้อมูลให้ครบถ้วนและถูกต้อง
                </div>
            </div>
        </div>

        <!-- Form Container -->
        <div class="form-container shadow-2xl max-w-5xl mx-auto">
            <div class="form-inner">
                <div class="p-8">
                    <!-- Success Message -->
                    <?php if ($success): ?>
                        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6" role="alert">
                            <span class="block sm:inline"><?php echo $success; ?></span>
                        </div>
                    <?php endif; ?>

                    <!-- Error Message -->
                    <?php if ($error): ?>
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6" role="alert">
                            <span class="block sm:inline"><?php echo $error; ?></span>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <!-- ข้อมูลพื้นฐาน -->
                        <div class="form-section">
                            <h3 class="section-title">
                                <i class="fas fa-info-circle section-icon"></i>
                                ข้อมูลพื้นฐาน
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-gray-700 font-semibold mb-2">
                                        Asset Tag <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="asset_tag" class="form-input" placeholder="เช่น KVA-PC-001" value="<?php echo htmlspecialchars($_POST['asset_tag'] ?? ''); ?>" required>
                                </div>
                                <div>
                                    <label class="block text-gray-700 font-semibold mb-2">Serial Number</label>
                                    <input type="text" name="serial_number" class="form-input" placeholder="เช่น PF123456" value="<?php echo htmlspecialchars($_POST['serial_number'] ?? ''); ?>">
                                </div>
                                <div>
                                    <label class="block text-gray-700 font-semibold mb-2">ประเภทอุปกรณ์</label>
                                    <select name="asset_type" class="form-input" required>
                                        <option value="PC" <?php echo (($_POST['asset_type'] ?? '') == 'PC') ? 'selected' : ''; ?>>Desktop PC</option>
                                        <option value="Laptop" <?php echo (($_POST['asset_type'] ?? '') == 'Laptop') ? 'selected' : ''; ?>>Laptop</option>
                                        <option value="Monitor" <?php echo (($_POST['asset_type'] ?? '') == 'Monitor') ? 'selected' : ''; ?>>Monitor</option>
                                        <option value="Printer" <?php echo (($_POST['asset_type'] ?? '') == 'Printer') ? 'selected' : ''; ?>>Printer</option>
                                        <option value="Software" <?php echo (($_POST['asset_type'] ?? '') == 'Software') ? 'selected' : ''; ?>>Software</option>
                                        <option value="Other" <?php echo (($_POST['asset_type'] ?? '') == 'Other') ? 'selected' : ''; ?>>อื่น ๆ</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-gray-700 font-semibold mb-2">ยี่ห้อ / Brand</label>
                                    <input type="text" name="brand" class="form-input" placeholder="เช่น Dell, HP, Lenovo" value="<?php echo htmlspecialchars($_POST['brand'] ?? ''); ?>">
                                </div>
                                <div>
                                    <label class="block text-gray-700 font-semibold mb-2">รุ่น / Model</label>
                                    <input type="text" name="model" class="form-input" placeholder="เช่น Latitude 7440" value="<?php echo htmlspecialchars($_POST['model'] ?? ''); ?>">
                                </div>
                                <div>
                                    <label class="block text-gray-700 font-semibold mb-2">CPU</label>
                                    <input type="text" name="cpu" class="form-input" placeholder="เช่น Intel i7-1365U" value="<?php echo htmlspecialchars($_POST['cpu'] ?? ''); ?>">
                                </div>
                                <div>
                                    <label class="block text-gray-700 font-semibold mb-2">RAM (GB)</label>
                                    <input type="number" name="ram_gb" class="form-input" placeholder="เช่น 32" value="<?php echo $_POST['ram_gb'] ?? ''; ?>">
                                </div>
                                <div>
                                    <label class="block text-gray-700 font-semibold mb-2">Storage</label>
                                    <input type="text" name="storage" class="form-input" placeholder="เช่น 1TB SSD" value="<?php echo htmlspecialchars($_POST['storage'] ?? ''); ?>">
                                </div>
                                <div>
                                    <label class="block text-gray-700 font-semibold mb-2">ระบบปฏิบัติการ / OS</label>
                                    <input type="text" name="os" class="form-input" placeholder="เช่น Windows 11 Pro" value="<?php echo htmlspecialchars($_POST['os'] ?? ''); ?>">
                                </div>
                                <div>
                                    <label class="block text-gray-700 font-semibold mb-2">สถานที่ / Location</label>
                                    <input type="text" name="location" class="form-input" placeholder="เช่น KVA, Bangkok Office" value="<?php echo htmlspecialchars($_POST['location'] ?? 'KVA'); ?>">
                                </div>
                                <div>
                                    <label class="block text-gray-700 font-semibold mb-2">วันที่ซื้อ</label>
                                    <input type="date" name="purchase_date" class="form-input" value="<?php echo $_POST['purchase_date'] ?? ''; ?>">
                                </div>
                                <div>
                                    <label class="block text-gray-700 font-semibold mb-2">ชื่อคอมพิวเตอร์</label>
                                    <input type="text" name="pc_name" class="form-input" value="<?php echo $_POST['pc_name'] ?? ''; ?>">
                                </div>
                                <div>
                                    <label class="block text-gray-700 font-semibold mb-2">วันหมดประกัน</label>
                                    <input type="date" name="warranty_end_date" class="form-input" value="<?php echo $_POST['warranty_end_date'] ?? ''; ?>">
                                </div>
                               
                            </div>
                        </div>

                        <!-- หมายเหตุ -->
                        <div class="form-section">
                            <h3 class="section-title">
                                <i class="fas fa-sticky-note section-icon"></i>
                                หมายเหตุเพิ่มเติม
                            </h3>
                            <textarea name="notes" rows="5" class="form-input" placeholder="เช่น ได้รับจากผู้ขายเมื่อ... หรือหมายเหตุพิเศษ"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                        </div>

                        <!-- ปุ่ม -->
                        <div class="flex flex-col sm:flex-row justify-end gap-4 mt-8">
                            <button type="submit" class="btn-primary text-white font-bold py-3 px-8 rounded-xl">
                                <i class="fas fa-save mr-2"></i>บันทึกอุปกรณ์ใหม่
                            </button>
                            <a href="user_list.php" class="btn-secondary text-white font-bold py-3 px-8 rounded-xl text-center">
                                <i class="fas fa-times mr-2"></i>ยกเลิก
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // ยืนยันก่อนบันทึก
        document.querySelector('form').addEventListener('submit', function(e) {
            e.preventDefault();
            Swal.fire({
                title: 'ยืนยันการเพิ่มอุปกรณ์ใหม่?',
                text: "กรุณาตรวจสอบข้อมูลให้ถูกต้องก่อนบันทึก",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3b82f6',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'ใช่, บันทึก!',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    this.submit();
                }
            });
        });
    </script>
</body>
</html>