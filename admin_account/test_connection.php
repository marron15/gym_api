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
        require_once '../class/Admin.php';
        
        // Test database connection
        $db = new Database();
        $conn = $db->connection();
        
        if (!$conn) {
            throw new Exception('Database connection failed');
        }
        
        // Test admin operations
        $admin = new Admin();
        
        // Test getAll
        $allAdmins = $admin->getAll();
        
        // Test getById if there are admins
        $firstAdmin = null;
        if (!empty($allAdmins)) {
            $firstAdmin = $admin->getById($allAdmins[0]['id']);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Connection test successful',
            'database_connected' => true,
            'total_admins' => count($allAdmins),
            'first_admin' => $firstAdmin ? $firstAdmin[0] : null,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Connection test failed: ' . $e->getMessage(),
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
