<?php
require_once __DIR__.'/../../config/student_admin.php';
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        checkCsrf(); $id=filter_var(field('id'),FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if (!$id) throw new InvalidArgumentException('Select a valid student.');
        $q=$pdo->prepare("SELECT id FROM users WHERE id=? AND role='student'"); $q->execute([$id]);
        if (!$q->fetch()) throw new InvalidArgumentException('This student no longer exists.');
        if (field('action')==='delete') {
            $q=$pdo->prepare("DELETE FROM users WHERE id=? AND role='student'"); $q->execute([$id]);
            success('Student deleted. Application history has been retained.','manage_students.php');
        } elseif (field('action')==='status') {
            $status=field('status');
            if (!in_array($status,['active','inactive'],true)) throw new InvalidArgumentException('Choose a valid status.');
            $q=$pdo->prepare("UPDATE users SET status=? WHERE id=? AND role='student'"); $q->execute([$status,$id]);
            success('Student marked '.$status.'.','manage_students.php');
        } elseif (field('action')==='edit') {
            [$name,$email,$phone,$status,$password,$username]=studentFields(false);
            $photoStmt=$pdo->prepare("SELECT profile_photo_base64 FROM users WHERE id=? AND role='student'");
            $photoStmt->execute([$id]);
            $photo=lmsUploadImage('profile_photo',$photoStmt->fetchColumn()?:null,'remove_profile_photo');
            $sql="UPDATE users SET full_name=?,email=?,phone=?,status=?,profile_photo_base64=?"; $params=[$name,$email,$phone,$status,$photo];
            if ($username!=='') { $sql.=',username=?'; $params[]=$username; }
            if ($password!=='') { $sql.=',password_hash=?'; $params[]=password_hash($password,PASSWORD_DEFAULT); }
            $sql.=" WHERE id=? AND role='student'"; $params[]=$id; $pdo->prepare($sql)->execute($params);
            success('Student details updated.','manage_students.php');
        } else throw new InvalidArgumentException('Invalid action.');
    } catch (Throwable $e) { $error=operationError($e); }
}
$edit=null;
$editId=(int)($_GET['edit']??0);
if ($editId>0) {
    $q=$pdo->prepare("SELECT * FROM users WHERE id=? AND role='student'"); $q->execute([$editId]); $edit=$q->fetch();
    if (!$edit) $error='Student not found.';
    elseif ($_SERVER['REQUEST_METHOD']==='POST' && field('action')==='edit') $edit=array_merge($edit,array_intersect_key($_POST,array_flip(['full_name','email','phone','status','username'])));
}
$search=(function_exists('mb_substr') ? mb_substr(is_string($_GET['q']??null)?trim($_GET['q']):'',0,100,'UTF-8') : substr(is_string($_GET['q']??null)?trim($_GET['q']):'',0,100));
$status=is_string($_GET['status']??null)?$_GET['status']:'all';
$where="role='student'"; $params=[];
if (in_array($status,['active','inactive','pending'],true)) { $where.=' AND status=?'; $params[]=$status; }
else $status='all';

$q=$pdo->prepare("SELECT COUNT(*) FROM users WHERE $where"); $q->execute($params); $total=(int)$q->fetchColumn();

$q=$pdo->prepare("SELECT id,full_name,username,email,phone,status,created_at,profile_photo_base64 FROM users WHERE $where ORDER BY id DESC "); $q->execute($params); $rows=$q->fetchAll();
startPage('Manage all students','Search, edit and manage access for every student account.');
if($error):?><div class="notice error" role="alert"><?=h($error)?></div><?php endif;
if($edit):?><section class="card"><h2>Edit student #<?=h($edit['id'])?></h2><form method="post" enctype="multipart/form-data"><?php csrf(); ?><input type="hidden" name="action" value="edit"><input type="hidden" name="id" value="<?=h($edit['id'])?>"><?php studentForm($edit,true); ?></form></section><?php endif; ?>
<section class="card"><div class="section-heading"><h2>Student directory <span class="badge"><?=$total?></span></h2><a class="btn" href="add_student.php">+ Add student</a></div><form class="filters" method="get"><label>Status<select name="status"><?php foreach(['all','active','inactive','pending'] as $s):?><option value="<?=$s?>" <?=$s===$status?'selected':''?>><?=ucfirst($s)?></option><?php endforeach;?></select></label><button class="btn" type="submit">Search</button><a href="manage_students.php">Reset</a></form><div class="table-scroll"><table class="data-table lms-admin-data" data-admin-datatable data-search="<?=h($search)?>" data-empty="No students found."><thead><tr><th>Student</th><th>Contact</th><th>Status</th><th>Joined</th><th data-dt-order="disable">Actions</th></tr></thead><tbody><?php foreach($rows as $row):?><tr><td><div class="lms-person-cell"><span class="lms-table-avatar"><?php if($photo=lmsImageDataUri($row['profile_photo_base64']??null)):?><img src="<?=h($photo)?>" alt="" loading="lazy"><?php else:?><?=h(strtoupper(substr($row['full_name'],0,1)))?><?php endif;?></span><span><strong><?=h($row['full_name'])?></strong><small>@<?=h($row['username']?:'not assigned')?> · #<?=h($row['id'])?></small></span></div></td><td><?=h($row['email'])?><small><?=h($row['phone']?:'—')?></small></td><td><span class="badge <?=h($row['status'])?>"><?=h(ucfirst($row['status']))?></span></td><td><?=h(substr($row['created_at'],0,10))?></td><td><div class="actions"><a class="btn secondary small" href="?edit=<?=$row['id']?>">Edit</a><form method="post"><?php csrf();?><input type="hidden" name="id" value="<?=$row['id']?>"><input type="hidden" name="action" value="status"><input type="hidden" name="status" value="<?=$row['status']==='active'?'inactive':'active'?>"><button class="btn secondary small" type="submit"><?=$row['status']==='active'?'Deactivate':'Activate'?></button></form><form method="post" data-confirm="Delete this student account? This cannot be undone. Application history will be retained."><?php csrf();?><input type="hidden" name="id" value="<?=$row['id']?>"><input type="hidden" name="action" value="delete"><button class="btn danger small" type="submit">Delete</button></form></div></td></tr><?php endforeach; ?></tbody></table></div></section><?php endPage(); ?>
