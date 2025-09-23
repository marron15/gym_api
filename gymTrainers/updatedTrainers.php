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

// accept JSON or form-encoded
$input = file_get_contents('php://input');
$json = json_decode($input, true);

$id = (int)($_POST['id'] ?? ($json['id'] ?? 0));
$data = [
    'firstName' => $_POST['firstName'] ?? ($json['firstName'] ?? ''),
    'middleName' => $_POST['middleName'] ?? ($json['middleName'] ?? ''),
    'lastName' => $_POST['lastName'] ?? ($json['lastName'] ?? ''),
    'contactNumber' => $_POST['contactNumber'] ?? ($json['contactNumber'] ?? ''),
    'createdBy' => 1,
    'createdAt' => date('Y-m-d H:i:s'),
    'updatedAt' => date('Y-m-d H:i:s'),
    'updatedBy' => 1,
];

$result = $trainers->updateServicesByID($id, $data);

echo json_encode(['success' => $result]);

?>
