<?php

require_once '../class/CustomerLogs.php';

$customerLogs = new CustomerLogs();

$id = $_POST['id'] ?? "";
$customerId = $_POST['customerId'] ?? "";
$oldValue = $_POST['oldValue'] ?? "";
$newValue = $_POST['newValue'] ?? "";
$updatedAt = date('Y-m-d H:i:s');


$data = [
    'id' => $id,
    'customerId' => $customerId,
    'oldValue' => $oldValue,
    'newValue' => $newValue,
    'updatedAt' => $updatedAt,
];

$result = $customerLogs->store($data);

echo json_encode($result);
?>  