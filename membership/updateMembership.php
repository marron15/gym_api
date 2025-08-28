<?php

require_once '../class/Membership.php';

$membership = new Membership();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(false);
    return;
}

// Pull existing record to preserve immutable fields when not provided
$existing = $membership->getById($id);
$existing = is_array($existing) && count($existing) > 0 ? $existing[0] : null;

$customerId = $_POST['customerId'] ?? ($existing['customer_id'] ?? "");
$membershipType = $_POST['membershipType'] ?? ($existing['membership_type'] ?? "");
$startDate = $_POST['startDate'] ?? ($existing['start_date'] ?? "");
$expirationDate = $_POST['expirationDate'] ?? ($existing['expiration_date'] ?? "");
$status = $_POST['status'] ?? ($existing['status'] ?? "");

// Keep created fields if not explicitly sent
$createdBy = $_POST['createdBy'] ?? ($existing['created_by'] ?? 1);
$createdAt = $_POST['createdAt'] ?? ($existing['created_at'] ?? date('Y-m-d H:i:s'));

$updatedAt = date('Y-m-d H:i:s');
$updatedBy = 1;

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

$result = $membership->updateServicesByID($id, $data);

echo json_encode($result);

?>

