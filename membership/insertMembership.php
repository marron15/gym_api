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

function normalize_membership_label($value) {
    $key = strtolower(trim($value));
    $key = str_replace(' ', '', $key);

    if ($key === 'daily') {
        return 'Daily';
    }

    if ($key === 'halfmonth') {
        return 'Half Month';
    }

    if ($key === 'monthly') {
        return 'Monthly';
    }

    return null;
}

$customerId = isset($_POST['customerId']) ? (int)$_POST['customerId'] : 0;
$rawMembershipType = $_POST['membershipType'] ?? '';
$membershipType = normalize_membership_label($rawMembershipType);
$startDate = $_POST['startDate'] ?? date('Y-m-d');
$expirationDate = $_POST['expirationDate'] ?? '';
$status = $_POST['status'] ?? $membershipType;
$createdBy = isset($_POST['createdBy']) ? (int)$_POST['createdBy'] : 0;
$createdAt = $_POST['createdAt'] ?? date('Y-m-d H:i:s');
$updatedAt = $_POST['updatedAt'] ?? date('Y-m-d H:i:s');
$updatedBy = isset($_POST['updatedBy']) ? (int)$_POST['updatedBy'] : 0;

if ($customerId <= 0 || $membershipType === null) {
    echo json_encode([
        'success' => false,
        'message' => 'customerId and membershipType are required'
    ]);
    exit();
}

if (empty($expirationDate)) {
    switch ($membershipType) {
        case 'Daily':
            $days = 1;
            break;
        case 'Half Month':
            $days = 15;
            break;
        case 'Monthly':
        default:
            $days = 30;
            break;
    }
    $expirationDate = date('Y-m-d', strtotime("+$days days", strtotime($startDate)));
}

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

$result = $membership->upsertByCustomerId($customerId, $data);

if ($result !== false) {
    echo json_encode([
        'success' => true,
        'data' => $result
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to save membership'
    ]);
}
?>