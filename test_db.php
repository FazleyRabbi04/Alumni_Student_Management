<?php
// test_database.php - Put this file in your root directory and run it
require_once 'config/database.php';

echo "<h2>Database Connection Test</h2>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if ($db) {
        echo "✅ Database connection successful!<br>";
        
        // Test basic query
        $stmt = $db->query("SELECT 1");
        if ($stmt) {
            echo "✅ Basic query execution successful!<br>";
        }
        
        // Check if tables exist
        $tables_to_check = ['person', 'email_address', 'person_phone', 'alumni', 'student'];
        
        foreach ($tables_to_check as $table) {
            try {
                $stmt = $db->query("DESCRIBE $table");
                if ($stmt) {
                    echo "✅ Table '$table' exists<br>";
                } else {
                    echo "❌ Table '$table' does not exist<br>";
                }
            } catch (Exception $e) {
                echo "❌ Table '$table' error: " . $e->getMessage() . "<br>";
            }
        }
        
        // Test person table structure
        echo "<h3>Person Table Structure:</h3>";
        $stmt = $db->query("DESCRIBE person");
        if ($stmt) {
            echo "<table border='1' cellpadding='5' cellspacing='0'>";
            echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo "<tr>";
                echo "<td>" . $row['Field'] . "</td>";
                echo "<td>" . $row['Type'] . "</td>";
                echo "<td>" . $row['Null'] . "</td>";
                echo "<td>" . $row['Key'] . "</td>";
                echo "<td>" . $row['Default'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
        // Test insert with dummy data (will rollback)
        echo "<h3>Testing Insert Operation:</h3>";
        try {
            $db->beginTransaction();
            
            $stmt = $db->prepare("INSERT INTO person (first_name, last_name, NID, gender, department, password, date_of_birth) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $result = $stmt->execute(['Test', 'User', 'TEST123', 'Male', 'CS', password_hash('testpass', PASSWORD_DEFAULT), '1990-01-01']);
            
            if ($result) {
                $test_person_id = $db->lastInsertId();
                echo "✅ Test person insert successful (ID: $test_person_id)<br>";
                
                // Test email insert
                $stmt = $db->prepare("INSERT INTO email_address (person_id, email) VALUES (?, ?)");
                $result = $stmt->execute([$test_person_id, 'test@example.com']);
                
                if ($result) {
                    echo "✅ Test email insert successful<br>";
                } else {
                    echo "❌ Test email insert failed<br>";
                }
                
                // Test phone insert
                $stmt = $db->prepare("INSERT INTO person_phone (person_id, phone_number) VALUES (?, ?)");
                $result = $stmt->execute([$test_person_id, '1234567890']);
                
                if ($result) {
                    echo "✅ Test phone insert successful<br>";
                } else {
                    echo "❌ Test phone insert failed<br>";
                }
                
                // Test alumni insert
                $stmt = $db->prepare("INSERT INTO alumni (person_id, grad_year) VALUES (?, ?)");
                $result = $stmt->execute([$test_person_id, 2023]);
                
                if ($result) {
                    echo "✅ Test alumni insert successful<br>";
                } else {
                    echo "❌ Test alumni insert failed<br>";
                }
            } else {
                echo "❌ Test person insert failed<br>";
            }
            
            // Rollback to clean up test data
            $db->rollback();
            echo "✅ Test transaction rolled back successfully<br>";
            
        } catch (Exception $e) {
            $db->rollback();
            echo "❌ Insert test failed: " . $e->getMessage() . "<br>";
        }
        
    } else {
        echo "❌ Database connection failed!<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<h3>Current PHP Configuration:</h3>";
echo "PHP Version: " . phpversion() . "<br>";
echo "PDO Available: " . (extension_loaded('pdo') ? 'Yes' : 'No') . "<br>";
echo "PDO MySQL Available: " . (extension_loaded('pdo_mysql') ? 'Yes' : 'No') . "<br>";
echo "Error Reporting: " . ini_get('display_errors') . "<br>";

echo "<hr>";
echo "<h3>Recommendations:</h3>";
echo "1. Run this test first to identify any database issues<br>";
echo "2. Check your database credentials in config/database.php<br>";
echo "3. Make sure your database 'alumninetworking' exists<br>";
echo "4. Ensure all required tables are created using the SQL script<br>";
echo "5. Check that your web server has proper permissions<br>";
echo "6. Add ?debug=1 to your signup URL to see detailed error messages<br>";
?>