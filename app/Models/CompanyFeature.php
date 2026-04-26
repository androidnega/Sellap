<?php

namespace App\Models;

use PDO;

require_once __DIR__ . '/../../config/database.php';

class CompanyFeature {
    private $db;

    public function __construct() {
        $this->db = \Database::getInstance()->getConnection();
    }

    public static function tableExists(): bool {
        try {
            $db = \Database::getInstance()->getConnection();
            $r = $db->query("SHOW TABLES LIKE 'company_features'");
            return $r && $r->rowCount() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function isEnabled(int $companyId, string $featureKey): bool {
        if (!self::tableExists()) {
            return false;
        }
        $stmt = $this->db->prepare(
            'SELECT enabled FROM company_features WHERE company_id = ? AND feature_key = ? LIMIT 1'
        );
        $stmt->execute([$companyId, $featureKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return false;
        }
        return (int)$row['enabled'] === 1;
    }

    public function setEnabled(int $companyId, string $featureKey, bool $enabled, ?int $updatedBy = null): void {
        $stmt = $this->db->prepare(
            'INSERT INTO company_features (company_id, feature_key, enabled, updated_by)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE enabled = VALUES(enabled), updated_by = VALUES(updated_by)'
        );
        $stmt->execute([$companyId, $featureKey, $enabled ? 1 : 0, $updatedBy]);
    }
}
