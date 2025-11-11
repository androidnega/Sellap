<?php
namespace App\Models;

use PDO;

require_once __DIR__ . '/../../config/database.php';

class Repair {
    private $db;
    private $table = 'repairs_new';

    public function __construct() {
        $this->db = \Database::getInstance()->getConnection();
    }

    /**
     * Create a new repair
     */
    public function create(array $data) {
        // Validate technician_id is set and is a valid integer
        if (!isset($data['technician_id']) || empty($data['technician_id']) || intval($data['technician_id']) <= 0) {
            throw new \Exception('technician_id is required and must be a valid user ID');
        }
        
        // Ensure technician_id is cast to integer
        $technicianId = (int)$data['technician_id'];
        
        // Verify technician exists in users table before inserting
        $checkTech = $this->db->prepare("SELECT id FROM users WHERE id = ?");
        $checkTech->execute([$technicianId]);
        if ($checkTech->rowCount() === 0) {
            throw new \Exception("Technician ID {$technicianId} does not exist in users table");
        }
        
        // Check if device_brand and device_model columns exist
        $hasDeviceFields = $this->checkDeviceColumnsExist();
        
        if ($hasDeviceFields) {
            // Check if labour_cost column exists
            $checkLabourCost = $this->db->query("SHOW COLUMNS FROM repairs_new LIKE 'labour_cost'");
            $hasLabourCost = $checkLabourCost && $checkLabourCost->rowCount() > 0;
            
            if ($hasLabourCost) {
                $stmt = $this->db->prepare("
                    INSERT INTO repairs_new (
                        company_id, technician_id, product_id, device_brand, device_model,
                        customer_name, customer_contact, customer_id, issue_description, 
                        repair_cost, labour_cost, parts_cost, accessory_cost, total_cost,
                        status, payment_status, tracking_code, notes
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                // Calculate labour_cost if not provided (default to 50% of repair_cost)
                $labourCost = $data['labour_cost'] ?? null;
                if ($labourCost === null && isset($data['repair_cost'])) {
                    $labourCost = floatval($data['repair_cost']) * 0.5; // Default 50% of repair cost
                }
                $labourCost = $labourCost ?? 0;
                
                $stmt->execute([
                    $data['company_id'],
                    $technicianId, // Use validated and cast technician_id
                    $data['product_id'] ?? null,
                    $data['device_brand'] ?? null,
                    $data['device_model'] ?? null,
                    $data['customer_name'],
                    $data['customer_contact'],
                    $data['customer_id'] ?? null,
                    $data['issue_description'],
                    $data['repair_cost'] ?? 0,
                    $labourCost,
                    $data['parts_cost'] ?? 0,
                    $data['accessory_cost'] ?? 0,
                    $data['total_cost'] ?? 0,
                    $data['status'] ?? 'pending',
                    $data['payment_status'] ?? 'paid', // Default to paid - payment received at booking
                    $data['tracking_code'] ?? $this->generateTrackingCode(),
                    $data['notes'] ?? null
                ]);
            } else {
                $stmt = $this->db->prepare("
                    INSERT INTO repairs_new (
                        company_id, technician_id, product_id, device_brand, device_model,
                        customer_name, customer_contact, customer_id, issue_description, 
                        repair_cost, parts_cost, accessory_cost, total_cost,
                        status, payment_status, tracking_code, notes
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $data['company_id'],
                    $technicianId, // Use validated and cast technician_id
                    $data['product_id'] ?? null,
                    $data['device_brand'] ?? null,
                    $data['device_model'] ?? null,
                    $data['customer_name'],
                    $data['customer_contact'],
                    $data['customer_id'] ?? null,
                    $data['issue_description'],
                    $data['repair_cost'] ?? 0,
                    $data['parts_cost'] ?? 0,
                    $data['accessory_cost'] ?? 0,
                    $data['total_cost'] ?? 0,
                    $data['status'] ?? 'pending',
                    $data['payment_status'] ?? 'paid', // Default to paid - payment received at booking
                    $data['tracking_code'] ?? $this->generateTrackingCode(),
                    $data['notes'] ?? null
                ]);
            }
        } else {
            // Check if labour_cost column exists
            $checkLabourCost = $this->db->query("SHOW COLUMNS FROM repairs_new LIKE 'labour_cost'");
            $hasLabourCost = $checkLabourCost && $checkLabourCost->rowCount() > 0;
            
            if ($hasLabourCost) {
                $stmt = $this->db->prepare("
                    INSERT INTO repairs_new (
                        company_id, technician_id, product_id, customer_name, customer_contact,
                        customer_id, issue_description, repair_cost, labour_cost, parts_cost, accessory_cost, total_cost,
                        status, payment_status, tracking_code, notes
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                // Calculate labour_cost if not provided (default to 50% of repair_cost)
                $labourCost = $data['labour_cost'] ?? null;
                if ($labourCost === null && isset($data['repair_cost'])) {
                    $labourCost = floatval($data['repair_cost']) * 0.5; // Default 50% of repair cost
                }
                $labourCost = $labourCost ?? 0;
                
                $stmt->execute([
                    $data['company_id'],
                    $technicianId, // Use validated and cast technician_id
                    $data['product_id'] ?? null,
                    $data['customer_name'],
                    $data['customer_contact'],
                    $data['customer_id'] ?? null,
                    $data['issue_description'],
                    $data['repair_cost'] ?? 0,
                    $labourCost,
                    $data['parts_cost'] ?? 0,
                    $data['accessory_cost'] ?? 0,
                    $data['total_cost'] ?? 0,
                    $data['status'] ?? 'pending',
                    $data['payment_status'] ?? 'paid', // Default to paid - payment received at booking
                    $data['tracking_code'] ?? $this->generateTrackingCode(),
                    $data['notes'] ?? null
                ]);
            } else {
                $stmt = $this->db->prepare("
                    INSERT INTO repairs_new (
                        company_id, technician_id, product_id, customer_name, customer_contact,
                        customer_id, issue_description, repair_cost, parts_cost, accessory_cost, total_cost,
                        status, payment_status, tracking_code, notes
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $data['company_id'],
                    $technicianId, // Use validated and cast technician_id
                    $data['product_id'] ?? null,
                    $data['customer_name'],
                    $data['customer_contact'],
                    $data['customer_id'] ?? null,
                    $data['issue_description'],
                    $data['repair_cost'] ?? 0,
                    $data['parts_cost'] ?? 0,
                    $data['accessory_cost'] ?? 0,
                    $data['total_cost'] ?? 0,
                    $data['status'] ?? 'pending',
                    $data['payment_status'] ?? 'paid', // Default to paid - payment received at booking
                    $data['tracking_code'] ?? $this->generateTrackingCode(),
                    $data['notes'] ?? null
                ]);
            }
        }
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Check if device_brand and device_model columns exist
     */
    private function checkDeviceColumnsExist() {
        try {
            $stmt = $this->db->query("SHOW COLUMNS FROM repairs_new LIKE 'device_brand'");
            return $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Update a repair
     */
    public function update($id, array $data, $company_id) {
        $stmt = $this->db->prepare("
            UPDATE repairs_new SET 
                technician_id = ?, product_id = ?, customer_name = ?, customer_contact = ?,
                customer_id = ?, issue_description = ?, repair_cost = ?, parts_cost = ?,
                total_cost = ?, status = ?, payment_status = ?, notes = ?
            WHERE id = ? AND company_id = ?
        ");
        
        return $stmt->execute([
            $data['technician_id'],
            $data['product_id'] ?? null,
            $data['customer_name'],
            $data['customer_contact'],
            $data['customer_id'] ?? null,
            $data['issue_description'],
            $data['repair_cost'] ?? 0,
            $data['parts_cost'] ?? 0,
            $data['total_cost'] ?? 0,
            $data['status'] ?? 'pending',
            $data['payment_status'] ?? 'unpaid',
            $data['notes'] ?? null,
            $id,
            $company_id
        ]);
    }

    /**
     * Find repair by ID
     */
    public function find($id, $company_id) {
        $stmt = $this->db->prepare("
            SELECT r.*, u.full_name as technician_name, p.name as product_name,
                   c.full_name as customer_name_from_table
            FROM repairs_new r
            LEFT JOIN users u ON r.technician_id = u.id
            LEFT JOIN products p ON r.product_id = p.id
            LEFT JOIN customers c ON r.customer_id = c.id
            WHERE r.id = ? AND r.company_id = ?
            LIMIT 1
        ");
        $stmt->execute([$id, $company_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Find repairs by company
     */
    public function findByCompany($company_id, $limit = 100, $status = null) {
        // Check which repairs table exists
        $checkRepairsNew = $this->db->query("SHOW TABLES LIKE 'repairs_new'");
        $hasRepairsNew = $checkRepairsNew && $checkRepairsNew->rowCount() > 0;
        $repairsTable = $hasRepairsNew ? 'repairs_new' : 'repairs';
        $statusColumn = $hasRepairsNew ? 'status' : 'repair_status';
        $technicianColumn = $hasRepairsNew ? 'technician_id' : 'technician_id';
        
        $sql = "
            SELECT r.*, u.full_name as technician_name, p.name as product_name,
                   c.full_name as customer_name_from_table
            FROM {$repairsTable} r
            LEFT JOIN users u ON r.{$technicianColumn} = u.id
            LEFT JOIN products p ON r.product_id = p.id
            LEFT JOIN customers c ON r.customer_id = c.id
            WHERE r.company_id = ?
        ";
        $params = [$company_id];
        
        if ($status) {
            // Normalize status: old table uses uppercase, new table uses lowercase
            if ($hasRepairsNew) {
                // repairs_new uses lowercase: pending, in_progress, completed, delivered, failed
                $normalizedStatus = strtolower($status);
            } else {
                // repairs uses uppercase: PENDING, IN_PROGRESS, COMPLETED, DELIVERED, CANCELLED
                $normalizedStatus = strtoupper($status);
                // Map 'failed' to 'CANCELLED' for old table if needed
                if ($normalizedStatus === 'FAILED') {
                    $normalizedStatus = 'CANCELLED';
                }
            }
            $sql .= " AND r.{$statusColumn} = ?";
            $params[] = $normalizedStatus;
        }
        
        $sql .= " ORDER BY r.created_at DESC LIMIT " . intval($limit);
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Normalize status values and customer name in results for consistency
        foreach ($results as &$result) {
            if (!$hasRepairsNew && isset($result['repair_status'])) {
                $result['status'] = strtolower($result['repair_status']);
            } elseif ($hasRepairsNew && isset($result['status'])) {
                // Ensure status is lowercase
                $result['status'] = strtolower($result['status']);
            }
            // Map customer_name_from_table to customer_name for consistency
            if (isset($result['customer_name_from_table'])) {
                $result['customer_name'] = $result['customer_name_from_table'];
            }
        }
        
        return $results;
    }

    /**
     * Find repairs by technician
     */
    public function findByTechnician($technician_id, $company_id, $status = null) {
        // Check which repairs table exists
        $checkRepairsNew = $this->db->query("SHOW TABLES LIKE 'repairs_new'");
        $hasRepairsNew = $checkRepairsNew && $checkRepairsNew->rowCount() > 0;
        $repairsTable = $hasRepairsNew ? 'repairs_new' : 'repairs';
        $statusColumn = $hasRepairsNew ? 'status' : 'repair_status';
        $technicianColumn = $hasRepairsNew ? 'technician_id' : 'technician_id';
        
        $sql = "
            SELECT r.*, p.name as product_name, c.full_name as customer_name_from_table
            FROM {$repairsTable} r
            LEFT JOIN products p ON r.product_id = p.id
            LEFT JOIN customers c ON r.customer_id = c.id
            WHERE r.{$technicianColumn} = ? AND r.company_id = ?
        ";
        $params = [$technician_id, $company_id];
        
        if ($status) {
            // Normalize status: old table uses uppercase, new table uses lowercase
            if ($hasRepairsNew) {
                // repairs_new uses lowercase: pending, in_progress, completed, delivered, failed
                $normalizedStatus = strtolower($status);
            } else {
                // repairs uses uppercase: PENDING, IN_PROGRESS, COMPLETED, DELIVERED, CANCELLED
                $normalizedStatus = strtoupper($status);
                // Map 'failed' to 'CANCELLED' for old table if needed
                if ($normalizedStatus === 'FAILED') {
                    $normalizedStatus = 'CANCELLED';
                }
            }
            $sql .= " AND r.{$statusColumn} = ?";
            $params[] = $normalizedStatus;
        }
        
        $sql .= " ORDER BY r.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Normalize status values and customer name in results for consistency
        foreach ($results as &$result) {
            if (!$hasRepairsNew && isset($result['repair_status'])) {
                $result['status'] = strtolower($result['repair_status']);
            } elseif ($hasRepairsNew && isset($result['status'])) {
                // Ensure status is lowercase
                $result['status'] = strtolower($result['status']);
            }
            // Map customer_name_from_table to customer_name for consistency
            if (isset($result['customer_name_from_table'])) {
                $result['customer_name'] = $result['customer_name_from_table'];
            }
        }
        
        return $results;
    }

    /**
     * Update repair status
     */
    public function updateStatus($id, $company_id, $status, $notes = null) {
        // Check which repairs table exists
        $checkRepairsNew = $this->db->query("SHOW TABLES LIKE 'repairs_new'");
        $hasRepairsNew = $checkRepairsNew && $checkRepairsNew->rowCount() > 0;
        $repairsTable = $hasRepairsNew ? 'repairs_new' : 'repairs';
        $statusColumn = $hasRepairsNew ? 'status' : 'repair_status';
        
        // Normalize status: old table uses uppercase, new table uses lowercase
        if ($hasRepairsNew) {
            $normalizedStatus = strtolower($status);
        } else {
            $normalizedStatus = strtoupper($status);
            // Map 'failed' to 'CANCELLED' for old table if needed
            if ($normalizedStatus === 'FAILED') {
                $normalizedStatus = 'CANCELLED';
            }
        }
        
        // Auto-set payment_status to 'paid' when status is 'delivered' or 'completed'
        $autoPayStatuses = $hasRepairsNew ? ['delivered', 'completed'] : ['DELIVERED', 'COMPLETED'];
        $shouldAutoPay = in_array($normalizedStatus, $autoPayStatuses);
        $normalizedPaymentStatus = $hasRepairsNew ? 'paid' : 'PAID';
        
        // Check if completed_at column exists (safely check table structure)
        $checkCompletedAt = $this->db->query("SHOW COLUMNS FROM `{$repairsTable}` LIKE 'completed_at'");
        $hasCompletedAt = $checkCompletedAt && $checkCompletedAt->rowCount() > 0;
        
        if ($hasCompletedAt) {
            if ($shouldAutoPay) {
                $sql = "
                    UPDATE {$repairsTable} SET 
                        {$statusColumn} = ?, 
                        payment_status = ?,
                        notes = COALESCE(?, notes),
                        completed_at = CASE WHEN ? = 'completed' OR ? = 'COMPLETED' THEN NOW() ELSE completed_at END
                    WHERE id = ? AND company_id = ?
                ";
                $params = [$normalizedStatus, $normalizedPaymentStatus, $notes, $normalizedStatus, $normalizedStatus, $id, $company_id];
            } else {
                $sql = "
                    UPDATE {$repairsTable} SET 
                        {$statusColumn} = ?, 
                        notes = COALESCE(?, notes),
                        completed_at = CASE WHEN ? = 'completed' OR ? = 'COMPLETED' THEN NOW() ELSE completed_at END
                    WHERE id = ? AND company_id = ?
                ";
                $params = [$normalizedStatus, $notes, $normalizedStatus, $normalizedStatus, $id, $company_id];
            }
        } else {
            if ($shouldAutoPay) {
                $sql = "
                    UPDATE {$repairsTable} SET 
                        {$statusColumn} = ?, 
                        payment_status = ?,
                        notes = COALESCE(?, notes)
                    WHERE id = ? AND company_id = ?
                ";
                $params = [$normalizedStatus, $normalizedPaymentStatus, $notes, $id, $company_id];
            } else {
                $sql = "
                    UPDATE {$repairsTable} SET 
                        {$statusColumn} = ?, 
                        notes = COALESCE(?, notes)
                    WHERE id = ? AND company_id = ?
                ";
                $params = [$normalizedStatus, $notes, $id, $company_id];
            }
        }
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Update payment status
     */
    public function updatePaymentStatus($id, $company_id, $payment_status) {
        // Check which repairs table exists
        $checkRepairsNew = $this->db->query("SHOW TABLES LIKE 'repairs_new'");
        $hasRepairsNew = $checkRepairsNew && $checkRepairsNew->rowCount() > 0;
        $repairsTable = $hasRepairsNew ? 'repairs_new' : 'repairs';
        
        // Normalize payment status: old table uses uppercase, new table uses lowercase
        if ($hasRepairsNew) {
            $normalizedPaymentStatus = strtolower($payment_status);
        } else {
            $normalizedPaymentStatus = strtoupper($payment_status);
        }
        
        $stmt = $this->db->prepare("
            UPDATE {$repairsTable} SET payment_status = ?
            WHERE id = ? AND company_id = ?
        ");
        
        return $stmt->execute([$normalizedPaymentStatus, $id, $company_id]);
    }

    /**
     * Get repair statistics
     */
    public function getStats($company_id) {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_repairs,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_repairs,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_repairs,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_repairs,
                SUM(CASE WHEN payment_status = 'unpaid' THEN 1 ELSE 0 END) as unpaid_repairs,
                SUM(total_cost) as total_revenue
            FROM repairs_new 
            WHERE company_id = ?
        ");
        $stmt->execute([$company_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Generate unique tracking code
     */
    private function generateTrackingCode() {
        return 'REP' . date('Ymd') . rand(1000, 9999);
    }

    /**
     * Find repair by tracking code
     */
    public function findByTrackingCode($tracking_code, $company_id) {
        $stmt = $this->db->prepare("
            SELECT r.*, u.full_name as technician_name, p.name as product_name,
                   c.full_name as customer_name_from_table
            FROM repairs_new r
            LEFT JOIN users u ON r.technician_id = u.id
            LEFT JOIN products p ON r.product_id = p.id
            LEFT JOIN customers c ON r.customer_id = c.id
            WHERE r.tracking_code = ? AND r.company_id = ?
            LIMIT 1
        ");
        $stmt->execute([$tracking_code, $company_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get technician performance data
     */
    public function getTechnicianPerformance($company_id, $period = 'today') {
        $dateCondition = '';
        $params = [$company_id];
        
        switch ($period) {
            case 'today':
                $dateCondition = 'DATE(r.created_at) = CURDATE()';
                break;
            case 'week':
                $dateCondition = 'r.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)';
                break;
            case 'month':
                $dateCondition = 'r.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)';
                break;
        }
        
        $stmt = $this->db->prepare("
            SELECT 
                r.technician_id,
                u.full_name as technician_name,
                COUNT(r.id) as total_repairs,
                SUM(r.total_cost) as total_revenue,
                SUM(r.accessory_cost) as total_accessory_cost,
                AVG(r.total_cost) as avg_repair_value,
                SUM(CASE WHEN r.status = 'completed' THEN 1 ELSE 0 END) as completed_repairs
            FROM repairs_new r
            LEFT JOIN users u ON r.technician_id = u.id
            WHERE r.company_id = ? AND $dateCondition
            GROUP BY r.technician_id, u.full_name
            ORDER BY total_revenue DESC
        ");
        
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get repair summary reports
     */
    public function getSummaryReports($company_id, $start_date, $end_date) {
        $stmt = $this->db->prepare("
            SELECT 
                DATE(r.created_at) as repair_date,
                r.technician_id,
                u.full_name as technician_name,
                COUNT(r.id) as total_repairs,
                SUM(r.accessory_cost) as accessories_cost,
                SUM(r.total_cost) as total_revenue,
                SUM(CASE WHEN r.status = 'completed' THEN 1 ELSE 0 END) as completed_repairs,
                SUM(CASE WHEN r.payment_status = 'paid' THEN 1 ELSE 0 END) as paid_repairs
            FROM repairs_new r
            LEFT JOIN users u ON r.technician_id = u.id
            WHERE r.company_id = ? 
            AND DATE(r.created_at) BETWEEN ? AND ?
            GROUP BY DATE(r.created_at), r.technician_id, u.full_name
            ORDER BY repair_date DESC, total_revenue DESC
        ");
        
        $stmt->execute([$company_id, $start_date, $end_date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Approve repair (Manager only)
     */
    public function approve($id, $company_id, $manager_id) {
        $stmt = $this->db->prepare("
            UPDATE repairs_new SET 
                status = 'completed',
                approved_by = ?,
                approved_at = NOW()
            WHERE id = ? AND company_id = ? AND status = 'pending_approval'
        ");
        
        return $stmt->execute([$manager_id, $id, $company_id]);
    }

    /**
     * Get repairs awaiting approval
     */
    public function getPendingApprovals($company_id) {
        $stmt = $this->db->prepare("
            SELECT r.*, u.full_name as technician_name, p.name as product_name
            FROM repairs_new r
            LEFT JOIN users u ON r.technician_id = u.id
            LEFT JOIN products p ON r.product_id = p.id
            WHERE r.company_id = ? AND r.status = 'pending_approval'
            ORDER BY r.created_at ASC
        ");
        
        $stmt->execute([$company_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}