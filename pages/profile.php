<?php
require_once '../config/database.php';
startSecureSession();

// Require user to be logged in
requireLogin();

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';
$edit_mode = false;

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
            $type = $_POST['ach_type'];
            
            if (!empty($ach_title) && !empty($ach_date)) {
                try {
                    $ach_query = "INSERT INTO achievement (person_id, ach_title, ach_date, organization, description, type) VALUES (?, ?, ?, ?, ?, ?)";
                    $ach_stmt = executeQuery($ach_query, [$user_id, $ach_title, $ach_date, $organization, $description, $type]);
                    
                    if ($ach_stmt) {
                        $success = 'Achievement added successfully!';
                    } else {
                        $error = 'Failed to add achievement.';
                    }
                } catch (Exception $e) {
                    $error = 'Error adding achievement: ' . $e->getMessage();
                }
            } else {
                $error = 'Please fill in all required achievement fields.';
            }
        }
    }
}

// Get user information
$user_info = getUserInfo($user_id);
if (!$user_info) {
    $error = 'Unable to load user profile.';
}

// Get user email and phone
$email = '';
$phone = '';
if ($user_info) {
    $email_query = "SELECT email FROM email_address WHERE person_id = ? LIMIT 1";
    $email_stmt = executeQuery($email_query, [$user_id]);
    if ($email_stmt && $email_stmt->rowCount() > 0) {
        $email_result = $email_stmt->fetch();
        $email = $email_result['email'];
    }
    
    $phone_query = "SELECT phone_number FROM person_phone WHERE person_id = ? LIMIT 1";
    $phone_stmt = executeQuery($phone_query, [$user_id]);
    if ($phone_stmt && $phone_stmt->rowCount() > 0) {
        $phone_result = $phone_stmt->fetch();
        $phone = $phone_result['phone_number'];
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

// Determine user type
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Alumni Network</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            color: #667eea;
            margin: 0 auto 1rem;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }
        .card-header {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 1rem 1.5rem;
        }
        .btn-edit {
            background: #667eea;
            border: none;
            color: white;
            border-radius: 25px;
            padding: 0.5rem 1.5rem;
            transition: all 0.3s;
        }
        .btn-edit:hover {
            background: #5a67d8;
            color: white;
        }
        .readonly-field {
            background-color: #e9ecef;
            cursor: not-allowed;
        }
        .edit-section {
            border: 2px dashed #667eea;
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1rem;
            background-color: #f8f9ff;
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
                    <p class="mb-0"><i class="fas fa-map-marker-alt me-2"></i><?php echo htmlspecialchars($user_info['city']); ?></p>
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
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-user me-2"></i>Personal Information</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($edit_mode): ?>
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="update_profile">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">First Name *</label>
                                        <input type="text" class="form-control" name="first_name" 
                                               value="<?php echo htmlspecialchars($user_info['first_name']); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Last Name *</label>
                                        <input type="text" class="form-control" name="last_name" 
                                               value="<?php echo htmlspecialchars($user_info['last_name']); ?>" required>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Gender</label>
                                        <select class="form-select" name="gender">
                                            <option value="Male" <?php echo $user_info['gender'] == 'Male' ? 'selected' : ''; ?>>Male</option>
                                            <option value="Female" <?php echo $user_info['gender'] == 'Female' ? 'selected' : ''; ?>>Female</option>
                                            <option value="Other" <?php echo $user_info['gender'] == 'Other' ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Date of Birth</label>
                                        <input type="date" class="form-control" name="date_of_birth" 
                                               value="<?php echo $user_info['date_of_birth']; ?>">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Department</label>
                                    <input type="text" class="form-control" name="department" 
                                           value="<?php echo htmlspecialchars($user_info['department']); ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Street Address</label>
                                    <input type="text" class="form-control" name="street" 
                                           value="<?php echo htmlspecialchars($user_info['street']); ?>">
                                </div>
                                <div class="row">
                                    <div class="col-md-8 mb-3">
                                        <label class="form-label">City</label>
                                        <input type="text" class="form-control" name="city" 
                                               value="<?php echo htmlspecialchars($user_info['city']); ?>">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">ZIP Code</label>
                                        <input type="text" class="form-control" name="zip" 
                                               value="<?php echo htmlspecialchars($user_info['zip']); ?>">
                                    </div>
                                </div>
                                <hr>
                                <h6 class="text-muted mb-3">Read-Only Information</h6>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control readonly-field" 
                                               value="<?php echo htmlspecialchars($email); ?>" readonly>
                                        <small class="text-muted">Email cannot be changed</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Phone</label>
                                        <input type="text" class="form-control readonly-field" 
                                               value="<?php echo htmlspecialchars($phone); ?>" readonly>
                                        <small class="text-muted">Phone cannot be changed</small>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">NID</label>
                                    <input type="text" class="form-control readonly-field" 
                                           value="<?php echo htmlspecialchars($user_info['NID']); ?>" readonly>
                                    <small class="text-muted">NID cannot be changed</small>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-save me-2"></i>Save Changes
                                    </button>
                                    <a href="profile.php" class="btn btn-secondary">
                                        <i class="fas fa-times me-2"></i>Cancel
                                    </a>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <strong>Email:</strong><br>
                                    <span class="text-muted"><?php echo htmlspecialchars($email); ?></span>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <strong>Phone:</strong><br>
                                    <span class="text-muted"><?php echo htmlspecialchars($phone); ?></span>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <strong>NID:</strong><br>
                                    <span class="text-muted"><?php echo htmlspecialchars($user_info['NID']); ?></span>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <strong>Gender:</strong><br>
                                    <span class="text-muted"><?php echo htmlspecialchars($user_info['gender']); ?></span>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <strong>Date of Birth:</strong><br>
                                    <span class="text-muted"><?php echo date('F j, Y', strtotime($user_info['date_of_birth'])); ?></span>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <strong>Address:</strong><br>
                                    <span class="text-muted">
                                        <?php 
                                        $address_parts = array_filter([
                                            $user_info['street'], 
                                            $user_info['city'], 
                                            $user_info['zip']
                                        ]);
                                        echo htmlspecialchars(implode(', ', $address_parts) ?: 'Not provided');
                                        ?>
                                    </span>
                                </div>
                            </div>
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
                                        <?php echo date('Y', strtotime($edu['start_date'])); ?> - 
                                        <?php echo $edu['end_date'] ? date('Y', strtotime($edu['end_date'])) : 'Present'; ?>
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
                            Your email, phone, and NID are protected and cannot be changed for security reasons.
                        </p>
                        <a href="change_password.php" class="btn btn-outline-primary btn-sm w-100">
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
                            <select class="form-select" id="ach_type" name="ach_type">
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
    </script>
</body>
</html>