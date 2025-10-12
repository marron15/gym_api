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
    
    // Validate Philippine mobile number format if provided
    if (!empty($input['phone_number'])) {
        // Clean the phone number (remove all non-digit characters)
        $cleanPhone = preg_replace('/[^\d]/', '', $input['phone_number']);
        
        // Check if it's exactly 11 digits and starts with 0
        if (strlen($cleanPhone) !== 11 || !str_starts_with($cleanPhone, '0')) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Phone number must be exactly 11 digits and start with 0 (Philippine mobile format)'
            ]);
            exit();
        }
        
        // Validate Philippine mobile prefixes
        $prefix = substr($cleanPhone, 0, 4);
        $validPrefixes = [
            // Globe/TM
            '0905', '0906', '0907', '0908', '0909', '0910', '0911', '0912', '0913', '0914', '0915', '0916', '0917', '0918', '0919',
            '0920', '0921', '0922', '0923', '0924', '0925', '0926', '0927', '0928', '0929', '0930', '0931', '0932', '0933', '0934', '0935', '0936', '0937', '0938', '0939',
            '0940', '0941', '0942', '0943', '0944', '0945', '0946', '0947', '0948', '0949', '0950', '0951', '0952', '0953', '0954', '0955', '0956', '0957', '0958', '0959',
            '0960', '0961', '0962', '0963', '0964', '0965', '0966', '0967', '0968', '0969', '0970', '0971', '0972', '0973', '0974', '0975', '0976', '0977', '0978', '0979',
            '0980', '0981', '0982', '0983', '0984', '0985', '0986', '0987', '0988', '0989', '0990', '0991', '0992', '0993', '0994', '0995', '0996', '0997', '0998', '0999',
            // Smart/Sun
            '0813', '0817', '0821', '0823', '0824', '0825', '0826', '0827', '0828', '0829', '0830', '0831', '0832', '0833', '0834', '0835', '0836', '0837', '0838', '0839',
            '0840', '0841', '0842', '0843', '0844', '0845', '0846', '0847', '0848', '0849', '0850', '0851', '0852', '0853', '0854', '0855', '0856', '0857', '0858', '0859',
            '0860', '0861', '0862', '0863', '0864', '0865', '0866', '0867', '0868', '0869', '0870', '0871', '0872', '0873', '0874', '0875', '0876', '0877', '0878', '0879',
            '0880', '0881', '0882', '0883', '0884', '0885', '0886', '0887', '0888', '0889', '0890', '0891', '0892', '0893', '0894', '0895', '0896', '0897', '0898', '0899',
            // DITO
            '0895', '0896', '0897', '0898', '0899',
            // GOMO
            '0927', '0928', '0929',
        ];
        
        if (!in_array($prefix, $validPrefixes)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid Philippine mobile number prefix. Must be a valid Globe, Smart, DITO, or GOMO number'
            ]);
            exit();
        }
        
        // Update the input with cleaned phone number
        $input['phone_number'] = $cleanPhone;
    }
    
    // Validate emergency contact number format if provided
    if (!empty($input['emergency_contact_number'])) {
        // Clean the emergency contact number (remove all non-digit characters)
        $cleanEmergencyPhone = preg_replace('/[^\d]/', '', $input['emergency_contact_number']);
        
        // Check if it's exactly 11 digits and starts with 0
        if (strlen($cleanEmergencyPhone) !== 11 || !str_starts_with($cleanEmergencyPhone, '0')) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Emergency contact number must be exactly 11 digits and start with 0 (Philippine mobile format)'
            ]);
            exit();
        }
        
        // Validate Philippine mobile prefixes for emergency contact
        $emergencyPrefix = substr($cleanEmergencyPhone, 0, 4);
        $validPrefixes = [
            // Globe/TM
            '0905', '0906', '0907', '0908', '0909', '0910', '0911', '0912', '0913', '0914', '0915', '0916', '0917', '0918', '0919',
            '0920', '0921', '0922', '0923', '0924', '0925', '0926', '0927', '0928', '0929', '0930', '0931', '0932', '0933', '0934', '0935', '0936', '0937', '0938', '0939',
            '0940', '0941', '0942', '0943', '0944', '0945', '0946', '0947', '0948', '0949', '0950', '0951', '0952', '0953', '0954', '0955', '0956', '0957', '0958', '0959',
            '0960', '0961', '0962', '0963', '0964', '0965', '0966', '0967', '0968', '0969', '0970', '0971', '0972', '0973', '0974', '0975', '0976', '0977', '0978', '0979',
            '0980', '0981', '0982', '0983', '0984', '0985', '0986', '0987', '0988', '0989', '0990', '0991', '0992', '0993', '0994', '0995', '0996', '0997', '0998', '0999',
            // Smart/Sun
            '0813', '0817', '0821', '0823', '0824', '0825', '0826', '0827', '0828', '0829', '0830', '0831', '0832', '0833', '0834', '0835', '0836', '0837', '0838', '0839',
            '0840', '0841', '0842', '0843', '0844', '0845', '0846', '0847', '0848', '0849', '0850', '0851', '0852', '0853', '0854', '0855', '0856', '0857', '0858', '0859',
            '0860', '0861', '0862', '0863', '0864', '0865', '0866', '0867', '0868', '0869', '0870', '0871', '0872', '0873', '0874', '0875', '0876', '0877', '0878', '0879',
            '0880', '0881', '0882', '0883', '0884', '0885', '0886', '0887', '0888', '0889', '0890', '0891', '0892', '0893', '0894', '0895', '0896', '0897', '0898', '0899',
            // DITO
            '0895', '0896', '0897', '0898', '0899',
            // GOMO
            '0927', '0928', '0929',
        ];
        
        if (!in_array($emergencyPrefix, $validPrefixes)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid Philippine mobile number prefix for emergency contact. Must be a valid Globe, Smart, DITO, or GOMO number'
            ]);
            exit();
        }
        
        // Update the input with cleaned emergency contact number
        $input['emergency_contact_number'] = $cleanEmergencyPhone;
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
