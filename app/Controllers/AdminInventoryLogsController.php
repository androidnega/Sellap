<?php

namespace App\Controllers;

use App\Middleware\WebAuthMiddleware;
use App\Models\CompanyFeature;
use App\Models\InventoryReturn;
use App\Models\InventoryReturnItem;
use App\Models\StockMovement;

class AdminInventoryLogsController {
    private function startSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    private function renderLayout(string $title, string $pageKey, string $innerView, array $vars = []): void {
        extract($vars, EXTR_SKIP);
        $GLOBALS['content'] = null;
        $GLOBALS['title'] = $title;
        $GLOBALS['currentPage'] = $pageKey;
        ob_start();
        include __DIR__ . '/../Views/' . $innerView;
        $GLOBALS['content'] = ob_get_clean();
        $this->startSession();
        if (isset($_SESSION['user'])) {
            $GLOBALS['user_data'] = $_SESSION['user'];
        }
        require __DIR__ . '/../Views/simple_layout.php';
    }

    public function hub(): void {
        WebAuthMiddleware::handle(['admin', 'system_admin']);
        $this->startSession();
        $role = $_SESSION['user']['role'] ?? '';
        $companyId = (int)($_SESSION['user']['company_id'] ?? 0);
        $returnsOn = CompanyFeature::tableExists() && $companyId > 0
            ? (new CompanyFeature())->isEnabled($companyId, 'returns_enabled')
            : false;
        $this->renderLayout('Inventory & order logs', 'admin-inventory-logs', 'admin_inventory_logs_hub.php', [
            'viewerRole' => $role,
            'returnsOn' => $returnsOn,
            'companyId' => $companyId,
        ]);
    }

    public function cancellations(): void {
        WebAuthMiddleware::handle(['admin', 'system_admin']);
        $this->startSession();
        $role = $_SESSION['user']['role'] ?? '';
        $sessionCompany = (int)($_SESSION['user']['company_id'] ?? 0);
        $db = \Database::getInstance()->getConnection();

        $filterCompany = null;
        if ($role === 'system_admin' && isset($_GET['company_id']) && $_GET['company_id'] !== '') {
            $filterCompany = (int)$_GET['company_id'];
        } elseif ($role !== 'system_admin') {
            $filterCompany = $sessionCompany > 0 ? $sessionCompany : null;
        }

        $rows = [];
        $companies = [];
        try {
            $check = $db->query("SHOW COLUMNS FROM pos_sales LIKE 'status'");
            if ($check && $check->rowCount() > 0) {
                $sql = "SELECT ps.*, c.name AS company_name, u.username AS cancelled_by_username
                        FROM pos_sales ps
                        LEFT JOIN companies c ON ps.company_id = c.id
                        LEFT JOIN users u ON ps.cancelled_by = u.id
                        WHERE ps.status = 'cancelled'";
                $params = [];
                if ($filterCompany !== null) {
                    $sql .= ' AND ps.company_id = ?';
                    $params[] = $filterCompany;
                }
                $sql .= ' ORDER BY ps.cancelled_at DESC, ps.id DESC LIMIT 250';
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            }
        } catch (\Throwable $e) {
            error_log('AdminInventoryLogsController::cancellations ' . $e->getMessage());
        }
        if ($role === 'system_admin') {
            try {
                $companies = $db->query('SELECT id, name FROM companies ORDER BY name ASC')->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            } catch (\Throwable $e) {
                $companies = [];
            }
        }

        $this->renderLayout('Cancellations log', 'admin-inventory-logs', 'admin_inventory_cancellations.php', [
            'rows' => $rows,
            'companies' => $companies,
            'viewerRole' => $role,
            'filterCompany' => $filterCompany,
        ]);
    }

    public function returnsList(): void {
        WebAuthMiddleware::handle(['admin', 'system_admin']);
        $this->startSession();
        $role = $_SESSION['user']['role'] ?? '';
        $sessionCompany = (int)($_SESSION['user']['company_id'] ?? 0);
        $filterCompany = null;
        if ($role === 'system_admin' && isset($_GET['company_id']) && $_GET['company_id'] !== '') {
            $filterCompany = (int)$_GET['company_id'];
        } elseif ($role !== 'system_admin') {
            $filterCompany = $sessionCompany > 0 ? $sessionCompany : null;
        }

        $model = new InventoryReturn();
        $rows = $model->listByCompany($filterCompany, 150);
        $detail = null;
        $detailItems = [];
        if (isset($_GET['id']) && (int)$_GET['id'] > 0) {
            $rid = (int)$_GET['id'];
            $scopeCompany = $role === 'system_admin' ? null : $sessionCompany;
            $detail = $model->findById($rid, $scopeCompany);
            if ($detail) {
                $detailItems = (new InventoryReturnItem())->listByReturn($rid);
            }
        }

        $companies = [];
        if ($role === 'system_admin') {
            try {
                $db = \Database::getInstance()->getConnection();
                $companies = $db->query('SELECT id, name FROM companies ORDER BY name ASC')->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            } catch (\Throwable $e) {
                $companies = [];
            }
        }

        $this->renderLayout('Returns log', 'admin-inventory-logs', 'admin_inventory_returns.php', [
            'rows' => $rows,
            'companies' => $companies,
            'viewerRole' => $role,
            'filterCompany' => $filterCompany,
            'detail' => $detail,
            'detailItems' => $detailItems,
        ]);
    }

    public function stockHistory(): void {
        WebAuthMiddleware::handle(['admin', 'system_admin']);
        $this->startSession();
        $role = $_SESSION['user']['role'] ?? '';
        $sessionCompany = (int)($_SESSION['user']['company_id'] ?? 0);
        $filterCompany = null;
        if ($role === 'system_admin' && isset($_GET['company_id']) && $_GET['company_id'] !== '') {
            $filterCompany = (int)$_GET['company_id'];
        } elseif ($role !== 'system_admin') {
            $filterCompany = $sessionCompany > 0 ? $sessionCompany : null;
        }
        $type = isset($_GET['type']) && $_GET['type'] !== '' ? (string)$_GET['type'] : null;
        if ($type && !in_array($type, ['sale', 'cancel', 'return'], true)) {
            $type = null;
        }

        $rows = (new StockMovement())->listByCompany($filterCompany, 250, $type);

        $companies = [];
        if ($role === 'system_admin') {
            try {
                $db = \Database::getInstance()->getConnection();
                $companies = $db->query('SELECT id, name FROM companies ORDER BY name ASC')->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            } catch (\Throwable $e) {
                $companies = [];
            }
        }

        $this->renderLayout('Stock movement history', 'admin-inventory-logs', 'admin_inventory_stock_history.php', [
            'rows' => $rows,
            'companies' => $companies,
            'viewerRole' => $role,
            'filterCompany' => $filterCompany,
            'typeFilter' => $type,
        ]);
    }

    public function companyFeatures(): void {
        WebAuthMiddleware::handle(['admin', 'system_admin']);
        $this->startSession();
        $role = $_SESSION['user']['role'] ?? '';
        $sessionCompany = (int)($_SESSION['user']['company_id'] ?? 0);
        $db = \Database::getInstance()->getConnection();

        $targetCompany = $sessionCompany;
        if ($role === 'system_admin' && isset($_GET['company_id']) && (int)$_GET['company_id'] > 0) {
            $targetCompany = (int)$_GET['company_id'];
        }

        $companies = [];
        if ($role === 'system_admin') {
            $companies = $db->query('SELECT id, name FROM companies ORDER BY name ASC')->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        }

        $returnsEnabled = false;
        if (CompanyFeature::tableExists() && $targetCompany > 0) {
            $returnsEnabled = (new CompanyFeature())->isEnabled($targetCompany, 'returns_enabled');
        }

        $flash = $_SESSION['flash_inventory_features'] ?? null;
        unset($_SESSION['flash_inventory_features']);

        $this->renderLayout('Company features', 'admin-inventory-logs', 'admin_company_features.php', [
            'viewerRole' => $role,
            'targetCompany' => $targetCompany,
            'companies' => $companies,
            'returnsEnabled' => $returnsEnabled,
            'flash' => $flash,
            'canEditFeatures' => ($role === 'admin'),
        ]);
    }

    public function companyFeaturesSave(): void {
        WebAuthMiddleware::handle(['admin', 'system_admin']);
        $this->startSession();
        $role = $_SESSION['user']['role'] ?? '';
        $sessionCompany = (int)($_SESSION['user']['company_id'] ?? 0);
        $userId = (int)($_SESSION['user']['id'] ?? 0);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL_PATH . '/dashboard/admin/company-features');
            exit;
        }

        if ($role !== 'admin') {
            $_SESSION['flash_inventory_features'] = [
                'type' => 'error',
                'text' => 'Only the company administrator can change company features.',
            ];
            $redir = BASE_URL_PATH . '/dashboard/admin/company-features';
            if ($role === 'system_admin' && isset($_POST['company_id']) && (int)$_POST['company_id'] > 0) {
                $redir .= '?company_id=' . (int)$_POST['company_id'];
            }
            header('Location: ' . $redir);
            exit;
        }

        $targetCompany = $sessionCompany;
        if ($role === 'system_admin' && isset($_POST['company_id']) && (int)$_POST['company_id'] > 0) {
            $targetCompany = (int)$_POST['company_id'];
        }

        if ($targetCompany <= 0 || !CompanyFeature::tableExists()) {
            $_SESSION['flash_inventory_features'] = ['type' => 'error', 'text' => 'Invalid company or features table missing.'];
            header('Location: ' . BASE_URL_PATH . '/dashboard/admin/company-features');
            exit;
        }

        $enabled = isset($_POST['returns_enabled']) && (string)$_POST['returns_enabled'] === '1';
        try {
            (new CompanyFeature())->setEnabled($targetCompany, 'returns_enabled', $enabled, $userId > 0 ? $userId : null);
            $_SESSION['flash_inventory_features'] = ['type' => 'success', 'text' => 'Features updated.'];
        } catch (\Throwable $e) {
            $_SESSION['flash_inventory_features'] = ['type' => 'error', 'text' => 'Could not save features.'];
        }
        $redir = BASE_URL_PATH . '/dashboard/admin/company-features';
        if ($role === 'system_admin') {
            $redir .= '?company_id=' . $targetCompany;
        }
        header('Location: ' . $redir);
        exit;
    }
}
