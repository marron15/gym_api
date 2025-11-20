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

$input = file_get_contents('php://input');
$json = json_decode($input, true);

$customerId = $_POST['customer_id'] ?? ($json['customer_id'] ?? null);
$productId = $_POST['product_id'] ?? ($json['product_id'] ?? null);
$quantity = $_POST['quantity'] ?? ($json['quantity'] ?? null);
$notes = $_POST['notes'] ?? ($json['notes'] ?? '');

if ($customerId === null || $productId === null || $quantity === null) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields.',
    ]);
    exit;
}

$quantity = (int) $quantity;
$customerId = (int) $customerId;
$productId = (int) $productId;

if ($quantity <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Quantity must be greater than zero.',
    ]);
    exit;
}

$result = $reservedProduct->createReservation($customerId, $productId, $quantity, $notes);

if ($result['success'] ?? false) {
    echo json_encode($result);
} else {
    http_response_code(400);
    echo json_encode($result);
}

?>

