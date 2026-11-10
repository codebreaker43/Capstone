<?php
// STUDENT ADVISER CONSULTATION PROCESS VIEW (CLEAN, SIMPLE & STEP-BY-STEP)

$is_in_group = !empty($my_group) && !empty($group_id);
$cons_summary = get_student_consultation_summary($conn, $uid, $group_id);
$consultations = $cons_summary['records'];
$c_stats = $cons_summary['stats'];

// Fetch active advisers
$advisers_list = [];
$adv_res = $conn->query("SELECT u.id, u.name, u.email, ap.department, ap.expertise, ap.schedule 
                         FROM user u 
                         LEFT JOIN adviser_profile ap ON u.id = ap.user_id 
                         WHERE u.role = 'adviser' AND u.account_status = 'active' 
                         ORDER BY u.name ASC");
if ($adv_res) {
    while ($a = $adv_res->fetch_assoc()) {
        $advisers_list[] = $a;
    }
}

// Determine default selected adviser
$default_adviser_id = (int)($my_adv_id ?? 0);
if ($default_adviser_id <= 0) {
    $grp_w = ($group_id > 0) ? "(m.group_id = $group_id OR m.student_id = $uid)" : "m.student_id = $uid";
    $pend_adv = $conn->query("SELECT m.adviser_id FROM match_request m WHERE $grp_w ORDER BY (m.status='accepted') DESC, m.id DESC LIMIT 1")->fetch_assoc();
    if ($pend_adv) {
        $default_adviser_id = (int)$pend_adv['adviser_id'];
    }
}

$default_topic = !empty($my_group['thesis_topic']) ? $my_group['thesis_topic'] : ($profile['thesis_topic'] ?? '');
?>

<!-- HEADER BANNER -->
<div class="banner" style="background: linear-gradient(135deg, #022C22 0%, #064E3B 60%, #047857 100%); border-radius: 14px; padding: 1.35rem 1.65rem; color: white; margin-bottom: 1.25rem; box-shadow: var(--shadow-sm);">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <span style="background: rgba(245, 158, 11, 0.25); color: #FCD34D; border: 1px solid rgba(245, 158, 11, 0.45); padding: 0.2rem 0.65rem; border-radius: 999px; font-size: 0.72rem; font-weight: 800; text-transform: uppercase;">
                <i class="fa-solid fa-comments"></i> Adviser Consultation Portal
            </span>
            <h1 style="font-size: 1.45rem; font-weight: 800; margin: 0.3rem 0 0.2rem 0; color: #FFFFFF;">
                My Adviser Consultations &amp; Defense Clearance
            </h1>
            <p style="margin: 0; color: #D1FAE5; font-size: 0.84rem;">
                Step-by-Step: <strong>1. Submit Consultation Form</strong> &rarr; <strong>2. Answer Adviser's Questions</strong> &rarr; <strong>3. Adviser Accepts</strong> &rarr; <strong>4. Completed &amp; Approved for Defense</strong>
            </p>
        </div>

        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
            <button type="button" onclick="const el=document.getElementById('newConsultationBox'); el.open=true; el.scrollIntoView({behavior:'smooth'});" class="btn-primary" style="background: #F59E0B; border-color: #D97706; color: #0F172A; font-weight: 800; padding: 0.55rem 1rem; border-radius: 8px; display: inline-flex; align-items: center; gap: 0.4rem;">
                <i class="fa-solid fa-plus-circle"></i> New Consultation Request
            </button>
            <a href="export_consultation_form.php?student_id=<?php echo $uid; ?><?php echo $group_id > 0 ? '&group_id=' . $group_id : ''; ?>" target="_blank" style="background: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.35); color: #FFFFFF; font-weight: 700; font-size: 0.82rem; padding: 0.55rem 0.9rem; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem;">
                <i class="fa-solid fa-print"></i> Print Form
            </a>
            <a href="export_consultation_form.php?student_id=<?php echo $uid; ?><?php echo $group_id > 0 ? '&group_id=' . $group_id : ''; ?>&format=doc" style="background: #10B981; color: #FFFFFF; font-weight: 700; font-size: 0.82rem; padding: 0.55rem 0.9rem; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem;">
                <i class="fa-solid fa-file-word"></i> Word (.doc)
            </a>
        </div>
    </div>
</div>

<!-- COMPACT STATUS BAR -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 0.85rem; margin-bottom: 1.25rem;">
    <div style="background: <?php echo $cons_summary['is_process_completed'] ? '#ECFDF5' : '#FFFBEB'; ?>; border: 1.5px solid <?php echo $cons_summary['is_process_completed'] ? '#6EE7B7' : '#FCD34D'; ?>; border-radius: 12px; padding: 0.9rem 1.1rem;">
        <div style="font-size: 0.72rem; font-weight: 800; text-transform: uppercase; color: <?php echo $cons_summary['is_process_completed'] ? '#065F46' : '#92400E'; ?>;">Consultation Process Status</div>
        <div style="font-size: 1.05rem; font-weight: 800; color: #0F172A; margin-top: 0.2rem;">
            <?php echo $cons_summary['is_process_completed'] ? '✅ Completed & Verified' : ($c_stats['total'] > 0 ? '⏳ In Progress (' . $c_stats['completed'] . ' Done)' : 'Not Started'); ?>
        </div>
    </div>

    <div style="background: <?php echo $cons_summary['oral_defense_approved'] ? '#ECFDF5' : '#F8FAFC'; ?>; border: 1.5px solid <?php echo $cons_summary['oral_defense_approved'] ? '#6EE7B7' : '#E2E8F0'; ?>; border-radius: 12px; padding: 0.9rem 1.1rem;">
        <div style="font-size: 0.72rem; font-weight: 800; text-transform: uppercase; color: <?php echo $cons_summary['oral_defense_approved'] ? '#065F46' : '#64748B'; ?>;">Oral Defense Endorsement</div>
        <div style="font-size: 1.05rem; font-weight: 800; color: <?php echo $cons_summary['oral_defense_approved'] ? '#047857' : '#334155'; ?>; margin-top: 0.2rem;">
            <?php echo $cons_summary['oral_defense_approved'] ? '🎓 Approved (' . htmlspecialchars($cons_summary['oral_defense_stage_approved']) . ')' : 'Pending Adviser Approval'; ?>
        </div>
    </div>

    <div style="background: <?php echo $c_stats['follow_up_required'] > 0 ? '#FEF2F2' : '#F8FAFC'; ?>; border: 1.5px solid <?php echo $c_stats['follow_up_required'] > 0 ? '#FCA5A5' : '#E2E8F0'; ?>; border-radius: 12px; padding: 0.9rem 1.1rem;">
        <div style="font-size: 0.72rem; font-weight: 800; text-transform: uppercase; color: <?php echo $c_stats['follow_up_required'] > 0 ? '#DC2626' : '#64748B'; ?>;">Adviser Questions</div>
        <div style="font-size: 1.05rem; font-weight: 800; color: <?php echo $c_stats['follow_up_required'] > 0 ? '#DC2626' : '#0F172A'; ?>; margin-top: 0.2rem;">
            <?php echo $c_stats['follow_up_required'] > 0 ? '❓ ' . $c_stats['follow_up_required'] . ' Needs Your Answer' : 'All Questions Answered'; ?>
        </div>
    </div>
</div>

<!-- COLLAPSIBLE NEW CONSULTATION FORM -->
<details id="newConsultationBox" <?php echo empty($consultations) ? 'open' : ''; ?> style="background: #FFFFFF; border: 1.5px solid #CBD5E1; border-top: 4px solid #047857; border-radius: 12px; margin-bottom: 1.35rem; box-shadow: var(--shadow-sm);">
    <summary style="padding: 1rem 1.25rem; cursor: pointer; font-weight: 800; font-size: 0.98rem; color: #0F172A; display: flex; justify-content: space-between; align-items: center; list-style: none;">
        <span><i class="fa-solid fa-file-pen" style="color: #047857; margin-right: 0.45rem;"></i> Submit a New Consultation Request Form</span>
        <span style="font-size: 0.76rem; font-weight: 700; color: #047857; background: #ECFDF5; border: 1px solid #A7F3D0; padding: 0.22rem 0.65rem; border-radius: 999px;">
            Click to Open / Close Form
        </span>
    </summary>
    <div style="padding: 0 1.25rem 1.25rem 1.25rem; border-top: 1px solid #F1F5F9;">
        <form method="POST" action="action.php" enctype="multipart/form-data" style="margin-top: 1rem;">
            <input type="hidden" name="submit_consultation_request" value="1">
            <input type="hidden" name="return_tab" value="consultations">

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 0.85rem; margin-bottom: 0.85rem;">
                <div>
                    <label style="font-size: 0.8rem; font-weight: 700; color: #0F172A; display: block; margin-bottom: 0.3rem;">Research Adviser <span style="color:#DC2626;">*</span></label>
                    <select name="adviser_id" required class="form-control" style="padding: 0.55rem; font-size: 0.84rem; font-weight: 600;">
                        <option value="">-- Select Research Adviser --</option>
                        <?php foreach ($advisers_list as $adv): ?>
                            <option value="<?php echo (int)$adv['id']; ?>" <?php echo ($default_adviser_id === (int)$adv['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($adv['name'] . (!empty($adv['department']) ? ' — ' . $adv['department'] : '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label style="font-size: 0.8rem; font-weight: 700; color: #0F172A; display: block; margin-bottom: 0.3rem;">Consultation Purpose <span style="color:#DC2626;">*</span></label>
                    <select name="consultation_stage" required class="form-control" style="padding: 0.55rem; font-size: 0.84rem; font-weight: 600;">
                        <option value="Initial Research Idea / Concept Consultation">1. Initial Research Idea / Concept Consultation</option>
                        <option value="Title Proposal & Scope Refinement">2. Title Proposal &amp; Scope Refinement</option>
                        <option value="Chapters 1-3 Proposal Manuscript Consultation">3. Chapters 1–3 Proposal Manuscript Consultation</option>
                        <option value="Pre-Proposal Oral Defense Readiness Consultation">4. Pre-Proposal Oral Defense Readiness</option>
                        <option value="System Architecture & Prototype Progress Consultation">5. System Prototype Progress</option>
                        <option value="Final Oral Defense Readiness & Endorsement Consultation">6. Final Oral Defense Readiness</option>
                    </select>
                </div>

                <div>
                    <label style="font-size: 0.8rem; font-weight: 700; color: #0F172A; display: block; margin-bottom: 0.3rem;">Preferred Date <span style="color:#DC2626;">*</span></label>
                    <input type="date" name="preferred_date" required value="<?php echo date('Y-m-d', strtotime('+2 days')); ?>" class="form-control" style="padding: 0.52rem; font-size: 0.84rem;">
                </div>

                <div>
                    <label style="font-size: 0.8rem; font-weight: 700; color: #0F172A; display: block; margin-bottom: 0.3rem;">Mode</label>
                    <select name="consultation_mode" class="form-control" style="padding: 0.55rem; font-size: 0.84rem;">
                        <option value="In-Person">In-Person (Faculty Room)</option>
                        <option value="Online / Virtual">Online (Google Meet)</option>
                    </select>
                    <input type="hidden" name="preferred_time" value="Flexible">
                    <input type="hidden" name="venue_or_link" value="CEIT Faculty Room">
                </div>
            </div>

            <div style="margin-bottom: 0.85rem;">
                <label style="font-size: 0.8rem; font-weight: 700; color: #0F172A; display: block; margin-bottom: 0.3rem;">Proposed Research Idea / Capstone Title <span style="color:#DC2626;">*</span></label>
                <input type="text" name="research_title" required value="<?php echo htmlspecialchars($default_topic); ?>" placeholder="Enter your research idea or capstone title..." class="form-control" style="padding: 0.55rem 0.75rem; font-size: 0.86rem; font-weight: 600;">
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 0.85rem; margin-bottom: 0.85rem;">
                <div>
                    <label style="font-size: 0.8rem; font-weight: 700; color: #0F172A; display: block; margin-bottom: 0.3rem;">Research Idea Summary &amp; Objectives <span style="color:#DC2626;">*</span></label>
                    <textarea name="research_idea_summary" rows="3" required placeholder="Briefly explain your research idea, problem, and main objectives..." class="form-control" style="font-size: 0.84rem;"></textarea>
                </div>
                <div>
                    <label style="font-size: 0.8rem; font-weight: 700; color: #0F172A; display: block; margin-bottom: 0.3rem;">Questions / Agenda for Adviser <span style="color:#DC2626;">*</span></label>
                    <textarea name="consultation_agenda" rows="3" required placeholder="What specific parts do you want to consult with your adviser?" class="form-control" style="font-size: 0.84rem;"></textarea>
                </div>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
                <div>
                    <label style="font-size: 0.78rem; font-weight: 700; color: #475569; margin-right: 0.5rem;"><i class="fa-solid fa-paperclip"></i> Attach Draft (Optional):</label>
                    <input type="file" name="consultation_attachment" accept=".pdf,.doc,.docx,.ppt,.pptx,.png,.jpg" style="font-size: 0.78rem;">
                </div>
                <button type="submit" class="btn-primary" style="background: #047857; padding: 0.6rem 1.25rem; font-weight: 800; border-radius: 8px;">
                    <i class="fa-solid fa-paper-plane"></i> Submit Consultation Request
                </button>
            </div>
        </form>
    </div>
</details>

<!-- CONSULTATION CARDS LIST -->
<div style="display: flex; flex-direction: column; gap: 1.15rem;">
    <?php if (empty($consultations)): ?>
        <div class="card" style="border-radius: 12px;">
            <div class="card-body" style="text-align: center; padding: 2.5rem 1rem; color: #64748B;">
                <i class="fa-regular fa-comments" style="font-size: 2.4rem; color: #94A3B8; margin-bottom: 0.5rem;"></i>
                <h3 style="color: #0F172A; font-size: 1rem; margin: 0 0 0.25rem 0;">No Consultation Records Yet</h3>
                <p style="margin: 0; font-size: 0.84rem;">Use the form above to request a consultation with your Research Adviser.</p>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($consultations as $cons):
            $st = $cons['status'];
            $has_asked = !empty(trim($cons['follow_up_questions'] ?? ''));
            $has_replied = !empty(trim($cons['student_response'] ?? ''));
            $is_stu_unread = ($st === 'follow_up_required' && empty($cons['student_seen']));
            $stu_cid = (int)$cons['id'];

            if ($st === 'completed') {
                $step_lbl = 'Step 4/4 • Completed'; $b_col = '#10B981'; $b_bg = '#D1FAE5'; $b_txt = '#065F46';
            } elseif ($st === 'accepted') {
                $step_lbl = 'Step 3/4 • Consultation Accepted'; $b_col = '#0284C7'; $b_bg = '#E0F2FE'; $b_txt = '#0369A1';
            } elseif ($st === 'follow_up_required' && !$has_replied) {
                $step_lbl = 'Step 2/4 • Please Answer Adviser Question'; $b_col = '#EF4444'; $b_bg = '#FEF2F2'; $b_txt = '#DC2626';
            } elseif ($has_replied || $st === 'student_responded') {
                $step_lbl = 'Step 2/4 • Answer Sent (Waiting Adviser Approval)'; $b_col = '#6366F1'; $b_bg = '#E0E7FF'; $b_txt = '#3730A3';
            } else {
                $step_lbl = 'Step 1/4 • Waiting for Adviser Review'; $b_col = '#F59E0B'; $b_bg = '#FEF3C7'; $b_txt = '#92400E';
            }
        ?>
        <div class="card"
             data-unread="<?php echo $is_stu_unread ? '1' : '0'; ?>"
             onclick="markStudentConsultationSeen(<?php echo $stu_cid; ?>, this)"
             style="margin-bottom: 0; border-radius: 14px; border: 1.5px solid <?php echo $is_stu_unread ? '#F59E0B' : '#E2E8F0'; ?>; border-left: 6px solid <?php echo $b_col; ?>; cursor: <?php echo $is_stu_unread ? 'pointer' : 'default'; ?>;">
            <!-- Header -->
            <div style="padding: 0.9rem 1.25rem; background: #F8FAFC; border-bottom: 1px solid #E2E8F0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
                <div>
                    <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 0.25rem;">
                        <?php if ($is_stu_unread): ?>
                            <span id="stu-cons-new-badge-<?php echo $stu_cid; ?>" style="background: #F59E0B; color: #0F172A; padding: 0.18rem 0.6rem; border-radius: 999px; font-size: 0.71rem; font-weight: 900;">
                                <i class="fa-solid fa-bell"></i> NEW &bull; Click to View
                            </span>
                        <?php endif; ?>
                        <span style="background: <?php echo $b_bg; ?>; color: <?php echo $b_txt; ?>; border: 1px solid <?php echo $b_col; ?>; font-size: 0.73rem; font-weight: 800; padding: 0.18rem 0.65rem; border-radius: 999px;">
                            <?php echo $step_lbl; ?>
                        </span>
                        <span style="font-size: 0.76rem; color: #475569; font-weight: 700;">
                            <i class="fa-solid fa-user-tie" style="color: #047857;"></i> Adviser: <?php echo htmlspecialchars($cons['adviser_name'] ?? 'Faculty Adviser'); ?>
                        </span>
                        <span style="font-size: 0.74rem; color: #64748B;">
                            &bull; <?php echo htmlspecialchars($cons['consultation_stage']); ?>
                        </span>
                    </div>
                    <h3 style="margin: 0; font-size: 1rem; font-weight: 800; color: #0F172A;">
                        <?php echo htmlspecialchars($cons['research_title']); ?>
                    </h3>
                </div>

                <div style="display: flex; gap: 0.4rem;">
                    <a href="export_consultation_form.php?cons_id=<?php echo (int)$cons['id']; ?>" target="_blank" style="background: #FFFFFF; border: 1px solid #CBD5E1; color: #0F172A; font-size: 0.75rem; font-weight: 700; padding: 0.35rem 0.7rem; border-radius: 7px; text-decoration: none;">
                        <i class="fa-solid fa-print" style="color: #047857;"></i> Print Form
                    </a>
                </div>
            </div>

            <div style="padding: 1.1rem 1.25rem; display: flex; flex-direction: column; gap: 0.85rem;">
                <!-- 1. Student Idea Summary -->
                <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 9px; padding: 0.75rem 1rem; font-size: 0.82rem; color: #334155;">
                    <strong style="color: #0F172A;">Your Submitted Research Idea:</strong> <?php echo nl2br(htmlspecialchars($cons['research_idea_summary'])); ?>
                </div>

                <!-- 2. Adviser Question & Readiness -->
                <?php if ($has_asked || !empty($cons['adviser_preliminary_comments'])): ?>
                    <div style="background: #FFFBEB; border: 1.5px solid #FCD34D; border-radius: 10px; padding: 0.85rem 1rem;">
                        <div style="font-size: 0.76rem; font-weight: 800; color: #B45309; text-transform: uppercase; margin-bottom: 0.3rem;">
                            <i class="fa-solid fa-circle-question"></i> Adviser's Question &amp; Evaluation (Readiness: <?php echo htmlspecialchars($cons['readiness_level']); ?>)
                        </div>
                        <?php if ($has_asked): ?>
                            <div style="font-size: 0.86rem; font-weight: 700; color: #0F172A; white-space: pre-line;"><?php echo htmlspecialchars($cons['follow_up_questions']); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($cons['adviser_preliminary_comments'])): ?>
                            <div style="font-size: 0.81rem; color: #78350F; margin-top: 0.25rem;"><?php echo nl2br(htmlspecialchars($cons['adviser_preliminary_comments'])); ?></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- 3. Student Answer Box (Form if pending/follow_up, or Display if already accepted/completed) -->
                <?php if (in_array($st, ['follow_up_required', 'pending_review', 'student_responded'])): ?>
                    <form method="POST" action="action.php" style="background: #EFF6FF; border: 1.5px solid #93C5FD; border-radius: 10px; padding: 0.9rem 1rem; margin: 0;">
                        <input type="hidden" name="student_respond_consultation" value="1">
                        <input type="hidden" name="consultation_id" value="<?php echo (int)$cons['id']; ?>">
                        <input type="hidden" name="return_tab" value="consultations">
                        <label style="font-size: 0.8rem; font-weight: 800; color: #1E3A8A; display: block; margin-bottom: 0.35rem;">
                            <i class="fa-solid fa-reply"></i> <?php echo $has_replied ? 'Your Answer to Adviser (You can update if needed):' : 'Answer Your Adviser\'s Questions Here:'; ?>
                        </label>
                        <textarea name="student_response" rows="2" required placeholder="Enter your response and clarification for your adviser here..." style="width: 100%; padding: 0.55rem 0.75rem; border: 1px solid #93C5FD; border-radius: 7px; font-size: 0.83rem; margin-bottom: 0.5rem;"><?php echo htmlspecialchars($cons['student_response'] ?? ''); ?></textarea>
                        <div style="display: flex; justify-content: flex-end;">
                            <button type="submit" class="btn-primary" style="background: #2563EB; padding: 0.45rem 1rem; font-size: 0.8rem; font-weight: 800; border-radius: 7px;">
                                <i class="fa-solid fa-paper-plane"></i> Send Answer to Adviser
                            </button>
                        </div>
                    </form>
                <?php elseif ($has_replied): ?>
                    <div style="background: #EFF6FF; border: 1px solid #BFDBFE; border-radius: 9px; padding: 0.75rem 1rem; font-size: 0.82rem; color: #1E3A8A;">
                        <strong><i class="fa-solid fa-reply"></i> Your Answer:</strong> <?php echo nl2br(htmlspecialchars($cons['student_response'])); ?>
                    </div>
                <?php endif; ?>

                <!-- 4. Adviser Feedback / Confirmed Schedule / Recorded Outcome -->
                <?php if ($st === 'accepted' || $st === 'completed'): ?>
                    <div style="background: #ECFDF5; border: 1.5px solid #6EE7B7; border-radius: 10px; padding: 0.85rem 1rem; font-size: 0.83rem; color: #064E3B;">
                        <?php if (!empty($cons['confirmed_schedule'])): ?>
                            <div style="margin-bottom: 0.3rem;">
                                <strong><i class="fa-regular fa-calendar-check"></i> Confirmed Schedule:</strong> <?php echo htmlspecialchars($cons['confirmed_schedule']); ?>
                                <?php if (!empty($cons['venue_or_link'])): ?> &bull; <strong>Venue:</strong> <?php echo htmlspecialchars($cons['venue_or_link']); ?><?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($cons['adviser_review_feedback'])): ?>
                            <div style="margin-bottom: 0.3rem;"><strong>Adviser Feedback:</strong> <?php echo nl2br(htmlspecialchars($cons['adviser_review_feedback'])); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($cons['outcome_summary'])): ?>
                            <div style="margin-top: 0.4rem; padding-top: 0.4rem; border-top: 1px dashed #6EE7B7;">
                                <strong><i class="fa-solid fa-flag-checkered"></i> Recorded Outcome (<?php echo htmlspecialchars($cons['outcome_verdict']); ?> — <?php echo (int)$cons['progress_rating']; ?>%):</strong>
                                <?php echo nl2br(htmlspecialchars($cons['outcome_summary'])); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
function markStudentConsultationSeen(consId, cardEl) {
    if (!cardEl || cardEl.getAttribute('data-unread') !== '1') return;

    cardEl.setAttribute('data-unread', '0');
    cardEl.style.borderColor = '#E2E8F0';
    cardEl.style.cursor = 'default';

    const newBadge = document.getElementById('stu-cons-new-badge-' + consId);
    if (newBadge) {
        newBadge.remove();
    }

    const badge = document.getElementById('sidebarStudentConsultationsBadge');
    if (badge) {
        let cur = parseInt(badge.textContent.trim(), 10);
        if (isNaN(cur)) cur = 0;
        const next = Math.max(0, cur - 1);
        if (next <= 0) {
            badge.style.display = 'none';
            badge.remove();
        } else {
            badge.textContent = String(next);
        }
    }

    const fd = new FormData();
    fd.append('action', 'mark_consultation_seen');
    fd.append('cons_id', consId);
    fetch('api.php', { method: 'POST', body: fd, keepalive: true })
        .then(r => r.json())
        .then(data => {
            if (data && data.success && typeof data.remaining_count === 'number') {
                const b = document.getElementById('sidebarStudentConsultationsBadge');
                if (data.remaining_count <= 0) {
                    if (b) { b.style.display = 'none'; b.remove(); }
                } else if (b) {
                    b.textContent = String(data.remaining_count);
                }
            }
        })
        .catch(err => console.error(err));
}
</script>
