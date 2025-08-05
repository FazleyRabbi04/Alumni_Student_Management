<?php
require_once '../config/database.php';
startSecureSession();

// Helper to check login
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Helper to check if user is alumni
function isAlumni($id) {
    global $conn;
    $stmt = $conn->prepare("SELECT 1 FROM alumni WHERE person_id = ?");
    $stmt->execute([$id]);
    return $stmt->fetchColumn();
}

// Add Event (for alumni only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_event']) && isAlumni($_SESSION['user_id'])) {
    $sql = "INSERT INTO events (event_title, event_date, city, venue, type, start_time, end_time)
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        $_POST['event_title'],
        $_POST['event_date'],
        $_POST['city'],
        $_POST['venue'],
        $_POST['type'],
        $_POST['start_time'],
        $_POST['end_time']
    ]);
    header("Location: events.php");
    exit;
}

// Delete Event (for alumni only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_event']) && isAlumni($_SESSION['user_id'])) {
    $stmt = $conn->prepare("DELETE FROM events WHERE event_id = ?");
    $stmt->execute([$_POST['event_id']]);
    header("Location: events.php");
    exit;
}

// Get events
$events = [];
try {
    $query = "SELECT * FROM events ORDER BY event_date ASC";
    $stmt = $conn->query($query);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error loading events: " . $e->getMessage());
}

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

<!-- Include your existing HTML and styling from the previous version -->
<!-- Insert the following inside the Upcoming Events tab after the heading -->
<?php if (isAlumni($_SESSION['user_id'])): ?>
<div class="card shadow-sm mb-4">
    <div class="card-header bg-primary text-white">Add New Event</div>
    <div class="card-body">
        <form method="POST">
            <div class="row g-3">
                <div class="col-md-6">
                    <input type="text" name="event_title" class="form-control" placeholder="Title" required>
                </div>
                <div class="col-md-6">
                    <input type="date" name="event_date" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <input type="text" name="city" class="form-control" placeholder="City" required>
                </div>
                <div class="col-md-4">
                    <input type="text" name="venue" class="form-control" placeholder="Venue" required>
                </div>
                <div class="col-md-4">
                    <select name="type" class="form-select" required>
                        <option value="">Select Type</option>
                        <option>Workshop</option>
                        <option>Seminar</option>
                        <option>Conference</option>
                        <option>Networking</option>
                        <option>Career Fair</option>
                        <option>Alumni Meet</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Start Time</label>
                    <input type="time" name="start_time" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">End Time</label>
                    <input type="time" name="end_time" class="form-control" required>
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" name="add_event" class="btn btn-success">Add Event</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Add Delete buttons inside each upcoming event card for alumni -->
<?php if (isAlumni($_SESSION['user_id'])): ?>
<form method="POST" onsubmit="return confirm('Delete this event?')">
    <input type="hidden" name="event_id" value="<?= $event['event_id'] ?>">
    <button type="submit" name="delete_event" class="btn btn-outline-danger btn-sm mt-2">Delete</button>
</form>
<?php endif; ?>
