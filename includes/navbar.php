<?php
require_once '../config/database.php';
startSecureSession();
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Open Sans', sans-serif;
            background-color: #faf5f6;
            color: #002147;
        }
        .navbar.bg-primary {
            background: #002147 !important;
        }
        .navbar-brand, .nav-link, .dropdown-item {
            color: white !important;
        }
        .nav-link:hover, .dropdown-item:hover {
            color: #f8f9fa !important;
            background-color: rgba(255, 255, 255, 0.1) !important;
        }
        .navbarDropdown .dropdown-item
        {
            background-color: #002147 !important;
        }
        .navbarDropdown .dropdown-item:hover
        {
            background-color: rgba(255, 255, 255, 0.1) !important;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
    <div class="container-fluid px-4">
        <a class="navbar-brand" href="../pages/home.php">
            <i class="fas fa-graduation-cap me-2"></i>Alumni Relationship & Networking System
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <?php if (isLoggedIn()): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle me-1"></i>
                            <?php echo isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'User'; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="../pages/profile.php"><i class="fas fa-user me-2"></i>My Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="registerDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-plus me-1"></i>Register
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="registerDropdown">
                            <li><a class="dropdown-item" href="../auth/signup.php"><i class="fas fa-user-plus me-2"></i>Sign Up</a></li>
                            <li><a class="dropdown-item" href="../auth/signin.php"><i class="fas fa-sign-in-alt me-2"></i>Sign In</a></li>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const dropdownToggle = document.querySelector('#navbarDropdown');
        if (dropdownToggle) {
            dropdownToggle.addEventListener('click', function(e) {
                e.preventDefault();
                const dropdown = new bootstrap.Dropdown(dropdownToggle);
                dropdown.toggle();
            });
        }
        const registerToggle = document.querySelector('#registerDropdown');
        if (registerToggle) {
            registerToggle.addEventListener('click', function(e) {
                e.preventDefault();
                const dropdown = new bootstrap.Dropdown(registerToggle);
                dropdown.toggle();
            });
        }
    });
</script>
</body>
</html>