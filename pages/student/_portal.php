<?php
declare(strict_types=1);
// Shared student-only page chrome and safe formatting helpers.
$requiredRole = 'student';
require_once __DIR__ . '/../../config/auth.php';

function spH(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function spLen(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function spDate(mixed $date): string
{
    $stamp = strtotime((string) $date);
    return $stamp === false ? '—' : date('d M Y', $stamp);
}

function spPageStart(string $title, array $currentUser, string $active): void
{
    $studentNavActive = $active;
    ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#111111">
  <title><?= spH($title) ?> | LMS Student Portal</title>
  <link rel="stylesheet" href="../../public/css/theme.css">
  <link rel="stylesheet" href="../../Components/student/css/navbar.css">
  <link rel="stylesheet" href="../../public/css/student-portal.css">
  <link rel="stylesheet" href="../../public/vendor/fontawesome.css">
  <link rel="stylesheet" href="../../public/css/portal-shell.css">
</head>
<body class="student-portal">
<?php include __DIR__ . '/../../Components/student/navbar.php'; ?>
<?php
}

function spPageEnd(): void
{
    ?>
<footer class="sp-footer">&copy; <?= date('Y') ?> LMS — Student Portal</footer>
<script src="../../public/vendor/sweetalert2.all.min.js"></script>
<script src="../../js/portal-sidebar.js"></script>
<script src="../../js/lms-alerts.js"></script>
</body>
</html>
<?php
}
