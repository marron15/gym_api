<?php

require_once '../class/CustomerAddress.php';

$address = new CustomerAddress();

$id = (int)($_POST['id'] ?? 0);

$result = $address->deleteById($id);

echo json_encode($result);

?>
