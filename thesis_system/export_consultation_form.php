<?php
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$uid = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'student';
$cons_id = isset($_GET['cons_id']) ? (int)$_GET['cons_id'] : 0;
$target_student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : ($role === 'student' ? $uid : 0);
$target_group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;
$format = isset($_GET['format']) ? strtolower(trim($_GET['format'])) : 'html';

// If a specific consultation ID is provided, resolve student_id and group_id from it
if ($cons_id > 0) {
    $c_row = $conn->query("SELECT student_id, group_id FROM adviser_consultations WHERE id = $cons_id")->fetch_assoc();
    if ($c_row) {
        $target_student_id = (int)$c_row['student_id'];
        $target_group_id = (int)($c_row['group_id'] ?? 0);
    }
}

if ($target_student_id <= 0 && $role === 'student') {
    $target_student_id = $uid;
}

// Fetch student & group info
$stu_user = $conn->query("SELECT * FROM user WHERE id = $target_student_id")->fetch_assoc() ?: [];
$stu_prof = $conn->query("SELECT * FROM student_profile WHERE user_id = $target_student_id")->fetch_assoc() ?: [];
if ($target_group_id <= 0 && !empty($stu_prof['group_id'])) {
    $target_group_id = (int)$stu_prof['group_id'];
}

$my_group = null;
$my_group_members = [];
$group_name = "Individual Capstone Research";

if ($target_group_id > 0) {
    $g_res = $conn->query("SELECT * FROM capstone_group WHERE id = $target_group_id");
    if ($g_res && $g_res->num_rows > 0) {
        $my_group = $g_res->fetch_assoc();
        $group_name = $my_group['group_name'] ?? 'Group-' . $target_group_id;
        $my_group_members = get_group_members_roster($conn, $target_group_id);
    }
}
if (empty($my_group_members)) {
    $my_group_members = [
        [
            'student_id' => $target_student_id,
            'name' => $stu_user['name'] ?? 'Capstone Student',
            'student_no' => $stu_prof['student_id'] ?? '',
            'email' => $stu_user['email'] ?? '',
            'is_leader' => 1
        ]
    ];
}

// Fetch consultation summary
$summary = get_student_consultation_summary($conn, $target_student_id, $target_group_id);
$all_records = $summary['records'];

// Filter if a single cons_id was requested
$display_records = $all_records;
if ($cons_id > 0) {
    $display_records = array_values(array_filter($all_records, function($r) use ($cons_id) {
        return (int)$r['id'] === $cons_id;
    }));
}

// Resolve Adviser Name
$adv_name = "Assigned Research Adviser";
$adv_dept = $stu_prof['department'] ?? 'Information Technology';
if (!empty($display_records[0]['adviser_name'])) {
    $adv_name = $display_records[0]['adviser_name'];
} else {
    $grp_w = ($target_group_id > 0) ? "(m.group_id = $target_group_id OR m.student_id = $target_student_id)" : "m.student_id = $target_student_id";
    $adv_q = $conn->query("SELECT u.name, ap.department FROM match_request m JOIN user u ON m.adviser_id = u.id LEFT JOIN adviser_profile ap ON u.id = ap.user_id WHERE $grp_w ORDER BY (m.status='accepted') DESC, m.id DESC LIMIT 1");
    if ($adv_q && $adv_q->num_rows > 0) {
        $ar = $adv_q->fetch_assoc();
        $adv_name = $ar['name'];
        if (!empty($ar['department'])) $adv_dept = $ar['department'];
    }
}

$thesis_title = !empty($display_records[0]['research_title'])
    ? $display_records[0]['research_title']
    : (!empty($my_group['thesis_topic']) ? $my_group['thesis_topic'] : (!empty($stu_prof['thesis_topic']) ? $stu_prof['thesis_topic'] : 'Proposed Capstone Research Idea'));

$generated_date = date("F d, Y");
$doc_ref = ($cons_id > 0 && !empty($display_records[0]['reference_no']))
    ? $display_records[0]['reference_no']
    : 'CEIT-CONS-LOG-' . date('Y') . '-' . str_pad((string)$target_student_id, 4, '0', STR_PAD_LEFT);

if ($format === 'doc') {
    $clean_ref = preg_replace('/[^A-Za-z0-9_\-]/', '_', $doc_ref);
    $filename = "CSU_CEIT_Consultation_Form_{$clean_ref}.doc";
    header("Content-Type: application/msword; charset=UTF-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Cache-Control: max-age=0, no-cache, no-store, must-revalidate");
    header("Pragma: public");
}
?>
<!DOCTYPE html>
<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:w="urn:schemas-microsoft-com:office:word"
      xmlns="http://www.w3.org/TR/REC-html40"
      lang="en">
<head>
    <meta charset="UTF-8">
    <title>Official Adviser Consultation Form - <?php echo htmlspecialchars($doc_ref); ?></title>
    <?php if ($format !== 'doc'): ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <?php endif; ?>
    <style>
        @page {
            size: 8.5in 11.0in;
            margin: 0.65in 0.7in 0.65in 0.7in;
        }
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 11pt;
            line-height: 1.4;
            color: #0F172A;
            background: <?php echo ($format === 'doc') ? '#FFFFFF' : '#F1F5F9'; ?>;
            margin: 0;
            padding: <?php echo ($format === 'doc') ? '0' : '24px 16px'; ?>;
        }
        .page-sheet {
            max-width: 820px;
            margin: 0 auto;
            background: #FFFFFF;
            padding: <?php echo ($format === 'doc') ? '0' : '38px 46px'; ?>;
            box-shadow: <?php echo ($format === 'doc') ? 'none' : '0 10px 30px rgba(0,0,0,0.1)'; ?>;
            border-radius: <?php echo ($format === 'doc') ? '0' : '10px'; ?>;
            border: <?php echo ($format === 'doc') ? 'none' : '1px solid #CBD5E1'; ?>;
        }
        .no-print-bar {
            max-width: 820px;
            margin: 0 auto 16px auto;
            background: #064E3B;
            color: #FFFFFF;
            padding: 12px 20px;
            border-radius: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            font-family: 'Inter', sans-serif;
            box-shadow: 0 4px 12px rgba(6, 78, 59, 0.25);
        }
        .btn-bar {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            border: none;
        }
        .header-section {
            text-align: center;
            border-bottom: 2.5px solid #064E3B;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        .univ-title {
            font-size: 11.5pt;
            font-weight: bold;
            text-transform: uppercase;
            margin: 0;
            letter-spacing: 0.5px;
        }
        .college-title {
            font-size: 11pt;
            font-weight: bold;
            color: #064E3B;
            margin: 2px 0;
        }
        .dept-title {
            font-size: 10pt;
            font-style: italic;
            margin: 0;
            color: #334155;
        }
        .doc-title {
            font-size: 14pt;
            font-weight: bold;
            margin-top: 10px;
            margin-bottom: 2px;
            text-transform: uppercase;
            color: #064E3B;
            letter-spacing: 0.5px;
        }
        .doc-subtitle {
            font-size: 10pt;
            font-weight: bold;
            color: #475569;
        }
        .form-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
            font-size: 10.5pt;
        }
        .form-table th, .form-table td {
            border: 1pt solid #334155;
            padding: 6px 8px;
            vertical-align: top;
            text-align: left;
        }
        .form-table th {
            background-color: #F1F5F9;
            font-weight: bold;
            color: #0F172A;
            width: 24%;
        }
        .section-banner {
            background-color: #064E3B;
            color: #FFFFFF;
            font-weight: bold;
            font-size: 10.5pt;
            padding: 5px 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 14px;
            margin-bottom: 0;
            border: 1pt solid #064E3B;
        }
        .badge-print {
            display: inline-block;
            padding: 2px 8px;
            font-size: 9pt;
            font-weight: bold;
            border-radius: 4px;
            border: 1pt solid #047857;
            background: #ECFDF5;
            color: #065F46;
        }
        .sign-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 26px;
            font-size: 10.5pt;
        }
        .sign-table td {
            width: 50%;
            padding: 10px 14px;
            vertical-align: top;
            text-align: center;
        }
        .sign-line {
            border-bottom: 1pt solid #000000;
            margin: 32px auto 4px auto;
            width: 85%;
            font-weight: bold;
        }
        @media print {
            body { background: #FFFFFF; padding: 0; }
            .page-sheet { box-shadow: none; border: none; padding: 0; max-width: 100%; }
            .no-print-bar { display: none !important; }
        }
    </style>
</head>
<body>

<?php if ($format !== 'doc'): ?>
<div class="no-print-bar">
    <div>
        <div style="font-weight: 800; font-size: 14px;">
            <i class="fa-solid fa-file-signature" style="color: #FCD34D;"></i>
            Official CEIT Adviser Consultation Form & Documentation Copy
        </div>
        <div style="font-size: 12px; color: #A7F3D0;">
            Reference: <?php echo htmlspecialchars($doc_ref); ?> • Ready for printing, PDF archiving, or Word download
        </div>
    </div>
    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
        <button type="button" onclick="window.print()" class="btn-bar" style="background: #F59E0B; color: #0F172A;">
            <i class="fa-solid fa-print"></i> Print / Save as PDF
        </button>
        <a href="export_consultation_form.php?<?php echo http_build_query(array_merge($_GET, ['format' => 'doc'])); ?>" class="btn-bar" style="background: #10B981; color: #FFFFFF;">
            <i class="fa-solid fa-file-word"></i> Download Word (.doc)
        </a>
        <a href="<?php echo ($role === 'adviser') ? 'faculty_dashboard.php?tab=consultations' : 'student_dashboard.php?tab=consultations'; ?>" class="btn-bar" style="background: rgba(255,255,255,0.16); color: #FFFFFF; border: 1px solid rgba(255,255,255,0.3);">
            <i class="fa-solid fa-arrow-left"></i> Back to Portal
        </a>
    </div>
</div>
<?php endif; ?>

<div class="page-sheet">
    <!-- Official University Header -->
    <div class="header-section">
        <p class="univ-title">Republic of the Philippines</p>
        <p class="univ-title">Caraga State University Cabadbaran Campus</p>
        <p class="college-title">College of Engineering and Information Technology (CEIT)</p>
        <p class="dept-title">T. Curato St., Cabadbaran City, Agusan del Norte</p>
        <div class="doc-title">Research Adviser Consultation Form &amp; Progress Record</div>
        <div class="doc-subtitle">Official Academic Documentation &amp; Oral Defense Endorsement Record (Ref: <?php echo htmlspecialchars($doc_ref); ?>)</div>
    </div>

    <!-- Section I: Student / Group & Adviser Information -->
    <div class="section-banner">I. Proponent &amp; Research Adviser Information</div>
    <table class="form-table">
        <tr>
            <th>Student / Proponent(s):</th>
            <td>
                <?php
                $m_lines = [];
                foreach ($my_group_members as $m) {
                    $m_lines[] = '<strong>' . htmlspecialchars($m['name']) . '</strong>'
                        . (!empty($m['student_no']) ? ' (ID: ' . htmlspecialchars($m['student_no']) . ')' : '')
                        . (!empty($m['is_leader']) ? ' <em>[Leader]</em>' : '');
                }
                echo implode('<br>', $m_lines);
                ?>
            </td>
            <th>Date Generated:</th>
            <td><?php echo htmlspecialchars($generated_date); ?></td>
        </tr>
        <tr>
            <th>Capstone Team / Group:</th>
            <td><?php echo htmlspecialchars($group_name); ?></td>
            <th>Research Adviser:</th>
            <td><strong><?php echo htmlspecialchars($adv_name); ?></strong></td>
        </tr>
        <tr>
            <th>Proposed Research Title / Idea:</th>
            <td colspan="3"><strong><?php echo htmlspecialchars($thesis_title); ?></strong></td>
        </tr>
        <tr>
            <th>Consultation Process Status:</th>
            <td>
                <?php if ($summary['is_process_completed']): ?>
                    <span class="badge-print">✔ COMPLETED &amp; VERIFIED BY ADVISER</span>
                    <?php if (!empty($summary['completed_at'])): ?>
                        <div style="font-size: 9pt; color: #475569; margin-top: 2px;">Verified: <?php echo date('M d, Y g:i A', strtotime($summary['completed_at'])); ?></div>
                    <?php endif; ?>
                <?php else: ?>
                    <strong><?php echo strtoupper(str_replace('_', ' ', $summary['process_status'])); ?></strong>
                    (<?php echo (int)$summary['stats']['completed']; ?> Completed Session(s) of <?php echo (int)$summary['stats']['total']; ?> Requested)
                <?php endif; ?>
            </td>
            <th>Oral Defense Clearance:</th>
            <td>
                <?php if ($summary['oral_defense_approved']): ?>
                    <span class="badge-print">✔ APPROVED FOR ORAL DEFENSE</span>
                    <div style="font-size: 9pt; color: #065F46; margin-top: 2px;">
                        Stage: <strong><?php echo htmlspecialchars($summary['oral_defense_stage_approved']); ?></strong>
                    </div>
                <?php else: ?>
                    <span style="color: #B45309; font-weight: bold;">Pending Adviser Defense Approval</span>
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <!-- Section II: Detailed Consultation Form Record(s) -->
    <div class="section-banner">II. Consultation Request Form, Readiness Evaluation &amp; Recorded Outcomes</div>
    <?php if (empty($display_records)): ?>
        <table class="form-table">
            <tr>
                <td colspan="4" style="text-align: center; padding: 18px; color: #64748B;">
                    No consultation sessions have been recorded yet for this student/group.
                </td>
            </tr>
        </table>
    <?php else: ?>
        <?php foreach ($display_records as $idx => $rec): ?>
            <table class="form-table" style="margin-bottom: 16px;">
                <tr style="background-color: #ECFDF5;">
                    <td colspan="4" style="font-weight: bold; color: #064E3B; font-size: 10.5pt;">
                        CONSULTATION RECORD #<?php echo ($idx + 1); ?> — Reference No: <?php echo htmlspecialchars($rec['reference_no']); ?>
                        &nbsp;|&nbsp; Stage: <?php echo htmlspecialchars($rec['consultation_stage']); ?>
                        &nbsp;|&nbsp; Status: <?php echo strtoupper(str_replace('_', ' ', $rec['status'])); ?>
                    </td>
                </tr>
                <tr>
                    <th>Research Idea / Title Consulted:</th>
                    <td colspan="3"><strong><?php echo htmlspecialchars($rec['research_title']); ?></strong></td>
                </tr>
                <tr>
                    <th>Preferred Date &amp; Time:</th>
                    <td>
                        <?php echo date('F d, Y', strtotime($rec['preferred_date'])); ?>
                        <?php if (!empty($rec['preferred_time'])) echo ' (' . htmlspecialchars($rec['preferred_time']) . ')'; ?>
                    </td>
                    <th>Mode &amp; Venue / Link:</th>
                    <td>
                        <strong><?php echo htmlspecialchars($rec['consultation_mode']); ?></strong>
                        <?php if (!empty($rec['venue_or_link'])) echo ' — ' . htmlspecialchars($rec['venue_or_link']); ?>
                    </td>
                </tr>
                <tr>
                    <th>Student's Research Idea &amp; Concept Summary:</th>
                    <td colspan="3"><?php echo nl2br(htmlspecialchars($rec['research_idea_summary'])); ?></td>
                </tr>
                <tr>
                    <th>Specific Consultation Agenda / Questions:</th>
                    <td colspan="3"><?php echo nl2br(htmlspecialchars($rec['consultation_agenda'])); ?></td>
                </tr>
                <?php if (!empty($rec['current_progress'])): ?>
                <tr>
                    <th>Accomplished Work / Current Progress:</th>
                    <td colspan="3"><?php echo nl2br(htmlspecialchars($rec['current_progress'])); ?></td>
                </tr>
                <?php endif; ?>

                <!-- Pre-Acceptance Evaluation, Follow-Up Questions & Student Responses -->
                <tr>
                    <th>Adviser Readiness Evaluation:</th>
                    <td>
                        <strong><?php echo htmlspecialchars($rec['readiness_level'] ?? 'Pending Evaluation'); ?></strong>
                        <?php if ($rec['readiness_score'] !== null): ?>
                            (Readiness Score: <strong><?php echo (int)$rec['readiness_score']; ?>%</strong>)
                        <?php endif; ?>
                    </td>
                    <th>Confirmed Schedule:</th>
                    <td>
                        <?php echo !empty($rec['confirmed_schedule']) ? htmlspecialchars($rec['confirmed_schedule']) : (!empty($rec['accepted_at']) ? 'Accepted on ' . date('M d, Y', strtotime($rec['accepted_at'])) : 'Pending Confirmation'); ?>
                    </td>
                </tr>
                <?php if (!empty($rec['follow_up_questions']) || !empty($rec['adviser_preliminary_comments'])): ?>
                <tr>
                    <th>Adviser Pre-Acceptance Follow-Up Questions &amp; Comments:</th>
                    <td colspan="3">
                        <?php if (!empty($rec['follow_up_questions'])): ?>
                            <div><strong>Follow-Up Questions:</strong> <?php echo nl2br(htmlspecialchars($rec['follow_up_questions'])); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($rec['adviser_preliminary_comments'])): ?>
                            <div style="margin-top: 4px;"><strong>Preliminary Comments:</strong> <?php echo nl2br(htmlspecialchars($rec['adviser_preliminary_comments'])); ?></div>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>

                <?php if (!empty($rec['student_response'])): ?>
                <tr>
                    <th>Student's Response to Follow-Up Questions:</th>
                    <td colspan="3">
                        <?php echo nl2br(htmlspecialchars($rec['student_response'])); ?>
                        <?php if (!empty($rec['student_responded_at'])): ?>
                            <div style="font-size: 9pt; color: #64748B; margin-top: 2px;">Responded on: <?php echo date('M d, Y g:i A', strtotime($rec['student_responded_at'])); ?></div>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>

                <?php if (!empty($rec['adviser_review_feedback'])): ?>
                <tr>
                    <th>Adviser Review Feedback on Student Response:</th>
                    <td colspan="3"><?php echo nl2br(htmlspecialchars($rec['adviser_review_feedback'])); ?></td>
                </tr>
                <?php endif; ?>

                <!-- Consultation Outcome & Progress Monitoring -->
                <tr style="background-color: #F8FAFC;">
                    <th>Recorded Consultation Outcome:</th>
                    <td>
                        <strong><?php echo htmlspecialchars($rec['outcome_verdict'] ?? 'Pending'); ?></strong>
                        <?php if ($rec['progress_rating'] !== null): ?>
                            <br><span style="font-size: 9.5pt; color: #047857;">Progress Rating: <strong><?php echo (int)$rec['progress_rating']; ?>%</strong></span>
                        <?php endif; ?>
                    </td>
                    <th>Adviser Sign-Off:</th>
                    <td>
                        <?php if (!empty($rec['adviser_signed_off'])): ?>
                            <strong style="color: #047857;">✔ Officially Signed Off by Adviser</strong>
                            <?php if (!empty($rec['completed_at'])): ?>
                                <div style="font-size: 9pt; color: #475569;"><?php echo date('M d, Y g:i A', strtotime($rec['completed_at'])); ?></div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span style="color: #64748B;">Awaiting Consultation Completion</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if (!empty($rec['outcome_summary'])): ?>
                <tr>
                    <th>Discussion Summary &amp; Adviser Findings:</th>
                    <td colspan="3"><?php echo nl2br(htmlspecialchars($rec['outcome_summary'])); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($rec['action_items'])): ?>
                <tr>
                    <th>Required Action Items / Next Steps:</th>
                    <td colspan="3"><?php echo nl2br(htmlspecialchars($rec['action_items'])); ?></td>
                </tr>
                <?php endif; ?>
            </table>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Section III: Adviser Certification of Consultation Completion & Oral Defense Approval -->
    <div class="section-banner">III. Certification of Consultation Completion &amp; Oral Defense Endorsement</div>
    <table class="form-table">
        <tr>
            <td colspan="4" style="padding: 10px 12px; line-height: 1.5;">
                This is to certify that the above-named student proponent(s) have undergone the required research advising and consultation process in accordance with the academic policies of the College of Engineering and Information Technology (CEIT), Caraga State University Cabadbaran Campus.
                <?php if (!empty($summary['completion_notes'])): ?>
                    <div style="margin-top: 6px;"><strong>Adviser Verification Notes:</strong> <?php echo htmlspecialchars($summary['completion_notes']); ?></div>
                <?php endif; ?>
                <?php if ($summary['oral_defense_approved']): ?>
                    <div style="margin-top: 6px; color: #065F46;">
                        <strong>✔ ORAL DEFENSE ENDORSEMENT:</strong> The research adviser has confirmed completion of the necessary consultation process and officially approves the student/group for <strong><?php echo htmlspecialchars($summary['oral_defense_stage_approved']); ?></strong>.
                        <?php if (!empty($summary['oral_defense_remarks'])): ?>
                            <br><em>Endorsement Remarks: <?php echo htmlspecialchars($summary['oral_defense_remarks']); ?></em>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <!-- Signatures -->
    <table class="sign-table">
        <tr>
            <td>
                <div class="sign-line"><?php echo htmlspecialchars($stu_user['name'] ?? 'Student Proponent'); ?></div>
                <div><strong>Student Proponent / Group Leader</strong></div>
                <div style="font-size: 9.5pt; color: #475569;">Signature over Printed Name &amp; Date</div>
            </td>
            <td>
                <div class="sign-line"><?php echo htmlspecialchars($adv_name); ?></div>
                <div><strong> Capstone Research Adviser</strong></div>
                <div style="font-size: 9.5pt; color: #475569;">Signature over Printed Name &amp; Date</div>
            </td>
        </tr>
    </table>
</div>

</body>
</html>

