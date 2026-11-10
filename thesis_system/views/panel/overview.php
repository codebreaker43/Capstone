<div class="banner">
    <h1><i class="fa-solid fa-users"></i> Panel Member Dashboard</h1>
    <p>Welcome, <?php echo htmlspecialchars($user_data['firstname'] ?? $name); ?> • Capstone Oral Defense Review & Grading</p>
</div>

<?php
// Count how many have been graded
$graded_count = 0;
$assigned_res->data_seek(0);
while($row = $assigned_res->fetch_assoc()) {
    if($row['status'] == 'graded') {
        $graded_count++;
    }
}
$assigned_res->data_seek(0);
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon"><i class="fa-solid fa-file-signature"></i></div>
        <div class="stat-value"><?php echo $assigned_res->num_rows; ?></div>
        <div class="stat-label">Assigned Capstones</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
        <div class="stat-value"><?php echo $graded_count; ?></div>
        <div class="stat-label">Graded Capstones</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fa-solid fa-hourglass-half"></i></div>
        <div class="stat-value"><?php echo max(0, $assigned_res->num_rows - $graded_count); ?></div>
        <div class="stat-label">Pending Evaluations</div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-header-left">
            <i class="fa-solid fa-bolt"></i>
            <h2>Quick Actions</h2>
        </div>
    </div>
    <div class="card-body">
        <div class="quick-actions">
            <a href="?tab=assigned"><button class="btn-primary" style="width: 100%;"><i class="fa-solid fa-file-signature"></i> Assigned Capstones</button></a>
            <a href="?tab=manuscripts"><button class="btn btn-outline" style="width: 100%; border-radius: 8px; background: #FEF2F2; color: #DC2626; border-color: #FECACA; font-weight: 700;"><i class="fa-solid fa-book-open-reader"></i> Candidate Manuscripts</button></a>
            <a href="?tab=comments"><button class="btn btn-outline" style="width: 100%; border-radius: 8px;"><i class="fa-solid fa-comments"></i> Panel Comments</button></a>
            <a href="?tab=meeting_doc"><button class="btn btn-outline" style="width: 100%; border-radius: 8px;"><i class="fa-solid fa-file-signature"></i> Minutes of the Meeting (MOM)</button></a>
            <a href="?tab=settings"><button class="btn btn-outline" style="width: 100%; border-radius: 8px;"><i class="fa-solid fa-gear"></i> Panel Settings</button></a>
        </div>
    </div>
</div>

<?php
// Query defenses assigned to this panelist
$esc_pname = $conn->real_escape_string($name);
$panel_defenses_q = $conn->query("
    SELECT dm.*, 
           u.name as student_name,
           cg.group_name,
           chair.name as chair_name,
           (SELECT dmm.decision_verdict FROM defense_meeting_minutes dmm WHERE dmm.meeting_id = dm.id LIMIT 1) as mom_verdict
    FROM defense_meetings dm
    JOIN user u ON dm.student_id = u.id
    LEFT JOIN capstone_group cg ON dm.group_id = cg.id
    LEFT JOIN user chair ON dm.panel_chair_id = chair.id
    WHERE dm.panel_chair_id = $uid 
       OR dm.student_id IN (SELECT student_id FROM thesis_panel WHERE panel_id = $uid)
       OR dm.panel_members LIKE '%\"$esc_pname\"%'
    ORDER BY dm.meeting_date DESC, dm.start_time DESC
    LIMIT 5
");
?>

<div class="card" style="margin-bottom: 1.5rem;">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <div class="card-header-left">
            <i class="fa-solid fa-calendar-check" style="color: #047857;"></i>
            <h2>Scheduled Defense Sessions</h2>
        </div>
        <span class="badge" style="background: #ECFDF5; color: #047857; font-weight: 700; border: 1px solid #A7F3D0; padding: 0.2rem 0.6rem; border-radius: 999px;">
            <?php echo $panel_defenses_q ? $panel_defenses_q->num_rows : 0; ?> Session(s)
        </span>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if(!$panel_defenses_q || $panel_defenses_q->num_rows == 0): ?>
            <div style="text-align: center; color: var(--text-muted); padding: 2rem 1rem;">
                <p style="color: #64748B; font-size: 0.88rem; margin: 0;">No upcoming defense sessions assigned yet.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Candidate / Group</th>
                            <th>Defense Stage</th>
                            <th>Date & Time</th>
                            <th>Venue</th>
                            <th>Your Role</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($pds = $panel_defenses_q->fetch_assoc()): 
                            $is_chair = ((int)$pds['panel_chair_id'] === $uid);
                            $cand_lbl = !empty($pds['group_name']) ? "Group {$pds['group_name']} ({$pds['student_name']})" : $pds['student_name'];
                            $time_fmt = !empty($pds['start_time']) ? date('g:i A', strtotime($pds['start_time'])) : 'TBA';
                            $date_fmt = date('M d, Y', strtotime($pds['meeting_date']));
                        ?>
                            <tr>
                                <td>
                                    <strong style="color: #0F172A;"><?php echo htmlspecialchars($cand_lbl); ?></strong>
                                    <div style="font-size: 0.78rem; color: #64748B;"><?php echo htmlspecialchars(mb_strimwidth($pds['title'], 0, 45, '...')); ?></div>
                                </td>
                                <td>
                                    <?php 
                                        $p_cat = !empty($pds['defense_category']) ? $pds['defense_category'] : (function_exists('resolve_defense_category') ? resolve_defense_category($pds['defense_stage']) : 'Final Defense');
                                        $p_cc = !empty($pds['course_code']) ? $pds['course_code'] : (function_exists('resolve_course_code') ? resolve_course_code($pds['defense_stage']) : 'IT 199');
                                        $p_ay = !empty($pds['academic_year']) ? $pds['academic_year'] : (function_exists('calculate_academic_year') ? calculate_academic_year($pds['meeting_date']) : '');
                                        $is_p_prop = ($p_cat === 'Proposal Defense' || $p_cc === 'IT 198');
                                    ?>
                                    <div style="display: flex; flex-direction: column; gap: 0.25rem; align-items: flex-start;">
                                        <span style="background: <?php echo $is_p_prop ? '#E0F2FE' : '#D1FAE5'; ?>; color: <?php echo $is_p_prop ? '#0369A1' : '#047857'; ?>; border: 1px solid <?php echo $is_p_prop ? '#BAE6FD' : '#A7F3D0'; ?>; font-size: 0.7rem; font-weight: 800; padding: 0.12rem 0.5rem; border-radius: 999px;">
                                            <i class="fa-solid fa-graduation-cap"></i> <?php echo htmlspecialchars($p_cc); ?>: <?php echo htmlspecialchars($p_cat); ?>
                                        </span>
                                        <span style="background: #EFF6FF; color: #1D4ED8; font-size: 0.72rem; font-weight: 700; padding: 0.12rem 0.5rem; border-radius: 999px; border: 1px solid #BFDBFE;">
                                            <?php echo htmlspecialchars($pds['defense_stage']); ?>
                                        </span>
                                        <?php if(!empty($p_ay)): ?>
                                            <span style="font-size: 0.68rem; color: #64748B; font-weight: 700;">
                                                <i class="fa-solid fa-calendar-days" style="color: #047857;"></i> A.Y. <?php echo htmlspecialchars($p_ay); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <strong><?php echo $date_fmt; ?></strong>
                                    <div style="font-size: 0.78rem; color: #64748B;"><?php echo $time_fmt; ?></div>
                                </td>
                                <td>
                                    <span style="font-size: 0.82rem; font-weight: 600; color: #334155;">
                                        <i class="fa-solid fa-location-dot" style="color: #047857;"></i> <?php echo htmlspecialchars($pds['venue'] ?? 'CEIT Defense Hall'); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if($is_chair): ?>
                                        <span style="background: #FEF3C7; color: #78350F; border: 1px solid #FCD34D; font-size: 0.72rem; font-weight: 800; padding: 0.15rem 0.5rem; border-radius: 999px;">
                                            <i class="fa-solid fa-crown" style="color: #D97706;"></i> Panel Chair
                                        </span>
                                    <?php else: ?>
                                        <span style="background: #ECFDF5; color: #047857; border: 1px solid #A7F3D0; font-size: 0.72rem; font-weight: 700; padding: 0.15rem 0.5rem; border-radius: 999px;">
                                            Panel Member
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right;">
                                    <a href="?tab=meeting_doc&student_id=<?php echo $pds['student_id']; ?>" class="btn-primary" style="padding: 0.35rem 0.75rem; font-size: 0.78rem; background: #047857; text-decoration: none; border-radius: 6px;">
                                        <i class="fa-solid fa-microphone-lines"></i> Open MOM & Voice
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-header-left">
            <i class="fa-solid fa-list-check"></i>
            <h2>Recent Oral Defense Assignments</h2>
        </div>
        <span class="badge badge-info"><?php echo $assigned_res->num_rows; ?> Total</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if($assigned_res->num_rows == 0): ?>
            <div style="text-align: center; color: var(--text-muted); padding: 3rem 1rem;">
                <i class="fa-solid fa-folder-open fa-3x" style="color: #CBD5E1; margin-bottom: 1rem; display: block;"></i>
                <p style="color: #64748B; font-size: 0.9rem; margin: 0;">No capstone projects have been assigned to your committee yet.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Student Candidate</th>
                            <th>Capstone Research Topic</th>
                            <th>Evaluation Status</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $count = 0;
                        while($row = $assigned_res->fetch_assoc()): 
                            if($count >= 5) break;
                            $count++;
                        ?>
                        <tr>
                            <td>
                                <div style="font-weight: 700; color: #0F172A;"><?php echo htmlspecialchars($row['stu_name']); ?></div>
                                <div style="font-size: 0.78rem; color: #64748B;"><?php echo htmlspecialchars($row['email']); ?></div>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: #1E293B; max-width: 420px; line-height: 1.4;">
                                    <?php echo htmlspecialchars($row['thesis_topic'] ?: 'Proposal pending submission'); ?>
                                </div>
                            </td>
                            <td>
                                <?php if($row['status'] == 'graded'): ?>
                                    <span class="badge badge-success"><i class="fa-solid fa-check"></i> Graded: <?php echo $row['grade']; ?>%</span>
                                <?php else: ?>
                                    <span class="badge badge-pending"><i class="fa-regular fa-clock"></i> Pending Review</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: inline-flex; gap: 0.35rem; align-items: center;">
                                    <?php if(!empty($row['manuscript_file'])): ?>
                                        <a href="<?php echo htmlspecialchars($row['manuscript_file']); ?>" target="_blank" class="btn btn-outline" style="text-decoration: none; padding: 0.35rem 0.65rem; font-size: 0.75rem; color: #DC2626; border-color: #FECACA; background: #FEF2F2;" title="View Manuscript PDF">
                                            <i class="fa-solid fa-file-pdf"></i>
                                        </a>
                                    <?php elseif(!empty($row['shareable_link'])): ?>
                                        <a href="<?php echo htmlspecialchars($row['shareable_link']); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline" style="text-decoration: none; padding: 0.35rem 0.65rem; font-size: 0.75rem; color: #2563EB; border-color: #BFDBFE; background: #EFF6FF;" title="Open Online Manuscript">
                                            <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                        </a>
                                    <?php endif; ?>
                                    <a href="?tab=comments&student_id=<?php echo $row['student_id']; ?>" class="btn btn-outline" style="text-decoration: none; padding: 0.35rem 0.65rem; font-size: 0.75rem;" title="Comments Panel">
                                        <i class="fa-solid fa-comments"></i>
                                    </a>
                                    <a href="?tab=meeting_doc&student_id=<?php echo $row['student_id']; ?>" class="btn btn-outline" style="text-decoration: none; padding: 0.35rem 0.65rem; font-size: 0.75rem;" title="Defense Minutes & Voice">
                                        <i class="fa-solid fa-microphone-lines"></i>
                                    </a>
                                    <a href="?tab=grading&tp_id=<?php echo $row['id']; ?>" class="btn-primary" style="text-decoration: none; padding: 0.35rem 0.85rem; font-size: 0.8rem;">
                                        <i class="fa-solid fa-file-pen"></i> Evaluate
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
