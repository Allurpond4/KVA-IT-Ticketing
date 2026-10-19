<?php
include 'connect.php';

// รับข้อมูลจาก URL
$issue_id = $_GET['issue_id'] ?? '';
$rating = $_GET['rating'] ?? '';

if ($issue_id && $rating) {
    try {
        // อัปเดตคอลัมน์ rate ในตาราง repair_requests
        $sql = "UPDATE repair_requests SET rate = :rate WHERE issue_id = :issue_id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':rate', $rating, PDO::PARAM_INT);
        $stmt->bindParam(':issue_id', $issue_id, PDO::PARAM_STR);
        if ($stmt->execute()) {
            echo "Thank you for your rating!";
        } else {
            echo "Failed to submit your rating.";
        }
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
} else {
    echo "Invalid request.";
}

$conn = null;
?>