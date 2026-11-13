<?php
require_once __DIR__ . '/_portal.php';
require_once __DIR__ . '/../../config/design_media.php';
$instructorId=(int)$currentInstructor['id'];
$statement=$pdo->prepare("SELECT c.id,c.title,c.description,c.status,c.seat_capacity,c.cover_image_base64,cc.name AS category_name,
 (SELECT COUNT(*) FROM course_applications a WHERE a.course_id=c.id AND a.status='approved') AS occupied
 FROM courses c JOIN course_categories cc ON cc.id=c.category_id WHERE c.instructor_id=? ORDER BY c.updated_at DESC");
$statement->execute([$instructorId]);
$courses=$statement->fetchAll(PDO::FETCH_ASSOC);
ipPageStart('My Courses');
?>
<main class="sp-main">
  <div class="sp-page-heading"><span class="sp-kicker">MY TEACHING</span><h1>Assigned courses<span aria-hidden="true">.</span></h1><p>View the courses assigned to your instructor account, including draft and published programmes.</p></div>
  <?php if (!$courses): ?><div class="sp-empty"><h3>No courses assigned</h3><p>Ask your administrator to assign a course to your profile.</p></div>
  <?php else: ?><div class="sp-course-grid">
    <?php foreach ($courses as $course): $taken=(int)$course['occupied'];$capacity=(int)$course['seat_capacity'];$percentage=$capacity>0?min(100,(int)round($taken*100/$capacity)):100; ?>
      <article class="sp-course-card">
        <div class="sp-course-cover">
        <?php if ($photo=lmsImageDataUri($course['cover_image_base64']??null)): ?><img class="lms-course-card-image" src="<?=ipH($photo)?>" alt="" loading="lazy"><?php elseif ($placeholder=lmsDesignImage('course_placeholder')): ?><img class="lms-course-card-image" src="<?=ipH($placeholder)?>" alt="" loading="lazy"><?php endif; ?>
        <span class="sp-course-cover-num">#<?= (int)$course['id'] ?></span><span class="sp-course-cover-type"><?=ipH(strtoupper($course['status']))?></span></div>
        <div class="sp-course-body"><span class="sp-course-category"><?=ipH($course['category_name'])?></span><h2 class="sp-course-title"><?=ipH($course['title'])?></h2><p class="sp-course-description"><?=ipH($course['description']?:'Course details available from your administrator.')?></p>
        <div class="sp-seat-info"><div class="sp-seat-line"><span>Seats filled</span><strong><?=$taken?> / <?=$capacity?></strong></div><div class="sp-meter" role="progressbar" aria-label="Seats filled" aria-valuenow="<?=$taken?>" aria-valuemin="0" aria-valuemax="<?=max(1,$capacity,$taken)?>"><span style="width:<?=$percentage?>%"></span></div></div>
        <p class="ip-course-note">Course changes and student approvals are managed by an administrator.</p></div>
      </article>
    <?php endforeach; ?>
  </div><?php endif; ?>
</main>
<?php ipPageEnd(); ?>
