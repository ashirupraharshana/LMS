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

function formatCategoryDate(mixed $value): string
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

function categoryStringLength(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function redirectToCategories(string $searchTerm = ''): never
{
    $url = 'course_categories.php';
    if ($searchTerm !== '') {
        $url .= '?q=' . rawurlencode($searchTerm);
    }
    header('Location: ' . $url);
    exit;
}

function setCategoryFlash(string $type, string $message): void
{
    $_SESSION['course_category_flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

$adminName = trim((string) ($_SESSION['full_name'] ?? 'Administrator'));
if ($adminName === '') {
    $adminName = 'Administrator';
}
$firstName = preg_split('/\s+/', $adminName)[0] ?? 'Administrator';

$pdo = null;
$pageError = '';
$formError = '';
$searchTerm = trim((string) ($_GET['q'] ?? ''));
$formAction = 'add';
$showEditForm = false;
$editCategory = null;
$viewCategory = null;
$categories = [];
$stats = [
    'total_categories' => 0,
    'active_categories' => 0,
    'inactive_categories' => 0,
];
$formValues = [
    'id' => '',
    'name' => '',
    'description' => '',
    'status' => 'active',
];

$flash = $_SESSION['course_category_flash'] ?? null;
unset($_SESSION['course_category_flash']);
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
} catch (Throwable $e) {
    error_log('Course categories connection error: ' . $e->getMessage());
    $pageError = 'The category data could not be loaded. Check the database connection and try again.';
    $pdo = null;
}

$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$postAction = $isPost ? (string) ($_POST['action'] ?? '') : '';

if ($isPost) {
    $searchTerm = trim((string) ($_POST['return_q'] ?? $searchTerm));

    if (in_array($postAction, ['add', 'update'], true)) {
        $formValues = [
            'id' => trim((string) ($_POST['id'] ?? '')),
            'name' => trim((string) ($_POST['name'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'status' => (string) ($_POST['status'] ?? 'active'),
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
                $name = $formValues['name'];
                $description = $formValues['description'];
                $status = $formValues['status'];

                if ($postAction === 'update' && $id === false) {
                    $formError = 'The category ID is invalid. Please select the category again.';
                } elseif ($name === '') {
                    $formError = 'Please enter a category name.';
                } elseif (categoryStringLength($name) > 100) {
                    $formError = 'The category name cannot exceed 100 characters.';
                } elseif (categoryStringLength($description) > 255) {
                    $formError = 'The description cannot exceed 255 characters.';
                } elseif (!in_array($status, ['active', 'inactive'], true)) {
                    $formError = 'Please select a valid category status.';
                } else {
                    $descriptionValue = $description === '' ? null : $description;

                    if ($postAction === 'add') {
                        $stmt = $pdo->prepare(
                            'INSERT INTO course_categories (name, description, status)
                             VALUES (:name, :description, :status)'
                        );
                        $stmt->execute([
                            ':name' => $name,
                            ':description' => $descriptionValue,
                            ':status' => $status,
                        ]);
                        setCategoryFlash('success', 'Course category added successfully.');
                    } else {
                        $stmt = $pdo->prepare(
                            'UPDATE course_categories
                             SET name = :name, description = :description, status = :status
                             WHERE id = :id'
                        );
                        $stmt->execute([
                            ':name' => $name,
                            ':description' => $descriptionValue,
                            ':status' => $status,
                            ':id' => $id,
                        ]);

                        if ($stmt->rowCount() === 0) {
                            $checkStmt = $pdo->prepare('SELECT id FROM course_categories WHERE id = :id');
                            $checkStmt->execute([':id' => $id]);
                            if (!$checkStmt->fetchColumn()) {
                                $formError = 'This category no longer exists. Refresh the list and try again.';
                            }
                        }

                        if ($formError === '') {
                            setCategoryFlash('success', 'Course category updated successfully.');
                        }
                    }

                    if ($formError === '') {
                        redirectToCategories($searchTerm);
                    }
                }
            } elseif ($postAction === 'delete') {
                $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($id === false) {
                    $formError = 'The category ID is invalid.';
                } else {
                    $stmt = $pdo->prepare('DELETE FROM course_categories WHERE id = :id');
                    $stmt->execute([':id' => $id]);

                    if ($stmt->rowCount() === 0) {
                        setCategoryFlash('error', 'The category was not found or has already been deleted.');
                    } else {
                        setCategoryFlash('success', 'Course category deleted successfully.');
                    }
                    redirectToCategories($searchTerm);
                }
            } else {
                $formError = 'Invalid request. Please try again.';
            }
        } catch (PDOException $e) {
            error_log('Course category operation error: ' . $e->getMessage());
            $mysqlErrorCode = (int) ($e->errorInfo[1] ?? 0);

            if ($mysqlErrorCode === 1062) {
                $formError = 'A category with this name already exists. Please use a different name.';
            } elseif ($postAction === 'delete' && (($e->errorInfo[0] ?? '') === '23000')) {
                $formError = 'This category cannot be deleted because it may be linked to existing courses.';
            } else {
                $formError = 'The operation could not be completed. Please check the category details and try again.';
            }
        } catch (Throwable $e) {
            error_log('Course category operation error: ' . $e->getMessage());
            $formError = 'An unexpected error occurred. Please try again.';
        }
    }
}

if (!$isPost && isset($_GET['edit'])) {
    $editId = filter_var($_GET['edit'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($editId === false) {
        $formError = 'The selected category ID is invalid.';
    } elseif ($pdo instanceof PDO) {
        try {
            $stmt = $pdo->prepare('SELECT id, name, description, status FROM course_categories WHERE id = :id');
            $stmt->execute([':id' => $editId]);
            $editCategory = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

            if ($editCategory) {
                $formValues = [
                    'id' => (string) $editCategory['id'],
                    'name' => (string) $editCategory['name'],
                    'description' => (string) ($editCategory['description'] ?? ''),
                    'status' => (string) $editCategory['status'],
                ];
                $formAction = 'update';
                $showEditForm = true;
            } else {
                $formError = 'The category you selected could not be found.';
            }
        } catch (Throwable $e) {
            error_log('Course category edit load error: ' . $e->getMessage());
            $pageError = 'The selected category could not be loaded.';
        }
    }
}

if (!$isPost && isset($_GET['view'])) {
    $viewId = filter_var($_GET['view'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($viewId !== false && $pdo instanceof PDO) {
        try {
            $stmt = $pdo->prepare(
                'SELECT id, name, description, status, created_at, updated_at
                 FROM course_categories WHERE id = :id'
            );
            $stmt->execute([':id' => $viewId]);
            $viewCategory = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if (!$viewCategory) {
                $formError = 'The category details could not be found.';
            }
        } catch (Throwable $e) {
            error_log('Course category detail load error: ' . $e->getMessage());
            $pageError = 'The category details could not be loaded.';
        }
    }
}

if ($pdo instanceof PDO) {
    try {
        $statsStmt = $pdo->query(
            "SELECT
                COUNT(*) AS total_categories,
                COALESCE(SUM(status = 'active'), 0) AS active_categories,
                COALESCE(SUM(status = 'inactive'), 0) AS inactive_categories
             FROM course_categories"
        );
        $stats = array_merge($stats, $statsStmt->fetch(PDO::FETCH_ASSOC) ?: []);

        if ($searchTerm !== '') {
            $stmt = $pdo->prepare(
                'SELECT id, name, description, status, created_at, updated_at
                 FROM course_categories
                 WHERE name LIKE :name_search OR description LIKE :description_search
                 ORDER BY created_at DESC, id DESC'
            );
            $likeSearch = '%' . $searchTerm . '%';
            $stmt->bindValue(':name_search', $likeSearch, PDO::PARAM_STR);
            $stmt->bindValue(':description_search', $likeSearch, PDO::PARAM_STR);
            $stmt->execute();
        } else {
            $stmt = $pdo->query(
                'SELECT id, name, description, status, created_at, updated_at
                 FROM course_categories
                 ORDER BY created_at DESC, id DESC'
            );
        }
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('Course category list error: ' . $e->getMessage());
        $pageError = 'Course categories could not be displayed. Please refresh the page.';
    }
}

// Queue all server-side success/error messages for SweetAlert2 instead of inline alerts.
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
    <meta name="theme-color" content="#111827">
    <title>Course Categories | Student Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="../../Components/admin/style/navbar.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* SweetAlert2 premium styling */
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
            background: #4f46e5 !important;
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
            color: #374151 !important;
            font-size: 13px !important;
            font-weight: 700 !important;
        }

        :root {
            --cc-primary: #4f46e5;
            --cc-primary-dark: #3730a3;
            --cc-text: #172033;
            --cc-muted: #6b7280;
            --cc-border: #e5e7eb;
            --cc-bg: #f6f7fb;
            --cc-card: #ffffff;
            --cc-green: #15803d;
            --cc-red: #b91c1c;
        }

        .category-content {
            width: 100%;
            max-width: 1500px;
            margin: 0 auto;
            padding: 28px clamp(16px, 2.5vw, 36px);
            color: var(--cc-text);
        }

        .category-welcome-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 18px;
            margin-bottom: 25px;
        }

        .category-eyebrow {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            color: var(--cc-primary);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .12em;
        }

        .category-welcome-row h1 {
            margin: 0;
            color: #111827;
            font-size: clamp(25px, 3vw, 32px);
            line-height: 1.2;
            letter-spacing: -.035em;
        }

        .category-welcome-row p {
            margin: 9px 0 0;
            color: var(--cc-muted);
            font-size: 14px;
            line-height: 1.6;
        }

        .category-date-chip {
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 10px 13px;
            border: 1px solid var(--cc-border);
            border-radius: 12px;
            background: var(--cc-card);
            color: #4b5563;
            font-size: 12px;
            font-weight: 600;
        }

        .category-stats-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 22px;
        }

        .category-stat-card {
            position: relative;
            overflow: hidden;
            padding: 19px 20px;
            border: 1px solid #edf0f5;
            border-radius: 16px;
            background: var(--cc-card);
            box-shadow: 0 4px 18px rgba(15, 23, 42, .035);
        }
        .category-stat-top { display: flex; justify-content: space-between; align-items: center; gap: 12px; }
        .category-stat-label { color: var(--cc-muted); font-size: 12px; font-weight: 700; }
        .category-stat-icon {
            display: grid;
            place-items: center;
            width: 39px;
            height: 39px;
            border-radius: 12px;
            color: var(--stat-color, #4f46e5);
            background: var(--stat-bg, #eef2ff);
            font-size: 15px;
        }
        .category-stat-number { margin-top: 10px; color: #111827; font-size: 29px; font-weight: 800; letter-spacing: -.04em; }
        .category-stat-foot { margin-top: 4px; color: #8a93a3; font-size: 11px; }

        .category-panel {
            min-width: 0;
            margin-bottom: 22px;
            overflow: hidden;
            border: 1px solid #e9ecf2;
            border-radius: 16px;
            background: var(--cc-card);
            box-shadow: 0 5px 20px rgba(15, 23, 42, .035);
        }
        .category-panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            padding: 20px 22px;
            border-bottom: 1px solid #edf0f4;
        }
        .category-panel-header h2 { margin: 0; color: #1f2937; font-size: 16px; font-weight: 800; }
        .category-panel-header p { margin: 5px 0 0; color: var(--cc-muted); font-size: 12px; line-height: 1.5; }
        .category-panel-body { padding: 22px; }
        .category-panel-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 32px;
            height: 30px;
            padding: 0 9px;
            border-radius: 9px;
            background: #eef2ff;
            color: #4338ca;
            font-size: 12px;
            font-weight: 800;
        }

        .category-form-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 18px 20px; }
        .category-field { display: flex; flex-direction: column; gap: 7px; min-width: 0; }
        .category-field.full-width { grid-column: 1 / -1; }
        .category-field label { color: #374151; font-size: 12px; font-weight: 700; }
        .category-field label .required { color: #dc2626; }
        .category-field input, .category-field select, .category-field textarea {
            width: 100%;
            min-height: 43px;
            padding: 11px 12px;
            border: 1px solid #dfe3eb;
            border-radius: 10px;
            outline: none;
            background: #fff;
            color: #1f2937;
            font: inherit;
            font-size: 13px;
            transition: border-color .18s ease, box-shadow .18s ease;
        }
        .category-field textarea { min-height: 86px; resize: vertical; }
        .category-field input:focus, .category-field select:focus, .category-field textarea:focus {
            border-color: #818cf8;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, .12);
        }
        .category-field .field-help { color: #8a93a3; font-size: 11px; }
        .category-form-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-top: 20px; }
        .category-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 40px;
            padding: 10px 15px;
            border: 1px solid transparent;
            border-radius: 10px;
            cursor: pointer;
            text-decoration: none;
            font: inherit;
            font-size: 12px;
            font-weight: 750;
            transition: background .18s ease, border-color .18s ease, transform .18s ease;
        }
        .category-btn:hover { transform: translateY(-1px); }
        .category-btn-primary { background: var(--cc-primary); color: #fff; }
        .category-btn-primary:hover { background: var(--cc-primary-dark); }
        .category-btn-light { border-color: #dfe3eb; background: #fff; color: #4b5563; }
        .category-btn-light:hover { background: #f8fafc; }
        .category-btn-danger { border-color: #fee2e2; background: #fff; color: #b91c1c; }
        .category-btn-danger:hover { background: #fef2f2; }

        .category-search-form { display: flex; width: min(100%, 450px); align-items: center; gap: 8px; }
        .category-search-input-wrap { position: relative; flex: 1; min-width: 100px; }
        .category-search-input-wrap i { position: absolute; top: 50%; left: 13px; transform: translateY(-50%); color: #9ca3af; font-size: 13px; }
        .category-search-input { width: 100%; height: 40px; padding: 9px 12px 9px 36px; border: 1px solid #e0e4eb; border-radius: 10px; outline: none; background: #fff; color: #1f2937; font: inherit; font-size: 12px; }
        .category-search-input:focus { border-color: #818cf8; box-shadow: 0 0 0 3px rgba(99, 102, 241, .10); }
        .category-table-wrap { width: 100%; overflow-x: auto; }
        .category-table { width: 100%; border-collapse: collapse; text-align: left; }
        .category-table th { padding: 13px 16px; border-bottom: 1px solid #e9edf3; background: #fafbfe; color: #8a93a3; font-size: 10px; font-weight: 800; letter-spacing: .07em; text-transform: uppercase; white-space: nowrap; }
        .category-table td { padding: 15px 16px; border-bottom: 1px solid #f0f2f6; color: #4b5563; font-size: 12px; vertical-align: middle; }
        .category-table tbody tr:last-child td { border-bottom: 0; }
        .category-table tbody tr:hover { background: #fcfcff; }
        .category-id { color: #9ca3af; font-size: 11px; font-weight: 700; }
        .category-name-cell { display: flex; align-items: center; gap: 11px; min-width: 180px; }
        .category-name-icon { display: grid; flex: 0 0 auto; place-items: center; width: 36px; height: 36px; border-radius: 11px; background: #eef2ff; color: #4f46e5; }
        .category-name-text { min-width: 0; }
        .category-name-text strong { display: block; overflow-wrap: anywhere; color: #1f2937; font-size: 12px; font-weight: 800; }
        .category-name-text span { display: block; margin-top: 4px; color: #9ca3af; font-size: 10px; }
        .category-description { display: block; max-width: 300px; color: #6b7280; line-height: 1.5; overflow-wrap: anywhere; }
        .category-status { display: inline-flex; align-items: center; gap: 6px; padding: 6px 9px; border-radius: 20px; font-size: 10px; font-weight: 800; text-transform: capitalize; white-space: nowrap; }
        .category-status::before { display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: currentColor; content: ''; }
        .category-status-active { background: #ecfdf3; color: #15803d; }
        .category-status-inactive { background: #f3f4f6; color: #6b7280; }
        .category-row-actions { display: flex; align-items: center; gap: 6px; white-space: nowrap; }
        .category-icon-btn { display: inline-grid; place-items: center; width: 32px; height: 32px; border: 1px solid #e8ebf1; border-radius: 9px; background: #fff; color: #6b7280; text-decoration: none; cursor: pointer; }
        .category-icon-btn:hover { border-color: #c7d2fe; background: #eef2ff; color: #4338ca; }
        .category-icon-btn.delete:hover { border-color: #fecaca; background: #fef2f2; color: #b91c1c; }
        .category-inline-form { display: inline-flex; margin: 0; }
        .category-empty-state { padding: 42px 20px; text-align: center; color: #9ca3af; }
        .category-empty-state i { display: block; margin-bottom: 12px; color: #c7cdd8; font-size: 28px; }
        .category-empty-state strong { display: block; margin-bottom: 6px; color: #4b5563; font-size: 13px; }
        .category-empty-state span { font-size: 12px; }
        .category-detail-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; }
        .category-detail-item { min-width: 0; }
        .category-detail-label { margin-bottom: 7px; color: #8a93a3; font-size: 10px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
        .category-detail-value { color: #263244; font-size: 13px; font-weight: 650; overflow-wrap: anywhere; }
        .category-detail-description { grid-column: 1 / -1; }
        .category-panel-footer { padding: 13px 20px; border-top: 1px solid #edf0f4; color: #9ca3af; font-size: 11px; }
        .category-footer { padding: 12px 0 2px; color: #9ca3af; font-size: 11px; text-align: center; }

        @media (max-width: 900px) {
            .category-panel-header { align-items: flex-start; flex-direction: column; }
            .category-search-form { width: 100%; }
            .category-detail-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 620px) {
            .category-content { padding: 20px 13px; }
            .category-welcome-row { flex-direction: column; }
            .category-date-chip { align-self: flex-start; }
            .category-stats-grid { grid-template-columns: 1fr; gap: 10px; }
            .category-stat-card { padding: 15px 16px; }
            .category-stat-number { font-size: 25px; }
            .category-panel-header, .category-panel-body { padding: 17px 15px; }
            .category-form-grid { grid-template-columns: 1fr; gap: 15px; }
            .category-field.full-width { grid-column: auto; }
            .category-detail-grid { grid-template-columns: 1fr; }
            .category-detail-description { grid-column: auto; }
            .category-search-form { flex-wrap: wrap; }
            .category-search-input-wrap { flex-basis: 100%; }
            .category-search-form .category-btn { flex: 1; }
        }
    </style>
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
                    <span>Workspace</span>
                    <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                    <strong>Course Categories</strong>
                </div>
            </div>
            <div class="topbar-actions">
                <span class="secure-pill"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Secure admin session</span>
                <form action="logout.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= escapeOutput($_SESSION['csrf_token']) ?>">
                    <button class="top-logout" type="submit">
                        <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>
                        <span>Log out</span>
                    </button>
                </form>
            </div>
        </header>

        <div class="category-content">
            <section class="category-welcome-row" aria-labelledby="categoryTitle">
                <div>
                    <div class="category-eyebrow"><i class="fa-solid fa-layer-group" aria-hidden="true"></i> COURSE MANAGEMENT</div>
                    <h1 id="categoryTitle">Course Categories</h1>
                    <p>Create, organize, search, and maintain the categories used to group your courses.</p>
                </div>
                <div class="category-date-chip"><i class="fa-regular fa-calendar" aria-hidden="true"></i> <?= escapeOutput(date('l, F j, Y')) ?></div>
            </section>

            <section class="category-stats-grid" aria-label="Category statistics">
                <article class="category-stat-card">
                    <div class="category-stat-top"><span class="category-stat-label">Total categories</span><span class="category-stat-icon" style="--stat-color:#4f46e5;--stat-bg:#eef2ff"><i class="fa-solid fa-layer-group" aria-hidden="true"></i></span></div>
                    <div class="category-stat-number"><?= number_format((int) $stats['total_categories']) ?></div>
                    <div class="category-stat-foot">All course categories in the system</div>
                </article>
                <article class="category-stat-card">
                    <div class="category-stat-top"><span class="category-stat-label">Active categories</span><span class="category-stat-icon" style="--stat-color:#15803d;--stat-bg:#ecfdf3"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></span></div>
                    <div class="category-stat-number"><?= number_format((int) $stats['active_categories']) ?></div>
                    <div class="category-stat-foot">Available for active course use</div>
                </article>
                <article class="category-stat-card">
                    <div class="category-stat-top"><span class="category-stat-label">Inactive categories</span><span class="category-stat-icon" style="--stat-color:#b45309;--stat-bg:#fffbeb"><i class="fa-solid fa-circle-pause" aria-hidden="true"></i></span></div>
                    <div class="category-stat-number"><?= number_format((int) $stats['inactive_categories']) ?></div>
                    <div class="category-stat-foot">Currently marked as inactive</div>
                </article>
            </section>

            <?php if ($viewCategory): ?>
                <section class="category-panel" aria-labelledby="categoryDetailsTitle">
                    <div class="category-panel-header">
                        <div><h2 id="categoryDetailsTitle">Category details</h2><p>Full information for the selected course category.</p></div>
                        <a class="category-btn category-btn-light" href="course_categories.php<?= $searchTerm !== '' ? '?q=' . rawurlencode($searchTerm) : '' ?>"><i class="fa-solid fa-xmark" aria-hidden="true"></i> Close details</a>
                    </div>
                    <div class="category-panel-body">
                        <div class="category-detail-grid">
                            <div class="category-detail-item"><div class="category-detail-label">Category ID</div><div class="category-detail-value">#<?= (int) $viewCategory['id'] ?></div></div>
                            <div class="category-detail-item"><div class="category-detail-label">Category name</div><div class="category-detail-value"><?= escapeOutput($viewCategory['name']) ?></div></div>
                            <div class="category-detail-item"><div class="category-detail-label">Status</div><div class="category-detail-value"><span class="category-status category-status-<?= escapeOutput($viewCategory['status']) ?>"><?= escapeOutput($viewCategory['status']) ?></span></div></div>
                            <div class="category-detail-item category-detail-description"><div class="category-detail-label">Description</div><div class="category-detail-value"><?= nl2br(escapeOutput($viewCategory['description'] ?: 'No description provided.')) ?></div></div>
                            <div class="category-detail-item"><div class="category-detail-label">Created at</div><div class="category-detail-value"><?= escapeOutput(formatCategoryDate($viewCategory['created_at'])) ?></div></div>
                            <div class="category-detail-item"><div class="category-detail-label">Last updated</div><div class="category-detail-value"><?= escapeOutput(formatCategoryDate($viewCategory['updated_at'])) ?></div></div>
                        </div>
                    </div>
                    <div class="category-panel-footer">Use the Edit action in the category list to change these details.</div>
                </section>
            <?php endif; ?>

            <section class="category-panel" aria-labelledby="categoryFormTitle">
                <div class="category-panel-header">
                    <div>
                        <h2 id="categoryFormTitle"><?= $showEditForm ? 'Update category' : 'Add a new category' ?></h2>
                        <p><?= $showEditForm ? 'Change the category information and save your updates.' : 'Enter a name, optional description, and status for the new category.' ?></p>
                    </div>
                    <?php if ($showEditForm): ?>
                        <a class="category-btn category-btn-light" href="course_categories.php<?= $searchTerm !== '' ? '?q=' . rawurlencode($searchTerm) : '' ?>"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add new instead</a>
                    <?php else: ?>
                        <span class="category-panel-count"><i class="fa-solid fa-plus" aria-hidden="true"></i>&nbsp; New</span>
                    <?php endif; ?>
                </div>
                <div class="category-panel-body">
                    <form id="categoryForm" method="post" action="course_categories.php<?= $searchTerm !== '' ? '?q=' . rawurlencode($searchTerm) : '' ?>" autocomplete="off" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= escapeOutput($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="action" value="<?= escapeOutput($formAction) ?>">
                        <input type="hidden" name="return_q" value="<?= escapeOutput($searchTerm) ?>">
                        <?php if ($showEditForm): ?>
                            <input type="hidden" name="id" value="<?= escapeOutput($formValues['id']) ?>">
                        <?php endif; ?>

                        <div class="category-form-grid">
                            <div class="category-field">
                                <label for="categoryName">Category name <span class="required">*</span></label>
                                <input id="categoryName" name="name" type="text" maxlength="100" required value="<?= escapeOutput($formValues['name']) ?>" placeholder="e.g. Web Development">
                                <span class="field-help">Maximum 100 characters. Category names must be unique.</span>
                            </div>
                            <div class="category-field">
                                <label for="categoryStatus">Status <span class="required">*</span></label>
                                <select id="categoryStatus" name="status" required>
                                    <option value="active" <?= $formValues['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                    <option value="inactive" <?= $formValues['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                </select>
                                <span class="field-help">Inactive categories remain in the database.</span>
                            </div>
                            <div class="category-field full-width">
                                <label for="categoryDescription">Description <span style="font-weight:500;color:#9ca3af">(optional)</span></label>
                                <textarea id="categoryDescription" name="description" maxlength="255" rows="3" placeholder="Briefly describe the courses in this category."><?= escapeOutput($formValues['description']) ?></textarea>
                                <span class="field-help">Maximum 255 characters.</span>
                            </div>
                        </div>
                        <div class="category-form-actions">
                            <button class="category-btn category-btn-primary" type="submit"><i class="fa-solid <?= $showEditForm ? 'fa-floppy-disk' : 'fa-plus' ?>" aria-hidden="true"></i><?= $showEditForm ? 'Save changes' : 'Add category' ?></button>
                            <?php if ($showEditForm || ($isPost && $postAction === 'add' && $formError !== '')): ?>
                                <a class="category-btn category-btn-light" href="course_categories.php<?= $searchTerm !== '' ? '?q=' . rawurlencode($searchTerm) : '' ?>">Cancel</a>
                            <?php else: ?>
                                <button class="category-btn category-btn-light" type="reset"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Clear form</button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </section>

            <section class="category-panel" aria-labelledby="categoryListTitle">
                <div class="category-panel-header">
                    <div><h2 id="categoryListTitle">All course categories</h2><p>View category details, make changes, or remove a category.</p></div>
                    <form class="category-search-form" method="get" action="course_categories.php" role="search">
                        <div class="category-search-input-wrap">
                            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                            <input class="category-search-input" type="search" name="q" value="<?= escapeOutput($searchTerm) ?>" placeholder="Search name or description..." aria-label="Search categories">
                        </div>
                        <button class="category-btn category-btn-primary" type="submit"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Search</button>
                        <?php if ($searchTerm !== ''): ?>
                            <a class="category-btn category-btn-light" href="course_categories.php" aria-label="Clear search">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>

                <?php if ($categories): ?>
                    <div class="category-table-wrap">
                        <table class="category-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Category</th>
                                    <th>Description</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td><span class="category-id">#<?= (int) $category['id'] ?></span></td>
                                    <td>
                                        <div class="category-name-cell">
                                            <span class="category-name-icon"><i class="fa-solid fa-folder-open" aria-hidden="true"></i></span>
                                            <span class="category-name-text">
                                                <strong><?= escapeOutput($category['name']) ?></strong>
                                                <span>Updated <?= escapeOutput(formatCategoryDate($category['updated_at'])) ?></span>
                                            </span>
                                        </div>
                                    </td>
                                    <td><span class="category-description"><?= escapeOutput($category['description'] ?: 'No description') ?></span></td>
                                    <td><span class="category-status category-status-<?= escapeOutput($category['status']) ?>"><?= escapeOutput($category['status']) ?></span></td>
                                    <td><?= escapeOutput(formatCategoryDate($category['created_at'])) ?></td>
                                    <td>
                                        <div class="category-row-actions">
                                            <a class="category-icon-btn" href="?view=<?= (int) $category['id'] ?><?= $searchTerm !== '' ? '&amp;q=' . rawurlencode($searchTerm) : '' ?>" title="View category" aria-label="View <?= escapeOutput($category['name']) ?>"><i class="fa-regular fa-eye" aria-hidden="true"></i></a>
                                            <a class="category-icon-btn" href="?edit=<?= (int) $category['id'] ?><?= $searchTerm !== '' ? '&amp;q=' . rawurlencode($searchTerm) : '' ?>" title="Edit category" aria-label="Edit <?= escapeOutput($category['name']) ?>"><i class="fa-regular fa-pen-to-square" aria-hidden="true"></i></a>
                                            <form class="category-inline-form js-delete-category-form" method="post" action="course_categories.php<?= $searchTerm !== '' ? '?q=' . rawurlencode($searchTerm) : '' ?>" data-category-name="<?= escapeOutput($category['name']) ?>">
                                                <input type="hidden" name="csrf_token" value="<?= escapeOutput($_SESSION['csrf_token']) ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int) $category['id'] ?>">
                                                <input type="hidden" name="return_q" value="<?= escapeOutput($searchTerm) ?>">
                                                <button class="category-icon-btn delete" type="submit" title="Delete category" aria-label="Delete <?= escapeOutput($category['name']) ?>"><i class="fa-regular fa-trash-can" aria-hidden="true"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="category-panel-footer">
                        Showing <?= number_format(count($categories)) ?> categor<?= count($categories) === 1 ? 'y' : 'ies' ?><?= $searchTerm !== '' ? ' matching “' . escapeOutput($searchTerm) . '”' : '' ?>.
                    </div>
                <?php else: ?>
                    <div class="category-empty-state">
                        <i class="fa-regular fa-folder-open" aria-hidden="true"></i>
                        <strong><?= $searchTerm !== '' ? 'No matching categories found' : 'No categories have been added yet' ?></strong>
                        <span><?= $searchTerm !== '' ? 'Try a different search term or clear your search.' : 'Use the form above to add your first course category.' ?></span>
                    </div>
                <?php endif; ?>
            </section>

            <footer class="category-footer">&copy; <?= date('Y') ?> Student Management System. All rights reserved.</footer>
        </div>
    </main>
</div>
<script>
    const categorySweetAlertOptions = {
        customClass: {
            popup: 'login-swal-popup',
            confirmButton: 'login-swal-confirm',
            cancelButton: 'login-swal-cancel'
        },
        buttonsStyling: false,
        reverseButtons: true
    };

    // Display all PHP-generated feedback through SweetAlert2.
    const categoryServerAlerts = <?= json_encode(
        $sweetAlerts,
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE
    ) ?>;

    async function showCategoryServerAlerts() {
        for (const alertItem of categoryServerAlerts) {
            await Swal.fire({
                ...categorySweetAlertOptions,
                icon: alertItem.icon,
                title: alertItem.title,
                text: alertItem.text,
                confirmButtonText: 'OK'
            });
        }
    }
    void showCategoryServerAlerts();

    // Use SweetAlert2 for category form validation instead of browser alert bubbles.
    const categoryForm = document.getElementById('categoryForm');
    if (categoryForm) {
        categoryForm.addEventListener('submit', async function (event) {
            const nameInput = document.getElementById('categoryName');
            const descriptionInput = document.getElementById('categoryDescription');
            const name = nameInput.value.trim();
            const description = descriptionInput.value.trim();
            let validationMessage = '';
            let invalidInput = null;

            if (name === '') {
                validationMessage = 'Please enter a category name.';
                invalidInput = nameInput;
            } else if (name.length > 100) {
                validationMessage = 'The category name cannot exceed 100 characters.';
                invalidInput = nameInput;
            } else if (description.length > 255) {
                validationMessage = 'The description cannot exceed 255 characters.';
                invalidInput = descriptionInput;
            }

            if (validationMessage !== '') {
                event.preventDefault();
                await Swal.fire({
                    ...categorySweetAlertOptions,
                    icon: 'warning',
                    title: 'Check the form',
                    text: validationMessage,
                    confirmButtonText: 'OK'
                });
                invalidInput.focus();
            } else {
                nameInput.value = name;
                descriptionInput.value = description;
            }
        });
    }

    // Replace window.confirm() with a SweetAlert2 delete confirmation.
    document.querySelectorAll('.js-delete-category-form').forEach(function (deleteForm) {
        deleteForm.addEventListener('submit', async function (event) {
            event.preventDefault();
            if (deleteForm.dataset.confirmed === 'true') {
                return;
            }

            const categoryName = deleteForm.dataset.categoryName || 'this category';
            const result = await Swal.fire({
                ...categorySweetAlertOptions,
                icon: 'warning',
                title: 'Delete category?',
                text: `Are you sure you want to delete "${categoryName}"? This action cannot be undone.`,
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

    // Optional mobile navigation toggle; works with the dashboard navbar's sidebar styles.
    const categoryMenuToggle = document.getElementById('menuToggle');
    if (categoryMenuToggle) {
        categoryMenuToggle.addEventListener('click', function () {
            const expanded = this.getAttribute('aria-expanded') === 'true';
            this.setAttribute('aria-expanded', String(!expanded));
            document.body.classList.toggle('sidebar-open', !expanded);
        });
    }
</script>
</body>
</html>
