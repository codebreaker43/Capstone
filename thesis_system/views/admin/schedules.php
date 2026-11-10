<?php
// DEFENSE SCHEDULE MANAGEMENT - ADMINISTRATOR PORTAL
// Allows Admin to create, assign, reschedule, and manage Oral & Final Defenses

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    exit('Access Denied');
}

// 1. Fetch all defense meetings with related student, adviser, and panel details
$schedules_query = $conn->query("
    SELECT dm.*, 
           u.name as student_name, u.email as student_email,
           COALESCE(NULLIF(dm.department, ''), NULLIF(sp.department, ''), cg.department, 'Information Technology') as department,
           sp.department as student_dept, sp.thesis_topic as student_topic, sp.student_id as student_no,
           cg.group_name, cg.leader_id,
           chair.name as panel_chair_name,
           adv.name as adviser_user_name,
           doc.name as mom_documenter_user_name,
           (SELECT COUNT(*) FROM defense_transcripts dt WHERE dt.meeting_id = dm.id) as transcript_count,
           (SELECT dmm.id FROM defense_meeting_minutes dmm WHERE dmm.meeting_id = dm.id LIMIT 1) as mom_id,
           (SELECT dmm.decision_verdict FROM defense_meeting_minutes dmm WHERE dmm.meeting_id = dm.id LIMIT 1) as mom_verdict,
           (SELECT dmm.recorded_by_name FROM defense_meeting_minutes dmm WHERE dmm.meeting_id = dm.id LIMIT 1) as mom_recorded_by,
           (SELECT dmm.status FROM defense_meeting_minutes dmm WHERE dmm.meeting_id = dm.id LIMIT 1) as mom_status,
           (SELECT dmm.grade_average FROM defense_meeting_minutes dmm WHERE dmm.meeting_id = dm.id LIMIT 1) as mom_grade
    FROM defense_meetings dm
    JOIN user u ON dm.student_id = u.id
    LEFT JOIN student_profile sp ON u.id = sp.user_id
    LEFT JOIN capstone_group cg ON dm.group_id = cg.id
    LEFT JOIN user chair ON dm.panel_chair_id = chair.id
    LEFT JOIN user adv ON dm.adviser_id = adv.id
    LEFT JOIN user doc ON dm.mom_documenter_id = doc.id
    ORDER BY dm.meeting_date DESC, dm.start_time DESC
");

$all_schedules = [];
$total_count = 0;
$upcoming_count = 0;
$inprogress_count = 0;
$completed_count = 0;
$proposal_count = 0;
$final_count = 0;

$course_counts = [
    'all' => 0,
    'BSIT' => 0,
    'BSCpE' => 0,
    'BSEE' => 0
];

$today_str = date('Y-m-d');

if ($schedules_query) {
    while ($row = $schedules_query->fetch_assoc()) {
        $sched_dept = !empty($row['department']) ? $row['department'] : (!empty($row['student_dept']) ? $row['student_dept'] : 'Information Technology');
        $sched_dept_norm = normalize_course_name($sched_dept);
        $sched_dept_short = get_course_short_code($sched_dept_norm);

        $row['dept_norm'] = $sched_dept_norm;
        $row['dept_code'] = $sched_dept_short;

        // Resolve Academic Year (A.Y.), Course Code (e.g. IT 198, CPE 198, EE 198), and Defense Category
        $row_ay = !empty($row['academic_year']) ? $row['academic_year'] : calculate_academic_year($row['meeting_date']);
        $row_cat = !empty($row['defense_category']) ? $row['defense_category'] : resolve_defense_category($row['defense_stage'], '');
        $row_cc = !empty($row['course_code']) ? $row['course_code'] : resolve_course_code($row['defense_stage'], '', $sched_dept_norm);

        $row['academic_year'] = $row_ay;
        $row['defense_category'] = $row_cat;
        $row['course_code'] = $row_cc;

        $all_schedules[] = $row;
        $total_count++;

        if (isset($course_counts[$sched_dept_short])) {
            $course_counts[$sched_dept_short]++;
        } else {
            $course_counts['BSIT']++;
        }

        $is_prop = ($row_cat === 'Proposal Defense' || strpos($row_cc, '198') !== false);
        if ($is_prop) {
            $proposal_count++;
        } else {
            $final_count++;
        }

        if ($row['status'] === 'in_progress') {
            $inprogress_count++;
        } elseif ($row['status'] === 'completed') {
            $completed_count++;
        } elseif ($row['meeting_date'] >= $today_str) {
            $upcoming_count++;
        }
    }
}

// Available Academic Years for filter and form dropdowns
$available_academic_years = get_available_academic_years($conn);
$curr_system_ay = calculate_academic_year(date('Y-m-d'));
if (!in_array($curr_system_ay, $available_academic_years)) {
    array_unshift($available_academic_years, $curr_system_ay);
}

// Partition schedules for separated category sections
$it198_schedules = array_values(array_filter($all_schedules, function($s) {
    return ($s['defense_category'] === 'Proposal Defense' || strpos($s['course_code'], '198') !== false);
}));
$it199_schedules = array_values(array_filter($all_schedules, function($s) {
    return !($s['defense_category'] === 'Proposal Defense' || strpos($s['course_code'], '198') !== false);
}));

// 2. Fetch list of eligible students / capstone groups for scheduling
$students_query = $conn->query("
    SELECT u.id as student_id, u.name as student_name, u.email,
           sp.department, sp.thesis_topic, sp.student_id as student_no, sp.group_id,
           cg.group_name,
           (SELECT mr.adviser_id FROM match_request mr WHERE mr.student_id = u.id AND mr.status = 'accepted' LIMIT 1) as matched_adv_id,
           (SELECT u_adv.name FROM match_request mr JOIN user u_adv ON mr.adviser_id = u_adv.id WHERE mr.student_id = u.id AND mr.status = 'accepted' LIMIT 1) as matched_adv_name
    FROM user u
    JOIN student_profile sp ON u.id = sp.user_id
    LEFT JOIN capstone_group cg ON sp.group_id = cg.id
    WHERE u.role = 'student' AND u.account_status = 'active'
    ORDER BY u.name ASC
");

$eligible_students = [];
$eligible_students_by_course = [
    'BSIT' => [],
    'BSCpE' => [],
    'BSEE' => []
];
$students_metadata = [];
if ($students_query) {
    while ($s = $students_query->fetch_assoc()) {
        $eligible_students[] = $s;
        
        // Also fetch any currently assigned panel IDs
        $assigned_panels = [];
        $p_res = $conn->query("SELECT panel_id FROM thesis_panel WHERE student_id = {$s['student_id']}");
        if ($p_res) {
            while ($pr = $p_res->fetch_assoc()) {
                $assigned_panels[] = (int)$pr['panel_id'];
            }
        }

        $s_norm_dept = normalize_course_name($s['department'] ?? '');
        $s_short_dept = get_course_short_code($s_norm_dept);
        
        $s['dept_norm'] = $s_norm_dept;
        $s['dept_code'] = $s_short_dept;
        if (isset($eligible_students_by_course[$s_short_dept])) {
            $eligible_students_by_course[$s_short_dept][] = $s;
        } else {
            $eligible_students_by_course['BSIT'][] = $s;
        }

        $students_metadata[$s['student_id']] = [
            'name' => $s['student_name'],
            'student_no' => $s['student_no'],
            'group_id' => $s['group_id'],
            'group_name' => $s['group_name'] ?? '',
            'topic' => $s['thesis_topic'] ?? '',
            'department' => $s_norm_dept,
            'dept_short' => $s_short_dept,
            'adviser_id' => $s['matched_adv_id'] ? (int)$s['matched_adv_id'] : 0,
            'adviser_name' => $s['matched_adv_name'] ?? '',
            'assigned_panel_ids' => $assigned_panels
        ];
    }
}

// 3. Fetch active faculty advisers
$advisers_query = $conn->query("
    SELECT u.id, u.name, u.email, ap.department, ap.expertise
    FROM user u
    JOIN adviser_profile ap ON u.id = ap.user_id
    WHERE u.role = 'adviser' AND u.account_status = 'active'
    ORDER BY u.name ASC
");
$all_advisers = [];
if ($advisers_query) {
    while ($adv = $advisers_query->fetch_assoc()) {
        $all_advisers[] = $adv;
    }
}

// 4. Fetch active panel members and faculty members eligible for defense committees
$panels_query = $conn->query("
    SELECT u.id, u.name, u.email, u.role,
           COALESCE(pp.department, ap.department, 'CEIT Faculty') as department,
           COALESCE(pp.expertise, ap.expertise, '') as expertise
    FROM user u
    LEFT JOIN panel_profile pp ON u.id = pp.user_id AND u.role = 'panel'
    LEFT JOIN adviser_profile ap ON u.id = ap.user_id AND u.role = 'adviser'
    WHERE u.role IN ('panel', 'adviser') AND u.account_status = 'active'
    ORDER BY (u.role = 'panel') DESC, u.name ASC
");
$all_panelists = [];
if ($panels_query) {
    while ($pnl = $panels_query->fetch_assoc()) {
        $all_panelists[] = $pnl;
    }
}

// 5. Fetch active defense secretaries
$secretaries_query = $conn->query("
    SELECT u.id, u.name, u.email, u.role,
           COALESCE(sp.department, 'CEIT Secretariat') as department
    FROM user u
    LEFT JOIN secretary_profile sp ON u.id = sp.user_id
    WHERE u.role = 'secretary' AND u.account_status = 'active'
    ORDER BY u.name ASC
");
$all_secretaries = [];
if ($secretaries_query) {
    while ($sec = $secretaries_query->fetch_assoc()) {
        $all_secretaries[] = $sec;
    }
}
// Helper function to render a single defense schedule card item
if (!function_exists('renderDefenseCardItem')) {
    function renderDefenseCardItem($sched, $today_str) {
        $date_obj = strtotime($sched['meeting_date']);
        $formatted_date = date('F j, Y', $date_obj);
        $day_name = date('l', $date_obj);
        $time_range = '';
        if (!empty($sched['start_time'])) {
            $time_range = date('g:i A', strtotime($sched['start_time']));
            if (!empty($sched['end_time'])) {
                $time_range .= ' - ' . date('g:i A', strtotime($sched['end_time']));
            }
        } else {
            $time_range = 'Time: TBA';
        }

        // Stage badge color
        $stage_badge_class = 'stage-final';
        if ($sched['defense_stage'] === 'Title Proposal') $stage_badge_class = 'stage-title';
        elseif ($sched['defense_stage'] === 'Outline Defense') $stage_badge_class = 'stage-outline';
        elseif ($sched['defense_stage'] === 'Pre-Oral Defense') $stage_badge_class = 'stage-preoral';

        // Status badge
        $is_upcoming = ($sched['meeting_date'] >= $today_str && $sched['status'] === 'scheduled');
        $is_today = ($sched['meeting_date'] === $today_str);

        // Decode panel members
        $panel_names_list = [];
        if (!empty($sched['panel_members'])) {
            $decoded = json_decode($sched['panel_members'], true);
            if (is_array($decoded)) {
                $panel_names_list = $decoded;
            } else {
                $panel_names_list = explode(',', $sched['panel_members']);
            }
        }

        // Capstone display name
        $candidate_title = !empty($sched['group_name']) ? "Group {$sched['group_name']}" : $sched['student_name'];
        $is_prop = ($sched['defense_category'] === 'Proposal Defense' || strpos($sched['course_code'], '198') !== false);
        ?>
        <div class="sched-card <?php echo $is_prop ? 'card-it198' : 'card-it199'; ?>" 
             data-id="<?php echo $sched['id']; ?>"
             data-status="<?php echo htmlspecialchars($sched['status']); ?>"
             data-stage="<?php echo htmlspecialchars($sched['defense_stage']); ?>"
             data-ay="<?php echo htmlspecialchars($sched['academic_year']); ?>"
             data-course="<?php echo htmlspecialchars($sched['course_code']); ?>"
             data-category="<?php echo htmlspecialchars($sched['defense_category']); ?>"
             data-dept="<?php echo htmlspecialchars($sched['dept_norm']); ?>"
             data-degree-course="<?php echo htmlspecialchars($sched['dept_code']); ?>"
             data-is-upcoming="<?php echo $is_upcoming ? '1' : '0'; ?>"
             data-search-text="<?php echo strtolower(htmlspecialchars($sched['title'] . ' ' . $sched['student_name'] . ' ' . ($sched['group_name'] ?? '') . ' ' . ($sched['adviser_name'] ?? '') . ' ' . ($sched['panel_chair_name'] ?? '') . ' ' . $sched['venue'] . ' ' . $sched['defense_stage'] . ' ' . $sched['academic_year'] . ' ' . $sched['course_code'] . ' ' . $sched['defense_category'] . ' ' . $sched['dept_norm'] . ' ' . $sched['dept_code'])); ?>">
            
            <!-- Top Ribbon: Date, Stage, Status -->
            <div class="sched-card-header">
                <div class="sched-date-badge <?php echo $is_today ? 'today-highlight' : ''; ?>">
                    <div class="date-month"><?php echo date('M', $date_obj); ?></div>
                    <div class="date-day"><?php echo date('d', $date_obj); ?></div>
                    <div class="date-year"><?php echo date('Y', $date_obj); ?></div>
                </div>

                <div class="sched-header-info">
                    <div class="sched-badges-row">
                        <!-- Degree Course Badge (BSIT / BSCpE / BSEE) -->
                        <span class="degree-pill pill-<?php echo strtolower($sched['dept_code']); ?>">
                            <i class="fa-solid fa-graduation-cap"></i> <?php echo htmlspecialchars($sched['dept_code']); ?>
                        </span>

                        <!-- Course Code Badge (IT 198 / CPE 198 / EE 198) -->
                        <span class="course-pill <?php echo $is_prop ? 'pill-it198' : 'pill-it199'; ?>">
                            <i class="fa-solid fa-code-branch"></i> <?php echo htmlspecialchars($sched['course_code'] ?: ($is_prop ? 'IT 198' : 'IT 199')); ?>
                        </span>

                        <!-- Defense Category Badge (Proposal vs Final Defense) -->
                        <span class="cat-pill <?php echo $is_prop ? 'pill-cat-proposal' : 'pill-cat-final'; ?>">
                            <i class="fa-solid <?php echo $is_prop ? 'fa-file-signature' : 'fa-graduation-cap'; ?>"></i> <?php echo htmlspecialchars($sched['defense_category'] ?: ($is_prop ? 'Proposal Defense' : 'Final Defense')); ?>
                        </span>

                        <!-- Academic Year Badge (A.Y.) -->
                        <span class="ay-pill">
                            <i class="fa-solid fa-calendar-days"></i> A.Y. <?php echo htmlspecialchars($sched['academic_year'] ?: calculate_academic_year($sched['meeting_date'])); ?>
                        </span>

                        <!-- Defense Stage Badge -->
                        <span class="stage-pill <?php echo $stage_badge_class; ?>">
                            <i class="fa-solid fa-bookmark"></i> <?php echo htmlspecialchars($sched['defense_stage']); ?>
                        </span>

                        <!-- Status Badge -->
                        <?php if($sched['status'] === 'in_progress'): ?>
                            <span class="status-pill in-progress">
                                <i class="fa-solid fa-circle-dot fa-fade"></i> Session In Progress
                            </span>
                        <?php elseif($sched['status'] === 'completed'): ?>
                            <span class="status-pill completed">
                                <i class="fa-solid fa-circle-check"></i> Deliberation Completed
                            </span>
                        <?php elseif($is_today): ?>
                            <span class="status-pill today">
                                <i class="fa-solid fa-bolt"></i> Defense Today
                            </span>
                        <?php elseif($is_upcoming): ?>
                            <span class="status-pill scheduled">
                                <i class="fa-regular fa-clock"></i> Scheduled
                            </span>
                        <?php else: ?>
                            <span class="status-pill archived">
                                <i class="fa-solid fa-archive"></i> Past Session
                            </span>
                        <?php endif; ?>

                        <?php if(!empty($sched['mom_verdict'])): ?>
                            <span class="verdict-tag">
                                <i class="fa-solid fa-gavel"></i> <?php echo htmlspecialchars($sched['mom_verdict']); ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Distinct Title Heading with Category Tag -->
                    <div class="sched-title-container">
                        <div class="sched-type-caption <?php echo $is_prop ? 'caption-it198' : 'caption-it199'; ?>">
                            <?php if ($is_prop): ?>
                                <i class="fa-solid fa-lightbulb"></i> <?php echo htmlspecialchars($sched['course_code']); ?> Proposal Defense Title &bull; <?php echo htmlspecialchars($sched['dept_code']); ?>
                            <?php else: ?>
                                <i class="fa-solid fa-award"></i> <?php echo htmlspecialchars($sched['course_code']); ?> Final Defense Title &bull; <?php echo htmlspecialchars($sched['dept_code']); ?>
                            <?php endif; ?>
                        </div>
                        <h3 class="sched-title"><?php echo htmlspecialchars($sched['title']); ?></h3>
                    </div>
                </div>

                <div class="sched-header-timing">
                    <div class="timing-row">
                        <i class="fa-regular fa-clock"></i>
                        <span><strong><?php echo $time_range; ?></strong> (<?php echo $day_name; ?>)</span>
                    </div>
                    <div class="timing-row">
                        <i class="fa-solid fa-location-dot"></i>
                        <span><?php echo htmlspecialchars($sched['venue'] ?? 'CEIT Defense Hall'); ?></span>
                    </div>
                    <?php if(!empty($sched['meeting_link'])): ?>
                        <a href="<?php echo htmlspecialchars($sched['meeting_link']); ?>" target="_blank" class="virtual-link-btn" title="Open Virtual Meeting Link">
                            <i class="fa-solid fa-video"></i> Virtual Room
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Body Grid: Candidate, Adviser, Panel Committee -->
            <div class="sched-card-body">
                <!-- Candidate / Research Group Column -->
                <div class="sched-party-box">
                    <div class="party-label"><i class="fa-solid fa-graduation-cap"></i> Candidate / Research Team</div>
                    <div class="party-main-name">
                        <strong><?php echo htmlspecialchars($candidate_title); ?></strong>
                        <?php if(!empty($sched['student_no'])): ?>
                            <span class="party-sub">(ID: <?php echo htmlspecialchars($sched['student_no']); ?>)</span>
                        <?php endif; ?>
                    </div>
                    <div class="party-sub-detail">
                        Presenter: <?php echo htmlspecialchars($sched['student_name']); ?> • <strong><?php echo htmlspecialchars($sched['dept_code']); ?></strong> (<?php echo htmlspecialchars($sched['dept_norm']); ?>)
                    </div>
                    <?php if(!empty($sched['student_topic']) && $sched['student_topic'] !== $sched['title']): ?>
                        <div class="party-topic-text"><i class="fa-solid fa-file-lines"></i> "<?php echo htmlspecialchars($sched['student_topic']); ?>"</div>
                    <?php endif; ?>
                </div>

                <!-- Capstone Adviser Column -->
                <div class="sched-party-box">
                    <div class="party-label"><i class="fa-solid fa-user-tie"></i> Assigned Capstone Adviser</div>
                    <div class="party-main-name">
                        <strong><?php echo htmlspecialchars(!empty($sched['adviser_name']) ? $sched['adviser_name'] : (!empty($sched['adviser_user_name']) ? $sched['adviser_user_name'] : 'To Be Assigned')); ?></strong>
                    </div>
                    <div class="party-sub-detail">
                        <span class="badge-role-pill adviser">Faculty Adviser</span>
                    </div>
                </div>

                <!-- Panel Committee Column -->
                <div class="sched-party-box">
                    <div class="party-label"><i class="fa-solid fa-users-viewfinder"></i> Panel Committee</div>
                    <?php if(!empty($sched['panel_chair_name'])): ?>
                        <div class="chair-line">
                            <i class="fa-solid fa-crown" style="color: #D97706;"></i>
                            <strong>Chair:</strong> <?php echo htmlspecialchars($sched['panel_chair_name']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="panel-chips-list">
                        <?php if(!empty($panel_names_list)): ?>
                            <?php foreach($panel_names_list as $pn): ?>
                                <span class="panel-member-chip">
                                    <i class="fa-solid fa-user-check"></i> <?php echo htmlspecialchars($pn); ?>
                                </span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span style="font-size: 0.8rem; color: #94A3B8; font-style: italic;">No panel members assigned yet.</span>
                        <?php endif; ?>
                    </div>

                    <!-- MOM Documenter -->
                    <?php 
                        $doc_display_name = !empty($sched['mom_documenter_name']) ? $sched['mom_documenter_name'] : (!empty($sched['mom_documenter_user_name']) ? $sched['mom_documenter_user_name'] : (!empty($sched['mom_recorded_by']) ? $sched['mom_recorded_by'] : ''));
                    ?>
                    <div class="documenter-line" style="margin-top: 0.5rem; padding-top: 0.45rem; border-top: 1px dashed #E2E8F0; font-size: 0.82rem; color: #065F46;">
                        <i class="fa-solid fa-pen-nib" style="color: #059669;"></i>
                        <strong>MOM Documenter:</strong> 
                        <?php if(!empty($doc_display_name)): ?>
                            <span class="badge" style="background:#ECFDF5; color:#047857; border:1px solid #A7F3D0; font-weight:700; padding:2px 7px; border-radius:6px;"><?php echo htmlspecialchars($doc_display_name); ?></span>
                        <?php else: ?>
                            <span style="color: #94A3B8; font-style: italic;">Unassigned (Edit schedule to assign)</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Notes Section if any -->
            <?php if(!empty($sched['notes'])): ?>
                <div class="sched-notes-bar">
                    <i class="fa-solid fa-circle-info"></i>
                    <span><strong>Instructions / Guidelines:</strong> <?php echo htmlspecialchars($sched['notes']); ?></span>
                </div>
            <?php endif; ?>

            <!-- Action Bar Footer -->
            <div class="sched-card-footer">
                <div class="footer-meta">
                    <?php if(!empty($sched['mom_verdict'])): ?>
                        <span><i class="fa-solid fa-gavel" style="color: #047857;"></i> MOM: <strong style="color: #047857;"><?php echo htmlspecialchars($sched['mom_verdict']); ?></strong></span>
                        <?php if(!empty($sched['mom_grade'])): ?>
                            <span>• Grade: <strong style="color: #047857;"><?php echo number_format((float)$sched['mom_grade'], 2); ?></strong></span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span><i class="fa-solid fa-file-signature"></i> MOM: <em style="color: #94A3B8;">Pending</em></span>
                    <?php endif; ?>
                    <span>• A.Y. <strong><?php echo htmlspecialchars($sched['academic_year']); ?></strong></span>
                    <span>• Created: <?php echo date('M d, Y', strtotime($sched['created_at'])); ?></span>
                </div>

                <div class="footer-buttons">
                    <?php if (!empty($sched['mom_id'])): ?>
                        <!-- Direct Link to View Official MOM Registry Record -->
                        <a href="admin_dashboard.php?tab=mom_archive&view_mom_id=<?php echo $sched['mom_id']; ?>" class="btn-action-outline" style="color: #047857; border-color: #A7F3D0; background: #ECFDF5; font-weight: 700;" title="View Official Minutes of the Meeting in Registry">
                            <i class="fa-solid fa-file-lines"></i> View Official MOM
                        </a>
                    <?php endif; ?>

                    <!-- Direct Access to MOM Workspace -->
                    <a href="admin_dashboard.php?tab=meeting_doc&student_id=<?php echo $sched['student_id']; ?>&meeting_id=<?php echo $sched['id']; ?>" class="btn-action-outline view-doc" title="Open Minutes of the Meeting (MOM) Workspace">
                        <i class="fa-solid fa-file-signature"></i> MOM Workspace
                    </a>

                    <!-- Notify Participants Button -->
                    <button type="button" class="btn-action-outline notify" onclick="openUnivReminderModal(<?php echo htmlspecialchars(json_encode([
                        'id' => (int)$sched['id'],
                        'candidate' => $candidate_title,
                        'title' => $sched['title'],
                        'stage' => $sched['defense_stage'],
                        'date' => date('D, M j, Y', strtotime($sched['meeting_date'])),
                        'time' => date('g:i A', strtotime($sched['start_time'])) . ' - ' . date('g:i A', strtotime($sched['end_time'])),
                        'venue' => $sched['venue'] ?? 'CEIT Defense Hall',
                        'adviser' => !empty($sched['adviser_name']) ? $sched['adviser_name'] : (!empty($sched['adviser_user_name']) ? $sched['adviser_user_name'] : 'Faculty Adviser'),
                        'chair' => $sched['panel_chair_name'] ?? 'Panel Committee Chair'
                    ]), ENT_QUOTES, 'UTF-8'); ?>)" title="Re-dispatch instant alerts to all participants">
                        <i class="fa-solid fa-bell"></i> Send Reminder
                    </button>

                    <!-- Edit Schedule Button -->
                    <button type="button" class="btn-action-outline edit" onclick="openEditScheduleModal(<?php echo $sched['id']; ?>)" title="Modify date, time, venue, or committee members">
                        <i class="fa-solid fa-pen-to-square"></i> Edit
                    </button>

                    <!-- Delete / Cancel Button -->
                    <button type="button" class="btn-action-outline delete" onclick="openUnivCancelScheduleModal(<?php echo htmlspecialchars(json_encode([
                        'id' => (int)$sched['id'],
                        'candidate' => $candidate_title,
                        'title' => $sched['title'],
                        'stage' => $sched['defense_stage'],
                        'date' => date('D, M j, Y', strtotime($sched['meeting_date'])),
                        'time' => date('g:i A', strtotime($sched['start_time'])) . ' - ' . date('g:i A', strtotime($sched['end_time'])),
                        'venue' => $sched['venue'] ?? 'CEIT Defense Hall'
                    ]), ENT_QUOTES, 'UTF-8'); ?>)" title="Cancel this defense schedule">
                        <i class="fa-solid fa-trash-can"></i>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
}
?>

<div class="schedules-wrapper">
    <!-- Header Banner -->
    <div class="schedule-banner">
        <div class="schedule-banner-content">
            <div class="banner-badge">
                <i class="fa-solid fa-graduation-cap"></i> Academic Defense Portal
            </div>
            <h1>Defense Schedule Management</h1>
            <p>Create, assign, synchronize, and monitor oral and final defense schedules categorized by degree course (BSIT, BSCpE, BSEE) and defense stage across Academic Years.</p>
        </div>
        <div class="schedule-banner-actions">
            <button type="button" class="btn-create-defense" onclick="openCreateScheduleModal()">
                <i class="fa-solid fa-plus-circle"></i> Create Defense Schedule
            </button>
        </div>
    </div>

    <!-- Quick Stats Metric Cards -->
    <div class="schedule-stats-grid">
        <div class="sched-stat-card">
            <div class="stat-icon-circle blue">
                <i class="fa-solid fa-calendar-days"></i>
            </div>
            <div class="stat-details">
                <div class="stat-num" id="statTotal"><?php echo $total_count; ?></div>
                <div class="stat-lbl">Total Defense Sessions</div>
            </div>
        </div>

        <div class="sched-stat-card">
            <div class="stat-icon-circle amber">
                <i class="fa-solid fa-file-signature"></i>
            </div>
            <div class="stat-details">
                <div class="stat-num" id="statProposal"><?php echo $proposal_count; ?></div>
                <div class="stat-lbl">Proposal Defenses (198)</div>
            </div>
        </div>

        <div class="sched-stat-card">
            <div class="stat-icon-circle purple">
                <i class="fa-solid fa-graduation-cap"></i>
            </div>
            <div class="stat-details">
                <div class="stat-num" id="statFinal"><?php echo $final_count; ?></div>
                <div class="stat-lbl">Final Defenses (199)</div>
            </div>
        </div>

        <div class="sched-stat-card">
            <div class="stat-icon-circle green">
                <i class="fa-solid fa-clock"></i>
            </div>
            <div class="stat-details">
                <div class="stat-num" id="statUpcoming"><?php echo $upcoming_count; ?></div>
                <div class="stat-lbl">Upcoming Defenses</div>
            </div>
        </div>

        <div class="sched-stat-card">
            <div class="stat-icon-circle teal">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <div class="stat-details">
                <div class="stat-num" id="statCompleted"><?php echo $completed_count; ?></div>
                <div class="stat-lbl">Completed / Deliberated</div>
            </div>
        </div>
    </div>

    <!-- Filter & Search Controls Bar -->
    <div class="sched-filter-card">
        <!-- Degree Program Filter Row (by Degree Courses: BSIT, BSCpE, BSEE) -->
        <div class="filter-degree-row">
            <div class="degree-filter-left">
                <span class="degree-filter-label">
                    <i class="fa-solid fa-graduation-cap"></i> Degree Program:
                </span>
                <div class="degree-filter-pills">
                    <button type="button" class="degree-pill-btn active" id="dpBtnAll" onclick="filterByDegreeProgram('all', this)">
                        <i class="fa-solid fa-shapes"></i> All Courses (<span id="cntAllCourse"><?php echo $total_count; ?></span>)
                    </button>
                    <button type="button" class="degree-pill-btn btn-bsit" id="dpBtnBSIT" onclick="filterByDegreeProgram('BSIT', this)">
                        <span class="dp-tag tag-bsit">BSIT</span> Information Technology (<span id="cntBSIT"><?php echo $course_counts['BSIT']; ?></span>)
                    </button>
                    <button type="button" class="degree-pill-btn btn-bscpe" id="dpBtnBSCpE" onclick="filterByDegreeProgram('BSCpE', this)">
                        <span class="dp-tag tag-bscpe">BSCpE</span> Computer Engineering (<span id="cntBSCpE"><?php echo $course_counts['BSCpE']; ?></span>)
                    </button>
                    <button type="button" class="degree-pill-btn btn-bsee" id="dpBtnBSEE" onclick="filterByDegreeProgram('BSEE', this)">
                        <span class="dp-tag tag-bsee">BSEE</span> Electrical Engineering (<span id="cntBSEE"><?php echo $course_counts['BSEE']; ?></span>)
                    </button>
                </div>
            </div>
            <div class="degree-filter-right">
                <span id="activeDegreeNotice" class="degree-notice-badge">
                    <i class="fa-solid fa-circle-check"></i> Showing All Programs
                </span>
            </div>
        </div>

        <!-- Top Toolbar: Defense Stage Category Switcher & Academic Year Filter -->
        <div class="filter-top-row">
            <!-- Category Tabs: All, Proposal (198), Final (199) -->
            <div class="category-tabs">
                <button type="button" class="cat-tab-btn active" id="catBtnAll" onclick="filterByCategory('all', this)">
                    <i class="fa-solid fa-layer-group"></i> All Defenses (<?php echo $total_count; ?>)
                </button>
                <button type="button" class="cat-tab-btn it198-tab" id="catBtn198" onclick="filterByCategory('proposal', this)">
                    <i class="fa-solid fa-file-signature"></i> Proposal Defenses (<span id="countPropHeader"><?php echo $proposal_count; ?></span>)
                </button>
                <button type="button" class="cat-tab-btn it199-tab" id="catBtn199" onclick="filterByCategory('final', this)">
                    <i class="fa-solid fa-graduation-cap"></i> Final Defenses (<span id="countFinalHeader"><?php echo $final_count; ?></span>)
                </button>
            </div>

            <!-- Academic Year (A.Y.) Typable Filter with Datalist -->
            <div class="ay-filter-wrapper">
                <label for="ayFilter" class="filter-label-ay">
                    <i class="fa-solid fa-graduation-cap"></i> Academic Year (A.Y.):
                </label>
                <div style="position: relative; display: inline-flex; align-items: center;">
                    <input type="text" 
                           id="ayFilter" 
                           list="ayDatalist" 
                           class="filter-select-ay" 
                           placeholder="Type A.Y. (e.g. 2026-2027)" 
                           oninput="onAyFilterInput()" 
                           onchange="onAyFilterInput()" 
                           autocomplete="off"
                           style="padding-right: 1.85rem; min-width: 175px;">
                    <datalist id="ayDatalist">
                        <?php foreach($available_academic_years as $ay_opt): ?>
                            <option value="<?php echo htmlspecialchars($ay_opt); ?>">A.Y. <?php echo htmlspecialchars($ay_opt); ?></option>
                        <?php endforeach; ?>
                    </datalist>
                    <button type="button" 
                            id="ayClearBtn" 
                            onclick="clearAyFilter()" 
                            title="Clear A.Y. filter" 
                            style="position: absolute; right: 0.5rem; background: none; border: none; color: #94A3B8; cursor: pointer; font-size: 0.85rem; display: none; padding: 2px;">
                        <i class="fa-solid fa-circle-xmark"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Bottom Toolbar: Status Pills, Stage Dropdown, and Search -->
        <div class="filter-bottom-row">
            <div class="filter-left">
                <div class="filter-pills">
                    <button type="button" class="filter-pill active" onclick="filterSchedules('all', this)">
                        All Statuses
                    </button>
                    <button type="button" class="filter-pill" onclick="filterSchedules('upcoming', this)">
                        <i class="fa-regular fa-calendar-check"></i> Upcoming
                    </button>
                    <button type="button" class="filter-pill" onclick="filterSchedules('in_progress', this)">
                        <i class="fa-solid fa-microphone-lines"></i> In-Progress
                    </button>
                    <button type="button" class="filter-pill" onclick="filterSchedules('completed', this)">
                        <i class="fa-solid fa-check-double"></i> Completed
                    </button>
                </div>
            </div>

            <div class="filter-right">
                <!-- Stage Dropdown Filter -->
                <select id="stageFilter" class="filter-select" onchange="applyFilters()">
                    <option value="">All Defense Stages</option>
                    <option value="Title Proposal">Title Proposal</option>
                    <option value="Outline Defense">Outline Defense</option>
                    <option value="Pre-Oral Defense">Pre-Oral Defense</option>
                    <option value="Final Oral Defense">Final Oral Defense</option>
                </select>

                <!-- Search input -->
                <div class="search-box-wrapper">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="scheduleSearchInput" class="search-input" placeholder="Search candidate, title, venue, A.Y..." onkeyup="applyFilters()">
                </div>
            </div>
        </div>
    </div>

    <!-- Schedules Feed / List Container -->
    <div class="schedules-list" id="schedulesContainer">
        <?php if(empty($all_schedules)): ?>
            <div class="empty-state-box">
                <div class="empty-icon"><i class="fa-solid fa-calendar-xmark"></i></div>
                <h3>No Defense Schedules Created Yet</h3>
                <p>Click "Create Defense Schedule" above to assign an oral or final defense session for a research candidate.</p>
                <button type="button" class="btn-create-defense" style="margin-top: 1rem;" onclick="openCreateScheduleModal()">
                    <i class="fa-solid fa-plus-circle"></i> Create First Schedule
                </button>
            </div>
        <?php else: ?>
            <!-- ======================================================== -->
            <!-- SECTION 1: PROPOSAL DEFENSES (198 COURSE SERIES) -->
            <!-- ======================================================== -->
            <div class="defense-category-section" id="sectionIT198">
                <div class="defense-section-banner it198-banner">
                    <div class="banner-title-group">
                        <span class="banner-code-badge it198">
                            <i class="fa-solid fa-file-signature"></i> 198 Series
                        </span>
                        <div>
                            <h3 class="section-title-heading">Proposal Defense Deliberations</h3>
                            <p class="section-title-sub">Capstone Project & Research 1 • Title Proposals, Outline & Pre-Oral Defenses (IT 198 / CPE 198 / EE 198)</p>
                        </div>
                    </div>
                    <span class="section-counter it198-counter" id="badgeCountIT198">
                        <i class="fa-solid fa-bookmark"></i> <span id="numIT198"><?php echo count($it198_schedules); ?></span> Session(s)
                    </span>
                </div>

                <div class="section-cards-container" id="cardsContainerIT198">
                    <?php if(empty($it198_schedules)): ?>
                        <div class="empty-section-notice" id="emptyNoticeIT198">
                            <i class="fa-regular fa-folder-open"></i> No Proposal Defense sessions scheduled yet.
                        </div>
                    <?php else: ?>
                        <?php foreach($it198_schedules as $sched): renderDefenseCardItem($sched, $today_str); endforeach; ?>
                        <div class="empty-section-notice" id="emptyNoticeIT198" style="display:none;">
                            <i class="fa-regular fa-folder-open"></i> No matching Proposal Defense sessions found with the selected filters.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ======================================================== -->
            <!-- SECTION 2: FINAL DEFENSES (199 COURSE SERIES) -->
            <!-- ======================================================== -->
            <div class="defense-category-section" id="sectionIT199">
                <div class="defense-section-banner it199-banner">
                    <div class="banner-title-group">
                        <span class="banner-code-badge it199">
                            <i class="fa-solid fa-graduation-cap"></i> 199 Series
                        </span>
                        <div>
                            <h3 class="section-title-heading">Final Oral Defense Deliberations</h3>
                            <p class="section-title-sub">Capstone Project & Research 2 • Final Oral Defenses & System Demonstrations (IT 199 / CPE 199 / EE 199)</p>
                        </div>
                    </div>
                    <span class="section-counter it199-counter" id="badgeCountIT199">
                        <i class="fa-solid fa-award"></i> <span id="numIT199"><?php echo count($it199_schedules); ?></span> Session(s)
                    </span>
                </div>

                <div class="section-cards-container" id="cardsContainerIT199">
                    <?php if(empty($it199_schedules)): ?>
                        <div class="empty-section-notice" id="emptyNoticeIT199">
                            <i class="fa-regular fa-folder-open"></i> No Final Defense sessions scheduled yet.
                        </div>
                    <?php else: ?>
                        <?php foreach($it199_schedules as $sched): renderDefenseCardItem($sched, $today_str); endforeach; ?>
                        <div class="empty-section-notice" id="emptyNoticeIT199" style="display:none;">
                            <i class="fa-regular fa-folder-open"></i> No matching Final Defense sessions found with the selected filters.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: CREATE / EDIT DEFENSE SCHEDULE -->
<!-- ======================================================== -->
<div id="scheduleModal" class="schedule-modal" style="display:none;">
    <div class="schedule-modal-content">
        <div class="schedule-modal-header">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <div class="modal-icon-badge">
                    <i class="fa-solid fa-calendar-plus" id="modalHeaderIcon"></i>
                </div>
                <div>
                    <h2 id="scheduleModalTitle" style="margin: 0; font-size: 1.25rem; font-weight: 800; color: #022C22;">Create Defense Schedule</h2>
                    <p style="margin: 0; font-size: 0.82rem; color: #64748B;">Assign date, time, venue, committee panelists, and adviser for oral defense</p>
                </div>
            </div>
            <button type="button" class="modal-close-btn" onclick="closeScheduleModal()">&times;</button>
        </div>

        <form method="POST" action="action.php" id="defenseScheduleForm" onsubmit="return validateDefenseScheduleForm()">
            <input type="hidden" name="save_defense_schedule" value="1">
            <input type="hidden" name="schedule_id" id="formScheduleId" value="0">

            <div class="schedule-modal-body">
                <!-- Row 1: Candidate Selection & Topic -->
                <div class="form-row">
                    <div class="form-group flex-2">
                        <label class="form-label" for="formStudentSelect">
                            <i class="fa-solid fa-user-graduate"></i> Assigned Student or Research Group <span style="color:#DC2626;">*</span>
                        </label>
                        <select name="student_id" id="formStudentSelect" class="form-control" required onchange="onStudentSelectChange(this.value)">
                            <option value="">-- Choose Candidate / Capstone Group --</option>
                            <?php if (!empty($eligible_students_by_course['BSIT'])): ?>
                                <optgroup label="BSIT — Information Technology (<?php echo count($eligible_students_by_course['BSIT']); ?>)">
                                    <?php foreach($eligible_students_by_course['BSIT'] as $st): 
                                        $glabel = !empty($st['group_name']) ? "Group {$st['group_name']} - " : "";
                                        $sno = !empty($st['student_no']) ? " [{$st['student_no']}]" : "";
                                    ?>
                                        <option value="<?php echo $st['student_id']; ?>">
                                            <?php echo htmlspecialchars($glabel . $st['student_name'] . $sno); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
                            <?php if (!empty($eligible_students_by_course['BSCpE'])): ?>
                                <optgroup label="BSCpE — Computer Engineering (<?php echo count($eligible_students_by_course['BSCpE']); ?>)">
                                    <?php foreach($eligible_students_by_course['BSCpE'] as $st): 
                                        $glabel = !empty($st['group_name']) ? "Group {$st['group_name']} - " : "";
                                        $sno = !empty($st['student_no']) ? " [{$st['student_no']}]" : "";
                                    ?>
                                        <option value="<?php echo $st['student_id']; ?>">
                                            <?php echo htmlspecialchars($glabel . $st['student_name'] . $sno); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
                            <?php if (!empty($eligible_students_by_course['BSEE'])): ?>
                                <optgroup label="BSEE — Electrical Engineering (<?php echo count($eligible_students_by_course['BSEE']); ?>)">
                                    <?php foreach($eligible_students_by_course['BSEE'] as $st): 
                                        $glabel = !empty($st['group_name']) ? "Group {$st['group_name']} - " : "";
                                        $sno = !empty($st['student_no']) ? " [{$st['student_no']}]" : "";
                                    ?>
                                        <option value="<?php echo $st['student_id']; ?>">
                                            <?php echo htmlspecialchars($glabel . $st['student_name'] . $sno); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
                        </select>
                        <div id="candidateAutoInfo" class="auto-info-pill" style="display:none;"></div>
                    </div>

                    <div class="form-group flex-1">
                        <label class="form-label" for="formDefenseStage">
                            <i class="fa-solid fa-layer-group"></i> Defense Type / Stage <span style="color:#DC2626;">*</span>
                        </label>
                        <select name="defense_stage" id="formDefenseStage" class="form-control" required onchange="updateDefaultTitle()">
                            <option value="Final Oral Defense" selected>Final Oral Defense</option>
                            <option value="Pre-Oral Defense">Pre-Oral Defense</option>
                            <option value="Outline Defense">Outline / Proposal Defense</option>
                            <option value="Title Proposal">Title Proposal Defense</option>
                        </select>
                    </div>
                </div>

                <!-- Row 1.5: Degree Program & Academic Year -->
                <div class="form-row">
                    <div class="form-group flex-1">
                        <label class="form-label" for="formDepartment">
                            <i class="fa-solid fa-graduation-cap"></i> Degree Program / Course <span style="color:#DC2626;">*</span>
                        </label>
                        <select name="department" id="formDepartment" class="form-control" required onchange="onDepartmentFormChange(this.value)">
                            <option value="Information Technology">BSIT — Information Technology</option>
                            <option value="Computer Engineering">BSCpE — Computer Engineering</option>
                            <option value="Electrical Engineering">BSEE — Electrical Engineering</option>
                        </select>
                    </div>

                    <div class="form-group flex-1">
                        <label class="form-label" for="formAcademicYear">
                            <i class="fa-solid fa-calendar-days"></i> Academic Year (A.Y.) <span style="color:#DC2626;">*</span>
                        </label>
                        <input type="text" 
                               name="academic_year" 
                               id="formAcademicYear" 
                               list="modalAyList" 
                               class="form-control" 
                               placeholder="e.g. 2026-2027" 
                               value="<?php echo htmlspecialchars($curr_system_ay); ?>" 
                               required 
                               autocomplete="off">
                        <datalist id="modalAyList">
                            <?php foreach($available_academic_years as $ay_opt): ?>
                                <option value="<?php echo htmlspecialchars($ay_opt); ?>">A.Y. <?php echo htmlspecialchars($ay_opt); ?></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                </div>

                <!-- Row 1.6: Course / Subject Code & Defense Category -->
                <div class="form-row">
                    <div class="form-group flex-1">
                        <label class="form-label" for="formCourseCode">
                            <i class="fa-solid fa-code-branch"></i> Course / Subject <span style="color:#DC2626;">*</span>
                        </label>
                        <select name="course_code" id="formCourseCode" class="form-control" required onchange="onCourseCodeChange(this.value)">
                            <option value="IT 198">IT 198 (Proposal Defense)</option>
                            <option value="IT 199" selected>IT 199 (Final Defense)</option>
                        </select>
                    </div>

                    <div class="form-group flex-1">
                        <label class="form-label" for="formDefenseCategory">
                            <i class="fa-solid fa-tags"></i> Defense Category <span style="color:#DC2626;">*</span>
                        </label>
                        <select name="defense_category" id="formDefenseCategory" class="form-control" required onchange="onDefenseCategoryChange(this.value)">
                            <option value="Proposal Defense">Proposal Defense (198 Series)</option>
                            <option value="Final Defense" selected>Final Defense (199 Series)</option>
                        </select>
                    </div>
                </div>

                <!-- Custom Title -->
                <div class="form-group">
                    <label class="form-label" for="formTitle">
                        <i class="fa-solid fa-heading"></i> Defense Session Title / Capstone Topic
                    </label>
                    <input type="text" name="title" id="formTitle" class="form-control" placeholder="e.g. Final Oral Defense: Web-Based IoT Smart Home Monitoring System" required>
                </div>

                <!-- Row 2: Date, Start Time, End Time -->
                <div class="form-row-3">
                    <div class="form-group">
                        <label class="form-label" for="formDate">
                            <i class="fa-regular fa-calendar"></i> Defense Date <span style="color:#DC2626;">*</span>
                        </label>
                        <input type="date" name="meeting_date" id="formDate" class="form-control" value="<?php echo date('Y-m-d', strtotime('+3 days')); ?>" required onchange="onDefenseDateChange(this.value)">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="formStartTime">
                            <i class="fa-regular fa-clock"></i> Start Time <span style="color:#DC2626;">*</span>
                        </label>
                        <input type="time" name="start_time" id="formStartTime" class="form-control" value="09:00" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="formEndTime">
                            <i class="fa-regular fa-clock"></i> End Time <span style="color:#DC2626;">*</span>
                        </label>
                        <input type="time" name="end_time" id="formEndTime" class="form-control" value="11:00" required>
                    </div>
                </div>

                <!-- Row 3: Venue, Virtual Meeting Link -->
                <div class="form-row">
                    <div class="form-group flex-1">
                        <label class="form-label" for="formVenue">
                            <i class="fa-solid fa-location-dot"></i> Venue / Room <span style="color:#DC2626;">*</span>
                        </label>
                        <input type="text" name="venue" id="formVenue" class="form-control" list="venuesList" placeholder="e.g. CEIT Defense Hall" value="CEIT Defense Hall" required>
                        <datalist id="venuesList">
                            <option value="CEIT Defense Hall">
                            <option value="AVR 1 - Engineering Building">
                            <option value="AVR 2 - ICT Building">
                            <option value="Conference Room 201">
                            <option value="Computer Laboratory 3">
                            <option value="Virtual Video Conference (Zoom / Google Meet)">
                        </datalist>
                    </div>

                    <div class="form-group flex-1">
                        <label class="form-label" for="formMeetingLink">
                            <i class="fa-solid fa-link"></i> Virtual Meeting Link (Optional)
                        </label>
                        <input type="url" name="meeting_link" id="formMeetingLink" class="form-control" placeholder="https://meet.google.com/... or Zoom link">
                    </div>
                </div>

                <!-- Row 4: Adviser & Status -->
                <div class="form-row">
                    <div class="form-group flex-2">
                        <label class="form-label" for="formAdviserSelect">
                            <i class="fa-solid fa-user-tie"></i> Assigned Capstone Adviser
                        </label>
                        <select name="adviser_id" id="formAdviserSelect" class="form-control">
                            <option value="">-- Auto-detect from Advisee Match or Select --</option>
                            <?php foreach($all_advisers as $adv): ?>
                                <option value="<?php echo $adv['id']; ?>">
                                    👨‍🏫 <?php echo htmlspecialchars($adv['name']); ?> (<?php echo htmlspecialchars($adv['department'] ?? 'Faculty'); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group flex-1">
                        <label class="form-label" for="formStatus">
                            <i class="fa-solid fa-flag"></i> Schedule Status
                        </label>
                        <select name="status" id="formStatus" class="form-control">
                            <option value="scheduled" selected>Scheduled</option>
                            <option value="in_progress">In-Progress</option>
                            <option value="completed">Completed</option>
                            <option value="archived">Archived / Cancelled</option>
                        </select>
                    </div>
                </div>

                <!-- Committee Roster Section -->
                <div class="committee-select-box">
                    <div class="committee-header">
                        <i class="fa-solid fa-users-viewfinder"></i> Panel Committee Assignment
                    </div>

                    <div class="form-group" style="margin-bottom: 0.85rem;">
                        <label class="form-label" for="formChairSelect">
                            <i class="fa-solid fa-crown" style="color: #D97706;"></i> Committee Chair <span style="color:#DC2626;">*</span>
                        </label>
                        <select name="panel_chair_id" id="formChairSelect" class="form-control" required>
                            <option value="">-- Designate Panel Committee Chair --</option>
                            <?php foreach($all_panelists as $p): ?>
                                <option value="<?php echo $p['id']; ?>">
                                    <?php echo ($p['role'] === 'panel') ? '🛡️ [Panelist]' : '👨‍🏫 [Faculty]'; ?> <?php echo htmlspecialchars($p['name']); ?> (<?php echo htmlspecialchars($p['department'] ?? 'CEIT'); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- MOM Documenter / Secretary / Recorder -->
                    <div class="form-group" style="margin-bottom: 0.95rem; background: #F0FDF4; border: 1.5px dashed #86EFAC; padding: 0.85rem 1rem; border-radius: 8px;">
                        <label class="form-label" for="formMomDocSelect" style="color: #065F46; font-weight: 700; margin-bottom: 0.35rem; display: flex; align-items: center; justify-content: space-between;">
                            <span><i class="fa-solid fa-pen-nib" style="color: #059669;"></i> MOM Documenter (Committee Documenter / Secretary)</span>
                            <span style="font-size: 0.72rem; background: #DCFCE7; color: #15803D; padding: 2px 6px; border-radius: 4px; font-weight: 600;">Oral Defense Documenter</span>
                        </label>
                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                            <select name="mom_documenter_id" id="formMomDocSelect" class="form-control" style="flex: 1; min-width: 220px;" onchange="onMomDocSelectChange(this)">
                                <option value="">-- Select MOM Documenter (Secretary / Panelist / Faculty) --</option>
                                <?php if(!empty($all_secretaries)): ?>
                                    <optgroup label="📋 Official Defense Secretaries">
                                        <?php foreach($all_secretaries as $sec): ?>
                                            <option value="<?php echo $sec['id']; ?>" data-name="<?php echo htmlspecialchars($sec['name']); ?>">
                                                📋 [Secretary] <?php echo htmlspecialchars($sec['name']); ?> (<?php echo htmlspecialchars($sec['department']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                                <optgroup label="🛡️ Faculty & Panel Committee">
                                    <?php foreach($all_panelists as $p): ?>
                                        <option value="<?php echo $p['id']; ?>" data-name="<?php echo htmlspecialchars($p['name']); ?>">
                                            <?php echo ($p['role'] === 'panel') ? '🛡️ [Panelist]' : '👨‍🏫 [Faculty]'; ?> <?php echo htmlspecialchars($p['name']); ?> (<?php echo htmlspecialchars($p['department'] ?? 'CEIT'); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <option value="custom">✏️ Type Custom Documenter / Student Secretary Name...</option>
                            </select>
                            <input type="text" name="mom_documenter_name" id="formMomDocName" class="form-control" placeholder="Name of MOM Documenter (e.g. Engr. Dela Cruz / Group Secretary)" style="flex: 1; min-width: 220px; display: none;">
                        </div>
                        <small style="color: #047857; font-size: 0.75rem; display: block; margin-top: 0.4rem; line-height: 1.35;">
                            <i class="fa-solid fa-circle-info"></i> The assigned documenter is authorized to record and document the official Minutes of the Meeting (MOM) during deliberation.
                        </small>
                    </div>

                    <label class="form-label">
                        <i class="fa-solid fa-user-group"></i> Panel Members (Select 1 to 3 Panelists):
                    </label>
                    <div class="panel-checkbox-grid">
                        <?php foreach($all_panelists as $p): ?>
                            <label class="panel-check-card" id="labelPanel_<?php echo $p['id']; ?>">
                                <input type="checkbox" name="panel_member_ids[]" value="<?php echo $p['id']; ?>" class="panel-checkbox" onchange="highlightPanelCard(this)">
                                <div class="panel-card-text">
                                    <strong><?php echo htmlspecialchars($p['name']); ?></strong>
                                    <span><?php echo ($p['role'] === 'panel') ? '🛡️ Panelist' : '👨‍🏫 Faculty'; ?> • <?php echo htmlspecialchars($p['department'] ?? 'CEIT'); ?></span>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Notes / Guidelines -->
                <div class="form-group">
                    <label class="form-label" for="formNotes">
                        <i class="fa-solid fa-clipboard-list"></i> Guidelines / Administrative Notes
                    </label>
                    <textarea name="notes" id="formNotes" class="form-control" rows="2" placeholder="e.g. Prepare 15-minute presentation deck, 3 hard copies of manuscript, and working hardware prototype."></textarea>
                </div>

                <!-- Notification Checkbox -->
                <div class="notif-toggle-box">
                    <label style="display: flex; align-items: center; gap: 0.65rem; cursor: pointer; font-size: 0.92rem; font-weight: 700; color: #064E3B; margin: 0;">
                        <input type="checkbox" name="send_notifications" value="1" checked style="width: 18px; height: 18px; accent-color: #059669;">
                        <span><i class="fa-solid fa-paper-plane"></i> Send instant notification alert to student candidate, assigned adviser, and panel members</span>
                    </label>
                </div>
            </div>

            <div class="schedule-modal-footer">
                <button type="button" class="btn-cancel" onclick="closeScheduleModal()">Cancel</button>
                <button type="submit" class="btn-submit" id="btnSubmitSchedule">
                    <i class="fa-solid fa-check"></i> Save & Assign Schedule
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: RECORD MOM & APPLY VERDICT (MINUTES OF THE MEETING) -->
<!-- ======================================================== -->
<div id="quickMomModal" class="schedule-modal" style="display:none;">
    <div class="schedule-modal-content" style="max-width: 820px;">
        <div class="schedule-modal-header" style="background: linear-gradient(135deg, #064E3B 0%, #047857 100%);">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <div class="modal-icon-badge" style="background: #10B981; color: white;">
                    <i class="fa-solid fa-file-signature"></i>
                </div>
                <div>
                    <h2 style="margin: 0; font-size: 1.25rem; font-weight: 800; color: #FFFFFF;">Record MOM &amp; Apply Deliberation Verdict</h2>
                    <p style="margin: 0; font-size: 0.82rem; color: #A7F3D0;">Official Oral Defense Minutes of the Meeting (MOM), Panel Directives & Evaluation Verdict</p>
                </div>
            </div>
            <button type="button" class="modal-close-btn" style="color: #FFFFFF;" onclick="closeQuickMomModal()">&times;</button>
        </div>

        <form id="quickMomForm" onsubmit="submitQuickMomForm(event)">
            <input type="hidden" name="meeting_id" id="qmMid" value="0">
            <input type="hidden" name="student_id" id="qmStudentId" value="0">
            <input type="hidden" name="group_id" id="qmGroupId" value="">
            <input type="hidden" name="defense_stage" id="qmDefenseStage" value="Final Oral Defense">
            <input type="hidden" name="meeting_date" id="qmMeetingDate" value="">
            <input type="hidden" name="venue" id="qmVenue" value="">

            <div class="schedule-modal-body" style="padding: 1.25rem 1.5rem; max-height: calc(88vh - 140px);">
                <!-- Candidate Brief Summary Banner -->
                <div style="background: #ECFDF5; border: 1.5px solid #A7F3D0; border-radius: 8px; padding: 0.85rem 1rem; margin-bottom: 1.25rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                    <div>
                        <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #047857;">Research Candidate / Capstone Team:</div>
                        <div id="qmCandidateDisplay" style="font-size: 1.05rem; font-weight: 800; color: #064E3B;">Candidate Name</div>
                        <div id="qmDateVenueDisplay" style="font-size: 0.8rem; color: #059669; margin-top: 2px;">Date & Venue</div>
                    </div>
                    <span id="qmStageDisplay" class="badge" style="background: #059669; color: white; padding: 4px 10px; border-radius: 6px; font-weight: 700; font-size: 0.82rem;">Defense Stage</span>
                </div>

                <!-- Document Title -->
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label" for="qmDocumentTitle">
                        <i class="fa-solid fa-file-lines"></i> Official MOM Document Title <span style="color:#DC2626;">*</span>
                    </label>
                    <input type="text" name="document_title" id="qmDocumentTitle" class="form-control" required placeholder="Oral Defense Minutes of the Meeting">
                </div>

                <!-- MOM Documenter & Committee Chair Row -->
                <div class="form-row" style="display: flex; gap: 1rem; margin-bottom: 1rem;">
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label" for="qmRecordedBy" style="color: #065F46; font-weight: 700;">
                            <i class="fa-solid fa-pen-nib" style="color: #059669;"></i> MOM Documenter (Recorded By / Documenter) <span style="color:#DC2626;">*</span>
                        </label>
                        <input type="text" name="recorded_by_name" id="qmRecordedBy" class="form-control" required placeholder="Name of MOM Documenter">
                    </div>

                    <div class="form-group" style="flex: 1;">
                        <label class="form-label" for="qmApprovedByChair">
                            <i class="fa-solid fa-crown" style="color: #D97706;"></i> Committee Chair (Approved By)
                        </label>
                        <input type="text" name="approved_by_chair" id="qmApprovedByChair" class="form-control" placeholder="Panel Committee Chair">
                    </div>
                </div>

                <!-- Verdict, Grade Average, and Status Row -->
                <div class="form-row" style="display: flex; gap: 1rem; margin-bottom: 1.25rem;">
                    <div class="form-group" style="flex: 2;">
                        <label class="form-label" for="qmVerdict" style="color: #0F172A; font-weight: 700;">
                            <i class="fa-solid fa-gavel" style="color: #059669;"></i> Deliberation Verdict (Official Decision) <span style="color:#DC2626;">*</span>
                        </label>
                        <select name="decision_verdict" id="qmVerdict" class="form-control" required style="font-weight: 700; color: #064E3B; background: #F0FDF4; border-color: #86EFAC;">
                            <option value="Approved without Revisions">🟢 Approved without Revisions</option>
                            <option value="Approved with Minor Revisions" selected>🟡 Approved with Minor Revisions</option>
                            <option value="Approved with Major Revisions">🟠 Approved with Major Revisions</option>
                            <option value="Re-defense Required">🔴 Re-defense Required</option>
                            <option value="Disapproved">❌ Disapproved</option>
                            <option value="Deferred">⏸️ Deferred</option>
                        </select>
                    </div>

                    <div class="form-group" style="flex: 1;">
                        <label class="form-label" for="qmGradeAverage">
                            <i class="fa-solid fa-star" style="color: #F59E0B;"></i> Grade Rating (%)
                        </label>
                        <input type="number" step="0.01" min="0" max="100" name="grade_average" id="qmGradeAverage" class="form-control" placeholder="e.g. 91.50">
                    </div>

                    <div class="form-group" style="flex: 1;">
                        <label class="form-label" for="qmMomStatus">
                            <i class="fa-solid fa-circle-check"></i> MOM Document Status
                        </label>
                        <select name="status" id="qmMomStatus" class="form-control">
                            <option value="draft">Draft</option>
                            <option value="under_review">Under Review</option>
                            <option value="finalized" selected>Finalized</option>
                            <option value="signed_off">Signed Off</option>
                        </select>
                    </div>
                </div>

                <!-- Discussion & Defense Summary -->
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label" for="qmDiscussion">
                        <i class="fa-solid fa-comments"></i> Salient Discussion & Deliberation Highlights (MOM Summary)
                    </label>
                    <textarea name="discussion_summary" id="qmDiscussion" class="form-control" rows="3" placeholder="Record deliberation proceedings, key questions from the panel, and responses from the research team..."></textarea>
                </div>

                <!-- Recommendations & Directives -->
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label" for="qmRecommendations">
                        <i class="fa-solid fa-list-check" style="color: #059669;"></i> Panel Recommendations (Directives & Required Changes)
                    </label>
                    <textarea name="recommendations" id="qmRecommendations" class="form-control" rows="3" placeholder="List all revisions required by the panel (e.g. Enhance Chapter 3 sampling methodology, include ISO 25010 user acceptance test, update UI architecture)..."></textarea>
                </div>

                <!-- Action Items / Revision Checklist -->
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label" for="qmActionItems">
                        <i class="fa-solid fa-clipboard-check"></i> Action Items & Compliance Checklist
                    </label>
                    <textarea name="action_items" id="qmActionItems" class="form-control" rows="2" placeholder="Tasks that must be completed by the student team before manuscript sign-off..."></textarea>
                </div>

                <!-- Notes -->
                <div class="form-group" style="margin-bottom: 0.5rem;">
                    <label class="form-label" for="qmNotes">
                        <i class="fa-solid fa-note-sticky"></i> Administrative Notes (Optional)
                    </label>
                    <textarea name="notes" id="qmNotes" class="form-control" rows="1" placeholder="Additional notes, directives, or special conditions..."></textarea>
                </div>
            </div>

            <div class="schedule-modal-footer" style="display: flex; justify-content: space-between; align-items: center; background: #F8FAFC; border-top: 1px solid #E2E8F0; padding: 1rem 1.5rem;">
                <div style="display: flex; gap: 0.5rem; align-items: center;">
                    <button type="button" class="btn-cancel" onclick="closeQuickMomModal()">Close</button>
                    <a id="btnOpenFullMeetingDoc" href="#" target="_blank" class="btn-cancel" style="display: inline-flex; align-items: center; gap: 0.4rem; text-decoration: none; color: #065F46; border-color: #A7F3D0; background: #F0FDF4;">
                        <i class="fa-solid fa-microphone-lines"></i> Open Speech Transcription & Full MOM
                    </a>
                </div>
                <button type="submit" class="btn-submit" id="btnApplyMomSubmit" style="background: linear-gradient(135deg, #059669, #047857); padding: 0.65rem 1.25rem; font-size: 0.92rem; font-weight: 700; border-radius: 8px;">
                    <i class="fa-solid fa-check-double"></i> Save MOM &amp; Apply Verdict
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ======================================================== -->
<!-- UNIVERSITY CONFIRMATION MODALS (CSU CEIT ACADEMIC THEME) -->
<!-- ======================================================== -->

<!-- 1. University Defense Notification Dispatch Modal -->
<div id="univReminderModal" class="modal" style="display:none;">
    <div class="modal-content logout-modal-content univ-modal-compact" style="max-width: 480px; width: 92%;">
        <div class="logout-modal-header" style="background: linear-gradient(135deg, #064E3B 0%, #047857 55%, #022C22 100%); border-bottom: 3px solid #10B981; padding: 1.5rem 1.25rem 1.25rem;">
            <div class="logout-seal-wrapper" style="box-shadow: 0 4px 14px rgba(0, 0, 0, 0.25), 0 0 0 2.5px rgba(16, 185, 129, 0.7); width: 64px; height: 64px; margin-bottom: 0.5rem;">
                <img src="logo.png" alt="CSU CEIT Seal">
            </div>
            <div class="logout-univ-subtitle" style="color: #A7F3D0; font-size: 0.7rem; letter-spacing: 1.2px;">Caraga State University</div>
            <div class="logout-college-subtitle" style="color: #D1FAE5; font-size: 0.75rem; margin-bottom: 0.35rem;">College of Engineering and Information Technology</div>
            <h2 style="font-size: 1.2rem; font-weight: 800; color: #FFFFFF; display: flex; align-items: center; justify-content: center; gap: 0.45rem;">
                <i class="fa-solid fa-paper-plane" style="color: #FCD34D;"></i> Defense Notification Dispatch
            </h2>
        </div>
        <div class="logout-modal-body" style="background: #FFFFFF; padding: 1.35rem 1.4rem;">
            <!-- Target Defense Schedule Card -->
            <div class="logout-user-card" style="display: flex; align-items: flex-start; background: #ECFDF5; border: 1px solid #A7F3D0; text-align: left; padding: 0.85rem 1rem; margin-bottom: 1rem; border-radius: 12px;">
                <div class="logout-user-avatar" style="background: linear-gradient(135deg, #059669 0%, #047857 100%); color: white; display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: 0 2px 6px rgba(5, 150, 105, 0.25); width: 40px; height: 40px; font-size: 1rem;">
                    <i class="fa-solid fa-bell"></i>
                </div>
                <div class="logout-user-details" style="flex: 1; min-width: 0; margin-left: 0.75rem;">
                    <div class="logout-user-name" id="urm_candidate" style="font-weight: 800; color: #065F46; line-height: 1.25; font-size: 0.95rem;">Candidate / Group</div>
                    <div id="urm_title" style="color: #047857; font-style: italic; font-size: 0.82rem; line-height: 1.35; margin-top: 2px;">"Defense Title"</div>
                    <div class="logout-user-role" id="urm_datetime" style="font-weight: 700; color: #059669; text-transform: none; letter-spacing: 0.2px; display: flex; align-items: center; gap: 0.35rem; margin-top: 0.35rem; font-size: 0.76rem;">
                        <i class="fa-regular fa-calendar-check"></i> <span id="urm_sched_info">Oct 23, 2026 • CEIT Hall</span>
                    </div>
                </div>
            </div>

            <!-- Recipients Breakdown Box -->
            <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 10px; padding: 0.75rem 0.95rem; margin-bottom: 1.15rem; text-align: left;">
                <div style="font-size: 0.7rem; font-weight: 800; text-transform: uppercase; color: #64748B; letter-spacing: 0.5px; margin-bottom: 0.45rem; display: flex; align-items: center; gap: 0.35rem;">
                    <i class="fa-solid fa-bullhorn" style="color: #047857;"></i> Broadcast Notification Recipients:
                </div>
                <div style="display: flex; flex-direction: column; gap: 0.4rem; font-size: 0.8rem; color: #334155;">
                    <div style="display: flex; align-items: center; gap: 0.45rem;">
                        <i class="fa-solid fa-circle-check" style="color: #10B981; font-size: 0.75rem;"></i>
                        <span><strong>Student Candidate & Group Team</strong></span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.45rem;">
                        <i class="fa-solid fa-circle-check" style="color: #10B981; font-size: 0.75rem;"></i>
                        <span><strong>Faculty Adviser:</strong> <span id="urm_adviser_name" style="color: #065F46; font-weight: 600;">Adviser</span></span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.45rem;">
                        <i class="fa-solid fa-circle-check" style="color: #10B981; font-size: 0.75rem;"></i>
                        <span><strong>Panel Committee:</strong> <span id="urm_chair_name" style="color: #065F46; font-weight: 600;">Panel Members</span></span>
                    </div>
                </div>
            </div>

            <div class="logout-prompt-title" style="font-weight: 700; color: #0F172A; font-size: 0.98rem; margin-bottom: 0.3rem;">
                Dispatch Official Reminders Now?
            </div>
            <p class="logout-prompt-desc" style="color: #64748B; font-size: 0.82rem; line-height: 1.45; margin: 0 0 1.25rem 0;">
                Instant in-app alerts and notifications will be sent directly to the student, adviser, and panel members with schedule venue, date, and defense stage guidelines.
            </p>

            <form method="POST" action="action.php" id="univReminderForm">
                <input type="hidden" name="send_defense_reminder" value="1">
                <input type="hidden" name="schedule_id" id="urm_schedule_id" value="0">
                <div class="logout-actions" style="display: flex; gap: 0.65rem;">
                    <button type="button" class="btn-logout-cancel" onclick="closeUnivReminderModal()" style="flex: 1; background: #F1F5F9; color: #475569; border: 1.5px solid #CBD5E1; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 0.45rem; border-radius: 10px; padding: 0.65rem 1rem; font-family: inherit; font-size: 0.85rem;">
                        <i class="fa-solid fa-arrow-left"></i> Cancel
                    </button>
                    <button type="submit" id="univ_reminder_submit_btn" style="flex: 1.35; background: linear-gradient(135deg, #059669 0%, #047857 100%); color: white; border: none; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 0.45rem; border-radius: 10px; padding: 0.65rem 1rem; box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3); font-family: inherit; font-size: 0.85rem; transition: all 0.2s ease;">
                        <i class="fa-solid fa-paper-plane"></i> Send Reminders Now
                    </button>
                </div>
            </form>

            <div class="logout-modal-footer-note" style="color: #94A3B8; font-size: 0.72rem; display: flex; align-items: center; justify-content: center; gap: 0.4rem; margin-top: 1rem; letter-spacing: 0.2px;">
                <i class="fa-solid fa-shield-halved"></i> Secured CEIT Academic Portal &bull; CSU Cabadbaran
            </div>
        </div>
    </div>
</div>

<!-- 2. University Cancel Schedule Confirmation Modal -->
<div id="univCancelScheduleModal" class="modal" style="display:none;">
    <div class="modal-content logout-modal-content univ-modal-compact" style="max-width: 460px; width: 92%;">
        <div class="logout-modal-header" style="background: linear-gradient(135deg, #7F1D1D 0%, #991B1B 55%, #450A0A 100%); border-bottom: 3px solid #EF4444; padding: 1.5rem 1.25rem 1.25rem;">
            <div class="logout-seal-wrapper" style="box-shadow: 0 4px 14px rgba(0, 0, 0, 0.25), 0 0 0 2.5px rgba(239, 68, 68, 0.7); width: 64px; height: 64px; margin-bottom: 0.5rem;">
                <img src="logo.png" alt="CSU Seal">
            </div>
            <div class="logout-univ-subtitle" style="color: #FECACA; font-size: 0.7rem; letter-spacing: 1.2px;">Caraga State University</div>
            <div class="logout-college-subtitle" style="color: #FEE2E2; font-size: 0.75rem; margin-bottom: 0.35rem;">College of Engineering and Information Technology</div>
            <h2 style="font-size: 1.2rem; font-weight: 800; color: #FFFFFF; display: flex; align-items: center; justify-content: center; gap: 0.45rem;">
                <i class="fa-solid fa-calendar-xmark" style="color: #FECACA;"></i> Cancel Defense Schedule
            </h2>
        </div>
        <div class="logout-modal-body" style="background: #FFFFFF; padding: 1.35rem 1.4rem;">
            <div class="logout-user-card" style="display: flex; align-items: flex-start; background: #FEF2F2; border: 1px solid #FECACA; text-align: left; padding: 0.85rem 1rem; margin-bottom: 1rem; border-radius: 12px;">
                <div class="logout-user-avatar" style="background: linear-gradient(135deg, #DC2626 0%, #991B1B 100%); color: white; display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: 0 2px 6px rgba(220, 38, 38, 0.25); width: 40px; height: 40px; font-size: 1rem;">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
                <div class="logout-user-details" style="flex: 1; min-width: 0; margin-left: 0.75rem;">
                    <div class="logout-user-name" id="ucm_candidate" style="font-weight: 800; color: #991B1B; line-height: 1.25; font-size: 0.95rem;">Candidate / Group</div>
                    <div id="ucm_title" style="color: #B91C1C; font-style: italic; font-size: 0.82rem; line-height: 1.35; margin-top: 2px;">"Defense Title"</div>
                    <div class="logout-user-role" style="font-weight: 700; color: #DC2626; text-transform: uppercase; letter-spacing: 0.3px; display: flex; align-items: center; gap: 0.35rem; margin-top: 0.35rem; font-size: 0.74rem;">
                        <span class="logout-session-dot" style="width: 6px; height: 6px; background: #DC2626; border-radius: 50%; display: inline-block; box-shadow: 0 0 0 2px rgba(220, 38, 38, 0.25);"></span>
                        <span>Irreversible Schedule Cancellation</span>
                    </div>
                </div>
            </div>

            <div class="logout-prompt-title" style="font-weight: 700; color: #991B1B; font-size: 0.98rem; margin-bottom: 0.3rem;">
                Remove Schedule from Calendar?
            </div>
            <p class="logout-prompt-desc" style="color: #64748B; font-size: 0.82rem; line-height: 1.45; margin: 0 0 1.25rem 0;">
                Are you sure you want to cancel and remove this defense schedule? This action <strong style="color: #DC2626;">cannot be undone</strong> and will clear panel assignments and venue reservations for this session.
            </p>

            <form method="POST" action="action.php" id="univCancelScheduleForm">
                <input type="hidden" name="delete_defense_schedule" value="1">
                <input type="hidden" name="schedule_id" id="ucm_schedule_id" value="0">
                <div class="logout-actions" style="display: flex; gap: 0.65rem;">
                    <button type="button" class="btn-logout-cancel" onclick="closeUnivCancelScheduleModal()" style="flex: 1; background: #F1F5F9; color: #475569; border: 1.5px solid #CBD5E1; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 0.45rem; border-radius: 10px; padding: 0.65rem 1rem; font-family: inherit; font-size: 0.85rem;">
                        <i class="fa-solid fa-arrow-left"></i> Cancel
                    </button>
                    <button type="submit" id="univ_cancel_sched_submit_btn" style="flex: 1.35; background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%); color: white; border: none; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 0.45rem; border-radius: 10px; padding: 0.65rem 1rem; box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3); font-family: inherit; font-size: 0.85rem; transition: all 0.2s ease;">
                        <i class="fa-solid fa-trash-can"></i> Yes, Cancel Schedule
                    </button>
                </div>
            </form>

            <div class="logout-modal-footer-note" style="color: #94A3B8; font-size: 0.72rem; display: flex; align-items: center; justify-content: center; gap: 0.4rem; margin-top: 1rem; letter-spacing: 0.2px;">
                <i class="fa-solid fa-shield-halved"></i> Secured CEIT Academic Portal &bull; CSU Cabadbaran
            </div>
        </div>
    </div>
</div>

<!-- Embed metadata for dynamic auto-population -->
<script>
function openUnivReminderModal(sched) {
    if (!sched) return;
    document.getElementById('urm_schedule_id').value = sched.id || 0;
    document.getElementById('urm_candidate').textContent = sched.candidate || 'Candidate / Group';
    document.getElementById('urm_title').textContent = '"' + (sched.title || 'Defense Title') + '"';
    document.getElementById('urm_sched_info').textContent = (sched.date || '') + ' • ' + (sched.time || '') + ' • ' + (sched.venue || 'CEIT Hall');
    document.getElementById('urm_adviser_name').textContent = sched.adviser || 'Assigned Adviser';
    document.getElementById('urm_chair_name').textContent = sched.chair ? ('Chair: ' + sched.chair) : 'Assigned Panel Members';

    const modal = document.getElementById('univReminderModal');
    if (modal) {
        modal.style.display = 'flex';
        modal.style.alignItems = 'center';
        modal.style.justifyContent = 'center';
    }
}

function closeUnivReminderModal() {
    const modal = document.getElementById('univReminderModal');
    if (modal) modal.style.display = 'none';
}

function openUnivCancelScheduleModal(sched) {
    if (!sched) return;
    document.getElementById('ucm_schedule_id').value = sched.id || 0;
    document.getElementById('ucm_candidate').textContent = sched.candidate || 'Candidate / Group';
    document.getElementById('ucm_title').textContent = '"' + (sched.title || 'Defense Title') + '"';

    const modal = document.getElementById('univCancelScheduleModal');
    if (modal) {
        modal.style.display = 'flex';
        modal.style.alignItems = 'center';
        modal.style.justifyContent = 'center';
    }
}

function closeUnivCancelScheduleModal() {
    const modal = document.getElementById('univCancelScheduleModal');
    if (modal) modal.style.display = 'none';
}

// Window click listener to close university modals on backdrop click
window.addEventListener('click', function(e) {
    const rModal = document.getElementById('univReminderModal');
    if (e.target === rModal) closeUnivReminderModal();
    const cModal = document.getElementById('univCancelScheduleModal');
    if (e.target === cModal) closeUnivCancelScheduleModal();
});

// Escape key listener
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeUnivReminderModal();
        closeUnivCancelScheduleModal();
    }
});
const studentsMetadata = <?php echo json_encode($students_metadata, JSON_UNESCAPED_UNICODE); ?>;

function syncCourseCodeOptions(preferredCode = null) {
    const deptSelect = document.getElementById('formDepartment');
    const dept = deptSelect ? deptSelect.value : 'Information Technology';
    const stageSelect = document.getElementById('formDefenseStage');
    const stage = stageSelect ? stageSelect.value : 'Final Oral Defense';
    const isProp = (stage === 'Title Proposal' || stage === 'Outline Defense' || stage === 'Pre-Oral Defense');
    
    let prefix = 'IT';
    const dLower = dept.toLowerCase();
    if (dLower.includes('computer')) prefix = 'CPE';
    else if (dLower.includes('electrical')) prefix = 'EE';
    
    const propCode = `${prefix} 198`;
    const finalCode = `${prefix} 199`;
    
    const courseCodeElem = document.getElementById('formCourseCode');
    if (courseCodeElem) {
        courseCodeElem.innerHTML = `
            <option value="${propCode}">${propCode} (Proposal Defense)</option>
            <option value="${finalCode}">${finalCode} (Final Defense)</option>
        `;
        if (preferredCode && (preferredCode === propCode || preferredCode === finalCode)) {
            courseCodeElem.value = preferredCode;
        } else if (isProp) {
            courseCodeElem.value = propCode;
        } else {
            courseCodeElem.value = finalCode;
        }
    }
    
    const catElem = document.getElementById('formDefenseCategory');
    if (catElem) {
        catElem.value = isProp ? 'Proposal Defense' : 'Final Defense';
    }
}

function onDepartmentFormChange(val) {
    syncCourseCodeOptions();
}

function onStudentSelectChange(studentId) {
    if (!studentId || !studentsMetadata[studentId]) {
        document.getElementById('candidateAutoInfo').style.display = 'none';
        return;
    }
    const meta = studentsMetadata[studentId];
    
    // Auto select department in form
    const deptSelect = document.getElementById('formDepartment');
    if (deptSelect && meta.department) {
        deptSelect.value = meta.department;
    }
    syncCourseCodeOptions();

    // Auto populate topic in title if empty or default
    const stageSelect = document.getElementById('formDefenseStage');
    const stage = stageSelect ? stageSelect.value : 'Final Oral Defense';
    const topic = meta.topic || 'Capstone Research Project';
    document.getElementById('formTitle').value = `${stage}: ${topic}`;

    // Auto select adviser if matched
    if (meta.adviser_id > 0) {
        const advSelect = document.getElementById('formAdviserSelect');
        if (advSelect) {
            advSelect.value = meta.adviser_id;
        }
    }

    // Pre-check panel members if already in thesis_panel
    if (meta.assigned_panel_ids && meta.assigned_panel_ids.length > 0) {
        meta.assigned_panel_ids.forEach(pid => {
            const chk = document.querySelector(`.panel-checkbox[value="${pid}"]`);
            if (chk) {
                chk.checked = true;
                highlightPanelCard(chk);
            }
        });
    }

    // Display info pill with course badge
    const infoDiv = document.getElementById('candidateAutoInfo');
    const shortCode = meta.dept_short || 'CEIT';
    infoDiv.innerHTML = `<i class="fa-solid fa-graduation-cap"></i> <span class="dp-tag tag-${shortCode.toLowerCase()}">${shortCode}</span> <strong>${meta.group_name ? 'Group ' + meta.group_name : meta.name}</strong> • ${meta.department || 'CEIT'} • Adviser: <em>${meta.adviser_name || 'Not yet matched'}</em>`;
    infoDiv.style.display = 'block';
}

function updateDefaultTitle() {
    const studentSelect = document.getElementById('formStudentSelect');
    const studentId = studentSelect ? studentSelect.value : '';
    const stage = document.getElementById('formDefenseStage').value;
    
    // Auto sync course and category based on defense stage and selected department
    syncCourseCodeOptions();

    if (studentId && studentsMetadata[studentId]) {
        const meta = studentsMetadata[studentId];
        const topic = meta.topic || 'Capstone Research Project';
        document.getElementById('formTitle').value = `${stage}: ${topic}`;
    }
}

function onCourseCodeChange(val) {
    const catElem = document.getElementById('formDefenseCategory');
    if (catElem) {
        catElem.value = val.includes('198') ? 'Proposal Defense' : 'Final Defense';
    }
}

function onDefenseCategoryChange(val) {
    const deptSelect = document.getElementById('formDepartment');
    const dept = deptSelect ? deptSelect.value : 'Information Technology';
    let prefix = 'IT';
    const dLower = dept.toLowerCase();
    if (dLower.includes('computer')) prefix = 'CPE';
    else if (dLower.includes('electrical')) prefix = 'EE';

    const courseCodeElem = document.getElementById('formCourseCode');
    if (courseCodeElem) {
        courseCodeElem.value = (val === 'Proposal Defense') ? `${prefix} 198` : `${prefix} 199`;
    }
}

function onDefenseDateChange(dateVal) {
    if (!dateVal) return;
    const d = new Date(dateVal);
    if (isNaN(d.getTime())) return;
    const year = d.getFullYear();
    const month = d.getMonth() + 1; // 1-12
    const ay = (month >= 8) ? `${year}-${year + 1}` : `${year - 1}-${year}`;
    const ayInput = document.getElementById('formAcademicYear');
    if (ayInput && !ayInput.value.trim()) {
        ayInput.value = ay;
    }
}

function highlightPanelCard(checkbox) {
    const card = checkbox.closest('.panel-check-card');
    if (card) {
        if (checkbox.checked) {
            card.classList.add('selected');
        } else {
            card.classList.remove('selected');
        }
    }
}

function openCreateScheduleModal() {
    document.getElementById('defenseScheduleForm').reset();
    document.getElementById('formScheduleId').value = "0";
    document.getElementById('scheduleModalTitle').innerText = "Create Defense Schedule";
    document.getElementById('modalHeaderIcon').className = "fa-solid fa-calendar-plus";
    document.getElementById('candidateAutoInfo').style.display = 'none';

    // Set default AY, Department, Course Code & Category
    const ayInput = document.getElementById('formAcademicYear');
    if (ayInput) ayInput.value = '<?php echo $curr_system_ay; ?>';
    const deptSelect = document.getElementById('formDepartment');
    if (deptSelect) deptSelect.value = 'Information Technology';
    syncCourseCodeOptions();

    // Clear all panel checkboxes
    document.querySelectorAll('.panel-checkbox').forEach(cb => {
        cb.checked = false;
        highlightPanelCard(cb);
    });

    // Reset MOM Documenter fields
    const momDocSelect = document.getElementById('formMomDocSelect');
    const momDocName = document.getElementById('formMomDocName');
    if (momDocSelect) momDocSelect.value = '';
    if (momDocName) {
        momDocName.value = '';
        momDocName.style.display = 'none';
    }

    document.getElementById('scheduleModal').style.display = 'flex';
}

function onMomDocSelectChange(selectElem) {
    const nameInput = document.getElementById('formMomDocName');
    if (!nameInput) return;
    if (selectElem.value === 'custom') {
        nameInput.style.display = 'block';
        nameInput.value = '';
        nameInput.focus();
    } else if (selectElem.value !== '') {
        nameInput.style.display = 'none';
        const opt = selectElem.options[selectElem.selectedIndex];
        nameInput.value = opt.getAttribute('data-name') || '';
    } else {
        nameInput.style.display = 'none';
        nameInput.value = '';
    }
}

function openEditScheduleModal(scheduleId) {
    // Fetch details via API
    fetch(`api.php?action=get_schedule_details&schedule_id=${scheduleId}`)
        .then(res => res.json())
        .then(data => {
            if (!data.success || !data.schedule) {
                alert('Could not load schedule details.');
                return;
            }
            const s = data.schedule;
            document.getElementById('formScheduleId').value = s.id;
            document.getElementById('scheduleModalTitle').innerText = "Edit Defense Schedule";
            document.getElementById('modalHeaderIcon').className = "fa-solid fa-pen-to-square";
            
            document.getElementById('formStudentSelect').value = s.student_id;
            onStudentSelectChange(s.student_id);

            document.getElementById('formDefenseStage').value = s.defense_stage;
            
            // Set Academic Year, Department, Course Code, and Defense Category
            const ayInput = document.getElementById('formAcademicYear');
            if (ayInput) {
                ayInput.value = s.academic_year || '<?php echo $curr_system_ay; ?>';
            }

            const deptSelect = document.getElementById('formDepartment');
            if (deptSelect) {
                deptSelect.value = s.department || s.student_department || 'Information Technology';
            }
            syncCourseCodeOptions(s.course_code);

            const catSelect = document.getElementById('formDefenseCategory');
            if (catSelect) {
                catSelect.value = s.defense_category || (s.defense_stage === 'Final Oral Defense' ? 'Final Defense' : 'Proposal Defense');
            }

            document.getElementById('formTitle').value = s.title;
            document.getElementById('formDate').value = s.meeting_date;
            document.getElementById('formStartTime').value = s.start_time ? s.start_time.substring(0, 5) : '09:00';
            document.getElementById('formEndTime').value = s.end_time ? s.end_time.substring(0, 5) : '11:00';
            document.getElementById('formVenue').value = s.venue || 'CEIT Defense Hall';
            document.getElementById('formMeetingLink').value = s.meeting_link || '';
            document.getElementById('formAdviserSelect').value = s.adviser_id || '';
            document.getElementById('formStatus').value = s.status || 'scheduled';
            document.getElementById('formNotes').value = s.notes || '';
            document.getElementById('formChairSelect').value = s.panel_chair_id || '';

            // Set MOM Documenter fields
            const momDocSelect = document.getElementById('formMomDocSelect');
            const momDocName = document.getElementById('formMomDocName');
            if (momDocSelect && momDocName) {
                if (s.mom_documenter_id && s.mom_documenter_id > 0) {
                    momDocSelect.value = s.mom_documenter_id;
                    momDocName.value = s.mom_documenter_name || '';
                    momDocName.style.display = 'none';
                } else if (s.mom_documenter_name) {
                    momDocSelect.value = 'custom';
                    momDocName.value = s.mom_documenter_name;
                    momDocName.style.display = 'block';
                } else {
                    momDocSelect.value = '';
                    momDocName.value = '';
                    momDocName.style.display = 'none';
                }
            }

            // Check panel checkboxes
            document.querySelectorAll('.panel-checkbox').forEach(cb => {
                cb.checked = false;
                highlightPanelCard(cb);
            });
            if (s.panel_member_ids && s.panel_member_ids.length > 0) {
                s.panel_member_ids.forEach(pid => {
                    const cb = document.querySelector(`.panel-checkbox[value="${pid}"]`);
                    if (cb) {
                        cb.checked = true;
                        highlightPanelCard(cb);
                    }
                });
            }

            document.getElementById('scheduleModal').style.display = 'flex';
        })
        .catch(err => {
            console.error(err);
            alert('Error fetching schedule details.');
        });
}

function closeScheduleModal() {
    document.getElementById('scheduleModal').style.display = 'none';
}

function openQuickMomModal(scheduleId) {
    fetch(`api.php?action=get_schedule_details&schedule_id=${scheduleId}`)
        .then(res => res.json())
        .then(data => {
            if (!data.success || !data.schedule) {
                alert('Could not load schedule and MOM details.');
                return;
            }
            const s = data.schedule;
            const mom = s.mom || {};

            document.getElementById('qmMid').value = s.id;
            document.getElementById('qmStudentId').value = s.student_id;
            document.getElementById('qmGroupId').value = s.group_id || '';
            document.getElementById('qmDefenseStage').value = s.defense_stage || 'Final Oral Defense';
            document.getElementById('qmMeetingDate').value = s.meeting_date || '';
            document.getElementById('qmVenue').value = s.venue || 'CEIT Defense Hall';

            // Candidate Summary Display
            const candName = s.group_name ? `Group ${s.group_name} (${s.student_name})` : s.student_name;
            document.getElementById('qmCandidateDisplay').innerText = candName;
            document.getElementById('qmStageDisplay').innerText = s.defense_stage;
            document.getElementById('qmDateVenueDisplay').innerText = `${s.meeting_date} • ${s.venue || 'CEIT Defense Hall'}`;

            // Form Fields
            document.getElementById('qmDocumentTitle').value = mom.document_title || `Oral Defense Minutes of the Meeting - ${s.title}`;
            
            // Documenter & Chair
            const docName = mom.recorded_by_name || s.mom_documenter_name || s.mom_documenter_user_name || 'Committee Documenter';
            document.getElementById('qmRecordedBy').value = docName;
            document.getElementById('qmApprovedByChair').value = mom.approved_by_chair || s.panel_chair_name || '';

            // Verdict & Grade
            document.getElementById('qmVerdict').value = mom.decision_verdict || 'Approved with Minor Revisions';
            document.getElementById('qmGradeAverage').value = mom.grade_average || '';
            document.getElementById('qmMomStatus').value = mom.status || 'finalized';

            // Discussion, Recommendations, Action Items, Notes
            document.getElementById('qmDiscussion').value = mom.discussion_summary || '';
            document.getElementById('qmRecommendations').value = mom.recommendations || '';
            document.getElementById('qmActionItems').value = mom.action_items || '';
            document.getElementById('qmNotes').value = mom.notes || '';

            // Full Meeting Doc link
            const fullLink = document.getElementById('btnOpenFullMeetingDoc');
            if (fullLink) {
                fullLink.href = `admin_dashboard.php?tab=meeting_doc&student_id=${s.student_id}&meeting_id=${s.id}`;
            }

            document.getElementById('quickMomModal').style.display = 'flex';
        })
        .catch(err => {
            console.error(err);
            alert('Error fetching MOM details.');
        });
}

function closeQuickMomModal() {
    document.getElementById('quickMomModal').style.display = 'none';
}

function submitQuickMomForm(event) {
    event.preventDefault();
    const btn = document.getElementById('btnApplyMomSubmit');
    const origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Applying MOM...';

    const formData = new FormData(document.getElementById('quickMomForm'));
    fetch('api.php?action=save_meeting_minutes', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = origHtml;

        if (data.success) {
            const meetingId = document.getElementById('qmMid').value;
            const verdict = document.getElementById('qmVerdict').value;
            const docName = document.getElementById('qmRecordedBy').value;

            // Live update the schedule card in the list
            const card = document.querySelector(`.sched-card[data-id="${meetingId}"]`);
            if (card) {
                let verdictTag = card.querySelector('.verdict-tag');
                if (!verdictTag) {
                    const badgeRow = card.querySelector('.sched-badges');
                    if (badgeRow) {
                        verdictTag = document.createElement('span');
                        verdictTag.className = 'verdict-tag';
                        badgeRow.appendChild(verdictTag);
                    }
                }
                if (verdictTag) {
                    verdictTag.innerHTML = `<i class="fa-solid fa-gavel"></i> ${verdict}`;
                }

                // Update documenter line on card
                const docLine = card.querySelector('.documenter-line');
                if (docLine) {
                    docLine.innerHTML = `<i class="fa-solid fa-pen-nib" style="color: #059669;"></i> <strong>MOM Documenter:</strong> <span class="badge" style="background:#ECFDF5; color:#047857; border:1px solid #A7F3D0; font-weight:700; padding:2px 7px; border-radius:6px;">${docName}</span>`;
                }
            }

            closeQuickMomModal();

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'MOM Applied Successfully!',
                    text: 'Minutes of the Meeting (MOM) and deliberation verdict successfully saved and applied.',
                    confirmButtonColor: '#059669'
                });
            } else {
                alert('Minutes of the Meeting (MOM) successfully applied and saved!');
            }
        } else {
            alert(data.error || 'Failed to apply MOM.');
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = origHtml;
        console.error(err);
        alert('An error occurred while saving and applying MOM.');
    });
}

function validateDefenseScheduleForm() {
    const studentId = document.getElementById('formStudentSelect').value;
    const chairId = document.getElementById('formChairSelect').value;
    const checkedPanels = document.querySelectorAll('.panel-checkbox:checked');
    
    if (!studentId) {
        alert('Please select an assigned student candidate or capstone group.');
        return false;
    }
    if (!chairId) {
        alert('Please designate a Panel Committee Chair.');
        return false;
    }
    // If no panel checkboxes were manually checked, auto-include the chair as committee panelist
    if (checkedPanels.length === 0) {
        const chairCheckbox = document.querySelector(`.panel-checkbox[value="${chairId}"]`);
        if (chairCheckbox) {
            chairCheckbox.checked = true;
            highlightPanelCard(chairCheckbox);
        }
    }
    return true;
}

// Filter variables
let currentStatusFilter = 'all';
let currentCategoryFilter = 'all';
let currentDegreeFilter = 'all';

function filterByDegreeProgram(deptCode, btn) {
    currentDegreeFilter = deptCode;
    document.querySelectorAll('.degree-pill-btn').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    
    const notice = document.getElementById('activeDegreeNotice');
    if (notice) {
        if (deptCode === 'all') {
            notice.innerHTML = '<i class="fa-solid fa-circle-check"></i> Showing All Programs';
            notice.className = 'degree-notice-badge';
        } else if (deptCode === 'BSIT') {
            notice.innerHTML = '<i class="fa-solid fa-laptop-code"></i> Filtered: BSIT (Information Technology)';
            notice.className = 'degree-notice-badge badge-bsit';
        } else if (deptCode === 'BSCpE') {
            notice.innerHTML = '<i class="fa-solid fa-microchip"></i> Filtered: BSCpE (Computer Engineering)';
            notice.className = 'degree-notice-badge badge-bscpe';
        } else if (deptCode === 'BSEE') {
            notice.innerHTML = '<i class="fa-solid fa-bolt"></i> Filtered: BSEE (Electrical Engineering)';
            notice.className = 'degree-notice-badge badge-bsee';
        }
    }
    applyFilters();
}

function filterByCategory(cat, btn) {
    currentCategoryFilter = cat;
    document.querySelectorAll('.cat-tab-btn').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    applyFilters();
}

function filterSchedules(status, btn) {
    currentStatusFilter = status;
    document.querySelectorAll('.filter-pill').forEach(p => p.classList.remove('active'));
    if (btn) btn.classList.add('active');
    applyFilters();
}

function onAyFilterInput() {
    const ayInput = document.getElementById('ayFilter');
    const clearBtn = document.getElementById('ayClearBtn');
    if (clearBtn && ayInput) {
        clearBtn.style.display = ayInput.value.trim() ? 'block' : 'none';
    }
    applyFilters();
}

function clearAyFilter() {
    const ayInput = document.getElementById('ayFilter');
    const clearBtn = document.getElementById('ayClearBtn');
    if (ayInput) {
        ayInput.value = '';
        ayInput.focus();
    }
    if (clearBtn) {
        clearBtn.style.display = 'none';
    }
    applyFilters();
}

function applyFilters() {
    const rawAy = (document.getElementById('ayFilter') ? document.getElementById('ayFilter').value : '').trim();
    const cleanAy = rawAy.replace(/^A\.?Y\.?\s*/i, '').trim().toLowerCase();
    const stage = document.getElementById('stageFilter').value;
    const term = (document.getElementById('scheduleSearchInput').value || '').toLowerCase().trim();
    const cards = document.querySelectorAll('.sched-card');

    let visible198 = 0;
    let visible199 = 0;

    cards.forEach(card => {
        const cardStatus = card.getAttribute('data-status');
        const cardStage = card.getAttribute('data-stage');
        const cardAy = card.getAttribute('data-ay') || '';
        const cardCourse = card.getAttribute('data-course') || '';
        const cardCat = card.getAttribute('data-category') || '';
        const cardDegree = card.getAttribute('data-degree-course') || '';
        const isUpcoming = card.getAttribute('data-is-upcoming') === '1';
        const searchText = card.getAttribute('data-search-text') || '';
        const isPropCard = (cardCourse.includes('198') || cardCat === 'Proposal Defense');

        // Degree Course filter (all, BSIT, BSCpE, BSEE)
        let degreeMatch = (currentDegreeFilter === 'all' || cardDegree === currentDegreeFilter);

        // Category filter (all, proposal/it198, final/it199)
        let catMatch = false;
        if (currentCategoryFilter === 'all') {
            catMatch = true;
        } else if (currentCategoryFilter === 'proposal' || currentCategoryFilter === 'it198') {
            catMatch = isPropCard;
        } else if (currentCategoryFilter === 'final' || currentCategoryFilter === 'it199') {
            catMatch = !isPropCard;
        }

        // Academic Year (A.Y.) filter (supports typing partial or full A.Y.)
        let ayMatch = true;
        if (cleanAy) {
            const cleanCardAy = (cardAy || '').replace(/^A\.?Y\.?\s*/i, '').trim().toLowerCase();
            ayMatch = (cleanCardAy.includes(cleanAy) || cleanAy.includes(cleanCardAy));
        }

        // Status filter
        let statusMatch = false;
        if (currentStatusFilter === 'all') {
            statusMatch = true;
        } else if (currentStatusFilter === 'upcoming') {
            statusMatch = isUpcoming;
        } else if (currentStatusFilter === 'in_progress') {
            statusMatch = (cardStatus === 'in_progress');
        } else if (currentStatusFilter === 'completed') {
            statusMatch = (cardStatus === 'completed');
        }

        let stageMatch = (!stage || cardStage === stage);
        let textMatch = (!term || searchText.includes(term));

        if (degreeMatch && catMatch && ayMatch && statusMatch && stageMatch && textMatch) {
            card.style.display = 'flex';
            if (isPropCard) {
                visible198++;
            } else {
                visible199++;
            }
        } else {
            card.style.display = 'none';
        }
    });

    // Update section visibility and counters
    const sec198 = document.getElementById('sectionIT198');
    const sec199 = document.getElementById('sectionIT199');
    const num198Elem = document.getElementById('numIT198');
    const num199Elem = document.getElementById('numIT199');
    const empty198 = document.getElementById('emptyNoticeIT198');
    const empty199 = document.getElementById('emptyNoticeIT199');

    if (num198Elem) num198Elem.innerText = visible198;
    if (num199Elem) num199Elem.innerText = visible199;

    if (sec198) {
        if (currentCategoryFilter === 'final' || currentCategoryFilter === 'it199') {
            sec198.style.display = 'none';
        } else {
            sec198.style.display = 'block';
            if (empty198) empty198.style.display = (visible198 === 0) ? 'block' : 'none';
        }
    }

    if (sec199) {
        if (currentCategoryFilter === 'proposal' || currentCategoryFilter === 'it198') {
            sec199.style.display = 'none';
        } else {
            sec199.style.display = 'block';
            if (empty199) empty199.style.display = (visible199 === 0) ? 'block' : 'none';
        }
    }
}

// Close modal on escape or background click
window.addEventListener('click', function(e) {
    const modal = document.getElementById('scheduleModal');
    if (e.target === modal) {
        closeScheduleModal();
    }
    const qmModal = document.getElementById('quickMomModal');
    if (e.target === qmModal) {
        closeQuickMomModal();
    }
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeScheduleModal();
        closeQuickMomModal();
    }
});
</script>

<style>
/* ── CSU CEIT Emerald Defense Schedules Theme ── */
.schedules-wrapper {
    display: flex;
    flex-direction: column;
    gap: 1.4rem;
}

.schedule-banner {
    background: linear-gradient(135deg, #064E3B 0%, #047857 55%, #022C22 100%);
    border-radius: 14px;
    padding: 1.6rem 2rem;
    color: white;
    box-shadow: 0 8px 24px -4px rgba(4, 120, 87, 0.35);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1.25rem;
    border: 1px solid #10B981;
}

.schedule-banner-content h1 {
    font-size: 1.65rem;
    font-weight: 800;
    margin: 0.35rem 0;
    color: #FFFFFF;
    letter-spacing: -0.3px;
}

.schedule-banner-content p {
    font-size: 0.92rem;
    color: #D1FAE5;
    margin: 0;
    max-width: 650px;
    line-height: 1.45;
}

.banner-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    background: rgba(16, 185, 129, 0.25);
    color: #A7F3D0;
    border: 1px solid rgba(16, 185, 129, 0.45);
    padding: 0.25rem 0.75rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.btn-create-defense {
    display: inline-flex;
    align-items: center;
    gap: 0.6rem;
    background: #F59E0B;
    color: #022C22;
    padding: 0.75rem 1.4rem;
    border-radius: 10px;
    font-size: 0.95rem;
    font-weight: 800;
    border: none;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(245, 158, 11, 0.4);
    transition: all 0.2s ease;
}

.btn-create-defense:hover {
    background: #D97706;
    color: #FFFFFF;
    transform: translateY(-1px);
}

/* Stats Metric Grid */
.schedule-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 1rem;
}

.sched-stat-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    padding: 1.15rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    transition: all 0.2s;
}

.sched-stat-card:hover {
    border-color: #CBD5E1;
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0,0,0,0.06);
}

.stat-icon-circle {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
    flex-shrink: 0;
}

.stat-icon-circle.blue { background: #EFF6FF; color: #2563EB; }
.stat-icon-circle.green { background: #ECFDF5; color: #059669; }
.stat-icon-circle.amber { background: #FEF3C7; color: #D97706; }
.stat-icon-circle.purple { background: #F3E8FF; color: #7C3AED; }

.stat-details .stat-num {
    font-size: 1.55rem;
    font-weight: 800;
    color: #0F172A;
    line-height: 1.1;
}

.stat-details .stat-lbl {
    font-size: 0.78rem;
    font-weight: 700;
    color: #64748B;
    margin-top: 0.2rem;
}

/* Filter Bar */
.sched-filter-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 14px;
    padding: 1.1rem 1.35rem;
    display: flex;
    flex-direction: column;
    gap: 0.9rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}

.filter-degree-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.85rem;
    padding-bottom: 0.85rem;
    border-bottom: 1.5px dashed #E2E8F0;
}

.degree-filter-left {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.degree-filter-label {
    font-size: 0.82rem;
    font-weight: 800;
    color: #334155;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}

.degree-filter-pills {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
}

.degree-pill-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    background: #F8FAFC;
    border: 1.5px solid #E2E8F0;
    color: #475569;
    padding: 0.42rem 0.85rem;
    border-radius: 999px;
    font-size: 0.8rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s ease;
}

.degree-pill-btn:hover {
    background: #F1F5F9;
    border-color: #CBD5E1;
    transform: translateY(-1px);
}

.degree-pill-btn.active {
    background: #0F172A;
    color: #FFFFFF;
    border-color: #0F172A;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.25);
}

.degree-pill-btn.btn-bsit.active {
    background: #047857;
    color: #FFFFFF;
    border-color: #047857;
    box-shadow: 0 4px 12px rgba(4, 120, 87, 0.3);
}

.degree-pill-btn.btn-bscpe.active {
    background: #1D4ED8;
    color: #FFFFFF;
    border-color: #1D4ED8;
    box-shadow: 0 4px 12px rgba(29, 78, 216, 0.3);
}

.degree-pill-btn.btn-bsee.active {
    background: #D97706;
    color: #FFFFFF;
    border-color: #D97706;
    box-shadow: 0 4px 12px rgba(217, 119, 6, 0.3);
}

.dp-tag {
    font-size: 0.68rem;
    font-weight: 800;
    padding: 1px 6px;
    border-radius: 5px;
    letter-spacing: 0.3px;
    display: inline-block;
}

.tag-bsit { background: #D1FAE5; color: #065F46; }
.tag-bscpe { background: #DBEAFE; color: #1E40AF; }
.tag-bsee { background: #FEF3C7; color: #92400E; }

.degree-pill-btn.active .dp-tag {
    background: rgba(255, 255, 255, 0.25);
    color: #FFFFFF;
}

.degree-notice-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.78rem;
    font-weight: 700;
    padding: 0.35rem 0.75rem;
    border-radius: 8px;
    background: #F1F5F9;
    color: #475569;
    border: 1px solid #E2E8F0;
}

.degree-notice-badge.badge-bsit {
    background: #ECFDF5;
    color: #047857;
    border-color: #A7F3D0;
}

.degree-notice-badge.badge-bscpe {
    background: #EFF6FF;
    color: #1D4ED8;
    border-color: #BFDBFE;
}

.degree-notice-badge.badge-bsee {
    background: #FFFBEB;
    color: #B45309;
    border-color: #FDE68A;
}

.filter-top-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.85rem;
    padding-bottom: 0.9rem;
    border-bottom: 1px solid #F1F5F9;
}

.category-tabs {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.cat-tab-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    background: #F8FAFC;
    border: 1.5px solid #E2E8F0;
    color: #475569;
    padding: 0.5rem 1rem;
    border-radius: 10px;
    font-size: 0.85rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s ease;
}

.cat-tab-btn:hover {
    background: #F1F5F9;
    border-color: #CBD5E1;
    color: #0F172A;
}

.cat-tab-btn.active {
    background: #022C22;
    color: #FFFFFF;
    border-color: #022C22;
    box-shadow: 0 4px 12px rgba(2, 44, 34, 0.2);
}

.cat-tab-btn.it198-tab.active {
    background: #D97706;
    color: #FFFFFF;
    border-color: #D97706;
    box-shadow: 0 4px 12px rgba(217, 119, 6, 0.25);
}

.cat-tab-btn.it199-tab.active {
    background: #047857;
    color: #FFFFFF;
    border-color: #047857;
    box-shadow: 0 4px 12px rgba(4, 120, 87, 0.25);
}

.ay-filter-wrapper {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    background: #F8FAFC;
    padding: 0.35rem 0.75rem;
    border-radius: 10px;
    border: 1.5px solid #E2E8F0;
}

.filter-label-ay {
    font-size: 0.82rem;
    font-weight: 700;
    color: #475569;
    margin: 0;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    white-space: nowrap;
}

.filter-label-ay i {
    color: #047857;
}

.filter-select-ay {
    border: 1px solid #CBD5E1;
    border-radius: 8px;
    padding: 0.35rem 0.75rem;
    font-size: 0.84rem;
    font-weight: 700;
    color: #0F172A;
    background: #FFFFFF;
    cursor: pointer;
}

.filter-bottom-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.85rem;
}

/* Category Sections (IT 198 vs IT 199) */
.defense-category-section {
    margin-bottom: 1.75rem;
}

.defense-section-banner {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
    padding: 1rem 1.35rem;
    border-radius: 12px;
    margin-bottom: 1rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}

.it198-banner {
    background: linear-gradient(135deg, #FEF3C7 0%, #FFFBEB 100%);
    border: 1.5px solid #FDE68A;
    border-left: 6px solid #D97706;
}

.it199-banner {
    background: linear-gradient(135deg, #D1FAE5 0%, #ECFDF5 100%);
    border: 1.5px solid #A7F3D0;
    border-left: 6px solid #059669;
}

.banner-title-group {
    display: flex;
    align-items: center;
    gap: 0.85rem;
}

.banner-code-badge {
    padding: 0.45rem 0.9rem;
    border-radius: 8px;
    font-size: 0.95rem;
    font-weight: 900;
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    letter-spacing: 0.5px;
}

.banner-code-badge.it198 {
    background: #D97706;
    color: #FFFFFF;
    box-shadow: 0 2px 6px rgba(217, 119, 6, 0.3);
}

.banner-code-badge.it199 {
    background: #047857;
    color: #FFFFFF;
    box-shadow: 0 2px 6px rgba(4, 120, 87, 0.3);
}

.section-title-heading {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 800;
    color: #0F172A;
    letter-spacing: -0.2px;
}

.section-title-sub {
    margin: 0.15rem 0 0 0;
    font-size: 0.8rem;
    color: #64748B;
    font-weight: 600;
}

.section-counter {
    font-size: 0.82rem;
    font-weight: 800;
    padding: 0.35rem 0.85rem;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}

.it198-counter {
    background: #FFFBEB;
    color: #92400E;
    border: 1px solid #FCD34D;
}

.it199-counter {
    background: #ECFDF5;
    color: #065F46;
    border: 1px solid #6EE7B7;
}

.section-cards-container {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.empty-section-notice {
    background: #F8FAFC;
    border: 2px dashed #E2E8F0;
    border-radius: 10px;
    padding: 2rem 1.5rem;
    text-align: center;
    color: #64748B;
    font-size: 0.9rem;
    font-weight: 600;
}

.empty-section-notice i {
    font-size: 1.3rem;
    margin-right: 0.4rem;
    color: #94A3B8;
}

/* Badges: Degree Course, Subject Course, Category, Academic Year */
.degree-pill {
    font-size: 0.74rem;
    font-weight: 800;
    padding: 0.2rem 0.65rem;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    letter-spacing: 0.3px;
    text-transform: uppercase;
}

.pill-bsit {
    background: #ECFDF5;
    color: #047857;
    border: 1.5px solid #A7F3D0;
}

.pill-bscpe {
    background: #EFF6FF;
    color: #1D4ED8;
    border: 1.5px solid #BFDBFE;
}

.pill-bsee {
    background: #FFFBEB;
    color: #B45309;
    border: 1.5px solid #FDE68A;
}

.course-pill {
    font-size: 0.74rem;
    font-weight: 800;
    padding: 0.2rem 0.65rem;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    letter-spacing: 0.3px;
}

.pill-it198 {
    background: #FEF3C7;
    color: #B45309;
    border: 1.5px solid #FDE68A;
}

.pill-it199 {
    background: #E0E7FF;
    color: #3730A3;
    border: 1.5px solid #C7D2FE;
}

.cat-pill {
    font-size: 0.74rem;
    font-weight: 800;
    padding: 0.2rem 0.65rem;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.pill-cat-proposal {
    background: #FFF7ED;
    color: #C2410C;
    border: 1.5px solid #FFEDD5;
}

.pill-cat-final {
    background: #ECFDF5;
    color: #047857;
    border: 1.5px solid #A7F3D0;
}

.ay-pill {
    background: #F1F5F9;
    color: #334155;
    border: 1.5px solid #CBD5E1;
    font-size: 0.74rem;
    font-weight: 800;
    padding: 0.2rem 0.65rem;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

/* Title Container & Captions */
.sched-title-container {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    margin-top: 0.2rem;
}

.sched-type-caption {
    font-size: 0.72rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.caption-it198 {
    color: #D97706;
}

.caption-it199 {
    color: #047857;
}

.card-it198 {
    border-left: 5px solid #D97706;
}

.card-it199 {
    border-left: 5px solid #059669;
}

.filter-pills {
    display: flex;
    gap: 0.4rem;
    flex-wrap: wrap;
}

.filter-pill {
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    color: #475569;
    padding: 0.4rem 0.85rem;
    border-radius: 999px;
    font-size: 0.82rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.15s ease;
}

.filter-pill:hover {
    border-color: #047857;
    color: #047857;
}

.filter-pill.active {
    background: #047857;
    color: #FFFFFF;
    border-color: #047857;
}

.filter-right {
    display: flex;
    gap: 0.65rem;
    align-items: center;
    flex-wrap: wrap;
}

.filter-select {
    padding: 0.45rem 0.85rem;
    border: 1.5px solid #CBD5E1;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 700;
    color: #0F172A;
    background: #FFFFFF;
    cursor: pointer;
}

.search-box-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.search-box-wrapper i {
    position: absolute;
    left: 0.85rem;
    color: #94A3B8;
    font-size: 0.85rem;
}

.search-input {
    padding: 0.45rem 0.85rem 0.45rem 2.2rem;
    border: 1.5px solid #CBD5E1;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 600;
    color: #0F172A;
    min-width: 250px;
    transition: border-color 0.2s;
}

.search-input:focus {
    outline: none;
    border-color: #047857;
    box-shadow: 0 0 0 3px rgba(4, 120, 87, 0.15);
}

/* Schedule Card */
.schedules-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.sched-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    display: flex;
    flex-direction: column;
    box-shadow: 0 2px 6px rgba(0,0,0,0.04);
    transition: all 0.2s ease;
    overflow: hidden;
}

.sched-card:hover {
    border-color: #CBD5E1;
    box-shadow: 0 8px 20px rgba(0,0,0,0.07);
}

.sched-card-header {
    background: #F8FAFC;
    border-bottom: 1px solid #F1F5F9;
    padding: 1rem 1.25rem;
    display: grid;
    grid-template-columns: auto 1fr auto;
    gap: 1.25rem;
    align-items: center;
}

@media (max-width: 768px) {
    .sched-card-header {
        grid-template-columns: 1fr;
    }
}

.sched-date-badge {
    background: #022C22;
    color: #FFFFFF;
    border-radius: 10px;
    padding: 0.5rem 0.85rem;
    text-align: center;
    min-width: 74px;
    border: 1px solid #047857;
}

.sched-date-badge.today-highlight {
    background: #DC2626;
    border-color: #EF4444;
    box-shadow: 0 0 10px rgba(220, 38, 38, 0.4);
}

.sched-date-badge .date-month {
    font-size: 0.72rem;
    font-weight: 800;
    text-transform: uppercase;
    color: #34D399;
}

.sched-date-badge.today-highlight .date-month {
    color: #FEF08A;
}

.sched-date-badge .date-day {
    font-size: 1.45rem;
    font-weight: 900;
    line-height: 1.1;
}

.sched-date-badge .date-year {
    font-size: 0.68rem;
    color: #94A3B8;
}

.sched-header-info {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.sched-badges-row {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
}

.stage-pill {
    font-size: 0.75rem;
    font-weight: 800;
    padding: 0.2rem 0.65rem;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.stage-final { background: #EFF6FF; color: #1D4ED8; border: 1px solid #BFDBFE; }
.stage-preoral { background: #F3E8FF; color: #7C3AED; border: 1px solid #DDD6FE; }
.stage-outline { background: #FEF3C7; color: #B45309; border: 1px solid #FDE68A; }
.stage-title { background: #ECFDF5; color: #047857; border: 1px solid #A7F3D0; }

.status-pill {
    font-size: 0.72rem;
    font-weight: 800;
    padding: 0.2rem 0.6rem;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.status-pill.scheduled { background: #EFF6FF; color: #1E40AF; }
.status-pill.today { background: #FEF3C7; color: #B45309; animation: pulseToday 2s infinite; }
.status-pill.in-progress { background: #DC2626; color: #FFFFFF; font-weight: 800; }
.status-pill.completed { background: #ECFDF5; color: #047857; }
.status-pill.archived { background: #F1F5F9; color: #64748B; }

.verdict-tag {
    background: #022C22;
    color: #34D399;
    font-size: 0.72rem;
    font-weight: 800;
    padding: 0.2rem 0.6rem;
    border-radius: 999px;
    border: 1px solid #10B981;
}

.sched-title {
    font-size: 1.05rem;
    font-weight: 800;
    color: #0F172A;
    margin: 0;
    line-height: 1.4;
}

.sched-header-timing {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    text-align: right;
    font-size: 0.82rem;
    color: #475569;
}

@media (max-width: 768px) {
    .sched-header-timing {
        text-align: left;
    }
}

.timing-row {
    display: flex;
    align-items: center;
    gap: 0.45rem;
}

.timing-row i {
    color: #047857;
}

.virtual-link-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.75rem;
    font-weight: 700;
    background: #0284C7;
    color: white;
    padding: 0.25rem 0.65rem;
    border-radius: 6px;
    text-decoration: none;
    margin-top: 0.25rem;
    width: fit-content;
}

.virtual-link-btn:hover {
    background: #0369A1;
}

/* Card Body Grid */
.sched-card-body {
    padding: 1.15rem 1.25rem;
    display: grid;
    grid-template-columns: 1.2fr 1fr 1.4fr;
    gap: 1.25rem;
    border-bottom: 1px solid #F1F5F9;
}

@media (max-width: 900px) {
    .sched-card-body {
        grid-template-columns: 1fr;
    }
}

.sched-party-box {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.party-label {
    font-size: 0.72rem;
    font-weight: 800;
    color: #64748B;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.party-main-name {
    font-size: 0.95rem;
    color: #0F172A;
}

.party-sub {
    font-size: 0.78rem;
    color: #64748B;
    font-weight: normal;
}

.party-sub-detail {
    font-size: 0.82rem;
    color: #475569;
}

.party-topic-text {
    font-size: 0.8rem;
    color: #047857;
    font-style: italic;
    margin-top: 0.15rem;
    line-height: 1.35;
}

.badge-role-pill {
    display: inline-block;
    padding: 0.15rem 0.55rem;
    border-radius: 4px;
    font-size: 0.72rem;
    font-weight: 700;
}

.badge-role-pill.adviser { background: #ECFDF5; color: #047857; border: 1px solid #A7F3D0; }

.chair-line {
    font-size: 0.86rem;
    color: #0F172A;
    background: #FEF3C7;
    border: 1px solid #FDE68A;
    padding: 0.25rem 0.6rem;
    border-radius: 6px;
    width: fit-content;
    margin-bottom: 0.3rem;
}

.panel-chips-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}

.panel-member-chip {
    background: #F1F5F9;
    border: 1px solid #CBD5E1;
    color: #334155;
    padding: 0.2rem 0.55rem;
    border-radius: 999px;
    font-size: 0.76rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}

/* Notes Bar */
.sched-notes-bar {
    background: #F0FDF4;
    border-left: 3px solid #10B981;
    padding: 0.5rem 1.25rem;
    font-size: 0.82rem;
    color: #065F46;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* Card Footer */
.sched-card-footer {
    padding: 0.75rem 1.25rem;
    background: #FFFFFF;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.footer-meta {
    font-size: 0.78rem;
    color: #64748B;
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.footer-buttons {
    display: flex;
    gap: 0.45rem;
    align-items: center;
    flex-wrap: wrap;
}

.btn-action-outline {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.35rem 0.75rem;
    border-radius: 7px;
    font-size: 0.8rem;
    font-weight: 700;
    cursor: pointer;
    border: 1px solid #CBD5E1;
    background: #FFFFFF;
    color: #334155;
    text-decoration: none;
    transition: all 0.15s;
}

.btn-action-outline:hover {
    background: #F8FAFC;
    border-color: #94A3B8;
}

.btn-action-outline.view-doc {
    background: #047857;
    color: #FFFFFF;
    border-color: #047857;
}

.btn-action-outline.view-doc:hover {
    background: #065F46;
}

.btn-action-outline.notify {
    background: #EFF6FF;
    color: #1D4ED8;
    border-color: #BFDBFE;
}

.btn-action-outline.notify:hover {
    background: #DBEAFE;
}

.btn-action-outline.delete:hover {
    color: #DC2626;
    background: #FEE2E2;
    border-color: #FCA5A5;
}

/* Empty State */
.empty-state-box {
    background: #FFFFFF;
    border: 2px dashed #CBD5E1;
    border-radius: 12px;
    padding: 3rem 1.5rem;
    text-align: center;
}

.empty-icon {
    font-size: 3.5rem;
    color: #94A3B8;
    margin-bottom: 0.85rem;
}

.empty-state-box h3 {
    font-size: 1.2rem;
    font-weight: 800;
    color: #1E293B;
    margin: 0 0 0.4rem 0;
}

.empty-state-box p {
    font-size: 0.88rem;
    color: #64748B;
    margin: 0;
}

/* Modal Styling */
.schedule-modal {
    position: fixed;
    inset: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(15, 23, 42, 0.65);
    backdrop-filter: blur(5px);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.5rem 1rem;
    box-sizing: border-box;
}

.schedule-modal-content {
    background: #FFFFFF;
    border-radius: 16px;
    width: 100%;
    max-width: 820px;
    max-height: calc(100vh - 2.5rem);
    height: auto;
    display: flex;
    flex-direction: column;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
    border: 1px solid #E2E8F0;
    overflow: hidden; /* Keeps child elements neatly clipped with rounded borders */
}

#defenseScheduleForm {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-height: 0;
    overflow: hidden;
    margin: 0;
}

.schedule-modal-header {
    padding: 1.1rem 1.5rem;
    border-bottom: 1px solid #E2E8F0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #F8FAFC;
    flex-shrink: 0;
}

.modal-icon-badge {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    background: #ECFDF5;
    color: #047857;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.modal-close-btn {
    background: transparent;
    border: none;
    font-size: 1.75rem;
    color: #94A3B8;
    cursor: pointer;
    line-height: 1;
    padding: 0.25rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    transition: all 0.15s;
}

.modal-close-btn:hover {
    color: #0F172A;
    background: #E2E8F0;
}

.schedule-modal-body {
    padding: 1.25rem 1.5rem;
    overflow-y: auto;
    flex: 1;
    min-height: 0;
    display: flex;
    flex-direction: column;
    gap: 1rem;
    box-sizing: border-box;
}

/* Custom sleek scrollbar for modal body */
.schedule-modal-body::-webkit-scrollbar {
    width: 7px;
}
.schedule-modal-body::-webkit-scrollbar-track {
    background: #F1F5F9;
    border-radius: 4px;
}
.schedule-modal-body::-webkit-scrollbar-thumb {
    background: #CBD5E1;
    border-radius: 4px;
}
.schedule-modal-body::-webkit-scrollbar-thumb:hover {
    background: #94A3B8;
}

.form-row {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.form-row-3 {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 1rem;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}

.form-group.flex-1 { flex: 1; min-width: 200px; }
.form-group.flex-2 { flex: 2; min-width: 260px; }

.form-label {
    font-size: 0.82rem;
    font-weight: 700;
    color: #0F172A;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.form-label i {
    color: #047857;
}

.form-control {
    width: 100%;
    box-sizing: border-box;
    padding: 0.6rem 0.8rem;
    border: 1.5px solid #CBD5E1;
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 600;
    color: #0F172A;
    background: #FFFFFF;
    transition: all 0.2s;
}

.form-control:focus {
    outline: none;
    border-color: #047857;
    box-shadow: 0 0 0 3px rgba(4, 120, 87, 0.15);
}

.auto-info-pill {
    font-size: 0.78rem;
    background: #ECFDF5;
    border: 1px solid #A7F3D0;
    color: #065F46;
    padding: 0.35rem 0.65rem;
    border-radius: 6px;
    margin-top: 0.25rem;
}

.committee-select-box {
    background: #F8FAFC;
    border: 1.5px solid #CBD5E1;
    border-radius: 10px;
    padding: 1rem 1.15rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    box-sizing: border-box;
}

.committee-header {
    font-size: 0.85rem;
    font-weight: 800;
    color: #064E3B;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: flex;
    align-items: center;
    gap: 0.45rem;
}

.panel-checkbox-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 0.65rem;
    max-height: 160px;
    overflow-y: auto;
    padding-right: 0.35rem;
}

.panel-checkbox-grid::-webkit-scrollbar {
    width: 5px;
}
.panel-checkbox-grid::-webkit-scrollbar-track {
    background: #E2E8F0;
    border-radius: 4px;
}
.panel-checkbox-grid::-webkit-scrollbar-thumb {
    background: #94A3B8;
    border-radius: 4px;
}

.panel-check-card {
    border: 1.5px solid #CBD5E1;
    border-radius: 8px;
    padding: 0.5rem 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.6rem;
    background: #FFFFFF;
    cursor: pointer;
    transition: all 0.15s;
    user-select: none;
}

.panel-check-card:hover {
    border-color: #047857;
}

.panel-check-card.selected {
    border-color: #047857;
    background: #ECFDF5;
}

.panel-card-text {
    display: flex;
    flex-direction: column;
}

.panel-card-text strong {
    font-size: 0.83rem;
    color: #0F172A;
}

.panel-card-text span {
    font-size: 0.72rem;
    color: #64748B;
}

.notif-toggle-box {
    background: #F0FDF4;
    border: 1px solid #A7F3D0;
    border-radius: 8px;
    padding: 0.75rem 1rem;
    box-sizing: border-box;
}

.schedule-modal-footer {
    padding: 0.9rem 1.5rem;
    border-top: 1px solid #E2E8F0;
    background: #F8FAFC;
    flex-shrink: 0;
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 0.75rem;
    border-radius: 0 0 16px 16px;
    box-sizing: border-box;
}

.btn-cancel {
    padding: 0.6rem 1.25rem;
    border-radius: 8px;
    font-size: 0.88rem;
    font-weight: 700;
    border: 1px solid #CBD5E1;
    background: #FFFFFF;
    color: #475569;
    cursor: pointer;
    transition: all 0.15s;
}

.btn-cancel:hover {
    background: #F1F5F9;
    color: #0F172A;
}

.btn-submit {
    padding: 0.6rem 1.5rem;
    border-radius: 8px;
    font-size: 0.88rem;
    font-weight: 800;
    border: none;
    background: linear-gradient(135deg, #059669, #047857);
    color: #FFFFFF;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(4, 120, 87, 0.35);
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.15s;
}

.btn-submit:hover {
    background: #047857;
    box-shadow: 0 6px 16px rgba(4, 120, 87, 0.45);
}

@media (max-width: 768px) {
    .schedule-modal {
        padding: 0.5rem;
    }
    .schedule-modal-content {
        max-height: 98vh;
        border-radius: 12px;
    }
    .schedule-modal-header,
    .schedule-modal-body,
    .schedule-modal-footer {
        padding: 0.85rem 1rem;
    }
    .form-row {
        flex-direction: column;
        gap: 0.75rem;
    }
    .form-row-3 {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }
    .form-group.flex-1,
    .form-group.flex-2 {
        min-width: 100%;
    }
    .panel-checkbox-grid {
        grid-template-columns: 1fr;
    }
}

@keyframes pulseToday {
    0% { transform: scale(1); }
    50% { transform: scale(1.03); }
    100% { transform: scale(1); }
}
</style>
