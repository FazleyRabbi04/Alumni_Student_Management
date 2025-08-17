<?php
require_once '../config/database.php';
startSecureSession();

// Require user to be logged in edits
requireLogin();

$user_id = $_SESSION['user_id'];
if (isset($_GET['cancel_edit'])) {
    $edit_mode = false;
    unset($_SESSION['edit_mode']);
}
$error = '';
$success = '';
$edit_mode = $_SESSION['edit_mode'] ?? false;

// Get user informations
$user_info = getUserInfo($user_id);
if (!$user_info) {
    $error = 'Unable to load user profile.';
}
// Define user type here so it's available for POST processing
$user_type = '';
$year_info = '';
if ($user_info) {
    if (!empty($user_info['grad_year'])) {
        $user_type = 'Alumni';
        $year_info = $user_info['grad_year'];
    } elseif (!empty($user_info['batch_year'])) {
        $user_type = 'Student';
        $year_info = $user_info['batch_year'];
    }
}
// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'enable_edit':
            // Verify password to enable edit mode
            $current_password = $_POST['verify_password'] ?? '';
            $user_query = "SELECT password FROM person WHERE person_id = ?";
            $user_stmt  = executeQuery($user_query, [$user_id]);

            if ($user_stmt && $user_stmt->rowCount() > 0) {
                $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
                if (password_verify($current_password, $user_data['password'])) {
                    $edit_mode = true;
                    $_SESSION['edit_mode'] = true;
                    $success = 'Edit mode enabled. You can now modify your information.';
                } else {
                    $error = 'Incorrect password. Please try again.';
                }
            } else {
                $error = 'Unable to verify account.';
            }
            break;

        case 'update_profile':
            // Update profile information
            $first_name   = trim($_POST['first_name'] ?? '');
            $last_name    = trim($_POST['last_name'] ?? '');
            $street       = trim($_POST['street'] ?? '');
            $city         = trim($_POST['city'] ?? '');
            $zip          = trim($_POST['zip'] ?? '');
            $gender       = $_POST['gender'] ?? '';
            $department   = trim($_POST['department'] ?? '');
            $date_of_birth = $_POST['date_of_birth'] ?? '';
            $shift_role   = $_POST['shift_role'] ?? '';

            if ($first_name === '' || $last_name === '') {
                $error = 'First name and last name are required.';
                break;
            }

            try {
                $update_query = "UPDATE person
                                 SET first_name = ?, last_name = ?, street = ?, city = ?, zip = ?, gender = ?, department = ?, date_of_birth = ?
                                 WHERE person_id = ?";
                $update_stmt = executeQuery($update_query, [
                    $first_name,
                    $last_name,
                    $street,
                    $city,
                    $zip,
                    $gender,
                    $department,
                    $date_of_birth,
                    $user_id
                ]);

                if ($update_stmt) {
                    $success = 'Profile updated successfully!';
                    $_SESSION['user_name'] = $first_name . ' ' . $last_name;
                    $edit_mode = true;
                    $_SESSION['edit_mode'] = true;

                    // Handle role shifting (Student → Alumni)
                    if ($shift_role === 'Alumni' && $user_type === 'Student') {
                        try {
                            $batch_stmt = executeQuery("SELECT batch_year FROM student WHERE person_id = ?", [$user_id]);
                            $batch_year = ($batch_stmt && $batch_stmt->rowCount() > 0) ? $batch_stmt->fetchColumn() : null;

                            $input_grad_year = $_POST['grad_year'] ?? '';
                            if (!preg_match('/^\d{4}$/', $input_grad_year) || $input_grad_year > date('Y') || $input_grad_year < 1950) {
                                $error = 'Please enter a valid graduation year.';
                            } else {
                                $grad_year = $input_grad_year;

                                $insert_alumni = executeQuery(
                                    "INSERT INTO alumni (person_id, grad_year) VALUES (?, ?)",
                                    [$user_id, $grad_year]
                                );

                                if ($insert_alumni) {
                                    executeQuery("DELETE FROM student WHERE person_id = ?", [$user_id]);
                                    $user_type = 'Alumni';
                                    $year_info = $grad_year;
                                    $success  .= ' Your role has been shifted to Alumni.';
                                } else {
                                    $error .= ' Profile updated, but failed to shift role.';
                                }
                            }
                        } catch (Exception $e) {
                            $error .= ' Error while shifting role: ' . $e->getMessage();
                        }
                    }
                } else {
                    $error = 'Failed to update profile. Please try again.';
                }
            } catch (Exception $e) {
                $error = 'Error updating profile: ' . $e->getMessage();
            }
            break;

        case 'add_education':
            // Add education record
            $degree      = trim($_POST['degree'] ?? '');
            $institution = trim($_POST['institution'] ?? '');
            $start_date  = $_POST['edu_start_date'] ?? '';
            $end_date    = $_POST['edu_end_date'] ?: null;

            if ($degree !== '' && $institution !== '' && $start_date !== '') {
                try {
                    if (!empty($end_date) && strtotime($end_date) < strtotime($start_date)) {
                        $error = 'End date cannot be before start date.';
                    } else {
                        $edu_query = "INSERT INTO education_history (person_id, degree, institution, start_date, end_date)
                                      VALUES (?, ?, ?, ?, ?)";
                        $edu_stmt = executeQuery($edu_query, [$user_id, $degree, $institution, $start_date, $end_date]);

                        if ($edu_stmt) {
                            $success = 'Education record added successfully!';
                            $edit_mode = true;
                            $_SESSION['edit_mode'] = true;
                        } else {
                            $error = 'Failed to add education record.';
                        }
                    }
                } catch (Exception $e) {
                    $error = 'Error adding education: ' . $e->getMessage();
                }
            } else {
                $error = 'Please fill in all required education fields.';
            }
            break;

        case 'add_skill':
            // person_skill stores ONE row per person with a CSV of skills in `skill`
            $skill_name = trim($_POST['skill_name'] ?? '');
            if ($skill_name === '') {
                $error = 'Please provide a skill name.';
                break;
            }

            try {
                $row = null;
                $sel = executeQuery("SELECT skill FROM person_skill WHERE person_id = ?", [$user_id]);
                if ($sel && $sel->rowCount() > 0) {
                    $row = $sel->fetch(PDO::FETCH_ASSOC);
                }

                $skills_arr = [];
                if ($row && !empty($row['skill'])) {
                    $skills_arr = array_filter(array_map('trim', explode(',', $row['skill'])));
                }

                $lower = array_map('mb_strtolower', $skills_arr);
                if (in_array(mb_strtolower($skill_name), $lower, true)) {
                    $error = 'You already have this skill.';
                    break;
                }

                $skills_arr[] = $skill_name;
                $csv = implode(', ', $skills_arr);

                if ($row) {
                    $ok = executeQuery("UPDATE person_skill SET skill = ? WHERE person_id = ?", [$csv, $user_id]);
                } else {
                    $ok = executeQuery("INSERT INTO person_skill (person_id, skill) VALUES (?, ?)", [$user_id, $csv]);
                }

                if ($ok) {
                    $success = 'Skill added successfully!';
                    $edit_mode = true;
                    $_SESSION['edit_mode'] = true;
                } else {
                    $error = 'Failed to add skill.';
                }
            } catch (Exception $e) {
                $error = 'Error adding skill: ' . $e->getMessage();
            }
            break;
        case 'delete_education':
            // Keys: person_id, degree, institution, start_date
            $degree      = $_POST['degree']      ?? '';
            $institution = $_POST['institution'] ?? '';
            $start_date  = $_POST['start_date']  ?? ''; // keep DB format 'Y-m-d'

            if ($degree === '' || $institution === '' || $start_date === '') {
                $error = 'Invalid education item.';
                break;
            }

            try {
                $ok = executeQuery(
                    "DELETE FROM education_history
             WHERE person_id = ? AND degree = ? AND institution = ? AND start_date = ?",
                    [$user_id, $degree, $institution, $start_date]
                );
                if ($ok) {
                    $success = 'Education record deleted.';
                    $edit_mode = true;
                    $_SESSION['edit_mode'] = true;
                    // Refresh list (optional, your page reload already does this)
                } else {
                    $error = 'Failed to delete education record.';
                }
            } catch (Exception $e) {
                $error = 'Error deleting education: ' . $e->getMessage();
            }
            break;

        case 'delete_employment':
            // Keys: person_id, job_title, company, start_date
            $job_title  = $_POST['job_title']  ?? '';
            $company    = $_POST['company']    ?? '';
            $start_date = $_POST['start_date'] ?? '';

            if ($job_title === '' || $company === '' || $start_date === '') {
                $error = 'Invalid employment item.';
                break;
            }

            try {
                $ok = executeQuery(
                    "DELETE FROM employment_history
             WHERE person_id = ? AND job_title = ? AND company = ? AND start_date = ?",
                    [$user_id, $job_title, $company, $start_date]
                );
                if ($ok) {
                    $success = 'Employment record deleted.';
                    $edit_mode = true;
                    $_SESSION['edit_mode'] = true;
                } else {
                    $error = 'Failed to delete employment record.';
                }
            } catch (Exception $e) {
                $error = 'Error deleting employment: ' . $e->getMessage();
            }
            break;

        case 'delete_achievement':
            // Keys: person_id, ach_title, ach_date
            $ach_title = $_POST['ach_title'] ?? '';
            $ach_date  = $_POST['ach_date']  ?? '';

            if ($ach_title === '' || $ach_date === '') {
                $error = 'Invalid achievement item.';
                break;
            }

            try {
                $ok = executeQuery(
                    "DELETE FROM achievement
             WHERE person_id = ? AND ach_title = ? AND ach_date = ?",
                    [$user_id, $ach_title, $ach_date]
                );
                if ($ok) {
                    $success = 'Achievement deleted.';
                    $edit_mode = true;
                    $_SESSION['edit_mode'] = true;
                } else {
                    $error = 'Failed to delete achievement.';
                }
            } catch (Exception $e) {
                $error = 'Error deleting achievement: ' . $e->getMessage();
            }
            break;

        case 'delete_skill':
            // Remove one item from the CSV in person_skill
            $skill_name = trim($_POST['skill_name'] ?? '');
            if ($skill_name === '') {
                $error = 'Invalid skill.';
                break;
            }

            try {
                $sel = executeQuery("SELECT skill FROM person_skill WHERE person_id = ?", [$user_id]);
                if ($sel && $sel->rowCount() > 0) {
                    $row = $sel->fetch(PDO::FETCH_ASSOC);
                    $skills_arr = array_filter(array_map('trim', explode(',', $row['skill'])));
                    $skills_arr = array_values(array_filter($skills_arr, function ($s) use ($skill_name) {
                        return mb_strtolower($s) !== mb_strtolower($skill_name);
                    }));
                    $csv = implode(', ', $skills_arr);

                    if ($csv === '') {
                        $ok = executeQuery("DELETE FROM person_skill WHERE person_id = ?", [$user_id]);
                    } else {
                        $ok = executeQuery("UPDATE person_skill SET skill = ? WHERE person_id = ?", [$csv, $user_id]);
                    }

                    if ($ok) {
                        $success = 'Skill removed.';
                        $edit_mode = true;
                        $_SESSION['edit_mode'] = true;
                    } else {
                        $error = 'Failed to remove skill.';
                    }
                } else {
                    $error = 'No skills found for this profile.';
                }
            } catch (Exception $e) {
                $error = 'Error removing skill: ' . $e->getMessage();
            }
            break;

        case 'add_interest':
            // Insert into alumni_interest or student_interest based on $user_type
            $interest_name = trim($_POST['interest_name'] ?? '');
            if ($interest_name === '') {
                $error = 'Please provide an interest name.';
                break;
            }
            if (!in_array($user_type, ['Student', 'Alumni'], true)) {
                $error = 'Unknown user type for adding interests.';
                break;
            }

            try {
                $int_stmt = executeQuery("SELECT interest_id FROM interest WHERE interest_name = ?", [$interest_name]);
                if ($int_stmt && ($row = $int_stmt->fetch(PDO::FETCH_ASSOC))) {
                    $interest_id = (int)$row['interest_id'];
                } else {
                    $ins_int = executeQuery("INSERT INTO interest (interest_name) VALUES (?)", [$interest_name]);
                    if (!$ins_int) {
                        throw new Exception('Failed to create interest.');
                    }
                    $re_sel = executeQuery("SELECT interest_id FROM interest WHERE interest_name = ?", [$interest_name]);
                    $interest_id = (int)$re_sel->fetchColumn();
                }

                if ($user_type === 'Alumni') {
                    $chk = executeQuery("SELECT 1 FROM alumni_interest WHERE person_id = ? AND interest_id = ?", [$user_id, $interest_id]);
                    if ($chk && $chk->rowCount() > 0) {
                        $error = 'You already added this interest.';
                    } else {
                        $ok = executeQuery("INSERT INTO alumni_interest (person_id, interest_id) VALUES (?, ?)", [$user_id, $interest_id]);
                        if ($ok) {
                            $success = 'Interest added successfully!';
                            $edit_mode = true;
                            $_SESSION['edit_mode'] = true;
                        } else {
                            $error = 'Failed to add interest.';
                        }
                    }
                } else { // Student
                    $chk = executeQuery("SELECT 1 FROM student_interest WHERE person_id = ? AND interest_id = ?", [$user_id, $interest_id]);
                    if ($chk && $chk->rowCount() > 0) {
                        $error = 'You already added this interest.';
                    } else {
                        $ok = executeQuery("INSERT INTO student_interest (person_id, interest_id) VALUES (?, ?)", [$user_id, $interest_id]);
                        if ($ok) {
                            $success = 'Interest added successfully!';
                            $edit_mode = true;
                            $_SESSION['edit_mode'] = true;
                        } else {
                            $error = 'Failed to add interest.';
                        }
                    }
                }
            } catch (Exception $e) {
                $error = 'Error adding interest: ' . $e->getMessage();
            }
            break;

        case 'delete_interest':
            // Delete from both interest link tables (safe regardless of current role)
            $interest_id = (int)($_POST['interest_id'] ?? 0);
            if ($interest_id <= 0) {
                $error = 'Invalid interest.';
                break;
            }
            try {
                executeQuery("DELETE FROM alumni_interest  WHERE person_id = ? AND interest_id = ?", [$user_id, $interest_id]);
                executeQuery("DELETE FROM student_interest WHERE person_id = ? AND interest_id = ?", [$user_id, $interest_id]);
                $success = 'Interest removed.';
                $edit_mode = true;
                $_SESSION['edit_mode'] = true;
            } catch (Exception $e) {
                $error = 'Error removing interest: ' . $e->getMessage();
            }
            break;

        case 'add_employment':
            // Add employment record
            $job_title     = trim($_POST['job_title'] ?? '');
            $company       = trim($_POST['company'] ?? '');
            $designation   = trim($_POST['designation'] ?? '');
            $emp_start_date = $_POST['emp_start_date'] ?? '';
            $emp_end_date  = $_POST['emp_end_date'] ?: null;

            if ($job_title !== '' && $company !== '' && $emp_start_date !== '') {
                try {
                    $emp_query = "INSERT INTO employment_history (person_id, job_title, company, designation, start_date, end_date)
                                  VALUES (?, ?, ?, ?, ?, ?)";
                    $emp_stmt = executeQuery($emp_query, [$user_id, $job_title, $company, $designation, $emp_start_date, $emp_end_date]);

                    if ($emp_stmt) {
                        $success = 'Employment record added successfully!';
                        $edit_mode = true;
                        $_SESSION['edit_mode'] = true;
                    } else {
                        $error = 'Failed to add employment record.';
                    }
                } catch (Exception $e) {
                    $error = 'Error adding employment: ' . $e->getMessage();
                }
            } else {
                $error = 'Please fill in all required employment fields.';
            }
            break;

        case 'add_achievement':
            // Add achievement record
            $ach_title    = trim($_POST['ach_title'] ?? '');
            $ach_date     = $_POST['ach_date'] ?? '';
            $organization = trim($_POST['organization'] ?? '');
            $description  = trim($_POST['description'] ?? '');
            $type         = $_POST['type'] ?? '';

            if ($ach_title !== '' && $ach_date !== '') {
                try {
                    $ach_query = "INSERT INTO achievement (person_id, ach_title, ach_date, organization, description, type)
                                  VALUES (?, ?, ?, ?, ?, ?)";
                    $ach_stmt = executeQuery($ach_query, [$user_id, $ach_title, $ach_date, $organization, $description, $type]);

                    if ($ach_stmt) {
                        $success = 'Achievement added successfully!';
                        $edit_mode = true;
                        $_SESSION['edit_mode'] = true;
                    } else {
                        $error = 'Failed to add achievement.';
                    }
                } catch (Exception $e) {
                    $error = 'Error adding achievement: ' . $e->getMessage();
                }
            } else {
                $error = 'Please fill in all required achievement fields.';
            }
            break;

        case 'add_email':
            // Add secondary email
            $new_email = trim($_POST['new_email'] ?? '');
            if ($new_email === '' || !filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please provide a valid email address.';
                break;
            }

            try {
                $check_query = "SELECT email FROM email_address WHERE email = ?";
                $check_stmt  = executeQuery($check_query, [$new_email]);
                if ($check_stmt && $check_stmt->rowCount() > 0) {
                    $error = 'This email is already in use.';
                } else {
                    $email_query = "INSERT INTO email_address (person_id, email) VALUES (?, ?)";
                    $email_stmt  = executeQuery($email_query, [$user_id, $new_email]);
                    if ($email_stmt) {
                        $success = 'Email added successfully!';
                        $edit_mode = true;
                        $_SESSION['edit_mode'] = true;
                    } else {
                        $error = 'Failed to add email.';
                    }
                }
            } catch (Exception $e) {
                $error = 'Error adding email: ' . $e->getMessage();
            }
            break;

        case 'add_phone':
            // Add secondary phone
            $new_phone = trim($_POST['new_phone'] ?? '');
            if ($new_phone === '' || !preg_match('/^[0-9+\-\s]{10,15}$/', $new_phone)) {
                $error = 'Please provide a valid phone number (10-15 digits, +, -, or spaces).';
                break;
            }

            try {
                $check_query = "SELECT phone_number FROM person_phone WHERE phone_number = ?";
                $check_stmt  = executeQuery($check_query, [$new_phone]);
                if ($check_stmt && $check_stmt->rowCount() > 0) {
                    $error = 'This phone number is already in use.';
                } else {
                    $phone_query = "INSERT INTO person_phone (person_id, phone_number) VALUES (?, ?)";
                    $phone_stmt  = executeQuery($phone_query, [$user_id, $new_phone]);
                    if ($phone_stmt) {
                        $success = 'Phone number added successfully!';
                        $edit_mode = true;
                        $_SESSION['edit_mode'] = true;
                    } else {
                        $error = 'Failed to add phone number.';
                    }
                }
            } catch (Exception $e) {
                $error = 'Error adding phone: ' . $e->getMessage();
            }
            break;

        case 'change_password':
            $current_password = trim($_POST['current_password'] ?? '');
            $new_password     = trim($_POST['new_password'] ?? '');
            $confirm_password = trim($_POST['confirm_password'] ?? '');

            try {
                if ($current_password === '' || $new_password === '' || $confirm_password === '') {
                    $error = 'All fields are required.';
                    break;
                }
                if ($new_password !== $confirm_password) {
                    $error = 'New password and confirmation do not match.';
                    break;
                }
                if (
                    strlen($new_password) < 8
                ) {
                    $error = 'Password must be at least 8 characters.';
                    break;
                }

                $stmt = executeQuery("SELECT password FROM person WHERE person_id = ?", [$user_id]);
                $row  = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;

                if (!$row || !password_verify($current_password, $row['password'])) {
                    $error = 'Your current password is incorrect.';
                    break;
                }
                if (password_verify($new_password, $row['password'])) {
                    $error = 'New password cannot be the same as your current password.';
                    break;
                }

                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $ok = executeQuery("UPDATE person SET password = ? WHERE person_id = ?", [$new_hash, $user_id]);

                if ($ok) {
                    // Force logout and redirect to sign-in:
                    if (session_status() === PHP_SESSION_ACTIVE) {
                        $_SESSION = [];
                        if (ini_get('session.use_cookies')) {
                            $p = session_get_cookie_params();
                            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
                        }
                        session_destroy();
                    }
                    header('Location: ../auth/signin.php?pwd_changed=1');
                    exit;
                } else {
                    $error = 'Failed to update password. Please try again.';
                }
            } catch (Exception $e) {
                $error = 'Error changing password: ' . $e->getMessage();
            }
            break;

        default:
            // Unknown or missing action
            break;
    }
}

// Get all user emails
$emails = [];
if ($user_info) {
    $email_query = "SELECT email FROM email_address WHERE person_id = ?";
    $email_stmt = executeQuery($email_query, [$user_id]);
    if ($email_stmt) {
        $emails = $email_stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}

// Get all user phone numbers
$phones = [];
if ($user_info) {
    $phone_query = "SELECT phone_number FROM person_phone WHERE person_id = ?";
    $phone_stmt = executeQuery($phone_query, [$user_id]);
    if ($phone_stmt) {
        $phones = $phone_stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}

// Get education history
$education_history = [];
if ($user_info) {
    $education_query = "SELECT * FROM education_history WHERE person_id = ? ORDER BY start_date DESC";
    $education_stmt = executeQuery($education_query, [$user_id]);
    if ($education_stmt) {
        $education_history = $education_stmt->fetchAll();
    }
}

// Get employment history
$employment_history = [];
if ($user_info) {
    $employment_query = "SELECT * FROM employment_history WHERE person_id = ? ORDER BY start_date DESC";
    $employment_stmt = executeQuery($employment_query, [$user_id]);
    if ($employment_stmt) {
        $employment_history = $employment_stmt->fetchAll();
    }
}

// Get achievements
$achievements = [];
if ($user_info) {
    $achievement_query = "SELECT * FROM achievement WHERE person_id = ? ORDER BY ach_date DESC";
    $achievement_stmt = executeQuery($achievement_query, [$user_id]);
    if ($achievement_stmt) {
        $achievements = $achievement_stmt->fetchAll();
    }
}
// Get skills (CSV from person_skill)
$skills = [];
if ($user_info) {
    $stmt = executeQuery("SELECT skill FROM person_skill WHERE person_id = ?", [$user_id]);
    if ($stmt && $stmt->rowCount() > 0) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!empty($row['skill'])) {
            $skills = array_values(array_filter(array_map('trim', explode(',', $row['skill']))));
        }
    }
}

// Get interests (union of student/alumni link tables)
$interests = [];
if ($user_info) {
    $interests_stmt = executeQuery("
        SELECT i.interest_id, i.interest_name
        FROM interest i
        JOIN alumni_interest ai ON ai.interest_id = i.interest_id
        WHERE ai.person_id = ?
        UNION
        SELECT i2.interest_id, i2.interest_name
        FROM interest i2
        JOIN student_interest si ON si.interest_id = i2.interest_id
        WHERE si.person_id = ?
        ORDER BY interest_name ASC
    ", [$user_id, $user_id]);
    if ($interests_stmt) {
        $interests = $interests_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Alumni Relationship & Networking System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Open Sans', sans-serif;
            background-color: #faf5f6;
            color: #002147;
        }

        .profile-header {
            background: linear-gradient(to right, #002147, #0077c8);
            color: white;
            padding: 2rem 0;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid white;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: #002147;
            margin: 0 auto 1rem;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
        }

        .card-header {
            background: linear-gradient(to right, #002147, #0077c8);
            color: #fff !important;
            border-radius: 15px 15px 0 0 !important;
            padding: 1rem 1.5rem;
        }

        .btn-edit {
            background: #002147;
            border: none;
            color: white;
            border-radius: 25px;
            padding: 0.5rem 1.5rem;
            transition: all 0.3s;
        }

        .btn-edit:hover {
            background: #002147;
            color: white;
        }

        .readonly-field {
            background-color: #e9ecef;
            cursor: not-allowed;
        }

        .edit-section {
            border: 2px dashed #002147;
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1rem;
            background-color: #f8f9ff;
        }

        .contact-list {
            list-style: none;
            padding: 0;
        }

        .contact-list li {
            margin-bottom: 0.5rem;
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

        .bg-navy {
            background-color: #002147;
        }
    </style>
</head>

<body>

    <?php include '../includes/navbar.php'; ?>

    <!-- Profile Header -->
    <div class="profile-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-3 text-center">
                    <div class="profile-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                </div>
                <div class="col-md-6">
                    <h2><?php echo htmlspecialchars($user_info['first_name'] . ' ' . $user_info['last_name']); ?></h2>
                    <p class="mb-1"><i class="fas fa-graduation-cap me-2"></i><?php echo htmlspecialchars($user_type . ' - ' . $year_info); ?></p>
                    <p class="mb-1"><i class="fas fa-building me-2"></i><?php echo htmlspecialchars($user_info['department']); ?></p>
                    <p class="mb-0"><i class="fas fa-map-marker-alt me-2"></i><?php echo htmlspecialchars($user_info['city'] ?? ''); ?></p>
                </div>
                <div class="col-md-3 text-end">
                    <?php if (!$edit_mode): ?>
                        <button class="btn btn-edit" data-bs-toggle="modal" data-bs-target="#verifyModal">
                            <i class="fas fa-edit me-2"></i>Edit Profile
                        </button>
                    <?php else: ?>
                        <span class="badge bg-success fs-6">
                            <i class="fas fa-check me-1"></i>Edit Mode Active
                        </span>
                        <a href="profile.php?cancel_edit=1" class="btn btn-outline-danger mt-2">
                            <i class="fas fa-times-circle me-1"></i>Save Changes
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <!-- /container -->
    <div class="container mt-4">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- LEFT: main content -->
            <div class="col-lg-8">
                <!-- Personal Information -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-user me-2"></i>Personal Information</h5>
                        <?php if ($edit_mode): ?>
                            <div>
                                <button class="btn btn-sm btn-light me-2" data-bs-toggle="modal" data-bs-target="#addEmailModal">
                                    <i class="fas fa-plus me-1"></i>Add Email
                                </button>
                                <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#addPhoneModal">
                                    <i class="fas fa-plus me-1"></i>Add Phone
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if ($edit_mode): ?>
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="update_profile">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">First Name *</label>
                                        <input type="text" class="form-control" name="first_name"
                                            value="<?php echo htmlspecialchars($user_info['first_name'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Last Name *</label>
                                        <input type="text" class="form-control" name="last_name"
                                            value="<?php echo htmlspecialchars($user_info['last_name'] ?? ''); ?>" required>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Gender</label>
                                        <select class="form-select" name="gender" required>
                                            <option value="Male" <?php echo ($user_info['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                                            <option value="Female" <?php echo ($user_info['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                                            <option value="Other" <?php echo ($user_info['gender'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Date of Birth</label>
                                        <input type="date" class="form-control" name="date_of_birth"
                                            value="<?php echo htmlspecialchars($user_info['date_of_birth'] ?? ''); ?>" required>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Street</label>
                                        <input type="text" class="form-control" name="street"
                                            value="<?php echo htmlspecialchars($user_info['street'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">City</label>
                                        <select class="form-select" id="city" name="city" required>
                                            <option value="">Select City</option>
                                            <option value="Dhaka" <?php echo ($user_info['city'] ?? '') === 'Dhaka' ? 'selected' : ''; ?>>Dhaka</option>
                                            <option value="Chattogram" <?php echo ($user_info['city'] ?? '') === 'Chattogram' ? 'selected' : ''; ?>>Chattogram</option>
                                            <option value="Khulna" <?php echo ($user_info['city'] ?? '') === 'Khulna' ? 'selected' : ''; ?>>Khulna</option>
                                            <option value="Rajshahi" <?php echo ($user_info['city'] ?? '') === 'Rajshahi' ? 'selected' : ''; ?>>Rajshahi</option>
                                            <option value="Sylhet" <?php echo ($user_info['city'] ?? '') === 'Sylhet' ? 'selected' : ''; ?>>Sylhet</option>
                                            <option value="Barishal" <?php echo ($user_info['city'] ?? '') === 'Barishal' ? 'selected' : ''; ?>>Barishal</option>
                                            <option value="Rangpur" <?php echo ($user_info['city'] ?? '') === 'Rangpur' ? 'selected' : ''; ?>>Rangpur</option>
                                            <option value="Mymensingh" <?php echo ($user_info['city'] ?? '') === 'Mymensingh' ? 'selected' : ''; ?>>Mymensingh</option>
                                            <option value="Jessore" <?php echo ($user_info['city'] ?? '') === 'Jessore' ? 'selected' : ''; ?>>Jessore</option>
                                            <option value="Bogura" <?php echo ($user_info['city'] ?? '') === 'Bogura' ? 'selected' : ''; ?>>Bogura</option>
                                        </select>

                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Zip Code</label>
                                        <input type="text" class="form-control" name="zip"
                                            value="<?php echo htmlspecialchars($user_info['zip'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Department</label>
                                        <select class="form-select" name="department" required>
                                            <option value="">Select Department</option>
                                            <option value="Bachelor of Architecture" <?php echo ($user_info['department'] ?? '') === 'Bachelor of Architecture' ? 'selected' : ''; ?>>Bachelor of Architecture</option>
                                            <option value="BS in Civil & Environmental Engineering (CEE)" <?php echo ($user_info['department'] ?? '') === 'BS in Civil & Environmental Engineering (CEE)' ? 'selected' : ''; ?>>BS in Civil & Environmental Engineering (CEE)</option>
                                            <option value="BS in Computer Science & Engineering (CSE)" <?php echo ($user_info['department'] ?? '') === 'BS in Computer Science & Engineering (CSE)' ? 'selected' : ''; ?>>BS in Computer Science & Engineering (CSE)</option>
                                            <option value="BS in Electrical & Electronic Engineering (EEE)" <?php echo ($user_info['department'] ?? '') === 'BS in Electrical & Electronic Engineering (EEE)' ? 'selected' : ''; ?>>BS in Electrical & Electronic Engineering (EEE)</option>
                                            <option value="BS in Electronic & Telecom Engineering (ETE)" <?php echo ($user_info['department'] ?? '') === 'BS in Electronic & Telecom Engineering (ETE)' ? 'selected' : ''; ?>>BS in Electronic & Telecom Engineering (ETE)</option>
                                            <option value="BS in Biochemistry and Biotechnology" <?php echo ($user_info['department'] ?? '') === 'BS in Biochemistry and Biotechnology' ? 'selected' : ''; ?>>BS in Biochemistry and Biotechnology</option>
                                            <option value="BS in Environmental Science & Management" <?php echo ($user_info['department'] ?? '') === 'BS in Environmental Science & Management' ? 'selected' : ''; ?>>BS in Environmental Science & Management</option>
                                            <option value="BS in Microbiology" <?php echo ($user_info['department'] ?? '') === 'BS in Microbiology' ? 'selected' : ''; ?>>BS in Microbiology</option>
                                            <option value="BPharm Professional" <?php echo ($user_info['department'] ?? '') === 'BPharm Professional' ? 'selected' : ''; ?>>BPharm Professional</option>
                                            <option value="BBA Major in Accounting" <?php echo ($user_info['department'] ?? '') === 'BBA Major in Accounting' ? 'selected' : ''; ?>>BBA Major in Accounting</option>
                                            <option value="BBA Major in Economics" <?php echo ($user_info['department'] ?? '') === 'BBA Major in Economics' ? 'selected' : ''; ?>>BBA Major in Economics</option>
                                            <option value="BBA Major in Entrepreneurship" <?php echo ($user_info['department'] ?? '') === 'BBA Major in Entrepreneurship' ? 'selected' : ''; ?>>BBA Major in Entrepreneurship</option>
                                            <option value="BBA Major in Finance" <?php echo ($user_info['department'] ?? '') === 'BBA Major in Finance' ? 'selected' : ''; ?>>BBA Major in Finance</option>
                                            <option value="BBA Major in Human Resource Management" <?php echo ($user_info['department'] ?? '') === 'BBA Major in Human Resource Management' ? 'selected' : ''; ?>>BBA Major in Human Resource Management</option>
                                            <option value="BBA Major in International Business" <?php echo ($user_info['department'] ?? '') === 'BBA Major in International Business' ? 'selected' : ''; ?>>BBA Major in International Business</option>
                                            <option value="BBA Major in Management" <?php echo ($user_info['department'] ?? '') === 'BBA Major in Management' ? 'selected' : ''; ?>>BBA Major in Management</option>
                                            <option value="BBA Major in Management Information Systems" <?php echo ($user_info['department'] ?? '') === 'BBA Major in Management Information Systems' ? 'selected' : ''; ?>>BBA Major in Management Information Systems</option>
                                            <option value="BBA Major in Marketing" <?php echo ($user_info['department'] ?? '') === 'BBA Major in Marketing' ? 'selected' : ''; ?>>BBA Major in Marketing</option>
                                            <option value="BBA Major in Supply Chain Management" <?php echo ($user_info['department'] ?? '') === 'BBA Major in Supply Chain Management' ? 'selected' : ''; ?>>BBA Major in Supply Chain Management</option>
                                            <option value="BBA General" <?php echo ($user_info['department'] ?? '') === 'BBA General' ? 'selected' : ''; ?>>BBA General</option>
                                            <option value="BS in Economics" <?php echo ($user_info['department'] ?? '') === 'BS in Economics' ? 'selected' : ''; ?>>BS in Economics</option>
                                            <option value="BA in English" <?php echo ($user_info['department'] ?? '') === 'BA in English' ? 'selected' : ''; ?>>BA in English</option>
                                            <option value="Bachelor of Laws (LLB Hons)" <?php echo ($user_info['department'] ?? '') === 'Bachelor of Laws (LLB Hons)' ? 'selected' : ''; ?>>Bachelor of Laws (LLB Hons)</option>
                                            <option value="BSS in Media and Journalism (MAJ)" <?php echo ($user_info['department'] ?? '') === 'BSS in Media and Journalism (MAJ)' ? 'selected' : ''; ?>>BSS in Media and Journalism (MAJ)</option>
                                        </select>
                                    </div>
                                </div>
                                <?php if ($user_type === 'Student'): ?>
                                    <div class="edit-section">
                                        <h6>Shift Role to Alumni</h6>
                                        <div class="mb-3">
                                            <label class="form-label">Role</label>
                                            <select class="form-select" name="shift_role">
                                                <option value="">Stay as Student</option>
                                                <option value="Alumni">Shift to Alumni</option>
                                            </select>
                                        </div>
                                        <div class="mb-3 grad-year-field" style="display:none;">
                                            <label class="form-label">Graduation Year</label>
                                            <input type="text" class="form-control" name="grad_year" placeholder="e.g., 2023">
                                        </div>
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-save me-1"></i> Save Role Change
                                        </button>
                                    </div>
                                <?php endif; ?>
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-times-circle me-1"></i>Save Change
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Student ID</label>
                                    <input type="text" class="form-control readonly-field"
                                        value="<?php echo htmlspecialchars($user_info['student_id'] ?? 'N/A'); ?>" readonly>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Gender</label>
                                    <input type="text" class="form-control readonly-field"
                                        value="<?php echo htmlspecialchars($user_info['gender'] ?? 'N/A'); ?>" readonly>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Date of Birth</label>
                                    <input type="text" class="form-control readonly-field"
                                        value="<?php echo htmlspecialchars($user_info['date_of_birth'] ?? 'N/A'); ?>" readonly>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Department</label>
                                    <input type="text" class="form-control readonly-field"
                                        value="<?php echo htmlspecialchars($user_info['department'] ?? 'N/A'); ?>" readonly>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Street</label>
                                    <input type="text" class="form-control readonly-field"
                                        value="<?php echo htmlspecialchars($user_info['street'] ?? 'N/A'); ?>" readonly>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">City</label>
                                    <input type="text" class="form-control readonly-field"
                                        value="<?php echo htmlspecialchars($user_info['city'] ?? 'N/A'); ?>" readonly>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Zip Code</label>
                                    <input type="text" class="form-control readonly-field"
                                        value="<?php echo htmlspecialchars($user_info['zip'] ?? 'N/A'); ?>" readonly>
                                </div>
                            </div>
                            <h6>Contact Information</h6>
                            <ul class="contact-list">
                                <?php foreach ($emails as $email): ?>
                                    <li><i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($email); ?></li>
                                <?php endforeach; ?>
                                <?php foreach ($phones as $phone): ?>
                                    <li><i class="fas fa-phone me-2"></i><?php echo htmlspecialchars($phone); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Skills -->
                <div class="card mt-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-lightbulb me-2"></i>Skills</h5>
                        <?php if ($edit_mode): ?>
                            <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#addSkillModal">
                                <i class="fas fa-plus me-1"></i>Add Skill
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (empty($skills)): ?>
                            <p class="text-muted text-center py-3">No skills added yet.</p>
                        <?php else: ?>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($skills as $sk): ?>
                                    <div class="d-flex align-items-center">
                                        <span class="badge bg-navy me-2"><?php echo htmlspecialchars($sk); ?></span>
                                        <?php if ($edit_mode): ?>
                                            <form method="POST" action="">
                                                <input type="hidden" name="action" value="delete_skill">
                                                <input type="hidden" name="skill_name" value="<?php echo htmlspecialchars($sk); ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Interests -->
                <div class="card mt-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-heart me-2"></i>Interests</h5>
                        <?php if ($edit_mode): ?>
                            <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#addInterestModal">
                                <i class="fas fa-plus me-1"></i>Add Interest
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (empty($interests)): ?>
                            <p class="text-muted text-center py-3">No interests added yet.</p>
                        <?php else: ?>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($interests as $in): ?>
                                    <div class="d-flex align-items-center">
                                        <span class="badge bg-navy me-2"><?php echo htmlspecialchars($in['interest_name']); ?></span>
                                        <?php if ($edit_mode): ?>
                                            <form method="POST" action="">
                                                <input type="hidden" name="action" value="delete_interest">
                                                <input type="hidden" name="interest_id" value="<?php echo (int)$in['interest_id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Education History -->
                <div class="card mt-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-graduation-cap me-2"></i>Education History</h5>
                        <?php if ($edit_mode): ?>
                            <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#addEducationModal">
                                <i class="fas fa-plus me-1"></i>Add Education
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (empty($education_history)): ?>
                            <p class="text-muted text-center py-3">No education history added yet.</p>
                        <?php else: ?>
                            <?php foreach ($education_history as $edu): ?>
                                <div class="border-start border-primary border-3 ps-3 mb-3 d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($edu['degree']); ?></h6>
                                        <p class="text-primary mb-1"><?php echo htmlspecialchars($edu['institution']); ?></p>
                                        <small class="text-muted">
                                            <?php echo date('M Y', strtotime($edu['start_date'])); ?> -
                                            <?php echo $edu['end_date'] ? date('M Y', strtotime($edu['end_date'])) : 'Present'; ?>
                                        </small>
                                    </div>
                                    <?php if ($edit_mode): ?>
                                        <form method="POST" action="" class="ms-3">
                                            <input type="hidden" name="action" value="delete_education">
                                            <input type="hidden" name="degree" value="<?php echo htmlspecialchars($edu['degree']); ?>">
                                            <input type="hidden" name="institution" value="<?php echo htmlspecialchars($edu['institution']); ?>">
                                            <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($edu['start_date']); ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete education"
                                                onclick="return confirm('Delete this education record?');">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Employment History -->
                <div class="card mt-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-briefcase me-2"></i>Employment History</h5>
                        <?php if ($edit_mode): ?>
                            <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#addEmploymentModal">
                                <i class="fas fa-plus me-1"></i>Add Employment
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (empty($employment_history)): ?>
                            <p class="text-muted text-center py-3">No employment history added yet.</p>
                        <?php else: ?>
                            <?php foreach ($employment_history as $emp): ?>
                                <div class="border-start border-primary border-3 ps-3 mb-3 d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($emp['job_title']); ?></h6>
                                        <p class="text-primary mb-1"><?php echo htmlspecialchars($emp['company']); ?></p>
                                        <?php if (!empty($emp['designation'])): ?>
                                            <small class="d-block"><?php echo htmlspecialchars($emp['designation']); ?></small>
                                        <?php endif; ?>
                                        <small class="text-muted">
                                            <?php echo date('M Y', strtotime($emp['start_date'])); ?> -
                                            <?php echo $emp['end_date'] ? date('M Y', strtotime($emp['end_date'])) : 'Present'; ?>
                                        </small>
                                    </div>
                                    <?php if ($edit_mode): ?>
                                        <form method="POST" action="" class="ms-3">
                                            <input type="hidden" name="action" value="delete_employment">
                                            <input type="hidden" name="job_title" value="<?php echo htmlspecialchars($emp['job_title']); ?>">
                                            <input type="hidden" name="company" value="<?php echo htmlspecialchars($emp['company']); ?>">
                                            <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($emp['start_date']); ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete employment"
                                                onclick="return confirm('Delete this employment record?');">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Achievements -->
                <div class="card mt-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-trophy me-2"></i>Achievements</h5>
                        <?php if ($edit_mode): ?>
                            <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#addAchievementModal">
                                <i class="fas fa-plus me-1"></i>Add Achievement
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (empty($achievements)): ?>
                            <p class="text-muted text-center py-3">No achievements added yet.</p>
                        <?php else: ?>
                            <?php foreach ($achievements as $ach): ?>
                                <div class="border-start border-primary border-3 ps-3 mb-3 d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($ach['ach_title']); ?></h6>
                                        <p class="text-primary mb-1"><?php echo htmlspecialchars($ach['organization'] ?? ''); ?></p>
                                        <small class="text-muted">
                                            <?php echo date('M d, Y', strtotime($ach['ach_date'])); ?>
                                            <?php if (!empty($ach['type'])): ?> • <?php echo htmlspecialchars($ach['type']); ?><?php endif; ?>
                                        </small>
                                        <?php if (!empty($ach['description'])): ?>
                                            <div class="mt-1"><?php echo nl2br(htmlspecialchars($ach['description'])); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($edit_mode): ?>
                                        <form method="POST" action="" class="ms-3">
                                            <input type="hidden" name="action" value="delete_achievement">
                                            <input type="hidden" name="ach_title" value="<?php echo htmlspecialchars($ach['ach_title']); ?>">
                                            <input type="hidden" name="ach_date" value="<?php echo htmlspecialchars($ach['ach_date']); ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete achievement"
                                                onclick="return confirm('Delete this achievement?');">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div><!-- /col-lg-8 -->

            <!-- RIGHT: sidebar -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Profile Stats</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Education Records:</span>
                            <span class="badge bg-primary"><?php echo count($education_history); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Employment History:</span>
                            <span class="badge bg-success"><?php echo count($employment_history); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Achievements:</span>
                            <span class="badge bg-warning"><?php echo count($achievements); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Email Addresses:</span>
                            <span class="badge bg-info"><?php echo count($emails); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Phone Numbers:</span>
                            <span class="badge bg-info"><?php echo count($phones); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Skills:</span>
                            <span class="badge bg-info"><?php echo count($skills); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Interests:</span>
                            <span class="badge bg-info"><?php echo count($interests); ?></span>
                        </div>
                        <hr>
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Complete your profile to connect with more alumni and students.
                        </small>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-lock me-2"></i>Security</h5>
                    </div>
                    <div class="card-body">
                        <p class="small text-muted mb-3">
                            Your email, phone, and Student ID are protected and cannot be changed for security reasons.
                        </p>
                        <button class="btn btn-outline-primary btn-sm w-100 action-link"
                            data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                            <i class="fas fa-key me-2"></i>Change Password
                        </button>
                    </div>
                </div>
            </div><!-- /col-lg-4 -->
        </div><!-- /row -->
    </div>


    <!-- Password Verification Modal -->
    <div class="modal fade" id="verifyModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-shield-alt me-2"></i>Verify Your Identity</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <p class="text-muted">Please enter your current password to enable edit mode:</p>
                        <input type="hidden" name="action" value="enable_edit">
                        <div class="mb-3">
                            <label for="verify_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="verify_password" name="verify_password" required>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Email Modal -->
    <div class="modal fade" id="addEmailModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-envelope me-2"></i>Add Secondary Email</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_email">
                        <div class="mb-3">
                            <label for="new_email" class="form-label">Email Address *</label>
                            <input type="email" class="form-control" id="new_email" name="new_email" required
                                placeholder="e.g., example@domain.com">
                            <small class="text-muted">Enter a valid email address</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Email</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Phone Modal -->
    <div class="modal fade" id="addPhoneModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-phone me-2"></i>Add Secondary Phone</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_phone">
                        <div class="mb-3">
                            <label for="new_phone" class="form-label">Phone Number *</label>
                            <input type="text" class="form-control" id="new_phone" name="new_phone" required
                                placeholder="e.g., +1234567890">
                            <small class="text-muted">Use 10-15 digits, may include +, -, or spaces</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Phone</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Education Modal -->
    <div class="modal fade" id="addEducationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-graduation-cap me-2"></i>Add Education</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_education">
                        <div class="mb-3">
                            <label for="degree" class="form-label">Degree *</label>
                            <select name="degree" class="form-control" id="degree" required>
                                <option value="">Select Degree</option>
                                <option value="Bachelor of Architecture">Bachelor of Architecture</option>
                                <option value="BS in Civil & Environmental Engineering (CEE)">BS in Civil & Environmental Engineering (CEE)</option>
                                <option value="BS in Computer Science & Engineering (CSE)">BS in Computer Science & Engineering (CSE)</option>
                                <option value="BS in Electrical & Electronic Engineering (EEE)">BS in Electrical & Electronic Engineering (EEE)</option>
                                <option value="BS in Electronic & Telecom Engineering (ETE)">BS in Electronic & Telecom Engineering (ETE)</option>
                                <option value="BS in Biochemistry and Biotechnology">BS in Biochemistry and Biotechnology</option>
                                <option value="BS in Environmental Science & Management">BS in Environmental Science & Management</option>
                                <option value="BS in Microbiology">BS in Microbiology</option>
                                <option value="BPharm Professional">BPharm Professional</option>
                                <option value="BBA Major in Accounting">BBA Major in Accounting</option>
                                <option value="BBA Major in Economics">BBA Major in Economics</option>
                                <option value="BBA Major in Entrepreneurship">BBA Major in Entrepreneurship</option>
                                <option value="BBA Major in Finance">BBA Major in Finance</option>
                                <option value="BBA Major in Human Resource Management">BBA Major in Human Resource Management</option>
                                <option value="BBA Major in International Business">BBA Major in International Business</option>
                                <option value="BBA Major in Management">BBA Major in Management</option>
                                <option value="BBA Major in Management Information Systems">BBA Major in Management Information Systems</option>
                                <option value="BBA Major in Marketing">BBA Major in Marketing</option>
                                <option value="BBA Major in Supply Chain Management">BBA Major in Supply Chain Management</option>
                                <option value="BBA General">BBA General</option>
                                <option value="BS in Economics">BS in Economics</option>
                                <option value="BA in English">BA in English</option>
                                <option value="Bachelor of Laws (LLB Hons)">Bachelor of Laws (LLB Hons)</option>
                                <option value="BSS in Media and Journalism (MAJ)">BSS in Media and Journalism (MAJ)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="institution" class="form-label">Institution *</label>
                            <input type="text" class="form-control" id="institution" name="institution" required
                                placeholder="e.g., ABC University">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edu_start_date" class="form-label">Start Date *</label>
                                <input type="date" class="form-control" id="edu_start_date" name="edu_start_date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edu_end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="edu_end_date" name="edu_end_date">
                                <small class="text-muted">Leave blank if currently studying</small>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Education</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Employment Modal -->
    <div class="modal fade" id="addEmploymentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-briefcase me-2"></i>Add Employment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_employment">
                        <div class="mb-3">
                            <label for="job_title" class="form-label">Job Title *</label>
                            <input type="text" class="form-control" id="job_title" name="job_title" required
                                placeholder="e.g., Software Engineer">
                        </div>
                        <div class="mb-3">
                            <label for="company" class="form-label">Company *</label>
                            <input type="text" class="form-control" id="company" name="company" required
                                placeholder="e.g., TechCorp Ltd.">
                        </div>
                        <div class="mb-3">
                            <label for="designation" class="form-label">Designation</label>
                            <input type="text" class="form-control" id="designation" name="designation"
                                placeholder="e.g., Senior Developer">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="emp_start_date" class="form-label">Start Date *</label>
                                <input type="date" class="form-control" id="emp_start_date" name="emp_start_date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="emp_end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="emp_end_date" name="emp_end_date">
                                <small class="text-muted">Leave blank if currently working</small>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Employment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Achievement Modal -->
    <div class="modal fade" id="addAchievementModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-trophy me-2"></i>Add Achievement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_achievement">
                        <div class="mb-3">
                            <label for="ach_title" class="form-label">Achievement Title *</label>
                            <input type="text" class="form-control" id="ach_title" name="ach_title" required
                                placeholder="e.g., Best Student Award">
                        </div>
                        <div class="mb-3">
                            <label for="ach_date" class="form-label">Achievement Date *</label>
                            <input type="date" class="form-control" id="ach_date" name="ach_date" required>
                        </div>
                        <div class="mb-3">
                            <label for="organization" class="form-label">Organization</label>
                            <input type="text" class="form-control" id="organization" name="organization"
                                placeholder="e.g., ABC University">
                        </div>
                        <div class="mb-3">
                            <label for="ach_type" class="form-label">Type</label>
                            <select class="form-select" id="ach_type" name="type">
                                <option value="">Select Type</option>
                                <option value="Academic">Academic</option>
                                <option value="Professional">Professional</option>
                                <option value="Research">Research</option>
                                <option value="Sports">Sports</option>
                                <option value="Cultural">Cultural</option>
                                <option value="Community Service">Community Service</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"
                                placeholder="Brief description of the achievement..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Achievement</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Add Skill Modal -->
    <div class="modal fade" id="addSkillModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-lightbulb me-2"></i>Add Skill</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_skill">
                        <div class="mb-3">
                            <label for="skill_name" class="form-label">Skill Name *</label>
                            <input type="text" class="form-control" id="skill_name" name="skill_name" required
                                placeholder="e.g., JavaScript, SQL, AutoCAD">
                            <small class="text-muted">Adds to your skills list (stored as CSV internally).</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Skill</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Interest Modal -->
    <div class="modal fade" id="addInterestModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-heart me-2"></i>Add Interest</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_interest">
                        <div class="mb-3">
                            <label for="interest_name" class="form-label">Interest Name *</label>
                            <input type="text" class="form-control" id="interest_name" name="interest_name" required
                                placeholder="e.g., AI, Public Speaking, Startups">
                        </div>
                        <small class="text-muted">Saved under your current role (Student/Alumni).</small>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Interest</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Change Password Modal -->
    <div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-navy">
                    <h5 class="modal-title" id="changePasswordLabel">
                        <i class="fas fa-key me-2"></i>Change Password
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="change_password">

                        <div class="mb-3">
                            <label class="form-label" for="current_password">Current Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                                <button class="btn btn-outline-secondary toggle-pass" type="button" data-target="current_password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="new_password">New Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                                <button class="btn btn-outline-secondary toggle-pass" type="button" data-target="new_password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="form-text">
                                Must be at least 8 characters.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="confirm_password">Confirm New Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                <button class="btn btn-outline-secondary toggle-pass" type="button" data-target="confirm_password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div id="matchHelp" class="form-text"></div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button id="submitChangePassword" type="submit" class="btn btn-primary">Update Password</button>
                    </div>
                </form>

            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);

        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            // Prevent end date from being before start date
            const eduStartDate = document.getElementById('edu_start_date');
            const eduEndDate = document.getElementById('edu_end_date');
            const empStartDate = document.getElementById('emp_start_date');
            const empEndDate = document.getElementById('emp_end_date');

            if (eduStartDate && eduEndDate) {
                eduStartDate.addEventListener('change', function() {
                    eduEndDate.min = this.value;
                });
            }

            if (empStartDate && empEndDate) {
                empStartDate.addEventListener('change', function() {
                    empEndDate.min = this.value;
                });
            }

            // Achievement date shouldn't be in the future
            const achDate = document.getElementById('ach_date');
            if (achDate) {
                const today = new Date().toISOString().split('T')[0];
                achDate.max = today;
            }
        });

        // Toggle graduation year input based on role selection
        document.addEventListener('DOMContentLoaded', function() {
            const roleSelect = document.querySelector('select[name="shift_role"]');
            const gradYearInput = document.querySelector('input[name="grad_year"]');

            function toggleGradYear() {
                if (roleSelect.value === 'Alumni') {
                    gradYearInput.closest('.mb-3').style.display = 'block';
                } else {
                    gradYearInput.closest('.mb-3').style.display = 'none';
                    gradYearInput.value = '';
                }
            }

            if (roleSelect && gradYearInput) {
                toggleGradYear(); // On load
                roleSelect.addEventListener('change', toggleGradYear);
            }
        });
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle show/hide password
            document.querySelectorAll('.toggle-pass').forEach(btn => {
                btn.addEventListener('click', function() {
                    const id = this.getAttribute('data-target');
                    const input = document.getElementById(id);
                    if (!input) return;
                    input.type = input.type === 'password' ? 'text' : 'password';
                    const icon = this.querySelector('i');
                    if (icon) icon.classList.toggle('fa-eye-slash');
                });
            });

            // Live match check (UX)
            const newPwd = document.getElementById('new_password');
            const confirmPwd = document.getElementById('confirm_password');
            const matchHelp = document.getElementById('matchHelp');
            const submitBtn = document.getElementById('submitChangePassword');

            function checkMatch() {
                if (!newPwd || !confirmPwd || !matchHelp || !submitBtn) return;
                if (confirmPwd.value.length === 0) {
                    matchHelp.textContent = '';
                    submitBtn.disabled = false;
                    return;
                }
                if (newPwd.value === confirmPwd.value) {
                    matchHelp.textContent = 'Passwords match.';
                    matchHelp.classList.remove('text-danger');
                    matchHelp.classList.add('text-success');
                    submitBtn.disabled = false;
                } else {
                    matchHelp.textContent = 'Passwords do not match.';
                    matchHelp.classList.remove('text-success');
                    matchHelp.classList.add('text-danger');
                    submitBtn.disabled = true;
                }
            }
            if (newPwd && confirmPwd) {
                newPwd.addEventListener('input', checkMatch);
                confirmPwd.addEventListener('input', checkMatch);
            }
        });
    </script>
</body>

</html>