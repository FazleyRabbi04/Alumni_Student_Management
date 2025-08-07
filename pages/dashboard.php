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
        .list-group-item .d-flex {
            flex-direction: column;
            align-items: center;
        }
        .list-group-item .badge {
            margin-top: 0.5rem;
        }
        .action-link {
            color: #003087;
            font-weight: 700;
            text-decoration: underline;
        }
        .action-link:hover {
            color: #002147;
            text-decoration: underline;
        }
    </style>
</head>
<body>
<?php include '../includes/navbar.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2 text-center w-100">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-download me-1"></i>Export
                        </button>
                    </div>
                </div>
            </div>

            <!-- Welcome Message -->
            <div class="alert alert-info alert-dismissible fade show text-center" role="alert">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Welcome back, <?php echo htmlspecialchars($user_info['first_name']); ?>!</strong>
                You have <?php echo $stats['unread_messages']; ?> unread messages and
                <?php echo $stats['my_events']; ?> upcoming events.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>

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
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/custom