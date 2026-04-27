<?php

namespace App\Controllers;

use App\Services\HolidaySmsDispatcher;

/**
 * HTTP entry for daily holiday SMS dispatch (same logic as CLI cron).
 * Set env HOLIDAY_SMS_CRON_TOKEN and call GET .../api/cron/holiday-sms?token=... once per day.
 */
class HolidaySmsCronController
{
    public function run(): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/json; charset=utf-8');
        $secret = getenv('HOLIDAY_SMS_CRON_TOKEN') ?: getenv('SELLAP_CRON_TOKEN') ?: 'sellapp_holiday_sms_cron_2024';
        $provided = (string)($_GET['token'] ?? $_SERVER['HTTP_X_SELLAP_CRON_TOKEN'] ?? '');
        if ($provided === '' || $provided !== $secret) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error' => 'Invalid or missing token',
                'hint' => 'Use ?token=... or header X-SELLAP-CRON-TOKEN. Set HOLIDAY_SMS_CRON_TOKEN in the server environment.',
            ], JSON_UNESCAPED_SLASHES);
            return;
        }
        try {
            $out = HolidaySmsDispatcher::runScheduledForToday();
            echo json_encode([
                'success' => true,
                'result' => $out,
            ], JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}
