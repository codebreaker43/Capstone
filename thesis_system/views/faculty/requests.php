<div class="card">
    <div class="card-header">
        <div class="card-header-left">
            <i class="fa-solid fa-inbox"></i>
            <h2>Advisee Requests Queue</h2>
        </div>
        <span class="badge badge-info"><i class="fa-solid fa-list-check"></i> <?php echo $req_res->num_rows; ?> Total</span>
    </div>
    <div class="card-body">
        <?php if($req_res->num_rows == 0): ?>
            <div style="text-align: center; color: var(--text-muted); padding: 3rem 1rem;">
                <i class="fa-solid fa-envelope-open fa-3x" style="color: #CBD5E1; margin-bottom: 1rem; display: block;"></i>
                <h3 style="color: #475569; font-size: 1.1rem; margin-bottom: 0.35rem;">No Pending Advisee Requests</h3>
                <p style="color: #94A3B8; font-size: 0.88rem; margin: 0;">When students select you as their prospective capstone adviser via the AI Matcher, their requests will appear here.</p>
            </div>
        <?php endif; ?>
        
        <?php while($req = $req_res->fetch_assoc()): 
            $req_group = null;
            $req_members = [];
            if (!empty($req['group_id'])) {
                $rgid = (int)$req['group_id'];
                $rg_res = $conn->query("SELECT * FROM capstone_group WHERE id=$rgid");
                if ($rg_res && $rg_res->num_rows > 0) {
                    $req_group = $rg_res->fetch_assoc();
                    $req_members = get_group_members_roster($conn, $rgid);
                }
            }
        ?>
        <div style="border: 1px solid #E2E8F0; border-radius: 12px; padding: 1.25rem 1.5rem; margin-bottom: 1.25rem; box-shadow: var(--shadow-sm); background: #FFFFFF; transition: box-shadow 0.2s ease;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; border-bottom: 1px solid #F1F5F9; padding-bottom: 0.85rem; flex-wrap: wrap; gap: 0.75rem;">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--primary-blue); color: white; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1rem;">
                        <?php echo strtoupper(substr($req['stu_name'], 0, 1)); ?>
                    </div>
                    <div>
                        <h3 style="font-size: 1.05rem; color: #0F172A; margin: 0 0 0.15rem 0; font-weight: 700;">
                            <?php echo htmlspecialchars($req['stu_name']); ?>
                        </h3>
                        <p style="color: #64748B; font-size: 0.82rem; margin: 0;">
                            <i class="fa-solid fa-envelope" style="margin-right: 0.3rem;"></i><?php echo htmlspecialchars($req['email']); ?>
                        </p>
                    </div>
                </div>
                
                <div style="display: flex; align-items: center; gap: 0.65rem;">
                    <?php if($req_group): ?>
                        <span class="badge <?php echo count($req_members) >= 3 ? 'badge-success' : 'badge-warning'; ?>" style="font-size: 0.8rem;">
                            <i class="fa-solid fa-users"></i> <?php echo htmlspecialchars($req_group['group_name']); ?> (<?php echo count($req_members); ?>/3)
                        </span>
                    <?php endif; ?>
                    <div style="background: #ECFDF5; color: #047857; padding: 0.35rem 0.85rem; border-radius: 999px; font-weight: 800; font-size: 0.85rem; border: 1px solid #A7F3D0; display: inline-flex; align-items: center; gap: 0.35rem;">
                        <i class="fa-solid fa-wand-magic-sparkles"></i> <?php echo $req['match_score']; ?>% AI Match
                    </div>
                    <?php if($req['status'] == 'pending'): ?>
                        <span class="badge badge-pending"><i class="fa-regular fa-clock"></i> Pending Review</span>
                    <?php elseif($req['status'] == 'accepted'): ?>
                        <span class="badge badge-success"><i class="fa-solid fa-check"></i> Accepted</span>
                    <?php else: ?>
                        <span class="badge badge-danger"><i class="fa-solid fa-xmark"></i> Rejected</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Group Members Banner (3 Students) -->
            <?php if($req_group && count($req_members) > 0): ?>
                <div style="background: #F0FDF4; border: 1px solid #DCFCE7; border-radius: 8px; padding: 0.75rem 1rem; margin-bottom: 1rem;">
                    <div style="font-size: 0.75rem; color: #166534; font-weight: 700; text-transform: uppercase; margin-bottom: 0.4rem; display: flex; justify-content: space-between;">
                        <span><i class="fa-solid fa-users" style="color: #15803D;"></i> Capstone Group Co-Authors</span>
                        <span>Team: <?php echo htmlspecialchars($req_group['group_name']); ?></span>
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.5rem;">
                        <?php 
                        for($idx = 0; $idx < 3; $idx++): 
                            $m_item = $req_members[$idx] ?? null;
                        ?>
                            <?php if($m_item): ?>
                                <div style="background: white; border: 1px solid #BBF7D0; border-radius: 6px; padding: 0.35rem 0.6rem; font-size: 0.8rem; display: flex; align-items: center; justify-content: space-between;">
                                    <span style="color: #0F172A; font-weight: 600;">
                                        <i class="fa-solid <?php echo $m_item['is_leader'] ? 'fa-crown' : 'fa-user'; ?>" style="color: <?php echo $m_item['is_leader'] ? '#D97706' : '#047857'; ?>; font-size: 0.72rem; margin-right: 0.25rem;"></i>
                                        <?php echo htmlspecialchars($m_item['name']); ?>
                                    </span>
                                    <span style="font-size: 0.68rem; color: <?php echo $m_item['is_leader'] ? '#B45309' : '#64748B'; ?>; font-weight: 700;">
                                        <?php echo $m_item['is_leader'] ? 'Leader' : 'Member '.($idx+1); ?>
                                    </span>
                                </div>
                            <?php else: ?>
                                <div style="background: #FAFAFA; border: 1px dashed #CBD5E1; border-radius: 6px; padding: 0.35rem 0.6rem; font-size: 0.75rem; color: #94A3B8; font-style: italic;">
                                    <i class="fa-regular fa-circle" style="font-size: 0.65rem;"></i> Vacant Slot <?php echo $idx+1; ?>
                                </div>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <div style="background: #F8FAFC; padding: 1.15rem; border-radius: 10px; margin-bottom: 1.15rem; border: 1px solid #E2E8F0;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.45rem; flex-wrap: wrap; gap: 0.35rem;">
                    <h4 style="font-size: 0.82rem; color: #475569; margin: 0; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">
                        <i class="fa-solid fa-book" style="color: var(--primary-blue); margin-right: 0.3rem;"></i> Proposed Capstone Topic
                    </h4>
                    <?php 
                    $req_tstat = $req['title_status'] ?? 'pending';
                    if ($req_tstat === 'approved'): ?>
                        <span class="badge" style="background: #ECFDF5; color: #047857; border: 1px solid #A7F3D0; font-size: 0.72rem; font-weight: 700; padding: 2px 8px; border-radius: 999px;">
                            <i class="fa-solid fa-circle-check"></i> Title Approved
                            <?php if(!empty($req['title_approved_at'])): ?>
                                <span style="font-size: 0.65rem; font-weight: 500; opacity: 0.85;">(<?php echo date('M d, Y', strtotime($req['title_approved_at'])); ?>)</span>
                            <?php endif; ?>
                        </span>
                    <?php elseif ($req_tstat === 'rejected'): ?>
                        <span class="badge" style="background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; font-size: 0.72rem; font-weight: 700; padding: 2px 8px; border-radius: 999px;">
                            <i class="fa-solid fa-triangle-exclamation"></i> Title Needs Revision
                        </span>
                    <?php else: ?>
                        <span class="badge" style="background: #FFFBEB; color: #B45309; border: 1px solid #FDE68A; font-size: 0.72rem; font-weight: 700; padding: 2px 8px; border-radius: 999px;">
                            <i class="fa-regular fa-clock"></i> Title Pending Approval
                        </span>
                    <?php endif; ?>
                </div>
                <p style="font-size: 0.92rem; color: #0F172A; margin: 0 0 1rem 0; line-height: 1.5; font-weight: 600;">
                    <?php echo nl2br(htmlspecialchars($req['thesis_topic'])); ?>
                </p>
                
                <h4 style="font-size: 0.82rem; color: #475569; margin: 0 0 0.4rem 0; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">
                    <i class="fa-solid fa-microchip" style="color: var(--primary-blue); margin-right: 0.3rem;"></i> Student Skills & Keywords
                </h4>
                <div>
                    <?php 
                    $skills = explode(',', $req['skills']);
                    foreach($skills as $skill) {
                        $s = trim($skill);
                        if(!empty($s)) {
                            echo '<span style="background: #FFFFFF; color: #334155; padding: 0.2rem 0.6rem; border-radius: 6px; font-size: 0.78rem; font-weight: 600; border: 1px solid #CBD5E1; margin-right: 0.4rem; display: inline-block; margin-bottom: 0.3rem;">'.htmlspecialchars($s).'</span>';
                        }
                    }
                    ?>
                </div>

                <?php if(!empty($req['abstract_file']) && file_exists($req['abstract_file'])): ?>
                    <div style="margin-top: 0.85rem; padding: 0.65rem 0.85rem; background: #FEF2F2; border: 1px solid #FECACA; border-radius: 8px; display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; flex-wrap: wrap;">
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fa-solid fa-file-pdf" style="color: #DC2626; font-size: 1.35rem;"></i>
                            <div>
                                <div style="font-size: 0.72rem; font-weight: 700; color: #991B1B; text-transform: uppercase;">Research Concept Attached</div>
                                <div style="font-size: 0.82rem; font-weight: 600; color: #0F172A;"><?php echo htmlspecialchars($req['abstract_name'] ?? 'Concept.pdf'); ?></div>
                            </div>
                        </div>
                        <a href="<?php echo htmlspecialchars($req['abstract_file']); ?>" target="_blank" class="btn" style="background: white; color: #DC2626; border: 1px solid #FECACA; font-size: 0.78rem; font-weight: 700; padding: 0.35rem 0.75rem; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem;">
                            <i class="fa-solid fa-arrow-up-right-from-square"></i> Open Concept PDF
                        </a>
                    </div>
                <?php endif; ?>

                <?php if($req['status'] == 'rejected' && !empty($req['rejection_reason'])): ?>
                    <div style="margin-top: 1rem; padding: 0.8rem 1rem; background: #FEF2F2; border: 1px solid #FECACA; border-radius: 8px; font-size: 0.85rem; color: #991B1B;">
                        <div style="font-weight: 700; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.25rem; display: flex; align-items: center; gap: 0.35rem;">
                            <i class="fa-solid fa-circle-exclamation" style="color: #DC2626;"></i> Rejection Reason Provided:
                        </div>
                        <?php echo nl2br(htmlspecialchars($req['rejection_reason'])); ?>
                    </div>
                <?php endif; ?>

                <!-- Pre-Acceptance Consultation Q&A & Readiness Stepper -->
                <?php
                $pre_cons = check_pre_acceptance_consultation_ready($conn, (int)$req['student_id'], (int)$uid, (int)($req['group_id'] ?? 0), $req);
                ?>
                <div style="margin-top: 1rem; padding: 1rem; background: #FFFBEB; border: 1.5px solid #F59E0B; border-radius: 10px; font-size: 0.82rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.65rem; flex-wrap: wrap; gap: 0.5rem;">
                        <strong style="color: #92400E; text-transform: uppercase; font-size: 0.78rem; display: flex; align-items: center; gap: 0.4rem;">
                            <i class="fa-solid fa-clipboard-question"></i> Mandatory Pre-Acceptance Consultation &amp; Questioning Process
                            <?php if (!empty($pre_cons['reference_no'])): ?>
                                <span style="background: #0F172A; color: #FCD34D; font-family: monospace; padding: 1px 6px; border-radius: 4px; font-size: 0.7rem;"><?php echo htmlspecialchars($pre_cons['reference_no']); ?></span>
                            <?php endif; ?>
                        </strong>
                        <span style="background: #FEF3C7; color: #B45309; border: 1px solid #F59E0B; padding: 2px 9px; border-radius: 999px; font-size: 0.72rem; font-weight: 800;">
                            Readiness: <?php echo htmlspecialchars($pre_cons['readiness_text']); ?>
                        </span>
                    </div>

                    <!-- 3-Step Visual Stepper -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 0.5rem; margin-bottom: 0.75rem;">
                        <div style="padding: 0.45rem 0.65rem; border-radius: 6px; background: <?php echo $pre_cons['adviser_asked_questions'] ? '#D1FAE5' : '#FEF3C7'; ?>; border: 1px solid <?php echo $pre_cons['adviser_asked_questions'] ? '#10B981' : '#F59E0B'; ?>; color: <?php echo $pre_cons['adviser_asked_questions'] ? '#065F46' : '#92400E'; ?>; font-weight: 700; font-size: 0.74rem;">
                            <i class="fa-solid <?php echo $pre_cons['adviser_asked_questions'] ? 'fa-circle-check' : 'fa-1'; ?>"></i>
                            Step 1: Adviser Asks Questions <?php echo $pre_cons['adviser_asked_questions'] ? '(Sent)' : '(Required Now)'; ?>
                        </div>
                        <div style="padding: 0.45rem 0.65rem; border-radius: 6px; background: <?php echo $pre_cons['student_answered_questions'] ? '#D1FAE5' : ($pre_cons['adviser_asked_questions'] ? '#FEF3C7' : '#F1F5F9'); ?>; border: 1px solid <?php echo $pre_cons['student_answered_questions'] ? '#10B981' : ($pre_cons['adviser_asked_questions'] ? '#F59E0B' : '#CBD5E1'); ?>; color: <?php echo $pre_cons['student_answered_questions'] ? '#065F46' : ($pre_cons['adviser_asked_questions'] ? '#92400E' : '#64748B'); ?>; font-weight: 700; font-size: 0.74rem;">
                            <i class="fa-solid <?php echo $pre_cons['student_answered_questions'] ? 'fa-circle-check' : 'fa-2'; ?>"></i>
                            Step 2: Student Answers Questions <?php echo $pre_cons['student_answered_questions'] ? '(Answered)' : ($pre_cons['adviser_asked_questions'] ? '(Waiting for Student)' : '(Pending Step 1)'); ?>
                        </div>
                        <div style="padding: 0.45rem 0.65rem; border-radius: 6px; background: <?php echo $pre_cons['can_accept_student'] ? '#D1FAE5' : '#F1F5F9'; ?>; border: 1px solid <?php echo $pre_cons['can_accept_student'] ? '#10B981' : '#CBD5E1'; ?>; color: <?php echo $pre_cons['can_accept_student'] ? '#065F46' : '#64748B'; ?>; font-weight: 700; font-size: 0.74rem;">
                            <i class="fa-solid <?php echo $pre_cons['can_accept_student'] ? 'fa-unlock' : 'fa-lock'; ?>"></i>
                            Step 3: Review Answers &amp; Accept <?php echo $pre_cons['can_accept_student'] ? '(Unlocked!)' : '(Locked)'; ?>
                        </div>
                    </div>

                    <?php if (!empty($pre_cons['questions_text'])): ?>
                        <div style="margin-bottom: 0.45rem; background: #FFFFFF; padding: 0.65rem; border-radius: 6px; border: 1px solid #FCD34D; color: #1E293B;">
                            <strong style="color: #B45309;"><i class="fa-solid fa-circle-question"></i> Your Consultation Questions to Student:</strong><br>
                            <?php echo nl2br(htmlspecialchars($pre_cons['questions_text'])); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($pre_cons['comments_text'])): ?>
                        <div style="margin-bottom: 0.45rem; background: #FFFFFF; padding: 0.65rem; border-radius: 6px; border: 1px solid #E2E8F0; color: #334155;">
                            <strong><i class="fa-solid fa-comment-dots"></i> Your Preliminary Comments:</strong><br>
                            <?php echo nl2br(htmlspecialchars($pre_cons['comments_text'])); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($pre_cons['student_answer_text'])): ?>
                        <div style="margin-top: 0.5rem; padding: 0.7rem; background: #EEF2FF; border: 1.5px solid #6366F1; border-radius: 6px; color: #1E293B;">
                            <strong style="color: #3730A3; display: block; margin-bottom: 0.2rem;">
                                <i class="fa-solid fa-reply"></i> Student's Consultation Answer <?php echo !empty($pre_cons['student_answered_at']) ? '(' . date('M d, Y h:i A', strtotime($pre_cons['student_answered_at'])) . ')' : ''; ?>:
                            </strong>
                            <?php echo nl2br(htmlspecialchars($pre_cons['student_answer_text'])); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($pre_cons['feedback_text'])): ?>
                        <div style="margin-top: 0.5rem; padding: 0.65rem; background: #ECFDF5; border: 1px solid #6EE7B7; border-radius: 6px; color: #064E3B;">
                            <strong style="color: #047857;"><i class="fa-solid fa-check-circle"></i> Your Review Feedback on Student's Response:</strong><br>
                            <?php echo nl2br(htmlspecialchars($pre_cons['feedback_text'])); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if($req['status'] == 'pending'): ?>
                <!-- Mandatory Pre-Acceptance Consultation Form (Open by default) -->
                <div style="margin-bottom: 0.9rem; background: #F8FAFC; border: 1.5px solid <?php echo $pre_cons['can_accept_student'] ? '#10B981' : '#F59E0B'; ?>; border-radius: 10px; padding: 1rem;">
                    <div style="font-weight: 800; font-size: 0.85rem; color: #0F172A; margin-bottom: 0.65rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem;">
                        <span>
                            <i class="fa-solid fa-comments" style="color: #059669; margin-right: 0.4rem;"></i>
                            <?php if (!$pre_cons['adviser_asked_questions']): ?>
                                Step 1 (Required Before Accepting): Ask Consultation Questions &amp; Evaluate Student Readiness
                            <?php elseif (!$pre_cons['student_answered_questions']): ?>
                                Step 2: Update Consultation Questions (Waiting for Student to Reply Before You Can Accept)
                            <?php else: ?>
                                Step 3: Review Student's Consultation Answers, Provide Feedback &amp; Accept Request
                            <?php endif; ?>
                        </span>
                        <a href="export_consultation_form.php?student_id=<?php echo (int)$req['student_id']; ?>" target="_blank" style="font-size: 0.74rem; color: #047857; text-decoration: none; font-weight: 700;">
                            <i class="fa-solid fa-print"></i> Print Consultation Form Copy
                        </a>
                    </div>
                    <form method="POST" action="action.php" style="display: flex; flex-direction: column; gap: 0.65rem; font-size: 0.8rem;">
                        <input type="hidden" name="req_id" value="<?php echo (int)$req['id']; ?>">
                        <div>
                            <label style="font-weight: 700; color: #334155; display: block; margin-bottom: 0.25rem;">Evaluate Student's Research Readiness *</label>
                            <select name="readiness_evaluation" class="form-control" style="padding: 0.45rem; font-size: 0.8rem; font-weight: 600;">
                                <?php
                                $r_opts = ['Ready for Advising', 'Moderately Ready - Clarification Needed', 'Needs Scope & Methodology Refinement', 'Not Yet Ready'];
                                $cur_re = $pre_cons['readiness_text'] ?: 'Moderately Ready - Clarification Needed';
                                foreach ($r_opts as $ro):
                                ?>
                                    <option value="<?php echo $ro; ?>" <?php echo $cur_re === $ro ? 'selected' : ''; ?>><?php echo $ro; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label style="font-weight: 700; color: #B45309; display: block; margin-bottom: 0.25rem;">
                                Consultation Questions for Student (Required Before Accepting) *
                            </label>
                            <textarea name="follow_up_questions" rows="2" required class="form-control" placeholder="Ask specific questions about the student's research idea, objectives, problem statement, methodology, or feasibility..." style="font-size: 0.8rem; border-color: #F59E0B;"><?php echo htmlspecialchars($pre_cons['questions_text']); ?></textarea>
                        </div>
                        <div>
                            <label style="font-weight: 700; color: #334155; display: block; margin-bottom: 0.25rem;">Adviser Preliminary Comments / Suggestions</label>
                            <textarea name="adviser_comments" rows="2" class="form-control" placeholder="Provide comments or guidance on their proposed research topic..." style="font-size: 0.8rem;"><?php echo htmlspecialchars($pre_cons['comments_text']); ?></textarea>
                        </div>
                        <?php if ($pre_cons['student_answered_questions']): ?>
                        <div style="background: #EEF2FF; border: 1px solid #A5B4FC; border-radius: 8px; padding: 0.75rem;">
                            <label style="font-weight: 800; color: #3730A3; display: block; margin-bottom: 0.25rem;">
                                <i class="fa-solid fa-check-double"></i> Your Feedback on the Student's Consultation Response *
                            </label>
                            <textarea name="adviser_feedback_on_response" rows="2" required class="form-control" placeholder="Evaluate how well the student answered your consultation questions before accepting..." style="font-size: 0.8rem; border-color: #6366F1; background: #FFFFFF;"><?php echo htmlspecialchars($pre_cons['feedback_text']); ?></textarea>
                            <div style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; margin-top: 0.6rem;">
                                <label style="display: inline-flex; align-items: center; gap: 0.4rem; font-weight: 800; color: #065F46; cursor: pointer;">
                                    <input type="checkbox" name="accept_after_review" value="1" checked>
                                    <span>Accept Student as My Advisee Now</span>
                                </label>
                                <label style="display: inline-flex; align-items: center; gap: 0.4rem; font-weight: 700; color: #0F172A; cursor: pointer;">
                                    <input type="checkbox" name="approve_title_after_review" value="1" checked>
                                    <span>Also Officially Approve Proposed Capstone Title</span>
                                </label>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div>
                            <button type="submit" name="adviser_followup_match_request" class="btn-primary" style="background: <?php echo $pre_cons['student_answered_questions'] ? '#047857' : '#D97706'; ?>; border-color: <?php echo $pre_cons['student_answered_questions'] ? '#065F46' : '#B45309'; ?>; padding: 0.5rem 1.15rem; font-size: 0.82rem; font-weight: 800;">
                                <i class="fa-solid <?php echo $pre_cons['student_answered_questions'] ? 'fa-user-check' : 'fa-paper-plane'; ?>"></i>
                                <?php echo $pre_cons['student_answered_questions'] ? 'Save Feedback & Accept Student Request' : 'Send Consultation Questions & Readiness Evaluation to Student'; ?>
                            </button>
                        </div>
                    </form>
                </div>

                <div style="display: flex; gap: 0.5rem; align-items: center; justify-content: space-between; border-top: 1px solid #F1F5F9; padding-top: 0.85rem; flex-wrap: wrap;">
                    <div>
                        <?php if (!$pre_cons['can_accept_student']): ?>
                            <span style="display: inline-flex; align-items: center; gap: 0.4rem; background: #FEF3C7; color: #92400E; border: 1px solid #F59E0B; padding: 0.35rem 0.75rem; border-radius: 8px; font-size: 0.76rem; font-weight: 800;">
                                <i class="fa-solid fa-lock"></i>
                                <?php echo !$pre_cons['adviser_asked_questions']
                                    ? 'Accept Locked: Ask consultation questions above first before accepting the student.'
                                    : 'Accept Locked: Waiting for the student to answer your consultation questions.'; ?>
                            </span>
                        <?php else: ?>
                            <span style="display: inline-flex; align-items: center; gap: 0.4rem; background: #D1FAE5; color: #065F46; border: 1px solid #10B981; padding: 0.35rem 0.75rem; border-radius: 8px; font-size: 0.76rem; font-weight: 800;">
                                <i class="fa-solid fa-circle-check"></i>
                                Student Answered Consultation Questions — Ready to Accept!
                            </span>
                        <?php endif; ?>
                    </div>
                    <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                        <a href="?tab=consultations&student_id=<?php echo (int)$req['student_id']; ?>" class="btn" style="text-decoration: none; padding: 0.45rem 0.95rem; background: #EFF6FF; color: #1D4ED8; border: 1px solid #93C5FD; font-weight: 700; font-size: 0.82rem; display: inline-flex; align-items: center; gap: 0.35rem;">
                            <i class="fa-solid fa-clipboard-check"></i> Open Consultation Center
                        </a>
                        <?php if ($pre_cons['can_accept_student']): ?>
                            <a href="action.php?respond=1&req_id=<?php echo $req['id']; ?>&action=accept&approve_title=1" class="btn-primary" style="text-decoration: none; padding: 0.45rem 1.15rem; background: #047857; display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.84rem;">
                                <i class="fa-solid fa-check-double"></i> Accept &amp; Approve Title
                            </a>
                            <a href="action.php?respond=1&req_id=<?php echo $req['id']; ?>&action=accept" class="btn" style="text-decoration: none; padding: 0.45rem 0.95rem; background: #F1F5F9; color: #334155; border: 1px solid #CBD5E1; font-weight: 600; font-size: 0.84rem; display: inline-flex; align-items: center; gap: 0.35rem;">
                                <i class="fa-solid fa-check"></i> Accept Only
                            </a>
                        <?php else: ?>
                            <button type="button" disabled title="Complete Consultation Q&A with the student first" style="padding: 0.45rem 1rem; background: #E2E8F0; color: #94A3B8; border: 1px solid #CBD5E1; border-radius: 6px; font-weight: 700; font-size: 0.82rem; cursor: not-allowed; display: inline-flex; align-items: center; gap: 0.35rem;">
                                <i class="fa-solid fa-lock"></i> Accept (Requires Consultation Q&amp;A)
                            </button>
                        <?php endif; ?>
                        <button type="button" class="btn btn-outline" style="color: #EF4444; border-color: #FECACA; padding: 0.45rem 0.85rem; cursor: pointer; font-size: 0.84rem;" onclick="openRejectModal(<?php echo $req['id']; ?>, '<?php echo addslashes(htmlspecialchars($req['stu_name'])); ?>', '<?php echo addslashes(htmlspecialchars(preg_replace('/\s+/', ' ', $req['thesis_topic']))); ?>')">
                            <i class="fa-solid fa-xmark"></i> Decline
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <div style="display: flex; gap: 0.5rem; align-items: center; justify-content: flex-end; border-top: 1px solid #F1F5F9; padding-top: 0.75rem;">
                    <a href="?tab=consultations&student_id=<?php echo (int)$req['student_id']; ?>" class="btn" style="text-decoration: none; padding: 0.4rem 0.9rem; background: #ECFDF5; color: #047857; border: 1px solid #6EE7B7; font-weight: 700; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 0.35rem;">
                        <i class="fa-solid fa-clipboard-check"></i> Manage Consultations &amp; Oral Defense Clearance
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <?php endwhile; ?>
    </div>
</div>

<!-- Decline Request Modal -->
<div id="rejectRequestModal" class="modal">
    <div class="modal-content" style="max-width: 540px; border-top: 4px solid #EF4444;">
        <span class="close" onclick="closeRejectModal()">&times;</span>
        
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.25rem;">
            <div style="width: 44px; height: 44px; border-radius: 50%; background: #FEE2E2; color: #DC2626; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0;">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <div>
                <h3 style="margin: 0; font-size: 1.15rem; color: #0F172A; font-weight: 700;">Decline Advisee Request</h3>
                <p style="margin: 0.2rem 0 0 0; color: #64748B; font-size: 0.82rem;">Please provide a reason so the student knows why their request was declined.</p>
            </div>
        </div>

        <form action="action.php" method="POST" onsubmit="return validateRejectionForm()">
            <input type="hidden" name="req_id" id="reject_req_id" value="">
            
            <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 0.85rem 1rem; margin-bottom: 1.15rem;">
                <div style="font-size: 0.78rem; font-weight: 700; color: #64748B; text-transform: uppercase; margin-bottom: 0.25rem;">
                    Student & Proposed Topic
                </div>
                <div id="reject_student_name" style="font-weight: 700; color: #0F172A; font-size: 0.95rem; margin-bottom: 0.2rem;"></div>
                <div id="reject_student_topic" style="color: #475569; font-size: 0.84rem; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;"></div>
            </div>

            <div class="form-group" style="margin-bottom: 1rem;">
                <label style="display: block; font-weight: 700; font-size: 0.85rem; color: #334155; margin-bottom: 0.5rem;">
                    Quick Reasons (Click to apply):
                </label>
                <div style="display: flex; flex-wrap: wrap; gap: 0.4rem; margin-bottom: 0.75rem;">
                    <button type="button" class="quick-reason-btn" onclick="setRejectReason('Adviser capacity is already full for this academic semester.')" style="background: #F1F5F9; border: 1px solid #CBD5E1; color: #334155; padding: 0.3rem 0.65rem; border-radius: 6px; font-size: 0.76rem; cursor: pointer; font-weight: 500;">
                        Workload / Capacity Full
                    </button>
                    <button type="button" class="quick-reason-btn" onclick="setRejectReason('The proposed capstone topic is outside my primary area of research specialization.')" style="background: #F1F5F9; border: 1px solid #CBD5E1; color: #334155; padding: 0.3rem 0.65rem; border-radius: 6px; font-size: 0.76rem; cursor: pointer; font-weight: 500;">
                        Outside Research Specialization
                    </button>
                    <button type="button" class="quick-reason-btn" onclick="setRejectReason('The project scope requires substantial revision and clarification before an adviser can be assigned.')" style="background: #F1F5F9; border: 1px solid #CBD5E1; color: #334155; padding: 0.3rem 0.65rem; border-radius: 6px; font-size: 0.76rem; cursor: pointer; font-weight: 500;">
                        Scope Needs Major Revision
                    </button>
                    <button type="button" class="quick-reason-btn" onclick="setRejectReason('Schedule mismatch; unable to accommodate the required weekly consultation hours.')" style="background: #F1F5F9; border: 1px solid #CBD5E1; color: #334155; padding: 0.3rem 0.65rem; border-radius: 6px; font-size: 0.76rem; cursor: pointer; font-weight: 500;">
                        Schedule Conflict
                    </button>
                </div>

                <label for="rejection_reason_text" style="display: block; font-weight: 700; font-size: 0.85rem; color: #334155; margin-bottom: 0.4rem;">
                    Reason for Declining <span style="color: #EF4444;">*</span>
                </label>
                <textarea name="rejection_reason" id="rejection_reason_text" rows="4" required placeholder="Type or choose a specific reason explaining why you are rejecting this request..." style="width: 100%; border: 1px solid #CBD5E1; border-radius: 8px; padding: 0.75rem; font-size: 0.88rem; font-family: inherit; resize: vertical; box-sizing: border-box; line-height: 1.45;"></textarea>
                <div style="font-size: 0.75rem; color: #64748B; margin-top: 0.35rem;">
                    <i class="fa-solid fa-circle-info" style="color: #3B82F6;"></i> This reason will be visible to the student on their dashboard so they can pick another adviser or revise their topic.
                </div>
            </div>

            <div style="display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 1.5rem; border-top: 1px solid #F1F5F9; padding-top: 1rem;">
                <button type="button" class="btn btn-outline" onclick="closeRejectModal()" style="padding: 0.55rem 1.25rem; font-size: 0.85rem;">
                    Cancel
                </button>
                <button type="submit" name="reject_advisee_request" class="btn" style="background: #DC2626; color: white; border: none; padding: 0.55rem 1.25rem; font-size: 0.85rem; font-weight: 600; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 0.4rem;">
                    <i class="fa-solid fa-xmark"></i> Confirm & Decline Request
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openRejectModal(reqId, studentName, topic) {
    document.getElementById('reject_req_id').value = reqId;
    document.getElementById('reject_student_name').innerText = studentName;
    document.getElementById('reject_student_topic').innerText = topic;
    document.getElementById('rejection_reason_text').value = '';
    document.getElementById('rejectRequestModal').style.display = 'block';
}

function closeRejectModal() {
    document.getElementById('rejectRequestModal').style.display = 'none';
}

function setRejectReason(text) {
    document.getElementById('rejection_reason_text').value = text;
    document.getElementById('rejection_reason_text').focus();
}

function validateRejectionForm() {
    var reason = document.getElementById('rejection_reason_text').value.trim();
    if(reason === '') {
        alert('Please provide a reason why you are declining this request.');
        document.getElementById('rejection_reason_text').focus();
        return false;
    }
    return true;
}

window.addEventListener('click', function(event) {
    var modal = document.getElementById('rejectRequestModal');
    if (event.target == modal) {
        closeRejectModal();
    }
});
</script>
