<?php
require_once __DIR__ . '/_portal.php';

$courseId = filter_var($_POST['course_id'] ?? $_GET['course_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$courseId) {
    header('Location: courses.php');
    exit;
}

$getCourse = $pdo->prepare(
    "SELECT c.id, c.title, c.description, c.seat_capacity, cc.name AS category_name,
            (SELECT COUNT(*) FROM course_applications a WHERE a.course_id=c.id AND a.status='approved') AS occupied_seats
       FROM courses c
       JOIN course_categories cc ON cc.id=c.category_id
      WHERE c.id = :id AND c.status='published' AND cc.status='active'"
);
$getCourse->execute([':id' => $courseId]);
$course = $getCourse->fetch(PDO::FETCH_ASSOC);
if (!$course) {
    header('Location: courses.php');
    exit;
}

$getProfile = $pdo->prepare('SELECT phone FROM users WHERE id = ? AND role = ?');
$getProfile->execute([(int) $currentUser['id'], 'student']);
$phone = (string) ($getProfile->fetchColumn() ?: '');
$message = '';
$error = '';

// A signed-in student may apply without going through the public application page.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $phone = is_string($_POST['phone'] ?? null) ? trim($_POST['phone']) : '';
    $message = is_string($_POST['message'] ?? null) ? trim($_POST['message']) : '';
    $token = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
    if (!hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $token)) {
        $error = 'Your session has expired. Refresh and try again.';
    } elseif ($phone === '' || spLen($phone) > 30) {
        $error = 'Enter a phone number of up to 30 characters.';
    } elseif ($message === '' || spLen($message) > 5000) {
        $error = 'Enter an application message (up to 5,000 characters).';
    } else {
        try {
            $pdo->beginTransaction();
            // The course lock serializes checks with the administrator approval flow.
            $lock = $pdo->prepare(
                "SELECT c.id, c.seat_capacity FROM courses c
                   JOIN course_categories cc ON cc.id = c.category_id
                  WHERE c.id = ? AND c.status = 'published' AND cc.status = 'active' FOR UPDATE"
            );
            $lock->execute([(int) $courseId]);
            $locked = $lock->fetch(PDO::FETCH_ASSOC);
            if (!$locked) {
                $error = 'This course is no longer open for applications.';
            } else {
                $existing = $pdo->prepare(
                    "SELECT id FROM course_applications
                      WHERE course_id = :course_id
                        AND (student_user_id = :student_id OR email = :email)
                        AND status IN ('pending','approved') LIMIT 1"
                );
                $existing->execute([
                    ':course_id' => $courseId,
                    ':student_id' => (int) $currentUser['id'],
                    ':email' => (string) $currentUser['email']
                ]);
                if ($existing->fetchColumn()) {
                    $error = 'You already have a pending or approved application for this course.';
                } else {
                    $count = $pdo->prepare("SELECT COUNT(*) FROM course_applications WHERE course_id = ? AND status='approved'");
                    $count->execute([(int) $courseId]);
                    if ((int) $count->fetchColumn() >= (int) $locked['seat_capacity']) {
                        $error = 'This course is now full. Please choose another course.';
                    } else {
                        $save = $pdo->prepare(
                            'INSERT INTO course_applications (course_id, full_name, email, phone, message, student_user_id)
                             VALUES (:course_id, :full_name, :email, :phone, :message, :student_id)'
                        );
                        $save->execute([
                            ':course_id' => $courseId,
                            ':full_name' => (string) $currentUser['full_name'],
                            ':email' => (string) $currentUser['email'],
                            ':phone' => $phone,
                            ':message' => $message,
                            ':student_id' => (int) $currentUser['id'],
                        ]);
                        $pdo->commit();
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                        header('Location: courses.php?applied=1');
                        exit;
                    }
                }
            }
            if ($pdo->inTransaction()) $pdo->rollBack();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Student course application error: ' . $e->getMessage());
            $error = 'Your application could not be saved. Please try again later.';
        }
    }
}

$myStatus = $pdo->prepare(
    "SELECT status FROM course_applications
      WHERE course_id = :course_id
        AND (student_user_id = :student_id OR email = :email)
        AND status IN ('pending', 'approved')
      ORDER BY (status = 'approved') DESC, id DESC LIMIT 1"
);
$myStatus->execute([':course_id' => $courseId, ':student_id' => (int) $currentUser['id'], ':email' => (string) $currentUser['email']]);
$existingStatus = $myStatus->fetchColumn() ?: '';
$full = (int) $course['occupied_seats'] >= (int) $course['seat_capacity'];

spPageStart('Apply for a Course', $currentUser, 'apply_course.php');
?>
<main class="sp-main">
  <div class="sp-page-heading">
    <a href="courses.php" class="sp-section-link">← Back to courses</a>
    <div style="height:20px"></div>
    <span class="sp-kicker">NEW COURSE APPLICATION</span>
    <h1>Apply for a course<span aria-hidden="true">.</span></h1>
    <p>Your personal information comes from your student account. Complete the remaining details to send an application to the administrator.</p>
  </div>

  <div class="sp-profile-grid">
    <section class="sp-panel">
      <div class="sp-panel-head"><div><h2>Application details</h2><p>All fields marked * are required</p></div></div>
      <?php if ($error !== ''): ?><div class="sp-message sp-message-error" role="alert"><?= spH($error) ?></div><?php endif; ?>
      <?php if ($existingStatus !== ''): ?>
        <div class="sp-message" role="status">You already have a <?= spH($existingStatus) ?> application for this course. You cannot submit another one while it is pending or approved.</div>
        <a class="sp-button" href="courses.php">Return to courses</a>
      <?php elseif ($full): ?>
        <div class="sp-message" role="alert">This course is full. Choose another available course.</div>
        <a class="sp-button" href="courses.php">Browse other courses</a>
      <?php else: ?>
        <form class="sp-form" method="post" action="apply_course.php">
          <input type="hidden" name="csrf_token" value="<?= spH($_SESSION['csrf_token']) ?>">
          <input type="hidden" name="course_id" value="<?= (int) $course['id'] ?>">
          <div class="sp-form-grid">
            <div class="sp-field"><label for="applicantName">Full name</label><input id="applicantName" value="<?= spH($currentUser['full_name']) ?>" readonly></div>
            <div class="sp-field"><label for="applicantEmail">Email address</label><input id="applicantEmail" type="email" value="<?= spH($currentUser['email']) ?>" readonly></div>
            <div class="sp-field sp-span-all"><label for="applicantPhone">Phone number *</label><input id="applicantPhone" name="phone" type="tel" maxlength="30" autocomplete="tel" value="<?= spH($phone) ?>" required></div>
            <div class="sp-field sp-span-all"><label for="applicationMessage">Application message *</label><textarea id="applicationMessage" name="message" maxlength="5000" required placeholder="Tell us why you want to join this course..."><?= spH($message) ?></textarea><p class="sp-help">Your application is reviewed by an administrator before approval.</p></div>
          </div>
          <div class="sp-form-actions"><button class="sp-button" type="submit">Submit application ↗</button><a href="courses.php" class="sp-button sp-button-outline">Cancel</a></div>
        </form>
      <?php endif; ?>
    </section>
    <aside class="sp-panel">
      <div class="sp-panel-head"><div><h2>Selected course</h2><p>Review before you apply</p></div></div>
      <div class="sp-selected-course"><span class="sp-course-category"><?= spH($course['category_name']) ?></span><strong><?= spH($course['title']) ?></strong><small><?= spH($course['description'] ?: 'Learn and develop practical skills.') ?></small></div>
      <div class="sp-seat-line"><span>Seats filled</span><strong><?= (int) $course['occupied_seats'] ?> / <?= (int) $course['seat_capacity'] ?></strong></div>
      <?php $pct = (int) $course['seat_capacity'] > 0 ? min(100, (int) round((int) $course['occupied_seats'] / (int) $course['seat_capacity'] * 100)) : 100; ?>
      <div class="sp-meter" role="progressbar" aria-label="Seats filled" aria-valuenow="<?= (int) $course['occupied_seats'] ?>" aria-valuemin="0" aria-valuemax="<?= max(1, (int) $course['seat_capacity'], (int) $course['occupied_seats']) ?>"><span style="width:<?= $pct ?>%"></span></div>
      <p class="sp-help" style="margin-top:12px"><?= max(0, (int) $course['seat_capacity'] - (int) $course['occupied_seats']) ?> seats remaining. Approval depends on availability at the time of review.</p>
    </aside>
  </div>
</main>
<?php spPageEnd(); ?>
