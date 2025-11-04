<?php
session_start();
require 'db.php';
header('Content-Type: application/json');

// Read JSON from fetch()
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$email = trim($data['email'] ?? '');
$name  = trim($data['name'] ?? '');
$uid   = trim($data['uid'] ?? '');
$photo = trim($data['photo'] ?? '');

if (empty($email)) {
    echo json_encode(['status' => 'error', 'message' => 'No email provided']);
    exit;
}

// Check if user already exists
$stmt = $conn->prepare("SELECT ID, FName, LName, Role FROM users WHERE GMail = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    // ✅ EXISTING USER - Log in directly (no OTP needed for Google)
    $_SESSION['user_id'] = $row['ID'];
    $_SESSION['role'] = $row['Role'];
    $_SESSION['FName'] = $row['FName'];
    $_SESSION['LName'] = $row['LName'];
    $_SESSION['name'] = $row['FName'] . ' ' . $row['LName'];

    // Generate remember token
    $token = bin2hex(random_bytes(32));
    $expiry = time() + (30 * 24 * 60 * 60); // 30 days

    // Update DB with token
    $stmt2 = $conn->prepare("UPDATE users SET remember_token = ?, remember_expiry = ? WHERE ID = ?");
    $stmt2->bind_param("sii", $token, $expiry, $row['ID']);
    $stmt2->execute();
    $stmt2->close();

    // Set cookie
    setcookie("remember_token", $token, $expiry, "/", "", false, true);

    // Redirect based on role
    $redirect = ($row['Role'] === 'provider') ? 'provider.php' : 'client.php';
    echo json_encode(['status' => 'success', 'redirect' => $redirect]);
    exit;
    
} else {
    // ✅ NEW USER - Store Google info and redirect to role selection
    // No OTP needed - Google already verified the email!
    $_SESSION['google_user'] = [
        'email' => $email,
        'name'  => $name,
        'uid'   => $uid,
        'photo' => $photo
    ];

    echo json_encode(['status' => 'success', 'redirect' => 'role_select.php']);
    exit;
}
?>