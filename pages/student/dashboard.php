<?php
require_once __DIR__ . '/_portal.php';

$stmt = $pdo->prepare(
    'SELECT a.id, a.status, a.created_at, c.title, cc.name AS category_name
       FROM course_applications a
       JOIN courses c ON c.id = a.course_id
       JOIN course_categories cc ON cc.id = c.category_id
      WHERE a.student_user_id = :user_id
      ORDER BY a.id DESC'
);
$stmt->execute([':user_id' => (int) $currentUser['id']]);
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totals = ['all' => 0, 'approved' => 0, 'pending' => 0, 'declined' => 0];
foreach ($applications as $application) {
    $totals['all']++;
    if (array_key_exists($application['status'], $totals)) {
        $totals[$application['status']]++;
    }
}

$openCourseCount = (int) $pdo->query(
    "SELECT COUNT(*)
       FROM courses c
       JOIN course_categories cc ON cc.id = c.category_id
      WHERE c.status = 'published'
        AND cc.status = 'active'
        AND (SELECT COUNT(*) FROM course_applications a
             WHERE a.course_id = c.id AND a.status = 'approved') < c.seat_capacity"
)->fetchColumn();

$nameParts = preg_split('/\s+/u', trim((string) $currentUser['full_name']));
$firstName = $nameParts[0] ?? 'Student';
spPageStart('Dashboard', $currentUser, 'dashboard.php');
?>
<main class="sp-main">
  <section class="sp-hero" aria-labelledby="studentWelcome">
    <div>
      <span class="sp-kicker">YOUR PERSONAL LEARNING SPACE</span>
      <h1 id="studentWelcome">Welcome back,<br><?= spH($firstName) ?>.</h1>
      <p>Track your course applications, discover new programmes and manage your student account from one place.</p>
      <div class="sp-hero-actions">
        <a href="courses.php" class="sp-button">Apply for more courses <span aria-hidden="true">↗</span></a>
        <a href="profile.php" class="sp-button sp-button-outline">Manage profile</a>
      </div>
    </div>
    <div class="sp-hero-decoration" aria-hidden="true">L.</div>
  </section>

  <section class="sp-stats" aria-label="Application overview">
    <?php foreach ([
      ['All applications', $totals['all'], 'Total submissions', '▦'],
      ['Approved', $totals['approved'], 'Accepted by admin', '✓'],
      ['Pending review', $totals['pending'], 'Awaiting decision', '◷'],
      ['Open courses', $openCourseCount, 'Available to explore', '↗'],
    ] as [$label, $value, $note, $symbol]): ?>
      <article class="sp-stat">
        <div class="sp-stat-top"><span class="sp-stat-label"><?= spH($label) ?></span><span class="sp-stat-symbol" aria-hidden="true"><?= spH($symbol) ?></span></div>
        <div class="sp-stat-value"><?= (int) $value ?></div>
        <div class="sp-stat-note"><?= spH($note) ?></div>
      </article>
    <?php endforeach; ?>
  </section>

  <div class="sp-content-grid">
    <section class="sp-panel" aria-labelledby="recentApplications">
      <div class="sp-panel-head">
        <div><h2 id="recentApplications">Recent applications</h2><p>Latest course activity linked to your account</p></div>
        <a class="sp-section-link" href="courses.php">Explore courses</a>
      </div>
      <?php if (!$applications): ?>
        <div class="sp-empty">
          <div class="sp-empty-symbol" aria-hidden="true">▤</div>
          <h3>No linked applications yet</h3>
          <p>Explore courses or ask your administrator to link an existing application to your account.</p>
          <a class="sp-button sp-button-sm" href="courses.php">Browse courses ↗</a>
        </div>
      <?php else: ?>
        <ul class="sp-application-list">
          <?php foreach (array_slice($applications, 0, 5) as $application): ?>
            <?php
              $status = (string) $application['status'];
              $initial = function_exists('mb_substr') ? mb_substr((string) $application['title'], 0, 1, 'UTF-8') : substr((string) $application['title'], 0, 1);
            ?>
            <li class="sp-application-item">
              <span class="sp-course-monogram" aria-hidden="true"><?= spH(strtoupper($initial)) ?></span>
              <span class="sp-application-copy">
                <strong><?= spH($application['title']) ?></strong>
                <small><?= spH($application['category_name']) ?> · Applied <?= spH(spDate($application['created_at'])) ?></small>
              </span>
              <span class="sp-status sp-status-<?= spH($status) ?>"><?= spH($status) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <aside class="sp-panel" aria-labelledby="quickActions">
      <div class="sp-panel-head"><div><h2 id="quickActions">Quick actions</h2><p>Everything you need, one click away</p></div></div>
      <div class="sp-quick-list">
        <a class="sp-quick-link" href="courses.php"><span class="sp-quick-icon" aria-hidden="true">▦</span><span class="sp-quick-copy"><strong>Find a course</strong><small>Browse published courses and apply</small></span><span class="sp-quick-arrow" aria-hidden="true">↗</span></a>
        <a class="sp-quick-link" href="profile.php"><span class="sp-quick-icon" aria-hidden="true">◯</span><span class="sp-quick-copy"><strong>Manage profile</strong><small>Update your details and password</small></span><span class="sp-quick-arrow" aria-hidden="true">↗</span></a>
      </div>
      <p class="sp-help" style="margin-top:18px">Applications are reviewed by the administrator. An application is not an enrolment until it is approved.</p>
    </aside>
  </div>
</main>
<?php spPageEnd(); ?>
