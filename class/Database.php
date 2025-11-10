<?php
class Database {
	public $conn;
	private $host = 'localhost'; // Hostinger MySQL host (usually 'localhost')
	private $username = 'u281361146_root'; // Your Hostinger MySQL username
	private $password = ''; // TODO: Add your Hostinger MySQL password here
	private $db = 'u281361146_gym'; // Your Hostinger database name

	public function connection() {
		try {
		    $this->conn = new PDO('mysql:host=' . $this->host . ';dbname=' . $this->db, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            return $this->conn;
		} catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            return null;
		}
    }
}
?>