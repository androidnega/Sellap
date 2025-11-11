<?php
/**
 * Admin Reset Company Page (PHASE E)
 * Route: /dashboard/companies/{id}/reset
 */

// Require database config
require_once __DIR__ . '/../../config/database.php';

$pageTitle = "Reset Company Data";
$currentPage = 'companies';

// Get company ID from URL (support both query param and route param)
$companyId = $_GET['company_id'] ?? $_GET['id'] ?? $id ?? null;
if (!$companyId) {
    $basePath = defined('BASE_URL_PATH') ? BASE_URL_PATH : '';
    header('Location: ' . $basePath . '/dashboard/companies');
    exit;
}

// Get company info
$db = \Database::getInstance()->getConnection();
$stmt = $db->prepare("SELECT id, name, email FROM companies WHERE id = ?");
$stmt->execute([$companyId]);
$company = $stmt->fetch(\PDO::FETCH_ASSOC);

if (!$company) {
    die("Company not found");
}

$content = '
<div class="max-w-6xl mx-auto">
    <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 mb-2">
                    <i class="fas fa-exclamation-triangle text-red-600 mr-2"></i>
                    Reset Company Data
                </h1>
                <p class="text-gray-600">Company: <span class="font-semibold">' . htmlspecialchars($company['name']) . '</span> (ID: ' . htmlspecialchars($companyId) . ')</p>
            </div>
        </div>

        <!-- Warning Banner -->
        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-circle text-red-500 text-xl"></i>
                </div>
                <div class="ml-3">
                    <h3 class="text-lg font-semibold text-red-800">Warning: Irreversible Operation</h3>
                    <p class="text-red-700 mt-2">
                        This action will permanently delete all transactional data for this company including:
                        products, sales, swaps, repairs, customers, and logs. The company record and global catalogs will be preserved.
                        This action cannot be undone.
                    </p>
                </div>
            </div>
        </div>

        <!-- Dry Run Results -->
        <div id="dryRunResults" class="hidden mb-6">
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <h3 class="text-lg font-semibold text-blue-900 mb-3">
                    <i class="fas fa-eye mr-2"></i>Preview: Affected Data
                </h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Table</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Rows Affected</th>
                            </tr>
                        </thead>
                        <tbody id="dryRunTableBody" class="bg-white divide-y divide-gray-200">
                            <!-- Populated by JavaScript -->
                        </tbody>
                        <tfoot class="bg-gray-100">
                            <tr>
                                <td class="px-4 py-3 font-semibold text-gray-900">Total</td>
                                <td class="px-4 py-3 text-right font-semibold text-gray-900" id="totalAffectedRows">0</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <p class="text-blue-700 text-sm mt-3">
                    <i class="fas fa-info-circle mr-1"></i>
                    This is a preview only. No data has been deleted.
                </p>
            </div>
        </div>

        <!-- Backup Section -->
        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
            <h3 class="text-lg font-semibold text-yellow-800 mb-2">
                <i class="fas fa-database mr-2"></i>Backup Required
            </h3>
            <p class="text-yellow-700 mb-3">
                A full backup is required before executing the reset. Click the button below to create a backup.
            </p>
            <div class="flex items-center space-x-4">
                <button id="createBackupBtn" onclick="createBackup()" 
                        class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-lg font-semibold">
                    <i class="fas fa-download mr-2"></i>Create Backup
                </button>
                <div id="backupStatus" class="hidden">
                    <span id="backupMessage" class="text-sm"></span>
                    <span id="backupId" class="text-sm font-mono ml-2"></span>
                </div>
            </div>
            <div class="mt-3">
                <label class="flex items-center cursor-pointer">
                    <input type="checkbox" id="backupConfirmed" 
                           class="form-checkbox h-5 w-5 text-yellow-600" 
                           onchange="updateButtons()"
                           onclick="updateButtons()">
                    <span class="ml-2 text-yellow-800 font-medium">
                        I confirm that a backup has been created and verified
                    </span>
                </label>
            </div>
        </div>

        <!-- Confirmation Section -->
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-3">Confirmation Required</h3>
            <p class="text-gray-700 mb-3">
                To confirm this action, type the following exactly:
            </p>
            <div class="bg-white border-2 border-gray-300 rounded-lg p-3 mb-3">
                <code class="text-lg font-mono font-semibold text-red-600" id="confirmPhrase">RESET COMPANY ' . htmlspecialchars($companyId) . '</code>
            </div>
            <input type="text" id="confirmationInput" 
                   placeholder="Type confirmation phrase here" 
                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500"
                   oninput="updateButtons()"
                   onkeyup="updateButtons()"
                   onchange="updateButtons()">
            <p class="text-gray-500 text-sm mt-2">
                <i class="fas fa-info-circle mr-1"></i>
                The confirmation phrase must match exactly (case-sensitive).
            </p>
        </div>

        <!-- Action Buttons -->
        <div class="flex space-x-4">
            <button id="dryRunBtn" onclick="runDryRun()" 
                    class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold transition">
                <i class="fas fa-eye mr-2"></i>Preview (Dry Run)
            </button>
            <button id="resetBtn" onclick="executeReset()" 
                    disabled
                    class="flex-1 bg-red-600 hover:bg-red-700 text-white px-6 py-3 rounded-lg font-semibold transition disabled:opacity-50 disabled:cursor-not-allowed">
                <i class="fas fa-exclamation-triangle mr-2"></i>Execute Reset
            </button>
        </div>
    </div>
</div>

<!-- Progress Modal -->
<div id="progressModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl p-6 max-w-md w-full mx-4">
        <div class="text-center">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
            <h3 class="text-xl font-semibold text-gray-900 mb-2" id="progressTitle">Processing Reset...</h3>
            <p class="text-gray-600 mb-4" id="progressMessage">Please wait while we reset the company data.</p>
            <div class="bg-gray-200 rounded-full h-2 mb-4">
                <div id="progressBar" class="bg-blue-600 h-2 rounded-full transition-all" style="width: 0%"></div>
            </div>
            <div id="progressDetails" class="text-sm text-gray-600 space-y-1">
                <!-- Progress details -->
            </div>
            <div class="mt-6" id="progressActions" class="hidden">
                <a id="statusLink" href="#" 
                   class="inline-block bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                    <i class="fas fa-eye mr-2"></i>View Status
                </a>
            </div>
        </div>
    </div>
</div>

<script>
// BASE is already declared in the layout
// const BASE = window.APP_BASE_PATH || ""; // Removed - already in layout
const companyId = ' . htmlspecialchars($companyId) . ';
const expectedConfirm = "RESET COMPANY ' . htmlspecialchars($companyId) . '";
let backupReference = null;

function getToken() {
    return localStorage.getItem("token") || localStorage.getItem("sellapp_token") || "";
}

function updateButtons() {
    const confirmation = document.getElementById("confirmationInput").value.trim();
    const backupChecked = document.getElementById("backupConfirmed").checked;
    const resetBtn = document.getElementById("resetBtn");
    
    // Debug logging
    console.log("updateButtons called:", {
        confirmation: confirmation,
        expectedConfirm: expectedConfirm,
        backupChecked: backupChecked,
        backupReference: backupReference,
        matches: confirmation === expectedConfirm
    });
    
    const canReset = confirmation === expectedConfirm && backupChecked && backupReference;
    resetBtn.disabled = !canReset;
    
    if (!backupReference) {
        resetBtn.title = "Backup must be created first";
    } else if (!backupChecked) {
        resetBtn.title = "Backup confirmation checkbox must be checked";
    } else if (confirmation !== expectedConfirm) {
        resetBtn.title = "Confirmation phrase must match exactly: " + expectedConfirm;
    } else {
        resetBtn.title = "Ready to execute reset";
    }
}

async function createBackup() {
    const btn = document.getElementById("createBackupBtn");
    const status = document.getElementById("backupStatus");
    const message = document.getElementById("backupMessage");
    const backupIdSpan = document.getElementById("backupId");
    
    btn.disabled = true;
    btn.innerHTML = "<i class=\"fas fa-spinner fa-spin mr-2\"></i>Creating Backup...";
    
    try {
        const response = await fetch(BASE + "/api/admin/backup/company", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Authorization": "Bearer " + getToken()
            },
            body: JSON.stringify({ company_id: companyId })
        });
        
        // Check if response is valid JSON
        const text = await response.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error("Invalid JSON response:", text);
            throw new Error("Server returned invalid JSON. Response: " + text.substring(0, 200));
        }
        
        if (data.success) {
            backupReference = data.backup_id;
            status.classList.remove("hidden");
            message.textContent = "Backup created successfully:";
            message.className = "text-sm text-green-700 font-semibold";
            backupIdSpan.textContent = data.backup_id;
            backupIdSpan.className = "text-sm font-mono text-green-700";
            
            // Add download link
            const downloadLink = document.createElement("a");
            downloadLink.href = BASE + "/api/admin/backup/download/" + data.backup_id;
            downloadLink.className = "text-sm text-blue-600 hover:text-blue-800 underline ml-2";
            downloadLink.innerHTML = "<i class=\"fas fa-download mr-1\"></i>Download";
            backupIdSpan.parentElement.appendChild(downloadLink);
            
            // Auto-check the backup confirmation checkbox
            document.getElementById("backupConfirmed").checked = true;
            updateButtons();
        } else {
            alert("Backup failed: " + (data.error || "Unknown error"));
        }
    } catch (error) {
        console.error("Backup error:", error);
        alert("Backup failed: " + error.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = "<i class=\"fas fa-download mr-2\"></i>Create Backup";
    }
}

async function runDryRun() {
    const btn = document.getElementById("dryRunBtn");
    btn.disabled = true;
    btn.innerHTML = "<i class=\"fas fa-spinner fa-spin mr-2\"></i>Loading Preview...";
    
    try {
        const response = await fetch(BASE + "/api/admin/companies/" + companyId + "/reset", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Authorization": "Bearer " + getToken()
            },
            body: JSON.stringify({
                dry_run: true
            })
        });
        
        // Check if response is valid JSON
        const text = await response.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error("Invalid JSON response:", text);
            throw new Error("Server returned invalid JSON. Response: " + text.substring(0, 200));
        }
        
        if (data.success && data.dry_run) {
            displayDryRunResults(data.row_counts || data.counts || {});
        } else {
            alert("Preview failed: " + (data.error || data.message || "Unknown error"));
        }
    } catch (error) {
        console.error("Preview error:", error);
        let errorMsg = "Preview failed: " + error.message;
        if (error.message.includes("JSON") || error.message.includes("parse")) {
            errorMsg = "Server returned invalid response. Please check console for details.";
        }
        alert(errorMsg);
    } finally {
        btn.disabled = false;
        btn.innerHTML = "<i class=\"fas fa-eye mr-2\"></i>Preview (Dry Run)";
    }
}

function displayDryRunResults(counts) {
    const resultsDiv = document.getElementById("dryRunResults");
    const tbody = document.getElementById("dryRunTableBody");
    const totalSpan = document.getElementById("totalAffectedRows");
    
    tbody.innerHTML = "";
    let total = 0;
    
    for (const [table, count] of Object.entries(counts)) {
        total += count;
        const row = document.createElement("tr");
        row.innerHTML = `
            <td class="px-4 py-3 text-sm text-gray-900">${table}</td>
            <td class="px-4 py-3 text-sm text-gray-900 text-right">${count.toLocaleString()}</td>
        `;
        tbody.appendChild(row);
    }
    
    totalSpan.textContent = total.toLocaleString();
    resultsDiv.classList.remove("hidden");
}

async function executeReset() {
    if (!confirm("Are you absolutely sure? This action is IRREVERSIBLE and will delete all company transactional data!")) {
        return;
    }
    
    const modal = document.getElementById("progressModal");
    const progressBar = document.getElementById("progressBar");
    const progressTitle = document.getElementById("progressTitle");
    const progressMessage = document.getElementById("progressMessage");
    const progressDetails = document.getElementById("progressDetails");
    const progressActions = document.getElementById("progressActions");
    const statusLink = document.getElementById("statusLink");
    
    modal.classList.remove("hidden");
    progressBar.style.width = "20%";
    progressTitle.textContent = "Executing Reset...";
    progressMessage.textContent = "This may take a few moments. Do not close this page.";
    progressDetails.innerHTML = "";
    progressActions.classList.add("hidden");
    
    try {
        progressBar.style.width = "40%";
        progressDetails.innerHTML += "<div>✓ Backup verified</div>";
        
        progressBar.style.width = "60%";
        progressDetails.innerHTML += "<div>✓ Confirmation verified</div>";
        
        progressBar.style.width = "80%";
        progressDetails.innerHTML += "<div>⏳ Executing database reset...</div>";
        
        const response = await fetch(BASE + "/api/admin/companies/" + companyId + "/reset", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Authorization": "Bearer " + getToken()
            },
            body: JSON.stringify({
                dry_run: false,
                delete_files: true,
                confirm_code: expectedConfirm
            })
        });
        
        const data = await response.json();
        
        progressBar.style.width = "100%";
        
        if (data.success) {
            progressTitle.textContent = "Reset Completed Successfully";
            progressMessage.textContent = "Company data has been reset. File cleanup is running in the background.";
            progressDetails.innerHTML = `
                <div class="text-green-700">✓ Database reset completed</div>
                <div class="text-green-700">✓ ${data.total_affected_rows} rows deleted</div>
                <div class="text-blue-700">⏳ File cleanup queued</div>
                <div class="mt-2 text-sm">Action ID: ${data.action_id}</div>
            `;
            
            statusLink.href = BASE + "/dashboard/admin/reset/" + data.action_id;
            progressActions.classList.remove("hidden");
            
            setTimeout(() => {
                window.location.href = BASE + "/dashboard/admin/reset/" + data.action_id;
            }, 3000);
        } else {
            progressTitle.textContent = "Reset Failed";
            progressMessage.textContent = "An error occurred during the reset operation.";
            progressDetails.innerHTML = `
                <div class="text-red-700">✗ ${data.error || "Unknown error"}</div>
                ${data.action_id ? "<div class=\"mt-2 text-sm\">Action ID: " + data.action_id + "</div>" : ""}
            `;
            progressActions.classList.remove("hidden");
            if (data.action_id) {
                statusLink.href = BASE + "/dashboard/admin/reset/" + data.action_id;
            }
        }
    } catch (error) {
        progressTitle.textContent = "Reset Failed";
        progressMessage.textContent = "An error occurred: " + error.message;
        progressDetails.innerHTML = "<div class=\"text-red-700\">✗ " + error.message + "</div>";
    }
}
</script>
';

// Include layout
include __DIR__ . '/layouts/dashboard.php';

