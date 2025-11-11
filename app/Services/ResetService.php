<?php

namespace App\Services;

require_once __DIR__ . '/../../config/database.php';

use PDO;
use Exception;

/**
 * Reset Service
 * Handles company-level and system-wide data reset operations
 * 
 * SAFETY RULES:
 * 1. Always run inside transactions where possible
 * 2. Support dry-run mode that returns counts only
 * 3. Log all operations to admin_actions table
 * 4. Preserve system_admin users (company_id = NULL) and global tables
 * 5. Handle foreign key constraints carefully
 * 6. Use optimized DELETE JOIN queries where possible
 */
class ResetService {
    private $db;
    private $dryRun;
    private $adminUserId;
    private $rowCounts = [];
    private $errors = [];
    private $monitoringService;

    public function __construct($adminUserId = null, $dryRun = false) {
        $this->db = \Database::getInstance()->getConnection();
        $this->dryRun = $dryRun;
        $this->adminUserId = $adminUserId;
        
        // Initialize monitoring service (PHASE H)
        require_once __DIR__ . '/MonitoringService.php';
        $this->monitoringService = new \App\Services\MonitoringService();
    }

    /**
     * Get affected row counts for a company (dry-run helper)
     * Returns counts for each table that will be affected
     * 
     * @param int $companyId Company ID
     * @return array ['table_name' => count, ...]
     */
    public function getAffectedCounts($companyId) {
        $counts = [];
        
        try {
            // Swaps and related
            try {
                $stmt = $this->db->prepare("SELECT COUNT(*) FROM swaps WHERE company_id = ?");
                $stmt->execute([$companyId]);
                $counts['swaps'] = (int)$stmt->fetchColumn();
            } catch (\Exception $e) {
                $counts['swaps'] = 0;
            }
            
            try {
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) FROM swapped_items 
                    WHERE swap_id IN (SELECT id FROM swaps WHERE company_id = ?)
                ");
                $stmt->execute([$companyId]);
                $counts['swapped_items'] = (int)$stmt->fetchColumn();
            } catch (\Exception $e) {
                $counts['swapped_items'] = 0;
            }
            
            // Note: swap_profit_links count (if table exists)
            try {
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) FROM swap_profit_links 
                    WHERE swap_id IN (SELECT id FROM swaps WHERE company_id = ?)
                ");
                $stmt->execute([$companyId]);
                $counts['swap_profit_links'] = (int)$stmt->fetchColumn();
            } catch (\Exception $e) {
                $counts['swap_profit_links'] = 0;
            }
            
            // POS Sales
            try {
                $stmt = $this->db->prepare("SELECT COUNT(*) FROM pos_sales WHERE company_id = ?");
                $stmt->execute([$companyId]);
                $counts['pos_sales'] = (int)$stmt->fetchColumn();
            } catch (\Exception $e) {
                $counts['pos_sales'] = 0;
            }
            
            try {
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) FROM pos_sale_items 
                    WHERE pos_sale_id IN (SELECT id FROM pos_sales WHERE company_id = ?)
                ");
                $stmt->execute([$companyId]);
                $counts['pos_sale_items'] = (int)$stmt->fetchColumn();
            } catch (\Exception $e) {
                $counts['pos_sale_items'] = 0;
            }
            
            // Repairs
            try {
                $stmt = $this->db->prepare("SELECT COUNT(*) FROM repairs WHERE company_id = ?");
                $stmt->execute([$companyId]);
                $counts['repairs'] = (int)$stmt->fetchColumn();
            } catch (\Exception $e) {
                $counts['repairs'] = 0;
            }
            
            try {
                $stmt = $this->db->prepare("SELECT COUNT(*) FROM repairs_new WHERE company_id = ?");
                $stmt->execute([$companyId]);
                $counts['repairs_new'] = (int)$stmt->fetchColumn();
                
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) FROM repair_accessories 
                    WHERE repair_id IN (SELECT id FROM repairs_new WHERE company_id = ?)
                ");
                $stmt->execute([$companyId]);
                $counts['repair_accessories'] = (int)$stmt->fetchColumn();
            } catch (\Exception $e) {
                $counts['repairs_new'] = 0;
                $counts['repair_accessories'] = 0;
            }
            
            // Customers
            try {
                $stmt = $this->db->prepare("SELECT COUNT(*) FROM customers WHERE company_id = ?");
                $stmt->execute([$companyId]);
                $counts['customers'] = (int)$stmt->fetchColumn();
            } catch (\Exception $e) {
                $counts['customers'] = 0;
            }
            
            try {
                $stmt = $this->db->prepare("SELECT COUNT(*) FROM customer_products WHERE company_id = ?");
                $stmt->execute([$companyId]);
                $counts['customer_products'] = (int)$stmt->fetchColumn();
            } catch (\Exception $e) {
                $counts['customer_products'] = 0;
            }
            
            // Products
            try {
                $stmt = $this->db->prepare("SELECT COUNT(*) FROM products WHERE company_id = ?");
                $stmt->execute([$companyId]);
                $counts['products'] = (int)$stmt->fetchColumn();
            } catch (\Exception $e) {
                $counts['products'] = 0;
            }
            
            try {
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) FROM product_images 
                    WHERE product_id IN (SELECT id FROM products WHERE company_id = ?)
                ");
                $stmt->execute([$companyId]);
                $counts['product_images'] = (int)$stmt->fetchColumn();
            } catch (\Exception $e) {
                $counts['product_images'] = 0;
            }
            
            try {
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) FROM product_specs 
                    WHERE product_id IN (SELECT id FROM products WHERE company_id = ?)
                ");
                $stmt->execute([$companyId]);
                $counts['product_specs'] = (int)$stmt->fetchColumn();
            } catch (\Exception $e) {
                $counts['product_specs'] = 0;
            }
            
            try {
                $stmt = $this->db->prepare("SELECT COUNT(*) FROM products_new WHERE company_id = ?");
                $stmt->execute([$companyId]);
                $counts['products_new'] = (int)$stmt->fetchColumn();
            } catch (\Exception $e) {
                $counts['products_new'] = 0;
            }
            
            // Logs (wrap each in try-catch for missing tables)
            try {
                $stmt = $this->db->prepare("SELECT COUNT(*) FROM restock_logs WHERE company_id = ?");
                $stmt->execute([$companyId]);
                $counts['restock_logs'] = (int)$stmt->fetchColumn();
            } catch (\Exception $e) {
                $counts['restock_logs'] = 0;
            }
            
            try {
                $stmt = $this->db->prepare("SELECT COUNT(*) FROM sms_logs WHERE company_id = ?");
                $stmt->execute([$companyId]);
                $counts['sms_logs'] = (int)$stmt->fetchColumn();
            } catch (\Exception $e) {
                $counts['sms_logs'] = 0;
            }
            
            try {
                $stmt = $this->db->prepare("SELECT COUNT(*) FROM notification_logs WHERE company_id = ?");
                $stmt->execute([$companyId]);
                $counts['notification_logs'] = (int)$stmt->fetchColumn();
            } catch (\Exception $e) {
                $counts['notification_logs'] = 0;
            }
            
            try {
                $stmt = $this->db->prepare("SELECT COUNT(*) FROM sms_payments WHERE company_id = ?");
                $stmt->execute([$companyId]);
                $counts['sms_payments'] = (int)$stmt->fetchColumn();
            } catch (\Exception $e) {
                $counts['sms_payments'] = 0;
            }
            
            // Company-specific data
            try {
                $stmt = $this->db->prepare("SELECT COUNT(*) FROM company_sms_accounts WHERE company_id = ?");
                $stmt->execute([$companyId]);
                $counts['company_sms_accounts'] = (int)$stmt->fetchColumn();
            } catch (\Exception $e) {
                $counts['company_sms_accounts'] = 0;
            }
            
            try {
                $stmt = $this->db->prepare("SELECT COUNT(*) FROM company_modules WHERE company_id = ?");
                $stmt->execute([$companyId]);
                $counts['company_modules'] = (int)$stmt->fetchColumn();
            } catch (\Exception $e) {
                $counts['company_modules'] = 0;
            }
            
            // Users (excluding system_admin)
            try {
                $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE company_id = ? AND role != 'system_admin'");
                $stmt->execute([$companyId]);
                $counts['users'] = (int)$stmt->fetchColumn();
            } catch (\Exception $e) {
                $counts['users'] = 0;
            }
            
        } catch (\Exception $e) {
            error_log("Error getting affected counts: " . $e->getMessage());
            throw $e;
        }
        
        return $counts;
    }

    /**
     * Reset company transactional data (PHASE C - Refined implementation)
     * Preserves company record and global catalogs
     * 
     * @param int $companyId Company ID to reset
     * @param array $options ['dry_run' => bool, 'delete_files' => bool, 'admin_user_id' => int, 'backup_reference' => string]
     * @return array ['success' => bool, 'row_counts' => array, 'errors' => array, 'admin_action_id' => int]
     */
    public function resetCompanyData($companyId, $options = []) {
        // Handle legacy call signature (backup_reference as second param)
        if (is_string($options)) {
            $options = ['backup_reference' => $options];
        }
        
        $dryRun = $options['dry_run'] ?? $this->dryRun;
        $deleteFiles = $options['delete_files'] ?? false;
        $adminUserId = $options['admin_user_id'] ?? $this->adminUserId;
        $backupReference = $options['backup_reference'] ?? null;
        
        $this->rowCounts = [];
        $this->errors = [];

        try {
            // PHASE H: Emit started metric
            $timerId = $this->monitoringService->startTiming('reset.company', [
                'company_id' => $companyId,
                'admin_user_id' => $adminUserId,
                'dry_run' => $dryRun ? 1 : 0
            ]);
            
            $this->monitoringService->emitMetric('reset.company.started', [
                'company_id' => (string)$companyId,
                'admin_user_id' => (string)$adminUserId,
                'dry_run' => $dryRun ? 'true' : 'false'
            ], []);
            
            // Verify company exists
            $companyCheck = $this->db->prepare("SELECT id, name FROM companies WHERE id = ?");
            $companyCheck->execute([$companyId]);
            $company = $companyCheck->fetch(PDO::FETCH_ASSOC);
            
            if (!$company) {
                throw new Exception("Company with ID {$companyId} not found");
            }

            // Verify admin is authorized
            $this->verifyAdminAuthorization($companyId);

            // 1. Fetch affected counts
            $counts = $this->getAffectedCounts($companyId);

            // 2. If dry run: return counts immediately
            if ($dryRun) {
                // PHASE H: Stop timing and emit dry-run metric
                $timing = $this->monitoringService->stopTiming($timerId);
                $this->monitoringService->emitMetric('reset.company.dry_run', [
                    'company_id' => (string)$companyId,
                    'admin_user_id' => (string)$adminUserId
                ], [
                    'total_rows' => array_sum($counts),
                    'duration_ms' => $timing['duration_ms'] ?? 0
                ]);
                
                return [
                    'success' => true,
                    'dry_run' => true,
                    'counts' => $counts,
                    'row_counts' => $counts,
                    'errors' => [],
                    'duration_ms' => $timing['duration_ms'] ?? 0
                ];
            }

            // 3. Verify backup exists (if provided) or require creation
            if (!$backupReference) {
                // Check if recent backup exists
                if (!$this->backupExistsRecently($companyId)) {
                    throw new Exception("Backup required before reset. Please create a backup first.");
                }
            }

            // 4. Begin transaction
            $this->db->beginTransaction();
            
            try {
                // Disable FK checks temporarily
                $this->db->exec("SET FOREIGN_KEY_CHECKS = 0");

                // Get product IDs for file cleanup
                $prodStmt = $this->db->prepare("SELECT id FROM products WHERE company_id = ?");
                $prodStmt->execute([$companyId]);
                $productIds = $prodStmt->fetchAll(PDO::FETCH_COLUMN);

                // Execute deletions in correct order (optimized with JOINs where possible)
                
                // Delete swap_profit_links
                try {
                    $stmt = $this->db->prepare("DELETE FROM swap_profit_links WHERE swap_id IN (SELECT id FROM swaps WHERE company_id = ?)");
                    $stmt->execute([$companyId]);
                    $this->rowCounts['swap_profit_links'] = $stmt->rowCount();
                } catch (\Exception $e) {
                    // Table might not exist, skip
                    $this->rowCounts['swap_profit_links'] = 0;
                }

                // Delete swapped_items using JOIN for better performance
                try {
                    $stmt = $this->db->prepare("DELETE si FROM swapped_items si INNER JOIN swaps s ON si.swap_id = s.id WHERE s.company_id = ?");
                    $stmt->execute([$companyId]);
                    $this->rowCounts['swapped_items'] = $stmt->rowCount();
                } catch (\Exception $e) {
                    // Fallback to subquery if JOIN not supported
                    $stmt = $this->db->prepare("DELETE FROM swapped_items WHERE swap_id IN (SELECT id FROM swaps WHERE company_id = ?)");
                    $stmt->execute([$companyId]);
                    $this->rowCounts['swapped_items'] = $stmt->rowCount();
                }

                // Delete pos_sale_items using JOIN
                try {
                    $stmt = $this->db->prepare("DELETE psi FROM pos_sale_items psi INNER JOIN pos_sales ps ON psi.pos_sale_id = ps.id WHERE ps.company_id = ?");
                    $stmt->execute([$companyId]);
                    $this->rowCounts['pos_sale_items'] = $stmt->rowCount();
                } catch (\Exception $e) {
                    // Fallback
                    $stmt = $this->db->prepare("DELETE FROM pos_sale_items WHERE pos_sale_id IN (SELECT id FROM pos_sales WHERE company_id = ?)");
                    $stmt->execute([$companyId]);
                    $this->rowCounts['pos_sale_items'] = $stmt->rowCount();
                }

                // Unlink swap_id in pos_sales
                $stmt = $this->db->prepare("UPDATE pos_sales SET swap_id = NULL WHERE company_id = ?");
                $stmt->execute([$companyId]);
                $this->rowCounts['pos_sales_unlinked'] = $stmt->rowCount();

                // Delete swaps
                $stmt = $this->db->prepare("DELETE FROM swaps WHERE company_id = ?");
                $stmt->execute([$companyId]);
                $this->rowCounts['swaps'] = $stmt->rowCount();

                // Delete pos_sales
                $stmt = $this->db->prepare("DELETE FROM pos_sales WHERE company_id = ?");
                $stmt->execute([$companyId]);
                $this->rowCounts['pos_sales'] = $stmt->rowCount();

                // Delete repair_accessories using JOIN
                try {
                    $stmt = $this->db->prepare("DELETE ra FROM repair_accessories ra INNER JOIN repairs_new r ON ra.repair_id = r.id WHERE r.company_id = ?");
                    $stmt->execute([$companyId]);
                    $this->rowCounts['repair_accessories'] = $stmt->rowCount();
                } catch (\Exception $e) {
                    // Fallback
                    $stmt = $this->db->prepare("DELETE FROM repair_accessories WHERE repair_id IN (SELECT id FROM repairs_new WHERE company_id = ?)");
                    $stmt->execute([$companyId]);
                    $this->rowCounts['repair_accessories'] = $stmt->rowCount();
                }

                // Delete repairs_new
                try {
                    $stmt = $this->db->prepare("DELETE FROM repairs_new WHERE company_id = ?");
                    $stmt->execute([$companyId]);
                    $this->rowCounts['repairs_new'] = $stmt->rowCount();
                } catch (\Exception $e) {
                    $this->rowCounts['repairs_new'] = 0;
                }

                // Delete repairs
                $stmt = $this->db->prepare("DELETE FROM repairs WHERE company_id = ?");
                $stmt->execute([$companyId]);
                $this->rowCounts['repairs'] = $stmt->rowCount();

                // Delete customer_products
                $stmt = $this->db->prepare("DELETE FROM customer_products WHERE company_id = ?");
                $stmt->execute([$companyId]);
                $this->rowCounts['customer_products'] = $stmt->rowCount();

                // Unlink customer_id in pos_sales and repairs_new
                $stmt = $this->db->prepare("UPDATE pos_sales SET customer_id = NULL WHERE company_id = ?");
                $stmt->execute([$companyId]);
                
                try {
                    $stmt = $this->db->prepare("UPDATE repairs_new SET customer_id = NULL WHERE company_id = ?");
                    $stmt->execute([$companyId]);
                } catch (\Exception $e) {
                    // repairs_new might not exist, skip
                }

                // Delete customers
                $stmt = $this->db->prepare("DELETE FROM customers WHERE company_id = ?");
                $stmt->execute([$companyId]);
                $this->rowCounts['customers'] = $stmt->rowCount();

                // Delete restock_logs
                $stmt = $this->db->prepare("DELETE FROM restock_logs WHERE company_id = ?");
                $stmt->execute([$companyId]);
                $this->rowCounts['restock_logs'] = $stmt->rowCount();

                // Delete product_images and product_specs (use prepared IN clause)
                if (!empty($productIds)) {
                    $in = str_repeat('?,', count($productIds) - 1) . '?';
                    
                    $stmt = $this->db->prepare("DELETE FROM product_images WHERE product_id IN ($in)");
                    $stmt->execute($productIds);
                    $this->rowCounts['product_images'] = $stmt->rowCount();

                    $stmt = $this->db->prepare("DELETE FROM product_specs WHERE product_id IN ($in)");
                    $stmt->execute($productIds);
                    $this->rowCounts['product_specs'] = $stmt->rowCount();
                } else {
                    $this->rowCounts['product_images'] = 0;
                    $this->rowCounts['product_specs'] = 0;
                }

                // Delete products
                $stmt = $this->db->prepare("DELETE FROM products WHERE company_id = ?");
                $stmt->execute([$companyId]);
                $this->rowCounts['products'] = $stmt->rowCount();

                // Delete products_new
                try {
                    $stmt = $this->db->prepare("DELETE FROM products_new WHERE company_id = ?");
                    $stmt->execute([$companyId]);
                    $this->rowCounts['products_new'] = $stmt->rowCount();
                } catch (\Exception $e) {
                    $this->rowCounts['products_new'] = 0;
                }

                // Delete SMS and notification logs (wrap in try-catch for missing tables)
                try {
                    $stmt = $this->db->prepare("DELETE FROM sms_logs WHERE company_id = ?");
                    $stmt->execute([$companyId]);
                    $this->rowCounts['sms_logs'] = $stmt->rowCount();
                } catch (\Exception $e) {
                    $this->rowCounts['sms_logs'] = 0;
                }

                try {
                    $stmt = $this->db->prepare("DELETE FROM notification_logs WHERE company_id = ?");
                    $stmt->execute([$companyId]);
                    $this->rowCounts['notification_logs'] = $stmt->rowCount();
                } catch (\Exception $e) {
                    $this->rowCounts['notification_logs'] = 0;
                }

                try {
                    $stmt = $this->db->prepare("DELETE FROM sms_payments WHERE company_id = ?");
                    $stmt->execute([$companyId]);
                    $this->rowCounts['sms_payments'] = $stmt->rowCount();
                } catch (\Exception $e) {
                    $this->rowCounts['sms_payments'] = 0;
                }

                // Reset company_sms_accounts (soft reset - clear credits)
                try {
                    $stmt = $this->db->prepare("UPDATE company_sms_accounts SET total_sms = 0, sms_used = 0 WHERE company_id = ?");
                    $stmt->execute([$companyId]);
                    $this->rowCounts['company_sms_accounts'] = $stmt->rowCount();
                } catch (\Exception $e) {
                    $this->rowCounts['company_sms_accounts'] = 0;
                }

                // Delete company_modules
                $stmt = $this->db->prepare("DELETE FROM company_modules WHERE company_id = ?");
                $stmt->execute([$companyId]);
                $this->rowCounts['company_modules'] = $stmt->rowCount();

                // Delete users except system_admin
                $stmt = $this->db->prepare("DELETE FROM users WHERE company_id = ? AND role != 'system_admin'");
                $stmt->execute([$companyId]);
                $this->rowCounts['users'] = $stmt->rowCount();

                // Re-enable FK checks
                $this->db->exec("SET FOREIGN_KEY_CHECKS = 1");

                // Commit transaction
                $this->db->commit();
                
                // PHASE H: Stop timing and emit completion metrics
                $timing = $this->monitoringService->stopTiming($timerId);
                $totalRows = array_sum($this->rowCounts);
                
                $this->monitoringService->emitMetric('reset.company.completed', [
                    'company_id' => (string)$companyId,
                    'admin_user_id' => (string)$adminUserId
                ], [
                    'total_rows' => $totalRows,
                    'duration_ms' => $timing['duration_ms'] ?? 0,
                    'duration_seconds' => round($timing['duration'] ?? 0, 3)
                ]);

                // 5. Return success with counts
                return [
                    'success' => true,
                    'dry_run' => false,
                    'row_counts' => $this->rowCounts,
                    'product_ids' => $productIds, // For file cleanup
                    'errors' => [],
                    'duration_ms' => $timing['duration_ms'] ?? 0,
                    'total_rows' => $totalRows
                ];

            } catch (Exception $e) {
                // Rollback on error
                $this->db->rollBack();
                // Re-enable FK checks to be safe
                $this->db->exec("SET FOREIGN_KEY_CHECKS = 1");
                throw $e;
            }

        } catch (Exception $e) {
            // PHASE H: Stop timing and emit failure metric
            if (isset($timerId)) {
                $timing = $this->monitoringService->stopTiming($timerId);
                $this->monitoringService->emitMetric('reset.company.failed', [
                    'company_id' => (string)$companyId,
                    'admin_user_id' => (string)($adminUserId ?? 'unknown'),
                    'error' => $e->getMessage()
                ], [
                    'duration_ms' => $timing['duration_ms'] ?? 0
                ]);
            }
            
            $this->errors[] = $e->getMessage();
            return [
                'success' => false,
                'dry_run' => $dryRun,
                'row_counts' => $this->rowCounts,
                'errors' => $this->errors
            ];
        }
    }

    /**
     * Check if backup exists recently (within last 24 hours)
     */
    private function backupExistsRecently($companyId = null) {
        // This is a placeholder - in real implementation, check backup storage
        // For now, we'll assume backup service handles this
        return false; // Always require explicit backup
    }

    /**
     * Reset all system data (wipes all companies and their data, preserves system_admin + optional settings)
     * 
     * @param bool $preserveSettings Whether to preserve system_settings table
     * @param bool $preserveGlobalCatalogs Whether to preserve categories, brands, subcategories
     * @param string $backupReference Backup file reference
     * @return array ['success' => bool, 'row_counts' => array, 'errors' => array]
     */
    public function resetSystemData($preserveSettings = true, $preserveGlobalCatalogs = true, $backupReference = null) {
        $this->rowCounts = [];
        $this->errors = [];

        try {
            // Only system_admin can run this
            $this->verifySystemAdminOnly();

            // Begin transaction
            if (!$this->dryRun) {
                $this->db->beginTransaction();
            }

            // Execute reset steps in order
            $steps = $this->getSystemResetSteps($preserveSettings, $preserveGlobalCatalogs);
            
            foreach ($steps as $step) {
                try {
                    $this->executeStep($step);
                } catch (Exception $e) {
                    $isOptional = $step['optional'] ?? false;
                    if ($isOptional) {
                        // For optional steps, just log the error but continue
                        error_log("Optional step {$step['order']} ({$step['table']}) skipped: " . $e->getMessage());
                        if (isset($step['table'])) {
                            $this->rowCounts[$step['table']] = 0;
                        }
                    } else {
                        // For required steps, add to errors
                        $this->errors[] = "Step {$step['order']} failed: " . $e->getMessage();
                        if (!$this->dryRun) {
                            throw $e; // Rollback on error for required steps
                        }
                    }
                }
            }

            if (!$this->dryRun) {
                $this->db->commit();
            }

            return [
                'success' => empty($this->errors),
                'row_counts' => $this->rowCounts,
                'errors' => $this->errors
            ];

        } catch (Exception $e) {
            if (!$this->dryRun && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->errors[] = $e->getMessage();
            return [
                'success' => false,
                'row_counts' => $this->rowCounts,
                'errors' => $this->errors
            ];
        }
    }

    /**
     * Get ordered list of steps for company reset
     */
    private function getCompanyResetSteps($companyId) {
        return [
            // Step 1: Disable FK checks
            ['order' => 1, 'type' => 'raw_sql', 'sql' => 'SET FOREIGN_KEY_CHECKS = 0', 'table' => null],
            
            // Steps 2-3: Delete swap-related data (handle circular references first)
            ['order' => 2, 'type' => 'delete', 'sql' => 'DELETE FROM swap_profit_links WHERE swap_id IN (SELECT id FROM swaps WHERE company_id = ?)', 'table' => 'swap_profit_links', 'params' => [$companyId]],
            ['order' => 3, 'type' => 'delete', 'sql' => 'DELETE FROM swapped_items WHERE swap_id IN (SELECT id FROM swaps WHERE company_id = ?)', 'table' => 'swapped_items', 'params' => [$companyId]],
            
            // Steps 4-6: Handle POS sales and swap links
            ['order' => 4, 'type' => 'delete', 'sql' => 'DELETE FROM pos_sale_items WHERE pos_sale_id IN (SELECT id FROM pos_sales WHERE company_id = ?)', 'table' => 'pos_sale_items', 'params' => [$companyId]],
            ['order' => 5, 'type' => 'update', 'sql' => 'UPDATE pos_sales SET swap_id = NULL WHERE company_id = ?', 'table' => 'pos_sales', 'params' => [$companyId]],
            ['order' => 6, 'type' => 'update', 'sql' => 'UPDATE pos_sale_items SET swap_id = NULL WHERE pos_sale_id IN (SELECT id FROM pos_sales WHERE company_id = ?)', 'table' => 'pos_sale_items', 'params' => [$companyId]],
            
            // Step 7: Delete swaps
            ['order' => 7, 'type' => 'delete', 'sql' => 'DELETE FROM swaps WHERE company_id = ?', 'table' => 'swaps', 'params' => [$companyId]],
            
            // Step 8: Delete POS sales
            ['order' => 8, 'type' => 'delete', 'sql' => 'DELETE FROM pos_sales WHERE company_id = ?', 'table' => 'pos_sales', 'params' => [$companyId]],
            
            // Steps 9-11: Delete repair-related data (repairs_new and repair_accessories may not exist)
            ['order' => 9, 'type' => 'delete', 'sql' => 'DELETE FROM repair_accessories WHERE repair_id IN (SELECT id FROM repairs_new WHERE company_id = ?)', 'table' => 'repair_accessories', 'params' => [$companyId], 'optional' => true],
            ['order' => 10, 'type' => 'delete', 'sql' => 'DELETE FROM repairs_new WHERE company_id = ?', 'table' => 'repairs_new', 'params' => [$companyId], 'optional' => true],
            ['order' => 11, 'type' => 'delete', 'sql' => 'DELETE FROM repairs WHERE company_id = ?', 'table' => 'repairs', 'params' => [$companyId]],
            
            // Steps 12-15: Handle customers
            ['order' => 12, 'type' => 'delete', 'sql' => 'DELETE FROM customer_products WHERE company_id = ?', 'table' => 'customer_products', 'params' => [$companyId]],
            ['order' => 13, 'type' => 'update', 'sql' => 'UPDATE pos_sales SET customer_id = NULL WHERE company_id = ?', 'table' => 'pos_sales', 'params' => [$companyId]],
            ['order' => 14, 'type' => 'update', 'sql' => 'UPDATE repairs_new SET customer_id = NULL WHERE company_id = ?', 'table' => 'repairs_new', 'params' => [$companyId], 'optional' => true],
            ['order' => 15, 'type' => 'delete', 'sql' => 'DELETE FROM customers WHERE company_id = ?', 'table' => 'customers', 'params' => [$companyId]],
            
            // Steps 16-20: Delete products and related data
            ['order' => 16, 'type' => 'delete', 'sql' => 'DELETE FROM restock_logs WHERE company_id = ?', 'table' => 'restock_logs', 'params' => [$companyId], 'optional' => true],
            ['order' => 17, 'type' => 'delete', 'sql' => 'DELETE FROM product_images WHERE product_id IN (SELECT id FROM products WHERE company_id = ?)', 'table' => 'product_images', 'params' => [$companyId]],
            ['order' => 18, 'type' => 'delete', 'sql' => 'DELETE FROM product_specs WHERE product_id IN (SELECT id FROM products WHERE company_id = ?)', 'table' => 'product_specs', 'params' => [$companyId]],
            ['order' => 19, 'type' => 'delete', 'sql' => 'DELETE FROM products WHERE company_id = ?', 'table' => 'products', 'params' => [$companyId]],
            ['order' => 20, 'type' => 'delete', 'sql' => 'DELETE FROM products_new WHERE company_id = ?', 'table' => 'products_new', 'params' => [$companyId], 'optional' => true],
            
            // Steps 21-24: Delete logs and notifications (these tables may not exist)
            ['order' => 21, 'type' => 'delete', 'sql' => 'DELETE FROM sms_logs WHERE company_id = ?', 'table' => 'sms_logs', 'params' => [$companyId], 'optional' => true],
            ['order' => 22, 'type' => 'delete', 'sql' => 'DELETE FROM notification_logs WHERE company_id = ?', 'table' => 'notification_logs', 'params' => [$companyId], 'optional' => true],
            ['order' => 23, 'type' => 'delete', 'sql' => 'DELETE FROM sms_payments WHERE company_id = ?', 'table' => 'sms_payments', 'params' => [$companyId], 'optional' => true],
            
            // Step 24: Soft reset SMS account (clear credits) - optional table
            ['order' => 24, 'type' => 'update', 'sql' => 'UPDATE company_sms_accounts SET total_sms = 0, sms_used = 0 WHERE company_id = ?', 'table' => 'company_sms_accounts', 'params' => [$companyId], 'optional' => true],
            
            // Steps 25-26: Delete company-specific settings and users (preserve system_admin)
            ['order' => 25, 'type' => 'delete', 'sql' => 'DELETE FROM company_modules WHERE company_id = ?', 'table' => 'company_modules', 'params' => [$companyId]],
            ['order' => 26, 'type' => 'delete', 'sql' => "DELETE FROM users WHERE company_id = ? AND role != 'system_admin'", 'table' => 'users', 'params' => [$companyId]],
            
            // Step 27: Re-enable FK checks
            ['order' => 27, 'type' => 'raw_sql', 'sql' => 'SET FOREIGN_KEY_CHECKS = 1', 'table' => null],
        ];
    }

    /**
     * Get ordered list of steps for system reset
     */
    private function getSystemResetSteps($preserveSettings, $preserveGlobalCatalogs) {
        $steps = [
            // Step 1: Disable FK checks
            ['order' => 1, 'type' => 'raw_sql', 'sql' => 'SET FOREIGN_KEY_CHECKS = 0', 'table' => null],
            
            // Steps 2-4: Delete swap-related data
            ['order' => 2, 'type' => 'delete', 'sql' => 'DELETE FROM swap_profit_links', 'table' => 'swap_profit_links'],
            ['order' => 3, 'type' => 'delete', 'sql' => 'DELETE FROM swapped_items', 'table' => 'swapped_items'],
            ['order' => 4, 'type' => 'delete', 'sql' => 'DELETE FROM pos_sale_items', 'table' => 'pos_sale_items'],
            
            // Steps 5-6: Delete swaps and POS sales
            ['order' => 5, 'type' => 'delete', 'sql' => 'DELETE FROM swaps', 'table' => 'swaps'],
            ['order' => 6, 'type' => 'delete', 'sql' => 'DELETE FROM pos_sales', 'table' => 'pos_sales'],
            
            // Steps 7-9: Delete repair data
            ['order' => 7, 'type' => 'delete', 'sql' => 'DELETE FROM repair_accessories', 'table' => 'repair_accessories'],
            ['order' => 8, 'type' => 'delete', 'sql' => 'DELETE FROM repairs_new', 'table' => 'repairs_new'],
            ['order' => 9, 'type' => 'delete', 'sql' => 'DELETE FROM repairs', 'table' => 'repairs'],
            
            // Steps 10-11: Delete customer data
            ['order' => 10, 'type' => 'delete', 'sql' => 'DELETE FROM customer_products', 'table' => 'customer_products'],
            ['order' => 11, 'type' => 'delete', 'sql' => 'DELETE FROM customers', 'table' => 'customers'],
            
            // Steps 12-16: Delete product data
            ['order' => 12, 'type' => 'delete', 'sql' => 'DELETE FROM restock_logs', 'table' => 'restock_logs'],
            ['order' => 13, 'type' => 'delete', 'sql' => 'DELETE FROM product_images', 'table' => 'product_images'],
            ['order' => 14, 'type' => 'delete', 'sql' => 'DELETE FROM product_specs', 'table' => 'product_specs'],
            ['order' => 15, 'type' => 'delete', 'sql' => 'DELETE FROM products', 'table' => 'products'],
            ['order' => 16, 'type' => 'delete', 'sql' => 'DELETE FROM products_new', 'table' => 'products_new', 'optional' => true],
            
            // Steps 17-20: Delete logs and notifications (these tables may not exist)
            ['order' => 17, 'type' => 'delete', 'sql' => 'DELETE FROM sms_logs', 'table' => 'sms_logs', 'optional' => true],
            ['order' => 18, 'type' => 'delete', 'sql' => 'DELETE FROM notification_logs', 'table' => 'notification_logs', 'optional' => true],
            ['order' => 19, 'type' => 'delete', 'sql' => 'DELETE FROM sms_payments', 'table' => 'sms_payments', 'optional' => true],
            ['order' => 20, 'type' => 'delete', 'sql' => 'DELETE FROM company_sms_accounts', 'table' => 'company_sms_accounts', 'optional' => true],
            
            // Steps 21-22: Delete company modules and users (preserve system_admin)
            ['order' => 21, 'type' => 'delete', 'sql' => 'DELETE FROM company_modules', 'table' => 'company_modules'],
            ['order' => 22, 'type' => 'delete', 'sql' => "DELETE FROM users WHERE company_id IS NOT NULL", 'table' => 'users'],
            
            // Step 23: Delete companies
            ['order' => 23, 'type' => 'delete', 'sql' => 'DELETE FROM companies', 'table' => 'companies'],
            
            // Step 24: Re-enable FK checks
            ['order' => 24, 'type' => 'raw_sql', 'sql' => 'SET FOREIGN_KEY_CHECKS = 1', 'table' => null],
        ];

        // Note: system_settings, categories, brands, subcategories are preserved by not being deleted
        // They are global tables and should remain unless explicitly requested

        return $steps;
    }

    /**
     * Execute a single reset step
     */
    private function executeStep($step, $companyId = null) {
        $isOptional = $step['optional'] ?? false;
        
        try {
            if ($step['type'] === 'raw_sql') {
                // Execute raw SQL (like SET FOREIGN_KEY_CHECKS)
                if (!$this->dryRun) {
                    $this->db->exec($step['sql']);
                }
                return;
            }

            // For DELETE and UPDATE operations, get count first
            $params = $step['params'] ?? [];
            
            if ($this->dryRun) {
                // In dry-run mode, count rows that would be affected
                $countSql = $this->convertDeleteToCount($step['sql']);
                $countStmt = $this->db->prepare($countSql);
                $countStmt->execute($params);
                $count = $countStmt->fetchColumn();
                
                if ($step['table']) {
                    $this->rowCounts[$step['table']] = (int)$count;
                }
            } else {
                // Execute the actual SQL
                $stmt = $this->db->prepare($step['sql']);
                $stmt->execute($params);
                $affectedRows = $stmt->rowCount();
                
                if ($step['table']) {
                    $this->rowCounts[$step['table']] = $affectedRows;
                }
            }
        } catch (\PDOException $e) {
            // Check if it's a missing table error
            if (strpos($e->getMessage(), "doesn't exist") !== false || 
                strpos($e->getMessage(), "Base table or view not found") !== false) {
                // Table doesn't exist - if optional, skip it; otherwise throw
                if ($isOptional) {
                    if ($step['table']) {
                        $this->rowCounts[$step['table']] = 0;
                    }
                    // Don't throw error for optional tables - just return
                    return;
                } else {
                    // For required tables, throw the error
                    throw $e;
                }
            } else {
                // Other database errors - always throw
                throw $e;
            }
        }
    }

    /**
     * Convert DELETE/UPDATE SQL to COUNT query for dry-run
     */
    private function convertDeleteToCount($sql) {
        // Simple conversion: DELETE FROM table WHERE... -> SELECT COUNT(*) FROM table WHERE...
        $sql = preg_replace('/^DELETE\s+FROM/i', 'SELECT COUNT(*) FROM', $sql);
        $sql = preg_replace('/^UPDATE\s+(\w+)\s+SET/i', 'SELECT COUNT(*) FROM $1 WHERE', $sql);
        
        // Handle UPDATE: remove SET clause and keep WHERE
        if (preg_match('/UPDATE\s+(\w+)\s+SET.*?WHERE\s+(.+)/i', $sql, $matches)) {
            $sql = "SELECT COUNT(*) FROM {$matches[1]} WHERE {$matches[2]}";
        }
        
        return $sql;
    }

    /**
     * Verify admin is authorized to reset company
     */
    private function verifyAdminAuthorization($companyId) {
        $stmt = $this->db->prepare("SELECT role, company_id FROM users WHERE id = ?");
        $stmt->execute([$this->adminUserId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            throw new Exception("Admin user not found");
        }
        
        // system_admin can reset any company
        if ($user['role'] === 'system_admin') {
            return;
        }
        
        // manager can reset their own company only
        if ($user['role'] === 'manager' && (int)$user['company_id'] === (int)$companyId) {
            return;
        }
        
        throw new Exception("Unauthorized: Only system_admin or company manager can reset company data");
    }

    /**
     * Verify only system_admin can run system reset
     */
    private function verifySystemAdminOnly() {
        $stmt = $this->db->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$this->adminUserId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || $user['role'] !== 'system_admin') {
            throw new Exception("Unauthorized: Only system_admin can reset system data");
        }
    }

    /**
     * Get row counts (for dry-run preview)
     */
    public function getRowCounts() {
        return $this->rowCounts;
    }

    /**
     * Get errors
     */
    public function getErrors() {
        return $this->errors;
    }
}

