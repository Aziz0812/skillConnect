<?php
session_start();
require "db.php";
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$client_id = $_SESSION['user_id'];
$request_id = intval($_POST['request_id'] ?? 0);

$stmt = $conn->prepare("SELECT Status, ConfirmedAt FROM request WHERE RequestID=? AND ClientID=?");
$stmt->bind_param("ii", $request_id, $client_id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();

$can_cancel = false;
if ($row) {
    if (strtolower($row['Status']) === 'pending') {
        $can_cancel = true;
    } elseif (strtolower($row['Status']) === 'confirmed' && !empty($row['ConfirmedAt'])) {
        $confirmed_time = strtotime($row['ConfirmedAt']);
        if ((time() - $confirmed_time) <= 86400) { // 24 hours
            $can_cancel = true;
        }
    }
}

if ($can_cancel) {
    $stmt = $conn->prepare("UPDATE request SET Status='Cancelled' WHERE RequestID=?");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Cancellation not allowed.']);
}
exit();