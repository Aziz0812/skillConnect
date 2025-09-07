<?php
<?php
session_start();
require "db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'provider') {
    header("Location: login.php");
    exit();
}

$provider_id = $_SESSION['user_id'];
$request_id = intval($_POST['request_id'] ?? 0);
$action = $_POST['action'] ?? '';

if ($action === 'confirm') {
    // Confirm the request
    $stmt = $conn->prepare("UPDATE request SET Status='Confirmed', ConfirmedAt=NOW() WHERE RequestID=? AND ProviderID=? AND Status='Pending'");
    $stmt->bind_param("ii", $request_id, $provider_id);
    $stmt->execute();
} elseif ($action === 'reschedule' && !empty($_POST['new_schedule'])) {
    // Suggest a new schedule (keep status pending)
    $new_schedule = $_POST['new_schedule'];
    $stmt = $conn->prepare("UPDATE request SET Schedule=? WHERE RequestID=? AND ProviderID=? AND Status='Pending'");
    $stmt->bind_param("sii", $new_schedule, $request_id, $provider_id);
    $stmt->execute();
}

header("Location: provider.php");
exit();