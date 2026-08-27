<?php
session_start();
if(!isset($_SESSION['user_id'])){
    header("Location: ../login.php");
    exit();
}
if($_SESSION['role'] != 'admin'){
    header("Location: ../admin/dashboard.php");
    exit();
}
require_once '../core/User.php';

$id = (int)($_GET['id'] ?? 0);
if(!$id || $id == $_SESSION['user_id']){
    header("Location: list_users.php");
    exit();
}

(new User())->delete($id);
header("Location: list_users.php?success=deleted");
exit();
?>