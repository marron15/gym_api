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
    // Get status from query parameter
    $status = $_GET['status'] ?? null;
    
    // Validate status parameter
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
    
    // Get customers based on status
    if ($status === null) {
        // Get all customers
        $result = $customer->getAllWithAddresses();
    } else {
        // Get customers by specific status
        $result = $customer->getCustomersByStatus($status);
        
        // If we have addresses, enrich the data
        if ($result && is_array($result)) {
            foreach ($result as &$customerData) {
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
            }
        }
    }
    
    if ($result !== false && is_array($result)) {
        // Process the data to add computed fields
        $processedData = [];
        foreach ($result as $customerData) {
            // Remove password for security
            unset($customerData['password']);
            
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
                'status' => $customerData['status'],
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
            'message' => 'Customers retrieved successfully',
            'data' => $processedData,
            'filters' => [
                'status' => $status,
                'total_count' => count($processedData)
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to retrieve customer data'
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
