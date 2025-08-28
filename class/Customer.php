<?php
require_once 'Database.php';
require_once 'JWT.php';
require_once 'CustomersAddress.php';

class Customer
{
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
        $sql = "SELECT * FROM `customers`";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $result;
    }

    public function getById($id)
    {
        $sql = "SELECT * FROM `customers` WHERE `id` = :id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $result;
    }

    public function deleteById($id)
    {
        $sql = "DELETE FROM `customers` WHERE id = :id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return true;
        }

        return false;
    }

    public function updateCustomersByID($id, $data)
    {
        try {
            // Handle address update separately in customers_address table
            $addressUpdated = true;
            if (isset($data['address']) && !empty($data['address'])) {
                $addressUpdated = $this->updateCustomerAddress($id, $data['address']);
            }
            
            // Check if password is included in the data
            if (isset($data['password']) && !empty($data['password'])) {
                // Include password in update (for password changes)
                $sql = "UPDATE `customers` SET 
                        `first_name` = :firstName,
                        `last_name` = :lastName,
                        `middle_name` = :middleName,
                        `email` = :email,
                        `password` = :password,
                        `birthdate` = :birthdate,
                        `phone_number` = :phoneNumber,
                        `updated_by` = :updatedBy,
                        `updated_at` = :updatedAt,
                        `emergency_contact_name` = :emergencyContactName,
                        `emergency_contact_number` = :emergencyContactNumber,
                        `status` = :status
                        WHERE id = :id";
                
                $stmt = $this->conn->prepare($sql);
                $stmt->bindParam(':id', $id, PDO::PARAM_INT);
                $stmt->bindParam(':firstName', $data['firstName']);
                $stmt->bindParam(':lastName', $data['lastName']);
                $stmt->bindParam(':middleName', $data['middleName']);
                $stmt->bindParam(':email', $data['email']);
                $stmt->bindParam(':password', $data['password']);
                $stmt->bindParam(':birthdate', $data['birthdate']);
                $stmt->bindParam(':phoneNumber', $data['phoneNumber']);
                $stmt->bindParam(':updatedBy', $data['updatedBy']);
                $stmt->bindParam(':updatedAt', $data['updatedAt']);
                $stmt->bindParam(':emergencyContactName', $data['emergencyContactName']);
                $stmt->bindParam(':emergencyContactNumber', $data['emergencyContactNumber']);
                $stmt->bindParam(':status', $data['status']);
            } else {
                // Exclude password from update (for profile updates)
                $sql = "UPDATE `customers` SET 
                        `first_name` = :firstName,
                        `last_name` = :lastName,
                        `middle_name` = :middleName,
                        `email` = :email,
                        `birthdate` = :birthdate,
                        `phone_number` = :phoneNumber,
                        `updated_by` = :updatedBy,
                        `updated_at` = :updatedAt,
                        `emergency_contact_name` = :emergencyContactName,
                        `emergency_contact_number` = :emergencyContactNumber,
                        `status` = :status
                        WHERE id = :id";
                
                $stmt = $this->conn->prepare($sql);
                $stmt->bindParam(':id', $id, PDO::PARAM_INT);
                $stmt->bindParam(':firstName', $data['firstName']);
                $stmt->bindParam(':lastName', $data['lastName']);
                $stmt->bindParam(':middleName', $data['middleName']);
                $stmt->bindParam(':email', $data['email']);
                $stmt->bindParam(':birthdate', $data['birthdate']);
                $stmt->bindParam(':phoneNumber', $data['phoneNumber']);
                $stmt->bindParam(':updatedBy', $data['updatedBy']);
                $stmt->bindParam(':updatedAt', $data['updatedAt']);
                $stmt->bindParam(':emergencyContactName', $data['emergencyContactName']);
                $stmt->bindParam(':emergencyContactNumber', $data['emergencyContactNumber']);
                $stmt->bindParam(':status', $data['status']);
            }
            
            $stmt->execute();

            // Return true if either user update succeeded or both user and address updates succeeded
            return ($stmt->rowCount() > 0) || $addressUpdated;
            
        } catch (Exception $e) {
            error_log("Error updating customer: " . $e->getMessage());
            return false;
        }
    }

    public function store($data)
    {
        $sql = "INSERT INTO `customers`
                SET `first_name` = :firstName,
                    `last_name` = :lastName,
                    `middle_name` = :middleName,
                    `email` = :email,
                    `password` = :password,
                    `birthdate` = :birthdate,
                    `phone_number` = :phoneNumber,
                    `created_by` = :createdBy,
                    `created_at` = :createdAt,
                    `emergency_contact_name` = :emergencyContactName,
                    `emergency_contact_number` = :emergencyContactNumber,
                    `status` = :status";


        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':firstName', $data['firstName']);
        $stmt->bindParam(':lastName', $data['lastName']);
        $stmt->bindParam(':middleName', $data['middleName']);
        $stmt->bindParam(':email', $data['email']);
        $stmt->bindParam(':password', $data['password']);
        $stmt->bindParam(':birthdate', $data['birthdate']);
        $stmt->bindParam(':phoneNumber', $data['phoneNumber']);
        $stmt->bindParam(':createdBy', $data['createdBy']);
        $stmt->bindParam(':createdAt', $data['createdAt']);
        $stmt->bindParam(':emergencyContactName', $data['emergencyContactName']);
        $stmt->bindParam(':emergencyContactNumber', $data['emergencyContactNumber']);
        $stmt->bindParam(':status', $data['status']);
        $stmt->execute();

        if ($this->conn->lastInsertId()) {
            return true;
        }

        return false;       
    }

    public function login($email, $password)
    {
        try {
            // Get user by email
            $sql = "SELECT * FROM `customers` WHERE `email` = :email LIMIT 1";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$customer) {
                return [
                    'success' => false,
                    'message' => 'Invalid email or password'
                ];
            }
            
            // Verify password using plain text comparison (as requested by admin)
            $passwordVerified = ($password === $customer['password']);
            
            if ($passwordVerified) {
                // Remove password from response for security
                unset($customer['password']);
                
                // Generate JWT token
                $tokenPayload = [
                    'customer_id' => $customer['id'],
                    'email' => $customer['email'],
                    'first_name' => $customer['first_name'],
                    'last_name' => $customer['last_name']
                ];
                
                $accessToken = JWT::encode($tokenPayload, 24); // 24 hours
                $refreshToken = JWT::encode($tokenPayload, 168); // 7 days
                
                return [
                    'success' => true,
                    'message' => 'Login successful',
                    'customer' => $customer,
                    'access_token' => $accessToken,
                    'refresh_token' => $refreshToken,
                    'token_type' => 'Bearer',
                    'expires_in' => 24 * 3600 // 24 hours in seconds
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Invalid email or password'
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
        $sql = "SELECT * FROM `customers` WHERE `email` = :email";
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
            $existingCustomer = $this->getByEmail($data['email']);
            if ($existingCustomer) {
                return [
                    'success' => false,
                    'message' => 'Email already exists'
                ];
            }

            // Store password in plain text (as requested by admin)
            $plainPassword = $data['password'];

            // Prepare data for insertion (excluding address - will be stored separately)
            $insertData = [
                'firstName' => $data['first_name'],
                'lastName' => $data['last_name'],
                'middleName' => $data['middle_name'] ?? null,
                'email' => $data['email'],
                'password' => $plainPassword,
                'birthdate' => $data['birthdate'] ?? null,
                'phoneNumber' => $data['phone_number'] ?? null,
                'createdBy' => $data['created_by'] ?? 'system',
                'createdAt' => date('Y-m-d H:i:s'),
                'emergencyContactName' => $data['emergency_contact_name'] ?? '',
                'emergencyContactNumber' => $data['emergency_contact_number'] ?? '',
                'status' => $data['status'] ?? null
            ];

            // Insert user
            if ($this->store($insertData)) {
                // Get the newly created customer
                $newCustomer = $this->getByEmail($data['email']);
                unset($newCustomer['password']); // Remove password from response

                // Store address in customers_address table if provided
                if (!empty($data['address'])) {
                    $this->storeCustomerAddress($newCustomer['id'], $data['address']);
                }

                // Add address information to user data
                $newCustomer = $this->getCustomerWithAddress($newCustomer['id']);
                if ($newCustomer) {
                    unset($newCustomer['password']); // Remove password from response again
                } else {
                    // Fallback if getUserWithAddress fails
                    $newCustomer = $this->getByEmail($data['email']);
                    unset($newCustomer['password']);
                }

                // Generate JWT token for new user
                $tokenPayload = [
                    'customer_id' => $newCustomer['id'],
                    'email' => $newCustomer['email'],
                    'first_name' => $newCustomer['first_name'],
                    'last_name' => $newCustomer['last_name']
                ];
                
                $accessToken = JWT::encode($tokenPayload, 24); // 24 hours
                $refreshToken = JWT::encode($tokenPayload, 168); // 7 days

                return [
                    'success' => true,
                    'message' => 'Customer registered successfully',
                    'customer' => $newCustomer,
                    'access_token' => $accessToken,
                    'refresh_token' => $refreshToken,
                    'token_type' => 'Bearer',
                    'expires_in' => 24 * 3600 // 24 hours in seconds
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to create customer account'
                ];
            }

        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }

    public function updateLoginMethod()
    {
        // Update the login method to use password hashing
        // This is a note for future reference to update the login method
        // to use password_verify() instead of plain text comparison
    }

    /**
     * Store user address in customers_address table
     */
    public function storeCustomerAddress($customerId, $addressString)
    {
        try {
            // Parse address string into components
            $addressComponents = $this->parseAddress($addressString);
            
            $customersAddress = new CustomersAddress();
            $addressData = [
                'customerId' => $customerId,
                'street' => $addressComponents['street'],
                'city' => $addressComponents['city'],
                'state' => $addressComponents['state'],
                'postalCode' => $addressComponents['postal_code'],
                'country' => $addressComponents['country'],
                'createdBy' => 'system',
                'createdAt' => date('Y-m-d H:i:s')
            ];
            
            return $customersAddress->store($addressData);
        } catch (Exception $e) {
            error_log("Error storing customer address: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update user address in customers_address table
     */
    public function updateCustomerAddress($customerId, $addressString)
    {
        try {
            $customersAddress = new CustomersAddress();
                
            // Check if address already exists for this user
            $existingAddresses = $customersAddress->getByCustomerId($customerId);
            
            if (!empty($existingAddresses)) {
                // Update existing address
                $addressId = $existingAddresses[0]['id'];
                $addressComponents = $this->parseAddress($addressString);
                
                $addressData = [
                    'customerId' => $customerId,
                    'street' => $addressComponents['street'],
                    'city' => $addressComponents['city'],
                    'state' => $addressComponents['state'],
                    'postalCode' => $addressComponents['postal_code'],
                    'country' => $addressComponents['country'],
                    'updatedBy' => 'system',
                    'updatedAt' => date('Y-m-d H:i:s')
                ];
                
                return $customersAddress->updateCustomersAddressByID($addressId, $addressData);
            } else {
                // Create new address
                return $this->storeCustomerAddress($customerId, $addressString);
            }
        } catch (Exception $e) {
            error_log("Error updating customer address: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Parse address string into components
     */
    private function parseAddress($addressString)
    {
        // Simple address parsing - you can make this more sophisticated
        $parts = explode(',', $addressString);
        $parts = array_map('trim', $parts);
        
        return [
            'street' => $parts[0] ?? '',
            'city' => $parts[1] ?? '',
            'state' => $parts[2] ?? '',
            'postal_code' => $parts[3] ?? '',
            'country' => $parts[4] ?? 'Philippines' // Default country
        ];
    }

    /**
     * Get customer with address information
     */
    public function getCustomerWithAddress($customerId)
    {
        try {
            // Get customer data
            $customerData = $this->getById($customerId);
            if (empty($customerData)) {
                return null;
            }
            
            $customer = $customerData[0];
            
            // Get address data
            $customersAddress = new CustomersAddress();
            $addresses = $customersAddress->getByCustomerId($customerId);
            
            // Add address information to customer data
            if (!empty($addresses)) {
                $address = $addresses[0]; // Get first address
                $customer['address'] = $address['street'] . ', ' . 
                                 $address['city'] . ', ' . 
                                 $address['state'] . ', ' . 
                                 $address['postal_code'] . ', ' . 
                                 $address['country'];
                $customer['address_details'] = $address;
            } else {
                $customer['address'] = null;
                $customer['address_details'] = null;
            }
            
            return $customer;
        } catch (Exception $e) {
            error_log("Error getting customer with address: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get all customers with their address information
     */
    public function getAllWithAddresses()
    {
        try {
            $customers = $this->getAll();
            $customersAddress = new CustomersAddress();
            
            foreach ($customers as &$customer) {
                // Remove password for security
                unset($customer['password']);
                
                // Get address data
                $addresses = $customersAddress->getByCustomerId($customer['id']);
                
                if (!empty($addresses)) {
                    $address = $addresses[0]; // Get first address
                    $customer['address'] = $address['street'] . ', ' . 
                                     $address['city'] . ', ' . 
                                     $address['postal_code'] . ', ' . 
                                     $address['state'] . ', ' . 
                                     $address['country'];
                    $customer['address_details'] = $address;
                } else {
                    $customer['address'] = null;
                    $customer['address_details'] = null;
                }
            }
            
            return $customers;
        } catch (Exception $e) {
            error_log("Error getting all customers with addresses: " . $e->getMessage());
            return $this->getAll(); // Fallback to customers without addresses
        }
    }

    /**
     * Get all customers with passwords (admin access only)
     */
    public function getAllWithPasswords()
    {
        try {
            $customers = $this->getAll();
            $customersAddress = new CustomersAddress();
            
            foreach ($customers as &$customer) {
                // Keep password for admin access
                // Password is already included from getAll()
                
                // Get address data
                $addresses = $customersAddress->getByCustomerId($customer['id']);
                
                if (!empty($addresses)) {
                    $address = $addresses[0]; // Get first address
                    $customer['address'] = $address['street'] . ', ' . 
                                     $address['city'] . ', ' . 
                                     $address['postal_code'] . ', ' . 
                                     $address['state'] . ', ' . 
                                     $address['country'];
                    $customer['address_details'] = $address;
                } else {
                    $customer['address'] = null;
                    $customer['address_details'] = null;
                }
            }
            
            return $customers;
        } catch (Exception $e) {
            error_log("Error getting all customers with passwords: " . $e->getMessage());
            return $this->getAll(); // Fallback to customers without addresses
        }
    }

    /**
     * Get customer address details for profile editing
     */
    public function getCustomerAddressDetails($customerId)
    {
        try {
            $customersAddress = new CustomersAddress();
            $addresses = $customersAddress->getByCustomerId($customerId);
            
            if (!empty($addresses)) {
                $address = $addresses[0]; // Get first address
                return [
                    'street' => $address['street'] ?? '',
                    'city' => $address['city'] ?? '',
                    'state' => $address['state'] ?? '',
                    'postal_code' => $address['postal_code'] ?? '',
                    'country' => $address['country'] ?? ''
                ];
            }
            
            return null;
        } catch (Exception $e) {
            error_log("Error getting customer address details: " . $e->getMessage());
            return null;
        }
    }



    
}
?>