<?php
// This navbar is included only after config/auth.php validates a student session.
$studentNavActive = $studentNavActive ?? basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'dashboard.php'));
$studentName = trim((string) ($currentUser['full_name'] ?? 'Student'));
$studentInitial = function_exists('mb_substr') ? mb_substr($studentName, 0, 1, 'UTF-8') : substr($studentName, 0, 1);
$studentEscape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
require_once __DIR__ . '/../../config/image_helpers.php';
?>
<header class="sp-nav" id="studentNavbar">
  <div class="sp-nav-inner">
    <a class="sp-brand" href="dashboard.php" aria-label="LMS student dashboard">
      <span class="sp-brand-symbol" aria-hidden="true">L.</span>
      <span class="sp-brand-type">LMS <small>STUDENT PORTAL</small></span>
    </a>

    <button class="sp-menu-toggle" type="button" aria-label="Open student menu" aria-controls="spNavigation" aria-expanded="false">
      <span></span><span></span><span></span>
    </button>

    <nav class="sp-nav-links" id="spNavigation" aria-label="Student navigation">
      <a class="sp-nav-link <?= $studentNavActive === 'dashboard.php' ? 'is-active' : '' ?>" href="dashboard.php" <?= $studentNavActive === 'dashboard.php' ? 'aria-current="page"' : '' ?>>Dashboard</a>
      <a class="sp-nav-link <?= in_array($studentNavActive, ['courses.php', 'apply_course.php'], true) ? 'is-active' : '' ?>" href="courses.php" <?= in_array($studentNavActive, ['courses.php', 'apply_course.php'], true) ? 'aria-current="page"' : '' ?>>Courses</a>
      <a class="sp-nav-link <?= $studentNavActive === 'profile.php' ? 'is-active' : '' ?>" href="profile.php" <?= $studentNavActive === 'profile.php' ? 'aria-current="page"' : '' ?>>Manage Profile</a>
      <div class="sp-nav-account">
        <span class="sp-nav-avatar" aria-hidden="true"><?php if($photo=lmsImageDataUri($currentUser['profile_photo_base64']??null)):?><img src="<?=$studentEscape($photo)?>" alt=""><?php else:?><?=$studentEscape(strtoupper($studentInitial))?><?php endif;?></span>
        <span class="sp-nav-person"><strong><?= $studentEscape($studentName) ?></strong><small>Student account</small></span>
      </div>
      <form action="../admin/logout.php" method="post" class="sp-logout-form">
        <input type="hidden" name="csrf_token" value="<?= $studentEscape($_SESSION['csrf_token'] ?? '') ?>">
        <button type="submit" class="sp-logout-btn">Log out <span aria-hidden="true">↗</span></button>
      </form>
    </nav>
  </div>
</header>
<script>
(() => {
  const bar = document.getElementById('studentNavbar');
  const toggle = bar?.querySelector('.sp-menu-toggle');
  const links = bar?.querySelector('.sp-nav-links');
  if (!toggle || !links) return;
  const close = () => { links.classList.remove('is-open'); toggle.setAttribute('aria-expanded', 'false'); toggle.setAttribute('aria-label', 'Open student menu'); };
  toggle.addEventListener('click', () => {
    const open = links.classList.toggle('is-open');
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', open ? 'Close student menu' : 'Open student menu');
  });
  document.addEventListener('keydown', event => { if (event.key === 'Escape') close(); });
  document.addEventListener('click', event => { if (!bar.contains(event.target)) close(); });
  links.querySelectorAll('a').forEach(link => link.addEventListener('click', close));
})();
</script>
