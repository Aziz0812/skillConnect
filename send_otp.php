<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'phpmailer/Exception.php';
require 'phpmailer/PHPMailer.php';
require 'phpmailer/SMTP.php';
require 'db.php';

// This script ONLY sends OTP emails
// It assumes the OTP is already generated and saved in the database

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid email']);
        exit;
    }

    // Get the OTP from database
    $stmt = $conn->prepare("SELECT otp_code FROM users WHERE GMail = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $otp = $row['otp_code'];
        $stmt->close();

        if (empty($otp)) {
            echo json_encode(['status' => 'error', 'message' => 'No OTP found. Please try again.']);
            exit;
        }

        // Send email using PHPMailer
        $mail = new PHPMailer(true);
        try {
            // SMTP Configuration
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'skillconnect.service25@gmail.com';     
            $mail->Password   = 'B!r-t-XcfRdgMV4';     
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            // Email Content
            $mail->setFrom('skillconnect.service25@gmail.com', 'SkillConnect');
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = 'Your SkillConnect Verification Code';
            $mail->Body    = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
                    <div style='text-align: center; margin-bottom: 30px;'>
                        <h1 style='color: #4285f4; margin: 0;'>SkillConnect</h1>
                    </div>
                    <div style='background: #f8f9fa; padding: 30px; border-radius: 10px; text-align: center;'>
                        <h2 style='color: #333; margin-top: 0;'>Your Verification Code</h2>
                        <div style='font-size: 48px; font-weight: bold; color: #4285f4; letter-spacing: 8px; margin: 20px 0;'>
                            $otp
                        </div>
                        <p style='color: #666; font-size: 14px; margin-bottom: 0;'>
                            This code will expire in 5 minutes.<br>
                            If you didn't request this code, please ignore this email.
                        </p>
                    </div>
                    <div style='text-align: center; margin-top: 30px; color: #999; font-size: 12px;'>
                        <p>© " . date('Y') . " SkillConnect. All rights reserved.</p>
                    </div>
                </div>
            ";

            $mail->send();
            echo json_encode(['status' => 'success', 'message' => 'OTP sent successfully']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'Email send failed: ' . $mail->ErrorInfo]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Email not found']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
?>