<?php
// Database setup utility for customer_address table
require_once '../class/Database.php';

echo "Setting up Database Schema...\n\n";

try {
    $db = new Database();
    $conn = $db->connection();
    
    if (!$conn) {
        echo "❌ Database connection failed\n";
        exit;
    }
    
    echo "✅ Database connected successfully\n\n";
    
    // Check if customer_address table exists
    echo "1. Checking if customer_address table exists...\n";
    $stmt = $conn->query("SHOW TABLES LIKE 'customer_address'");
    $tableExists = $stmt->rowCount() > 0;
    
    if ($tableExists) {
        echo "   ✅ customer_address table exists\n";
    } else {
        echo "   ❌ customer_address table does not exist\n";
        echo "   Creating table...\n";
        
        $createTableSQL = "
        CREATE TABLE `customer_address` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `customer_id` int(11) NOT NULL,
            `street` varchar(255) DEFAULT NULL,
            `city` varchar(100) DEFAULT NULL,
            `state` varchar(100) DEFAULT NULL,
            `postal_code` varchar(20) DEFAULT NULL,
            `country` varchar(100) DEFAULT 'Philippines',
            `created_by` varchar(50) DEFAULT 'system',
            `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
            `updated_by` varchar(50) DEFAULT 'system',
            `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `customer_id` (`customer_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        
        $conn->exec($createTableSQL);
        echo "   ✅ customer_address table created successfully\n";
    }
    echo "\n";
    
    // Check table structure
    echo "2. Checking table structure...\n";
    $stmt = $conn->query("DESCRIBE customer_address");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "   Table columns:\n";
    foreach ($columns as $column) {
        echo "      - {$column['Field']}: {$column['Type']} " . 
             ($column['Null'] === 'NO' ? 'NOT NULL' : 'NULL') . 
             ($column['Default'] ? " DEFAULT '{$column['Default']}'" : '') . "\n";
    }
    echo "\n";
    
    // Check if customers table exists and has required fields
    echo "3. Checking customers table...\n";
    $stmt = $conn->query("SHOW TABLES LIKE 'customers'");
    $customersTableExists = $stmt->rowCount() > 0;
    
    if ($customersTableExists) {
        echo "   ✅ customers table exists\n";
        
        // Check customers table structure
        $stmt = $conn->query("DESCRIBE customers");
        $customerColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "   Customers table columns:\n";
        foreach ($customerColumns as $column) {
            echo "      - {$column['Field']}: {$column['Type']} " . 
                 ($column['Null'] === 'NO' ? 'NOT NULL' : 'NULL') . "\n";
        }
    } else {
        echo "   ❌ customers table does not exist\n";
    }
    echo "\n";
    
    // Check sample data
    echo "4. Checking sample data...\n";
    $stmt = $conn->query("SELECT COUNT(*) as count FROM customers");
    $customerCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "   Total customers: $customerCount\n";
    
    $stmt = $conn->query("SELECT COUNT(*) as count FROM customer_address");
    $addressCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "   Total addresses: $addressCount\n";
    
    if ($customerCount > 0) {
        echo "   Sample customer:\n";
        $stmt = $conn->query("SELECT id, first_name, last_name, email FROM customers LIMIT 1");
        $sampleCustomer = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "      ID: {$sampleCustomer['id']}\n";
        echo "      Name: {$sampleCustomer['first_name']} {$sampleCustomer['last_name']}\n";
        echo "      Email: {$sampleCustomer['email']}\n";
    }
    
    echo "\n✅ Database setup completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error during setup: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>
