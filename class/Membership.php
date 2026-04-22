<?php

require_once 'Database.php';

class Membership
{
    private const ALLOWED_STATUSES = ['Daily', 'Half Month', 'Monthly'];

    public function __construct()
    {
        $db = new Database();
        $this->conn = $db->connection();
        $this->ensureHistoryTableExists();
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

        try {
            $this->conn->beginTransaction();

            if ($this->store($data)) {
                $membershipId = (int)$this->conn->lastInsertId();
                if (!$this->insertHistoryRecord($membershipId, $data)) {
                    $this->conn->rollBack();
                    return false;
                }

                $this->conn->commit();
                return [
                    'membership_id' => $membershipId,
                    'operation' => 'inserted'
                ];
            }

            $this->conn->rollBack();
        } catch (Exception $e) {
            if ($this->conn && $this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log('Membership upsert transaction failed: ' . $e->getMessage());
        }

        return false;
    }

    public function getHistoryByCustomerId($customerId)
    {
        $sql = "SELECT mh.id,
                       mh.membership_id,
                       mh.customer_id,
                       mh.membership_type,
                       mh.start_date,
                       mh.expiration_date,
                       mh.status,
                       mh.renewed_by_id,
                       mh.renewed_by_name,
                       TRIM(
                           COALESCE(
                               NULLIF(TRIM(CONCAT(COALESCE(a.first_name, ''), ' ', COALESCE(a.last_name, ''))), ''),
                               NULLIF(TRIM(mh.renewed_by_name), ''),
                               NULLIF(TRIM(CAST(mh.renewed_by_id AS CHAR)), ''),
                               'System'
                           )
                       ) AS renewed_by,
                       mh.created_at,
                       mh.updated_at
                FROM `membership_history` mh
                LEFT JOIN `admins` a ON a.id = mh.renewed_by_id
                WHERE mh.customer_id = :customerId
                ORDER BY mh.start_date DESC, mh.created_at DESC, mh.id DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':customerId', $customerId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function ensureHistoryTableExists()
    {
        $createSql = "CREATE TABLE IF NOT EXISTS `membership_history` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `membership_id` int(11) NOT NULL,
                `customer_id` int(11) NOT NULL,
                `membership_type` varchar(50) NOT NULL,
                `start_date` datetime NOT NULL,
                `expiration_date` datetime NOT NULL,
                `status` varchar(50) NOT NULL,
                `renewed_by_id` int(11) DEFAULT NULL,
                `renewed_by_name` varchar(255) DEFAULT NULL,
                `source_table` varchar(50) DEFAULT 'memberships',
                `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_membership_id` (`membership_id`),
                KEY `idx_customer_id` (`customer_id`),
                KEY `idx_renewed_by_id` (`renewed_by_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $this->conn->exec($createSql);

        $seedSql = "INSERT IGNORE INTO `membership_history`
                (`membership_id`, `customer_id`, `membership_type`, `start_date`, `expiration_date`, `status`, `renewed_by_id`, `renewed_by_name`, `source_table`, `created_at`, `updated_at`)
                SELECT m.id,
                       m.customer_id,
                       m.membership_type,
                       m.start_date,
                       m.expiration_date,
                       m.status,
                       CASE
                           WHEN m.updated_by IS NOT NULL
                                AND TRIM(CAST(m.updated_by AS CHAR)) != ''
                                AND TRIM(CAST(m.updated_by AS CHAR)) != '0'
                             THEN CAST(m.updated_by AS UNSIGNED)
                           WHEN m.created_by IS NOT NULL
                                AND TRIM(CAST(m.created_by AS CHAR)) != ''
                                AND TRIM(CAST(m.created_by AS CHAR)) != '0'
                             THEN CAST(m.created_by AS UNSIGNED)
                           ELSE NULL
                       END AS renewed_by_id,
                       TRIM(
                           COALESCE(
                               NULLIF(TRIM(CONCAT(COALESCE(a.first_name, ''), ' ', COALESCE(a.last_name, ''))), ''),
                               NULLIF(TRIM(CAST(m.updated_by AS CHAR)), ''),
                               NULLIF(TRIM(CAST(m.created_by AS CHAR)), ''),
                               'System'
                           )
                       ) AS renewed_by_name,
                       'memberships' AS source_table,
                       m.created_at,
                       m.updated_at
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

        $this->conn->exec($seedSql);
    }

    private function insertHistoryRecord($membershipId, $data)
    {
        $renewedById = $this->resolveActorId(
            $data['updatedById'] ?? $data['createdById'] ?? null,
            $data['updatedBy'] ?? $data['createdBy'] ?? null
        );
        $renewedByName = $this->resolveActorName(
            $data['updatedByName'] ?? $data['createdByName'] ?? null,
            $data['updatedBy'] ?? $data['createdBy'] ?? null
        );

        $sql = "INSERT INTO `membership_history`
                (`membership_id`, `customer_id`, `membership_type`, `start_date`, `expiration_date`, `status`, `renewed_by_id`, `renewed_by_name`, `source_table`)
                VALUES
                (:membershipId, :customerId, :membershipType, :startDate, :expirationDate, :status, :renewedById, :renewedByName, 'memberships')";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':membershipId', $membershipId, PDO::PARAM_INT);
        $stmt->bindValue(':customerId', (int)$data['customerId'], PDO::PARAM_INT);
        $stmt->bindValue(':membershipType', $data['membershipType']);
        $stmt->bindValue(':startDate', $data['startDate']);
        $stmt->bindValue(':expirationDate', $data['expirationDate']);
        $stmt->bindValue(':status', $data['status']);
        if ($renewedById > 0) {
            $stmt->bindValue(':renewedById', $renewedById, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':renewedById', null, PDO::PARAM_NULL);
        }
        if ($renewedByName !== '') {
            $stmt->bindValue(':renewedByName', $renewedByName);
        } else {
            $stmt->bindValue(':renewedByName', null, PDO::PARAM_NULL);
        }

        return $stmt->execute();
    }

    private function resolveActorId($primary, $fallback)
    {
        foreach ([$primary, $fallback] as $value) {
            if ($value === null) {
                continue;
            }
            if (is_int($value)) {
                return $value > 0 ? $value : 0;
            }
            $text = trim((string)$value);
            if ($text === '' || !ctype_digit($text)) {
                continue;
            }
            $numeric = (int)$text;
            if ($numeric > 0) {
                return $numeric;
            }
        }

        return 0;
    }

    private function resolveActorName($primary, $fallback)
    {
        foreach ([$primary, $fallback] as $value) {
            $text = trim((string)($value ?? ''));
            if ($text !== '' && !ctype_digit($text)) {
                return $text;
            }
        }

        return '';
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