<?php
// ═════════════════════════════════════════════════════════════════════════════
// CEIT SYSTEM REPORTS & STATISTICS (100% DATABASE-BACKED)
// ═════════════════════════════════════════════════════════════════════════════

// 1. KPI Counts from Database
$stat_students     = (int)($conn->query("SELECT COUNT(*) as c FROM user WHERE role='student' AND account_status='active'")->fetch_assoc()['c'] ?? 0);
$stat_faculty      = (int)($conn->query("SELECT COUNT(*) as c FROM user WHERE role='adviser' AND account_status='active'")->fetch_assoc()['c'] ?? 0);
$stat_panels       = (int)($conn->query("SELECT COUNT(*) as c FROM user WHERE role='panel' AND account_status='active'")->fetch_assoc()['c'] ?? 0);
$stat_secretaries  = (int)($conn->query("SELECT COUNT(*) as c FROM user WHERE role='secretary' AND account_status='active'")->fetch_assoc()['c'] ?? 0);
$stat_admins       = (int)($conn->query("SELECT COUNT(*) as c FROM user WHERE role='admin' AND account_status='active'")->fetch_assoc()['c'] ?? 0);
$stat_archived     = (int)($conn->query("SELECT COUNT(*) as c FROM user WHERE account_status='archived'")->fetch_assoc()['c'] ?? 0);
$stat_total_users  = $stat_students + $stat_faculty + $stat_panels + $stat_secretaries + $stat_admins;

// Capstone Groups & Proposals
$stat_groups       = (int)($conn->query("SELECT COUNT(*) as c FROM capstone_group")->fetch_assoc()['c'] ?? 0);
$stat_theses       = (int)($conn->query("SELECT COUNT(*) as c FROM student_profile WHERE thesis_topic IS NOT NULL AND thesis_topic != ''")->fetch_assoc()['c'] ?? 0);
$stat_approved_titles = (int)($conn->query("SELECT COUNT(*) as c FROM capstone_group WHERE title_status='approved'")->fetch_assoc()['c'] ?? 0)
                     + (int)($conn->query("SELECT COUNT(*) as c FROM student_profile WHERE title_status='approved' AND (group_id IS NULL OR group_id = 0)")->fetch_assoc()['c'] ?? 0);

// Match Requests
$stat_req_accepted = (int)($conn->query("SELECT COUNT(*) as c FROM match_request WHERE status='accepted'")->fetch_assoc()['c'] ?? 0);
$stat_req_pending  = (int)($conn->query("SELECT COUNT(*) as c FROM match_request WHERE status='pending'")->fetch_assoc()['c'] ?? 0);
$stat_req_rejected = (int)($conn->query("SELECT COUNT(*) as c FROM match_request WHERE status='rejected'")->fetch_assoc()['c'] ?? 0);
$stat_req_total    = $stat_req_accepted + $stat_req_pending + $stat_req_rejected;

// Defense Sessions & Deliberations
$stat_def_total    = (int)($conn->query("SELECT COUNT(*) as c FROM defense_meetings")->fetch_assoc()['c'] ?? 0);
$stat_def_proposal = (int)($conn->query("SELECT COUNT(*) as c FROM defense_meetings WHERE defense_category='Proposal Defense' OR course_code LIKE '%198%'")->fetch_assoc()['c'] ?? 0);
$stat_def_final    = (int)($conn->query("SELECT COUNT(*) as c FROM defense_meetings WHERE defense_category='Final Defense' OR course_code LIKE '%199%'")->fetch_assoc()['c'] ?? 0);
$stat_def_completed= (int)($conn->query("SELECT COUNT(*) as c FROM defense_meetings WHERE status='completed'")->fetch_assoc()['c'] ?? 0);
$stat_def_scheduled= (int)($conn->query("SELECT COUNT(*) as c FROM defense_meetings WHERE status='scheduled'")->fetch_assoc()['c'] ?? 0);
$stat_moms_total   = (int)($conn->query("SELECT COUNT(*) as c FROM defense_meeting_minutes")->fetch_assoc()['c'] ?? 0);

// Manuscripts & Submissions
$stat_docs_total    = (int)($conn->query("SELECT COUNT(*) as c FROM document_submissions")->fetch_assoc()['c'] ?? 0);
$stat_docs_approved = (int)($conn->query("SELECT COUNT(*) as c FROM document_submissions WHERE status='approved'")->fetch_assoc()['c'] ?? 0);
$stat_docs_revision = (int)($conn->query("SELECT COUNT(*) as c FROM document_submissions WHERE status='needs_revision'")->fetch_assoc()['c'] ?? 0);
$stat_docs_pending  = (int)($conn->query("SELECT COUNT(*) as c FROM document_submissions WHERE status='pending' OR status='under_review'")->fetch_assoc()['c'] ?? 0);

// 2. Department & Degree Program Breakdown (Canonical: BSIT, BSCpE, BSEE)
$dept_counts = [
    'BSIT' => ['name' => 'Information Technology', 'students' => 0, 'faculty' => 0, 'groups' => 0, 'defenses' => 0],
    'BSCpE' => ['name' => 'Computer Engineering', 'students' => 0, 'faculty' => 0, 'groups' => 0, 'defenses' => 0],
    'BSEE' => ['name' => 'Electrical Engineering', 'students' => 0, 'faculty' => 0, 'groups' => 0, 'defenses' => 0]
];

// Students by dept
$stu_dept_q = $conn->query("SELECT sp.department, COUNT(*) as c FROM student_profile sp JOIN user u ON sp.user_id = u.id WHERE u.account_status='active' GROUP BY sp.department");
if ($stu_dept_q) {
    while ($r = $stu_dept_q->fetch_assoc()) {
        $short = function_exists('get_course_short_code') ? get_course_short_code($r['department'] ?? '') : 'BSIT';
        if (isset($dept_counts[$short])) $dept_counts[$short]['students'] += (int)$r['c'];
    }
}

// Faculty / Panel / Secretary by dept
$fac_dept_q = $conn->query("
    SELECT COALESCE(ap.department, pp.department, sp.department, 'Information Technology') as dept, COUNT(*) as c
    FROM user u
    LEFT JOIN adviser_profile ap ON u.id = ap.user_id AND u.role='adviser'
    LEFT JOIN panel_profile pp ON u.id = pp.user_id AND u.role='panel'
    LEFT JOIN secretary_profile sp ON u.id = sp.user_id AND u.role='secretary'
    WHERE u.role IN ('adviser', 'panel', 'secretary') AND u.account_status='active'
    GROUP BY dept
");
if ($fac_dept_q) {
    while ($r = $fac_dept_q->fetch_assoc()) {
        $short = function_exists('get_course_short_code') ? get_course_short_code($r['dept'] ?? '') : 'BSIT';
        if (isset($dept_counts[$short])) $dept_counts[$short]['faculty'] += (int)$r['c'];
    }
}

// Groups by dept
$grp_dept_q = $conn->query("SELECT department, COUNT(*) as c FROM capstone_group GROUP BY department");
if ($grp_dept_q) {
    while ($r = $grp_dept_q->fetch_assoc()) {
        $short = function_exists('get_course_short_code') ? get_course_short_code($r['department'] ?? '') : 'BSIT';
        if (isset($dept_counts[$short])) $dept_counts[$short]['groups'] += (int)$r['c'];
    }
}

// Defenses by dept
$def_dept_q = $conn->query("SELECT department, COUNT(*) as c FROM defense_meetings GROUP BY department");
if ($def_dept_q) {
    while ($r = $def_dept_q->fetch_assoc()) {
        $short = function_exists('get_course_short_code') ? get_course_short_code($r['department'] ?? '') : 'BSIT';
        if (isset($dept_counts[$short])) $dept_counts[$short]['defenses'] += (int)$r['c'];
    }
}

// Total by Course
$total_per_course = [
    'BSIT' => $dept_counts['BSIT']['students'] + $dept_counts['BSIT']['faculty'],
    'BSCpE' => $dept_counts['BSCpE']['students'] + $dept_counts['BSCpE']['faculty'],
    'BSEE' => $dept_counts['BSEE']['students'] + $dept_counts['BSEE']['faculty']
];

// Available Academic Years
$available_academic_years = function_exists('get_available_academic_years') ? get_available_academic_years($conn) : [];
$curr_system_ay = function_exists('calculate_academic_year') ? calculate_academic_year(date('Y-m-d')) : '2026-2027';
if (!in_array($curr_system_ay, $available_academic_years)) {
    array_unshift($available_academic_years, $curr_system_ay);
}

// 3. Fetch Master Capstone & Theses Roster Data (Comprehensive from DB)
$theses_roster_query = $conn->query("
    SELECT u.id as student_id, u.name as student_name, u.email as student_email,
           sp.department as student_dept, sp.thesis_topic as student_topic, sp.skills, sp.student_id as student_no, sp.group_id,
           cg.group_name, cg.department as group_dept, cg.thesis_topic as group_topic, cg.title_status as group_title_status,
           cg.academic_year as group_ay, cg.course_code as group_cc,
           cg.member2_name, cg.member3_name, cg.member4_name,
           adv.name as adviser_name, adv.id as adviser_id,
           mr.status as match_status, mr.match_score,
           dm.id as meeting_id, dm.defense_stage, dm.status as defense_status, dm.meeting_date, dm.venue, dm.course_code as defense_cc,
           dmm.decision_verdict, dmm.grade_average
    FROM user u
    JOIN student_profile sp ON u.id = sp.user_id
    LEFT JOIN capstone_group cg ON sp.group_id = cg.id
    LEFT JOIN match_request mr ON mr.student_id = u.id AND mr.status = 'accepted'
    LEFT JOIN user adv ON adv.id = mr.adviser_id
    LEFT JOIN (
        SELECT dm1.* FROM defense_meetings dm1
        JOIN (SELECT student_id, MAX(id) as max_id FROM defense_meetings GROUP BY student_id) dm2 ON dm1.id = dm2.max_id
    ) dm ON (dm.student_id = u.id OR (sp.group_id > 0 AND dm.group_id = sp.group_id))
    LEFT JOIN defense_meeting_minutes dmm ON dm.id = dmm.meeting_id
    WHERE u.role = 'student' AND u.account_status = 'active'
    ORDER BY (sp.group_id > 0) DESC, u.name ASC
");

$all_roster = [];
if ($theses_roster_query) {
    while ($row = $theses_roster_query->fetch_assoc()) {
        $raw_dept = !empty($row['group_dept']) ? $row['group_dept'] : (!empty($row['student_dept']) ? $row['student_dept'] : 'Information Technology');
        $norm_dept = function_exists('normalize_course_name') ? normalize_course_name($raw_dept) : 'Information Technology';
        $short_dept = function_exists('get_course_short_code') ? get_course_short_code($norm_dept) : 'BSIT';
        
        $row['dept_norm'] = $norm_dept;
        $row['dept_code'] = $short_dept;
        $row['effective_topic'] = !empty($row['group_topic']) ? $row['group_topic'] : (!empty($row['student_topic']) ? $row['student_topic'] : 'Capstone Topic Pending');
        $row['effective_ay'] = !empty($row['group_ay']) ? $row['group_ay'] : $curr_system_ay;
        $row['effective_cc'] = !empty($row['defense_cc']) ? $row['defense_cc'] : (!empty($row['group_cc']) ? $row['group_cc'] : ($short_dept === 'BSCpE' ? 'CPE 199' : ($short_dept === 'BSEE' ? 'EE 199' : 'IT 199')));
        
        $all_roster[] = $row;
    }
}

// 4. Fetch Defense Deliberations & Grades Registry
$deliberations_query = $conn->query("
    SELECT dm.*, 
           u.name as student_name,
           cg.group_name,
           chair.name as panel_chair_name,
           adv.name as adviser_user_name,
           dmm.id as mom_id, dmm.decision_verdict, dmm.grade_average, dmm.recorded_by_name, dmm.status as mom_status
    FROM defense_meetings dm
    JOIN user u ON dm.student_id = u.id
    LEFT JOIN capstone_group cg ON dm.group_id = cg.id
    LEFT JOIN user chair ON dm.panel_chair_id = chair.id
    LEFT JOIN user adv ON dm.adviser_id = adv.id
    LEFT JOIN defense_meeting_minutes dmm ON dm.id = dmm.meeting_id
    ORDER BY dm.meeting_date DESC, dm.start_time DESC
");

$all_deliberations = [];
if ($deliberations_query) {
    while ($row = $deliberations_query->fetch_assoc()) {
        $norm_dept = function_exists('normalize_course_name') ? normalize_course_name($row['department'] ?? '') : 'Information Technology';
        $short_dept = function_exists('get_course_short_code') ? get_course_short_code($norm_dept) : 'BSIT';
        $row['dept_norm'] = $norm_dept;
        $row['dept_code'] = $short_dept;
        $all_deliberations[] = $row;
    }
}

// 5. Fetch Faculty Portfolio (Advising & Panel Duties)
$faculty_portfolio_query = $conn->query("
    SELECT u.id, u.name, u.email, u.role,
           COALESCE(ap.department, pp.department, 'Information Technology') as department,
           COALESCE(ap.expertise, pp.expertise, 'CEIT Faculty') as expertise,
           COALESCE(ap.capacity, 5) as capacity,
           (SELECT COUNT(*) FROM match_request mr WHERE mr.adviser_id = u.id AND mr.status = 'accepted') as advisee_count,
           (SELECT COUNT(*) FROM thesis_panel tp WHERE tp.panel_id = u.id) as panel_count,
           (SELECT COUNT(*) FROM defense_meetings dm WHERE dm.panel_chair_id = u.id) as chair_count
    FROM user u
    LEFT JOIN adviser_profile ap ON u.id = ap.user_id AND u.role = 'adviser'
    LEFT JOIN panel_profile pp ON u.id = pp.user_id AND u.role = 'panel'
    WHERE u.role IN ('adviser', 'panel') AND u.account_status = 'active'
    ORDER BY u.role ASC, u.name ASC
");

$all_faculty = [];
if ($faculty_portfolio_query) {
    while ($row = $faculty_portfolio_query->fetch_assoc()) {
        $norm_dept = function_exists('normalize_course_name') ? normalize_course_name($row['department'] ?? '') : 'Information Technology';
        $short_dept = function_exists('get_course_short_code') ? get_course_short_code($norm_dept) : 'BSIT';
        $row['dept_norm'] = $norm_dept;
        $row['dept_code'] = $short_dept;
        $all_faculty[] = $row;
    }
}

// 6. Fetch Document Manuscripts Log
$submissions_query = $conn->query("
    SELECT ds.*, 
           u.name as student_name,
           cg.group_name,
           adv.name as adviser_name
    FROM document_submissions ds
    JOIN user u ON ds.student_id = u.id
    LEFT JOIN capstone_group cg ON ds.group_id = cg.id
    LEFT JOIN user adv ON ds.adviser_id = adv.id
    ORDER BY ds.id DESC
");

$all_submissions = [];
if ($submissions_query) {
    while ($row = $submissions_query->fetch_assoc()) {
        $all_submissions[] = $row;
    }
}

$generated_date = date('F j, Y — g:i A');
?>

<!-- ═══════════════════════════════════════════════════════════════════════════
     REPORT COMPONENT STYLES
════════════════════════════════════════════════════════════════════════════ -->
<style>
.rpt-wrapper {
    display: flex;
    flex-direction: column;
    gap: 1.35rem;
}

/* Banner */
.rpt-banner {
    background: linear-gradient(135deg, #064E3B 0%, #047857 55%, #022C22 100%);
    border-radius: 16px;
    padding: 1.6rem 2rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1.25rem;
    color: #FFFFFF;
    box-shadow: 0 8px 24px -4px rgba(4, 120, 87, 0.35);
    border: 1px solid #10B981;
}

.rpt-banner-content h1 {
    font-size: 1.65rem;
    font-weight: 800;
    margin: 0.35rem 0;
    letter-spacing: -0.3px;
    color: #FFFFFF;
}

.rpt-banner-content p {
    font-size: 0.9rem;
    color: #D1FAE5;
    margin: 0;
    line-height: 1.45;
}

.rpt-banner-badge {
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

.rpt-print-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;
    background: #F59E0B;
    color: #022C22;
    padding: 0.75rem 1.4rem;
    border-radius: 10px;
    font-size: 0.92rem;
    font-weight: 800;
    border: none;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(245, 158, 11, 0.4);
    transition: all 0.2s ease;
}

.rpt-print-btn:hover {
    background: #D97706;
    color: #FFFFFF;
    transform: translateY(-2px);
}

/* KPI Stat Cards Grid */
.rpt-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(185px, 1fr));
    gap: 1rem;
}

.rpt-kpi-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 14px;
    padding: 1.15rem 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    transition: all 0.2s ease;
    position: relative;
    overflow: hidden;
}

.rpt-kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0,0,0,0.06);
    border-color: #CBD5E1;
}

.rpt-kpi-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; bottom: 0;
    width: 4px;
}

.rpt-kpi-card.green::before { background: #059669; }
.rpt-kpi-card.amber::before { background: #D97706; }
.rpt-kpi-card.blue::before { background: #2563EB; }
.rpt-kpi-card.purple::before { background: #7C3AED; }
.rpt-kpi-card.teal::before { background: #0D9488; }
.rpt-kpi-card.indigo::before { background: #4F46E5; }

.rpt-kpi-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}

.rpt-kpi-card.green .rpt-kpi-icon { background: #ECFDF5; color: #059669; }
.rpt-kpi-card.amber .rpt-kpi-icon { background: #FEF3C7; color: #D97706; }
.rpt-kpi-card.blue .rpt-kpi-icon { background: #EFF6FF; color: #2563EB; }
.rpt-kpi-card.purple .rpt-kpi-icon { background: #F3E8FF; color: #7C3AED; }
.rpt-kpi-card.teal .rpt-kpi-icon { background: #CCFBF1; color: #0D9488; }
.rpt-kpi-card.indigo .rpt-kpi-icon { background: #EEF2FF; color: #4F46E5; }

.rpt-kpi-value {
    font-size: 1.6rem;
    font-weight: 800;
    color: #0F172A;
    line-height: 1.1;
}

.rpt-kpi-label {
    font-size: 0.76rem;
    font-weight: 700;
    color: #64748B;
    margin-top: 0.2rem;
}

/* Degree Program Filter Bar */
.rpt-filter-bar {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 14px;
    padding: 1rem 1.35rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.85rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}

.rpt-filter-left {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    flex-wrap: wrap;
}

.rpt-filter-label {
    font-size: 0.82rem;
    font-weight: 800;
    color: #334155;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}

.rpt-course-btn {
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

.rpt-course-btn:hover {
    background: #F1F5F9;
    border-color: #CBD5E1;
    transform: translateY(-1px);
}

.rpt-course-btn.active {
    background: #0F172A;
    color: #FFFFFF;
    border-color: #0F172A;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.25);
}

.rpt-course-btn.btn-bsit.active {
    background: #047857;
    color: #FFFFFF;
    border-color: #047857;
    box-shadow: 0 4px 12px rgba(4, 120, 87, 0.3);
}

.rpt-course-btn.btn-bscpe.active {
    background: #1D4ED8;
    color: #FFFFFF;
    border-color: #1D4ED8;
    box-shadow: 0 4px 12px rgba(29, 78, 216, 0.3);
}

.rpt-course-btn.btn-bsee.active {
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
.rpt-course-btn.active .dp-tag {
    background: rgba(255, 255, 255, 0.25);
    color: #FFFFFF;
}

.rpt-search-box {
    position: relative;
    display: inline-flex;
    align-items: center;
}

.rpt-search-box i {
    position: absolute;
    left: 0.75rem;
    color: #94A3B8;
    font-size: 0.85rem;
}

.rpt-search-input {
    padding: 0.45rem 1rem 0.45rem 2.2rem;
    border: 1.5px solid #E2E8F0;
    border-radius: 10px;
    font-size: 0.82rem;
    min-width: 220px;
    outline: none;
    transition: all 0.2s ease;
}

.rpt-search-input:focus {
    border-color: #059669;
    box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.12);
}

/* Charts Grid */
.rpt-charts-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.25rem;
}

.rpt-chart-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 14px;
    padding: 1.35rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}

.rpt-chart-head {
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #F1F5F9;
}

.rpt-chart-head h3 {
    font-size: 0.95rem;
    font-weight: 800;
    color: #0F172A;
    margin: 0 0 0.2rem;
    display: flex;
    align-items: center;
    gap: 0.45rem;
}

.rpt-chart-head p {
    font-size: 0.78rem;
    color: #64748B;
    margin: 0;
}

.rpt-chart-wrap {
    height: 220px;
    position: relative;
}

/* Table Card & Tabs */
.rpt-master-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.04);
}

.rpt-tabs-header {
    background: #F8FAFC;
    border-bottom: 1.5px solid #E2E8F0;
    padding: 0.75rem 1.25rem 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    overflow-x: auto;
}

.rpt-tab-btn {
    background: none;
    border: none;
    padding: 0.65rem 1.15rem;
    font-size: 0.85rem;
    font-weight: 700;
    color: #64748B;
    border-bottom: 3px solid transparent;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s ease;
    white-space: nowrap;
}

.rpt-tab-btn:hover {
    color: #0F172A;
}

.rpt-tab-btn.active {
    color: #047857;
    border-bottom-color: #047857;
    background: #FFFFFF;
    border-radius: 8px 8px 0 0;
}

.rpt-tab-badge {
    background: #ECFDF5;
    color: #047857;
    border: 1px solid #A7F3D0;
    padding: 1px 7px;
    border-radius: 999px;
    font-size: 0.72rem;
}

.rpt-table-container {
    padding: 0;
    overflow-x: auto;
}

.rpt-data-table {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
}

.rpt-data-table thead tr {
    background: #F8FAFC;
    border-bottom: 2px solid #E2E8F0;
}

.rpt-data-table thead th {
    padding: 0.85rem 1.15rem;
    font-size: 0.75rem;
    font-weight: 800;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    white-space: nowrap;
}

.rpt-data-table tbody tr {
    border-bottom: 1px solid #F1F5F9;
    transition: background 0.15s ease;
}

.rpt-data-table tbody tr:hover {
    background: #F8FAFC;
}

.rpt-data-table tbody td {
    padding: 0.9rem 1.15rem;
    font-size: 0.85rem;
    color: #334155;
    vertical-align: middle;
}

/* Badges */
.badge-course {
    font-size: 0.72rem;
    font-weight: 800;
    padding: 2px 7px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}

.badge-bsit { background: #ECFDF5; color: #047857; border: 1px solid #A7F3D0; }
.badge-bscpe { background: #EFF6FF; color: #1D4ED8; border: 1px solid #BFDBFE; }
.badge-bsee { background: #FFFBEB; color: #B45309; border: 1px solid #FDE68A; }

.badge-status {
    font-size: 0.72rem;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.badge-status.approved { background: #D1FAE5; color: #065F46; }
.badge-status.pending { background: #FEF3C7; color: #92400E; }
.badge-status.revision { background: #FEE2E2; color: #991B1B; }

.badge-verdict {
    background: #ECFDF5;
    color: #047857;
    border: 1px solid #A7F3D0;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 6px;
    font-size: 0.76rem;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

/* Print Only Header */
.rpt-print-header {
    display: none;
    text-align: center;
    padding-bottom: 1.25rem;
    border-bottom: 2px solid #0F172A;
    margin-bottom: 1.5rem;
}

.rpt-print-footer {
    display: none;
    text-align: center;
    font-size: 0.75rem;
    color: #64748B;
    margin-top: 2rem;
    padding-top: 1rem;
    border-top: 1px solid #E2E8F0;
}

@media print {
    .rpt-banner, .rpt-filter-bar, .rpt-print-btn, .sidebar, .topbar, .rpt-tabs-header { display: none !important; }
    .rpt-print-header, .rpt-print-footer { display: block !important; }
    .rpt-kpi-grid { grid-template-columns: repeat(4, 1fr) !important; }
    .rpt-charts-grid { display: none !important; }
    .rpt-master-card { box-shadow: none !important; border: 1px solid #CBD5E1 !important; }
    .tab-content-panel { display: block !important; margin-bottom: 2rem; }
    body { background: #FFFFFF !important; }
}

@media (max-width: 900px) {
    .rpt-charts-grid { grid-template-columns: 1fr; }
}
</style>

<!-- ═══════════════════════════════════════════════════════════════════════════
     PRINT-ONLY CSU CEIT LETTERHEAD
════════════════════════════════════════════════════════════════════════════ -->
<div class="rpt-print-header">
    <img src="logo.png" style="width: 64px; height: auto; margin-bottom: 0.35rem;" alt="CSU Seal">
    <div style="font-size: 1.05rem; font-weight: 800; color: #0F172A;">CARAGA STATE UNIVERSITY — CABADBARAN CAMPUS</div>
    <div style="font-size: 0.88rem; color: #334155; margin-top: 2px;">College of Engineering and Information Technology</div>
    <div style="font-size: 0.82rem; color: #047857; font-weight: 800; margin-top: 0.5rem; letter-spacing: 0.5px;">
        OFFICIAL SYSTEM REPORTS &amp; RESEARCH STATISTICS
    </div>
    <div style="font-size: 0.72rem; color: #64748B; margin-top: 0.2rem;">Generated on: <?= $generated_date ?> &bull; A.Y. <?= $curr_system_ay ?></div>
</div>

<div class="rpt-wrapper">
    <!-- Header Banner -->
    <div class="rpt-banner">
        <div class="rpt-banner-content">
            <div class="rpt-banner-badge">
                <i class="fa-solid fa-database"></i> Database-Driven Academic Reports
            </div>
            <h1>System Reports &amp; Statistics</h1>
            <p>Comprehensive university analytics, research rosters, defense deliberations, and committee portfolios derived directly from active database records.</p>
        </div>
        <div class="rpt-banner-actions">
            <button type="button" class="rpt-print-btn" onclick="window.print()">
                <i class="fa-solid fa-print"></i> Print Official Report
            </button>
        </div>
    </div>

    <!-- KPI Metric Cards Grid -->
    <div class="rpt-kpi-grid">
        <div class="rpt-kpi-card green">
            <div class="rpt-kpi-icon"><i class="fa-solid fa-user-graduate"></i></div>
            <div>
                <div class="rpt-kpi-value" id="kpiStudents"><?= $stat_students ?></div>
                <div class="rpt-kpi-label">Active Students</div>
            </div>
        </div>

        <div class="rpt-kpi-card amber">
            <div class="rpt-kpi-icon"><i class="fa-solid fa-chalkboard-user"></i></div>
            <div>
                <div class="rpt-kpi-value" id="kpiAdvisers"><?= $stat_faculty ?></div>
                <div class="rpt-kpi-label">Active Advisers</div>
            </div>
        </div>

        <div class="rpt-kpi-card blue">
            <div class="rpt-kpi-icon"><i class="fa-solid fa-people-group"></i></div>
            <div>
                <div class="rpt-kpi-value" id="kpiPanels"><?= $stat_panels ?></div>
                <div class="rpt-kpi-label">Panel Members</div>
            </div>
        </div>

        <div class="rpt-kpi-card purple">
            <div class="rpt-kpi-icon"><i class="fa-solid fa-layer-group"></i></div>
            <div>
                <div class="rpt-kpi-value" id="kpiGroups"><?= $stat_groups ?></div>
                <div class="rpt-kpi-label">Capstone Groups</div>
            </div>
        </div>

        <div class="rpt-kpi-card teal">
            <div class="rpt-kpi-icon"><i class="fa-solid fa-calendar-check"></i></div>
            <div>
                <div class="rpt-kpi-value" id="kpiDefenses"><?= $stat_def_total ?></div>
                <div class="rpt-kpi-label">Defense Sessions</div>
            </div>
        </div>

        <div class="rpt-kpi-card green">
            <div class="rpt-kpi-icon"><i class="fa-solid fa-gavel"></i></div>
            <div>
                <div class="rpt-kpi-value" id="kpiDeliberated"><?= $stat_def_completed ?></div>
                <div class="rpt-kpi-label">MOMs Completed</div>
            </div>
        </div>

        <div class="rpt-kpi-card indigo">
            <div class="rpt-kpi-icon"><i class="fa-solid fa-handshake"></i></div>
            <div>
                <div class="rpt-kpi-value" id="kpiMatches"><?= $stat_req_accepted ?></div>
                <div class="rpt-kpi-label">Accepted Matches</div>
            </div>
        </div>

        <div class="rpt-kpi-card amber">
            <div class="rpt-kpi-icon"><i class="fa-solid fa-file-signature"></i></div>
            <div>
                <div class="rpt-kpi-value" id="kpiDocs"><?= $stat_docs_total ?></div>
                <div class="rpt-kpi-label">Manuscripts Filed</div>
            </div>
        </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="rpt-filter-bar">
        <div class="rpt-filter-left">
            <span class="rpt-filter-label">
                <i class="fa-solid fa-graduation-cap"></i> Degree Program:
            </span>
            <button type="button" class="rpt-course-btn active" id="btnCourseAll" onclick="filterReportByCourse('all', this)">
                All Courses (<?= $stat_total_users ?> Users)
            </button>
            <button type="button" class="rpt-course-btn btn-bsit" id="btnCourseBSIT" onclick="filterReportByCourse('BSIT', this)">
                <span class="dp-tag tag-bsit">BSIT</span> Information Technology (<?= $total_per_course['BSIT'] ?>)
            </button>
            <button type="button" class="rpt-course-btn btn-bscpe" id="btnCourseBSCpE" onclick="filterReportByCourse('BSCpE', this)">
                <span class="dp-tag tag-bscpe">BSCpE</span> Computer Engineering (<?= $total_per_course['BSCpE'] ?>)
            </button>
            <button type="button" class="rpt-course-btn btn-bsee" id="btnCourseBSEE" onclick="filterReportByCourse('BSEE', this)">
                <span class="dp-tag tag-bsee">BSEE</span> Electrical Engineering (<?= $total_per_course['BSEE'] ?>)
            </button>
        </div>

        <div class="rpt-search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="reportSearchInput" class="rpt-search-input" placeholder="Search title, student, adviser, verdict..." onkeyup="searchReportTables(this.value)">
        </div>
    </div>

    <!-- Visual Analytics Charts Grid -->
    <div class="rpt-charts-grid">
        <!-- Chart 1: All User Roles Distribution -->
        <div class="rpt-chart-card">
            <div class="rpt-chart-head">
                <h3><i class="fa-solid fa-users" style="color: #059669;"></i> User Roles Distribution</h3>
                <p>All active accounts recorded in user table</p>
            </div>
            <div class="rpt-chart-wrap">
                <canvas id="userRolesChart"></canvas>
            </div>
        </div>

        <!-- Chart 2: Matchmaking & Submissions Pipeline -->
        <div class="rpt-chart-card">
            <div class="rpt-chart-head">
                <h3><i class="fa-solid fa-chart-column" style="color: #2563EB;"></i> Workflow Status Pipeline</h3>
                <p>Status of match requests &amp; manuscript submissions</p>
            </div>
            <div class="rpt-chart-wrap">
                <canvas id="workflowPipelineChart"></canvas>
            </div>
        </div>

        <!-- Chart 3: Distribution by Degree Program -->
        <div class="rpt-chart-card">
            <div class="rpt-chart-head">
                <h3><i class="fa-solid fa-building-columns" style="color: #7C3AED;"></i> Distribution by Degree Program</h3>
                <p>Combined users across BSIT, BSCpE &amp; BSEE</p>
            </div>
            <div class="rpt-chart-wrap">
                <canvas id="deptDistributionChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Master Data Tables Container -->
    <div class="rpt-master-card" id="printable-area">
        <!-- Table Navigation Tabs -->
        <div class="rpt-tabs-header">
            <button type="button" class="rpt-tab-btn active" id="tabBtnRoster" onclick="switchReportTab('roster', this)">
                <i class="fa-solid fa-book-bookmark"></i> Master Capstone Projects Roster
                <span class="rpt-tab-badge" id="badgeCountRoster"><?= count($all_roster) ?></span>
            </button>
            <button type="button" class="rpt-tab-btn" id="tabBtnDelib" onclick="switchReportTab('deliberations', this)">
                <i class="fa-solid fa-gavel"></i> Defense Deliberations &amp; Verdicts
                <span class="rpt-tab-badge" id="badgeCountDelib"><?= count($all_deliberations) ?></span>
            </button>
            <button type="button" class="rpt-tab-btn" id="tabBtnFaculty" onclick="switchReportTab('faculty', this)">
                <i class="fa-solid fa-user-tie"></i> Faculty Advising &amp; Panels
                <span class="rpt-tab-badge" id="badgeCountFaculty"><?= count($all_faculty) ?></span>
            </button>
            <button type="button" class="rpt-tab-btn" id="tabBtnDocs" onclick="switchReportTab('documents', this)">
                <i class="fa-solid fa-file-lines"></i> Manuscripts &amp; Submissions
                <span class="rpt-tab-badge" id="badgeCountDocs"><?= count($all_submissions) ?></span>
            </button>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════════
             PANEL 1: MASTER CAPSTONE PROJECTS ROSTER
        ════════════════════════════════════════════════════════════════════ -->
        <div class="tab-content-panel" id="panelRoster">
            <div class="rpt-table-container">
                <table class="rpt-data-table" id="tableRoster">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Candidate / Research Group</th>
                            <th>Degree Program</th>
                            <th>Capstone Topic</th>
                            <th>Assigned Adviser</th>
                            <th>Defense Stage &amp; Verdict</th>
                            <th>Course Series</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($all_roster)): ?>
                            <tr><td colspan="7" style="text-align: center; padding: 2rem; color: #94A3B8;">No student research records found in the database.</td></tr>
                        <?php else: ?>
                            <?php $i = 0; foreach ($all_roster as $r): $i++; 
                                $group_lbl = !empty($r['group_name']) ? "Group {$r['group_name']}" : "Solo Candidate";
                                $is_prop = (strpos($r['effective_cc'], '198') !== false || ($r['defense_stage'] ?? '') === 'Title Proposal');
                            ?>
                            <tr class="rpt-row-item" 
                                data-degree="<?= htmlspecialchars($r['dept_code']) ?>"
                                data-search="<?= strtolower(htmlspecialchars($r['student_name'] . ' ' . $r['group_name'] . ' ' . $r['effective_topic'] . ' ' . ($r['adviser_name'] ?? '') . ' ' . $r['dept_code'] . ' ' . ($r['mom_verdict'] ?? ''))) ?>">
                                <td style="font-weight: 700; color: #94A3B8;"><?= $i ?></td>
                                <td>
                                    <div style="font-weight: 800; color: #0F172A;"><?= htmlspecialchars($r['student_name']) ?></div>
                                    <div style="font-size: 0.76rem; color: #64748B; margin-top: 1px;">
                                        <i class="fa-solid fa-users"></i> <?= htmlspecialchars($group_lbl) ?> &bull; ID: <?= htmlspecialchars($r['student_no'] ?: 'N/A') ?>
                                    </div>
                                    <?php if (!empty($r['member2_name'])): ?>
                                        <div style="font-size: 0.72rem; color: #047857; margin-top: 1px;">
                                            Members: <?= htmlspecialchars($r['member2_name'] . (!empty($r['member3_name']) ? ', ' . $r['member3_name'] : '')) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge-course badge-<?= strtolower($r['dept_code']) ?>">
                                        <i class="fa-solid fa-graduation-cap"></i> <?= htmlspecialchars($r['dept_code']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="font-weight: 700; color: #1E293B; line-height: 1.35; max-width: 380px;">
                                        <?= htmlspecialchars($r['effective_topic']) ?>
                                    </div>
                                    <div style="font-size: 0.74rem; color: #94A3B8; margin-top: 2px;">
                                        A.Y. <?= htmlspecialchars($r['effective_ay']) ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($r['adviser_name'])): ?>
                                        <span style="font-weight: 700; color: #047857;">
                                            <i class="fa-solid fa-user-check"></i> <?= htmlspecialchars($r['adviser_name']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #94A3B8; font-style: italic;">
                                            <i class="fa-solid fa-circle-question"></i> Pending Match
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($r['defense_stage'])): ?>
                                        <div style="font-weight: 700; color: #334155; font-size: 0.8rem;">
                                            <i class="fa-solid fa-bookmark" style="color: #059669;"></i> <?= htmlspecialchars($r['defense_stage']) ?>
                                        </div>
                                        <?php if (!empty($r['mom_verdict'])): ?>
                                            <div class="badge-verdict" style="margin-top: 3px;">
                                                <i class="fa-solid fa-gavel"></i> <?= htmlspecialchars($r['mom_verdict']) ?>
                                                <?php if (!empty($r['mom_grade'])): ?>
                                                    (<?= number_format((float)$r['mom_grade'], 2) ?>)
                                                <?php endif; ?>
                                            </div>
                                        <?php elseif ($r['defense_status'] === 'scheduled'): ?>
                                            <span class="badge-status pending" style="margin-top: 3px;">
                                                <i class="fa-regular fa-clock"></i> Scheduled
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: #94A3B8; font-size: 0.78rem;">Not Scheduled Yet</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge-status <?= $is_prop ? 'pending' : 'approved' ?>">
                                        <?= htmlspecialchars($r['effective_cc']) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════════
             PANEL 2: DEFENSE DELIBERATIONS & VERDICTS REGISTRY
        ════════════════════════════════════════════════════════════════════ -->
        <div class="tab-content-panel" id="panelDeliberations" style="display: none;">
            <div class="rpt-table-container">
                <table class="rpt-data-table" id="tableDelib">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date &amp; Time</th>
                            <th>Defense Session &amp; Candidate</th>
                            <th>Degree Course</th>
                            <th>Venue</th>
                            <th>Committee Chair &amp; Documenter</th>
                            <th>Deliberation Verdict</th>
                            <th>Grade Average</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($all_deliberations)): ?>
                            <tr><td colspan="8" style="text-align: center; padding: 2rem; color: #94A3B8;">No defense deliberation sessions recorded yet.</td></tr>
                        <?php else: ?>
                            <?php $i = 0; foreach ($all_deliberations as $d): $i++;
                                $date_str = date('M j, Y', strtotime($d['meeting_date']));
                                $time_str = date('g:i A', strtotime($d['start_time'])) . (!empty($d['end_time']) ? ' - ' . date('g:i A', strtotime($d['end_time'])) : '');
                                $cand_name = !empty($d['group_name']) ? "Group {$d['group_name']} ({$d['student_name']})" : $d['student_name'];
                            ?>
                            <tr class="rpt-row-item"
                                data-degree="<?= htmlspecialchars($d['dept_code']) ?>"
                                data-search="<?= strtolower(htmlspecialchars($d['title'] . ' ' . $cand_name . ' ' . ($d['panel_chair_name'] ?? '') . ' ' . ($d['venue'] ?? '') . ' ' . ($d['decision_verdict'] ?? ''))) ?>">
                                <td style="font-weight: 700; color: #94A3B8;"><?= $i ?></td>
                                <td>
                                    <div style="font-weight: 800; color: #0F172A;"><?= $date_str ?></div>
                                    <div style="font-size: 0.74rem; color: #64748B;"><?= $time_str ?></div>
                                </td>
                                <td>
                                    <div style="font-weight: 800; color: #1E293B; line-height: 1.35; max-width: 320px;">
                                        <?= htmlspecialchars($d['title']) ?>
                                    </div>
                                    <div style="font-size: 0.75rem; color: #059669; font-weight: 700; margin-top: 2px;">
                                        <i class="fa-solid fa-user-graduate"></i> <?= htmlspecialchars($cand_name) ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge-course badge-<?= strtolower($d['dept_code']) ?>">
                                        <?= htmlspecialchars($d['dept_code']) ?>
                                    </span>
                                    <div style="font-size: 0.72rem; color: #64748B; margin-top: 2px;">
                                        <?= htmlspecialchars($d['course_code']) ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 600; color: #475569;"><?= htmlspecialchars($d['venue'] ?? 'CEIT Hall') ?></div>
                                </td>
                                <td>
                                    <div style="font-weight: 700; color: #0F172A;">
                                        <i class="fa-solid fa-crown" style="color: #D97706; font-size: 0.75rem;"></i> <?= htmlspecialchars($d['panel_chair_name'] ?? 'TBA') ?>
                                    </div>
                                    <div style="font-size: 0.74rem; color: #64748B; margin-top: 2px;">
                                        MOM: <?= htmlspecialchars($d['mom_documenter_name'] ?? 'Assigned Documenter') ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($d['decision_verdict'])): ?>
                                        <span class="badge-verdict">
                                            <i class="fa-solid fa-gavel"></i> <?= htmlspecialchars($d['decision_verdict']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge-status pending"><i class="fa-regular fa-clock"></i> Scheduled</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($d['grade_average'])): ?>
                                        <span style="font-size: 1rem; font-weight: 800; color: #047857;">
                                            <?= number_format((float)$d['grade_average'], 2) ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #94A3B8;">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════════
             PANEL 3: FACULTY ADVISING & COMMITTEE PORTFOLIO
        ════════════════════════════════════════════════════════════════════ -->
        <div class="tab-content-panel" id="panelFaculty" style="display: none;">
            <div class="rpt-table-container">
                <table class="rpt-data-table" id="tableFaculty">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Faculty Name &amp; Contact</th>
                            <th>Department Program</th>
                            <th>Academic Expertise</th>
                            <th>Adviser Load</th>
                            <th>Panel Commitments</th>
                            <th>Role Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($all_faculty)): ?>
                            <tr><td colspan="7" style="text-align: center; padding: 2rem; color: #94A3B8;">No faculty members registered.</td></tr>
                        <?php else: ?>
                            <?php $i = 0; foreach ($all_faculty as $f): $i++; ?>
                            <tr class="rpt-row-item"
                                data-degree="<?= htmlspecialchars($f['dept_code']) ?>"
                                data-search="<?= strtolower(htmlspecialchars($f['name'] . ' ' . $f['email'] . ' ' . $f['department'] . ' ' . $f['expertise'])) ?>">
                                <td style="font-weight: 700; color: #94A3B8;"><?= $i ?></td>
                                <td>
                                    <div style="font-weight: 800; color: #0F172A;"><?= htmlspecialchars($f['name']) ?></div>
                                    <div style="font-size: 0.76rem; color: #64748B;"><?= htmlspecialchars($f['email']) ?></div>
                                </td>
                                <td>
                                    <span class="badge-course badge-<?= strtolower($f['dept_code']) ?>">
                                        <?= htmlspecialchars($f['dept_code']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="font-size: 0.8rem; color: #475569; max-width: 250px;">
                                        <?= htmlspecialchars($f['expertise'] ?: 'General Engineering & Computing') ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 800; color: #047857; font-size: 0.95rem;">
                                        <?= $f['advisee_count'] ?> / <?= $f['capacity'] ?>
                                    </div>
                                    <div style="font-size: 0.72rem; color: #94A3B8;">Advisee Research Groups</div>
                                </td>
                                <td>
                                    <div style="font-weight: 700; color: #2563EB;">
                                        <?= $f['panel_count'] ?> Paneling &bull; <?= $f['chair_count'] ?> as Chair
                                    </div>
                                </td>
                                <td>
                                    <span class="badge-status <?= $f['role'] === 'adviser' ? 'approved' : 'pending' ?>">
                                        <?= ucfirst(htmlspecialchars($f['role'])) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════════
             PANEL 4: MANUSCRIPTS & SUBMISSIONS LOG
        ════════════════════════════════════════════════════════════════════ -->
        <div class="tab-content-panel" id="panelDocuments" style="display: none;">
            <div class="rpt-table-container">
                <table class="rpt-data-table" id="tableDocs">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Student / Group</th>
                            <th>Document Title &amp; Type</th>
                            <th>Version</th>
                            <th>Assigned Reviewer</th>
                            <th>Review Status</th>
                            <th>Adviser Feedback</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($all_submissions)): ?>
                            <tr><td colspan="7" style="text-align: center; padding: 2rem; color: #94A3B8;">No manuscripts submitted yet in the database.</td></tr>
                        <?php else: ?>
                            <?php $i = 0; foreach ($all_submissions as $s): $i++;
                                $cand = !empty($s['group_name']) ? "Group {$s['group_name']} ({$s['student_name']})" : $s['student_name'];
                                $badge_class = 'pending';
                                if ($s['status'] === 'approved') $badge_class = 'approved';
                                elseif ($s['status'] === 'needs_revision') $badge_class = 'revision';
                            ?>
                            <tr class="rpt-row-item"
                                data-degree="all"
                                data-search="<?= strtolower(htmlspecialchars($cand . ' ' . $s['title'] . ' ' . $s['doc_type'] . ' ' . ($s['adviser_name'] ?? '') . ' ' . $s['status'] . ' ' . ($s['feedback'] ?? ''))) ?>">
                                <td style="font-weight: 700; color: #94A3B8;"><?= $i ?></td>
                                <td>
                                    <div style="font-weight: 800; color: #0F172A;"><?= htmlspecialchars($cand) ?></div>
                                </td>
                                <td>
                                    <div style="font-weight: 700; color: #1E293B; line-height: 1.35; max-width: 320px;">
                                        <?= htmlspecialchars($s['title']) ?>
                                    </div>
                                    <div style="font-size: 0.74rem; color: #047857; margin-top: 1px;">
                                        <i class="fa-solid fa-file-pdf"></i> <?= htmlspecialchars($s['doc_type'] ?: 'Manuscript Draft') ?>
                                    </div>
                                </td>
                                <td>
                                    <span style="font-weight: 700; background: #F1F5F9; padding: 2px 6px; border-radius: 4px; font-size: 0.75rem;">
                                        <?= htmlspecialchars($s['version'] ?: 'v1.0') ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="font-weight: 700; color: #047857;">
                                        <?= htmlspecialchars($s['adviser_name'] ?: 'Adviser Assigned') ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge-status <?= $badge_class ?>">
                                        <?= ucfirst(str_replace('_', ' ', htmlspecialchars($s['status']))) ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="font-size: 0.78rem; color: #475569; max-width: 280px; font-style: italic;">
                                        <?= htmlspecialchars($s['feedback'] ?: 'No adviser feedback written yet.') ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Official University Print Footer -->
    <div class="rpt-print-footer">
        <strong>Caraga State University &bull; Cabadbaran Campus</strong> — College of Engineering and Information Technology<br>
        Capstone Matchmaking, Defense Deliberations &amp; Institutional Research System &bull; <?= $generated_date ?>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     CHART.JS & DYNAMIC FILTERING SCRIPT
════════════════════════════════════════════════════════════════════════════ -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let currentReportCourse = 'all';

function switchReportTab(tabId, btn) {
    document.querySelectorAll('.rpt-tab-btn').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');

    document.querySelectorAll('.tab-content-panel').forEach(p => p.style.display = 'none');
    
    if (tabId === 'roster') document.getElementById('panelRoster').style.display = 'block';
    else if (tabId === 'deliberations') document.getElementById('panelDeliberations').style.display = 'block';
    else if (tabId === 'faculty') document.getElementById('panelFaculty').style.display = 'block';
    else if (tabId === 'documents') document.getElementById('panelDocuments').style.display = 'block';
}

function filterReportByCourse(courseCode, btn) {
    currentReportCourse = courseCode;
    document.querySelectorAll('.rpt-course-btn').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    applyReportFilters();
}

function searchReportTables(query) {
    applyReportFilters();
}

function applyReportFilters() {
    const q = (document.getElementById('reportSearchInput') ? document.getElementById('reportSearchInput').value : '').toLowerCase().trim();
    const rows = document.querySelectorAll('.rpt-row-item');

    let visibleRoster = 0;
    let visibleDelib = 0;
    let visibleFaculty = 0;
    let visibleDocs = 0;

    rows.forEach(row => {
        const rowDegree = row.getAttribute('data-degree') || 'all';
        const searchTxt = row.getAttribute('data-search') || '';

        let courseMatch = (currentReportCourse === 'all' || rowDegree === 'all' || rowDegree === currentReportCourse);
        let textMatch = (!q || searchTxt.includes(q));

        if (courseMatch && textMatch) {
            row.style.display = '';
            if (row.closest('#tableRoster')) visibleRoster++;
            else if (row.closest('#tableDelib')) visibleDelib++;
            else if (row.closest('#tableFaculty')) visibleFaculty++;
            else if (row.closest('#tableDocs')) visibleDocs++;
        } else {
            row.style.display = 'none';
        }
    });

    const bRoster = document.getElementById('badgeCountRoster');
    const bDelib = document.getElementById('badgeCountDelib');
    const bFaculty = document.getElementById('badgeCountFaculty');
    const bDocs = document.getElementById('badgeCountDocs');

    if (bRoster) bRoster.innerText = visibleRoster;
    if (bDelib) bDelib.innerText = visibleDelib;
    if (bFaculty) bFaculty.innerText = visibleFaculty;
    if (bDocs) bDocs.innerText = visibleDocs;
}

document.addEventListener('DOMContentLoaded', function() {
    Chart.defaults.font.family = "'Inter', 'Outfit', sans-serif";
    Chart.defaults.font.size   = 11.5;
    Chart.defaults.color       = '#64748B';

    // ── 1. User Roles Doughnut Chart (All 17 Active Users in DB) ──
    const ctxRoles = document.getElementById('userRolesChart');
    if (ctxRoles) {
        new Chart(ctxRoles.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Students (<?= $stat_students ?>)', 'Faculty Advisers (<?= $stat_faculty ?>)', 'Panel Members (<?= $stat_panels ?>)', 'Secretariat (<?= $stat_secretaries ?>)', 'Admins (<?= $stat_admins ?>)'],
                datasets: [{
                    data: [<?= $stat_students ?>, <?= $stat_faculty ?>, <?= $stat_panels ?>, <?= $stat_secretaries ?>, <?= $stat_admins ?>],
                    backgroundColor: ['#059669', '#F59E0B', '#2563EB', '#7C3AED', '#0F172A'],
                    borderWidth: 3,
                    borderColor: '#FFFFFF',
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { padding: 12, usePointStyle: true, pointStyle: 'circle', font: { weight: '600', size: 10.5 } }
                    },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => ` ${ctx.label}: ${ctx.parsed} user accounts`
                        }
                    }
                },
                cutout: '66%'
            }
        });
    }

    // ── 2. Matchmaking & Workflow Bar Chart ──
    const ctxWorkflow = document.getElementById('workflowPipelineChart');
    if (ctxWorkflow) {
        new Chart(ctxWorkflow.getContext('2d'), {
            type: 'bar',
            data: {
                labels: ['Match Accepted', 'Match Pending', 'Docs Approved', 'Needs Revision'],
                datasets: [{
                    label: 'Records',
                    data: [<?= $stat_req_accepted ?>, <?= $stat_req_pending ?>, <?= $stat_docs_approved ?>, <?= $stat_docs_revision ?>],
                    backgroundColor: [
                        'rgba(5, 150, 105, 0.85)',
                        'rgba(245, 158, 11, 0.85)',
                        'rgba(37, 99, 235, 0.85)',
                        'rgba(220, 38, 38, 0.85)'
                    ],
                    borderRadius: { topLeft: 6, topRight: 6 },
                    barPercentage: 0.6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false }, ticks: { font: { weight: '600', size: 10 } } },
                    y: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 }, grid: { color: '#F1F5F9' } }
                }
            }
        });
    }

    // ── 3. Degree Programs Distribution Chart ──
    const ctxDept = document.getElementById('deptDistributionChart');
    if (ctxDept) {
        new Chart(ctxDept.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: [
                    'BSIT (<?= $total_per_course['BSIT'] ?>)',
                    'BSCpE (<?= $total_per_course['BSCpE'] ?>)',
                    'BSEE (<?= $total_per_course['BSEE'] ?>)'
                ],
                datasets: [{
                    data: [<?= $total_per_course['BSIT'] ?>, <?= $total_per_course['BSCpE'] ?>, <?= $total_per_course['BSEE'] ?>],
                    backgroundColor: ['#047857', '#1D4ED8', '#D97706'],
                    borderWidth: 3,
                    borderColor: '#FFFFFF',
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { padding: 14, usePointStyle: true, pointStyle: 'circle', font: { weight: '600', size: 11 } }
                    },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => ` ${ctx.label}: ${ctx.parsed} stakeholders`
                        }
                    }
                },
                cutout: '66%'
            }
        });
    }
});
</script>
