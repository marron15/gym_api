<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../class/JWT.php';
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
    // Get token from Authorization header or request body
    $token = null;
    
    // Check Authorization header first
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        $authHeader = $headers['Authorization'];
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
        }
    }
    
    // If no token in header, check request body
    if (!$token) {
        $input = json_decode(file_get_contents('php://input'), true);
        $token = $input['token'] ?? null;
    }
    
    if (!$token) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'No token provided'
        ]);
        exit();
    }
    
    // Validate token
    $validation = JWT::validateToken($token);
    
    if (!$validation['valid']) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => $validation['message']
        ]);
        exit();
    }
    
    // Token is valid, fetch complete customer data from database
    $payload = $validation['payload'];
    
    try {
        $customer = new Customer();
        $customerData = $customer->getByEmail($payload['email']);
        
        if ($customerData) {
            // Remove password from response
            unset($customerData['password']);
            
            // Attach address details
            $withAddress = $customer->getCustomerWithAddress($customerData['id']);
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Token is valid',
                'data' => [
                    'customer_id' => $customerData['id'],
                    'email' => $customerData['email'],
                    'first_name' => $customerData['first_name'],
                    'last_name' => $customerData['last_name'],
                    'middle_name' => $customerData['middle_name'],
                    'full_name' => trim($customerData['first_name'] . ' ' . $customerData['last_name']),
                    'phone_number' => $customerData['phone_number'],
                    'birthdate' => $customerData['birthdate'],
                    'address' => $withAddress['address'] ?? null,
                    'address_details' => $withAddress['address_details'] ?? null,
                    'emergency_contact_name' => $customerData['emergency_contact_name'],
                    'emergency_contact_number' => $customerData['emergency_contact_number'],
                    // Image is no longer required; avoid undefined index warnings
                    'img' => $customerData['img'] ?? null,
                    'created_at' => $customerData['created_at'],
                    'expires_at' => $payload['exp'] ?? null
                ]
            ]);
        } else {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Customer not found'
            ]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error fetching customer data: ' . $e->getMessage()
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
