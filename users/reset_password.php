<?php
session_start();
if(!isset($_SESSION['user_id'])){ header("Location: ../login.php"); exit(); }
if($_SESSION['role'] != 'admin'){ header("Location: ../admin/dashboard.php"); exit(); }
require_once '../core/User.php';

$id = (int)($_GET['id'] ?? 0);
if(!$id){ header("Location: list_users.php"); exit(); }

$userModel = new User();
$user = $userModel->getById($id);
if(!$user || $user['role'] === 'admin'){ header("Location: list_users.php"); exit(); }

$userModel->resetPassword($id, $user['username']);
header("Location: list_users.php?success=reset");
exit();
