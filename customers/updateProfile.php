<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../class/Customer.php';
require_once '../class/AuditLog.php';

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Only POST requests are accepted.'
    ]);
    exit();
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate input
    if (!$input) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid JSON input'
        ]);
        exit();
    }
    
    // Require only customer_id; allow partial updates for other fields
    if (empty($input['customer_id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Customer ID is required'
        ]);
        exit();
    }
    
    $customerId = (int)$input['customer_id'];
    
    // If email provided, validate format
    if (isset($input['email']) && strlen(trim($input['email'])) > 0) {
        if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid email format'
            ]);
            exit();
        }
    }
    
    // Prepare data for update
    // Fetch current values to backfill
$customer = new Customer();
$currentRows = $customer->getById($customerId);
    $current = ($currentRows && count($currentRows) > 0) ? $currentRows[0] : [];

    function valOrNull($arr, $key) { return isset($arr[$key]) && strlen(trim($arr[$key])) > 0 ? trim($arr[$key]) : null; }
function normalizeAuditValue($value) {
    if ($value === null) {
        return null;
    }

    if (is_string($value)) {
        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }

    return $value;
}

function addAuditChange(array &$changes, string $field, $old, $new): void {
    $normalizedOld = normalizeAuditValue($old);
    $normalizedNew = normalizeAuditValue($new);

    if ($normalizedOld !== $normalizedNew) {
        $changes[$field] = [
            'old' => $normalizedOld,
            'new' => $normalizedNew,
        ];
    }
}

    $data = [
        'firstName' => valOrNull($input, 'first_name') ?? ($current['first_name'] ?? null),
        'middleName' => valOrNull($input, 'middle_name') ?? ($current['middle_name'] ?? null),
        'lastName' => valOrNull($input, 'last_name') ?? ($current['last_name'] ?? null),
        'email' => strtolower(valOrNull($input, 'email') ?? ($current['email'] ?? '')),
        'birthdate' => valOrNull($input, 'birthdate') ?? ($current['birthdate'] ?? null),
        'phoneNumber' => valOrNull($input, 'phone_number') ?? ($current['phone_number'] ?? null),
        'emergencyContactName' => valOrNull($input, 'emergency_contact_name') ?? ($current['emergency_contact_name'] ?? null),
        'emergencyContactNumber' => valOrNull($input, 'emergency_contact_number') ?? ($current['emergency_contact_number'] ?? null),
        'updatedBy' => 'customer_update',
        'updatedAt' => date('Y-m-d H:i:s'),
        'img' => valOrNull($input, 'img') ?? ($current['img'] ?? null),
        'status' => $current['status'] ?? 'active'
    ];

    // Enforce 11-digit phone number when provided
    if ($data['phoneNumber'] !== null) {
        if (!preg_match('/^\d{11}$/', $data['phoneNumber'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Contact number must be exactly 11 digits'
            ]);
            exit();
        }
    }
    
    // Handle password update if provided
    if (isset($input['password']) && !empty(trim($input['password']))) {
        $data['password'] = trim($input['password']);
    }
    
    // Create customer instance and attempt update
$result = $customer->updateCustomersByID($customerId, $data);
    
    if ($result) {
        // Also update address if provided
        $addressUpdated = true;
        $addressStringForLogging = null;
        if (isset($input['address_details']) && is_array($input['address_details'])) {
            // Convert address array to string format for the updateCustomerAddress method
            $addressString = implode(', ', array_filter([
                $input['address_details']['street'] ?? '',
                $input['address_details']['city'] ?? '',
                $input['address_details']['state'] ?? '',
                $input['address_details']['postal_code'] ?? '',
                $input['address_details']['country'] ?? ''
            ]));
            $addressStringForLogging = $addressString;
            $addressUpdated = $customer->updateCustomerAddress($customerId, $addressString);
        }

        $changes = [];
        addAuditChange($changes, 'first_name', $current['first_name'] ?? null, $data['firstName']);
        addAuditChange($changes, 'middle_name', $current['middle_name'] ?? null, $data['middleName']);
        addAuditChange($changes, 'last_name', $current['last_name'] ?? null, $data['lastName']);
        addAuditChange($changes, 'email', $current['email'] ?? null, $data['email']);
        addAuditChange($changes, 'birthdate', $current['birthdate'] ?? null, $data['birthdate']);
        addAuditChange($changes, 'phone_number', $current['phone_number'] ?? null, $data['phoneNumber']);
        addAuditChange(
            $changes,
            'emergency_contact_name',
            $current['emergency_contact_name'] ?? null,
            $data['emergencyContactName']
        );
        addAuditChange(
            $changes,
            'emergency_contact_number',
            $current['emergency_contact_number'] ?? null,
            $data['emergencyContactNumber']
        );

        if ($addressStringForLogging !== null) {
            $changes['address'] = [
                'old' => $current['address'] ?? null,
                'new' => $addressStringForLogging,
            ];
        }

        if (!empty($changes)) {
            $sections = [];
            $personalFields = ['first_name', 'middle_name', 'last_name', 'email', 'birthdate', 'phone_number'];
            $emergencyFields = ['emergency_contact_name', 'emergency_contact_number'];

            if (count(array_intersect(array_keys($changes), $personalFields)) > 0) {
                $sections[] = 'personal_information';
            }
            if (count(array_intersect(array_keys($changes), $emergencyFields)) > 0) {
                $sections[] = 'emergency_contact';
            }
            if (isset($changes['address'])) {
                $sections[] = 'address';
            }

            $customerName = trim(($current['first_name'] ?? '') . ' ' . ($current['last_name'] ?? ''));
            try {
                $auditLog = new AuditLog();
                $auditLog->record([
                    'customer_id' => $customerId,
                    'customer_name' => $customerName ?: null,
                    'activity_category' => 'profile',
                    'activity_type' => 'profile_update',
                    'activity_title' => 'Customer updated profile',
                    'description' => $sections
                        ? 'Updated ' . implode(', ', str_replace('_', ' ', $sections))
                        : 'Profile updated',
                    'metadata' => [
                        'sections' => $sections,
                        'changes' => $changes,
                        'initiated_by' => 'customer',
                    ],
                    'actor_type' => 'customer',
                    'actor_name' => $customerName ?: null,
                ]);
            } catch (Exception $e) {
                error_log('Audit log profile update error: ' . $e->getMessage());
            }
        }
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => [
                'customer_id' => $customerId,
                'updated_at' => $data['updatedAt'],
                'address_updated' => $addressUpdated
            ]
        ], JSON_PRETTY_PRINT);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update profile data'
        ], JSON_PRETTY_PRINT);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>
