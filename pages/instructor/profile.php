<?php
require_once __DIR__ . '/_portal.php';
$instructorId = (int) $currentInstructor['id'];
$formName=(string)$currentInstructor['full_name'];$formEmail=(string)$currentInstructor['email'];
$formPhone=(string)($currentInstructor['phone']??'');$formBio=(string)($currentInstructor['bio']??'');
$error='';$success=(string)($_SESSION['instructor_profile_success']??'');unset($_SESSION['instructor_profile_success']);
if (($_SERVER['REQUEST_METHOD']??'GET') === 'POST') {
    $value=static fn(string $key):string=>is_string($_POST[$key]??null)?trim($_POST[$key]):'';
    $formName=$value('full_name');$formEmail=strtolower($value('email'));$formPhone=$value('phone');$formBio=$value('bio');
    $currentPassword=is_string($_POST['current_password']??null)?$_POST['current_password']:'';
    $newPassword=is_string($_POST['new_password']??null)?$_POST['new_password']:'';
    $confirm=is_string($_POST['confirm_password']??null)?$_POST['confirm_password']:'';
    $emailChanged=$formEmail!==$currentInstructor['email'];$passwordChanged=$newPassword!==''||$confirm!=='';
    try {
        if (!hash_equals((string)($_SESSION['csrf_token']??''),$value('csrf_token'))) throw new InvalidArgumentException('Your session expired. Refresh and try again.');
        if ($formName===''||ipLen($formName)>120) throw new InvalidArgumentException('Enter a full name of up to 120 characters.');
        if (!filter_var($formEmail,FILTER_VALIDATE_EMAIL)||strlen($formEmail)>190) throw new InvalidArgumentException('Enter a valid email address.');
        if (ipLen($formPhone)>30||ipLen($formBio)>3000) throw new InvalidArgumentException('Phone or biography is too long.');
        if ($passwordChanged && (strlen($newPassword)<8||strlen($newPassword)>72||$newPassword!==$confirm)) throw new InvalidArgumentException('New passwords must match and contain 8–72 bytes.');
        $pdo->beginTransaction();
        $locked=$pdo->prepare('SELECT password_hash,photo_base64 FROM instructors WHERE id=? AND status=? FOR UPDATE');
        $locked->execute([$instructorId,'active']);$row=$locked->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new InvalidArgumentException('Your instructor account is no longer active.');
        if (($emailChanged||$passwordChanged) && !password_verify($currentPassword,(string)$row['password_hash'])) throw new InvalidArgumentException('Enter your current password to change email or password.');
        if ($emailChanged) {
            $exists=$pdo->prepare('SELECT id FROM users WHERE email=? LIMIT 1');$exists->execute([$formEmail]);
            if ($exists->fetchColumn()) throw new InvalidArgumentException('Email is already used by another LMS account.');
        }
        $photo=lmsUploadImage('photo',$row['photo_base64'],'remove_photo');
        $sql='UPDATE instructors SET full_name=?,email=?,phone=?,bio=?,photo_base64=?';
        $params=[$formName,$formEmail,$formPhone?:null,$formBio?:null,$photo];
        if($passwordChanged){$sql.=',password_hash=?';$params[]=password_hash($newPassword,PASSWORD_DEFAULT);}
        $sql.=' WHERE id=?';$params[]=$instructorId;
        $pdo->prepare($sql)->execute($params);
        $pdo->commit();
        $_SESSION['full_name']=$formName;$_SESSION['email']=$formEmail;
        $_SESSION['csrf_token']=bin2hex(random_bytes(32));
        $_SESSION['instructor_profile_success']='Your instructor profile was updated successfully.';
        header('Location: profile.php');exit;
    } catch(InvalidArgumentException $e){if($pdo->inTransaction())$pdo->rollBack();$error=$e->getMessage();}
    catch(PDOException $e){if($pdo->inTransaction())$pdo->rollBack();error_log('Instructor profile: '.$e->getMessage());$error=$e->getCode()==='23000'?'This email is already in use.':'Unable to update your profile.';}
    catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('Instructor profile: '.$e->getMessage());$error='Unable to save your profile.';}
}
ipPageStart('Manage Profile');
?>
<main class="sp-main">
<div class="sp-page-heading"><span class="sp-kicker">INSTRUCTOR ACCOUNT</span><h1>Manage profile<span aria-hidden="true">.</span></h1><p>Keep your teaching contact details and login credentials up to date.</p></div>
<?php if($success): ?><div class="sp-message" role="status"><?=ipH($success)?></div><?php endif; ?>
<?php if($error): ?><div class="sp-message sp-message-error" role="alert"><?=ipH($error)?></div><?php endif; ?>
<div class="sp-profile-grid"><section class="sp-panel"><div class="sp-panel-head"><div><h2>Account information</h2><p>Your instructor identity and security</p></div></div>
<form class="sp-form" method="post" enctype="multipart/form-data">
  <input type="hidden" name="csrf_token" value="<?=ipH($_SESSION['csrf_token'])?>">
  <div class="sp-field"><label for="photo">Profile photo (JPG / PNG / WebP, max 1 MB)</label><input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp" data-image-preview="instructorPhotoPreview">
    <div id="instructorPhotoPreview" class="lms-edit-photo"><?php if($photo=lmsImageDataUri($currentInstructor['photo_base64']??null)): ?><img src="<?=ipH($photo)?>" alt="Current instructor photo"><?php else: ?><span>Your photo appears here</span><?php endif; ?></div>
    <?php if(!empty($currentInstructor['photo_base64'])): ?><label class="lms-checkbox"><input type="checkbox" name="remove_photo" value="1"> Remove current photo</label><?php endif; ?></div>
  <div class="sp-form-grid">
    <div class="sp-field"><label for="full_name">Full name *</label><input id="full_name" name="full_name" maxlength="120" required value="<?=ipH($formName)?>" autocomplete="name"></div>
    <div class="sp-field"><label for="email">Email *</label><input id="email" type="email" name="email" maxlength="190" required value="<?=ipH($formEmail)?>" autocomplete="email"></div>
    <div class="sp-field"><label for="phone">Phone</label><input id="phone" name="phone" maxlength="30" value="<?=ipH($formPhone)?>" autocomplete="tel"></div>
    <div class="sp-field"><label>Specialization<input value="<?=ipH($currentInstructor['specialization'])?>" readonly></label></div>
    <div class="sp-field sp-span-all"><label for="bio">Biography</label><textarea id="bio" name="bio" maxlength="3000" rows="4"><?=ipH($formBio)?></textarea></div>
  </div>
  <div class="sp-panel-head ip-security-heading"><div><h2>Password & security</h2><p>Current password is required to change email or password</p></div></div>
  <div class="sp-form-grid"><div class="sp-field sp-span-all"><label for="current_password">Current password</label><input id="current_password" name="current_password" type="password" autocomplete="current-password"></div>
    <div class="sp-field"><label for="new_password">New password (optional)</label><input id="new_password" name="new_password" type="password" minlength="8" maxlength="72" autocomplete="new-password"></div>
    <div class="sp-field"><label for="confirm_password">Confirm new password</label><input id="confirm_password" name="confirm_password" type="password" minlength="8" maxlength="72" autocomplete="new-password"></div></div>
  <div class="sp-form-actions"><button class="sp-button" type="submit">Save changes</button><a class="sp-button sp-button-outline" href="dashboard.php">Cancel</a></div>
</form></section>
<aside class="sp-panel sp-profile-summary"><div class="sp-profile-avatar"><?php if($photo=lmsImageDataUri($currentInstructor['photo_base64']??null)):?><img src="<?=ipH($photo)?>" alt=""><?php else: ?><?=ipH(strtoupper(substr($currentInstructor['full_name'],0,1)))?><?php endif;?></div><h2><?=ipH($currentInstructor['full_name'])?></h2><p><?=ipH($currentInstructor['email'])?></p><dl class="sp-summary-data"><dt>Role</dt><dd>Instructor</dd><dt>Status</dt><dd><?=ipH(ucfirst($currentInstructor['status']))?></dd><dt>Specialization</dt><dd><?=ipH($currentInstructor['specialization'])?></dd><dt>Qualifications</dt><dd><?=ipH($currentInstructor['qualifications']?:'Not provided')?></dd></dl></aside></div>
</main>
<?php ipPageEnd(); ?>
