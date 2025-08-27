<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../class/JWT.php';

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
    
    if (!$input || !isset($input['refresh_token'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Refresh token is required'
        ]);
        exit();
    }
    
    $refreshToken = $input['refresh_token'];
    
    // Validate refresh token
    $validation = JWT::validateToken($refreshToken);
    
    if (!$validation['valid']) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid or expired refresh token'
        ]);
        exit();
    }
    
    // Generate new tokens
    $payload = $validation['payload'];
    $tokenPayload = [
        'customer_id' => $payload['customer_id'],
        'email' => $payload['email'],
        'first_name' => $payload['first_name'],
        'last_name' => $payload['last_name']
    ];
    
    $newAccessToken = JWT::encode($tokenPayload, 24); // 24 hours
    $newRefreshToken = JWT::encode($tokenPayload, 168); // 7 days
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Tokens refreshed successfully',
        'access_token' => $newAccessToken,
        'refresh_token' => $newRefreshToken,
        'token_type' => 'Bearer',
        'expires_in' => 24 * 3600, // 24 hours in seconds
        'data' => [
            'customer_id' => $payload['customer_id'],
            'email' => $payload['email'],
            'first_name' => $payload['first_name'],
            'last_name' => $payload['last_name'],
            'full_name' => trim($payload['first_name'] . ' ' . $payload['last_name'])
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error: ' . $e->getMessage()
    ]);
}
?>
