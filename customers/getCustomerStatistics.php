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
    // Create customer instance
    $customer = new Customer();
    
    // Get customer statistics
    $statistics = $customer->getCustomerStatistics();
    
    if ($statistics !== false) {
        // Calculate percentages
        $total = $statistics['total_customers'];
        $percentages = [];
        
        if ($total > 0) {
            $percentages = [
                'active_percentage' => round(($statistics['active_customers'] / $total) * 100, 2),
                'inactive_percentage' => round(($statistics['inactive_customers'] / $total) * 100, 2),
                'archived_percentage' => round(($statistics['archived_customers'] / $total) * 100, 2),
                'no_status_percentage' => round(($statistics['no_status_customers'] / $total) * 100, 2)
            ];
        } else {
            $percentages = [
                'active_percentage' => 0,
                'inactive_percentage' => 0,
                'archived_percentage' => 0,
                'no_status_percentage' => 0
            ];
        }
        
        // Combine statistics with percentages
        $result = array_merge($statistics, $percentages);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Customer statistics retrieved successfully',
            'data' => $result,
            'generated_at' => date('Y-m-d H:i:s')
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to retrieve customer statistics'
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
