<?php
require_once '../config/database.php';
requireLogin();

$user_info = getUserInfo($_SESSION['user_id']);
$user_id = $_SESSION['user_id'];

// Get dashboard statistics
$stats = [];

// Total events
$events_query = "SELECT COUNT(*) as total FROM events WHERE event_date >= CURDATE()";
$events_stmt = executeQuery($events_query);
$stats['upcoming_events'] = $events_stmt ? $events_stmt->fetch()['total'] : 0;

// User's registered events
$user_events_query = "SELECT COUNT(*) as total FROM registers WHERE person_id = ? AND status != 'Cancelled'";
$user_events_stmt = executeQuery($user_events_query, [$user_id]);
$stats['my_events'] = $user_events_stmt ? $user_events_stmt->fetch()['total'] : 0;

// Total jobs
$jobs_query = "SELECT COUNT(*) as total FROM job WHERE post_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
$jobs_stmt = executeQuery($jobs_query);
$stats['recent_jobs'] = $jobs_stmt ? $jobs_stmt->fetch()['total'] : 0;

// Unread communications
$comm_query = "SELECT COUNT(*) as total FROM sends s 
               JOIN communication c ON s.comm_id = c.comm_id 
               WHERE s.person_id = ? AND s.response = 'Unread'";
$comm_stmt = executeQuery($comm_query, [$user_id]);
$stats['unread_messages'] = $comm_stmt ? $comm_stmt->fetch()['total'] : 0;

// User's registered mentorship sessions
$mentorship_query = "SELECT COUNT(*) as total FROM mentorship_sessions ms 
                     JOIN registers r ON ms.id = r.event_id 
                     WHERE r.person_id = ? AND ms.date >= CURDATE()";
$mentorship_stmt = executeQuery($mentorship_query, [$user_id]);
$stats['mentorship_sessions'] = $mentorship_stmt ? $mentorship_stmt->fetch()['total'] : 0;

// Get recent activities
$recent_events_query = "SELECT e.event_title, e.event_date, e.city, r.status
                        FROM events e 
                        JOIN registers r ON e.event_id = r.event_id 
                        WHERE r.person_id = ? 
                        ORDER BY e.event_date DESC 
                        LIMIT 5";
$recent_events_stmt = executeQuery($recent_events_query, [$user_id]);
$recent_events = $recent_events_stmt ? $recent_events_stmt->fetchAll(PDO::FETCH_ASSOC) : [];

// Get recent jobs
$recent_jobs_query = "SELECT j.job_title, j.company, j.location, j.post_date
                      FROM job j 
                      ORDER BY j.post_date DESC 
                      LIMIT 5";
$recent_jobs_stmt = executeQuery($recent_jobs_query);
$recent_jobs = $recent_jobs_stmt ? $recent_jobs_stmt->fetchAll(PDO::FETCH_ASSOC) : [];

// Get recent mentorship sessions
$recent_mentorship_query = "SELECT ms.title, ms.date, ms.location, r.status
                            FROM mentorship_sessions ms 
                            JOIN registers r ON ms.id = r.event_id 
                            WHERE r.person_id = ? 
                            ORDER BY ms.date DESC 
                            LIMIT 5";
$recent_mentorship_stmt = executeQuery($recent_mentorship_query, [$user_id]);
$recent_mentorship_sessions = $recent_mentorship_stmt ? $recent_mentorship_stmt->fetchAll(PDO::FETCH_ASSOC) : [];

// Get available mentorship sessions for form
$available_sessions_query = "SELECT id, title, date FROM mentorship_sessions WHERE date >= CURDATE() ORDER BY date ASC";
$available_sessions_stmt = executeQuery($available_sessions_query);
$available_sessions = $available_sessions_stmt ? $available_sessions_stmt->fetchAll(PDO::FETCH_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Alumni Relationship & Networking System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;700&display=swap" rel="stylesheet">
    <link href="../assets/css/custom.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Open Sans', sans-serif;
            background-color: #faf5f6;
            color: #002147;
        }
        .dashboard-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .dashboard-card.primary .card-body {
            background: linear-gradient(to right, #1861bf, #6bbcf6);
            color: white;
        }
        .dashboard-card.success .card-body {
            background: linear-gradient(to right, #28a745, #71dd8a);
            color: white;
        }
        .dashboard-card.warning .card-body {
            background: linear-gradient(to right, #ffc107, #ffda6a);
            color: #333;
        }
        .dashboard-card.info .card-body {
            background: linear-gradient(to right, #17a2b8, #5bc0de);
            color: white;
        }
        .dashboard-card.purple .card-body {
            background: linear-gradient(to right, #6f42c1, #d3adf7);
            color: white;
        }
        .dashboard-card .card-body {
            display: flex;
            justify-content: center;
            align-items: center;
            text-align: center;
        }
        .dashboard-card .row {
            justify-content: center;
        }
        .card-body.centered {
            text-align: center;
        }
        .list-group-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        .list-group-item .badge {
            margin-top: 0.5rem;
        }
        .btn-outline-primary, .btn-outline-success, .btn-outline-warning, .btn-outline-info, .btn-outline-purple {
            border-radius: 10px;
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
            padding: 20px;
            border-radius: 12px;
            background-color: #fff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }
    </style>
</head>
<body>

<?php include '../includes/navbar.php'; ?>


<div class="container-fluid">
    <div class="row">
        <main class="col-12 px-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2 text-center w-100">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </h1>
            </div>

            <!-- Welcome Message -->
            <div class="alert alert-info alert-dismissible fade show text-center" role="alert">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Welcome back, <?php echo htmlspecialchars($user_info['first_name']); ?>!</strong>
                You have <?php echo $stats['unread_messages']; ?> unread messages,
                <?php echo $stats['my_events']; ?> upcoming events, and
                <?php echo $stats['mentorship_sessions']; ?> mentorship sessions.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>

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

            <!-- Statistics Cards -->
            <div class="row mb-4 justify-content-center">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card dashboard-card primary h-100">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1">
                                        Upcoming Events
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold">
                                        <?php echo $stats['upcoming_events']; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-calendar fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card dashboard-card success h-100">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1">
                                        My Events
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold">
                                        <?php echo $stats['my_events']; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-user-check fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card dashboard-card warning h-100">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1">
                                        Recent Jobs
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold">
                                        <?php echo $stats['recent_jobs']; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-briefcase fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card dashboard-card info h-100">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1">
                                        Unread Messages
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold">
                                        <?php echo $stats['unread_messages']; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-envelope fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card dashboard-card purple h-100">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1">
                                        Mentorship Sessions
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold">
                                        <?php echo $stats['mentorship_sessions']; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-chalkboard-teacher fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content Row -->
            <div class="row justify-content-center">
                <!-- Recent Events -->
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header text-center">
                            <i class="fas fa-calendar-check me-2"></i>My Recent Events
                        </div>
                        <div class="card-body centered">
                            <?php if (empty($recent_events)): ?>
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-calendar-times fa-3x mb-3"></i>
                                    <p>No events registered yet.</p>
                                    <a href="events.php" class="btn btn-primary btn-sm">
                                        <i class="fas fa-plus me-1"></i>Browse Events
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($recent_events as $event): ?>
                                        <div class="list-group-item">
                                            <div class="fw-bold"><?php echo htmlspecialchars($event['event_title']); ?></div>
                                            <small class="text-muted">
                                                <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($event['city']); ?>
                                                <i class="fas fa-calendar ms-2 me-1"></i><?php echo date('M d, Y', strtotime($event['event_date'])); ?>
                                            </small>
                                            <span class="badge bg-<?php echo $event['status'] == 'Confirmed' ? 'success' : 'warning'; ?> rounded-pill">
                                                <?php echo $event['status']; ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="events.php" class="btn btn-outline-primary btn-sm">
                                        View All Events <i class="fas fa-arrow-right ms-1"></i>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Recent Jobs -->
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header text-center">
                            <i class="fas fa-briefcase me-2"></i>Latest Job Opportunities
                        </div>
                        <div class="card-body centered">
                            <?php if (empty($recent_jobs)): ?>
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-briefcase fa-3x mb-3"></i>
                                    <p>No recent job postings.</p>
                                    <a href="jobs.php" class="btn btn-primary btn-sm">
                                        <i class="fas fa-plus me-1"></i>Post a Job
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($recent_jobs as $job): ?>
                                        <div class="list-group-item">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($job['job_title']); ?></h6>
                                            <small class="text-muted"><?php echo date('M d', strtotime($job['post_date'])); ?></small>
                                            <p class="mb-1">
                                                <i class="fas fa-building me-1"></i><?php echo htmlspecialchars($job['company']); ?>
                                            </p>
                                            <small class="text-muted">
                                                <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($job['location']); ?>
                                            </small>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="jobs.php" class="btn btn-outline-primary btn-sm">
                                        View All Jobs <i class="fas fa-arrow-right ms-1"></i>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Recent Mentorship Sessions -->
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header text-center">
                            <i class="fas fa-chalkboard-teacher me-2"></i>My Recent Mentorship Sessions
                        </div>
                        <div class="card-body centered">
                            <?php if (empty($recent_mentorship_sessions)): ?>
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-chalkboard fa-3x mb-3"></i>
                                    <p>No mentorship sessions registered yet.</p>
                                    <a href="mentorship.php" class="btn btn-primary btn-sm">
                                        <i class="fas fa-plus me-1"></i>Browse Mentorship Sessions
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($recent_mentorship_sessions as $session): ?>
                                        <div class="list-group-item">
                                            <div class="fw-bold"><?php echo htmlspecialchars($session['title']); ?></div>
                                            <small class="text-muted">
                                                <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($session['location']); ?>
                                                <i class="fas fa-calendar ms-2 me-1"></i><?php echo date('M d, Y', strtotime($session['date'])); ?>
                                            </small>
                                            <span class="badge bg-<?php echo $session['status'] == 'Confirmed' ? 'success' : 'warning'; ?> rounded-pill">
                                                <?php echo $session['status']; ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="mentorship.php" class="btn btn-outline-primary btn-sm">
                                        View All Mentorship Sessions <i class="fas fa-arrow-right ms-1"></i>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row mb-4 justify-content-center">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header text-center">
                            <i class="fas fa-bolt me-2"></i>Quick Actions
                        </div>
                        <div class="card-body centered">
                            <div class="row text-center justify-content-center">
                                <div class="col-md-3 mb-3">
                                    <a href="profile.php" class="btn btn-outline-primary btn-lg w-100">
                                        <i class="fas fa-user-edit d-block mb-2"></i>
                                        Update Profile
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="events.php" class="btn btn-outline-success btn-lg w-100">
                                        <i class="fas fa-calendar-plus d-block mb-2"></i>
                                        Register Event
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="jobs.php" class="btn btn-outline-warning btn-lg w-100">
                                        <i class="fas fa-briefcase d-block mb-2"></i>
                                        Post Job
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="communications.php" class="btn btn-outline-info btn-lg w-100">
                                        <i class="fas fa-envelope d-block mb-2"></i>
                                        Messages
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <button type="button" class="btn btn-outline-purple btn-lg w-100" data-bs-toggle="modal" data-bs-target="#mentorshipModal">
                                        <i class="fas fa-chalkboard-teacher d-block mb-2"></i>
                                        Register Mentorship
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="mentorshipModal" tabindex="-1" aria-labelledby="mentorshipModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="mentorshipModalLabel">Register for Mentorship</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body mentorship-form">
                            <?php if (isset($_SESSION['success'])): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <?php echo htmlspecialchars($_SESSION['success']); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                                <?php unset($_SESSION['success']); ?>
                            <?php endif; ?>
                            <?php if (isset($_SESSION['error'])): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <?php echo htmlspecialchars($_SESSION['error']); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                                <?php unset($_SESSION['error']); ?>
                            <?php endif; ?>
                            <form action="mentorship.php" method="POST">
                                <input type="hidden" name="modal_submit" value="1">
                                <div class="mb-3">
                                    <label for="full_name" class="form-label">Full Name</label>
                                    <input type="text" class="form-control" id="full_name" name="full_name"
                                           value="<?php echo htmlspecialchars($user_info['first_name'] . ' ' . $user_info['last_name']); ?>" readonly />
                                </div>
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email"
                                           value="<?php echo htmlspecialchars($user_info['email']); ?>" readonly />
                                </div>
                                <div class="mb-3">
                                    <label for="role" class="form-label">Role</label>
                                    <select class="form-control" id="role" name="role" required>
                                        <option value="">Select Role</option>
                                        <option value="student" <?php echo isset($user_info['batch_year']) ? 'selected' : ''; ?>>Student</option>
                                        <option value="alumni" <?php echo isset($user_info['grad_year']) ? 'selected' : ''; ?>>Alumni</option>
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
                                <button type="submit" class="btn btn-outline-purple btn-lg w-100">Register</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/custom.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        setInterval(function() {
            console.log('Dashboard stats refresh');
        }, 300000);

        const counters = document.querySelectorAll('.h5');
        counters.forEach(counter => {
            const target = parseInt(counter.textContent);
            let current = 0;
            const increment = target / 20;

            const updateCounter = () => {
                if (current < target) {
                    current += increment;
                    counter.textContent = Math.ceil(current);
                    setTimeout(updateCounter, 50);
                } else {
                    counter.textContent = target;
                }
            };

            updateCounter();
        });
    });
</script>
</body>
</html>