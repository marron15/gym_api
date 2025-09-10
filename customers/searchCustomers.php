<?php
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
    // Get search parameters
    $query = $_GET['q'] ?? '';
    $status = $_GET['status'] ?? null;
    
    // Validate search query
    if (empty($query) || strlen(trim($query)) < 2) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Search query must be at least 2 characters long'
        ]);
        exit();
    }
    
    // Validate status parameter if provided
    if ($status !== null) {
        $validStatuses = ['active', 'inactive', 'archived'];
        if (!in_array($status, $validStatuses)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid status. Must be one of: active, inactive, archived'
            ]);
            exit();
        }
    }
    
    // Create customer instance
    $customer = new Customer();
    
    // Search customers
    $result = $customer->searchCustomers($query, $status);
    
    if ($result !== false && is_array($result)) {
        // Process the data to add computed fields
        $processedData = [];
        foreach ($result as $customerData) {
            // Remove password for security
            unset($customerData['password']);
            
            // Add computed full_name field
            $customerData['full_name'] = trim($customerData['first_name'] . ' ' . $customerData['last_name']);
            
            // Get address details for each customer
            $addressDetails = $customer->getCustomerAddressDetails($customerData['id']);
            if ($addressDetails) {
                $customerData['address'] = $addressDetails['street'] . ', ' . 
                                         $addressDetails['city'] . ', ' . 
                                         $addressDetails['state'] . ', ' . 
                                         $addressDetails['postal_code'] . ', ' . 
                                         $addressDetails['country'];
                $customerData['address_details'] = $addressDetails;
            } else {
                $customerData['address'] = null;
                $customerData['address_details'] = null;
            }
            
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
                'status' => $customerData['status'],
                'created_at' => $customerData['created_at'],
                'updated_at' => $customerData['updated_at'],
                'address' => $customerData['address'],
                'address_details' => $customerData['address_details']
            ];

            $processedData[] = $processedCustomer;
        }
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Customer search completed successfully',
            'data' => $processedData,
            'search_info' => [
                'query' => $query,
                'status_filter' => $status,
                'total_results' => count($processedData),
                'search_performed_at' => date('Y-m-d H:i:s')
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to perform customer search'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error: ' . $e->getMessage()
    ]);
}
?>
