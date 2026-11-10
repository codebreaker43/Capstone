<div class="banner">
    <h1><i class="fa-solid fa-chalkboard-user"></i> Faculty Adviser Dashboard</h1>
    <p>Welcome, <?php echo htmlspecialchars($name); ?> • CEIT Department of <?php echo htmlspecialchars($profile['department'] ?? 'Information Technology'); ?></p>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon"><i class="fa-solid fa-user-graduate"></i></div>
        <div class="stat-value"><?php echo $advisees_res->num_rows; ?></div>
        <div class="stat-label">Active Advisees</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fa-solid fa-inbox"></i></div>
        <div class="stat-value"><?php echo $req_res->num_rows; ?></div>
        <div class="stat-label">Advisee Requests</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fa-solid fa-users-line"></i></div>
        <div class="stat-value"><?php echo $profile['capacity'] ?? 5; ?></div>
        <div class="stat-label">Total Student Capacity</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
        <div class="stat-value"><?php echo max(0, ($profile['capacity'] ?? 5) - $advisees_res->num_rows); ?></div>
        <div class="stat-label">Available Slots</div>
    </div>
    <?php 
    $ov_c_stats = count_adviser_advisee_comments($conn, $uid);
    ?>
    <div class="stat-card">
        <div class="stat-icon" style="background: <?php echo $ov_c_stats['revisions'] > 0 ? '#FEE2E2' : '#E0F2FE'; ?>; color: <?php echo $ov_c_stats['revisions'] > 0 ? '#DC2626' : '#0284C7'; ?>;">
            <i class="fa-solid <?php echo $ov_c_stats['revisions'] > 0 ? 'fa-triangle-exclamation' : 'fa-comments'; ?>"></i>
        </div>
        <div class="stat-value" style="<?php if($ov_c_stats['revisions'] > 0) echo 'color: #DC2626;'; ?>">
            <?php echo $ov_c_stats['total']; ?>
        </div>
        <div class="stat-label">
            Panel Feedback <?php if($ov_c_stats['revisions'] > 0): ?><span style="color: #DC2626; font-weight: 700;">(<?php echo $ov_c_stats['revisions']; ?> Revisions)</span><?php endif; ?>
        </div>
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
        <div class="quick-actions" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
            <a href="?tab=requests"><button class="btn-primary" style="width: 100%;"><i class="fa-solid fa-inbox"></i> Requests (<?php echo $req_res->num_rows; ?>)</button></a>
            <a href="?tab=consultations"><button class="btn-primary" style="width: 100%; background: #022C22; border-color: #064E3B; color: #FCD34D;"><i class="fa-solid fa-clipboard-check"></i> Consultations (<?php echo $pending_consultations_count ?? 0; ?>)</button></a>
            <a href="?tab=advisees"><button class="btn-primary btn-success" style="width: 100%;"><i class="fa-solid fa-user-graduate"></i> My Advisees</button></a>
            <a href="?tab=panel_comments"><button class="btn-primary" style="width: 100%; background: #059669; border-color: #059669;"><i class="fa-solid fa-comments"></i> Panel Comments (<?php echo $ov_c_stats['total']; ?>)</button></a>
            <a href="?tab=schedule"><button class="btn-primary btn-warning" style="width: 100%;"><i class="fa-solid fa-calendar-check"></i> Manage Schedule</button></a>
            <a href="?tab=expertise"><button class="btn btn-outline" style="width: 100%; border-radius: 8px;"><i class="fa-solid fa-tags"></i> Edit Expertise</button></a>
        </div>
    </div>
</div>

<!-- Recent Panel Feedback for Supervised Advisees -->
<?php 
$recent_adv_comments = get_adviser_advisee_comments($conn, $uid, null, null, null, null);
$recent_preview = array_slice($recent_adv_comments, 0, 4);
?>
<div class="card">
    <div class="card-header">
        <div class="card-header-left">
            <i class="fa-solid fa-comments"></i>
            <h2>Recent Committee Feedback for Advisees</h2>
        </div>
        <a href="?tab=panel_comments" style="font-size: 0.82rem; font-weight: 700; color: #047857; text-decoration: none;">
            View All Comments (<?php echo count($recent_adv_comments); ?>) <i class="fa-solid fa-arrow-right"></i>
        </a>
    </div>
    <div class="card-body">
        <?php if (empty($recent_preview)): ?>
            <div style="text-align: center; padding: 2rem 1rem; color: #64748B;">
                <i class="fa-solid fa-comment-slash fa-2x" style="color: #CBD5E1; margin-bottom: 0.5rem; display: block;"></i>
                <p style="margin: 0; font-size: 0.88rem;">No panel evaluation comments recorded yet for your supervisees.</p>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 0.85rem;">
                <?php foreach($recent_preview as $rc): 
                    $t_bg = '#F1F5F9';
                    $t_color = '#334155';
                    if ($rc['feedback_type'] === 'Required Revision') {
                        $t_bg = '#FEE2E2'; $t_color = '#991B1B';
                    } elseif ($rc['feedback_type'] === 'Commendation') {
                        $t_bg = '#DCFCE7'; $t_color = '#166534';
                    }
                ?>
                <div style="border: 1px solid #E2E8F0; border-radius: 8px; padding: 0.85rem 1rem; background: #FFFFFF; display: flex; justify-content: space-between; align-items: flex-start; gap: 0.75rem; flex-wrap: wrap;">
                    <div style="flex: 1; min-width: 240px;">
                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                            <strong style="color: #0F172A; font-size: 0.9rem;">
                                <?php echo htmlspecialchars($rc['student_name']); ?>
                            </strong>
                            <?php if(!empty($rc['group_name'])): ?>
                                <span style="font-size: 0.72rem; color: #047857; font-weight: 700; background: #ECFDF5; padding: 1px 6px; border-radius: 4px;">
                                    <?php echo htmlspecialchars($rc['group_name']); ?>
                                </span>
                            <?php endif; ?>
                            <span style="font-size: 0.75rem; padding: 2px 7px; border-radius: 999px; background: <?php echo $t_bg; ?>; color: <?php echo $t_color; ?>; font-weight: 700;">
                                <?php echo htmlspecialchars($rc['feedback_type']); ?>
                            </span>
                            <?php if(!empty($rc['audio_file'])): ?>
                                <span style="font-size: 0.72rem; padding: 2px 7px; border-radius: 999px; background: #FEF2F2; color: #DC2626; font-weight: 800; border: 1px solid #FECACA;">
                                    <i class="fa-solid fa-microphone-lines"></i> Voice Recorded
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php if(!empty($rc['audio_file'])): ?>
                        <div style="background: #ECFDF5; border: 1px solid #A7F3D0; border-radius: 6px; padding: 0.45rem 0.65rem; margin: 0.35rem 0;">
                            <div style="font-size: 0.73rem; font-weight: 800; color: #065F46; margin-bottom: 0.2rem;">
                                <i class="fa-solid fa-volume-high"></i> Play Panel Voice Recording
                                <?php if(!empty($rc['audio_duration'])): ?> (<?php echo gmdate("i:s", (int)$rc['audio_duration']); ?>)<?php endif; ?>
                            </div>
                            <audio controls preload="metadata" src="<?php echo htmlspecialchars($rc['audio_file']); ?>" style="width: 100%; height: 32px;"></audio>
                        </div>
                        <?php endif; ?>
                        <?php if(!empty($rc['comment_text']) && $rc['comment_text'] !== '[Voice Recorded Comment]'): ?>
                        <div style="font-size: 0.84rem; color: #334155; line-height: 1.4; margin-bottom: 0.35rem;">
                            <?php echo htmlspecialchars(mb_strimwidth($rc['comment_text'], 0, 140, '...')); ?>
                        </div>
                        <?php endif; ?>
                        <div style="font-size: 0.75rem; color: #64748B;">
                            <i class="fa-solid fa-user-check"></i> <?php echo htmlspecialchars($rc['panel_name']); ?> • 
                            <i class="fa-regular fa-clock"></i> <?php echo date("M d, Y", strtotime($rc['created_at'])); ?>
                        </div>
                    </div>
                    <div style="align-self: center;">
                        <a href="?tab=panel_comments&student_id=<?php echo $rc['student_id']; ?>" class="btn" style="background: #F8FAFC; border: 1px solid #CBD5E1; color: #0F172A; font-size: 0.78rem; font-weight: 700; padding: 0.35rem 0.75rem; border-radius: 6px; text-decoration: none; white-space: nowrap;">
                            <i class="fa-solid fa-magnifying-glass"></i> View Details
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
