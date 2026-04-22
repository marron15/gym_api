<?php

require_once 'Database.php';

class Membership
{
    private const ALLOWED_STATUSES = ['Daily', 'Half Month', 'Monthly'];

    public function __construct()
    {
        $db = new Database();
        $this->conn = $db->connection();
    }

    public function getAll()
    {
        $sql = "SELECT m.*, 
                       TRIM(
                           COALESCE(
                               NULLIF(TRIM(CONCAT(COALESCE(a.first_name, ''), ' ', COALESCE(a.last_name, ''))), ''),
                               NULLIF(TRIM(CAST(m.updated_by AS CHAR)), ''),
                               NULLIF(TRIM(CAST(m.created_by AS CHAR)), '')
                           )
                       ) AS verified_by 
                FROM `memberships` m
                LEFT JOIN `admins` a ON a.id = CASE 
                                            WHEN m.updated_by IS NOT NULL
                                                 AND TRIM(CAST(m.updated_by AS CHAR)) != ''
                                                 AND TRIM(CAST(m.updated_by AS CHAR)) != '0'
                                              THEN CAST(m.updated_by AS UNSIGNED)
                                            WHEN m.created_by IS NOT NULL
                                                 AND TRIM(CAST(m.created_by AS CHAR)) != ''
                                                 AND TRIM(CAST(m.created_by AS CHAR)) != '0'
                                              THEN CAST(m.created_by AS UNSIGNED)
                                            ELSE 0
                                         END";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fallback for missing admin names
        foreach ($result as &$row) {
            if (empty($row['verified_by']) || $row['verified_by'] === '0') {
                // Keep '0' logic out, replace with a recognizable name or empty string to let frontend display '—'
                $row['verified_by'] = ''; 
            }
        }

        return $result;
    }

    public function getById($id)
    {
        $sql = "SELECT * FROM `memberships` WHERE `id` = :id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $result;
    }

    public function getByCustomerId($customerId)
    {
        $sql = "SELECT * FROM `memberships` WHERE `customer_id` = :customerId ORDER BY `created_at` DESC LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':customerId', $customerId);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result;
    }

    public function deleteById($id)
    {
        $sql = "DELETE FROM `memberships` WHERE id = :id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return true;
        }

        return false;
    }
    
    public function updateServicesByID($id, $data)
    {
        $normalizedStatus = $this->normalizeStatus($data['status'] ?? $data['membershipType'] ?? null);
        if ($normalizedStatus === null) {
            return false;
        }
        $normalizedType = $this->normalizeStatus($data['membershipType'] ?? $normalizedStatus);

        $sql = "UPDATE `memberships` SET 
                `customer_id` = :customerId,
                `membership_type` = :membershipType,
                `start_date` = :startDate,
                `expiration_date` = :expirationDate,
                `status` = :status,
                `created_by` = :createdBy,
                `created_at` = :createdAt,
                `updated_at` = :updatedAt,
                `updated_by` = :updatedBy
                WHERE `id` = :id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':customerId', $data['customerId']);
        $stmt->bindParam(':membershipType', $normalizedType);
        $stmt->bindParam(':startDate', $data['startDate']);
        $stmt->bindParam(':expirationDate', $data['expirationDate']);
        $stmt->bindParam(':status', $normalizedStatus);
        $stmt->bindParam(':createdBy', $data['createdBy']);
        $stmt->bindParam(':createdAt', $data['createdAt']);
        $stmt->bindParam(':updatedAt', $data['updatedAt']);
        $stmt->bindParam(':updatedBy', $data['updatedBy']);
        $stmt->execute();

        return $stmt->rowCount() >= 0;
    }

    public function store($data)
    {
        $normalizedStatus = $this->normalizeStatus($data['status'] ?? $data['membershipType'] ?? null);
        if ($normalizedStatus === null) {
            return false;
        }
        $normalizedType = $this->normalizeStatus($data['membershipType'] ?? $normalizedStatus);

        $sql = "INSERT INTO `memberships`
                SET `customer_id` = :customerId,
                    `membership_type` = :membershipType,
                    `start_date` = :startDate,
                    `expiration_date` = :expirationDate,
                    `status` = :status,
                    `created_by` = :createdBy,
                    `created_at` = :createdAt,
                    `updated_at` = :updatedAt,
                    `updated_by` = :updatedBy";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':customerId', $data['customerId']);
        $stmt->bindParam(':membershipType', $normalizedType);
        $stmt->bindParam(':startDate', $data['startDate']);
        $stmt->bindParam(':expirationDate', $data['expirationDate']);
        $stmt->bindParam(':status', $normalizedStatus);
        $stmt->bindParam(':createdBy', $data['createdBy']);
        $stmt->bindParam(':createdAt', $data['createdAt']);
        $stmt->bindParam(':updatedAt', $data['updatedAt']);
        $stmt->bindParam(':updatedBy', $data['updatedBy']);
        $stmt->execute();

        if ($this->conn->lastInsertId()) {
            return true;
        }

        return false;       
    }

    /**
     * Insert or update membership for a customer. Returns array with metadata or false.
     */
    public function upsertByCustomerId($customerId, $data)
    {
        $existing = $this->getByCustomerId($customerId);
        $now = date('Y-m-d H:i:s');

        $data['customerId'] = $customerId;
        $data['updatedAt'] = $data['updatedAt'] ?? $now;
        $data['updatedBy'] = $data['updatedBy'] ?? 0;

        if ($existing) {
            $incomingType = $this->normalizeStatus($data['membershipType'] ?? $data['status'] ?? null);
            $existingType = $this->normalizeStatus($existing['membership_type'] ?? $existing['status'] ?? null);
            $incomingStart = trim((string)($data['startDate'] ?? ''));
            $incomingEnd = trim((string)($data['expirationDate'] ?? ''));
            $existingStart = trim((string)($existing['start_date'] ?? ''));
            $existingEnd = trim((string)($existing['expiration_date'] ?? ''));

            // Keep idempotency for exact retries so we do not create duplicate rows.
            if ($incomingType === $existingType &&
                $incomingStart === $existingStart &&
                $incomingEnd === $existingEnd) {
                return [
                    'membership_id' => (int)$existing['id'],
                    'operation' => 'unchanged'
                ];
            }
        }

        $data['createdBy'] = $data['createdBy'] ?? 0;
        $data['createdAt'] = $data['createdAt'] ?? $now;

        if ($this->store($data)) {
            return [
                'membership_id' => (int)$this->conn->lastInsertId(),
                'operation' => 'inserted'
            ];
        }

        return false;
    }

    private function normalizeStatus($rawStatus)
    {
        if ($rawStatus === null) {
            return null;
        }

        $key = strtolower(trim($rawStatus));
        $key = str_replace(' ', '', $key);

        if ($key === 'daily') {
            return 'Daily';
        }

        if ($key === 'halfmonth') {
            return 'Half Month';
        }

        if ($key === 'monthly') {
            return 'Monthly';
        }

        return null;
    }
}
?>