<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once '../class/Admin.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get JSON input
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            throw new Exception('Invalid JSON input');
        }

        // Validate required fields
        $requiredFields = ['first_name', 'last_name', 'email_address', 'password'];
        foreach ($requiredFields as $field) {
            if (empty($input[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }

        // Validate email format
        if (!filter_var($input['email_address'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format');
        }

        // Validate password length
        if (strlen($input['password']) < 6) {
            throw new Exception('Password must be at least 6 characters long');
        }

        // Validate phone number if provided (exactly 11 digits)
        if (isset($input['phone_number']) && $input['phone_number'] !== null) {
            $phone = preg_replace('/\D/', '', $input['phone_number']);
            if ($phone !== '' && strlen($phone) !== 11) {
                throw new Exception('Phone number must be exactly 11 digits');
            }
        }

        $admin = new Admin();
        $result = $admin->signup($input);

        if ($result['success']) {
            echo json_encode($result);
        } else {
            http_response_code(400);
            echo json_encode($result);
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
        'message' => 'Method not allowed. Use POST.'
    ]);
}
?>
