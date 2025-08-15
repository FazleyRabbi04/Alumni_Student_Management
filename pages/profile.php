<?php
require_once '../config/database.php';
startSecureSession();
requireLogin();

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header('Location: ../pages/signin.php');
    exit();
}

/* ---------- helpers ---------- */
function isAjax(): bool {
    return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_SERVER['HTTP_ACCEPT']) && stripos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
}
function jsonOut($ok, $msg = '', $extra = []) {
    header('Content-Type: application/json');
    echo json_encode(array_merge(['ok' => $ok, 'message' => $msg], $extra));
    exit;
}

/* ---------- state ---------- */
$error = '';
$success = '';
$edit_mode = $_SESSION['edit_mode'] ?? false;

/* One-time auto prompt if coming from dashboard card (?prompt_edit=1) */
$auto_prompt_edit  = (isset($_GET['prompt_edit']) && !$edit_mode);
$show_verify_modal = false;

/* ---------- load user ---------- */
$user_info = getUserInfo($user_id);
if (!$user_info) {
    $error = 'Unable to load user profile.';
}

/* user type */
$user_type = '';
$year_info = '';
if ($user_info) {
    if (!empty($user_info['grad_year'])) {
        $user_type = 'Alumni';
        $year_info = $user_info['grad_year'];
    } elseif (!empty($user_info['batch_year'])) {
        $user_type = 'Student';
        $year_info = $user_info['batch_year'];
    }
}

/* ---------- handle post ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /* 1) enable edit (password gate) */
    if ($action === 'enable_edit') {
        $current_password = $_POST['verify_password'] ?? '';
        $user_stmt = executeQuery("SELECT password FROM person WHERE person_id = ?", [$user_id]);
        if ($user_stmt && $user_stmt->rowCount()) {
            $hash = $user_stmt->fetchColumn();
            if (password_verify($current_password, $hash)) {
                $_SESSION['edit_mode'] = true;
                if (isAjax()) jsonOut(true, 'Edit mode enabled');
                header('Location: profile.php'); // PRG; keep URL clean
                exit;
            }
        }
        $error = 'Incorrect password. Please try again.';
        $show_verify_modal = true;
        if (isAjax()) jsonOut(false, $error);
    }

    /* 2) update profile (auto-save; no buttons) */
    if ($action === 'update_profile') {
        $first_name   = trim($_POST['first_name'] ?? '');
        $last_name    = trim($_POST['last_name'] ?? '');
        $street       = trim($_POST['street'] ?? '');
        $city         = trim($_POST['city'] ?? '');
        $zip          = trim($_POST['zip'] ?? '');
        $gender       = $_POST['gender'] ?? '';
        $date_of_birth= $_POST['date_of_birth'] ?? '';
        $department   = trim($_POST['department'] ?? '');

        if ($first_name === '' || $last_name === '') {
            $msg = 'First name and last name are required.';
            if (isAjax()) jsonOut(false, $msg);
            $error = $msg;
        } else {
            try {
                $ok = executeQuery(
                    "UPDATE person
                     SET first_name=?, last_name=?, street=?, city=?, zip=?, gender=?, department=?, date_of_birth=?
                     WHERE person_id=?",
                    [$first_name, $last_name, $street, $city, $zip, $gender, $department, $date_of_birth, $user_id]
                );
                if ($ok) {
                    $_SESSION['user_name'] = $first_name.' '.$last_name;
                    if (isAjax()) jsonOut(true, 'Saved');
                    $success = 'Profile saved.';
                } else {
                    if (isAjax()) jsonOut(false, 'Nothing changed.');
                    $error = 'Nothing changed.';
                }
            } catch (Exception $e) {
                if (isAjax()) jsonOut(false, 'Error: '.$e->getMessage());
                $error = 'Error updating profile: '.$e->getMessage();
            }
        }
    }

    /* 3) add secondary email */
    if ($action === 'add_email') {
        $new_email = trim($_POST['new_email'] ?? '');
        if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $msg = 'Please provide a valid email address.';
            if (isAjax()) jsonOut(false, $msg);
            $error = $msg;
        } else {
            $exists = executeQuery("SELECT 1 FROM email_address WHERE email=?", [$new_email]);
            if ($exists && $exists->rowCount()) {
                $msg = 'This email is already in use.';
                if (isAjax()) jsonOut(false, $msg);
                $error = $msg;
            } else {
                $ok = executeQuery("INSERT INTO email_address (person_id, email) VALUES (?,?)", [$user_id, $new_email]);
                if ($ok) {
                    if (isAjax()) jsonOut(true, 'Email added');
                    $success = 'Email added successfully!';
                } else {
                    if (isAjax()) jsonOut(false, 'Failed to add email.');
                    $error = 'Failed to add email.';
                }
            }
        }
    }

    /* 4) add secondary phone */
    if ($action === 'add_phone') {
        $new_phone = trim($_POST['new_phone'] ?? '');
        if ($new_phone === '' || !preg_match('/^[0-9+\-\s]{10,15}$/', $new_phone)) {
            $msg = 'Please provide a valid phone number (10-15 digits, +, -, or spaces).';
            if (isAjax()) jsonOut(false, $msg);
            $error = $msg;
        } else {
            $exists = executeQuery("SELECT 1 FROM person_phone WHERE phone_number=?", [$new_phone]);
            if ($exists && $exists->rowCount()) {
                $msg = 'This phone number is already in use.';
                if (isAjax()) jsonOut(false, $msg);
                $error = $msg;
            } else {
                $ok = executeQuery("INSERT INTO person_phone (person_id, phone_number) VALUES (?,?)", [$user_id, $new_phone]);
                if ($ok) {
                    if (isAjax()) jsonOut(true, 'Phone added');
                    $success = 'Phone number added successfully!';
                } else {
                    if (isAjax()) jsonOut(false, 'Failed to add phone.');
                    $error = 'Failed to add phone number.';
                }
            }
        }
    }

    /* 5) education/employment/achievement (unchanged) */
    if ($action === 'add_education') {
        $degree = trim($_POST['degree'] ?? '');
        $institution = trim($_POST['institution'] ?? '');
        $start = $_POST['edu_start_date'] ?? '';
        $end   = $_POST['edu_end_date'] ?: null;
        if ($degree && $institution && $start) {
            $ok = executeQuery(
                "INSERT INTO education_history (person_id, degree, institution, start_date, end_date)
                 VALUES (?,?,?,?,?)",
                [$user_id, $degree, $institution, $start, $end]
            );
            if ($ok) { $success = 'Education record added successfully!'; if (isAjax()) jsonOut(true,'Added'); }
            else { $error='Failed to add education record.'; if (isAjax()) jsonOut(false,$error); }
        } else { $error='Please fill in all required education fields.'; if (isAjax()) jsonOut(false,$error); }
    }
    if ($action === 'add_employment') {
        $job = trim($_POST['job_title'] ?? '');
        $company = trim($_POST['company'] ?? '');
        $designation = trim($_POST['designation'] ?? '');
        $start = $_POST['emp_start_date'] ?? '';
        $end   = $_POST['emp_end_date'] ?: null;
        if ($job && $company && $start) {
            $ok = executeQuery(
                "INSERT INTO employment_history (person_id, job_title, company, designation, start_date, end_date)
                 VALUES (?,?,?,?,?,?)",
                [$user_id, $job, $company, $designation, $start, $end]
            );
            if ($ok) { $success = 'Employment record added successfully!'; if (isAjax()) jsonOut(true,'Added'); }
            else { $error='Failed to add employment record.'; if (isAjax()) jsonOut(false,$error); }
        } else { $error='Please fill in all required employment fields.'; if (isAjax()) jsonOut(false,$error); }
    }
    if ($action === 'add_achievement') {
        $title = trim($_POST['ach_title'] ?? '');
        $date  = $_POST['ach_date'] ?? '';
        $org   = trim($_POST['organization'] ?? '');
        $desc  = trim($_POST['description'] ?? '');
        $type  = $_POST['type'] ?? '';
        if ($title && $date) {
            $ok = executeQuery(
                "INSERT INTO achievement (person_id, ach_title, ach_date, organization, description, type)
                 VALUES (?,?,?,?,?,?)",
                [$user_id, $title, $date, $org, $desc, $type]
            );
            if ($ok) { $success='Achievement added successfully!'; if (isAjax()) jsonOut(true,'Added'); }
            else { $error='Failed to add achievement.'; if (isAjax()) jsonOut(false,$error); }
        } else { $error='Please fill in all required achievement fields.'; if (isAjax()) jsonOut(false,$error); }
    }

    /* 6) SKILLS (JSON in person_skill.skill) */
    if ($action === 'add_skill' || $action === 'update_skill' || $action === 'delete_skill') {
        // fetch current JSON
        $row = executeQuery("SELECT skill FROM person_skill WHERE person_id=?", [$user_id]);
        $skills_json = ($row && $row->rowCount()) ? $row->fetchColumn() : '[]';
        $skills = json_decode($skills_json, true);
        if (!is_array($skills)) $skills = [];

        if ($action === 'add_skill') {
            $name  = trim($_POST['skill_name'] ?? '');
            $level = (int)($_POST['skill_level'] ?? 70);
            if ($name === '') { if (isAjax()) jsonOut(false,'Skill name required'); $error='Skill name required.'; }
            else {
                // prevent duplicates by name (case-insensitive)
                $exists = false;
                foreach ($skills as $s) { if (strcasecmp($s['name'], $name) === 0) { $exists=true; break; } }
                if ($exists) { if (isAjax()) jsonOut(false,'Skill already exists'); $error='Skill already exists.'; }
                else {
                    $skills[] = ['name'=>$name,'level'=>max(0,min(100,$level))];
                    $ok = executeQuery(
                        "INSERT INTO person_skill (person_id, skill) VALUES (?,?)
                         ON DUPLICATE KEY UPDATE skill=VALUES(skill)",
                        [$user_id, json_encode($skills)]
                    );
                    if ($ok) { if (isAjax()) jsonOut(true,'Skill added'); $success='Skill added.'; }
                    else { if (isAjax()) jsonOut(false,'Failed to add skill'); $error='Failed to add skill.'; }
                }
            }
        }

        if ($action === 'update_skill') {
            $old = trim($_POST['old_name'] ?? '');
            $name = trim($_POST['skill_name'] ?? '');
            $level = (int)($_POST['skill_level'] ?? 70);
            $updated = false;
            foreach ($skills as &$s) {
                if (strcasecmp($s['name'], $old) === 0) {
                    $s['name'] = $name !== '' ? $name : $s['name'];
                    $s['level'] = max(0,min(100,$level));
                    $updated = true;
                    break;
                }
            }
            if ($updated) {
                $ok = executeQuery(
                    "INSERT INTO person_skill (person_id, skill) VALUES (?,?)
                     ON DUPLICATE KEY UPDATE skill=VALUES(skill)",
                    [$user_id, json_encode($skills)]
                );
                if ($ok) { if (isAjax()) jsonOut(true,'Skill updated'); $success='Skill updated.'; }
                else { if (isAjax()) jsonOut(false,'Failed to update'); $error='Failed to update skill.'; }
            } else {
                if (isAjax()) jsonOut(false,'Skill not found'); $error='Skill not found.';
            }
        }

        if ($action === 'delete_skill') {
            $name = trim($_POST['skill_name'] ?? '');
            $before = count($skills);
            $skills = array_values(array_filter($skills, fn($s) => strcasecmp($s['name'],$name)!==0));
            if (count($skills) === $before) {
                if (isAjax()) jsonOut(false,'Skill not found'); $error='Skill not found.';
            } else {
                $ok = executeQuery(
                    "INSERT INTO person_skill (person_id, skill) VALUES (?,?)
                     ON DUPLICATE KEY UPDATE skill=VALUES(skill)",
                    [$user_id, json_encode($skills)]
                );
                if ($ok) { if (isAjax()) jsonOut(true,'Skill removed'); $success='Skill removed.'; }
                else { if (isAjax()) jsonOut(false,'Failed to remove'); $error='Failed to remove skill.'; }
            }
        }
    }

    /* 7) INTERESTS (M2M tables, no level) */
    if ($action === 'add_interest' || $action === 'remove_interest') {
        $link_table = ($user_type === 'Alumni') ? 'alumni_interest' : 'student_interest';

        if ($action === 'add_interest') {
            $interest_id = (int)($_POST['interest_id'] ?? 0);
            $new_name    = trim($_POST['interest_name'] ?? '');

            if ($interest_id <= 0 && $new_name === '') {
                if (isAjax()) jsonOut(false,'Pick or enter an interest');
                $error = 'Pick or enter an interest.'; 
            } else {
                // If a name is provided, ensure it exists in interest table
                if ($new_name !== '') {
                    // insert ignore unique
                    executeQuery("INSERT INTO interest (interest_name) VALUES (?) ON DUPLICATE KEY UPDATE interest_name=VALUES(interest_name)", [$new_name]);
                    $get = executeQuery("SELECT interest_id FROM interest WHERE interest_name=?", [$new_name]);
                    $interest_id = (int)$get->fetchColumn();
                }
                // link (ignore duplicates)
                executeQuery("INSERT IGNORE INTO {$link_table} (person_id, interest_id) VALUES (?,?)", [$user_id, $interest_id]);
                if (isAjax()) jsonOut(true,'Interest added');
                $success = 'Interest added.';
            }
        } else { // remove_interest
            $interest_id = (int)($_POST['interest_id'] ?? 0);
            if ($interest_id > 0) {
                executeQuery("DELETE FROM {$link_table} WHERE person_id=? AND interest_id=?", [$user_id, $interest_id]);
                if (isAjax()) jsonOut(true,'Interest removed');
                $success = 'Interest removed.';
            } else {
                if (isAjax()) jsonOut(false,'Invalid interest');
                $error = 'Invalid interest.';
            }
        }
    }

    /* stop double posting on normal form posts */
    if (!isAjax()) {
        header('Location: profile.php');
        exit;
    }
}

/* ---------- cancel edit (from query) ---------- */
if (isset($_GET['cancel_edit'])) {
    unset($_SESSION['edit_mode']);
    $edit_mode = false;
}

/* ---------- load page data ---------- */
/* Emails & Phones */
$emails = [];
$phones = [];
if ($user_info) {
    $email_stmt = executeQuery("SELECT email FROM email_address WHERE person_id=?", [$user_id]);
    if ($email_stmt) $emails = $email_stmt->fetchAll(PDO::FETCH_COLUMN);
    $phone_stmt = executeQuery("SELECT phone_number FROM person_phone WHERE person_id=?", [$user_id]);
    if ($phone_stmt) $phones = $phone_stmt->fetchAll(PDO::FETCH_COLUMN);
}

/* Education, Employment, Achievements */
$education_history = [];
$employment_history = [];
$achievements = [];
if ($user_info) {
    $ed = executeQuery("SELECT * FROM education_history WHERE person_id=? ORDER BY start_date DESC", [$user_id]);
    if ($ed) $education_history = $ed->fetchAll(PDO::FETCH_ASSOC);
    $em = executeQuery("SELECT * FROM employment_history WHERE person_id=? ORDER BY start_date DESC", [$user_id]);
    if ($em) $employment_history = $em->fetchAll(PDO::FETCH_ASSOC);
    $ac = executeQuery("SELECT * FROM achievement WHERE person_id=? ORDER BY ach_date DESC", [$user_id]);
    if ($ac) $achievements = $ac->fetchAll(PDO::FETCH_ASSOC);
}

/* Skills JSON */
$skills = [];
$skills_row = executeQuery("SELECT skill FROM person_skill WHERE person_id=?", [$user_id]);
if ($skills_row && $skills_row->rowCount()) {
    $skills = json_decode($skills_row->fetchColumn(), true);
    if (!is_array($skills)) $skills = [];
}

/* Interests list + user interests */
$all_interests = [];
$int_stmt = executeQuery("SELECT interest_id, interest_name FROM interest ORDER BY interest_name ASC");
if ($int_stmt) $all_interests = $int_stmt->fetchAll(PDO::FETCH_ASSOC);

$link_table = ($user_type === 'Alumni') ? 'alumni_interest' : 'student_interest';
$user_interests = [];
$ui_stmt = executeQuery(
    "SELECT i.interest_id, i.interest_name
     FROM {$link_table} li
     JOIN interest i ON i.interest_id = li.interest_id
     WHERE li.person_id=?
     ORDER BY i.interest_name ASC",
    [$user_id]
);
if ($ui_stmt) $user_interests = $ui_stmt->fetchAll(PDO::FETCH_ASSOC);

/* modal auto open logic */
if (!$edit_mode) {
    $show_verify_modal = $show_verify_modal || $auto_prompt_edit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Profile - Alumni Relationship & Networking System</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;700&display=swap" rel="stylesheet">
<style>
    body{font-family:'Open Sans',sans-serif;background:#faf5f6;color:#002147;}
    .profile-header{background:linear-gradient(to right,#002147,#0077c8);color:#fff;padding:2rem 0;}
    .profile-avatar{width:120px;height:120px;border-radius:50%;border:4px solid #fff;background:#fff;display:flex;align-items:center;justify-content:center;font-size:3rem;color:#002147;margin:0 auto 1rem;}
    .card{border:none;border-radius:15px;box-shadow:0 2px 10px rgba(0,0,0,.1);margin-bottom:1.5rem;}
    .card-header{background:linear-gradient(to right,#002147,#0077c8);color:#fff;border-radius:15px 15px 0 0;padding:1rem 1.5rem;}
    .btn-edit{background:#002147;border:none;color:#fff;border-radius:25px;padding:.5rem 1.5rem}
    .btn-edit:hover{background:#002147;color:#fff;}
    .readonly-field{background:#e9ecef;cursor:not-allowed;}
    .edit-section{border:2px dashed #002147;border-radius:10px;padding:1rem;margin-top:1rem;background:#f8f9ff;}
    .action-link{color:#003087;font-weight:700;text-decoration:underline;}
    .action-link:hover{color:#002147;text-decoration:underline;}
    /* progress bar labels */
    .skill-row{display:flex;align-items:center;gap:12px;margin-bottom:10px}
    .skill-name{min-width:140px;font-weight:600}
    .tag{display:inline-flex;align-items:center;gap:8px;background:#eef2ff;border:1px solid #c7d2fe;color:#1e40af;border-radius:999px;padding:.35rem .6rem;margin:.2rem .3rem;font-weight:600}
    .tag .x{cursor:pointer;color:#1e40af;}
    .save-badge{display:none; margin-left:10px;}
    /* Force all card header titles and icons to render white */
        .card-header h1,
        .card-header h2,
        .card-header h3,
        .card-header h4,
        .card-header h5,
        .card-header h6,
        .card-header i {
        color: #fff !important;
        }

</style>
</head>
<body>

<?php include '../includes/navbar.php'; ?>

<!-- Header -->
<div class="profile-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-3 text-center">
                <div class="profile-avatar"><i class="fas fa-user"></i></div>
            </div>
            <div class="col-md-6">
                <h2><?= htmlspecialchars(($user_info['first_name'] ?? '').' '.($user_info['last_name'] ?? '')) ?></h2>
                <p class="mb-1"><i class="fas fa-graduation-cap me-2"></i><?= htmlspecialchars($user_type.($year_info?(' - '.$year_info):'')) ?></p>
                <p class="mb-1"><i class="fas fa-building me-2"></i><?= htmlspecialchars($user_info['department'] ?? ''); ?></p>
                <p class="mb-0"><i class="fas fa-map-marker-alt me-2"></i><?= htmlspecialchars($user_info['city'] ?? ''); ?></p>
            </div>
            <div class="col-md-3 text-end">
                <?php if (!$edit_mode): ?>
                    <button class="btn btn-edit" data-bs-toggle="modal" data-bs-target="#verifyModal">
                        <i class="fas fa-edit me-2"></i>Edit Profile
                    </button>
                <?php else: ?>
                    <span class="badge bg-success fs-6"><i class="fas fa-check me-1"></i>Edit Mode Active</span>
                    <a href="profile.php?cancel_edit=1" class="btn btn-outline-light mt-2">
                        <i class="fas fa-times-circle me-1"></i>Save Changes
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="container mt-4">
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Left -->
        <div class="col-lg-8">
            <!-- Personal Information -->
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="mb-0"><i class="fas fa-user me-2"></i>Personal Information</h5>
                    <?php if ($edit_mode): ?>
                        <div>
                            <button class="btn btn-sm btn-light me-2" data-bs-toggle="modal" data-bs-target="#addEmailModal">
                                <i class="fas fa-plus me-1"></i>Add Email
                            </button>
                            <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#addPhoneModal">
                                <i class="fas fa-plus me-1"></i>Add Phone
                            </button>
                            <span class="badge bg-success save-badge" id="piSaved">Saved</span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if ($edit_mode): ?>
                        <!-- No Save/Cancel buttons; auto-save on change -->
                        <form id="profileForm" class="profile-form">
                            <input type="hidden" name="action" value="update_profile">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">First Name *</label>
                                    <input type="text" class="form-control" name="first_name" value="<?= htmlspecialchars($user_info['first_name'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Last Name *</label>
                                    <input type="text" class="form-control" name="last_name" value="<?= htmlspecialchars($user_info['last_name'] ?? '') ?>" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Gender</label>
                                    <select class="form-select" name="gender" required>
                                        <?php $g=$user_info['gender']??''; ?>
                                        <option value="Male"   <?= $g==='Male'?'selected':''?>>Male</option>
                                        <option value="Female" <?= $g==='Female'?'selected':''?>>Female</option>
                                        <option value="Other"  <?= $g==='Other'?'selected':''?>>Other</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Date of Birth</label>
                                    <input type="date" class="form-control" name="date_of_birth" value="<?= htmlspecialchars($user_info['date_of_birth'] ?? '') ?>" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Street</label>
                                    <input type="text" class="form-control" name="street" value="<?= htmlspecialchars($user_info['street'] ?? '') ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">City</label>
                                    <input type="text" class="form-control" name="city" value="<?= htmlspecialchars($user_info['city'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Zip Code</label>
                                    <input type="text" class="form-control" name="zip" value="<?= htmlspecialchars($user_info['zip'] ?? '') ?>">
                                </div>
                                <div class="col-md-6 mb-1">
                                    <label class="form-label">Department</label>
                                    <?php $dept=$user_info['department']??''; ?>
                                    <select class="form-select" name="department" required>
                                        <option value="">Select Department</option>
                                        <?php
                                        $departments = [
                                            'Bachelor of Architecture',
                                            'BS in Civil & Environmental Engineering (CEE)',
                                            'BS in Computer Science & Engineering (CSE)',
                                            'BS in Electrical & Electronic Engineering (EEE)',
                                            'BS in Electronic & Telecom Engineering (ETE)',
                                            'BS in Biochemistry and Biotechnology',
                                            'BS in Environmental Science & Management',
                                            'BS in Microbiology',
                                            'BPharm Professional',
                                            'BBA Major in Accounting',
                                            'BBA Major in Economics',
                                            'BBA Major in Entrepreneurship',
                                            'BBA Major in Finance',
                                            'BBA Major in Human Resource Management',
                                            'BBA Major in International Business',
                                            'BBA Major in Management',
                                            'BBA Major in Management Information Systems',
                                            'BBA Major in Marketing',
                                            'BBA Major in Supply Chain Management',
                                            'BBA General',
                                            'BS in Economics',
                                            'BA in English',
                                            'Bachelor of Laws (LLB Hons)',
                                            'BSS in Media and Journalism (MAJ)'
                                        ];
                                        foreach ($departments as $d) {
                                            $sel = ($dept === $d) ? 'selected' : '';
                                            echo "<option value=\"".htmlspecialchars($d)."\" $sel>".htmlspecialchars($d)."</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <!-- No buttons here on purpose -->
                        </form>
                    <?php else: ?>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Student ID</label>
                                <input class="form-control readonly-field" value="<?= htmlspecialchars($user_info['student_id'] ?? 'N/A') ?>" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Gender</label>
                                <input class="form-control readonly-field" value="<?= htmlspecialchars($user_info['gender'] ?? 'N/A') ?>" readonly>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Date of Birth</label>
                                <input class="form-control readonly-field" value="<?= htmlspecialchars($user_info['date_of_birth'] ?? 'N/A') ?>" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Department</label>
                                <input class="form-control readonly-field" value="<?= htmlspecialchars($user_info['department'] ?? 'N/A') ?>" readonly>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Street</label>
                                <input class="form-control readonly-field" value="<?= htmlspecialchars($user_info['street'] ?? 'N/A') ?>" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">City</label>
                                <input class="form-control readonly-field" value="<?= htmlspecialchars($user_info['city'] ?? 'N/A') ?>" readonly>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Zip Code</label>
                                <input class="form-control readonly-field" value="<?= htmlspecialchars($user_info['zip'] ?? 'N/A') ?>" readonly>
                            </div>
                        </div>
                        <h6>Contact Information</h6>
                        <ul class="list-unstyled">
                            <?php foreach ($emails as $em): ?>
                                <li><i class="fas fa-envelope me-2"></i><?= htmlspecialchars($em) ?></li>
                            <?php endforeach; ?>
                            <?php foreach ($phones as $ph): ?>
                                <li><i class="fas fa-phone me-2"></i><?= htmlspecialchars($ph) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Skills -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-sliders me-2"></i>Skills</h5>
                    <?php if ($edit_mode): ?>
                        <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#skillModal" data-mode="add">
                            <i class="fas fa-plus me-1"></i>Add Skill
                        </button>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (empty($skills)): ?>
                        <p class="text-muted text-center py-3">No skills added yet.</p>
                    <?php else: ?>
                        <?php foreach ($skills as $s): ?>
                            <div class="skill-row">
                                <div class="skill-name"><?= htmlspecialchars($s['name']) ?></div>
                                <div class="flex-grow-1">
                                    <div class="progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int)$s['level'] ?>">
                                        <div class="progress-bar" style="width: <?= (int)$s['level'] ?>%;">
                                            <?= (int)$s['level'] ?>%
                                        </div>
                                    </div>
                                </div>
                                <?php if ($edit_mode): ?>
                                    <button class="btn btn-sm btn-outline-primary ms-2"
                                            data-bs-toggle="modal" data-bs-target="#skillModal"
                                            data-mode="edit"
                                            data-name="<?= htmlspecialchars($s['name']) ?>"
                                            data-level="<?= (int)$s['level'] ?>">
                                        Edit
                                    </button>
                                    <form method="post" class="ms-2 d-inline-block skill-del-form" onsubmit="return confirm('Remove this skill?');">
                                        <input type="hidden" name="action" value="delete_skill">
                                        <input type="hidden" name="skill_name" value="<?= htmlspecialchars($s['name']) ?>">
                                        <button class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Interests -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-heart me-2"></i>Interests</h5>
                    <?php if ($edit_mode): ?>
                        <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#interestModal">
                            <i class="fas fa-plus me-1"></i>Add Interest
                        </button>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (empty($user_interests)): ?>
                        <p class="text-muted text-center py-3">No interests added yet.</p>
                    <?php else: ?>
                        <?php foreach ($user_interests as $it): ?>
                            <span class="tag">
                                <i class="fas fa-star-of-life"></i><?= htmlspecialchars($it['interest_name']) ?>
                                <?php if ($edit_mode): ?>
                                    <form method="post" class="d-inline remove-interest-form">
                                        <input type="hidden" name="action" value="remove_interest">
                                        <input type="hidden" name="interest_id" value="<?= (int)$it['interest_id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-link p-0 x" title="Remove">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Education -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-graduation-cap me-2"></i>Education History</h5>
                    <?php if ($edit_mode): ?>
                        <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#addEducationModal">
                            <i class="fas fa-plus me-1"></i>Add Education
                        </button>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (empty($education_history)): ?>
                        <p class="text-muted text-center py-3">No education history added yet.</p>
                    <?php else: ?>
                        <?php foreach ($education_history as $edu): ?>
                            <div class="border-start border-primary border-3 ps-3 mb-3">
                                <h6 class="mb-1"><?= htmlspecialchars($edu['degree']) ?></h6>
                                <p class="text-primary mb-1"><?= htmlspecialchars($edu['institution']) ?></p>
                                <small class="text-muted">
                                    <?= date('M Y', strtotime($edu['start_date'])) ?> -
                                    <?= $edu['end_date'] ? date('M Y', strtotime($edu['end_date'])) : 'Present' ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Employment -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-briefcase me-2"></i>Employment History</h5>
                    <?php if ($edit_mode): ?>
                        <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#addEmploymentModal">
                            <i class="fas fa-plus me-1"></i>Add Employment
                        </button>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (empty($employment_history)): ?>
                        <p class="text-muted text-center py-3">No employment history added yet.</p>
                    <?php else: ?>
                        <?php foreach ($employment_history as $emp): ?>
                            <div class="border-start border-success border-3 ps-3 mb-3">
                                <h6 class="mb-1"><?= htmlspecialchars($emp['job_title']) ?></h6>
                                <p class="text-success mb-1"><?= htmlspecialchars($emp['company']) ?></p>
                                <?php if ($emp['designation']): ?>
                                    <p class="mb-1 small"><?= htmlspecialchars($emp['designation']) ?></p>
                                <?php endif; ?>
                                <small class="text-muted">
                                    <?= date('M Y', strtotime($emp['start_date'])) ?> -
                                    <?= $emp['end_date'] ? date('M Y', strtotime($emp['end_date'])) : 'Present' ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Achievements -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-trophy me-2"></i>Achievements</h5>
                    <?php if ($edit_mode): ?>
                        <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#addAchievementModal">
                            <i class="fas fa-plus me-1"></i>Add Achievement
                        </button>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (empty($achievements)): ?>
                        <p class="text-muted text-center py-3">No achievements added yet.</p>
                    <?php else: ?>
                        <?php foreach ($achievements as $a): ?>
                            <div class="border-start border-warning border-3 ps-3 mb-3">
                                <h6 class="mb-1"><?= htmlspecialchars($a['ach_title']) ?></h6>
                                <?php if ($a['organization']): ?>
                                    <p class="text-warning mb-1"><?= htmlspecialchars($a['organization']) ?></p>
                                <?php endif; ?>
                                <?php if ($a['description']): ?>
                                    <p class="mb-1 small"><?= htmlspecialchars($a['description']) ?></p>
                                <?php endif; ?>
                                <small class="text-muted"><?= date('F j, Y', strtotime($a['ach_date'])) ?><?= $a['type'] ? ' | '.htmlspecialchars($a['type']) : '' ?></small>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- Right -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Profile Stats</h5></div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2"><span>Education Records:</span><span class="badge bg-primary"><?= count($education_history) ?></span></div>
                    <div class="d-flex justify-content-between mb-2"><span>Employment History:</span><span class="badge bg-success"><?= count($employment_history) ?></span></div>
                    <div class="d-flex justify-content-between mb-2"><span>Achievements:</span><span class="badge bg-warning"><?= count($achievements) ?></span></div>
                    <div class="d-flex justify-content-between mb-2"><span>Skills:</span><span class="badge bg-info"><?= count($skills) ?></span></div>
                    <div class="d-flex justify-content-between mb-2"><span>Interests:</span><span class="badge bg-info"><?= count($user_interests) ?></span></div>
                    <hr>
                    <small class="text-muted"><i class="fas fa-info-circle me-1"></i>Complete your profile to connect with more alumni and students.</small>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h5 class="mb-0"><i class="fas fa-lock me-2"></i>Security</h5></div>
                <div class="card-body">
                    <p class="small text-muted mb-3">Your email, phone, and Student ID are protected and cannot be changed for security reasons.</p>
                    <a href="change_password.php" class="btn btn-outline-primary btn-sm w-100 action-link">
                        <i class="fas fa-key me-2"></i>Change Password
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Verify Modal -->
<div class="modal fade" id="verifyModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="post">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-shield-alt me-2"></i>Verify Your Identity</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted">Enter your password to enable edit mode.</p>
        <input type="hidden" name="action" value="enable_edit">
        <div class="mb-3">
            <label class="form-label">Current Password</label>
            <input type="password" class="form-control" name="verify_password" required>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Cancel</button>
        <button class="btn btn-primary" type="submit">Verify & Edit</button>
      </div>
    </form>
  </div>
</div>

<!-- Add Email Modal -->
<div class="modal fade" id="addEmailModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="post">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-envelope me-2"></i>Add Secondary Email</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action" value="add_email">
        <div class="mb-3">
            <label class="form-label">Email Address *</label>
            <input type="email" class="form-control" name="new_email" required placeholder="e.g., example@domain.com">
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Cancel</button>
        <button class="btn btn-primary" type="submit">Add Email</button>
      </div>
    </form>
  </div>
</div>

<!-- Add Phone Modal -->
<div class="modal fade" id="addPhoneModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="post">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-phone me-2"></i>Add Secondary Phone</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action" value="add_phone">
        <div class="mb-3">
            <label class="form-label">Phone Number *</label>
            <input type="text" class="form-control" name="new_phone" required placeholder="e.g., +1234567890">
            <small class="text-muted">Use 10-15 digits, may include +, -, or spaces</small>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Cancel</button>
        <button class="btn btn-primary" type="submit">Add Phone</button>
      </div>
    </form>
  </div>
</div>

<!-- Skill Modal -->
<div class="modal fade" id="skillModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="post" id="skillForm">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-sliders me-2"></i><span id="skillMode">Add</span> Skill</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action" value="add_skill">
        <input type="hidden" name="old_name" value="">
        <div class="mb-3">
            <label class="form-label">Skill Name *</label>
            <input type="text" class="form-control" name="skill_name" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Proficiency: <span id="lvlVal">70</span>%</label>
            <input type="range" class="form-range" name="skill_level" min="0" max="100" value="70" oninput="document.getElementById('lvlVal').textContent=this.value">
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Cancel</button>
        <button class="btn btn-primary" type="submit">Save Skill</button>
      </div>
    </form>
  </div>
</div>

<!-- Add Education Modal -->
<div class="modal fade" id="addEducationModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="post">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-graduation-cap me-2"></i>Add Education</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action" value="add_education">
        <div class="mb-3">
            <label class="form-label">Degree *</label>
            <input type="text" class="form-control" name="degree" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Institution *</label>
            <input type="text" class="form-control" name="institution" required>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Start Date *</label>
                <input type="date" class="form-control" name="edu_start_date" id="edu_start_date">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">End Date</label>
                <input type="date" class="form-control" name="edu_end_date" id="edu_end_date">
                <small class="text-muted">Leave blank if currently studying</small>
            </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Cancel</button>
        <button class="btn btn-primary" type="submit">Add Education</button>
      </div>
    </form>
  </div>
</div>

<!-- Add Employment Modal -->
<div class="modal fade" id="addEmploymentModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="post">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-briefcase me-2"></i>Add Employment</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action" value="add_employment">
        <div class="mb-3">
            <label class="form-label">Job Title *</label>
            <input type="text" class="form-control" name="job_title" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Company *</label>
            <input type="text" class="form-control" name="company" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Designation</label>
            <input type="text" class="form-control" name="designation">
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Start Date *</label>
                <input type="date" class="form-control" name="emp_start_date" id="emp_start_date">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">End Date</label>
                <input type="date" class="form-control" name="emp_end_date" id="emp_end_date">
                <small class="text-muted">Leave blank if currently working</small>
            </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Cancel</button>
        <button class="btn btn-primary" type="submit">Add Employment</button>
      </div>
    </form>
  </div>
</div>

<!-- Add Achievement Modal -->
<div class="modal fade" id="addAchievementModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="post">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-trophy me-2"></i>Add Achievement</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action" value="add_achievement">
        <div class="mb-3">
            <label class="form-label">Achievement Title *</label>
            <input type="text" class="form-control" name="ach_title" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Achievement Date *</label>
            <input type="date" class="form-control" name="ach_date" id="ach_date">
        </div>
        <div class="mb-3">
            <label class="form-label">Organization</label>
            <input type="text" class="form-control" name="organization">
        </div>
        <div class="mb-3">
            <label class="form-label">Type</label>
            <select class="form-select" name="type">
                <option value="">Select Type</option>
                <option value="Academic">Academic</option>
                <option value="Professional">Professional</option>
                <option value="Research">Research</option>
                <option value="Sports">Sports</option>
                <option value="Cultural">Cultural</option>
                <option value="Community Service">Community Service</option>
                <option value="Other">Other</option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea class="form-control" name="description" rows="3"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Cancel</button>
        <button class="btn btn-primary" type="submit">Add Achievement</button>
      </div>
    </form>
  </div>
</div>

<!-- Interest Modal -->
<div class="modal fade" id="interestModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="post">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-heart me-2"></i>Add Interest</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action" value="add_interest">
        <div class="mb-3">
            <label class="form-label">Choose Existing</label>
            <select class="form-select" name="interest_id">
                <option value="">-- Select --</option>
                <?php foreach ($all_interests as $i): ?>
                    <option value="<?= (int)$i['interest_id'] ?>"><?= htmlspecialchars($i['interest_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="text-center my-2">or</div>
        <div class="mb-3">
            <label class="form-label">Create New</label>
            <input type="text" class="form-control" name="interest_name" placeholder="e.g., AI & ML">
            <small class="text-muted">New interest will be added to the global list.</small>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Cancel</button>
        <button class="btn btn-primary" type="submit">Add Interest</button>
      </div>
    </form>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ------- Dashboard → Profile: one-time modal + nice Back behavior ------- */
document.addEventListener('DOMContentLoaded', () => {
  const shouldPrompt = <?= ($show_verify_modal ? 'true' : 'false') ?>;
  const editMode     = <?= ($edit_mode ? 'true' : 'false') ?>;

  // If we arrived with ?prompt_edit=1, make it one-time by stripping the query and push a dummy state
  <?php if ($auto_prompt_edit): ?>
    history.replaceState({stage:'profile'}, '', 'profile.php');
    history.pushState({stage:'profile-edit'}, '', '#edit');
  <?php endif; ?>

  if (shouldPrompt) {
    const el = document.getElementById('verifyModal');
    if (el) {
      const modal = new bootstrap.Modal(el);
      modal.show();
      setTimeout(() => {
        const input = el.querySelector('input[name="verify_password"]');
        if (input) input.focus({preventScroll:true});
      }, 150);
    }
  }

  // If user is in edit mode and presses Back, stay on profile but exit edit server-side
  window.addEventListener('popstate', () => {
    if (editMode) {
      location.replace('profile.php?cancel_edit=1#view');
    }
  });
});

/* ------- Auto-save Personal Info (no buttons) ------- */
(function(){
  const form = document.getElementById('profileForm');
  if (!form) return;
  let timer = null;
  const savedBadge = document.getElementById('piSaved');

  const send = () => {
    const fd = new FormData(form);
    fetch('profile.php', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body: fd })
      .then(r => r.json()).then(j => {
        if (savedBadge) {
          savedBadge.textContent = j.ok ? 'Saved' : 'Error';
          savedBadge.classList.toggle('bg-success', !!j.ok);
          savedBadge.classList.toggle('bg-danger', !j.ok);
          savedBadge.style.display = 'inline-block';
          setTimeout(()=>{ savedBadge.style.display='none'; }, 1500);
        }
      }).catch(()=>{ /* ignore */ });
  };

  form.querySelectorAll('input,select,textarea').forEach(el=>{
    el.addEventListener('change', () => {
      clearTimeout(timer); timer = setTimeout(send, 250);
    });
    el.addEventListener('input', () => {
      clearTimeout(timer); timer = setTimeout(send, 600);
    });
  });
})();

/* ------- Skill modal: add vs edit ------- */
const skillModal = document.getElementById('skillModal');
if (skillModal) {
  skillModal.addEventListener('show.bs.modal', (ev) => {
    const btn = ev.relatedTarget;
    const mode = btn?.getAttribute('data-mode') || 'add';
    const form = document.getElementById('skillForm');
    const modeSpan = document.getElementById('skillMode');
    const lvlVal = document.getElementById('lvlVal');

    form.reset();
    form.action.value = (mode === 'edit') ? 'update_skill' : 'add_skill';
    modeSpan.textContent = (mode === 'edit') ? 'Edit' : 'Add';
    let name = btn?.getAttribute('data-name') || '';
    let level = btn?.getAttribute('data-level') || '70';
    form.old_name.value = name;
    form.skill_name.value = name;
    form.skill_level.value = level;
    if (lvlVal) lvlVal.textContent = level;
  });

  // AJAX submit skill form
  document.getElementById('skillForm')?.addEventListener('submit', function(e){
    e.preventDefault();
    const fd = new FormData(this);
    fetch('profile.php', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body: fd })
      .then(r => r.json()).then(j => location.reload());
  });

  // AJAX delete skill
  document.querySelectorAll('.skill-del-form').forEach(f=>{
    f.addEventListener('submit', (e)=>{
      e.preventDefault();
      const fd = new FormData(f);
      fetch('profile.php', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body: fd })
        .then(r=>r.json()).then(()=>location.reload());
    });
  });
}

/* ------- Interest add/remove AJAX ------- */
document.querySelectorAll('.remove-interest-form').forEach(f=>{
  f.addEventListener('submit',(e)=>{
    e.preventDefault();
    const fd = new FormData(f);
    fetch('profile.php', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body: fd })
      .then(r=>r.json()).then(()=>location.reload());
  });
});
document.getElementById('interestModal')?.querySelector('form')?.addEventListener('submit', function(e){
  // allow normal post without JS too; but prefer ajax
  e.preventDefault();
  const fd = new FormData(this);
  fetch('profile.php', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body: fd })
    .then(r=>r.json()).then(()=>location.reload());
});

/* ------- Minor validations ------- */
document.addEventListener('DOMContentLoaded', function() {
  const es = document.getElementById('edu_start_date');
  const ee = document.getElementById('edu_end_date');
  const ss = document.getElementById('emp_start_date');
  const se = document.getElementById('emp_end_date');
  if (es && ee) es.addEventListener('change',()=> ee.min = es.value);
  if (ss && se) ss.addEventListener('change',()=> se.min = ss.value);
  const ach = document.getElementById('ach_date');
  if (ach) ach.max = new Date().toISOString().split('T')[0];
});

</script>
</body>
</html>
