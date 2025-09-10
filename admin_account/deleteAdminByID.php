<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../class/Admin.php';

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE' || $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get ID from URL parameter or POST body
        $id = null;
        
        if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
            $id = $_GET['id'] ?? null;
        } else {
            // For POST requests, get from JSON body
            $input = json_decode(file_get_contents('php://input'), true);
            $id = $input['id'] ?? null;
        }
        
        if (!$id) {
            throw new Exception('Admin ID is required');
        }

        $admin = new Admin();
        
        // Check if admin exists
        $existingAdmin = $admin->getById($id);
        if (empty($existingAdmin)) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Admin not found'
            ]);
            exit;
        }

        // Archive admin (soft delete)
        if ($admin->deleteById($id)) {
            echo json_encode([
                'success' => true,
                'message' => 'Admin archived successfully'
            ]);
        } else {
            throw new Exception('Failed to archive admin account');
        }

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use DELETE or POST.'
    ]);
}
?>
