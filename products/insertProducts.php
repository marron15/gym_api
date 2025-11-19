<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once '../class/Product.php';

$products = new Products();

$input = file_get_contents('php://input');
$json = json_decode($input, true);

$name = $_POST['name'] ?? ($json['name'] ?? '');
$description = $_POST['description'] ?? ($json['description'] ?? '');
$status = 1; // active by default
$img = $_POST['img'] ?? ($json['img'] ?? '');
$quantity = (int)($_POST['quantity'] ?? ($json['quantity'] ?? 0));
$quantity = max(0, $quantity);
$createdBy = 1;
$createdAt = date('Y-m-d H:i:s');

if ($name === '' || $description === '' || $img === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// If image is a data URL or base64, persist to disk and save path instead
if (!empty($img)) {
    $isDataUrl = strpos($img, 'data:') === 0;
    $base64Data = $isDataUrl ? explode(',', $img, 2)[1] : $img;
    $decoded = base64_decode($base64Data, true);
    if ($decoded !== false) {
        $uploadDir = dirname(__DIR__) . '/uploads/products';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0775, true);
        }
        // Determine file extension from mime
        $ext = 'png';
        if ($isDataUrl && preg_match('/^data:(.*?);base64,/', $img, $m)) {
            $mime = $m[1];
            if ($mime === 'image/jpeg') $ext = 'jpg';
            elseif ($mime === 'image/webp') $ext = 'webp';
            elseif ($mime === 'image/png') $ext = 'png';
        }
        $fileName = uniqid('prod_', true) . '.' . $ext;
        $filePath = $uploadDir . '/' . $fileName;
        if (@file_put_contents($filePath, $decoded) !== false) {
            // Store relative path for serving via Apache
            $img = 'uploads/products/' . $fileName;
        }
    }
}

$data = [
    'name' => $name,
    'description' => $description,
    'status' => $status,
    'quantity' => $quantity,
    'img' => $img,
    'createdBy' => $createdBy,
    'createdAt' => $createdAt,
];

$result = $products->store($data);

if ($result) {
    echo json_encode(['success' => true, 'message' => 'Product created']);
} else {
    http_response_code(200);
    echo json_encode(['success' => false, 'message' => 'DB insert failed']);
}

?>


