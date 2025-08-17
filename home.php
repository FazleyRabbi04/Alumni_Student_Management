<?php
require_once 'config/database.php';
startSecureSession();

// If user is logged in, redirect to dashboard
if (isLoggedIn()) {
    header('Location: pages/dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="description" content="Alumni Relationship & Networking System" />
    <title>Alumni Relationship & Networking System</title>

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;700&family=Roboto:wght@500;700&display=swap" rel="stylesheet" />

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />

    <!-- AOS Animation CSS -->
    <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet" />

    <style>
        body {
            font-family: 'Open Sans', sans-serif;
            background-color: #faf5f6;
            color: #002147;
        }

        .bg-navy {
            background-color: #002147;
        }

        .text-navy {
            color: #002147;
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            letter-spacing: 0.5px;
        }

        .nav-link {
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .nav-link:hover {
            color: #aad4ff !important;
        }

        .hero {
            background: linear-gradient(to right, #002147, #0077c8);
            color: #fff;
            padding: 100px 20px;
            text-align: center;
            position: relative;
        }

        .hero h1 {
            font-weight: 700;
            font-size: 2.75rem;
        }

        .hero p {
            font-size: 1.2rem;
            margin-bottom: 25px;
        }

        h2.section-title {
            font-weight: 700;
            font-family: 'Roboto', sans-serif;
            font-size: 2rem;
            margin-bottom: 2rem;
        }

        .feature-card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background-color: #ffffff;
            padding: 24px;
            transition: all 0.3s ease;
            height: 100%;
        }

        .feature-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.08);
        }

        .card-title {
            font-size: 1.15rem;
            font-weight: 600;
        }

        .footer {
            margin-top: auto;
            background: #002147;
            text-align: center;
            padding: 25px 0;
            box-shadow: 0 -4px 12px rgba(0, 0, 0, 0.1);
            color: #ffffff;
        }
    </style>
</head>

<body>

<!-- Header -->
<header>
    <nav class="navbar navbar-expand-lg navbar-dark bg-navy">
        <div class="container-fluid px-4">
            <a class="navbar-brand" href="home.php">Alumni Relationship & Networking System</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="home.php">Home</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="eventsDropdown" role="button" data-bs-toggle="dropdown">Register</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="auth/signup.php">Sign Up</a></li>
                            <li><a class="dropdown-item" href="auth/signin.php">Sign In</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
</header>

<!-- Hero Section -->
<section class="hero" data-aos="fade-up">
    <div class="container">
        <h1 class="display-4">Welcome to Alumni Relationship & Networking System</h1>
        <p class="lead">Connect, engage, and grow with our community.</p>
    </div>
</section>

<!-- Features Section -->
<section class="features py-5" id="features">
    <div class="container">
        <h2 class="section-title text-center text-navy" data-aos="fade-up">Key Features</h2>
        <div class="row row-cols-1 row-cols-md-3 g-4">
            <div class="col" data-aos="zoom-in">
                <div class="feature-card h-100">
                    <h5 class="card-title">Profiles</h5>
                    <p>Access detailed profiles including education, employment, and achievements.</p>
                </div>
            </div>
            <div class="col" data-aos="zoom-in" data-aos-delay="100">
                <div class="feature-card h-100">
                    <h5 class="card-title">Events & Participation</h5>
                    <p>Register for events and provide feedback.</p>
                </div>
            </div>
            <div class="col" data-aos="zoom-in" data-aos-delay="200">
                <div class="feature-card h-100">
                    <h5 class="card-title">Mentorship Programs</h5>
                    <p>Connect with mentors for career guidance.</p>
                </div>
            </div>
            <div class="col" data-aos="zoom-in" data-aos-delay="300">
                <div class="feature-card h-100">
                    <h5 class="card-title">Career Opportunities</h5>
                    <p>Explore job and internship listings.</p>
                </div>
            </div>
            <div class="col" data-aos="zoom-in" data-aos-delay="400">
                <div class="feature-card h-100">
                    <h5 class="card-title">Communications</h5>
                    <p>Manage communications and generate reports.</p>
                </div>
            </div>
            <div class="col" data-aos="zoom-in" data-aos-delay="500">
                <div class="feature-card h-100">
                    <h5 class="card-title">Future Enhancements</h5>
                    <p>Mobile app, analytics, and AI recommendations.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<footer class="footer">
    <div class="container">
        &copy; 2025 ABC University. All rights reserved.
    </div>
</footer>

<!-- JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
<script>
    AOS.init({
        duration: 1000,
        once: true
    });
</script>
</body>
</html>