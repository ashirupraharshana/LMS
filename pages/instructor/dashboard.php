<?php
require_once __DIR__ . '/_portal.php';
require_once __DIR__ . '/../../config/design_media.php';
$instructorId = (int) $currentInstructor['id'];
$summary = $pdo->prepare("SELECT COUNT(*) AS assigned, COALESCE(SUM(status='published'),0) AS published, COALESCE(SUM(status='draft'),0) AS drafts FROM courses WHERE instructor_id = ?");
$summary->execute([$instructorId]);
$totals = $summary->fetch(PDO::FETCH_ASSOC);
$seatStmt = $pdo->prepare("SELECT COALESCE(SUM(COALESCE(a.approved, 0)), 0) AS learners
 FROM courses c
 LEFT JOIN (SELECT course_id, COUNT(*) AS approved FROM course_applications WHERE status = 'approved' GROUP BY course_id) a ON a.course_id = c.id
 WHERE c.instructor_id = ?");
$seatStmt->execute([$instructorId]);
$learners = (int) $seatStmt->fetchColumn();
$courseStmt = $pdo->prepare("SELECT c.id, c.title, c.status, c.seat_capacity, cc.name AS category_name,
  (SELECT COUNT(*) FROM course_applications a WHERE a.course_id = c.id AND a.status = 'approved') AS occupied
  FROM courses c JOIN course_categories cc ON cc.id = c.category_id WHERE c.instructor_id = ? ORDER BY c.updated_at DESC LIMIT 6");
$courseStmt->execute([$instructorId]);
$courses = $courseStmt->fetchAll(PDO::FETCH_ASSOC);
$firstName = preg_split('/\s+/u', trim((string) $currentInstructor['full_name']))[0] ?: 'Instructor';
ipPageStart('Dashboard');
?>
<main class="sp-main">
  <section class="sp-hero" aria-labelledby="instructorGreeting">
    <div><span class="sp-kicker">TEACHING WORKSPACE</span><h1 id="instructorGreeting">Welcome back,<br><?= ipH($firstName) ?>.</h1>
      <p>Your assigned courses and learning activity, all in one place.</p>
      <div class="sp-hero-actions"><a class="sp-button" href="courses.php">View my courses ↗</a><a class="sp-button sp-button-outline" href="profile.php">Manage profile</a></div>
    </div>
    <div class="sp-hero-decoration lms-student-hero-photo"><?php if($hero=lmsDesignImage('instructor_hero')): ?><img src="<?=ipH($hero)?>" alt="Teaching workspace"><?php else: ?><span aria-hidden="true">✦</span><small>Instructor hero image slot</small><?php endif; ?></div>
  </section>
  <section class="sp-stats" aria-label="Course summary">
  <?php foreach ([['Assigned courses',(int)$totals['assigned'],'All your course allocations','fa-book-open'],['Published',(int)$totals['published'],'Currently available to students','fa-circle-check'],['Draft courses',(int)$totals['drafts'],'Not yet publicly available','fa-file-lines'],['Enrolled learners',$learners,'Approved seats in your courses','fa-users']] as [$label,$value,$note,$icon]): ?>
    <article class="sp-stat"><div class="sp-stat-top"><span class="sp-stat-label"><?=ipH($label)?></span><span class="sp-stat-symbol"><i class="fa-solid <?=ipH($icon)?>" aria-hidden="true"></i></span></div><div class="sp-stat-value"><?=$value?></div><div class="sp-stat-note"><?=ipH($note)?></div></article>
  <?php endforeach; ?>
  </section>
  <section class="sp-panel"><div class="sp-panel-head"><div><h2>My recent courses</h2><p>Courses assigned to you by the administrator</p></div><a class="sp-section-link" href="courses.php">View all</a></div>
    <?php if (!$courses): ?><div class="sp-empty"><h3>No assigned courses yet</h3><p>Contact your administrator to have courses assigned to your account.</p></div>
    <?php else: ?><ul class="sp-application-list"><?php foreach ($courses as $course): ?>
      <li class="sp-application-item"><span class="sp-course-monogram"><i class="fa-solid fa-book-open"></i></span><span class="sp-application-copy"><strong><?=ipH($course['title'])?></strong><small><?=ipH($course['category_name'])?> · <?= (int)$course['occupied'] ?> / <?= (int)$course['seat_capacity'] ?> seats filled</small></span><span class="sp-status"><?=ipH(ucfirst($course['status']))?></span></li>
    <?php endforeach; ?></ul><?php endif; ?>
  </section>
</main>
<?php ipPageEnd(); ?>
