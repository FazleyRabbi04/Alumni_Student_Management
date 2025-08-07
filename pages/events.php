<?php
require_once '../config/database.php';
startSecureSession();

// Initialize variables
$events = [];
$error_message = '';
$success_message = '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$event_type = isset($_GET['event_type']) ? trim($_GET['event_type']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$events_per_page = 6;

// Function to check if user is an alumnus
function isAlumni($user_id) {
    $query = "SELECT COUNT(*) FROM alumni WHERE person_id = ?";
    $stmt = executeQuery($query, [$user_id]);
    if ($stmt === false) {
        error_log("Error checking alumni status for user_id $user_id");
        return false;
    }
    return $stmt->fetchColumn() > 0;
}

// Function to check if user can edit an event
function canEditEvent($user_id, $event_id) {
    $query = "SELECT COUNT(*) FROM registers WHERE person_id = ? AND event_id = ? AND role IN ('Organizer', 'Speaker')";
    $stmt = executeQuery($query, [$user_id, $event_id]);
    if ($stmt === false) {
        error_log("Error checking edit permission for user_id $user_id, event_id $event_id");
        return false;
    }
    return $stmt->fetchColumn() > 0;
}

// Handle event creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_event' && isLoggedIn() && isAlumni($_SESSION['user_id'])) {
    $title = sanitizeInput($_POST['event_title'] ?? '');
    $date = sanitizeInput($_POST['event_date'] ?? '');
    $city = sanitizeInput($_POST['city'] ?? '');
    $venue = sanitizeInput($_POST['venue'] ?? '');
    $type = sanitizeInput($_POST['type'] ?? '');
    $start_time = sanitizeInput($_POST['start_time'] ?? '');
    $end_time = sanitizeInput($_POST['end_time'] ?? '');

    if ($title && $date && $city && $venue && $type && $start_time && $end_time) {
        try {
            $query = "INSERT INTO events (event_title, event_date, city, venue, type, start_time, end_time) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = executeQuery($query, [$title, $date, $city, $venue, $type, $start_time, $end_time]);
            if ($stmt) {
                $event_id = getLastInsertId();
                $query = "INSERT INTO registers (person_id, event_id, role, response, status) VALUES (?, ?, 'Organizer', 'Attending', 'Confirmed')";
                $stmt = executeQuery($query, [$_SESSION['user_id'], $event_id]);
                if ($stmt) {
                    $success_message = "Event created successfully!";
                    logActivity($_SESSION['user_id'], 'Event Created', "Created event: $title (ID: $event_id)");
                } else {
                    $error_message = "Error registering organizer for event.";
                    error_log("Error registering organizer for event ID: $event_id");
                }
            } else {
                $error_message = "Error creating event.";
                error_log("Error executing INSERT query for event: $title");
            }
        } catch (Exception $e) {
            $error_message = "Error creating event: " . $e->getMessage();
            error_log("Exception in event creation: " . $e->getMessage());
        }
    } else {
        $error_message = "All fields are required.";
    }
}

// Handle event editing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_event' && isLoggedIn()) {
    $event_id = (int)($_POST['event_id'] ?? 0);
    if (canEditEvent($_SESSION['user_id'], $event_id)) {
        $title = sanitizeInput($_POST['event_title'] ?? '');
        $date = sanitizeInput($_POST['event_date'] ?? '');
        $city = sanitizeInput($_POST['city'] ?? '');
        $venue = sanitizeInput($_POST['venue'] ?? '');
        $type = sanitizeInput($_POST['type'] ?? '');
        $start_time = sanitizeInput($_POST['start_time'] ?? '');
        $end_time = sanitizeInput($_POST['end_time'] ?? '');

        if ($title && $date && $city && $venue && $type && $start_time && $end_time) {
            try {
                $query = "UPDATE events SET event_title = ?, event_date = ?, city = ?, venue = ?, type = ?, start_time = ?, end_time = ? WHERE event_id = ?";
                $stmt = executeQuery($query, [$title, $date, $city, $venue, $type, $start_time, $end_time, $event_id]);
                if ($stmt) {
                    $success_message = "Event updated successfully!";
                    logActivity($_SESSION['user_id'], 'Event Updated', "Updated event ID: $event_id");
                } else {
                    $error_message = "Error updating event.";
                    error_log("Error executing UPDATE query for event ID: $event_id");
                }
            } catch (Exception $e) {
                $error_message = "Error updating event: " . $e->getMessage();
                error_log("Exception in event update: " . $e->getMessage());
            }
        } else {
            $error_message = "All fields are required.";
        }
    } else {
        $error_message = "You are not authorized to edit this event.";
    }
}

// Handle event registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register_event' && isLoggedIn()) {
    $event_id = (int)($_POST['event_id'] ?? 0);
    try {
        $query = "INSERT INTO registers (person_id, event_id, role, response, status) VALUES (?, ?, 'Attendee', 'Attending', 'Pending')";
        $stmt = executeQuery($query, [$_SESSION['user_id'], $event_id]);
        if ($stmt) {
            $success_message = "Registered for event successfully! Awaiting confirmation.";
            logActivity($_SESSION['user_id'], 'Event Registration', "Registered for event ID: $event_id");
        } else {
            $error_message = "Error registering for event.";
            error_log("Error executing INSERT query for registration, event ID: $event_id");
        }
    } catch (Exception $e) {
        $error_message = "Error registering for event: " . $e->getMessage();
        error_log("Exception in event registration: " . $e->getMessage());
    }
}

// Get distinct event types for filter dropdown
$event_types = [];
try {
    $query = "SELECT DISTINCT type FROM events";
    $stmt = executeQuery($query);
    if ($stmt) {
        $event_types = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } else {
        error_log("Error fetching event types: executeQuery returned false");
    }
} catch (Exception $e) {
    error_log("Exception fetching event types: " . $e->getMessage());
}

// Build the database query for events
try {
    // Step 1: Build count query (for pagination)
    $count_query = "SELECT COUNT(*) FROM events WHERE 1=1";
    $count_params = [];

    if (!empty($search)) {
        $count_query .= " AND (event_title LIKE ? OR city LIKE ? OR venue LIKE ?)";
        $count_params[] = '%' . $search . '%';
        $count_params[] = '%' . $search . '%';
        $count_params[] = '%' . $search . '%';
    }

    if (!empty($event_type)) {
        $count_query .= " AND type = ?";
        $count_params[] = $event_type;
    }

    $count_stmt = executeQuery($count_query, $count_params);
    if ($count_stmt === false) {
        throw new Exception("Failed to execute count query");
    }

    $total_events = $count_stmt->fetchColumn();
    $total_pages = ceil($total_events / $events_per_page);

    // Step 2: Build main event query
    $query = "SELECT * FROM events WHERE 1=1";
    $params = [];

    if (!empty($search)) {
        $query .= " AND (event_title LIKE ? OR city LIKE ? OR venue LIKE ?)";
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }

    if (!empty($event_type)) {
        $query .= " AND type = ?";
        $params[] = $event_type;
    }

    $offset = ($page - 1) * $events_per_page;
    $query .= " ORDER BY event_date ASC LIMIT " . (int)$offset . ", " . (int)$events_per_page;

    $stmt = executeQuery($query, $params);
    if ($stmt === false) {
        throw new Exception("Failed to execute event query");
    }

    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Step 3: Get registration status
    $user_registrations = [];
    if (isLoggedIn()) {
        $query = "SELECT event_id, role, response, status FROM registers WHERE person_id = ?";
        $stmt = executeQuery($query, [$_SESSION['user_id']]);
        if ($stmt) {
            $registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($registrations as $reg) {
                $user_registrations[$reg['event_id']] = $reg;
            }
        } else {
            error_log("Error fetching user registrations for user_id: {$_SESSION['user_id']}");
        }
    }
} catch (Exception $e) {
    $error_message = "Oops! Something went wrong while loading the events. Please try again later.";
    $debug_info = [
        'message' => $e->getMessage(),
        'query' => $query,
        'params' => $params,
        'user_id' => $_SESSION['user_id'] ?? 'guest',
        'time' => date('Y-m-d H:i:s')
    ];
    error_log("EVENT LOAD ERROR: " . json_encode($debug_info));

    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get registration status for logged-in user
    $user_registrations = [];
    if (isLoggedIn()) {
        $query = "SELECT event_id, role, response, status FROM registers WHERE person_id = ?";
        $stmt = executeQuery($query, [$_SESSION['user_id']]);
        if ($stmt) {
            $registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($registrations as $reg) {
                $user_registrations[$reg['event_id']] = $reg;
            }
        } else {
            error_log("Error fetching user registrations for user_id: {$_SESSION['user_id']}");
        }
    }
}
catch (Exception $e) {
    $error_message = "Oops! Something went wrong while loading the events. Please try again later.";
    
    // Detailed logging for developers only
    $debug_info = [
        'message' => $e->getMessage(),
        'query' => $query,
        'params' => $params,
        'user_id' => $_SESSION['user_id'] ?? 'guest',
        'time' => date('Y-m-d H:i:s')
    ];
    error_log("EVENT LOAD ERROR: " . json_encode($debug_info));
}


// Separate events into upcoming and past
$currentDate = new DateTime();
$upcomingEvents = [];
$pastEvents = [];

foreach ($events as $event) {
    $eventDate = new DateTime($event['event_date']);
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet" />

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />

    <!-- AOS Animation CSS -->
    <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet" />

    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa;
            color: #002147;
        }
        .hero {
            background: linear-gradient(90deg, #3484ff 0%, #3fcfff 100%);
            color: #fff;
            padding: 60px 20px 30px 20px;
            text-align: center;
        }
        .hero h1 {
            font-weight: 700;
            font-size: 2.5rem;
        }
        .event-card {
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            background-color: #fff;
            padding: 28px 24px;
            transition: all 0.28s cubic-bezier(.4,0,.2,1);
            height: 100%;
        }
        .event-card:hover {
            box-shadow: 0 12px 30px rgba(52, 132, 255, 0.11);
            transform: translateY(-3px) scale(1.015);
        }
        .filter-form {
            margin-bottom: 2rem;
        }
        .pagination {
            justify-content: center;
            margin-top: 2rem;
        }
        .modal-content {
            border-radius: 12px;
        }
        @media (max-width: 576px) {
            .filter-form .input-group {
                flex-direction: column;
            }
            .filter-form .form-control,
            .filter-form .form-select {
                width: 100%;
                margin-bottom: 0.5rem;
            }
        }
    </style>
</head>
<body>

<?php include '..\includes\navbar.php'; ?>

<!-- Hero Section -->
<section class="hero" data-aos="fade-up">
    <div class="container">
        <h1 class="display-5">Events</h1>
        <?php if (isLoggedIn() && isAlumni($_SESSION['user_id'])): ?>
            <button class="btn btn-light mt-3 shadow" data-bs-toggle="modal" data-bs-target="#addEventModal">Add New Event</button>
        <?php endif; ?>
    </div>
</section>

<!-- Filter and Search Section -->
<section class="py-4">
    <div class="container">
        <form class="filter-form" method="GET">
            <div class="input-group">
                <input type="text" name="search" class="form-control" placeholder="Search by title, city, or venue" value="<?php echo htmlspecialchars($search); ?>">
                <select name="event_type" class="form-select">
                    <option value="">All Event Types</option>
                    <?php foreach ($event_types as $type): ?>
                        <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $event_type === $type ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($type); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary">Filter</button>
            </div>
        </form>
    </div>
</section>

<!-- Events Section -->
<section class="py-5">
    <div class="container">
        <?php if ($success_message): ?>
            <div class="alert alert-success text-center"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert alert-danger text-center"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        
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
                            <p class="text-center text-muted py-5">
                                <?php echo empty($error_message) ? 'No upcoming events found.' : ''; ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($upcomingEvents as $event): ?>
                            <div class="col-md-6">
                                <div class="event-card h-100">
                                    <h5><?php echo htmlspecialchars($event['event_title']); ?></h5>
                                    <p><strong>Date:</strong> <?php echo date('F j, Y', strtotime($event['event_date'])); ?></p>
                                    <p><strong>Time:</strong> <?php echo htmlspecialchars($event['start_time']) . ' - ' . htmlspecialchars($event['end_time']); ?></p>
                                    <p><strong>Venue:</strong> <?php echo htmlspecialchars($event['venue'] . ', ' . $event['city']); ?></p>
                                    <p><strong>Type:</strong> <?php echo htmlspecialchars($event['type']); ?></p>
                                    <?php if (isLoggedIn() && isset($user_registrations[$event['event_id']])): ?>
                                        <p><strong>Status:</strong> <?php echo htmlspecialchars($user_registrations[$event['event_id']]['status']); ?> (<?php echo htmlspecialchars($user_registrations[$event['event_id']]['role']); ?>)</p>
                                    <?php endif; ?>
                                    <div class="mt-3">
                                        <?php if (isLoggedIn()): ?>
                                            <?php if (canEditEvent($_SESSION['user_id'], $event['event_id'])): ?>
                                                <button class="btn btn-warning me-2" data-bs-toggle="modal" data-bs-target="#editEventModal" data-event-id="<?php echo $event['event_id']; ?>" data-title="<?php echo htmlspecialchars($event['event_title']); ?>" data-date="<?php echo $event['event_date']; ?>" data-city="<?php echo htmlspecialchars($event['city']); ?>" data-venue="<?php echo htmlspecialchars($event['venue']); ?>" data-type="<?php echo htmlspecialchars($event['type']); ?>" data-start-time="<?php echo htmlspecialchars($event['start_time']); ?>" data-end-time="<?php echo htmlspecialchars($event['end_time']); ?>">Edit Event</button>
                                            <?php endif; ?>
                                            <?php if (!isset($user_registrations[$event['event_id']])): ?>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="action" value="register_event">
                                                    <input type="hidden" name="event_id" value="<?php echo $event['event_id']; ?>">
                                                    <button type="submit" class="btn btn-primary">Register for Event</button>
                                                </form>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <a href="../auth/signin.php" class="btn btn-primary">Sign In to Register</a>
                                        <?php endif; ?>
                                    </div>
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
                            <p class="text-center text-muted py-5">
                                <?php echo empty($error_message) ? 'No past events found.' : ''; ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($pastEvents as $event): ?>
                            <div class="col-md-6">
                                <div class="event-card h-100">
                                    <h5><?php echo htmlspecialchars($event['event_title']); ?></h5>
                                    <p><strong>Date:</strong> <?php echo date('F j, Y', strtotime($event['event_date'])); ?></p>
                                    <p><strong>Time:</strong> <?php echo htmlspecialchars($event['start_time']) . ' - ' . htmlspecialchars($event['end_time']); ?></p>
                                    <p><strong>Venue:</strong> <?php echo htmlspecialchars($event['venue'] . ', ' . $event['city']); ?></p>
                                    <p><strong>Type:</strong> <?php echo htmlspecialchars($event['type']); ?></p>
                                    <a href="#" class="btn btn-secondary">View Details</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation">
                <ul class="pagination">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&event_type=<?php echo urlencode($event_type); ?>">Previous</a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&event_type=<?php echo urlencode($event_type); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&event_type=<?php echo urlencode($event_type); ?>">Next</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</section>

<!-- Add Event Modal -->
<div class="modal fade" id="addEventModal" tabindex="-1" aria-labelledby="addEventModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addEventModalLabel">Add New Event</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_event">
                    <div class="mb-3">
                        <label for="event_title" class="form-label">Event Title</label>
                        <input type="text" class="form-control" id="event_title" name="event_title" required>
                    </div>
                    <div class="mb-3">
                        <label for="event_date" class="form-label">Date</label>
                        <input type="date" class="form-control" id="event_date" name="event_date" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="city" class="form-label">City</label>
                        <input type="text" class="form-control" id="city" name="city" required>
                    </div>
                    <div class="mb-3">
                        <label for="venue" class="form-label">Venue</label>
                        <input type="text" class="form-control" id="venue" name="venue" required>
                    </div>
                    <div class="mb-3">
                        <label for="type" class="form-label">Event Type</label>
                        <select class="form-select" id="type" name="type" required>
                            <option value="Workshop">Workshop</option>
                            <option value="Seminar">Seminar</option>
                            <option value="Conference">Conference</option>
                            <option value="Networking">Networking</option>
                            <option value="Career Fair">Career Fair</option>
                            <option value="Alumni Meet">Alumni Meet</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="start_time" class="form-label">Start Time</label>
                        <input type="time" class="form-control" id="start_time" name="start_time" required>
                    </div>
                    <div class="mb-3">
                        <label for="end_time" class="form-label">End Time</label>
                        <input type="time" class="form-control" id="end_time" name="end_time" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Add Event</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Event Modal -->
<div class="modal fade" id="editEventModal" tabindex="-1" aria-labelledby="editEventModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editEventModalLabel">Edit Event</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_event">
                    <input type="hidden" name="event_id" id="edit_event_id">
                    <div class="mb-3">
                        <label for="edit_event_title" class="form-label">Event Title</label>
                        <input type="text" class="form-control" id="edit_event_title" name="event_title" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_event_date" class="form-label">Date</label>
                        <input type="date" class="form-control" id="edit_event_date" name="event_date" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="edit_city" class="form-label">City</label>
                        <input type="text" class="form-control" id="edit_city" name="city" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_venue" class="form-label">Venue</label>
                        <input type="text" class="form-control" id="edit_venue" name="venue" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_type" class="form-label">Event Type</label>
                        <select class="form-select" id="edit_type" name="type" required>
                            <option value="Workshop">Workshop</option>
                            <option value="Seminar">Seminar</option>
                            <option value="Conference">Conference</option>
                            <option value="Networking">Networking</option>
                            <option value="Career Fair">Career Fair</option>
                            <option value="Alumni Meet">Alumni Meet</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_start_time" class="form-label">Start Time</label>
                        <input type="time" class="form-control" id="edit_start_time" name="start_time" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_end_time" class="form-label">End Time</label>
                        <input type="time" class="form-control" id="edit_end_time" name="end_time" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Footer -->
<footer class="footer text-center mt-4 py-4 bg-white border-top">
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
        <div class="small mb-2 text-muted">&copy; 2025 ABC University. All rights reserved.</div>
    </div>
</footer>

<!-- JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
<script>
    AOS.init({ duration: 1000, once: true });

    // Populate edit modal with event data
    const editEventModal = document.getElementById('editEventModal');
    if (editEventModal) {
        editEventModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const eventId = button.getAttribute('data-event-id');
            const title = button.getAttribute('data-title');
            const date = button.getAttribute('data-date');
            const city = button.getAttribute('data-city');
            const venue = button.getAttribute('data-venue');
            const type = button.getAttribute('data-type');
            const startTime = button.getAttribute('data-start-time');
            const endTime = button.getAttribute('data-end-time');

            const modal = this;
            modal.querySelector('#edit_event_id').value = eventId;
            modal.querySelector('#edit_event_title').value = title;
            modal.querySelector('#edit_event_date').value = date;
            modal.querySelector('#edit_city').value = city;
            modal.querySelector('#edit_venue').value = venue;
            modal.querySelector('#edit_type').value = type;
            modal.querySelector('#edit_start_time').value = startTime;
            modal.querySelector('#edit_end_time').value = endTime;
        });
    }
</script>
</body>
</html>
