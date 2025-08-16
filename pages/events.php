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

// Function to check if user is a student
function isStudent($user_id) {
    $query = "SELECT COUNT(*) FROM student WHERE person_id = ?";
    $stmt = executeQuery($query, [$user_id]);
    if ($stmt === false) {
        error_log("Error checking student status for user_id $user_id");
        return false;
    }
    return $stmt->fetchColumn() > 0;
}

// Function to check if user is an organizer for an event
function isOrganizer($user_id, $event_id) {
    $query = "SELECT COUNT(*) FROM registers WHERE person_id = ? AND event_id = ? AND role = 'Organizer'";
    $stmt = executeQuery($query, [$user_id, $event_id]);
    if ($stmt === false) {
        error_log("Error checking organizer status for user_id $user_id, event_id $event_id");
        return false;
    }
    return $stmt->fetchColumn() > 0;
}

// Function to get total confirmed participants for an event
function getTotalParticipants($event_id) {
    $query = "SELECT COUNT(*) FROM registers WHERE event_id = ? AND response_status = 'Confirmed'";
    $stmt = executeQuery($query, [$event_id]);
    return $stmt ? $stmt->fetchColumn() : 0;
}

// Function to check if event has an organizer
function eventHasOrganizer($event_id) {
    $query = "SELECT COUNT(*) FROM registers WHERE event_id = ? AND role = 'Organizer' AND response_status = 'Confirmed'";
    $stmt = executeQuery($query, [$event_id]);
    return $stmt ? $stmt->fetchColumn() > 0 : false;
}

// Function to get user's registration status for an event
function getUserRegistrationStatus($user_id, $event_id) {
    $query = "SELECT response_status, role FROM registers WHERE person_id = ? AND event_id = ?";
    $stmt = executeQuery($query, [$user_id, $event_id]);
    return $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
}

// Function to check if event is completed (past event date)
function isEventCompleted($event_date) {
    $current_date = new DateTime('2025-08-16');
    $event_datetime = new DateTime($event_date);
    return $event_datetime < $current_date;
}

// ADDED: Handle AJAX request to fetch all feedback for an event
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'fetch_all_feedback' && isLoggedIn()) {
    header('Content-Type: application/json');
    $event_id = (int)($_POST['event_id'] ?? 0);

    // Check if user has permission to view feedback (organizer or confirmed participant)
    $has_permission = false;
    if (isOrganizer($_SESSION['user_id'], $event_id)) {
        $has_permission = true;
    } else {
        $user_reg = getUserRegistrationStatus($_SESSION['user_id'], $event_id);
        if ($user_reg && $user_reg['response_status'] === 'Confirmed') {
            $has_permission = true;
        }
    }

    if (!$has_permission) {
        echo json_encode(['error' => 'You do not have permission to view feedback for this event.']);
        exit;
    }

    try {
        $query = "SELECT r.feedback, r.role,
                         CONCAT(p.first_name, ' ', p.last_name) AS full_name,
                         CASE 
                             WHEN a.person_id IS NOT NULL THEN 'Alumni'
                             WHEN s.person_id IS NOT NULL THEN 'Student'
                             ELSE 'Unknown'
                         END AS user_status
                  FROM registers r 
                  JOIN person p ON r.person_id = p.person_id 
                  LEFT JOIN alumni a ON r.person_id = a.person_id 
                  LEFT JOIN student s ON r.person_id = s.person_id 
                  WHERE r.event_id = ? AND r.response_status = 'Confirmed' AND r.feedback IS NOT NULL AND r.feedback != ''
                  ORDER BY r.person_id";

        $stmt = executeQuery($query, [$event_id]);
        if ($stmt) {
            $feedback = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'feedback' => $feedback]);
        } else {
            echo json_encode(['error' => 'Error fetching feedback data.']);
        }
    } catch (Exception $e) {
        echo json_encode(['error' => 'Database error occurred.']);
        error_log("Exception fetching all feedback: " . $e->getMessage());
    }
    exit;
}

// Handle AJAX request to fetch registrants
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'fetch_registrants' && isLoggedIn()) {
    header('Content-Type: application/json');
    $event_id = (int)($_POST['event_id'] ?? 0);
    if (!isOrganizer($_SESSION['user_id'], $event_id)) {
        echo json_encode([]);
        error_log("Unauthorized attempt to fetch registrants for event_id: $event_id by user_id: {$_SESSION['user_id']}");
        exit;
    }
    try {
        $query = "SELECT r.person_id, r.role, r.response_status, r.feedback,
                         CONCAT(p.first_name, ' ', p.last_name) AS full_name,
                         CASE 
                             WHEN a.person_id IS NOT NULL THEN 'Alumni'
                             WHEN s.person_id IS NOT NULL THEN 'Student'
                             ELSE 'Unknown'
                         END AS user_status,
                         (SELECT phone_number FROM person_phone pp WHERE pp.person_id = r.person_id LIMIT 1) AS primary_phone,
                         (SELECT email FROM email_address ea WHERE ea.person_id = r.person_id LIMIT 1) AS primary_email
                  FROM registers r 
                  JOIN person p ON r.person_id = p.person_id 
                  LEFT JOIN alumni a ON r.person_id = a.person_id 
                  LEFT JOIN student s ON r.person_id = s.person_id 
                  WHERE r.event_id = ?";
        $stmt = executeQuery($query, [$event_id]);
        if ($stmt) {
            $registrants = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($registrants);
        } else {
            echo json_encode([]);
            error_log("Error fetching registrants for event_id: $event_id");
        }
    } catch (Exception $e) {
        echo json_encode([]);
        error_log("Exception fetching registrants: " . $e->getMessage());
    }
    exit;
}

// Handle event creation (Alumni only - always becomes organizer)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_event' && isLoggedIn() && isAlumni($_SESSION['user_id'])) {
    $title = sanitizeInput($_POST['event_title'] ?? '');
    $date = sanitizeInput($_POST['event_date'] ?? '');
    $city = sanitizeInput($_POST['city'] ?? '');
    $venue = sanitizeInput($_POST['venue'] ?? '');
    $type = sanitizeInput($_POST['type'] ?? '');
    $start_time = sanitizeInput($_POST['start_time'] ?? '');
    $end_time = sanitizeInput($_POST['end_time'] ?? '');

    $event_date = new DateTime($date);
    $current_date = new DateTime('2025-08-16 01:47:00'); // 01:47 AM +06, August 16, 2025
    $start_datetime = DateTime::createFromFormat('Y-m-d H:i', "$date $start_time");
    $end_datetime = DateTime::createFromFormat('Y-m-d H:i', "$date $end_time");

    if ($title && $date && $city && $venue && $type && $start_time && $end_time) {
        if ($event_date < $current_date) {
            $error_message = "Event date must be in the future.";
        } elseif ($start_datetime >= $end_datetime) {
            $error_message = "Start time must be before end time.";
        } else {
            try {
                $db = getDatabaseConnection();
                $query = "INSERT INTO events (event_title, event_date, city, venue, type, start_time, end_time) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([$title, $date, $city, $venue, $type, $start_time, $end_time]);
                if ($stmt) {
                    $event_id = $db->lastInsertId();
                    // Alumni who creates event automatically becomes organizer with confirmed status
                    $query = "INSERT INTO registers (person_id, event_id, role, response_status) VALUES (?, ?, 'Organizer', 'Confirmed')";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$_SESSION['user_id'], $event_id]);
                    if ($stmt) {
                        $success_message = "Event created successfully! You are now the organizer.";
                        logActivity($_SESSION['user_id'], 'Event Created', "Created event: $title (ID: $event_id) as Organizer");
                    } else {
                        $error_message = "Failed to register user as organizer.";
                        error_log("Error registering user as organizer for event ID: $event_id - " . implode(" ", $db->errorInfo()));
                    }
                } else {
                    $error_message = "Failed to Register Event.";
                    error_log("Error executing INSERT query for event: $title - " . implode(" ", $db->errorInfo()));
                }
            } catch (Exception $e) {
                $error_message = "Error creating event: " . $e->getMessage();
                error_log("Exception in event creation: " . $e->getMessage());
            }
        }
    } else {
        $error_message = "All fields are required.";
    }
}

// Handle join event request (Students or Alumni who are not organizers)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'join_event' && isLoggedIn()) {
    $event_id = (int)($_POST['event_id'] ?? 0);
    $user_id = $_SESSION['user_id'];
    // Role selection based on user type
    $requested_role = sanitizeInput($_POST['requested_role'] ?? 'Attendee');

    // If no role selected in form, show selection prompt for alumni
    if (!isset($_POST['requested_role']) && isAlumni($user_id)) {
        // This will be handled in the frontend modal
        $allowed_roles = ['Attendee', 'Speaker', 'Volunteer', 'Sponsor'];
    } elseif (!isset($_POST['requested_role'])) {
        $requested_role = 'Attendee'; // Default for students
    }

    // Check if event exists and has an organizer
    if (!eventHasOrganizer($event_id)) {
        $error_message = "This event does not have an organizer. Cannot join.";
    } else {
        // Check current registration status
        $current_registration = getUserRegistrationStatus($user_id, $event_id);

        if ($current_registration && in_array($current_registration['response_status'], ['Pending', 'Confirmed'])) {
            $error_message = "You already have an active registration for this event.";
        } elseif (isOrganizer($user_id, $event_id)) {
            $error_message = "You are the organizer of this event.";
        } else {
            // Validate role based on user type
            $allowed_roles = ['Attendee']; // Default for students
            if (isAlumni($user_id)) {
                $allowed_roles = ['Attendee', 'Speaker', 'Volunteer', 'Sponsor'];
            }

            if (!in_array($requested_role, $allowed_roles)) {
                $requested_role = 'Attendee'; // Fallback to attendee
            }

            try {
                // If user was previously cancelled/rejected, delete old record and create new one
                if ($current_registration) {
                    $query = "DELETE FROM registers WHERE person_id = ? AND event_id = ?";
                    executeQuery($query, [$user_id, $event_id]);
                }

                $query = "INSERT INTO registers (person_id, event_id, role, response_status) VALUES (?, ?, ?, 'Pending')";
                $stmt = executeQuery($query, [$user_id, $event_id, $requested_role]);
                if ($stmt) {
                    $success_message = "Join request submitted successfully! Awaiting organizer approval.";
                    logActivity($user_id, 'Event Join Request', "Requested to join event ID: $event_id as $requested_role");
                } else {
                    $error_message = "Error submitting join request.";
                    error_log("Error executing INSERT query for join request, event ID: $event_id");
                }
            } catch (Exception $e) {
                $error_message = "Error submitting join request: " . $e->getMessage();
                error_log("Exception in join request: " . $e->getMessage());
            }
        }
    }
}

// Handle event editing (Organizer only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_event' && isLoggedIn()) {
    $event_id = (int)($_POST['event_id'] ?? 0);
    if (!isOrganizer($_SESSION['user_id'], $event_id)) {
        $error_message = "You are not authorized to edit this event.";
        error_log("Unauthorized edit attempt for event_id: $event_id by user_id: {$_SESSION['user_id']}");
    } else {
        $title = sanitizeInput($_POST['event_title'] ?? '');
        $date = sanitizeInput($_POST['event_date'] ?? '');
        $city = sanitizeInput($_POST['city'] ?? '');
        $venue = sanitizeInput($_POST['venue'] ?? '');
        $type = sanitizeInput($_POST['type'] ?? '');
        $start_time = sanitizeInput($_POST['start_time'] ?? '');
        $end_time = sanitizeInput($_POST['end_time'] ?? '');

        $event_date = new DateTime($date);
        $current_date = new DateTime('2025-08-16 01:47:00');
        $start_datetime = DateTime::createFromFormat('Y-m-d H:i', "$date $start_time");
        $end_datetime = DateTime::createFromFormat('Y-m-d H:i', "$date $end_time");

        if ($title && $date && $city && $venue && $type && $start_time && $end_time) {
            if ($event_date < $current_date) {
                $error_message = "Event date must be in the future.";
            } elseif ($start_datetime >= $end_datetime) {
                $error_message = "Start time must be before end time.";
            } else {
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
            }
        } else {
            $error_message = "All fields are required.";
        }
    }
}

// Handle event deletion (Organizer only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_event' && isLoggedIn()) {
    $event_id = (int)($_POST['event_id'] ?? 0);
    if (!isOrganizer($_SESSION['user_id'], $event_id)) {
        $error_message = "You are not authorized to delete this event.";
        error_log("Unauthorized delete attempt for event_id: $event_id by user_id: {$_SESSION['user_id']}");
    } else {
        try {
            $del_reg_query = "DELETE FROM registers WHERE event_id = ?";
            executeQuery($del_reg_query, [$event_id]);
            $del_event_query = "DELETE FROM events WHERE event_id = ?";
            $stmt = executeQuery($del_event_query, [$event_id]);
            if ($stmt) {
                $success_message = "Event deleted successfully!";
                logActivity($_SESSION['user_id'], 'Event Deleted', "Deleted event ID: $event_id");
            } else {
                $error_message = "Error deleting event.";
                error_log("Error deleting event, event_id: $event_id");
            }
        } catch (Exception $e) {
            $error_message = "Error deleting event: " . $e->getMessage();
            error_log("Exception in event deletion: " . $e->getMessage());
        }
    }
}

// Handle cancelling join request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_join_request' && isLoggedIn()) {
    $event_id = (int)($_POST['event_id'] ?? 0);
    $user_id = $_SESSION['user_id'];

    $current_registration = getUserRegistrationStatus($user_id, $event_id);

    if ($current_registration && in_array($current_registration['response_status'], ['Pending', 'Confirmed']) && !isOrganizer($user_id, $event_id)) {
        try {
            $query = "DELETE FROM registers WHERE person_id = ? AND event_id = ?";
            $stmt = executeQuery($query, [$user_id, $event_id]);
            if ($stmt) {
                $success_message = "Join request cancelled successfully!";
                logActivity($user_id, 'Event Join Request Cancelled', "Cancelled join request for event ID: $event_id");
            } else {
                $error_message = "Error cancelling join request.";
                error_log("Error executing DELETE query for join request, event ID: $event_id");
            }
        } catch (Exception $e) {
            $error_message = "Error cancelling join request: " . $e->getMessage();
            error_log("Exception in join request cancellation: " . $e->getMessage());
        }
    } else {
        $error_message = "Cannot cancel this request.";
    }
}

// Handle feedback submission - Enhanced to allow feedback only for confirmed participants of completed events
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_feedback' && isLoggedIn()) {
    $event_id = (int)($_POST['event_id'] ?? 0);
    $feedback = sanitizeInput($_POST['feedback'] ?? '');
    $user_id = $_SESSION['user_id'];

    // Get event details to check if it's completed
    $event_query = "SELECT event_date FROM events WHERE event_id = ?";
    $event_stmt = executeQuery($event_query, [$event_id]);
    $event_details = $event_stmt ? $event_stmt->fetch(PDO::FETCH_ASSOC) : false;

    $current_registration = getUserRegistrationStatus($user_id, $event_id);

    if (!$event_details) {
        $error_message = "Event not found.";
    } elseif (!$current_registration || $current_registration['response_status'] !== 'Confirmed') {
        $error_message = "You can only submit feedback for events you have confirmed attendance.";
    } elseif (!isEventCompleted($event_details['event_date'])) {
        $error_message = "You can only submit feedback for completed events.";
    } elseif (empty(trim($feedback))) {
        $error_message = "Feedback cannot be empty.";
    } else {
        try {
            $query = "UPDATE registers SET feedback = ? WHERE person_id = ? AND event_id = ?";
            $stmt = executeQuery($query, [$feedback, $user_id, $event_id]);
            if ($stmt) {
                $success_message = "Feedback submitted successfully!";
                logActivity($user_id, 'Feedback Submitted', "Submitted feedback for event ID: $event_id");
            } else {
                $error_message = "Error submitting feedback.";
                error_log("Error updating feedback for user_id: $user_id, event_id: $event_id");
            }
        } catch (Exception $e) {
            $error_message = "Error submitting feedback: " . $e->getMessage();
            error_log("Exception in feedback submission: " . $e->getMessage());
        }
    }
}

// Handle registration approval, rejection, and role updates (Organizer only)
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action']) &&
    in_array($_POST['action'], ['approve_registration', 'reject_registration', 'update_roles']) &&
    isLoggedIn()
) {
    $user_id = $_SESSION['user_id'] ?? 0;
    $event_id = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;
    $person_ids = isset($_POST['person_ids']) ? array_map('intval', (array) $_POST['person_ids']) : [];
    $roles = isset($_POST['roles']) ? array_map('sanitizeInput', (array) $_POST['roles']) : [];

    $action = $_POST['action'];
    $log_action = $action === 'approve_registration' ? 'Approved' : ($action === 'reject_registration' ? 'Rejected' : 'Role Updated');
    $new_status = $action === 'approve_registration' ? 'Confirmed' : ($action === 'reject_registration' ? 'Cancelled' : null);

    error_log("[$log_action] Request received | User ID: $user_id | Event ID: $event_id | Person IDs: " . json_encode($person_ids) . " | Roles: " . json_encode($roles));

    if ($event_id <= 0 || $user_id <= 0) {
        $error_message = "Invalid event or user.";
        error_log("[$log_action] Invalid event_id ($event_id) or user_id ($user_id). Aborting.");
    } elseif (empty($person_ids) && $action !== 'update_roles') {
        $error_message = "No participants selected for $log_action.";
        error_log("[$log_action] No person IDs provided. Aborting.");
    } elseif (!isOrganizer($user_id, $event_id)) {
        $error_message = "You are not authorized to $log_action registrations for this event.";
        error_log("[$log_action] Unauthorized access attempt by user_id: $user_id for event_id: $event_id");
    } else {
        try {
            if ($action === 'approve_registration') {
                if (count($person_ids) !== count($roles)) {
                    $error_message = "Invalid number of roles provided.";
                    error_log("[APPROVAL] Mismatch between person_ids and roles. Person IDs: " . json_encode($person_ids) . ", Roles: " . json_encode($roles));
                } else {
                    $affected_rows = 0;
                    foreach ($person_ids as $index => $person_id) {
                        $role = in_array($roles[$index], ['Attendee', 'Organizer', 'Speaker', 'Volunteer', 'Sponsor']) ? $roles[$index] : 'Attendee';
                        $query = "UPDATE registers 
                                  SET role = ?, response_status = ? 
                                  WHERE person_id = ? AND event_id = ? AND response_status = 'Pending'";
                        $stmt = executeQuery($query, [$role, $new_status, $person_id, $event_id]);
                        if ($stmt) {
                            $affected_rows += $stmt->rowCount();
                            logActivity(
                                $user_id,
                                "Event Registration $log_action",
                                "$log_action person_id: $person_id for event ID: $event_id with role: $role"
                            );
                        } else {
                            error_log("[APPROVAL] Failed to execute query for person_id: $person_id, event_id: $event_id");
                        }
                    }

                    if ($affected_rows > 0) {
                        $success_message = "Event registrations approved successfully!";
                    } else {
                        $error_message = "No pending requests found or error occurred.";
                        error_log("[APPROVAL] No pending requests for Event ID: $event_id, Person IDs: " . json_encode($person_ids));
                    }
                }
            } elseif ($action === 'reject_registration') {
                $placeholders = implode(',', array_fill(0, count($person_ids), '?'));
                $query = "UPDATE registers 
                          SET response_status = ? 
                          WHERE event_id = ? AND person_id IN ($placeholders)";
                $params = array_merge([$new_status, $event_id], $person_ids);

                $stmt = executeQuery($query, $params);
                if ($stmt === false) {
                    $error_message = "Error executing rejection query.";
                    $db = getDatabaseConnection();
                    error_log("[REJECTION] Query execution failed for Event ID: $event_id, Person IDs: " . json_encode($person_ids) . ", Error: " . implode(" ", $db->errorInfo()));
                } else {
                    $affected_rows = $stmt->rowCount();
                    if ($affected_rows > 0) {
                        $success_message = "Event registrations rejected successfully!";
                        foreach ($person_ids as $pid) {
                            logActivity(
                                $user_id,
                                "Event Registration Rejected",
                                "Rejected person_id: $pid for event ID: $event_id"
                            );
                        }
                    } else {
                        $error_message = "No matching registrations found for the selected participants.";
                        error_log("[REJECTION] No matching records for Event ID: $event_id, Person IDs: " . json_encode($person_ids));
                    }
                }
            } elseif ($action === 'update_roles') {
                if (count($person_ids) !== count($roles)) {
                    $error_message = "Invalid number of roles provided.";
                    error_log("[ROLE UPDATE] Mismatch between person_ids and roles. Person IDs: " . json_encode($person_ids) . ", Roles: " . json_encode($roles));
                } else {
                    $affected_rows = 0;
                    foreach ($person_ids as $index => $person_id) {
                        $role = in_array($roles[$index], ['Attendee', 'Organizer', 'Speaker', 'Volunteer', 'Sponsor']) ? $roles[$index] : 'Attendee';
                        $query = "UPDATE registers 
                                  SET role = ? 
                                  WHERE person_id = ? AND event_id = ?";
                        $stmt = executeQuery($query, [$role, $person_id, $event_id]);
                        if ($stmt) {
                            $affected_rows += $stmt->rowCount();
                            logActivity(
                                $user_id,
                                "Event Registration Role Updated",
                                "Updated role for person_id: $person_id for event ID: $event_id to role: $role"
                            );
                        } else {
                            error_log("[ROLE UPDATE] Failed to execute query for person_id: $person_id, event_id: $event_id");
                        }
                    }

                    if ($affected_rows > 0) {
                        $success_message = "Participant roles updated successfully!";
                    } else {
                        $error_message = "No roles updated or error occurred.";
                        error_log("[ROLE UPDATE] No matching records found for Event ID: $event_id, Person IDs: " . json_encode($person_ids));
                    }
                }
            }
        } catch (Exception $e) {
            $error_message = "Error $log_action registrations: " . $e->getMessage();
            error_log("[$log_action] Exception: " . $e->getMessage());
        }
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

if (!isset($_SESSION['events_loaded'])) {
    $_SESSION['events_loaded'] = true;
}

// Build the database query for events
try {
    // Step 1: Build count query (for pagination)
    $count_query = "SELECT COUNT(*) FROM events WHERE 1=1";
    $count_params = [];

    if (!empty($search)) {
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

    // Step 3: Get registration status with additional user details and feedback
    $user_registrations = [];
    if (isLoggedIn()) {
        $query = "SELECT r.event_id, r.role, r.response_status, r.feedback,
                         CONCAT(p.first_name, ' ', p.last_name) AS full_name,
                         CASE 
                             WHEN a.person_id IS NOT NULL THEN 'Alumni'
                             WHEN s.person_id IS NOT NULL THEN 'Student'
                             ELSE 'Unknown'
                         END AS user_status,
                         (SELECT phone_number FROM person_phone pp WHERE pp.person_id = r.person_id LIMIT 1) AS primary_phone,
                         (SELECT email FROM email_address ea WHERE ea.person_id = r.person_id LIMIT 1) AS primary_email
                  FROM registers r 
                  JOIN person p ON r.person_id = p.person_id 
                  LEFT JOIN alumni a ON r.person_id = a.person_id 
                  LEFT JOIN student s ON r.person_id = s.person_id 
                  WHERE r.person_id = ?";
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
    error_log("EVENT LOAD ERROR: " . $e->getMessage());
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
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;700&family=Roboto:wght@500;700&display=swap" rel="stylesheet" />

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />

    <!-- AOS Animation CSS -->
    <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet" />

    <!-- Bootstrap JavaScript (with Popper.js for dropdowns) -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js"></script>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <style>
        body {
            font-family: 'Open Sans', sans-serif;
            background-color: #f5f7fa;
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
        }
        .event-card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background-color: #ffffff;
            padding: 24px;
            transition: all 0.3s ease;
            height: 100%;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }
        .event-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15);
        }
        .footer {
            background-color: #002147;
            color: #fff;
            padding: 40px 0;
            font-size: 0.95rem;
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
        .filter-form {
            margin-bottom: 2rem;
        }
        .pagination .page-link {
            border-radius: 8px;
            margin: 0 3px;
            color: #002147;
        }
        .pagination .page-item.active .page-link {
            background-color: #002147;
            border-color: #002147;
        }
        .modal-content {
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }
        .btn-primary {
            background-color: #002147;
            border-color: #002147;
            border-radius: 8px;
            padding: 10px 20px;
        }
        .btn-primary:hover {
            background-color: #0077c8;
            border-color: #0077c8;
        }
        .event-form {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .modal-header.bg-navy {
            background-color: #002147;
            color: #fff;
        }
        .modal-header.bg-success {
            background-color: #28a745;
            color: #fff;
        }
        .modal-title {
            font-family: 'Roboto', sans-serif;
            font-weight: 700;
        }
        .form-label {
            font-weight: 600;
            color: #002147;
        }
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #ced4da;
            padding: 10px;
            font-size: 1rem;
        }
        .form-control:focus, .form-select:focus {
            border-color: #002147;
            box-shadow: 0 0 0 0.2rem rgba(0, 33, 71, 0.25);
        }
        .nav-tabs .nav-link {
            color: #002147;
            font-weight: 600;
        }
        .nav-tabs .nav-link.active {
            color: #002147;
            border-bottom: 2px solid #002147;
        }
        .nav-tabs .nav-link:hover {
            color: #002147;
        }
        .table-responsive {
            max-height: 400px;
        }
        .form-select-sm {
            padding: 5px;
            font-size: 0.875rem;
        }
        .badge-organizer { background-color: #dc3545; }
        .badge-speaker { background-color: #28a745; }
        .badge-volunteer { background-color: #17a2b8; }
        .badge-sponsor { background-color: #ffc107; color: #212529; }
        .badge-attendee { background-color: #6c757d; }
        .feedback-section {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
        }
        .feedback-text {
            font-style: italic;
            color: #6c757d;
            font-size: 0.9rem;
        }
        .feedback-card {
            border-left: 4px solid #007bff;
            background-color: #f8f9fa;
        }

        .feedback-card.alumni-feedback {
            border-left-color: #dc3545;
        }

        .feedback-card.student-feedback {
            border-left-color: #28a745;
        }

        .feedback-meta {
            font-size: 0.875rem;
            color: #6c757d;
        }

        .feedback-text {
            line-height: 1.6;
            white-space: pre-line;
        }

        .feedback-summary {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .feedback-count-badge {
            background-color: #007bff;
            color: white;
            border-radius: 20px;
            padding: 0.25rem 0.75rem;
            font-size: 0.875rem;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .feedback-card .card-header {
                flex-direction: column;
                align-items: flex-start !important;
            }

            .feedback-card .card-header > div:last-child {
                margin-top: 0.5rem;
            }
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

<?php include '../includes/navbar.php'; ?>

<!-- Hero Section -->
<section class="hero" data-aos="fade-up">
    <div class="container">
        <h1 class="display-4">Events</h1>
        <?php if (isLoggedIn() && isAlumni($_SESSION['user_id'])): ?>
            <button class="btn btn-light mt-3" data-bs-toggle="modal" data-bs-target="#addEventModal">
                <i class="fas fa-plus me-2"></i>Register Event
            </button>
        <?php endif; ?>
    </div>
</section>

<!-- Filter and Search Section -->
<section class="py-4">
    <div class="container">
        <form class="filter-form" method="GET">
            <div class="input-group input-group-lg">
                <input type="text" name="search" class="form-control rounded-start" placeholder="Search by title, city, or venue" value="<?php echo htmlspecialchars($search); ?>">
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
            <div class="alert alert-success text-center rounded-3" data-aos="fade-up"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert alert-danger text-center rounded-3" data-aos="fade-up"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <ul class="nav nav-tabs mb-4" id="eventTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="upcoming-tab" data-bs-toggle="tab" data-bs-target="#upcoming" type="button" role="tab" style="color: #002147 !important;">Upcoming Events</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="past-tab" data-bs-toggle="tab" data-bs-target="#past" type="button" role="tab" style="color: #002147 !important;">Past Events</button>
            </li>
        </ul>

        <div class="tab-content" id="eventTabContent">
            <!-- Upcoming Events Tab -->
            <div class="tab-pane fade show active" id="upcoming" role="tabpanel">
                <div class="row g-4" data-aos="fade-up" data-aos-delay="100">
                    <?php if (empty($upcomingEvents)): ?>
                        <div class="col-12 text-center py-5">
                            <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                            <p class="text-muted"><?php echo empty($error_message) ? 'No upcoming events found.' : ''; ?></p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($upcomingEvents as $event): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="event-card">
                                    <h5><?php echo htmlspecialchars($event['event_title']); ?></h5>
                                    <p><i class="fas fa-calendar me-2"></i><?php echo date('F j, Y', strtotime($event['event_date'])); ?></p>
                                    <p><i class="fas fa-clock me-2"></i><?php echo htmlspecialchars($event['start_time']) . ' - ' . htmlspecialchars($event['end_time']); ?></p>
                                    <p><i class="fas fa-map-marker-alt me-2"></i><?php echo htmlspecialchars($event['venue'] . ', ' . $event['city']); ?></p>
                                    <p><i class="fas fa-tag me-2"></i><?php echo htmlspecialchars($event['type']); ?></p>

                                    <?php if (!eventHasOrganizer($event['event_id'])): ?>
                                        <p class="text-warning"><i class="fas fa-exclamation-triangle me-2"></i>No organizer assigned</p>
                                    <?php endif; ?>

                                    <?php if (isLoggedIn() && isset($user_registrations[$event['event_id']])): ?>
                                        <p><strong>Status:</strong>
                                            <span class="badge bg-<?php echo $user_registrations[$event['event_id']]['response_status'] == 'Confirmed' ? 'success' : ($user_registrations[$event['event_id']]['response_status'] == 'Pending' ? 'warning' : 'danger'); ?>">
                                                <?php echo htmlspecialchars($user_registrations[$event['event_id']]['response_status']); ?>
                                            </span>
                                        </p>
                                        <p><strong>Role:</strong>
                                            <span class="badge badge-<?php echo strtolower($user_registrations[$event['event_id']]['role']); ?>">
                                                <?php echo htmlspecialchars($user_registrations[$event['event_id']]['role']); ?>
                                            </span>
                                        </p>
                                    <?php endif; ?>

                                    <div class="d-flex gap-2 flex-wrap">
                                        <?php if (isLoggedIn()): ?>
                                            <?php if (isOrganizer($_SESSION['user_id'], $event['event_id'])): ?>
                                                <!-- Organizer buttons -->
                                                <?php if (!isEventCompleted($event['event_date'])): ?>
                                                    <!-- Upcoming event organizer buttons -->
                                                    <button class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editEventModal" data-event-id="<?php echo $event['event_id']; ?>" data-title="<?php echo htmlspecialchars($event['event_title']); ?>" data-date="<?php echo $event['event_date']; ?>" data-city="<?php echo htmlspecialchars($event['city']); ?>" data-venue="<?php echo htmlspecialchars($event['venue']); ?>" data-type="<?php echo htmlspecialchars($event['type']); ?>" data-start-time="<?php echo htmlspecialchars($event['start_time']); ?>" data-end-time="<?php echo htmlspecialchars($event['end_time']); ?>">Edit</button>
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="action" value="delete_event">
                                                        <input type="hidden" name="event_id" value="<?php echo $event['event_id']; ?>">
                                                        <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Are you sure you want to delete this event?');">Delete</button>
                                                    </form>
                                                <?php endif; ?>
                                                <button class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#approveRegistrationModal" data-event-id="<?php echo $event['event_id']; ?>" data-event-title="<?php echo htmlspecialchars($event['event_title']); ?>">Manage</button>
                                                <button class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#viewParticipantsModal" data-event-id="<?php echo $event['event_id']; ?>" data-event-title="<?php echo htmlspecialchars($event['event_title']); ?>">Participants (<?php echo getTotalParticipants($event['event_id']); ?>)</button>

                                                <div class="dropdown">
                                                    <button class="btn btn-outline-primary btn-sm dropdown-toggle" type="button" id="feedbackDropdown<?php echo $event['event_id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">Feedback</button>
                                                    <ul class="dropdown-menu" aria-labelledby="feedbackDropdown<?php echo $event['event_id']; ?>">
                                                        <li>
                                                            <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#viewAllFeedbackModal" data-event-id="<?php echo $event['event_id']; ?>" data-event-title="<?php echo htmlspecialchars($event['event_title']); ?>">
                                                                View Feedback
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#feedbackModal" data-event-id="<?php echo $event['event_id']; ?>" data-event-title="<?php echo htmlspecialchars($event['event_title']); ?>" data-current-feedback="">
                                                                Send Feedback
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>

                                            <?php else: ?>
                                                <!-- Non-organizer buttons -->
                                                <?php
                                                $current_registration = isset($user_registrations[$event['event_id']]) ? $user_registrations[$event['event_id']] : false;
                                                ?>
                                                <?php if (!isEventCompleted($event['event_date'])): ?>
                                                    <!-- Upcoming event buttons for participants -->
                                                    <?php if ($current_registration && in_array($current_registration['response_status'], ['Pending', 'Confirmed'])): ?>
                                                        <form method="POST" style="display:inline;">
                                                            <input type="hidden" name="action" value="cancel_join_request">
                                                            <input type="hidden" name="event_id" value="<?php echo $event['event_id']; ?>">
                                                            <button type="submit" class="btn btn-outline-danger btn-sm">Cancel Request</button>
                                                        </form>
                                                    <?php elseif (eventHasOrganizer($event['event_id'])): ?>
                                                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#joinEventModal" data-event-id="<?php echo $event['event_id']; ?>" data-event-title="<?php echo htmlspecialchars($event['event_title']); ?>">Join Event</button>
                                                    <?php endif; ?>
                                                <?php endif; ?>

                                                <!-- NEW: View All Feedback button for confirmed participants -->
                                                <?php if ($current_registration && $current_registration['response_status'] === 'Confirmed'): ?>
                                                    <div class="dropdown">
                                                        <button class="btn btn-outline-primary btn-sm dropdown-toggle" type="button" id="feedbackDropdown<?php echo $event['event_id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                                            <i class="fas fa-comments me-1"></i>Feedback
                                                        </button>
                                                        <ul class="dropdown-menu" aria-labelledby="feedbackDropdown<?php echo $event['event_id']; ?>">
                                                            <li>
                                                                <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#viewAllFeedbackModal" data-event-id="<?php echo $event['event_id']; ?>" data-event-title="<?php echo htmlspecialchars($event['event_title']); ?>">
                                                                    View Feedback
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#feedbackModal" data-event-id="<?php echo $event['event_id']; ?>" data-event-title="<?php echo htmlspecialchars($event['event_title']); ?>" data-current-feedback="">
                                                                    Send Feedback
                                                                </a>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <a href="/auth/signin.php" class="btn btn-primary btn-sm">Sign In to Join</a>
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
                        <div class="col-12 text-center py-5">
                            <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                            <p class="text-muted"><?php echo empty($error_message) ? 'No past events found.' : ''; ?></p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($pastEvents as $event): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="event-card h-100">
                                    <h5 class="mb-3"><?php echo htmlspecialchars($event['event_title']); ?></h5>
                                    <p class="mb-2"><i class="fas fa-calendar me-2"></i><?php echo date('F j, Y', strtotime($event['event_date'])); ?></p>
                                    <p class="mb-2"><i class="fas fa-clock me-2"></i><?php echo htmlspecialchars($event['start_time']) . ' - ' . htmlspecialchars($event['end_time']); ?></p>
                                    <p class="mb-2"><i class="fas fa-map-marker-alt me-2"></i><?php echo htmlspecialchars($event['venue'] . ', ' . $event['city']); ?></p>
                                    <p class="mb-3"><i class="fas fa-tag me-2"></i><?php echo htmlspecialchars($event['type']); ?></p>

                                    <?php if (isLoggedIn() && isset($user_registrations[$event['event_id']])): ?>
                                        <p><strong>Status:</strong>
                                            <span class="badge bg-<?php echo $user_registrations[$event['event_id']]['response_status'] == 'Confirmed' ? 'success' : ($user_registrations[$event['event_id']]['response_status'] == 'Pending' ? 'warning' : 'danger'); ?>">
                                                <?php echo htmlspecialchars($user_registrations[$event['event_id']]['response_status']); ?>
                                            </span>
                                        </p>
                                        <p><strong>Role:</strong>
                                            <span class="badge badge-<?php echo strtolower($user_registrations[$event['event_id']]['role']); ?>">
                                                <?php echo htmlspecialchars($user_registrations[$event['event_id']]['role']); ?>
                                            </span>
                                        </p>

                                        <?php if ($user_registrations[$event['event_id']]['response_status'] === 'Confirmed'): ?>
                                            <!-- Display existing feedback if available -->
                                            <?php if (!empty($user_registrations[$event['event_id']]['feedback'])): ?>
                                                <div class="feedback-section">
                                                    <h6><i class="fas fa-comment me-2"></i>Your Feedback:</h6>
                                                    <p class="feedback-text mb-2"><?php echo htmlspecialchars($user_registrations[$event['event_id']]['feedback']); ?></p>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Feedback button - always show for completed events -->
                                            <button class="btn btn-outline-info btn-sm mb-2" data-bs-toggle="modal" data-bs-target="#feedbackModal" data-event-id="<?php echo $event['event_id']; ?>" data-event-title="<?php echo htmlspecialchars($event['event_title']); ?>" data-current-feedback="<?php echo htmlspecialchars($user_registrations[$event['event_id']]['feedback'] ?? ''); ?>">
                                                <i class="fas fa-comment me-1"></i>
                                                <?php echo !empty($user_registrations[$event['event_id']]['feedback']) ? 'Update Feedback' : 'Add Feedback'; ?>
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <div class="d-flex gap-2 flex-wrap">
                                        <?php if (isLoggedIn()): ?>
                                            <?php if (isOrganizer($_SESSION['user_id'], $event['event_id'])): ?>
                                                <!-- Organizer buttons -->
                                                <?php if (!isEventCompleted($event['event_date'])): ?>
                                                    <!-- Upcoming event organizer buttons -->
                                                    <button class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editEventModal" data-event-id="<?php echo $event['event_id']; ?>" data-title="<?php echo htmlspecialchars($event['event_title']); ?>" data-date="<?php echo $event['event_date']; ?>" data-city="<?php echo htmlspecialchars($event['city']); ?>" data-venue="<?php echo htmlspecialchars($event['venue']); ?>" data-type="<?php echo htmlspecialchars($event['type']); ?>" data-start-time="<?php echo htmlspecialchars($event['start_time']); ?>" data-end-time="<?php echo htmlspecialchars($event['end_time']); ?>">Edit</button>
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="action" value="delete_event">
                                                        <input type="hidden" name="event_id" value="<?php echo $event['event_id']; ?>">
                                                        <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Are you sure you want to delete this event?');">Delete</button>
                                                    </form>
                                                <?php endif; ?>
                                                <button class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#approveRegistrationModal" data-event-id="<?php echo $event['event_id']; ?>" data-event-title="<?php echo htmlspecialchars($event['event_title']); ?>">Manage</button>
                                                <button class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#viewParticipantsModal" data-event-id="<?php echo $event['event_id']; ?>" data-event-title="<?php echo htmlspecialchars($event['event_title']); ?>">Participants (<?php echo getTotalParticipants($event['event_id']); ?>)</button>

                                                <!-- NEW: View All Feedback button for organizers -->
                                                <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#viewAllFeedbackModal" data-event-id="<?php echo $event['event_id']; ?>" data-event-title="<?php echo htmlspecialchars($event['event_title']); ?>">
                                                    <i class="fas fa-comments me-1"></i>View Feedback
                                                </button>

                                            <?php else: ?>
                                                <!-- Non-organizer buttons -->
                                                <?php
                                                $current_registration = isset($user_registrations[$event['event_id']]) ? $user_registrations[$event['event_id']] : false;
                                                ?>
                                                <?php if (!isEventCompleted($event['event_date'])): ?>
                                                    <!-- Upcoming event buttons for participants -->
                                                    <?php if ($current_registration && in_array($current_registration['response_status'], ['Pending', 'Confirmed'])): ?>
                                                        <form method="POST" style="display:inline;">
                                                            <input type="hidden" name="action" value="cancel_join_request">
                                                            <input type="hidden" name="event_id" value="<?php echo $event['event_id']; ?>">
                                                            <button type="submit" class="btn btn-outline-danger btn-sm">Cancel Request</button>
                                                        </form>
                                                    <?php elseif (eventHasOrganizer($event['event_id'])): ?>
                                                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#joinEventModal" data-event-id="<?php echo $event['event_id']; ?>" data-event-title="<?php echo htmlspecialchars($event['event_title']); ?>">Join Event</button>
                                                    <?php endif; ?>
                                                <?php endif; ?>

                                                <!-- NEW: View All Feedback button for confirmed participants -->
                                                <?php if ($current_registration && $current_registration['response_status'] === 'Confirmed'): ?>
                                                    <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#viewAllFeedbackModal" data-event-id="<?php echo $event['event_id']; ?>" data-event-title="<?php echo htmlspecialchars($event['event_title']); ?>">
                                                        <i class="fas fa-comments me-1"></i>View Feedback
                                                    </button>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <a href="/auth/signin.php" class="btn btn-primary btn-sm">Sign In to Join</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation" class="mt-4">
                <ul class="pagination justify-content-center">
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
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #002147; color: white;">
                <h5 class="modal-title" id="addEventModalLabel">Register Event</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_event">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        You will automatically become the organizer of this event.
                    </div>

                    <div class="mb-3">
                        <label for="event_title" class="form-label">Event Title</label>
                        <input type="text" class="form-control" id="event_title" name="event_title" placeholder="Enter event title" required>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="event_date" class="form-label">Event Date</label>
                            <input type="date" class="form-control" id="event_date" name="event_date" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="city" class="form-label">City</label>
                            <select class="form-select" id="city" name="city" required>
                                <option value="">Select City</option>
                                <option value="Dhaka">Dhaka</option>
                                <option value="Chattogram">Chattogram</option>
                                <option value="Khulna">Khulna</option>
                                <option value="Rajshahi">Rajshahi</option>
                                <option value="Sylhet">Sylhet</option>
                                <option value="Barishal">Barishal</option>
                                <option value="Rangpur">Rangpur</option>
                                <option value="Mymensingh">Mymensingh</option>
                                <option value="Jessore">Jessore</option>
                                <option value="Bogura">Bogura</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="venue" class="form-label">Venue</label>
                        <input type="text" class="form-control" id="venue" name="venue" placeholder="Enter venue" required>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="start_time" class="form-label">Start Time</label>
                            <input type="time" class="form-control" id="start_time" name="start_time" required>
                        </div>
                        <div class="col-md-6">
                            <label for="end_time" class="form-label">End Time</label>
                            <input type="time" class="form-control" id="end_time" name="end_time" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="type" class="form-label">Event Type</label>
                        <select class="form-select" id="type" name="type" required>
                            <option value="">Select Event Type</option>
                            <option value="Workshop">Workshop</option>
                            <option value="Seminar">Seminar</option>
                            <option value="Conference">Conference</option>
                            <option value="Networking">Networking</option>
                            <option value="Career Fair">Career Fair</option>
                            <option value="Alumni Meet">Alumni Meet</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" style="background-color:#002147; color:white;">Register Event</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Join Event Modal -->
<div class="modal fade" id="joinEventModal" tabindex="-1" aria-labelledby="joinEventModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="joinEventModalLabel">Join Event</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="join_event">
                    <input type="hidden" name="event_id" id="join_event_id">

                    <p>You are requesting to join: <strong id="join_event_title"></strong></p>

                    <div class="mb-3">
                        <label for="requested_role" class="form-label">Select Your Role</label>
                        <?php if (isLoggedIn() && isAlumni($_SESSION['user_id'])): ?>
                            <select class="form-select" id="requested_role" name="requested_role" required>
                                <option value="">Choose your preferred role...</option>
                                <option value="Attendee">Attendee</option>
                                <option value="Speaker">Speaker</option>
                                <option value="Volunteer">Volunteer</option>
                                <option value="Sponsor">Sponsor</option>
                            </select>
                            <div class="form-text">The organizer will review and may assign a different role.</div>
                        <?php else: ?>
                            <select class="form-select" id="requested_role" name="requested_role" required>
                                <option value="">Choose your role...</option>
                                <option value="Attendee">Attendee</option>
                                <option value="Volunteer">Volunteer</option>
                            </select>
                            <div class="form-text">Students can join as Attendee or Volunteer. The organizer will review your request.</div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Submit Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Feedback Modal -->
<div class="modal fade" id="feedbackModal" tabindex="-1" aria-labelledby="feedbackModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="feedbackModalLabel">Event Feedback</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="submit_feedback">
                    <input type="hidden" name="event_id" id="feedback_event_id">

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note:</strong> Feedback can only be submitted for completed events that you attended.
                    </div>

                    <p>Feedback for: <strong id="feedback_event_title"></strong></p>

                    <div class="mb-3">
                        <label for="feedback" class="form-label">Your Feedback <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="feedback" name="feedback" rows="5" placeholder="Please share your experience about this event. What did you like? What could be improved? Your feedback helps us organize better events in the future..." required></textarea>
                        <div class="form-text">Minimum 10 characters required. Be specific and constructive.</div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info">Submit Feedback</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Event Modal -->
<div class="modal fade" id="editEventModal" tabindex="-1" aria-labelledby="editEventModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background-color:#ffc107; color:#212529;">
                <h5 class="modal-title" id="editEventModalLabel">Edit Event</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_event">
                    <input type="hidden" name="event_id" id="edit_event_id">

                    <div class="mb-3">
                        <label for="edit_event_title" class="form-label">Event Title</label>
                        <input type="text" class="form-control" id="edit_event_title" name="event_title" placeholder="Enter event title" required>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_event_date" class="form-label">Event Date</label>
                            <input type="date" class="form-control" id="edit_event_date" name="event_date" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="edit_city" class="form-label">City</label>
                            <select class="form-select" id="edit_city" name="city" required>
                                <option value="">Select City</option>
                                <option value="Dhaka">Dhaka</option>
                                <option value="Chattogram">Chattogram</option>
                                <option value="Khulna">Khulna</option>
                                <option value="Rajshahi">Rajshahi</option>
                                <option value="Sylhet">Sylhet</option>
                                <option value="Barishal">Barishal</option>
                                <option value="Rangpur">Rangpur</option>
                                <option value="Mymensingh">Mymensingh</option>
                                <option value="Jessore">Jessore</option>
                                <option value="Bogura">Bogura</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="edit_venue" class="form-label">Venue</label>
                        <input type="text" class="form-control" id="edit_venue" name="venue" placeholder="Enter venue" required>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_start_time" class="form-label">Start Time</label>
                            <input type="time" class="form-control" id="edit_start_time" name="start_time" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_end_time" class="form-label">End Time</label>
                            <input type="time" class="form-control" id="edit_end_time" name="end_time" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="edit_type" class="form-label">Event Type</label>
                        <select class="form-select" id="edit_type" name="type" required>
                            <option value="">Select Event Type</option>
                            <option value="Workshop">Workshop</option>
                            <option value="Seminar">Seminar</option>
                            <option value="Conference">Conference</option>
                            <option value="Networking">Networking</option>
                            <option value="Career Fair">Career Fair</option>
                            <option value="Alumni Meet">Alumni Meet</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" style="background-color:#ffc107; color:#212529;">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Approve Registration Modal -->
<div class="modal fade" id="approveRegistrationModal" tabindex="-1" aria-labelledby="approveRegistrationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="approveRegistrationModalLabel">Manage Registrations</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                        <tr>
                            <th>Name</th>
                            <th>Status</th>
                            <th>Role</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Registration Status</th>
                            <th>Feedback</th>
                            <th>Select</th>
                        </tr>
                        </thead>
                        <tbody id="registrantsTableBody">
                        <!-- Populated dynamically via JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-danger" id="rejectSelectedBtn">Reject Selected</button>
                <button type="button" class="btn btn-success" id="approveSelectedBtn">Approve Selected</button>
            </div>
        </div>
    </div>
</div>

<!-- View Participants Modal -->
<div class="modal fade" id="viewParticipantsModal" tabindex="-1" aria-labelledby="viewParticipantsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="viewParticipantsModalLabel">View Participants</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                        <tr>
                            <th>Name</th>
                            <th>Status</th>
                            <th>Role</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Registration Status</th>
                            <th>Feedback</th>
                            <th>Select</th>
                        </tr>
                        </thead>
                        <tbody id="participantsTableBody">
                        <!-- Populated dynamically via JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="saveRolesBtn">Update Roles</button>
            </div>
        </div>
    </div>
</div>

<!-- View All Feedback Modal -->
<div class="modal fade" id="viewAllFeedbackModal" tabindex="-1" aria-labelledby="viewAllFeedbackModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="viewAllFeedbackModalLabel">Event Feedback</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="feedbackContent">
                    <!-- Feedback content will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<!-- JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
<script>
    AOS.init({ duration: 1000, once: true });

    // Populate join event modal
    const joinEventModal = document.getElementById('joinEventModal');
    if (joinEventModal) {
        joinEventModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const eventId = button.getAttribute('data-event-id');
            const eventTitle = button.getAttribute('data-event-title');

            const modal = this;
            modal.querySelector('#join_event_id').value = eventId;
            modal.querySelector('#join_event_title').textContent = eventTitle;
        });
    }

    // Populate feedback modal with enhanced validation
    const feedbackModal = document.getElementById('feedbackModal');
    if (feedbackModal) {
        feedbackModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const eventId = button.getAttribute('data-event-id');
            const eventTitle = button.getAttribute('data-event-title');
            const currentFeedback = button.getAttribute('data-current-feedback');

            const modal = this;
            modal.querySelector('#feedback_event_id').value = eventId;
            modal.querySelector('#feedback_event_title').textContent = eventTitle;
            modal.querySelector('#feedback').value = currentFeedback || '';

        });

        // Enhanced feedback form validation
        const feedbackForm = feedbackModal.querySelector('form');
        if (feedbackForm) {
            feedbackForm.addEventListener('submit', function(e) {
                const feedbackText = this.querySelector('#feedback').value.trim();

                if (feedbackText.length < 10) {
                    e.preventDefault();
                    alert('Please provide at least 10 characters of feedback.');
                    return false;
                }
            });
        }
    }

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

    // Populate approve registration modal with registrants
    let currentEventId = null; // Store event ID for approve/reject/update actions
    const approveRegistrationModal = document.getElementById('approveRegistrationModal');
    if (approveRegistrationModal) {
        approveRegistrationModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            currentEventId = button.getAttribute('data-event-id'); // Store event ID
            const eventTitle = button.getAttribute('data-event-title');
            const modal = this;
            modal.querySelector('#approveRegistrationModalLabel').textContent = `Manage Registrations for ${eventTitle}`;

            // Fetch registrants via AJAX
            fetch('events.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=fetch_registrants&event_id=${currentEventId}`
            })
                .then(response => response.json())
                .then(data => {
                    const tableBody = document.getElementById('registrantsTableBody');
                    tableBody.innerHTML = '';
                    if (data.length === 0) {
                        tableBody.innerHTML = '<tr><td colspan="8" class="text-center">No registrants found.</td></tr>';
                    } else {
                        data.forEach(reg => {
                            const row = document.createElement('tr');
                            const roleOptions = ['Attendee', 'Organizer', 'Speaker', 'Volunteer', 'Sponsor']
                                .map(role => `<option value="${role}" ${reg.role === role ? 'selected' : ''}>${role}</option>`)
                                .join('');

                            const feedbackDisplay = reg.feedback ?
                                `<small class="text-truncate d-inline-block" style="max-width: 150px;" title="${reg.feedback}">${reg.feedback}</small>` :
                                '<small class="text-muted">No feedback</small>';

                            row.innerHTML = `
                                <td>${reg.full_name} (${reg.user_status})</td>
                                <td>${reg.user_status}</td>
                                <td>
                                    ${reg.response_status === 'Pending' ?
                                `<select class="form-select form-select-sm" name="roles[]">${roleOptions}</select>` :
                                reg.role}
                                </td>
                                <td>${reg.primary_phone || 'N/A'}</td>
                                <td>${reg.primary_email || 'N/A'}</td>
                                <td><span class="badge bg-${reg.response_status === 'Confirmed' ? 'success' : (reg.response_status === 'Pending' ? 'warning' : 'danger')}">${reg.response_status}</span></td>
                                <td>${feedbackDisplay}</td>
                                <td>
                                    ${reg.response_status === 'Pending' || reg.response_status === 'Confirmed' ?
                                `<input type="checkbox" name="person_ids[]" value="${reg.person_id}" data-user-status="${reg.user_status}" data-response-status="${reg.response_status}">` :
                                ''}
                                </td>
                            `;
                            tableBody.appendChild(row);
                        });
                    }
                })
                .then(() => {
                    // Enable/disable buttons based on checkbox state
                    const checkboxes = document.querySelectorAll('#registrantsTableBody input[name="person_ids[]"]');
                    const approveBtn = document.getElementById('approveSelectedBtn');
                    const rejectBtn = document.getElementById('rejectSelectedBtn');

                    const updateButtonState = () => {
                        const checked = document.querySelectorAll('#registrantsTableBody input[name="person_ids[]"]:checked').length > 0;
                        approveBtn.disabled = !checked;
                        rejectBtn.disabled = !checked;
                    };

                    checkboxes.forEach(checkbox => {
                        checkbox.addEventListener('change', updateButtonState);
                    });

                    updateButtonState();
                })
                .catch(error => {
                    console.error('Error fetching registrants:', error);
                    document.getElementById('registrantsTableBody').innerHTML = '<tr><td colspan="8" class="text-center">Error loading registrants.</td></tr>';
                });
        });
    }

    // Handle Approve Selected button
    const approveSelectedBtn = document.getElementById('approveSelectedBtn');
    if (approveSelectedBtn) {
        approveSelectedBtn.addEventListener('click', function() {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'events.php';
            form.style.display = 'none';

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'approve_registration';
            form.appendChild(actionInput);

            const eventIdInput = document.createElement('input');
            eventIdInput.type = 'hidden';
            eventIdInput.name = 'event_id';
            eventIdInput.value = currentEventId;
            form.appendChild(eventIdInput);

            const checkboxes = document.querySelectorAll('#registrantsTableBody input[name="person_ids[]"]:checked');
            const rows = document.querySelectorAll('#registrantsTableBody tr');
            const selectedIndices = Array.from(checkboxes).map(cb => Array.from(rows).findIndex(row => row.contains(cb)));

            selectedIndices.forEach((rowIndex, i) => {
                const personIdInput = document.createElement('input');
                personIdInput.type = 'hidden';
                personIdInput.name = 'person_ids[]';
                personIdInput.value = checkboxes[i].value;
                form.appendChild(personIdInput);

                const roleInput = document.createElement('input');
                roleInput.type = 'hidden';
                roleInput.name = 'roles[]';
                const roleSelect = rows[rowIndex].querySelector('select[name="roles[]"]');
                roleInput.value = roleSelect ? roleSelect.value : 'Attendee';
                form.appendChild(roleInput);
            });

            if (checkboxes.length === 0) {
                alert('Please select at least one registrant to approve.');
                return;
            }

            document.body.appendChild(form);
            form.submit();
        });
    }

    // Handle Reject Selected button
    const rejectSelectedBtn = document.getElementById('rejectSelectedBtn');
    if (rejectSelectedBtn) {
        rejectSelectedBtn.addEventListener('click', function() {
            if (!confirm('Are you sure you want to reject the selected registrations?')) {
                return;
            }

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'events.php';
            form.style.display = 'none';

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'reject_registration';
            form.appendChild(actionInput);

            const eventIdInput = document.createElement('input');
            eventIdInput.type = 'hidden';
            eventIdInput.name = 'event_id';
            eventIdInput.value = currentEventId;
            form.appendChild(eventIdInput);

            const checkboxes = document.querySelectorAll('#registrantsTableBody input[name="person_ids[]"]:checked');
            checkboxes.forEach(checkbox => {
                const personIdInput = document.createElement('input');
                personIdInput.type = 'hidden';
                personIdInput.name = 'person_ids[]';
                personIdInput.value = checkbox.value;
                form.appendChild(personIdInput);
            });

            if (checkboxes.length === 0) {
                alert('Please select at least one registrant to reject.');
                return;
            }

            document.body.appendChild(form);
            form.submit();
        });
    }

    // Populate view participants modal with registrants
    const viewParticipantsModal = document.getElementById('viewParticipantsModal');
    if (viewParticipantsModal) {
        viewParticipantsModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            currentEventId = button.getAttribute('data-event-id'); // Store event ID
            const eventTitle = button.getAttribute('data-event-title');
            const modal = this;
            modal.querySelector('#viewParticipantsModalLabel').textContent = `View Participants for ${eventTitle}`;

            // Fetch registrants via AJAX
            fetch('events.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=fetch_registrants&event_id=${currentEventId}`
            })
                .then(response => response.json())
                .then(data => {
                    const tableBody = document.getElementById('participantsTableBody');
                    tableBody.innerHTML = '';
                    if (data.length === 0) {
                        tableBody.innerHTML = '<tr><td colspan="8" class="text-center">No participants found.</td></tr>';
                    } else {
                        data.forEach(reg => {
                            const row = document.createElement('tr');
                            const roleOptions = ['Attendee', 'Organizer', 'Speaker', 'Volunteer', 'Sponsor']
                                .map(role => `<option value="${role}" ${reg.role === role ? 'selected' : ''}>${role}</option>`)
                                .join('');

                            const feedbackDisplay = reg.feedback ?
                                `<small class="text-truncate d-inline-block" style="max-width: 150px;" title="${reg.feedback}">${reg.feedback}</small>` :
                                '<small class="text-muted">No feedback</small>';

                            row.innerHTML = `
                                <td>${reg.full_name} (${reg.user_status})</td>
                                <td>${reg.user_status}</td>
                                <td>
                                    <select class="form-select form-select-sm" name="roles[]">
                                        ${roleOptions}
                                    </select>
                                </td>
                                <td>${reg.primary_phone || 'N/A'}</td>
                                <td>${reg.primary_email || 'N/A'}</td>
                                <td><span class="badge bg-${reg.response_status === 'Confirmed' ? 'success' : (reg.response_status === 'Pending' ? 'warning' : 'danger')}">${reg.response_status}</span></td>
                                <td>${feedbackDisplay}</td>
                                <td>
                                    <input type="checkbox" name="person_ids[]" value="${reg.person_id}" data-user-status="${reg.user_status}">
                                </td>
                            `;
                            tableBody.appendChild(row);
                        });
                    }
                })
                .then(() => {
                    // Enable/disable save button based on checkbox state
                    const checkboxes = document.querySelectorAll('#participantsTableBody input[name="person_ids[]"]');
                    const saveBtn = document.getElementById('saveRolesBtn');

                    const updateButtonState = () => {
                        saveBtn.disabled = document.querySelectorAll('#participantsTableBody input[name="person_ids[]"]:checked').length === 0;
                    };

                    checkboxes.forEach(checkbox => {
                        checkbox.addEventListener('change', updateButtonState);
                    });

                    updateButtonState();
                })
                .catch(error => {
                    console.error('Error fetching participants:', error);
                    document.getElementById('participantsTableBody').innerHTML = '<tr><td colspan="8" class="text-center">Error loading participants.</td></tr>';
                });
        });
    }

    // Handle Save Changes button for role updates
    const saveRolesBtn = document.getElementById('saveRolesBtn');
    if (saveRolesBtn) {
        saveRolesBtn.addEventListener('click', function() {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'events.php';
            form.style.display = 'none';

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'update_roles';
            form.appendChild(actionInput);

            const eventIdInput = document.createElement('input');
            eventIdInput.type = 'hidden';
            eventIdInput.name = 'event_id';
            eventIdInput.value = currentEventId;
            form.appendChild(eventIdInput);

            const checkboxes = document.querySelectorAll('#participantsTableBody input[name="person_ids[]"]:checked');
            const rows = document.querySelectorAll('#participantsTableBody tr');
            const selectedIndices = Array.from(checkboxes).map(cb => Array.from(rows).findIndex(row => row.contains(cb)));

            selectedIndices.forEach((rowIndex, i) => {
                const personIdInput = document.createElement('input');
                personIdInput.type = 'hidden';
                personIdInput.name = 'person_ids[]';
                personIdInput.value = checkboxes[i].value;
                form.appendChild(personIdInput);

                const roleInput = document.createElement('input');
                roleInput.type = 'hidden';
                roleInput.name = 'roles[]';
                const roleSelect = rows[rowIndex].querySelector('select[name="roles[]"]');
                roleInput.value = roleSelect ? roleSelect.value : 'Attendee';
                form.appendChild(roleInput);
            });

            if (checkboxes.length === 0) {
                alert('Please select at least one participant to update roles.');
                return;
            }

            document.body.appendChild(form);
            form.submit();
        });
    }

    // Form validation for event creation and editing
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            const startTime = this.querySelector('input[name="start_time"]');
            const endTime = this.querySelector('input[name="end_time"]');
            const eventDate = this.querySelector('input[name="event_date"]');

            if (startTime && endTime && startTime.value && endTime.value) {
                if (startTime.value >= endTime.value) {
                    e.preventDefault();
                    alert('Start time must be before end time.');
                    return false;
                }
            }

            if (eventDate && eventDate.value) {
                const selectedDate = new Date(eventDate.value);
                const today = new Date();
                today.setHours(0, 0, 0, 0);

                if (selectedDate < today) {
                    e.preventDefault();
                    alert('Event date must be in the future.');
                    return false;
                }
            }
        });
    });

    // Auto-refresh registration status every 30 seconds for upcoming events
    if (document.querySelector('#upcoming .event-card')) {
        setInterval(function() {
            // Only refresh if user is logged in and viewing upcoming events
            const upcomingTab = document.querySelector('#upcoming-tab');
            if (upcomingTab && upcomingTab.classList.contains('active')) {
                // You can implement AJAX refresh here if needed
                console.log('Auto-refresh check for registration updates');
            }
        }, 30000);
    }

    // Enhanced feedback character counter
    const feedbackTextarea = document.getElementById('feedback');
    if (feedbackTextarea) {
        const charCountDiv = document.createElement('div');
        charCountDiv.className = 'form-text';
        charCountDiv.id = 'charCount';
        feedbackTextarea.parentNode.insertBefore(charCountDiv, feedbackTextarea.nextSibling);

        feedbackTextarea.addEventListener('input', function() {
            const count = this.value.length;
            const countDiv = document.getElementById('charCount');
            countDiv.textContent = `${count} characters (minimum 10 required)`;
            countDiv.className = count >= 10 ? 'form-text text-success' : 'form-text text-warning';
        });
    }

    // Tooltip initialization for truncated feedback text
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Handle View All Feedback Modal
    const viewAllFeedbackModal = document.getElementById('viewAllFeedbackModal');
    if (viewAllFeedbackModal) {
        viewAllFeedbackModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const eventId = button.getAttribute('data-event-id');
            const eventTitle = button.getAttribute('data-event-title');
            const modal = this;
            const feedbackContent = modal.querySelector('#feedbackContent');

            // Update modal title
            modal.querySelector('#viewAllFeedbackModalLabel').textContent = `Feedback for "${eventTitle}"`;

            // Show loading indicator
            feedbackContent.innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2 text-muted">Loading feedback...</p>
            </div>
        `;

            // Fetch feedback via AJAX
            fetch('events.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=fetch_all_feedback&event_id=${eventId}`
            })
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        feedbackContent.innerHTML = `
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        ${data.error}
                    </div>
                `;
                        return;
                    }

                    if (!data.success || data.feedback.length === 0) {
                        feedbackContent.innerHTML = `
                    <div class="text-center py-5">
                        <i class="fas fa-comment-slash fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No Feedback Yet</h5>
                        <p class="text-muted">No participants have submitted feedback for this event yet.</p>
                    </div>
                `;
                        return;
                    }

                    // Process and display feedback
                    let feedbackHtml = `
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>${data.feedback.length}</strong> participant(s) have provided feedback for this event.
                        </div>
                    </div>
                </div>
            `;

                    data.feedback.forEach((item, index) => {
                        const badgeClass = item.user_status === 'Alumni' ? 'bg-primary' : 'bg-success';
                        const roleBadgeClass = {
                            'Organizer': 'bg-danger',
                            'Speaker': 'bg-success',
                            'Volunteer': 'bg-info',
                            'Sponsor': 'bg-warning text-dark',
                            'Attendee': 'bg-secondary'
                        }[item.role] || 'bg-secondary';

                        feedbackHtml += `
                    <div class="card mb-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div>
                                <strong>${item.full_name}</strong>
                                <span class="badge ${badgeClass} ms-2">${item.user_status}</span>
                                <span class="badge ${roleBadgeClass} ms-1">${item.role}</span>
                        <div class="card-body">
                            <p class="card-text">${feedbackText.replace(/\n/g, '<br>')}</p>
                        </div>
                    </div>
                `;
                    });

                    feedbackContent.innerHTML = feedbackHtml;
                })
                .catch(error => {
                    console.error('Error fetching feedback:', error);
                    feedbackContent.innerHTML = `
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Error loading feedback. Please try again later.
                </div>
            `;
                });
        });
    }
</script>
</body>
</html>
//completed