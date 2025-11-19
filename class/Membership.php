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
        $sql = "SELECT * FROM `memberships`";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
            $data['createdBy'] = $existing['created_by'] ?? ($data['createdBy'] ?? 0);
            $data['createdAt'] = $existing['created_at'] ?? ($data['createdAt'] ?? $now);
            $result = $this->updateServicesByID((int)$existing['id'], $data);

            if ($result) {
                return [
                    'membership_id' => (int)$existing['id'],
                    'operation' => 'updated'
                ];
            }

            return [
                'membership_id' => (int)$existing['id'],
                'operation' => 'unchanged'
            ];
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