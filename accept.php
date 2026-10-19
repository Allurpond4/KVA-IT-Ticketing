<?php
session_start();
date_default_timezone_set('Asia/Bangkok');
header('Content-Type: application/json; charset=utf-8');

include 'auth.php';
include 'connect.php';

function getPriorityDays($priority)
{
    switch (strtolower(trim($priority))) {
        case 'critical': return 1;
        case 'high':     return 3;
        case 'normal':   return 5;
        case 'low':      return 10;
        default:         return 7;
    }
}

function addBusinessDays($date, $days)
{
    $date = new DateTime($date);
    while ($days > 0) {
        $date->modify('+1 day');
        if ($date->format('N') < 6) {
            $days--;
        }
    }
    return $date->format('Y-m-d H:i:s');
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    echo json_encode(['success' => false, 'error' => 'Invalid JSON'], JSON_UNESCAPED_UNICODE);
    exit;
}

$issue_id       = trim($data['issue_id'] ?? '');
$completion_date = $data['completion_date'] ?? null;
$it_priority    = strtolower(trim($data['it_priority'] ?? ''));
$user_id        = $_SESSION['user_id'] ?? '';

$allowed_priorities = ['critical', 'high', 'normal', 'low'];

if (!$issue_id || !$user_id) {
    echo json_encode([
        'success' => false,
        'error' => 'ไม่มีข้อมูล issue_id หรือ user_id'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!in_array($it_priority, $allowed_priorities, true)) {
    echo json_encode([
        'success' => false,
        'error' => 'กรุณาระบุ IT Priority: critical, high, normal หรือ low'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $user_sql = "
        SELECT CONCAT(first_name, ' ', last_name) AS full_name
        FROM users
        WHERE id = :user_id
    ";
    $user_stmt = $conn->prepare($user_sql);
    $user_stmt->execute([':user_id' => $user_id]);
    $full_name = $user_stmt->fetchColumn();

    if (!$full_name) {
        echo json_encode(['success' => false, 'error' => 'ไม่พบข้อมูลผู้ใช้'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $ticket_sql = "
        SELECT priority, it_priority, status
        FROM repair_requests
        WHERE issue_id = :issue_id
    ";
    $ticket_stmt = $conn->prepare($ticket_sql);
    $ticket_stmt->execute([':issue_id' => $issue_id]);
    $row = $ticket_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['success' => false, 'error' => 'ไม่พบ Issue นี้'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($row['status'] !== 'Pending') {
        echo json_encode(['success' => false, 'error' => 'งานนี้ถูกดำเนินการไปแล้ว'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $accept_date = date('Y-m-d H:i:s');
    $days = getPriorityDays($it_priority);

    if ($completion_date) {
        $completion_date_obj = DateTime::createFromFormat('Y-m-d\TH:i', $completion_date);
        $date_errors = DateTime::getLastErrors();

        if (!$completion_date_obj || ($date_errors !== false && ($date_errors['warning_count'] > 0 || $date_errors['error_count'] > 0))) {
            echo json_encode(['success' => false, 'error' => 'รูปแบบวันที่ไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $plan_date = $completion_date_obj->format('Y-m-d H:i:s');
    } else {
        $plan_date = addBusinessDays($accept_date, $days);
    }

    $status = 'In Progress';

    $sql = "
        UPDATE repair_requests
        SET accept_date = :accept_date,
            status = :status,
            it_accept = :it_accept,
            operator = :operator,
            plan_date = :plan_date,
            it_priority = :it_priority,
            updated_at = GETDATE()
        WHERE issue_id = :issue_id
          AND status = 'Pending'
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':accept_date' => $accept_date,
        ':status' => $status,
        ':it_accept' => $full_name,
        ':operator' => $full_name,
        ':plan_date' => $plan_date,
        ':it_priority' => $it_priority,
        ':issue_id' => $issue_id
    ]);

    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'plan_date' => $plan_date,
            'priority' => $row['priority'],
            'it_priority' => $it_priority
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['success' => false, 'error' => 'ไม่สามารถอัปเดตข้อมูลได้'], JSON_UNESCAPED_UNICODE);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

$conn = null;
?>
