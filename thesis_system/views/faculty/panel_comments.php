<?php
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'adviser') {
    header("Location: dashboard.php");
    exit();
}

$uid = (int)$_SESSION['user_id'];
$faculty_name = $_SESSION['name'] ?? 'Faculty Adviser';

// Get all advisee student IDs and group IDs strictly assigned to this adviser
$adv_ids = get_adviser_advisee_ids($conn, $uid);
$advisee_student_ids = $adv_ids['student_ids'];
$advisee_group_ids = $adv_ids['group_ids'];

// Filter inputs
$selected_stu_id = !empty($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$filter_type = sanitize($_GET['type'] ?? 'all');
$filter_status = sanitize($_GET['status'] ?? 'all');
$search_query = trim($_GET['search'] ?? '');

// Strict Access Control Check
$unauthorized_access_attempt = false;
if ($selected_stu_id > 0 && !in_array($selected_stu_id, $advisee_student_ids)) {
    $unauthorized_access_attempt = true;
    $selected_stu_id = 0; // Reset to prevent unauthorized access
}

// Fetch list of active advisees for the filter dropdown
$advisees_roster = [];
if (!empty($advisee_student_ids)) {
    $stu_list = implode(',', $advisee_student_ids);
    $adv_q = $conn->query("SELECT u.id as student_id, u.name, u.email, sp.student_id as student_no, sp.thesis_topic, cg.group_name 
                           FROM user u 
                           LEFT JOIN student_profile sp ON u.id = sp.user_id 
                           LEFT JOIN capstone_group cg ON sp.group_id = cg.id 
                           WHERE u.id IN ($stu_list) 
                           ORDER BY u.name ASC");
    if ($adv_q) {
        while ($r = $adv_q->fetch_assoc()) {
            $advisees_roster[] = $r;
        }
    }
}

// Fetch comments strictly scoped to this adviser's assigned students or research groups
$all_comments = get_adviser_advisee_comments($conn, $uid, $selected_stu_id ?: null, $filter_type, $filter_status, $search_query);

// Summary counts
$stats = count_adviser_advisee_comments($conn, $uid);

// If filtered to a specific student, find that student's details
$current_stu = null;
if ($selected_stu_id > 0) {
    foreach ($advisees_roster as $ar) {
        if ($ar['student_id'] == $selected_stu_id) {
            $current_stu = $ar;
            break;
        }
    }
}
?>

<link rel="stylesheet" href="css/meeting_doc.css?v=<?php echo time(); ?>">

<style>
/* Adviser Panel Comments Styling */
.adv-comments-wrapper {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.adv-banner-card {
    background: linear-gradient(135deg, #064E3B 0%, #047857 60%, #059669 100%);
    border-radius: 12px;
    padding: 1.5rem 1.75rem;
    color: #FFFFFF;
    box-shadow: 0 4px 14px rgba(4, 120, 87, 0.2);
}

.adv-banner-title {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.adv-banner-title h1 {
    font-size: 1.45rem;
    font-weight: 800;
    margin: 0;
    color: #FFFFFF;
    display: flex;
    align-items: center;
    gap: 0.65rem;
}

.adv-banner-title p {
    margin: 0.35rem 0 0 0;
    color: #D1FAE5;
    font-size: 0.9rem;
}

.adv-stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.adv-stat-box {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 10px;
    padding: 1rem 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.03);
    transition: transform 0.2s, box-shadow 0.2s;
}

.adv-stat-box:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(0,0,0,0.07);
}

.adv-stat-icon {
    width: 46px;
    height: 46px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}

.adv-stat-info .num {
    font-size: 1.5rem;
    font-weight: 800;
    color: #0F172A;
    line-height: 1.1;
}

.adv-stat-info .lbl {
    font-size: 0.78rem;
    font-weight: 700;
    color: #64748B;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 0.2rem;
}

/* Filter Bar */
.adv-filter-bar {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 10px;
    padding: 1rem 1.25rem;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.85rem;
}

.adv-filter-group {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    flex-wrap: wrap;
}

.filter-chip-link {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.4rem 0.85rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 700;
    text-decoration: none;
    background: #F1F5F9;
    color: #475569;
    transition: all 0.2s;
}

.filter-chip-link:hover {
    background: #E2E8F0;
    color: #0F172A;
}

.filter-chip-link.active {
    background: #047857;
    color: #FFFFFF;
}

/* Comment Cards */
.adv-comment-card {
    background: #FFFFFF;
    border: 1.5px solid #E2E8F0;
    border-left: 5px solid #047857;
    border-radius: 10px;
    padding: 1.25rem 1.4rem;
    box-shadow: 0 2px 6px rgba(0,0,0,0.04);
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    transition: all 0.2s ease;
}

.adv-comment-card.revision-needed {
    border-left-color: #DC2626;
    background: #FFFAFA;
}

.adv-comment-card.commendation-card {
    border-left-color: #10B981;
}

.adv-comment-card.question-card {
    border-left-color: #2563EB;
}

.adv-comment-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

.comment-supervisee-strip {
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    padding: 0.65rem 0.95rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.supervisee-name {
    font-size: 0.92rem;
    font-weight: 800;
    color: #0F172A;
    display: flex;
    align-items: center;
    gap: 0.45rem;
}

.supervisee-topic {
    font-size: 0.82rem;
    color: #475569;
    margin-top: 0.15rem;
    font-weight: 600;
}

.adv-comment-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.adv-author-meta {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.adv-author-avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: #047857;
    color: #FFFFFF;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
}

.adv-author-name {
    font-size: 0.92rem;
    font-weight: 800;
    color: #0F172A;
}

.adv-author-role {
    font-size: 0.75rem;
    color: #059669;
    font-weight: 700;
}

.adv-comment-text {
    font-size: 0.95rem;
    line-height: 1.6;
    color: #1E293B;
    background: #FFFFFF;
    padding: 0.5rem 0;
    white-space: pre-wrap;
    word-break: break-word;
}

/* Student Reply Box */
.adv-student-reply-box {
    background: #F0FDF4;
    border: 1px solid #BBF7D0;
    border-left: 4px solid #16A34A;
    border-radius: 6px;
    padding: 0.75rem 1rem;
    font-size: 0.85rem;
}

.adv-student-reply-title {
    color: #166534;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 0.4rem;
    margin-bottom: 0.25rem;
}

/* Adviser Notes Box */
.adv-guidance-box {
    background: #FFFBEB;
    border: 1px solid #FDE68A;
    border-left: 4px solid #D97706;
    border-radius: 6px;
    padding: 0.75rem 1rem;
    font-size: 0.85rem;
}

.adv-guidance-title {
    color: #92400E;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 0.4rem;
    margin-bottom: 0.25rem;
}

.adv-guidance-form {
    margin-top: 0.65rem;
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}

.adv-btn-save {
    background: #047857;
    color: #FFFFFF;
    font-weight: 700;
    font-size: 0.8rem;
    padding: 0.4rem 0.9rem;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    align-self: flex-end;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.adv-btn-save:hover {
    background: #065F46;
}
</style>

<div class="adv-comments-wrapper">

    <!-- Unauthorized Alert (if attempted to access outside supervision) -->
    <?php if ($unauthorized_access_attempt): ?>
    <div class="alert alert-danger" style="background: #FEF2F2; border: 1.5px solid #F87171; color: #991B1B; border-radius: 8px; padding: 0.85rem 1.25rem;">
        <i class="fa-solid fa-triangle-exclamation"></i> 
        <strong>Access Restricted:</strong> You can only access and view panel comments for students or research groups under your direct supervision as assigned adviser.
    </div>
    <?php endif; ?>

    <!-- Header Banner -->
    <div class="adv-banner-card">
        <div class="adv-banner-title">
            <div>
                <h1><i class="fa-solid fa-comments"></i> Advisees' Panel Comments & Feedback</h1>
                <p>Comprehensive monitoring of committee evaluations, revisions, and suggestions for your supervised students.</p>
            </div>
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                <button type="button" class="btn-primary" onclick="window.print()" style="background: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.4); color: #FFFFFF; padding: 0.45rem 0.9rem; font-size: 0.82rem; font-weight: 700;">
                    <i class="fa-solid fa-print"></i> Print Summary
                </button>
                <a href="?tab=advisees" class="btn-primary" style="background: #FFFFFF; color: #047857; border: none; padding: 0.45rem 0.95rem; font-size: 0.82rem; font-weight: 800; text-decoration: none;">
                    <i class="fa-solid fa-user-graduate"></i> My Advisees Roster
                </a>
            </div>
        </div>

        <?php if ($current_stu): ?>
        <div style="margin-top: 1rem; background: rgba(0,0,0,0.15); border-radius: 8px; padding: 0.75rem 1rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
            <div>
                <span style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.85; font-weight: 700;">Active Filter:</span>
                <span style="font-weight: 800; font-size: 0.95rem; margin-left: 0.35rem;"><?php echo htmlspecialchars($current_stu['name']); ?></span>
                <?php if(!empty($current_stu['student_no'])): ?>
                    <span style="font-size: 0.8rem; opacity: 0.9;">[<?php echo htmlspecialchars($current_stu['student_no']); ?>]</span>
                <?php endif; ?>
                <span style="margin: 0 0.5rem; opacity: 0.6;">•</span>
                <span style="font-size: 0.85rem; font-style: italic;"><?php echo htmlspecialchars($current_stu['thesis_topic'] ?: 'No registered topic'); ?></span>
            </div>
            <a href="?tab=panel_comments" style="color: #FFFFFF; font-size: 0.78rem; text-decoration: underline; font-weight: 700;">
                <i class="fa-solid fa-xmark"></i> Show All Advisees
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Summary Stats Row -->
    <div class="adv-stats-row">
        <div class="adv-stat-box">
            <div class="adv-stat-icon" style="background: #ECFDF5; color: #047857;">
                <i class="fa-solid fa-comments"></i>
            </div>
            <div class="adv-stat-info">
                <div class="num"><?php echo $stats['total']; ?></div>
                <div class="lbl">Total Comments</div>
            </div>
        </div>

        <div class="adv-stat-box">
            <div class="adv-stat-icon" style="background: #FEF2F2; color: #DC2626;">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <div class="adv-stat-info">
                <div class="num" style="color: #DC2626;"><?php echo $stats['revisions']; ?></div>
                <div class="lbl">Required Revisions</div>
            </div>
        </div>

        <div class="adv-stat-box">
            <div class="adv-stat-icon" style="background: #EFF6FF; color: #2563EB;">
                <i class="fa-solid fa-clock-rotate-left"></i>
            </div>
            <div class="adv-stat-info">
                <div class="num" style="color: #2563EB;"><?php echo $stats['addressed']; ?></div>
                <div class="lbl">Addressed by Advisees</div>
            </div>
        </div>

        <div class="adv-stat-box">
            <div class="adv-stat-icon" style="background: #F0FDF4; color: #16A34A;">
                <i class="fa-solid fa-check-double"></i>
            </div>
            <div class="adv-stat-info">
                <div class="num" style="color: #16A34A;"><?php echo $stats['resolved']; ?></div>
                <div class="lbl">Resolved by Panel</div>
            </div>
        </div>
    </div>

    <!-- Filter & Candidate Selector Bar -->
    <div class="adv-filter-bar">
        <form method="GET" action="" style="display: flex; gap: 0.65rem; align-items: center; flex-wrap: wrap; flex: 1;">
            <input type="hidden" name="tab" value="panel_comments">

            <!-- Candidate Advisee Selector -->
            <div style="display: flex; align-items: center; gap: 0.4rem;">
                <label style="font-weight: 700; color: #0F172A; font-size: 0.85rem;"><i class="fa-solid fa-user-graduate" style="color: #047857;"></i> Advisee:</label>
                <select name="student_id" class="mom-select" style="width: auto; min-width: 220px; font-weight: 700; font-size: 0.85rem;" onchange="this.form.submit()">
                    <option value="">All Advisees (<?php echo count($advisees_roster); ?> Active)</option>
                    <?php foreach ($advisees_roster as $adv): ?>
                        <option value="<?php echo $adv['student_id']; ?>" <?php echo $selected_stu_id == $adv['student_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($adv['name']); ?> 
                            <?php if(!empty($adv['group_name'])): ?>(<?php echo htmlspecialchars($adv['group_name']); ?>)<?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Feedback Type Filter -->
            <div style="display: flex; align-items: center; gap: 0.4rem;">
                <label style="font-weight: 700; color: #0F172A; font-size: 0.85rem;"><i class="fa-solid fa-tag" style="color: #047857;"></i> Type:</label>
                <select name="type" class="mom-select" style="width: auto; font-weight: 700; font-size: 0.85rem;" onchange="this.form.submit()">
                    <option value="all" <?php echo $filter_type == 'all' ? 'selected' : ''; ?>>All Types</option>
                    <option value="Required Revision" <?php echo $filter_type == 'Required Revision' ? 'selected' : ''; ?>>⚠️ Required Revision</option>
                    <option value="Suggestion" <?php echo $filter_type == 'Suggestion' ? 'selected' : ''; ?>>💡 Suggestion</option>
                    <option value="Commendation" <?php echo $filter_type == 'Commendation' ? 'selected' : ''; ?>>⭐ Commendation</option>
                    <option value="Clarification" <?php echo $filter_type == 'Clarification' ? 'selected' : ''; ?>>❓ Clarification</option>
                    <option value="Question" <?php echo $filter_type == 'Question' ? 'selected' : ''; ?>>❓ Question</option>
                </select>
            </div>

            <!-- Status Filter -->
            <div style="display: flex; align-items: center; gap: 0.4rem;">
                <label style="font-weight: 700; color: #0F172A; font-size: 0.85rem;"><i class="fa-solid fa-list-check" style="color: #047857;"></i> Status:</label>
                <select name="status" class="mom-select" style="width: auto; font-weight: 700; font-size: 0.85rem;" onchange="this.form.submit()">
                    <option value="all" <?php echo $filter_status == 'all' ? 'selected' : ''; ?>>All Statuses</option>
                    <option value="open" <?php echo $filter_status == 'open' ? 'selected' : ''; ?>>Open</option>
                    <option value="addressed" <?php echo $filter_status == 'addressed' ? 'selected' : ''; ?>>Addressed by Student</option>
                    <option value="resolved" <?php echo $filter_status == 'resolved' ? 'selected' : ''; ?>>Resolved by Panel</option>
                </select>
            </div>

            <!-- Search Keyword -->
            <div style="position: relative; flex: 1; min-width: 180px;">
                <input type="text" name="search" class="mom-input" style="padding: 0.45rem 2rem 0.45rem 0.85rem; font-size: 0.85rem;" placeholder="Search topic, panelist, or feedback..." value="<?php echo htmlspecialchars($search_query); ?>">
                <button type="submit" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #64748B; cursor: pointer;">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </button>
            </div>
            
            <?php if (!empty($search_query) || $filter_type !== 'all' || $filter_status !== 'all' || $selected_stu_id > 0): ?>
                <a href="?tab=panel_comments" class="btn" style="background: #F1F5F9; color: #475569; font-size: 0.8rem; font-weight: 700; padding: 0.45rem 0.85rem; border-radius: 6px; text-decoration: none;">
                    Reset Filters
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Comments List -->
    <?php if (empty($all_comments)): ?>
        <div class="card" style="padding: 3.5rem 1.5rem; text-align: center;">
            <i class="fa-solid fa-comments fa-3x" style="color: #CBD5E1; margin-bottom: 1rem; display: block;"></i>
            <h3 style="color: #0F172A; font-size: 1.15rem; margin-bottom: 0.35rem;">No Panel Comments Found</h3>
            <p style="color: #64748B; font-size: 0.9rem; max-width: 520px; margin: 0 auto;">
                <?php if (empty($advisee_student_ids)): ?>
                    You do not have any active advisees assigned yet. Once student match requests are accepted, any feedback entered by defense panel members will appear here.
                <?php elseif (!empty($search_query) || $filter_type !== 'all' || $filter_status !== 'all' || $selected_stu_id > 0): ?>
                    No comments match your active filters. Try adjusting your search query or selecting "All Types / Statuses".
                <?php else: ?>
                    The defense panel has not posted feedback for your advisees yet. Oral defense evaluations and panel notes will be synchronized live.
                <?php endif; ?>
            </p>
        </div>
    <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 1.15rem;">
            <?php foreach ($all_comments as $c): 
                $card_class = '';
                $tag_class = 'suggestion';
                if ($c['feedback_type'] === 'Required Revision') {
                    $card_class = 'revision-needed';
                    $tag_class = 'revision';
                } elseif ($c['feedback_type'] === 'Commendation') {
                    $card_class = 'commendation-card';
                    $tag_class = 'commendation';
                } elseif ($c['feedback_type'] === 'Question' || $c['feedback_type'] === 'Clarification') {
                    $card_class = 'question-card';
                    $tag_class = 'clarification';
                }
            ?>
            <div class="adv-comment-card <?php echo $card_class; ?>">

                <!-- Supervisee Identity Strip -->
                <div class="comment-supervisee-strip">
                    <div>
                        <div class="supervisee-name">
                            <i class="fa-solid fa-graduation-cap" style="color: #047857;"></i>
                            <span><?php echo htmlspecialchars($c['student_name'] ?? 'Candidate Student'); ?></span>
                            <?php if(!empty($c['student_no'])): ?>
                                <span style="font-size: 0.75rem; color: #64748B; font-weight: 600;">[<?php echo htmlspecialchars($c['student_no']); ?>]</span>
                            <?php endif; ?>
                            <?php if(!empty($c['group_name'])): ?>
                                <span class="badge badge-success" style="font-size: 0.7rem; margin-left: 0.35rem;">
                                    <i class="fa-solid fa-users"></i> <?php echo htmlspecialchars($c['group_name']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="supervisee-topic">
                            <i class="fa-solid fa-book" style="color: #059669; font-size: 0.75rem;"></i>
                            <?php echo htmlspecialchars($c['thesis_topic'] ?: 'Capstone Title Not Specified'); ?>
                        </div>
                    </div>

                    <a href="?tab=review&student_id=<?php echo $c['student_id']; ?>" style="text-decoration: none; font-size: 0.78rem; font-weight: 700; color: #047857; background: #ECFDF5; border: 1px solid #A7F3D0; padding: 0.3rem 0.75rem; border-radius: 6px; display: inline-flex; align-items: center; gap: 0.35rem;">
                        <i class="fa-solid fa-file-lines"></i> View Advisee Papers
                    </a>
                </div>

                <!-- Panel Author & Metadata -->
                <div class="adv-comment-header">
                    <div class="adv-author-meta">
                        <div class="adv-author-avatar">
                            <?php echo strtoupper(substr($c['panel_name'] ?? 'P', 0, 1)); ?>
                        </div>
                        <div>
                            <div class="adv-author-name">
                                <?php echo htmlspecialchars($c['panel_name'] ?? 'Panel Evaluator'); ?>
                            </div>
                            <div class="adv-author-role">
                                <i class="fa-solid fa-user-check"></i> 
                                <?php echo htmlspecialchars($c['panel_role'] ?? 'Defense Panel Member'); ?>
                                <span style="color: #94A3B8; font-weight: 500; margin-left: 0.4rem;">
                                    • <i class="fa-regular fa-clock"></i> <?php echo date("M d, Y h:i A", strtotime($c['created_at'])); ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Tags & Badges -->
                    <div style="display: flex; gap: 0.45rem; align-items: center; flex-wrap: wrap;">
                        <?php if (!empty($c['audio_file'])): ?>
                            <span style="font-size: 0.75rem; padding: 0.25rem 0.65rem; border-radius: 999px; background: #FEF2F2; color: #DC2626; font-weight: 800; border: 1px solid #FECACA;">
                                <i class="fa-solid fa-microphone-lines"></i> Voice Recorded
                            </span>
                        <?php endif; ?>

                        <span class="feedback-tag <?php echo $tag_class; ?>" style="font-size: 0.75rem; padding: 0.25rem 0.65rem;">
                            <?php if ($c['feedback_type'] === 'Required Revision'): ?>
                                <i class="fa-solid fa-triangle-exclamation"></i>
                            <?php elseif ($c['feedback_type'] === 'Commendation'): ?>
                                <i class="fa-solid fa-award"></i>
                            <?php else: ?>
                                <i class="fa-solid fa-lightbulb"></i>
                            <?php endif; ?>
                            <?php echo htmlspecialchars($c['feedback_type']); ?>
                        </span>

                        <span style="font-size: 0.75rem; padding: 0.25rem 0.65rem; border-radius: 999px; background: #F1F5F9; color: #334155; font-weight: 700;">
                            <i class="fa-solid fa-bookmark" style="color: #047857; font-size: 0.7rem;"></i> <?php echo htmlspecialchars($c['category']); ?>
                        </span>

                        <?php if ($c['status'] === 'resolved'): ?>
                            <span class="badge badge-success" style="font-size: 0.75rem; padding: 0.25rem 0.65rem;">
                                <i class="fa-solid fa-check-double"></i> Resolved
                            </span>
                        <?php elseif ($c['status'] === 'addressed'): ?>
                            <span class="badge badge-info" style="font-size: 0.75rem; padding: 0.25rem 0.65rem; background: #EFF6FF; color: #1D4ED8; border: 1px solid #BFDBFE;">
                                <i class="fa-solid fa-clock-rotate-left"></i> Addressed by Student
                            </span>
                        <?php else: ?>
                            <span class="badge badge-pending" style="font-size: 0.75rem; padding: 0.25rem 0.65rem;">
                                <i class="fa-regular fa-circle"></i> Open
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recorded Voice Audio Player (if panelist recorded voice comment) -->
                <?php if (!empty($c['audio_file'])): ?>
                <div style="background: #F0FDF4; border: 1px solid #A7F3D0; border-radius: 8px; padding: 0.75rem 0.95rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem; flex-wrap: wrap; gap: 0.4rem;">
                        <span style="font-size: 0.8rem; font-weight: 800; color: #065F46;">
                            <i class="fa-solid fa-volume-high" style="color: #059669;"></i> Recorded Voice Comment from <?php echo htmlspecialchars($c['panel_name'] ?? 'Panel Evaluator'); ?>
                            <?php if (!empty($c['audio_duration'])): ?>
                                <span style="font-weight: 600; color: #047857;">(<?php echo gmdate("i:s", (int)$c['audio_duration']); ?>)</span>
                            <?php endif; ?>
                        </span>
                        <a href="<?php echo htmlspecialchars($c['audio_file']); ?>" download style="font-size: 0.75rem; color: #047857; font-weight: 700; text-decoration: none;">
                            <i class="fa-solid fa-download"></i> Download Audio
                        </a>
                    </div>
                    <audio controls preload="metadata" src="<?php echo htmlspecialchars($c['audio_file']); ?>" style="width: 100%; height: 38px;"></audio>
                </div>
                <?php endif; ?>

                <!-- Comment Content -->
                <?php if (!empty($c['comment_text']) && $c['comment_text'] !== '[Voice Recorded Comment]'): ?>
                <div class="adv-comment-text">
                    <?php echo nl2br(htmlspecialchars($c['comment_text'])); ?>
                </div>
                <?php endif; ?>

                <!-- Advisee Compliance Response (if submitted) -->
                <?php if (!empty($c['student_reply'])): ?>
                <div class="adv-student-reply-box">
                    <div class="adv-student-reply-title">
                        <i class="fa-solid fa-reply"></i> Advisee Group Compliance Action:
                    </div>
                    <div style="color: #1E293B; line-height: 1.5; white-space: pre-wrap;">
                        <?php echo nl2br(htmlspecialchars($c['student_reply'])); ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Adviser Guidance Note Section -->
                <?php if (!empty($c['adviser_notes'])): ?>
                <div class="adv-guidance-box">
                    <div class="adv-guidance-title">
                        <i class="fa-solid fa-chalkboard-user"></i> Your Advisory Guidance for Advisees:
                    </div>
                    <div style="color: #78350F; line-height: 1.5; white-space: pre-wrap; font-weight: 500;">
                        <?php echo nl2br(htmlspecialchars($c['adviser_notes'])); ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Expandable Form: Add/Edit Advisory Guidance -->
                <div style="border-top: 1px dashed #E2E8F0; padding-top: 0.5rem; margin-top: 0.25rem;">
                    <details style="font-size: 0.82rem;">
                        <summary style="color: #047857; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 0.35rem;">
                            <i class="fa-solid fa-pen-to-square"></i> 
                            <?php echo empty($c['adviser_notes']) ? 'Add Advisory Guidance Note for Student' : 'Edit Advisory Guidance Note'; ?>
                        </summary>
                        <form method="POST" action="action.php" class="adv-guidance-form">
                            <input type="hidden" name="save_adviser_comment_notes" value="1">
                            <input type="hidden" name="comment_id" value="<?php echo $c['id']; ?>">
                            <input type="hidden" name="return_tab" value="panel_comments">
                            <input type="hidden" name="student_id" value="<?php echo $selected_stu_id; ?>">

                            <textarea name="adviser_notes" class="mom-textarea" style="min-height: 65px; font-size: 0.85rem;" placeholder="Write advisory guidance or instructions for your supervisees regarding this panel recommendation..."><?php echo htmlspecialchars($c['adviser_notes'] ?? ''); ?></textarea>

                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="font-size: 0.72rem; color: #64748B;">
                                    <i class="fa-solid fa-circle-info"></i> Advisees will receive a real-time system notification with your guidance.
                                </span>
                                <button type="submit" class="adv-btn-save">
                                    <i class="fa-solid fa-paper-plane"></i> Save Guidance Note
                                </button>
                            </div>
                        </form>
                    </details>
                </div>

            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>
