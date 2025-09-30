<?php
// Suppress any notices/warnings to avoid breaking JSON output
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
    if (empty($input['customer_id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Customer ID is required'
        ]);
        exit();
    }
    
    $customerId = (int)$input['customer_id'];
    
    // Create customer instance and get profile data
    $customer = new Customer();
    $profileData = $customer->getCustomerWithAddress($customerId);
    
    if ($profileData) {
        // Get address details separately
        $addressDetails = $customer->getCustomerAddressDetails($customerId);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Profile data retrieved successfully',
            'data' => [
                'customer_id' => $profileData['id'],
                'email' => $profileData['email'],
                'first_name' => $profileData['first_name'],
                'last_name' => $profileData['last_name'],
                'middle_name' => $profileData['middle_name'],
                'full_name' => trim($profileData['first_name'] . ' ' . $profileData['last_name']),
                'phone_number' => $profileData['phone_number'],
                'birthdate' => $profileData['birthdate'],
                'address' => $profileData['address'] ?? null,
                'address_details' => $addressDetails,
                'emergency_contact_name' => $profileData['emergency_contact_name'],
                'emergency_contact_number' => $profileData['emergency_contact_number'],
                'img' => $profileData['img'],
                'created_at' => $profileData['created_at'],
                'updated_at' => $profileData['updated_at'] ?? null
            ]
        ], JSON_PRETTY_PRINT);
    } else {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Customer not found'
        ], JSON_PRETTY_PRINT);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>
