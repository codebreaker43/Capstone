<div class="card">
    <div class="card-header">
        <div class="card-header-left">
            <i class="fa-solid fa-user-graduate"></i>
            <h2>My Active Advisees</h2>
        </div>
        <span class="badge badge-success"><i class="fa-solid fa-check"></i> <?php echo $advisees_res->num_rows; ?> Advisees</span>
    </div>
    <div class="card-body">
        <?php if($advisees_res->num_rows == 0): ?>
            <div style="text-align: center; color: var(--text-muted); padding: 3rem 1rem;">
                <i class="fa-solid fa-graduation-cap fa-3x" style="color: #CBD5E1; margin-bottom: 1rem; display: block;"></i>
                <h3 style="color: #0F172A; font-size: 1.1rem; margin-bottom: 0.35rem;">No Active Advisees Yet</h3>
                <p style="color: #64748B; font-size: 0.88rem; margin: 0;">Once you accept student advisee requests, their capstone projects and research manuscripts will appear here.</p>
            </div>
        <?php else: ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.15rem;">
                <?php while($adv = $advisees_res->fetch_assoc()): 
                    // Query group and 3 members if group_id exists
                    $adv_group = null;
                    $adv_members = [];
                    if (!empty($adv['group_id'])) {
                        $agid = (int)$adv['group_id'];
                        $ag_res = $conn->query("SELECT * FROM capstone_group WHERE id=$agid");
                        if ($ag_res && $ag_res->num_rows > 0) {
                            $adv_group = $ag_res->fetch_assoc();
                            $adv_members = get_group_members_roster($conn, $agid);
                        }
                    }
                ?>
                    <div style="background: white; border: 1px solid #E2E8F0; border-radius: 12px; padding: 1.25rem; box-shadow: var(--shadow-sm); display: flex; flex-direction: column; justify-content: space-between; gap: 0.85rem; transition: all 0.2s ease;">
                        <div>
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem;">
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <div style="background: var(--csu-green); color: white; width: 42px; height: 42px; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 1.1rem; font-weight: 800; flex-shrink: 0;">
                                        <?php echo strtoupper(substr($adv['stu_name'], 0, 1)); ?>
                                    </div>
                                    <div style="overflow: hidden;">
                                        <h3 style="margin: 0; color: #0F172A; font-size: 1rem; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($adv['stu_name']); ?></h3>
                                        <p style="margin: 0; color: #64748B; font-size: 0.8rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($adv['email']); ?></p>
                                    </div>
                                </div>
                                <?php if($adv_group): ?>
                                    <span class="badge <?php echo count($adv_members) >= 3 ? 'badge-success' : 'badge-warning'; ?>" style="font-size: 0.72rem;">
                                        <i class="fa-solid fa-users"></i> <?php echo count($adv_members); ?>/3
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 0.85rem; margin-bottom: 0.75rem;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.45rem; flex-wrap: wrap; gap: 0.35rem;">
                                    <div style="display: flex; align-items: center; gap: 0.4rem; flex-wrap: wrap;">
                                        <div style="font-size: 0.72rem; color: #64748B; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">
                                            <i class="fa-solid fa-book" style="color: var(--csu-green);"></i> Capstone Topic
                                        </div>
                                        <?php if(!empty($adv['course_code'])): ?>
                                            <span style="background: <?php echo ($adv['course_code'] === 'IT 198') ? '#E0F2FE; color: #0369A1; border: 1px solid #BAE6FD;' : '#D1FAE5; color: #047857; border: 1px solid #A7F3D0;'; ?> font-size: 0.68rem; font-weight: 800; padding: 1px 6px; border-radius: 999px;">
                                                <?php echo htmlspecialchars($adv['course_code']); ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if(!empty($adv['academic_year'])): ?>
                                            <span style="background: #FFFBEB; color: #B45309; border: 1px solid #FDE68A; font-size: 0.68rem; font-weight: 800; padding: 1px 6px; border-radius: 999px;">
                                                A.Y. <?php echo htmlspecialchars($adv['academic_year']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <!-- Title Approval Status Badge -->
                                    <?php if(!empty($adv['thesis_topic'])): ?>
                                        <?php if(($adv['title_status'] ?? 'pending') == 'approved'): ?>
                                            <span class="badge" style="background: #ECFDF5; color: #047857; border: 1px solid #A7F3D0; font-weight: 700; font-size: 0.7rem; padding: 2px 7px; border-radius: 999px;">
                                                <i class="fa-solid fa-circle-check"></i> Title Approved
                                            </span>
                                        <?php elseif(($adv['title_status'] ?? '') == 'rejected'): ?>
                                            <span class="badge" style="background: #FEF2F2; color: #B91C1C; border: 1px solid #FECACA; font-weight: 700; font-size: 0.7rem; padding: 2px 7px; border-radius: 999px;">
                                                <i class="fa-solid fa-circle-xmark"></i> Title Needs Revision
                                            </span>
                                        <?php else: ?>
                                            <span class="badge" style="background: #FFFBEB; color: #B45309; border: 1px solid #FDE68A; font-weight: 700; font-size: 0.7rem; padding: 2px 7px; border-radius: 999px;">
                                                <i class="fa-regular fa-clock"></i> Title Pending Approval
                                            </span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                <p style="font-size: 0.88rem; color: #1E293B; margin: 0 0 0.5rem 0; font-weight: 600; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                    <?php echo htmlspecialchars($adv['thesis_topic'] ?: 'No Topic Registered'); ?>
                                </p>

                                <?php if(!empty($adv['abstract_file']) && file_exists($adv['abstract_file'])): ?>
                                    <div style="margin-bottom: 0.5rem;">
                                        <a href="<?php echo htmlspecialchars($adv['abstract_file']); ?>" target="_blank" style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.75rem; font-weight: 600; color: #DC2626; background: #FEF2F2; border: 1px solid #FECACA; padding: 0.2rem 0.55rem; border-radius: 4px; text-decoration: none;">
                                            <i class="fa-solid fa-file-pdf"></i> View Concept PDF
                                        </a>
                                    </div>
                                <?php endif; ?>

                                <!-- Title Approval Quick Action Bar -->
                                <?php if(!empty($adv['thesis_topic'])): ?>
                                    <div style="display: flex; gap: 0.35rem; align-items: center; justify-content: space-between; border-top: 1px dashed #CBD5E1; padding-top: 0.45rem; font-size: 0.75rem;">
                                        <?php if(($adv['title_status'] ?? 'pending') != 'approved'): ?>
                                            <a href="action.php?update_title_approval=1&student_id=<?php echo $adv['student_id']; ?>&status=approved&return_tab=advisees" 
                                               class="btn" 
                                               style="background: #ECFDF5; color: #047857; border: 1px solid #A7F3D0; font-weight: 700; font-size: 0.72rem; padding: 2px 8px; border-radius: 4px; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                                                <i class="fa-solid fa-check"></i> Approve Title
                                            </a>
                                            <a href="action.php?update_title_approval=1&student_id=<?php echo $adv['student_id']; ?>&status=rejected&return_tab=advisees" 
                                               class="btn" 
                                               style="background: #FFF1F2; color: #BE123C; border: 1px solid #FECDD3; font-weight: 600; font-size: 0.7rem; padding: 2px 6px; border-radius: 4px; text-decoration: none;">
                                                Request Revision
                                            </a>
                                        <?php else: ?>
                                            <span style="color: #047857; font-size: 0.72rem; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                                                <i class="fa-solid fa-award" style="color: var(--csu-gold);"></i> Officially Approved Title
                                            </span>
                                            <a href="action.php?update_title_approval=1&student_id=<?php echo $adv['student_id']; ?>&status=rejected&return_tab=advisees" 
                                               style="color: #94A3B8; font-size: 0.68rem; text-decoration: underline; cursor: pointer;">
                                                Revoke Approval
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- 3 Group Members Section -->
                            <div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 8px; padding: 0.75rem; margin-bottom: 0.5rem;">
                                <div style="font-size: 0.72rem; color: #475569; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.4rem; display: flex; justify-content: space-between; align-items: center;">
                                    <span><i class="fa-solid fa-users" style="color: var(--primary-blue);"></i> Group Members</span>
                                    <?php if($adv_group): ?>
                                        <span style="color: var(--csu-green); text-transform: none; font-size: 0.75rem; font-weight: 600;"><?php echo htmlspecialchars($adv_group['group_name']); ?></span>
                                    <?php endif; ?>
                                </div>

                                <?php if($adv_group && count($adv_members) > 0): ?>
                                    <div style="display: flex; flex-direction: column; gap: 0.35rem;">
                                        <?php 
                                        for($slot = 0; $slot < 3; $slot++): 
                                            $mem = $adv_members[$slot] ?? null;
                                        ?>
                                            <?php if($mem): ?>
                                                <div style="display: flex; align-items: center; justify-content: space-between; font-size: 0.8rem; background: #F8FAFC; padding: 0.3rem 0.5rem; border-radius: 6px; border: 1px solid #E2E8F0;">
                                                    <span style="color: #0F172A; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 170px;">
                                                        <i class="fa-solid <?php echo $mem['is_leader'] ? 'fa-crown' : 'fa-user'; ?>" style="color: <?php echo $mem['is_leader'] ? '#D97706' : '#047857'; ?>; font-size: 0.72rem; margin-right: 0.25rem;"></i>
                                                        <?php echo htmlspecialchars($mem['name']); ?>
                                                    </span>
                                                    <span style="font-size: 0.7rem; color: <?php echo $mem['is_leader'] ? '#B45309' : '#64748B'; ?>; font-weight: 700;">
                                                        <?php echo $mem['is_leader'] ? 'Leader' : 'Member '.($slot+1); ?>
                                                    </span>
                                                </div>
                                            <?php else: ?>
                                                <div style="font-size: 0.75rem; color: #94A3B8; font-style: italic; background: #FAFAFA; padding: 0.25rem 0.5rem; border-radius: 6px; border: 1px dashed #CBD5E1;">
                                                    <i class="fa-regular fa-circle" style="font-size: 0.65rem; margin-right: 0.25rem;"></i> Vacant Member Slot <?php echo $slot + 1; ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                    </div>
                                <?php else: ?>
                                    <div style="font-size: 0.78rem; color: #D97706; background: #FEF3C7; padding: 0.4rem 0.6rem; border-radius: 6px; border: 1px solid #FDE68A;">
                                        <i class="fa-solid fa-triangle-exclamation"></i> Individual student (No group formed yet)
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div style="display: flex; flex-direction: column; gap: 0.45rem; margin-top: 0.35rem;">
                            <?php
                            $adv_cons = get_student_consultation_summary($conn, (int)$adv['student_id'], (int)($adv['group_id'] ?? 0));
                            ?>
                            <div style="background: <?php echo $adv_cons['is_process_completed'] ? '#ECFDF5' : '#FFFBEB'; ?>; border: 1px solid <?php echo $adv_cons['is_process_completed'] ? '#6EE7B7' : '#FDE68A'; ?>; border-radius: 8px; padding: 0.5rem 0.65rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.35rem; font-size: 0.74rem;">
                                <span style="font-weight: 800; color: <?php echo $adv_cons['is_process_completed'] ? '#065F46' : '#92400E'; ?>;">
                                    <i class="fa-solid <?php echo $adv_cons['is_process_completed'] ? 'fa-circle-check' : 'fa-clock'; ?>"></i>
                                    Consultations: <?php echo $adv_cons['is_process_completed'] ? 'Process Completed' : ($adv_cons['stats']['completed'] . ' Completed / ' . $adv_cons['stats']['total'] . ' Total'); ?>
                                </span>
                                <?php if ($adv_cons['oral_defense_approved']): ?>
                                    <span style="background: #022C22; color: #FCD34D; padding: 1px 6px; border-radius: 999px; font-size: 0.68rem; font-weight: 800;">
                                        <i class="fa-solid fa-award"></i> Defense Approved
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div style="display: flex; gap: 0.45rem;">
                                <a href="?tab=consultations&student_id=<?php echo $adv['student_id']; ?>" style="text-decoration: none; flex: 1;">
                                    <button type="button" class="btn" style="width: 100%; padding: 0.48rem; font-size: 0.78rem; background: #022C22; border: 1px solid #064E3B; color: #FCD34D; font-weight: 800; border-radius: 6px; cursor: pointer;">
                                        <i class="fa-solid fa-clipboard-check"></i> Consultations &amp; Defense
                                    </button>
                                </a>
                                <a href="export_consultation_form.php?student_id=<?php echo $adv['student_id']; ?>" target="_blank" title="Print Official Consultation Form" style="text-decoration: none;">
                                    <button type="button" class="btn" style="padding: 0.48rem 0.65rem; font-size: 0.78rem; background: #F8FAFC; border: 1px solid #CBD5E1; color: #0F172A; font-weight: 700; border-radius: 6px; cursor: pointer;">
                                        <i class="fa-solid fa-print" style="color: #059669;"></i>
                                    </button>
                                </a>
                            </div>
                            <div style="display: flex; gap: 0.5rem;">
                                <a href="?tab=review&student_id=<?php echo $adv['student_id']; ?>" style="text-decoration: none; flex: 1;">
                                    <button class="btn-primary" style="width: 100%; padding: 0.5rem; font-size: 0.8rem;">
                                        <i class="fa-solid fa-file-lines"></i> Papers & Review
                                    </button>
                                </a>
                                <a href="?tab=panel_comments&student_id=<?php echo $adv['student_id']; ?>" style="text-decoration: none; flex: 1;">
                                    <button type="button" class="btn" style="width: 100%; padding: 0.5rem; font-size: 0.8rem; background: #ECFDF5; border: 1.5px solid #10B981; color: #047857; font-weight: 700; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; gap: 0.35rem; cursor: pointer;">
                                        <i class="fa-solid fa-comments"></i> Panel Comments
                                    </button>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
