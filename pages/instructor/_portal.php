<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/instructor_auth.php';
require_once __DIR__ . '/../../config/image_helpers.php';
function ipH(mixed $v): string { return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function ipLen(string $v): int { return function_exists('mb_strlen') ? mb_strlen($v, 'UTF-8') : strlen($v); }
function ipPageStart(string $title): void {
    global $currentInstructor;
    ?><!doctype html><html lang="en"><head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#111111">
    <title><?=ipH($title)?> | LMS Instructor</title>
    <link rel="stylesheet" href="../../public/css/theme.css">
    <link rel="stylesheet" href="../../public/css/student-portal.css">
    <link rel="stylesheet" href="../../public/vendor/fontawesome.css">
    <link rel="stylesheet" href="../../public/css/portal-shell.css">
    <link rel="stylesheet" href="../../public/css/instructor-portal.css">
    </head><body class="student-portal instructor-portal">
    <?php require __DIR__ . '/../../Components/instructor/navbar.php'; ?>
    <?php
}
function ipPageEnd(): void { ?>
    <footer class="sp-footer">&copy; <?= date('Y') ?> LMS — Instructor Portal</footer>
    <script src="../../public/vendor/sweetalert2.all.min.js"></script>
    <script src="../../js/portal-sidebar.js"></script>
    <script src="../../js/lms-alerts.js"></script>
    <script src="../../js/lms-media.js"></script>
    </body></html>
<?php }
