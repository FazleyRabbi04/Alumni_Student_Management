<?php
require_once '../config/database.php';
startSecureSession();

// Get available mentorship sessions
$available_sessions_query = "SELECT id, title, date, location, duration FROM mentorship_sessions WHERE date >= CURDATE() ORDER BY date ASC";
$available_sessions_stmt = executeQuery($available_sessions_query);
$available_sessions = $available_sessions_stmt ? $available_sessions_stmt->fetchAll(PDO::FETCH_ASSOC) : [];

$user_info = isLoggedIn() ? getUserInfo($_SESSION['user_id']) : null;
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

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />

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
            text-align: center;
        }
        .mentorship-card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background-color: #ffffff;
            padding: 24px;
            transition: all 0.3s ease;
            height: 100%;
            text-align: center;
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
            text-align: center;
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
        .btn-outline-purple {
            border-color: #6f42c1;
            color: #6f42c1;
        }
        .btn-outline-purple:hover {
            background-color: #6f42c1;
            color: white;
        }
        .mentorship-form {
            max-width: 500px;
            margin: 0 auto;
        }
    </style>
</head>
<body>

<!-- Header -->
<?php include '../includes/navbar.php'; ?>

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
        <div class="row g-4" data-aos="fade-up" data-aos-delay="100">
            <?php if (empty($available_sessions)): ?>
                <div class="col-12">
                    <p class="text-center text-muted">No mentorship sessions found.</p>
                </div>
            <?php else: ?>
                <?php foreach ($available_sessions as $session): ?>
                    <div class="col-md-6">
                        <div class="mentorship-card h-100">
                            <h5><?php echo htmlspecialchars($session['title']); ?></h5>
                            <p><strong>Date:</strong> <?php echo date('M d, Y', strtotime($session['date'])); ?></p>
                            <p><strong>Location:</strong> <?php echo htmlspecialchars($session['location']); ?></p>
                            <p><strong>Duration:</strong> <?php echo htmlspecialchars($session['duration']); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <h2 class="section-title text-navy mt-5" data-aos="fade-up">Register for Mentorship</h2>
        <!-- Alerts for Form Submission -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show text-center" role="alert">
                <?php echo htmlspecialchars($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show text-center" role="alert">
                <?php echo htmlspecialchars($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        <form class="mentorship-form" action="../pages/register_mentorship.php" method="POST" data-aos="fade-up" data-aos-delay="200">
            <div class="mb-3">
                <label for="full_name" class="form-label">Full Name</label>
                <input type="text" class="form-control" id="full_name" name="full_name"
                       value="<?php echo $user_info ? htmlspecialchars($user_info['first_name'] . ' ' . $user_info['last_name']) : ''; ?>"
                    <?php echo $user_info ? 'readonly' : 'required'; ?> />
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email"
                       value="<?php echo $user_info ? htmlspecialchars($user_info['email']) : ''; ?>"
                    <?php echo $user_info ? 'readonly' : 'required'; ?> />
            </div>
            <div class="mb-3">
                <label for="role" class="form-label">Role</label>
                <select class="form-control" id="role" name="role" required>
                    <option value="">Select Role</option>
                    <option value="student" <?php echo $user_info && isset($user_info['batch_year']) ? 'selected' : ''; ?>>Student</option>
                    <option value="alumni" <?php echo $user_info && isset($user_info['grad_year']) ? 'selected' : ''; ?>>Alumni</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="session_id" class="form-label">Session</label>
                <select class="form-control" id="session_id" name="session_id" required>
                    <option value="">Select Session</option>
                    <?php foreach ($available_sessions as $session): ?>
                        <option value="<?php echo $session['id']; ?>">
                            <?php echo htmlspecialchars($session['title'] . ' - ' . date('M d, Y', strtotime($session['date']))); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-outline-purple w-100">Register</button>
        </form>
    </div>
</section>

<?php include '../includes/footer.php'; ?>

<!-- JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
<script>
    AOS.init({ duration: 1000, once: true });
</script>
</body>
</html>