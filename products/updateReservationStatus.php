<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once '../class/ReservedProduct.php';
require_once '../class/AuditLog.php';

$reservedProduct = new ReservedProduct();

$input = file_get_contents('php://input');
$json = json_decode($input, true);

$reservationId = $_POST['reservation_id'] ?? ($json['reservation_id'] ?? null);
$status = $_POST['status'] ?? ($json['status'] ?? null);
$declineNote = $_POST['decline_note'] ?? ($json['decline_note'] ?? null);
$adminId = $_POST['admin_id'] ?? ($json['admin_id'] ?? null);
$adminName = $_POST['admin_name'] ?? ($json['admin_name'] ?? null);

if ($reservationId === null || $status === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

$result = $reservedProduct->updateStatus((int) $reservationId, $status, $declineNote);

if ($result['success'] ?? false) {
    try {
        $auditLog = new AuditLog();
        $details = $result['reservation'] ?? $reservedProduct->getReservationById((int)$reservationId);
        $customerId = isset($details['customer_id']) ? (int)$details['customer_id'] : null;
        $customerName = null;
        if ($details) {
            $customerName = trim(($details['customer_first_name'] ?? '') . ' ' . ($details['customer_last_name'] ?? ''));
        }
        $normalizedStatus = strtolower((string)$status);
        $productName = $details['product_name'] ?? 'Unknown Product';
        $quantity = $details['quantity'] ?? 0;
        
        // Build detailed description
        $description = ($adminName ?: 'An admin') . ' ' . $normalizedStatus . ' reservation #' . $reservationId;
        if ($customerName) {
            $description .= ' for customer: ' . $customerName;
        }
        $description .= '. Product: ' . $productName . ' (Qty: ' . $quantity . ')';
        if ($normalizedStatus === 'declined' && $declineNote) {
            $description .= '. Reason: ' . trim($declineNote);
        }
        
        $auditLog->record([
            'customer_id' => $customerId,
            'customer_name' => $customerName ?: null,
            'admin_id' => $adminId ? (int)$adminId : null,
            'actor_type' => 'admin',
            'actor_name' => $adminName ? trim((string)$adminName) : null,
            'activity_category' => 'admin',
            'activity_type' => 'reservation_' . $normalizedStatus,
            'activity_title' => 'Admin ' . ucfirst($normalizedStatus) . ' reservation',
            'description' => $description,
            'metadata' => [
                'reservation_id' => $reservationId,
                'product_id' => $details['product_id'] ?? null,
                'product_name' => $productName,
                'quantity' => $quantity,
                'previous_status' => $result['previous_status'] ?? null,
                'new_status' => $result['new_status'] ?? $normalizedStatus,
                'decline_note' => $declineNote,
            ],
        ]);
    } catch (Exception $e) {
        error_log('Audit log reservation status error: ' . $e->getMessage());
    }

    echo json_encode($result);
} else {
    http_response_code(400);
    echo json_encode($result);
}

?>

