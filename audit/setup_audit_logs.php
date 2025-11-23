<?php

require_once '../class/Database.php';

echo "Setting up customer audit log schema...\n\n";

try {
    $db = new Database();
    $conn = $db->connection();

    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    echo "✅ Connected to database\n\n";

    $tableName = 'customer_audit_logs';
    echo "1. Checking if `$tableName` table exists...\n";
    $stmt = $conn->query("SHOW TABLES LIKE '{$tableName}'");
    $exists = $stmt->rowCount() > 0;

    if ($exists) {
        echo "   ✅ Table already exists\n";
    } else {
        echo "   ❌ Table not found. Creating...\n";
        $createSql = "
            CREATE TABLE `customer_audit_logs` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `customer_id` int DEFAULT NULL,
                `customer_name` varchar(255) DEFAULT NULL,
                `admin_id` int DEFAULT NULL,
                `actor_type` varchar(32) NOT NULL DEFAULT 'system',
                `actor_name` varchar(255) DEFAULT NULL,
                `activity_category` varchar(32) NOT NULL,
                `activity_type` varchar(64) NOT NULL,
                `activity_title` varchar(255) NOT NULL,
                `description` text DEFAULT NULL,
                `metadata_json` longtext DEFAULT NULL,
                `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_customer_id` (`customer_id`),
                KEY `idx_activity_type` (`activity_type`),
                KEY `idx_activity_category` (`activity_category`),
                KEY `idx_created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        $conn->exec($createSql);
        echo "   ✅ Table created\n";
    }

    echo "\n2. Table preview:\n";
    $stmt = $conn->query("DESCRIBE {$tableName}");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        echo sprintf(
            "   - %-20s %-20s %s\n",
            $column['Field'],
            $column['Type'],
            $column['Null'] === 'NO' ? 'NOT NULL' : 'NULL'
        );
    }

    echo "\n✅ Audit log schema ready!\n";
} catch (Exception $e) {
    echo "❌ Setup failed: " . $e->getMessage() . "\n";
    exit(1);
}

?>

