<?php
require_once __DIR__ . '/_portal.php';

$search = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$search = function_exists('mb_substr') ? mb_substr($search, 0, 100, 'UTF-8') : substr($search, 0, 100);

$sql = "SELECT c.id, c.title, c.description, c.seat_capacity, cc.name AS category_name,
               (SELECT COUNT(*) FROM course_applications a
                 WHERE a.course_id = c.id AND a.status = 'approved') AS occupied_seats
          FROM courses c
          JOIN course_categories cc ON cc.id = c.category_id
         WHERE c.status = 'published' AND cc.status = 'active'";
$params = [];
if ($search !== '') {
    $sql .= " AND (c.title LIKE :title OR COALESCE(c.description, '') LIKE :description OR cc.name LIKE :category)";
    $pattern = '%' . $search . '%';
    $params = [':title' => $pattern, ':description' => $pattern, ':category' => $pattern];
}
$sql .= ' ORDER BY cc.name ASC, c.title ASC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Include applications linked to this student, and older unlinked applications
// submitted with the same verified account email.
$own = $pdo->prepare(
    "SELECT course_id, status
       FROM course_applications
      WHERE student_user_id = :student_id
         OR (student_user_id IS NULL AND email = :email)
      ORDER BY id DESC"
);
$own->execute([':student_id' => (int) $currentUser['id'], ':email' => (string) $currentUser['email']]);
$myApplications = [];
foreach ($own->fetchAll(PDO::FETCH_ASSOC) as $application) {
    $courseId = (int) $application['course_id'];
    $status = (string) $application['status'];
    if (!isset($myApplications[$courseId]) || ($status === 'approved' && $myApplications[$courseId] !== 'approved')) {
        $myApplications[$courseId] = $status;
    }
}

spPageStart('Courses', $currentUser, 'courses.php');
?>
<main class="sp-main">
  <div class="sp-page-heading">
    <span class="sp-kicker">EXPLORE YOUR NEXT OPPORTUNITY</span>
    <h1>Available courses<span aria-hidden="true">.</span></h1>
    <p>Browse published courses without leaving your student portal. Check seat availability and submit new applications using your account.</p>
  </div>

  <?php if (($_GET['applied'] ?? '') === '1'): ?>
    <div class="sp-message" role="status">Your course application was submitted successfully. An administrator will review it.</div>
  <?php endif; ?>

  <div class="sp-course-tools">
    <form method="get" action="courses.php" class="sp-course-search" role="search">
      <input type="search" name="q" value="<?= spH($search) ?>" maxlength="100" placeholder="Search courses or categories" aria-label="Search courses">
      <button class="sp-button sp-button-sm" type="submit">Search</button>
      <?php if ($search !== ''): ?><a class="sp-button sp-button-outline sp-button-sm" href="courses.php">Clear</a><?php endif; ?>
    </form>
    <span class="sp-course-count"><?= count($courses) ?> <?= count($courses) === 1 ? 'course' : 'courses' ?> found</span>
  </div>

  <?php if (!$courses): ?>
    <div class="sp-empty"><div class="sp-empty-symbol" aria-hidden="true">⌕</div><h3>No courses found</h3><p>Try a different keyword or check again later.</p><a href="courses.php" class="sp-button sp-button-sm">View all courses</a></div>
  <?php else: ?>
    <section class="sp-course-grid" aria-label="Course catalogue">
      <?php foreach ($courses as $index => $course): ?>
        <?php
          $id = (int) $course['id'];
          $taken = max(0, (int) $course['occupied_seats']);
          $capacity = max(0, (int) $course['seat_capacity']);
          $available = max(0, $capacity - $taken);
          $isFull = $available === 0;
          $percent = $capacity > 0 ? min(100, (int) round($taken / $capacity * 100)) : 100;
          $status = $myApplications[$id] ?? null;
          $description = trim((string) ($course['description'] ?? ''));
          $description = $description !== '' ? $description : 'Discover new skills and grow your knowledge with this course.';
        ?>
        <article class="sp-course-card">
          <div class="sp-course-cover">
            <span class="sp-course-cover-num" aria-hidden="true"><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
            <span class="sp-course-cover-type"><?= $isFull ? 'FULL' : 'OPEN' ?></span>
          </div>
          <div class="sp-course-body">
            <span class="sp-course-category"><?= spH($course['category_name']) ?></span>
            <h2 class="sp-course-title"><?= spH($course['title']) ?></h2>
            <p class="sp-course-description"><?= spH($description) ?></p>
            <div class="sp-seat-info">
              <div class="sp-seat-line"><span><?= $taken ?> / <?= $capacity ?> seats filled</span><strong><?= $available ?> available</strong></div>
              <div class="sp-meter" role="progressbar" aria-label="Seats filled" aria-valuenow="<?= $taken ?>" aria-valuemin="0" aria-valuemax="<?= max($taken, $capacity, 1) ?>"><span style="width:<?= $percent ?>%"></span></div>
            </div>
            <div class="sp-course-footer">
              <?php if ($status === 'approved'): ?>
                <span class="sp-button sp-disabled" aria-label="Already approved for this course">✓ Application approved</span>
              <?php elseif ($status === 'pending'): ?>
                <span class="sp-button sp-disabled" aria-label="Application awaiting admin review">◷ Application pending</span>
              <?php elseif ($isFull): ?>
                <span class="sp-button sp-disabled" aria-label="This course is full">No seats available</span>
              <?php else: ?>
                <a class="sp-button" href="apply_course.php?course_id=<?= $id ?>"><?= $status === 'declined' ? 'Apply again' : 'Apply for course' ?> <span aria-hidden="true">↗</span></a>
              <?php endif; ?>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>
</main>
<?php spPageEnd(); ?>
