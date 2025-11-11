<?php

namespace App\Models;

use PDO;

require_once __DIR__ . '/../../config/database.php';

class Phone {
    private $conn;
    private $table = 'phones';

    public function __construct() {
        $this->conn = \Database::getInstance()->getConnection();
    }

    /**
     * Create a new phone (Multi-tenant)
     */
    public function create($data) {
        $sql = "INSERT INTO {$this->table} (company_id, unique_id, brand, model, imei, phone_condition, purchase_price, selling_price, phone_value, status, notes)
                VALUES (:company_id, :unique_id, :brand, :model, :imei, :condition, :purchase_price, :selling_price, :value, :status, :notes)";
        $stmt = $this->conn->prepare($sql);
        
        $params = [
            'company_id' => $data['company_id'],
            'unique_id' => $data['unique_id'],
            'brand' => $data['brand'],
            'model' => $data['model'],
            'imei' => $data['imei'] ?? null,
            'condition' => $data['condition'] ?? 'good',
            'purchase_price' => $data['purchase_price'] ?? null,
            'selling_price' => $data['selling_price'] ?? null,
            'value' => $data['value'],
            'status' => $data['status'] ?? 'AVAILABLE',
            'notes' => $data['notes'] ?? null
        ];
        
        return $stmt->execute($params);
    }

    /**
     * Find phone by ID
     */
    public function findById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Find phone by unique_id
     */
    public function findByUniqueId($uniqueId) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE unique_id = :unique_id LIMIT 1");
        $stmt->execute(['unique_id' => $uniqueId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Find phone by IMEI
     */
    public function findByImei($imei) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE imei = :imei LIMIT 1");
        $stmt->execute(['imei' => $imei]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get all phones
     */
    public function all() {
        return $this->conn->query("SELECT * FROM {$this->table} ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all phones by company (Multi-tenant filtering)
     */
    public function allByCompany($company_id) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE company_id = :company_id ORDER BY id DESC");
        $stmt->execute(['company_id' => $company_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get available phones
     */
    public function available() {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE status = 'AVAILABLE' ORDER BY id DESC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get available phones by company
     */
    public function availableByCompany($company_id) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE status = 'AVAILABLE' AND company_id = :company_id ORDER BY id DESC");
        $stmt->execute(['company_id' => $company_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update phone information
     */
    public function update($id, $data) {
        $fields = [];
        $params = ['id' => $id];
        
        foreach ($data as $key => $value) {
            if ($key !== 'id') {
                $fields[] = "$key = :$key";
                $params[$key] = $value;
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Delete phone
     */
    public function delete($id) {
        $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
}

