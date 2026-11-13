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
// Count pending COURSE APPLICATIONS, not users with a pending account status.
// The auth middleware already loads the authenticated admin's PDO connection.
$pendingCountForNav = 0;
if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $pendingCountForNav = (int) $pdo
            ->query("SELECT COUNT(*) FROM course_applications WHERE status = 'pending'")
            ->fetchColumn();
    } catch (Throwable $e) {
        error_log('Admin pending application badge: ' . $e->getMessage());
    }
}
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

            ×
        </button>
    </div>

    <a class="brand" href="dashboard.php" aria-label="LMS admin dashboard">
        <span class="brand-icon"><i class="fa-solid fa-graduation-cap" aria-hidden="true"></i></span>
        <span class="brand-copy">
            <strong>LMS Admin</strong>
            <span>Management Portal</span>
        </span>
    </a>



    <div class="nav-label">Workspace</div>
    <nav class="nav-links" aria-label="Admin workspace">
        <a class="nav-link <?= ($currentPage === 'dashboard.php') ? 'active' : '' ?>"
            href="dashboard.php"
            <?= ($currentPage === 'dashboard.php') ? 'aria-current="page"' : '' ?>>

            <i class="fa-solid fa-table-columns" aria-hidden="true"></i>
            <span>Dashboard</span>
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
        <details class="student-menu" <?= in_array($currentPage, ['student_applications.php', 'add_student.php', 'manage_students.php'], true) ? 'open' : '' ?>>
            <summary class="nav-link" aria-label="Manage students">
                <i class="fa-solid fa-users" aria-hidden="true"></i>
                <span>Manage Students</span>
                <?php if ($pendingCountForNav > 0): ?>
                    <span class="nav-pending-dot" aria-label="Pending course applications"></span>
                <?php endif; ?>
                <i class="fa-solid fa-chevron-down student-menu-arrow" aria-hidden="true"></i>
            </summary>
            <div class="subnav">
                <a class="nav-link <?= $currentPage === 'student_applications.php' ? 'active' : '' ?>" href="student_applications.php" <?= $currentPage === 'student_applications.php' ? 'aria-current="page"' : '' ?>>
                    <i class="fa-regular fa-file-lines" aria-hidden="true"></i>
                    <span>Student Applications</span>
                    <?php if ($pendingCountForNav > 0): ?>
                        <span class="nav-notification" aria-label="<?= $pendingCountForNav ?> pending applications" title="<?= $pendingCountForNav ?> pending applications"><?= $pendingCountForNav > 99 ? '99+' : $pendingCountForNav ?></span>
                    <?php endif; ?>
                </a>
                <a class="nav-link <?= $currentPage === 'add_student.php' ? 'active' : '' ?>" href="add_student.php" <?= $currentPage === 'add_student.php' ? 'aria-current="page"' : '' ?>>
                    <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
                    <span>Add Student</span>
                </a>
                <a class="nav-link <?= $currentPage === 'manage_students.php' ? 'active' : '' ?>" href="manage_students.php" <?= $currentPage === 'manage_students.php' ? 'aria-current="page"' : '' ?>>
                    <i class="fa-solid fa-address-book" aria-hidden="true"></i>
                    <span>Manage All Students</span>
                </a>
            </div>
        </details>
        <a class="nav-link" href="../public/courses.php"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i><span>View Website</span></a>
    </nav>

    <div class="sidebar-spacer"></div>

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