<?php
// Suppress any output before JSON
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../class/Customer.php';

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Only POST requests are accepted.'
    ]);
    exit();
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate input
    if (!$input) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid JSON input'
        ]);
        exit();
    }
    
    // Check required fields
    if (empty($input['contact_number']) || empty($input['password'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Contact number and password are required'
        ]);
        exit();
    }
    
    $contact = trim($input['contact_number']);
    $password = $input['password'];
    
    // Validate contact number format (10-11 digits)
    if (!preg_match('/^\d{10,11}$/', $contact)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid contact number format'
        ]);
        exit();
    }
    
    // Create customer instance and attempt login
    $customer = new Customer();
    $loginResult = $customer->loginByContact($contact, $password);
    
    if ($loginResult['success']) {
        // Enrich with address information
        $customerWithAddress = $customer->getCustomerWithAddress($loginResult['customer']['id']);
        $addressStr = $customerWithAddress['address'] ?? null;
        $addressDetails = $customerWithAddress['address_details'] ?? null;
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'customer_id' => $loginResult['customer']['id'],
                'email' => $loginResult['customer']['email'],
                'first_name' => $loginResult['customer']['first_name'],
                'last_name' => $loginResult['customer']['last_name'],
                'middle_name' => $loginResult['customer']['middle_name'],
                'full_name' => trim($loginResult['customer']['first_name'] . ' ' . $loginResult['customer']['last_name']),
                'phone_number' => $loginResult['customer']['phone_number'],
                'birthdate' => $loginResult['customer']['birthdate'],
                'address' => $addressStr,
                'address_details' => $addressDetails,
                'emergency_contact_name' => $loginResult['customer']['emergency_contact_name'],
                'emergency_contact_number' => $loginResult['customer']['emergency_contact_number'],
                'img' => $loginResult['customer']['img'],
                'created_at' => $loginResult['customer']['created_at']
            ],
            'access_token' => $loginResult['access_token'],
            'refresh_token' => $loginResult['refresh_token'],
            'token_type' => $loginResult['token_type'],
            'expires_in' => $loginResult['expires_in']
        ]);
    } else {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => $loginResult['message']
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error: ' . $e->getMessage()
    ]);
}
?>
