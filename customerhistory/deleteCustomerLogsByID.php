<?php

require_once '../class/CustomerLogs.php';

$customerLogs = new CustomerLogs();

$id = (int)($_POST['id'] ?? 0);

$result = $customerLogs->deleteById($id);

echo json_encode($result);

?>
