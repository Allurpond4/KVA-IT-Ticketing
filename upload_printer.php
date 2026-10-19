<?php
include 'nav.php';

$serverName = "10.248.200.32";
            $connectionOptions = array(
                "Database" => "kva_it", // open
                "Uid" => "sa",
                "PWD" => "[K]=30159&",
				"CharacterSet" => "UTF-8",
                "Encrypt" => "yes",
                "TrustServerCertificate" => "true"
            );

            // สร้างการเชื่อมต่อ
            $conn = sqlsrv_connect($serverName, $connectionOptions);
            if ($conn === false) {
                //die(print_r(sqlsrv_errors(), true));
				die(print_r("DB is closing.", true));
            }
			
// เปิด error reporting สำหรับ dev
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ---------------------------
// Helpers
// ---------------------------
function castValue($val) {
    $val = trim((string)$val);
    if ($val === '') return null;

    // วันที่ ISO
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) return $val;
    // DD/MM/YYYY → YYYY-MM-DD
    if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $val)) {
        [$d, $m, $y] = explode('/', $val);
        if (checkdate((int)$m, (int)$d, (int)$y)) {
            return sprintf('%04d-%02d-%02d', $y, $m, $d);
        }
    }
    // int
    if (preg_match('/^-?\d+$/', $val)) return (int)$val;
    // float (รองรับ , และ .)
    if (preg_match('/^-?\d+[.,]\d+$/', $val)) return (float)str_replace(',', '.', $val);
    // boolean
    $lower = strtolower($val);
    if (in_array($lower, ['true','false','yes','no'], true)) {
        return in_array($lower, ['true','yes'], true);
    }
    return $val;
}

// ---------------------------
// POST handler → อ่าน CSV ลงตัวแปร $Result
// ---------------------------
$Result = [];         // Array 2 มิติ: แต่ละแถวเป็น associative ตาม header
$Headers = [];        // เก็บ header เพื่อใช้สร้างตาราง
$DelimiterUsed = ','; // debug: delimiter ที่ใช้
$ErrorMsg = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
	
    try {
        if (!isset($_FILES['csv_file'])) {
            throw new RuntimeException('ไม่มีไฟล์ที่อัปโหลด');
        }
        $file = $_FILES['csv_file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('อัปโหลดไฟล์ไม่สำเร็จ (error: '.$file['error'].')');
        }

        // ตรวจนามสกุล
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            throw new RuntimeException('รองรับเฉพาะไฟล์ .csv เท่านั้น');
        }

        // ย้ายไฟล์ไปโฟลเดอร์ชั่วคราว
        $uploadDir = __DIR__ . '/uploads';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
        $destPath = $uploadDir . '/' . uniqid('csv_', true) . '.csv';
        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            throw new RuntimeException('ย้ายไฟล์ไม่สำเร็จ');
        }

        // เปิดไฟล์
        $handle = fopen($destPath, 'r');
        if (!$handle) throw new RuntimeException('เปิดไฟล์ไม่สำเร็จ');

        // อ่านบรรทัดแรกเพื่อเดลิมิเตอร์ + ตัด BOM
        $firstLine = fgets($handle);
        if ($firstLine === false) throw new RuntimeException('ไฟล์ว่างหรืออ่านไม่ได้');
        $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine);

        // auto-detect delimiter
        $DelimiterUsed = ',';
        $candidates = [',', ';', "\t", '|'];
        $scores = [];
        foreach ($candidates as $d) $scores[$d] = substr_count($firstLine, $d);
        arsort($scores);
        $top = array_key_first($scores);
        if ($scores[$top] > 0) $DelimiterUsed = $top;

        // เริ่มอ่านใหม่
        rewind($handle);

        // ใช้แถวแรกเป็น header
        $headers = null;
        while (($row = fgetcsv($handle, 0, $DelimiterUsed)) !== false) {
            // ข้ามแถวว่าง
            $notEmpty = array_filter($row, fn($v) => $v !== null && trim((string)$v) !== '');
            if (count($notEmpty) === 0) continue;

            if ($headers === null) {
                // กำหนด header จากแถวแรก
                $headers = array_map(function($h) {
                    $h = preg_replace('/^\xEF\xBB\xBF/', '', (string)$h);
                    $h = trim($h);
                    return $h !== '' ? $h : null;
                }, $row);
                // เติม header ว่างด้วยชื่อคอลัมน์
                foreach ($headers as $i => $h) {
                    if ($h === null) $headers[$i] = 'Column_' . ($i + 1);
                }
                $Headers = $headers; // เก็บสำหรับแสดงตาราง
                continue;
            }

            // สร้าง record ตาม header + cast type
            $item = [];
            foreach ($row as $i => $val) {
                $key = $headers[$i] ?? ('Column_' . ($i + 1));
                $item[$key] = castValue($val);
            }
            $Result[] = $item;
        }
        fclose($handle);

        // ลบไฟล์ชั่วคราว (เปิดใช้จริงใน production)
        // @unlink($destPath);

    } catch (Throwable $e) {
        $ErrorMsg = $e->getMessage();
        $Result = [];
        $Headers = [];
    }
}
?>			
			
			
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>CSV Upload Printer Log</title>

<style>
/* ===== Base ===== */
* {
    box-sizing: border-box;
    font-family: "Segoe UI", Tahoma, sans-serif;
}

body {
    background: #f4f6f8;
    margin: 0;
    padding: 40px 0;
}

/* ===== Center Container ===== */
.container {
    max-width: 1200px;
    margin: auto;
    padding: 0 20px;
}

/* ===== Card ===== */
.card {
    background: #fff;
    border-radius: 10px;
    padding: 25px;
    margin-bottom: 25px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

/* ===== Meta ===== */
.meta {
    font-size: 15px;
    color: #555;
    margin-bottom: 20px;
}

/* ===== Form ===== */
.upload-form {
    max-width: 500px;
    margin: auto;
}

.form-group {
    margin-bottom: 15px;
}

label {
    font-weight: 600;
    display: block;
    margin-bottom: 6px;
}

input[type="text"],
input[type="date"],
input[type="file"] {
    width: 100%;
    padding: 8px 10px;
    border: 1px solid #ccc;
    border-radius: 6px;
}

.center {
    text-align: center;
}

button {
    background: #0078d4;
    color: #fff;
    border: none;
    padding: 10px 30px;
    border-radius: 6px;
    font-size: 15px;
    cursor: pointer;
}

button:hover {
    background: #005ea6;
}

/* ===== Alert ===== */
.alert {
    padding: 15px 20px;
    border-radius: 6px;
    margin-bottom: 20px;
}

.alert.success {
    background: #e6f4ea;
    color: #1e7e34;
    border-left: 5px solid #28a745;
}

.alert.error {
    background: #fdecea;
    color: #b02a37;
    border-left: 5px solid #dc3545;
}

/* ===== Table ===== */
.table-wrap {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

caption {
    font-weight: bold;
    margin-bottom: 10px;
}

thead {
    background: #0078d4;
    color: #fff;
}

th, td {
    padding: 8px 10px;
    border: 1px solid #ddd;
    text-align: center;
}

tbody tr:nth-child(even) {
    background: #f9f9f9;
}

tbody tr:hover {
    background: #eef5ff;
}
</style>
</head>

<body>

<div class="container">

    <!-- ===== Upload Form ===== -->
    <div class="card">
        <p class="meta">
            Suggest Excel is <strong>CSV UTF-8</strong> For support Thai Language.
        </p>

        <form method="post" enctype="multipart/form-data" class="upload-form">
            <div class="form-group">
                <label>IP Address</label>
                <input type="text" name="IPAddress" required>
            </div>

            <div class="form-group">
                <label>Date</label>
                <input type="date" name="Date" required>
            </div>

            <div class="form-group">
                <label>Choose CSV File</label>
                <input type="file" name="csv_file" accept=".csv" required>
            </div>

            <div class="form-group center">
                <button type="submit">Upload Data</button>
            </div>
        </form>
    </div>

    <!-- ===== Error ===== -->
    <?php if ($ErrorMsg): ?>
        <div class="alert error">
            <strong>Found Problem:</strong>
            <?php echo htmlspecialchars($ErrorMsg); ?>
        </div>
    <?php endif; ?>
	
	

    <!-- ===== Result ===== -->
    <?php if (!empty($Headers)): 
	
		$IPAddress = $_POST['IPAddress'];
		$Date = $_POST['Date'];
	?>
		
        <div class="alert success">
            Read Data Successful —
            Delimiter: <strong><?php echo htmlspecialchars($DelimiterUsed); ?></strong>,
            Rows: <strong><?php echo count($Result); ?></strong>
        </div>

        <div class="card table-wrap">
            <table>
                <caption>Upload Result</caption>
                <thead>
                    <tr>
                        <th>Dept. ID</th>
                        <th>User Name</th>
                        <th>Total Prints</th>
                        <th>Color Total</th>
                        <th>Black & White Total</th>
                        <th>Color Copy</th>
                        <th>Color Scan</th>
                        <th>Color Print</th>
                        <th>BW Copy</th>
                        <th>BW Scan</th>
                        <th>BW Print</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($Result as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['Dept. ID'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['User Name'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['Total Prints'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['Color Total'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['Black & White Total'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['Color Copy'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['Color Scan'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['Color Print'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['Black & White Copy'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['Black & White Scan'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['Black & White Print'] ?? '') ?></td>
                        <td>
						<?php 
							$updatesertSQL = "INSERT INTO printer_log (Date, IPAddress, DeptID, UserName, TotalPrints, ColorTotal, BlackWhiteTotal, ColorCopy, ColorScan, ColorPrint, BlackWhiteCopy, BlackWhiteScan, BlackWhitePrint) 
												VALUES ('".$Date."' , '".$IPAddress."'
														, '".$row['Dept. ID']."' 
														, '".$row['User Name']."'
														, ".$row['Total Prints']."
														, ".$row['Color Total']."
														, ".$row['Black & White Total']."
														, ".$row['Color Copy']."
														, ".$row['Color Scan']."
														, ".$row['Color Print']."
														, ".$row['Black & White Copy']."
														, ".$row['Black & White Scan']."
														, ".$row['Black & White Print']."
														)";


							
							//Update coitem_mst set Uf_DelayReasonCode = '".$row['delayreasoncode']."' where co_num = '".$row['co_num']."' and co_line = ".$row['co_line'];
							$stmt = sqlsrv_query($conn, $updatesertSQL);
							
							
							if ($stmt === false) {
								// กรณีเกิด error ตอน execute
								echo "Error in executing query.\n";
								// แสดงรายละเอียด error จาก sqlsrv
								die(print_r(sqlsrv_errors(), true));
							}

							// ตรวจจำนวนแถวที่ได้รับผลกระทบ
							$rowsAffected = sqlsrv_rows_affected($stmt);

							if ($rowsAffected === false) {
								// กรณีไม่สามารถอ่านจำนวนแถวที่ได้รับผลกระทบ (มักเกิดเฉพาะบางชนิด statement)
								echo "Error: cannot determine rows affected.\n";
								die(print_r(sqlsrv_errors(), true));
							} elseif ($rowsAffected === -1) {
								// สำหรับบางสถานการณ์ SQL Server อาจส่ง -1 (เช่น statement ที่ไม่ได้แก้ไขข้อมูลจริง)
								// ในงาน UPDATE ปกติควรได้ 0 หรือจำนวนจริง
								echo "(-1 rows affected)"; // หรือจะแสดงข้อความกำกับเอง เช่น "unknown rows affected"
							} else {
								// ปกติจะได้ 0, 1, 2, ...
								echo "({$rowsAffected} row" . ($rowsAffected === 1 ? "" : "s") . " affected)";
							}

							// เคลียร์ statement และปิดการเชื่อมต่อ (ถ้าต้องปิดตรงนี้)
							sqlsrv_free_stmt($stmt);
						  ?>
					  </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</div>

</body>
</html>

<?php sqlsrv_close($conn); ?>		
			
			
