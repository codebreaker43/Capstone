<?php
// STUDENT PANELISTS, COMMENTS & DEFENSE MEETING DOCUMENTATION VIEW

$panel_where = (!empty($group_id)) ? "(tp.group_id = $group_id OR tp.student_id IN ($group_student_ids_str))" : "tp.student_id = $uid";
$panel_res = $conn->query("
    SELECT u.name, u.email, pp.department, pp.expertise, tp.status, tp.grade 
    FROM thesis_panel tp 
    JOIN user u ON tp.panel_id = u.id 
    JOIN panel_profile pp ON u.id = pp.user_id 
    WHERE $panel_where
");

// Fetch Panel Comments for this student / group
$where_comments = (!empty($group_id)) ? "(prc.group_id = $group_id OR prc.student_id IN ($group_student_ids_str))" : "prc.student_id = $uid";
$comments_res = $conn->query("
    SELECT prc.*, u.name as author_name 
    FROM panel_research_comments prc
    JOIN user u ON prc.panel_id = u.id
    WHERE $where_comments
    ORDER BY prc.id DESC
");
$all_comments = [];
if ($comments_res) {
    while($c = $comments_res->fetch_assoc()) {
        $all_comments[] = $c;
    }
}

// Fetch Defense Meeting and Minutes (MOM)
$where_meeting = (!empty($group_id)) ? "(group_id = $group_id OR student_id IN ($group_student_ids_str))" : "student_id = $uid";
$meeting_res = $conn->query("SELECT * FROM defense_meetings WHERE $where_meeting ORDER BY id DESC LIMIT 1");
$defense_meeting = ($meeting_res && $meeting_res->num_rows > 0) ? $meeting_res->fetch_assoc() : null;

$defense_mom = null;
$defense_transcripts = [];
if ($defense_meeting) {
    $mid = (int)$defense_meeting['id'];
    $mom_q = $conn->query("SELECT * FROM defense_meeting_minutes WHERE meeting_id = $mid");
    if ($mom_q && $mom_q->num_rows > 0) {
        $defense_mom = $mom_q->fetch_assoc();
    }

    $t_q = $conn->query("SELECT * FROM defense_transcripts WHERE meeting_id = $mid ORDER BY id ASC");
    if ($t_q) {
        while($t = $t_q->fetch_assoc()) {
            $defense_transcripts[] = $t;
        }
    }
}
?>

<div style="display: flex; flex-direction: column; gap: 1.25rem;">
    
    <!-- Panel Committee Roster -->
    <div class="card">
        <div class="card-header">
            <div class="card-header-left">
                <i class="fa-solid fa-users"></i>
                <h2>My Capstone Panel Committee</h2>
            </div>
            <span class="badge badge-info"><?php echo $panel_res->num_rows; ?> Panelists</span>
        </div>
        <div class="card-body">
            <?php if($panel_res->num_rows == 0): ?>
                <div style="text-align: center; color: var(--text-muted); padding: 2.5rem 1rem;">
                    <i class="fa-solid fa-user-clock fa-3x" style="margin-bottom: 1rem; color: #CBD5E1; display: block;"></i>
                    <p style="color: #64748B; font-size: 0.9rem; margin: 0;">Your committee panelists will be assigned once your proposal is verified and approved by your adviser.</p>
                </div>
            <?php else: ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1rem;">
                    <?php while($p = $panel_res->fetch_assoc()): ?>
                        <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 10px; padding: 1.15rem; display: flex; flex-direction: column; gap: 0.4rem; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='var(--shadow-md)';" onmouseout="this.style.transform='none'; this.style.boxShadow='none';">
                            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.25rem;">
                                <div style="width: 40px; height: 40px; background: var(--primary-blue); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.05rem; font-weight: bold; flex-shrink: 0;">
                                    <?php echo strtoupper(substr($p['name'], 0, 1)); ?>
                                </div>
                                <div>
                                    <h3 style="margin: 0; color: #1E293B; font-size: 0.95rem; font-weight: 700;"><?php echo htmlspecialchars($p['name']); ?></h3>
                                    <p style="margin: 0; color: var(--primary-blue); font-size: 0.78rem; font-weight: 600;"><i class="fa-solid fa-graduation-cap"></i> Panel Member</p>
                                </div>
                            </div>
                            
                            <p style="margin: 0; color: #64748B; font-size: 0.82rem;"><i class="fa-solid fa-envelope" style="width: 18px;"></i> <?php echo htmlspecialchars($p['email']); ?></p>
                            <p style="margin: 0; color: #64748B; font-size: 0.82rem;"><i class="fa-solid fa-building" style="width: 18px;"></i> <?php echo htmlspecialchars($p['department']); ?></p>
                            
                            <?php if(!empty($p['expertise'])): ?>
                                <p style="margin: 0; color: #64748B; font-size: 0.82rem;"><i class="fa-solid fa-tags" style="width: 18px;"></i> <?php echo htmlspecialchars($p['expertise']); ?></p>
                            <?php endif; ?>
                            
                            <div style="margin-top: 0.65rem; padding-top: 0.65rem; border-top: 1px dashed #CBD5E1; text-align: center;">
                                <?php if($p['status'] == 'graded'): ?>
                                    <span style="background: #D1FAE5; color: #065F46; padding: 0.3rem 0.75rem; border-radius: 16px; font-weight: 700; font-size: 0.78rem; display: inline-block;"><i class="fa-solid fa-check-circle"></i> Graded: <?php echo $p['grade']; ?>%</span>
                                <?php else: ?>
                                    <span style="background: #FEF3C7; color: #B45309; padding: 0.3rem 0.75rem; border-radius: 16px; font-weight: 600; font-size: 0.78rem; display: inline-block;"><i class="fa-solid fa-clock"></i> Evaluation Pending</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- SUBMIT MANUSCRIPT FOR PANEL COMMITTEE REVIEW CARD -->
    <div class="card" id="manuscript-section" style="border-top: 4px solid var(--csu-green); box-shadow: var(--shadow-sm); scroll-margin-top: 80px;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
            <div class="card-header-left" style="display: flex; align-items: center; gap: 0.65rem;">
                <i class="fa-solid fa-file-circle-check" style="color: var(--csu-green); font-size: 1.25rem;"></i>
                <div>
                    <h2 style="margin: 0; font-size: 1.15rem; color: #0F172A;">Defense Manuscript for Panel Review</h2>
                    <span style="font-size: 0.8rem; color: #64748B;">
                        Submit your research manuscript so your assigned Panel Committee can inspect your paper, review defense chapters, and evaluate your defense.
                    </span>
                </div>
            </div>

            <?php 
            $curr_m_file = !empty($profile['manuscript_file']) ? $profile['manuscript_file'] : (!empty($my_group['manuscript_file']) ? $my_group['manuscript_file'] : '');
            $curr_m_link = !empty($profile['shareable_link']) ? $profile['shareable_link'] : (!empty($my_group['shareable_link']) ? $my_group['shareable_link'] : '');
            $curr_m_name = !empty($profile['manuscript_name']) ? $profile['manuscript_name'] : (!empty($my_group['manuscript_name']) ? $my_group['manuscript_name'] : basename($curr_m_file));
            $curr_m_stage = !empty($profile['manuscript_stage']) ? $profile['manuscript_stage'] : (!empty($my_group['manuscript_stage']) ? $my_group['manuscript_stage'] : 'Proposal Defense');
            $curr_m_updated = !empty($profile['manuscript_updated_at']) ? $profile['manuscript_updated_at'] : (!empty($my_group['manuscript_updated_at']) ? $my_group['manuscript_updated_at'] : '');
            $has_active_manuscript = (!empty($curr_m_file) || !empty($curr_m_link));
            ?>

            <div>
                <?php if ($has_active_manuscript): ?>
                    <span class="badge badge-success" style="font-size: 0.82rem; padding: 0.4rem 0.85rem;">
                        <i class="fa-solid fa-circle-check"></i> Available to Panel Committee
                    </span>
                <?php else: ?>
                    <span class="badge badge-warning" style="font-size: 0.82rem; padding: 0.4rem 0.85rem;">
                        <i class="fa-solid fa-triangle-exclamation"></i> Action Required: Upload Manuscript
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <div class="card-body">
            <!-- CURRENT ACTIVE MANUSCRIPT SUMMARY BANNER -->
            <?php if ($has_active_manuscript): ?>
                <div style="background: #F0FDF4; border: 1.5px solid #86EFAC; border-radius: 10px; padding: 1.15rem; margin-bottom: 1.25rem;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
                        <div>
                            <div style="font-size: 0.72rem; font-weight: 800; color: #166534; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.25rem;">
                                <i class="fa-solid fa-shield-check"></i> Active Defense Manuscript on File
                            </div>
                            <h3 style="margin: 0 0 0.35rem 0; font-size: 1.05rem; font-weight: 700; color: #0F172A;">
                                <?php echo htmlspecialchars($profile['thesis_topic'] ?: 'Capstone Research Manuscript'); ?>
                            </h3>
                            <div style="display: flex; gap: 1rem; flex-wrap: wrap; font-size: 0.82rem; color: #475569;">
                                <span><strong>Defense Stage:</strong> <span class="badge" style="background:#DCFCE7; color:#15803D;"><?php echo htmlspecialchars($curr_m_stage); ?></span></span>
                                <?php if (!empty($curr_m_updated)): ?>
                                    <span><strong>Submitted:</strong> <?php echo date('M d, Y h:i A', strtotime($curr_m_updated)); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
                            <?php if (!empty($curr_m_file)): ?>
                                <a href="<?php echo htmlspecialchars($curr_m_file); ?>" target="_blank" class="btn" style="padding: 0.5rem 1rem; background: #DC2626; color: white; border: none; border-radius: 8px; font-size: 0.82rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.4rem; text-decoration: none;">
                                    <i class="fa-solid fa-file-pdf"></i> View / Download File
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($curr_m_link)): ?>
                                <a href="<?php echo htmlspecialchars($curr_m_link); ?>" target="_blank" rel="noopener noreferrer" class="btn" style="padding: 0.5rem 1rem; background: #2563EB; color: white; border: none; border-radius: 8px; font-size: 0.82rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.4rem; text-decoration: none;">
                                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Open Live Doc (Google Docs)
                                </a>
                            <?php endif; ?>
                            <button type="button" onclick="const sec = document.getElementById('uploadManuscriptFormSection'); sec.style.display = (sec.style.display === 'none' ? 'block' : 'none'); if(sec.style.display === 'block') sec.scrollIntoView({behavior:'smooth'});" class="btn" style="padding: 0.5rem 0.95rem; background: white; border: 1px solid #CBD5E1; color: #334155; border-radius: 8px; font-size: 0.82rem; font-weight: 600; cursor: pointer;">
                                <i class="fa-solid fa-pen-to-square"></i> Update / Replace Manuscript
                            </button>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div style="background: #FFFBEB; border: 1.5px solid #FDE68A; border-radius: 10px; padding: 1rem 1.25rem; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.75rem;">
                    <i class="fa-solid fa-triangle-exclamation" style="color: #D97706; font-size: 1.4rem;"></i>
                    <div style="font-size: 0.85rem; color: #92400E;">
                        <strong>Panel Review Requirement:</strong> Submit your capstone manuscript below so your assigned committee panelists can inspect your paper, provide revision comments, and grade your defense.
                    </div>
                </div>
            <?php endif; ?>

            <!-- UPLOAD / SUBMIT MANUSCRIPT FORM -->
            <div id="uploadManuscriptFormSection" style="<?php echo $has_active_manuscript ? 'display: none;' : ''; ?> background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 10px; padding: 1.25rem;">
                <h4 style="margin: 0 0 0.85rem 0; font-size: 0.98rem; font-weight: 700; color: #0F172A; display: flex; align-items: center; gap: 0.45rem;">
                    <i class="fa-solid fa-cloud-arrow-up" style="color: var(--csu-green);"></i>
                    <?php echo $has_active_manuscript ? 'Update / Submit New Manuscript Version' : 'Submit Manuscript for Panel Committee'; ?>
                </h4>

                <form method="POST" action="action.php" enctype="multipart/form-data">
                    <input type="hidden" name="submit_panel_manuscript" value="1">
                    <input type="hidden" name="redirect_tab" value="panelists">

                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div>
                            <label style="font-size: 0.82rem; font-weight: 700; color: #334155; display: block; margin-bottom: 0.35rem;">
                                Capstone Title / Research Topic <span style="color:#DC2626;">*</span>
                            </label>
                            <input type="text" name="thesis_topic" value="<?php echo htmlspecialchars($profile['thesis_topic'] ?? ''); ?>" required placeholder="Enter approved capstone title" class="form-control" style="width: 100%; padding: 0.6rem 0.85rem; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 0.88rem; background: white;">
                        </div>
                        <div>
                            <label style="font-size: 0.82rem; font-weight: 700; color: #334155; display: block; margin-bottom: 0.35rem;">
                                Defense Stage <span style="color:#DC2626;">*</span>
                            </label>
                            <select name="manuscript_stage" class="form-control" style="width: 100%; padding: 0.6rem 0.85rem; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 0.88rem; background: white;">
                                <option value="Proposal Defense" <?php echo ($curr_m_stage === 'Proposal Defense') ? 'selected' : ''; ?>>Proposal Defense (Chapters 1-3)</option>
                                <option value="Final Oral Defense" <?php echo ($curr_m_stage === 'Final Oral Defense') ? 'selected' : ''; ?>>Final Oral Defense (Chapters 1-5)</option>
                                <option value="Title Proposal" <?php echo ($curr_m_stage === 'Title Proposal') ? 'selected' : ''; ?>>Title / Concept Paper</option>
                                <option value="Manuscript Re-defense / Compliance" <?php echo ($curr_m_stage === 'Manuscript Re-defense / Compliance') ? 'selected' : ''; ?>>Revised Manuscript (Post-Defense Compliance)</option>
                            </select>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <!-- File Upload -->
                        <div style="background: white; border: 1px solid #CBD5E1; border-radius: 8px; padding: 0.85rem;">
                            <label style="font-size: 0.82rem; font-weight: 700; color: #047857; display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.35rem;">
                                <i class="fa-solid fa-file-arrow-up"></i> Upload File (PDF / DOCX)
                            </label>
                            <input type="file" name="manuscript_file" accept=".pdf,.doc,.docx" class="form-control" style="width: 100%; padding: 0.45rem; font-size: 0.82rem;">
                            <small style="color: #64748B; font-size: 0.74rem; display: block; margin-top: 0.35rem;">
                                Max 50MB. PDF is highly recommended for best formatting preservation.
                            </small>
                        </div>

                        <!-- Live Link -->
                        <div style="background: white; border: 1px solid #CBD5E1; border-radius: 8px; padding: 0.85rem;">
                            <label style="font-size: 0.82rem; font-weight: 700; color: #2563EB; display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.35rem;">
                                <i class="fa-solid fa-link"></i> Live Cloud Link (Google Docs / OneDrive)
                            </label>
                            <input type="url" name="shareable_link" value="<?php echo htmlspecialchars($curr_m_link); ?>" placeholder="https://docs.google.com/document/d/..." class="form-control" style="width: 100%; padding: 0.55rem 0.75rem; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 0.82rem;">
                            <small style="color: #64748B; font-size: 0.74rem; display: block; margin-top: 0.35rem;">
                                Set share permission to <em>"Anyone with link can comment/view"</em>.
                            </small>
                        </div>
                    </div>

                    <div style="margin-bottom: 1rem;">
                        <label style="font-size: 0.82rem; font-weight: 700; color: #334155; display: block; margin-bottom: 0.35rem;">
                            Notes / Executive Summary for Panelists (Optional)
                        </label>
                        <textarea name="manuscript_notes" rows="2" placeholder="Brief summary of your project, key highlights, or specific chapters you want the panel to focus on..." class="form-control" style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 0.82rem;"><?php echo htmlspecialchars($profile['manuscript_notes'] ?? ''); ?></textarea>
                    </div>

                    <div style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                        <?php if ($has_active_manuscript): ?>
                            <button type="button" onclick="document.getElementById('uploadManuscriptFormSection').style.display='none'" class="btn" style="padding: 0.6rem 1rem; background: white; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 0.85rem; cursor: pointer;">Cancel</button>
                        <?php endif; ?>
                        <button type="submit" class="btn-primary" style="padding: 0.65rem 1.4rem; background: var(--csu-green); font-size: 0.88rem; font-weight: 700; border-radius: 8px; cursor: pointer;">
                            <i class="fa-solid fa-cloud-arrow-up"></i> Submit Manuscript to Panel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Official Defense Minutes of the Meeting (MOM) -->
    <div class="card" id="studentMomCard">
        <div class="card-header">
            <div class="card-header-left">
                <i class="fa-solid fa-file-signature"></i>
                <h2>Oral Defense Minutes of the Meeting (MOM)</h2>
            </div>
            <?php if($defense_mom): ?>
            <button type="button" class="btn btn-outline" onclick="window.print()" style="font-size: 0.78rem; padding: 0.3rem 0.75rem;">
                <i class="fa-solid fa-print"></i> Print Official Document
            </button>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if(!$defense_mom && !$defense_meeting): ?>
                <div style="text-align: center; color: var(--text-muted); padding: 2.5rem 1rem;">
                    <i class="fa-solid fa-folder-open fa-3x" style="margin-bottom: 0.75rem; color: #CBD5E1; display: block;"></i>
                    <p style="color: #64748B; font-size: 0.9rem; margin: 0;">Defense meeting minutes will appear here once your committee convenes for your oral defense session.</p>
                </div>
            <?php else: ?>
                
                <div class="csu-mom-letterhead" style="margin-bottom: 1.25rem;">
                    <div style="display: flex; align-items: center; justify-content: center; gap: 1rem; margin-bottom: 0.4rem;">
                        <img src="logo.png" alt="CSU Seal" style="width: 50px; height: auto;">
                        <div>
                            <div class="univ-rep">Republic of the Philippines</div>
                            <div class="univ-title">CARAGA STATE UNIVERSITY CABADBARAN CAMPUS</div>
                            <div class="college-title">College of Engineering and Information Technology</div>
                        </div>
                    </div>
                    <div class="doc-heading" style="font-size: 0.9rem;">
                        OFFICIAL MINUTES OF THE ORAL DEFENSE PROCEEDINGS
                    </div>
                </div>

                <!-- Session Metadata Grid -->
                <div class="candidate-strip" style="background: #F8FAFC; border: 1px solid #E2E8F0; margin-bottom: 1.25rem;">
                    <div class="strip-item">
                        <div class="label" style="color: #64748B;">Defense Topic</div>
                        <div class="value" style="color: #0F172A;"><?php echo htmlspecialchars($defense_mom['document_title'] ?? $defense_meeting['title']); ?></div>
                    </div>
                    <div class="strip-item">
                        <div class="label" style="color: #64748B;">Defense Stage</div>
                        <div class="value" style="color: #047857;"><?php echo htmlspecialchars($defense_meeting['defense_stage'] ?? 'Final Oral Defense'); ?></div>
                    </div>
                    <div class="strip-item">
                        <div class="label" style="color: #64748B;">Session Date</div>
                        <div class="value" style="color: #0F172A;"><?php echo date("F d, Y", strtotime($defense_meeting['meeting_date'] ?? date('Y-m-d'))); ?></div>
                    </div>
                    <div class="strip-item">
                        <div class="label" style="color: #64748B;">Committee Verdict</div>
                        <div class="value" style="color: #059669; font-weight: 700;">
                            <?php echo htmlspecialchars($defense_mom['decision_verdict'] ?? 'Deliberation Pending'); ?>
                        </div>
                    </div>
                </div>

                <!-- Section 1: Important Discussions -->
                <?php if(!empty($defense_mom['discussion_summary'])): ?>
                <div class="mom-section-box" style="margin-bottom: 1rem;">
                    <div class="mom-section-header">
                        <h3><i class="fa-solid fa-list-check"></i> I. Presentation Highlights & Deliberations</h3>
                    </div>
                    <div style="font-size: 0.9rem; color: #1E293B; line-height: 1.6; white-space: pre-wrap;">
                        <?php echo nl2br(htmlspecialchars($defense_mom['discussion_summary'])); ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Section 2: Committee Recommendations & Required Fixes -->
                <?php if(!empty($defense_mom['recommendations'])): ?>
                <div class="mom-section-box" style="margin-bottom: 1rem;">
                    <div class="mom-section-header">
                        <h3><i class="fa-solid fa-list-ol"></i> II. Committee Recommendations & Required Revisions</h3>
                    </div>
                    <div style="font-size: 0.9rem; color: #1E293B; line-height: 1.6; white-space: pre-wrap; background: #FFFBEB; border: 1px solid #FEF3C7; padding: 1rem; border-radius: 8px;">
                        <?php echo nl2br(htmlspecialchars($defense_mom['recommendations'])); ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Section 3: Action Items & Compliance Table -->
                <?php 
                $ai_items = [];
                if (!empty($defense_mom['action_items'])) {
                    $ai_items = json_decode($defense_mom['action_items'], true);
                }
                if (!empty($ai_items) && is_array($ai_items)):
                ?>
                <div class="mom-section-box" style="margin-bottom: 1rem;">
                    <div class="mom-section-header">
                        <h3><i class="fa-solid fa-tasks"></i> III. Action Items & Revision Target Deadlines</h3>
                    </div>
                    <table class="action-items-table">
                        <thead>
                            <tr>
                                <th>Action Item / Required Fix</th>
                                <th>Responsible</th>
                                <th>Compliance Deadline</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($ai_items as $item): ?>
                            <tr>
                                <td style="font-weight: 600; color: #1E293B;"><?php echo htmlspecialchars($item['task'] ?? ''); ?></td>
                                <td style="color: #64748B;"><?php echo htmlspecialchars($item['assigned'] ?? 'Team'); ?></td>
                                <td>
                                    <span style="background: #F1F5F9; color: #0F172A; padding: 0.2rem 0.55rem; border-radius: 4px; font-weight: 600; font-size: 0.78rem;">
                                        <i class="fa-regular fa-calendar"></i> <?php echo htmlspecialchars($item['deadline'] ?? ''); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <!-- Committee Sign-off certification -->
                <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #E2E8F0; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
                    <div>
                        <div style="font-size: 0.72rem; color: #64748B; text-transform: uppercase;">Recorded By</div>
                        <div style="font-weight: 700; color: #0F172A;"><?php echo htmlspecialchars($defense_mom['recorded_by_name'] ?? 'Committee Documenter'); ?></div>
                    </div>
                    <div>
                        <div style="font-size: 0.72rem; color: #64748B; text-transform: uppercase;">Certified / Approved By</div>
                        <div style="font-weight: 700; color: #0F172A;"><?php echo htmlspecialchars($defense_mom['approved_by_chair'] ?? 'Panel Committee Chair'); ?></div>
                    </div>
                </div>

                <!-- Speech Transcripts Accordion Toggle -->
                <?php if(!empty($defense_transcripts)): ?>
                <details style="margin-top: 1.25rem; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 0.75rem 1rem;" class="no-print">
                    <summary style="font-weight: 700; color: #047857; cursor: pointer;">
                        <i class="fa-solid fa-microphone-lines"></i> View Transcribed Oral Defense Spoken Records (<?php echo count($defense_transcripts); ?> entries)
                    </summary>
                    <div style="margin-top: 0.75rem; display: flex; flex-direction: column; gap: 0.5rem; max-height: 350px; overflow-y: auto;">
                        <?php foreach($defense_transcripts as $dt): ?>
                        <div style="background: white; border: 1px solid #E2E8F0; border-radius: 6px; padding: 0.5rem 0.75rem; font-size: 0.82rem;">
                            <div style="display: flex; justify-content: space-between; color: #64748B; margin-bottom: 0.2rem; font-size: 0.75rem;">
                                <strong><?php echo htmlspecialchars($dt['speaker_name']); ?> (<?php echo htmlspecialchars($dt['speaker_role']); ?>)</strong>
                                <span><?php echo htmlspecialchars($dt['timestamp_marker']); ?></span>
                            </div>
                            <div style="color: #1E293B;"><?php echo htmlspecialchars($dt['transcript_text']); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </details>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>

    <!-- Panel Comments & Research Feedback List -->
    <div class="card">
        <div class="card-header">
            <div class="card-header-left">
                <i class="fa-solid fa-comments"></i>
                <h2>Panel Comments, Suggestions & Feedback</h2>
            </div>
            <span class="badge badge-info"><?php echo count($all_comments); ?> Total Comments</span>
        </div>
        <div class="card-body">
            <?php if(empty($all_comments)): ?>
                <div style="text-align: center; color: var(--text-muted); padding: 2.5rem 1rem;">
                    <i class="fa-solid fa-comment-dots fa-3x" style="margin-bottom: 0.75rem; color: #CBD5E1; display: block;"></i>
                    <p style="color: #64748B; font-size: 0.9rem; margin: 0;">No comments have been posted by your panel members yet.</p>
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <?php foreach($all_comments as $c): 
                        $tag_class = 'suggestion';
                        if ($c['feedback_type'] === 'Required Revision') $tag_class = 'revision';
                        if ($c['feedback_type'] === 'Commendation') $tag_class = 'commendation';
                        if ($c['feedback_type'] === 'Clarification' || $c['feedback_type'] === 'Question') $tag_class = 'clarification';
                    ?>
                    <div class="comment-card <?php echo $c['feedback_type'] === 'Required Revision' ? 'revision-needed' : ''; ?> <?php echo $c['status'] === 'resolved' ? 'resolved' : ''; ?>">
                        
                        <div class="comment-card-top">
                            <div class="comment-author-info">
                                <div class="comment-avatar">
                                    <?php echo strtoupper(substr($c['author_name'], 0, 1)); ?>
                                </div>
                                <div>
                                    <div style="font-weight: 700; color: #0F172A; font-size: 0.92rem;">
                                        <?php echo htmlspecialchars($c['author_name']); ?>
                                        <span style="font-size: 0.72rem; color: #047857; font-weight: 600; margin-left: 0.3rem;">• Committee Evaluator</span>
                                    </div>
                                    <div style="font-size: 0.75rem; color: #94A3B8;">
                                        <i class="fa-regular fa-clock"></i> <?php echo date("M d, Y h:i A", strtotime($c['created_at'])); ?>
                                    </div>
                                </div>
                            </div>

                            <div style="display: flex; gap: 0.4rem; align-items: center; flex-wrap: wrap;">
                                <?php if(!empty($c['audio_file'])): ?>
                                    <span style="font-size: 0.72rem; padding: 0.2rem 0.6rem; border-radius: 999px; background: #FEF2F2; color: #DC2626; font-weight: 800; border: 1px solid #FECACA;">
                                        <i class="fa-solid fa-microphone-lines"></i> Voice Recorded
                                    </span>
                                <?php endif; ?>
                                <span class="feedback-tag <?php echo $tag_class; ?>">
                                    <?php echo htmlspecialchars($c['feedback_type']); ?>
                                </span>
                                <span style="font-size: 0.75rem; padding: 0.2rem 0.6rem; border-radius: 999px; background: #F1F5F9; color: #475569; font-weight: 600;">
                                    <?php echo htmlspecialchars($c['category']); ?>
                                </span>
                                <?php if($c['status'] === 'resolved'): ?>
                                    <span class="badge badge-success" style="font-size: 0.7rem;"><i class="fa-solid fa-check-double"></i> Resolved</span>
                                <?php elseif($c['status'] === 'addressed'): ?>
                                    <span class="badge badge-info" style="font-size: 0.7rem;"><i class="fa-solid fa-clock-rotate-left"></i> Addressed</span>
                                <?php else: ?>
                                    <span class="badge badge-pending" style="font-size: 0.7rem;"><i class="fa-regular fa-circle"></i> Open</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if(!empty($c['audio_file'])): ?>
                        <div style="background: #F0FDF4; border: 1px solid #A7F3D0; border-radius: 8px; padding: 0.65rem 0.85rem; margin: 0.35rem 0;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem; flex-wrap: wrap; gap: 0.4rem;">
                                <span style="font-size: 0.78rem; font-weight: 800; color: #065F46;">
                                    <i class="fa-solid fa-volume-high" style="color: #059669;"></i> Recorded Voice Comment from <?php echo htmlspecialchars($c['author_name']); ?>
                                    <?php if(!empty($c['audio_duration'])): ?>
                                        <span style="font-weight: 600; color: #047857;">(<?php echo gmdate("i:s", (int)$c['audio_duration']); ?>)</span>
                                    <?php endif; ?>
                                </span>
                                <a href="<?php echo htmlspecialchars($c['audio_file']); ?>" download style="font-size: 0.74rem; color: #047857; font-weight: 700; text-decoration: none;">
                                    <i class="fa-solid fa-download"></i> Download Audio
                                </a>
                            </div>
                            <audio controls preload="metadata" src="<?php echo htmlspecialchars($c['audio_file']); ?>" style="width: 100%; height: 36px;"></audio>
                        </div>
                        <?php endif; ?>

                        <?php if(!empty($c['comment_text']) && $c['comment_text'] !== '[Voice Recorded Comment]'): ?>
                        <div style="font-size: 0.92rem; color: #1E293B; line-height: 1.55; white-space: pre-wrap; padding: 0.35rem 0;">
                            <?php echo nl2br(htmlspecialchars($c['comment_text'])); ?>
                        </div>
                        <?php endif; ?>

                        <?php if(!empty($c['adviser_notes'])): ?>
                        <div style="background: #FFFBEB; border-left: 3px solid #D97706; padding: 0.65rem 0.85rem; border-radius: 6px; font-size: 0.82rem; margin-top: 0.35rem;">
                            <strong style="color: #92400E;"><i class="fa-solid fa-chalkboard-user"></i> Adviser Guidance Note:</strong>
                            <div style="color: #78350F; margin-top: 0.2rem;"><?php echo nl2br(htmlspecialchars($c['adviser_notes'])); ?></div>
                        </div>
                        <?php endif; ?>

                        <?php if(!empty($c['student_reply'])): ?>
                        <div style="background: #F8FAFC; border-left: 3px solid #047857; padding: 0.65rem 0.85rem; border-radius: 6px; font-size: 0.82rem; margin-top: 0.35rem;">
                            <strong style="color: #064E3B;"><i class="fa-solid fa-reply"></i> Your Group's Response:</strong>
                            <div style="color: #334155; margin-top: 0.2rem;"><?php echo nl2br(htmlspecialchars($c['student_reply'])); ?></div>
                        </div>
                        <?php endif; ?>

                        <!-- Student Reply Form / Mark as Addressed -->
                        <div style="border-top: 1px solid #F1F5F9; padding-top: 0.5rem; margin-top: 0.25rem;">
                            <details style="font-size: 0.8rem;">
                                <summary style="color: #047857; font-weight: 600; cursor: pointer;">
                                    <i class="fa-solid fa-reply"></i> <?php echo empty($c['student_reply']) ? 'Reply / Mark as Addressed' : 'Update Response'; ?>
                                </summary>
                                <div style="margin-top: 0.5rem; display: flex; gap: 0.5rem; flex-direction: column;">
                                    <textarea id="replyText-<?php echo $c['id']; ?>" class="mom-textarea" style="min-height: 60px; font-size: 0.82rem;" placeholder="Explain how your group addressed or implemented this revision in the manuscript..."><?php echo htmlspecialchars($c['student_reply'] ?? ''); ?></textarea>
                                    <div style="display: flex; justify-content: flex-end; gap: 0.4rem;">
                                        <button type="button" class="btn-primary" onclick="submitStudentReply(<?php echo $c['id']; ?>)" style="padding: 0.3rem 0.75rem; font-size: 0.75rem;">
                                            <i class="fa-solid fa-paper-plane"></i> Submit Response
                                        </button>
                                    </div>
                                </div>
                            </details>
                        </div>

                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<script>
function submitStudentReply(commentId) {
    const text = document.getElementById('replyText-' + commentId).value.trim();
    if (!text) {
        alert("Please enter your response before submitting.");
        return;
    }

    const formData = new FormData();
    formData.append('api_action', 'update_comment_status');
    formData.append('comment_id', commentId);
    formData.append('status', 'addressed');
    formData.append('student_reply', text);

    fetch('api.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            alert("Response recorded! Marked as addressed.");
            window.location.reload();
        } else {
            alert(data.error || "Failed to update response.");
        }
    })
    .catch(err => console.error(err));
}
</script>
