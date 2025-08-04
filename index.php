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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alumni Network Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/custom.css" rel="stylesheet">
</head>
<body>
<!-- Hero Section -->
<section class="hero-section">
    <div class="container">
        <div class="row min-vh-100 align-items-center">
            <div class="col-lg-6">
                <div class="hero-content">
                    <h1 class="display-4 fw-bold text-primary mb-4">
                        Connect. Network. Grow.
                    </h1>
                    <p class="lead mb-4">
                        Join our vibrant alumni network and stay connected with your peers,
                        discover career opportunities, and contribute to the growth of our community.
                    </p>
                    <div class="d-flex gap-3">
                        <a href="auth/login.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-sign-in-alt me-2"></i>Login
                        </a>
                        <a href="auth/register.php" class="btn btn-outline-primary btn-lg">
                            <i class="fas fa-user-plus me-2"></i>Join Us
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="hero-image text-center">
                    <i class="fas fa-graduation-cap hero-icon"></i>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="row text-center mb-5">
            <div class="col-12">
                <h2 class="display-5 fw-bold">Platform Features</h2>
                <p class="lead">Everything you need to stay connected and grow professionally</p>
            </div>
        </div>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="feature-card h-100">
                    <div class="card-body text-center p-4">
                        <i class="fas fa-users feature-icon text-primary mb-3"></i>
                        <h5 class="card-title">Network Building</h5>
                        <p class="card-text">Connect with alumni and students from your department and beyond.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card h-100">
                    <div class="card-body text-center p-4">
                        <i class="fas fa-briefcase feature-icon text-success mb-3"></i>
                        <h5 class="card-title">Career Opportunities</h5>
                        <p class="card-text">Discover job postings and career guidance from fellow alumni.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card h-100">
                    <div class="card-body text-center p-4">
                        <i class="fas fa-calendar-alt feature-icon text-warning mb-3"></i>
                        <h5 class="card-title">Events & Workshops</h5>
                        <p class="card-text">Attend networking events, workshops, and alumni meetups.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card h-100">
                    <div class="card-body text-center p-4">
                        <i class="fas fa-comments feature-icon text-info mb-3"></i>
                        <h5 class="card-title">Communication Hub</h5>
                        <p class="card-text">Stay updated with announcements and network communications.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card h-100">
                    <div class="card-body text-center p-4">
                        <i class="fas fa-chalkboard-teacher feature-icon text-danger mb-3"></i>
                        <h5 class="card-title">Mentorship Sessions</h5>
                        <p class="card-text">Participate in mentorship programs and knowledge sharing sessions.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card h-100">
                    <div class="card-body text-center p-4">
                        <i class="fas fa-trophy feature-icon text-secondary mb-3"></i>
                        <h5 class="card-title">Achievement Showcase</h5>
                        <p class="card-text">Celebrate and share your professional and personal achievements.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Statistics Section -->
<section class="py-5">
    <div class="container">
        <div class="row text-center">
            <div class="col-md-3">
                <div class="stat-item">
                    <h3 class="display-4 fw-bold text-primary">500+</h3>
                    <p class="lead">Alumni Members</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-item">
                    <h3 class="display-4 fw-bold text-success">150+</h3>
                    <p class="lead">Job Opportunities</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-item">
                    <h3 class="display-4 fw-bold text-warning">75+</h3>
                    <p class="lead">Events Organized</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-item">
                    <h3 class="display-4 fw-bold text-info">25+</h3>
                    <p class="lead">Departments</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Footer -->
<footer class="bg-dark text-light py-4">
    <div class="container">
        <div class="row">
            <div class="col-md-6">
                <h5>Alumni Network</h5>
                <p>Connecting generations of graduates worldwide.</p>
            </div>
            <div class="col-md-6 text-md-end">
                <p>&copy; 2025 Alumni Network Management System. All rights reserved.</p>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/custom.js"></script>
</body>
</html>