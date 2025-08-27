<?php

require_once '../class/Membership.php';

$membership = new Membership();

$id = (int)($_POST['id'] ?? 0);

$result = $membership->deleteById($id);

echo json_encode($result);

?>
