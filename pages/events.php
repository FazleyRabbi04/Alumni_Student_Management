<?php
require_once '../config/database.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle event registration
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register_event'])) {
    $event_id = $_POST['event_id'];
    $role = $_POST['role'];

    // Check if already registered
    $check_query = "SELECT * FROM registers WHERE person_id = ? AND event_id = ?";
    $check_stmt = executeQuery($check_query, [$user_id, $event_id]);

    if ($check_stmt && $check_stmt->rowCount() > 0) {
        $error = 'You are already registered for this event.';
    } else {
        $register_query = "INSERT INTO registers (person_id, event_id, role, response, status) VALUES (?, ?, ?, 'Attending', 'Pending')";
        if (executeQuery($register_query, [$user_id, $event_id, $role])) {
            $message = 'Successfully registered for the event!';
        } else {
            $error = 'Failed to register for the event.';
        }
    }
}

// Handle event creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_event'])) {
    $event_title = trim($_POST['event_title']);
    $event_date = $_POST['event_date'];
    $city = trim($_POST['city']);
    $venue = trim($_POST['venue']);
    $type = $_POST['type'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];

    if (!empty($event_title) && !empty($event_date) && !empty($city) && !empty($venue)) {
        $create_query = "INSERT INTO events (event_title, event_date, city, venue, type, start_time, end_time) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
        if (executeQuery($create_query, [$event_title, $event_date, $city, $venue, $type, $start_time, $end_time])) {
            $message = 'Event created successfully!';
        } else {
            $error = 'Failed to create event.';
        }
    } else {
        $error = 'Please fill in all required fields.';
    }
}

// Get upcoming events
$events_query = "SELECT e.*, 
                 (SELECT COUNT(*) FROM registers r WHERE r.event_id = e.event_id AND r.status != 'Cancelled') as registered_count,
                 (SELECT status FROM registers r WHERE r.person_id = ? AND r.event_id = e.event_id) as user_status
                 FROM events e 
                 WHERE e.event_date >= CURDATE() 
                 ORDER BY e.event_date ASC";
$events_stmt = executeQuery($events_query, [$user_id]);
$events = $events_stmt ? $events_stmt->fetchAll(PDO::FETCH_ASSOC) : [];

// Get user's registered events
$my_events_query = "SELECT e.*, r.role, r.response, r.status, r.feedback
                    FROM events e 
                    JOIN registers r ON e.event_id = r.event_id 
                    WHERE r.person_id = ? 
                    ORDER BY e.event_date DESC";
$my_events_stmt = executeQuery($my_events_query, [$user_id]);
$my_events = $my_events_stmt ? $my_events_stmt->fetchAll(PDO::FETCH_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events - Alumni Network</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/custom.css" rel="stylesheet">
</head>
<body>
<?php include '../includes/navbar.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-calendar me-2"></i>Events Management
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createEventModal">
                        <i class="fas fa-plus me-1"></i>Create Event
                    </button>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Navigation Tabs -->
            <ul class="nav nav-tabs" id="eventsTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="upcoming-tab" data-bs-toggle="tab" data-bs-target="#upcoming" type="button" role="tab">
                        <i class="fas fa-calendar-plus me-1"></i>Upcoming Events
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="my-events-tab" data-bs-toggle="tab" data-bs-target="#my-events" type="button" role="tab">
                        <i class="fas fa-calendar-check me-1"></i>My Events
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="eventsTabContent">
                <!-- Upcoming Events Tab -->
                <div class="tab-pane fade show active" id="upcoming" role="tabpanel">
                    <div class="row mt-4">
                        <?php if (empty($events)): ?>
                            <div class="col-12">
                                <div class="text-center py-5">
                                    <i class="fas fa-calendar-times fa-4x text-muted mb-4"></i>
                                    <h4>No Upcoming Events</h4>
                                    <p class="text-muted">There are no events scheduled at the moment.</p>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($events as $event): ?>
                                <div class="col-lg-6 mb-4">
                                    <div class="card h-100">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <h5 class="mb-0"><?php echo htmlspecialchars($event['event_title']); ?></h5>
                                            <span class="badge bg-primary"><?php echo htmlspecialchars($event['type']); ?></span>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <div class="d-flex align-items-center mb-2">
                                                    <i class="fas fa-calendar text-primary me-2"></i>
                                                    <span><?php echo date('F j, Y', strtotime($event['event_date'])); ?></span>
                                                </div>
                                                <div class="d-flex align-items-center mb-2">
                                                    <i class="fas fa-clock text-primary me-2"></i>
                                                    <span><?php echo date('g:i A', strtotime($event['start_time'])); ?> - <?php echo date('g:i A', strtotime($event['end_time'])); ?></span>
                                                </div>
                                                <div class="d-flex align-items-center mb-2">
                                                    <i class="fas fa-map-marker-alt text-primary me-2"></i>
                                                    <span><?php echo htmlspecialchars($event['venue'] . ', ' . $event['city']); ?></span>
                                                </div>
                                                <div class="d-flex align-items-center">
                                                    <i class="fas fa-users text-primary me-2"></i>
                                                    <span><?php echo $event['registered_count']; ?> registered</span>
                                                </div>
                                            </div>

                                            <?php if ($event['user_status']): ?>
                                                <div class="alert alert-info">
                                                    <i class="fas fa-check-circle me-2"></i>
                                                    You are registered as <strong><?php echo $event['user_status']; ?></strong>
                                                </div>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-primary"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#registerModal"
                                                        data-event-id="<?php echo $event['event_id']; ?>"
                                                        data-event-title="<?php echo htmlspecialchars($event['event_title']); ?>">
                                                    <i class="fas fa-user-plus me-1"></i>Register
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- My Events Tab -->
                <div class="tab-pane fade" id="my-events" role="tabpanel">
                    <div class="mt-4">
                        <?php if (empty($my_events)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-calendar-times fa-4x text-muted mb-4"></i>
                                <h4>No Events Registered</h4>
                                <p class="text-muted">You haven't registered for any events yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                    <tr>
                                        <th>Event</th>
                                        <th>Date</th>
                                        <th>Location</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($my_events as $event): ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($event['event_title']); ?></strong>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($event['type']); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <?php echo date('M j, Y', strtotime($event['event_date'])); ?>
                                                <br><small><?php echo date('g:i A', strtotime($event['start_time'])); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($event['city']); ?></td>
                                            <td>
                                                <span class="badge bg-secondary"><?php echo htmlspecialchars($event['role']); ?></span>
                                            </td>
                                            <td>
                                                <?php
                                                $status_class = 'secondary';
                                                if ($event['status'] == 'Confirmed') $status_class = 'success';
                                                elseif ($event['status'] == 'Cancelled') $status_class = 'danger';
                                                elseif ($event['status'] == 'Completed') $status_class = 'info';
                                                ?>
                                                <span class="badge bg-<?php echo $status_class; ?>"><?php echo htmlspecialchars($event['status']); ?></span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-primary" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <?php if ($event['status'] == 'Completed' && empty($event['feedback'])): ?>
                                                        <button class="btn btn-outline-success" title="Add Feedback">
                                                            <i class="fas fa-comment"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if ($event['status'] == 'Pending'): ?>
                                                        <button class="btn btn-outline-danger" title="Cancel Registration">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Event Registration Modal -->
<div class="modal fade" id="registerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Register for Event</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="event_id" id="register_event_id">
                    <div class="mb-3">
                        <label class="form-label">Event:</label>
                        <p class="fw-bold" id="register_event_title"></p>
                    </div>
                    <div class="mb-3">
                        <label for="role" class="form-label">Your Role *</label>
                        <select class="form-select" name="role" required>
                            <option value="">Select Role</option>
                            <option value="Attendee">Attendee</option>
                            <option value="Speaker">Speaker</option>
                            <option value="Organizer">Organizer</option>
                            <option value="Volunteer">Volunteer</option>
                            <option value="Sponsor">Sponsor</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="register_event" class="btn btn-primary">Register</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Create Event Modal -->
<div class="modal fade" id="createEventModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Event</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="event_title" class="form-label">Event Title *</label>
                            <input type="text" class="form-control" name="event_title" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="type" class="form-label">Event Type *</label>
                            <select class="form-select" name="type" required>
                                <option value="">Select Type</option>
                                <option value="Workshop">Workshop</option>
                                <option value="Seminar">Seminar</option>
                                <option value="Conference">Conference</option>
                                <option value="Networking">Networking</option>
                                <option value="Career Fair">Career Fair</option>
                                <option value="Alumni Meet">Alumni Meet</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="event_date" class="form-label">Date *</label>
                            <input type="date" class="form-control" name="event_date" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="start_time" class="form-label">Start Time *</label>
                            <input type="time" class="form-control" name="start_time" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="end_time" class="form-label">End Time *</label>
                            <input type="time" class="form-control" name="end_time" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="city" class="form-label">City *</label>
                            <input type="text" class="form-control" name="city" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="venue" class="form-label">Venue *</label>
                            <input type="text" class="form-control" name="venue" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="create_event" class="btn btn-primary">Create Event</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/custom.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle registration modal
        const registerModal = document.getElementById('registerModal');
        registerModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const eventId = button.getAttribute('data-event-id');
            const eventTitle = button.getAttribute('data-event-title');

            document.getElementById('register_event_id').value = eventId;
            document.getElementById('register_event_title').textContent = eventTitle;
        });

        // Set minimum date for event creation
        const eventDateInput = document.querySelector('input[name="event_date"]');
        if (eventDateInput) {
            const today = new Date().toISOString().split('T')[0];
            eventDateInput.setAttribute('min', today);
        }

        // Validate end time is after start time
        const startTimeInput = document.querySelector('input[name="start_time"]');
        const endTimeInput = document.querySelector('input[name="end_time"]');

        if (startTimeInput && endTimeInput) {
            function validateTimes() {
                if (startTimeInput.value && endTimeInput.value) {
                    if (endTimeInput.value <= startTimeInput.value) {
                        endTimeInput.setCustomValidity('End time must be after start time');
                    } else {
                        endTimeInput.setCustomValidity('');
                    }
                }
            }

            startTimeInput.addEventListener('change', validateTimes);
            endTimeInput.addEventListener('change', validateTimes);
        }
    });
</script>
</body>
</html>