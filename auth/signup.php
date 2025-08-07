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
    $student_id = trim($_POST['student_id']);
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

    // Get grad/batch year based on user type
    $grad_batch_year = $user_type === 'student' ? $_POST['batch_year'] : $_POST['grad_batch_year'];

    // Validation
    if (empty($first_name) || empty($last_name) || empty($student_id) || empty($email) ||
        empty($phone) || empty($password) || empty($user_type) || empty($grad_batch_year)) {
        $error = 'Please fill in all required fields.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } else {
        // Check if Student ID or email already exists
        $check_query = "SELECT person_id FROM person WHERE student_id = ? OR person_id IN (SELECT person_id FROM email_address WHERE email = ?)";
        $check_stmt = executeQuery($check_query, [$student_id, $email]);

        if ($check_stmt && $check_stmt->rowCount() > 0) {
            $error = 'Student ID or email already exists.';
        } else {
            try {
                $database = new Database();
                $db = $database->getConnection();
                $db->beginTransaction();

                // Insert into person table
                $person_query = "INSERT INTO person (first_name, last_name, street, city, zip, student_id, gender, department, password, date_of_birth) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $db->prepare($person_query);
                $stmt->execute([
                    $first_name, $last_name, $street, $city, $zip, $student_id,
                    $gender, $department, $hashed_password, $date_of_birth
                ]);

                $person_id = $db->lastInsertId();

                // Insert email
                $email_query = "INSERT INTO email_address (person_id, email) VALUES (?, ?)";
                $stmt = $db->prepare($email_query);
                $stmt->execute([$person_id, $email]);

                // Insert phone
                $phone_query = "INSERT INTO person_phone (person_id, phone_number) VALUES (?, ?)";
                $stmt = $db->prepare($phone_query);
                $stmt->execute([$person_id, $phone]);

                // Insert into alumni or student table
                if ($user_type == 'alumni') {
                    $alumni_query = "INSERT INTO alumni (person_id, grad_year) VALUES (?, ?)";
                    $stmt = $db->prepare($alumni_query);
                    $stmt->execute([$person_id, $grad_batch_year]);
                } else {
                    $student_query = "INSERT INTO student (person_id, batch_year) VALUES (?, ?)";
                    $stmt = $db->prepare($student_query);
                    $stmt->execute([$person_id, $grad_batch_year]);
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
    <title>Sign Up - Alumni Relationship & Networking System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background: linear-gradient(135deg, #002147, #0077c8);
            background-size: 200% 200%;
            animation: gradientAnimation 10s ease infinite;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            margin: 0;
        }

        @keyframes gradientAnimation {
            0% { background-position: 0% 0%; }
            50% { background-position: 100% 100%; }
            100% { background-position: 0% 0%; }
        }

        .navbar {
            background-color: #fff;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .navbar-brand {
            font-weight: 700;
            color: #003087 !important;
            font-size: 1.8rem;
        }

        .nav-link {
            font-weight: 500;
            color: #555 !important;
        }

        .nav-link:hover {
            color: #0059ff !important;
        }

        .signup-section {
            margin: 60px auto;
            max-width: 600px;
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
        }

        .signup-section h2 {
            text-align: center;
            color: #003087;
            margin-bottom: 25px;
            font-weight: 700;
        }

        .form-label {
            font-weight: 500;
            color: #003087;
        }

        .btn-primary {
            background-color: #003087;
            border-color: #003087;
            font-weight: 600;
            border-radius: 10px;
            padding: 10px;
            width: 100%;
        }

        .btn-primary:hover {
            background-color: #0059ff;
            border-color: #0059ff;
        }

        .error {
            color: red;
            font-size: 0.9rem;
            margin-top: 5px;
            text-align: center;
        }

        .success {
            color: green;
            font-size: 0.9rem;
            margin-top: 5px;
            text-align: center;
        }

        .footer {
            margin-top: auto;
            background: white;
            text-align: center;
            padding: 25px 0;
            box-shadow: 0 -4px 12px rgba(0, 0, 0, 0.1);
            color: #003087;
        }

        .signup-link {
            color: #003087;
            font-weight: 700;
            text-decoration: underline;
        }

        .signup-link:hover {
            color: #0059ff;
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .signup-section {
                margin: 30px 20px;
            }
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light">
    <div class="container">
        <a class="navbar-brand" href="../home.php">Alumni Relationship & Networking System</a>
        <div class="collapse navbar-collapse">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="../home.php">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="signin.php">Sign In</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="signup-section" data-aos="fade-up">
    <h2>Sign Up</h2>
    <?php if (!empty($error)) echo "<div class='error'>$error</div>"; ?>
    <?php if (!empty($success)) echo "<div class='success'>$success</div>"; ?>
    <form method="POST" action="signup.php">
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="first_name" class="form-label">First Name</label>
                <input type="text" name="first_name" class="form-control" id="first_name" required />
            </div>
            <div class="col-md-6 mb-3">
                <label for="last_name" class="form-label">Last Name</label>
                <input type="text" name="last_name" class="form-control" id="last_name" required />
            </div>
        </div>
        <div class="mb-3">
            <label for="student_id" class="form-label">Student ID</label>
            <input type="text" name="student_id" class="form-control" id="student_id" required />
        </div>
        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" name="email" class="form-control" id="email" placeholder="user@abc.edu" required />
        </div>
        <div class="mb-3">
            <label for="phone" class="form-label">Phone</label>
            <input type="text" name="phone" class="form-control" id="phone" required />
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Create Password</label>
            <input type="password" name="password" class="form-control" id="password" placeholder="Password must be at least 8 characters long." required />
        </div>
        <div class="mb-3">
            <label for="confirm_password" class="form-label">Confirm Password</label>
            <input type="password" name="confirm_password" class="form-control" id="confirm_password" required />
        </div>
        <div class="mb-3">
            <label for="gender" class="form-label">Gender</label>
            <select name="gender" class="form-control" id="gender" required>
                <option value="">Select Gender</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
                <option value="Other">Other</option>
            </select>
        </div>
        <div class="mb-3">
            <label for="date_of_birth" class="form-label">Date of Birth</label>
            <input type="date" name="date_of_birth" class="form-control" id="date_of_birth" required />
        </div>
        <div class="mb-3">
            <label for="department" class="form-label">Department</label>
            <select name="department" class="form-control" id="department" required>
                <option value="">Select Department</option>
                <option value="Bachelor of Architecture">Bachelor of Architecture</option>
                <option value="BS in Civil & Environmental Engineering (CEE)">BS in Civil & Environmental Engineering (CEE)</option>
                <option value="BS in Computer Science & Engineering (CSE)">BS in Computer Science & Engineering (CSE)</option>
                <option value="BS in Electrical & Electronic Engineering (EEE)">BS in Electrical & Electronic Engineering (EEE)</option>
                <option value="BS in Electronic & Telecom Engineering (ETE)">BS in Electronic & Telecom Engineering (ETE)</option>
                <option value="BS in Biochemistry and Biotechnology">BS in Biochemistry and Biotechnology</option>
                <option value="BS in Environmental Science & Management">BS in Environmental Science & Management</option>
                <option value="BS in Microbiology">BS in Microbiology</option>
                <option value="BPharm Professional">BPharm Professional</option>
                <option value="BBA Major in Accounting">BBA Major in Accounting</option>
                <option value="BBA Major in Economics">BBA Major in Economics</option>
                <option value="BBA Major in Entrepreneurship">BBA Major in Entrepreneurship</option>
                <option value="BBA Major in Finance">BBA Major in Finance</option>
                <option value="BBA Major in Human Resource Management">BBA Major in Human Resource Management</option>
                <option value="BBA Major in International Business">BBA Major in International Business</option>
                <option value="BBA Major in Management">BBA Major in Management</option>
                <option value="BBA Major in Management Information Systems">BBA Major in Management Information Systems</option>
                <option value="BBA Major in Marketing">BBA Major in Marketing</option>
                <option value="BBA Major in Supply Chain Management">BBA Major in Supply Chain Management</option>
                <option value="BBA General">BBA General</option>
                <option value="BS in Economics">BS in Economics</option>
                <option value="BA in English">BA in English</option>
                <option value="Bachelor of Laws (LLB Hons)">Bachelor of Laws (LLB Hons)</option>
                <option value="BSS in Media and Journalism (MAJ)">BSS in Media and Journalism (MAJ)</option>
            </select>
        </div>
        <div class="mb-3">
            <label for="street" class="form-label">Street</label>
            <input type="text" name="street" class="form-control" id="street" required />
        </div>
        <div class="mb-3">
            <label for="city" class="form-label">City</label>
            <input type="text" name="city" class="form-control" id="city" required />
        </div>
        <div class="mb-3">
            <label for="zip" class="form-label">Zip Code</label>
            <input type="text" name="zip" class="form-control" id="zip" required />
        </div>
        <div class="mb-3">
            <label for="user_type" class="form-label">User Type</label>
            <select name="user_type" class="form-control" id="user_type" required>
                <option value="">Select User Type</option>
                <option value="alumni">Alumni</option>
                <option value="student">Student</option>
            </select>
        </div>
        <div class="mb-3" id="grad_batch_year_field">
            <label for="grad_batch_year" class="form-label">Graduation/Batch Year</label>
            <input type="text" name="grad_batch_year" class="form-control" id="grad_batch_year" />
            <input type="text" name="batch_year" class="form-control" id="batch_year" style="display:none;" />
        </div>
        <button type="submit" class="btn btn-primary">Register</button>
    </form>
    <p class="text-center mt-3">Already have an account? <a href="signin.php" class="signup-link">Sign In</a></p>
</div>

<footer class="footer">
    <div class="container">
        &copy; 2025 ABC University. All rights reserved.
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
<script>
    AOS.init({ duration: 1000, once: true });

    document.getElementById('user_type').addEventListener('change', function() {
        const gradBatchYearField = document.getElementById('grad_batch_year_field');
        const gradBatchYearInput = document.getElementById('grad_batch_year');
        const batchYearInput = document.getElementById('batch_year');
        if (this.value === 'student') {
            gradBatchYearField.querySelector('label').textContent = 'Batch Year';
            gradBatchYearInput.style.display = 'none';
            batchYearInput.style.display = 'block';
        } else {
            gradBatchYearField.querySelector('label').textContent = 'Graduation Year';
            gradBatchYearInput.style.display = 'block';
            batchYearInput.style.display = 'none';
        }
    });
    document.querySelector('form').addEventListener('submit', function () {
        if (document.getElementById('user_type').value === 'student') {
            document.getElementById('grad_batch_year').value = document.getElementById('batch_year').value;
        } else {
            document.getElementById('batch_year').value = document.getElementById('grad_batch_year').value;
        }
    });
</script>
</body>
</html>