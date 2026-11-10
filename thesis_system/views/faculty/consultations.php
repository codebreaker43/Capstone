<?php
// FACULTY ADVISER CONSULTATION PROCESS & ORAL DEFENSE CLEARANCE VIEW (SIMPLIFIED & STEP-BY-STEP)

if (!isset($conn) || !isset($uid)) {
    die('Unauthorized access.');
}

if (function_exists('ensure_consultation_schema')) {
    ensure_consultation_schema($conn);
}

// Auto-ensure every pending match_request for this adviser has a linked consultation record
$pend_mr_q = $conn->query("SELECT student_id, group_id FROM match_request WHERE adviser_id = $uid AND status = 'pending'");
if ($pend_mr_q) {
    while ($pmr = $pend_mr_q->fetch_assoc()) {
        ensure_linked_consultation_for_request($conn, (int)$pmr['student_id'], (int)$uid, (int)($pmr['group_id'] ?? 0));
    }
}

$filter_status = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$filter_student = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$highlight_cons_id = isset($_GET['cons_id']) ? (int)$_GET['cons_id'] : 0;

// If a single item was explicitly opened via cons_id, mark only that 1 item as seen
if ($highlight_cons_id > 0) {
    $conn->query("UPDATE adviser_consultations SET adviser_seen = 1 WHERE id = $highlight_cons_id AND adviser_id = $uid AND adviser_seen = 0");
}

// Fetch all advisees + any student who submitted a consultation to this adviser
$advisees_sql = "
    SELECT DISTINCT u.id AS student_id, u.name AS full_name, u.email,
           sp.student_id AS id_number, sp.department, sp.course_code, sp.thesis_topic, sp.group_id,
           sp.consultation_process_status, sp.consultation_completed_at, sp.consultation_completion_notes,
           sp.oral_defense_approved, sp.oral_defense_approved_at, sp.oral_defense_stage_approved, sp.oral_defense_endorsement_remarks,
           cg.group_name
    FROM user u
    LEFT JOIN student_profile sp ON sp.user_id = u.id
    LEFT JOIN capstone_group cg ON sp.group_id = cg.id
    LEFT JOIN match_request mr ON mr.student_id = u.id AND mr.adviser_id = $uid AND mr.status IN ('pending', 'accepted')
    LEFT JOIN adviser_consultations ac ON ac.student_id = u.id AND ac.adviser_id = $uid
    WHERE u.role = 'student'
      AND (mr.id IS NOT NULL OR ac.id IS NOT NULL)
    ORDER BY u.name ASC
";
$advisees_q = $conn->query($advisees_sql);
$advisees = [];
if ($advisees_q) {
    while ($row = $advisees_q->fetch_assoc()) {
        $sid = (int)$row['student_id'];
        $gid = (int)($row['group_id'] ?? 0);
        $row['summary'] = get_student_consultation_summary($conn, $sid, $gid);
        $advisees[$sid] = $row;
    }
}

// Fetch consultations for this adviser
$where_clauses = ["ac.adviser_id = $uid", "ac.status != 'cancelled'"];
if ($filter_status !== 'all' && $filter_status !== '') {
    $safe_st = $conn->real_escape_string($filter_status);
    $where_clauses[] = "ac.status = '$safe_st'";
}
if ($filter_student > 0) {
    $where_clauses[] = "ac.student_id = $filter_student";
}
$where_sql = implode(' AND ', $where_clauses);

$consultations = [];
$c_res = $conn->query("
    SELECT ac.*,
           u.name AS student_name, u.email AS student_email,
           sp.student_id AS student_no, sp.department AS student_dept, sp.thesis_topic AS profile_thesis_title,
           sp.consultation_process_status, sp.oral_defense_approved, sp.oral_defense_stage_approved,
           cg.group_name
    FROM adviser_consultations ac
    JOIN user u ON ac.student_id = u.id
    LEFT JOIN student_profile sp ON sp.user_id = u.id
    LEFT JOIN capstone_group cg ON ac.group_id = cg.id
    WHERE $where_sql
    ORDER BY 
        CASE ac.status
            WHEN 'student_responded' THEN 1
            WHEN 'pending_review' THEN 2
            WHEN 'follow_up_required' THEN 3
            WHEN 'accepted' THEN 4
            WHEN 'completed' THEN 5
            ELSE 6
        END ASC,
        ac.id DESC
");
if ($c_res) {
    while ($r = $c_res->fetch_assoc()) {
        $consultations[] = $r;
    }
}

// Counts by status for filter tabs
$counts = [
    'all' => 0,
    'pending_review' => 0,
    'follow_up_required' => 0,
    'student_responded' => 0,
    'accepted' => 0,
    'completed' => 0,
];
$cnt_q = $conn->query("SELECT status, COUNT(*) AS c FROM adviser_consultations WHERE adviser_id = $uid AND status != 'cancelled' GROUP BY status");
if ($cnt_q) {
    while ($cr = $cnt_q->fetch_assoc()) {
        $st = $cr['status'];
        $c = (int)$cr['c'];
        $counts['all'] += $c;
        if (isset($counts[$st])) {
            $counts[$st] += $c;
        }
    }
}
?>

<!-- HEADER BANNER -->
<div class="banner" style="background: linear-gradient(135deg, #022C22 0%, #064E3B 60%, #047857 100%); border-radius: 14px; padding: 1.35rem 1.65rem; color: white; margin-bottom: 1.25rem; box-shadow: var(--shadow-sm);">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <span style="background: rgba(245, 158, 11, 0.25); color: #FCD34D; border: 1px solid rgba(245, 158, 11, 0.45); padding: 0.2rem 0.65rem; border-radius: 999px; font-size: 0.72rem; font-weight: 800; text-transform: uppercase;">
                <i class="fa-solid fa-clipboard-check"></i> Step-by-Step Adviser Consultation
            </span>
            <h1 style="font-size: 1.45rem; font-weight: 800; margin: 0.3rem 0 0.2rem 0; color: #FFFFFF;">
                Adviser Consultation &amp; Defense Clearance
            </h1>
            <p style="margin: 0; color: #D1FAE5; font-size: 0.84rem;">
                Simple 4-Step Flow: <strong>1. Ask Question</strong> &rarr; <strong>2. Student Replies</strong> &rarr; <strong>3. Accept Consultation</strong> &rarr; <strong>4. Record Outcome &amp; Approve Defense</strong>
            </p>
        </div>

        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
            <a href="#defense-clearance-registry" style="background: #F59E0B; color: #0F172A; font-weight: 800; font-size: 0.82rem; padding: 0.55rem 1rem; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem;">
                <i class="fa-solid fa-graduation-cap"></i> Oral Defense Approval List
            </a>
            <a href="?tab=schedule" style="background: rgba(255,255,255,0.16); border: 1px solid rgba(255,255,255,0.3); color: #FFFFFF; font-weight: 700; font-size: 0.82rem; padding: 0.55rem 1rem; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem;">
                <i class="fa-solid fa-calendar-plus"></i> Schedule Meeting
            </a>
        </div>
    </div>
</div>

<!-- COMPACT FILTER BAR -->
<div class="card" style="margin-bottom: 1.25rem; border: 1px solid #E2E8F0; border-radius: 12px;">
    <div class="card-body" style="padding: 0.85rem 1.15rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
        <!-- Status Pills -->
        <div style="display: flex; gap: 0.4rem; flex-wrap: wrap;">
            <?php
            $filter_pills = [
                'all' => ['label' => 'All', 'count' => $counts['all']],
                'pending_review' => ['label' => '1. New Request', 'count' => $counts['pending_review']],
                'follow_up_required' => ['label' => '2. Waiting Student Reply', 'count' => $counts['follow_up_required']],
                'student_responded' => ['label' => '3. Student Replied', 'count' => $counts['student_responded']],
                'accepted' => ['label' => '4. Accepted', 'count' => $counts['accepted']],
                'completed' => ['label' => '5. Completed', 'count' => $counts['completed']],
            ];
            foreach ($filter_pills as $f_key => $f_meta):
                $is_act = ($filter_status === $f_key);
            ?>
                <a href="?tab=consultations&status=<?php echo $f_key; ?><?php echo $filter_student > 0 ? '&student_id=' . $filter_student : ''; ?>"
                   style="text-decoration: none; padding: 0.42rem 0.8rem; border-radius: 8px; font-size: 0.78rem; font-weight: 800; border: 1.5px solid <?php echo $is_act ? '#064E3B' : '#CBD5E1'; ?>; background: <?php echo $is_act ? '#064E3B' : '#FFFFFF'; ?>; color: <?php echo $is_act ? '#FFFFFF' : '#334155'; ?>; display: inline-flex; align-items: center; gap: 0.35rem;">
                    <?php echo $f_meta['label']; ?>
                    <span style="background: <?php echo $is_act ? 'rgba(255,255,255,0.22)' : '#F1F5F9'; ?>; color: <?php echo $is_act ? '#FFFFFF' : '#0F172A'; ?>; padding: 1px 6px; border-radius: 999px; font-size: 0.72rem;"><?php echo (int)$f_meta['count']; ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Student Dropdown -->
        <form method="GET" action="faculty_dashboard.php" style="display: flex; align-items: center; gap: 0.5rem; margin: 0;">
            <input type="hidden" name="tab" value="consultations">
            <input type="hidden" name="status" value="<?php echo htmlspecialchars($filter_status); ?>">
            <select name="student_id" onchange="this.form.submit()" class="form-control" style="width: auto; min-width: 220px; padding: 0.42rem 0.75rem; font-size: 0.8rem; font-weight: 700; border-radius: 8px;">
                <option value="0">All Students / Groups</option>
                <?php foreach ($advisees as $sid => $adv): ?>
                    <option value="<?php echo $sid; ?>" <?php echo $filter_student === $sid ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($adv['full_name'] . (!empty($adv['group_name']) ? ' (' . $adv['group_name'] . ')' : '')); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($filter_student > 0 || $filter_status !== 'all'): ?>
                <a href="?tab=consultations" style="font-size: 0.76rem; font-weight: 800; color: #DC2626; text-decoration: none;">Reset</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- SECTION 1: CLEAN STEP-BY-STEP CONSULTATION CARDS                          -->
<!-- ========================================================================= -->
<div style="display: flex; flex-direction: column; gap: 1.15rem; margin-bottom: 2rem;">
    <?php if (empty($consultations)): ?>
        <div class="card" style="border-radius: 12px;">
            <div class="card-body" style="text-align: center; padding: 2.75rem 1.5rem;">
                <i class="fa-regular fa-folder-open" style="font-size: 2.4rem; color: #94A3B8; margin-bottom: 0.6rem;"></i>
                <h3 style="margin: 0 0 0.3rem 0; color: #1E293B; font-size: 1.02rem;">No Consultation Records Found</h3>
                <p style="margin: 0 auto; color: #64748B; font-size: 0.84rem; max-width: 480px;">
                    Consultation requests submitted by your research students will appear here.
                </p>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($consultations as $c):
            $cid = (int)$c['id'];
            $sid = (int)$c['student_id'];
            $st = $c['status'] ?? 'pending_review';
            $has_asked = !empty(trim($c['follow_up_questions'] ?? ''));
            $has_replied = !empty(trim($c['student_response'] ?? ''));

            // Determine current active step (1 to 4)
            // Step 1: Adviser needs to ask questions
            // Step 2: Waiting for student reply OR student replied & ready to accept
            // Step 3: Accepted -> Record Outcome
            // Step 4: Completed
            if ($st === 'completed') {
                $step_num = 4;
                $step_badge = ['label' => 'Step 4/4 • Completed', 'bg' => '#D1FAE5', 'col' => '#065F46', 'bdr' => '#10B981'];
            } elseif ($st === 'accepted') {
                $step_num = 3;
                $step_badge = ['label' => 'Step 3/4 • Accepted (Record Outcome)', 'bg' => '#E0F2FE', 'col' => '#0369A1', 'bdr' => '#0284C7'];
            } elseif ($has_replied || $st === 'student_responded') {
                $step_num = 2;
                $step_badge = ['label' => 'Step 2/4 • Student Replied (Ready to Accept)', 'bg' => '#E0E7FF', 'col' => '#3730A3', 'bdr' => '#6366F1'];
            } elseif ($has_asked || $st === 'follow_up_required') {
                $step_num = 2;
                $step_badge = ['label' => 'Step 2/4 • Waiting for Student Reply', 'bg' => '#FFEDD5', 'col' => '#9A3412', 'bdr' => '#F97316'];
            } else {
                $step_num = 1;
                $step_badge = ['label' => 'Step 1/4 • New Request (Ask Question First)', 'bg' => '#FEF3C7', 'col' => '#92400E', 'bdr' => '#F59E0B'];
            }

            $is_highlighted = ($highlight_cons_id === $cid);
            $is_unread_item = (in_array($st, ['pending_review', 'student_responded'], true) && empty($c['adviser_seen']));
        ?>
        <div class="card" id="consultation-card-<?php echo $cid; ?>"
             data-unread="<?php echo $is_unread_item ? '1' : '0'; ?>"
             onclick="markSingleConsultationSeen(<?php echo $cid; ?>, this)"
             style="margin-bottom: 0; border-radius: 14px; border: 1.5px solid <?php echo $is_unread_item ? '#F59E0B' : '#E2E8F0'; ?>; border-left: 6px solid <?php echo $step_badge['bdr']; ?>; cursor: <?php echo $is_unread_item ? 'pointer' : 'default'; ?>; transition: box-shadow 0.2s, border-color 0.2s; <?php echo ($is_highlighted || $is_unread_item) ? 'box-shadow: 0 0 0 3px rgba(245,158,11,0.22);' : ''; ?>">
            
            <!-- Card Top Bar -->
            <div style="padding: 0.95rem 1.25rem; background: #F8FAFC; border-bottom: 1px solid #E2E8F0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
                <div>
                    <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 0.25rem;">
                        <?php if ($is_unread_item): ?>
                            <span id="cons-new-badge-<?php echo $cid; ?>" style="background: #F59E0B; color: #0F172A; padding: 0.2rem 0.65rem; border-radius: 999px; font-size: 0.72rem; font-weight: 900; display: inline-flex; align-items: center; gap: 0.3rem; box-shadow: 0 2px 6px rgba(245,158,11,0.35);">
                                <i class="fa-solid fa-bell"></i> NEW &bull; Click to View
                            </span>
                        <?php endif; ?>
                        <span style="background: <?php echo $step_badge['bg']; ?>; color: <?php echo $step_badge['col']; ?>; border: 1px solid <?php echo $step_badge['bdr']; ?>; padding: 0.2rem 0.65rem; border-radius: 999px; font-size: 0.73rem; font-weight: 800;">
                            <?php echo $step_badge['label']; ?>
                        </span>
                        <span style="font-size: 0.75rem; font-weight: 700; color: #475569;">
                            <i class="fa-solid fa-user-graduate" style="color: #059669;"></i>
                            <?php echo htmlspecialchars($c['student_name'] . (!empty($c['group_name']) ? " • Group {$c['group_name']}" : "")); ?>
                        </span>
                        <span style="font-size: 0.73rem; color: #64748B;">
                            &bull; <i class="fa-regular fa-calendar"></i> <?php echo date('M d, Y', strtotime($c['preferred_date'])); ?> (<?php echo htmlspecialchars($c['consultation_mode']); ?>)
                        </span>
                    </div>
                    <h3 style="margin: 0; font-size: 1.02rem; font-weight: 800; color: #0F172A;">
                        <?php echo htmlspecialchars($c['research_title']); ?>
                    </h3>
                </div>

                <div style="display: flex; align-items: center; gap: 0.45rem;">
                    <a href="export_consultation_form.php?cons_id=<?php echo $cid; ?>" target="_blank" style="background: #FFFFFF; border: 1px solid #CBD5E1; color: #0F172A; padding: 0.35rem 0.7rem; border-radius: 7px; font-size: 0.75rem; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 0.3rem;">
                        <i class="fa-solid fa-print" style="color: #059669;"></i> Print Form
                    </a>
                    <a href="export_consultation_form.php?cons_id=<?php echo $cid; ?>&format=doc" style="background: #EFF6FF; border: 1px solid #93C5FD; color: #1D4ED8; padding: 0.35rem 0.7rem; border-radius: 7px; font-size: 0.75rem; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 0.3rem;">
                        <i class="fa-solid fa-file-word"></i> Word
                    </a>
                </div>
            </div>

            <!-- Visual 4-Step Progress Bar -->
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); background: #FFFFFF; border-bottom: 1px solid #F1F5F9; padding: 0.55rem 1.25rem; gap: 0.5rem; font-size: 0.74rem; font-weight: 800;">
                <div style="color: <?php echo $step_num >= 1 ? '#047857' : '#94A3B8'; ?>; display: flex; align-items: center; gap: 0.35rem;">
                    <i class="fa-solid <?php echo $has_asked ? 'fa-circle-check' : 'fa-circle-dot'; ?>"></i> 1. Ask Question
                </div>
                <div style="color: <?php echo $step_num >= 2 ? ($has_replied ? '#047857' : '#D97706') : '#94A3B8'; ?>; display: flex; align-items: center; gap: 0.35rem;">
                    <i class="fa-solid <?php echo $has_replied ? 'fa-circle-check' : ($has_asked ? 'fa-clock' : 'fa-circle'); ?>"></i> 2. Student Answer
                </div>
                <div style="color: <?php echo $step_num >= 3 ? '#047857' : '#94A3B8'; ?>; display: flex; align-items: center; gap: 0.35rem;">
                    <i class="fa-solid <?php echo ($st === 'accepted' || $st === 'completed') ? 'fa-circle-check' : 'fa-circle'; ?>"></i> 3. Accept Request
                </div>
                <div style="color: <?php echo $step_num >= 4 ? '#047857' : '#94A3B8'; ?>; display: flex; align-items: center; gap: 0.35rem;">
                    <i class="fa-solid <?php echo $st === 'completed' ? 'fa-circle-check' : 'fa-circle'; ?>"></i> 4. Record Outcome
                </div>
            </div>

            <!-- Card Main Content: Left = Conversation Summary | Right = Current Step Action Box -->
            <div style="padding: 1.15rem 1.25rem; display: grid; grid-template-columns: 1.15fr 1fr; gap: 1.25rem; align-items: start;">
                
                <!-- LEFT: CLEAN CONVERSATION SUMMARY -->
                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                    <!-- Student Research Idea Box -->
                    <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 10px; padding: 0.85rem 1rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
                            <span style="font-size: 0.73rem; font-weight: 800; color: #475569; text-transform: uppercase;">
                                <i class="fa-solid fa-lightbulb" style="color: #F59E0B;"></i> Student's Research Idea &amp; Agenda
                            </span>
                            <?php if (!empty($c['attachment_path'])): ?>
                                <a href="<?php echo htmlspecialchars($c['attachment_path']); ?>" target="_blank" style="font-size: 0.73rem; font-weight: 700; color: #0284C7; text-decoration: none;">
                                    <i class="fa-solid fa-paperclip"></i> View Attached File
                                </a>
                            <?php endif; ?>
                        </div>
                        <div style="font-size: 0.83rem; color: #1E293B; line-height: 1.45;">
                            <?php echo nl2br(htmlspecialchars($c['research_idea_summary'])); ?>
                        </div>
                        <?php if (!empty($c['consultation_agenda'])): ?>
                            <div style="margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px dashed #CBD5E1; font-size: 0.8rem; color: #475569;">
                                <strong>Agenda:</strong> <?php echo nl2br(htmlspecialchars($c['consultation_agenda'])); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Adviser's Question Bubble (if asked) -->
                    <?php if ($has_asked || !empty($c['adviser_preliminary_comments'])): ?>
                        <div style="background: #FFFBEB; border: 1px solid #FDE68A; border-radius: 10px; padding: 0.8rem 1rem; font-size: 0.82rem;">
                            <div style="font-size: 0.72rem; font-weight: 800; color: #B45309; text-transform: uppercase; margin-bottom: 0.25rem;">
                                <i class="fa-solid fa-circle-question"></i> Your Question &amp; Comments to Student (Readiness: <?php echo htmlspecialchars($c['readiness_level'] ?? 'Evaluated'); ?>)
                            </div>
                            <?php if ($has_asked): ?>
                                <div style="color: #0F172A; font-weight: 600; white-space: pre-line;"><?php echo htmlspecialchars($c['follow_up_questions']); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($c['adviser_preliminary_comments'])): ?>
                                <div style="color: #475569; margin-top: 0.25rem; white-space: pre-line;"><?php echo htmlspecialchars($c['adviser_preliminary_comments']); ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Student's Reply Bubble (if answered) -->
                    <?php if ($has_replied): ?>
                        <div style="background: #EEF2FF; border: 1.5px solid #A5B4FC; border-radius: 10px; padding: 0.85rem 1rem; font-size: 0.83rem;">
                            <div style="font-size: 0.72rem; font-weight: 800; color: #3730A3; text-transform: uppercase; margin-bottom: 0.25rem;">
                                <i class="fa-solid fa-reply"></i> Student's Answer
                                <?php if (!empty($c['student_responded_at'])): ?>
                                    <span style="font-weight: 600; color: #6366F1;">(<?php echo date('M d, g:i A', strtotime($c['student_responded_at'])); ?>)</span>
                                <?php endif; ?>
                            </div>
                            <div style="color: #1E293B; white-space: pre-line; line-height: 1.45;"><?php echo htmlspecialchars($c['student_response']); ?></div>
                        </div>
                    <?php endif; ?>

                    <!-- Adviser Feedback on Reply (if any) -->
                    <?php if (!empty($c['adviser_review_feedback'])): ?>
                        <div style="background: #F0FDF4; border: 1px solid #BBF7D0; border-radius: 10px; padding: 0.75rem 1rem; font-size: 0.81rem; color: #065F46;">
                            <strong><i class="fa-solid fa-check"></i> Your Feedback on Student's Answer:</strong>
                            <div style="margin-top: 0.2rem; white-space: pre-line;"><?php echo htmlspecialchars($c['adviser_review_feedback']); ?></div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- RIGHT: SINGLE FOCUSED ACTION BOX FOR THE CURRENT STEP -->
                <div>
                    <?php if ($st === 'completed'): ?>
                        <!-- STEP 4: COMPLETED SUMMARY -->
                        <div style="background: #ECFDF5; border: 1.5px solid #10B981; border-radius: 12px; padding: 1.05rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.6rem;">
                                <span style="font-size: 0.8rem; font-weight: 800; color: #065F46;">
                                    <i class="fa-solid fa-circle-check"></i> Consultation Completed
                                </span>
                                <span style="background: #059669; color: #FFFFFF; padding: 0.2rem 0.6rem; border-radius: 999px; font-size: 0.72rem; font-weight: 800;">
                                    <?php echo htmlspecialchars($c['outcome_verdict']); ?> (<?php echo (int)($c['progress_rating'] ?? 85); ?>%)
                                </span>
                            </div>
                            <div style="background: #FFFFFF; border: 1px solid #A7F3D0; border-radius: 8px; padding: 0.7rem; font-size: 0.81rem; color: #1E293B; margin-bottom: 0.6rem;">
                                <strong>Outcome Summary:</strong><br>
                                <?php echo nl2br(htmlspecialchars($c['outcome_summary'])); ?>
                                <?php if (!empty($c['action_items'])): ?>
                                    <div style="margin-top: 0.4rem; padding-top: 0.4rem; border-top: 1px dashed #A7F3D0;">
                                        <strong>Next Action Items:</strong> <?php echo nl2br(htmlspecialchars($c['action_items'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php if (empty($c['oral_defense_approved'])): ?>
                                <form method="POST" action="action.php" style="margin: 0;">
                                    <input type="hidden" name="student_id" value="<?php echo $sid; ?>">
                                    <input type="hidden" name="return_tab" value="consultations">
                                    <input type="hidden" name="action_mode" value="approve_oral_defense">
                                    <input type="hidden" name="oral_defense_stage_approved" value="Proposal Oral Defense (IT 198)">
                                    <input type="hidden" name="oral_defense_endorsement_remarks" value="Completed required adviser consultation process.">
                                    <input type="hidden" name="confirm_consultation_checkbox" value="1">
                                    <button type="submit" name="adviser_confirm_consultation_and_defense" class="btn-primary" style="width: 100%; background: #022C22; color: #FCD34D; font-weight: 800; padding: 0.55rem; border-radius: 8px;">
                                        <i class="fa-solid fa-graduation-cap"></i> Approve Student for Oral Defense
                                    </button>
                                </form>
                            <?php else: ?>
                                <div style="font-size: 0.78rem; font-weight: 800; color: #047857; text-align: center; padding: 0.35rem; background: #D1FAE5; border-radius: 6px;">
                                    <i class="fa-solid fa-award"></i> Student is Approved for <?php echo htmlspecialchars($c['oral_defense_stage_approved'] ?: 'Oral Defense'); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                    <?php elseif ($st === 'accepted'): ?>
                        <!-- STEP 3: RECORD OUTCOME FORM (SHOWN ONLY AFTER CONSULTATION IS ACCEPTED) -->
                        <div style="background: #F0FDF4; border: 1.5px solid #86EFAC; border-radius: 12px; padding: 1.05rem;">
                            <div style="font-size: 0.84rem; font-weight: 800; color: #065F46; margin-bottom: 0.25rem;">
                                <i class="fa-solid fa-clipboard-check"></i> Step 3: Record Consultation Outcome
                            </div>
                            <p style="margin: 0 0 0.75rem 0; font-size: 0.76rem; color: #047857;">
                                Record the outcome of your consultation to monitor student progress.
                            </p>

                            <form method="POST" action="action.php" style="display: flex; flex-direction: column; gap: 0.65rem; font-size: 0.8rem;">
                                <input type="hidden" name="consultation_id" value="<?php echo $cid; ?>">
                                <input type="hidden" name="return_tab" value="consultations">

                                <div style="display: grid; grid-template-columns: 1fr 95px; gap: 0.5rem;">
                                    <div>
                                        <label style="font-weight: 700; color: #065F46; display: block; margin-bottom: 0.2rem;">Outcome Result</label>
                                        <select name="outcome_verdict" class="form-control" style="padding: 0.45rem; font-size: 0.8rem; font-weight: 700;">
                                            <option value="Proceed to Next Milestone">Proceed to Next Milestone</option>
                                            <option value="Endorsed for Oral Defense">Endorsed for Oral Defense</option>
                                            <option value="Revisions Required">Revisions Required</option>
                                            <option value="Follow-Up Consultation Needed">Follow-Up Needed</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label style="font-weight: 700; color: #065F46; display: block; margin-bottom: 0.2rem;">Progress %</label>
                                        <input type="number" name="progress_rating" min="0" max="100" value="85" class="form-control" style="padding: 0.45rem; font-size: 0.8rem; font-weight: 700;">
                                    </div>
                                </div>

                                <div>
                                    <label style="font-weight: 700; color: #065F46; display: block; margin-bottom: 0.2rem;">Summary of Discussion / Findings <span style="color:#DC2626;">*</span></label>
                                    <textarea name="outcome_summary" rows="2" required class="form-control" placeholder="Key discussion points, agreed revisions, and progress evaluation..." style="font-size: 0.8rem;"></textarea>
                                </div>

                                <div>
                                    <label style="font-weight: 700; color: #065F46; display: block; margin-bottom: 0.2rem;">Next Action Items (Optional)</label>
                                    <input type="text" name="action_items" class="form-control" placeholder="e.g. Finalize Chapter 1-3 manuscript for defense" style="padding: 0.45rem; font-size: 0.8rem;">
                                </div>

                                <label style="display: flex; align-items: center; gap: 0.45rem; font-weight: 800; color: #064E3B; cursor: pointer; background: #DCFCE7; padding: 0.45rem 0.65rem; border-radius: 7px;">
                                    <input type="checkbox" name="mark_process_complete" value="1" checked>
                                    <span>Mark Student's Consultation Process as Completed</span>
                                </label>

                                <button type="submit" name="adviser_record_consultation_outcome" class="btn-primary" style="background: #047857; padding: 0.6rem; font-weight: 800; width: 100%; border-radius: 8px;">
                                    <i class="fa-solid fa-check-double"></i> Save Outcome &amp; Complete Session
                                </button>
                            </form>
                        </div>

                    <?php elseif ($has_replied): ?>
                        <!-- STEP 2B: STUDENT REPLIED -> REVIEW & ACCEPT -->
                        <div style="background: #EEF2FF; border: 1.5px solid #818CF8; border-radius: 12px; padding: 1.05rem;">
                            <div style="font-size: 0.84rem; font-weight: 800; color: #3730A3; margin-bottom: 0.25rem;">
                                <i class="fa-solid fa-user-check"></i> Step 2: Review Student's Answer &amp; Accept
                            </div>
                            <p style="margin: 0 0 0.75rem 0; font-size: 0.76rem; color: #4338CA;">
                                The student has responded to your questions. You may now review their response and accept the consultation request.
                            </p>

                            <form method="POST" action="action.php" style="display: flex; flex-direction: column; gap: 0.65rem; font-size: 0.8rem;">
                                <input type="hidden" name="consultation_id" value="<?php echo $cid; ?>">
                                <input type="hidden" name="return_tab" value="consultations">
                                <input type="hidden" name="readiness_level" value="Ready">
                                <input type="hidden" name="readiness_score" value="90">

                                <div>
                                    <label style="font-weight: 700; color: #1E293B; display: block; margin-bottom: 0.2rem;">Your Feedback on Student's Answer</label>
                                    <textarea name="adviser_review_feedback" rows="2" class="form-control" placeholder="Good explanation. Your scope and objectives are now clear..." style="font-size: 0.8rem;"><?php echo htmlspecialchars($c['adviser_review_feedback'] ?? ''); ?></textarea>
                                </div>

                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                                    <div>
                                        <label style="font-weight: 700; color: #334155; display: block; margin-bottom: 0.2rem;">Meeting Date/Time</label>
                                        <input type="text" name="confirmed_schedule" value="<?php echo htmlspecialchars($c['confirmed_schedule'] ?: (date('M d, Y', strtotime($c['preferred_date'])) . ' ' . ($c['preferred_time'] ?? ''))); ?>" class="form-control" style="padding: 0.42rem; font-size: 0.78rem;">
                                    </div>
                                    <div>
                                        <label style="font-weight: 700; color: #334155; display: block; margin-bottom: 0.2rem;">Room / Meet Link</label>
                                        <input type="text" name="venue_or_link" value="<?php echo htmlspecialchars($c['venue_or_link'] ?: 'CEIT Faculty Room'); ?>" class="form-control" style="padding: 0.42rem; font-size: 0.78rem;">
                                    </div>
                                </div>

                                <label style="display: flex; align-items: center; gap: 0.45rem; font-weight: 800; color: #065F46; cursor: pointer; background: #ECFDF5; border: 1px solid #A7F3D0; padding: 0.45rem 0.65rem; border-radius: 7px;">
                                    <input type="checkbox" name="also_accept_advisee_request" value="1" checked>
                                    <span>Also Accept Student as My Official Advisee</span>
                                </label>

                                <input type="hidden" name="eval_action" value="accept_consultation">
                                <button type="submit" name="adviser_evaluate_consultation" class="btn-primary" style="background: #047857; padding: 0.6rem; font-weight: 800; width: 100%; border-radius: 8px;">
                                    <i class="fa-solid fa-circle-check"></i> Accept Consultation &amp; Approve Student
                                </button>
                            </form>
                        </div>

                    <?php else: ?>
                        <!-- STEP 1: ASK FOLLOW-UP QUESTIONS & EVALUATE READINESS -->
                        <div style="background: #FFFBEB; border: 1.5px solid #FCD34D; border-radius: 12px; padding: 1.05rem;">
                            <div style="font-size: 0.84rem; font-weight: 800; color: #92400E; margin-bottom: 0.25rem;">
                                <i class="fa-solid fa-circle-question"></i>
                                <?php echo $has_asked ? 'Waiting for Student Reply (Update Questions)' : 'Step 1: Ask Follow-Up Questions Before Accepting'; ?>
                            </div>
                            <p style="margin: 0 0 0.75rem 0; font-size: 0.76rem; color: #B45309;">
                                <?php echo $has_asked
                                    ? 'Your questions have been sent. Awaiting student reply.'
                                    : 'Ask clarifying questions regarding the student\'s research proposal and evaluate their readiness before accepting.'; ?>
                            </p>

                            <form method="POST" action="action.php" style="display: flex; flex-direction: column; gap: 0.65rem; font-size: 0.8rem;">
                                <input type="hidden" name="consultation_id" value="<?php echo $cid; ?>">
                                <input type="hidden" name="return_tab" value="consultations">
                                <input type="hidden" name="eval_action" value="ask_followup">

                                <div style="display: grid; grid-template-columns: 1fr 90px; gap: 0.5rem;">
                                    <div>
                                        <label style="font-weight: 700; color: #334155; display: block; margin-bottom: 0.2rem;">Student Readiness</label>
                                        <select name="readiness_level" class="form-control" style="padding: 0.42rem; font-size: 0.8rem; font-weight: 600;">
                                            <?php
                                             $r_levels = ['Moderately Ready', 'Ready', 'Needs Clarification', 'Not Yet Ready'];
                                            $cur_rl = $c['readiness_level'] ?? 'Moderately Ready';
                                            foreach ($r_levels as $rl):
                                            ?>
                                                <option value="<?php echo $rl; ?>" <?php echo $cur_rl === $rl ? 'selected' : ''; ?>><?php echo $rl; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label style="font-weight: 700; color: #334155; display: block; margin-bottom: 0.2rem;">Score %</label>
                                        <input type="number" name="readiness_score" min="0" max="100" value="<?php echo $c['readiness_score'] !== null ? (int)$c['readiness_score'] : 80; ?>" class="form-control" style="padding: 0.42rem; font-size: 0.8rem; font-weight: 700;">
                                    </div>
                                </div>

                                <div>
                                    <label style="font-weight: 700; color: #334155; display: block; margin-bottom: 0.2rem;">Questions for Student <span style="color:#DC2626;">*</span></label>
                                    <textarea name="follow_up_questions" rows="2" required class="form-control" placeholder="e.g. What is the primary algorithm and dataset you plan to use? Who are your target beneficiaries?" style="font-size: 0.8rem;"><?php echo htmlspecialchars($c['follow_up_questions'] ?? ''); ?></textarea>
                                </div>

                                <div>
                                    <label style="font-weight: 700; color: #334155; display: block; margin-bottom: 0.2rem;">Initial Adviser Comments (Optional)</label>
                                    <input type="text" name="adviser_preliminary_comments" value="<?php echo htmlspecialchars($c['adviser_preliminary_comments'] ?? ''); ?>" placeholder="Initial feedback on their proposed title..." class="form-control" style="padding: 0.42rem; font-size: 0.8rem;">
                                </div>

                                <button type="submit" name="adviser_evaluate_consultation" class="btn-primary" style="background: #D97706; border-color: #B45309; padding: 0.58rem; font-weight: 800; width: 100%; border-radius: 8px;">
                                    <i class="fa-solid fa-paper-plane"></i> <?php echo $has_asked ? 'Update Questions Sent to Student' : 'Send Questions to Student'; ?>
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- ========================================================================= -->
<!-- SECTION 2: SIMPLE & CLEAN ORAL DEFENSE APPROVAL TABLE                     -->
<!-- ========================================================================= -->
<div class="card" id="defense-clearance-registry" style="border-top: 4px solid #047857; border-radius: 14px; overflow: hidden;">
    <div class="card-header" style="background: #022C22; color: #FFFFFF; padding: 1rem 1.25rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
        <div>
            <h2 style="margin: 0; font-size: 1.02rem; color: #FFFFFF; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-graduation-cap" style="color: #FCD34D;"></i>
                Student Consultation Completion &amp; Oral Defense Approval
            </h2>
            <small style="color: #A7F3D0; font-size: 0.75rem;">
                Confirm student consultation completion and endorse for Oral Defense (IT 198 / IT 199).
            </small>
        </div>
    </div>

    <div class="card-body" style="padding: 0;">
        <?php if (empty($advisees)): ?>
            <p style="text-align: center; color: #64748B; padding: 1.5rem; margin: 0;">No advisees or consulting students linked to your account yet.</p>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.82rem;">
                    <thead>
                        <tr style="background: #F8FAFC; border-bottom: 1.5px solid #E2E8F0; text-align: left; color: #475569; font-size: 0.74rem; text-transform: uppercase;">
                            <th style="padding: 0.85rem 1.1rem;">Student / Group</th>
                            <th style="padding: 0.85rem 1rem;">Research Title</th>
                            <th style="padding: 0.85rem 1rem;">Consultation Status</th>
                            <th style="padding: 0.85rem 1rem;">Oral Defense Status</th>
                            <th style="padding: 0.85rem 1.1rem; text-align: right;">Quick Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($advisees as $sid => $adv):
                            $sum = $adv['summary'];
                            $c_stats = $sum['stats'];
                            $is_proc_done = $sum['is_process_completed'];
                            $is_def_ok = $sum['oral_defense_approved'];
                        ?>
                        <tr style="border-bottom: 1px solid #F1F5F9;">
                            <td style="padding: 0.9rem 1.1rem;">
                                <strong style="color: #0F172A; font-size: 0.88rem;"><?php echo htmlspecialchars($adv['full_name']); ?></strong>
                                <?php if (!empty($adv['group_name'])): ?>
                                    <div style="font-size: 0.74rem; color: #047857; font-weight: 700;">Group: <?php echo htmlspecialchars($adv['group_name']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 0.9rem 1rem; color: #334155; max-width: 280px;">
                                <?php echo htmlspecialchars($adv['thesis_topic'] ?: 'Not specified'); ?>
                                <div style="font-size: 0.72rem; color: #64748B; margin-top: 0.15rem;">
                                    Sessions Completed: <strong><?php echo (int)$c_stats['completed']; ?> / <?php echo (int)$c_stats['total']; ?></strong>
                                </div>
                            </td>
                            <td style="padding: 0.9rem 1rem;">
                                <?php if ($is_proc_done): ?>
                                    <span style="background: #D1FAE5; color: #065F46; padding: 0.22rem 0.65rem; border-radius: 999px; font-size: 0.73rem; font-weight: 800;">
                                        <i class="fa-solid fa-check"></i> Completed
                                    </span>
                                <?php else: ?>
                                    <span style="background: #FEF3C7; color: #92400E; padding: 0.22rem 0.65rem; border-radius: 999px; font-size: 0.73rem; font-weight: 800;">
                                        In Progress
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 0.9rem 1rem;">
                                <?php if ($is_def_ok): ?>
                                    <span style="background: #022C22; color: #FCD34D; padding: 0.25rem 0.7rem; border-radius: 999px; font-size: 0.73rem; font-weight: 800;">
                                        <i class="fa-solid fa-award"></i> Approved (<?php echo htmlspecialchars($sum['oral_defense_stage_approved']); ?>)
                                    </span>
                                <?php else: ?>
                                    <span style="background: #F1F5F9; color: #64748B; padding: 0.22rem 0.65rem; border-radius: 999px; font-size: 0.73rem; font-weight: 700;">
                                        Not Yet Approved
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 0.9rem 1.1rem; text-align: right;">
                                <form method="POST" action="action.php" style="display: inline-flex; align-items: center; gap: 0.4rem; flex-wrap: wrap; justify-content: flex-end; margin: 0;">
                                    <input type="hidden" name="student_id" value="<?php echo $sid; ?>">
                                    <input type="hidden" name="return_tab" value="consultations">
                                    <input type="hidden" name="confirm_consultation_checkbox" value="1">
                                    <input type="hidden" name="oral_defense_endorsement_remarks" value="Verified completed consultation process.">
                                    <input type="hidden" name="consultation_completion_notes" value="Verified completed consultation process.">

                                    <select name="oral_defense_stage_approved" style="padding: 0.35rem 0.55rem; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 0.75rem; font-weight: 700;">
                                        <option value="Proposal Oral Defense (IT 198)" <?php echo ($sum['oral_defense_stage_approved'] === 'Proposal Oral Defense (IT 198)') ? 'selected' : ''; ?>>IT 198 Proposal</option>
                                        <option value="Final Oral Defense (IT 199)" <?php echo ($sum['oral_defense_stage_approved'] === 'Final Oral Defense (IT 199)') ? 'selected' : ''; ?>>IT 199 Final</option>
                                    </select>

                                    <input type="hidden" name="action_mode" value="approve_oral_defense">
                                    <button type="submit" name="adviser_confirm_consultation_and_defense" style="background: #047857; color: #FFFFFF; border: none; padding: 0.38rem 0.75rem; border-radius: 6px; font-size: 0.75rem; font-weight: 800; cursor: pointer;">
                                        <i class="fa-solid fa-check-double"></i> <?php echo $is_def_ok ? 'Update Approval' : 'Approve Defense'; ?>
                                    </button>

                                    <a href="export_consultation_form.php?student_id=<?php echo $sid; ?>" target="_blank" style="background: #F8FAFC; border: 1px solid #CBD5E1; color: #0F172A; padding: 0.36rem 0.65rem; border-radius: 6px; font-size: 0.75rem; font-weight: 700; text-decoration: none;">
                                        <i class="fa-solid fa-print"></i> Form
                                    </a>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function decrementSidebarConsultationBadge(newExactCount) {
    const badge = document.getElementById('sidebarConsultationsBadge');
    if (!badge) return;

    let currentCount = parseInt(badge.textContent.trim(), 10);
    if (isNaN(currentCount)) currentCount = 0;

    const updatedCount = (typeof newExactCount === 'number' && !isNaN(newExactCount))
        ? newExactCount
        : Math.max(0, currentCount - 1);

    if (updatedCount <= 0) {
        badge.style.display = 'none';
        badge.remove();
    } else {
        badge.textContent = String(updatedCount);
    }
}

function markSingleConsultationSeen(consId, cardEl) {
    if (!cardEl || cardEl.getAttribute('data-unread') !== '1') return;

    // Immediately mark card as read in UI & decrement sidebar badge by 1
    cardEl.setAttribute('data-unread', '0');
    cardEl.style.borderColor = '#E2E8F0';
    cardEl.style.boxShadow = 'none';
    cardEl.style.cursor = 'default';

    const newBadge = document.getElementById('cons-new-badge-' + consId);
    if (newBadge) {
        newBadge.style.background = '#ECFDF5';
        newBadge.style.color = '#047857';
        newBadge.style.boxShadow = 'none';
        newBadge.innerHTML = '<i class="fa-solid fa-check"></i> Viewed';
        setTimeout(() => { if (newBadge && newBadge.parentNode) newBadge.remove(); }, 1200);
    }

    // Decrement badge counter by 1 immediately (and hide/remove when 0)
    decrementSidebarConsultationBadge();

    // Persist in database for this single consultation item
    const fd = new FormData();
    fd.append('action', 'mark_consultation_seen');
    fd.append('cons_id', consId);
    fetch('api.php', { method: 'POST', body: fd, keepalive: true })
        .then(r => r.json())
        .then(data => {
            if (data && data.success && typeof data.remaining_count === 'number') {
                const badge = document.getElementById('sidebarConsultationsBadge');
                if (data.remaining_count <= 0) {
                    if (badge) {
                        badge.style.display = 'none';
                        badge.remove();
                    }
                } else if (badge) {
                    badge.textContent = String(data.remaining_count);
                }
            }
        })
        .catch(err => console.error(err));
}
</script>
