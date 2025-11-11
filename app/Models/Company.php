<?php

namespace App\Models;

use PDO;

require_once __DIR__ . '/../../config/database.php';

class Company {
    private $conn;
    private $table = 'companies';

    public function __construct() {
        $this->conn = \Database::getInstance()->getConnection();
    }

    /**
     * Find company by ID
     */
    public function find($id) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Find company by ID (alias for find)
     */
    public function findById($id) {
        return $this->find($id);
    }

    /**
     * Get all companies
     */
    public function all() {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} ORDER BY name ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create a new company
     */
    public function create($data) {
        // Support both old column names and new schema
        $columns = [];
        $values = [];
        $params = [];
        
        // Map data to correct column names based on schema
        if (isset($data['name'])) {
            $columns[] = 'name';
            $values[] = ':name';
            $params['name'] = $data['name'];
        }
        
        if (isset($data['address']) || isset($data['phone_number'])) {
            $columns[] = 'address';
            $values[] = ':address';
            $params['address'] = $data['address'] ?? $data['phone_number'] ?? null;
        }
        
        if (isset($data['phone']) || isset($data['phone_number'])) {
            $columns[] = 'phone_number';
            $values[] = ':phone_number';
            $params['phone_number'] = $data['phone'] ?? $data['phone_number'] ?? null;
        }
        
        if (isset($data['email'])) {
            $columns[] = 'email';
            $values[] = ':email';
            $params['email'] = $data['email'];
        }
        
        if (isset($data['contact_person'])) {
            $columns[] = 'contact_person';
            $values[] = ':contact_person';
            $params['contact_person'] = $data['contact_person'];
        }
        
        if (isset($data['status'])) {
            $columns[] = 'status';
            $values[] = ':status';
            $params['status'] = $data['status'];
        }
        
        if (isset($data['created_by_user_id'])) {
            $columns[] = 'created_by_user_id';
            $values[] = ':created_by_user_id';
            $params['created_by_user_id'] = $data['created_by_user_id'];
        }
        
        // Check if columns table has these columns first
        try {
            $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ", created_at) 
                    VALUES (" . implode(', ', $values) . ", NOW())";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            
            return $this->conn->lastInsertId();
        } catch (\PDOException $e) {
            // Fallback to basic columns if table structure is different
            error_log("Company::create - Error with full columns, trying basic: " . $e->getMessage());
            $sql = "INSERT INTO {$this->table} (name, email, phone_number, address, created_at) 
                    VALUES (:name, :email, :phone_number, :address, NOW())";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                'name' => $data['name'] ?? '',
                'email' => $data['email'] ?? null,
                'phone_number' => $data['phone'] ?? $data['phone_number'] ?? null,
                'address' => $data['address'] ?? null
            ]);
            
            return $this->conn->lastInsertId();
        }
    }

    /**
     * Update company
     */
    public function update($id, $data) {
        try {
            $sql = "UPDATE {$this->table} SET 
                    name = :name, 
                    address = :address, 
                    phone_number = :phone_number, 
                    email = :email,
                    contact_person = :contact_person,
                    status = :status,
                    updated_at = NOW()
                    WHERE id = :id";
            $stmt = $this->conn->prepare($sql);
            
            $stmt->execute([
                'id' => $id,
                'name' => $data['name'] ?? '',
                'address' => $data['address'] ?? null,
                'phone_number' => $data['phone'] ?? $data['phone_number'] ?? null,
                'email' => $data['email'] ?? null,
                'contact_person' => $data['contact_person'] ?? null,
                'status' => $data['status'] ?? 'active'
            ]);
            
            return $stmt->rowCount() > 0;
        } catch (\PDOException $e) {
            // Fallback to basic update
            error_log("Company::update - Error: " . $e->getMessage());
            $sql = "UPDATE {$this->table} SET 
                    name = :name, 
                    email = :email,
                    updated_at = NOW()
                    WHERE id = :id";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                'id' => $id,
                'name' => $data['name'] ?? '',
                'email' => $data['email'] ?? null
            ]);
            
            return $stmt->rowCount() > 0;
        }
    }

    /**
     * Check if company has related data
     */
    public function hasData($id) {
        $data = [
            'has_users' => false,
            'has_customers' => false,
            'has_sales' => false,
            'has_products' => false,
            'has_repairs' => false,
            'has_swaps' => false,
            'total_count' => 0
        ];
        
        try {
            // Check users
            $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM users WHERE company_id = ?");
            $stmt->execute([$id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $userCount = (int)($result['count'] ?? 0);
            if ($userCount > 0) {
                $data['has_users'] = true;
                $data['total_count'] += $userCount;
            }
            
            // Check customers
            $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM customers WHERE company_id = ?");
            $stmt->execute([$id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $customerCount = (int)($result['count'] ?? 0);
            if ($customerCount > 0) {
                $data['has_customers'] = true;
                $data['total_count'] += $customerCount;
            }
            
            // Check sales
            $tableCheck = $this->conn->query("SHOW TABLES LIKE 'pos_sales'");
            if ($tableCheck && $tableCheck->rowCount() > 0) {
                $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM pos_sales WHERE company_id = ?");
                $stmt->execute([$id]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $salesCount = (int)($result['count'] ?? 0);
                if ($salesCount > 0) {
                    $data['has_sales'] = true;
                    $data['total_count'] += $salesCount;
                }
            }
            
            // Check products
            $tableCheck = $this->conn->query("SHOW TABLES LIKE 'products'");
            if ($tableCheck && $tableCheck->rowCount() > 0) {
                $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM products WHERE company_id = ?");
                $stmt->execute([$id]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $productCount = (int)($result['count'] ?? 0);
                if ($productCount > 0) {
                    $data['has_products'] = true;
                    $data['total_count'] += $productCount;
                }
            }
            
            // Check repairs
            $tableCheck = $this->conn->query("SHOW TABLES LIKE 'repairs'");
            if ($tableCheck && $tableCheck->rowCount() > 0) {
                $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM repairs WHERE company_id = ?");
                $stmt->execute([$id]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $repairCount = (int)($result['count'] ?? 0);
                if ($repairCount > 0) {
                    $data['has_repairs'] = true;
                    $data['total_count'] += $repairCount;
                }
            }
            
            // Check swaps
            $tableCheck = $this->conn->query("SHOW TABLES LIKE 'swaps'");
            if ($tableCheck && $tableCheck->rowCount() > 0) {
                $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM swaps WHERE company_id = ?");
                $stmt->execute([$id]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $swapCount = (int)($result['count'] ?? 0);
                if ($swapCount > 0) {
                    $data['has_swaps'] = true;
                    $data['total_count'] += $swapCount;
                }
            }
        } catch (\Exception $e) {
            error_log("Company::hasData error: " . $e->getMessage());
        }
        
        return $data;
    }
    
    /**
     * Delete company with proper cascade handling
     * This method handles foreign key constraints by deleting in the correct order
     */
    public function deleteWithCascade($id) {
        try {
            $this->conn->beginTransaction();
            
            // Step 1: Get all user IDs for this company first
            $userStmt = $this->conn->prepare("SELECT id FROM users WHERE company_id = ?");
            $userStmt->execute([$id]);
            $userIds = $userStmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Step 2: Handle customers that reference users from this company
            // First, update customers to set created_by_user_id to NULL to remove foreign key constraint
            if (!empty($userIds)) {
                $placeholders = implode(',', array_fill(0, count($userIds), '?'));
                $stmt = $this->conn->prepare("
                    UPDATE customers 
                    SET created_by_user_id = NULL 
                    WHERE company_id = ? 
                    AND created_by_user_id IN ($placeholders)
                ");
                $stmt->execute(array_merge([$id], $userIds));
            }
            
            // Step 3: Delete all customers for this company
            $stmt = $this->conn->prepare("DELETE FROM customers WHERE company_id = ?");
            $stmt->execute([$id]);
            
            // Step 4: Delete users (CASCADE will handle other related data)
            $stmt = $this->conn->prepare("DELETE FROM users WHERE company_id = ?");
            $stmt->execute([$id]);
            
            // Step 5: Delete company modules
            $tableCheck = $this->conn->query("SHOW TABLES LIKE 'company_modules'");
            if ($tableCheck && $tableCheck->rowCount() > 0) {
                $stmt = $this->conn->prepare("DELETE FROM company_modules WHERE company_id = ?");
                $stmt->execute([$id]);
            }
            
            // Step 6: Delete company SMS accounts
            $tableCheck = $this->conn->query("SHOW TABLES LIKE 'company_sms_accounts'");
            if ($tableCheck && $tableCheck->rowCount() > 0) {
                $stmt = $this->conn->prepare("DELETE FROM company_sms_accounts WHERE company_id = ?");
                $stmt->execute([$id]);
            }
            
            // Step 7: Finally delete the company
            $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE id = ?");
            $stmt->execute([$id]);
            
            $this->conn->commit();
            return $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            $this->conn->rollBack();
            error_log("Company::deleteWithCascade error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Delete company (simple delete - use deleteWithCascade for safe deletion)
     */
    public function delete($id) {
        $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Get company statistics
     */
    public function getStats($id) {
        try {
            // Verify company exists
            $company = $this->find($id);
            if (!$company) {
                return [
                    'success' => false,
                    'error' => 'Company not found'
                ];
            }

            // Get basic counts
            $stats = [
                'company_id' => (int)$id,
                'company_name' => $company['name'] ?? 'Unknown',
                'total_users' => 0,
                'total_customers' => 0,
                'total_sales' => 0,
                'total_revenue' => 0,
                'total_repairs' => 0
            ];

            // Count users
            try {
                $userStmt = $this->conn->prepare("SELECT COUNT(*) as count FROM users WHERE company_id = ?");
                $userStmt->execute([$id]);
                $userCount = $userStmt->fetch(PDO::FETCH_ASSOC);
                $stats['total_users'] = (int)($userCount['count'] ?? 0);
            } catch (\Exception $e) {
                error_log("Company::getStats - Error counting users: " . $e->getMessage());
            }

            // Count customers
            try {
                $customerStmt = $this->conn->prepare("SELECT COUNT(*) as count FROM customers WHERE company_id = ?");
                $customerStmt->execute([$id]);
                $customerCount = $customerStmt->fetch(PDO::FETCH_ASSOC);
                $stats['total_customers'] = (int)($customerCount['count'] ?? 0);
            } catch (\Exception $e) {
                error_log("Company::getStats - Error counting customers: " . $e->getMessage());
            }

            // Count sales/revenue (if pos_sales table exists)
            try {
                $salesCheck = $this->conn->query("SHOW TABLES LIKE 'pos_sales'");
                if ($salesCheck && $salesCheck->rowCount() > 0) {
                    $salesStmt = $this->conn->prepare("SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as revenue FROM pos_sales WHERE company_id = ?");
                    $salesStmt->execute([$id]);
                    $salesData = $salesStmt->fetch(PDO::FETCH_ASSOC);
                    $stats['total_sales'] = (int)($salesData['count'] ?? 0);
                    $stats['total_revenue'] = (float)($salesData['revenue'] ?? 0);
                }
            } catch (\Exception $e) {
                error_log("Company::getStats - Error counting sales: " . $e->getMessage());
            }

            // Count repairs (if repairs table exists)
            try {
                $repairCheck = $this->conn->query("SHOW TABLES LIKE 'repairs'");
                if ($repairCheck && $repairCheck->rowCount() > 0) {
                    $repairStmt = $this->conn->prepare("SELECT COUNT(*) as count FROM repairs WHERE company_id = ?");
                    $repairStmt->execute([$id]);
                    $repairCount = $repairStmt->fetch(PDO::FETCH_ASSOC);
                    $stats['total_repairs'] = (int)($repairCount['count'] ?? 0);
                }
            } catch (\Exception $e) {
                error_log("Company::getStats - Error counting repairs: " . $e->getMessage());
            }

            return $stats;
        } catch (\Exception $e) {
            error_log("Company::getStats error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}