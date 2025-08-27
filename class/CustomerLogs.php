<?php

require_once 'Database.php';

class CustomerLogs
{
    public function __construct()
    {
        $db = new Database();
        $this->conn = $db->connection();
    }

    public function getAll()
    {
        $sql = "SELECT `id`, `customer_id`, `old_value`, `new_value`, `updated_at` 
                FROM `customer_activity_log` 
                ORDER BY `updated_at` DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $result;
    }

    public function getById($id)
    {
        $sql = "SELECT `id`, `customer_id`, `old_value`, `new_value`, `updated_at` 
                FROM `customer_activity_log` 
                WHERE `id` = :id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $result;
    }

    public function deleteById($id)
    {
        $sql = "DELETE FROM `customer_activity_log` WHERE id = :id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return true;
        }

        return false;
    }
    
    public function updateById($id, $data)
    {
        $sql = "UPDATE `customer_activity_log` 
                SET `customer_id` = :customerId,
                    `old_value`   = :oldValue,
                    `new_value`   = :newValue,
                    `updated_at`  = :updatedAt
                WHERE `id` = :id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':customerId', $data['customerId'], PDO::PARAM_INT);
        $stmt->bindParam(':oldValue', $data['oldValue']);
        $stmt->bindParam(':newValue', $data['newValue']);
        $stmt->bindParam(':updatedAt', $data['updatedAt']);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return true;
        }

        return false;
    }

    public function store($data)
    {
        $sql = "INSERT INTO `customer_activity_log`
                (`customer_id`, `old_value`, `new_value`, `updated_at`)
                VALUES (:customerId, :oldValue, :newValue, :updatedAt)";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':customerId', $data['customerId'], PDO::PARAM_INT);
        $stmt->bindParam(':oldValue', $data['oldValue']);
        $stmt->bindParam(':newValue', $data['newValue']);
        $stmt->bindParam(':updatedAt', $data['updatedAt']);
        $stmt->execute();

        if ($this->conn->lastInsertId()) {
            return true;
        }

        return false;       
    }
}
?>