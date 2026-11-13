<?php
require_once __DIR__.'/../../config/student_admin.php';
require_once __DIR__.'/../../config/image_helpers.php';
require_once __DIR__.'/../../config/design_media.php';
$view=(string)($_GET['view']??'all');
if(!in_array($view,['all','active','inactive','create'],true))$view='all';
$error=''; $editing=null;
$editId=filter_var($_GET['edit']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if($editId){$q=$pdo->prepare('SELECT * FROM instructors WHERE id=?');$q->execute([$editId]);$editing=$q->fetch()?:null; if(!$editing)$error='Instructor not found.';}
if($_SERVER['REQUEST_METHOD']==='POST'){
 try {
    checkCsrf(); $action=field('action');
    $id=filter_var(field('id'),FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if(in_array($action,['delete','status'],true)){
        if(!$id) throw new InvalidArgumentException('Select a valid instructor.');
        if($action==='delete'){
            $pdo->prepare('DELETE FROM instructors WHERE id=?')->execute([$id]);
            success('Instructor removed. Assigned courses are retained.','instructors.php?view=all');
        }
        $status=field('status');
        if(!in_array($status,['active','inactive'],true))throw new InvalidArgumentException('Invalid instructor status.');
        $pdo->prepare('UPDATE instructors SET status=? WHERE id=?')->execute([$status,$id]);
        success('Instructor status updated.','instructors.php?view='.$status);
    }
    if(!in_array($action,['create','edit'],true))throw new InvalidArgumentException('Invalid action.');
    if($action==='edit' && !$id) throw new InvalidArgumentException('Select a valid instructor.');
    $name=field('full_name');$email=strtolower(field('email'));$phone=field('phone');$specialization=field('specialization');$qualifications=field('qualifications');$bio=field('bio');$status=field('status');
    $len=static fn(string $s)=>function_exists('mb_strlen')?mb_strlen($s,'UTF-8'):strlen($s);
    if($name===''||$len($name)>120)throw new InvalidArgumentException('Name must have 1–120 characters.');
    if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>190)throw new InvalidArgumentException('Enter a valid email.');
    if($len($phone)>30)throw new InvalidArgumentException('Phone must not exceed 30 characters.');
    if($specialization===''||$len($specialization)>160)throw new InvalidArgumentException('Enter a specialization (max 160 characters).');
    if($len($qualifications)>500||$len($bio)>3000)throw new InvalidArgumentException('Qualifications or bio is too long.');
    if(!in_array($status,['active','inactive'],true))throw new InvalidArgumentException('Choose a valid status.');
    $previous=null;
    if($action==='edit'){$q=$pdo->prepare('SELECT photo_base64 FROM instructors WHERE id=?');$q->execute([$id]);$record=$q->fetch();if(!$record)throw new InvalidArgumentException('Instructor not found.');$previous=$record['photo_base64'];}
    $photo=lmsUploadImage('photo',$previous,'remove_photo');
    if($action==='create'){
      $q=$pdo->prepare('INSERT INTO instructors(full_name,email,phone,specialization,qualifications,bio,status,photo_base64) VALUES(?,?,?,?,?,?,?,?)');
      $q->execute([$name,$email,$phone?:null,$specialization,$qualifications?:null,$bio?:null,$status,$photo]);
    } else {
      $q=$pdo->prepare('UPDATE instructors SET full_name=?,email=?,phone=?,specialization=?,qualifications=?,bio=?,status=?,photo_base64=? WHERE id=?');
      $q->execute([$name,$email,$phone?:null,$specialization,$qualifications?:null,$bio?:null,$status,$photo,$id]);
    }
    success('Instructor '.($action==='create'?'created':'updated').' successfully.','instructors.php?view=all');
 } catch(Throwable $e) { $error=operationError($e); }
}
$search=substr(trim((string)($_GET['q']??'')),0,100);
$where='1=1';$params=[];
if($view==='active'||$view==='inactive'){$where.=' AND status=?';$params[]=$view;}

$list=$pdo->prepare("SELECT id,full_name,email,phone,specialization,qualifications,bio,status,photo_base64 FROM instructors WHERE $where ORDER BY id DESC");$list->execute($params);$instructors=$list->fetchAll();
$counts=$pdo->query("SELECT COUNT(*) AS total, COALESCE(SUM(status='active'),0) AS active, COALESCE(SUM(status='inactive'),0) AS inactive FROM instructors")->fetch();
$values = ($_SERVER['REQUEST_METHOD']==='POST' && in_array(field('action'),['create','edit'],true)) ? $_POST : ($editing?:[]);
$isForm=$view==='create'||$editing!==null||($_SERVER['REQUEST_METHOD']==='POST' && in_array(field('action'),['create','edit'],true));
$pageTitle=$isForm?($editing?'Edit instructor':'Create instructor'):ucfirst($view).' instructors';
startPage($pageTitle,'Create, assign and maintain instructor profiles and availability.');
?>
<div class="lms-module-hero">
  <div><span class="lms-module-kicker">FACULTY DIRECTORY</span><h2>People behind the learning.</h2><p>Organize your teaching team, course assignments, and instructor status in one place.</p>
    <a class="btn lms-hero-action" href="instructors.php?view=create">+ New instructor</a></div>
  <div class="lms-decorative-slot"><?php if($hero=lmsDesignImage('instructor_hero')): ?><img src="<?=h($hero)?>" alt="Instructor showcase"> <?php else: ?><span class="lms-slot-icon">✧</span><strong>Instructor showcase photo</strong><small>Add the path in config/design_media.php</small><?php endif; ?></div>
</div>
<div class="lms-stat-row"><a href="instructors.php?view=all" class="lms-mini-stat"><span>Total instructors</span><strong><?=number_format((int)$counts['total'])?></strong></a><a href="instructors.php?view=active" class="lms-mini-stat"><span>Active instructors</span><strong><?=number_format((int)$counts['active'])?></strong></a><a href="instructors.php?view=inactive" class="lms-mini-stat"><span>Inactive instructors</span><strong><?=number_format((int)$counts['inactive'])?></strong></a></div>
<?php if($error): ?><div class="notice error" role="alert"><?=h($error)?></div><?php endif; ?>
<?php if($isForm): ?>
<section class="card"><div class="section-heading"><h2><?= $editing?'Edit instructor details':'Register new instructor' ?></h2><a class="btn secondary" href="instructors.php?view=all">Back to directory</a></div>
<form method="post" enctype="multipart/form-data" class="lms-instructor-form">
  <?php csrf(); ?><input type="hidden" name="action" value="<?= $editing?'edit':'create' ?>"><?php if($editing):?><input type="hidden" name="id" value="<?=(int)$editing['id']?>"><?php endif; ?>
  <div class="form-grid">
    <label>Full name *<input type="text" name="full_name" maxlength="120" required value="<?=h($values['full_name']??'')?>" autocomplete="name"></label>
    <label>Email *<input type="email" name="email" maxlength="190" required value="<?=h($values['email']??'')?>" autocomplete="email"></label>
    <label>Phone<input type="tel" name="phone" maxlength="30" value="<?=h($values['phone']??'')?>" autocomplete="tel"></label>
    <label>Specialization *<input type="text" name="specialization" maxlength="160" required value="<?=h($values['specialization']??'')?>" placeholder="e.g. Software Engineering"></label>
    <label>Qualifications<input type="text" name="qualifications" maxlength="500" value="<?=h($values['qualifications']??'')?>" placeholder="e.g. BSc, MSc"></label>
    <label>Status<select name="status"><option value="active" <?=($values['status']??'active')==='active'?'selected':''?>>Active</option><option value="inactive" <?=($values['status']??'active')==='inactive'?'selected':''?>>Inactive</option></select></label>
    <label class="lms-full-width">Biography<textarea name="bio" maxlength="3000" rows="4" placeholder="Briefly introduce this instructor..."><?=h($values['bio']??'')?></textarea></label>
    <label class="lms-full-width">Instructor photo (JPG, PNG, WebP · max 1 MB)<input type="file" name="photo" accept="image/jpeg,image/png,image/webp" data-image-preview="instructorPreview"></label>
  </div>
  <div class="lms-edit-photo" id="instructorPreview"><?php if($editing && lmsImageDataUri($editing['photo_base64']??null)):?><?=lmsImageHtml($editing['photo_base64'],'Current instructor photo')?><?php else:?><span>Photo preview</span><?php endif;?></div>
  <?php if($editing && !empty($editing['photo_base64'])):?><label class="lms-checkbox"><input type="checkbox" name="remove_photo" value="1"> Remove existing photo</label><?php endif;?>
  <div class="actions"><button type="submit" class="btn"><?= $editing?'Save instructor':'Create instructor' ?></button><a class="btn secondary" href="instructors.php?view=all">Cancel</a></div>
</form></section>
<?php else: ?>
<section class="card"><div class="section-heading"><h2><?=ucfirst($view)?> instructors <span class="badge"><?=count($instructors)?></span></h2><a class="btn" href="instructors.php?view=create">+ Create instructor</a></div>
<div class="table-scroll lms-instructor-list">
<table class="data-table lms-admin-data" data-admin-datatable data-search="<?=h($search)?>" data-empty="No instructors found.">
<thead><tr><th>Instructor</th><th>Specialization</th><th>Email</th><th>Status</th><th data-dt-order="disable">Actions</th></tr></thead>
<tbody>
<?php foreach ($instructors as $instructor): ?>
<tr>
  <td><div class="lms-person-cell"><span class="lms-table-avatar"><?php if($photo=lmsImageDataUri($instructor['photo_base64']??null)):?><img src="<?=h($photo)?>" alt="" loading="lazy"><?php else:?><?=h(strtoupper(substr($instructor['full_name'],0,1)))?><?php endif;?></span><span><strong><?=h($instructor['full_name'])?></strong><small>#<?=(int)$instructor['id']?></small></span></div></td>
  <td><?=h($instructor['specialization'])?></td>
  <td><?=h($instructor['email'])?><small><?=h($instructor['phone']?:'—')?></small></td>
  <td><span class="badge <?=h($instructor['status'])?>"><?=h(ucfirst($instructor['status']))?></span></td>
  <td><div class="actions lms-dt-actions">
    <a class="btn secondary small" href="instructors.php?edit=<?=(int)$instructor['id']?>">Edit</a>
    <form method="post"><?php csrf();?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?=(int)$instructor['id']?>"><input type="hidden" name="status" value="<?=$instructor['status']==='active'?'inactive':'active'?>"><button class="btn secondary small" type="submit"><?=$instructor['status']==='active'?'Deactivate':'Activate'?></button></form>
    <form method="post" data-confirm="Delete this instructor? Assigned courses will remain but will become unassigned."><?php csrf();?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=(int)$instructor['id']?>"><button class="btn danger small" type="submit">Delete</button></form>
  </div></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
</section>
<?php endif; ?>
<script src="../../js/lms-media.js" defer></script>
<?php endPage(); ?>
