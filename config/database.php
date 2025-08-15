    <?php
    class Database
    {
        private $host = 'localhost';
        private $db_name = 'alumninetworking';
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
    // Add this near the top of config/database.php, after the Database class
    function db(): PDO {
        static $pdoInstance = null;
        if ($pdoInstance instanceof PDO) {
            return $pdoInstance;
        }
        $database = new Database();
        $pdoInstance = $database->getConnection();
        return $pdoInstance;
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
        $query = "
            SELECT 
                p.*, 
                s.batch_year, 
                a.grad_year
            FROM person p
            LEFT JOIN student s ON p.person_id = s.person_id
            LEFT JOIN alumni a ON p.person_id = a.person_id
            WHERE p.person_id = ?
            LIMIT 1
        ";
        $stmt = executeQuery($query, [$user_id]);
        return $stmt && $stmt->rowCount() > 0 ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
    }


    // Authentication function - THIS WAS MISSING
    function authenticateUser($email, $password) {
        try {
            // First, get the person_id from email_address table
            $email_query = "SELECT person_id FROM email_address WHERE email = ?";
            $email_stmt = executeQuery($email_query, [$email]);
            
            if (!$email_stmt || $email_stmt->rowCount() == 0) {
                return false;
            }
            
            $email_result = $email_stmt->fetch(PDO::FETCH_ASSOC);
            $person_id = $email_result['person_id'];
            
            // Now get the user info including password
            $user_query = "SELECT * FROM person WHERE person_id = ?";
            $user_stmt = executeQuery($user_query, [$person_id]);
            
            if (!$user_stmt || $user_stmt->rowCount() == 0) {
                return false;
            }
            
            $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Verify password
            if (password_verify($password, $user['password'])) {
                return $user;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Authentication error: " . $e->getMessage());
            return false;
        }
    }

    // Additional helper functions
    function sanitizeInput($input) {
        return htmlspecialchars(strip_tags(trim($input)));
    }

    function generateRandomString($length = 10) {
        return bin2hex(random_bytes($length / 2));
    }

    function logActivity($user_id, $activity, $details = '') {
        try {
            $query = "INSERT INTO activity_log (user_id, activity, details, created_at) VALUES (?, ?, ?, NOW())";
            executeQuery($query, [$user_id, $activity, $details]);
        } catch (Exception $e) {
            error_log("Activity logging error: " . $e->getMessage());
        }
    }
    ?>