<?php

namespace App\Models;

use App\Services\ReturnVisibilityPolicy;
use PDO;

require_once __DIR__ . '/../../config/database.php';

/**
 * POS return records (table name: returns).
 */
class InventoryReturn {
    private $db;
    private $table = 'returns';

    /** @var bool|null */
    private static $posSalesHasSalesPersonId = null;

    public function __construct() {
        $this->db = \Database::getInstance()->getConnection();
    }

    private function posSalesHasSalesPersonIdColumn(): bool {
        if (self::$posSalesHasSalesPersonId !== null) {
            return self::$posSalesHasSalesPersonId;
        }
        try {
            $stmt = $this->db->prepare("SHOW COLUMNS FROM pos_sales LIKE 'sales_person_id'");
            $stmt->execute();
            self::$posSalesHasSalesPersonId = $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            self::$posSalesHasSalesPersonId = false;
        }
        return self::$posSalesHasSalesPersonId;
    }

    /** SQL expression for the user credited with the sale (returns visibility for sales roles). */
    private function saleOwnerSqlExpr(string $psAlias = 'ps'): string {
        if ($this->posSalesHasSalesPersonIdColumn()) {
            return "COALESCE({$psAlias}.sales_person_id, {$psAlias}.created_by_user_id)";
        }
        return "{$psAlias}.created_by_user_id";
    }

    public static function tableExists(): bool {
        try {
            $db = \Database::getInstance()->getConnection();
            $r = $db->query("SHOW TABLES LIKE 'returns'");
            return $r && $r->rowCount() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function createHeader(int $companyId, int $posSaleId, int $createdBy, string $createdRole, ?string $notes = null): int {
        $stmt = $this->db->prepare(
            "INSERT INTO {$this->table} (company_id, pos_sale_id, notes, created_by, created_role) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$companyId, $posSaleId, $notes, $createdBy, $createdRole]);
        return (int)$this->db->lastInsertId();
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findById(int $id, ?int $companyId = null): ?array {
        if (!self::tableExists()) {
            return null;
        }
        $ownerExpr = $this->saleOwnerSqlExpr('ps');
        $sql = "SELECT r.*, ps.unique_id AS sale_code, ps.created_at AS sale_created_at, u.username AS created_by_username,
                       c.name AS company_name,
                       {$ownerExpr} AS sale_owner_id,
                       ou.full_name AS sale_owner_name
                FROM {$this->table} r
                INNER JOIN pos_sales ps ON r.pos_sale_id = ps.id
                LEFT JOIN users u ON r.created_by = u.id
                LEFT JOIN users ou ON ou.id = {$ownerExpr}
                LEFT JOIN companies c ON r.company_id = c.id
                WHERE r.id = ?";
        $params = [$id];
        if ($companyId !== null) {
            $sql .= ' AND r.company_id = ?';
            $params[] = $companyId;
        }
        $sql .= ' LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Load a return if it exists and the viewer is allowed to see it (role + sale ownership).
     */
    public function findForViewer(int $id, ?int $companyId, string $role, int $userId): ?array {
        $row = $this->findById($id, $companyId);
        if (!$row) {
            return null;
        }
        if (!ReturnVisibilityPolicy::canViewReturnRecord($row, $role, $userId)) {
            return null;
        }
        return $row;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listByCompany(?int $companyId, int $limit = 100): array {
        if (!self::tableExists()) {
            return [];
        }
        $ownerExpr = $this->saleOwnerSqlExpr('ps');
        $sql = "SELECT r.*, ps.unique_id AS sale_code, ps.created_at AS sale_created_at, u.username AS created_by_username,
                       c.name AS company_name,
                       {$ownerExpr} AS sale_owner_id,
                       ou.full_name AS sale_owner_name
                FROM {$this->table} r
                INNER JOIN pos_sales ps ON r.pos_sale_id = ps.id
                LEFT JOIN users u ON r.created_by = u.id
                LEFT JOIN users ou ON ou.id = {$ownerExpr}
                LEFT JOIN companies c ON r.company_id = c.id
                WHERE 1=1";
        $params = [];
        if ($companyId !== null) {
            $sql .= ' AND r.company_id = ?';
            $params[] = $companyId;
        }
        $sql .= ' ORDER BY r.created_at DESC LIMIT ' . max(1, min(300, $limit));
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * List returns with role-based row filtering (manager: all in company; sales: own sales only;
     * admin/system_admin: all in scope — same SQL without sales filter for admins).
     *
     * @param 'manager'|'admin'|'system_admin'|'salesperson'|'sales'|string $role
     */
    public function listForViewer(?int $companyId, int $limit, string $role, int $userId): array {
        if (!self::tableExists()) {
            return [];
        }
        $roleNorm = ReturnVisibilityPolicy::normalizeRole($role);
        $limit = max(1, min(300, $limit));
        $ownerExpr = $this->saleOwnerSqlExpr('ps');
        $sql = "SELECT r.*, ps.unique_id AS sale_code, ps.created_at AS sale_created_at, u.username AS created_by_username,
                       c.name AS company_name,
                       {$ownerExpr} AS sale_owner_id,
                       ou.full_name AS sale_owner_name
                FROM {$this->table} r
                INNER JOIN pos_sales ps ON r.pos_sale_id = ps.id
                LEFT JOIN users u ON r.created_by = u.id
                LEFT JOIN users ou ON ou.id = {$ownerExpr}
                LEFT JOIN companies c ON r.company_id = c.id
                WHERE 1=1";
        $params = [];
        if ($companyId !== null) {
            $sql .= ' AND r.company_id = ?';
            $params[] = $companyId;
        }
        if (ReturnVisibilityPolicy::isSalesRole($roleNorm)) {
            $sql .= " AND {$ownerExpr} = ?";
            $params[] = $userId;
        }
        $sql .= ' ORDER BY r.created_at DESC LIMIT ' . $limit;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array{total: int, last_30_days: int}
     */
    public function statsForCompany(int $companyId, string $role, int $userId): array {
        $out = ['total' => 0, 'last_30_days' => 0];
        if (!self::tableExists()) {
            return $out;
        }
        $roleNorm = ReturnVisibilityPolicy::normalizeRole($role);
        $ownerExpr = $this->saleOwnerSqlExpr('ps');
        $base = " FROM {$this->table} r INNER JOIN pos_sales ps ON r.pos_sale_id = ps.id WHERE r.company_id = ?";
        $params = [$companyId];
        if (ReturnVisibilityPolicy::isSalesRole($roleNorm)) {
            $base .= " AND {$ownerExpr} = ?";
            $params[] = $userId;
        }
        try {
            $q1 = "SELECT COUNT(*) " . $base;
            $st = $this->db->prepare($q1);
            $st->execute($params);
            $out['total'] = (int)$st->fetchColumn();
            $q2 = "SELECT COUNT(*) " . $base . " AND r.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            $st2 = $this->db->prepare($q2);
            $st2->execute($params);
            $out['last_30_days'] = (int)$st2->fetchColumn();
        } catch (\Exception $e) {
            error_log('InventoryReturn::statsForCompany: ' . $e->getMessage());
        }
        return $out;
    }
}
