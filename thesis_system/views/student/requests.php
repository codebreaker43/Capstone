<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
        <div class="card-header-left" style="display: flex; align-items: center; gap: 0.5rem;">
            <i class="fa-solid fa-paper-plane" style="color: var(--primary-blue);"></i>
            <h2 style="margin: 0;">Capstone Adviser Requests</h2>
        </div>
        <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
            <a href="?tab=consultations" class="btn-primary" style="background: #047857; padding: 0.38rem 0.85rem; font-size: 0.78rem; text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem;">
                <i class="fa-solid fa-file-signature"></i> Open Consultation Form &amp; Log
            </a>
            <?php if(!empty($my_group)): ?>
                <span style="background: #ECFDF5; color: #047857; font-size: 0.76rem; font-weight: 700; padding: 0.25rem 0.65rem; border-radius: 999px; border: 1px solid #A7F3D0;">
                    <i class="fa-solid fa-users"></i> Shared for Group: <?php echo htmlspecialchars($my_group['group_name']); ?>
                </span>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <?php if($req_count == 0): ?>
            <div style="text-align: center; padding: 3rem 1rem;">
                <i class="fa-solid fa-inbox fa-3x" style="color: #CBD5E1; margin-bottom: 1rem;"></i>
                <p style="color: var(--text-muted); font-size: 1.05rem; margin-bottom: 1.5rem;">No adviser request sent yet.</p>
                <a href="?tab=ai" class="btn-primary" style="text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-solid fa-robot"></i> Find Adviser via AI
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Adviser Name</th>
                            <th>AI Match Score</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $latest_req = null;
                        while($req = $req_res->fetch_assoc()): 
                            $latest_req = $req;
                        ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.6rem;">
                                    <div style="width: 32px; height: 32px; border-radius: 50%; background: var(--csu-green-subtle); color: var(--csu-green); display: flex; align-items: center; justify-content: center; font-weight: 700;">
                                        <?php echo strtoupper(substr($req['adv_name'], 0, 1)); ?>
                                    </div>
                                    <div style="font-weight: 700; color: #0F172A;"><?php echo htmlspecialchars($req['adv_name']); ?></div>
                                </div>
                            </td>
                            <td>
                                <span style="font-weight: 800; color: var(--csu-green); font-size: 0.92rem;"><?php echo $req['match_score']; ?>%</span>
                            </td>
                            <td>
                                <?php if($req['status'] == 'pending'): ?>
                                    <span class="badge badge-pending"><i class="fa-regular fa-clock"></i> Pending Approval</span>
                                <?php elseif($req['status'] == 'accepted'): ?>
                                    <span class="badge badge-success"><i class="fa-solid fa-check"></i> Assigned Adviser</span>
                                <?php else: ?>
                                    <span class="badge badge-danger"><i class="fa-solid fa-xmark"></i> Rejected</span>
                                    <?php if(!empty($req['rejection_reason'])): ?>
                                        <div style="font-size: 0.76rem; color: #DC2626; margin-top: 0.35rem; max-width: 260px; line-height: 1.35; background: #FEF2F2; padding: 0.35rem 0.6rem; border-radius: 6px; border: 1px solid #FECACA;">
                                            <strong>Reason:</strong> <?php echo htmlspecialchars($req['rejection_reason']); ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($req['status'] == 'pending'): ?>
                                    <a href="#" onclick="openCancelAdviserModal('<?php echo addslashes(htmlspecialchars($req['adv_name'])); ?>', <?php echo $req['id']; ?>, 'requests'); return false;" class="btn btn-outline" style="color: #E11D48; border-color: #FECDD3; padding: 0.3rem 0.75rem; font-size: 0.78rem; cursor: pointer;">
                                        <i class="fa-solid fa-xmark"></i> Cancel Request
                                    </a>
                                <?php elseif($req['status'] == 'accepted'): ?>
                                    <span style="color: #059669; font-size: 0.8rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.35rem;">
                                        <i class="fa-solid fa-circle-check"></i> Official Adviser
                                    </span>
                                <?php else: ?>
                                    <a href="?tab=ai" class="btn-primary" style="padding: 0.3rem 0.75rem; font-size: 0.78rem;">
                                        <i class="fa-solid fa-rotate"></i> Request Other
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <?php if($latest_req && $latest_req['status'] == 'pending'):
                $stu_pre_cons = check_pre_acceptance_consultation_ready($conn, (int)$latest_req['student_id'], (int)$latest_req['adviser_id'], (int)($latest_req['group_id'] ?? $group_id), $latest_req);
            ?>
            <div style="margin-top: 1rem; background: #FFFBEB; border: 1.5px solid #F59E0B; border-radius: 10px; padding: 1.1rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.75rem;">
                    <div style="font-weight: 800; font-size: 0.92rem; color: #92400E;">
                        <i class="fa-solid fa-comments"></i> Mandatory Pre-Acceptance Consultation with <?php echo htmlspecialchars($latest_req['adv_name']); ?>
                        <?php if (!empty($stu_pre_cons['reference_no'])): ?>
                            <span style="background: #0F172A; color: #FCD34D; font-family: monospace; padding: 2px 7px; border-radius: 4px; font-size: 0.72rem; margin-left: 0.35rem;"><?php echo htmlspecialchars($stu_pre_cons['reference_no']); ?></span>
                        <?php endif; ?>
                    </div>
                    <span style="background: #FEF3C7; color: #B45309; border: 1px solid #F59E0B; padding: 0.2rem 0.65rem; border-radius: 999px; font-size: 0.74rem; font-weight: 800;">
                        Readiness: <?php echo htmlspecialchars($stu_pre_cons['readiness_text']); ?>
                    </span>
                </div>

                <!-- 3-Step Stepper for Student -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 0.5rem; margin-bottom: 0.85rem;">
                    <div style="padding: 0.45rem 0.65rem; border-radius: 6px; background: <?php echo $stu_pre_cons['adviser_asked_questions'] ? '#D1FAE5' : '#FEF3C7'; ?>; border: 1px solid <?php echo $stu_pre_cons['adviser_asked_questions'] ? '#10B981' : '#F59E0B'; ?>; color: <?php echo $stu_pre_cons['adviser_asked_questions'] ? '#065F46' : '#92400E'; ?>; font-weight: 700; font-size: 0.74rem;">
                        <i class="fa-solid <?php echo $stu_pre_cons['adviser_asked_questions'] ? 'fa-circle-check' : 'fa-hourglass-half'; ?>"></i>
                        Step 1: Adviser Asks Consultation Questions <?php echo $stu_pre_cons['adviser_asked_questions'] ? '(Received)' : '(Awaiting Adviser)'; ?>
                    </div>
                    <div style="padding: 0.45rem 0.65rem; border-radius: 6px; background: <?php echo $stu_pre_cons['student_answered_questions'] ? '#D1FAE5' : ($stu_pre_cons['adviser_asked_questions'] ? '#FFEDD5' : '#F1F5F9'); ?>; border: 1px solid <?php echo $stu_pre_cons['student_answered_questions'] ? '#10B981' : ($stu_pre_cons['adviser_asked_questions'] ? '#F97316' : '#CBD5E1'); ?>; color: <?php echo $stu_pre_cons['student_answered_questions'] ? '#065F46' : ($stu_pre_cons['adviser_asked_questions'] ? '#9A3412' : '#64748B'); ?>; font-weight: 700; font-size: 0.74rem;">
                        <i class="fa-solid <?php echo $stu_pre_cons['student_answered_questions'] ? 'fa-circle-check' : 'fa-reply'; ?>"></i>
                        Step 2: Answer Adviser's Questions <?php echo $stu_pre_cons['student_answered_questions'] ? '(Submitted)' : ($stu_pre_cons['adviser_asked_questions'] ? '(Action Required Now!)' : '(Pending Step 1)'); ?>
                    </div>
                    <div style="padding: 0.45rem 0.65rem; border-radius: 6px; background: #F1F5F9; border: 1px solid #CBD5E1; color: #475569; font-weight: 700; font-size: 0.74rem;">
                        <i class="fa-solid fa-user-check"></i>
                        Step 3: Adviser Reviews Answer &amp; Accepts
                    </div>
                </div>

                <?php if (!$stu_pre_cons['adviser_asked_questions']): ?>
                    <div style="font-size: 0.84rem; color: #92400E; line-height: 1.45;">
                        Before accepting your request, <strong><?php echo htmlspecialchars($latest_req['adv_name']); ?></strong> will review your research idea and send you consultation follow-up questions here and in the <a href="?tab=consultations" style="color: #047857; font-weight: 800; text-decoration: underline;">Adviser Consultations</a> tab.
                    </div>
                <?php else: ?>
                    <div style="background: #FFFFFF; border: 1.5px solid #F59E0B; border-radius: 8px; padding: 0.85rem; margin-bottom: 0.75rem;">
                        <div style="margin-bottom: 0.5rem;">
                            <strong style="color: #B45309; font-size: 0.82rem; display: block; margin-bottom: 0.2rem;">
                                <i class="fa-solid fa-circle-question"></i> Consultation Questions from <?php echo htmlspecialchars($latest_req['adv_name']); ?>:
                            </strong>
                            <div style="color: #0F172A; font-size: 0.86rem; white-space: pre-line; font-weight: 600;"><?php echo htmlspecialchars($stu_pre_cons['questions_text']); ?></div>
                        </div>
                        <?php if (!empty($stu_pre_cons['comments_text'])): ?>
                            <div style="border-top: 1px dashed #FDE68A; padding-top: 0.45rem; font-size: 0.82rem; color: #334155;">
                                <strong>Adviser Preliminary Comments:</strong><br>
                                <?php echo nl2br(htmlspecialchars($stu_pre_cons['comments_text'])); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($stu_pre_cons['feedback_text'])): ?>
                        <div style="background: #ECFDF5; border: 1px solid #6EE7B7; border-radius: 8px; padding: 0.75rem; font-size: 0.84rem; color: #064E3B; margin-bottom: 0.75rem;">
                            <strong><i class="fa-solid fa-check-circle"></i> Adviser Feedback on Your Response:</strong><br>
                            <?php echo nl2br(htmlspecialchars($stu_pre_cons['feedback_text'])); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="action.php" style="display: flex; flex-direction: column; gap: 0.6rem; background: #FFFFFF; padding: 0.85rem; border-radius: 8px; border: 1px solid #CBD5E1;">
                        <input type="hidden" name="req_id" value="<?php echo (int)$latest_req['id']; ?>">
                        <div>
                            <label style="font-size: 0.8rem; font-weight: 800; color: #0F172A; display: block; margin-bottom: 0.25rem;">
                                <i class="fa-solid fa-reply" style="color: #059669;"></i> Your Answer to the Adviser's Consultation Questions *
                            </label>
                            <textarea name="student_followup_response" rows="3" required class="form-control" placeholder="Type your answers to the adviser's consultation questions here so the adviser can review your readiness and accept your request..."><?php echo htmlspecialchars($stu_pre_cons['student_answer_text']); ?></textarea>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                            <button type="submit" name="student_reply_match_request" class="btn-primary" style="background: #047857; padding: 0.48rem 1.1rem; font-size: 0.82rem; font-weight: 800;">
                                <i class="fa-solid fa-paper-plane"></i> <?php echo $stu_pre_cons['student_answered_questions'] ? 'Update My Consultation Answer' : 'Submit Answer for Adviser Review & Acceptance'; ?>
                            </button>
                            <a href="export_consultation_form.php?student_id=<?php echo (int)$uid; ?>" target="_blank" style="font-size: 0.76rem; font-weight: 700; color: #1D4ED8; text-decoration: none;">
                                <i class="fa-solid fa-print"></i> View Printable Consultation Form
                            </a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
            <?php elseif($latest_req && $latest_req['status'] == 'accepted'): ?>
            <div style="margin-top: 1rem; background: #ECFDF5; border: 1px solid #A7F3D0; border-radius: 8px; padding: 0.85rem 1rem; display: flex; align-items: flex-start; gap: 0.75rem;">
                <i class="fa-solid fa-circle-check" style="color: #059669; font-size: 1.15rem; margin-top: 0.1rem;"></i>
                <div style="font-size: 0.85rem; color: #065F46; line-height: 1.45;">
                    <div style="font-weight: 700; margin-bottom: 0.15rem;">Adviser Confirmed</div>
                    <strong><?php echo htmlspecialchars($latest_req['adv_name']); ?></strong> has accepted your request and is now your official capstone adviser.
                </div>
            </div>
            <?php elseif($latest_req && $latest_req['status'] == 'rejected'): ?>
            <div style="margin-top: 1rem; background: #FEF2F2; border: 1px solid #FECACA; border-radius: 8px; padding: 1rem 1.15rem; display: flex; align-items: flex-start; gap: 0.85rem;">
                <i class="fa-solid fa-triangle-exclamation" style="color: #DC2626; font-size: 1.25rem; margin-top: 0.15rem;"></i>
                <div style="font-size: 0.88rem; color: #991B1B; line-height: 1.5; flex: 1;">
                    <div style="font-weight: 700; font-size: 0.95rem; margin-bottom: 0.25rem;">Request Declined by Adviser</div>
                    Your advisee request was declined by <strong><?php echo htmlspecialchars($latest_req['adv_name']); ?></strong>.
                    <?php if(!empty($latest_req['rejection_reason'])): ?>
                        <div style="margin-top: 0.6rem; padding: 0.7rem 0.85rem; background: #FFFFFF; border: 1px solid #FCA5A5; border-radius: 6px; color: #7F1D1D;">
                            <div style="font-weight: 700; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.25rem; color: #991B1B;">
                                <i class="fa-solid fa-comment-dots" style="margin-right: 0.3rem;"></i> Adviser's Reason / Feedback:
                            </div>
                            <?php echo nl2br(htmlspecialchars($latest_req['rejection_reason'])); ?>
                        </div>
                    <?php endif; ?>
                    <p style="margin: 0.6rem 0 0 0; font-size: 0.82rem; color: #7F1D1D;">You can now review the feedback and select another adviser from the AI Recommendations.</p>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
