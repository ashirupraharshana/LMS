<?php
declare(strict_types=1);
$requiredRole = 'admin';
require_once __DIR__ . '/../../config/auth.php';

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

function formatCourseDate(mixed $value): string
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

function courseStringLength(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function redirectToCourses(string $searchTerm = ''): never
{
    $url = 'manage_courses.php';
    if ($searchTerm !== '') {
        $url .= '?q=' . rawurlencode($searchTerm);
    }
    header('Location: ' . $url);
    exit;
}

function setCourseFlash(string $type, string $message): void
{
    $_SESSION['manage_courses_flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

$adminName = trim((string) ($_SESSION['full_name'] ?? 'Administrator'));
if ($adminName === '') {
    $adminName = 'Administrator';
}
$firstName = preg_split('/\s+/', $adminName)[0] ?? 'Administrator';
$currentPage = basename($_SERVER['PHP_SELF']);


$pageError = '';
$formError = '';
$searchTerm = trim((string) ($_GET['q'] ?? ''));
$formAction = 'add';
$showEditForm = false;
$editCourse = null;
$courses = [];
$categories = [];
$stats = [
    'total_courses' => 0,
    'published_courses' => 0,
    'draft_courses' => 0,
    'archived_courses' => 0,
];
$formValues = [
    'id' => '',
    'title' => '',
    'description' => '',
    'category_id' => '',
    'status' => 'draft',
];

$flash = $_SESSION['manage_courses_flash'] ?? null;
unset($_SESSION['manage_courses_flash']);
$successMessage = '';
$flashError = '';
if (is_array($flash)) {
    if (($flash['type'] ?? '') === 'success') {
        $successMessage = (string) ($flash['message'] ?? '');
    } else {
        $flashError = (string) ($flash['message'] ?? '');
    }
}

try {
    require_once __DIR__ . '/../../config/database.php';

    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new RuntimeException('The PDO database connection is unavailable.');
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Load real categories for the course form dropdown. Inactive categories
    // are included and labelled so existing courses can still be edited safely.
    $categoryStmt = $pdo->query('SELECT id, name, status FROM course_categories ORDER BY name ASC');
    $categories = $categoryStmt->fetchAll();
} catch (Throwable $e) {
    error_log('Manage courses connection/category error: ' . $e->getMessage());
    $pageError = 'Course data or categories could not be loaded. Check the database connection and required tables.';
    $pdo = null;
}

$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$postAction = $isPost ? (string) ($_POST['action'] ?? '') : '';

if ($isPost) {
    $searchTerm = trim((string) ($_POST['return_q'] ?? $searchTerm));

    if (in_array($postAction, ['add', 'update'], true)) {
        $formValues = [
            'id' => trim((string) ($_POST['id'] ?? '')),
            'title' => trim((string) ($_POST['title'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'category_id' => trim((string) ($_POST['category_id'] ?? '')),
            'status' => (string) ($_POST['status'] ?? 'draft'),
        ];
        $formAction = $postAction;
        $showEditForm = $postAction === 'update';
    }

    if (!hash_equals((string) $_SESSION['csrf_token'], (string) ($_POST['csrf_token'] ?? ''))) {
        $formError = 'Your session token is invalid or expired. Refresh the page and try again.';
    } elseif (!$pdo instanceof PDO) {
        $formError = 'The database connection is unavailable. Your changes were not saved.';
    } else {
        try {
            if ($postAction === 'add' || $postAction === 'update') {
                $id = filter_var($formValues['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                $categoryId = filter_var($formValues['category_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                $title = $formValues['title'];
                $description = $formValues['description'];
                $status = $formValues['status'];

                if ($postAction === 'update' && $id === false) {
                    $formError = 'The course ID is invalid. Please select the course again.';
                } elseif ($title === '') {
                    $formError = 'Please enter a course title.';
                } elseif (courseStringLength($title) > 150) {
                    $formError = 'The course title cannot exceed 150 characters.';
                } elseif (courseStringLength($description) > 5000) {
                    $formError = 'The description cannot exceed 5,000 characters.';
                } elseif ($categoryId === false) {
                    $formError = 'Please select a course category.';
                } elseif (!in_array($status, ['draft', 'published', 'archived'], true)) {
                    $formError = 'Please select a valid course status.';
                } else {
                    $categoryCheck = $pdo->prepare('SELECT id FROM course_categories WHERE id = :id');
                    $categoryCheck->execute([':id' => $categoryId]);
                    if (!$categoryCheck->fetchColumn()) {
                        $formError = 'The selected category no longer exists. Refresh the page and select another category.';
                    } else {
                        $descriptionValue = $description === '' ? null : $description;

                        if ($postAction === 'add') {
                            $stmt = $pdo->prepare(
                                'INSERT INTO courses (title, description, category_id, status)
                                 VALUES (:title, :description, :category_id, :status)'
                            );
                            $stmt->execute([
                                ':title' => $title,
                                ':description' => $descriptionValue,
                                ':category_id' => $categoryId,
                                ':status' => $status,
                            ]);
                            setCourseFlash('success', 'Course added successfully.');
                        } else {
                            $stmt = $pdo->prepare(
                                'UPDATE courses
                                 SET title = :title,
                                     description = :description,
                                     category_id = :category_id,
                                     status = :status
                                 WHERE id = :id'
                            );
                            $stmt->execute([
                                ':title' => $title,
                                ':description' => $descriptionValue,
                                ':category_id' => $categoryId,
                                ':status' => $status,
                                ':id' => $id,
                            ]);

                            if ($stmt->rowCount() === 0) {
                                $checkStmt = $pdo->prepare('SELECT id FROM courses WHERE id = :id');
                                $checkStmt->execute([':id' => $id]);
                                if (!$checkStmt->fetchColumn()) {
                                    $formError = 'This course no longer exists. Refresh the list and try again.';
                                }
                            }

                            if ($formError === '') {
                                setCourseFlash('success', 'Course updated successfully.');
                            }
                        }

                        if ($formError === '') {
                            redirectToCourses($searchTerm);
                        }
                    }
                }
            } elseif ($postAction === 'delete') {
                $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($id === false) {
                    $formError = 'The course ID is invalid.';
                } else {
                    $stmt = $pdo->prepare('DELETE FROM courses WHERE id = :id');
                    $stmt->execute([':id' => $id]);

                    if ($stmt->rowCount() === 0) {
                        setCourseFlash('error', 'The course was not found or has already been deleted.');
                    } else {
                        setCourseFlash('success', 'Course deleted successfully.');
                    }
                    redirectToCourses($searchTerm);
                }
            } else {
                $formError = 'Invalid request. Please try again.';
            }
        } catch (PDOException $e) {
            error_log('Manage course operation error: ' . $e->getMessage());
            $sqlState = (string) ($e->errorInfo[0] ?? '');
            if ($postAction === 'delete' && $sqlState === '23000') {
                $formError = 'This course has application history and cannot be deleted. Set its status to Archived instead.';
            } else {
                $formError = 'The operation could not be completed. Check the course details and try again.';
            }
        } catch (Throwable $e) {
            error_log('Manage course operation error: ' . $e->getMessage());
            $formError = 'An unexpected error occurred. Please try again.';
        }
    }
}

if (!$isPost && isset($_GET['edit'])) {
    $editId = filter_var($_GET['edit'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($editId === false) {
        $formError = 'The selected course ID is invalid.';
    } elseif ($pdo instanceof PDO) {
        try {
            $stmt = $pdo->prepare('SELECT id, title, description, category_id, status FROM courses WHERE id = :id');
            $stmt->execute([':id' => $editId]);
            $editCourse = $stmt->fetch() ?: null;

            if ($editCourse) {
                $formValues = [
                    'id' => (string) $editCourse['id'],
                    'title' => (string) $editCourse['title'],
                    'description' => (string) ($editCourse['description'] ?? ''),
                    'category_id' => (string) $editCourse['category_id'],
                    'status' => (string) $editCourse['status'],
                ];
                $formAction = 'update';
                $showEditForm = true;
            } else {
                $formError = 'The course you selected could not be found.';
            }
        } catch (Throwable $e) {
            error_log('Manage course edit load error: ' . $e->getMessage());
            $pageError = 'The selected course could not be loaded.';
        }
    }
}

if ($pdo instanceof PDO) {
    try {
        $statsStmt = $pdo->query(
            "SELECT
                COUNT(*) AS total_courses,
                COALESCE(SUM(status = 'published'), 0) AS published_courses,
                COALESCE(SUM(status = 'draft'), 0) AS draft_courses,
                COALESCE(SUM(status = 'archived'), 0) AS archived_courses
             FROM courses"
        );
        $stats = array_merge($stats, $statsStmt->fetch() ?: []);

        if ($searchTerm !== '') {
            $stmt = $pdo->prepare(
                'SELECT c.id, c.title, c.description, c.category_id, c.status, c.created_at, c.updated_at,
                        cc.name AS category_name, cc.status AS category_status
                 FROM courses c
                 INNER JOIN course_categories cc ON cc.id = c.category_id
                 WHERE c.title LIKE :title_search
                    OR c.description LIKE :description_search
                    OR cc.name LIKE :category_search
                 ORDER BY c.created_at DESC, c.id DESC'
            );
            $likeSearch = '%' . $searchTerm . '%';
            $stmt->bindValue(':title_search', $likeSearch, PDO::PARAM_STR);
            $stmt->bindValue(':description_search', $likeSearch, PDO::PARAM_STR);
            $stmt->bindValue(':category_search', $likeSearch, PDO::PARAM_STR);
            $stmt->execute();
        } else {
            $stmt = $pdo->query(
                'SELECT c.id, c.title, c.description, c.category_id, c.status, c.created_at, c.updated_at,
                        cc.name AS category_name, cc.status AS category_status
                 FROM courses c
                 INNER JOIN course_categories cc ON cc.id = c.category_id
                 ORDER BY c.created_at DESC, c.id DESC'
            );
        }
        $courses = $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('Manage course list error: ' . $e->getMessage());
        $pageError = 'Courses could not be displayed. Confirm that the courses table exists and has the expected columns.';
    }
}

$sweetAlerts = [];
if ($successMessage !== '') {
    $sweetAlerts[] = ['icon' => 'success', 'title' => 'Success', 'text' => $successMessage];
}
if ($flashError !== '') {
    $sweetAlerts[] = ['icon' => 'error', 'title' => 'Unable to complete request', 'text' => $flashError];
}
if ($pageError !== '') {
    $sweetAlerts[] = ['icon' => 'error', 'title' => 'Something went wrong', 'text' => $pageError];
}
if ($formError !== '') {
    $sweetAlerts[] = ['icon' => 'error', 'title' => 'Please check your request', 'text' => $formError];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#382720">
    <title>Manage Courses | Student Management System</title>
    <link rel="stylesheet" href="../../public/vendor/fontawesome.css">
    <link rel="stylesheet" href="../../Components/admin/style/navbar.css">
    <script src="../../public/vendor/sweetalert2.all.min.js"></script>
    <style>
        /* SweetAlert2 premium styling — matched to course_categories.php */
        .login-swal-popup {
            border: 1px solid #e7eaf2 !important;
            border-radius: 22px !important;
            padding: 30px 24px !important;
            font-family: Inter, "Segoe UI", sans-serif !important;
            box-shadow: 0 24px 70px rgba(23, 32, 51, 0.16) !important;
        }

        .login-swal-confirm {
            min-width: 120px !important;
            padding: 12px 22px !important;
            border: 0 !important;
            border-radius: 10px !important;
            background: #9a0002 !important;
            color: #fff !important;
            font-size: 13px !important;
            font-weight: 700 !important;
            box-shadow: 0 5px 14px rgba(80, 101, 233, 0.2) !important;
        }

        .login-swal-confirm:focus {
            box-shadow: 0 0 0 4px rgba(80, 101, 233, 0.18) !important;
        }

        .login-swal-cancel {
            min-width: 110px !important;
            padding: 12px 22px !important;
            border: 1px solid #e5e7eb !important;
            border-radius: 10px !important;
            background: #fff !important;
            color: #382720 !important;
            font-size: 13px !important;
            font-weight: 700 !important;
        }

        :root{--mc-primary:#9a0002;--mc-primary-dark:#9a0002;--mc-text:#382720;--mc-muted:#9a0002;--mc-border:#e5e7eb;--mc-card:#fff}
        .course-content{width:100%;max-width:1500px;margin:0 auto;padding:28px clamp(16px,2.5vw,36px);color:var(--mc-text)}
        .course-welcome{display:flex;justify-content:space-between;align-items:flex-start;gap:18px;margin-bottom:25px}
        .course-eyebrow{display:flex;align-items:center;gap:8px;margin-bottom:8px;color:var(--mc-primary);font-size:11px;font-weight:800;letter-spacing:.12em}
        .course-welcome h1{margin:0;color:#382720;font-size:clamp(25px,3vw,32px);line-height:1.2;letter-spacing:-.035em}
        .course-welcome p{margin:9px 0 0;color:var(--mc-muted);font-size:14px;line-height:1.6}
        .course-date{flex-shrink:0;display:inline-flex;align-items:center;gap:9px;padding:10px 13px;border:1px solid var(--mc-border);border-radius:12px;background:#fff;color:#9a0002;font-size:12px;font-weight:600}
        .course-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:15px;margin-bottom:22px}
        .course-stat{padding:18px 19px;border:1px solid #edf0f5;border-radius:16px;background:#fff;box-shadow:0 4px 18px rgba(15,23,42,.035)}
        .course-stat-label{color:var(--mc-muted);font-size:12px;font-weight:700}.course-stat-number{margin-top:10px;color:#382720;font-size:29px;font-weight:800;letter-spacing:-.04em}.course-stat-foot{margin-top:4px;color:#9a0002;font-size:11px}
        .course-panel{min-width:0;margin-bottom:22px;overflow:hidden;border:1px solid #e9ecf2;border-radius:16px;background:#fff;box-shadow:0 5px 20px rgba(15,23,42,.035)}
        .course-panel-header{display:flex;justify-content:space-between;align-items:center;gap:15px;padding:20px 22px;border-bottom:1px solid #edf0f4}
        .course-panel-header h2{margin:0;color:#382720;font-size:16px;font-weight:800}.course-panel-header p{margin:5px 0 0;color:var(--mc-muted);font-size:12px;line-height:1.5}.course-panel-body{padding:22px}
        .course-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px 20px}.course-field{display:flex;flex-direction:column;gap:7px;min-width:0}.course-field.full{grid-column:1/-1}
        .course-field label{color:#382720;font-size:12px;font-weight:700}.course-field .required{color:#dc2626}
        .course-field input,.course-field select,.course-field textarea{width:100%;min-height:43px;padding:11px 12px;border:1px solid #dfe3eb;border-radius:10px;outline:none;background:#fff;color:#382720;font:inherit;font-size:13px;transition:border-color .18s ease,box-shadow .18s ease}
        .course-field textarea{min-height:100px;resize:vertical}.course-field input:focus,.course-field select:focus,.course-field textarea:focus{border-color:#9a0002;box-shadow:0 0 0 3px rgba(99,102,241,.12)}.course-help{color:#9a0002;font-size:11px}
        .course-actions{display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin-top:20px}
        .course-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:40px;padding:10px 15px;border:1px solid transparent;border-radius:10px;cursor:pointer;text-decoration:none;font:inherit;font-size:12px;font-weight:750;transition:background .18s ease,transform .18s ease}.course-btn:hover{transform:translateY(-1px)}
        .course-btn-primary{background:var(--mc-primary);color:#fff}.course-btn-primary:hover{background:var(--mc-primary-dark)}.course-btn-light{border-color:#dfe3eb;background:#fff;color:#9a0002}.course-btn-light:hover{background:#f8fafc}
        .course-search{display:flex;width:min(100%,490px);align-items:center;gap:8px}.course-search-wrap{position:relative;flex:1;min-width:100px}.course-search-wrap i{position:absolute;top:50%;left:13px;transform:translateY(-50%);color:#9a0002;font-size:13px}.course-search input{width:100%;height:40px;padding:9px 12px 9px 36px;border:1px solid #e0e4eb;border-radius:10px;outline:none;background:#fff;color:#382720;font:inherit;font-size:12px}
        .course-table-wrap{width:100%;overflow-x:auto}.course-table{width:100%;border-collapse:collapse;text-align:left}.course-table th{padding:13px 16px;border-bottom:1px solid #e9edf3;background:#fafbfe;color:#9a0002;font-size:10px;font-weight:800;letter-spacing:.07em;text-transform:uppercase;white-space:nowrap}.course-table td{padding:15px 16px;border-bottom:1px solid #f0f2f6;color:#9a0002;font-size:12px;vertical-align:middle}.course-table tbody tr:last-child td{border-bottom:0}.course-table tbody tr:hover{background:#fcfcff}
        .course-title-cell{display:flex;align-items:center;gap:11px;min-width:180px}.course-title-icon{display:grid;flex:0 0 auto;place-items:center;width:36px;height:36px;border-radius:11px;background:#eef2ff;color:#9a0002}.course-title-text strong{display:block;max-width:260px;overflow-wrap:anywhere;color:#382720;font-size:12px;font-weight:800}.course-title-text span{display:block;margin-top:4px;color:#9a0002;font-size:10px}.course-description{display:block;max-width:320px;overflow-wrap:anywhere;color:#9a0002;line-height:1.5}
        .course-status{display:inline-flex;align-items:center;gap:6px;padding:6px 9px;border-radius:20px;font-size:10px;font-weight:800;text-transform:capitalize;white-space:nowrap}.course-status:before{display:inline-block;width:6px;height:6px;border-radius:50%;background:currentColor;content:''}.course-status-published{background:#ecfdf3;color:#15803d}.course-status-draft{background:#fffbeb;color:#a16207}.course-status-archived{background:#f3f4f6;color:#9a0002}
        .course-row-actions{display:flex;align-items:center;gap:6px;white-space:nowrap}.course-icon-btn{display:inline-grid;place-items:center;width:32px;height:32px;border:1px solid #e8ebf1;border-radius:9px;background:#fff;color:#9a0002;text-decoration:none;cursor:pointer}.course-icon-btn:hover{border-color:#d9bcb0;background:#eef2ff;color:#9a0002}.course-icon-btn.delete:hover{border-color:#fecaca;background:#fef2f2;color:#b91c1c}.course-inline-form{display:inline-flex;margin:0}
        .course-empty{padding:42px 20px;text-align:center;color:#9a0002}.course-empty i{display:block;margin-bottom:12px;color:#c7cdd8;font-size:28px}.course-empty strong{display:block;margin-bottom:6px;color:#9a0002;font-size:13px}.course-empty span{font-size:12px}.course-panel-footer{padding:13px 20px;border-top:1px solid #edf0f4;color:#9a0002;font-size:11px}.course-footer{padding:12px 0 2px;color:#9a0002;font-size:11px;text-align:center}
        @media(max-width:1000px){.course-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.course-panel-header{align-items:flex-start;flex-direction:column}.course-search{width:100%}}
        @media(max-width:620px){.course-content{padding:20px 13px}.course-welcome{flex-direction:column}.course-date{align-self:flex-start}.course-stats{gap:10px}.course-stat{padding:15px}.course-stat-number{font-size:25px}.course-panel-header,.course-panel-body{padding:17px 15px}.course-form-grid{grid-template-columns:1fr;gap:15px}.course-field.full{grid-column:auto}.course-search{flex-wrap:wrap}.course-search-wrap{flex-basis:100%}.course-search .course-btn{flex:1}}
    </style>
<link rel="stylesheet" href="../../public/css/theme.css">
</head>
<body>
<div class="app">
    <?php require_once __DIR__ . '/../../Components/admin/navbar.php'; ?>

    <main class="main">
        <header class="topbar">
            <div class="topbar-left">
                <button class="menu-toggle" id="menuToggle" type="button" aria-label="Open navigation" aria-expanded="false">
                    <i class="fa-solid fa-bars" aria-hidden="true"></i>
                </button>
                <div class="breadcrumbs">
                    <span>Workspace</span><i class="fa-solid fa-chevron-right" aria-hidden="true"></i><strong>Manage Courses</strong>
                </div>
            </div>
            <div class="topbar-actions">
                <span class="secure-pill"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Secure admin session</span>
                <form action="logout.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= escapeOutput($_SESSION['csrf_token']) ?>">
                    <button class="top-logout" type="submit"><i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i><span>Log out</span></button>
                </form>
            </div>
        </header>

        <div class="course-content">
            <section class="course-welcome" aria-labelledby="courseTitle">
                <div>
                    <div class="course-eyebrow"><i class="fa-solid fa-book-open" aria-hidden="true"></i> COURSE MANAGEMENT</div>
                    <h1 id="courseTitle">Manage Courses</h1>
                    <p>Create courses, assign them to categories, and control their publication status.</p>
                </div>
                <div class="course-date"><i class="fa-regular fa-calendar" aria-hidden="true"></i><?= escapeOutput(date('l, F j, Y')) ?></div>
            </section>

            <section class="course-stats" aria-label="Course statistics">
                <article class="course-stat"><div class="course-stat-label">Total courses</div><div class="course-stat-number"><?= number_format((int) $stats['total_courses']) ?></div><div class="course-stat-foot">All courses in the system</div></article>
                <article class="course-stat"><div class="course-stat-label">Published</div><div class="course-stat-number"><?= number_format((int) $stats['published_courses']) ?></div><div class="course-stat-foot">Available to students</div></article>
                <article class="course-stat"><div class="course-stat-label">Drafts</div><div class="course-stat-number"><?= number_format((int) $stats['draft_courses']) ?></div><div class="course-stat-foot">Not published yet</div></article>
                <article class="course-stat"><div class="course-stat-label">Archived</div><div class="course-stat-number"><?= number_format((int) $stats['archived_courses']) ?></div><div class="course-stat-foot">Retained for records</div></article>
            </section>

            <section class="course-panel" aria-labelledby="courseFormTitle">
                <div class="course-panel-header">
                    <div>
                        <h2 id="courseFormTitle"><?= $showEditForm ? 'Update course' : 'Add a new course' ?></h2>
                        <p><?= $showEditForm ? 'Update the course details and save your changes.' : 'Complete the form below and choose a category from your existing categories.' ?></p>
                    </div>
                    <?php if ($showEditForm): ?>
                        <a class="course-btn course-btn-light" href="manage_courses.php<?= $searchTerm !== '' ? '?q=' . rawurlencode($searchTerm) : '' ?>"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add new instead</a>
                    <?php endif; ?>
                </div>
                <div class="course-panel-body">
                    <?php if (!$categories): ?>
                        <p class="course-help">No course categories were found. Add a category in Categories before creating a course.</p>
                    <?php endif; ?>
                    <form id="courseForm" method="post" action="manage_courses.php<?= $searchTerm !== '' ? '?q=' . rawurlencode($searchTerm) : '' ?>" autocomplete="off" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= escapeOutput($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="action" value="<?= escapeOutput($formAction) ?>">
                        <input type="hidden" name="return_q" value="<?= escapeOutput($searchTerm) ?>">
                        <?php if ($showEditForm): ?><input type="hidden" name="id" value="<?= escapeOutput($formValues['id']) ?>"><?php endif; ?>
                        <div class="course-form-grid">
                            <div class="course-field">
                                <label for="courseTitleInput">Course title <span class="required">*</span></label>
                                <input id="courseTitleInput" name="title" type="text" maxlength="150" required value="<?= escapeOutput($formValues['title']) ?>" placeholder="e.g. Introduction to Web Development">
                                <span class="course-help">Maximum 150 characters.</span>
                            </div>
                            <div class="course-field">
                                <label for="courseCategory">Course category <span class="required">*</span></label>
                                <select id="courseCategory" name="category_id" required <?= !$categories ? 'disabled' : '' ?>>
                                    <option value="">Select a category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= (int) $category['id'] ?>" <?= (string) $formValues['category_id'] === (string) $category['id'] ? 'selected' : '' ?>>
                                            <?= escapeOutput($category['name']) ?><?= ($category['status'] ?? '') !== 'active' ? ' (Inactive)' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="course-help">Choose the category this course belongs to.</span>
                            </div>
                            <div class="course-field full">
                                <label for="courseDescription">Description</label>
                                <textarea id="courseDescription" name="description" maxlength="5000" rows="4" placeholder="Describe what students will learn in this course."><?= escapeOutput($formValues['description']) ?></textarea>
                                <span class="course-help">Optional. Maximum 5,000 characters.</span>
                            </div>
                            <div class="course-field">
                                <label for="courseStatus">Status <span class="required">*</span></label>
                                <select id="courseStatus" name="status" required>
                                    <option value="draft" <?= $formValues['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                                    <option value="published" <?= $formValues['status'] === 'published' ? 'selected' : '' ?>>Published</option>
                                    <option value="archived" <?= $formValues['status'] === 'archived' ? 'selected' : '' ?>>Archived</option>
                                </select>
                                <span class="course-help">Published courses can be shown to students by your public course page.</span>
                            </div>
                        </div>
                        <div class="course-actions">
                            <button class="course-btn course-btn-primary" type="submit" <?= !$categories ? 'disabled' : '' ?>><i class="fa-solid <?= $showEditForm ? 'fa-floppy-disk' : 'fa-plus' ?>" aria-hidden="true"></i><?= $showEditForm ? 'Save changes' : 'Add course' ?></button>
                            <?php if ($showEditForm || ($isPost && $formError !== '')): ?>
                                <a class="course-btn course-btn-light" href="manage_courses.php<?= $searchTerm !== '' ? '?q=' . rawurlencode($searchTerm) : '' ?>">Cancel</a>
                            <?php else: ?>
                                <button class="course-btn course-btn-light" type="reset"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Clear form</button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </section>

            <section class="course-panel" aria-labelledby="courseListTitle">
                <div class="course-panel-header">
                    <div><h2 id="courseListTitle">All courses</h2><p>Search courses, review their categories and statuses, or update existing records.</p></div>
                    <form class="course-search" method="get" action="manage_courses.php" role="search">
                        <div class="course-search-wrap"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><input type="search" name="q" value="<?= escapeOutput($searchTerm) ?>" placeholder="Search title, description, category..." aria-label="Search courses"></div>
                        <button class="course-btn course-btn-primary" type="submit"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Search</button>
                        <?php if ($searchTerm !== ''): ?><a class="course-btn course-btn-light" href="manage_courses.php">Clear</a><?php endif; ?>
                    </form>
                </div>
                <?php if ($courses): ?>
                    <div class="course-table-wrap">
                        <table class="course-table">
                            <thead><tr><th>ID</th><th>Course</th><th>Category</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
                            <tbody>
                            <?php foreach ($courses as $course): ?>
                                <tr>
                                    <td>#<?= (int) $course['id'] ?></td>
                                    <td><div class="course-title-cell"><span class="course-title-icon"><i class="fa-solid fa-book-open" aria-hidden="true"></i></span><span class="course-title-text"><strong><?= escapeOutput($course['title']) ?></strong><span>Updated <?= escapeOutput(formatCourseDate($course['updated_at'] ?? null)) ?></span></span></div></td>
                                    <td><?= escapeOutput($course['category_name']) ?><?= ($course['category_status'] ?? '') !== 'active' ? ' (Inactive)' : '' ?></td>
                                    <td><span class="course-status course-status-<?= escapeOutput($course['status']) ?>"><?= escapeOutput($course['status']) ?></span></td>
                                    <td><?= escapeOutput(formatCourseDate($course['created_at'] ?? null)) ?></td>
                                    <td><div class="course-row-actions">
                                        <a class="course-icon-btn" href="?edit=<?= (int) $course['id'] ?><?= $searchTerm !== '' ? '&amp;q=' . rawurlencode($searchTerm) : '' ?>" title="Edit course" aria-label="Edit <?= escapeOutput($course['title']) ?>"><i class="fa-regular fa-pen-to-square" aria-hidden="true"></i> Edit</a>
                                        <form class="course-inline-form js-delete-course-form" method="post" action="manage_courses.php<?= $searchTerm !== '' ? '?q=' . rawurlencode($searchTerm) : '' ?>" data-course-title="<?= escapeOutput($course['title']) ?>">
                                            <input type="hidden" name="csrf_token" value="<?= escapeOutput($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $course['id'] ?>"><input type="hidden" name="return_q" value="<?= escapeOutput($searchTerm) ?>">
                                            <button class="course-icon-btn delete" type="submit" title="Delete course" aria-label="Delete <?= escapeOutput($course['title']) ?>"><i class="fa-regular fa-trash-can" aria-hidden="true"></i> Delete</button>
                                        </form>
                                    </div></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="course-panel-footer">Showing <?= number_format(count($courses)) ?> course<?= count($courses) === 1 ? '' : 's' ?><?= $searchTerm !== '' ? ' matching “' . escapeOutput($searchTerm) . '”' : '' ?>.</div>
                <?php else: ?>
                    <div class="course-empty"><i class="fa-regular fa-folder-open" aria-hidden="true"></i><strong><?= $searchTerm !== '' ? 'No matching courses found' : 'No courses have been added yet' ?></strong><span><?= $searchTerm !== '' ? 'Try another search term or clear your search.' : 'Use the form above to add your first course.' ?></span></div>
                <?php endif; ?>
            </section>
            <footer class="course-footer">&copy; <?= date('Y') ?> Student Management System. All rights reserved.</footer>
        </div>
    </main>
</div>
<script>
    const courseSwalOptions = {
        customClass: {
            popup: 'login-swal-popup',
            confirmButton: 'login-swal-confirm',
            cancelButton: 'login-swal-cancel'
        },
        buttonsStyling: false,
        reverseButtons: true
    };

    const courseServerAlerts = <?= json_encode($sweetAlerts, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
    async function showCourseServerAlerts() {
        for (const alertItem of courseServerAlerts) {
            await Swal.fire({
                ...courseSwalOptions,
                icon: alertItem.icon,
                title: alertItem.title,
                text: alertItem.text,
                confirmButtonText: 'OK'
            });
        }
    }
    void showCourseServerAlerts();

    const courseForm = document.getElementById('courseForm');
    if (courseForm) {
        courseForm.addEventListener('submit', async function (event) {
            const titleInput = document.getElementById('courseTitleInput');
            const categoryInput = document.getElementById('courseCategory');
            const title = titleInput.value.trim();
            let message = '';
            let invalidInput = null;

            if (title === '') {
                message = 'Please enter a course title.';
                invalidInput = titleInput;
            } else if (title.length > 150) {
                message = 'The course title cannot exceed 150 characters.';
                invalidInput = titleInput;
            } else if (!categoryInput.value) {
                message = 'Please select a course category.';
                invalidInput = categoryInput;
            }

            if (message !== '') {
                event.preventDefault();
                await Swal.fire({ ...courseSwalOptions, icon: 'warning', title: 'Check the form', text: message, confirmButtonText: 'OK' });
                invalidInput.focus();
            } else {
                titleInput.value = title;
                document.getElementById('courseDescription').value = document.getElementById('courseDescription').value.trim();
            }
        });
    }

    document.querySelectorAll('.js-delete-course-form').forEach(function (deleteForm) {
        deleteForm.addEventListener('submit', async function (event) {
            event.preventDefault();
            if (deleteForm.dataset.confirmed === 'true') return;

            const title = deleteForm.dataset.courseTitle || 'this course';
            const result = await Swal.fire({
                ...courseSwalOptions,
                icon: 'warning',
                title: 'Delete course?',
                text: `Are you sure you want to delete "${title}"? This action cannot be undone.`,
                showCancelButton: true,
                confirmButtonText: 'Yes, delete it',
                cancelButtonText: 'Cancel',
                focusCancel: true
            });

            if (result.isConfirmed) {
                deleteForm.dataset.confirmed = 'true';
                deleteForm.submit();
            }
        });
    });
</script>
</body>
</html>
