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

// Send server-side messages to SweetAlert2 instead of inline alert boxes.
$applySweetAlerts = [];
if ($successMessage !== '') {
    $applySweetAlerts[] = [
        'icon' => 'success',
        'title' => 'Application submitted',
        'text' => $successMessage,
    ];
}
if ($pageError !== '') {
    $applySweetAlerts[] = [
        'icon' => 'error',
        'title' => 'Courses unavailable',
        'text' => $pageError,
    ];
}
if ($formError !== '') {
    $applySweetAlerts[] = [
        'icon' => 'error',
        'title' => 'Unable to submit application',
        'text' => $formError,
    ];
}
if (!$courses && $pageError === '') {
    $applySweetAlerts[] = [
        'icon' => 'info',
        'title' => 'No courses available',
        'text' => 'There are currently no published courses available for applications.',
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#382720">
    <title>Apply for a Course | Student Management System</title>
    <link rel="stylesheet" href="../../public/vendor/fontawesome.css">
    <link rel="stylesheet" href="/LMS/Components/public/css/navbar.css">
    <script src="../../public/vendor/sweetalert2.all.min.js"></script>
    <style>
        :root{--primary:#212121;--text:#2a2a2a;--muted:#212121;--border:#e7e7e7;--bg:#f7f7f7}
        /* SweetAlert2 styling matched to the admin Categories page */
        .apply-swal-popup {
            border: 1px solid #eaeaea !important;
            border-radius: 22px !important;
            padding: 30px 24px !important;
            font-family: Inter, "Segoe UI", sans-serif !important;
            box-shadow: 0 24px 70px rgba(31,31,31,0.16) !important;
        }

        .apply-swal-confirm {
            min-width: 120px !important;
            padding: 12px 22px !important;
            border: 0 !important;
            border-radius: 10px !important;
            background: #212121 !important;
            color: #ffffff !important;
            font-size: 13px !important;
            font-weight: 700 !important;
            box-shadow: 0 5px 14px rgba(106,106,106,0.2) !important;
            cursor: pointer !important;
        }

        .apply-swal-confirm:focus {
            box-shadow: 0 0 0 4px rgba(106,106,106,0.18) !important;
        }

        .apply-swal-cancel {
            min-width: 110px !important;
            padding: 12px 22px !important;
            border: 1px solid #e7e7e7 !important;
            border-radius: 10px !important;
            background: #ffffff !important;
            color: #2a2a2a !important;
            font-size: 13px !important;
            font-weight: 700 !important;
            cursor: pointer !important;
        }

        .empty-courses {
            margin-top: 22px;
            padding: 28px 20px;
            border: 1px dashed #dedede;
            border-radius: 14px;
            background: #ffffff;
            text-align: center;
        }
        .empty-courses > i { display:block; margin-bottom:10px; color:#212121; font-size:25px; }
        .empty-courses h2 { margin:0 0 6px; color:#2a2a2a; font-size:16px; }
        .empty-courses p { margin:0; }

        *{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font-family:Inter,"Segoe UI",Arial,sans-serif}
        .page{width:min(760px,calc(100% - 28px));margin:42px auto}.brand{display:flex;align-items:center;gap:10px;margin-bottom:20px;color:var(--text);text-decoration:none;font-weight:800}
        .brand-icon{display:grid;place-items:center;width:42px;height:42px;border-radius:13px;background:#f2f2f2;color:var(--primary)}
        .card{background:#ffffff;border:1px solid #ececec;border-radius:20px;padding:clamp(22px,5vw,36px);box-shadow:0 12px 35px rgba(23,23,23,.06)}
        .eyebrow{font-size:11px;font-weight:800;letter-spacing:.12em;color:var(--primary)}h1{margin:9px 0 8px;font-size:clamp(25px,4vw,34px);letter-spacing:-.03em}p{color:var(--muted);font-size:14px;line-height:1.65}
        .notice{padding:13px 15px;border-radius:12px;margin:16px 0;font-size:13px;line-height:1.6}.success{background:#f9f9f9;color:#515151}.error{background:#f5f5f5;color:#363636}
        .grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:24px}.field{display:flex;flex-direction:column;gap:7px}.wide{grid-column:1/-1}label{font-size:12px;font-weight:750;color:#2a2a2a}
        input,select,textarea{width:100%;border:1px solid #e3e3e3;border-radius:10px;padding:12px;background:#ffffff;color:#2a2a2a;font:inherit;font-size:13px;outline:none}textarea{min-height:110px;resize:vertical}input:focus,select:focus,textarea:focus{border-color:#212121;box-shadow:0 0 0 3px rgba(111,111,111,.12)}
        .actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:22px}.btn{display:inline-flex;justify-content:center;align-items:center;gap:8px;min-height:42px;padding:11px 16px;border:1px solid transparent;border-radius:10px;text-decoration:none;font-size:12px;font-weight:800;cursor:pointer}.primary{background:var(--primary);color:#ffffff}.light{border-color:var(--border);background:#ffffff;color:#212121}.foot{margin-top:18px;text-align:center;font-size:12px;color:#212121}
        @media(max-width:600px){.page{margin:20px auto}.grid{grid-template-columns:1fr}.wide{grid-column:auto}.actions .btn{flex:1}}
    </style>
<link rel="stylesheet" href="../../public/css/theme.css">
</head>
<body>

<?php include __DIR__ . '/../../Components/public/navbar.php'; ?>
<main class="page">
    <a class="brand" href="../../index.php"><span class="brand-icon"><i class="fa-solid fa-graduation-cap"></i></span><span>Student Management System</span></a>
    <section class="card">
        <?php if ($formError !== ''): ?><div class="notice error" role="alert"><?=applyEscape($formError)?></div><?php endif; ?>
        <?php if ($pageError !== ''): ?><div class="notice error" role="alert"><?=applyEscape($pageError)?></div><?php endif; ?>
        <?php if ($successMessage !== ''): ?><div class="notice" role="status"><?=applyEscape($successMessage)?></div><?php endif; ?>
        <div class="eyebrow"><i class="fa-solid fa-file-circle-plus"></i> COURSE APPLICATION</div>
        <h1>Apply for a course</h1>
        <p>Students do not create accounts directly. Submit your application for a published course; an administrator will review it and, if approved, create your student account.</p>
        <?php $hasOpenSeats=false; foreach($courses as $course){ if((int)$course['occupied_seats'] < (int)$course['seat_capacity']){$hasOpenSeats=true;break;} } ?>
        <?php if (!$hasOpenSeats): ?>
            <div class="empty-courses">
                <i class="fa-regular fa-folder-open" aria-hidden="true"></i>
                <h2>No seats currently available</h2>
                <p>Courses will open for registration when a published course has available seats.</p>
            </div>
        <?php else: ?>
        <form method="post" action="apply_course.php">
            <input type="hidden" name="csrf_token" value="<?= applyEscape($_SESSION['csrf_token']) ?>">
            <div class="grid">
                <div class="field wide"><label for="course_id">Course <span style="color:#dc2626">*</span></label>
                    <select id="course_id" name="course_id" required><option value="">Choose a course</option>
                    <?php foreach ($courses as $course): $full=(int)$course['occupied_seats'] >= (int)$course['seat_capacity']; ?><option value="<?= (int)$course['id'] ?>" <?= $full?'disabled':'' ?> <?= (string)$course['id'] === $selectedCourse && !$full ? 'selected' : '' ?>><?= applyEscape($course['title']) ?> — <?= (int)$course['occupied_seats'] ?>/<?= (int)$course['seat_capacity'] ?> seats filled<?= $full ? ' (Full)' : '' ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="field"><label for="full_name">Full name <span style="color:#dc2626">*</span></label><input id="full_name" name="full_name" maxlength="120" autocomplete="name" required value="<?= applyEscape($fullName) ?>"></div>
                <div class="field"><label for="email">Email address <span style="color:#dc2626">*</span></label><input id="email" name="email" type="email" maxlength="190" autocomplete="email" required value="<?= applyEscape($email) ?>"></div>
                <div class="field wide"><label for="phone">Phone number *</label><input id="phone" name="phone" type="tel" maxlength="30" autocomplete="tel" required value="<?= applyEscape($phone) ?>"></div>
                <div class="field wide"><label for="message">Application message *</label><textarea id="message" name="message" maxlength="5000" required placeholder="Tell us briefly why you are interested in this course."><?= applyEscape($message) ?></textarea></div>
            </div>
            <div class="actions"><button class="btn primary" type="submit"><i class="fa-solid fa-paper-plane"></i> Submit application</button><a class="btn light" href="courses.php">Back to courses</a></div>
        </form>
        <?php endif; ?>
    </section>
    <div class="foot">&copy; <?= date('Y') ?> Student Management System</div>
</main>

<script>
    const applySweetAlertOptions = {
        customClass: {
            popup: 'apply-swal-popup',
            confirmButton: 'apply-swal-confirm',
            cancelButton: 'apply-swal-cancel'
        },
        buttonsStyling: false,
        reverseButtons: true,
        confirmButtonText: 'OK'
    };

    // Safely pass server-side messages to SweetAlert2.
    const applyServerAlerts = <?= json_encode(
        $applySweetAlerts,
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE
    ) ?>;

    async function showApplyServerAlerts() {
        for (const alertItem of applyServerAlerts) {
            await Swal.fire({
                ...applySweetAlertOptions,
                icon: alertItem.icon,
                title: alertItem.title,
                text: alertItem.text
            });
        }
    }
    void showApplyServerAlerts();

    // Use SweetAlert2 for client-side validation.
    const applicationForm = document.querySelector('form[method="post"]');
    if (applicationForm) {
        applicationForm.addEventListener('submit', async function (event) {
            const courseInput = document.getElementById('course_id');
            const nameInput = document.getElementById('full_name');
            const emailInput = document.getElementById('email');
            const phoneInput = document.getElementById('phone');
            const messageInput = document.getElementById('message');

            let validationMessage = '';
            let invalidInput = null;

            if (!courseInput.value) {
                validationMessage = 'Please select a course.';
                invalidInput = courseInput;
            } else if (nameInput.value.trim() === '') {
                validationMessage = 'Please enter your full name.';
                invalidInput = nameInput;
            } else if (nameInput.value.trim().length > 120) {
                validationMessage = 'Your full name cannot exceed 120 characters.';
                invalidInput = nameInput;
            } else if (emailInput.value.trim() === '' || !emailInput.checkValidity()) {
                validationMessage = 'Please enter a valid email address.';
                invalidInput = emailInput;
            } else if (emailInput.value.trim().length > 190) {
                validationMessage = 'Your email address cannot exceed 190 characters.';
                invalidInput = emailInput;
            } else if (phoneInput.value.trim().length > 30) {
                validationMessage = 'Your phone number cannot exceed 30 characters.';
                invalidInput = phoneInput;
            } else if (messageInput.value.length > 5000) {
                validationMessage = 'Your application message cannot exceed 5,000 characters.';
                invalidInput = messageInput;
            }

            if (validationMessage !== '') {
                event.preventDefault();
                await Swal.fire({
                    ...applySweetAlertOptions,
                    icon: 'warning',
                    title: 'Check your application',
                    text: validationMessage
                });
                if (invalidInput) invalidInput.focus();
                return;
            }

            // Submit trimmed values to keep stored information clean.
            nameInput.value = nameInput.value.trim();
            emailInput.value = emailInput.value.trim();
            phoneInput.value = phoneInput.value.trim();
            messageInput.value = messageInput.value.trim();
        });
    }
</script>
</body>
</html>
