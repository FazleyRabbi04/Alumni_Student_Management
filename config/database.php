<?php
class database
{
    private $host = 'localhost';
    private $db_name = 'alumni_network_management';
    private $username = 'root';
    private $password = '';
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        return $this->conn;
    }
}

// Database utility functions
function executeQuery($query, $params = []) {
    $database = new Database();
    $db = $database->getConnection();

    try {
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        return $stmt;
    } catch(PDOException $e) {
        error_log("Database query error: " . $e->getMessage());
        return false;
    }
}

function getLastInsertId() {
    $database = new Database();
    $db = $database->getConnection();
    return $db->lastInsertId();
}

// Session management
function startSecureSession() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
}

function isLoggedIn() {
    startSecureSession();
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /alumni-network/auth/signin.php');
        exit();
    }
}

function getUserInfo($user_id) {
    $query = "SELECT p.*, a.grad_year, s.batch_year 
              FROM person p 
              LEFT JOIN alumni a ON p.person_id = a.person_id 
              LEFT JOIN student s ON p.person_id = s.person_id 
              WHERE p.person_id = ?";
    $stmt = executeQuery($query, [$user_id]);
    return $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
}
?>