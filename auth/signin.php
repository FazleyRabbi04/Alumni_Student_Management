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
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        $user = authenticateUser($email, $password);
        if ($user) {
            $_SESSION['user_id'] = $user['person_id'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            header('Location: ../pages/dashboard.php');
            exit();
        } else {
            $error = 'Invalid email or password.';
        }
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
        }

        @keyframes gradientAnimation {
            0% {
                background-position: 0% 0%;
            }

            50% {
                background-position: 100% 100%;
            }

            100% {
                background-position: 0% 0%;
            }
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
        }

        .nav-link {
            font-weight: 500;
            color: #555 !important;
            padding: 0.5rem 1rem;
            transition: color 0.3s ease;
        }

        .nav-link:hover {
            color: #0059ff !important;
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
            padding: 12px;
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
            padding: 12px;
            width: 100%;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background-color: #0059ff;
            border-color: #0059ff;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 89, 255, 0.3);
        }

        .alert {
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .footer {
            box-shadow: 0 -4px 12px rgba(0, 0, 0, 0.1);
            background-color: #ffffff;
            padding: 40px 0;
            color: #003087;
            margin-top: auto;
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
            .login-section {
                margin: 40px 20px;
                padding: 20px;
            }
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container">
            <a class="navbar-brand" href="../home.php">Alumni Relationship & Networking System</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="../home.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="signup.php">Sign Up</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="login-section">
            <h2>Sign In</h2>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success" role="alert">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="signin.php">
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email"
                        value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary">Sign In</button>
            </form>
            <p class="text-center mt-3">Don't have an account? <a href="signup.php" class="signup-link">Sign Up</a></p>
        </div>
    </div>

    <footer class="footer mt-auto">
        <div class="container">
            <p class="text-center mb-0">&copy; 2025 ABC University. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>