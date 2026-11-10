<?php
require 'db.php';

if(!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Quick Role Switcher for Multi-Role Users (e.g. same email for Panel & Adviser)
if (isset($_GET['switch_role_to'])) {
    $cur_uid = (int)$_SESSION['user_id'];
    $cur_u = $conn->query("SELECT email FROM user WHERE id = $cur_uid")->fetch_assoc();
    if ($cur_u && !empty($cur_u['email'])) {
        $target_uid = (int)$_GET['switch_role_to'];
        $chk = $conn->prepare("SELECT id, name, role, account_status FROM user WHERE id = ? AND email = ? AND account_status = 'active'");
        $chk->bind_param("is", $target_uid, $cur_u['email']);
        $chk->execute();
        $target_u = $chk->get_result()->fetch_assoc();
        if ($target_u) {
            $_SESSION['user_id'] = $target_u['id'];
            $_SESSION['role'] = $target_u['role'];
            $_SESSION['name'] = $target_u['name'];
            log_audit('SWITCH_ROLE', "User '{$target_u['name']}' switched role to " . ucfirst($target_u['role']) . ".", 'Authentication', 'success', ['role' => $target_u['role']], $target_u['id'], $target_u['name'], $target_u['role']);
            $_SESSION['msg'] = "Switched to " . ucfirst($target_u['role']) . " portal!";
            header("Location: dashboard.php");
            exit();
        }
    }
    header("Location: dashboard.php");
    exit();
}

if(isset($_POST['update_profile'])) {
    $uid = $_SESSION['user_id'];
    if($_SESSION['role'] == 'student') {
        $topic = sanitize($_POST['thesis_topic']);
        
        $file_update = "";
        $uploaded_path = "";
        $uploaded_name = "";
        if(isset($_FILES['content_pdf']) && $_FILES['content_pdf']['error'] == 0) {
            $target_dir = "uploads/documents/";
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            $uploaded_name = basename($_FILES["content_pdf"]["name"]);
            $file_name = time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "_", $uploaded_name);
            $target_file = $target_dir . $file_name;
            $file_type = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));
            
            if($file_type == "pdf") {
                if (move_uploaded_file($_FILES["content_pdf"]["tmp_name"], $target_file)) {
                    $uploaded_path = sanitize($target_file);
                    $file_update = ", manuscript_file='$uploaded_path', paper_status='pending'";
                }
            }
        }
        
        $conn->query("UPDATE student_profile SET thesis_topic='$topic' $file_update WHERE user_id=$uid");

        if(!empty($uploaded_path)) {
            $adv_q = $conn->query("SELECT adviser_id FROM match_request WHERE student_id = $uid AND status = 'accepted' LIMIT 1");
            $aid = ($adv_q && $adv_q->num_rows > 0) ? (int)$adv_q->fetch_assoc()['adviser_id'] : "NULL";
            $sp_grp = $conn->query("SELECT group_id FROM student_profile WHERE user_id=$uid")->fetch_assoc();
            $gid = !empty($sp_grp['group_id']) ? (int)$sp_grp['group_id'] : "NULL";
            $fsize = file_exists(__DIR__ . '/' . $uploaded_path) ? filesize(__DIR__ . '/' . $uploaded_path) : 0;
            $esc_orig = sanitize($uploaded_name);
            $conn->query("INSERT INTO document_submissions (student_id, group_id, adviser_id, title, doc_type, version, file_path, file_name, file_size, file_ext, description, status) 
                          VALUES ($uid, $gid, $aid, '$topic', 'Topic Proposal / Concept Paper', 'v1.0', '$uploaded_path', '$esc_orig', $fsize, 'pdf', 'Initial thesis proposal document', 'pending')");
            log_audit('DOCUMENT_SUBMISSION', "Student '{$_SESSION['name']}' uploaded proposal document '{$topic}'.", 'Capstone & Review', 'success');
        }

        $_SESSION['msg'] = "Capstone profile and document updated successfully!";
        header("Location: dashboard.php?tab=submission&show_ai=1");
        exit();
    } else {
        $exp = sanitize($_POST['expertise'] ?? '');
        $cap = isset($_POST['capacity']) ? (int)$_POST['capacity'] : 5;
        $dept = isset($_POST['department']) ? sanitize($_POST['department']) : '';
        $academic_degree = sanitize($_POST['academic_degree'] ?? '');
        $graduation_year = sanitize($_POST['graduation_year'] ?? '');
        $google_scholar_url = sanitize($_POST['google_scholar_url'] ?? '');
        $google_scholar_url = format_google_scholar_link($google_scholar_url, $_SESSION['name'] ?? '');
        $google_scholar_id = extract_google_scholar_user_id($google_scholar_url);
        $published_papers = sanitize($_POST['published_papers'] ?? '');
        $previous_advised_titles = sanitize($_POST['previous_advised_titles'] ?? '');

        // If Google Scholar URL provided and user requested sync or published_papers is empty, retrieve from Google Scholar
        if (!empty($google_scholar_url) && (empty($published_papers) || !empty($_POST['sync_scholar_now']))) {
            $gs_res = fetch_google_scholar_publications($google_scholar_url, $_SESSION['name'] ?? '', $dept, $exp);
            if (!empty($gs_res['formatted_publications'])) {
                $published_papers = sanitize($gs_res['formatted_publications']);
            }
            if (!empty($gs_res['google_scholar_id'])) {
                $google_scholar_id = sanitize($gs_res['google_scholar_id']);
            }
        }

        // Auto-identify research specializations from publications & supervised titles
        if ((!empty($published_papers) || !empty($previous_advised_titles)) && (!empty($_POST['auto_identify_specializations']) || empty($exp))) {
            $inferred = extract_specializations_from_publications($_POST['published_papers'] ?? $published_papers, $_POST['previous_advised_titles'] ?? $previous_advised_titles, $_POST['expertise'] ?? $exp);
            if (!empty($inferred['merged_expertise'])) {
                $exp = sanitize($inferred['merged_expertise']);
            }
        }

        $dept_sql = !empty($dept) ? ", department='$dept'" : "";
        $sync_sql = (!empty($published_papers) || !empty($google_scholar_url)) ? ", publications_last_synced=NOW()" : "";

        $conn->query("UPDATE adviser_profile SET 
            expertise='$exp', 
            capacity=$cap, 
            academic_degree='$academic_degree',
            graduation_year='$graduation_year',
            google_scholar_url='$google_scholar_url',
            google_scholar_id='$google_scholar_id',
            published_papers='$published_papers',
            previous_advised_titles='$previous_advised_titles'
            $dept_sql $sync_sql 
            WHERE user_id=$uid");

        $_SESSION['msg'] = "Faculty information, research specializations, Google Scholar publications, and previously advised titles updated successfully!";
        header("Location: dashboard.php?tab=expertise");
        exit();
    }
}

if(isset($_POST['update_settings'])) {
    $uid = (int)$_SESSION['user_id'];
    $cur_u = $conn->query("SELECT * FROM user WHERE id=$uid")->fetch_assoc();
    
    $firstname = isset($_POST['firstname']) ? sanitize($_POST['firstname']) : ($cur_u['firstname'] ?? '');
    $lastname = isset($_POST['lastname']) ? sanitize($_POST['lastname']) : ($cur_u['lastname'] ?? '');
    $name = trim($firstname . ' ' . $lastname);
    if(empty($name)) $name = $cur_u['name'] ?? 'User';
    
    $email = isset($_POST['email']) ? sanitize(trim($_POST['email'])) : ($cur_u['email'] ?? '');
    $phone = isset($_POST['phone']) ? sanitize($_POST['phone']) : ($cur_u['phone'] ?? '');
    $address = isset($_POST['address']) ? sanitize($_POST['address']) : ($cur_u['address'] ?? '');
    $dob = isset($_POST['dob']) ? sanitize($_POST['dob']) : ($cur_u['dob'] ?? '');
    $new_password = $_POST['new_password'] ?? '';

    $settings_redirect = ($_SESSION['role'] === 'secretary') ? 'secretary_dashboard.php?tab=settings' : 'dashboard.php?tab=settings';

    // Validate email format if provided
    if (!empty($email)) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['msg'] = "Error: Invalid email format! Please provide a valid Gmail or university email address.";
            header("Location: $settings_redirect");
            exit();
        }
        
        // Check if email already used by another user with the same role
        $check_email = $conn->prepare("SELECT id FROM user WHERE email = ? AND role = ? AND id != ?");
        $check_email->bind_param("ssi", $email, $_SESSION['role'], $uid);
        $check_email->execute();
        if ($check_email->get_result()->num_rows > 0) {
            $_SESSION['msg'] = "Error: Email '{$email}' is already registered to another " . ucfirst($_SESSION['role']) . " account.";
            header("Location: $settings_redirect");
            exit();
        }
    } else {
        $email = $cur_u['email'];
    }

    if (!empty($new_password)) {
        $password_hashed = password_hash($new_password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("UPDATE user SET firstname=?, lastname=?, name=?, email=?, phone=?, address=?, dob=?, password=? WHERE id=?");
        $stmt->bind_param("ssssssssi", $firstname, $lastname, $name, $email, $phone, $address, $dob, $password_hashed, $uid);
    } else {
        $stmt = $conn->prepare("UPDATE user SET firstname=?, lastname=?, name=?, email=?, phone=?, address=?, dob=? WHERE id=?");
        $stmt->bind_param("sssssssi", $firstname, $lastname, $name, $email, $phone, $address, $dob, $uid);
    }
    
    $stmt->execute();
    
    // Update session variables
    $_SESSION['name'] = $name;
    $_SESSION['email'] = $email;
    
    log_audit('SETTINGS_UPDATE', "User '{$name}' updated their account settings (Email: {$email})" . (!empty($new_password) ? " including password." : "."), 'Settings & Security', 'success', ['email' => $email, 'has_password_change' => !empty($new_password)]);

    $_SESSION['msg'] = !empty($new_password) ? "Security settings and password updated successfully!" : "Personal information and email updated successfully!";
    header("Location: $settings_redirect");
    exit();
}

// --- UPDATE ADVISER ACADEMIC PROFILE ---
if(isset($_POST['update_adviser_profile'])) {
    $uid = (int)$_SESSION['user_id'];
    $department = sanitize($_POST['department'] ?? '');
    $expertise = sanitize($_POST['expertise'] ?? '');
    $capacity = isset($_POST['capacity']) ? (int)$_POST['capacity'] : 5;
    $schedule = sanitize($_POST['schedule'] ?? '');
    $academic_degree = sanitize($_POST['academic_degree'] ?? '');
    $graduation_year = sanitize($_POST['graduation_year'] ?? '');
    $google_scholar_url = sanitize($_POST['google_scholar_url'] ?? '');
    $google_scholar_id = extract_google_scholar_user_id($google_scholar_url);
    $published_papers = sanitize($_POST['published_papers'] ?? '');
    $previous_advised_titles = sanitize($_POST['previous_advised_titles'] ?? '');

    if (!empty($google_scholar_url) && empty($published_papers)) {
        $gs_res = fetch_google_scholar_publications($google_scholar_url, $_SESSION['name'] ?? '', $department, $expertise);
        if (!empty($gs_res['formatted_publications'])) {
            $published_papers = sanitize($gs_res['formatted_publications']);
        }
        if (!empty($gs_res['google_scholar_id'])) {
            $google_scholar_id = sanitize($gs_res['google_scholar_id']);
        }
    }

    if ((!empty($published_papers) || !empty($previous_advised_titles)) && empty($expertise)) {
        $inferred = extract_specializations_from_publications($_POST['published_papers'] ?? $published_papers, $_POST['previous_advised_titles'] ?? $previous_advised_titles, $expertise);
        if (!empty($inferred['merged_expertise'])) {
            $expertise = sanitize($inferred['merged_expertise']);
        }
    }

    $check = $conn->query("SELECT id FROM adviser_profile WHERE user_id = $uid");
    if($check && $check->num_rows > 0) {
        $stmt = $conn->prepare("UPDATE adviser_profile SET department=?, expertise=?, capacity=?, schedule=?, academic_degree=?, graduation_year=?, google_scholar_url=?, google_scholar_id=?, published_papers=?, previous_advised_titles=?, publications_last_synced=NOW() WHERE user_id=?");
        $stmt->bind_param("ssisssssssi", $department, $expertise, $capacity, $schedule, $academic_degree, $graduation_year, $google_scholar_url, $google_scholar_id, $published_papers, $previous_advised_titles, $uid);
    } else {
        $stmt = $conn->prepare("INSERT INTO adviser_profile (user_id, department, expertise, capacity, schedule, academic_degree, graduation_year, google_scholar_url, google_scholar_id, published_papers, previous_advised_titles, publications_last_synced) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("ississsssss", $uid, $department, $expertise, $capacity, $schedule, $academic_degree, $graduation_year, $google_scholar_url, $google_scholar_id, $published_papers, $previous_advised_titles);
    }
    $stmt->execute();
    log_audit('PROFILE_UPDATE', "Adviser '{$_SESSION['name']}' updated their academic, publication, and advising profile.", 'Faculty Profile', 'success');
    $_SESSION['msg'] = "Adviser academic profile, Google Scholar publications, and specializations updated successfully!";
    header("Location: dashboard.php?tab=settings");
    exit();
}

// --- UPDATE PANEL ACADEMIC PROFILE ---
if(isset($_POST['update_panel_profile'])) {
    $uid = (int)$_SESSION['user_id'];
    $department = sanitize($_POST['department'] ?? '');
    $expertise = sanitize($_POST['expertise'] ?? '');
    $academic_degree = sanitize($_POST['academic_degree'] ?? '');
    $graduation_year = sanitize($_POST['graduation_year'] ?? '');
    $google_scholar_url = sanitize($_POST['google_scholar_url'] ?? '');
    $google_scholar_url = format_google_scholar_link($google_scholar_url, $_SESSION['name'] ?? '');
    $google_scholar_id = extract_google_scholar_user_id($google_scholar_url);
    $published_papers = sanitize($_POST['published_papers'] ?? '');
    $previous_advised_titles = sanitize($_POST['previous_advised_titles'] ?? '');

    $check = $conn->query("SELECT user_id FROM panel_profile WHERE user_id = $uid");
    if($check && $check->num_rows > 0) {
        $stmt = $conn->prepare("UPDATE panel_profile SET department=?, expertise=?, academic_degree=?, graduation_year=?, google_scholar_url=?, google_scholar_id=?, published_papers=?, previous_advised_titles=?, publications_last_synced=NOW() WHERE user_id=?");
        $stmt->bind_param("ssssssssi", $department, $expertise, $academic_degree, $graduation_year, $google_scholar_url, $google_scholar_id, $published_papers, $previous_advised_titles, $uid);
    } else {
        $stmt = $conn->prepare("INSERT INTO panel_profile (user_id, department, expertise, academic_degree, graduation_year, google_scholar_url, google_scholar_id, published_papers, previous_advised_titles, publications_last_synced) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("issssssss", $uid, $department, $expertise, $academic_degree, $graduation_year, $google_scholar_url, $google_scholar_id, $published_papers, $previous_advised_titles);
    }
    $stmt->execute();
    log_audit('PROFILE_UPDATE', "Panel member '{$_SESSION['name']}' updated their academic profile.", 'Panel Profile', 'success');
    $_SESSION['msg'] = "Panel evaluation profile updated successfully!";
    header("Location: dashboard.php?tab=settings");
    exit();
}

// --- UPDATE SECRETARY OFFICE PROFILE ---
if(isset($_POST['update_secretary_profile'])) {
    $uid = (int)$_SESSION['user_id'];
    $sec_department = sanitize($_POST['sec_department'] ?? 'CEIT Secretariat');
    $sec_office = sanitize($_POST['sec_office'] ?? '');
    $sec_phone = sanitize($_POST['sec_phone'] ?? '');

    // Upsert secretary_profile
    $check = $conn->query("SELECT id FROM secretary_profile WHERE user_id = $uid");
    if ($check && $check->num_rows > 0) {
        $stmt = $conn->prepare("UPDATE secretary_profile SET department=?, office=?, phone=? WHERE user_id=?");
        $stmt->bind_param("sssi", $sec_department, $sec_office, $sec_phone, $uid);
    } else {
        $stmt = $conn->prepare("INSERT INTO secretary_profile (user_id, department, office, phone) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $uid, $sec_department, $sec_office, $sec_phone);
    }
    $stmt->execute();

    $_SESSION['msg'] = "Office details updated successfully!";
    header("Location: " . ($_SESSION['role'] === 'secretary' ? 'secretary_dashboard.php' : 'dashboard.php') . "?tab=settings");
    exit();
}

// --- POST SHARED GROUP ACTIVITY & TEAM UPDATE ---
if(isset($_POST['post_group_update'])) {
    if(!isset($_SESSION['user_id'])) {
        header("Location: dashboard.php");
        exit();
    }
    $uid = (int)$_SESSION['user_id'];
    $group_id = (int)($_POST['group_id'] ?? 0);
    $title = sanitize($_POST['title'] ?? '');
    $message = sanitize($_POST['message'] ?? '');
    $update_type = sanitize($_POST['update_type'] ?? 'note');
    if (!in_array($update_type, ['note', 'task', 'meeting', 'urgent'])) {
        $update_type = 'note';
    }

    if (empty($message)) {
        $_SESSION['msg'] = "Error: Update message cannot be empty.";
        header("Location: dashboard.php?tab=overview");
        exit();
    }

    // Determine group_id if not passed directly
    if (!$group_id) {
        $sp_g = $conn->query("SELECT group_id FROM student_profile WHERE user_id=$uid")->fetch_assoc();
        if (!empty($sp_g['group_id'])) {
            $group_id = (int)$sp_g['group_id'];
        } else {
            $cgm_g = $conn->query("SELECT group_id FROM capstone_group_member WHERE student_id=$uid LIMIT 1")->fetch_assoc();
            if (!empty($cgm_g['group_id'])) {
                $group_id = (int)$cgm_g['group_id'];
            }
        }
    }

    if ($group_id > 0) {
        $author_name = $_SESSION['name'];
        $author_role = 'Member';
        $cg_lead = $conn->query("SELECT leader_id FROM capstone_group WHERE id=$group_id")->fetch_assoc();
        if ($cg_lead && (int)$cg_lead['leader_id'] === $uid) {
            $author_role = 'Leader';
        }
        
        $stmt = $conn->prepare("INSERT INTO capstone_group_updates (group_id, user_id, author_name, author_role, update_type, title, message, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("iisssss", $group_id, $uid, $author_name, $author_role, $update_type, $title, $message);
        $stmt->execute();
        $_SESSION['msg'] = "Team update posted to shared dashboard successfully!";
    } else {
        $_SESSION['msg'] = "Error: Group not found.";
    }

    header("Location: dashboard.php?tab=overview");
    exit();
}

// --- DELETE SHARED GROUP UPDATE ---
if(isset($_POST['delete_group_update'])) {
    if(!isset($_SESSION['user_id'])) {
        header("Location: dashboard.php");
        exit();
    }
    $uid = (int)$_SESSION['user_id'];
    $update_id = (int)($_POST['update_id'] ?? 0);

    if ($update_id > 0) {
        $chk = $conn->query("SELECT * FROM capstone_group_updates WHERE id=$update_id")->fetch_assoc();
        if ($chk) {
            $can_del = false;
            if ($_SESSION['role'] === 'admin' || (int)$chk['user_id'] === $uid) {
                $can_del = true;
            } else {
                $cg = $conn->query("SELECT leader_id FROM capstone_group WHERE id=" . (int)$chk['group_id'])->fetch_assoc();
                if ($cg && (int)$cg['leader_id'] === $uid) {
                    $can_del = true;
                }
            }

            if ($can_del) {
                $conn->query("DELETE FROM capstone_group_updates WHERE id=$update_id");
                $_SESSION['msg'] = "Team update deleted.";
            } else {
                $_SESSION['msg'] = "Error: You do not have permission to delete this update.";
            }
        }
    }
    header("Location: dashboard.php?tab=overview");
    exit();
}

// --- STUDENT SAVE PROPOSED THESIS TITLE & RESEARCH ABSTRACT (PDF ONLY) ---
if(isset($_POST['save_thesis_title'])) {
    if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
        header("Location: dashboard.php");
        exit();
    }
    $student_id = (int)$_SESSION['user_id'];
    $title = sanitize($_POST['thesis_topic'] ?? '');
    $return_tab = isset($_POST['return_tab']) ? sanitize($_POST['return_tab']) : 'submission';
    
    // Check if remove abstract requested
    $abstract_update = "";
    $abstract_name_for_audit = "";
    if (!empty($_POST['remove_abstract'])) {
        $old_p = $conn->query("SELECT abstract_file FROM student_profile WHERE user_id=$student_id")->fetch_assoc();
        if (!empty($old_p['abstract_file']) && file_exists($old_p['abstract_file'])) {
            @unlink($old_p['abstract_file']);
        }
        $abstract_update = ", abstract_file=NULL, abstract_name=NULL, abstract_text=NULL";
    }

    // Abstract PDF Requirement: student CANNOT proceed without an abstract PDF!
    $existing_profile = $conn->query("SELECT abstract_file FROM student_profile WHERE user_id=$student_id")->fetch_assoc();
    $has_existing_pdf = (!empty($existing_profile['abstract_file']) && file_exists($existing_profile['abstract_file']));
    $has_uploaded_pdf = (isset($_FILES['abstract_file']) && !empty($_FILES['abstract_file']['name']));

    if (!$has_existing_pdf && !$has_uploaded_pdf) {
        $_SESSION['msg'] = "Error: In compliance with CEIT academic policy, you cannot proceed without uploading your Capstone Concept in PDF format (.pdf).";
        header("Location: dashboard.php?tab=" . $return_tab);
        exit();
    }

    // Handle Concept PDF Upload
    if (isset($_FILES['abstract_file']) && !empty($_FILES['abstract_file']['name'])) {
        $orig_name = $_FILES['abstract_file']['name'];
        $file_ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
        $file_size = (int)$_FILES['abstract_file']['size'];

        // Strict PDF validation: ONLY PDF files accepted!
        if ($file_ext !== 'pdf') {
            $_SESSION['msg'] = "Error: Invalid file format ($file_ext). Only PDF (.pdf) documents are accepted for the capstone concept.";
            header("Location: dashboard.php?tab=" . $return_tab);
            exit();
        }

        if ($file_size > 25 * 1024 * 1024) {
            $_SESSION['msg'] = "Error: Concept PDF exceeds the 25MB file size limit.";
            header("Location: dashboard.php?tab=" . $return_tab);
            exit();
        }

        if ($_FILES['abstract_file']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['msg'] = "Error uploading file. Code: " . $_FILES['abstract_file']['error'];
            header("Location: dashboard.php?tab=" . $return_tab);
            exit();
        }

        $target_dir = "uploads/abstracts/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $safe_orig_name = preg_replace("/[^a-zA-Z0-9._-]/", "_", $orig_name);
        $stored_filename = time() . '_' . rand(1000, 9999) . '_' . $safe_orig_name;
        $target_path = $target_dir . $stored_filename;

        if (!move_uploaded_file($_FILES['abstract_file']['tmp_name'], $target_path)) {
            $_SESSION['msg'] = "Error: Failed to save uploaded Concept PDF to storage.";
            header("Location: dashboard.php?tab=" . $return_tab);
            exit();
        }

        // Delete previous abstract file if it exists
        $prev = $conn->query("SELECT abstract_file FROM student_profile WHERE user_id=$student_id")->fetch_assoc();
        if (!empty($prev['abstract_file']) && file_exists($prev['abstract_file']) && $prev['abstract_file'] !== $target_path) {
            @unlink($prev['abstract_file']);
        }

        // Extract text from the PDF for AI matching engine
        $extracted_text = extract_text_from_pdf($target_path);
        $esc_path = $conn->real_escape_string($target_path);
        $esc_orig = $conn->real_escape_string($orig_name);
        $esc_text = $conn->real_escape_string($extracted_text);

        $abstract_update = ", abstract_file='$esc_path', abstract_name='$esc_orig', abstract_text='$esc_text'";
        $abstract_name_for_audit = $orig_name;
    }
    
    // Optional legacy skills field if submitted
    $skills_sql = "";
    if (isset($_POST['skills'])) {
        $skills = sanitize($_POST['skills']);
        $skills_sql = ", skills='$skills'";
    }
    
    $conn->query("UPDATE student_profile SET thesis_topic='$title' $abstract_update $skills_sql WHERE user_id=$student_id");
    
    // Discover group if exists
    $sp = $conn->query("SELECT group_id, is_group_leader, abstract_file, abstract_name, abstract_text FROM student_profile WHERE user_id=$student_id")->fetch_assoc();
    $gid = !empty($sp['group_id']) ? (int)$sp['group_id'] : 0;
    if (!$gid) {
        $cgm = $conn->query("SELECT group_id FROM capstone_group_member WHERE student_id=$student_id LIMIT 1")->fetch_assoc();
        if ($cgm && !empty($cgm['group_id'])) $gid = (int)$cgm['group_id'];
    }
    
    if ($gid > 0) {
        $esc_af = !empty($sp['abstract_file']) ? "'" . $conn->real_escape_string($sp['abstract_file']) . "'" : "NULL";
        $esc_an = !empty($sp['abstract_name']) ? "'" . $conn->real_escape_string($sp['abstract_name']) . "'" : "NULL";
        $esc_at = !empty($sp['abstract_text']) ? "'" . $conn->real_escape_string($sp['abstract_text']) . "'" : "NULL";
        $conn->query("UPDATE capstone_group SET thesis_topic='$title', abstract_file=$esc_af, abstract_name=$esc_an, abstract_text=$esc_at WHERE id=$gid");
        
        // Sync all group members' student profiles so everyone shares identical capstone topic & concept
        $conn->query("UPDATE student_profile SET thesis_topic='$title' $abstract_update WHERE group_id=$gid");
        
        // Post automated update to group feed
        $updater_name = $_SESSION['name'];
        $updater_role = !empty($sp['is_group_leader']) ? 'Leader' : 'Member';
        $feed_title = "Capstone Topic & Concept Updated";
        $feed_msg = "{$updater_name} ({$updater_role}) updated the group capstone topic to: \"{$title}\"" . (!empty($abstract_name_for_audit) ? " and uploaded concept PDF: {$abstract_name_for_audit}" : "");
        $esc_ft = $conn->real_escape_string($feed_title);
        $esc_fm = $conn->real_escape_string($feed_msg);
        $conn->query("INSERT INTO capstone_group_updates (group_id, user_id, author_name, author_role, update_type, title, message, created_at)
                      VALUES ($gid, $student_id, '$updater_name', '$updater_role', 'note', '$esc_ft', '$esc_fm', NOW())");
    }
    
    $audit_detail = "Student '{$_SESSION['name']}' updated proposed thesis topic to '{$title}'";
    if (!empty($abstract_name_for_audit)) {
        $audit_detail .= " and uploaded Concept PDF '{$abstract_name_for_audit}'";
    }
    log_audit('TOPIC_UPDATED', $audit_detail . ".", 'Capstone & Review', 'info');
    
    $_SESSION['msg'] = !empty($abstract_name_for_audit)
        ? "Proposed capstone title & Concept PDF ('" . htmlspecialchars($abstract_name_for_audit) . "') saved successfully! Faculty recommendations updated."
        : "Proposed capstone title updated successfully! AI faculty recommendations refreshed.";

    $show_ai = (!empty($_POST['show_ai']) && $_POST['show_ai'] == '1') ? '&show_ai=1' : '';
    header("Location: dashboard.php?tab=" . $return_tab . $show_ai);
    exit();
}

if(isset($_POST['send_request'])) {
    $student_id = (int)$_SESSION['user_id'];
    $adviser_id = (int)$_POST['adviser_id'];
    $score = (float)$_POST['match_score'];
    
    // Discover group_id
    $sp = $conn->query("SELECT group_id, is_group_leader, abstract_file FROM student_profile WHERE user_id=$student_id")->fetch_assoc();
    $group_id = !empty($sp['group_id']) ? (int)$sp['group_id'] : 0;
    if (!$group_id) {
        $cgm = $conn->query("SELECT group_id FROM capstone_group_member WHERE student_id=$student_id LIMIT 1")->fetch_assoc();
        if ($cgm && !empty($cgm['group_id'])) $group_id = (int)$cgm['group_id'];
    }
    
    // Fallback abstract if needed
    if ((empty($sp['abstract_file']) || !file_exists($sp['abstract_file'])) && $group_id > 0) {
        $cg_abs = $conn->query("SELECT abstract_file FROM capstone_group WHERE id=$group_id")->fetch_assoc();
        if (!empty($cg_abs['abstract_file']) && file_exists($cg_abs['abstract_file'])) {
            $sp['abstract_file'] = $cg_abs['abstract_file'];
        }
    }

    // Enforce Concept PDF requirement: cannot proceed without concept PDF!
    if (empty($sp['abstract_file']) || !file_exists($sp['abstract_file'])) {
        echo json_encode(["status"=>"error", "message"=>"Concept PDF Required: In compliance with CEIT academic policy, you cannot proceed without uploading your Capstone Concept in PDF format (.pdf)."]);
        exit();
    }

    // Save title if provided
    if (!empty($_POST['title'])) {
        $clean_title = sanitize($_POST['title']);
        $conn->query("UPDATE student_profile SET thesis_topic='$clean_title' WHERE user_id=$student_id AND (thesis_topic IS NULL OR thesis_topic = '')");
        if ($group_id > 0) {
            $conn->query("UPDATE capstone_group SET thesis_topic='$clean_title' WHERE id=$group_id AND (thesis_topic IS NULL OR thesis_topic = '')");
        }
    }
    
    // Check if student or group already has an active (pending or accepted) request
    $grp_check = ($group_id > 0) ? " OR group_id=$group_id" : "";
    $check_active = $conn->query("SELECT id, status FROM match_request WHERE (student_id=$student_id $grp_check) AND status IN ('pending', 'accepted')");
    if($check_active->num_rows > 0) {
        $active_row = $check_active->fetch_assoc();
        $msg = ($active_row['status'] == 'pending') 
            ? "Your group already has a pending adviser request. Only 1 adviser request is allowed at a time." 
            : "Your group already has an assigned adviser.";
        echo json_encode(["status"=>"error", "message"=>$msg]);
        exit();
    }
    
    if ($group_id > 0) {
        $conn->query("DELETE FROM match_request WHERE student_id=$student_id OR group_id=$group_id");
        $conn->query("INSERT INTO match_request (student_id, adviser_id, match_score, status, group_id) VALUES ($student_id, $adviser_id, $score, 'pending', $group_id)");
        
        $adv_u = $conn->query("SELECT name FROM user WHERE id=$adviser_id")->fetch_assoc();
        $adv_name = $adv_u['name'] ?? 'Faculty Adviser';
        $sender_name = $_SESSION['name'];
        $sender_role = !empty($sp['is_group_leader']) ? 'Leader' : 'Member';
        $feed_msg = "{$sender_name} ({$sender_role}) sent an adviser endorsement request to Prof. {$adv_name} (Specialization Match: " . number_format($score, 1) . "%).";
        $esc_fm = $conn->real_escape_string($feed_msg);
        $conn->query("INSERT INTO capstone_group_updates (group_id, user_id, author_name, author_role, update_type, title, message, created_at)
                      VALUES ($group_id, $student_id, '$sender_name', '$sender_role', 'milestone', 'Adviser Request Sent', '$esc_fm', NOW())");
    } else {
        $conn->query("DELETE FROM match_request WHERE student_id=$student_id");
        $conn->query("INSERT INTO match_request (student_id, adviser_id, match_score, status) VALUES ($student_id, $adviser_id, $score, 'pending')");
    }
    ensure_linked_consultation_for_request($conn, $student_id, $adviser_id, $group_id);
    echo json_encode(["status"=>"success"]);
    exit();
}

if(isset($_GET['request_adviser'])) {
    $student_id = (int)$_SESSION['user_id'];
    $adviser_id = (int)$_GET['adviser_id'];
    $score = (float)$_GET['match_score'];
    $return_tab = isset($_GET['return_tab']) ? sanitize($_GET['return_tab']) : 'submission';
    
    // Discover group_id
    $sp = $conn->query("SELECT group_id, is_group_leader, abstract_file FROM student_profile WHERE user_id=$student_id")->fetch_assoc();
    $group_id = !empty($sp['group_id']) ? (int)$sp['group_id'] : 0;
    if (!$group_id) {
        $cgm = $conn->query("SELECT group_id FROM capstone_group_member WHERE student_id=$student_id LIMIT 1")->fetch_assoc();
        if ($cgm && !empty($cgm['group_id'])) $group_id = (int)$cgm['group_id'];
    }
    
    // Fallback abstract if needed
    if ((empty($sp['abstract_file']) || !file_exists($sp['abstract_file'])) && $group_id > 0) {
        $cg_abs = $conn->query("SELECT abstract_file FROM capstone_group WHERE id=$group_id")->fetch_assoc();
        if (!empty($cg_abs['abstract_file']) && file_exists($cg_abs['abstract_file'])) {
            $sp['abstract_file'] = $cg_abs['abstract_file'];
        }
    }

    // Enforce Concept PDF requirement: cannot proceed without concept PDF!
    if (empty($sp['abstract_file']) || !file_exists($sp['abstract_file'])) {
        $_SESSION['msg'] = "Error: In compliance with CEIT academic policy, you cannot proceed to select an adviser without uploading your Capstone Concept in PDF format (.pdf).";
        header("Location: dashboard.php?tab=" . $return_tab);
        exit();
    }

    // Save title if provided
    if (!empty($_GET['title'])) {
        $clean_title = sanitize($_GET['title']);
        $conn->query("UPDATE student_profile SET thesis_topic='$clean_title' WHERE user_id=$student_id AND (thesis_topic IS NULL OR thesis_topic = '')");
        if ($group_id > 0) {
            $conn->query("UPDATE capstone_group SET thesis_topic='$clean_title' WHERE id=$group_id AND (thesis_topic IS NULL OR thesis_topic = '')");
        }
    }
    
    // Check if student or group already has an active (pending or accepted) request
    $grp_check = ($group_id > 0) ? " OR group_id=$group_id" : "";
    $check_active = $conn->query("SELECT id, status FROM match_request WHERE (student_id=$student_id $grp_check) AND status IN ('pending', 'accepted')");
    if($check_active->num_rows > 0) {
        $active_row = $check_active->fetch_assoc();
        if($active_row['status'] == 'pending') {
            $_SESSION['msg'] = "Your group already has a pending request. Only 1 adviser request is allowed at a time. Please wait for adviser approval.";
        } else {
            $_SESSION['msg'] = "Your group already has an assigned adviser.";
        }
    } else {
        if ($group_id > 0) {
            $conn->query("DELETE FROM match_request WHERE student_id=$student_id OR group_id=$group_id");
            $conn->query("INSERT INTO match_request (student_id, adviser_id, match_score, status, group_id) VALUES ($student_id, $adviser_id, $score, 'pending', $group_id)");
            
            $adv_u = $conn->query("SELECT name FROM user WHERE id=$adviser_id")->fetch_assoc();
            $adv_name = $adv_u['name'] ?? 'Faculty Adviser';
            $sender_name = $_SESSION['name'];
            $sender_role = !empty($sp['is_group_leader']) ? 'Leader' : 'Member';
            $feed_msg = "{$sender_name} ({$sender_role}) sent an adviser request to Prof. {$adv_name} (Specialization Match: " . number_format($score, 1) . "%).";
            $esc_fm = $conn->real_escape_string($feed_msg);
            $conn->query("INSERT INTO capstone_group_updates (group_id, user_id, author_name, author_role, update_type, title, message, created_at)
                          VALUES ($group_id, $student_id, '$sender_name', '$sender_role', 'milestone', 'Adviser Request Sent', '$esc_fm', NOW())");
        } else {
            $conn->query("DELETE FROM match_request WHERE student_id=$student_id");
            $conn->query("INSERT INTO match_request (student_id, adviser_id, match_score, status) VALUES ($student_id, $adviser_id, $score, 'pending')");
        }
        ensure_linked_consultation_for_request($conn, $student_id, $adviser_id, $group_id);
        $_SESSION['msg'] = "Adviser request & initial consultation form submitted! Before accepting, your adviser will ask consultation follow-up questions and evaluate your readiness.";
    }
    $return_tab = isset($_GET['return_tab']) ? sanitize($_GET['return_tab']) : 'requests';
    header("Location: dashboard.php?tab=" . $return_tab);
    exit();
}

if(isset($_GET['cancel_request'])) {
    $student_id = (int)$_SESSION['user_id'];
    $req_id = (int)$_GET['req_id'];
    
    // Group context
    $sp = $conn->query("SELECT group_id, is_group_leader FROM student_profile WHERE user_id=$student_id")->fetch_assoc();
    $group_id = !empty($sp['group_id']) ? (int)$sp['group_id'] : 0;
    
    $where_cancel = ($group_id > 0) ? "(student_id=$student_id OR group_id=$group_id)" : "student_id=$student_id";
    $conn->query("DELETE FROM match_request WHERE id=$req_id AND $where_cancel AND status='pending'");
    
    if ($group_id > 0) {
        $canceller = $_SESSION['name'];
        $c_role = !empty($sp['is_group_leader']) ? 'Leader' : 'Member';
        $conn->query("INSERT INTO capstone_group_updates (group_id, user_id, author_name, author_role, update_type, title, message, created_at)
                      VALUES ($group_id, $student_id, '$canceller', '$c_role', 'note', 'Adviser Request Cancelled', '{$canceller} cancelled the pending adviser request.', NOW())");
    }
    
    $_SESSION['msg'] = "Adviser request has been cancelled. You may now select another adviser.";
    $return_tab = isset($_GET['return_tab']) ? sanitize($_GET['return_tab']) : 'requests';
    header("Location: dashboard.php?tab=" . $return_tab);
    exit();
}

// --- STUDENT DOCUMENT & PAPER SUBMISSION HANDLER ---
if(isset($_POST['submit_document'])) {
    if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
        header("Location: dashboard.php");
        exit();
    }
    
    $student_id = (int)$_SESSION['user_id'];
    $title = sanitize($_POST['title'] ?? '');
    $doc_type = sanitize($_POST['doc_type'] ?? 'Chapters 1-3: Proposal Defense Manuscript');
    $version = sanitize($_POST['version'] ?? 'v1.0');
    $description = sanitize($_POST['description'] ?? '');
    $shareable_link = trim($_POST['shareable_link'] ?? '');
    
    if (empty($title)) {
        $_SESSION['msg'] = "Error: Please provide a title or topic for your document.";
        header("Location: dashboard.php?tab=submission");
        exit();
    }

    // Auto prepend https:// if link starts with domain name
    if (!empty($shareable_link) && !preg_match('~^(?:f|ht)tps?://~i', $shareable_link)) {
        $shareable_link = 'https://' . $shareable_link;
    }

    $has_file = isset($_FILES['document_file']) && $_FILES['document_file']['error'] == 0 && $_FILES['document_file']['size'] > 0;
    $has_link = !empty($shareable_link);

    if (!$has_file && !$has_link) {
        $_SESSION['msg'] = "Error: Please provide either a Shareable Document Link (Google Docs / Word Online) or upload a Word document file.";
        header("Location: dashboard.php?tab=submission");
        exit();
    }

    if ($has_link && !filter_var($shareable_link, FILTER_VALIDATE_URL)) {
        $_SESSION['msg'] = "Error: Please provide a valid URL for your shareable document link.";
        header("Location: dashboard.php?tab=submission");
        exit();
    }

    $orig_name = '';
    $file_ext = '';
    $file_size = 0;
    $target_path = '';

    if ($has_file) {
        $orig_name = basename($_FILES['document_file']['name']);
        $file_ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
        $allowed_exts = ['docx', 'doc', 'pdf', 'odt', 'rtf', 'zip', 'rar', 'txt'];
        
        if (!in_array($file_ext, $allowed_exts)) {
            $_SESSION['msg'] = "Error: Invalid file type ($file_ext). Recommended: DOCX, DOC. Also accepted: PDF, ODT, RTF, ZIP.";
            header("Location: dashboard.php?tab=submission");
            exit();
        }
        
        $file_size = (int)$_FILES['document_file']['size'];
        if ($file_size > 40 * 1024 * 1024) {
            $_SESSION['msg'] = "Error: File size exceeds the 40MB limit.";
            header("Location: dashboard.php?tab=submission");
            exit();
        }
        
        $target_dir = "uploads/documents/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $safe_orig_name = preg_replace("/[^a-zA-Z0-9._-]/", "_", $orig_name);
        $stored_filename = time() . '_' . rand(1000, 9999) . '_' . $safe_orig_name;
        $target_path = $target_dir . $stored_filename;
        
        if (!move_uploaded_file($_FILES['document_file']['tmp_name'], $target_path)) {
            $_SESSION['msg'] = "Error: Failed to save uploaded document to server storage.";
            header("Location: dashboard.php?tab=submission");
            exit();
        }
    } else {
        // Link-only submission (Google Docs / Word Online)
        $target_path = $shareable_link;
        if (strpos($shareable_link, 'docs.google.com') !== false || strpos($shareable_link, 'drive.google.com') !== false) {
            $orig_name = "Google Docs Document";
            $file_ext = "gdoc";
        } elseif (strpos($shareable_link, 'office.com') !== false || strpos($shareable_link, 'onedrive') !== false || strpos($shareable_link, 'sharepoint.com') !== false) {
            $orig_name = "Word Online Document";
            $file_ext = "docx";
        } else {
            $orig_name = "Online Document Link";
            $file_ext = "link";
        }
        $file_size = 0;
    }
    
    // Get student's group_id, thesis_topic, and title_status
    $sp_q = $conn->query("SELECT group_id, thesis_topic, title_status FROM student_profile WHERE user_id = $student_id");
    $sp_data = $sp_q ? $sp_q->fetch_assoc() : null;
    $group_id = !empty($sp_data['group_id']) ? (int)$sp_data['group_id'] : "NULL";
    
    // Check group title status if in group
    $title_status = $sp_data['title_status'] ?? 'pending';
    if ($group_id !== "NULL") {
        $cg_q = $conn->query("SELECT title_status FROM capstone_group WHERE id = $group_id");
        if ($cg_q && $cg_q->num_rows > 0) {
            $cg_data = $cg_q->fetch_assoc();
            if (($cg_data['title_status'] ?? '') === 'approved') {
                $title_status = 'approved';
            }
        }
    }

    // Ensure student's adviser match request has been accepted
    $group_match_clause = ($group_id !== "NULL") ? " OR group_id = $group_id" : "";
    $adv_q = $conn->query("SELECT adviser_id, status FROM match_request WHERE (student_id = $student_id $group_match_clause) AND status = 'accepted' ORDER BY id DESC LIMIT 1");
    if (!$adv_q || $adv_q->num_rows === 0) {
        if ($has_file && file_exists($target_path)) {
            unlink($target_path);
        }
        $_SESSION['msg'] = "Error: You cannot submit documents yet. Your adviser request is still waiting for approval.";
        header("Location: dashboard.php?tab=submission");
        exit();
    }
    
    // Ensure research title is approved by adviser
    if ($title_status !== 'approved') {
        if ($has_file && file_exists($target_path)) {
            unlink($target_path);
        }
        $_SESSION['msg'] = "Error: You cannot upload manuscripts until your capstone title has been officially approved by your adviser.";
        header("Location: dashboard.php?tab=submission");
        exit();
    }
    
    $adviser_id = (int)$adv_q->fetch_assoc()['adviser_id'];
    
    $escaped_path = sanitize($target_path);
    $escaped_orig = sanitize($orig_name);
    $escaped_link_val = !empty($shareable_link) ? "'" . sanitize($shareable_link) . "'" : "NULL";
    
    $insert_sql = "INSERT INTO document_submissions 
        (student_id, group_id, adviser_id, title, doc_type, version, file_path, file_name, file_size, file_ext, shareable_link, description, status) 
        VALUES ($student_id, $group_id, $adviser_id, '$title', '$doc_type', '$version', '$escaped_path', '$escaped_orig', $file_size, '$file_ext', $escaped_link_val, '$description', 'pending')";
    
    if ($conn->query($insert_sql)) {
        $submission_id = $conn->insert_id;
        
        // Sync legacy student_profile columns for backward compatibility
        $sync_topic = empty($sp_data['thesis_topic']) ? ", thesis_topic='$title'" : "";
        $conn->query("UPDATE student_profile SET manuscript_file='$escaped_path', paper_status='pending', paper_feedback=NULL $sync_topic WHERE user_id=$student_id");
        
        // If in group, sync all group members' student profiles so all group members see identical manuscript & status
        if ($group_id !== "NULL" && $group_id > 0) {
            $conn->query("UPDATE student_profile SET manuscript_file='$escaped_path', paper_status='pending', paper_feedback=NULL $sync_topic WHERE group_id=$group_id");
            
            $author_name = $_SESSION['name'];
            $role_label = (!empty($sp_data['is_group_leader'])) ? 'Leader' : 'Member';
            $feed_title = "Document Submitted: {$title} ({$version})";
            $doc_source_desc = $has_link ? ($has_file ? "File: {$orig_name} & Cloud Shareable Link" : "Shareable Link: Google Docs / Word Online") : "File: {$orig_name}";
            $feed_msg = "{$author_name} ({$role_label}) submitted {$doc_type} ({$version}) for faculty review. ({$doc_source_desc})";
            $esc_ft = $conn->real_escape_string($feed_title);
            $esc_fm = $conn->real_escape_string($feed_msg);
            $conn->query("INSERT INTO capstone_group_updates (group_id, user_id, author_name, author_role, update_type, title, message, created_at) 
                          VALUES ($group_id, $student_id, '$author_name', '$role_label', 'document_upload', '$esc_ft', '$esc_fm', NOW())");
        }

        log_audit(
            'DOCUMENT_SUBMISSION',
            "Student '{$_SESSION['name']}' submitted document '{$title}' ({$doc_type} {$version}) for checking.",
            'Capstone & Review',
            'success',
            ['submission_id' => $submission_id, 'file' => $orig_name, 'size' => $file_size, 'doc_type' => $doc_type, 'shareable_link' => $shareable_link]
        );
        
        $_SESSION['msg'] = "Document '{$title}' ({$version}) submitted successfully! Your adviser has been notified for checking and review.";
    } else {
        $_SESSION['msg'] = "Database error: " . $conn->error;
    }
    
    header("Location: dashboard.php?tab=submission");
    exit();
}

// --- STUDENT SUBMIT MANUSCRIPT DIRECTLY TO PANEL COMMITTEE ---
if (isset($_POST['submit_panel_manuscript'])) {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
        header("Location: dashboard.php");
        exit();
    }

    $student_id = (int)$_SESSION['user_id'];
    $stage = sanitize($_POST['manuscript_stage'] ?? 'Proposal Defense');
    $notes = sanitize($_POST['manuscript_notes'] ?? '');
    $shareable_link = trim($_POST['shareable_link'] ?? '');
    $topic = sanitize($_POST['thesis_topic'] ?? '');
    $redirect_tab = sanitize($_POST['redirect_tab'] ?? 'panelists');

    // Get student profile & group info
    $sp = $conn->query("SELECT * FROM student_profile WHERE user_id = $student_id")->fetch_assoc();
    $group_id = !empty($sp['group_id']) ? (int)$sp['group_id'] : 0;
    if (empty($topic) && !empty($sp['thesis_topic'])) {
        $topic = $sp['thesis_topic'];
    }

    $uploaded_path = '';
    $orig_name = '';

    // Handle file upload if provided
    if (isset($_FILES['manuscript_file']) && $_FILES['manuscript_file']['error'] === UPLOAD_ERR_OK) {
        $file_name = $_FILES['manuscript_file']['name'];
        $file_tmp = $_FILES['manuscript_file']['tmp_name'];
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        $allowed_exts = ['pdf', 'doc', 'docx'];
        if (!in_array($ext, $allowed_exts)) {
            $_SESSION['msg'] = "Error: Invalid file type ($ext). Only PDF, DOC, and DOCX files are allowed.";
            header("Location: dashboard.php?tab=" . $redirect_tab);
            exit();
        }

        if ($_FILES['manuscript_file']['size'] > 50 * 1024 * 1024) { // 50MB limit
            $_SESSION['msg'] = "Error: File size exceeds the 50MB maximum limit.";
            header("Location: dashboard.php?tab=" . $redirect_tab);
            exit();
        }

        $upload_dir = "uploads/documents/";
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $clean_name = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $file_name);
        $unique_name = time() . "_panel_" . $clean_name;
        $dest = $upload_dir . $unique_name;

        if (move_uploaded_file($file_tmp, $dest)) {
            $uploaded_path = $dest;
            $orig_name = $file_name;
        } else {
            $_SESSION['msg'] = "Error: Failed to save uploaded manuscript file to server.";
            header("Location: dashboard.php?tab=" . $redirect_tab);
            exit();
        }
    }

    // Must provide either a new file, an existing file, or a shareable link
    $existing_file = !empty($sp['manuscript_file']) ? $sp['manuscript_file'] : '';
    $final_path = !empty($uploaded_path) ? $uploaded_path : $existing_file;
    $final_name = !empty($orig_name) ? $orig_name : ($sp['manuscript_name'] ?? basename($final_path));

    if (empty($final_path) && empty($shareable_link)) {
        $_SESSION['msg'] = "Error: Please upload a manuscript document (PDF/Word) or provide a Google Docs / Word Online shareable link.";
        header("Location: dashboard.php?tab=" . $redirect_tab);
        exit();
    }

    $esc_path = sanitize($final_path);
    $esc_name = sanitize($final_name);
    $esc_link = sanitize($shareable_link);
    $esc_topic = sanitize($topic);
    $esc_stage = sanitize($stage);
    $esc_notes = sanitize($notes);

    // Update student_profile
    $conn->query("
        UPDATE student_profile 
        SET manuscript_file = '$esc_path',
            shareable_link = '$esc_link',
            manuscript_name = '$esc_name',
            manuscript_stage = '$esc_stage',
            manuscript_notes = '$esc_notes',
            manuscript_updated_at = NOW()
            " . (!empty($esc_topic) ? ", thesis_topic = '$esc_topic'" : "") . "
        WHERE user_id = $student_id
    ");

    // If in group, sync capstone_group and all group members
    if ($group_id > 0) {
        $conn->query("
            UPDATE capstone_group 
            SET manuscript_file = '$esc_path',
                shareable_link = '$esc_link',
                manuscript_name = '$esc_name',
                manuscript_stage = '$esc_stage',
                manuscript_notes = '$esc_notes',
                manuscript_updated_at = NOW()
                " . (!empty($esc_topic) ? ", thesis_topic = '$esc_topic'" : "") . "
            WHERE id = $group_id
        ");

        $conn->query("
            UPDATE student_profile 
            SET manuscript_file = '$esc_path',
                shareable_link = '$esc_link',
                manuscript_name = '$esc_name',
                manuscript_stage = '$esc_stage',
                manuscript_notes = '$esc_notes',
                manuscript_updated_at = NOW()
                " . (!empty($esc_topic) ? ", thesis_topic = '$esc_topic'" : "") . "
            WHERE group_id = $group_id
        ");

        $author_name = sanitize($_SESSION['name']);
        $feed_title = "Defense Manuscript Submitted: {$esc_stage}";
        $feed_msg = "{$author_name} submitted/updated the capstone manuscript for the Oral Defense Panel Committee.";
        $conn->query("
            INSERT INTO capstone_group_updates (group_id, user_id, author_name, author_role, update_type, title, message, created_at)
            VALUES ($group_id, $student_id, '$author_name', 'Student', 'document_upload', '$feed_title', '$feed_msg', NOW())
        ");
    }

    // Also record in document_submissions for formal audit & review history
    $file_size = !empty($uploaded_path) ? (int)filesize($uploaded_path) : 0;
    $ext = !empty($uploaded_path) ? pathinfo($uploaded_path, PATHINFO_EXTENSION) : 'link';
    $esc_link_val = !empty($esc_link) ? "'$esc_link'" : "NULL";
    $gid_sql = $group_id > 0 ? $group_id : "NULL";

    $conn->query("
        INSERT INTO document_submissions 
            (student_id, group_id, title, doc_type, version, file_path, file_name, file_size, file_ext, shareable_link, description, status) 
        VALUES 
            ($student_id, $gid_sql, '$esc_topic', 'Official Defense Manuscript ($esc_stage)', 'Panel-Review', '$esc_path', '$esc_name', $file_size, '$ext', $esc_link_val, '$esc_notes', 'pending')
    ");

    log_audit('PANEL_MANUSCRIPT_SUBMITTED', "Student '{$_SESSION['name']}' submitted defense manuscript '{$esc_topic}' for panel review.", 'Defense & Panel', 'success', ['stage' => $stage, 'file' => $final_name, 'link' => $shareable_link]);

    $_SESSION['msg'] = "Manuscript successfully submitted to your Capstone Panel Committee! Your assigned panelists can now view and inspect your document.";
    header("Location: dashboard.php?tab=" . $redirect_tab);
    exit();
}

// --- STUDENT DELETE PENDING SUBMISSION ---
if(isset($_POST['delete_document_submission'])) {
    if(!isset($_SESSION['user_id'])) {
        header("Location: dashboard.php");
        exit();
    }
    
    $uid = (int)$_SESSION['user_id'];
    $role = $_SESSION['role'];
    $sub_id = (int)$_POST['submission_id'];
    
    $chk = $conn->query("SELECT * FROM document_submissions WHERE id=$sub_id");
    if($chk && $chk->num_rows > 0) {
        $row = $chk->fetch_assoc();
        
        $is_author = ($role == 'student' && (int)$row['student_id'] === $uid);
        $is_grp_leader = false;
        if ($role == 'student' && !empty($row['group_id'])) {
            $cg = $conn->query("SELECT leader_id FROM capstone_group WHERE id=" . (int)$row['group_id'])->fetch_assoc();
            if ($cg && (int)$cg['leader_id'] === $uid) {
                $is_grp_leader = true;
            }
        }

        if($role == 'admin' || (($is_author || $is_grp_leader) && $row['status'] == 'pending')) {
            if(!empty($row['file_path']) && file_exists(__DIR__ . '/' . $row['file_path'])) {
                @unlink(__DIR__ . '/' . $row['file_path']);
            }
            if(!empty($row['reviewed_file_path']) && file_exists(__DIR__ . '/' . $row['reviewed_file_path'])) {
                @unlink(__DIR__ . '/' . $row['reviewed_file_path']);
            }
            $conn->query("DELETE FROM document_submissions WHERE id=$sub_id");
            
            if (!empty($row['group_id'])) {
                $gid = (int)$row['group_id'];
                $canceller = $_SESSION['name'];
                $c_role = $is_grp_leader ? 'Leader' : 'Member';
                $conn->query("INSERT INTO capstone_group_updates (group_id, user_id, author_name, author_role, update_type, title, message, created_at)
                              VALUES ($gid, $uid, '$canceller', '$c_role', 'note', 'Document Withdrawn', '{$canceller} withdrew submission: {$row['title']} ({$row['version']})', NOW())");
            }

            log_audit('DOCUMENT_DELETE', "Document submission #{$sub_id} ('{$row['title']}') was withdrawn by {$_SESSION['name']}.", 'Capstone & Review', 'warning');
            $_SESSION['msg'] = "Document submission was successfully withdrawn.";
        } else {
            $_SESSION['msg'] = "Error: Cannot delete document once it has been checked or belongs to another group.";
        }
    }
    header("Location: dashboard.php?tab=submission");
    exit();
}

// --- FACULTY / ADVISER DOCUMENT REVIEW HANDLER ---
if(isset($_POST['review_document_submission'])) {
    if(!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['adviser', 'admin'])) {
        header("Location: dashboard.php");
        exit();
    }
    
    $reviewer_id = (int)$_SESSION['user_id'];
    $submission_id = (int)$_POST['submission_id'];
    $review_status = sanitize($_POST['review_status'] ?? 'needs_revision');
    $feedback = sanitize($_POST['feedback'] ?? '');
    
    if (!in_array($review_status, ['approved', 'needs_revision', 'under_review'])) {
        $review_status = 'needs_revision';
    }
    
    $sub_q = $conn->query("SELECT * FROM document_submissions WHERE id=$submission_id");
    if (!$sub_q || $sub_q->num_rows == 0) {
        $_SESSION['msg'] = "Error: Submission record not found.";
        header("Location: dashboard.php?tab=review");
        exit();
    }
    $sub = $sub_q->fetch_assoc();
    $student_id = (int)$sub['student_id'];
    
    // Optional annotated file returned by reviewer
    $annotated_update = "";
    if (isset($_FILES['reviewed_file']) && $_FILES['reviewed_file']['error'] == 0) {
        $target_dir = "uploads/documents/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $orig_rev_name = basename($_FILES['reviewed_file']['name']);
        $safe_rev_name = preg_replace("/[^a-zA-Z0-9._-]/", "_", $orig_rev_name);
        $rev_filename = time() . '_reviewed_' . rand(100, 999) . '_' . $safe_rev_name;
        $target_rev_file = $target_dir . $rev_filename;
        
        if (move_uploaded_file($_FILES['reviewed_file']['tmp_name'], $target_rev_file)) {
            $esc_rev_file = sanitize($target_rev_file);
            $esc_rev_name = sanitize($orig_rev_name);
            $annotated_update = ", reviewed_file_path='$esc_rev_file', reviewed_file_name='$esc_rev_name'";
        }
    }
    
    $upd = "UPDATE document_submissions SET 
        status = '$review_status', 
        feedback = '$feedback', 
        reviewed_by = $reviewer_id, 
        reviewed_at = NOW() 
        $annotated_update 
        WHERE id = $submission_id";
    
    if ($conn->query($upd)) {
        // Sync legacy student_profile fields
        $legacy_status = ($review_status == 'approved') ? 'approved' : 'revise';
        $title_approved_sync = ($review_status == 'approved') ? ", title_status='approved', title_approved_at=NOW(), title_approved_by=$reviewer_id" : "";
        $conn->query("UPDATE student_profile SET paper_status='$legacy_status', paper_feedback='$feedback' $title_approved_sync WHERE user_id=$student_id");
        
        $rev_gid = !empty($sub['group_id']) ? (int)$sub['group_id'] : 0;
        if (!$rev_gid) {
            $sp_grp = $conn->query("SELECT group_id FROM student_profile WHERE user_id=$student_id")->fetch_assoc();
            if (!empty($sp_grp['group_id'])) $rev_gid = (int)$sp_grp['group_id'];
        }

        if ($rev_gid > 0) {
            $conn->query("UPDATE student_profile SET paper_status='$legacy_status', paper_feedback='$feedback' $title_approved_sync WHERE group_id=$rev_gid");
            if ($review_status == 'approved') {
                $conn->query("UPDATE capstone_group SET title_status='approved', title_approved_at=NOW(), title_approved_by=$reviewer_id WHERE id=$rev_gid");
            }
            
            $adv_name = $_SESSION['name'];
            $status_name = ($review_status == 'approved') ? 'Approved' : (($review_status == 'needs_revision') ? 'Needs Revision' : 'Under Review');
            $feed_title = "Document Review: {$status_name}";
            $feed_msg = "Prof. {$adv_name} completed review for '{$sub['title']}' ({$sub['version']}). Result: {$status_name}." . (!empty($feedback) ? "\nAdviser Feedback: {$feedback}" : "");
            $esc_ft = $conn->real_escape_string($feed_title);
            $esc_fm = $conn->real_escape_string($feed_msg);
            $conn->query("INSERT INTO capstone_group_updates (group_id, user_id, author_name, author_role, update_type, title, message, created_at)
                          VALUES ($rev_gid, $reviewer_id, '$adv_name', 'Adviser', 'adviser_feedback', '$esc_ft', '$esc_fm', NOW())");
        }
        
        $status_label = ($review_status == 'approved') ? 'Approved' : (($review_status == 'needs_revision') ? 'Needs Revision' : 'Under Review');
        log_audit(
            'DOCUMENT_REVIEW',
            "Adviser '{$_SESSION['name']}' completed review for document '{$sub['title']}' (Result: {$status_label}).",
            'Capstone & Review',
            'success',
            ['submission_id' => $submission_id, 'student_id' => $student_id, 'status' => $review_status]
        );
        
        $_SESSION['msg'] = "Document review submitted successfully! Result marked as '{$status_label}'.";
    } else {
        $_SESSION['msg'] = "Error updating review: " . $conn->error;
    }
    
    header("Location: dashboard.php?tab=review&student_id=$student_id&submission_id=$submission_id");
    exit();
}

if(isset($_POST['create_paper'])) {
    $uid = $_SESSION['user_id'];
    $title = sanitize($_POST['paper_title']);
    $content = sanitize($_POST['paper_content']);
    
    $file_update = "";
    $target_file = "";
    $file_name_orig = "";
    if(isset($_FILES['manuscript_file']) && $_FILES['manuscript_file']['error'] == 0) {
        $target_dir = "uploads/documents/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $file_name_orig = basename($_FILES["manuscript_file"]["name"]);
        $file_name = time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "_", $file_name_orig);
        $target_file = $target_dir . $file_name;
        $file_type = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));
        
        // Allow certain file formats
        if($file_type == "pdf" || $file_type == "doc" || $file_type == "docx") {
            if (move_uploaded_file($_FILES["manuscript_file"]["tmp_name"], $target_file)) {
                $file_update = ", manuscript_file='" . sanitize($target_file) . "'";
            }
        }
    }

    $conn->query("UPDATE student_profile SET thesis_topic='$title', paper_content='$content' $file_update WHERE user_id=$uid");
    
    // Also record in document_submissions if file was provided
    if(!empty($target_file)) {
        $adv_q = $conn->query("SELECT adviser_id FROM match_request WHERE student_id = $uid AND status = 'accepted' LIMIT 1");
        $aid = ($adv_q && $adv_q->num_rows > 0) ? (int)$adv_q->fetch_assoc()['adviser_id'] : "NULL";
        $sp_grp = $conn->query("SELECT group_id FROM student_profile WHERE user_id=$uid")->fetch_assoc();
        $gid = !empty($sp_grp['group_id']) ? (int)$sp_grp['group_id'] : "NULL";
        $ext = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        $fsize = file_exists(__DIR__ . '/' . $target_file) ? filesize(__DIR__ . '/' . $target_file) : 0;
        $desc = sanitize(substr($content, 0, 500));
        $esc_path = sanitize($target_file);
        $esc_orig = sanitize($file_name_orig);
        $conn->query("INSERT INTO document_submissions (student_id, group_id, adviser_id, title, doc_type, version, file_path, file_name, file_size, file_ext, description, status) 
                      VALUES ($uid, $gid, $aid, '$title', 'Chapters 1-3 Proposal Manuscript', 'v1.0', '$esc_path', '$esc_orig', $fsize, '$ext', '$desc', 'pending')");
    }

    $_SESSION['msg'] = "Paper draft submitted successfully for checking and review!";
    header("Location: dashboard.php?tab=submission");
    exit();
}

if(isset($_POST['review_paper'])) {
    $sid = (int)$_POST['student_id'];
    $feedback = sanitize($_POST['feedback']);
    $status = sanitize($_POST['status']);
    
    if($sid > 0) {
        $conn->query("UPDATE student_profile SET paper_feedback='$feedback', paper_status='$status' WHERE user_id=$sid");
        
        // Also update latest document_submissions for this student
        $doc_stat = ($status == 'approved') ? 'approved' : 'needs_revision';
        $sub_latest = $conn->query("SELECT id FROM document_submissions WHERE student_id=$sid ORDER BY id DESC LIMIT 1");
        if($sub_latest && $sub_latest->num_rows > 0) {
            $latest_id = (int)$sub_latest->fetch_assoc()['id'];
            $rev_uid = (int)($_SESSION['user_id'] ?? 0);
            $conn->query("UPDATE document_submissions SET status='$doc_stat', feedback='$feedback', reviewed_by=$rev_uid, reviewed_at=NOW() WHERE id=$latest_id");
        }
        
        $_SESSION['msg'] = "Paper review submitted successfully!";
        header("Location: dashboard.php?tab=review&student_id=$sid");
    } else {
        $_SESSION['msg'] = "Error: No student selected.";
        header("Location: dashboard.php?tab=review");
    }
    exit();
}

if(isset($_POST['schedule_meeting'])) {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'adviser') {
        header("Location: dashboard.php");
        exit();
    }

    if (function_exists('ensure_consultation_schema')) {
        ensure_consultation_schema($conn);
    }

    $adv_uid = (int)$_SESSION['user_id'];
    $adv_name = $_SESSION['name'] ?? 'Research Adviser';
    $sid = (int)($_POST['student_id'] ?? 0);
    $raw_dt = trim($_POST['meeting_time'] ?? '');
    $location = trim($_POST['location'] ?? 'CEIT Faculty Consultation Room');
    $stage = trim($_POST['consultation_stage'] ?? 'Adviser-Scheduled Progress Meeting');
    $mode = trim($_POST['consultation_mode'] ?? 'In-Person');
    if (!in_array($mode, ['In-Person', 'Online / Virtual', 'Document / Asynchronous'], true)) {
        $mode = 'In-Person';
    }
    $agenda = trim($_POST['meeting_agenda'] ?? '');

    if ($sid > 0 && !empty($raw_dt)) {
        $ts = strtotime($raw_dt);
        $pref_date = $ts ? date('Y-m-d', $ts) : date('Y-m-d');
        $pref_time = $ts ? date('g:i A', $ts) : 'TBA';
        $conf_sched = $ts ? date('M d, Y \a\t g:i A', $ts) : $raw_dt;

        $sp = $conn->query("SELECT * FROM student_profile WHERE user_id = $sid")->fetch_assoc() ?: [];
        $gid = (int)($sp['group_id'] ?? 0);
        $cg = ($gid > 0) ? ($conn->query("SELECT * FROM capstone_group WHERE id = $gid")->fetch_assoc() ?: []) : [];

        $res_title = !empty($cg['thesis_topic']) ? $cg['thesis_topic'] : (!empty($sp['thesis_topic']) ? $sp['thesis_topic'] : 'Capstone Research Advising Session');
        $res_summary = !empty($cg['abstract_text']) ? $cg['abstract_text'] : (!empty($sp['abstract_text']) ? $sp['abstract_text'] : "Adviser-scheduled meeting for {$res_title}.");
        if ($agenda === '') {
            $agenda = "1. Review current research progress and milestones.\n2. Discuss adviser feedback and next action items.";
        }

        $ref_no = 'CEIT-CF-' . date('Y') . '-' . strtoupper(substr(md5(uniqid((string)$sid, true)), 0, 6));
        $gid_sql = $gid > 0 ? $gid : "NULL";

        $esc_ref = $conn->real_escape_string($ref_no);
        $esc_stage = $conn->real_escape_string($stage);
        $esc_title = $conn->real_escape_string($res_title);
        $esc_sum = $conn->real_escape_string($res_summary);
        $esc_ag = $conn->real_escape_string($agenda);
        $esc_date = $conn->real_escape_string($pref_date);
        $esc_time = $conn->real_escape_string($pref_time);
        $esc_mode = $conn->real_escape_string($mode);
        $esc_loc = $conn->real_escape_string($location);
        $esc_conf = $conn->real_escape_string($conf_sched);

        $conn->query("INSERT INTO adviser_consultations 
            (reference_no, student_id, group_id, adviser_id, consultation_stage, research_title, research_idea_summary, consultation_agenda, current_progress, preferred_date, preferred_time, consultation_mode, venue_or_link, status, accepted_at, confirmed_schedule, readiness_level)
            VALUES ('$esc_ref', $sid, $gid_sql, $adv_uid, '$esc_stage', '$esc_title', '$esc_sum', '$esc_ag', 'Scheduled directly by Research Adviser', '$esc_date', '$esc_time', '$esc_mode', '$esc_loc', 'accepted', NOW(), '$esc_conf', 'Ready')");

        $new_cid = (int)$conn->insert_id;
        if ($new_cid > 0) {
            $esc_adv_name = $conn->real_escape_string($adv_name);
            $msg_body = "Scheduled {$stage} ({$mode}) on {$conf_sched} at {$location}.\nAgenda: {$agenda}";
            $esc_msg_body = $conn->real_escape_string($msg_body);
            $conn->query("INSERT INTO consultation_messages (consultation_id, sender_id, sender_name, sender_role, message_type, message_text)
                          VALUES ($new_cid, $adv_uid, '$esc_adv_name', 'adviser', 'adviser_comment', '$esc_msg_body')");

            $conn->query("UPDATE student_profile SET consultation_process_status = 'in_progress' WHERE user_id = $sid AND consultation_process_status = 'not_started'");
        }

        // Notify student (and group members if in a group)
        if (function_exists('create_notification')) {
            $notif_title = "New Advising Meeting Scheduled";
            $notif_msg = "Your adviser {$adv_name} scheduled a meeting ({$stage}) on {$conf_sched} ({$mode}: {$location}).";
            create_notification($conn, $sid, 'consultation', $notif_title, $notif_msg, 'student_dashboard.php?tab=consultations');
            if ($gid > 0) {
                $gm_q = $conn->query("SELECT user_id FROM student_profile WHERE group_id = $gid AND user_id != $sid");
                if ($gm_q) {
                    while ($gm = $gm_q->fetch_assoc()) {
                        create_notification($conn, (int)$gm['user_id'], 'consultation', $notif_title, $notif_msg, 'student_dashboard.php?tab=consultations');
                    }
                }
            }
        }

        $_SESSION['msg'] = "Advisee meeting scheduled for {$conf_sched} and notification sent!";
    } else {
        $_SESSION['msg'] = "Error: Please select an advisee and specify the meeting date & time.";
    }
    header("Location: faculty_dashboard.php?tab=schedule");
    exit();
}

if(isset($_POST['reject_advisee_request'])) {
    if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'adviser') {
        header("Location: dashboard.php");
        exit();
    }
    
    $req_id = (int)$_POST['req_id'];
    $rejection_reason = trim($_POST['rejection_reason'] ?? '');
    
    if(empty($rejection_reason)) {
        $_SESSION['msg'] = "Error: Please provide a reason for declining the advisee request.";
        header("Location: dashboard.php?tab=requests");
        exit();
    }
    
    $rejection_reason = sanitize($rejection_reason);
    $adviser_id = (int)$_SESSION['user_id'];
    
    $conn->query("UPDATE match_request SET status='rejected', rejection_reason='$rejection_reason' WHERE id=$req_id AND adviser_id=$adviser_id");
    $_SESSION['msg'] = "Advisee request declined and reason has been communicated to the student.";
    header("Location: dashboard.php?tab=requests");
    exit();
}

if(isset($_GET['respond'])) {
    $req_id = (int)$_GET['req_id'];
    $action = $_GET['action']; // accept or reject
    
    if($action == 'accept') {
        $aid = (int)$_SESSION['user_id'];
        $mr_info = $conn->query("SELECT m.*, u.name as adv_name FROM match_request m JOIN user u ON m.adviser_id = u.id WHERE m.id=$req_id AND m.adviser_id=$aid")->fetch_assoc();
        $student_id = (int)($mr_info['student_id'] ?? 0);
        $gid = (int)($mr_info['group_id'] ?? 0);
        if (!$gid && $student_id > 0) {
            $sp = $conn->query("SELECT group_id FROM student_profile WHERE user_id=$student_id")->fetch_assoc();
            if(!empty($sp['group_id'])) $gid = (int)$sp['group_id'];
        }

        // Enforce Pre-Acceptance Consultation & Questioning Process before accepting student
        $pre_check = check_pre_acceptance_consultation_ready($conn, $student_id, $aid, $gid, $mr_info);
        if (!$pre_check['can_accept_student']) {
            if (!$pre_check['adviser_asked_questions']) {
                $_SESSION['msg'] = "Action Required Before Accepting: The student must go through the consultation process first. Please ask consultation follow-up questions and evaluate the student's readiness before accepting.";
            } else {
                $_SESSION['msg'] = "Waiting for Student Response: You have sent consultation questions to the student. Please wait for the student to answer your questions and review their response before accepting.";
            }
            header("Location: dashboard.php?tab=requests");
            exit();
        }

        $conn->query("UPDATE match_request SET status='accepted', consultation_status='evaluated', rejection_reason=NULL WHERE id=$req_id AND adviser_id=$aid");
        if (!empty($pre_check['consultation_id'])) {
            $cid_sync = (int)$pre_check['consultation_id'];
            $conn->query("UPDATE adviser_consultations SET status = CASE WHEN status = 'completed' THEN 'completed' ELSE 'accepted' END, accepted_at = COALESCE(accepted_at, NOW()) WHERE id = $cid_sync");
        }
        
        $adv_name = $mr_info['adv_name'] ?? $_SESSION['name'];
        if ($gid > 0) {
            $feed_title = "Adviser Request Accepted";
            $feed_msg = "Prof. {$adv_name} reviewed your consultation responses and accepted your group as an advisee.";
            $esc_ft = $conn->real_escape_string($feed_title);
            $esc_fm = $conn->real_escape_string($feed_msg);
            $conn->query("INSERT INTO capstone_group_updates (group_id, user_id, author_name, author_role, update_type, title, message, created_at)
                          VALUES ($gid, $aid, '$adv_name', 'Adviser', 'milestone', '$esc_ft', '$esc_fm', NOW())");
        }
        
        if(!empty($_GET['approve_title']) && $student_id > 0) {
            $conn->query("UPDATE student_profile SET title_status='approved', title_approved_at=NOW(), title_approved_by=$aid WHERE user_id=$student_id");
            if($gid > 0) {
                $conn->query("UPDATE capstone_group SET title_status='approved', title_approved_at=NOW(), title_approved_by=$aid WHERE id=$gid");
                $conn->query("UPDATE student_profile SET title_status='approved', title_approved_at=NOW(), title_approved_by=$aid WHERE group_id=$gid");
            }
            $_SESSION['msg'] = "Consultation responses verified! Advisee request accepted and proposed capstone title officially approved!";
        } else {
            $_SESSION['msg'] = "Consultation responses verified and advisee request accepted successfully!";
        }
    } else {
        $_SESSION['msg'] = "To decline a request, please provide a reason using the Decline button.";
    }
    header("Location: dashboard.php?tab=requests");
    exit();
}

// --- ADVISER DIRECT TITLE APPROVAL / REVISION HANDLER ---
if(isset($_POST['update_title_approval']) || isset($_GET['update_title_approval'])) {
    if(!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'adviser' && $_SESSION['role'] !== 'admin')) {
        header("Location: dashboard.php");
        exit();
    }
    $adviser_id = (int)$_SESSION['user_id'];
    $student_id = (int)($_REQUEST['student_id'] ?? 0);
    $status = sanitize($_REQUEST['status'] ?? 'approved'); // 'approved', 'pending', 'rejected'
    
    if($student_id > 0) {
        $stu_row = $conn->query("SELECT u.name, sp.thesis_topic, sp.group_id FROM user u JOIN student_profile sp ON u.id = sp.user_id WHERE u.id=$student_id")->fetch_assoc();
        $stu_name = $stu_row['name'] ?? 'Student';
        $topic_name = $stu_row['thesis_topic'] ?? 'Capstone Topic';
        $gid = !empty($stu_row['group_id']) ? (int)$stu_row['group_id'] : 0;
        $adv_name = $_SESSION['name'];
        
        if($status === 'approved') {
            $conn->query("UPDATE student_profile SET title_status='approved', title_approved_at=NOW(), title_approved_by=$adviser_id WHERE user_id=$student_id");
            if($gid > 0) {
                $conn->query("UPDATE capstone_group SET title_status='approved', title_approved_at=NOW(), title_approved_by=$adviser_id WHERE id=$gid");
                $conn->query("UPDATE student_profile SET title_status='approved', title_approved_at=NOW(), title_approved_by=$adviser_id WHERE group_id=$gid");
                
                $feed_title = "Capstone Title Approved";
                $feed_msg = "Prof. {$adv_name} officially approved your group capstone title: '{$topic_name}'.";
                $esc_ft = $conn->real_escape_string($feed_title);
                $esc_fm = $conn->real_escape_string($feed_msg);
                $conn->query("INSERT INTO capstone_group_updates (group_id, user_id, author_name, author_role, update_type, title, message, created_at)
                              VALUES ($gid, $adviser_id, '$adv_name', 'Adviser', 'milestone', '$esc_ft', '$esc_fm', NOW())");
            }
            log_audit('TITLE_APPROVED', "Adviser '{$_SESSION['name']}' officially approved capstone title '{$topic_name}' for '{$stu_name}'.", 'Capstone & Review', 'success', ['student_id' => $student_id]);
            $_SESSION['msg'] = "Capstone title for {$stu_name} has been officially approved!";
        } elseif($status === 'rejected') {
            $conn->query("UPDATE student_profile SET title_status='rejected' WHERE user_id=$student_id");
            if($gid > 0) {
                $conn->query("UPDATE capstone_group SET title_status='rejected' WHERE id=$gid");
                $conn->query("UPDATE student_profile SET title_status='rejected' WHERE group_id=$gid");
                
                $feed_title = "Capstone Title Needs Revision";
                $feed_msg = "Prof. {$adv_name} marked your capstone title '{$topic_name}' as needing revision.";
                $esc_ft = $conn->real_escape_string($feed_title);
                $esc_fm = $conn->real_escape_string($feed_msg);
                $conn->query("INSERT INTO capstone_group_updates (group_id, user_id, author_name, author_role, update_type, title, message, created_at)
                              VALUES ($gid, $adviser_id, '$adv_name', 'Adviser', 'adviser_feedback', '$esc_ft', '$esc_fm', NOW())");
            }
            log_audit('TITLE_REVISION', "Adviser '{$_SESSION['name']}' marked capstone title '{$topic_name}' for '{$stu_name}' as needing revision.", 'Capstone & Review', 'warning', ['student_id' => $student_id]);
            $_SESSION['msg'] = "Capstone title for {$stu_name} marked as needing revision.";
        } else {
            $conn->query("UPDATE student_profile SET title_status='pending', title_approved_at=NULL, title_approved_by=NULL WHERE user_id=$student_id");
            if($gid > 0) {
                $conn->query("UPDATE capstone_group SET title_status='pending', title_approved_at=NULL, title_approved_by=NULL WHERE id=$gid");
                $conn->query("UPDATE student_profile SET title_status='pending', title_approved_at=NULL, title_approved_by=NULL WHERE group_id=$gid");
            }
            $_SESSION['msg'] = "Capstone title status for {$stu_name} reset to pending.";
        }
    }
    
    $return_tab = sanitize($_REQUEST['return_tab'] ?? 'advisees');
    $sub_param = !empty($_REQUEST['submission_id']) ? "&submission_id=" . (int)$_REQUEST['submission_id'] : "";
    $stu_param = $student_id ? "&student_id=$student_id" : "";
    header("Location: dashboard.php?tab=" . $return_tab . $stu_param . $sub_param);
    exit();
}

if(isset($_POST['approve_faculty']) || isset($_POST['approve_user'])) {
    if($_SESSION['role'] == 'admin') {
        $adv_id = (int)$_POST['user_id'];
        $u_info = $conn->query("SELECT name, role, email FROM user WHERE id=$adv_id")->fetch_assoc();
        $u_name = $u_info['name'] ?? "User #$adv_id";
        $u_role = $u_info['role'] ?? 'user';
        
        $conn->query("UPDATE user SET account_status='active' WHERE id=$adv_id");
        $_SESSION['msg'] = "Account for " . htmlspecialchars($u_name) . " (" . ucfirst($u_role) . ") approved successfully!";
        log_audit('APPROVE_USER', "Admin approved {$u_role} account for '{$u_name}' (ID #{$adv_id}).", 'Account Approvals', 'success', ['user_id' => $adv_id, 'name' => $u_name, 'role' => $u_role]);
    }
    header("Location: dashboard.php?tab=approvals");
    exit();
}

if(isset($_POST['reject_faculty']) || isset($_POST['reject_user'])) {
    if($_SESSION['role'] == 'admin') {
        $adv_id = (int)$_POST['user_id'];
        $u_info = $conn->query("SELECT name, role, email FROM user WHERE id=$adv_id")->fetch_assoc();
        $u_name = $u_info['name'] ?? "User #$adv_id";
        $u_role = $u_info['role'] ?? 'user';

        $conn->query("UPDATE user SET account_status='rejected' WHERE id=$adv_id");
        $_SESSION['msg'] = "Account for " . htmlspecialchars($u_name) . " (" . ucfirst($u_role) . ") has been rejected.";
        log_audit('REJECT_USER', "Admin rejected {$u_role} account for '{$u_name}' (ID #{$adv_id}).", 'Account Approvals', 'warning', ['user_id' => $adv_id, 'name' => $u_name, 'role' => $u_role]);
    }
    header("Location: dashboard.php?tab=approvals");
    exit();
}

if(isset($_GET['delete_user'])) {
    if($_SESSION['role'] == 'admin') {
        $del_id = (int)$_GET['delete_user'];
        // Don't let admin delete themselves
        if($del_id != $_SESSION['user_id']) {
            $u_info = $conn->query("SELECT name, role, email FROM user WHERE id=$del_id")->fetch_assoc();
            $u_name = $u_info['name'] ?? "User #$del_id";
            $u_role = $u_info['role'] ?? 'user';

            // Delete associated child records first to satisfy foreign key constraints
            $conn->query("DELETE FROM match_request WHERE student_id = $del_id OR adviser_id = $del_id");
            $conn->query("DELETE FROM thesis_panel WHERE student_id = $del_id OR panel_id = $del_id");
            $conn->query("DELETE FROM announcements WHERE adviser_id = $del_id");
            $conn->query("DELETE FROM adviser_profile WHERE user_id = $del_id");
            $conn->query("DELETE FROM student_profile WHERE user_id = $del_id");
            $conn->query("DELETE FROM panel_profile WHERE user_id = $del_id");

            $res = $conn->query("DELETE FROM user WHERE id=$del_id");
            if($res && $conn->affected_rows > 0) {
                $_SESSION['msg'] = "User successfully deleted.";
                log_audit('USER_DELETE', "Admin permanently deleted user '{$u_name}' (Role: {$u_role}, ID #{$del_id}).", 'User Management', 'warning', ['deleted_user_id' => $del_id, 'name' => $u_name, 'role' => $u_role]);
            } else {
                $_SESSION['msg'] = "Unable to delete user: " . ($conn->error ?: "User record not found.");
            }
        } else {
            $_SESSION['msg'] = "You cannot delete your own account.";
        }
    }
    header("Location: dashboard.php?tab=archived");
    exit();
}

if(isset($_GET['delete_thesis'])) {
    if($_SESSION['role'] == 'admin') {
        $sid = (int)$_GET['delete_thesis'];
        $u_info = $conn->query("SELECT name FROM user WHERE id=$sid")->fetch_assoc();
        $s_name = $u_info['name'] ?? "Student #$sid";

        $conn->query("UPDATE student_profile SET thesis_topic='', skills='', paper_content='', manuscript_file='', paper_feedback='', paper_status='draft' WHERE user_id=$sid");
        $_SESSION['msg'] = "Capstone record successfully deleted.";
        log_audit('THESIS_DELETE', "Admin deleted capstone thesis submission of student '{$s_name}' (ID #{$sid}).", 'Capstone & Panel', 'warning', ['student_id' => $sid, 'student_name' => $s_name]);
    }
    header("Location: dashboard.php?tab=theses");
    exit();
}

if(isset($_POST['admin_edit_user'])) {
    if($_SESSION['role'] == 'admin') {
        $edit_id = (int)$_POST['edit_user_id'];
        $firstname = sanitize($_POST['firstname']);
        $lastname = sanitize($_POST['lastname']);
        $name = $firstname . ' ' . $lastname;
        $email = sanitize($_POST['email']);
        $role = sanitize($_POST['role']);
        
        // Prevent duplicate email for the same role
        $chk_dup = $conn->prepare("SELECT id FROM user WHERE email = ? AND role = ? AND id != ?");
        $chk_dup->bind_param("ssi", $email, $role, $edit_id);
        $chk_dup->execute();
        if ($chk_dup->get_result()->num_rows > 0) {
            $_SESSION['msg'] = "Error: Another account with email '{$email}' already exists for role " . ucfirst($role) . "!";
            header("Location: dashboard.php?tab=users");
            exit();
        }

        $stmt = $conn->prepare("UPDATE user SET firstname=?, lastname=?, name=?, email=? WHERE id=?");
        $stmt->bind_param("ssssi", $firstname, $lastname, $name, $email, $edit_id);
        $stmt->execute();
        
        $pwd_changed = false;
        if (!empty($_POST['password'])) {
            $new_password = trim($_POST['password']);
            $new_password_hashed = password_hash($new_password, PASSWORD_BCRYPT);
            $stmt_pwd = $conn->prepare("UPDATE user SET password=? WHERE id=?");
            $stmt_pwd->bind_param("si", $new_password_hashed, $edit_id);
            $stmt_pwd->execute();
            $pwd_changed = true;
        }
        
        if ($role == 'adviser') {
            $spec = sanitize($_POST['specialization'] ?? '');
            $hire_date = sanitize($_POST['hire_date'] ?? '');
            $academic_degree = sanitize($_POST['academic_degree'] ?? '');
            $graduation_year = sanitize($_POST['graduation_year'] ?? '');
            $google_scholar_url = sanitize($_POST['google_scholar_url'] ?? '');
            $google_scholar_url = format_google_scholar_link($google_scholar_url, $name);
            $google_scholar_id = extract_google_scholar_user_id($google_scholar_url);
            $published_papers = sanitize($_POST['published_papers'] ?? '');
            $previous_advised_titles = sanitize($_POST['previous_advised_titles'] ?? '');

            // If Google Scholar URL provided and published_papers is empty, fetch from Google Scholar automatically
            if (!empty($google_scholar_url) && empty($published_papers)) {
                $gs_res = fetch_google_scholar_publications($google_scholar_url, $name, $_POST['department'] ?? '', $spec);
                if (!empty($gs_res['formatted_publications'])) {
                    $published_papers = sanitize($gs_res['formatted_publications']);
                }
                if (!empty($gs_res['google_scholar_id'])) {
                    $google_scholar_id = sanitize($gs_res['google_scholar_id']);
                }
            }

            // Use publications & previous supervised titles to enrich specializations
            if (!empty($published_papers) || !empty($previous_advised_titles)) {
                $inferred = extract_specializations_from_publications($_POST['published_papers'] ?? $published_papers, $_POST['previous_advised_titles'] ?? $previous_advised_titles, $_POST['specialization'] ?? $spec);
                if (!empty($inferred['merged_expertise'])) {
                    $spec = sanitize($inferred['merged_expertise']);
                }
            }

            $stmt_adv = $conn->prepare("UPDATE adviser_profile SET expertise=?, hire_date=?, academic_degree=?, graduation_year=?, google_scholar_url=?, google_scholar_id=?, published_papers=?, previous_advised_titles=?, publications_last_synced=NOW() WHERE user_id=?");
            $stmt_adv->bind_param("ssssssssi", $spec, $hire_date, $academic_degree, $graduation_year, $google_scholar_url, $google_scholar_id, $published_papers, $previous_advised_titles, $edit_id);
            $stmt_adv->execute();
        } else if ($role == 'panel') {
            $spec = sanitize($_POST['specialization'] ?? '');
            $academic_degree = sanitize($_POST['academic_degree'] ?? '');
            $graduation_year = sanitize($_POST['graduation_year'] ?? '');
            $google_scholar_url = sanitize($_POST['google_scholar_url'] ?? '');
            $google_scholar_url = format_google_scholar_link($google_scholar_url, $name);
            $google_scholar_id = extract_google_scholar_user_id($google_scholar_url);
            $published_papers = sanitize($_POST['published_papers'] ?? '');
            $previous_advised_titles = sanitize($_POST['previous_advised_titles'] ?? '');

            if (!empty($published_papers) || !empty($previous_advised_titles)) {
                $inferred = extract_specializations_from_publications($_POST['published_papers'] ?? $published_papers, $_POST['previous_advised_titles'] ?? $previous_advised_titles, $_POST['specialization'] ?? $spec);
                if (!empty($inferred['merged_expertise'])) {
                    $spec = sanitize($inferred['merged_expertise']);
                }
            }

            $stmt_pan = $conn->prepare("UPDATE panel_profile SET expertise=?, academic_degree=?, graduation_year=?, google_scholar_url=?, google_scholar_id=?, published_papers=?, previous_advised_titles=?, publications_last_synced=NOW() WHERE user_id=?");
            $stmt_pan->bind_param("sssssssi", $spec, $academic_degree, $graduation_year, $google_scholar_url, $google_scholar_id, $published_papers, $previous_advised_titles, $edit_id);
            $stmt_pan->execute();
        } else if ($role == 'student') {
            $edit_school_id = sanitize($_POST['school_id'] ?? '');
            if (!empty($_POST['student_course'])) {
                $student_dept = sanitize($_POST['student_course']);
                $stmt_stu = $conn->prepare("UPDATE student_profile SET department=?, student_id=? WHERE user_id=?");
                $stmt_stu->bind_param("ssi", $student_dept, $edit_school_id, $edit_id);
                $stmt_stu->execute();
                $department = $student_dept;
            } else {
                $stmt_stu = $conn->prepare("UPDATE student_profile SET student_id=? WHERE user_id=?");
                $stmt_stu->bind_param("si", $edit_school_id, $edit_id);
                $stmt_stu->execute();
            }
        }
        
        log_audit('USER_UPDATE', "Admin updated account details for '{$name}' (Role: {$role}, ID #{$edit_id})" . ($pwd_changed ? " including password." : "."), 'User Management', 'success', ['target_user_id' => $edit_id, 'name' => $name, 'role' => $role, 'email' => $email, 'password_reset' => $pwd_changed]);
        $_SESSION['msg'] = "User details updated successfully!";
    }
    $redirect_dept = isset($department) ? urlencode($department) : (isset($_POST['department']) ? urlencode($_POST['department']) : 'Computer Engineering');
    header("Location: dashboard.php?tab=users&dept=" . $redirect_dept);
    exit();
}

if(isset($_GET['archive_user'])) {
    if($_SESSION['role'] == 'admin') {
        $archive_id = (int)$_GET['archive_user'];
        $u_info = $conn->query("SELECT name, role FROM user WHERE id=$archive_id")->fetch_assoc();
        $u_name = $u_info['name'] ?? "User #$archive_id";
        $u_role = $u_info['role'] ?? 'user';

        $conn->query("UPDATE user SET account_status='archived' WHERE id=$archive_id");
        log_audit('USER_ARCHIVE', "Admin moved user '{$u_name}' (Role: {$u_role}, ID #{$archive_id}) to archive.", 'User Management', 'warning', ['target_user_id' => $archive_id, 'name' => $u_name, 'role' => $u_role]);
        $_SESSION['msg'] = "User successfully archived.";
    }
    $redirect_dept = isset($_GET['dept']) ? urlencode($_GET['dept']) : 'Computer Engineering';
    header("Location: dashboard.php?tab=users&dept=" . $redirect_dept);
    exit();
}

if(isset($_GET['restore_user'])) {
    if($_SESSION['role'] == 'admin') {
        $restore_id = (int)$_GET['restore_user'];
        $u_info = $conn->query("SELECT name, role FROM user WHERE id=$restore_id")->fetch_assoc();
        $u_name = $u_info['name'] ?? "User #$restore_id";
        $u_role = $u_info['role'] ?? 'user';

        $conn->query("UPDATE user SET account_status='active' WHERE id=$restore_id");
        log_audit('USER_RESTORE', "Admin restored user '{$u_name}' (Role: {$u_role}, ID #{$restore_id}) to active status.", 'User Management', 'success', ['target_user_id' => $restore_id, 'name' => $u_name, 'role' => $u_role]);
        $_SESSION['msg'] = "User successfully restored to active status.";
    }
    header("Location: dashboard.php?tab=archived");
    exit();
}

if(isset($_GET['restore_all_archived'])) {
    if($_SESSION['role'] == 'admin') {
        $conn->query("UPDATE user SET account_status='active' WHERE account_status='archived'");
        $count = $conn->affected_rows;
        if($count > 0) {
            log_audit('RESTORE_ALL_ARCHIVED', "Admin bulk restored {$count} archived user(s) to active status.", 'User Management', 'success', ['count' => $count]);
            $_SESSION['msg'] = "Successfully restored {$count} archived user(s) to active status.";
        } else {
            $_SESSION['msg'] = "No archived users found to restore.";
        }
    }
    header("Location: dashboard.php?tab=archived");
    exit();
}

if(isset($_GET['delete_all_archived'])) {
    if($_SESSION['role'] == 'admin') {
        $admin_id = (int)$_SESSION['user_id'];
        $res = $conn->query("SELECT id FROM user WHERE account_status='archived' AND id != $admin_id");
        $deleted_count = 0;
        if($res && $res->num_rows > 0) {
            while($row = $res->fetch_assoc()) {
                $del_id = (int)$row['id'];
                $conn->query("DELETE FROM match_request WHERE student_id = $del_id OR adviser_id = $del_id");
                $conn->query("DELETE FROM thesis_panel WHERE student_id = $del_id OR panel_id = $del_id");
                $conn->query("DELETE FROM announcements WHERE adviser_id = $del_id");
                $conn->query("DELETE FROM adviser_profile WHERE user_id = $del_id");
                $conn->query("DELETE FROM student_profile WHERE user_id = $del_id");
                $conn->query("DELETE FROM panel_profile WHERE user_id = $del_id");
                $conn->query("DELETE FROM user WHERE id = $del_id");
                $deleted_count++;
            }
            log_audit('DELETE_ALL_ARCHIVED', "Admin permanently deleted {$deleted_count} archived user account(s).", 'User Management', 'warning', ['deleted_count' => $deleted_count]);
            $_SESSION['msg'] = "Successfully deleted {$deleted_count} archived user(s) permanently.";
        } else {
            $_SESSION['msg'] = "No archived users found to delete.";
        }
    }
    header("Location: dashboard.php?tab=archived");
    exit();
}
if(isset($_POST['admin_add_user'])) {
    if($_SESSION['role'] == 'admin') {
        $firstname = sanitize($_POST['firstname']);
        $lastname = sanitize($_POST['lastname']);
        $name = $firstname . ' ' . $lastname;
        $email = sanitize($_POST['email']);
        $department = sanitize($_POST['department']);
        $role = sanitize($_POST['role']); // 'adviser', 'student', 'panel'
        
        // If student/adviser and a course was selected, use that department/course
        if (($role == 'student' || $role == 'adviser') && !empty($_POST['student_course'])) {
            $department = sanitize($_POST['student_course']);
        }
        
        // Password (custom or default)
        $password = !empty($_POST['password']) ? trim($_POST['password']) : "password123";
        $password_hashed = password_hash($password, PASSWORD_BCRYPT);
        
        $check = $conn->prepare("SELECT id FROM user WHERE email = ? AND role = ?");
        $check->bind_param("ss", $email, $role);
        $check->execute();
        if($check->get_result()->num_rows > 0) {
            $_SESSION['msg'] = "Error: An account with email '{$email}' already exists for role " . ucfirst($role) . "!";
        } else {
            $stmt = $conn->prepare("INSERT INTO user (firstname, lastname, name, email, password, role, account_status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
            $stmt->bind_param("ssssss", $firstname, $lastname, $name, $email, $password_hashed, $role);
            $stmt->execute();
            $user_id = $conn->insert_id;
            
            if ($role == 'adviser') {
                $spec = sanitize($_POST['specialization'] ?? '');
                $hire_date = sanitize($_POST['hire_date'] ?? '');
                $academic_degree = sanitize($_POST['academic_degree'] ?? '');
                $graduation_year = sanitize($_POST['graduation_year'] ?? '');
                $google_scholar_url = sanitize($_POST['google_scholar_url'] ?? '');
                $google_scholar_url = format_google_scholar_link($google_scholar_url, $name);
                $google_scholar_id = extract_google_scholar_user_id($google_scholar_url);
                $published_papers = sanitize($_POST['published_papers'] ?? '');
                $previous_advised_titles = sanitize($_POST['previous_advised_titles'] ?? '');

                // If Google Scholar URL provided and published_papers is empty, fetch from Google Scholar automatically
                if (!empty($google_scholar_url) && empty($published_papers)) {
                    $gs_res = fetch_google_scholar_publications($google_scholar_url, $name, $department, $spec);
                    if (!empty($gs_res['formatted_publications'])) {
                        $published_papers = sanitize($gs_res['formatted_publications']);
                    }
                    if (!empty($gs_res['google_scholar_id'])) {
                        $google_scholar_id = sanitize($gs_res['google_scholar_id']);
                    }
                }

                // Use publications & previous supervised titles to enrich specializations
                if (!empty($published_papers) || !empty($previous_advised_titles)) {
                    $inferred = extract_specializations_from_publications($_POST['published_papers'] ?? $published_papers, $_POST['previous_advised_titles'] ?? $previous_advised_titles, $_POST['specialization'] ?? $spec);
                    if (!empty($inferred['merged_expertise'])) {
                        $spec = sanitize($inferred['merged_expertise']);
                    }
                }

                $stmt_adv = $conn->prepare("INSERT INTO adviser_profile (user_id, department, expertise, capacity, schedule, hire_date, academic_degree, graduation_year, google_scholar_url, google_scholar_id, published_papers, previous_advised_titles, publications_last_synced) VALUES (?, ?, ?, 5, '', ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt_adv->bind_param("isssssssss", $user_id, $department, $spec, $hire_date, $academic_degree, $graduation_year, $google_scholar_url, $google_scholar_id, $published_papers, $previous_advised_titles);
                $stmt_adv->execute();
            } else if ($role == 'student') {
                $sch_id = sanitize($_POST['school_id'] ?? '');
                $stmt_stu = $conn->prepare("INSERT INTO student_profile (user_id, student_id, department, skills, thesis_topic) VALUES (?, ?, ?, '', '')");
                $stmt_stu->bind_param("iss", $user_id, $sch_id, $department);
                $stmt_stu->execute();
            } else if ($role == 'panel') {
                $spec = sanitize($_POST['specialization'] ?? '');
                $academic_degree = sanitize($_POST['academic_degree'] ?? '');
                $graduation_year = sanitize($_POST['graduation_year'] ?? '');
                $google_scholar_url = sanitize($_POST['google_scholar_url'] ?? '');
                $google_scholar_url = format_google_scholar_link($google_scholar_url, $name);
                $google_scholar_id = extract_google_scholar_user_id($google_scholar_url);
                $published_papers = sanitize($_POST['published_papers'] ?? '');
                $previous_advised_titles = sanitize($_POST['previous_advised_titles'] ?? '');

                if (!empty($google_scholar_url) && empty($published_papers)) {
                    $gs_res = fetch_google_scholar_publications($google_scholar_url, $name, $department, $spec);
                    if (!empty($gs_res['formatted_publications'])) {
                        $published_papers = sanitize($gs_res['formatted_publications']);
                    }
                }
                if (!empty($published_papers) || !empty($previous_advised_titles)) {
                    $inferred = extract_specializations_from_publications($_POST['published_papers'] ?? $published_papers, $_POST['previous_advised_titles'] ?? $previous_advised_titles, $_POST['specialization'] ?? $spec);
                    if (!empty($inferred['merged_expertise'])) {
                        $spec = sanitize($inferred['merged_expertise']);
                    }
                }

                $stmt_pan = $conn->prepare("INSERT INTO panel_profile (user_id, department, expertise, academic_degree, graduation_year, google_scholar_url, google_scholar_id, published_papers, previous_advised_titles, publications_last_synced) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt_pan->bind_param("issssssss", $user_id, $department, $spec, $academic_degree, $graduation_year, $google_scholar_url, $google_scholar_id, $published_papers, $previous_advised_titles);
                $stmt_pan->execute();
            } else if ($role == 'secretary') {
                $office = sanitize($_POST['office'] ?? 'CEIT Defense Secretariat Office');
                $stmt_sec = $conn->prepare("INSERT INTO secretary_profile (user_id, department, office) VALUES (?, ?, ?)");
                $stmt_sec->bind_param("iss", $user_id, $department, $office);
                $stmt_sec->execute();
            }
            
            log_audit('USER_CREATE', "Admin created new user '{$name}' with role " . ucfirst($role) . " in '{$department}' (ID #{$user_id}).", 'User Management', 'success', ['user_id' => $user_id, 'name' => $name, 'role' => $role, 'department' => $department, 'email' => $email]);
            $_SESSION['msg'] = "User added successfully to " . $department . "!";
        }
    }
    header("Location: dashboard.php?tab=users&dept=" . urlencode($department));
    exit();
}

if(isset($_POST['assign_panel'])) {
    if($_SESSION['role'] == 'admin') {
        $student_id = (int)$_POST['student_id'];
        $panel_ids = isset($_POST['panel_id']) && is_array($_POST['panel_id']) ? $_POST['panel_id'] : [];
        
        if (count($panel_ids) > 0) {
            $assigned_count = 0;
            foreach($panel_ids as $pid) {
                $panel_id = (int)$pid;
                $check = $conn->query("SELECT id FROM thesis_panel WHERE student_id=$student_id AND panel_id=$panel_id");
                if($check->num_rows == 0) {
                    $conn->query("INSERT INTO thesis_panel (student_id, panel_id) VALUES ($student_id, $panel_id)");
                    $assigned_count++;
                }
            }
            $s_info = $conn->query("SELECT name FROM user WHERE id=$student_id")->fetch_assoc();
            $s_name = $s_info['name'] ?? "Student #$student_id";
            log_audit('ASSIGN_PANEL', "Admin assigned {$assigned_count} panel member(s) to student '{$s_name}' (ID #{$student_id}).", 'Capstone & Panel', 'success', ['student_id' => $student_id, 'assigned_count' => $assigned_count, 'panel_ids' => $panel_ids]);
            $_SESSION['msg'] = "$assigned_count Panel member(s) assigned successfully!";
        } else {
            $_SESSION['msg'] = "Error: Please select at least one panel member.";
        }
    }
    header("Location: dashboard.php?tab=theses");
    exit();
}

// ==========================================
// DEFENSE SCHEDULE MANAGEMENT (ADMINISTRATOR)
// ==========================================
if (isset($_POST['save_defense_schedule'])) {
    if ($_SESSION['role'] !== 'admin') {
        header("Location: dashboard.php");
        exit();
    }

    $schedule_id = !empty($_POST['schedule_id']) ? (int)$_POST['schedule_id'] : 0;
    $student_id = (int)$_POST['student_id'];
    $defense_stage = sanitize($_POST['defense_stage'] ?? 'Final Oral Defense');
    $meeting_date = sanitize($_POST['meeting_date'] ?? date('Y-m-d'));
    $start_time = sanitize($_POST['start_time'] ?? '09:00');
    $end_time = sanitize($_POST['end_time'] ?? '11:00');
    $venue = sanitize($_POST['venue'] ?? 'CEIT Defense Hall');
    $meeting_link = sanitize($_POST['meeting_link'] ?? '');
    $panel_chair_id = !empty($_POST['panel_chair_id']) ? (int)$_POST['panel_chair_id'] : null;
    $mom_documenter_id = !empty($_POST['mom_documenter_id']) && $_POST['mom_documenter_id'] !== 'custom' ? (int)$_POST['mom_documenter_id'] : null;
    $mom_documenter_name = sanitize($_POST['mom_documenter_name'] ?? '');
    if ($mom_documenter_id > 0 && empty($mom_documenter_name)) {
        $doc_u = $conn->query("SELECT name FROM user WHERE id = $mom_documenter_id")->fetch_assoc();
        $mom_documenter_name = $doc_u['name'] ?? '';
    }
    $adviser_id = !empty($_POST['adviser_id']) ? (int)$_POST['adviser_id'] : null;
    $status = sanitize($_POST['status'] ?? 'scheduled');
    $notes = sanitize($_POST['notes'] ?? '');
    $send_notifications = !empty($_POST['send_notifications']) ? 1 : 0;

    // Resolve group_id and title
    $sp_q = $conn->query("SELECT group_id, thesis_topic FROM student_profile WHERE user_id = $student_id");
    $sp_row = $sp_q ? $sp_q->fetch_assoc() : null;
    $group_id = !empty($sp_row['group_id']) ? (int)$sp_row['group_id'] : null;
    $custom_title = !empty($_POST['title']) ? sanitize($_POST['title']) : '';
    if (empty($custom_title)) {
        $topic = !empty($sp_row['thesis_topic']) ? $sp_row['thesis_topic'] : 'Capstone Research Project';
        $custom_title = "{$defense_stage}: {$topic}";
    }

    // Resolve adviser name
    $adviser_name = '';
    if ($adviser_id > 0) {
        $adv_user = $conn->query("SELECT name FROM user WHERE id = $adviser_id")->fetch_assoc();
        $adviser_name = $adv_user['name'] ?? '';
    } else {
        $adv_mr = $conn->query("SELECT u.id, u.name FROM match_request mr JOIN user u ON mr.adviser_id = u.id WHERE mr.student_id = $student_id AND mr.status = 'accepted' LIMIT 1")->fetch_assoc();
        if ($adv_mr) {
            $adviser_id = (int)$adv_mr['id'];
            $adviser_name = $adv_mr['name'];
        }
    }

    // Resolve panel members array
    $panel_member_ids = isset($_POST['panel_member_ids']) && is_array($_POST['panel_member_ids']) ? array_map('intval', $_POST['panel_member_ids']) : [];
    if ($panel_chair_id > 0 && !in_array($panel_chair_id, $panel_member_ids)) {
        $panel_member_ids[] = $panel_chair_id;
    }

    $panel_names_arr = [];
    foreach ($panel_member_ids as $pm_id) {
        $p_user = $conn->query("SELECT name FROM user WHERE id = $pm_id")->fetch_assoc();
        if ($p_user) {
            $role_suffix = ($pm_id === $panel_chair_id) ? ' (Chair)' : '';
            $panel_names_arr[] = $p_user['name'] . $role_suffix;
        }
    }
    $panel_members_json = json_encode($panel_names_arr, JSON_UNESCAPED_UNICODE);

    // Academic Year, Course Code, Department, and Defense Category
    $department = sanitize($_POST['department'] ?? '');
    if (empty($department)) {
        $dept_q = $conn->query("SELECT sp.department, cg.department as g_dept FROM student_profile sp LEFT JOIN capstone_group cg ON sp.group_id = cg.id WHERE sp.user_id = $student_id LIMIT 1");
        if ($dept_q && $d_row = $dept_q->fetch_assoc()) {
            $department = !empty($d_row['department']) ? $d_row['department'] : (!empty($d_row['g_dept']) ? $d_row['g_dept'] : 'Information Technology');
        } else {
            $department = 'Information Technology';
        }
    }
    $department = normalize_course_name($department);
    $esc_department = sanitize($department);

    $academic_year = sanitize($_POST['academic_year'] ?? '');
    if (empty($academic_year)) {
        $academic_year = calculate_academic_year($meeting_date);
    }
    $course_code = sanitize($_POST['course_code'] ?? '');
    if (empty($course_code)) {
        $course_code = resolve_course_code($defense_stage, '', $department);
    }
    $defense_category = sanitize($_POST['defense_category'] ?? '');
    if (empty($defense_category)) {
        $defense_category = resolve_defense_category($defense_stage, '');
    }

    $admin_uid = (int)$_SESSION['user_id'];
    $gid_sql = $group_id ? $group_id : "NULL";
    $chair_sql = $panel_chair_id ? $panel_chair_id : "NULL";
    $doc_id_sql = $mom_documenter_id ? $mom_documenter_id : "NULL";
    $esc_doc_name = sanitize($mom_documenter_name);
    $adv_id_sql = $adviser_id ? $adviser_id : "NULL";
    $esc_title = sanitize($custom_title);
    $esc_adv_name = sanitize($adviser_name);
    $esc_p_members = sanitize($panel_members_json);

    if ($schedule_id > 0) {
        $update_sql = "UPDATE defense_meetings SET 
            title = '$esc_title',
            student_id = $student_id,
            group_id = $gid_sql,
            department = '$esc_department',
            defense_stage = '$defense_stage',
            academic_year = '$academic_year',
            course_code = '$course_code',
            defense_category = '$defense_category',
            meeting_date = '$meeting_date',
            start_time = '$start_time',
            end_time = '$end_time',
            venue = '$venue',
            meeting_link = '$meeting_link',
            panel_chair_id = $chair_sql,
            mom_documenter_id = $doc_id_sql,
            mom_documenter_name = '$esc_doc_name',
            panel_members = '$esc_p_members',
            adviser_id = $adv_id_sql,
            adviser_name = '$esc_adv_name',
            status = '$status',
            notes = '$notes'
            WHERE id = $schedule_id";
        $conn->query($update_sql);
        $meeting_id = $schedule_id;
        $audit_action = 'DEFENSE_SCHEDULE_UPDATED';
        $audit_desc = "Admin updated defense schedule #{$schedule_id} for student #{$student_id} ({$department} - {$defense_stage} on {$meeting_date}, {$course_code} - {$academic_year}, Documenter: {$esc_doc_name}).";
    } else {
        $insert_sql = "INSERT INTO defense_meetings (
            title, student_id, group_id, department, defense_stage, academic_year, course_code, defense_category,
            meeting_date, start_time, end_time, venue, meeting_link, panel_chair_id, mom_documenter_id, mom_documenter_name, panel_members,
            adviser_id, adviser_name, status, notes, created_by
        ) VALUES (
            '$esc_title', $student_id, $gid_sql, '$esc_department', '$defense_stage', '$academic_year', '$course_code', '$defense_category',
            '$meeting_date', '$start_time', '$end_time', '$venue', '$meeting_link', $chair_sql, $doc_id_sql, '$esc_doc_name', '$esc_p_members',
            $adv_id_sql, '$esc_adv_name', '$status', '$notes', $admin_uid
        )";
        $conn->query($insert_sql);
        $meeting_id = $conn->insert_id;
        $audit_action = 'DEFENSE_SCHEDULE_CREATED';
        $audit_desc = "Admin created defense schedule #{$meeting_id} for student #{$student_id} ({$department} - {$defense_stage} on {$meeting_date}, {$course_code} - {$academic_year}, Documenter: {$esc_doc_name}).";
    }

    // Synchronize documenter name in defense_meeting_minutes if record exists
    if (!empty($esc_doc_name)) {
        $chk_mom = $conn->query("SELECT id FROM defense_meeting_minutes WHERE meeting_id = $meeting_id");
        if ($chk_mom && $chk_mom->num_rows > 0) {
            $conn->query("UPDATE defense_meeting_minutes SET recorded_by_name = '$esc_doc_name' WHERE meeting_id = $meeting_id");
        }
    }

    // Synchronize thesis_panel table for all selected panel members
    foreach ($panel_member_ids as $pid) {
        $chk_tp = $conn->query("SELECT id FROM thesis_panel WHERE student_id = $student_id AND panel_id = $pid");
        if (!$chk_tp || $chk_tp->num_rows == 0) {
            $conn->query("INSERT INTO thesis_panel (student_id, group_id, panel_id, status) VALUES ($student_id, $gid_sql, $pid, 'assigned')");
        }
    }

    // Send notifications if checked
    if ($send_notifications) {
        $nice_date = date('D, M j, Y', strtotime($meeting_date));
        $nice_start = date('g:i A', strtotime($start_time));
        $nice_end = date('g:i A', strtotime($end_time));
        $time_str = "{$nice_start} - {$nice_end}";

        // Student / Group Name
        $stu_user = $conn->query("SELECT u.name, cg.group_name FROM user u LEFT JOIN student_profile sp ON u.id = sp.user_id LEFT JOIN capstone_group cg ON sp.group_id = cg.id WHERE u.id = $student_id")->fetch_assoc();
        $target_display = !empty($stu_user['group_name']) ? "Group {$stu_user['group_name']} ({$stu_user['name']})" : ($stu_user['name'] ?? 'Candidate');

        // 1. Notify Student(s)
        $student_recipients = [$student_id];
        if ($group_id > 0) {
            $g_roster = get_group_members_roster($conn, $group_id);
            foreach ($g_roster as $gm) {
                if (!empty($gm['student_id'])) {
                    $student_recipients[] = (int)$gm['student_id'];
                }
            }
        }
        $student_recipients = array_unique($student_recipients);
        $stu_notif_title = "📅 Oral Defense Scheduled: {$defense_stage}";
        $stu_notif_msg = "Your {$defense_stage} is officially scheduled on {$nice_date} ({$time_str}) at {$venue}. Panel Chair: " . ($panel_chair_id ? "Assigned" : "TBA") . (!empty($esc_doc_name) ? " • Documenter (MOM): {$esc_doc_name}" : "") . ". Please review the guidelines.";
        foreach ($student_recipients as $s_uid) {
            create_notification($conn, $s_uid, $stu_notif_title, $stu_notif_msg, 'defense_schedule', 'student_dashboard.php?tab=schedule');
        }

        // Also add update in capstone_group_updates if in group
        if ($group_id > 0) {
            $conn->query("INSERT INTO capstone_group_updates (group_id, user_id, author_name, author_role, update_type, title, message) 
                          VALUES ($group_id, $admin_uid, 'CEIT Administrator', 'Administrator', 'schedule', 'Official Defense Schedule Assigned', 'Your {$defense_stage} has been scheduled for {$nice_date} from {$time_str} at {$venue}.')");
        }

        // 2. Notify Adviser
        if ($adviser_id > 0) {
            $adv_notif_title = "📅 Advisee Defense Scheduled: {$target_display}";
            $adv_notif_msg = "Your advisee {$target_display} has been scheduled for {$defense_stage} on {$nice_date} from {$time_str} at {$venue}.";
            create_notification($conn, $adviser_id, $adv_notif_title, $adv_notif_msg, 'defense_schedule', 'faculty_dashboard.php?tab=schedule');
        }

        // 3. Notify Panel Committee
        foreach ($panel_member_ids as $pid) {
            $is_chair = ($pid === $panel_chair_id);
            $role_label = $is_chair ? "Committee Chair" : "Panel Member";
            $p_notif_title = "🎙️ Defense Panel Assignment: {$target_display}";
            $p_notif_msg = "You are assigned as {$role_label} for {$target_display}'s {$defense_stage} on {$nice_date} ({$time_str}) at {$venue}.";
            create_notification($conn, $pid, $p_notif_title, $p_notif_msg, 'defense_schedule', "panel_dashboard.php?tab=meeting_doc&student_id={$student_id}");
        }

        // 4. Notify MOM Documenter (if assigned and not already chair/adviser)
        if ($mom_documenter_id > 0 && $mom_documenter_id !== $panel_chair_id && $mom_documenter_id !== $adviser_id) {
            $doc_notif_title = "📝 Assigned as MOM Documenter: {$target_display}";
            $doc_notif_msg = "You are designated to record and prepare the Minutes of the Meeting (MOM) for {$target_display}'s {$defense_stage} on {$nice_date} ({$time_str}) at {$venue}.";
            create_notification($conn, $mom_documenter_id, $doc_notif_title, $doc_notif_msg, 'defense_schedule', "panel_dashboard.php?tab=meeting_doc&student_id={$student_id}&meeting_id={$meeting_id}");
        }
    }

    log_audit($audit_action, $audit_desc, 'Defense Schedule', 'success');
    $_SESSION['msg'] = ($schedule_id > 0) ? "Defense schedule updated and notifications sent successfully!" : "Defense schedule created and participants notified successfully!";
    header("Location: dashboard.php?tab=schedules");
    exit();
}

if (isset($_POST['delete_defense_schedule'])) {
    if ($_SESSION['role'] !== 'admin') {
        header("Location: dashboard.php");
        exit();
    }
    $schedule_id = (int)$_POST['schedule_id'];
    $m_info = $conn->query("SELECT * FROM defense_meetings WHERE id = $schedule_id")->fetch_assoc();
    if ($m_info) {
        $conn->query("DELETE FROM defense_meetings WHERE id = $schedule_id");
        log_audit('DEFENSE_SCHEDULE_DELETED', "Admin deleted defense schedule #{$schedule_id} for student #{$m_info['student_id']}.", 'Defense Schedule', 'warning');
        $_SESSION['msg'] = "Defense schedule has been removed.";
    }
    header("Location: dashboard.php?tab=schedules");
    exit();
}

if (isset($_POST['send_defense_reminder'])) {
    if ($_SESSION['role'] !== 'admin') {
        header("Location: dashboard.php");
        exit();
    }
    $schedule_id = (int)$_POST['schedule_id'];
    $m = $conn->query("SELECT * FROM defense_meetings WHERE id = $schedule_id")->fetch_assoc();
    if ($m) {
        $nice_date = date('D, M j, Y', strtotime($m['meeting_date']));
        $time_str = date('g:i A', strtotime($m['start_time'])) . " - " . date('g:i A', strtotime($m['end_time']));
        $stage = $m['defense_stage'];
        $venue = !empty($m['venue']) ? $m['venue'] : 'CEIT Defense Hall';
        $stu_id = (int)$m['student_id'];
        $grp_id = !empty($m['group_id']) ? (int)$m['group_id'] : 0;

        // Resolve Candidate Display Name
        $stu_q = $conn->query("SELECT u.name, cg.group_name FROM user u LEFT JOIN student_profile sp ON u.id = sp.user_id LEFT JOIN capstone_group cg ON sp.group_id = cg.id WHERE u.id = $stu_id");
        $stu_user = $stu_q ? $stu_q->fetch_assoc() : null;
        $target_display = !empty($stu_user['group_name']) ? "Group {$stu_user['group_name']} ({$stu_user['name']})" : ($stu_user['name'] ?? 'Candidate');

        // 1. Notify Student(s)
        $student_recipients = [$stu_id];
        if ($grp_id > 0) {
            $g_roster = get_group_members_roster($conn, $grp_id);
            foreach ($g_roster as $gm) {
                if (!empty($gm['student_id'])) $student_recipients[] = (int)$gm['student_id'];
            }
        }
        $student_recipients = array_unique(array_filter(array_map('intval', $student_recipients)));
        $stu_title = "⏰ Defense Schedule Reminder: {$stage}";
        $stu_msg = "Reminder: Your {$stage} is scheduled on {$nice_date} at {$time_str} ({$venue}). Please be prepared with your presentation slides and documents.";
        foreach ($student_recipients as $r_uid) {
            create_notification($conn, $r_uid, $stu_title, $stu_msg, 'defense_schedule', 'student_dashboard.php?tab=schedule');
        }

        // 2. Notify Adviser
        $adviser_id = !empty($m['adviser_id']) ? (int)$m['adviser_id'] : 0;
        if ($adviser_id <= 0 && !empty($m['adviser_name'])) {
            $esc_adv = $conn->real_escape_string(trim($m['adviser_name']));
            $adv_lookup = $conn->query("SELECT id FROM user WHERE role != 'student' AND (name = '$esc_adv' OR CONCAT(firstname, ' ', lastname) = '$esc_adv' OR name LIKE '%$esc_adv%' OR CONCAT(firstname, ' ', lastname) LIKE '%$esc_adv%') ORDER BY (role='adviser') DESC, (role='faculty') DESC LIMIT 1");
            if ($adv_lookup && $adv_lookup->num_rows > 0) {
                $adviser_id = (int)$adv_lookup->fetch_assoc()['id'];
            }
        }
        if ($adviser_id <= 0) {
            $mr_q = $conn->query("SELECT adviser_id FROM match_request WHERE student_id = $stu_id AND status = 'accepted' LIMIT 1");
            if ($mr_q && $mr_q->num_rows > 0) {
                $adviser_id = (int)$mr_q->fetch_assoc()['adviser_id'];
            }
        }
        if ($adviser_id > 0) {
            if (empty($m['adviser_id'])) {
                $conn->query("UPDATE defense_meetings SET adviser_id = $adviser_id WHERE id = $schedule_id");
            }
            $adv_title = "⏰ Advisee Defense Reminder: {$target_display}";
            $adv_msg = "Reminder: Your advisee {$target_display}'s {$stage} is scheduled on {$nice_date} ({$time_str}) at {$venue}.";
            create_notification($conn, $adviser_id, $adv_title, $adv_msg, 'defense_schedule', 'faculty_dashboard.php?tab=schedule');
        }

        // 3. Notify Panel Committee Members & Chair
        $panel_recipients = [];

        // Add Chair
        if (!empty($m['panel_chair_id'])) {
            $panel_recipients[] = (int)$m['panel_chair_id'];
        }

        // Add from thesis_panel table
        $tp_where = "student_id = $stu_id";
        if ($grp_id > 0) $tp_where .= " OR group_id = $grp_id";
        $tp_q = $conn->query("SELECT panel_id FROM thesis_panel WHERE $tp_where");
        if ($tp_q) {
            while ($tp_row = $tp_q->fetch_assoc()) {
                if (!empty($tp_row['panel_id'])) $panel_recipients[] = (int)$tp_row['panel_id'];
            }
        }

        // Add from panel_members column (supports JSON array or string/comma list)
        if (!empty($m['panel_members'])) {
            $raw_members = $m['panel_members'];
            $names_list = [];
            if (strpos($raw_members, '[') === 0) {
                $decoded = json_decode($raw_members, true);
                if (is_array($decoded)) $names_list = $decoded;
            } else {
                $names_list = explode(',', $raw_members);
            }
            foreach ($names_list as $raw_name) {
                $clean_name = trim(preg_replace('/\s*\(Chair\)\s*/i', '', $raw_name));
                if (empty($clean_name)) continue;
                $esc_name = $conn->real_escape_string($clean_name);
                $p_user_q = $conn->query("SELECT id FROM user WHERE role != 'student' AND (name = '$esc_name' OR CONCAT(firstname, ' ', lastname) = '$esc_name' OR name LIKE '%$esc_name%' OR CONCAT(firstname, ' ', lastname) LIKE '%$esc_name%') ORDER BY (role='panel') DESC, (role='adviser') DESC, (role='faculty') DESC LIMIT 1");
                if ($p_user_q && $p_user_q->num_rows > 0) {
                    $pid = (int)$p_user_q->fetch_assoc()['id'];
                    $panel_recipients[] = $pid;
                    // Auto-sync into thesis_panel if missing
                    $chk = $conn->query("SELECT id FROM thesis_panel WHERE student_id = $stu_id AND panel_id = $pid");
                    if (!$chk || $chk->num_rows == 0) {
                        $gid_val = $grp_id > 0 ? $grp_id : "NULL";
                        $conn->query("INSERT INTO thesis_panel (student_id, group_id, panel_id, status) VALUES ($stu_id, $gid_val, $pid, 'assigned')");
                    }
                }
            }
        }

        $panel_recipients = array_unique(array_filter(array_map('intval', $panel_recipients)));
        foreach ($panel_recipients as $pid) {
            $is_chair = (!empty($m['panel_chair_id']) && (int)$m['panel_chair_id'] === $pid);
            $role_str = $is_chair ? "Committee Chair" : "Panel Member";
            $p_title = "⏰ Panel Session Reminder: {$target_display}";
            $p_msg = "Reminder: You are scheduled as {$role_str} for {$target_display}'s {$stage} on {$nice_date} ({$time_str}) at {$venue}.";
            create_notification($conn, $pid, $p_title, $p_msg, 'defense_schedule', "panel_dashboard.php?tab=meeting_doc&student_id={$stu_id}&meeting_id={$schedule_id}");
        }

        // 4. Notify MOM Documenter (if assigned and not already chair/panel)
        if (!empty($m['mom_documenter_id']) && !in_array((int)$m['mom_documenter_id'], $panel_recipients)) {
            $doc_id = (int)$m['mom_documenter_id'];
            $doc_title = "📝 MOM Documenter Reminder: {$target_display}";
            $doc_msg = "Reminder: You are designated to record the Minutes of the Meeting (MOM) for {$target_display}'s {$stage} on {$nice_date} ({$time_str}) at {$venue}.";
            create_notification($conn, $doc_id, $doc_title, $doc_msg, 'defense_schedule', "secretary_dashboard.php?tab=meeting_doc&student_id={$stu_id}&meeting_id={$schedule_id}");
        }

        log_audit('DEFENSE_REMINDER_SENT', "Admin re-sent defense schedule reminders for schedule #{$schedule_id} to student, adviser, and panel members.", 'Defense Schedule', 'success');
        $_SESSION['msg'] = "Reminder notifications dispatched successfully to student, adviser, and panel members!";
    }
    header("Location: dashboard.php?tab=schedules");
    exit();
}

if(isset($_POST['panel_grade_submit'])) {
    if($_SESSION['role'] == 'panel') {
        $tp_id = (int)$_POST['thesis_panel_id'];
        $grade = (float)$_POST['grade'];
        $feedback = sanitize($_POST['feedback']);
        
        $stmt = $conn->prepare("UPDATE thesis_panel SET grade=?, feedback=?, status='graded' WHERE id=? AND panel_id=?");
        $stmt->bind_param("dsii", $grade, $feedback, $tp_id, $_SESSION['user_id']);
        $stmt->execute();
        
        $_SESSION['msg'] = "Grade and feedback submitted successfully!";
    }
    header("Location: dashboard.php?tab=assigned");
    exit();
}

if(isset($_POST['advanced_panel_grade_submit'])) {
    if($_SESSION['role'] == 'panel') {
        $tp_id = (int)$_POST['thesis_panel_id'];
        $final_grade = (float)$_POST['final_grade'];
        $feedback = sanitize($_POST['feedback']);
        $rubric_scores = isset($_POST['rubric_scores_json']) ? $_POST['rubric_scores_json'] : null;
        
        $stmt = $conn->prepare("UPDATE thesis_panel SET grade=?, feedback=?, rubric_scores=?, status='graded' WHERE id=? AND panel_id=?");
        $stmt->bind_param("dssii", $final_grade, $feedback, $rubric_scores, $tp_id, $_SESSION['user_id']);
        $stmt->execute();
        
        log_audit('PANEL_EVALUATION_SUBMIT', "Panel member '{$_SESSION['name']}' submitted CEIT-T07A rubric evaluation with score {$final_grade}/100.", 'Grading & Evaluation', 'success', ['thesis_panel_id' => $tp_id, 'score' => $final_grade]);
        $_SESSION['msg'] = "CEIT-T07A Proposal Hearing Evaluation Rubric submitted successfully!";
    }
    header("Location: dashboard.php?tab=assigned");
    exit();
}

// ==========================================
// CAPSTONE GROUP MANAGEMENT (3 MEMBERS PER GROUP - DIRECT INPUT & SELECTION)
// ==========================================

// Helper to save direct group members
if (!function_exists('save_capstone_direct_members')) {
    function save_capstone_direct_members($conn, $group_id, $m2_name, $m2_id, $m2_email, $m3_name, $m3_id, $m3_email, $topic = '', $m4_name = '', $m4_id = '', $m4_email = '') {
        $gid = (int)$group_id;
        $m2_name = trim($m2_name);
        $m2_id = trim($m2_id);
        $m2_email = trim($m2_email);
        $m3_name = trim($m3_name);
        $m3_id = trim($m3_id);
        $m3_email = trim($m3_email);
        $m4_name = trim($m4_name);
        $m4_id = trim($m4_id);
        $m4_email = trim($m4_email);

        // Update capstone_group table direct columns
        $stmt = $conn->prepare("UPDATE capstone_group SET member2_name=?, member2_student_id=?, member2_email=?, member3_name=?, member3_student_id=?, member3_email=?, member4_name=?, member4_student_id=?, member4_email=? WHERE id=?");
        $stmt->bind_param("sssssssssi", $m2_name, $m2_id, $m2_email, $m3_name, $m3_id, $m3_email, $m4_name, $m4_id, $m4_email, $gid);
        $stmt->execute();

        // Clear non-leader members in capstone_group_member and reset their profiles
        $old_mems = $conn->query("SELECT student_id FROM capstone_group_member WHERE group_id = $gid AND is_leader = 0");
        if ($old_mems) {
            while ($om = $old_mems->fetch_assoc()) {
                if (!empty($om['student_id'])) {
                    $conn->query("UPDATE student_profile SET group_id = NULL, is_group_leader = 0 WHERE user_id = " . (int)$om['student_id']);
                }
            }
        }
        $conn->query("DELETE FROM capstone_group_member WHERE group_id = $gid AND is_leader = 0");

        $count = 1;

        // Re-insert Member 2 if provided
        if (!empty($m2_name)) {
            $m2_uid = null;
            if (!empty($m2_email)) {
                $u = $conn->query("SELECT id FROM user WHERE email='" . sanitize($m2_email) . "' AND role='student' LIMIT 1")->fetch_assoc();
                if ($u) $m2_uid = (int)$u['id'];
            }
            if (!$m2_uid) {
                $u = $conn->query("SELECT id FROM user WHERE name='" . sanitize($m2_name) . "' AND role='student' LIMIT 1")->fetch_assoc();
                if ($u) $m2_uid = (int)$u['id'];
            }

            $m2_uid_sql = $m2_uid ? $m2_uid : "NULL";
            $conn->query("INSERT INTO capstone_group_member (group_id, student_id, member_name, student_no, email, is_leader) 
                          VALUES ($gid, $m2_uid_sql, '" . sanitize($m2_name) . "', '" . sanitize($m2_id) . "', '" . sanitize($m2_email) . "', 0)");
            if ($m2_uid) {
                $conn->query("UPDATE student_profile SET group_id = $gid, is_group_leader = 0 WHERE user_id = $m2_uid");
                if (!empty($topic)) {
                    $conn->query("UPDATE student_profile SET thesis_topic = '" . sanitize($topic) . "' WHERE user_id = $m2_uid AND (thesis_topic IS NULL OR thesis_topic = '')");
                }
            }
            $count++;
        }

        // Re-insert Member 3 if provided
        if (!empty($m3_name)) {
            $m3_uid = null;
            if (!empty($m3_email)) {
                $u = $conn->query("SELECT id FROM user WHERE email='" . sanitize($m3_email) . "' AND role='student' LIMIT 1")->fetch_assoc();
                if ($u) $m3_uid = (int)$u['id'];
            }
            if (!$m3_uid) {
                $u = $conn->query("SELECT id FROM user WHERE name='" . sanitize($m3_name) . "' AND role='student' LIMIT 1")->fetch_assoc();
                if ($u) $m3_uid = (int)$u['id'];
            }

            $m3_uid_sql = $m3_uid ? $m3_uid : "NULL";
            $conn->query("INSERT INTO capstone_group_member (group_id, student_id, member_name, student_no, email, is_leader) 
                          VALUES ($gid, $m3_uid_sql, '" . sanitize($m3_name) . "', '" . sanitize($m3_id) . "', '" . sanitize($m3_email) . "', 0)");
            if ($m3_uid) {
                $conn->query("UPDATE student_profile SET group_id = $gid, is_group_leader = 0 WHERE user_id = $m3_uid");
                if (!empty($topic)) {
                    $conn->query("UPDATE student_profile SET thesis_topic = '" . sanitize($topic) . "' WHERE user_id = $m3_uid AND (thesis_topic IS NULL OR thesis_topic = '')");
                }
            }
            $count++;
        }

        // Re-insert Member 4 if provided
        if (!empty($m4_name)) {
            $m4_uid = null;
            if (!empty($m4_email)) {
                $u = $conn->query("SELECT id FROM user WHERE email='" . sanitize($m4_email) . "' AND role='student' LIMIT 1")->fetch_assoc();
                if ($u) $m4_uid = (int)$u['id'];
            }
            if (!$m4_uid) {
                $u = $conn->query("SELECT id FROM user WHERE name='" . sanitize($m4_name) . "' AND role='student' LIMIT 1")->fetch_assoc();
                if ($u) $m4_uid = (int)$u['id'];
            }

            $m4_uid_sql = $m4_uid ? $m4_uid : "NULL";
            $conn->query("INSERT INTO capstone_group_member (group_id, student_id, member_name, student_no, email, is_leader) 
                          VALUES ($gid, $m4_uid_sql, '" . sanitize($m4_name) . "', '" . sanitize($m4_id) . "', '" . sanitize($m4_email) . "', 0)");
            if ($m4_uid) {
                $conn->query("UPDATE student_profile SET group_id = $gid, is_group_leader = 0 WHERE user_id = $m4_uid");
                if (!empty($topic)) {
                    $conn->query("UPDATE student_profile SET thesis_topic = '" . sanitize($topic) . "' WHERE user_id = $m4_uid AND (thesis_topic IS NULL OR thesis_topic = '')");
                }
            }
            $count++;
        }

        return $count;
    }
}

// 1. Create Capstone Group (Direct member input)
if (isset($_POST['create_group'])) {
    if ($_SESSION['role'] !== 'student') {
        header("Location: dashboard.php");
        exit();
    }
    $uid = $_SESSION['user_id'];
    $group_name = sanitize($_POST['group_name'] ?? '');
    
    // Member 2 direct inputs
    $m2_name = sanitize($_POST['member_2_name'] ?? '');
    $m2_id   = sanitize($_POST['member_2_id'] ?? '');
    $m2_email = sanitize($_POST['member_2_email'] ?? '');

    // Fallback if submitted via dropdown
    if (empty($m2_name) && !empty($_POST['member_2'])) {
        $sel_m2 = (int)$_POST['member_2'];
        $u2 = $conn->query("SELECT u.name, u.email, sp.student_id FROM user u LEFT JOIN student_profile sp ON u.id = sp.user_id WHERE u.id = $sel_m2")->fetch_assoc();
        if ($u2) {
            $m2_name = $u2['name'];
            $m2_id = $u2['student_id'] ?? '';
            $m2_email = $u2['email'] ?? '';
        }
    }

    // Member 3 direct inputs
    $m3_name = sanitize($_POST['member_3_name'] ?? '');
    $m3_id   = sanitize($_POST['member_3_id'] ?? '');
    $m3_email = sanitize($_POST['member_3_email'] ?? '');

    // Fallback if submitted via dropdown
    if (empty($m3_name) && !empty($_POST['member_3'])) {
        $sel_m3 = (int)$_POST['member_3'];
        $u3 = $conn->query("SELECT u.name, u.email, sp.student_id FROM user u LEFT JOIN student_profile sp ON u.id = sp.user_id WHERE u.id = $sel_m3")->fetch_assoc();
        if ($u3) {
            $m3_name = $u3['name'];
            $m3_id = $u3['student_id'] ?? '';
            $m3_email = $u3['email'] ?? '';
        }
    }

    // Member 4 direct inputs (Optional for 4-student teams)
    $m4_name = sanitize($_POST['member_4_name'] ?? '');
    $m4_id   = sanitize($_POST['member_4_id'] ?? '');
    $m4_email = sanitize($_POST['member_4_email'] ?? '');

    // Enforce Student ID & Email requirement for direct members
    if (!empty($m2_name)) {
        if (empty($m2_id)) {
            $_SESSION['msg'] = "Error: Student ID is required for Member 2 ($m2_name).";
            header("Location: dashboard.php?tab=group");
            exit();
        }
        if (empty($m2_email)) {
            $_SESSION['msg'] = "Error: Email address is required for Member 2 ($m2_name).";
            header("Location: dashboard.php?tab=group");
            exit();
        }
    }
    if (!empty($m3_name)) {
        if (empty($m3_id)) {
            $_SESSION['msg'] = "Error: Student ID is required for Member 3 ($m3_name).";
            header("Location: dashboard.php?tab=group");
            exit();
        }
        if (empty($m3_email)) {
            $_SESSION['msg'] = "Error: Email address is required for Member 3 ($m3_name).";
            header("Location: dashboard.php?tab=group");
            exit();
        }
    }
    if (!empty($m4_name)) {
        if (empty($m4_id)) {
            $_SESSION['msg'] = "Error: Student ID is required for Member 4 ($m4_name).";
            header("Location: dashboard.php?tab=group");
            exit();
        }
        if (empty($m4_email)) {
            $_SESSION['msg'] = "Error: Email address is required for Member 4 ($m4_name).";
            header("Location: dashboard.php?tab=group");
            exit();
        }
    }

    // Check if user is already in a group
    $cur_prof = $conn->query("SELECT * FROM student_profile WHERE user_id = $uid")->fetch_assoc();
    if (!empty($cur_prof['group_id'])) {
        $_SESSION['msg'] = "Error: You are already a member of a capstone group.";
        header("Location: dashboard.php?tab=group");
        exit();
    }

    if (empty($group_name)) {
        $group_name = "Team " . $_SESSION['name'];
    }

    $dept = $cur_prof['department'] ?? '';
    $topic = $cur_prof['thesis_topic'] ?? '';

    // Create group record
    $stmt = $conn->prepare("INSERT INTO capstone_group (group_name, leader_id, department, thesis_topic, member2_name, member2_student_id, member2_email, member3_name, member3_student_id, member3_email, member4_name, member4_student_id, member4_email) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sisssssssssss", $group_name, $uid, $dept, $topic, $m2_name, $m2_id, $m2_email, $m3_name, $m3_id, $m3_email, $m4_name, $m4_id, $m4_email);
    $stmt->execute();
    $group_id = $conn->insert_id;

    // Add leader
    $leader_name = sanitize($_SESSION['name']);
    $leader_sno = sanitize($cur_prof['student_id'] ?? '');
    $leader_email = sanitize($_SESSION['email'] ?? '');
    $conn->query("INSERT INTO capstone_group_member (group_id, student_id, member_name, student_no, email, is_leader) 
                  VALUES ($group_id, $uid, '$leader_name', '$leader_sno', '$leader_email', 1)");
    $conn->query("UPDATE student_profile SET group_id = $group_id, is_group_leader = 1 WHERE user_id = $uid");

    // Save direct members (Members 2, 3, and optional 4)
    $total_members = save_capstone_direct_members($conn, $group_id, $m2_name, $m2_id, $m2_email, $m3_name, $m3_id, $m3_email, $topic, $m4_name, $m4_id, $m4_email);

    // Link match requests
    $conn->query("UPDATE match_request SET group_id = $group_id WHERE student_id = $uid");

    log_audit('GROUP_CREATED', "Student '{$_SESSION['name']}' formed capstone group '{$group_name}' with {$total_members} member(s).", 'Capstone & Groups', 'success', ['group_id' => $group_id, 'group_name' => $group_name, 'member_count' => $total_members]);

    $_SESSION['msg'] = "Capstone group '{$group_name}' created successfully with {$total_members} members!";
    header("Location: dashboard.php?tab=group");
    exit();
}

// 2. Update Group Members directly (Leader or Admin)
if (isset($_POST['update_group_members'])) {
    $uid = $_SESSION['user_id'];
    $role = $_SESSION['role'];
    $group_id = (int)$_POST['group_id'];

    $grp = $conn->query("SELECT * FROM capstone_group WHERE id = $group_id")->fetch_assoc();
    if (!$grp || ($role !== 'admin' && $grp['leader_id'] != $uid)) {
        $_SESSION['msg'] = "Error: Only the group leader or administrator can edit group members.";
        header("Location: dashboard.php?tab=" . ($role === 'admin' ? 'theses' : 'group'));
        exit();
    }

    $group_name = !empty($_POST['group_name']) ? sanitize($_POST['group_name']) : $grp['group_name'];
    $conn->query("UPDATE capstone_group SET group_name = '$group_name' WHERE id = $group_id");

    $m2_name = sanitize($_POST['member_2_name'] ?? '');
    $m2_id   = sanitize($_POST['member_2_id'] ?? '');
    $m2_email = sanitize($_POST['member_2_email'] ?? '');

    $m3_name = sanitize($_POST['member_3_name'] ?? '');
    $m3_id   = sanitize($_POST['member_3_id'] ?? '');
    $m3_email = sanitize($_POST['member_3_email'] ?? '');

    $m4_name = sanitize($_POST['member_4_name'] ?? '');
    $m4_id   = sanitize($_POST['member_4_id'] ?? '');
    $m4_email = sanitize($_POST['member_4_email'] ?? '');

    // Enforce Student ID & Email requirement for direct members
    if (!empty($m2_name)) {
        if (empty($m2_id)) {
            $_SESSION['msg'] = "Error: Student ID is required for Member 2 ($m2_name).";
            header("Location: dashboard.php?tab=" . ($role === 'admin' ? 'theses' : 'group'));
            exit();
        }
        if (empty($m2_email)) {
            $_SESSION['msg'] = "Error: Email address is required for Member 2 ($m2_name).";
            header("Location: dashboard.php?tab=" . ($role === 'admin' ? 'theses' : 'group'));
            exit();
        }
    }
    if (!empty($m3_name)) {
        if (empty($m3_id)) {
            $_SESSION['msg'] = "Error: Student ID is required for Member 3 ($m3_name).";
            header("Location: dashboard.php?tab=" . ($role === 'admin' ? 'theses' : 'group'));
            exit();
        }
        if (empty($m3_email)) {
            $_SESSION['msg'] = "Error: Email address is required for Member 3 ($m3_name).";
            header("Location: dashboard.php?tab=" . ($role === 'admin' ? 'theses' : 'group'));
            exit();
        }
    }
    if (!empty($m4_name)) {
        if (empty($m4_id)) {
            $_SESSION['msg'] = "Error: Student ID is required for Member 4 ($m4_name).";
            header("Location: dashboard.php?tab=" . ($role === 'admin' ? 'theses' : 'group'));
            exit();
        }
        if (empty($m4_email)) {
            $_SESSION['msg'] = "Error: Email address is required for Member 4 ($m4_name).";
            header("Location: dashboard.php?tab=" . ($role === 'admin' ? 'theses' : 'group'));
            exit();
        }
    }

    $total = save_capstone_direct_members($conn, $group_id, $m2_name, $m2_id, $m2_email, $m3_name, $m3_id, $m3_email, $grp['thesis_topic'] ?? '', $m4_name, $m4_id, $m4_email);

    log_audit('GROUP_MEMBERS_UPDATED', "Updated members for capstone group '{$group_name}' (now {$total} members).", 'Capstone & Groups', 'success', ['group_id' => $group_id, 'total' => $total]);

    $_SESSION['msg'] = "Capstone group members updated successfully! ({$total} members)";
    header("Location: dashboard.php?tab=" . ($role === 'admin' ? 'theses' : 'group'));
    exit();
}

// 3. Add direct member to existing vacant slot
if (isset($_POST['add_group_member'])) {
    $uid = $_SESSION['user_id'];
    $role = $_SESSION['role'];
    $group_id = (int)$_POST['group_id'];

    $grp = $conn->query("SELECT * FROM capstone_group WHERE id = $group_id")->fetch_assoc();
    if (!$grp || ($role !== 'admin' && $grp['leader_id'] != $uid)) {
        $_SESSION['msg'] = "Error: Only the group leader or administrator can add members.";
        header("Location: dashboard.php?tab=" . ($role === 'admin' ? 'theses' : 'group'));
        exit();
    }

    $new_name = sanitize($_POST['member_name'] ?? '');
    $new_id = sanitize($_POST['student_no'] ?? '');
    $new_email = sanitize($_POST['email'] ?? '');

    // Fallback if selected from peer list
    if (empty($new_name) && !empty($_POST['student_id'])) {
        $st_u = $conn->query("SELECT u.name, u.email, sp.student_id FROM user u LEFT JOIN student_profile sp ON u.id = sp.user_id WHERE u.id = " . (int)$_POST['student_id'])->fetch_assoc();
        if ($st_u) {
            $new_name = $st_u['name'];
            $new_id = $st_u['student_id'] ?? '';
            $new_email = $st_u['email'] ?? '';
        }
    }

    if (empty($new_name)) {
        $_SESSION['msg'] = "Error: Please enter the student's name.";
        header("Location: dashboard.php?tab=" . ($role === 'admin' ? 'theses' : 'group'));
        exit();
    }

    if (empty($new_id)) {
        $_SESSION['msg'] = "Error: Student ID is required.";
        header("Location: dashboard.php?tab=" . ($role === 'admin' ? 'theses' : 'group'));
        exit();
    }

    if (empty($new_email)) {
        $_SESSION['msg'] = "Error: Student email address is required.";
        header("Location: dashboard.php?tab=" . ($role === 'admin' ? 'theses' : 'group'));
        exit();
    }

    // Determine which slot is vacant: Member 2, Member 3, or Member 4
    if (empty($grp['member2_name'])) {
        $m2_name = $new_name; $m2_id = $new_id; $m2_email = $new_email;
        $m3_name = $grp['member3_name']; $m3_id = $grp['member3_student_id']; $m3_email = $grp['member3_email'];
        $m4_name = $grp['member4_name'] ?? ''; $m4_id = $grp['member4_student_id'] ?? ''; $m4_email = $grp['member4_email'] ?? '';
    } elseif (empty($grp['member3_name'])) {
        $m2_name = $grp['member2_name']; $m2_id = $grp['member2_student_id']; $m2_email = $grp['member2_email'];
        $m3_name = $new_name; $m3_id = $new_id; $m3_email = $new_email;
        $m4_name = $grp['member4_name'] ?? ''; $m4_id = $grp['member4_student_id'] ?? ''; $m4_email = $grp['member4_email'] ?? '';
    } elseif (empty($grp['member4_name'])) {
        $m2_name = $grp['member2_name']; $m2_id = $grp['member2_student_id']; $m2_email = $grp['member2_email'];
        $m3_name = $grp['member3_name']; $m3_id = $grp['member3_student_id']; $m3_email = $grp['member3_email'];
        $m4_name = $new_name; $m4_id = $new_id; $m4_email = $new_email;
    } else {
        $_SESSION['msg'] = "Error: Group already has maximum 4 members.";
        header("Location: dashboard.php?tab=" . ($role === 'admin' ? 'theses' : 'group'));
        exit();
    }

    save_capstone_direct_members($conn, $group_id, $m2_name, $m2_id, $m2_email, $m3_name, $m3_id, $m3_email, $grp['thesis_topic'] ?? '', $m4_name, $m4_id, $m4_email);

    $_SESSION['msg'] = "Added '{$new_name}' to your capstone group!";
    header("Location: dashboard.php?tab=" . ($role === 'admin' ? 'theses' : 'group'));
    exit();
}

// 4. Remove member from specific slot (Slot 2, 3, or 4)
if (isset($_POST['remove_group_member'])) {
    $uid = $_SESSION['user_id'];
    $role = $_SESSION['role'];
    $group_id = (int)($_POST['group_id'] ?? 0);
    if ($group_id <= 0 && $role === 'student') {
        $sp_g = $conn->query("SELECT group_id FROM student_profile WHERE user_id = $uid")->fetch_assoc();
        $group_id = (int)($sp_g['group_id'] ?? 0);
    }
    $slot = isset($_POST['slot']) ? (int)$_POST['slot'] : 0;
    $target_student_id = isset($_POST['member_id']) ? (int)$_POST['member_id'] : 0;

    $grp = $conn->query("SELECT * FROM capstone_group WHERE id = $group_id")->fetch_assoc();
    if (!$grp || ($role !== 'admin' && $grp['leader_id'] != $uid)) {
        $_SESSION['msg'] = "Error: You do not have permission to remove members.";
        header("Location: dashboard.php?tab=" . ($role === 'admin' ? 'theses' : 'group'));
        exit();
    }

    $m2_name = $grp['member2_name']; $m2_id = $grp['member2_student_id']; $m2_email = $grp['member2_email'];
    $m3_name = $grp['member3_name']; $m3_id = $grp['member3_student_id']; $m3_email = $grp['member3_email'];
    $m4_name = $grp['member4_name'] ?? ''; $m4_id = $grp['member4_student_id'] ?? ''; $m4_email = $grp['member4_email'] ?? '';

    if ($slot == 2) {
        $m2_name = $m3_name; $m2_id = $m3_id; $m2_email = $m3_email;
        $m3_name = $m4_name; $m3_id = $m4_id; $m3_email = $m4_email;
        $m4_name = ''; $m4_id = ''; $m4_email = '';
    } elseif ($slot == 3) {
        $m3_name = $m4_name; $m3_id = $m4_id; $m3_email = $m4_email;
        $m4_name = ''; $m4_id = ''; $m4_email = '';
    } elseif ($slot == 4) {
        $m4_name = ''; $m4_id = ''; $m4_email = '';
    } else {
        $m2_name = $m3_name; $m2_id = $m3_id; $m2_email = $m3_email;
        $m3_name = $m4_name; $m3_id = $m4_id; $m3_email = $m4_email;
        $m4_name = ''; $m4_id = ''; $m4_email = '';
    }

    save_capstone_direct_members($conn, $group_id, $m2_name, $m2_id, $m2_email, $m3_name, $m3_id, $m3_email, $grp['thesis_topic'] ?? '', $m4_name, $m4_id, $m4_email);

    $_SESSION['msg'] = "Member removed from the group slot.";
    header("Location: dashboard.php?tab=" . ($role === 'admin' ? 'theses' : 'group'));
    exit();
}

// 5. Leave group (member action)
if (isset($_POST['leave_group'])) {
    $uid = $_SESSION['user_id'];
    $mem = $conn->query("SELECT * FROM capstone_group_member WHERE student_id = $uid")->fetch_assoc();
    if ($mem) {
        if ($mem['is_leader']) {
            $_SESSION['msg'] = "Error: As group leader, please disband the group if you wish to dissolve it.";
            header("Location: dashboard.php?tab=group");
            exit();
        }
        $gid = (int)$mem['group_id'];
        $grp = $conn->query("SELECT * FROM capstone_group WHERE id = $gid")->fetch_assoc();
        if ($grp) {
            $m2_name = $grp['member2_name']; $m2_id = $grp['member2_student_id']; $m2_email = $grp['member2_email'];
            $m3_name = $grp['member3_name']; $m3_id = $grp['member3_student_id']; $m3_email = $grp['member3_email'];

            // Clear whichever matches current user
            if ($m2_name == $_SESSION['name'] || $m2_email == $_SESSION['email']) {
                $m2_name = $m3_name; $m2_id = $m3_id; $m2_email = $m3_email;
                $m3_name = ''; $m3_id = ''; $m3_email = '';
            } else {
                $m3_name = ''; $m3_id = ''; $m3_email = '';
            }
            save_capstone_direct_members($conn, $gid, $m2_name, $m2_id, $m2_email, $m3_name, $m3_id, $m3_email, $grp['thesis_topic'] ?? '');
        }
        $conn->query("DELETE FROM capstone_group_member WHERE student_id = $uid");
        $conn->query("UPDATE student_profile SET group_id = NULL, is_group_leader = 0 WHERE user_id = $uid");
        log_audit('GROUP_LEFT', "Student '{$_SESSION['name']}' left their capstone group.", 'Capstone & Groups', 'info', ['group_id' => $gid]);
        $_SESSION['msg'] = "You have successfully left the group.";
    }
    header("Location: dashboard.php?tab=group");
    exit();
}

// 6. Disband group (leader or admin action)
if (isset($_POST['disband_group'])) {
    $uid = $_SESSION['user_id'];
    $role = $_SESSION['role'];
    $group_id = (int)($_POST['group_id'] ?? 0);
    if ($group_id <= 0 && $role === 'student') {
        $sp_g = $conn->query("SELECT group_id FROM student_profile WHERE user_id = $uid")->fetch_assoc();
        $group_id = (int)($sp_g['group_id'] ?? 0);
    }

    $grp = $conn->query("SELECT * FROM capstone_group WHERE id = $group_id")->fetch_assoc();
    if (!$grp || ($role !== 'admin' && $grp['leader_id'] != $uid)) {
        $_SESSION['msg'] = "Error: Permission denied.";
        header("Location: dashboard.php?tab=" . ($role === 'admin' ? 'theses' : 'group'));
        exit();
    }

    $conn->query("UPDATE student_profile SET group_id = NULL, is_group_leader = 0 WHERE group_id = $group_id");
    $conn->query("DELETE FROM capstone_group_member WHERE group_id = $group_id");
    $conn->query("DELETE FROM capstone_group WHERE id = $group_id");
    $conn->query("UPDATE match_request SET group_id = NULL WHERE group_id = $group_id");

    log_audit('GROUP_DISBANDED', "Capstone group '{$grp['group_name']}' was disbanded.", 'Capstone & Groups', 'warning', ['group_id' => $group_id]);

    $_SESSION['msg'] = "Capstone group disbanded successfully.";
    header("Location: dashboard.php?tab=" . ($role === 'admin' ? 'theses' : 'group'));
    exit();
}

// 7. Admin Assign/Configure Group of 3 (Supports both direct text and student selection)
if (isset($_POST['admin_save_group'])) {
    if ($_SESSION['role'] !== 'admin') {
        header("Location: dashboard.php");
        exit();
    }
    $group_id = (int)($_POST['group_id'] ?? 0);
    $group_name = sanitize($_POST['group_name']);
    $student_1 = (int)$_POST['student_1']; // Leader
    
    // Member 2 (direct name or selected id)
    $m2_name = sanitize($_POST['member_2_name'] ?? '');
    $m2_id   = sanitize($_POST['member_2_id'] ?? '');
    $m2_email = sanitize($_POST['member_2_email'] ?? '');
    if (empty($m2_name) && !empty($_POST['student_2'])) {
        $s2_id = (int)$_POST['student_2'];
        $u2 = $conn->query("SELECT u.name, u.email, sp.student_id FROM user u LEFT JOIN student_profile sp ON u.id = sp.user_id WHERE u.id = $s2_id")->fetch_assoc();
        if ($u2) {
            $m2_name = $u2['name'];
            $m2_id = $u2['student_id'] ?? '';
            $m2_email = $u2['email'] ?? '';
        }
    }

    // Member 3 (direct name or selected id)
    $m3_name = sanitize($_POST['member_3_name'] ?? '');
    $m3_id   = sanitize($_POST['member_3_id'] ?? '');
    $m3_email = sanitize($_POST['member_3_email'] ?? '');
    if (empty($m3_name) && !empty($_POST['student_3'])) {
        $s3_id = (int)$_POST['student_3'];
        $u3 = $conn->query("SELECT u.name, u.email, sp.student_id FROM user u LEFT JOIN student_profile sp ON u.id = sp.user_id WHERE u.id = $s3_id")->fetch_assoc();
        if ($u3) {
            $m3_name = $u3['name'];
            $m3_id = $u3['student_id'] ?? '';
            $m3_email = $u3['email'] ?? '';
        }
    }

    if (empty($group_name) || $student_1 <= 0) {
        $_SESSION['msg'] = "Error: Group name and primary student leader are required.";
        header("Location: dashboard.php?tab=theses");
        exit();
    }

    $sp1 = $conn->query("SELECT * FROM student_profile WHERE user_id = $student_1")->fetch_assoc();
    $u1 = $conn->query("SELECT * FROM user WHERE id = $student_1")->fetch_assoc();
    $dept = $sp1['department'] ?? 'Information Technology';
    $topic = $sp1['thesis_topic'] ?? '';

    if ($group_id > 0) {
        $stmt = $conn->prepare("UPDATE capstone_group SET group_name = ?, leader_id = ? WHERE id = ?");
        $stmt->bind_param("sii", $group_name, $student_1, $group_id);
        $stmt->execute();
        $gid = $group_id;
    } else {
        $stmt = $conn->prepare("INSERT INTO capstone_group (group_name, leader_id, department, thesis_topic) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("siss", $group_name, $student_1, $dept, $topic);
        $stmt->execute();
        $gid = $conn->insert_id;
    }

    // Leader record
    $conn->query("DELETE FROM capstone_group_member WHERE group_id = $gid AND is_leader = 1");
    $conn->query("INSERT INTO capstone_group_member (group_id, student_id, member_name, student_no, email, is_leader) 
                  VALUES ($gid, $student_1, '" . sanitize($u1['name'] ?? '') . "', '" . sanitize($sp1['student_id'] ?? '') . "', '" . sanitize($u1['email'] ?? '') . "', 1)");
    $conn->query("UPDATE student_profile SET group_id = $gid, is_group_leader = 1 WHERE user_id = $student_1");

    // Save direct members
    $total = save_capstone_direct_members($conn, $gid, $m2_name, $m2_id, $m2_email, $m3_name, $m3_id, $m3_email, $topic);

    // Link match requests
    $conn->query("UPDATE match_request SET group_id = $gid WHERE student_id = $student_1");

    log_audit('ADMIN_GROUP_ASSIGN', "Admin configured capstone group '{$group_name}' with {$total}/3 members.", 'Capstone & Groups', 'success', ['group_id' => $gid, 'group_name' => $group_name, 'count' => $total]);

    $_SESSION['msg'] = "Capstone group '{$group_name}' successfully configured ({$total}/3 members)!";
    header("Location: dashboard.php?tab=theses");
    exit();
}

// ==========================================
// PANEL COMMENTS ACTIONS
// ==========================================
if (isset($_POST['submit_panel_comment'])) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: dashboard.php");
        exit();
    }

    $uid = (int)$_SESSION['user_id'];
    $author_name = $_SESSION['name'] ?? 'Panel Evaluator';
    $student_id = (int)$_POST['student_id'];
    $group_id = !empty($_POST['group_id']) ? (int)$_POST['group_id'] : null;
    $category = sanitize($_POST['category'] ?? 'General Feedback');
    $feedback_type = sanitize($_POST['feedback_type'] ?? 'Suggestion');
    $comment_text = trim($_POST['comment_text'] ?? '');
    $redirect = sanitize($_POST['redirect_tab'] ?? 'comments');

    $audio_file = save_panel_voice_recording($uid);
    $audio_duration = !empty($_POST['audio_duration']) ? (int)$_POST['audio_duration'] : null;

    if (empty($comment_text) && !empty($audio_file)) {
        $comment_text = "[Voice Recorded Comment]";
    }

    if ((!empty($comment_text) || !empty($audio_file)) && $student_id > 0) {
        $stmt = $conn->prepare("INSERT INTO panel_research_comments (student_id, group_id, panel_id, category, feedback_type, comment_text, audio_file, audio_duration, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'open')");
        $stmt->bind_param("iiissssi", $student_id, $group_id, $uid, $category, $feedback_type, $comment_text, $audio_file, $audio_duration);
        if ($stmt->execute()) {
            notify_student_and_adviser_on_panel_comment($conn, $student_id, $group_id, $author_name, $category, $feedback_type, $comment_text, !empty($audio_file));
            $_SESSION['msg'] = !empty($audio_file)
                ? "Voice recording & panel comment sent to Student and Adviser dashboards!"
                : "Panel comment posted to Student and Adviser dashboards!";
            log_audit('PANEL_COMMENT', "Panel member posted {$category} feedback" . (!empty($audio_file) ? " [with Voice Recording]" : "") . " for student #{$student_id}.", 'Defense Comments', 'success');
        } else {
            $_SESSION['msg'] = "Error posting comment: " . $conn->error;
        }
    }

    header("Location: dashboard.php?tab=" . urlencode($redirect) . "&student_id=" . $student_id);
    exit();
}

if (isset($_POST['delete_panel_comment'])) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: dashboard.php");
        exit();
    }

    $uid = (int)$_SESSION['user_id'];
    $role = $_SESSION['role'] ?? '';
    $cid = (int)$_POST['comment_id'];
    $student_id = (int)($_POST['student_id'] ?? 0);
    $redirect = sanitize($_POST['redirect_tab'] ?? 'comments');

    if ($role === 'admin') {
        $conn->query("DELETE FROM panel_research_comments WHERE id = $cid");
    } else {
        $conn->query("DELETE FROM panel_research_comments WHERE id = $cid AND panel_id = $uid");
    }

    $_SESSION['msg'] = "Comment removed.";
    header("Location: dashboard.php?tab=" . urlencode($redirect) . "&student_id=" . $student_id);
    exit();
}

if (isset($_POST['save_mom_form'])) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: dashboard.php");
        exit();
    }

    $meeting_id = (int)$_POST['meeting_id'];
    $student_id = (int)$_POST['student_id'];
    $group_id = !empty($_POST['group_id']) ? (int)$_POST['group_id'] : null;
    $document_title = sanitize($_POST['document_title'] ?? 'Official Defense Minutes of the Meeting');
    $defense_stage = sanitize($_POST['defense_stage'] ?? 'Final Oral Defense');
    $meeting_date = sanitize($_POST['meeting_date'] ?? date('Y-m-d'));
    $venue = sanitize($_POST['venue'] ?? 'CEIT Defense Hall');
    $discussion_summary = trim($_POST['discussion_summary'] ?? '');
    $decision_verdict = sanitize($_POST['decision_verdict'] ?? 'Approved with Minor Revisions');
    $grade_average = !empty($_POST['grade_average']) ? (float)$_POST['grade_average'] : null;
    $recommendations = trim($_POST['recommendations'] ?? '');
    $action_items = trim($_POST['action_items'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $recorded_by_name = sanitize($_POST['recorded_by_name'] ?? $_SESSION['name'] ?? '');
    $approved_by_chair = sanitize($_POST['approved_by_chair'] ?? '');
    $mom_status = sanitize($_POST['mom_status'] ?? 'draft');

    // Update meeting details
    $conn->query("UPDATE defense_meetings SET title = '" . sanitize($document_title) . "', defense_stage = '" . sanitize($defense_stage) . "', meeting_date = '" . sanitize($meeting_date) . "', venue = '" . sanitize($venue) . "', status = 'completed' WHERE id = $meeting_id");

    // Upsert MOM
    $chk = $conn->query("SELECT id FROM defense_meeting_minutes WHERE meeting_id = $meeting_id");
    if ($chk && $chk->num_rows > 0) {
        $mom_id = (int)$chk->fetch_assoc()['id'];
        $stmt = $conn->prepare("UPDATE defense_meeting_minutes SET document_title = ?, discussion_summary = ?, decision_verdict = ?, grade_average = ?, recommendations = ?, action_items = ?, notes = ?, recorded_by_name = ?, approved_by_chair = ?, status = ? WHERE id = ?");
        $stmt->bind_param("sssdssssssi", $document_title, $discussion_summary, $decision_verdict, $grade_average, $recommendations, $action_items, $notes, $recorded_by_name, $approved_by_chair, $mom_status, $mom_id);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("INSERT INTO defense_meeting_minutes (meeting_id, student_id, group_id, document_title, discussion_summary, decision_verdict, grade_average, recommendations, action_items, notes, recorded_by_name, approved_by_chair, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiisssdssssss", $meeting_id, $student_id, $group_id, $document_title, $discussion_summary, $decision_verdict, $grade_average, $recommendations, $action_items, $notes, $recorded_by_name, $approved_by_chair, $mom_status);
        $stmt->execute();
    }

    log_audit('SAVE_DEFENSE_MOM', "Minutes of the Meeting saved ({$decision_verdict}) for Student #{$student_id}.", 'Defense Documentation', 'success');
    $_SESSION['msg'] = "Minutes of the Meeting saved successfully!";
    header("Location: dashboard.php?tab=meeting_doc&student_id=" . $student_id);
    exit();
}

if (isset($_POST['save_adviser_comment_notes'])) {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'adviser') {
        header("Location: dashboard.php");
        exit();
    }

    $uid = (int)$_SESSION['user_id'];
    $name = $_SESSION['name'] ?? 'Faculty Adviser';
    $comment_id = (int)$_POST['comment_id'];
    $adviser_notes = trim($_POST['adviser_notes'] ?? '');
    $return_tab = sanitize($_POST['return_tab'] ?? 'panel_comments');
    $filter_stu = !empty($_POST['student_id']) ? (int)$_POST['student_id'] : 0;

    $c_res = $conn->query("SELECT * FROM panel_research_comments WHERE id = $comment_id");
    if ($c_res && $c_res->num_rows > 0) {
        $comment = $c_res->fetch_assoc();
        $adv_ids = get_adviser_advisee_ids($conn, $uid);

        // Security check: adviser can ONLY annotate comments for their assigned advisees
        $is_authorized = in_array((int)$comment['student_id'], $adv_ids['student_ids']) || 
                         (!empty($comment['group_id']) && in_array((int)$comment['group_id'], $adv_ids['group_ids']));

        if (!$is_authorized) {
            $_SESSION['msg'] = "Unauthorized: You can only view and manage comments for your assigned advisees.";
            header("Location: faculty_dashboard.php?tab=" . urlencode($return_tab));
            exit();
        }

        $stmt = $conn->prepare("UPDATE panel_research_comments SET adviser_notes = ? WHERE id = ?");
        $stmt->bind_param("si", $adviser_notes, $comment_id);
        if ($stmt->execute()) {
            $_SESSION['msg'] = "Advisory guidance note saved successfully!";
            create_notification(
                $conn, 
                (int)$comment['student_id'], 
                "Adviser Guidance Note on Panel Comment", 
                "Your adviser {$name} added an advisory guidance note on your panel evaluation comment.", 
                "adviser_guidance", 
                "student_dashboard.php?tab=panelists"
            );
            log_audit('ADVISER_COMMENT_GUIDANCE', "Faculty {$name} posted advisory guidance on comment #{$comment_id} for student #{$comment['student_id']}.", 'Advising', 'success');
        } else {
            $_SESSION['msg'] = "Error saving advisory note: " . $conn->error;
        }
    }

    $redirect_url = "faculty_dashboard.php?tab=" . urlencode($return_tab);
    if ($filter_stu > 0) {
        $redirect_url .= "&student_id=" . $filter_stu;
    }
    header("Location: " . $redirect_url);
    exit();
}

// ----------------------------------------------------
// IT-199 Requirements Checklist Action Handlers
// ----------------------------------------------------
if (isset($_POST['update_checklist_item'])) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: index.php");
        exit();
    }

    $uid = (int)$_SESSION['user_id'];
    $user_name = $_SESSION['name'] ?? 'Student';
    $item_id = (int)$_POST['item_id'];
    $link = trim($_POST['attached_link'] ?? '');
    $status = $_POST['status'] ?? 'incomplete';
    $notes = trim($_POST['notes'] ?? '');
    
    // Check item exists
    $chk = $conn->query("SELECT * FROM capstone_checklist_items WHERE id = $item_id");
    if (!$chk || $chk->num_rows == 0) {
        $_SESSION['msg'] = "Requirement item not found.";
        header("Location: dashboard.php?tab=checklist");
        exit();
    }
    $item = $chk->fetch_assoc();

    // Verify permission: student must own it or belong to the group
    $sp = $conn->query("SELECT group_id FROM student_profile WHERE user_id = $uid")->fetch_assoc();
    $student_gid = (int)($sp['group_id'] ?? 0);
    $item_gid = (int)($item['group_id'] ?? 0);
    $item_sid = (int)($item['student_id'] ?? 0);

    $is_authorized = ($item_sid === $uid) || ($student_gid > 0 && $item_gid === $student_gid) || ($_SESSION['role'] === 'admin');
    if (!$is_authorized) {
        $_SESSION['msg'] = "Unauthorized: You do not have permission to edit this checklist.";
        header("Location: dashboard.php?tab=checklist");
        exit();
    }

    // Handle optional file upload
    $file_update_sql = "";
    $new_file_path = $item['file_path'];
    $new_file_name = $item['file_name'];
    if (isset($_FILES['attachment_file']) && $_FILES['attachment_file']['error'] === UPLOAD_ERR_OK) {
        $target_dir = "uploads/checklist_evidence/";
        if (!is_dir(__DIR__ . '/' . $target_dir)) {
            mkdir(__DIR__ . '/' . $target_dir, 0777, true);
        }
        $original_name = basename($_FILES["attachment_file"]["name"]);
        $clean_name = time() . '_' . preg_replace("/[^a-zA-Z0-9._-]/", "_", $original_name);
        $target_file = $target_dir . $clean_name;
        $ext = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        $allowed = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'zip', 'rar', 'jpg', 'png'];
        if (in_array($ext, $allowed)) {
            if (move_uploaded_file($_FILES["attachment_file"]["tmp_name"], __DIR__ . '/' . $target_file)) {
                $new_file_path = $conn->real_escape_string($target_file);
                $new_file_name = $conn->real_escape_string($original_name);
                $file_update_sql = ", file_path = '$new_file_path', file_name = '$new_file_name'";
                
                // If link was empty, auto-populate with file path
                if (empty($link)) {
                    $link = $target_file;
                }
                // Auto-mark completed if file provided and status was incomplete
                if ($status === 'incomplete') {
                    $status = 'completed';
                }
            }
        }
    }

    // Auto-status adjustment if status wasn't explicitly overridden
    if (empty($_POST['status_explicit'])) {
        if (!empty($link) && strtolower($link) !== 'n/a') {
            $status = 'completed';
        } elseif (strtolower($link) === 'n/a') {
            $status = 'not_applicable';
        }
    }

    $link_esc = $conn->real_escape_string($link);
    $status_esc = $conn->real_escape_string($status);
    $notes_esc = $conn->real_escape_string($notes);

    $conn->query("UPDATE capstone_checklist_items SET 
        attached_link = '$link_esc', 
        status = '$status_esc', 
        notes = '$notes_esc', 
        updated_by = $uid 
        $file_update_sql 
        WHERE id = $item_id");

    // Group update log
    if ($student_gid > 0) {
        $grp_msg = "Updated requirement #{$item['item_no']} ({$item['requirement_title']}) to " . strtoupper($status);
        $conn->query("INSERT INTO capstone_group_updates (group_id, user_id, author_name, author_role, update_type, title, message) 
            VALUES ($student_gid, $uid, '" . $conn->real_escape_string($user_name) . "', 'Student', 'checklist', 'Checklist Requirement Updated', '" . $conn->real_escape_string($grp_msg) . "')");
    }

    log_audit('CHECKLIST_UPDATE', "User '{$user_name}' updated checklist item #{$item['item_no']} ({$item['requirement_title']}). Status: {$status}", 'Requirements', 'success');

    if (isset($_POST['ajax_mode'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'status' => $status, 'link' => $link]);
        exit();
    }

    $_SESSION['msg'] = "Requirement #{$item['item_no']} updated successfully!";
    header("Location: dashboard.php?tab=checklist");
    exit();
}

if (isset($_POST['toggle_checklist_status'])) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: index.php");
        exit();
    }

    $uid = (int)$_SESSION['user_id'];
    $user_name = $_SESSION['name'] ?? 'Student';
    $item_id = (int)$_POST['item_id'];

    $chk = $conn->query("SELECT * FROM capstone_checklist_items WHERE id = $item_id");
    if ($chk && $chk->num_rows > 0) {
        $item = $chk->fetch_assoc();
        $new_status = ($item['status'] === 'completed') ? 'incomplete' : 'completed';

        $conn->query("UPDATE capstone_checklist_items SET status = '$new_status', updated_by = $uid WHERE id = $item_id");

        log_audit('CHECKLIST_TOGGLE', "User '{$user_name}' toggled checklist item #{$item['item_no']} to {$new_status}", 'Requirements', 'success');

        if (isset($_POST['ajax_mode']) || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'new_status' => $new_status]);
            exit();
        }
    }

    header("Location: dashboard.php?tab=checklist");
    exit();
}

if (isset($_POST['batch_update_checklist'])) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: index.php");
        exit();
    }

    $uid = (int)$_SESSION['user_id'];
    $user_name = $_SESSION['name'] ?? 'Student';
    $links = $_POST['links'] ?? [];
    $statuses = $_POST['statuses'] ?? [];

    foreach ($links as $id => $val) {
        $id = (int)$id;
        $l_val = trim($val);
        $s_val = $statuses[$id] ?? 'incomplete';
        if (!empty($l_val) && strtolower($l_val) !== 'n/a' && empty($statuses[$id])) {
            $s_val = 'completed';
        }
        $l_esc = $conn->real_escape_string($l_val);
        $s_esc = $conn->real_escape_string($s_val);
        $conn->query("UPDATE capstone_checklist_items SET attached_link = '$l_esc', status = '$s_esc', updated_by = $uid WHERE id = $id");
    }

    $_SESSION['msg'] = "Checklist requirements saved successfully!";
    header("Location: dashboard.php?tab=checklist");
    exit();
}

// Mark sidebar notification badge as seen
if (isset($_GET['action']) && $_GET['action'] === 'mark_badge_seen') {
    $uid = (int)($_SESSION['user_id'] ?? 0);
    $badge_key = $_POST['badge_key'] ?? $_GET['badge_key'] ?? '';
    
    if ($uid > 0 && in_array($badge_key, ['schedules', 'mom_archive'])) {
        $conn->query("CREATE TABLE IF NOT EXISTS user_badge_seen (
            user_id INT NOT NULL,
            badge_key VARCHAR(64) NOT NULL,
            last_seen_id INT NOT NULL DEFAULT 0,
            seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, badge_key)
        )");

        $badge_max_queries = [
            'schedules'   => "SELECT COALESCE(MAX(id), 0) AS m FROM defense_meetings",
            'mom_archive' => "SELECT COALESCE(MAX(id), 0) AS m FROM defense_meeting_minutes",
        ];

        $max_res = $conn->query($badge_max_queries[$badge_key]);
        $max_id = $max_res ? (int)$max_res->fetch_assoc()['m'] : 0;
        
        $stmt = $conn->prepare("INSERT INTO user_badge_seen (user_id, badge_key, last_seen_id) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE last_seen_id = VALUES(last_seen_id)");
        if ($stmt) {
            $stmt->bind_param("isi", $uid, $badge_key, $max_id);
            $stmt->execute();
        }
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'badge_key' => $badge_key, 'last_seen_id' => $max_id]);
        exit();
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => false]);
    exit();
}

// =========================================================
// ADVISER CONSULTATION PROCESS ACTION HANDLERS
// =========================================================

// 1. Student initiates a Consultation Request using the Official Consultation Form
if (isset($_POST['submit_consultation_request'])) {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
        header("Location: dashboard.php");
        exit();
    }

    $student_id = (int)$_SESSION['user_id'];
    $student_name = $_SESSION['name'] ?? 'Student';
    $return_tab = sanitize($_POST['return_tab'] ?? 'consultations');

    // Resolve group_id
    $sp = $conn->query("SELECT group_id, thesis_topic, is_group_leader FROM student_profile WHERE user_id = $student_id")->fetch_assoc() ?: [];
    $group_id = !empty($sp['group_id']) ? (int)$sp['group_id'] : 0;

    // Resolve adviser_id (from form or from accepted/pending match_request)
    $adviser_id = (int)($_POST['adviser_id'] ?? 0);
    if ($adviser_id <= 0) {
        $grp_sql = ($group_id > 0) ? " OR group_id = $group_id" : "";
        $mr = $conn->query("SELECT adviser_id FROM match_request WHERE (student_id = $student_id $grp_sql) AND status IN ('accepted', 'pending') ORDER BY (status='accepted') DESC, id DESC LIMIT 1")->fetch_assoc();
        $adviser_id = (int)($mr['adviser_id'] ?? 0);
    }

    $consultation_stage = sanitize($_POST['consultation_stage'] ?? 'Initial Research Idea / Concept Consultation');
    $research_title = sanitize($_POST['research_title'] ?? '');
    $research_idea_summary = sanitize($_POST['research_idea_summary'] ?? '');
    $consultation_agenda = sanitize($_POST['consultation_agenda'] ?? '');
    $current_progress = sanitize($_POST['current_progress'] ?? '');
    $preferred_date = sanitize($_POST['preferred_date'] ?? date('Y-m-d', strtotime('+2 days')));
    $preferred_time = sanitize($_POST['preferred_time'] ?? '10:00 AM - 11:00 AM');
    $consultation_mode = sanitize($_POST['consultation_mode'] ?? 'In-Person');
    if (!in_array($consultation_mode, ['In-Person', 'Online / Virtual', 'Document / Asynchronous'])) {
        $consultation_mode = 'In-Person';
    }
    $venue_or_link = sanitize($_POST['venue_or_link'] ?? 'Faculty Consultation Room / Google Meet');

    if ($adviser_id <= 0 || empty($research_title) || empty($research_idea_summary) || empty($consultation_agenda)) {
        $_SESSION['msg'] = "Error: Please complete all required fields in the Consultation Request Form (Adviser, Research Idea/Title, Summary, and Consultation Agenda).";
        header("Location: student_dashboard.php?tab=" . urlencode($return_tab));
        exit();
    }

    // Optional file upload
    $att_path_sql = "NULL";
    $att_name_sql = "NULL";
    if (isset($_FILES['consultation_attachment']) && $_FILES['consultation_attachment']['error'] === UPLOAD_ERR_OK) {
        $target_dir = "uploads/consultations/";
        if (!is_dir(__DIR__ . '/' . $target_dir)) {
            mkdir(__DIR__ . '/' . $target_dir, 0777, true);
        }
        $orig_name = basename($_FILES['consultation_attachment']['name']);
        $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
        $allowed = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'png', 'jpg', 'jpeg', 'zip'];
        if (in_array($ext, $allowed)) {
            $safe_name = time() . '_cons_' . preg_replace("/[^a-zA-Z0-9._-]/", "_", $orig_name);
            $dest = $target_dir . $safe_name;
            if (move_uploaded_file($_FILES['consultation_attachment']['tmp_name'], __DIR__ . '/' . $dest)) {
                $att_path_sql = "'" . $conn->real_escape_string($dest) . "'";
                $att_name_sql = "'" . $conn->real_escape_string($orig_name) . "'";
            }
        }
    }

    $gid_sql = ($group_id > 0) ? $group_id : "NULL";
    $ref_no = 'CEIT-CONS-' . date('Y') . '-' . strtoupper(substr(md5(uniqid((string)$student_id, true)), 0, 5));
    $ref_esc = $conn->real_escape_string($ref_no);

    $ins_sql = "INSERT INTO adviser_consultations (
        reference_no, student_id, group_id, adviser_id, consultation_stage,
        research_title, research_idea_summary, consultation_agenda, current_progress,
        preferred_date, preferred_time, consultation_mode, venue_or_link,
        attachment_path, attachment_name, status
    ) VALUES (
        '$ref_esc', $student_id, $gid_sql, $adviser_id, '$consultation_stage',
        '$research_title', '$research_idea_summary', '$consultation_agenda', '$current_progress',
        '$preferred_date', '$preferred_time', '$consultation_mode', '$venue_or_link',
        $att_path_sql, $att_name_sql, 'pending_review'
    )";

    if ($conn->query($ins_sql)) {
        $new_cons_id = (int)$conn->insert_id;

        // If student's thesis_topic was empty, populate it from their own research idea
        if (empty($sp['thesis_topic']) && !empty($research_title)) {
            $conn->query("UPDATE student_profile SET thesis_topic = '$research_title' WHERE user_id = $student_id");
            if ($group_id > 0) {
                $conn->query("UPDATE capstone_group SET thesis_topic = '$research_title' WHERE id = $group_id AND (thesis_topic IS NULL OR thesis_topic = '')");
                $conn->query("UPDATE student_profile SET thesis_topic = '$research_title' WHERE group_id = $group_id AND (thesis_topic IS NULL OR thesis_topic = '')");
            }
        }

        // Update consultation_process_status to 'in_progress' if 'not_started'
        $conn->query("UPDATE student_profile SET consultation_process_status = 'in_progress' WHERE user_id = $student_id AND (consultation_process_status IS NULL OR consultation_process_status = 'not_started')");
        if ($group_id > 0) {
            $conn->query("UPDATE capstone_group SET consultation_process_status = 'in_progress' WHERE id = $group_id AND (consultation_process_status IS NULL OR consultation_process_status = 'not_started')");
            $conn->query("UPDATE student_profile SET consultation_process_status = 'in_progress' WHERE group_id = $group_id AND (consultation_process_status IS NULL OR consultation_process_status = 'not_started')");

            $role_lbl = !empty($sp['is_group_leader']) ? 'Leader' : 'Member';
            $feed_title = "Consultation Form Submitted ({$ref_no})";
            $feed_msg = "{$student_name} ({$role_lbl}) submitted a Research Adviser Consultation Form ({$consultation_stage}) for \"{$research_title}\".";
            $conn->query("INSERT INTO capstone_group_updates (group_id, user_id, author_name, author_role, update_type, title, message, created_at)
                          VALUES ($group_id, $student_id, '" . $conn->real_escape_string($student_name) . "', '$role_lbl', 'meeting', '" . $conn->real_escape_string($feed_title) . "', '" . $conn->real_escape_string($feed_msg) . "', NOW())");
        }

        // Notify Adviser
        create_notification(
            $conn,
            $adviser_id,
            "📋 New Consultation Request: {$student_name}",
            "{$student_name} submitted Consultation Form {$ref_no} ({$consultation_stage}) based on research idea: \"{$research_title}\". Please evaluate readiness or ask follow-up questions.",
            "consultation",
            "faculty_dashboard.php?tab=consultations&cons_id={$new_cons_id}"
        );

        log_audit('CONSULTATION_REQUESTED', "Student '{$student_name}' submitted Consultation Form {$ref_no} ({$consultation_stage}) to Adviser #{$adviser_id}.", 'Adviser Consultation', 'success', ['consultation_id' => $new_cons_id, 'ref_no' => $ref_no]);
        $_SESSION['msg'] = "Official Consultation Request Form ({$ref_no}) submitted to your adviser! You can print/download your form copy or respond to adviser follow-up questions anytime.";
    } else {
        $_SESSION['msg'] = "Error submitting consultation form: " . $conn->error;
    }

    header("Location: student_dashboard.php?tab=" . urlencode($return_tab));
    exit();
}

// 2. Student responds to Adviser's follow-up questions & comments before acceptance
if (isset($_POST['student_respond_consultation'])) {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
        header("Location: dashboard.php");
        exit();
    }

    $student_id = (int)$_SESSION['user_id'];
    $student_name = $_SESSION['name'] ?? 'Student';
    $consultation_id = (int)($_POST['consultation_id'] ?? 0);
    $student_response = trim($_POST['student_response'] ?? '');
    $updated_idea = trim($_POST['updated_research_idea'] ?? '');
    $return_tab = sanitize($_POST['return_tab'] ?? 'consultations');

    if ($consultation_id <= 0 || empty($student_response)) {
        $_SESSION['msg'] = "Error: Please enter your response to the adviser's follow-up questions.";
        header("Location: student_dashboard.php?tab=" . urlencode($return_tab));
        exit();
    }

    $cons = $conn->query("SELECT * FROM adviser_consultations WHERE id = $consultation_id")->fetch_assoc();
    if ($cons) {
        $esc_resp = sanitize($student_response);
        $idea_sql = !empty($updated_idea) ? ", research_idea_summary = '" . sanitize($updated_idea) . "'" : "";
        $new_status = in_array($cons['status'], ['pending_review', 'follow_up_required', 'student_responded']) ? 'student_responded' : $cons['status'];

        $conn->query("UPDATE adviser_consultations 
                      SET student_response = '$esc_resp', 
                          student_responded_at = NOW(), 
                          status = '$new_status',
                          adviser_seen = 0,
                          student_seen = 1
                          $idea_sql
                      WHERE id = $consultation_id");

        // Sync response to any pending match_request with this adviser
        $adv_id_sync = (int)$cons['adviser_id'];
        $grp_id_sync = (int)($cons['group_id'] ?? 0);
        $mr_w = ($grp_id_sync > 0) ? "(student_id = $student_id OR group_id = $grp_id_sync)" : "student_id = $student_id";
        $conn->query("UPDATE match_request 
                      SET student_followup_response = '$esc_resp',
                          student_responded_at = NOW(),
                          consultation_status = 'student_replied'
                      WHERE $mr_w AND adviser_id = $adv_id_sync AND status = 'pending'");

        // Log in consultation_messages
        $stmt = $conn->prepare("INSERT INTO consultation_messages (consultation_id, sender_id, sender_name, sender_role, message_type, message_text) VALUES (?, ?, ?, 'student', 'student_response', ?)");
        $stmt->bind_param("iiss", $consultation_id, $student_id, $student_name, $student_response);
        $stmt->execute();

        // Notify Adviser
        create_notification(
            $conn,
            (int)$cons['adviser_id'],
            "💬 Student Responded to Consultation Follow-Up ({$cons['reference_no']})",
            "{$student_name} answered your follow-up questions on Consultation Form {$cons['reference_no']}. Please review their response and provide feedback or accept the request.",
            "consultation",
            "faculty_dashboard.php?tab=consultations&cons_id={$consultation_id}"
        );

        log_audit('CONSULTATION_STUDENT_RESPONSE', "Student '{$student_name}' responded to adviser follow-up questions on Consultation #{$consultation_id} ({$cons['reference_no']}).", 'Adviser Consultation', 'success');
        $_SESSION['msg'] = "Your response has been sent to your research adviser for review and approval!";
    }

    header("Location: student_dashboard.php?tab=" . urlencode($return_tab));
    exit();
}

// 3. Student cancels a pending consultation request
if (isset($_POST['cancel_consultation_request'])) {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
        header("Location: dashboard.php");
        exit();
    }
    $student_id = (int)$_SESSION['user_id'];
    $consultation_id = (int)($_POST['consultation_id'] ?? 0);
    $conn->query("UPDATE adviser_consultations SET status = 'cancelled' WHERE id = $consultation_id AND student_id = $student_id AND status IN ('pending_review', 'follow_up_required', 'student_responded')");
    $_SESSION['msg'] = "Consultation request cancelled.";
    header("Location: student_dashboard.php?tab=consultations");
    exit();
}

// 4. Adviser evaluates readiness, asks follow-up questions, reviews responses, and accepts/declines consultation request
if (isset($_POST['adviser_evaluate_consultation'])) {
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['adviser', 'admin'])) {
        header("Location: dashboard.php");
        exit();
    }

    $adviser_id = (int)$_SESSION['user_id'];
    $adviser_name = $_SESSION['name'] ?? 'Faculty Adviser';
    $consultation_id = (int)($_POST['consultation_id'] ?? 0);
    $eval_action = sanitize($_POST['eval_action'] ?? 'ask_followup'); // 'ask_followup', 'review_response', 'accept_consultation', 'decline_consultation'
    $readiness_level = sanitize($_POST['readiness_level'] ?? 'Moderately Ready');
    $readiness_score = isset($_POST['readiness_score']) && $_POST['readiness_score'] !== '' ? min(100, max(0, (int)$_POST['readiness_score'])) : 80;
    $follow_up_questions = trim($_POST['follow_up_questions'] ?? '');
    $adviser_preliminary_comments = trim($_POST['adviser_preliminary_comments'] ?? '');
    $adviser_review_feedback = trim($_POST['adviser_review_feedback'] ?? '');
    $confirmed_schedule = sanitize($_POST['confirmed_schedule'] ?? '');
    $venue_or_link = sanitize($_POST['venue_or_link'] ?? '');
    $return_tab = sanitize($_POST['return_tab'] ?? 'consultations');

    $cons = $conn->query("SELECT * FROM adviser_consultations WHERE id = $consultation_id")->fetch_assoc();
    if (!$cons) {
        $_SESSION['msg'] = "Error: Consultation record not found.";
        header("Location: faculty_dashboard.php?tab=" . urlencode($return_tab));
        exit();
    }

    $stu_id = (int)$cons['student_id'];
    $grp_id = (int)($cons['group_id'] ?? 0);
    $esc_fq = sanitize($follow_up_questions);
    $esc_pc = sanitize($adviser_preliminary_comments);
    $esc_rf = sanitize($adviser_review_feedback);
    $also_accept_advisee = !empty($_POST['also_accept_advisee_request']) ? 1 : 0;

    // Enforce Pre-Acceptance Consultation Q&A rule before accepting a consultation request
    $has_asked_before = !empty(trim($cons['follow_up_questions'] ?? '')) || !empty($follow_up_questions);
    $has_student_replied = !empty(trim($cons['student_response'] ?? ''));
    if ($eval_action === 'accept_consultation' && (!$has_asked_before || !$has_student_replied)) {
        if (!$has_asked_before) {
            $_SESSION['msg'] = "Action Required Before Accepting: You must first ask follow-up questions to the student and evaluate their readiness before accepting the consultation request.";
            header("Location: faculty_dashboard.php?tab=" . urlencode($return_tab) . "&cons_id=" . $consultation_id);
            exit();
        } else {
            // Questions were just entered or already sent, but student hasn't answered yet -> send as follow-up required first!
            $eval_action = 'ask_followup';
        }
    }

    $status_sql = "";
    $accepted_sql = "";
    $msg_type = 'adviser_comment';
    $msg_log_text = '';

    if ($eval_action === 'ask_followup') {
        $status_sql = ", status = 'follow_up_required'";
        $msg_type = 'follow_up_question';
        $msg_log_text = "Readiness Evaluation: {$readiness_level} ({$readiness_score}%)\n";
        if (!empty($follow_up_questions)) {
            $msg_log_text .= "Follow-Up Questions: {$follow_up_questions}\n";
        }
        if (!empty($adviser_preliminary_comments)) {
            $msg_log_text .= "Adviser Comments: {$adviser_preliminary_comments}";
        }
    } elseif ($eval_action === 'accept_consultation') {
        $status_sql = ", status = 'accepted'";
        $accepted_sql = ", accepted_at = NOW()";
        $msg_type = 'readiness_eval';
        $msg_log_text = "Consultation Request Accepted & Approved! Readiness: {$readiness_level} ({$readiness_score}%).";
        if (!empty($adviser_review_feedback)) {
            $msg_log_text .= "\nFeedback on Student Response: {$adviser_review_feedback}";
        } elseif (!empty($adviser_preliminary_comments)) {
            $msg_log_text .= "\nAdviser Comments: {$adviser_preliminary_comments}";
        }
        if (!empty($confirmed_schedule)) {
            $msg_log_text .= "\nConfirmed Schedule: {$confirmed_schedule}";
        }
    } elseif ($eval_action === 'decline_consultation') {
        $status_sql = ", status = 'declined'";
        $msg_type = 'readiness_eval';
        $msg_log_text = "Consultation Request Declined / Revision Needed (Readiness: {$readiness_level}). " . ($adviser_review_feedback ?: $adviser_preliminary_comments);
    } else {
        // 'review_response' / save feedback before accepting
        $msg_type = 'adviser_comment';
        $msg_log_text = "Adviser Review Feedback (Readiness: {$readiness_level} - {$readiness_score}%): " . ($adviser_review_feedback ?: $adviser_preliminary_comments);
    }

    $sched_sql = !empty($confirmed_schedule) ? ", confirmed_schedule = '$confirmed_schedule'" : "";
    $venue_sql = !empty($venue_or_link) ? ", venue_or_link = '$venue_or_link'" : "";
    $fq_sql = !empty($follow_up_questions) ? ", follow_up_questions = '$esc_fq'" : "";
    $pc_sql = !empty($adviser_preliminary_comments) ? ", adviser_preliminary_comments = '$esc_pc'" : "";
    $rf_sql = !empty($adviser_review_feedback) ? ", adviser_review_feedback = '$esc_rf'" : "";

    $upd_sql = "UPDATE adviser_consultations 
                SET readiness_level = '$readiness_level',
                    readiness_score = $readiness_score,
                    adviser_seen = 1,
                    student_seen = 0
                    $fq_sql
                    $pc_sql
                    $rf_sql
                    $sched_sql
                    $venue_sql
                    $status_sql
                    $accepted_sql
                WHERE id = $consultation_id";

    if ($conn->query($upd_sql)) {
        if (!empty(trim($msg_log_text))) {
            $stmt = $conn->prepare("INSERT INTO consultation_messages (consultation_id, sender_id, sender_name, sender_role, message_type, message_text) VALUES (?, ?, ?, 'adviser', ?, ?)");
            $stmt->bind_param("iisss", $consultation_id, $adviser_id, $adviser_name, $msg_type, $msg_log_text);
            $stmt->execute();
        }

        // Synchronize with pending match_request for this student/group & adviser
        $mr_w = ($grp_id > 0) ? "(student_id = $stu_id OR group_id = $grp_id)" : "student_id = $stu_id";
        $mr_cstat = ($eval_action === 'accept_consultation' || !empty($adviser_review_feedback)) ? 'evaluated' : 'follow_up_asked';
        $mr_accept_sql = ($eval_action === 'accept_consultation' && $also_accept_advisee) ? ", status = 'accepted', rejection_reason = NULL" : "";
        $conn->query("UPDATE match_request 
                      SET readiness_evaluation = '$readiness_level ($readiness_score%)',
                          follow_up_questions = CASE WHEN '$esc_fq' != '' THEN '$esc_fq' ELSE follow_up_questions END,
                          adviser_comments = CASE WHEN '$esc_pc' != '' THEN '$esc_pc' ELSE adviser_comments END,
                          adviser_feedback_on_response = CASE WHEN '$esc_rf' != '' THEN '$esc_rf' ELSE adviser_feedback_on_response END,
                          consultation_status = '$mr_cstat'
                          $mr_accept_sql
                      WHERE $mr_w AND adviser_id = $adviser_id AND status = 'pending'");

        // Notify student
        $notif_title = ($eval_action === 'accept_consultation')
            ? "✅ Consultation Request Accepted ({$cons['reference_no']})"
            : (($eval_action === 'ask_followup') ? "❓ Adviser Follow-Up Questions on Consultation ({$cons['reference_no']})" : "📝 Adviser Feedback on Consultation ({$cons['reference_no']})");
        create_notification(
            $conn,
            $stu_id,
            $notif_title,
            "Prof. {$adviser_name} updated your Consultation Request ({$cons['reference_no']}): " . mb_strimwidth($msg_log_text, 0, 160, '...'),
            "consultation",
            "student_dashboard.php?tab=consultations"
        );

        if ($grp_id > 0) {
            $conn->query("INSERT INTO capstone_group_updates (group_id, user_id, author_name, author_role, update_type, title, message, created_at)
                          VALUES ($grp_id, $adviser_id, '" . $conn->real_escape_string($adviser_name) . "', 'Adviser', 'adviser_feedback', '" . $conn->real_escape_string($notif_title) . "', '" . $conn->real_escape_string($msg_log_text) . "', NOW())");
        }

        log_audit('CONSULTATION_EVALUATED', "Adviser '{$adviser_name}' performed '{$eval_action}' on Consultation #{$consultation_id} ({$cons['reference_no']}).", 'Adviser Consultation', 'success');
        $_SESSION['msg'] = ($eval_action === 'accept_consultation')
            ? "Consultation request accepted and student notified!"
            : (($eval_action === 'ask_followup') ? "Follow-up questions and readiness evaluation sent to the student!" : "Consultation feedback and readiness evaluation saved!");
    }

    header("Location: faculty_dashboard.php?tab=" . urlencode($return_tab) . "&cons_id=" . $consultation_id);
    exit();
}

// 5. Adviser records the Outcome of a Consultation to monitor student progress
if (isset($_POST['adviser_record_consultation_outcome'])) {
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['adviser', 'admin'])) {
        header("Location: dashboard.php");
        exit();
    }

    $adviser_id = (int)$_SESSION['user_id'];
    $adviser_name = $_SESSION['name'] ?? 'Faculty Adviser';
    $consultation_id = (int)($_POST['consultation_id'] ?? 0);
    $outcome_verdict = sanitize($_POST['outcome_verdict'] ?? 'Proceed to Next Milestone');
    $outcome_summary = trim($_POST['outcome_summary'] ?? '');
    $action_items = trim($_POST['action_items'] ?? '');
    $progress_rating = isset($_POST['progress_rating']) ? min(100, max(0, (int)$_POST['progress_rating'])) : 85;
    $mark_process_complete = !empty($_POST['mark_process_complete']) ? 1 : 0;
    $return_tab = sanitize($_POST['return_tab'] ?? 'consultations');

    $cons = $conn->query("SELECT * FROM adviser_consultations WHERE id = $consultation_id")->fetch_assoc();
    if (!$cons) {
        $_SESSION['msg'] = "Error: Consultation record not found.";
        header("Location: faculty_dashboard.php?tab=" . urlencode($return_tab));
        exit();
    }

    $stu_id = (int)$cons['student_id'];
    $grp_id = (int)($cons['group_id'] ?? 0);
    $esc_sum = sanitize($outcome_summary);
    $esc_act = sanitize($action_items);

    $upd_sql = "UPDATE adviser_consultations 
                SET status = 'completed',
                    outcome_verdict = '$outcome_verdict',
                    outcome_summary = '$esc_sum',
                    action_items = '$esc_act',
                    progress_rating = $progress_rating,
                    completed_at = NOW(),
                    adviser_signed_off = 1
                WHERE id = $consultation_id";

    if ($conn->query($upd_sql)) {
        $log_text = "Consultation Completed • Outcome Verdict: {$outcome_verdict} (Progress Rating: {$progress_rating}%)\nDiscussion & Outcome: {$outcome_summary}" . (!empty($action_items) ? "\nAction Items: {$action_items}" : "");
        $stmt = $conn->prepare("INSERT INTO consultation_messages (consultation_id, sender_id, sender_name, sender_role, message_type, message_text) VALUES (?, ?, ?, 'adviser', 'outcome_record', ?)");
        $stmt->bind_param("iiss", $consultation_id, $adviser_id, $adviser_name, $log_text);
        $stmt->execute();

        // Sync IT-199 Checklist Item #1 (Student consultation form)
        sync_consultation_checklist_item($conn, $stu_id, $grp_id);

        // If adviser also checked "Mark Required Consultation Process as Completed" or verdict is "Endorsed for Oral Defense"
        if ($mark_process_complete || $outcome_verdict === 'Endorsed for Oral Defense') {
            $comp_note = "Confirmed via Consultation {$cons['reference_no']} ({$outcome_verdict}).";
            $esc_cn = $conn->real_escape_string($comp_note);
            $conn->query("UPDATE student_profile SET consultation_process_status = 'completed', consultation_completed_at = NOW(), consultation_completed_by = $adviser_id, consultation_completion_notes = '$esc_cn' WHERE user_id = $stu_id");
            if ($grp_id > 0) {
                $conn->query("UPDATE capstone_group SET consultation_process_status = 'completed', consultation_completed_at = NOW(), consultation_completed_by = $adviser_id, consultation_completion_notes = '$esc_cn' WHERE id = $grp_id");
                $conn->query("UPDATE student_profile SET consultation_process_status = 'completed', consultation_completed_at = NOW(), consultation_completed_by = $adviser_id, consultation_completion_notes = '$esc_cn' WHERE group_id = $grp_id");
            }
        } else {
            $conn->query("UPDATE student_profile SET consultation_process_status = 'in_progress' WHERE user_id = $stu_id AND consultation_process_status = 'not_started'");
        }

        // Notify student
        create_notification(
            $conn,
            $stu_id,
            "📊 Consultation Outcome Recorded ({$cons['reference_no']})",
            "Prof. {$adviser_name} recorded the outcome of your consultation ({$outcome_verdict} • Progress: {$progress_rating}%). Your official Consultation Form is ready for documentation.",
            "consultation",
            "student_dashboard.php?tab=consultations"
        );

        if ($grp_id > 0) {
            $conn->query("INSERT INTO capstone_group_updates (group_id, user_id, author_name, author_role, update_type, title, message, created_at)
                          VALUES ($grp_id, $adviser_id, '" . $conn->real_escape_string($adviser_name) . "', 'Adviser', 'milestone', 'Consultation Outcome Recorded ({$cons['reference_no']})', '" . $conn->real_escape_string($log_text) . "', NOW())");
        }

        log_audit('CONSULTATION_OUTCOME_RECORDED', "Adviser '{$adviser_name}' recorded outcome '{$outcome_verdict}' ({$progress_rating}%) for Consultation #{$consultation_id}.", 'Adviser Consultation', 'success');
        $_SESSION['msg'] = "Consultation outcome recorded and signed off! Student's progress tracker and official consultation form have been updated.";
    }

    header("Location: faculty_dashboard.php?tab=" . urlencode($return_tab) . "&cons_id=" . $consultation_id);
    exit();
}

// 6. Adviser confirms completion of required Consultation Process & approves Student for Oral Defense
if (isset($_POST['adviser_confirm_consultation_and_defense'])) {
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['adviser', 'admin'])) {
        header("Location: dashboard.php");
        exit();
    }

    $adviser_id = (int)$_SESSION['user_id'];
    $adviser_name = $_SESSION['name'] ?? 'Faculty Adviser';
    $student_id = (int)($_POST['student_id'] ?? 0);
    $action_mode = sanitize($_POST['action_mode'] ?? 'confirm_consultation'); // 'confirm_consultation', 'approve_oral_defense', 'revoke_defense'
    $completion_notes = sanitize($_POST['consultation_completion_notes'] ?? '');
    $defense_stage = sanitize($_POST['oral_defense_stage_approved'] ?? 'Proposal Oral Defense (IT 198)');
    $endorsement_remarks = sanitize($_POST['oral_defense_endorsement_remarks'] ?? '');
    $confirm_checkbox = !empty($_POST['confirm_consultation_checkbox']) ? 1 : 0;
    $return_tab = sanitize($_POST['return_tab'] ?? 'consultations');

    if ($student_id <= 0) {
        $_SESSION['msg'] = "Error: Invalid student selected.";
        header("Location: faculty_dashboard.php?tab=" . urlencode($return_tab));
        exit();
    }

    $sp = $conn->query("SELECT group_id, consultation_process_status FROM student_profile WHERE user_id = $student_id")->fetch_assoc() ?: [];
    $grp_id = !empty($sp['group_id']) ? (int)$sp['group_id'] : 0;

    if ($action_mode === 'confirm_consultation') {
        $conn->query("UPDATE student_profile 
                      SET consultation_process_status = 'completed',
                          consultation_completed_at = NOW(),
                          consultation_completed_by = $adviser_id,
                          consultation_completion_notes = '$completion_notes'
                      WHERE user_id = $student_id");
        if ($grp_id > 0) {
            $conn->query("UPDATE capstone_group 
                          SET consultation_process_status = 'completed',
                              consultation_completed_at = NOW(),
                              consultation_completed_by = $adviser_id,
                              consultation_completion_notes = '$completion_notes'
                          WHERE id = $grp_id");
            $conn->query("UPDATE student_profile 
                          SET consultation_process_status = 'completed',
                              consultation_completed_at = NOW(),
                              consultation_completed_by = $adviser_id,
                              consultation_completion_notes = '$completion_notes'
                          WHERE group_id = $grp_id");
        }

        sync_consultation_checklist_item($conn, $student_id, $grp_id);

        create_notification(
            $conn,
            $student_id,
            "✅ Required Consultation Process Completed",
            "Prof. {$adviser_name} has officially confirmed that you have completed the required Adviser Consultation Process.",
            "consultation",
            "student_dashboard.php?tab=consultations"
        );
        log_audit('CONSULTATION_PROCESS_COMPLETED', "Adviser '{$adviser_name}' confirmed completion of required consultation process for Student #{$student_id}.", 'Adviser Consultation', 'success');
        $_SESSION['msg'] = "Confirmed! The student's required Adviser Consultation Process is now marked as Completed.";
    } elseif ($action_mode === 'approve_oral_defense') {
        // Strict rule: Adviser MUST confirm that the student has completed the necessary consultation process before approving for oral defense
        $is_already_completed = (($sp['consultation_process_status'] ?? '') === 'completed');
        if (!$is_already_completed && !$confirm_checkbox) {
            $_SESSION['msg'] = "Error: You must confirm that the student has completed the necessary consultation process before approving them for an oral defense.";
            header("Location: faculty_dashboard.php?tab=" . urlencode($return_tab) . "&student_id=" . $student_id);
            exit();
        }

        $note_val = !empty($completion_notes) ? $completion_notes : "Verified completed consultation process prior to {$defense_stage} approval.";
        $esc_note = $conn->real_escape_string($note_val);

        $conn->query("UPDATE student_profile 
                      SET consultation_process_status = 'completed',
                          consultation_completed_at = COALESCE(consultation_completed_at, NOW()),
                          consultation_completed_by = COALESCE(consultation_completed_by, $adviser_id),
                          consultation_completion_notes = '$esc_note',
                          oral_defense_approved = 1,
                          oral_defense_approved_at = NOW(),
                          oral_defense_approved_by = $adviser_id,
                          oral_defense_stage_approved = '$defense_stage',
                          oral_defense_endorsement_remarks = '$endorsement_remarks'
                      WHERE user_id = $student_id");

        if ($grp_id > 0) {
            $conn->query("UPDATE capstone_group 
                          SET consultation_process_status = 'completed',
                              consultation_completed_at = COALESCE(consultation_completed_at, NOW()),
                              consultation_completed_by = COALESCE(consultation_completed_by, $adviser_id),
                              consultation_completion_notes = '$esc_note',
                              oral_defense_approved = 1,
                              oral_defense_approved_at = NOW(),
                              oral_defense_approved_by = $adviser_id,
                              oral_defense_stage_approved = '$defense_stage',
                              oral_defense_endorsement_remarks = '$endorsement_remarks'
                          WHERE id = $grp_id");
            $conn->query("UPDATE student_profile 
                          SET consultation_process_status = 'completed',
                              consultation_completed_at = COALESCE(consultation_completed_at, NOW()),
                              consultation_completed_by = COALESCE(consultation_completed_by, $adviser_id),
                              consultation_completion_notes = '$esc_note',
                              oral_defense_approved = 1,
                              oral_defense_approved_at = NOW(),
                              oral_defense_approved_by = $adviser_id,
                              oral_defense_stage_approved = '$defense_stage',
                              oral_defense_endorsement_remarks = '$endorsement_remarks'
                          WHERE group_id = $grp_id");

            $feed_title = "Approved & Endorsed for Oral Defense ({$defense_stage})";
            $feed_msg = "Prof. {$adviser_name} confirmed completion of the required consultation process and officially endorsed your group for {$defense_stage}." . (!empty($endorsement_remarks) ? " Remarks: {$endorsement_remarks}" : "");
            $conn->query("INSERT INTO capstone_group_updates (group_id, user_id, author_name, author_role, update_type, title, message, created_at)
                          VALUES ($grp_id, $adviser_id, '" . $conn->real_escape_string($adviser_name) . "', 'Adviser', 'milestone', '" . $conn->real_escape_string($feed_title) . "', '" . $conn->real_escape_string($feed_msg) . "', NOW())");
        }

        sync_consultation_checklist_item($conn, $student_id, $grp_id);

        create_notification(
            $conn,
            $student_id,
            "🎓 Approved for Oral Defense ({$defense_stage})!",
            "Prof. {$adviser_name} confirmed your completed consultation process and officially approved you for {$defense_stage}!",
            "defense_schedule",
            "student_dashboard.php?tab=consultations"
        );
        log_audit('ORAL_DEFENSE_APPROVED_BY_ADVISER', "Adviser '{$adviser_name}' confirmed consultation completion and approved Student #{$student_id} for {$defense_stage}.", 'Adviser Consultation', 'success');
        $_SESSION['msg'] = "Student's consultation process confirmed and officially approved for {$defense_stage}!";
    } elseif ($action_mode === 'revoke_defense') {
        $conn->query("UPDATE student_profile SET oral_defense_approved = 0 WHERE user_id = $student_id");
        if ($grp_id > 0) {
            $conn->query("UPDATE capstone_group SET oral_defense_approved = 0 WHERE id = $grp_id");
            $conn->query("UPDATE student_profile SET oral_defense_approved = 0 WHERE group_id = $grp_id");
        }
        $_SESSION['msg'] = "Oral defense endorsement status reset to pending.";
    }

    header("Location: faculty_dashboard.php?tab=" . urlencode($return_tab) . "&student_id=" . $student_id);
    exit();
}

// 7. Adviser asks follow-up questions / evaluates readiness on an Advisee Match Request BEFORE accepting
if (isset($_POST['adviser_followup_match_request'])) {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'adviser') {
        header("Location: dashboard.php");
        exit();
    }

    $adviser_id = (int)$_SESSION['user_id'];
    $adviser_name = $_SESSION['name'] ?? 'Faculty Adviser';
    $req_id = (int)($_POST['req_id'] ?? 0);
    $readiness_evaluation = sanitize($_POST['readiness_evaluation'] ?? 'Moderately Ready - Clarification Needed');
    $follow_up_questions = sanitize($_POST['follow_up_questions'] ?? '');
    $adviser_comments = sanitize($_POST['adviser_comments'] ?? '');
    $adviser_feedback_on_response = sanitize($_POST['adviser_feedback_on_response'] ?? '');
    $accept_now = !empty($_POST['accept_after_review']) ? 1 : 0;
    $approve_title_now = !empty($_POST['approve_title_after_review']) ? 1 : 0;

    $req_row = $conn->query("SELECT * FROM match_request WHERE id = $req_id AND adviser_id = $adviser_id")->fetch_assoc();
    if ($req_row) {
        $stu_id = (int)$req_row['student_id'];
        $grp_id = (int)($req_row['group_id'] ?? 0);
        if ($grp_id <= 0) {
            $sp_g = $conn->query("SELECT group_id FROM student_profile WHERE user_id = $stu_id")->fetch_assoc();
            if (!empty($sp_g['group_id'])) $grp_id = (int)$sp_g['group_id'];
        }

        // Ensure linked consultation form exists and sync with it
        $linked_cons = ensure_linked_consultation_for_request($conn, $stu_id, $adviser_id, $grp_id);
        $has_student_replied = !empty(trim($req_row['student_followup_response'] ?? '')) || !empty(trim($linked_cons['student_response'] ?? ''));

        // Enforce that student MUST have answered the adviser's consultation questions before accepting
        if ($accept_now && !$has_student_replied) {
            $accept_now = 0;
            $blocked_accept_notice = true;
        } else {
            $blocked_accept_notice = false;
        }

        $c_stat = ($accept_now || !empty($adviser_feedback_on_response)) ? 'evaluated' : ($has_student_replied ? 'student_replied' : 'follow_up_asked');
        $status_upd = $accept_now ? ", status = 'accepted', rejection_reason = NULL" : "";

        $conn->query("UPDATE match_request 
                      SET readiness_evaluation = '$readiness_evaluation',
                          follow_up_questions = CASE WHEN '$follow_up_questions' != '' THEN '$follow_up_questions' ELSE follow_up_questions END,
                          adviser_comments = CASE WHEN '$adviser_comments' != '' THEN '$adviser_comments' ELSE adviser_comments END,
                          adviser_feedback_on_response = CASE WHEN '$adviser_feedback_on_response' != '' THEN '$adviser_feedback_on_response' ELSE adviser_feedback_on_response END,
                          consultation_status = '$c_stat'
                          $status_upd
                      WHERE id = $req_id AND adviser_id = $adviser_id");

        // Sync with adviser_consultations & consultation_messages
        if ($linked_cons) {
            $l_cid = (int)$linked_cons['id'];
            $cons_st = $accept_now ? 'accepted' : ($has_student_replied ? 'student_responded' : 'follow_up_required');
            $acc_at_sql = $accept_now ? ", accepted_at = COALESCE(accepted_at, NOW())" : "";
            $conn->query("UPDATE adviser_consultations 
                          SET follow_up_questions = CASE WHEN '$follow_up_questions' != '' THEN '$follow_up_questions' ELSE follow_up_questions END,
                              adviser_preliminary_comments = CASE WHEN '$adviser_comments' != '' THEN '$adviser_comments' ELSE adviser_preliminary_comments END,
                              adviser_review_feedback = CASE WHEN '$adviser_feedback_on_response' != '' THEN '$adviser_feedback_on_response' ELSE adviser_review_feedback END,
                              status = CASE WHEN status = 'completed' THEN 'completed' ELSE '$cons_st' END,
                              adviser_seen = 1,
                              student_seen = 0
                              $acc_at_sql
                          WHERE id = $l_cid");

            $log_msg = "Readiness Evaluation: {$readiness_evaluation}";
            if (!empty($_POST['follow_up_questions'])) $log_msg .= "\nFollow-Up Questions: " . trim($_POST['follow_up_questions']);
            if (!empty($_POST['adviser_comments'])) $log_msg .= "\nAdviser Comments: " . trim($_POST['adviser_comments']);
            if (!empty($_POST['adviser_feedback_on_response'])) $log_msg .= "\nReview Feedback on Student Response: " . trim($_POST['adviser_feedback_on_response']);
            $m_type = $accept_now ? 'readiness_eval' : (!empty($_POST['follow_up_questions']) ? 'follow_up_question' : 'adviser_comment');
            $stmt_m = $conn->prepare("INSERT INTO consultation_messages (consultation_id, sender_id, sender_name, sender_role, message_type, message_text) VALUES (?, ?, ?, 'adviser', ?, ?)");
            $stmt_m->bind_param("iisss", $l_cid, $adviser_id, $adviser_name, $m_type, $log_msg);
            $stmt_m->execute();
        }

        if ($accept_now && $approve_title_now) {
            $conn->query("UPDATE student_profile SET title_status='approved', title_approved_at=NOW(), title_approved_by=$adviser_id WHERE user_id=$stu_id");
            if ($grp_id > 0) {
                $conn->query("UPDATE capstone_group SET title_status='approved', title_approved_at=NOW(), title_approved_by=$adviser_id WHERE id=$grp_id");
                $conn->query("UPDATE student_profile SET title_status='approved', title_approved_at=NOW(), title_approved_by=$adviser_id WHERE group_id=$grp_id");
            }
        }

        if ($accept_now) {
            create_notification(
                $conn,
                $stu_id,
                "✅ Adviser Request Accepted after Consultation Review",
                "Prof. {$adviser_name} reviewed your consultation responses, provided feedback, and officially accepted your advisee request!",
                "consultation",
                "student_dashboard.php?tab=requests"
            );
            $_SESSION['msg'] = "Student consultation responses reviewed, feedback recorded, and advisee request officially accepted!";
        } else {
            create_notification(
                $conn,
                $stu_id,
                "❓ Adviser Consultation Questions & Readiness Evaluation",
                "Before accepting your request, Prof. {$adviser_name} evaluated your readiness ({$readiness_evaluation}) and asked consultation follow-up questions. Please answer them in the Requests or Adviser Consultations tab.",
                "consultation",
                "student_dashboard.php?tab=requests"
            );
            $_SESSION['msg'] = $blocked_accept_notice
                ? "Consultation questions sent to student! Note: The student must answer your consultation questions first before the request can be accepted."
                : "Consultation follow-up questions, comments, and readiness evaluation sent to the student!";
        }
    }

    header("Location: faculty_dashboard.php?tab=requests");
    exit();
}

// 8. Student responds to Adviser's pre-acceptance follow-up questions on a Match Request
if (isset($_POST['student_reply_match_request'])) {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
        header("Location: dashboard.php");
        exit();
    }

    $student_id = (int)$_SESSION['user_id'];
    $student_name = $_SESSION['name'] ?? 'Student';
    $req_id = (int)($_POST['req_id'] ?? 0);
    $student_response = sanitize($_POST['student_followup_response'] ?? '');
    $research_idea_pitch = sanitize($_POST['research_idea_pitch'] ?? '');

    $req_row = $conn->query("SELECT * FROM match_request WHERE id = $req_id")->fetch_assoc();
    if ($req_row && !empty($student_response)) {
        $conn->query("UPDATE match_request 
                      SET student_followup_response = '$student_response',
                          research_idea_pitch = CASE WHEN '$research_idea_pitch' != '' THEN '$research_idea_pitch' ELSE research_idea_pitch END,
                          student_responded_at = NOW(),
                          consultation_status = 'student_replied'
                      WHERE id = $req_id");

        // Also sync student's answer to the linked adviser_consultations record
        $stu_id_sync = (int)$req_row['student_id'];
        $adv_id_sync = (int)$req_row['adviser_id'];
        $grp_id_sync = (int)($req_row['group_id'] ?? 0);
        $linked_cons = ensure_linked_consultation_for_request($conn, $stu_id_sync, $adv_id_sync, $grp_id_sync);
        if ($linked_cons) {
            $l_cid = (int)$linked_cons['id'];
            $conn->query("UPDATE adviser_consultations 
                          SET student_response = '$student_response',
                              student_responded_at = NOW(),
                              status = CASE WHEN status IN ('pending_review','follow_up_required','student_responded') THEN 'student_responded' ELSE status END,
                              adviser_seen = 0,
                              student_seen = 1
                          WHERE id = $l_cid");
            $raw_resp = trim($_POST['student_followup_response'] ?? '');
            $stmt_m = $conn->prepare("INSERT INTO consultation_messages (consultation_id, sender_id, sender_name, sender_role, message_type, message_text) VALUES (?, ?, ?, 'student', 'student_response', ?)");
            $stmt_m->bind_param("iiss", $l_cid, $student_id, $student_name, $raw_resp);
            $stmt_m->execute();
        }

        create_notification(
            $conn,
            (int)$req_row['adviser_id'],
            "💬 Student Replied to Pre-Acceptance Questions",
            "{$student_name} responded to your follow-up questions. You may now review their response, provide feedback, and accept the request.",
            "consultation",
            "faculty_dashboard.php?tab=requests"
        );
        $_SESSION['msg'] = "Your response has been submitted to the adviser for review and approval!";
    }

    header("Location: student_dashboard.php?tab=requests");
    exit();
}
?>
