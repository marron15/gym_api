<?php

require_once 'Database.php';

class Membership
{
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
        $sql = "UPDATE `memberships` SET 
                'description = :description,
                'price = :price,
                'created_by = :createdBy,
                'created_at = :createdAt,
                'updated_at = :updatedAt,
                'updated_by = :updatedBy,
                'status = :status
                WHERE id = :id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':description', $data['description']);
        $stmt->bindParam(':price', $data['price']);
        $stmt->bindParam(':createdBy', $data['createdBy'], PDO::PARAM_INT);
        $stmt->bindParam(':createdAt', $data['createdAt']);
        $stmt->bindParam(':updatedAt', $data['updatedAt']);
        $stmt->bindParam(':updatedBy', $data['updatedBy'], PDO::PARAM_INT);
        $stmt->bindParam(':status', $data['status'], PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return true;
        }

        return false;
    }

    public function store($data)
    {
        $sql = "INSERT INTO `memberships`
                SET `description` = :description,
                    `price` = :price,
                    `created_by` = :createdBy,
                    `created_at` = :createdAt,
                    `updated_at` = :updatedAt,
                    `updated_by` = :updatedBy,
                    `status` = :status";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':description', $data['description']);
        $stmt->bindParam(':price', $data['price']);
        $stmt->bindParam(':createdBy', $data['createdBy'],  PDO::PARAM_INT);
        $stmt->bindParam(':createdAt', $data['createdAt']);
        $stmt->bindParam(':updatedAt', $data['updatedAt']);
        $stmt->bindParam(':updatedBy', $data['updatedBy'], PDO::PARAM_INT);
        $stmt->bindParam(':status', $data['status'], PDO::PARAM_INT);
        $stmt->execute();

        if ($this->conn->lastInsertId()) {
            return true;
        }

        return false;       
    }
}
?>