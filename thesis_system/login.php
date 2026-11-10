<?php
require 'db.php';
if(isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';
$old = [];

// Check if signup or forgot password is active based on GET param
$is_signup = (isset($_GET['signup']) && $_GET['signup'] == 1);
$is_forgot = (isset($_GET['forgot']) && $_GET['forgot'] == 1);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['login'])) {
        $email = sanitize($_POST['email']);
        $password = $_POST['password'];
        
        $stmt = $conn->prepare("SELECT id, password, role, name, account_status FROM user WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();
            
        if ($res->num_rows > 0) {
            $all_matching = [];
            $valid_matching = [];
            while ($u = $res->fetch_assoc()) {
                $all_matching[] = $u;
                if (password_verify($password, $u['password']) || $password === $u['password']) {
                    $valid_matching[] = $u;
                }
            }

            if (empty($valid_matching)) {
                $error = "Invalid password.";
                $u_first = $all_matching[0];
                log_audit('LOGIN_FAILED', "Failed login attempt for user '{$u_first['name']}' ({$email}): Invalid password.", 'Authentication', 'failed', ['email' => $email], $u_first['id'], $u_first['name'], $u_first['role']);
            } elseif (count($valid_matching) === 1) {
                $user = $valid_matching[0];
                if ($user['account_status'] == 'pending') {
                    $error = "Your " . ucfirst($user['role']) . " account is pending admin approval.";
                    log_audit('LOGIN_BLOCKED', "User '{$user['name']}' attempted login but account is pending approval.", 'Authentication', 'warning', ['email' => $email], $user['id'], $user['name'], $user['role']);
                } elseif ($user['account_status'] == 'rejected') {
                    $error = "Your " . ucfirst($user['role']) . " account registration was rejected. Please contact the administrator.";
                    log_audit('LOGIN_BLOCKED', "User '{$user['name']}' attempted login but account was rejected.", 'Authentication', 'warning', ['email' => $email], $user['id'], $user['name'], $user['role']);
                } else {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['name'] = $user['name'];
                    log_audit('LOGIN', "User '{$user['name']}' logged in successfully as " . ucfirst($user['role']) . ".", 'Authentication', 'success', ['role' => $user['role']], $user['id'], $user['name'], $user['role']);
                    header("Location: dashboard.php");
                    exit();
                }
            } else {
                // Multiple active accounts matching this email & password
                $_SESSION['multi_login_accounts'] = $valid_matching;
                $_SESSION['multi_login_name'] = $valid_matching[0]['name'];
                $_SESSION['multi_login_email'] = $email;
            }
        } else {
            $error = "User not found.";
            log_audit('LOGIN_FAILED', "Failed login attempt: Unregistered email '{$email}'.", 'Authentication', 'failed', ['email' => $email], null, $email, '');
        }
            
    } elseif (isset($_POST['choose_role_login'])) {
        $selected_id = (int)($_POST['selected_user_id'] ?? 0);
        if (!empty($_SESSION['multi_login_accounts'])) {
            foreach ($_SESSION['multi_login_accounts'] as $user) {
                if ((int)$user['id'] === $selected_id) {
                    unset($_SESSION['multi_login_accounts']);
                    unset($_SESSION['multi_login_name']);
                    unset($_SESSION['multi_login_email']);
                    if ($user['account_status'] == 'pending') {
                        $error = "Your " . ucfirst($user['role']) . " account is pending admin approval.";
                        log_audit('LOGIN_BLOCKED', "User '{$user['name']}' attempted login but account is pending approval.", 'Authentication', 'warning', ['email' => $user['email'] ?? ''], $user['id'], $user['name'], $user['role']);
                        break;
                    } elseif ($user['account_status'] == 'rejected') {
                        $error = "Your " . ucfirst($user['role']) . " account registration was rejected. Please contact the administrator.";
                        log_audit('LOGIN_BLOCKED', "User '{$user['name']}' attempted login but account was rejected.", 'Authentication', 'warning', ['email' => $user['email'] ?? ''], $user['id'], $user['name'], $user['role']);
                        break;
                    } else {
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['name'] = $user['name'];
                        log_audit('LOGIN', "User '{$user['name']}' logged in successfully as " . ucfirst($user['role']) . ".", 'Authentication', 'success', ['role' => $user['role']], $user['id'], $user['name'], $user['role']);
                        header("Location: dashboard.php");
                        exit();
                    }
                }
            }
        }
    } elseif (isset($_POST['cancel_role_choice'])) {
        unset($_SESSION['multi_login_accounts']);
        unset($_SESSION['multi_login_name']);
        unset($_SESSION['multi_login_email']);
    } elseif (isset($_POST['register'])) {
        $old = $_POST;
        $firstname = sanitize($_POST['firstname'] ?? '');
        $lastname = sanitize($_POST['lastname'] ?? '');
        $extension = sanitize($_POST['extension'] ?? '');
        $sex = sanitize($_POST['sex'] ?? '');
        $dob = sanitize($_POST['dob'] ?? '');
        $name = trim($firstname . ' ' . $lastname . (!empty($extension) ? ' ' . $extension : ''));
        $email = sanitize($_POST['email'] ?? '');
        $address = sanitize($_POST['address'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $role = sanitize($_POST['role'] ?? '');
        $department = sanitize($_POST['department'] ?? '');
        $school_id = sanitize($_POST['school_id'] ?? '');
        
        // Comprehensive Server-Side Validation
        // Name validator helper
        $name_error = function($val, $label) {
            if (empty(trim($val))) return $label . ' is required. Please enter your ' . strtolower($label) . ' (minimum 2 characters, letters only, must start with a capital letter).';
            if (strlen(trim($val)) < 2) return $label . ' is too short — please enter at least 2 characters. Example: "Jo" or "Ana".';
            if (strlen(trim($val)) > 30) return $label . ' is too long — maximum of 30 characters allowed.';
            if ($val !== trim($val) && substr($val, 0, 1) === ' ') return $label . ' cannot start with a space.';
            if (substr(trim($val), -1) === ' ') return $label . ' cannot end with a space.';
            if (preg_match('/  /', $val)) return $label . ' cannot have double spaces.';
            if (preg_match('/[0-9]/', $val)) return 'Numbers are not allowed in ' . strtolower($label) . '.';
            if (preg_match('/[^a-zA-Z\s\-\']/', $val)) return 'Special characters are not allowed in ' . strtolower($label) . '.';
            if (preg_match('/([a-zA-Z])\1\1/', $val)) return $label . ' cannot have three consecutive identical letters.';
            if (!preg_match('/^[A-Z]/', trim($val))) return $label . ' must start with a capital letter.';
            if (preg_match('/^[A-Z]+$/', trim($val))) return $label . ' cannot be all capital letters.';
            if (preg_match('/[a-z][A-Z]/', $val) || preg_match('/[A-Z][a-z][A-Z]/', $val)) {
                // Check for mixed capitalization (allow normal Title Case or hyphenated names)
                $words = preg_split('/[\s\-]+/', trim($val));
                foreach ($words as $word) {
                    if (strlen($word) > 1 && preg_match('/[A-Z]/', substr($word, 1))) {
                        return $label . ' cannot have mixed capitalization within a word.';
                    }
                }
            }
            return '';
        };

        $fn_err = $name_error($firstname, 'First name');
        $ln_err = $name_error($lastname, 'Last name');

        if ($fn_err) {
            $error = $fn_err;
            $is_signup = true;
        } elseif ($ln_err) {
            $error = $ln_err;
            $is_signup = true;
        } elseif (!empty($extension) && (!preg_match('/^[IVXLCDMivxlcdm\.\s]+$/', $extension) || strlen($extension) > 10)) {
            $error = 'Extension must be Roman numerals only (e.g. Jr, III, IV) and max 10 characters.';
        } elseif (!empty($extension) && (!preg_match('/^(Jr\.?|Sr\.?|[IVXLCDM]+\.?)$/i', trim($extension)) || strlen($extension) > 10)) {
            $error = 'Extension must be a valid name suffix or Roman numeral (e.g. Jr., Sr., II, III, IV) and max 10 characters.';
            $is_signup = true;
        } elseif (empty($sex) || !in_array($sex, ['Male', 'Female'])) {
            $error = "Please select your sex.";
            $is_signup = true;
        } elseif (empty($dob)) {
            $error = "Please enter your date of birth.";
            $is_signup = true;
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob) || !strtotime($dob)) {
            $error = "Please enter a valid date of birth (YYYY-MM-DD).";
            $is_signup = true;
        } elseif (strtotime($dob) > time()) {
            $error = "Date of birth cannot be in the future.";
            $is_signup = true;
        } elseif ((new DateTime())->diff(new DateTime($dob))->y < 15) {
            $error = "You must be at least 15 years old to register.";
            $is_signup = true;
        } elseif ((new DateTime())->diff(new DateTime($dob))->y > 100) {
            $error = "Please enter a valid date of birth.";
            $is_signup = true;
        } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address (e.g. name@csucc.edu.ph or name@gmail.com).";
            $is_signup = true;
        } elseif (!preg_match('/@((csucc\.edu\.ph)|(csu\.edu\.ph)|(gmail\.com)|(ceit\.edu))$/i', $email)) {
            $error = "Registration requires an institutional email (@csucc.edu.ph) or Gmail (@gmail.com).";
            $is_signup = true;
        } elseif (empty($phone) || !preg_match('/^\d{11}$/', $phone)) {
            $error = "Phone number must be exactly 11 digits (numbers only, e.g. 09123456789).";
            $is_signup = true;
        } elseif (empty($address) || strlen($address) < 4) {
            $error = "Please enter a valid home address (e.g. Street / Barangay / City / Municipality).";
            $is_signup = true;
        } elseif (strlen($password) < 8) {
            $error = "Password must be at least 8 characters long.";
            $is_signup = true;
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match!";
            $is_signup = true;
        } elseif (!in_array($role, ['student', 'adviser', 'panel', 'secretary'])) {
            $error = "Please select a valid user role.";
            $is_signup = true;
        } elseif ($role === 'student' && empty(trim($school_id))) {
            $error = "School ID is required for student registration. Please enter your student ID number.";
            $is_signup = true;
        } elseif ($role === 'student' && (strlen(trim($school_id)) < 3 || strlen(trim($school_id)) > 30)) {
            $error = "School ID must be between 3 and 30 characters (e.g. 2021-00123).";
            $is_signup = true;
        } elseif ($role === 'student' && !preg_match('/^[A-Za-z0-9\-\s]+$/', trim($school_id))) {
            $error = "School ID may only contain letters, numbers, hyphens, and spaces.";
            $is_signup = true;
        } elseif (empty($department) || !in_array($department, [
            'Information Technology',
            'Computer Engineering',
            'Computer Engineering (ComEng)',
            'Electrical Engineering',
            'Electrical Engineering (EE)'
        ])) {
            $error = "Please select a valid academic department.";
            $is_signup = true;
        } elseif (($role == 'adviser' || $role == 'panel') && empty(trim($_POST['expertise'] ?? ''))) {
            $error = "Faculty members must provide their field(s) of expertise.";
            $is_signup = true;
        } elseif ($role == 'adviser' && (!isset($_POST['capacity']) || (int)$_POST['capacity'] < 1 || (int)$_POST['capacity'] > 30)) {
            $error = "Adviser student capacity must be between 1 and 30.";
            $is_signup = true;
        } else {
            // Check if email already registered for this specific role
            $stmt_chk = $conn->prepare("SELECT id FROM user WHERE email = ? AND role = ?");
            $stmt_chk->bind_param("ss", $email, $role);
            $stmt_chk->execute();
            $res_chk = $stmt_chk->get_result();
            
            if ($res_chk->num_rows > 0) {
                $error = "An account with this email address is already registered as " . ucfirst($role) . "! Please sign in.";
                $is_signup = true;
            } elseif ($role === 'student') {
                // Check if School ID is already registered to an existing student
                $stmt_sid = $conn->prepare("SELECT sp.id FROM student_profile sp JOIN user u ON sp.user_id = u.id WHERE sp.student_id = ? AND sp.student_id != '' AND u.account_status != 'archived'");
                $stmt_sid->bind_param("s", $school_id);
                $stmt_sid->execute();
                if ($stmt_sid->get_result()->num_rows > 0) {
                    $error = "This School ID ({$school_id}) is already registered to an existing student account. Please use your own school ID or contact the administrator.";
                    $is_signup = true;
                }
            }

            if (empty($error)) {
                $password_hashed = password_hash($password, PASSWORD_BCRYPT);
                $status = in_array($role, ['adviser', 'panel', 'secretary']) ? 'pending' : 'active';
                
                $stmt = $conn->prepare("INSERT INTO user (firstname, lastname, sex, dob, extension, name, email, address, phone, password, role, account_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssssssssss", $firstname, $lastname, $sex, $dob, $extension, $name, $email, $address, $phone, $password_hashed, $role, $status);
                $stmt->execute();
                $user_id = $conn->insert_id;
                
                if ($role == 'student') {
                    $stmt_stu = $conn->prepare("INSERT INTO student_profile (user_id, student_id, department, skills, thesis_topic) VALUES (?, ?, ?, '', '')");
                    $stmt_stu->bind_param("iss", $user_id, $school_id, $department);
                    $stmt_stu->execute();
                } elseif ($role == 'adviser') {
                    $exp = sanitize($_POST['expertise'] ?? '');
                    $cap = isset($_POST['capacity']) ? (int)$_POST['capacity'] : 5;
                    $sched = sanitize($_POST['schedule'] ?? '');
                    
                    $stmt_adv = $conn->prepare("INSERT INTO adviser_profile (user_id, department, expertise, capacity, schedule) VALUES (?, ?, ?, ?, ?)");
                    $stmt_adv->bind_param("issis", $user_id, $department, $exp, $cap, $sched);
                    $stmt_adv->execute();
                } elseif ($role == 'panel') {
                    $exp = sanitize($_POST['expertise'] ?? '');
                    $stmt_pan = $conn->prepare("INSERT INTO panel_profile (user_id, department, expertise) VALUES (?, ?, ?)");
                    $stmt_pan->bind_param("iss", $user_id, $department, $exp);
                    $stmt_pan->execute();
                } elseif ($role == 'secretary') {
                    $stmt_sec = $conn->prepare("INSERT INTO secretary_profile (user_id, department, office) VALUES (?, ?, 'CEIT Defense Secretariat Office')");
                    $stmt_sec->bind_param("is", $user_id, $department);
                    $stmt_sec->execute();
                }
                
                $success = in_array($role, ['adviser', 'panel', 'secretary']) 
                    ? "Registration submitted successfully! Your account is pending Admin approval." 
                    : "Registration successful! You may now sign in.";
                $is_signup = false;
                $old = []; // Clear sticky inputs

                log_audit(
                    'USER_REGISTER',
                    "New registration: '{$name}' registered as " . ucfirst($role) . " in department '{$department}'" . ($role === 'student' ? " (School ID: {$school_id})" : "") . " (Status: {$status}).",
                    'User Management',
                    'success',
                    ['user_id' => $user_id, 'email' => $email, 'school_id' => ($role === 'student' ? $school_id : null), 'dob' => $dob, 'role' => $role, 'department' => $department, 'account_status' => $status],
                    $user_id,
                    $name,
                    $role
                );
            }
        }
    } elseif (isset($_POST['reset_password'])) {
        $reset_email = sanitize($_POST['reset_email'] ?? '');
        $new_password = $_POST['new_password'] ?? '';
        $confirm_new_password = $_POST['confirm_new_password'] ?? '';
        $old_reset_email = $reset_email;

        if (empty($reset_email) || !filter_var($reset_email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid registered email address.";
            $is_forgot = true;
        } elseif (strlen($new_password) < 8) {
            $error = "New password must be at least 8 characters long.";
            $is_forgot = true;
        } elseif ($new_password !== $confirm_new_password) {
            $error = "New passwords do not match!";
            $is_forgot = true;
        } else {
            
            $stmt_u = $conn->prepare("SELECT id, name FROM user WHERE email = ?");
            $stmt_u->bind_param("s", $reset_email);
            $stmt_u->execute();
            $u_res = $stmt_u->get_result();

            if ($u_res->num_rows === 0) {
                $error = "No user account found with this email address.";
                $is_forgot = true;
            } else {
                $hashed = password_hash($new_password, PASSWORD_BCRYPT);
                $stmt_up = $conn->prepare("UPDATE user SET password = ? WHERE email = ?");
                $stmt_up->bind_param("ss", $hashed, $reset_email);
                
                if ($stmt_up->execute()) {
                    $success = "Password successfully reset! You can now sign in with your new password.";
                    $is_forgot = false;
                    $is_signup = false;
                } else {
                    $error = "Failed to reset password. Please try again.";
                    $is_forgot = true;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CSU CEIT | Interactive Platform for Capstone Project Oral Defense</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/variables.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/layout.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/components.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/auth.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/print.css?v=<?php echo time(); ?>">
</head>
<body class="auth-page-body">

<!-- ==========================================
     TOP NAVIGATION BAR (Cohesive with Homepage)
     ========================================== -->
<header class="auth-navbar">
    <a href="../index.php" class="auth-brand-container">
        <div class="auth-brand-logo-wrap">
            <img src="logo.png" alt="CSUCEIT Logo" class="auth-brand-logo">
            <span class="auth-brand-ai-pulse" title="AI Match Engine Active"></span>
        </div>
        <div class="auth-brand-text">
            <div class="auth-brand-title">
                CSUCEIT <span class="badge-ai">AI MATCH</span>
            </div>
            <span class="auth-brand-subtitle">Interactive Platform for Capstone Project Oral Defense</span>
        </div>
    </a>

    <!-- Navigation Links identical to Homepage -->
    <ul class="auth-nav-menu">
        <li><a href="../index.php"><i class="fa-solid fa-house" style="font-size: 0.8rem; color: #f59e0b;"></i> Home</a></li>
        <li><a href="../index.php#defense-hub"><i class="fa-solid fa-graduation-cap" style="font-size: 0.8rem; color: #f59e0b;"></i> Oral Defense Hub</a></li>
        <li><a href="../index.php#capabilities"><i class="fa-solid fa-layer-group" style="font-size: 0.8rem; color: #f59e0b;"></i> Platform Features</a></li>
        <li><a href="../index.php#workflow"><i class="fa-solid fa-diagram-project" style="font-size: 0.8rem; color: #f59e0b;"></i> Defense Lifecycle</a></li>
    </ul>

    <div class="auth-nav-right-action">
        <button class="auth-mobile-menu-toggle" id="authMobileToggle" aria-label="Toggle navigation">
            <i class="fa-solid fa-bars"></i>
        </button>
    </div>
</header>

<!-- Mobile Drawer for Login / Register -->
<div class="auth-mobile-nav-drawer" id="authMobileDrawer">
    <a href="../index.php" onclick="closeAuthMobileMenu()"><i class="fa-solid fa-house" style="color: #f59e0b;"></i> Home</a>
    <a href="../index.php#defense-hub" onclick="closeAuthMobileMenu()"><i class="fa-solid fa-graduation-cap" style="color: #f59e0b;"></i> Oral Defense Hub</a>
    <a href="../index.php#capabilities" onclick="closeAuthMobileMenu()"><i class="fa-solid fa-layer-group" style="color: #f59e0b;"></i> Platform Features</a>
    <a href="../index.php#workflow" onclick="closeAuthMobileMenu()"><i class="fa-solid fa-diagram-project" style="color: #f59e0b;"></i> Defense Lifecycle</a>
</div>

<div class="auth-split-wrapper">
    <div class="auth-unified-card <?php echo $is_signup ? 'register-mode' : ''; ?>" id="authCard">
        
        <!-- Left: CSUCEIT Oral Defense Platform Branding Panel -->
        <div class="auth-card-info-side">
            <div class="auth-info-content">
                <div class="auth-logo-header">
                    <img src="logo.png" alt="CSUCEIT Logo">
                    <div class="auth-logo-text">
                        <h2>CSUCEIT</h2>
                        <p>College of Engineering &amp; Information Technology</p>
                    </div>
                </div>
                
                <div class="auth-ai-tag">
                    <i class="fa-solid fa-shield-halved"></i> CAPSTONE ORAL DEFENSE PLATFORM
                </div>
                
                <h1>Interactive Platform for <span>Capstone Project Oral Defense</span></h1>
                
                <p class="desc">Caraga State University CEIT's centralized academic portal for research adviser matching, pre-acceptance consultations, panel committee deliberations, and oral defense clearances.</p>
                
                <ul class="feature-list">
                    <li><i class="fa-solid fa-circle-check"></i> IT 198 &amp; IT 199 Oral Defense Calendars</li>
                    <li><i class="fa-solid fa-circle-check"></i> Pre-Acceptance Consultation &amp; Clearance</li>
                    <li><i class="fa-solid fa-circle-check"></i> Voice-Recorded Panel Feedback &amp; Rubrics</li>
                    <li><i class="fa-solid fa-circle-check"></i> Live Minutes of the Meeting (MOM) Records</li>
                    <li><i class="fa-solid fa-circle-check"></i> AI &amp; MCDA Weighted Faculty Matching</li>
                </ul>

                <div class="auth-info-badges-row">
                    <div class="auth-badge-tile">
                        <span class="num"><i class="fa-solid fa-graduation-cap"></i> IT 198 &bull; IT 199</span>
                        <span class="lbl">Oral Defense Sessions</span>
                    </div>
                    <div class="auth-badge-tile">
                        <span class="num"><i class="fa-solid fa-file-signature"></i> Live MOM</span>
                        <span class="lbl">Automated Deliberations</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right: Form Side -->
        <div class="auth-card-form-side">
            <?php if($error): ?>
                <div class="auth-alert auth-alert-error">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <?php if($success): ?>
                <div class="auth-alert auth-alert-success">
                    <i class="fa-solid fa-circle-check"></i>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
            <?php endif; ?>

            <?php if(isset($_GET['success'])): ?>
                <div class="auth-alert auth-alert-success">
                    <i class="fa-solid fa-circle-check"></i>
                    <span>Registration successful. Please sign in to continue.</span>
                </div>
            <?php endif; ?>

            <?php if (!empty($_SESSION['multi_login_accounts'])): ?>
            <!-- Multi-Role Selection Screen -->
            <div class="auth-form-container active" id="form-role-select">
                <div class="auth-card-header">
                    <h2 class="auth-card-title"><i class="fa-solid fa-users-viewfinder" style="color: #059669; font-size: 1.35rem;"></i> Select Academic Role</h2>
                    <p class="auth-card-subtitle">Multiple accounts detected for <strong><?php echo htmlspecialchars($_SESSION['multi_login_name'] ?? 'User'); ?></strong> (<?php echo htmlspecialchars($_SESSION['multi_login_email'] ?? ''); ?>). Choose which portal to enter:</p>
                </div>

                <div style="display: flex; flex-direction: column; gap: 0.85rem; margin: 1.5rem 0;">
                    <?php foreach ($_SESSION['multi_login_accounts'] as $acc): 
                        $r = $acc['role'];
                        $r_title = ucfirst($r);
                        $r_desc = '';
                        $r_icon = 'fa-user-tag';
                        $r_color = '#059669';
                        $r_bg = '#F0FDF4';
                        $r_border = '#BBF7D0';
                        
                        if ($r === 'adviser') {
                            $r_title = 'Faculty Adviser';
                            $r_desc = 'Advisee proposals, capstone matching & reviews';
                            $r_icon = 'fa-chalkboard-user';
                            $r_color = '#047857';
                            $r_bg = '#ECFDF5';
                            $r_border = '#A7F3D0';
                        } elseif ($r === 'panel') {
                            $r_title = 'Panel Committee';
                            $r_desc = 'Oral defense evaluation, scoring & rubrics';
                            $r_icon = 'fa-users';
                            $r_color = '#2563EB';
                            $r_bg = '#EFF6FF';
                            $r_border = '#BFDBFE';
                        } elseif ($r === 'student') {
                            $r_title = 'Student Researcher';
                            $r_desc = 'Capstone proposal submission & milestone tracking';
                            $r_icon = 'fa-graduation-cap';
                            $r_color = '#D97706';
                            $r_bg = '#FFFBEB';
                            $r_border = '#FDE68A';
                        } elseif ($r === 'secretary') {
                            $r_title = 'Defense Secretariat';
                            $r_desc = 'Defense schedules, minutes of meeting & archives';
                            $r_icon = 'fa-clipboard-user';
                            $r_color = '#7C3AED';
                            $r_bg = '#F5F3FF';
                            $r_border = '#DDD6FE';
                        }
                    ?>
                        <form action="login.php" method="POST" style="margin: 0;">
                            <input type="hidden" name="choose_role_login" value="1">
                            <input type="hidden" name="selected_user_id" value="<?php echo (int)$acc['id']; ?>">
                            <button type="submit" class="role-select-card-btn" style="width: 100%; display: flex; align-items: center; gap: 1rem; padding: 1.05rem 1.25rem; background: <?php echo $r_bg; ?>; border: 2px solid <?php echo $r_border; ?>; border-radius: 12px; cursor: pointer; text-align: left; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); font-family: inherit;">
                                <div style="width: 46px; height: 46px; border-radius: 10px; background: white; display: flex; align-items: center; justify-content: center; color: <?php echo $r_color; ?>; font-size: 1.25rem; box-shadow: 0 2px 6px rgba(0,0,0,0.06); flex-shrink: 0;">
                                    <i class="fa-solid <?php echo $r_icon; ?>"></i>
                                </div>
                                <div style="flex: 1; min-width: 0;">
                                    <div style="font-weight: 700; font-size: 1rem; color: #0F172A; display: flex; align-items: center; gap: 0.5rem;">
                                        <?php echo $r_title; ?>
                                        <span style="font-size: 0.68rem; font-weight: 700; text-transform: uppercase; padding: 0.15rem 0.5rem; border-radius: 999px; background: white; color: <?php echo $r_color; ?>; border: 1px solid <?php echo $r_border; ?>;">Enter Portal</span>
                                    </div>
                                    <div style="font-size: 0.78rem; color: #64748B; margin-top: 0.15rem;"><?php echo $r_desc; ?></div>
                                </div>
                                <div style="color: <?php echo $r_color; ?>; font-size: 1.1rem;">
                                    <i class="fa-solid fa-arrow-right"></i>
                                </div>
                            </button>
                        </form>
                    <?php endforeach; ?>
                </div>

                <form action="login.php" method="POST" style="margin-top: 1rem;">
                    <input type="hidden" name="cancel_role_choice" value="1">
                    <button type="submit" style="width: 100%; padding: 0.75rem; background: #F1F5F9; border: 1px solid #CBD5E1; color: #475569; border-radius: 8px; font-weight: 600; cursor: pointer; font-family: inherit; font-size: 0.85rem; display: flex; align-items: center; justify-content: center; gap: 0.4rem;">
                        <i class="fa-solid fa-arrow-left"></i> Sign in with another account
                    </button>
                </form>
            </div>
            <?php else: ?>

            <!-- Login Form -->
            <div class="auth-form-container <?php echo $is_signup ? '' : 'active'; ?>" id="form-login">
                <div class="auth-card-header">
                    <h2 class="auth-card-title"><i class="fa-solid fa-shield-halved" style="color: #059669; font-size: 1.35rem;"></i> Sign In</h2>
                    <p class="auth-card-subtitle">Enter your credentials to access your capstone matchmaking portal</p>
                </div>

                <form action="login.php" method="POST">
                    <input type="hidden" name="login" value="1">
                    
                    <div class="form-group">
                        <label class="input-label">Email Address <span>*</span></label>
                        <div class="input-with-icon">
                            <i class="fa-regular fa-envelope input-icon"></i>
                            <input type="email" name="email" placeholder="firstname.lastname@csucc.edu.ph" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="input-label">Password <span>*</span></label>
                        <div class="input-with-icon">
                            <i class="fa-solid fa-lock input-icon"></i>
                            <input type="password" name="password" id="login_pwd" placeholder="Enter password" required>
                            <i class="fa-regular fa-eye toggle-pwd" onclick="togglePwd('login_pwd', this)"></i>
                        </div>
                        <div class="forgot-pwd-wrap">
                            <a onclick="switchTab('forgot')" class="forgot-pwd-link">Forgot Password?</a>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-auth-submit"><i class="fa-solid fa-right-to-bracket"></i> Sign In to Portal</button>
                    
                    <div class="auth-footer">
                        Don't have an account? <a onclick="switchTab('register')">Register here</a>
                    </div>
                </form>
            </div>
            <?php endif; ?>
            
            <!-- Forgot Password Form -->
            <div class="auth-form-container <?php echo ($is_forgot && !$is_signup) ? 'active' : ''; ?>" id="form-forgot">
                <div class="auth-card-header">
                    <h2 class="auth-card-title"><i class="fa-solid fa-key" style="color: #059669; font-size: 1.25rem;"></i> Reset Password</h2>
                    <p class="auth-card-subtitle">Verify your email and set a new password to recover access</p>
                </div>

                <form action="login.php" method="POST" id="form-forgot-inner">
                    <input type="hidden" name="reset_password" value="1">
                    
                    <div class="form-group">
                        <label class="input-label">Registered Email Address <span>*</span></label>
                        <div class="input-with-icon">
                            <i class="fa-regular fa-envelope input-icon"></i>
                            <input type="email" name="reset_email" placeholder="firstname.lastname@csucc.edu.ph" required value="<?php echo htmlspecialchars($old_reset_email ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="input-label">New Password <span>*</span></label>
                        <div class="input-with-icon">
                            <i class="fa-solid fa-lock input-icon"></i>
                            <input type="password" name="new_password" id="reset_new_pwd" placeholder="Min. 8 chars" required minlength="8">
                            <i class="fa-regular fa-eye toggle-pwd" onclick="togglePwd('reset_new_pwd', this)"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="input-label">Confirm New Password <span>*</span></label>
                        <div class="input-with-icon">
                            <i class="fa-solid fa-lock input-icon"></i>
                            <input type="password" name="confirm_new_password" id="reset_confirm_pwd" placeholder="Confirm new password" required minlength="8">
                            <i class="fa-regular fa-eye toggle-pwd" onclick="togglePwd('reset_confirm_pwd', this)"></i>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-auth-submit"><i class="fa-solid fa-shield-check"></i> Reset Password & Sign In</button>
                    
                    <div class="auth-footer">
                        Remember your password? <a onclick="switchTab('login')">Back to Sign In</a>
                    </div>
                </form>
            </div>
            
            <!-- Register Form -->
            <div class="auth-form-container <?php echo $is_signup ? 'active' : ''; ?>" id="form-register">
                <div class="auth-card-header">
                    <h2 class="auth-card-title"><i class="fa-solid fa-user-plus" style="color: #059669; font-size: 1.25rem;"></i> Create Account</h2>
                    <p class="auth-card-subtitle">Register to get AI-matched with your capstone research adviser</p>
                </div>

                <form action="login.php" method="POST" class="grid-form" id="form-register-inner" novalidate>
                    <input type="hidden" name="register" value="1">
                    
                    <!-- Row 1: First Name, Last Name, Ext Name -->
                    <div class="form-row-names full-width">
                        <div class="form-group col-fname">
                            <label class="input-label" for="reg_firstname">First Name <span>*</span></label>
                            <div class="input-with-icon" id="wrap_firstname">
                                <i class="fa-regular fa-user input-icon"></i>
                                <input type="text" name="firstname" id="reg_firstname" placeholder="First Name" required value="<?php echo htmlspecialchars($old['firstname'] ?? ''); ?>">
                            </div>
                            <div class="field-feedback" id="fb_firstname"></div>
                        </div>
                    
                        <div class="form-group col-lname">
                            <label class="input-label" for="reg_lastname">Last Name <span>*</span></label>
                            <div class="input-with-icon" id="wrap_lastname">
                                <i class="fa-regular fa-user input-icon"></i>
                                <input type="text" name="lastname" id="reg_lastname" placeholder="Last Name" required value="<?php echo htmlspecialchars($old['lastname'] ?? ''); ?>">
                            </div>
                            <div class="field-feedback" id="fb_lastname"></div>
                        </div>

                        <div class="form-group col-ext">
                            <label class="input-label" for="reg_extension">Ext. <small style="font-weight:400; color:#64748b;">(Opt)</small></label>
                            <div class="input-with-icon" id="wrap_extension">
                                <input type="text" name="extension" id="reg_extension" placeholder="Jr., III" maxlength="10" value="<?php echo htmlspecialchars($old['extension'] ?? ''); ?>" style="padding-left: 0.65rem; text-align: center;">
                            </div>
                            <div class="field-feedback" id="fb_extension"></div>
                        </div>
                    </div>
                    
                    <!-- Row 2: Sex and Date of Birth -->
                    <div class="form-group col-sex">
                        <label class="input-label" for="reg_sex">Sex <span>*</span></label>
                        <div class="input-with-icon" id="wrap_sex">
                            <i class="fa-solid fa-venus-mars input-icon"></i>
                            <select name="sex" id="reg_sex" required>
                                <option value="" disabled <?php echo empty($old['sex']) ? 'selected' : ''; ?>>Select Sex</option>
                                <option value="Male" <?php echo (($old['sex'] ?? '') === 'Male') ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo (($old['sex'] ?? '') === 'Female') ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>
                        <div class="field-feedback" id="fb_sex"></div>
                    </div>

                    <div class="form-group col-dob">
                        <label class="input-label" for="reg_dob">Date of Birth <span>*</span></label>
                        <div class="input-with-icon" id="wrap_dob">
                            <i class="fa-regular fa-calendar-days input-icon"></i>
                            <input type="date" name="dob" id="reg_dob" required max="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($old['dob'] ?? ''); ?>">
                        </div>
                        <div class="field-feedback" id="fb_dob"></div>
                    </div>

                    <!-- Row 3: Phone Number and Email Address -->
                    <div class="form-group col-phone">
                        <label class="input-label" for="reg_phone">Phone Number <span>*</span></label>
                        <div class="input-with-icon" id="wrap_phone">
                            <i class="fa-solid fa-phone input-icon"></i>
                            <input type="tel" name="phone" id="reg_phone" placeholder="09123456789" required maxlength="13" value="<?php echo htmlspecialchars($old['phone'] ?? ''); ?>">
                        </div>
                        <div class="field-feedback" id="fb_phone"></div>
                    </div>

                    <div class="form-group col-email">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem;">
                            <label class="input-label" for="reg_email" style="margin-bottom: 0;">Email Address <span>*</span></label>
                            <button type="button" onclick="forceGenerateCsuccEmail()" style="background: none; border: none; color: #059669; font-size: 0.75rem; font-weight: 700; cursor: pointer; padding: 0; text-decoration: underline;" title="Auto-generate CSUCC email from First and Last Name">
                                <i class="fa-solid fa-wand-magic-sparkles"></i> Auto-fill from Name
                            </button>
                        </div>
                        <div class="input-with-icon" id="wrap_email">
                            <i class="fa-regular fa-envelope input-icon"></i>
                            <input type="email" name="email" id="reg_email" placeholder="name@csucc.edu.ph or name@gmail.com" required value="<?php echo htmlspecialchars($old['email'] ?? ''); ?>">
                        </div>
                        <div class="field-feedback" id="fb_email"></div>
                        <small style="display: block; font-size: 0.72rem; color: #047857; margin-top: 0.25rem; font-weight: 600;">
                            <i class="fa-solid fa-circle-check"></i> Accepted: <code>@csucc.edu.ph</code> or <code>@gmail.com</code>
                        </small>
                    </div>

                    <!-- Row 4: Home Address -->
                    <div class="form-group col-address full-width">
                        <label class="input-label" for="reg_address">Home Address <span>*</span></label>
                        <div class="input-with-icon" id="wrap_address">
                            <i class="fa-solid fa-location-dot input-icon"></i>
                            <input type="text" name="address" id="reg_address" placeholder="Street / Barangay / City / Municipality" required value="<?php echo htmlspecialchars($old['address'] ?? ''); ?>">
                        </div>
                        <div class="field-feedback" id="fb_address"></div>
                    </div>

                    <!-- Row 4: Academic Role and Department -->
                    <div class="form-group col-role">
                        <label class="input-label" for="role_select">Role <span>*</span></label>
                        <div class="input-with-icon" id="wrap_role">
                            <i class="fa-solid fa-users input-icon"></i>
                            <select name="role" id="role_select" required onchange="toggleRoleFields()">
                                <option value="" disabled <?php echo empty($old['role']) ? 'selected' : ''; ?>>Select Role</option>
                                <option value="student" <?php echo (($old['role'] ?? '') === 'student') ? 'selected' : ''; ?>>Student</option>
                                <option value="adviser" <?php echo (($old['role'] ?? '') === 'adviser') ? 'selected' : ''; ?>>Faculty Adviser</option>
                                <option value="panel" <?php echo (($old['role'] ?? '') === 'panel') ? 'selected' : ''; ?>>Panel Member</option>
                                <option value="secretary" <?php echo (($old['role'] ?? '') === 'secretary') ? 'selected' : ''; ?>>Defense / Department Secretary</option>
                            </select>
                        </div>
                        <div class="field-feedback" id="fb_role"></div>
                    </div>
                    
                    <div class="form-group col-dept">
                        <label class="input-label" for="reg_department">Department <span>*</span></label>
                        <div class="input-with-icon" id="wrap_dept">
                            <i class="fa-solid fa-building-columns input-icon"></i>
                            <select name="department" id="reg_department" required>
                                <option value="" disabled <?php echo empty($old['department']) ? 'selected' : ''; ?>>Select Department</option>
                                <option value="Information Technology" <?php echo (($old['department'] ?? '') === 'Information Technology') ? 'selected' : ''; ?>>Information Technology</option>
                                <option value="Computer Engineering (ComEng)" <?php echo in_array(($old['department'] ?? ''), ['Computer Engineering', 'Computer Engineering (ComEng)']) ? 'selected' : ''; ?>>Computer Engineering (ComEng)</option>
                                <option value="Electrical Engineering (EE)" <?php echo in_array(($old['department'] ?? ''), ['Electrical Engineering', 'Electrical Engineering (EE)']) ? 'selected' : ''; ?>>Electrical Engineering (EE)</option>
                            </select>
                        </div>
                        <div class="field-feedback" id="fb_dept"></div>
                    </div>

                    <!-- Student Specific Field: School ID (Required when Role is Student) -->
                    <div class="form-group full-width" id="student_fields" style="display: <?php echo (($old['role'] ?? '') === 'student') ? 'block' : 'none'; ?>;">
                        <label class="input-label" for="reg_school_id">School ID Number <span>*</span></label>
                        <div class="input-with-icon" id="wrap_school_id">
                            <i class="fa-solid fa-id-card input-icon"></i>
                            <input type="text" name="school_id" id="reg_school_id" placeholder="e.g. 2021-00123" maxlength="30" value="<?php echo htmlspecialchars($old['school_id'] ?? ''); ?>" autocomplete="off" <?php echo (($old['role'] ?? '') === 'student') ? 'required' : ''; ?>>
                        </div>
                        <div class="field-feedback" id="fb_school_id"></div>
                    </div>
                    
                    <!-- Row 5: Security (Password & Confirm Password) -->
                    <div class="form-group col-pwd">
                        <label class="input-label" for="reg_pwd">Password <span>*</span></label>
                        <div class="input-with-icon" id="wrap_pwd">
                            <i class="fa-solid fa-lock input-icon"></i>
                            <input type="password" name="password" id="reg_pwd" placeholder="Min. 8 chars" required minlength="8">
                            <i class="fa-regular fa-eye toggle-pwd" onclick="togglePwd('reg_pwd', this)"></i>
                        </div>
                        <!-- Password Strength Bar -->
                        <div class="pwd-strength-wrap" id="pwd_strength_wrap" style="display:none;">
                            <div class="pwd-strength-track">
                                <div class="pwd-strength-bar" id="pwd_strength_bar"></div>
                            </div>
                            <span class="pwd-strength-label" id="pwd_strength_label"></span>
                        </div>
                        <div class="field-feedback" id="fb_pwd"></div>
                    </div>
                    
                    <div class="form-group col-cpwd">
                        <label class="input-label" for="reg_cpwd">Confirm Password <span>*</span></label>
                        <div class="input-with-icon" id="wrap_cpwd">
                            <i class="fa-solid fa-lock input-icon"></i>
                            <input type="password" name="confirm_password" id="reg_cpwd" placeholder="Confirm password" required minlength="8">
                            <i class="fa-regular fa-eye toggle-pwd" onclick="togglePwd('reg_cpwd', this)"></i>
                        </div>
                        <div class="field-feedback" id="fb_cpwd"></div>
                    </div>
                    
                    <!-- Faculty Specific Fields (conditionally shown) -->
                    <div class="form-group full-width" id="faculty_fields" style="display: none;">
                        <div class="grid-form" style="margin-top: 0.35rem; border-top: 1px dashed #CBD5E1; padding-top: 0.65rem;">

                            <div class="form-group full-width">
                                <label class="input-label" for="reg_expertise">Field(s) of Expertise <span id="exp_star">*</span></label>
                                <div class="input-with-icon" id="wrap_expertise">
                                    <i class="fa-solid fa-tags input-icon"></i>
                                    <input type="text" name="expertise" id="reg_expertise" placeholder="e.g. AI, Machine Learning, Web & Mobile Dev" value="<?php echo htmlspecialchars($old['expertise'] ?? ''); ?>">
                                </div>
                                <div class="field-feedback" id="fb_expertise"></div>
                            </div>
                            
                            <div class="form-group adviser-only">
                                <label class="input-label" for="reg_capacity">Max Students (1-30) <span>*</span></label>
                                <div class="input-with-icon" id="wrap_capacity">
                                    <i class="fa-solid fa-users-line input-icon"></i>
                                    <input type="number" name="capacity" id="reg_capacity" min="1" max="30" placeholder="e.g. 5" value="<?php echo htmlspecialchars($old['capacity'] ?? '5'); ?>">
                                </div>
                                <div class="field-feedback" id="fb_capacity"></div>
                            </div>
                            
                            <div class="form-group adviser-only">
                                <label class="input-label" for="reg_schedule">Advising Schedule</label>
                                <div class="input-with-icon">
                                    <i class="fa-regular fa-clock input-icon"></i>
                                    <input type="text" name="schedule" id="reg_schedule" placeholder="e.g. MWF 1:00 PM - 3:00 PM" value="<?php echo htmlspecialchars($old['schedule'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group full-width">
                        <button type="submit" class="btn-auth-submit" id="btnSubmitRegister"><i class="fa-solid fa-user-plus"></i> Create Account</button>
                    </div>
                    
                    <div class="form-group full-width auth-footer">
                        Already have an account? <a onclick="switchTab('login')">Login here</a>
                    </div>
                </form>
            </div>
        </div> <!-- End auth-card-form-side -->
    </div> <!-- End auth-unified-card -->
</div> <!-- End auth-split-wrapper -->

<script>
function switchTab(tab) {
    // Update active forms
    const loginForm = document.getElementById('form-login');
    const registerForm = document.getElementById('form-register');
    const forgotForm = document.getElementById('form-forgot');
    
    if (loginForm) loginForm.classList.remove('active');
    if (registerForm) registerForm.classList.remove('active');
    if (forgotForm) forgotForm.classList.remove('active');
    
    const activeForm = document.getElementById('form-' + tab);
    if (activeForm) activeForm.classList.add('active');
    
    // Toggle card size
    const card = document.getElementById('authCard');
    if (tab === 'register') {
        if (card) card.classList.add('register-mode');
        window.history.pushState({}, '', '?signup=1');
    } else if (tab === 'forgot') {
        if (card) card.classList.remove('register-mode');
        window.history.pushState({}, '', '?forgot=1');
    } else {
        if (card) card.classList.remove('register-mode');
        window.history.pushState({}, '', 'login.php');
    }
}

function toggleRoleFields() {
    const roleSelect = document.getElementById('role_select');
    if (!roleSelect) return;
    const role = roleSelect.value;
    const facFields = document.getElementById('faculty_fields');
    const stuFields = document.getElementById('student_fields');
    const schoolIdInput = document.getElementById('reg_school_id');
    const adviserOnly = document.querySelectorAll('.adviser-only');
    
    if (role === 'student') {
        if (stuFields) stuFields.style.display = 'block';
        if (facFields) facFields.style.display = 'none';
        if (schoolIdInput) schoolIdInput.required = true;
    } else if (role === 'adviser' || role === 'panel') {
        if (stuFields) {
            stuFields.style.display = 'none';
            clearFieldStatus('wrap_school_id', 'fb_school_id');
        }
        if (schoolIdInput) schoolIdInput.required = false;
        if (facFields) {
            facFields.style.display = 'block';
            if (role === 'panel') {
                adviserOnly.forEach(el => el.style.display = 'none');
            } else {
                adviserOnly.forEach(el => el.style.display = 'block');
            }
        }
    } else {
        if (stuFields) stuFields.style.display = 'none';
        if (facFields) facFields.style.display = 'none';
        if (schoolIdInput) schoolIdInput.required = false;
        clearFieldStatus('wrap_school_id', 'fb_school_id');
    }
}

function toggleFacultyFields() {
    toggleRoleFields();
}

function togglePwd(id, icon) {
    const input = document.getElementById(id);
    if (input.type === "password") {
        input.type = "text";
        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash");
    } else {
        input.type = "password";
        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye");
    }
}

// ----------------------------------------------------
// Real-time Registration Input Validation
// ----------------------------------------------------
function setFieldStatus(wrapId, fbId, isValid, msg) {
    const wrap = document.getElementById(wrapId);
    const fb = document.getElementById(fbId);
    if (!wrap || !fb) return;
    
    if (isValid) {
        wrap.classList.remove('has-error');
        wrap.classList.add('has-success');
        fb.className = 'field-feedback success';
        fb.innerHTML = '<i class="fa-solid fa-circle-check"></i> ' + msg;
    } else {
        wrap.classList.remove('has-success');
        wrap.classList.add('has-error');
        fb.className = 'field-feedback error';
        fb.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + msg;
    }
}

function clearFieldStatus(wrapId, fbId) {
    const wrap = document.getElementById(wrapId);
    const fb = document.getElementById(fbId);
    if (!wrap || !fb) return;
    wrap.classList.remove('has-error', 'has-success');
    fb.className = 'field-feedback';
    fb.innerHTML = '';
}

// Attach live event listeners once DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    toggleRoleFields();

    const firstNameInput = document.getElementById('reg_firstname');
    const lastNameInput = document.getElementById('reg_lastname');
    const emailInput = document.getElementById('reg_email');
    const phoneInput = document.getElementById('reg_phone');
    const addressInput = document.getElementById('reg_address');
    const pwdInput = document.getElementById('reg_pwd');
    const cpwdInput = document.getElementById('reg_cpwd');
    const sexSelect = document.getElementById('reg_sex');
    const dobInput = document.getElementById('reg_dob');
    const roleSelect = document.getElementById('role_select');
    const deptSelect = document.getElementById('reg_department');
    const schoolIdInput = document.getElementById('reg_school_id');
    const expInput = document.getElementById('reg_expertise');
    const capInput = document.getElementById('reg_capacity');
    const regForm = document.getElementById('form-register-inner');

    // School ID Validator (for students)
    function validateSchoolId(input, wrapId, fbId) {
        const role = roleSelect ? roleSelect.value : '';
        if (role !== 'student') {
            clearFieldStatus(wrapId, fbId);
            return true;
        }
        const raw = input ? input.value : '';
        const val = raw.trim();

        if (val.length === 0) {
            setFieldStatus(wrapId, fbId, false, 'School ID is required for student registration.');
            return false;
        }
        if (val.length < 3) {
            setFieldStatus(wrapId, fbId, false, 'School ID is too short (min. 3 characters, e.g. 2021-00123).');
            return false;
        }
        if (val.length > 30) {
            setFieldStatus(wrapId, fbId, false, 'School ID is too long (maximum 30 characters).');
            return false;
        }
        if (!/^[A-Za-z0-9\-\s]+$/.test(val)) {
            setFieldStatus(wrapId, fbId, false, 'School ID may only contain letters, numbers, and hyphens.');
            return false;
        }
        setFieldStatus(wrapId, fbId, true, 'Valid School ID format.');
        return true;
    }

    if (schoolIdInput) {
        schoolIdInput.addEventListener('input', () => validateSchoolId(schoolIdInput, 'wrap_school_id', 'fb_school_id'));
        schoolIdInput.addEventListener('blur', () => validateSchoolId(schoolIdInput, 'wrap_school_id', 'fb_school_id'));
    }

    // Extension Validator
    const extInput = document.getElementById('reg_extension');
    function validateExtension(input, wrapId, fbId) {
        const val = input.value;
        const val = input.value.trim();
        if (val.length === 0) {
            clearFieldStatus(wrapId, fbId);
            return true; // optional field
        }
        if (val.length > 10) {
            setFieldStatus(wrapId, fbId, false, 'Extension must not exceed 10 characters.');
            return false;
        }
        if (!/^[IVXLCDMivxlcdm\.\s]+$/.test(val)) {
            setFieldStatus(wrapId, fbId, false, 'Only Roman numerals allowed (e.g. Jr, III, IV).');
        if (!/^(Jr\.?|Sr\.?|J|S|[IVXLCDM]+\.?)$/i.test(val)) {
            setFieldStatus(wrapId, fbId, false, 'Only valid suffixes allowed (e.g. Jr., Sr., III, IV).');
            return false;
        }
        setFieldStatus(wrapId, fbId, true, 'Valid extension.');
        return true;
    }
    if (extInput) {
        extInput.addEventListener('input', () => validateExtension(extInput, 'wrap_extension', 'fb_extension'));
        extInput.addEventListener('blur', () => validateExtension(extInput, 'wrap_extension', 'fb_extension'));
    }

    // Name Validators (comprehensive)
    function validateName(input, wrapId, fbId, fieldName) {
        const raw = input.value;
        const val = raw.trim();

        if (val.length === 0) {
            setFieldStatus(wrapId, fbId, false, fieldName + ' is required — enter at least 2 letters, starting with a capital (e.g. "Juan", "Maria").');
            return false;
        }
        if (val.length < 2) {
            setFieldStatus(wrapId, fbId, false, fieldName + ' is too short — must be at least 2 characters (e.g. "Jo" or "Ana").');
            return false;
        }
        if (val.length > 30) {
            setFieldStatus(wrapId, fbId, false, fieldName + ' is too long — maximum of 30 characters allowed.');
            return false;
        }
        if (raw.startsWith(' ')) {
            setFieldStatus(wrapId, fbId, false, 'Cannot start with a space.');
            return false;
        }
        if (raw.endsWith(' ')) {
            setFieldStatus(wrapId, fbId, false, 'Cannot end with a space.');
            return false;
        }
        if (/  /.test(raw)) {
            setFieldStatus(wrapId, fbId, false, 'No double spaces allowed.');
            return false;
        }
        if (/[0-9]/.test(val)) {
            setFieldStatus(wrapId, fbId, false, 'Numbers are not allowed.');
            return false;
        }
        if (/[^a-zA-Z\s\-\']/.test(val)) {
            setFieldStatus(wrapId, fbId, false, 'Special characters are not allowed.');
            return false;
        }
        if (/([a-zA-Z])\1\1/.test(val)) {
            setFieldStatus(wrapId, fbId, false, 'No three consecutive identical letters.');
            return false;
        }
        if (!/^[A-Z]/.test(val)) {
            setFieldStatus(wrapId, fbId, false, 'Must start with a capital letter.');
            return false;
        }
        if (/^[A-Z]+$/.test(val.replace(/[\s\-\']/g, ''))) {
            setFieldStatus(wrapId, fbId, false, 'All capital letters not allowed.');
            return false;
        }
        // Check mixed capitalization within each word
        const words = val.split(/[\s\-]+/);
        for (const word of words) {
            if (word.length > 1 && /[A-Z]/.test(word.slice(1))) {
                setFieldStatus(wrapId, fbId, false, 'No mixed capitalization within a word.');
                return false;
            }
        }
        setFieldStatus(wrapId, fbId, true, fieldName + ' looks good!');
        return true;
    }

    if (firstNameInput) {
        firstNameInput.addEventListener('input', () => validateName(firstNameInput, 'wrap_firstname', 'fb_firstname', 'First name'));
        firstNameInput.addEventListener('blur', () => validateName(firstNameInput, 'wrap_firstname', 'fb_firstname', 'First name'));
    }

    if (lastNameInput) {
        lastNameInput.addEventListener('input', () => validateName(lastNameInput, 'wrap_lastname', 'fb_lastname', 'Last name'));
        lastNameInput.addEventListener('blur', () => validateName(lastNameInput, 'wrap_lastname', 'fb_lastname', 'Last name'));
    }

    // Date of Birth Validator
    function validateDob(input, wrapId, fbId) {
        const val = input.value;
        if (!val) {
            setFieldStatus(wrapId, fbId, false, 'Date of birth is required.');
            return false;
        }
        const birthDate = new Date(val);
        const today = new Date();
        if (isNaN(birthDate.getTime())) {
            setFieldStatus(wrapId, fbId, false, 'Please enter a valid date of birth.');
            return false;
        }
        if (birthDate > today) {
            setFieldStatus(wrapId, fbId, false, 'Date of birth cannot be in the future.');
            return false;
        }
        let age = today.getFullYear() - birthDate.getFullYear();
        const m = today.getMonth() - birthDate.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
            age--;
        }
        if (age < 15) {
            setFieldStatus(wrapId, fbId, false, 'You must be at least 15 years old to register.');
            return false;
        }
        if (age > 100) {
            setFieldStatus(wrapId, fbId, false, 'Please enter a valid date of birth.');
            return false;
        }
        setFieldStatus(wrapId, fbId, true, 'Valid date of birth (' + age + ' yrs old).');
        return true;
    }

    if (dobInput) {
        dobInput.addEventListener('change', () => validateDob(dobInput, 'wrap_dob', 'fb_dob'));
        dobInput.addEventListener('blur', () => validateDob(dobInput, 'wrap_dob', 'fb_dob'));
        dobInput.addEventListener('input', () => {
            if (dobInput.value) validateDob(dobInput, 'wrap_dob', 'fb_dob');
        });
    }

    // CSUCC Institutional Email Helpers and Validator (firstname.lastname@csucc.edu.ph)
    function generateCsuccEmail(first, last) {
        const cleanFirst = (first || '').toLowerCase().trim()
            .replace(/\s+/g, '')
            .replace(/[^a-z0-9]/g, '');
        const cleanLast = (last || '').toLowerCase().trim()
            .replace(/\s+/g, '')
            .replace(/[^a-z0-9]/g, '');
        if (cleanFirst && cleanLast) {
            return `${cleanFirst}.${cleanLast}@csucc.edu.ph`;
        }
        return '';
    }

    let userManuallyEditedEmail = Boolean(emailInput && emailInput.value.trim().length > 0);

    function syncEmailFromNames() {
        if (!userManuallyEditedEmail && emailInput && firstNameInput && lastNameInput) {
            const auto = generateCsuccEmail(firstNameInput.value, lastNameInput.value);
            if (auto) {
                emailInput.value = auto;
                validateEmail(emailInput, 'wrap_email', 'fb_email');
            }
        }
    }

    window.forceGenerateCsuccEmail = function() {
        if (firstNameInput && lastNameInput && emailInput) {
            const auto = generateCsuccEmail(firstNameInput.value, lastNameInput.value);
            if (auto) {
                emailInput.value = auto;
                userManuallyEditedEmail = false;
                validateEmail(emailInput, 'wrap_email', 'fb_email');
            } else {
                setFieldStatus('wrap_email', 'fb_email', false, 'Please enter First Name and Last Name first.');
            }
        }
    };

    if (firstNameInput) {
        firstNameInput.addEventListener('input', syncEmailFromNames);
    }
    if (lastNameInput) {
        lastNameInput.addEventListener('input', syncEmailFromNames);
    }

    // Email Validator — allows CSUCC institutional email or Gmail
    function validateEmail(input, wrapId, fbId) {
        const val = input.value.trim().toLowerCase();
        if (val.length === 0) {
            setFieldStatus(wrapId, fbId, false, 'Email address is required.');
            return false;
        }
        const isAllowedDomain = val.endsWith('@csucc.edu.ph') || val.endsWith('@gmail.com') || val.endsWith('@csu.edu.ph') || val.endsWith('@ceit.edu');
        if (!isAllowedDomain) {
            setFieldStatus(wrapId, fbId, false, 'Must be an institutional email (@csucc.edu.ph) or Gmail (@gmail.com)');
            return false;
        }
        const emailRegex = /^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/i;
        if (!emailRegex.test(val)) {
            setFieldStatus(wrapId, fbId, false, 'Please enter a valid email address');
            return false;
        }
        setFieldStatus(wrapId, fbId, true, 'Valid email address');
        return true;
    }

    if (emailInput) {
        emailInput.addEventListener('input', () => {
            userManuallyEditedEmail = true;
            validateEmail(emailInput, 'wrap_email', 'fb_email');
        });
        emailInput.addEventListener('blur', () => validateEmail(emailInput, 'wrap_email', 'fb_email'));
    }

    // Phone Validator — exactly 11 numeric digits only
    function validatePhone(input, wrapId, fbId) {
        const raw = input.value;
        const digitsOnly = raw.replace(/\D/g, '');

        if (raw.length === 0) {
            setFieldStatus(wrapId, fbId, false, 'Phone number is required.');
            return false;
        }
        if (/\D/.test(raw)) {
            setFieldStatus(wrapId, fbId, false, 'Numbers only — no letters or special characters.');
            return false;
        }
        if (raw.length < 11) {
            setFieldStatus(wrapId, fbId, false, 'Phone number must be exactly 11 digits (' + raw.length + '/11).');
            return false;
        }
        if (raw.length > 11) {
            setFieldStatus(wrapId, fbId, false, 'Phone number must be exactly 11 digits (too many digits).');
            return false;
        }
        setFieldStatus(wrapId, fbId, true, 'Valid phone number.');
        return true;
    }

    if (phoneInput) {
        // Prevent non-numeric input
        phoneInput.addEventListener('keypress', function(e) {
            if (!/[0-9]/.test(e.key)) e.preventDefault();
        });
        phoneInput.addEventListener('input', () => {
            // Strip non-digits as they type
            const cleaned = phoneInput.value.replace(/\D/g, '').slice(0, 11);
            phoneInput.value = cleaned;
            if (cleaned.length > 0) {
                validatePhone(phoneInput, 'wrap_phone', 'fb_phone');
            } else {
                clearFieldStatus('wrap_phone', 'fb_phone');
            }
        });
        phoneInput.addEventListener('blur', () => validatePhone(phoneInput, 'wrap_phone', 'fb_phone'));
    }

    // Address Validator
    function validateAddress(input, wrapId, fbId) {
        const val = input.value.trim();
        if (val.length === 0) {
            setFieldStatus(wrapId, fbId, false, 'Home address is required (e.g. Street / Barangay / City / Municipality).');
            return false;
        } else if (val.length < 4) {
            setFieldStatus(wrapId, fbId, false, 'Address is too short (min. 4 characters, e.g. Street / Barangay / City / Municipality).');
            return false;
        } else {
            setFieldStatus(wrapId, fbId, true, 'Valid address.');
            return true;
        }
    }

    if (addressInput) {
        addressInput.addEventListener('input', () => {
            if (addressInput.value.trim().length > 0) {
                validateAddress(addressInput, 'wrap_address', 'fb_address');
            } else {
                clearFieldStatus('wrap_address', 'fb_address');
            }
        });
        addressInput.addEventListener('blur', () => validateAddress(addressInput, 'wrap_address', 'fb_address'));
    }

    // Password Strength Scorer
    function getPasswordStrength(pwd) {
        let score = 0;
        if (pwd.length >= 8)  score++;
        if (pwd.length >= 12) score++;
        if (/[A-Z]/.test(pwd)) score++;
        if (/[a-z]/.test(pwd)) score++;
        if (/[0-9]/.test(pwd)) score++;
        if (/[^A-Za-z0-9]/.test(pwd)) score++;

        if (score <= 2) return { level: 1, label: 'Weak' };
        if (score === 3) return { level: 2, label: 'Fair' };
        if (score <= 4) return { level: 3, label: 'Good' };
        return { level: 4, label: 'Strong' };
    }

    function updateStrengthBar(pwd) {
        const wrap  = document.getElementById('pwd_strength_wrap');
        const bar   = document.getElementById('pwd_strength_bar');
        const label = document.getElementById('pwd_strength_label');
        if (!wrap || !bar || !label) return;

        if (pwd.length === 0) {
            wrap.style.display = 'none';
            bar.removeAttribute('data-level');
            label.removeAttribute('data-level');
            label.textContent = '';
            return;
        }

        const s = getPasswordStrength(pwd);
        wrap.style.display = 'flex';
        bar.setAttribute('data-level', s.level);
        label.setAttribute('data-level', s.level);
        label.textContent = s.label;
    }

    // Password & Confirm Password Real-time Matching
    function validatePasswords() {
        const pwd = pwdInput.value;
        const cpwd = cpwdInput.value;
        let valid = true;

        // Update strength bar
        updateStrengthBar(pwd);

        // Check password length
        if (pwd.length === 0) {
            clearFieldStatus('wrap_pwd', 'fb_pwd');
            valid = false;
        } else if (pwd.length < 8) {
            setFieldStatus('wrap_pwd', 'fb_pwd', false, 'Password must be at least 8 characters long.');
            valid = false;
        } else {
            setFieldStatus('wrap_pwd', 'fb_pwd', true, 'Password meets minimum length.');
        }

        // Check confirm password match
        if (cpwd.length > 0 || pwd.length >= 8) {
            if (cpwd.length === 0) {
                setFieldStatus('wrap_cpwd', 'fb_cpwd', false, 'Please confirm your password.');
                valid = false;
            } else if (pwd !== cpwd) {
                setFieldStatus('wrap_cpwd', 'fb_cpwd', false, 'Passwords do not match.');
                valid = false;
            } else {
                setFieldStatus('wrap_cpwd', 'fb_cpwd', true, 'Passwords match perfectly!');
            }
        } else {
            clearFieldStatus('wrap_cpwd', 'fb_cpwd');
        }

        return valid;
    }

    if (pwdInput) {
        pwdInput.addEventListener('input', validatePasswords);
    }
    if (cpwdInput) {
        cpwdInput.addEventListener('input', validatePasswords);
    }

    // Role & Department Listeners
    if (sexSelect) {
        sexSelect.addEventListener('change', function() {
            if (sexSelect.value) {
                clearFieldStatus('wrap_sex', 'fb_sex');
            }
        });
    }

    if (roleSelect) {
        roleSelect.addEventListener('change', function() {
            toggleRoleFields();
            if (roleSelect.value) {
                clearFieldStatus('wrap_role', 'fb_role');
            }
            if (roleSelect.value === 'student' && schoolIdInput && schoolIdInput.value.trim().length > 0) {
                validateSchoolId(schoolIdInput, 'wrap_school_id', 'fb_school_id');
            }
        });
    }

    if (deptSelect) {
        deptSelect.addEventListener('change', function() {
            if (deptSelect.value) {
                clearFieldStatus('wrap_dept', 'fb_dept');
            }
        });
    }

    // On Form Submit Interception & Comprehensive Check
    if (regForm) {
        regForm.addEventListener('submit', function(e) {
            let hasError = false;
            let firstInvalidEl = null;

            function markError(el, wrapId, fbId, msg) {
                setFieldStatus(wrapId, fbId, false, msg);
                hasError = true;
                if (!firstInvalidEl) firstInvalidEl = el;
            }

            // Validate First Name
            if (!validateName(firstNameInput, 'wrap_firstname', 'fb_firstname', 'First name')) {
                hasError = true;
                if (!firstInvalidEl) firstInvalidEl = firstNameInput;
            }

            // Validate Last Name
            if (!validateName(lastNameInput, 'wrap_lastname', 'fb_lastname', 'Last name')) {
                hasError = true;
                if (!firstInvalidEl) firstInvalidEl = lastNameInput;
            }

            // Validate Sex
            if (!sexSelect.value) {
                markError(sexSelect, 'wrap_sex', 'fb_sex', 'Please select your sex.');
            }

            // Validate Date of Birth
            if (dobInput && !validateDob(dobInput, 'wrap_dob', 'fb_dob')) {
                hasError = true;
                if (!firstInvalidEl) firstInvalidEl = dobInput;
            }

            // Validate Email
            if (!validateEmail(emailInput, 'wrap_email', 'fb_email')) {
                hasError = true;
                if (!firstInvalidEl) firstInvalidEl = emailInput;
            }

            // Validate Extension (optional but must be Roman numeral if filled)
            if (extInput && extInput.value.trim()) {
                if (!validateExtension(extInput, 'wrap_extension', 'fb_extension')) {
                    hasError = true;
                    if (!firstInvalidEl) firstInvalidEl = extInput;
                }
            }

            // Validate Phone
            if (!validatePhone(phoneInput, 'wrap_phone', 'fb_phone')) {
                hasError = true;
                if (!firstInvalidEl) firstInvalidEl = phoneInput;
            }

            // Validate Address
            if (!validateAddress(addressInput, 'wrap_address', 'fb_address')) {
                hasError = true;
                if (!firstInvalidEl) firstInvalidEl = addressInput;
            }

            // Validate Passwords
            if (pwdInput.value.length < 8) {
                markError(pwdInput, 'wrap_pwd', 'fb_pwd', 'Password must be at least 8 characters.');
            }
            if (pwdInput.value !== cpwdInput.value) {
                markError(cpwdInput, 'wrap_cpwd', 'fb_cpwd', 'Passwords do not match.');
            }

            // Validate Role
            if (!roleSelect.value) {
                markError(roleSelect, 'wrap_role', 'fb_role', 'Please select a role.');
            }

            // Validate Department
            if (!deptSelect.value) {
                markError(deptSelect, 'wrap_dept', 'fb_dept', 'Please select an academic department.');
            }

            // Role specific checks
            const role = roleSelect.value;
            if (role === 'student') {
                if (schoolIdInput && !validateSchoolId(schoolIdInput, 'wrap_school_id', 'fb_school_id')) {
                    hasError = true;
                    if (!firstInvalidEl) firstInvalidEl = schoolIdInput;
                }
            } else if (role === 'adviser' || role === 'panel') {
                if (!expInput.value.trim()) {
                    markError(expInput, 'wrap_expertise', 'fb_expertise', 'Please specify your field(s) of expertise.');
                }
            }

            if (role === 'adviser') {
                const cap = parseInt(capInput.value, 10);
                if (isNaN(cap) || cap < 1 || cap > 30) {
                    markError(capInput, 'wrap_capacity', 'fb_capacity', 'Capacity must be between 1 and 30 students.');
                }
            }

            if (hasError) {
                e.preventDefault();
                if (firstInvalidEl) {
                    firstInvalidEl.focus();
                    firstInvalidEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });
    }

    // Auth Mobile Drawer Toggle
    const authMobileToggle = document.getElementById('authMobileToggle');
    const authMobileDrawer = document.getElementById('authMobileDrawer');
    if (authMobileToggle && authMobileDrawer) {
        authMobileToggle.addEventListener('click', function() {
            authMobileDrawer.classList.toggle('active');
        });
    }
});

function closeAuthMobileMenu() {
    const authMobileDrawer = document.getElementById('authMobileDrawer');
    if (authMobileDrawer) {
        authMobileDrawer.classList.remove('active');
    }
}
</script>

</body>
</html>
