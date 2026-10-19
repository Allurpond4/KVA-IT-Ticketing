<?php
include 'connect.php'; // เชื่อมต่อกับฐานข้อมูล
include 'auth.php'; // ตรวจสอบการล็อกอิน

if (isset($_GET['id'])) {
    $issue_id = $_GET['id'];

    try {
        // ดึงข้อมูลจากฐานข้อมูล
        $sql = "SELECT * FROM repair_requests WHERE issue_id = :issue_id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':issue_id', $issue_id, PDO::PARAM_STR);
        $stmt->execute();
        $request = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($request) {
            echo json_encode($request);
        } else {
            echo json_encode(['error' => 'ไม่พบข้อมูลในฐานข้อมูล']);
        }
    } catch (PDOException $e) {
        echo json_encode(['error' => 'ไม่สามารถดึงข้อมูลได้: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'ไม่พบข้อมูล']);
}

$conn = null;
?>