<?php
require_once 'Database.php';

class Products
{
    public function __construct()
    {
        $db = new Database();
        $this->conn = $db->connection();
    }

    public function getAll()
    {
        $sql = "SELECT * FROM `products`";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $result;
    }

    public function getById($id)
    {
        $sql = "SELECT * FROM `products` WHERE `id` = :id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $result;
    }

    public function deleteById($id)
    {
        $sql = "DELETE FROM `products` WHERE id = :id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return true;
        }

        return false;
    }

    public function updateProductsByID($id, $data)
    {
        $sql = "UPDATE `products` SET 
                `name` = :name,
                `description` = :description,
                `price` = :price,
                `status` = :status,
                `img` = :img,
                `updated_by` = :updatedBy,
                `updated_at` = :updatedAt
                WHERE id = :id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':description', $data['description']);
        $stmt->bindParam(':price', $data['price']);
        $stmt->bindParam(':status', $data['status'], PDO::PARAM_INT);
        $stmt->bindParam(':img', $data['img']);
        $stmt->bindParam(':updatedBy', $data['updatedBy'], PDO::PARAM_INT);
        $stmt->bindParam(':updatedAt', $data['updatedAt']);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return true;
        }

        return false;
    }

    public function store($data)
    {
        $sql = "INSERT INTO `products`
                SET `name` = :name,
                    `description` = :description,
                    `price` = :price,
                    `status` = :status,
                    `img` = :img,
                    `created_by` = :createdBy,
                    `created_at` = :createdAt";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':description', $data['description']);
        $stmt->bindParam(':price', $data['price']);
        $stmt->bindParam(':status', $data['status'], PDO::PARAM_INT);
        $stmt->bindParam(':img', $data['img']);
        $stmt->bindParam(':createdBy', $data['createdBy'], PDO::PARAM_INT);
        $stmt->bindParam(':createdAt', $data['createdAt']);
        $stmt->execute();

        if ($this->conn->lastInsertId()) {
            return true;
        }

        return false;       
    }

    public function getByStatus($status)
    {
        $sql = "SELECT * FROM `products` WHERE `status` = :status";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':status', $status, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $result;
    }

    public function getActiveProducts()
    {
        return $this->getByStatus(1);
    }

    public function getInactiveProducts()
    {
        return $this->getByStatus(0);
    }

    public function searchByName($name)
    {
        $sql = "SELECT * FROM `products` WHERE `name` LIKE :name";

        $stmt = $this->conn->prepare($sql);
        $searchName = '%' . $name . '%';
        $stmt->bindParam(':name', $searchName);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $result;
    }

    public function getByPriceRange($minPrice, $maxPrice)
    {
        $sql = "SELECT * FROM `products` WHERE `price` BETWEEN :minPrice AND :maxPrice";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':minPrice', $minPrice);
        $stmt->bindParam(':maxPrice', $maxPrice);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $result;
    }
}
?>
