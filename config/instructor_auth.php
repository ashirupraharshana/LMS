<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
    session_start();
}
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
require_once __DIR__ . '/database.php';
if (($_SESSION['role'] ?? '') !== 'instructor' || empty($_SESSION['instructor_id'])) {
    if (!empty($_SESSION['user_id']) && in_array($_SESSION['role'] ?? '', ['admin','student'], true)) {
        header('Location: ../' . $_SESSION['role'] . '/dashboard.php');
    } else { header('Location: ../../login.php'); }
    exit;
}
$stmt = $pdo->prepare('SELECT id, full_name, email, phone, specialization, qualifications, bio, status, photo_base64, created_at FROM instructors WHERE id = ? LIMIT 1');
$stmt->execute([(int) $_SESSION['instructor_id']]);
$currentInstructor = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$currentInstructor || $currentInstructor['status'] !== 'active') {
    $_SESSION = [];
    session_regenerate_id(true);
    header('Location: ../../login.php?account_inactive=1');
    exit;
}
$_SESSION['full_name'] = $currentInstructor['full_name'];
$_SESSION['email'] = $currentInstructor['email'];
$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
