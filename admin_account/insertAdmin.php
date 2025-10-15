<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../class/Admin.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get JSON input
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            throw new Exception('Invalid JSON input');
        }

        // Validate required fields
        $requiredFields = ['first_name', 'last_name', 'password'];
        foreach ($requiredFields as $field) {
            if (empty($input[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }

        // Prepare data for insertion
        $adminData = [
            'firstName' => $input['first_name'],
            'middleName' => $input['middle_name'] ?? null,
            'lastName' => $input['last_name'],
            'dateOfBirth' => $input['date_of_birth'],
            'password' => $input['password'],
            'phoneNumber' => $input['phone_number'],
            'email' => $input['email'] ?? ($input['email_address'] ?? null),
            'createdBy' => $input['created_by'] ?? 'system',
            'createdAt' => date('Y-m-d H:i:s'),
            'img' => $input['img'] ?? null
        ];

        $admin = new Admin();
        
        // Insert admin
        if ($admin->store($adminData)) {
            // Get the newly created admin by phone number
            $newAdmin = $admin->getByPhoneNumber($input['phone_number']);
            unset($newAdmin['password']); // Remove password from response

            echo json_encode([
                'success' => true,
                'message' => 'Admin created successfully',
                'admin' => $newAdmin
            ]);
        } else {
            throw new Exception('Failed to create admin account');
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
