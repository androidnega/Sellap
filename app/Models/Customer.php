<?php

namespace App\Models;

use PDO;

require_once __DIR__ . '/../../config/database.php';

class Customer {
    private $conn;
    private $table = 'customers';

    public function __construct() {
        $this->conn = \Database::getInstance()->getConnection();
    }

    /**
     * Create a new customer (Multi-tenant)
     */
    public function create($data) {
        $sql = "INSERT INTO {$this->table} (company_id, unique_id, full_name, phone_number, email, address, created_by_user_id)
                VALUES (:company_id, :unique_id, :full_name, :phone_number, :email, :address, :created_by_user_id)";
        $stmt = $this->conn->prepare($sql);
        
        // Ensure data is properly sanitized and trimmed
        $params = [
            'company_id' => (int)$data['company_id'],
            'unique_id' => trim($data['unique_id'] ?? ''),
            'full_name' => trim($data['full_name'] ?? ''),
            'phone_number' => trim($data['phone_number'] ?? ''),
            'email' => !empty($data['email']) ? trim($data['email']) : null,
            'address' => !empty($data['address']) ? trim($data['address']) : null,
            'created_by_user_id' => isset($data['created_by_user_id']) && $data['created_by_user_id'] !== null ? (int)$data['created_by_user_id'] : null
        ];
        
        error_log("CUSTOMER CREATE: unique_id={$params['unique_id']}, name={$params['full_name']}, phone={$params['phone_number']}");
        
        $result = $stmt->execute($params);
        
        if ($result) {
            $newId = $this->conn->lastInsertId();
            error_log("CUSTOMER CREATED: ID=$newId, unique_id={$params['unique_id']}");
        } else {
            error_log("CUSTOMER CREATE FAILED: " . print_r($stmt->errorInfo(), true));
        }
        
        return $result;
    }

    /**
     * Find customer by ID
     */
    public function findById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Find customer by ID and company (Multi-tenant safe)
     */
    public function find($id, $company_id) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE id = :id AND company_id = :company_id LIMIT 1");
        $stmt->execute(['id' => $id, 'company_id' => $company_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Find customer by unique_id (Multi-tenant safe)
     * @param string $uniqueId Customer unique ID
     * @param int|null $companyId Optional company ID for multi-tenant isolation
     */
    public function findByUniqueId($uniqueId, $companyId = null) {
        $sql = "SELECT * FROM {$this->table} WHERE unique_id = :unique_id";
        $params = ['unique_id' => $uniqueId];
        
        // Add company filter for multi-tenant isolation if provided
        if ($companyId !== null) {
            $sql .= " AND company_id = :company_id";
            $params['company_id'] = $companyId;
        }
        
        $sql .= " LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Find customer by phone number
     */
    public function findByPhone($phone) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE phone_number = :phone LIMIT 1");
        $stmt->execute(['phone' => $phone]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get all customers
     */
    public function all() {
        return $this->conn->query("SELECT * FROM {$this->table} ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all customers by company (Multi-tenant filtering)
     */
    public function allByCompany($company_id) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE company_id = :company_id ORDER BY id DESC");
        $stmt->execute(['company_id' => $company_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find customers by company with limit (Multi-tenant filtering)
     */
    public function findByCompany($company_id, $limit = 100) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE company_id = :company_id ORDER BY id DESC LIMIT :limit");
        $stmt->bindValue(':company_id', $company_id, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find customer by phone in a specific company
     */
    public function findByPhoneInCompany($phone, $company_id) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE phone_number = :phone AND company_id = :company_id LIMIT 1");
        $stmt->execute(['phone' => $phone, 'company_id' => $company_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Update customer information (Multi-tenant safe)
     * @param int $id Customer ID
     * @param array $data Update data
     * @param int|null $companyId Optional company ID for multi-tenant isolation
     */
    public function update($id, $data, $companyId = null) {
        $fields = [];
        foreach ($data as $key => $value) {
            $fields[] = "$key = :$key";
        }
        
        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = :id";
        $params = $data;
        $params['id'] = $id;
        
        // Add company filter for multi-tenant isolation if provided
        if ($companyId !== null) {
            $sql .= " AND company_id = :company_id";
            $params['company_id'] = $companyId;
        }
        
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Delete customer (Multi-tenant safe)
     * Handles foreign key constraints by setting customer_id to NULL in related tables
     * @param int $id Customer ID
     * @param int|null $companyId Optional company ID for multi-tenant isolation
     */
    public function delete($id, $companyId = null) {
        try {
            // Start transaction
            $this->conn->beginTransaction();
            
            // Check if customer exists and belongs to company (if companyId provided)
            if ($companyId !== null) {
                $checkStmt = $this->conn->prepare("SELECT id FROM {$this->table} WHERE id = :id AND company_id = :company_id");
                $checkStmt->execute(['id' => $id, 'company_id' => $companyId]);
                if ($checkStmt->rowCount() === 0) {
                    $this->conn->rollBack();
                    return false;
                }
            }
            
            // Handle related records in swaps table
            // Note: swaps.customer_id is NOT NULL, so we need to check if we can delete
            // For now, we'll check if swaps exist and prevent deletion if they do
            // Alternatively, we could cancel swaps, but that's business logic
            $swapCheck = $this->conn->prepare("SELECT COUNT(*) as count FROM swaps WHERE customer_id = :id");
            $swapCheck->execute(['id' => $id]);
            $swapCount = $swapCheck->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($swapCount > 0) {
                // Get swap IDs first
                $getSwaps = $this->conn->prepare("SELECT id FROM swaps WHERE customer_id = :id");
                $getSwaps->execute(['id' => $id]);
                $swapIds = $getSwaps->fetchAll(PDO::FETCH_COLUMN);
                
                if (!empty($swapIds)) {
                    $placeholders = implode(',', array_fill(0, count($swapIds), '?'));
                    
                    // Delete swapped_items first (if table exists) to avoid foreign key issues
                    try {
                        $deleteSwappedItems = $this->conn->prepare("DELETE FROM swapped_items WHERE swap_id IN ($placeholders)");
                        $deleteSwappedItems->execute($swapIds);
                    } catch (\Exception $e) {
                        // Table might not exist, continue
                        error_log("Note: swapped_items table might not exist: " . $e->getMessage());
                    }
                    
                    // Delete swap_profit_links if they exist
                    try {
                        $deleteProfitLinks = $this->conn->prepare("DELETE FROM swap_profit_links WHERE swap_id IN ($placeholders)");
                        $deleteProfitLinks->execute($swapIds);
                    } catch (\Exception $e) {
                        // Table might not exist, continue
                        error_log("Note: swap_profit_links table might not exist: " . $e->getMessage());
                    }
                }
                
                // Now delete related swaps
                $deleteSwaps = $this->conn->prepare("DELETE FROM swaps WHERE customer_id = :id");
                $deleteSwaps->execute(['id' => $id]);
            }
            
            // Handle related records in repairs table
            $repairCheck = $this->conn->prepare("SELECT COUNT(*) as count FROM repairs WHERE customer_id = :id");
            $repairCheck->execute(['id' => $id]);
            $repairCount = $repairCheck->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($repairCount > 0) {
                // Get repair IDs first
                $getRepairs = $this->conn->prepare("SELECT id FROM repairs WHERE customer_id = :id");
                $getRepairs->execute(['id' => $id]);
                $repairIds = $getRepairs->fetchAll(PDO::FETCH_COLUMN);
                
                if (!empty($repairIds)) {
                    $placeholders = implode(',', array_fill(0, count($repairIds), '?'));
                    
                    // Delete repair_accessories first (if table exists) to avoid foreign key issues
                    try {
                        $deleteAccessories = $this->conn->prepare("DELETE FROM repair_accessories WHERE repair_id IN ($placeholders)");
                        $deleteAccessories->execute($repairIds);
                    } catch (\Exception $e) {
                        // Table might not exist, continue
                        error_log("Note: repair_accessories table might not exist: " . $e->getMessage());
                    }
                }
                
                // Now delete related repairs
                $deleteRepairs = $this->conn->prepare("DELETE FROM repairs WHERE customer_id = :id");
                $deleteRepairs->execute(['id' => $id]);
            }
            
            // Handle pos_sales (already has ON DELETE SET NULL, but we'll set it explicitly for clarity)
            $updateSales = $this->conn->prepare("UPDATE pos_sales SET customer_id = NULL WHERE customer_id = :id");
            $updateSales->execute(['id' => $id]);
            
            // Now delete the customer
            $sql = "DELETE FROM {$this->table} WHERE id = :id";
            $params = ['id' => $id];
            
            // Add company filter for multi-tenant isolation if provided
            if ($companyId !== null) {
                $sql .= " AND company_id = :company_id";
                $params['company_id'] = $companyId;
            }
            
            $stmt = $this->conn->prepare($sql);
            $result = $stmt->execute($params);
            
            // Commit transaction
            $this->conn->commit();
            return $result;
            
        } catch (\Exception $e) {
            // Rollback on error
            $this->conn->rollBack();
            error_log("Error deleting customer: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get paginated customers with search and filters (Multi-tenant)
     * @param int $page Page number
     * @param int $limit Items per page
     * @param string|null $search Search term
     * @param string|null $dateFilter Date filter
     * @param int|null $companyId Company ID for filtering (required for multi-tenant isolation)
     */
    public function getPaginated($page = 1, $limit = 10, $search = null, $dateFilter = null, $companyId = null) {
        $offset = ($page - 1) * $limit;
        
        $where = [];
        $params = [];
        
        // Company filter (MANDATORY for multi-tenant isolation)
        // If companyId is not provided, return empty results to prevent data leakage
        if ($companyId === null) {
            // Return empty array if no company_id provided (security: prevent data leakage)
            return [];
        }
        
        $where[] = "company_id = :company_id";
        $params[':company_id'] = (int)$companyId;
        
        // Search filter
        if (!empty($search)) {
            $where[] = "(full_name LIKE :search OR phone_number LIKE :search OR email LIKE :search OR unique_id LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }
        
        // Date filter
        if (!empty($dateFilter)) {
            switch ($dateFilter) {
                case 'today':
                    $where[] = "DATE(created_at) = CURDATE()";
                    break;
                case 'week':
                    $where[] = "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                    break;
                case 'month':
                    $where[] = "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                    break;
                case 'year':
                    $where[] = "created_at >= DATE_SUB(NOW(), INTERVAL 365 DAY)";
                    break;
            }
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        // Select customers ordered by creation date (newest first)
        // PHP-side deduplication will handle any duplicate rows
        $sql = "SELECT * FROM {$this->table} {$whereClause} ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->conn->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Log query results
        $ids = array_map(function($r) { return $r['id']; }, $results);
        error_log("CUSTOMER QUERY: Page=$page, Limit=$limit, Company=$companyId, IDs=" . implode(',', $ids));
        
        return $results;
    }

    /**
     * Get total count of customers with search and filters (Multi-tenant)
     * @param string|null $search Search term
     * @param string|null $dateFilter Date filter
     * @param int|null $companyId Company ID for filtering (required for multi-tenant isolation)
     */
    public function getTotalCount($search = null, $dateFilter = null, $companyId = null) {
        $where = [];
        $params = [];
        
        // Company filter (MANDATORY for multi-tenant isolation)
        // If companyId is not provided, return 0 to prevent data leakage
        if ($companyId === null) {
            return 0;
        }
        
        $where[] = "company_id = :company_id";
        $params[':company_id'] = (int)$companyId;
        
        // Search filter
        if (!empty($search)) {
            $where[] = "(full_name LIKE :search OR phone_number LIKE :search OR email LIKE :search OR unique_id LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }
        
        // Date filter
        if (!empty($dateFilter)) {
            switch ($dateFilter) {
                case 'today':
                    $where[] = "DATE(created_at) = CURDATE()";
                    break;
                case 'week':
                    $where[] = "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                    break;
                case 'month':
                    $where[] = "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                    break;
                case 'year':
                    $where[] = "created_at >= DATE_SUB(NOW(), INTERVAL 365 DAY)";
                    break;
            }
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $sql = "SELECT COUNT(*) as total FROM {$this->table} {$whereClause}";
        $stmt = $this->conn->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'];
    }

    /**
     * Search customers by name or phone (Multi-tenant)
     */
    public function search($searchTerm, $companyId, $limit = 20) {
        $sql = "SELECT id, full_name, phone_number, email, address 
                FROM {$this->table} 
                WHERE company_id = :company_id 
                AND (full_name LIKE :search_term OR phone_number LIKE :search_term)
                ORDER BY full_name ASC 
                LIMIT :limit";
        
        $stmt = $this->conn->prepare($sql);
        $searchPattern = '%' . $searchTerm . '%';
        
        $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
        $stmt->bindParam(':search_term', $searchPattern, PDO::PARAM_STR);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Quick search for API - returns matching customers for autocomplete/search (Multi-tenant)
     * @param string $searchTerm Search term
     * @param int $limit Result limit
     * @param int|null $companyId Company ID for filtering (required for multi-tenant isolation)
     */
    public function quickSearch($searchTerm, $limit = 50, $companyId = null) {
        // Company filter (MANDATORY for multi-tenant isolation)
        // If companyId is not provided, return empty array to prevent data leakage
        if ($companyId === null) {
            return [];
        }
        
        $where = [];
        $params = [];
        
        $where[] = "company_id = :company_id";
        $params[':company_id'] = (int)$companyId;
        
        // Search filter
        $where[] = "(full_name LIKE :search_term OR phone_number LIKE :search_term OR email LIKE :search_term OR unique_id LIKE :search_term)";
        $params[':search_term'] = '%' . $searchTerm . '%';
        
        $whereClause = 'WHERE ' . implode(' AND ', $where);
        
        $sql = "SELECT * FROM {$this->table} 
                {$whereClause}
                ORDER BY full_name ASC 
                LIMIT :limit";
        
        $stmt = $this->conn->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

