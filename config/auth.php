<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params(['httponly'=>true, 'samesite'=>'Lax', 'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
    session_start();
}
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
require_once __DIR__ . '/database.php';
$authQuery = $pdo->prepare('SELECT id, full_name, email, role, status FROM users WHERE id = ?');
$authQuery->execute([$_SESSION['user_id'] ?? 0]);
$currentUser = $authQuery->fetch();
if (!$currentUser || $currentUser['status'] !== 'active') {
    $_SESSION = [];
    session_regenerate_id(true);
    header('Location: ../../login.php');
    exit;
}
$_SESSION['role'] = $currentUser['role'];
$_SESSION['full_name'] = $currentUser['full_name'];
$_SESSION['email'] = $currentUser['email'];
if (isset($requiredRole) && $currentUser['role'] !== $requiredRole) {
    header('Location: ../' . ($currentUser['role'] === 'admin' ? 'admin' : 'student') . '/dashboard.php');
    exit;
}
$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
