<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Only authenticated administrators may access this page.
if (empty($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

if (($_SESSION['role'] ?? '') !== 'admin') {
    if (($_SESSION['role'] ?? '') === 'student') {
        header('Location: ../student/dashboard.php');
    } else {
        header('Location: ../../login.php');
    }
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function escapeOutput(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$adminName = trim((string) ($_SESSION['full_name'] ?? 'Administrator'));
if ($adminName === '') {
    $adminName = 'Administrator';
}
$firstName = preg_split('/\s+/', $adminName)[0] ?? 'Administrator';
$stats = [
    'total_students' => 0,
    'active_students' => 0,
    'pending_students' => 0,
    'inactive_students' => 0,
    'total_admins' => 0,
];
$recentStudents = [];
$pendingStudents = [];
$dashboardError = '';

try {
    require_once __DIR__ . '/../../config/database.php';

    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new RuntimeException('Database connection is unavailable.');
    }

    $statsStmt = $pdo->query(
        "SELECT
            COUNT(CASE WHEN role = 'student' THEN 1 END) AS total_students,
            COUNT(CASE WHEN role = 'student' AND status = 'active' THEN 1 END) AS active_students,
            COUNT(CASE WHEN role = 'student' AND status = 'pending' THEN 1 END) AS pending_students,
            COUNT(CASE WHEN role = 'student' AND status = 'inactive' THEN 1 END) AS inactive_students,
            COUNT(CASE WHEN role = 'admin' THEN 1 END) AS total_admins
         FROM users"
    );
    $stats = array_merge($stats, $statsStmt->fetch(PDO::FETCH_ASSOC) ?: []);

    $recentStmt = $pdo->query(
        "SELECT id, full_name, email, status, created_at
         FROM users
         WHERE role = 'student'
         ORDER BY created_at DESC, id DESC
         LIMIT 8"
    );
    $recentStudents = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

    $pendingStmt = $pdo->query(
        "SELECT id, full_name, email, created_at
         FROM users
         WHERE role = 'student' AND status = 'pending'
         ORDER BY created_at ASC, id ASC
         LIMIT 6"
    );
    $pendingStudents = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('LMS admin dashboard error: ' . $e->getMessage());
    $dashboardError = 'Dashboard data could not be loaded. Check the database connection and try refreshing.';
}

function formatDate(mixed $value): string
{
    if (!$value) {
        return '—';
    }
    try {
        return (new DateTimeImmutable((string) $value))->format('M j, Y');
    } catch (Throwable) {
        return '—';
    }
}

function statusClass(string $status): string
{
    return match ($status) {
        'active' => 'status-active',
        'pending' => 'status-pending',
        'inactive' => 'status-inactive',
        default => 'status-neutral',
    };
}
?>

<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#111827">
    <title>Admin Dashboard | Student Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="../../Components/admin/style/navbar.css">
</head>
<body>
<div class="app">
    <?php require_once __DIR__ . '/../../Components/admin/navbar.php'; ?>

    <main class="main">
        <header class="topbar">
            <div class="topbar-left">
                <button class="menu-toggle" id="menuToggle" type="button" aria-label="Open navigation" aria-expanded="false"><i class="fa-solid fa-bars" aria-hidden="true"></i></button>
                <div class="breadcrumbs"><span>Workspace</span><i class="fa-solid fa-chevron-right" aria-hidden="true"></i><strong>Dashboard</strong></div>
            </div>
            <div class="topbar-actions">
                <span class="secure-pill"><i class="fa-solid fa-shield-check" aria-hidden="true"></i> Secure admin session</span>
                <form action="logout.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= escapeOutput($_SESSION['csrf_token']) ?>">
                    <button class="top-logout" type="submit"><i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i><span>Log out</span></button>
                </form>
            </div>
        </header>

        <div class="content">
            <section class="welcome-row" aria-labelledby="welcomeTitle">
                <div>
                    <div class="eyebrow"><i class="fa-solid fa-sparkles" aria-hidden="true"></i> ADMIN OVERVIEW</div>
                    <h1 id="welcomeTitle">Welcome back, <?= escapeOutput($firstName) ?>.</h1>
                    <p class="welcome-copy">Here’s what’s happening with your student community today.</p>
                </div>
                <div class="date-chip"><i class="fa-regular fa-calendar" aria-hidden="true"></i><?= escapeOutput(date('l, F j, Y')) ?></div>
            </section>

            <?php if ($dashboardError !== ''): ?>
                <div class="alert" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><span><?= escapeOutput($dashboardError) ?></span></div>
            <?php endif; ?>

            <section class="stats-grid" aria-label="Student statistics">
                <article class="stat-card" style="--orb:#ecefff;--icon:#5668df;--icon-bg:#eef0ff">
                    <div class="stat-top"><span class="stat-label">Total students</span><span class="stat-icon"><i class="fa-solid fa-users" aria-hidden="true"></i></span></div>
                    <div class="stat-number"><?= number_format((int) $stats['total_students']) ?></div>
                    <div class="stat-foot">Registered student accounts</div>
                </article>
                <article class="stat-card" style="--orb:#e1f7eb;--icon:#13865b;--icon-bg:#eaf8f0">
                    <div class="stat-top"><span class="stat-label">Active students</span><span class="stat-icon"><i class="fa-solid fa-user-check" aria-hidden="true"></i></span></div>
                    <div class="stat-number"><?= number_format((int) $stats['active_students']) ?></div>
                    <div class="stat-foot"><strong>Active</strong> and able to log in</div>
                </article>
                <article class="stat-card" style="--orb:#fff0d7;--icon:#a7670a;--icon-bg:#fff5e4">
                    <div class="stat-top"><span class="stat-label">Pending approval</span><span class="stat-icon"><i class="fa-regular fa-hourglass-half" aria-hidden="true"></i></span></div>
                    <div class="stat-number"><?= number_format((int) $stats['pending_students']) ?></div>
                    <div class="stat-foot"><strong>Needs review</strong> by an administrator</div>
                </article>
                <article class="stat-card" style="--orb:#ffe7eb;--icon:#bd3a4b;--icon-bg:#fff0f1">
                    <div class="stat-top"><span class="stat-label">Inactive students</span><span class="stat-icon"><i class="fa-solid fa-user-slash" aria-hidden="true"></i></span></div>
                    <div class="stat-number"><?= number_format((int) $stats['inactive_students']) ?></div>
                    <div class="stat-foot">Currently inactive accounts</div>
                </article>
            </section>

            <div class="columns">
                <section class="panel" id="students" aria-labelledby="studentsTitle">
                    <div class="panel-header">
                        <div><h2 class="panel-title" id="studentsTitle">Recent student registrations</h2><p class="panel-subtitle">The latest accounts created in your system</p></div>
                        <span class="panel-count"><?= count($recentStudents) ?></span>
                    </div>
                    <?php if ($recentStudents): ?>
                        <div class="table-wrap">
                            <table>
                                <thead><tr><th>Student</th><th>Status</th><th>Registered</th></tr></thead>
                                <tbody>
                                <?php foreach ($recentStudents as $student): ?>
                                    <?php $studentName = (string) ($student['full_name'] ?? 'Student'); ?>
                                    <tr>
                                        <td><div class="student-cell"><span class="student-avatar"><?= escapeOutput(mb_strtoupper(mb_substr($studentName, 0, 1, 'UTF-8'), 'UTF-8')) ?></span><span class="student-info"><strong><?= escapeOutput($studentName) ?></strong><span><?= escapeOutput($student['email'] ?? '') ?></span></span></div></td>
                                        <td><span class="status <?= escapeOutput(statusClass((string) ($student['status'] ?? ''))) ?>"><?= escapeOutput($student['status'] ?? 'unknown') ?></span></td>
                                        <td><?= escapeOutput(formatDate($student['created_at'] ?? null)) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state"><i class="fa-regular fa-folder-open" aria-hidden="true"></i>No student registrations yet.</div>
                    <?php endif; ?>
                    <div class="panel-footer">Showing up to 8 most recently registered students.</div>
                </section>

                <div>
                    <section class="panel" id="pending" aria-labelledby="pendingTitle">
                        <div class="panel-header">
                            <div><h2 class="panel-title" id="pendingTitle">Pending approvals</h2><p class="panel-subtitle">Students waiting for account activation</p></div>
                            <span class="panel-count"><?= (int) $stats['pending_students'] ?></span>
                        </div>
                        <?php if ($pendingStudents): ?>
                            <div class="pending-list">
                                <?php foreach ($pendingStudents as $student): ?>
                                    <div class="pending-item">
                                        <span class="student-avatar"><?= escapeOutput(mb_strtoupper(mb_substr((string) ($student['full_name'] ?? 'S'), 0, 1, 'UTF-8'), 'UTF-8')) ?></span>
                                        <span class="pending-person"><strong><?= escapeOutput($student['full_name'] ?? 'Student') ?></strong><span><?= escapeOutput($student['email'] ?? '') ?></span></span>
                                        <span class="pending-date"><?= escapeOutput(formatDate($student['created_at'] ?? null)) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="panel-footer">To approve an account, change its status to <strong>active</strong> in your admin workflow or database.</div>
                        <?php else: ?>
                            <div class="empty-state"><i class="fa-solid fa-circle-check" aria-hidden="true"></i>No pending student accounts. You’re all caught up.</div>
                        <?php endif; ?>
                    </section>

                    <aside class="quick-card">
                        <div class="quick-card-top"><span class="quick-icon"><i class="fa-solid fa-lightbulb" aria-hidden="true"></i></span><h3>Administrator tip</h3></div>
                        <p>New registrations are created with <strong>pending</strong> status. Review each student before activating their account.</p>
                    </aside>
                </div>
            </div>
            <footer class="footer">&copy; <?= date('Y') ?> Student Management System. All rights reserved.</footer>
        </div>
    </main>
</div>

</body>
</html>
