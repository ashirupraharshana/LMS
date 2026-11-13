<?php
$portalRole = 'instructor';
$portalName = (string) ($currentInstructor['full_name'] ?? 'Instructor');
$portalEmail = (string) ($currentInstructor['email'] ?? '');
$portalPhoto = $currentInstructor['photo_base64'] ?? null;
$portalActive = basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'dashboard.php'));
$portalPageLabel = match ($portalActive) {
    'courses.php' => 'My Courses',
    'profile.php' => 'Manage Profile',
    default => 'Dashboard',
};
$portalItems = [
    ['name' => 'Dashboard', 'icon' => 'fa-solid fa-table-columns', 'href' => 'dashboard.php', 'active' => ['dashboard.php']],
    ['name' => 'My Courses', 'icon' => 'fa-solid fa-book-open', 'href' => 'courses.php', 'active' => ['courses.php']],
    ['name' => 'Manage Profile', 'icon' => 'fa-solid fa-user-gear', 'href' => 'profile.php', 'active' => ['profile.php']],
];
require __DIR__ . '/../portal/sidebar.php';
