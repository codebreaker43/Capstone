<?php
require 'db.php';

// 1. Alter `user` table
$check_acc = $conn->query("SHOW COLUMNS FROM user LIKE 'account_status'");
if($check_acc && $check_acc->num_rows == 0) {
    $conn->query("ALTER TABLE user ADD COLUMN account_status ENUM('active','pending','rejected') DEFAULT 'active'");
}

$check_dob = $conn->query("SHOW COLUMNS FROM user LIKE 'dob'");
if($check_dob && $check_dob->num_rows == 0) {
    $conn->query("ALTER TABLE user ADD COLUMN dob DATE DEFAULT NULL AFTER sex");
}

// Update existing users to 'active' just in case
$conn->query("UPDATE user SET account_status = 'active' WHERE account_status IS NULL");

// 2. Alter `adviser_profile` table
$check_dep = $conn->query("SHOW COLUMNS FROM adviser_profile LIKE 'department'");
if($check_dep && $check_dep->num_rows == 0) {
    $conn->query("ALTER TABLE adviser_profile ADD COLUMN department VARCHAR(100) DEFAULT NULL");
}
$check_kw = $conn->query("SHOW COLUMNS FROM adviser_profile LIKE 'research_keywords'");
if($check_kw && $check_kw->num_rows == 0) {
    $conn->query("ALTER TABLE adviser_profile ADD COLUMN research_keywords TEXT DEFAULT NULL");
}
$check_sch = $conn->query("SHOW COLUMNS FROM adviser_profile LIKE 'schedule'");
if($check_sch && $check_sch->num_rows == 0) {
    $conn->query("ALTER TABLE adviser_profile ADD COLUMN schedule VARCHAR(255) DEFAULT NULL");
}

// 3. Alter `match_request` table
$check_col = $conn->query("SHOW COLUMNS FROM match_request LIKE 'rejection_reason'");
if($check_col && $check_col->num_rows == 0) {
    $conn->query("ALTER TABLE match_request ADD COLUMN rejection_reason TEXT DEFAULT NULL");
}

$check_mr_grp = $conn->query("SHOW COLUMNS FROM match_request LIKE 'group_id'");
if($check_mr_grp && $check_mr_grp->num_rows == 0) {
    $conn->query("ALTER TABLE match_request ADD COLUMN group_id INT DEFAULT NULL");
}

// 4. Create `capstone_group` table
$conn->query("CREATE TABLE IF NOT EXISTS `capstone_group` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_name` varchar(150) NOT NULL,
  `leader_id` int(11) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `thesis_topic` text DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 5. Create `capstone_group_member` table
$conn->query("CREATE TABLE IF NOT EXISTS `capstone_group_member` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL UNIQUE,
  `is_leader` tinyint(1) DEFAULT 0,
  `joined_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `group_id` (`group_id`),
  KEY `student_id` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 6. Alter `student_profile` table
$check_sid = $conn->query("SHOW COLUMNS FROM student_profile LIKE 'student_id'");
if($check_sid && $check_sid->num_rows == 0) {
    $conn->query("ALTER TABLE student_profile ADD COLUMN student_id VARCHAR(50) DEFAULT NULL AFTER user_id");
}
$check_grp = $conn->query("SHOW COLUMNS FROM student_profile LIKE 'group_id'");
if($check_grp && $check_grp->num_rows == 0) {
    $conn->query("ALTER TABLE student_profile ADD COLUMN group_id INT DEFAULT NULL");
}
$check_ldr = $conn->query("SHOW COLUMNS FROM student_profile LIKE 'is_group_leader'");
if($check_ldr && $check_ldr->num_rows == 0) {
    $conn->query("ALTER TABLE student_profile ADD COLUMN is_group_leader TINYINT(1) DEFAULT 0");
}

// 7. Create `document_submissions` table for student document & paper review workflow
$conn->query("CREATE TABLE IF NOT EXISTS `document_submissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `group_id` int(11) DEFAULT NULL,
  `adviser_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `doc_type` varchar(100) NOT NULL,
  `version` varchar(20) DEFAULT 'v1.0',
  `file_path` varchar(255) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_size` int(11) DEFAULT 0,
  `file_ext` varchar(20) DEFAULT 'pdf',
  `description` text DEFAULT NULL,
  `status` enum('pending','under_review','needs_revision','approved','rejected') DEFAULT 'pending',
  `feedback` longtext DEFAULT NULL,
  `reviewed_file_path` varchar(255) DEFAULT NULL,
  `reviewed_file_name` varchar(255) DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `group_id` (`group_id`),
  KEY `adviser_id` (`adviser_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// 8. Add title_status to student_profile & capstone_group
$check_ts = $conn->query("SHOW COLUMNS FROM student_profile LIKE 'title_status'");
if($check_ts && $check_ts->num_rows == 0) {
    $conn->query("ALTER TABLE student_profile ADD COLUMN title_status ENUM('pending','approved','rejected') DEFAULT 'pending'");
    $conn->query("ALTER TABLE student_profile ADD COLUMN title_approved_at DATETIME DEFAULT NULL");
    $conn->query("ALTER TABLE student_profile ADD COLUMN title_approved_by INT DEFAULT NULL");
}
$check_gts = $conn->query("SHOW COLUMNS FROM capstone_group LIKE 'title_status'");
if($check_gts && $check_gts->num_rows == 0) {
    $conn->query("ALTER TABLE capstone_group ADD COLUMN title_status ENUM('pending','approved','rejected') DEFAULT 'pending'");
    $conn->query("ALTER TABLE capstone_group ADD COLUMN title_approved_at DATETIME DEFAULT NULL");
    $conn->query("ALTER TABLE capstone_group ADD COLUMN title_approved_by INT DEFAULT NULL");
}

// 9. Add abstract_file, abstract_name, abstract_text to student_profile & capstone_group
$check_abs = $conn->query("SHOW COLUMNS FROM student_profile LIKE 'abstract_file'");
if($check_abs && $check_abs->num_rows == 0) {
    $conn->query("ALTER TABLE student_profile ADD COLUMN abstract_file VARCHAR(255) DEFAULT NULL");
    $conn->query("ALTER TABLE student_profile ADD COLUMN abstract_name VARCHAR(255) DEFAULT NULL");
    $conn->query("ALTER TABLE student_profile ADD COLUMN abstract_text LONGTEXT DEFAULT NULL");
}
$check_gabs = $conn->query("SHOW COLUMNS FROM capstone_group LIKE 'abstract_file'");
if($check_gabs && $check_gabs->num_rows == 0) {
    $conn->query("ALTER TABLE capstone_group ADD COLUMN abstract_file VARCHAR(255) DEFAULT NULL");
    $conn->query("ALTER TABLE capstone_group ADD COLUMN abstract_name VARCHAR(255) DEFAULT NULL");
    $conn->query("ALTER TABLE capstone_group ADD COLUMN abstract_text LONGTEXT DEFAULT NULL");
}

// 9b. Add manuscript columns to student_profile & capstone_group
$check_sp_link = $conn->query("SHOW COLUMNS FROM student_profile LIKE 'shareable_link'");
if($check_sp_link && $check_sp_link->num_rows == 0) {
    $conn->query("ALTER TABLE student_profile ADD COLUMN shareable_link VARCHAR(255) DEFAULT NULL");
}
$check_sp_mname = $conn->query("SHOW COLUMNS FROM student_profile LIKE 'manuscript_name'");
if($check_sp_mname && $check_sp_mname->num_rows == 0) {
    $conn->query("ALTER TABLE student_profile ADD COLUMN manuscript_name VARCHAR(255) DEFAULT NULL");
}
$check_sp_mstage = $conn->query("SHOW COLUMNS FROM student_profile LIKE 'manuscript_stage'");
if($check_sp_mstage && $check_sp_mstage->num_rows == 0) {
    $conn->query("ALTER TABLE student_profile ADD COLUMN manuscript_stage VARCHAR(100) DEFAULT 'Proposal Defense'");
}
$check_sp_mnotes = $conn->query("SHOW COLUMNS FROM student_profile LIKE 'manuscript_notes'");
if($check_sp_mnotes && $check_sp_mnotes->num_rows == 0) {
    $conn->query("ALTER TABLE student_profile ADD COLUMN manuscript_notes TEXT DEFAULT NULL");
}
$check_sp_mupdated = $conn->query("SHOW COLUMNS FROM student_profile LIKE 'manuscript_updated_at'");
if($check_sp_mupdated && $check_sp_mupdated->num_rows == 0) {
    $conn->query("ALTER TABLE student_profile ADD COLUMN manuscript_updated_at DATETIME DEFAULT NULL");
}

$check_cg_mfile = $conn->query("SHOW COLUMNS FROM capstone_group LIKE 'manuscript_file'");
if($check_cg_mfile && $check_cg_mfile->num_rows == 0) {
    $conn->query("ALTER TABLE capstone_group ADD COLUMN manuscript_file VARCHAR(255) DEFAULT NULL");
}
$check_cg_link = $conn->query("SHOW COLUMNS FROM capstone_group LIKE 'shareable_link'");
if($check_cg_link && $check_cg_link->num_rows == 0) {
    $conn->query("ALTER TABLE capstone_group ADD COLUMN shareable_link VARCHAR(255) DEFAULT NULL");
}
$check_cg_mname = $conn->query("SHOW COLUMNS FROM capstone_group LIKE 'manuscript_name'");
if($check_cg_mname && $check_cg_mname->num_rows == 0) {
    $conn->query("ALTER TABLE capstone_group ADD COLUMN manuscript_name VARCHAR(255) DEFAULT NULL");
}
$check_cg_mstage = $conn->query("SHOW COLUMNS FROM capstone_group LIKE 'manuscript_stage'");
if($check_cg_mstage && $check_cg_mstage->num_rows == 0) {
    $conn->query("ALTER TABLE capstone_group ADD COLUMN manuscript_stage VARCHAR(100) DEFAULT 'Proposal Defense'");
}
$check_cg_mnotes = $conn->query("SHOW COLUMNS FROM capstone_group LIKE 'manuscript_notes'");
if($check_cg_mnotes && $check_cg_mnotes->num_rows == 0) {
    $conn->query("ALTER TABLE capstone_group ADD COLUMN manuscript_notes TEXT DEFAULT NULL");
}
$check_cg_mupdated = $conn->query("SHOW COLUMNS FROM capstone_group LIKE 'manuscript_updated_at'");
if($check_cg_mupdated && $check_cg_mupdated->num_rows == 0) {
    $conn->query("ALTER TABLE capstone_group ADD COLUMN manuscript_updated_at DATETIME DEFAULT NULL");
}

// 10. Add group_id to thesis_panel
$check_tp_grp = $conn->query("SHOW COLUMNS FROM thesis_panel LIKE 'group_id'");
if($check_tp_grp && $check_tp_grp->num_rows == 0) {
    $conn->query("ALTER TABLE thesis_panel ADD COLUMN group_id INT DEFAULT NULL AFTER student_id, ADD INDEX (group_id)");
}

// 11. Create capstone_group_updates table for shared team updates, activity logs, and notice board
$conn->query("CREATE TABLE IF NOT EXISTS `capstone_group_updates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `author_name` varchar(150) NOT NULL,
  `author_role` varchar(50) DEFAULT 'Member',
  `update_type` varchar(50) DEFAULT 'note',
  `title` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `group_id` (`group_id`),
  KEY `user_id` (`user_id`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// 12. Link all existing groups, leaders, and members to establish unified shared dashboard
$all_groups = $conn->query("SELECT * FROM capstone_group");
if ($all_groups) {
    while ($g = $all_groups->fetch_assoc()) {
        $gid = (int)$g['id'];
        $lid = (int)$g['leader_id'];
        $g_topic = $conn->real_escape_string($g['thesis_topic'] ?? '');
        $g_tstat = $g['title_status'] ?? 'pending';

        // 12a. Ensure leader has group_id and is in capstone_group_member
        $conn->query("UPDATE student_profile SET group_id = $gid, is_group_leader = 1 WHERE user_id = $lid");
        $chk_l = $conn->query("SELECT id FROM capstone_group_member WHERE group_id = $gid AND student_id = $lid");
        if (!$chk_l || $chk_l->num_rows == 0) {
            $luser = $conn->query("SELECT u.name, u.email, sp.student_id as student_no FROM user u LEFT JOIN student_profile sp ON u.id = sp.user_id WHERE u.id = $lid")->fetch_assoc();
            $lname = $conn->real_escape_string($luser['name'] ?? 'Group Leader');
            $lsno = $conn->real_escape_string($luser['student_no'] ?? '');
            $lemail = $conn->real_escape_string($luser['email'] ?? '');
            $conn->query("INSERT INTO capstone_group_member (group_id, student_id, member_name, student_no, email, is_leader) VALUES ($gid, $lid, '$lname', '$lsno', '$lemail', 1)");
        }

        // 12b. Link Member 2
        $m2_id_val = trim($g['member2_student_id'] ?? '');
        $m2_name_val = trim($g['member2_name'] ?? '');
        $m2_email_val = trim($g['member2_email'] ?? '');
        if (!empty($m2_id_val) || !empty($m2_name_val) || !empty($m2_email_val)) {
            $m2_id_esc = $conn->real_escape_string($m2_id_val);
            $m2_name_esc = $conn->real_escape_string($m2_name_val);
            $m2_email_esc = $conn->real_escape_string($m2_email_val);
            $u2 = $conn->query("SELECT u.id, u.name, u.email, sp.student_id as student_no FROM user u LEFT JOIN student_profile sp ON u.id = sp.user_id WHERE (sp.student_id = '$m2_id_esc' AND '$m2_id_esc' != '') OR (u.email = '$m2_email_esc' AND '$m2_email_esc' != '') OR (u.name = '$m2_name_esc' AND '$m2_name_esc' != '') LIMIT 1")->fetch_assoc();
            if ($u2) {
                $uid2 = (int)$u2['id'];
                $conn->query("UPDATE student_profile SET group_id = $gid, is_group_leader = 0 WHERE user_id = $uid2");
                $chk_gm2 = $conn->query("SELECT id FROM capstone_group_member WHERE group_id = $gid AND (student_id = $uid2 OR (student_no = '$m2_id_esc' AND '$m2_id_esc' != '')) LIMIT 1");
                if ($chk_gm2 && $chk_gm2->num_rows > 0) {
                    $conn->query("UPDATE capstone_group_member SET student_id = $uid2, member_name = '" . $conn->real_escape_string($u2['name']) . "', student_no = '" . $conn->real_escape_string($u2['student_no'] ?? $m2_id_val) . "', email = '" . $conn->real_escape_string($u2['email']) . "' WHERE id = " . (int)$chk_gm2->fetch_assoc()['id']);
                } else {
                    $conn->query("INSERT INTO capstone_group_member (group_id, student_id, member_name, student_no, email, is_leader) VALUES ($gid, $uid2, '" . $conn->real_escape_string($u2['name']) . "', '" . $conn->real_escape_string($u2['student_no'] ?? $m2_id_val) . "', '" . $conn->real_escape_string($u2['email']) . "', 0)");
                }
            }
        }

        // 12c. Link Member 3
        $m3_id_val = trim($g['member3_student_id'] ?? '');
        $m3_name_val = trim($g['member3_name'] ?? '');
        $m3_email_val = trim($g['member3_email'] ?? '');
        if (!empty($m3_id_val) || !empty($m3_name_val) || !empty($m3_email_val)) {
            $m3_id_esc = $conn->real_escape_string($m3_id_val);
            $m3_name_esc = $conn->real_escape_string($m3_name_val);
            $m3_email_esc = $conn->real_escape_string($m3_email_val);
            $u3 = $conn->query("SELECT u.id, u.name, u.email, sp.student_id as student_no FROM user u LEFT JOIN student_profile sp ON u.id = sp.user_id WHERE (sp.student_id = '$m3_id_esc' AND '$m3_id_esc' != '') OR (u.email = '$m3_email_esc' AND '$m3_email_esc' != '') OR (u.name = '$m3_name_esc' AND '$m3_name_esc' != '') LIMIT 1")->fetch_assoc();
            if ($u3) {
                $uid3 = (int)$u3['id'];
                $conn->query("UPDATE student_profile SET group_id = $gid, is_group_leader = 0 WHERE user_id = $uid3");
                $chk_gm3 = $conn->query("SELECT id FROM capstone_group_member WHERE group_id = $gid AND (student_id = $uid3 OR (student_no = '$m3_id_esc' AND '$m3_id_esc' != '')) LIMIT 1");
                if ($chk_gm3 && $chk_gm3->num_rows > 0) {
                    $conn->query("UPDATE capstone_group_member SET student_id = $uid3, member_name = '" . $conn->real_escape_string($u3['name']) . "', student_no = '" . $conn->real_escape_string($u3['student_no'] ?? $m3_id_val) . "', email = '" . $conn->real_escape_string($u3['email']) . "' WHERE id = " . (int)$chk_gm3->fetch_assoc()['id']);
                } else {
                    $conn->query("INSERT INTO capstone_group_member (group_id, student_id, member_name, student_no, email, is_leader) VALUES ($gid, $uid3, '" . $conn->real_escape_string($u3['name']) . "', '" . $conn->real_escape_string($u3['student_no'] ?? $m3_id_val) . "', '" . $conn->real_escape_string($u3['email']) . "', 0)");
                }
            }
        }

        // 12d. Backfill group_id on match_request, document_submissions, and thesis_panel
        $conn->query("UPDATE match_request SET group_id = $gid WHERE student_id = $lid AND (group_id IS NULL OR group_id = 0)");
        $conn->query("UPDATE document_submissions SET group_id = $gid WHERE student_id = $lid AND (group_id IS NULL OR group_id = 0)");
        $conn->query("UPDATE thesis_panel SET group_id = $gid WHERE student_id = $lid AND (group_id IS NULL OR group_id = 0)");
    }
}

// 13. Create `panel_research_comments` table for structured panel comments, suggestions, and feedback
$conn->query("CREATE TABLE IF NOT EXISTS `panel_research_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `group_id` int(11) DEFAULT NULL,
  `panel_id` int(11) NOT NULL,
  `thesis_panel_id` int(11) DEFAULT NULL,
  `category` varchar(100) NOT NULL DEFAULT 'General Feedback',
  `feedback_type` enum('Suggestion','Required Revision','Commendation','Clarification','Question') NOT NULL DEFAULT 'Suggestion',
  `comment_text` longtext NOT NULL,
  `status` enum('open','addressed','resolved') NOT NULL DEFAULT 'open',
  `student_reply` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `group_id` (`group_id`),
  KEY `panel_id` (`panel_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// 14. Create `defense_meetings` table for session schedule and tracking
$conn->query("CREATE TABLE IF NOT EXISTS `defense_meetings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `student_id` int(11) NOT NULL,
  `group_id` int(11) DEFAULT NULL,
  `defense_stage` enum('Title Proposal','Outline Defense','Pre-Oral Defense','Final Oral Defense') NOT NULL DEFAULT 'Final Oral Defense',
  `meeting_date` date NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `venue` varchar(150) DEFAULT 'CEIT Defense Hall / Virtual Room',
  `panel_chair_id` int(11) DEFAULT NULL,
  `panel_members` text DEFAULT NULL,
  `adviser_name` varchar(150) DEFAULT NULL,
  `status` enum('scheduled','in_progress','completed','archived') NOT NULL DEFAULT 'in_progress',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `group_id` (`group_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// 15. Create `defense_transcripts` table for automated voice-to-text and recorded discussions
$conn->query("CREATE TABLE IF NOT EXISTS `defense_transcripts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `meeting_id` int(11) NOT NULL,
  `speaker_name` varchar(150) NOT NULL,
  `speaker_role` enum('Panel Chair','Panel Member','Adviser','Student Presenter','Documenter/Secretary','Other') NOT NULL DEFAULT 'Panel Member',
  `transcript_text` longtext NOT NULL,
  `timestamp_marker` varchar(50) DEFAULT NULL,
  `is_voice_generated` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `meeting_id` (`meeting_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// 16. Create `defense_meeting_minutes` table for Minutes of the Meeting (MOM)
$conn->query("CREATE TABLE IF NOT EXISTS `defense_meeting_minutes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `meeting_id` int(11) NOT NULL UNIQUE,
  `student_id` int(11) NOT NULL,
  `group_id` int(11) DEFAULT NULL,
  `document_title` varchar(255) NOT NULL,
  `discussion_summary` longtext DEFAULT NULL,
  `decision_verdict` enum('Approved without Revisions','Approved with Minor Revisions','Approved with Major Revisions','Re-defense Required','Disapproved','Deferred') NOT NULL DEFAULT 'Approved with Minor Revisions',
  `grade_average` decimal(5,2) DEFAULT NULL,
  `recommendations` longtext DEFAULT NULL,
  `action_items` longtext DEFAULT NULL,
  `notes` longtext DEFAULT NULL,
  `recorded_by_name` varchar(150) DEFAULT NULL,
  `approved_by_chair` varchar(150) DEFAULT NULL,
  `status` enum('draft','under_review','finalized','signed_off') NOT NULL DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `group_id` (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// 17. Ensure extra columns on `defense_meetings`
$check_dm_adv_id = $conn->query("SHOW COLUMNS FROM defense_meetings LIKE 'adviser_id'");
if($check_dm_adv_id && $check_dm_adv_id->num_rows == 0) {
    $conn->query("ALTER TABLE defense_meetings ADD COLUMN adviser_id INT DEFAULT NULL AFTER adviser_name");
}
$check_dm_link = $conn->query("SHOW COLUMNS FROM defense_meetings LIKE 'meeting_link'");
if($check_dm_link && $check_dm_link->num_rows == 0) {
    $conn->query("ALTER TABLE defense_meetings ADD COLUMN meeting_link VARCHAR(255) DEFAULT NULL AFTER venue");
}
$check_dm_notes = $conn->query("SHOW COLUMNS FROM defense_meetings LIKE 'notes'");
if($check_dm_notes && $check_dm_notes->num_rows == 0) {
    $conn->query("ALTER TABLE defense_meetings ADD COLUMN notes TEXT DEFAULT NULL AFTER status");
}
$check_dm_mom_doc_id = $conn->query("SHOW COLUMNS FROM defense_meetings LIKE 'mom_documenter_id'");
if($check_dm_mom_doc_id && $check_dm_mom_doc_id->num_rows == 0) {
    $conn->query("ALTER TABLE defense_meetings ADD COLUMN mom_documenter_id INT DEFAULT NULL AFTER panel_chair_id");
}
$check_dm_mom_doc_name = $conn->query("SHOW COLUMNS FROM defense_meetings LIKE 'mom_documenter_name'");
if($check_dm_mom_doc_name && $check_dm_mom_doc_name->num_rows == 0) {
    $conn->query("ALTER TABLE defense_meetings ADD COLUMN mom_documenter_name VARCHAR(150) DEFAULT NULL AFTER mom_documenter_id");
}

// 18. Create `system_notifications` table for real-time schedule, defense, and system alerts
$conn->query("CREATE TABLE IF NOT EXISTS `system_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'defense_schedule',
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `is_read` (`is_read`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// 19. Ensure `adviser_notes` column on `panel_research_comments`
$check_prc_adv_notes = $conn->query("SHOW COLUMNS FROM panel_research_comments LIKE 'adviser_notes'");
if($check_prc_adv_notes && $check_prc_adv_notes->num_rows == 0) {
    $conn->query("ALTER TABLE panel_research_comments ADD COLUMN adviser_notes TEXT DEFAULT NULL AFTER student_reply");
}

// 20. Create `capstone_checklist_items` table for IT-199 Requirements Checklist
$conn->query("CREATE TABLE IF NOT EXISTS `capstone_checklist_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` int(11) DEFAULT NULL,
  `student_id` int(11) DEFAULT NULL,
  `item_no` int(11) NOT NULL,
  `requirement_title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `attached_link` text DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `status` enum('completed','incomplete','in_progress','not_applicable') DEFAULT 'incomplete',
  `notes` text DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `group_id` (`group_id`),
  KEY `student_id` (`student_id`),
  KEY `item_no` (`item_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// 21. Add academic_year, course_code, and defense_category to defense_meetings
$check_dm_ay = $conn->query("SHOW COLUMNS FROM defense_meetings LIKE 'academic_year'");
if($check_dm_ay && $check_dm_ay->num_rows == 0) {
    $conn->query("ALTER TABLE defense_meetings ADD COLUMN academic_year VARCHAR(50) DEFAULT NULL AFTER defense_stage");
}
$check_dm_cc = $conn->query("SHOW COLUMNS FROM defense_meetings LIKE 'course_code'");
if($check_dm_cc && $check_dm_cc->num_rows == 0) {
    $conn->query("ALTER TABLE defense_meetings ADD COLUMN course_code VARCHAR(50) DEFAULT 'IT 199' AFTER academic_year");
}
$check_dm_cat = $conn->query("SHOW COLUMNS FROM defense_meetings LIKE 'defense_category'");
if($check_dm_cat && $check_dm_cat->num_rows == 0) {
    $conn->query("ALTER TABLE defense_meetings ADD COLUMN defense_category ENUM('Proposal Defense','Final Defense') DEFAULT 'Final Defense' AFTER course_code");
}

// Add academic_year & course_code to student_profile & capstone_group
$check_sp_ay = $conn->query("SHOW COLUMNS FROM student_profile LIKE 'academic_year'");
if($check_sp_ay && $check_sp_ay->num_rows == 0) {
    $conn->query("ALTER TABLE student_profile ADD COLUMN academic_year VARCHAR(50) DEFAULT '2026-2027', ADD COLUMN course_code VARCHAR(50) DEFAULT 'IT 199'");
}
$check_cg_ay = $conn->query("SHOW COLUMNS FROM capstone_group LIKE 'academic_year'");
if($check_cg_ay && $check_cg_ay->num_rows == 0) {
    $conn->query("ALTER TABLE capstone_group ADD COLUMN academic_year VARCHAR(50) DEFAULT '2026-2027', ADD COLUMN course_code VARCHAR(50) DEFAULT 'IT 199'");
}

// Backfill existing defense_meetings
$dm_rows = $conn->query("SELECT id, meeting_date, defense_stage, academic_year, course_code FROM defense_meetings");
if ($dm_rows) {
    while ($dm = $dm_rows->fetch_assoc()) {
        $mid = (int)$dm['id'];
        $stg = $dm['defense_stage'];
        $is_prop = in_array($stg, ['Title Proposal', 'Outline Defense', 'Pre-Oral Defense', 'Proposal Defense']);
        $cc = $is_prop ? 'IT 198' : 'IT 199';
        $cat = $is_prop ? 'Proposal Defense' : 'Final Defense';

        $mdate = $dm['meeting_date'];
        $calc_ay = '';
        if (!empty($mdate) && $mdate !== '0000-00-00') {
            $yr = (int)date('Y', strtotime($mdate));
            $mo = (int)date('n', strtotime($mdate));
            $calc_ay = ($mo >= 8) ? "{$yr}-" . ($yr + 1) : ($yr - 1) . "-{$yr}";
        } else {
            $calc_ay = '2026-2027';
        }

        $ay_val = !empty($dm['academic_year']) ? $dm['academic_year'] : $calc_ay;
        $conn->query("UPDATE defense_meetings SET academic_year = '$ay_val', course_code = '$cc', defense_category = '$cat' WHERE id = $mid");
    }
}

// 22. Enable 'secretary' role on `user` table
$conn->query("ALTER TABLE `user` MODIFY COLUMN `role` ENUM('student', 'adviser', 'admin', 'panel', 'secretary') NOT NULL DEFAULT 'student'");

// 23. Create `secretary_profile` table
$conn->query("CREATE TABLE IF NOT EXISTS `secretary_profile` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL UNIQUE,
  `department` varchar(100) DEFAULT 'CEIT Secretariat',
  `office` varchar(150) DEFAULT 'CEIT Dean Office & Defense Secretariat',
  `phone` varchar(30) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Seed demo secretary account if none exists
$check_sec = $conn->query("SELECT id FROM `user` WHERE `role` = 'secretary' LIMIT 1");
if ($check_sec && $check_sec->num_rows == 0) {
    $sec_email = 'secretary@csucc.edu.ph';
    $chk_u = $conn->query("SELECT id FROM `user` WHERE `email` = '$sec_email'");
    if (!$chk_u || $chk_u->num_rows == 0) {
        $sec_pass = password_hash('password123', PASSWORD_BCRYPT);
        $conn->query("INSERT INTO `user` (firstname, lastname, sex, dob, name, email, phone, address, password, role, account_status) 
                      VALUES ('Maria', 'Santos', 'Female', '1995-05-15', 'Maria Santos', '$sec_email', '09123456780', 'CSU Cabadbaran Campus', '$sec_pass', 'secretary', 'active')");
        $new_sec_id = $conn->insert_id;
        $conn->query("INSERT INTO `secretary_profile` (user_id, department, office) VALUES ($new_sec_id, 'CEIT Secretariat', 'CEIT Defense Secretariat Office')");
    }
}

// 24. Ensure `department` column on `defense_meetings`
$check_dm_dept = $conn->query("SHOW COLUMNS FROM defense_meetings LIKE 'department'");
if($check_dm_dept && $check_dm_dept->num_rows == 0) {
    $conn->query("ALTER TABLE defense_meetings ADD COLUMN department VARCHAR(100) DEFAULT NULL AFTER group_id");
}

// Backfill department and normalize course codes
$conn->query("UPDATE defense_meetings dm 
    LEFT JOIN student_profile sp ON dm.student_id = sp.user_id 
    LEFT JOIN capstone_group cg ON dm.group_id = cg.id 
    SET dm.department = COALESCE(NULLIF(sp.department, ''), cg.department, 'Information Technology') 
    WHERE dm.department IS NULL OR dm.department = ''");

// 25. Ensure Adviser Consultation Process tables and columns
if (function_exists('ensure_consultation_schema')) {
    ensure_consultation_schema($conn);
}

echo "Database altered successfully.";
?>
