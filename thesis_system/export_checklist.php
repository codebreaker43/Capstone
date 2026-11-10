<?php
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$uid = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'student';

// Fetch user data
$user_res = $conn->query("SELECT * FROM user WHERE id=$uid");
$user_data = $user_res ? $user_res->fetch_assoc() : [];

// Fetch profile data
$res = $conn->query("SELECT * FROM student_profile WHERE user_id=$uid");
$profile = $res ? $res->fetch_assoc() : [];
$group_id = (int)($profile['group_id'] ?? 0);

// Fetch group data & members
$my_group = null;
$my_group_members = [];
$group_name = "Group-1";

if ($group_id > 0) {
    $g_res = $conn->query("SELECT * FROM capstone_group WHERE id=$group_id");
    if ($g_res && $g_res->num_rows > 0) {
        $my_group = $g_res->fetch_assoc();
        $group_name = $my_group['group_name'] ?? 'Group-1';
        $my_group_members = get_group_members_roster($conn, $group_id);
    }
} else {
    $my_group_members = [
        [
            'student_id' => $uid,
            'name' => $user_data['name'] ?? $_SESSION['name'] ?? 'Student',
            'student_no' => $profile['student_id'] ?? '',
            'is_leader' => 1
        ]
    ];
}

// Fetch assigned adviser
$adv_name = "To be assigned";
$adv_q = $conn->query("SELECT u.name FROM match_request m JOIN user u ON m.adviser_id = u.id WHERE " . ($group_id > 0 ? "m.group_id = $group_id" : "m.student_id = $uid") . " AND m.status = 'accepted' LIMIT 1");
if ($adv_q && $adv_q->num_rows > 0) {
    $adv_name = $adv_q->fetch_assoc()['name'];
}

// Fetch project title
$thesis_title = !empty($my_group['thesis_topic']) ? $my_group['thesis_topic'] : (!empty($profile['thesis_topic']) ? $profile['thesis_topic'] : 'Proposed Capstone Research Project');

// Fetch checklist items
$items = get_student_checklist_items($conn, $uid, $group_id);
$stats = get_checklist_statistics($items);

// Determine filename: Group-1 (Villanueva-Lerio-Paderes-Mr. Bojocan).doc
$filename = generate_checklist_group_filename($group_name, $my_group_members, $adv_name);

// Format date
$generated_date = date("F d, Y");

// Output headers for Microsoft Word
header("Content-Type: application/msword; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Cache-Control: max-age=0, no-cache, no-store, must-revalidate");
header("Pragma: public");
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" 
      xmlns:w="urn:schemas-microsoft-com:office:word" 
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>IT-199 Requirements Checklist - <?php echo htmlspecialchars($group_name); ?></title>
    <!--[if gte mso 9]>
    <xml>
        <w:WordDocument>
            <w:View>Print</w:View>
            <w:Zoom>100</w:Zoom>
            <w:DoNotOptimizeForBrowser/>
        </w:WordDocument>
    </xml>
    <![endif]-->
    <style>
        @page {
            size: 8.5in 11.0in;
            margin: 0.8in 0.8in 0.8in 0.8in;
            mso-header-margin: 0.5in;
            mso-footer-margin: 0.5in;
        }
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 11pt;
            line-height: 1.35;
            color: #000000;
        }
        .header-section {
            text-align: center;
            margin-bottom: 18pt;
        }
        .univ-title {
            font-size: 12pt;
            font-weight: bold;
            text-transform: uppercase;
            margin: 0;
        }
        .college-title {
            font-size: 11pt;
            font-weight: bold;
            margin: 2pt 0;
        }
        .dept-title {
            font-size: 10.5pt;
            font-style: italic;
            margin: 0;
        }
        .doc-title {
            font-size: 14pt;
            font-weight: bold;
            margin-top: 14pt;
            margin-bottom: 2pt;
            text-transform: uppercase;
            color: #064E3B;
        }
        .doc-subtitle {
            font-size: 12pt;
            font-weight: bold;
            letter-spacing: 1px;
            margin-bottom: 12pt;
        }
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14pt;
            font-size: 10.5pt;
        }
        .meta-table td {
            padding: 4pt 6pt;
            vertical-align: top;
        }
        .instructions-box {
            border: 1.5pt solid #064E3B;
            background-color: #F4FBF7;
            padding: 8pt 10pt;
            margin-bottom: 14pt;
            font-size: 10pt;
        }
        .instructions-box strong {
            color: #064E3B;
        }
        .checklist-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18pt;
            font-size: 10pt;
        }
        .checklist-table th {
            border: 1pt solid #000000;
            background-color: #047857;
            color: #FFFFFF;
            font-weight: bold;
            padding: 6pt 5pt;
            text-align: center;
            vertical-align: middle;
        }
        .checklist-table td {
            border: 1pt solid #000000;
            padding: 6pt 6pt;
            vertical-align: top;
        }
        .status-cell {
            text-align: center;
            font-size: 12pt;
            font-weight: bold;
            vertical-align: middle;
        }
        .status-done {
            color: #047857;
        }
        .status-fail {
            color: #B91C1C;
        }
        .status-na {
            color: #4B5563;
        }
        .sign-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 24pt;
            font-size: 10.5pt;
        }
        .sign-table td {
            padding: 10pt 8pt;
            vertical-align: top;
            width: 33.33%;
        }
        .sign-line {
            border-bottom: 1pt solid #000000;
            margin-top: 32pt;
            margin-bottom: 4pt;
            width: 90%;
        }
    </style>
</head>
<body>

    <!-- University & Document Header -->
    <div class="header-section">
        <p class="univ-title">Republic of the Philippines</p>
        <p class="univ-title">Caraga State University Cabadbaran City</p>
        <p class="college-title">College of Engineering and Information Technology</p>
        <p class="dept-title">Department of Information Technology</p>
        
        <div class="doc-title">IT-199 (Capstone Project and Research)</div>
        <div class="doc-subtitle">*** Requirements Checklist ***</div>
    </div>

    <!-- Metadata Information Box -->
    <table class="meta-table">
        <tr>
            <td style="width: 18%; font-weight: bold;">Group Name:</td>
            <td style="width: 42%; font-weight: bold; color: #064E3B;"><?php echo htmlspecialchars($group_name); ?></td>
            <td style="width: 18%; font-weight: bold;">Date Generated:</td>
            <td style="width: 22%;"><?php echo htmlspecialchars($generated_date); ?></td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Capstone Title:</td>
            <td colspan="3"><?php echo htmlspecialchars($thesis_title); ?></td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Group Members:</td>
            <td>
                <?php 
                $m_list = [];
                foreach ($my_group_members as $m) {
                    $m_list[] = htmlspecialchars($m['name']) . (!empty($m['student_no']) ? ' (' . htmlspecialchars($m['student_no']) . ')' : '') . ($m['is_leader'] ? ' [Leader]' : '');
                }
                echo implode('<br>', $m_list);
                ?>
            </td>
            <td style="font-weight: bold;">Faculty Adviser:</td>
            <td><?php echo htmlspecialchars($adv_name); ?></td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Compliance Rate:</td>
            <td colspan="3">
                <strong><?php echo $stats['percentage']; ?>% Completed</strong> 
                (<?php echo $stats['completed']; ?> of <?php echo $stats['active_required']; ?> active requirements verified)
            </td>
        </tr>
    </table>

    <!-- Official Instructions Callout Box -->
    <div class="instructions-box">
        <strong>Instructions:</strong> Please upload your electronic copies of the required evidence in PDF format in the column labeled "Attached Link." Make sure the scanned documents are clear and readable. All pieces of evidence must also be properly stored and accessible in your designated group folder.<br><br>
        This Word document checklist of requirements must also be included in your group folder. The filename of the checklist should be your group name (e.g., <em>Group-1 (Villanueva-Lerio-Paderes-Mr. Bojocan)</em>).<br><br>
        <strong>Important Notice:</strong> Strictly follow the instructions. Missing or incomplete attachments will result in an <strong>"Incomplete" (INC)</strong> grade for the subject IT-199.
    </div>

    <!-- Official Requirements Table -->
    <table class="checklist-table">
        <thead>
            <tr>
                <th style="width: 5%;">No.</th>
                <th style="width: 24%;">Requirement</th>
                <th style="width: 38%;">Description/Evidence</th>
                <th style="width: 23%;">Attached Link</th>
                <th style="width: 10%;">Status (✔/✘)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <?php 
                    $stat = $item['status'] ?? 'incomplete';
                    $link = trim($item['attached_link'] ?? '');
                    $is_na = ($stat === 'not_applicable' || strtolower($link) === 'n/a');
                    $is_done = ($stat === 'completed');
                    
                    if ($is_done) {
                        $stat_symbol = "✔";
                        $stat_class = "status-done";
                    } elseif ($is_na) {
                        $stat_symbol = "N/A";
                        $stat_class = "status-na";
                    } else {
                        $stat_symbol = "✘";
                        $stat_class = "status-fail";
                    }
                ?>
                <tr>
                    <td style="text-align: center; font-weight: bold;">
                        <?php echo (int)$item['item_no']; ?>
                    </td>
                    <td>
                        <strong><?php echo htmlspecialchars($item['requirement_title']); ?></strong>
                    </td>
                    <td>
                        <?php echo nl2br(htmlspecialchars($item['description'])); ?>
                        <?php if (!empty($item['notes'])): ?>
                            <div style="font-size: 8.5pt; color: #4B5563; margin-top: 3pt;">
                                <em>Remarks: <?php echo htmlspecialchars($item['notes']); ?></em>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td style="word-break: break-all; font-size: 8.5pt;">
                        <?php if (empty($link)): ?>
                            <span style="color: #9CA3AF; font-style: italic;">[Not Attached]</span>
                        <?php elseif (strtolower($link) === 'n/a'): ?>
                            <span style="color: #6B7280; font-weight: bold;">N/A</span>
                        <?php elseif (preg_match('/^https?:\/\//i', $link)): ?>
                            <a href="<?php echo htmlspecialchars($link); ?>" target="_blank" style="color: #047857; text-decoration: underline;">
                                <?php echo htmlspecialchars($link); ?>
                            </a>
                        <?php else: ?>
                            <span style="color: #065F46; font-weight: bold;">
                                <?php echo htmlspecialchars(basename($link)); ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="status-cell <?php echo $stat_class; ?>">
                        <?php echo $stat_symbol; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Verification and Signatures -->
    <table class="sign-table">
        <tr>
            <td>
                <strong>Prepared & Submitted by:</strong>
                <div class="sign-line"></div>
                <div><strong><?php echo htmlspecialchars($my_group_members[0]['name'] ?? 'Group Leader'); ?></strong></div>
                <div style="font-size: 9pt; color: #4B5563;">Lead Proponent / IT-199 Student</div>
            </td>
            <td>
                <strong>Endorsed & Verified by:</strong>
                <div class="sign-line"></div>
                <div><strong><?php echo htmlspecialchars($adv_name); ?></strong></div>
                <div style="font-size: 9pt; color: #4B5563;">Capstone Research Adviser</div>
            </td>
            <td>
                <strong>Accepted for Defense:</strong>
                <div class="sign-line"></div>
                <div><strong>CEIT IT Chairperson</strong></div>
                <div style="font-size: 9pt; color: #4B5563;">Department of Information Technology</div>
            </td>
        </tr>
    </table>

</body>
</html>
