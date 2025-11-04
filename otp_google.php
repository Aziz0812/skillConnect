<?php
session_start();
require 'db.php';

if (!isset($_SESSION['pending_google_user'])) {
    header("Location: login.php");
    exit;
}

$email = $_SESSION['pending_google_user']['email'];
$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $otp = trim($_POST['otp']);
    $user_id = $_SESSION['pending_google_user']['user_id'];

    $stmt = $conn->prepare("SELECT otp_code, otp_expires FROM users WHERE ID = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if ($otp === $row['otp_code'] && time() < $row['otp_expires']) {
        // OTP OK → FULL ACCOUNT
        $stmt = $conn->prepare("UPDATE users SET otp_code=NULL, otp_expires=NULL WHERE ID=?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();

        $_SESSION['user_id'] = $user_id;
        $_SESSION['role'] = 'client'; // will be updated in role_select
        unset($_SESSION['pending_google_user']);

        header("Location: role_select.php");
        exit;
    } else {
        $message = "Wrong or expired OTP!";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Verify OTP</title>
    <meta http-equiv="Cross-Origin-Opener-Policy" content="same-origin-allow-popups">
    <style>
        body { font-family: Arial; text-align: center; margin-top: 100px; }
        input { font-size: 30px; width: 200px; text-align: center; letter-spacing: 10px; }
        button { padding: 15px 30px; font-size: 18px; background: #4285f4; color: white; border: none; border-radius: 8px; cursor: pointer; }
        .resend { margin-top: 20px; color: #1a73e8; }
    </style>
</head>
<body>
    <h2>Almost there!</h2>
    <p>We sent a 6-digit code to<br><b><?= htmlspecialchars($email) ?></b></p>
    <?php if ($message): ?><p style="color:red"><?= $message ?></p><?php endif; ?>

    <form method="POST">
        <input name="otp" maxlength="6" required placeholder="000000"><br><br>
        <button type="submit">Verify & Continue</button>
    </form>

    <div class="resend">
        <a href="send_otp.php?resend=<?= urlencode($email) ?>">Resend OTP</a>
    </div>
</body>
</html>