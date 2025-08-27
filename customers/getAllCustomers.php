<?php
// Suppress any output before JSON
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../class/Customer.php';

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Only GET requests are accepted.'
    ]);
    exit();
}

try {
    $customer = new Customer();
    // Fetch customers including their address details
    $result = $customer->getAllWithAddresses();
    
    if ($result !== false && is_array($result)) {
        // Process the data to add computed fields like full_name and format properly
        $processedData = [];
        foreach ($result as $customerData) {
            // Password is already removed in getAllWithAddresses()
            
            // Add computed full_name field
            $customerData['full_name'] = trim($customerData['first_name'] . ' ' . $customerData['last_name']);
            
            // Ensure consistent field naming for the API
            $processedCustomer = [
                'customer_id' => $customerData['id'],
                'email' => $customerData['email'],
                'first_name' => $customerData['first_name'],
                'last_name' => $customerData['last_name'],
                'middle_name' => $customerData['middle_name'],
                'full_name' => $customerData['full_name'],
                'phone_number' => $customerData['phone_number'],
                'birthdate' => $customerData['birthdate'],
                'emergency_contact_name' => $customerData['emergency_contact_name'],
                'emergency_contact_number' => $customerData['emergency_contact_number'],
                'img' => $customerData['img'],
                'created_at' => $customerData['created_at'],
                'updated_at' => $customerData['updated_at']
            ];
            
            // Attach address fields when available
            if (isset($customerData['address'])) {
                $processedCustomer['address'] = $customerData['address'];
            } else {
                $processedCustomer['address'] = null;
            }

            if (isset($customerData['address_details'])) {
                $processedCustomer['address_details'] = $customerData['address_details'];
            } else {
                $processedCustomer['address_details'] = null;
            }

            $processedData[] = $processedCustomer;
        }
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Customer retrieved successfully',
            'data' => $processedData
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to retrieve customer data'
        ], JSON_PRETTY_PRINT);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error: ' . $e->getMessage(),
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>
