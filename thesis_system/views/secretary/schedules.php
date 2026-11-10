<?php
// SECRETARY DEFENSE SCHEDULES VIEW
// Displays all defense sessions with filter tabs and quick-action buttons

$current_uid = (int)$_SESSION['user_id'];
$current_name = $_SESSION['name'] ?? '';
$today_str = date('Y-m-d');

// Fetch all defense meetings with related data
$schedules_query = $conn->query("
    SELECT dm.*, 
           u.name as student_name, u.email as student_email,
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
$today_count = 0;
$completed_count = 0;
$assigned_to_me_count = 0;

$course_counts = ['BSIT' => 0, 'BSCpE' => 0, 'BSEE' => 0];

if ($schedules_query) {
    while ($row = $schedules_query->fetch_assoc()) {
        // Resolve Degree Course (Department)
        $raw_dept = !empty($row['department']) ? $row['department'] : (!empty($row['student_dept']) ? $row['student_dept'] : 'Information Technology');
        $dept_norm = function_exists('normalize_course_name') ? normalize_course_name($raw_dept) : 'Information Technology';
        $dept_code = function_exists('get_course_short_code') ? get_course_short_code($dept_norm) : 'BSIT';

        // Resolve Academic Year, Course Code, and Defense Category
        $row_ay = !empty($row['academic_year']) ? $row['academic_year'] : (function_exists('calculate_academic_year') ? calculate_academic_year($row['meeting_date']) : '2026-2027');
        $is_prop = in_array($row['defense_stage'], ['Title Proposal', 'Outline Defense', 'Pre-Oral Defense']);
        
        $row_cc = !empty($row['course_code']) ? $row['course_code'] : (function_exists('resolve_course_code') ? resolve_course_code($row['defense_stage'], '', $dept_norm) : ($is_prop ? 'IT 198' : 'IT 199'));
        $row_cat = !empty($row['defense_category']) ? $row['defense_category'] : ($is_prop ? 'Proposal Defense' : 'Final Defense');

        $row['academic_year'] = $row_ay;
        $row['course_code'] = $row_cc;
        $row['defense_category'] = $row_cat;
        $row['dept_norm'] = $dept_norm;
        $row['dept_code'] = $dept_code;

        $all_schedules[] = $row;
        $total_count++;

        if (isset($course_counts[$dept_code])) {
            $course_counts[$dept_code]++;
        } else {
            $course_counts['BSIT']++;
        }

        if ($row['meeting_date'] === $today_str) $today_count++;
        if ($row['status'] === 'completed') $completed_count++;
        elseif ($row['meeting_date'] >= $today_str && $row['status'] === 'scheduled') $upcoming_count++;

        // Check if assigned to me
        if ((int)$row['mom_documenter_id'] === $current_uid || $row['mom_documenter_name'] === $current_name) {
            $assigned_to_me_count++;
        }
    }
}

// Helper: decode panel members
function sec_decode_panel_members($pm) {
    if (empty($pm)) return [];
    $decoded = json_decode($pm, true);
    if (is_array($decoded)) return $decoded;
    return array_map('trim', explode(',', $pm));
}
?>

<style>
/* Secretary Schedules Specific Styles */
.sec-sched-banner {
    background: linear-gradient(135deg, #047857 0%, #065F46 50%, #064E3B 100%);
    border-radius: 16px;
    padding: 2rem 2.5rem;
    margin: 1.5rem 2rem 1.25rem;
    color: white;
    position: relative;
    overflow: hidden;
}
.sec-sched-banner::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 300px;
    height: 300px;
    background: rgba(255,255,255,0.05);
    border-radius: 50%;
}
.sec-sched-banner h1 {
    font-size: 1.6rem;
    font-weight: 800;
    margin-bottom: 0.35rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.sec-sched-banner p {
    font-size: 0.9rem;
    opacity: 0.85;
    margin: 0;
}

.sec-stats-row {
    display: flex;
    gap: 1rem;
    margin: 0 2rem 1.25rem;
    flex-wrap: wrap;
}
.sec-stat-card {
    flex: 1;
    min-width: 140px;
    background: white;
    border-radius: 12px;
    padding: 1rem 1.25rem;
    border: 1px solid #E2E8F0;
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
    overflow: hidden;
}
.sec-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}
.sec-stat-card.active {
    border-color: #047857;
    box-shadow: 0 0 0 2px rgba(4,120,87,0.15);
}
.sec-stat-card .stat-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    margin-bottom: 0.5rem;
}
.sec-stat-card .stat-count {
    font-size: 1.5rem;
    font-weight: 800;
    color: #1E293B;
    line-height: 1;
}
.sec-stat-card .stat-label {
    font-size: 0.75rem;
    color: #64748B;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 0.25rem;
}

/* Schedule Cards */
.sec-schedules-grid {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin: 0 2rem 2rem;
}
.sec-sched-item {
    background: white;
    border-radius: 14px;
    border: 1px solid #E2E8F0;
    overflow: hidden;
    transition: all 0.2s ease;
}
.sec-sched-item:hover {
    box-shadow: 0 4px 16px rgba(0,0,0,0.07);
    border-color: #CBD5E1;
}
.sec-sched-item.highlight-mine {
    border-left: 4px solid #047857;
}
.sec-sched-top {
    display: flex;
    align-items: stretch;
    gap: 0;
}
.sec-date-column {
    width: 80px;
    min-width: 80px;
    background: linear-gradient(180deg, #F0FDF4 0%, #ECFDF5 100%);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 1rem 0.5rem;
    border-right: 1px solid #E2E8F0;
}
.sec-date-column .date-month {
    font-size: 0.7rem;
    font-weight: 800;
    text-transform: uppercase;
    color: #047857;
    letter-spacing: 1px;
}
.sec-date-column .date-day {
    font-size: 1.6rem;
    font-weight: 900;
    color: #065F46;
    line-height: 1.1;
}
.sec-date-column .date-year {
    font-size: 0.65rem;
    color: #64748B;
    font-weight: 600;
}
.sec-date-column.is-today {
    background: linear-gradient(180deg, #047857, #065F46);
}
.sec-date-column.is-today .date-month,
.sec-date-column.is-today .date-day,
.sec-date-column.is-today .date-year {
    color: white;
}

.sec-info-column {
    flex: 1;
    padding: 1rem 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.sec-info-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    align-items: center;
}
.sec-badge {
    font-size: 0.68rem;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}
.sec-badge.badge-stage {
    background: #EFF6FF;
    color: #1D4ED8;
    border: 1px solid #BFDBFE;
}
.sec-badge.badge-bsit {
    background: #ECFDF5;
    color: #047857;
    border: 1px solid #A7F3D0;
}
.sec-badge.badge-bscpe {
    background: #EFF6FF;
    color: #1D4ED8;
    border: 1px solid #BFDBFE;
}
.sec-badge.badge-bsee {
    background: #FFFBEB;
    color: #B45309;
    border: 1px solid #FDE68A;
}
.sec-course-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    background: #F8FAFC;
    border: 1.5px solid #E2E8F0;
    color: #475569;
    padding: 0.38rem 0.8rem;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s ease;
}
.sec-course-pill:hover {
    background: #F1F5F9;
    border-color: #CBD5E1;
}
.sec-course-pill.active {
    background: #0F172A;
    color: white;
    border-color: #0F172A;
    box-shadow: 0 4px 10px rgba(15, 23, 42, 0.2);
}
.sec-course-pill.pill-bsit-btn.active {
    background: #047857;
    color: white;
    border-color: #047857;
    box-shadow: 0 4px 10px rgba(4, 120, 87, 0.25);
}
.sec-course-pill.pill-bscpe-btn.active {
    background: #1D4ED8;
    color: white;
    border-color: #1D4ED8;
    box-shadow: 0 4px 10px rgba(29, 78, 216, 0.25);
}
.sec-course-pill.pill-bsee-btn.active {
    background: #D97706;
    color: white;
    border-color: #D97706;
    box-shadow: 0 4px 10px rgba(217, 119, 6, 0.25);
}
.dp-tag {
    font-size: 0.65rem;
    font-weight: 800;
    padding: 1px 5px;
    border-radius: 4px;
    letter-spacing: 0.3px;
}
.tag-bsit { background: #D1FAE5; color: #065F46; }
.tag-bscpe { background: #DBEAFE; color: #1E40AF; }
.tag-bsee { background: #FEF3C7; color: #92400E; }
.sec-course-pill.active .dp-tag {
    background: rgba(255, 255, 255, 0.25);
    color: #FFFFFF;
}
.sec-badge.badge-course-198 {
    background: #FEF3C7;
    color: #92400E;
    border: 1px solid #FDE68A;
}
.sec-badge.badge-course-199 {
    background: #EDE9FE;
    color: #6D28D9;
    border: 1px solid #C4B5FD;
}
.sec-badge.badge-status-scheduled {
    background: #DBEAFE;
    color: #1E40AF;
}
.sec-badge.badge-status-progress {
    background: #FEF3C7;
    color: #B45309;
    animation: pulse 2s infinite;
}
.sec-badge.badge-status-completed {
    background: #D1FAE5;
    color: #065F46;
}
.sec-badge.badge-status-today {
    background: #FF6B35;
    color: white;
}
.sec-badge.badge-verdict {
    background: #ECFDF5;
    color: #047857;
    border: 1px solid #A7F3D0;
}
.sec-badge.badge-mine {
    background: linear-gradient(135deg, #047857, #065F46);
    color: white;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

.sec-info-title {
    font-size: 1rem;
    font-weight: 700;
    color: #1E293B;
    margin: 0;
    line-height: 1.3;
}
.sec-info-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    font-size: 0.8rem;
    color: #64748B;
}
.sec-info-meta span {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}
.sec-info-meta i {
    font-size: 0.7rem;
    color: #94A3B8;
}

/* People row */
.sec-people-row {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    padding: 0.75rem 1.25rem;
    background: #F8FAFC;
    border-top: 1px solid #F1F5F9;
    font-size: 0.8rem;
}
.sec-person-block {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.sec-person-block .person-label {
    font-weight: 700;
    color: #475569;
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}
.sec-person-block .person-name {
    color: #1E293B;
    font-weight: 600;
}
.sec-person-sep {
    width: 1px;
    height: 16px;
    background: #CBD5E1;
}

/* Actions row */
.sec-actions-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.65rem 1.25rem;
    background: #FAFBFC;
    border-top: 1px solid #F1F5F9;
    gap: 0.5rem;
    flex-wrap: wrap;
}
.sec-actions-left {
    display: flex;
    gap: 0.4rem;
    font-size: 0.78rem;
    color: #64748B;
    align-items: center;
}
.sec-actions-right {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}
.sec-action-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.4rem 0.85rem;
    border-radius: 8px;
    font-size: 0.78rem;
    font-weight: 700;
    border: 1px solid #E2E8F0;
    background: white;
    color: #475569;
    cursor: pointer;
    transition: all 0.15s ease;
    text-decoration: none;
}
.sec-action-btn:hover {
    background: #F8FAFC;
    border-color: #CBD5E1;
    transform: translateY(-1px);
}
.sec-action-btn.btn-mom {
    background: #ECFDF5;
    color: #047857;
    border-color: #A7F3D0;
}
.sec-action-btn.btn-mom:hover {
    background: #D1FAE5;
}
.sec-action-btn.btn-transcribe {
    background: #EFF6FF;
    color: #1D4ED8;
    border-color: #BFDBFE;
}
.sec-action-btn.btn-transcribe:hover {
    background: #DBEAFE;
}

/* Empty state */
.sec-empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: #94A3B8;
}
.sec-empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    display: block;
    color: #CBD5E1;
}
.sec-empty-state h3 {
    color: #475569;
    margin-bottom: 0.5rem;
}

/* Search bar */
.sec-search-bar {
    margin: 0 2rem 1rem;
    position: relative;
}
.sec-search-bar input {
    width: 100%;
    padding: 0.75rem 1rem 0.75rem 2.5rem;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    font-family: 'Inter', sans-serif;
    font-size: 0.85rem;
    background: white;
    transition: all 0.2s ease;
}
.sec-search-bar input:focus {
    outline: none;
    border-color: #047857;
    box-shadow: 0 0 0 3px rgba(4,120,87,0.1);
}
.sec-search-bar i {
    position: absolute;
    left: 0.85rem;
    top: 50%;
    transform: translateY(-50%);
    color: #94A3B8;
    font-size: 0.85rem;
}

/* Responsive */
@media (max-width: 768px) {
    .sec-sched-banner { margin: 1rem; padding: 1.5rem; }
    .sec-stats-row { margin: 0 1rem 1rem; }
    .sec-schedules-grid { margin: 0 1rem 1.5rem; }
    .sec-search-bar { margin: 0 1rem 1rem; }
    .sec-sched-top { flex-direction: column; }
    .sec-date-column { width: 100%; flex-direction: row; gap: 0.5rem; padding: 0.5rem 1rem; border-right: none; border-bottom: 1px solid #E2E8F0; }
    .sec-people-row { flex-direction: column; gap: 0.5rem; }
}
</style>

<!-- Banner -->
<div class="sec-sched-banner">
    <h1><i class="fa-solid fa-calendar-days"></i> Defense Schedules</h1>
    <p>View, monitor, and manage all defense sessions. Record MOM and apply deliberation verdicts.</p>
</div>

<!-- Stats Row (Filter Tabs) -->
<div class="sec-stats-row">
    <div class="sec-stat-card active" data-filter="all" onclick="filterSchedules('all', this)">
        <div class="stat-icon" style="background: #F0FDF4; color: #047857;"><i class="fa-solid fa-layer-group"></i></div>
        <div class="stat-count"><?php echo $total_count; ?></div>
        <div class="stat-label">All Defenses</div>
    </div>
    <div class="sec-stat-card" data-filter="upcoming" onclick="filterSchedules('upcoming', this)">
        <div class="stat-icon" style="background: #DBEAFE; color: #1D4ED8;"><i class="fa-solid fa-clock"></i></div>
        <div class="stat-count"><?php echo $upcoming_count; ?></div>
        <div class="stat-label">Upcoming</div>
    </div>
    <div class="sec-stat-card" data-filter="today" onclick="filterSchedules('today', this)">
        <div class="stat-icon" style="background: #FEF3C7; color: #D97706;"><i class="fa-solid fa-bolt"></i></div>
        <div class="stat-count"><?php echo $today_count; ?></div>
        <div class="stat-label">Today</div>
    </div>
    <div class="sec-stat-card" data-filter="completed" onclick="filterSchedules('completed', this)">
        <div class="stat-icon" style="background: #D1FAE5; color: #065F46;"><i class="fa-solid fa-circle-check"></i></div>
        <div class="stat-count"><?php echo $completed_count; ?></div>
        <div class="stat-label">Completed</div>
    </div>
    <div class="sec-stat-card" data-filter="mine" onclick="filterSchedules('mine', this)">
        <div class="stat-icon" style="background: linear-gradient(135deg, #047857, #065F46); color: white;"><i class="fa-solid fa-pen-nib"></i></div>
        <div class="stat-count"><?php echo $assigned_to_me_count; ?></div>
        <div class="stat-label">Assigned to Me</div>
    </div>
</div>

<!-- Degree Program Course Filter Pills -->
<div class="sec-course-filter-bar" style="margin: 0 2rem 1rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; background: white; padding: 0.85rem 1.25rem; border-radius: 12px; border: 1px solid #E2E8F0; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
    <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
        <span style="font-size: 0.82rem; font-weight: 800; color: #334155; text-transform: uppercase; letter-spacing: 0.4px;">
            <i class="fa-solid fa-graduation-cap"></i> Degree Program:
        </span>
        <button type="button" class="sec-course-pill active" data-course="all" onclick="filterByCourse('all', this)">
            All Courses (<?php echo $total_count; ?>)
        </button>
        <button type="button" class="sec-course-pill pill-bsit-btn" data-course="BSIT" onclick="filterByCourse('BSIT', this)">
            <span class="dp-tag tag-bsit">BSIT</span> Information Technology (<?php echo $course_counts['BSIT']; ?>)
        </button>
        <button type="button" class="sec-course-pill pill-bscpe-btn" data-course="BSCpE" onclick="filterByCourse('BSCpE', this)">
            <span class="dp-tag tag-bscpe">BSCpE</span> Computer Engineering (<?php echo $course_counts['BSCpE']; ?>)
        </button>
        <button type="button" class="sec-course-pill pill-bsee-btn" data-course="BSEE" onclick="filterByCourse('BSEE', this)">
            <span class="dp-tag tag-bsee">BSEE</span> Electrical Engineering (<?php echo $course_counts['BSEE']; ?>)
        </button>
    </div>
</div>

<!-- Search Bar -->
<div class="sec-search-bar">
    <i class="fa-solid fa-search"></i>
    <input type="text" id="secScheduleSearch" placeholder="Search by candidate name, topic, panel chair, degree course, venue..." onkeyup="searchSchedules(this.value)">
</div>

<!-- Schedule Cards -->
<div class="sec-schedules-grid" id="secSchedulesList">
    <?php if (empty($all_schedules)): ?>
        <div class="sec-empty-state">
            <i class="fa-solid fa-calendar-xmark"></i>
            <h3>No Defense Schedules Found</h3>
            <p>Defense schedules will appear here once the admin creates them.</p>
        </div>
    <?php else: ?>
        <?php foreach ($all_schedules as $sched): ?>
            <?php
            $date_obj = strtotime($sched['meeting_date']);
            $is_today = ($sched['meeting_date'] === $today_str);
            $is_upcoming = ($sched['meeting_date'] >= $today_str && $sched['status'] === 'scheduled');
            $is_completed = ($sched['status'] === 'completed');
            $is_mine = ((int)$sched['mom_documenter_id'] === $current_uid || $sched['mom_documenter_name'] === $current_name);
            $is_prop = ($sched['course_code'] === 'IT 198' || $sched['defense_category'] === 'Proposal Defense');

            $time_range = '';
            if (!empty($sched['start_time'])) {
                $time_range = date('g:i A', strtotime($sched['start_time']));
                if (!empty($sched['end_time'])) {
                    $time_range .= ' – ' . date('g:i A', strtotime($sched['end_time']));
                }
            } else {
                $time_range = 'TBA';
            }

            $panel_names = sec_decode_panel_members($sched['panel_members']);
            $doc_name = !empty($sched['mom_documenter_name']) ? $sched['mom_documenter_name'] : (!empty($sched['mom_documenter_user_name']) ? $sched['mom_documenter_user_name'] : '');
            $candidate_title = !empty($sched['group_name']) ? "Group {$sched['group_name']}" : $sched['student_name'];
            ?>
            <div class="sec-sched-item <?php echo $is_mine ? 'highlight-mine' : ''; ?>"
                 data-filter-status="<?php echo $is_today ? 'today' : ($is_completed ? 'completed' : ($is_upcoming ? 'upcoming' : 'past')); ?>"
                 data-degree-course="<?php echo htmlspecialchars($sched['dept_code']); ?>"
                 data-is-mine="<?php echo $is_mine ? '1' : '0'; ?>"
                 data-search-text="<?php echo strtolower(htmlspecialchars($sched['title'] . ' ' . $sched['student_name'] . ' ' . ($sched['group_name'] ?? '') . ' ' . ($sched['adviser_name'] ?? '') . ' ' . ($sched['panel_chair_name'] ?? '') . ' ' . ($sched['venue'] ?? '') . ' ' . $sched['defense_stage'] . ' ' . $sched['course_code'] . ' ' . $sched['dept_code'] . ' ' . $sched['dept_norm'] . ' ' . $doc_name)); ?>">

                <div class="sec-sched-top">
                    <!-- Date Column -->
                    <div class="sec-date-column <?php echo $is_today ? 'is-today' : ''; ?>">
                        <div class="date-month"><?php echo date('M', $date_obj); ?></div>
                        <div class="date-day"><?php echo date('d', $date_obj); ?></div>
                        <div class="date-year"><?php echo date('Y', $date_obj); ?></div>
                    </div>

                    <!-- Info Column -->
                    <div class="sec-info-column">
                        <!-- Badges -->
                        <div class="sec-info-badges">
                            <!-- Degree Program Badge -->
                            <span class="sec-badge badge-<?php echo strtolower($sched['dept_code']); ?>">
                                <i class="fa-solid fa-graduation-cap"></i> <?php echo htmlspecialchars($sched['dept_code']); ?>
                            </span>

                            <span class="sec-badge <?php echo $is_prop ? 'badge-course-198' : 'badge-course-199'; ?>">
                                <i class="fa-solid fa-code-branch"></i> <?php echo htmlspecialchars($sched['course_code']); ?>
                            </span>
                            <span class="sec-badge badge-stage">
                                <i class="fa-solid fa-bookmark"></i> <?php echo htmlspecialchars($sched['defense_stage']); ?>
                            </span>
                            <?php if ($is_today): ?>
                                <span class="sec-badge badge-status-today"><i class="fa-solid fa-bolt"></i> TODAY</span>
                            <?php elseif ($sched['status'] === 'in_progress'): ?>
                                <span class="sec-badge badge-status-progress"><i class="fa-solid fa-circle-dot"></i> In Progress</span>
                            <?php elseif ($is_completed): ?>
                                <span class="sec-badge badge-status-completed"><i class="fa-solid fa-circle-check"></i> Completed</span>
                            <?php elseif ($is_upcoming): ?>
                                <span class="sec-badge badge-status-scheduled"><i class="fa-regular fa-clock"></i> Scheduled</span>
                            <?php endif; ?>
                            <?php if (!empty($sched['mom_verdict'])): ?>
                                <span class="sec-badge badge-verdict"><i class="fa-solid fa-gavel"></i> <?php echo htmlspecialchars($sched['mom_verdict']); ?></span>
                            <?php endif; ?>
                            <?php if ($is_mine): ?>
                                <span class="sec-badge badge-mine"><i class="fa-solid fa-pen-nib"></i> My Assignment</span>
                            <?php endif; ?>
                        </div>

                        <!-- Title -->
                        <h3 class="sec-info-title"><?php echo htmlspecialchars($sched['title']); ?></h3>

                        <!-- Meta -->
                        <div class="sec-info-meta">
                            <span><i class="fa-regular fa-clock"></i> <?php echo $time_range; ?></span>
                            <span><i class="fa-solid fa-location-dot"></i> <?php echo htmlspecialchars($sched['venue'] ?? 'CEIT Defense Hall'); ?></span>
                            <span><i class="fa-solid fa-calendar-days"></i> A.Y. <?php echo htmlspecialchars($sched['academic_year']); ?></span>
                            <?php if (!empty($sched['meeting_link'])): ?>
                                <a href="<?php echo htmlspecialchars($sched['meeting_link']); ?>" target="_blank" style="color: #1D4ED8; font-weight: 600; text-decoration: none;">
                                    <i class="fa-solid fa-video"></i> Virtual Room
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- People Row -->
                <div class="sec-people-row">
                    <div class="sec-person-block">
                        <i class="fa-solid fa-graduation-cap" style="color: #D97706;"></i>
                        <span class="person-label">Candidate:</span>
                        <span class="person-name"><?php echo htmlspecialchars($candidate_title); ?></span>
                    </div>
                    <div class="sec-person-sep"></div>
                    <div class="sec-person-block">
                        <i class="fa-solid fa-user-tie" style="color: #047857;"></i>
                        <span class="person-label">Adviser:</span>
                        <span class="person-name"><?php echo htmlspecialchars(!empty($sched['adviser_name']) ? $sched['adviser_name'] : (!empty($sched['adviser_user_name']) ? $sched['adviser_user_name'] : 'TBA')); ?></span>
                    </div>
                    <div class="sec-person-sep"></div>
                    <div class="sec-person-block">
                        <i class="fa-solid fa-crown" style="color: #D97706;"></i>
                        <span class="person-label">Chair:</span>
                        <span class="person-name"><?php echo htmlspecialchars($sched['panel_chair_name'] ?? 'TBA'); ?></span>
                    </div>
                    <?php if (!empty($doc_name)): ?>
                        <div class="sec-person-sep"></div>
                        <div class="sec-person-block">
                            <i class="fa-solid fa-pen-nib" style="color: #059669;"></i>
                            <span class="person-label">MOM:</span>
                            <span class="person-name" style="color: #047857; font-weight: 700;"><?php echo htmlspecialchars($doc_name); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($panel_names)): ?>
                        <div class="sec-person-sep"></div>
                        <div class="sec-person-block">
                            <i class="fa-solid fa-users" style="color: #6366F1;"></i>
                            <span class="person-label">Panel:</span>
                            <span class="person-name"><?php echo htmlspecialchars(implode(', ', $panel_names)); ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Actions Row -->
                <div class="sec-actions-row">
                    <div class="sec-actions-left">
                        <i class="fa-solid fa-microphone-lines"></i>
                        <span>Transcripts: <strong><?php echo (int)$sched['transcript_count']; ?></strong></span>
                        <?php if (!empty($sched['mom_status'])): ?>
                            <span>• MOM: <strong style="color: <?php echo $sched['mom_status'] === 'finalized' ? '#047857' : '#D97706'; ?>;"><?php echo ucfirst(str_replace('_', ' ', $sched['mom_status'])); ?></strong></span>
                        <?php endif; ?>
                        <?php if (!empty($sched['mom_grade'])): ?>
                            <span>• Grade: <strong style="color: #047857;"><?php echo number_format((float)$sched['mom_grade'], 2); ?></strong></span>
                        <?php endif; ?>
                    </div>
                    <div class="sec-actions-right">
                        <a href="secretary_dashboard.php?tab=meeting_doc&student_id=<?php echo $sched['student_id']; ?>&meeting_id=<?php echo $sched['id']; ?>" class="sec-action-btn btn-transcribe" title="Open Voice-to-Text & MOM Document">
                            <i class="fa-solid fa-microphone-lines"></i> Take MOM / Transcribe
                        </a>
                        <?php if (!empty($sched['mom_id'])): ?>
                            <a href="secretary_dashboard.php?tab=mom_archive" class="sec-action-btn btn-mom" title="View Filed MOM">
                                <i class="fa-solid fa-file-signature"></i> View MOM
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
let currentSecStatus = 'all';
let currentSecCourse = 'all';

function filterByCourse(course, el) {
    currentSecCourse = course;
    document.querySelectorAll('.sec-course-pill').forEach(b => b.classList.remove('active'));
    if (el) el.classList.add('active');
    applySecFilters();
}

function filterSchedules(filter, el) {
    currentSecStatus = filter;
    document.querySelectorAll('.sec-stat-card').forEach(c => c.classList.remove('active'));
    if (el) el.classList.add('active');
    applySecFilters();
}

function searchSchedules(query) {
    applySecFilters();
}

function applySecFilters() {
    const q = (document.getElementById('secScheduleSearch') ? document.getElementById('secScheduleSearch').value : '').toLowerCase().trim();
    const items = document.querySelectorAll('.sec-sched-item');
    items.forEach(item => {
        const status = item.getAttribute('data-filter-status');
        const course = item.getAttribute('data-degree-course');
        const isMine = item.getAttribute('data-is-mine') === '1';
        const text = item.getAttribute('data-search-text') || '';

        let statusMatch = false;
        if (currentSecStatus === 'all') statusMatch = true;
        else if (currentSecStatus === 'mine') statusMatch = isMine;
        else statusMatch = (status === currentSecStatus);

        let courseMatch = (currentSecCourse === 'all' || course === currentSecCourse);
        let searchMatch = (!q || text.includes(q));

        item.style.display = (statusMatch && courseMatch && searchMatch) ? '' : 'none';
    });
}
</script>
