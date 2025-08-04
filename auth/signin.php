<?php
$success = "";
$error = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $password = $_POST["password"];
    $confirm_password = $_POST["confirm_password"];

    // Basic email pattern
    $emailPattern = "/^[a-zA-Z0-9._%+-]+@abc\.edu$/";

    if (!preg_match($emailPattern, $email)) {
        $error = "Email must be in the format example@abc.edu";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Connect to database (update credentials if needed)
        $conn = new mysqli("localhost", "root", "", "alumninetworking");
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }

        // Check if email already exists
        $stmt = $conn->prepare("SELECT * FROM person WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error = "An account with this email already exists.";
        } else {
            // Insert new user
            $stmt = $conn->prepare("INSERT INTO person (name, email, password) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $name, $email, $hashed_password);
            if ($stmt->execute()) {
                // Success
                header("Location: signin.html");
                exit();
            } else {
                $error = "Failed to register. Try again later.";
            }
        }

        $stmt->close();
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Sign In - Alumni Relationship & Networking System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background: linear-gradient(135deg, #002147, #0077c8);
            background-size: 200% 200%;
            animation: gradientAnimation 10s ease infinite;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            margin: 0;
            color: #333;
            transition: background 0.5s ease;
        }

        @keyframes gradientAnimation {
            0% { background-position: 0% 0%; }
            50% { background-position: 100% 100%; }
            100% { background-position: 0% 0%; }
        }

        .navbar {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            background-color: #ffffff;
            padding: 1rem 0;
        }

        .navbar-brand {
            font-weight: 700;
            color: #003087 !important;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
        }

        .nav-link {
            font-weight: 500;
            color: #555 !important;
            padding: 0.5rem 1rem;
            transition: color 0.3s ease, background-color 0.3s ease;
        }

        .nav-link:hover, .nav-link.active {
            color: #0059ff !important;
            background-color: rgba(0, 89, 255, 0.1);
            border-radius: 5px;
        }

        .container.mt-5 {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 70vh;
        }

        .login-section {
            width: 100%;
            max-width: 400px;
            padding: 30px;
            background-color: #ffffff;
            border-radius: 15px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
        }

        .login-section h2 {
            color: #003087;
            font-weight: 700;
            font-size: 1.8rem;
            text-align: center;
            margin-bottom: 25px;
        }

        .form-label {
            color: #003087;
            font-weight: 500;
            font-size: 1rem;
        }

        .form-control {
            border-radius: 10px;
            border-color: #ced4da;
        }

        .form-control:focus {
            border-color: #0077c8;
            box-shadow: 0 0 0 0.15rem rgba(0, 119, 200, 0.25);
        }

        .btn-primary {
            background-color: #003087;
            border-color: #003087;
            font-weight: 600;
            border-radius: 10px;
            padding: 10px;
            width: 100%;
            transition: background-color 0.3s ease, transform 0.2s ease, box-shadow 0.2s ease;
        }

        .btn-primary:hover {
            background-color: #0059ff;
            border-color: #0059ff;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 89, 255, 0.3);
        }

        .spinner-border {
            display: none;
        }

        .error {
            color: red;
            font-size: 0.9rem;
            margin-top: 5px;
            display: none;
        }

        .text-center.mt-3 a {
            font-weight: 500;
            color: #003087;
        }

        .text-center.mt-3 a:hover {
            color: #0059ff;
            text-decoration: underline;
        }

        .footer {
            box-shadow: 0 -4px 12px rgba(0, 0, 0, 0.1);
            background-color: #ffffff;
            padding: 40px 0;
            color: #003087;
            margin-top: auto;
        }

        .footer h5 {
            font-weight: 700;
            margin-bottom: 15px;
        }

        .footer a {
            color: #003087;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .footer a:hover {
            color: #0059ff;
        }

        .footer .social-icons a {
            margin-right: 15px;
            font-size: 1.2rem;
            color: #003087;
            transition: color 0.3s ease;
        }

        .footer .social-icons a:hover {
            color: #0059ff;
        }

        @media (max-width: 768px) {
            .login-section {
                margin: 40px 20px;
                padding: 20px;
            }
            .navbar-brand {
                font-size: 1.5rem;
            }
        }

        .hidden {
            display: none;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light">
    <div class="container">
        <a class="navbar-brand" href="home.html">Alumni Relationship & Networking System</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="home.html">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="signup.html">Sign Up</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="container mt-5">
    <div class="login-section">
        <h2>Sign In</h2>
        <form id="loginForm" action="login.php" method="POST" onsubmit="return validateLogin(event)">
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" placeholder="e.g., user@abc.edu" required>
                <div id="emailError" class="error"></div>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
                <div id="passwordError" class="error"></div>
            </div>
            <button type="submit" class="btn btn-primary">Sign In</button>
            <div id="loginError" class="error mt-2"></div>
        </form>
        <p class="text-center mt-3">Don't have an account? <a href="signup.html">Sign Up</a></p>
    </div>
</div>

<footer class="footer mt-auto">
    <div class="container">
        <p class="text-center mb-0">&copy; 2025 ABC University. All rights reserved.</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function validateLogin(event) {
        event.preventDefault();
        let isValid = true;
        const errors = {};

        // Reset error messages
        document.querySelectorAll('.error').forEach(error => error.style.display = 'none');

        // Validate Email
        const email = document.getElementById('email').value.trim();
        const emailRegex = /^[a-zA-Z0-9]+@abc\.edu$/;
        if (!email || !emailRegex.test(email)) {
            errors.email = 'Email must be in the format (e.g., example@abc.edu)';
            isValid = false;
        }

        // Validate Password
        const password = document.getElementById('password').value;
        if (!password || password.length < 6) {
            errors.password = 'Password must be at least 6 characters';
            isValid = false;
        }

        // Display errors
        for (let key in errors) {
            const errorElement = document.getElementById(key + 'Error');
            if (errorElement) {
                errorElement.textContent = errors[key];
                errorElement.style.display = 'block';
            }
        }

        if (isValid) {
            localStorage.setItem('authToken', 'loggedin');
            window.location.href = 'profile.html';
        }

        return isValid;
    }
</script>
</body>
</html>
