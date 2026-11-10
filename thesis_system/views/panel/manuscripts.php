<?php
// Candidate Manuscripts & Research Papers Hub for Panel Committee (Chapters 1 to References)

// Fetch all assigned theses with group, student, and defense schedule details
$dept_filter = isset($_GET['dept']) ? $_GET['dept'] : 'all';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

$esc_pname = $conn->real_escape_string($name);

$query = "
    SELECT tp.id as tp_id, tp.student_id, tp.grade, tp.feedback, tp.status as grading_status,
           u.name as student_name, u.email as student_email,
           COALESCE(NULLIF(sp.thesis_topic, ''), cg.thesis_topic, 'Capstone Research Topic') as thesis_topic,
           sp.department, sp.student_id as student_school_id,
           COALESCE(NULLIF(sp.manuscript_file, ''), cg.manuscript_file) as manuscript_file,
           COALESCE(NULLIF(sp.shareable_link, ''), cg.shareable_link) as shareable_link,
           COALESCE(NULLIF(sp.manuscript_name, ''), cg.manuscript_name) as manuscript_name,
           COALESCE(NULLIF(sp.manuscript_stage, ''), cg.manuscript_stage, 'Proposal Defense') as manuscript_stage,
           COALESCE(NULLIF(sp.manuscript_updated_at, ''), cg.manuscript_updated_at) as manuscript_updated_at,
           COALESCE(NULLIF(sp.manuscript_notes, ''), cg.manuscript_notes) as manuscript_notes,
           cg.group_name, cg.leader_id, cg.member2_name, cg.member3_name,
           ds.meeting_date, ds.start_time, ds.end_time, ds.venue, ds.defense_stage as sched_defense_stage,
           adv.name as adviser_name
    FROM thesis_panel tp
    JOIN user u ON tp.student_id = u.id
    JOIN student_profile sp ON u.id = sp.user_id
    LEFT JOIN capstone_group cg ON sp.group_id = cg.id
    LEFT JOIN defense_schedule ds ON (ds.student_id = tp.student_id OR (cg.id IS NOT NULL AND ds.group_id = cg.id))
    LEFT JOIN user adv ON ds.adviser_id = adv.id
    WHERE tp.panel_id = $uid
";

if ($dept_filter !== 'all') {
    $esc_dept = $conn->real_escape_string($dept_filter);
    $query .= " AND sp.department = '$esc_dept'";
}

if (!empty($search_query)) {
    $esc_s = $conn->real_escape_string($search_query);
    $query .= " AND (u.name LIKE '%$esc_s%' OR sp.thesis_topic LIKE '%$esc_s%' OR cg.group_name LIKE '%$esc_s%')";
}

$query .= " GROUP BY tp.id ORDER BY (sp.manuscript_file IS NOT NULL OR sp.shareable_link IS NOT NULL) DESC, ds.meeting_date ASC, tp.id DESC";
$manuscripts_res = $conn->query($query);
$total_assigned = $manuscripts_res ? $manuscripts_res->num_rows : 0;
?>

<div style="max-width: 1350px; margin: 0 auto; padding-bottom: 3rem;">
    <!-- Institutional Banner -->
    <div style="background: linear-gradient(135deg, #022C22 0%, #064E3B 60%, #047857 100%); color: white; padding: 2rem 2.25rem; border-radius: 16px; margin-bottom: 1.5rem; box-shadow: 0 10px 25px -5px rgba(2, 44, 34, 0.3); position: relative; overflow: hidden;">
        <div style="position: absolute; right: -20px; bottom: -30px; opacity: 0.12; font-size: 11rem; pointer-events: none;">
            <i class="fa-solid fa-book-open-reader"></i>
        </div>
        <div style="display: flex; align-items: center; gap: 0.65rem; margin-bottom: 0.5rem;">
            <span style="background: rgba(255,255,255,0.2); padding: 3px 10px; border-radius: 999px; font-size: 0.74rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;">
                <i class="fa-solid fa-shield-halved"></i> CEIT Oral Defense Panel Repository
            </span>
            <span style="background: #FCD34D; color: #064E3B; padding: 3px 10px; border-radius: 999px; font-size: 0.74rem; font-weight: 800;">
                Chapters 1 to References Reader
            </span>
        </div>
        <h1 style="margin: 0 0 0.5rem 0; font-size: 1.6rem; font-weight: 900; letter-spacing: -0.5px;">
            Candidate Manuscripts &amp; Research Papers
        </h1>
        <p style="margin: 0; font-size: 0.88rem; color: #A7F3D0; max-width: 820px; line-height: 1.55;">
            Directly access and inspect complete capstone manuscripts (Chapters 1 to References, Appendices, and System Architectures) submitted by your assigned candidates. Read papers in-portal or via live cloud documents prior to and during CEIT-T07A rubric evaluation.
        </p>
    </div>

    <!-- Filter & Search Toolbar -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <!-- Department Pills -->
        <div style="display: flex; gap: 0.4rem; flex-wrap: wrap;">
            <?php
            $depts = [
                'all' => 'All Departments',
                'Information Technology' => 'Information Technology',
                'Computer Engineering' => 'Computer Engineering',
                'Electrical Engineering' => 'Electrical Engineering'
            ];
            foreach ($depts as $d_key => $d_label):
                $active = ($dept_filter === $d_key) ? 'background: #047857; color: white; border-color: #047857;' : 'background: white; color: #334155; border-color: #CBD5E1;';
            ?>
                <a href="?tab=manuscripts&dept=<?php echo urlencode($d_key); ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>"
                   style="text-decoration: none; padding: 0.45rem 0.9rem; border-radius: 999px; font-size: 0.78rem; font-weight: 700; border: 1.5px solid; transition: all 0.15s ease; <?php echo $active; ?>">
                    <?php echo $d_label; ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Search Bar -->
        <form method="GET" action="panel_dashboard.php" style="display: flex; gap: 0.4rem; align-items: center;">
            <input type="hidden" name="tab" value="manuscripts">
            <input type="hidden" name="dept" value="<?php echo htmlspecialchars($dept_filter); ?>">
            <div style="position: relative;">
                <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94A3B8; font-size: 0.82rem;"></i>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Search topic or candidate..." style="padding: 0.5rem 0.85rem 0.5rem 2rem; border-radius: 8px; border: 1.5px solid #CBD5E1; font-size: 0.82rem; width: 240px; font-family: inherit;">
            </div>
            <button type="submit" class="btn-primary" style="padding: 0.5rem 0.9rem; font-size: 0.82rem; font-weight: 700; border-radius: 8px; background: #047857; border: none; cursor: pointer;">
                Filter
            </button>
            <?php if (!empty($search_query) || $dept_filter !== 'all'): ?>
                <a href="?tab=manuscripts" class="btn" style="padding: 0.5rem 0.75rem; background: #E2E8F0; color: #475569; border-radius: 8px; font-size: 0.82rem; text-decoration: none;" title="Reset filter">
                    <i class="fa-solid fa-xmark"></i>
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Assigned Candidate Manuscripts List -->
    <?php if ($total_assigned == 0): ?>
        <div class="card" style="border-radius: 14px;">
            <div class="card-body" style="text-align: center; padding: 4rem 1.5rem; color: #64748B;">
                <i class="fa-solid fa-folder-open" style="font-size: 3.5rem; color: #CBD5E1; margin-bottom: 0.85rem;"></i>
                <h3 style="margin: 0 0 0.4rem 0; font-size: 1.15rem; color: #0F172A; font-weight: 800;">No Candidate Manuscripts Found</h3>
                <p style="margin: 0 auto; max-width: 480px; font-size: 0.88rem;">
                    <?php echo !empty($search_query) ? 'No assigned capstones matched your search. Try another search keyword.' : 'When students submit their complete Chapter 1 to References manuscripts for your defense committee, they will appear here.'; ?>
                </p>
            </div>
        </div>
    <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 1.25rem;">
            <?php while ($row = $manuscripts_res->fetch_assoc()):
                $has_file = !empty($row['manuscript_file']);
                $has_link = !empty($row['shareable_link']);
                $has_manuscript = ($has_file || $has_link);
                $is_graded = ($row['grading_status'] === 'graded');
                $grade_val = $row['grade'] !== null ? number_format((float)$row['grade'], 1) : null;

                // Presenters / Group members
                $members = [];
                if (!empty($row['student_name'])) $members[] = $row['student_name'];
                if (!empty($row['member2_name'])) $members[] = $row['member2_name'];
                if (!empty($row['member3_name'])) $members[] = $row['member3_name'];
                $members_str = implode(', ', $members);
            ?>
                <div class="card" style="border-radius: 14px; border: 1.5px solid <?php echo $has_manuscript ? '#E2E8F0' : '#FDE68A'; ?>; overflow: hidden; box-shadow: 0 4px 14px rgba(0,0,0,0.04); transition: transform 0.15s ease;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
                    <div style="padding: 1.25rem 1.5rem; background: #FFFFFF; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1.25rem;">
                        <!-- Left Details Column -->
                        <div style="flex: 1; min-width: 320px;">
                            <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 0.45rem;">
                                <!-- Manuscript Availability Badge -->
                                <?php if ($has_manuscript): ?>
                                    <span class="badge" style="background: #DCFCE7; color: #15803D; border: 1px solid #86EFAC; font-weight: 800; font-size: 0.74rem; padding: 3px 9px; border-radius: 6px;">
                                        <i class="fa-solid fa-circle-check"></i> MANUSCRIPT ON FILE
                                    </span>
                                <?php else: ?>
                                    <span class="badge" style="background: #FEF3C7; color: #92400E; border: 1px solid #FCD34D; font-weight: 800; font-size: 0.74rem; padding: 3px 9px; border-radius: 6px;">
                                        <i class="fa-solid fa-triangle-exclamation"></i> MANUSCRIPT PENDING SUBMISSION
                                    </span>
                                <?php endif; ?>

                                <!-- Defense Stage Badge -->
                                <span class="badge" style="background: #EFF6FF; color: #1D4ED8; border: 1px solid #BFDBFE; font-weight: 700; font-size: 0.74rem; padding: 3px 8px; border-radius: 6px;">
                                    <?php echo htmlspecialchars($row['manuscript_stage']); ?>
                                </span>

                                <!-- Department Badge -->
                                <span style="font-size: 0.75rem; color: #475569; font-weight: 600;">
                                    <i class="fa-solid fa-building-columns" style="color: #047857;"></i> <?php echo htmlspecialchars($row['department']); ?>
                                </span>

                                <!-- Grading Status Badge -->
                                <?php if ($is_graded): ?>
                                    <span class="badge" style="background: #ECFDF5; color: #047857; font-weight: 800; font-size: 0.74rem; padding: 3px 8px; border-radius: 6px;">
                                        <i class="fa-solid fa-star" style="color: #F59E0B;"></i> CEIT-T07A Grade: <?php echo $grade_val; ?>/100
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- Topic Title -->
                            <h3 style="margin: 0 0 0.45rem 0; font-size: 1.15rem; font-weight: 800; color: #0F172A; line-height: 1.45;">
                                <?php echo htmlspecialchars($row['thesis_topic']); ?>
                            </h3>

                            <!-- Proponents & Adviser -->
                            <div style="font-size: 0.84rem; color: #334155; margin-bottom: 0.65rem;">
                                <strong><i class="fa-solid fa-users" style="color: #047857;"></i> Proponents:</strong> 
                                <?php echo !empty($row['group_name']) ? "<strong>Group {$row['group_name']}</strong> — " : ""; ?>
                                <?php echo htmlspecialchars($members_str); ?>
                                <?php if(!empty($row['adviser_name'])): ?>
                                    <span style="color: #64748B; margin-left: 0.5rem;">&bull; <strong>Adviser:</strong> <?php echo htmlspecialchars($row['adviser_name']); ?></span>
                                <?php endif; ?>
                            </div>

                            <!-- Schedule & Submission Info -->
                            <div style="display: flex; gap: 1rem; flex-wrap: wrap; font-size: 0.78rem; color: #64748B; background: #F8FAFC; border: 1px solid #E2E8F0; padding: 0.5rem 0.85rem; border-radius: 8px;">
                                <?php if (!empty($row['meeting_date'])): ?>
                                    <span>
                                        <i class="fa-regular fa-calendar-check" style="color: #047857;"></i>
                                        <strong>Defense Schedule:</strong> <?php echo date('M d, Y', strtotime($row['meeting_date'])); ?> 
                                        <?php if (!empty($row['start_time'])): ?>
                                            (<?php echo date('g:i A', strtotime($row['start_time'])); ?>)
                                        <?php endif; ?>
                                        &bull; <?php echo htmlspecialchars($row['venue'] ?: 'CEIT Defense Hall'); ?>
                                    </span>
                                <?php endif; ?>

                                <?php if ($has_file): ?>
                                    <span>
                                        <i class="fa-solid fa-paperclip" style="color: #047857;"></i> 
                                        <strong>File:</strong> <?php echo htmlspecialchars($row['manuscript_name'] ?: basename($row['manuscript_file'])); ?>
                                    </span>
                                <?php endif; ?>

                                <?php if (!empty($row['manuscript_updated_at'])): ?>
                                    <span>
                                        <i class="fa-regular fa-clock" style="color: #047857;"></i> 
                                        <strong>Uploaded:</strong> <?php echo date('M d, Y h:i A', strtotime($row['manuscript_updated_at'])); ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- Chapter 1 to References Breadcrumb Bar -->
                            <div style="margin-top: 0.65rem; display: flex; gap: 0.35rem; flex-wrap: wrap; font-size: 0.72rem;">
                                <span style="background: #F1F5F9; color: #334155; padding: 2px 7px; border-radius: 4px; font-weight: 700;">Ch 1: Problem Formulation</span>
                                <span style="background: #F1F5F9; color: #334155; padding: 2px 7px; border-radius: 4px; font-weight: 700;">Ch 2: Literature &amp; Theories</span>
                                <span style="background: #F1F5F9; color: #334155; padding: 2px 7px; border-radius: 4px; font-weight: 700;">Ch 3: System Architecture</span>
                                <span style="background: #F1F5F9; color: #334155; padding: 2px 7px; border-radius: 4px; font-weight: 700;">Ch 4: Results</span>
                                <span style="background: #F1F5F9; color: #334155; padding: 2px 7px; border-radius: 4px; font-weight: 700;">Ch 5: Conclusions</span>
                                <span style="background: #ECFDF5; color: #047857; padding: 2px 7px; border-radius: 4px; font-weight: 800;">References (IEEE)</span>
                            </div>
                        </div>

                        <!-- Right Action Buttons Column -->
                        <div style="display: flex; flex-direction: column; gap: 0.5rem; min-width: 200px;">
                            <!-- Option 1: Read PDF Manuscript -->
                            <?php if ($has_file): ?>
                                <button type="button" onclick="openManuscriptViewerModal('<?php echo htmlspecialchars($row['manuscript_file'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars(addslashes($row['thesis_topic']), ENT_QUOTES); ?>', '<?php echo (int)$row['tp_id']; ?>')" class="btn-primary" style="background: #DC2626; border-color: #DC2626; padding: 0.55rem 1rem; font-size: 0.82rem; font-weight: 800; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; gap: 0.45rem; cursor: pointer; box-shadow: 0 4px 10px rgba(220, 38, 38, 0.2);">
                                    <i class="fa-solid fa-book-open"></i> Read Manuscript (PDF)
                                </button>
                                <a href="<?php echo htmlspecialchars($row['manuscript_file']); ?>" target="_blank" download class="btn" style="background: #FFFFFF; border: 1px solid #CBD5E1; color: #475569; padding: 0.45rem 1rem; font-size: 0.78rem; font-weight: 700; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 0.35rem;">
                                    <i class="fa-solid fa-download"></i> Download Local File
                                </a>
                            <?php endif; ?>

                            <!-- Option 2: Live Cloud Document (Google Docs) -->
                            <?php if ($has_link): ?>
                                <a href="<?php echo htmlspecialchars($row['shareable_link']); ?>" target="_blank" rel="noopener noreferrer" class="btn" style="background: #2563EB; color: white; border: none; padding: 0.55rem 1rem; font-size: 0.82rem; font-weight: 800; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 0.45rem; box-shadow: 0 4px 10px rgba(37, 99, 235, 0.2);">
                                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Open Live Google Docs
                                </a>
                            <?php endif; ?>

                            <!-- Action: Grade via CEIT-T07A Rubric -->
                            <a href="?tab=grading&tp_id=<?php echo $row['tp_id']; ?>" class="btn-primary" style="background: #047857; border-color: #047857; padding: 0.55rem 1rem; font-size: 0.82rem; font-weight: 800; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 0.45rem;">
                                <i class="fa-solid fa-clipboard-check"></i> <?php echo $is_graded ? 'Review / Edit Grade' : 'Grade (CEIT-T07A)'; ?>
                            </a>

                            <!-- Action: Panel Comments -->
                            <a href="?tab=comments&student_id=<?php echo $row['student_id']; ?>" class="btn btn-outline" style="background: #FFFFFF; color: #166534; border-color: #BBF7D0; padding: 0.45rem 1rem; font-size: 0.78rem; font-weight: 700; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 0.35rem;">
                                <i class="fa-solid fa-comments"></i> Chapter Comments &amp; Voice
                            </a>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>
</div>

<!-- ======================================================== -->
<!-- MODAL: INTEGRATED MANUSCRIPT PDF READER & EXAMINER       -->
<!-- ======================================================== -->
<div id="manuscriptViewerModal" class="modal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.75); z-index: 99999; backdrop-filter: blur(4px); align-items: center; justify-content: center;">
    <div style="background: #FFFFFF; width: 95%; max-width: 1200px; height: 92vh; border-radius: 14px; overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 25px 60px rgba(0,0,0,0.35);">
        <!-- Modal Top Header -->
        <div style="background: #022C22; color: white; padding: 0.85rem 1.25rem; display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-shrink: 0;">
            <div style="display: flex; align-items: center; gap: 0.75rem; min-width: 0;">
                <div style="width: 36px; height: 36px; border-radius: 8px; background: #DC2626; color: white; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0;">
                    <i class="fa-solid fa-file-pdf"></i>
                </div>
                <div style="min-width: 0;">
                    <div style="font-size: 0.72rem; text-transform: uppercase; font-weight: 800; color: #A7F3D0; letter-spacing: 0.5px;">
                        CEIT Official Manuscript Reader (Chapters 1 to References)
                    </div>
                    <div id="modalTopicTitle" style="font-size: 0.96rem; font-weight: 800; color: #FFFFFF; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        Manuscript Title
                    </div>
                </div>
            </div>

            <div style="display: flex; align-items: center; gap: 0.65rem; flex-shrink: 0;">
                <a id="modalExternalOpenBtn" href="#" target="_blank" class="btn" style="background: rgba(255,255,255,0.15); color: white; border: 1px solid rgba(255,255,255,0.25); font-size: 0.78rem; font-weight: 700; padding: 0.4rem 0.8rem; border-radius: 6px; text-decoration: none;">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Open Full Tab
                </a>
                <a id="modalGradeJumpBtn" href="#" class="btn-primary" style="background: #059669; border: none; font-size: 0.78rem; font-weight: 800; padding: 0.4rem 0.85rem; border-radius: 6px; text-decoration: none;">
                    <i class="fa-solid fa-clipboard-check"></i> Open Rubric &amp; Grade (CEIT-T07A)
                </a>
                <button type="button" onclick="closeManuscriptViewerModal()" style="background: transparent; border: none; color: #A7F3D0; font-size: 1.6rem; cursor: pointer; line-height: 1; padding: 0 0.35rem;" title="Close Viewer">
                    &times;
                </button>
            </div>
        </div>

        <!-- Embedded PDF Viewer Frame -->
        <div style="flex: 1; background: #334155; position: relative;">
            <iframe id="manuscriptIframe" src="" style="width: 100%; height: 100%; border: none;" title="Manuscript Document Reader"></iframe>
        </div>
    </div>
</div>

<script>
function openManuscriptViewerModal(pdfUrl, topicTitle, tpId) {
    const modal = document.getElementById('manuscriptViewerModal');
    const iframe = document.getElementById('manuscriptIframe');
    const titleEl = document.getElementById('modalTopicTitle');
    const extBtn = document.getElementById('modalExternalOpenBtn');
    const gradeBtn = document.getElementById('modalGradeJumpBtn');

    titleEl.innerText = topicTitle || 'Capstone Manuscript';
    iframe.src = pdfUrl;
    extBtn.href = pdfUrl;
    if (tpId > 0) {
        gradeBtn.href = `panel_dashboard.php?tab=grading&tp_id=${tpId}`;
        gradeBtn.style.display = 'inline-flex';
    } else {
        gradeBtn.style.display = 'none';
    }

    modal.style.display = 'flex';
}

function closeManuscriptViewerModal() {
    const modal = document.getElementById('manuscriptViewerModal');
    const iframe = document.getElementById('manuscriptIframe');
    modal.style.display = 'none';
    iframe.src = '';
}

// Close on escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeManuscriptViewerModal();
    }
});
</script>

