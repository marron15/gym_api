<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once '../class/Product.php';

$products = new Products();

$input = file_get_contents('php://input');
$json = json_decode($input, true);
$id = (int)($_POST['id'] ?? ($json['id'] ?? 0));

// Restore by setting status to 'active'
$result = $products->setStatusById($id, 'active');

echo json_encode(['success' => $result]);

?>


