<?php

namespace App\Models;

use PDO;

require_once __DIR__ . '/../../config/database.php';

class SalePayment {
    private $conn;
    private $table = 'pos_sale_payments';

    public function __construct() {
        $this->conn = \Database::getInstance()->getConnection();
    }

    /**
     * Create a new payment record
     */
    public function create($data) {
        $sql = "INSERT INTO {$this->table} (
                    pos_sale_id, company_id, amount, payment_method, 
                    recorded_by_user_id, notes
                ) VALUES (
                    :pos_sale_id, :company_id, :amount, :payment_method,
                    :recorded_by_user_id, :notes
                )";
        
        $stmt = $this->conn->prepare($sql);
        
        $paymentMethod = strtoupper($data['payment_method'] ?? 'CASH');
        $allowedMethods = ['CASH', 'MOBILE_MONEY', 'CARD', 'BANK_TRANSFER'];
        if (!in_array($paymentMethod, $allowedMethods)) {
            $paymentMethod = 'CASH';
        }
        
        $params = [
            'pos_sale_id' => $data['pos_sale_id'],
            'company_id' => $data['company_id'],
            'amount' => $data['amount'],
            'payment_method' => $paymentMethod,
            'recorded_by_user_id' => $data['recorded_by_user_id'],
            'notes' => $data['notes'] ?? null
        ];
        
        try {
            $stmt->execute($params);
            $paymentId = $this->conn->lastInsertId();
            
            // Update sale payment status after adding payment
            $this->updateSalePaymentStatus($data['pos_sale_id'], $data['company_id']);
            
            return $paymentId;
        } catch (\PDOException $e) {
            error_log("SalePayment create error: " . $e->getMessage());
            throw new \Exception('Failed to create payment: ' . $e->getMessage());
        }
    }

    /**
     * Get all payments for a sale
     */
    public function getBySaleId($saleId, $companyId = null) {
        $sql = "SELECT p.*, u.full_name as recorded_by_name, u.username as recorded_by_username
                FROM {$this->table} p
                LEFT JOIN users u ON p.recorded_by_user_id = u.id
                WHERE p.pos_sale_id = :sale_id";
        
        $params = ['sale_id' => $saleId];
        
        if ($companyId !== null) {
            $sql .= " AND p.company_id = :company_id";
            $params['company_id'] = $companyId;
        }
        
        $sql .= " ORDER BY p.created_at ASC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get total amount paid for a sale
     */
    public function getTotalPaid($saleId, $companyId = null) {
        $sql = "SELECT COALESCE(SUM(amount), 0) as total_paid
                FROM {$this->table}
                WHERE pos_sale_id = :sale_id";
        
        $params = ['sale_id' => $saleId];
        
        if ($companyId !== null) {
            $sql .= " AND company_id = :company_id";
            $params['company_id'] = $companyId;
        }
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return floatval($result['total_paid'] ?? 0);
    }

    /**
     * Update sale payment status based on total payments
     */
    private function updateSalePaymentStatus($saleId, $companyId) {
        // Get sale details
        $saleStmt = $this->conn->prepare("
            SELECT final_amount FROM pos_sales 
            WHERE id = :sale_id AND company_id = :company_id
        ");
        $saleStmt->execute(['sale_id' => $saleId, 'company_id' => $companyId]);
        $sale = $saleStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$sale) {
            return;
        }
        
        $finalAmount = floatval($sale['final_amount']);
        $totalPaid = $this->getTotalPaid($saleId, $companyId);
        
        // Determine payment status
        $paymentStatus = 'UNPAID';
        if ($totalPaid >= $finalAmount) {
            $paymentStatus = 'PAID';
        } elseif ($totalPaid > 0) {
            $paymentStatus = 'PARTIAL';
        }
        
        // Update sale payment status
        $updateStmt = $this->conn->prepare("
            UPDATE pos_sales 
            SET payment_status = :payment_status, updated_at = NOW()
            WHERE id = :sale_id AND company_id = :company_id
        ");
        $updateStmt->execute([
            'payment_status' => $paymentStatus,
            'sale_id' => $saleId,
            'company_id' => $companyId
        ]);
    }

    /**
     * Get payment by ID
     */
    public function findById($id, $companyId = null) {
        $sql = "SELECT p.*, u.full_name as recorded_by_name, u.username as recorded_by_username
                FROM {$this->table} p
                LEFT JOIN users u ON p.recorded_by_user_id = u.id
                WHERE p.id = :id";
        
        $params = ['id' => $id];
        
        if ($companyId !== null) {
            $sql .= " AND p.company_id = :company_id";
            $params['company_id'] = $companyId;
        }
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Delete payment (and update sale status)
     */
    public function delete($id, $companyId, $saleId) {
        $stmt = $this->conn->prepare("
            DELETE FROM {$this->table} 
            WHERE id = :id AND company_id = :company_id
        ");
        $result = $stmt->execute(['id' => $id, 'company_id' => $companyId]);
        
        if ($result) {
            // Update sale payment status after deletion
            $this->updateSalePaymentStatus($saleId, $companyId);
        }
        
        return $result;
    }

    /**
     * Get payment statistics for a sale
     */
    public function getPaymentStats($saleId, $companyId = null) {
        $totalPaid = $this->getTotalPaid($saleId, $companyId);
        
        // Get sale final amount
        $sql = "SELECT final_amount FROM pos_sales WHERE id = :sale_id";
        $params = ['sale_id' => $saleId];
        
        if ($companyId !== null) {
            $sql .= " AND company_id = :company_id";
            $params['company_id'] = $companyId;
        }
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $sale = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $finalAmount = floatval($sale['final_amount'] ?? 0);
        $remaining = max(0, $finalAmount - $totalPaid);
        
        return [
            'total_paid' => $totalPaid,
            'final_amount' => $finalAmount,
            'remaining' => $remaining,
            'payment_status' => $totalPaid >= $finalAmount ? 'PAID' : ($totalPaid > 0 ? 'PARTIAL' : 'UNPAID')
        ];
    }
}

