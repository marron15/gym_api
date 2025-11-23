<?php

require_once 'Database.php';

class AuditLog
{
    private $conn;

    public function __construct()
    {
        $db = new Database();
        $this->conn = $db->connection();

        if (!$this->conn) {
            throw new Exception('Database connection failed');
        }
    }

    public static function currentTimestamp(): string
    {
        $dt = new DateTime('now');
        return $dt->format('Y-m-d H:i:s');
    }

    public function record(array $payload): bool
    {
        try {
            $sql = "INSERT INTO customer_audit_logs
                    (customer_id, customer_name, admin_id, actor_type, actor_name,
                     activity_category, activity_type, activity_title, description,
                     metadata_json, created_at)
                    VALUES (
                        :customerId,
                        :customerName,
                        :adminId,
                        :actorType,
                        :actorName,
                        :activityCategory,
                        :activityType,
                        :activityTitle,
                        :description,
                        :metadataJson,
                        :createdAt
                    )";

            $stmt = $this->conn->prepare($sql);

            $this->bindNullableInt($stmt, ':customerId', $payload['customer_id'] ?? null);
            $stmt->bindValue(':customerName', $this->sanitizeString($payload['customer_name'] ?? null));
            $this->bindNullableInt($stmt, ':adminId', $payload['admin_id'] ?? null);
            $stmt->bindValue(':actorType', $this->sanitizeString($payload['actor_type'] ?? 'system', 32));
            $stmt->bindValue(':actorName', $this->sanitizeString($payload['actor_name'] ?? null));
            $stmt->bindValue(
                ':activityCategory',
                $this->sanitizeString(
                    $payload['activity_category'] ?? ($payload['activity_type'] ?? 'general'),
                    32
                )
            );
            $stmt->bindValue(':activityType', $this->sanitizeString($payload['activity_type'] ?? 'general', 64));
            $stmt->bindValue(
                ':activityTitle',
                $this->sanitizeString($payload['activity_title'] ?? 'Customer activity')
            );
            $stmt->bindValue(':description', $payload['description'] ?? null);
            $stmt->bindValue(
                ':metadataJson',
                $this->encodeMetadata($payload['metadata'] ?? [])
            );
            $stmt->bindValue(':createdAt', $payload['created_at'] ?? self::currentTimestamp());

            return $stmt->execute();
        } catch (Exception $e) {
            error_log('AuditLog record error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * @param array{
     *   activity_type?: string,
     *   activity_category?: string,
     *   search?: string,
     *   customer_id?: int,
     *   limit?: int
     * } $filters
     */
    public function getLogs(array $filters = []): array
    {
        try {
            $limit = isset($filters['limit']) ? (int)$filters['limit'] : 200;
            $limit = max(1, min($limit, 500));

        $sql = "SELECT
                    cal.*,
                    cal.created_at AS created_at_ph,
                    c.first_name AS customer_first_name,
                    c.last_name AS customer_last_name,
                    a.first_name AS admin_first_name,
                    a.last_name AS admin_last_name
                FROM customer_audit_logs cal
                LEFT JOIN customers c ON c.id = cal.customer_id
                LEFT JOIN admins a ON a.id = cal.admin_id
                WHERE 1 = 1";

            $params = [];

            if (!empty($filters['activity_type'])) {
                $sql .= " AND cal.activity_type = :activityType";
                $params[':activityType'] = $filters['activity_type'];
            }

            if (!empty($filters['activity_category'])) {
                $sql .= " AND cal.activity_category = :activityCategory";
                $params[':activityCategory'] = $filters['activity_category'];
            }

            if (!empty($filters['customer_id'])) {
                $sql .= " AND cal.customer_id = :customerId";
                $params[':customerId'] = (int)$filters['customer_id'];
            }

            if (!empty($filters['search'])) {
                $sql .= " AND (
                    LOWER(cal.activity_title) LIKE :searchTerm
                    OR LOWER(cal.description) LIKE :searchTerm
                    OR LOWER(cal.actor_name) LIKE :searchTerm
                    OR LOWER(CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c.last_name, ''))) LIKE :searchTerm
                    OR LOWER(CONCAT(COALESCE(a.first_name, ''), ' ', COALESCE(a.last_name, ''))) LIKE :searchTerm
                    OR CAST(cal.customer_id AS CHAR) LIKE :searchTerm
                )";
                $params[':searchTerm'] = '%' . strtolower($filters['search']) . '%';
            }

            $sql .= " ORDER BY cal.created_at DESC LIMIT :limit";

            $stmt = $this->conn->prepare($sql);
            foreach ($params as $key => $value) {
                if ($key === ':customerId') {
                    $stmt->bindValue($key, $value, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($key, $value);
                }
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return array_map([$this, 'formatRow'], $rows);
        } catch (Exception $e) {
            error_log('AuditLog fetch error: ' . $e->getMessage());
            return [];
        }
    }

    private function formatRow(array $row): array
    {
        $metadata = null;
        if (!empty($row['metadata_json'])) {
            $decoded = json_decode($row['metadata_json'], true);
            $metadata = json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }

        $customerName = $row['customer_name'];
        if (!$customerName) {
            $customerName = trim(
                ($row['customer_first_name'] ?? '') . ' ' . ($row['customer_last_name'] ?? '')
            );
        }

        $adminName = $row['actor_name'];
        if (!$adminName && $row['admin_first_name']) {
            $adminName = trim(
                ($row['admin_first_name'] ?? '') . ' ' . ($row['admin_last_name'] ?? '')
            );
        }

        return [
            'id' => (int)$row['id'],
            'customer_id' => $row['customer_id'] !== null ? (int)$row['customer_id'] : null,
            'customer_name' => $customerName ?: null,
            'admin_id' => $row['admin_id'] !== null ? (int)$row['admin_id'] : null,
            'actor_type' => $row['actor_type'],
            'actor_name' => $adminName ?: null,
            'activity_category' => $row['activity_category'],
            'activity_type' => $row['activity_type'],
            'activity_title' => $row['activity_title'],
            'description' => $row['description'],
            'metadata' => $metadata,
            'created_at' => $row['created_at'],
            'created_at_ph' => $row['created_at_ph'] ?? null,
        ];
    }

    private function sanitizeString(?string $value, int $maxLength = 255): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        if (function_exists('mb_substr')) {
            return mb_substr($trimmed, 0, $maxLength);
        }

        return substr($trimmed, 0, $maxLength);
    }

    private function encodeMetadata($metadata): ?string
    {
        if ($metadata === null) {
            return null;
        }

        if (is_string($metadata)) {
            return $metadata;
        }

        return json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function bindNullableInt(\PDOStatement $stmt, string $placeholder, $value): void
    {
        if ($value === null || $value === '') {
            $stmt->bindValue($placeholder, null, PDO::PARAM_NULL);
            return;
        }

        $stmt->bindValue($placeholder, (int)$value, PDO::PARAM_INT);
    }
}

?>

