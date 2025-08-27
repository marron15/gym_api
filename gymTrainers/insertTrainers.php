<?php

require_once '../class/Trainers.php';

$trainers = new Trainers();

$firstName = $_POST['firstName'] ?? "";
$middleName = $_POST['middleName'] ?? "";
$lastName = $_POST['lastName'] ?? "";
$contactNumber = $_POST['contactNumber'] ?? "";
$createdBy = 1;
$createdAt = date('Y-m-d H:i:s');
$updatedAt = date('Y-m-d H:i:s');
$updatedBy = 1;


$data = [
    'firstName' => $firstName,
    'middleName' => $middleName,
    'lastName' => $lastName,
    'contactNumber' => $contactNumber,
    'createdBy' => $createdBy,
    'createdAt' => $createdAt,
    'updatedAt' => $updatedAt,
    'updatedBy' => $updatedBy,
];

    $result = $trainers->store($data);

echo json_encode($result);
?>