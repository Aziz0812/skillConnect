<?php
session_start();
require 'db.php';

// ✅ AUTO-LOGIN USING COOKIE
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];

    $stmt = $conn->prepare("SELECT ID, Role, FName, LName, remember_token, remember_expiry 
                            FROM users WHERE remember_token = ? LIMIT 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if ($row['remember_expiry'] > time()) {
            // Restore session
            $_SESSION['user_id'] = $row['ID'];
            $_SESSION['role'] = $row['Role'];
            $_SESSION['FName'] = $row['FName'];
            $_SESSION['LName'] = $row['LName'];
            $_SESSION['name'] = $row['FName'] . ' ' . $row['LName'];

            // Redirect to the right dashboard
            if ($row['Role'] === 'provider') {
                header("Location: provider.php");
            } elseif ($row['Role'] === 'client') {
                header("Location: client.php");
            }
            exit;
        } else {
            // Expired token cleanup
            setcookie("remember_token", $token, time() + (86400 * 30), "/");
            $stmt = $conn->prepare("UPDATE users SET remember_token = NULL, remember_expiry = NULL WHERE ID = ?");
            $stmt->bind_param("i", $row['ID']);
            $stmt->execute();
        }
    }
}


$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Input validation
    if (empty($email) || empty($password)) {
        $message = "Please fill in all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
    } else {
        $stmt = $conn->prepare("SELECT ID, Password, Role, FName, LName FROM users WHERE GMail = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->bind_result($id, $hashed_password, $role, $fname, $lname);
            $stmt->fetch();

            if (password_verify($password, $hashed_password)) {
                // Set session variables
                $_SESSION['user_id'] = $id;
                $_SESSION['role'] = $role;
                $_SESSION['FName'] = $fname;
                $_SESSION['LName'] = $lname;
                $_SESSION['name'] = $fname . ' ' . $lname;

                    // ✅ Handle "Remember Me"
                if (!empty($_POST['remember'])) {
                    $token = bin2hex(random_bytes(32)); // secure random token
                    $expiry = time() + (86400 * 30); // 30 days

                    // Store token in DB (you’ll add this column below)
                    $update = $conn->prepare("UPDATE users SET remember_token = ?, remember_expiry = ? WHERE ID = ?");
                    $update->bind_param("ssi", $token, $expiry, $id);
                    $update->execute();
                    $update->close();

                    // Simpler version for localhost testing
                    setcookie("remember_token", $token, time() + (86400 * 30), "/");

                    // 🔍 Debug output (you can remove later)
                    echo "<script>console.log('Remember cookie set: ' + document.cookie);</script>";

                }


                // Redirect based on role
                if ($role == 'client') {
                    header("Location: client.php");
                } elseif ($role == 'provider') {
                    header("Location: provider.php"); // Changed from provider.html
                } else {
                    $message = "Invalid user role.";
                }
                exit;
            } else {
                $message = "Invalid email or password.";
            }
        } else {
            $message = "Invalid email or password."; // Don't reveal if email exists
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
</head>
<body>
    <div class="container">
        <div class="login-header">
            <h2>Welcome Back</h2>
            <p>Sign in to your SkillConnect account</p>
        </div>
        
                <form method="POST" class="login-form">
                    <?php if (!empty($message)): ?>
                        <div class="error-message">
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" placeholder="Enter your email" 
                            value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" placeholder="Enter your password" required>
                    </div>

                    
                    <div class="form-group remember-me">
                        <label>
                            <input type="checkbox" name="remember" value="1">
                            Remember Me
                        </label>
                    </div>

                    <button type="submit" class="login-btn">Sign In</button>
                </form>

        
        <div class="login-footer">
            <p>Don't have an account? <a href="index.php">Sign up here</a></p>
            <a href="#" class="forgot-password">Forgot Password?</a>
        </div>
    </div>

    <script src="js/login.js"></script>
</body>
</html>