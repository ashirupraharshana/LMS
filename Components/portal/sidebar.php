<?php
/** Shared authenticated portal navigation. Caller supplies role, account and links. */
require_once __DIR__ . '/../../config/image_helpers.php';
$escape = static fn(mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$initial = function_exists('mb_substr') ? mb_substr(trim($portalName), 0, 1, 'UTF-8') : substr(trim($portalName), 0, 1);
$avatar = lmsImageDataUri($portalPhoto);
?>
<div class="portal-backdrop" id="portalBackdrop" hidden></div>
<aside class="portal-sidebar" id="portalSidebar" aria-label="<?= $escape(ucfirst($portalRole)) ?> sidebar navigation">
  <a class="portal-brand" href="dashboard.php"><span class="portal-brand-mark">L.</span><span>LMS<small><?= $escape(strtoupper($portalRole)) ?> PORTAL</small></span></a>
  <div class="portal-account">
    <span class="portal-avatar" aria-hidden="true"><?php if ($avatar): ?><img src="<?= $escape($avatar) ?>" alt=""><?php else: ?><?= $escape(strtoupper($initial)) ?><?php endif; ?></span>
    <span class="portal-account-details"><strong><?= $escape($portalName) ?></strong><small><?= $escape($portalEmail) ?></small></span>
  </div>
  <p class="portal-nav-caption">WORKSPACE</p>
  <nav class="portal-links" aria-label="<?= $escape(ucfirst($portalRole)) ?> navigation">
    <?php foreach ($portalItems as $link): ?>
      <?php $selected = in_array($portalActive, $link['active'], true); ?>
      <a class="portal-link <?= $selected ? 'is-active' : '' ?>" href="<?= $escape($link['href']) ?>" <?= $selected ? 'aria-current="page"' : '' ?>><i class="<?= $escape($link['icon']) ?>" aria-hidden="true"></i><span><?= $escape($link['name']) ?></span></a>
    <?php endforeach; ?>
  </nav>
  <div class="portal-sidebar-end">
    <form action="../admin/logout.php" method="post" data-confirm="Sign out of your LMS account?">
      <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['csrf_token'] ?? '') ?>">
      <button class="portal-signout" type="submit"><i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i> Log out</button>
    </form>
  </div>
</aside>
<header class="portal-topbar" aria-label="Portal top navigation">
  <div class="portal-topbar-left">
    <button type="button" id="portalMenuToggle" class="portal-toggle" aria-label="Open sidebar" aria-controls="portalSidebar" aria-expanded="false"><i class="fa-solid fa-bars" aria-hidden="true"></i></button>
    <span class="portal-breadcrumb">LMS <span aria-hidden="true">/</span> <strong><?= $escape($portalPageLabel) ?></strong></span>
  </div>
  <div class="portal-topbar-right">
    <span class="portal-role-chip"><?= $escape(ucfirst($portalRole)) ?></span>
    <a class="portal-top-account" href="profile.php"><span class="portal-top-avatar" aria-hidden="true"><?php if ($avatar): ?><img src="<?= $escape($avatar) ?>" alt=""><?php else: ?><?= $escape(strtoupper($initial)) ?><?php endif; ?></span><span><?= $escape($portalName) ?></span></a>
  </div>
</header>
