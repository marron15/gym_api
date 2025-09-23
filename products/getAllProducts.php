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
// Optional filter by status: /getAllProducts.php?status=active|inactive (or numeric 1|0)
$status = isset($_GET['status']) ? $_GET['status'] : null;
if ($status !== null && $status !== '') {
  $result = $products->getByStatus($status);
} else {
  $result = $products->getAll();
}

echo json_encode($result);

?>


