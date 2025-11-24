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

    $email = !empty($input['email']) ? strtolower(trim($input['email'])) : null;
    $code = !empty($input['verification_code']) ? trim($input['verification_code']) : null;

    if (!$email || !$code) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Email and verification code are required.',
        ]);
        exit();
    }

    $verifier = new SignupVerification();
    $pending = $verifier->getPendingByEmail($email);

    if (!$pending) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'No pending verification found for this email.',
        ]);
        exit();
    }

    $maxAttempts = $verifier->getMaxAttempts();
    if ((int)$pending['attempts'] >= $maxAttempts) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'message' => 'Too many invalid attempts. Please request a new code.',
        ]);
        exit();
    }

    $isExpired = strtotime($pending['expires_at']) < time();
    if ($isExpired) {
        http_response_code(410);
        echo json_encode([
            'success' => false,
            'message' => 'Verification code has expired. Please request a new one.',
        ]);
        exit();
    }

    if (!password_verify($code, $pending['code_hash'])) {
        $verifier->incrementAttempts((int)$pending['id']);
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid verification code.',
            'attempts_remaining' => max(
                0,
                $maxAttempts - ((int)$pending['attempts'] + 1)
            ),
        ]);
        exit();
    }

    $payload = json_decode($pending['payload_json'], true);
    if (!$payload || empty($payload['password_hash'])) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Stored signup details are invalid. Please try again.',
        ]);
        exit();
    }

    $payload['password'] = $payload['password_hash'];
    $payload['password_is_hashed'] = true;
    unset($payload['password_hash']);

    $customer = new Customer();
    $signupResult = $customer->signup($payload);

    if ($signupResult['success'] === true) {
        $membershipCreated = false;
        if (!empty($payload['membership_type'])) {
            $membershipCreated = $customer->upsertCustomerMembership(
                $signupResult['customer']['id'],
                $payload['membership_type'],
                $payload['membership_start_date'] ?? null,
                $payload['membership_end_date'] ?? null
            );

            // Send membership creation notification if membership was successfully created by admin
            $createdBy = $payload['created_by'] ?? '';
            $isAdminCreated = in_array(strtolower($createdBy), ['admin', 'admin_portal']);
            
            if ($membershipCreated && $isAdminCreated && !empty($signupResult['customer']['email'])) {
                try {
                    $mailer = new Mailer();
                    $customerName = trim(
                        ($signupResult['customer']['first_name'] ?? '') . ' ' . 
                        ($signupResult['customer']['last_name'] ?? '')
                    );
                    if (empty($customerName)) {
                        $customerName = 'Member';
                    }
                    
                    $startDate = $payload['membership_start_date'] ?? date('Y-m-d');
                    $endDate = $payload['membership_end_date'] ?? null;
                    
                    // If end date is not provided, calculate it based on membership type
                    if (!$endDate) {
                        $membershipType = strtolower(trim($payload['membership_type']));
                        $startDateTime = new DateTime($startDate);
                        if ($membershipType === 'daily') {
                            // Set expiration to 9 PM of the same day
                            $endDateTime = clone $startDateTime;
                            $endDateTime->setTime(21, 0, 0);
                            // If created after 9 PM, expire at 9 PM next day
                            if ($startDateTime->format('H') >= 21) {
                                $endDateTime->add(new DateInterval('P1D'));
                            }
                            $endDate = $endDateTime->format('Y-m-d H:i:s');
                        } elseif ($membershipType === 'half month' || $membershipType === 'halfmonth') {
                            $endDateTime = clone $startDateTime;
                            $endDateTime->add(new DateInterval('P15D'));
                            $endDate = $endDateTime->format('Y-m-d');
                        } else {
                            // Monthly
                            $endDateTime = clone $startDateTime;
                            $endDateTime->add(new DateInterval('P30D'));
                            $endDate = $endDateTime->format('Y-m-d');
                        }
                    }
                    
                    $mailer->sendMembershipCreatedNotification(
                        $signupResult['customer']['email'],
                        $customerName,
                        $payload['membership_type'],
                        $startDate,
                        $endDate
                    );
                } catch (Exception $e) {
                    // Log error but don't fail the signup process
                    error_log('Failed to send membership creation notification: ' . $e->getMessage());
                }
            }
        }

        $verifier->deleteById((int)$pending['id']);

        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Account created successfully.',
            'data' => [
                'customer_id' => $signupResult['customer']['id'],
                'email' => $signupResult['customer']['email'],
                'first_name' => $signupResult['customer']['first_name'],
                'last_name' => $signupResult['customer']['last_name'],
                'middle_name' => $signupResult['customer']['middle_name'],
                'full_name' => trim(
                    $signupResult['customer']['first_name'] . ' ' . $signupResult['customer']['last_name']
                ),
                'phone_number' => $signupResult['customer']['phone_number'],
                'birthdate' => $signupResult['customer']['birthdate'],
                'emergency_contact_name' => $signupResult['customer']['emergency_contact_name'],
                'emergency_contact_number' => $signupResult['customer']['emergency_contact_number'],
                'img' => $signupResult['customer']['img'],
                'created_at' => $signupResult['customer']['created_at'],
            ],
            'membership_created' => $membershipCreated,
            'access_token' => $signupResult['access_token'],
            'refresh_token' => $signupResult['refresh_token'],
            'token_type' => $signupResult['token_type'],
            'expires_in' => $signupResult['expires_in'],
        ]);
    } else {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $signupResult['message'] ?? 'Failed to create account.',
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error: ' . $e->getMessage(),
    ]);
}

