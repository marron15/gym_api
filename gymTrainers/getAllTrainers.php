<?php

require_once '../class/Trainers.php';

$trainers = new Trainers();

$result = $trainers->getAll();
echo json_encode($result);

?>
