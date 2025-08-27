<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' || $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_once '../class/Admin.php';
        
        $id = null;
        
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $id = $_GET['id'] ?? null;
        } else {
            // For POST requests, get from JSON body
            $input = json_decode(file_get_contents('php://input'), true);
            $id = $input['id'] ?? null;
        }
        
        if (!$id) {
            throw new Exception('ID parameter is required');
        }

        $admin = new Admin();
        $result = $admin->getById($id);
        
        if (empty($result)) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Admin not found'
            ]);
        } else {
            echo json_encode($result[0]); // Return first (and only) result
        }
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Error retrieving admin: ' . $e->getMessage()
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use GET or POST.'
    ]);
}
?>
