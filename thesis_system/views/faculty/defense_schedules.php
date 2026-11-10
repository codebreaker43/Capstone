<?php
// FACULTY / ADVISER OFFICIAL DEFENSE SCHEDULES VIEW
// Dedicated exclusively to Official Oral & Final Defense Schedules (IT 198 & IT 199) assigned by CEIT Administration

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'adviser') {
    exit('Access Denied');
}

$uid = (int)$_SESSION['user_id'];

// 1. Fetch all accepted advisees for this faculty member
$advisees_q = $conn->query("
    SELECT u.id as student_id, u.name as student_name, u.email,
           sp.thesis_topic, sp.department, sp.student_id as student_no, sp.group_id,
           cg.group_name
    FROM match_request mr
    JOIN user u ON mr.student_id = u.id
    LEFT JOIN student_profile sp ON u.id = sp.user_id
    LEFT JOIN capstone_group cg ON sp.group_id = cg.id
    WHERE mr.adviser_id = $uid AND mr.status = 'accepted'
    ORDER BY u.name ASC
");

$advisee_student_ids = [];
if ($advisees_q) {
    while ($adv = $advisees_q->fetch_assoc()) {
        $advisee_student_ids[] = (int)$adv['student_id'];
    }
}
$advisee_ids_str = !empty($advisee_student_ids) ? implode(',', $advisee_student_ids) : '0';

// 2. Fetch Official Defense Schedules assigned by Admin
$defense_schedules_q = $conn->query("
    SELECT dm.*, 
           u.name as student_name, u.email as student_email,
           sp.department as student_dept, sp.thesis_topic as student_topic,
           cg.group_name,
           chair.name as panel_chair_name,
           (SELECT dmm.decision_verdict FROM defense_meeting_minutes dmm WHERE dmm.meeting_id = dm.id LIMIT 1) as mom_verdict
    FROM defense_meetings dm
    JOIN user u ON dm.student_id = u.id
    LEFT JOIN student_profile sp ON u.id = sp.user_id
    LEFT JOIN capstone_group cg ON dm.group_id = cg.id
    LEFT JOIN user chair ON dm.panel_chair_id = chair.id
    WHERE dm.adviser_id = $uid OR dm.student_id IN ($advisee_ids_str)
    ORDER BY dm.meeting_date DESC, dm.start_time DESC
");

$defense_schedules = [];
$fac_it198 = [];
$fac_it199 = [];

if ($defense_schedules_q) {
    while ($ds = $defense_schedules_q->fetch_assoc()) {
        $ds_ay = !empty($ds['academic_year']) ? $ds['academic_year'] : calculate_academic_year($ds['meeting_date']);
        $ds_cat = !empty($ds['defense_category']) ? $ds['defense_category'] : resolve_defense_category($ds['defense_stage'], '');
        $ds_cc = !empty($ds['course_code']) ? $ds['course_code'] : resolve_course_code($ds['defense_stage'], ($ds_cat === 'Proposal Defense' ? 'IT 198' : 'IT 199'));
        
        $ds['computed_ay'] = $ds_ay;
        $ds['computed_cat'] = $ds_cat;
        $ds['computed_cc'] = $ds_cc;

        $defense_schedules[] = $ds;

        if ($ds_cc === 'IT 198' || $ds_cat === 'Proposal Defense') {
            $fac_it198[] = $ds;
        } else {
            $fac_it199[] = $ds;
        }
    }
}

// Available Academic Years for Adviser
$fac_available_ays = get_available_academic_years($conn);
$curr_fac_ay = calculate_academic_year(date('Y-m-d'));
if (!in_array($curr_fac_ay, $fac_available_ays)) {
    array_unshift($fac_available_ays, $curr_fac_ay);
}

if (!function_exists('renderFacDefenseCard')) {
    function renderFacDefenseCard($ds) {
        $d_time = !empty($ds['start_time']) ? date('g:i A', strtotime($ds['start_time'])) . (!empty($ds['end_time']) ? ' - ' . date('g:i A', strtotime($ds['end_time'])) : '') : 'Time: TBA';
        $date_obj = strtotime($ds['meeting_date']);
        $is_today = ($ds['meeting_date'] === date('Y-m-d'));

        // Panel names
        $p_list = [];
        if (!empty($ds['panel_members'])) {
            $dec = json_decode($ds['panel_members'], true);
            if (is_array($dec)) $p_list = $dec;
            else $p_list = explode(',', $ds['panel_members']);
        }

        $candidate_name = !empty($ds['group_name']) ? "Group {$ds['group_name']} ({$ds['student_name']})" : $ds['student_name'];
        $ds_ay = $ds['computed_ay'] ?? calculate_academic_year($ds['meeting_date']);
        $ds_cat = $ds['computed_cat'] ?? resolve_defense_category($ds['defense_stage']);
        $ds_cc = $ds['computed_cc'] ?? resolve_course_code($ds['defense_stage']);
        $is_prop_fac = ($ds_cc === 'IT 198' || $ds_cat === 'Proposal Defense');
        ?>
        <div class="fac-defense-card" 
             data-ay="<?php echo htmlspecialchars($ds_ay); ?>" 
             data-course="<?php echo htmlspecialchars($ds_cc); ?>" 
             data-category="<?php echo htmlspecialchars($ds_cat); ?>"
             data-search="<?php echo htmlspecialchars(strtolower($candidate_name . ' ' . $ds['title'] . ' ' . ($ds['venue'] ?? '') . ' ' . $ds_ay)); ?>"
             style="background: #FFFFFF; border: 1.5px solid <?php echo $is_prop_fac ? '#BAE6FD' : '#A7F3D0'; ?>; border-radius: 12px; padding: 1.25rem; display: flex; flex-direction: column; gap: 0.85rem; box-shadow: 0 2px 6px rgba(0,0,0,0.04); border-left: 6px solid <?php echo $is_prop_fac ? '#0284C7' : '#059669'; ?>;">
            
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 0.75rem;">
                <div style="flex: 1; min-width: 260px;">
                    <!-- Badge Row -->
                    <div style="display: flex; align-items: center; gap: 0.45rem; flex-wrap: wrap; margin-bottom: 0.45rem;">
                        <span style="background: <?php echo $is_prop_fac ? '#E0F2FE; color: #0369A1; border: 1px solid #BAE6FD;' : '#D1FAE5; color: #047857; border: 1px solid #A7F3D0;'; ?> font-size: 0.74rem; font-weight: 800; padding: 0.18rem 0.6rem; border-radius: 999px;">
                            <i class="fa-solid fa-graduation-cap"></i> <?php echo htmlspecialchars($ds_cc); ?>
                        </span>

                        <span style="background: <?php echo $is_prop_fac ? '#F0F9FF; color: #0284C7; border: 1px solid #BAE6FD;' : '#ECFDF5; color: #059669; border: 1px solid #A7F3D0;'; ?> font-size: 0.74rem; font-weight: 800; padding: 0.18rem 0.6rem; border-radius: 999px;">
                            <i class="fa-solid <?php echo $is_prop_fac ? 'fa-file-signature' : 'fa-award'; ?>"></i> <?php echo htmlspecialchars($ds_cat); ?>
                        </span>

                        <span style="background: #FFFBEB; color: #B45309; border: 1px solid #FDE68A; font-size: 0.74rem; font-weight: 800; padding: 0.18rem 0.6rem; border-radius: 999px;">
                            <i class="fa-solid fa-calendar-days"></i> A.Y. <?php echo htmlspecialchars($ds_ay); ?>
                        </span>

                        <span style="background: #EFF6FF; color: #1D4ED8; border: 1px solid #BFDBFE; font-size: 0.74rem; font-weight: 800; padding: 0.18rem 0.6rem; border-radius: 999px;">
                            <i class="fa-solid fa-bookmark"></i> <?php echo htmlspecialchars($ds['defense_stage']); ?>
                        </span>

                        <?php if($ds['status'] === 'in_progress'): ?>
                            <span style="background: #DC2626; color: white; font-size: 0.72rem; font-weight: 800; padding: 0.18rem 0.6rem; border-radius: 999px;">
                                <i class="fa-solid fa-circle-dot fa-fade"></i> In Progress
                            </span>
                        <?php elseif($ds['status'] === 'completed'): ?>
                            <span style="background: #ECFDF5; color: #047857; border: 1px solid #A7F3D0; font-size: 0.72rem; font-weight: 800; padding: 0.18rem 0.6rem; border-radius: 999px;">
                                <i class="fa-solid fa-check-double"></i> Completed
                            </span>
                        <?php elseif($is_today): ?>
                            <span style="background: #FEF3C7; color: #B45309; font-size: 0.72rem; font-weight: 800; padding: 0.18rem 0.6rem; border-radius: 999px;">
                                <i class="fa-solid fa-bolt"></i> Today
                            </span>
                        <?php endif; ?>

                        <?php if(!empty($ds['mom_verdict'])): ?>
                            <span style="background: #022C22; color: #34D399; font-size: 0.72rem; font-weight: 800; padding: 0.18rem 0.6rem; border-radius: 999px;">
                                <i class="fa-solid fa-gavel"></i> <?php echo htmlspecialchars($ds['mom_verdict']); ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div style="font-size: 0.74rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; color: <?php echo $is_prop_fac ? '#0284C7' : '#059669'; ?>; margin-bottom: 0.2rem;">
                        <i class="fa-solid <?php echo $is_prop_fac ? 'fa-lightbulb' : 'fa-award'; ?>"></i> <?php echo $is_prop_fac ? 'Proposal Defense Title' : 'Final Defense'; ?>
                    </div>

                    <h3 style="font-size: 1.05rem; font-weight: 800; color: #0F172A; margin: 0 0 0.35rem 0; line-height: 1.35;">
                        <?php echo htmlspecialchars($ds['title']); ?>
                    </h3>

                    <div style="font-size: 0.85rem; color: #475569; display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                        <span><i class="fa-solid fa-user-graduate" style="color: #047857;"></i> Candidate: <strong><?php echo htmlspecialchars($candidate_name); ?></strong></span>
                        <?php if(!empty($ds['student_dept'])): ?>
                            <span>• <?php echo htmlspecialchars($ds['student_dept']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Schedule Date & Venue Card -->
                <div style="text-align: right; font-size: 0.84rem; color: #475569; background: #F8FAFC; border: 1px solid #E2E8F0; padding: 0.75rem 1rem; border-radius: 8px; min-width: 220px;">
                    <div><i class="fa-regular fa-calendar" style="color: #047857;"></i> <strong><?php echo date('M d, Y (l)', $date_obj); ?></strong></div>
                    <div style="margin-top: 0.2rem;"><i class="fa-regular fa-clock" style="color: #047857;"></i> <strong><?php echo $d_time; ?></strong></div>
                    <div style="margin-top: 0.2rem;"><i class="fa-solid fa-location-dot" style="color: #DC2626;"></i> <?php echo htmlspecialchars($ds['venue'] ?? 'CEIT Defense Hall'); ?></div>
                    <?php if(!empty($ds['meeting_link'])): ?>
                        <a href="<?php echo htmlspecialchars($ds['meeting_link']); ?>" target="_blank" style="display: inline-block; margin-top: 0.4rem; font-size: 0.74rem; font-weight: 700; background: #0284C7; color: white; padding: 0.2rem 0.6rem; border-radius: 4px; text-decoration: none;">
                            <i class="fa-solid fa-video"></i> Virtual Link
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Panel Committee Display -->
            <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 0.65rem 0.85rem; font-size: 0.82rem; display: flex; flex-wrap: wrap; gap: 0.65rem; align-items: center;">
                <div>
                    <strong style="color: #64748B; text-transform: uppercase; font-size: 0.72rem;"><i class="fa-solid fa-users-viewfinder"></i> Committee:</strong>
                </div>
                <?php if(!empty($ds['panel_chair_name'])): ?>
                    <span style="background: #FEF3C7; color: #78350F; border: 1px solid #FCD34D; padding: 0.15rem 0.55rem; border-radius: 999px; font-weight: 700;">
                        <i class="fa-solid fa-crown" style="color: #D97706;"></i> Chair: <?php echo htmlspecialchars($ds['panel_chair_name']); ?>
                    </span>
                <?php endif; ?>
                <?php foreach($p_list as $pm): ?>
                    <span style="background: #FFFFFF; border: 1px solid #CBD5E1; color: #334155; padding: 0.15rem 0.5rem; border-radius: 999px; font-weight: 600;">
                        <?php echo htmlspecialchars($pm); ?>
                    </span>
                <?php endforeach; ?>
                <?php if(!empty($ds['mom_documenter_name'])): ?>
                    <span style="background: #ECFDF5; border: 1px solid #A7F3D0; color: #047857; padding: 0.15rem 0.55rem; border-radius: 999px; font-weight: 700;">
                        <i class="fa-solid fa-pen-nib"></i> MOM Documenter: <?php echo htmlspecialchars($ds['mom_documenter_name']); ?>
                    </span>
                <?php endif; ?>
            </div>

            <?php if(!empty($ds['notes'])): ?>
                <div style="font-size: 0.8rem; color: #065F46; background: #ECFDF5; padding: 0.45rem 0.75rem; border-radius: 6px; border: 1px solid #A7F3D0;">
                    <i class="fa-solid fa-info-circle"></i> <strong>Instructions / Guidelines:</strong> <?php echo htmlspecialchars($ds['notes']); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
?>

<div class="fac-sched-wrapper">
    <!-- Header Banner (Properly Closed!) -->
    <div class="banner" style="background: linear-gradient(135deg, var(--csu-green-darker) 0%, var(--csu-green-dark) 60%, var(--csu-green) 100%); border-radius: 12px; padding: 1.35rem 1.75rem; color: white; margin-bottom: 1.5rem; box-shadow: var(--shadow-sm); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="background: rgba(16, 185, 129, 0.25); color: #A7F3D0; border: 1px solid rgba(16, 185, 129, 0.45); padding: 0.2rem 0.65rem; border-radius: 999px; font-size: 0.72rem; font-weight: 800; text-transform: uppercase; display: inline-flex; align-items: center; gap: 0.35rem; margin-bottom: 0.3rem;">
                <i class="fa-solid fa-gavel"></i> Official Oral Defense Calendar
            </div>
            <h1 style="font-size: 1.45rem; font-weight: 800; margin: 0; color: #FFFFFF;">Advisee Defense Schedules</h1>
            <p style="margin: 0.25rem 0 0 0; color: #D1FAE5; font-size: 0.86rem;">Monitor administrator-scheduled oral defenses categorized by Proposal Defense Title and Final Defense across Academic Years.</p>
        </div>
        <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
            <div style="background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.25); border-radius: 10px; padding: 0.6rem 1.05rem; text-align: center;">
                <div style="font-size: 1.3rem; font-weight: 900; color: #BAE6FD; line-height: 1;"><?php echo count($fac_it198); ?></div>
                <small style="color: #E0F2FE; font-size: 0.72rem; font-weight: 700;">Proposal Defense Title</small>
            </div>
            <div style="background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.25); border-radius: 10px; padding: 0.6rem 1.05rem; text-align: center;">
                <div style="font-size: 1.3rem; font-weight: 900; color: #A7F3D0; line-height: 1;"><?php echo count($fac_it199); ?></div>
                <small style="color: #D1FAE5; font-size: 0.72rem; font-weight: 700;">Final Defense</small>
            </div>
            <a href="?tab=schedule" style="background: #F59E0B; color: #022C22; font-weight: 800; font-size: 0.8rem; padding: 0.6rem 1rem; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem;">
                <i class="fa-solid fa-handshake-angle"></i> Go to Schedule Meetings
            </a>
        </div>
    </div>

    <!-- OFFICIAL DEFENSE SCHEDULES FROM ADMINISTRATOR -->
    <div class="card" style="margin-bottom: 1.8rem;">
        <div class="card-header" style="background: #F8FAFC; border-bottom: 1px solid #E2E8F0; padding: 1.1rem 1.25rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
            <div style="display: flex; align-items: center; gap: 0.6rem;">
                <div style="width: 38px; height: 38px; border-radius: 8px; background: #ECFDF5; color: #047857; display: flex; align-items: center; justify-content: center; font-size: 1.15rem;">
                    <i class="fa-solid fa-calendar-days"></i>
                </div>
                <div>
                    <h2 style="font-size: 1.12rem; font-weight: 800; color: #064E3B; margin: 0;">Official Oral &amp; Final Defense Schedules</h2>
                    <span style="font-size: 0.78rem; color: #64748B;">Assigned by CEIT Administration for your research advisees</span>
                </div>
            </div>
            <span class="badge" style="background: #EFF6FF; color: #1D4ED8; border: 1px solid #BFDBFE; font-weight: 700; font-size: 0.78rem; padding: 0.3rem 0.75rem; border-radius: 999px;">
                <?php echo count($defense_schedules); ?> Total Session(s)
            </span>
        </div>

        <!-- Filter Toolbar for Adviser -->
        <div style="background: #FFFFFF; border-bottom: 1px solid #E2E8F0; padding: 0.85rem 1.25rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
            <div style="display: flex; gap: 0.4rem; align-items: center; flex-wrap: wrap;">
                <button type="button" class="fac-filter-btn active" id="btnFacAll" onclick="filterFacCategory('all')" style="padding: 0.45rem 0.85rem; border-radius: 8px; font-size: 0.82rem; font-weight: 700; border: 1.5px solid #064E3B; background: #064E3B; color: white; cursor: pointer; transition: all 0.2s;">
                    All Defenses (<span id="countFacTotal"><?php echo count($defense_schedules); ?></span>)
                </button>
                <button type="button" class="fac-filter-btn" id="btnFacIT198" onclick="filterFacCategory('IT 198')" style="padding: 0.45rem 0.85rem; border-radius: 8px; font-size: 0.82rem; font-weight: 700; border: 1.5px solid #BAE6FD; background: #F0F9FF; color: #0369A1; cursor: pointer; transition: all 0.2s;">
                    <i class="fa-solid fa-file-signature"></i> Proposal Defense Title (<span id="countFac198"><?php echo count($fac_it198); ?></span>)
                </button>
                <button type="button" class="fac-filter-btn" id="btnFacIT199" onclick="filterFacCategory('IT 199')" style="padding: 0.45rem 0.85rem; border-radius: 8px; font-size: 0.82rem; font-weight: 700; border: 1.5px solid #A7F3D0; background: #ECFDF5; color: #047857; cursor: pointer; transition: all 0.2s;">
                    <i class="fa-solid fa-graduation-cap"></i> Final Defense (<span id="countFac199"><?php echo count($fac_it199); ?></span>)
                </button>
            </div>

            <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                <div style="display: inline-flex; align-items: center; gap: 0.35rem; background: #FFFFFF; border: 1.5px solid #047857; border-radius: 8px; padding: 0.35rem 0.65rem;">
                    <i class="fa-solid fa-calendar-days" style="color: #047857; font-size: 0.85rem;"></i>
                    <input type="text" 
                           id="facAyFilter" 
                           list="facAyList" 
                           placeholder="Type A.Y. (e.g. 2026-2027)" 
                           oninput="applyFacFilters()" 
                           onchange="applyFacFilters()" 
                           style="border: none; background: transparent; font-size: 0.82rem; font-weight: 700; color: #0F172A; outline: none; width: 170px; padding: 0; margin: 0;" 
                           autocomplete="off">
                    <datalist id="facAyList">
                        <option value="">All Academic Years</option>
                        <?php foreach($fac_available_ays as $ay_opt): ?>
                            <option value="<?php echo htmlspecialchars($ay_opt); ?>">A.Y. <?php echo htmlspecialchars($ay_opt); ?></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <div style="display: inline-flex; align-items: center; gap: 0.45rem; background: #F8FAFC; border: 1.5px solid #CBD5E1; border-radius: 8px; padding: 0.38rem 0.75rem; min-width: 220px;">
                    <i class="fa-solid fa-magnifying-glass" style="color: #94A3B8; font-size: 0.82rem; flex-shrink: 0;"></i>
                    <input type="text" id="facSearchInput" placeholder="Search title, candidate, venue..." onkeyup="applyFacFilters()" style="border: none; outline: none; background: transparent; font-size: 0.82rem; color: #0F172A; width: 100%; padding: 0; margin: 0;">
                </div>
            </div>
        </div>

        <div class="card-body" style="padding: 1.25rem;">
            <?php if(empty($defense_schedules)): ?>
                <div style="text-align: center; padding: 2.5rem 1.5rem; background: #F8FAFC; border: 2px dashed #E2E8F0; border-radius: 10px;">
                    <i class="fa-regular fa-calendar-xmark" style="font-size: 2.75rem; color: #94A3B8; margin-bottom: 0.65rem;"></i>
                    <h3 style="font-size: 1.1rem; color: #1E293B; margin: 0 0 0.35rem 0;">No Defenses Scheduled Yet</h3>
                    <p style="font-size: 0.85rem; color: #64748B; margin: 0;">None of your advisees currently have an active defense schedule. Schedules assigned by the administrator will appear here automatically.</p>
                </div>
            <?php else: ?>
                <!-- SECTION 1: PROPOSAL DEFENSE TITLE -->
                <div id="facSectionIT198" style="margin-bottom: 2rem;">
                    <div style="background: linear-gradient(135deg, #0284C7 0%, #0369A1 100%); color: white; border-radius: 10px; padding: 0.75rem 1.15rem; display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; box-shadow: 0 2px 8px rgba(2, 132, 199, 0.25);">
                        <div style="display: flex; align-items: center; gap: 0.65rem;">
                            <span style="background: rgba(255,255,255,0.25); border-radius: 6px; padding: 0.2rem 0.55rem; font-weight: 800; font-size: 0.78rem;"><i class="fa-solid fa-file-signature"></i></span>
                            <div>
                                <h3 style="margin: 0; font-size: 1.05rem; font-weight: 800; color: #FFFFFF;">Proposal Defense Title</h3>
                                <small style="color: #E0F2FE; font-size: 0.75rem;">Title Proposal, Outline &amp; Pre-Oral Defense Deliberations</small>
                            </div>
                        </div>
                        <span style="background: #FFFFFF; color: #0369A1; font-weight: 800; font-size: 0.75rem; padding: 0.2rem 0.65rem; border-radius: 999px;">
                            <span id="facNumIT198"><?php echo count($fac_it198); ?></span> Session(s)
                        </span>
                    </div>

                    <div id="facCardsIT198" style="display: flex; flex-direction: column; gap: 1rem;">
                        <?php if(empty($fac_it198)): ?>
                            <div class="empty-fac-sub" style="text-align: center; padding: 1.5rem; background: #F8FAFC; border: 1px dashed #CBD5E1; border-radius: 8px; color: #64748B; font-size: 0.85rem;">
                                No Proposal Defense Title sessions scheduled for your advisees.
                            </div>
                        <?php else: ?>
                            <?php foreach($fac_it198 as $ds): renderFacDefenseCard($ds); endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- SECTION 2: FINAL DEFENSE -->
                <div id="facSectionIT199">
                    <div style="background: linear-gradient(135deg, #059669 0%, #047857 100%); color: white; border-radius: 10px; padding: 0.75rem 1.15rem; display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; box-shadow: 0 2px 8px rgba(5, 150, 105, 0.25);">
                        <div style="display: flex; align-items: center; gap: 0.65rem;">
                            <span style="background: rgba(255,255,255,0.25); border-radius: 6px; padding: 0.2rem 0.55rem; font-weight: 800; font-size: 0.78rem;"><i class="fa-solid fa-graduation-cap"></i></span>
                            <div>
                                <h3 style="margin: 0; font-size: 1.05rem; font-weight: 800; color: #FFFFFF;">Final Defense</h3>
                                <small style="color: #D1FAE5; font-size: 0.75rem;">Final Oral Capstone Defense Deliberations &amp; Minutes</small>
                            </div>
                        </div>
                        <span style="background: #FFFFFF; color: #047857; font-weight: 800; font-size: 0.75rem; padding: 0.2rem 0.65rem; border-radius: 999px;">
                            <span id="facNumIT199"><?php echo count($fac_it199); ?></span> Session(s)
                        </span>
                    </div>

                    <div id="facCardsIT199" style="display: flex; flex-direction: column; gap: 1rem;">
                        <?php if(empty($fac_it199)): ?>
                            <div class="empty-fac-sub" style="text-align: center; padding: 1.5rem; background: #F8FAFC; border: 1px dashed #CBD5E1; border-radius: 8px; color: #64748B; font-size: 0.85rem;">
                                No Final Defense sessions scheduled for your advisees.
                            </div>
                        <?php else: ?>
                            <?php foreach($fac_it199 as $ds): renderFacDefenseCard($ds); endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
let activeFacCategory = 'all';

function filterFacCategory(cat) {
    activeFacCategory = cat;
    
    document.querySelectorAll('.fac-filter-btn').forEach(btn => {
        btn.style.background = '#FFFFFF';
        btn.style.color = '#334155';
        btn.style.borderColor = '#CBD5E1';
    });

    if (cat === 'all') {
        const b = document.getElementById('btnFacAll');
        if (b) { b.style.background = '#064E3B'; b.style.color = '#FFFFFF'; b.style.borderColor = '#064E3B'; }
    } else if (cat === 'IT 198') {
        const b = document.getElementById('btnFacIT198');
        if (b) { b.style.background = '#0284C7'; b.style.color = '#FFFFFF'; b.style.borderColor = '#0284C7'; }
    } else if (cat === 'IT 199') {
        const b = document.getElementById('btnFacIT199');
        if (b) { b.style.background = '#059669'; b.style.color = '#FFFFFF'; b.style.borderColor = '#059669'; }
    }

    applyFacFilters();
}

function applyFacFilters() {
    const selectedAy = (document.getElementById('facAyFilter') ? document.getElementById('facAyFilter').value.trim() : '');
    const cleanSelectedAy = selectedAy.replace(/^A\.?Y\.?\s*/i, '').trim().toLowerCase();
    const searchTerm = (document.getElementById('facSearchInput') ? document.getElementById('facSearchInput').value.trim().toLowerCase() : '');

    const sec198 = document.getElementById('facSectionIT198');
    const sec199 = document.getElementById('facSectionIT199');

    let vis198 = 0;
    let vis199 = 0;

    document.querySelectorAll('.fac-defense-card').forEach(card => {
        const cardAy = (card.getAttribute('data-ay') || '').trim();
        const cardCourse = (card.getAttribute('data-course') || '').trim();
        const cardSearch = (card.getAttribute('data-search') || '').toLowerCase();

        const matchCat = (activeFacCategory === 'all' || cardCourse === activeFacCategory);
        let matchAy = true;
        if (cleanSelectedAy) {
            const cleanCardAy = cardAy.replace(/^A\.?Y\.?\s*/i, '').trim().toLowerCase();
            matchAy = (cleanCardAy.includes(cleanSelectedAy) || cleanSelectedAy.includes(cardAy.toLowerCase()));
        }
        const matchSearch = (searchTerm === '' || cardSearch.includes(searchTerm));

        const isVisible = (matchCat && matchAy && matchSearch);
        card.style.display = isVisible ? 'flex' : 'none';

        if (isVisible) {
            if (cardCourse === 'IT 198') vis198++;
            else vis199++;
        }
    });

    if (sec198) {
        sec198.style.display = (activeFacCategory === 'IT 199') ? 'none' : 'block';
    }
    if (sec199) {
        sec199.style.display = (activeFacCategory === 'IT 198') ? 'none' : 'block';
    }

    const count198El = document.getElementById('facNumIT198');
    if (count198El) count198El.textContent = vis198;
    const count199El = document.getElementById('facNumIT199');
    if (count199El) count199El.textContent = vis199;
}
</script>

