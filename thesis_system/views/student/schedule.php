<?php
// STUDENT DEFENSE SCHEDULE VIEW
// Displays assigned Oral / Final Defense date, time, venue, committee panel, adviser, guidelines, and countdown

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    exit('Access Denied');
}

$uid = (int)$_SESSION['user_id'];

// Check student group
$sp = $conn->query("SELECT group_id, thesis_topic, department FROM student_profile WHERE user_id = $uid")->fetch_assoc();
$group_id = !empty($sp['group_id']) ? (int)$sp['group_id'] : 0;

$where_cond = $group_id > 0 ? "(dm.student_id = $uid OR dm.group_id = $group_id)" : "dm.student_id = $uid";

$all_student_defenses = [];
$schedule_res = $conn->query("
    SELECT dm.*, 
           chair.name as chair_name,
           adv.name as adviser_user_name,
           cg.group_name,
           (SELECT dmm.decision_verdict FROM defense_meeting_minutes dmm WHERE dmm.meeting_id = dm.id LIMIT 1) as mom_verdict,
           (SELECT dmm.id FROM defense_meeting_minutes dmm WHERE dmm.meeting_id = dm.id LIMIT 1) as mom_id
    FROM defense_meetings dm
    LEFT JOIN user chair ON dm.panel_chair_id = chair.id
    LEFT JOIN user adv ON dm.adviser_id = adv.id
    LEFT JOIN capstone_group cg ON dm.group_id = cg.id
    WHERE $where_cond
    ORDER BY dm.meeting_date DESC, dm.id DESC
");

if ($schedule_res) {
    while($row = $schedule_res->fetch_assoc()) {
        $row['computed_ay'] = !empty($row['academic_year']) ? $row['academic_year'] : calculate_academic_year($row['meeting_date']);
        $row['computed_cat'] = !empty($row['defense_category']) ? $row['defense_category'] : resolve_defense_category($row['defense_stage']);
        $row['computed_cc'] = !empty($row['course_code']) ? $row['course_code'] : resolve_course_code($row['defense_stage']);
        $all_student_defenses[] = $row;
    }
}

$selected_defense_id = isset($_GET['sched_id']) ? (int)$_GET['sched_id'] : 0;
$schedule = null;
if ($selected_defense_id > 0) {
    foreach ($all_student_defenses as $ds_item) {
        if ((int)$ds_item['id'] === $selected_defense_id) {
            $schedule = $ds_item;
            break;
        }
    }
}
if (!$schedule && !empty($all_student_defenses)) {
    $schedule = $all_student_defenses[0];
}

// Fetch assigned panel members
$panels_list = [];
if ($schedule) {
    if (!empty($schedule['panel_members'])) {
        $dec = json_decode($schedule['panel_members'], true);
        if (is_array($dec)) $panels_list = $dec;
        else $panels_list = explode(',', $schedule['panel_members']);
    } else {
        $p_q = $conn->query("SELECT u.name FROM thesis_panel tp JOIN user u ON tp.panel_id = u.id WHERE tp.student_id = {$schedule['student_id']}");
        if ($p_q) {
            while ($pr = $p_q->fetch_assoc()) $panels_list[] = $pr['name'];
        }
    }
}
?>

<div class="student-sched-container">
    <?php if(!$schedule): ?>
        <!-- No Schedule Assigned Yet -->
        <div class="no-sched-card">
            <div class="no-sched-icon">
                <i class="fa-regular fa-calendar-xmark"></i>
            </div>
            <h2>Defense Schedule Pending</h2>
            <p>The CEIT Administration has not yet scheduled your defense session. Once an oral or final defense is officially assigned, your date, time, venue, committee panelists, and presentation guidelines will be displayed here.</p>
            <div class="pending-checklist-box">
                <div class="checklist-title"><i class="fa-solid fa-list-check"></i> While Waiting, Ensure You Have Prepared:</div>
                <div class="checklist-grid">
                    <div class="check-item"><i class="fa-solid fa-circle-check"></i> Approved Capstone Title & Concept</div>
                    <div class="check-item"><i class="fa-solid fa-circle-check"></i> Manuscript Draft Reviewed by Adviser</div>
                    <div class="check-item"><i class="fa-solid fa-circle-check"></i> 15-Minute PowerPoint Slide Presentation</div>
                    <div class="check-item"><i class="fa-solid fa-circle-check"></i> Working Demonstration / Prototype</div>
                </div>
            </div>
            <div style="margin-top: 1.5rem;">
                <a href="?tab=overview" class="btn-return"><i class="fa-solid fa-arrow-left"></i> Return to Overview</a>
            </div>
        </div>
    <?php else: 
        $date_ts = strtotime($schedule['meeting_date']);
        $start_time_ts = !empty($schedule['start_time']) ? strtotime($schedule['start_time']) : null;
        $end_time_ts = !empty($schedule['end_time']) ? strtotime($schedule['end_time']) : null;
        $formatted_date = date('l, F j, Y', $date_ts);
        $time_text = $start_time_ts ? date('g:i A', $start_time_ts) . ($end_time_ts ? ' - ' . date('g:i A', $end_time_ts) : '') : 'Time: To Be Announced';
        
        $today_mid = strtotime(date('Y-m-d'));
        $days_diff = round(($date_ts - $today_mid) / (60 * 60 * 24));
        
        $target_datetime = $schedule['meeting_date'] . ' ' . ($schedule['start_time'] ?? '09:00:00');

        $stu_ay = !empty($schedule['academic_year']) ? $schedule['academic_year'] : calculate_academic_year($schedule['meeting_date']);
        $stu_cat = !empty($schedule['defense_category']) ? $schedule['defense_category'] : resolve_defense_category($schedule['defense_stage'], '');
        $stu_cc = !empty($schedule['course_code']) ? $schedule['course_code'] : resolve_course_code($schedule['defense_stage'], ($stu_cat === 'Proposal Defense' ? 'IT 198' : 'IT 199'));
        $is_prop_stu = ($stu_cc === 'IT 198' || $stu_cat === 'Proposal Defense');
    ?>

        <?php if(count($all_student_defenses) > 1): ?>
            <!-- Defense Session & Academic Year Switcher Bar -->
            <div style="background: #FFFFFF; border: 1.5px solid #CBD5E1; border-radius: 12px; padding: 0.85rem 1.25rem; margin-bottom: 1.25rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem; box-shadow: 0 2px 6px rgba(0,0,0,0.04);">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <div style="width: 32px; height: 32px; border-radius: 8px; background: #ECFDF5; color: #047857; display: flex; align-items: center; justify-content: center; font-size: 0.95rem;">
                        <i class="fa-solid fa-layer-group"></i>
                    </div>
                    <div>
                        <strong style="font-size: 0.88rem; color: #0F172A; display: block;">Your Registered Defenses:</strong>
                        <span style="font-size: 0.74rem; color: #64748B;">Select to view details for Proposal vs Final Defense</span>
                    </div>
                </div>

                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
                    <?php foreach($all_student_defenses as $s_item): 
                        $is_curr = ((int)$s_item['id'] === (int)$schedule['id']);
                        $s_cc = $s_item['computed_cc'];
                        $s_cat = $s_item['computed_cat'];
                        $s_ay = $s_item['computed_ay'];
                        $is_p = ($s_cc === 'IT 198' || $s_cat === 'Proposal Defense');
                    ?>
                        <a href="?tab=schedule&sched_id=<?php echo $s_item['id']; ?>" 
                           style="text-decoration: none; padding: 0.45rem 0.85rem; border-radius: 8px; font-size: 0.82rem; font-weight: 800; display: inline-flex; align-items: center; gap: 0.45rem; border: 1.5px solid <?php echo $is_curr ? ($is_p ? '#0284C7' : '#059669') : '#CBD5E1'; ?>; background: <?php echo $is_curr ? ($is_p ? '#E0F2FE' : '#D1FAE5') : '#F8FAFC'; ?>; color: <?php echo $is_curr ? ($is_p ? '#0369A1' : '#047857') : '#475569'; ?>; transition: all 0.2s;">
                            <i class="fa-solid <?php echo $is_p ? 'fa-file-signature' : 'fa-graduation-cap'; ?>"></i>
                            <?php echo htmlspecialchars($s_cc); ?>: <?php echo htmlspecialchars($s_cat); ?> 
                            <span style="font-weight: 700; opacity: 0.85;">(A.Y. <?php echo htmlspecialchars($s_ay); ?>)</span>
                            <?php if($is_curr): ?><i class="fa-solid fa-circle-check" style="color: <?php echo $is_p ? '#0284C7' : '#059669'; ?>; font-size: 0.78rem;"></i><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Hero Defense Banner -->
        <div class="defense-hero-banner">
            <div class="hero-top-row">
                <!-- Course Code Badge -->
                <span class="badge" style="background: <?= $is_prop_stu ? '#FEF3C7; color: #B45309; border: 1px solid #FDE68A;' : '#E0E7FF; color: #3730A3; border: 1px solid #C7D2FE;' ?> font-size: 0.76rem; font-weight: 800; padding: 0.25rem 0.65rem; border-radius: 999px;">
                    <i class="fa-solid fa-code-branch"></i> <?= htmlspecialchars($stu_cc) ?>
                </span>

                <!-- Defense Category Badge -->
                <span class="badge" style="background: <?= $is_prop_stu ? '#FFF7ED; color: #C2410C; border: 1px solid #FFEDD5;' : '#ECFDF5; color: #047857; border: 1px solid #A7F3D0;' ?> font-size: 0.76rem; font-weight: 800; padding: 0.25rem 0.65rem; border-radius: 999px;">
                    <i class="fa-solid <?= $is_prop_stu ? 'fa-file-signature' : 'fa-graduation-cap' ?>"></i> <?= htmlspecialchars($stu_cat) ?>
                </span>

                <!-- Academic Year (A.Y.) Badge -->
                <span class="badge" style="background: rgba(255,255,255,0.22); color: #FFFFFF; border: 1px solid rgba(255,255,255,0.45); font-size: 0.76rem; font-weight: 800; padding: 0.25rem 0.65rem; border-radius: 999px;">
                    <i class="fa-solid fa-calendar-days"></i> A.Y. <?= htmlspecialchars($stu_ay) ?>
                </span>

                <span class="defense-stage-badge">
                    <i class="fa-solid fa-bookmark"></i> <?php echo htmlspecialchars($schedule['defense_stage']); ?>
                </span>
                
                <?php if($schedule['status'] === 'in_progress'): ?>
                    <span class="hero-status-tag live"><i class="fa-solid fa-circle-dot fa-fade"></i> Session Currently Live</span>
                <?php elseif($schedule['status'] === 'completed'): ?>
                    <span class="hero-status-tag done"><i class="fa-solid fa-circle-check"></i> Defense Completed</span>
                <?php elseif($days_diff === 0): ?>
                    <span class="hero-status-tag today"><i class="fa-solid fa-bolt"></i> Defense Is Today!</span>
                <?php elseif($days_diff > 0): ?>
                    <span class="hero-status-tag upcoming"><i class="fa-regular fa-clock"></i> In <?php echo $days_diff; ?> Days</span>
                <?php else: ?>
                    <span class="hero-status-tag past"><i class="fa-solid fa-archive"></i> Concluded Session</span>
                <?php endif; ?>
            </div>

            <div style="font-size: 0.76rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; color: <?= $is_prop_stu ? '#FDE68A' : '#A7F3D0' ?>; margin-top: 0.5rem;">
                <i class="fa-solid <?= $is_prop_stu ? 'fa-lightbulb' : 'fa-award' ?>"></i> <?= $is_prop_stu ? 'Proposal Defense Title' : 'Final Defense' ?>
            </div>

            <h1 class="hero-title" style="margin-top: 0.25rem;"><?php echo htmlspecialchars($schedule['title']); ?></h1>
            <p class="hero-subtitle">
                <?php if(!empty($schedule['group_name'])): ?>
                    Capstone Team: <strong>Group <?php echo htmlspecialchars($schedule['group_name']); ?></strong> • 
                <?php endif; ?>
                College of Engineering and Information Technology • Caraga State University
            </p>

            <!-- Live Countdown Box (for upcoming defenses) -->
            <?php if($days_diff >= 0 && $schedule['status'] !== 'completed'): ?>
                <div class="defense-countdown-container">
                    <div class="countdown-title"><i class="fa-solid fa-stopwatch"></i> Countdown to Defense Presentation:</div>
                    <div class="countdown-digits-row" id="countdownRow" data-target="<?php echo $target_datetime; ?>">
                        <div class="countdown-block">
                            <span class="c-num" id="cdDays">00</span>
                            <span class="c-lbl">DAYS</span>
                        </div>
                        <div class="countdown-sep">:</div>
                        <div class="countdown-block">
                            <span class="c-num" id="cdHours">00</span>
                            <span class="c-lbl">HOURS</span>
                        </div>
                        <div class="countdown-sep">:</div>
                        <div class="countdown-block">
                            <span class="c-num" id="cdMinutes">00</span>
                            <span class="c-lbl">MINS</span>
                        </div>
                        <div class="countdown-sep">:</div>
                        <div class="countdown-block">
                            <span class="c-num" id="cdSeconds">00</span>
                            <span class="c-lbl">SECS</span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Two Column Content Grid -->
        <div class="sched-details-grid">
            <!-- Left: Schedule & Venue Card -->
            <div class="sched-info-card">
                <div class="card-section-heading">
                    <i class="fa-solid fa-calendar-check"></i> Session Schedule & Venue
                </div>

                <div class="key-value-list">
                    <div class="kv-item">
                        <div class="kv-icon green"><i class="fa-regular fa-calendar-days"></i></div>
                        <div class="kv-content">
                            <span class="kv-label">Defense Date</span>
                            <span class="kv-value"><strong><?php echo $formatted_date; ?></strong></span>
                        </div>
                    </div>

                    <div class="kv-item">
                        <div class="kv-icon blue"><i class="fa-regular fa-clock"></i></div>
                        <div class="kv-content">
                            <span class="kv-label">Designated Time Slot</span>
                            <span class="kv-value"><strong><?php echo $time_text; ?></strong></span>
                        </div>
                    </div>

                    <div class="kv-item">
                        <div class="kv-icon red"><i class="fa-solid fa-location-dot"></i></div>
                        <div class="kv-content">
                            <span class="kv-label">Official Venue</span>
                            <span class="kv-value"><strong><?php echo htmlspecialchars($schedule['venue'] ?? 'CEIT Defense Hall'); ?></strong></span>
                        </div>
                    </div>

                    <?php if(!empty($schedule['meeting_link'])): ?>
                        <div class="kv-item">
                            <div class="kv-icon purple"><i class="fa-solid fa-video"></i></div>
                            <div class="kv-content">
                                <span class="kv-label">Virtual Video Room</span>
                                <span class="kv-value">
                                    <a href="<?php echo htmlspecialchars($schedule['meeting_link']); ?>" target="_blank" class="link-meeting-btn">
                                        <i class="fa-solid fa-up-right-from-square"></i> Open Meeting Link
                                    </a>
                                </span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Administrative Guidelines / Instructions -->
                <div class="instructions-box">
                    <div class="instr-title"><i class="fa-solid fa-circle-exclamation"></i> Presentation Instructions</div>
                    <p class="instr-text">
                        <?php echo !empty($schedule['notes']) ? nl2br(htmlspecialchars($schedule['notes'])) : 'Please arrive or log in at least 15 minutes before the scheduled time. Wear formal corporate / university attire and have your presentation deck and documentation ready.'; ?>
                    </p>
                </div>
            </div>

            <!-- Right: Panel Committee & Adviser Card -->
            <div class="sched-info-card">
                <div class="card-section-heading">
                    <i class="fa-solid fa-users-viewfinder"></i> Academic Defense Committee
                </div>

                <!-- Committee Chair -->
                <div class="chair-feature-box">
                    <div class="chair-crown-icon"><i class="fa-solid fa-crown"></i></div>
                    <div>
                        <div class="chair-role-label">Panel Committee Chair</div>
                        <div class="chair-name">
                            <?php echo htmlspecialchars(!empty($schedule['chair_name']) ? $schedule['chair_name'] : 'Designation Pending'); ?>
                        </div>
                        <div class="chair-sub">Presiding Officer for Oral Defense & Deliberations</div>
                    </div>
                </div>

                <!-- Panel Members -->
                <div class="committee-members-group">
                    <div class="sub-heading-lbl"><i class="fa-solid fa-user-group"></i> Panel Committee Members</div>
                    <div class="members-chips-container">
                        <?php if(!empty($panels_list)): ?>
                            <?php foreach($panels_list as $pm): ?>
                                <div class="member-badge-card">
                                    <div class="member-avatar"><?php echo strtoupper(substr(trim($pm), 0, 1)); ?></div>
                                    <div class="member-info">
                                        <strong><?php echo htmlspecialchars($pm); ?></strong>
                                        <span>Panel Evaluator</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="font-size: 0.85rem; color: #94A3B8; font-style: italic;">Panel members will be assigned by the CEIT Administration.</div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Capstone Adviser -->
                <div class="adviser-feature-box">
                    <div class="sub-heading-lbl"><i class="fa-solid fa-user-tie"></i> Capstone Adviser</div>
                    <div class="adviser-row">
                        <div class="adviser-avatar"><i class="fa-solid fa-chalkboard-user"></i></div>
                        <div>
                            <div class="adviser-name">
                                <?php echo htmlspecialchars(!empty($schedule['adviser_name']) ? $schedule['adviser_name'] : (!empty($schedule['adviser_user_name']) ? $schedule['adviser_user_name'] : 'Not yet assigned')); ?>
                            </div>
                            <div class="adviser-sub" style="color: #64748B;">Project Mentor & Technical Adviser</div>
                        </div>
                    </div>
                </div>

                <!-- MOM Documenter (Committee Documenter) -->
                <?php 
                    $stu_doc_name = !empty($schedule['mom_documenter_name']) ? $schedule['mom_documenter_name'] : '';
                    if (empty($stu_doc_name) && !empty($schedule['id'])) {
                        $q_doc = $conn->query("SELECT recorded_by_name FROM defense_meeting_minutes WHERE meeting_id = {$schedule['id']} LIMIT 1");
                        if ($q_doc && $q_doc->num_rows > 0) {
                            $stu_doc_name = $q_doc->fetch_assoc()['recorded_by_name'] ?? '';
                        }
                    }
                ?>
                <div class="adviser-feature-box" style="margin-top: 1rem; background: #F0FDF4; border: 1.5px solid #BBF7D0;">
                    <div class="sub-heading-lbl" style="color: #15803D;"><i class="fa-solid fa-pen-nib" style="color: #059669;"></i> MOM Documenter (Committee Documenter)</div>
                    <div class="adviser-row">
                        <div class="adviser-avatar" style="background: #DCFCE7; color: #16A34A;"><i class="fa-solid fa-file-pen"></i></div>
                        <div>
                            <div class="adviser-name" style="color: #064E3B;">
                                <?php echo htmlspecialchars(!empty($stu_doc_name) ? $stu_doc_name : 'Designation Pending'); ?>
                            </div>
                            <div class="adviser-sub" style="color: #16A34A;">Designated Minutes of the Meeting (MOM) Recorder</div>
                        </div>
                    </div>
                </div>

                <!-- If Completed / Deliberated -->
                <?php if(!empty($schedule['mom_verdict'])): ?>
                    <div class="deliberation-result-box">
                        <div class="result-label"><i class="fa-solid fa-gavel"></i> Official Deliberation Verdict</div>
                        <div class="result-verdict"><?php echo htmlspecialchars($schedule['mom_verdict']); ?></div>
                        <div style="margin-top: 0.5rem;">
                            <a href="?tab=panelists#mom_section" class="btn-view-mom">
                                <i class="fa-solid fa-file-signature"></i> View Minutes of the Meeting (MOM)
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
// Real-time Countdown Script
(function() {
    const cdElem = document.getElementById('countdownRow');
    if (!cdElem) return;

    const targetDateStr = cdElem.getAttribute('data-target');
    const targetDate = new Date(targetDateStr.replace(' ', 'T')).getTime();

    function updateCountdown() {
        const now = new Date().getTime();
        const diff = targetDate - now;

        if (diff <= 0) {
            document.getElementById('cdDays').innerText = '00';
            document.getElementById('cdHours').innerText = '00';
            document.getElementById('cdMinutes').innerText = '00';
            document.getElementById('cdSeconds').innerText = '00';
            return;
        }

        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
        const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((diff % (1000 * 60)) / 1000);

        document.getElementById('cdDays').innerText = String(days).padStart(2, '0');
        document.getElementById('cdHours').innerText = String(hours).padStart(2, '0');
        document.getElementById('cdMinutes').innerText = String(minutes).padStart(2, '0');
        document.getElementById('cdSeconds').innerText = String(seconds).padStart(2, '0');
    }

    updateCountdown();
    setInterval(updateCountdown, 1000);
})();
</script>

<style>
/* ── Student Defense Schedule Styles (CSU CEIT Green) ── */
.student-sched-container {
    display: flex;
    flex-direction: column;
    gap: 1.4rem;
}

.no-sched-card {
    background: #FFFFFF;
    border: 2px dashed #CBD5E1;
    border-radius: 16px;
    padding: 3.5rem 2rem;
    text-align: center;
    max-width: 750px;
    margin: 1.5rem auto;
}

.no-sched-icon {
    font-size: 4rem;
    color: #94A3B8;
    margin-bottom: 1rem;
}

.no-sched-card h2 {
    font-size: 1.5rem;
    font-weight: 800;
    color: #0F172A;
    margin: 0 0 0.6rem 0;
}

.no-sched-card p {
    font-size: 0.95rem;
    color: #64748B;
    line-height: 1.6;
    margin: 0 auto 1.5rem auto;
    max-width: 600px;
}

.pending-checklist-box {
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    padding: 1.25rem 1.5rem;
    text-align: left;
    max-width: 620px;
    margin: 0 auto;
}

.checklist-title {
    font-size: 0.88rem;
    font-weight: 800;
    color: #064E3B;
    margin-bottom: 0.85rem;
    display: flex;
    align-items: center;
    gap: 0.45rem;
}

.checklist-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 0.65rem;
}

.check-item {
    font-size: 0.84rem;
    font-weight: 600;
    color: #334155;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.check-item i {
    color: #10B981;
}

.btn-return {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.65rem 1.25rem;
    background: #047857;
    color: white;
    border-radius: 8px;
    font-size: 0.88rem;
    font-weight: 700;
    text-decoration: none;
}

/* Defense Hero Banner */
.defense-hero-banner {
    background: linear-gradient(135deg, #064E3B 0%, #047857 55%, #022C22 100%);
    border-radius: 16px;
    padding: 2rem 2.25rem;
    color: white;
    border: 1px solid #10B981;
    box-shadow: 0 10px 30px -5px rgba(4, 120, 87, 0.4);
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    position: relative;
    overflow: hidden;
}

.hero-top-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.defense-stage-badge {
    background: rgba(16, 185, 129, 0.3);
    color: #A7F3D0;
    border: 1px solid rgba(16, 185, 129, 0.6);
    padding: 0.35rem 0.95rem;
    border-radius: 999px;
    font-size: 0.82rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
}

.hero-status-tag {
    padding: 0.35rem 0.95rem;
    border-radius: 999px;
    font-size: 0.82rem;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
}

.hero-status-tag.upcoming { background: #FEF3C7; color: #92400E; }
.hero-status-tag.today { background: #EF4444; color: #FFFFFF; animation: pulseToday 1.5s infinite; }
.hero-status-tag.live { background: #DC2626; color: #FFFFFF; font-weight: 900; }
.hero-status-tag.done { background: #D1FAE5; color: #065F46; }
.hero-status-tag.past { background: rgba(255,255,255,0.2); color: #FFFFFF; }

.hero-title {
    font-size: 1.65rem;
    font-weight: 800;
    color: #FFFFFF;
    margin: 0;
    line-height: 1.35;
}

.hero-subtitle {
    font-size: 0.92rem;
    color: #D1FAE5;
    margin: 0;
}

/* Countdown Container */
.defense-countdown-container {
    margin-top: 0.75rem;
    background: rgba(2, 44, 34, 0.75);
    border: 1px solid rgba(16, 185, 129, 0.4);
    border-radius: 12px;
    padding: 1rem 1.4rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.countdown-title {
    font-size: 0.9rem;
    font-weight: 700;
    color: #FDE047;
    display: flex;
    align-items: center;
    gap: 0.45rem;
}

.countdown-digits-row {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}

.countdown-block {
    background: #022C22;
    border: 1px solid #10B981;
    border-radius: 8px;
    padding: 0.4rem 0.75rem;
    text-align: center;
    min-width: 58px;
}

.countdown-block .c-num {
    display: block;
    font-size: 1.45rem;
    font-weight: 900;
    color: #FFFFFF;
    line-height: 1.1;
    font-family: monospace;
}

.countdown-block .c-lbl {
    display: block;
    font-size: 0.65rem;
    font-weight: 800;
    color: #6EE7B7;
    letter-spacing: 0.5px;
}

.countdown-sep {
    font-size: 1.4rem;
    font-weight: 800;
    color: #34D399;
}

/* Grid Layout */
.sched-details-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.4rem;
}

@media (max-width: 860px) {
    .sched-details-grid {
        grid-template-columns: 1fr;
    }
}

.sched-info-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 14px;
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}

.card-section-heading {
    font-size: 1.05rem;
    font-weight: 800;
    color: #064E3B;
    border-bottom: 2px solid #F1F5F9;
    padding-bottom: 0.65rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.key-value-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.kv-item {
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
}

.kv-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    flex-shrink: 0;
}

.kv-icon.green { background: #ECFDF5; color: #047857; }
.kv-icon.blue { background: #EFF6FF; color: #2563EB; }
.kv-icon.red { background: #FEF2F2; color: #DC2626; }
.kv-icon.purple { background: #F5F3FF; color: #7C3AED; }

.kv-content {
    display: flex;
    flex-direction: column;
}

.kv-label {
    font-size: 0.76rem;
    font-weight: 700;
    color: #64748B;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.kv-value {
    font-size: 0.98rem;
    color: #0F172A;
    margin-top: 0.15rem;
}

.link-meeting-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    background: #0284C7;
    color: white;
    padding: 0.35rem 0.85rem;
    border-radius: 6px;
    font-size: 0.82rem;
    font-weight: 700;
    text-decoration: none;
    margin-top: 0.2rem;
}

.link-meeting-btn:hover {
    background: #0369A1;
}

.instructions-box {
    background: #F0FDF4;
    border: 1px solid #BBF7D0;
    border-radius: 10px;
    padding: 1rem 1.15rem;
}

.instr-title {
    font-size: 0.84rem;
    font-weight: 800;
    color: #166534;
    margin-bottom: 0.4rem;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.instr-text {
    font-size: 0.86rem;
    color: #15803D;
    line-height: 1.5;
    margin: 0;
}

/* Committee Right Column */
.chair-feature-box {
    background: #FFFBEB;
    border: 1.5px solid #FCD34D;
    border-radius: 12px;
    padding: 1rem 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.chair-crown-icon {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: #FEF3C7;
    color: #D97706;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
    flex-shrink: 0;
}

.chair-role-label {
    font-size: 0.74rem;
    font-weight: 800;
    color: #B45309;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.chair-name {
    font-size: 1.05rem;
    font-weight: 800;
    color: #78350F;
}

.chair-sub {
    font-size: 0.78rem;
    color: #92400E;
}

.sub-heading-lbl {
    font-size: 0.78rem;
    font-weight: 800;
    color: #64748B;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.55rem;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.committee-members-group {
    display: flex;
    flex-direction: column;
}

.members-chips-container {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}

.member-badge-card {
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    padding: 0.5rem 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.65rem;
}

.member-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #047857;
    color: white;
    font-weight: 800;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.member-info strong {
    display: block;
    font-size: 0.88rem;
    color: #0F172A;
}

.member-info span {
    font-size: 0.72rem;
    color: #64748B;
}

.adviser-feature-box {
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    border-radius: 10px;
    padding: 0.85rem 1rem;
}

.adviser-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.adviser-avatar {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: #ECFDF5;
    color: #047857;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
}

.adviser-name {
    font-size: 0.95rem;
    font-weight: 800;
    color: #0F172A;
}

.adviser-sub {
    font-size: 0.76rem;
    color: #64748B;
}

.deliberation-result-box {
    background: #022C22;
    color: white;
    border-radius: 10px;
    padding: 1rem 1.25rem;
    border: 1px solid #10B981;
}

.result-label {
    font-size: 0.75rem;
    font-weight: 800;
    color: #34D399;
    text-transform: uppercase;
}

.result-verdict {
    font-size: 1.1rem;
    font-weight: 800;
    color: #FFFFFF;
    margin-top: 0.2rem;
}

.btn-view-mom {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    background: #F59E0B;
    color: #022C22;
    padding: 0.4rem 0.85rem;
    border-radius: 6px;
    font-size: 0.82rem;
    font-weight: 800;
    text-decoration: none;
}

.btn-view-mom:hover {
    background: #D97706;
    color: white;
}
</style>
