<?php

require_once '../class/Customer.php';

$customer = new Customer();

$id = (int)($_GET['id'] ?? 0);

$result = $customer->getById($id);

echo json_encode($result);

?>
