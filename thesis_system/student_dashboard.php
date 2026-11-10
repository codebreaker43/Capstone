<?php
require 'db.php';
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
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
$res = $conn->query("SELECT * FROM student_profile WHERE user_id=$uid");
$profile = $res ? $res->fetch_assoc() : [];

// Auto-discover group if not yet set in profile
if (empty($profile['group_id'])) {
    $s_id_esc = !empty($profile['student_id']) ? $conn->real_escape_string($profile['student_id']) : '';
    $u_email_esc = !empty($user_data['email']) ? $conn->real_escape_string($user_data['email']) : '';
    $u_name_esc = !empty($user_data['name']) ? $conn->real_escape_string($user_data['name']) : '';

    $find_grp = $conn->query("
        SELECT id FROM capstone_group WHERE leader_id = $uid 
        OR (member2_student_id = '$s_id_esc' AND '$s_id_esc' != '') 
        OR (member3_student_id = '$s_id_esc' AND '$s_id_esc' != '')
        OR (member2_email = '$u_email_esc' AND '$u_email_esc' != '')
        OR (member3_email = '$u_email_esc' AND '$u_email_esc' != '')
        OR (member2_name = '$u_name_esc' AND '$u_name_esc' != '')
        OR (member3_name = '$u_name_esc' AND '$u_name_esc' != '')
        LIMIT 1
    ");
    $discovered_gid = 0;
    if ($find_grp && $find_grp->num_rows > 0) {
        $discovered_gid = (int)$find_grp->fetch_assoc()['id'];
    } else {
        $find_gm = $conn->query("
            SELECT group_id FROM capstone_group_member WHERE student_id = $uid 
            OR (student_no = '$s_id_esc' AND '$s_id_esc' != '')
            OR (email = '$u_email_esc' AND '$u_email_esc' != '')
            OR (member_name = '$u_name_esc' AND '$u_name_esc' != '')
            LIMIT 1
        ");
        if ($find_gm && $find_gm->num_rows > 0) {
            $discovered_gid = (int)$find_gm->fetch_assoc()['group_id'];
        }
    }

    if (!empty($discovered_gid)) {
        $conn->query("UPDATE student_profile SET group_id = $discovered_gid WHERE user_id = $uid");
        $conn->query("UPDATE capstone_group_member SET student_id = $uid WHERE group_id = $discovered_gid AND (student_no = '$s_id_esc' OR email = '$u_email_esc' OR member_name = '$u_name_esc')");
        $profile['group_id'] = $discovered_gid;
    }
}

// Fetch student capstone group data and compile all group members
$my_group = null;
$my_group_members = [];
$group_student_ids = [$uid];
$group_id = 0;

if (!empty($profile['group_id'])) {
    $gid = (int)$profile['group_id'];
    $g_res = $conn->query("SELECT * FROM capstone_group WHERE id=$gid");
    if ($g_res && $g_res->num_rows > 0) {
        $my_group = $g_res->fetch_assoc();
        $group_id = $gid;
        $my_group_members = get_group_members_roster($conn, $gid);

        if (!empty($my_group['leader_id'])) {
            $group_student_ids[] = (int)$my_group['leader_id'];
        }
        foreach ($my_group_members as $m) {
            if (!empty($m['student_id'])) {
                $group_student_ids[] = (int)$m['student_id'];
            }
        }
        $group_student_ids = array_values(array_unique(array_filter($group_student_ids)));

        // Unify group thesis topic, title_status, and abstract across all group members
        if (!empty($my_group['thesis_topic'])) {
            $profile['thesis_topic'] = $my_group['thesis_topic'];
        } elseif (!empty($profile['thesis_topic'])) {
            $esc_t = $conn->real_escape_string($profile['thesis_topic']);
            $conn->query("UPDATE capstone_group SET thesis_topic = '$esc_t' WHERE id = $gid");
            $my_group['thesis_topic'] = $profile['thesis_topic'];
        }

        if (!empty($my_group['title_status'])) {
            $profile['title_status'] = $my_group['title_status'];
        }

        if (!empty($my_group['abstract_file'])) {
            $profile['abstract_file'] = $my_group['abstract_file'];
            $profile['abstract_name'] = $my_group['abstract_name'];
            $profile['abstract_text'] = $my_group['abstract_text'];
        }

        if (!empty($my_group['manuscript_file'])) {
            $profile['manuscript_file'] = $my_group['manuscript_file'];
        }
        if (!empty($my_group['shareable_link'])) {
            $profile['shareable_link'] = $my_group['shareable_link'];
        }
        if (!empty($my_group['manuscript_name'])) {
            $profile['manuscript_name'] = $my_group['manuscript_name'];
        }
        if (!empty($my_group['manuscript_stage'])) {
            $profile['manuscript_stage'] = $my_group['manuscript_stage'];
        }
        if (!empty($my_group['manuscript_notes'])) {
            $profile['manuscript_notes'] = $my_group['manuscript_notes'];
        }
        if (!empty($my_group['manuscript_updated_at'])) {
            $profile['manuscript_updated_at'] = $my_group['manuscript_updated_at'];
        }
    }
}
$group_student_ids_str = implode(',', $group_student_ids);

// Match request & Assigned Adviser (Shared across entire group)
$group_req_where = ($group_id > 0) ? "(m.group_id = $group_id OR m.student_id IN ($group_student_ids_str))" : "m.student_id = $uid";
$req_res = $conn->query("SELECT m.*, u.name as adv_name, u.email as adv_email FROM match_request m JOIN user u ON m.adviser_id = u.id WHERE $group_req_where ORDER BY (m.status='accepted') DESC, m.id DESC");
$req_count = $req_res ? $req_res->num_rows : 0;

$accepted_adv = $conn->query("SELECT m.*, u.name as adv_name, u.email as adv_email FROM match_request m JOIN user u ON m.adviser_id = u.id WHERE $group_req_where AND m.status='accepted' LIMIT 1")->fetch_assoc();
$my_adv_id = $accepted_adv['adviser_id'] ?? 0;
$adviser_assigned = ($my_adv_id > 0) ? 1 : 0;

// Panel data for student & group
$panel_group_where = ($group_id > 0) ? "(tp.group_id = $group_id OR tp.student_id IN ($group_student_ids_str))" : "tp.student_id = $uid";
$panel_res = $conn->query("SELECT tp.*, u.name as panel_name FROM thesis_panel tp JOIN user u ON tp.panel_id = u.id WHERE $panel_group_where");
$panel_count = $panel_res ? $panel_res->num_rows : 0;

// Announcements: count new (last 24h) from assigned adviser only
$ann_new_badge = 0;
if ($my_adv_id > 0) {
    $ann_new_badge = (int)($conn->query("SELECT COUNT(*) as c FROM announcements WHERE adviser_id=$my_adv_id AND created_at >= NOW() - INTERVAL 24 HOUR")->fetch_assoc()['c'] ?? 0);
}

// Document feedback updates for student / group
$doc_group_where = ($group_id > 0) ? "(group_id = $group_id OR student_id IN ($group_student_ids_str))" : "student_id = $uid";
$sub_feedback_badge = (int)($conn->query("SELECT COUNT(*) as c FROM document_submissions WHERE $doc_group_where AND status IN ('needs_revision', 'approved') AND reviewed_at >= NOW() - INTERVAL 7 DAY")->fetch_assoc()['c'] ?? 0);

// Recent team updates badge (last 24h from teammates)
$team_updates_badge = 0;
if ($group_id > 0) {
    $team_updates_badge = (int)($conn->query("SELECT COUNT(*) as c FROM capstone_group_updates WHERE group_id = $group_id AND user_id != $uid AND created_at >= NOW() - INTERVAL 24 HOUR")->fetch_assoc()['c'] ?? 0);
}

// Active Defense Schedule badge
$student_sched_where = ($group_id > 0) ? "(student_id = $uid OR group_id = $group_id)" : "student_id = $uid";
$active_sched_badge = (int)($conn->query("SELECT COUNT(*) as c FROM defense_meetings WHERE $student_sched_where AND status IN ('scheduled', 'in_progress') AND meeting_date >= CURDATE()")->fetch_assoc()['c'] ?? 0);

$graded_count = 0;
$total_grade = 0;
if ($panel_res) {
    while($p = $panel_res->fetch_assoc()) {
        if($p['status'] == 'graded') {
            $graded_count++;
            $total_grade += (float)$p['grade'];
        }
    }
}
$avg_grade = ($graded_count > 0) ? round($total_grade / $graded_count, 1) : 0;

// Dynamic 5-stage progress calculation based on unified group system flow
$has_topic = !empty($profile['thesis_topic']) || (!empty($my_group) && !empty($my_group['thesis_topic']));
$step1_done = $has_topic ? 1 : 0;
$step2_done = ($req_count > 0) ? 1 : 0;
$step3_done = $adviser_assigned;
$step4_done = ($panel_count > 0) ? 1 : 0;
$step5_done = ($panel_count > 0 && $graded_count == $panel_count) ? 1 : 0;

$completed_steps = 0;
if ($step1_done) $completed_steps++;
if ($step2_done) $completed_steps++;
if ($step3_done) $completed_steps++;
if ($step3_done && $step4_done) $completed_steps++;
if ($step3_done && $step5_done) $completed_steps++;
$progress = $completed_steps * 20;

// IT-199 Requirements Checklist stats for badges
$checklist_items = get_student_checklist_items($conn, $uid, $group_id);
$checklist_stats = get_checklist_statistics($checklist_items);

// Consultation summary for sidebar badge: decrement by 1 per view/click, auto-hide when 0
if ($tab === 'consultations') {
    $cons_seen_where = ($group_id > 0) ? "(group_id = $group_id OR student_id = $uid)" : "student_id = $uid";
    $viewed_stu_cid = !empty($_GET['cons_id']) ? (int)$_GET['cons_id'] : 0;
    if ($viewed_stu_cid > 0) {
        $conn->query("UPDATE adviser_consultations SET student_seen = 1 WHERE id = $viewed_stu_cid AND $cons_seen_where AND student_seen = 0");
    } else {
        $one_stu_unseen = $conn->query("SELECT id FROM adviser_consultations WHERE $cons_seen_where AND status = 'follow_up_required' AND student_seen = 0 ORDER BY id DESC LIMIT 1");
        if ($one_stu_unseen && $row_su = $one_stu_unseen->fetch_assoc()) {
            $su_id = (int)$row_su['id'];
            $conn->query("UPDATE adviser_consultations SET student_seen = 1 WHERE id = $su_id");
        }
    }
}
$student_cons_summary = get_student_consultation_summary($conn, $uid, $group_id);
$cons_followup_badge = (int)($student_cons_summary['stats']['unread_followup'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>CEIT Student Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/variables.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/layout.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/components.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/auth.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/print.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/meeting_doc.css?v=<?php echo time(); ?>">
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
            <span class="badge-blue"><i class="fa-solid fa-graduation-cap"></i> Student Portal</span>
        </div>
    </div>
    
    <div class="sidebar-nav">
        <div class="nav-section"><i class="fa-solid fa-house" style="margin-right:0.5rem"></i> STUDENT DASHBOARD</div>
        
        <a href="?tab=overview" class="nav-item <?php echo $tab=='overview'?'active':''; ?>">
            <i class="fa-solid fa-chart-line"></i> Overview
            <?php if($team_updates_badge > 0): ?>
            <span style="background:#10B981;color:white;padding:2px 7px;border-radius:999px;font-size:0.7rem;font-weight:700;margin-left:auto;" title="New team updates in last 24h"><i class="fa-solid fa-users"></i> +<?php echo $team_updates_badge; ?></span>
            <?php endif; ?>
        </a>
        <a href="?tab=submission" class="nav-item <?php echo $tab=='submission'?'active':''; ?>">
            <i class="fa-solid fa-pen-ruler"></i> Capstone Submission
            <?php if($sub_feedback_badge > 0): ?>
            <span style="background:var(--accent-yellow);color:var(--sidebar-bg);padding:2px 7px;border-radius:999px;font-size:0.7rem;font-weight:700;margin-left:auto;"><i class="fa-solid fa-bell"></i> Update</span>
            <?php endif; ?>
        </a>
        <a href="?tab=manuscript" class="nav-item <?php echo $tab=='manuscript'?'active':''; ?>">
            <i class="fa-solid fa-file-pdf"></i> Manuscript (Ch 1 - Refs)
            <?php if(!empty($profile['manuscript_file']) || !empty($profile['shareable_link'])): ?>
            <span style="background:#059669;color:white;padding:2px 7px;border-radius:999px;font-size:0.7rem;font-weight:700;margin-left:auto;"><i class="fa-solid fa-check"></i> On File</span>
            <?php else: ?>
            <span style="background:#EF4444;color:white;padding:2px 7px;border-radius:999px;font-size:0.7rem;font-weight:700;margin-left:auto;" title="Upload Chapters 1 to References"><i class="fa-solid fa-triangle-exclamation"></i> Required</span>
            <?php endif; ?>
        </a>
        <a href="?tab=checklist" class="nav-item <?php echo $tab=='checklist'?'active':''; ?>">
            <i class="fa-solid fa-list-check"></i> Requirements Checklist
            <?php if($checklist_stats['incomplete'] > 0): ?>
            <span style="background:#EF4444;color:white;padding:2px 7px;border-radius:999px;font-size:0.7rem;font-weight:700;margin-left:auto;" title="<?php echo $checklist_stats['incomplete']; ?> requirements incomplete"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo $checklist_stats['incomplete']; ?></span>
            <?php else: ?>
            <span style="background:#059669;color:white;padding:2px 7px;border-radius:999px;font-size:0.7rem;font-weight:700;margin-left:auto;"><i class="fa-solid fa-check"></i></span>
            <?php endif; ?>
        </a>
        <a href="?tab=group" class="nav-item <?php echo $tab=='group'?'active':''; ?>">
            <i class="fa-solid fa-users"></i> Capstone Group
            <?php if(empty($profile['group_id'])): ?>
                <span style="background:var(--accent-yellow);color:var(--sidebar-bg);padding:2px 7px;border-radius:999px;font-size:0.7rem;font-weight:700;margin-left:auto;">Needed</span>
            <?php elseif(count($my_group_members) < 3): ?>
                <span style="background:#F59E0B;color:white;padding:2px 7px;border-radius:999px;font-size:0.7rem;font-weight:700;margin-left:auto;"><?php echo count($my_group_members); ?>/3</span>
            <?php else: ?>
                <span style="background:#059669;color:white;padding:2px 7px;border-radius:999px;font-size:0.7rem;font-weight:700;margin-left:auto;">3/3</span>
            <?php endif; ?>
        </a>
        <a href="?tab=panelists" class="nav-item <?php echo $tab=='panelists'?'active':''; ?>">
            <i class="fa-solid fa-chalkboard-user"></i> My Panelists
        </a>
        <a href="?tab=schedule" class="nav-item <?php echo $tab=='schedule'?'active':''; ?>">
            <i class="fa-solid fa-calendar-check"></i> Defense Schedule
            <?php if($active_sched_badge > 0): ?>
            <span style="background:var(--accent-yellow);color:var(--sidebar-bg);padding:2px 7px;border-radius:999px;font-size:0.7rem;font-weight:700;margin-left:auto;"><i class="fa-solid fa-bolt"></i> Scheduled</span>
            <?php endif; ?>
        </a>
        <a href="?tab=progress" class="nav-item <?php echo $tab=='progress'?'active':''; ?>">
            <i class="fa-solid fa-arrow-trend-up"></i> Progress
        </a>
        <a href="?tab=consultations" class="nav-item <?php echo $tab=='consultations'?'active':''; ?>">
            <i class="fa-solid fa-comments"></i> Adviser Consultations
            <?php if($cons_followup_badge > 0): ?>
                <span id="sidebarStudentConsultationsBadge" data-count="<?php echo $cons_followup_badge; ?>" style="background:#F59E0B;color:#0F172A;padding:2px 7px;border-radius:999px;font-size:0.7rem;font-weight:800;margin-left:auto;" title="Adviser asked follow-up questions"><?php echo $cons_followup_badge; ?></span>
            <?php elseif(!empty($student_cons_summary['is_process_completed'])): ?>
                <span style="background:#059669;color:white;padding:2px 7px;border-radius:999px;font-size:0.7rem;font-weight:700;margin-left:auto;" title="Consultation process completed"><i class="fa-solid fa-check"></i></span>
            <?php endif; ?>
        </a>
        <a href="?tab=requests" class="nav-item <?php echo $tab=='requests'?'active':''; ?>">
            <i class="fa-solid fa-paper-plane"></i> Requests
        </a>
        <a href="?tab=announcements" class="nav-item <?php echo $tab=='announcements'?'active':''; ?>">
            <i class="fa-solid fa-bullhorn"></i> Announcements
            <?php if($ann_new_badge > 0): ?>
            <span style="background:var(--accent-yellow);color:var(--sidebar-bg);padding:2px 7px;border-radius:999px;font-size:0.72rem;font-weight:700;margin-left:auto;"><?php echo $ann_new_badge; ?></span>
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
            <div class="sidebar-user-status">Online • Student</div>
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
                <span>CEIT Portal</span>
                <i class="fa-solid fa-chevron-right" style="font-size: 0.7rem; color: #94A3B8;"></i>
                <strong><?php echo ($tab == 'checklist') ? 'Requirements Checklist' : (($tab == 'consultations') ? 'Adviser Consultations' : (($tab == 'manuscript') ? 'Capstone Manuscript (Ch 1 - References)' : ucfirst($tab))); ?></strong>
            </div>
            <span class="topbar-campus-badge">
                <i class="fa-solid fa-building-columns"></i> CSU Cabadbaran
            </span>
            <?php if(!empty($my_group)): ?>
            <span class="topbar-campus-badge" style="background:#ECFDF5; border-color:#A7F3D0; color:#065F46; font-weight:700;" title="Live Shared Capstone Group Dashboard">
                <i class="fa-solid fa-users"></i> Team: <?php echo htmlspecialchars(mb_strimwidth($my_group['group_name'], 0, 24, '...')); ?>
                <span style="display:inline-block; width:7px; height:7px; border-radius:50%; background:#10B981; margin-left:3px;" title="Connected & Synchronized"></span>
            </span>
            <?php endif; ?>
        </div>
        <div class="topbar-right">
            <?php include 'views/common/notifications_widget.php'; ?>
            <?php include 'views/common/role_switcher_widget.php'; ?>
            <div class="topbar-user-pill">
                <div class="topbar-avatar"><?php echo strtoupper(substr($name, 0, 1)); ?></div>
                <span class="topbar-user-name"><?php echo htmlspecialchars($name); ?></span>
                <span class="topbar-role-badge topbar-role-student"><?php echo (!empty($my_group) && (int)($my_group['leader_id'] ?? 0) === (int)$uid) ? '👑 Leader' : '👤 Member'; ?></span>
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
        $view_file = "views/student/" . $tab . ".php";
        if(file_exists($view_file)) {
            include $view_file;
        } else {
            echo '<div class="card"><div class="card-body"><h2 style="color: var(--text-muted); text-align: center; padding: 3rem;"><i class="fa-solid fa-person-digging fa-3x" style="margin-bottom: 1rem;"></i><br>This module is under construction.</h2></div></div>';
        }
    ?>
</div>

<!-- Global AI Modal (Student Only) -->
<div id="aiModal" class="modal" style="z-index: 99999;">
    <div class="modal-content" style="max-width: 580px; padding: 1.25rem 1.5rem; max-height: 86vh; overflow-y: auto;">
        <span class="close" onclick="closeAIModal()">&times;</span>
        <h2 style="margin-bottom: 0.5rem; color: var(--primary-blue); font-size: 1.15rem;"><i class="fa-solid fa-scale-balanced"></i> AI + MCDA Adviser Matching Engine</h2>
        <div id="aiLoading" style="text-align: center; padding: 2rem 1.5rem;">
            <i class="fa-solid fa-circle-notch fa-spin fa-2x" style="color: var(--csu-gold); margin-bottom: 0.75rem;"></i>
            <p style="color: var(--text-muted); font-size: 0.88rem;">Evaluating multiple criteria (Specialization, Publications, Supervised Titles, Capacity &amp; Qualification)...</p>
        </div>
        <div id="aiResults" style="display: none;">
            <!-- Modal Title Input & MCDA Priority Profile Bar -->
            <div style="background: #F8FAFC; border: 1px solid #CBD5E1; border-radius: 8px; padding: 0.75rem 0.9rem; margin-bottom: 0.85rem;">
                <label style="font-size: 0.76rem; font-weight: 700; color: #0F172A; display: block; margin-bottom: 0.35rem;">
                    <i class="fa-solid fa-pen-nib" style="color: var(--csu-gold);"></i> Proposed Capstone Title / Topic:
                </label>
                <div style="display: flex; gap: 0.4rem; margin-bottom: 0.5rem;">
                    <input type="text" id="aiModalTopicInput" onkeydown="if(event.key==='Enter'){event.preventDefault(); refreshAIModalWithTitle();}" placeholder="Enter proposed capstone title to match..." style="flex: 1; padding: 0.5rem 0.65rem; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 0.82rem; font-family: 'Inter', sans-serif;">
                    <button type="button" onclick="refreshAIModalWithTitle()" class="btn-primary" style="padding: 0.5rem 0.85rem; font-size: 0.8rem; white-space: nowrap; border-radius: 6px;">
                        <i class="fa-solid fa-wand-magic-sparkles"></i> Evaluate MCDA
                    </button>
                </div>
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.4rem; flex-wrap: wrap; padding-top: 0.4rem; border-top: 1px dashed #CBD5E1;">
                    <span style="font-size: 0.72rem; font-weight: 700; color: #334155;"><i class="fa-solid fa-sliders" style="color: #047857;"></i> MCDA Priority Profile:</span>
                    <select id="aiModalMcdaPreset" onchange="refreshAIModalWithTitle()" style="padding: 0.3rem 0.55rem; border: 1px solid #94A3B8; border-radius: 6px; font-size: 0.74rem; font-weight: 700; color: #0F172A; background: white;">
                        <option value="balanced">Balanced MCDA (Default: 35/25/15/15/10)</option>
                        <option value="publications">Research &amp; Publications Priority (25/40/15/10/10)</option>
                        <option value="specialization">Specialization-First Priority (50/20/15/10/5)</option>
                        <option value="availability">Workload &amp; Availability Priority (30/15/15/30/10)</option>
                    </select>
                </div>
            </div>

            <p id="aiModalSubtitle" style="color: var(--text-muted); margin-bottom: 0.85rem; font-size: 0.82rem;">Based on Multi-Criteria Decision Analysis (MCDA), the CEIT AI has ranked the most suitable research advisers for you:</p>
            <div id="adviserList">
                <!-- Data populated by JS -->
            </div>
        </div>
    </div>
</div>

<script>
    function formatScholarLink(url, name) {
        url = (url || '').trim();
        name = (name || '').trim();
        if (!url || url.includes('@')) {
            if (!name) return '';
            const clean = name.replace(/^(Dr\.|Engr\.|Prof\.|Mr\.|Ms\.|Mrs\.)\s+/i, '').replace(/,\s*(MIT|MSIT|MSCS|PhD|DIT|MEP|ECE|CPE).*$/i, '').trim();
            return 'https://scholar.google.com/scholar?hl=en&q=' + encodeURIComponent('author:"' + clean + '"');
        }
        if (url.startsWith('http://') || url.startsWith('https://')) return url;
        if (url.startsWith('scholar.google.')) return 'https://' + url;
        if (/[?&]user=([a-zA-Z0-9_-]{8,20})/.test(url)) {
            const m = url.match(/[?&]user=([a-zA-Z0-9_-]{8,20})/);
            return 'https://scholar.google.com/citations?user=' + encodeURIComponent(m[1]) + '&hl=en';
        }
        return 'https://scholar.google.com/scholar?hl=en&q=' + encodeURIComponent('author:"' + url + '"');
    }

    window.openAIModal = function(customTitle = null) {
        const modal = document.getElementById('aiModal');
        if (modal) modal.style.display = 'block';
        document.getElementById('aiLoading').style.display = 'block';
        document.getElementById('aiResults').style.display = 'none';

        // Check if page already has an entered title
        if (customTitle === null || customTitle === undefined) {
            const pageInput = document.getElementById('gatekeeper_topic') || document.getElementById('ai_tab_topic');
            if (pageInput && pageInput.value.trim()) {
                customTitle = pageInput.value.trim();
            }
        }
        
        // Also check if user entered specialization keywords or MCDA preset
        const skillsInput = document.getElementById('gatekeeper_skills') || document.getElementById('ai_tab_skills');
        const customSkills = skillsInput ? skillsInput.value.trim() : '';
        const modalPresetEl = document.getElementById('aiModalMcdaPreset');
        const tabPresetEl = document.getElementById('mcdaPresetSelect');
        const mcdaPreset = (modalPresetEl && modalPresetEl.value) ? modalPresetEl.value : ((tabPresetEl && tabPresetEl.value) ? tabPresetEl.value : 'balanced');
        const skillsParam = customSkills ? `&skills=${encodeURIComponent(customSkills)}` : '';
        const titleParam = customTitle ? `&title=${encodeURIComponent(customTitle)}&save_topic=1` : '';
        const presetParam = `&mcda_preset=${encodeURIComponent(mcdaPreset)}`;
        
        fetch(`api.php?action=get_matches${titleParam}${skillsParam}${presetParam}`)
            .then(res => res.json())
            .then(data => {
                setTimeout(() => {
                    document.getElementById('aiLoading').style.display = 'none';
                    document.getElementById('aiResults').style.display = 'block';

                    window.studentHasAbstract = !!data.has_abstract;
                    window.studentAbstractName = data.abstract_name || '';

                    const modalInput = document.getElementById('aiModalTopicInput');
                    if (modalInput) {
                        modalInput.value = data.thesis_topic || customTitle || '';
                    }

                    const list = document.getElementById('adviserList');
                    list.innerHTML = '';
                    
                    const subtitle = document.getElementById('aiModalSubtitle');
                    if (subtitle) {
                        const hasTopic = (data.thesis_topic && data.thesis_topic.trim()) || (modalInput && modalInput.value.trim());
                        if (hasTopic) {
                            if (data.has_abstract) {
                                subtitle.innerHTML = `Showing CEIT faculty specialists ranked by <strong>MCDA</strong> for your title &amp; <span style="color: #DC2626; font-weight: 700;"><i class="fa-solid fa-file-pdf"></i> Concept PDF</span>:`;
                            } else {
                                subtitle.innerHTML = `Showing CEIT faculty specialists ranked by <strong>MCDA</strong> for your proposed capstone title:`;
                            }
                        } else if (data.student_dept) {
                            subtitle.innerHTML = `Showing MCDA-evaluated faculty advisers for <strong>${data.student_dept}</strong>:`;
                        } else {
                            subtitle.innerHTML = `Based on Multi-Criteria Decision Analysis (MCDA), the CEIT AI has ranked the best faculty matches for you:`;
                        }
                    }
                    
                    let matches = [];
                    if (data.matches && Array.isArray(data.matches)) {
                        matches = data.matches;
                    } else if (Array.isArray(data)) {
                        matches = data;
                    }
                    
                    const active = data.active_request;
                    let headerNotice = '';

                    // Strict Abstract PDF Gating Notice
                    if (!data.has_abstract) {
                        headerNotice += `
                            <div style="background: #FEF2F2; color: #991B1B; border: 1.5px solid #FECACA; border-radius: 8px; padding: 0.85rem 1rem; margin-bottom: 0.85rem; display: flex; align-items: center; gap: 0.75rem;">
                                <div style="width: 36px; height: 36px; border-radius: 50%; background: #FEE2E2; color: #DC2626; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                                    <i class="fa-solid fa-file-pdf"></i>
                                </div>
                                <div style="flex: 1;">
                                    <strong style="display: block; font-size: 0.88rem; color: #991B1B; margin-bottom: 2px;">
                                        Capstone Concept PDF Required to Proceed
                                    </strong>
                                    <span style="font-size: 0.78rem; color: #B91C1C;">
                                        In compliance with CEIT academic policy, you must attach and upload your official Capstone Concept in PDF format (.pdf) in the Proposed Title form before an adviser request can be submitted.
                                    </span>
                                </div>
                            </div>
                        `;
                    }

                    if (active) {
                        if (active.status === 'pending') {
                            headerNotice += `
                                <div style="background: #FEF3C7; color: #92400E; border: 1px solid #FDE68A; border-radius: 6px; padding: 0.7rem 0.85rem; margin-bottom: 0.85rem; display: flex; align-items: center; gap: 0.6rem; font-size: 0.82rem;">
                                    <i class="fa-regular fa-clock" style="font-size: 1.1rem; color: #D97706; flex-shrink: 0;"></i>
                                    <div>
                                        <strong>Request Pending:</strong> Sent to <strong>${active.adv_name}</strong>. Only 1 request allowed at a time. Please wait for adviser approval.
                                    </div>
                                </div>
                            `;
                        } else if (active.status === 'accepted') {
                            headerNotice += `
                                <div style="background: #D1FAE5; color: #065F46; border: 1px solid #A7F3D0; border-radius: 6px; padding: 0.7rem 0.85rem; margin-bottom: 0.85rem; display: flex; align-items: center; gap: 0.6rem; font-size: 0.82rem;">
                                    <i class="fa-solid fa-circle-check" style="font-size: 1.1rem; color: #059669; flex-shrink: 0;"></i>
                                    <div>
                                        <strong>Adviser Assigned:</strong> <strong>${active.adv_name}</strong> is your officially accepted capstone adviser.
                                    </div>
                                </div>
                            `;
                        }
                    }
                    
                    if (data.notice) {
                        headerNotice += `
                            <div style="background: #EFF6FF; color: #1E40AF; border: 1px solid #BFDBFE; border-radius: 6px; padding: 0.65rem 0.85rem; margin-bottom: 0.85rem; display: flex; align-items: center; gap: 0.6rem; font-size: 0.82rem;">
                                <i class="fa-solid fa-circle-info" style="font-size: 1rem; color: #3B82F6; flex-shrink: 0;"></i>
                                <div>${data.notice}</div>
                            </div>
                        `;
                    }
                    
                    list.innerHTML = headerNotice;
                    
                    if (matches.length > 0) {
                        matches.forEach(adv => {
                            let actionBtn = '';
                            if (active) {
                                if (active.adviser_id == adv.id && active.status === 'pending') {
                                    actionBtn = `<button class="btn-primary" disabled style="background: #FEF3C7; color: #B45309; border: 1px solid #FDE68A; cursor: not-allowed; font-weight: 700; padding: 0.35rem 0.75rem; font-size: 0.78rem;"><i class="fa-regular fa-clock"></i> Pending</button>`;
                                } else if (active.adviser_id == adv.id && active.status === 'accepted') {
                                    actionBtn = `<button class="btn-primary btn-success" disabled style="cursor: not-allowed; font-weight: 700; padding: 0.35rem 0.75rem; font-size: 0.78rem;"><i class="fa-solid fa-check"></i> Assigned</button>`;
                                } else {
                                    actionBtn = `<button class="btn-primary" disabled style="opacity: 0.45; cursor: not-allowed; background: #94A3B8; padding: 0.35rem 0.75rem; font-size: 0.78rem;"><i class="fa-solid fa-lock"></i> Locked</button>`;
                                }
                            } else if (!data.has_abstract) {
                                actionBtn = `<button class="btn-primary" onclick="sendRequest(${adv.id}, ${adv.match_score})" style="background: #DC2626; border-color: #B91C1C; padding: 0.35rem 0.75rem; font-size: 0.78rem; font-weight: 700;"><i class="fa-solid fa-file-pdf"></i> Abstract Required</button>`;
                            } else {
                                actionBtn = `<button class="btn-primary" onclick="sendRequest(${adv.id}, ${adv.match_score})" style="padding: 0.35rem 0.75rem; font-size: 0.78rem;"><i class="fa-solid fa-paper-plane"></i> Send Request</button>`;
                            }
                            
                            const availSlots = (adv.available_slots !== undefined) ? adv.available_slots : 5;
                            const maxCap = adv.max_capacity || 5;
                            const deptTag = adv.department ? `<span style="background: #F1F5F9; color: #475569; padding: 2px 6px; border-radius: 4px; font-size: 0.7rem; font-weight: 600;">${adv.department}</span>` : '';
                            const slotTag = `<span style="background: ${availSlots > 0 ? '#ECFDF5' : '#FEF2F2'}; color: ${availSlots > 0 ? '#047857' : '#DC2626'}; border: 1px solid ${availSlots > 0 ? '#A7F3D0' : '#FECACA'}; padding: 1px 5px; border-radius: 4px; font-size: 0.68rem; font-weight: 700;"><i class="fa-solid fa-users-gear"></i> ${availSlots}/${maxCap} Slots</span>`;
                            const degTag = adv.academic_degree ? `<span style="background: #EFF6FF; color: #1D4ED8; border: 1px solid #BFDBFE; padding: 1px 5px; border-radius: 4px; font-size: 0.68rem; font-weight: 700;">${adv.academic_degree}</span>` : '';
                            const gradTag = adv.graduation_year ? `<span style="background: #FEF3C7; color: #92400E; border: 1px solid #FDE68A; padding: 1px 5px; border-radius: 4px; font-size: 0.68rem; font-weight: 700;"><i class="fa-solid fa-graduation-cap"></i> Grad: ${adv.graduation_year}</span>` : '';
                            const scholarLink = formatScholarLink(adv.google_scholar_url, adv.name);
                            const scholarTag = scholarLink ? `<a href="${scholarLink}" target="_blank" rel="noopener noreferrer" style="background: #EFF6FF; color: #2563EB; border: 1px solid #93C5FD; padding: 1px 5px; border-radius: 4px; font-size: 0.68rem; font-weight: 700; text-decoration: none;"><i class="fa-solid fa-arrow-up-right-from-square"></i> Scholar</a>` : '';
                            const expText = adv.expertise ? adv.expertise : 'General Faculty Adviser';
                            const pubLines = (adv.published_papers || '').split(/\r?\n/).map(s => s.trim()).filter(Boolean);
                            const prevLines = (adv.previous_advised_titles || '').split(/\r?\n/).map(s => s.trim()).filter(Boolean);

                            let mcdaPills = '';
                            if (adv.mcda_criteria) {
                                const c = adv.mcda_criteria;
                                mcdaPills = `
                                    <div style="margin-top: 0.35rem; display: flex; flex-wrap: wrap; gap: 0.25rem;">
                                        ${c.specialization ? `<span style="background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0; font-size: 0.66rem; font-weight: 700; padding: 1px 5px; border-radius: 4px;" title="Specialization Alignment">Spec: ${c.specialization.raw_score}%</span>` : ''}
                                        ${c.publications ? `<span style="background: #EFF6FF; color: #1E40AF; border: 1px solid #BFDBFE; font-size: 0.66rem; font-weight: 700; padding: 1px 5px; border-radius: 4px;" title="Published Research Papers">Pubs: ${c.publications.raw_score}%</span>` : ''}
                                        ${c.supervised_titles ? `<span style="background: #FFFBEB; color: #92400E; border: 1px solid #FDE68A; font-size: 0.66rem; font-weight: 700; padding: 1px 5px; border-radius: 4px;" title="Supervised Research Track Record">Supervised: ${c.supervised_titles.raw_score}%</span>` : ''}
                                        ${c.workload_capacity ? `<span style="background: #F5F3FF; color: #5B21B6; border: 1px solid #DDD6FE; font-size: 0.66rem; font-weight: 700; padding: 1px 5px; border-radius: 4px;" title="Advisory Slot Availability">Capacity: ${c.workload_capacity.raw_score}%</span>` : ''}
                                        ${c.department_qualification ? `<span style="background: #F1F5F9; color: #334155; border: 1px solid #CBD5E1; font-size: 0.66rem; font-weight: 700; padding: 1px 5px; border-radius: 4px;" title="Department & Qualification Fit">Qual: ${c.department_qualification.raw_score}%</span>` : ''}
                                    </div>
                                `;
                            }

                            let extraMeta = mcdaPills;
                            if (pubLines.length > 0) {
                                extraMeta += `<div style="margin-top: 0.35rem; font-size: 0.72rem; color: #065F46;"><i class="fa-solid fa-book-journal-whills"></i> <strong>Publications (${pubLines.length}):</strong> ${pubLines[0].replace(/^\d+[\.\)]\s*/, '')}</div>`;
                            }
                            if (prevLines.length > 0) {
                                extraMeta += `<div style="margin-top: 0.2rem; font-size: 0.72rem; color: #92400E;"><i class="fa-solid fa-folder-tree"></i> <strong>Previously Advised (${prevLines.length}):</strong> ${prevLines[0].replace(/^[\-\*\d\.\)\s]+/, '')}</div>`;
                            }
                            
                            list.innerHTML += `
                                <div style="border: 1px solid #E2E8F0; border-radius: 8px; padding: 0.75rem 0.9rem; margin-bottom: 0.75rem; display: flex; justify-content: space-between; align-items: flex-start; box-shadow: 0 1px 3px rgba(0,0,0,0.02); background: white;">
                                    <div style="flex: 1; padding-right: 0.5rem;">
                                        <h3 style="margin: 0 0 0.25rem 0; color: #1E293B; font-size: 0.92rem; display: flex; align-items: center; gap: 0.3rem; flex-wrap: wrap;">
                                            <i class="fa-solid fa-chalkboard-user" style="color: var(--primary-blue);"></i> ${adv.name} ${deptTag} ${slotTag} ${degTag} ${gradTag} ${scholarTag}
                                        </h3>
                                        <p style="color: #64748B; font-size: 0.78rem; margin: 0; line-height: 1.35;"><i class="fa-solid fa-tags" style="color: #94A3B8;"></i> ${expText}</p>
                                        ${extraMeta}
                                    </div>
                                    <div style="text-align: right; flex-shrink: 0; margin-left: 0.5rem;">
                                        <div style="font-size: 1.02rem; font-weight: 800; color: var(--primary-blue); margin-bottom: 0.1rem;">${adv.match_score}% MCDA</div>
                                        <div style="font-size: 0.65rem; font-weight: 700; color: #047857; margin-bottom: 0.3rem;">${adv.mcda_rank_label || 'MCDA Match'}</div>
                                        ${actionBtn}
                                    </div>
                                </div>
                            `;
                        });
                    } else {
                        const topicVal = modalInput ? modalInput.value.trim() : '';
                        const topicMsg = topicVal ? `specializing in "<strong>${topicVal}</strong>"` : '';
                        const deptMsg = data.student_dept ? ` in <strong>${data.student_dept}</strong>` : '';
                        list.innerHTML += `<div style="text-align: center; color: #64748B; padding: 1.75rem 1rem; font-size: 0.88rem; background: #F8FAFC; border: 1px dashed #CBD5E1; border-radius: 8px;"><i class="fa-solid fa-filter-circle-xmark fa-2x" style="color: #94A3B8; margin-bottom: 0.6rem; display: block;"></i> No faculty advisers ${topicMsg}${deptMsg} found. Try adjusting your proposed capstone title.</div>`;
                    }
                }, 600);
            })
            .catch(err => {
                document.getElementById('aiLoading').style.display = 'none';
                document.getElementById('aiResults').style.display = 'block';
                document.getElementById('adviserList').innerHTML = '<div style="text-align: center; color: #EF4444; padding: 1rem; font-size: 0.85rem;"><i class="fa-solid fa-triangle-exclamation"></i> Unable to load faculty matches at this moment. Please refresh the page.</div>';
            });
    }

    function refreshAIModalWithTitle() {
        const input = document.getElementById('aiModalTopicInput');
        const title = input ? input.value.trim() : '';
        openAIModal(title);
    }

    function closeAIModal() {
        document.getElementById('aiModal').style.display = 'none';
    }

    function sendRequest(adviser_id, score) {
        if (!window.studentHasAbstract) {
            closeAIModal();
            if (typeof window.showUniversityNotice === 'function') {
                window.showUniversityNotice({
                    heading: 'Academic Requirement Notice',
                    pillText: 'Official Capstone Policy',
                    title: 'Capstone Concept PDF Required',
                    message: 'In compliance with CEIT academic policy, you cannot submit an adviser request without uploading your official Capstone Concept in PDF format (.pdf).',
                    hint: 'Please attach and save your Capstone Concept (.pdf) in the Proposed Title form before submitting an adviser request.',
                    targetFocusId: 'gatekeeper_abstract_file',
                    primaryBtnText: 'Attach Concept PDF Now'
                });
            } else {
                alert("Academic Requirement Notice:\n\nIn compliance with CEIT academic policy, you cannot submit an adviser request without uploading your official Capstone Concept in PDF format (.pdf).\n\nPlease attach your Concept PDF in the Proposed Title form first.");
            }
            return false;
        }
        const currentTab = new URLSearchParams(window.location.search).get('tab') || 'submission';
        const modalInput = document.getElementById('aiModalTopicInput');
        const title = modalInput ? modalInput.value.trim() : '';
        const titleParam = title ? `&title=${encodeURIComponent(title)}` : '';
        window.location.href = `action.php?request_adviser=1&adviser_id=${adviser_id}&match_score=${score}${titleParam}&return_tab=${currentTab}`;
    }

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

    // Auto-open AI modal if redirected from thesis submission
    document.addEventListener("DOMContentLoaded", function() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('show_ai') === '1') {
            openAIModal();
            // Clean up URL so it doesn't pop up again on refresh
            window.history.replaceState({}, document.title, window.location.pathname + "?tab=submission");
        }
    });

    function generateAIPaper() {
        const title = document.getElementById('paper_title').value;
        if(!title.trim()) {
            alert('Please enter a Draft Title first!');
            return;
        }
        
        const btn = document.querySelector('button[onclick="generateAIPaper()"]');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Generating...';
        btn.disabled = true;
        
        fetch('api.php?action=generate_paper&title=' + encodeURIComponent(title))
            .then(res => res.json())
            .then(data => {
                setTimeout(() => {
                    if(data.draft) {
                        document.getElementById('paper_content').value = data.draft;
                    } else if(data.error) {
                        alert(data.error);
                    }
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }, 1000); // Simulate AI thinking delay
            })
            .catch(err => {
                alert('Error generating paper');
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
    }
</script>

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
                        Capstone Student &bull; <?php echo htmlspecialchars($profile['course'] ?? 'CEIT'); ?>
                    </div>
                </div>
            </div>

            <div class="logout-prompt-title">End Active Academic Session?</div>
            <p class="logout-prompt-desc">
                Your capstone topic proposals, manuscript drafts, and adviser requests are safely preserved. You will need your university credentials to sign back in.
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

<!-- Global University Cancel Adviser Request Confirmation Modal -->
<div id="cancelAdviserModal" class="modal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center;">
    <div class="modal-content logout-modal-content" style="max-width: 460px; border-radius: 20px; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.35); border: 1px solid rgba(255,255,255,0.2); background: white;">
        <div class="logout-modal-header" style="background: linear-gradient(135deg, #064E3B 0%, #047857 55%, #022C22 100%); border-bottom: 3px solid #F59E0B;">
            <div class="logout-seal-wrapper">
                <img src="logo.png" alt="CSU Seal">
            </div>
            <div class="logout-univ-subtitle">Caraga State University</div>
            <div class="logout-college-subtitle">College of Engineering and Information Technology</div>
            <h2 style="font-size: 1.25rem; font-weight: 800; margin: 0; color: white;">Cancel Adviser Request</h2>
        </div>
        <div class="logout-modal-body" style="padding: 1.5rem; text-align: center; background: #FFFFFF;">
            
            <div class="logout-user-card" style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 12px; padding: 0.85rem 1rem; margin-bottom: 1.15rem; text-align: left;">
                <div class="logout-user-avatar" style="background: linear-gradient(135deg, #D97706 0%, #B45309 100%);">
                    <i class="fa-solid fa-user-clock"></i>
                </div>
                <div class="logout-user-details">
                    <div style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: #B45309; letter-spacing: 0.5px;">Pending Adviser Request</div>
                    <div id="cancelUnivModalAdviserName" class="logout-user-name" style="font-size: 0.98rem; font-weight: 700; color: #0F172A;">Faculty Adviser</div>
                    <div class="logout-user-role" style="color: #64748B; font-size: 0.74rem;">
                        <span class="logout-session-dot" style="background: #F59E0B;"></span>
                        Awaiting Adviser Acceptance
                    </div>
                </div>
            </div>

            <div class="logout-prompt-title" style="font-size: 1.05rem; font-weight: 800; color: #0F172A; margin-bottom: 0.4rem;">
                Withdraw Your Match Request?
            </div>
            <p class="logout-prompt-desc" style="color: #64748B; font-size: 0.86rem; line-height: 1.55; margin: 0 0 1.35rem 0;">
                Are you sure you want to cancel your adviser request to <strong id="cancelUnivModalAdviserBold" style="color: #0F172A;">this faculty member</strong>? Once cancelled, you can select another adviser from the AI Matcher.
            </p>

            <div class="logout-actions" style="display: flex; gap: 0.75rem; justify-content: center;">
                <button type="button" class="btn-logout-cancel" onclick="closeCancelAdviserModal()" style="flex: 1; padding: 0.7rem 1rem; font-size: 0.88rem; font-weight: 700; background: #F1F5F9; color: #475569; border: 1px solid #CBD5E1; border-radius: 10px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 0.45rem;">
                    <i class="fa-solid fa-arrow-left"></i> Keep Request
                </button>
                <a id="cancelUnivModalConfirmBtn" href="#" class="btn-logout-confirm" style="flex: 1; padding: 0.7rem 1rem; font-size: 0.88rem; font-weight: 700; background: #DC2626; color: white; border: none; border-radius: 10px; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 0.45rem; box-shadow: 0 4px 12px rgba(220, 38, 38, 0.25);">
                    <i class="fa-solid fa-xmark"></i> Cancel Request
                </a>
            </div>

            <div class="logout-modal-footer-note" style="margin-top: 1.25rem; font-size: 0.74rem; color: #94A3B8; display: flex; align-items: center; justify-content: center; gap: 0.4rem;">
                <i class="fa-solid fa-shield-halved"></i> Secured CEIT Academic Portal &bull; CSU Cabadbaran
            </div>
        </div>
    </div>
</div>

<!-- Global University Academic Requirement & Notice Modal -->
<div id="universityNoticeModal" class="modal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.72); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px); z-index: 999999; align-items: center; justify-content: center; padding: 1rem;">
    <div class="modal-content logout-modal-content" style="max-width: 480px; width: 100%; border-radius: 20px; overflow: hidden; box-shadow: 0 25px 60px -15px rgba(0,0,0,0.45), 0 0 0 1px rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.25); background: #FFFFFF; animation: univModalPop 0.28s cubic-bezier(0.16, 1, 0.3, 1);">
        <div class="logout-modal-header" style="background: linear-gradient(135deg, #064E3B 0%, #047857 55%, #022C22 100%); border-bottom: 3px solid #F59E0B; padding: 1.6rem 1.5rem 1.35rem 1.5rem; text-align: center; color: white; position: relative;">
            <div class="logout-seal-wrapper" style="box-shadow: 0 4px 14px rgba(0, 0, 0, 0.3), 0 0 0 3px rgba(245, 158, 11, 0.85); width: 68px; height: 68px; margin-bottom: 0.65rem;">
                <img src="logo.png" alt="CSU Seal">
            </div>
            <div class="logout-univ-subtitle" style="font-size: 0.72rem; letter-spacing: 1.5px; text-transform: uppercase; color: #FCD34D; font-weight: 800; margin-bottom: 0.2rem;">Caraga State University</div>
            <div class="logout-college-subtitle" style="font-size: 0.76rem; color: #D1FAE5; font-weight: 500; margin-bottom: 0.4rem;">College of Engineering and Information Technology</div>
            <h2 id="univNoticeHeading" style="font-size: 1.25rem; font-weight: 800; margin: 0; color: #FFFFFF; letter-spacing: -0.2px;">Academic Requirement Notice</h2>
        </div>
        <div class="logout-modal-body" style="padding: 1.5rem; text-align: center; background: #FFFFFF;">
            
            <!-- Requirement Pill / Category Badge -->
            <div style="margin-bottom: 0.85rem;">
                <span id="univNoticePill" style="display: inline-flex; align-items: center; gap: 0.45rem; padding: 0.35rem 0.9rem; border-radius: 9999px; background: #FEF2F2; color: #991B1B; font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; border: 1px solid #FECACA;">
                    <i id="univNoticePillIcon" class="fa-solid fa-file-pdf"></i>
                    <span id="univNoticePillText">Required Academic Document</span>
                </span>
            </div>

            <!-- Central Icon Box -->
            <div id="univNoticeIconWrapper" style="width: 58px; height: 58px; margin: 0 auto 0.85rem auto; border-radius: 50%; background: linear-gradient(135deg, #FEE2E2 0%, #FECACA 100%); color: #DC2626; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; box-shadow: 0 6px 16px rgba(220, 38, 38, 0.2);">
                <i id="univNoticeMainIcon" class="fa-solid fa-file-circle-exclamation"></i>
            </div>

            <!-- Notice Title & Description -->
            <div id="univNoticeTitle" style="font-size: 1.08rem; font-weight: 800; color: #0F172A; margin-bottom: 0.4rem; letter-spacing: -0.2px;">
                Capstone Concept PDF Required
            </div>
            <p id="univNoticeDesc" style="color: #475569; font-size: 0.86rem; line-height: 1.6; margin: 0 0 1.15rem 0;">
                In accordance with CEIT academic policy, you cannot proceed without uploading your official Capstone Concept in PDF format (.pdf).
            </p>

            <!-- Formal Hint / Policy Guidelines Box -->
            <div id="univNoticeHint" style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 10px; padding: 0.75rem 0.9rem; font-size: 0.79rem; color: #64748B; text-align: left; display: flex; align-items: flex-start; gap: 0.65rem; margin-bottom: 1.35rem;">
                <i class="fa-solid fa-circle-info" style="color: #047857; margin-top: 0.15rem; flex-shrink: 0; font-size: 0.95rem;"></i>
                <span id="univNoticeHintText">Please attach your official Concept PDF document (.pdf) in the proposal form before submitting.</span>
            </div>

            <!-- Action Buttons -->
            <div class="logout-actions" style="display: flex; gap: 0.75rem; justify-content: center;">
                <button type="button" class="btn-logout-cancel" onclick="closeUniversityNoticeModal()" style="flex: 0 0 auto; padding: 0.72rem 1.15rem; font-size: 0.86rem; font-weight: 700; background: #F1F5F9; color: #475569; border: 1px solid #CBD5E1; border-radius: 10px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 0.45rem;">
                    <i class="fa-solid fa-xmark"></i> Dismiss
                </button>
                <button type="button" id="univNoticePrimaryBtn" onclick="onUnivNoticePrimaryClick()" style="flex: 1; padding: 0.72rem 1.15rem; font-size: 0.86rem; font-weight: 700; background: linear-gradient(135deg, #047857 0%, #065F46 100%); color: white; border: none; border-radius: 10px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; box-shadow: 0 4px 14px rgba(4, 120, 87, 0.35);">
                    <i class="fa-solid fa-file-circle-plus"></i> <span id="univNoticePrimaryBtnText">Attach Concept PDF Now</span>
                </button>
            </div>

            <!-- Official Seal Footer -->
            <div class="logout-modal-footer-note" style="margin-top: 1.25rem; font-size: 0.74rem; color: #94A3B8; display: flex; align-items: center; justify-content: center; gap: 0.4rem;">
                <i class="fa-solid fa-shield-halved" style="color: #047857;"></i> Secured CEIT Academic Portal &bull; CSU Cabadbaran
            </div>
        </div>
    </div>
</div>

<style>
@keyframes univModalPop {
    0% { opacity: 0; transform: scale(0.93) translateY(12px); }
    100% { opacity: 1; transform: scale(1) translateY(0); }
}
</style>

<script>
    function openCancelAdviserModal(adviserName, reqId, returnTab) {
        returnTab = returnTab || 'submission';
        const modal = document.getElementById('cancelAdviserModal');
        const nameEl = document.getElementById('cancelUnivModalAdviserName');
        const boldEl = document.getElementById('cancelUnivModalAdviserBold');
        const btn = document.getElementById('cancelUnivModalConfirmBtn');
        
        if (nameEl) nameEl.textContent = adviserName;
        if (boldEl) boldEl.textContent = adviserName;
        if (btn) btn.href = 'action.php?cancel_request=1&req_id=' + reqId + '&return_tab=' + returnTab;
        
        if (modal) {
            modal.style.display = 'flex';
        }
    }

    function closeCancelAdviserModal() {
        const modal = document.getElementById('cancelAdviserModal');
        if (modal) {
            modal.style.display = 'none';
        }
    }

    // Official University Academic Notice Modal Controllers
    window.showUniversityNotice = function(options) {
        options = options || {};
        const modal = document.getElementById('universityNoticeModal');
        if (!modal) {
            if (options.message) alert((options.title || 'Academic Notice') + "\n\n" + options.message.replace(/<[^>]*>?/gm, ''));
            return;
        }

        const headingEl = document.getElementById('univNoticeHeading');
        const pillIconEl = document.getElementById('univNoticePillIcon');
        const pillTextEl = document.getElementById('univNoticePillText');
        const pillEl = document.getElementById('univNoticePill');
        const titleEl = document.getElementById('univNoticeTitle');
        const descEl = document.getElementById('univNoticeDesc');
        const hintEl = document.getElementById('univNoticeHint');
        const hintTextEl = document.getElementById('univNoticeHintText');
        const primaryBtn = document.getElementById('univNoticePrimaryBtn');
        const primaryBtnText = document.getElementById('univNoticePrimaryBtnText');
        const mainIconEl = document.getElementById('univNoticeMainIcon');
        const iconWrapperEl = document.getElementById('univNoticeIconWrapper');

        if (headingEl) headingEl.textContent = options.heading || 'Academic Requirement Notice';
        if (pillTextEl) pillTextEl.textContent = options.pillText || 'Required Academic Document';
        if (titleEl) titleEl.textContent = options.title || 'Capstone Concept PDF Required';
        if (descEl) descEl.innerHTML = options.message || 'In compliance with CEIT academic policy, you must attach your Capstone Concept in PDF format (.pdf) to proceed.';
        
        if (pillIconEl && options.pillIcon) {
            pillIconEl.className = options.pillIcon;
        }
        if (mainIconEl && options.icon) {
            mainIconEl.className = options.icon;
        }

        if (hintEl && hintTextEl) {
            if (options.hint) {
                hintEl.style.display = 'flex';
                hintTextEl.innerHTML = options.hint;
            } else {
                hintEl.style.display = 'none';
            }
        }

        if (primaryBtnText) {
            primaryBtnText.textContent = options.primaryBtnText || 'Attach Concept PDF Now';
        }

        window._univNoticeTargetFocusId = options.targetFocusId || 'gatekeeper_abstract_file';
        window._univNoticeCallback = (typeof options.onPrimary === 'function') ? options.onPrimary : null;

        modal.style.display = 'flex';
    };

    window.closeUniversityNoticeModal = function() {
        const modal = document.getElementById('universityNoticeModal');
        if (modal) modal.style.display = 'none';
    };

    window.onUnivNoticePrimaryClick = function() {
        closeUniversityNoticeModal();
        if (typeof window._univNoticeCallback === 'function') {
            window._univNoticeCallback();
            return;
        }
        const targetId = window._univNoticeTargetFocusId || 'gatekeeper_abstract_file';
        const targetEl = document.getElementById(targetId) || document.getElementById('ai_tab_abstract_file') || document.getElementById('gatekeeper_topic') || document.getElementById('ai_tab_topic');
        if (targetEl) {
            targetEl.focus();
            targetEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
            targetEl.style.transition = 'box-shadow 0.3s ease, border-color 0.3s ease';
            targetEl.style.boxShadow = '0 0 0 4px rgba(245, 158, 11, 0.45)';
            targetEl.style.borderColor = '#F59E0B';
            setTimeout(() => {
                targetEl.style.boxShadow = '';
                targetEl.style.borderColor = '';
            }, 2600);
        }
    };

    window.showUniversityAlert = function(title, message, targetFocusId) {
        window.showUniversityNotice({
            heading: 'Academic Requirement Notice',
            pillText: 'Official Academic Compliance',
            title: title || 'Capstone Concept PDF Required',
            message: message || 'In compliance with CEIT academic policy, you cannot proceed without uploading your official Capstone Concept in PDF format (.pdf).',
            hint: 'Please attach and save your official Capstone Concept in PDF format (.pdf) in the proposed title form to continue.',
            targetFocusId: targetFocusId || 'gatekeeper_abstract_file',
            primaryBtnText: 'Attach Concept PDF Now'
        });
    };

    window.addEventListener('click', function(e) {
        const logoutModal = document.getElementById('logoutModal');
        if (e.target === logoutModal) {
            closeLogoutModal();
        }
        const cancelModal = document.getElementById('cancelAdviserModal');
        if (e.target === cancelModal) {
            closeCancelAdviserModal();
        }
        const aiModal = document.getElementById('aiModal');
        if (e.target === aiModal) {
            closeAIModal();
        }
        const univNoticeModal = document.getElementById('universityNoticeModal');
        if (e.target === univNoticeModal) {
            closeUniversityNoticeModal();
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeLogoutModal();
            closeCancelAdviserModal();
            closeAIModal();
            closeUniversityNoticeModal();
        }
    });
</script>

</body>
</html>
