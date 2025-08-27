<?php

require_once '../class/Membership.php';

$membership = new Membership();

$result = $membership->getAll();
echo json_encode($result);

?>
