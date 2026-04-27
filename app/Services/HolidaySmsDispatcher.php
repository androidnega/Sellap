<?php

namespace App\Services;

use App\Models\CompanyHolidayMessage;
use App\Models\Customer;
use App\Models\CompanySMSAccount;

/**
 * Scheduled holiday SMS: each send debits the company's SMS account (not system/admin).
 */
class HolidaySmsDispatcher {
    public static function runsTableExists(\PDO $db): bool {
        $r = $db->query("SHOW TABLES LIKE 'holiday_sms_runs'");
        return $r && $r->rowCount() > 0;
    }

    /**
     * @return array{processed: int, sent: int, errors: list<string>}
     */
    public static function runScheduledForToday(): array {
        $out = ['processed' => 0, 'sent' => 0, 'errors' => []];
        if (!CompanyHolidayMessage::tableExists()) {
            $out['errors'][] = 'company_holiday_messages table missing; run database migration.';
            return $out;
        }

        $db = \Database::getInstance()->getConnection();
        if (!self::runsTableExists($db)) {
            $out['errors'][] = 'holiday_sms_runs table missing; run database migration.';
            return $out;
        }

        $model = new CompanyHolidayMessage();
        $holidays = $model->listMatchingTodayForDispatch();
        if (!$holidays) {
            return $out;
        }

        $sms = new SMSService();
        $q = $db->query("SELECT setting_key, setting_value FROM system_settings");
        if ($q) {
            $pairs = $q->fetchAll(\PDO::FETCH_KEY_PAIR);
            if (is_array($pairs)) {
                $sms->loadFromSettings($pairs);
            }
        }

        $runDate = (new \DateTimeImmutable('now'))->format('Y-m-d');
        $customerModel = new Customer();
        $acc = new CompanySMSAccount();

        foreach ($holidays as $row) {
            $out['processed']++;
            $companyId = (int)$row['company_id'];
            $holidayId = (int)$row['id'];
            if ($companyId < 1 || $holidayId < 1) {
                continue;
            }

            $message = trim((string)($row['message_body'] ?? ''));
            if ($message === '') {
                $out['errors'][] = "Holiday id {$holidayId}: empty message, skipped";
                continue;
            }

            try {
                $ins = $db->prepare(
                    "INSERT INTO holiday_sms_runs (company_id, holiday_id, run_date, customers_sent, status, error_note)
                     VALUES (?, ?, ?, 0, 'running', NULL)"
                );
                $ins->execute([$companyId, $holidayId, $runDate]);
            } catch (\Exception $e) {
                if (self::isDuplicateKeyError($e)) {
                    continue;
                }
                $out['errors'][] = "Holiday {$holidayId} run lock: " . $e->getMessage();
                continue;
            }

            $customers = $customerModel->findByCompany($companyId, 50000);
            $withPhone = array_values(array_filter($customers, static function ($c) {
                return trim((string)($c['phone_number'] ?? '')) !== '';
            }));
            $n = count($withPhone);

            if ($n === 0) {
                self::finishRun($db, $holidayId, $runDate, 0, 'completed', 'No customers with phone numbers');
                continue;
            }

            if (!$acc->hasEnoughCredits($companyId, $n)) {
                self::finishRun($db, $holidayId, $runDate, 0, 'failed', 'Insufficient SMS credits for ' . $n . ' recipients');
                $out['errors'][] = "Company {$companyId} holiday {$holidayId}: insufficient credits for {$n} messages";
                continue;
            }

            $sent = 0;
            foreach ($withPhone as $c) {
                $r = $sms->sendRealSMS(
                    (string)$c['phone_number'],
                    $message,
                    $companyId,
                    'custom'
                );
                if (!empty($r['success'])) {
                    $sent++;
                }
            }

            self::finishRun($db, $holidayId, $runDate, $sent, 'completed', null);
            $out['sent'] += $sent;
        }

        return $out;
    }

    private static function finishRun(\PDO $db, int $holidayId, string $runDate, int $sent, string $status, ?string $errorNote): void {
        $st = $db->prepare(
            "UPDATE holiday_sms_runs SET customers_sent = ?, status = ?, error_note = ? WHERE holiday_id = ? AND run_date = ?"
        );
        $st->execute([$sent, $status, $errorNote, $holidayId, $runDate]);
    }

    private static function isDuplicateKeyError(\Exception $e): bool {
        if ((int)$e->getCode() === 23000) {
            return true;
        }
        $m = $e->getMessage();
        return $m !== '' && (stripos($m, 'Duplicate') !== false || str_contains($m, '1062'));
    }
}
