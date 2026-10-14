<?php


// Define the helper only if the current page has not already defined it.
if (!function_exists('escapeOutput')) {
    function escapeOutput(mixed $value): string
    {
        return htmlspecialchars(
            (string) $value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }
}

$currentPage = basename($_SERVER['PHP_SELF']);


$adminNameForNav = trim((string) ($adminName ?? 'Administrator'));
if ($adminNameForNav === '') {
    $adminNameForNav = 'Administrator';
}
$pendingCountForNav = (int) ($stats['pending_students'] ?? 0);
$adminInitialForNav = function_exists('mb_substr') && function_exists('mb_strtoupper')
    ? mb_strtoupper(mb_substr($adminNameForNav, 0, 1, 'UTF-8'), 'UTF-8')
    : strtoupper(substr($adminNameForNav, 0, 1));
?>
<div class="mobile-overlay" id="mobileOverlay" aria-hidden="true"></div>

<aside class="sidebar" id="sidebar" aria-label="Admin navigation">

    <!-- Mobile sidebar header -->
    <div class="sidebar-mobile-header">
        <span>Navigation</span>

        <button
            type="button"
            class="sidebar-close-btn"
            id="closeSidebar"
            aria-label="Close navigation">

            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
    </div>

    <a class="brand" href="../../index.php" aria-label="Student Portal home">
        <span class="brand-icon"><i class="fa-solid fa-graduation-cap" aria-hidden="true"></i></span>
        <span class="brand-copy">
            <strong>Student Portal</strong>
            <span>Management System</span>
        </span>
    </a>



    <div class="nav-label">Workspace</div>
    <nav class="nav-links" aria-label="Admin workspace">
        <a class="nav-link <?= ($currentPage === 'dashboard.php') ? 'active' : '' ?>"
            href="dashboard.php"
            <?= ($currentPage === 'dashboard.php') ? 'aria-current="page"' : '' ?>>

            <i class="fa-solid fa-grid-2" aria-hidden="true"></i>
            <span>Overview</span>
        </a>
        <a class="nav-link <?= ($currentPage === 'course_categories.php') ? 'active' : '' ?>"
            href="../../pages/admin/course_categories.php">
            <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
            <span>Categories</span>
        </a>
        <a class="nav-link <?= ($currentPage === 'manage_courses.php') ? 'active' : '' ?>"
            href="../../pages/admin/manage_courses.php">
            <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
            <span>Courses</span>
        </a>
        <a class="nav-link <?= ($currentPage === 'manage_students.php') ? 'active' : '' ?>"
            href="../../pages/admin/manage_students.php">
            <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
            <span>Student</span>
        </a>
        <a class="nav-link <?= ($currentPage === 'register.php') ? 'active' : '' ?>"
            href="../../pages/admin/register.php">
            <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
            <span>Register</span>
        </a>
        <a class="nav-link <?= ($currentPage === 'apply_courses.php') ? 'active' : '' ?>"
            href="../../pages/admin/apply_courses.php">
            <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
            <span>apply_courses</span>
        </a>
        <a class="nav-link" href="dashboard.php#pending">
            <i class="fa-regular fa-clock" aria-hidden="true"></i>
            <span>Pending Approvals</span>
            <?php if ($pendingCountForNav > 0): ?>
                <span class="panel-count nav-pending-count"><?= $pendingCountForNav ?></span>
            <?php endif; ?>
        </a>
        <a class="nav-link" href="../../courses.php">
            <i class="fa-solid fa-book-open" aria-hidden="true"></i>
            <span>Courses</span>
        </a>
    </nav>

    <div class="sidebar-spacer"></div>

    <div class="sidebar-help">
        <span class="help-icon"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></span>
        <div>
            <strong>Admin workspace</strong>
            <span>Manage registrations and monitor student account status.</span>
        </div>
    </div>

    <div class="sidebar-bottom">
        <span class="avatar"><?= escapeOutput($adminInitialForNav) ?></span>
        <div class="sidebar-user">
            <strong><?= escapeOutput($adminNameForNav) ?></strong>
            <span>Administrator</span>
        </div>
        <form action="logout.php" method="post" class="sidebar-logout-form">
            <input type="hidden" name="csrf_token" value="<?= escapeOutput($_SESSION['csrf_token'] ?? '') ?>">
            <button class="logout-mini" type="submit" aria-label="Log out" title="Log out">
                <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>
            </button>
        </form>
    </div>
</aside>

<script src="../../Components/admin/js/admin-navbar.js" defer></script>