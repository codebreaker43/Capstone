<?php
require 'db.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Security: Allow secretary and admin oversight
if ($_SESSION['role'] !== 'secretary' && $_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

$uid = (int)$_SESSION['user_id'];
$role = $_SESSION['role'];
$name = $_SESSION['name'];
$msg = '';
if (isset($_SESSION['msg'])) {
    $msg = $_SESSION['msg'];
    unset($_SESSION['msg']);
}

// Active tab (default: schedules)
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'schedules';

// Fetch secretary user details
$user_res = $conn->query("SELECT * FROM user WHERE id = $uid");
$user_data = $user_res ? $user_res->fetch_assoc() : [];

// Fetch secretary profile data
$sec_profile = null;
$sp_q = $conn->query("SELECT * FROM secretary_profile WHERE user_id = $uid");
if ($sp_q && $sp_q->num_rows > 0) {
    $sec_profile = $sp_q->fetch_assoc();
}

// Summary Statistics for Secretariat
$total_defenses_count = (int)($conn->query("SELECT COUNT(*) as c FROM defense_meetings")->fetch_assoc()['c'] ?? 0);
$scheduled_count = (int)($conn->query("SELECT COUNT(*) as c FROM defense_meetings WHERE status IN ('scheduled', 'in_progress')")->fetch_assoc()['c'] ?? 0);
$filed_moms_count = (int)($conn->query("SELECT COUNT(*) as c FROM defense_meeting_minutes")->fetch_assoc()['c'] ?? 0);
$assigned_to_me_count = (int)($conn->query("SELECT COUNT(*) as c FROM defense_meetings WHERE mom_documenter_id = $uid OR mom_documenter_name = '" . sanitize($name) . "'")->fetch_assoc()['c'] ?? 0);

// ---------------------------------------------------------------
// Sidebar notification badges: show only items the user hasn't seen.
// Opening a tab records the latest record ID as "seen" for that tab.
// ---------------------------------------------------------------
$conn->query("CREATE TABLE IF NOT EXISTS user_badge_seen (
    user_id INT NOT NULL,
    badge_key VARCHAR(50) NOT NULL,
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

$new_schedules_count = (int)($conn->query("SELECT COUNT(*) as c FROM defense_meetings WHERE status IN ('scheduled', 'in_progress') AND id > " . $badge_seen['schedules'])->fetch_assoc()['c'] ?? 0);
$new_moms_count = (int)($conn->query("SELECT COUNT(*) as c FROM defense_meeting_minutes WHERE is_archived = 0 AND id > " . $badge_seen['mom_archive'])->fetch_assoc()['c'] ?? 0);
$archived_moms_count = (int)($conn->query("SELECT COUNT(*) as c FROM defense_meeting_minutes WHERE is_archived = 1")->fetch_assoc()['c'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>CEIT Defense Secretariat Dashboard</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/variables.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/layout.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/components.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/auth.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/print.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/meeting_doc.css?v=<?php echo time(); ?>">
    <style>
        .badge-secretariat {
            background: linear-gradient(135deg, #047857, #065F46);
            color: #FFFFFF;
            font-size: 0.72rem;
            font-weight: 800;
            padding: 3px 8px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
    </style>
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
            <span class="badge-secretariat"><i class="fa-solid fa-pen-nib"></i> Secretariat</span>
        </div>
    </div>
    
    <div class="sidebar-nav">
        <div class="nav-section"><i class="fa-solid fa-clipboard-list" style="margin-right:0.5rem"></i> DEFENSE SECRETARIAT</div>
        
        <a href="?tab=schedules" class="nav-item <?php echo $tab=='schedules'?'active':''; ?>" data-badge-target="badgeSchedules" data-badge-key="schedules">
            <i class="fa-solid fa-calendar-days"></i> Defense Schedules
            <?php if(!empty($new_schedules_count) && (int)$new_schedules_count > 0): ?>
            <span id="badgeSchedules" class="nav-count-badge" style="background: var(--accent-yellow); color: var(--sidebar-bg); padding: 2px 8px; border-radius: 999px; font-size: 0.75rem; font-weight: 700; margin-left: auto;"><?php echo (int)$new_schedules_count; ?></span>
            <?php endif; ?>
        </a>

        <a href="?tab=meeting_doc" class="nav-item <?php echo $tab=='meeting_doc'?'active':''; ?>">
            <i class="fa-solid fa-file-signature"></i> Minutes of the Meeting (MOM)
        </a>

        <a href="?tab=mom_archive" class="nav-item <?php echo ($tab=='mom_archive' && ($_GET['view']??'')!=='archived')?'active':''; ?>" data-badge-target="badgeMomArchive" data-badge-key="mom_archive">
            <i class="fa-solid fa-file-lines"></i> Official MOM Registry
            <?php if(!empty($new_moms_count) && (int)$new_moms_count > 0): ?>
            <span id="badgeMomArchive" class="nav-count-badge" style="background: #10B981; color: white; padding: 2px 8px; border-radius: 999px; font-size: 0.75rem; font-weight: 700; margin-left: auto;"><?php echo (int)$new_moms_count; ?></span>
            <?php endif; ?>
        </a>

        <a href="?tab=archive" class="nav-item <?php echo ($tab=='archive' || ($tab=='mom_archive' && ($_GET['view']??'')==='archived'))?'active':''; ?>">
            <i class="fa-solid fa-box-archive"></i> Archive
            <?php if($archived_moms_count > 0): ?>
            <span class="nav-count-badge" style="background: rgba(255,255,255,0.18); color: #FFFFFF; padding: 2px 8px; border-radius: 999px; font-size: 0.75rem; font-weight: 700; margin-left: auto;"><?php echo $archived_moms_count; ?></span>
            <?php endif; ?>
        </a>

        <a href="?tab=settings" class="nav-item <?php echo $tab=='settings'?'active':''; ?>" style="margin-top: 1rem;">
            <i class="fa-solid fa-gear"></i> Settings
        </a>
    </div>

    <div class="sidebar-footer">
        <div class="sidebar-user-avatar" style="background: linear-gradient(135deg, #10B981, #047857);"><?php echo strtoupper(substr($name, 0, 1)); ?></div>
        <div class="sidebar-user-info">
            <div class="sidebar-user-name"><?php echo htmlspecialchars($name); ?></div>
            <div class="sidebar-user-status">Online • Defense Secretary</div>
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
                <span>Secretariat Portal</span>
                <i class="fa-solid fa-chevron-right" style="font-size: 0.7rem; color: #94A3B8;"></i>
                <strong><?php 
                    if($tab === 'meeting_doc') echo 'Minutes of the Meeting (MOM)';
                    elseif($tab === 'archive' || ($tab === 'mom_archive' && ($_GET['view']??'')==='archived')) echo 'MOM Archive';
                    elseif($tab === 'mom_archive') echo 'Official MOM Registry';
                    else echo ucwords(str_replace('_', ' ', $tab)); 
                ?></strong>
            </div>
            <span class="topbar-campus-badge">
                <i class="fa-solid fa-building-columns"></i> CSU Cabadbaran
            </span>
        </div>
        <div class="topbar-right">
            <?php include 'views/common/notifications_widget.php'; ?>
            <?php include 'views/common/role_switcher_widget.php'; ?>
            <div class="topbar-user-pill">
                <div class="topbar-avatar" style="background: linear-gradient(135deg, #10B981, #047857);"><?php echo strtoupper(substr($name, 0, 1)); ?></div>
                <span class="topbar-user-name"><?php echo htmlspecialchars($name); ?></span>
                <span class="topbar-role-badge" style="background: #ECFDF5; color: #047857; border: 1px solid #A7F3D0;">Secretary</span>
            </div>
            <button class="topbar-logout-btn" onclick="confirmLogout(event)" title="Sign Out">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
            </button>
        </div>
    </div>
    
    <?php if($msg): ?>
        <div class="alert alert-success" style="margin: 1.25rem 2rem 0;"><i class="fa-solid fa-circle-check"></i> <?php echo $msg; ?></div>
    <?php endif; ?>

    <?php 
        $view_file = "views/secretary/" . $tab . ".php";
        if(file_exists($view_file)) {
            include $view_file;
        } else {
            echo '<div class="card" style="margin: 2rem;"><div class="card-body"><h2 style="color: var(--text-muted); text-align: center; padding: 3rem;"><i class="fa-solid fa-person-digging fa-3x" style="margin-bottom: 1rem;"></i><br>Module under development.</h2></div></div>';
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
                <div class="logout-user-avatar" style="background: linear-gradient(135deg, #10B981, #047857);">
                    <?php echo strtoupper(substr($name, 0, 1)); ?>
                </div>
                <div class="logout-user-details">
                    <div class="logout-user-name"><?php echo htmlspecialchars($name); ?></div>
                    <div class="logout-user-role">
                        <span class="logout-session-dot"></span>
                        Defense Secretariat
                    </div>
                </div>
            </div>

            <div class="logout-prompt-title">End Active Academic Session?</div>
            <p class="logout-prompt-desc">
                Your recorded minutes and transcript entries are securely saved. You can sign back in anytime.
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
</script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</body>
</html>
