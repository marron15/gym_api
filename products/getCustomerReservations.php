<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once '../class/ReservedProduct.php';

$reservedProduct = new ReservedProduct();

// Get customer_id from query parameter
$customerId = $_GET['customer_id'] ?? null;

if (!$customerId) {
    echo json_encode([
        'success' => false,
        'message' => 'Customer ID is required.',
        'data' => []
    ]);
    exit;
}

// Validate customer_id is numeric
if (!is_numeric($customerId)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid customer ID.',
        'data' => []
    ]);
    exit;
}

$status = $_GET['status'] ?? null;

$data = $reservedProduct->getReservationsByCustomer((int)$customerId, $status);

echo json_encode($data);

?>

