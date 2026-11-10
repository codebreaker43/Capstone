<?php
// Unified Shared Group Dashboard: Overview View
$is_in_group = !empty($my_group) && !empty($group_id);
$is_leader = $is_in_group && ((int)($my_group['leader_id'] ?? 0) === (int)$uid);

// 1. Fetch active request & assigned adviser for student or group
$group_req_sql = $is_in_group ? "(m.group_id = $group_id OR m.student_id IN ($group_student_ids_str))" : "m.student_id = $uid";
$active_req = $conn->query("
    SELECT m.*, u.name as adv_name, u.email as adv_email, ap.department as adv_dept, ap.expertise 
    FROM match_request m 
    JOIN user u ON m.adviser_id = u.id 
    LEFT JOIN adviser_profile ap ON u.id = ap.user_id 
    WHERE $group_req_sql AND m.status IN ('pending', 'accepted') 
    ORDER BY (m.status = 'accepted') DESC, m.id DESC 
    LIMIT 1
")->fetch_assoc();

// 2. Fetch document submissions count & latest submission for group
$doc_group_sql = $is_in_group ? "(ds.group_id = $group_id OR ds.student_id IN ($group_student_ids_str))" : "ds.student_id = $uid";
$total_docs = (int)($conn->query("SELECT COUNT(*) as c FROM document_submissions ds WHERE $doc_group_sql")->fetch_assoc()['c'] ?? 0);

$latest_doc = $conn->query("
    SELECT ds.*, 
           rev.name as reviewer_name, 
           stu.name as submitter_name, 
           sp.is_group_leader as submitter_is_leader 
    FROM document_submissions ds 
    LEFT JOIN user rev ON ds.reviewed_by = rev.id 
    LEFT JOIN user stu ON ds.student_id = stu.id 
    LEFT JOIN student_profile sp ON ds.student_id = sp.user_id 
    WHERE $doc_group_sql 
    ORDER BY ds.id DESC 
    LIMIT 1
")->fetch_assoc();

// 3. Fetch shared group feed / updates
$group_updates = [];
if ($is_in_group) {
    $upd_res = $conn->query("
        SELECT gu.*, u.role as user_sys_role 
        FROM capstone_group_updates gu 
        LEFT JOIN user u ON gu.user_id = u.id 
        WHERE gu.group_id = $group_id 
        ORDER BY gu.id DESC 
        LIMIT 20
    ");
    if ($upd_res) {
        while ($row = $upd_res->fetch_assoc()) {
            $group_updates[] = $row;
        }
    }
}

// 4. Determine authoritative capstone title and approval status
$shared_title = !empty($my_group['thesis_topic']) ? $my_group['thesis_topic'] : (!empty($profile['thesis_topic']) ? $profile['thesis_topic'] : '');
$shared_title_stat = $my_group['title_status'] ?? $profile['title_status'] ?? 'pending';
$shared_abstract_file = !empty($my_group['abstract_file']) ? $my_group['abstract_file'] : ($profile['abstract_file'] ?? '');
$shared_abstract_name = !empty($my_group['abstract_name']) ? $my_group['abstract_name'] : ($profile['abstract_name'] ?? '');
$shared_manuscript_file = !empty($profile['manuscript_file']) ? $profile['manuscript_file'] : '';
?>

<!-- UNIFIED BANNER -->
<div class="banner" style="background: linear-gradient(135deg, var(--csu-green-darker) 0%, var(--csu-green-dark) 60%, var(--csu-green) 100%); border-radius: 12px; padding: 1.25rem 1.6rem; color: white; margin-bottom: 1.4rem; box-shadow: var(--shadow-sm); position: relative; overflow: hidden;">
    <div style="position: absolute; right: -15px; bottom: -20px; opacity: 0.08; font-size: 6.5rem; pointer-events: none;">
        <i class="fa-solid fa-users-viewfinder"></i>
    </div>
    
    <div style="position: relative; z-index: 1;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
            <div>
                <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 0.4rem;">
                    <?php if($is_in_group): ?>
                        <span style="background: rgba(16, 185, 129, 0.25); color: #A7F3D0; border: 1px solid rgba(16, 185, 129, 0.45); padding: 0.25rem 0.75rem; border-radius: 999px; font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; display: inline-flex; align-items: center; gap: 0.4rem;">
                            <span style="width: 8px; height: 8px; border-radius: 50%; background: #34D399; box-shadow: 0 0 8px #34D399;"></span>
                            One Shared Group Dashboard
                        </span>
                        <span style="background: rgba(245, 158, 11, 0.22); color: #FCD34D; border: 1px solid rgba(245, 158, 11, 0.4); padding: 0.25rem 0.75rem; border-radius: 999px; font-size: 0.72rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.35rem;">
                            <i class="fa-solid fa-users"></i> <?php echo count($my_group_members); ?>/3 Members Synchronized
                        </span>
                    <?php else: ?>
                        <span style="background: rgba(239, 68, 68, 0.25); color: #FECACA; border: 1px solid rgba(239, 68, 68, 0.4); padding: 0.25rem 0.75rem; border-radius: 999px; font-size: 0.72rem; font-weight: 700;">
                            <i class="fa-solid fa-triangle-exclamation"></i> Solo Mode • Form a Capstone Group
                        </span>
                    <?php endif; ?>
                </div>

                <h1 style="font-size: 1.45rem; font-weight: 800; margin: 0 0 0.35rem 0; color: #FFFFFF; font-family: 'Outfit', sans-serif;">
                    Welcome back, <?php echo htmlspecialchars($name); ?>!
                </h1>
                
                <p style="margin: 0; color: #D1FAE5; font-size: 0.85rem; max-width: 780px; line-height: 1.45;">
                    <?php if($is_in_group): ?>
                        Capstone Team: <strong style="color: #FFFFFF; font-weight: 700;"><?php echo htmlspecialchars($my_group['group_name']); ?></strong>
                        • Role: <span style="background: rgba(255,255,255,0.18); padding: 2px 7px; border-radius: 4px; font-weight: 700;"><?php echo $is_leader ? '👑 Group Leader' : '👤 Team Member'; ?></span>
                        <?php if(!empty($profile['student_id'])): ?> • ID: <strong style="color: #FCD34D;"><?php echo htmlspecialchars($profile['student_id']); ?></strong><?php endif; ?>
                    <?php else: ?>
                        College of Engineering and Information Technology • Capstone Student Portal<?php if(!empty($profile['student_id'])): ?> • School ID: <strong style="color: #FCD34D;"><?php echo htmlspecialchars($profile['student_id']); ?></strong><?php endif; ?>
                    <?php endif; ?>
                </p>
            </div>

            <?php if($is_in_group): ?>
                <div style="background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.22); border-radius: 10px; padding: 0.65rem 1rem; text-align: right; backdrop-filter: blur(4px);">
                    <div style="font-size: 0.72rem; color: #A7F3D0; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700;">
                        <i class="fa-solid fa-arrows-rotate"></i> Synchronized Hub
                    </div>
                    <div style="font-size: 0.95rem; font-weight: 800; color: #FFFFFF; margin-top: 2px;">
                        All Teammates Connected
                    </div>
                    <small style="color: #D1FAE5; font-size: 0.72rem;">Updates and submissions shared live</small>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Check for pending Pre-Acceptance Adviser Consultation Questions
if ($active_req && $active_req['status'] === 'pending') {
    $ov_pre_cons = check_pre_acceptance_consultation_ready($conn, (int)$active_req['student_id'], (int)$active_req['adviser_id'], (int)($active_req['group_id'] ?? $group_id), $active_req);
    if ($ov_pre_cons['adviser_asked_questions'] && !$ov_pre_cons['student_answered_questions']):
?>
<div style="background: #FFFBEB; border: 2px solid #F59E0B; border-radius: 12px; padding: 1rem 1.25rem; margin-bottom: 1.35rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; box-shadow: var(--shadow-sm);">
    <div style="display: flex; align-items: flex-start; gap: 0.85rem; flex: 1;">
        <div style="width: 42px; height: 42px; border-radius: 50%; background: #FEF3C7; color: #D97706; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; border: 1.5px solid #F59E0B;">
            <i class="fa-solid fa-clipboard-question"></i>
        </div>
        <div>
            <div style="font-size: 0.92rem; font-weight: 800; color: #92400E; margin-bottom: 0.2rem;">
                Action Required: <?php echo htmlspecialchars($active_req['adv_name']); ?> Sent Consultation Questions Before Accepting Your Request!
            </div>
            <div style="font-size: 0.82rem; color: #78350F;">
                <strong>Questions:</strong> <?php echo htmlspecialchars(mb_strimwidth($ov_pre_cons['questions_text'], 0, 160, '...')); ?>
            </div>
        </div>
    </div>
    <div style="display: flex; gap: 0.5rem;">
        <a href="?tab=requests" class="btn-primary" style="background: #D97706; border-color: #B45309; color: #FFFFFF; font-weight: 800; padding: 0.5rem 1rem; text-decoration: none; font-size: 0.8rem;">
            <i class="fa-solid fa-reply"></i> Answer Adviser Questions Now
        </a>
        <a href="?tab=consultations" class="btn" style="background: #FFFFFF; border: 1px solid #F59E0B; color: #92400E; font-weight: 700; padding: 0.5rem 0.85rem; text-decoration: none; font-size: 0.8rem;">
            Open Consultation Portal
        </a>
    </div>
</div>
<?php
    endif;
}

// Check for active scheduled defense
$sched_chk_where = $is_in_group ? "(dm.student_id = $uid OR dm.group_id = $group_id)" : "dm.student_id = $uid";
$upcoming_sched_res = $conn->query("
    SELECT dm.*, chair.name as chair_name, adv.name as adviser_user_name
    FROM defense_meetings dm
    LEFT JOIN user chair ON dm.panel_chair_id = chair.id
    LEFT JOIN user adv ON dm.adviser_id = adv.id
    WHERE $sched_chk_where AND dm.status IN ('scheduled', 'in_progress')
    ORDER BY dm.meeting_date ASC, dm.start_time ASC
    LIMIT 1
");
$upcoming_sched = $upcoming_sched_res ? $upcoming_sched_res->fetch_assoc() : null;
?>

<?php if($upcoming_sched): 
    $u_date_obj = strtotime($upcoming_sched['meeting_date']);
    $u_formatted_date = date('l, F j, Y', $u_date_obj);
    $u_time_str = !empty($upcoming_sched['start_time']) ? date('g:i A', strtotime($upcoming_sched['start_time'])) . (!empty($upcoming_sched['end_time']) ? ' - ' . date('g:i A', strtotime($upcoming_sched['end_time'])) : '') : 'Time: TBA';
?>
    <div style="background: linear-gradient(135deg, #064E3B 0%, #047857 60%, #022C22 100%); border: 1.5px solid #10B981; border-radius: 12px; padding: 1.25rem 1.5rem; color: white; margin-bottom: 1.4rem; box-shadow: 0 4px 16px rgba(4, 120, 87, 0.35); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div style="display: flex; align-items: center; gap: 1.15rem;">
            <div style="width: 52px; height: 52px; border-radius: 12px; background: rgba(16, 185, 129, 0.25); border: 1px solid rgba(16, 185, 129, 0.5); display: flex; align-items: center; justify-content: center; font-size: 1.65rem; color: #A7F3D0; flex-shrink: 0;">
                <i class="fa-solid fa-calendar-check"></i>
            </div>
            <div>
                <?php
                    $u_cat = !empty($upcoming_sched['defense_category']) ? $upcoming_sched['defense_category'] : (function_exists('resolve_defense_category') ? resolve_defense_category($upcoming_sched['defense_stage']) : 'Final Defense');
                    $u_cc = !empty($upcoming_sched['course_code']) ? $upcoming_sched['course_code'] : (function_exists('resolve_course_code') ? resolve_course_code($upcoming_sched['defense_stage']) : 'IT 199');
                    $u_ay = !empty($upcoming_sched['academic_year']) ? $upcoming_sched['academic_year'] : (function_exists('calculate_academic_year') ? calculate_academic_year($upcoming_sched['meeting_date']) : '');
                    $is_u_prop = ($u_cat === 'Proposal Defense' || $u_cc === 'IT 198');
                ?>
                <div style="display: flex; align-items: center; gap: 0.45rem; margin-bottom: 0.35rem; flex-wrap: wrap;">
                    <span style="background: <?php echo $is_u_prop ? '#0284C7' : '#059669'; ?>; color: #FFFFFF; font-size: 0.72rem; font-weight: 800; padding: 0.15rem 0.6rem; border-radius: 999px; letter-spacing: 0.03em; border: 1px solid rgba(255,255,255,0.3);">
                        <i class="fa-solid fa-graduation-cap"></i> <?php echo htmlspecialchars($u_cc); ?>: <?php echo htmlspecialchars($u_cat); ?>
                    </span>
                    <span style="background: rgba(255,255,255,0.2); color: white; font-size: 0.72rem; font-weight: 700; padding: 0.15rem 0.55rem; border-radius: 999px;">
                        <?php echo htmlspecialchars($upcoming_sched['defense_stage']); ?>
                    </span>
                    <?php if(!empty($u_ay)): ?>
                        <span style="background: rgba(245, 158, 11, 0.25); color: #FDE68A; border: 1px solid rgba(245, 158, 11, 0.5); font-size: 0.72rem; font-weight: 800; padding: 0.15rem 0.55rem; border-radius: 999px;">
                            <i class="fa-solid fa-calendar-days"></i> A.Y. <?php echo htmlspecialchars($u_ay); ?>
                        </span>
                    <?php endif; ?>
                </div>
                <h3 style="margin: 0; font-size: 1.15rem; font-weight: 800; color: #FFFFFF;"><?php echo htmlspecialchars($upcoming_sched['title']); ?></h3>
                <div style="font-size: 0.85rem; color: #D1FAE5; margin-top: 0.3rem; display: flex; gap: 1rem; flex-wrap: wrap;">
                    <span><i class="fa-regular fa-calendar"></i> <strong><?php echo $u_formatted_date; ?></strong></span>
                    <span><i class="fa-regular fa-clock"></i> <strong><?php echo $u_time_str; ?></strong></span>
                    <span><i class="fa-solid fa-location-dot"></i> <strong><?php echo htmlspecialchars($upcoming_sched['venue'] ?? 'CEIT Defense Hall'); ?></strong></span>
                </div>
            </div>
        </div>
        <div style="display: flex; gap: 0.5rem; align-items: center;">
            <a href="?tab=schedule" style="display: inline-flex; align-items: center; gap: 0.45rem; padding: 0.6rem 1.15rem; border-radius: 8px; font-size: 0.88rem; font-weight: 800; background: #FFFFFF; color: #064E3B; text-decoration: none; box-shadow: 0 2px 6px rgba(0,0,0,0.15); transition: all 0.2s;">
                <i class="fa-solid fa-arrow-right"></i> View Full Details & Roster
            </a>
        </div>
    </div>
<?php endif; ?>

<!-- SHARED STATS GRID -->
<div class="stats-grid" style="margin-bottom: 1.5rem;">
    <div class="stat-card">
        <div class="stat-icon" style="background: #ECFDF5; color: #059669;"><i class="fa-solid fa-file-circle-check"></i></div>
        <div class="stat-value"><?php echo $total_docs ?: (!empty($shared_title) ? '1' : '0'); ?></div>
        <div class="stat-label"><?php echo $is_in_group ? 'Team Papers Submitted' : 'Papers Submitted'; ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: #EFF6FF; color: #2563EB;"><i class="fa-solid fa-users"></i></div>
        <div class="stat-value"><?php echo $panel_count; ?></div>
        <div class="stat-label">Panelists Assigned</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: #FEF3C7; color: #D97706;"><i class="fa-solid fa-chart-line"></i></div>
        <div class="stat-value"><?php echo $progress; ?>%</div>
        <div class="stat-label">Milestone Progress</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: #F0FDF4; color: #16A34A;"><i class="fa-solid fa-user-check"></i></div>
        <div class="stat-value"><?php echo $adviser_assigned ? '1' : ($req_count > 0 ? 'Pending' : 'None'); ?></div>
        <div class="stat-label">Adviser Status</div>
    </div>
</div>

<!-- ACTIVE CAPSTONE TOPIC & ADVISER STATUS (SHARED ACROSS GROUP) -->
<?php if(!empty($shared_title)): ?>
    <div class="card" style="border-left: 4px solid <?php echo ($shared_title_stat == 'approved') ? '#059669' : '#F59E0B'; ?>; margin-bottom: 1.5rem;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
            <div class="card-header-left">
                <i class="fa-solid fa-book-bookmark" style="color: <?php echo ($shared_title_stat == 'approved') ? '#059669' : '#F59E0B'; ?>;"></i>
                <h2>Shared Capstone Topic & Title Status</h2>
            </div>
            <div>
                <?php if($shared_title_stat == 'approved'): ?>
                    <span class="badge badge-success" style="font-size: 0.78rem; padding: 0.3rem 0.75rem;">
                        <i class="fa-solid fa-circle-check"></i> Title Officially Approved
                    </span>
                <?php elseif($shared_title_stat == 'rejected'): ?>
                    <span class="badge badge-danger" style="font-size: 0.78rem; padding: 0.3rem 0.75rem;">
                        <i class="fa-solid fa-xmark"></i> Title Needs Modification
                    </span>
                <?php else: ?>
                    <span class="badge badge-warning" style="font-size: 0.78rem; padding: 0.3rem 0.75rem;">
                        <i class="fa-regular fa-clock"></i> Title Awaiting Adviser Approval
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <h3 style="margin: 0 0 0.75rem 0; color: #0F172A; font-size: 1.15rem; font-weight: 700; line-height: 1.35;">
                <?php echo htmlspecialchars($shared_title); ?>
            </h3>
            
            <div style="display: flex; gap: 1.5rem; flex-wrap: wrap; margin-top: 1rem; padding-top: 0.95rem; border-top: 1px dashed #E2E8F0; font-size: 0.85rem; align-items: center;">
                <div>
                    <span style="color: #64748B;"><i class="fa-solid fa-user-tie"></i> Assigned Adviser:</span>
                    <?php if($active_req && $active_req['status'] == 'accepted'): ?>
                        <strong style="color: #065F46; margin-left: 0.25rem;"><i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($active_req['adv_name']); ?></strong>
                        <span style="color: #64748B; font-size: 0.78rem;">(<?php echo htmlspecialchars($active_req['adv_email']); ?>)</span>
                    <?php elseif($active_req && $active_req['status'] == 'pending'): ?>
                        <strong style="color: #D97706; margin-left: 0.25rem;"><i class="fa-regular fa-clock"></i> Request Sent: <?php echo htmlspecialchars($active_req['adv_name']); ?></strong>
                    <?php else: ?>
                        <span style="color: #94A3B8; margin-left: 0.25rem;">No Adviser Selected</span>
                        <a href="?tab=ai" style="color: #0284C7; font-weight: 600; text-decoration: underline; margin-left: 0.4rem;">Select Adviser &rarr;</a>
                    <?php endif; ?>
                </div>

                <?php if(!empty($shared_abstract_file) && file_exists($shared_abstract_file)): ?>
                    <div>
                        <span style="color: #64748B;"><i class="fa-solid fa-file-pdf" style="color: #DC2626;"></i> Concept PDF:</span>
                        <a href="<?php echo htmlspecialchars($shared_abstract_file); ?>" target="_blank" style="color: #DC2626; font-weight: 700; text-decoration: underline; margin-left: 0.25rem;">
                            <?php echo htmlspecialchars(!empty($shared_abstract_name) ? $shared_abstract_name : 'View Concept PDF'); ?>
                        </a>
                    </div>
                <?php endif; ?>

                <?php 
                $is_cloud_manuscript = !empty($shared_manuscript_file) && (strpos($shared_manuscript_file, 'http://') === 0 || strpos($shared_manuscript_file, 'https://') === 0);
                if(!empty($shared_manuscript_file) && (file_exists($shared_manuscript_file) || $is_cloud_manuscript)): 
                ?>
                    <div>
                        <span style="color: #64748B;"><i class="fa-solid fa-file-word" style="color: #2563EB;"></i> Latest Manuscript:</span>
                        <a href="<?php echo htmlspecialchars($shared_manuscript_file); ?>" target="_blank" rel="noopener noreferrer" style="color: #0284C7; font-weight: 600; text-decoration: underline; margin-left: 0.25rem;">
                            <?php echo $is_cloud_manuscript ? 'Open Online Document (Google Docs / Word)' : 'View Document'; ?>
                        </a>
                    </div>
                <?php endif; ?>

                <div style="margin-left: auto; display: flex; gap: 0.4rem; flex-wrap: wrap;">
                    <a href="?tab=submission" class="btn" style="padding: 0.3rem 0.75rem; font-size: 0.78rem; background: #F1F5F9; color: #1E293B; border: 1px solid #CBD5E1; border-radius: 6px; text-decoration: none; font-weight: 600;">
                        <i class="fa-solid fa-pen-ruler"></i> Concept Paper Hub
                    </a>
                    <a href="?tab=manuscript" class="btn" style="padding: 0.3rem 0.75rem; font-size: 0.78rem; background: #059669; color: #FFFFFF; border-radius: 6px; text-decoration: none; font-weight: 700;">
                        <i class="fa-solid fa-file-pdf"></i> Manuscript (Ch 1 - Refs) &rarr;
                    </a>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
// IT-199 Requirements Checklist statistics for Overview
$ov_chk_items = get_student_checklist_items($conn, $uid, $group_id);
$ov_chk_stats = get_checklist_statistics($ov_chk_items);
?>
<!-- IT-199 REQUIREMENTS CHECKLIST HIGHLIGHT CARD -->
<div class="card" style="border-left: 4px solid var(--csu-gold); margin-bottom: 1.5rem;">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
        <div class="card-header-left" style="display: flex; align-items: center; gap: 0.5rem;">
            <i class="fa-solid fa-list-check" style="color: var(--csu-gold); font-size: 1.15rem;"></i>
            <h2 style="margin: 0; font-size: 1.05rem;">IT-199 Requirements Checklist Status</h2>
        </div>
        <div style="display: flex; gap: 0.4rem; align-items: center;">
            <span class="badge" style="background: <?php echo $ov_chk_stats['incomplete'] > 0 ? '#FEE2E2' : '#D1FAE5'; ?>; color: <?php echo $ov_chk_stats['incomplete'] > 0 ? '#991B1B' : '#065F46'; ?>; font-size: 0.78rem; font-weight: 700; border: 1px solid <?php echo $ov_chk_stats['incomplete'] > 0 ? '#FECACA' : '#A7F3D0'; ?>;">
                <?php echo $ov_chk_stats['percentage']; ?>% Completed
            </span>
            <a href="export_checklist.php" class="btn" style="padding: 0.25rem 0.6rem; font-size: 0.75rem; background: #FFFBEB; border: 1px solid #FCD34D; color: #92400E; font-weight: 700; text-decoration: none; border-radius: 6px;">
                <i class="fa-solid fa-file-word"></i> Word (.doc)
            </a>
        </div>
    </div>
    <div class="card-body">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 0.85rem;">
            <div>
                <p style="margin: 0 0 0.35rem 0; color: #334155; font-size: 0.86rem; line-height: 1.4;">
                    <?php if($ov_chk_stats['incomplete'] > 0): ?>
                        You have <strong><?php echo $ov_chk_stats['incomplete']; ?> incomplete requirements</strong> remaining. Missing evidence attachments will result in an <strong>"Incomplete" (INC)</strong> grade.
                    <?php else: ?>
                        All active capstone requirements have attached evidence and are verified complete!
                    <?php endif; ?>
                </p>
                <div style="display: flex; gap: 0.8rem; font-size: 0.78rem; color: #64748B; flex-wrap: wrap;">
                    <span><strong style="color: #059669;">✔ <?php echo $ov_chk_stats['completed']; ?></strong> Completed</span>
                    <span><strong style="color: #DC2626;">✘ <?php echo $ov_chk_stats['incomplete']; ?></strong> Incomplete</span>
                    <span><strong style="color: #2563EB;">🔗 <?php echo $ov_chk_stats['with_link']; ?></strong> Attached Links</span>
                </div>
            </div>
            <div>
                <a href="?tab=checklist" class="btn-primary" style="padding: 0.45rem 0.95rem; font-size: 0.82rem; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem; white-space: nowrap;">
                    <span>Manage Checklist</span> <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>
        </div>

        <div style="background: #E2E8F0; height: 9px; border-radius: 999px; overflow: hidden;">
            <div style="width: <?php echo $ov_chk_stats['percentage']; ?>%; background: linear-gradient(90deg, #059669 0%, #10B981 100%); height: 100%; border-radius: 999px; transition: width 0.5s;"></div>
        </div>
    </div>
</div>

<!-- LATEST DOCUMENT REVIEW STATUS (SHARED TEAM VISIBILITY) -->
<?php if($latest_doc): 
    $ld_stat = $latest_doc['status'];
    $ld_badgeBg = '#FEF3C7'; $ld_badgeColor = '#B45309'; $ld_badgeBorder = '#FDE68A'; $ld_label = 'Pending Review';
    if($ld_stat == 'approved') {
        $ld_badgeBg = '#D1FAE5'; $ld_badgeColor = '#047857'; $ld_badgeBorder = '#A7F3D0'; $ld_label = 'Approved';
    } elseif($ld_stat == 'needs_revision') {
        $ld_badgeBg = '#FEE2E2'; $ld_badgeColor = '#DC2626'; $ld_badgeBorder = '#FECACA'; $ld_label = 'Needs Revision';
    } elseif($ld_stat == 'under_review') {
        $ld_badgeBg = '#DBEAFE'; $ld_badgeColor = '#1D4ED8'; $ld_badgeBorder = '#BFDBFE'; $ld_label = 'Under Review';
    }
?>
    <div class="card" style="border-left: 4px solid <?php echo ($ld_stat == 'approved') ? '#047857' : (($ld_stat == 'needs_revision') ? '#DC2626' : '#F59E0B'); ?>; margin-bottom: 1.5rem;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
            <div class="card-header-left">
                <i class="fa-solid fa-file-pen" style="color: var(--csu-green);"></i>
                <h2>Latest Document Review Status</h2>
            </div>
            <div style="display: flex; gap: 0.5rem; align-items: center;">
                <span style="background: <?php echo $ld_badgeBg; ?>; color: <?php echo $ld_badgeColor; ?>; border: 1px solid <?php echo $ld_badgeBorder; ?>; font-size: 0.78rem; font-weight: 700; padding: 0.25rem 0.65rem; border-radius: 999px;">
                    <?php echo $ld_label; ?>
                </span>
                <a href="?tab=submission" class="btn" style="padding: 0.25rem 0.65rem; font-size: 0.76rem; background: #F1F5F9; color: #1E293B; border: 1px solid #CBD5E1; border-radius: 6px; text-decoration: none; font-weight: 600;">
                    View All Submissions &rarr;
                </a>
            </div>
        </div>
        <div class="card-body">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 0.75rem;">
                <div>
                    <div style="display: flex; align-items: center; gap: 0.45rem; flex-wrap: wrap; margin-bottom: 0.35rem;">
                        <span style="background: #F1F5F9; color: #334155; font-size: 0.72rem; font-weight: 700; padding: 0.15rem 0.45rem; border-radius: 4px; border: 1px solid #CBD5E1;">
                            <?php echo htmlspecialchars($latest_doc['doc_type']); ?>
                        </span>
                        <span style="background: #EFF6FF; color: #1E40AF; font-size: 0.72rem; font-weight: 700; padding: 0.15rem 0.45rem; border-radius: 4px;">
                            <?php echo htmlspecialchars($latest_doc['version']); ?>
                        </span>
                        
                        <!-- AUTHOR BADGE -->
                        <?php if((int)$latest_doc['student_id'] === (int)$uid): ?>
                            <span style="background: #ECFDF5; color: #047857; font-size: 0.72rem; font-weight: 700; padding: 0.15rem 0.5rem; border-radius: 4px; border: 1px solid #A7F3D0;">
                                <i class="fa-solid fa-user-check"></i> Uploaded by You
                            </span>
                        <?php else: ?>
                            <span style="background: #EFF6FF; color: #1E40AF; font-size: 0.72rem; font-weight: 700; padding: 0.15rem 0.5rem; border-radius: 4px; border: 1px solid #BFDBFE;">
                                <i class="fa-solid fa-users"></i> Uploaded by <?php echo htmlspecialchars($latest_doc['submitter_name'] ?? 'Teammate'); ?><?php echo !empty($latest_doc['submitter_is_leader']) ? ' (Leader)' : ''; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <h3 style="margin: 0; color: #0F172A; font-size: 1.05rem; font-weight: 700;">
                        <?php echo htmlspecialchars($latest_doc['title']); ?>
                    </h3>
                </div>

                <?php if(!empty($latest_doc['file_path']) && file_exists($latest_doc['file_path'])): ?>
                    <a href="<?php echo htmlspecialchars($latest_doc['file_path']); ?>" target="_blank" download class="btn" style="padding: 0.35rem 0.75rem; font-size: 0.8rem; background: #F0FDF4; color: #047857; border: 1px solid #A7F3D0; border-radius: 6px; text-decoration: none; font-weight: 600;">
                        <i class="fa-solid fa-download"></i> Download Submission
                    </a>
                <?php endif; ?>
            </div>

            <?php if(!empty($latest_doc['feedback'])): ?>
                <div style="background: <?php echo ($ld_stat == 'approved') ? '#F0FDF4' : '#FFFBEB'; ?>; border: 1px solid <?php echo ($ld_stat == 'approved') ? '#BBF7D0' : '#FDE68A'; ?>; border-radius: 8px; padding: 0.85rem 1rem; margin-top: 0.5rem; font-size: 0.85rem; color: #1E293B;">
                    <strong><i class="fa-solid fa-comment-dots"></i> Adviser Feedback (<?php echo htmlspecialchars($latest_doc['reviewer_name'] ?? 'Adviser'); ?>):</strong>
                    <p style="margin: 0.35rem 0 0 0; line-height: 1.45;"><?php echo nl2br(htmlspecialchars($latest_doc['feedback'])); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php
// Fetch recent panel comments & voice recordings for Student Overview
$ov_prc_where = $is_in_group ? "(prc.student_id IN ($group_student_ids_str) OR prc.group_id = $group_id)" : "prc.student_id = $uid";
$ov_panel_comments = [];
$ov_prc_q = $conn->query("
    SELECT prc.*, u.name as author_name
    FROM panel_research_comments prc
    JOIN user u ON prc.panel_id = u.id
    WHERE $ov_prc_where
    ORDER BY prc.id DESC
    LIMIT 3
");
if ($ov_prc_q) {
    while ($rc_row = $ov_prc_q->fetch_assoc()) {
        $ov_panel_comments[] = $rc_row;
    }
}
?>
<?php if(!empty($ov_panel_comments)): ?>
<div class="card" style="border-left: 4px solid #047857; margin-bottom: 1.5rem;">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
        <div class="card-header-left">
            <i class="fa-solid fa-microphone-lines" style="color: #047857;"></i>
            <h2>Recent Panel Comments & Voice Recordings</h2>
        </div>
        <a href="?tab=panelists" class="btn" style="padding: 0.25rem 0.7rem; font-size: 0.76rem; background: #ECFDF5; color: #047857; border: 1px solid #A7F3D0; border-radius: 6px; text-decoration: none; font-weight: 700;">
            Open All Panel Comments & Reply &rarr;
        </a>
    </div>
    <div class="card-body" style="display: flex; flex-direction: column; gap: 0.75rem;">
        <?php foreach($ov_panel_comments as $opc): ?>
            <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-left: 3.5px solid <?php echo $opc['feedback_type'] === 'Required Revision' ? '#DC2626' : '#059669'; ?>; border-radius: 8px; padding: 0.75rem 1rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.4rem; margin-bottom: 0.35rem;">
                    <div style="display: flex; align-items: center; gap: 0.45rem; flex-wrap: wrap;">
                        <strong style="color: #0F172A; font-size: 0.86rem;"><i class="fa-solid fa-user-check" style="color: #047857;"></i> <?php echo htmlspecialchars($opc['author_name']); ?></strong>
                        <span style="font-size: 0.72rem; padding: 2px 7px; border-radius: 999px; background: #F1F5F9; color: #475569; font-weight: 600;">
                            <?php echo htmlspecialchars($opc['category']); ?> • <?php echo htmlspecialchars($opc['feedback_type']); ?>
                        </span>
                        <?php if(!empty($opc['audio_file'])): ?>
                            <span style="font-size: 0.7rem; padding: 2px 7px; border-radius: 999px; background: #FEF2F2; color: #DC2626; font-weight: 800; border: 1px solid #FECACA;">
                                <i class="fa-solid fa-microphone-lines"></i> Voice Recorded
                            </span>
                        <?php endif; ?>
                    </div>
                    <span style="font-size: 0.72rem; color: #94A3B8;"><?php echo date("M d, Y h:i A", strtotime($opc['created_at'])); ?></span>
                </div>

                <?php if(!empty($opc['audio_file'])): ?>
                <div style="background: #ECFDF5; border: 1px solid #A7F3D0; border-radius: 6px; padding: 0.5rem 0.7rem; margin: 0.35rem 0;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem;">
                        <span style="font-size: 0.75rem; font-weight: 800; color: #065F46;">
                            <i class="fa-solid fa-volume-high"></i> Play Recorded Voice Comment
                            <?php if(!empty($opc['audio_duration'])): ?> (<?php echo gmdate("i:s", (int)$opc['audio_duration']); ?>)<?php endif; ?>
                        </span>
                        <a href="<?php echo htmlspecialchars($opc['audio_file']); ?>" download style="font-size: 0.72rem; color: #047857; font-weight: 700; text-decoration: none;">
                            <i class="fa-solid fa-download"></i> Download
                        </a>
                    </div>
                    <audio controls preload="metadata" src="<?php echo htmlspecialchars($opc['audio_file']); ?>" style="width: 100%; height: 34px;"></audio>
                </div>
                <?php endif; ?>

                <?php if(!empty($opc['comment_text']) && $opc['comment_text'] !== '[Voice Recorded Comment]'): ?>
                <div style="font-size: 0.84rem; color: #334155; line-height: 1.45;">
                    <?php echo nl2br(htmlspecialchars($opc['comment_text'])); ?>
                </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- CAPSTONE GROUP ROSTER CARD (3 MEMBERS) -->
<div class="card" style="border-left: 4px solid var(--csu-gold); margin-bottom: 1.5rem;">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <div class="card-header-left">
            <i class="fa-solid fa-users" style="color: var(--csu-gold);"></i>
            <h2>Capstone Group Roster</h2>
        </div>
        <?php if($my_group): ?>
            <?php if(count($my_group_members) >= 3): ?>
                <span class="badge badge-success"><i class="fa-solid fa-circle-check"></i> Complete Team (3/3)</span>
            <?php else: ?>
                <span class="badge badge-warning"><i class="fa-solid fa-clock"></i> <?php echo count($my_group_members); ?>/3 Members</span>
            <?php endif; ?>
        <?php else: ?>
            <span class="badge badge-danger"><i class="fa-solid fa-triangle-exclamation"></i> Group Not Formed</span>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if($my_group): ?>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.85rem; flex-wrap: wrap; gap: 0.5rem;">
                <div>
                    <strong style="color: #0F172A; font-size: 1rem;"><?php echo htmlspecialchars($my_group['group_name']); ?></strong>
                    <span style="color: #64748B; font-size: 0.82rem; margin-left: 0.5rem;">(<?php echo htmlspecialchars($my_group['department'] ?? 'CEIT'); ?>)</span>
                </div>
                <a href="?tab=group" style="font-size: 0.82rem; color: #0284C7; font-weight: 600; text-decoration: none;">
                    <i class="fa-solid fa-gear"></i> Manage Group Roster &rarr;
                </a>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 0.75rem;">
                <?php 
                for($i = 0; $i < 3; $i++): 
                    $gm = $my_group_members[$i] ?? null;
                ?>
                    <?php if($gm): ?>
                        <div style="background: #F8FAFC; border: 1px solid <?php echo (!empty($gm['student_id']) && (int)$gm['student_id'] === (int)$uid) ? '#86EFAC' : '#E2E8F0'; ?>; border-radius: 8px; padding: 0.75rem; display: flex; align-items: center; gap: 0.65rem; <?php echo (!empty($gm['student_id']) && (int)$gm['student_id'] === (int)$uid) ? 'background:#F0FDF4;' : ''; ?>">
                            <div style="width: 36px; height: 36px; border-radius: 50%; background: <?php echo $gm['is_leader'] ? '#FEF3C7' : '#E0E7FF'; ?>; color: <?php echo $gm['is_leader'] ? '#B45309' : '#4338CA'; ?>; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem; flex-shrink: 0;">
                                <?php echo strtoupper(substr($gm['name'], 0, 1)); ?>
                            </div>
                            <div style="overflow: hidden; flex: 1;">
                                <div style="font-size: 0.85rem; font-weight: 700; color: #0F172A; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    <?php echo htmlspecialchars($gm['name']); ?>
                                    <?php if(!empty($gm['student_id']) && (int)$gm['student_id'] === (int)$uid): ?>
                                        <span style="font-size: 0.72rem; color: #059669; font-weight: 700;">(You)</span>
                                    <?php endif; ?>
                                </div>
                                <div style="font-size: 0.72rem; color: <?php echo $gm['is_leader'] ? '#B45309' : '#047857'; ?>; font-weight: 600;">
                                    <?php echo $gm['is_leader'] ? '👑 Group Leader' : '👤 Member '.($i + 1); ?>
                                    <?php if(!empty($gm['student_no'])): ?>
                                        • <span style="color: #64748B; font-weight: normal;"><?php echo htmlspecialchars($gm['student_no']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div style="background: #FAFAFA; border: 1px dashed #CBD5E1; border-radius: 8px; padding: 0.75rem; display: flex; align-items: center; gap: 0.65rem; color: #94A3B8;">
                            <div style="width: 36px; height: 36px; border-radius: 50%; background: #F1F5F9; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; flex-shrink: 0;">
                                <i class="fa-solid fa-plus"></i>
                            </div>
                            <div>
                                <div style="font-size: 0.82rem; font-weight: 600; color: #64748B;">Slot <?php echo $i + 1; ?>: Vacant</div>
                                <a href="?tab=group" style="font-size: 0.72rem; color: #0284C7; text-decoration: underline;">Add Member</a>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
        <?php else: ?>
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <strong style="color: #0F172A; font-size: 0.95rem;">You are not in a Capstone Group yet.</strong>
                    <p style="margin: 0.25rem 0 0 0; color: #64748B; font-size: 0.82rem;">Every CEIT capstone project requires a registered group of three (3) students.</p>
                </div>
                <a href="?tab=group">
                    <button class="btn-primary" style="padding: 0.5rem 1rem; font-size: 0.85rem;">
                        <i class="fa-solid fa-users"></i> Form Capstone Group
                    </button>
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- SHARED GROUP ACTIVITY FEED & TEAM UPDATES BOARD -->
<?php if($is_in_group): ?>
    <div class="card" style="margin-bottom: 1.5rem; border-top: 4px solid var(--primary-blue);">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.6rem;">
            <div class="card-header-left" style="display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-tower-broadcast" style="color: var(--primary-blue);"></i>
                <h2 style="margin: 0;">Live Group Activity & Updates Feed</h2>
                <span style="background: #EFF6FF; color: #1D4ED8; font-size: 0.7rem; font-weight: 800; padding: 2px 8px; border-radius: 999px; border: 1px solid #BFDBFE;">Shared with all 3 members</span>
            </div>
            
            <button type="button" onclick="togglePostUpdateBox()" class="btn-primary" style="padding: 0.4rem 0.85rem; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 0.35rem; border-radius: 7px;">
                <i class="fa-solid fa-plus-circle"></i> Post Team Note / Update
            </button>
        </div>

        <div class="card-body">
            
            <!-- QUICK POST UPDATE BOX (COLLAPSIBLE) -->
            <div id="postUpdateBox" style="display: none; background: #F8FAFC; border: 1px solid #CBD5E1; border-radius: 10px; padding: 1.15rem; margin-bottom: 1.25rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                    <strong style="color: #0F172A; font-size: 0.92rem;"><i class="fa-solid fa-paper-plane" style="color: var(--primary-blue);"></i> Share Update or Note with Your Group</strong>
                    <button type="button" onclick="togglePostUpdateBox()" style="background: none; border: none; font-size: 1.1rem; color: #64748B; cursor: pointer;">&times;</button>
                </div>
                
                <form method="POST" action="action.php">
                    <input type="hidden" name="post_group_update" value="1">
                    <input type="hidden" name="group_id" value="<?php echo $group_id; ?>">

                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 0.75rem; margin-bottom: 0.75rem;">
                        <input type="text" name="title" placeholder="Update Title / Topic (optional, e.g., 'Chapter 3 revisions completed')" style="padding: 0.55rem 0.75rem; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 0.82rem; font-family: 'Inter', sans-serif;">
                        
                        <select name="update_type" style="padding: 0.55rem 0.75rem; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 0.82rem; font-family: 'Inter', sans-serif; background: white;">
                            <option value="note">📝 General Note</option>
                            <option value="task">✅ Task Completed</option>
                            <option value="meeting">📅 Meeting / Schedule</option>
                            <option value="urgent">🚨 Urgent Reminder</option>
                        </select>
                    </div>

                    <div style="margin-bottom: 0.75rem;">
                        <textarea name="message" required rows="3" placeholder="Type your update here for all group members to see (e.g. 'I updated the introduction draft and attached Turnitin similarity report in the Submissions tab. Please review!')..." style="width: 100%; padding: 0.65rem 0.85rem; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 0.85rem; font-family: 'Inter', sans-serif; resize: vertical; box-sizing: border-box;"></textarea>
                    </div>

                    <div style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                        <button type="button" onclick="togglePostUpdateBox()" class="btn" style="padding: 0.45rem 0.85rem; font-size: 0.8rem; background: white; border: 1px solid #CBD5E1; border-radius: 6px; cursor: pointer;">Cancel</button>
                        <button type="submit" class="btn-primary" style="padding: 0.45rem 1.15rem; font-size: 0.82rem; border-radius: 6px; background: var(--csu-green);">
                            <i class="fa-solid fa-share"></i> Post to Shared Dashboard
                        </button>
                    </div>
                </form>
            </div>

            <!-- FEED LIST -->
            <?php if(empty($group_updates)): ?>
                <div style="text-align: center; padding: 2.5rem 1rem; color: #94A3B8;">
                    <i class="fa-solid fa-comments fa-2x" style="margin-bottom: 0.65rem; color: #CBD5E1; display: block;"></i>
                    <p style="margin: 0; font-size: 0.9rem; color: #64748B; font-weight: 600;">No team updates posted yet.</p>
                    <small style="color: #94A3B8; font-size: 0.78rem;">Whenever a group member uploads a document, receives adviser feedback, or posts a note, it will appear here in real time for everyone!</small>
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 0.85rem;">
                    <?php foreach($group_updates as $upd): 
                        $utype = $upd['update_type'];
                        $iconClass = 'fa-comment-dots';
                        $iconColor = '#3B82F6';
                        $iconBg = '#EFF6FF';
                        $typeLabel = 'Team Note';

                        if ($utype == 'document_upload') {
                            $iconClass = 'fa-cloud-arrow-up';
                            $iconColor = '#059669';
                            $iconBg = '#ECFDF5';
                            $typeLabel = 'Document Submitted';
                        } elseif ($utype == 'adviser_feedback') {
                            $iconClass = 'fa-clipboard-check';
                            $iconColor = '#7C3AED';
                            $iconBg = '#F5F3FF';
                            $typeLabel = 'Adviser Review';
                        } elseif ($utype == 'milestone') {
                            $iconClass = 'fa-flag-checkered';
                            $iconColor = '#D97706';
                            $iconBg = '#FEF3C7';
                            $typeLabel = 'Milestone';
                        } elseif ($utype == 'task') {
                            $iconClass = 'fa-circle-check';
                            $iconColor = '#10B981';
                            $iconBg = '#ECFDF5';
                            $typeLabel = 'Task Done';
                        } elseif ($utype == 'urgent') {
                            $iconClass = 'fa-triangle-exclamation';
                            $iconColor = '#DC2626';
                            $iconBg = '#FEF2F2';
                            $typeLabel = 'Urgent';
                        } elseif ($utype == 'meeting') {
                            $iconClass = 'fa-calendar-days';
                            $iconColor = '#2563EB';
                            $iconBg = '#EFF6FF';
                            $typeLabel = 'Meeting / Schedule';
                        }

                        $is_my_update = ((int)$upd['user_id'] === (int)$uid);
                        $can_delete = $is_my_update || $is_leader;
                        $timeAgo = date('M d, Y • h:i A', strtotime($upd['created_at']));
                    ?>
                        <div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 10px; padding: 0.95rem 1.15rem; display: flex; gap: 0.95rem; align-items: flex-start; transition: transform 0.15s ease;">
                            <div style="width: 38px; height: 38px; border-radius: 10px; background: <?php echo $iconBg; ?>; color: <?php echo $iconColor; ?>; display: flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0;">
                                <i class="fa-solid <?php echo $iconClass; ?>"></i>
                            </div>

                            <div style="flex: 1; overflow: hidden;">
                                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.4rem; margin-bottom: 0.25rem;">
                                    <div style="display: flex; align-items: center; gap: 0.45rem; flex-wrap: wrap;">
                                        <strong style="color: #0F172A; font-size: 0.88rem;"><?php echo htmlspecialchars($upd['author_name']); ?></strong>
                                        <span style="font-size: 0.72rem; color: #64748B; background: #F1F5F9; padding: 1px 6px; border-radius: 4px; font-weight: 600;">
                                            <?php echo htmlspecialchars($upd['author_role'] ?? 'Member'); ?>
                                            <?php if($is_my_update): ?> (You)<?php endif; ?>
                                        </span>
                                        <span style="font-size: 0.72rem; color: <?php echo $iconColor; ?>; font-weight: 700; background: <?php echo $iconBg; ?>; padding: 1px 6px; border-radius: 4px;">
                                            <?php echo $typeLabel; ?>
                                        </span>
                                    </div>
                                    
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <span style="font-size: 0.74rem; color: #94A3B8;">
                                            <i class="fa-regular fa-clock"></i> <?php echo $timeAgo; ?>
                                        </span>
                                        <?php if($can_delete && $utype == 'note'): ?>
                                            <form method="POST" action="action.php" onsubmit="return confirm('Delete this update?');" style="margin: 0;">
                                                <input type="hidden" name="delete_group_update" value="1">
                                                <input type="hidden" name="update_id" value="<?php echo $upd['id']; ?>">
                                                <button type="submit" title="Delete note" style="background: none; border: none; color: #94A3B8; cursor: pointer; font-size: 0.8rem; padding: 2px 4px;">
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if(!empty($upd['title'])): ?>
                                    <h4 style="margin: 0 0 0.25rem 0; font-size: 0.9rem; color: #1E293B; font-weight: 700;">
                                        <?php echo htmlspecialchars($upd['title']); ?>
                                    </h4>
                                <?php endif; ?>

                                <p style="margin: 0; font-size: 0.84rem; color: #334155; line-height: 1.45; word-break: break-word;">
                                    <?php echo nl2br(htmlspecialchars($upd['message'])); ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    </div>
<?php endif; ?>

<!-- QUICK ACTIONS -->
<div class="card">
    <div class="card-header">
        <div class="card-header-left">
            <i class="fa-solid fa-bolt"></i>
            <h2>Quick Actions</h2>
        </div>
    </div>
    <div class="card-body">
        <div class="quick-actions">
            <a href="?tab=submission"><button class="btn-primary" style="width: 100%;"><i class="fa-solid fa-upload"></i> Submit / Edit Proposal</button></a>
            <a href="?tab=ai"><button class="btn-primary btn-warning" style="width: 100%;"><i class="fa-solid fa-wand-magic-sparkles"></i> AI Adviser Match</button></a>
            <a href="?tab=progress"><button class="btn-primary btn-success" style="width: 100%;"><i class="fa-solid fa-arrow-trend-up"></i> Track Milestone Progress</button></a>
            <a href="?tab=panelists"><button class="btn btn-outline" style="width: 100%; border-radius: 8px;"><i class="fa-solid fa-users"></i> View Panelists</button></a>
        </div>
    </div>
</div>

<script>
function togglePostUpdateBox() {
    const box = document.getElementById('postUpdateBox');
    if (box) {
        if (box.style.display === 'none' || box.style.display === '') {
            box.style.display = 'block';
            box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } else {
            box.style.display = 'none';
        }
    }
}
</script>
