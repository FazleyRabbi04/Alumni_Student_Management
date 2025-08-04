<?php
require_once __DIR__ . '/config/database.php'; // ✅ includes $conn

echo "<h2>Event List</h2>";

$sql = "SELECT * FROM event";
$result = $conn->query($sql); // ✅ now $conn is defined

if (!$result) {
    die("Query failed: " . $conn->error);
}

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<p><strong>" . htmlspecialchars($row['event_name']) . "</strong> on " . $row['event_date'] . "<br>";
        echo "Location: " . htmlspecialchars($row['location']) . "<br>";
        echo "Description: " . htmlspecialchars($row['description']) . "</p><hr>";
    }
} else {
    echo "No events found.";
}

$conn->close();
?>
