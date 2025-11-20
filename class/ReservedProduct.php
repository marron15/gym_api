<?php

require_once 'Database.php';

class ReservedProduct
{
    private $conn;

    public function __construct()
    {
        $db = new Database();
        $this->conn = $db->connection();
    }

    private function ensureConnection()
    {
        if (!$this->conn) {
            throw new Exception('Database connection unavailable.');
        }
    }

    public function createReservation($customerId, $productId, $quantity, $notes = '')
    {
        try {
            $this->ensureConnection();
            $this->conn->beginTransaction();

            $lockStmt = $this->conn->prepare(
                'SELECT `quantity` FROM `products` WHERE `id` = :productId FOR UPDATE'
            );
            $lockStmt->bindParam(':productId', $productId, PDO::PARAM_INT);
            $lockStmt->execute();
            $product = $lockStmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Product not found.'];
            }

            $availableQty = (int) $product['quantity'];
            if ($availableQty < $quantity) {
                $this->conn->rollBack();
                return [
                    'success' => false,
                    'message' => 'Insufficient stock for this product.',
                ];
            }

            $now = date('Y-m-d H:i:s');
            $status = 'pending';

            $insertStmt = $this->conn->prepare(
                'INSERT INTO `reserved_products`
                (`customer_id`, `product_id`, `quantity`, `notes`, `status`, `created_at`, `updated_at`)
                VALUES (:customerId, :productId, :quantity, :notes, :status, :createdAt, :updatedAt)'
            );

            $insertStmt->execute([
                ':customerId' => $customerId,
                ':productId' => $productId,
                ':quantity' => $quantity,
                ':notes' => $notes,
                ':status' => $status,
                ':createdAt' => $now,
                ':updatedAt' => $now,
            ]);

            $updateProduct = $this->conn->prepare(
                'UPDATE `products`
                 SET `quantity` = `quantity` - :quantity,
                     `updated_at` = :updatedAt
                 WHERE `id` = :productId'
            );
            $updateProduct->execute([
                ':quantity' => $quantity,
                ':updatedAt' => $now,
                ':productId' => $productId,
            ]);

            $this->conn->commit();
            return [
                'success' => true,
                'reservation_id' => (int) $this->conn->lastInsertId(),
            ];
        } catch (Exception $e) {
            if ($this->conn && $this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getReservations($status = null)
    {
        try {
            $this->ensureConnection();
            $params = [];
            $where = '';
            if ($status !== null && $status !== '') {
                $where = 'WHERE rp.status = :status';
                $params[':status'] = strtolower($status);
            }

            $sql = "
                SELECT
                    rp.*,
                    p.name AS product_name,
                    p.description AS product_description,
                    c.first_name,
                    c.last_name,
                    c.email
                FROM `reserved_products` rp
                LEFT JOIN `products` p ON p.id = rp.product_id
                LEFT JOIN `customers` c ON c.id = rp.customer_id
                $where
                ORDER BY rp.created_at DESC";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    public function getReservationsByCustomer($customerId, $status = null)
    {
        try {
            $this->ensureConnection();
            $params = [':customerId' => $customerId];
            $where = 'WHERE rp.customer_id = :customerId';
            
            if ($status !== null && $status !== '') {
                $where .= ' AND rp.status = :status';
                $params[':status'] = strtolower($status);
            }

            $sql = "
                SELECT
                    rp.*,
                    p.name AS product_name,
                    p.description AS product_description,
                    c.first_name,
                    c.last_name,
                    c.email
                FROM `reserved_products` rp
                LEFT JOIN `products` p ON p.id = rp.product_id
                LEFT JOIN `customers` c ON c.id = rp.customer_id
                $where
                ORDER BY rp.created_at DESC";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'data' => $results
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => []
            ];
        }
    }

    public function updateStatus($reservationId, $status, $declineNote = null)
    {
        $allowedStatuses = ['pending', 'accepted', 'declined', 'cancelled'];
        $status = strtolower($status);

        if (!in_array($status, $allowedStatuses, true)) {
            return ['success' => false, 'message' => 'Invalid status provided.'];
        }

        try {
            $this->ensureConnection();
            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare(
                'SELECT * FROM `reserved_products` WHERE `id` = :id FOR UPDATE'
            );
            $stmt->bindParam(':id', $reservationId, PDO::PARAM_INT);
            $stmt->execute();
            $reservation = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$reservation) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Reservation not found.'];
            }

            $currentStatus = strtolower($reservation['status']);
            if ($currentStatus === $status) {
                $this->conn->rollBack();
                return ['success' => true, 'message' => 'Status unchanged.'];
            }

            $quantity = (int) $reservation['quantity'];
            $productId = (int) $reservation['product_id'];
            $now = date('Y-m-d H:i:s');

            $wasInactive = in_array($currentStatus, ['declined', 'cancelled'], true);
            $becomesInactive = in_array($status, ['declined', 'cancelled'], true);

            if ($becomesInactive && !$wasInactive) {
                $restore = $this->conn->prepare(
                    'UPDATE `products`
                     SET `quantity` = `quantity` + :qty,
                         `updated_at` = :updatedAt
                     WHERE `id` = :productId'
                );
                $restore->execute([
                    ':qty' => $quantity,
                    ':updatedAt' => $now,
                    ':productId' => $productId,
                ]);
            } elseif (!$becomesInactive && $wasInactive) {
                $release = $this->conn->prepare(
                    'SELECT `quantity` FROM `products` WHERE `id` = :productId FOR UPDATE'
                );
                $release->execute([':productId' => $productId]);
                $product = $release->fetch(PDO::FETCH_ASSOC);
                if (!$product || (int) $product['quantity'] < $quantity) {
                    $this->conn->rollBack();
                    return [
                        'success' => false,
                        'message' => 'Insufficient stock to re-open reservation.',
                    ];
                }

                $deduct = $this->conn->prepare(
                    'UPDATE `products`
                     SET `quantity` = `quantity` - :qty,
                         `updated_at` = :updatedAt
                     WHERE `id` = :productId'
                );
                $deduct->execute([
                    ':qty' => $quantity,
                    ':updatedAt' => $now,
                    ':productId' => $productId,
                ]);
            }

            // Build update query with optional decline_note
            $updateFields = '`status` = :status, `updated_at` = :updatedAt';
            $updateParams = [
                ':status' => $status,
                ':updatedAt' => $now,
                ':id' => $reservationId,
            ];

            if ($declineNote !== null && $declineNote !== '') {
                $updateFields .= ', `decline_note` = :declineNote';
                $updateParams[':declineNote'] = $declineNote;
            }

            $update = $this->conn->prepare(
                "UPDATE `reserved_products`
                 SET $updateFields
                 WHERE `id` = :id"
            );
            $update->execute($updateParams);

            $this->conn->commit();
            return ['success' => true];
        } catch (Exception $e) {
            if ($this->conn && $this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}

?>

