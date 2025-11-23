<?php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

require_once __DIR__ . '/../class/Customer.php';
require_once __DIR__ . '/../class/SignupVerification.php';
require_once __DIR__ . '/../class/Mailer.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Only POST is accepted.',
    ]);
    exit();
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid JSON input.',
        ]);
        exit();
    }

    $requiredFields = ['first_name', 'last_name', 'email', 'password'];
    $missing = [];
    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            $missing[] = $field;
        }
    }

    if (!empty($missing)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Missing required fields: ' . implode(', ', $missing),
        ]);
        exit();
    }

    $email = strtolower(trim($input['email']));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid email format.',
        ]);
        exit();
    }

    $password = (string)$input['password'];
    if (strlen($password) < 6) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Password must be at least 6 characters long.',
        ]);
        exit();
    }

    $phoneNumber = sanitizePhone($input['phone_number'] ?? null);
    if ($phoneNumber === false) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Phone number must be 11 digits and start with 09.',
        ]);
        exit();
    }

    $emergencyPhone = sanitizePhone($input['emergency_contact_number'] ?? null);
    if ($emergencyPhone === false) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Emergency contact number must be 11 digits and start with 09.',
        ]);
        exit();
    }

    if (!empty($input['birthdate'])) {
        $birthdate = DateTime::createFromFormat('Y-m-d', $input['birthdate']);
        if (!$birthdate || $birthdate->format('Y-m-d') !== $input['birthdate']) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid birthdate format. Use YYYY-MM-DD.',
            ]);
            exit();
        }
    }

    $membershipType = !empty($input['membership_type']) ? trim($input['membership_type']) : null;
    $membershipStartDate = null;
    if (!empty($input['membership_start_date'])) {
        $start = DateTime::createFromFormat('Y-m-d', $input['membership_start_date']);
        if (!$start || $start->format('Y-m-d') !== $input['membership_start_date']) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid membership start date format. Use YYYY-MM-DD.',
            ]);
            exit();
        }
        $membershipStartDate = $start->format('Y-m-d');
    }

    $membershipEndDate = null;
    if (!empty($input['membership_end_date'])) {
        $end = DateTime::createFromFormat('Y-m-d', $input['membership_end_date']);
        if (!$end || $end->format('Y-m-d') !== $input['membership_end_date']) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid membership end date format. Use YYYY-MM-DD.',
            ]);
            exit();
        }
        $membershipEndDate = $end->format('Y-m-d');
    }

    $customer = new Customer();
    $existing = $customer->getByEmail($email);
    if ($existing) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Email already exists.',
        ]);
        exit();
    }

    $payload = [
        'first_name' => trim($input['first_name']),
        'last_name' => trim($input['last_name']),
        'middle_name' => !empty($input['middle_name']) ? trim($input['middle_name']) : null,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'birthdate' => !empty($input['birthdate']) ? $input['birthdate'] : null,
        'phone_number' => $phoneNumber,
        'address' => !empty($input['address']) ? trim($input['address']) : null,
        'emergency_contact_name' => !empty($input['emergency_contact_name'])
            ? trim($input['emergency_contact_name'])
            : null,
        'emergency_contact_number' => $emergencyPhone,
        'membership_type' => $membershipType,
        'membership_start_date' => $membershipStartDate,
        'membership_end_date' => $membershipEndDate,
        'created_by' => $input['created_by'] ?? 'customer_portal',
        'status' => $input['status'] ?? 'active',
    ];

    $verifier = new SignupVerification();
    $mailer = new Mailer();

    $result = $verifier->createOrUpdate($payload);
    $mailer->sendVerificationCode(
        $email,
        trim($input['first_name']) . ' ' . trim($input['last_name']),
        $result['code'],
        $result['ttl_minutes']
    );

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Verification code sent to your email.',
        'expires_at' => $result['expires_at'],
        'expires_in_minutes' => $result['ttl_minutes'],
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error: ' . $e->getMessage(),
    ]);
}

function sanitizePhone(?string $value)
{
    if ($value === null || $value === '') {
        return null;
    }
    $digits = preg_replace('/\D+/', '', $value);
    if (strlen($digits) === 11 && substr($digits, 0, 2) === '09') {
        return $digits;
    }
    return false;
}

