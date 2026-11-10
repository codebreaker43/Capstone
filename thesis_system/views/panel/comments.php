<?php
// PANEL COMMENTS PANEL VIEW
// Allows panel members to enter, review, filter, and manage feedback, suggestions, and revisions for submitted research.

$panel_id = $_SESSION['user_id'];
$panel_name = $_SESSION['name'];

// Fetch all students assigned to this panelist
$assignments_q = $conn->query("
    SELECT tp.id as tp_id, tp.student_id, tp.status as grading_status, tp.grade,
           u.name as stu_name, u.email as stu_email,
           COALESCE(NULLIF(sp.thesis_topic, ''), cg.thesis_topic, 'Capstone Research') as thesis_topic,
           COALESCE(NULLIF(sp.manuscript_file, ''), cg.manuscript_file) as manuscript_file,
           COALESCE(NULLIF(sp.shareable_link, ''), cg.shareable_link) as shareable_link,
           COALESCE(NULLIF(sp.manuscript_stage, ''), cg.manuscript_stage) as manuscript_stage,
           sp.department, sp.group_id,
           cg.group_name
    FROM thesis_panel tp
    JOIN user u ON tp.student_id = u.id
    JOIN student_profile sp ON u.id = sp.user_id
    LEFT JOIN capstone_group cg ON sp.group_id = cg.id
    WHERE tp.panel_id = $panel_id
    ORDER BY u.name ASC
");

$assigned_list = [];
while ($a = $assignments_q->fetch_assoc()) {
    $assigned_list[] = $a;
}

// Determine active student
$selected_stu_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
if (!$selected_stu_id && isset($_GET['tp_id'])) {
    $target_tp = (int)$_GET['tp_id'];
    foreach ($assigned_list as $al) {
        if ($al['tp_id'] == $target_tp) {
            $selected_stu_id = (int)$al['student_id'];
            break;
        }
    }
}
if (!$selected_stu_id && count($assigned_list) > 0) {
    $selected_stu_id = (int)$assigned_list[0]['student_id'];
}

// Find active student info
$active_student = null;
foreach ($assigned_list as $al) {
    if ((int)$al['student_id'] === $selected_stu_id) {
        $active_student = $al;
        break;
    }
}

// If student selected, fetch all comments for this student/group
$comments = [];
$active_group_id = null;
if ($active_student) {
    $active_group_id = !empty($active_student['group_id']) ? (int)$active_student['group_id'] : null;
    $where_comment = $active_group_id ? "(prc.student_id = $selected_stu_id OR prc.group_id = $active_group_id)" : "prc.student_id = $selected_stu_id";

    $cq = $conn->query("
        SELECT prc.*, u.name as author_name, u.role as author_role
        FROM panel_research_comments prc
        JOIN user u ON prc.panel_id = u.id
        WHERE $where_comment
        ORDER BY prc.id DESC
    ");
    if ($cq) {
        while ($c = $cq->fetch_assoc()) {
            $comments[] = $c;
        }
    }
}

// Counts
$total_comments = count($comments);
$revisions_count = 0;
$resolved_count = 0;
foreach ($comments as $c) {
    if ($c['feedback_type'] === 'Required Revision') $revisions_count++;
    if ($c['status'] === 'resolved') $resolved_count++;
}
?>

<link rel="stylesheet" href="css/meeting_doc.css?v=<?php echo time(); ?>">

<div class="meeting-doc-wrapper">
    <!-- Header Banner -->
    <div class="meeting-header-card">
        <div class="meeting-title-row">
            <div>
                <h1><i class="fa-solid fa-comments"></i> Panel Comments & Research Feedback</h1>
                <p>Provide structured evaluations, suggestions, and revisions across capstone chapters.</p>
            </div>
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
                <span class="session-badge-pill">
                    <i class="fa-solid fa-user-tie"></i> Evaluator: <?php echo htmlspecialchars($panel_name); ?>
                </span>
                <?php if($active_student): ?>
                <a href="?tab=meeting_doc&student_id=<?php echo $selected_stu_id; ?>" class="btn-primary" style="text-decoration:none; padding: 0.4rem 0.9rem; font-size: 0.8rem; background: #059669; border-color: #059669;">
                    <i class="fa-solid fa-microphone-lines"></i> Open Defense MOM & Voice
                </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if($active_student): ?>
        <div class="candidate-strip">
            <div class="strip-item">
                <div class="label"><i class="fa-solid fa-user-graduate"></i> Student Candidate</div>
                <div class="value"><?php echo htmlspecialchars($active_student['stu_name']); ?></div>
            </div>
            <div class="strip-item">
                <div class="label"><i class="fa-solid fa-book-bookmark"></i> Capstone Title</div>
                <div class="value"><?php echo htmlspecialchars($active_student['thesis_topic'] ?: 'Proposal Pending'); ?></div>
            </div>
            <div class="strip-item">
                <div class="label"><i class="fa-solid fa-building-columns"></i> Department</div>
                <div class="value"><?php echo htmlspecialchars($active_student['department'] ?: 'CEIT'); ?></div>
            </div>
            <div class="strip-item">
                <div class="label"><i class="fa-solid fa-users"></i> Capstone Group</div>
                <div class="value"><?php echo htmlspecialchars($active_student['group_name'] ?: 'Individual / Single'); ?></div>
            </div>
            <div class="strip-item" style="display: flex; flex-direction: column; justify-content: center; gap: 0.35rem;">
                <div class="label"><i class="fa-solid fa-file-lines"></i> Candidate Manuscript</div>
                <div style="display: flex; gap: 0.4rem; flex-wrap: wrap;">
                    <?php if(!empty($active_student['manuscript_file'])): ?>
                        <a href="<?php echo htmlspecialchars($active_student['manuscript_file']); ?>" target="_blank" class="btn" style="padding: 0.35rem 0.75rem; font-size: 0.76rem; background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; font-weight: 700; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 0.3rem;">
                            <i class="fa-solid fa-file-pdf"></i> View Manuscript
                        </a>
                    <?php endif; ?>
                    <?php if(!empty($active_student['shareable_link'])): ?>
                        <a href="<?php echo htmlspecialchars($active_student['shareable_link']); ?>" target="_blank" rel="noopener noreferrer" class="btn" style="padding: 0.35rem 0.75rem; font-size: 0.76rem; background: #EFF6FF; color: #2563EB; border: 1px solid #BFDBFE; font-weight: 700; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 0.3rem;">
                            <i class="fa-solid fa-arrow-up-right-from-square"></i> Open Live Doc
                        </a>
                    <?php endif; ?>
                    <?php if(empty($active_student['manuscript_file']) && empty($active_student['shareable_link'])): ?>
                        <span style="font-size: 0.74rem; color: #94A3B8; font-style: italic;">No manuscript uploaded yet</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Candidate Selector Bar -->
    <div class="card" style="padding: 0.75rem 1.25rem;">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <label style="font-weight: 700; color: #1E293B; font-size: 0.85rem;"><i class="fa-solid fa-folder-user"></i> Select Candidate:</label>
                <select class="mom-select" style="width: auto; min-width: 260px;" onchange="window.location.href='?tab=comments&student_id='+this.value">
                    <?php if(empty($assigned_list)): ?>
                        <option value="">No students assigned to your panel yet</option>
                    <?php else: ?>
                        <?php foreach($assigned_list as $al): ?>
                            <option value="<?php echo $al['student_id']; ?>" <?php echo $al['student_id'] == $selected_stu_id ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($al['stu_name']); ?> - <?php echo htmlspecialchars(mb_strimwidth($al['thesis_topic'] ?: 'No Topic', 0, 45, '...')); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div style="display: flex; gap: 0.5rem; align-items: center;">
                <span class="badge badge-info"><i class="fa-solid fa-comment-dots"></i> <?php echo $total_comments; ?> Comments</span>
                <span class="badge" style="background:#FEF3C7; color:#B45309;"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo $revisions_count; ?> Revisions</span>
                <span class="badge badge-success"><i class="fa-solid fa-check-double"></i> <?php echo $resolved_count; ?> Resolved</span>
            </div>
        </div>
    </div>

    <?php if(!$active_student): ?>
        <div class="card" style="text-align: center; padding: 4rem 1rem;">
            <i class="fa-solid fa-user-clock fa-3x" style="color: #CBD5E1; margin-bottom: 1rem; display: block;"></i>
            <h3 style="color: #475569; margin-bottom: 0.5rem;">No Candidate Selected</h3>
            <p style="color: #94A3B8; font-size: 0.9rem;">Once students are assigned to your committee, you can submit and review structured research feedback here.</p>
        </div>
    <?php else: ?>

    <!-- Two-column Content: Left = Submit New Comment / Right = Existing Feedback Stream -->
    <div style="display: grid; grid-template-columns: 380px 1fr; gap: 1.25rem; align-items: start;">
        
        <!-- Left: Enter New Feedback with Voice Recording & Typing -->
        <div class="doc-card">
            <div class="doc-card-header">
                <h2><i class="fa-solid fa-microphone-lines" style="color: #047857;"></i> Record & Type Feedback</h2>
                <span style="font-size: 0.72rem; color: #047857; font-weight: 700; background: #ECFDF5; padding: 2px 8px; border-radius: 999px; border: 1px solid #A7F3D0;">Sent to Student & Adviser</span>
            </div>
            <div class="doc-card-body">
                <form id="commentForm" onsubmit="submitCommentAjax(event)" enctype="multipart/form-data">
                    <input type="hidden" name="api_action" value="add_panel_comment">
                    <input type="hidden" name="student_id" value="<?php echo $selected_stu_id; ?>">
                    <input type="hidden" name="group_id" value="<?php echo $active_group_id ? $active_group_id : ''; ?>">
                    <input type="hidden" name="thesis_panel_id" value="<?php echo $active_student['tp_id']; ?>">
                    <input type="hidden" name="audio_duration" id="audioDurationInput" value="">
                    <input type="hidden" name="audio_base64" id="audioBase64Input" value="">

                    <div style="margin-bottom: 0.85rem;">
                        <label class="mom-label"><i class="fa-solid fa-layer-group"></i> Research Component / Chapter</label>
                        <select name="category" id="commentCategory" class="mom-select" required>
                            <option value="General Feedback">General Feedback & Presentation</option>
                            <option value="Title & Abstract">Title, Keywords & Abstract</option>
                            <option value="Chapter 1: Background">Chapter 1: Background & Problem Statement</option>
                            <option value="Chapter 2: Literature Review">Chapter 2: Literature Review (RRL)</option>
                            <option value="Chapter 3: Methodology">Chapter 3: Methodology & System Architecture</option>
                            <option value="Chapter 4: Results & Discussion">Chapter 4: Results, Analysis & Discussion</option>
                            <option value="Chapter 5: Introduction">Chapter 5: Introduction (Final Chapter)</option>
                            <option value="Chapter 6: Conclusion">Chapter 6: Conclusion & Recommendations</option>
                            <option value="Defense Presentation">Oral Defense Q&A Performance</option>
                        </select>
                    </div>

                    <div style="margin-bottom: 0.85rem;">
                        <label class="mom-label"><i class="fa-solid fa-tag"></i> Feedback Type & Severity</label>
                        <select name="feedback_type" id="commentType" class="mom-select" required>
                            <option value="Suggestion">💡 Suggestion (Constructive Recommendation)</option>
                            <option value="Required Revision">⚠️ Required Revision (Mandatory Fix)</option>
                            <option value="Commendation">⭐ Commendation (Commendable Work)</option>
                            <option value="Clarification">❓ Clarification / Inquiry</option>
                            <option value="Question">❓ Defense Question for Candidate</option>
                        </select>
                    </div>

                    <!-- 1. VOICE RECORDING SECTION -->
                    <div style="margin-bottom: 0.95rem; background: #F8FAFC; border: 1.5px solid #CBD5E1; border-radius: 10px; padding: 0.85rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; flex-wrap: wrap; gap: 0.35rem;">
                            <label class="mom-label" style="margin: 0; color: #0F172A; font-size: 0.84rem;">
                                <i class="fa-solid fa-microphone" style="color: #DC2626;"></i> 1. Record Voice Comment <span style="font-weight: 500; color: #64748B; font-size: 0.74rem;">(Audio Record)</span>
                            </label>
                            <span id="recTimerBadge" style="display: none; background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; font-size: 0.75rem; font-weight: 800; padding: 2px 8px; border-radius: 999px; font-family: monospace;">
                                <i class="fa-solid fa-circle fa-beat-fade" style="font-size: 0.55rem;"></i> REC <span id="recTimerText">00:00</span>
                            </span>
                        </div>

                        <div style="display: flex; gap: 0.45rem; flex-wrap: wrap; align-items: center;">
                            <button type="button" id="recordVoiceBtn" onclick="toggleVoiceRecording()" style="flex: 1; background: #047857; color: #FFFFFF; font-weight: 700; padding: 0.5rem 0.85rem; border: none; font-size: 0.82rem; border-radius: 7px; display: inline-flex; align-items: center; justify-content: center; gap: 0.45rem; cursor: pointer; transition: all 0.2s;">
                                <i class="fa-solid fa-microphone"></i> <span id="recordVoiceLabel">Start Voice Record</span>
                            </button>

                            <label for="uploadVoiceInput" style="background: #FFFFFF; color: #334155; border: 1px solid #CBD5E1; font-weight: 700; padding: 0.5rem 0.7rem; font-size: 0.78rem; border-radius: 7px; display: inline-flex; align-items: center; gap: 0.35rem; cursor: pointer; margin: 0;" title="Or upload an audio file (.mp3, .wav, .m4a, .webm)">
                                <i class="fa-solid fa-upload" style="color: #0284C7;"></i> Upload Audio
                            </label>
                            <input type="file" id="uploadVoiceInput" accept="audio/*" style="display: none;" onchange="handleAudioFileUpload(this)">
                        </div>

                        <div id="recLiveHint" style="display: none; font-size: 0.75rem; color: #DC2626; margin-top: 0.45rem; font-weight: 600;">
                            <i class="fa-solid fa-waveform-lines"></i> Recording your voice now... Click <strong>Stop Recording</strong> when done.
                        </div>

                        <!-- Recorded Audio Playback & Re-record Box -->
                        <div id="voicePreviewBox" style="display: none; margin-top: 0.65rem; background: #ECFDF5; border: 1px solid #A7F3D0; border-radius: 8px; padding: 0.65rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
                                <span style="font-size: 0.76rem; font-weight: 800; color: #065F46;">
                                    <i class="fa-solid fa-circle-check" style="color: #059669;"></i> Voice Comment Ready (<span id="voicePreviewDuration">0s</span>)
                                </span>
                                <button type="button" onclick="clearVoiceRecording()" style="background: #FEE2E2; color: #991B1B; border: 1px solid #FECACA; border-radius: 5px; padding: 2px 8px; font-size: 0.72rem; font-weight: 700; cursor: pointer;">
                                    <i class="fa-solid fa-trash-can"></i> Remove / Re-record
                                </button>
                            </div>
                            <audio id="voicePreviewPlayer" controls style="width: 100%; height: 36px;"></audio>
                        </div>
                    </div>

                    <!-- 2. TYPING COMMENT SECTION -->
                    <div style="margin-bottom: 1rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem; flex-wrap: wrap; gap: 0.35rem;">
                            <label class="mom-label" style="margin: 0;"><i class="fa-solid fa-keyboard" style="color: #047857;"></i> 2. Typed Comment & Evaluation Notes</label>
                            <span style="font-size: 0.72rem; color: #64748B;">Type comment, record voice, or both</span>
                        </div>
                        <textarea name="comment_text" id="commentText" class="mom-textarea" style="min-height: 125px;" placeholder="Type your comment, suggestions, page references, or questions here (optional if you recorded a voice comment above)..."></textarea>
                        <div style="font-size: 0.73rem; color: #047857; margin-top: 0.35rem; background: #F0FDF4; border: 1px solid #BBF7D0; padding: 0.4rem 0.6rem; border-radius: 6px;">
                            <i class="fa-solid fa-share-nodes"></i> Recorded voice audio & typed comments are automatically sent to both the <strong>Student Dashboard</strong> and <strong>Adviser Dashboard</strong>.
                        </div>
                    </div>

                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                        <button type="submit" id="submitCommentBtn" class="btn-primary" style="flex: 1; justify-content: center; padding: 0.65rem 1rem;">
                            <i class="fa-solid fa-paper-plane"></i> Send Recorded / Typed Comment
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Right: Filter Chips & Comments List -->
        <div style="display: flex; flex-direction: column; gap: 1rem;">
            
            <!-- Filters & Actions -->
            <div class="comments-filter-bar">
                <span style="font-size: 0.8rem; font-weight: 700; color: #64748B; margin-right: 0.35rem;"><i class="fa-solid fa-filter"></i> Filter:</span>
                <button type="button" class="filter-chip active" onclick="filterComments('all', this)">All (<?php echo $total_comments; ?>)</button>
                <button type="button" class="filter-chip" onclick="filterComments('Required Revision', this)">Revisions (<?php echo $revisions_count; ?>)</button>
                <button type="button" class="filter-chip" onclick="filterComments('Suggestion', this)">Suggestions</button>
                <button type="button" class="filter-chip" onclick="filterComments('Commendation', this)">Commendations</button>
                <button type="button" class="filter-chip" onclick="filterComments('resolved', this)">Resolved</button>
                <button type="button" class="filter-chip" onclick="filterComments('open', this)">Open Status</button>

                <div style="margin-left: auto;">
                    <button type="button" class="btn btn-outline" onclick="window.print()" style="padding: 0.3rem 0.75rem; font-size: 0.78rem;">
                        <i class="fa-solid fa-print"></i> Print Feedback
                    </button>
                </div>
            </div>

            <!-- Comment Cards List -->
            <div id="commentsContainer" style="display: flex; flex-direction: column; gap: 0.85rem;">
                <?php if(empty($comments)): ?>
                    <div id="emptyCommentsCard" class="doc-card" style="text-align: center; padding: 3rem 1rem; color: #94A3B8;">
                        <i class="fa-solid fa-comment-slash fa-3x" style="color: #CBD5E1; margin-bottom: 0.75rem; display: block;"></i>
                        <h4 style="color: #475569; margin-bottom: 0.35rem;">No Comments Submitted Yet</h4>
                        <p style="font-size: 0.85rem; margin: 0;">Use the form on the left to record a voice comment or type your feedback, required revisions, and commendations.</p>
                    </div>
                <?php else: ?>
                    <?php foreach($comments as $c): 
                        $tag_class = 'suggestion';
                        if ($c['feedback_type'] === 'Required Revision') $tag_class = 'revision';
                        if ($c['feedback_type'] === 'Commendation') $tag_class = 'commendation';
                        if ($c['feedback_type'] === 'Clarification' || $c['feedback_type'] === 'Question') $tag_class = 'clarification';

                        $is_author = ($c['panel_id'] == $panel_id || $_SESSION['role'] === 'admin');
                    ?>
                    <div class="comment-card <?php echo $c['feedback_type'] === 'Required Revision' ? 'revision-needed' : ''; ?> <?php echo $c['status'] === 'resolved' ? 'resolved' : ''; ?>" data-category="<?php echo htmlspecialchars($c['category']); ?>" data-type="<?php echo htmlspecialchars($c['feedback_type']); ?>" data-status="<?php echo htmlspecialchars($c['status']); ?>" id="comment-item-<?php echo $c['id']; ?>">
                        
                        <div class="comment-card-top">
                            <div class="comment-author-info">
                                <div class="comment-avatar">
                                    <?php echo strtoupper(substr($c['author_name'], 0, 1)); ?>
                                </div>
                                <div>
                                    <div style="font-weight: 700; color: #0F172A; font-size: 0.92rem;">
                                        <?php echo htmlspecialchars($c['author_name']); ?>
                                        <span style="font-size: 0.72rem; color: #047857; font-weight: 600; margin-left: 0.3rem;">• Panel Evaluator</span>
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
                                    <?php if($c['feedback_type'] === 'Required Revision'): ?><i class="fa-solid fa-triangle-exclamation"></i>
                                    <?php elseif($c['feedback_type'] === 'Commendation'): ?><i class="fa-solid fa-award"></i>
                                    <?php else: ?><i class="fa-solid fa-lightbulb"></i><?php endif; ?>
                                    <?php echo htmlspecialchars($c['feedback_type']); ?>
                                </span>

                                <span style="font-size: 0.75rem; padding: 0.2rem 0.6rem; border-radius: 999px; background: #F1F5F9; color: #475569; font-weight: 600; border: 1px solid #E2E8F0;">
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
                                    <i class="fa-solid fa-volume-high" style="color: #059669;"></i> Recorded Voice Comment
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
                        <div class="transcript-text" style="font-size: 0.92rem; color: #1E293B; line-height: 1.55; white-space: pre-wrap; padding: 0.35rem 0;">
                            <?php echo nl2br(htmlspecialchars($c['comment_text'])); ?>
                        </div>
                        <?php endif; ?>

                        <?php if(!empty($c['student_reply'])): ?>
                        <div style="background: #F8FAFC; border-left: 3px solid #3B82F6; padding: 0.65rem 0.85rem; border-radius: 6px; font-size: 0.82rem;">
                            <strong style="color: #1E40AF;"><i class="fa-solid fa-reply"></i> Student / Group Response:</strong>
                            <div style="color: #334155; margin-top: 0.2rem;"><?php echo nl2br(htmlspecialchars($c['student_reply'])); ?></div>
                        </div>
                        <?php endif; ?>

                        <?php if(!empty($c['adviser_notes'])): ?>
                        <div style="background: #FFFBEB; border-left: 3px solid #D97706; padding: 0.65rem 0.85rem; border-radius: 6px; font-size: 0.82rem; margin-top: 0.35rem;">
                            <strong style="color: #92400E;"><i class="fa-solid fa-chalkboard-user"></i> Adviser Guidance Note:</strong>
                            <div style="color: #78350F; margin-top: 0.2rem;"><?php echo nl2br(htmlspecialchars($c['adviser_notes'])); ?></div>
                        </div>
                        <?php endif; ?>

                        <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #F1F5F9; padding-top: 0.5rem; margin-top: 0.25rem;">
                            <!-- Status Toggle -->
                            <div style="display: flex; gap: 0.35rem; align-items: center;">
                                <span style="font-size: 0.72rem; color: #64748B; font-weight: 600;">Mark Status:</span>
                                <button type="button" class="btn-transcript-act" onclick="updateCommentStatus(<?php echo $c['id']; ?>, 'open')" style="<?php echo $c['status']=='open'?'font-weight:700; color:#B45309;':''; ?>">Open</button>
                                <span style="color:#CBD5E1;">|</span>
                                <button type="button" class="btn-transcript-act" onclick="updateCommentStatus(<?php echo $c['id']; ?>, 'addressed')" style="<?php echo $c['status']=='addressed'?'font-weight:700; color:#1D4ED8;':''; ?>">Addressed</button>
                                <span style="color:#CBD5E1;">|</span>
                                <button type="button" class="btn-transcript-act" onclick="updateCommentStatus(<?php echo $c['id']; ?>, 'resolved')" style="<?php echo $c['status']=='resolved'?'font-weight:700; color:#047857;':''; ?>">Resolved</button>
                            </div>

                            <?php if($is_author): ?>
                            <button type="button" class="btn-transcript-act delete" onclick="deleteComment(<?php echo $c['id']; ?>)" title="Delete Comment">
                                <i class="fa-solid fa-trash-can"></i> Delete
                            </button>
                            <?php endif; ?>
                        </div>

                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <?php endif; ?>
</div>

<script>
// Filter comments by tag or status
function filterComments(criteria, btn) {
    document.querySelectorAll('.filter-chip').forEach(b => b.classList.remove('active'));
    if(btn) btn.classList.add('active');

    const cards = document.querySelectorAll('.comment-card');
    cards.forEach(card => {
        if(criteria === 'all') {
            card.style.display = 'flex';
        } else if(criteria === 'open' || criteria === 'resolved' || criteria === 'addressed') {
            if(card.getAttribute('data-status') === criteria) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        } else {
            if(card.getAttribute('data-type') === criteria || card.getAttribute('data-category') === criteria) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        }
    });
}

// =========================================================
// VOICE RECORDING (AUDIO RECORD) + TYPING FOR PANEL COMMENTS
// =========================================================
let mediaRecorder = null;
let audioChunks = [];
let recordedAudioBlob = null;
let recordedAudioExt = 'webm';
let isRecordingVoice = false;
let recStartTime = 0;
let recTimerInterval = null;
let recordedDurationSec = 0;

function formatRecTime(sec) {
    const m = Math.floor(sec / 60).toString().padStart(2, '0');
    const s = Math.floor(sec % 60).toString().padStart(2, '0');
    return `${m}:${s}`;
}

async function toggleVoiceRecording() {
    if (!isRecordingVoice) {
        await startVoiceRecording();
    } else {
        stopVoiceRecording();
    }
}

async function startVoiceRecording() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        alert("Your browser does not support direct microphone recording. You can click 'Upload Audio' to attach an audio recording file or type your comment below.");
        return;
    }

    try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        audioChunks = [];
        recordedAudioBlob = null;

        let mimeType = '';
        if (window.MediaRecorder) {
            if (MediaRecorder.isTypeSupported('audio/webm;codecs=opus')) {
                mimeType = 'audio/webm;codecs=opus';
                recordedAudioExt = 'webm';
            } else if (MediaRecorder.isTypeSupported('audio/webm')) {
                mimeType = 'audio/webm';
                recordedAudioExt = 'webm';
            } else if (MediaRecorder.isTypeSupported('audio/ogg;codecs=opus')) {
                mimeType = 'audio/ogg;codecs=opus';
                recordedAudioExt = 'ogg';
            } else if (MediaRecorder.isTypeSupported('audio/mp4')) {
                mimeType = 'audio/mp4';
                recordedAudioExt = 'm4a';
            }
        }

        mediaRecorder = mimeType ? new MediaRecorder(stream, { mimeType }) : new MediaRecorder(stream);

        mediaRecorder.ondataavailable = function(e) {
            if (e.data && e.data.size > 0) {
                audioChunks.push(e.data);
            }
        };

        mediaRecorder.onstop = function() {
            stream.getTracks().forEach(t => t.stop());
            const blobType = mimeType || 'audio/webm';
            recordedAudioBlob = new Blob(audioChunks, { type: blobType });
            const audioUrl = URL.createObjectURL(recordedAudioBlob);

            const player = document.getElementById('voicePreviewPlayer');
            const box = document.getElementById('voicePreviewBox');
            const durText = document.getElementById('voicePreviewDuration');
            const durInput = document.getElementById('audioDurationInput');

            if (player) player.src = audioUrl;
            if (box) box.style.display = 'block';
            if (durText) durText.textContent = formatRecTime(recordedDurationSec);
            if (durInput) durInput.value = recordedDurationSec;

            // Also populate base64 fallback
            const reader = new FileReader();
            reader.onloadend = function() {
                const b64Inp = document.getElementById('audioBase64Input');
                if (b64Inp) b64Inp.value = reader.result || '';
            };
            reader.readAsDataURL(recordedAudioBlob);
        };

        mediaRecorder.start();
        isRecordingVoice = true;
        recStartTime = Date.now();
        recordedDurationSec = 0;

        const btn = document.getElementById('recordVoiceBtn');
        const lbl = document.getElementById('recordVoiceLabel');
        const badge = document.getElementById('recTimerBadge');
        const timerTxt = document.getElementById('recTimerText');
        const hint = document.getElementById('recLiveHint');
        const previewBox = document.getElementById('voicePreviewBox');

        if (previewBox) previewBox.style.display = 'none';
        if (btn) btn.style.background = '#DC2626';
        if (lbl) lbl.innerHTML = 'Stop Recording';
        if (badge) badge.style.display = 'inline-block';
        if (timerTxt) timerTxt.textContent = '00:00';
        if (hint) hint.style.display = 'block';

        if (recTimerInterval) clearInterval(recTimerInterval);
        recTimerInterval = setInterval(() => {
            recordedDurationSec = Math.max(1, Math.floor((Date.now() - recStartTime) / 1000));
            if (timerTxt) timerTxt.textContent = formatRecTime(recordedDurationSec);
        }, 500);

    } catch (err) {
        console.error('Microphone error:', err);
        alert("Could not access microphone. Please allow microphone permission in your browser or use 'Upload Audio'.");
    }
}

function stopVoiceRecording() {
    isRecordingVoice = false;
    if (recTimerInterval) {
        clearInterval(recTimerInterval);
        recTimerInterval = null;
    }

    const btn = document.getElementById('recordVoiceBtn');
    const lbl = document.getElementById('recordVoiceLabel');
    const badge = document.getElementById('recTimerBadge');
    const hint = document.getElementById('recLiveHint');

    if (btn) btn.style.background = '#047857';
    if (lbl) lbl.innerHTML = 'Record Again';
    if (badge) badge.style.display = 'none';
    if (hint) hint.style.display = 'none';

    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
        try { mediaRecorder.stop(); } catch (e) {}
    }
}

function handleAudioFileUpload(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    recordedAudioBlob = file;
    const parts = file.name.split('.');
    recordedAudioExt = parts.length > 1 ? parts.pop().toLowerCase() : 'mp3';

    const audioUrl = URL.createObjectURL(file);
    const player = document.getElementById('voicePreviewPlayer');
    const box = document.getElementById('voicePreviewBox');
    const durText = document.getElementById('voicePreviewDuration');
    const durInput = document.getElementById('audioDurationInput');

    if (player) {
        player.src = audioUrl;
        player.onloadedmetadata = function() {
            if (player.duration && isFinite(player.duration)) {
                recordedDurationSec = Math.round(player.duration);
                if (durText) durText.textContent = formatRecTime(recordedDurationSec);
                if (durInput) durInput.value = recordedDurationSec;
            }
        };
    }
    if (box) box.style.display = 'block';
    if (durText) durText.textContent = 'Audio File Attached';

    const reader = new FileReader();
    reader.onloadend = function() {
        const b64Inp = document.getElementById('audioBase64Input');
        if (b64Inp) b64Inp.value = reader.result || '';
    };
    reader.readAsDataURL(file);
}

function clearVoiceRecording() {
    if (isRecordingVoice) {
        stopVoiceRecording();
    }
    recordedAudioBlob = null;
    recordedDurationSec = 0;

    const player = document.getElementById('voicePreviewPlayer');
    const box = document.getElementById('voicePreviewBox');
    const durInput = document.getElementById('audioDurationInput');
    const b64Inp = document.getElementById('audioBase64Input');
    const fileInp = document.getElementById('uploadVoiceInput');
    const lbl = document.getElementById('recordVoiceLabel');

    if (player) {
        player.pause();
        player.removeAttribute('src');
    }
    if (box) box.style.display = 'none';
    if (durInput) durInput.value = '';
    if (b64Inp) b64Inp.value = '';
    if (fileInp) fileInp.value = '';
    if (lbl) lbl.innerHTML = 'Start Voice Record';
}

// Submit Comment (Recorded Voice and/or Typed Text) via AJAX
function submitCommentAjax(e) {
    e.preventDefault();
    if (isRecordingVoice) {
        stopVoiceRecording();
        setTimeout(() => submitCommentAjax(e), 350);
        return;
    }

    const form = document.getElementById('commentForm');
    const commentText = document.getElementById('commentText').value.trim();
    const b64Val = document.getElementById('audioBase64Input').value.trim();

    if (!commentText && !recordedAudioBlob && !b64Val) {
        alert("Please record a voice comment or enter written feedback before submitting.");
        return;
    }

    const formData = new FormData(form);
    if (recordedAudioBlob) {
        const filename = recordedAudioBlob.name || ('voice_comment.' + recordedAudioExt);
        formData.append('audio_blob', recordedAudioBlob, filename);
    }

    const submitBtn = document.getElementById('submitCommentBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Sending to Student & Adviser...';

    fetch('api.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Send Recorded / Typed Comment';
        if (data.success) {
            document.getElementById('commentText').value = '';
            clearVoiceRecording();
            window.location.reload();
        } else {
            alert("Error: " + (data.error || 'Failed to submit comment.'));
        }
    })
    .catch(err => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Send Recorded / Typed Comment';
        console.error(err);
        // Fallback to regular submit
        form.action = 'action.php';
        form.method = 'POST';
        const hiddenInp = document.createElement('input');
        hiddenInp.type = 'hidden';
        hiddenInp.name = 'submit_panel_comment';
        hiddenInp.value = '1';
        form.appendChild(hiddenInp);
        form.submit();
    });
}

// Update Comment Status
function updateCommentStatus(commentId, newStatus) {
    const formData = new FormData();
    formData.append('api_action', 'update_comment_status');
    formData.append('comment_id', commentId);
    formData.append('status', newStatus);

    fetch('api.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            window.location.reload();
        } else {
            alert(data.error || 'Could not update status');
        }
    });
}

// Delete Comment
function deleteComment(commentId) {
    if(!confirm("Are you sure you want to delete this comment?")) return;

    const formData = new FormData();
    formData.append('api_action', 'delete_panel_comment');
    formData.append('comment_id', commentId);

    fetch('api.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            const el = document.getElementById('comment-item-' + commentId);
            if(el) el.remove();
        } else {
            alert(data.error || 'Failed to delete');
        }
    });
}
</script>
