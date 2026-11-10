<?php
require 'db.php';
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: dashboard.php");
    exit();
}

$uid = $_SESSION['user_id'];
$role = $_SESSION['role'];
$name = $_SESSION['name'];
$msg = '';
if(isset($_SESSION['msg'])) {
    $msg = $_SESSION['msg'];
    unset($_SESSION['msg']);
}

// Handle routing for active tab
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'overview';

if (!function_exists('isDeptActive')) {
    function isDeptActive($current, $target) {
        if ($target === 'all') {
            return ($current === 'all' || empty($current)) ? 'active' : '';
        }
        return $current === $target ? 'active' : '';
    }
}

// Fetch user data
$user_res = $conn->query("SELECT * FROM user WHERE id=$uid");
$user_data = $user_res->fetch_assoc();

// Fetch profile data depending on role
$total_students = $conn->query("SELECT COUNT(*) as c FROM user WHERE role='student'")->fetch_assoc()['c'];
$total_advisers = $conn->query("SELECT COUNT(*) as c FROM user WHERE role='adviser' AND account_status='active'")->fetch_assoc()['c'];
$total_theses = $conn->query("SELECT COUNT(*) as c FROM student_profile WHERE thesis_topic != ''")->fetch_assoc()['c'];
$all_users = $conn->query("SELECT * FROM user ORDER BY role, name");
$all_theses = $conn->query("SELECT u.name as student_name, sp.user_id, sp.thesis_topic, sp.skills, sp.manuscript_file FROM student_profile sp JOIN user u ON sp.user_id = u.id WHERE sp.thesis_topic != ''");

$pending_users = $conn->query("
    SELECT u.id, u.name, u.firstname, u.lastname, u.email, u.phone, u.role, u.account_status,
           COALESCE(NULLIF(ap.department, ''), NULLIF(pp.department, ''), NULLIF(sec.department, ''), NULLIF(stp.department, ''), 'General / CEIT') as department,
           COALESCE(ap.expertise, pp.expertise, '') as expertise,
           ap.capacity,
           ap.schedule,
           sec.office,
           stp.student_id,
           stp.thesis_topic
    FROM user u
    LEFT JOIN adviser_profile ap ON u.id = ap.user_id
    LEFT JOIN panel_profile pp ON u.id = pp.user_id
    LEFT JOIN secretary_profile sec ON u.id = sec.user_id
    LEFT JOIN student_profile stp ON u.id = stp.user_id
    WHERE u.account_status = 'pending'
    ORDER BY u.id DESC
");
$pending_count = $pending_users ? $pending_users->num_rows : 0;

$pending_advisers = $conn->query("SELECT u.id, u.name, u.email, u.phone, a.department, a.expertise, a.capacity, a.schedule FROM user u LEFT JOIN adviser_profile a ON u.id = a.user_id WHERE u.role='adviser' AND u.account_status='pending'");
$pending_panels = $conn->query("SELECT u.id, u.name, u.email, u.phone, p.department, p.expertise FROM user u LEFT JOIN panel_profile p ON u.id = p.user_id WHERE u.role='panel' AND u.account_status='pending'");

$active_panels = $conn->query("SELECT u.id, u.name, p.department FROM user u JOIN panel_profile p ON u.id = p.user_id WHERE u.role='panel' AND u.account_status='active' ORDER BY u.name");
$archived_count = (int)($conn->query("SELECT COUNT(*) as c FROM user WHERE account_status='archived'")->fetch_assoc()['c'] ?? 0);

// ---------------------------------------------------------------
// Sidebar notification badges: show only items the admin hasn't seen.
// Visiting a tab records the latest record ID as "seen" for that tab.
$conn->query("CREATE TABLE IF NOT EXISTS user_badge_seen (
    user_id INT NOT NULL,
    badge_key VARCHAR(64) NOT NULL,
    last_seen_id INT NOT NULL DEFAULT 0,
    seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, badge_key)
)");

$badge_max_queries = [
    'schedules'   => "SELECT COALESCE(MAX(id), 0) AS m FROM defense_meetings",
    'mom_archive' => "SELECT COALESCE(MAX(id), 0) AS m FROM defense_meeting_minutes",
];

if (isset($badge_max_queries[$tab])) {
    $max_res = $conn->query($badge_max_queries[$tab]);
    $max_id = $max_res ? (int)$max_res->fetch_assoc()['m'] : 0;
    $seen_stmt = $conn->prepare("INSERT INTO user_badge_seen (user_id, badge_key, last_seen_id) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE last_seen_id = VALUES(last_seen_id)");
    if ($seen_stmt) {
        $seen_stmt->bind_param("isi", $uid, $tab, $max_id);
        $seen_stmt->execute();
    }
}

$badge_seen = ['schedules' => 0, 'mom_archive' => 0];
$seen_res = $conn->query("SELECT badge_key, last_seen_id FROM user_badge_seen WHERE user_id = $uid");
if ($seen_res) {
    while ($s = $seen_res->fetch_assoc()) {
        $badge_seen[$s['badge_key']] = (int)$s['last_seen_id'];
    }
}

$scheduled_defenses_count = (int)($conn->query("SELECT COUNT(*) as c FROM defense_meetings WHERE status IN ('scheduled', 'in_progress') AND id > " . $badge_seen['schedules'])->fetch_assoc()['c'] ?? 0);
$admin_moms_count = (int)($conn->query("SELECT COUNT(*) as c FROM defense_meeting_minutes WHERE is_archived = 0 AND id > " . $badge_seen['mom_archive'])->fetch_assoc()['c'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>CEIT Admin Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/variables.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/layout.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/components.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/auth.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/print.css?v=<?php echo time(); ?>">
    <?php if(in_array($tab, ['meeting_doc', 'mom_archive', 'archive'])): ?>
    <link rel="stylesheet" href="css/meeting_doc.css?v=<?php echo time(); ?>">
    <?php endif; ?>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="sidebar-brand">
        <div style="text-align: center; margin-bottom: 0.5rem;">
            <img src="logo.png" alt="CSU CEIT Logo" style="width: 150px; height: auto; filter: drop-shadow(0px 4px 6px rgba(0,0,0,0.3));">
        </div>
        <div class="brand-badges">
            <span class="badge-yellow"><i class="fa-solid fa-robot"></i> CEIT AI</span>
            <span class="badge-blue"><i class="fa-solid fa-graduation-cap"></i> Admin Portal</span>
        </div>
    </div>
    
    <div class="sidebar-nav">
        <div class="nav-section"><i class="fa-solid fa-house" style="margin-right:0.5rem"></i> ADMIN DASHBOARD</div>
        
        <a href="?tab=overview" class="nav-item <?php echo $tab=='overview'?'active':''; ?>">
            <i class="fa-solid fa-chart-line"></i> System Overview
        </a>
        <a href="?tab=schedules" class="nav-item <?php echo $tab=='schedules'?'active':''; ?>" data-badge-target="badgeSchedules" data-badge-key="schedules">
            <i class="fa-solid fa-calendar-days"></i> Defense Schedules
            <?php if(!empty($scheduled_defenses_count) && (int)$scheduled_defenses_count > 0): ?>
            <span id="badgeSchedules" class="nav-count-badge" style="background: var(--accent-yellow); color: var(--sidebar-bg); padding: 2px 8px; border-radius: 999px; font-size: 0.75rem; font-weight: 700; margin-left: auto;"><?php echo (int)$scheduled_defenses_count; ?></span>
            <?php endif; ?>
        </a>
        <a href="?tab=mom_archive" class="nav-item <?php echo ($tab=='mom_archive' && ($_GET['view']??'')!=='archived')?'active':''; ?>" data-badge-target="badgeMomArchive" data-badge-key="mom_archive">
            <i class="fa-solid fa-file-lines"></i> Official MOM Registry
            <?php if(!empty($admin_moms_count) && (int)$admin_moms_count > 0): ?>
            <span id="badgeMomArchive" class="nav-count-badge" style="background: #10B981; color: white; padding: 2px 8px; border-radius: 999px; font-size: 0.75rem; font-weight: 700; margin-left: auto;"><?php echo (int)$admin_moms_count; ?></span>
            <?php endif; ?>
        </a>
        <a href="?tab=users" class="nav-item <?php echo $tab=='users'?'active':''; ?>">
            <i class="fa-solid fa-users-gear"></i> Manage Users
        </a>
        <a href="?tab=approvals" class="nav-item <?php echo $tab=='approvals'?'active':''; ?>">
            <i class="fa-solid fa-user-check"></i> Pending Approvals
            <?php if($pending_count > 0): ?>
            <span style="background: var(--accent-yellow); color: var(--sidebar-bg); padding: 2px 8px; border-radius: 999px; font-size: 0.75rem; font-weight: 700; margin-left: auto;"><?php echo $pending_count; ?></span>
            <?php endif; ?>
        </a>
        <a href="?tab=theses" class="nav-item <?php echo $tab=='theses'?'active':''; ?>">
            <i class="fa-solid fa-book"></i> All Capstone Registry
        </a>
        <a href="?tab=groups" class="nav-item <?php echo $tab=='groups'?'active':''; ?>">
            <i class="fa-solid fa-users"></i> Capstone Groups
        </a>
        <a href="?tab=reports" class="nav-item <?php echo $tab=='reports'?'active':''; ?>">
            <i class="fa-solid fa-file-pdf"></i> System Reports
        </a>
        <a href="?tab=audit_logs" class="nav-item <?php echo $tab=='audit_logs'?'active':''; ?>">
            <i class="fa-solid fa-clock-rotate-left"></i> Audit Logs
        </a>
        <a href="?tab=archived" class="nav-item <?php echo $tab=='archived'?'active':''; ?>">
            <i class="fa-solid fa-box-archive"></i> Archived Users
            <?php if($archived_count > 0): ?>
            <span style="background: var(--accent-yellow); color: var(--sidebar-bg); padding: 2px 8px; border-radius: 999px; font-size: 0.75rem; font-weight: 700; margin-left: auto;"><?php echo $archived_count; ?></span>
            <?php endif; ?>
        </a>
        
        <a href="?tab=settings" class="nav-item <?php echo $tab=='settings'?'active':''; ?>" style="margin-top: 1rem;">
            <i class="fa-solid fa-gear"></i> Settings
        </a>
    </div>

    <div class="sidebar-footer">
        <div class="sidebar-user-avatar"><?php echo strtoupper(substr($name, 0, 1)); ?></div>
        <div class="sidebar-user-info">
            <div class="sidebar-user-name"><?php echo htmlspecialchars($name); ?></div>
            <div class="sidebar-user-status">Online • Admin</div>
        </div>
    </div>
</div>

<!-- MAIN CONTENT -->
<div class="main-content">
    
    <!-- TOPBAR -->
    <div class="topbar">
        <div class="topbar-left">
            <button class="topbar-toggle-btn" onclick="toggleSidebar()" title="Toggle Sidebar">
                <i class="fa-solid fa-bars"></i>
            </button>
            <div class="topbar-breadcrumb">
                <span>Admin Portal</span>
                <i class="fa-solid fa-chevron-right" style="font-size: 0.7rem; color: #94A3B8;"></i>
                <strong><?php 
                    if($tab === 'meeting_doc') echo 'Minutes of the Meeting (MOM)';
                    elseif($tab === 'mom_archive') echo 'Official MOM Registry';
                    elseif($tab === 'archive') echo 'MOM Archive';
                    else echo ucwords(str_replace('_', ' ', $tab)); 
                ?></strong>
            </div>
            <span class="topbar-campus-badge">
                <i class="fa-solid fa-shield-halved"></i> CSU Cabadbaran
            </span>
        </div>
        <div class="topbar-right">
            <?php include 'views/common/notifications_widget.php'; ?>
            <div class="topbar-user-pill">
                <div class="topbar-avatar"><?php echo strtoupper(substr($name, 0, 1)); ?></div>
                <span class="topbar-user-name"><?php echo htmlspecialchars($name); ?></span>
                <span class="topbar-role-badge topbar-role-admin">Administrator</span>
            </div>
            <button class="topbar-logout-btn" onclick="confirmLogout(event)" title="Sign Out">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
            </button>
        </div>
    </div>
    
    <?php if($msg): ?>
        <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?php echo $msg; ?></div>
    <?php endif; ?>

    <?php 
        $view_file = "views/admin/" . $tab . ".php";
        if(file_exists($view_file)) {
            include $view_file;
        } else {
            echo '<div class="card"><div class="card-body"><h2 style="color: var(--text-muted); text-align: center; padding: 3rem;"><i class="fa-solid fa-person-digging fa-3x" style="margin-bottom: 1rem;"></i><br>This module is under construction.</h2></div></div>';
        }
    ?>
</div>

<!-- Global University Logout Modal -->
<div id="logoutModal" class="modal">
    <div class="modal-content logout-modal-content">
        <div class="logout-modal-header">
            <div class="logout-seal-wrapper">
                <img src="logo.png" alt="CSU Seal">
            </div>
            <div class="logout-univ-subtitle">Caraga State University</div>
            <div class="logout-college-subtitle">College of Engineering and Information Technology</div>
            <h2>Sign Out of Portal</h2>
        </div>
        <div class="logout-modal-body">
            <div class="logout-user-card">
                <div class="logout-user-avatar">
                    <?php echo strtoupper(substr($name, 0, 1)); ?>
                </div>
                <div class="logout-user-details">
                    <div class="logout-user-name"><?php echo htmlspecialchars($name); ?></div>
                    <div class="logout-user-role">
                        <span class="logout-session-dot"></span>
                        System Administrator
                    </div>
                </div>
            </div>

            <div class="logout-prompt-title">End Active Academic Session?</div>
            <p class="logout-prompt-desc">
                Your administrative updates and system configurations are safely preserved. You will need your university credentials to sign back in.
            </p>

            <div class="logout-actions">
                <button type="button" class="btn-logout-cancel" onclick="closeLogoutModal()">
                    <i class="fa-solid fa-arrow-left"></i> Stay in Portal
                </button>
                <button type="button" class="btn-logout-confirm" onclick="window.location.href='logout.php'">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i> End Session
                </button>
            </div>

            <div class="logout-modal-footer-note">
                <i class="fa-solid fa-shield-halved"></i> Secured CEIT Academic Portal &bull; CSU Cabadbaran
            </div>
        </div>
    </div>
</div>

<script>
    function toggleSidebar() {
        const sidebar = document.querySelector('.sidebar');
        const main = document.querySelector('.main-content');
        if (!sidebar) return;

        let backdrop = document.getElementById('sidebarMobileBackdrop');
        if (!backdrop) {
            backdrop = document.createElement('div');
            backdrop.id = 'sidebarMobileBackdrop';
            backdrop.className = 'sidebar-backdrop';
            backdrop.onclick = toggleSidebar;
            document.body.appendChild(backdrop);
        }

        if (window.innerWidth <= 900) {
            const isOpen = sidebar.classList.toggle('open');
            backdrop.classList.toggle('active', isOpen);
            document.body.style.overflow = isOpen ? 'hidden' : '';
        } else {
            sidebar.classList.toggle('collapsed');
            if (main) main.classList.toggle('expanded');
        }
    }

    window.addEventListener('resize', function() {
        if (window.innerWidth > 900) {
            const sidebar = document.querySelector('.sidebar');
            const backdrop = document.getElementById('sidebarMobileBackdrop');
            if (sidebar) sidebar.classList.remove('open');
            if (backdrop) backdrop.classList.remove('active');
            document.body.style.overflow = '';
        }
    });

    function confirmLogout(e) {
        if(e) e.preventDefault();
        document.getElementById('logoutModal').style.display = 'block';
    }

    function closeLogoutModal() {
        document.getElementById('logoutModal').style.display = 'none';
    }

    window.addEventListener('click', function(e) {
        const modal = document.getElementById('logoutModal');
        if (e.target === modal) {
            closeLogoutModal();
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeLogoutModal();
        }
    });

    // Hide a sidebar badge instantly when its menu item is clicked
    document.querySelectorAll('.sidebar-nav a[data-badge-target]').forEach(function(link) {
        link.addEventListener('click', function() {
            var targetId = link.getAttribute('data-badge-target');
            if (targetId) {
                var badge = document.getElementById(targetId);
                if (badge) {
                    badge.style.setProperty('display', 'none', 'important');
                    badge.remove();
                }
            }
            var innerBadge = link.querySelector('.nav-count-badge');
            if (innerBadge) {
                innerBadge.style.setProperty('display', 'none', 'important');
                innerBadge.remove();
            }
            var badgeKey = link.getAttribute('data-badge-key');
            if (badgeKey && window.fetch) {
                fetch('action.php?action=mark_badge_seen&badge_key=' + encodeURIComponent(badgeKey), { method: 'POST' }).catch(function(){});
            }
        });
    });

    // Safety net: completely hide/remove any badge whose count is 0 or less
    function enforceBadgeVisibility() {
        document.querySelectorAll('.nav-count-badge').forEach(function(badge) {
            var count = parseInt(badge.textContent.trim(), 10);
            if (isNaN(count) || count <= 0) {
                badge.style.setProperty('display', 'none', 'important');
                badge.remove();
            }
        });
    }
    document.addEventListener('DOMContentLoaded', enforceBadgeVisibility);
    enforceBadgeVisibility();
</script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</body>
</html>
