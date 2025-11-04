<?php
session_start();
require 'db.php';

$message = "";
$showOtpForm = false;

// ===============  STEP 1: REGISTER & SEND OTP ===============
if ($_SERVER["REQUEST_METHOD"] === "POST" && !isset($_POST['otp'])) {
    $fname     = trim($_POST['fname']);
    $lname     = trim($_POST['lname']);
    $mname     = trim($_POST['mname']) ?: null;
    $email     = trim($_POST['email']);
    $password  = $_POST['password'];
    $city      = trim($_POST['city']);
    $province  = trim($_POST['province']);
    $barangay  = trim($_POST['barangay']);
    $role      = $_POST['role'] ?? 'client';

    // Validation
    if (empty($fname) || empty($lname) || empty($email) || empty($password) || empty($city) || empty($province) || empty($barangay)) {
        $message = "❌ Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "❌ Invalid email format.";
    } elseif (strlen($password) < 6) {
        $message = "❌ Password must be at least 6 characters.";
    } else {
        // Check if email already exists
        $check = $conn->prepare("SELECT ID FROM users WHERE GMail = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $message = "❌ Email already registered. <a href='login.php' style='color:#4285f4;'>Login here</a>";
            $check->close();
        } else {
            $check->close();

            // Generate OTP
            $otp = sprintf("%06d", mt_rand(100000, 999999));
            $expires = time() + 300; // 5 minutes
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insert user with OTP (account not verified yet)
            $stmt = $conn->prepare("
                INSERT INTO users 
                (LName, FName, MName, GMail, Password, Role, City, Province, Barangay, otp_code, otp_expires)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("ssssssssssi", $lname, $fname, $mname, $email, $hashed_password, $role, $city, $province, $barangay, $otp, $expires);

            if ($stmt->execute()) {
                $user_id = $stmt->insert_id;
                $stmt->close();

                // Send OTP via email
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, "http://localhost/skillConnect/send_otp.php");
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['email' => $email]));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_exec($ch);
                curl_close($ch);

                // Store in session for OTP verification
                $_SESSION['pending_registration'] = [
                    'user_id' => $user_id,
                    'email'   => $email,
                    'role'    => $role,
                    'fname'   => $fname,
                    'lname'   => $lname
                ];

                $showOtpForm = true;
                $message = "✅ OTP sent to your email! Please verify to complete registration.";
            } else {
                $message = "❌ Error: " . $stmt->error;
                $stmt->close();
            }
        }
    }
}

// ===============  STEP 2: VERIFY OTP ===============
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['otp'])) {
    $otp = trim($_POST['otp']);
    
    if (isset($_SESSION['pending_registration'])) {
        $user_id = $_SESSION['pending_registration']['user_id'];
        $email   = $_SESSION['pending_registration']['email'];
        $role    = $_SESSION['pending_registration']['role'];
        $fname   = $_SESSION['pending_registration']['fname'];
        $lname   = $_SESSION['pending_registration']['lname'];

        // Verify OTP
        $stmt = $conn->prepare("SELECT otp_code, otp_expires FROM users WHERE ID = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row && $otp === $row['otp_code'] && time() < $row['otp_expires']) {
            // ✅ OTP VERIFIED → Clear OTP and login
            $clear = $conn->prepare("UPDATE users SET otp_code = NULL, otp_expires = NULL WHERE ID = ?");
            $clear->bind_param("i", $user_id);
            $clear->execute();
            $clear->close();

            // Set session
            $_SESSION['user_id'] = $user_id;
            $_SESSION['role']    = $role;
            $_SESSION['FName']   = $fname;
            $_SESSION['LName']   = $lname;
            $_SESSION['name']    = "$fname $lname";

            // Clear pending registration
            unset($_SESSION['pending_registration']);

            // Redirect to dashboard
            header("Location: " . ($role === 'provider' ? 'provider.php' : 'client.php'));
            exit;
        } else {
            $message = "❌ Wrong or expired OTP! Please try again.";
            $showOtpForm = true;
        }
    } else {
        $message = "❌ Session expired. Please register again.";
    }
}

// Check if we should show OTP form from session
if (isset($_SESSION['pending_registration']) && !$showOtpForm) {
    $showOtpForm = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | SkillConnect</title>
    <link rel="stylesheet" href="styles/register.css">
    <meta http-equiv="Cross-Origin-Opener-Policy" content="same-origin-allow-popups">
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            max-width: 440px;
            width: 100%;
            background: #fff;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        h2 {
            text-align: center;
            color: #333;
            margin: 0 0 10px 0;
            font-size: 28px;
            font-weight: 600;
        }
        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .message {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
        }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        /* GOOGLE BUTTON - PROMINENT */
        .google-section {
            margin-bottom: 30px;
        }
        .google-btn {
            width: 100%;
            padding: 16px 24px;
            font-size: 16px;
            background: #fff;
            color: #444;
            border: 2px solid #ddd;
            border-radius: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            transition: all 0.3s;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .google-btn:hover {
            background: #f8f9fa;
            border-color: #4285f4;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(66, 133, 244, 0.2);
        }
        .google-btn img {
            width: 24px;
            height: 24px;
        }
        .google-benefit {
            text-align: center;
            color: #666;
            font-size: 13px;
            margin-top: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .google-benefit span {
            color: #34a853;
            font-weight: 600;
        }
        
        .divider {
            display: flex;
            align-items: center;
            margin: 30px 0;
            color: #999;
        }
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #e0e0e0;
        }
        .divider span {
            padding: 0 15px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .form-section {
            margin-top: 20px;
        }
        .form-header {
            text-align: center;
            color: #666;
            font-size: 13px;
            margin-bottom: 20px;
        }
        
        form {
            display: grid;
            gap: 14px;
        }
        
        .role-selector {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 6px;
        }
        .role-option {
            position: relative;
        }
        .role-option input[type="radio"] {
            position: absolute;
            opacity: 0;
        }
        .role-label {
            display: block;
            padding: 14px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            font-weight: 600;
            font-size: 14px;
            background: #f8f9fa;
        }
        .role-option input[type="radio"]:checked + .role-label {
            border-color: #667eea;
            background: linear-gradient(135deg, #667eea15, #764ba215);
            color: #667eea;
        }
        .role-label:hover {
            border-color: #667eea;
        }
        
        label {
            font-size: 13px;
            color: #555;
            font-weight: 600;
            margin-bottom: -8px;
        }
        
        input[type="text"],
        input[type="email"],
        input[type="password"] {
            padding: 13px 14px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.2s;
            background: #f8f9fa;
        }
        input:focus {
            outline: none;
            border-color: #667eea;
            background: #fff;
        }
        
        .name-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        
        .submit-btn {
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            margin-top: 10px;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(102, 126, 234, 0.5);
        }
        
        .footer-text {
            text-align: center;
            margin-top: 24px;
            color: #666;
            font-size: 14px;
        }
        .footer-text a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        .footer-text a:hover {
            text-decoration: underline;
        }
        
        .otp-input {
            font-size: 36px !important;
            letter-spacing: 14px;
            text-align: center;
            font-weight: bold;
            padding: 20px !important;
        }
        .resend-link {
            display: block;
            text-align: center;
            margin-top: 15px;
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
        }
        .resend-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="container">
    <?php if (!$showOtpForm): ?>
        <!-- STEP 1: REGISTRATION OPTIONS -->
        <h2>Join SkillConnect</h2>
        <p class="subtitle">Create your account in seconds</p>

        <?php if ($message): ?>
            <div class="message <?php echo strpos($message, '✅') !== false ? 'success' : 'error'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- GOOGLE SIGN UP - PROMINENT -->
        <div class="google-section">
            <button onclick="googleSignIn()" class="google-btn">
                <img src="imge/google-icon.png" alt="Google">
                Continue with Google
            </button>
            <div class="google-benefit">
                ✓ <span>Instant signup</span> • No password needed
            </div>
        </div>

        <div class="divider"><span>Or use email</span></div>

        <!-- EMAIL REGISTRATION FORM -->
        <div class="form-section">
            <form method="POST">
                <label>Register as:</label>
                <div class="role-selector">
                    <div class="role-option">
                        <input type="radio" name="role" value="client" id="role-client" checked>
                        <label for="role-client" class="role-label">👤 Client</label>
                    </div>
                    <div class="role-option">
                        <input type="radio" name="role" value="provider" id="role-provider">
                        <label for="role-provider" class="role-label">⚙️ Provider</label>
                    </div>
                </div>
                
                <div class="name-row">
                    <input type="text" name="fname" placeholder="First Name *" required>
                    <input type="text" name="lname" placeholder="Last Name *" required>
                </div>
                <input type="text" name="mname" placeholder="Middle Name (optional)">
                <input type="email" name="email" placeholder="Email Address *" required>
                <input type="password" name="password" placeholder="Password (min 6 chars) *" required minlength="6">
                <input type="text" name="city" placeholder="City *" required>
                <input type="text" name="province" placeholder="Province *" required>
                <input type="text" name="barangay" placeholder="Barangay *" required>
                
                <button type="submit" class="submit-btn">Create Account</button>
            </form>
        </div>

        <p class="footer-text">
            Already have an account? <a href="login.php">Sign in</a>
        </p>

    <?php else: ?>
        <!-- STEP 2: OTP VERIFICATION -->
        <h2>Verify Your Email</h2>
        <p class="subtitle">Enter the 6-digit code sent to<br><strong><?php echo htmlspecialchars($_SESSION['pending_registration']['email'] ?? ''); ?></strong></p>

        <?php if ($message): ?>
            <div class="message <?php echo strpos($message, '✅') !== false ? 'success' : 'error'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input 
                type="text" 
                name="otp" 
                class="otp-input"
                maxlength="6" 
                required 
                placeholder="000000"
                autofocus
            >
            <button type="submit" class="submit-btn">Verify & Complete</button>
        </form>

        <a href="?resend=1" class="resend-link">Resend Code</a>
        <a href="register.php" class="resend-link">← Start Over</a>
    <?php endif; ?>
</div>

<?php
// Handle OTP resend
if (isset($_GET['resend']) && isset($_SESSION['pending_registration'])) {
    $user_id = $_SESSION['pending_registration']['user_id'];
    $email = $_SESSION['pending_registration']['email'];
    
    // Generate new OTP
    $otp = sprintf("%06d", mt_rand(100000, 999999));
    $expires = time() + 300;
    
    $update = $conn->prepare("UPDATE users SET otp_code = ?, otp_expires = ? WHERE ID = ?");
    $update->bind_param("sii", $otp, $expires, $user_id);
    $update->execute();
    $update->close();
    
    // Send email
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://localhost/skillConnect/send_otp.php");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['email' => $email]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
    
    echo "<script>alert('✅ Code resent successfully!'); window.location.href='register.php';</script>";
}
?>

<script type="module" src="js/firebase-login.js"></script>

</body>
</html>