<?php

require_once 'Database.php';

class Attendance
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

    public function getLogs(array $filters = [])
    {
        $sql = "SELECT 
                    cal.id,
                    cal.customer_id,
                    cal.time_in,
                    cal.time_out,
                    cal.status,
                    cal.verified_by_admin_id,
                    cal.verified_by_name,
                    cal.created_at,
                    cal.updated_at,
                    COALESCE(a.first_name, '') AS admin_first_name,
                    COALESCE(a.last_name, '') AS admin_last_name,
                    c.first_name AS customer_first_name,
                    c.last_name AS customer_last_name
                FROM customer_attendance_logs cal
                LEFT JOIN customers c ON c.id = cal.customer_id
                LEFT JOIN admins a ON a.id = cal.verified_by_admin_id
                WHERE 1 = 1";

        $params = [];

        if (!empty($filters['date'])) {
            $sql .= " AND DATE(COALESCE(cal.time_in, cal.time_out, cal.created_at)) = :logDate";
            $params[':logDate'] = $filters['date'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (
                LOWER(CONCAT(c.first_name, ' ', c.last_name)) LIKE :term
                OR c.id LIKE :term
                OR LOWER(cal.status) LIKE :term
            )";
            $params[':term'] = '%' . strtolower($filters['search']) . '%';
        }

        $sql .= " ORDER BY cal.created_at DESC LIMIT 500";

        $stmt = $this->conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map([$this, 'formatRecord'], $rows);
    }

    public function getSnapshot(int $customerId)
    {
        $sql = "SELECT 
                    cal.id,
                    cal.customer_id,
                    cal.time_in,
                    cal.time_out,
                    cal.status,
                    cal.verified_by_admin_id,
                    cal.verified_by_name,
                    cal.created_at,
                    cal.updated_at,
                    COALESCE(a.first_name, '') AS admin_first_name,
                    COALESCE(a.last_name, '') AS admin_last_name,
                    c.first_name AS customer_first_name,
                    c.last_name AS customer_last_name
                FROM customer_attendance_logs cal
                LEFT JOIN customers c ON c.id = cal.customer_id
                LEFT JOIN admins a ON a.id = cal.verified_by_admin_id
                WHERE cal.customer_id = :customerId
                ORDER BY cal.created_at DESC
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':customerId', $customerId, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->formatSnapshot($row) : null;
    }

    public function recordScan(int $customerId, array $adminMeta = [], ?string $platform = null)
    {
        $adminId = isset($adminMeta['adminId']) ? (int)$adminMeta['adminId'] : null;
        $adminName = $this->resolveAdminName($adminMeta);

        $now = new DateTime('now', new DateTimeZone('Asia/Manila'));
        $timestamp = $now->format('Y-m-d H:i:s');

        try {
            $this->conn->beginTransaction();

            $openLog = $this->getOpenLog($customerId);
            if ($openLog) {
                $sql = "UPDATE customer_attendance_logs
                        SET time_out = :timeOut,
                            status = 'OUT',
                            verified_by_admin_id = :adminId,
                            verified_by_name = :adminName,
                            platform = COALESCE(:platform, platform),
                            updated_at = :updatedAt
                        WHERE id = :id";

                $stmt = $this->conn->prepare($sql);
                $stmt->bindValue(':timeOut', $timestamp);
                $this->bindNullableInt($stmt, ':adminId', $adminId);
                $stmt->bindValue(':adminName', $adminName);
                $stmt->bindValue(':platform', $platform);
                $stmt->bindValue(':updatedAt', $timestamp);
                $stmt->bindValue(':id', $openLog['id'], PDO::PARAM_INT);
                $stmt->execute();
            } else {
                $sql = "INSERT INTO customer_attendance_logs
                        (customer_id, time_in, status, verified_by_admin_id, verified_by_name, platform, created_at, updated_at)
                        VALUES (:customerId, :timeIn, 'IN', :adminId, :adminName, :platform, :createdAt, :updatedAt)";

                $stmt = $this->conn->prepare($sql);
                $stmt->bindValue(':customerId', $customerId, PDO::PARAM_INT);
                $stmt->bindValue(':timeIn', $timestamp);
                $this->bindNullableInt($stmt, ':adminId', $adminId);
                $stmt->bindValue(':adminName', $adminName);
                $stmt->bindValue(':platform', $platform);
                $stmt->bindValue(':createdAt', $timestamp);
                $stmt->bindValue(':updatedAt', $timestamp);
                $stmt->execute();
            }

            $this->conn->commit();
            return $this->getSnapshot($customerId);
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    private function getOpenLog(int $customerId)
    {
        $sql = "SELECT id FROM customer_attendance_logs
                WHERE customer_id = :customerId AND time_out IS NULL
                ORDER BY created_at DESC
                LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':customerId', $customerId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function resolveAdminName(array $adminMeta)
    {
        $name = trim(($adminMeta['firstName'] ?? '') . ' ' . ($adminMeta['lastName'] ?? ''));
        if (!empty($name)) {
            return $name;
        }

        if (!empty($adminMeta['name'])) {
            return $adminMeta['name'];
        }

        if (!empty($adminMeta['contact'])) {
            return $adminMeta['contact'];
        }

        return 'System';
    }

    private function formatRecord(array $row)
    {
        $customerName = trim(($row['customer_first_name'] ?? '') . ' ' . ($row['customer_last_name'] ?? ''));
        $adminName = $row['verified_by_name'] ?? trim(($row['admin_first_name'] ?? '') . ' ' . ($row['admin_last_name'] ?? ''));

        return [
            'attendance_id' => (int)$row['id'],
            'customer_id' => (int)$row['customer_id'],
            'customer_name' => $customerName,
            'date' => $row['time_in'] ?? $row['time_out'] ?? $row['created_at'],
            'time_in' => $row['time_in'],
            'time_out' => $row['time_out'],
            'status' => strtoupper($row['status'] ?? ($row['time_out'] ? 'OUT' : 'IN')),
            'verified_by' => $adminName,
            'verified_by_admin_id' => $row['verified_by_admin_id'],
        ];
    }

    private function formatSnapshot(array $row)
    {
        $adminName = $row['verified_by_name'] ?? trim(($row['admin_first_name'] ?? '') . ' ' . ($row['admin_last_name'] ?? ''));
        $status = strtoupper($row['status'] ?? ($row['time_out'] ? 'OUT' : 'IN'));

        return [
            'attendance_id' => (int)$row['id'],
            'customer_id' => (int)$row['customer_id'],
            'is_clocked_in' => $status === 'IN',
            'status' => $status,
            'last_time_in' => $row['time_in'],
            'last_time_out' => $row['time_out'],
            'verified_by' => $adminName,
            'verified_by_admin_id' => $row['verified_by_admin_id'],
        ];
    }

    private function bindNullableInt(\PDOStatement $stmt, string $param, $value): void
    {
        if ($value === null) {
            $stmt->bindValue($param, null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue($param, (int)$value, PDO::PARAM_INT);
        }
    }
}

?>

