<?php
// ─── Course Filter & Setup ──────────────────────────────────────────────────
$dept = isset($_GET['dept']) ? trim($_GET['dept']) : 'all';

if (!function_exists('isDeptActive')) {
    function isDeptActive($current, $target) {
        if ($target === 'all') {
            return ($current === 'all' || empty($current)) ? 'active' : '';
        }
        return $current === $target ? 'active' : '';
    }
}

// ─── Ensure Active Panels list is loaded ─────────────────────────────────────
if (!isset($active_panels) || !$active_panels) {
    $active_panels = $conn->query("SELECT u.id, u.name, p.department FROM user u JOIN panel_profile p ON u.id = p.user_id WHERE u.role='panel' AND u.account_status='active' ORDER BY u.name");
}

// ─── Capstone Registry Pagination, Search & A.Y. / Course Filters ─────────
$search_thesis = isset($_GET['search_thesis']) ? trim($_GET['search_thesis']) : '';
$t_page        = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$t_limit       = isset($_GET['limit']) ? max(2, min(50, (int)$_GET['limit'])) : 6;
$t_offset      = ($t_page - 1) * $t_limit;
$ay_filter     = isset($_GET['ay']) ? trim($_GET['ay']) : '';
$cat_filter    = isset($_GET['cat']) ? trim($_GET['cat']) : 'all';

// Available Academic Years for dropdown
$available_ays_theses = get_available_academic_years($conn);
$curr_ay_theses = calculate_academic_year(date('Y-m-d'));
if (!in_array($curr_ay_theses, $available_ays_theses)) {
    array_unshift($available_ays_theses, $curr_ay_theses);
}

// Where condition
$t_where = ["sp.thesis_topic != ''"];

if ($dept !== '' && $dept !== 'all') {
    $dept_clean = $conn->real_escape_string(preg_replace('/\s*\(.*?\)/', '', $dept));
    $dept_escaped = $conn->real_escape_string($dept);
    $t_where[] = "(sp.department='$dept_escaped' OR sp.department='$dept_clean' OR sp.department LIKE '%$dept_clean%' OR cg.department='$dept_escaped' OR cg.department='$dept_clean' OR cg.department LIKE '%$dept_clean%')";
}

if ($ay_filter !== '') {
    $ay_clean = trim(preg_replace('/^A\.?Y\.?\s*/i', '', $ay_filter));
    $ay_esc = $conn->real_escape_string($ay_clean);
    $t_where[] = "(sp.academic_year LIKE '%$ay_esc%' OR cg.academic_year LIKE '%$ay_esc%' OR EXISTS (SELECT 1 FROM defense_meetings dm WHERE (dm.student_id = u.id OR (sp.group_id > 0 AND dm.group_id = sp.group_id)) AND dm.academic_year LIKE '%$ay_esc%'))";
}

if ($cat_filter === 'it198') {
    $t_where[] = "(sp.course_code = 'IT 198' OR cg.course_code = 'IT 198' OR EXISTS (SELECT 1 FROM defense_meetings dm WHERE (dm.student_id = u.id OR (sp.group_id > 0 AND dm.group_id = sp.group_id)) AND (dm.course_code = 'IT 198' OR dm.defense_category = 'Proposal Defense')))";
} elseif ($cat_filter === 'it199') {
    $t_where[] = "(sp.course_code = 'IT 199' OR cg.course_code = 'IT 199' OR EXISTS (SELECT 1 FROM defense_meetings dm WHERE (dm.student_id = u.id OR (sp.group_id > 0 AND dm.group_id = sp.group_id)) AND (dm.course_code = 'IT 199' OR dm.defense_category = 'Final Defense')) OR (sp.course_code IS NULL AND cg.course_code IS NULL))";
}

if ($search_thesis !== '') {
    $st_esc = $conn->real_escape_string($search_thesis);
    $t_where[] = "(u.name LIKE '%$st_esc%' OR sp.thesis_topic LIKE '%$st_esc%' OR sp.skills LIKE '%$st_esc%' OR cg.group_name LIKE '%$st_esc%' OR cg.member2_name LIKE '%$st_esc%' OR cg.member3_name LIKE '%$st_esc%')";
}
$t_where_clause = implode(' AND ', $t_where);

// Total count
$total_theses_res = $conn->query("SELECT COUNT(*) as c FROM student_profile sp JOIN user u ON sp.user_id = u.id LEFT JOIN capstone_group cg ON sp.group_id = cg.id WHERE $t_where_clause");
$total_theses_count = (int)($total_theses_res ? $total_theses_res->fetch_assoc()['c'] : 0);
$t_total_pages = max(1, (int)ceil($total_theses_count / $t_limit));

// Fetch counts for IT 198 vs IT 199 for badges
$it198_theses_count = (int)($conn->query("SELECT COUNT(*) as c FROM student_profile sp JOIN user u ON sp.user_id = u.id LEFT JOIN capstone_group cg ON sp.group_id = cg.id WHERE sp.thesis_topic != '' AND (sp.course_code = 'IT 198' OR cg.course_code = 'IT 198' OR EXISTS (SELECT 1 FROM defense_meetings dm WHERE (dm.student_id = u.id OR (sp.group_id > 0 AND dm.group_id = sp.group_id)) AND (dm.course_code = 'IT 198' OR dm.defense_category = 'Proposal Defense')))")->fetch_assoc()['c'] ?? 0);
$it199_theses_count = (int)($conn->query("SELECT COUNT(*) as c FROM student_profile sp JOIN user u ON sp.user_id = u.id LEFT JOIN capstone_group cg ON sp.group_id = cg.id WHERE sp.thesis_topic != '' AND (sp.course_code = 'IT 199' OR cg.course_code = 'IT 199' OR EXISTS (SELECT 1 FROM defense_meetings dm WHERE (dm.student_id = u.id OR (sp.group_id > 0 AND dm.group_id = sp.group_id)) AND (dm.course_code = 'IT 199' OR dm.defense_category = 'Final Defense')) OR (sp.course_code IS NULL AND cg.course_code IS NULL))")->fetch_assoc()['c'] ?? 0);

// Fetch paginated records
$paginated_theses = $conn->query("
    SELECT u.name as student_name, sp.user_id, sp.thesis_topic, sp.skills, sp.manuscript_file, sp.group_id, sp.department,
           sp.academic_year as student_ay, sp.course_code as student_cc,
           cg.group_name, cg.member2_name, cg.member3_name, cg.academic_year as group_ay, cg.course_code as group_cc,
           (SELECT dm.defense_stage FROM defense_meetings dm WHERE (dm.student_id = u.id OR (sp.group_id > 0 AND dm.group_id = sp.group_id)) ORDER BY dm.id DESC LIMIT 1) as defense_stage,
           (SELECT dm.defense_category FROM defense_meetings dm WHERE (dm.student_id = u.id OR (sp.group_id > 0 AND dm.group_id = sp.group_id)) ORDER BY dm.id DESC LIMIT 1) as defense_cat,
           (SELECT dm.course_code FROM defense_meetings dm WHERE (dm.student_id = u.id OR (sp.group_id > 0 AND dm.group_id = sp.group_id)) ORDER BY dm.id DESC LIMIT 1) as defense_cc,
           (SELECT dm.academic_year FROM defense_meetings dm WHERE (dm.student_id = u.id OR (sp.group_id > 0 AND dm.group_id = sp.group_id)) ORDER BY dm.id DESC LIMIT 1) as defense_ay
    FROM student_profile sp 
    JOIN user u ON sp.user_id = u.id 
    LEFT JOIN capstone_group cg ON sp.group_id = cg.id
    WHERE $t_where_clause 
    ORDER BY u.name ASC 
    LIMIT $t_limit OFFSET $t_offset
");

// Fetch all students for group assignment modal
$all_students_raw = $conn->query("SELECT u.id, u.name, sp.department, sp.student_id as student_no, sp.group_id FROM user u JOIN student_profile sp ON u.id = sp.user_id WHERE u.role='student' AND u.account_status != 'archived' ORDER BY u.name ASC");
$student_list = [];
if ($all_students_raw) {
    while($st_row = $all_students_raw->fetch_assoc()) {
        $student_list[] = $st_row;
    }
}
?>

<!-- Defense Category Switcher: Separate IT 198 (Proposal Defense) and IT 199 (Final Defense) -->
<div style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1.15rem;">
    <?php
        $base_url_all = '?tab=theses' . ($dept !== 'all' ? '&dept=' . urlencode($dept) : '') . ($ay_filter !== '' ? '&ay=' . urlencode($ay_filter) : '') . '&cat=all';
        $base_url_198 = '?tab=theses' . ($dept !== 'all' ? '&dept=' . urlencode($dept) : '') . ($ay_filter !== '' ? '&ay=' . urlencode($ay_filter) : '') . '&cat=it198';
        $base_url_199 = '?tab=theses' . ($dept !== 'all' ? '&dept=' . urlencode($dept) : '') . ($ay_filter !== '' ? '&ay=' . urlencode($ay_filter) : '') . '&cat=it199';
    ?>
    <a href="<?= $base_url_all ?>" class="btn" style="padding: 0.5rem 1.1rem; border-radius: 8px; font-weight: 700; font-size: 0.85rem; text-decoration: none; <?= $cat_filter === 'all' ? 'background: #022C22; color: #FFFFFF; border: 1.5px solid #022C22;' : 'background: #F8FAFC; color: #475569; border: 1.5px solid #E2E8F0;' ?>">
        <i class="fa-solid fa-layer-group"></i> All Titles
    </a>
    <a href="<?= $base_url_198 ?>" class="btn" style="padding: 0.5rem 1.1rem; border-radius: 8px; font-weight: 700; font-size: 0.85rem; text-decoration: none; <?= $cat_filter === 'it198' ? 'background: #D97706; color: #FFFFFF; border: 1.5px solid #D97706;' : 'background: #FFFBEB; color: #B45309; border: 1.5px solid #FDE68A;' ?>">
        <i class="fa-solid fa-file-signature"></i> Proposal Defense Title (<?= $it198_theses_count ?>)
    </a>
    <a href="<?= $base_url_199 ?>" class="btn" style="padding: 0.5rem 1.1rem; border-radius: 8px; font-weight: 700; font-size: 0.85rem; text-decoration: none; <?= $cat_filter === 'it199' ? 'background: #047857; color: #FFFFFF; border: 1.5px solid #047857;' : 'background: #ECFDF5; color: #047857; border: 1.5px solid #A7F3D0;' ?>">
        <i class="fa-solid fa-graduation-cap"></i> Final Defense (<?= $it199_theses_count ?>)
    </a>
</div>

<!-- Course Filter Tabs -->
<div class="theses-header-nav" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div class="dept-tabs" style="margin-bottom: 0;">
        <button class="dept-tab <?= isDeptActive($dept, 'all') ?>" onclick="window.location.href='?tab=theses&dept=all<?= ($ay_filter !== '' ? '&ay=' . urlencode($ay_filter) : '') . ($cat_filter !== 'all' ? '&cat=' . urlencode($cat_filter) : '') ?>'">
            <i class="fa-solid fa-layer-group"></i> All Courses
        </button>
        <button class="dept-tab <?= isDeptActive($dept, 'Computer Engineering') ?>" onclick="window.location.href='?tab=theses&dept=Computer Engineering<?= ($ay_filter !== '' ? '&ay=' . urlencode($ay_filter) : '') . ($cat_filter !== 'all' ? '&cat=' . urlencode($cat_filter) : '') ?>'">
            <i class="fa-solid fa-gear"></i> Computer Engineering (ComEng)
        </button>
        <button class="dept-tab <?= isDeptActive($dept, 'Information Technology') ?>" onclick="window.location.href='?tab=theses&dept=Information Technology<?= ($ay_filter !== '' ? '&ay=' . urlencode($ay_filter) : '') . ($cat_filter !== 'all' ? '&cat=' . urlencode($cat_filter) : '') ?>'">
            <i class="fa-solid fa-globe"></i> Information Technology (IT)
        </button>
        <button class="dept-tab <?= isDeptActive($dept, 'Electrical Engineering') ?>" onclick="window.location.href='?tab=theses&dept=Electrical Engineering<?= ($ay_filter !== '' ? '&ay=' . urlencode($ay_filter) : '') . ($cat_filter !== 'all' ? '&cat=' . urlencode($cat_filter) : '') ?>'">
            <i class="fa-solid fa-plug"></i> Electrical Engineering (EE)
        </button>
    </div>
</div>

<div class="card">
    <div class="card-header" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;">
        <div class="card-header-left" style="display: flex; align-items: center; gap: 0.65rem;">
            <i class="fa-solid fa-book-bookmark"></i>
            <h2>Capstone Registry & Panel Committee Matrix</h2>
            <span class="badge badge-info" style="margin-left: 0.35rem;">
                <?= $total_theses_count; ?> Registered<?= ($dept !== '' && $dept !== 'all') ? ' (' . htmlspecialchars($dept) . ')' : '' ?><?= ($cat_filter !== 'all') ? ' [' . ($cat_filter === 'it198' ? 'Proposal Defense Title' : 'Final Defense') . ']' : '' ?>
            </span>
        </div>
        
        <!-- Search bar, Academic Year & Category filter for Theses -->
        <form method="GET" action="" style="display: flex; gap: 0.4rem; align-items: center; margin: 0; flex-wrap: wrap;">
            <input type="hidden" name="tab" value="theses">
            <?php if ($dept !== '' && $dept !== 'all'): ?>
                <input type="hidden" name="dept" value="<?= htmlspecialchars($dept) ?>">
            <?php endif; ?>
            <?php if ($cat_filter !== 'all'): ?>
                <input type="hidden" name="cat" value="<?= htmlspecialchars($cat_filter) ?>">
            <?php endif; ?>

            <!-- Academic Year Filter (Typable with Datalist) -->
            <div style="position: relative; display: inline-flex; align-items: center;">
                <input type="text" 
                       name="ay" 
                       id="ayThesesFilter"
                       list="ayThesesDatalist" 
                       value="<?= htmlspecialchars($ay_filter) ?>" 
                       placeholder="Type A.Y. (e.g. 2026-2027)" 
                       style="padding: 0.45rem 1.8rem 0.45rem 0.75rem; border: 1.5px solid #047857; border-radius: 8px; font-size: 0.84rem; font-weight: 700; background: #FFFFFF; color: #0F172A; min-width: 175px;" 
                       onchange="this.form.submit()"
                       autocomplete="off">
                <datalist id="ayThesesDatalist">
                    <option value="">All Academic Years</option>
                    <?php foreach($available_ays_theses as $ay_opt): ?>
                        <option value="<?= htmlspecialchars($ay_opt) ?>">A.Y. <?= htmlspecialchars($ay_opt) ?></option>
                    <?php endforeach; ?>
                </datalist>
                <?php if(!empty($ay_filter)): ?>
                    <a href="?dept=<?= urlencode($dept) ?>&cat=<?= urlencode($cat_filter) ?>&search_thesis=<?= urlencode($search_thesis) ?>" 
                       title="Clear Academic Year" 
                       style="position: absolute; right: 0.5rem; color: #94A3B8; text-decoration: none; font-size: 0.85rem;">
                        <i class="fa-solid fa-circle-xmark"></i>
                    </a>
                <?php endif; ?>
            </div>

            <div style="position: relative;">
                <input type="text" 
                       name="search_thesis" 
                       value="<?= htmlspecialchars($search_thesis) ?>" 
                       placeholder="Search topic, student, skill..." 
                       style="padding: 0.45rem 0.85rem 0.45rem 2rem; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 0.85rem; width: 200px; background: #F8FAFC;">
                <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 0.7rem; top: 50%; transform: translateY(-50%); color: #94A3B8; font-size: 0.8rem;"></i>
            </div>

            <button type="submit" class="btn-primary" style="padding: 0.45rem 0.85rem; font-size: 0.82rem;">Filter</button>
            <?php if ($search_thesis !== '' || ($dept !== '' && $dept !== 'all') || $ay_filter !== '' || $cat_filter !== 'all'): ?>
                <a href="?tab=theses" class="btn" style="padding: 0.45rem 0.75rem; background: #E2E8F0; color: #475569; border-radius: 8px; font-size: 0.82rem;" title="Reset filter">
                    <i class="fa-solid fa-rotate-left"></i>
                </a>
            <?php endif; ?>
        </form>
    </div>

    <div class="card-body">
        <?php if($total_theses_count == 0): ?>
            <div style="text-align: center; color: var(--text-muted); padding: 3.5rem 1rem;">
                <i class="fa-solid fa-folder-open fa-3x" style="color: #CBD5E1; margin-bottom: 1rem; display: block;"></i>
                <h3 style="color: #0F172A; font-size: 1.1rem; margin-bottom: 0.35rem;">
                    <?= ($dept !== '' && $dept !== 'all') ? "No " . htmlspecialchars($dept) . " Capstone Projects Found" : "No Capstone Projects Found" ?>
                </h3>
                <p style="color: #64748B; font-size: 0.88rem; margin: 0;">
                    <?= $search_thesis !== '' ? 'No submissions matched your search query. Try another keyword.' : 'Once ' . (($dept !== '' && $dept !== 'all') ? htmlspecialchars($dept) . ' ' : '') . 'students submit their research topics, they will appear here for panel assignments.' ?>
                </p>
            </div>
        <?php else: ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.15rem; margin-bottom: 1.5rem;">
                <?php while($th = $paginated_theses->fetch_assoc()): 
                    // Query group and 3 members
                    $th_group = null;
                    $th_members = [];
                    if (!empty($th['group_id'])) {
                        $th_gid = (int)$th['group_id'];
                        $th_gres = $conn->query("SELECT * FROM capstone_group WHERE id=$th_gid");
                        if ($th_gres && $th_gres->num_rows > 0) {
                            $th_group = $th_gres->fetch_assoc();
                            $th_members = get_group_members_roster($conn, $th_gid);
                        }
                    }
                    $th_mcount = count($th_members);
                    $th_gid = $th_group['id'] ?? 0;
                    $th_gname = $th_group['group_name'] ?? '';
                    $th_leader_id = $th_group['leader_id'] ?? $th['user_id'];
                    $th_m2_id = isset($th_members[1]) ? (int)($th_members[1]['student_id'] ?? 0) : 0;
                    $th_m3_id = isset($th_members[2]) ? (int)($th_members[2]['student_id'] ?? 0) : 0;
                    $th_m2_name = isset($th_members[1]) ? $th_members[1]['name'] : ($th_group['member2_name'] ?? '');
                    $th_m3_name = isset($th_members[2]) ? $th_members[2]['name'] : ($th_group['member3_name'] ?? '');

                    // Resolve course code (IT 198 vs IT 199), category, and Academic Year
                    $thesis_cc = !empty($th['defense_cc']) ? $th['defense_cc'] : (!empty($th['group_cc']) ? $th['group_cc'] : (!empty($th['student_cc']) ? $th['student_cc'] : 'IT 199'));
                    $thesis_cat = !empty($th['defense_cat']) ? $th['defense_cat'] : (($thesis_cc === 'IT 198') ? 'Proposal Defense' : 'Final Defense');
                    $thesis_ay = !empty($th['defense_ay']) ? $th['defense_ay'] : (!empty($th['group_ay']) ? $th['group_ay'] : (!empty($th['student_ay']) ? $th['student_ay'] : calculate_academic_year(date('Y-m-d'))));
                    $is_prop_thesis = ($thesis_cc === 'IT 198' || $thesis_cat === 'Proposal Defense');
                ?>
                    <div style="background: white; border: 1px solid #E2E8F0; border-left: 5px solid <?= $is_prop_thesis ? '#D97706' : '#059669' ?>; border-radius: 12px; padding: 1.25rem; box-shadow: var(--shadow-sm); display: flex; flex-direction: column; justify-content: space-between; gap: 0.85rem; transition: box-shadow 0.2s ease;">
                        <div>
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem;">
                                <div style="display: flex; align-items: center; gap: 0.65rem;">
                                    <div style="width: 36px; height: 36px; border-radius: 50%; background: var(--csu-green-subtle); color: var(--csu-green); display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.95rem; flex-shrink: 0;">
                                        <?= strtoupper(substr($th['student_name'], 0, 1)); ?>
                                    </div>
                                    <div style="overflow: hidden;">
                                        <div style="font-weight: 700; color: #0F172A; font-size: 0.95rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                            <?= htmlspecialchars($th['student_name']); ?>
                                        </div>
                                        <div style="font-size: 0.75rem; color: #64748B;">
                                            Student Candidate<?php if(!empty($th['department'])): ?> &bull; <span style="color: var(--csu-green); font-weight: 600;"><?= htmlspecialchars($th['department']) ?></span><?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php if($th_group): ?>
                                    <span class="badge <?= $th_mcount >= 3 ? 'badge-success' : 'badge-warning' ?>" style="font-size: 0.72rem;">
                                        <i class="fa-solid fa-users"></i> <?= $th_mcount ?>/3 Members
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-danger" style="font-size: 0.72rem;">No Group</span>
                                <?php endif; ?>
                            </div>

                            <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 0.85rem; margin-bottom: 0.75rem;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.45rem; flex-wrap: wrap; gap: 0.35rem;">
                                    <span style="font-size: 0.72rem; font-weight: 800; padding: 0.2rem 0.55rem; border-radius: 6px; <?= $is_prop_thesis ? 'background: #FEF3C7; color: #B45309; border: 1px solid #FDE68A;' : 'background: #E0E7FF; color: #3730A3; border: 1px solid #C7D2FE;' ?>">
                                        <i class="fa-solid <?= $is_prop_thesis ? 'fa-file-signature' : 'fa-graduation-cap' ?>"></i> <?= $is_prop_thesis ? 'Proposal Defense Title' : 'Final Defense' ?>
                                    </span>
                                    <span style="font-size: 0.72rem; font-weight: 700; color: #334155; background: #FFFFFF; border: 1px solid #CBD5E1; padding: 0.2rem 0.55rem; border-radius: 6px;">
                                        <i class="fa-solid fa-calendar-days" style="color: #047857;"></i> A.Y. <?= htmlspecialchars($thesis_ay) ?>
                                    </span>
                                </div>
                                <h3 style="margin: 0; color: #1E293B; font-size: 0.95rem; font-weight: 700; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                    <?= htmlspecialchars($th['thesis_topic']); ?>
                                </h3>
                            </div>

                            <!-- 3 Group Members Box (Admin View) -->
                            <div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 8px; padding: 0.75rem; margin-bottom: 0.75rem;">
                                <div style="font-size: 0.72rem; color: #475569; font-weight: 700; text-transform: uppercase; margin-bottom: 0.35rem; display: flex; justify-content: space-between; align-items: center;">
                                    <span><i class="fa-solid fa-users" style="color: var(--csu-green);"></i> Group Members</span>
                                    <?php if($th_group): ?>
                                        <span style="color: var(--csu-green); font-size: 0.75rem; text-transform: none; font-weight: 700;"><?= htmlspecialchars($th_gname) ?></span>
                                    <?php endif; ?>
                                </div>

                                <?php if($th_group && $th_mcount > 0): ?>
                                    <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                                        <?php for($i = 0; $i < 3; $i++): $gm = $th_members[$i] ?? null; ?>
                                            <?php if($gm): ?>
                                                <div style="font-size: 0.78rem; color: #1E293B; display: flex; justify-content: space-between; align-items: center; background: #F8FAFC; padding: 0.25rem 0.5rem; border-radius: 4px; border: 1px solid #E2E8F0;">
                                                    <span style="font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 170px;">
                                                        <i class="fa-solid <?= $gm['is_leader'] ? 'fa-crown' : 'fa-user' ?>" style="color: <?= $gm['is_leader'] ? '#D97706' : '#047857' ?>; font-size: 0.7rem; margin-right: 0.25rem;"></i>
                                                        <?= htmlspecialchars($gm['name']) ?>
                                                    </span>
                                                    <span style="font-size: 0.68rem; color: <?= $gm['is_leader'] ? '#B45309' : '#64748B' ?>; font-weight: 700;">
                                                        <?= $gm['is_leader'] ? 'Leader' : 'Member '.($i+1) ?>
                                                    </span>
                                                </div>
                                            <?php else: ?>
                                                <div style="font-size: 0.75rem; color: #94A3B8; font-style: italic; background: #FAFAFA; padding: 0.2rem 0.5rem; border-radius: 4px; border: 1px dashed #CBD5E1;">
                                                    <i class="fa-regular fa-circle" style="font-size: 0.6rem;"></i> Vacant Slot <?= $i+1 ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                    </div>
                                <?php else: ?>
                                    <div style="font-size: 0.78rem; color: #94A3B8; font-style: italic; padding: 0.25rem 0;">
                                        No capstone group assigned yet.
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php 
                            $t_skills = array_filter(array_map('trim', explode(',', $th['skills'])));
                            if(!empty($t_skills)):
                            ?>
                                <div style="margin-bottom: 0.75rem;">
                                    <div style="font-size: 0.72rem; color: #64748B; font-weight: 700; text-transform: uppercase; margin-bottom: 0.35rem;">Skills & Keywords:</div>
                                    <div style="display: flex; flex-wrap: wrap; gap: 0.3rem;">
                                        <?php foreach($t_skills as $sk): ?>
                                            <span style="background: #FFFFFF; color: #334155; padding: 0.15rem 0.5rem; border-radius: 4px; font-size: 0.75rem; border: 1px solid #CBD5E1; font-weight: 500;"><?= htmlspecialchars($sk); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php
                            // Fetch assigned panels for this thesis
                            $student_id = $th['user_id'];
                            $panel_res = $conn->query("SELECT p.name, tp.status, tp.grade FROM thesis_panel tp JOIN user p ON tp.panel_id = p.id WHERE tp.student_id = $student_id");
                            ?>
                            <div style="border-top: 1px dashed #E2E8F0; padding-top: 0.75rem; margin-top: 0.5rem;">
                                <div style="font-size: 0.75rem; color: #64748B; font-weight: 700; text-transform: uppercase; margin-bottom: 0.4rem; display: flex; justify-content: space-between; align-items: center;">
                                    <span><i class="fa-solid fa-users" style="color: var(--primary-blue);"></i> Panel Committee</span>
                                    <span style="font-size: 0.7rem; color: #94A3B8;"><?= $panel_res->num_rows; ?> Assigned</span>
                                </div>
                                <?php if($panel_res->num_rows > 0): ?>
                                    <div style="display: flex; flex-direction: column; gap: 0.3rem; margin-bottom: 0.5rem;">
                                        <?php while($prow = $panel_res->fetch_assoc()): ?>
                                            <div style="font-size: 0.8rem; color: #1E293B; display: flex; justify-content: space-between; align-items: center; background: #F8FAFC; padding: 0.35rem 0.6rem; border-radius: 6px; border: 1px solid #E2E8F0;">
                                                <span style="font-weight: 600;"><?= htmlspecialchars($prow['name']); ?></span>
                                                <?php if($prow['status'] == 'graded'): ?>
                                                    <span style="color: #059669; font-weight: 700; font-size: 0.75rem;"><i class="fa-solid fa-check"></i> <?= $prow['grade']; ?>%</span>
                                                <?php else: ?>
                                                    <span style="color: #D97706; font-size: 0.75rem; font-weight: 600;"><i class="fa-regular fa-clock"></i> Pending</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                <?php else: ?>
                                    <div style="font-size: 0.78rem; color: #94A3B8; font-style: italic; margin-bottom: 0.5rem;">No panel members assigned yet.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 0.35rem; border-top: 1px solid #F1F5F9; padding-top: 0.75rem; flex-wrap: wrap;">
                            <button class="btn-primary" style="flex: 1.1; min-width: 105px; padding: 0.4rem 0.5rem; font-size: 0.78rem;" onclick="openAssignModal(<?= $th['user_id'] ?>, '<?= addslashes($th['student_name']) ?>')">
                                <i class="fa-solid fa-user-plus"></i> Panels
                            </button>
                            <button class="btn" style="flex: 1.1; min-width: 105px; padding: 0.4rem 0.5rem; font-size: 0.78rem; background: #ECFDF5; color: #047857; border: 1px solid #A7F3D0; border-radius: 8px; font-weight: 600;" onclick="openGroupModal(<?= $th_gid ?>, '<?= addslashes($th_gname) ?>', <?= $th_leader_id ?>, <?= $th_m2_id ?>, <?= $th_m3_id ?>, '<?= addslashes($th_m2_name) ?>', '<?= addslashes($th_m3_name) ?>')">
                                <i class="fa-solid fa-users-gear"></i> Manage Group
                            </button>
                            <?php if(!empty($th['manuscript_file'])): ?>
                                <a href="<?= htmlspecialchars($th['manuscript_file']); ?>" target="_blank" class="btn btn-outline" style="padding: 0.4rem 0.5rem; font-size: 0.78rem; border-radius: 8px;" title="View PDF">
                                    <i class="fa-solid fa-file-pdf" style="color: #EF4444;"></i>
                                </a>
                            <?php endif; ?>
                            
                            <button class="btn" style="padding: 0.4rem 0.5rem; background: #FEE2E2; color: #DC2626; border: 1px solid #FECACA; border-radius: 8px; font-size: 0.78rem;" onclick="deleteThesis(<?= (int)$th['user_id'] ?>, <?= htmlspecialchars(json_encode($th['student_name'] ?? ''), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($th['thesis_topic'] ?? ''), ENT_QUOTES) ?>)" title="Delete Capstone">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>

            <!-- Theses Pagination Bar -->
            <div style="display: flex; align-items: center; justify-content: space-between; padding-top: 1rem; border-top: 1px solid #E2E8F0; flex-wrap: wrap; gap: 0.75rem; font-size: 0.85rem; color: #64748B;">
                <div>
                    Showing <strong><?= min($total_theses_count, $t_offset + 1) ?></strong> to <strong><?= min($total_theses_count, $t_offset + $paginated_theses->num_rows) ?></strong> of <strong><?= number_format($total_theses_count) ?></strong> registered capstone projects
                </div>
                
                <div style="display: flex; gap: 0.35rem; align-items: center;">
                    <?php
                        $t_params = $_GET;
                        // Prev
                        $t_params['p'] = max(1, $t_page - 1);
                        $prev_t_url = '?' . http_build_query($t_params);
                    ?>
                    <a href="<?= $prev_t_url ?>" 
                       style="padding: 0.38rem 0.75rem; border: 1px solid #CBD5E1; background: #FFFFFF; color: #334155; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 0.82rem; <?= $t_page <= 1 ? 'opacity: 0.5; pointer-events: none;' : '' ?>">
                        <i class="fa-solid fa-chevron-left"></i> Prev
                    </a>

                    <?php
                        $t_start = max(1, $t_page - 2);
                        $t_end   = min($t_total_pages, $t_page + 2);
                        for ($i = $t_start; $i <= $t_end; $i++):
                            $t_params['p'] = $i;
                            $t_url = '?' . http_build_query($t_params);
                    ?>
                        <a href="<?= $t_url ?>" 
                           style="padding: 0.38rem 0.75rem; border: 1px solid <?= $t_page == $i ? '#059669' : '#CBD5E1' ?>; background: <?= $t_page == $i ? '#059669' : '#FFFFFF' ?>; color: <?= $t_page == $i ? '#FFFFFF' : '#334155' ?>; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 0.82rem;">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php
                        // Next
                        $t_params['p'] = min($t_total_pages, $t_page + 1);
                        $next_t_url = '?' . http_build_query($t_params);
                    ?>
                    <a href="<?= $next_t_url ?>" 
                       style="padding: 0.38rem 0.75rem; border: 1px solid #CBD5E1; background: #FFFFFF; color: #334155; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 0.82rem; <?= $t_page >= $t_total_pages ? 'opacity: 0.5; pointer-events: none;' : '' ?>">
                        Next <i class="fa-solid fa-chevron-right"></i>
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function openAssignModal(studentId, studentName) {
    document.getElementById('assign_student_id').value = studentId;
    document.getElementById('assign_student_name').textContent = studentName;
    document.getElementById('assignPanelModal').style.display = 'block';
}

function openGroupModal(groupId, groupName, leaderId, member2Id, member3Id, member2Name, member3Name) {
    document.getElementById('grp_modal_group_id').value = groupId || 0;
    document.getElementById('grp_modal_group_name').value = groupName || 'Capstone Research Group';
    document.getElementById('grp_modal_student_1').value = leaderId || '';
    document.getElementById('grp_modal_student_2').value = member2Id || '0';
    document.getElementById('grp_modal_student_3').value = member3Id || '0';
    document.getElementById('grp_modal_m2_name').value = member2Name || '';
    document.getElementById('grp_modal_m3_name').value = member3Name || '';
    document.getElementById('assignGroupModal').style.display = 'block';
}

let targetDeleteThesisId = null;

function deleteThesis(student_id, studentName, thesisTopic) {
    targetDeleteThesisId = student_id;
    const name = (studentName || 'Selected Student').trim();
    document.getElementById('del_thesis_student').textContent = name;
    document.getElementById('del_thesis_avatar').textContent = name.charAt(0).toUpperCase() || 'S';
    document.getElementById('del_thesis_topic').textContent = thesisTopic || 'Untitled Capstone Research';
    document.getElementById('deleteThesisModal').style.display = 'block';
}

function closeDeleteThesisModal() {
    document.getElementById('deleteThesisModal').style.display = 'none';
    targetDeleteThesisId = null;
}

function confirmDeleteThesis() {
    if (targetDeleteThesisId) {
        window.location.href = 'action.php?delete_thesis=' + targetDeleteThesisId;
    }
}

window.addEventListener('click', function(e) {
    if (e.target === document.getElementById('deleteThesisModal')) closeDeleteThesisModal();
});
document.addEventListener('keydown', function(e) {
    const m = document.getElementById('deleteThesisModal');
    if (e.key === 'Escape' && m && m.style.display === 'block') closeDeleteThesisModal();
});
</script>

<!-- University Delete Thesis Confirmation Modal -->
<div id="deleteThesisModal" class="modal">
    <div class="modal-content logout-modal-content" style="max-width: 470px;">
        <div class="logout-modal-header" style="background: linear-gradient(135deg, #064E3B 0%, #047857 55%, #022C22 100%); border-bottom: 3px solid #F59E0B;">
            <div class="logout-seal-wrapper">
                <img src="logo.png" alt="CSU Seal">
            </div>
            <div class="logout-univ-subtitle">Caraga State University</div>
            <div class="logout-college-subtitle">College of Engineering and Information Technology</div>
            <h2>Delete Capstone Record</h2>
        </div>
        <div class="logout-modal-body">
            <div class="logout-user-card">
                <div class="logout-user-avatar" id="del_thesis_avatar">S</div>
                <div class="logout-user-details" style="min-width: 0;">
                    <div class="logout-user-name" id="del_thesis_student">Student Name</div>
                    <div class="logout-user-role" style="white-space: normal;">
                        <span class="logout-session-dot" style="background: #DC2626; box-shadow: 0 0 0 2px rgba(220, 38, 38, 0.25); flex-shrink: 0;"></span>
                        <span id="del_thesis_topic" style="overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;">Capstone Topic</span>
                    </div>
                </div>
            </div>

            <div class="logout-prompt-title">Permanently Remove from Registry?</div>
            <p class="logout-prompt-desc">
                This will remove the <strong>capstone topic</strong> and <strong>uploaded manuscript</strong> from the CEIT Capstone Registry. This action <strong style="color: #DC2626;">cannot be undone</strong>.
            </p>

            <div class="logout-actions">
                <button type="button" class="btn-logout-cancel" onclick="closeDeleteThesisModal()">
                    <i class="fa-solid fa-arrow-left"></i> Cancel
                </button>
                <button type="button" onclick="confirmDeleteThesis()" class="btn-delete-thesis-confirm">
                    <i class="fa-solid fa-trash-can"></i> Delete Record
                </button>
            </div>

            <div class="logout-modal-footer-note">
                <i class="fa-solid fa-shield-halved"></i> Secured CEIT Academic Portal &bull; CSU Cabadbaran
            </div>
        </div>
    </div>
</div>

<style>
.btn-delete-thesis-confirm {
    flex: 1.15; padding: 0.65rem 1.1rem; border-radius: 10px;
    background: linear-gradient(135deg, #DC2626 0%, #991B1B 100%);
    color: #fff; border: none; font-weight: 700; cursor: pointer; font-size: 0.85rem;
    display: inline-flex; align-items: center; justify-content: center; gap: 0.45rem;
    box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); font-family: inherit;
}
.btn-delete-thesis-confirm:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(220, 38, 38, 0.4);
}
</style>

<!-- Assign Panel Modal -->
<div id="assignPanelModal" class="modal">
    <div class="modal-content" style="max-width: 480px;">
        <span class="close" onclick="document.getElementById('assignPanelModal').style.display='none'">&times;</span>
        <h2 style="margin-bottom: 0.5rem; color: var(--primary-blue); font-size: 1.15rem;"><i class="fa-solid fa-users"></i> Assign Panel Committee</h2>
        <p style="margin-bottom: 1.25rem; color: var(--text-muted); font-size: 0.85rem;">Assigning oral defense panelists for: <strong id="assign_student_name" style="color: #0F172A;"></strong></p>
        
        <form action="action.php" method="POST">
            <input type="hidden" name="assign_panel" value="1">
            <input type="hidden" name="student_id" id="assign_student_id">
            
            <div class="form-group">
                <label class="input-label">Select Panel Members (Up to 3 members) <span>*</span></label>
                <div style="display: grid; grid-template-columns: 1fr; gap: 0.45rem; margin-top: 0.35rem; max-height: 220px; overflow-y: auto; background: #F8FAFC; padding: 0.85rem; border-radius: 8px; border: 1px solid #E2E8F0;">
                    <?php 
                    $active_panels->data_seek(0);
                    if($active_panels->num_rows == 0):
                    ?>
                        <p style="color: var(--text-muted); font-size: 0.85rem; text-align: center; margin: 0; padding: 1rem;">No active panel members found in registry.</p>
                    <?php else:
                    while($pan = $active_panels->fetch_assoc()): 
                    ?>
                        <label style="display: flex; align-items: center; cursor: pointer; font-size: 0.88rem; color: #1E293B; padding: 0.5rem 0.75rem; background: white; border: 1px solid #E2E8F0; border-radius: 6px; transition: background 0.2s;">
                            <input type="checkbox" name="panel_id[]" value="<?php echo $pan['id']; ?>" style="margin-right: 0.65rem; width: 1.05rem; height: 1.05rem; accent-color: var(--primary-blue); cursor: pointer;">
                            <div style="font-weight: 600;"><?php echo htmlspecialchars($pan['name']); ?></div>
                            <span style="font-size: 0.75rem; color: #64748B; margin-left: 0.4rem;">(<?php echo htmlspecialchars($pan['department'] ?: 'Faculty'); ?>)</span>
                        </label>
                    <?php endwhile; endif; ?>
                </div>
            </div>
            
            <button type="submit" class="btn-primary" style="width: 100%; padding: 0.65rem; margin-top: 1rem; font-size: 0.88rem;">
                <i class="fa-solid fa-check"></i> Save Panel Assignment
            </button>
        </form>
    </div>
</div>

<!-- Assign / Manage Capstone Group Modal (3 Members) -->
<div id="assignGroupModal" class="modal">
    <div class="modal-content" style="max-width: 520px;">
        <span class="close" onclick="document.getElementById('assignGroupModal').style.display='none'">&times;</span>
        <h2 style="margin-bottom: 0.5rem; color: var(--csu-green); font-size: 1.15rem;">
            <i class="fa-solid fa-users-gear"></i> Manage Capstone Group
        </h2>
        <p style="margin-bottom: 1.25rem; color: var(--text-muted); font-size: 0.85rem;">
            Configure or reassign the 3 group members for this capstone research project.
        </p>

        <form action="action.php" method="POST">
            <input type="hidden" name="admin_save_group" value="1">
            <input type="hidden" name="group_id" id="grp_modal_group_id" value="0">

            <div class="form-group">
                <label class="input-label">Capstone Title <span>*</span></label>
                <input type="text" name="group_name" id="grp_modal_group_name" required class="form-control" style="width: 100%; padding: 0.6rem 0.85rem; border: 1px solid #CBD5E1; border-radius: 8px;">
            </div>

            <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 1rem; margin-bottom: 1rem;">
                <div style="font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 0.75rem;">
                    <i class="fa-solid fa-id-badge" style="color: var(--primary-blue);"></i> Group Members (Maximum 3 Students)
                </div>

                <!-- Member 1: Leader -->
                <div class="form-group" style="margin-bottom: 0.75rem;">
                    <label style="font-size: 0.8rem; font-weight: 600; color: #B45309; display: flex; align-items: center; gap: 0.35rem;">
                        <i class="fa-solid fa-crown"></i> Member 1 (Group Leader) <span>*</span>
                    </label>
                    <select name="student_1" id="grp_modal_student_1" required class="form-control" style="width: 100%; padding: 0.55rem 0.75rem; border: 1px solid #FCD34D; border-radius: 6px; background: white;">
                        <option value="">-- Select Leader --</option>
                        <?php foreach($student_list as $s): ?>
                            <option value="<?= $s['id'] ?>">
                                <?= htmlspecialchars($s['name']) ?> (<?= htmlspecialchars($s['department']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Member 2 -->
                <div class="form-group" style="margin-bottom: 0.75rem;">
                    <label style="font-size: 0.8rem; font-weight: 600; color: #047857; display: flex; align-items: center; gap: 0.35rem;">
                        <i class="fa-solid fa-user"></i> Member 2 (Second Student)
                    </label>
                    <select name="student_2" id="grp_modal_student_2" class="form-control" style="width: 100%; padding: 0.55rem 0.75rem; border: 1px solid #CBD5E1; border-radius: 6px; background: white;">
                        <option value="0">-- Select Registered Account (Optional) --</option>
                        <?php foreach($student_list as $s): ?>
                            <option value="<?= $s['id'] ?>">
                                <?= htmlspecialchars($s['name']) ?> (<?= htmlspecialchars($s['department']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="member_2_name" id="grp_modal_m2_name" placeholder="Or type Full Name directly (e.g. Juan Dela Cruz)..." class="form-control" style="width: 100%; margin-top: 0.35rem; padding: 0.45rem 0.65rem; font-size: 0.82rem; border: 1px solid #CBD5E1; border-radius: 6px;">
                </div>

                <!-- Member 3 -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.8rem; font-weight: 600; color: #047857; display: flex; align-items: center; gap: 0.35rem;">
                        <i class="fa-solid fa-user"></i> Member 3 (Third Student)
                    </label>
                    <select name="student_3" id="grp_modal_student_3" class="form-control" style="width: 100%; padding: 0.55rem 0.75rem; border: 1px solid #CBD5E1; border-radius: 6px; background: white;">
                        <option value="0">-- Select Registered Account (Optional) --</option>
                        <?php foreach($student_list as $s): ?>
                            <option value="<?= $s['id'] ?>">
                                <?= htmlspecialchars($s['name']) ?> (<?= htmlspecialchars($s['department']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="member_3_name" id="grp_modal_m3_name" placeholder="Or type Full Name directly (e.g. Maria Santos)..." class="form-control" style="width: 100%; margin-top: 0.35rem; padding: 0.45rem 0.65rem; font-size: 0.82rem; border: 1px solid #CBD5E1; border-radius: 6px;">
                </div>
            </div>

            <button type="submit" class="btn-primary" style="width: 100%; padding: 0.65rem; font-size: 0.88rem; background: var(--csu-green);">
                <i class="fa-solid fa-floppy-disk"></i> Save Group Roster
            </button>
        </form>
    </div>
</div>
