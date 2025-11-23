<?php
require_once 'Database.php';
require_once 'JWT.php';
require_once 'CustomersAddress.php';
require_once 'Membership.php';

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
        $sql = "SELECT * FROM `customers` ORDER BY `created_at` DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $result;
    }

    public function getActiveCustomers()
    {
        $sql = "SELECT * FROM `customers` WHERE `status` = 'active' ORDER BY `created_at` DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $result;
    }

    public function getInactiveCustomers()
    {
        $sql = "SELECT * FROM `customers` WHERE `status` = 'inactive' ORDER BY `created_at` DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $result;
    }

    public function getArchivedCustomers()
    {
        $sql = "SELECT * FROM `customers` WHERE `status` = 'archived' ORDER BY `created_at` DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $result;
    }

    public function getCustomersByStatus($status)
    {
        // Validate status values
        $validStatuses = ['active', 'inactive', 'archived'];
        if (!in_array($status, $validStatuses)) {
            return [];
        }

        $sql = "SELECT * FROM `customers` WHERE `status` = :status ORDER BY `created_at` DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':status', $status);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $result;
    }

    public function getCustomerStatistics()
    {
        try {
            $sql = "SELECT 
                        COUNT(*) as total_customers,
                        SUM(CASE WHEN `status` = 'active' THEN 1 ELSE 0 END) as active_customers,
                        SUM(CASE WHEN `status` = 'inactive' THEN 1 ELSE 0 END) as inactive_customers,
                        SUM(CASE WHEN `status` = 'archived' THEN 1 ELSE 0 END) as archived_customers,
                        SUM(CASE WHEN `status` IS NULL OR `status` = '' THEN 1 ELSE 0 END) as no_status_customers
                    FROM `customers`";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result;
        } catch (Exception $e) {
            error_log("Error getting customer statistics: " . $e->getMessage());
            return [
                'total_customers' => 0,
                'active_customers' => 0,
                'inactive_customers' => 0,
                'archived_customers' => 0,
                'no_status_customers' => 0
            ];
        }
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

    public function getByIdSingle($id)
    {
        $records = $this->getById($id);
        if (empty($records)) {
            return null;
        }
        return $records[0];
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

    public function archiveCustomerByID($id)
    {
        $sql = "UPDATE `customers` SET `status` = 'inactive' WHERE `id` = :id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    public function activateCustomerByID($id)
    {
        $sql = "UPDATE `customers` SET `status` = 'active' WHERE `id` = :id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    public function updateCustomerStatus($id, $status)
    {
        // Validate status values
        $validStatuses = ['active', 'inactive', 'archived'];
        if (!in_array($status, $validStatuses)) {
            return false;
        }

        $sql = "UPDATE `customers` SET `status` = :status WHERE `id` = :id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    public function bulkUpdateCustomerStatus($customerIds, $status)
    {
        // Validate status values
        $validStatuses = ['active', 'inactive', 'archived'];
        if (!in_array($status, $validStatuses)) {
            return false;
        }

        if (empty($customerIds) || !is_array($customerIds)) {
            return false;
        }

        try {
            // Create placeholders for the IN clause
            $placeholders = str_repeat('?,', count($customerIds) - 1) . '?';
            $sql = "UPDATE `customers` SET `status` = ? WHERE `id` IN ($placeholders)";

            $stmt = $this->conn->prepare($sql);
            
            // Bind status first, then all IDs
            $params = array_merge([$status], $customerIds);
            $stmt->execute($params);

            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("Error bulk updating customer status: " . $e->getMessage());
            return false;
        }
    }

    public function searchCustomers($query, $status = null)
    {
        try {
            $sql = "SELECT * FROM `customers` WHERE 
                    (`first_name` LIKE :query OR 
                     `last_name` LIKE :query OR 
                     `email` LIKE :query OR 
                     `phone_number` LIKE :query)";
            
            $params = [':query' => "%$query%"];
            
            if ($status !== null) {
                $sql .= " AND `status` = :status";
                $params[':status'] = $status;
            }
            
            $sql .= " ORDER BY `created_at` DESC";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $result;
        } catch (Exception $e) {
            error_log("Error searching customers: " . $e->getMessage());
            return [];
        }
    }


    public function updateCustomersByID($id, $data)
    {
        try {
            // Handle address update separately in customers_address table
            $addressUpdated = true;
            $membershipUpdated = false;
            if (isset($data['address']) && !empty($data['address'])) {
                $addressUpdated = $this->updateCustomerAddress($id, $data['address']);
            }

            if (isset($data['membershipType']) || isset($data['membership_type'])) {
                $membershipType = $data['membershipType'] ?? $data['membership_type'];
                $membershipStart = $data['membershipStartDate'] ?? $data['membership_start_date'] ?? null;
                $membershipEnd = $data['membershipExpirationDate'] ?? $data['membership_expiration_date'] ?? null;
                $membershipUpdated = $this->upsertCustomerMembership($id, $membershipType, $membershipStart, $membershipEnd);
            }
            
            // Check if password is included in the data
            if (isset($data['password']) && !empty($data['password'])) {
                // Hash the password before storing
                $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
                
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
                $stmt->bindParam(':password', $hashedPassword);
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
            return ($stmt->rowCount() > 0) || $addressUpdated || (!empty($membershipUpdated));
            
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
            
            // Verify password using password_verify for hashed passwords
            $passwordVerified = password_verify($password, $customer['password']);
            
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

    public function loginByContact($contactNumber, $password)
    {
        try {
            // Get user by contact number
            $sql = "SELECT * FROM `customers` WHERE `phone_number` = :contact LIMIT 1";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':contact', $contactNumber);
            $stmt->execute();
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$customer) {
                return [
                    'success' => false,
                    'message' => 'Invalid contact number or password'
                ];
            }

            // Verify password using password_verify for hashed passwords
            $passwordVerified = password_verify($password, $customer['password']);

            if ($passwordVerified) {
                unset($customer['password']);

                $tokenPayload = [
                    'customer_id' => $customer['id'],
                    'email' => $customer['email'],
                    'first_name' => $customer['first_name'],
                    'last_name' => $customer['last_name']
                ];

                $accessToken = JWT::encode($tokenPayload, 24);
                $refreshToken = JWT::encode($tokenPayload, 168);

                return [
                    'success' => true,
                    'message' => 'Login successful',
                    'customer' => $customer,
                    'access_token' => $accessToken,
                    'refresh_token' => $refreshToken,
                    'token_type' => 'Bearer',
                    'expires_in' => 24 * 3600
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
            // Check if email already exists (only if email is provided)
            if (!empty($data['email'])) {
                $existingCustomer = $this->getByEmail($data['email']);
                if ($existingCustomer) {
                    return [
                        'success' => false,
                        'message' => 'Email already exists'
                    ];
                }
            }

            if (empty($data['password'])) {
                return [
                    'success' => false,
                    'message' => 'Password is required',
                ];
            }

            $passwordSource = $data['password'];
            $isPasswordHashed = !empty($data['password_is_hashed']);
            $hashedPassword = $isPasswordHashed
                ? $passwordSource
                : password_hash($passwordSource, PASSWORD_DEFAULT);

            // Prepare data for insertion (excluding address - will be stored separately)
            $insertData = [
                'firstName' => $data['first_name'],
                'lastName' => $data['last_name'],
                'middleName' => $data['middle_name'] ?? null,
                'email' => $data['email'] ?? null,
                'password' => $hashedPassword,
                'birthdate' => $data['birthdate'] ?? null,
                'phoneNumber' => $data['phone_number'] ?? null,
                'createdBy' => $data['created_by'] ?? 'system',
                'createdAt' => date('Y-m-d H:i:s'),
                'emergencyContactName' => $data['emergency_contact_name'] ?? '',
                'emergencyContactNumber' => $data['emergency_contact_number'] ?? '',
                'status' => $data['status'] ?? 'active'
            ];

            // Insert user
            if ($this->store($insertData)) {
                // Get the newly created customer by ID (since email might be null)
                $customerId = $this->conn->lastInsertId();
                $newCustomer = $this->getById($customerId);
                if (!empty($newCustomer)) {
                    $newCustomer = $newCustomer[0];
                    unset($newCustomer['password']); // Remove password from response
                }

                // Store address in customers_address table if provided
                if (!empty($data['address']) && $newCustomer) {
                    $this->storeCustomerAddress($newCustomer['id'], $data['address']);
                }

                // Add address information to user data
                if ($newCustomer) {
                    $newCustomer = $this->getCustomerWithAddress($newCustomer['id']);
                    if ($newCustomer) {
                        unset($newCustomer['password']); // Remove password from response again
                    } else {
                        // Fallback if getUserWithAddress fails
                        $newCustomer = $this->getById($customerId);
                        if (!empty($newCustomer)) {
                            $newCustomer = $newCustomer[0];
                            unset($newCustomer['password']);
                        }
                    }
                }

                // Generate JWT token for new user
                $tokenPayload = [
                    'customer_id' => $newCustomer['id'],
                    'email' => $newCustomer['email'] ?? null,
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
            $addressComponents = $this->parseAddress($addressString);
            
            $customersAddress = new CustomersAddress();
            $addressData = [
                'customerId' => $customerId,
                'street' => $addressComponents['street'],
                'city' => $addressComponents['city'],
                'state' => $addressComponents['state'],
                'postalCode' => $addressComponents['postal_code'],
                'country' => $addressComponents['country'],
                'createdBy' => 0,
                'createdAt' => date('Y-m-d H:i:s')
            ];

            $result = $customersAddress->upsertByCustomerId($customerId, $addressData);

            return $result !== false;
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
            $addressComponents = $this->parseAddress($addressString);

            $addressData = [
                'customerId' => $customerId,
                'street' => $addressComponents['street'],
                'city' => $addressComponents['city'],
                'state' => $addressComponents['state'],
                'postalCode' => $addressComponents['postal_code'],
                'country' => $addressComponents['country'],
                'updatedBy' => 0,
                'updatedAt' => date('Y-m-d H:i:s')
            ];

            $result = $customersAddress->upsertByCustomerId($customerId, $addressData);

            return $result !== false;
        } catch (Exception $e) {
            error_log("Error updating customer address: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Insert or update a customer's membership record.
     */
    public function upsertCustomerMembership($customerId, $membershipType, $startDate = null, $expirationDate = null)
    {
        try {
            $normalizedType = $this->normalizeMembershipType($membershipType);
            if ($normalizedType === null) {
                return false;
            }

            $start = $startDate ?: date('Y-m-d');
            $end = $expirationDate ?: $this->calculateMembershipEndDate($start, $normalizedType);

            $membership = new Membership();
            $payload = [
                'customerId' => $customerId,
                'membershipType' => $normalizedType,
                'startDate' => $start,
                'expirationDate' => $end,
                'status' => $normalizedType,
                'createdBy' => 0,
                'createdAt' => date('Y-m-d H:i:s', strtotime($start)),
                'updatedBy' => 0,
                'updatedAt' => date('Y-m-d H:i:s')
            ];

            $result = $membership->upsertByCustomerId($customerId, $payload);
            return $result !== false;
        } catch (Exception $e) {
            error_log("Error updating customer membership: " . $e->getMessage());
            return false;
        }
    }

    private function normalizeMembershipType($value)
    {
        if ($value === null) {
            return null;
        }

        $key = strtolower(trim($value));
        $key = str_replace(' ', '', $key);

        if ($key === 'daily') {
            return 'Daily';
        }

        if ($key === 'halfmonth') {
            return 'Half Month';
        }

        if ($key === 'monthly') {
            return 'Monthly';
        }

        return null;
    }

    private function calculateMembershipEndDate($startDate, $membershipType)
    {
        $startTimestamp = strtotime($startDate);
        if ($startTimestamp === false) {
            $startTimestamp = time();
        }

        switch ($membershipType) {
            case 'Daily':
                $days = 1;
                break;
            case 'Half Month':
                $days = 15;
                break;
            case 'Monthly':
            default:
                $days = 30;
                break;
        }

        return date('Y-m-d', strtotime("+$days days", $startTimestamp));
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
     * Get count of new members for the current week grouped by weekday
     * Returns associative array like ['Mon'=>3,'Tue'=>2,...]
     */
    public function getNewMembersThisWeek()
    {
        $sql = "SELECT DAYNAME(`created_at`) as day_name, COUNT(*) as cnt
                FROM `customers`
                WHERE YEARWEEK(`created_at`, 1) = YEARWEEK(CURDATE(), 1)
                GROUP BY DAYNAME(`created_at`)";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Initialize all days to 0 (Mon..Sun)
        $order = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
        $result = [];
        foreach ($order as $d) { $result[$d] = 0; }
        foreach ($rows as $r) {
            $name = $r['day_name'];
            if (isset($result[$name])) {
                $result[$name] = (int)$r['cnt'];
            }
        }
        return $result;
    }

    /**
     * Get count of new members for the current month grouped by ISO week index (1..5)
     */
    public function getNewMembersByWeekOfMonth()
    {
        $sql = "SELECT WEEK(`created_at`, 1) - WEEK(DATE_SUB(`created_at`, INTERVAL DAYOFMONTH(`created_at`) - 1 DAY), 1) + 1 AS week_of_month,
                       COUNT(*) as cnt
                FROM `customers`
                WHERE YEAR(`created_at`) = YEAR(CURDATE()) AND MONTH(`created_at`) = MONTH(CURDATE())
                GROUP BY week_of_month";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [1=>0,2=>0,3=>0,4=>0];
        foreach ($rows as $r) {
            $idx = (int)$r['week_of_month'];
            if ($idx >= 1 && $idx <= 4) {
                $result[$idx] = (int)$r['cnt'];
            }
        }
        return $result;
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