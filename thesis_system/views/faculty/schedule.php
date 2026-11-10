<?php
// FACULTY / ADVISER SCHEDULE MEETINGS VIEW
// Dedicated exclusively to Adviser-Advisee Meeting & Consultation Scheduling

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'adviser') {
    exit('Access Denied');
}

$uid = (int)$_SESSION['user_id'];

if (function_exists('ensure_consultation_schema')) {
    ensure_consultation_schema($conn);
}

// 1. Fetch all accepted advisees (plus pending advisees in consultation) for this faculty member
$advisees_q = $conn->query("
    SELECT DISTINCT u.id as student_id, u.name as student_name, u.email,
           sp.thesis_topic, sp.department, sp.student_id as student_no, sp.group_id,
           cg.group_name, cg.thesis_topic as group_topic,
           mr.status as match_status
    FROM match_request mr
    JOIN user u ON mr.student_id = u.id
    LEFT JOIN student_profile sp ON u.id = sp.user_id
    LEFT JOIN capstone_group cg ON sp.group_id = cg.id
    WHERE mr.adviser_id = $uid AND mr.status IN ('accepted', 'pending')
    ORDER BY FIELD(mr.status, 'accepted', 'pending'), u.name ASC
");

$advisee_list = [];
if ($advisees_q) {
    while ($adv = $advisees_q->fetch_assoc()) {
        $advisee_list[] = $adv;
    }
}

// 2. Fetch Scheduled Advisee Meetings & Consultation Sessions for this Adviser
$meetings_q = $conn->query("
    SELECT ac.*,
           u.name as student_name, u.email as student_email,
           sp.student_id as student_no, sp.department as student_dept, sp.thesis_topic as student_topic,
           cg.group_name
    FROM adviser_consultations ac
    JOIN user u ON ac.student_id = u.id
    LEFT JOIN student_profile sp ON u.id = sp.user_id
    LEFT JOIN capstone_group cg ON ac.group_id = cg.id
    WHERE ac.adviser_id = $uid AND ac.status != 'cancelled'
    ORDER BY 
        CASE WHEN ac.preferred_date >= CURDATE() AND ac.status IN ('accepted', 'pending_review', 'follow_up_required', 'student_responded') THEN 0 ELSE 1 END ASC,
        ac.preferred_date DESC,
        ac.id DESC
");

$scheduled_meetings = [];
$upcoming_meetings_count = 0;
$completed_meetings_count = 0;
$pending_meetings_count = 0;

if ($meetings_q) {
    while ($m = $meetings_q->fetch_assoc()) {
        $scheduled_meetings[] = $m;
        if ($m['status'] === 'completed') {
            $completed_meetings_count++;
        } elseif ($m['status'] === 'accepted') {
            $upcoming_meetings_count++;
        } elseif (in_array($m['status'], ['pending_review', 'follow_up_required', 'student_responded'])) {
            $pending_meetings_count++;
        }
    }
}
?>

<div class="fac-sched-wrapper">
    <!-- Header Banner (Properly closed!) -->
    <div class="banner" style="background: linear-gradient(135deg, var(--csu-green-darker) 0%, var(--csu-green-dark) 60%, var(--csu-green) 100%); border-radius: 14px; padding: 1.4rem 1.8rem; color: white; margin-bottom: 1.5rem; box-shadow: var(--shadow-sm); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="background: rgba(16, 185, 129, 0.25); color: #A7F3D0; border: 1px solid rgba(16, 185, 129, 0.45); padding: 0.2rem 0.65rem; border-radius: 999px; font-size: 0.72rem; font-weight: 800; text-transform: uppercase; display: inline-flex; align-items: center; gap: 0.35rem; margin-bottom: 0.35rem;">
                <i class="fa-solid fa-handshake-angle"></i> Advising & Consultation Coordinator
            </div>
            <h1 style="font-size: 1.5rem; font-weight: 800; margin: 0; color: #FFFFFF;">Schedule Advisee Meetings</h1>
            <p style="margin: 0.25rem 0 0 0; color: #D1FAE5; font-size: 0.88rem;">Set up 1-on-1 or group advising sessions, send meeting invitations, and manage your scheduled consultation calendar.</p>
        </div>
        <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
            <div style="background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.25); border-radius: 10px; padding: 0.6rem 1.05rem; text-align: center;">
                <div style="font-size: 1.3rem; font-weight: 900; color: #A7F3D0; line-height: 1;"><?php echo $upcoming_meetings_count; ?></div>
                <small style="color: #D1FAE5; font-size: 0.71rem; font-weight: 700;">Confirmed Meetings</small>
            </div>
            <div style="background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.25); border-radius: 10px; padding: 0.6rem 1.05rem; text-align: center;">
                <div style="font-size: 1.3rem; font-weight: 900; color: #FDE68A; line-height: 1;"><?php echo $pending_meetings_count; ?></div>
                <small style="color: #FEF3C7; font-size: 0.71rem; font-weight: 700;">Pending Requests</small>
            </div>
            <div style="background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.25); border-radius: 10px; padding: 0.6rem 1.05rem; text-align: center;">
                <div style="font-size: 1.3rem; font-weight: 900; color: #BAE6FD; line-height: 1;"><?php echo $completed_meetings_count; ?></div>
                <small style="color: #E0F2FE; font-size: 0.71rem; font-weight: 700;">Completed</small>
            </div>
            <a href="?tab=defense_schedules" style="background: #FFFFFF; color: #064E3B; padding: 0.65rem 1.1rem; border-radius: 10px; font-size: 0.82rem; font-weight: 800; text-decoration: none; display: inline-flex; align-items: center; gap: 0.45rem; box-shadow: 0 2px 6px rgba(0,0,0,0.12);">
                <i class="fa-solid fa-gavel" style="color: #047857;"></i> View Defense Schedules
            </a>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: minmax(340px, 430px) 1fr; gap: 1.5rem; align-items: start;">
        
        <!-- LEFT COLUMN: SCHEDULE A NEW ADVISEE MEETING FORM -->
        <div class="card" style="margin-bottom: 0; border: 1px solid #E2E8F0; border-radius: 14px; overflow: hidden;">
            <div class="card-header" style="background: #F8FAFC; border-bottom: 1px solid #E2E8F0; padding: 1.05rem 1.25rem; display: flex; align-items: center; gap: 0.65rem;">
                <div style="width: 38px; height: 38px; border-radius: 10px; background: #ECFDF5; color: #047857; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; border: 1px solid #A7F3D0;">
                    <i class="fa-solid fa-calendar-plus"></i>
                </div>
                <div>
                    <h2 style="font-size: 1.05rem; font-weight: 800; color: #0F172A; margin: 0;">Schedule New Advisee Meeting</h2>
                    <span style="font-size: 0.76rem; color: #64748B;">Send an official meeting schedule & notification</span>
                </div>
            </div>

            <div class="card-body" style="padding: 1.25rem;">
                <form method="POST" action="action.php" style="display: flex; flex-direction: column; gap: 1rem;">
                    <input type="hidden" name="schedule_meeting" value="1">
                    
                    <div>
                        <label style="font-size: 0.82rem; font-weight: 800; color: #0F172A; display: block; margin-bottom: 0.35rem;">
                            <i class="fa-solid fa-user-graduate" style="color: #047857;"></i> Advisee / Research Team <span style="color:#DC2626;">*</span>
                        </label>
                        <select name="student_id" required style="width: 100%; padding: 0.62rem 0.8rem; border: 1.5px solid #CBD5E1; border-radius: 8px; font-size: 0.86rem; font-weight: 600; color: #1E293B; background: #FFFFFF;">
                            <option value="" disabled selected>-- Select advisee or research group --</option>
                            <?php foreach($advisee_list as $adv): 
                                $t_topic = !empty($adv['group_topic']) ? $adv['group_topic'] : ($adv['thesis_topic'] ?? '');
                            ?>
                                <option value="<?php echo (int)$adv['student_id']; ?>">
                                    <?php echo htmlspecialchars($adv['student_name'] . (!empty($adv['group_name']) ? " • Group {$adv['group_name']}" : "")); ?>
                                    <?php echo ($adv['match_status'] === 'pending') ? ' [Pending Request]' : ''; ?>
                                    <?php if(!empty($t_topic)): ?> — "<?php echo htmlspecialchars(mb_strimwidth($t_topic, 0, 34, '...')); ?>"<?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if(empty($advisee_list)): ?>
                            <small style="color: #B45309; font-size: 0.75rem; display: block; margin-top: 0.3rem;">
                                <i class="fa-solid fa-circle-info"></i> You have no active or pending advisees yet.
                            </small>
                        <?php endif; ?>
                    </div>

                    <div>
                        <label style="font-size: 0.82rem; font-weight: 800; color: #0F172A; display: block; margin-bottom: 0.35rem;">
                            <i class="fa-solid fa-layer-group" style="color: #0284C7;"></i> Meeting Purpose / Stage <span style="color:#DC2626;">*</span>
                        </label>
                        <select name="consultation_stage" required style="width: 100%; padding: 0.62rem 0.8rem; border: 1.5px solid #CBD5E1; border-radius: 8px; font-size: 0.86rem; font-weight: 600; color: #1E293B; background: #FFFFFF;">
                            <option value="Adviser-Scheduled Progress Meeting">Adviser-Scheduled Progress Meeting</option>
                            <option value="Initial Research Idea / Concept Consultation">Initial Research Idea / Concept Consultation</option>
                            <option value="Chapter 1-3 Proposal Manuscript Review">Chapter 1–3 Proposal Manuscript Review</option>
                            <option value="Pre-Oral Defense Readiness Check (IT 198)">Pre-Oral Defense Readiness Check (IT 198)</option>
                            <option value="System Prototype & Implementation Review">System Prototype & Implementation Review</option>
                            <option value="Final Manuscript & Pre-Final Defense Check (IT 199)">Final Manuscript & Pre-Final Defense Check (IT 199)</option>
                            <option value="Post-Defense Revision Follow-Up">Post-Defense Revision Follow-Up</option>
                        </select>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                        <div>
                            <label style="font-size: 0.82rem; font-weight: 800; color: #0F172A; display: block; margin-bottom: 0.35rem;">
                                <i class="fa-regular fa-calendar-days" style="color: #047857;"></i> Date & Time <span style="color:#DC2626;">*</span>
                            </label>
                            <input type="datetime-local" name="meeting_time" required min="<?php echo date('Y-m-d\TH:i'); ?>" style="width: 100%; padding: 0.6rem 0.7rem; border: 1.5px solid #CBD5E1; border-radius: 8px; font-size: 0.84rem; font-weight: 600;">
                        </div>
                        <div>
                            <label style="font-size: 0.82rem; font-weight: 800; color: #0F172A; display: block; margin-bottom: 0.35rem;">
                                <i class="fa-solid fa-video" style="color: #7C3AED;"></i> Meeting Mode <span style="color:#DC2626;">*</span>
                            </label>
                            <select name="consultation_mode" required style="width: 100%; padding: 0.62rem 0.7rem; border: 1.5px solid #CBD5E1; border-radius: 8px; font-size: 0.84rem; font-weight: 600; background: #FFFFFF;">
                                <option value="In-Person">In-Person</option>
                                <option value="Online / Virtual">Online / Virtual</option>
                                <option value="Document / Asynchronous">Asynchronous Review</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label style="font-size: 0.82rem; font-weight: 800; color: #0F172A; display: block; margin-bottom: 0.35rem;">
                            <i class="fa-solid fa-location-dot" style="color: #DC2626;"></i> Room / Venue or Google Meet Link <span style="color:#DC2626;">*</span>
                        </label>
                        <input type="text" name="location" placeholder="e.g. CEIT Faculty Room 102 or https://meet.google.com/..." required style="width: 100%; padding: 0.62rem 0.8rem; border: 1.5px solid #CBD5E1; border-radius: 8px; font-size: 0.86rem;">
                    </div>

                    <div>
                        <label style="font-size: 0.82rem; font-weight: 800; color: #0F172A; display: block; margin-bottom: 0.35rem;">
                            <i class="fa-solid fa-list-check" style="color: #D97706;"></i> Meeting Agenda / Instructions for Advisee
                        </label>
                        <textarea name="meeting_agenda" rows="3" placeholder="Specify what the student/group should bring or prepare (e.g., revised Chapter 1-3 draft, prototype demo, response to panel comments)..." style="width: 100%; padding: 0.62rem 0.8rem; border: 1.5px solid #CBD5E1; border-radius: 8px; font-size: 0.84rem; resize: vertical;"></textarea>
                    </div>

                    <button type="submit" class="btn-primary" style="background: linear-gradient(135deg, #047857, #065F46); padding: 0.75rem 1.25rem; font-weight: 800; border-radius: 10px; width: 100%; justify-content: center; font-size: 0.9rem; box-shadow: 0 4px 10px rgba(4, 120, 87, 0.22);">
                        <i class="fa-solid fa-paper-plane"></i> Schedule Meeting & Notify Advisee
                    </button>
                </form>
            </div>
        </div>

        <!-- RIGHT COLUMN: SCHEDULED ADVISEE MEETINGS & CONSULTATION CALENDAR -->
        <div class="card" style="margin-bottom: 0; border: 1px solid #E2E8F0; border-radius: 14px; overflow: hidden;">
            <div class="card-header" style="background: #F8FAFC; border-bottom: 1px solid #E2E8F0; padding: 1.05rem 1.25rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
                <div style="display: flex; align-items: center; gap: 0.65rem;">
                    <div style="width: 38px; height: 38px; border-radius: 10px; background: #EFF6FF; color: #1D4ED8; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; border: 1px solid #BFDBFE;">
                        <i class="fa-solid fa-calendar-week"></i>
                    </div>
                    <div>
                        <h2 style="font-size: 1.05rem; font-weight: 800; color: #0F172A; margin: 0;">Scheduled Advisee Meetings & Sessions</h2>
                        <span style="font-size: 0.76rem; color: #64748B;">Track upcoming advising appointments, pending requests, and completed sessions</span>
                    </div>
                </div>
                <a href="?tab=consultations" style="font-size: 0.78rem; font-weight: 800; color: #047857; background: #ECFDF5; border: 1px solid #A7F3D0; padding: 0.4rem 0.8rem; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem;">
                    <i class="fa-solid fa-clipboard-check"></i> Open Full Consultation Log
                </a>
            </div>

            <!-- Filter Bar -->
            <div style="background: #FFFFFF; border-bottom: 1px solid #E2E8F0; padding: 0.75rem 1.25rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.65rem;">
                <div style="display: flex; gap: 0.35rem; flex-wrap: wrap;">
                    <button type="button" class="mtg-filter-btn active" onclick="filterMeetings('all', this)" style="padding: 0.38rem 0.75rem; border-radius: 8px; font-size: 0.78rem; font-weight: 800; border: 1.5px solid #064E3B; background: #064E3B; color: #FFFFFF; cursor: pointer;">
                        All (<?php echo count($scheduled_meetings); ?>)
                    </button>
                    <button type="button" class="mtg-filter-btn" onclick="filterMeetings('accepted', this)" style="padding: 0.38rem 0.75rem; border-radius: 8px; font-size: 0.78rem; font-weight: 800; border: 1.5px solid #CBD5E1; background: #FFFFFF; color: #334155; cursor: pointer;">
                        Confirmed (<?php echo $upcoming_meetings_count; ?>)
                    </button>
                    <button type="button" class="mtg-filter-btn" onclick="filterMeetings('pending', this)" style="padding: 0.38rem 0.75rem; border-radius: 8px; font-size: 0.78rem; font-weight: 800; border: 1.5px solid #CBD5E1; background: #FFFFFF; color: #334155; cursor: pointer;">
                        Pending / Follow-Up (<?php echo $pending_meetings_count; ?>)
                    </button>
                    <button type="button" class="mtg-filter-btn" onclick="filterMeetings('completed', this)" style="padding: 0.38rem 0.75rem; border-radius: 8px; font-size: 0.78rem; font-weight: 800; border: 1.5px solid #CBD5E1; background: #FFFFFF; color: #334155; cursor: pointer;">
                        Completed (<?php echo $completed_meetings_count; ?>)
                    </button>
                </div>

                <div style="position: relative; min-width: 210px;">
                    <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 0.7rem; top: 50%; transform: translateY(-50%); color: #94A3B8; font-size: 0.8rem;"></i>
                    <input type="text" id="mtgSearchInput" placeholder="Search advisee, topic, venue..." oninput="applyMeetingFilter()" style="width: 100%; padding: 0.42rem 0.7rem 0.42rem 1.95rem; border: 1.5px solid #CBD5E1; border-radius: 8px; font-size: 0.8rem; outline: none;">
                </div>
            </div>

            <div class="card-body" style="padding: 1.15rem; max-height: 680px; overflow-y: auto;">
                <?php if(empty($scheduled_meetings)): ?>
                    <div style="text-align: center; padding: 2.75rem 1.25rem; background: #F8FAFC; border: 2px dashed #E2E8F0; border-radius: 12px;">
                        <i class="fa-regular fa-calendar-plus" style="font-size: 2.5rem; color: #94A3B8; margin-bottom: 0.65rem;"></i>
                        <h3 style="font-size: 1.05rem; font-weight: 800; color: #1E293B; margin: 0 0 0.3rem 0;">No Advisee Meetings Scheduled Yet</h3>
                        <p style="font-size: 0.84rem; color: #64748B; margin: 0;">Use the form on the left to schedule a meeting with any of your advisees or capstone groups.</p>
                    </div>
                <?php else: ?>
                    <div id="scheduledMeetingsList" style="display: flex; flex-direction: column; gap: 0.85rem;">
                        <?php foreach($scheduled_meetings as $mtg): 
                            $st = $mtg['status'];
                            $filter_group = ($st === 'completed') ? 'completed' : (($st === 'accepted') ? 'accepted' : 'pending');
                            
                            $badge_bg = '#FEF3C7'; $badge_col = '#B45309'; $badge_lbl = 'Pending Review';
                            if ($st === 'accepted') { $badge_bg = '#ECFDF5'; $badge_col = '#047857'; $badge_lbl = 'Confirmed Meeting'; }
                            elseif ($st === 'completed') { $badge_bg = '#EFF6FF'; $badge_col = '#1D4ED8'; $badge_lbl = 'Completed'; }
                            elseif ($st === 'follow_up_required') { $badge_bg = '#FFF7ED'; $badge_col = '#C2410C'; $badge_lbl = 'Follow-Up Asked'; }
                            elseif ($st === 'student_responded') { $badge_bg = '#F3E8FF'; $badge_col = '#6D28D9'; $badge_lbl = 'Student Replied'; }
                            elseif ($st === 'declined') { $badge_bg = '#FEF2F2'; $badge_col = '#DC2626'; $badge_lbl = 'Declined'; }

                            $disp_date = !empty($mtg['preferred_date']) ? date('M d, Y (D)', strtotime($mtg['preferred_date'])) : 'Date TBA';
                            $disp_time = !empty($mtg['confirmed_schedule']) ? $mtg['confirmed_schedule'] : (!empty($mtg['preferred_time']) ? $mtg['preferred_time'] : 'Flexible');
                            $venue = !empty($mtg['venue_or_link']) ? $mtg['venue_or_link'] : 'CEIT Faculty Consultation Room';
                            $is_url = filter_var($venue, FILTER_VALIDATE_URL) || stripos($venue, 'meet.google.com') !== false || stripos($venue, 'zoom.us') !== false;
                            $adv_display = $mtg['student_name'] . (!empty($mtg['group_name']) ? " (Group {$mtg['group_name']})" : "");
                            $search_blob = strtolower($adv_display . ' ' . ($mtg['research_title'] ?? '') . ' ' . ($mtg['consultation_stage'] ?? '') . ' ' . $venue . ' ' . ($mtg['reference_no'] ?? ''));
                        ?>
                            <div class="scheduled-mtg-card"
                                 data-status-group="<?php echo htmlspecialchars($filter_group); ?>"
                                 data-search="<?php echo htmlspecialchars($search_blob); ?>"
                                 style="background: #FFFFFF; border: 1.5px solid #E2E8F0; border-left: 5px solid <?php echo $badge_col; ?>; border-radius: 12px; padding: 1rem 1.15rem; display: flex; flex-direction: column; gap: 0.65rem; box-shadow: 0 1px 4px rgba(15,23,42,0.04);">
                                
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 0.75rem; flex-wrap: wrap;">
                                    <div style="flex: 1; min-width: 220px;">
                                        <div style="display: flex; align-items: center; gap: 0.45rem; flex-wrap: wrap; margin-bottom: 0.3rem;">
                                            <span style="background: <?php echo $badge_bg; ?>; color: <?php echo $badge_col; ?>; font-size: 0.71rem; font-weight: 800; padding: 0.16rem 0.55rem; border-radius: 999px;">
                                                <?php echo htmlspecialchars($badge_lbl); ?>
                                            </span>
                                            <span style="background: #F1F5F9; color: #475569; font-size: 0.71rem; font-weight: 700; padding: 0.16rem 0.55rem; border-radius: 999px;">
                                                <i class="fa-solid fa-laptop-file"></i> <?php echo htmlspecialchars($mtg['consultation_mode'] ?? 'In-Person'); ?>
                                            </span>
                                            <?php if(!empty($mtg['reference_no'])): ?>
                                                <span style="font-size: 0.7rem; font-weight: 700; color: #64748B;">#<?php echo htmlspecialchars($mtg['reference_no']); ?></span>
                                            <?php endif; ?>
                                        </div>

                                        <div style="font-size: 0.74rem; font-weight: 800; color: #047857; text-transform: uppercase; letter-spacing: 0.03em;">
                                            <?php echo htmlspecialchars($mtg['consultation_stage']); ?>
                                        </div>
                                        <h4 style="font-size: 0.96rem; font-weight: 800; color: #0F172A; margin: 0.15rem 0;">
                                            <?php echo htmlspecialchars($mtg['research_title']); ?>
                                        </h4>
                                        <div style="font-size: 0.81rem; color: #475569;">
                                            <i class="fa-solid fa-user-graduate" style="color: #047857;"></i> Advisee: <strong><?php echo htmlspecialchars($adv_display); ?></strong>
                                        </div>
                                    </div>

                                    <!-- Date, Time & Location Pill -->
                                    <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 9px; padding: 0.6rem 0.85rem; font-size: 0.79rem; color: #334155; min-width: 205px; text-align: right;">
                                        <div style="font-weight: 800; color: #0F172A;">
                                            <i class="fa-regular fa-calendar" style="color: #047857;"></i> <?php echo htmlspecialchars($disp_date); ?>
                                        </div>
                                        <div style="margin-top: 0.18rem; color: #475569; font-weight: 600;">
                                            <i class="fa-regular fa-clock" style="color: #0284C7;"></i> <?php echo htmlspecialchars($disp_time); ?>
                                        </div>
                                        <div style="margin-top: 0.22rem;">
                                            <?php if($is_url): ?>
                                                <a href="<?php echo htmlspecialchars(stripos($venue, 'http') === 0 ? $venue : 'https://' . $venue); ?>" target="_blank" style="color: #0284C7; font-weight: 800; text-decoration: none;">
                                                    <i class="fa-solid fa-video"></i> Join Online Meeting
                                                </a>
                                            <?php else: ?>
                                                <span style="color: #475569;"><i class="fa-solid fa-location-dot" style="color: #DC2626;"></i> <?php echo htmlspecialchars($venue); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <?php if(!empty($mtg['consultation_agenda'])): ?>
                                    <div style="background: #F8FAFC; border-radius: 8px; padding: 0.5rem 0.75rem; font-size: 0.79rem; color: #334155; border: 1px solid #E2E8F0;">
                                        <strong style="color: #0F172A;"><i class="fa-solid fa-list-check" style="color: #047857;"></i> Agenda:</strong>
                                        <?php echo nl2br(htmlspecialchars(mb_strimwidth($mtg['consultation_agenda'], 0, 220, '...'))); ?>
                                    </div>
                                <?php endif; ?>

                                <div style="display: flex; justify-content: flex-end; gap: 0.5rem; flex-wrap: wrap; padding-top: 0.2rem;">
                                    <a href="export_consultation_form.php?consultation_id=<?php echo (int)$mtg['id']; ?>" target="_blank" style="font-size: 0.75rem; font-weight: 700; color: #334155; background: #F1F5F9; border: 1px solid #CBD5E1; padding: 0.32rem 0.7rem; border-radius: 7px; text-decoration: none; display: inline-flex; align-items: center; gap: 0.3rem;">
                                        <i class="fa-solid fa-print"></i> Print Form
                                    </a>
                                    <a href="?tab=consultations&cons_id=<?php echo (int)$mtg['id']; ?>#consultation-card-<?php echo (int)$mtg['id']; ?>" style="font-size: 0.75rem; font-weight: 800; color: #047857; background: #ECFDF5; border: 1px solid #A7F3D0; padding: 0.32rem 0.75rem; border-radius: 7px; text-decoration: none; display: inline-flex; align-items: center; gap: 0.3rem;">
                                        <i class="fa-solid fa-arrow-up-right-from-square"></i> Manage Session & Outcome
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
let currentMtgFilter = 'all';

function filterMeetings(group, btnEl) {
    currentMtgFilter = group;
    document.querySelectorAll('.mtg-filter-btn').forEach(btn => {
        btn.style.background = '#FFFFFF';
        btn.style.color = '#334155';
        btn.style.borderColor = '#CBD5E1';
    });
    if (btnEl) {
        btnEl.style.background = '#064E3B';
        btnEl.style.color = '#FFFFFF';
        btnEl.style.borderColor = '#064E3B';
    }
    applyMeetingFilter();
}

function applyMeetingFilter() {
    const q = (document.getElementById('mtgSearchInput') ? document.getElementById('mtgSearchInput').value.trim().toLowerCase() : '');
    document.querySelectorAll('.scheduled-mtg-card').forEach(card => {
        const grp = card.getAttribute('data-status-group') || '';
        const srch = card.getAttribute('data-search') || '';
        const matchGrp = (currentMtgFilter === 'all' || grp === currentMtgFilter);
        const matchSrch = (q === '' || srch.includes(q));
        card.style.display = (matchGrp && matchSrch) ? 'flex' : 'none';
    });
}
</script>
