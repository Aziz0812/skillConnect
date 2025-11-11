<?php
$plain_password = 'admin123'; // Change this to your desired password
$hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);
echo "Hashed password: " . $hashed_password;
?>