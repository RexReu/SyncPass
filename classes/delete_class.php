<?php
session_start();
if(!isset($_SESSION['user_id'])){
    header("Location: ../login.php");
    exit();
}
if($_SESSION['role'] != 'admin'){
    header("Location: list_classes.php");
    exit();
}
require_once '../core/ClassRoom.php';

$id = (int)($_GET['id'] ?? 0);
if(!$id){
    header("Location: list_classes.php");
    exit();
}

(new ClassRoom())->delete($id);
header("Location: list_classes.php?success=deleted");
exit();
?>