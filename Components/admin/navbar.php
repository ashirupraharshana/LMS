<?php
if (!function_exists('escapeOutput')) {
    function escapeOutput(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}
$currentPage = basename($_SERVER['PHP_SELF']);
$adminNameForNav = trim((string)($adminName ?? $_SESSION['full_name'] ?? 'Administrator')) ?: 'Administrator';
$adminInitialForNav = function_exists('mb_substr') ? mb_strtoupper(mb_substr($adminNameForNav,0,1,'UTF-8'),'UTF-8') : strtoupper(substr($adminNameForNav,0,1));
$pendingCountForNav = 0;
if (isset($pdo) && $pdo instanceof PDO) {
    try { $pendingCountForNav = (int)$pdo->query("SELECT COUNT(*) FROM course_applications WHERE status='pending'")->fetchColumn(); }
    catch (Throwable $e) { error_log('Pending applications badge: '.$e->getMessage()); }
}
$courseOpen = in_array($currentPage, ['manage_courses.php','course_categories.php'], true);
$instructorOpen = in_array($currentPage, ['instructors.php'], true);
$studentOpen = in_array($currentPage, ['student_applications.php','add_student.php','manage_students.php'], true);
$view = (string)($_GET['view'] ?? 'all');
$studentStatus = (string)($_GET['status'] ?? 'all');
?>
<div class="mobile-overlay" id="mobileOverlay" aria-hidden="true"></div>
<aside class="sidebar" id="sidebar" aria-label="Admin navigation">
  <div class="sidebar-mobile-header"><span>Navigation</span><button type="button" class="sidebar-close-btn" id="closeSidebar" aria-label="Close navigation">&times;</button></div>
  <a class="brand" href="dashboard.php"><span class="brand-icon"><i class="fa-solid fa-graduation-cap" aria-hidden="true"></i></span><span class="brand-copy"><strong>LMS Admin</strong><span>Management Portal</span></span></a>
  <div class="nav-label">USER MANAGEMENT</div>
  <nav class="nav-links" aria-label="User management">
    <a class="nav-link <?= $currentPage==='users.php'?'active':'' ?>" href="users.php" <?= $currentPage==='users.php'?'aria-current="page"':'' ?>><i class="fa-solid fa-user-shield" aria-hidden="true"></i><span>Users</span></a>
  </nav>
  <div class="nav-label">MAIN PANEL</div>
  <nav class="nav-links" aria-label="Main panel">
    <a class="nav-link <?= $currentPage==='dashboard.php'?'active':'' ?>" href="dashboard.php" <?= $currentPage==='dashboard.php'?'aria-current="page"':'' ?>><i class="fa-solid fa-table-columns" aria-hidden="true"></i><span>Dashboard</span></a>
    <details class="student-menu" <?= $courseOpen?'open':'' ?>><summary class="nav-link"><i class="fa-solid fa-book-open" aria-hidden="true"></i><span>Courses</span><i class="fa-solid fa-chevron-down student-menu-arrow" aria-hidden="true"></i></summary>
      <div class="subnav">
        <a class="nav-link <?= $currentPage==='manage_courses.php' && isset($_GET['create'])?'active':'' ?>" href="manage_courses.php?create=1#courseForm"><i class="fa-solid fa-plus" aria-hidden="true"></i><span>Create Courses</span></a>
        <a class="nav-link <?= $currentPage==='manage_courses.php' && !isset($_GET['create'])?'active':'' ?>" href="manage_courses.php#courseListTitle"><i class="fa-solid fa-list" aria-hidden="true"></i><span>Manage Courses</span></a>
        <a class="nav-link <?= $currentPage==='course_categories.php'?'active':'' ?>" href="course_categories.php"><i class="fa-solid fa-layer-group" aria-hidden="true"></i><span>Course Categories</span></a>
      </div>
    </details>
    <details class="student-menu" <?= $instructorOpen?'open':'' ?>><summary class="nav-link"><i class="fa-solid fa-chalkboard-user" aria-hidden="true"></i><span>Instructors</span><i class="fa-solid fa-chevron-down student-menu-arrow" aria-hidden="true"></i></summary>
      <div class="subnav">
        <a class="nav-link <?= $currentPage==='instructors.php' && $view==='create'?'active':'' ?>" href="instructors.php?view=create"><i class="fa-solid fa-user-plus" aria-hidden="true"></i><span>Create Instructor</span></a>
        <a class="nav-link <?= $currentPage==='instructors.php' && $view==='active'?'active':'' ?>" href="instructors.php?view=active"><i class="fa-solid fa-circle-check" aria-hidden="true"></i><span>Active Instructors</span></a>
        <a class="nav-link <?= $currentPage==='instructors.php' && $view==='inactive'?'active':'' ?>" href="instructors.php?view=inactive"><i class="fa-solid fa-circle-pause" aria-hidden="true"></i><span>Inactive Instructors</span></a>
        <a class="nav-link <?= $currentPage==='instructors.php' && $view==='all'?'active':'' ?>" href="instructors.php?view=all"><i class="fa-solid fa-address-book" aria-hidden="true"></i><span>All Instructors</span></a>
      </div>
    </details>
    <details class="student-menu" <?= $studentOpen?'open':'' ?>><summary class="nav-link"><i class="fa-solid fa-users" aria-hidden="true"></i><span>Students</span><?php if($pendingCountForNav>0): ?><span class="nav-pending-dot" title="Pending applications"></span><?php endif; ?><i class="fa-solid fa-chevron-down student-menu-arrow" aria-hidden="true"></i></summary>
      <div class="subnav">
        <a class="nav-link <?= $currentPage==='student_applications.php'?'active':'' ?>" href="student_applications.php?status=pending"><i class="fa-regular fa-file-lines" aria-hidden="true"></i><span>Applications</span><?php if($pendingCountForNav>0): ?><span class="nav-notification" aria-label="<?= $pendingCountForNav ?> pending applications"><?= $pendingCountForNav>99?'99+':$pendingCountForNav ?></span><?php endif; ?></a>
        <a class="nav-link <?= $currentPage==='manage_students.php' && $studentStatus==='all'?'active':'' ?>" href="manage_students.php"><i class="fa-solid fa-address-book" aria-hidden="true"></i><span>All Students</span></a>
        <a class="nav-link <?= $currentPage==='manage_students.php' && $studentStatus==='active'?'active':'' ?>" href="manage_students.php?status=active"><i class="fa-solid fa-user-check" aria-hidden="true"></i><span>Active Students</span></a>
        <a class="nav-link <?= $currentPage==='manage_students.php' && $studentStatus==='inactive'?'active':'' ?>" href="manage_students.php?status=inactive"><i class="fa-solid fa-user-slash" aria-hidden="true"></i><span>Inactive Students</span></a>
        <a class="nav-link <?= $currentPage==='add_student.php'?'active':'' ?>" href="add_student.php"><i class="fa-solid fa-user-plus" aria-hidden="true"></i><span>Add Student</span></a>
      </div>
    </details>
  </nav>
  <div class="sidebar-spacer"></div>
  <a class="nav-link nav-website" href="../public/courses.php"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i><span>View Website</span></a>
  <div class="sidebar-bottom"><span class="avatar"><?= escapeOutput($adminInitialForNav) ?></span><div class="sidebar-user"><strong><?= escapeOutput($adminNameForNav) ?></strong><span>Administrator</span></div><form action="logout.php" method="post" class="sidebar-logout-form"><input type="hidden" name="csrf_token" value="<?= escapeOutput($_SESSION['csrf_token']??'') ?>"><button class="logout-mini" type="submit" aria-label="Log out" title="Log out"><i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i></button></form></div>
</aside>
<script src="../../Components/admin/js/admin-navbar.js" defer></script>
