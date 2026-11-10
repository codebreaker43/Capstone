<?php
$content = file_get_contents('dashboard.php');

// Helper to extract a block between two markers
function extract_block($content, $start_marker, $end_marker) {
    $start = strpos($content, $start_marker);
    $end = strpos($content, $end_marker, $start);
    if ($start === false || $end === false) return "";
    return substr($content, $start, $end - $start);
}

$top_common = <<<'PHP'
<?php
require 'db.php';
if(!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$uid = $_SESSION['user_id'];
$role = $_SESSION['role'];
$name = $_SESSION['name'];
PHP;

$top_common2 = <<<'PHP'

$msg = '';
if(isset($_SESSION['msg'])) {
    $msg = $_SESSION['msg'];
    unset($_SESSION['msg']);
}

// Handle routing for active tab
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'overview';

// Fetch user data
$user_res = $conn->query("SELECT * FROM user WHERE id=$uid");
$user_data = $user_res->fetch_assoc();
PHP;

$student_data = <<<'PHP'
$res = $conn->query("SELECT * FROM student_profile WHERE user_id=$uid");
$profile = $res->fetch_assoc();
$req_res = $conn->query("SELECT m.*, u.name as adv_name FROM match_request m JOIN user u ON m.adviser_id = u.id WHERE m.student_id=$uid");
$req_count = $req_res->num_rows;
// Calculate progress mock
$progress = empty($profile['thesis_topic']) ? 0 : ($req_count > 0 ? 66 : 33);
$adviser_assigned = ($conn->query("SELECT id FROM match_request WHERE student_id=$uid AND status='accepted'")->num_rows > 0) ? 1 : 0;
PHP;

$adviser_data = <<<'PHP'
$res = $conn->query("SELECT * FROM adviser_profile WHERE user_id=$uid");
$profile = $res->fetch_assoc();
$req_res = $conn->query("SELECT m.*, u.name as stu_name, u.email, sp.thesis_topic, sp.skills FROM match_request m JOIN user u ON m.student_id = u.id JOIN student_profile sp ON u.id = sp.user_id WHERE m.adviser_id=$uid");
$advisees_res = $conn->query("SELECT m.*, u.name as stu_name, u.email, sp.thesis_topic, sp.skills FROM match_request m JOIN user u ON m.student_id = u.id JOIN student_profile sp ON u.id = sp.user_id WHERE m.adviser_id=$uid AND m.status='accepted'");
PHP;

$admin_data = <<<'PHP'
$total_students = $conn->query("SELECT COUNT(*) as c FROM user WHERE role='student'")->fetch_assoc()['c'];
$total_advisers = $conn->query("SELECT COUNT(*) as c FROM user WHERE role='adviser'")->fetch_assoc()['c'];
$total_theses = $conn->query("SELECT COUNT(*) as c FROM student_profile WHERE thesis_topic != ''")->fetch_assoc()['c'];
$all_users = $conn->query("SELECT * FROM user ORDER BY role, name");
$all_theses = $conn->query("SELECT u.name as student_name, sp.thesis_topic, sp.skills FROM student_profile sp JOIN user u ON sp.user_id = u.id WHERE sp.thesis_topic != ''");
PHP;

$head = extract_block($content, "<!DOCTYPE html>", "<!-- SIDEBAR -->");
$sidebar_top = extract_block($content, "<!-- SIDEBAR -->", "<?php if(\$role == 'student'): ?>");
$settings_ui = extract_block($content, "<?php if(\$tab == 'settings'): ?>", "<!-- STUDENT DASHBOARD LOGIC -->");

$student_nav = extract_block($content, "<?php if(\$role == 'student'): ?>", "<?php elseif(\$role == 'adviser'): ?>");
$student_nav = str_replace("<?php if(\$role == 'student'): ?>\n", "", $student_nav);

$adviser_nav = extract_block($content, "<?php elseif(\$role == 'adviser'): ?>", "<?php elseif(\$role == 'admin'): ?>");
$adviser_nav = str_replace("<?php elseif(\$role == 'adviser'): ?>\n", "", $adviser_nav);

$admin_nav = extract_block($content, "<?php elseif(\$role == 'admin'): ?>", "<?php endif; ?>");
$admin_nav = str_replace("<?php elseif(\$role == 'admin'): ?>\n", "", $admin_nav);

$sidebar_bottom = extract_block($content, "<a href=\"?tab=settings\"", "<!-- MAIN CONTENT -->");

$main_content_top = extract_block($content, "<!-- MAIN CONTENT -->", "<?php if(\$tab == 'settings'): ?>");

$student_logic = extract_block($content, "<!-- STUDENT DASHBOARD LOGIC -->", "<!-- ADVISER DASHBOARD LOGIC -->");
$student_logic = preg_replace('/<\?php elseif\(\$role == \'student\'\): \?>\s*/', '', $student_logic);

$adviser_logic = extract_block($content, "<!-- ADVISER DASHBOARD LOGIC -->", "<!-- ADMIN DASHBOARD LOGIC -->");
$adviser_logic = preg_replace('/<\?php elseif\(\$role == \'adviser\'\): \?>\s*/', '', $adviser_logic);

$admin_logic = extract_block($content, "<!-- ADMIN DASHBOARD LOGIC -->", "<!-- Global AI Modal -->");
$admin_logic = preg_replace('/<\?php elseif\(\$role == \'admin\'\): \?>\s*/', '', $admin_logic);
// Remove trailing <?php endif; ?> from admin logic which closed the role check
$admin_logic = preg_replace('/<\?php endif; \?>\s*$/', '', $admin_logic);

$footer = extract_block($content, "<!-- Global AI Modal -->", "</html>") . "</html>\n";

function build_file($role, $role_data, $role_nav, $role_logic) {
    global $top_common, $top_common2, $head, $sidebar_top, $sidebar_bottom, $main_content_top, $settings_ui, $footer;
    
    $out = $top_common . "\nif(\$role != '$role') { header('Location: dashboard.php'); exit(); }\n" . $top_common2 . "\n" . $role_data . "\n?>\n";
    $out .= $head;
    $out .= $sidebar_top;
    $out .= $role_nav;
    $out .= $sidebar_bottom;
    $out .= $main_content_top;
    $out .= $settings_ui;
    $out .= $role_logic;
    $out .= $footer;
    
    return $out;
}

// Ensure the old copies from before are overwritten
file_put_contents('student_dashboard.php', build_file('student', $student_data, $student_nav, $student_logic));
file_put_contents('faculty_dashboard.php', build_file('adviser', $adviser_data, $adviser_nav, $adviser_logic));
file_put_contents('admin_dashboard.php', build_file('admin', $admin_data, $admin_nav, $admin_logic));

// Make dashboard.php a router
$router = <<<'PHP'
<?php
session_start();
if(!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$role = $_SESSION['role'];
if ($role == 'student') {
    header("Location: student_dashboard.php");
} elseif ($role == 'adviser') {
    header("Location: faculty_dashboard.php");
} elseif ($role == 'admin') {
    header("Location: admin_dashboard.php");
} else {
    header("Location: index.php");
}
exit();
?>
PHP;

file_put_contents('dashboard.php', $router);

echo "Refactored successfully!";
?>
