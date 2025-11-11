<?php
session_start();
require 'db.php';

// Redirect if already logged in as admin
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin') {
    header("Location: admin/index.php");
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password.";
    } else {
        $stmt = $conn->prepare("SELECT ID, Password, Role, FName, LName, AccountStatus FROM users WHERE GMail = ? AND Role = 'admin' LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            if ($row['AccountStatus'] === 'suspended' || $row['AccountStatus'] === 'banned') {
                $error = "This admin account has been suspended. Contact the system administrator immediately.";
            } elseif (password_verify($password, $row['Password'])) {
                // ✅ Admin login successful
                $_SESSION['user_id'] = $row['ID'];
                $_SESSION['role'] = 'admin';
                $_SESSION['FName'] = $row['FName'];
                $_SESSION['LName'] = $row['LName'];
                $_SESSION['name'] = $row['FName'] . ' ' . $row['LName'];
                
                // Log admin login
                $log_stmt = $conn->prepare("INSERT INTO admin_activity_log (AdminID, Action, TargetType, TargetID, Details, IPAddress) VALUES (?, 'login', 'user', ?, 'Admin logged in', ?)");
                $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                $log_stmt->bind_param("iis", $row['ID'], $row['ID'], $ip);
                $log_stmt->execute();
                $log_stmt->close();
                
                header("Location: admin/index.php");
                exit;
            } else {
                $error = "Invalid password.";
            }
        } else {
            $error = "Admin account not found.";
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
    <title>Admin Login | SkillConnect</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .admin-login-container {
            background: white;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 420px;
            animation: slideUp 0.4s ease;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .admin-header {
            text-align: center;
            margin-bottom: 32px;
        }
        
        .admin-badge {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 16px;
        }
        
        .admin-header h1 {
            font-size: 28px;
            color: #2c3e50;
            margin-bottom: 8px;
        }
        
        .admin-header p {
            color: #6c757d;
            font-size: 14px;
        }
        
        .error-message {
            background: #fee;
            border-left: 4px solid #dc3545;
            color: #721c24;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-size: 14px;
            animation: shake 0.3s ease;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }
        
        .login-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 8px;
        }
        
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }
        
        .login-btn:active {
            transform: translateY(0);
        }
        
        .back-link {
            text-align: center;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #e9ecef;
        }
        
        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: color 0.2s;
        }
        
        .back-link a:hover {
            color: #764ba2;
        }
        
        .security-notice {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 12px 16px;
            border-radius: 8px;
            margin-top: 24px;
            font-size: 13px;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="admin-login-container">
        <div class="admin-header">
            <div class="admin-badge">🔐 Admin Access</div>
            <h1>SkillConnect Admin</h1>
            <p>Secure administrative panel</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error-message">
                ⚠️ <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Admin Email</label>
                <input type="email" name="email" required autofocus 
                       placeholder="admin@skillconnect.com"
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required 
                       placeholder="Enter your admin password">
            </div>
            
            <button type="submit" class="login-btn">
                🔓 Access Admin Panel
            </button>
        </form>
        
        <div class="security-notice">
            🛡️ This is a restricted area. All access attempts are logged.
        </div>
        
        <div class="back-link">
            <a href="index.php">← Back to Main Site</a>
        </div>
    </div>
</body>
</html>