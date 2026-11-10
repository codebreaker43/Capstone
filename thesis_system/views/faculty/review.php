<?php
// Faculty user ID
$adviser_id = (int)$uid;

// 1. Fetch all accepted advisees of this faculty member
$advisees_list_q = $conn->query("SELECT m.student_id, u.name as stu_name, u.email, sp.thesis_topic, sp.student_id as student_no, sp.group_id, sp.title_status, sp.title_approved_at, sp.title_approved_by, sp.abstract_file, sp.abstract_name, cg.group_name 
    FROM match_request m 
    JOIN user u ON m.student_id = u.id 
    JOIN student_profile sp ON u.id = sp.user_id 
    LEFT JOIN capstone_group cg ON sp.group_id = cg.id 
    WHERE m.adviser_id = $adviser_id AND m.status = 'accepted' 
    ORDER BY u.name ASC");

$my_advisees = [];
$advisee_student_ids = [];
if ($advisees_list_q) {
    while ($adv = $advisees_list_q->fetch_assoc()) {
        $my_advisees[] = $adv;
        $advisee_student_ids[] = (int)$adv['student_id'];
    }
}

// 2. Determine selected student filter and selected submission
$selected_student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$selected_sub_id = isset($_GET['submission_id']) ? (int)$_GET['submission_id'] : 0;

// Build query for documents submitted to this adviser or by their advisees
$where_clauses = [];
if (!empty($advisee_student_ids)) {
    $ids_str = implode(',', $advisee_student_ids);
    $where_clauses[] = "(ds.adviser_id = $adviser_id OR ds.student_id IN ($ids_str))";
} else {
    $where_clauses[] = "ds.adviser_id = $adviser_id";
}

if ($selected_student_id > 0) {
    $where_clauses[] = "ds.student_id = $selected_student_id";
}

$where_sql = implode(' AND ', $where_clauses);

$all_subs_q = $conn->query("SELECT ds.*, u.name as stu_name, u.email as stu_email, sp.student_id as student_no, sp.group_id, sp.thesis_topic, sp.title_status, sp.title_approved_at, sp.title_approved_by, sp.abstract_file, sp.abstract_name, cg.group_name, rev.name as reviewer_name 
    FROM document_submissions ds 
    JOIN user u ON ds.student_id = u.id 
    LEFT JOIN student_profile sp ON u.id = sp.user_id 
    LEFT JOIN capstone_group cg ON ds.group_id = cg.id 
    LEFT JOIN user rev ON ds.reviewed_by = rev.id 
    WHERE $where_sql 
    ORDER BY (ds.status = 'pending') DESC, ds.id DESC");

$submissions = [];
$counts = [
    'total' => 0,
    'pending' => 0,
    'under_review' => 0,
    'needs_revision' => 0,
    'approved' => 0
];

$active_sub = null;

if ($all_subs_q) {
    while ($row = $all_subs_q->fetch_assoc()) {
        $submissions[] = $row;
        $counts['total']++;
        if (isset($counts[$row['status']])) {
            $counts[$row['status']]++;
        }
        // If this matches selected submission_id or is the first one when requested
        if ($selected_sub_id > 0 && $row['id'] == $selected_sub_id) {
            $active_sub = $row;
        }
    }
}

// If no specific submission selected but a student was filtered, default to their latest submission
if (!$active_sub && $selected_student_id > 0 && !empty($submissions)) {
    $active_sub = $submissions[0];
}

// Fallback: If still no active_sub and submissions exist, select the first pending or first submission
if (!$active_sub && !empty($submissions)) {
    $active_sub = $submissions[0];
}

function format_file_size_faculty($bytes) {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    } elseif ($bytes > 0) {
        return $bytes . ' B';
    }
    return 'N/A';
}
?>

<!-- PAGE BANNER & HEADER -->
<div class="banner" style="background: linear-gradient(135deg, var(--csu-green-darker) 0%, var(--csu-green-dark) 55%, var(--csu-green) 100%); border-radius: 14px; padding: 1.75rem 2rem; color: white; margin-bottom: 1.5rem; box-shadow: var(--shadow-md); position: relative; overflow: hidden;">
    <div style="position: absolute; right: -25px; bottom: -30px; opacity: 0.12; font-size: 11rem; pointer-events: none;">
        <i class="fa-solid fa-file-signature"></i>
    </div>
    <div style="position: relative; z-index: 1;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
            <div>
                <span class="badge" style="background: rgba(245, 158, 11, 0.2); color: #FCD34D; border: 1px solid rgba(245, 158, 11, 0.35); padding: 0.3rem 0.75rem; border-radius: 999px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; display: inline-flex; align-items: center; gap: 0.4rem; margin-bottom: 0.6rem;">
                    <i class="fa-solid fa-chalkboard-user"></i> Faculty Advisory Suite
                </span>
                <h1 style="font-size: 1.65rem; font-weight: 800; margin: 0 0 0.4rem 0; color: #FFFFFF; font-family: 'Outfit', sans-serif;">
                    Manuscript Checking & Document Review Center
                </h1>
                <p style="margin: 0; color: #A7F3D0; font-size: 0.92rem; max-width: 740px; line-height: 1.5;">
                    Review submitted proposals, research chapters, and full drafts from your active advisees. Provide structured evaluations, return annotated manuscripts, and record official approvals.
                </p>
            </div>
            <div style="display: flex; gap: 0.6rem; flex-wrap: wrap;">
                <a href="?tab=advisees" class="btn" style="background: rgba(255,255,255,0.12); color: white; border: 1px solid rgba(255,255,255,0.25); padding: 0.6rem 1.1rem; border-radius: 8px; font-size: 0.85rem; text-decoration: none; display: inline-flex; align-items: center; gap: 0.45rem;">
                    <i class="fa-solid fa-user-graduate"></i> Active Advisees (<?php echo count($my_advisees); ?>)
                </a>
            </div>
        </div>
    </div>
</div>

<!-- STATS CARDS -->
<div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
    <div class="stat-card" style="background: white; border: 1px solid #E2E8F0; border-radius: 12px; padding: 1.15rem 1.25rem; display: flex; align-items: center; gap: 1rem; box-shadow: var(--shadow-sm);">
        <div style="width: 48px; height: 48px; border-radius: 10px; background: #ECFDF5; color: #059669; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; flex-shrink: 0;">
            <i class="fa-solid fa-layer-group"></i>
        </div>
        <div>
            <div style="font-size: 1.55rem; font-weight: 800; color: #0F172A; line-height: 1;"><?php echo $counts['total']; ?></div>
            <div style="font-size: 0.8rem; color: #64748B; font-weight: 600; margin-top: 0.25rem;">Total Submissions</div>
        </div>
    </div>

    <div class="stat-card" style="background: white; border: 1px solid <?php echo ($counts['pending'] > 0) ? '#F59E0B' : '#E2E8F0'; ?>; border-radius: 12px; padding: 1.15rem 1.25rem; display: flex; align-items: center; gap: 1rem; box-shadow: var(--shadow-sm);">
        <div style="width: 48px; height: 48px; border-radius: 10px; background: #FEF3C7; color: #B45309; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; flex-shrink: 0;">
            <i class="fa-solid fa-bell"></i>
        </div>
        <div>
            <div style="font-size: 1.55rem; font-weight: 800; color: #B45309; line-height: 1;"><?php echo $counts['pending'] + $counts['under_review']; ?></div>
            <div style="font-size: 0.8rem; color: #92400E; font-weight: 700; margin-top: 0.25rem;">Pending Your Review</div>
        </div>
    </div>

    <div class="stat-card" style="background: white; border: 1px solid #FED7AA; border-radius: 12px; padding: 1.15rem 1.25rem; display: flex; align-items: center; gap: 1rem; box-shadow: var(--shadow-sm);">
        <div style="width: 48px; height: 48px; border-radius: 10px; background: #FFF7ED; color: #EA580C; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; flex-shrink: 0;">
            <i class="fa-solid fa-rotate-left"></i>
        </div>
        <div>
            <div style="font-size: 1.55rem; font-weight: 800; color: #C2410C; line-height: 1;"><?php echo $counts['needs_revision']; ?></div>
            <div style="font-size: 0.8rem; color: #9A3412; font-weight: 600; margin-top: 0.25rem;">Revisions Requested</div>
        </div>
    </div>

    <div class="stat-card" style="background: white; border: 1px solid #A7F3D0; border-radius: 12px; padding: 1.15rem 1.25rem; display: flex; align-items: center; gap: 1rem; box-shadow: var(--shadow-sm);">
        <div style="width: 48px; height: 48px; border-radius: 10px; background: #D1FAE5; color: #047857; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; flex-shrink: 0;">
            <i class="fa-solid fa-circle-check"></i>
        </div>
        <div>
            <div style="font-size: 1.55rem; font-weight: 800; color: #065F46; line-height: 1;"><?php echo $counts['approved']; ?></div>
            <div style="font-size: 0.8rem; color: #047857; font-weight: 600; margin-top: 0.25rem;">Approved Papers</div>
        </div>
    </div>
</div>

<!-- ============================================================== -->
<!-- FILTER BAR & ADVISEE SELECTOR -->
<!-- ============================================================== -->
<div class="card" style="margin-bottom: 1.5rem; padding: 1rem 1.25rem; background: #F8FAFC; border: 1px solid #E2E8F0;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        
        <!-- Filter by Advisee -->
        <div style="display: flex; align-items: center; gap: 0.65rem; flex: 1; min-width: 280px;">
            <label style="font-size: 0.82rem; font-weight: 700; color: #475569; white-space: nowrap;">
                <i class="fa-solid fa-filter" style="color: var(--csu-green);"></i> Filter by Advisee:
            </label>
            <select onchange="window.location.href='?tab=review&student_id=' + this.value" 
                    style="flex: 1; max-width: 360px; padding: 0.45rem 0.75rem; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 0.84rem; background: white; font-family: 'Inter', sans-serif;">
                <option value="0" <?php echo ($selected_student_id == 0) ? 'selected' : ''; ?>>All Advisees (Show All Submissions)</option>
                <?php foreach($my_advisees as $adv): 
                    $tstat_label = (($adv['title_status'] ?? 'pending') == 'approved') ? '✓ Title Approved' : '⏳ Title Pending';
                ?>
                    <option value="<?php echo $adv['student_id']; ?>" <?php echo ($selected_student_id == $adv['student_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($adv['stu_name']); ?> <?php echo !empty($adv['group_name']) ? '('.htmlspecialchars($adv['group_name']).')' : ''; ?> — [<?php echo $tstat_label; ?>]
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Filter by Status -->
        <div style="display: flex; gap: 0.35rem; flex-wrap: wrap;">
            <button type="button" onclick="filterFacultySubmissions('all')" id="facBtnAll" class="btn-filter-fac" style="padding: 0.35rem 0.65rem; font-size: 0.78rem; font-weight: 600; border-radius: 6px; border: 1px solid #CBD5E1; background: #047857; color: white; cursor: pointer;">
                All (<?php echo $counts['total']; ?>)
            </button>
            <button type="button" onclick="filterFacultySubmissions('pending')" id="facBtnPending" class="btn-filter-fac" style="padding: 0.35rem 0.65rem; font-size: 0.78rem; font-weight: 600; border-radius: 6px; border: 1px solid #FDE68A; background: #FEF3C7; color: #92400E; cursor: pointer;">
                Pending (<?php echo $counts['pending'] + $counts['under_review']; ?>)
            </button>
            <button type="button" onclick="filterFacultySubmissions('needs_revision')" id="facBtnRevision" class="btn-filter-fac" style="padding: 0.35rem 0.65rem; font-size: 0.78rem; font-weight: 600; border-radius: 6px; border: 1px solid #FED7AA; background: #FFF7ED; color: #C2410C; cursor: pointer;">
                Needs Revision (<?php echo $counts['needs_revision']; ?>)
            </button>
            <button type="button" onclick="filterFacultySubmissions('approved')" id="facBtnApproved" class="btn-filter-fac" style="padding: 0.35rem 0.65rem; font-size: 0.78rem; font-weight: 600; border-radius: 6px; border: 1px solid #A7F3D0; background: #ECFDF5; color: #047857; cursor: pointer;">
                Approved (<?php echo $counts['approved']; ?>)
            </button>
        </div>

    </div>
</div>

<!-- ============================================================== -->
<!-- MAIN TWO-COLUMN WORKBENCH: SUBMISSIONS LIST & EVALUATION PANE -->
<!-- ============================================================== -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 1.5rem; align-items: flex-start;">
    
    <!-- LEFT COLUMN: SUBMISSION QUEUE -->
    <div>
        <div class="card" style="border-top: 4px solid var(--csu-gold); margin-bottom: 1.5rem;">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <div class="card-header-left">
                    <i class="fa-solid fa-list-check" style="color: var(--csu-gold);"></i>
                    <h2>Submissions Review Queue</h2>
                </div>
                <span class="badge badge-success" style="font-size: 0.75rem;">
                    <?php echo count($submissions); ?> Documents
                </span>
            </div>
            <div class="card-body" style="padding: 1rem;">
                
                <?php if(empty($submissions)): ?>
                    <div style="text-align: center; padding: 3rem 1rem; color: #64748B;">
                        <i class="fa-solid fa-folder-open fa-3x" style="color: #CBD5E1; margin-bottom: 0.85rem; display: block;"></i>
                        <h4 style="color: #0F172A; font-size: 1rem; margin-bottom: 0.35rem;">No Submissions Found</h4>
                        <p style="margin: 0; font-size: 0.84rem;">
                            <?php if($selected_student_id > 0): ?>
                                This student has not uploaded any documents for review yet.
                            <?php else: ?>
                                Once your advisees submit manuscripts, they will appear in this review queue.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 0.85rem;">
                        <?php foreach($submissions as $sub): 
                            $is_active = ($active_sub && $active_sub['id'] == $sub['id']);
                            $stat = $sub['status'];
                            $badgeColor = '#D97706'; $badgeBg = '#FEF3C7'; $badgeBorder = '#FDE68A'; $badgeText = 'Pending Review';
                            if ($stat == 'approved') {
                                $badgeColor = '#047857'; $badgeBg = '#D1FAE5'; $badgeBorder = '#A7F3D0'; $badgeText = 'Approved';
                            } elseif ($stat == 'needs_revision') {
                                $badgeColor = '#DC2626'; $badgeBg = '#FEE2E2'; $badgeBorder = '#FECACA'; $badgeText = 'Needs Revision';
                            } elseif ($stat == 'under_review') {
                                $badgeColor = '#2563EB'; $badgeBg = '#DBEAFE'; $badgeBorder = '#BFDBFE'; $badgeText = 'Under Review';
                            }

                            $ext = strtolower($sub['file_ext']);
                            $fileIcon = 'fa-file-lines'; $fileIconColor = '#64748B';
                            if ($ext == 'pdf') { $fileIcon = 'fa-file-pdf'; $fileIconColor = '#DC2626'; }
                            elseif (in_array($ext, ['doc', 'docx'])) { $fileIcon = 'fa-file-word'; $fileIconColor = '#2563EB'; }
                            elseif ($ext == 'gdoc') { $fileIcon = 'fa-file-lines'; $fileIconColor = '#2563EB'; }
                        ?>
                            <div class="fac-sub-item" data-status="<?php echo $stat == 'under_review' ? 'pending' : $stat; ?>"
                                 style="background: <?php echo $is_active ? '#F0FDF4' : 'white'; ?>; border: 1px solid <?php echo $is_active ? 'var(--csu-green)' : '#E2E8F0'; ?>; border-radius: 10px; padding: 1rem; box-shadow: <?php echo $is_active ? '0 0 0 2px rgba(4,120,87,0.15)' : 'var(--shadow-sm)'; ?>; transition: all 0.2s ease;">
                                
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem; gap: 0.5rem;">
                                    <div style="display: flex; align-items: center; gap: 0.65rem;">
                                        <div style="width: 34px; height: 34px; border-radius: 50%; background: var(--csu-green); color: white; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; font-weight: 800; flex-shrink: 0;">
                                            <?php echo strtoupper(substr($sub['stu_name'], 0, 1)); ?>
                                        </div>
                                        <div style="overflow: hidden;">
                                            <strong style="color: #0F172A; font-size: 0.88rem; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                <?php echo htmlspecialchars($sub['stu_name']); ?>
                                            </strong>
                                            <small style="color: #64748B; font-size: 0.72rem; display: flex; align-items: center; gap: 0.35rem; margin-top: 0.15rem; flex-wrap: wrap;">
                                                <span><?php echo !empty($sub['group_name']) ? htmlspecialchars($sub['group_name']) : 'Individual'; ?></span>
                                                <?php if(!empty($sub['student_no'])): ?><span>• ID: <?php echo htmlspecialchars($sub['student_no']); ?></span><?php endif; ?>
                                                <?php 
                                                $st_tstat = $sub['title_status'] ?? 'pending';
                                                if ($st_tstat === 'approved'): ?>
                                                    <span style="color: #047857; font-weight: 700; background: #ECFDF5; border: 1px solid #A7F3D0; padding: 1px 5px; border-radius: 4px; font-size: 0.65rem;">
                                                        <i class="fa-solid fa-circle-check"></i> Title Approved
                                                    </span>
                                                <?php elseif ($st_tstat === 'rejected'): ?>
                                                    <span style="color: #DC2626; font-weight: 700; background: #FEF2F2; border: 1px solid #FECACA; padding: 1px 5px; border-radius: 4px; font-size: 0.65rem;">
                                                        <i class="fa-solid fa-triangle-exclamation"></i> Title Revision
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color: #B45309; font-weight: 700; background: #FEF3C7; border: 1px solid #FDE68A; padding: 1px 5px; border-radius: 4px; font-size: 0.65rem;">
                                                        <i class="fa-regular fa-clock"></i> Title Pending
                                                    </span>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </div>

                                    <span style="background: <?php echo $badgeBg; ?>; color: <?php echo $badgeColor; ?>; border: 1px solid <?php echo $badgeBorder; ?>; font-size: 0.7rem; font-weight: 700; padding: 0.2rem 0.55rem; border-radius: 999px; white-space: nowrap;">
                                        <?php echo $badgeText; ?>
                                    </span>
                                </div>

                                <div style="margin-bottom: 0.65rem;">
                                    <div style="display: flex; align-items: center; gap: 0.35rem; margin-bottom: 0.2rem; flex-wrap: wrap;">
                                        <span style="background: #F1F5F9; color: #475569; font-size: 0.7rem; font-weight: 700; padding: 0.15rem 0.45rem; border-radius: 4px;">
                                            <?php echo htmlspecialchars($sub['doc_type']); ?>
                                        </span>
                                        <span style="background: #EFF6FF; color: #1E40AF; font-size: 0.7rem; font-weight: 700; padding: 0.15rem 0.45rem; border-radius: 4px;">
                                            <?php echo htmlspecialchars($sub['version']); ?>
                                        </span>
                                        <?php if(!empty($sub['shareable_link'])): ?>
                                            <span style="background: #EFF6FF; color: #1D4ED8; font-size: 0.68rem; font-weight: 700; padding: 0.15rem 0.45rem; border-radius: 4px; border: 1px solid #BFDBFE;">
                                                <i class="fa-solid fa-link"></i> Shareable Link
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <h4 style="margin: 0; color: #1E293B; font-size: 0.92rem; font-weight: 700; line-height: 1.35; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                        <?php echo htmlspecialchars($sub['title']); ?>
                                    </h4>
                                </div>

                                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.75rem; color: #64748B; padding-top: 0.5rem; border-top: 1px dashed #E2E8F0;">
                                    <span>
                                        <?php if(!empty($sub['shareable_link']) && ($ext == 'gdoc' || $ext == 'link' || empty($sub['file_size']))): ?>
                                            <i class="fa-solid fa-globe" style="color: #2563EB;"></i> Online Cloud Doc
                                        <?php else: ?>
                                            <i class="fa-solid <?php echo $fileIcon; ?>" style="color: <?php echo $fileIconColor; ?>;"></i> <?php echo format_file_size_faculty($sub['file_size']); ?>
                                        <?php endif; ?>
                                    </span>
                                    <a href="?tab=review&student_id=<?php echo $sub['student_id']; ?>&submission_id=<?php echo $sub['id']; ?>" 
                                       class="btn-primary" 
                                       style="padding: 0.3rem 0.75rem; font-size: 0.76rem; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem;">
                                       <i class="fa-solid fa-file-pen"></i> Review
                                    </a>
                                </div>

                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <!-- RIGHT COLUMN: ACTIVE DOCUMENT INSPECTION & EVALUATION FORM -->
    <div style="flex: 1.4;">
        
        <?php if($active_sub): 
            $astat = $active_sub['status'];
            $aext = strtolower($active_sub['file_ext']);
            $aicon = ($aext == 'pdf') ? 'fa-file-pdf' : (in_array($aext, ['doc','docx']) ? 'fa-file-word' : 'fa-file-lines');
            $aiconColor = ($aext == 'pdf') ? '#DC2626' : (in_array($aext, ['doc','docx']) ? '#2563EB' : '#059669');

            // Fetch group members if group_id exists
            $grp_members = [];
            if (!empty($active_sub['group_id'])) {
                $grp_members = get_group_members_roster($conn, (int)$active_sub['group_id']);
            }
        ?>

            <!-- INSPECTION CARD -->
            <div class="card" style="border-top: 4px solid var(--csu-green); margin-bottom: 1.5rem;">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                    <div class="card-header-left">
                        <i class="fa-solid fa-file-invoice" style="color: var(--csu-green);"></i>
                        <h2>Evaluating Document #<?php echo $active_sub['id']; ?></h2>
                    </div>
                    <div style="display: flex; gap: 0.4rem; align-items: center;">
                        <span class="badge" style="background: #EFF6FF; color: #1D4ED8; border: 1px solid #BFDBFE; font-size: 0.76rem;">
                            <?php echo htmlspecialchars($active_sub['version']); ?>
                        </span>
                        <span class="badge <?php echo ($astat == 'approved') ? 'badge-success' : (($astat == 'needs_revision') ? 'badge-danger' : 'badge-warning'); ?>" style="font-size: 0.76rem;">
                            <?php echo ucfirst(str_replace('_', ' ', $astat)); ?>
                        </span>
                    </div>
                </div>

                <div class="card-body">
                    
                    <!-- Student & Group Profile Bar -->
                    <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 10px; padding: 1rem 1.15rem; margin-bottom: 1.25rem;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 0.75rem;">
                            <div>
                                <div style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: #64748B; letter-spacing: 0.5px; margin-bottom: 0.2rem;">
                                    Lead Submitter & Advisee
                                </div>
                                <h3 style="margin: 0; color: #0F172A; font-size: 1.05rem; font-weight: 800;">
                                    <?php echo htmlspecialchars($active_sub['stu_name']); ?>
                                </h3>
                                <p style="margin: 0.15rem 0 0 0; color: #64748B; font-size: 0.8rem;">
                                    <i class="fa-solid fa-envelope"></i> <?php echo htmlspecialchars($active_sub['stu_email']); ?>
                                    <?php if(!empty($active_sub['student_no'])): ?> • School ID: <strong><?php echo htmlspecialchars($active_sub['student_no']); ?></strong><?php endif; ?>
                                </p>
                            </div>

                            <?php if(!empty($active_sub['group_name'])): ?>
                                <div style="text-align: right;">
                                    <div style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: #64748B; letter-spacing: 0.5px; margin-bottom: 0.2rem;">
                                        Capstone Group
                                    </div>
                                    <span style="color: var(--csu-green); font-weight: 800; font-size: 0.92rem;">
                                        <i class="fa-solid fa-users"></i> <?php echo htmlspecialchars($active_sub['group_name']); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- 3 Group Members Roster if present -->
                        <?php if(!empty($grp_members)): ?>
                            <div style="padding-top: 0.65rem; border-top: 1px dashed #CBD5E1;">
                                <div style="font-size: 0.7rem; font-weight: 700; text-transform: uppercase; color: #64748B; margin-bottom: 0.35rem;">
                                    Group Co-Authors (3 Members):
                                </div>
                                <div style="display: flex; gap: 0.45rem; flex-wrap: wrap;">
                                    <?php for($i = 0; $i < 3; $i++): $mem = $grp_members[$i] ?? null; ?>
                                        <div style="flex: 1; min-width: 140px; background: white; border: 1px solid #E2E8F0; border-radius: 6px; padding: 0.3rem 0.5rem; font-size: 0.76rem;">
                                            <?php if($mem): ?>
                                                <i class="fa-solid <?php echo $mem['is_leader'] ? 'fa-crown' : 'fa-user'; ?>" style="color: <?php echo $mem['is_leader'] ? '#D97706' : '#047857'; ?>; font-size: 0.7rem;"></i>
                                                <strong><?php echo htmlspecialchars($mem['name']); ?></strong>
                                            <?php else: ?>
                                                <span style="color: #94A3B8; font-style: italic;">Vacant Slot <?php echo $i+1; ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Official Research Title & Approval Status Bar -->
                        <div style="margin-top: 0.85rem; padding-top: 0.75rem; border-top: 1px dashed #CBD5E1;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem; flex-wrap: wrap; gap: 0.5rem;">
                                <div style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: #475569; letter-spacing: 0.5px;">
                                    <i class="fa-solid fa-graduation-cap" style="color: var(--csu-green);"></i> Registered Capstone Title:
                                </div>
                                <div style="display: flex; align-items: center; gap: 0.4rem; flex-wrap: wrap;">
                                    <?php 
                                    $active_tstat = $active_sub['title_status'] ?? 'pending';
                                    if ($active_tstat === 'approved'): ?>
                                        <span class="badge" style="background: #ECFDF5; color: #047857; border: 1px solid #A7F3D0; font-size: 0.74rem; font-weight: 700; padding: 2px 8px; border-radius: 999px;">
                                            <i class="fa-solid fa-circle-check"></i> Title Approved
                                            <?php if(!empty($active_sub['title_approved_at'])): ?>
                                                <span style="font-size: 0.68rem; font-weight: 500; opacity: 0.85;">(<?php echo date('M d, Y', strtotime($active_sub['title_approved_at'])); ?>)</span>
                                            <?php endif; ?>
                                        </span>
                                        <a href="action.php?update_title_approval=1&student_id=<?php echo $active_sub['student_id']; ?>&status=pending&return_tab=review&submission_id=<?php echo $active_sub['id']; ?>" 
                                           onclick="return confirm('Revoke title approval for this student?');"
                                           style="font-size: 0.72rem; color: #64748B; text-decoration: underline; margin-left: 4px;">
                                            Revoke
                                        </a>
                                    <?php elseif ($active_tstat === 'rejected'): ?>
                                        <span class="badge" style="background: #FEF2F2; color: #B91C1C; border: 1px solid #FECACA; font-size: 0.74rem; font-weight: 700; padding: 2px 8px; border-radius: 999px;">
                                            <i class="fa-solid fa-circle-xmark"></i> Title Needs Revision
                                        </span>
                                        <a href="action.php?update_title_approval=1&student_id=<?php echo $active_sub['student_id']; ?>&status=approved&return_tab=review&submission_id=<?php echo $active_sub['id']; ?>" 
                                           class="btn" style="background: #ECFDF5; color: #047857; border: 1px solid #A7F3D0; font-weight: 700; font-size: 0.72rem; padding: 2px 8px; border-radius: 4px; text-decoration: none;">
                                            <i class="fa-solid fa-check"></i> Approve Title
                                        </a>
                                    <?php else: ?>
                                        <span class="badge" style="background: #FFFBEB; color: #B45309; border: 1px solid #FDE68A; font-size: 0.74rem; font-weight: 700; padding: 2px 8px; border-radius: 999px;">
                                            <i class="fa-regular fa-clock"></i> Title Pending Approval
                                        </span>
                                        <a href="action.php?update_title_approval=1&student_id=<?php echo $active_sub['student_id']; ?>&status=approved&return_tab=review&submission_id=<?php echo $active_sub['id']; ?>" 
                                           class="btn" style="background: #ECFDF5; color: #047857; border: 1px solid #A7F3D0; font-weight: 700; font-size: 0.72rem; padding: 2px 8px; border-radius: 4px; text-decoration: none;">
                                            <i class="fa-solid fa-check"></i> Approve Title
                                        </a>
                                        <a href="action.php?update_title_approval=1&student_id=<?php echo $active_sub['student_id']; ?>&status=rejected&return_tab=review&submission_id=<?php echo $active_sub['id']; ?>" 
                                           class="btn" style="background: #FFF1F2; color: #BE123C; border: 1px solid #FECDD3; font-weight: 600; font-size: 0.7rem; padding: 2px 6px; border-radius: 4px; text-decoration: none;">
                                            Request Revision
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div style="font-size: 0.92rem; color: #0F172A; font-weight: 700; line-height: 1.4;">
                                <?php echo !empty($active_sub['thesis_topic']) ? htmlspecialchars($active_sub['thesis_topic']) : '<span style="color: #94A3B8; font-style: italic; font-weight: 400;">No official topic registered yet in student profile</span>'; ?>
                            </div>
                            <?php if(!empty($active_sub['abstract_file']) && file_exists($active_sub['abstract_file'])): ?>
                                <div style="margin-top: 0.45rem;">
                                    <a href="<?php echo htmlspecialchars($active_sub['abstract_file']); ?>" target="_blank" style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.76rem; font-weight: 700; color: #DC2626; background: #FEF2F2; border: 1px solid #FECACA; padding: 2px 8px; border-radius: 6px; text-decoration: none;">
                                        <i class="fa-solid fa-file-pdf"></i> View Concept PDF (<?php echo htmlspecialchars($active_sub['abstract_name'] ?? 'Concept.pdf'); ?>)
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Document Title, Category, and Remarks -->
                    <div style="margin-bottom: 1.25rem;">
                        <span style="background: #F1F5F9; color: #334155; font-size: 0.75rem; font-weight: 700; padding: 0.25rem 0.6rem; border-radius: 6px; border: 1px solid #CBD5E1; display: inline-block; margin-bottom: 0.45rem;">
                            <?php echo htmlspecialchars($active_sub['doc_type']); ?>
                        </span>
                        <h2 style="margin: 0 0 0.5rem 0; color: #0F172A; font-size: 1.25rem; font-weight: 800; line-height: 1.35;">
                            <?php echo htmlspecialchars($active_sub['title']); ?>
                        </h2>
                        <small style="color: #64748B; font-size: 0.78rem;">
                            <i class="fa-regular fa-clock"></i> Submitted: <?php echo date('F d, Y • h:i A', strtotime($active_sub['created_at'])); ?>
                        </small>
                    </div>

                    <!-- Student Remarks / Abstract Box -->
                    <?php if(!empty($active_sub['description'])): ?>
                        <div style="background: #F8FAFC; border-left: 4px solid var(--primary-blue); border-radius: 0 8px 8px 0; padding: 0.85rem 1.15rem; margin-bottom: 1.25rem;">
                            <strong style="color: #1E293B; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 0.35rem;">
                                <i class="fa-solid fa-message" style="color: var(--primary-blue);"></i> Student Remarks & Abstract:
                            </strong>
                            <p style="margin: 0; color: #334155; font-size: 0.88rem; line-height: 1.5; white-space: pre-wrap;"><?php echo htmlspecialchars($active_sub['description']); ?></p>
                        </div>
                    <?php endif; ?>

                    <!-- Shareable Cloud Document Direct Access Banner (Google Docs / Word Online) -->
                    <?php if(!empty($active_sub['shareable_link'])): 
                        $linkUrl = $active_sub['shareable_link'];
                        $is_gdoc = (strpos($linkUrl, 'docs.google.com') !== false || strpos($linkUrl, 'drive.google.com') !== false);
                        $is_word_online = (strpos($linkUrl, 'office.com') !== false || strpos($linkUrl, 'onedrive') !== false || strpos($linkUrl, 'sharepoint.com') !== false);
                        $platform_name = $is_gdoc ? 'Google Docs' : ($is_word_online ? 'Microsoft Word Online' : 'Cloud Document');
                        $platform_icon = $is_gdoc ? 'fa-file-lines' : ($is_word_online ? 'fa-file-word' : 'fa-link');
                    ?>
                        <div style="background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%); border: 2px solid #93C5FD; border-radius: 12px; padding: 1.15rem 1.35rem; margin-bottom: 1.35rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; box-shadow: 0 2px 4px rgba(37,99,235,0.08);">
                            <div style="display: flex; align-items: center; gap: 0.9rem; flex: 1; min-width: 250px;">
                                <div style="width: 48px; height: 48px; border-radius: 12px; background: #2563EB; color: white; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0; box-shadow: 0 4px 6px -1px rgba(37,99,235,0.3);">
                                    <i class="fa-solid <?php echo $platform_icon; ?>"></i>
                                </div>
                                <div>
                                    <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 0.2rem;">
                                        <strong style="color: #1E3A8A; font-size: 1rem;">Shareable <?php echo $platform_name; ?> Document</strong>
                                        <span style="background: #2563EB; color: white; font-size: 0.68rem; font-weight: 800; padding: 0.15rem 0.55rem; border-radius: 999px; text-transform: uppercase; letter-spacing: 0.5px;">
                                            Live Cloud Document
                                        </span>
                                    </div>
                                    <p style="margin: 0; color: #1E40AF; font-size: 0.82rem; line-height: 1.4;">
                                        Open directly in your browser to inspect the manuscript, add comments, and suggest revisions in real time.
                                    </p>
                                </div>
                            </div>
                            <div>
                                <a href="<?php echo htmlspecialchars($linkUrl); ?>" target="_blank" rel="noopener noreferrer" class="btn" style="background: #2563EB; color: white; font-weight: 700; font-size: 0.9rem; padding: 0.65rem 1.35rem; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 0.55rem; box-shadow: 0 4px 6px -1px rgba(37,99,235,0.3); border: none;">
                                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Open in <?php echo $platform_name; ?>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Document File / Link Inspection Bar -->
                    <div style="background: white; border: 1px solid #CBD5E1; border-radius: 10px; padding: 0.85rem 1.15rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 1.5rem;">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <span style="font-size: 1.8rem; color: <?php echo $aiconColor; ?>;">
                                <i class="fa-solid <?php echo $aicon; ?>"></i>
                            </span>
                            <div>
                                <strong style="color: #0F172A; font-size: 0.92rem; display: block;">
                                    <?php echo htmlspecialchars($active_sub['file_name']); ?>
                                </strong>
                                <small style="color: #64748B; font-size: 0.76rem;">
                                    <?php if(!empty($active_sub['shareable_link']) && ($aext == 'gdoc' || $aext == 'link' || empty($active_sub['file_size']))): ?>
                                        Format: <strong>ONLINE DOCUMENT</strong> • Shareable Google Docs / Word Link
                                    <?php else: ?>
                                        Format: <strong><?php echo strtoupper($aext); ?></strong> • Size: <?php echo format_file_size_faculty($active_sub['file_size']); ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>

                        <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                            <?php if(!empty($active_sub['shareable_link'])): ?>
                                <a href="<?php echo htmlspecialchars($active_sub['shareable_link']); ?>" target="_blank" rel="noopener noreferrer" class="btn" style="background: #2563EB; color: white; padding: 0.5rem 1rem; border-radius: 8px; font-size: 0.85rem; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem; border: none; box-shadow: 0 1px 2px rgba(37,99,235,0.2);">
                                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Open Online Document
                                </a>
                            <?php endif; ?>

                            <?php if(!empty($active_sub['file_path']) && $active_sub['file_ext'] !== 'gdoc' && $active_sub['file_ext'] !== 'link' && !empty($active_sub['file_size'])): ?>
                                <a href="<?php echo htmlspecialchars($active_sub['file_path']); ?>" target="_blank" download class="btn-primary" style="padding: 0.5rem 1.15rem; font-size: 0.85rem; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem;">
                                    <i class="fa-solid fa-download"></i> Download Manuscript
                                </a>
                                <?php if($aext == 'pdf'): ?>
                                    <a href="<?php echo htmlspecialchars($active_sub['file_path']); ?>" target="_blank" class="btn" style="background: #F1F5F9; color: #1E293B; border: 1px solid #CBD5E1; padding: 0.5rem 0.85rem; border-radius: 8px; font-size: 0.85rem; text-decoration: none;" title="Open PDF in new tab">
                                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- ============================================================== -->
                    <!-- FACULTY EVALUATION & REVIEW ACTION FORM -->
                    <!-- ============================================================== -->
                    <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 12px; padding: 1.5rem;">
                        <div style="margin-bottom: 1.25rem;">
                            <h3 style="margin: 0 0 0.35rem 0; color: #0F172A; font-size: 1.1rem; font-weight: 800; display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fa-solid fa-clipboard-check" style="color: var(--csu-green);"></i> Faculty Evaluation & Review Decision
                            </h3>
                            <p style="margin: 0; color: #64748B; font-size: 0.82rem;">
                                Provide your written feedback, set the document status, and optionally upload your annotated file with Track Changes.
                            </p>
                        </div>

                        <form method="POST" action="action.php" enctype="multipart/form-data">
                            <input type="hidden" name="review_document_submission" value="1">
                            <input type="hidden" name="submission_id" value="<?php echo $active_sub['id']; ?>">

                            <!-- Decision Radios / Status Picker -->
                            <div class="form-group" style="margin-bottom: 1.25rem;">
                                <label style="font-weight: 700; color: #0F172A; display: block; margin-bottom: 0.5rem; font-size: 0.88rem;">
                                    Review Decision / Status <span style="color: #DC2626;">*</span>
                                </label>
                                
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.75rem;">
                                    
                                    <!-- Approved Option -->
                                    <label style="display: flex; align-items: flex-start; gap: 0.65rem; background: white; border: 2px solid <?php echo ($astat == 'approved') ? '#047857' : '#E2E8F0'; ?>; border-radius: 8px; padding: 0.85rem; cursor: pointer; transition: all 0.2s ease;">
                                        <input type="radio" name="review_status" value="approved" <?php echo ($astat == 'approved') ? 'checked' : ''; ?> style="margin-top: 0.2rem;" required>
                                        <div>
                                            <strong style="color: #047857; font-size: 0.88rem; display: block;"><i class="fa-solid fa-check-circle"></i> Approve Paper</strong>
                                            <small style="color: #64748B; font-size: 0.74rem;">Meets academic standards and rubric criteria.</small>
                                        </div>
                                    </label>

                                    <!-- Needs Revision Option -->
                                    <label style="display: flex; align-items: flex-start; gap: 0.65rem; background: white; border: 2px solid <?php echo ($astat == 'needs_revision' || $astat == 'pending') ? '#DC2626' : '#E2E8F0'; ?>; border-radius: 8px; padding: 0.85rem; cursor: pointer; transition: all 0.2s ease;">
                                        <input type="radio" name="review_status" value="needs_revision" <?php echo ($astat == 'needs_revision' || $astat == 'pending') ? 'checked' : ''; ?> style="margin-top: 0.2rem;" required>
                                        <div>
                                            <strong style="color: #DC2626; font-size: 0.88rem; display: block;"><i class="fa-solid fa-rotate-left"></i> Needs Revision</strong>
                                            <small style="color: #64748B; font-size: 0.74rem;">Student must revise and resubmit new draft.</small>
                                        </div>
                                    </label>

                                    <!-- Under Review Option -->
                                    <label style="display: flex; align-items: flex-start; gap: 0.65rem; background: white; border: 2px solid <?php echo ($astat == 'under_review') ? '#2563EB' : '#E2E8F0'; ?>; border-radius: 8px; padding: 0.85rem; cursor: pointer; transition: all 0.2s ease;">
                                        <input type="radio" name="review_status" value="under_review" <?php echo ($astat == 'under_review') ? 'checked' : ''; ?> style="margin-top: 0.2rem;" required>
                                        <div>
                                            <strong style="color: #2563EB; font-size: 0.88rem; display: block;"><i class="fa-solid fa-magnifying-glass"></i> Under Review</strong>
                                            <small style="color: #64748B; font-size: 0.74rem;">Checking in progress; notify student.</small>
                                        </div>
                                    </label>

                                </div>
                            </div>

                            <!-- Written Feedback Textarea -->
                            <div class="form-group" style="margin-bottom: 1.25rem;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
                                    <label style="font-weight: 700; color: #0F172A; font-size: 0.88rem;">
                                        Adviser Feedback & Revision Instructions <span style="color: #DC2626;">*</span>
                                    </label>
                                    <div style="display: flex; gap: 0.35rem;">
                                        <button type="button" onclick="insertFeedbackTemplate('Methodology & system architecture clear. Approved to proceed to next chapter.')" class="btn" style="padding: 0.2rem 0.5rem; font-size: 0.72rem; background: #ECFDF5; color: #047857; border: 1px solid #A7F3D0; border-radius: 4px; cursor: pointer;">+ Approve Template</button>
                                        <button type="button" onclick="insertFeedbackTemplate('Please revise Chapter 2 to include more recent literature from the last 5 years. Update theoretical matrix and cite IEEE journals.')" class="btn" style="padding: 0.2rem 0.5rem; font-size: 0.72rem; background: #FFF7ED; color: #C2410C; border: 1px solid #FED7AA; border-radius: 4px; cursor: pointer;">+ Revision Template</button>
                                    </div>
                                </div>
                                <textarea name="feedback" id="feedbackTextarea" rows="6" required 
                                          placeholder="Detail your findings, required corrections, citation checks, formatting issues, or praise for well-structured sections..."
                                          style="width: 100%; padding: 0.75rem 0.85rem; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 0.88rem; font-family: 'Inter', sans-serif; line-height: 1.5; background: white;"><?php echo htmlspecialchars($active_sub['feedback'] ?? ''); ?></textarea>
                            </div>

                            <!-- Optional Annotated Document Attachment -->
                            <div class="form-group" style="margin-bottom: 1.5rem;">
                                <label style="font-weight: 700; color: #0F172A; display: block; margin-bottom: 0.4rem; font-size: 0.88rem;">
                                    Return Annotated / Marked File <span style="font-size: 0.78rem; color: #64748B; font-weight: 400;">(Optional Track Changes / PDF Comments)</span>
                                </label>
                                
                                <div style="background: white; border: 1px solid #CBD5E1; border-radius: 8px; padding: 0.75rem 1rem;">
                                    <?php if(!empty($active_sub['reviewed_file_path'])): ?>
                                        <p style="margin: 0 0 0.5rem 0; font-size: 0.82rem; color: #047857; font-weight: 600;">
                                            <i class="fa-solid fa-circle-check"></i> Current Annotated File: 
                                            <a href="<?php echo htmlspecialchars($active_sub['reviewed_file_path']); ?>" target="_blank" style="color: #0284C7; text-decoration: underline;">
                                                <?php echo htmlspecialchars($active_sub['reviewed_file_name'] ?: 'Reviewed_Draft.docx'); ?>
                                            </a>
                                        </p>
                                    <?php endif; ?>
                                    <input type="file" name="reviewed_file" accept=".pdf,.doc,.docx" 
                                           style="width: 100%; font-size: 0.82rem; color: #334155;">
                                    <small style="color: #64748B; font-size: 0.75rem; display: block; margin-top: 0.35rem;">
                                        Upload your Word document with Track Changes or highlighted PDF comments for the advisee to download.
                                    </small>
                                </div>
                            </div>

                            <!-- SUBMIT REVIEW BUTTON -->
                            <div style="display: flex; gap: 0.75rem; align-items: center;">
                                <button type="submit" class="btn-primary" style="padding: 0.7rem 1.75rem; font-size: 0.92rem; font-weight: 700; border-radius: 8px; display: inline-flex; align-items: center; gap: 0.45rem;">
                                    <i class="fa-solid fa-paper-plane"></i> Record & Send Review Decision
                                </button>
                                <?php if(!empty($active_sub['reviewed_at'])): ?>
                                    <small style="color: #64748B; font-size: 0.78rem;">
                                        <i class="fa-solid fa-check"></i> Previously evaluated on <?php echo date('M d, Y', strtotime($active_sub['reviewed_at'])); ?>
                                    </small>
                                <?php endif; ?>
                            </div>

                        </form>
                    </div>

                </div>
            </div>

        <?php else: ?>
            <div class="card" style="text-align: center; padding: 4rem 2rem; color: #64748B;">
                <i class="fa-solid fa-file-circle-question fa-3x" style="color: #CBD5E1; margin-bottom: 1rem; display: block;"></i>
                <h3 style="color: #0F172A; font-size: 1.15rem; margin-bottom: 0.35rem;">No Document Selected</h3>
                <p style="margin: 0; font-size: 0.88rem; max-width: 420px; margin-left: auto; margin-right: auto;">
                    Select an advisee or click on any document in the Submissions Review Queue to inspect and evaluate their manuscript.
                </p>
            </div>
        <?php endif; ?>

    </div>
</div>

<script>
function insertFeedbackTemplate(text) {
    const ta = document.getElementById('feedbackTextarea');
    if (!ta) return;
    if (ta.value.trim() === '') {
        ta.value = text;
    } else {
        ta.value = ta.value + "\n\n" + text;
    }
    ta.focus();
}

function filterFacultySubmissions(status) {
    const items = document.querySelectorAll('.fac-sub-item');
    items.forEach(item => {
        if (status === 'all' || item.getAttribute('data-status') === status) {
            item.style.display = 'block';
        } else {
            item.style.display = 'none';
        }
    });

    document.querySelectorAll('.btn-filter-fac').forEach(btn => {
        btn.style.opacity = '0.7';
    });
    if (status === 'all') document.getElementById('facBtnAll').style.opacity = '1';
    if (status === 'pending') document.getElementById('facBtnPending').style.opacity = '1';
    if (status === 'needs_revision') document.getElementById('facBtnRevision').style.opacity = '1';
    if (status === 'approved') document.getElementById('facBtnApproved').style.opacity = '1';
}
</script>
