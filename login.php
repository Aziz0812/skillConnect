<?php
session_start();
require 'db.php';

// AUTO-LOGIN USING COOKIE
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    $stmt = $conn->prepare("SELECT ID, Role, FName, LName, remember_token, remember_expiry 
                            FROM users WHERE remember_token = ? LIMIT 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if ($row['remember_expiry'] > time()) {
            $_SESSION['user_id'] = $row['ID'];
            $_SESSION['role'] = $row['Role'];
            $_SESSION['FName'] = $row['FName'];
            $_SESSION['LName'] = $row['LName'];
            $_SESSION['name'] = $row['FName'] . ' ' . $row['LName'];
            header("Location: " . ($row['Role'] === 'provider' ? 'provider.php' : 'client.php'));
            exit;
        } else {
            setcookie("remember_token", "", time() - 3600, "/");
            $clear = $conn->prepare("UPDATE users SET remember_token = NULL, remember_expiry = NULL WHERE ID = ?");
            $clear->bind_param("i", $row['ID']);
            $clear->execute();
        }
    }
    $stmt->close();
}

$message = "";

// ===============  SIMPLE LOGIN - NO OTP ===============
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = !empty($_POST['remember']);

    if (empty($email) || empty($password)) {
        $message = "Please fill in all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email format.";
    } else {
        $stmt = $conn->prepare("SELECT ID, Password, Role, FName, LName, AccountStatus FROM users WHERE GMail = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

       if ($row = $result->fetch_assoc()) {
        // ✅ CHECK ACCOUNT STATUS BEFORE PASSWORD
            if (isset($row['AccountStatus']) && $row['AccountStatus'] === 'suspended') {
                $message = "⚠️ Your account has been suspended. Please contact <strong>admin@skillconnect.com</strong> for reactivation.";
            } elseif (isset($row['AccountStatus']) && $row['AccountStatus'] === 'banned') {
                $message = "🚫 Your account has been permanently banned. Contact <strong>admin@skillconnect.com</strong> if you believe this is an error.";
            } elseif (password_verify($password, $row['Password'])) {
                // ✅ PASSWORD CORRECT → LOGIN IMMEDIATELY (NO OTP)
                 $_SESSION['user_id'] = $row['ID'];
                $_SESSION['role']    = $row['Role'];
                $_SESSION['FName']   = $row['FName'];
                $_SESSION['LName']   = $row['LName'];
                $_SESSION['name']    = $row['FName'] . ' ' . $row['LName'];

                // Handle "Remember Me"
                if ($remember) {
                    $token  = bin2hex(random_bytes(32));
                    $expiry = time() + (30 * 24 * 60 * 60);
                    $up = $conn->prepare("UPDATE users SET remember_token=?, remember_expiry=? WHERE ID=?");
                    $up->bind_param("sii", $token, $expiry, $row['ID']);
                    $up->execute();
                    $up->close();
                    setcookie("remember_token", $token, $expiry, "/", "", false, true);
                }

                // Redirect to dashboardif ($row = $result->fetch_assoc()) {
                header("Location: " . ($row['Role'] === 'provider' ? 'provider.php' : 'client.php'));
                exit;
            } else {
                $message = "❌ Wrong password.";
            }
        } else {
            $message = "❌ Email not found. Please <a href='register.php' style='color:#4285f4;'>register here</a>.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | SkillConnect</title>
    <link rel="stylesheet" href="styles/login.css">
    <meta http-equiv="Cross-Origin-Opener-Policy" content="same-origin-allow-popups">
    <style>
        .divider {
            display: flex;
            align-items: center;
            margin: 24px 0;
            color: #666;
        }
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #ddd;
        }
        .divider span {
            padding: 0 12px;
            font-size: 14px;
        }
        .google-btn {
            width: 100%;
            padding: 14px 24px;
            background: #fff;
            color: #444;
            border: 1px solid #ddd;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            font-size: 16px;
            font-weight: 500;
            transition: all 0.2s;
        }
        .google-btn:hover {
            background: #f8f9fa;
            border-color: #dadce0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .google-btn img {
            width: 20px;
            height: 20px;
        }
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 16px;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="login-header">
        <h2>Welcome Back</h2>
        <p>Sign in to your SkillConnect account</p>
    </div>

    <?php if ($message): ?>
        <div class="error-message">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <!-- LOGIN FORM -->
    <form method="POST" class="login-form">
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" id="email" required placeholder="you@gmail.com">
        </div>
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" required placeholder="Enter your password">
        </div>
        <div class="form-group remember-me">
            <label>
                <input type="checkbox" name="remember" value="1"> 
                Remember me (30 days)
            </label>
        </div>
        <button type="submit" class="login-btn">Sign In</button>
    </form>

    <div class="divider">
        <span>OR</span>
    </div>

    <!-- GOOGLE SIGN IN -->
    <button onclick="googleSignIn()" class="google-btn">
        <img src="imge/google-icon.png" alt="Google">
        Sign in with Google
    </button>

    <div class="login-footer">
        <p>No account? <a href="register.php">Register here</a></p>
    </div>
</div>

<script type="module" src="js/firebase-login.js"></script>
</body>
</html>