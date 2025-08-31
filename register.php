<?php
session_start();
require 'db.php';

$role = isset($_GET['role']) ? $_GET['role'] : 'client';
$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fname = $_POST['fname'];
    $lname = $_POST['lname'];
    $mname = $_POST['mname'] ?: NULL;
    $email = $_POST['email'];
    $location = $_POST['location'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users (LName, FName, MName, GMail, Password, Role, Location) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssss", $lname, $fname, $mname, $email, $password, $role, $location);

    if ($stmt->execute()) {
        $message = "Registration successful! <a href='login.php'>Login here</a>";
    } else {
        $message = "Error: " . $stmt->error;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Register | SkillConnect</title>
    <link rel="stylesheet" href="styles/register.css">
</head>
<body>
<h2>Register as <?php echo ucfirst($role); ?></h2>
<p style="color: red;"><?php echo $message; ?></p>
<form method="POST">
    <input type="text" name="fname" placeholder="First Name" required><br>
    <input type="text" name="lname" placeholder="Last Name" required><br>
    <input type="text" name="mname" placeholder="Middle Name"><br>
    <input type="email" name="email" placeholder="Email" required><br>
    <input type="text" name="location" placeholder="Location" required><br>
    <input type="password" name="password" placeholder="Password" required><br>
    <button type="submit">Register</button>
</form>
</body>
</html>
