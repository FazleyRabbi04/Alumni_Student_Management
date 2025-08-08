<?php
require_once '../config/database.php';
startSecureSession();

$message = '';
$error = '';

// Handle mentorship registration
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = isLoggedIn() ? $_SESSION['user_id'] : null;
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $session_id = trim($_POST['session_id'] ?? '');
    $modal_submit = isset($_POST['modal_submit']);

    if (isLoggedIn()) {
        if (empty($session_id)) {
            $error = 'Please select a session.';
        } else {
            $check_query = "SELECT COUNT(*) as count FROM registers WHERE person_id = ? AND event_id = ?";
            $check_stmt = executeQuery($check_query, [$user_id, $session_id]);
            $count = $check_stmt->fetch()['count'];

            if ($count > 0) {
                $error = 'You are already registered for this session.';
            } else {
                $register_query = "INSERT INTO registers (person_id, event_id, status) VALUES (?, ?, 'Pending')";
                if (executeQuery($register_query, [$user_id, $session_id])) {
                    $message = 'Registration successful! You will be notified soon.';
                    if ($modal_submit) {
                        $_SESSION['success'] = $message;
                        header('Location: dashboard.php');
                        exit();
                    }
                } else {
                    $error = 'Registration failed. Please try again.';
                }
            }
        }
    } else {
        if (empty($full_name) || empty($email) || empty($role) || empty($session_id)) {
            $error = 'All fields are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format.';
        } else {
            $check_query = "SELECT COUNT(*) as count FROM registers WHERE person_id IS NULL AND email = ? AND event_id = ?";
            $check_stmt = executeQuery($check_query, [$email, $session_id]);
            $count = $check_stmt->fetch()['count'];

            if ($count > 0) {
                $error = 'You are already registered for this session with this email.';
            } else {
                $register_query = "INSERT INTO registers (person_id, email, event_id, status) VALUES (NULL, ?, ?, 'Pending')";
                if (executeQuery($register_query, [$email, $session_id])) {
                    $message = 'Registration successful! Check your email for confirmation.';
                } else {
                    $error = 'Registration failed. Please try again.';
                }
            }
        }
    }
    if ($error && $modal_submit) {
        $_SESSION['error'] = $error;
        header('Location: dashboard.php');
        exit();
    }
}

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
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }
        .mentorship-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.12);
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
        }
        .registration-form {
            max-width: 500px;
            margin: 0 auto;
            padding: 20px;
            border-radius: 12px;
            background-color: #fff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }
    </style>
</head>
<body>
<?php include '../includes/navbar.php'; ?>

<!-- Hero Section -->
<section class="hero" data-aos="fade-up">
    <div class="container">
        <h1 class="display-4">Mentorship Program</h1>
        <p class="lead">Connect with experienced alumni for guidance and growth.</p>
        <a href="#register" class="btn btn-primary">Register Now</a>
    </div>
</section>

<!-- Registration Section -->
<section class="py-5" id="register">
    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <h2 class="section-title text-navy" data-aos="fade-up">Register for a Mentorship Session</h2>
        <div class="registration-form" data-aos="fade-up" data-aos-delay="200">
            <form action="" method="POST">
                <?php if (!isLoggedIn()): ?>
                    <div class="mb-3">
                        <label for="full_name" class="form-label">Full Name *</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" required
                               value="<?php echo htmlspecialchars($full_name ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email *</label>
                        <input type="email" class="form-control" id="email" name="email" required
                               value="<?php echo htmlspecialchars($email ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="role" class="form-label">Role *</label>
                        <select class="form-control" id="role" name="role" required>
                            <option value="">Select Role</option>
                            <option value="student" <?php echo isset($role) && $role == 'student' ? 'selected' : ''; ?>>Student</option>
                            <option value="alumni" <?php echo isset($role) && $role == 'alumni' ? 'selected' : ''; ?>>Alumni</option>
                        </select>
                    </div>
                <?php endif; ?>
                <div class="mb-3">
                    <label for="session_id" class="form-label">Session *</label>
                    <select class="form-control" id="session_id" name="session_id" required>
                        <option value="">Select Session</option>
                        <?php foreach ($available_sessions as $session): ?>
                            <option value="<?php echo $session['id']; ?>">
                                <?php echo htmlspecialchars($session['title'] . ' - ' . date('M d, Y', strtotime($session['date'])) . ' (' . $session['location'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary w-100">Register</button>
            </form>
        </div>
    </div>
</section>

<?php include '../includes/footer.php'; ?>

<!-- JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
<script>
    AOS.init({ duration: 1000, once: true });
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form');
        if (form) {
            form.addEventListener('submit', function(e) {
                const sessionId = document.getElementById('session_id').value;
                if (!sessionId) {
                    e.preventDefault();
                    alert('Please select a session.');
                }
            });
        }
    });
</script>
</body>
</html>