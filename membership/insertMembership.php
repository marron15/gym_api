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
    // Trigger notification check for this membership
    try {
        require_once '../class/MembershipNotification.php';
        $notification = new MembershipNotification();
        
        // Get the membership data to check if notification is needed
        $membershipData = $membership->getByCustomerId($customerId);
        if ($membershipData) {
            // Get customer data for notification
            require_once '../class/Customer.php';
            $customer = new Customer();
            $customerData = $customer->getById($customerId);
            
            if ($customerData && count($customerData) > 0) {
                $customerData = $customerData[0];
                $membershipData['email'] = $customerData['email'] ?? '';
                $membershipData['first_name'] = $customerData['first_name'] ?? '';
                $membershipData['last_name'] = $customerData['last_name'] ?? '';
                $membershipData['middle_name'] = $customerData['middle_name'] ?? '';
                $membershipData['membership_id'] = $membershipData['id'];
                
                // Only send if customer has email
                if (!empty($membershipData['email'])) {
                    $expirationDate = $membershipData['expiration_date'];
                    $today = date('Y-m-d');
                    $threeDaysFromNow = date('Y-m-d', strtotime('+3 days'));
                    $expirationTimestamp = strtotime($expirationDate);
                    $todayTimestamp = strtotime($today);
                    $threeDaysTimestamp = strtotime($threeDaysFromNow);
                    
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
        }
    } catch (Exception $e) {
        // Don't fail the membership creation if notification fails
        error_log('Notification error in insertMembership: ' . $e->getMessage());
    }
    
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