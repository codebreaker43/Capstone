<div class="card">
    <div class="card-header">
        <i class="fa-solid fa-file-invoice"></i>
        <h2>Create Paper (Manuscript Draft)</h2>
    </div>
    <div class="card-body">
        <?php if(!empty($profile['paper_feedback'])): ?>
            <div style="background: <?php echo ($profile['paper_status'] == 'approved') ? '#D1FAE5' : '#FEF3C7'; ?>; border: 1px solid <?php echo ($profile['paper_status'] == 'approved') ? '#A7F3D0' : '#FDE68A'; ?>; border-radius: 8px; padding: 1.5rem; margin-bottom: 2rem;">
                <h3 style="margin-bottom: 0.5rem; color: <?php echo ($profile['paper_status'] == 'approved') ? '#047857' : '#B45309'; ?>;">
                    <?php if($profile['paper_status'] == 'approved'): ?>
                        <i class="fa-solid fa-check-circle"></i> Adviser Feedback: Approved
                    <?php else: ?>
                        <i class="fa-solid fa-rotate-left"></i> Adviser Feedback: Needs Revision
                    <?php endif; ?>
                </h3>
                <p style="color: #334155; line-height: 1.6; margin-top: 1rem;">
                    <strong>Comments:</strong><br>
                    <?php echo nl2br(htmlspecialchars($profile['paper_feedback'])); ?>
                </p>
            </div>
        <?php endif; ?>
        
        <?php if(!empty($profile['manuscript_file'])): ?>
            <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h4 style="margin: 0 0 0.25rem 0; color: #1E293B;"><i class="fa-solid fa-file-pdf" style="color: var(--primary-blue);"></i> Attached Manuscript</h4>
                    <p style="margin: 0; color: #64748B; font-size: 0.9rem;">You have already uploaded a manuscript file.</p>
                </div>
                <a href="<?php echo htmlspecialchars($profile['manuscript_file']); ?>" target="_blank" class="btn-primary" style="text-decoration: none; padding: 0.5rem 1rem; font-size: 0.9rem;"><i class="fa-solid fa-download"></i> Download</a>
            </div>
        <?php endif; ?>

        <form method="POST" action="action.php" enctype="multipart/form-data">
            <input type="hidden" name="create_paper" value="1">
            <div class="form-group">
                <label>Draft Title</label>
                <div style="display: flex; gap: 10px;">
                    <input type="text" name="paper_title" id="paper_title" placeholder="Enter manuscript title" required style="flex: 1;">
                    <button type="button" class="btn-primary btn-warning" onclick="generateAIPaper()" style="white-space: nowrap;"><i class="fa-solid fa-wand-magic-sparkles"></i> Auto-Generate Draft</button>
                </div>
            </div>
            <div class="form-group">
                <label>Attach Manuscript File (PDF, DOC, DOCX) - Optional</label>
                <input type="file" name="manuscript_file" accept=".pdf,.doc,.docx" style="display: block; width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; font-family: 'Inter', sans-serif;">
            </div>
            <div class="form-group">
                <label>Content / Abstract Update</label>
                <textarea name="paper_content" id="paper_content" rows="12" placeholder="Write your initial draft or update here..." required>
Chapter 1: Introduction
[Write your introduction here...]

Chapter 2: Review of Related Literature
[Write your literature review here...]

Chapter 3: Methodology
[Write your methodology here...]

Chapter 4: Results and Discussion
[Write your results here...]

Chapter 5: Conclusion and Recommendations
[Write your conclusion here...]
</textarea>
            </div>
            <button type="submit" class="btn-primary"><i class="fa-solid fa-upload"></i> Submit Draft</button>
        </form>
    </div>
</div>
