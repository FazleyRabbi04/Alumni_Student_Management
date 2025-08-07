<?php
require_once '../config/database.php';
startSecureSession();

// Require user to be logged in
requireLogin();

$user_id = $_SESSION['user_id'];
if (isset($_GET['cancel_edit'])) {
    $edit_mode = false;
    unset($_SESSION['edit_mode']);
}
$error = '';
$success = '';
$edit_mode = $_SESSION['edit_mode'] ?? false;

// Get user information
$user_info = getUserInfo($user_id);
if (!$user_info) {
    $error = 'Unable to load user profile.';
}
// Define user type here so it's available for POST processing
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'enable_edit') {
            // Verify password to enable edit mode
            $current_password = $_POST['verify_password'];
            $user_query = "SELECT password FROM person WHERE person_id = ?";
            $user_stmt = executeQuery($user_query, [$user_id]);

            if ($user_stmt && $user_stmt->rowCount() > 0) {
                $user_data = $user_stmt->fetch();
                if (password_verify($current_password, $user_data['password'])) {
                    $edit_mode = true;
                    $_SESSION['edit_mode'] = true;
                    $success = 'Edit mode enabled. You can now modify your information.';
                } else {
                    $error = 'Incorrect password. Please try again.';
                }
            }
        } elseif ($_POST['action'] == 'update_profile') {
            // Update profile information
            $first_name = trim($_POST['first_name']);
            $last_name = trim($_POST['last_name']);
            $street = trim($_POST['street']);
            $city = trim($_POST['city']);
            $zip = trim($_POST['zip']);
            $gender = $_POST['gender'];
            $department = trim($_POST['department']);
            $date_of_birth = $_POST['date_of_birth'];

            if (empty($first_name) || empty($last_name)) {
                $error = 'First name and last name are required.';
            } else {
                try {
                    $update_query = "UPDATE person SET first_name = ?, last_name = ?, street = ?, city = ?, zip = ?, gender = ?, department = ?, date_of_birth = ? WHERE person_id = ?";
                    $update_stmt = executeQuery($update_query, [
                        $first_name, $last_name, $street, $city, $zip,
                        $gender, $department, $date_of_birth, $user_id
                    ]);

                    if ($update_stmt) {
                        $success = 'Profile updated successfully!';
                        $_SESSION['user_name'] = $first_name . ' ' . $last_name;
                        $edit_mode = true;
                        $_SESSION['edit_mode'] = true;
                        // Handle role shifting (Student → Alumni)
                        $shift_role = $_POST['shift_role'] ?? '';

                        if ($shift_role === 'Alumni' && $user_type === 'Student') {
                            try {
                                // Get batch year from student table (optional)
                                $batch_stmt = executeQuery("SELECT batch_year FROM student WHERE person_id = ?", [$user_id]);
                                $batch_year = ($batch_stmt && $batch_stmt->rowCount() > 0) ? $batch_stmt->fetchColumn() : null;

                                $input_grad_year = $_POST['grad_year'] ?? '';

                                if (!preg_match('/^\d{4}$/', $input_grad_year) || $input_grad_year > date('Y') || $input_grad_year < 1950) {
                                    $error = 'Please enter a valid graduation year.';
                                } else {
                                    // Proceed with shifting
                                    $grad_year = $input_grad_year;

                                    $insert_alumni = executeQuery(
                                        "INSERT INTO alumni (person_id, grad_year) VALUES (?, ?)",
                                        [$user_id, $grad_year]
                                    );

                                    if ($insert_alumni) {
                                        executeQuery("DELETE FROM student WHERE person_id = ?", [$user_id]);
                                        $user_type = 'Alumni';
                                        $year_info = $grad_year;
                                        $success .= ' Your role has been shifted to Alumni.';
                                    } else {
                                        $error .= ' Profile updated, but failed to shift role.';
                                    }
                                }
                            } catch (Exception $e) {
                                $error .= ' Error while shifting role: ' . $e->getMessage();
                            }
                        }
                    } else {
                        $error = 'Failed to update profile. Please try again.';
                    }
                } catch (Exception $e) {
                    $error = 'Error updating profile: ' . $e->getMessage();
                }
            }
        } elseif ($_POST['action'] == 'add_education') {
            // Add education record
            $degree = trim($_POST['degree']);
            $institution = trim($_POST['institution']);
            $start_date = $_POST['edu_start_date'];
            $end_date = $_POST['edu_end_date'] ?: null;

            if (!empty($degree) && !empty($institution) && !empty($start_date)) {
                try {
                    $edu_query = "INSERT INTO education_history (person_id, degree, institution, start_date, end_date) VALUES (?, ?, ?, ?, ?)";
                    $edu_stmt = executeQuery($edu_query, [$user_id, $degree, $institution, $start_date, $end_date]);

                    if ($edu_stmt) {
                        $success = 'Education record added successfully!';
                        $edit_mode = true;
                        $_SESSION['edit_mode'] = true;
                    } else {
                        $error = 'Failed to add education record.';
                    }
                } catch (Exception $e) {
                    $error = 'Error adding education: ' . $e->getMessage();
                }
            } else {
                $error = 'Please fill in all required education fields.';
            }
        } elseif ($_POST['action'] == 'add_employment') {
            // Add employment record
            $job_title = trim($_POST['job_title']);
            $company = trim($_POST['company']);
            $designation = trim($_POST['designation']);
            $emp_start_date = $_POST['emp_start_date'];
            $emp_end_date = $_POST['emp_end_date'] ?: null;

            if (!empty($job_title) && !empty($company) && !empty($emp_start_date)) {
                try {
                    $emp_query = "INSERT INTO employment_history (person_id, job_title, company, designation, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?)";
                    $emp_stmt = executeQuery($emp_query, [$user_id, $job_title, $company, $designation, $emp_start_date, $emp_end_date]);

                    if ($emp_stmt) {
                        $success = 'Employment record added successfully!';
                        $edit_mode = true;
                        $_SESSION['edit_mode'] = true;
                    } else {
                        $error = 'Failed to add employment record.';
                    }
                } catch (Exception $e) {
                    $error = 'Error adding employment: ' . $e->getMessage();
                }
            } else {
                $error = 'Please fill in all required employment fields.';
            }
        } elseif ($_POST['action'] == 'add_achievement') {
            // Add achievement record
            $ach_title = trim($_POST['ach_title']);
            $ach_date = $_POST['ach_date'];
            $organization = trim($_POST['organization']);
            $description = trim($_POST['description']);
            $type = $_POST['type'];

            if (!empty($ach_title) && !empty($ach_date)) {
                try {
                    $ach_query = "INSERT INTO achievement (person_id, ach_title, ach_date, organization, description, type) VALUES (?, ?, ?, ?, ?, ?)";
                    $ach_stmt = executeQuery($ach_query, [$user_id, $ach_title, $ach_date, $organization, $description, $type]);

                    if ($ach_stmt) {
                        $success = 'Achievement added successfully!';
                        $edit_mode = true;
                        $_SESSION['edit_mode'] = true;
                    } else {
                        $error = 'Failed to add achievement.';
                    }
                } catch (Exception $e) {
                    $error = 'Error adding achievement: ' . $e->getMessage();
                }
            } else {
                $error = 'Please fill in all required achievement fields.';
            }
        } elseif ($_POST['action'] == 'add_email') {
            // Add secondary email
            $new_email = trim($_POST['new_email']);
            if (!empty($new_email) && filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
                try {
                    // Check if email already exists
                    $check_query = "SELECT email FROM email_address WHERE email = ?";
                    $check_stmt = executeQuery($check_query, [$new_email]);
                    if ($check_stmt && $check_stmt->rowCount() > 0) {
                        $error = 'This email is already in use.';
                    } else {
                        $email_query = "INSERT INTO email_address (person_id, email) VALUES (?, ?)";
                        $email_stmt = executeQuery($email_query, [$user_id, $new_email]);
                        if ($email_stmt) {
                            $success = 'Email added successfully!';
                            $edit_mode = true;
                            $_SESSION['edit_mode'] = true;
                        } else {
                            $error = 'Failed to add email.';
                        }
                    }
                } catch (Exception $e) {
                    $error = 'Error adding email: ' . $e->getMessage();
                }
            } else {
                $error = 'Please provide a valid email address.';
            }
        } elseif ($_POST['action'] == 'add_phone') {
            // Add secondary phone
            $new_phone = trim($_POST['new_phone']);
            if (!empty($new_phone) && preg_match('/^[0-9+\-\s]{10,15}$/', $new_phone)) {
                try {
                    // Check if phone already exists
                    $check_query = "SELECT phone_number FROM person_phone WHERE phone_number = ?";
                    $check_stmt = executeQuery($check_query, [$new_phone]);
                    if ($check_stmt && $check_stmt->rowCount() > 0) {
                        $error = 'This phone number is already in use.';
                    } else {
                        $phone_query = "INSERT INTO person_phone (person_id, phone_number) VALUES (?, ?)";
                        $phone_stmt = executeQuery($phone_query, [$user_id, $new_phone]);
                        if ($phone_stmt) {
                            $success = 'Phone number added successfully!';
                            $edit_mode = true;
                            $_SESSION['edit_mode'] = true;
                        } else {
                            $error = 'Failed to add phone number.';
                        }
                    }
                } catch (Exception $e) {
                    $error = 'Error adding phone: ' . $e->getMessage();
                }
            } else {
                $error = 'Please provide a valid phone number (10-15 digits, +, -, or spaces).';
            }
        }
    }
}

// Get all user emails
$emails = [];
if ($user_info) {
    $email_query = "SELECT email FROM email_address WHERE person_id = ?";
    $email_stmt = executeQuery($email_query, [$user_id]);
    if ($email_stmt) {
        $emails = $email_stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}

// Get all user phone numbers
$phones = [];
if ($user_info) {
    $phone_query = "SELECT phone_number FROM person_phone WHERE person_id = ?";
    $phone_stmt = executeQuery($phone_query, [$user_id]);
    if ($phone_stmt) {
        $phones = $phone_stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}

// Get education history
$education_history = [];
if ($user_info) {
    $education_query = "SELECT * FROM education_history WHERE person_id = ? ORDER BY start_date DESC";
    $education_stmt = executeQuery($education_query, [$user_id]);
    if ($education_stmt) {
        $education_history = $education_stmt->fetchAll();
    }
}

// Get employment history
$employment_history = [];
if ($user_info) {
    $employment_query = "SELECT * FROM employment_history WHERE person_id = ? ORDER BY start_date DESC";
    $employment_stmt = executeQuery($employment_query, [$user_id]);
    if ($employment_stmt) {
        $employment_history = $employment_stmt->fetchAll();
    }
}

// Get achievements
$achievements = [];
if ($user_info) {
    $achievement_query = "SELECT * FROM achievement WHERE person_id = ? ORDER BY ach_date DESC";
    $achievement_stmt = executeQuery($achievement_query, [$user_id]);
    if ($achievement_stmt) {
        $achievements = $achievement_stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Alumni Relationship & Networking System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Open Sans', sans-serif;
            background-color: #faf5f6;
            color: #002147;
        }
        .profile-header {
            background: linear-gradient(to right, #002147, #0077c8);
            color: white;
            padding: 2rem 0;
        }
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid white;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: #002147;
            margin: 0 auto 1rem;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }
        .card-header {
            background: linear-gradient(to right, #002147, #0077c8);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 1rem 1.5rem;
        }
        .btn-edit {
            background: #002147;
            border: none;
            color: white;
            border-radius: 25px;
            padding: 0.5rem 1.5rem;
            transition: all 0.3s;
        }
        .btn-edit:hover {
            background: #002147;
            color: white;
        }
        .readonly-field {
            background-color: #e9ecef;
            cursor: not-allowed;
        }
        .edit-section {
            border: 2px dashed #002147;
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1rem;
            background-color: #f8f9ff;
        }
        .contact-list {
            list-style: none;
            padding: 0;
        }
        .contact-list li {
            margin-bottom: 0.5rem;
        }
        .action-link {
            color: #003087;
            font-weight: 700;
            text-decoration: underline;
        }
        .action-link:hover {
            color: #002147;
            text-decoration: underline;
        }
        .bg-navy {
            background-color: #002147;
        }
    </style>
</head>
<body>
<?php include '../includes/navbar.php'; ?>

<!-- Profile Header -->
<div class="profile-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-3 text-center">
                <div class="profile-avatar">
                    <i class="fas fa-user"></i>
                </div>
            </div>
            <div class="col-md-6">
                <h2><?php echo htmlspecialchars($user_info['first_name'] . ' ' . $user_info['last_name']); ?></h2>
                <p class="mb-1"><i class="fas fa-graduation-cap me-2"></i><?php echo htmlspecialchars($user_type . ' - ' . $year_info); ?></p>
                <p class="mb-1"><i class="fas fa-building me-2"></i><?php echo htmlspecialchars($user_info['department']); ?></p>
                <p class="mb-0"><i class="fas fa-map-marker-alt me-2"></i><?php echo htmlspecialchars($user_info['city'] ?? ''); ?></p>
            </div>
            <div class="col-md-3 text-end">
                <?php if (!$edit_mode): ?>
                    <button class="btn btn-edit" data-bs-toggle="modal" data-bs-target="#verifyModal">
                        <i class="fas fa-edit me-2"></i>Edit Profile
                    </button>
                <?php else: ?>
                    <span class="badge bg-success fs-6">
                        <i class="fas fa-check me-1"></i>Edit Mode Active
                    </span>
                    <a href="profile.php?cancel_edit=1" class="btn btn-outline-danger mt-2">
                        <i class="fas fa-times-circle me-1"></i>Exit Edit Mode
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="container mt-4">
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Personal Information -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-user me-2"></i>Personal Information</h5>
                    <?php if ($edit_mode): ?>
                        <div>
                            <button class="btn btn-sm btn-light me-2" data-bs-toggle="modal" data-bs-target="#addEmailModal">
                                <i class="fas fa-plus me-1"></i>Add Email
                            </button>
                            <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#addPhoneModal">
                                <i class="fas fa-plus me-1"></i>Add Phone
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if ($edit_mode): ?>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="update_profile">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">First Name *</label>
                                    <input type="text" class="form-control" name="first_name"
                                           value="<?php echo htmlspecialchars($user_info['first_name'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Last Name *</label>
                                    <input type="text" class="form-control" name="last_name"
                                           value="<?php echo htmlspecialchars($user_info['last_name'] ?? ''); ?>" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Gender</label>
                                    <select class="form-select" name="gender" required>
                                        <option value="Male" <?php echo ($user_info['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                                        <option value="Female" <?php echo ($user_info['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                                        <option value="Other" <?php echo ($user_info['gender'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Date of Birth</label>
                                    <input type="date" class="form-control" name="date_of_birth"
                                           value="<?php echo htmlspecialchars($user_info['date_of_birth'] ?? ''); ?>" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Street</label>
                                    <input type="text" class="form-control" name="street"
                                           value="<?php echo htmlspecialchars($user_info['street'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">City</label>
                                    <input type="text" class="form-control" name="city"
                                           value="<?php echo htmlspecialchars($user_info['city'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Zip Code</label>
                                    <input type="text" class="form-control" name="zip"
                                           value="<?php echo htmlspecialchars($user_info['zip'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Department</label>
                                    <select class="form-select" name="department" required>
                                        <option value="">Select Department</option>
                                        <option value="Bachelor of Architecture" <?php echo ($user_info['department'] ?? '') === 'Bachelor of Architecture' ? 'selected' : ''; ?>>Bachelor of Architecture</option>
                                        <option value="BS in Civil & Environmental Engineering (CEE)" <?php echo ($user_info['department'] ?? '') === 'BS in Civil & Environmental Engineering (CEE)' ? 'selected' : ''; ?>>BS in Civil & Environmental Engineering (CEE)</option>
                                        <option value="BS in Computer Science & Engineering (CSE)" <?php echo ($user_info['department'] ?? '') === 'BS in Computer Science & Engineering (CSE)' ? 'selected' : ''; ?>>BS in Computer Science & Engineering (CSE)</option>
                                        <option value="BS in Electrical & Electronic Engineering (EEE)" <?php echo ($user_info['department'] ?? '') === 'BS in Electrical & Electronic Engineering (EEE)' ? 'selected' : ''; ?>>BS in Electrical & Electronic Engineering (EEE)</option>
                                        <option value="BS in Electronic & Telecom Engineering (ETE)" <?php echo ($user_info['department'] ?? '') === 'BS in Electronic & Telecom Engineering (ETE)' ? 'selected' : ''; ?>>BS in Electronic & Telecom Engineering (ETE)</option>
                                        <option value="BS in Biochemistry and Biotechnology" <?php echo ($user_info['department'] ?? '') === 'BS in Biochemistry and Biotechnology' ? 'selected' : ''; ?>>BS in Biochemistry and Biotechnology</option>
                                        <option value="BS in Environmental Science & Management" <?php echo ($user_info['department'] ?? '') === 'BS in Environmental Science & Management' ? 'selected' : ''; ?>>BS in Environmental Science & Management</option>
                                        <option value="BS in Microbiology" <?php echo ($user_info['department'] ?? '') === 'BS in Microbiology' ? 'selected' : ''; ?>>BS in Microbiology</option>
                                        <option value="BPharm Professional" <?php echo ($user_info['department'] ?? '') === 'BPharm Professional' ? 'selected' : ''; ?>>BPharm Professional</option>
                                        <option value="BBA Major in Accounting" <?php echo ($user_info['department'] ?? '') === 'BBA Major in Accounting' ? 'selected' : ''; ?>>BBA Major in Accounting</option>
                                        <option value="BBA Major in Economics" <?php echo ($user_info['department'] ?? '') === 'BBA Major in Economics' ? 'selected' : ''; ?>>BBA Major in Economics</option>
                                        <option value="BBA Major in Entrepreneurship" <?php echo ($user_info['department'] ?? '') === 'BBA Major in Entrepreneurship' ? 'selected' : ''; ?>>BBA Major in Entrepreneurship</option>
                                        <option value="BBA Major in Finance" <?php echo ($user_info['department'] ?? '') === 'BBA Major in Finance' ? 'selected' : ''; ?>>BBA Major in Finance</option>
                                        <option value="BBA Major in Human Resource Management" <?php echo ($user_info['department'] ?? '') === 'BBA Major in Human Resource Management' ? 'selected' : ''; ?>>BBA Major in Human Resource Management</option>
                                        <option value="BBA Major in International Business" <?php echo ($user_info['department'] ?? '') === 'BBA Major in International Business' ? 'selected' : ''; ?>>BBA Major in International Business</option>
                                        <option value="BBA Major in Management" <?php echo ($user_info['department'] ?? '') === 'BBA Major in Management' ? 'selected' : ''; ?>>BBA Major in Management</option>
                                        <option value="BBA Major in Management Information Systems" <?php echo ($user_info['department'] ?? '') === 'BBA Major in Management Information Systems' ? 'selected' : ''; ?>>BBA Major in Management Information Systems</option>
                                        <option value="BBA Major in Marketing" <?php echo ($user_info['department'] ?? '') === 'BBA Major in Marketing' ? 'selected' : ''; ?>>BBA Major in Marketing</option>
                                        <option value="BBA Major in Supply Chain Management" <?php echo ($user_info['department'] ?? '') === 'BBA Major in Supply Chain Management' ? 'selected' : ''; ?>>BBA Major in Supply Chain Management</option>
                                        <option value="BBA General" <?php echo ($user_info['department'] ?? '') === 'BBA General' ? 'selected' : ''; ?>>BBA General</option>
                                        <option value="BS in Economics" <?php echo ($user_info['department'] ?? '') === 'BS in Economics' ? 'selected' : ''; ?>>BS in Economics</option>
                                        <option value="BA in English" <?php echo ($user_info['department'] ?? '') === 'BA in English' ? 'selected' : ''; ?>>BA in English</option>
                                        <option value="Bachelor of Laws (LLB Hons)" <?php echo ($user_info['department'] ?? '') === 'Bachelor of Laws (LLB Hons)' ? 'selected' : ''; ?>>Bachelor of Laws (LLB Hons)</option>
                                        <option value="BSS in Media and Journalism (MAJ)" <?php echo ($user_info['department'] ?? '') === 'BSS in Media and Journalism (MAJ)' ? 'selected' : ''; ?>>BSS in Media and Journalism (MAJ)</option>
                                    </select>
                                </div>
                            </div>
                            <?php if ($user_type === 'Student'): ?>
                                <div class="edit-section">
                                    <h6>Shift Role to Alumni</h6>
                                    <div class="mb-3">
                                        <label class="form-label">Role</label>
                                        <select class="form-select" name="shift_role">
                                            <option value="">Stay as Student</option>
                                            <option value="Alumni">Shift to Alumni</option>
                                        </select>
                                    </div>
                                    <div class="mb-3" style="display: none;">
                                        <label class="form-label">Graduation Year</label>
                                        <input type="text" class="form-control" name="grad_year" placeholder="e.g., 2023">
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div class="text-end mt-3">
                                <a href="profile.php?cancel_edit=1" class="btn btn-outline-secondary me-2">Cancel</a>
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Student ID</label>
                                <input type="text" class="form-control readonly-field"
                                       value="<?php echo htmlspecialchars($user_info['student_id'] ?? 'N/A'); ?>" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Gender</label>
                                <input type="text" class="form-control readonly-field"
                                       value="<?php echo htmlspecialchars($user_info['gender'] ?? 'N/A'); ?>" readonly>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Date of Birth</label>
                                <input type="text" class="form-control readonly-field"
                                       value="<?php echo htmlspecialchars($user_info['date_of_birth'] ?? 'N/A'); ?>" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Department</label>
                                <input type="text" class="form-control readonly-field"
                                       value="<?php echo htmlspecialchars($user_info['department'] ?? 'N/A'); ?>" readonly>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Street</label>
                                <input type="text" class="form-control readonly-field"
                                       value="<?php echo htmlspecialchars($user_info['street'] ?? 'N/A'); ?>" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">City</label>
                                <input type="text" class="form-control readonly-field"
                                       value="<?php echo htmlspecialchars($user_info['city'] ?? 'N/A'); ?>" readonly>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Zip Code</label>
                                <input type="text" class="form-control readonly-field"
                                       value="<?php echo htmlspecialchars($user_info['zip'] ?? 'N/A'); ?>" readonly>
                            </div>
                        </div>
                        <h6>Contact Information</h6>
                        <ul class="contact-list">
                            <?php foreach ($emails as $email): ?>
                                <li><i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($email); ?></li>
                            <?php endforeach; ?>
                            <?php foreach ($phones as $phone): ?>
                                <li><i class="fas fa-phone me-2"></i><?php echo htmlspecialchars($phone); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Education History -->
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
                                <h6 class="mb-1"><?php echo htmlspecialchars($edu['degree']); ?></h6>
                                <p class="text-primary mb-1"><?php echo htmlspecialchars($edu['institution']); ?></p>
                                <small class="text-muted">
                                    <?php echo date('M Y', strtotime($edu['start_date'])); ?> -
                                    <?php echo $edu['end_date'] ? date('M Y', strtotime($edu['end_date'])) : 'Present'; ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Employment History -->
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
                                <h6 class="mb-1"><?php echo htmlspecialchars($emp['job_title']); ?></h6>
                                <p class="text-success mb-1"><?php echo htmlspecialchars($emp['company']); ?></p>
                                <?php if ($emp['designation']): ?>
                                    <p class="mb-1 small"><?php echo htmlspecialchars($emp['designation']); ?></p>
                                <?php endif; ?>
                                <small class="text-muted">
                                    <?php echo date('M Y', strtotime($emp['start_date'])); ?> -
                                    <?php echo $emp['end_date'] ? date('M Y', strtotime($emp['end_date'])) : 'Present'; ?>
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
                        <?php foreach ($achievements as $achievement): ?>
                            <div class="border-start border-warning border-3 ps-3 mb-3">
                                <h6 class="mb-1"><?php echo htmlspecialchars($achievement['ach_title']); ?></h6>
                                <?php if ($achievement['organization']): ?>
                                    <p class="text-warning mb-1"><?php echo htmlspecialchars($achievement['organization']); ?></p>
                                <?php endif; ?>
                                <?php if ($achievement['description']): ?>
                                    <p class="mb-1 small"><?php echo htmlspecialchars($achievement['description']); ?></p>
                                <?php endif; ?>
                                <small class="text-muted">
                                    <?php echo date('F j, Y', strtotime($achievement['ach_date'])); ?>
                                    <?php if ($achievement['type']): ?>
                                        | <?php echo htmlspecialchars($achievement['type']); ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Stats Sidebar -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Profile Stats</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Education Records:</span>
                        <span class="badge bg-primary"><?php echo count($education_history); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Work Experience:</span>
                        <span class="badge bg-success"><?php echo count($employment_history); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Achievements:</span>
                        <span class="badge bg-warning"><?php echo count($achievements); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Emails:</span>
                        <span class="badge bg-info"><?php echo count($emails); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Phone Numbers:</span>
                        <span class="badge bg-info"><?php echo count($phones); ?></span>
                    </div>
                    <hr>
                    <small class="text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        Complete your profile to connect with more alumni and students.
                    </small>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-lock me-2"></i>Security</h5>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-3">
                        Your email, phone, and Student ID are protected and cannot be changed for security reasons.
                    </p>
                    <a href="change_password.php" class="btn btn-outline-primary btn-sm w-100 action-link">
                        <i class="fas fa-key me-2"></i>Change Password
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Password Verification Modal -->
<div class="modal fade" id="verifyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-shield-alt me-2"></i>Verify Your Identity</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <p class="text-muted">Please enter your current password to enable edit mode:</p>
                    <input type="hidden" name="action" value="enable_edit">
                    <div class="mb-3">
                        <label for="verify_password" class="form-label">Current Password</label>
                        <input type="password" class="form-control" id="verify_password" name="verify_password" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Verify & Edit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Email Modal -->
<div class="modal fade" id="addEmailModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-envelope me-2"></i>Add Secondary Email</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_email">
                    <div class="mb-3">
                        <label for="new_email" class="form-label">Email Address *</label>
                        <input type="email" class="form-control" id="new_email" name="new_email" required
                               placeholder="e.g., example@domain.com">
                        <small class="text-muted">Enter a valid email address</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Email</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Phone Modal -->
<div class="modal fade" id="addPhoneModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-phone me-2"></i>Add Secondary Phone</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_phone">
                    <div class="mb-3">
                        <label for="new_phone" class="form-label">Phone Number *</label>
                        <input type="text" class="form-control" id="new_phone" name="new_phone" required
                               placeholder="e.g., +1234567890">
                        <small class="text-muted">Use 10-15 digits, may include +, -, or spaces</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Phone</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Education Modal -->
<div class="modal fade" id="addEducationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-graduation-cap me-2"></i>Add Education</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_education">
                    <div class="mb-3">
                        <label for="degree" class="form-label">Degree *</label>
                        <input type="text" class="form-control" id="degree" name="degree" required
                               placeholder="e.g., Bachelor of Science in Computer Science">
                    </div>
                    <div class="mb-3">
                        <label for="institution" class="form-label">Institution *</label>
                        <input type="text" class="form-control" id="institution" name="institution" required
                               placeholder="e.g., ABC University">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edu_start_date" class="form-label">Start Date *</label>
                            <input type="date" class="form-control" id="edu_start_date" name="edu_start_date" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edu_end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="edu_end_date" name="edu_end_date">
                            <small class="text-muted">Leave blank if currently studying</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Education</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Employment Modal -->
<div class="modal fade" id="addEmploymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-briefcase me-2"></i>Add Employment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_employment">
                    <div class="mb-3">
                        <label for="job_title" class="form-label">Job Title *</label>
                        <input type="text" class="form-control" id="job_title" name="job_title" required
                               placeholder="e.g., Software Engineer">
                    </div>
                    <div class="mb-3">
                        <label for="company" class="form-label">Company *</label>
                        <input type="text" class="form-control" id="company" name="company" required
                               placeholder="e.g., TechCorp Ltd.">
                    </div>
                    <div class="mb-3">
                        <label for="designation" class="form-label">Designation</label>
                        <input type="text" class="form-control" id="designation" name="designation"
                               placeholder="e.g., Senior Developer">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="emp_start_date" class="form-label">Start Date *</label>
                            <input type="date" class="form-control" id="emp_start_date" name="emp_start_date" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="emp_end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="emp_end_date" name="emp_end_date">
                            <small class="text-muted">Leave blank if currently working</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Employment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Achievement Modal -->
<div class="modal fade" id="addAchievementModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-trophy me-2"></i>Add Achievement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_achievement">
                    <div class="mb-3">
                        <label for="ach_title" class="form-label">Achievement Title *</label>
                        <input type="text" class="form-control" id="ach_title" name="ach_title" required
                               placeholder="e.g., Best Student Award">
                    </div>
                    <div class="mb-3">
                        <label for="ach_date" class="form-label">Achievement Date *</label>
                        <input type="date" class="form-control" id="ach_date" name="ach_date" required>
                    </div>
                    <div class="mb-3">
                        <label for="organization" class="form-label">Organization</label>
                        <input type="text" class="form-control" id="organization" name="organization"
                               placeholder="e.g., ABC University">
                    </div>
                    <div class="mb-3">
                        <label for="ach_type" class="form-label">Type</label>
                        <select class="form-select" id="ach_type" name="type">
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
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"
                                  placeholder="Brief description of the achievement..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Achievement</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        var alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            var bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);

    // Form validation
    document.addEventListener('DOMContentLoaded', function() {
        // Prevent end date from being before start date
        const eduStartDate = document.getElementById('edu_start_date');
        const eduEndDate = document.getElementById('edu_end_date');
        const empStartDate = document.getElementById('emp_start_date');
        const empEndDate = document.getElementById('emp_end_date');

        if (eduStartDate && eduEndDate) {
            eduStartDate.addEventListener('change', function() {
                eduEndDate.min = this.value;
            });
        }

        if (empStartDate && empEndDate) {
            empStartDate.addEventListener('change', function() {
                empEndDate.min = this.value;
            });
        }

        // Achievement date shouldn't be in the future
        const achDate = document.getElementById('ach_date');
        if (achDate) {
            const today = new Date().toISOString().split('T')[0];
            achDate.max = today;
        }
    });

    // Toggle graduation year input based on role selection
    document.addEventListener('DOMContentLoaded', function () {
        const roleSelect = document.querySelector('select[name="shift_role"]');
        const gradYearInput = document.querySelector('input[name="grad_year"]');

        function toggleGradYear() {
            if (roleSelect.value === 'Alumni') {
                gradYearInput.closest('.mb-3').style.display = 'block';
            } else {
                gradYearInput.closest('.mb-3').style.display = 'none';
                gradYearInput.value = '';
            }
        }

        if (roleSelect && gradYearInput) {
            toggleGradYear(); // On load
            roleSelect.addEventListener('change', toggleGradYear);
        }
    });
</script>
</body>
</html>