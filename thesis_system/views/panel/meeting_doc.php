<?php
// DEFENSE MINUTES OF THE MEETING (MOM) DOCUMENTATION VIEW - PURE MANUAL ENCODING

$current_role = $_SESSION['role'] ?? 'panel';
$panel_id = (int)($_SESSION['user_id'] ?? 0);
$panel_name = $_SESSION['name'] ?? 'Faculty Member';

// Fetch candidates list (Admin and Secretary have global oversight of all capstones; panelist has assigned candidates)
if ($current_role === 'admin' || $current_role === 'secretary') {
    $assignments_q = $conn->query("
        SELECT DISTINCT u.id as student_id, 0 as tp_id, '' as grading_status, NULL as grade,
               u.name as stu_name, u.email as stu_email,
               COALESCE(NULLIF(sp.thesis_topic, ''), cg.thesis_topic, (SELECT dm.title FROM defense_meetings dm WHERE dm.student_id = u.id ORDER BY dm.id DESC LIMIT 1), 'Capstone Research') as thesis_topic,
               sp.department, sp.group_id,
               cg.group_name,
               COALESCE(NULLIF(sp.manuscript_file, ''), cg.manuscript_file) as manuscript_file,
               COALESCE(NULLIF(sp.shareable_link, ''), cg.shareable_link) as shareable_link,
               COALESCE(NULLIF(sp.manuscript_stage, ''), cg.manuscript_stage) as manuscript_stage,
               COALESCE(NULLIF(sp.manuscript_updated_at, ''), cg.manuscript_updated_at) as manuscript_updated_at
        FROM user u
        LEFT JOIN student_profile sp ON u.id = sp.user_id
        LEFT JOIN capstone_group cg ON sp.group_id = cg.id
        WHERE u.role = 'student' AND (
            (sp.thesis_topic IS NOT NULL AND sp.thesis_topic != '') 
            OR EXISTS (SELECT 1 FROM defense_meetings dm WHERE dm.student_id = u.id)
            OR sp.group_id IS NOT NULL
        )
        ORDER BY u.name ASC
    ");
} else {
    $assignments_q = $conn->query("
        SELECT tp.id as tp_id, tp.student_id, tp.status as grading_status, tp.grade,
               u.name as stu_name, u.email as stu_email,
               COALESCE(NULLIF(sp.thesis_topic, ''), cg.thesis_topic, 'Capstone Research') as thesis_topic,
               sp.department, sp.group_id,
               cg.group_name,
               COALESCE(NULLIF(sp.manuscript_file, ''), cg.manuscript_file) as manuscript_file,
               COALESCE(NULLIF(sp.shareable_link, ''), cg.shareable_link) as shareable_link,
               COALESCE(NULLIF(sp.manuscript_stage, ''), cg.manuscript_stage) as manuscript_stage,
               COALESCE(NULLIF(sp.manuscript_updated_at, ''), cg.manuscript_updated_at) as manuscript_updated_at
        FROM thesis_panel tp
        JOIN user u ON tp.student_id = u.id
        JOIN student_profile sp ON u.id = sp.user_id
        LEFT JOIN capstone_group cg ON sp.group_id = cg.id
        WHERE tp.panel_id = $panel_id
        ORDER BY u.name ASC
    ");
}

$assigned_list = [];
if ($assignments_q) {
    while ($a = $assignments_q->fetch_assoc()) {
        $assigned_list[] = $a;
    }
}

// Active student
$selected_stu_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
if (!$selected_stu_id && (!empty($_GET['meeting_id']) || !empty($_GET['schedule_id']))) {
    $target_mid = (int)(!empty($_GET['meeting_id']) ? $_GET['meeting_id'] : $_GET['schedule_id']);
    $m_lookup = $conn->query("SELECT student_id FROM defense_meetings WHERE id = $target_mid LIMIT 1");
    if ($m_lookup && $m_row = $m_lookup->fetch_assoc()) {
        $selected_stu_id = (int)$m_row['student_id'];
    }
}
if (!$selected_stu_id && isset($_GET['tp_id'])) {
    $target_tp = (int)$_GET['tp_id'];
    foreach ($assigned_list as $al) {
        if ($al['tp_id'] == $target_tp) {
            $selected_stu_id = (int)$al['student_id'];
            break;
        }
    }
}
if (!$selected_stu_id && count($assigned_list) > 0 && $current_role !== 'secretary') {
    $selected_stu_id = (int)$assigned_list[0]['student_id'];
}

$active_student = null;
foreach ($assigned_list as $al) {
    if ((int)$al['student_id'] === $selected_stu_id) {
        $active_student = $al;
        break;
    }
}

// Fallback: If student ID is requested but not present in list, retrieve directly
if (!$active_student && $selected_stu_id > 0) {
    $stu_q = $conn->query("
        SELECT u.id as student_id, 0 as tp_id, '' as grading_status, NULL as grade,
               u.name as stu_name, u.email as stu_email,
               COALESCE(NULLIF(sp.thesis_topic, ''), cg.thesis_topic, 'Capstone Research') as thesis_topic,
               sp.department, sp.group_id,
               cg.group_name,
               COALESCE(NULLIF(sp.manuscript_file, ''), cg.manuscript_file) as manuscript_file,
               COALESCE(NULLIF(sp.shareable_link, ''), cg.shareable_link) as shareable_link,
               COALESCE(NULLIF(sp.manuscript_stage, ''), cg.manuscript_stage) as manuscript_stage,
               COALESCE(NULLIF(sp.manuscript_updated_at, ''), cg.manuscript_updated_at) as manuscript_updated_at
        FROM user u
        LEFT JOIN student_profile sp ON u.id = sp.user_id
        LEFT JOIN capstone_group cg ON sp.group_id = cg.id
        WHERE u.id = $selected_stu_id
    ");
    if ($stu_q && $stu_q->num_rows > 0) {
        $active_student = $stu_q->fetch_assoc();
        array_unshift($assigned_list, $active_student);
    }
}

// If student selected, retrieve or initialize defense_meetings record
$meeting = null;
$mom = null;
$transcripts = [];

if ($active_student) {
    $gid = !empty($active_student['group_id']) ? (int)$active_student['group_id'] : null;
    $where_m = $gid ? "(student_id = $selected_stu_id OR group_id = $gid)" : "student_id = $selected_stu_id";

    // Prioritize specific meeting requested
    $requested_mid = (int)(!empty($_GET['meeting_id']) ? $_GET['meeting_id'] : (!empty($_GET['schedule_id']) ? $_GET['schedule_id'] : 0));
    if ($requested_mid > 0) {
        $m_res = $conn->query("SELECT * FROM defense_meetings WHERE id = $requested_mid LIMIT 1");
        if ($m_res && $m_res->num_rows > 0) {
            $meeting = $m_res->fetch_assoc();
        }
    }

    if (!$meeting) {
        $m_res = $conn->query("SELECT * FROM defense_meetings WHERE $where_m ORDER BY id DESC LIMIT 1");
        if ($m_res && $m_res->num_rows > 0) {
            $meeting = $m_res->fetch_assoc();
        }
    }

    if (!$meeting) {
        // Do NOT auto-insert into defense_meetings; use in-memory draft structure so student schedules aren't falsely created
        $meeting = [
            'id' => 0,
            'title' => "Oral Defense - " . ($active_student['thesis_topic'] ?: 'Capstone Research'),
            'student_id' => $selected_stu_id,
            'group_id' => $gid,
            'defense_stage' => 'Final Oral Defense',
            'meeting_date' => date('Y-m-d'),
            'start_time' => null,
            'venue' => 'CEIT Defense Hall / Virtual Room',
            'panel_chair_id' => $panel_id,
            'panel_members' => $panel_name,
            'adviser_name' => '',
            'status' => 'draft'
        ];
    }

    $meeting_id = (int)($meeting['id'] ?? 0);

    // Load Minutes of the Meeting if meeting already exists in database
    if ($meeting_id > 0) {
        $mom_res = $conn->query("SELECT * FROM defense_meeting_minutes WHERE meeting_id = $meeting_id");
        if ($mom_res && $mom_res->num_rows > 0) {
            $mom = $mom_res->fetch_assoc();
        }
    }

    // Fetch all panel members assigned to this student for attendance
    $all_panels_res = $conn->query("SELECT u.id, u.name FROM thesis_panel tp JOIN user u ON tp.panel_id = u.id WHERE tp.student_id = $selected_stu_id");
    $panel_names_arr = [];
    if ($all_panels_res) {
        while ($p = $all_panels_res->fetch_assoc()) {
            $panel_names_arr[] = $p['name'];
        }
    }
    $all_panel_names_str = implode(', ', $panel_names_arr);
    if (empty($all_panel_names_str)) $all_panel_names_str = $panel_name;
}

// Build candidates dictionary for dynamic instant switching and auto-population
$candidates_map = [];
foreach ($assigned_list as $al) {
    $sid = (int)$al['student_id'];
    $gid = !empty($al['group_id']) ? (int)$al['group_id'] : null;
    $where_m_cand = $gid ? "(student_id = $sid OR group_id = $gid)" : "student_id = $sid";

    // Meeting query
    $c_meet = null;
    $cm_q = $conn->query("SELECT * FROM defense_meetings WHERE $where_m_cand ORDER BY id DESC LIMIT 1");
    if ($cm_q && $cm_q->num_rows > 0) {
        $c_meet = $cm_q->fetch_assoc();
    }

    // Minutes query
    $c_mom = null;
    $c_mid = $c_meet ? (int)$c_meet['id'] : 0;
    if ($c_mid > 0) {
        $cmom_q = $conn->query("SELECT * FROM defense_meeting_minutes WHERE meeting_id = $c_mid LIMIT 1");
        if ($cmom_q && $cmom_q->num_rows > 0) {
            $c_mom = $cmom_q->fetch_assoc();
        }
    }

    // Panel members
    $c_panels = [];
    $cp_q = $conn->query("SELECT u.name FROM thesis_panel tp JOIN user u ON tp.panel_id = u.id WHERE tp.student_id = $sid");
    if ($cp_q) {
        while ($cp_row = $cp_q->fetch_assoc()) {
            $c_panels[] = $cp_row['name'];
        }
    }
    $c_panel_str = implode(', ', $c_panels);
    if (empty($c_panel_str)) {
        $c_panel_str = !empty($c_meet['panel_members']) ? $c_meet['panel_members'] : $panel_name;
    }

    // Adviser name
    $c_adviser = !empty($c_meet['adviser_name']) ? $c_meet['adviser_name'] : '';
    if (empty($c_adviser)) {
        $adv_q = $conn->query("SELECT u.name FROM match_request mr JOIN user u ON mr.adviser_id = u.id WHERE (" . ($gid ? "mr.group_id = $gid OR " : "") . "mr.student_id = $sid) AND mr.status = 'accepted' ORDER BY mr.id DESC LIMIT 1");
        if ($adv_q && $adv_row = $adv_q->fetch_assoc()) {
            $c_adviser = $adv_row['name'];
        }
    }
    if (empty($c_adviser)) $c_adviser = 'Engr. Assigned Adviser';

    $topic = !empty($al['thesis_topic']) ? $al['thesis_topic'] : 'Capstone Research';
    $venue = !empty($c_meet['venue']) ? $c_meet['venue'] : 'CEIT Defense Hall';
    $m_date = !empty($c_meet['meeting_date']) ? $c_meet['meeting_date'] : date('Y-m-d');
    $stage = !empty($c_meet['defense_stage']) ? $c_meet['defense_stage'] : 'Final Oral Defense';
    $group_disp = $al['stu_name'] . (!empty($al['group_name']) ? ' (' . $al['group_name'] . ')' : '');

    $candidates_map[$sid] = [
        'student_id' => $sid,
        'group_id' => $gid,
        'meeting_id' => $c_mid,
        'stu_name' => $al['stu_name'],
        'thesis_topic' => $topic,
        'venue' => $venue,
        'meeting_date' => $m_date,
        'formatted_date' => date("F d, Y", strtotime($m_date)),
        'defense_stage' => $stage,
        'group_display' => $group_disp,
        'document_title' => !empty($c_mom['document_title']) ? $c_mom['document_title'] : ('Minutes of Oral Defense: ' . $topic),
        'panel_members' => $c_panel_str,
        'adviser_name' => $c_adviser,
        'discussion_summary' => !empty($c_mom['discussion_summary']) ? $c_mom['discussion_summary'] : '',
        'decision_verdict' => !empty($c_mom['decision_verdict']) ? $c_mom['decision_verdict'] : '',
        'grade_average' => !empty($c_mom['grade_average']) ? (float)$c_mom['grade_average'] : '',
        'recommendations' => !empty($c_mom['recommendations']) ? $c_mom['recommendations'] : '',
        'notes' => !empty($c_mom['notes']) ? $c_mom['notes'] : '',
        'action_items' => !empty($c_mom['action_items']) ? $c_mom['action_items'] : '[]',
        'recorded_by_name' => !empty($c_mom['recorded_by_name']) ? $c_mom['recorded_by_name'] : (!empty($c_meet['mom_documenter_name']) ? $c_meet['mom_documenter_name'] : $panel_name),
        'approved_by_chair' => !empty($c_mom['approved_by_chair']) ? $c_mom['approved_by_chair'] : 'Panel Committee Chair',
        'status' => !empty($c_mom['status']) ? $c_mom['status'] : 'draft',
        'manuscript_file' => !empty($al['manuscript_file']) ? $al['manuscript_file'] : '',
        'shareable_link' => !empty($al['shareable_link']) ? $al['shareable_link'] : '',
        'manuscript_stage' => !empty($al['manuscript_stage']) ? $al['manuscript_stage'] : ''
    ];
}
?>

<link rel="stylesheet" href="css/meeting_doc.css?v=<?php echo time(); ?>">

<div class="meeting-doc-wrapper">
    <!-- Header Banner -->
    <div class="meeting-header-card">
        <div class="meeting-title-row">
            <div>
                <h1><i class="fa-solid fa-file-signature"></i> Create Minutes of the Meeting (MOM)</h1>
                <p>Document defense proceedings, panel questions, committee verdicts, and generate official Minutes of the Meeting (MOM).</p>
            </div>
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
                <span class="session-badge-pill">
                    <i class="fa-solid fa-signal" style="color: #10B981;"></i> Session Active
                </span>
                <?php if($active_student): ?>
                <?php if($current_role === 'admin'): ?>
                <a href="?tab=schedules" class="btn-primary" style="text-decoration:none; padding: 0.4rem 0.9rem; font-size: 0.8rem; background: #047857; border-color: #047857;">
                    <i class="fa-solid fa-calendar-days"></i> Defense Schedules
                </a>
                <a href="?tab=mom_archive" class="btn-primary" style="text-decoration:none; padding: 0.4rem 0.9rem; font-size: 0.8rem; background: #065F46; border-color: #065F46;">
                    <i class="fa-solid fa-file-lines"></i> Official MOM Registry
                </a>
                <?php elseif($current_role === 'secretary'): ?>
                <a href="?tab=mom_archive" class="btn-primary" style="text-decoration:none; padding: 0.4rem 0.9rem; font-size: 0.8rem; background: #047857; border-color: #047857;">
                    <i class="fa-solid fa-file-signature"></i> Official MOM Registry
                </a>
                <?php else: ?>
                <a href="?tab=comments&student_id=<?php echo $selected_stu_id; ?>" class="btn-primary" style="text-decoration:none; padding: 0.4rem 0.9rem; font-size: 0.8rem; background: #047857; border-color: #047857;">
                    <i class="fa-solid fa-comments"></i> Open Comments Panel
                </a>
                <?php endif; ?>
                <button type="button" class="btn btn-outline" onclick="window.print()" style="padding: 0.4rem 0.9rem; font-size: 0.8rem; background: rgba(255,255,255,0.15); color: white; border-color: rgba(255,255,255,0.3);">
                    <i class="fa-solid fa-print"></i> Print Official MOM
                </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="candidate-strip">
            <div class="strip-item">
                <div class="label"><i class="fa-solid fa-user-graduate"></i> Student Candidate</div>
                <div class="value" id="stripCandidateName"><?php echo $active_student ? htmlspecialchars($active_student['stu_name']) : '--'; ?></div>
            </div>
            <div class="strip-item">
                <div class="label"><i class="fa-solid fa-book-bookmark"></i> Capstone Title</div>
                <div class="value" id="stripCapstoneTitle"><?php echo $active_student ? htmlspecialchars($active_student['thesis_topic'] ?: 'Proposal Pending') : 'No active defense session selected'; ?></div>
            </div>
            <div class="strip-item">
                <div class="label"><i class="fa-solid fa-building-columns"></i> Defense Venue</div>
                <div class="value" id="stripDefenseVenue"><?php echo $active_student ? htmlspecialchars($meeting['venue'] ?? 'CEIT Defense Hall') : '--'; ?></div>
            </div>
            <div class="strip-item">
                <div class="label"><i class="fa-solid fa-calendar-day"></i> Session Date</div>
                <div class="value" id="stripSessionDate"><?php echo $active_student ? date("F d, Y", strtotime($meeting['meeting_date'] ?? date('Y-m-d'))) : '--'; ?></div>
            </div>
            <div class="strip-item">
                <div class="label"><i class="fa-solid fa-file-lines"></i> Defense Manuscript</div>
                <div class="value" id="stripManuscript">
                    <?php if(!empty($active_student['manuscript_file']) || !empty($active_student['shareable_link'])): ?>
                        <div style="display:inline-flex; gap:0.4rem; flex-wrap:wrap; margin-top:2px;">
                            <?php if(!empty($active_student['manuscript_file'])): ?>
                                <a href="<?php echo htmlspecialchars($active_student['manuscript_file']); ?>" target="_blank" style="display:inline-flex; align-items:center; gap:0.3rem; padding:0.25rem 0.6rem; background:#047857; color:#fff; border-radius:4px; font-size:0.75rem; font-weight:600; text-decoration:none;">
                                    <i class="fa-solid fa-file-pdf"></i> Read PDF
                                </a>
                            <?php endif; ?>
                            <?php if(!empty($active_student['shareable_link'])): ?>
                                <a href="<?php echo htmlspecialchars($active_student['shareable_link']); ?>" target="_blank" rel="noopener noreferrer" style="display:inline-flex; align-items:center; gap:0.3rem; padding:0.25rem 0.6rem; background:#1D4ED8; color:#fff; border-radius:4px; font-size:0.75rem; font-weight:600; text-decoration:none;">
                                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Live Doc
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <span style="font-size:0.8rem; color:#94A3B8; font-weight:normal;">None submitted</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Candidate Selector Bar -->
    <div class="card no-print" style="padding: 0.75rem 1.25rem;">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <label style="font-weight: 700; color: #1E293B; font-size: 0.85rem;"><i class="fa-solid fa-folder-user"></i> Defense Candidate:</label>
                <select id="candidateSelect" class="mom-select" style="width: auto; min-width: 280px;" onchange="onCandidateChange(this.value)">
                    <option value="" <?php echo empty($selected_stu_id) ? 'selected' : ''; ?>>-- Select Defense Candidate --</option>
                    <?php if(!empty($assigned_list)): ?>
                        <?php foreach($assigned_list as $al): ?>
                            <option value="<?php echo $al['student_id']; ?>" <?php echo $al['student_id'] == $selected_stu_id ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($al['stu_name']); ?> - <?php echo htmlspecialchars(mb_strimwidth($al['thesis_topic'] ?: 'No Topic', 0, 45, '...')); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div style="display: flex; gap: 0.5rem; align-items: center;">
                <span class="badge badge-info" id="badgeMeetingId">
                    <i class="fa-solid fa-receipt"></i> <?php echo (!empty($meeting['id'])) ? ('Meeting #' . $meeting['id']) : 'No Session Selected'; ?>
                </span>
                <span class="badge" id="badgeVerdict" style="<?php echo empty($selected_stu_id) ? 'background:#F1F5F9; color:#64748B; border:1px solid #CBD5E1;' : (empty($mom['decision_verdict']) ? 'background:#FEF3C7; color:#92400E; border:1px solid #FDE68A;' : 'background:#ECFDF5; color:#047857; border:1px solid #A7F3D0;'); ?>">
                    <?php if(empty($selected_stu_id)): ?>
                        <i class="fa-solid fa-circle-pause"></i> No Session Selected
                    <?php elseif(empty($mom['decision_verdict'])): ?>
                        <i class="fa-solid fa-clock"></i> Draft / Ongoing Defense
                    <?php else: ?>
                        <i class="fa-solid fa-gavel"></i> <?php echo htmlspecialchars($mom['decision_verdict']); ?>
                    <?php endif; ?>
                </span>
            </div>
        </div>
    </div>

    <?php if(empty($assigned_list)): ?>
        <div class="card" style="text-align: center; padding: 4rem 1rem;">
            <i class="fa-solid fa-folder-open fa-3x" style="color: #CBD5E1; margin-bottom: 1rem; display: block;"></i>
            <h3 style="color: #475569; margin-bottom: 0.5rem;">No Candidates Assigned Yet</h3>
            <p style="color: #94A3B8; font-size: 0.9rem;">Once defense schedules and candidates are created, you can document defense proceedings here.</p>
        </div>
    <?php else: ?>

    <!-- Full-Width Minutes of the Meeting (MOM) Layout -->
    <div class="doc-full-layout">

        <!-- ==========================================
             MINUTES OF THE MEETING (MOM) DOCUMENT (FULL-WIDTH)
             ========================================== -->
        <div class="doc-card" id="momOfficialDocCard">
            <div class="doc-card-header">
                <h2><i class="fa-solid fa-file-shield" style="color: #047857;"></i> Minutes of the Meeting (MOM)</h2>
                <div style="display: flex; gap: 0.5rem;" class="no-print">
                    <button type="button" id="saveDraftMomBtn" class="btn btn-outline btn-save-draft" onclick="saveMOM('draft')" style="font-size: 0.82rem; padding: 0.4rem 0.9rem;">
                        <i class="fa-solid fa-floppy-disk"></i> Save Draft
                    </button>
                    <button type="button" id="finalizeMomBtn" class="btn-primary btn-finalize-mom" onclick="saveMOM('finalized')" style="font-size: 0.82rem; padding: 0.4rem 1.1rem;">
                        <i class="fa-solid fa-signature"></i> Finalize & Save MOM
                    </button>
                </div>
            </div>

            <div class="doc-card-body mom-document-container">
                
                <!-- University Official Letterhead for Print/Official View -->
                <div class="csu-mom-letterhead">
                    <div style="display: flex; align-items: center; justify-content: center; gap: 1rem; margin-bottom: 0.4rem;">
                        <img src="logo.png" alt="CSU Seal" style="width: 55px; height: auto;">
                        <div>
                            <div class="univ-rep">Republic of the Philippines</div>
                            <div class="univ-title">CARAGA STATE UNIVERSITY CABADBARAN CAMPUS</div>
                            <div class="college-title">College of Engineering and Information Technology</div>
                        </div>
                    </div>
                    <div class="doc-heading">
                        MINUTES OF THE ORAL DEFENSE PROCEEDINGS
                    </div>
                </div>

                <!-- Form to Record / Edit MOM -->
                <form id="momForm" onsubmit="event.preventDefault(); saveMOM('finalized');">
                    <input type="hidden" name="meeting_id" id="mom_meeting_id" value="<?php echo $meeting['id'] ?? 0; ?>">
                    <input type="hidden" name="student_id" id="mom_student_id" value="<?php echo $selected_stu_id; ?>">
                    <input type="hidden" name="group_id" id="mom_group_id" value="<?php echo $active_student['group_id'] ?? ''; ?>">

                    <!-- Section 0: Meeting Identification & Attendees -->
                    <div class="mom-section-box">
                        <div class="mom-section-header">
                            <h3><i class="fa-solid fa-circle-info"></i> Session & Committee Information</h3>
                        </div>

                        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 0.75rem;">
                            <div>
                                <label class="mom-label">Document Title / Defense Topic</label>
                                <input type="text" name="document_title" id="mom_title" class="mom-input" value="<?php echo $active_student ? htmlspecialchars($mom['document_title'] ?? ('Minutes of Oral Defense: ' . ($active_student['thesis_topic'] ?: 'Capstone Project'))) : ''; ?>" placeholder="e.g. Minutes of Oral Defense: Capstone Research Title" required>
                            </div>
                            <div>
                                <label class="mom-label">Defense Stage</label>
                                <?php $def_stage_val = $meeting['defense_stage'] ?? 'Final Oral Defense'; ?>
                                <select name="defense_stage" id="mom_stage" class="mom-select">
                                    <option value="Final Oral Defense" <?php echo $def_stage_val=='Final Oral Defense'?'selected':''; ?>>Final Oral Defense</option>
                                    <option value="Pre-Oral Defense" <?php echo $def_stage_val=='Pre-Oral Defense'?'selected':''; ?>>Pre-Oral Defense</option>
                                    <option value="Outline Defense" <?php echo $def_stage_val=='Outline Defense'?'selected':''; ?>>Outline Defense</option>
                                    <option value="Title Proposal" <?php echo $def_stage_val=='Title Proposal'?'selected':''; ?>>Title Proposal</option>
                                </select>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.75rem;">
                            <div>
                                <label class="mom-label">Meeting Date</label>
                                <input type="date" name="meeting_date" id="mom_date" class="mom-input" value="<?php echo $active_student ? htmlspecialchars($meeting['meeting_date'] ?? date('Y-m-d')) : ''; ?>">
                            </div>
                            <div>
                                <label class="mom-label">Defense Venue</label>
                                <input type="text" name="venue" id="mom_venue" class="mom-input" value="<?php echo $active_student ? htmlspecialchars($meeting['venue'] ?? 'CEIT Defense Hall') : ''; ?>" placeholder="e.g. CEIT Defense Hall / Comlab 102">
                            </div>
                            <div>
                                <label class="mom-label">Candidate Group</label>
                                <input type="text" id="mom_candidate_group" class="mom-input" value="<?php echo $active_student ? htmlspecialchars($active_student['stu_name'] . (!empty($active_student['group_name']) ? ' (' . $active_student['group_name'] . ')' : '')) : ''; ?>" placeholder="-- Candidate / Group Name --" readonly style="background:#F1F5F9;">
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                            <div>
                                <label class="mom-label">Panel Committee Members Present</label>
                                <input type="text" name="panel_members" id="mom_panel_members" class="mom-input" value="<?php echo $active_student ? htmlspecialchars($meeting['panel_members'] ?: $all_panel_names_str) : ''; ?>" placeholder="e.g. Engr. Santos, Dr. Reyes">
                            </div>
                            <div>
                                <label class="mom-label">Capstone Adviser Present</label>
                                <input type="text" name="adviser_name" id="mom_adviser_name" class="mom-input" value="<?php echo $active_student ? htmlspecialchars($meeting['adviser_name'] ?: '') : ''; ?>" placeholder="e.g. Engr. Assigned Adviser">
                            </div>
                        </div>
                    </div>

                    <!-- Section 1: Presentation & Discussions Summary -->
                    <div class="mom-section-box">
                        <div class="mom-section-header">
                            <h3><i class="fa-solid fa-list-check"></i> I. Discussion Points / Panel Questions</h3>
                        </div>
                        <label class="mom-label">Document primary student presentation highlights, panel queries, and candidate defenses:</label>
                        <textarea name="discussion_summary" id="mom_discussions" class="mom-textarea" style="min-height: 120px;" placeholder="Document primary student presentation highlights, questions posed by panel members regarding methodology, testing results, and candidate explanations..."><?php echo htmlspecialchars($mom['discussion_summary'] ?? ''); ?></textarea>
                    </div>

                    <!-- Section 2: Committee Decision & Verdict -->
                    <div class="mom-section-box">
                        <div class="mom-section-header">
                            <h3><i class="fa-solid fa-gavel"></i> II. Committee Verdict & Numerical Rating</h3>
                        </div>

                        <?php $current_verdict = $mom['decision_verdict'] ?? ''; ?>
                        <div class="verdict-options-grid">
                            <label class="verdict-radio-card <?php echo $current_verdict=='Approved without Revisions'?'selected':''; ?>">
                                <input type="radio" name="decision_verdict" value="Approved without Revisions" <?php echo $current_verdict=='Approved without Revisions'?'checked':''; ?> onchange="updateVerdictSelection()">
                                <span>✅ Passed (No Revisions)</span>
                            </label>
                            <label class="verdict-radio-card <?php echo $current_verdict=='Approved with Minor Revisions'?'selected':''; ?>">
                                <input type="radio" name="decision_verdict" value="Approved with Minor Revisions" <?php echo $current_verdict=='Approved with Minor Revisions'?'checked':''; ?> onchange="updateVerdictSelection()">
                                <span>⚠️ Approved (Minor Revisions)</span>
                            </label>
                            <label class="verdict-radio-card <?php echo $current_verdict=='Approved with Major Revisions'?'selected':''; ?>">
                                <input type="radio" name="decision_verdict" value="Approved with Major Revisions" <?php echo $current_verdict=='Approved with Major Revisions'?'checked':''; ?> onchange="updateVerdictSelection()">
                                <span>⚡ Approved (Major Revisions)</span>
                            </label>
                            <label class="verdict-radio-card <?php echo $current_verdict=='Re-defense Required'?'selected':''; ?>">
                                <input type="radio" name="decision_verdict" value="Re-defense Required" <?php echo $current_verdict=='Re-defense Required'?'checked':''; ?> onchange="updateVerdictSelection()">
                                <span>🔄 Re-defense Required</span>
                            </label>
                        </div>

                        <div style="margin-top: 0.65rem; display: flex; align-items: center; gap: 1rem;">
                            <label class="mom-label" style="margin: 0; min-width: 170px;"><i class="fa-solid fa-calculator"></i> Committee Average Grade (%):</label>
                            <input type="number" step="0.01" min="0" max="100" name="grade_average" id="mom_grade_avg" class="mom-input" style="width: 140px; font-weight: 700; color: #047857;" value="<?php echo htmlspecialchars($mom['grade_average'] ?? ''); ?>" placeholder="e.g. 85.00">
                        </div>
                    </div>

                    <!-- Section 3: Recommendations & Revisions List -->
                    <div class="mom-section-box">
                        <div class="mom-section-header">
                            <h3><i class="fa-solid fa-list-ol"></i> III. Required Revisions & Recommendations</h3>
                        </div>
                        <label class="mom-label">Itemize all specific manuscript corrections, chapter enhancements, and hardware/software changes required:</label>
                        <textarea name="recommendations" id="mom_recommendations" class="mom-textarea" style="min-height: 120px;" placeholder="• Chapter 1: Refine the specific objectives to align with prototype capabilities.&#10;• Chapter 3: Add architectural block diagrams illustrating IoT sensor communication protocol.&#10;• Chapter 4: Provide tabular comparison between experimental sensor readings and calibrated benchmarks..."><?php echo htmlspecialchars($mom['recommendations'] ?? ''); ?></textarea>
                    </div>

                    <!-- Section 4: Remarks / Additional Committee Agreements -->
                    <div class="mom-section-box">
                        <div class="mom-section-header">
                            <h3><i class="fa-solid fa-clipboard-check"></i> IV. Remarks / Additional Committee Agreements</h3>
                        </div>
                        <label class="mom-label">Document any routing requirements, clearance notes, or special committee instructions (Optional):</label>
                        <textarea name="notes" id="mom_notes" class="mom-textarea" style="min-height: 80px;" placeholder="e.g. Candidate instructed to secure adviser sign-off on Chapter 3 before panel chair endorsement..."><?php echo htmlspecialchars($mom['notes'] ?? ''); ?></textarea>
                    </div>

                    <!-- Section 5: Action Items & Compliance Table -->
                    <div class="mom-section-box">
                        <div class="mom-section-header">
                            <h3><i class="fa-solid fa-tasks"></i> V. Action Items & Next Steps</h3>
                            <button type="button" class="btn-transcript-act no-print" onclick="addActionItemRow()" style="background:#F1F5F9; color:#0F172A; font-weight:700;">
                                <i class="fa-solid fa-plus"></i> Add Action Item
                            </button>
                        </div>
                        
                        <!-- JSON storage for action items -->
                        <textarea name="action_items" id="mom_action_items" style="display:none;"><?php echo htmlspecialchars($mom['action_items'] ?? ''); ?></textarea>

                        <div class="table-responsive">
                            <table class="action-items-table" id="actionItemsTable">
                                <thead>
                                    <tr>
                                        <th style="width: 45%;">Action Item / Task</th>
                                        <th style="width: 25%;">Responsible Person</th>
                                        <th style="width: 18%;">Target Deadline</th>
                                        <th style="width: 12%; text-align: center;" class="no-print">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="actionItemsBody">
                                    <!-- Rendered dynamically via JS from action_items JSON -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Section 6: Certification & Signatories -->
                    <div class="mom-section-box">
                        <div class="mom-section-header">
                            <h3><i class="fa-solid fa-stamp"></i> VI. Documentation Certification & Signatures</h3>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 0.5rem;">
                            <div>
                                <?php 
                                    $assigned_recorder_name = !empty($mom['recorded_by_name']) ? $mom['recorded_by_name'] : (!empty($meeting['mom_documenter_name']) ? $meeting['mom_documenter_name'] : $panel_name);
                                ?>
                                <label class="mom-label"><i class="fa-solid fa-pen-nib" style="color: #059669;"></i> Documented / Recorded By (MOM Documenter):</label>
                                <input type="text" name="recorded_by_name" id="mom_recorded_by" class="mom-input" value="<?php echo htmlspecialchars($assigned_recorder_name); ?>">
                                <div style="font-size: 0.72rem; color: #047857; margin-top: 0.2rem;">
                                    <?php if(!empty($meeting['mom_documenter_name'])): ?>
                                        <i class="fa-solid fa-circle-check"></i> Assigned in Schedule: <strong><?php echo htmlspecialchars($meeting['mom_documenter_name']); ?></strong>
                                    <?php else: ?>
                                        Panel Evaluator / Secretary / Documenter
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div>
                                <label class="mom-label"><i class="fa-solid fa-crown" style="color: #D97706;"></i> Approved & Certified by Panel Chair:</label>
                                <input type="text" name="approved_by_chair" id="mom_approved_chair" class="mom-input" value="<?php echo htmlspecialchars($mom['approved_by_chair'] ?? 'Panel Committee Chair'); ?>">
                                <div style="font-size: 0.72rem; color: #64748B; margin-top: 0.2rem;">Oral Defense Committee Chair</div>
                            </div>
                        </div>

                        <!-- Formal Print Signature Lines -->
                        <div style="display: none; margin-top: 3rem; justify-content: space-between; page-break-inside: avoid;" class="print-signatures-block">
                            <div style="text-align: center; width: 45%;">
                                <div style="border-bottom: 1px solid black; padding-bottom: 0.2rem; font-weight: bold;" id="printDocBy"><?php echo htmlspecialchars($mom['recorded_by_name'] ?? $panel_name); ?></div>
                                <div style="font-size: 9pt; margin-top: 4px;">Documented By / Panel Member</div>
                            </div>
                            <div style="text-align: center; width: 45%;">
                                <div style="border-bottom: 1px solid black; padding-bottom: 0.2rem; font-weight: bold;" id="printChair"><?php echo htmlspecialchars($mom['approved_by_chair'] ?? 'Panel Committee Chair'); ?></div>
                                <div style="font-size: 9pt; margin-top: 4px;">Certified & Approved / Panel Chair</div>
                            </div>
                        </div>

                    </div>

                    <!-- Bottom Action Bar -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid #E2E8F0;" class="no-print">
                        <span style="font-size: 0.85rem; color: #64748B;" id="momSaveStatusText">
                            <i class="fa-solid fa-cloud-arrow-up"></i> Auto-save ready.
                        </span>
                        <div style="display: flex; gap: 0.5rem; align-items: center;">
                            <button type="button" class="btn btn-outline" onclick="window.print()" style="padding: 0.45rem 0.9rem; font-size: 0.82rem;">
                                <i class="fa-solid fa-print"></i> Print Official MOM
                            </button>
                            <button type="button" id="saveDraftMomBtnBottom" class="btn btn-outline btn-save-draft" onclick="saveMOM('draft')" style="padding: 0.45rem 0.9rem; font-size: 0.82rem;">
                                <i class="fa-solid fa-floppy-disk"></i> Save Draft
                            </button>
                            <button type="button" id="finalizeMomBtnBottom" class="btn-primary btn-finalize-mom" onclick="saveMOM('finalized')" style="padding: 0.45rem 1.15rem; font-size: 0.82rem;">
                                <i class="fa-solid fa-signature"></i> Finalize & Save MOM
                            </button>
                        </div>
                    </div>

                </form>

            </div>
        </div>

    </div>

    <?php endif; ?>
</div>


<script>
// ==========================================
// MINUTES OF THE MEETING (MOM) LOGIC (PURE MANUAL ENCODING)
// ==========================================

// Action Items Manager (Dynamic Table + JSON Serialization)
let actionItemsList = [];

try {
    const rawAi = document.getElementById('mom_action_items').value.trim();
    if (rawAi && rawAi !== '[]') {
        actionItemsList = JSON.parse(rawAi);
    }
} catch (e) {
    actionItemsList = [];
}

function renderActionItems() {
    const tbody = document.getElementById('actionItemsBody');
    if (!tbody) return;
    tbody.innerHTML = '';

    if (!Array.isArray(actionItemsList) || actionItemsList.length === 0) {
        tbody.innerHTML = `<tr><td colspan="4" style="text-align: center; color: #94A3B8; font-size: 0.85rem; padding: 1.25rem;"><i class="fa-solid fa-list-check" style="margin-right: 0.4rem;"></i>No action items added yet. Click <strong>+ Add Action Item</strong> to record deliverables.</td></tr>`;
        const aiField = document.getElementById('mom_action_items');
        if (aiField) aiField.value = '[]';
        return;
    }

    actionItemsList.forEach((item, index) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <input type="text" class="mom-input" style="padding: 0.35rem 0.5rem; font-size: 0.82rem;" value="${escapeHtml(item.task)}" onchange="updateActionItem(${index}, 'task', this.value)">
            </td>
            <td>
                <input type="text" class="mom-input" style="padding: 0.35rem 0.5rem; font-size: 0.82rem;" value="${escapeHtml(item.assigned)}" onchange="updateActionItem(${index}, 'assigned', this.value)">
            </td>
            <td>
                <input type="date" class="mom-input" style="padding: 0.35rem 0.5rem; font-size: 0.82rem;" value="${escapeHtml(item.deadline)}" onchange="updateActionItem(${index}, 'deadline', this.value)">
            </td>
            <td style="text-align: center;" class="no-print">
                <button type="button" class="btn-transcript-act delete" onclick="removeActionItemRow(${index})" title="Delete Item">
                    <i class="fa-solid fa-trash-can"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    });

    document.getElementById('mom_action_items').value = JSON.stringify(actionItemsList);
}

function updateActionItem(idx, field, val) {
    if (actionItemsList[idx]) {
        actionItemsList[idx][field] = val;
        document.getElementById('mom_action_items').value = JSON.stringify(actionItemsList);
    }
}

function addActionItemRow() {
    actionItemsList.push({
        task: "",
        assigned: "Candidate / Group",
        deadline: "<?php echo date('Y-m-d', strtotime('+7 days')); ?>",
        status: "Pending"
    });
    renderActionItems();
}

function removeActionItemRow(idx) {
    actionItemsList.splice(idx, 1);
    renderActionItems();
}

function updateVerdictSelection() {
    let chosenVerdict = '';
    document.querySelectorAll('.verdict-radio-card').forEach(card => {
        const radio = card.querySelector('input');
        if (radio && radio.checked) {
            card.classList.add('selected');
            chosenVerdict = radio.value;
        } else {
            card.classList.remove('selected');
        }
    });

    if (chosenVerdict) {
        setVerdictBadgeStyle(chosenVerdict);
    }
}

function setVerdictBadgeStyle(verdict) {
    const bVerdict = document.getElementById('badgeVerdict');
    if (!bVerdict) return;

    if (!verdict) {
        bVerdict.style.background = '#FEF3C7';
        bVerdict.style.color = '#92400E';
        bVerdict.style.border = '1px solid #FDE68A';
        bVerdict.innerHTML = '<i class="fa-solid fa-clock"></i> Draft / Ongoing Defense';
        return;
    }

    if (verdict === 'Approved without Revisions') {
        bVerdict.style.background = '#ECFDF5';
        bVerdict.style.color = '#065F46';
        bVerdict.style.border = '1px solid #A7F3D0';
        bVerdict.innerHTML = '<i class="fa-solid fa-circle-check"></i> Passed (No Revisions)';
    } else if (verdict === 'Approved with Minor Revisions') {
        bVerdict.style.background = '#ECFDF5';
        bVerdict.style.color = '#047857';
        bVerdict.style.border = '1px solid #A7F3D0';
        bVerdict.innerHTML = '<i class="fa-solid fa-check-double"></i> Approved (Minor Revisions)';
    } else if (verdict === 'Approved with Major Revisions') {
        bVerdict.style.background = '#FEF3C7';
        bVerdict.style.color = '#92400E';
        bVerdict.style.border = '1px solid #FDE68A';
        bVerdict.innerHTML = '<i class="fa-solid fa-pen-to-square"></i> Approved (Major Revisions)';
    } else if (verdict === 'Re-defense Required') {
        bVerdict.style.background = '#FEE2E2';
        bVerdict.style.color = '#991B1B';
        bVerdict.style.border = '1px solid #FECACA';
        bVerdict.innerHTML = '<i class="fa-solid fa-rotate-right"></i> Re-defense Required';
    } else {
        bVerdict.style.background = '#ECFDF5';
        bVerdict.style.color = '#047857';
        bVerdict.style.border = '1px solid #A7F3D0';
        bVerdict.innerHTML = `<i class="fa-solid fa-gavel"></i> ${escapeHtml(verdict)}`;
    }
}

// Dictionary of all candidates for instant client-side auto-population
const candidatesData = <?php echo json_encode($candidates_map, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

// Dynamic Candidate Selector Handler
function onCandidateChange(studentId) {
    if (!studentId) {
        // RESET TO INITIAL BLANK STATE
        const elName = document.getElementById('stripCandidateName');
        const elTitle = document.getElementById('stripCapstoneTitle');
        const elVenue = document.getElementById('stripDefenseVenue');
        const elDate = document.getElementById('stripSessionDate');
        if (elName) elName.textContent = '--';
        if (elTitle) elTitle.textContent = 'No active defense session selected';
        if (elVenue) elVenue.textContent = '--';
        if (elDate) elDate.textContent = '--';

        const bMeeting = document.getElementById('badgeMeetingId');
        const bVerdict = document.getElementById('badgeVerdict');
        if (bMeeting) bMeeting.innerHTML = '<i class="fa-solid fa-receipt"></i> No Session Selected';
        if (bVerdict) {
            bVerdict.style.background = '#F1F5F9';
            bVerdict.style.color = '#64748B';
            bVerdict.style.border = '1px solid #CBD5E1';
            bVerdict.innerHTML = '<i class="fa-solid fa-circle-pause"></i> No Session Selected';
        }

        document.getElementById('mom_meeting_id').value = 0;
        document.getElementById('mom_student_id').value = 0;
        document.getElementById('mom_group_id').value = '';
        document.getElementById('mom_title').value = '';
        document.getElementById('mom_date').value = '';
        document.getElementById('mom_venue').value = '';
        document.getElementById('mom_candidate_group').value = '';
        document.getElementById('mom_panel_members').value = '';
        document.getElementById('mom_adviser_name').value = '';

        document.getElementById('mom_discussions').value = '';
        document.getElementById('mom_recommendations').value = '';
        if (document.getElementById('mom_notes')) document.getElementById('mom_notes').value = '';
        document.getElementById('mom_grade_avg').value = '';

        document.querySelectorAll('input[name="decision_verdict"]').forEach(r => r.checked = false);
        document.querySelectorAll('.verdict-radio-card').forEach(card => card.classList.remove('selected'));

        actionItemsList = [];
        renderActionItems();

        if (window.history && window.history.pushState) {
            const newUrl = new URL(window.location.href);
            newUrl.searchParams.delete('student_id');
            window.history.pushState({}, '', newUrl.toString());
        }

        const saveStatus = document.getElementById('momSaveStatusText');
        if (saveStatus) {
            saveStatus.innerHTML = '<i class="fa-solid fa-circle-info"></i> Please select a defense candidate to begin.';
        }
        return;
    }

    studentId = parseInt(studentId, 10);
    if (!studentId || !candidatesData[studentId]) return;
    const c = candidatesData[studentId];

    // 1. AUTO-FILL Top summary banner
    const elName = document.getElementById('stripCandidateName');
    const elTitle = document.getElementById('stripCapstoneTitle');
    const elVenue = document.getElementById('stripDefenseVenue');
    const elDate = document.getElementById('stripSessionDate');
    if (elName) elName.textContent = c.stu_name;
    if (elTitle) elTitle.textContent = c.thesis_topic;
    if (elVenue) elVenue.textContent = c.venue;
    if (elDate) elDate.textContent = c.formatted_date;

    const elManu = document.getElementById('stripManuscript');
    if (elManu) {
        let manuHtml = '';
        if (c.manuscript_file) {
            manuHtml += `<a href="${c.manuscript_file}" target="_blank" style="display:inline-flex; align-items:center; gap:0.3rem; padding:0.25rem 0.6rem; background:#047857; color:#fff; border-radius:4px; font-size:0.75rem; font-weight:600; text-decoration:none;"><i class="fa-solid fa-file-pdf"></i> Read PDF</a> `;
        }
        if (c.shareable_link) {
            manuHtml += `<a href="${c.shareable_link}" target="_blank" rel="noopener noreferrer" style="display:inline-flex; align-items:center; gap:0.3rem; padding:0.25rem 0.6rem; background:#1D4ED8; color:#fff; border-radius:4px; font-size:0.75rem; font-weight:600; text-decoration:none;"><i class="fa-solid fa-arrow-up-right-from-square"></i> Live Doc</a>`;
        }
        if (!manuHtml) {
            manuHtml = '<span style="font-size:0.8rem; color:#94A3B8; font-weight:normal;">None submitted</span>';
        }
        elManu.innerHTML = manuHtml;
    }

    // 2. AUTO-FILL Session & Committee Information fields
    const fMeetingId = document.getElementById('mom_meeting_id');
    const fStudentId = document.getElementById('mom_student_id');
    const fGroupId = document.getElementById('mom_group_id');
    const fDocTitle = document.getElementById('mom_title');
    const fStage = document.getElementById('mom_stage');
    const fDate = document.getElementById('mom_date');
    const fVenue = document.getElementById('mom_venue');
    const fGroup = document.getElementById('mom_candidate_group');
    const fPanel = document.getElementById('mom_panel_members');
    const fAdviser = document.getElementById('mom_adviser_name');

    if (fMeetingId) fMeetingId.value = c.meeting_id || 0;
    if (fStudentId) fStudentId.value = c.student_id;
    if (fGroupId) fGroupId.value = c.group_id || '';
    if (fDocTitle) fDocTitle.value = c.document_title || ('Minutes of Oral Defense: ' + c.thesis_topic);
    if (fStage) fStage.value = c.defense_stage || 'Final Oral Defense';
    if (fDate) fDate.value = c.meeting_date;
    if (fVenue) fVenue.value = c.venue;
    if (fGroup) fGroup.value = c.group_display;
    if (fPanel) fPanel.value = c.panel_members;
    if (fAdviser) fAdviser.value = c.adviser_name;

    // 3. LIVE ENCODING FIELDS: Keep blank unless candidate already has an existing saved MOM
    const fDisc = document.getElementById('mom_discussions');
    const fRec = document.getElementById('mom_recommendations');
    const fNotes = document.getElementById('mom_notes');
    const fGrade = document.getElementById('mom_grade_avg');
    if (fDisc) fDisc.value = c.discussion_summary || '';
    if (fRec) fRec.value = c.recommendations || '';
    if (fNotes) fNotes.value = c.notes || '';
    if (fGrade) fGrade.value = c.grade_average || '';

    // Verdict radio cards
    const savedVerdict = c.decision_verdict || '';
    const radios = document.querySelectorAll('input[name="decision_verdict"]');
    radios.forEach(r => {
        r.checked = (savedVerdict !== '' && r.value === savedVerdict);
    });
    updateVerdictSelection();

    // Top status badge
    if (savedVerdict) {
        setVerdictBadgeStyle(savedVerdict);
    } else {
        const bVerdict = document.getElementById('badgeVerdict');
        if (bVerdict) {
            bVerdict.style.background = '#FEF3C7';
            bVerdict.style.color = '#92400E';
            bVerdict.style.border = '1px solid #FDE68A';
            bVerdict.innerHTML = '<i class="fa-solid fa-clock"></i> Draft / Ongoing Defense';
        }
    }

    // Meeting badge
    const bMeeting = document.getElementById('badgeMeetingId');
    if (bMeeting) {
        bMeeting.innerHTML = `<i class="fa-solid fa-receipt"></i> Meeting #${c.meeting_id || 'Active'}`;
    }

    // Action items
    try {
        if (c.action_items && typeof c.action_items === 'string' && c.action_items.trim().startsWith('[') && c.action_items.trim() !== '[]') {
            actionItemsList = JSON.parse(c.action_items);
        } else if (Array.isArray(c.action_items)) {
            actionItemsList = c.action_items;
        } else {
            actionItemsList = [];
        }
    } catch(e) {
        actionItemsList = [];
    }
    renderActionItems();

    // Signatures
    const fRecBy = document.getElementById('mom_recorded_by');
    const fAppChair = document.getElementById('mom_approved_chair');
    if (fRecBy) fRecBy.value = c.recorded_by_name || '<?php echo addslashes($panel_name); ?>';
    if (fAppChair) fAppChair.value = c.approved_by_chair || 'Panel Committee Chair';

    // Quietly update browser URL so reloading keeps this candidate
    if (window.history && window.history.pushState) {
        const newUrl = new URL(window.location.href);
        newUrl.searchParams.set('student_id', studentId);
        window.history.pushState({ student_id: studentId }, '', newUrl.toString());
    }

    const saveStatus = document.getElementById('momSaveStatusText');
    if (saveStatus) {
        saveStatus.innerHTML = '<i class="fa-solid fa-circle-check" style="color: #059669;"></i> Schedule data auto-filled. Live fields ready for encoding.';
    }
}

// Save Minutes of the Meeting (Draft or Finalized)
function saveMOM(status) {
    const form = document.getElementById('momForm');
    if (!form) return;

    // Check basic validity
    const docTitle = document.getElementById('mom_title');
    if (docTitle && !docTitle.value.trim()) {
        alert("Please provide a Document Title / Defense Topic.");
        docTitle.focus();
        return;
    }

    const formData = new FormData(form);
    formData.append('api_action', 'save_meeting_minutes');
    formData.append('status', status);

    const statusText = document.getElementById('momSaveStatusText');
    if (statusText) {
        statusText.innerHTML = '<i class="fa-solid fa-spinner fa-spin" style="color: #047857;"></i> Saving Minutes of the Meeting...';
    }

    const draftBtns = document.querySelectorAll('.btn-save-draft');
    const finalizeBtns = document.querySelectorAll('.btn-finalize-mom');
    draftBtns.forEach(b => b.disabled = true);
    finalizeBtns.forEach(b => b.disabled = true);

    fetch('api.php', { method: 'POST', body: formData })
    .then(async r => {
        const text = await r.text();
        try {
            return JSON.parse(text);
        } catch(e) {
            console.error('Non-JSON server response:', text);
            throw new Error(text || 'Invalid response from server.');
        }
    })
    .then(data => {
        draftBtns.forEach(b => b.disabled = false);
        finalizeBtns.forEach(b => b.disabled = false);

        if (data.success) {
            if (statusText) {
                statusText.innerHTML = `<i class="fa-solid fa-circle-check" style="color: #059669;"></i> Saved at ${data.saved_at} (${status.toUpperCase()})`;
            }

            // Sync meeting ID
            if (data.meeting_id) {
                const fMid = document.getElementById('mom_meeting_id');
                if (fMid) fMid.value = data.meeting_id;
                const bMid = document.getElementById('badgeMeetingId');
                if (bMid) bMid.innerHTML = `<i class="fa-solid fa-receipt"></i> Meeting #${data.meeting_id}`;
            }

            // Update candidate cache in memory
            const currentStuId = parseInt(document.getElementById('mom_student_id').value, 10);
            if (candidatesData[currentStuId]) {
                candidatesData[currentStuId].document_title = document.getElementById('mom_title').value;
                candidatesData[currentStuId].discussion_summary = document.getElementById('mom_discussions').value;
                candidatesData[currentStuId].recommendations = document.getElementById('mom_recommendations').value;
                candidatesData[currentStuId].grade_average = document.getElementById('mom_grade_avg').value;
                candidatesData[currentStuId].decision_verdict = document.querySelector('input[name="decision_verdict"]:checked')?.value || 'Approved with Minor Revisions';
                candidatesData[currentStuId].action_items = JSON.stringify(actionItemsList);
                candidatesData[currentStuId].status = status;
                if (data.meeting_id) candidatesData[currentStuId].meeting_id = data.meeting_id;
            }

            const printDoc = document.getElementById('printDocBy');
            const printCh = document.getElementById('printChair');
            if (printDoc) printDoc.textContent = document.getElementById('mom_recorded_by').value;
            if (printCh) printCh.textContent = document.getElementById('mom_approved_chair').value;

            if (status === 'draft') {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'MOM Draft Saved!',
                        text: 'Your in-progress Minutes of the Meeting have been safely saved. You can continue editing or return to it anytime.',
                        confirmButtonColor: '#047857',
                        timer: 2500
                    });
                } else {
                    alert("MOM draft successfully saved for later editing!");
                }
            } else if (status === 'finalized') {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Official MOM Finalized!',
                        html: 'The Minutes of the Meeting have been certified and submitted directly to the <b>Official MOM Registry</b>.',
                        showCancelButton: true,
                        confirmButtonColor: '#047857',
                        cancelButtonColor: '#64748B',
                        confirmButtonText: '<i class="fa-solid fa-file-signature"></i> Go to Official MOM Registry',
                        cancelButtonText: 'Stay in Workspace'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = '?tab=mom_archive';
                        }
                    });
                } else {
                    if (confirm("Minutes of the Meeting finalized and stored in the Official MOM Registry! Go to Official MOM Registry now?")) {
                        window.location.href = '?tab=mom_archive';
                    }
                }
            }
        } else {
            if (statusText) {
                statusText.innerHTML = `<i class="fa-solid fa-circle-xmark" style="color: #DC2626;"></i> Save failed: ${data.error}`;
            }
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'error', title: 'Save Failed', text: data.error || 'Could not save MOM.' });
            } else {
                alert("Save failed: " + data.error);
            }
        }
    })
    .catch(err => {
        draftBtns.forEach(b => b.disabled = false);
        finalizeBtns.forEach(b => b.disabled = false);
        console.error(err);
        if (statusText) {
            statusText.innerHTML = `<i class="fa-solid fa-circle-xmark" style="color: #DC2626;"></i> ${err.message || 'Connection error while saving.'}`;
        }
    });
}

function escapeHtml(text) {
    if (!text) return '';
    return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}

// Initial render
document.addEventListener('DOMContentLoaded', function() {
    renderActionItems();
});
</script>
