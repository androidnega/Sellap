<?php

namespace App\Services;

use App\Models\CompanyHolidayMessage;
use PDO;

/**
 * Inserts default Ghana (fixed-date) public holiday SMS templates per company.
 * Movable feasts (Eid, Good Friday, etc.) are not included; managers can add those manually.
 */
class GhanaPublicHolidaySeed
{
    /**
     * @return list<array{label: string, month: int, day: int, message: string}>
     */
    public static function templates(): array
    {
        return [
            [
                'label' => "New Year's Day",
                'month' => 1,
                'day' => 1,
                'message' => "Happy New Year! Thank you for your support. Wishing you health and success in the year ahead from all of us.",
            ],
            [
                'label' => 'Constitution Day (Ghana)',
                'month' => 1,
                'day' => 7,
                'message' => "Warm wishes on Constitution Day. Thank you for being part of our community.",
            ],
            [
                'label' => 'Independence Day (Ghana)',
                'month' => 3,
                'day' => 6,
                'message' => "Happy Independence Day! Thank you for your trust and loyalty.",
            ],
            [
                'label' => "May Day (Workers' Day)",
                'month' => 5,
                'day' => 1,
                'message' => "Happy Workers' Day! We appreciate you and your continued support.",
            ],
            [
                'label' => 'AU / Africa Day',
                'month' => 5,
                'day' => 25,
                'message' => "Best wishes on Africa Day. Thank you for being with us.",
            ],
            [
                'label' => "Founders' Day",
                'month' => 8,
                'day' => 4,
                'message' => "Warm wishes on Founders' Day. We value you as a customer.",
            ],
            [
                'label' => 'Kwame Nkrumah Memorial Day',
                'month' => 9,
                'day' => 21,
                'message' => "Greetings on Kwame Nkrumah Memorial Day. Thank you for your support.",
            ],
            [
                'label' => 'Christmas Day',
                'month' => 12,
                'day' => 25,
                'message' => "Merry Christmas! Thank you for a wonderful year — warm wishes from our team to you and your family.",
            ],
            [
                'label' => 'Boxing Day',
                'month' => 12,
                'day' => 26,
                'message' => "Happy Boxing Day! Wishing you joy and good health. Thank you for your continued custom.",
            ],
        ];
    }

    /**
     * @return array{companies_seeded: int, companies_skipped: int, rows_inserted: int, errors: list<string>}
     */
    public static function seedForAllCompaniesWithNoHolidays(): array
    {
        $out = [
            'companies_seeded' => 0,
            'companies_skipped' => 0,
            'rows_inserted' => 0,
            'errors' => [],
        ];
        if (!CompanyHolidayMessage::tableExists()) {
            $out['errors'][] = 'Table company_holiday_messages does not exist. Run the holiday SMS migration first.';
            return $out;
        }

        $db = \Database::getInstance()->getConnection();
        $ids = $db->query("SELECT id FROM companies ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);
        if (!$ids) {
            return $out;
        }

        $templates = self::templates();
        $insert = $db->prepare("INSERT INTO company_holiday_messages
            (company_id, label, event_date, is_annual, message_body, is_active, created_by_user_id, created_at)
            VALUES (?, ?, ?, 1, ?, 1, NULL, NOW())");

        $countSt = $db->prepare("SELECT COUNT(*) FROM company_holiday_messages WHERE company_id = ?");
        foreach ($ids as $cid) {
            $companyId = (int)$cid;
            $countSt->execute([$companyId]);
            $c = (int)$countSt->fetchColumn();
            if ($c > 0) {
                $out['companies_skipped']++;
                continue;
            }
            try {
                foreach ($templates as $t) {
                    $md = sprintf('2000-%02d-%02d', (int)$t['month'], (int)$t['day']);
                    $insert->execute([$companyId, $t['label'], $md, $t['message']]);
                    $out['rows_inserted']++;
                }
                $out['companies_seeded']++;
            } catch (\Exception $e) {
                $out['errors'][] = "Company {$companyId}: " . $e->getMessage();
            }
        }

        return $out;
    }
}
