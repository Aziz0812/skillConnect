<?php
session_start();
require 'db.php';

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