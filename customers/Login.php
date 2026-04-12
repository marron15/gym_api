<?php
// Suppress any output before JSON
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../class/Customer.php';
require_once '../class/Admin.php';
require_once '../class/AuditLog.php';

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
    if (empty($input['email']) || empty($input['password'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Email and password are required'
        ]);
        exit();
    }
    
    $email = strtolower(trim($input['email']));
    $password = $input['password'];
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid email format'
        ]);
        exit();
    }
    
    // First attempt Admin login
    $admin = new Admin();
    $adminLoginResult = $admin->login($email, $password);
    
    if ($adminLoginResult['success']) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Admin login successful',
            'role' => 'admin',
            'admin' => $adminLoginResult['admin'],
            'access_token' => $adminLoginResult['access_token'],
            'refresh_token' => $adminLoginResult['refresh_token'],
            'token_type' => $adminLoginResult['token_type'],
            'expires_in' => $adminLoginResult['expires_in']
        ]);
        exit();
    }

    // Prepare for Customer attempt
    // If admin login simply failed due to wrong password, we shouldn't necessarily assume customer lookup will succeed,
    // but we fall through to Customer lookup gracefully.
    
    // Create customer instance and attempt login
    $customer = new Customer();
    $loginResult = $customer->login($email, $password);
    
    if ($loginResult['success']) {
        $customerId = $loginResult['customer']['id'];
        $customerFullName = trim(
            ($loginResult['customer']['first_name'] ?? '') . ' ' . ($loginResult['customer']['last_name'] ?? '')
        );
        try {
            $auditLog = new AuditLog();
            $auditLog->record([
                'customer_id' => $customerId,
                'customer_name' => $customerFullName ?: null,
                'activity_category' => 'auth',
                'activity_type' => 'login',
                'activity_title' => 'Customer logged in',
                'description' => $customerFullName
                    ? "{$customerFullName} logged in"
                    : "Customer #{$customerId} logged in",
                'metadata' => [
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                ],
                'actor_type' => 'customer',
                'actor_name' => $customerFullName ?: null,
            ]);
        } catch (Exception $e) {
            error_log('Audit log login error: ' . $e->getMessage());
        }

        // Enrich with address information
        $customerWithAddress = $customer->getCustomerWithAddress($customerId);
        $addressStr = $customerWithAddress['address'] ?? null;
        $addressDetails = $customerWithAddress['address_details'] ?? null;
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'role' => 'customer',
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
            'message' => 'Invalid email or password'
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
