<?php
if (!isset($_GET['tp_id'])) {
    echo "<div class='alert alert-danger'>Invalid Request: Panel assignment ID missing.</div>";
    exit();
}

$tp_id = (int)$_GET['tp_id'];
$panel_id = $_SESSION['user_id'];
$panel_name = $_SESSION['name'];

// Fetch assigned thesis, student, group, and defense schedule data
$stmt = $conn->prepare("
    SELECT tp.id, tp.student_id, tp.grade, tp.feedback, tp.rubric_scores,
           u.name as stu_name, u.email as stu_email,
           COALESCE(NULLIF(sp.thesis_topic, ''), cg.thesis_topic, 'Capstone Research') as thesis_topic,
           sp.department, sp.group_id,
           sp.student_id as student_school_id,
           COALESCE(NULLIF(sp.manuscript_file, ''), cg.manuscript_file) as manuscript_file,
           COALESCE(NULLIF(sp.shareable_link, ''), cg.shareable_link) as shareable_link,
           COALESCE(NULLIF(sp.manuscript_name, ''), cg.manuscript_name) as manuscript_name,
           COALESCE(NULLIF(sp.manuscript_stage, ''), cg.manuscript_stage, 'Proposal Defense') as manuscript_stage,
           COALESCE(NULLIF(sp.manuscript_notes, ''), cg.manuscript_notes) as manuscript_notes,
           cg.group_name, cg.leader_id,
           cg.member2_name, cg.member2_student_id,
           cg.member3_name, cg.member3_student_id
    FROM thesis_panel tp 
    JOIN user u ON tp.student_id = u.id 
    JOIN student_profile sp ON u.id = sp.user_id 
    LEFT JOIN capstone_group cg ON sp.group_id = cg.id
    WHERE tp.id = ? AND tp.panel_id = ?
");
$stmt->bind_param("ii", $tp_id, $panel_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows == 0) {
    echo "<div class='alert alert-danger'>Assignment not found or unauthorized access.</div>";
    exit();
}

$thesis = $res->fetch_assoc();
$stu_name = htmlspecialchars($thesis['stu_name']);
$thesis_topic = htmlspecialchars($thesis['thesis_topic'] ?: 'Capstone Project Oral Defense');
$department = htmlspecialchars($thesis['department'] ?: 'Information Technology');

// Presenters (Members)
$presenters = [];
$leader_name = $stu_name;
if (!empty($thesis['leader_id'])) {
    $l_res = $conn->query("SELECT name FROM user WHERE id = " . (int)$thesis['leader_id']);
    if ($l_res && $l_row = $l_res->fetch_assoc()) {
        $leader_name = $l_row['name'];
    }
}
$presenters[] = $leader_name . (!empty($thesis['group_name']) ? " (Leader)" : "");
if (!empty($thesis['member2_name'])) {
    $presenters[] = $thesis['member2_name'];
}
if (!empty($thesis['member3_name'])) {
    $presenters[] = $thesis['member3_name'];
}

// Find Faculty Adviser
$adviser_name = 'Engr. Faculty Adviser';
$mr_q = $conn->query("
    SELECT u.name FROM match_request mr 
    JOIN user u ON mr.faculty_id = u.id 
    WHERE (mr.student_id = {$thesis['student_id']}" . (!empty($thesis['group_id']) ? " OR mr.group_id = {$thesis['group_id']}" : "") . ") 
      AND mr.status = 'accepted' 
    LIMIT 1
");
if ($mr_q && $mr_row = $mr_q->fetch_assoc()) {
    $adviser_name = $mr_row['name'];
} else {
    $ds_q = $conn->query("
        SELECT adv.name FROM defense_schedule ds
        LEFT JOIN user adv ON ds.adviser_id = adv.id
        WHERE ds.student_id = {$thesis['student_id']}" . (!empty($thesis['group_id']) ? " OR ds.group_id = {$thesis['group_id']}" : "") . "
        ORDER BY ds.id DESC LIMIT 1
    ");
    if ($ds_q && $ds_row = $ds_q->fetch_assoc()) {
        if (!empty($ds_row['name'])) $adviser_name = $ds_row['name'];
    }
}

// Find Defense Date and Time
$defense_schedule_str = date("F j, Y — g:i A");
$ds_dt_q = $conn->query("
    SELECT meeting_date, start_time, end_time, venue, course_code, department FROM defense_schedule 
    WHERE student_id = {$thesis['student_id']}" . (!empty($thesis['group_id']) ? " OR group_id = {$thesis['group_id']}" : "") . "
    ORDER BY id DESC LIMIT 1
");
if ($ds_dt_q && $ds_dt = $ds_dt_q->fetch_assoc()) {
    $d_date = !empty($ds_dt['meeting_date']) ? date("F j, Y", strtotime($ds_dt['meeting_date'])) : date("F j, Y");
    $d_time = !empty($ds_dt['start_time']) ? date("g:i A", strtotime($ds_dt['start_time'])) : '';
    if (!empty($ds_dt['end_time'])) $d_time .= ' - ' . date("g:i A", strtotime($ds_dt['end_time']));
    $defense_schedule_str = trim("$d_date" . ($d_time ? " ($d_time)" : ""));
}

// Determine default Program
$dept_lower = strtolower($department);
$is_ee = strpos($dept_lower, 'electrical') !== false || strpos($dept_lower, 'bsee') !== false;
$is_cpe = strpos($dept_lower, 'computer') !== false || strpos($dept_lower, 'cpe') !== false;
$is_it = (!$is_ee && !$is_cpe);

// Load existing rubric scores or defaults
$existing_grade = $thesis['grade'] !== null ? (float)$thesis['grade'] : 85.0;
$existing_feedback = htmlspecialchars($thesis['feedback'] ?? '');

$saved_scores = [];
if (!empty($thesis['rubric_scores'])) {
    $decoded = json_decode($thesis['rubric_scores'], true);
    if (is_array($decoded) && isset($decoded['scores'])) {
        $saved_scores = $decoded['scores'];
    }
}

// Preset defaults or existing
$pfo1_1 = isset($saved_scores['pfo1_1']) ? (float)$saved_scores['pfo1_1'] : 9.0;
$pfo1_2 = isset($saved_scores['pfo1_2']) ? (float)$saved_scores['pfo1_2'] : 8.5;
$pfo1_3 = isset($saved_scores['pfo1_3']) ? (float)$saved_scores['pfo1_3'] : 8.5;

$lts2_1 = isset($saved_scores['lts2_1']) ? (float)$saved_scores['lts2_1'] : 8.5;
$lts2_2 = isset($saved_scores['lts2_2']) ? (float)$saved_scores['lts2_2'] : 4.5;
$lts2_3 = isset($saved_scores['lts2_3']) ? (float)$saved_scores['lts2_3'] : 4.0;

$msa3_1 = isset($saved_scores['msa3_1']) ? (float)$saved_scores['msa3_1'] : 8.5;
$msa3_2 = isset($saved_scores['msa3_2']) ? (float)$saved_scores['msa3_2'] : 17.0;
$msa3_3 = isset($saved_scores['msa3_3']) ? (float)$saved_scores['msa3_3'] : 8.5;

$ppr4_1 = isset($saved_scores['ppr4_1']) ? (float)$saved_scores['ppr4_1'] : 4.5;
$ppr4_2 = isset($saved_scores['ppr4_2']) ? (float)$saved_scores['ppr4_2'] : 4.0;
?>

<div class="ceit-rubric-wrapper">
    <!-- Top Action Bar -->
    <div class="rubric-action-bar no-print">
        <a href="?tab=assigned" class="btn btn-outline" style="background: #FFFFFF; color: #334155; border: 1px solid #CBD5E1; text-decoration: none; border-radius: 8px; font-size: 0.82rem; padding: 0.5rem 1rem; font-weight: 700;">
            <i class="fa-solid fa-arrow-left"></i> Back to Assigned Capstones
        </a>

        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
            <?php if(!empty($thesis['manuscript_file']) || !empty($thesis['shareable_link'])): ?>
                <button type="button" onclick="toggleGradingManuscriptDrawer()" class="btn" style="background: #DC2626; color: white; border: none; font-weight: 800; border-radius: 8px; font-size: 0.82rem; padding: 0.5rem 1rem; display: inline-flex; align-items: center; gap: 0.45rem; cursor: pointer; box-shadow: 0 4px 10px rgba(220, 38, 38, 0.25);">
                    <i class="fa-solid fa-book-open"></i> Read Manuscript (Ch 1 - Refs)
                </button>
            <?php else: ?>
                <span style="background: #FEF3C7; color: #92400E; border: 1px solid #FCD34D; font-size: 0.78rem; font-weight: 700; padding: 0.45rem 0.8rem; border-radius: 8px; display: inline-flex; align-items: center; gap: 0.35rem;">
                    <i class="fa-solid fa-triangle-exclamation"></i> No Manuscript on File
                </span>
            <?php endif; ?>

            <a href="?tab=comments&student_id=<?php echo $thesis['student_id']; ?>" class="btn btn-outline" style="font-size: 0.82rem; padding: 0.5rem 1rem; border-radius: 8px; background: #F0FDF4; color: #166534; border-color: #BBF7D0; text-decoration: none; font-weight: 700;">
                <i class="fa-solid fa-comments"></i> Chapter Comments &amp; Voice
            </a>
            <a href="?tab=meeting_doc&student_id=<?php echo $thesis['student_id']; ?>" class="btn btn-outline" style="font-size: 0.82rem; padding: 0.5rem 1rem; border-radius: 8px; background: #FEF3C7; color: #92400E; border-color: #FDE68A; text-decoration: none; font-weight: 700;">
                <i class="fa-solid fa-microphone-lines"></i> Deliberation MOM &amp; Transcription
            </a>
            <button type="button" onclick="window.print()" class="btn btn-outline" style="background: #FFFFFF; color: #065F46; border: 1.5px solid #059669; border-radius: 8px; font-size: 0.82rem; padding: 0.5rem 1rem; font-weight: 800;">
                <i class="fa-solid fa-print"></i> Print Official CEIT-T07A Sheet
            </button>
        </div>
    </div>

    <!-- Collapsible In-Portal Manuscript Examiner Drawer -->
    <?php if(!empty($thesis['manuscript_file']) || !empty($thesis['shareable_link'])): ?>
    <div id="gradingManuscriptDrawer" class="no-print" style="display: none; background: #FFFFFF; border: 2px solid #DC2626; border-radius: 12px; margin-bottom: 1.5rem; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.12);">
        <div style="background: #991B1B; color: white; padding: 0.75rem 1.25rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-book-open-reader" style="font-size: 1.1rem; color: #FCA5A5;"></i>
                <strong style="font-size: 0.95rem;">Candidate Manuscript Viewer (Chapters 1 to References)</strong>
                <span class="badge" style="background: #FEE2E2; color: #991B1B; font-size: 0.72rem; font-weight: 800; padding: 2px 8px; border-radius: 4px;">
                    <?php echo htmlspecialchars($thesis['manuscript_stage']); ?>
                </span>
            </div>
            <div style="display: flex; gap: 0.5rem; align-items: center;">
                <?php if(!empty($thesis['manuscript_file'])): ?>
                    <a href="<?php echo htmlspecialchars($thesis['manuscript_file']); ?>" target="_blank" class="btn" style="background: white; color: #991B1B; font-size: 0.76rem; font-weight: 800; padding: 0.35rem 0.75rem; border-radius: 6px; text-decoration: none;">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i> Open In New Tab
                    </a>
                <?php endif; ?>
                <?php if(!empty($thesis['shareable_link'])): ?>
                    <a href="<?php echo htmlspecialchars($thesis['shareable_link']); ?>" target="_blank" rel="noopener noreferrer" class="btn" style="background: #2563EB; color: white; font-size: 0.76rem; font-weight: 800; padding: 0.35rem 0.75rem; border-radius: 6px; text-decoration: none;">
                        <i class="fa-solid fa-link"></i> Live Google Docs
                    </a>
                <?php endif; ?>
                <button type="button" onclick="toggleGradingManuscriptDrawer()" style="background: transparent; border: none; color: #FECACA; font-size: 1.4rem; cursor: pointer; line-height: 1;" title="Close Viewer">
                    &times;
                </button>
            </div>
        </div>

        <?php if(!empty($thesis['manuscript_file'])): ?>
            <div style="background: #475569; height: 650px;">
                <iframe id="drawerIframe" src="" data-src="<?php echo htmlspecialchars($thesis['manuscript_file']); ?>" style="width: 100%; height: 100%; border: none;" title="Manuscript Document Viewer"></iframe>
            </div>
        <?php else: ?>
            <div style="padding: 2rem; text-align: center; background: #F8FAFC;">
                <p style="margin: 0 0 1rem 0; color: #334155; font-size: 0.9rem;">This candidate submitted a live cloud document link.</p>
                <a href="<?php echo htmlspecialchars($thesis['shareable_link']); ?>" target="_blank" rel="noopener noreferrer" class="btn-primary" style="background: #2563EB; border: none; padding: 0.65rem 1.25rem; font-size: 0.85rem; font-weight: 800; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 0.45rem;">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Open Live Manuscript on Google Docs / Word Online
                </a>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Official CEIT Document Sheet -->
    <div class="ceit-official-sheet">
        <!-- 1. Institutional Header -->
        <div class="inst-header">
            <div class="inst-header-left">
                <img src="logo.png" alt="CSU CEIT Seal" class="inst-logo">
                <div class="inst-titles">
                    <h3>Republic of the Philippines</h3>
                    <h1>CARAGA STATE UNIVERSITY</h1>
                    <p class="inst-sub">Ampayon, Butuan City 8600, Philippines &bull; Competence Service Uprightness</p>
                    <p class="inst-college">COLLEGE OF ENGINEERING AND INFORMATION TECHNOLOGY</p>
                </div>
            </div>
            <div class="inst-form-code">
                <span class="badge-code">CEIT-T07A</span>
                <div>Thesis and Capstone Proposal Hearing</div>
                <div style="font-weight: 600; color: #475569;">(Evaluation Rubric and Score Sheet)</div>
            </div>
        </div>

        <!-- 2. Document Title Banner -->
        <div class="doc-title-banner">
            <h2>THESIS/CAPSTONE PROPOSAL HEARING</h2>
            <p>Evaluation Rubric and Score Sheet</p>
        </div>

        <!-- Start of Evaluation Form -->
        <form id="officialRubricForm" action="action.php" method="POST">
            <input type="hidden" name="advanced_panel_grade_submit" value="1">
            <input type="hidden" name="thesis_panel_id" value="<?php echo $tp_id; ?>">
            <input type="hidden" name="final_grade" id="hiddenFinalGrade" value="<?php echo $existing_grade; ?>">
            <input type="hidden" name="rubric_scores_json" id="hiddenRubricJson" value="">

            <!-- 3. Metadata Table -->
            <table class="metadata-table">
                <tr>
                    <td class="meta-label">Program:</td>
                    <td class="meta-value">
                        <label style="margin-right: 1.5rem; font-weight: 600; cursor: pointer;">
                            <input type="radio" name="program" value="BSEE" <?php echo $is_ee ? 'checked' : ''; ?>> [ ] BSEE
                        </label>
                        <label style="margin-right: 1.5rem; font-weight: 600; cursor: pointer;">
                            <input type="radio" name="program" value="BSCpE" <?php echo $is_cpe ? 'checked' : ''; ?>> [ ] BSCpE
                        </label>
                        <label style="font-weight: 600; cursor: pointer;">
                            <input type="radio" name="program" value="BSIT" <?php echo $is_it ? 'checked' : ''; ?>> [ ] BSIT
                        </label>
                    </td>
                </tr>
                <tr>
                    <td class="meta-label">Date and Time:</td>
                    <td class="meta-value">
                        <strong><?php echo htmlspecialchars($defense_schedule_str); ?></strong>
                    </td>
                </tr>
                <tr>
                    <td class="meta-label">Thesis/Capstone Title:</td>
                    <td class="meta-value" style="font-size: 0.95rem; font-weight: 800; color: #064E3B;">
                        <?php echo $thesis_topic; ?>
                    </td>
                </tr>
                <tr>
                    <td class="meta-label">Member(s) / Presenter(s):</td>
                    <td class="meta-value">
                        <ol style="margin: 0; padding-left: 1.25rem; line-height: 1.6; font-weight: 600;">
                            <?php foreach ($presenters as $p): ?>
                                <li><?php echo htmlspecialchars($p); ?></li>
                            <?php endforeach; ?>
                        </ol>
                    </td>
                </tr>
                <tr>
                    <td class="meta-label">Adviser:</td>
                    <td class="meta-value">
                        <strong><?php echo htmlspecialchars($adviser_name); ?></strong>
                    </td>
                </tr>
                <tr>
                    <td class="meta-label">Panel Member:</td>
                    <td class="meta-value" style="color: #065F46; font-weight: 800;">
                        <i class="fa-solid fa-user-check"></i> <?php echo htmlspecialchars($panel_name); ?>
                    </td>
                </tr>
            </table>

            <!-- ============================================================== -->
            <!-- PART 1: GRADING RUBRIC (100 pts. Total)                        -->
            <!-- ============================================================== -->
            <div class="part-header">
                <span>PART 1: GRADING RUBRIC (100 pts. Total)</span>
                <span style="font-size: 0.8rem; background: #047857; padding: 2px 8px; border-radius: 4px;">Oral Defense Examination</span>
            </div>
            <div class="part-instructions">
                <strong>Instructions for Panel:</strong> Score the proponents based on the rigor of their printed manuscript (Chapters 1-3) and their ability to defend their proposed architecture and methodology during the oral presentation.
            </div>

            <table class="rubric-table">
                <thead>
                    <tr>
                        <th style="width: 85px;">Item Code</th>
                        <th>Area and Max Score</th>
                        <th style="width: 140px;">Score</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- CHAPTER 1: Problem Formulation and Objectives (30 pts.) -->
                    <tr class="chapter-header-row">
                        <td colspan="2">1. Chapter 1: Problem Formulation and Objectives (30 pts.)</td>
                        <td class="chapter-subtotal-cell" id="subtotalCh1">26.0 / 30 pts</td>
                    </tr>
                    <tr>
                        <td class="item-code-cell">PFO1.1</td>
                        <td class="criterion-desc-cell">
                            <div class="criterion-title">Introduction and Project Context [10 pts.]:</div>
                            <div class="criterion-detail">Clearly defines the industry/sector overview and establishes a valid, real-world problem with properly cited current pain points.</div>
                        </td>
                        <td class="score-input-cell">
                            <div class="score-control-box">
                                <input type="number" step="0.5" min="0" max="10" id="pfo1_1" class="score-num-input ch1-score" value="<?php echo $pfo1_1; ?>" oninput="calculateRubricTotal()">
                                <span class="max-score-hint">/ 10</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="item-code-cell">PFO1.2</td>
                        <td class="criterion-desc-cell">
                            <div class="criterion-title">Objectives [10 pts.]:</div>
                            <div class="criterion-detail">General and Specific Objectives are measurable, achievable, and logically mapped to the development phases.</div>
                        </td>
                        <td class="score-input-cell">
                            <div class="score-control-box">
                                <input type="number" step="0.5" min="0" max="10" id="pfo1_2" class="score-num-input ch1-score" value="<?php echo $pfo1_2; ?>" oninput="calculateRubricTotal()">
                                <span class="max-score-hint">/ 10</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="item-code-cell">PFO1.3</td>
                        <td class="criterion-desc-cell">
                            <div class="criterion-title">Scope and Significance [10 pts.]:</div>
                            <div class="criterion-detail">Boundaries and constraints are clearly set. Beneficiaries are well-identified and mapped to a specific SDG or University Research Agenda.</div>
                        </td>
                        <td class="score-input-cell">
                            <div class="score-control-box">
                                <input type="number" step="0.5" min="0" max="10" id="pfo1_3" class="score-num-input ch1-score" value="<?php echo $pfo1_3; ?>" oninput="calculateRubricTotal()">
                                <span class="max-score-hint">/ 10</span>
                            </div>
                        </td>
                    </tr>

                    <!-- CHAPTER 2: Literature and Theoretical Soundness (20 pts.) -->
                    <tr class="chapter-header-row">
                        <td colspan="2">2. Chapter 2: Literature and Theoretical Soundness (20 pts.)</td>
                        <td class="chapter-subtotal-cell" id="subtotalCh2">17.0 / 20 pts</td>
                    </tr>
                    <tr>
                        <td class="item-code-cell">LTS2.1</td>
                        <td class="criterion-desc-cell">
                            <div class="criterion-title">RRL and RRS [10 pts.]:</div>
                            <div class="criterion-detail">Academic literature and existing systems are highly relevant, current (last 5 years), and comprehensively analyzed.</div>
                        </td>
                        <td class="score-input-cell">
                            <div class="score-control-box">
                                <input type="number" step="0.5" min="0" max="10" id="lts2_1" class="score-num-input ch2-score" value="<?php echo $lts2_1; ?>" oninput="calculateRubricTotal()">
                                <span class="max-score-hint">/ 10</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="item-code-cell">LTS2.2</td>
                        <td class="criterion-desc-cell">
                            <div class="criterion-title">Synthesis and Research Gap [5 pts.]:</div>
                            <div class="criterion-detail">The proponents successfully synthesized the reviews and explicitly proved a valid "Research Gap" that their project will address.</div>
                        </td>
                        <td class="score-input-cell">
                            <div class="score-control-box">
                                <input type="number" step="0.5" min="0" max="5" id="lts2_2" class="score-num-input ch2-score" value="<?php echo $lts2_2; ?>" oninput="calculateRubricTotal()">
                                <span class="max-score-hint">/ 5</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="item-code-cell">LTS2.3</td>
                        <td class="criterion-desc-cell">
                            <div class="criterion-title">Theoretical/Conceptual and Technical Background [5 pts.]:</div>
                            <div class="criterion-detail">Methodological soundness is proven via a clear IPO diagram, established scientific theories, and a solid understanding of the chosen modern components/technologies.</div>
                        </td>
                        <td class="score-input-cell">
                            <div class="score-control-box">
                                <input type="number" step="0.5" min="0" max="5" id="lts2_3" class="score-num-input ch2-score" value="<?php echo $lts2_3; ?>" oninput="calculateRubricTotal()">
                                <span class="max-score-hint">/ 5</span>
                            </div>
                        </td>
                    </tr>

                    <!-- CHAPTER 3: Methodology and System Architecture (40 pts.) -->
                    <tr class="chapter-header-row">
                        <td colspan="2">3. Chapter 3: Methodology and System Architecture (40 pts.)</td>
                        <td class="chapter-subtotal-cell" id="subtotalCh3">34.0 / 40 pts</td>
                    </tr>
                    <tr>
                        <td class="item-code-cell">MSA3.1</td>
                        <td class="criterion-desc-cell">
                            <div class="criterion-title">Research Design and Development Phases [10 pts.]:</div>
                            <div class="criterion-detail">Appropriate research approach (e.g., SDLC for IT, Experimental/Engineering Design for BSCpE/BSEE) is justified and detailed step-by-step.</div>
                        </td>
                        <td class="score-input-cell">
                            <div class="score-control-box">
                                <input type="number" step="0.5" min="0" max="10" id="msa3_1" class="score-num-input ch3-score" value="<?php echo $msa3_1; ?>" oninput="calculateRubricTotal()">
                                <span class="max-score-hint">/ 10</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="item-code-cell">MSA3.2</td>
                        <td class="criterion-desc-cell">
                            <div class="criterion-title">Technical Description and Architecture [20 pts.]:</div>
                            <div class="criterion-detail">Visual blueprints (ERDs, Schematics, AI models, UI/UX wireframes, etc.) are logically sound. Hardware/Software requirements are fully itemized.</div>
                        </td>
                        <td class="score-input-cell">
                            <div class="score-control-box">
                                <input type="number" step="0.5" min="0" max="20" id="msa3_2" class="score-num-input ch3-score" value="<?php echo $msa3_2; ?>" oninput="calculateRubricTotal()">
                                <span class="max-score-hint">/ 20</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="item-code-cell">MSA3.3</td>
                        <td class="criterion-desc-cell">
                            <div class="criterion-title">Requirements and Mathematics/Statistics [10 pts.]:</div>
                            <div class="criterion-detail">Functional and Non-Functional Requirements are distinct and clear. The mathematical foundation (load computations/algorithms) and Statistical Treatment for future testing are correct.</div>
                        </td>
                        <td class="score-input-cell">
                            <div class="score-control-box">
                                <input type="number" step="0.5" min="0" max="10" id="msa3_3" class="score-num-input ch3-score" value="<?php echo $msa3_3; ?>" oninput="calculateRubricTotal()">
                                <span class="max-score-hint">/ 10</span>
                            </div>
                        </td>
                    </tr>

                    <!-- 4. Oral Presentation and Proposal Readiness (10 pts.) -->
                    <tr class="chapter-header-row">
                        <td colspan="2">4. Oral Presentation and Proposal Readiness (10 pts.)</td>
                        <td class="chapter-subtotal-cell" id="subtotalOral">8.5 / 10 pts</td>
                    </tr>
                    <tr>
                        <td class="item-code-cell">PPR4.1</td>
                        <td class="criterion-desc-cell">
                            <div class="criterion-title">Defense and Justification [5 pts.]:</div>
                            <div class="criterion-detail">Ability to confidently answer the panel's technical queries and successfully justify their chosen architecture and methodology.</div>
                        </td>
                        <td class="score-input-cell">
                            <div class="score-control-box">
                                <input type="number" step="0.5" min="0" max="5" id="ppr4_1" class="score-num-input oral-score" value="<?php echo $ppr4_1; ?>" oninput="calculateRubricTotal()">
                                <span class="max-score-hint">/ 5</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="item-code-cell">PPR4.2</td>
                        <td class="criterion-desc-cell">
                            <div class="criterion-title">Delivery, Mastery, and Manuscript Compliance [5 pts.]:</div>
                            <div class="criterion-detail">Proponents demonstrated professional oral delivery and a deep understanding of the proposed solution and the printed manuscript strictly adheres to IEEE formatting, grammatical standards, academic integrity checks, and includes all required CEIT forms and appendices.</div>
                        </td>
                        <td class="score-input-cell">
                            <div class="score-control-box">
                                <input type="number" step="0.5" min="0" max="5" id="ppr4_2" class="score-num-input oral-score" value="<?php echo $ppr4_2; ?>" oninput="calculateRubricTotal()">
                                <span class="max-score-hint">/ 5</span>
                            </div>
                        </td>
                    </tr>

                    <!-- TOTAL SCORE ROW -->
                    <tr style="background: #F0FDF4; font-weight: 900; font-size: 1.05rem;">
                        <td colspan="2" style="text-align: right; text-transform: uppercase; color: #064E3B; padding: 0.85rem 1rem;">
                            TOTAL CALCULATED SCORE:
                        </td>
                        <td style="text-align: center; color: #047857; font-size: 1.25rem;">
                            <span id="rubricTotalDisplay">85.5</span> / 100
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Total Score Banner Callout -->
            <div class="total-score-callout">
                <div>
                    <div style="font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.9;">Total Calculated Score</div>
                    <div style="font-size: 1.1rem; font-weight: 700; margin-top: 0.25rem;">CEIT Proposal Hearing Grade</div>
                </div>
                <div style="display: flex; align-items: baseline; gap: 0.5rem;">
                    <span class="score-big" id="calloutBigScore">85.5</span>
                    <span style="font-size: 1.2rem; font-weight: 700; opacity: 0.85;">/ 100</span>
                </div>
            </div>

            <!-- ============================================================== -->
            <!-- PART 2: FINAL SCORE and VERDICT                                -->
            <!-- ============================================================== -->
            <div class="part-header">
                <span>PART 2: FINAL SCORE and VERDICT</span>
                <span style="font-size: 0.8rem; background: #047857; padding: 2px 8px; border-radius: 4px;">Deliberation Decision</span>
            </div>

            <div style="padding: 1rem 0 0.5rem 0; font-size: 0.95rem; font-weight: 700; color: #0F172A;">
                Final Calculated Score: <span id="finalScoreText" style="color: #047857; font-size: 1.15rem; font-weight: 900;">85.5</span> / 100
            </div>
            <div style="font-size: 0.82rem; color: #475569; margin-bottom: 0.5rem;">
                Check the appropriate verdict based on the final score:
            </div>

            <div class="verdict-options-grid">
                <!-- Verdict 1: 95 - 100 -->
                <label class="verdict-card" id="card_v1">
                    <input type="radio" name="verdict_radio" value="PASSED/APPROVED (No Revision)" onchange="onVerdictRadioChange(this)">
                    <div>
                        <div class="verdict-title">🟢 [ ] PASSED/APPROVED (No Revision) [95 - 100] - Outstanding Proposal</div>
                        <p class="verdict-desc">&bull; No changes required; the group is cleared to immediately proceed to Chapter 4 / Prototype Development and the rest of the documentary requirements.</p>
                    </div>
                </label>

                <!-- Verdict 2: 80 - 94 -->
                <label class="verdict-card" id="card_v2">
                    <input type="radio" name="verdict_radio" value="PASSED/APPROVED (Minor Revision)" onchange="onVerdictRadioChange(this)">
                    <div>
                        <div class="verdict-title">🟡 [ ] PASSED/APPROVED (Minor Revision) [80 - 94] - Successful Proposal</div>
                        <p class="verdict-desc">&bull; Comply with cited minor revisions (typographical, formatting, or slight scope adjustments) prior to proceeding to development.</p>
                    </div>
                </label>

                <!-- Verdict 3: 60 - 79 -->
                <label class="verdict-card" id="card_v3">
                    <input type="radio" name="verdict_radio" value="PASSED/APPROVED (Major Revision)" onchange="onVerdictRadioChange(this)">
                    <div>
                        <div class="verdict-title">🟠 [ ] PASSED/APPROVED (Major Revision) [60 - 79] - Core Methodological Adjustment</div>
                        <p class="verdict-desc">&bull; Significant corrections needed in the system architecture, objectives, or methodology. Must be revised and approved by the adviser before actual development can begin.</p>
                    </div>
                </label>

                <!-- Verdict 4: 50 - 59 -->
                <label class="verdict-card" id="card_v4">
                    <input type="radio" name="verdict_radio" value="REDEFENSE" onchange="onVerdictRadioChange(this)">
                    <div>
                        <div class="verdict-title">🔴 [ ] REDEFENSE [50 - 59] - Subject for Special Schedule of Presentation</div>
                        <p class="verdict-desc">&bull; The proposed research gap is invalid, or the methodology is fundamentally flawed. The group must propose a revised architecture or a new topic and present it again to the panel.</p>
                    </div>
                </label>

                <!-- Verdict 5: Below 50 -->
                <label class="verdict-card" id="card_v5">
                    <input type="radio" name="verdict_radio" value="FAILED" onchange="onVerdictRadioChange(this)">
                    <div>
                        <div class="verdict-title">❌ [ ] FAILED [Below 50] - Subject for Course Re-enrollment</div>
                        <p class="verdict-desc">&bull; The proposal lacks academic integrity, is plagiarized, or no presentation was conducted.</p>
                    </div>
                </label>
            </div>

            <!-- ============================================================== -->
            <!-- PART 3: REVISIONS / COMMENTS & SUGGESTIONS / RECOMMENDATIONS   -->
            <!-- ============================================================== -->
            <div class="part-header" style="margin-top: 2rem;">
                <span>PART 3: REVISIONS / COMMENTS and SUGGESTIONS / RECOMMENDATIONS</span>
                <span style="font-size: 0.8rem; background: #047857; padding: 2px 8px; border-radius: 4px;">Directives for Compliance</span>
            </div>

            <table class="revisions-table" id="revisionsTable">
                <thead>
                    <tr>
                        <th style="width: 140px; text-align: center;">Item Code</th>
                        <th>Revisions / Comments and Suggestions / Recommendations</th>
                        <th class="no-print" style="width: 50px; text-align: center;">Act</th>
                    </tr>
                </thead>
                <tbody id="revisionsBody">
                    <tr>
                        <td style="text-align: center;">
                            <select class="form-control rev-code" style="font-weight: 700; font-size: 0.82rem; padding: 0.35rem 0.5rem;">
                                <option value="General">General</option>
                                <option value="PFO1.1">PFO1.1 - Project Context</option>
                                <option value="PFO1.2">PFO1.2 - Objectives</option>
                                <option value="PFO1.3">PFO1.3 - Scope &amp; Significance</option>
                                <option value="LTS2.1">LTS2.1 - RRL &amp; RRS</option>
                                <option value="LTS2.2">LTS2.2 - Research Gap</option>
                                <option value="LTS2.3">LTS2.3 - Conceptual &amp; IPO</option>
                                <option value="MSA3.1">MSA3.1 - Research Design</option>
                                <option value="MSA3.2">MSA3.2 - System Architecture</option>
                                <option value="MSA3.3">MSA3.3 - Requirements &amp; Math</option>
                                <option value="PPR4.1">PPR4.1 - Defense Justification</option>
                                <option value="PPR4.2">PPR4.2 - IEEE Format &amp; Forms</option>
                            </select>
                        </td>
                        <td>
                            <input type="text" class="form-control rev-text" placeholder="Specify required correction, missing reference, or architecture adjustment..." style="width: 100%; font-size: 0.82rem; padding: 0.4rem 0.65rem;">
                        </td>
                        <td class="no-print" style="text-align: center;">
                            <button type="button" class="btn" onclick="removeRevisionRow(this)" style="color: #DC2626; padding: 0.25rem 0.5rem; background: transparent; border: none; font-size: 0.85rem;" title="Remove row">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div class="no-print" style="display: flex; justify-content: flex-end; margin-top: -0.75rem; margin-bottom: 1.25rem;">
                <button type="button" onclick="addRevisionRow()" class="btn btn-outline" style="font-size: 0.78rem; font-weight: 700; color: #065F46; border-color: #A7F3D0; background: #F0FDF4; border-radius: 6px; padding: 0.35rem 0.75rem;">
                    <i class="fa-solid fa-plus"></i> Add Item Revision Row
                </button>
            </div>

            <!-- Comprehensive Feedback Textarea -->
            <div style="margin-top: 1rem;">
                <label style="font-weight: 800; color: #0F172A; font-size: 0.85rem; display: block; margin-bottom: 0.4rem;">
                    Overall Panel Feedback &amp; Synthesis Summary:
                </label>
                <textarea name="feedback" id="overallFeedback" rows="3" class="form-control" placeholder="Provide general assessment, commendable strengths, and comprehensive directives for the student team..." style="width: 100%; border-radius: 8px; border: 1.5px solid #CBD5E1; font-size: 0.85rem; padding: 0.75rem; font-family: inherit;"><?php echo $existing_feedback; ?></textarea>
            </div>

            <!-- Panel Member Signature Section -->
            <div class="signatures-row">
                <div class="sig-block">
                    <div class="sig-line">
                        <?php echo htmlspecialchars($panel_name); ?>
                    </div>
                    <div class="sig-name"><?php echo htmlspecialchars($panel_name); ?></div>
                    <div class="sig-title">Panel Member's Signature / Evaluator</div>
                </div>
            </div>

            <!-- Institutional Document Footer -->
            <div class="inst-footer">
                <div>
                    <strong>Email:</strong> csuccceit@carsu.edu.ph &bull; <strong>Contact no.:</strong> 818-0205
                </div>
                <div>
                    <strong>CEIT-T07A:</strong> Thesis and Capstone Proposal Hearing (Evaluation Rubric and Score Sheet)
                </div>
            </div>

            <!-- Submit Action Group (Buttons) -->
            <div class="btn-group no-print" style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1.5px solid #E2E8F0;">
                <a href="?tab=assigned" class="btn btn-secondary-grade" style="text-decoration:none; display: inline-flex; align-items: center; gap: 0.4rem;">
                    <i class="fas fa-arrow-left"></i> Cancel
                </a>
                <button type="button" class="btn btn-secondary-grade" onclick="resetOfficialRubric()" style="display: inline-flex; align-items: center; gap: 0.4rem;">
                    <i class="fas fa-undo"></i> Reset Default Scores
                </button>
                <button type="button" class="btn btn-success-grade" onclick="submitOfficialRubric()" style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 12px 35px; font-size: 0.95rem; font-weight: 800; background: linear-gradient(135deg, #059669 0%, #047857 100%);">
                    <i class="fas fa-check-double"></i> Submit CEIT-T07A Evaluation
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Live calculation of CEIT-T07A Rubric Scores
function calculateRubricTotal() {
    // 1. Chapter 1
    const pfo1_1 = parseFloat(document.getElementById('pfo1_1').value) || 0;
    const pfo1_2 = parseFloat(document.getElementById('pfo1_2').value) || 0;
    const pfo1_3 = parseFloat(document.getElementById('pfo1_3').value) || 0;
    const ch1_total = Math.min(30, pfo1_1 + pfo1_2 + pfo1_3);
    document.getElementById('subtotalCh1').innerText = ch1_total.toFixed(1) + ' / 30 pts';

    // 2. Chapter 2
    const lts2_1 = parseFloat(document.getElementById('lts2_1').value) || 0;
    const lts2_2 = parseFloat(document.getElementById('lts2_2').value) || 0;
    const lts2_3 = parseFloat(document.getElementById('lts2_3').value) || 0;
    const ch2_total = Math.min(20, lts2_1 + lts2_2 + lts2_3);
    document.getElementById('subtotalCh2').innerText = ch2_total.toFixed(1) + ' / 20 pts';

    // 3. Chapter 3
    const msa3_1 = parseFloat(document.getElementById('msa3_1').value) || 0;
    const msa3_2 = parseFloat(document.getElementById('msa3_2').value) || 0;
    const msa3_3 = parseFloat(document.getElementById('msa3_3').value) || 0;
    const ch3_total = Math.min(40, msa3_1 + msa3_2 + msa3_3);
    document.getElementById('subtotalCh3').innerText = ch3_total.toFixed(1) + ' / 40 pts';

    // 4. Oral Presentation
    const ppr4_1 = parseFloat(document.getElementById('ppr4_1').value) || 0;
    const ppr4_2 = parseFloat(document.getElementById('ppr4_2').value) || 0;
    const oral_total = Math.min(10, ppr4_1 + ppr4_2);
    document.getElementById('subtotalOral').innerText = oral_total.toFixed(1) + ' / 10 pts';

    // Final Total Score
    const total_score = Math.min(100, ch1_total + ch2_total + ch3_total + oral_total);
    const scoreStr = total_score.toFixed(1);

    document.getElementById('rubricTotalDisplay').innerText = scoreStr;
    document.getElementById('calloutBigScore').innerText = scoreStr;
    document.getElementById('finalScoreText').innerText = scoreStr;
    document.getElementById('hiddenFinalGrade').value = scoreStr;

    // Automatic Verdict Selection
    autoSelectVerdict(total_score);

    // Update JSON summary
    updateRubricJson();
}

function autoSelectVerdict(score) {
    document.querySelectorAll('.verdict-card').forEach(c => c.classList.remove('selected'));
    let selId = 'card_v5';
    let radVal = 'FAILED';

    if (score >= 95) {
        selId = 'card_v1';
        radVal = 'PASSED/APPROVED (No Revision)';
    } else if (score >= 80) {
        selId = 'card_v2';
        radVal = 'PASSED/APPROVED (Minor Revision)';
    } else if (score >= 60) {
        selId = 'card_v3';
        radVal = 'PASSED/APPROVED (Major Revision)';
    } else if (score >= 50) {
        selId = 'card_v4';
        radVal = 'REDEFENSE';
    }

    const card = document.getElementById(selId);
    if (card) {
        card.classList.add('selected');
        const radio = card.querySelector('input[type="radio"]');
        if (radio) radio.checked = true;
    }
}

function onVerdictRadioChange(radio) {
    document.querySelectorAll('.verdict-card').forEach(c => c.classList.remove('selected'));
    if (radio.checked) {
        radio.closest('.verdict-card').classList.add('selected');
    }
    updateRubricJson();
}

function addRevisionRow() {
    const tbody = document.getElementById('revisionsBody');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td style="text-align: center;">
            <select class="form-control rev-code" style="font-weight: 700; font-size: 0.82rem; padding: 0.35rem 0.5rem;">
                <option value="General">General</option>
                <option value="PFO1.1">PFO1.1 - Project Context</option>
                <option value="PFO1.2">PFO1.2 - Objectives</option>
                <option value="PFO1.3">PFO1.3 - Scope &amp; Significance</option>
                <option value="LTS2.1">LTS2.1 - RRL &amp; RRS</option>
                <option value="LTS2.2">LTS2.2 - Research Gap</option>
                <option value="LTS2.3">LTS2.3 - Conceptual &amp; IPO</option>
                <option value="MSA3.1">MSA3.1 - Research Design</option>
                <option value="MSA3.2">MSA3.2 - System Architecture</option>
                <option value="MSA3.3">MSA3.3 - Requirements &amp; Math</option>
                <option value="PPR4.1">PPR4.1 - Defense Justification</option>
                <option value="PPR4.2">PPR4.2 - IEEE Format &amp; Forms</option>
            </select>
        </td>
        <td>
            <input type="text" class="form-control rev-text" placeholder="Specify required correction, missing reference, or architecture adjustment..." style="width: 100%; font-size: 0.82rem; padding: 0.4rem 0.65rem;">
        </td>
        <td class="no-print" style="text-align: center;">
            <button type="button" class="btn" onclick="removeRevisionRow(this)" style="color: #DC2626; padding: 0.25rem 0.5rem; background: transparent; border: none; font-size: 0.85rem;" title="Remove row">
                <i class="fa-solid fa-trash-can"></i>
            </button>
        </td>
    `;
    tbody.appendChild(tr);
}

function removeRevisionRow(btn) {
    const row = btn.closest('tr');
    if (row && document.querySelectorAll('#revisionsBody tr').length > 1) {
        row.remove();
        updateRubricJson();
    }
}

function updateRubricJson() {
    const scores = {
        pfo1_1: parseFloat(document.getElementById('pfo1_1').value) || 0,
        pfo1_2: parseFloat(document.getElementById('pfo1_2').value) || 0,
        pfo1_3: parseFloat(document.getElementById('pfo1_3').value) || 0,
        lts2_1: parseFloat(document.getElementById('lts2_1').value) || 0,
        lts2_2: parseFloat(document.getElementById('lts2_2').value) || 0,
        lts2_3: parseFloat(document.getElementById('lts2_3').value) || 0,
        msa3_1: parseFloat(document.getElementById('msa3_1').value) || 0,
        msa3_2: parseFloat(document.getElementById('msa3_2').value) || 0,
        msa3_3: parseFloat(document.getElementById('msa3_3').value) || 0,
        ppr4_1: parseFloat(document.getElementById('ppr4_1').value) || 0,
        ppr4_2: parseFloat(document.getElementById('ppr4_2').value) || 0,
    };

    const selectedRad = document.querySelector('input[name="verdict_radio"]:checked');
    const verdict = selectedRad ? selectedRad.value : '';

    const revisions = [];
    document.querySelectorAll('#revisionsBody tr').forEach(r => {
        const code = r.querySelector('.rev-code') ? r.querySelector('.rev-code').value : '';
        const text = r.querySelector('.rev-text') ? r.querySelector('.rev-text').value.trim() : '';
        if (text) {
            revisions.push({ code: code, comment: text });
        }
    });

    const data = {
        form_code: 'CEIT-T07A',
        scores: scores,
        total_score: parseFloat(document.getElementById('hiddenFinalGrade').value) || 0,
        verdict: verdict,
        item_revisions: revisions
    };

    document.getElementById('hiddenRubricJson').value = JSON.stringify(data);
}

function resetOfficialRubric() {
    document.getElementById('pfo1_1').value = 9.0;
    document.getElementById('pfo1_2').value = 8.5;
    document.getElementById('pfo1_3').value = 8.5;
    document.getElementById('lts2_1').value = 8.5;
    document.getElementById('lts2_2').value = 4.5;
    document.getElementById('lts2_3').value = 4.0;
    document.getElementById('msa3_1').value = 8.5;
    document.getElementById('msa3_2').value = 17.0;
    document.getElementById('msa3_3').value = 8.5;
    document.getElementById('ppr4_1').value = 4.5;
    document.getElementById('ppr4_2').value = 4.0;
    calculateRubricTotal();
}

function submitOfficialRubric() {
    updateRubricJson();

    const selectedRad = document.querySelector('input[name="verdict_radio"]:checked');
    const verdict = selectedRad ? selectedRad.value : 'PASSED/APPROVED';
    const finalScore = document.getElementById('hiddenFinalGrade').value;

    // Collect all revisions and append to overall feedback if helpful
    const fbText = document.getElementById('overallFeedback').value.trim();
    let compiled = `[VERDICT: ${verdict}] — Final Score: ${finalScore}/100\n`;
    
    const revs = [];
    document.querySelectorAll('#revisionsBody tr').forEach(r => {
        const c = r.querySelector('.rev-code') ? r.querySelector('.rev-code').value : '';
        const t = r.querySelector('.rev-text') ? r.querySelector('.rev-text').value.trim() : '';
        if (t) revs.push(`• [${c}]: ${t}`);
    });

    if (revs.length > 0) {
        compiled += "\nRequired Revisions & Action Items:\n" + revs.join("\n") + "\n";
    }
    if (fbText) {
        compiled += "\nGeneral Evaluation Notes:\n" + fbText;
    }

    document.getElementById('overallFeedback').value = compiled;
    document.getElementById('officialRubricForm').submit();
}

function toggleGradingManuscriptDrawer() {
    const drawer = document.getElementById('gradingManuscriptDrawer');
    if (!drawer) return;
    if (drawer.style.display === 'none' || drawer.style.display === '') {
        const iframe = document.getElementById('drawerIframe');
        if (iframe && !iframe.src && iframe.getAttribute('data-src')) {
            iframe.src = iframe.getAttribute('data-src');
        }
        drawer.style.display = 'block';
        drawer.scrollIntoView({ behavior: 'smooth' });
    } else {
        drawer.style.display = 'none';
    }
}

// Initial calculation on load
document.addEventListener('DOMContentLoaded', function() {
    calculateRubricTotal();
});
</script>
