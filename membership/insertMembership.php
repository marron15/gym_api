<?php
// CORS headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../class/Membership.php';

$membership = new Membership();

// PHP receives form-encoded fields from Flutter
$customerId = $_POST['customerId'] ?? "";
$membershipType = $_POST['membershipType'] ?? "";
$startDate = $_POST['startDate'] ?? "";
$expirationDate = $_POST['expirationDate'] ?? "";
$status = $_POST['status'] ?? "";
$createdBy = $_POST['createdBy'] ?? 1;
$createdAt = $_POST['createdAt'] ?? date('Y-m-d H:i:s');
$updatedAt = $_POST['updatedAt'] ?? date('Y-m-d H:i:s');
$updatedBy = $_POST['updatedBy'] ?? 1;

$data = [
    'customerId' => $customerId,
    'membershipType' => $membershipType,
    'startDate' => $startDate,
    'expirationDate' => $expirationDate,
    'status' => $status,
    'createdBy' => $createdBy,
    'createdAt' => $createdAt,
    'updatedAt' => $updatedAt,
    'updatedBy' => $updatedBy,
];

$result = $membership->store($data);

echo json_encode(['success' => (bool)$result]);
?>