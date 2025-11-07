<?php
// Minimal DB bootstrap — no output, no BOM. Save as UTF-8 (without BOM).
$DB_HOST = '127.0.0.1';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'skillconnect';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn && !$conn->connect_errno) {
    $conn->set_charset('utf8mb4');
} else {
    // keep silent for includes; log the error so it's visible in server logs
    error_log('DB connection error: ' . ($conn ? $conn->connect_error : 'unknown'));
    $conn = null;
}
?>
