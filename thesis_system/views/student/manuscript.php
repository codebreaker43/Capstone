<?php
// Student & Group Manuscript Submission Portal (Chapters 1 to References & Appendices)
$curr_m_file = !empty($profile['manuscript_file']) ? $profile['manuscript_file'] : (!empty($my_group['manuscript_file']) ? $my_group['manuscript_file'] : '');
$curr_m_link = !empty($profile['shareable_link']) ? $profile['shareable_link'] : (!empty($my_group['shareable_link']) ? $my_group['shareable_link'] : '');
$curr_m_name = !empty($profile['manuscript_name']) ? $profile['manuscript_name'] : (!empty($my_group['manuscript_name']) ? $my_group['manuscript_name'] : basename($curr_m_file));
$curr_m_stage = !empty($profile['manuscript_stage']) ? $profile['manuscript_stage'] : (!empty($my_group['manuscript_stage']) ? $my_group['manuscript_stage'] : 'Proposal Defense');
$curr_m_updated = !empty($profile['manuscript_updated_at']) ? $profile['manuscript_updated_at'] : (!empty($my_group['manuscript_updated_at']) ? $my_group['manuscript_updated_at'] : '');
$curr_m_notes = !empty($profile['manuscript_notes']) ? $profile['manuscript_notes'] : (!empty($my_group['manuscript_notes']) ? $my_group['manuscript_notes'] : '');
$has_active_manuscript = (!empty($curr_m_file) || !empty($curr_m_link));

// Team details
$is_leader = (!empty($my_group) && (int)($my_group['leader_id'] ?? 0) === (int)$uid);
$group_name_disp = !empty($my_group['group_name']) ? "Group {$my_group['group_name']}" : "Individual Research";
?>

<div style="max-width: 1200px; margin: 0 auto; padding-bottom: 3rem;">
    <!-- Institutional Header Banner -->
    <div style="background: linear-gradient(135deg, #064E3B 0%, #047857 100%); color: white; padding: 2rem 2.25rem; border-radius: 16px; margin-bottom: 1.5rem; box-shadow: 0 10px 25px -5px rgba(6, 78, 59, 0.25); position: relative; overflow: hidden;">
        <div style="position: absolute; right: -25px; bottom: -35px; opacity: 0.12; font-size: 11rem; pointer-events: none;">
            <i class="fa-solid fa-book-open"></i>
        </div>
        <div style="display: flex; align-items: center; gap: 0.65rem; margin-bottom: 0.5rem;">
            <span style="background: rgba(255,255,255,0.2); padding: 3px 10px; border-radius: 999px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;">
                <i class="fa-solid fa-graduation-cap"></i> Oral Defense Manuscript Submission
            </span>
            <span style="background: #FCD34D; color: #064E3B; padding: 3px 10px; border-radius: 999px; font-size: 0.75rem; font-weight: 800;">
                Chapters 1 to References
            </span>
        </div>
        <h1 style="margin: 0 0 0.5rem 0; font-size: 1.6rem; font-weight: 900; letter-spacing: -0.5px;">
            Submit Capstone Manuscript &amp; Papers for Panel Review
        </h1>
        <p style="margin: 0; font-size: 0.9rem; color: #A7F3D0; max-width: 780px; line-height: 1.55;">
            Upload your complete capstone manuscript (PDF/DOCX) or provide a live Google Docs link. Your assigned Panel Committee evaluators will inspect these documents, verify your Chapter 1 to References rigor, and grade your oral presentation using the official CEIT-T07A rubric.
        </p>
    </div>

    <!-- Active Manuscript Status Banner -->
    <?php if ($has_active_manuscript): ?>
        <div class="card" style="border: 1.5px solid #86EFAC; background: #F0FDF4; border-radius: 14px; margin-bottom: 1.75rem; box-shadow: 0 4px 15px rgba(5, 150, 105, 0.08);">
            <div class="card-body" style="padding: 1.5rem 1.75rem;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1.25rem;">
                    <div>
                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.4rem;">
                            <span class="badge" style="background: #059669; color: white; padding: 4px 10px; font-size: 0.76rem; font-weight: 800; border-radius: 6px;">
                                <i class="fa-solid fa-circle-check"></i> ACTIVE ON FILE FOR PANEL
                            </span>
                            <span class="badge" style="background: #DCFCE7; color: #166534; border: 1px solid #86EFAC; font-weight: 700; font-size: 0.76rem; border-radius: 6px;">
                                <?php echo htmlspecialchars($curr_m_stage); ?>
                            </span>
                            <span style="font-size: 0.78rem; color: #475569; font-weight: 600;">
                                <i class="fa-solid fa-users"></i> <?php echo htmlspecialchars($group_name_disp); ?>
                            </span>
                        </div>

                        <h3 style="margin: 0 0 0.5rem 0; font-size: 1.2rem; font-weight: 800; color: #064E3B; line-height: 1.4;">
                            <?php echo htmlspecialchars($profile['thesis_topic'] ?: 'Capstone Project Manuscript'); ?>
                        </h3>

                        <div style="display: flex; gap: 1.25rem; flex-wrap: wrap; font-size: 0.82rem; color: #334155; margin-top: 0.5rem;">
                            <span><i class="fa-regular fa-file" style="color: #059669;"></i> <strong>File Name:</strong> <?php echo htmlspecialchars($curr_m_name); ?></span>
                            <?php if (!empty($curr_m_updated)): ?>
                                <span><i class="fa-regular fa-clock" style="color: #059669;"></i> <strong>Last Updated:</strong> <?php echo date('M d, Y — h:i A', strtotime($curr_m_updated)); ?></span>
                            <?php endif; ?>
                            <span><i class="fa-solid fa-shield-halved" style="color: #059669;"></i> <strong>Panel Access:</strong> Live &amp; Synchronized</span>
                        </div>

                        <?php if (!empty($curr_m_notes)): ?>
                            <div style="margin-top: 0.75rem; background: #FFFFFF; border: 1px solid #BBF7D0; border-radius: 8px; padding: 0.65rem 0.9rem; font-size: 0.82rem; color: #1E293B;">
                                <strong style="color: #065F46;">Submission Notes / Scope Overview:</strong> <?php echo htmlspecialchars($curr_m_notes); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Direct Action Buttons -->
                    <div style="display: flex; flex-direction: column; gap: 0.5rem; min-width: 220px;">
                        <?php if (!empty($curr_m_file)): ?>
                            <a href="<?php echo htmlspecialchars($curr_m_file); ?>" target="_blank" class="btn-primary" style="background: #DC2626; border-color: #DC2626; text-decoration: none; padding: 0.65rem 1.15rem; font-size: 0.85rem; font-weight: 800; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; box-shadow: 0 4px 12px rgba(220, 38, 38, 0.25);">
                                <i class="fa-solid fa-file-pdf"></i> Read / Open PDF Manuscript
                            </a>
                        <?php endif; ?>

                        <?php if (!empty($curr_m_link)): ?>
                            <a href="<?php echo htmlspecialchars($curr_m_link); ?>" target="_blank" rel="noopener noreferrer" class="btn-primary" style="background: #2563EB; border-color: #2563EB; text-decoration: none; padding: 0.65rem 1.15rem; font-size: 0.85rem; font-weight: 800; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);">
                                <i class="fa-solid fa-arrow-up-right-from-square"></i> Open Live Google Docs
                            </a>
                        <?php endif; ?>

                        <button type="button" onclick="document.getElementById('manuscriptFormCard').scrollIntoView({behavior:'smooth'});" class="btn btn-outline" style="background: #FFFFFF; border: 1.5px solid #CBD5E1; color: #334155; font-size: 0.82rem; font-weight: 700; padding: 0.55rem; border-radius: 8px; cursor: pointer;">
                            <i class="fa-solid fa-file-arrow-up"></i> Upload New Version / Re-submit
                        </button>
                    </div>
                </div>

                <!-- Embedded PDF Preview Accordion if PDF on file -->
                <?php if (!empty($curr_m_file) && strtolower(pathinfo($curr_m_file, PATHINFO_EXTENSION)) === 'pdf'): ?>
                    <div style="margin-top: 1.25rem; border-top: 1px dashed #A7F3D0; padding-top: 1rem;">
                        <button type="button" onclick="const p = document.getElementById('embeddedPdfWrap'); p.style.display = (p.style.display === 'none' ? 'block' : 'none');" style="background: transparent; border: none; color: #047857; font-weight: 800; font-size: 0.85rem; cursor: pointer; display: inline-flex; align-items: center; gap: 0.4rem;">
                            <i class="fa-solid fa-eye"></i> Toggle In-Portal Manuscript Preview (Read Chapters 1 to References)
                        </button>
                        <div id="embeddedPdfWrap" style="display: none; margin-top: 0.85rem; border-radius: 10px; overflow: hidden; border: 1.5px solid #CBD5E1;">
                            <iframe src="<?php echo htmlspecialchars($curr_m_file); ?>" style="width: 100%; height: 600px; border: none;" title="Manuscript PDF Viewer"></iframe>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div style="background: #FFFBEB; border: 1.5px solid #FCD34D; border-radius: 12px; padding: 1.25rem 1.5rem; margin-bottom: 1.75rem; display: flex; align-items: center; gap: 1rem;">
            <div style="width: 44px; height: 44px; border-radius: 50%; background: #FEF3C7; color: #D97706; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0;">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <div>
                <h4 style="margin: 0 0 0.2rem 0; font-size: 1rem; font-weight: 800; color: #92400E;">No Manuscript Submitted Yet</h4>
                <p style="margin: 0; font-size: 0.84rem; color: #B45309;">
                    Your panel evaluators cannot inspect your proposal or defense papers until you submit the manuscript below. Upload your Chapters 1 to References file so the committee can prepare for your presentation.
                </p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Submission Form Card -->
    <div class="card" id="manuscriptFormCard" style="border-radius: 14px; overflow: hidden; border: 1px solid #E2E8F0; box-shadow: 0 4px 20px rgba(0,0,0,0.05);">
        <div class="card-header" style="background: #022C22; color: #FFFFFF; padding: 1.15rem 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
            <div>
                <h2 style="margin: 0; font-size: 1.15rem; color: #FFFFFF; font-weight: 800; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-solid fa-cloud-arrow-up" style="color: #FCD34D;"></i>
                    <?php echo $has_active_manuscript ? 'Submit Updated Version of Manuscript' : 'Upload & Submit Capstone Manuscript'; ?>
                </h2>
                <small style="color: #A7F3D0; font-size: 0.76rem;">
                    Supported formats: PDF (preferred), Word (.docx, .doc) up to 50MB, or Google Docs shareable link.
                </small>
            </div>
            <span style="background: rgba(255,255,255,0.15); color: #FFFFFF; padding: 3px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700;">
                Official Panel Repository
            </span>
        </div>

        <div class="card-body" style="padding: 1.75rem;">
            <form method="POST" action="action.php" enctype="multipart/form-data" id="manuscriptSubmitForm">
                <input type="hidden" name="submit_panel_manuscript" value="1">
                <input type="hidden" name="redirect_tab" value="manuscript">

                <!-- 1. Capstone Title & Stage -->
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.25rem; margin-bottom: 1.25rem;">
                    <div>
                        <label style="font-weight: 800; color: #0F172A; font-size: 0.85rem; display: block; margin-bottom: 0.4rem;">
                            Approved Capstone Research Title <span style="color:#DC2626;">*</span>
                        </label>
                        <input type="text" name="thesis_topic" value="<?php echo htmlspecialchars($profile['thesis_topic'] ?? ''); ?>" required placeholder="e.g. IoT and AI-Driven Smart Agriculture Monitoring and Solar Optimization" class="form-control" style="width: 100%; padding: 0.65rem 0.85rem; border: 1.5px solid #CBD5E1; border-radius: 8px; font-size: 0.9rem; font-family: inherit;">
                    </div>

                    <div>
                        <label style="font-weight: 800; color: #0F172A; font-size: 0.85rem; display: block; margin-bottom: 0.4rem;">
                            Oral Defense Stage <span style="color:#DC2626;">*</span>
                        </label>
                        <select name="manuscript_stage" class="form-control" required style="width: 100%; padding: 0.65rem 0.85rem; border: 1.5px solid #CBD5E1; border-radius: 8px; font-size: 0.9rem; font-family: inherit; font-weight: 700; color: #064E3B;">
                            <option value="Proposal Defense" <?php echo ($curr_m_stage === 'Proposal Defense') ? 'selected' : ''; ?>>
                                📑 Proposal Hearing (Chapters 1 to 3 &amp; References)
                            </option>
                            <option value="Final Oral Defense" <?php echo ($curr_m_stage === 'Final Oral Defense') ? 'selected' : ''; ?>>
                                🎓 Final Oral Defense (Chapters 1 to 5, References &amp; Appendices)
                            </option>
                            <option value="Title Proposal" <?php echo ($curr_m_stage === 'Title Proposal') ? 'selected' : ''; ?>>
                                💡 Title / Concept Paper Proposal
                            </option>
                            <option value="Manuscript Re-defense / Compliance" <?php echo ($curr_m_stage === 'Manuscript Re-defense / Compliance') ? 'selected' : ''; ?>>
                                🔄 Revised Manuscript (Post-Defense Panel Compliance)
                            </option>
                        </select>
                    </div>
                </div>

                <!-- 2. Dual Submission: File Upload & Live Cloud Link -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1.5rem;">
                    <!-- Option A: File Upload -->
                    <div style="background: #F8FAFC; border: 1.5px dashed #059669; border-radius: 10px; padding: 1.25rem;">
                        <label style="font-weight: 800; color: #064E3B; font-size: 0.88rem; display: flex; align-items: center; gap: 0.45rem; margin-bottom: 0.4rem;">
                            <i class="fa-solid fa-file-arrow-up" style="color: #059669; font-size: 1.1rem;"></i>
                            Option A: Upload Manuscript File (PDF / DOCX)
                        </label>
                        <p style="margin: 0 0 0.85rem 0; font-size: 0.78rem; color: #64748B;">
                            Please compile <strong>Chapters 1 to References</strong> into a single organized document. PDF is strongly recommended.
                        </p>
                        <input type="file" name="manuscript_file" accept=".pdf,.doc,.docx" class="form-control" style="width: 100%; padding: 0.55rem; background: #FFFFFF; font-size: 0.85rem; border: 1px solid #CBD5E1; border-radius: 7px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.45rem; font-size: 0.74rem; color: #64748B;">
                            <span>Max upload size: 50MB</span>
                            <?php if(!empty($curr_m_file)): ?>
                                <span style="color: #047857; font-weight: 700;"><i class="fa-solid fa-check"></i> Current file: <?php echo htmlspecialchars($curr_m_name); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Option B: Live Cloud Document -->
                    <div style="background: #F8FAFC; border: 1.5px dashed #2563EB; border-radius: 10px; padding: 1.25rem;">
                        <label style="font-weight: 800; color: #1E3A8A; font-size: 0.88rem; display: flex; align-items: center; gap: 0.45rem; margin-bottom: 0.4rem;">
                            <i class="fa-solid fa-link" style="color: #2563EB; font-size: 1.1rem;"></i>
                            Option B: Live Cloud Link (Google Docs / OneDrive)
                        </label>
                        <p style="margin: 0 0 0.85rem 0; font-size: 0.78rem; color: #64748B;">
                            Paste your live Google Docs or Word Online link. Ensure share permissions are set to <strong>"Anyone with link can comment/view"</strong>.
                        </p>
                        <input type="url" name="shareable_link" value="<?php echo htmlspecialchars($curr_m_link); ?>" placeholder="https://docs.google.com/document/d/..." class="form-control" style="width: 100%; padding: 0.65rem 0.85rem; background: #FFFFFF; font-size: 0.85rem; border: 1px solid #CBD5E1; border-radius: 7px;">
                        <small style="color: #2563EB; font-size: 0.74rem; display: block; margin-top: 0.45rem;">
                            <i class="fa-solid fa-circle-info"></i> Allows panel members to leave inline comments and suggestions on your manuscript.
                        </small>
                    </div>
                </div>

                <!-- 3. Chapter Coverage Checklist -->
                <div style="background: #F1F5F9; border-radius: 10px; padding: 1rem 1.25rem; margin-bottom: 1.25rem;">
                    <div style="font-size: 0.82rem; font-weight: 800; color: #1E293B; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.4rem;">
                        <i class="fa-solid fa-list-check" style="color: #059669;"></i>
                        Chapters Included in this Manuscript Submission:
                    </div>
                    <div style="display: flex; gap: 0.65rem; flex-wrap: wrap; font-size: 0.78rem;">
                        <span style="background: white; border: 1px solid #CBD5E1; padding: 4px 10px; border-radius: 6px; font-weight: 700; color: #065F46;">
                            <i class="fa-solid fa-check" style="color: #10B981;"></i> Chapter 1: Problem Formulation &amp; Objectives
                        </span>
                        <span style="background: white; border: 1px solid #CBD5E1; padding: 4px 10px; border-radius: 6px; font-weight: 700; color: #065F46;">
                            <i class="fa-solid fa-check" style="color: #10B981;"></i> Chapter 2: Literature &amp; Theoretical Soundness
                        </span>
                        <span style="background: white; border: 1px solid #CBD5E1; padding: 4px 10px; border-radius: 6px; font-weight: 700; color: #065F46;">
                            <i class="fa-solid fa-check" style="color: #10B981;"></i> Chapter 3: Methodology &amp; System Architecture
                        </span>
                        <span style="background: white; border: 1px solid #CBD5E1; padding: 4px 10px; border-radius: 6px; font-weight: 700; color: #334155;">
                            Chapter 4: Results &amp; Discussion (Final Defense)
                        </span>
                        <span style="background: white; border: 1px solid #CBD5E1; padding: 4px 10px; border-radius: 6px; font-weight: 700; color: #334155;">
                            Chapter 5: Conclusion &amp; Recommendation
                        </span>
                        <span style="background: white; border: 1px solid #CBD5E1; padding: 4px 10px; border-radius: 6px; font-weight: 700; color: #065F46;">
                            <i class="fa-solid fa-check" style="color: #10B981;"></i> References (IEEE Citation Style)
                        </span>
                        <span style="background: white; border: 1px solid #CBD5E1; padding: 4px 10px; border-radius: 6px; font-weight: 700; color: #334155;">
                            Appendices &amp; CEIT Forms
                        </span>
                    </div>
                </div>

                <!-- 4. Notes / Abstract for Panel -->
                <div style="margin-bottom: 1.5rem;">
                    <label style="font-weight: 800; color: #0F172A; font-size: 0.85rem; display: block; margin-bottom: 0.4rem;">
                        Executive Summary &amp; Notes for Panel Committee (Optional)
                    </label>
                    <textarea name="manuscript_notes" rows="3" class="form-control" placeholder="Provide a brief overview of key methodologies, target beneficiaries, datasets, or specific chapters where you request focused panel feedback..." style="width: 100%; padding: 0.75rem; border: 1.5px solid #CBD5E1; border-radius: 8px; font-size: 0.85rem; font-family: inherit;"><?php echo htmlspecialchars($curr_m_notes); ?></textarea>
                </div>

                <!-- Submit Button -->
                <div style="display: flex; justify-content: flex-end; gap: 0.75rem; align-items: center;">
                    <button type="submit" class="btn-primary" style="background: linear-gradient(135deg, #059669 0%, #047857 100%); border: none; padding: 0.85rem 2rem; font-size: 0.95rem; font-weight: 800; border-radius: 10px; box-shadow: 0 4px 14px rgba(5, 150, 105, 0.3); display: inline-flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <i class="fa-solid fa-cloud-arrow-up"></i> Submit Complete Manuscript for Panel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

