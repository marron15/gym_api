<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../class/JWT.php';
require_once '../class/Membership.php';

function extractBearerToken(): ?string
{
    $possibleAuthKeys = [
        'HTTP_AUTHORIZATION',
        'REDIRECT_HTTP_AUTHORIZATION',
        'Authorization',
    ];

    foreach ($possibleAuthKeys as $key) {
        if (!empty($_SERVER[$key])) {
            $authHeader = $_SERVER[$key];
            if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                return trim($matches[1]);
            }
        }
    }

    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (is_array($headers)) {
            foreach ($headers as $name => $value) {
                if (strcasecmp($name, 'Authorization') === 0 && is_string($value)) {
                    if (preg_match('/Bearer\s+(.*)$/i', $value, $matches)) {
                        return trim($matches[1]);
                    }
                }
            }
        }
    }

    return null;
}

try {
    $token = extractBearerToken();
    if (!$token) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'No token provided'
        ]);
        exit();
    }

    $validation = JWT::validateToken($token);
    if (!$validation['valid']) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => $validation['message'] ?? 'Invalid token'
        ]);
        exit();
    }

    $customerId = (int)($validation['customer_id'] ?? 0);
    if ($customerId <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Customer ID is required'
        ]);
        exit();
    }

    $membership = new Membership();
    $history = $membership->getHistoryByCustomerId($customerId);

    echo json_encode([
        'success' => true,
        'customer_id' => $customerId,
        'data' => $history,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>