<?php
require_once '../config/database.php';
startSecureSession();

// If user is already logged in, redirect to dashboard
if (isLoggedIn()) {
    header('Location: ../pages/dashboard.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $nid = trim($_POST['nid']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $gender = $_POST['gender'];
    $date_of_birth = $_POST['date_of_birth'];
    $department = trim($_POST['department']);
    $street = trim($_POST['street']);
    $city = trim($_POST['city']);
    $zip = trim($_POST['zip']);
    $user_type = $_POST['user_type'];
    $grad_batch_year = $_POST['grad_batch_year'];

    // Validation
    if (empty($first_name) || empty($last_name) || empty($nid) || empty($email) ||
        empty($phone) || empty($password) || empty($user_type) || empty($grad_batch_year)) {
        $error = 'Please fill in all required fields.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } else {
        // Check if NID or email already exists
        $check_query = "SELECT person_id FROM person WHERE NID = ? OR person_id IN (SELECT person_id FROM email_address WHERE email = ?)";
        $check_stmt = executeQuery($check_query, [$nid, $email]);

        if ($check_stmt && $check_stmt->rowCount() > 0) {
            $error = 'NID or email already exists.';
        } else {
            try {
                $database = new Database();
                $db = $database->getConnection();
                $db->beginTransaction();

                // Insert into person table
                $person_query = "INSERT INTO person (first_name, last_name, street, city, zip, NID, gender, department, password, date_of_birth) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                executeQuery($person_query, [
                    $first_name, $last_name, $street, $city, $zip, $nid,
                    $gender, $department, $hashed_password, $date_of_birth
                ]);

                $person_id = $db->lastInsertId();

                // Insert email
                $email_query = "INSERT INTO email_address (person_id, email) VALUES (?, ?)";
                executeQuery($email_query, [$person_id, $email]);

                // Insert phone
                $phone_query = "INSERT INTO person_phone (person_id, phone_number) VALUES (?, ?)";
                executeQuery($phone_query, [$person_id, $phone]);

                // Insert into alumni or student table
                if ($user_type == 'alumni') {
                    $alumni_query = "INSERT INTO alumni (person_id, grad_year) VALUES (?, ?)";
                    executeQuery($alumni_query, [$person_id, $grad_batch_year]);
                } else {
                    $student_query = "INSERT INTO student (person_id, batch_year) VALUES (?, ?)";
                    executeQuery($student_query, [$person_id, $grad_batch_year]);
                }

                $db->commit();
                $success = 'Registration successful! You can now login.';

            } catch (Exception $e) {
                $db->rollback();
                $error = 'Registration failed. Please try again.';
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
    <title>Register - Alumni Network</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/custom.css" rel="stylesheet">
</head>
<body>
<div class="min-vh-100 py-5" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow-lg">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="fas fa-user-plus fa-3x text-primary mb-3"></i>
                            <h2 class="fw-bold">Join Our Network</h2>
                            <p class="text-muted">Create your alumni account</p>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success" role="alert">
                                <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                                <br><a href="login.php" class="alert-link">Click here to login</a>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="first_name" class="form-label">First Name *</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name"
                                           value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="last_name" class="form-label">Last Name *</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name"
                                           value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" required>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="nid" class="form-label">National ID *</label>
                                    <input type="text" class="form-control" id="nid" name="nid"
                                           value="<?php echo isset($_POST['nid']) ? htmlspecialchars($_POST['nid']) : ''; ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="gender" class="form-label">Gender *</label>
                                    <select class="form-select" id="gender" name="gender" required>
                                        <option value="">Select Gender</option>
                                        <option value="Male" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                                        <option value="Female" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                                        <option value="Other" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="email" name="email"
                                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">Phone Number *</label>
                                    <input type="tel" class="form-control" id="phone" name="phone"
                                           value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="date_of_birth" class="form-label">Date of Birth *</label>
                                <input type="date" class="form-control" id="date_of_birth" name="date_of_birth"
                                       value="<?php echo isset($_POST['date_of_birth']) ? $_POST['date_of_birth'] : ''; ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="department" class="form-label">Department</label>
                                <input type="text" class="form-control" id="department" name="department"
                                       value="<?php echo isset($_POST['department']) ? htmlspecialchars($_POST['department']) : ''; ?>"
                                       placeholder="e.g., Computer Science, Engineering">
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="user_type" class="form-label">I am a *</label>
                                    <select class="form-select" id="user_type" name="user_type" required>
                                        <option value="">Select Type</option>
                                        <option value="student" <?php echo (isset($_POST['user_type']) && $_POST['user_type'] == 'student') ? 'selected' : ''; ?>>Current Student</option>
                                        <option value="alumni" <?php echo (isset($_POST['user_type']) && $_POST['user_type'] == 'alumni') ? 'selected' : ''; ?>>Alumni</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="grad_batch_year" class="form-label" id="year_label">Year *</label>
                                    <input type="number" class="form-control" id="grad_batch_year" name="grad_batch_year"
                                           min="1900" max="2035" value="<?php echo isset($_POST['grad_batch_year']) ? $_POST['grad_batch_year'] : ''; ?>" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="street" class="form-label">Street Address</label>
                                <input type="text" class="form-control" id="street" name="street"
                                       value="<?php echo isset($_POST['street']) ? htmlspecialchars($_POST['street']) : ''; ?>">
                            </div>

                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label for="city" class="form-label">City</label>
                                    <input type="text" class="form-control" id="city" name="city"
                                           value="<?php echo isset($_POST['city']) ? htmlspecialchars($_POST['city']) : ''; ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="zip" class="form-label">ZIP Code</label>
                                    <input type="text" class="form-control" id="zip" name="zip"
                                           value="<?php echo isset($_POST['zip']) ? htmlspecialchars($_POST['zip']) : ''; ?>">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">Password *</label>
                                    <input type="password" class="form-control" id="password" name="password"
                                           minlength="8" required>
                                    <div class="form-text">Minimum 8 characters</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="confirm_password" class="form-label">Confirm Password *</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                            </div>

                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="terms" required>
                                <label class="form-check-label" for="terms">
                                    I agree to the <a href="#" class="text-primary">Terms and Conditions</a>
                                </label>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 mb-3">
                                <i class="fas fa-user-plus me-2"></i>Create Account
                            </button>
                        </form>

                        <div class="text-center">
                            <p class="mb-2">Already have an account?
                                <a href="login.php" class="text-primary">Sign in here</a>
                            </p>
                            <p class="mb-0">
                                <a href="../index.php" class="text-muted">
                                    <i class="fas fa-arrow-left me-1"></i>Back to Home
                                </a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const userTypeSelect = document.getElementById('user_type');
        const yearLabel = document.getElementById('year_label');

        userTypeSelect.addEventListener('change', function() {
            if (this.value === 'student') {
                yearLabel.textContent = 'Batch Year *';
            } else if (this.value === 'alumni') {
                yearLabel.textContent = 'Graduation Year *';
            } else {
                yearLabel.textContent = 'Year *';
            }
        });

        // Password confirmation validation
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');

        function validatePassword() {
            if (password.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity("Passwords don't match");
            } else {
                confirmPassword.setCustomValidity('');
            }
        }

        password.addEventListener('change', validatePassword);
        confirmPassword.addEventListener('keyup', validatePassword);
    });
</script>
</body>
</html>