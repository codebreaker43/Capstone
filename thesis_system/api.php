<?php
function get_cosine_similarity($str1, $str2) {
    // Basic NLP Preprocessing
    $str1 = strtolower(preg_replace('/[^a-zA-Z0-9\s]/', '', $str1));
    $str2 = strtolower(preg_replace('/[^a-zA-Z0-9\s]/', '', $str2));
    
    $tokens1 = explode(" ", $str1);
    $tokens2 = explode(" ", $str2);
    
    $stopwords = array("the", "and", "a", "an", "in", "to", "of", "is", "for", "on", "with", "as", "by", "this", "that");
    $tokens1 = array_diff($tokens1, $stopwords);
    $tokens2 = array_diff($tokens2, $stopwords);
    
    $freq1 = array_count_values($tokens1);
    $freq2 = array_count_values($tokens2);
    
    $unique_words = array_unique(array_merge(array_keys($freq1), array_keys($freq2)));
    
    $dot_product = 0;
    $mag1 = 0;
    $mag2 = 0;
    
    foreach ($unique_words as $word) {
        if(empty($word)) continue;
        $v1 = $freq1[$word] ?? 0;
        $v2 = $freq2[$word] ?? 0;
        
        $dot_product += $v1 * $v2;
        $mag1 += pow($v1, 2);
        $mag2 += pow($v2, 2);
    }
    
    $mag1 = sqrt($mag1);
    $mag2 = sqrt($mag2);
    
    if ($mag1 * $mag2 == 0) return 0;
    return $dot_product / ($mag1 * $mag2);
}

// API Endpoint
if(isset($_GET['action']) && $_GET['action'] == 'get_matches') {
    require_once 'db.php';
    global $conn;
    if(!isset($_SESSION['user_id'])) die(json_encode(["error" => "Unauthorized"]));
    
    $uid = (int)$_SESSION['user_id'];
    $profile_res = $conn->query("SELECT skills, thesis_topic, department, abstract_file, abstract_name, abstract_text, group_id FROM student_profile WHERE user_id = $uid");
    $profile = $profile_res ? $profile_res->fetch_assoc() : ['skills'=>'', 'thesis_topic'=>'', 'department'=>'', 'abstract_file'=>'', 'abstract_name'=>'', 'abstract_text'=>'', 'group_id'=>0];

    // Shared Group Discovery
    $group_id = !empty($profile['group_id']) ? (int)$profile['group_id'] : 0;
    if (!$group_id) {
        $cgm = $conn->query("SELECT group_id FROM capstone_group_member WHERE student_id = $uid LIMIT 1")->fetch_assoc();
        if ($cgm && !empty($cgm['group_id'])) {
            $group_id = (int)$cgm['group_id'];
        }
    }

    $group_student_ids = [$uid];
    if ($group_id > 0) {
        $g_mem_q = $conn->query("SELECT student_id FROM capstone_group_member WHERE group_id = $group_id AND student_id IS NOT NULL");
        if ($g_mem_q) {
            while ($g_m = $g_mem_q->fetch_assoc()) {
                if (!empty($g_m['student_id'])) {
                    $group_student_ids[] = (int)$g_m['student_id'];
                }
            }
        }
        $cg_q = $conn->query("SELECT * FROM capstone_group WHERE id = $group_id")->fetch_assoc();
        if ($cg_q) {
            if (!empty($cg_q['leader_id'])) $group_student_ids[] = (int)$cg_q['leader_id'];
            if (empty($profile['thesis_topic']) && !empty($cg_q['thesis_topic'])) {
                $profile['thesis_topic'] = $cg_q['thesis_topic'];
            }
            if (empty($profile['abstract_file']) && !empty($cg_q['abstract_file'])) {
                $profile['abstract_file'] = $cg_q['abstract_file'];
                $profile['abstract_name'] = $cg_q['abstract_name'];
                $profile['abstract_text'] = $cg_q['abstract_text'];
            }
        }
    }
    $group_student_ids = array_unique($group_student_ids);
    $group_student_ids_str = implode(',', $group_student_ids);
    
    $student_skills = trim($profile['skills'] ?? '');
    $student_topic = trim($profile['thesis_topic'] ?? '');
    $student_abstract_text = trim($profile['abstract_text'] ?? '');
    $student_abstract_file = trim($profile['abstract_file'] ?? '');
    $student_abstract_name = trim($profile['abstract_name'] ?? '');
    
    // Support dynamic topic / title submission via request
    if (isset($_REQUEST['title']) && trim($_REQUEST['title']) !== '') {
        $student_topic = trim($_REQUEST['title']);
    }
    if (isset($_REQUEST['skills']) && trim($_REQUEST['skills']) !== '') {
        $student_skills = trim($_REQUEST['skills']);
    }
    if (!empty($_REQUEST['save_topic'])) {
        $esc_t = $conn->real_escape_string($student_topic);
        $esc_s = $conn->real_escape_string($student_skills);
        $conn->query("UPDATE student_profile SET thesis_topic='$esc_t', skills='$esc_s' WHERE user_id=$uid");
    }

    $student_dept = trim($profile['department'] ?? '');
    $student_doc = trim($student_skills . " " . $student_topic . " " . $student_abstract_text);

    // Support MCDA Weighting Presets or Custom Criterion Weights
    $mcda_preset = trim($_REQUEST['mcda_preset'] ?? 'balanced');
    $custom_weights = null;
    if ($mcda_preset === 'publications') {
        $custom_weights = ['specialization' => 25, 'publications' => 40, 'supervised_titles' => 15, 'workload_capacity' => 10, 'department_qualification' => 10];
    } elseif ($mcda_preset === 'specialization') {
        $custom_weights = ['specialization' => 50, 'publications' => 20, 'supervised_titles' => 15, 'workload_capacity' => 10, 'department_qualification' => 5];
    } elseif ($mcda_preset === 'availability') {
        $custom_weights = ['specialization' => 30, 'publications' => 15, 'supervised_titles' => 15, 'workload_capacity' => 30, 'department_qualification' => 10];
    } elseif (isset($_REQUEST['w_spec']) || isset($_REQUEST['w_pub']) || isset($_REQUEST['w_sup']) || isset($_REQUEST['w_cap']) || isset($_REQUEST['w_dept'])) {
        $custom_weights = [
            'specialization' => (float)($_REQUEST['w_spec'] ?? 35),
            'publications' => (float)($_REQUEST['w_pub'] ?? 25),
            'supervised_titles' => (float)($_REQUEST['w_sup'] ?? 15),
            'workload_capacity' => (float)($_REQUEST['w_cap'] ?? 15),
            'department_qualification' => (float)($_REQUEST['w_dept'] ?? 10)
        ];
    }
    
    $notice = null;
    $has_student_input = (!empty($student_topic) || !empty($student_skills) || !empty($student_abstract_text));
    $dept_label = !empty($student_dept) ? " for <strong>" . htmlspecialchars($student_dept) . "</strong>" : "";
    
    if(!$has_student_input) {
        $notice = "Enter your proposed thesis title above to run Multi-Criteria Decision Analysis (MCDA) across faculty specializations, publications, track record, and workload:";
    } else {
        $notice = "MCDA multi-criteria evaluation completed for: \"<strong>" . htmlspecialchars($student_topic) . "</strong>\" (Evaluating Specialization, Publications, Supervised Titles, Capacity & Qualification)";
    }
    
    // When matching by specialization, check ALL active advisers across CEIT
    $where = "u.role='adviser' AND u.account_status='active'";
    if (!$has_student_input && !empty($student_dept)) {
        $escaped_dept = $conn->real_escape_string($student_dept);
        $clean_dept = $conn->real_escape_string(preg_replace('/\s*\(.*?\)/', '', $student_dept));
        $where .= " AND (a.department = '$escaped_dept' OR a.department = '$clean_dept' OR a.department LIKE '%$clean_dept%')";
    }
    
    $advisers_res = $conn->query("SELECT u.id, u.name, u.email, a.expertise, a.capacity, a.department, a.academic_degree, a.graduation_year, a.google_scholar_url, a.published_papers, a.previous_advised_titles, a.publications_last_synced FROM user u JOIN adviser_profile a ON u.id = a.user_id WHERE $where ORDER BY u.name");
    
    $matches = [];
    $active_weights = ['specialization' => 35, 'publications' => 25, 'supervised_titles' => 15, 'workload_capacity' => 15, 'department_qualification' => 10];

    while($adv = $advisers_res->fetch_assoc()) {
        $adv_doc = trim($adv['expertise'] ?? '');
        $adv_pubs = trim($adv['published_papers'] ?? '');
        $adv_prev = trim($adv['previous_advised_titles'] ?? '');
        $match_res = calculate_specialization_match($student_topic, $student_skills, $adv_doc, (int)$adv['id'], $adv['department'] ?? '', $student_dept, $student_abstract_text, $adv_pubs, $adv_prev, $custom_weights);
        
        if (!empty($match_res['mcda_weights'])) {
            $active_weights = $match_res['mcda_weights'];
        }

        // Strict exclusion: if student provided a research title/keywords/abstract, only include advisers whose specialization matches!
        if ($has_student_input && !$match_res['matched']) {
            continue;
        }

        $adv['match_score'] = $match_res['score'];
        $adv['mcda_score'] = $match_res['mcda_score'] ?? $match_res['score'];
        $adv['mcda_rank_label'] = $match_res['mcda_rank_label'] ?? 'Suitable MCDA Match';
        $adv['mcda_criteria'] = $match_res['mcda_criteria'] ?? [];
        $adv['mcda_weights'] = $match_res['mcda_weights'] ?? $active_weights;
        $adv['current_load'] = $match_res['current_load'] ?? 0;
        $adv['max_capacity'] = $match_res['max_capacity'] ?? ((int)($adv['capacity'] ?? 5));
        $adv['available_slots'] = $match_res['available_slots'] ?? max(0, $adv['max_capacity'] - $adv['current_load']);
        $adv['matched_terms'] = $match_res['matched_terms'];
        $adv['matched_publications'] = $match_res['matched_publications'] ?? [];
        $adv['matched_advised_titles'] = $match_res['matched_advised_titles'] ?? [];
        $adv['match_reason'] = $match_res['match_reason'] ?? '';
        $matches[] = $adv;
    }
    
    // Sort highest MCDA score first, with student's home department as tie-breaker
    usort($matches, function($a, $b) use ($student_dept) {
        if ($b['match_score'] !== $a['match_score']) {
            return $b['match_score'] <=> $a['match_score'];
        }
        $a_same = (!empty($student_dept) && strcasecmp($a['department'] ?? '', $student_dept) === 0) ? 1 : 0;
        $b_same = (!empty($student_dept) && strcasecmp($b['department'] ?? '', $student_dept) === 0) ? 1 : 0;
        return $b_same <=> $a_same;
    });

    if ($has_student_input && count($matches) === 0) {
        $notice = "No faculty advisers specializing in this domain found for \"<strong>" . htmlspecialchars($student_topic) . "</strong>\". Try refining your research title or updating your concept PDF.";
    }
    
    $req_where = ($group_id > 0) ? "(m.group_id = $group_id OR m.student_id IN ($group_student_ids_str))" : "m.student_id = $uid";
    $active_req = $conn->query("SELECT m.*, u.name as adv_name FROM match_request m JOIN user u ON m.adviser_id = u.id WHERE $req_where AND m.status IN ('pending', 'accepted') LIMIT 1")->fetch_assoc();
    echo json_encode([
        "matches" => $matches,
        "mcda_preset" => $mcda_preset,
        "mcda_weights" => $active_weights,
        "thesis_topic" => $student_topic,
        "student_dept" => $student_dept,
        "abstract_file" => $student_abstract_file,
        "abstract_name" => $student_abstract_name,
        "has_abstract" => (!empty($student_abstract_file) && file_exists($student_abstract_file)),
        "active_request" => $active_req,
        "notice" => $notice
    ]);
    exit();
}

if(isset($_GET['action']) && $_GET['action'] == 'generate_paper') {
    if(!isset($_GET['title']) || empty(trim($_GET['title']))) {
        die(json_encode(["error" => "Title is required."]));
    }
    
    $title = trim($_GET['title']);
    
    // Mock an AI generated draft based on the title
    $draft = "Chapter 1: Introduction\n";
    $draft .= "The rapid evolution of technology in recent years has brought significant attention to the domain of \"$title\". As industries continue to modernize, the need for robust, scalable, and efficient solutions within this space has never been more critical. This study introduces a comprehensive approach to addressing the prevailing challenges associated with $title, aiming to bridge the gap between theoretical models and practical, real-world deployment.\n\n";
    $draft .= "Furthermore, the background of the study establishes that traditional methodologies often fall short when dealing with the complexities introduced by $title. By investigating the foundational principles and integrating modern frameworks, this research provides a vital stepping stone. The primary objectives are to evaluate the current landscape, identify key limitations, and propose a new architecture that significantly improves performance, reliability, and user satisfaction.\n\n";
    
    $draft .= "Chapter 2: Review of Related Literature\n";
    $draft .= "A thorough examination of existing literature reveals a growing consensus on the importance of $title. Early research primarily focused on basic implementations, but recent advancements have shifted the paradigm towards intelligent, automated systems. According to recent academic journals, the integration of advanced algorithms into $title has yielded up to a 40% increase in operational efficiency in controlled environments.\n\n";
    $draft .= "Despite these advancements, several research gaps remain. Many proposed solutions are heavily theoretical and lack empirical validation in large-scale deployments. Additionally, literature highlights a persistent issue with interoperability between legacy systems and new $title frameworks. This study synthesizes these findings, drawing upon both established theories and cutting-edge experimental data to construct a solid theoretical foundation for the proposed system.\n\n";
    
    $draft .= "Chapter 3: Methodology\n";
    $draft .= "To achieve the objectives of this study, a mixed-methods research design will be employed. The quantitative phase involves the deployment of prototype systems in simulated environments to gather hard data on processing speeds, resource utilization, and error rates related to $title. Statistical analysis tools will be utilized to interpret the raw data, ensuring a high degree of validity and reliability.\n\n";
    $draft .= "The qualitative phase will consist of structured interviews and surveys administered to industry professionals and domain experts. This will provide necessary context regarding the usability and practical challenges of implementing $title. Agile software development methodologies will be used throughout the prototyping phase, allowing for iterative improvements based on initial testing feedback.\n\n";
    
    $draft .= "Chapter 4: Results and Discussion\n";
    $draft .= "[AI Placeholder: Detailed results of the prototype testing will be documented here. Initial models predict a significant reduction in latency and an increase in throughput. The discussion will compare these findings against the baseline metrics established in Chapter 2, analyzing the specific factors within $title that contributed to the success or failure of the tests.]\n\n";
    
    $draft .= "Chapter 5: Conclusion and Recommendations\n";
    $draft .= "In conclusion, the investigation into $title demonstrates clear potential for transforming current industry standards. The proposed framework not only addresses the core limitations identified in the literature but also provides a flexible, scalable architecture for future enhancements. The findings confirm that adopting this approach leads to measurable improvements in both efficiency and reliability.\n\n";
    $draft .= "It is recommended that future researchers expand upon this work by testing the $title framework across diverse, unstructured environments. Additionally, stakeholders should consider investing in training programs to facilitate the seamless integration of these new systems into existing workflows.\n\n";
    
    $draft .= "References\n";
    $draft .= "1. Smith, J., & Doe, A. (2023). Advances in \"$title\": A Comprehensive Review. Journal of Modern Technology, 45(2), 112-130.\n";
    $draft .= "2. Johnson, R. (2022). Evaluating the Performance Metrics of Next-Generation Systems. International Conference on Engineering Innovations, 89-104.\n";
    $draft .= "3. Williams, T., et al. (2024). Overcoming Interoperability Challenges in $title. Tech Research Quarterly, 12(1), 45-60.\n";
    
    echo json_encode(["draft" => $draft]);
    exit();
}

// ==========================================================================
// PANEL COMMENTS & DEFENSE MEETING DOCUMENTATION API ENDPOINTS
// ==========================================================================

// Helper: Ensure user session
if (!isset($_SESSION['user_id']) && (isset($_POST['api_action']) || isset($_GET['api_action']))) {
    require_once 'db.php';
    if (!isset($_SESSION['user_id'])) {
        header('Content-Type: application/json');
        die(json_encode(["success" => false, "error" => "Unauthorized access. Please log in."]));
    }
}

$req_action = $_POST['api_action'] ?? $_GET['api_action'] ?? $_POST['action'] ?? $_GET['action'] ?? '';

// 1. ADD PANEL RESEARCH COMMENT
if ($req_action === 'add_panel_comment') {
    require_once 'db.php';
    header('Content-Type: application/json');
    $uid = (int)($_SESSION['user_id'] ?? 0);
    $role = $_SESSION['role'] ?? '';

    if (!in_array($role, ['panel', 'admin', 'adviser', 'secretary'])) {
        die(json_encode(["success" => false, "error" => "Only panel members or authorized faculty can submit comments."]));
    }

    $student_id = (int)($_POST['student_id'] ?? 0);
    $group_id = !empty($_POST['group_id']) ? (int)$_POST['group_id'] : null;
    $category = sanitize($_POST['category'] ?? 'General Feedback');
    $feedback_type = sanitize($_POST['feedback_type'] ?? 'Suggestion');
    $comment_text = trim($_POST['comment_text'] ?? '');
    $tp_id = !empty($_POST['thesis_panel_id']) ? (int)$_POST['thesis_panel_id'] : null;

    // Handle optional or primary voice recording upload
    $audio_file = save_panel_voice_recording($uid);
    $audio_duration = !empty($_POST['audio_duration']) ? (int)$_POST['audio_duration'] : null;

    if ($student_id <= 0 || (empty($comment_text) && empty($audio_file))) {
        die(json_encode(["success" => false, "error" => "Please select a candidate and either record a voice comment or type a written comment."]));
    }

    if (empty($comment_text) && !empty($audio_file)) {
        $comment_text = "[Voice Recorded Comment]";
    }

    $stmt = $conn->prepare("INSERT INTO panel_research_comments (student_id, group_id, panel_id, thesis_panel_id, category, feedback_type, comment_text, audio_file, audio_duration, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'open')");
    $stmt->bind_param("iiiissssi", $student_id, $group_id, $uid, $tp_id, $category, $feedback_type, $comment_text, $audio_file, $audio_duration);

    if ($stmt->execute()) {
        $new_id = $conn->insert_id;
        $author_name = $_SESSION['name'] ?? 'Panel Evaluator';

        notify_student_and_adviser_on_panel_comment($conn, $student_id, $group_id, $author_name, $category, $feedback_type, $comment_text, !empty($audio_file));

        log_audit('PANEL_COMMENT_ADD', "Panel member {$author_name} posted research feedback ({$category} - {$feedback_type})" . (!empty($audio_file) ? " [with Voice Recording]" : "") . " for student #{$student_id}.", 'Defense & Evaluation', 'success', ['comment_id' => $new_id, 'student_id' => $student_id, 'audio_file' => $audio_file]);

        echo json_encode([
            "success" => true,
            "comment_id" => $new_id,
            "author_name" => $author_name,
            "category" => $category,
            "feedback_type" => $feedback_type,
            "comment_text" => htmlspecialchars($comment_text),
            "audio_file" => $audio_file,
            "audio_duration" => $audio_duration,
            "created_at" => date("M d, Y h:i A"),
            "message" => !empty($audio_file) ? "Voice recording & research comment sent to Student and Adviser dashboards!" : "Research comment submitted to Student and Adviser dashboards!"
        ]);
    } else {
        echo json_encode(["success" => false, "error" => "Database error: " . $conn->error]);
    }
    exit();
}

// 2. UPDATE COMMENT STATUS (e.g. Open / Addressed / Resolved) or STUDENT REPLY
if ($req_action === 'update_comment_status') {
    require_once 'db.php';
    header('Content-Type: application/json');
    $uid = (int)($_SESSION['user_id'] ?? 0);
    $comment_id = (int)($_POST['comment_id'] ?? 0);
    $status = sanitize($_POST['status'] ?? 'open');
    $student_reply = isset($_POST['student_reply']) ? sanitize($_POST['student_reply']) : null;

    if ($comment_id <= 0 || !in_array($status, ['open', 'addressed', 'resolved'])) {
        die(json_encode(["success" => false, "error" => "Invalid comment status or ID."]));
    }

    if ($student_reply !== null) {
        $stmt = $conn->prepare("UPDATE panel_research_comments SET status = ?, student_reply = ? WHERE id = ?");
        $stmt->bind_param("ssi", $status, $student_reply, $comment_id);
    } else {
        $stmt = $conn->prepare("UPDATE panel_research_comments SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $comment_id);
    }

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "status" => $status, "message" => "Comment status updated to " . ucfirst($status)]);
    } else {
        echo json_encode(["success" => false, "error" => "Database error: " . $conn->error]);
    }
    exit();
}

// 3. DELETE PANEL COMMENT
if ($req_action === 'delete_panel_comment') {
    require_once 'db.php';
    header('Content-Type: application/json');
    $uid = (int)($_SESSION['user_id'] ?? 0);
    $role = $_SESSION['role'] ?? '';
    $comment_id = (int)($_POST['comment_id'] ?? 0);

    if ($role === 'admin') {
        $conn->query("DELETE FROM panel_research_comments WHERE id = $comment_id");
    } else {
        $conn->query("DELETE FROM panel_research_comments WHERE id = $comment_id AND panel_id = $uid");
    }

    if ($conn->affected_rows > 0) {
        echo json_encode(["success" => true, "message" => "Comment deleted."]);
    } else {
        echo json_encode(["success" => false, "error" => "Permission denied or comment not found."]);
    }
    exit();
}

// 3b. GOOGLE SCHOLAR INTEGRATION & PUBLICATION SPECIALIZATION EXTRACTOR
if ($req_action === 'sync_google_scholar') {
    require_once 'db.php';
    header('Content-Type: application/json');
    $scholar_url = trim($_REQUEST['google_scholar_url'] ?? '');
    $faculty_name = trim($_REQUEST['faculty_name'] ?? ($_SESSION['name'] ?? ''));
    $department = trim($_REQUEST['department'] ?? '');
    $existing_expertise = trim($_REQUEST['existing_expertise'] ?? '');
    $target_uid = isset($_REQUEST['user_id']) ? (int)$_REQUEST['user_id'] : 0;

    if (empty($scholar_url) && empty($faculty_name)) {
        echo json_encode(["ok" => false, "error" => "Please provide a Google Scholar Profile URL/ID or Faculty Name to retrieve publications."]);
        exit();
    }

    $res = fetch_google_scholar_publications($scholar_url, $faculty_name, $department, $existing_expertise);

    // Optionally persist if save_to_profile=1 and user is authorized
    if (!empty($_REQUEST['save_to_profile']) && $target_uid > 0 && isset($_SESSION['user_id'])) {
        $curr_uid = (int)$_SESSION['user_id'];
        $curr_role = $_SESSION['role'] ?? '';
        if ($curr_role === 'admin' || $curr_uid === $target_uid) {
            $esc_url = $conn->real_escape_string($res['google_scholar_url'] ?? $scholar_url);
            $esc_id = $conn->real_escape_string($res['google_scholar_id'] ?? '');
            $esc_pubs = $conn->real_escape_string($res['formatted_publications'] ?? '');
            $esc_exp = $conn->real_escape_string($res['merged_specializations'] ?? $existing_expertise);
            $conn->query("UPDATE adviser_profile SET google_scholar_url = '$esc_url', google_scholar_id = '$esc_id', published_papers = '$esc_pubs', expertise = '$esc_exp', publications_last_synced = NOW() WHERE user_id = $target_uid");
            $conn->query("UPDATE panel_profile SET google_scholar_url = '$esc_url', google_scholar_id = '$esc_id', published_papers = '$esc_pubs', expertise = '$esc_exp', publications_last_synced = NOW() WHERE user_id = $target_uid");
        }
    }

    echo json_encode($res);
    exit();
}

if ($req_action === 'extract_specializations') {
    require_once 'db.php';
    header('Content-Type: application/json');
    $published_papers = trim($_REQUEST['published_papers'] ?? '');
    $previous_advised_titles = trim($_REQUEST['previous_advised_titles'] ?? '');
    $existing_expertise = trim($_REQUEST['existing_expertise'] ?? '');

    $spec = extract_specializations_from_publications($published_papers, $previous_advised_titles, $existing_expertise);
    echo json_encode([
        "ok" => true,
        "identified_tags" => $spec['identified_tags'],
        "merged_tags" => $spec['merged_tags'],
        "merged_expertise" => $spec['merged_expertise']
    ]);
    exit();
}

// 4. SAVE REAL-TIME TRANSCRIPT ENTRY (Voice-to-Text Speech Block)
if ($req_action === 'save_transcript_entry') {
    require_once 'db.php';
    header('Content-Type: application/json');
    $meeting_id = (int)($_POST['meeting_id'] ?? 0);
    $speaker_name = sanitize($_POST['speaker_name'] ?? 'Speaker');
    $speaker_role = sanitize($_POST['speaker_role'] ?? 'Panel Member');
    $transcript_text = trim($_POST['transcript_text'] ?? '');
    $timestamp_marker = sanitize($_POST['timestamp_marker'] ?? date("H:i:s"));
    $is_voice = isset($_POST['is_voice_generated']) ? (int)$_POST['is_voice_generated'] : 1;

    if ($meeting_id <= 0 || empty($transcript_text)) {
        die(json_encode(["success" => false, "error" => "Meeting identification and speech content are required."]));
    }

    $stmt = $conn->prepare("INSERT INTO defense_transcripts (meeting_id, speaker_name, speaker_role, transcript_text, timestamp_marker, is_voice_generated) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssi", $meeting_id, $speaker_name, $speaker_role, $transcript_text, $timestamp_marker, $is_voice);

    if ($stmt->execute()) {
        $new_tid = $conn->insert_id;
        echo json_encode([
            "success" => true,
            "id" => $new_tid,
            "speaker_name" => htmlspecialchars($speaker_name),
            "speaker_role" => htmlspecialchars($speaker_role),
            "transcript_text" => htmlspecialchars($transcript_text),
            "timestamp_marker" => htmlspecialchars($timestamp_marker),
            "created_at" => date("h:i A")
        ]);
    } else {
        echo json_encode(["success" => false, "error" => "Failed to save transcript: " . $conn->error]);
    }
    exit();
}

// 5. UPDATE TRANSCRIPT ENTRY
if ($req_action === 'update_transcript_entry') {
    require_once 'db.php';
    header('Content-Type: application/json');
    $tid = (int)($_POST['transcript_id'] ?? 0);
    $speaker_name = sanitize($_POST['speaker_name'] ?? '');
    $speaker_role = sanitize($_POST['speaker_role'] ?? 'Panel Member');
    $transcript_text = trim($_POST['transcript_text'] ?? '');

    if ($tid <= 0 || empty($transcript_text)) {
        die(json_encode(["success" => false, "error" => "Transcript ID and updated content are required."]));
    }

    $stmt = $conn->prepare("UPDATE defense_transcripts SET speaker_name = ?, speaker_role = ?, transcript_text = ? WHERE id = ?");
    $stmt->bind_param("sssi", $speaker_name, $speaker_role, $transcript_text, $tid);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Transcript block updated."]);
    } else {
        echo json_encode(["success" => false, "error" => "Failed to update transcript: " . $conn->error]);
    }
    exit();
}

// 6. DELETE TRANSCRIPT ENTRY
if ($req_action === 'delete_transcript_entry') {
    require_once 'db.php';
    header('Content-Type: application/json');
    $tid = (int)($_POST['transcript_id'] ?? 0);

    if ($tid > 0) {
        $conn->query("DELETE FROM defense_transcripts WHERE id = $tid");
        echo json_encode(["success" => true, "message" => "Transcript block deleted."]);
    } else {
        echo json_encode(["success" => false, "error" => "Invalid transcript ID."]);
    }
    exit();
}

// 7. SAVE MINUTES OF THE MEETING (MOM)
if ($req_action === 'save_meeting_minutes') {
    require_once 'db.php';
    header('Content-Type: application/json');
    $uid = (int)($_SESSION['user_id'] ?? 0);
    $role = $_SESSION['role'] ?? '';

    $meeting_id = (int)($_POST['meeting_id'] ?? 0);
    $student_id = (int)($_POST['student_id'] ?? 0);
    $group_id = !empty($_POST['group_id']) ? (int)$_POST['group_id'] : null;
    $document_title = sanitize($_POST['document_title'] ?? 'Official Defense Minutes of the Meeting');
    $defense_stage = sanitize($_POST['defense_stage'] ?? 'Final Oral Defense');
    $meeting_date = sanitize($_POST['meeting_date'] ?? date('Y-m-d'));
    $venue = sanitize($_POST['venue'] ?? 'CEIT Defense Hall');
    $panel_chair_id = !empty($_POST['panel_chair_id']) ? (int)$_POST['panel_chair_id'] : null;
    $panel_members = sanitize($_POST['panel_members'] ?? '');
    $adviser_name = sanitize($_POST['adviser_name'] ?? '');

    $discussion_summary = trim($_POST['discussion_summary'] ?? '');
    $decision_verdict = sanitize($_POST['decision_verdict'] ?? 'Approved with Minor Revisions');
    $grade_average = !empty($_POST['grade_average']) ? (float)$_POST['grade_average'] : null;
    $recommendations = trim($_POST['recommendations'] ?? '');
    $action_items = trim($_POST['action_items'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $recorded_by_name = sanitize($_POST['recorded_by_name'] ?? $_SESSION['name'] ?? 'Committee Documenter');
    $approved_by_chair = sanitize($_POST['approved_by_chair'] ?? '');
    $mom_status = sanitize($_POST['status'] ?? 'draft');

    if ($student_id <= 0) {
        die(json_encode(["success" => false, "error" => "Invalid student candidate record."]));
    }

    if ($meeting_id <= 0) {
        $find_m = $conn->query("SELECT id FROM defense_meetings WHERE student_id = $student_id ORDER BY id DESC LIMIT 1");
        if ($find_m && $find_m->num_rows > 0) {
            $meeting_id = (int)$find_m->fetch_assoc()['id'];
        } else {
            $ins_m = $conn->prepare("INSERT INTO defense_meetings (title, student_id, group_id, defense_stage, meeting_date, venue, panel_members, adviser_name, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'in_progress', ?)");
            if ($ins_m) {
                $ins_m->bind_param("siisssssi", $document_title, $student_id, $group_id, $defense_stage, $meeting_date, $venue, $panel_members, $adviser_name, $uid);
                $ins_m->execute();
                $meeting_id = $conn->insert_id;
            }
        }
    }

    if ($meeting_id <= 0) {
        die(json_encode(["success" => false, "error" => "Could not initialize defense meeting session."]));
    }

    // 1. Update Defense Meeting Metadata
    $new_status = in_array($mom_status, ['finalized', 'signed_off']) ? 'completed' : 'in_progress';
    $m_stmt = $conn->prepare("UPDATE defense_meetings SET title = ?, defense_stage = ?, meeting_date = ?, venue = ?, panel_chair_id = ?, mom_documenter_name = ?, panel_members = ?, adviser_name = ?, status = ? WHERE id = ?");
    if ($m_stmt) {
        $m_stmt->bind_param("ssssissssi", $document_title, $defense_stage, $meeting_date, $venue, $panel_chair_id, $recorded_by_name, $panel_members, $adviser_name, $new_status, $meeting_id);
        $m_stmt->execute();
    }

    // 2. Check if Minutes Record Exists
    $chk = $conn->query("SELECT id FROM defense_meeting_minutes WHERE meeting_id = $meeting_id");
    if ($chk && $chk->num_rows > 0) {
        $mom_id = (int)$chk->fetch_assoc()['id'];
        $stmt = $conn->prepare("UPDATE defense_meeting_minutes SET document_title = ?, discussion_summary = ?, decision_verdict = ?, grade_average = ?, recommendations = ?, action_items = ?, notes = ?, recorded_by_name = ?, approved_by_chair = ?, status = ? WHERE id = ?");
        if (!$stmt) {
            die(json_encode(["success" => false, "error" => "Failed to prepare update: " . $conn->error]));
        }
        $stmt->bind_param("sssdssssssi", $document_title, $discussion_summary, $decision_verdict, $grade_average, $recommendations, $action_items, $notes, $recorded_by_name, $approved_by_chair, $mom_status, $mom_id);
        if (!$stmt->execute()) {
            die(json_encode(["success" => false, "error" => "Failed to save minutes: " . $stmt->error]));
        }
    } else {
        $stmt = $conn->prepare("INSERT INTO defense_meeting_minutes (meeting_id, student_id, group_id, document_title, discussion_summary, decision_verdict, grade_average, recommendations, action_items, notes, recorded_by_name, approved_by_chair, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            die(json_encode(["success" => false, "error" => "Failed to prepare insert: " . $conn->error]));
        }
        $stmt->bind_param("iiisssdssssss", $meeting_id, $student_id, $group_id, $document_title, $discussion_summary, $decision_verdict, $grade_average, $recommendations, $action_items, $notes, $recorded_by_name, $approved_by_chair, $mom_status);
        if (!$stmt->execute()) {
            die(json_encode(["success" => false, "error" => "Failed to create minutes: " . $stmt->error]));
        }
        $mom_id = $conn->insert_id;
    }

    log_audit('SAVE_DEFENSE_MOM', "Defense Minutes of the Meeting saved ({$decision_verdict}) for Student #{$student_id}, Meeting #{$meeting_id}.", 'Defense Documentation', 'success', ['meeting_id' => $meeting_id, 'mom_id' => $mom_id, 'verdict' => $decision_verdict]);

    echo json_encode([
        "success" => true,
        "mom_id" => $mom_id,
        "meeting_id" => $meeting_id,
        "status" => $mom_status,
        "saved_at" => date("M d, Y h:i A"),
        "message" => "Minutes of the Meeting saved successfully."
    ]);
    exit();
}

// 7.1 SOFT-DELETE / ARCHIVE MINUTES OF THE MEETING (MOM)
if ($req_action === 'delete_meeting_minutes' || $req_action === 'archive_meeting_minutes') {
    require_once 'db.php';
    header('Content-Type: application/json');
    $uid = (int)($_SESSION['user_id'] ?? 0);
    $role = $_SESSION['role'] ?? '';

    if (!in_array($role, ['secretary', 'admin', 'panel'])) {
        die(json_encode(["success" => false, "error" => "Unauthorized: Only secretaries, administrators, or panel members can archive MOM records."]));
    }

    $mom_id = (int)($_POST['mom_id'] ?? 0);
    if ($mom_id <= 0) {
        die(json_encode(["success" => false, "error" => "Invalid MOM ID."]));
    }

    $q = $conn->query("SELECT dmm.*, u.name as student_name FROM defense_meeting_minutes dmm LEFT JOIN user u ON dmm.student_id = u.id WHERE dmm.id = $mom_id LIMIT 1");
    if (!$q || $q->num_rows === 0) {
        die(json_encode(["success" => false, "error" => "MOM record not found."]));
    }
    $mom_data = $q->fetch_assoc();

    $now = date('Y-m-d H:i:s');
    $upd = $conn->query("UPDATE defense_meeting_minutes SET is_archived = 1, archived_at = '$now' WHERE id = $mom_id");
    if ($upd) {
        if (function_exists('log_audit')) {
            log_audit('ARCHIVE_DEFENSE_MOM', "Minutes of the Meeting #{$mom_id} archived (soft-deleted) for student {$mom_data['student_name']}.", 'Defense Documentation', 'success', ['mom_id' => $mom_id, 'student_id' => $mom_data['student_id']]);
        }
        echo json_encode(["success" => true, "message" => "Minutes of the Meeting moved to Archived MOMs safely."]);
    } else {
        echo json_encode(["success" => false, "error" => "Database error while archiving MOM: " . $conn->error]);
    }
    exit();
}

// 7.2 RESTORE ARCHIVED MINUTES OF THE MEETING (MOM)
if ($req_action === 'restore_meeting_minutes') {
    require_once 'db.php';
    header('Content-Type: application/json');
    $uid = (int)($_SESSION['user_id'] ?? 0);
    $role = $_SESSION['role'] ?? '';

    if (!in_array($role, ['secretary', 'admin', 'panel'])) {
        die(json_encode(["success" => false, "error" => "Unauthorized access."]));
    }

    $mom_id = (int)($_POST['mom_id'] ?? 0);
    if ($mom_id <= 0) {
        die(json_encode(["success" => false, "error" => "Invalid MOM ID."]));
    }

    $q = $conn->query("SELECT dmm.*, u.name as student_name FROM defense_meeting_minutes dmm LEFT JOIN user u ON dmm.student_id = u.id WHERE dmm.id = $mom_id LIMIT 1");
    if (!$q || $q->num_rows === 0) {
        die(json_encode(["success" => false, "error" => "MOM record not found."]));
    }
    $mom_data = $q->fetch_assoc();

    $upd = $conn->query("UPDATE defense_meeting_minutes SET is_archived = 0, archived_at = NULL WHERE id = $mom_id");
    if ($upd) {
        if (function_exists('log_audit')) {
            log_audit('RESTORE_DEFENSE_MOM', "Minutes of the Meeting #{$mom_id} restored to active registry for student {$mom_data['student_name']}.", 'Defense Documentation', 'success', ['mom_id' => $mom_id, 'student_id' => $mom_data['student_id']]);
        }
        echo json_encode(["success" => true, "message" => "Minutes of the Meeting successfully restored to the active registry."]);
    } else {
        echo json_encode(["success" => false, "error" => "Database error while restoring MOM: " . $conn->error]);
    }
    exit();
}

// 7.3 PERMANENTLY PURGE MINUTES OF THE MEETING (MOM)
if ($req_action === 'purge_meeting_minutes') {
    require_once 'db.php';
    header('Content-Type: application/json');
    $uid = (int)($_SESSION['user_id'] ?? 0);
    $role = $_SESSION['role'] ?? '';

    if (!in_array($role, ['secretary', 'admin'])) {
        die(json_encode(["success" => false, "error" => "Unauthorized: Only secretaries or administrators can permanently purge records."]));
    }

    $mom_id = (int)($_POST['mom_id'] ?? 0);
    if ($mom_id <= 0) {
        die(json_encode(["success" => false, "error" => "Invalid MOM ID."]));
    }

    $q = $conn->query("SELECT dmm.*, u.name as student_name FROM defense_meeting_minutes dmm LEFT JOIN user u ON dmm.student_id = u.id WHERE dmm.id = $mom_id LIMIT 1");
    if (!$q || $q->num_rows === 0) {
        die(json_encode(["success" => false, "error" => "MOM record not found."]));
    }
    $mom_data = $q->fetch_assoc();

    $del = $conn->query("DELETE FROM defense_meeting_minutes WHERE id = $mom_id");
    if ($del) {
        if (function_exists('log_audit')) {
            log_audit('PURGE_DEFENSE_MOM', "Minutes of the Meeting #{$mom_id} permanently purged for student {$mom_data['student_name']}.", 'Defense Documentation', 'success', ['mom_id' => $mom_id, 'student_id' => $mom_data['student_id']]);
        }
        echo json_encode(["success" => true, "message" => "Minutes of the Meeting permanently purged."]);
    } else {
        echo json_encode(["success" => false, "error" => "Database error while purging MOM: " . $conn->error]);
    }
    exit();
}

// 7.4 RESTORE ALL ARCHIVED MINUTES OF THE MEETING
if ($req_action === 'restore_all_meeting_minutes') {
    require_once 'db.php';
    header('Content-Type: application/json');
    $uid = (int)($_SESSION['user_id'] ?? 0);
    $role = $_SESSION['role'] ?? '';

    if (!in_array($role, ['secretary', 'admin'])) {
        die(json_encode(["success" => false, "error" => "Unauthorized access."]));
    }

    $count_res = $conn->query("SELECT COUNT(*) as c FROM defense_meeting_minutes WHERE is_archived = 1");
    $count = (int)($count_res ? $count_res->fetch_assoc()['c'] : 0);

    if ($count === 0) {
        die(json_encode(["success" => false, "error" => "No archived records to restore."]));
    }

    $upd = $conn->query("UPDATE defense_meeting_minutes SET is_archived = 0, archived_at = NULL WHERE is_archived = 1");
    if ($upd) {
        if (function_exists('log_audit')) {
            log_audit('RESTORE_ALL_DEFENSE_MOMS', "Restored {$count} archived Minutes of the Meeting records to active registry.", 'Defense Documentation', 'success', ['count' => $count]);
        }
        echo json_encode(["success" => true, "restored_count" => $count, "message" => "All {$count} archived MOM record(s) restored successfully."]);
    } else {
        echo json_encode(["success" => false, "error" => "Database error while restoring records: " . $conn->error]);
    }
    exit();
}

// 7.5 PERMANENTLY PURGE ALL ARCHIVED MINUTES OF THE MEETING
if ($req_action === 'purge_all_meeting_minutes' || $req_action === 'delete_all_meeting_minutes') {
    require_once 'db.php';
    header('Content-Type: application/json');
    $uid = (int)($_SESSION['user_id'] ?? 0);
    $role = $_SESSION['role'] ?? '';

    if (!in_array($role, ['secretary', 'admin'])) {
        die(json_encode(["success" => false, "error" => "Unauthorized: Only secretaries or administrators can permanently delete all archived records."]));
    }

    $count_res = $conn->query("SELECT COUNT(*) as c FROM defense_meeting_minutes WHERE is_archived = 1");
    $count = (int)($count_res ? $count_res->fetch_assoc()['c'] : 0);

    if ($count === 0) {
        die(json_encode(["success" => false, "error" => "No archived records to delete."]));
    }

    $del = $conn->query("DELETE FROM defense_meeting_minutes WHERE is_archived = 1");
    if ($del) {
        if (function_exists('log_audit')) {
            log_audit('PURGE_ALL_DEFENSE_MOMS', "Permanently purged {$count} archived Minutes of the Meeting records.", 'Defense Documentation', 'success', ['count' => $count]);
        }
        echo json_encode(["success" => true, "deleted_count" => $count, "message" => "All {$count} archived MOM record(s) permanently deleted."]);
    } else {
        echo json_encode(["success" => false, "error" => "Database error while deleting records: " . $conn->error]);
    }
    exit();
}

// 8. AUTO-SUMMARIZE / EXTRACT MOM HIGHLIGHTS FROM VOICE TRANSCRIPT
if ($req_action === 'generate_mom_summary') {
    require_once 'db.php';
    header('Content-Type: application/json');
    $meeting_id = (int)($_POST['meeting_id'] ?? 0);

    if ($meeting_id <= 0) {
        die(json_encode(["success" => false, "error" => "Valid meeting ID is required."]));
    }

    $t_res = $conn->query("SELECT speaker_name, speaker_role, transcript_text, timestamp_marker FROM defense_transcripts WHERE meeting_id = $meeting_id ORDER BY id ASC");
    if (!$t_res || $t_res->num_rows == 0) {
        die(json_encode(["success" => false, "error" => "No speech transcripts recorded yet. Record discussions first to generate highlights."]));
    }

    $transcripts = [];
    $all_text = "";
    while ($row = $t_res->fetch_assoc()) {
        $transcripts[] = $row;
        $all_text .= "[{$row['timestamp_marker']}] {$row['speaker_name']} ({$row['speaker_role']}): {$row['transcript_text']}\n";
    }

    // Intelligent extraction of discussions, recommendations, and action items
    $summary = "1. Session Call to Order & Preliminaries:\n";
    $summary .= "The oral defense session commenced with opening remarks. The candidate presented an overview of the capstone project objectives, system design, and experimental findings.\n\n";
    $summary .= "2. Key Inquiries & Deliberations Raised:\n";

    $recs = [];
    $actions = [];
    $count = 1;

    foreach ($transcripts as $t) {
        $sp = $t['speaker_name'];
        $role = $t['speaker_role'];
        $txt = $t['transcript_text'];

        $lower_txt = ' ' . mb_strtolower($txt) . ' ';
        $is_recommendation = false;
        $rec_keywords = [
            'recommend', 'suggest', 'revise', 'improve', 'change', 'add', 'modify', 'update',
            'i-revise', 'i-update', 'i-check', 'i-rephrase', 'i-ayos', 'i-usab', 'i-modify',
            'kinahanglan', 'dapat', 'kailangan', 'paki-', 'paki ', 'gi-suggest', 'gi-recommend',
            'ayuhon', 'usba', 'bag-oha', 'kulang', 'kuwang', 'paki-explain', 'paki-clarify'
        ];
        foreach ($rec_keywords as $kw) {
            if (mb_stripos($lower_txt, $kw) !== false) {
                $is_recommendation = true;
                break;
            }
        }

        if ($is_recommendation) {
            $recs[] = "• ($sp - $role): " . trim($txt);
            $actions[] = [
                'task' => "Incorporate panel feedback: " . mb_strimwidth(trim($txt), 0, 80, '...'),
                'assigned' => 'Candidate / Capstone Group',
                'deadline' => date('Y-m-d', strtotime('+7 days')),
                'status' => 'Pending'
            ];
        }

        if ($role === 'Panel Chair' || $role === 'Panel Member') {
            $summary .= "• [{$t['timestamp_marker']}] {$sp}: Raised question on {$txt}\n";
        }
    }

    if (empty($recs)) {
        $recs[] = "• Refine Chapter 3 methodology diagrams and clarify hardware sensor specifications.";
        $recs[] = "• Update literature citations with recent 2024–2025 IEEE/ACM peer-reviewed publications.";
        $recs[] = "• Standardize CSU CEIT formatting across all manuscript tables and figures.";
    }

    if (empty($actions)) {
        $actions[] = [
            'task' => 'Implement required manuscript revisions according to panel recommendations',
            'assigned' => 'Capstone Team',
            'deadline' => date('Y-m-d', strtotime('+10 days')),
            'status' => 'Pending'
        ];
        $actions[] = [
            'task' => 'Submit revised manuscript draft to Thesis Adviser for verification',
            'assigned' => 'Group Leader',
            'deadline' => date('Y-m-d', strtotime('+14 days')),
            'status' => 'Pending'
        ];
    }

    echo json_encode([
        "success" => true,
        "discussion_summary" => $summary,
        "recommendations" => implode("\n", $recs),
        "action_items" => json_encode($actions, JSON_PRETTY_PRINT),
        "message" => "Minutes of the Meeting generated from {$t_res->num_rows} transcript blocks."
    ]);
    exit();
}

// ── Consultation: Mark Single Item as Seen & Return Remaining Count ─────────
if ((isset($_REQUEST['action']) && $_REQUEST['action'] === 'mark_consultation_seen') || (isset($_POST['action']) && $_POST['action'] === 'mark_consultation_seen')) {
    require_once 'db.php';
    if (!isset($_SESSION['user_id'])) {
        die(json_encode(["success" => false, "error" => "Unauthorized"]));
    }
    $uid = (int)$_SESSION['user_id'];
    $role = $_SESSION['role'] ?? '';
    $cons_id = (int)($_REQUEST['cons_id'] ?? ($_POST['cons_id'] ?? 0));

    if ($role === 'adviser') {
        if ($cons_id > 0) {
            $conn->query("UPDATE adviser_consultations SET adviser_seen = 1 WHERE id = $cons_id AND adviser_id = $uid");
        }
        $rem = (int)($conn->query("SELECT COUNT(*) as c FROM adviser_consultations WHERE adviser_id = $uid AND status IN ('pending_review', 'student_responded') AND adviser_seen = 0")->fetch_assoc()['c'] ?? 0);
        echo json_encode(["success" => true, "remaining_count" => $rem]);
        exit();
    } elseif ($role === 'student') {
        $sp_g = $conn->query("SELECT group_id FROM student_profile WHERE user_id = $uid")->fetch_assoc();
        $gid = (int)($sp_g['group_id'] ?? 0);
        $w = ($gid > 0) ? "(group_id = $gid OR student_id = $uid)" : "student_id = $uid";
        if ($cons_id > 0) {
            $conn->query("UPDATE adviser_consultations SET student_seen = 1 WHERE id = $cons_id AND $w");
        }
        $rem = (int)($conn->query("SELECT COUNT(*) as c FROM adviser_consultations WHERE $w AND status = 'follow_up_required' AND student_seen = 0")->fetch_assoc()['c'] ?? 0);
        echo json_encode(["success" => true, "remaining_count" => $rem]);
        exit();
    }
    echo json_encode(["success" => false]);
    exit();
}

// ── Notification: Mark as Read ──────────────────────────────────────────────
if ((isset($_REQUEST['action']) && $_REQUEST['action'] === 'mark_notifications_read') || (isset($_POST['action']) && $_POST['action'] === 'mark_notifications_read')) {
    require_once 'db.php';
    if (!isset($_SESSION['user_id'])) {
        die(json_encode(["success" => false, "error" => "Unauthorized"]));
    }
    $uid = (int)$_SESSION['user_id'];
    $notif_id = !empty($_REQUEST['notif_id']) ? (int)$_REQUEST['notif_id'] : (!empty($_POST['notif_id']) ? (int)$_POST['notif_id'] : 0);
    if ($notif_id > 0) {
        $conn->query("UPDATE system_notifications SET is_read = 1 WHERE id = $notif_id AND user_id = $uid");
    } else {
        $conn->query("UPDATE system_notifications SET is_read = 1 WHERE user_id = $uid");
    }
    echo json_encode(["success" => true]);
    exit();
}

// ── Fetch Defense Schedule Details ──────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'get_schedule_details') {
    require_once 'db.php';
    if (!isset($_SESSION['user_id'])) {
        die(json_encode(["success" => false, "error" => "Unauthorized"]));
    }
    $sid = (int)($_GET['schedule_id'] ?? 0);
    $q = $conn->query("
        SELECT dm.*, 
               u.name as student_name, u.email as student_email,
               sp.department as student_department, sp.thesis_topic as student_topic,
               cg.group_name,
               chair.name as panel_chair_name,
               adv.name as adviser_user_name,
               doc.name as mom_documenter_user_name
        FROM defense_meetings dm
        JOIN user u ON dm.student_id = u.id
        LEFT JOIN student_profile sp ON u.id = sp.user_id
        LEFT JOIN capstone_group cg ON dm.group_id = cg.id
        LEFT JOIN user chair ON dm.panel_chair_id = chair.id
        LEFT JOIN user adv ON dm.adviser_id = adv.id
        LEFT JOIN user doc ON dm.mom_documenter_id = doc.id
        WHERE dm.id = $sid
        LIMIT 1
    ");
    if (!$q || $q->num_rows == 0) {
        die(json_encode(["success" => false, "error" => "Schedule not found"]));
    }
    $sched = $q->fetch_assoc();

    // Also fetch panel members assigned to this student
    $panels_res = $conn->query("SELECT tp.panel_id, u.name, u.email FROM thesis_panel tp JOIN user u ON tp.panel_id = u.id WHERE tp.student_id = {$sched['student_id']}");
    $assigned_panel_ids = [];
    $assigned_panel_names = [];
    if ($panels_res) {
        while ($p = $panels_res->fetch_assoc()) {
            $assigned_panel_ids[] = (int)$p['panel_id'];
            $assigned_panel_names[] = $p['name'];
        }
    }
    $sched['panel_member_ids'] = $assigned_panel_ids;
    $sched['panel_member_names'] = $assigned_panel_names;
    $raw_dept = !empty($sched['department']) ? $sched['department'] : ($sched['student_department'] ?? 'Information Technology');
    $sched['department'] = normalize_course_name($raw_dept);
    $sched['dept_norm'] = $sched['department'];
    $sched['dept_code'] = get_course_short_code($sched['department']);

    // Fetch existing MOM record if present
    $mom_res = $conn->query("SELECT * FROM defense_meeting_minutes WHERE meeting_id = $sid LIMIT 1");
    if ($mom_res && $mom_res->num_rows > 0) {
        $sched['mom'] = $mom_res->fetch_assoc();
    } else {
        $sched['mom'] = null;
    }

    echo json_encode(["success" => true, "schedule" => $sched]);
    exit();
}
?>
