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

$reservationId = $_POST['reservation_id'] ?? ($json['reservation_id'] ?? null);
$status = $_POST['status'] ?? ($json['status'] ?? null);

if ($reservationId === null || $status === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

$result = $reservedProduct->updateStatus((int) $reservationId, $status);

if ($result['success'] ?? false) {
    echo json_encode($result);
} else {
    http_response_code(400);
    echo json_encode($result);
}

?>

