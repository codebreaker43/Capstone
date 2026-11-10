<?php
session_start();
if(!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$role = $_SESSION['role'];
$query_string = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '';

if ($role == 'student') {
    header("Location: student_dashboard.php" . $query_string);
} elseif ($role == 'adviser') {
    header("Location: faculty_dashboard.php" . $query_string);
} elseif ($role == 'admin') {
    header("Location: admin_dashboard.php" . $query_string);
} elseif ($role == 'panel') {
    header("Location: panel_dashboard.php" . $query_string);
} elseif ($role == 'secretary') {
    header("Location: secretary_dashboard.php" . $query_string);
} else {
    header("Location: index.php");
}
exit();
?>
