<?php

require_once '../class/Trainers.php';

$trainers = new Trainers();

$id = (int)($_POST['id'] ?? 0);

$result = $trainers->deleteById($id);

echo json_encode($result);

?>
