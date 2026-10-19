<?php
session_start();
date_default_timezone_set('Asia/Bangkok');

include 'connect.php';

function getPriorityDays($priority)
{
    switch (strtolower($priority)) {
        case 'critical': return 1;
        case 'high': return 3;
        case 'normal': return 7;
        case 'low': return 14;
        default: return 7;
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

    return $date->format('Y-m-d\TH:i');
}

$data = json_decode(file_get_contents('php://input'), true);

$issue_id = $data['issue_id'];

$sql = "SELECT priority FROM repair_requests WHERE issue_id = :issue_id";
$stmt = $conn->prepare($sql);
$stmt->execute([':issue_id'=>$issue_id]);

$priority = $stmt->fetchColumn();

$days = getPriorityDays($priority);

$plan_date = addBusinessDays(date('Y-m-d H:i:s'), $days);

echo json_encode([
    'plan_date'=>$plan_date,
    'priority'=>$priority
]);