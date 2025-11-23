<?php

require_once 'Database.php';
require_once 'Mailer.php';
require_once 'Membership.php';
require_once 'Customer.php';

/**
 * MembershipNotification Class
 * Handles sending email notifications for membership expiration
 */
class MembershipNotification
{
    private $conn;
    private $mailer;
    private $membership;
    private $customer;

    public function __construct()
    {
        $db = new Database();
        $this->conn = $db->connection();
        
        if (!$this->conn) {
            throw new Exception('Database connection failed');
        }

        $this->mailer = new Mailer();
        $this->membership = new Membership();
        $this->customer = new Customer();
    }

    /**
     * Get memberships expiring within N days (including today)
     * @param int $days Number of days until expiration (checks 0 to N days)
     * @return array Array of memberships with customer information
     */
    public function getMembershipsExpiringInDays(int $days): array
    {
        $today = date('Y-m-d');
        $targetDate = date('Y-m-d', strtotime("+{$days} days"));
        
        $sql = "SELECT 
                    m.id as membership_id,
                    m.customer_id,
                    m.membership_type,
                    m.start_date,
                    m.expiration_date,
                    m.status as membership_status,
                    c.email,
                    c.first_name,
                    c.last_name,
                    c.middle_name,
                    c.status as customer_status
                FROM `memberships` m
                INNER JOIN `customers` c ON m.customer_id = c.id
                WHERE DATE(m.expiration_date) >= :today
                AND DATE(m.expiration_date) <= :target_date
                AND c.status = 'active'
                AND c.email IS NOT NULL
                AND c.email != ''
                ORDER BY m.expiration_date ASC";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':today', $today);
        $stmt->bindParam(':target_date', $targetDate);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get expired memberships (expired today or earlier)
     * @return array Array of expired memberships with customer information
     */
    public function getExpiredMemberships(): array
    {
        $today = date('Y-m-d');
        
        $sql = "SELECT 
                    m.id as membership_id,
                    m.customer_id,
                    m.membership_type,
                    m.start_date,
                    m.expiration_date,
                    m.status as membership_status,
                    c.email,
                    c.first_name,
                    c.last_name,
                    c.middle_name,
                    c.status as customer_status
                FROM `memberships` m
                INNER JOIN `customers` c ON m.customer_id = c.id
                WHERE DATE(m.expiration_date) <= :today
                AND c.status = 'active'
                AND c.email IS NOT NULL
                AND c.email != ''
                ORDER BY m.expiration_date DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':today', $today);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Check if notification was already sent
     * @param int $customerId
     * @param int $membershipId
     * @param string $notificationType '3_days_left' or 'expired'
     * @param string $expirationDate
     * @return bool
     */
    public function wasNotificationSent(int $customerId, int $membershipId, string $notificationType, string $expirationDate): bool
    {
        $sql = "SELECT COUNT(*) as count 
                FROM `membership_notifications` 
                WHERE `customer_id` = :customer_id 
                AND `membership_id` = :membership_id 
                AND `notification_type` = :notification_type 
                AND `expiration_date` = :expiration_date
                AND `status` = 'sent'";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':customer_id', $customerId);
        $stmt->bindParam(':membership_id', $membershipId);
        $stmt->bindParam(':notification_type', $notificationType);
        $stmt->bindParam(':expiration_date', $expirationDate);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$result['count'] > 0;
    }

    /**
     * Record notification in database
     * @param int $customerId
     * @param int $membershipId
     * @param string $notificationType
     * @param string $email
     * @param string $expirationDate
     * @param bool $success
     * @param string|null $errorMessage
     * @return bool
     */
    public function recordNotification(
        int $customerId,
        int $membershipId,
        string $notificationType,
        string $email,
        string $expirationDate,
        bool $success = true,
        ?string $errorMessage = null
    ): bool {
        $status = $success ? 'sent' : 'failed';
        $now = date('Y-m-d H:i:s');

        $sql = "INSERT INTO `membership_notifications` 
                (`customer_id`, `membership_id`, `notification_type`, `email`, `expiration_date`, `status`, `error_message`, `sent_at`)
                VALUES (:customer_id, :membership_id, :notification_type, :email, :expiration_date, :status, :error_message, :sent_at)";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':customer_id', $customerId);
        $stmt->bindParam(':membership_id', $membershipId);
        $stmt->bindParam(':notification_type', $notificationType);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':expiration_date', $expirationDate);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':error_message', $errorMessage);
        $stmt->bindParam(':sent_at', $now);

        return $stmt->execute();
    }

    /**
     * Send 3 days left notification
     * @param array $membershipData Membership data with customer info
     * @return array Result with success status and message
     */
    public function sendThreeDaysLeftNotification(array $membershipData): array
    {
        $customerId = (int)$membershipData['customer_id'];
        $membershipId = (int)$membershipData['membership_id'];
        $email = $membershipData['email'];
        $expirationDate = $membershipData['expiration_date'];
        $firstName = $membershipData['first_name'] ?? '';
        $lastName = $membershipData['last_name'] ?? '';
        $fullName = trim("{$firstName} {$lastName}") ?: 'Member';
        $membershipType = $membershipData['membership_type'] ?? 'Membership';

        // Check if already sent
        if ($this->wasNotificationSent($customerId, $membershipId, '3_days_left', $expirationDate)) {
            return [
                'success' => false,
                'message' => 'Notification already sent',
                'skipped' => true
            ];
        }

        try {
            $sent = $this->mailer->sendMembershipExpiringNotification(
                $email,
                $fullName,
                $membershipType,
                $expirationDate,
                3
            );

            if ($sent) {
                $this->recordNotification($customerId, $membershipId, '3_days_left', $email, $expirationDate, true);
                return [
                    'success' => true,
                    'message' => 'Notification sent successfully',
                    'customer_id' => $customerId,
                    'email' => $email
                ];
            } else {
                $this->recordNotification($customerId, $membershipId, '3_days_left', $email, $expirationDate, false, 'Mailer returned false');
                return [
                    'success' => false,
                    'message' => 'Failed to send notification',
                    'customer_id' => $customerId
                ];
            }
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            $this->recordNotification($customerId, $membershipId, '3_days_left', $email, $expirationDate, false, $errorMessage);
            error_log("MembershipNotification Error (3 days left): {$errorMessage}");
            
            return [
                'success' => false,
                'message' => 'Error sending notification: ' . $errorMessage,
                'customer_id' => $customerId
            ];
        }
    }

    /**
     * Send expired membership notification
     * @param array $membershipData Membership data with customer info
     * @return array Result with success status and message
     */
    public function sendExpiredNotification(array $membershipData): array
    {
        $customerId = (int)$membershipData['customer_id'];
        $membershipId = (int)$membershipData['membership_id'];
        $email = $membershipData['email'];
        $expirationDate = $membershipData['expiration_date'];
        $firstName = $membershipData['first_name'] ?? '';
        $lastName = $membershipData['last_name'] ?? '';
        $fullName = trim("{$firstName} {$lastName}") ?: 'Member';
        $membershipType = $membershipData['membership_type'] ?? 'Membership';

        // Check if already sent for this expiration date
        if ($this->wasNotificationSent($customerId, $membershipId, 'expired', $expirationDate)) {
            return [
                'success' => false,
                'message' => 'Notification already sent',
                'skipped' => true
            ];
        }

        try {
            $sent = $this->mailer->sendMembershipExpiredNotification(
                $email,
                $fullName,
                $membershipType,
                $expirationDate
            );

            if ($sent) {
                $this->recordNotification($customerId, $membershipId, 'expired', $email, $expirationDate, true);
                return [
                    'success' => true,
                    'message' => 'Notification sent successfully',
                    'customer_id' => $customerId,
                    'email' => $email
                ];
            } else {
                $this->recordNotification($customerId, $membershipId, 'expired', $email, $expirationDate, false, 'Mailer returned false');
                return [
                    'success' => false,
                    'message' => 'Failed to send notification',
                    'customer_id' => $customerId
                ];
            }
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            $this->recordNotification($customerId, $membershipId, 'expired', $email, $expirationDate, false, $errorMessage);
            error_log("MembershipNotification Error (expired): {$errorMessage}");
            
            return [
                'success' => false,
                'message' => 'Error sending notification: ' . $errorMessage,
                'customer_id' => $customerId
            ];
        }
    }

    /**
     * Process all pending notifications (3 days left and expired)
     * @return array Summary of processed notifications
     */
    public function processAllNotifications(): array
    {
        $results = [
            'three_days_left' => [
                'processed' => 0,
                'sent' => 0,
                'failed' => 0,
                'skipped' => 0,
                'errors' => []
            ],
            'expired' => [
                'processed' => 0,
                'sent' => 0,
                'failed' => 0,
                'skipped' => 0,
                'errors' => []
            ]
        ];

        // Process 3 days left notifications
        $expiringMemberships = $this->getMembershipsExpiringInDays(3);
        foreach ($expiringMemberships as $membership) {
            $results['three_days_left']['processed']++;
            $result = $this->sendThreeDaysLeftNotification($membership);
            
            if ($result['success']) {
                $results['three_days_left']['sent']++;
            } elseif (isset($result['skipped']) && $result['skipped']) {
                $results['three_days_left']['skipped']++;
            } else {
                $results['three_days_left']['failed']++;
                $results['three_days_left']['errors'][] = $result['message'];
            }
        }

        // Process expired notifications
        $expiredMemberships = $this->getExpiredMemberships();
        foreach ($expiredMemberships as $membership) {
            $results['expired']['processed']++;
            $result = $this->sendExpiredNotification($membership);
            
            if ($result['success']) {
                $results['expired']['sent']++;
            } elseif (isset($result['skipped']) && $result['skipped']) {
                $results['expired']['skipped']++;
            } else {
                $results['expired']['failed']++;
                $results['expired']['errors'][] = $result['message'];
            }
        }

        return $results;
    }

    /**
     * Get notification history for a customer
     * @param int $customerId
     * @return array
     */
    public function getNotificationHistory(int $customerId): array
    {
        $sql = "SELECT 
                    id,
                    membership_id,
                    notification_type,
                    sent_at,
                    email,
                    expiration_date,
                    status,
                    error_message
                FROM `membership_notifications`
                WHERE `customer_id` = :customer_id
                ORDER BY `sent_at` DESC
                LIMIT 50";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':customer_id', $customerId);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

