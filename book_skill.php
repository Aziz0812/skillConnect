<?php
session_start();
require 'db.php';
header('Content-Type: application/json');

$client = $_SESSION['user_id'] ?? null;
$skill  = intval($_POST['skill_id'] ?? 0);
$schedule = trim($_POST['preferred_schedule'] ?? '');

// Validate user is logged in
if (!$client || $_SESSION['role'] !== 'client') {
    echo json_encode(['success' => false, 'message' => 'Please log in to book services.']);
    exit;
}

// Validate inputs
if (!$skill || empty($schedule)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request data.']);
    exit;
}

// Validate schedule format and future date
$scheduleTime = strtotime($schedule);
if ($scheduleTime === false || $scheduleTime < time()) {
    echo json_encode(['success' => false, 'message' => 'Invalid or past date selected.']);
    exit;
}

// Check for existing active bookings for same skill
$stmt = $conn->prepare("
    SELECT COUNT(*) AS cnt
    FROM request
    WHERE ClientID = ? AND SkillID = ? AND Status IN ('Pending','Confirmed','In Progress')
");
$stmt->bind_param('ii', $client, $skill);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
if ($res['cnt'] > 0) {
    echo json_encode([
        'success' => false,
        'error' => 'already_booked',
        'message' => 'You already have an active booking for this service.'
    ]);
    exit;
}

// Find provider for skill
$stmt = $conn->prepare("SELECT UserID FROM skills WHERE SkillID = ? AND IsActive = 1 LIMIT 1");
$stmt->bind_param('i', $skill);
$stmt->execute();
$s = $stmt->get_result()->fetch_assoc();
if (!$s) {
    echo json_encode(['success'=>false,'message'=>'Service not found or no longer available.']);
    exit;
}
$provider = $s['UserID'];

// Insert request with schedule
$stmt = $conn->prepare("
    INSERT INTO request (ClientID, ProviderID, SkillID, Status, Schedule, CreatedAt)
    VALUES (?, ?, ?, 'Pending', ?, NOW())
");
$stmt->bind_param('iiis', $client, $provider, $skill, $schedule);
$ok = $stmt->execute();

echo json_encode([
    'success' => (bool)$ok,
    'message' => $ok ? 'Booking successful!' : 'Failed to create booking.'
]);
?>