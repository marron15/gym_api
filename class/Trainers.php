<?php

require_once 'Database.php';

class Trainers
{
    public function __construct()
    {
        $db = new Database();
        $this->conn = $db->connection();
    }

    public function getAll()
    {
        $sql = "SELECT * FROM `trainers`";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $result;
    }

    public function getById($id)
    {
        $sql = "SELECT * FROM `trainers` WHERE `id` = :id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $result;
    }

    public function deleteById($id)
    {
        $sql = "DELETE FROM `trainers` WHERE id = :id";

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
        $sql = "UPDATE `trainers` SET 
                `first_name` = :firstName,
                `middle_name` = :middleName,
                `last_name` = :lastName,
                `contact_number` = :contactNumber,
                `created_by` = :createdBy,
                `created_at` = :createdAt,
                `updated_at` = :updatedAt,
                `updated_by` = :updatedBy
                WHERE id = :id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':firstName', $data['firstName']);
        $stmt->bindParam(':middleName', $data['middleName']);
        $stmt->bindParam(':lastName', $data['lastName']);
        $stmt->bindParam(':contactNumber', $data['contactNumber']);
        $stmt->bindParam(':createdBy', $data['createdBy'], PDO::PARAM_INT);
        $stmt->bindParam(':createdAt', $data['createdAt']);
        $stmt->bindParam(':updatedAt', $data['updatedAt']);
        $stmt->bindParam(':updatedBy', $data['updatedBy'], PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return true;
        }

        return false;
    }

    public function store($data)
    {
        $sql = "INSERT INTO `trainers`
                SET `first_name` = :firstName,
                    `middle_name` = :middleName,
                    `last_name` = :lastName,
                    `contact_number` = :contactNumber,
                    `status` = :status,
                    `created_by` = :createdBy,
                    `created_at` = :createdAt,
                    `updated_at` = :updatedAt,
                    `updated_by` = :updatedBy";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':firstName', $data['firstName']);
        $stmt->bindParam(':middleName', $data['middleName']);
        $stmt->bindParam(':lastName', $data['lastName']);
        $stmt->bindParam(':contactNumber', $data['contactNumber']);
        $stmt->bindParam(':status', $data['status']);
        $stmt->bindParam(':createdBy', $data['createdBy'],  PDO::PARAM_INT);
        $stmt->bindParam(':createdAt', $data['createdAt']);
        $stmt->bindParam(':updatedAt', $data['updatedAt']);
        $stmt->bindParam(':updatedBy', $data['updatedBy'], PDO::PARAM_INT);
        $stmt->execute();

        if ($this->conn->lastInsertId()) {
            return true;
        }

        return false;       
    }
    public function setStatusById($id, $status)
    {
        $sql = "UPDATE `trainers` SET `status` = :status, `updated_at` = NOW() WHERE id = :id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':status', $status);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return true;
        }

        return false;
    }

    public function archiveById($id)
    {
        return $this->setStatusById($id, 'inactive');
    }

    public function restoreById($id)
    {
        return $this->setStatusById($id, 'active');
    }
}
?>