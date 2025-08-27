<?php

require_once '../class/CustomersAddress.php';

$address = new CustomersAddress();

$result = $address->getAll();
echo json_encode($result);

?>
