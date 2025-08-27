<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

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

    $customerId = $_POST['customer_id'] ?? 0;
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

    $result = $address->store($data);

    if ($result) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Address inserted successfully',
            'data' => [
                'customer_id' => $customerId,
                'address_id' => $address->conn->lastInsertId()
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