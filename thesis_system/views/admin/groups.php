<?php
// Admin View: Capstone Groups Management (with Pagination, Search & Roster Status Filter)
$search_group  = isset($_GET['search_group']) ? trim($_GET['search_group']) : '';
$dept_filter   = isset($_GET['dept']) ? trim($_GET['dept']) : 'all';
$roster_filter = isset($_GET['roster']) ? trim($_GET['roster']) : 'all';
$per_page      = isset($_GET['per_page']) && in_array((int)$_GET['per_page'], [3, 6, 9, 12]) ? (int)$_GET['per_page'] : 6;
$page          = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

if (!function_exists('isDeptActive')) {
    function isDeptActive($current, $target) {
        if ($target === 'all') {
            return ($current === 'all' || empty($current)) ? 'active' : '';
        }
        return $current === $target ? 'active' : '';
    }
}

$where = ["1=1"];
if ($search_group !== '') {
    $sg_esc = $conn->real_escape_string($search_group);
    $where[] = "(cg.group_name LIKE '%$sg_esc%' OR cg.thesis_topic LIKE '%$sg_esc%' OR cg.member2_name LIKE '%$sg_esc%' OR cg.member3_name LIKE '%$sg_esc%' OR u.name LIKE '%$sg_esc%')";
}
if ($dept_filter !== '' && $dept_filter !== 'all') {
    $dept_clean = $conn->real_escape_string(preg_replace('/\s*\(.*?\)/', '', $dept_filter));
    $df_esc = $conn->real_escape_string($dept_filter);
    $where[] = "(cg.department = '$df_esc' OR cg.department = '$dept_clean' OR cg.department LIKE '%$dept_clean%')";
}
$where_clause = implode(' AND ', $where);

// Fetch all matching groups first so we can compute exact member count, adviser status, and paginate accurately
$all_groups_res = $conn->query("
    SELECT cg.*, u.name as leader_name, u.email as leader_email,
           (SELECT u_adv.name FROM match_request mr JOIN user u_adv ON mr.adviser_id = u_adv.id WHERE (mr.group_id = cg.id OR mr.student_id = cg.leader_id) AND mr.status = 'accepted' LIMIT 1) as assigned_adviser_name
    FROM capstone_group cg 
    LEFT JOIN user u ON cg.leader_id = u.id 
    WHERE $where_clause 
    ORDER BY cg.id DESC
");

$filtered_groups = [];
$stats_complete = 0;
$stats_incomplete = 0;
$stats_with_adviser = 0;

if ($all_groups_res) {
    while ($g_row = $all_groups_res->fetch_assoc()) {
        $gid = (int)$g_row['id'];
        $roster = get_group_members_roster($conn, $gid);
        $mc = count($roster);
        $g_row['_roster'] = $roster;
        $g_row['_member_count'] = $mc;

        if ($mc >= 3) {
            $stats_complete++;
        } else {
            $stats_incomplete++;
        }
        if (!empty($g_row['assigned_adviser_name'])) {
            $stats_with_adviser++;
        }

        if ($roster_filter === 'complete' && $mc < 3) {
            continue;
        }
        if ($roster_filter === 'incomplete' && $mc >= 3) {
            continue;
        }
        $filtered_groups[] = $g_row;
    }
}

$total_groups = count($filtered_groups);
$total_pages  = max(1, (int)ceil($total_groups / $per_page));
if ($page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $per_page;
$paged_groups = array_slice($filtered_groups, $offset, $per_page);

$showing_from = $total_groups > 0 ? ($offset + 1) : 0;
$showing_to   = min($offset + $per_page, $total_groups);

// Helper to build pagination URLs preserving active filters
if (!function_exists('buildGroupsPageUrl')) {
    function buildGroupsPageUrl($target_page, $dept_filter, $search_group, $roster_filter, $per_page) {
        $params = ['tab' => 'groups', 'page' => max(1, (int)$target_page), 'per_page' => (int)$per_page];
        if ($dept_filter !== '' && $dept_filter !== 'all') $params['dept'] = $dept_filter;
        if ($search_group !== '') $params['search_group'] = $search_group;
        if ($roster_filter !== '' && $roster_filter !== 'all') $params['roster'] = $roster_filter;
        return '?' . http_build_query($params);
    }
}
?>

<div class="banner" style="background: linear-gradient(135deg, #022C22 0%, #064E3B 55%, #047857 100%); border-radius: 14px; padding: 1.25rem 1.65rem; color: white; margin-bottom: 1.25rem; box-shadow: 0 8px 20px -5px rgba(4, 120, 87, 0.25);">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="margin: 0 0 0.25rem 0; font-size: 1.35rem; font-weight: 800; color: #FFFFFF;"><i class="fa-solid fa-users"></i> Groups Administration</h1>
            <p style="margin: 0; color: #D1FAE5; font-size: 0.84rem;">CEIT Capstone Policy: Review, manage, and configure the rosters for all research teams.</p>
        </div>
        <div style="display: flex; gap: 0.6rem; flex-wrap: wrap;">
            <span style="background: rgba(255,255,255,0.14); border: 1px solid rgba(255,255,255,0.25); padding: 0.35rem 0.75rem; border-radius: 8px; font-size: 0.76rem; font-weight: 700; color: #ECFDF5;">
                <i class="fa-solid fa-check-double" style="color: #34D399;"></i> <?= $stats_complete ?> Complete (3/3)
            </span>
            <span style="background: rgba(255,255,255,0.14); border: 1px solid rgba(255,255,255,0.25); padding: 0.35rem 0.75rem; border-radius: 8px; font-size: 0.76rem; font-weight: 700; color: #FEF3C7;">
                <i class="fa-solid fa-user-clock" style="color: #FBBF24;"></i> <?= $stats_incomplete ?> Incomplete (&lt;3)
            </span>
            <span style="background: rgba(255,255,255,0.14); border: 1px solid rgba(255,255,255,0.25); padding: 0.35rem 0.75rem; border-radius: 8px; font-size: 0.76rem; font-weight: 700; color: #DBEAFE;">
                <i class="fa-solid fa-chalkboard-user" style="color: #93C5FD;"></i> <?= $stats_with_adviser ?> With Adviser
            </span>
        </div>
    </div>
</div>

<!-- Course Filter Tabs -->
<div class="groups-header-nav" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.85rem;">
    <div class="dept-tabs" style="margin-bottom: 0;">
        <button class="dept-tab <?= isDeptActive($dept_filter, 'all') ?>" onclick="window.location.href='?tab=groups&dept=all&per_page=<?= $per_page ?>'">
            <i class="fa-solid fa-layer-group"></i> All Courses
        </button>
        <button class="dept-tab <?= isDeptActive($dept_filter, 'Computer Engineering') ?>" onclick="window.location.href='?tab=groups&dept=Computer Engineering&per_page=<?= $per_page ?>'">
            <i class="fa-solid fa-gear"></i> Computer Engineering (ComEng)
        </button>
        <button class="dept-tab <?= isDeptActive($dept_filter, 'Information Technology') ?>" onclick="window.location.href='?tab=groups&dept=Information Technology&per_page=<?= $per_page ?>'">
            <i class="fa-solid fa-globe"></i> Information Technology (IT)
        </button>
        <button class="dept-tab <?= isDeptActive($dept_filter, 'Electrical Engineering') ?>" onclick="window.location.href='?tab=groups&dept=Electrical Engineering&per_page=<?= $per_page ?>'">
            <i class="fa-solid fa-plug"></i> Electrical Engineering (EE)
        </button>
    </div>
</div>

<div class="card" style="margin-bottom: 1.5rem; border-radius: 12px; border: 1px solid #E2E8F0;">
    <div class="card-header" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; padding: 1rem 1.25rem; border-bottom: 1px solid #F1F5F9;">
        <div class="card-header-left" style="display: flex; align-items: center; gap: 0.65rem; flex-wrap: wrap;">
            <i class="fa-solid fa-users" style="color: #047857;"></i>
            <h2 style="margin: 0; font-size: 1.02rem; font-weight: 800; color: #0F172A;">Registered Capstone Teams</h2>
            <span class="badge badge-success" style="font-size: 0.74rem; padding: 0.25rem 0.65rem;">
                <?= $total_groups ?> Active Teams<?= ($dept_filter !== '' && $dept_filter !== 'all') ? ' (' . htmlspecialchars($dept_filter) . ')' : '' ?>
            </span>
        </div>

        <form method="GET" action="" style="display: flex; gap: 0.5rem; align-items: center; margin: 0; flex-wrap: wrap;">
            <input type="hidden" name="tab" value="groups">
            <input type="hidden" name="page" value="1">
            <?php if ($dept_filter !== '' && $dept_filter !== 'all'): ?>
                <input type="hidden" name="dept" value="<?= htmlspecialchars($dept_filter) ?>">
            <?php endif; ?>

            <!-- Roster Status Filter -->
            <select name="roster" onchange="this.form.submit()" style="padding: 0.45rem 0.65rem; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 0.8rem; font-weight: 600; color: #334155; background: #F8FAFC; cursor: pointer;">
                <option value="all" <?= $roster_filter === 'all' ? 'selected' : '' ?>>All Rosters</option>
                <option value="complete" <?= $roster_filter === 'complete' ? 'selected' : '' ?>>Complete (3/3)</option>
                <option value="incomplete" <?= $roster_filter === 'incomplete' ? 'selected' : '' ?>>Incomplete (&lt;3)</option>
            </select>

            <!-- Per Page Selector -->
            <select name="per_page" onchange="this.form.submit()" style="padding: 0.45rem 0.65rem; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 0.8rem; font-weight: 600; color: #334155; background: #F8FAFC; cursor: pointer;">
                <option value="3" <?= $per_page === 3 ? 'selected' : '' ?>>3 / page</option>
                <option value="6" <?= $per_page === 6 ? 'selected' : '' ?>>6 / page</option>
                <option value="9" <?= $per_page === 9 ? 'selected' : '' ?>>9 / page</option>
                <option value="12" <?= $per_page === 12 ? 'selected' : '' ?>>12 / page</option>
            </select>

            <!-- Non-overlapping Search Box -->
            <div style="display: inline-flex; align-items: center; gap: 0.45rem; background: #F8FAFC; border: 1px solid #CBD5E1; border-radius: 8px; padding: 0.38rem 0.75rem; width: 235px;">
                <i class="fa-solid fa-magnifying-glass" style="color: #94A3B8; font-size: 0.8rem; flex-shrink: 0;"></i>
                <input type="text" name="search_group" value="<?= htmlspecialchars($search_group) ?>" placeholder="Search team or student..." style="border: none; outline: none; background: transparent; font-size: 0.83rem; color: #0F172A; width: 100%; padding: 0; margin: 0;">
            </div>
            
            <button type="submit" class="btn-primary" style="padding: 0.45rem 0.9rem; font-size: 0.82rem; border-radius: 8px;">Filter</button>
            <?php if ($search_group !== '' || $roster_filter !== 'all' || ($dept_filter !== '' && $dept_filter !== 'all')): ?>
                <a href="?tab=groups<?= ($dept_filter !== '' && $dept_filter !== 'all') ? '&dept=' . urlencode($dept_filter) : '' ?>&per_page=<?= $per_page ?>" class="btn" style="padding: 0.45rem 0.75rem; background: #E2E8F0; color: #475569; border-radius: 8px; font-size: 0.82rem; text-decoration: none;" title="Reset filter">
                    <i class="fa-solid fa-rotate-left"></i>
                </a>
            <?php endif; ?>
        </form>
    </div>

    <div class="card-body" style="padding: 1.25rem;">
        <?php if(empty($paged_groups)): ?>
            <div style="text-align: center; color: var(--text-muted); padding: 3rem 1rem;">
                <i class="fa-solid fa-users-slash fa-3x" style="color: #CBD5E1; margin-bottom: 1rem; display: block;"></i>
                <h3 style="color: #0F172A; font-size: 1.1rem; margin-bottom: 0.35rem;">
                    <?= ($dept_filter !== '' && $dept_filter !== 'all') ? "No " . htmlspecialchars($dept_filter) . " Capstone Groups Found" : "No Capstone Groups Found" ?>
                </h3>
                <p style="color: #64748B; font-size: 0.88rem; margin: 0;">Once <?= ($dept_filter !== '' && $dept_filter !== 'all') ? htmlspecialchars($dept_filter) . ' ' : '' ?>students form their 3-member teams, they will appear here with full visibility to admins and advisers.</p>
            </div>
        <?php else: ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 1.25rem;">
                <?php foreach($paged_groups as $grp): 
                    $gid = (int)$grp['id'];
                    $members = $grp['_roster'];
                    $mcount = $grp['_member_count'];
                    $adv_name = $grp['assigned_adviser_name'] ?? '';
                ?>
                    <div style="background: white; border: 1px solid #E2E8F0; border-top: 3px solid <?= $mcount >= 3 ? '#059669' : '#F59E0B' ?>; border-radius: 12px; padding: 1.25rem; box-shadow: 0 2px 6px rgba(15,23,42,0.03); display: flex; flex-direction: column; justify-content: space-between; gap: 0.85rem; transition: transform 0.18s ease, box-shadow 0.18s ease;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 18px rgba(15,23,42,0.06)'" onmouseout="this.style.transform='none'; this.style.boxShadow='0 2px 6px rgba(15,23,42,0.03)'">
                        <div>
                            <!-- Card Header -->
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 0.5rem; margin-bottom: 0.65rem;">
                                <div>
                                    <h3 style="margin: 0; font-size: 1.02rem; font-weight: 800; color: #0F172A; line-height: 1.3;">
                                        <?= htmlspecialchars($grp['group_name']) ?>
                                    </h3>
                                    <span style="font-size: 0.76rem; color: #64748B; display: inline-block; margin-top: 2px;">
                                        <i class="fa-solid fa-building-columns" style="font-size: 0.7rem;"></i> <?= htmlspecialchars($grp['department']) ?> &bull; Formed <?= date('M d, Y', strtotime($grp['created_at'])) ?>
                                    </span>
                                </div>
                                <span class="badge <?= $mcount >= 3 ? 'badge-success' : 'badge-warning' ?>" style="font-size: 0.72rem; white-space: nowrap; flex-shrink: 0;">
                                    <?= $mcount ?> / 3 Members
                                </span>
                            </div>

                            <!-- Assigned Adviser Pill -->
                            <div style="margin-bottom: 0.65rem;">
                                <?php if(!empty($adv_name)): ?>
                                    <span style="display: inline-flex; align-items: center; gap: 0.35rem; background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0; padding: 3px 8px; border-radius: 6px; font-size: 0.73rem; font-weight: 700;">
                                        <i class="fa-solid fa-user-tie" style="color: #059669;"></i> Adviser: <?= htmlspecialchars($adv_name) ?>
                                    </span>
                                <?php else: ?>
                                    <span style="display: inline-flex; align-items: center; gap: 0.35rem; background: #F8FAFC; color: #64748B; border: 1px solid #E2E8F0; padding: 3px 8px; border-radius: 6px; font-size: 0.73rem; font-weight: 600;">
                                        <i class="fa-regular fa-clock"></i> No Confirmed Adviser Yet
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- Topic Box -->
                            <?php if(!empty($grp['thesis_topic'])): ?>
                                <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 0.6rem 0.85rem; margin-bottom: 0.75rem;">
                                    <span style="font-size: 0.68rem; font-weight: 700; color: #64748B; text-transform: uppercase; letter-spacing: 0.4px;">Topic:</span>
                                    <div style="font-size: 0.82rem; color: #1E293B; font-weight: 600; line-height: 1.35; margin-top: 2px;"><?= htmlspecialchars($grp['thesis_topic']) ?></div>
                                </div>
                            <?php endif; ?>

                            <!-- 3 Members Roster -->
                            <div style="border: 1px solid #E2E8F0; border-radius: 8px; padding: 0.65rem; background: #FFFFFF;">
                                <div style="font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; margin-bottom: 0.4rem; display: flex; justify-content: space-between;">
                                    <span><i class="fa-solid fa-users" style="color: var(--csu-green);"></i> 3 Group Members:</span>
                                </div>

                                <div style="display: flex; flex-direction: column; gap: 0.3rem;">
                                    <?php 
                                    $admin_slots = max(3, count($members));
                                    for($i = 0; $i < $admin_slots; $i++): $mem = $members[$i] ?? null; ?>
                                        <?php if($mem): ?>
                                            <div style="font-size: 0.8rem; background: #F8FAFC; padding: 0.35rem 0.6rem; border-radius: 6px; border: 1px solid #E2E8F0; display: flex; justify-content: space-between; align-items: center;">
                                                <div style="overflow: hidden; max-width: 220px;">
                                                    <span style="font-weight: 600; color: #0F172A;">
                                                        <i class="fa-solid <?= $mem['is_leader'] ? 'fa-crown' : 'fa-user' ?>" style="color: <?= $mem['is_leader'] ? '#D97706' : '#047857' ?>; font-size: 0.7rem; margin-right: 0.3rem;"></i>
                                                        <?= htmlspecialchars($mem['name']) ?>
                                                    </span>
                                                    <?php if(!empty($mem['student_no'])): ?>
                                                        <span style="font-size: 0.7rem; color: #64748B;">(<?= htmlspecialchars($mem['student_no']) ?>)</span>
                                                    <?php endif; ?>
                                                </div>
                                                <span style="font-size: 0.68rem; color: <?= $mem['is_leader'] ? '#B45309' : '#64748B' ?>; font-weight: 700;">
                                                    <?= $mem['is_leader'] ? 'Leader' : 'Member '.($i+1) ?>
                                                </span>
                                            </div>
                                        <?php else: ?>
                                            <div style="font-size: 0.75rem; color: #94A3B8; font-style: italic; background: #FAFAFA; padding: 0.3rem 0.6rem; border-radius: 6px; border: 1px dashed #CBD5E1;">
                                                <i class="fa-regular fa-circle" style="font-size: 0.6rem; margin-right: 0.25rem;"></i> Vacant Member Slot <?= $i + 1 ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Card Action Buttons -->
                        <div style="display: flex; gap: 0.5rem; border-top: 1px solid #F1F5F9; padding-top: 0.75rem; justify-content: flex-end;">
                            <button class="btn" style="padding: 0.4rem 0.85rem; font-size: 0.8rem; background: #ECFDF5; color: #047857; border: 1px solid #A7F3D0; border-radius: 6px; font-weight: 600; cursor: pointer;" onclick="openAdminEditGroupModal(<?= $gid ?>, '<?= addslashes($grp['group_name']) ?>', '<?= addslashes($grp['leader_name'] ?? 'Leader') ?>', '<?= addslashes($grp['member2_name'] ?? '') ?>', '<?= addslashes($grp['member2_student_id'] ?? '') ?>', '<?= addslashes($grp['member2_email'] ?? '') ?>', '<?= addslashes($grp['member3_name'] ?? '') ?>', '<?= addslashes($grp['member3_student_id'] ?? '') ?>', '<?= addslashes($grp['member3_email'] ?? '') ?>', '<?= addslashes($grp['member4_name'] ?? '') ?>', '<?= addslashes($grp['member4_student_id'] ?? '') ?>', '<?= addslashes($grp['member4_email'] ?? '') ?>')">
                                <i class="fa-solid fa-pen-to-square"></i> Edit Members
                            </button>
                            <form method="POST" action="action.php" onsubmit="return confirm('WARNING: Are you sure you want to disband <?= addslashes($grp['group_name']) ?>?');" style="margin: 0;">
                                <input type="hidden" name="disband_group" value="1">
                                <input type="hidden" name="group_id" value="<?= $gid ?>">
                                <button type="submit" class="btn" style="padding: 0.4rem 0.75rem; font-size: 0.8rem; background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; border-radius: 6px; cursor: pointer;">
                                    <i class="fa-solid fa-trash-can"></i> Disband
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- PAGINATION FOOTER BAR -->
            <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #E2E8F0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.85rem;">
                <div style="font-size: 0.8rem; color: #64748B; font-weight: 600;">
                    Showing <strong style="color: #0F172A;"><?= $showing_from ?></strong> to <strong style="color: #0F172A;"><?= $showing_to ?></strong> of <strong style="color: #047857;"><?= $total_groups ?></strong> Capstone Teams
                    <span style="color: #94A3B8; margin-left: 0.35rem;">(Page <?= $page ?> of <?= $total_pages ?>)</span>
                </div>

                <div style="display: flex; align-items: center; gap: 0.35rem; flex-wrap: wrap;">
                    <!-- First / Prev -->
                    <?php if ($page > 1): ?>
                        <a href="<?= htmlspecialchars(buildGroupsPageUrl(1, $dept_filter, $search_group, $roster_filter, $per_page)) ?>" style="padding: 0.4rem 0.65rem; border: 1px solid #CBD5E1; background: white; color: #334155; border-radius: 6px; font-size: 0.78rem; font-weight: 700; text-decoration: none;" title="First Page">
                            <i class="fa-solid fa-angles-left"></i>
                        </a>
                        <a href="<?= htmlspecialchars(buildGroupsPageUrl($page - 1, $dept_filter, $search_group, $roster_filter, $per_page)) ?>" style="padding: 0.4rem 0.75rem; border: 1px solid #CBD5E1; background: white; color: #334155; border-radius: 6px; font-size: 0.78rem; font-weight: 700; text-decoration: none;">
                            <i class="fa-solid fa-chevron-left"></i> Prev
                        </a>
                    <?php else: ?>
                        <span style="padding: 0.4rem 0.75rem; border: 1px solid #E2E8F0; background: #F8FAFC; color: #94A3B8; border-radius: 6px; font-size: 0.78rem; font-weight: 600; cursor: not-allowed;">
                            <i class="fa-solid fa-chevron-left"></i> Prev
                        </span>
                    <?php endif; ?>

                    <!-- Page Numbers -->
                    <?php
                    $start_p = max(1, $page - 2);
                    $end_p   = min($total_pages, $page + 2);
                    for ($p = $start_p; $p <= $end_p; $p++):
                        $is_curr = ($p === $page);
                    ?>
                        <a href="<?= htmlspecialchars(buildGroupsPageUrl($p, $dept_filter, $search_group, $roster_filter, $per_page)) ?>"
                           style="padding: 0.4rem 0.75rem; border-radius: 6px; font-size: 0.8rem; font-weight: 800; text-decoration: none; border: 1px solid <?= $is_curr ? '#047857' : '#CBD5E1' ?>; background: <?= $is_curr ? '#047857' : 'white' ?>; color: <?= $is_curr ? 'white' : '#334155' ?>;">
                            <?= $p ?>
                        </a>
                    <?php endfor; ?>

                    <!-- Next / Last -->
                    <?php if ($page < $total_pages): ?>
                        <a href="<?= htmlspecialchars(buildGroupsPageUrl($page + 1, $dept_filter, $search_group, $roster_filter, $per_page)) ?>" style="padding: 0.4rem 0.75rem; border: 1px solid #CBD5E1; background: white; color: #334155; border-radius: 6px; font-size: 0.78rem; font-weight: 700; text-decoration: none;">
                            Next <i class="fa-solid fa-chevron-right"></i>
                        </a>
                        <a href="<?= htmlspecialchars(buildGroupsPageUrl($total_pages, $dept_filter, $search_group, $roster_filter, $per_page)) ?>" style="padding: 0.4rem 0.65rem; border: 1px solid #CBD5E1; background: white; color: #334155; border-radius: 6px; font-size: 0.78rem; font-weight: 700; text-decoration: none;" title="Last Page">
                            <i class="fa-solid fa-angles-right"></i>
                        </a>
                    <?php else: ?>
                        <span style="padding: 0.4rem 0.75rem; border: 1px solid #E2E8F0; background: #F8FAFC; color: #94A3B8; border-radius: 6px; font-size: 0.78rem; font-weight: 600; cursor: not-allowed;">
                            Next <i class="fa-solid fa-chevron-right"></i>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ADMIN EDIT GROUP MODAL -->
<div id="adminEditGroupModal" class="modal" style="display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(2px);">
    <div style="background: white; border-radius: 12px; max-width: 540px; margin: 4% auto; padding: 1.5rem; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); position: relative;">
        <span onclick="document.getElementById('adminEditGroupModal').style.display='none'" style="position: absolute; right: 1.25rem; top: 1rem; font-size: 1.5rem; cursor: pointer; color: #64748B;">&times;</span>
        <h3 style="margin: 0 0 0.4rem 0; font-size: 1.15rem; color: #0F172A; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fa-solid fa-users-gear" style="color: var(--csu-green);"></i> Edit Capstone Group Roster
        </h3>
        <p style="font-size: 0.82rem; color: #64748B; margin-bottom: 1.25rem;">
            Admin can directly update team name and the 2 or 3 group member slots.
        </p>

        <form method="POST" action="action.php">
            <input type="hidden" name="update_group_members" value="1">
            <input type="hidden" name="group_id" id="adm_grp_id" value="0">

            <div class="form-group" style="margin-bottom: 1rem;">
                <label style="font-size: 0.82rem; font-weight: 600; color: #334155; display: block; margin-bottom: 0.35rem;">Capstone Title <span style="color:#DC2626;">*</span></label>
                <input type="text" name="group_name" id="adm_grp_name" required class="form-control" style="width: 100%; padding: 0.6rem 0.85rem; border: 1px solid #CBD5E1; border-radius: 8px;">
            </div>

            <!-- Member 1: Leader (Fixed) -->
            <div style="background: #F8FAFC; border: 1px solid #E2E8F0; padding: 0.65rem 0.85rem; border-radius: 8px; margin-bottom: 0.85rem; display: flex; align-items: center; justify-content: space-between;">
                <div style="font-size: 0.85rem; color: #0F172A;">
                    <i class="fa-solid fa-crown" style="color: #D97706; margin-right: 0.35rem;"></i>
                    <strong id="adm_grp_leader">Leader</strong> <span style="color: #64748B; font-size: 0.78rem;">(Group Leader)</span>
                </div>
                <span class="badge badge-success" style="font-size: 0.72rem;">Fixed Leader</span>
            </div>

            <!-- Member 2 Direct Inputs -->
            <div style="background: #F8FAFC; border: 1px solid #E2E8F0; padding: 0.85rem; border-radius: 8px; margin-bottom: 0.85rem;">
                <label style="font-size: 0.82rem; font-weight: 700; color: #047857; display: block; margin-bottom: 0.4rem;">
                    <i class="fa-solid fa-user"></i> Member 2 (Second Student)
                </label>
                <input type="text" name="member_2_name" id="adm_grp_m2_name" placeholder="Full Name (Leave blank if vacant)" class="form-control" style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #CBD5E1; border-radius: 6px; margin-bottom: 0.4rem; font-size: 0.85rem;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                    <input type="text" name="member_2_id" id="adm_grp_m2_id" placeholder="Student ID *" class="form-control" style="width: 100%; padding: 0.45rem 0.65rem; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 0.8rem;">
                    <input type="email" name="member_2_email" id="adm_grp_m2_email" placeholder="Email Address *" class="form-control" style="width: 100%; padding: 0.45rem 0.65rem; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 0.8rem;">
                </div>
            </div>

            <!-- Member 3 Direct Inputs -->
            <div style="background: #F8FAFC; border: 1px solid #E2E8F0; padding: 0.85rem; border-radius: 8px; margin-bottom: 0.85rem;">
                <label style="font-size: 0.82rem; font-weight: 700; color: #047857; display: block; margin-bottom: 0.4rem;">
                    <i class="fa-solid fa-user"></i> Member 3 (Third Student)
                </label>
                <input type="text" name="member_3_name" id="adm_grp_m3_name" placeholder="Full Name (Leave blank if vacant)" class="form-control" style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #CBD5E1; border-radius: 6px; margin-bottom: 0.4rem; font-size: 0.85rem;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                    <input type="text" name="member_3_id" id="adm_grp_m3_id" placeholder="Student ID *" class="form-control" style="width: 100%; padding: 0.45rem 0.65rem; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 0.8rem;">
                    <input type="email" name="member_3_email" id="adm_grp_m3_email" placeholder="Email Address *" class="form-control" style="width: 100%; padding: 0.45rem 0.65rem; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 0.8rem;">
                </div>
            </div>

            <!-- Member 4 Direct Inputs (Optional) -->
            <div style="background: #F0FDF4; border: 1px solid #BBF7D0; padding: 0.85rem; border-radius: 8px; margin-bottom: 1.25rem;">
                <label style="font-size: 0.82rem; font-weight: 700; color: #047857; display: block; margin-bottom: 0.4rem;">
                    <i class="fa-solid fa-user"></i> Member 4 (Fourth Student · Optional)
                </label>
                <input type="text" name="member_4_name" id="adm_grp_m4_name" placeholder="Full Name (Leave blank if not needed)" class="form-control" style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #CBD5E1; border-radius: 6px; margin-bottom: 0.4rem; font-size: 0.85rem; background: white;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                    <input type="text" name="member_4_id" id="adm_grp_m4_id" placeholder="Student ID *" class="form-control" style="width: 100%; padding: 0.45rem 0.65rem; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 0.8rem; background: white;">
                    <input type="email" name="member_4_email" id="adm_grp_m4_email" placeholder="Email Address *" class="form-control" style="width: 100%; padding: 0.45rem 0.65rem; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 0.8rem; background: white;">
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                <button type="button" onclick="document.getElementById('adminEditGroupModal').style.display='none'" class="btn" style="padding: 0.6rem 1rem; border: 1px solid #CBD5E1; background: white; border-radius: 6px; font-size: 0.85rem; cursor: pointer;">Cancel</button>
                <button type="submit" class="btn-primary" style="padding: 0.6rem 1.25rem; font-size: 0.85rem; background: var(--csu-green);">
                    <i class="fa-solid fa-check"></i> Save Members Directly
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openAdminEditGroupModal(gid, name, leader, m2Name, m2Id, m2Email, m3Name, m3Id, m3Email, m4Name, m4Id, m4Email) {
    document.getElementById('adm_grp_id').value = gid;
    document.getElementById('adm_grp_name').value = name;
    document.getElementById('adm_grp_leader').textContent = leader;
    document.getElementById('adm_grp_m2_name').value = m2Name || '';
    document.getElementById('adm_grp_m2_id').value = m2Id || '';
    document.getElementById('adm_grp_m2_email').value = m2Email || '';
    document.getElementById('adm_grp_m3_name').value = m3Name || '';
    document.getElementById('adm_grp_m3_id').value = m3Id || '';
    document.getElementById('adm_grp_m3_email').value = m3Email || '';
    document.getElementById('adm_grp_m4_name').value = m4Name || '';
    document.getElementById('adm_grp_m4_id').value = m4Id || '';
    document.getElementById('adm_grp_m4_email').value = m4Email || '';
    document.getElementById('adminEditGroupModal').style.display = 'block';
}
</script>
