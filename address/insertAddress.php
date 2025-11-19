<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../class/CustomersAddress.php';

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
    $address = new CustomersAddress();

    $customerId = (int)($_POST['customer_id'] ?? 0);
    $street = $_POST['street'] ?? "";
    $city = $_POST['city'] ?? "";
    $state = $_POST['state'] ?? "";
    $zipCode = $_POST['zip_code'] ?? "";
    $country = $_POST['country'] ?? "";
    $status = (int)($_POST['status'] ?? 0);
    $createdBy = 1;
    $createdAt = date('Y-m-d H:i:s');
    $updatedBy = 1;
    $updatedAt = date('Y-m-d H:i:s');

    if ($customerId <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'customer_id is required'
        ]);
        exit();
    }

    $data = [
        'customerId' => $customerId,
        'street' => $street,
        'city' => $city,
        'state' => $state,
        'postalCode' => $zipCode,
        'country' => $country,
        'status' => $status,
        'createdBy' => $createdBy,
        'createdAt' => $createdAt,
        'updatedBy' => $updatedBy,
        'updatedAt' => $updatedAt,
    ];

    $result = $address->upsertByCustomerId($customerId, $data);

    if ($result) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => $result['operation'] === 'inserted'
                ? 'Address inserted successfully'
                : 'Address updated successfully',
            'data' => [
                'customer_id' => $customerId,
                'address_id' => $result['address_id'],
                'operation' => $result['operation']
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to insert address'
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