<?php
require_once '../config/database.php';
startSecureSession();

// Get events from database
$events = [];
try {
    $query = "SELECT * FROM events ORDER BY event_date ASC";
    $stmt = executeQuery($query);

    if ($stmt) {
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    // Handle database error silently, events will remain empty array
    error_log("Error loading events: " . $e->getMessage());
}

// Separate events into upcoming and past
$currentDate = new DateTime();
$upcomingEvents = [];
$pastEvents = [];

foreach ($events as $event) {
    $eventDate = new DateTime($event['date']);
    if ($eventDate >= $currentDate) {
        $upcomingEvents[] = $event;
    } else {
        $pastEvents[] = $event;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="description" content="Alumni Relationship & Networking System" />
    <title>Events - Alumni Relationship & Networking System</title>

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

        .event-card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background-color: #ffffff;
            padding: 24px;
            transition: all 0.3s ease;
            height: 100%;
        }

        .event-card:hover {
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
                        <a class="nav-link active" href="events.php">Events</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="mentorship.php">Mentorship</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="careers.php">Careers</a>
                    </li>
                    <?php if (isLoggedIn()): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                                <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="dashboard.php">Dashboard</a></li>
                                <li><a class="dropdown-item" href="profile.php">My Profile</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="../auth/logout.php">Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="eventsDropdown" role="button" data-bs-toggle="dropdown">Register</a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="auth/signup.php">Sign Up</a></li>
                                <li><a class="dropdown-item" href="auth/signin.php">Sign In</a></li>
                            </ul>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
</header>

<!-- Hero Section -->
<section class="hero" data-aos="fade-up">
    <div class="container">
        <h1 class="display-4">Events</h1>
    </div>
</section>

<!-- Events Section -->
<section class="py-5">
    <div class="container">
        <ul class="nav nav-tabs mb-4" id="eventTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="upcoming-tab" data-bs-toggle="tab" data-bs-target="#upcoming" type="button" role="tab">Upcoming Events</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="past-tab" data-bs-toggle="tab" data-bs-target="#past" type="button" role="tab">Past Events</button>
            </li>
        </ul>
        <div class="tab-content" id="eventTabContent">
            <!-- Upcoming Events Tab -->
            <div class="tab-pane fade show active" id="upcoming" role="tabpanel">
                <div class="row g-4" data-aos="fade-up" data-aos-delay="100">
                    <?php if (empty($upcomingEvents)): ?>
                        <div class="col-12">
                            <p class="text-center text-muted py-5">No upcoming events found.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($upcomingEvents as $event): ?>
                            <div class="col-md-6">
                                <div class="event-card h-100">
                                    <h5><?php echo htmlspecialchars($event['title']); ?></h5>
                                    <p><strong>Date:</strong> <?php echo date('F j, Y', strtotime($event['date'])); ?></p>
                                    <p><strong>Time:</strong> <?php echo htmlspecialchars($event['time']); ?></p>
                                    <p><strong>Venue:</strong> <?php echo htmlspecialchars($event['location']); ?></p>
                                    <p><strong>Type:</strong> <?php echo htmlspecialchars($event['type']); ?></p>
                                    <p><?php echo htmlspecialchars($event['description']); ?></p>
                                    <?php if (isLoggedIn()): ?>
                                        <a href="#" class="btn btn-primary">Register for Event</a>
                                    <?php else: ?>
                                        <a href="../auth/signin.php" class="btn btn-primary">Sign In to Register</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Past Events Tab -->
            <div class="tab-pane fade" id="past" role="tabpanel">
                <div class="row g-4" data-aos="fade-up" data-aos-delay="100">
                    <?php if (empty($pastEvents)): ?>
                        <div class="col-12">
                            <p class="text-center text-muted py-5">No past events found.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($pastEvents as $event): ?>
                            <div class="col-md-6">
                                <div class="event-card h-100">
                                    <h5><?php echo htmlspecialchars($event['title']); ?></h5>
                                    <p><strong>Date:</strong> <?php echo date('F j, Y', strtotime($event['date'])); ?></p>
                                    <p><strong>Time:</strong> <?php echo htmlspecialchars($event['time']); ?></p>
                                    <p><strong>Venue:</strong> <?php echo htmlspecialchars($event['location']); ?></p>
                                    <p><strong>Type:</strong> <?php echo htmlspecialchars($event['type']); ?></p>
                                    <p><?php echo htmlspecialchars($event['description']); ?></p>
                                    <a href="#" class="btn btn-secondary">View Details</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
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
</script>
</body>
</html>