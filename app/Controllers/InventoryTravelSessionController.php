<?php

namespace App\Controllers;

use App\Middleware\WebAuthMiddleware;
use App\Services\InventoryTravelSessionService;

class InventoryTravelSessionController {

    private function sessionUser(): ?array {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return $_SESSION['user'] ?? null;
    }

    private function requireManagerJson(): array {
        header('Content-Type: application/json');
        $u = $this->sessionUser();
        if (!$u) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Authentication required']);
            exit;
        }
        $role = strtolower(trim($u['role'] ?? ''));
        if (!in_array($role, ['manager', 'admin', 'system_admin'], true)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Managers only']);
            exit;
        }
        $cid = (int)($u['company_id'] ?? 0);
        if ($cid <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Company association required']);
            exit;
        }
        return $u;
    }

    public function index(): void {
        \App\Helpers\AdminBlockHelper::blockAdmin(
            ['manager', 'admin', 'system_admin'],
            'Travel inventory sessions are available to company managers.',
            BASE_URL_PATH . '/dashboard'
        );
        WebAuthMiddleware::handle(['manager', 'admin', 'system_admin']);

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $GLOBALS['currentPage'] = 'travel-sessions';
        $GLOBALS['title'] = 'Travel inventory sessions';
        ob_start();
        include __DIR__ . '/../Views/inventory_travel_sessions.php';
        $content = ob_get_clean();
        $GLOBALS['content'] = $content;
        if (isset($_SESSION['user'])) {
            $GLOBALS['user_data'] = $_SESSION['user'];
        }
        require __DIR__ . '/../Views/simple_layout.php';
    }

    public function apiList(): void {
        $u = $this->requireManagerJson();
        $companyId = (int)($u['company_id'] ?? 0);
        try {
            $svc = new InventoryTravelSessionService();
            $sessions = $svc->listSessions($companyId, 80);
            echo json_encode(['success' => true, 'data' => $sessions]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function apiActive(): void {
        $u = $this->requireManagerJson();
        $companyId = (int)($u['company_id'] ?? 0);
        try {
            $svc = new InventoryTravelSessionService();
            $open = $svc->getOpenSession($companyId);
            echo json_encode(['success' => true, 'data' => $open]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function apiStart(): void {
        $u = $this->requireManagerJson();
        $companyId = (int)($u['company_id'] ?? 0);
        $managerId = (int)($u['id'] ?? 0);
        try {
            $svc = new InventoryTravelSessionService();
            $row = $svc->startSession($companyId, $managerId);
            echo json_encode(['success' => true, 'data' => $row]);
        } catch (\RuntimeException $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function apiEnd(int $sessionId): void {
        $u = $this->requireManagerJson();
        $companyId = (int)($u['company_id'] ?? 0);
        $managerId = (int)($u['id'] ?? 0);
        $raw = file_get_contents('php://input') ?: '';
        $body = json_decode($raw, true);
        if (!is_array($body)) {
            $body = $_POST;
        }
        $mode = strtolower(trim((string)($body['staff_mode'] ?? '')));
        $staffUserId = isset($body['staff_user_id']) ? (int)$body['staff_user_id'] : null;
        if ($mode === '') {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'staff_mode required (single or all)']);
            return;
        }
        try {
            $svc = new InventoryTravelSessionService();
            $row = $svc->endSession($sessionId, $companyId, $managerId, $mode, $staffUserId ?: null);
            echo json_encode(['success' => true, 'data' => $row]);
        } catch (\RuntimeException $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function apiReport(int $sessionId): void {
        $u = $this->requireManagerJson();
        $companyId = (int)($u['company_id'] ?? 0);
        try {
            $svc = new InventoryTravelSessionService();
            $report = $svc->buildReport($sessionId, $companyId);
            echo json_encode(['success' => true, 'data' => $report]);
        } catch (\RuntimeException $e) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}
