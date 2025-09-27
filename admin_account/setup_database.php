<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        require_once '../class/Database.php';
        
        $db = new Database();
        $conn = $db->connection();
        
        if (!$conn) {
            throw new Exception('Database connection failed');
        }

        // Check if admins table exists
        $checkTable = $conn->query("SHOW TABLES LIKE 'admins'");
        if ($checkTable->rowCount() == 0) {
            // Create admins table
            $createTable = "CREATE TABLE `admins` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `first_name` varchar(255) NOT NULL,
                `middle_name` varchar(255) DEFAULT NULL,
                `last_name` varchar(255) NOT NULL,
                `date_of_birth` date DEFAULT NULL,
                `email_address` varchar(255) DEFAULT NULL,
                `password` varchar(255) NOT NULL,
                `phone_number` varchar(20) DEFAULT NULL,
                `img` longtext DEFAULT NULL,
                `created_by` varchar(255) DEFAULT 'system',
                `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                `updated_by` varchar(255) DEFAULT NULL,
                `updated_at` timestamp DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            
            $conn->exec($createTable);
            $message = "Admins table created successfully";
        } else {
            $messages = [];
            
            // Check if date_of_birth column allows NULL
            $checkColumn = $conn->query("SHOW COLUMNS FROM `admins` LIKE 'date_of_birth'");
            $columnInfo = $checkColumn->fetch(PDO::FETCH_ASSOC);
            
            if ($columnInfo && $columnInfo['Null'] === 'NO') {
                // Modify column to allow NULL
                $alterColumn = "ALTER TABLE `admins` MODIFY `date_of_birth` date DEFAULT NULL";
                $conn->exec($alterColumn);
                $messages[] = "date_of_birth column modified to allow NULL values";
            }
            
            // Check if email_address column allows NULL
            $checkEmailColumn = $conn->query("SHOW COLUMNS FROM `admins` LIKE 'email_address'");
            $emailColumnInfo = $checkEmailColumn->fetch(PDO::FETCH_ASSOC);
            
            if ($emailColumnInfo && $emailColumnInfo['Null'] === 'NO') {
                // Modify column to allow NULL
                $alterEmailColumn = "ALTER TABLE `admins` MODIFY `email_address` varchar(255) DEFAULT NULL";
                $conn->exec($alterEmailColumn);
                $messages[] = "email_address column modified to allow NULL values";
            }
            
            if (empty($messages)) {
                $message = "Database structure is already correct";
            } else {
                $message = implode("; ", $messages);
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => $message,
            'database_connected' => true,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database setup failed: ' . $e->getMessage(),
            'database_connected' => false,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use GET.'
    ]);
}
?>
