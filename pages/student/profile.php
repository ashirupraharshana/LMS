<?php
require_once __DIR__ . '/_portal.php';
require_once __DIR__ . '/../../config/image_helpers.php';

$profileStmt = $pdo->prepare('SELECT username, phone, password_hash, created_at, profile_photo_base64 FROM users WHERE id = ? AND role = ? LIMIT 1');
$profileStmt->execute([(int) $currentUser['id'], 'student']);
$account = $profileStmt->fetch(PDO::FETCH_ASSOC);
if (!$account) {
    http_response_code(403);
    exit('Student account unavailable.');
}

$formName = (string) $currentUser['full_name'];
$formEmail = (string) $currentUser['email'];
$formPhone = (string) ($account['phone'] ?? '');
$error = '';
$success = (string) ($_SESSION['student_profile_success'] ?? '');
unset($_SESSION['student_profile_success']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $formName = is_string($_POST['full_name'] ?? null) ? trim($_POST['full_name']) : '';
    $formEmail = is_string($_POST['email'] ?? null) ? strtolower(trim($_POST['email'])) : '';
    $formPhone = is_string($_POST['phone'] ?? null) ? trim($_POST['phone']) : '';
    $currentPassword = is_string($_POST['current_password'] ?? null) ? $_POST['current_password'] : '';
    $newPassword = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
    $confirmPassword = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';
    $token = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
    $emailChanged = $formEmail !== (string) $currentUser['email'];
    $passwordChanged = $newPassword !== '' || $confirmPassword !== '';

    if (!hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $token)) {
        $error = 'Your session has expired. Refresh the page and try again.';
    } elseif ($formName === '' || spLen($formName) > 120) {
        $error = 'Your full name must be between 1 and 120 characters.';
    } elseif (!filter_var($formEmail, FILTER_VALIDATE_EMAIL) || strlen($formEmail) > 190) {
        $error = 'Enter a valid email address (maximum 190 characters).';
    } elseif (spLen($formPhone) > 30) {
        $error = 'Your phone number must be at most 30 characters.';
    } elseif (($emailChanged || $passwordChanged) && !password_verify($currentPassword, (string) $account['password_hash'])) {
        $error = 'Enter your current password to change your email or password.';
    } elseif ($passwordChanged && (strlen($newPassword) < 8 || strlen($newPassword) > 72)) {
        $error = 'Your new password must contain between 8 and 72 bytes.';
    } elseif ($passwordChanged && $newPassword !== $confirmPassword) {
        $error = 'Your new passwords do not match.';
    } else {
        try {
            $pdo->beginTransaction();
            // Lock the record and recheck the password to avoid updating stale credentials.
            $latestStmt = $pdo->prepare('SELECT password_hash FROM users WHERE id=? AND role=? AND status=? FOR UPDATE');
            $latestStmt->execute([(int) $currentUser['id'], 'student', 'active']);
            $latestHash = $latestStmt->fetchColumn();
            if (!$latestHash) {
                throw new RuntimeException('Your account is no longer active.');
            }
            if (($emailChanged || $passwordChanged) && !password_verify($currentPassword, (string) $latestHash)) {
                $error = 'Current password verification failed. Please try again.';
            } else {
                $photo = lmsUploadImage('profile_photo', $account['profile_photo_base64'] ?? null, 'remove_profile_photo');
                $sql = 'UPDATE users SET full_name=:name, email=:email, phone=:phone, profile_photo_base64=:photo';
                $values = [':name' => $formName, ':email' => $formEmail, ':phone' => $formPhone, ':photo' => $photo, ':id' => (int) $currentUser['id']];
                if ($passwordChanged) {
                    $sql .= ', password_hash=:hash';
                    $values[':hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
                }
                $sql .= ' WHERE id=:id';
                $pdo->prepare($sql)->execute($values);
                $pdo->commit();
                $_SESSION['full_name'] = $formName;
                $_SESSION['email'] = $formEmail;
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                $_SESSION['student_profile_success'] = 'Your profile has been updated successfully.';
                header('Location: profile.php');
                exit;
            }
            if ($pdo->inTransaction()) $pdo->rollBack();
        } catch (InvalidArgumentException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = $e->getMessage();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($e->getCode() === '23000') {
                $error = 'This email address is already used by another account.';
            } else {
                error_log('Student profile update error: ' . $e->getMessage());
                $error = 'Your profile could not be saved. Please try again later.';
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Student profile error: ' . $e->getMessage());
            $error = 'Your profile could not be saved. Please try again later.';
        }
    }
}

$fullName = trim((string) $currentUser['full_name']);
$initial = function_exists('mb_substr') ? mb_substr($fullName, 0, 1, 'UTF-8') : substr($fullName, 0, 1);
spPageStart('Manage Profile', $currentUser, 'profile.php');
?>
<main class="sp-main">
  <div class="sp-page-heading">
    <span class="sp-kicker">ACCOUNT SETTINGS</span>
    <h1>Manage profile<span aria-hidden="true">.</span></h1>
    <p>Keep your student information up to date and protect your account credentials.</p>
  </div>

  <?php if ($success !== ''): ?><div class="sp-message" role="status"><?= spH($success) ?></div><?php endif; ?>
  <?php if ($error !== ''): ?><div class="sp-message sp-message-error" role="alert"><?= spH($error) ?></div><?php endif; ?>

  <div class="sp-profile-grid">
    <div>
      <section class="sp-panel">
        <div class="sp-panel-head"><div><h2>Personal information</h2><p>Changes here update your student account</p></div></div>
        <form class="sp-form" method="post" action="profile.php" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?= spH($_SESSION['csrf_token']) ?>">
          <div class="sp-field sp-profile-photo-field"><label for="profilePhoto">Profile photo (JPG, PNG, WebP · max 1 MB)</label><input id="profilePhoto" type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp" data-image-preview="profilePhotoPreview"><div class="lms-edit-photo" id="profilePhotoPreview"><?php if($currentPhoto=lmsImageDataUri($account['profile_photo_base64']??null)):?><img src="<?=spH($currentPhoto)?>" alt="Current profile photo"><?php else:?><span>Your photo will appear here</span><?php endif;?></div><?php if(!empty($account['profile_photo_base64'])): ?><label class="lms-checkbox"><input type="checkbox" name="remove_profile_photo" value="1"> Remove current photo</label><?php endif; ?></div>
          <div class="sp-form-grid">
            <div class="sp-field"><label for="studentUsername">Username</label><input id="studentUsername" value="<?= spH($account['username'] ?? '') ?>" readonly><p class="sp-help">Your username is assigned by your administrator.</p></div>
            <div class="sp-field"><label for="studentFullName">Full name *</label><input id="studentFullName" name="full_name" required maxlength="120" autocomplete="name" value="<?= spH($formName) ?>"></div>
            <div class="sp-field"><label for="studentEmail">Email *</label><input id="studentEmail" name="email" type="email" maxlength="190" required autocomplete="email" value="<?= spH($formEmail) ?>"></div>
            <div class="sp-field"><label for="studentPhone">Phone number</label><input id="studentPhone" name="phone" type="tel" maxlength="30" autocomplete="tel" value="<?= spH($formPhone) ?>"></div>
          </div>
          <div style="border-top:1px solid #eee;padding-top:22px">
            <div class="sp-panel-head"><div><h2>Account security</h2><p>Leave the new password fields blank to keep your current password</p></div></div>
            <div class="sp-form-grid">
              <div class="sp-field sp-span-all"><label for="currentPassword">Current password</label><input id="currentPassword" type="password" name="current_password" autocomplete="current-password" placeholder="Required when changing email or password"><p class="sp-help">For security, your current password is required to change email or password.</p></div>
              <div class="sp-field"><label for="newPassword">New password</label><input id="newPassword" type="password" name="new_password" minlength="8" maxlength="72" autocomplete="new-password" placeholder="Minimum 8 characters"></div>
              <div class="sp-field"><label for="confirmPassword">Confirm new password</label><input id="confirmPassword" type="password" name="confirm_password" minlength="8" maxlength="72" autocomplete="new-password" placeholder="Repeat new password"></div>
            </div>
          </div>
          <div class="sp-form-actions"><button class="sp-button" type="submit">Save changes ↗</button><a href="dashboard.php" class="sp-button sp-button-outline">Back to dashboard</a></div>
        </form>
      </section>
    </div>
    <aside class="sp-panel sp-profile-summary">
      <div class="sp-profile-avatar" aria-hidden="true"><?php if($photo=lmsImageDataUri($account['profile_photo_base64']??null)):?><img src="<?=spH($photo)?>" alt=""><?php else:?><?=spH(strtoupper($initial))?><?php endif;?></div>
      <h2><?= spH($currentUser['full_name']) ?></h2>
      <p><?= spH($currentUser['email']) ?></p>
      <dl class="sp-summary-data">
        <dt>Account type</dt><dd>Student</dd>
        <dt>Account status</dt><dd>Active</dd>
        <dt>Member since</dt><dd><?= spH(spDate($account['created_at'])) ?></dd>
        <dt>Username</dt><dd><?= spH($account['username'] ?: 'Not assigned') ?></dd>
      </dl>
    </aside>
  </div>
</main>
<script src="../../js/lms-media.js" defer></script>
<?php spPageEnd(); ?>
