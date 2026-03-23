<?php

namespace App\Controllers;

use App\Models\Product;

/**
 * Mobile PWA scanner API — JWT auth (Authorization: Bearer), same as POSController.
 */
class PwaScannerController
{
    private $product;

    public function __construct()
    {
        $this->product = new Product();
    }

    /**
     * Web app manifest — uses APP_URL (production: https://sellapp.store) plus BASE_URL_PATH for subfolders.
     */
    public function manifestWebManifest(): void
    {
        $origin = rtrim(defined('APP_URL') ? APP_URL : 'https://sellapp.store', '/');
        $pathPrefix = (defined('BASE_URL_PATH') && BASE_URL_PATH !== '') ? rtrim(BASE_URL_PATH, '/') : '';
        $base = $pathPrefix !== '' ? ($origin . '/' . $pathPrefix) : $origin;

        if (!headers_sent()) {
            header('Content-Type: application/manifest+json; charset=utf-8');
            header('Cache-Control: public, max-age=3600');
        }

        $data = [
            'name' => 'SellApp Scanner',
            'short_name' => 'Scanner',
            'description' => 'Barcode scanning for POS and inventory',
            'id' => $base . '/',
            'start_url' => $base . '/pwa-login',
            'scope' => $base . '/',
            'display' => 'standalone',
            'orientation' => 'portrait-primary',
            'background_color' => '#ffffff',
            'theme_color' => '#ffffff',
            'icons' => [
                [
                    'src' => $base . '/assets/images/favicon.svg',
                    'sizes' => 'any',
                    'type' => 'image/svg+xml',
                    'purpose' => 'any maskable',
                ],
            ],
        ];

        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function getAuthenticatedUser(): ?array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $authHeader = '';
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        } else {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        }

        if (!empty($authHeader) && strpos($authHeader, 'Bearer ') === 0) {
            $token = substr($authHeader, 7);
            try {
                $auth = new \App\Services\AuthService();
                $payload = $auth->validateToken($token);
                return [
                    'id' => $payload->sub,
                    'username' => $payload->username,
                    'role' => $payload->role,
                    'company_id' => $payload->company_id ?? null,
                ];
            } catch (\Exception $e) {
                return null;
            }
        }

        if (isset($_SESSION['user']) && !empty($_SESSION['user'])) {
            return $_SESSION['user'];
        }

        return null;
    }

    private function tableHasColumn(\PDO $db, string $table, string $column): bool
    {
        try {
            $q = $db->prepare('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '` LIKE ?');
            $q->execute([$column]);
            return $q->rowCount() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function jsonResponse(array $data, int $code = 200): void
    {
        while (ob_get_level()) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code($code);
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Resolve scanned code to product JSON for the user's company.
     */
    private function resolveProduct(string $code, ?int $companyId): ?array
    {
        if (!$companyId) {
            return null;
        }

        $code = trim($code);
        if ($code === '') {
            return null;
        }

        $row = $this->product->findBySku($code, $companyId);
        if (!$row) {
            $row = $this->product->findByProductId($code);
            if ($row && (int)($row['company_id'] ?? 0) !== (int)$companyId) {
                return null;
            }
        }

        if (!$row) {
            return null;
        }

        $full = $this->product->find((int)$row['id'], $companyId);
        return $full ?: null;
    }

    private function productPayload(array $p): array
    {
        return [
            'id' => (int)$p['id'],
            'product_id' => $p['product_id'] ?? null,
            'name' => $p['name'] ?? '',
            'sku' => $p['sku'] ?? null,
            'price' => isset($p['price']) ? (float)$p['price'] : 0.0,
            'quantity' => isset($p['quantity']) ? (int)$p['quantity'] : 0,
            'category_name' => $p['category_name'] ?? 'General',
            'brand_name' => $p['brand_name'] ?? null,
            'status' => $p['status'] ?? null,
        ];
    }

    /**
     * GET api/pwa/products/by-barcode | api/products/find-by-barcode
     * Query: code | sku | barcode
     */
    public function lookupByBarcode(): void
    {
        $user = $this->getAuthenticatedUser();
        if (!$user) {
            $this->jsonResponse(['success' => false, 'error' => 'Authentication required'], 401);
        }

        $code = $_GET['code'] ?? $_GET['sku'] ?? $_GET['barcode'] ?? '';
        $code = is_string($code) ? trim($code) : '';

        $companyId = $user['company_id'] ?? null;
        if (!$companyId) {
            $this->jsonResponse(['success' => false, 'error' => 'No company assigned to this account'], 403);
        }

        $product = $this->resolveProduct($code, (int)$companyId);
        if (!$product) {
            $this->jsonResponse(['success' => false, 'error' => 'Product not found'], 404);
        }

        $this->jsonResponse([
            'success' => true,
            'product' => $this->productPayload($product),
        ]);
    }

    /**
     * POST api/pwa/pos/scan-add | api/pos/scan-add
     * JSON: { "barcode"|"sku"|"code": "..." }
     */
    public function scanAdd(): void
    {
        $user = $this->getAuthenticatedUser();
        if (!$user) {
            $this->jsonResponse(['success' => false, 'error' => 'Authentication required'], 401);
        }

        $role = $user['role'] ?? '';
        if (!in_array($role, ['salesperson', 'manager', 'admin', 'system_admin'], true)) {
            $this->jsonResponse(['success' => false, 'error' => 'PWA scanner is not enabled for this role'], 403);
        }

        $companyId = $user['company_id'] ?? null;
        if (!$companyId) {
            $this->jsonResponse(['success' => false, 'error' => 'No company assigned to this account'], 403);
        }

        $input = json_decode((string)file_get_contents('php://input'), true) ?: [];
        $code = $input['barcode'] ?? $input['sku'] ?? $input['code'] ?? $_POST['barcode'] ?? $_POST['sku'] ?? '';
        $code = is_string($code) ? trim($code) : '';

        if ($code === '') {
            $this->jsonResponse(['success' => false, 'error' => 'Barcode or SKU required'], 400);
        }

        $product = $this->resolveProduct($code, (int)$companyId);
        if (!$product) {
            $this->jsonResponse(['success' => false, 'error' => 'Product not found'], 404);
        }

        $this->jsonResponse([
            'success' => true,
            'message' => 'Product resolved',
            'product' => $this->productPayload($product),
        ]);
    }

    /**
     * POST api/pwa/inventory/add | api/inventory/add
     * JSON: { "product_id": n, "quantity_to_add": n, "notes": "optional" }
     */
    public function addInventoryStock(): void
    {
        $user = $this->getAuthenticatedUser();
        if (!$user) {
            $this->jsonResponse(['success' => false, 'error' => 'Authentication required'], 401);
        }

        $role = $user['role'] ?? '';
        if (!in_array($role, ['manager', 'admin', 'system_admin'], true)) {
            $this->jsonResponse(['success' => false, 'error' => 'Only managers can add stock'], 403);
        }

        $companyId = $user['company_id'] ?? null;
        if (!$companyId) {
            $this->jsonResponse(['success' => false, 'error' => 'No company assigned to this account'], 403);
        }

        $input = json_decode((string)file_get_contents('php://input'), true) ?: [];
        $productId = (int)($input['product_id'] ?? $_POST['product_id'] ?? 0);
        $quantityToAdd = (int)($input['quantity_to_add'] ?? $input['quantity'] ?? $_POST['quantity_to_add'] ?? 0);
        $notes = trim((string)($input['notes'] ?? ''));

        if ($productId <= 0 || $quantityToAdd <= 0) {
            $this->jsonResponse(['success' => false, 'error' => 'Valid product_id and quantity_to_add are required'], 400);
        }

        $product = $this->product->findInNew($productId, (int)$companyId);
        if (!$product || (int)$product['company_id'] !== (int)$companyId) {
            $this->jsonResponse(['success' => false, 'error' => 'Product not found'], 404);
        }

        require_once __DIR__ . '/../../config/database.php';
        $db = \Database::getInstance()->getConnection();

        $qtyCol = $this->tableHasColumn($db, 'products', 'qty') ? 'qty' : 'quantity';
        $costCol = null;
        if ($this->tableHasColumn($db, 'products', 'cost')) {
            $costCol = 'cost';
        } elseif ($this->tableHasColumn($db, 'products', 'cost_price')) {
            $costCol = 'cost_price';
        }

        $currentQty = (int)($product['quantity'] ?? $product[$qtyCol] ?? 0);
        $newQuantity = $currentQty + $quantityToAdd;
        $newCost = (float)($product['cost'] ?? $product['cost_price'] ?? 0);
        $newPrice = (float)($product['price'] ?? 0);

        if ($costCol) {
            $stmt = $db->prepare("
                UPDATE products
                SET {$qtyCol} = ?,
                    {$costCol} = ?,
                    price = ?,
                    updated_at = ?,
                    status = CASE
                        WHEN ? <= 0 THEN 'out_of_stock'
                        ELSE 'available'
                    END
                WHERE id = ? AND company_id = ?
            ");
            $ok = $stmt->execute([
                $newQuantity,
                $newCost,
                $newPrice,
                date('Y-m-d H:i:s'),
                $newQuantity,
                $productId,
                $companyId,
            ]);
        } else {
            $stmt = $db->prepare("
                UPDATE products
                SET {$qtyCol} = ?,
                    price = ?,
                    updated_at = ?,
                    status = CASE
                        WHEN ? <= 0 THEN 'out_of_stock'
                        ELSE 'available'
                    END
                WHERE id = ? AND company_id = ?
            ");
            $ok = $stmt->execute([
                $newQuantity,
                $newPrice,
                date('Y-m-d H:i:s'),
                $newQuantity,
                $productId,
                $companyId,
            ]);
        }

        if (!$ok) {
            $this->jsonResponse(['success' => false, 'error' => 'Failed to update stock'], 500);
        }

        $this->logRestockSimple($db, $productId, (int)$companyId, $quantityToAdd, $currentQty, $newQuantity, $newCost, $newPrice, $notes, (int)($user['id'] ?? 0));

        $updated = $this->product->find($productId, (int)$companyId);

        $this->jsonResponse([
            'success' => true,
            'message' => 'Stock updated',
            'product' => $updated ? $this->productPayload($updated) : null,
        ]);
    }

    private function logRestockSimple($db, int $productId, int $companyId, int $quantityAdded, int $qtyBefore, int $qtyAfter, float $newCost, float $newPrice, string $notes, int $userId): void
    {
        try {
            $checkColumns = $db->query("SHOW COLUMNS FROM restock_logs LIKE 'quantity_at_restock'");
            $hasLifecycle = $checkColumns && $checkColumns->rowCount() > 0;

            if ($hasLifecycle) {
                $stmt = $db->prepare("
                    INSERT INTO restock_logs (
                        product_id, company_id, quantity_added, new_cost, new_price, notes,
                        quantity_at_restock, quantity_after_restock, user_id, status, created_at
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
                ");
                $stmt->execute([
                    $productId,
                    $companyId,
                    $quantityAdded,
                    $newCost,
                    $newPrice,
                    $notes,
                    $qtyBefore,
                    $qtyAfter,
                    $userId ?: null,
                ]);
            } else {
                $stmt = $db->prepare("
                    INSERT INTO restock_logs (product_id, company_id, quantity_added, new_cost, new_price, notes, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $productId,
                    $companyId,
                    $quantityAdded,
                    $newCost,
                    $newPrice,
                    $notes,
                ]);
            }
        } catch (\Exception $e) {
            error_log('PwaScannerController restock log: ' . $e->getMessage());
        }
    }
}
