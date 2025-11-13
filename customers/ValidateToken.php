<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../class/JWT.php';
require_once '../class/Customer.php';

if (!function_exists('logValidateToken')) {
    function logValidateToken(string $message): void {
        $logFile = __DIR__ . '/../validate_token_debug.log';
        $timestamp = '[' . date('c') . '] ';
        @file_put_contents($logFile, $timestamp . $message . PHP_EOL, FILE_APPEND);
    }
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
    // Get token from Authorization header or request body (robust across servers)
    $token = null;
    $rawBody = null;

    // 1) Try common server variables first (works on LiteSpeed/Hostinger)
    $possibleAuthKeys = [
        'HTTP_AUTHORIZATION',
        'REDIRECT_HTTP_AUTHORIZATION',
        'Authorization',
    ];
    foreach ($possibleAuthKeys as $key) {
        if (!empty($_SERVER[$key])) {
            $authHeader = $_SERVER[$key];
            if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                $token = $matches[1];
                break;
            }
        }
    }

    // 2) Fallback: case-insensitive lookup via getallheaders (may be disabled on some SAPIs)
    if (!$token && function_exists('getallheaders')) {
        $headers = getallheaders();
        if (is_array($headers)) {
            foreach ($headers as $name => $value) {
                if (strcasecmp($name, 'Authorization') === 0 && is_string($value)) {
                    if (preg_match('/Bearer\s+(.*)$/i', $value, $matches)) {
                        $token = $matches[1];
                        break;
                    }
                }
            }
        }
    }

    // 3) Fallback: JSON body { "token": "..." }
    if (!$token) {
        $rawBody = file_get_contents('php://input');
        if ($rawBody) {
            $input = json_decode($rawBody, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($input)) {
                $token = $input['token'] ?? null;
            }
        }
    }

    // 4) Fallback: form-encoded token=...
    if (!$token && isset($_POST['token'])) {
        $token = $_POST['token'];
    }
    
    if (!$token) {
        $debugHeaders = [];
        foreach ($_SERVER as $key => $value) {
            if (stripos($key, 'HTTP_') === 0 || stripos($key, 'REDIRECT_HTTP_') === 0 || strcasecmp($key, 'Authorization') === 0) {
                $debugHeaders[$key] = is_string($value) ? $value : json_encode($value);
            }
        }
        if ($rawBody === null) {
            $rawBody = file_get_contents('php://input');
        }
        logValidateToken('Missing token. Headers=' . json_encode($debugHeaders) . ' body=' . substr($rawBody ?? '', 0, 200));
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
        logValidateToken('Invalid token: ' . ($validation['message'] ?? 'unknown'));
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => $validation['message']
        ]);
        exit();
    }
    logValidateToken('Token valid for email=' . ($validation['email'] ?? 'n/a'));
    
    // Token is valid, fetch complete customer data from database
    $payload = $validation['payload'];
    
    try {
        $customer = new Customer();
        $customerData = null;

        if (!empty($payload['email'])) {
            $customerData = $customer->getByEmail($payload['email']);
        }

        if (!$customerData && !empty($payload['customer_id'])) {
            $customerData = $customer->getByIdSingle($payload['customer_id']);
        }

        if (!$customerData) {
            logValidateToken('Customer lookup failed for token payload: ' . json_encode($payload));
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Customer not found'
            ]);
            exit();
        }

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
                'phone_number' => $customerData['phone_number'],
                'first_name' => $customerData['first_name'],
                'last_name' => $customerData['last_name'],
                'middle_name' => $customerData['middle_name'],
                'full_name' => trim($customerData['first_name'] . ' ' . $customerData['last_name']),
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
