<?php
// Only included after config/auth.php verified the student role.
$portalRole = 'student';
$portalName = (string) ($currentUser['full_name'] ?? 'Student');
$portalEmail = (string) ($currentUser['email'] ?? '');
$portalPhoto = $currentUser['profile_photo_base64'] ?? null;
$portalActive = $studentNavActive ?? basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'dashboard.php'));
$portalPageLabel = match ($portalActive) {
    'courses.php', 'apply_course.php' => 'Courses',
    'profile.php' => 'Manage Profile',
    default => 'Dashboard',
};
$portalItems = [
    ['name' => 'Dashboard', 'icon' => 'fa-solid fa-table-columns', 'href' => 'dashboard.php', 'active' => ['dashboard.php']],
    ['name' => 'Courses', 'icon' => 'fa-solid fa-book-open', 'href' => 'courses.php', 'active' => ['courses.php','apply_course.php']],
    ['name' => 'Manage Profile', 'icon' => 'fa-solid fa-user-gear', 'href' => 'profile.php', 'active' => ['profile.php']],
];
require __DIR__ . '/../portal/sidebar.php';
