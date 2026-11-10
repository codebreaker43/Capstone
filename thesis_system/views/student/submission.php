<?php
// 1. Check if student has selected an adviser (accepted or pending approval)
$group_match_clause = !empty($profile['group_id']) ? " OR m.group_id = " . (int)$profile['group_id'] : "";
$selected_adv_req = $conn->query("SELECT m.*, u.name as adv_name, u.email as adv_email, ap.department, ap.expertise, ap.capacity 
    FROM match_request m 
    JOIN user u ON m.adviser_id = u.id 
    LEFT JOIN adviser_profile ap ON u.id = ap.user_id 
    WHERE (m.student_id = $uid $group_match_clause) AND m.status IN ('accepted', 'pending') 
    ORDER BY (m.status = 'accepted') DESC, m.id DESC 
    LIMIT 1")->fetch_assoc();

$has_selected_adviser = !empty($selected_adv_req);
$chosen_adviser_name = $has_selected_adviser ? $selected_adv_req['adv_name'] : '';

// 2. Fetch available faculty advisers and rank by specialization match
$available_advisers = [];
if (!$has_selected_adviser) {
    $student_topic = trim($profile['thesis_topic'] ?? '');
    $student_skills = trim($profile['skills'] ?? '');
    $student_dept = trim($profile['department'] ?? '');
    $student_doc = trim($student_skills . " " . $student_topic);

    $has_student_input = (!empty($student_topic) || !empty($student_skills));
    $where_dept = "";
    if (!$has_student_input && !empty($student_dept)) {
        $esc_d = $conn->real_escape_string($student_dept);
        $clean_d = $conn->real_escape_string(preg_replace('/\s*\(.*?\)/', '', $student_dept));
        $where_dept = " AND (ap.department = '$esc_d' OR ap.department = '$clean_d' OR ap.department LIKE '%$clean_d%')";
    }

    $fac_q = $conn->query("SELECT u.id, u.name, u.email, ap.expertise, ap.department, ap.capacity 
        FROM user u 
        LEFT JOIN adviser_profile ap ON u.id = ap.user_id 
        WHERE u.role = 'adviser' AND u.account_status = 'active' $where_dept
        ORDER BY u.name ASC");

    if ($fac_q) {
        while ($fa = $fac_q->fetch_assoc()) {
            $adv_exp = trim($fa['expertise'] ?? '');
            $match_res = calculate_specialization_match($student_topic, $student_skills, $adv_exp, (int)$fa['id'], $fa['department'] ?? '', $student_dept);
            
            // Strict exclusion: if research title/keywords provided, only show advisers matching the area of specialization
            if ($has_student_input && !$match_res['matched']) {
                continue;
            }

            $fa['match_score'] = $match_res['score'];
            $fa['matched_terms'] = $match_res['matched_terms'];
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
}

// 3. Fetch all document submissions for this student / group
$sub_group_sql = !empty($group_id) ? "(ds.group_id = $group_id OR ds.student_id IN ($group_student_ids_str))" : "ds.student_id = $uid";
$submissions_res = $conn->query("SELECT ds.*, 
    u.name as reviewer_name, 
    u.email as reviewer_email,
    stu.name as submitter_name,
    sp.is_group_leader as submitter_is_leader
    FROM document_submissions ds 
    LEFT JOIN user u ON ds.reviewed_by = u.id 
    LEFT JOIN user stu ON ds.student_id = stu.id 
    LEFT JOIN student_profile sp ON ds.student_id = sp.user_id 
    WHERE $sub_group_sql 
    ORDER BY ds.id DESC");

$submissions = [];
$counts = [
    'total' => 0,
    'pending' => 0,
    'under_review' => 0,
    'needs_revision' => 0,
    'approved' => 0
];

if ($submissions_res) {
    while ($sub = $submissions_res->fetch_assoc()) {
        $submissions[] = $sub;
        $counts['total']++;
        if (isset($counts[$sub['status']])) {
            $counts[$sub['status']]++;
        }
    }
}

if (!function_exists('format_file_size')) {
    function format_file_size($bytes) {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        } elseif ($bytes > 0) {
            return $bytes . ' B';
        }
        return 'N/A';
    }
}
?>

<!-- PAGE BANNER & HEADER -->
<div class="banner" style="background: linear-gradient(135deg, var(--csu-green-darker) 0%, var(--csu-green-dark) 55%, var(--csu-green) 100%); border-radius: 10px; padding: 0.85rem 1.4rem; color: white; margin-bottom: 1.15rem; box-shadow: var(--shadow-sm); position: relative; overflow: hidden;">
    <div style="position: absolute; right: -10px; bottom: -15px; opacity: 0.08; font-size: 5.5rem; pointer-events: none;">
        <i class="fa-solid fa-file-circle-check"></i>
    </div>
    <div style="position: relative; z-index: 1;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.85rem;">
            <div>
                <span class="badge" style="background: rgba(245, 158, 11, 0.2); color: #FCD34D; border: 1px solid rgba(245, 158, 11, 0.35); padding: 0.2rem 0.6rem; border-radius: 999px; font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; display: inline-flex; align-items: center; gap: 0.35rem; margin-bottom: 0.35rem;">
                    <i class="fa-solid fa-graduation-cap"></i> Capstone Hub
                </span>
                <h1 style="font-size: 1.25rem; font-weight: 800; margin: 0 0 0.25rem 0; color: #FFFFFF; font-family: 'Outfit', sans-serif; line-height: 1.25;">
                    Document & Paper Review Portal
                </h1>
                <p style="margin: 0; color: #A7F3D0; font-size: 0.82rem; max-width: 680px; line-height: 1.4;">
                    Submit your capstone proposals, chapter drafts, full manuscripts, and similarity reports for comprehensive evaluation, line-by-line checking, and approval by your capstone adviser.
                </p>

                <!-- Chosen Adviser Banner Pill if selected -->
                <?php if($has_selected_adviser): ?>
                    <div style="margin-top: 0.5rem; display: inline-flex; align-items: center; gap: 0.5rem; background: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.3); border-radius: 7px; padding: 0.3rem 0.75rem; font-size: 0.8rem;">
                        <i class="fa-solid fa-user-check" style="color: #FCD34D;"></i>
                        <span>Chosen Capstone Adviser: <strong style="color: #FFFFFF; font-weight: 800;"><?php echo htmlspecialchars($chosen_adviser_name); ?></strong></span>
                        <?php if($selected_adv_req['status'] == 'accepted'): ?>
                            <span style="background: #D1FAE5; color: #065F46; padding: 1px 6px; border-radius: 999px; font-size: 0.7rem; font-weight: 700;">Confirmed</span>
                        <?php else: ?>
                            <span style="background: #FEF3C7; color: #92400E; padding: 1px 6px; border-radius: 999px; font-size: 0.7rem; font-weight: 700;">Pending Approval</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            </div>
            
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
                <?php 
                $hdr_title_stat = $profile['title_status'] ?? 'pending';
                if (!empty($my_group) && ($my_group['title_status'] ?? '') === 'approved') {
                    $hdr_title_stat = 'approved';
                }
                $hdr_is_approved = ($has_selected_adviser && ($selected_adv_req['status'] == 'accepted') && ($hdr_title_stat == 'approved'));
                ?>
                <?php if($hdr_is_approved): ?>
                    <button type="button" onclick="scrollToUploadForm()" class="btn" style="background: #F59E0B; color: #022C22; font-weight: 700; border: none; padding: 0.45rem 0.95rem; border-radius: 7px; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 0.4rem; box-shadow: 0 2px 5px rgba(0,0,0,0.12); cursor: pointer; transition: all 0.2s ease;">
                        <i class="fa-solid fa-cloud-arrow-up"></i> Submit New Document
                    </button>
                <?php elseif($has_selected_adviser): ?>
                    <button type="button" onclick="scrollToUploadForm()" class="btn" style="background: rgba(245, 158, 11, 0.22); color: #FCD34D; border: 1px solid rgba(245, 158, 11, 0.35); font-weight: 700; padding: 0.45rem 0.95rem; border-radius: 7px; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 0.4rem; cursor: pointer; transition: all 0.2s ease;">
                        <i class="fa-solid fa-lock"></i> Waiting for Title Approval
                    </button>
                <?php else: ?>
                    <button type="button" onclick="openAIModalWithLiveUpdate()" class="btn" style="background: #F59E0B; color: #022C22; font-weight: 700; border: none; padding: 0.45rem 0.95rem; border-radius: 7px; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 0.4rem; box-shadow: 0 2px 5px rgba(0,0,0,0.12); cursor: pointer; transition: all 0.2s ease;">
                        <i class="fa-solid fa-user-plus"></i> Select Adviser First
                    </button>
                <?php endif; ?>
                <a href="?tab=group" class="btn" style="background: rgba(255,255,255,0.12); color: white; border: 1px solid rgba(255,255,255,0.25); padding: 0.45rem 0.85rem; border-radius: 7px; font-size: 0.8rem; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem; transition: all 0.2s ease;">
                    <i class="fa-solid fa-users"></i> Capstone Group
                </a>
            </div>
        </div>
    </div>
</div>

<!-- PANEL MANUSCRIPT SUBMISSION QUICK CALLOUT -->
<?php 
$sub_m_file = !empty($profile['manuscript_file']) ? $profile['manuscript_file'] : (!empty($my_group['manuscript_file']) ? $my_group['manuscript_file'] : '');
$sub_m_link = !empty($profile['shareable_link']) ? $profile['shareable_link'] : (!empty($my_group['shareable_link']) ? $my_group['shareable_link'] : '');
$sub_has_m = (!empty($sub_m_file) || !empty($sub_m_link));
?>
<div class="card" style="margin-bottom: 1.15rem; background: linear-gradient(135deg, #F0FDF4 0%, #ECFDF5 100%); border: 1px solid #A7F3D0; box-shadow: var(--shadow-sm); padding: 0.85rem 1.25rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.85rem;">
        <div style="display: flex; align-items: center; gap: 0.75rem; flex: 1; min-width: 280px;">
            <div style="width: 40px; height: 40px; border-radius: 8px; background: #047857; color: white; display: flex; align-items: center; justify-content: center; font-size: 1.15rem; flex-shrink: 0; box-shadow: 0 2px 4px rgba(4,120,87,0.25);">
                <i class="fa-solid fa-users-viewfinder"></i>
            </div>
            <div>
                <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                    <span style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #047857;">Oral Defense Panel Committee Review</span>
                    <?php if($sub_has_m): ?>
                        <span style="background: #D1FAE5; color: #065F46; border: 1px solid #6EE7B7; border-radius: 999px; font-size: 0.68rem; font-weight: 700; padding: 1px 7px;">
                            <i class="fa-solid fa-circle-check"></i> Manuscript Ready for Panel
                        </span>
                    <?php else: ?>
                        <span style="background: #FEF3C7; color: #92400E; border: 1px solid #FDE68A; border-radius: 999px; font-size: 0.68rem; font-weight: 700; padding: 1px 7px;">
                            <i class="fa-solid fa-clock"></i> No Panel Manuscript Yet
                        </span>
                    <?php endif; ?>
                </div>
                <div style="font-size: 0.82rem; color: #334155; margin-top: 2px;">
                    Provide your manuscript file (PDF/Word) or live Google Docs link specifically so your assigned <strong>Panel Members</strong> can inspect and evaluate your oral defense.
                </div>
            </div>
        </div>
        <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
            <?php if(!empty($sub_m_file)): ?>
                <a href="<?php echo htmlspecialchars($sub_m_file); ?>" target="_blank" class="btn" style="background: #FFFFFF; color: #047857; border: 1px solid #A7F3D0; font-size: 0.8rem; font-weight: 600; padding: 0.4rem 0.8rem; text-decoration: none; border-radius: 6px; display: inline-flex; align-items: center; gap: 0.35rem;">
                    <i class="fa-solid fa-file-pdf"></i> View Current PDF
                </a>
            <?php endif; ?>
            <?php if(!empty($sub_m_link)): ?>
                <a href="<?php echo htmlspecialchars($sub_m_link); ?>" target="_blank" rel="noopener noreferrer" class="btn" style="background: #FFFFFF; color: #1D4ED8; border: 1px solid #BFDBFE; font-size: 0.8rem; font-weight: 600; padding: 0.4rem 0.8rem; text-decoration: none; border-radius: 6px; display: inline-flex; align-items: center; gap: 0.35rem;">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Open Live Doc
                </a>
            <?php endif; ?>
            <a href="?tab=panelists#manuscript-section" class="btn" style="background: #047857; color: white; border: none; font-size: 0.8rem; font-weight: 700; padding: 0.4rem 0.9rem; text-decoration: none; border-radius: 6px; display: inline-flex; align-items: center; gap: 0.4rem; box-shadow: 0 2px 4px rgba(4,120,87,0.2);">
                <i class="fa-solid fa-file-arrow-up"></i> <?php echo $sub_has_m ? 'Update Panel Manuscript' : 'Submit Manuscript for Panel'; ?>
            </a>
        </div>
    </div>
</div>

<!-- ============================================================== -->
<!-- TOP INFO SECTION: ASSIGNED CAPSTONE REVIEWER & CAPSTONE CO-AUTHORS -->
<!-- ============================================================== -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
    
    <!-- ASSIGNED CAPSTONE REVIEWER CARD -->
    <div class="card" style="margin-bottom: 0; border: 1px solid <?php echo $has_selected_adviser ? '#A7F3D0' : '#FECACA'; ?>; background: <?php echo $has_selected_adviser ? '#F0FDF4' : '#FFF5F5'; ?>;">
        <div style="padding: 1.15rem 1.25rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.65rem;">
                <span style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: <?php echo $has_selected_adviser ? '#065F46' : '#991B1B'; ?>; display: flex; align-items: center; gap: 0.4rem;">
                    <i class="fa-solid fa-user-tie"></i> Assigned Capstone Reviewer
                </span>
                <?php if($has_selected_adviser): ?>
                    <?php if($selected_adv_req['status'] == 'accepted'): ?>
                        <span class="badge badge-success" style="font-size: 0.72rem; padding: 0.2rem 0.55rem;">
                            <i class="fa-solid fa-circle-check"></i> Official Adviser
                        </span>
                    <?php else: ?>
                        <span class="badge badge-warning" style="font-size: 0.72rem; padding: 0.2rem 0.55rem;">
                            <i class="fa-regular fa-clock"></i> Awaiting Approval
                        </span>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="badge badge-danger" style="font-size: 0.72rem; padding: 0.2rem 0.55rem;">
                        <i class="fa-solid fa-circle-xmark"></i> Not Assigned
                    </span>
                <?php endif; ?>
            </div>

            <?php if($has_selected_adviser): ?>
                <div style="display: flex; align-items: center; gap: 0.9rem;">
                    <div style="width: 48px; height: 48px; border-radius: 50%; background: var(--csu-green); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; font-weight: 800; flex-shrink: 0; box-shadow: 0 2px 6px rgba(4,120,87,0.3);">
                        <?php echo strtoupper(substr($chosen_adviser_name, 0, 1)); ?>
                    </div>
                    <div style="overflow: hidden; flex: 1;">
                        <div style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: #047857; letter-spacing: 0.5px;">
                            <?php echo ($selected_adv_req['status'] == 'accepted') ? 'Official Capstone Adviser' : 'Chosen Adviser (Pending Approval)'; ?>
                        </div>
                        <h4 style="margin: 0.15rem 0; color: #0F172A; font-size: 1.15rem; font-weight: 800; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            <?php echo htmlspecialchars($chosen_adviser_name); ?>
                        </h4>
                        <p style="margin: 0; color: #475569; font-size: 0.8rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            <i class="fa-solid fa-envelope" style="font-size: 0.75rem; margin-right: 0.25rem;"></i> <?php echo htmlspecialchars($selected_adv_req['adv_email']); ?>
                            <?php if(!empty($selected_adv_req['department'])): ?>
                                • <strong style="color: #047857;"><?php echo htmlspecialchars($selected_adv_req['department']); ?></strong>
                            <?php endif; ?>
                        </p>
                    </div>
                    <?php if($selected_adv_req['status'] == 'pending'): ?>
                        <a href="#" 
                           onclick="openCancelAdviserModal('<?php echo addslashes(htmlspecialchars($selected_adv_req['adv_name'])); ?>', <?php echo $selected_adv_req['id']; ?>, 'submission'); return false;" 
                           class="btn" 
                           title="Cancel and choose another adviser"
                           style="padding: 0.35rem 0.65rem; font-size: 0.75rem; background: #FFF1F2; color: #E11D48; border: 1px solid #FECDD3; border-radius: 6px; text-decoration: none; white-space: nowrap; display: inline-flex; align-items: center; gap: 0.3rem;">
                            <i class="fa-solid fa-rotate-left"></i> Change
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div style="display: flex; justify-content: space-between; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                    <p style="margin: 0; color: #991B1B; font-size: 0.84rem; line-height: 1.4; flex: 1;">
                        <strong>No adviser selected yet.</strong> You must select an adviser before paper submission features are unlocked.
                    </p>
                    <button type="button" onclick="openAIModalWithLiveUpdate()" class="btn-primary" style="padding: 0.4rem 0.85rem; font-size: 0.78rem; text-decoration: none; white-space: nowrap; border-radius: 6px;">
                        <i class="fa-solid fa-wand-magic-sparkles"></i> Select Adviser
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- CAPSTONE GROUP CO-AUTHORS -->
    <div class="card" style="margin-bottom: 0; border: 1px solid #E2E8F0;">
        <div style="padding: 1.15rem 1.25rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.65rem;">
                <span style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #475569; display: flex; align-items: center; gap: 0.4rem;">
                    <i class="fa-solid fa-users" style="color: var(--csu-green);"></i> Capstone Co-Authors
                </span>
                <?php if($my_group): ?>
                    <span class="badge <?php echo count($my_group_members) >= 3 ? 'badge-success' : 'badge-warning'; ?>" style="font-size: 0.72rem; padding: 0.2rem 0.55rem;">
                        <?php echo htmlspecialchars($my_group['group_name']); ?> • <?php echo count($my_group_members); ?>/3
                    </span>
                <?php else: ?>
                    <span class="badge badge-danger" style="font-size: 0.72rem; padding: 0.2rem 0.55rem;">Individual</span>
                <?php endif; ?>
            </div>

            <?php if($my_group): ?>
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <?php for($i = 0; $i < 3; $i++): $gm = $my_group_members[$i] ?? null; ?>
                        <div style="flex: 1; min-width: 140px; background: #F8FAFC; border: 1px solid <?php echo $gm ? '#CBD5E1' : '#E2E8F0'; ?>; border-radius: 6px; padding: 0.4rem 0.6rem; display: flex; align-items: center; gap: 0.45rem; font-size: 0.78rem;">
                            <?php if($gm): ?>
                                <span style="color: <?php echo $gm['is_leader'] ? '#D97706' : '#047857'; ?>;">
                                    <i class="fa-solid <?php echo $gm['is_leader'] ? 'fa-crown' : 'fa-user'; ?>"></i>
                                </span>
                                <div style="overflow: hidden; line-height: 1.2;">
                                    <strong style="color: #0F172A; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                        <?php echo htmlspecialchars($gm['name']); ?>
                                    </strong>
                                    <small style="color: #64748B; font-size: 0.68rem;"><?php echo $gm['is_leader'] ? 'Leader' : 'Member '.($i+1); ?></small>
                                </div>
                            <?php else: ?>
                                <span style="color: #94A3B8;"><i class="fa-regular fa-circle"></i></span>
                                <span style="color: #94A3B8; font-style: italic; font-size: 0.74rem;">Vacant Slot <?php echo $i+1; ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endfor; ?>
                </div>
            <?php else: ?>
                <div style="display: flex; justify-content: space-between; align-items: center; gap: 0.75rem;">
                    <p style="margin: 0; color: #64748B; font-size: 0.82rem; line-height: 1.4;">
                        You have not registered a 3-member capstone group yet.
                    </p>
                    <a href="?tab=group" class="btn" style="padding: 0.4rem 0.75rem; font-size: 0.78rem; background: #ECFDF5; color: #047857; border: 1px solid #A7F3D0; border-radius: 6px; text-decoration: none; white-space: nowrap;">
                        <i class="fa-solid fa-users-plus"></i> Form Group
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ============================================================== -->
<!-- CONDITIONAL GATEKEEPER: HAS THE STUDENT SELECTED AN ADVISER? -->
<!-- ============================================================== -->

<?php if(!$has_selected_adviser): ?>

    <!-- ============================================================== -->
    <!-- STEP 1: PROPOSED CAPSTONE TITLE & AI ADVISER MATCHMAKING -->
    <!-- ============================================================== -->
    <div class="card" style="border-top: 4px solid var(--accent-yellow); margin-bottom: 2rem; box-shadow: var(--shadow-md);">
        <div class="card-body" style="padding: 1.75rem 2rem;">
            
            <!-- STAGE STEPPER HEADER -->
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid #E2E8F0;">
                <div>
                    <span class="badge" style="background: #FEF3C7; color: #92400E; border: 1px solid #FDE68A; padding: 0.3rem 0.75rem; border-radius: 999px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; display: inline-flex; align-items: center; gap: 0.4rem; margin-bottom: 0.4rem;">
                        <i class="fa-solid fa-flag-checkered"></i> Step 1 of 2: Proposed Title & Adviser Matching
                    </span>
                    <h2 style="font-size: 1.45rem; font-weight: 800; color: #0F172A; margin: 0; font-family: 'Outfit', sans-serif;">
                        Submit Your Capstone Title to Match an Adviser
                    </h2>
                    <p style="color: #64748B; font-size: 0.88rem; margin: 0.35rem 0 0 0; max-width: 760px; line-height: 1.5;">
                        Enter your proposed capstone title and concept below. Our CEIT AI matching engine will analyze your topic against faculty areas of specialization and recommend the most qualified capstone advisers.
                    </p>
                </div>
                <div style="background: #F8FAFC; border: 1px solid #CBD5E1; border-radius: 8px; padding: 0.5rem 0.9rem; font-size: 0.8rem; color: #475569; text-align: right;">
                    <div style="font-weight: 700; color: #0F172A;"><i class="fa-solid fa-shield-halved" style="color: #F59E0B;"></i> Step 2: Document Review Hub</div>
                    <small style="color: #94A3B8;">Unlocks as soon as you select an adviser</small>
                </div>
            </div>

            <!-- TITLE & CONCEPT INPUT FORM -->
            <?php $has_abstract_pdf = (!empty($profile['abstract_file']) && file_exists($profile['abstract_file'])); ?>
            <form method="POST" action="action.php" id="gatekeeperTitleForm" enctype="multipart/form-data" style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 12px; padding: 1.35rem; margin-bottom: 2rem;">
                <input type="hidden" name="save_thesis_title" value="1">
                <input type="hidden" name="return_tab" value="submission">
                <input type="hidden" name="show_ai" id="gatekeeper_show_ai" value="0">

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.25rem; margin-bottom: 1rem;">
                    
                    <!-- Title Input -->
                    <div>
                        <label style="font-weight: 700; color: #0F172A; display: block; margin-bottom: 0.4rem; font-size: 0.88rem;">
                            Proposed Capstone Title / Topic <span style="color: #DC2626;">*</span>
                        </label>
                        <input type="text" name="thesis_topic" id="gatekeeper_topic" 
                               value="<?php echo htmlspecialchars($profile['thesis_topic'] ?? ''); ?>" 
                               placeholder="e.g. IoT-Based Precision Smart Farm Water Quality System" 
                               required 
                               style="width: 100%; padding: 0.7rem 0.85rem; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 0.9rem; font-family: 'Inter', sans-serif;">
                        <small style="color: #64748B; font-size: 0.75rem; display: block; margin-top: 0.35rem;">
                            State your proposed capstone title. The AI reads this title to match faculty specialization.
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
                                <div id="gatekeeper_abstract_current" style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; margin-bottom: 0.5rem; background: #ECFDF5; border: 1px solid #A7F3D0; padding: 0.4rem 0.6rem; border-radius: 6px;">
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

                            <input type="file" name="abstract_file" id="gatekeeper_abstract_file" 
                                   accept=".pdf,application/pdf" 
                                   <?php echo !$has_abstract_pdf ? 'required' : ''; ?>
                                   onchange="handleAbstractPdfSelect(this, 'gatekeeper_abstract_status')" 
                                   style="width: 100%; font-size: 0.82rem; color: #475569; cursor: pointer;">
                            
                            <div id="gatekeeper_abstract_status" style="margin-top: 0.35rem; font-size: 0.74rem; color: #64748B; display: flex; align-items: center; gap: 0.35rem;">
                                <i class="fa-solid fa-circle-exclamation" style="color: #DC2626;"></i>
                                <span><strong>Strictly PDF (.pdf) format only.</strong> Concept PDF is strictly required before proceeding to capstone adviser matching.</span>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- ACTION BUTTONS -->
                <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
                    <button type="button" onclick="openAIModalWithLiveUpdate()" class="btn" style="padding: 0.65rem 1.35rem; font-size: 0.88rem; font-weight: 700; background: #FEF3C7; color: #92400E; border: 1px solid #FDE68A; border-radius: 8px; display: inline-flex; align-items: center; gap: 0.45rem; cursor: pointer; transition: all 0.2s ease;">
                        <i class="fa-solid fa-robot"></i> Open AI Matching Modal
                    </button>
                    <button type="submit" class="btn" style="padding: 0.65rem 1.25rem; font-size: 0.88rem; font-weight: 600; background: white; color: #1E293B; border: 1px solid #CBD5E1; border-radius: 8px; display: inline-flex; align-items: center; gap: 0.45rem;">
                        <i class="fa-solid fa-floppy-disk"></i> Save Title & Concept
                    </button>
                </div>
            </form>

        </div>
    </div>

    <!-- GATEKEEPER JAVASCRIPT FOR TITLE & LIVE ABSTRACT / MATCHING -->
    <script>
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
                    hint: 'Please select an authentic PDF (.pdf) document of your capstone concept.',
                    targetFocusId: 'gatekeeper_abstract_file',
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
                    targetFocusId: 'gatekeeper_abstract_file',
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
            statusEl.innerHTML = '<span style="color: #059669; font-weight: 700;"><i class="fa-solid fa-circle-check"></i> ' + file.name.replace(/</g, '&lt;') + ' (' + sizeKb + ' KB) &bull; PDF verified & ready</span>';
        }
        return true;
    }

    function openAIModalWithLiveUpdate() {
        const titleInput = document.getElementById('gatekeeper_topic');
        const fileInput = document.getElementById('gatekeeper_abstract_file');
        const form = document.getElementById('gatekeeperTitleForm');
        const hasExisting = <?php echo $has_abstract_pdf ? 'true' : 'false'; ?>;
        const title = titleInput ? titleInput.value.trim() : '';

        if (!title) {
            if (typeof window.showUniversityNotice === 'function') {
                window.showUniversityNotice({
                    heading: 'Academic Submission Notice',
                    pillText: 'Proposed Title Required',
                    title: 'Proposed Title Required',
                    message: 'Please enter your proposed capstone title before proceeding with faculty matchmaking.',
                    hint: 'The AI matching engine evaluates your capstone title to suggest the most qualified faculty advisers.',
                    targetFocusId: 'gatekeeper_topic',
                    primaryBtnText: 'Enter Proposed Title'
                });
            } else {
                alert('Proposed Title Required: Please enter your proposed capstone title before proceeding.');
            }
            if (titleInput) {
                titleInput.focus();
                titleInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
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
                    targetFocusId: 'gatekeeper_abstract_file',
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

        // If a new PDF is selected, automatically save and upload it so AI engine can extract and rank with it
        if (hasNewFile) {
            const showAiInput = document.getElementById('gatekeeper_show_ai');
            if (showAiInput) showAiInput.value = '1';
            if (form) {
                form.submit();
                return;
            }
        }

        // Has existing concept, open modal
        if (typeof openAIModal === 'function') {
            openAIModal(title);
        } else if (typeof window.openAIModal === 'function') {
            window.openAIModal(title);
        }
    }

    document.getElementById('gatekeeperTitleForm')?.addEventListener('submit', function(e) {
        const hasExisting = <?php echo $has_abstract_pdf ? 'true' : 'false'; ?>;
        const fileInput = document.getElementById('gatekeeper_abstract_file');
        const hasNewFile = fileInput && fileInput.files && fileInput.files.length > 0;
        if (!hasExisting && !hasNewFile) {
            e.preventDefault();
            if (typeof window.showUniversityNotice === 'function') {
                window.showUniversityNotice({
                    heading: 'Academic Requirement Notice',
                    pillText: 'Official Academic Compliance',
                    title: 'Capstone Concept PDF Required',
                    message: 'In compliance with CEIT academic policy, you cannot proceed without uploading your official Capstone Concept in PDF format (.pdf).',
                    hint: 'Please attach your Capstone Concept in PDF format (.pdf) before submitting your proposed title.',
                    targetFocusId: 'gatekeeper_abstract_file',
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

<?php else: ?>

    <!-- ============================================================== -->
    <!-- UNLOCKED: AN ADVISER HAS BEEN SELECTED! DISPLAY FEATURES -->
    <!-- ============================================================== -->

    <!-- STATS SUMMARY GRID -->
    <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
        <div class="stat-card" style="background: white; border: 1px solid #E2E8F0; border-radius: 12px; padding: 1.15rem 1.25rem; display: flex; align-items: center; gap: 1rem; box-shadow: var(--shadow-sm);">
            <div style="width: 48px; height: 48px; border-radius: 10px; background: #ECFDF5; color: #059669; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; flex-shrink: 0;">
                <i class="fa-solid fa-folder-open"></i>
            </div>
            <div>
                <div style="font-size: 1.55rem; font-weight: 800; color: #0F172A; line-height: 1;"><?php echo $counts['total']; ?></div>
                <div style="font-size: 0.8rem; color: #64748B; font-weight: 600; margin-top: 0.25rem;">Total Submissions</div>
            </div>
        </div>
        <div class="stat-card" style="background: white; border: 1px solid #FDE68A; border-radius: 12px; padding: 1.15rem 1.25rem; display: flex; align-items: center; gap: 1rem; box-shadow: var(--shadow-sm);">
            <div style="width: 48px; height: 48px; border-radius: 10px; background: #FEF3C7; color: #D97706; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; flex-shrink: 0;">
                <i class="fa-solid fa-clock-rotate-left"></i>
            </div>
            <div>
                <div style="font-size: 1.55rem; font-weight: 800; color: #B45309; line-height: 1;"><?php echo $counts['pending'] + $counts['under_review']; ?></div>
                <div style="font-size: 0.8rem; color: #92400E; font-weight: 600; margin-top: 0.25rem;">Awaiting Review</div>
            </div>
        </div>
        <div class="stat-card" style="background: white; border: 1px solid #FED7AA; border-radius: 12px; padding: 1.15rem 1.25rem; display: flex; align-items: center; gap: 1rem; box-shadow: var(--shadow-sm);">
            <div style="width: 48px; height: 48px; border-radius: 10px; background: #FFF7ED; color: #EA580C; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; flex-shrink: 0;">
                <i class="fa-solid fa-pen-to-square"></i>
            </div>
            <div>
                <div style="font-size: 1.55rem; font-weight: 800; color: #C2410C; line-height: 1;"><?php echo $counts['needs_revision']; ?></div>
                <div style="font-size: 0.8rem; color: #9A3412; font-weight: 600; margin-top: 0.25rem;">Needs Revision</div>
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
    <!-- SECTION 1: SUBMIT NEW DOCUMENT OR WAITING FOR APPROVAL -->
    <!-- ============================================================== -->
    <?php 
    $my_title_stat = $profile['title_status'] ?? 'pending';
    if (!empty($my_group) && ($my_group['title_status'] ?? '') === 'approved') {
        $my_title_stat = 'approved';
    }
    $is_match_accepted = ($selected_adv_req && $selected_adv_req['status'] === 'accepted');
    $is_title_approved = ($is_match_accepted && $my_title_stat === 'approved');
    ?>

    <?php if (!$is_title_approved): ?>
        <!-- LOCKED: WAITING FOR TITLE / ADVISER APPROVAL -->
        <div class="card" id="submissionLockedCard" style="border-top: 4px solid var(--csu-gold); margin-bottom: 2rem; background: white; box-shadow: var(--shadow-sm); border-radius: 12px; overflow: hidden;">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem; background: #FFFBEB; border-bottom: 1px solid #FEF3C7; padding: 1rem 1.25rem;">
                <div class="card-header-left" style="display: flex; align-items: center; gap: 0.65rem;">
                    <div style="width: 36px; height: 36px; border-radius: 8px; background: #FEF3C7; color: #D97706; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; border: 1px solid #FDE68A;">
                        <i class="fa-solid fa-lock"></i>
                    </div>
                    <div>
                        <h2 style="margin: 0; font-size: 1.05rem; color: #92400E; font-weight: 800;">Manuscript Upload Locked</h2>
                        <small style="color: #B45309; font-size: 0.78rem;">Waiting for Adviser Title Approval Before Document Submission</small>
                    </div>
                </div>
                <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                    <span style="font-size: 0.8rem; color: #047857; font-weight: 700; background: #ECFDF5; padding: 0.25rem 0.65rem; border-radius: 6px; border: 1px solid #A7F3D0;">
                        <i class="fa-solid fa-user-check"></i> Reviewer: <?php echo htmlspecialchars($chosen_adviser_name); ?>
                    </span>
                    <?php if ($my_title_stat === 'rejected'): ?>
                        <span style="font-size: 0.8rem; color: #DC2626; font-weight: 700; background: #FEE2E2; padding: 0.25rem 0.65rem; border-radius: 6px; border: 1px solid #FECACA; display: inline-flex; align-items: center; gap: 4px;">
                            <i class="fa-solid fa-triangle-exclamation"></i> Title Needs Revision
                        </span>
                    <?php else: ?>
                        <span style="font-size: 0.8rem; color: #B45309; font-weight: 700; background: #FEF3C7; padding: 0.25rem 0.65rem; border-radius: 6px; border: 1px solid #FDE68A; display: inline-flex; align-items: center; gap: 4px;">
                            <i class="fa-regular fa-clock"></i> Waiting for Approval
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card-body" style="padding: 2.25rem 1.75rem; text-align: center;">
                <div style="max-width: 640px; margin: 0 auto;">
                    
                    <div style="width: 76px; height: 76px; border-radius: 50%; background: #FFFBEB; color: #D97706; display: flex; align-items: center; justify-content: center; font-size: 2.2rem; margin: 0 auto 1.25rem auto; border: 2px solid #FDE68A; box-shadow: 0 4px 14px rgba(217, 119, 6, 0.12);">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                    </div>

                    <?php if(!$is_match_accepted): ?>
                        <h3 style="color: #0F172A; font-size: 1.25rem; font-weight: 800; margin-bottom: 0.45rem;">
                            Waiting for Adviser Acceptance & Title Approval
                        </h3>
                        <p style="color: #64748B; font-size: 0.92rem; line-height: 1.6; margin-bottom: 1.5rem;">
                            Your request to be advised by <strong><?php echo htmlspecialchars($chosen_adviser_name); ?></strong> is currently pending review. Once your adviser accepts your request and approves your proposed capstone title, manuscript uploading will automatically unlock.
                        </p>
                    <?php elseif($my_title_stat === 'rejected'): ?>
                        <h3 style="color: #991B1B; font-size: 1.25rem; font-weight: 800; margin-bottom: 0.45rem;">
                            Capstone Title Needs Revision
                        </h3>
                        <p style="color: #64748B; font-size: 0.92rem; line-height: 1.6; margin-bottom: 1.5rem;">
                            Your adviser <strong><?php echo htmlspecialchars($chosen_adviser_name); ?></strong> requested revisions to your proposed capstone title. Please revise and update your capstone title below so your adviser can re-evaluate and approve it.
                        </p>
                    <?php else: ?>
                        <h3 style="color: #0F172A; font-size: 1.25rem; font-weight: 800; margin-bottom: 0.45rem;">
                            Waiting for Adviser's Title Approval
                        </h3>
                        <p style="color: #64748B; font-size: 0.92rem; line-height: 1.6; margin-bottom: 1.5rem;">
                            <strong><?php echo htmlspecialchars($chosen_adviser_name); ?></strong> has accepted your match request! Your proposed title is now awaiting official vetting. Once your adviser officially approves your title, manuscript submission will unlock.
                        </p>
                    <?php endif; ?>

                    <!-- Current Proposed Title Display & Inline Editor -->
                    <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 12px; padding: 1.25rem; text-align: left; margin-bottom: 1.5rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; flex-wrap: wrap; gap: 0.35rem;">
                            <span style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #64748B; letter-spacing: 0.5px;">
                                <i class="fa-solid fa-graduation-cap" style="color: var(--csu-green);"></i> Proposed Capstone Title:
                            </span>
                            <button type="button" onclick="toggleEditProposedTitle()" class="btn" style="padding: 0.25rem 0.65rem; font-size: 0.74rem; background: white; border: 1px solid #CBD5E1; border-radius: 6px; color: var(--primary-blue); font-weight: 600; cursor: pointer;">
                                <i class="fa-solid fa-pen-to-square"></i> Edit Title
                            </button>
                        </div>
                        
                        <div id="displayProposedTitle" style="font-size: 1.05rem; font-weight: 700; color: #0F172A; line-height: 1.45;">
                            <?php echo !empty($profile['thesis_topic']) ? htmlspecialchars($profile['thesis_topic']) : '<span style="color: #94A3B8; font-style: italic;">No title submitted yet</span>'; ?>
                            <?php if(!empty($profile['abstract_file']) && file_exists($profile['abstract_file'])): ?>
                                <div style="margin-top: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                                    <a href="<?php echo htmlspecialchars($profile['abstract_file']); ?>" target="_blank" style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.78rem; font-weight: 600; color: #DC2626; background: #FEF2F2; border: 1px solid #FECACA; padding: 0.25rem 0.65rem; border-radius: 6px; text-decoration: none;">
                                        <i class="fa-solid fa-file-pdf"></i> <?php echo htmlspecialchars($profile['abstract_name'] ?? 'Concept.pdf'); ?> &bull; View Concept PDF
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Collapsible Title Editor -->
                        <form method="POST" action="action.php" id="editProposedTitleForm" enctype="multipart/form-data" style="display: none; margin-top: 0.85rem; padding-top: 0.85rem; border-top: 1px dashed #CBD5E1;">
                            <input type="hidden" name="save_thesis_title" value="1">
                            <input type="hidden" name="return_tab" value="submission">
                            <div class="form-group" style="margin-bottom: 0.75rem;">
                                <label style="font-size: 0.8rem; font-weight: 700; color: #334155; display: block; margin-bottom: 0.35rem;">
                                    Update Proposed Capstone Title:
                                </label>
                                <textarea name="thesis_topic" rows="2" required 
                                          style="width: 100%; padding: 0.55rem 0.75rem; border: 1px solid #94A3B8; border-radius: 6px; font-size: 0.88rem; font-family: 'Inter', sans-serif; line-height: 1.4;"><?php echo htmlspecialchars($profile['thesis_topic'] ?? ''); ?></textarea>
                            </div>
                            <div class="form-group" style="margin-bottom: 0.75rem;">
                                <label style="font-size: 0.8rem; font-weight: 700; color: #334155; display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.35rem;">
                                    <span><i class="fa-solid fa-file-pdf" style="color: #DC2626;"></i> Capstone Concept <span style="color: #DC2626;">*</span> (PDF Only):</span>
                                    <?php if(!empty($profile['abstract_file']) && file_exists($profile['abstract_file'])): ?>
                                        <a href="<?php echo htmlspecialchars($profile['abstract_file']); ?>" target="_blank" style="font-size: 0.74rem; color: #2563EB; font-weight: 600; text-decoration: none;">
                                            <i class="fa-solid fa-arrow-up-right-from-square"></i> Current PDF
                                        </a>
                                    <?php endif; ?>
                                </label>
                                <input type="file" name="abstract_file" id="edit_title_abstract_file" accept=".pdf,application/pdf"
                                       <?php echo (empty($profile['abstract_file']) || !file_exists($profile['abstract_file'])) ? 'required' : ''; ?>
                                       onchange="handleAbstractPdfSelect(this, 'edit_title_abstract_status')"
                                       style="width: 100%; padding: 0.4rem 0.6rem; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 0.82rem; background: white; cursor: pointer;">
                                <div id="edit_title_abstract_status" style="font-size: 0.72rem; color: #64748B; margin-top: 0.25rem;">
                                    Strictly PDF (.pdf) format only. Concept PDF is required.
                                </div>
                            </div>
                            <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                                <button type="button" onclick="toggleEditProposedTitle()" class="btn" style="padding: 0.35rem 0.85rem; font-size: 0.8rem; background: #F1F5F9; border: 1px solid #CBD5E1; border-radius: 6px;">
                                    Cancel
                                </button>
                                <button type="submit" class="btn-primary" style="padding: 0.35rem 1rem; font-size: 0.8rem; border-radius: 6px;">
                                    <i class="fa-solid fa-floppy-disk"></i> Save & Notify Adviser
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Steps Timeline / Tracker -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 0.85rem; text-align: left; margin-bottom: 1.5rem;">
                        <div style="background: #F0FDF4; border: 1px solid #BBF7D0; border-radius: 8px; padding: 0.75rem 0.85rem;">
                            <div style="font-size: 0.7rem; color: #166534; font-weight: 700; text-transform: uppercase; margin-bottom: 0.2rem;">Step 1</div>
                            <div style="font-weight: 700; color: #047857; font-size: 0.85rem; display: flex; align-items: center; gap: 4px;">
                                <i class="fa-solid fa-circle-check"></i> Choose Adviser
                            </div>
                            <small style="color: #15803D; font-size: 0.72rem; display: block; margin-top: 0.2rem;">
                                <?php echo htmlspecialchars($chosen_adviser_name); ?>
                            </small>
                        </div>

                        <div style="background: #FEF3C7; border: 1px solid #FDE68A; border-radius: 8px; padding: 0.75rem 0.85rem;">
                            <div style="font-size: 0.7rem; color: #92400E; font-weight: 700; text-transform: uppercase; margin-bottom: 0.2rem;">Step 2 (Current)</div>
                            <div style="font-weight: 700; color: #B45309; font-size: 0.85rem; display: flex; align-items: center; gap: 4px;">
                                <i class="fa-solid fa-clock"></i> Title Approval
                            </div>
                            <small style="color: #92400E; font-size: 0.72rem; display: block; margin-top: 0.2rem;">
                                Waiting for Adviser
                            </small>
                        </div>

                        <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 0.75rem 0.85rem; opacity: 0.75;">
                            <div style="font-size: 0.7rem; color: #64748B; font-weight: 700; text-transform: uppercase; margin-bottom: 0.2rem;">Step 3</div>
                            <div style="font-weight: 700; color: #64748B; font-size: 0.85rem; display: flex; align-items: center; gap: 4px;">
                                <i class="fa-solid fa-lock"></i> Submit Manuscript
                            </div>
                            <small style="color: #94A3B8; font-size: 0.72rem; display: block; margin-top: 0.2rem;">
                                Unlocks when Approved
                            </small>
                        </div>
                    </div>

                    <?php if(!$is_match_accepted && !empty($selected_adv_req['id'])): ?>
                        <div style="border-top: 1px solid #F1F5F9; padding-top: 1rem;">
                            <a href="#" 
                               onclick="openCancelAdviserModal('<?php echo addslashes(htmlspecialchars($chosen_adviser_name)); ?>', <?php echo $selected_adv_req['id']; ?>, 'submission'); return false;"
                               style="color: #EF4444; font-size: 0.82rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; cursor: pointer;">
                                <i class="fa-solid fa-xmark"></i> Cancel Request / Change Adviser
                            </a>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
        <script>
        function toggleEditProposedTitle() {
            var f = document.getElementById('editProposedTitleForm');
            if (!f) return;
            if (f.style.display === 'none' || f.style.display === '') {
                f.style.display = 'block';
            } else {
                f.style.display = 'none';
            }
        }
        </script>
    <?php else: ?>

        <!-- ============================================================== -->
        <!-- UNLOCKED: SUBMIT NEW DOCUMENT OR REVISION FORM -->
        <!-- ============================================================== -->
        <div class="card" id="submissionUploadCard" style="border-top: 4px solid var(--csu-green); margin-bottom: 2rem;">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                <div class="card-header-left">
                    <i class="fa-solid fa-cloud-arrow-up" style="color: var(--csu-green);"></i>
                    <h2 id="formCardHeading">Submit Document or Paper for Checking</h2>
                </div>
                <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                    <span style="font-size: 0.8rem; color: #047857; font-weight: 700; background: #ECFDF5; padding: 0.25rem 0.65rem; border-radius: 6px; border: 1px solid #A7F3D0;">
                        <i class="fa-solid fa-user-check"></i> Reviewer: <?php echo htmlspecialchars($chosen_adviser_name); ?>
                    </span>
                    <span style="font-size: 0.8rem; color: #047857; font-weight: 700; background: #D1FAE5; padding: 0.25rem 0.65rem; border-radius: 6px; border: 1px solid #A7F3D0; display: inline-flex; align-items: center; gap: 4px;">
                        <i class="fa-solid fa-circle-check"></i> Title Approved by Adviser
                    </span>
                    <button type="button" onclick="toggleAIOutlineModal()" class="btn" style="padding: 0.35rem 0.75rem; font-size: 0.8rem; background: #FFFBEB; color: #B45309; border: 1px solid #FDE68A; border-radius: 6px; cursor: pointer;">
                        <i class="fa-solid fa-wand-magic-sparkles"></i> AI Outline Helper
                    </button>
                </div>
            </div>
            <div class="card-body">
                
                <form method="POST" action="action.php" enctype="multipart/form-data" id="docSubmissionForm" onsubmit="return validateSubmissionForm()">
                    <input type="hidden" name="submit_document" value="1">
                    <input type="hidden" name="resubmit_id" id="resubmit_id" value="">

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem; margin-bottom: 1.25rem;">
                        
                        <!-- Document Title -->
                        <div class="form-group" style="margin-bottom: 0;">
                            <label style="font-weight: 700; color: #0F172A; display: block; margin-bottom: 0.4rem; font-size: 0.88rem;">
                                Document Title / Capstone Topic <span style="color: #DC2626;">*</span>
                            </label>
                            <input type="text" name="title" id="doc_title" 
                                   value="<?php echo htmlspecialchars($profile['thesis_topic'] ?? ''); ?>" 
                                   placeholder="e.g. IoT-Based Precision Smart Farm Water Quality System" 
                                   required 
                                   style="width: 100%; padding: 0.65rem 0.85rem; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 0.88rem; font-family: 'Inter', sans-serif;">
                            <small style="color: #64748B; font-size: 0.75rem; display: block; margin-top: 0.35rem;">
                                <span style="color: #047857; font-weight: 700;"><i class="fa-solid fa-circle-check"></i> Official capstone title has been approved by your adviser.</span>
                            </small>
                        </div>

                        <!-- Document Category / Stage -->
                        <div class="form-group" style="margin-bottom: 0;">
                            <label style="font-weight: 700; color: #0F172A; display: block; margin-bottom: 0.4rem; font-size: 0.88rem;">
                                Document Category / Stage <span style="color: #DC2626;">*</span>
                            </label>
                            <select name="doc_type" id="doc_type" required 
                                    style="width: 100%; padding: 0.65rem 0.85rem; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 0.88rem; font-family: 'Inter', sans-serif; background: white;">
                                <option value="Chapters 1-3: Proposal Defense Manuscript" selected>Chapters 1-3: Proposal Defense Manuscript</option>
                                <option value="Topic Proposal / Concept Paper">Topic Proposal / Concept Paper</option>
                                <option value="Chapter 1: Introduction">Chapter 1: Introduction & Problem Statement</option>
                                <option value="Chapter 2: Review of Related Literature (RRL)">Chapter 2: Review of Related Literature (RRL)</option>
                                <option value="Chapter 3: Methodology & Architecture">Chapter 3: Methodology & System Architecture</option>
                                <option value="Chapter 4: Results, Implementation & Testing">Chapter 4: Results, Implementation & Testing</option>
                                <option value="Chapter 5: Summary, Conclusions & Recommendations">Chapter 5: Summary, Conclusions & Recommendations</option>
                                <option value="Complete Final Manuscript (Chapters 1-5)">Complete Final Manuscript (Chapters 1-5)</option>
                                <option value="Plagiarism / Similarity Report (Turnitin)">Plagiarism / Similarity Report (Turnitin/DrillBit)</option>
                                <option value="Ethics Clearance & Survey Instruments">Ethics Clearance & Survey Instruments</option>
                                <option value="Revised Manuscript (Response to Comments)">Revised Manuscript (Response to Comments)</option>
                                <option value="Other Supporting Document">Other Supporting Document</option>
                            </select>
                            <small style="color: #64748B; font-size: 0.75rem; display: block; margin-top: 0.35rem;">
                                Categorize your document to route proper review rubrics.
                            </small>
                        </div>

                        <!-- Version Tag -->
                        <div class="form-group" style="margin-bottom: 0;">
                            <label style="font-weight: 700; color: #0F172A; display: block; margin-bottom: 0.4rem; font-size: 0.88rem;">
                                Version Tag
                            </label>
                            <div style="display: flex; gap: 0.5rem; align-items: center;">
                                <input type="text" name="version" id="doc_version" value="v1.0" maxlength="20"
                                       placeholder="e.g. v1.0, v1.1, v2.0"
                                       style="width: 110px; padding: 0.65rem 0.85rem; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 0.88rem; font-family: 'Inter', sans-serif; font-weight: 600; text-align: center;">
                                <div style="display: flex; gap: 0.35rem; flex-wrap: wrap;">
                                    <button type="button" onclick="setVersion('v1.0')" class="btn" style="padding: 0.35rem 0.55rem; font-size: 0.74rem; background: #F1F5F9; border: 1px solid #CBD5E1; border-radius: 6px; cursor: pointer;">v1.0</button>
                                    <button type="button" onclick="setVersion('v1.1')" class="btn" style="padding: 0.35rem 0.55rem; font-size: 0.74rem; background: #F1F5F9; border: 1px solid #CBD5E1; border-radius: 6px; cursor: pointer;">v1.1</button>
                                    <button type="button" onclick="setVersion('v2.0')" class="btn" style="padding: 0.35rem 0.55rem; font-size: 0.74rem; background: #F1F5F9; border: 1px solid #CBD5E1; border-radius: 6px; cursor: pointer;">v2.0</button>
                                    <button type="button" onclick="setVersion('Final-Draft')" class="btn" style="padding: 0.35rem 0.55rem; font-size: 0.74rem; background: #F1F5F9; border: 1px solid #CBD5E1; border-radius: 6px; cursor: pointer;">Final</button>
                                </div>
                            </div>
                            <small style="color: #64748B; font-size: 0.75rem; display: block; margin-top: 0.35rem;">
                                Use version tags to easily track iterative revisions.
                            </small>
                        </div>

                    </div>

                    <!-- SHAREABLE DOCUMENT LINK & WORD FILE UPLOAD AREA -->
                    <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 12px; padding: 1.25rem; margin-bottom: 1.35rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.85rem; flex-wrap: wrap; gap: 0.5rem;">
                            <div>
                                <h4 style="margin: 0; color: #0F172A; font-size: 0.98rem; font-weight: 800; display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fa-solid fa-link" style="color: #2563EB;"></i> Shareable Document Link
                                    <span style="background: #ECFDF5; color: #047857; font-size: 0.7rem; font-weight: 700; padding: 0.2rem 0.55rem; border-radius: 999px; border: 1px solid #A7F3D0;">
                                        Recommended
                                    </span>
                                </h4>
                                <p style="margin: 0.25rem 0 0 0; color: #64748B; font-size: 0.8rem;">
                                    Instead of a static PDF, paste a <strong>Google Docs</strong> or <strong>Microsoft Word Online / OneDrive</strong> link for quick viewing and inline annotations.
                                </p>
                            </div>
                            <span id="docTypeDetectedBadge" style="display: none; font-size: 0.74rem; font-weight: 700; padding: 0.25rem 0.65rem; border-radius: 999px;"></span>
                        </div>

                        <!-- Link Input Box -->
                        <div class="form-group" style="margin-bottom: 0.75rem;">
                            <div style="display: flex; gap: 0.5rem; align-items: center;">
                                <div style="position: relative; flex: 1;">
                                    <span id="linkInputIcon" style="position: absolute; left: 0.85rem; top: 50%; transform: translateY(-50%); color: #64748B; font-size: 1rem;">
                                        <i class="fa-solid fa-globe"></i>
                                    </span>
                                    <input type="url" name="shareable_link" id="shareable_link" 
                                           oninput="handleLinkInput(this.value)" 
                                           placeholder="https://docs.google.com/document/d/... or OneDrive/Word Online share link"
                                           style="width: 100%; padding: 0.7rem 0.85rem 0.7rem 2.5rem; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 0.88rem; font-family: 'Inter', sans-serif;">
                                </div>
                                <button type="button" onclick="testShareableLink()" class="btn" style="background: white; border: 1px solid #CBD5E1; color: #2563EB; font-weight: 600; font-size: 0.82rem; padding: 0.65rem 0.95rem; border-radius: 8px; white-space: nowrap; cursor: pointer; display: inline-flex; align-items: center; gap: 0.4rem;">
                                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Test Link
                                </button>
                            </div>
                            
                            <!-- Helpful sharing guide pill -->
                            <div style="background: #EFF6FF; border: 1px solid #BFDBFE; border-radius: 8px; padding: 0.65rem 0.85rem; margin-top: 0.65rem; font-size: 0.76rem; color: #1E40AF; line-height: 1.45; display: flex; align-items: flex-start; gap: 0.6rem;">
                                <i class="fa-solid fa-circle-info" style="margin-top: 0.15rem; flex-shrink: 0; font-size: 0.9rem;"></i>
                                <div>
                                    <strong>Sharing Tip:</strong> Ensure sharing permissions are set to <em>"Anyone with the link can view / comment"</em> so your adviser can open and critique without permission requests.
                                </div>
                            </div>
                        </div>

                        <!-- OR DIVIDER -->
                        <div style="display: flex; align-items: center; margin: 1.15rem 0; text-align: center;">
                            <div style="flex: 1; border-bottom: 1px dashed #CBD5E1;"></div>
                            <span style="padding: 0 0.85rem; color: #64748B; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">
                                OR UPLOAD WORD DOCUMENT (.DOCX, .DOC)
                            </span>
                            <div style="flex: 1; border-bottom: 1px dashed #CBD5E1;"></div>
                        </div>

                        <!-- DRAG AND DROP FILE UPLOAD AREA -->
                        <div id="dropZone" 
                             onclick="document.getElementById('document_file').click()" 
                             ondragover="handleDragOver(event)" 
                             ondragleave="handleDragLeave(event)" 
                             ondrop="handleDrop(event)"
                             style="border: 2px dashed #94A3B8; border-radius: 12px; background: white; padding: 1.75rem 1.5rem; text-align: center; cursor: pointer; transition: all 0.2s ease;">
                            
                            <input type="file" name="document_file" id="document_file" 
                                   accept=".docx,.doc,.pdf,.odt,.rtf,.zip,.rar" 
                                   onchange="handleFileSelect(event)" 
                                   style="display: none;">

                            <div id="dropZonePrompt">
                                <div style="width: 54px; height: 54px; border-radius: 50%; background: #EFF6FF; color: #2563EB; display: flex; align-items: center; justify-content: center; font-size: 1.75rem; margin: 0 auto 0.75rem auto;">
                                    <i class="fa-solid fa-file-word"></i>
                                </div>
                                <h4 style="margin: 0 0 0.35rem 0; color: #0F172A; font-size: 0.98rem; font-weight: 700;">
                                    Click to browse or drag and drop your Word document here
                                </h4>
                                <p style="margin: 0; color: #64748B; font-size: 0.8rem;">
                                    Preferred format: <strong>Word Document (.DOCX, .DOC)</strong>. Also accepted: PDF, ZIP (Maximum file size: 40MB)
                                </p>
                            </div>

                            <!-- File Selected Info Display -->
                            <div id="fileSelectedDisplay" style="display: none; align-items: center; justify-content: center; gap: 1rem; flex-wrap: wrap; background: #F8FAFC; padding: 0.85rem 1.15rem; border-radius: 8px; border: 1px solid #CBD5E1; max-width: 500px; margin: 0 auto;">
                                <div id="fileIconDisplay" style="font-size: 2rem; color: #2563EB;">
                                    <i class="fa-solid fa-file-word"></i>
                                </div>
                                <div style="text-align: left; overflow: hidden; flex: 1;">
                                    <strong id="selectedFileName" style="display: block; color: #0F172A; font-size: 0.88rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"></strong>
                                    <small id="selectedFileSize" style="color: #64748B; font-size: 0.76rem;"></small>
                                </div>
                                <button type="button" onclick="clearSelectedFile(event)" class="btn" style="background: #FEE2E2; color: #B91C1C; border: 1px solid #FECACA; padding: 0.3rem 0.65rem; border-radius: 6px; font-size: 0.78rem; cursor: pointer;" title="Remove File">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </div>

                        </div>
                    </div>

                    <!-- Student Remarks / Abstract / Summary of Changes -->
                    <div class="form-group" style="margin-bottom: 1.5rem;">
                        <label style="font-weight: 700; color: #0F172A; display: block; margin-bottom: 0.4rem; font-size: 0.88rem;">
                            Student Remarks, Abstract, or Summary of Revisions <span style="font-size: 0.78rem; color: #64748B; font-weight: 400;">(Optional but recommended)</span>
                        </label>
                        <textarea name="description" id="doc_description" rows="4" 
                                  placeholder="Provide a brief abstract, highlights of what this document contains, or specific questions/revisions for your adviser to review..."
                                  style="width: 100%; padding: 0.75rem 0.85rem; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 0.88rem; font-family: 'Inter', sans-serif; line-height: 1.5;"></textarea>
                        <small style="color: #64748B; font-size: 0.75rem; display: block; margin-top: 0.35rem;">
                            Let your adviser know which sections require attention or how you addressed prior feedback.
                        </small>
                    </div>

                    <!-- SUBMIT BUTTONS -->
                    <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
                        <button type="submit" id="submitBtn" class="btn-primary" style="padding: 0.7rem 1.65rem; font-size: 0.92rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.5rem; border-radius: 8px;">
                            <i class="fa-solid fa-paper-plane"></i> Submit Document for Review
                        </button>
                        <button type="button" onclick="resetFormToNew()" id="cancelResubmitBtn" class="btn" style="display: none; padding: 0.7rem 1.25rem; font-size: 0.88rem; background: #F1F5F9; color: #475569; border: 1px solid #CBD5E1; border-radius: 8px; cursor: pointer;">
                            <i class="fa-solid fa-xmark"></i> Cancel Revision Mode
                        </button>
                        <span id="submittingIndicator" style="display: none; color: var(--csu-green); font-size: 0.85rem; font-weight: 600;">
                            <i class="fa-solid fa-circle-notch fa-spin"></i> Uploading document... please wait.
                        </span>
                    </div>

                </form>

            </div>
        </div>
    <?php endif; ?>

    <!-- ============================================================== -->
    <!-- SECTION 2: SUBMITTED DOCUMENTS & ADVISER REVIEW HISTORY -->
    <!-- ============================================================== -->
    <div class="card" style="border-top: 4px solid var(--primary-blue);">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
            <div class="card-header-left">
                <i class="fa-solid fa-file-signature" style="color: var(--primary-blue);"></i>
                <h2>Submitted Documents & Review History</h2>
            </div>
            
            <!-- Filter Tabs -->
            <div style="display: flex; gap: 0.4rem; flex-wrap: wrap;">
                <button type="button" onclick="filterSubmissions('all')" id="btnFilterAll" class="btn-filter active-filter" style="padding: 0.35rem 0.75rem; font-size: 0.78rem; font-weight: 600; border-radius: 6px; cursor: pointer; border: 1px solid #CBD5E1; background: #047857; color: white;">
                    All (<?php echo $counts['total']; ?>)
                </button>
                <button type="button" onclick="filterSubmissions('pending')" id="btnFilterPending" class="btn-filter" style="padding: 0.35rem 0.75rem; font-size: 0.78rem; font-weight: 600; border-radius: 6px; cursor: pointer; border: 1px solid #FDE68A; background: #FEF3C7; color: #92400E;">
                    Pending (<?php echo $counts['pending'] + $counts['under_review']; ?>)
                </button>
                <button type="button" onclick="filterSubmissions('needs_revision')" id="btnFilterRevision" class="btn-filter" style="padding: 0.35rem 0.75rem; font-size: 0.78rem; font-weight: 600; border-radius: 6px; cursor: pointer; border: 1px solid #FED7AA; background: #FFF7ED; color: #C2410C;">
                    Needs Revision (<?php echo $counts['needs_revision']; ?>)
                </button>
                <button type="button" onclick="filterSubmissions('approved')" id="btnFilterApproved" class="btn-filter" style="padding: 0.35rem 0.75rem; font-size: 0.78rem; font-weight: 600; border-radius: 6px; cursor: pointer; border: 1px solid #A7F3D0; background: #ECFDF5; color: #047857;">
                    Approved (<?php echo $counts['approved']; ?>)
                </button>
            </div>
        </div>
        
        <div class="card-body">
            
            <?php if(empty($submissions)): ?>
                <div style="text-align: center; padding: 3.5rem 1.5rem; color: #64748B;">
                    <div style="width: 72px; height: 72px; border-radius: 50%; background: #F1F5F9; color: #94A3B8; display: flex; align-items: center; justify-content: center; font-size: 2.2rem; margin: 0 auto 1.25rem auto;">
                        <i class="fa-solid fa-file-circle-plus"></i>
                    </div>
                    <h3 style="color: #0F172A; font-size: 1.15rem; margin-bottom: 0.4rem; font-weight: 700;">No Documents Submitted Yet</h3>
                    <p style="margin: 0 0 1.25rem 0; font-size: 0.88rem; max-width: 480px; margin-left: auto; margin-right: auto; line-height: 1.5;">
                        Use the form above to upload your first capstone draft, proposal chapters, or manuscript file. Your adviser, <strong><?php echo htmlspecialchars($chosen_adviser_name); ?></strong>, will check and review your paper here.
                    </p>
                    <?php if($is_title_approved): ?>
                        <button type="button" onclick="scrollToUploadForm()" class="btn-primary" style="padding: 0.55rem 1.25rem; font-size: 0.85rem; border-radius: 8px;">
                            <i class="fa-solid fa-cloud-arrow-up"></i> Upload First Document
                        </button>
                    <?php else: ?>
                        <div style="display: inline-flex; align-items: center; gap: 6px; background: #FEF3C7; color: #92400E; border: 1px solid #FDE68A; padding: 0.45rem 1rem; border-radius: 8px; font-size: 0.82rem; font-weight: 600;">
                            <i class="fa-solid fa-lock"></i> Uploading will unlock once your title is approved
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>

                <div style="display: flex; flex-direction: column; gap: 1.15rem;">
                    <?php foreach($submissions as $sub): 
                        $stat = $sub['status'];
                        $statBadgeClass = 'badge-warning';
                        $statLabel = 'Pending Review';
                        $statColor = '#D97706';
                        $statBg = '#FEF3C7';
                        $statBorder = '#FDE68A';
                        $statIcon = 'fa-regular fa-clock';

                        if ($stat == 'approved') {
                            $statBadgeClass = 'badge-success';
                            $statLabel = 'Approved';
                            $statColor = '#047857';
                            $statBg = '#D1FAE5';
                            $statBorder = '#A7F3D0';
                            $statIcon = 'fa-solid fa-circle-check';
                        } elseif ($stat == 'needs_revision') {
                            $statBadgeClass = 'badge-danger';
                            $statLabel = 'Needs Revision';
                            $statColor = '#DC2626';
                            $statBg = '#FEE2E2';
                            $statBorder = '#FECACA';
                            $statIcon = 'fa-solid fa-rotate-left';
                        } elseif ($stat == 'under_review') {
                            $statBadgeClass = 'badge-info';
                            $statLabel = 'Under Review';
                            $statColor = '#2563EB';
                            $statBg = '#DBEAFE';
                            $statBorder = '#BFDBFE';
                            $statIcon = 'fa-solid fa-magnifying-glass';
                        }

                        $ext = strtolower($sub['file_ext']);
                        $extIcon = 'fa-file-lines';
                        $extColor = '#64748B';
                        if ($ext == 'pdf') { $extIcon = 'fa-file-pdf'; $extColor = '#DC2626'; }
                        elseif (in_array($ext, ['doc', 'docx'])) { $extIcon = 'fa-file-word'; $extColor = '#2563EB'; }
                        elseif ($ext == 'gdoc') { $extIcon = 'fa-file-lines'; $extColor = '#2563EB'; }
                        elseif (in_array($ext, ['zip', 'rar'])) { $extIcon = 'fa-file-zipper'; $extColor = '#7C3AED'; }

                        $dateFormatted = date('M d, Y • h:i A', strtotime($sub['created_at']));
                        $reviewedFormatted = !empty($sub['reviewed_at']) ? date('M d, Y • h:i A', strtotime($sub['reviewed_at'])) : null;
                    ?>
                        <div class="submission-card" data-status="<?php echo $stat == 'under_review' ? 'pending' : $stat; ?>" 
                             style="background: white; border: 1px solid #E2E8F0; border-radius: 12px; padding: 1.25rem 1.4rem; box-shadow: var(--shadow-sm); transition: all 0.2s ease;">
                            
                            <!-- Header Line: Title, Category, Version, Status -->
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 0.85rem;">
                                <div style="flex: 1; min-width: 260px;">
                                    <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 0.35rem;">
                                        <span style="background: #F1F5F9; color: #334155; font-size: 0.72rem; font-weight: 700; padding: 0.2rem 0.55rem; border-radius: 6px; border: 1px solid #CBD5E1;">
                                            <?php echo htmlspecialchars($sub['doc_type']); ?>
                                        </span>
                                        <span style="background: #EFF6FF; color: #1D4ED8; font-size: 0.72rem; font-weight: 700; padding: 0.2rem 0.55rem; border-radius: 6px; border: 1px solid #BFDBFE;">
                                            <?php echo htmlspecialchars($sub['version']); ?>
                                        </span>
                                        <?php if(!empty($sub['shareable_link'])): ?>
                                            <span style="background: #EFF6FF; color: #1D4ED8; font-size: 0.72rem; font-weight: 700; padding: 0.2rem 0.55rem; border-radius: 6px; border: 1px solid #BFDBFE;">
                                                <i class="fa-solid fa-link"></i> Shareable Document
                                            </span>
                                        <?php endif; ?>
                                        <?php if((int)$sub['student_id'] === (int)$uid): ?>
                                            <span style="background: #ECFDF5; color: #047857; font-size: 0.72rem; font-weight: 700; padding: 0.2rem 0.55rem; border-radius: 6px; border: 1px solid #A7F3D0;">
                                                <i class="fa-solid fa-user-check"></i> Uploaded by You
                                            </span>
                                        <?php else: ?>
                                            <span style="background: #EFF6FF; color: #1E40AF; font-size: 0.72rem; font-weight: 700; padding: 0.2rem 0.55rem; border-radius: 6px; border: 1px solid #BFDBFE;">
                                                <i class="fa-solid fa-users"></i> Uploaded by <?php echo htmlspecialchars($sub['submitter_name'] ?? 'Teammate'); ?><?php echo !empty($sub['submitter_is_leader']) ? ' (Leader)' : ''; ?>
                                            </span>
                                        <?php endif; ?>
                                        <span style="font-size: 0.75rem; color: #94A3B8;">
                                            <i class="fa-regular fa-calendar"></i> <?php echo $dateFormatted; ?>
                                        </span>
                                    </div>
                                    <h3 style="margin: 0; color: #0F172A; font-size: 1.05rem; font-weight: 700; line-height: 1.35;">
                                        <?php echo htmlspecialchars($sub['title']); ?>
                                    </h3>
                                </div>

                                <div>
                                    <span style="background: <?php echo $statBg; ?>; color: <?php echo $statColor; ?>; border: 1px solid <?php echo $statBorder; ?>; font-size: 0.78rem; font-weight: 700; padding: 0.3rem 0.75rem; border-radius: 999px; display: inline-flex; align-items: center; gap: 0.35rem;">
                                        <i class="<?php echo $statIcon; ?>"></i> <?php echo $statLabel; ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Student Notes / Abstract if present -->
                            <?php if(!empty($sub['description'])): ?>
                                <div style="background: #F8FAFC; border-left: 3px solid #CBD5E1; padding: 0.65rem 0.95rem; border-radius: 0 6px 6px 0; margin-bottom: 0.85rem; font-size: 0.84rem; color: #334155; line-height: 1.45;">
                                    <strong style="color: #475569; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 0.2rem;">
                                        <i class="fa-solid fa-message"></i> Student Remarks:
                                    </strong>
                                    <?php echo nl2br(htmlspecialchars($sub['description'])); ?>
                                </div>
                            <?php endif; ?>

                            <!-- Uploaded File Badge & Shareable Link Action Row -->
                            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem; padding: 0.75rem 0.95rem; background: #F8FAFC; border-radius: 8px; border: 1px solid #E2E8F0; margin-bottom: 0.85rem;">
                                <div style="display: flex; align-items: center; gap: 0.65rem; overflow: hidden; max-width: 60%; min-width: 200px;">
                                    <div style="font-size: 1.4rem; color: <?php echo $extColor; ?>; flex-shrink: 0;">
                                        <i class="fa-solid <?php echo $extIcon; ?>"></i>
                                    </div>
                                    <div style="overflow: hidden;">
                                        <span style="font-weight: 600; color: #0F172A; font-size: 0.85rem; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                            <?php echo htmlspecialchars($sub['file_name']); ?>
                                        </span>
                                        <small style="color: #64748B; font-size: 0.74rem;">
                                            <?php if(!empty($sub['shareable_link']) && ($ext == 'gdoc' || $ext == 'link' || empty($sub['file_size']))): ?>
                                                Online Cloud Document (Google Docs / Word Online)
                                            <?php else: ?>
                                                <?php echo strtoupper($ext); ?> Document • <?php echo format_file_size($sub['file_size']); ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                </div>
                                
                                <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                                    <?php if(!empty($sub['shareable_link'])): ?>
                                        <a href="<?php echo htmlspecialchars($sub['shareable_link']); ?>" target="_blank" rel="noopener noreferrer" class="btn" style="background: #2563EB; border: 1px solid #1D4ED8; color: white; padding: 0.4rem 0.9rem; border-radius: 6px; font-size: 0.82rem; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem; box-shadow: 0 1px 2px rgba(37,99,235,0.2);">
                                            <i class="fa-solid fa-arrow-up-right-from-square"></i> Open in Google Docs / Word
                                        </a>
                                    <?php endif; ?>

                                    <?php if(!empty($sub['file_path']) && $sub['file_ext'] !== 'gdoc' && $sub['file_ext'] !== 'link' && !empty($sub['file_size'])): ?>
                                        <a href="<?php echo htmlspecialchars($sub['file_path']); ?>" target="_blank" download class="btn" style="background: white; border: 1px solid #CBD5E1; color: #0284C7; padding: 0.4rem 0.85rem; border-radius: 6px; font-size: 0.8rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem;">
                                            <i class="fa-solid fa-download"></i> Download File
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- ADVISER FEEDBACK BOX (If Reviewed) -->
                            <?php if(!empty($sub['feedback']) || !empty($sub['reviewed_file_path'])): ?>
                                <div style="background: <?php echo ($stat == 'approved') ? '#F0FDF4' : '#FFFBEB'; ?>; border: 1px solid <?php echo ($stat == 'approved') ? '#BBF7D0' : '#FDE68A'; ?>; border-radius: 10px; padding: 1rem 1.15rem; margin-bottom: 0.85rem;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; flex-wrap: wrap; gap: 0.4rem;">
                                        <div style="font-size: 0.82rem; font-weight: 700; color: <?php echo ($stat == 'approved') ? '#065F46' : '#92400E'; ?>; display: flex; align-items: center; gap: 0.4rem;">
                                            <i class="fa-solid fa-comment-dots"></i>
                                            <span>Adviser Review & Feedback:</span>
                                            <?php if(!empty($sub['reviewer_name'])): ?>
                                                <span style="font-weight: 800;">Prof. <?php echo htmlspecialchars($sub['reviewer_name']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if($reviewedFormatted): ?>
                                            <span style="font-size: 0.72rem; color: #78716C;">
                                                <i class="fa-regular fa-clock"></i> Reviewed: <?php echo $reviewedFormatted; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <?php if(!empty($sub['feedback'])): ?>
                                        <div style="color: #1E293B; font-size: 0.88rem; line-height: 1.5; white-space: pre-wrap; background: white; padding: 0.85rem; border-radius: 6px; border: 1px solid <?php echo ($stat == 'approved') ? '#DCFCE7' : '#FEF3C7'; ?>;">
                                            <?php echo nl2br(htmlspecialchars($sub['feedback'])); ?>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Annotated Document Returned by Reviewer -->
                                    <?php if(!empty($sub['reviewed_file_path'])): ?>
                                        <div style="margin-top: 0.75rem; display: flex; align-items: center; justify-content: space-between; background: white; padding: 0.65rem 0.85rem; border-radius: 6px; border: 1px solid #CBD5E1;">
                                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                <span style="color: #047857; font-size: 1.1rem;"><i class="fa-solid fa-file-pen"></i></span>
                                                <div>
                                                    <strong style="color: #0F172A; font-size: 0.82rem; display: block;">Adviser Annotated File:</strong>
                                                    <small style="color: #64748B; font-size: 0.74rem;"><?php echo htmlspecialchars($sub['reviewed_file_name'] ?: 'Annotated_Manuscript.docx'); ?></small>
                                                </div>
                                            </div>
                                            <a href="<?php echo htmlspecialchars($sub['reviewed_file_path']); ?>" target="_blank" download class="btn" style="background: #ECFDF5; color: #047857; border: 1px solid #A7F3D0; font-size: 0.78rem; font-weight: 700; padding: 0.35rem 0.75rem; border-radius: 6px; text-decoration: none;">
                                                <i class="fa-solid fa-download"></i> Download Annotated Draft
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <!-- ACTION BUTTONS: Resubmit, Delete (if pending) -->
                            <div style="display: flex; justify-content: flex-end; align-items: center; gap: 0.5rem; margin-top: 0.5rem; border-top: 1px dashed #E2E8F0; padding-top: 0.75rem;">
                                
                                <!-- Submit New Revision Button -->
                                <button type="button" 
                                        onclick="prepareResubmission(<?php echo htmlspecialchars(json_encode([
                                            'id' => $sub['id'],
                                            'title' => $sub['title'],
                                            'doc_type' => $sub['doc_type'],
                                            'version' => $sub['version'],
                                            'shareable_link' => $sub['shareable_link'] ?? ''
                                        ])); ?>)" 
                                        class="btn" 
                                        style="background: #F0FDF4; color: #047857; border: 1px solid #A7F3D0; padding: 0.35rem 0.85rem; border-radius: 6px; font-size: 0.8rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 0.35rem;">
                                    <i class="fa-solid fa-rotate-right"></i> Submit New Revision
                                </button>

                                <!-- Withdraw / Delete (Only if pending, by uploader or leader) -->
                                <?php if($stat == 'pending' && ((int)$sub['student_id'] === (int)$uid || (!empty($my_group) && (int)($my_group['leader_id'] ?? 0) === (int)$uid))): ?>
                                    <form method="POST" action="action.php" style="display: inline;" onsubmit="return confirm('Are you sure you want to withdraw and delete this pending document submission?');">
                                        <input type="hidden" name="delete_document_submission" value="1">
                                        <input type="hidden" name="submission_id" value="<?php echo $sub['id']; ?>">
                                        <button type="submit" class="btn" style="background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; padding: 0.35rem 0.75rem; border-radius: 6px; font-size: 0.8rem; cursor: pointer;" title="Withdraw pending upload">
                                            <i class="fa-solid fa-trash-can"></i> Withdraw
                                        </button>
                                    </form>
                                <?php endif; ?>

                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>

            <?php endif; ?>

        </div>
    </div>

    <!-- ============================================================== -->
    <!-- SECTION 3: CAPSTONE TOPIC PROFILE QUICK UPDATE -->
    <!-- ============================================================== -->
    <div class="card" style="border: 1px solid #E2E8F0; margin-top: 2rem;">
        <div class="card-header" style="cursor: pointer;" onclick="toggleTopicSection()">
            <div class="card-header-left">
                <i class="fa-solid fa-book-bookmark" style="color: var(--csu-gold);"></i>
                <h2>Capstone Registered Topic & General Manuscript Profile</h2>
            </div>
            <span id="topicToggleIcon" style="color: #64748B;"><i class="fa-solid fa-chevron-down"></i></span>
        </div>
        <div class="card-body" id="topicSectionBody" style="display: none;">
            <form method="POST" action="action.php" enctype="multipart/form-data">
                <input type="hidden" name="update_profile" value="1">
                <div class="form-group">
                    <label style="font-weight: 700; color: #0F172A; display: block; margin-bottom: 0.4rem; font-size: 0.88rem;">General Capstone Topic / Title</label>
                    <textarea name="thesis_topic" rows="3" placeholder="Enter your full capstone title and keywords..." required style="width: 100%; padding: 0.65rem 0.85rem; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 0.88rem; font-family: 'Inter', sans-serif;"><?php echo htmlspecialchars($profile['thesis_topic'] ?? ''); ?></textarea>
                </div>
                <div class="form-group">
                    <label style="font-weight: 700; color: #0F172A; display: block; margin-bottom: 0.4rem; font-size: 0.88rem;">Content PDF Document (Optional Quick Replace)</label>
                    <div style="background: #F8FAFC; padding: 1rem; border-radius: 8px; border: 1px solid #E2E8F0;">
                        <?php if(!empty($profile['manuscript_file'])): ?>
                            <p style="margin-bottom: 0.65rem; color: #059669; font-weight: 600; font-size: 0.85rem;">
                                <i class="fa-solid fa-file-pdf"></i> Current File: 
                                <a href="<?php echo htmlspecialchars($profile['manuscript_file']); ?>" target="_blank" style="color: #0284C7; text-decoration: underline;">View Uploaded PDF</a>
                            </p>
                        <?php endif; ?>
                        <input type="file" name="content_pdf" accept=".pdf" class="form-control" style="width: 100%; padding: 0.5rem 0.75rem; border-radius: 6px; border: 1px solid #CBD5E1; background: white; font-size: 0.82rem;">
                        <small style="color: var(--text-muted); display: block; margin-top: 0.35rem; font-size: 0.78rem;">Upload your primary capstone proposal document in PDF format.</small>
                    </div>
                </div>
                <button type="submit" class="btn-primary" style="padding: 0.55rem 1.25rem; font-size: 0.85rem; border-radius: 6px;">
                    <i class="fa-solid fa-floppy-disk"></i> Update Capstone Profile
                </button>
            </form>
        </div>
    </div>

<!-- AI OUTLINE MODAL -->
<div id="aiOutlineModal" class="modal" style="display: none; position: fixed; z-index: 1050; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(2px);">
    <div class="modal-content" style="background: white; border-radius: 14px; max-width: 600px; margin: 5% auto; padding: 1.5rem 1.75rem; max-height: 85vh; overflow-y: auto; box-shadow: var(--shadow-xl);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h3 style="margin: 0; color: #0F172A; font-size: 1.15rem; font-weight: 800; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-wand-magic-sparkles" style="color: var(--csu-gold);"></i> AI Capstone Chapter Outline Generator
            </h3>
            <span onclick="toggleAIOutlineModal()" style="font-size: 1.5rem; color: #94A3B8; cursor: pointer;">&times;</span>
        </div>
        <p style="color: #64748B; font-size: 0.85rem; line-height: 1.45; margin-bottom: 1rem;">
            Enter your proposed title to generate a standard CEIT IEEE manuscript structure to guide your paper writing:
        </p>
        <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem;">
            <input type="text" id="aiTopicInput" value="<?php echo htmlspecialchars($profile['thesis_topic'] ?? ''); ?>" placeholder="Enter paper title..." style="flex: 1; padding: 0.6rem 0.8rem; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 0.88rem;">
            <button type="button" onclick="generateOutline()" class="btn-primary" style="padding: 0.6rem 1rem; font-size: 0.85rem; white-space: nowrap;">
                Generate
            </button>
        </div>
        <div id="aiOutlineLoading" style="display: none; text-align: center; padding: 1.5rem; color: var(--csu-green);">
            <i class="fa-solid fa-circle-notch fa-spin fa-2x"></i>
            <p style="margin-top: 0.5rem; font-size: 0.85rem;">Generating standard chapter outlines...</p>
        </div>
        <textarea id="aiOutlineResult" rows="10" readonly style="display: none; width: 100%; padding: 0.75rem; border: 1px solid #CBD5E1; border-radius: 8px; font-family: monospace; font-size: 0.82rem; background: #F8FAFC; color: #1E293B; line-height: 1.45;"></textarea>
        <div id="aiOutlineActions" style="display: none; margin-top: 0.75rem; text-align: right;">
            <button type="button" onclick="copyOutline()" class="btn" style="background: #F1F5F9; color: #334155; border: 1px solid #CBD5E1; padding: 0.4rem 0.85rem; border-radius: 6px; font-size: 0.8rem;">
                <i class="fa-solid fa-copy"></i> Copy to Clipboard
            </button>
            <button type="button" onclick="insertOutlineIntoRemarks()" class="btn-primary" style="padding: 0.4rem 0.85rem; border-radius: 6px; font-size: 0.8rem;">
                <i class="fa-solid fa-arrow-down"></i> Insert into Remarks
            </button>
        </div>
    </div>
</div>

<script>
function scrollToUploadForm() {
    const el = document.getElementById('submissionUploadCard');
    if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        const titleInput = document.getElementById('doc_title');
        if (titleInput) titleInput.focus();
    }
}

function handleDragOver(e) {
    e.preventDefault();
    e.stopPropagation();
    const dz = document.getElementById('dropZone');
    if (dz) {
        dz.style.borderColor = 'var(--csu-green)';
        dz.style.backgroundColor = '#ECFDF5';
    }
}

function handleDragLeave(e) {
    e.preventDefault();
    e.stopPropagation();
    const dz = document.getElementById('dropZone');
    if (dz) {
        dz.style.borderColor = '#94A3B8';
        dz.style.backgroundColor = '#F8FAFC';
    }
}

function handleDrop(e) {
    e.preventDefault();
    e.stopPropagation();
    handleDragLeave(e);
    if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length > 0) {
        const fileInput = document.getElementById('document_file');
        if (fileInput) {
            fileInput.files = e.dataTransfer.files;
            updateFileDisplay(fileInput.files[0]);
        }
    }
}

function handleFileSelect(e) {
    if (e.target.files && e.target.files.length > 0) {
        updateFileDisplay(e.target.files[0]);
    }
}

function updateFileDisplay(file) {
    if (!file) return;
    const promptEl = document.getElementById('dropZonePrompt');
    if (promptEl) promptEl.style.display = 'none';
    const display = document.getElementById('fileSelectedDisplay');
    if (display) display.style.display = 'flex';
    const nameEl = document.getElementById('selectedFileName');
    if (nameEl) nameEl.textContent = file.name;
    
    let sizeStr = '';
    if (file.size >= 1048576) {
        sizeStr = (file.size / 1048576).toFixed(2) + ' MB';
    } else {
        sizeStr = (file.size / 1024).toFixed(1) + ' KB';
    }
    const sizeEl = document.getElementById('selectedFileSize');
    if (sizeEl) sizeEl.textContent = sizeStr;

    // Icon update
    const ext = file.name.split('.').pop().toLowerCase();
    const iconEl = document.getElementById('fileIconDisplay');
    if (iconEl) {
        if (ext === 'pdf') {
            iconEl.innerHTML = '<i class="fa-solid fa-file-pdf" style="color: #DC2626;"></i>';
        } else if (['doc', 'docx'].includes(ext)) {
            iconEl.innerHTML = '<i class="fa-solid fa-file-word" style="color: #2563EB;"></i>';
        } else if (['zip', 'rar'].includes(ext)) {
            iconEl.innerHTML = '<i class="fa-solid fa-file-zipper" style="color: #7C3AED;"></i>';
        } else {
            iconEl.innerHTML = '<i class="fa-solid fa-file-lines" style="color: #059669;"></i>';
        }
    }
}

function clearSelectedFile(e) {
    e.stopPropagation();
    const fileInput = document.getElementById('document_file');
    if (fileInput) fileInput.value = '';
    const display = document.getElementById('fileSelectedDisplay');
    if (display) display.style.display = 'none';
    const promptEl = document.getElementById('dropZonePrompt');
    if (promptEl) promptEl.style.display = 'block';
}

function setVersion(ver) {
    const verInput = document.getElementById('doc_version');
    if (verInput) verInput.value = ver;
}

function handleLinkInput(val) {
    const trimmed = (val || '').trim().toLowerCase();
    const badge = document.getElementById('docTypeDetectedBadge');
    const icon = document.getElementById('linkInputIcon');
    if (!badge || !icon) return;

    if (!trimmed) {
        badge.style.display = 'none';
        icon.innerHTML = '<i class="fa-solid fa-globe"></i>';
        icon.style.color = '#64748B';
        return;
    }

    badge.style.display = 'inline-flex';
    badge.style.alignItems = 'center';
    badge.style.gap = '4px';

    if (trimmed.includes('docs.google.com') || trimmed.includes('drive.google.com')) {
        badge.style.background = '#EFF6FF';
        badge.style.color = '#1D4ED8';
        badge.style.border = '1px solid #BFDBFE';
        badge.innerHTML = '<i class="fa-solid fa-file-lines"></i> Google Docs Link Detected';
        icon.innerHTML = '<i class="fa-solid fa-file-lines" style="color: #2563EB;"></i>';
    } else if (trimmed.includes('office.com') || trimmed.includes('onedrive') || trimmed.includes('1drv.ms') || trimmed.includes('sharepoint.com')) {
        badge.style.background = '#EFF6FF';
        badge.style.color = '#1E40AF';
        badge.style.border = '1px solid #93C5FD';
        badge.innerHTML = '<i class="fa-solid fa-file-word"></i> Word Online / OneDrive Detected';
        icon.innerHTML = '<i class="fa-solid fa-file-word" style="color: #1E40AF;"></i>';
    } else {
        badge.style.background = '#F1F5F9';
        badge.style.color = '#334155';
        badge.style.border = '1px solid #CBD5E1';
        badge.innerHTML = '<i class="fa-solid fa-link"></i> Web Link Detected';
        icon.innerHTML = '<i class="fa-solid fa-link" style="color: #047857;"></i>';
    }
}

function testShareableLink() {
    const linkInput = document.getElementById('shareable_link');
    if (!linkInput || !linkInput.value.trim()) {
        alert('Please enter a shareable document link first (e.g. Google Docs or Word Online link).');
        if (linkInput) linkInput.focus();
        return;
    }
    let url = linkInput.value.trim();
    if (!url.startsWith('http://') && !url.startsWith('https://')) {
        url = 'https://' + url;
        linkInput.value = url;
        handleLinkInput(url);
    }
    window.open(url, '_blank', 'noopener,noreferrer');
}

function prepareResubmission(sub) {
    scrollToUploadForm();
    const resubInput = document.getElementById('resubmit_id');
    const titleInput = document.getElementById('doc_title');
    const typeInput = document.getElementById('doc_type');
    const verInput = document.getElementById('doc_version');
    const descInput = document.getElementById('doc_description');
    const linkInput = document.getElementById('shareable_link');

    if (resubInput) resubInput.value = sub.id;
    if (titleInput) titleInput.value = sub.title;
    if (typeInput) typeInput.value = sub.doc_type;
    if (linkInput) {
        linkInput.value = sub.shareable_link || '';
        handleLinkInput(linkInput.value);
    }
    
    // Auto advance version
    let currentVer = sub.version || 'v1.0';
    let nextVer = 'v1.1';
    if (currentVer.startsWith('v')) {
        let parts = currentVer.substring(1).split('.');
        if (parts.length === 2 && !isNaN(parts[1])) {
            nextVer = 'v' + parts[0] + '.' + (parseInt(parts[1]) + 1);
        } else if (parts.length === 1 && !isNaN(parts[0])) {
            nextVer = 'v' + (parseInt(parts[0]) + 1) + '.0';
        }
    }
    if (verInput) verInput.value = nextVer;
    if (descInput) descInput.value = `Resubmission addressing adviser review feedback for ${sub.version}.`;
    
    const heading = document.getElementById('formCardHeading');
    if (heading) heading.innerHTML = `<i class="fa-solid fa-rotate-right" style="color: #047857;"></i> Submit New Revision for: "${sub.title}" (${nextVer})`;
    const cancelBtn = document.getElementById('cancelResubmitBtn');
    if (cancelBtn) cancelBtn.style.display = 'inline-block';
    const submitBtn = document.getElementById('submitBtn');
    if (submitBtn) submitBtn.innerHTML = `<i class="fa-solid fa-cloud-arrow-up"></i> Submit Revised ${nextVer}`;
}

function resetFormToNew() {
    const resubInput = document.getElementById('resubmit_id');
    const verInput = document.getElementById('doc_version');
    const descInput = document.getElementById('doc_description');
    const linkInput = document.getElementById('shareable_link');
    const heading = document.getElementById('formCardHeading');
    const cancelBtn = document.getElementById('cancelResubmitBtn');
    const submitBtn = document.getElementById('submitBtn');

    if (resubInput) resubInput.value = '';
    if (verInput) verInput.value = 'v1.0';
    if (descInput) descInput.value = '';
    if (linkInput) {
        linkInput.value = '';
        handleLinkInput('');
    }
    clearSelectedFile(new Event('clear'));
    if (heading) heading.innerHTML = `Submit Document or Paper for Checking`;
    if (cancelBtn) cancelBtn.style.display = 'none';
    if (submitBtn) submitBtn.innerHTML = `<i class="fa-solid fa-paper-plane"></i> Submit Document for Review`;
}

function validateSubmissionForm() {
    const fileInput = document.getElementById('document_file');
    const linkInput = document.getElementById('shareable_link');
    const linkVal = linkInput ? linkInput.value.trim() : '';
    const hasFile = fileInput && fileInput.files && fileInput.files.length > 0;
    const hasLink = linkVal.length > 0;

    if (!hasFile && !hasLink) {
        alert('Please provide either a Shareable Document Link (Google Docs / Word Online) or upload a Word document file.');
        if (linkInput) linkInput.focus();
        return false;
    }

    if (hasLink) {
        if (!linkVal.startsWith('http://') && !linkVal.startsWith('https://')) {
            if (linkVal.includes('google.com') || linkVal.includes('office.com') || linkVal.includes('live.com') || linkVal.includes('sharepoint.com')) {
                linkInput.value = 'https://' + linkVal;
            } else {
                alert('Please enter a valid URL for your document link (e.g. starting with https://).');
                linkInput.focus();
                return false;
            }
        }
    }

    const submitBtn = document.getElementById('submitBtn');
    const indicator = document.getElementById('submittingIndicator');
    if (submitBtn) submitBtn.disabled = true;
    if (indicator) indicator.style.display = 'inline-block';
    return true;
}

function filterSubmissions(status) {
    const cards = document.querySelectorAll('.submission-card');
    cards.forEach(card => {
        if (status === 'all' || card.getAttribute('data-status') === status) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });

    document.querySelectorAll('.btn-filter').forEach(btn => {
        btn.style.opacity = '0.7';
    });
    if (status === 'all' && document.getElementById('btnFilterAll')) document.getElementById('btnFilterAll').style.opacity = '1';
    if (status === 'pending' && document.getElementById('btnFilterPending')) document.getElementById('btnFilterPending').style.opacity = '1';
    if (status === 'needs_revision' && document.getElementById('btnFilterRevision')) document.getElementById('btnFilterRevision').style.opacity = '1';
    if (status === 'approved' && document.getElementById('btnFilterApproved')) document.getElementById('btnFilterApproved').style.opacity = '1';
}

function toggleTopicSection() {
    const body = document.getElementById('topicSectionBody');
    const icon = document.getElementById('topicToggleIcon');
    if (body) {
        if (body.style.display === 'none') {
            body.style.display = 'block';
            if (icon) icon.innerHTML = '<i class="fa-solid fa-chevron-up"></i>';
        } else {
            body.style.display = 'none';
            if (icon) icon.innerHTML = '<i class="fa-solid fa-chevron-down"></i>';
        }
    }
}

function toggleAIOutlineModal() {
    const modal = document.getElementById('aiOutlineModal');
    if (modal) {
        modal.style.display = (modal.style.display === 'none' || modal.style.display === '') ? 'block' : 'none';
    }
}

function generateOutline() {
    const topicInput = document.getElementById('aiTopicInput');
    const title = topicInput ? topicInput.value.trim() : '';
    if (!title) {
        alert('Please provide a paper title.');
        return;
    }
    const loading = document.getElementById('aiOutlineLoading');
    const result = document.getElementById('aiOutlineResult');
    const actions = document.getElementById('aiOutlineActions');

    if (loading) loading.style.display = 'block';
    if (result) result.style.display = 'none';
    if (actions) actions.style.display = 'none';

    fetch(`api.php?action=generate_paper&title=${encodeURIComponent(title)}`)
        .then(res => res.json())
        .then(data => {
            if (loading) loading.style.display = 'none';
            if (data.draft) {
                if (result) {
                    result.value = data.draft;
                    result.style.display = 'block';
                }
                if (actions) actions.style.display = 'block';
            } else {
                alert('Could not generate draft: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(err => {
            if (loading) loading.style.display = 'none';
            alert('Failed to connect to outline generator.');
        });
}

function copyOutline() {
    const txt = document.getElementById('aiOutlineResult');
    if (txt) {
        txt.select();
        document.execCommand('copy');
        alert('Chapter outline copied to clipboard!');
    }
}

function insertOutlineIntoRemarks() {
    const txt = document.getElementById('aiOutlineResult');
    const desc = document.getElementById('doc_description');
    if (txt && desc) {
        desc.value = (desc.value ? desc.value + "\n\n" : "") + "--- Outlined Chapters & Methodology ---\n" + txt.value;
    }
    toggleAIOutlineModal();
    scrollToUploadForm();
}
</script>
<?php endif; ?>
