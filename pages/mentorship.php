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
    <title>Mentorship - Alumni Relationship & Networking System</title>

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;700&family=Roboto:wght@500;700&display=swap" rel="stylesheet" />

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />

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

        .mentorship-card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background-color: #ffffff;
            padding: 24px;
            transition: all 0.3s ease;
            height: 100%;
        }

        .mentorship-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.08);
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
                        <a class="nav-link" href="home.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">Alumni Profiles</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="events.php">Events</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="mentorship.php">Mentorship</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="careers.php">Careers</a>
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
        <h1 class="display-4">Mentorship Opportunities</h1>
        <p class="lead">Connect with alumni for guidance and career development.</p>
    </div>
</section>

<!-- Mentorship Section -->
<section class="py-5">
    <div class="container">
        <h2 class="section-title text-navy" data-aos="fade-up">Upcoming Sessions</h2>
        <div class="row g-4" id="mentorshipSessions" data-aos="fade-up" data-aos-delay="100">
            <!-- Sessions will be loaded dynamically -->
        </div>
        <h2 class="section-title text-navy mt-5" data-aos="fade-up">Register for Mentorship</h2>
        <form class="mentorship-form" data-aos="fade-up" data-aos-delay="200">
            <div class="mb-3">
                <label for="fullName" class="form-label">Full Name</label>
                <input type="text" class="form-control" id="fullName" required />
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" required />
            </div>
            <div class="mb-3">
                <label for="role" class="form-label">Role</label>
                <select class="form-control" id="role" required>
                    <option value="">Select Role</option>
                    <option value="student">Student</option>
                    <option value="alumni">Alumni</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="session" class="form-label">Session</label>
                <select class="form-control" id="session" required>
                    <option value="">Select Session</option>
                    <!-- Sessions will be loaded dynamically -->
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Register</button>
        </form>
    </div>
</section>

<!-- Footer -->
<footer class="footer text-center">
    <div class="container">
        <div class="fw-bold fs-5 mb-2">Alumni Relationship & Networking System</div>
        <div class="mb-3">
            <a href="profile.php">Alumni Profiles</a>
            <a href="events.php">Events</a>
            <a href="mentorship.php">Mentorship</a>
            <a href="careers.php">Careers</a>
            <a href="terms.php">Terms</a>
            <a href="privacy.php">Privacy</a>
        </div>
        <div class="social-icons mb-2">
            <a href="#"><img src="https://via.placeholder.com/24/facebook.png" alt="Facebook" /></a>
            <a href="#"><img src="https://via.placeholder.com/24/twitter.png" alt="Twitter" /></a>
            <a href="#"><img src="https://via.placeholder.com/24/linkedin.png" alt="LinkedIn" /></a>
        </div>
        <p class="small mb-0">&copy; 2025 ABC University. All rights reserved.</p>
    </div>
</footer>

<!-- JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
<script>
    AOS.init({ duration: 1000, once: true });

    const apiUrl = 'http://localhost:8080/api/mentorship';

    function loadMentorshipSessions() {
        fetch(apiUrl)
            .then(response => response.json())
            .then(sessions => {
                const sessionList = document.getElementById('mentorshipSessions');
                const sessionSelect = document.getElementById('session');
                sessionList.innerHTML = '';
                sessionSelect.innerHTML = '<option value="">Select Session</option>';

                if (!sessions.length) {
                    sessionList.innerHTML = '<p class="text-center">No mentorship sessions found.</p>';
                    return;
                }

                sessions.forEach(session => {
                    const sessionDate = new Date(session.date).toLocaleDateString();
                    const card = document.createElement('div');
                    card.className = 'col-md-6';
                    card.innerHTML = `
                        <div class="mentorship-card h-100">
                            <h5>${session.title}</h5>
                            <p><strong>Date:</strong> ${sessionDate}</p>
                            <p><strong>Location:</strong> ${session.location}</p>
                            <p><strong>Duration:</strong> ${session.duration}</p>
                        </div>
                    `;
                    sessionList.appendChild(card);

                    const option = document.createElement('option');
                    option.value = session.id;
                    option.textContent = `${session.title} - ${sessionDate}`;
                    sessionSelect.appendChild(option);
                });
            })
            .catch(err => {
                console.error('Error loading mentorship sessions:', err);
                document.getElementById('mentorshipSessions').innerHTML = '<p class="text-center text-danger">Failed to load mentorship sessions.</p>';
            });
    }

    window.addEventListener('load', loadMentorshipSessions);
</script>
</body>
</html>