<?php
namespace App\Models;

use PDO;

require_once __DIR__ . '/../../config/database.php';

class Repair {
    private $db;
    private $table = 'repairs_new';

    public function __construct() {
        $this->db = \Database::getInstance()->getConnection();
    }

    /**
     * Create a new repair
     */
    public function create(array $data) {
        // Validate technician_id is set and is a valid integer
        if (!isset($data['technician_id']) || $data['technician_id'] === null || $data['technician_id'] === '' || intval($data['technician_id']) <= 0) {
            error_log("Repair::create() - Invalid technician_id: " . var_export($data['technician_id'] ?? 'NOT SET', true));
            throw new \Exception('technician_id is required and must be a valid user ID. Received: ' . var_export($data['technician_id'] ?? 'NOT SET', true));
        }
        
        // Ensure technician_id is cast to integer
        $technicianId = (int)$data['technician_id'];
        
        if ($technicianId <= 0) {
            error_log("Repair::create() - technician_id is zero or negative after casting: {$technicianId}");
            throw new \Exception("technician_id must be a positive integer. Got: {$technicianId}");
        }
        
        // Verify technician exists in users table and belongs to the same company
        $companyId = $data['company_id'] ?? null;
        if ($companyId) {
            $checkTech = $this->db->prepare("SELECT id, role FROM users WHERE id = ? AND company_id = ?");
            $checkTech->execute([$technicianId, $companyId]);
        } else {
            $checkTech = $this->db->prepare("SELECT id, role FROM users WHERE id = ?");
            $checkTech->execute([$technicianId]);
        }
        
        $techResult = $checkTech->fetch(PDO::FETCH_ASSOC);
        if (!$techResult || !isset($techResult['id'])) {
            error_log("Repair::create() - Technician ID {$technicianId} does not exist in users table" . ($companyId ? " for company {$companyId}" : ""));
            throw new \Exception("Technician ID {$technicianId} does not exist" . ($companyId ? " in your company" : " in the users table") . ". Please verify the technician exists and try again.");
        }
        
        // Log technician validation success
        error_log("Repair::create() - Validated technician_id: {$technicianId}, role: " . ($techResult['role'] ?? 'unknown') . ", company_id: " . ($companyId ?? 'NULL'));
        
        // Ensure payment_status is 'paid' for new bookings (payment received at booking time)
        // This ensures all booked repairs are marked as paid
        if (!isset($data['payment_status']) || empty($data['payment_status']) || $data['payment_status'] === 'unpaid') {
            $data['payment_status'] = 'paid';
            error_log("Repair::create() - Setting payment_status to 'paid' for new booking");
        }
        
        // Check if device_brand and device_model columns exist
        $hasDeviceFields = $this->checkDeviceColumnsExist();
        error_log("Repair::create() - checkDeviceColumnsExist() returned: " . ($hasDeviceFields ? 'TRUE' : 'FALSE'));
        
        // Initialize insertedId to track which path was taken
        $insertedId = null;
        
        if ($hasDeviceFields) {
            error_log("Repair::create() - Using PATH: hasDeviceFields=TRUE");
            // Check if labour_cost column exists
            $checkLabourCost = $this->db->query("SHOW COLUMNS FROM repairs_new LIKE 'labour_cost'");
            $hasLabourCost = $checkLabourCost && $checkLabourCost->rowCount() > 0;
            
            if ($hasLabourCost) {
                $stmt = $this->db->prepare("
                    INSERT INTO repairs_new (
                        company_id, technician_id, product_id, device_brand, device_model,
                        customer_name, customer_contact, customer_id, issue_description, 
                        repair_cost, labour_cost, parts_cost, accessory_cost, total_cost,
                        status, payment_status, tracking_code, notes
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                // Calculate labour_cost if not provided (default to 50% of repair_cost)
                $labourCost = $data['labour_cost'] ?? null;
                if ($labourCost === null && isset($data['repair_cost'])) {
                    $labourCost = floatval($data['repair_cost']) * 0.5; // Default 50% of repair cost
                }
                $labourCost = $labourCost ?? 0;
                
                // Ensure customer_name, customer_contact, and issue_description are not empty
                // These are required fields and should always have values from the booking form
                $customerName = trim($data['customer_name'] ?? '');
                $customerContact = trim($data['customer_contact'] ?? '');
                $issueDescription = trim($data['issue_description'] ?? '');
                
                // ============================================
                // COMPREHENSIVE LOGGING IN Repair::create()
                // ============================================
                error_log("============================================");
                error_log("Repair::create() - START");
                error_log("============================================");
                error_log("Repair::create() - Full data array received:");
                error_log(json_encode($data, JSON_PRETTY_PRINT));
                error_log("Repair::create() - EXTRACTED VALUES:");
                error_log("  - customer_name: " . var_export($customerName, true) . " (length: " . strlen($customerName) . ")");
                error_log("  - customer_contact: " . var_export($customerContact, true) . " (length: " . strlen($customerContact) . ")");
                error_log("  - issue_description: " . var_export($issueDescription, true) . " (length: " . strlen($issueDescription) . ")");
                error_log("  - repair_cost: " . var_export($data['repair_cost'] ?? 'NOT SET', true));
                error_log("  - parts_cost: " . var_export($data['parts_cost'] ?? 'NOT SET', true));
                error_log("  - total_cost: " . var_export($data['total_cost'] ?? 'NOT SET', true));
                error_log("  - product_id: " . var_export($data['product_id'] ?? 'NOT SET', true));
                error_log("  - device_brand: " . var_export($data['device_brand'] ?? 'NOT SET', true));
                error_log("  - device_model: " . var_export($data['device_model'] ?? 'NOT SET', true));
                
                // CRITICAL: Validate required fields BEFORE attempting insert
                // Throw exception if any required field is empty - don't allow silent failures
                if (empty($customerName)) {
                    error_log("Repair::create() ERROR - customer_name is EMPTY!");
                    error_log("Repair::create() - Raw data['customer_name']: " . var_export($data['customer_name'] ?? 'NOT SET', true));
                    throw new \Exception('Customer name is required and cannot be empty. Data was not saved properly from the form.');
                }
                
                if (empty($customerContact)) {
                    error_log("Repair::create() ERROR - customer_contact is EMPTY!");
                    error_log("Repair::create() - Raw data['customer_contact']: " . var_export($data['customer_contact'] ?? 'NOT SET', true));
                    throw new \Exception('Customer contact is required and cannot be empty. Data was not saved properly from the form.');
                }
                
                if (empty($issueDescription)) {
                    error_log("Repair::create() ERROR - issue_description is EMPTY!");
                    error_log("Repair::create() - Raw data['issue_description']: " . var_export($data['issue_description'] ?? 'NOT SET', true));
                    throw new \Exception('Issue description is required and cannot be empty. Data was not saved properly from the form.');
                }
                
                // Log if these are empty (shouldn't happen after validation above)
                error_log("Repair::create() - VALIDATION PASSED:");
                error_log("  - customer_name: " . var_export($customerName, true) . " (length: " . strlen($customerName) . ")");
                error_log("  - customer_contact: " . var_export($customerContact, true) . " (length: " . strlen($customerContact) . ")");
                error_log("  - issue_description: " . var_export($issueDescription, true) . " (length: " . strlen($issueDescription) . ")");
                
                // Prepare values for execution - ensure we use the validated trimmed values
                $executeValues = [
                    $data['company_id'],
                    $technicianId, // Use validated and cast technician_id
                    $data['product_id'] ?? null,
                    $data['device_brand'] ?? null,
                    $data['device_model'] ?? null,
                    $customerName, // Use validated and trimmed value
                    $customerContact, // Use validated and trimmed value
                    $data['customer_id'] ?? null,
                    $issueDescription, // Use validated and trimmed value
                    $data['repair_cost'] ?? 0,
                    $labourCost,
                    $data['parts_cost'] ?? 0,
                    $data['accessory_cost'] ?? 0,
                    $data['total_cost'] ?? 0,
                    $data['status'] ?? 'pending',
                    $data['payment_status'], // Always 'paid' for new bookings (enforced above)
                    $data['tracking_code'] ?? $this->generateTrackingCode(),
                    $data['notes'] ?? null
                ];
                
                error_log("Repair::create() - VALUES BEING INSERTED:");
                error_log("  [0] company_id: " . var_export($executeValues[0], true));
                error_log("  [1] technician_id: " . var_export($executeValues[1], true));
                error_log("  [2] product_id: " . var_export($executeValues[2], true));
                error_log("  [3] device_brand: " . var_export($executeValues[3], true));
                error_log("  [4] device_model: " . var_export($executeValues[4], true));
                error_log("  [5] customer_name: " . var_export($executeValues[5], true));
                error_log("  [6] customer_contact: " . var_export($executeValues[6], true));
                error_log("  [7] customer_id: " . var_export($executeValues[7], true));
                error_log("  [8] issue_description: " . var_export($executeValues[8], true) . " (length: " . strlen($executeValues[8]) . ")");
                error_log("  [9] repair_cost: " . var_export($executeValues[9], true));
                error_log("  [10] labour_cost: " . var_export($executeValues[10], true));
                error_log("  [11] parts_cost: " . var_export($executeValues[11], true));
                error_log("  [12] accessory_cost: " . var_export($executeValues[12], true));
                error_log("  [13] total_cost: " . var_export($executeValues[13], true));
                error_log("  [14] status: " . var_export($executeValues[14], true));
                error_log("  [15] payment_status: " . var_export($executeValues[15], true));
                error_log("  [16] tracking_code: " . var_export($executeValues[16], true));
                error_log("  [17] notes: " . var_export($executeValues[17], true));
                
                try {
                    // Execute the insert
                    $executeResult = $stmt->execute($executeValues);
                    
                    // Always check error info, even if execute() returns true
                    $errorInfo = $stmt->errorInfo();
                    $errorCode = $stmt->errorCode();
                    
                    // Check if execute succeeded
                    if ($executeResult === false || $errorCode !== '00000') {
                        error_log("Repair::create() - Execute failed. Error Code: {$errorCode}, Error Info: " . json_encode($errorInfo));
                        
                        // Check for specific constraint violations
                        if ($errorCode == '23000') {
                            $errorMessage = $errorInfo[2] ?? 'Unknown constraint error';
                            if (strpos($errorMessage, 'tracking_code') !== false) {
                                throw new \Exception('Failed to create repair: Tracking code already exists. Please try again.');
                            } elseif (strpos($errorMessage, 'technician_id') !== false || strpos($errorMessage, 'FOREIGN KEY') !== false) {
                                throw new \Exception('Failed to create repair: Invalid technician ID. The selected technician (ID: ' . $technicianId . ') does not exist in the users table.');
                            } elseif (strpos($errorMessage, 'company_id') !== false) {
                                throw new \Exception('Failed to create repair: Invalid company ID.');
                            } else {
                                throw new \Exception('Failed to create repair: Database constraint violation. ' . $errorMessage);
                            }
                        }
                        
                        throw new \Exception('Failed to execute INSERT statement: ' . ($errorInfo[2] ?? 'Unknown error (Code: ' . $errorCode . ')'));
                    }
                    
                    // Check if any rows were affected
                    $rowsAffected = $stmt->rowCount();
                    if ($rowsAffected === 0) {
                        error_log("Repair::create() - No rows affected! Error Code: {$errorCode}, Error Info: " . json_encode($errorInfo));
                        
                        // Check for constraint violations even if execute() returned true
                        if ($errorCode == '23000' || (isset($errorInfo[2]) && strpos($errorInfo[2], 'Duplicate') !== false)) {
                            $errorMessage = $errorInfo[2] ?? 'Unknown constraint error';
                            if (strpos($errorMessage, 'tracking_code') !== false) {
                                throw new \Exception('Failed to create repair: Tracking code already exists. Please try again.');
                            } elseif (strpos($errorMessage, 'technician_id') !== false || strpos($errorMessage, 'FOREIGN KEY') !== false) {
                                throw new \Exception('Failed to create repair: Invalid technician ID. The selected technician (ID: ' . $technicianId . ') does not exist in the users table.');
                            } else {
                                throw new \Exception('Failed to create repair: Database constraint violation. ' . $errorMessage);
                            }
                        }
                        
                        throw new \Exception('INSERT statement executed but no rows were affected. This may indicate a constraint violation or invalid data. Error: ' . ($errorInfo[2] ?? 'Unknown'));
                    }
                    
                    // Get the inserted ID - must be called on the same connection that executed the INSERT
                    $insertedId = $this->db->lastInsertId();
                    error_log("Repair::create() - Inserted repair with ID: {$insertedId} (type: " . gettype($insertedId) . ")");
                    error_log("Repair::create() - Rows affected: {$rowsAffected}");
                    
                    // Double-check: if lastInsertId is 0 or empty, the insert likely failed
                    if (empty($insertedId) || $insertedId == 0 || $insertedId === '0') {
                        $errorInfo = $stmt->errorInfo();
                        error_log("Repair::create() - CRITICAL: lastInsertId() returned 0/empty despite rowCount() = {$rowsAffected}");
                        error_log("Repair::create() - PDO Error Info: " . json_encode($errorInfo));
                        error_log("Repair::create() - PDO Error Code: " . $stmt->errorCode());
                        
                        // Try to verify if the insert actually happened by checking for the record
                        $verifyStmt = $this->db->prepare("SELECT id FROM repairs_new WHERE company_id = ? AND technician_id = ? AND customer_name = ? AND customer_contact = ? ORDER BY id DESC LIMIT 1");
                        $verifyStmt->execute([$data['company_id'], $technicianId, $customerName, $customerContact]);
                        $verifyResult = $verifyStmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($verifyResult && isset($verifyResult['id'])) {
                            $insertedId = $verifyResult['id'];
                            error_log("Repair::create() - Found repair record via verification query. ID: {$insertedId}");
                        } else {
                            throw new \Exception('INSERT appeared to succeed but no ID was returned. This may indicate a database constraint violation, transaction issue, or the technician_id (ID: ' . $technicianId . ') does not exist in the users table. Please verify the technician exists and try again.');
                        }
                    }
                    
                    // Verify the insert by querying back IMMEDIATELY
                    $verifyStmt = $this->db->prepare("SELECT issue_description, repair_cost, customer_name, customer_contact, parts_cost, total_cost FROM repairs_new WHERE id = ?");
                    $verifyStmt->execute([$insertedId]);
                    $verifyResult = $verifyStmt->fetch(PDO::FETCH_ASSOC);
                    if ($verifyResult) {
                        error_log("Repair::create() - VERIFICATION QUERY RESULT (IMMEDIATE):");
                        error_log("  - issue_description: " . var_export($verifyResult['issue_description'] ?? 'NULL', true) . " (length: " . strlen($verifyResult['issue_description'] ?? '') . ")");
                        error_log("  - repair_cost: " . var_export($verifyResult['repair_cost'] ?? 'NULL', true));
                        error_log("  - customer_name: " . var_export($verifyResult['customer_name'] ?? 'NULL', true));
                        error_log("  - customer_contact: " . var_export($verifyResult['customer_contact'] ?? 'NULL', true));
                        error_log("  - parts_cost: " . var_export($verifyResult['parts_cost'] ?? 'NULL', true));
                        error_log("  - total_cost: " . var_export($verifyResult['total_cost'] ?? 'NULL', true));
                        
                        // CRITICAL: Check if data was lost and throw exception if so
                        if (empty($verifyResult['issue_description']) && !empty($issueDescription)) {
                            error_log("Repair::create() - CRITICAL ERROR: issue_description was LOST during insert!");
                            error_log("  - Expected: " . var_export($issueDescription, true));
                            error_log("  - Got: " . var_export($verifyResult['issue_description'], true));
                            throw new \Exception('CRITICAL: Issue description was lost during database insert. Expected: ' . substr($issueDescription, 0, 50) . '... but got empty value. This indicates a database issue.');
                        }
                        if (empty($verifyResult['customer_name']) && !empty($customerName)) {
                            error_log("Repair::create() - CRITICAL ERROR: customer_name was LOST during insert!");
                            error_log("  - Expected: " . var_export($customerName, true));
                            error_log("  - Got: " . var_export($verifyResult['customer_name'], true));
                            throw new \Exception('CRITICAL: Customer name was lost during database insert. Expected: ' . $customerName . ' but got empty value. This indicates a database issue.');
                        }
                        if (empty($verifyResult['customer_contact']) && !empty($customerContact)) {
                            error_log("Repair::create() - CRITICAL ERROR: customer_contact was LOST during insert!");
                            error_log("  - Expected: " . var_export($customerContact, true));
                            error_log("  - Got: " . var_export($verifyResult['customer_contact'], true));
                            throw new \Exception('CRITICAL: Customer contact was lost during database insert. Expected: ' . $customerContact . ' but got empty value. This indicates a database issue.');
                        }
                        
                        // Success verification
                        error_log("Repair::create() - ✅ VERIFICATION PASSED: All data saved correctly!");
                    } else {
                        error_log("Repair::create() - ERROR: Verification query returned no results!");
                        throw new \Exception('Repair was created but could not be verified. ID: ' . $insertedId);
                    }
                } catch (\Exception $e) {
                    error_log("Repair::create() - SQL ERROR: " . $e->getMessage());
                    error_log("Repair::create() - SQL ERROR CODE: " . $e->getCode());
                    error_log("Repair::create() - SQL ERROR TRACE: " . $e->getTraceAsString());
                    throw $e;
                }
                error_log("============================================");
                error_log("Repair::create() - END");
                error_log("============================================");
                // Set insertedId from this path
                $insertedId = $this->db->lastInsertId();
            } else {
                $stmt = $this->db->prepare("
                    INSERT INTO repairs_new (
                        company_id, technician_id, product_id, device_brand, device_model,
                        customer_name, customer_contact, customer_id, issue_description, 
                        repair_cost, parts_cost, accessory_cost, total_cost,
                        status, payment_status, tracking_code, notes
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                // Ensure customer_name, customer_contact, and issue_description are not empty
                $customerName = trim($data['customer_name'] ?? '');
                $customerContact = trim($data['customer_contact'] ?? '');
                $issueDescription = trim($data['issue_description'] ?? '');
                
                // CRITICAL: Validate required fields BEFORE attempting insert
                if (empty($customerName)) {
                    error_log("Repair::create() ERROR - customer_name is EMPTY!");
                    throw new \Exception('Customer name is required and cannot be empty. Data was not saved properly from the form.');
                }
                
                if (empty($customerContact)) {
                    error_log("Repair::create() ERROR - customer_contact is EMPTY!");
                    throw new \Exception('Customer contact is required and cannot be empty. Data was not saved properly from the form.');
                }
                
                if (empty($issueDescription)) {
                    error_log("Repair::create() ERROR - issue_description is EMPTY!");
                    throw new \Exception('Issue description is required and cannot be empty. Data was not saved properly from the form.');
                }
                
                // Add comprehensive logging for this path too
                error_log("Repair::create() - PATH: hasDeviceFields=true, hasLabourCost=false");
                error_log("Repair::create() - VALUES BEING INSERTED:");
                error_log("  - customer_name: " . var_export($customerName, true) . " (length: " . strlen($customerName) . ")");
                error_log("  - customer_contact: " . var_export($customerContact, true) . " (length: " . strlen($customerContact) . ")");
                error_log("  - issue_description: " . var_export($issueDescription, true) . " (length: " . strlen($issueDescription) . ")");
                error_log("  - repair_cost: " . var_export($data['repair_cost'] ?? 0, true));
                
                $executeValues = [
                    $data['company_id'],
                    $technicianId,
                    $data['product_id'] ?? null,
                    $data['device_brand'] ?? null,
                    $data['device_model'] ?? null,
                    $customerName,
                    $customerContact,
                    $data['customer_id'] ?? null,
                    $issueDescription,
                    $data['repair_cost'] ?? 0,
                    $data['parts_cost'] ?? 0,
                    $data['accessory_cost'] ?? 0,
                    $data['total_cost'] ?? 0,
                    $data['status'] ?? 'pending',
                    $data['payment_status'],
                    $data['tracking_code'] ?? $this->generateTrackingCode(),
                    $data['notes'] ?? null
                ];
                
                try {
                    // Execute the insert
                    $executeResult = $stmt->execute($executeValues);
                    
                    // Always check error info, even if execute() returns true
                    $errorInfo = $stmt->errorInfo();
                    $errorCode = $stmt->errorCode();
                    
                    // Check if execute succeeded
                    if ($executeResult === false || $errorCode !== '00000') {
                        error_log("Repair::create() - Execute failed. Error Code: {$errorCode}, Error Info: " . json_encode($errorInfo));
                        
                        // Check for specific constraint violations
                        if ($errorCode == '23000') {
                            $errorMessage = $errorInfo[2] ?? 'Unknown constraint error';
                            if (strpos($errorMessage, 'tracking_code') !== false) {
                                throw new \Exception('Failed to create repair: Tracking code already exists. Please try again.');
                            } elseif (strpos($errorMessage, 'technician_id') !== false || strpos($errorMessage, 'FOREIGN KEY') !== false) {
                                throw new \Exception('Failed to create repair: Invalid technician ID. The selected technician (ID: ' . $technicianId . ') does not exist in the users table.');
                            } elseif (strpos($errorMessage, 'company_id') !== false) {
                                throw new \Exception('Failed to create repair: Invalid company ID.');
                            } else {
                                throw new \Exception('Failed to create repair: Database constraint violation. ' . $errorMessage);
                            }
                        }
                        
                        throw new \Exception('Failed to execute INSERT statement: ' . ($errorInfo[2] ?? 'Unknown error (Code: ' . $errorCode . ')'));
                    }
                    
                    // Check if any rows were affected
                    $rowsAffected = $stmt->rowCount();
                    if ($rowsAffected === 0) {
                        error_log("Repair::create() - No rows affected! Error Code: {$errorCode}, Error Info: " . json_encode($errorInfo));
                        
                        // Check for constraint violations even if execute() returned true
                        if ($errorCode == '23000' || (isset($errorInfo[2]) && strpos($errorInfo[2], 'Duplicate') !== false)) {
                            $errorMessage = $errorInfo[2] ?? 'Unknown constraint error';
                            if (strpos($errorMessage, 'tracking_code') !== false) {
                                throw new \Exception('Failed to create repair: Tracking code already exists. Please try again.');
                            } elseif (strpos($errorMessage, 'technician_id') !== false || strpos($errorMessage, 'FOREIGN KEY') !== false) {
                                throw new \Exception('Failed to create repair: Invalid technician ID. The selected technician (ID: ' . $technicianId . ') does not exist in the users table.');
                            } else {
                                throw new \Exception('Failed to create repair: Database constraint violation. ' . $errorMessage);
                            }
                        }
                        
                        throw new \Exception('INSERT statement executed but no rows were affected. This may indicate a constraint violation or invalid data. Error: ' . ($errorInfo[2] ?? 'Unknown'));
                    }
                    
                    // Get the inserted ID - must be called on the same connection that executed the INSERT
                    $insertedId = $this->db->lastInsertId();
                    error_log("Repair::create() - Inserted repair with ID: {$insertedId} (type: " . gettype($insertedId) . ")");
                    
                    // Double-check: if lastInsertId is 0 or empty, the insert likely failed
                    if (empty($insertedId) || $insertedId == 0 || $insertedId === '0') {
                        $errorInfo = $stmt->errorInfo();
                        error_log("Repair::create() - CRITICAL: lastInsertId() returned 0/empty despite rowCount() = {$rowsAffected}");
                        error_log("Repair::create() - PDO Error Info: " . json_encode($errorInfo));
                        error_log("Repair::create() - PDO Error Code: " . $stmt->errorCode());
                        
                        // Try to verify if the insert actually happened by checking for the record
                        $verifyStmt = $this->db->prepare("SELECT id FROM repairs_new WHERE company_id = ? AND technician_id = ? AND customer_name = ? AND customer_contact = ? ORDER BY id DESC LIMIT 1");
                        $verifyStmt->execute([$data['company_id'], $technicianId, $customerName, $customerContact]);
                        $verifyResult = $verifyStmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($verifyResult && isset($verifyResult['id'])) {
                            $insertedId = $verifyResult['id'];
                            error_log("Repair::create() - Found repair record via verification query. ID: {$insertedId}");
                        } else {
                            throw new \Exception('INSERT appeared to succeed but no ID was returned. This may indicate a database constraint violation, transaction issue, or the technician_id (ID: ' . $technicianId . ') does not exist in the users table. Please verify the technician exists and try again.');
                        }
                    }
                    
                    // Verify immediately
                    $verifyStmt = $this->db->prepare("SELECT issue_description, repair_cost, customer_name, customer_contact FROM repairs_new WHERE id = ?");
                    $verifyStmt->execute([$insertedId]);
                    $verifyResult = $verifyStmt->fetch(PDO::FETCH_ASSOC);
                    if ($verifyResult) {
                        error_log("Repair::create() - VERIFICATION:");
                        error_log("  - issue_description: " . var_export($verifyResult['issue_description'] ?? 'NULL', true));
                        error_log("  - customer_name: " . var_export($verifyResult['customer_name'] ?? 'NULL', true));
                    }
                } catch (\Exception $e) {
                    error_log("Repair::create() - SQL ERROR: " . $e->getMessage());
                    throw $e;
                }
                // Set insertedId from this path
                $insertedId = $this->db->lastInsertId();
            }
        } else {
            error_log("Repair::create() - Using PATH: hasDeviceFields=FALSE (device_brand/device_model columns not found!)");
            // Check if labour_cost column exists
            $checkLabourCost = $this->db->query("SHOW COLUMNS FROM repairs_new LIKE 'labour_cost'");
            $hasLabourCost = $checkLabourCost && $checkLabourCost->rowCount() > 0;
            error_log("Repair::create() - hasLabourCost: " . ($hasLabourCost ? 'TRUE' : 'FALSE'));
            
            if ($hasLabourCost) {
                error_log("Repair::create() - Using PATH: hasDeviceFields=FALSE, hasLabourCost=TRUE");
                $stmt = $this->db->prepare("
                    INSERT INTO repairs_new (
                        company_id, technician_id, product_id, customer_name, customer_contact,
                        customer_id, issue_description, repair_cost, labour_cost, parts_cost, accessory_cost, total_cost,
                        status, payment_status, tracking_code, notes
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                // Calculate labour_cost if not provided (default to 50% of repair_cost)
                $labourCost = $data['labour_cost'] ?? null;
                if ($labourCost === null && isset($data['repair_cost'])) {
                    $labourCost = floatval($data['repair_cost']) * 0.5; // Default 50% of repair cost
                }
                $labourCost = $labourCost ?? 0;
                
                // Ensure customer_name, customer_contact, and issue_description are not empty
                $customerName = trim($data['customer_name'] ?? '');
                $customerContact = trim($data['customer_contact'] ?? '');
                $issueDescription = trim($data['issue_description'] ?? '');
                
                // CRITICAL: Validate required fields BEFORE attempting insert
                if (empty($customerName)) {
                    error_log("Repair::create() ERROR - customer_name is EMPTY!");
                    throw new \Exception('Customer name is required and cannot be empty. Data was not saved properly from the form.');
                }
                
                if (empty($customerContact)) {
                    error_log("Repair::create() ERROR - customer_contact is EMPTY!");
                    throw new \Exception('Customer contact is required and cannot be empty. Data was not saved properly from the form.');
                }
                
                if (empty($issueDescription)) {
                    error_log("Repair::create() ERROR - issue_description is EMPTY!");
                    throw new \Exception('Issue description is required and cannot be empty. Data was not saved properly from the form.');
                }
                
                try {
                    // Execute the insert
                    $executeResult = $stmt->execute([
                        $data['company_id'],
                        $technicianId, // Use validated and cast technician_id
                        $data['product_id'] ?? null,
                        $customerName, // Use validated and trimmed value
                        $customerContact, // Use validated and trimmed value
                        $data['customer_id'] ?? null,
                        $issueDescription, // Use validated and trimmed value
                        $data['repair_cost'] ?? 0,
                        $labourCost,
                        $data['parts_cost'] ?? 0,
                        $data['accessory_cost'] ?? 0,
                        $data['total_cost'] ?? 0,
                        $data['status'] ?? 'pending',
                        $data['payment_status'], // Always 'paid' for new bookings (enforced above)
                        $data['tracking_code'] ?? $this->generateTrackingCode(),
                        $data['notes'] ?? null
                    ]);
                    
                    // Always check error info, even if execute() returns true
                    $errorInfo = $stmt->errorInfo();
                    $errorCode = $stmt->errorCode();
                    
                    // Check if execute succeeded
                    if ($executeResult === false || $errorCode !== '00000') {
                        error_log("Repair::create() - Execute failed. Error Code: {$errorCode}, Error Info: " . json_encode($errorInfo));
                        
                        // Check for specific constraint violations
                        if ($errorCode == '23000') {
                            $errorMessage = $errorInfo[2] ?? 'Unknown constraint error';
                            if (strpos($errorMessage, 'tracking_code') !== false) {
                                throw new \Exception('Failed to create repair: Tracking code already exists. Please try again.');
                            } elseif (strpos($errorMessage, 'technician_id') !== false || strpos($errorMessage, 'FOREIGN KEY') !== false) {
                                throw new \Exception('Failed to create repair: Invalid technician ID. The selected technician (ID: ' . $technicianId . ') does not exist in the users table.');
                            } elseif (strpos($errorMessage, 'company_id') !== false) {
                                throw new \Exception('Failed to create repair: Invalid company ID.');
                            } else {
                                throw new \Exception('Failed to create repair: Database constraint violation. ' . $errorMessage);
                            }
                        }
                        
                        throw new \Exception('Failed to execute INSERT statement: ' . ($errorInfo[2] ?? 'Unknown error (Code: ' . $errorCode . ')'));
                    }
                    
                    // Check if any rows were affected
                    $rowsAffected = $stmt->rowCount();
                    if ($rowsAffected === 0) {
                        error_log("Repair::create() - No rows affected! Error Code: {$errorCode}, Error Info: " . json_encode($errorInfo));
                        
                        // Check for constraint violations even if execute() returned true
                        if ($errorCode == '23000' || (isset($errorInfo[2]) && strpos($errorInfo[2], 'Duplicate') !== false)) {
                            $errorMessage = $errorInfo[2] ?? 'Unknown constraint error';
                            if (strpos($errorMessage, 'tracking_code') !== false) {
                                throw new \Exception('Failed to create repair: Tracking code already exists. Please try again.');
                            } elseif (strpos($errorMessage, 'technician_id') !== false || strpos($errorMessage, 'FOREIGN KEY') !== false) {
                                throw new \Exception('Failed to create repair: Invalid technician ID. The selected technician (ID: ' . $technicianId . ') does not exist in the users table.');
                            } else {
                                throw new \Exception('Failed to create repair: Database constraint violation. ' . $errorMessage);
                            }
                        }
                        
                        throw new \Exception('INSERT statement executed but no rows were affected. This may indicate a constraint violation or invalid data. Error: ' . ($errorInfo[2] ?? 'Unknown'));
                    }
                    
                    // Get the inserted ID - must be called on the same connection that executed the INSERT
                    $insertedId = $this->db->lastInsertId();
                    error_log("Repair::create() - Inserted repair with ID: {$insertedId} (type: " . gettype($insertedId) . ")");
                    
                    // Double-check: if lastInsertId is 0 or empty, the insert likely failed
                    if (empty($insertedId) || $insertedId == 0 || $insertedId === '0') {
                        $errorInfo = $stmt->errorInfo();
                        error_log("Repair::create() - CRITICAL: lastInsertId() returned 0/empty despite rowCount() = {$rowsAffected}");
                        error_log("Repair::create() - PDO Error Info: " . json_encode($errorInfo));
                        error_log("Repair::create() - PDO Error Code: " . $stmt->errorCode());
                        
                        // Try to verify if the insert actually happened by checking for the record
                        $verifyStmt = $this->db->prepare("SELECT id FROM repairs_new WHERE company_id = ? AND technician_id = ? AND customer_name = ? AND customer_contact = ? ORDER BY id DESC LIMIT 1");
                        $verifyStmt->execute([$data['company_id'], $technicianId, $customerName, $customerContact]);
                        $verifyResult = $verifyStmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($verifyResult && isset($verifyResult['id'])) {
                            $insertedId = $verifyResult['id'];
                            error_log("Repair::create() - Found repair record via verification query. ID: {$insertedId}");
                        } else {
                            throw new \Exception('INSERT appeared to succeed but no ID was returned. This may indicate a database constraint violation, transaction issue, or the technician_id (ID: ' . $technicianId . ') does not exist in the users table. Please verify the technician exists and try again.');
                        }
                    }
                } catch (\Exception $e) {
                    error_log("Repair::create() - SQL ERROR: " . $e->getMessage());
                    throw $e;
                }
                
                // Verify immediately
                $verifyStmt = $this->db->prepare("SELECT issue_description, repair_cost, customer_name, customer_contact FROM repairs_new WHERE id = ?");
                $verifyStmt->execute([$insertedId]);
                $verifyResult = $verifyStmt->fetch(PDO::FETCH_ASSOC);
                if ($verifyResult) {
                    error_log("Repair::create() - VERIFICATION (path: hasDeviceFields=FALSE, hasLabourCost=TRUE):");
                    error_log("  - issue_description: " . var_export($verifyResult['issue_description'] ?? 'NULL', true));
                    error_log("  - customer_name: " . var_export($verifyResult['customer_name'] ?? 'NULL', true));
                    error_log("  - repair_cost: " . var_export($verifyResult['repair_cost'] ?? 'NULL', true));
                }
            } else {
                error_log("Repair::create() - Using PATH: hasDeviceFields=FALSE, hasLabourCost=FALSE");
                $stmt = $this->db->prepare("
                    INSERT INTO repairs_new (
                        company_id, technician_id, product_id, customer_name, customer_contact,
                        customer_id, issue_description, repair_cost, parts_cost, accessory_cost, total_cost,
                        status, payment_status, tracking_code, notes
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                // Extract and log values
                $customerName = trim($data['customer_name'] ?? '');
                $customerContact = trim($data['customer_contact'] ?? '');
                $issueDescription = trim($data['issue_description'] ?? '');
                
                // CRITICAL: Validate required fields BEFORE attempting insert
                if (empty($customerName)) {
                    error_log("Repair::create() ERROR - customer_name is EMPTY!");
                    throw new \Exception('Customer name is required and cannot be empty. Data was not saved properly from the form.');
                }
                
                if (empty($customerContact)) {
                    error_log("Repair::create() ERROR - customer_contact is EMPTY!");
                    throw new \Exception('Customer contact is required and cannot be empty. Data was not saved properly from the form.');
                }
                
                if (empty($issueDescription)) {
                    error_log("Repair::create() ERROR - issue_description is EMPTY!");
                    throw new \Exception('Issue description is required and cannot be empty. Data was not saved properly from the form.');
                }
                
                error_log("Repair::create() - VALUES BEING INSERTED (path: hasDeviceFields=FALSE, hasLabourCost=FALSE):");
                error_log("  - customer_name: " . var_export($customerName, true) . " (length: " . strlen($customerName) . ")");
                error_log("  - customer_contact: " . var_export($customerContact, true) . " (length: " . strlen($customerContact) . ")");
                error_log("  - issue_description: " . var_export($issueDescription, true) . " (length: " . strlen($issueDescription) . ")");
                error_log("  - repair_cost: " . var_export($data['repair_cost'] ?? 0, true));
                
                $executeValues = [
                    $data['company_id'],
                    $technicianId,
                    $data['product_id'] ?? null,
                    $customerName,
                    $customerContact,
                    $data['customer_id'] ?? null,
                    $issueDescription,
                    $data['repair_cost'] ?? 0,
                    $data['parts_cost'] ?? 0,
                    $data['accessory_cost'] ?? 0,
                    $data['total_cost'] ?? 0,
                    $data['status'] ?? 'pending',
                    $data['payment_status'],
                    $data['tracking_code'] ?? $this->generateTrackingCode(),
                    $data['notes'] ?? null
                ];
                
                try {
                    // Execute the insert
                    $executeResult = $stmt->execute($executeValues);
                    
                    // Always check error info, even if execute() returns true
                    $errorInfo = $stmt->errorInfo();
                    $errorCode = $stmt->errorCode();
                    
                    // Check if execute succeeded
                    if ($executeResult === false || $errorCode !== '00000') {
                        error_log("Repair::create() - Execute failed. Error Code: {$errorCode}, Error Info: " . json_encode($errorInfo));
                        
                        // Check for specific constraint violations
                        if ($errorCode == '23000') {
                            $errorMessage = $errorInfo[2] ?? 'Unknown constraint error';
                            if (strpos($errorMessage, 'tracking_code') !== false) {
                                throw new \Exception('Failed to create repair: Tracking code already exists. Please try again.');
                            } elseif (strpos($errorMessage, 'technician_id') !== false || strpos($errorMessage, 'FOREIGN KEY') !== false) {
                                throw new \Exception('Failed to create repair: Invalid technician ID. The selected technician (ID: ' . $technicianId . ') does not exist in the users table.');
                            } elseif (strpos($errorMessage, 'company_id') !== false) {
                                throw new \Exception('Failed to create repair: Invalid company ID.');
                            } else {
                                throw new \Exception('Failed to create repair: Database constraint violation. ' . $errorMessage);
                            }
                        }
                        
                        throw new \Exception('Failed to execute INSERT statement: ' . ($errorInfo[2] ?? 'Unknown error (Code: ' . $errorCode . ')'));
                    }
                    
                    // Check if any rows were affected
                    $rowsAffected = $stmt->rowCount();
                    if ($rowsAffected === 0) {
                        error_log("Repair::create() - No rows affected! Error Code: {$errorCode}, Error Info: " . json_encode($errorInfo));
                        
                        // Check for constraint violations even if execute() returned true
                        if ($errorCode == '23000' || (isset($errorInfo[2]) && strpos($errorInfo[2], 'Duplicate') !== false)) {
                            $errorMessage = $errorInfo[2] ?? 'Unknown constraint error';
                            if (strpos($errorMessage, 'tracking_code') !== false) {
                                throw new \Exception('Failed to create repair: Tracking code already exists. Please try again.');
                            } elseif (strpos($errorMessage, 'technician_id') !== false || strpos($errorMessage, 'FOREIGN KEY') !== false) {
                                throw new \Exception('Failed to create repair: Invalid technician ID. The selected technician (ID: ' . $technicianId . ') does not exist in the users table.');
                            } else {
                                throw new \Exception('Failed to create repair: Database constraint violation. ' . $errorMessage);
                            }
                        }
                        
                        throw new \Exception('INSERT statement executed but no rows were affected. This may indicate a constraint violation or invalid data. Error: ' . ($errorInfo[2] ?? 'Unknown'));
                    }
                    
                    // Get the inserted ID - must be called on the same connection that executed the INSERT
                    $insertedId = $this->db->lastInsertId();
                    error_log("Repair::create() - Inserted repair with ID: {$insertedId} (type: " . gettype($insertedId) . ")");
                    
                    // Double-check: if lastInsertId is 0 or empty, the insert likely failed
                    if (empty($insertedId) || $insertedId == 0 || $insertedId === '0') {
                        $errorInfo = $stmt->errorInfo();
                        error_log("Repair::create() - CRITICAL: lastInsertId() returned 0/empty despite rowCount() = {$rowsAffected}");
                        error_log("Repair::create() - PDO Error Info: " . json_encode($errorInfo));
                        error_log("Repair::create() - PDO Error Code: " . $stmt->errorCode());
                        
                        // Try to verify if the insert actually happened by checking for the record
                        $verifyStmt = $this->db->prepare("SELECT id FROM repairs_new WHERE company_id = ? AND technician_id = ? AND customer_name = ? AND customer_contact = ? ORDER BY id DESC LIMIT 1");
                        $verifyStmt->execute([$data['company_id'], $technicianId, $customerName, $customerContact]);
                        $verifyResult = $verifyStmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($verifyResult && isset($verifyResult['id'])) {
                            $insertedId = $verifyResult['id'];
                            error_log("Repair::create() - Found repair record via verification query. ID: {$insertedId}");
                        } else {
                            throw new \Exception('INSERT appeared to succeed but no ID was returned. This may indicate a database constraint violation, transaction issue, or the technician_id (ID: ' . $technicianId . ') does not exist in the users table. Please verify the technician exists and try again.');
                        }
                    }
                    
                    // Verify immediately
                    $verifyStmt = $this->db->prepare("SELECT issue_description, repair_cost, customer_name, customer_contact FROM repairs_new WHERE id = ?");
                    $verifyStmt->execute([$insertedId]);
                    $verifyResult = $verifyStmt->fetch(PDO::FETCH_ASSOC);
                    if ($verifyResult) {
                        error_log("Repair::create() - VERIFICATION (path: hasDeviceFields=FALSE, hasLabourCost=FALSE):");
                        error_log("  - issue_description: " . var_export($verifyResult['issue_description'] ?? 'NULL', true));
                        error_log("  - customer_name: " . var_export($verifyResult['customer_name'] ?? 'NULL', true));
                        error_log("  - repair_cost: " . var_export($verifyResult['repair_cost'] ?? 'NULL', true));
                    }
                } catch (\Exception $e) {
                    error_log("Repair::create() - SQL ERROR: " . $e->getMessage());
                    throw $e;
                }
                // Set insertedId from this path (already set above)
            }
        }
        
        // Validate that we got a valid ID from one of the code paths
        if ($insertedId === null) {
            error_log("Repair::create() - ERROR: No code path executed! insertedId is null.");
            error_log("Repair::create() - hasDeviceFields: " . ($hasDeviceFields ?? 'NOT SET'));
            throw new \Exception('Failed to create repair: No insert statement was executed. This indicates a logic error in the code paths.');
        }
        
        if (!$insertedId || $insertedId <= 0 || $insertedId === '0') {
            error_log("Repair::create() - ERROR: Invalid insertedId value: " . var_export($insertedId, true));
            error_log("Repair::create() - Attempting to get lastInsertId() again: " . $this->db->lastInsertId());
            error_log("Repair::create() - Technician ID used: " . var_export($technicianId, true));
            error_log("Repair::create() - Company ID used: " . var_export($data['company_id'] ?? 'NOT SET', true));
            
            // Try one more verification query
            $finalVerifyStmt = $this->db->prepare("SELECT id FROM repairs_new WHERE company_id = ? AND technician_id = ? ORDER BY id DESC LIMIT 1");
            $finalVerifyStmt->execute([$data['company_id'], $technicianId]);
            $finalVerifyResult = $finalVerifyStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($finalVerifyResult && isset($finalVerifyResult['id'])) {
                $insertedId = $finalVerifyResult['id'];
                error_log("Repair::create() - Found repair via final verification. ID: {$insertedId}");
            } else {
                throw new \Exception('Failed to create repair: Invalid ID returned from database. ID: ' . var_export($insertedId, true) . '. This may indicate the insert failed silently, possibly due to a foreign key constraint violation (technician_id: ' . $technicianId . ' does not exist in users table) or a database transaction issue. Please verify the technician exists and try again.');
            }
        }
        
        error_log("Repair::create() - Successfully created repair with ID: {$insertedId}");
        return $insertedId;
    }
    
    /**
     * Check if device_brand and device_model columns exist
     */
    private function checkDeviceColumnsExist() {
        try {
            $stmt = $this->db->query("SHOW COLUMNS FROM repairs_new LIKE 'device_brand'");
            return $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Update a repair
     */
    public function update($id, array $data, $company_id) {
        // CRITICAL: Only update fields that are provided in $data
        // This prevents accidentally clearing fields when only updating specific values (e.g., notes)
        $fields = [];
        $values = [];
        
        // Check if device_brand and device_model columns exist
        $hasDeviceFields = $this->checkDeviceColumnsExist();
        
        // Build dynamic UPDATE query based on provided fields
        if (isset($data['technician_id'])) {
            $fields[] = 'technician_id = ?';
            $values[] = $data['technician_id'];
        }
        if (isset($data['product_id'])) {
            $fields[] = 'product_id = ?';
            $values[] = $data['product_id'];
        }
        if ($hasDeviceFields) {
            if (isset($data['device_brand'])) {
                $fields[] = 'device_brand = ?';
                $values[] = $data['device_brand'];
            }
            if (isset($data['device_model'])) {
                $fields[] = 'device_model = ?';
                $values[] = $data['device_model'];
            }
        }
        if (isset($data['customer_name'])) {
            $fields[] = 'customer_name = ?';
            $values[] = $data['customer_name'];
        }
        if (isset($data['customer_contact'])) {
            $fields[] = 'customer_contact = ?';
            $values[] = $data['customer_contact'];
        }
        if (isset($data['customer_id'])) {
            $fields[] = 'customer_id = ?';
            $values[] = $data['customer_id'];
        }
        if (isset($data['issue_description'])) {
            $fields[] = 'issue_description = ?';
            $values[] = $data['issue_description'];
        }
        if (isset($data['repair_cost'])) {
            $fields[] = 'repair_cost = ?';
            $values[] = $data['repair_cost'];
        }
        if (isset($data['parts_cost'])) {
            $fields[] = 'parts_cost = ?';
            $values[] = $data['parts_cost'];
        }
        if (isset($data['accessory_cost'])) {
            $fields[] = 'accessory_cost = ?';
            $values[] = $data['accessory_cost'];
        }
        if (isset($data['total_cost'])) {
            $fields[] = 'total_cost = ?';
            $values[] = $data['total_cost'];
        }
        if (isset($data['status'])) {
            $fields[] = 'status = ?';
            $values[] = $data['status'];
        }
        if (isset($data['payment_status'])) {
            $fields[] = 'payment_status = ?';
            $values[] = $data['payment_status'];
        }
        if (isset($data['notes'])) {
            $fields[] = 'notes = ?';
            $values[] = $data['notes'];
        }
        
        // If no fields to update, return false
        if (empty($fields)) {
            error_log("Repair::update() - No fields to update for repair ID: {$id}");
            return false;
        }
        
        // Add WHERE clause values
        $values[] = $id;
        $values[] = $company_id;
        
        // Build and execute UPDATE query
        $sql = "UPDATE repairs_new SET " . implode(', ', $fields) . " WHERE id = ? AND company_id = ?";
        $stmt = $this->db->prepare($sql);
        
        error_log("Repair::update() - Updating repair ID: {$id}, Fields: " . implode(', ', array_keys($data)));
        error_log("Repair::update() - SQL: {$sql}");
        error_log("Repair::update() - Values: " . json_encode($values));
        
        return $stmt->execute($values);
    }
    
    /**
     * Update specific repair fields (for partial updates)
     */
    public function updateFields($id, array $data, $company_id) {
        $fields = [];
        $values = [];
        
        $allowedFields = [
            'issue_description', 'repair_cost', 'parts_cost', 'accessory_cost', 
            'total_cost', 'customer_name', 'customer_contact', 'device_brand', 
            'device_model', 'notes', 'status', 'payment_status'
        ];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "{$field} = ?";
                if (in_array($field, ['repair_cost', 'parts_cost', 'accessory_cost', 'total_cost'])) {
                    $values[] = floatval($data[$field]);
                } else {
                    $values[] = $data[$field];
                }
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $values[] = $id;
        $values[] = $company_id;
        
        $sql = "UPDATE repairs_new SET " . implode(', ', $fields) . " WHERE id = ? AND company_id = ?";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute($values);
    }

    /**
     * Find repair by ID
     */
    public function find($id, $company_id) {
        // Check if labour_cost column exists
        $checkLabourCost = $this->db->query("SHOW COLUMNS FROM repairs_new LIKE 'labour_cost'");
        $hasLabourCost = $checkLabourCost && $checkLabourCost->rowCount() > 0;
        
        $labourCostSelect = $hasLabourCost ? ', r.labour_cost' : '';
        
        // Build customer_contact selection - prioritize repair record
        $contactSelect = "COALESCE(
            NULLIF(TRIM(r.customer_contact), ''),
            NULLIF(TRIM(c.phone_number), ''),
            ''
        ) as customer_contact_merged";
        
        $stmt = $this->db->prepare("
            SELECT r.*, 
                   r.issue_description,
                   r.repair_cost,
                   r.parts_cost,
                   r.accessory_cost,
                   r.total_cost,
                   u.full_name as technician_name, 
                   p.name as product_name,
                   c.full_name as customer_name_from_table, 
                   c.phone_number,
                   COALESCE(
                       NULLIF(TRIM(r.customer_name), ''),
                       NULLIF(TRIM(c.full_name), ''),
                       ''
                   ) as customer_name_merged,
                   {$contactSelect}
                   {$labourCostSelect}
            FROM repairs_new r
            LEFT JOIN users u ON r.technician_id = u.id
            LEFT JOIN products p ON r.product_id = p.id
            LEFT JOIN customers c ON r.customer_id = c.id
            WHERE r.id = ? AND r.company_id = ?
            LIMIT 1
        ");
        $stmt->execute([$id, $company_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // ============================================
        // LOGGING IN Repair::find()
        // ============================================
        error_log("============================================");
        error_log("Repair::find() - Retrieving repair ID: {$id}, company_id: {$company_id}");
        error_log("============================================");
        if ($result) {
            error_log("Repair::find() - RAW DATABASE RESULT:");
            error_log("  - r.customer_name (raw): " . var_export($result['customer_name'] ?? 'NOT IN RESULT', true) . " (length: " . strlen($result['customer_name'] ?? '') . ")");
            error_log("  - r.customer_contact (raw): " . var_export($result['customer_contact'] ?? 'NOT IN RESULT', true) . " (length: " . strlen($result['customer_contact'] ?? '') . ")");
            error_log("  - r.issue_description (raw): " . var_export($result['issue_description'] ?? 'NOT IN RESULT', true) . " (length: " . strlen($result['issue_description'] ?? '') . ")");
            error_log("  - customer_name_merged: " . var_export($result['customer_name_merged'] ?? 'NOT IN RESULT', true));
            error_log("  - customer_contact_merged: " . var_export($result['customer_contact_merged'] ?? 'NOT IN RESULT', true));
            error_log("  - repair_cost: " . var_export($result['repair_cost'] ?? 'NOT IN RESULT', true));
            error_log("  - parts_cost: " . var_export($result['parts_cost'] ?? 'NOT IN RESULT', true));
            error_log("  - total_cost: " . var_export($result['total_cost'] ?? 'NOT IN RESULT', true));
        } else {
            error_log("Repair::find() - ERROR: No result found for repair ID: {$id}");
        }
        
        if ($result) {
            // Normalize payment_status - all repairs are paid at booking time, so default to 'paid'
            if (isset($result['payment_status'])) {
                $result['payment_status'] = strtolower(trim($result['payment_status']));
                // Handle empty or null values - default to 'paid' since all bookings are paid
                if (empty($result['payment_status']) || $result['payment_status'] === 'null' || $result['payment_status'] === 'unpaid') {
                    $result['payment_status'] = 'paid';
                }
            } else {
                // Default to paid if payment_status is not set (all bookings are paid)
                $result['payment_status'] = 'paid';
            }
            
            // IMPORTANT: Use merged customer_name and customer_contact from SQL COALESCE
            // This prioritizes repair record data, then falls back to customer table
            // Since every booking has customer details, we should always have data
            $customerName = '';
            // First check the merged field (from SQL COALESCE)
            if (isset($result['customer_name_merged']) && !empty(trim($result['customer_name_merged']))) {
                $customerName = trim($result['customer_name_merged']);
            } 
            // Then check direct customer_name from repair record (may be empty but we want to preserve it)
            elseif (isset($result['customer_name'])) {
                $customerName = trim($result['customer_name']);
            }
            // Then check customer table
            elseif (isset($result['customer_name_from_table']) && !empty(trim($result['customer_name_from_table']))) {
                $customerName = trim($result['customer_name_from_table']);
            }
            
            // Only use "Unknown Customer" if we truly have no data
            // But preserve empty string if it was intentionally empty (don't override with "Unknown Customer")
            if (empty($customerName) && !isset($result['customer_name'])) {
                $customerName = 'Unknown Customer';
            }
            $result['customer_name'] = $customerName;
            
            // Ensure customer_contact is properly set - use merged contact
            $contact = '';
            // First check the merged field (from SQL COALESCE)
            if (isset($result['customer_contact_merged']) && !empty(trim($result['customer_contact_merged']))) {
                $contact = trim($result['customer_contact_merged']);
            }
            // Then check direct customer_contact from repair record (preserve even if empty)
            elseif (isset($result['customer_contact'])) {
                $contact = trim($result['customer_contact']);
            }
            // Then check customer table phone_number
            elseif (isset($result['phone_number']) && !empty(trim($result['phone_number']))) {
                $contact = trim($result['phone_number']);
            }
            $result['customer_contact'] = $contact;
            
            // Ensure issue_description is preserved - NEVER use notes as fallback
            // Always preserve the actual issue_description from the repair record
            if (isset($result['issue_description'])) {
                // Preserve the value even if empty (don't override)
                $result['issue_description'] = trim($result['issue_description']);
            } elseif (isset($result['issue']) && !empty(trim($result['issue']))) {
                // Check for alternative column names
                $result['issue_description'] = trim($result['issue']);
            } else {
                // Set to empty string if truly missing (view will handle display)
                $result['issue_description'] = '';
            }
            
            // Ensure repair_cost is always set (default to 0 if NULL)
            if (!isset($result['repair_cost']) || $result['repair_cost'] === null) {
                $result['repair_cost'] = 0;
            } else {
                $result['repair_cost'] = floatval($result['repair_cost']);
            }
            
            // Ensure parts_cost is always set
            if (!isset($result['parts_cost']) || $result['parts_cost'] === null) {
                $result['parts_cost'] = 0;
            } else {
                $result['parts_cost'] = floatval($result['parts_cost']);
            }
            
            // Ensure accessory_cost is always set
            if (!isset($result['accessory_cost']) || $result['accessory_cost'] === null) {
                $result['accessory_cost'] = 0;
            } else {
                $result['accessory_cost'] = floatval($result['accessory_cost']);
            }
            
            // Ensure total_cost is always set
            if (!isset($result['total_cost']) || $result['total_cost'] === null) {
                $result['total_cost'] = 0;
            } else {
                $result['total_cost'] = floatval($result['total_cost']);
            }
            
            error_log("Repair::find() - FINAL PROCESSED RESULT:");
            error_log("  - issue_description: " . var_export($result['issue_description'] ?? 'NOT SET', true) . " (length: " . strlen($result['issue_description'] ?? '') . ")");
            error_log("  - repair_cost: " . var_export($result['repair_cost'] ?? 'NOT SET', true));
            error_log("  - customer_name: " . var_export($result['customer_name'] ?? 'NOT SET', true));
            error_log("  - customer_contact: " . var_export($result['customer_contact'] ?? 'NOT SET', true));
            error_log("============================================");
        }
        
        return $result;
    }

    /**
     * Find repairs by company
     */
    public function findByCompany($company_id, $limit = 100, $status = null, $dateFrom = null, $dateTo = null) {
        // Check which repairs table exists
        $checkRepairsNew = $this->db->query("SHOW TABLES LIKE 'repairs_new'");
        $hasRepairsNew = $checkRepairsNew && $checkRepairsNew->rowCount() > 0;
        $repairsTable = $hasRepairsNew ? 'repairs_new' : 'repairs';
        $statusColumn = $hasRepairsNew ? 'status' : 'repair_status';
        $technicianColumn = $hasRepairsNew ? 'technician_id' : 'technician_id';
        
        // Build customer_contact selection based on which table we're using
        if ($hasRepairsNew) {
            // repairs_new table has customer_contact column
            $contactSelect = "COALESCE(
                NULLIF(TRIM(r.customer_contact), ''),
                NULLIF(TRIM(c.phone_number), ''),
                ''
            ) as customer_contact_merged";
        } else {
            // repairs table doesn't have customer_contact, only customer_id
            $contactSelect = "COALESCE(
                NULLIF(TRIM(c.phone_number), ''),
                ''
            ) as customer_contact_merged";
        }
        
        $sql = "
            SELECT r.*, u.full_name as technician_name, p.name as product_name,
                   c.full_name as customer_name_from_table, c.phone_number,
                   COALESCE(
                       NULLIF(TRIM(r.customer_name), ''),
                       NULLIF(TRIM(c.full_name), ''),
                       ''
                   ) as customer_name_merged,
                   {$contactSelect}
            FROM {$repairsTable} r
            LEFT JOIN users u ON r.{$technicianColumn} = u.id
            LEFT JOIN products p ON r.product_id = p.id
            LEFT JOIN customers c ON r.customer_id = c.id
            WHERE r.company_id = ?
        ";
        $params = [$company_id];
        
        // Add date range filter
        if ($dateFrom && $dateTo) {
            $sql .= " AND DATE(r.created_at) >= ? AND DATE(r.created_at) <= ?";
            $params[] = $dateFrom;
            $params[] = $dateTo;
        } elseif ($dateFrom) {
            $sql .= " AND DATE(r.created_at) >= ?";
            $params[] = $dateFrom;
        } elseif ($dateTo) {
            $sql .= " AND DATE(r.created_at) <= ?";
            $params[] = $dateTo;
        }
        
        if ($status) {
            // Normalize status: old table uses uppercase, new table uses lowercase
            if ($hasRepairsNew) {
                // repairs_new uses lowercase: pending, in_progress, completed, delivered, failed
                $normalizedStatus = strtolower($status);
            } else {
                // repairs uses uppercase: PENDING, IN_PROGRESS, COMPLETED, DELIVERED, CANCELLED
                $normalizedStatus = strtoupper($status);
                // Map 'failed' to 'CANCELLED' for old table if needed
                if ($normalizedStatus === 'FAILED') {
                    $normalizedStatus = 'CANCELLED';
                }
            }
            $sql .= " AND r.{$statusColumn} = ?";
            $params[] = $normalizedStatus;
        }
        
        $sql .= " ORDER BY r.created_at DESC LIMIT " . intval($limit);
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Normalize status values and customer name in results for consistency
        foreach ($results as &$result) {
            if (!$hasRepairsNew && isset($result['repair_status'])) {
                $result['status'] = strtolower($result['repair_status']);
            } elseif ($hasRepairsNew && isset($result['status'])) {
                // Ensure status is lowercase
                $result['status'] = strtolower($result['status']);
            }
            
            // Normalize payment_status (handle both old and new table formats)
            // All repairs are paid at booking time, so default to 'paid'
            if (isset($result['payment_status'])) {
                $result['payment_status'] = strtolower(trim($result['payment_status']));
                // Handle empty or null values - default to 'paid' since all bookings are paid
                if (empty($result['payment_status']) || $result['payment_status'] === 'null' || $result['payment_status'] === 'unpaid') {
                    $result['payment_status'] = 'paid';
                }
            } else {
                // Default to paid if payment_status is not set (all bookings are paid)
                $result['payment_status'] = 'paid';
            }
            
            // IMPORTANT: Use merged customer_name and customer_contact from SQL COALESCE
            // This prioritizes repair record data, then falls back to customer table
            // Since every booking has customer details, we should always have data
            $customerName = '';
            if (isset($result['customer_name_merged']) && !empty(trim($result['customer_name_merged']))) {
                // Use merged customer_name (prioritizes repair record, then customer table)
                $customerName = trim($result['customer_name_merged']);
            } elseif (isset($result['customer_name']) && !empty(trim($result['customer_name']))) {
                // Fall back to direct customer_name from repair record
                $customerName = trim($result['customer_name']);
            } elseif (isset($result['customer_name_from_table']) && !empty(trim($result['customer_name_from_table']))) {
                // Fall back to customer table
                $customerName = trim($result['customer_name_from_table']);
            }
            // Only show "Unknown Customer" if we truly have no data (shouldn't happen for bookings)
            $result['customer_name'] = $customerName ?: 'Unknown Customer';
            
            // Ensure customer_contact is properly set - use merged contact
            $contact = '';
            if (isset($result['customer_contact_merged']) && !empty(trim($result['customer_contact_merged']))) {
                // Use merged customer_contact (prioritizes repair record, then customer table)
                $contact = trim($result['customer_contact_merged']);
            } elseif (isset($result['customer_contact']) && !empty(trim($result['customer_contact']))) {
                // Fall back to direct customer_contact from repair record
                $contact = trim($result['customer_contact']);
            } elseif (isset($result['phone_number']) && !empty(trim($result['phone_number']))) {
                // Fall back to customer table phone_number
                $contact = trim($result['phone_number']);
            }
            $result['customer_contact'] = $contact;
            
            // Ensure issue_description is preserved - NEVER use notes as fallback
            if (!isset($result['issue_description']) || empty(trim($result['issue_description'] ?? ''))) {
                // Check for alternative column names
                if (isset($result['issue']) && !empty(trim($result['issue']))) {
                    $result['issue_description'] = trim($result['issue']);
                } else {
                    // Only use a generic default, never use notes field
                    $result['issue_description'] = 'Repair service';
                }
            } else {
                $result['issue_description'] = trim($result['issue_description']);
            }
            
            // Calculate total_cost if it's 0 or missing
            $repairCost = floatval($result['repair_cost'] ?? 0);
            $partsCost = floatval($result['parts_cost'] ?? 0);
            $accessoryCost = floatval($result['accessory_cost'] ?? 0);
            $totalCost = floatval($result['total_cost'] ?? 0);
            
            // If total_cost is 0 or missing, calculate it
            if ($totalCost == 0) {
                $totalCost = $repairCost + $partsCost + $accessoryCost;
                $result['total_cost'] = $totalCost;
            }
        }
        
        return $results;
    }

    /**
     * Find repairs by company with pagination and search
     */
    public function findByCompanyPaginated($company_id, $page = 1, $limit = 20, $status = null, $dateFrom = null, $dateTo = null, $search = null) {
        $offset = ($page - 1) * $limit;
        
        // Check which repairs table exists
        $checkRepairsNew = $this->db->query("SHOW TABLES LIKE 'repairs_new'");
        $hasRepairsNew = $checkRepairsNew && $checkRepairsNew->rowCount() > 0;
        $repairsTable = $hasRepairsNew ? 'repairs_new' : 'repairs';
        $statusColumn = $hasRepairsNew ? 'status' : 'repair_status';
        $technicianColumn = $hasRepairsNew ? 'technician_id' : 'technician_id';
        
        // Build customer_contact selection based on which table we're using
        if ($hasRepairsNew) {
            $contactSelect = "COALESCE(
                NULLIF(TRIM(r.customer_contact), ''),
                NULLIF(TRIM(c.phone_number), ''),
                ''
            ) as customer_contact_merged";
        } else {
            $contactSelect = "COALESCE(
                NULLIF(TRIM(c.phone_number), ''),
                ''
            ) as customer_contact_merged";
        }
        
        $sql = "
            SELECT r.*, u.full_name as technician_name, p.name as product_name,
                   c.full_name as customer_name_from_table, c.phone_number,
                   COALESCE(
                       NULLIF(TRIM(r.customer_name), ''),
                       NULLIF(TRIM(c.full_name), ''),
                       ''
                   ) as customer_name_merged,
                   {$contactSelect}
            FROM {$repairsTable} r
            LEFT JOIN users u ON r.{$technicianColumn} = u.id
            LEFT JOIN products p ON r.product_id = p.id
            LEFT JOIN customers c ON r.customer_id = c.id
            WHERE r.company_id = ?
        ";
        $params = [$company_id];
        
        // Add search filter
        if (!empty($search)) {
            $sql .= " AND (
                r.customer_name LIKE ? OR 
                r.customer_contact LIKE ? OR 
                r.tracking_code LIKE ? OR
                r.issue_description LIKE ? OR
                c.full_name LIKE ? OR
                c.phone_number LIKE ? OR
                p.name LIKE ?
            )";
            $searchParam = '%' . $search . '%';
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }
        
        // Add date range filter
        if ($dateFrom && $dateTo) {
            $sql .= " AND DATE(r.created_at) >= ? AND DATE(r.created_at) <= ?";
            $params[] = $dateFrom;
            $params[] = $dateTo;
        } elseif ($dateFrom) {
            $sql .= " AND DATE(r.created_at) >= ?";
            $params[] = $dateFrom;
        } elseif ($dateTo) {
            $sql .= " AND DATE(r.created_at) <= ?";
            $params[] = $dateTo;
        }
        
        if ($status) {
            // Normalize status: old table uses uppercase, new table uses lowercase
            if ($hasRepairsNew) {
                $normalizedStatus = strtolower($status);
            } else {
                $normalizedStatus = strtoupper($status);
                if ($normalizedStatus === 'FAILED') {
                    $normalizedStatus = 'CANCELLED';
                }
            }
            $sql .= " AND r.{$statusColumn} = ?";
            $params[] = $normalizedStatus;
        }
        
        // LIMIT and OFFSET must be integers in SQL, not bound parameters
        $limit = (int)$limit;
        $offset = (int)$offset;
        $sql .= " ORDER BY r.created_at DESC LIMIT {$limit} OFFSET {$offset}";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Normalize results (same as findByCompany)
        foreach ($results as &$result) {
            if (!$hasRepairsNew && isset($result['repair_status'])) {
                $result['status'] = strtolower($result['repair_status']);
            } elseif ($hasRepairsNew && isset($result['status'])) {
                $result['status'] = strtolower($result['status']);
            }
            
            if (isset($result['payment_status'])) {
                $result['payment_status'] = strtolower(trim($result['payment_status']));
                if (empty($result['payment_status']) || $result['payment_status'] === 'null' || $result['payment_status'] === 'unpaid') {
                    $result['payment_status'] = 'paid';
                }
            } else {
                $result['payment_status'] = 'paid';
            }
            
            $customerName = '';
            if (isset($result['customer_name_merged']) && !empty(trim($result['customer_name_merged']))) {
                $customerName = trim($result['customer_name_merged']);
            } elseif (isset($result['customer_name']) && !empty(trim($result['customer_name']))) {
                $customerName = trim($result['customer_name']);
            } elseif (isset($result['customer_name_from_table']) && !empty(trim($result['customer_name_from_table']))) {
                $customerName = trim($result['customer_name_from_table']);
            }
            $result['customer_name'] = $customerName ?: 'Unknown Customer';
            
            $contact = '';
            if (isset($result['customer_contact_merged']) && !empty(trim($result['customer_contact_merged']))) {
                $contact = trim($result['customer_contact_merged']);
            } elseif (isset($result['customer_contact']) && !empty(trim($result['customer_contact']))) {
                $contact = trim($result['customer_contact']);
            } elseif (isset($result['phone_number']) && !empty(trim($result['phone_number']))) {
                $contact = trim($result['phone_number']);
            }
            $result['customer_contact'] = $contact;
            
            if (!isset($result['issue_description']) || empty(trim($result['issue_description'] ?? ''))) {
                if (isset($result['issue']) && !empty(trim($result['issue']))) {
                    $result['issue_description'] = trim($result['issue']);
                } else {
                    $result['issue_description'] = 'Repair service';
                }
            } else {
                $result['issue_description'] = trim($result['issue_description']);
            }
            
            $repairCost = floatval($result['repair_cost'] ?? 0);
            $partsCost = floatval($result['parts_cost'] ?? 0);
            $accessoryCost = floatval($result['accessory_cost'] ?? 0);
            $totalCost = floatval($result['total_cost'] ?? 0);
            
            if ($totalCost == 0) {
                $totalCost = $repairCost + $partsCost + $accessoryCost;
                $result['total_cost'] = $totalCost;
            }
        }
        
        return $results;
    }

    /**
     * Get total count of repairs by company with filters
     */
    public function getCountByCompany($company_id, $status = null, $dateFrom = null, $dateTo = null, $search = null) {
        // Check which repairs table exists
        $checkRepairsNew = $this->db->query("SHOW TABLES LIKE 'repairs_new'");
        $hasRepairsNew = $checkRepairsNew && $checkRepairsNew->rowCount() > 0;
        $repairsTable = $hasRepairsNew ? 'repairs_new' : 'repairs';
        $statusColumn = $hasRepairsNew ? 'status' : 'repair_status';
        
        $sql = "
            SELECT COUNT(DISTINCT r.id) as total
            FROM {$repairsTable} r
            LEFT JOIN customers c ON r.customer_id = c.id
            LEFT JOIN products p ON r.product_id = p.id
            WHERE r.company_id = ?
        ";
        $params = [$company_id];
        
        // Add search filter
        if (!empty($search)) {
            $sql .= " AND (
                r.customer_name LIKE ? OR 
                r.customer_contact LIKE ? OR 
                r.tracking_code LIKE ? OR
                r.issue_description LIKE ? OR
                c.full_name LIKE ? OR
                c.phone_number LIKE ? OR
                p.name LIKE ?
            )";
            $searchParam = '%' . $search . '%';
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }
        
        // Add date range filter
        if ($dateFrom && $dateTo) {
            $sql .= " AND DATE(r.created_at) >= ? AND DATE(r.created_at) <= ?";
            $params[] = $dateFrom;
            $params[] = $dateTo;
        } elseif ($dateFrom) {
            $sql .= " AND DATE(r.created_at) >= ?";
            $params[] = $dateFrom;
        } elseif ($dateTo) {
            $sql .= " AND DATE(r.created_at) <= ?";
            $params[] = $dateTo;
        }
        
        if ($status) {
            if ($hasRepairsNew) {
                $normalizedStatus = strtolower($status);
            } else {
                $normalizedStatus = strtoupper($status);
                if ($normalizedStatus === 'FAILED') {
                    $normalizedStatus = 'CANCELLED';
                }
            }
            $sql .= " AND r.{$statusColumn} = ?";
            $params[] = $normalizedStatus;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($result['total'] ?? 0);
    }

    /**
     * Find repairs by technician
     */
    public function findByTechnician($technician_id, $company_id, $status = null) {
        // Check which repairs table exists
        $checkRepairsNew = $this->db->query("SHOW TABLES LIKE 'repairs_new'");
        $hasRepairsNew = $checkRepairsNew && $checkRepairsNew->rowCount() > 0;
        $repairsTable = $hasRepairsNew ? 'repairs_new' : 'repairs';
        $statusColumn = $hasRepairsNew ? 'status' : 'repair_status';
        $technicianColumn = $hasRepairsNew ? 'technician_id' : 'technician_id';
        
        // Ensure technician_id and company_id are integers for proper matching
        $technician_id = (int)$technician_id;
        $company_id = (int)$company_id;
        
        // Use CAST to ensure proper type matching for BIGINT UNSIGNED columns
        // This handles cases where technician_id might be stored as string or different type
        // Build customer_contact based on which table we're using
        if ($hasRepairsNew) {
            // repairs_new table has customer_contact column - prioritize repair record
            $contactSelect = "COALESCE(
                NULLIF(TRIM(r.customer_contact), ''),
                NULLIF(TRIM(c.phone_number), ''),
                ''
            ) as customer_contact_merged";
        } else {
            // repairs table doesn't have customer_contact, only customer_id
            $contactSelect = "COALESCE(
                NULLIF(TRIM(c.phone_number), ''),
                ''
            ) as customer_contact_merged";
        }
        
        $sql = "
            SELECT r.*, 
                   p.name as product_name, 
                   c.full_name as customer_name_from_table,
                   c.phone_number,
                   COALESCE(
                       NULLIF(TRIM(r.customer_name), ''),
                       NULLIF(TRIM(c.full_name), ''),
                       ''
                   ) as customer_name_merged,
                   {$contactSelect}
            FROM {$repairsTable} r
            LEFT JOIN products p ON r.product_id = p.id
            LEFT JOIN customers c ON r.customer_id = c.id
            WHERE CAST(r.{$technicianColumn} AS UNSIGNED) = CAST(? AS UNSIGNED)
              AND CAST(r.company_id AS UNSIGNED) = CAST(? AS UNSIGNED)
        ";
        $params = [$technician_id, $company_id];
        $normalizedStatus = null;
        
        if ($status) {
            // Normalize status: old table uses uppercase, new table uses lowercase
            if ($hasRepairsNew) {
                // repairs_new uses lowercase: pending, in_progress, completed, delivered, failed
                $normalizedStatus = strtolower($status);
            } else {
                // repairs uses uppercase: PENDING, IN_PROGRESS, COMPLETED, DELIVERED, CANCELLED
                $normalizedStatus = strtoupper($status);
                // Map 'failed' to 'CANCELLED' for old table if needed
                if ($normalizedStatus === 'FAILED') {
                    $normalizedStatus = 'CANCELLED';
                }
            }
            $sql .= " AND r.{$statusColumn} = ?";
            $params[] = $normalizedStatus;
        }
        
        $sql .= " ORDER BY r.created_at DESC";
        
        // Debug: Check what's actually in the database
        $debugCheck = $this->db->prepare("SELECT COUNT(*) as total FROM {$repairsTable} WHERE company_id = ?");
        $debugCheck->execute([$company_id]);
        $totalInCompany = $debugCheck->fetch(PDO::FETCH_ASSOC)['total'];
        
        $debugTech = $this->db->prepare("SELECT DISTINCT {$technicianColumn} as tech_id FROM {$repairsTable} WHERE company_id = ? LIMIT 5");
        $debugTech->execute([$company_id]);
        $techIdsInDb = $debugTech->fetchAll(PDO::FETCH_COLUMN);
        
        error_log("Repair::findByTechnician() - Looking for tech_id: {$technician_id} (type: " . gettype($technician_id) . ") in company: {$company_id}");
        error_log("Repair::findByTechnician() - Total repairs in company: {$totalInCompany}");
        error_log("Repair::findByTechnician() - Technician IDs in DB: " . json_encode($techIdsInDb));
        error_log("Repair::findByTechnician() - SQL: " . $sql);
        error_log("Repair::findByTechnician() - Params: " . json_encode($params));
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("Repair::findByTechnician() - Found " . count($results) . " repairs");
        
        // Normalize status values and customer name in results for consistency
        foreach ($results as &$result) {
            if (!$hasRepairsNew && isset($result['repair_status'])) {
                $result['status'] = strtolower(trim($result['repair_status']));
            } elseif ($hasRepairsNew && isset($result['status'])) {
                // Ensure status is lowercase and trimmed
                $result['status'] = strtolower(trim($result['status']));
            } else {
                // If status is not set, ensure it's at least an empty string (normalized)
                $result['status'] = strtolower(trim($result['status'] ?? ''));
            }
            
            // Normalize payment_status (handle both old and new table formats)
            // All repairs are paid at booking time, so default to 'paid'
            if (isset($result['payment_status'])) {
                $result['payment_status'] = strtolower(trim($result['payment_status']));
                // Handle empty or null values - default to 'paid' since all bookings are paid
                if (empty($result['payment_status']) || $result['payment_status'] === 'null' || $result['payment_status'] === 'unpaid') {
                    $result['payment_status'] = 'paid';
                }
            } else {
                // Default to paid if payment_status is not set (all bookings are paid)
                $result['payment_status'] = 'paid';
            }
            
            // IMPORTANT: Use merged customer_name and customer_contact from SQL COALESCE
            // This prioritizes repair record data, then falls back to customer table
            // Since every booking has customer details, we should always have data
            $customerName = '';
            if (isset($result['customer_name_merged']) && !empty(trim($result['customer_name_merged']))) {
                // Use merged customer_name (prioritizes repair record, then customer table)
                $customerName = trim($result['customer_name_merged']);
            } elseif (isset($result['customer_name']) && !empty(trim($result['customer_name']))) {
                // Fall back to direct customer_name from repair record
                $customerName = trim($result['customer_name']);
            } elseif (isset($result['customer_name_from_table']) && !empty(trim($result['customer_name_from_table']))) {
                // Fall back to customer table
                $customerName = trim($result['customer_name_from_table']);
            }
            // Only show "Unknown Customer" if we truly have no data (shouldn't happen for bookings)
            $result['customer_name'] = $customerName ?: 'Unknown Customer';
            
            // Ensure customer_contact is properly set - use merged contact
            $contact = '';
            if (isset($result['customer_contact_merged']) && !empty(trim($result['customer_contact_merged']))) {
                // Use merged customer_contact (prioritizes repair record, then customer table)
                $contact = trim($result['customer_contact_merged']);
            } elseif (isset($result['customer_contact']) && !empty(trim($result['customer_contact']))) {
                // Fall back to direct customer_contact from repair record
                $contact = trim($result['customer_contact']);
            } elseif (!empty(trim($result['phone_number'] ?? ''))) {
                $contact = trim($result['phone_number']);
            }
            $result['customer_contact'] = $contact;
            
            // Ensure issue_description is preserved - NEVER use notes as fallback (notes contains profit info)
            if (!empty(trim($result['issue_description'] ?? ''))) {
                $result['issue_description'] = trim($result['issue_description']);
            } else {
                // Only use a generic default, never use notes field
                $result['issue_description'] = 'Repair service';
            }
            
            // Calculate total_cost if it's 0 or missing
            $repairCost = floatval($result['repair_cost'] ?? 0);
            $partsCost = floatval($result['parts_cost'] ?? 0);
            $accessoryCost = floatval($result['accessory_cost'] ?? 0);
            $totalCost = floatval($result['total_cost'] ?? 0);
            
            // If total_cost is 0 or missing, calculate it
            if ($totalCost == 0) {
                $totalCost = $repairCost + $partsCost + $accessoryCost;
                $result['total_cost'] = $totalCost;
            }
        }
        
        return $results;
    }

    /**
     * Update repair status
     */
    public function updateStatus($id, $company_id, $status, $notes = null) {
        // Check which repairs table exists
        $checkRepairsNew = $this->db->query("SHOW TABLES LIKE 'repairs_new'");
        $hasRepairsNew = $checkRepairsNew && $checkRepairsNew->rowCount() > 0;
        $repairsTable = $hasRepairsNew ? 'repairs_new' : 'repairs';
        $statusColumn = $hasRepairsNew ? 'status' : 'repair_status';
        
        // Normalize status: old table uses uppercase, new table uses lowercase
        if ($hasRepairsNew) {
            $normalizedStatus = strtolower($status);
        } else {
            $normalizedStatus = strtoupper($status);
            // Map 'failed' to 'CANCELLED' for old table if needed
            if ($normalizedStatus === 'FAILED') {
                $normalizedStatus = 'CANCELLED';
            }
        }
        
        // Auto-set payment_status to 'paid' when status is 'delivered' or 'completed'
        $autoPayStatuses = $hasRepairsNew ? ['delivered', 'completed'] : ['DELIVERED', 'COMPLETED'];
        $shouldAutoPay = in_array($normalizedStatus, $autoPayStatuses);
        $normalizedPaymentStatus = $hasRepairsNew ? 'paid' : 'PAID';
        
        // Check if completed_at column exists (safely check table structure)
        $checkCompletedAt = $this->db->query("SHOW COLUMNS FROM `{$repairsTable}` LIKE 'completed_at'");
        $hasCompletedAt = $checkCompletedAt && $checkCompletedAt->rowCount() > 0;
        
        if ($hasCompletedAt) {
            if ($shouldAutoPay) {
                $sql = "
                    UPDATE {$repairsTable} SET 
                        {$statusColumn} = ?, 
                        payment_status = ?,
                        notes = COALESCE(?, notes),
                        completed_at = CASE WHEN ? = 'completed' OR ? = 'COMPLETED' THEN NOW() ELSE completed_at END
                    WHERE id = ? AND company_id = ?
                ";
                $params = [$normalizedStatus, $normalizedPaymentStatus, $notes, $normalizedStatus, $normalizedStatus, $id, $company_id];
            } else {
                $sql = "
                    UPDATE {$repairsTable} SET 
                        {$statusColumn} = ?, 
                        notes = COALESCE(?, notes),
                        completed_at = CASE WHEN ? = 'completed' OR ? = 'COMPLETED' THEN NOW() ELSE completed_at END
                    WHERE id = ? AND company_id = ?
                ";
                $params = [$normalizedStatus, $notes, $normalizedStatus, $normalizedStatus, $id, $company_id];
            }
        } else {
            if ($shouldAutoPay) {
                $sql = "
                    UPDATE {$repairsTable} SET 
                        {$statusColumn} = ?, 
                        payment_status = ?,
                        notes = COALESCE(?, notes)
                    WHERE id = ? AND company_id = ?
                ";
                $params = [$normalizedStatus, $normalizedPaymentStatus, $notes, $id, $company_id];
            } else {
                $sql = "
                    UPDATE {$repairsTable} SET 
                        {$statusColumn} = ?, 
                        notes = COALESCE(?, notes)
                    WHERE id = ? AND company_id = ?
                ";
                $params = [$normalizedStatus, $notes, $id, $company_id];
            }
        }
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Update payment status
     */
    public function updatePaymentStatus($id, $company_id, $payment_status) {
        // Check which repairs table exists
        $checkRepairsNew = $this->db->query("SHOW TABLES LIKE 'repairs_new'");
        $hasRepairsNew = $checkRepairsNew && $checkRepairsNew->rowCount() > 0;
        $repairsTable = $hasRepairsNew ? 'repairs_new' : 'repairs';
        
        // Normalize payment status: old table uses uppercase, new table uses lowercase
        if ($hasRepairsNew) {
            $normalizedPaymentStatus = strtolower($payment_status);
        } else {
            $normalizedPaymentStatus = strtoupper($payment_status);
        }
        
        $stmt = $this->db->prepare("
            UPDATE {$repairsTable} SET payment_status = ?
            WHERE id = ? AND company_id = ?
        ");
        
        return $stmt->execute([$normalizedPaymentStatus, $id, $company_id]);
    }

    /**
     * Get repair statistics
     */
    public function getStats($company_id) {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_repairs,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_repairs,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_repairs,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_repairs,
                SUM(CASE WHEN payment_status = 'unpaid' THEN 1 ELSE 0 END) as unpaid_repairs,
                SUM(total_cost) as total_revenue
            FROM repairs_new 
            WHERE company_id = ?
        ");
        $stmt->execute([$company_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Generate unique tracking code
     */
    private function generateTrackingCode() {
        $maxAttempts = 10;
        $attempt = 0;
        
        while ($attempt < $maxAttempts) {
            $code = 'REP' . date('Ymd') . rand(1000, 9999);
            
            // Check if code already exists
            $checkStmt = $this->db->prepare("SELECT id FROM repairs_new WHERE tracking_code = ? LIMIT 1");
            $checkStmt->execute([$code]);
            
            if ($checkStmt->rowCount() === 0) {
                return $code;
            }
            
            $attempt++;
        }
        
        // If all attempts failed, use timestamp + microtime for uniqueness
        return 'REP' . date('YmdHis') . substr(microtime(), 2, 4);
    }

    /**
     * Find repair by tracking code
     */
    public function findByTrackingCode($tracking_code, $company_id) {
        $stmt = $this->db->prepare("
            SELECT r.*, u.full_name as technician_name, p.name as product_name,
                   c.full_name as customer_name_from_table
            FROM repairs_new r
            LEFT JOIN users u ON r.technician_id = u.id
            LEFT JOIN products p ON r.product_id = p.id
            LEFT JOIN customers c ON r.customer_id = c.id
            WHERE r.tracking_code = ? AND r.company_id = ?
            LIMIT 1
        ");
        $stmt->execute([$tracking_code, $company_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get technician performance data
     */
    public function getTechnicianPerformance($company_id, $period = 'today') {
        $dateCondition = '';
        $params = [$company_id];
        
        switch ($period) {
            case 'today':
                $dateCondition = 'DATE(r.created_at) = CURDATE()';
                break;
            case 'week':
                $dateCondition = 'r.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)';
                break;
            case 'month':
                $dateCondition = 'r.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)';
                break;
        }
        
        $stmt = $this->db->prepare("
            SELECT 
                r.technician_id,
                u.full_name as technician_name,
                COUNT(r.id) as total_repairs,
                SUM(r.total_cost) as total_revenue,
                SUM(r.accessory_cost) as total_accessory_cost,
                AVG(r.total_cost) as avg_repair_value,
                SUM(CASE WHEN r.status = 'completed' THEN 1 ELSE 0 END) as completed_repairs
            FROM repairs_new r
            LEFT JOIN users u ON r.technician_id = u.id
            WHERE r.company_id = ? AND $dateCondition
            GROUP BY r.technician_id, u.full_name
            ORDER BY total_revenue DESC
        ");
        
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get repair summary reports
     */
    public function getSummaryReports($company_id, $start_date, $end_date) {
        $stmt = $this->db->prepare("
            SELECT 
                DATE(r.created_at) as repair_date,
                r.technician_id,
                u.full_name as technician_name,
                COUNT(r.id) as total_repairs,
                SUM(r.accessory_cost) as accessories_cost,
                SUM(r.total_cost) as total_revenue,
                SUM(CASE WHEN r.status = 'completed' THEN 1 ELSE 0 END) as completed_repairs,
                SUM(CASE WHEN r.payment_status = 'paid' THEN 1 ELSE 0 END) as paid_repairs
            FROM repairs_new r
            LEFT JOIN users u ON r.technician_id = u.id
            WHERE r.company_id = ? 
            AND DATE(r.created_at) BETWEEN ? AND ?
            GROUP BY DATE(r.created_at), r.technician_id, u.full_name
            ORDER BY repair_date DESC, total_revenue DESC
        ");
        
        $stmt->execute([$company_id, $start_date, $end_date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Approve repair (Manager only)
     */
    public function approve($id, $company_id, $manager_id) {
        $stmt = $this->db->prepare("
            UPDATE repairs_new SET 
                status = 'completed',
                approved_by = ?,
                approved_at = NOW()
            WHERE id = ? AND company_id = ? AND status = 'pending_approval'
        ");
        
        return $stmt->execute([$manager_id, $id, $company_id]);
    }

    /**
     * Delete a repair (soft delete or hard delete)
     */
    public function delete($id, $company_id) {
        // First verify the repair belongs to the company
        $repair = $this->find($id, $company_id);
        if (!$repair) {
            throw new \Exception("Repair not found or does not belong to your company");
        }
        
        // Check if deleted_at column exists for soft delete
        $checkDeletedAt = $this->db->query("SHOW COLUMNS FROM {$this->table} LIKE 'deleted_at'");
        $hasDeletedAt = $checkDeletedAt && $checkDeletedAt->rowCount() > 0;
        
        if ($hasDeletedAt) {
            // Soft delete
            $stmt = $this->db->prepare("
                UPDATE {$this->table} 
                SET deleted_at = NOW() 
                WHERE id = ? AND company_id = ?
            ");
            $stmt->execute([$id, $company_id]);
        } else {
            // Hard delete - also delete related accessories
            // Delete repair accessories first
            $checkAccessoriesTable = $this->db->query("SHOW TABLES LIKE 'repair_accessories'");
            if ($checkAccessoriesTable && $checkAccessoriesTable->rowCount() > 0) {
                $deleteAccessories = $this->db->prepare("DELETE FROM repair_accessories WHERE repair_id = ?");
                $deleteAccessories->execute([$id]);
            }
            
            // Delete the repair
            $stmt = $this->db->prepare("
                DELETE FROM {$this->table} 
                WHERE id = ? AND company_id = ?
            ");
            $stmt->execute([$id, $company_id]);
        }
        
        return $stmt->rowCount() > 0;
    }

    public function getPendingApprovals($company_id) {
        $stmt = $this->db->prepare("
            SELECT r.*, u.full_name as technician_name, p.name as product_name
            FROM repairs_new r
            LEFT JOIN users u ON r.technician_id = u.id
            LEFT JOIN products p ON r.product_id = p.id
            WHERE r.company_id = ? AND r.status = 'pending_approval'
            ORDER BY r.created_at ASC
        ");
        
        $stmt->execute([$company_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}