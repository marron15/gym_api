<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        require_once '../class/Admin.php';
        
        $admin = new Admin();
        $result = $admin->getAll();

        // Attach computed image URL for each admin if possible
        $origin = (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) ? $_SERVER['HTTP_X_FORWARDED_PROTO'] : (isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'http'))
            . '://' . $_SERVER['HTTP_HOST'];
        $basePath = rtrim(dirname($_SERVER['PHP_SELF']), '/');
        $imageEndpoint = $origin . $basePath . '/getAdminImage.php?id=';

        foreach ($result as &$row) {
            if (isset($row['id'])) {
                $row['img_url'] = $imageEndpoint . urlencode($row['id']);
            }
        }
        unset($row);

        echo json_encode($result);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error retrieving admins: ' . $e->getMessage()
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use GET.'
    ]);
}
?>
