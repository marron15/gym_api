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

// Trigger notification check for this membership after update
if ($result) {
    try {
        require_once '../class/MembershipNotification.php';
        $notification = new MembershipNotification();
        
        // Get the updated membership data
        $membershipData = $membership->getById($id);
        if ($membershipData && count($membershipData) > 0) {
            $membershipData = $membershipData[0];
            $expirationDate = $membershipData['expiration_date'];
            $today = date('Y-m-d');
            $threeDaysFromNow = date('Y-m-d', strtotime('+3 days'));
            $expirationTimestamp = strtotime($expirationDate);
            $todayTimestamp = strtotime($today);
            $threeDaysTimestamp = strtotime($threeDaysFromNow);
            
            // Get customer data for notification
            require_once '../class/Customer.php';
            $customer = new Customer();
            $customerData = $customer->getById($membershipData['customer_id']);
            
            if ($customerData && count($customerData) > 0) {
                $customerData = $customerData[0];
                $membershipData['email'] = $customerData['email'] ?? '';
                $membershipData['first_name'] = $customerData['first_name'] ?? '';
                $membershipData['last_name'] = $customerData['last_name'] ?? '';
                $membershipData['middle_name'] = $customerData['middle_name'] ?? '';
                $membershipData['membership_id'] = $membershipData['id'];
                
                // Check if membership expires within 3 days or has expired
                if ($expirationTimestamp <= $threeDaysTimestamp && $expirationTimestamp >= $todayTimestamp) {
                    // Within 3 days - send 3 days left notification
                    $notification->sendThreeDaysLeftNotification($membershipData);
                } elseif ($expirationTimestamp < $todayTimestamp) {
                    // Already expired - send expired notification
                    $notification->sendExpiredNotification($membershipData);
                }
            }
        }
    } catch (Exception $e) {
        // Don't fail the membership update if notification fails
        error_log('Notification error in updateMembership: ' . $e->getMessage());
    }
}

echo json_encode($result);

?>

