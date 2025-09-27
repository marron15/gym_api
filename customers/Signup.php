<?php
// Suppress any output before JSON
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../class/Customer.php';
// Load Membership class if it exists to prevent fatal errors
if (file_exists(__DIR__ . '/../class/Customer.php')) {
    require_once __DIR__ . '/../class/Customer.php';
}

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
    
    // Check required fields (email is now optional)
    $requiredFields = ['first_name', 'last_name', 'password'];
    $missingFields = [];
    
    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            $missingFields[] = $field;
        }
    }
    
    if (!empty($missingFields)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Missing required fields: ' . implode(', ', $missingFields)
        ]);
        exit();
    }
    
    // Validate email format only if email is provided
    if (!empty($input['email']) && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid email format'
        ]);
        exit();
    }
    
    // Validate password strength (minimum 6 characters)
    if (strlen($input['password']) < 6) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Password must be at least 6 characters long'
        ]);
        exit();
    }
    
    // Validate phone number format if provided
    if (!empty($input['phone_number'])) {
        // Simple phone number validation (adjust regex as needed)
        if (!preg_match('/^[\d\s\-\+\(\)]{7,15}$/', $input['phone_number'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid phone number format'
            ]);
            exit();
        }
    }
    
    // Validate birthdate format if provided
    if (!empty($input['birthdate'])) {
        $date = DateTime::createFromFormat('Y-m-d', $input['birthdate']);
        if (!$date || $date->format('Y-m-d') !== $input['birthdate']) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid birthdate format. Use YYYY-MM-DD'
            ]);
            exit();
        }
    }
    
    // Sanitize input data
    $customerData = [
        'first_name' => trim($input['first_name']),
        'last_name' => trim($input['last_name']),
        'middle_name' => !empty($input['middle_name']) ? trim($input['middle_name']) : null,
        'email' => !empty($input['email']) ? strtolower(trim($input['email'])) : null,
        'password' => $input['password'],
        'birthdate' => !empty($input['birthdate']) ? $input['birthdate'] : null,
        'phone_number' => !empty($input['phone_number']) ? trim($input['phone_number']) : null,
        'emergency_contact_name' => !empty($input['emergency_contact_name']) ? trim($input['emergency_contact_name']) : null,
        'emergency_contact_number' => !empty($input['emergency_contact_number']) ? trim($input['emergency_contact_number']) : null,
        'address' => !empty($input['address']) ? trim($input['address']) : null,
        'created_by' => isset($input['created_by']) ? $input['created_by'] : 'signup_form'
    ];
    
    // Create customer instance and attempt signup
    $customer = new Customer();
    $signupResult = $customer->signup($customerData);
    
    if ($signupResult['success']) {
        // Create membership if requested AND Membership class is available
        $membershipCreated = false;
        if (isset($input['membership_type']) && isset($input['expiration_date']) && class_exists('Membership')) {
            try {
                $membership = new Membership();
                $membershipResult = $membership->createMembership(
                    $signupResult['customer']['id'],
                    $input['membership_type'],
                    $input['expiration_date'],
                    $input['created_by'] ?? 'admin',
                    $input['services'] ?? null,
                    $input['price'] ?? null,
                    $input['membership_description'] ?? null,
                    $input['payment_method'] ?? null,
                    $input['payment_amount_total'] ?? null,
                    $input['pay_amount_paid'] ?? null,
                    $input['reference_number'] ?? null,
                    $input['pay_reference_image'] ?? null
                );
                $membershipCreated = !empty($membershipResult['success']);
            } catch (Throwable $t) {
                // Do not fail signup if membership creation fails; report flag only
                error_log('Membership creation failed: ' . $t->getMessage());
                $membershipCreated = false;
            }
        }
        
        http_response_code(201); // Created
        echo json_encode([
            'success' => true,
            'message' => $signupResult['message'],
            'data' => [
                'customer_id' => $signupResult['customer']['id'],
                'email' => $signupResult['customer']['email'],
                'first_name' => $signupResult['customer']['first_name'],
                'last_name' => $signupResult['customer']['last_name'],
                'middle_name' => $signupResult['customer']['middle_name'],
                'full_name' => trim($signupResult['customer']['first_name'] . ' ' . $signupResult['customer']['last_name']),
                'phone_number' => $signupResult['customer']['phone_number'],
                'birthdate' => $signupResult['customer']['birthdate'],
                'emergency_contact_name' => $signupResult['customer']['emergency_contact_name'],
                'emergency_contact_number' => $signupResult['customer']['emergency_contact_number'],
                'img' => $signupResult['customer']['img'],
                'created_at' => $signupResult['customer']['created_at']
            ],
            'access_token' => $signupResult['access_token'],
            'refresh_token' => $signupResult['refresh_token'],
            'token_type' => $signupResult['token_type'],
            'expires_in' => $signupResult['expires_in'],
            'customer_created' => $signupResult['customer']['id'],
            'membership_created' => $membershipCreated
        ]);
    } else {
        // Determine appropriate HTTP status code based on error type
        $statusCode = 400; // Bad Request (default)
        if (strpos($signupResult['message'], 'already exists') !== false) {
            $statusCode = 409; // Conflict
        } elseif (strpos($signupResult['message'], 'Database error') !== false) {
            $statusCode = 500; // Internal Server Error
        }
        
        http_response_code($statusCode);
        echo json_encode([
            'success' => false,
            'message' => $signupResult['message']
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
