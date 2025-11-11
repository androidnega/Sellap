<?php
/**
 * Restore Mhannuellens customer data from backup
 */
require_once __DIR__ . '/config/database.php';

$db = \Database::getInstance()->getConnection();

echo "=== RESTORING MHANNULLENS CUSTOMER DATA ===\n\n";

// Data from backup (November 2, 2025)
$backupCustomers = [
    [
        'id' => 1,
        'company_id' => 1,
        'unique_id' => 'CUS6900A52348056',
        'full_name' => 'MERCY HOWARD',
        'phone_number' => '0538370699',
        'email' => 'asantewaaabena303@gmail.com',
        'address' => 'WH-0001-2124',
        'created_by_user_id' => 1,
        'created_at' => '2025-10-28 11:12:35'
    ],
    [
        'id' => 2,
        'company_id' => 1,
        'unique_id' => 'CUS6900AE0999D8C',
        'full_name' => 'Emmanuel Kwofie',
        'phone_number' => '0597749930',
        'email' => 'kwofiee3@gmail.com',
        'address' => null,
        'created_by_user_id' => 1,
        'created_at' => '2025-10-28 11:50:33'
    ]
];

// Check current customers
echo "Current customers in database:\n";
$stmt = $db->query("SELECT id, unique_id, full_name, phone_number FROM customers WHERE company_id = 1");
$current = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($current as $cust) {
    echo "  - {$cust['unique_id']}: {$cust['full_name']} ({$cust['phone_number']})\n";
}

echo "\nRestoring customers from backup...\n";

$restored = 0;
$skipped = 0;

foreach ($backupCustomers as $customer) {
    // Check if customer already exists
    $stmt = $db->prepare("SELECT id FROM customers WHERE unique_id = ? OR (company_id = ? AND phone_number = ?)");
    $stmt->execute([$customer['unique_id'], $customer['company_id'], $customer['phone_number']]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        echo "  ⊘ Skipping {$customer['unique_id']} - already exists\n";
        $skipped++;
        continue;
    }
    
    // Insert customer
    try {
        $stmt = $db->prepare("
            INSERT INTO customers 
            (company_id, unique_id, full_name, phone_number, email, address, created_by_user_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $customer['company_id'],
            $customer['unique_id'],
            $customer['full_name'],
            $customer['phone_number'],
            $customer['email'],
            $customer['address'],
            $customer['created_by_user_id'],
            $customer['created_at']
        ]);
        
        echo "  ✓ Restored {$customer['unique_id']}: {$customer['full_name']}\n";
        $restored++;
    } catch (\Exception $e) {
        echo "  ✗ Error restoring {$customer['unique_id']}: {$e->getMessage()}\n";
    }
}

echo "\n=== SUMMARY ===\n";
echo "Restored: {$restored}\n";
echo "Skipped (already exist): {$skipped}\n";

// Show final count
$stmt = $db->query("SELECT COUNT(*) as count FROM customers WHERE company_id = 1");
$final = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Total customers for Mhannuellens: {$final['count']}\n";



