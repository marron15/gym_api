<?php
// Database connection checker
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../class/Database.php';
require_once '../class/Customer.php';

try {
    // Test database connection
    $db = new Database();
    $conn = $db->connection();
    
    if (!$conn) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed',
            'error' => 'Unable to establish database connection'
        ]);
        exit();
    }
    
    // Test if customers table exists
    $stmt = $conn->prepare("SHOW TABLES LIKE 'customers'");
    $stmt->execute();
    $tableExists = $stmt->rowCount() > 0;
    
    if (!$tableExists) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Table not found',
            'error' => 'customers table does not exist in database'
        ]);
        exit();
    }
    
    // Test table structure
    $stmt = $conn->prepare("DESCRIBE customers");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Test basic query
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM customers");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Database connection successful',
        'data' => [
            'connection' => 'OK',
            'table_exists' => $tableExists,
            'table_name' => 'customers',
            'column_count' => count($columns),
            'columns' => array_column($columns, 'Field'),
            'record_count' => $result['count'] ?? 0
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Test failed',
        'error' => $e->getMessage()
    ]);
}
?>
