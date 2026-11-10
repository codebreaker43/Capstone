<?php
require_once __DIR__ . '/db.php';

if (isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    $name = $_SESSION['name'] ?? 'User';
    $role = $_SESSION['role'] ?? 'user';
    log_audit('LOGOUT', "User '{$name}' logged out.", 'Authentication', 'success', null, $uid, $name, $role);
}

session_unset();
session_destroy();
header("Location: index.php");
exit();
?>
