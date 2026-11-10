<?php
$student_topic = trim($profile['thesis_topic'] ?? '');
$student_skills = trim($profile['skills'] ?? '');
$student_dept = trim($profile['department'] ?? '');
$student_doc = trim($student_skills . " " . $student_topic);

// Active Request / Assigned Adviser (Shared across all group members)
$ai_req_where = (!empty($group_id)) ? "(m.group_id = $group_id OR m.student_id IN ($group_student_ids_str))" : "m.student_id = $uid";
$active_req = $conn->query("SELECT m.*, u.name as adv_name, u.email as adv_email, ap.department, ap.expertise 
    FROM match_request m 
    JOIN user u ON m.adviser_id = u.id 
    LEFT JOIN adviser_profile ap ON u.id = ap.user_id 
    WHERE $ai_req_where AND m.status IN ('pending', 'accepted') 
    LIMIT 1")->fetch_assoc();

// Fetch and rank advisers by specialization
$has_student_input = (!empty($student_topic) || !empty($student_skills));
$where_dept = "";
if (!$has_student_input && !empty($student_dept)) {
    $esc_d = $conn->real_escape_string($student_dept);
    $clean_d = $conn->real_escape_string(preg_replace('/\s*\(.*?\)/', '', $student_dept));
    $where_dept = " AND (ap.department = '$esc_d' OR ap.department = '$clean_d' OR ap.department LIKE '%$clean_d%')";
}

$fac_q = $conn->query("SELECT u.id, u.name, u.email, ap.expertise, ap.department, ap.capacity, ap.academic_degree, ap.graduation_year, ap.google_scholar_url, ap.published_papers, ap.previous_advised_titles 
    FROM user u 
    LEFT JOIN adviser_profile ap ON u.id = ap.user_id 
    WHERE u.role = 'adviser' AND u.account_status = 'active' $where_dept
    ORDER BY u.name ASC");

$available_advisers = [];
if ($fac_q) {
    while ($fa = $fac_q->fetch_assoc()) {
        $adv_exp = trim($fa['expertise'] ?? '');
        $adv_pubs = trim($fa['published_papers'] ?? '');
        $adv_prev = trim($fa['previous_advised_titles'] ?? '');
        $match_res = calculate_specialization_match($student_topic, $student_skills, $adv_exp, (int)$fa['id'], $fa['department'] ?? '', $student_dept, $profile['abstract_text'] ?? '', $adv_pubs, $adv_prev);
        
        // Strict exclusion: if research title/keywords provided, only show advisers matching the area of specialization
        if ($has_student_input && !$match_res['matched']) {
            continue;
        }

        $fa['match_score'] = $match_res['score'];
        $fa['mcda_score'] = $match_res['mcda_score'] ?? $match_res['score'];
        $fa['mcda_rank_label'] = $match_res['mcda_rank_label'] ?? 'Suitable MCDA Match';
        $fa['mcda_criteria'] = $match_res['mcda_criteria'] ?? [];
        $fa['mcda_weights'] = $match_res['mcda_weights'] ?? ['specialization' => 35, 'publications' => 25, 'supervised_titles' => 15, 'workload_capacity' => 15, 'department_qualification' => 10];
        $fa['current_load'] = $match_res['current_load'] ?? 0;
        $fa['max_capacity'] = $match_res['max_capacity'] ?? ((int)($fa['capacity'] ?? 5));
        $fa['available_slots'] = $match_res['available_slots'] ?? max(0, $fa['max_capacity'] - $fa['current_load']);
        $fa['matched_terms'] = $match_res['matched_terms'];
        $fa['matched_publications'] = $match_res['matched_publications'] ?? [];
        $fa['matched_advised_titles'] = $match_res['matched_advised_titles'] ?? [];
        $available_advisers[] = $fa;
    }
    usort($available_advisers, function($a, $b) use ($student_dept) {
        if ($b['match_score'] !== $a['match_score']) {
            return $b['match_score'] <=> $a['match_score'];
        }
        $a_same = (!empty($student_dept) && strcasecmp($a['department'] ?? '', $student_dept) === 0) ? 1 : 0;
        $b_same = (!empty($student_dept) && strcasecmp($b['department'] ?? '', $student_dept) === 0) ? 1 : 0;
        return $b_same <=> $a_same;
    });
}
?>

<!-- BANNER -->
<div class="banner" style="background: linear-gradient(135deg, var(--csu-green-darker) 0%, var(--csu-green-dark) 55%, var(--csu-green) 100%); border-radius: 10px; padding: 0.85rem 1.4rem; color: white; margin-bottom: 1.15rem; box-shadow: var(--shadow-sm); position: relative; overflow: hidden;">
    <div style="position: absolute; right: -10px; bottom: -15px; opacity: 0.08; font-size: 5.5rem; pointer-events: none;">
        <i class="fa-solid fa-robot"></i>
    </div>
    <div style="position: relative; z-index: 1;">
        <span class="badge" style="background: rgba(245, 158, 11, 0.2); color: #FCD34D; border: 1px solid rgba(245, 158, 11, 0.35); padding: 0.2rem 0.6rem; border-radius: 999px; font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; display: inline-flex; align-items: center; gap: 0.35rem; margin-bottom: 0.35rem;">
            <i class="fa-solid fa-scale-balanced"></i> AI + MCDA Multi-Criteria Matchmaking Engine
        </span>
        <h1 style="font-size: 1.25rem; font-weight: 800; margin: 0 0 0.25rem 0; color: #FFFFFF; font-family: 'Outfit', sans-serif; line-height: 1.25;">
            Find Your Capstone Adviser via Multi-Criteria Decision Analysis (MCDA)
        </h1>
        <p style="margin: 0; color: #A7F3D0; font-size: 0.82rem; max-width: 760px; line-height: 1.4;">
            Submit your proposed capstone title and concept below. Our integrated <strong>MCDA (Multi-Criteria Decision Analysis)</strong> model evaluates 5 weighted criteria — <em>Research Specialization (35%)</em>, <em>Published Research Papers (25%)</em>, <em>Supervised Titles Track Record (15%)</em>, <em>Workload Availability (15%)</em>, and <em>Department &amp; Qualification Fit (10%)</em> — to recommend the most suitable research adviser.
        </p>
    </div>
</div>

<!-- ACTIVE ADVISER STATUS NOTICE (IF ANY) -->
<?php if($active_req): ?>
    <?php if($active_req['status'] == 'pending'): ?>
        <div style="background: #FFFBEB; border: 1px solid #FDE68A; border-radius: 10px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;">
            <div style="display: flex; align-items: center; gap: 0.85rem;">
                <div style="width: 42px; height: 42px; border-radius: 50%; background: #FEF3C7; color: #D97706; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                    <i class="fa-regular fa-clock"></i>
                </div>
                <div>
                    <div style="font-weight: 800; color: #92400E; font-size: 0.95rem;">Adviser Request Pending Approval</div>
                    <div style="color: #B45309; font-size: 0.83rem;">
                        You have sent a match request to <strong><?php echo htmlspecialchars($active_req['adv_name']); ?></strong> (<?php echo $active_req['match_score']; ?>% Match). Awaiting faculty confirmation.
                    </div>
                </div>
            </div>
            <div style="display: flex; gap: 0.5rem;">
                <a href="#" 
                   onclick="openCancelAdviserModal('<?php echo addslashes(htmlspecialchars($active_req['adv_name'])); ?>', <?php echo $active_req['id']; ?>, 'ai'); return false;" 
                   class="btn" 
                   style="padding: 0.45rem 0.85rem; font-size: 0.82rem; background: #FFF1F2; color: #E11D48; border: 1px solid #FECDD3; border-radius: 6px; text-decoration: none; cursor: pointer;">
                    <i class="fa-solid fa-rotate-left"></i> Cancel Request
                </a>
                <a href="?tab=submission" class="btn-primary" style="padding: 0.45rem 0.85rem; font-size: 0.82rem; text-decoration: none; border-radius: 6px;">
                    <i class="fa-solid fa-file-lines"></i> Go to Submission Hub
                </a>
            </div>
        </div>
    <?php elseif($active_req['status'] == 'accepted'): ?>
        <div style="background: #ECFDF5; border: 1px solid #A7F3D0; border-radius: 10px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;">
            <div style="display: flex; align-items: center; gap: 0.85rem;">
                <div style="width: 42px; height: 42px; border-radius: 50%; background: #D1FAE5; color: #047857; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div>
                    <div style="font-weight: 800; color: #065F46; font-size: 0.95rem;">Officially Assigned Capstone Adviser</div>
                    <div style="color: #047857; font-size: 0.83rem;">
                        <strong><?php echo htmlspecialchars($active_req['adv_name']); ?></strong> is your confirmed capstone adviser. Your Document Review Hub is fully unlocked!
                    </div>
                </div>
            </div>
            <a href="?tab=submission" class="btn-primary" style="padding: 0.5rem 1.1rem; font-size: 0.85rem; text-decoration: none; border-radius: 6px;">
                <i class="fa-solid fa-cloud-arrow-up"></i> Submit Documents for Review
            </a>
        </div>
    <?php endif; ?>
<?php endif; ?>

<!-- CARD 1: SUBMIT & REFINE CAPSTONE TITLE & ABSTRACT FOR AI MATCHING -->
<div class="card" style="border-top: 4px solid var(--csu-gold); margin-bottom: 1.75rem;">
    <div class="card-header">
        <div class="card-header-left">
            <i class="fa-solid fa-pen-ruler" style="color: var(--csu-gold);"></i>
            <h2>Step 1: Enter Proposed Capstone Title & Capstone Concept (PDF)</h2>
        </div>
    </div>
    <div class="card-body">
        <?php $has_abstract_pdf = (!empty($profile['abstract_file']) && file_exists($profile['abstract_file'])); ?>
        <form method="POST" action="action.php" id="aiTabTitleForm" enctype="multipart/form-data">
            <input type="hidden" name="save_thesis_title" value="1">
            <input type="hidden" name="return_tab" value="ai">
            <input type="hidden" name="show_ai" id="ai_tab_show_ai" value="0">

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.25rem; margin-bottom: 1rem;">
                <!-- Proposed Title -->
                <div>
                    <label style="font-weight: 700; color: #0F172A; display: block; margin-bottom: 0.4rem; font-size: 0.88rem;">
                        Proposed Capstone Title / Topic <span style="color: #DC2626;">*</span>
                    </label>
                    <input type="text" name="thesis_topic" id="ai_tab_topic" 
                           value="<?php echo htmlspecialchars($profile['thesis_topic'] ?? ''); ?>" 
                           placeholder="e.g. IoT-Based Precision Smart Farm Water Quality System" 
                           required 
                           style="width: 100%; padding: 0.7rem 0.85rem; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 0.9rem; font-family: 'Inter', sans-serif;">
                    <small style="color: #64748B; font-size: 0.75rem; display: block; margin-top: 0.35rem;">
                        The AI analyzes the keywords in this title and concept to find advisers specializing in this domain.
                    </small>
                </div>

                <!-- Capstone Concept Input (PDF ONLY & REQUIRED) -->
                <div>
                    <label style="font-weight: 700; color: #0F172A; display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.4rem; font-size: 0.88rem;">
                        <span>
                            <i class="fa-solid fa-file-pdf" style="color: #DC2626; margin-right: 0.25rem;"></i>
                            Capstone Concept <span style="color: #DC2626;">*</span> 
                            <span style="font-size: 0.73rem; color: #DC2626; font-weight: 700; background: #FEF2F2; padding: 0.15rem 0.45rem; border-radius: 4px; border: 1px solid #FECACA;">PDF Only &bull; Required</span>
                        </span>
                        <?php if($has_abstract_pdf): ?>
                            <a href="<?php echo htmlspecialchars($profile['abstract_file']); ?>" target="_blank" style="font-size: 0.76rem; color: #2563EB; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 0.25rem;">
                                <i class="fa-solid fa-arrow-up-right-from-square"></i> View PDF
                            </a>
                        <?php endif; ?>
                    </label>

                    <div style="background: white; border: 1.5px dashed <?php echo $has_abstract_pdf ? '#A7F3D0' : '#FECACA'; ?>; border-radius: 8px; padding: 0.65rem 0.85rem; transition: border-color 0.2s;">
                        <?php if($has_abstract_pdf): ?>
                            <div id="ai_tab_abstract_current" style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; margin-bottom: 0.5rem; background: #ECFDF5; border: 1px solid #A7F3D0; padding: 0.4rem 0.6rem; border-radius: 6px;">
                                <div style="display: flex; align-items: center; gap: 0.45rem; overflow: hidden;">
                                    <i class="fa-solid fa-file-pdf" style="color: #DC2626; font-size: 1.15rem; flex-shrink: 0;"></i>
                                    <span style="font-size: 0.82rem; font-weight: 600; color: #065F46; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                        <?php echo htmlspecialchars($profile['abstract_name'] ?? 'Uploaded_Concept.pdf'); ?>
                                    </span>
                                </div>
                                <span style="font-size: 0.72rem; color: #059669; font-weight: 700; background: white; border: 1px solid #A7F3D0; padding: 0.15rem 0.4rem; border-radius: 4px; white-space: nowrap;">
                                        <i class="fa-solid fa-check"></i> Attached
                                </span>
                            </div>
                        <?php endif; ?>

                        <input type="file" name="abstract_file" id="ai_tab_abstract_file" 
                               accept=".pdf,application/pdf" 
                               <?php echo !$has_abstract_pdf ? 'required' : ''; ?>
                               onchange="handleAbstractPdfSelect(this, 'ai_tab_abstract_status')" 
                               style="width: 100%; font-size: 0.82rem; color: #475569; cursor: pointer;">
                        
                        <div id="ai_tab_abstract_status" style="margin-top: 0.35rem; font-size: 0.74rem; color: #64748B; display: flex; align-items: center; gap: 0.35rem;">
                            <i class="fa-solid fa-circle-exclamation" style="color: #DC2626;"></i>
                            <span><strong>Strictly PDF (.pdf) format only.</strong> Concept PDF is required before you can request or select an adviser.</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ACTION BUTTONS -->
            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
                <button type="button" onclick="openAIModalWithLiveAiTab()" class="btn" style="padding: 0.65rem 1.35rem; font-size: 0.88rem; font-weight: 700; background: #FEF3C7; color: #92400E; border: 1px solid #FDE68A; border-radius: 8px; display: inline-flex; align-items: center; gap: 0.45rem; cursor: pointer; transition: all 0.2s ease;">
                    <i class="fa-solid fa-robot"></i> Open AI Matching Modal
                </button>
                <button type="submit" class="btn" style="padding: 0.65rem 1.25rem; font-size: 0.88rem; font-weight: 600; background: white; color: #1E293B; border: 1px solid #CBD5E1; border-radius: 8px; display: inline-flex; align-items: center; gap: 0.45rem;">
                    <i class="fa-solid fa-floppy-disk"></i> Save Title & Concept
                </button>
                <span id="aiTabLoading" style="display: none; color: var(--csu-green); font-size: 0.85rem; font-weight: 600;">
                    <i class="fa-solid fa-circle-notch fa-spin"></i> Analyzing faculty specializations...
                </span>
            </div>
        </form>
    </div>
</div>

<!-- CARD 2: AI RECOMMENDED ADVISERS RANKED BY MCDA (MULTI-CRITERIA DECISION ANALYSIS) -->
<div class="card" style="border-top: 4px solid var(--csu-green);">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
        <div class="card-header-left">
            <i class="fa-solid fa-scale-balanced" style="color: var(--csu-green);"></i>
            <h2>Recommended Faculty Advisers (MCDA Multi-Criteria Evaluation)</h2>
        </div>
        <span id="aiTabMatchesCount" style="font-size: 0.8rem; color: #047857; font-weight: 700; background: #ECFDF5; padding: 0.25rem 0.65rem; border-radius: 6px; border: 1px solid #A7F3D0;">
            <?php echo count($available_advisers); ?> Available Faculty Members
        </span>
    </div>
    
    <div class="card-body">
        <!-- MCDA MULTI-CRITERIA WEIGHTING & EVALUATION SUMMARY BAR -->
        <div style="background: linear-gradient(135deg, #F8FAFC 0%, #F0FDF4 100%); border: 1px solid #CBD5E1; border-radius: 10px; padding: 0.9rem 1.1rem; margin-bottom: 1.25rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 0.65rem;">
                <div>
                    <div style="font-size: 0.82rem; font-weight: 800; color: #0F172A; display: flex; align-items: center; gap: 0.4rem;">
                        <i class="fa-solid fa-sliders" style="color: #047857;"></i>
                        Multi-Criteria Decision Analysis (MCDA) Weighting Model
                    </div>
                    <div style="font-size: 0.74rem; color: #475569; margin-top: 2px;">
                        Each adviser is scored across 5 weighted criteria ($S_{\text{MCDA}} = \sum w_i C_i$). Select a decision priority profile to re-evaluate rankings:
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 0.45rem; flex-wrap: wrap;">
                    <label for="mcdaPresetSelect" style="font-size: 0.75rem; font-weight: 700; color: #334155;">MCDA Priority Profile:</label>
                    <select id="mcdaPresetSelect" onchange="runAiTabMatch()" style="padding: 0.4rem 0.65rem; border: 1px solid #94A3B8; border-radius: 6px; font-size: 0.78rem; font-weight: 700; color: #0F172A; background: white; cursor: pointer;">
                        <option value="balanced">Balanced MCDA (Default: 35 / 25 / 15 / 15 / 10)</option>
                        <option value="publications">Research &amp; Publications Priority (25 / 40 / 15 / 10 / 10)</option>
                        <option value="specialization">Specialization-First Priority (50 / 20 / 15 / 10 / 5)</option>
                        <option value="availability">Workload &amp; Availability Priority (30 / 15 / 15 / 30 / 10)</option>
                    </select>
                </div>
            </div>
            <div id="mcdaWeightPills" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(175px, 1fr)); gap: 0.5rem;">
                <div style="background: white; border: 1px solid #A7F3D0; border-radius: 7px; padding: 0.4rem 0.65rem; display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 0.72rem; font-weight: 700; color: #065F46;"><i class="fa-solid fa-tags"></i> C1: Specialization</span>
                    <span id="wBadgeSpec" style="font-size: 0.72rem; font-weight: 800; background: #D1FAE5; color: #065F46; padding: 1px 6px; border-radius: 999px;">35%</span>
                </div>
                <div style="background: white; border: 1px solid #BFDBFE; border-radius: 7px; padding: 0.4rem 0.65rem; display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 0.72rem; font-weight: 700; color: #1E40AF;"><i class="fa-solid fa-book-journal-whills"></i> C2: Publications</span>
                    <span id="wBadgePub" style="font-size: 0.72rem; font-weight: 800; background: #DBEAFE; color: #1E40AF; padding: 1px 6px; border-radius: 999px;">25%</span>
                </div>
                <div style="background: white; border: 1px solid #FDE68A; border-radius: 7px; padding: 0.4rem 0.65rem; display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 0.72rem; font-weight: 700; color: #92400E;"><i class="fa-solid fa-folder-tree"></i> C3: Supervised Titles</span>
                    <span id="wBadgeSup" style="font-size: 0.72rem; font-weight: 800; background: #FEF3C7; color: #92400E; padding: 1px 6px; border-radius: 999px;">15%</span>
                </div>
                <div style="background: white; border: 1px solid #DDD6FE; border-radius: 7px; padding: 0.4rem 0.65rem; display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 0.72rem; font-weight: 700; color: #5B21B6;"><i class="fa-solid fa-users-gear"></i> C4: Slot Availability</span>
                    <span id="wBadgeCap" style="font-size: 0.72rem; font-weight: 800; background: #EDE9FE; color: #5B21B6; padding: 1px 6px; border-radius: 999px;">15%</span>
                </div>
                <div style="background: white; border: 1px solid #CBD5E1; border-radius: 7px; padding: 0.4rem 0.65rem; display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 0.72rem; font-weight: 700; color: #334155;"><i class="fa-solid fa-user-graduate"></i> C5: Dept &amp; Degree</span>
                    <span id="wBadgeDept" style="font-size: 0.72rem; font-weight: 800; background: #F1F5F9; color: #334155; padding: 1px 6px; border-radius: 999px;">10%</span>
                </div>
            </div>
        </div>

        <p id="aiTabNotice" style="color: #64748B; font-size: 0.85rem; margin-top: 0; margin-bottom: 1.25rem;">
            <?php echo !empty($student_topic) 
                ? 'Showing faculty advisers ranked by Multi-Criteria Decision Analysis (MCDA) for your proposed topic ("<strong>' . htmlspecialchars($student_topic) . '</strong>").' 
                : 'Enter your capstone title in the form above to compute personalized MCDA multi-criteria rankings across faculty specializations, publications, track record, and availability.'; ?>
        </p>

        <!-- RESULTS GRID -->
        <div id="aiTabMatchesContainer" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.25rem;">
            <?php if(!empty($available_advisers)): ?>
                <?php foreach($available_advisers as $fa): 
                    $score = $fa['match_score'] ?? 70;
                    $scoreBorder = ($score >= 80) ? '#A7F3D0' : (($score >= 60) ? '#FDE68A' : '#E2E8F0');
                    $scoreBg = ($score >= 80) ? '#F0FDF4' : (($score >= 60) ? '#FFFBEB' : 'white');
                    $scoreColor = ($score >= 80) ? '#065F46' : (($score >= 60) ? '#92400E' : '#475569');
                    $firstName = explode(' ', $fa['name'])[0];
                    $mcda_crit = $fa['mcda_criteria'] ?? [];
                    $avail_slots = $fa['available_slots'] ?? 5;
                    $max_cap = $fa['max_capacity'] ?? 5;
                    $rank_lbl = $fa['mcda_rank_label'] ?? 'Suitable MCDA Match';
                ?>
                    <div style="background: <?php echo $scoreBg; ?>; border: 1px solid <?php echo $scoreBorder; ?>; border-radius: 12px; padding: 1.25rem; box-shadow: var(--shadow-sm); display: flex; flex-direction: column; justify-content: space-between; gap: 0.85rem; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='var(--shadow-md)'" onmouseout="this.style.transform='none'; this.style.boxShadow='var(--shadow-sm)'">
                        <div>
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.65rem; gap: 0.5rem;">
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <div style="width: 44px; height: 44px; border-radius: 50%; background: var(--csu-green); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.15rem; font-weight: 800; flex-shrink: 0; box-shadow: 0 2px 5px rgba(4,120,87,0.25);">
                                        <?php echo strtoupper(substr($fa['name'], 0, 1)); ?>
                                    </div>
                                    <div style="overflow: hidden;">
                                        <h4 style="margin: 0; color: #0F172A; font-size: 1rem; font-weight: 800; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                            <?php echo htmlspecialchars($fa['name']); ?>
                                        </h4>
                                        <small style="color: #64748B; font-size: 0.76rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block;">
                                            <?php echo htmlspecialchars($fa['email']); ?>
                                        </small>
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <span class="badge" style="background: <?php echo ($score >= 80) ? '#D1FAE5' : '#FEF3C7'; ?>; color: <?php echo $scoreColor; ?>; font-weight: 800; font-size: 0.78rem; padding: 0.25rem 0.55rem; border-radius: 999px; white-space: nowrap; border: 1px solid <?php echo $scoreBorder; ?>; display: inline-block;">
                                        <i class="fa-solid fa-scale-balanced"></i> <?php echo $score; ?>% MCDA Score
                                    </span>
                                    <div style="font-size: 0.66rem; font-weight: 700; color: #047857; margin-top: 2px;">
                                        <?php echo htmlspecialchars($rank_lbl); ?>
                                    </div>
                                </div>
                            </div>

                            <?php if(!empty($fa['department'])): ?>
                                <div style="margin-bottom: 0.5rem; display: flex; flex-wrap: wrap; gap: 0.35rem; align-items: center;">
                                    <span style="background: #F1F5F9; color: #475569; font-size: 0.72rem; font-weight: 600; padding: 2px 8px; border-radius: 4px; display: inline-block;">
                                        <i class="fa-solid fa-building-columns"></i> <?php echo htmlspecialchars($fa['department']); ?>
                                    </span>
                                    <span style="background: <?php echo $avail_slots > 0 ? '#ECFDF5' : '#FEF2F2'; ?>; color: <?php echo $avail_slots > 0 ? '#047857' : '#DC2626'; ?>; border: 1px solid <?php echo $avail_slots > 0 ? '#A7F3D0' : '#FECACA'; ?>; font-size: 0.68rem; font-weight: 700; padding: 1px 6px; border-radius: 4px;">
                                        <i class="fa-solid fa-users-gear"></i> <?php echo $avail_slots; ?>/<?php echo $max_cap; ?> Slots Open
                                    </span>
                                    <?php if(!empty($fa['academic_degree'])): ?>
                                        <span style="background: #EFF6FF; color: #1D4ED8; border: 1px solid #BFDBFE; font-size: 0.68rem; font-weight: 700; padding: 1px 6px; border-radius: 4px;">
                                            <?php echo htmlspecialchars($fa['academic_degree']); ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if(!empty($fa['graduation_year'])): ?>
                                        <span style="background: #FEF3C7; color: #92400E; border: 1px solid #FDE68A; font-size: 0.68rem; font-weight: 700; padding: 1px 6px; border-radius: 4px;">
                                            <i class="fa-solid fa-graduation-cap"></i> Grad: <?php echo htmlspecialchars($fa['graduation_year']); ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php 
                                        $fa_scholar_link = format_google_scholar_link($fa['google_scholar_url'] ?? '', $fa['name'] ?? ''); 
                                    ?>
                                    <?php if(!empty($fa_scholar_link)): ?>
                                        <a href="<?php echo htmlspecialchars($fa_scholar_link); ?>" target="_blank" rel="noopener noreferrer" style="background: #EFF6FF; color: #2563EB; border: 1px solid #93C5FD; font-size: 0.68rem; font-weight: 700; padding: 1px 6px; border-radius: 4px; text-decoration: none;">
                                            <i class="fa-solid fa-arrow-up-right-from-square"></i> Google Scholar
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <!-- MCDA 5-CRITERIA SCORECARD BREAKDOWN -->
                            <?php if(!empty($mcda_crit)): ?>
                                <div style="background: #F8FAFC; border: 1px solid #CBD5E1; border-radius: 8px; padding: 0.6rem 0.75rem; margin-bottom: 0.6rem;">
                                    <div style="font-size: 0.68rem; font-weight: 800; text-transform: uppercase; color: #0F172A; letter-spacing: 0.4px; margin-bottom: 0.4rem; display: flex; justify-content: space-between; align-items: center;">
                                        <span><i class="fa-solid fa-chart-simple" style="color: #047857;"></i> MCDA Multi-Criteria Breakdown</span>
                                        <span style="color: #047857;">Weighted Total: <?php echo $score; ?>%</span>
                                    </div>
                                    <?php
                                    $crit_colors = [
                                        'specialization' => '#059669',
                                        'publications' => '#2563EB',
                                        'supervised_titles' => '#D97706',
                                        'workload_capacity' => '#7C3AED',
                                        'department_qualification' => '#475569'
                                    ];
                                    foreach ($mcda_crit as $ckey => $cval):
                                        $bar_color = $crit_colors[$ckey] ?? '#059669';
                                        $c_raw = (float)($cval['raw_score'] ?? 0);
                                        $c_w = (float)($cval['weight'] ?? 0);
                                        $c_ws = (float)($cval['weighted_score'] ?? 0);
                                    ?>
                                        <div style="margin-bottom: 0.3rem;">
                                            <div style="display: flex; justify-content: space-between; font-size: 0.68rem; color: #334155; font-weight: 600; margin-bottom: 1px;">
                                                <span><?php echo htmlspecialchars($cval['label']); ?> <span style="color: #64748B;">(w=<?php echo $c_w; ?>%)</span></span>
                                                <span style="font-weight: 700; color: #0F172A;"><?php echo $c_raw; ?>% <span style="color: #64748B; font-weight: 600;">(+<?php echo $c_ws; ?>)</span></span>
                                            </div>
                                            <div style="height: 5px; background: #E2E8F0; border-radius: 999px; overflow: hidden;">
                                                <div style="width: <?php echo min(100, max(5, $c_raw)); ?>%; height: 100%; background: <?php echo $bar_color; ?>; border-radius: 999px;"></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <div style="background: white; border: 1px solid #E2E8F0; border-radius: 8px; padding: 0.65rem 0.8rem; margin-bottom: 0.65rem;">
                                <span style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: #64748B; letter-spacing: 0.5px; display: block; margin-bottom: 0.25rem;">
                                    <i class="fa-solid fa-tags" style="color: var(--csu-gold);"></i> Area of Specialization:
                                </span>
                                <p style="margin: 0; color: #1E293B; font-size: 0.8rem; line-height: 1.45;">
                                    <?php echo htmlspecialchars($fa['expertise'] ?: 'General Capstone Adviser'); ?>
                                </p>
                                <?php if(!empty($fa['matched_terms'])): ?>
                                    <div style="margin-top: 0.4rem; display: flex; flex-wrap: wrap; gap: 0.3rem;">
                                        <?php foreach($fa['matched_terms'] as $mt): ?>
                                            <span style="background: #ECFDF5; color: #047857; border: 1px solid #A7F3D0; font-size: 0.68rem; font-weight: 700; padding: 1px 6px; border-radius: 4px;">
                                                <i class="fa-solid fa-check"></i> <?php echo htmlspecialchars($mt); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <?php 
                                $fa_pub_list = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $fa['published_papers'] ?? ''))));
                                $fa_prev_list = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $fa['previous_advised_titles'] ?? ''))));
                                ?>
                                <?php if(!empty($fa_pub_list)): ?>
                                    <div style="margin-top: 0.55rem; padding-top: 0.45rem; border-top: 1px dashed #E2E8F0;">
                                        <span style="font-size: 0.68rem; font-weight: 800; text-transform: uppercase; color: #047857; display: block; margin-bottom: 0.2rem;">
                                            <i class="fa-solid fa-book-journal-whills"></i> Published Research Papers (<?php echo count($fa_pub_list); ?>):
                                        </span>
                                        <?php foreach(array_slice($fa_pub_list, 0, 2) as $p_item): ?>
                                            <div style="font-size: 0.74rem; color: #334155; line-height: 1.35; margin-bottom: 2px;">
                                                • <?php echo htmlspecialchars(preg_replace('/^\d+[\.\)]\s*/', '', $p_item)); ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if(!empty($fa_prev_list)): ?>
                                    <div style="margin-top: 0.45rem; padding-top: 0.45rem; border-top: 1px dashed #E2E8F0;">
                                        <span style="font-size: 0.68rem; font-weight: 800; text-transform: uppercase; color: #B45309; display: block; margin-bottom: 0.2rem;">
                                            <i class="fa-solid fa-folder-tree"></i> Previously Supervised Research Titles (<?php echo count($fa_prev_list); ?>):
                                        </span>
                                        <?php foreach(array_slice($fa_prev_list, 0, 2) as $pr_item): ?>
                                            <div style="font-size: 0.74rem; color: #334155; line-height: 1.35; margin-bottom: 2px;">
                                                • <?php echo htmlspecialchars(preg_replace('/^[\-\*\d\.\)\s]+/', '', $pr_item)); ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if($active_req && $active_req['status'] == 'accepted' && $active_req['adviser_id'] == $fa['id']): ?>
                            <button type="button" disabled class="btn" style="width: 100%; text-align: center; padding: 0.55rem 0.85rem; font-size: 0.85rem; font-weight: 700; border-radius: 8px; background: #D1FAE5; color: #065F46; border: 1px solid #A7F3D0; cursor: not-allowed;">
                                <i class="fa-solid fa-circle-check"></i> Current Assigned Adviser
                            </button>
                        <?php elseif($active_req && $active_req['status'] == 'pending' && $active_req['adviser_id'] == $fa['id']): ?>
                            <button type="button" disabled class="btn" style="width: 100%; text-align: center; padding: 0.55rem 0.85rem; font-size: 0.85rem; font-weight: 700; border-radius: 8px; background: #FEF3C7; color: #92400E; border: 1px solid #FDE68A; cursor: not-allowed;">
                                <i class="fa-regular fa-clock"></i> Request Pending Approval
                            </button>
                        <?php else: ?>
                            <button type="button" onclick="selectAdviserFromAiTab(<?php echo $fa['id']; ?>, <?php echo $score; ?>)" 
                                    class="btn-primary" 
                                    style="width: 100%; text-align: center; padding: 0.55rem 0.85rem; font-size: 0.85rem; font-weight: 700; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 0.45rem; box-shadow: 0 2px 4px rgba(4,120,87,0.2);">
                                <i class="fa-solid fa-user-check"></i> Select <?php echo htmlspecialchars($firstName); ?> as Adviser
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: 2.5rem 1.5rem; background: #F8FAFC; border: 1px dashed #CBD5E1; border-radius: 12px; color: #64748B;">
                    <i class="fa-solid fa-filter-circle-xmark fa-2x" style="color: #94A3B8; margin-bottom: 0.75rem; display: block;"></i>
                    <strong style="color: #1E293B; font-size: 0.95rem; display: block; margin-bottom: 0.35rem;">No Specialized Advisers Found for this Topic</strong>
                    No faculty members currently specialize in this domain in your department. You can try adjusting your title or adding optional capstone keywords.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function formatScholarLink(url, name) {
    url = (url || '').trim();
    name = (name || '').trim();
    if (!url || url.includes('@')) {
        if (!name) return '';
        const clean = name.replace(/^(Dr\.|Engr\.|Prof\.|Mr\.|Ms\.|Mrs\.)\s+/i, '').replace(/,\s*(MIT|MSIT|MSCS|PhD|DIT|MEP|ECE|CPE).*$/i, '').trim();
        return 'https://scholar.google.com/scholar?hl=en&q=' + encodeURIComponent('author:"' + clean + '"');
    }
    if (url.startsWith('http://') || url.startsWith('https://')) return url;
    if (url.startsWith('scholar.google.')) return 'https://' + url;
    if (/[?&]user=([a-zA-Z0-9_-]{8,20})/.test(url)) {
        const m = url.match(/[?&]user=([a-zA-Z0-9_-]{8,20})/);
        return 'https://scholar.google.com/citations?user=' + encodeURIComponent(m[1]) + '&hl=en';
    }
    return 'https://scholar.google.com/scholar?hl=en&q=' + encodeURIComponent('author:"' + url + '"');
}
function handleAbstractPdfSelect(input, statusElId) {
    if (!input.files || input.files.length === 0) return;
    const file = input.files[0];
    const ext = file.name.split('.').pop().toLowerCase();
    const statusEl = document.getElementById(statusElId);
    
    if (ext !== 'pdf' && file.type !== 'application/pdf') {
        if (typeof window.showUniversityNotice === 'function') {
            window.showUniversityNotice({
                heading: 'Document Format Policy',
                pillText: 'PDF Document Format Only',
                title: 'Invalid File Format',
                message: 'Only PDF (.pdf) documents are accepted for the official capstone concept.',
                hint: 'Please select an authentic PDF document (.pdf) of your capstone concept.',
                targetFocusId: 'ai_tab_abstract_file',
                primaryBtnText: 'Select Concept PDF'
            });
        } else {
            alert('Invalid File Format: Only PDF (.pdf) documents are accepted for the capstone concept.');
        }
        input.value = '';
        if (statusEl) {
            statusEl.innerHTML = '<span style="color: #DC2626; font-weight: 700;"><i class="fa-solid fa-circle-xmark"></i> Rejected: Only PDF files (.pdf) are accepted!</span>';
        }
        return false;
    }
    
    if (file.size > 25 * 1024 * 1024) {
        if (typeof window.showUniversityNotice === 'function') {
            window.showUniversityNotice({
                heading: 'Document Size Policy',
                pillText: 'Maximum Limit: 25MB',
                title: 'File Size Exceeded',
                message: 'The selected Concept PDF exceeds the maximum university file size limit of 25MB.',
                hint: 'Please optimize or compress your PDF file before uploading.',
                targetFocusId: 'ai_tab_abstract_file',
                primaryBtnText: 'Choose Smaller PDF'
            });
        } else {
            alert('File Too Large: Concept PDF must be less than 25MB.');
        }
        input.value = '';
        if (statusEl) {
            statusEl.innerHTML = '<span style="color: #DC2626; font-weight: 700;"><i class="fa-solid fa-circle-xmark"></i> File exceeds 25MB limit!</span>';
        }
        return false;
    }
    
    const sizeKb = Math.round(file.size / 1024);
    if (statusEl) {
        statusEl.innerHTML = '<span style="color: #059669; font-weight: 700;"><i class="fa-solid fa-circle-check"></i> ' + file.name.replace(/</g, '&lt;') + ' (' + sizeKb + ' KB) &bull; PDF verified & ready to save</span>';
    }
    return true;
}

function openAIModalWithLiveAiTab() {
    const input = document.getElementById('ai_tab_topic');
    const fileInput = document.getElementById('ai_tab_abstract_file');
    const form = document.getElementById('aiTabTitleForm');
    const hasExisting = <?php echo $has_abstract_pdf ? 'true' : 'false'; ?>;
    const title = input ? input.value.trim() : '';

    if (!title) {
        if (typeof window.showUniversityNotice === 'function') {
            window.showUniversityNotice({
                heading: 'Academic Submission Notice',
                pillText: 'Proposed Title Required',
                title: 'Proposed Title Required',
                message: 'Please enter your proposed capstone title before proceeding with faculty matchmaking.',
                hint: 'The AI matching engine evaluates your capstone title to suggest the most qualified faculty advisers.',
                targetFocusId: 'ai_tab_topic',
                primaryBtnText: 'Enter Proposed Title'
            });
        } else {
            alert('Proposed Title Required: Please enter your proposed capstone title first.');
        }
        if (input) {
            input.focus();
            input.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        return false;
    }

    const hasNewFile = fileInput && fileInput.files && fileInput.files.length > 0;

    // Strict Requirement: Cannot proceed without concept PDF!
    if (!hasExisting && !hasNewFile) {
        if (typeof window.showUniversityNotice === 'function') {
            window.showUniversityNotice({
                heading: 'Academic Requirement Notice',
                pillText: 'Official Academic Compliance',
                title: 'Capstone Concept PDF Required',
                message: 'In compliance with CEIT academic policy, you cannot proceed without uploading your official Capstone Concept in PDF format (.pdf).',
                hint: 'Please attach and save your Capstone Concept in PDF format (.pdf) to continue with faculty adviser matchmaking.',
                targetFocusId: 'ai_tab_abstract_file',
                primaryBtnText: 'Attach Concept PDF Now'
            });
        } else {
            alert('Capstone Concept PDF Required: In compliance with CEIT academic policy, you cannot proceed without uploading your Capstone Concept in PDF format (.pdf).');
        }
        if (fileInput) {
            fileInput.focus();
            fileInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        return false;
    }

    // If new file chosen, submit form first so it is uploaded and AI can extract text
    if (hasNewFile) {
        const showAiInput = document.getElementById('ai_tab_show_ai');
        if (showAiInput) showAiInput.value = '1';
        if (form) {
            form.submit();
            return;
        }
    }

    if (typeof openAIModal === 'function') {
        openAIModal(title);
    } else if (typeof window.openAIModal === 'function') {
        window.openAIModal(title);
    }
    runAiTabMatch();
}

function runAiTabMatch() {
    const topicEl = document.getElementById('ai_tab_topic');
    const title = topicEl ? topicEl.value.trim() : '';
    const skillsEl = document.getElementById('ai_tab_skills');
    const skills = skillsEl ? skillsEl.value.trim() : '';
    const presetEl = document.getElementById('mcdaPresetSelect');
    const mcdaPreset = presetEl ? presetEl.value : 'balanced';
    const container = document.getElementById('aiTabMatchesContainer');
    const loading = document.getElementById('aiTabLoading');
    const notice = document.getElementById('aiTabNotice');

    if (loading) loading.style.display = 'inline-block';
    if (container) container.style.opacity = '0.5';

    fetch(`api.php?action=get_matches&title=${encodeURIComponent(title)}&skills=${encodeURIComponent(skills)}&mcda_preset=${encodeURIComponent(mcdaPreset)}&save_topic=1`)
        .then(res => res.json())
        .then(data => {
            if (loading) loading.style.display = 'none';
            if (container) container.style.opacity = '1';
            if (data.notice && notice) {
                notice.innerHTML = data.notice;
            }
            if (data.mcda_weights) {
                const w = data.mcda_weights;
                if (document.getElementById('wBadgeSpec')) document.getElementById('wBadgeSpec').innerText = (w.specialization || 35) + '%';
                if (document.getElementById('wBadgePub')) document.getElementById('wBadgePub').innerText = (w.publications || 25) + '%';
                if (document.getElementById('wBadgeSup')) document.getElementById('wBadgeSup').innerText = (w.supervised_titles || 15) + '%';
                if (document.getElementById('wBadgeCap')) document.getElementById('wBadgeCap').innerText = (w.workload_capacity || 15) + '%';
                if (document.getElementById('wBadgeDept')) document.getElementById('wBadgeDept').innerText = (w.department_qualification || 10) + '%';
            }
            const countBadge = document.getElementById('aiTabMatchesCount');
            if (countBadge && data.matches) {
                countBadge.innerText = `${data.matches.length} MCDA-Evaluated Faculty Members`;
            }
            if (data.matches && container) {
                renderAiTabCards(data.matches, title);
            }
        })
        .catch(err => {
            if (loading) loading.style.display = 'none';
            if (container) container.style.opacity = '1';
        });
}

function renderAiTabCards(matches, currentTitle) {
    const container = document.getElementById('aiTabMatchesContainer');
    if (!container) return;

    if (!matches || matches.length === 0) {
        container.innerHTML = `
            <div style="grid-column: 1 / -1; text-align: center; padding: 2.5rem 1.5rem; background: #F8FAFC; border: 1px dashed #CBD5E1; border-radius: 12px; color: #64748B;">
                <i class="fa-solid fa-filter-circle-xmark fa-2x" style="color: #94A3B8; margin-bottom: 0.75rem; display: block;"></i>
                <strong style="color: #1E293B; font-size: 0.95rem; display: block; margin-bottom: 0.35rem;">No Specialized Advisers Found for this Topic</strong>
                No faculty members currently specialize in this domain in your department. You can try adjusting your title or adding optional capstone keywords.
            </div>
        `;
        return;
    }

    const critColors = {
        specialization: '#059669',
        publications: '#2563EB',
        supervised_titles: '#D97706',
        workload_capacity: '#7C3AED',
        department_qualification: '#475569'
    };

    let html = '';
    matches.forEach(fa => {
        const score = fa.match_score || 70;
        const scoreBorder = (score >= 80) ? '#A7F3D0' : ((score >= 60) ? '#FDE68A' : '#E2E8F0');
        const scoreBg = (score >= 80) ? '#F0FDF4' : ((score >= 60) ? '#FFFBEB' : 'white');
        const scoreColor = (score >= 80) ? '#065F46' : ((score >= 60) ? '#92400E' : '#475569');
        const firstName = fa.name.split(' ')[0];
        const availSlots = (fa.available_slots !== undefined) ? fa.available_slots : 5;
        const maxCap = fa.max_capacity || 5;
        const rankLabel = fa.mcda_rank_label || 'Suitable MCDA Match';

        let mcdaHtml = '';
        if (fa.mcda_criteria && typeof fa.mcda_criteria === 'object') {
            const critEntries = Object.entries(fa.mcda_criteria);
            if (critEntries.length > 0) {
                const rowsHtml = critEntries.map(([ckey, cval]) => {
                    const barColor = critColors[ckey] || '#059669';
                    const cRaw = parseFloat(cval.raw_score || 0);
                    const cW = parseFloat(cval.weight || 0);
                    const cWs = parseFloat(cval.weighted_score || 0);
                    return `
                        <div style="margin-bottom: 0.3rem;">
                            <div style="display: flex; justify-content: space-between; font-size: 0.68rem; color: #334155; font-weight: 600; margin-bottom: 1px;">
                                <span>${cval.label} <span style="color: #64748B;">(w=${cW}%)</span></span>
                                <span style="font-weight: 700; color: #0F172A;">${cRaw}% <span style="color: #64748B; font-weight: 600;">(+${cWs})</span></span>
                            </div>
                            <div style="height: 5px; background: #E2E8F0; border-radius: 999px; overflow: hidden;">
                                <div style="width: ${Math.min(100, Math.max(5, cRaw))}%; height: 100%; background: ${barColor}; border-radius: 999px;"></div>
                            </div>
                        </div>
                    `;
                }).join('');
                mcdaHtml = `
                    <div style="background: #F8FAFC; border: 1px solid #CBD5E1; border-radius: 8px; padding: 0.6rem 0.75rem; margin-bottom: 0.6rem;">
                        <div style="font-size: 0.68rem; font-weight: 800; text-transform: uppercase; color: #0F172A; letter-spacing: 0.4px; margin-bottom: 0.4rem; display: flex; justify-content: space-between; align-items: center;">
                            <span><i class="fa-solid fa-chart-simple" style="color: #047857;"></i> MCDA Multi-Criteria Breakdown</span>
                            <span style="color: #047857;">Weighted Total: ${score}%</span>
                        </div>
                        ${rowsHtml}
                    </div>
                `;
            }
        }

        let matchedTagsHtml = '';
        if (fa.matched_terms && fa.matched_terms.length > 0) {
            matchedTagsHtml = '<div style="margin-top: 0.4rem; display: flex; flex-wrap: wrap; gap: 0.3rem;">' + 
                fa.matched_terms.map(t => `<span style="background: #ECFDF5; color: #047857; border: 1px solid #A7F3D0; font-size: 0.68rem; font-weight: 700; padding: 1px 6px; border-radius: 4px;"><i class="fa-solid fa-check"></i> ${t}</span>`).join('') +
                '</div>';
        }

        const pubLines = (fa.published_papers || '').split(/\r?\n/).map(s => s.trim()).filter(Boolean);
        const prevLines = (fa.previous_advised_titles || '').split(/\r?\n/).map(s => s.trim()).filter(Boolean);

        let pubsHtml = '';
        if (pubLines.length > 0) {
            pubsHtml = `
                <div style="margin-top: 0.55rem; padding-top: 0.45rem; border-top: 1px dashed #E2E8F0;">
                    <span style="font-size: 0.68rem; font-weight: 800; text-transform: uppercase; color: #047857; display: block; margin-bottom: 0.2rem;">
                        <i class="fa-solid fa-book-journal-whills"></i> Published Research Papers (${pubLines.length}):
                    </span>
                    ${pubLines.slice(0, 2).map(p => `<div style="font-size: 0.74rem; color: #334155; line-height: 1.35; margin-bottom: 2px;">• ${p.replace(/^\d+[\.\)]\s*/, '')}</div>`).join('')}
                </div>
            `;
        }

        let prevHtml = '';
        if (prevLines.length > 0) {
            prevHtml = `
                <div style="margin-top: 0.45rem; padding-top: 0.45rem; border-top: 1px dashed #E2E8F0;">
                    <span style="font-size: 0.68rem; font-weight: 800; text-transform: uppercase; color: #B45309; display: block; margin-bottom: 0.2rem;">
                        <i class="fa-solid fa-folder-tree"></i> Previously Supervised Research Titles (${prevLines.length}):
                    </span>
                    ${prevLines.slice(0, 2).map(pr => `<div style="font-size: 0.74rem; color: #334155; line-height: 1.35; margin-bottom: 2px;">• ${pr.replace(/^[\-\*\d\.\)\s]+/, '')}</div>`).join('')}
                </div>
            `;
        }

        html += `
            <div style="background: ${scoreBg}; border: 1px solid ${scoreBorder}; border-radius: 12px; padding: 1.25rem; box-shadow: var(--shadow-sm); display: flex; flex-direction: column; justify-content: space-between; gap: 0.85rem; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='var(--shadow-md)'" onmouseout="this.style.transform='none'; this.style.boxShadow='var(--shadow-sm)'">
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.65rem; gap: 0.5rem;">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <div style="width: 44px; height: 44px; border-radius: 50%; background: var(--csu-green); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.15rem; font-weight: 800; flex-shrink: 0; box-shadow: 0 2px 5px rgba(4,120,87,0.25);">
                                ${fa.name.charAt(0).toUpperCase()}
                            </div>
                            <div style="overflow: hidden;">
                                <h4 style="margin: 0; color: #0F172A; font-size: 1rem; font-weight: 800; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    ${fa.name}
                                </h4>
                                <small style="color: #64748B; font-size: 0.76rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block;">
                                    ${fa.email}
                                </small>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <span class="badge" style="background: ${score >= 80 ? '#D1FAE5' : '#FEF3C7'}; color: ${scoreColor}; font-weight: 800; font-size: 0.78rem; padding: 0.25rem 0.55rem; border-radius: 999px; white-space: nowrap; border: 1px solid ${scoreBorder}; display: inline-block;">
                                <i class="fa-solid fa-scale-balanced"></i> ${score}% MCDA Score
                            </span>
                            <div style="font-size: 0.66rem; font-weight: 700; color: #047857; margin-top: 2px;">
                                ${rankLabel}
                            </div>
                        </div>
                    </div>

                    <div style="margin-bottom: 0.5rem; display: flex; flex-wrap: wrap; gap: 0.35rem; align-items: center;">
                        ${fa.department ? `<span style="background: #F1F5F9; color: #475569; font-size: 0.72rem; font-weight: 600; padding: 2px 8px; border-radius: 4px; display: inline-block;"><i class="fa-solid fa-building-columns"></i> ${fa.department}</span>` : ''}
                        <span style="background: ${availSlots > 0 ? '#ECFDF5' : '#FEF2F2'}; color: ${availSlots > 0 ? '#047857' : '#DC2626'}; border: 1px solid ${availSlots > 0 ? '#A7F3D0' : '#FECACA'}; font-size: 0.68rem; font-weight: 700; padding: 1px 6px; border-radius: 4px;"><i class="fa-solid fa-users-gear"></i> ${availSlots}/${maxCap} Slots Open</span>
                        ${fa.academic_degree ? `<span style="background: #EFF6FF; color: #1D4ED8; border: 1px solid #BFDBFE; font-size: 0.68rem; font-weight: 700; padding: 1px 6px; border-radius: 4px;">${fa.academic_degree}</span>` : ''}
                        ${fa.graduation_year ? `<span style="background: #FEF3C7; color: #92400E; border: 1px solid #FDE68A; font-size: 0.68rem; font-weight: 700; padding: 1px 6px; border-radius: 4px;"><i class="fa-solid fa-graduation-cap"></i> Grad: ${fa.graduation_year}</span>` : ''}
                        ${(() => { const sl = formatScholarLink(fa.google_scholar_url, fa.name); return sl ? `<a href="${sl}" target="_blank" rel="noopener noreferrer" style="background: #EFF6FF; color: #2563EB; border: 1px solid #93C5FD; font-size: 0.68rem; font-weight: 700; padding: 1px 6px; border-radius: 4px; text-decoration: none;"><i class="fa-solid fa-arrow-up-right-from-square"></i> Google Scholar</a>` : ''; })()}
                    </div>

                    ${mcdaHtml}

                    <div style="background: white; border: 1px solid #E2E8F0; border-radius: 8px; padding: 0.65rem 0.8rem; margin-bottom: 0.65rem;">
                        <span style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: #64748B; letter-spacing: 0.5px; display: block; margin-bottom: 0.25rem;">
                            <i class="fa-solid fa-tags" style="color: var(--csu-gold);"></i> Area of Specialization:
                        </span>
                        <p style="margin: 0; color: #1E293B; font-size: 0.8rem; line-height: 1.45;">
                            ${fa.expertise || 'General Capstone Adviser'}
                        </p>
                        ${matchedTagsHtml}
                        ${pubsHtml}
                        ${prevHtml}
                    </div>
                </div>

                <button type="button" onclick="selectAdviserFromAiTab(${fa.id}, ${score})" 
                        class="btn-primary" 
                        style="width: 100%; text-align: center; padding: 0.55rem 0.85rem; font-size: 0.85rem; font-weight: 700; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 0.45rem; box-shadow: 0 2px 4px rgba(4,120,87,0.2);">
                    <i class="fa-solid fa-user-check"></i> Select ${firstName} as Adviser
                </button>
            </div>
        `;
    });

    container.innerHTML = html;
}

function selectAdviserFromAiTab(adviserId, score) {
    const titleInput = document.getElementById('ai_tab_topic');
    const fileInput = document.getElementById('ai_tab_abstract_file');
    const hasExisting = <?php echo $has_abstract_pdf ? 'true' : 'false'; ?>;
    const title = titleInput ? titleInput.value.trim() : '';

    if (!hasExisting) {
        if (typeof window.showUniversityNotice === 'function') {
            window.showUniversityNotice({
                heading: 'Academic Requirement Notice',
                pillText: 'Official Academic Compliance',
                title: 'Capstone Concept PDF Required',
                message: 'In compliance with CEIT academic policy, you cannot select an adviser without uploading your official Capstone Concept in PDF format (.pdf).',
                hint: 'Please upload and save your Capstone Concept (.pdf) in the form above before submitting an adviser request.',
                targetFocusId: 'ai_tab_abstract_file',
                primaryBtnText: 'Upload Concept PDF'
            });
        } else {
            alert('Capstone Concept PDF Required: In compliance with CEIT academic policy, you cannot proceed to select an adviser without uploading your Capstone Concept in PDF format (.pdf).');
        }
        if (fileInput) {
            fileInput.focus();
            fileInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        return false;
    }

    if (!title) {
        if (typeof window.showUniversityNotice === 'function') {
            window.showUniversityNotice({
                heading: 'Academic Submission Notice',
                pillText: 'Proposed Title Required',
                title: 'Proposed Title Required',
                message: 'Please enter your proposed capstone title first so your adviser knows your capstone topic.',
                hint: 'Your proposed title is attached to your adviser match request.',
                targetFocusId: 'ai_tab_topic',
                primaryBtnText: 'Enter Proposed Title'
            });
        } else {
            alert('Proposed Title Required: Please enter your proposed capstone title first.');
        }
        if (titleInput) {
            titleInput.focus();
            titleInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        return false;
    }
    window.location.href = `action.php?request_adviser=1&adviser_id=${adviserId}&match_score=${score}&title=${encodeURIComponent(title)}&return_tab=submission`;
}

document.getElementById('aiTabTitleForm')?.addEventListener('submit', function(e) {
    const hasExisting = <?php echo $has_abstract_pdf ? 'true' : 'false'; ?>;
    const fileInput = document.getElementById('ai_tab_abstract_file');
    const hasNewFile = fileInput && fileInput.files && fileInput.files.length > 0;
    if (!hasExisting && !hasNewFile) {
        e.preventDefault();
        if (typeof window.showUniversityNotice === 'function') {
            window.showUniversityNotice({
                heading: 'Academic Requirement Notice',
                pillText: 'Official Academic Compliance',
                title: 'Capstone Concept PDF Required',
                message: 'In compliance with CEIT academic policy, you cannot proceed without uploading your official Capstone Concept in PDF format (.pdf).',
                hint: 'Please attach your Capstone Concept in PDF format (.pdf) before saving or updating your proposed title.',
                targetFocusId: 'ai_tab_abstract_file',
                primaryBtnText: 'Attach Concept PDF Now'
            });
        } else {
            alert('Capstone Concept PDF Required: In compliance with CEIT academic policy, you cannot proceed without uploading your Capstone Concept in PDF format (.pdf).');
        }
        if (fileInput) {
            fileInput.focus();
            fileInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        return false;
    }
});
</script>
