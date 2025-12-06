<?php
require_once 'Database.php';

class Products
{
    public function __construct()
    {
        $db = new Database();
        $this->conn = $db->connection();
    }

    private function hasColumn($column)
    {
        try {
            $stmt = $this->conn->prepare("SHOW COLUMNS FROM `products` LIKE :col");
            $stmt->bindParam(':col', $column);
            $stmt->execute();
            return (bool)$stmt->fetch();
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function updatedByColumn()
    {
        // Some schemas use `update_by` instead of `updated_by`
        return $this->hasColumn('updated_by') ? 'updated_by' : ($this->hasColumn('update_by') ? 'update_by' : null);
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
        $updatedByCol = $this->updatedByColumn();
        $hasStatus = $this->hasColumn('status');

        $sql = "UPDATE `products` SET 
                `name` = :name,
                `description` = :description,
                " . ($hasStatus ? "`status` = :status,\n                " : "") .
                "`img` = :img,
                " . ($updatedByCol ? "`$updatedByCol` = :updatedBy,\n                " : "") .
                "`updated_at` = :updatedAt
                WHERE id = :id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':description', $data['description']);
        if ($hasStatus) {
            $stmt->bindParam(':status', $data['status']);
        }
        $stmt->bindParam(':img', $data['img']);
        if ($updatedByCol) {
            $stmt->bindParam(':updatedBy', $data['updatedBy'], PDO::PARAM_INT);
        }
        $stmt->bindParam(':updatedAt', $data['updatedAt']);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return true;
        }

        return false;
    }

    public function store($data)
    {
        $hasStatus = $this->hasColumn('status');
        $hasCreatedBy = $this->hasColumn('created_by');

        $sql = "INSERT INTO `products`
                SET `name` = :name,
                    `description` = :description,
                    " . ($hasStatus ? "`status` = :status,\n                    " : "") .
                    "`img` = :img,
                    " . ($hasCreatedBy ? "`created_by` = :createdBy,\n                    " : "") .
                    "`created_at` = :createdAt";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':description', $data['description']);
        if ($hasStatus) {
            $stmt->bindParam(':status', $data['status']);
        }
        $stmt->bindParam(':img', $data['img']);
        if ($hasCreatedBy) {
            $stmt->bindParam(':createdBy', $data['createdBy'], PDO::PARAM_INT);
        }
        $stmt->bindParam(':createdAt', $data['createdAt']);
        $stmt->execute();

        if ($this->conn->lastInsertId()) {
            return true;
        }

        return false;       
    }

    public function getByStatus($status)
    {
        if (!$this->hasColumn('status')) {
            return $this->getAll();
        }

        // Normalize to support both numeric and string schemas
        $statusStr = is_numeric($status)
            ? ((intval($status) === 1) ? 'active' : 'inactive')
            : strtolower(trim((string)$status));

        $statusNum = null;
        if ($statusStr === 'active') $statusNum = '1';
        if ($statusStr === 'inactive') $statusNum = '0';

        if ($statusNum !== null) {
            $sql = "SELECT * FROM `products` WHERE `status` = :s1 OR `status` = :s2";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':s1', $statusStr);
            $stmt->bindParam(':s2', $statusNum);
        } else {
            $sql = "SELECT * FROM `products` WHERE `status` = :s1";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':s1', $statusStr);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getActiveProducts()
    {
        return $this->getByStatus(1);
    }

    public function getInactiveProducts()
    {
        return $this->getByStatus(0);
    }

    public function setStatusById($id, $status)
    {
        if (!$this->hasColumn('status')) {
            return false;
        }
        $sql = "UPDATE `products` SET `status` = :status, `updated_at` = :updatedAt WHERE id = :id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':updatedAt', date('Y-m-d H:i:s'));
        $stmt->execute();

        return $stmt->rowCount() > 0;
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
