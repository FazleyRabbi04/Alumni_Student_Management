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

        #viewEventsBtn {
            background-color: #ffffff;
            color: #002147;
            border: none;
            padding: 12px 30px;
            font-weight: 600;
            border-radius: 50px;
            transition: all 0.3s ease;
        }

        #viewEventsBtn:hover {
            background-color: #e3e3e3;
            color: #002147;
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
        <button class="btn" id="viewEventsBtn">View Upcoming Events</button>
    </div>
</section>

<!-- Features Section -->
<section class="features py-5" id="features">
    <div class="container">
        <h2 class="section-title text-center text-navy" data-aos="fade-up">Key Features</h2>
        <div class="row row-cols-1 row-cols-md-3 g-4">
            <div class="col" data-aos="zoom-in">
                <div class="feature-card h-100">
                    <h5 class="card-title">Alumni Profiles</h5>
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
                    <h5 class="card-title">Admin Tools</h5>
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

<!-- Upcoming Events Section -->
<section id="events" class="py-5">
    <div class="container">
        <h2 class="section-title text-center text-navy" data-aos="fade-up">Upcoming Events</h2>
        <ul id="eventList" class="list-group" data-aos="fade-up" data-aos-delay="100">
            <!-- Events will be loaded dynamically -->
        </ul>
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

    const apiUrl = 'http://localhost:8080/api/events';

    function loadEvents() {
        fetch(apiUrl)
            .then(response => response.json())
            .then(events => {
                const eventList = document.getElementById('eventList');
                eventList.innerHTML = '';

                if (!events.length) {
                    eventList.innerHTML = '<li class="list-group-item">No upcoming events found.</li>';
                    return;
                }

                events.forEach(event => {
                    const eventDate = new Date(event.date).toLocaleDateString();
                    const li = document.createElement('li');
                    li.className = 'list-group-item';
                    li.innerHTML = `<strong>${event.title}</strong> - ${eventDate}<br>${event.description}<br><em>Location: ${event.location}</em>`;
                    eventList.appendChild(li);
                });
            })
            .catch(err => {
                console.error('Error loading events:', err);
                const eventList = document.getElementById('eventList');
                eventList.innerHTML = '<li class="list-group-item text-danger">Failed to load events.</li>';
            });
    }

    window.addEventListener('load', loadEvents);
    document.getElementById('viewEventsBtn').addEventListener('click', () => {
        document.getElementById('events').scrollIntoView({ behavior: 'smooth' });
    });
</script>
</body>
</html>