<?php
require_once __DIR__.'/../../config/student_admin.php';
$error=''; $values=[];
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $values=$_POST;
    try {
        checkCsrf(); [$name,$email,$phone,$status,$password]=studentFields(true);
        $q=$pdo->prepare("INSERT INTO users(full_name,email,phone,password_hash,role,status) VALUES(?,?,?,?,'student',?)");
        $q->execute([$name,$email,$phone,password_hash($password,PASSWORD_DEFAULT),$status]);
        success('Student account created successfully.','manage_students.php');
    } catch (Throwable $e) { $error=operationError($e); }
}
startPage('Add student','Create a student account and choose its access status.');
?><section class="card"><h2>Student details</h2><?php if($error):?><div class="notice error" role="alert"><?=h($error)?></div><?php endif;?><form method="post"><?php csrf(); studentForm($values); ?></form></section><?php endPage(); ?>
