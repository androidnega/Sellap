<?php

namespace App\Services;

use App\Models\CompanyFeature;
use App\Models\InventoryReturn;
use App\Models\InventoryReturnItem;
use App\Models\Product;
use App\Models\StockMovement;
use PDO;

/**
 * Cancellations, returns, and stock_movements for POS (pos_sales / pos_sale_items).
 */
class PosOrderInventoryService {
    private $db;
    private $product;
    private $stockMovement;
    private $companyFeature;
    private $inventoryReturn;
    private $returnItem;

    public function __construct() {
        $this->db = \Database::getInstance()->getConnection();
        $this->product = new Product();
        $this->stockMovement = new StockMovement();
        $this->companyFeature = new CompanyFeature();
        $this->inventoryReturn = new InventoryReturn();
        $this->returnItem = new InventoryReturnItem();
    }

    public function schemaReady(): bool {
        return $this->columnExists('pos_sales', 'status') && StockMovement::tableExists();
    }

    private function columnExists(string $table, string $column): bool {
        $s = $this->db->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $s->execute([$table, $column]);
        return (int)$s->fetchColumn() > 0;
    }

    public function mapRoleForAudit(string $appRole): string {
        $r = strtolower(trim($appRole));
        if ($r === 'salesperson' || $r === 'sales') {
            return 'sales';
        }
        if ($r === 'manager') {
            return 'manager';
        }
        // POS checkout by other roles: record as sales for audit (no admin stock actions here)
        return 'sales';
    }

    public function cancelledRoleForDb(string $appRole): string {
        return $this->mapRoleForAudit($appRole);
    }

    public function recordSaleMovements(int $companyId, int $posSaleId, int $userId, string $appRole): void {
        if (!$this->schemaReady()) {
            return;
        }
        $stmt = $this->db->prepare('SELECT id, item_id, quantity FROM pos_sale_items WHERE pos_sale_id = ?');
        $stmt->execute([$posSaleId]);
        $lines = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $auditRole = $this->mapRoleForAudit($appRole);
        foreach ($lines as $line) {
            $pid = (int)($line['item_id'] ?? 0);
            if ($pid <= 0) {
                continue;
            }
            $qty = (int)($line['quantity'] ?? 0);
            if ($qty <= 0) {
                continue;
            }
            $this->stockMovement->insert(
                $companyId,
                $pid,
                'sale',
                $qty,
                (int)$line['id'],
                'pos_sale_item',
                $userId,
                $auditRole
            );
        }
    }

    /**
     * @return array{ok:bool,message:string}
     */
    public function cancelOrder(int $posSaleId, int $companyId, int $userId, string $appRole): array {
        if (!$this->schemaReady()) {
            return ['ok' => false, 'message' => 'Run the database migration (pos order inventory) to enable cancellations.'];
        }
        $r = strtolower(trim($appRole));
        if (in_array($r, ['admin', 'system_admin'], true)) {
            return ['ok' => false, 'message' => 'Administrators use logs and company features only. Managers or sales staff must cancel orders.'];
        }
        $isSales = in_array($r, ['salesperson', 'sales'], true);
        $isManager = ($r === 'manager');
        if (!$isSales && !$isManager) {
            return ['ok' => false, 'message' => 'Only sales or manager roles may cancel orders.'];
        }

        $this->db->beginTransaction();
        try {
            $sale = $this->lockSale($posSaleId, $companyId);
            if (!$sale) {
                $this->db->rollBack();
                return ['ok' => false, 'message' => 'Sale not found.'];
            }
            if (!empty($sale['deleted_at'])) {
                $this->db->rollBack();
                return ['ok' => false, 'message' => 'This sale is archived and cannot be cancelled.'];
            }
            if (($sale['status'] ?? 'completed') !== 'completed') {
                $this->db->rollBack();
                return ['ok' => false, 'message' => 'Only active sales can be cancelled.'];
            }
            if ($this->saleIsBlockedSwap($sale)) {
                $this->db->rollBack();
                return ['ok' => false, 'message' => 'Swap-linked sales cannot be cancelled from POS.'];
            }
            if ($isSales) {
                $created = strtotime($sale['created_at'] ?? 'now');
                if (time() - $created > 30 * 60) {
                    $this->db->rollBack();
                    return ['ok' => false, 'message' => 'Sales can only cancel within 30 minutes of checkout.'];
                }
            }

            $linesStmt = $this->db->prepare('SELECT * FROM pos_sale_items WHERE pos_sale_id = ? FOR UPDATE');
            $linesStmt->execute([$posSaleId]);
            $lines = $linesStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($lines as $line) {
                if ($this->lineIsSwappedResale($line)) {
                    $this->db->rollBack();
                    return ['ok' => false, 'message' => 'This sale includes swapped-resale inventory lines; cancel is blocked.'];
                }
                $pid = (int)($line['item_id'] ?? 0);
                if ($pid <= 0) {
                    continue;
                }
                $qty = (int)($line['quantity'] ?? 0);
                if ($qty <= 0) {
                    continue;
                }
                $p = $this->product->find($pid, $companyId);
                if (!$p) {
                    continue;
                }
                $newQty = (int)($p['quantity'] ?? 0) + $qty;
                $this->product->updateQuantity($pid, $newQty, $companyId);
                $this->stockMovement->insert(
                    $companyId,
                    $pid,
                    'cancel',
                    $qty,
                    $posSaleId,
                    'pos_sale',
                    $userId,
                    $this->mapRoleForAudit($appRole)
                );
            }

            $roleDb = $this->cancelledRoleForDb($appRole);
            $upd = $this->db->prepare(
                'UPDATE pos_sales SET status = ?, cancelled_by = ?, cancelled_role = ?, cancelled_at = NOW() WHERE id = ? AND company_id = ?'
            );
            $upd->execute(['cancelled', $userId, $roleDb, $posSaleId, $companyId]);
            $this->db->commit();
            return ['ok' => true, 'message' => 'Order cancelled and stock restored.'];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('PosOrderInventoryService::cancelOrder ' . $e->getMessage());
            return ['ok' => false, 'message' => 'Cancel failed.'];
        }
    }

    /**
     * @param list<array{pos_sale_item_id:int,quantity:int}> $lines
     * @return array{ok:bool,message:string,return_id?:int}
     */
    public function processReturn(
        int $posSaleId,
        int $companyId,
        int $userId,
        string $appRole,
        array $lines,
        ?string $notes = null
    ): array {
        if (!$this->schemaReady() || !InventoryReturn::tableExists()) {
            return ['ok' => false, 'message' => 'Returns are not available until the database migration is applied.'];
        }
        $r = strtolower(trim($appRole));
        if ($r !== 'manager') {
            return ['ok' => false, 'message' => 'Only managers can process returns.'];
        }
        if (!$this->companyFeature->isEnabled($companyId, 'returns_enabled')) {
            return ['ok' => false, 'message' => 'Returns are disabled for this company. An admin can enable them under Company features.'];
        }
        if (empty($lines)) {
            return ['ok' => false, 'message' => 'No return lines provided.'];
        }

        $this->db->beginTransaction();
        try {
            $sale = $this->lockSale($posSaleId, $companyId);
            if (!$sale) {
                $this->db->rollBack();
                return ['ok' => false, 'message' => 'Sale not found.'];
            }
            if (!empty($sale['deleted_at'])) {
                $this->db->rollBack();
                return ['ok' => false, 'message' => 'This sale is archived and cannot be returned.'];
            }
            if (($sale['status'] ?? '') === 'cancelled') {
                $this->db->rollBack();
                return ['ok' => false, 'message' => 'Cancelled sales cannot be returned.'];
            }

            $linesStmt = $this->db->prepare('SELECT * FROM pos_sale_items WHERE pos_sale_id = ? FOR UPDATE');
            $linesStmt->execute([$posSaleId]);
            $dbLines = $linesStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $byId = [];
            foreach ($dbLines as $row) {
                $byId[(int)$row['id']] = $row;
            }

            foreach ($lines as $req) {
                $sid = (int)($req['pos_sale_item_id'] ?? 0);
                $rqty = (int)($req['quantity'] ?? 0);
                if ($sid <= 0 || $rqty <= 0) {
                    $this->db->rollBack();
                    return ['ok' => false, 'message' => 'Each line needs a valid sale item id and quantity.'];
                }
                if (!isset($byId[$sid])) {
                    $this->db->rollBack();
                    return ['ok' => false, 'message' => 'Invalid sale line for this order.'];
                }
                $row = $byId[$sid];
                if ($this->lineIsSwappedResale($row)) {
                    $this->db->rollBack();
                    return ['ok' => false, 'message' => 'Swapped-resale lines cannot be returned through this flow.'];
                }
                $sold = (int)($row['quantity'] ?? 0);
                $already = (int)($row['returned_quantity'] ?? 0);
                if ($already + $rqty > $sold) {
                    $this->db->rollBack();
                    return ['ok' => false, 'message' => 'Return quantity exceeds purchased quantity for one or more lines.'];
                }
            }

            $returnId = $this->inventoryReturn->createHeader(
                $companyId,
                $posSaleId,
                $userId,
                $this->mapRoleForAudit($appRole),
                $notes
            );

            foreach ($lines as $req) {
                $sid = (int)$req['pos_sale_item_id'];
                $rqty = (int)$req['quantity'];
                $row = $byId[$sid];
                $pid = (int)($row['item_id'] ?? 0);

                $this->returnItem->insert($returnId, $sid, $pid > 0 ? $pid : null, $rqty);

                $up = $this->db->prepare(
                    'UPDATE pos_sale_items SET returned_quantity = returned_quantity + ? WHERE id = ? AND pos_sale_id = ?'
                );
                $up->execute([$rqty, $sid, $posSaleId]);

                if ($pid > 0) {
                    $p = $this->product->find($pid, $companyId);
                    if ($p) {
                        $newQty = (int)($p['quantity'] ?? 0) + $rqty;
                        $this->product->updateQuantity($pid, $newQty, $companyId);
                        $this->stockMovement->insert(
                            $companyId,
                            $pid,
                            'return',
                            $rqty,
                            $returnId,
                            'return',
                            $userId,
                            $this->mapRoleForAudit($appRole)
                        );
                    }
                }
            }

            $this->refreshSaleReturnStatus($posSaleId);
            $this->db->commit();
            return ['ok' => true, 'message' => 'Return recorded.', 'return_id' => $returnId];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('PosOrderInventoryService::processReturn ' . $e->getMessage());
            return ['ok' => false, 'message' => 'Return failed.'];
        }
    }

    private function refreshSaleReturnStatus(int $posSaleId): void {
        $stmt = $this->db->prepare('SELECT quantity, returned_quantity FROM pos_sale_items WHERE pos_sale_id = ?');
        $stmt->execute([$posSaleId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($rows === []) {
            return;
        }
        $anySold = false;
        $allReturned = true;
        foreach ($rows as $row) {
            $q = (int)($row['quantity'] ?? 0);
            if ($q <= 0) {
                continue;
            }
            $anySold = true;
            $rq = (int)($row['returned_quantity'] ?? 0);
            if ($rq < $q) {
                $allReturned = false;
                break;
            }
        }
        if (!$anySold) {
            return;
        }
        $status = $allReturned ? 'returned' : 'completed';
        $u = $this->db->prepare('UPDATE pos_sales SET status = ? WHERE id = ? AND status <> ?');
        $u->execute([$status, $posSaleId, 'cancelled']);
    }

    /**
     * @return array<string,mixed>|null
     */
    private function lockSale(int $posSaleId, int $companyId): ?array {
        $stmt = $this->db->prepare('SELECT * FROM pos_sales WHERE id = ? AND company_id = ? FOR UPDATE LIMIT 1');
        $stmt->execute([$posSaleId, $companyId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function saleIsBlockedSwap(array $sale): bool {
        if (!empty($sale['swap_id'])) {
            return true;
        }
        if (isset($sale['is_swap_mode']) && (int)$sale['is_swap_mode'] === 1) {
            return true;
        }
        return false;
    }

    private function lineIsSwappedResale(array $line): bool {
        return isset($line['is_swapped_item']) && (int)$line['is_swapped_item'] === 1;
    }
}
