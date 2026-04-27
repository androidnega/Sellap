<?php

namespace App\Controllers;

use App\Middleware\WebAuthMiddleware;
use App\Models\CompanyFeature;
use App\Models\InventoryReturn;
use App\Models\InventoryReturnItem;
use App\Services\ReturnVisibilityPolicy;

/**
 * Company returns dashboard for managers and sales (not company admin — they use inventory logs).
 */
class InventoryReturnsController {
    public function dashboard(): void {
        WebAuthMiddleware::handle(['manager', 'salesperson', 'sales']);
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $companyId = (int)($_SESSION['user']['company_id'] ?? 0);
        if ($companyId <= 0) {
            header('Location: ' . BASE_URL_PATH . '/dashboard');
            exit;
        }
        if (!CompanyFeature::tableExists() || !(new CompanyFeature())->isEnabled($companyId, 'returns_enabled')) {
            $_SESSION['error_message'] = 'Returns are not enabled for your company. Ask a company administrator to enable them under Company features.';
            header('Location: ' . BASE_URL_PATH . '/dashboard/pos/sales-history');
            exit;
        }

        $role = (string)($_SESSION['user']['role'] ?? '');
        $userId = (int)($_SESSION['user']['id'] ?? 0);
        $model = new InventoryReturn();
        $rows = $model->listForViewer($companyId, 150, $role, $userId);
        $returnStats = $model->statsForCompany($companyId, $role, $userId);
        $returnsTableOk = InventoryReturn::tableExists();
        if (!$returnsTableOk) {
            $returnStats = ['total' => 0, 'last_30_days' => 0];
        }

        $detail = null;
        $detailItems = [];
        if (isset($_GET['id']) && (int)$_GET['id'] > 0) {
            $rid = (int)$_GET['id'];
            $detail = $model->findForViewer($rid, $companyId, $role, $userId);
            if ($detail) {
                $detailItems = (new InventoryReturnItem())->listByReturn($rid);
            }
        }

        $rn = ReturnVisibilityPolicy::normalizeRole($role);
        $pageHeading = 'Returns';
        $readOnlyUi = ReturnVisibilityPolicy::returnsUiReadOnly($rn);
        $canProcessReturns = ReturnVisibilityPolicy::canProcessReturns($rn);

        $GLOBALS['title'] = $pageHeading;
        $title = $pageHeading;
        $GLOBALS['currentPage'] = 'returns';
        ob_start();
        include __DIR__ . '/../Views/inventory_returns_dashboard.php';
        $content = ob_get_clean();
        $GLOBALS['content'] = $content;
        if (isset($_SESSION['user'])) {
            $GLOBALS['user_data'] = $_SESSION['user'];
        }
        require __DIR__ . '/../Views/simple_layout.php';
    }
}
