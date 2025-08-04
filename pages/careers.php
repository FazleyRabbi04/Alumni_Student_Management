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
    <title>Careers - Alumni Relationship & Networking System</title>

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;700&family=Roboto:wght@500;700&display=swap" rel="stylesheet" />

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />

    <!-- AOS Animation CSS -->
    <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet" />

    <style>
        body {
            font-family: 'Open Sans', sans-serif;
            background-color: #f5f7fa;
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
            padding: 60px 20px;
            text-align: center;
            position: relative;
        }

        .hero h1 {
            font-weight: 700;
            font-size: 2.75rem;
        }

        h2.section-title {
            font-weight: 700;
            font-family: 'Roboto', sans-serif;
            font-size: 2rem;
            margin-bottom: 2rem;
        }

        .job-card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background-color: #ffffff;
            padding: 24px;
            transition: all 0.3s ease;
            height: 100%;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }

        .job-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15);
        }

        .job-card h5 {
            color: #002147;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .job-card .company-info {
            color: #0077c8;
            font-weight: 500;
        }

        .job-card .location-info {
            color: #666;
            font-size: 0.95rem;
        }

        .job-card .date-info {
            color: #888;
            font-size: 0.9rem;
            font-style: italic;
        }

        .post-job-form {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .btn-primary {
            background-color: #002147;
            border-color: #002147;
            font-weight: 600;
            padding: 12px 30px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background-color: #0077c8;
            border-color: #0077c8;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 119, 200, 0.3);
        }

        .form-control {
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            padding: 12px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #0077c8;
            box-shadow: 0 0 0 0.2rem rgba(0, 119, 200, 0.25);
        }

        .loading {
            text-align: center;
            padding: 2rem;
        }

        .spinner-border {
            color: #002147;
        }

        .footer {
            background-color: #002147;
            color: #fff;
            padding: 40px 0;
            font-size: 0.95rem;
        }

        .footer a {
            color: #aad4ff;
            text-decoration: none;
            margin: 0 10px;
            transition: color 0.3s;
        }

        .footer a:hover {
            color: #ffffff;
        }

        .social-icons img {
            margin: 0 6px;
            width: 24px;
            height: 24px;
            filter: grayscale(100%);
            transition: filter 0.3s;
        }

        .social-icons img:hover {
            filter: grayscale(0%);
        }

        .alert {
            border-radius: 10px;
            border: none;
        }

        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2rem;
            }

            .job-card {
                margin-bottom: 1rem;
            }
        }
    </style>
</head>
<body>

<!-- Header -->
<header>
    <nav class="navbar navbar-expand-lg navbar-dark bg-navy">
        <div class="container-fluid px-4">
            <a class="navbar-brand" href="home.html">Alumni Relationship & Networking System</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="home.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">Alumni Profiles</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="events.php">Events</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="mentorship.php">Mentorship</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="careers.php">Careers</a>
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
        <h1 class="display-4">Career Opportunities</h1>
        <p class="lead">Explore job and internship opportunities posted by our alumni network.</p>
        <div class="mt-4">
            <a href="#job-listings" class="btn btn-light btn-lg me-3">
                <i class="fas fa-search me-2"></i>Browse Jobs
            </a>
            <a href="#post-job" class="btn btn-outline-light btn-lg">
                <i class="fas fa-plus me-2"></i>Post a Job
            </a>
        </div>
    </div>
</section>

<!-- Job Search Section -->
<section class="py-4 bg-white">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="input-group input-group-lg">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" class="form-control" id="jobSearch" placeholder="Search jobs by title, company, or location...">
                    <button class="btn btn-primary" type="button" onclick="searchJobs()">Search</button>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Job Listings Section -->
<section class="py-5" id="job-listings">
    <div class="container">
        <h2 class="section-title text-navy text-center" data-aos="fade-up">Latest Job Opportunities</h2>

        <!-- Loading State -->
        <div id="jobsLoading" class="loading">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-3">Loading job opportunities...</p>
        </div>

        <!-- Jobs Container -->
        <div class="row g-4" id="jobListings" data-aos="fade-up" data-aos-delay="100" style="display: none;">
            <!-- Jobs will be loaded dynamically -->
        </div>

        <!-- No Jobs Message -->
        <div id="noJobsMessage" class="text-center py-5" style="display: none;">
            <i class="fas fa-briefcase fa-3x text-muted mb-3"></i>
            <h4 class="text-muted">No job opportunities found</h4>
            <p class="text-muted">Be the first to post a job opportunity for our alumni network!</p>
            <a href="#post-job" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Post a Job
            </a>
        </div>
    </div>
</section>

<!-- Post Job Section -->
<section class="py-5 bg-light" id="post-job">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h2 class="section-title text-navy text-center" data-aos="fade-up">Post a Job Opportunity</h2>
                <div class="post-job-form" data-aos="fade-up" data-aos-delay="200">
                    <form id="jobPostForm">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="jobTitle" class="form-label">
                                    <i class="fas fa-briefcase me-2"></i>Job Title *
                                </label>
                                <input type="text" class="form-control" id="jobTitle" name="title" required
                                       placeholder="e.g., Software Engineer">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="company" class="form-label">
                                    <i class="fas fa-building me-2"></i>Company *
                                </label>
                                <input type="text" class="form-control" id="company" name="company" required
                                       placeholder="e.g., TechCorp Inc.">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="location" class="form-label">
                                    <i class="fas fa-map-marker-alt me-2"></i>Location *
                                </label>
                                <input type="text" class="form-control" id="location" name="location" required
                                       placeholder="e.g., Dhaka, Bangladesh">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="jobType" class="form-label">
                                    <i class="fas fa-clock me-2"></i>Job Type
                                </label>
                                <select class="form-control" id="jobType" name="job_type">
                                    <option value="">Select Type</option>