<?php
// XAMPP defaults. Change these values if your local MySQL configuration differs.
$host = getenv('LMS_DB_HOST') ?: '127.0.0.1';
$dbname = getenv('LMS_DB_NAME') ?: 'lms';
$username = getenv('LMS_DB_USER') ?: 'root';
$password = getenv('LMS_DB_PASSWORD') ?: '';
$port = getenv('LMS_DB_PORT') ?: '3306';
try {
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    error_log('LMS database connection: ' . $e->getMessage());
    http_response_code(503);
    exit('Database unavailable. Start MySQL in XAMPP, import database/lms.sql and check config/database.php.');
}
