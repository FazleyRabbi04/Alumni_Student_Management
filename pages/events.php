<?php
session_start();
include '../navbar.php';
include '../sidebar.php';
include '../database.php';

// Simple helper to run parameterized queries
function executeQuery($query, $params = []) {
    global $conn;
    $stmt = $conn->prepare($query);
    if ($params) {
        $types = str_repeat('s', count($params)); // All strings for simplicity
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt;
}

// Check if current user is alumni
function isAlumni($user_id) {
    $stmt = executeQuery("SELECT 1 FROM alumni WHERE person_id = ?", [$user_id]);
    $stmt->store_result();
    return $stmt->num_rows > 0;
}

// Handle Add Event
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_event']) && isAlumni($_SESSION['user_id'])) {
    $query = "INSERT INTO events (event_title, event_date, city, venue, type, start_time, end_time)
              VALUES (?, ?, ?, ?, ?, ?, ?)";
    executeQuery($query, [
        $_POST['event_title'],
        $_POST['event_date'],
        $_POST['city'],
        $_POST['venue'],
        $_POST['type'],
        $_POST['start_time'],
        $_POST['end_time']
    ]);
    header("Location: events.php"); // Redirect to prevent resubmission
    exit;
}

// Handle Delete Event
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_event']) && isAlumni($_SESSION['user_id'])) {
    executeQuery("DELETE FROM events WHERE event_id = ?", [$_POST['event_id']]);
    header("Location: events.php");
    exit;
}
?>

<div class="main-content">
    <div class="header">
        <h2>Upcoming Events</h2>
    </div>

    <?php if (isAlumni($_SESSION['user_id'])): ?>
    <div class="add-event-form">
        <h3>Add New Event</h3>
        <form method="POST" action="events.php">
            <input type="text" name="event_title" required placeholder="Title"><br>
            <input type="date" name="event_date" required><br>
            <input type="text" name="city" required placeholder="City"><br>
            <input type="text" name="venue" required placeholder="Venue"><br>
            <select name="type" required>
                <option value="">Select Type</option>
                <option>Workshop</option>
                <option>Seminar</option>
                <option>Conference</option>
                <option>Networking</option>
                <option>Career Fair</option>
                <option>Alumni Meet</option>
            </select><br>
            <input type="time" name="start_time" required><br>
            <input type="time" name="end_time" required><br>
            <button type="submit" name="add_event">Add Event</button>
        </form>
    </div>
    <?php endif; ?>

    <div class="event-list">
        <?php
        $today = date('Y-m-d');
        $query = "SELECT * FROM events WHERE event_date >= ? ORDER BY event_date ASC";
        $stmt = executeQuery($query, [$today]);
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            while ($event = $result->fetch_assoc()) {
        ?>
                <div class="event-card">
                    <h3><?php echo htmlspecialchars($event['event_title']); ?></h3>
                    <p><strong>Date:</strong> <?php echo date('F j, Y', strtotime($event['event_date'])); ?></p>
                    <p><strong>Time:</strong>
                        <?php echo date('h:i A', strtotime($event['start_time'])) . ' - ' . date('h:i A', strtotime($event['end_time'])); ?>
                    </p>
                    <p><strong>City:</strong> <?php echo htmlspecialchars($event['city']); ?></p>
                    <p><strong>Venue:</strong> <?php echo htmlspecialchars($event['venue']); ?></p>
                    <p><strong>Type:</strong> <?php echo htmlspecialchars($event['type']); ?></p>

                    <?php if (isAlumni($_SESSION['user_id'])): ?>
                        <form method="POST" style="margin-top:10px;">
                            <input type="hidden" name="event_id" value="<?php echo $event['event_id']; ?>">
                            <button type="submit" name="delete_event" onclick="return confirm('Delete this event?')">Delete</button>
                        </form>
                    <?php endif; ?>
                </div>
        <?php
            }
        } else {
            echo "<p>No upcoming events found.</p>";
        }
        ?>
    </div>
</div>

<?php include 'footer.php'; ?>
