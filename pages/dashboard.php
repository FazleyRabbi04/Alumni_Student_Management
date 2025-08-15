<?php
require_once '../config/database.php';
requireLogin();

$user_info = getUserInfo($_SESSION['user_id']);
$user_id   = $_SESSION['user_id'];

// ---------------- Dashboard stats ----------------
$stats = [];

// Total upcoming events
$events_stmt  = executeQuery("SELECT COUNT(*) as total FROM events WHERE event_date >= CURDATE()");
$stats['upcoming_events'] = $events_stmt ? (int)($events_stmt->fetch()['total'] ?? 0) : 0;

// User's registered events
$user_events_stmt  = executeQuery("SELECT COUNT(*) as total FROM registers WHERE person_id = ? AND status != 'Cancelled'", [$user_id]);
$stats['my_events'] = $user_events_stmt ? (int)($user_events_stmt->fetch()['total'] ?? 0) : 0;

// Recent jobs (last 30 days)
$jobs_stmt  = executeQuery("SELECT COUNT(*) as total FROM job WHERE post_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
$stats['recent_jobs'] = $jobs_stmt ? (int)($jobs_stmt->fetch()['total'] ?? 0) : 0;

// Unread messages
$comm_stmt = executeQuery(
    "SELECT COUNT(DISTINCT c.comm_id) as total
     FROM communication c
     JOIN sends s_you   ON s_you.comm_id = c.comm_id 
                       AND s_you.person_id = ? 
                       AND s_you.response = 'Unread'
     JOIN sends s_other ON s_other.comm_id = c.comm_id 
                       AND s_other.person_id <> s_you.person_id",
    [$user_id]
);

$stats['unread_messages'] = $comm_stmt ? (int)($comm_stmt->fetch()['total'] ?? 0) : 0;

// User's registered mentorship sessions (upcoming)
$mentorship_stmt  = executeQuery(
    "SELECT COUNT(*) as total
     FROM mentorship_sessions ms
     JOIN registers r ON ms.id = r.event_id
     WHERE r.person_id = ? AND ms.date >= CURDATE()",
    [$user_id]
);
$stats['mentorship_sessions'] = $mentorship_stmt ? (int)($mentorship_stmt->fetch()['total'] ?? 0) : 0;

// ---------------- Available mentorship sessions for form ----------------
$available_sessions_stmt = executeQuery(
    "SELECT id, title, date
     FROM mentorship_sessions
     WHERE date >= CURDATE()
     ORDER BY date ASC"
);
$available_sessions = $available_sessions_stmt ? $available_sessions_stmt->fetchAll(PDO::FETCH_ASSOC) : [];

// ---------------- Popup datasets (small + fast) ----------------
$popup_upcoming_stmt = executeQuery(
    "SELECT event_title, event_date, city, venue
     FROM events
     WHERE event_date >= CURDATE()
     ORDER BY event_date ASC
     LIMIT 10"
);
$popup_upcoming = $popup_upcoming_stmt ? $popup_upcoming_stmt->fetchAll(PDO::FETCH_ASSOC) : [];

$popup_my_events_stmt = executeQuery(
    "SELECT e.event_title, e.event_date, e.city, r.status
     FROM events e
     JOIN registers r ON r.event_id = e.event_id
     WHERE r.person_id = ? AND r.status != 'Cancelled'
     ORDER BY e.event_date ASC
     LIMIT 10",
    [$user_id]
);
$popup_my_events = $popup_my_events_stmt ? $popup_my_events_stmt->fetchAll(PDO::FETCH_ASSOC) : [];

$popup_jobs_stmt = executeQuery(
    "SELECT job_title, company, location, post_date
     FROM job
     WHERE post_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
     ORDER BY post_date DESC
     LIMIT 10"
);
$popup_jobs = $popup_jobs_stmt ? $popup_jobs_stmt->fetchAll(PDO::FETCH_ASSOC) : [];

$popup_unread_stmt = executeQuery(
    "SELECT c.comm_id, c.subject, c.message, c.sent_at,
            p.first_name, p.last_name
     FROM communication c
     JOIN sends s_you   ON s_you.comm_id = c.comm_id AND s_you.person_id = ? AND s_you.response = 'Unread'
     JOIN sends s_other ON s_other.comm_id = c.comm_id AND s_other.person_id <> s_you.person_id
     JOIN person p      ON p.person_id = s_other.person_id
     ORDER BY c.sent_at DESC
     LIMIT 10",
    [$user_id]
);
$popup_unread = $popup_unread_stmt ? $popup_unread_stmt->fetchAll(PDO::FETCH_ASSOC) : [];

$popup_mentorship_stmt = executeQuery(
    "SELECT ms.title, ms.date, ms.location, r.status
     FROM mentorship_sessions ms
     JOIN registers r ON r.event_id = ms.id
     WHERE r.person_id = ? AND ms.date >= CURDATE()
     ORDER BY ms.date ASC
     LIMIT 10",
    [$user_id]
);
$popup_mentorship = $popup_mentorship_stmt ? $popup_mentorship_stmt->fetchAll(PDO::FETCH_ASSOC) : [];
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
        body { font-family: 'Open Sans', sans-serif; background-color: #faf5f6; color: #002147; }
        .dashboard-card { border: none; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .dashboard-card .card-body { display:flex; justify-content:center; align-items:center; text-align:center; }
        .dashboard-card .row { justify-content:center; }

        .dashboard-card.primary  .card-body { background: linear-gradient(to right, #1861bf, #6bbcf6); color:#fff; }
        .dashboard-card.success  .card-body { background: linear-gradient(to right, #28a745, #71dd8a); color:#fff; }
        .dashboard-card.warning  .card-body { background: linear-gradient(to right, #ffc107, #ffda6a); color:#333; }
        .dashboard-card.info     .card-body { background: linear-gradient(to right, #17a2b8, #5bc0de); color:#fff; }
        .dashboard-card.purple   .card-body { background: linear-gradient(to right, #6f42c1, #d3adf7); color:#fff; }

        .btn-outline-purple { border-color:#6f42c1; color:#6f42c1; border-radius:10px; }
        .btn-outline-purple:hover { background-color:#6f42c1; color:#fff; }

        /* Quick Actions */
        .quick-action-btn { font-weight:600; border-width:2.5px; border-radius:12px; padding:26px 0 18px 0; font-size:1.17rem;
            transition:background .2s, color .2s, border-color .2s; box-shadow:0 2px 12px rgba(40,40,60,0.02); margin-bottom:6px; }
        .quick-action-blue   { border-color:#0d6efd !important; color:#0d6efd !important; background:#fff !important; }
        .quick-action-blue:hover, .quick-action-blue:focus { background:#0d6efd !important; color:#fff !important; border-color:#0d6efd !important; }
        .quick-action-green  { border-color:#198754 !important; color:#198754 !important; background:#fff !important; }
        .quick-action-green:hover, .quick-action-green:focus { background:#198754 !important; color:#fff !important; border-color:#198754 !important; }
        .quick-action-yellow { border-color:#ffc107 !important; color:#ffc107 !important; background:#fff !important; }
        .quick-action-yellow:hover, .quick-action-yellow:focus { background:#ffc107 !important; color:#fff !important; border-color:#ffc107 !important; }
        .quick-action-teal   { border-color:#0dcaf0 !important; color:#0dcaf0 !important; background:#fff !important; }
        .quick-action-teal:hover, .quick-action-teal:focus   { background:#0dcaf0 !important; color:#fff !important; border-color:#0dcaf0 !important; }
        .quick-action-purple { border-color:#6f42c1 !important; color:#6f42c1 !important; background:#fff !important; }
        .quick-action-purple:hover, .quick-action-purple:focus { background:#6f42c1 !important; color:#fff !important; border-color:#6f42c1 !important; }

        /* Cards act like buttons to open popup */
        .js-open-stat { cursor: pointer; }
        .js-open-stat:focus-visible { outline:3px solid #0d6efd; outline-offset:2px; }
        .js-open-stat:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,0,0,.08); }

        /* Modal skins */
        .modal-content { border: none; border-radius: 14px; overflow: hidden; }
        /* Primary (Upcoming Events) */
        .modal-skin-primary .modal-header {
          background: linear-gradient(to right, #1861bf, #6bbcf6);
          color: #fff;
        }
        .modal-skin-primary .btn-close { filter: invert(1) brightness(200%); }
        .modal-skin-primary .modal-body { background: #eef6ff; }
        /* Success (My Events) */
        .modal-skin-success .modal-header {
          background: linear-gradient(to right, #28a745, #71dd8a);
          color: #fff;
        }
        .modal-skin-success .btn-close { filter: invert(1) brightness(200%); }
        .modal-skin-success .modal-body { background: #ecfdf3; }
        /* Warning (Recent Jobs) */
        .modal-skin-warning .modal-header {
          background: linear-gradient(to right, #ffc107, #ffda6a);
          color: #333;
        }
        .modal-skin-warning .btn-close { filter: none; }
        .modal-skin-warning .modal-body { background: #fff9db; }
        /* Info (Unread Messages) */
        .modal-skin-info .modal-header {
          background: linear-gradient(to right, #17a2b8, #5bc0de);
          color: #fff;
        }
        .modal-skin-info .btn-close { filter: invert(1) brightness(200%); }
        .modal-skin-info .modal-body { background: #e7f8fd; }
        /* Purple (Mentorship Sessions) */
        .modal-skin-purple .modal-header {
          background: linear-gradient(to right, #6f42c1, #d3adf7);
          color: #fff;
        }
        .modal-skin-purple .btn-close { filter: invert(1) brightness(200%); }
        .modal-skin-purple .modal-body { background: #f3e8ff; }
    </style>
</head>
<body>

<?php include '../includes/navbar.php'; ?>

<div class="container-fluid">
    <div class="row">
        <main class="col-12 px-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2 text-center w-100"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</h1>
            </div>

            <!-- Welcome Message -->
            <div class="alert alert-info alert-dismissible fade show text-center" role="alert">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Welcome back, <?php echo htmlspecialchars($user_info['first_name']); ?>!</strong>
                You have <?php echo (int)$stats['unread_messages']; ?> unread messages,
                <?php echo (int)$stats['my_events']; ?> upcoming events, and
                <?php echo (int)$stats['mentorship_sessions']; ?> mentorship sessions.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>

            <!-- Flash Messages -->
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

            <!-- Statistics Cards (open popup on click) -->
            <div class="row mb-4 justify-content-center">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card dashboard-card primary h-100 js-open-stat" data-type="upcoming" role="button" tabindex="0">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center w-100">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1">Upcoming Events</div>
                                    <div class="h5 mb-0 font-weight-bold"><?php echo (int)$stats['upcoming_events']; ?></div>
                                </div>
                                <div class="col-auto"><i class="fas fa-calendar fa-2x"></i></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card dashboard-card success h-100 js-open-stat" data-type="my" role="button" tabindex="0">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center w-100">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1">My Events</div>
                                    <div class="h5 mb-0 font-weight-bold"><?php echo (int)$stats['my_events']; ?></div>
                                </div>
                                <div class="col-auto"><i class="fas fa-user-check fa-2x"></i></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card dashboard-card warning h-100 js-open-stat" data-type="jobs" role="button" tabindex="0">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center w-100">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1">Recent Jobs</div>
                                    <div class="h5 mb-0 font-weight-bold"><?php echo (int)$stats['recent_jobs']; ?></div>
                                </div>
                                <div class="col-auto"><i class="fas fa-briefcase fa-2x"></i></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card dashboard-card info h-100 js-open-stat" data-type="unread" role="button" tabindex="0">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center w-100">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1">Unread Messages</div>
                                    <div class="h5 mb-0 font-weight-bold"><?php echo (int)$stats['unread_messages']; ?></div>
                                </div>
                                <div class="col-auto"><i class="fas fa-envelope fa-2x"></i></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card dashboard-card purple h-100 js-open-stat" data-type="mentorship" role="button" tabindex="0">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center w-100">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1">Mentorship Sessions</div>
                                    <div class="h5 mb-0 font-weight-bold"><?php echo (int)$stats['mentorship_sessions']; ?></div>
                                </div>
                                <div class="col-auto"><i class="fas fa-chalkboard-teacher fa-2x"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row mb-4 justify-content-center">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header text-center"><i class="fas fa-bolt me-2"></i>Quick Actions</div>
                        <div class="card-body text-center">
                            <div class="row text-center justify-content-center g-3">
                                <div class="col-md-3 mb-3">
                                    <a href="profile.php?prompt_edit=1" class="btn quick-action-btn quick-action-blue btn-lg w-100">
                                          <i class="fas fa-user-edit fa-2x d-block mb-2"></i>
                                                    Update Profile
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="events.php" class="btn quick-action-btn quick-action-green btn-lg w-100">
                                        <i class="fas fa-calendar-plus fa-2x d-block mb-2"></i>Register Event
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="jobs.php" class="btn quick-action-btn quick-action-yellow btn-lg w-100">
                                        <i class="fas fa-briefcase fa-2x d-block mb-2"></i>Post Job
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="communications.php" class="btn quick-action-btn quick-action-teal btn-lg w-100">
                                        <i class="fas fa-envelope fa-2x d-block mb-2"></i>Messages
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="mentorship.php" class="btn quick-action-btn quick-action-purple btn-lg w-100">
                                        <i class="fas fa-chalkboard-teacher fa-2x d-block mb-2"></i>Register Mentorship
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mentorship Registration Modal -->
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
                                            <option value="<?php echo (int)$session['id']; ?>">
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

            <!-- Hidden popup templates -->
            <div id="tpl-upcoming" class="d-none" data-count="<?php echo count($popup_upcoming); ?>">
                <?php if (empty($popup_upcoming)): ?>
                    <p class="text-muted mb-0">No upcoming events.</p>
                <?php else: ?>
                    <ul class="list-group">
                        <?php foreach ($popup_upcoming as $e): ?>
                            <li class="list-group-item">
                                <strong><?php echo htmlspecialchars($e['event_title']); ?></strong><br>
                                <small class="text-muted">
                                    <i class="fas fa-calendar me-1"></i><?php echo date('M d, Y', strtotime($e['event_date'])); ?>
                                    <span class="ms-2"><i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($e['city']); ?></span>
                                </small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div id="tpl-my" class="d-none" data-count="<?php echo count($popup_my_events); ?>">
                <?php if (empty($popup_my_events)): ?>
                    <p class="text-muted mb-0">You have no registered events.</p>
                <?php else: ?>
                    <ul class="list-group">
                        <?php foreach ($popup_my_events as $e): ?>
                            <li class="list-group-item">
                                <strong><?php echo htmlspecialchars($e['event_title']); ?></strong>
                                <span class="badge ms-2 bg-<?php echo ($e['status']==='Confirmed') ? 'success' : 'warning'; ?>">
                                    <?php echo htmlspecialchars($e['status']); ?>
                                </span><br>
                                <small class="text-muted">
                                    <i class="fas fa-calendar me-1"></i><?php echo date('M d, Y', strtotime($e['event_date'])); ?>
                                    <span class="ms-2"><i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($e['city']); ?></span>
                                </small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div id="tpl-jobs" class="d-none" data-count="<?php echo count($popup_jobs); ?>">
                <?php if (empty($popup_jobs)): ?>
                    <p class="text-muted mb-0">No recent job postings.</p>
                <?php else: ?>
                    <ul class="list-group">
                        <?php foreach ($popup_jobs as $j): ?>
                            <li class="list-group-item">
                                <strong><?php echo htmlspecialchars($j['job_title']); ?></strong><br>
                                <small class="text-muted">
                                    <i class="fas fa-building me-1"></i><?php echo htmlspecialchars($j['company']); ?>
                                    <span class="ms-2"><i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($j['location']); ?></span>
                                    <span class="ms-2"><i class="fas fa-clock me-1"></i><?php echo date('M d', strtotime($j['post_date'])); ?></span>
                                </small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div id="tpl-unread" class="d-none" data-count="<?php echo count($popup_unread); ?>">
                <?php if (empty($popup_unread)): ?>
                    <p class="text-muted mb-0">No unread messages.</p>
                <?php else: ?>
                    <ul class="list-group">
                        <?php foreach ($popup_unread as $m): ?>
                            <li class="list-group-item">
                                <strong><?php echo htmlspecialchars($m['subject'] ?: '(No subject)'); ?></strong><br>
                                <small class="text-muted">
                                    From: <?php echo htmlspecialchars(trim($m['first_name'].' '.$m['last_name'])); ?>
                                    <span class="ms-2"><i class="fas fa-clock me-1"></i><?php echo date('M d, Y g:i A', strtotime($m['sent_at'])); ?></span>
                                </small>
                                <div class="mt-1 text-muted">
                                    <?php
                                      $msg = (string)$m['message'];
                                      if (mb_strlen($msg) > 160) $msg = mb_substr($msg, 0, 160).'…';
                                      echo nl2br(htmlspecialchars($msg));
                                    ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div id="tpl-mentorship" class="d-none" data-count="<?php echo count($popup_mentorship); ?>">
                <?php if (empty($popup_mentorship)): ?>
                    <p class="text-muted mb-0">No upcoming mentorship sessions.</p>
                <?php else: ?>
                    <ul class="list-group">
                        <?php foreach ($popup_mentorship as $s): ?>
                            <li class="list-group-item">
                                <strong><?php echo htmlspecialchars($s['title']); ?></strong>
                                <span class="badge ms-2 bg-<?php echo ($s['status']==='Confirmed') ? 'success' : 'warning'; ?>">
                                    <?php echo htmlspecialchars($s['status']); ?>
                                </span><br>
                                <small class="text-muted">
                                    <i class="fas fa-calendar me-1"></i><?php echo date('M d, Y', strtotime($s['date'])); ?>
                                    <span class="ms-2"><i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($s['location']); ?></span>
                                </small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <!-- Stats Popup Modal -->
            <div class="modal fade" id="statModal" tabindex="-1" aria-hidden="true">
              <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title">
                      <span id="statModalIcon" class="me-2"></span>
                      <span id="statModalTitle"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                  </div>
                  <div class="modal-body" id="statModalBody"></div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
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
    // counter animation
    document.querySelectorAll('.h5').forEach(counter => {
        const target = parseInt(counter.textContent || '0', 10) || 0;
        let current = 0;
        const increment = Math.max(1, Math.ceil(target / 20));
        const updateCounter = () => {
            if (current < target) {
                current = Math.min(target, current + increment);
                counter.textContent = current;
                setTimeout(updateCounter, 40);
            } else {
                counter.textContent = target;
            }
        };
        updateCounter();
    });

    // popup mapping + handlers with color skins
    const map = {
        upcoming:   { title: 'Upcoming Events',     icon: '<i class="fas fa-calendar"></i>',            tpl: 'tpl-upcoming',   skin: 'primary'   },
        my:         { title: 'My Events',           icon: '<i class="fas fa-user-check"></i>',          tpl: 'tpl-my',         skin: 'success'   },
        jobs:       { title: 'Recent Jobs',         icon: '<i class="fas fa-briefcase"></i>',           tpl: 'tpl-jobs',       skin: 'warning'   },
        unread:     { title: 'Unread Messages',     icon: '<i class="fas fa-envelope"></i>',            tpl: 'tpl-unread',     skin: 'info'      },
        mentorship: { title: 'Mentorship Sessions', icon: '<i class="fas fa-chalkboard-teacher"></i>',  tpl: 'tpl-mentorship', skin: 'purple'    }
    };

    const modalEl   = document.getElementById('statModal');
    const modal     = new bootstrap.Modal(modalEl);
    const contentEl = modalEl.querySelector('.modal-content');
    const titleEl   = document.getElementById('statModalTitle');
    const iconEl    = document.getElementById('statModalIcon');
    const bodyEl    = document.getElementById('statModalBody');

    const allSkins = ['modal-skin-primary','modal-skin-success','modal-skin-warning','modal-skin-info','modal-skin-purple'];

    function openPopup(type) {
        const cfg = map[type]; if (!cfg) return;

        // apply skin
        contentEl.classList.remove(...allSkins);
        contentEl.classList.add('modal-skin-' + cfg.skin);

        // fill content
        const tpl = document.getElementById(cfg.tpl);
        const count = parseInt(tpl?.getAttribute('data-count') || '0', 10) || 0;
        titleEl.textContent = cfg.title + (count ? ` (${count})` : '');
        iconEl.innerHTML = cfg.icon;
        bodyEl.innerHTML = tpl ? tpl.innerHTML : '<p class="text-muted mb-0">No data.</p>';

        modal.show();
    }

    document.querySelectorAll('.js-open-stat').forEach(card => {
        const type = card.getAttribute('data-type');
        card.addEventListener('click', () => openPopup(type));
        card.addEventListener('keydown', e => {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openPopup(type); }
        });
    });
});
</script>
</body>
</html>
