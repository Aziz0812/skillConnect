<?php
session_start();
require 'db.php';

// ✅ Clear "remember me" cookie (both in browser and database)
if (isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];

    // Remove token from database (optional but good for security)
    $stmt = $conn->prepare("UPDATE users SET remember_token = NULL, remember_expiry = NULL WHERE remember_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $stmt->close();

    // Expire the cookie
    setcookie('remember_token', '', time() - 3600, '/', '', false, true);
}

// ✅ Destroy session completely
session_unset();
session_destroy();

// ✅ Redirect back to homepage (index)
header("Location: index.php");
exit();
?>
