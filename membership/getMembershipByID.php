<?php

require_once '../class/Membership.php';

$membership = new Membership();

$id = (int)($_GET['id'] ?? 0);

$result = $membership->getById($id);

echo json_encode($result);

?>
