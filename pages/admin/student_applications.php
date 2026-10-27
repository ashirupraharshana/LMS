<?php
require_once __DIR__.'/../../config/student_admin.php';
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        checkCsrf(); $id=filter_var(field('id'),FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if (!$id) throw new InvalidArgumentException('Select a valid application.');
        $pdo->beginTransaction();
        $q=$pdo->prepare('SELECT * FROM course_applications WHERE id=? FOR UPDATE'); $q->execute([$id]); $app=$q->fetch();
        if (!$app) throw new InvalidArgumentException('Application not found.');
        if (field('action')==='review') {
            $decision=field('decision'); $note=field('admin_note');
            if (!in_array($decision,['approved','declined'],true)) throw new InvalidArgumentException('Choose approve or decline.');
            if ($app['status']!=='pending') throw new InvalidArgumentException('This application was already reviewed. Refresh the page.');
            if (mb_strlen($note)>500) throw new InvalidArgumentException('Review note must be at most 500 characters.');
            $q=$pdo->prepare('UPDATE course_applications SET status=?,admin_note=?,reviewed_by=?,reviewed_at=NOW() WHERE id=?');
            $q->execute([$decision,$note,$_SESSION['user_id'],$id]);
            $message='Application '.$decision.'.';
        } elseif (field('action')==='enrol') {
            if ($app['status']!=='approved' || $app['student_user_id']) throw new InvalidArgumentException('Only approved, unlinked applications can be enrolled.');
            $q=$pdo->prepare('SELECT id,role FROM users WHERE email=? FOR UPDATE'); $q->execute([$app['email']]); $existing=$q->fetch();
            if ($existing && $existing['role']!=='student') throw new InvalidArgumentException('This email belongs to an administrator.');
            if ($existing) $studentId=$existing['id'];
            else {
                $password=is_string($_POST['password']??null)?$_POST['password']:'';
                $confirm=is_string($_POST['confirm_password']??null)?$_POST['confirm_password']:'';
                if (strlen($password)<8 || strlen($password)>72 || $password!==$confirm) throw new InvalidArgumentException('Enter matching initial passwords of 8–72 bytes for the new account.');
                $q=$pdo->prepare("INSERT INTO users(full_name,email,phone,password_hash,role,status) VALUES(?,?,?,?,'student','active')");
                $q->execute([$app['full_name'],$app['email'],$app['phone'],password_hash($password,PASSWORD_DEFAULT)]); $studentId=$pdo->lastInsertId();
            }
            $pdo->prepare('UPDATE course_applications SET student_user_id=? WHERE id=?')->execute([$studentId,$id]);
            $message=$existing?'Existing student linked. Their password and account status were preserved.':'Student created and linked to the approved course application.';
        } else throw new InvalidArgumentException('Invalid action.');
        $pdo->commit(); success($message,'student_applications.php?view='.$id);
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); $error=operationError($e); }
}
$search=mb_substr(is_string($_GET['q']??null)?trim($_GET['q']):'',0,100);
$status=is_string($_GET['status']??null)?$_GET['status']:'all'; $params=[]; $where='1=1';
if(in_array($status,['pending','approved','declined'],true)) {$where.=' AND a.status=?';$params[]=$status;} else $status='all';
if($search!=='') {$where.=' AND (a.full_name LIKE ? OR a.email LIKE ? OR c.title LIKE ?)';$params=array_merge($params,["%$search%","%$search%","%$search%"]);}
$q=$pdo->prepare("SELECT COUNT(*) FROM course_applications a JOIN courses c ON c.id=a.course_id WHERE $where");$q->execute($params);$total=(int)$q->fetchColumn();
$pages=max(1,(int)ceil($total/15));$page=min($pages,max(1,(int)($_GET['page']??1)));$offset=($page-1)*15;
$q=$pdo->prepare("SELECT a.*,c.title FROM course_applications a JOIN courses c ON c.id=a.course_id WHERE $where ORDER BY a.id DESC LIMIT 15 OFFSET $offset");$q->execute($params);$rows=$q->fetchAll();
$detail=null;
if(isset($_GET['view'])) {$q=$pdo->prepare('SELECT a.*,c.title,u.status AS student_status FROM course_applications a JOIN courses c ON c.id=a.course_id LEFT JOIN users u ON u.id=a.student_user_id WHERE a.id=?');$q->execute([(int)$_GET['view']]);$detail=$q->fetch();if(!$detail)$error='Application not found.';}
startPage('Student applications','Review course applications, record decisions and create or link student accounts.');
if($error):?><div class="notice error" role="alert"><?=h($error)?></div><?php endif;
if($detail):?><section class="card"><div class="section-heading"><h2>Application #<?=$detail['id']?></h2><a href="student_applications.php">Close details</a></div><dl class="details"><div><dt>Applicant</dt><dd><?=h($detail['full_name'])?></dd></div><div><dt>Course</dt><dd><?=h($detail['title'])?></dd></div><div><dt>Email</dt><dd><?=h($detail['email'])?></dd></div><div><dt>Phone</dt><dd><?=h($detail['phone']?:'—')?></dd></div><div><dt>Status</dt><dd><span class="badge <?=h($detail['status'])?>"><?=h($detail['status'])?></span></dd></div><div><dt>Submitted</dt><dd><?=h($detail['created_at'])?></dd></div></dl><h3>Applicant message</h3><p class="preserve"><?=h($detail['message']?:'No message provided.')?></p>
<?php if($detail['status']==='pending'):?><form method="post"><?php csrf();?><input type="hidden" name="id" value="<?=$detail['id']?>"><input type="hidden" name="action" value="review"><label>Review note<textarea name="admin_note" rows="3" maxlength="500"><?=h(field('admin_note'))?></textarea></label><div class="actions"><button class="btn" name="decision" value="approved">Approve application</button><button class="btn secondary" name="decision" value="declined">Decline application</button></div></form><?php else:?><p><strong>Review note:</strong> <?=h($detail['admin_note']?:'No note')?></p><p class="muted">Reviewed <?=h($detail['reviewed_at'])?></p><?php endif;?>
<?php if($detail['status']==='approved' && !$detail['student_user_id']):?><hr><h3>Create / link student account</h3><p class="muted">If this email already has a student account, it will be linked without changing its password or status. Otherwise, enter an initial password below.</p><form method="post"><?php csrf();?><input type="hidden" name="id" value="<?=$detail['id']?>"><input type="hidden" name="action" value="enrol"><div class="form-grid"><label>Initial password<input type="password" name="password" minlength="8" maxlength="72" autocomplete="new-password"></label><label>Confirm password<input type="password" name="confirm_password" minlength="8" maxlength="72" autocomplete="new-password"></label></div><button class="btn">Create / link student</button></form><?php elseif($detail['student_user_id']):?><p class="notice">Linked to student #<?=$detail['student_user_id']?> (<?=h($detail['student_status'])?>). <a href="manage_students.php?edit=<?=$detail['student_user_id']?>">Manage student</a></p><?php endif;?></section><?php endif;?>
<section class="card"><h2>All applications <span class="badge"><?=$total?></span></h2><form class="filters" method="get"><label class="search-field">Search applications<input name="q" value="<?=h($search)?>" maxlength="100" placeholder="Name, email or course"></label><label>Status<select name="status"><?php foreach(['all','pending','approved','declined'] as $s):?><option value="<?=$s?>" <?=$s===$status?'selected':''?>><?=ucfirst($s)?></option><?php endforeach;?></select></label><button class="btn">Search</button><a href="student_applications.php">Reset</a></form><div class="table-scroll"><table class="data-table"><thead><tr><th>Applicant</th><th>Course</th><th>Status</th><th>Account</th><th>Action</th></tr></thead><tbody><?php foreach($rows as $row):?><tr><td><strong><?=h($row['full_name'])?></strong><small><?=h($row['email'])?></small></td><td><?=h($row['title'])?></td><td><span class="badge <?=h($row['status'])?>"><?=h($row['status'])?></span></td><td><?=$row['student_user_id']?'Linked #'.(int)$row['student_user_id']:'Not linked'?></td><td><a class="btn secondary small" href="?view=<?=$row['id']?>">View / manage</a></td></tr><?php endforeach;if(!$rows):?><tr><td colspan="5" class="empty">No applications found.</td></tr><?php endif;?></tbody></table></div><div class="pagination"><span><?=$total?> applications · Page <?=$page?> of <?=$pages?></span><?php if($page>1):?><a class="btn secondary small" href="?<?=h(http_build_query(['q'=>$search,'status'=>$status,'page'=>$page-1]))?>">Previous</a><?php endif;if($page<$pages):?><a class="btn secondary small" href="?<?=h(http_build_query(['q'=>$search,'status'=>$status,'page'=>$page+1]))?>">Next</a><?php endif;?></div></section><?php endPage();?>
