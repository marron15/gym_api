<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../class/Customer.php';

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
    
    // Check required fields
    if (empty($input['id']) || empty($input['first_name']) || empty($input['last_name']) || empty($input['email'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'ID, first name, last name, and email are required'
        ]);
        exit();
    }
    
    // Validate Philippine mobile number format if provided
    if (!empty($input['phone_number'])) {
        // Clean the phone number (remove all non-digit characters)
        $cleanPhone = preg_replace('/[^\d]/', '', $input['phone_number']);
        
        // Check if it's exactly 11 digits and starts with 0
        if (strlen($cleanPhone) !== 11 || !str_starts_with($cleanPhone, '0')) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Phone number must be exactly 11 digits and start with 0 (Philippine mobile format)'
            ]);
            exit();
        }
        
        // Validate Philippine mobile prefixes
        $prefix = substr($cleanPhone, 0, 4);
        $validPrefixes = [
            // Globe/TM
            '0905', '0906', '0907', '0908', '0909', '0910', '0911', '0912', '0913', '0914', '0915', '0916', '0917', '0918', '0919',
            '0920', '0921', '0922', '0923', '0924', '0925', '0926', '0927', '0928', '0929', '0930', '0931', '0932', '0933', '0934', '0935', '0936', '0937', '0938', '0939',
            '0940', '0941', '0942', '0943', '0944', '0945', '0946', '0947', '0948', '0949', '0950', '0951', '0952', '0953', '0954', '0955', '0956', '0957', '0958', '0959',
            '0960', '0961', '0962', '0963', '0964', '0965', '0966', '0967', '0968', '0969', '0970', '0971', '0972', '0973', '0974', '0975', '0976', '0977', '0978', '0979',
            '0980', '0981', '0982', '0983', '0984', '0985', '0986', '0987', '0988', '0989', '0990', '0991', '0992', '0993', '0994', '0995', '0996', '0997', '0998', '0999',
            // Smart/Sun
            '0813', '0817', '0821', '0823', '0824', '0825', '0826', '0827', '0828', '0829', '0830', '0831', '0832', '0833', '0834', '0835', '0836', '0837', '0838', '0839',
            '0840', '0841', '0842', '0843', '0844', '0845', '0846', '0847', '0848', '0849', '0850', '0851', '0852', '0853', '0854', '0855', '0856', '0857', '0858', '0859',
            '0860', '0861', '0862', '0863', '0864', '0865', '0866', '0867', '0868', '0869', '0870', '0871', '0872', '0873', '0874', '0875', '0876', '0877', '0878', '0879',
            '0880', '0881', '0882', '0883', '0884', '0885', '0886', '0887', '0888', '0889', '0890', '0891', '0892', '0893', '0894', '0895', '0896', '0897', '0898', '0899',
            // DITO
            '0895', '0896', '0897', '0898', '0899',
            // GOMO
            '0927', '0928', '0929',
        ];
        
        if (!in_array($prefix, $validPrefixes)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid Philippine mobile number prefix. Must be a valid Globe, Smart, DITO, or GOMO number'
            ]);
            exit();
        }
        
        // Update the input with cleaned phone number
        $input['phone_number'] = $cleanPhone;
    }
    
    // Validate emergency contact number format if provided
    if (!empty($input['emergency_contact_number'])) {
        // Clean the emergency contact number (remove all non-digit characters)
        $cleanEmergencyPhone = preg_replace('/[^\d]/', '', $input['emergency_contact_number']);
        
        // Check if it's exactly 11 digits and starts with 0
        if (strlen($cleanEmergencyPhone) !== 11 || !str_starts_with($cleanEmergencyPhone, '0')) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Emergency contact number must be exactly 11 digits and start with 0 (Philippine mobile format)'
            ]);
            exit();
        }
        
        // Validate Philippine mobile prefixes for emergency contact
        $emergencyPrefix = substr($cleanEmergencyPhone, 0, 4);
        $validPrefixes = [
            // Globe/TM
            '0905', '0906', '0907', '0908', '0909', '0910', '0911', '0912', '0913', '0914', '0915', '0916', '0917', '0918', '0919',
            '0920', '0921', '0922', '0923', '0924', '0925', '0926', '0927', '0928', '0929', '0930', '0931', '0932', '0933', '0934', '0935', '0936', '0937', '0938', '0939',
            '0940', '0941', '0942', '0943', '0944', '0945', '0946', '0947', '0948', '0949', '0950', '0951', '0952', '0953', '0954', '0955', '0956', '0957', '0958', '0959',
            '0960', '0961', '0962', '0963', '0964', '0965', '0966', '0967', '0968', '0969', '0970', '0971', '0972', '0973', '0974', '0975', '0976', '0977', '0978', '0979',
            '0980', '0981', '0982', '0983', '0984', '0985', '0986', '0987', '0988', '0989', '0990', '0991', '0992', '0993', '0994', '0995', '0996', '0997', '0998', '0999',
            // Smart/Sun
            '0813', '0817', '0821', '0823', '0824', '0825', '0826', '0827', '0828', '0829', '0830', '0831', '0832', '0833', '0834', '0835', '0836', '0837', '0838', '0839',
            '0840', '0841', '0842', '0843', '0844', '0845', '0846', '0847', '0848', '0849', '0850', '0851', '0852', '0853', '0854', '0855', '0856', '0857', '0858', '0859',
            '0860', '0861', '0862', '0863', '0864', '0865', '0866', '0867', '0868', '0869', '0870', '0871', '0872', '0873', '0874', '0875', '0876', '0877', '0878', '0879',
            '0880', '0881', '0882', '0883', '0884', '0885', '0886', '0887', '0888', '0889', '0890', '0891', '0892', '0893', '0894', '0895', '0896', '0897', '0898', '0899',
            // DITO
            '0895', '0896', '0897', '0898', '0899',
            // GOMO
            '0927', '0928', '0929',
        ];
        
        if (!in_array($emergencyPrefix, $validPrefixes)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid Philippine mobile number prefix for emergency contact. Must be a valid Globe, Smart, DITO, or GOMO number'
            ]);
            exit();
        }
        
        // Update the input with cleaned emergency contact number
        $input['emergency_contact_number'] = $cleanEmergencyPhone;
    }
    
    $id = (int)$input['id'];
    
    // Validate email format
    if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid email format'
        ]);
        exit();
    }
    
    // Prepare data for update (admin can update password)
    $data = [
        'firstName' => trim($input['first_name']),
        'middleName' => isset($input['middle_name']) && !empty(trim($input['middle_name'])) ? trim($input['middle_name']) : null,
        'lastName' => trim($input['last_name']),
        'email' => strtolower(trim($input['email'])),
        'birthdate' => isset($input['birthdate']) && !empty(trim($input['birthdate'])) ? trim($input['birthdate']) : null,
        'phoneNumber' => isset($input['phone_number']) && !empty(trim($input['phone_number'])) ? trim($input['phone_number']) : null,
        'emergencyContactName' => isset($input['emergency_contact_name']) && !empty(trim($input['emergency_contact_name'])) ? trim($input['emergency_contact_name']) : null,
        'emergencyContactNumber' => isset($input['emergency_contact_number']) && !empty(trim($input['emergency_contact_number'])) ? trim($input['emergency_contact_number']) : null,
        'updatedBy' => 'admin_update',
        'updatedAt' => date('Y-m-d H:i:s'),
        'img' => isset($input['img']) && !empty(trim($input['img'])) ? trim($input['img']) : null
    ];

    if (!empty($input['membership_type']) || !empty($input['membershipType'])) {
        $data['membershipType'] = $input['membership_type'] ?? $input['membershipType'];
        if (!empty($input['membership_start_date']) || !empty($input['membershipStartDate'])) {
            $data['membershipStartDate'] = $input['membership_start_date'] ?? $input['membershipStartDate'];
        }
        if (!empty($input['membership_expiration_date']) || !empty($input['membershipExpirationDate'])) {
            $data['membershipExpirationDate'] = $input['membership_expiration_date'] ?? $input['membershipExpirationDate'];
        }
    }
    
    // Handle password update if provided
    if (isset($input['password']) && !empty(trim($input['password']))) {
        $data['password'] = trim($input['password']);
    }
    
    // Create customer instance and attempt update
    $customer = new Customer();
    $result = $customer->updateCustomersByID($id, $data);
    
    if ($result) {
        // Also update address if provided
        $addressUpdated = true;
        if (isset($input['address_details']) && is_array($input['address_details'])) {
            // Convert address array to string format for the updateCustomerAddress method
            $addressString = implode(', ', array_filter([
                $input['address_details']['street'] ?? '',
                $input['address_details']['city'] ?? '',
                $input['address_details']['state'] ?? '',
                $input['address_details']['postal_code'] ?? '',
                $input['address_details']['country'] ?? ''
            ]));
            $addressUpdated = $customer->updateCustomerAddress($id, $addressString);
        }
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Customer updated successfully',
            'data' => [
                'customer_id' => $id,
                'updated_at' => $data['updatedAt'],
                'address_updated' => $addressUpdated
            ]
        ], JSON_PRETTY_PRINT);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update customer data'
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
