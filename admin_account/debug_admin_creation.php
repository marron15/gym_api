<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get JSON input
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            throw new Exception('Invalid JSON input');
        }

        // Debug: Log the received data
        error_log("Debug: Received data: " . print_r($input, true));

        // Check required fields
        $requiredFields = ['first_name', 'last_name', 'email_address', 'password'];
        foreach ($requiredFields as $field) {
            if (empty($input[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }

        // Debug: Check date_of_birth handling
        $dateOfBirth = $input['date_of_birth'] ?? null;
        error_log("Debug: date_of_birth value: " . ($dateOfBirth ?? 'NULL'));

        // Test database connection
        require_once '../class/Database.php';
        $db = new Database();
        $conn = $db->connection();
        
        if (!$conn) {
            throw new Exception('Database connection failed');
        }

        // Check table structure
        $checkColumn = $conn->query("SHOW COLUMNS FROM `admins` LIKE 'date_of_birth'");
        $columnInfo = $checkColumn->fetch(PDO::FETCH_ASSOC);
        error_log("Debug: date_of_birth column info: " . print_r($columnInfo, true));

        // Test admin creation
        require_once '../class/Admin.php';
        $admin = new Admin();
        
        // Prepare test data
        $testData = [
            'first_name' => $input['first_name'],
            'middle_name' => $input['middle_name'] ?? null,
            'last_name' => $input['last_name'],
            'date_of_birth' => $dateOfBirth,
            'email_address' => $input['email_address'],
            'password' => $input['password'],
            'phone_number' => $input['phone_number'] ?? null,
            'created_by' => 'system',
            'img' => $input['img'] ?? null
        ];

        error_log("Debug: Test data prepared: " . print_r($testData, true));

        // Try to create admin
        $result = $admin->signup($testData);
        
        echo json_encode([
            'success' => true,
            'message' => 'Debug completed',
            'received_data' => $input,
            'processed_data' => $testData,
            'column_info' => $columnInfo,
            'signup_result' => $result
        ]);

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Debug failed: ' . $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use POST.'
    ]);
}
?>
