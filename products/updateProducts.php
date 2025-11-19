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
$quantity = (int)($_POST['quantity'] ?? ($json['quantity'] ?? 0));
$quantity = max(0, $quantity);
$data = [
  'name' => $_POST['name'] ?? ($json['name'] ?? ''),
  'description' => $_POST['description'] ?? ($json['description'] ?? ''),
  'status' => $_POST['status'] ?? ($json['status'] ?? 1),
  'quantity' => $quantity,
  'img' => $_POST['img'] ?? ($json['img'] ?? ''),
  'updatedBy' => 1,
  'updatedAt' => date('Y-m-d H:i:s'),
];

$result = $products->updateProductsByID($id, $data);

echo json_encode(['success' => $result]);

?>


