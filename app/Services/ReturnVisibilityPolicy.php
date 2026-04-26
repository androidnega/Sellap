<?php

namespace App\Services;

/**
 * Central rules for who may see or act on POS return records (returns + linked pos_sales).
 */
class ReturnVisibilityPolicy {
    public static function normalizeRole(string $role): string {
        $r = strtolower(trim($role));
        return $r === 'sales' ? 'salesperson' : $r;
    }

    public static function isSalesRole(string $role): bool {
        return self::normalizeRole($role) === 'salesperson';
    }

    /**
     * Managers may process returns (stock) from the sale detail API.
     */
    public static function canProcessReturns(string $role): bool {
        return self::normalizeRole($role) === 'manager';
    }

    /**
     * Company admin / platform admin: full return list for audit, no processing.
     */
    public static function isAdminAuditorRole(string $role): bool {
        $r = self::normalizeRole($role);
        return $r === 'admin' || $r === 'system_admin';
    }

    /**
     * Row must include sale_owner_id (COALESCE(sales_person_id, created_by_user_id) from pos_sales).
     */
    public static function canViewReturnRecord(array $row, string $role, int $userId): bool {
        $r = self::normalizeRole($role);
        if (in_array($r, ['manager', 'admin', 'system_admin'], true)) {
            return true;
        }
        if (self::isSalesRole($r)) {
            $owner = (int)($row['sale_owner_id'] ?? 0);
            return $owner > 0 && $owner === $userId;
        }
        return false;
    }

    /**
     * UI hint: list/detail is view-only (no return processing from this screen).
     */
    public static function returnsUiReadOnly(string $role): bool {
        $r = self::normalizeRole($role);
        return self::isSalesRole($r) || self::isAdminAuditorRole($r);
    }
}
