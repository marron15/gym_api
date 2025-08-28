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
        if (!isset($data['status']) || !in_array($data['status'], self::ALLOWED_STATUSES, true)) {
            return false;
        }

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
        $stmt->bindParam(':membershipType', $data['membershipType']);
        $stmt->bindParam(':startDate', $data['startDate']);
        $stmt->bindParam(':expirationDate', $data['expirationDate']);
        $stmt->bindParam(':status', $data['status']);
        $stmt->bindParam(':createdBy', $data['createdBy']);
        $stmt->bindParam(':createdAt', $data['createdAt']);
        $stmt->bindParam(':updatedAt', $data['updatedAt']);
        $stmt->bindParam(':updatedBy', $data['updatedBy']);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return true;
        }

        return false;
    }

    public function store($data)
    {
        if (!isset($data['status']) || !in_array($data['status'], self::ALLOWED_STATUSES, true)) {
            return false;
        }

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
        $stmt->bindParam(':membershipType', $data['membershipType']);
        $stmt->bindParam(':startDate', $data['startDate']);
        $stmt->bindParam(':expirationDate', $data['expirationDate']);
        $stmt->bindParam(':status', $data['status']);
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
}
?>