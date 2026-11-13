<?php
session_start();
require_once __DIR__.'/config/database.php';
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'],true)) { http_response_code(403); exit('Run setup from localhost on your XAMPP computer.'); }
$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
$error=''; $done=false;
$exists=(int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn()>0;
if ($_SERVER['REQUEST_METHOD']==='POST' && !$exists) {
 try {
    if (!is_string($_POST['csrf_token']??null) || !hash_equals($_SESSION['csrf_token'],$_POST['csrf_token'])) throw new RuntimeException('Refresh the page and try again.');
    $name=trim((string)($_POST['full_name']??''));$email=strtolower(trim((string)($_POST['email']??'')));$password=(string)($_POST['password']??'');
    if ($name==='' || mb_strlen($name)>120 || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>190) throw new RuntimeException('Enter a valid name and email.');
    if(strlen($password)<8 || strlen($password)>72 || $password!==($_POST['confirm_password']??'')) throw new RuntimeException('Enter matching passwords of 8–72 bytes.');
    $pdo->beginTransaction();
    // Serialize first-admin creation across concurrent setup requests.
    $lock=$pdo->query("SELECT GET_LOCK('lms_first_admin', 5)")->fetchColumn();
    if ((int)$lock!==1) throw new RuntimeException('Setup is busy. Try again.');
    if ($pdo->query("SELECT id FROM users WHERE role='admin' FOR UPDATE")->fetchColumn()) throw new RuntimeException('An administrator already exists.');
    $q=$pdo->prepare("INSERT INTO users(full_name,email,password_hash,role,status) VALUES(?,?,?,'admin','active')");
    $q->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT)]);$pdo->commit();$done=true;$exists=true;
 } catch(Throwable $e) {
    if($pdo->inTransaction())$pdo->rollBack();
    $error=$e instanceof PDOException?'This email is already registered, or the database is not ready.':$e->getMessage();
 } finally { $pdo->query("SELECT RELEASE_LOCK('lms_first_admin')"); }
}
function h($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
?><!doctype html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>First-time setup | LMS</title><link rel="stylesheet" href="public/css/theme.css"></head><body class="standalone"><main class="card setup-card"><span class="eyebrow">LMS · FIRST-TIME SETUP</span><h1>Create your administrator</h1><p>Use your own email and password to manage courses and students.</p><?php if($error):?><p class="notice error"><?=h($error)?></p><?php endif;if($exists):?><p class="notice"><?=$done?'Your administrator account is ready.':'Setup is locked because an administrator already exists.'?></p><a class="btn" href="login.php">Go to login</a><p class="muted">You can now delete setup.php.</p><?php else:?><form method="post"><input type="hidden" name="csrf_token" value="<?=h($_SESSION['csrf_token'])?>"><label>Full name<input name="full_name" maxlength="120" required></label><label>Email<input type="email" name="email" maxlength="190" required></label><label>Password<input type="password" name="password" minlength="8" maxlength="72" required autocomplete="new-password"></label><label>Confirm password<input type="password" name="confirm_password" minlength="8" maxlength="72" required autocomplete="new-password"></label><button class="btn">Create administrator</button></form><?php endif;?></main></body></html>
