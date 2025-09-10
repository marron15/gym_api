<?php
require_once 'Database.php';
require_once 'JWT.php';

class Admin
{
    private $conn;

    public function __construct()
    {
        $db = new Database();
        $this->conn = $db->connection();
        
        if (!$this->conn) {
            throw new Exception('Database connection failed');
        }
    }

    public function getAll()
    {
        $sql = "SELECT * FROM `admins`";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $result;
    }

    public function getById($id)
    {
        $sql = "SELECT * FROM `admins` WHERE `id` = :id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $result;
    }

    public function deleteById($id)
    {
        // Soft delete: archive admin by setting status to 'inactive'
        $sql = "UPDATE `admins` SET `status` = 'inactive', `updated_at` = NOW() WHERE id = :id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $executed = $stmt->execute();

        // Treat already-inactive (0 affected rows) as success
        return $executed;
    }

    public function activateById($id)
    {
        $sql = "UPDATE `admins` SET `status` = 'active', `updated_at` = NOW() WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $executed = $stmt->execute();
        // Also consider no-op as success
        return $executed;
    }

    public function updateAdminByID($id, $data)
    {
        try {
            // Handle date_of_birth - provide default if null
            $dateOfBirth = $data['dateOfBirth'] ?? null;
            if (empty($dateOfBirth)) {
                // Set a default date (18 years ago) if none provided
                $dateOfBirth = date('Y-m-d', strtotime('-18 years'));
            }

            // Check if password is included in the data
            if (isset($data['password']) && !empty($data['password'])) {
                // Include password in update (for password changes)
                $sql = "UPDATE `admins` SET 
                        `first_name` = :firstName,
                        `middle_name` = :middleName,
                        `last_name` = :lastName,
                        `date_of_birth` = :dateOfBirth,
                        `email_address` = :emailAddress,
                        `password` = :password,
                        `phone_number` = :phoneNumber,
                        `updated_by` = :updatedBy,
                        `updated_at` = :updatedAt
                        WHERE id = :id";
                
                $stmt = $this->conn->prepare($sql);
                $stmt->bindParam(':id', $id, PDO::PARAM_INT);
                $stmt->bindParam(':firstName', $data['firstName']);
                $stmt->bindParam(':middleName', $data['middleName']);
                $stmt->bindParam(':lastName', $data['lastName']);
                $stmt->bindParam(':dateOfBirth', $dateOfBirth);
                $stmt->bindParam(':emailAddress', $data['emailAddress']);
                $stmt->bindParam(':password', $data['password']);
                $stmt->bindParam(':phoneNumber', $data['phoneNumber']);
                $stmt->bindParam(':updatedBy', $data['updatedBy']);
                $stmt->bindParam(':updatedAt', $data['updatedAt']);
            } else {
                // Exclude password from update (for profile updates)
                $sql = "UPDATE `admins` SET 
                        `first_name` = :firstName,
                        `middle_name` = :middleName,
                        `last_name` = :lastName,
                        `date_of_birth` = :dateOfBirth,
                        `email_address` = :emailAddress,
                        `phone_number` = :phoneNumber,
                        `updated_by` = :updatedBy,
                        `updated_at` = :updatedAt
                        WHERE id = :id";
                
                $stmt = $this->conn->prepare($sql);
                $stmt->bindParam(':id', $id, PDO::PARAM_INT);
                $stmt->bindParam(':firstName', $data['firstName']);
                $stmt->bindParam(':middleName', $data['middleName']);
                $stmt->bindParam(':lastName', $data['lastName']);
                $stmt->bindParam(':dateOfBirth', $dateOfBirth);
                $stmt->bindParam(':emailAddress', $data['emailAddress']);
                $stmt->bindParam(':phoneNumber', $data['phoneNumber']);
                $stmt->bindParam(':updatedBy', $data['updatedBy']);
                $stmt->bindParam(':updatedAt', $data['updatedAt']);
            }
            
            $executed = $stmt->execute();

            // Consider the update successful if the statement executed, even if 0 rows changed
            return $executed;
            
        } catch (Exception $e) {
            error_log("Error updating admin: " . $e->getMessage());
            return false;
        }
    }

    public function store($data)
    {
        $sql = "INSERT INTO `admins`
                SET `first_name` = :firstName,
                    `middle_name` = :middleName,
                    `last_name` = :lastName,
                    `date_of_birth` = :dateOfBirth,
                    `email_address` = :emailAddress,
                    `password` = :password,
                    `phone_number` = :phoneNumber,
                    `created_by` = :createdBy,
                    `created_at` = :createdAt";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':firstName', $data['firstName']);
        $stmt->bindParam(':middleName', $data['middleName']);
        $stmt->bindParam(':lastName', $data['lastName']);
        $stmt->bindParam(':dateOfBirth', $data['dateOfBirth']);
        $stmt->bindParam(':emailAddress', $data['emailAddress']);
        $stmt->bindParam(':password', $data['password']);
        $stmt->bindParam(':phoneNumber', $data['phoneNumber']);
        $stmt->bindParam(':createdBy', $data['createdBy']);
        $stmt->bindParam(':createdAt', $data['createdAt']);
        $stmt->execute();

        if ($this->conn->lastInsertId()) {
            return true;
        }

        return false;       
    }

    public function login($phoneNumber, $password)
    {
        try {
            // Get admin by contact number
            $sql = "SELECT * FROM `admins` WHERE `phone_number` = :phone LIMIT 1";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':phone', $phoneNumber);
            $stmt->execute();
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$admin) {
                return [
                    'success' => false,
                    'message' => 'Invalid contact number or password'
                ];
            }
            
            // Verify password using plain text comparison (as requested by admin)
            $passwordVerified = ($password === $admin['password']);
            
            if ($passwordVerified) {
                // Remove password from response for security
                unset($admin['password']);
                
                // Generate JWT token
                $tokenPayload = [
                    'admin_id' => $admin['id'],
                    'email' => $admin['email_address'],
                    'first_name' => $admin['first_name'],
                    'last_name' => $admin['last_name']
                ];
                
                $accessToken = JWT::encode($tokenPayload, 24); // 24 hours
                $refreshToken = JWT::encode($tokenPayload, 168); // 7 days
                
                return [
                    'success' => true,
                    'message' => 'Login successful',
                    'admin' => $admin,
                    'access_token' => $accessToken,
                    'refresh_token' => $refreshToken,
                    'token_type' => 'Bearer',
                    'expires_in' => 24 * 3600 // 24 hours in seconds
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Invalid contact number or password'
                ];
            }
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }

    public function getByEmail($email)
    {
        $sql = "SELECT * FROM `admins` WHERE `email_address` = :email";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result;
    }

    public function signup($data)
    {
        try {
            // Check if email already exists
            $existingAdmin = $this->getByEmail($data['email_address']);
            if ($existingAdmin) {
                return [
                    'success' => false,
                    'message' => 'Email already exists'
                ];
            }

            // Store password in plain text (as requested by admin)
            $plainPassword = $data['password'];

            // Handle date_of_birth - provide default if null
            $dateOfBirth = $data['date_of_birth'] ?? null;
            if (empty($dateOfBirth)) {
                // Set a default date (18 years ago) if none provided
                $dateOfBirth = date('Y-m-d', strtotime('-18 years'));
            }

            // Prepare data for insertion
            $insertData = [
                'firstName' => $data['first_name'],
                'middleName' => $data['middle_name'] ?? null,
                'lastName' => $data['last_name'],
                'dateOfBirth' => $dateOfBirth,
                'emailAddress' => $data['email_address'],
                'password' => $plainPassword,
                'phoneNumber' => $data['phone_number'] ?? null,
                'createdBy' => $data['created_by'] ?? 'system',
                'createdAt' => date('Y-m-d H:i:s'),

            ];

            // Insert admin
            if ($this->store($insertData)) {
                // Get the newly created admin
                $newAdmin = $this->getByEmail($data['email_address']);
                unset($newAdmin['password']); // Remove password from response

                // Generate JWT token for new admin
                $tokenPayload = [
                    'admin_id' => $newAdmin['id'],
                    'email' => $newAdmin['email_address'],
                    'first_name' => $newAdmin['first_name'],
                    'last_name' => $newAdmin['last_name']
                ];
                
                $accessToken = JWT::encode($tokenPayload, 24); // 24 hours
                $refreshToken = JWT::encode($tokenPayload, 168); // 7 days

                return [
                    'success' => true,
                    'message' => 'Admin registered successfully',
                    'admin' => $newAdmin,
                    'access_token' => $accessToken,
                    'refresh_token' => $refreshToken,
                    'token_type' => 'Bearer',
                    'expires_in' => 24 * 3600 // 24 hours in seconds
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to create admin account'
                ];
            }

        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }

    public function getAllWithPasswords()
    {
        try {
            $admins = $this->getAll();
            
            foreach ($admins as &$admin) {
                // Keep password for admin access
                // Password is already included from getAll()
            }
            
            return $admins;
        } catch (Exception $e) {
            error_log("Error getting all admins with passwords: " . $e->getMessage());
            return $this->getAll(); // Fallback to admins without passwords
        }
    }

    public function getAllWithoutPasswords()
    {
        try {
            $admins = $this->getAll();
            
            foreach ($admins as &$admin) {
                // Remove password for security
                unset($admin['password']);
            }
            
            return $admins;
        } catch (Exception $e) {
            error_log("Error getting all admins without passwords: " . $e->getMessage());
            return $this->getAll(); // Fallback to admins with passwords
        }
    }
}
?>
