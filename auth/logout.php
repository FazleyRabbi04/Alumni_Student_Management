<?php
require_once '../config/database.php';
startSecureSession();

// Destroy all session data
session_unset();
session_destroy();

// Redirect to home page
header('Location: ../index.php');
exit();
?>