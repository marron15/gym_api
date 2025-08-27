<?php

require_once '../class/CustomerLogs.php';

$customerLogs = new CustomerLogs();

$result = $customerLogs->getAll();
echo json_encode($result);

?>
