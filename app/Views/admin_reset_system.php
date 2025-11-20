<?php
/**
 * Admin Reset System Page (PHASE E)
 * Route: /dashboard/reset
 */

$pageTitle = "Reset System Data";
$currentPage = 'admin';

$content = '
<div class="max-w-6xl mx-auto">
    <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 mb-2">
                    <i class="fas fa-skull text-red-600 mr-2"></i>
                    Reset System Data
                </h1>
                <p class="text-gray-600">This will delete ALL companies and their data</p>
            </div>
        </div>

        <!-- Critical Warning Banner -->
        <div class="bg-red-100 border-l-4 border-red-600 p-6 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-triangle text-red-600 text-3xl"></i>
                </div>
                <div class="ml-4">
                    <h3 class="text-2xl font-bold text-red-900 mb-2">EXTREMELY DESTRUCTIVE OPERATION</h3>
                    <p class="text-red-800 text-lg mb-2">
                        This will permanently delete:
                    </p>
                    <ul class="list-disc list-inside text-red-800 space-y-1 mb-4">
                        <li>ALL companies</li>
                        <li>ALL company users (except system_admin)</li>
                        <li>ALL products, sales, swaps, repairs, customers</li>
                        <li>ALL transactional data</li>
                    </ul>
                    <p class="text-red-900 font-bold text-lg">
                        Only system_admin users will remain. This action CANNOT be undone.
                    </p>
                </div>
            </div>
        </div>

        <!-- Preserved Data Section -->
        <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6">
            <h3 class="text-lg font-semibold text-green-800 mb-2">
                <i class="fas fa-shield-alt mr-2"></i>Data That Will Be Preserved
            </h3>
            <ul class="list-disc list-inside text-green-700 space-y-1">
                <li>System admin users (company_id = NULL)</li>
                <li>System settings (API keys, configuration)</li>
                <li>Global catalogs (categories, brands, subcategories)</li>
                <li>Reset audit logs (this operation will be logged)</li>
            </ul>
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
                <i class="fas fa-database mr-2"></i>Full System Backup Required
            </h3>
            <p class="text-yellow-700 mb-3">
                A complete system backup is mandatory before executing this operation. This may take several minutes.
            </p>
            <div class="flex items-center space-x-4">
                <button id="createBackupBtn" onclick="createBackup()" 
                        class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-lg font-semibold">
                    <i class="fas fa-download mr-2"></i>Create Full System Backup
                </button>
                <div id="backupStatus" class="hidden">
                    <span id="backupMessage" class="text-sm"></span>
                    <span id="backupId" class="text-sm font-mono ml-2"></span>
                </div>
            </div>
            <div class="mt-3">
                <label class="flex items-center">
                    <input type="checkbox" id="backupConfirmed" 
                           class="form-checkbox h-5 w-5 text-yellow-600" 
                           onchange="updateButtons()">
                    <span class="ml-2 text-yellow-800 font-medium">
                        I confirm that a full system backup has been created and verified
                    </span>
                </label>
            </div>
        </div>

        <!-- Two-Step Confirmation -->
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-3">Two-Step Confirmation Required</h3>
            
            <!-- Step 1: Confirmation Phrase -->
            <div class="mb-4">
                <p class="text-gray-700 mb-2">Step 1: Type the confirmation phrase exactly:</p>
                <div class="bg-white border-2 border-gray-300 rounded-lg p-3 mb-2">
                    <code class="text-lg font-mono font-semibold text-red-600">RESET SYSTEM</code>
                </div>
                <input type="text" id="confirmationInput" 
                       placeholder="Type confirmation phrase here" 
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500"
                       oninput="updateButtons()"
                       onkeyup="updateButtons()"
                       onchange="updateButtons()">
            </div>
            
            <!-- Step 2: Password -->
            <div>
                <p class="text-gray-700 mb-2">Step 2: Enter your admin password:</p>
                <input type="password" id="adminPassword" 
                       placeholder="Enter admin password" 
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500"
                       oninput="updateButtons()"
                       onkeyup="updateButtons()"
                       onchange="updateButtons()">
                <p class="text-gray-500 text-sm mt-2">
                    <i class="fas fa-lock mr-1"></i>
                    Your password is required to confirm this operation.
                </p>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex space-x-4">
            <button id="dryRunBtn" onclick="runDryRun()" 
                    class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold transition">
                <i class="fas fa-eye mr-2"></i>Preview (Dry Run)
            </button>
            <button id="resetBtn" onclick="executeReset()" 
                    disabled
                    class="flex-1 bg-red-700 hover:bg-red-800 text-white px-6 py-3 rounded-lg font-semibold transition disabled:opacity-50 disabled:cursor-not-allowed text-lg">
                <i class="fas fa-skull mr-2"></i>EXECUTE SYSTEM RESET
            </button>
        </div>
    </div>
</div>

<!-- Progress Modal -->
<div id="progressModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl p-6 max-w-md w-full mx-4">
        <div class="text-center">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-red-600 mx-auto mb-4"></div>
            <h3 class="text-xl font-semibold text-gray-900 mb-2" id="progressTitle">Processing System Reset...</h3>
            <p class="text-gray-600 mb-4" id="progressMessage">This may take several minutes. Do not close this page.</p>
            <div class="bg-gray-200 rounded-full h-2 mb-4">
                <div id="progressBar" class="bg-red-600 h-2 rounded-full transition-all" style="width: 0%"></div>
            </div>
            <div id="progressDetails" class="text-sm text-gray-600 space-y-1">
                <!-- Progress details -->
            </div>
            <div class="mt-6" id="progressActions" class="hidden">
                <a id="statusLink" href="#" 
                   class="inline-block bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg">
                    <i class="fas fa-eye mr-2"></i>View Status
                </a>
            </div>
        </div>
    </div>
</div>

<script>
// BASE is already declared in the layout
// const BASE = window.APP_BASE_PATH || ""; // Removed - already in layout
const expectedConfirm = "RESET SYSTEM";
let backupReference = null;

function getToken() {
    return localStorage.getItem("token") || localStorage.getItem("sellapp_token") || "";
}

// Check if token is expired (simple JWT expiration check)
function isTokenExpired(token) {
    if (!token) return true;
    try {
        // JWT tokens have 3 parts separated by dots: header.payload.signature
        const parts = token.split(".");
        if (parts.length !== 3) return true;
        
        // Decode the payload (second part)
        let base64 = parts[1].replace(/-/g, "+").replace(/_/g, "/");
        const payload = JSON.parse(atob(base64));
        
        // Check if token has expiration claim and if it\'s expired
        if (payload.exp) {
            // exp is in seconds, Date.now() is in milliseconds
            return payload.exp * 1000 < Date.now();
        }
        return false; // No expiration claim, assume valid
    } catch (e) {
        // If we can\'t parse the token, assume it\'s expired/invalid
        return true;
    }
}

function updateButtons() {
    const confirmation = document.getElementById("confirmationInput").value;
    const password = document.getElementById("adminPassword").value;
    const backupChecked = document.getElementById("backupConfirmed").checked;
    const resetBtn = document.getElementById("resetBtn");
    
    const canReset = confirmation === expectedConfirm && password.length > 0 && backupChecked && backupReference;
    resetBtn.disabled = !canReset;
    
    // Update button appearance based on state
    if (canReset) {
        resetBtn.classList.remove("opacity-50", "cursor-not-allowed");
        resetBtn.classList.add("hover:bg-red-800");
    } else {
        resetBtn.classList.add("opacity-50", "cursor-not-allowed");
        resetBtn.classList.remove("hover:bg-red-800");
    }
}

// Initialize buttons when script loads (DOM should be ready since script is in body)
if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", updateButtons);
} else {
    // DOM already loaded
    updateButtons();
}

// Helper function to get headers with token handling
function getAuthHeaders() {
    const headers = {
        "Content-Type": "application/json"
    };
    const token = getToken();
    if (token && !isTokenExpired(token)) {
        headers["Authorization"] = "Bearer " + token;
    }
    return headers;
}

async function createBackup() {
    const btn = document.getElementById("createBackupBtn");
    const status = document.getElementById("backupStatus");
    const message = document.getElementById("backupMessage");
    const backupIdSpan = document.getElementById("backupId");
    
    btn.disabled = true;
    btn.innerHTML = "<i class=\"fas fa-spinner fa-spin mr-2\"></i>Creating Backup...";
    
    try {
        const response = await fetch(BASE + "/api/admin/backup/system", {
            method: "POST",
            headers: getAuthHeaders(),
            credentials: "same-origin"  // Include cookies (session) with the request
        });
        
        // Check if response is JSON
        const contentType = response.headers.get("content-type");
        let data;
        
        if (contentType && contentType.includes("application/json")) {
            data = await response.json();
        } else {
            // Response is not JSON, likely an HTML error page
            const text = await response.text();
            console.error("Non-JSON response received:", text.substring(0, 500));
            throw new Error("Server returned an invalid response. This may be a server error. Please check the server logs.");
        }
        
        if (data.success) {
            backupReference = data.backup_id;
            status.classList.remove("hidden");
            message.textContent = "Backup created successfully:";
            message.className = "text-sm text-green-700 font-semibold";
            backupIdSpan.textContent = data.backup_id;
            backupIdSpan.className = "text-sm font-mono text-green-700";
            updateButtons();
        } else {
            // Handle token expiration errors
            if (data.error && (data.error.includes("expired") || data.error.includes("Invalid or expired token"))) {
                alert("Backup failed: Session expired. Please refresh the page and try again.");
            } else {
                alert("Backup failed: " + (data.error || data.message || "Unknown error"));
            }
        }
    } catch (error) {
        console.error("Backup error:", error);
        alert("Backup failed: " + error.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = "<i class=\"fas fa-download mr-2\"></i>Create Full System Backup";
    }
}

async function runDryRun() {
    const btn = document.getElementById("dryRunBtn");
    btn.disabled = true;
    btn.innerHTML = "<i class=\"fas fa-spinner fa-spin mr-2\"></i>Loading Preview...";
    
    try {
        const response = await fetch(BASE + "/api/admin/system/reset", {
            method: "POST",
            headers: getAuthHeaders(),
            credentials: "same-origin",  // Include cookies (session) with the request
            body: JSON.stringify({
                dry_run: true
            })
        });
        
        const data = await response.json();
        
        if (data.success && data.dry_run) {
            displayDryRunResults(data.row_counts || data.counts || {});
        } else {
            // Handle token expiration errors
            if (data.error && (data.error.includes("expired") || data.error.includes("Invalid or expired token"))) {
                alert("Preview failed: Session expired. Please refresh the page and try again.");
            } else {
                alert("Preview failed: " + (data.error || data.message || "Unknown error"));
            }
        }
    } catch (error) {
        alert("Preview failed: " + error.message);
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
    // Triple confirmation for system reset
    if (!confirm("FINAL WARNING: This will delete ALL companies and data. Are you ABSOLUTELY SURE?")) {
        return;
    }
    
    if (!confirm("This is your LAST CHANCE. This operation is IRREVERSIBLE. Continue?")) {
        return;
    }
    
    const modal = document.getElementById("progressModal");
    const progressBar = document.getElementById("progressBar");
    const progressTitle = document.getElementById("progressTitle");
    const progressMessage = document.getElementById("progressMessage");
    const progressDetails = document.getElementById("progressDetails");
    const progressActions = document.getElementById("progressActions");
    const statusLink = document.getElementById("statusLink");
    
    const password = document.getElementById("adminPassword").value;
    
    modal.classList.remove("hidden");
    progressBar.style.width = "10%";
    progressTitle.textContent = "Executing System Reset...";
    progressMessage.textContent = "This may take several minutes. Do not close this page.";
    progressDetails.innerHTML = "";
    progressActions.classList.add("hidden");
    
    try {
        progressBar.style.width = "20%";
        progressDetails.innerHTML += "<div>✓ Backup verified</div>";
        
        progressBar.style.width = "40%";
        progressDetails.innerHTML += "<div>✓ Confirmation verified</div>";
        
        progressBar.style.width = "60%";
        progressDetails.innerHTML += "<div>✓ Password verified</div>";
        
        progressBar.style.width = "80%";
        progressDetails.innerHTML += "<div>⏳ Executing database reset...</div>";
        
        const response = await fetch(BASE + "/api/admin/system/reset", {
            method: "POST",
            headers: getAuthHeaders(),
            credentials: "same-origin",  // Include cookies (session) with the request
            body: JSON.stringify({
                dry_run: false,
                delete_files: true,
                confirm_code: expectedConfirm,
                admin_password: password,
                backup_reference: backupReference
            })
        });
        
        const data = await response.json();
        
        progressBar.style.width = "100%";
        
        if (data.success) {
            progressTitle.textContent = "System Reset Completed";
            progressMessage.textContent = "All system data has been reset. File cleanup is running in the background.";
            progressDetails.innerHTML = `
                <div class="text-green-700">✓ Database reset completed</div>
                <div class="text-green-700">✓ ${data.total_affected_rows} rows deleted</div>
                <div class="text-blue-700">⏳ File cleanup queued</div>
                <div class="mt-2 text-sm">Action ID: ${data.action_id}</div>
                <div class="mt-4 p-3 bg-yellow-100 rounded text-yellow-800 text-sm">
                    <strong>Note:</strong> Only system_admin users remain. You may need to log in again.
                </div>
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

