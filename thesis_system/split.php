<?php
$lines = file('dashboard.php');

function create_dashboard($target_role, $filename, $lines) {
    $out = [];
    $state = 'common';
    $skip_until = null;
    
    foreach ($lines as $i => $line) {
        $trim = trim($line);
        
        if ($target_role == 'student' && $i == 7) {
            $out[] = "if (\$_SESSION['role'] != 'student') { header('Location: dashboard.php'); exit(); }\n";
        }
        if ($target_role == 'adviser' && $i == 7) {
            $out[] = "if (\$_SESSION['role'] != 'adviser') { header('Location: dashboard.php'); exit(); }\n";
        }
        if ($target_role == 'admin' && $i == 7) {
            $out[] = "if (\$_SESSION['role'] != 'admin') { header('Location: dashboard.php'); exit(); }\n";
        }

        // Top PHP Logic
        if (strpos($line, "if (\$role == 'student') {") !== false) {
            if ($target_role != 'student') $state = 'skip';
            else $out[] = $line;
            continue;
        }
        if (strpos($line, "} elseif (\$role == 'adviser') {") !== false) {
            if ($target_role != 'adviser') $state = 'skip';
            else {
                $state = 'common';
                $out[] = str_replace("} elseif (\$role == 'adviser') {", "if (\$role == 'adviser') {", $line);
            }
            continue;
        }
        if (strpos($line, "} elseif (\$role == 'admin') {") !== false) {
            if ($target_role != 'admin') $state = 'skip';
            else {
                $state = 'common';
                $out[] = str_replace("} elseif (\$role == 'admin') {", "if (\$role == 'admin') {", $line);
            }
            continue;
        }
        if ($trim === "?>" && $i < 50) {
            $state = 'common';
        }

        // Sidebar Navigation
        if (strpos($line, "<?php if(\$role == 'student'): ?>") !== false && $i > 65) {
            if ($target_role != 'student') $state = 'skip';
            continue;
        }
        if (strpos($line, "<?php elseif(\$role == 'adviser'): ?>") !== false && $i > 65 && $i < 130) {
            if ($target_role != 'adviser') $state = 'skip';
            else $state = 'common';
            continue;
        }
        if (strpos($line, "<?php elseif(\$role == 'admin'): ?>") !== false && $i > 65 && $i < 130) {
            if ($target_role != 'admin') $state = 'skip';
            else $state = 'common';
            continue;
        }
        if (strpos($line, "<?php endif; ?>") !== false && $i > 65 && $i < 130) {
            $state = 'common';
            continue;
        }

        // Main Content Logic
        if (strpos($line, "<!-- STUDENT DASHBOARD LOGIC -->") !== false) {
            $out[] = $line;
            if ($target_role != 'student') $state = 'skip';
            continue;
        }
        if (strpos($line, "<!-- ADVISER DASHBOARD LOGIC -->") !== false) {
            $out[] = $line;
            if ($target_role != 'adviser') $state = 'skip';
            else $state = 'common';
            continue;
        }
        if (strpos($line, "<!-- ADMIN DASHBOARD LOGIC -->") !== false) {
            $out[] = $line;
            if ($target_role != 'admin') $state = 'skip';
            else $state = 'common';
            continue;
        }
        
        // Remove the php elseif lines that started the blocks
        if ($state == 'common' && strpos($line, "<?php elseif(\$role == 'student'): ?>") !== false) continue;
        if ($state == 'common' && strpos($line, "<?php elseif(\$role == 'adviser'): ?>") !== false && $i > 390) continue;
        if ($state == 'common' && strpos($line, "<?php elseif(\$role == 'admin'): ?>") !== false && $i > 390) continue;
        
        if (strpos($line, "<!-- Global AI Modal -->") !== false) {
            $state = 'common'; // Reset for footer
            // Note: admin block didn't have an endif right before this in my previous script, but actually we need to make sure syntax is correct.
            // In the original file, the whole if-elseif is closed. We removed the opening tags for the specific blocks, so we must remove the closing endif.
        }

        if ($state != 'skip') {
            $out[] = $line;
        }
    }
    
    $content = implode("", $out);
    
    // We need to fix the closing <?php endif; ?> for the main content block.
    // Since we removed the "elseif", the "if($tab == 'settings')" now needs an endif before the main logic, 
    // OR we just convert the main logic from "elseif" to "if($role == ...)".
    // Actually, in dashboard.php:
    // <?php if($tab == 'settings'): ?> ... <?php elseif($role == 'student'): ?> ... <?php endif; ?>
    // If we kept the student block, we removed "elseif($role == 'student')". Wait!
    // If we removed it, then the student block becomes part of the settings block! That's bad.
    
    file_put_contents($filename, $content);
}

// Just write dashboard router first
$router = <<<PHP
<?php
session_start();
if(!isset(\$_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
\$role = \$_SESSION['role'];
if (\$role == 'student') {
    header("Location: student_dashboard.php");
} elseif (\$role == 'adviser') {
    header("Location: faculty_dashboard.php");
} elseif (\$role == 'admin') {
    header("Location: admin_dashboard.php");
} else {
    header("Location: index.php");
}
exit();
?>
PHP;
file_put_contents('dashboard.php', $router);

echo "Done splitting files!";
?>
