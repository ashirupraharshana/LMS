<?php
declare(strict_types=1);
$requiredRole = 'admin';
require_once __DIR__.'/../../config/auth.php';
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
$username = strtolower(trim((string)($_GET['username'] ?? '')));
$exclude = filter_var($_GET['exclude'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
if (!preg_match('/^[a-z][a-z0-9._-]{2,31}$/D', $username)) {
    http_response_code(422);
    echo json_encode(['available'=>false, 'message'=>'Use 3–32 characters; start with a letter.']);
    exit;
}
try {
    $q=$pdo->prepare('SELECT id FROM users WHERE username=? LIMIT 1');
    $q->execute([$username]);
    $id=$q->fetchColumn();
    $available = !$id || ($exclude!==false && (int)$id===(int)$exclude);
    echo json_encode(['available'=>$available, 'message'=>$available?'Username is available.':'Username is already taken.']);
} catch(Throwable $e) {
    error_log('Username availability check: '.$e->getMessage());
    http_response_code(503);
    echo json_encode(['available'=>false,'message'=>'Availability check unavailable.']);
}
