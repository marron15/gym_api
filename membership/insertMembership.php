<?php

require_once '../class/Membership.php';

$membership = new Membership();

$description = $_POST['description'] ?? "";
$price = $_POST['price'] ?? "";
$createdBy = 1;
$createdAt = date('Y-m-d H:i:s');
$updatedAt = date('Y-m-d H:i:s');
$updatedBy = 1;
$status = (int)($_POST['status'] ?? 0);


$data = [
    'description' => $description,
    'price' => $price,
    'createdBy' => $createdBy,
    'createdAt' => $createdAt,
    'updatedAt' => $updatedAt,
    'updatedBy' => $updatedBy,
    'status' => $status,
];

$result = $membership->store($data);

echo json_encode($result);
?>