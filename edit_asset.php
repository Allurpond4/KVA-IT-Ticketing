<?php
session_start();
include 'connect.php';
include 'auth.php';

// admin only
if ($_SESSION['user_type'] !== 'admin') {
    header('Location: index.php');
    exit();
}

// ---------------- GET ID ----------------
if (!isset($_GET['id'])) {
    die("Missing ID");
}

$id = (int)$_GET['id'];

$success = '';
$error = '';

// ---------------- FETCH DATA ----------------
$stmt = $conn->prepare("SELECT * FROM pc_assets WHERE id = :id");
$stmt->execute([':id' => $id]);
$asset = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$asset) {
    die("Asset not found");
}

// ---------------- UPDATE ----------------
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

    try {

        // check duplicate (ยกเว้นตัวเอง)
        $check = $conn->prepare("SELECT id FROM pc_assets WHERE asset_tag = :asset_tag AND id != :id");
        $check->execute([
            ':asset_tag' => $asset_tag,
            ':id' => $id
        ]);

        if ($check->rowCount() > 0) {
            $error = "Asset Tag นี้มีในระบบแล้ว";
        } else {

            $sql = "UPDATE pc_assets SET
                asset_tag = :asset_tag,
                serial_number = :serial_number,
                asset_type = :asset_type,
                brand = :brand,
                model = :model,
                cpu = :cpu,
                ram_gb = :ram_gb,
                storage = :storage,
                os = :os,
                purchase_date = :purchase_date,
                warranty_end_date = :warranty_end_date,
                location = :location,
                notes = :notes,
                pc_name =:pc_name,
                updated_at = GETDATE()
            WHERE id = :id";

            $stmt = $conn->prepare($sql);

            $stmt->execute([
                ':asset_tag' => $asset_tag,
                ':serial_number' => $serial_number,
                ':asset_type' => $asset_type,
                ':brand' => $brand,
                ':model' => $model,
                ':cpu' => $cpu,
                ':ram_gb' => $ram_gb,
                ':storage' => $storage,
                ':os' => $os,
                ':purchase_date' => $purchase_date,
                ':warranty_end_date' => $warranty_end_date,
                ':location' => $location,
                ':notes' => $notes,
                ':pc_name' => $pc_name,
                ':id' => $id
            ]);

            header("Location: asset_list.php");
            exit();
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>แก้ไข Asset</title>

    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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
            padding: 20px;
        }

        .form-section {
            background: #f8fafc;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .form-input {
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px;
            width: 100%;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
        }
    </style>

</head>

<body class="bg-gray-50">

    <?php include 'nav.php'; ?>

    <div class="container mx-auto px-4 py-8 max-w-5xl">

        <!-- HEADER -->
        <div class="text-center mb-8">
            <div class="header-gradient text-white rounded-xl p-6 shadow-xl">
                <h1 class="text-3xl font-bold">
                    ✏️ แก้ไข Asset
                </h1>
                <p class="opacity-80">Edit Asset Information</p>
            </div>
        </div>

        <div class="form-container shadow-2xl">
            <div class="form-inner">

                <!-- ERROR -->
                <?php if ($error): ?>
                    <div class="bg-red-100 text-red-700 p-3 mb-4 rounded">
                        <?= $error ?>
                    </div>
                <?php endif; ?>

                <form method="POST">

                    <div class="form-section">
                        <h3 class="font-bold mb-4">ข้อมูลพื้นฐาน</h3>

                        <div class="grid md:grid-cols-2 gap-4">

                            <!-- Asset Tag -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Asset Tag <span class="text-red-500">*</span></label>
                                <input type="text" name="asset_tag"
                                    value="<?= htmlspecialchars($asset['asset_tag'] ?? '') ?>"
                                    class="form-input" required placeholder="Asset Tag">
                            </div>

                            <!-- Serial Number -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Serial Number</label>
                                <input type="text" name="serial_number"
                                    value="<?= htmlspecialchars($asset['serial_number'] ?? '') ?>"
                                    class="form-input" placeholder="Serial">
                            </div>

                            <!-- Asset Type -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">ประเภททรัพย์สิน</label>
                                <select name="asset_type" class="form-input">
                                    <option value="PC" <?= ($asset['asset_type'] ?? '') == 'PC' ? 'selected' : '' ?>>PC</option>
                                    <option value="Laptop" <?= ($asset['asset_type'] ?? '') == 'Laptop' ? 'selected' : '' ?>>Laptop</option>
                                </select>
                            </div>

                            <!-- Brand -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">ยี่ห้อ (Brand)</label>
                                <input type="text" name="brand"
                                    value="<?= htmlspecialchars($asset['brand'] ?? '') ?>"
                                    class="form-input" placeholder="Brand">
                            </div>

                            <!-- Model -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">รุ่น (Model)</label>
                                <input type="text" name="model"
                                    value="<?= htmlspecialchars($asset['model'] ?? '') ?>"
                                    class="form-input" placeholder="Model">
                            </div>

                            <!-- CPU -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">CPU</label>
                                <input type="text" name="cpu"
                                    value="<?= htmlspecialchars($asset['cpu'] ?? '') ?>"
                                    class="form-input" placeholder="CPU">
                            </div>

                            <!-- RAM -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">RAM (GB)</label>
                                <input type="number" name="ram_gb"
                                    value="<?= htmlspecialchars($asset['ram_gb'] ?? '') ?>"
                                    class="form-input" placeholder="RAM (GB)">
                            </div>

                            <!-- Storage -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">พื้นที่เก็บข้อมูล</label>
                                <input type="text" name="storage"
                                    value="<?= htmlspecialchars($asset['storage'] ?? '') ?>"
                                    class="form-input" placeholder="Storage">
                            </div>

                            <!-- OS -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">ระบบปฏิบัติการ (OS)</label>
                                <input type="text" name="os"
                                    value="<?= htmlspecialchars($asset['os'] ?? '') ?>"
                                    class="form-input" placeholder="OS">
                            </div>
                            <div>
                                <label class="block text-gray-700 font-semibold mb-2">ชื่อคอมพิวเตอร์</label>
                                <input type="text" name="pc_name" class="form-input" value="<?php echo $_POST['pc_name'] ?? ''; ?>">
                            </div>
                            <!-- Purchase Date -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">วันที่ซื้อ</label>
                                <input type="date" name="purchase_date"
                                    value="<?= $asset['purchase_date'] ?? '' ?>"
                                    class="form-input">
                            </div>

                            <!-- Warranty End Date -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">วันที่หมดประกัน</label>
                                <input type="date" name="warranty_end_date"
                                    value="<?= $asset['warranty_end_date'] ?? '' ?>"
                                    class="form-input">
                            </div>

                            <!-- Location -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">สถานที่ตั้ง</label>
                                <input type="text" name="location"
                                    value="<?= htmlspecialchars($asset['location'] ?? '') ?>"
                                    class="form-input" placeholder="Location">
                            </div>

                        </div>
                    </div>

                    <div class="form-section">
                        <h3 class="font-bold mb-4">หมายเหตุ</h3>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">หมายเหตุ / ข้อมูลเพิ่มเติม</label>
                            <textarea name="notes" class="form-input" rows="4"><?= htmlspecialchars($asset['notes'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3">
                        <a href="asset_list.php" class="bg-gray-500 text-white px-4 py-2 rounded">
                            ยกเลิก
                        </a>

                        <button type="submit" class="btn-primary text-white px-6 py-2 rounded">
                            💾 บันทึก
                        </button>
                    </div>

                </form>

            </div>
        </div>

    </div>

    <script>
        document.querySelector('form').addEventListener('submit', function(e) {
            e.preventDefault();
            Swal.fire({
                title: 'บันทึกการแก้ไข?',
                icon: 'question',
                showCancelButton: true
            }).then((r) => {
                if (r.isConfirmed) this.submit();
            });
        });
    </script>

</body>

</html>