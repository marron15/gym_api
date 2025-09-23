<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../class/Customer.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }
if ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(405); echo json_encode(['success'=>false,'message'=>'Method not allowed']); exit(); }

try {
    $customer = new Customer();
    $data = $customer->getNewMembersByWeekOfMonth();
    echo json_encode(['success'=>true,'data'=>$data]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Server error: '.$e->getMessage()]);
}
?>


