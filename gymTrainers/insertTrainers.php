<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once '../class/Trainers.php';

$trainers = new Trainers();

// accept JSON payload as well as form-data
$input = file_get_contents('php://input');
$json = json_decode($input, true);

$firstName = $_POST['firstName'] ?? ($json['firstName'] ?? "");
$middleName = $_POST['middleName'] ?? ($json['middleName'] ?? "");
$lastName = $_POST['lastName'] ?? ($json['lastName'] ?? "");
$contactNumber = $_POST['contactNumber'] ?? ($json['contactNumber'] ?? "");
$status = 'active';
$createdBy = 1;
$createdAt = date('Y-m-d H:i:s');
$updatedAt = date('Y-m-d H:i:s');
$updatedBy = 1;

if ($firstName === '' || $lastName === '' || $contactNumber === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$data = [
    'firstName' => $firstName,
    'middleName' => $middleName,
    'lastName' => $lastName,
    'contactNumber' => $contactNumber,
    'status' => $status,
    'createdBy' => $createdBy,
    'createdAt' => $createdAt,
    'updatedAt' => $updatedAt,
    'updatedBy' => $updatedBy,
];

$result = $trainers->store($data);

echo json_encode(['success' => $result]);
?>