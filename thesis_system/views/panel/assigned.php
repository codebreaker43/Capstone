<div class="banner">
    <h1><i class="fa-solid fa-file-pen"></i> Assigned Capstone Projects</h1>
    <p>Review candidate manuscripts and submit oral defense evaluations and grades.</p>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-header-left">
            <i class="fa-solid fa-users"></i>
            <h2>My Panel Assignments</h2>
        </div>
        <span class="badge badge-info"><?php echo $assigned_res->num_rows; ?> Candidates</span>
    </div>
    <div class="card-body">
        <?php 
        $assigned_res->data_seek(0);
        if($assigned_res->num_rows == 0): 
        ?>
            <div style="text-align: center; color: var(--text-muted); padding: 3rem 1rem;">
                <i class="fa-solid fa-user-clock fa-3x" style="color: #CBD5E1; margin-bottom: 1rem; display: block;"></i>
                <p style="color: #64748B; font-size: 0.9rem; margin: 0;">No student candidates have been assigned to your committee yet.</p>
            </div>
        <?php else: ?>
            <div style="display: grid; grid-template-columns: 1fr; gap: 1.15rem;">
                <?php while($row = $assigned_res->fetch_assoc()): ?>
                    <div style="background: white; border: 1px solid #E2E8F0; border-radius: 12px; padding: 1.25rem 1.5rem; box-shadow: var(--shadow-sm); display: flex; flex-direction: column; gap: 0.85rem; transition: box-shadow 0.2s ease;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid #F1F5F9; padding-bottom: 0.85rem; flex-wrap: wrap; gap: 0.75rem;">
                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--primary-blue); color: white; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1rem;">
                                    <?php echo strtoupper(substr($row['stu_name'], 0, 1)); ?>
                                </div>
                                <div>
                                    <h3 style="margin: 0 0 0.15rem 0; color: #0F172A; font-size: 1.05rem; font-weight: 700;">
                                        <?php echo htmlspecialchars($row['stu_name']); ?>
                                    </h3>
                                    <p style="margin: 0; color: #64748B; font-size: 0.82rem;">
                                        <i class="fa-solid fa-envelope" style="margin-right: 0.3rem;"></i><?php echo htmlspecialchars($row['email']); ?>
                                    </p>
                                </div>
                            </div>
                            <div>
                                <?php if($row['status'] == 'graded'): ?>
                                    <span class="badge badge-success"><i class="fa-solid fa-check"></i> Graded: <?php echo $row['grade']; ?>%</span>
                                <?php else: ?>
                                    <span class="badge badge-pending"><i class="fa-regular fa-clock"></i> Pending Evaluation</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div style="background: #F8FAFC; padding: 1rem; border-radius: 8px; border: 1px solid #E2E8F0;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem; flex-wrap: wrap; gap: 0.5rem;">
                                <div style="font-size: 0.75rem; color: #64748B; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">
                                    <i class="fa-solid fa-book" style="color: var(--primary-blue);"></i> Research Topic
                                </div>
                                <div style="display: flex; gap: 0.4rem; align-items: center;">
                                    <?php if(!empty($row['group_name'])): ?>
                                        <span class="badge" style="background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0; font-size: 0.72rem;">
                                            <i class="fa-solid fa-users"></i> <?php echo htmlspecialchars($row['group_name']); ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if(!empty($row['manuscript_stage'])): ?>
                                        <span class="badge" style="background: #EFF6FF; color: #1E40AF; border: 1px solid #BFDBFE; font-size: 0.72rem;">
                                            <i class="fa-solid fa-flag"></i> <?php echo htmlspecialchars($row['manuscript_stage']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div style="font-size: 0.95rem; color: #0F172A; font-weight: 700; line-height: 1.4;">
                                <?php echo htmlspecialchars($row['thesis_topic'] ?: 'Proposal not yet submitted'); ?>
                            </div>
                            <?php if(!empty($row['manuscript_updated_at'])): ?>
                                <div style="margin-top: 0.35rem; font-size: 0.75rem; color: #64748B;">
                                    <i class="fa-regular fa-clock"></i> Manuscript submitted: <?php echo date('M d, Y h:i A', strtotime($row['manuscript_updated_at'])); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center; justify-content: flex-end; padding-top: 0.35rem;">
                            <?php if(!empty($row['manuscript_file'])): ?>
                                <a href="<?php echo htmlspecialchars($row['manuscript_file']); ?>" target="_blank" class="btn" style="border-radius: 8px; font-size: 0.82rem; padding: 0.45rem 0.85rem; background: #DC2626; color: #FFFFFF; border: none; font-weight: 800; text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem; box-shadow: 0 2px 6px rgba(220, 38, 38, 0.25);">
                                    <i class="fa-solid fa-file-pdf"></i> Read Manuscript (Ch 1 - Refs)
                                </a>
                            <?php endif; ?>

                            <?php if(!empty($row['shareable_link'])): ?>
                                <a href="<?php echo htmlspecialchars($row['shareable_link']); ?>" target="_blank" rel="noopener noreferrer" class="btn" style="border-radius: 8px; font-size: 0.82rem; padding: 0.45rem 0.85rem; background: #EFF6FF; color: #2563EB; border: 1px solid #BFDBFE; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem;">
                                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Open Live Doc (Google Docs)
                                </a>
                            <?php endif; ?>

                            <?php if(empty($row['manuscript_file']) && empty($row['shareable_link'])): ?>
                                <span style="font-size: 0.78rem; color: #94A3B8; background: #F8FAFC; padding: 0.35rem 0.75rem; border-radius: 6px; border: 1px dashed #CBD5E1;">
                                    <i class="fa-solid fa-clock"></i> No manuscript uploaded yet
                                </span>
                            <?php endif; ?>

                            <a href="?tab=comments&student_id=<?php echo $row['student_id']; ?>" class="btn btn-outline" style="border-radius: 8px; font-size: 0.82rem; padding: 0.45rem 0.85rem; background: #F0FDF4; color: #166534; border-color: #BBF7D0;">
                                <i class="fa-solid fa-comments"></i> Comments Panel
                            </a>

                            <a href="?tab=meeting_doc&student_id=<?php echo $row['student_id']; ?>" class="btn btn-outline" style="border-radius: 8px; font-size: 0.82rem; padding: 0.45rem 0.85rem; background: #FEF3C7; color: #92400E; border-color: #FDE68A;">
                                <i class="fa-solid fa-microphone-lines"></i> Defense MOM & Voice
                            </a>
                            
                            <a href="?tab=grading&tp_id=<?php echo $row['id']; ?>" class="btn-primary" style="border-radius: 8px; font-size: 0.82rem; padding: 0.45rem 1.15rem; text-decoration: none;">
                                <i class="fa-solid fa-pen-to-square"></i> Evaluate Capstone
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
