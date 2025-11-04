<?php
session_start();
require 'db.php';

// Make sure this page is accessed after Google login
if (!isset($_SESSION['google_user'])) {
    header("Location: login.php");
    exit;
}

$google_user = $_SESSION['google_user'];
$email = $google_user['email'];
$name = $google_user['name'];
$uid = $google_user['uid'];
$photo = $google_user['photo'] ?? null;

$message = "";

// Split name into first and last
$nameParts = explode(' ', $name, 2);
$fname = $nameParts[0];
$lname = isset($nameParts[1]) ? $nameParts[1] : '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $role = $_POST['role'] ?? 'client';
    $city = trim($_POST['city'] ?? '');
    $province = trim($_POST['province'] ?? '');
    $barangay = trim($_POST['barangay'] ?? '');

    // Validation
    if (empty($city) || empty($province) || empty($barangay)) {
        $message = "❌ Please fill in all location fields.";
    } else {
        // Check if already registered (avoid duplicates)
        $check = $conn->prepare("SELECT ID FROM users WHERE GMail = ? LIMIT 1");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows == 0) {
            // Insert new Google user (NO OTP needed - Google verified them)
            $stmt = $conn->prepare("
                INSERT INTO users (FName, LName, GMail, Role, City, Province, Barangay, Password)
                VALUES (?, ?, ?, ?, ?, ?, ?, '')
            ");
            $stmt->bind_param("sssssss", $fname, $lname, $email, $role, $city, $province, $barangay);

            if ($stmt->execute()) {
                $user_id = $stmt->insert_id;

                // Set session variables
                $_SESSION['user_id'] = $user_id;
                $_SESSION['role'] = $role;
                $_SESSION['FName'] = $fname;
                $_SESSION['LName'] = $lname;
                $_SESSION['name'] = "$fname $lname";

                // Set remember token
                $token  = bin2hex(random_bytes(32));
                $expiry = time() + (30 * 24 * 60 * 60);
                $update = $conn->prepare("UPDATE users SET remember_token = ?, remember_expiry = ? WHERE ID = ?");
                $update->bind_param("sii", $token, $expiry, $user_id);
                $update->execute();
                $update->close();
                setcookie("remember_token", $token, $expiry, "/", "", false, true);

                unset($_SESSION['google_user']);

                // Redirect based on role
                $redirect = ($role === 'provider') ? 'provider.php' : 'client.php';
                header("Location: $redirect");
                exit;
            } else {
                $message = "❌ Error creating account: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $message = "❌ Account already exists. Please login.";
        }
        $check->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Setup | SkillConnect</title>
    <link rel="stylesheet" href="styles/register.css">
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
        .welcome-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .welcome-header h2 {
            color: #333;
            margin: 0 0 10px 0;
            font-size: 28px;
            font-weight: 600;
        }
        .user-info {
            background: linear-gradient(135deg, #667eea15, #764ba215);
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 25px;
            text-align: center;
        }
        .user-info .email {
            color: #667eea;
            font-weight: 600;
            font-size: 16px;
        }
        .user-info .note {
            color: #666;
            font-size: 13px;
            margin-top: 6px;
        }
        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 25px;
            font-size: 14px;
        }
        .message {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        form {
            display: grid;
            gap: 16px;
        }
        label {
            font-size: 13px;
            color: #555;
            font-weight: 600;
            margin-bottom: -10px;
        }
        .role-selector {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
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
            padding: 16px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            font-weight: 600;
            font-size: 15px;
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
        input[type="text"] {
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
        button {
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
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(102, 126, 234, 0.5);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="welcome-header">
            <h2>👋 Welcome!</h2>
        </div>

        <div class="user-info">
            <div class="email"><?php echo htmlspecialchars($email); ?></div>
            <div class="note">✓ Verified by Google</div>
        </div>

        <p class="subtitle">Just a few more details to complete your profile</p>

        <?php if (!empty($message)): ?>
            <div class="message"><?php echo $message; ?></div>
        <?php endif; ?>

        <form method="POST">
            <label>I want to use SkillConnect as:</label>
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

            <label>Your Location:</label>
            <input type="text" name="city" placeholder="City *" required>
            <input type="text" name="province" placeholder="Province *" required>
            <input type="text" name="barangay" placeholder="Barangay *" required>

            <button type="submit">Complete Setup & Continue →</button>
        </form>
    </div>
</body>
</html>