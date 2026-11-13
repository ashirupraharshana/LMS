<?php
declare(strict_types=1);
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#111111"><title>Account access help | LMS</title>
<link rel="stylesheet" href="public/css/theme.css">
<style>
*{box-sizing:border-box}body{margin:0;background:#f5f5f5;color:#151515;font:15px/1.6 system-ui,Arial,sans-serif;min-height:100vh;display:grid;place-items:center;padding:22px}
.help-card{width:min(560px,100%);border:1px solid #e5e5e5;border-radius:20px;background:#fff;padding:clamp(24px,5vw,46px);box-shadow:0 14px 45px #00000009}
.help-mark{display:grid;place-items:center;width:46px;height:46px;background:#111;color:white;border-radius:12px;font-size:23px;font-weight:900}h1{font-size:clamp(28px,5vw,38px);letter-spacing:-.05em;margin:22px 0 10px}p{color:#646464;margin:0 0 17px}.help-note{background:#f6f6f6;border:1px solid #e8e8e8;border-radius:12px;padding:15px 17px;margin:20px 0 24px;color:#333}.help-btn{display:inline-flex;text-decoration:none;border-radius:10px;background:#111;color:#fff;padding:12px 19px;font-size:13px;font-weight:750}.help-btn:hover{background:#333;color:#fff}
</style></head><body>
<main class="help-card"><span class="help-mark" aria-hidden="true">L.</span><h1>Account access help</h1>
<p>Student and instructor accounts are managed by your LMS administrator.</p>
<div class="help-note">If you forgot your password or cannot sign in, contact your administrator. They can verify your identity and assign a new password using the student or instructor management page.</div>
<a href="login.php" class="help-btn">Back to login →</a>
</main></body></html>
