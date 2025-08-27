<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../class/Admin.php';

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get JSON input
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            throw new Exception('Invalid JSON input');
        }

        // Get ID from URL parameter or JSON input
        $id = $_GET['id'] ?? $input['id'] ?? null;
        
        if (!$id) {
            throw new Exception('Admin ID is required');
        }

        // Map Flutter field names to PHP field names
        $firstName = $input['firstName'] ?? $input['first_name'] ?? null;
        $middleName = $input['middleName'] ?? $input['middle_name'] ?? null;
        $lastName = $input['lastName'] ?? $input['last_name'] ?? null;
        $emailAddress = $input['emailAddress'] ?? $input['email_address'] ?? null;
        $phoneNumber = $input['phoneNumber'] ?? $input['phone_number'] ?? null;
        $dateOfBirth = $input['dateOfBirth'] ?? $input['date_of_birth'] ?? null;
        $password = $input['password'] ?? null;
        $img = $input['img'] ?? null;

        // Validate required fields
        if (empty($firstName) || empty($lastName) || empty($emailAddress)) {
            throw new Exception('First name, last name, and email address are required');
        }

        // Prepare data for update
        $adminData = [
            'firstName' => $firstName,
            'middleName' => $middleName,
            'lastName' => $lastName,
            'dateOfBirth' => $dateOfBirth,
            'emailAddress' => $emailAddress,
            'phoneNumber' => $phoneNumber,
            'updatedBy' => $input['updatedBy'] ?? 'system',
            'updatedAt' => date('Y-m-d H:i:s'),
            'img' => $img
        ];

        // Include password if provided
        if (isset($password) && !empty($password)) {
            $adminData['password'] = $password;
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

        // Check if email is being changed and if it already exists
        if ($emailAddress !== $existingAdmin[0]['email_address']) {
            $adminWithEmail = $admin->getByEmail($emailAddress);
            if ($adminWithEmail) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Email address already exists'
                ]);
                exit;
            }
        }

        // Update admin
        if ($admin->updateAdminByID($id, $adminData)) {
            // Get the updated admin
            $updatedAdmin = $admin->getById($id);
            if (!empty($updatedAdmin)) {
                unset($updatedAdmin[0]['password']); // Remove password from response
            }

            echo json_encode([
                'success' => true,
                'message' => 'Admin updated successfully',
                'admin' => $updatedAdmin[0] ?? null
            ]);
        } else {
            throw new Exception('Failed to update admin account');
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
        'message' => 'Method not allowed. Use PUT or POST.'
    ]);
}
?>
