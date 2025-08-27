<?php

require_once '../class/CustomerLogs.php';

$customerLogs = new CustomerLogs();

$id = (int)($_GET['id'] ?? 0);

$result = $customerLogs->getById($id);

echo json_encode($result);

?>
