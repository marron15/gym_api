<?php

require_once __DIR__ . '/Database.php';

class SignupVerification
{
    private $conn;
    private $table = 'pending_customer_verifications';
    private $ttlMinutes;
    private $maxAttempts;

    public function __construct()
    {
        $db = new Database();
        $this->conn = $db->connection();
        if (!$this->conn) {
            throw new Exception('Database connection failed');
        }

        $config = require __DIR__ . '/../config/email.php';
        $this->ttlMinutes = (int)($config['verification_ttl_minutes'] ?? 10);
        $this->maxAttempts = (int)($config['max_attempts'] ?? 5);
    }

    public function createOrUpdate(array $payload): array
    {
        if (empty($payload['email'])) {
            throw new \InvalidArgumentException('Email is required for verification.');
        }

        $email = strtolower(trim($payload['email']));
        $code = (string)random_int(100000, 999999);
        $codeHash = password_hash($code, PASSWORD_DEFAULT);
        $expiresAt = date('Y-m-d H:i:s', time() + $this->ttlMinutes * 60);
        $payloadJson = json_encode($payload);

        $sql = "
            INSERT INTO {$this->table} (email, code_hash, payload_json, expires_at, attempts, created_at, updated_at)
            VALUES (:email, :code_hash, :payload_json, :expires_at, 0, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                code_hash = VALUES(code_hash),
                payload_json = VALUES(payload_json),
                expires_at = VALUES(expires_at),
                attempts = 0,
                updated_at = NOW()
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':code_hash', $codeHash);
        $stmt->bindParam(':payload_json', $payloadJson);
        $stmt->bindParam(':expires_at', $expiresAt);
        $stmt->execute();

        return [
            'code' => $code,
            'expires_at' => $expiresAt,
            'ttl_minutes' => $this->ttlMinutes,
        ];
    }

    public function getPendingByEmail(string $email): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $normalizedEmail = strtolower(trim($email));
        $stmt->bindParam(':email', $normalizedEmail);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function incrementAttempts(int $id): void
    {
        $sql = "
            UPDATE {$this->table}
            SET attempts = attempts + 1, updated_at = NOW()
            WHERE id = :id
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function deleteById(int $id): void
    {
        $sql = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }
}

