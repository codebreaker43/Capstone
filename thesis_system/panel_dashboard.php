<?php
require 'db.php';
if(!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$qs = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '';
if($_SESSION['role'] === 'admin') {
    header("Location: admin_dashboard.php" . $qs);
    exit();
}

if($_SESSION['role'] !== 'panel') {
    header("Location: dashboard.php" . $qs);
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
$res = $conn->query("SELECT * FROM panel_profile WHERE user_id=$uid");
$profile = $res->fetch_assoc();
$assigned_res = $conn->query("
    SELECT tp.*, 
           u.name as stu_name, u.email, 
           COALESCE(NULLIF(sp.thesis_topic, ''), cg.thesis_topic, 'Capstone Research') as thesis_topic, 
           COALESCE(NULLIF(sp.manuscript_file, ''), cg.manuscript_file) as manuscript_file,
           COALESCE(NULLIF(sp.shareable_link, ''), cg.shareable_link) as shareable_link,
           COALESCE(NULLIF(sp.manuscript_name, ''), cg.manuscript_name) as manuscript_name,
           COALESCE(NULLIF(sp.manuscript_stage, ''), cg.manuscript_stage) as manuscript_stage,
           COALESCE(NULLIF(sp.manuscript_updated_at, ''), cg.manuscript_updated_at) as manuscript_updated_at,
           cg.group_name
    FROM thesis_panel tp 
    JOIN user u ON tp.student_id = u.id 
    JOIN student_profile sp ON u.id = sp.user_id 
    LEFT JOIN capstone_group cg ON sp.group_id = cg.id
    WHERE tp.panel_id=$uid
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>CEIT Panel Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/variables.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/layout.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/components.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/auth.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/print.css?v=<?php echo time(); ?>">
    <?php if($tab == 'grading'): ?>
    <link rel="stylesheet" href="css/grading.css?v=<?php echo time(); ?>">
    <?php endif; ?>
    <?php if($tab == 'comments' || $tab == 'meeting_doc'): ?>
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
            <span class="badge-blue"><i class="fa-solid fa-users-viewfinder"></i> Panel Portal</span>
        </div>
    </div>
    
    <div class="sidebar-nav">
        <div class="nav-section"><i class="fa-solid fa-house" style="margin-right:0.5rem"></i> PANEL DASHBOARD</div>
        
        <a href="?tab=overview" class="nav-item <?php echo $tab=='overview'?'active':''; ?>">
            <i class="fa-solid fa-chart-line"></i> Overview
        </a>
        <a href="?tab=assigned" class="nav-item <?php echo $tab=='assigned'?'active':''; ?>">
            <i class="fa-solid fa-file-pen"></i> Assigned Capstones
        </a>
        <a href="?tab=manuscripts" class="nav-item <?php echo $tab=='manuscripts'?'active':''; ?>">
            <i class="fa-solid fa-book-open-reader"></i> Candidate Manuscripts
        </a>
        <a href="?tab=comments" class="nav-item <?php echo $tab=='comments'?'active':''; ?>">
            <i class="fa-solid fa-comments"></i> Panel Comments
        </a>
        <a href="?tab=meeting_doc" class="nav-item <?php echo $tab=='meeting_doc'?'active':''; ?>">
            <i class="fa-solid fa-file-signature"></i> Minutes of the Meeting (MOM)
        </a>
        
        <a href="?tab=settings" class="nav-item <?php echo $tab=='settings'?'active':''; ?>" style="margin-top: 1rem;">
            <i class="fa-solid fa-gear"></i> Settings
        </a>
    </div>

    <div class="sidebar-footer">
        <div class="sidebar-user-avatar"><?php echo strtoupper(substr($name, 0, 1)); ?></div>
        <div class="sidebar-user-info">
            <div class="sidebar-user-name"><?php echo htmlspecialchars($name); ?></div>
            <div class="sidebar-user-status">Online • Panel</div>
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
                <span>Panel Portal</span>
                <i class="fa-solid fa-chevron-right" style="font-size: 0.7rem; color: #94A3B8;"></i>
                <strong><?php echo $tab === 'meeting_doc' ? 'Minutes of the Meeting (MOM)' : ($tab === 'manuscripts' ? 'Candidate Manuscripts (Ch 1 - References)' : ucfirst($tab)); ?></strong>
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
                <span class="topbar-role-badge topbar-role-panel">Panel Member</span>
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
        $view_file = "views/panel/" . $tab . ".php";
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
                        Panel Evaluator
                    </div>
                </div>
            </div>

            <div class="logout-prompt-title">End Active Academic Session?</div>
            <p class="logout-prompt-desc">
                Your defense evaluations, scoring rubrics, and feedback remarks are safely preserved. You will need your university credentials to sign back in.
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
