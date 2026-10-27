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
            [$name,$email,$phone,$status,$password]=studentFields(false);
            $sql="UPDATE users SET full_name=?,email=?,phone=?,status=?"; $params=[$name,$email,$phone,$status];
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
    elseif ($_SERVER['REQUEST_METHOD']==='POST' && field('action')==='edit') $edit=array_merge($edit,array_intersect_key($_POST,array_flip(['full_name','email','phone','status'])));
}
$search=mb_substr(is_string($_GET['q']??null)?trim($_GET['q']):'',0,100);
$status=is_string($_GET['status']??null)?$_GET['status']:'all';
$where="role='student'"; $params=[];
if (in_array($status,['active','inactive','pending'],true)) { $where.=' AND status=?'; $params[]=$status; }
else $status='all';
if ($search!=='') { $where.=' AND (full_name LIKE ? OR email LIKE ? OR phone LIKE ? OR CAST(id AS CHAR)=?)'; $params=array_merge($params,["%$search%","%$search%","%$search%",$search]); }
$q=$pdo->prepare("SELECT COUNT(*) FROM users WHERE $where"); $q->execute($params); $total=(int)$q->fetchColumn();
$pages=max(1,(int)ceil($total/15)); $page=min($pages,max(1,(int)($_GET['page']??1))); $offset=($page-1)*15;
$q=$pdo->prepare("SELECT id,full_name,email,phone,status,created_at FROM users WHERE $where ORDER BY id DESC LIMIT 15 OFFSET $offset"); $q->execute($params); $rows=$q->fetchAll();
startPage('Manage all students','Search, edit and manage access for every student account.');
if($error):?><div class="notice error" role="alert"><?=h($error)?></div><?php endif;
if($edit):?><section class="card"><h2>Edit student #<?=h($edit['id'])?></h2><form method="post"><?php csrf(); ?><input type="hidden" name="action" value="edit"><input type="hidden" name="id" value="<?=h($edit['id'])?>"><?php studentForm($edit,true); ?></form></section><?php endif; ?>
<section class="card"><div class="section-heading"><h2>Student directory <span class="badge"><?=$total?></span></h2><a class="btn" href="add_student.php">+ Add student</a></div><form class="filters" method="get"><label class="search-field">Search students<input name="q" value="<?=h($search)?>" placeholder="Name, email, phone or ID" maxlength="100"></label><label>Status<select name="status"><?php foreach(['all','active','inactive','pending'] as $s):?><option value="<?=$s?>" <?=$s===$status?'selected':''?>><?=ucfirst($s)?></option><?php endforeach;?></select></label><button class="btn" type="submit">Search</button><a href="manage_students.php">Reset</a></form><div class="table-scroll"><table class="data-table"><thead><tr><th>Student</th><th>Contact</th><th>Status</th><th>Joined</th><th>Actions</th></tr></thead><tbody><?php foreach($rows as $row):?><tr><td><strong><?=h($row['full_name'])?></strong><small>#<?=h($row['id'])?></small></td><td><?=h($row['email'])?><small><?=h($row['phone']?:'—')?></small></td><td><span class="badge <?=h($row['status'])?>"><?=h(ucfirst($row['status']))?></span></td><td><?=h(substr($row['created_at'],0,10))?></td><td><div class="actions"><a class="btn secondary small" href="?edit=<?=$row['id']?>">Edit</a><form method="post"><?php csrf();?><input type="hidden" name="id" value="<?=$row['id']?>"><input type="hidden" name="action" value="status"><input type="hidden" name="status" value="<?=$row['status']==='active'?'inactive':'active'?>"><button class="btn secondary small" type="submit"><?=$row['status']==='active'?'Deactivate':'Activate'?></button></form><form method="post" data-confirm="Delete this student account? This cannot be undone. Application history will be retained."><?php csrf();?><input type="hidden" name="id" value="<?=$row['id']?>"><input type="hidden" name="action" value="delete"><button class="btn danger small" type="submit">Delete</button></form></div></td></tr><?php endforeach; if(!$rows):?><tr><td colspan="5" class="empty">No students found. Try a different search or add a student.</td></tr><?php endif;?></tbody></table></div><div class="pagination"><span><?=$total?> students · Page <?=$page?> of <?=$pages?></span><?php if($page>1):?><a class="btn secondary small" href="?<?=h(http_build_query(['q'=>$search,'status'=>$status,'page'=>$page-1]))?>">Previous</a><?php endif; if($page<$pages):?><a class="btn secondary small" href="?<?=h(http_build_query(['q'=>$search,'status'=>$status,'page'=>$page+1]))?>">Next</a><?php endif;?></div></section><?php endPage(); ?>
