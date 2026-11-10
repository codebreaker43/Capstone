<?php
// IT-199 (Capstone Project and Research) Requirements Checklist View
$is_in_group = !empty($my_group) && !empty($group_id);
$is_leader = $is_in_group && ((int)($my_group['leader_id'] ?? 0) === (int)$uid);

// Fetch assigned adviser
$adv_name = "Not yet assigned";
$adv_q = $conn->query("SELECT u.name FROM match_request m JOIN user u ON m.adviser_id = u.id WHERE " . ($group_id > 0 ? "m.group_id = $group_id" : "m.student_id = $uid") . " AND m.status = 'accepted' LIMIT 1");
if ($adv_q && $adv_q->num_rows > 0) {
    $adv_name = $adv_q->fetch_assoc()['name'];
}

// Fetch checklist items and compute stats
$checklist_items = get_student_checklist_items($conn, $uid, $group_id);
$stats = get_checklist_statistics($checklist_items);

// Standard filename required by IT-199
$group_name_label = !empty($my_group['group_name']) ? $my_group['group_name'] : "Group-1";
$standard_filename = generate_checklist_group_filename($group_name_label, $my_group_members, $adv_name);
?>

<!-- BANNER -->
<div class="banner" style="background: linear-gradient(135deg, #022C22 0%, #064E3B 55%, #047857 100%); border-radius: 12px; padding: 1.35rem 1.6rem; color: white; margin-bottom: 1.25rem; box-shadow: var(--shadow-sm); position: relative; overflow: hidden;">
    <div style="position: absolute; right: -15px; bottom: -20px; opacity: 0.07; font-size: 7rem; pointer-events: none;">
        <i class="fa-solid fa-list-check"></i>
    </div>
    
    <div style="position: relative; z-index: 1;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
            <div>
                <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 0.4rem;">
                    <span style="background: rgba(245, 158, 11, 0.25); color: #FCD34D; border: 1px solid rgba(245, 158, 11, 0.45); padding: 0.25rem 0.75rem; border-radius: 999px; font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;">
                        <i class="fa-solid fa-graduation-cap"></i> IT-199 Capstone Project & Research
                    </span>
                    <?php if($is_in_group): ?>
                        <span style="background: rgba(16, 185, 129, 0.25); color: #A7F3D0; border: 1px solid rgba(16, 185, 129, 0.45); padding: 0.25rem 0.75rem; border-radius: 999px; font-size: 0.72rem; font-weight: 700;">
                            <i class="fa-solid fa-users"></i> Synchronized for <?php echo htmlspecialchars($group_name_label); ?>
                        </span>
                    <?php endif; ?>
                </div>

                <h1 style="font-size: 1.5rem; font-weight: 800; margin: 0 0 0.35rem 0; color: #FFFFFF; font-family: 'Outfit', sans-serif;">
                    IT-199 Requirements Checklist
                </h1>
                
                <p style="margin: 0; color: #D1FAE5; font-size: 0.85rem; max-width: 820px; line-height: 1.45;">
                    Verify, upload, and link all required electronic evidence for final oral defense, panel revisions, and institutional clearance.
                </p>
            </div>

            <!-- Export Actions -->
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                <a href="export_checklist.php" class="btn-primary" style="background: #F59E0B; border-color: #D97706; color: #0F172A; font-weight: 700; padding: 0.55rem 0.95rem; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 2px 4px rgba(0,0,0,0.15);" title="Download official Microsoft Word checklist for your group folder">
                    <i class="fa-solid fa-file-word"></i> Download Word (.doc)
                </a>
                <button type="button" onclick="window.print()" class="btn-primary" style="background: rgba(255,255,255,0.18); border-color: rgba(255,255,255,0.35); color: #FFFFFF; font-weight: 600; padding: 0.55rem 0.85rem; display: inline-flex; align-items: center; gap: 0.4rem;">
                    <i class="fa-solid fa-print"></i> Print / PDF
                </button>
            </div>
        </div>
    </div>
</div>

<!-- STRICT ACADEMIC WARNING BANNER -->
<div style="background: #FFFBEB; border: 1.5px solid #FCD34D; border-left: 5px solid #F59E0B; border-radius: 10px; padding: 1rem 1.25rem; margin-bottom: 1.35rem; display: flex; align-items: flex-start; gap: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
    <div style="width: 36px; height: 36px; border-radius: 50%; background: #FEF3C7; color: #D97706; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0; margin-top: 2px;">
        <i class="fa-solid fa-triangle-exclamation"></i>
    </div>
    <div style="flex: 1;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
            <strong style="color: #92400E; font-size: 0.95rem; font-family: 'Outfit', sans-serif;">
                Instructions & Critical Clearance Policy:
            </strong>
            <span style="font-size: 0.75rem; background: #FEF2F2; color: #B91C1C; padding: 2px 8px; border-radius: 4px; font-weight: 800; border: 1px solid #FECACA;">
                STRICT COMPLIANCE
            </span>
        </div>
        <p style="margin: 0.35rem 0 0.5rem 0; color: #78350F; font-size: 0.84rem; line-height: 1.45;">
            Please upload your electronic copies of the required evidence in PDF format in the column labeled <strong>"Attached Link"</strong>. Make sure scanned documents are clear and readable. All pieces of evidence must also be properly stored and accessible in your designated group folder.<br>
            <strong>Warning:</strong> Missing or incomplete attachments will result in an <strong>"Incomplete" (INC)</strong> grade for the subject IT-199.
        </p>
        <div style="background: rgba(245, 158, 11, 0.12); border-radius: 6px; padding: 0.4rem 0.75rem; display: inline-flex; align-items: center; gap: 0.45rem; font-size: 0.8rem; color: #92400E;">
            <i class="fa-solid fa-folder-closed" style="color: #D97706;"></i>
            <span>Required Group Word Checklist Filename: <strong style="color: #0F172A; font-family: monospace; background: white; padding: 2px 6px; border-radius: 4px; border: 1px solid #FDE68A;"><?php echo htmlspecialchars($standard_filename); ?></strong></span>
        </div>
    </div>
</div>

<!-- PROGRESS SUMMARY METRICS -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.35rem;">
    <!-- Metric 1: Rate -->
    <div class="card" style="padding: 1rem 1.15rem; border-top: 4px solid var(--csu-green);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
            <span style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #64748B;">Compliance Rate</span>
            <span style="font-size: 1.15rem; font-weight: 800; color: var(--csu-green);"><?php echo $stats['percentage']; ?>%</span>
        </div>
        <div style="background: #E2E8F0; height: 8px; border-radius: 999px; overflow: hidden; margin-bottom: 0.5rem;">
            <div style="width: <?php echo $stats['percentage']; ?>%; background: linear-gradient(90deg, #059669, #10B981); height: 100%; border-radius: 999px; transition: width 0.5s ease;"></div>
        </div>
        <small style="color: #64748B; font-size: 0.75rem;">
            <?php echo $stats['completed']; ?> of <?php echo $stats['active_required']; ?> active requirements verified
        </small>
    </div>

    <!-- Metric 2: Completed -->
    <div class="card" style="padding: 1rem 1.15rem; border-top: 4px solid #10B981;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <span style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #64748B;">Completed (✔)</span>
                <div style="font-size: 1.45rem; font-weight: 800; color: #065F46; margin-top: 0.2rem;"><?php echo $stats['completed']; ?></div>
            </div>
            <div style="width: 38px; height: 38px; border-radius: 50%; background: #D1FAE5; color: #059669; display: flex; align-items: center; justify-content: center; font-size: 1.15rem;">
                <i class="fa-solid fa-circle-check"></i>
            </div>
        </div>
        <small style="color: #059669; font-size: 0.74rem; font-weight: 600; margin-top: 0.35rem; display: block;">
            All attachments linked
        </small>
    </div>

    <!-- Metric 3: Incomplete -->
    <div class="card" style="padding: 1rem 1.15rem; border-top: 4px solid #EF4444;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <span style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #64748B;">Pending / Incomplete (✘)</span>
                <div style="font-size: 1.45rem; font-weight: 800; color: #991B1B; margin-top: 0.2rem;"><?php echo $stats['incomplete']; ?></div>
            </div>
            <div style="width: 38px; height: 38px; border-radius: 50%; background: #FEE2E2; color: #DC2626; display: flex; align-items: center; justify-content: center; font-size: 1.15rem;">
                <i class="fa-solid fa-circle-xmark"></i>
            </div>
        </div>
        <small style="color: #B91C1C; font-size: 0.74rem; font-weight: 600; margin-top: 0.35rem; display: block;">
            <?php echo $stats['incomplete'] > 0 ? 'Requires action to prevent INC' : 'Zero incomplete items!'; ?>
        </small>
    </div>

    <!-- Metric 4: Folder Details -->
    <div class="card" style="padding: 1rem 1.15rem; border-top: 4px solid #3B82F6;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <span style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #64748B;">Group Team & Adviser</span>
                <div style="font-size: 0.92rem; font-weight: 800; color: #1E293B; margin-top: 0.2rem; text-overflow: ellipsis; overflow: hidden; white-space: nowrap; max-width: 170px;">
                    <?php echo htmlspecialchars($group_name_label); ?>
                </div>
            </div>
            <div style="width: 38px; height: 38px; border-radius: 50%; background: #DBEAFE; color: #2563EB; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
                <i class="fa-solid fa-user-tie"></i>
            </div>
        </div>
        <small style="color: #475569; font-size: 0.74rem; margin-top: 0.35rem; display: block; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;">
            Adviser: <strong><?php echo htmlspecialchars($adv_name); ?></strong>
        </small>
    </div>
</div>

<!-- REQUIREMENTS CHECKLIST TABLE CARD -->
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
        <div class="card-header-left" style="display: flex; align-items: center; gap: 0.6rem;">
            <i class="fa-solid fa-table-list" style="color: var(--csu-green); font-size: 1.2rem;"></i>
            <div>
                <h2 style="margin: 0; font-size: 1.05rem;">Official IT-199 Requirements Checklist</h2>
                <small style="color: #64748B; font-size: 0.75rem;">Click any requirement's link or action button to update evidence attachments.</small>
            </div>
        </div>

        <!-- Filter & Search Controls -->
        <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
            <div style="position: relative;">
                <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); font-size: 0.75rem; color: #94A3B8;"></i>
                <input type="text" id="checklistSearch" onkeyup="filterChecklistTable()" placeholder="Search requirements..." style="padding: 0.4rem 0.6rem 0.4rem 1.8rem; font-size: 0.8rem; border: 1px solid #CBD5E1; border-radius: 6px; width: 170px;">
            </div>

            <select id="checklistStatusFilter" onchange="filterChecklistTable()" style="padding: 0.4rem 0.65rem; font-size: 0.8rem; border: 1px solid #CBD5E1; border-radius: 6px; background: white; color: #334155;">
                <option value="all">All Statuses (<?php echo count($checklist_items); ?>)</option>
                <option value="incomplete">Incomplete Only (<?php echo $stats['incomplete']; ?>)</option>
                <option value="completed">Completed Only (<?php echo $stats['completed']; ?>)</option>
                <option value="not_applicable">N/A Items (<?php echo $stats['not_applicable']; ?>)</option>
            </select>
        </div>
    </div>

    <div class="card-body" style="padding: 0;">
        <div class="table-responsive" style="overflow-x: auto;">
            <table class="table" id="checklistTable" style="margin: 0; width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #F8FAFC; border-bottom: 2px solid #E2E8F0; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.5px; color: #475569;">
                        <th style="padding: 0.75rem 0.85rem; width: 55px; text-align: center;">No.</th>
                        <th style="padding: 0.75rem 0.9rem; width: 220px;">Requirement</th>
                        <th style="padding: 0.75rem 0.9rem;">Description / Evidence</th>
                        <th style="padding: 0.75rem 0.9rem; width: 260px;">Attached Link</th>
                        <th style="padding: 0.75rem 0.85rem; width: 110px; text-align: center;">Status</th>
                        <th style="padding: 0.75rem 0.85rem; width: 95px; text-align: center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($checklist_items as $item): ?>
                        <?php 
                            $stat = $item['status'] ?? 'incomplete';
                            $link = trim($item['attached_link'] ?? '');
                            $is_na = ($stat === 'not_applicable' || strtolower($link) === 'n/a');
                            $is_done = ($stat === 'completed');
                            $row_class = $is_done ? 'row-completed' : ($is_na ? 'row-na' : 'row-incomplete');
                        ?>
                        <tr class="checklist-row <?php echo $row_class; ?>" data-status="<?php echo $stat; ?>" data-text="<?php echo htmlspecialchars(strtolower($item['requirement_title'] . ' ' . $item['description'])); ?>" style="border-bottom: 1px solid #F1F5F9; transition: background 0.15s ease;" onmouseover="this.style.background='#F8FAFC'" onmouseout="this.style.background='white'">
                            <!-- No. -->
                            <td style="text-align: center; font-weight: 800; font-size: 0.95rem; color: #1E293B; vertical-align: top; padding: 0.9rem 0.85rem;">
                                <?php echo (int)$item['item_no']; ?>
                            </td>

                            <!-- Requirement Title -->
                            <td style="vertical-align: top; padding: 0.9rem 0.9rem;">
                                <div style="font-weight: 700; color: #0F172A; font-size: 0.88rem; margin-bottom: 0.25rem;">
                                    <?php echo htmlspecialchars($item['requirement_title']); ?>
                                </div>
                                <?php if($item['item_no'] == 1 || $item['item_no'] == 5 || $item['item_no'] == 16): ?>
                                    <span style="display: inline-block; background: #EEF2FF; color: #4338CA; font-size: 0.68rem; font-weight: 700; padding: 1px 6px; border-radius: 4px; border: 1px solid #C7D2FE;">
                                        Adviser / Panel Signed
                                    </span>
                                <?php endif; ?>
                                <?php if($item['item_no'] == 1): ?>
                                    <div style="margin-top: 0.35rem; display: flex; gap: 0.35rem; flex-wrap: wrap;">
                                        <a href="?tab=consultations" style="font-size: 0.7rem; font-weight: 700; color: #047857; background: #ECFDF5; border: 1px solid #6EE7B7; padding: 2px 7px; border-radius: 4px; text-decoration: none;">
                                            <i class="fa-solid fa-comments"></i> Consultation Portal
                                        </a>
                                        <a href="export_consultation_form.php?student_id=<?php echo $uid; ?>" target="_blank" style="font-size: 0.7rem; font-weight: 700; color: #1D4ED8; background: #EFF6FF; border: 1px solid #93C5FD; padding: 2px 7px; border-radius: 4px; text-decoration: none;">
                                            <i class="fa-solid fa-print"></i> Official Form Copy
                                        </a>
                                    </div>
                                <?php elseif($item['item_no'] == 17): ?>
                                    <span style="display: inline-block; background: #FEF3C7; color: #B45309; font-size: 0.68rem; font-weight: 700; padding: 1px 6px; border-radius: 4px; border: 1px solid #FDE68A;">
                                        ALBA Printing Press (Butuan)
                                    </span>
                                <?php elseif($item['item_no'] == 13): ?>
                                    <span style="display: inline-block; background: #ECFDF5; color: #065F46; font-size: 0.68rem; font-weight: 700; padding: 1px 6px; border-radius: 4px; border: 1px solid #A7F3D0;">
                                        ISO/IEC 25010 Standard
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- Description / Evidence -->
                            <td style="vertical-align: top; padding: 0.9rem 0.9rem; font-size: 0.82rem; color: #475569; line-height: 1.45;">
                                <div><?php echo nl2br(htmlspecialchars($item['description'])); ?></div>
                                <?php if(!empty($item['notes'])): ?>
                                    <div style="margin-top: 0.4rem; background: #F8FAFC; border-left: 3px solid #94A3B8; padding: 0.25rem 0.55rem; font-size: 0.76rem; color: #334155; border-radius: 0 4px 4px 0;">
                                        <strong>Remarks:</strong> <?php echo htmlspecialchars($item['notes']); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if(!empty($item['file_path']) && file_exists(__DIR__ . '/../../' . $item['file_path'])): ?>
                                    <div style="margin-top: 0.4rem;">
                                        <a href="<?php echo htmlspecialchars($item['file_path']); ?>" target="_blank" style="font-size: 0.74rem; color: #047857; text-decoration: none; font-weight: 700; display: inline-flex; align-items: center; gap: 0.3rem;">
                                            <i class="fa-solid fa-file-pdf" style="color: #DC2626;"></i>
                                            <span>Uploaded: <?php echo htmlspecialchars($item['file_name'] ?? 'View Document'); ?></span>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <!-- Attached Link -->
                            <td style="vertical-align: top; padding: 0.9rem 0.9rem;">
                                <?php if(empty($link)): ?>
                                    <div style="display: inline-flex; align-items: center; gap: 0.4rem; background: #FEF2F2; color: #991B1B; padding: 0.3rem 0.65rem; border-radius: 6px; font-size: 0.76rem; border: 1px solid #FECACA; font-weight: 600;">
                                        <i class="fa-solid fa-circle-exclamation"></i>
                                        <span>Missing Attachment</span>
                                    </div>
                                    <div style="margin-top: 0.35rem;">
                                        <button type="button" onclick="openChecklistModal(<?php echo (int)$item['id']; ?>, <?php echo (int)$item['item_no']; ?>, '<?php echo htmlspecialchars(addslashes($item['requirement_title'])); ?>', '', '<?php echo $stat; ?>', '<?php echo htmlspecialchars(addslashes($item['notes'] ?? '')); ?>')" style="background: none; border: none; padding: 0; color: #047857; font-size: 0.75rem; text-decoration: underline; cursor: pointer; font-weight: 700;">
                                            + Add Link or PDF
                                        </button>
                                    </div>
                                <?php elseif(strtolower($link) === 'n/a'): ?>
                                    <span style="background: #F1F5F9; color: #64748B; padding: 0.25rem 0.65rem; border-radius: 6px; font-size: 0.76rem; font-weight: 700; border: 1px solid #CBD5E1; display: inline-block;">
                                        N/A
                                    </span>
                                <?php elseif(preg_match('/drive\.google\.com/i', $link)): ?>
                                    <div style="background: #F0FDF4; border: 1px solid #BBF7D0; border-radius: 6px; padding: 0.45rem 0.65rem;">
                                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.4rem; margin-bottom: 2px;">
                                            <span style="color: #16A34A; font-weight: 700; font-size: 0.72rem; display: inline-flex; align-items: center; gap: 0.3rem;">
                                                <i class="fa-brands fa-google-drive"></i> Google Drive
                                            </span>
                                            <button type="button" onclick="copyChecklistLink('<?php echo htmlspecialchars(addslashes($link)); ?>')" title="Copy URL" style="background: none; border: none; color: #64748B; cursor: pointer; font-size: 0.72rem;">
                                                <i class="fa-regular fa-copy"></i>
                                            </button>
                                        </div>
                                        <a href="<?php echo htmlspecialchars($link); ?>" target="_blank" rel="noopener noreferrer" style="color: #065F46; font-size: 0.76rem; font-family: monospace; word-break: break-all; display: block; text-decoration: none; font-weight: 600;" title="Open Link">
                                            <?php echo htmlspecialchars(mb_strimwidth($link, 0, 36, '...')); ?>
                                            <i class="fa-solid fa-arrow-up-right-from-square" style="font-size: 0.65rem; margin-left: 2px;"></i>
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 6px; padding: 0.45rem 0.65rem;">
                                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.4rem; margin-bottom: 2px;">
                                            <span style="color: #047857; font-weight: 700; font-size: 0.72rem; display: inline-flex; align-items: center; gap: 0.3rem;">
                                                <i class="fa-solid fa-link"></i> Link
                                            </span>
                                            <button type="button" onclick="copyChecklistLink('<?php echo htmlspecialchars(addslashes($link)); ?>')" title="Copy URL" style="background: none; border: none; color: #64748B; cursor: pointer; font-size: 0.72rem;">
                                                <i class="fa-regular fa-copy"></i>
                                            </button>
                                        </div>
                                        <a href="<?php echo htmlspecialchars($link); ?>" target="_blank" rel="noopener noreferrer" style="color: #0F172A; font-size: 0.76rem; font-family: monospace; word-break: break-all; display: block; text-decoration: none; font-weight: 600;" title="Open Link">
                                            <?php echo htmlspecialchars(mb_strimwidth($link, 0, 36, '...')); ?>
                                            <i class="fa-solid fa-arrow-up-right-from-square" style="font-size: 0.65rem; margin-left: 2px;"></i>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <!-- Status (✔/✘) -->
                            <td style="text-align: center; vertical-align: top; padding: 0.9rem 0.85rem;">
                                <?php if($is_done): ?>
                                    <span style="display: inline-flex; align-items: center; justify-content: center; gap: 0.3rem; background: #D1FAE5; color: #065F46; border: 1px solid #A7F3D0; padding: 0.3rem 0.65rem; border-radius: 999px; font-size: 0.76rem; font-weight: 800;" title="Completed and verified">
                                        <span style="font-size: 0.95rem;">✔</span> Completed
                                    </span>
                                <?php elseif($is_na): ?>
                                    <span style="display: inline-flex; align-items: center; justify-content: center; gap: 0.3rem; background: #F1F5F9; color: #64748B; border: 1px solid #CBD5E1; padding: 0.3rem 0.65rem; border-radius: 999px; font-size: 0.76rem; font-weight: 700;">
                                        N/A
                                    </span>
                                <?php else: ?>
                                    <span style="display: inline-flex; align-items: center; justify-content: center; gap: 0.3rem; background: #FEE2E2; color: #991B1B; border: 1px solid #FECACA; padding: 0.3rem 0.65rem; border-radius: 999px; font-size: 0.76rem; font-weight: 800;" title="Incomplete - Required evidence missing">
                                        <span style="font-size: 0.95rem;">✘</span> Incomplete
                                    </span>
                                <?php endif; ?>

                                <!-- Quick Toggle Form -->
                                <form method="POST" action="action.php" style="margin-top: 0.35rem;">
                                    <input type="hidden" name="item_id" value="<?php echo (int)$item['id']; ?>">
                                    <input type="hidden" name="toggle_checklist_status" value="1">
                                    <button type="submit" style="background: none; border: none; font-size: 0.72rem; color: #64748B; cursor: pointer; text-decoration: underline;" title="Toggle completion status">
                                        Toggle (✔/✘)
                                    </button>
                                </form>
                            </td>

                            <!-- Action -->
                            <td style="text-align: center; vertical-align: top; padding: 0.9rem 0.85rem;">
                                <button type="button" 
                                        onclick="openChecklistModal(<?php echo (int)$item['id']; ?>, <?php echo (int)$item['item_no']; ?>, '<?php echo htmlspecialchars(addslashes($item['requirement_title'])); ?>', '<?php echo htmlspecialchars(addslashes($link)); ?>', '<?php echo $stat; ?>', '<?php echo htmlspecialchars(addslashes($item['notes'] ?? '')); ?>')" 
                                        class="btn-primary" 
                                        style="padding: 0.35rem 0.65rem; font-size: 0.76rem; white-space: nowrap; border-radius: 6px;" 
                                        title="Edit Link, Status or Upload PDF">
                                    <i class="fa-solid fa-pen-to-square"></i> Edit
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- EDIT ITEM MODAL -->
<div id="checklistModal" class="modal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 99999; backdrop-filter: blur(3px); align-items: center; justify-content: center;">
    <div class="modal-content" style="background: white; border-radius: 12px; width: 92%; max-width: 560px; box-shadow: var(--shadow-xl); overflow: hidden; margin: 2rem auto; position: relative;">
        <!-- Modal Header -->
        <div style="background: linear-gradient(135deg, #022C22 0%, #047857 100%); padding: 1.15rem 1.4rem; color: white; display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 0.6rem;">
                <div style="width: 28px; height: 28px; border-radius: 50%; background: #F59E0B; color: #022C22; font-weight: 800; font-size: 0.85rem; display: flex; align-items: center; justify-content: center;" id="modalItemNoBadge">
                    #
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 1.05rem; color: #FFFFFF;" id="modalRequirementTitle">Requirement</h3>
                    <small style="color: #A7F3D0; font-size: 0.74rem;">IT-199 Electronic Evidence Submission</small>
                </div>
            </div>
            <button type="button" onclick="closeChecklistModal()" style="background: none; border: none; color: white; font-size: 1.4rem; cursor: pointer; line-height: 1;">&times;</button>
        </div>

        <!-- Modal Form -->
        <form method="POST" action="action.php" enctype="multipart/form-data" style="padding: 1.35rem 1.5rem;">
            <input type="hidden" name="update_checklist_item" value="1">
            <input type="hidden" id="modalItemId" name="item_id" value="">

            <!-- Attached Link Input -->
            <div style="margin-bottom: 1.15rem;">
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #0F172A; margin-bottom: 0.4rem;">
                    <i class="fa-solid fa-link" style="color: var(--csu-green);"></i> Attached Link (Google Drive, OneDrive, or URL):
                </label>
                <input type="text" id="modalAttachedLink" name="attached_link" placeholder="https://drive.google.com/drive/folders/... or N/A" style="width: 100%; padding: 0.55rem 0.75rem; border: 1.5px solid #CBD5E1; border-radius: 7px; font-size: 0.84rem; font-family: monospace;">
                <small style="color: #64748B; font-size: 0.72rem; display: block; margin-top: 0.25rem;">
                    Tip: Enter "N/A" if this item is not applicable to your capstone project.
                </small>
            </div>

            <!-- Upload PDF File -->
            <div style="margin-bottom: 1.15rem; background: #F8FAFC; border: 1px dashed #94A3B8; border-radius: 8px; padding: 0.85rem 1rem;">
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #0F172A; margin-bottom: 0.35rem;">
                    <i class="fa-solid fa-file-pdf" style="color: #DC2626;"></i> Or Upload Scanned PDF Evidence:
                </label>
                <input type="file" name="attachment_file" accept=".pdf,.doc,.docx,.ppt,.pptx,.zip" style="width: 100%; font-size: 0.8rem; color: #334155;">
                <small style="color: #64748B; font-size: 0.72rem; display: block; margin-top: 0.25rem;">
                    PDF copies will be stored in your capstone group evidence repository. Max size: 25MB.
                </small>
            </div>

            <!-- Status Selector -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem; margin-bottom: 1.15rem;">
                <div>
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #0F172A; margin-bottom: 0.4rem;">
                        Status:
                    </label>
                    <select id="modalStatus" name="status" style="width: 100%; padding: 0.55rem 0.65rem; border: 1.5px solid #CBD5E1; border-radius: 7px; font-size: 0.84rem; background: white; font-weight: 600;">
                        <option value="completed">✔ Completed</option>
                        <option value="incomplete">✘ Incomplete</option>
                        <option value="in_progress">⏳ In Progress</option>
                        <option value="not_applicable">➖ Not Applicable (N/A)</option>
                    </select>
                    <input type="hidden" name="status_explicit" value="1">
                </div>

                <div>
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #0F172A; margin-bottom: 0.4rem;">
                        Group Synchronization:
                    </label>
                    <div style="background: #ECFDF5; border: 1px solid #A7F3D0; border-radius: 7px; padding: 0.5rem 0.65rem; font-size: 0.75rem; color: #065F46; font-weight: 600; display: flex; align-items: center; gap: 0.35rem;">
                        <i class="fa-solid fa-rotate"></i>
                        <span>Live Sync to All Teammates</span>
                    </div>
                </div>
            </div>

            <!-- Notes / Remarks -->
            <div style="margin-bottom: 1.35rem;">
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #0F172A; margin-bottom: 0.4rem;">
                    Remarks / Notes (Optional):
                </label>
                <textarea id="modalNotes" name="notes" rows="2" placeholder="e.g., Hard copies signed by Sir Monzon, scanned on Oct 2026..." style="width: 100%; padding: 0.5rem 0.75rem; border: 1.5px solid #CBD5E1; border-radius: 7px; font-size: 0.82rem; font-family: 'Inter', sans-serif; resize: vertical;"></textarea>
            </div>

            <!-- Modal Footer -->
            <div style="display: flex; justify-content: flex-end; gap: 0.6rem; border-top: 1px solid #E2E8F0; padding-top: 1rem;">
                <button type="button" onclick="closeChecklistModal()" class="btn-primary" style="background: #F1F5F9; color: #334155; border: 1px solid #CBD5E1;">
                    Cancel
                </button>
                <button type="submit" class="btn-primary" style="background: var(--csu-green); color: white; font-weight: 700;">
                    <i class="fa-solid fa-floppy-disk"></i> Save Requirement
                </button>
            </div>
        </form>
    </div>
</div>

<!-- COPY NOTIFICATION TOAST -->
<div id="copyToast" style="display: none; position: fixed; bottom: 25px; right: 25px; background: #064E3B; color: white; padding: 0.65rem 1.15rem; border-radius: 8px; font-size: 0.84rem; font-weight: 700; box-shadow: var(--shadow-lg); z-index: 999999; display: none; align-items: center; gap: 0.5rem;">
    <i class="fa-solid fa-circle-check" style="color: #34D399;"></i>
    <span>Link copied to clipboard!</span>
</div>

<script>
function openChecklistModal(id, itemNo, title, link, status, notes) {
    document.getElementById('modalItemId').value = id;
    document.getElementById('modalItemNoBadge').innerText = '#' + itemNo;
    document.getElementById('modalRequirementTitle').innerText = title;
    document.getElementById('modalAttachedLink').value = link;
    document.getElementById('modalStatus').value = status;
    document.getElementById('modalNotes').value = notes;

    const modal = document.getElementById('checklistModal');
    modal.style.display = 'flex';
}

function closeChecklistModal() {
    const modal = document.getElementById('checklistModal');
    modal.style.display = 'none';
}

// Close when clicking modal backdrop
window.addEventListener('click', function(e) {
    const modal = document.getElementById('checklistModal');
    if (e.target === modal) {
        closeChecklistModal();
    }
});

function copyChecklistLink(url) {
    if (!url || url.toLowerCase() === 'n/a') return;
    navigator.clipboard.writeText(url).then(() => {
        const toast = document.getElementById('copyToast');
        toast.style.display = 'flex';
        setTimeout(() => {
            toast.style.display = 'none';
        }, 2500);
    }).catch(err => {
        console.error('Copy failed:', err);
    });
}

function filterChecklistTable() {
    const searchVal = document.getElementById('checklistSearch').value.toLowerCase();
    const statusVal = document.getElementById('checklistStatusFilter').value;
    const rows = document.querySelectorAll('.checklist-row');

    rows.forEach(row => {
        const text = row.getAttribute('data-text') || '';
        const status = row.getAttribute('data-status') || '';

        const matchesSearch = !searchVal || text.includes(searchVal);
        let matchesStatus = true;

        if (statusVal === 'completed') {
            matchesStatus = (status === 'completed');
        } else if (statusVal === 'incomplete') {
            matchesStatus = (status === 'incomplete' || status === 'in_progress');
        } else if (statusVal === 'not_applicable') {
            matchesStatus = (status === 'not_applicable');
        }

        if (matchesSearch && matchesStatus) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}
</script>

<style>
@media print {
    .sidebar, .topbar, .topbar-toggle-btn, .banner button, .banner a, #checklistSearch, #checklistStatusFilter, .checklist-row td:last-child, th:last-child, form button {
        display: none !important;
    }
    .main-content {
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
    }
    .banner {
        background: #047857 !important;
        color: white !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .card {
        box-shadow: none !important;
        border: 1px solid #000 !important;
    }
    table {
        width: 100% !important;
        border: 1px solid #000 !important;
    }
    th, td {
        border: 1px solid #000 !important;
    }
}
</style>
