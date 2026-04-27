<?php

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Middleware\WebAuthMiddleware;
use App\Models\CompanyHolidayMessage;
use App\Models\Customer;
use App\Services\SMSService;
use App\Models\CompanySMSAccount;

/**
 * Manager: holiday SMS schedule + manual broadcast (company SMS balance only).
 */
class ManagerSmsBroadcastController {

    public function page(): void {
        WebAuthMiddleware::handle(['manager', 'admin']);
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $companyId = (int)($_SESSION['user']['company_id'] ?? 0);
        if ($companyId <= 0) {
            header('Location: ' . BASE_URL_PATH . '/dashboard');
            exit;
        }
        $GLOBALS['title'] = 'Holiday & broadcast SMS';
        $GLOBALS['currentPage'] = 'sms-broadcast';
        $migrationNeeded = !CompanyHolidayMessage::tableExists();
        ob_start();
        include __DIR__ . '/../Views/manager_sms_broadcast.php';
        $content = ob_get_clean();
        $GLOBALS['content'] = $content;
        if (isset($_SESSION['user'])) {
            $GLOBALS['user_data'] = $_SESSION['user'];
        }
        require __DIR__ . '/../Views/simple_layout.php';
    }

    private function json(array $data, int $code = 200): void {
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code($code);
        }
        echo json_encode($data);
    }

    private function companyContext(): ?array {
        $p = AuthMiddleware::handle(['manager', 'admin']);
        $cid = (int)($p->company_id ?? 0);
        if ($cid < 1) {
            $this->json(['success' => false, 'error' => 'Company not found in session'], 400);
            return null;
        }
        return ['company_id' => $cid, 'user_id' => (int)($p->sub ?? 0)];
    }

    public function listHolidays(): void {
        $ctx = $this->companyContext();
        if (!$ctx) {
            return;
        }
        $companyId = (int)$ctx['company_id'];
        if (!CompanyHolidayMessage::tableExists()) {
            $this->json(['success' => true, 'holidays' => [], 'migration_needed' => true]);
            return;
        }
        $list = (new CompanyHolidayMessage())->listByCompany($companyId);
        $this->json(['success' => true, 'holidays' => $list, 'migration_needed' => false]);
    }

    public function saveHoliday(): void {
        $ctx = $this->companyContext();
        if (!$ctx) {
            return;
        }
        if (!CompanyHolidayMessage::tableExists()) {
            $this->json(['success' => false, 'error' => 'Run database migration for company_holiday_messages.'], 400);
            return;
        }
        $in = json_decode((string)file_get_contents('php://input'), true) ?: [];
        $label = trim((string)($in['label'] ?? ''));
        $eventDate = trim((string)($in['event_date'] ?? ''));
        $messageBody = trim((string)($in['message_body'] ?? ''));
        $isAnnual = !empty($in['is_annual']);
        $isActive = array_key_exists('is_active', $in) ? (bool)$in['is_active'] : true;

        if ($label === '' || $eventDate === '' || $messageBody === '') {
            $this->json(['success' => false, 'error' => 'label, event_date, and message_body are required'], 400);
            return;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate)) {
            $this->json(['success' => false, 'error' => 'event_date must be YYYY-MM-DD'], 400);
            return;
        }

        $m = new CompanyHolidayMessage();
        $id = !empty($in['id']) ? (int)$in['id'] : 0;
        if ($id > 0) {
            $m->update($id, (int)$ctx['company_id'], [
                'label' => $label,
                'event_date' => $eventDate,
                'is_annual' => $isAnnual,
                'message_body' => $messageBody,
                'is_active' => $isActive,
            ]);
            $this->json(['success' => true, 'id' => $id]);
            return;
        }
        $newId = $m->create((int)$ctx['company_id'], [
            'label' => $label,
            'event_date' => $eventDate,
            'is_annual' => $isAnnual,
            'message_body' => $messageBody,
            'is_active' => $isActive,
        ], (int)$ctx['user_id']);
        $this->json(['success' => true, 'id' => $newId]);
    }

    public function deleteHoliday(int $id): void {
        $ctx = $this->companyContext();
        if (!$ctx) {
            return;
        }
        if (!CompanyHolidayMessage::tableExists()) {
            $this->json(['success' => false, 'error' => 'Table missing'], 400);
            return;
        }
        if ($id < 1) {
            $this->json(['success' => false, 'error' => 'Invalid id'], 400);
            return;
        }
        $ok = (new CompanyHolidayMessage())->delete($id, (int)$ctx['company_id']);
        $this->json(['success' => $ok]);
    }

    public function listCustomers(): void {
        $ctx = $this->companyContext();
        if (!$ctx) {
            return;
        }
        $rows = (new Customer())->findByCompany((int)$ctx['company_id'], 20000);
        $out = [];
        foreach ($rows as $c) {
            $out[] = [
                'id' => (int)$c['id'],
                'full_name' => $c['full_name'] ?? '',
                'phone_number' => $c['phone_number'] ?? '',
            ];
        }
        $this->json(['success' => true, 'customers' => $out]);
    }

    public function broadcast(): void {
        $ctx = $this->companyContext();
        if (!$ctx) {
            return;
        }
        $in = json_decode((string)file_get_contents('php://input'), true) ?: [];
        $message = trim((string)($in['message'] ?? ''));
        if ($message === '') {
            $this->json(['success' => false, 'error' => 'message is required'], 400);
            return;
        }
        $scope = (string)($in['scope'] ?? 'all');
        $ids = $in['customer_ids'] ?? [];
        if (!is_array($ids)) {
            $ids = [];
        }
        $ids = array_values(array_filter(array_map('intval', $ids), static function ($n) { return $n > 0; }));
        $companyId = (int)$ctx['company_id'];

        $rows = (new Customer())->findByCompany($companyId, 20000);
        if ($scope === 'selected' && $ids) {
            $idSet = array_fill_keys($ids, true);
            $rows = array_values(array_filter($rows, static function ($c) use ($idSet) {
                return isset($idSet[(int)$c['id']]);
            }));
        }
        $recipients = [];
        foreach ($rows as $c) {
            $p = trim((string)($c['phone_number'] ?? ''));
            if ($p === '') {
                continue;
            }
            $recipients[] = $c;
        }
        if (!$recipients) {
            $this->json(['success' => false, 'error' => 'No customers with a phone number match this selection.'], 400);
            return;
        }

        $n = count($recipients);
        $acc = new CompanySMSAccount();
        if (!$acc->hasEnoughCredits($companyId, $n)) {
            $b = $acc->getSMSBalance($companyId);
            $this->json([
                'success' => false,
                'error' => 'Insufficient SMS credits. Need ' . $n . ', have ' . (int)($b['sms_remaining'] ?? 0),
            ], 400);
            return;
        }

        $sms = $this->makeSms();
        $sent = 0;
        $errors = 0;
        foreach ($recipients as $c) {
            $r = $sms->sendRealSMS((string)$c['phone_number'], $message, $companyId, 'custom');
            if (!empty($r['success'])) {
                $sent++;
            } else {
                $errors++;
            }
        }
        $this->json(['success' => true, 'sent' => $sent, 'failed' => $errors, 'total' => $n]);
    }

    public function sendHolidayNow(int $id): void {
        $ctx = $this->companyContext();
        if (!$ctx) {
            return;
        }
        if (!CompanyHolidayMessage::tableExists() || $id < 1) {
            $this->json(['success' => false, 'error' => 'Invalid request'], 400);
            return;
        }
        $companyId = (int)$ctx['company_id'];
        $row = (new CompanyHolidayMessage())->findForCompany($id, $companyId);
        if (!$row) {
            $this->json(['success' => false, 'error' => 'Holiday not found'], 404);
            return;
        }
        $message = trim((string)($row['message_body'] ?? ''));
        if ($message === '') {
            $this->json(['success' => false, 'error' => 'Empty message'], 400);
            return;
        }
        $customers = (new Customer())->findByCompany($companyId, 20000);
        $recipients = array_values(array_filter($customers, static function ($c) {
            return trim((string)($c['phone_number'] ?? '')) !== '';
        }));
        $n = count($recipients);
        if ($n === 0) {
            $this->json(['success' => false, 'error' => 'No customers with phone numbers'], 400);
            return;
        }
        $acc = new CompanySMSAccount();
        if (!$acc->hasEnoughCredits($companyId, $n)) {
            $b = $acc->getSMSBalance($companyId);
            $this->json([
                'success' => false,
                'error' => 'Insufficient credits: need ' . $n . ', have ' . (int)($b['sms_remaining'] ?? 0),
            ], 400);
            return;
        }
        $sms = $this->makeSms();
        $sent = 0;
        $failed = 0;
        foreach ($recipients as $c) {
            $r = $sms->sendRealSMS((string)$c['phone_number'], $message, $companyId, 'custom');
            if (!empty($r['success'])) {
                $sent++;
            } else {
                $failed++;
            }
        }
        $this->json(['success' => true, 'sent' => $sent, 'failed' => $failed, 'total' => $n]);
    }

    private function makeSms(): SMSService {
        $db = \Database::getInstance()->getConnection();
        $sms = new SMSService();
        $q = $db->query("SELECT setting_key, setting_value FROM system_settings");
        if ($q) {
            $pairs = $q->fetchAll(\PDO::FETCH_KEY_PAIR);
            if (is_array($pairs)) {
                $sms->loadFromSettings($pairs);
            }
        }
        return $sms;
    }
}
