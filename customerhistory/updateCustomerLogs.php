<?php

require_once '../class/CustomerLogs.php';

$customerLogs = new CustomerLogs();

$id = (int)($_POST['id'] ?? 0);
$customerId = $_POST['customerId'] ?? 0;
$oldValue = $_POST['oldValue'] ?? "";
$newValue = $_POST['newValue'] ?? "";
$updatedAt = date('Y-m-d H:i:s');
$updatedBy = 1;

$data = [
    'id' => $id,
    'customerId' => $customerId,
    'oldValue' => $oldValue,
    'newValue' => $newValue,
    'updatedAt' => $updatedAt,
    'updatedBy' => $updatedBy,
];

$result = $customerLogs->updateById($id, $data);

echo json_encode($result);
?>
