<?php

require_once '../class/Trainers.php';

$trainers = new Trainers();

$id = (int)($_GET['id'] ?? 0);

$result = $trainers->getById($id);

echo json_encode($result);

?>
