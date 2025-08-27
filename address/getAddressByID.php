<?php

require_once '../class/CustomerAddress.php';

$address = new CustomerAddress();

$id = (int)($_GET['id'] ?? 0);

$result = $address->getById($id);

echo json_encode($result);

?>
