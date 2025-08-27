<?php
require_once 'Database.php';

class CustomersAddress
{
    public function __construct()
    {
        $db = new Database();
        $this->conn = $db->connection();
    }

    public function getAll()
    {
        $sql = "SELECT * FROM `customer_address`";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $result;
    }

    public function getById($id)
    {
        $sql = "SELECT * FROM `customer_address` WHERE `id` = :id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $result;
    }

    public function deleteById($id)
    {
        $sql = "DELETE FROM `customer_address` WHERE id = :id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return true;
        }

        return false;
    }

    public function deleteByCustomerId($customerId)
    {
        $sql = "DELETE FROM `customer_address` WHERE customer_id = :customerId";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':customerId', $customerId);
        $stmt->execute();

        return true; // deleting zero or more is fine
    }

    public function updateCustomersAddressByID($id, $data)
    {
        $sql = "UPDATE `customer_address` SET 
                `customer_id` = :customerId,
                `street` = :street,
                `city` = :city,
                `state` = :state,
                `postal_code` = :postalCode,
                `country` = :country,
                `updated_by` = :updatedBy,
                `updated_at` = :updatedAt
                WHERE id = :id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':customerId', $data['customerId']);
        $stmt->bindParam(':street', $data['street']);
        $stmt->bindParam(':city', $data['city']);
        $stmt->bindParam(':state', $data['state']);
        $stmt->bindParam(':postalCode', $data['postalCode']);
        $stmt->bindParam(':country', $data['country']);
        $stmt->bindParam(':updatedBy', $data['updatedBy']);
        $stmt->bindParam(':updatedAt', $data['updatedAt']);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return true;
        }

        return false;
    }

    public function store($data)
    {
        $sql = "INSERT INTO `customer_address`
                SET `customer_id` = :customerId,
                    `street` = :street,
                    `city` = :city,
                    `state` = :state,
                    `postal_code` = :postalCode,
                    `country` = :country,
                    `created_by` = :createdBy,
                    `created_at` = :createdAt";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':customerId', $data['customerId']);
        $stmt->bindParam(':street', $data['street']);
        $stmt->bindParam(':city', $data['city']);
        $stmt->bindParam(':state', $data['state']);
        $stmt->bindParam(':postalCode', $data['postalCode']);
        $stmt->bindParam(':country', $data['country']);
        $stmt->bindParam(':createdBy', $data['createdBy'], PDO::PARAM_INT);
        $stmt->bindParam(':createdAt', $data['createdAt']);
        $stmt->execute();

        if ($this->conn->lastInsertId()) {
            return true;
        }

        return false;       
    }

    public function getByCustomerId($customerId)
    {
        $sql = "SELECT * FROM `customer_address` WHERE `customer_id` = :customerId";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':customerId', $customerId);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $result;
    }

    /**
     * Update address by ID with simplified data
     */
    public function updateById($id, $data)
    {
        $sql = "UPDATE `customer_address` SET 
                `street` = :street,
                `city` = :city,
                `state` = :state,
                `postal_code` = :postalCode,
                `country` = :country,
                `updated_by` = :updatedBy,
                `updated_at` = :updatedAt
                WHERE id = :id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':street', $data['street']);
        $stmt->bindParam(':city', $data['city']);
        $stmt->bindParam(':state', $data['state']);
        $stmt->bindParam(':postalCode', $data['postal_code']);
        $stmt->bindParam(':country', $data['country']);
        $stmt->bindParam(':updatedBy', 'admin_update');
        $stmt->bindParam(':updatedAt', date('Y-m-d H:i:s'));
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /**
     * Insert new address with simplified data
     */
    public function insert($data)
    {
        $sql = "INSERT INTO `customer_address`
                SET `customer_id` = :customerId,
                    `street` = :street,
                    `city` = :city,
                    `state` = :state,
                    `postal_code` = :postalCode,
                    `country` = :country,
                    `created_by` = :createdBy,
                    `created_at` = :createdAt";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':customerId', $data['customer_id']);
        $stmt->bindParam(':street', $data['street']);
        $stmt->bindParam(':city', $data['city']);
        $stmt->bindParam(':state', $data['state']);
        $stmt->bindParam(':postalCode', $data['postal_code']);
        $stmt->bindParam(':country', $data['country']);
        $stmt->bindParam(':createdBy', 'admin_insert');
        $stmt->bindParam(':createdAt', date('Y-m-d H:i:s'));
        $stmt->execute();

        return $this->conn->lastInsertId() ? true : false;
    }
}
?>
