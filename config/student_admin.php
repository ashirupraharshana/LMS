<?php
$requiredRole = 'admin';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/image_helpers.php';
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function field(string $key): string { return is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : ''; }
function csrf(): void { echo '<input type="hidden" name="csrf_token" value="'.h($_SESSION['csrf_token']).'">'; }
function checkCsrf(): void {
    if (!hash_equals($_SESSION['csrf_token'], field('csrf_token'))) throw new InvalidArgumentException('Your session expired. Refresh this page and try again.');
}
function success(string $text, string $url): void {
    $_SESSION['notice'] = $text;
    header('Location: '.$url); exit;
}
function studentFields(bool $passwordRequired): array {
    $name=field('full_name'); $email=strtolower(field('email')); $phone=field('phone'); $status=field('status');
    $username = strtolower(field('username'));
    if ($username === '' && $passwordRequired) throw new InvalidArgumentException('Enter a username for the student.');
    if ($username !== '' && !preg_match('/^[a-z][a-z0-9._-]{2,31}$/D', $username)) throw new InvalidArgumentException('Username must be 3–32 characters, start with a letter and use only letters, digits, dots, hyphens or underscores.');
    $password=is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $confirm=is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';
    $instructorCheck = $GLOBALS['pdo']->prepare('SELECT id FROM instructors WHERE email=? LIMIT 1');
    $instructorCheck->execute([$email]);
    if ($instructorCheck->fetchColumn()) throw new InvalidArgumentException('This email already belongs to an instructor.');
    if ($name==='' || (function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name))>120) throw new InvalidArgumentException('Enter a name of 1–120 characters.');
    if (!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>190) throw new InvalidArgumentException('Enter a valid email address (maximum 190 characters).');
    if ((function_exists('mb_strlen') ? mb_strlen($phone, 'UTF-8') : strlen($phone))>30) throw new InvalidArgumentException('Phone number must be at most 30 characters.');
    if (!in_array($status,['active','inactive','pending'],true)) throw new InvalidArgumentException('Choose a valid status.');
    if ($passwordRequired || $password!=='' || $confirm!=='') {
        if (strlen($password)<8 || strlen($password)>72) throw new InvalidArgumentException('Password must contain 8–72 bytes.');
        if ($password!==$confirm) throw new InvalidArgumentException('Passwords do not match.');
    }
    return [$name,$email,$phone,$status,$password,$username];
}
function operationError(Throwable $e): string {
    if ($e instanceof InvalidArgumentException) return $e->getMessage();
    error_log('LMS student operation: '.$e->getMessage());
    return $e instanceof PDOException && $e->getCode()==='23000' ? 'This email or username is already in use, or the record is linked to another item.' : 'The change could not be saved. Refresh and try again.';
}
function startPage(string $title, string $subtitle): void {
    $adminName=$_SESSION['full_name'];
    ?><!doctype html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=h($title)?> | LMS</title><link rel="stylesheet" href="../../public/vendor/fontawesome.css"><link rel="stylesheet" href="../../Components/admin/style/navbar.css"><link rel="stylesheet" href="../../public/css/theme.css"><link rel="stylesheet" href="../../Components/admin/style/admin-mono.css"><link rel="stylesheet" href="https://cdn.datatables.net/v/dt/dt-3.1.5/datatables.min.css"><link rel="stylesheet" href="../../public/css/admin-datatables.css"><link rel="stylesheet" href="../../Components/admin/style/admin-v2.css">
</head><body><div class="app"><?php require __DIR__.'/../Components/admin/navbar.php'; ?><main class="main"><header class="topbar"><div class="topbar-left"><button class="menu-toggle" id="menuToggle" type="button" aria-label="Open navigation" aria-expanded="false"><i class="fa-solid fa-bars" aria-hidden="true"></i></button><span>Workspace / <strong><?=h($title)?></strong></span></div><a href="../../pages/public/courses.php">View website ↗</a></header><div class="content"><div class="page-heading"><span class="eyebrow">LMS MANAGEMENT</span><h1><?=h($title)?></h1><p><?=h($subtitle)?></p></div><?php
    if (isset($_SESSION['notice'])) { echo '<div class="notice" role="status">'.h($_SESSION['notice']).'</div>'; unset($_SESSION['notice']); }
}
function endPage(): void { echo '</div></main></div><script src="../../public/vendor/sweetalert2.all.min.js"></script><script src="../../js/student-admin.js"></script><script src="../../js/lms-alerts.js" defer></script><script src="https://cdn.datatables.net/v/dt/dt-3.1.5/datatables.min.js"></script><script src="../../js/admin-datatables.js" defer></script><script src="../../js/lms-media.js" defer></script></body></html>'; }
function studentForm(array $values, bool $editing=false): void {
    ?><div class="form-grid"><label>Username <?=$editing?'':'*'?><input name="username" id="username" data-exclude="<?=h($values['id']??'')?>" maxlength="32" minlength="3" pattern="[A-Za-z][A-Za-z0-9._-]{2,31}" autocomplete="off" value="<?=h($values['username']??'')?>" <?=$editing?'':'required'?>><small class="username-hint muted" aria-live="polite">3–32 characters. Availability checked as you type.</small></label><label>Full name *<input name="full_name" maxlength="120" required value="<?=h($values['full_name']??'')?>" autocomplete="name"></label><label>Email address *<input type="email" name="email" maxlength="190" required value="<?=h($values['email']??'')?>" autocomplete="email"></label><label>Phone number<input name="phone" maxlength="30" value="<?=h($values['phone']??'')?>" autocomplete="tel"></label><label>Student photo (JPG, PNG, WebP · max 1 MB)<input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp" data-image-preview="studentPhotoPreview"></label><div class="lms-edit-photo" id="studentPhotoPreview"><?php if(!empty($values['profile_photo_base64'])): ?><?=lmsImageHtml($values['profile_photo_base64'],'Student photo')?><?php else: ?><span>Student photo preview</span><?php endif;?></div><?php if($editing && !empty($values['profile_photo_base64'])): ?><label class="lms-checkbox"><input type="checkbox" name="remove_profile_photo" value="1"> Remove existing photo</label><?php endif; ?><label>Account status<select name="status"><?php foreach(['active','inactive','pending'] as $s): ?><option value="<?=$s?>" <?=($values['status']??'active')===$s?'selected':''?>><?=ucfirst($s)?></option><?php endforeach; ?></select></label><label><?=$editing?'New password (optional)':'Initial password *'?><input type="password" name="password" minlength="8" maxlength="72" autocomplete="new-password" <?=$editing?'':'required'?>></label><label>Confirm password<input type="password" name="confirm_password" minlength="8" maxlength="72" autocomplete="new-password" <?=$editing?'':'required'?>></label></div><p class="muted"><?=$editing?'Leave both password fields blank to keep the current password.':'Share the initial password privately with the student.'?></p><div class="actions"><button class="btn" type="submit"><?=$editing?'Save changes':'Create student'?></button><a class="btn secondary" href="manage_students.php">Cancel</a></div><?php
}
