<?php
require_once __DIR__.'/../../config/student_admin.php';
$search=substr(trim((string)($_GET['q']??'')),0,100);
$role=(string)($_GET['role']??'all'); if(!in_array($role,['all','admin','student'],true))$role='all';
$where='1=1'; $params=[];
if($role!=='all'){$where.=' AND role=?';$params[]=$role;}

$stmt=$pdo->prepare("SELECT id,full_name,username,email,role,status,created_at FROM users WHERE $where ORDER BY role ASC, full_name ASC");$stmt->execute($params);$users=$stmt->fetchAll();
startPage('Users','Read-only directory of administrators and students.');
?>
<section class="card"><div class="section-heading"><h2>User management <span class="badge"><?=count($users)?></span></h2><a class="btn secondary" href="add_student.php">+ Add student</a></div>
<form method="get" class="filters"><label>Role<select name="role"><?php foreach(['all','admin','student'] as $r):?><option value="<?=$r?>" <?=$role===$r?'selected':''?>><?=ucfirst($r)?></option><?php endforeach;?></select></label><button class="btn" type="submit">Filter</button></form>
<div class="table-scroll"><table class="data-table lms-admin-data" data-admin-datatable data-search="<?=h($search)?>" data-empty="No matching users."><thead><tr><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th></tr></thead><tbody><?php foreach($users as $u): ?><tr><td><?=h($u['full_name'])?></td><td><?=h($u['username']??'—')?></td><td><?=h($u['email'])?></td><td><span class="badge"><?=h($u['role'])?></span></td><td><span class="badge"><?=h($u['status'])?></span></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php endPage(); ?>
