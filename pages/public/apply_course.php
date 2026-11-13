<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function applyEscape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function applyLength(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

$pdo = null;
$pageError = '';
$formError = '';
$successMessage = (string) ($_SESSION['course_application_success'] ?? '');
unset($_SESSION['course_application_success']);
$fullName = '';
$email = '';
$phone = '';
$message = '';
$selectedCourse = (string) ($_POST['course_id'] ?? $_GET['course_id'] ?? '');
$courses = [];

try {
    require_once __DIR__ . '/../../config/database.php';
    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new RuntimeException('PDO database connection is unavailable.');
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $courseStmt = $pdo->query(
        "SELECT c.id, c.title, c.seat_capacity, (SELECT COUNT(*) FROM course_applications a WHERE a.course_id=c.id AND a.status='approved') AS occupied_seats FROM courses c JOIN course_categories cc ON cc.id=c.category_id WHERE c.status='published' AND cc.status='active' ORDER BY c.title ASC"
    );
    $courses = $courseStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Apply course page error: ' . $e->getMessage());
    $pageError = 'Courses are currently unavailable. Please try again later.';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $message = trim((string) ($_POST['message'] ?? ''));
    $selectedCourse = trim((string) ($_POST['course_id'] ?? ''));
    $postedToken = (string) ($_POST['csrf_token'] ?? '');

    if (!hash_equals((string) $_SESSION['csrf_token'], $postedToken)) {
        $formError = 'Your session token has expired. Refresh the page and try again.';
    } elseif (!$pdo instanceof PDO) {
        $formError = 'The database is unavailable. Your application was not submitted.';
    } elseif ($fullName === '' || applyLength($fullName) > 120) {
        $formError = 'Enter your full name (maximum 120 characters).';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        $formError = 'Enter a valid email address.';
    } elseif ($phone === '' || applyLength($phone) > 30) {
        $formError = 'Enter your phone number (maximum 30 characters).';
    } elseif ($message === '' || applyLength($message) > 5000) {
        $formError = 'Enter your application message (maximum 5,000 characters).';
    } else {
        $courseId = filter_var($selectedCourse, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($courseId === false) {
            $formError = 'Please select a course.';
        } else {
            try {
                $pdo->beginTransaction();
                $checkCourse = $pdo->prepare("SELECT c.id, c.seat_capacity FROM courses c JOIN course_categories cc ON cc.id=c.category_id WHERE c.id=:id AND c.status='published' AND cc.status='active' FOR UPDATE");
                $checkCourse->execute([':id' => $courseId]);
                $course = $checkCourse->fetch(PDO::FETCH_ASSOC);
                if (!$course) {
                    $formError = 'That course is not available for applications. Please select another course.';
                } else {
                    $filledStmt = $pdo->prepare("SELECT COUNT(*) FROM course_applications WHERE course_id = :id AND status = 'approved'");
                    $filledStmt->execute([':id'=>$courseId]);
                    $filled = (int)$filledStmt->fetchColumn();
                    if ($filled >= (int)$course['seat_capacity']) {
                        $formError = 'This course has reached its seat limit. Please select another course.';
                    } else {
                        $duplicate = $pdo->prepare("SELECT id FROM course_applications WHERE course_id=:course_id AND email=:email AND status IN ('pending','approved') LIMIT 1");
                        $duplicate->execute([':course_id'=>$courseId, ':email'=>$email]);
                        if ($duplicate->fetchColumn()) {
                            $formError = 'You already have an application for this course.';
                        } else {
                            $insert = $pdo->prepare('INSERT INTO course_applications (course_id, full_name, email, phone, message) VALUES (:course_id,:full_name,:email,:phone,:message)');
                            $insert->execute([':course_id'=>$courseId, ':full_name'=>$fullName, ':email'=>$email, ':phone'=>$phone, ':message'=>$message]);
                            $pdo->commit();
                            $_SESSION['course_application_success'] = 'Your course application has been submitted. The administrator will review it.';
                            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                            header('Location: apply_course.php');
                            exit;
                        }
                    }
                }
                if ($pdo->inTransaction()) $pdo->rollBack();
            } catch (Throwable $e) {
                if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
                error_log('Course application insert error: ' . $e->getMessage());
                $formError = 'Your application could not be saved. Please try again later.';
            }
        }
    }
}

// Check availability after the data has been loaded.
$hasOpenSeats = false;
foreach ($courses as $courseItem) {
    if ((int) $courseItem['occupied_seats'] < (int) $courseItem['seat_capacity']) {
        $hasOpenSeats = true;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#141414">
    <title>Course Application | LMS Student Portal</title>
    <link rel="stylesheet" href="../../public/vendor/fontawesome.css">
    <link rel="stylesheet" href="../../Components/public/css/navbar.css">
    <link rel="stylesheet" href="../../public/css/theme.css">
    <script src="../../public/vendor/sweetalert2.all.min.js" defer></script>
    <style>
        /* Scoped styles: deliberately avoid theme.css .page, .card, .btn, and .field. */
        .apply-screen {
            --apply-black: #151515;
            --apply-white: #fff;
            --apply-gray: #737373;
            --apply-border: #e7e7e7;
            width: min(1240px, calc(100% - 48px));
            margin: 0 auto;
            padding: 28px 0 36px;
            color: var(--apply-black);
        }
        .apply-screen * { box-sizing: border-box; }
        .apply-screen .apply-breadcrumb {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin: 0 0 25px;
            color: #777;
            font-size: 12px;
            font-weight: 600;
        }
        .apply-screen .apply-breadcrumb a { color: #5d5d5d; text-decoration: none; }
        .apply-screen .apply-breadcrumb a:hover { color: #111; text-decoration: underline; }
        .apply-screen .apply-breadcrumb .apply-current { color: #171717; }
        .apply-screen .apply-layout {
            display: grid;
            grid-template-columns: minmax(0, 0.83fr) minmax(0, 1.17fr);
            align-items: stretch;
            min-height: 680px;
            background: #fff;
            border: 1px solid #e1e1e1;
            border-radius: 22px;
            overflow: hidden;
            box-shadow: 0 18px 45px rgba(0,0,0,.035);
        }
        .apply-screen .apply-intro {
            display: flex;
            flex-direction: column;
            min-width: 0;
            padding: clamp(30px, 4.5vw, 58px);
            background: #151515;
            color: #f5f5f5;
            position: relative;
            isolation: isolate;
        }
        .apply-screen .apply-intro::after {
            content: '';
            pointer-events: none;
            position: absolute;
            width: 230px;
            height: 230px;
            right: -125px;
            top: -133px;
            border: 1px solid rgba(255,255,255,.16);
            border-radius: 50%;
            z-index: -1;
            box-shadow: 0 0 0 46px rgba(255,255,255,.025), 0 0 0 94px rgba(255,255,255,.018);
        }
        .apply-screen .apply-overline {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            width: max-content;
            max-width: 100%;
            color: #d4d4d4;
            font-size: 11px;
            font-weight: 750;
            letter-spacing: .15em;
            text-transform: uppercase;
        }
        .apply-screen .apply-overline::before {
            content: '';
            width: 20px;
            height: 1px;
            background: currentColor;
        }
        .apply-screen .apply-intro h1 {
            margin: 39px 0 19px;
            color: #fff !important;
            font-size: clamp(39px, 4.1vw, 58px);
            line-height: 1.06;
            letter-spacing: -.065em;
            max-width: 420px;
            font-weight: 780;
        }
        .apply-screen .apply-intro h1 span { color: #9e9e9e; }
        .apply-screen .apply-intro-copy {
            max-width: 355px;
            color: #bfbfbf !important;
            font-size: 14px;
            line-height: 1.8;
            margin: 0;
        }
        .apply-screen .apply-process { margin: 50px 0 auto; }
        .apply-screen .apply-process-label {
            margin: 0 0 16px;
            color: #969696;
            font-size: 10px;
            letter-spacing: .17em;
            text-transform: uppercase;
            font-weight: 750;
        }
        .apply-screen .apply-step {
            display: grid;
            grid-template-columns: 33px minmax(0, 1fr);
            align-items: start;
            column-gap: 14px;
            padding: 15px 0;
            border-top: 1px solid #393939;
        }
        .apply-screen .apply-step-index {
            color: #a0a0a0;
            font-size: 11px;
            font-weight: 700;
            font-variant-numeric: tabular-nums;
        }
        .apply-screen .apply-step strong { display: block; font-size: 13px; font-weight: 700; color: #fff; }
        .apply-screen .apply-step small { display: block; margin-top: 3px; color: #aaa; font-size: 12px; line-height: 1.55; }
        .apply-screen .apply-intro-footer {
            display: flex;
            align-items: center;
            gap: 9px;
            margin-top: 28px;
            padding-top: 20px;
            border-top: 1px solid #393939;
            color: #bcbcbc;
            font-size: 11px;
            line-height: 1.5;
        }
        .apply-screen .apply-intro-footer i { font-size: 14px; }
        .apply-screen .apply-content {
            min-width: 0;
            padding: clamp(28px, 4.25vw, 56px);
            background: #fff;
        }
        .apply-screen .apply-heading-row {
            display: flex;
            align-items: center;
            gap: 15px;
            justify-content: space-between;
            margin-bottom: 7px;
        }
        .apply-screen .apply-form-overline {
            color: #696969;
            text-transform: uppercase;
            letter-spacing: .15em;
            font-size: 10px;
            font-weight: 800;
        }
        .apply-screen .apply-status-pill {
            padding: 6px 10px;
            border-radius: 100px;
            border: 1px solid #e3e3e3;
            background: #f7f7f7;
            color: #505050;
            font-size: 10px;
            font-weight: 700;
            white-space: nowrap;
        }
        .apply-screen .apply-content h2 {
            color: #151515 !important;
            font-size: clamp(25px, 3vw, 35px);
            font-weight: 760;
            letter-spacing: -.05em;
            line-height: 1.16;
            margin: 0 0 10px;
        }
        .apply-screen .apply-lead {
            color: #737373 !important;
            font-size: 13px;
            line-height: 1.75;
            max-width: 535px;
            margin: 0 0 27px;
        }
        .apply-screen .apply-note {
            display: flex;
            align-items: flex-start;
            gap: 11px;
            padding: 14px 16px;
            border: 1px solid #dddddd;
            border-radius: 12px;
            background: #fafafa;
            font-size: 12px;
            line-height: 1.65;
            color: #444;
            margin-bottom: 20px;
        }
        .apply-screen .apply-note i { font-size: 15px; margin-top: 2px; }
        .apply-screen .apply-note p { color: inherit !important; font-size: inherit; margin: 0; line-height: inherit; }
        .apply-screen .apply-note strong { color: #222; }
        .apply-screen .apply-note-error { border-color: #bcbcbc; background: #f1f1f1; }
        .apply-screen .apply-empty {
            padding: 30px 24px;
            background: #fafafa;
            border: 1px dashed #cdcdcd;
            border-radius: 14px;
            text-align: center;
        }
        .apply-screen .apply-empty i { font-size: 24px; color: #555; display: block; margin-bottom: 14px; }
        .apply-screen .apply-empty h3 { color: #1a1a1a !important; font-size: 17px; margin: 0 0 8px; }
        .apply-screen .apply-empty p { margin: 0 auto 18px; max-width: 340px; color: #727272; font-size: 13px; }
        .apply-screen .apply-divider-label {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 0 0 16px;
            color: #303030;
            font-size: 12px;
            font-weight: 750;
        }
        .apply-screen .apply-divider-label::after { content: ''; flex: 1; height: 1px; background: #e8e8e8; }
        .apply-screen .apply-fields {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            column-gap: 17px;
            row-gap: 18px;
        }
        .apply-screen .apply-field { min-width: 0; margin: 0; }
        .apply-screen .apply-field-wide { grid-column: 1 / -1; }
        .apply-screen .apply-label {
            display: flex !important;
            align-items: baseline;
            gap: 4px;
            margin: 0 0 8px !important;
            font-size: 12px;
            font-weight: 700;
            color: #242424 !important;
            line-height: 1.45;
        }
        .apply-screen .apply-required { color: #515151; }
        .apply-screen .apply-control {
            display: block;
            width: 100% !important;
            min-width: 0;
            min-height: 50px !important;
            padding: 12px 14px !important;
            border: 1px solid #dcdcdc !important;
            border-radius: 9px !important;
            background: #fff !important;
            color: #202020 !important;
            box-shadow: none !important;
            outline: none;
            font-family: inherit;
            font-size: 13px;
            line-height: 1.45;
            transition: border-color .15s ease, box-shadow .15s ease;
        }
        .apply-screen .apply-control:hover { border-color: #b1b1b1 !important; }
        .apply-screen .apply-control:focus {
            border-color: #181818 !important;
            box-shadow: 0 0 0 3px rgba(0,0,0,.09) !important;
        }
        .apply-screen .apply-control::placeholder { color: #9b9b9b !important; }
        .apply-screen select.apply-control { appearance: auto; cursor: pointer; padding-right: 10px !important; }
        .apply-screen textarea.apply-control { min-height: 116px !important; resize: vertical; }
        .apply-screen .apply-help {
            display: block;
            margin-top: 7px;
            color: #797979;
            font-size: 11px;
            line-height: 1.55;
        }
        .apply-screen .apply-counter { text-align: right; font-variant-numeric: tabular-nums; }
        .apply-screen .apply-availability {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 6px 16px;
            margin-top: 12px;
            padding: 13px 15px;
            border: 1px solid #e8e8e8;
            border-radius: 11px;
            background: #f8f8f8;
        }
        .apply-screen .apply-availability strong { color: #222; font-size: 11px; }
        .apply-screen .apply-availability span { color: #666; font-size: 11px; }
        .apply-screen .apply-seat-track {
            grid-column: 1 / -1;
            width: 100%;
            height: 5px;
            background: #dedede;
            border-radius: 20px;
            overflow: hidden;
            margin-top: 4px;
        }
        .apply-screen .apply-seat-track div {
            height: 100%;
            width: 0;
            background: #222;
            border-radius: inherit;
            transition: width .2s ease;
        }
        .apply-screen .apply-form-section + .apply-form-section { margin-top: 27px; }
        .apply-screen .apply-privacy {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            padding-top: 16px;
            margin: 23px 0 19px;
            border-top: 1px solid #ebebeb;
            font-size: 11px;
            line-height: 1.65;
            color: #777;
        }
        .apply-screen .apply-privacy i { color: #555; margin-top: 2px; }
        .apply-screen .apply-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 11px; }
        .apply-screen .apply-submit,
        .apply-screen .apply-back {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-align: center;
            min-height: 49px;
            padding: 12px 19px;
            border-radius: 9px;
            font-family: inherit;
            font-size: 12px;
            font-weight: 750;
            text-decoration: none;
            line-height: 1.4;
            cursor: pointer;
            transition: background .15s ease, border-color .15s ease, transform .15s ease;
        }
        .apply-screen .apply-submit {
            background: #171717 !important;
            color: #fff !important;
            border: 1px solid #171717 !important;
        }
        .apply-screen .apply-submit:hover { background: #333 !important; border-color: #333 !important; }
        .apply-screen .apply-submit:disabled { opacity: .55 !important; }
        .apply-screen .apply-back {
            background: #fff !important;
            color: #222 !important;
            border: 1px solid #d8d8d8 !important;
        }
        .apply-screen .apply-back:hover { background: #f4f4f4 !important; }
        .apply-screen .apply-page-footer {
            padding: 20px 4px 0;
            color: #888;
            font-size: 11px;
            text-align: center;
        }
        .apply-swal-popup { border: 1px solid #e3e3e3 !important; border-radius: 18px !important; font-family: 'Segoe UI', Arial, sans-serif !important; }
        .apply-swal-confirm { background: #171717 !important; color: #fff !important; border: 0 !important; border-radius: 8px !important; padding: 12px 25px !important; font: 700 13px 'Segoe UI', Arial, sans-serif !important; cursor: pointer; }
        @media (max-width: 980px) {
            .apply-screen .apply-layout { grid-template-columns: 1fr; }
            .apply-screen .apply-intro { padding: 32px 36px; }
            .apply-screen .apply-intro h1 { font-size: clamp(35px, 6vw, 49px); margin: 23px 0 13px; }
            .apply-screen .apply-intro-copy { max-width: 700px; }
            .apply-screen .apply-process { display: grid; grid-template-columns: repeat(3,minmax(0,1fr)); gap: 14px; margin: 25px 0 0; }
            .apply-screen .apply-process-label { grid-column: 1/-1; margin: 0; }
            .apply-screen .apply-step { gap: 8px; }
            .apply-screen .apply-intro-footer { margin-top: 16px; }
            .apply-screen .apply-content { padding: 35px 36px; }
        }
        @media (max-width: 580px) {
            .apply-screen { width: calc(100% - 28px); padding: 19px 0 25px; }
            .apply-screen .apply-breadcrumb { margin-bottom: 15px; font-size: 11px; gap: 8px; }
            .apply-screen .apply-layout { border-radius: 15px; }
            .apply-screen .apply-intro { padding: 27px 23px; }
            .apply-screen .apply-intro h1 { font-size: clamp(34px, 9vw, 43px); margin-top: 21px; }
            .apply-screen .apply-intro-copy { font-size: 12px; }
            /* Keep the application form high on the screen on small phones. */
            .apply-screen .apply-process { display: none; }
            .apply-screen .apply-intro-footer { padding-top: 15px; }
            .apply-screen .apply-content { padding: 29px 20px 30px; }
            .apply-screen .apply-status-pill { display: none; }
            .apply-screen .apply-fields { grid-template-columns: 1fr; gap: 15px; }
            .apply-screen .apply-field-wide { grid-column: auto; }
            .apply-screen .apply-actions { flex-direction: column; align-items: stretch; }
            .apply-screen .apply-submit,.apply-screen .apply-back { width: 100%; }
            .apply-screen .apply-availability { grid-template-columns: 1fr; }
            .apply-screen .apply-seat-track { grid-column: 1; }
        }
        @media (prefers-reduced-motion: reduce) {
            .apply-screen *, .apply-screen *::before, .apply-screen *::after { transition: none !important; }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../../Components/public/navbar.php'; ?>
<main class="apply-screen">
    <nav class="apply-breadcrumb" aria-label="Breadcrumb">
        <a href="../../index.php">Home</a>
        <span aria-hidden="true">/</span>
        <a href="courses.php">Courses</a>
        <span aria-hidden="true">/</span>
        <span class="apply-current" aria-current="page">Application</span>
    </nav>

    <div class="apply-layout">
        <aside class="apply-intro" aria-label="How applications work">
            <div class="apply-overline">Your learning starts here</div>
            <h1>Make your<br>next move<span>.</span></h1>
            <p class="apply-intro-copy">Discover new skills and take the next step in your education. Tell us a little about yourself to get started.</p>

            <div class="apply-process">
                <div class="apply-process-label">Application process</div>
                <div class="apply-step">
                    <span class="apply-step-index">01</span>
                    <div><strong>Choose a course</strong><small>Select an available course that interests you.</small></div>
                </div>
                <div class="apply-step">
                    <span class="apply-step-index">02</span>
                    <div><strong>Send your application</strong><small>Share your contact details and a short message.</small></div>
                </div>
                <div class="apply-step">
                    <span class="apply-step-index">03</span>
                    <div><strong>Admin reviews it</strong><small>If approved, an administrator will create your account.</small></div>
                </div>
            </div>
            <div class="apply-intro-footer"><i class="fa-solid fa-lock" aria-hidden="true"></i> No student account is needed to apply.</div>
        </aside>

        <section class="apply-content" aria-labelledby="apply-form-title">
            <div class="apply-heading-row">
                <span class="apply-form-overline">Registration / Application</span>
                <span class="apply-status-pill">01 — 01</span>
            </div>
            <h2 id="apply-form-title">Course application</h2>
            <p class="apply-lead">Complete the details below. Fields marked <strong>*</strong> are required.</p>

            <?php if ($successMessage !== ''): ?>
                <div class="apply-note" role="status"><i class="fa-solid fa-circle-check" aria-hidden="true"></i><p><?= applyEscape($successMessage) ?></p></div>
            <?php endif; ?>
            <?php if ($pageError !== ''): ?>
                <div class="apply-note apply-note-error" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><p><?= applyEscape($pageError) ?></p></div>
            <?php endif; ?>
            <?php if ($formError !== ''): ?>
                <div class="apply-note apply-note-error" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><p><strong>Could not submit:</strong> <?= applyEscape($formError) ?></p></div>
            <?php endif; ?>

            <?php if (!$hasOpenSeats): ?>
                <div class="apply-empty">
                    <i class="fa-regular fa-folder-open" aria-hidden="true"></i>
                    <h3>No seats currently available</h3>
                    <p>There are no published courses with open seats right now. Check the course catalogue for updates.</p>
                    <a class="apply-back" href="courses.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Browse courses</a>
                </div>
            <?php else: ?>
                <form id="courseApplicationForm" method="post" action="apply_course.php">
                    <input type="hidden" name="csrf_token" value="<?= applyEscape($_SESSION['csrf_token']) ?>">

                    <div class="apply-form-section">
                        <div class="apply-divider-label">01 / Course selection</div>
                        <div class="apply-field">
                            <label class="apply-label" for="course_id">Select course <span class="apply-required" aria-hidden="true">*</span></label>
                            <select class="apply-control" name="course_id" id="course_id" required aria-describedby="course-help seat-info">
                                <option value="">Choose a course</option>
                                <?php foreach ($courses as $courseItem):
                                    $courseFull = (int) $courseItem['occupied_seats'] >= (int) $courseItem['seat_capacity'];
                                ?>
                                    <option value="<?= (int) $courseItem['id'] ?>"
                                        data-filled="<?= (int) $courseItem['occupied_seats'] ?>"
                                        data-capacity="<?= (int) $courseItem['seat_capacity'] ?>"
                                        <?= $courseFull ? 'disabled' : '' ?>
                                        <?= (string) $courseItem['id'] === $selectedCourse && !$courseFull ? 'selected' : '' ?>>
                                        <?= applyEscape($courseItem['title']) ?> — <?= (int) $courseItem['occupied_seats'] ?>/<?= (int) $courseItem['seat_capacity'] ?> filled<?= $courseFull ? ' (Full)' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="apply-help" id="course-help">Full courses are unavailable for new applications.</span>
                            <div class="apply-availability" id="seat-info" aria-live="polite">
                                <strong id="apply-seat-title">Seat availability</strong>
                                <span id="apply-seat-detail">Select a course to view remaining seats</span>
                                <div class="apply-seat-track" aria-hidden="true"><div id="apply-seat-fill"></div></div>
                            </div>
                        </div>
                    </div>

                    <div class="apply-form-section">
                        <div class="apply-divider-label">02 / Your information</div>
                        <div class="apply-fields">
                            <div class="apply-field">
                                <label class="apply-label" for="full_name">Full name <span class="apply-required" aria-hidden="true">*</span></label>
                                <input class="apply-control" id="full_name" name="full_name" type="text" maxlength="120" autocomplete="name" placeholder="Enter your full name" required value="<?= applyEscape($fullName) ?>">
                            </div>
                            <div class="apply-field">
                                <label class="apply-label" for="email">Email address <span class="apply-required" aria-hidden="true">*</span></label>
                                <input class="apply-control" id="email" name="email" type="email" maxlength="190" autocomplete="email" placeholder="you@example.com" required value="<?= applyEscape($email) ?>">
                            </div>
                            <div class="apply-field apply-field-wide">
                                <label class="apply-label" for="phone">Phone number <span class="apply-required" aria-hidden="true">*</span></label>
                                <input class="apply-control" id="phone" name="phone" type="tel" maxlength="30" autocomplete="tel" placeholder="Your contact number" required value="<?= applyEscape($phone) ?>">
                            </div>
                            <div class="apply-field apply-field-wide">
                                <label class="apply-label" for="message">Application message <span class="apply-required" aria-hidden="true">*</span></label>
                                <textarea class="apply-control" id="message" name="message" maxlength="5000" required aria-describedby="message-help" placeholder="Tell us why you are interested in this course..."><?= applyEscape($message) ?></textarea>
                                <div class="apply-help apply-counter" id="message-help"><span id="apply-message-count">0</span> / 5,000 characters</div>
                            </div>
                        </div>
                    </div>

                    <p class="apply-privacy"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i><span>Your application will be reviewed by an administrator. An account is created only after approval.</span></p>
                    <div class="apply-actions">
                        <button class="apply-submit" id="apply-submit" type="submit">Submit application <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button>
                        <a class="apply-back" href="courses.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to courses</a>
                    </div>
                </form>
            <?php endif; ?>
        </section>
    </div>
    <footer class="apply-page-footer">&copy; <?= date('Y') ?> LMS Student Portal. All rights reserved.</footer>
</main>
<script>
    (() => {
        'use strict';
        const form = document.getElementById('courseApplicationForm');
        if (form) {
            const courseSelect = document.getElementById('course_id');
            const seatTitle = document.getElementById('apply-seat-title');
            const seatDetail = document.getElementById('apply-seat-detail');
            const seatFill = document.getElementById('apply-seat-fill');
            const messageInput = document.getElementById('message');
            const messageCount = document.getElementById('apply-message-count');
            const submitButton = document.getElementById('apply-submit');
            const updateAvailability = () => {
                const option = courseSelect.selectedOptions[0];
                const capacity = Number(option?.dataset.capacity || 0);
                const filled = Number(option?.dataset.filled || 0);
                if (!option || !option.value || capacity <= 0) {
                    seatTitle.textContent = 'Seat availability';
                    seatDetail.textContent = 'Select a course to view remaining seats';
                    seatFill.style.width = '0%';
                    return;
                }
                const remaining = Math.max(0, capacity - filled);
                seatTitle.textContent = `${filled} of ${capacity} seats filled`;
                seatDetail.textContent = `${remaining} seat${remaining === 1 ? '' : 's'} remaining`;
                seatFill.style.width = `${Math.min(100, (filled / capacity) * 100)}%`;
            };
            const updateMessageCount = () => {
                messageCount.textContent = messageInput.value.length.toLocaleString('en-US');
            };
            courseSelect.addEventListener('change', updateAvailability);
            messageInput.addEventListener('input', updateMessageCount);
            updateAvailability();
            updateMessageCount();
            form.addEventListener('submit', (event) => {
                for (const id of ['full_name', 'email', 'phone', 'message']) {
                    const input = document.getElementById(id);
                    input.value = input.value.trim();
                }
                document.getElementById('email').value = document.getElementById('email').value.toLowerCase();
                if (!form.checkValidity()) {
                    event.preventDefault();
                    form.reportValidity();
                    return;
                }
                submitButton.disabled = true;
                submitButton.textContent = 'Submitting...';
            });
        }
    })();
</script>
<?php if ($successMessage !== ''): ?>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'success',
                title: 'Application submitted',
                text: <?= json_encode($successMessage, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>,
                buttonsStyling: false,
                customClass: { popup: 'apply-swal-popup', confirmButton: 'apply-swal-confirm' },
                confirmButtonText: 'Continue'
            });
        }
    });
</script>
<?php endif; ?>
<script src="../../js/lms-alerts.js" defer></script>
</body>
</html>
