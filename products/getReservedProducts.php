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

$status = $_GET['status'] ?? null;

$data = $reservedProduct->getReservations($status);

echo json_encode($data);

?>

