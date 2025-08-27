<?php
// config/db_config.php

define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'nano_db');

// สร้างการเชื่อมต่อ
// รองรับการกำหนดค่า host อื่น ๆ ผ่าน Environment Variables (ถ้ามี)
$db_server = getenv('DB_SERVER') ?: DB_SERVER;
$db_username = getenv('DB_USERNAME') ?: DB_USERNAME;
$db_password = getenv('DB_PASSWORD') ?: DB_PASSWORD;
$db_name = getenv('DB_NAME') ?: DB_NAME;

$conn = new mysqli($db_server, $db_username, $db_password, $db_name);
$conn->set_charset("utf8mb4");
// ตรวจสอบการเชื่อมต่อ
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ตั้งค่า charset เป็น utf8mb4 สำหรับรองรับภาษาไทยและอีโมจิ
$conn->set_charset("utf8mb4");
?>