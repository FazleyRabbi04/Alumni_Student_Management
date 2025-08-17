<?php
require_once '../config/database.php';
startSecureSession();

// Initialize variables
$sessions = [];
$error_message = '';
$success_message = '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$session_mode = isset($_GET['session_mode']) ? trim($_GET['session_mode']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$sessions_per_page = 6;

// Function to check if user is an alumnus
function isAlumni($user_id)
{
    $query = "SELECT COUNT(*) FROM alumni WHERE person_id = ?";
    $stmt = executeQuery($query, [$user_id]);
    if ($stmt === false) {
        error_log("Error checking alumni status for user_id $user_id");
        return false;
    }
    return $stmt->fetchColumn() > 0;
}

// Function to check if user is a student
function isStudent($user_id)
{
    $query = "SELECT COUNT(*) FROM student WHERE person_id = ?";
    $stmt = executeQuery($query, [$user_id]);
    if ($stmt === false) {
        error_log("Error checking student status for user_id $user_id");
        return false;
    }
    return $stmt->fetchColumn() > 0;
}

// Function to check if user is an organizer for a session
function isSessionOrganizer($user_id, $session_id)
{
    $query = "SELECT COUNT(*) FROM conducts WHERE person_id = ? AND session_id = ? AND role = 'Organizer'";
    $stmt = executeQuery($query, [$user_id, $session_id]);
    if ($stmt === false) {
        error_log("Error checking organizer status for user_id $user_id, session_id $session_id");
        return false;
    }
    return $stmt->fetchColumn() > 0;
}

// Function to get total confirmed participants for a session
function getTotalSessionParticipants($session_id)
{
    $query = "SELECT COUNT(*) FROM conducts WHERE session_id = ? AND response_status = 'Confirmed'";
    $stmt = executeQuery($query, [$session_id]);
    return $stmt ? $stmt->fetchColumn() : 0;
}

// Function to check if session has an organizer
function sessionHasOrganizer($session_id)
{
    $query = "SELECT COUNT(*) FROM conducts WHERE session_id = ? AND role = 'Organizer' AND response_status = 'Confirmed'";
    $stmt = executeQuery($query, [$session_id]);
    return $stmt ? $stmt->fetchColumn() > 0 : false;
}

// Function to get user's registration status for a session
function getUserSessionRegistrationStatus($user_id, $session_id)
{
    $query = "SELECT response_status, role FROM conducts WHERE person_id = ? AND session_id = ?";
    $stmt = executeQuery($query, [$user_id, $session_id]);
    return $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
}

// Function to check if session is completed (past session date)
function isSessionCompleted($session_date)
{
    $current_date = new DateTime();
    $session_datetime = new DateTime($session_date);
    return $session_datetime < $current_date;
}

// Function to check if student has matching interests with alumni organizer
function hasMatchingInterests($student_id, $session_id)
{
    $query = "SELECT COUNT(*) FROM student_interest si
              INNER JOIN alumni_interest ai ON si.interest_id = ai.interest_id
              INNER JOIN conducts c ON ai.person_id = c.person_id
              WHERE si.person_id = ? AND c.session_id = ? AND c.role = 'Organizer'";
    $stmt = executeQuery($query, [$student_id, $session_id]);
    return $stmt ? $stmt->fetchColumn() > 0 : false;
}

// Function to get matching interests between student and alumni
function getMatchingInterests($student_id, $session_id)
{
    $query = "SELECT DISTINCT i.interest_name 
              FROM interest i
              INNER JOIN student_interest si ON i.interest_id = si.interest_id
              INNER JOIN alumni_interest ai ON i.interest_id = ai.interest_id
              INNER JOIN conducts c ON ai.person_id = c.person_id
              WHERE si.person_id = ? AND c.session_id = ? AND c.role = 'Organizer'";
    $stmt = executeQuery($query, [$student_id, $session_id]);
    return $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
}

// AJAX request to fetch all feedback for a session
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'fetch_all_session_feedback' && isLoggedIn()) {
    header('Content-Type: application/json');
    $session_id = (int)($_POST['session_id'] ?? 0);

    // Check if user has permission to view feedback (organizer or confirmed participant)
    $has_permission = false;
    if (isSessionOrganizer($_SESSION['user_id'], $session_id)) {
        $has_permission = true;
    } else {
        $user_reg = getUserSessionRegistrationStatus($_SESSION['user_id'], $session_id);
        if ($user_reg && $user_reg['response_status'] === 'Confirmed') {
            $has_permission = true;
        }
    }

    if (!$has_permission) {
        echo json_encode(['error' => 'You do not have permission to view feedback for this session.']);
        exit;
    }

    try {
        $query = "SELECT c.feedback, c.role,
                         CONCAT(p.first_name, ' ', p.last_name) AS full_name,
                         CASE 
                             WHEN a.person_id IS NOT NULL THEN 'Alumni'
                             WHEN s.person_id IS NOT NULL THEN 'Student'
                             ELSE 'Unknown'
                         END AS user_status
                  FROM conducts c 
                  JOIN person p ON c.person_id = p.person_id 
                  LEFT JOIN alumni a ON c.person_id = a.person_id 
                  LEFT JOIN student s ON c.person_id = s.person_id 
                  WHERE c.session_id = ? AND c.response_status = 'Confirmed' AND c.feedback IS NOT NULL AND c.feedback != ''
                  ORDER BY c.person_id";
        $stmt = executeQuery($query, [$session_id]);
        if ($stmt) {
            $feedback = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'feedback' => $feedback]);
        } else {
            echo json_encode(['error' => 'Error fetching feedback data.']);
            error_log("Error fetching feedback for session_id: $session_id");
        }
    } catch (Exception $e) {
        echo json_encode(['error' => 'Database error occurred.']);
        error_log("Exception fetching all session feedback: " . $e->getMessage());
    }
    exit;
}

// Handle AJAX request to fetch session participants
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'fetch_session_participants' && isLoggedIn()) {
    header('Content-Type: application/json');
    $session_id = (int)($_POST['session_id'] ?? 0);
    if (!isSessionOrganizer($_SESSION['user_id'], $session_id)) {
        echo json_encode([]);
        error_log("Unauthorized attempt to fetch participants for session_id: $session_id by user_id: {$_SESSION['user_id']}");
        exit;
    }
    try {
        $query = "SELECT c.person_id, c.role, c.response_status, c.feedback,
                         CONCAT(p.first_name, ' ', p.last_name) AS full_name,
                         CASE 
                             WHEN a.person_id IS NOT NULL THEN 'Alumni'
                             WHEN s.person_id IS NOT NULL THEN 'Student'
                             ELSE 'Unknown'
                         END AS user_status,
                         (SELECT phone_number FROM person_phone pp WHERE pp.person_id = c.person_id LIMIT 1) AS primary_phone,
                         (SELECT email FROM email_address ea WHERE ea.person_id = c.person_id LIMIT 1) AS primary_email
                  FROM conducts c 
                  JOIN person p ON c.person_id = p.person_id 
                  LEFT JOIN alumni a ON c.person_id = a.person_id 
                  LEFT JOIN student s ON c.person_id = s.person_id 
                  WHERE c.session_id = ?";
        $stmt = executeQuery($query, [$session_id]);
        if ($stmt) {
            $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($participants);
        } else {
            echo json_encode([]);
            error_log("Error fetching participants for session_id: $session_id");
        }
    } catch (Exception $e) {
        echo json_encode([]);
        error_log("Exception fetching session participants: " . $e->getMessage());
    }
    exit;
}

// Handle session creation (Alumni only - always becomes organizer)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_session' && isLoggedIn() && isAlumni($_SESSION['user_id'])) {
    $title = sanitizeInput($_POST['session_title'] ?? '');
    $date = sanitizeInput($_POST['session_date'] ?? '');
    $city = sanitizeInput($_POST['city'] ?? '');
    $venue = sanitizeInput($_POST['venue'] ?? '');
    $mode = sanitizeInput($_POST['mode'] ?? '');
    $start_time = sanitizeInput($_POST['start_time'] ?? '');
    $end_time = sanitizeInput($_POST['end_time'] ?? '');

    $session_date = new DateTime($date);
    $current_date = new DateTime();
    $start_datetime = DateTime::createFromFormat('Y-m-d H:i', "$date $start_time");
    $end_datetime = DateTime::createFromFormat('Y-m-d H:i', "$date $end_time");

    if ($title && $date && $city && $venue && $mode && $start_time && $end_time) {
        if ($session_date < $current_date) {
            $error_message = "Session date must be in the future.";
        } elseif ($start_datetime >= $end_datetime) {
            $error_message = "Start time must be before end time.";
        } else {
            try {
                $db = getDatabaseConnection();
                $query = "INSERT INTO `session` (session_title, session_date, city, venue, mode, start_time, end_time) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                if ($stmt) {
                    if ($stmt->execute([$title, $date, $city, $venue, $mode, $start_time, $end_time])) {
                        $session_id = $db->lastInsertId();
                        $query = "INSERT INTO conducts (person_id, session_id, role, response_status) VALUES (?, ?, 'Organizer', 'Confirmed')";
                        $stmt = $db->prepare($query);
                        if ($stmt) {
                            if ($stmt->execute([$_SESSION['user_id'], $session_id])) {
                                $success_message = "Mentorship session created successfully! You are now the organizer.";
                                logActivity($_SESSION['user_id'], 'Session Created', "Created session: $title (ID: $session_id) as Organizer");
                            } else {
                                $error_message = "Failed to register user as organizer.";
                                error_log("Error registering user as organizer for session ID: $session_id - " . implode(" ", $stmt->errorInfo()));
                            }
                        } else {
                            $error_message = "Failed to prepare organizer registration query.";
                            error_log("Error preparing query for organizer registration - " . implode(" ", $db->errorInfo()));
                        }
                    } else {
                        $error_message = "Failed to create session.";
                        error_log("Error executing INSERT query for session: $title - " . implode(" ", $stmt->errorInfo()));
                    }
                } else {
                    $error_message = "Failed to prepare session creation query.";
                    error_log("Error preparing query for session creation - " . implode(" ", $db->errorInfo()));
                }
            } catch (Exception $e) {
                $error_message = "Error creating session: " . $e->getMessage();
                error_log("Exception in session creation: " . $e->getMessage());
            }
        }
    } else {
        $error_message = "All fields are required.";
    }
}

// Handle join session request (Students only - based on matching interests)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'join_session' && isLoggedIn() && isStudent($_SESSION['user_id'])) {
    $session_id = (int)($_POST['session_id'] ?? 0);
    $user_id = $_SESSION['user_id'];

    // Check if session exists and has an organizer
    if (!sessionHasOrganizer($session_id)) {
        $error_message = "This session does not have an organizer. Cannot join.";
    } elseif (!hasMatchingInterests($user_id, $session_id)) {
        $error_message = "You can only join sessions where your interests match with the mentor's interests.";
    } else {
        // Check current registration status
        $current_registration = getUserSessionRegistrationStatus($user_id, $session_id);

        if ($current_registration && in_array($current_registration['response_status'], ['Pending', 'Confirmed'])) {
            $error_message = "You already have an active registration for this session.";
        } else {
            try {
                // If user was previously cancelled/rejected, delete old record and create new one
                if ($current_registration) {
                    $query = "DELETE FROM conducts WHERE person_id = ? AND session_id = ?";
                    executeQuery($query, [$user_id, $session_id]);
                }

                $query = "INSERT INTO conducts (person_id, session_id, role, response_status) VALUES (?, ?, 'Attendee', 'Pending')";
                $stmt = executeQuery($query, [$user_id, $session_id]);
                if ($stmt) {
                    $success_message = "Join request submitted successfully! Awaiting mentor approval.";
                    logActivity($user_id, 'Session Join Request', "Requested to join session ID: $session_id as Attendee");
                } else {
                    $error_message = "Error submitting join request.";
                    error_log("Error executing INSERT query for join request, session ID: $session_id");
                }
            } catch (Exception $e) {
                $error_message = "Error submitting join request: " . $e->getMessage();
                error_log("Exception in join request: " . $e->getMessage());
            }
        }
    }
}

// Handle session editing (Organizer only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_session' && isLoggedIn()) {
    $session_id = (int)($_POST['session_id'] ?? 0);
    if (!isSessionOrganizer($_SESSION['user_id'], $session_id)) {
        $error_message = "You are not authorized to edit this session.";
        error_log("Unauthorized edit attempt for session_id: $session_id by user_id: {$_SESSION['user_id']}");
    } else {
        $title = sanitizeInput($_POST['session_title'] ?? '');
        $date = sanitizeInput($_POST['session_date'] ?? '');
        $city = sanitizeInput($_POST['city'] ?? '');
        $venue = sanitizeInput($_POST['venue'] ?? '');
        $mode = sanitizeInput($_POST['mode'] ?? '');
        $start_time = sanitizeInput($_POST['start_time'] ?? '');
        $end_time = sanitizeInput($_POST['end_time'] ?? '');

        $session_date = new DateTime($date);
        $current_date = new DateTime();
        $start_datetime = DateTime::createFromFormat('Y-m-d H:i', "$date $start_time");
        $end_datetime = DateTime::createFromFormat('Y-m-d H:i', "$date $end_time");

        if ($title && $date && $city && $venue && $mode && $start_time && $end_time) {
            if ($session_date < $current_date) {
                $error_message = "Session date must be in the future.";
            } elseif ($start_datetime >= $end_datetime) {
                $error_message = "Start time must be before end time.";
            } else {
                try {
                    $query = "UPDATE `session` SET session_title = ?, session_date = ?, city = ?, venue = ?, mode = ?, start_time = ?, end_time = ? WHERE session_id = ?";
                    $stmt = executeQuery($query, [$title, $date, $city, $venue, $mode, $start_time, $end_time, $session_id]);
                    if ($stmt) {
                        $success_message = "Session updated successfully!";
                        logActivity($_SESSION['user_id'], 'Session Updated', "Updated session ID: $session_id");
                    } else {
                        $error_message = "Error updating session.";
                        error_log("Error executing UPDATE query for session ID: $session_id");
                    }
                } catch (Exception $e) {
                    $error_message = "Error updating session: " . $e->getMessage();
                    error_log("Exception in session update: " . $e->getMessage());
                }
            }
        } else {
            $error_message = "All fields are required.";
        }
    }
}

// Handle session deletion (Organizer only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_session' && isLoggedIn()) {
    $session_id = (int)($_POST['session_id'] ?? 0);
    if (!isSessionOrganizer($_SESSION['user_id'], $session_id)) {
        $error_message = "You are not authorized to delete this session.";
        error_log("Unauthorized delete attempt for session_id: $session_id by user_id: {$_SESSION['user_id']}");
    } else {
        try {
            $del_reg_query = "DELETE FROM conducts WHERE session_id = ?";
            executeQuery($del_reg_query, [$session_id]);
            $del_session_query = "DELETE FROM session WHERE session_id = ?";
            $stmt = executeQuery($del_session_query, [$session_id]);
            if ($stmt) {
                $success_message = "Session deleted successfully!";
                logActivity($_SESSION['user_id'], 'Session Deleted', "Deleted session ID: $session_id");
            } else {
                $error_message = "Error deleting session.";
                error_log("Error deleting session, session_id: $session_id");
            }
        } catch (Exception $e) {
            $error_message = "Error deleting session: " . $e->getMessage();
            error_log("Exception in session deletion: " . $e->getMessage());
        }
    }
}

// Handle cancelling join request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_join_request' && isLoggedIn()) {
    $session_id = (int)($_POST['session_id'] ?? 0);
    $user_id = $_SESSION['user_id'];

    $current_registration = getUserSessionRegistrationStatus($user_id, $session_id);

    if ($current_registration && in_array($current_registration['response_status'], ['Pending', 'Confirmed']) && !isSessionOrganizer($user_id, $session_id)) {
        try {
            $query = "DELETE FROM conducts WHERE person_id = ? AND session_id = ?";
            $stmt = executeQuery($query, [$user_id, $session_id]);
            if ($stmt) {
                $success_message = "Join request cancelled successfully!";
                logActivity($user_id, 'Session Join Request Cancelled', "Cancelled join request for session ID: $session_id");
            } else {
                $error_message = "Error cancelling join request.";
                error_log("Error executing DELETE query for join request, session ID: $session_id");
            }
        } catch (Exception $e) {
            $error_message = "Error cancelling join request: " . $e->getMessage();
            error_log("Exception in join request cancellation: " . $e->getMessage());
        }
    } else {
        $error_message = "Cannot cancel this request.";
    }
}

// Handle feedback submission - Enhanced to allow feedback only for confirmed participants of completed sessions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_session_feedback' && isLoggedIn()) {
    $session_id = (int)($_POST['session_id'] ?? 0);
    $feedback = sanitizeInput($_POST['feedback'] ?? '');
    $user_id = $_SESSION['user_id'];

    // Get session details to check if it's completed
    $session_query = "SELECT session_date FROM session WHERE session_id = ?";
    $session_stmt = executeQuery($session_query, [$session_id]);
    $session_details = $session_stmt ? $session_stmt->fetch(PDO::FETCH_ASSOC) : false;

    $current_registration = getUserSessionRegistrationStatus($user_id, $session_id);

    if (!$session_details) {
        $error_message = "Session not found.";
    } elseif (!$current_registration || $current_registration['response_status'] !== 'Confirmed') {
        $error_message = "You can only submit feedback for sessions you have confirmed attendance.";
    } elseif (!isSessionCompleted($session_details['session_date'])) {
        $error_message = "You can only submit feedback for completed sessions.";
    } elseif (empty(trim($feedback))) {
        $error_message = "Feedback cannot be empty.";
    } else {
        try {
            $query = "UPDATE conducts SET feedback = ? WHERE person_id = ? AND session_id = ?";
            $stmt = executeQuery($query, [$feedback, $user_id, $session_id]);
            if ($stmt) {
                $success_message = "Feedback submitted successfully!";
                logActivity($user_id, 'Feedback Submitted', "Submitted feedback for session ID: $session_id");
            } else {
                $error_message = "Error submitting feedback.";
                error_log("Error updating feedback for user_id: $user_id, session_id: $session_id");
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
    in_array($_POST['action'], ['approve_session_registration', 'reject_session_registration', 'update_session_roles']) &&
    isLoggedIn()
) {
    $user_id = $_SESSION['user_id'] ?? 0;
    $session_id = isset($_POST['session_id']) ? (int) $_POST['session_id'] : 0;
    $person_ids = isset($_POST['person_ids']) ? array_map('intval', (array) $_POST['person_ids']) : [];
    $roles = isset($_POST['roles']) ? array_map('sanitizeInput', (array) $_POST['roles']) : [];

    $action = $_POST['action'];
    $log_action = $action === 'approve_session_registration' ? 'Approved' : ($action === 'reject_session_registration' ? 'Rejected' : 'Role Updated');
    $new_status = $action === 'approve_session_registration' ? 'Confirmed' : ($action === 'reject_session_registration' ? 'Cancelled' : null);

    error_log("[$log_action] Request received | User ID: $user_id | Session ID: $session_id | Person IDs: " . json_encode($person_ids) . " | Roles: " . json_encode($roles));

    if ($session_id <= 0 || $user_id <= 0) {
        $error_message = "Invalid session or user.";
        error_log("[$log_action] Invalid session_id ($session_id) or user_id ($user_id). Aborting.");
    } elseif (empty($person_ids) && $action !== 'update_session_roles') {
        $error_message = "No participants selected for $log_action.";
        error_log("[$log_action] No person IDs provided. Aborting.");
    } elseif (!isSessionOrganizer($user_id, $session_id)) {
        $error_message = "You are not authorized to $log_action registrations for this session.";
        error_log("[$log_action] Unauthorized access attempt by user_id: $user_id for session_id: $session_id");
    } else {
        try {
            if ($action === 'approve_session_registration') {
                if (count($person_ids) !== count($roles)) {
                    $error_message = "Invalid number of roles provided.";
                    error_log("[APPROVAL] Mismatch between person_ids and roles. Person IDs: " . json_encode($person_ids) . ", Roles: " . json_encode($roles));
                } else {
                    $affected_rows = 0;
                    foreach ($person_ids as $index => $person_id) {
                        $role = in_array($roles[$index], ['Attendee', 'Speaker', 'Volunteer']) ? $roles[$index] : 'Attendee';
                        $query = "UPDATE conducts 
                                  SET role = ?, response_status = ? 
                                  WHERE person_id = ? AND session_id = ? AND response_status = 'Pending'";
                        $stmt = executeQuery($query, [$role, $new_status, $person_id, $session_id]);
                        if ($stmt) {
                            $affected_rows += $stmt->rowCount();
                            logActivity(
                                $user_id,
                                "Session Registration $log_action",
                                "$log_action person_id: $person_id for session ID: $session_id with role: $role"
                            );
                        } else {
                            error_log("[APPROVAL] Failed to execute query for person_id: $person_id, session_id: $session_id");
                        }
                    }

                    if ($affected_rows > 0) {
                        $success_message = "Session registrations approved successfully!";
                    } else {
                        $error_message = "No pending requests found or error occurred.";
                        error_log("[APPROVAL] No pending requests for Session ID: $session_id, Person IDs: " . json_encode($person_ids));
                    }
                }
            } elseif ($action === 'reject_session_registration') {
                $placeholders = implode(',', array_fill(0, count($person_ids), '?'));
                $query = "UPDATE conducts 
                          SET response_status = ? 
                          WHERE session_id = ? AND person_id IN ($placeholders)";
                $params = array_merge([$new_status, $session_id], $person_ids);

                $stmt = executeQuery($query, $params);
                if ($stmt === false) {
                    $error_message = "Error executing rejection query.";
                    $db = getDatabaseConnection();
                    error_log("[REJECTION] Query execution failed for Session ID: $session_id, Person IDs: " . json_encode($person_ids) . ", Error: " . implode(" ", $db->errorInfo()));
                } else {
                    $affected_rows = $stmt->rowCount();
                    if ($affected_rows > 0) {
                        $success_message = "Session registrations rejected successfully!";
                        foreach ($person_ids as $pid) {
                            logActivity(
                                $user_id,
                                "Session Registration Rejected",
                                "Rejected person_id: $pid for session ID: $session_id"
                            );
                        }
                    } else {
                        $error_message = "No matching registrations found for the selected participants.";
                        error_log("[REJECTION] No matching records for Session ID: $session_id, Person IDs: " . json_encode($person_ids));
                    }
                }
            } elseif ($action === 'update_session_roles') {
                if (count($person_ids) !== count($roles)) {
                    $error_message = "Invalid number of roles provided.";
                    error_log("[ROLE UPDATE] Mismatch between person_ids and roles. Person IDs: " . json_encode($person_ids) . ", Roles: " . json_encode($roles));
                } else {
                    $affected_rows = 0;
                    foreach ($person_ids as $index => $person_id) {
                        $role = in_array($roles[$index], ['Attendee', 'Speaker', 'Volunteer']) ? $roles[$index] : 'Attendee';
                        $query = "UPDATE conducts 
                                  SET role = ? 
                                  WHERE person_id = ? AND session_id = ?";
                        $stmt = executeQuery($query, [$role, $person_id, $session_id]);
                        if ($stmt) {
                            $affected_rows += $stmt->rowCount();
                            logActivity(
                                $user_id,
                                "Session Registration Role Updated",
                                "Updated role for person_id: $person_id for session ID: $session_id to role: $role"
                            );
                        } else {
                            error_log("[ROLE UPDATE] Failed to execute query for person_id: $person_id, session_id: $session_id");
                        }
                    }

                    if ($affected_rows > 0) {
                        $success_message = "Participant roles updated successfully!";
                    } else {
                        $error_message = "No roles updated or error occurred.";
                        error_log("[ROLE UPDATE] No matching records found for Session ID: $session_id, Person IDs: " . json_encode($person_ids));
                    }
                }
            }
        } catch (Exception $e) {
            $error_message = "Error $log_action registrations: " . $e->getMessage();
            error_log("[$log_action] Exception: " . $e->getMessage());
        }
    }
}

// Get distinct session modes for filter dropdown
$session_modes = ['Online', 'Offline', 'Hybrid'];

if (!isset($_SESSION['sessions_loaded'])) {
    $_SESSION['sessions_loaded'] = true;
}

// Build the database query for sessions
try {
    // Step 1: Build count query (for pagination)
    $count_query = "SELECT COUNT(*) FROM session WHERE 1=1";
    $count_params = [];

    if (!empty($search)) {
        $count_query .= " AND type = ?";
        $count_params[] = $mode;
    }

    $count_stmt = executeQuery($count_query, $count_params);
    if ($count_stmt === false) {
        throw new Exception("Failed to execute count query");
    }

    $total_sessions = $count_stmt->fetchColumn();
    $total_pages = ceil($total_sessions / $sessions_per_page);

    // Step 2: Build main session query
    $query = "SELECT * FROM session WHERE 1=1";
    $params = [];

    if (!empty($search)) {
        $query .= " AND (session_title LIKE ? OR city LIKE ? OR venue LIKE ?)";
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }

    if (!empty($session_mode)) {
        $query .= " AND mode = ?";
        $params[] = $session_mode;
    }

    $offset = ($page - 1) * $sessions_per_page;
    $query .= " ORDER BY session_date ASC LIMIT " . (int)$offset . ", " . (int)$sessions_per_page;

    $stmt = executeQuery($query, $params);
    if ($stmt === false) {
        throw new Exception("Failed to execute session query");
    }

    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Step 3: Separate upcoming and past sessions
    $upcomingSessions = [];
    $pastSessions = [];
    $current_date = new DateTime('2025-08-17');

    foreach ($sessions as $session) {
        $session_date = new DateTime($session['session_date']);
        if ($session_date >= $current_date) {
            $upcomingSessions[] = $session;
        } else {
            $pastSessions[] = $session;
        }
    }

    // Step 4: Get registration status
    $user_registrations = [];
    if (isLoggedIn()) {
        $query = "SELECT c.session_id, c.role, c.response_status, c.feedback,
                         CONCAT(p.first_name, ' ', p.last_name) AS full_name,
                         CASE 
                             WHEN a.person_id IS NOT NULL THEN 'Alumni'
                             WHEN s.person_id IS NOT NULL THEN 'Student'
                             ELSE 'Unknown'
                         END AS user_status,
                         (SELECT phone_number FROM person_phone pp WHERE pp.person_id = c.person_id LIMIT 1) AS primary_phone,
                         (SELECT email FROM email_address ea WHERE ea.person_id = c.person_id LIMIT 1) AS primary_email
                  FROM conducts c 
                  JOIN person p ON c.person_id = p.person_id 
                  LEFT JOIN alumni a ON c.person_id = a.person_id 
                  LEFT JOIN student s ON c.person_id = s.person_id 
                  WHERE c.person_id = ?";
        $stmt = executeQuery($query, [$_SESSION['user_id']]);
        if ($stmt) {
            $registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($registrations as $reg) {
                $user_registrations[$reg['session_id']] = $reg;
            }
        } else {
            error_log("Error fetching user registrations for user_id: {$_SESSION['user_id']}");
        }
    }
} catch (Exception $e) {
    $error_message = "Error fetching sessions: " . $e->getMessage();
    error_log("Exception fetching sessions: " . $e->getMessage());
}

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

        .session-card {
            background: #fff;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .session-card:hover {
            transform: translateY(-5px);
        }

        .session-form {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
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

        .form-control,
        .form-select {
            border-radius: 8px;
            border: 1px solid #ced4da;
            padding: 10px;
            font-size: 1rem;
        }

        .form-control:focus,
        .form-select:focus {
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

        .badge-organizer {
            background-color: #dc3545;
        }

        .badge-speaker {
            background-color: #28a745;
        }

        .badge-volunteer {
            background-color: #17a2b8;
        }

        .badge-sponsor {
            background-color: #ffc107;
            color: #212529;
        }

        .badge-attendee {
            background-color: #6c757d;
        }

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

            .feedback-card .card-header>div:last-child {
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
            <h1 class="display-4">Mentorship</h1>
            <?php if (isLoggedIn() && isAlumni($_SESSION['user_id'])): ?>
                <button class="btn btn-light mt-3" data-bs-toggle="modal" data-bs-target="#addSessionModal">
                    <i class="fas fa-plus me-2"></i>Register Session
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
                    <select name="session_mode" class="form-select">
                        <option value="">All Session Modes</option>
                        <?php foreach ($session_modes as $mode): ?>
                            <option value="<?php echo htmlspecialchars($mode); ?>" <?php echo $session_mode === $mode ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($mode); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary">Filter</button>
                </div>
            </form>
        </div>
    </section>

    <!-- Sessions Section -->
    <section class="py-5">
        <div class="container">
            <?php if ($success_message): ?>
                <div class="alert alert-success text-center rounded-3" data-aos="fade-up"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="alert alert-danger text-center rounded-3" data-aos="fade-up"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <ul class="nav nav-tabs mb-4" id="sessionTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="upcoming-tab" data-bs-toggle="tab" data-bs-target="#upcoming" type="button" role="tab" style="color: #002147 !important;">Upcoming Sessions</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="past-tab" data-bs-toggle="tab" data-bs-target="#past" type="button" role="tab" style="color: #002147 !important;">Past Sessions</button>
                </li>
            </ul>

            <div class="tab-content" id="sessionTabContent">
                <!-- Upcoming Sessions Tab -->
                <div class="tab-pane fade show active" id="upcoming" role="tabpanel">
                    <div class="row g-4" data-aos="fade-up" data-aos-delay="100">
                        <?php if (empty($upcomingSessions)): ?>
                            <div class="col-12 text-center py-5">
                                <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                <p class="text-muted"><?php echo empty($error_message) ? 'No upcoming sessions found.' : ''; ?></p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($upcomingSessions as $session): ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="session-card">
                                        <h5><?php echo htmlspecialchars($session['session_title']); ?></h5>
                                        <p><i class="fas fa-calendar me-2"></i><?php echo date('F j, Y', strtotime($session['session_date'])); ?></p>
                                        <p><i class="fas fa-clock me-2"></i><?php echo htmlspecialchars($session['start_time']) . ' - ' . htmlspecialchars($session['end_time']); ?></p>
                                        <p><i class="fas fa-map-marker-alt me-2"></i><?php echo htmlspecialchars($session['venue'] . ', ' . $session['city']); ?></p>
                                        <p><i class="fas fa-desktop me-2"></i><?php echo htmlspecialchars($session['mode']); ?></p>

                                        <?php if (!sessionHasOrganizer($session['session_id'])): ?>
                                            <p class="text-warning"><i class="fas fa-exclamation-triangle me-2"></i>No organizer assigned</p>
                                        <?php endif; ?>

                                        <?php if (isLoggedIn() && isset($user_registrations[$session['session_id']])): ?>
                                            <p><strong>Status:</strong>
                                                <span class="badge bg-<?php echo $user_registrations[$session['session_id']]['response_status'] == 'Confirmed' ? 'success' : ($user_registrations[$session['session_id']]['response_status'] == 'Pending' ? 'warning' : 'danger'); ?>">
                                                    <?php echo htmlspecialchars($user_registrations[$session['session_id']]['response_status']); ?>
                                                </span>
                                            </p>

                                        <?php endif; ?>

                                        <!-- Show matching interests for students -->
                                        <?php if (isLoggedIn() && isStudent($_SESSION['user_id'])): ?>
                                            <?php
                                            $matchingInterests = getMatchingInterests($_SESSION['user_id'], $session['session_id']);
                                            ?>
                                            <?php if (!empty($matchingInterests)): ?>
                                                <div class="matching-interests">
                                                    <small><i class="fas fa-heart text-success me-1"></i><strong>Matching Interests:</strong> <?php echo implode(', ', $matchingInterests); ?></small>
                                                </div>
                                            <?php else: ?>
                                                <div class="no-matching-interests">
                                                    <small><i class="fas fa-info-circle text-warning me-1"></i><strong>No matching interests</strong> - You can only join sessions with matching interests</small>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <div class="d-flex gap-2 flex-wrap mt-3">
                                            <?php if (isLoggedIn()): ?>
                                                <?php if (isSessionOrganizer($_SESSION['user_id'], $session['session_id'])): ?>
                                                    <!-- Organizer buttons -->
                                                    <?php if (!isSessionCompleted($session['session_date'])): ?>
                                                        <!-- Upcoming session organizer buttons -->
                                                        <button class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editSessionModal" data-session-id="<?php echo $session['session_id']; ?>" data-title="<?php echo htmlspecialchars($session['session_title']); ?>" data-date="<?php echo $session['session_date']; ?>" data-city="<?php echo htmlspecialchars($session['city']); ?>" data-venue="<?php echo htmlspecialchars($session['venue']); ?>" data-mode="<?php echo htmlspecialchars($session['mode']); ?>" data-start-time="<?php echo htmlspecialchars($session['start_time']); ?>" data-end-time="<?php echo htmlspecialchars($session['end_time']); ?>">Edit</button>
                                                        <form method="POST" style="display:inline;">
                                                            <input type="hidden" name="action" value="delete_session">
                                                            <input type="hidden" name="session_id" value="<?php echo $session['session_id']; ?>">
                                                            <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Are you sure you want to delete this session?');">Delete</button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <button class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#approveSessionRegistrationModal" data-session-id="<?php echo $session['session_id']; ?>" data-session-title="<?php echo htmlspecialchars($session['session_title']); ?>">Manage</button>
                                                    <button class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#viewSessionParticipantsModal" data-session-id="<?php echo $session['session_id']; ?>" data-session-title="<?php echo htmlspecialchars($session['session_title']); ?>">Participants (<?php echo getTotalSessionParticipants($session['session_id']); ?>)</button>

                                                    <div class="dropdown">
                                                        <button class="btn btn-outline-primary btn-sm dropdown-toggle" type="button" id="feedbackDropdown<?php echo $session['session_id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">Feedback</button>
                                                        <ul class="dropdown-menu" aria-labelledby="feedbackDropdown<?php echo $session['session_id']; ?>">
                                                            <li>
                                                                <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#viewAllSessionFeedbackModal" data-session-id="<?php echo $session['session_id']; ?>" data-session-title="<?php echo htmlspecialchars($session['session_title']); ?>">
                                                                    View Feedback
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#sessionFeedbackModal" data-session-id="<?php echo $session['session_id']; ?>" data-session-title="<?php echo htmlspecialchars($session['session_title']); ?>" data-current-feedback="">
                                                                    Send Feedback
                                                                </a>
                                                            </li>
                                                        </ul>
                                                    </div>

                                                <?php else: ?>
                                                    <!-- Non-organizer buttons -->
                                                    <?php
                                                    $current_registration = isset($user_registrations[$session['session_id']]) ? $user_registrations[$session['session_id']] : false;
                                                    ?>
                                                    <?php if (!isSessionCompleted($session['session_date'])): ?>
                                                        <!-- Upcoming session buttons for participants -->
                                                        <?php if ($current_registration && in_array($current_registration['response_status'], ['Pending', 'Confirmed'])): ?>
                                                            <form method="POST" style="display:inline;">
                                                                <input type="hidden" name="action" value="cancel_join_request">
                                                                <input type="hidden" name="session_id" value="<?php echo $session['session_id']; ?>">
                                                                <button type="submit" class="btn btn-outline-danger btn-sm">Cancel Request</button>
                                                            </form>
                                                        <?php elseif (sessionHasOrganizer($session['session_id']) && isStudent($_SESSION['user_id']) && hasMatchingInterests($_SESSION['user_id'], $session['session_id'])): ?>
                                                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#joinSessionModal" data-session-id="<?php echo $session['session_id']; ?>" data-session-title="<?php echo htmlspecialchars($session['session_title']); ?>">Join Session</button>
                                                        <?php elseif (isStudent($_SESSION['user_id']) && !hasMatchingInterests($_SESSION['user_id'], $session['session_id'])): ?>
                                                            <button class="btn btn-secondary btn-sm" disabled title="No matching interests with mentor">Cannot Join</button>
                                                        <?php endif; ?>
                                                    <?php endif; ?>

                                                    <!-- View All Feedback button for confirmed participants -->
                                                    <?php if ($current_registration && $current_registration['response_status'] === 'Confirmed'): ?>
                                                        <div class="dropdown">
                                                            <button class="btn btn-outline-primary btn-sm dropdown-toggle" type="button" id="feedbackDropdown<?php echo $session['session_id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                                                <i class="fas fa-comments me-1"></i>Feedback
                                                            </button>
                                                            <ul class="dropdown-menu" aria-labelledby="feedbackDropdown<?php echo $session['session_id']; ?>">
                                                                <li>
                                                                    <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#viewAllSessionFeedbackModal" data-session-id="<?php echo $session['session_id']; ?>" data-session-title="<?php echo htmlspecialchars($session['session_title']); ?>">
                                                                        View Feedback
                                                                    </a>
                                                                </li>
                                                                <li>
                                                                    <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#sessionFeedbackModal" data-session-id="<?php echo $session['session_id']; ?>" data-session-title="<?php echo htmlspecialchars($session['session_title']); ?>" data-current-feedback="">
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

                <!-- Past Sessions Tab -->
                <div class="tab-pane fade" id="past" role="tabpanel">
                    <div class="row g-4" data-aos="fade-up" data-aos-delay="100">
                        <?php if (empty($pastSessions)): ?>
                            <div class="col-12 text-center py-5">
                                <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                <p class="text-muted"><?php echo empty($error_message) ? 'No past sessions found.' : ''; ?></p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($pastSessions as $session): ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="session-card">
                                        <h5><?php echo htmlspecialchars($session['session_title']); ?></h5>
                                        <p><i class="fas fa-calendar me-2"></i><?php echo date('F j, Y', strtotime($session['session_date'])); ?></p>
                                        <p><i class="fas fa-clock me-2"></i><?php echo htmlspecialchars($session['start_time']) . ' - ' . htmlspecialchars($session['end_time']); ?></p>
                                        <p><i class="fas fa-map-marker-alt me-2"></i><?php echo htmlspecialchars($session['venue'] . ', ' . $session['city']); ?></p>
                                        <p><i class="fas fa-desktop me-2"></i><?php echo htmlspecialchars($session['mode']); ?></p>

                                        <?php if (!sessionHasOrganizer($session['session_id'])): ?>
                                            <p class="text-warning"><i class="fas fa-exclamation-triangle me-2"></i>No organizer assigned</p>
                                        <?php endif; ?>

                                        <?php if (isLoggedIn() && isset($user_registrations[$session['session_id']])): ?>
                                            <p><strong>Status:</strong>
                                                <span class="badge bg-<?php echo $user_registrations[$session['session_id']]['response_status'] == 'Confirmed' ? 'success' : ($user_registrations[$session['session_id']]['response_status'] == 'Pending' ? 'warning' : 'danger'); ?>">
                                                    <?php echo htmlspecialchars($user_registrations[$session['session_id']]['response_status']); ?>
                                                </span>
                                            </p>

                                        <?php endif; ?>

                                        <div class="d-flex gap-2 flex-wrap mt-3">
                                            <?php if (isLoggedIn()): ?>
                                                <?php if (isSessionOrganizer($_SESSION['user_id'], $session['session_id'])): ?>
                                                    <button class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#viewSessionParticipantsModal" data-session-id="<?php echo $session['session_id']; ?>" data-session-title="<?php echo htmlspecialchars($session['session_title']); ?>">Participants (<?php echo getTotalSessionParticipants($session['session_id']); ?>)</button>
                                                    <div class="dropdown">
                                                        <button class="btn btn-outline-primary btn-sm dropdown-toggle" type="button" id="feedbackDropdown<?php echo $session['session_id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">Feedback</button>
                                                        <ul class="dropdown-menu" aria-labelledby="feedbackDropdown<?php echo $session['session_id']; ?>">
                                                            <li>
                                                                <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#viewAllSessionFeedbackModal" data-session-id="<?php echo $session['session_id']; ?>" data-session-title="<?php echo htmlspecialchars($session['session_title']); ?>">
                                                                    View Feedback
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#sessionFeedbackModal" data-session-id="<?php echo $session['session_id']; ?>" data-session-title="<?php echo htmlspecialchars($session['session_title']); ?>" data-current-feedback="">
                                                                    Send Feedback
                                                                </a>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                <?php elseif (isset($user_registrations[$session['session_id']]) && $user_registrations[$session['session_id']]['response_status'] === 'Confirmed'): ?>
                                                    <div class="dropdown">
                                                        <button class="btn btn-outline-primary btn-sm dropdown-toggle" type="button" id="feedbackDropdown<?php echo $session['session_id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                                            <i class="fas fa-comments me-1"></i>Feedback
                                                        </button>
                                                        <ul class="dropdown-menu" aria-labelledby="feedbackDropdown<?php echo $session['session_id']; ?>">
                                                            <li>
                                                                <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#viewAllSessionFeedbackModal" data-session-id="<?php echo $session['session_id']; ?>" data-session-title="<?php echo htmlspecialchars($session['session_title']); ?>">
                                                                    View Feedback
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#sessionFeedbackModal" data-session-id="<?php echo $session['session_id']; ?>" data-session-title="<?php echo htmlspecialchars($session['session_title']); ?>" data-current-feedback="">
                                                                    Send Feedback
                                                                </a>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <a href="/auth/signin.php" class="btn btn-primary btn-sm">Sign In to View</a>
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
                <nav aria-label="Page navigation" class="mt-5">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&session_mode=<?php echo urlencode($session_mode); ?>">Previous</a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&session_mode=<?php echo urlencode($session_mode); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&session_mode=<?php echo urlencode($session_mode); ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </section>

    <!-- Add Session Modal -->
    <div class="modal fade" id="addSessionModal" tabindex="-1" aria-labelledby="addSessionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background-color: #002147; color: white;">
                    <h5 class="modal-title" id="addSessionModalLabel">Register Session</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_session">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            You will automatically become the organizer of this session.
                        </div>

                        <div class="mb-3">
                            <label for="session_title" class="form-label">Session Title</label>
                            <input type="text" class="form-control" id="session_title" name="session_title" required>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="session_date" class="form-label">Session Date</label>
                                <input type="date" class="form-control" id="session_date" name="session_date" required min="<?php echo date('Y-m-d'); ?>">
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
                            <label for="mode" class="form-label">Mode</label>
                            <select class="form-select" id="mode" name="mode" required>
                                <option value=" ">Select Mode</option>
                                <option value="Online">Online</option>
                                <option value="Offline">Offline</option>
                                <option value="Hybrid">Hybrid</option>
                            </select>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary" style="background-color:#002147; color:white;">Register Session</button>
                        </div>
                </form>
            </div>
        </div>
    </div>
    </div>

    <!-- Join Session Modal -->
    <div class="modal fade" id="joinSessionModal" tabindex="-1" aria-labelledby="joinSessionModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="joinSessionModalLabel">Join Session</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="join_session">
                        <input type="hidden" name="session_id" id="join_session_id">
                        <p>You are requesting to join: <strong id="join_session_title"></strong></p>
                        <button type="submit" class="btn btn-success">Submit Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Session Modal -->
    <div class="modal fade" id="editSessionModal" tabindex="-1" aria-labelledby="editSessionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background-color:#ffc107; color:#212529;">
                    <h5 class="modal-title" id="editSessionModalLabel">Edit Session</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_session">
                        <input type="hidden" name="session_id" id="edit_session_id">
                        <div class="mb-3">
                            <label for="edit_session_title" class="form-label">Session Title</label>
                            <input type="text" class="form-control" id="edit_session_title" name="session_title" required>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_session_date" class="form-label">Session Date</label>
                                <input type="date" class="form-control" id="edit_session_date" name="session_date" required min="<?php echo date('Y-m-d'); ?>">
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
                            <label for="edit_venue" class="form-label">Venue</label>
                            <input type="text" class="form-control" id="edit_venue" name="venue" required>
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
                            <label for="edit_mode" class="form-label">Mode</label>
                            <select class="form-select" id="edit_mode" name="mode" required>
                                <option value="Online">Online</option>
                                <option value="Offline">Offline</option>
                                <option value="Hybrid">Hybrid</option>
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
    </div>

    <!-- Session Feedback Modal -->
    <div class="modal fade" id="sessionFeedbackModal" tabindex="-1" aria-labelledby="sessionFeedbackModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="feedbackModalLabel">Session Feedback</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="submit_session_feedback">
                        <input type="hidden" name="session_id" id="feedback_session_id">

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Note:</strong> Feedback can only be submitted for completed sessions that you attended.
                        </div>

                        <p>Feedback for: <strong id="feedback_session_title"></strong></p>
                        <div class="mb-3">
                            <label for="feedback" class="form-label">Your Feedback<span class="text-danger">*</span></label>
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

    <!-- Approve Session Registration Modal -->
    <div class="modal fade" id="approveSessionRegistrationModal" tabindex="-1" aria-labelledby="approveSessionRegistrationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="approveSessionRegistrationModalLabel">Manage Registrations</h5>
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
                                    <th>Select</th>
                                </tr>
                            </thead>
                            <tbody id="sessionRegistrantsTableBody">
                                <!-- Populated dynamically via JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-danger" id="rejectSessionSelectedBtn">Reject Selected</button>
                    <button type="button" class="btn btn-success" id="approveSessionSelectedBtn">Approve Selected</button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Participants Modal -->
    <div class="modal fade" id="viewSessionParticipantsModal" tabindex="-1" aria-labelledby="viewSessionParticipantsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="viewSessionParticipantsModalLabel">View Participants</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                            <tr>
                                <th>Name</th>
                                <th>Status</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th>Registration Status</th>
                            </tr>
                            </thead>
                            <tbody id="sessionParticipantsTableBody">
                            <!-- Populated dynamically via JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- View All Feedback Modal -->
    <div class="modal fade" id="viewAllSessionFeedbackModal" tabindex="-1" aria-labelledby="viewAllSessionFeedbackModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="viewAllSessionFeedbackModalLabel">Session Feedback</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="sessionFeedbackContent">
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
        AOS.init({
            duration: 1000,
            once: true
        });

        // Populate join session modal
        const joinSessionModal = document.getElementById('joinSessionModal');
        if (joinSessionModal) {
            joinSessionModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const sessionId = button.getAttribute('data-session-id');
                const sessionTitle = button.getAttribute('data-session-title');

                const modal = this;
                modal.querySelector('#join_session_id').value = sessionId;
                modal.querySelector('#join_session_title').textContent = sessionTitle;
            });
        }

        // Populate session feedback modal with enhanced validation
        const sessionFeedbackModal = document.getElementById('sessionFeedbackModal');
        if (sessionFeedbackModal) {
            sessionFeedbackModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const sessionId = button.getAttribute('data-session-id');
                const sessionTitle = button.getAttribute('data-session-title');
                const currentFeedback = button.getAttribute('data-current-feedback');

                const modal = this;
                modal.querySelector('#feedback_session_id').value = sessionId;
                modal.querySelector('#feedback_session_title').textContent = sessionTitle;
                modal.querySelector('#feedback').value = currentFeedback || '';

            });

            // Enhanced feedback form validation
            const feedbackForm = sessionFeedbackModal.querySelector('form');
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

        // Populate edit modal with session data
        const editSessionModal = document.getElementById('editSessionModal');
        if (editSessionModal) {
            editSessionModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const sessionId = button.getAttribute('data-session-id');
                const title = button.getAttribute('data-title');
                const date = button.getAttribute('data-date');
                const city = button.getAttribute('data-city');
                const venue = button.getAttribute('data-venue');
                const mode = button.getAttribute('data-mode');
                const startTime = button.getAttribute('data-start-time');
                const endTime = button.getAttribute('data-end-time');

                const modal = this;
                modal.querySelector('#edit_session_id').value = sessionId;
                modal.querySelector('#edit_session_title').value = title;
                modal.querySelector('#edit_session_date').value = date;
                modal.querySelector('#edit_city').value = city;
                modal.querySelector('#edit_venue').value = venue;
                modal.querySelector('#edit_mode').value = mode;
                modal.querySelector('#edit_start_time').value = startTime;
                modal.querySelector('#edit_end_time').value = endTime;
            });
        }

        // Populate approve session registration modal with registrants
        let currentSessionId = null; // Store session ID for approve/reject/update actions
        const approveSessionRegistrationModal = document.getElementById('approveSessionRegistrationModal');
        if (approveSessionRegistrationModal) {
            approveSessionRegistrationModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                currentSessionId = button.getAttribute('data-session-id');
                const sessionTitle = button.getAttribute('data-session-title');
                const modal = this;
                modal.querySelector('#approveSessionRegistrationModalLabel').textContent = `Manage Registrations for ${sessionTitle}`;

                // Fetch registrants via AJAX
                fetch('mentorship.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: `action=fetch_session_participants&session_id=${currentSessionId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        const tableBody = document.getElementById('sessionRegistrantsTableBody');
                        tableBody.innerHTML = '';
                        if (data.length === 0) {
                            tableBody.innerHTML = '<tr><td colspan="7" class="text-center">No registrants found.</td></tr>';
                        } else {
                            data.forEach(reg => {
                                const row = document.createElement('tr');
                                const roleOptions = ['Attendee', 'Speaker', 'Volunteer']
                                    .map(role => `<option value="${role}" ${reg.role === role ? 'selected' : ''}>${role}</option>`)
                                    .join('');

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
                        const checkboxes = document.querySelectorAll('#sessionRegistrantsTableBody input[name="person_ids[]"]');
                        const approveBtn = document.getElementById('approveSessionSelectedBtn');
                        const rejectBtn = document.getElementById('rejectSessionSelectedBtn');

                        const updateButtonState = () => {
                            const checked = document.querySelectorAll('#sessionRegistrantsTableBody input[name="person_ids[]"]:checked').length > 0;
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
                        document.getElementById('sessionRegistrantsTableBody').innerHTML = '<tr><td colspan="7" class="text-center">Error loading registrants.</td></tr>';
                    });
            });
        }

        // Handle Approve Selected button
        const approveSessionSelectedBtn = document.getElementById('approveSessionSelectedBtn');
        if (approveSessionSelectedBtn) {
            approveSessionSelectedBtn.addEventListener('click', function() {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'mentorship.php';
                form.style.display = 'none';

                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'approve_session_registration';
                form.appendChild(actionInput);

                const sessionIdInput = document.createElement('input');
                sessionIdInput.type = 'hidden';
                sessionIdInput.name = 'session_id';
                sessionIdInput.value = currentSessionId;
                form.appendChild(sessionIdInput);

                const checkboxes = document.querySelectorAll('#sessionRegistrantsTableBody input[name="person_ids[]"]:checked');
                const rows = document.querySelectorAll('#sessionRegistrantsTableBody tr');
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
        const rejectSessionSelectedBtn = document.getElementById('rejectSessionSelectedBtn');
        if (rejectSessionSelectedBtn) {
            rejectSessionSelectedBtn.addEventListener('click', function() {
                if (!confirm('Are you sure you want to reject the selected registrations?')) {
                    return;
                }

                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'mentorship.php';
                form.style.display = 'none';

                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'reject_session_registration';
                form.appendChild(actionInput);

                const sessionIdInput = document.createElement('input');
                sessionIdInput.type = 'hidden';
                sessionIdInput.name = 'session_id';
                sessionIdInput.value = currentSessionId;
                form.appendChild(sessionIdInput);

                const checkboxes = document.querySelectorAll('#sessionRegistrantsTableBody input[name="person_ids[]"]:checked');
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

        // Populate view session participants modal
        const viewSessionParticipantsModal = document.getElementById('viewSessionParticipantsModal');
        if (viewSessionParticipantsModal) {
            viewSessionParticipantsModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                currentSessionId = button.getAttribute('data-session-id');
                const sessionTitle = button.getAttribute('data-session-title');
                const modal = this;
                modal.querySelector('#viewSessionParticipantsModalLabel').textContent = `Participants for ${sessionTitle}`;

                // Fetch participants via AJAX
                fetch('mentorship.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `action=fetch_session_participants&session_id=${currentSessionId}`
                })
                    .then(response => response.json())
                    .then(data => {
                        const tableBody = document.getElementById('sessionParticipantsTableBody');
                        tableBody.innerHTML = '';
                        if (data.length === 0) {
                            tableBody.innerHTML = '<tr><td colspan="5" class="text-center">No participants found.</td></tr>';
                        } else {
                            data.forEach(participant => {
                                const row = document.createElement('tr');
                                row.innerHTML = `
                            <td>${participant.full_name} (${participant.user_status})</td>
                            <td>${participant.user_status}</td>
                            <td>${participant.primary_phone || 'N/A'}</td>
                            <td>${participant.primary_email || 'N/A'}</td>
                            <td><span class="badge bg-${participant.response_status === 'Confirmed' ? 'success' : (participant.response_status === 'Pending' ? 'warning' : 'danger')}">${participant.response_status}</span></td>
                        `;
                                tableBody.appendChild(row);
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching participants:', error);
                        document.getElementById('sessionParticipantsTableBody').innerHTML = '<tr><td colspan="5" class="text-center">Error loading participants.</td></tr>';
                    });
            });
        }

        // Populate view all feedback modal
        const viewAllSessionFeedbackModal = document.getElementById('viewAllSessionFeedbackModal');
        if (viewAllSessionFeedbackModal) {
            viewAllSessionFeedbackModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const sessionId = button.getAttribute('data-session-id');
                const sessionTitle = button.getAttribute('data-session-title');
                const modal = this;
                const feedbackContent = modal.querySelector('#sessionFeedbackContent');

                // Update modal title
                modal.querySelector('#viewAllSessionFeedbackModalLabel').textContent = `Feedback for "${sessionTitle}"`;

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
                fetch('mentorship.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: `action=fetch_all_session_feedback&session_id=${sessionId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        feedbackContent.innerHTML = '';

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
                            <p class="text-muted">No participants have submitted feedback for this session yet.</p>
                        </div>
                    `;
                            return;
                        }

                        let feedbackHtml = `
                    <div class="row mb-3">
                        <div class="col-12">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>${data.feedback.length}</strong> participant(s) have provided feedback for this session.
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
                                'Attendee': 'bg-secondary'
                            } [item.role] || 'bg-secondary';
                            const feedbackText = item.feedback.replace(/\n/g, '<br>');

                            feedbackHtml += `
                        <div class="card mb-3">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>${item.full_name}</strong>
                                    <span class="badge ${badgeClass} ms-2">${item.user_status}</span>
                                    <span class="badge ${roleBadgeClass} ms-1">${item.role}</span>
                                </div>
                            </div>
                            <div class="card-body">
                                <p class="card-text">${feedbackText}</p>
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