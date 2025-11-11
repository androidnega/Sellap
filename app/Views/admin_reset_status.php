<?php
/**
 * Admin Reset Status/Logs Page (PHASE E)
 * Route: /dashboard/admin/reset/{admin_action_id}
 */

$pageTitle = "Reset Operation Status";
$currentPage = 'admin';

// Get action ID from URL (support both query param and route param)
$actionId = $_GET['action_id'] ?? $_GET['id'] ?? $GLOBALS['reset_action_id'] ?? $id ?? null;

// Validate actionId is numeric
if (!$actionId || !is_numeric($actionId)) {
    header('Location: ' . (defined('BASE_URL_PATH') ? BASE_URL_PATH : '') . '/dashboard/reset/history');
    exit;
}

$actionId = (int)$actionId; // Ensure it's an integer

$content = '
<div class="max-w-6xl mx-auto">
    <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 mb-2">
                    <i class="fas fa-clipboard-list mr-2"></i>
                    Reset Operation Status
                </h1>
                <p class="text-gray-600">Action ID: <span class="font-mono font-semibold">' . htmlspecialchars($actionId) . '</span></p>
            </div>
            <a href="' . (defined('BASE_URL_PATH') ? BASE_URL_PATH : '') . '/dashboard/reset/history" 
               class="text-blue-600 hover:text-blue-800">
                <i class="fas fa-arrow-left mr-2"></i>Back to History
            </a>
        </div>

        <!-- Action Details -->
        <div id="actionDetails" class="space-y-6">
            <!-- Loading -->
            <div class="text-center py-8">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
                <p class="text-gray-600">Loading action details...</p>
            </div>
        </div>
    </div>
</div>

<script>
// BASE is already declared in the layout
// const BASE = window.APP_BASE_PATH || ""; // Removed - already in layout
const resetActionId = ' . (is_numeric($actionId) ? (int)$actionId : htmlspecialchars($actionId)) . '; // Use different name to avoid conflicts

function getToken() {
    return localStorage.getItem("token") || localStorage.getItem("sellapp_token") || "";
}

async function loadActionDetails() {
    try {
        // Ensure resetActionId is a string/number, not an object
        const actionIdStr = String(resetActionId);
        const response = await fetch(BASE + "/api/admin/reset/" + actionIdStr, {
            headers: {
                "Authorization": "Bearer " + getToken()
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            displayActionDetails(data);
        } else {
            document.getElementById("actionDetails").innerHTML = `
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <p class="text-red-800">Error: ${data.error || "Failed to load action details"}</p>
                </div>
            `;
        }
    } catch (error) {
        document.getElementById("actionDetails").innerHTML = `
            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                <p class="text-red-800">Error: ${error.message}</p>
            </div>
        `;
    }
}

function displayActionDetails(data) {
    const action = data.action;
    const jobs = data.jobs || [];
    const jobStats = data.job_statistics || {};
    
    const statusColor = {
        "completed": "green",
        "failed": "red",
        "pending": "yellow",
        "running": "blue"
    }[action.status] || "gray";
    
    const statusIcon = {
        "completed": "check-circle",
        "failed": "times-circle",
        "pending": "clock",
        "running": "spinner fa-spin"
    }[action.status] || "question-circle";
    
    const rowCounts = action.row_counts || {};
    const totalAffected = Object.values(rowCounts).reduce((sum, count) => sum + parseInt(count), 0);
    
    let html = `
        <!-- Status Banner -->
        <div class="bg-${statusColor}-50 border-l-4 border-${statusColor}-500 p-4 mb-6">
            <div class="flex items-center">
                <i class="fas fa-${statusIcon} text-${statusColor}-600 text-2xl mr-3"></i>
                <div>
                    <h3 class="text-lg font-semibold text-${statusColor}-900">Status: ${action.status.toUpperCase()}</h3>
                    <p class="text-${statusColor}-700">
                        ${action.dry_run ? "Dry Run (No data deleted)" : "Actual Reset Executed"}
                    </p>
                </div>
            </div>
        </div>

        <!-- Action Info -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div class="bg-gray-50 rounded-lg p-4">
                <h3 class="font-semibold text-gray-900 mb-3">Operation Details</h3>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-600">Action Type:</dt>
                        <dd class="font-semibold">${action.action_type.replace("_", " ").toUpperCase()}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-600">Performed By:</dt>
                        <dd class="font-semibold">${data.admin_user?.full_name || data.admin_user?.username || "Unknown"}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-600">Started:</dt>
                        <dd>${new Date(action.created_at).toLocaleString()}</dd>
                    </div>
                    ${action.completed_at ? `
                    <div class="flex justify-between">
                        <dt class="text-gray-600">Completed:</dt>
                        <dd>${new Date(action.completed_at).toLocaleString()}</dd>
                    </div>
                    ` : ""}
                    ${action.target_company ? `
                    <div class="flex justify-between">
                        <dt class="text-gray-600">Company:</dt>
                        <dd class="font-semibold">${action.target_company.name} (ID: ${action.target_company.id})</dd>
                    </div>
                    ` : ""}
                </dl>
            </div>
            
            <div class="bg-gray-50 rounded-lg p-4">
                <h3 class="font-semibold text-gray-900 mb-3">Summary</h3>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-600">Total Rows Affected:</dt>
                        <dd class="font-semibold text-lg">${totalAffected.toLocaleString()}</dd>
                    </div>
                    ${action.backup_reference ? `
                    <div class="flex justify-between">
                        <dt class="text-gray-600">Backup Reference:</dt>
                        <dd class="font-mono text-xs">${action.backup_reference}</dd>
                    </div>
                    ` : ""}
                    ${action.error_message ? `
                    <div class="mt-3 p-2 bg-red-50 rounded">
                        <dt class="text-red-800 font-semibold">Error:</dt>
                        <dd class="text-red-700 text-xs">${action.error_message}</dd>
                    </div>
                    ` : ""}
                </dl>
            </div>
        </div>

        <!-- Row Counts Table -->
        ${Object.keys(rowCounts).length > 0 ? `
        <div class="mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-3">Rows Affected by Table</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Table</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Rows Deleted</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        ${Object.entries(rowCounts).map(([table, count]) => `
                            <tr>
                                <td class="px-4 py-3 text-sm text-gray-900">${table}</td>
                                <td class="px-4 py-3 text-sm text-gray-900 text-right">${parseInt(count).toLocaleString()}</td>
                            </tr>
                        `).join("")}
                    </tbody>
                    <tfoot class="bg-gray-100">
                        <tr>
                            <td class="px-4 py-3 font-semibold text-gray-900">Total</td>
                            <td class="px-4 py-3 text-right font-semibold text-gray-900">${totalAffected.toLocaleString()}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        ` : ""}

        <!-- File Cleanup Jobs -->
        ${jobs.length > 0 ? `
        <div class="mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-3">File Cleanup Jobs</h3>
            <div class="bg-gray-50 rounded-lg p-4 mb-3">
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4 text-center">
                    <div>
                        <div class="text-2xl font-bold">${jobStats.total || 0}</div>
                        <div class="text-xs text-gray-600">Total Jobs</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-yellow-600">${jobStats.pending || 0}</div>
                        <div class="text-xs text-gray-600">Pending</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-blue-600">${jobStats.running || 0}</div>
                        <div class="text-xs text-gray-600">Running</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-green-600">${jobStats.completed || 0}</div>
                        <div class="text-xs text-gray-600">Completed</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-red-600">${jobStats.failed || 0}</div>
                        <div class="text-xs text-gray-600">Failed</div>
                    </div>
                </div>
            </div>
            <div class="space-y-2">
                ${jobs.map(job => {
                    const jobStatusColor = {
                        "completed": "green",
                        "failed": "red",
                        "pending": "yellow",
                        "running": "blue"
                    }[job.status] || "gray";
                    
                    const jobDetails = job.details || {};
                    return `
                    <div class="border border-gray-200 rounded-lg p-4">
                        <div class="flex justify-between items-center mb-2">
                            <div>
                                <span class="px-2 py-1 bg-${jobStatusColor}-100 text-${jobStatusColor}-800 rounded text-xs font-semibold">
                                    ${job.status.toUpperCase()}
                                </span>
                                <span class="ml-2 text-sm text-gray-600">${job.job_type.replace("_", " ")}</span>
                            </div>
                            <span class="text-xs text-gray-500">Job #${job.id}</span>
                        </div>
                        ${jobDetails.total_files ? `
                        <div class="text-sm text-gray-700 mt-2">
                            Files: ${jobDetails.deleted_count || 0} deleted, ${jobDetails.failed_count || 0} failed (of ${jobDetails.total_files} total)
                        </div>
                        ` : ""}
                        ${job.error_message ? `
                        <div class="mt-2 text-sm text-red-700 bg-red-50 p-2 rounded">
                            Error: ${job.error_message}
                        </div>
                        ` : ""}
                        ${job.completed_at ? `
                        <div class="text-xs text-gray-500 mt-2">
                            Completed: ${new Date(job.completed_at).toLocaleString()}
                        </div>
                        ` : ""}
                    </div>
                    `;
                }).join("")}
            </div>
        </div>
        ` : ""}

        <!-- Backup Download -->
        ${action.backup_reference ? `
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <h3 class="font-semibold text-blue-900 mb-2">
                <i class="fas fa-download mr-2"></i>Backup Information
            </h3>
            <p class="text-blue-800 text-sm mb-2">Backup Reference: <code class="bg-white px-2 py-1 rounded">${action.backup_reference}</code></p>
            <p class="text-blue-700 text-xs">
                Backup files are stored in: <code>storage/backups/${action.backup_reference}/</code>
            </p>
            <p class="text-blue-700 text-xs mt-1">
                <i class="fas fa-info-circle mr-1"></i>
                Contact your system administrator to access backup files.
            </p>
        </div>
        ` : ""}
    `;
    
    document.getElementById("actionDetails").innerHTML = html;
}

// Load on page load
loadActionDetails();

// Refresh every 5 seconds if job is still running
setInterval(() => {
    // Only refresh if there are pending/running jobs
    const jobStats = document.querySelector("[data-job-stats]");
    if (jobStats) {
        loadActionDetails();
    }
}, 5000);
</script>
';

// Include layout
include __DIR__ . '/layouts/dashboard.php';

