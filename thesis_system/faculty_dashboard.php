<?php
require 'db.php';
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'adviser') {
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

// Fetch user data
$user_res = $conn->query("SELECT * FROM user WHERE id=$uid");
$user_data = $user_res->fetch_assoc();

// Fetch profile data depending on role
$res = $conn->query("SELECT * FROM adviser_profile WHERE user_id=$uid");
$profile = $res->fetch_assoc();
$req_res = $conn->query("SELECT m.*, u.name as stu_name, u.email, sp.thesis_topic, sp.skills, sp.abstract_file, sp.abstract_name, sp.title_status, sp.title_approved_at, sp.title_approved_by, COALESCE(m.group_id, sp.group_id, 0) as group_id FROM match_request m JOIN user u ON m.student_id = u.id JOIN student_profile sp ON u.id = sp.user_id WHERE m.adviser_id=$uid");
$advisees_res = $conn->query("SELECT m.*, u.name as stu_name, u.email, sp.thesis_topic, sp.skills, sp.abstract_file, sp.abstract_name, sp.title_status, sp.title_approved_at, sp.title_approved_by, sp.course_code, sp.academic_year, COALESCE(m.group_id, sp.group_id, 0) as group_id FROM match_request m JOIN user u ON m.student_id = u.id JOIN student_profile sp ON u.id = sp.user_id WHERE m.adviser_id=$uid AND m.status='accepted'");
$raw_ann_count_fac = (int)($conn->query("SELECT COUNT(*) as c FROM announcements WHERE adviser_id=$uid")->fetch_assoc()['c'] ?? 0);
$raw_pending_reviews_count = (int)($conn->query("SELECT COUNT(*) as c FROM document_submissions ds WHERE (ds.adviser_id = $uid OR ds.student_id IN (SELECT student_id FROM match_request WHERE adviser_id = $uid AND status = 'accepted')) AND ds.status IN ('pending', 'under_review')")->fetch_assoc()['c'] ?? 0);

// Auto-ensure every pending match_request for this adviser has a linked consultation record
$pend_mr_q = $conn->query("SELECT student_id, group_id FROM match_request WHERE adviser_id = $uid AND status = 'pending'");
if ($pend_mr_q) {
    while ($pmr = $pend_mr_q->fetch_assoc()) {
        ensure_linked_consultation_for_request($conn, (int)$pmr['student_id'], (int)$uid, (int)($pmr['group_id'] ?? 0));
    }
}

// Per-item decrement (by 1) when Consultations is clicked/viewed, hiding badge once it reaches 0
if ($tab === 'consultations') {
    $viewed_cid = !empty($_GET['cons_id']) ? (int)$_GET['cons_id'] : 0;
    if ($viewed_cid > 0) {
        $conn->query("UPDATE adviser_consultations SET adviser_seen = 1 WHERE id = $viewed_cid AND adviser_id = $uid AND adviser_seen = 0");
    } else {
        $one_unseen = $conn->query("SELECT id FROM adviser_consultations WHERE adviser_id = $uid AND status IN ('pending_review', 'student_responded') AND adviser_seen = 0 ORDER BY id DESC LIMIT 1");
        if ($one_unseen && $row_u = $one_unseen->fetch_assoc()) {
            $u_id = (int)$row_u['id'];
            $conn->query("UPDATE adviser_consultations SET adviser_seen = 1 WHERE id = $u_id");
        }
    }
}

// Per-item decrement (by 1) when Defense Schedules is clicked/viewed, hiding badge once it reaches 0
if ($tab === 'defense_schedules') {
    $one_dm = $conn->query("SELECT id FROM defense_meetings WHERE (adviser_id=$uid OR student_id IN (SELECT student_id FROM match_request WHERE adviser_id=$uid AND status='accepted')) AND adviser_seen = 0 ORDER BY id DESC LIMIT 1");
    if ($one_dm && $row_dm = $one_dm->fetch_assoc()) {
        $dm_id = (int)$row_dm['id'];
        $conn->query("UPDATE defense_meetings SET adviser_seen = 1 WHERE id = $dm_id");
    }
}

// Per-item decrement (by 1) for other sidebar badges (review, announcements, panel_comments)
if (!isset($_SESSION['fac_seen_counts'][$uid])) {
    $_SESSION['fac_seen_counts'][$uid] = ['review' => 0, 'announcements' => 0, 'panel_comments' => 0];
}
if (in_array($tab, ['review', 'announcements', 'panel_comments'], true)) {
    $_SESSION['fac_seen_counts'][$uid][$tab] = ($_SESSION['fac_seen_counts'][$uid][$tab] ?? 0) + 1;
}

$ann_count_fac = max(0, $raw_ann_count_fac - (int)($_SESSION['fac_seen_counts'][$uid]['announcements'] ?? 0));
$pending_reviews_count = max(0, $raw_pending_reviews_count - (int)($_SESSION['fac_seen_counts'][$uid]['review'] ?? 0));
$pending_consultations_count = (int)($conn->query("SELECT COUNT(*) as c FROM adviser_consultations WHERE adviser_id=$uid AND status IN ('pending_review', 'student_responded') AND adviser_seen = 0")->fetch_assoc()['c'] ?? 0);
$defense_schedules_count = (int)($conn->query("SELECT COUNT(*) as c FROM defense_meetings WHERE (adviser_id=$uid OR student_id IN (SELECT student_id FROM match_request WHERE adviser_id=$uid AND status='accepted')) AND adviser_seen = 0")->fetch_assoc()['c'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>CEIT Faculty Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/variables.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/layout.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/components.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/auth.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/print.css?v=<?php echo time(); ?>">
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
            <span class="badge-blue"><i class="fa-solid fa-graduation-cap"></i> Faculty Portal</span>
        </div>
    </div>
    
    <div class="sidebar-nav">
        <div class="nav-section"><i class="fa-solid fa-house" style="margin-right:0.5rem"></i> FACULTY DASHBOARD</div>
        
        <a href="?tab=overview" class="nav-item <?php echo $tab=='overview'?'active':''; ?>">
            <i class="fa-solid fa-chart-line"></i> Overview
        </a>
        <a href="?tab=advisees" class="nav-item <?php echo $tab=='advisees'?'active':''; ?>">
            <i class="fa-solid fa-user-graduate"></i> My Advisees
        </a>
        <a href="?tab=requests" class="nav-item <?php echo $tab=='requests'?'active':''; ?>">
            <i class="fa-solid fa-inbox"></i> Advisee Requests
        </a>
        <a href="?tab=consultations" class="nav-item <?php echo $tab=='consultations'?'active':''; ?>">
            <i class="fa-solid fa-clipboard-check"></i> Consultations
            <?php if($pending_consultations_count > 0): ?>
            <span id="sidebarConsultationsBadge" style="background:var(--accent-yellow);color:var(--sidebar-bg);padding:2px 7px;border-radius:999px;font-size:0.72rem;font-weight:700;margin-left:auto;"><?php echo $pending_consultations_count; ?></span>
            <?php endif; ?>
        </a>
        <a href="?tab=review" class="nav-item <?php echo $tab=='review'?'active':''; ?>">
            <i class="fa-solid fa-file-pen"></i> Review Papers
            <?php if($pending_reviews_count > 0): ?>
            <span style="background:var(--accent-yellow);color:var(--sidebar-bg);padding:2px 7px;border-radius:999px;font-size:0.72rem;font-weight:700;margin-left:auto;"><?php echo $pending_reviews_count; ?></span>
            <?php endif; ?>
        </a>
        <a href="?tab=panel_comments" class="nav-item <?php echo $tab=='panel_comments'?'active':''; ?>">
            <i class="fa-solid fa-comments"></i> Panel Comments
            <?php 
            $adv_c_counts = count_adviser_advisee_comments($conn, $uid);
            $seen_pc = (int)($_SESSION['fac_seen_counts'][$uid]['panel_comments'] ?? 0);
            $rem_pc_rev = max(0, (int)$adv_c_counts['revisions'] - $seen_pc);
            $rem_pc_tot = max(0, (int)$adv_c_counts['total'] - $seen_pc);
            if ($rem_pc_rev > 0): ?>
            <span style="background:#DC2626;color:#FFFFFF;padding:2px 7px;border-radius:999px;font-size:0.72rem;font-weight:700;margin-left:auto;" title="<?php echo $rem_pc_rev; ?> Required Revisions"><?php echo $rem_pc_rev; ?></span>
            <?php elseif ($rem_pc_tot > 0): ?>
            <span style="background:var(--accent-yellow);color:var(--sidebar-bg);padding:2px 7px;border-radius:999px;font-size:0.72rem;font-weight:700;margin-left:auto;"><?php echo $rem_pc_tot; ?></span>
            <?php endif; ?>
        </a>
        <a href="?tab=defense_schedules" class="nav-item <?php echo $tab=='defense_schedules'?'active':''; ?>">
            <i class="fa-solid fa-gavel"></i> Defense Schedules
            <?php if($defense_schedules_count > 0): ?>
            <span style="background:var(--accent-yellow);color:var(--sidebar-bg);padding:2px 7px;border-radius:999px;font-size:0.72rem;font-weight:700;margin-left:auto;"><?php echo $defense_schedules_count; ?></span>
            <?php endif; ?>
        </a>
        <a href="?tab=schedule" class="nav-item <?php echo $tab=='schedule'?'active':''; ?>">
            <i class="fa-solid fa-calendar-check"></i> Schedule Meetings
        </a>
        <a href="?tab=expertise" class="nav-item <?php echo $tab=='expertise'?'active':''; ?>">
            <i class="fa-solid fa-tags"></i> My Expertise
        </a>
        <a href="?tab=announcements" class="nav-item <?php echo $tab=='announcements'?'active':''; ?>">
            <i class="fa-solid fa-bullhorn"></i> Announcements
            <?php if($ann_count_fac > 0): ?>
            <span style="background:var(--accent-yellow);color:var(--sidebar-bg);padding:2px 7px;border-radius:999px;font-size:0.72rem;font-weight:700;margin-left:auto;"><?php echo $ann_count_fac; ?></span>
            <?php endif; ?>
        </a>
        <a href="?tab=reports" class="nav-item <?php echo $tab=='reports'?'active':''; ?>">
            <i class="fa-solid fa-file-pdf"></i> Advisee Reports
        </a>
        
        <a href="?tab=settings" class="nav-item <?php echo $tab=='settings'?'active':''; ?>" style="margin-top: 1rem;">
            <i class="fa-solid fa-gear"></i> Settings
        </a>
    </div>

    <div class="sidebar-footer">
        <div class="sidebar-user-avatar"><?php echo strtoupper(substr($name, 0, 1)); ?></div>
        <div class="sidebar-user-info">
            <div class="sidebar-user-name"><?php echo htmlspecialchars($name); ?></div>
            <div class="sidebar-user-status">Online • Faculty</div>
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
                <span>Faculty Portal</span>
                <i class="fa-solid fa-chevron-right" style="font-size: 0.7rem; color: #94A3B8;"></i>
                <strong><?php 
                    $tab_labels = [
                        'panel_comments' => 'Panel Comments',
                        'consultations' => 'Adviser Consultations',
                        'defense_schedules' => 'Defense Schedules',
                        'schedule' => 'Schedule Meetings'
                    ];
                    echo $tab_labels[$tab] ?? ucfirst($tab);
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
                <div class="topbar-avatar"><?php echo strtoupper(substr($name, 0, 1)); ?></div>
                <span class="topbar-user-name"><?php echo htmlspecialchars($name); ?></span>
                <span class="topbar-role-badge topbar-role-adviser">Faculty Adviser</span>
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
        $view_file = __DIR__ . "/views/faculty/" . $tab . ".php";
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
                        Faculty Adviser
                    </div>
                </div>
            </div>

            <div class="logout-prompt-title">End Active Academic Session?</div>
            <p class="logout-prompt-desc">
                Your advising advisories, matched advisees, and review evaluations are safely preserved. You will need your university credentials to sign back in.
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
</script>

</body>
</html>
