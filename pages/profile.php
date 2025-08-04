<?php
require_once '../config/database.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Get user information including all related data
$user_query = "SELECT p.*, a.grad_year, s.batch_year 
               FROM person p 
               LEFT JOIN alumni a ON p.person_id = a.person_id 
               LEFT JOIN student s ON p.person_id = s.person_id 
               WHERE p.person_id = ?";
$user_stmt = executeQuery($user_query, [$user_id]);
$user = $user_stmt ? $user_stmt->fetch(PDO::FETCH_ASSOC) : null;

// Get user emails
$email_query = "SELECT email FROM email_address WHERE person_id = ?";
$email_stmt = executeQuery($email_query, [$user_id]);
$emails = $email_stmt ? $email_stmt->fetchAll(PDO::FETCH_COLUMN) : [];

// Get user phones
$phone_query = "SELECT phone_number FROM person_phone WHERE person_id = ?";
$phone_stmt = executeQuery($phone_query, [$user_id]);
$phones = $phone_stmt ? $phone_stmt->fetchAll(PDO::FETCH_COLUMN) : [];

// Get education history
$education_query = "SELECT * FROM education_history WHERE person_id = ? ORDER BY start_date DESC";
$education_stmt = executeQuery($education_query, [$user_id]);
$education = $education_stmt ? $education_stmt->fetchAll(PDO::FETCH_ASSOC) : [];

// Get employment history
$employment_query = "SELECT * FROM employment_history WHERE person_id = ? ORDER BY start_date DESC";
$employment_stmt = executeQuery($employment_query, [$user_id]);
$employment = $employment_stmt ? $employment_stmt->fetchAll(PDO::FETCH_ASSOC) : [];

// Get achievements
$achievement_query = "SELECT * FROM achievement WHERE person_id = ? ORDER BY ach_date DESC";
$achievement_stmt = executeQuery($achievement_query, [$user_id]);
$achievements = $achievement_stmt ? $achievement_stmt->fetchAll(PDO::FETCH_ASSOC) : [];

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $street = trim($_POST['street']);
    $city = trim($_POST['city']);
    $zip = trim($_POST['zip']);
    $gender = $_POST['gender'];
    $department = trim($_POST['department']);
    $date_of_birth = $_POST['date_of_birth'];

    $update_query = "UPDATE person SET first_name = ?, last_name = ?, street = ?, city = ?, zip = ?, 
                     gender = ?, department = ?, date_of_birth = ? WHERE person_id = ?";

    if (executeQuery($update_query, [$first_name, $last_name, $street, $city, $zip, $gender,
        $department, $date_of_birth, $user_id])) {
        $message = 'Profile updated successfully!';
        $_SESSION['user_name'] = $first_name . ' ' . $last_name;
        // Refresh user data
        $user_stmt = executeQuery($user_query, [$user_id]);
        $user = $user_stmt ? $user_stmt->fetch(PDO::FETCH_ASSOC) : null;
    } else {
        $error = 'Failed to update profile.';
    }
}

// Handle education addition
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_education'])) {
    $degree = trim($_POST['degree']);
    $institution = trim($_POST['institution']);
    $start_date = $_POST['edu_start_date'];
    $end_date = $_POST['edu_end_date'] ?: null;

    $edu_query = "INSERT INTO education_history (person_id, degree, institution, start_date, end_date) 
                  VALUES (?, ?, ?, ?, ?)";

    if (executeQuery($edu_query, [$user_id, $degree, $institution, $start_date, $end_date])) {
        $message = 'Education record added successfully!';
        // Refresh education data
        $education_stmt = executeQuery($education_query, [$user_id]);
        $education = $education_stmt ? $education_stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } else {
        $error = 'Failed to add education record.';
    }
}

// Handle employment addition
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_employment'])) {
    $job_title = trim($_POST['job_title']);
    $company = trim($_POST['company']);
    $designation = trim($_POST['designation']);
    $start_date = $_POST['emp_start_date'];
    $end_date = $_POST['emp_end_date'] ?: null;

    $emp_query = "INSERT INTO employment_history (person_id, job_title, company, designation, start_date, end_date) 
                  VALUES (?, ?, ?, ?, ?, ?)";

    if (executeQuery($emp_query, [$user_id, $job_title, $company, $designation, $start_date, $end_date])) {
        $message = 'Employment record added successfully!';
        // Refresh employment data
        $employment_stmt = executeQuery($employment_query, [$user_id]);
        $employment = $employment_stmt ? $employment_stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } else {
        $error = 'Failed to add employment record.';
    }
}

// Handle achievement addition
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_achievement'])) {
    $ach_title = trim($_POST['ach_title']);
    $ach_date = $_POST['ach_date'];
    $organization = trim($_POST['organization']);
    $description = trim($_POST['description']);
    $type = $_POST['ach_type'];

    $ach_query = "INSERT INTO achievement (person_id, ach_title, ach_date, organization, description, type) 
                  VALUES (?, ?, ?, ?, ?, ?)";

    if (executeQuery($ach_query, [$user_id, $ach_title, $ach_date, $organization, $description, $type])) {
        $message = 'Achievement added successfully!';
        // Refresh achievement data
        $achievement_stmt = executeQuery($achievement_query, [$user_id]);
        $achievements = $achievement_stmt ? $achievement_stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } else {
        $error = 'Failed to add achievement.';
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
    <link href="../assets/css/custom.css" rel="stylesheet">
</head>
<body>
<?php include '../includes/navbar.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Profile Header -->
            <div class="profile-header text-center py-5 mb-4">
                <div class="container">
                    <div class="profile-avatar mx-auto mb-3 d-flex align-items-center justify-content-center bg-white">
                        <i class="fas fa-user fa-4x text-muted"></i>
                    </div>
                    <h2 class="fw-bold"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h2>
                    <p class="mb-2"><?php echo htmlspecialchars($user['department'] ?: 'Department not specified'); ?></p>
                    <p class="mb-0">
                        <?php if ($user['grad_year']): ?>
                            <span class="badge bg-light text-dark">Alumni - Class of <?php echo $user['grad_year']; ?></span>
                        <?php elseif ($user['batch_year']): ?>
                            <span class="badge bg-light text-dark">Student - Batch <?php echo $user['batch_year']; ?></span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <!-- Navigation Tabs -->
            <ul class="nav nav-tabs" id="profileTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="basic-info-tab" data-bs-toggle="tab" data-bs-target="#basic-info" type="button" role="tab">
                        <i class="fas fa-user me-1"></i>Basic Info
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="education-tab" data-bs-toggle="tab" data-bs-target="#education" type="button" role="tab">
                        <i class="fas fa-graduation-cap me-1"></i>Education
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="employment-tab" data-bs-toggle="tab" data-bs-target="#employment" type="button" role="tab">
                        <i class="fas fa-briefcase me-1"></i>Employment
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="achievements-tab" data-bs-toggle="tab" data-bs-target="#achievements" type="button" role="tab">
                        <i class="fas fa-trophy me-1"></i>Achievements
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="profileTabContent">
                <!-- Basic Info Tab -->
                <div class="tab-pane fade show active" id="basic-info" role="tabpanel">
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Personal Information</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="first_name" class="form-label">First Name</label>
                                        <input type="text" class="form-control" name="first_name"
                                               value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="last_name" class="form-label">Last Name</label>
                                        <input type="text" class="form-control" name="last_name"
                                               value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="gender" class="form-label">Gender</label>
                                        <select class="form-select" name="gender">
                                            <option value="Male" <?php echo $user['gender'] == 'Male' ? 'selected' : ''; ?>>Male</option>
                                            <option value="Female" <?php echo $user['gender'] == 'Female' ? 'selected' : ''; ?>>Female</option>
                                            <option value="Other" <?php echo $user['gender'] == 'Other' ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="date_of_birth" class="form-label">Date of Birth</label>
                                        <input type="date" class="form-control" name="date_of_birth"
                                               value="<?php echo $user['date_of_birth']; ?>">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="department" class="form-label">Department</label>
                                    <input type="text" class="form-control" name="department"
                                           value="<?php echo htmlspecialchars($user['department']); ?>">
                                </div>

                                <div class="mb-3">
                                    <label for="street" class="form-label">Street Address</label>
                                    <input type="text" class="form-control" name="street"
                                           value="<?php echo htmlspecialchars($user['street']); ?>">
                                </div>

                                <div class="row">
                                    <div class="col-md-8 mb-3">
                                        <label for="city" class="form-label">City</label>
                                        <input type="text" class="form-control" name="city"
                                               value="<?php echo htmlspecialchars($user['city']); ?>">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="zip" class="form-label">ZIP Code</label>
                                        <input type="text" class="form-control" name="zip"
                                               value="<?php echo htmlspecialchars($user['zip']); ?>">
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Email Addresses</label>
                                        <?php foreach ($emails as $email): ?>
                                            <div class="input-group mb-2">
                                                <input type="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" readonly>
                                                <button class="btn btn-outline-secondary" type="button">
                                                    <i class="fas fa-envelope"></i>
                                                </button>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Phone Numbers</label>
                                        <?php foreach ($phones as $phone): ?>
                                            <div class="input-group mb-2">
                                                <input type="tel" class="form-control" value="<?php echo htmlspecialchars($phone); ?>" readonly>
                                                <button class="btn btn-outline-secondary" type="button">
                                                    <i class="fas fa-phone"></i>
                                                </button>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <button type="submit" name="update_profile" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>Update Profile
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Education Tab -->
                <div class="tab-pane fade" id="education" role="tabpanel">
                    <div class="mt-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5><i class="fas fa-graduation-cap me-2"></i>Education History</h5>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEducationModal">
                                <i class="fas fa-plus me-1"></i>Add Education
                            </button>
                        </div>

                        <?php if (empty($education)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-graduation-cap fa-4x text-muted mb-4"></i>
                                <h4>No Education Records</h4>
                                <p class="text-muted">Add your educational background to showcase your qualifications.</p>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($education as $edu): ?>
                                    <div class="col-md-6 mb-4">
                                        <div class="card">
                                            <div class="card-body">
                                                <h6 class="card-title"><?php echo htmlspecialchars($edu['degree']); ?></h6>
                                                <p class="card-text">
                                                    <strong><?php echo htmlspecialchars($edu['institution']); ?></strong><br>
                                                    <small class="text-muted">
                                                        <?php echo date('Y', strtotime($edu['start_date'])); ?> -
                                                        <?php echo $edu['end_date'] ? date('Y', strtotime($edu['end_date'])) : 'Present'; ?>
                                                    </small>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Employment Tab -->
                <div class="tab-pane fade" id="employment" role="tabpanel">
                    <div class="mt-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5><i class="fas fa-briefcase me-2"></i>Employment History</h5>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEmploymentModal">
                                <i class="fas fa-plus me-1"></i>Add Employment
                            </button>
                        </div>

                        <?php if (empty($employment)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-briefcase fa-4x text-muted mb-4"></i>
                                <h4>No Employment Records</h4>
                                <p class="text-muted">Add your work experience to highlight your professional journey.</p>
                            </div>
                        <?php else: ?>
                            <div class="timeline">
                                <?php foreach ($employment as $emp): ?>
                                    <div class="card mb-3">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="card-title"><?php echo htmlspecialchars($emp['job_title']); ?></h6>
                                                    <p class="card-text">
                                                        <strong><?php echo htmlspecialchars($emp['company']); ?></strong>
                                                        <?php if ($emp['designation']): ?>
                                                            <br><span class="text-muted"><?php echo htmlspecialchars($emp['designation']); ?></span>
                                                        <?php endif; ?>
                                                    </p>
                                                </div>
                                                <span class="badge bg-secondary">
                                                        <?php echo date('M Y', strtotime($emp['start_date'])); ?> -
                                                        <?php echo $emp['end_date'] ? date('M Y', strtotime($emp['end_date'])) : 'Present'; ?>
                                                    </span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Achievements Tab -->
                <div class="tab-pane fade" id="achievements" role="tabpanel">
                    <div class="mt-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5><i class="fas fa-trophy me-2"></i>Achievements</h5>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAchievementModal">
                                <i class="fas fa-plus me-1"></i>Add Achievement
                            </button>
                        </div>

                        <?php if (empty($achievements)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-trophy fa-4x text-muted mb-4"></i>
                                <h4>No Achievements</h4>
                                <p class="text-muted">Share your accomplishments and recognition with the community.</p>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($achievements as $achievement): ?>
                                    <div class="col-md-6 mb-4">
                                        <div class="card">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <h6 class="card-title"><?php echo htmlspecialchars($achievement['ach_title']); ?></h6>
                                                    <span class="badge bg-<?php
                                                    $type_colors = [
                                                        'Academic' => 'primary',
                                                        'Professional' => 'success',
                                                        'Research' => 'info',
                                                        'Sports' => 'warning',
                                                        'Cultural' => 'danger',
                                                        'Community Service' => 'secondary'
                                                    ];
                                                    echo $type_colors[$achievement['type']] ?? 'secondary';
                                                    ?>"><?php echo htmlspecialchars($achievement['type']); ?></span>
                                                </div>
                                                <?php if ($achievement['organization']): ?>
                                                    <p class="card-text"><strong><?php echo htmlspecialchars($achievement['organization']); ?></strong></p>
                                                <?php endif; ?>
                                                <?php if ($achievement['description']): ?>
                                                    <p class="card-text"><?php echo htmlspecialchars($achievement['description']); ?></p>
                                                <?php endif; ?>
                                                <small class="text-muted">
                                                    <i class="fas fa-calendar me-1"></i><?php echo date('F j, Y', strtotime($achievement['ach_date'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Add Education Modal -->
<div class="modal fade" id="addEducationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Education</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="degree" class="form-label">Degree *</label>
                        <input type="text" class="form-control" name="degree" required>
                    </div>
                    <div class="mb-3">
                        <label for="institution" class="form-label">Institution *</label>
                        <input type="text" class="form-control" name="institution" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edu_start_date" class="form-label">Start Date *</label>
                            <input type="date" class="form-control" name="edu_start_date" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edu_end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" name="edu_end_date">
                            <div class="form-text">Leave empty if currently studying</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_education" class="btn btn-primary">Add Education</button>
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
                <h5 class="modal-title">Add Employment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="job_title" class="form-label">Job Title *</label>
                        <input type="text" class="form-control" name="job_title" required>
                    </div>
                    <div class="mb-3">
                        <label for="company" class="form-label">Company *</label>
                        <input type="text" class="form-control" name="company" required>
                    </div>
                    <div class="mb-3">
                        <label for="designation" class="form-label">Designation</label>
                        <input type="text" class="form-control" name="designation">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="emp_start_date" class="form-label">Start Date *</label>
                            <input type="date" class="form-control" name="emp_start_date" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="emp_end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" name="emp_end_date">
                            <div class="form-text">Leave empty if currently working</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_employment" class="btn btn-primary">Add Employment</button>
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
                <h5 class="modal-title">Add Achievement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="ach_title" class="form-label">Achievement Title *</label>
                        <input type="text" class="form-control" name="ach_title" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="ach_type" class="form-label">Type *</label>
                            <select class="form-select" name="ach_type" required>
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
                        <div class="col-md-6 mb-3">
                            <label for="ach_date" class="form-label">Date *</label>
                            <input type="date" class="form-control" name="ach_date" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="organization" class="form-label">Organization</label>
                        <input type="text" class="form-control" name="organization">
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_achievement" class="btn btn-primary">Add Achievement</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/custom.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Set max date for date inputs to today
        const today = new Date().toISOString().split('T')[0];
        const dateInputs = document.querySelectorAll('input[type="date"]');

        dateInputs.forEach(input => {
            if (input.name.includes('end_date') || input.name === 'ach_date' || input.name === 'date_of_birth') {
                input.setAttribute('max', today);
            }
        });

        // Validate end dates are after start dates
        function validateDateRange(startInput, endInput) {
            if (startInput.value && endInput.value) {
                if (endInput.value <= startInput.value) {
                    endInput.setCustomValidity('End date must be after start date');
                } else {
                    endInput.setCustomValidity('');
                }
            }
        }

        // Education date validation
        const eduStartDate = document.querySelector('input[name="edu_start_date"]');
        const eduEndDate = document.querySelector('input[name="edu_end_date"]');
        if (eduStartDate && eduEndDate) {
            eduStartDate.addEventListener('change', () => validateDateRange(eduStartDate, eduEndDate));
            eduEndDate.addEventListener('change', () => validateDateRange(eduStartDate, eduEndDate));
        }

        // Employment date validation
        const empStartDate = document.querySelector('input[name="emp_start_date"]');
        const empEndDate = document.querySelector('input[name="emp_end_date"]');
        if (empStartDate && empEndDate) {
            empStartDate.addEventListener('change', () => validateDateRange(empStartDate, empEndDate));
            empEndDate.addEventListener('change', () => validateDateRange(empStartDate, empEndDate));
        }
    });
</script>
</body>
</html>