<?php
// Retrieve detailed progress metrics (Shared Group Context)
$prog_req_where = (!empty($group_id)) ? "(m.group_id = $group_id OR m.student_id IN ($group_student_ids_str))" : "m.student_id = $uid";
$latest_req = $conn->query("SELECT m.*, u.name as adv_name FROM match_request m JOIN user u ON m.adviser_id = u.id WHERE $prog_req_where ORDER BY (m.status='accepted') DESC, m.id DESC LIMIT 1")->fetch_assoc();

$prog_panel_where = (!empty($group_id)) ? "(tp.group_id = $group_id OR tp.student_id IN ($group_student_ids_str))" : "tp.student_id = $uid";
$panel_members_res = $conn->query("SELECT tp.*, u.name as panel_name FROM thesis_panel tp JOIN user u ON tp.panel_id = u.id WHERE $prog_panel_where");
$panel_names = [];
if ($panel_members_res) {
    while($pm = $panel_members_res->fetch_assoc()) {
        $panel_names[] = $pm['panel_name'] . ($pm['status'] == 'graded' ? ' (Graded: ' . $pm['grade'] . '%)' : ' (Pending)');
    }
}

// Current stage label
$current_stage = "Step 1: Capstone Submission";
if ($step5_done) {
    $current_stage = "All Steps Completed! Capstone Defended";
} elseif ($step4_done) {
    $current_stage = "Step 5: Oral Defense & Evaluation";
} elseif ($step3_done) {
    $current_stage = "Step 4: Panel Committee Assignment";
} elseif ($step2_done) {
    $current_stage = "Step 3: Waiting for Adviser Approval";
} elseif ($step1_done) {
    $current_stage = "Step 2: Adviser Selection via AI";
}
?>
<div class="card">
    <div class="card-header">
        <i class="fa-solid fa-arrow-trend-up"></i>
        <h2>Capstone Progress Tracker</h2>
    </div>
    <div class="card-body">
        <!-- Progress Summary Header -->
        <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 10px; padding: 1rem 1.25rem; margin-bottom: 1.25rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; flex-wrap: wrap; gap: 0.75rem;">
                <div>
                    <h3 style="margin: 0 0 0.15rem 0; color: #1E293B; font-size: 1.1rem;">Overall Completion Progress</h3>
                    <p style="margin: 0; color: #64748B; font-size: 0.82rem;">
                        <i class="fa-solid fa-flag-checkered" style="color: var(--primary-blue);"></i> Current Stage: <strong><?php echo $current_stage; ?></strong>
                    </p>
                </div>
                <div style="text-align: right;">
                    <span style="font-size: 1.5rem; font-weight: 800; color: var(--primary-blue); display: block; line-height: 1;"><?php echo $progress; ?>%</span>
                    <span style="font-size: 0.75rem; color: #64748B; font-weight: 600;"><?php echo $completed_steps; ?> of 5 Milestones Completed</span>
                </div>
            </div>
            
            <!-- Animated Progress Bar -->
            <div style="background: #E2E8F0; height: 14px; border-radius: 999px; overflow: hidden; position: relative; border: 1px solid #CBD5E1; box-shadow: inset 0 1px 3px rgba(0,0,0,0.06);">
                <div style="width: <?php echo $progress; ?>%; background: linear-gradient(90deg, #059669 0%, #10B981 100%); height: 100%; border-radius: 999px; transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: 0 2px 5px rgba(5, 150, 105, 0.25);"></div>
            </div>

            <!-- Milestone Indicators -->
            <div style="display: flex; justify-content: space-between; margin-top: 0.5rem; font-size: 0.72rem; color: #64748B; padding: 0 0.2rem;">
                <span style="color: <?php echo $step1_done ? '#059669; font-weight:700;' : '#64748B;'; ?>">1. Proposal</span>
                <span style="color: <?php echo $step2_done ? '#059669; font-weight:700;' : '#64748B;'; ?>">2. Request</span>
                <span style="color: <?php echo $step3_done ? '#059669; font-weight:700;' : '#64748B;'; ?>">3. Adviser</span>
                <span style="color: <?php echo $step4_done ? '#059669; font-weight:700;' : '#64748B;'; ?>">4. Panel</span>
                <span style="color: <?php echo $step5_done ? '#059669; font-weight:700;' : '#64748B;'; ?>">5. Defense</span>
            </div>
        </div>

        <!-- 5-Stage System Flow Timeline -->
        <div style="position: relative; display: flex; flex-direction: column; gap: 0.75rem;">

            <!-- STEP 1: Capstone Submission -->
            <div style="background: white; border: 1px solid <?php echo $step1_done ? '#A7F3D0' : '#E2E8F0'; ?>; border-radius: 8px; padding: 0.75rem 1rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02); transition: transform 0.2s;" onmouseover="this.style.transform='translateX(3px)'" onmouseout="this.style.transform='none'">
                <div style="display: flex; align-items: center; gap: 0.85rem; flex: 1;">
                    <div style="width: 32px; height: 32px; border-radius: 50%; background: <?php echo $step1_done ? '#D1FAE5' : '#F1F5F9'; ?>; color: <?php echo $step1_done ? '#047857' : '#94A3B8'; ?>; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; font-weight: 700; flex-shrink: 0; border: 2px solid <?php echo $step1_done ? '#059669' : '#CBD5E1'; ?>;">
                        <?php echo $step1_done ? '<i class="fa-solid fa-check"></i>' : '1'; ?>
                    </div>
                    <div>
                        <h4 style="margin: 0 0 0.15rem 0; color: #1E293B; font-size: 0.92rem;">
                            Step 1: Submit Capstone Topic & Proposal
                        </h4>
                        <p style="margin: 0; color: #64748B; font-size: 0.8rem; line-height: 1.35;">
                            <?php if($step1_done): ?>
                                Topic: <strong><?php echo htmlspecialchars($profile['thesis_topic']); ?></strong>
                                <?php if(!empty($profile['manuscript_file'])): ?>
                                    • <span style="color: #059669;"><i class="fa-solid fa-file-pdf"></i> Manuscript Uploaded</span>
                                <?php endif; ?>
                            <?php else: ?>
                                Enter your proposed capstone title and upload your capstone manuscript PDF.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <?php if($step1_done): ?>
                        <span style="background: #D1FAE5; color: #047857; padding: 0.25rem 0.65rem; border-radius: 999px; font-size: 0.75rem; font-weight: 700; border: 1px solid #A7F3D0; white-space: nowrap;">
                            <i class="fa-solid fa-circle-check"></i> Completed
                        </span>
                        <a href="?tab=submission" class="btn-primary" style="padding: 0.25rem 0.6rem; font-size: 0.75rem; text-decoration: none; white-space: nowrap; background: #F1F5F9; color: #334155; border: 1px solid #CBD5E1;">Edit</a>
                    <?php else: ?>
                        <span style="background: #FEF3C7; color: #B45309; padding: 0.25rem 0.65rem; border-radius: 999px; font-size: 0.75rem; font-weight: 700; border: 1px solid #FDE68A; white-space: nowrap;">
                            <i class="fa-regular fa-clock"></i> Pending
                        </span>
                        <a href="?tab=submission" class="btn-primary" style="padding: 0.25rem 0.65rem; font-size: 0.75rem; text-decoration: none; white-space: nowrap;">Submit</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- STEP 2: Adviser Selection / AI Match Request -->
            <div style="background: white; border: 1px solid <?php echo $step2_done ? '#A7F3D0' : '#E2E8F0'; ?>; border-radius: 8px; padding: 0.75rem 1rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02); transition: transform 0.2s;" onmouseover="this.style.transform='translateX(3px)'" onmouseout="this.style.transform='none'">
                <div style="display: flex; align-items: center; gap: 0.85rem; flex: 1;">
                    <div style="width: 32px; height: 32px; border-radius: 50%; background: <?php echo $step2_done ? '#D1FAE5' : ($step1_done ? '#FEF3C7' : '#F1F5F9'); ?>; color: <?php echo $step2_done ? '#047857' : ($step1_done ? '#B45309' : '#94A3B8'); ?>; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; font-weight: 700; flex-shrink: 0; border: 2px solid <?php echo $step2_done ? '#059669' : ($step1_done ? '#F59E0B' : '#CBD5E1'); ?>;">
                        <?php echo $step2_done ? '<i class="fa-solid fa-check"></i>' : '2'; ?>
                    </div>
                    <div>
                        <h4 style="margin: 0 0 0.15rem 0; color: #1E293B; font-size: 0.92rem;">
                            Step 2: Request Adviser via AI Recommendations
                        </h4>
                        <p style="margin: 0; color: #64748B; font-size: 0.8rem; line-height: 1.35;">
                            <?php if($step2_done && $latest_req): ?>
                                Request sent to chosen adviser: <strong><?php echo htmlspecialchars($latest_req['adv_name']); ?></strong> (<?php echo $latest_req['match_score']; ?>% Match)
                            <?php else: ?>
                                Use the CEIT AI Matching Engine to find faculty matches and submit 1 adviser request.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <?php if($step2_done): ?>
                        <span style="background: #D1FAE5; color: #047857; padding: 0.25rem 0.65rem; border-radius: 999px; font-size: 0.75rem; font-weight: 700; border: 1px solid #A7F3D0; white-space: nowrap;">
                            <i class="fa-solid fa-circle-check"></i> Request Sent
                        </span>
                        <a href="?tab=requests" class="btn-primary" style="padding: 0.25rem 0.6rem; font-size: 0.75rem; text-decoration: none; white-space: nowrap; background: #F1F5F9; color: #334155; border: 1px solid #CBD5E1;">View</a>
                    <?php elseif($step1_done): ?>
                        <span style="background: #FEF3C7; color: #B45309; padding: 0.25rem 0.65rem; border-radius: 999px; font-size: 0.75rem; font-weight: 700; border: 1px solid #FDE68A; white-space: nowrap;">
                            <i class="fa-solid fa-wand-magic-sparkles"></i> Action Needed
                        </span>
                        <a href="?tab=ai" class="btn-primary" style="padding: 0.25rem 0.65rem; font-size: 0.75rem; text-decoration: none; white-space: nowrap;">Find</a>
                    <?php else: ?>
                        <span style="background: #F1F5F9; color: #94A3B8; padding: 0.25rem 0.65rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600; border: 1px solid #E2E8F0; white-space: nowrap;">
                            <i class="fa-solid fa-lock"></i> Locked
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- STEP 3: Adviser Review & Acceptance -->
            <div style="background: white; border: 1px solid <?php echo $step3_done ? '#A7F3D0' : ($step2_done ? '#FDE68A' : '#E2E8F0'); ?>; border-radius: 8px; padding: 0.75rem 1rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02); transition: transform 0.2s;" onmouseover="this.style.transform='translateX(3px)'" onmouseout="this.style.transform='none'">
                <div style="display: flex; align-items: center; gap: 0.85rem; flex: 1;">
                    <div style="width: 32px; height: 32px; border-radius: 50%; background: <?php echo $step3_done ? '#D1FAE5' : ($step2_done ? '#FEF3C7' : '#F1F5F9'); ?>; color: <?php echo $step3_done ? '#047857' : ($step2_done ? '#B45309' : '#94A3B8'); ?>; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; font-weight: 700; flex-shrink: 0; border: 2px solid <?php echo $step3_done ? '#059669' : ($step2_done ? '#F59E0B' : '#CBD5E1'); ?>;">
                        <?php echo $step3_done ? '<i class="fa-solid fa-check"></i>' : ($step2_done ? '<i class="fa-regular fa-clock"></i>' : '3'); ?>
                    </div>
                    <div>
                        <h4 style="margin: 0 0 0.15rem 0; color: #1E293B; font-size: 0.92rem;">
                            Step 3: Adviser Acceptance & Approval
                        </h4>
                        <p style="margin: 0; color: #64748B; font-size: 0.8rem; line-height: 1.35;">
                            <?php if($step3_done && $latest_req): ?>
                                Confirmed: <strong><?php echo htmlspecialchars($latest_req['adv_name']); ?></strong> is your official capstone adviser.
                            <?php elseif($step2_done && $latest_req && $latest_req['status'] == 'pending'): ?>
                                <span style="color: #B45309; font-weight: 600;"><i class="fa-regular fa-clock"></i> Waiting for approval</span> from <strong><?php echo htmlspecialchars($latest_req['adv_name']); ?></strong>.
                            <?php elseif($step2_done && $latest_req && $latest_req['status'] == 'rejected'): ?>
                                <span style="color: #DC2626;">Request was declined by <?php echo htmlspecialchars($latest_req['adv_name']); ?><?php if(!empty($latest_req['rejection_reason'])): ?> (Reason: <em><?php echo htmlspecialchars($latest_req['rejection_reason']); ?></em>)<?php endif; ?>. Please select another adviser.</span>
                            <?php else: ?>
                                Awaiting adviser request submission. The adviser must review and accept your topic.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <?php if($step3_done): ?>
                        <span style="background: #D1FAE5; color: #047857; padding: 0.25rem 0.65rem; border-radius: 999px; font-size: 0.75rem; font-weight: 700; border: 1px solid #A7F3D0; white-space: nowrap;">
                            <i class="fa-solid fa-circle-check"></i> Approved
                        </span>
                        <a href="?tab=requests" class="btn-primary" style="padding: 0.25rem 0.6rem; font-size: 0.75rem; text-decoration: none; white-space: nowrap; background: #F1F5F9; color: #334155; border: 1px solid #CBD5E1;">View</a>
                    <?php elseif($step2_done && $latest_req && $latest_req['status'] == 'pending'): ?>
                        <span style="background: #FEF3C7; color: #B45309; padding: 0.25rem 0.65rem; border-radius: 999px; font-size: 0.75rem; font-weight: 700; border: 1px solid #FDE68A; white-space: nowrap;">
                            <i class="fa-regular fa-clock"></i> Pending
                        </span>
                        <a href="?tab=requests" class="btn-primary" style="padding: 0.25rem 0.65rem; font-size: 0.75rem; text-decoration: none; white-space: nowrap;">Track</a>
                    <?php elseif($step2_done && $latest_req && $latest_req['status'] == 'rejected'): ?>
                        <span style="background: #FEE2E2; color: #B91C1C; padding: 0.25rem 0.65rem; border-radius: 999px; font-size: 0.75rem; font-weight: 700; border: 1px solid #FECACA; white-space: nowrap;">
                            <i class="fa-solid fa-xmark"></i> Rejected
                        </span>
                        <a href="?tab=ai" class="btn-primary" style="padding: 0.25rem 0.65rem; font-size: 0.75rem; text-decoration: none; white-space: nowrap;">Pick Another</a>
                    <?php else: ?>
                        <span style="background: #F1F5F9; color: #94A3B8; padding: 0.25rem 0.65rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600; border: 1px solid #E2E8F0; white-space: nowrap;">
                            <i class="fa-solid fa-lock"></i> Locked
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ADVISER CONSULTATION PROCESS & ORAL DEFENSE CLEARANCE -->
            <?php
            $prog_cons = get_student_consultation_summary($conn, $uid, $group_id);
            $cons_done = $prog_cons['is_process_completed'];
            ?>
            <div style="background: white; border: 1px solid <?php echo $cons_done ? '#A7F3D0' : '#FDE68A'; ?>; border-radius: 8px; padding: 0.75rem 1rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02); transition: transform 0.2s;" onmouseover="this.style.transform='translateX(3px)'" onmouseout="this.style.transform='none'">
                <div style="display: flex; align-items: center; gap: 0.85rem; flex: 1;">
                    <div style="width: 32px; height: 32px; border-radius: 50%; background: <?php echo $cons_done ? '#D1FAE5' : '#FEF3C7'; ?>; color: <?php echo $cons_done ? '#047857' : '#B45309'; ?>; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; font-weight: 700; flex-shrink: 0; border: 2px solid <?php echo $cons_done ? '#059669' : '#F59E0B'; ?>;">
                        <i class="fa-solid <?php echo $cons_done ? 'fa-check' : 'fa-comments'; ?>"></i>
                    </div>
                    <div>
                        <h4 style="margin: 0 0 0.15rem 0; color: #1E293B; font-size: 0.92rem;">
                            Required Adviser Consultation Process &amp; Oral Defense Clearance
                        </h4>
                        <p style="margin: 0; color: #64748B; font-size: 0.8rem; line-height: 1.35;">
                            <?php if ($cons_done): ?>
                                Confirmed by Adviser: Required consultation process is <strong>Completed</strong> (<?php echo (int)$prog_cons['stats']['completed']; ?> session(s) recorded).
                                <?php if ($prog_cons['oral_defense_approved']): ?>
                                    &bull; <strong style="color: #059669;">Endorsed for <?php echo htmlspecialchars($prog_cons['oral_defense_stage_approved']); ?></strong>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php echo (int)$prog_cons['stats']['completed']; ?> completed consultation(s) out of <?php echo (int)$prog_cons['stats']['total']; ?> requested. Complete required adviser consultations to unlock Oral Defense endorsement.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <?php if ($cons_done): ?>
                        <span style="background: #D1FAE5; color: #047857; padding: 0.25rem 0.65rem; border-radius: 999px; font-size: 0.75rem; font-weight: 700; border: 1px solid #A7F3D0; white-space: nowrap;">
                            <i class="fa-solid fa-circle-check"></i> Completed
                        </span>
                    <?php else: ?>
                        <span style="background: #FEF3C7; color: #B45309; padding: 0.25rem 0.65rem; border-radius: 999px; font-size: 0.75rem; font-weight: 700; border: 1px solid #FDE68A; white-space: nowrap;">
                            <i class="fa-solid fa-clock"></i> <?php echo ucfirst(str_replace('_', ' ', $prog_cons['process_status'])); ?>
                        </span>
                    <?php endif; ?>
                    <a href="?tab=consultations" class="btn-primary" style="padding: 0.25rem 0.65rem; font-size: 0.75rem; text-decoration: none; white-space: nowrap;">Consultations</a>
                    <a href="export_consultation_form.php?student_id=<?php echo $uid; ?>" target="_blank" class="btn-primary" style="padding: 0.25rem 0.6rem; font-size: 0.75rem; text-decoration: none; white-space: nowrap; background: #F1F5F9; color: #334155; border: 1px solid #CBD5E1;" title="Print Consultation Form"><i class="fa-solid fa-print"></i> Form</a>
                </div>
            </div>

            <!-- STEP 4: Panelists Assignment -->
            <div style="background: white; border: 1px solid <?php echo $step4_done ? '#A7F3D0' : '#E2E8F0'; ?>; border-radius: 8px; padding: 0.75rem 1rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02); transition: transform 0.2s;" onmouseover="this.style.transform='translateX(3px)'" onmouseout="this.style.transform='none'">
                <div style="display: flex; align-items: center; gap: 0.85rem; flex: 1;">
                    <div style="width: 32px; height: 32px; border-radius: 50%; background: <?php echo $step4_done ? '#D1FAE5' : ($step3_done ? '#FEF3C7' : '#F1F5F9'); ?>; color: <?php echo $step4_done ? '#047857' : ($step3_done ? '#B45309' : '#94A3B8'); ?>; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; font-weight: 700; flex-shrink: 0; border: 2px solid <?php echo $step4_done ? '#059669' : ($step3_done ? '#F59E0B' : '#CBD5E1'); ?>;">
                        <?php echo $step4_done ? '<i class="fa-solid fa-check"></i>' : '4'; ?>
                    </div>
                    <div>
                        <h4 style="margin: 0 0 0.15rem 0; color: #1E293B; font-size: 0.92rem;">
                            Step 4: Panel Committee Assignment
                        </h4>
                        <p style="margin: 0; color: #64748B; font-size: 0.8rem; line-height: 1.35;">
                            <?php if($panel_count > 0): ?>
                                <?php echo $panel_count; ?> Panel member(s) assigned: <strong><?php echo htmlspecialchars(implode(', ', $panel_names)); ?></strong>
                            <?php elseif($step3_done): ?>
                                Your adviser has approved. Awaiting panel assignment by the CEIT Administrator.
                            <?php else: ?>
                                Panel committee will be assigned by the administrator after your adviser confirms approval.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <?php if($step4_done): ?>
                        <span style="background: #D1FAE5; color: #047857; padding: 0.25rem 0.65rem; border-radius: 999px; font-size: 0.75rem; font-weight: 700; border: 1px solid #A7F3D0; white-space: nowrap;">
                            <i class="fa-solid fa-circle-check"></i> Assigned (<?php echo $panel_count; ?>)
                        </span>
                        <a href="?tab=panelists" class="btn-primary" style="padding: 0.25rem 0.6rem; font-size: 0.75rem; text-decoration: none; white-space: nowrap; background: #F1F5F9; color: #334155; border: 1px solid #CBD5E1;">View</a>
                    <?php elseif($step3_done): ?>
                        <span style="background: #FEF3C7; color: #B45309; padding: 0.25rem 0.65rem; border-radius: 999px; font-size: 0.75rem; font-weight: 700; border: 1px solid #FDE68A; white-space: nowrap;">
                            <i class="fa-regular fa-clock"></i> Pending
                        </span>
                        <a href="?tab=panelists" class="btn-primary" style="padding: 0.25rem 0.65rem; font-size: 0.75rem; text-decoration: none; white-space: nowrap;">Check</a>
                    <?php else: ?>
                        <span style="background: #F1F5F9; color: #94A3B8; padding: 0.25rem 0.65rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600; border: 1px solid #E2E8F0; white-space: nowrap;">
                            <i class="fa-solid fa-lock"></i> Locked
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- STEP 5: Oral Defense & Evaluation -->
            <div style="background: white; border: 1px solid <?php echo $step5_done ? '#A7F3D0' : '#E2E8F0'; ?>; border-radius: 8px; padding: 0.75rem 1rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02); transition: transform 0.2s;" onmouseover="this.style.transform='translateX(3px)'" onmouseout="this.style.transform='none'">
                <div style="display: flex; align-items: center; gap: 0.85rem; flex: 1;">
                    <div style="width: 32px; height: 32px; border-radius: 50%; background: <?php echo $step5_done ? '#D1FAE5' : ($step4_done ? '#EEF2FF' : '#F1F5F9'); ?>; color: <?php echo $step5_done ? '#047857' : ($step4_done ? 'var(--primary-blue)' : '#94A3B8'); ?>; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; font-weight: 700; flex-shrink: 0; border: 2px solid <?php echo $step5_done ? '#059669' : ($step4_done ? '#C7D2FE' : '#CBD5E1'); ?>;">
                        <?php echo $step5_done ? '<i class="fa-solid fa-award"></i>' : '5'; ?>
                    </div>
                    <div>
                        <h4 style="margin: 0 0 0.15rem 0; color: #1E293B; font-size: 0.92rem;">
                            Step 5: Oral Defense & Panel Evaluation
                        </h4>
                        <p style="margin: 0; color: #64748B; font-size: 0.8rem; line-height: 1.35;">
                            <?php if($step5_done): ?>
                                All assigned panel members have evaluated your defense. Final Average Grade: <strong style="color: #059669;"><?php echo $avg_grade; ?>%</strong>.
                            <?php elseif($graded_count > 0): ?>
                                Evaluation in progress: <strong><?php echo $graded_count; ?> of <?php echo $panel_count; ?></strong> panelist(s) graded. Current avg: <strong><?php echo $avg_grade; ?>%</strong>.
                            <?php elseif($panel_count > 0): ?>
                                Panel members assigned. Defense schedule and evaluations are pending.
                            <?php else: ?>
                                Final defense grading and evaluation occurs after panel members are assigned.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <?php if($step5_done): ?>
                        <span style="background: #D1FAE5; color: #047857; padding: 0.25rem 0.65rem; border-radius: 999px; font-size: 0.75rem; font-weight: 700; border: 1px solid #A7F3D0; white-space: nowrap;">
                            <i class="fa-solid fa-award"></i> Defended (<?php echo $avg_grade; ?>%)
                        </span>
                        <a href="?tab=panelists" class="btn-primary" style="padding: 0.25rem 0.6rem; font-size: 0.75rem; text-decoration: none; white-space: nowrap; background: #F1F5F9; color: #334155; border: 1px solid #CBD5E1;">Grades</a>
                    <?php elseif($graded_count > 0): ?>
                        <span style="background: #EEF2FF; color: var(--primary-blue); padding: 0.25rem 0.65rem; border-radius: 999px; font-size: 0.75rem; font-weight: 700; border: 1px solid #C7D2FE; white-space: nowrap;">
                            <i class="fa-solid fa-spinner fa-spin"></i> In Progress (<?php echo $graded_count; ?>/<?php echo $panel_count; ?>)
                        </span>
                        <a href="?tab=panelists" class="btn-primary" style="padding: 0.25rem 0.65rem; font-size: 0.75rem; text-decoration: none; white-space: nowrap;">View</a>
                    <?php elseif($panel_count > 0): ?>
                        <span style="background: #FEF3C7; color: #B45309; padding: 0.25rem 0.65rem; border-radius: 999px; font-size: 0.75rem; font-weight: 700; border: 1px solid #FDE68A; white-space: nowrap;">
                            <i class="fa-regular fa-clock"></i> Pending Defense
                        </span>
                        <a href="?tab=panelists" class="btn-primary" style="padding: 0.25rem 0.65rem; font-size: 0.75rem; text-decoration: none; white-space: nowrap;">View</a>
                    <?php else: ?>
                        <span style="background: #F1F5F9; color: #94A3B8; padding: 0.25rem 0.65rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600; border: 1px solid #E2E8F0; white-space: nowrap;">
                            <i class="fa-solid fa-lock"></i> Locked
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <?php
            // IT-199 Requirements Checklist in Progress Tracker
            $prog_chk_items = get_student_checklist_items($conn, $uid, $group_id);
            $prog_chk_stats = get_checklist_statistics($prog_chk_items);
            ?>
            <!-- IT-199 REQUIREMENTS CHECKLIST MILESTONE CARD -->
            <div style="margin-top: 1.25rem; background: #F8FAFC; border: 1.5px solid #CBD5E1; border-radius: 10px; padding: 1.15rem 1.35rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 0.85rem;">
                    <div style="display: flex; align-items: center; gap: 0.65rem;">
                        <div style="width: 36px; height: 36px; border-radius: 8px; background: #FEF3C7; color: #D97706; display: flex; align-items: center; justify-content: center; font-size: 1.15rem;">
                            <i class="fa-solid fa-list-check"></i>
                        </div>
                        <div>
                            <h4 style="margin: 0; color: #0F172A; font-size: 0.98rem; font-weight: 700;">
                                IT-199 Capstone Requirements Checklist
                            </h4>
                            <p style="margin: 0; color: #64748B; font-size: 0.78rem;">
                                Complete all 15 required evidence items to clear final grading and avoid an Incomplete (INC) grade.
                            </p>
                        </div>
                    </div>
                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                        <span style="background: <?php echo $prog_chk_stats['incomplete'] > 0 ? '#FEE2E2' : '#D1FAE5'; ?>; color: <?php echo $prog_chk_stats['incomplete'] > 0 ? '#991B1B' : '#065F46'; ?>; padding: 0.3rem 0.75rem; border-radius: 999px; font-size: 0.76rem; font-weight: 800; border: 1px solid <?php echo $prog_chk_stats['incomplete'] > 0 ? '#FECACA' : '#A7F3D0'; ?>;">
                            <?php echo $prog_chk_stats['completed']; ?> / <?php echo $prog_chk_stats['active_required']; ?> Completed (<?php echo $prog_chk_stats['percentage']; ?>%)
                        </span>
                        <a href="?tab=checklist" class="btn-primary" style="padding: 0.35rem 0.8rem; font-size: 0.78rem; text-decoration: none; white-space: nowrap;">
                            <i class="fa-solid fa-arrow-up-right-from-square"></i> Open Checklist
                        </a>
                        <a href="export_checklist.php" class="btn" style="padding: 0.35rem 0.75rem; font-size: 0.78rem; background: #FFFBEB; border: 1px solid #FCD34D; color: #92400E; font-weight: 700; text-decoration: none; border-radius: 6px;">
                            <i class="fa-solid fa-file-word"></i> Word (.doc)
                        </a>
                    </div>
                </div>

                <div style="background: #E2E8F0; height: 10px; border-radius: 999px; overflow: hidden; margin-bottom: 0.65rem;">
                    <div style="width: <?php echo $prog_chk_stats['percentage']; ?>%; background: linear-gradient(90deg, #059669 0%, #10B981 100%); height: 100%; border-radius: 999px; transition: width 0.6s;"></div>
                </div>

                <div style="display: flex; justify-content: space-between; font-size: 0.75rem; color: #64748B;">
                    <span>Evidence Attached: <strong style="color: #0F172A;"><?php echo $prog_chk_stats['with_link']; ?> items</strong></span>
                    <span>Remaining to Clear INC: <strong style="color: #DC2626;"><?php echo $prog_chk_stats['incomplete']; ?> item(s)</strong></span>
                </div>
            </div>

        </div>
    </div>
</div>
