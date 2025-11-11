<?php
/**
 * Admin Reset History Page (PHASE E)
 * Route: /dashboard/reset/history
 */

$pageTitle = "Reset Operation History";
$currentPage = 'admin';

$content = '
<div class="max-w-6xl mx-auto">
    <div class="bg-white rounded-lg shadow-lg p-6">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-3xl font-bold text-gray-900">
                <i class="fas fa-history mr-2"></i>Reset Operation History
            </h1>
        </div>

        <!-- Filters -->
        <div class="bg-gray-50 rounded-lg p-4 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Action Type</label>
                    <select id="filterType" onchange="loadHistory()" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        <option value="">All Types</option>
                        <option value="company_reset">Company Reset</option>
                        <option value="system_reset">System Reset</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select id="filterStatus" onchange="loadHistory()" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        <option value="">All Status</option>
                        <option value="completed">Completed</option>
                        <option value="failed">Failed</option>
                        <option value="pending">Pending</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Limit</label>
                    <select id="filterLimit" onchange="loadHistory()" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        <option value="50">50</option>
                        <option value="100">100</option>
                        <option value="200">200</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Actions Table -->
        <div id="historyTable" class="overflow-x-auto">
            <div class="text-center py-8">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
                <p class="text-gray-600">Loading history...</p>
            </div>
        </div>
    </div>
</div>

<script>
// BASE is already declared in the layout, just use it
// const BASE = window.APP_BASE_PATH || ""; // Removed - already in layout

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

async function loadHistory() {
    const type = document.getElementById("filterType").value;
    const status = document.getElementById("filterStatus").value;
    const limit = document.getElementById("filterLimit").value;
    
    try {
        let url = BASE + "/api/admin/reset/actions?limit=" + limit;
        if (type) url += "&type=" + type;
        if (status) url += "&status=" + status;
        
        // Only send token if it\'s not expired - otherwise rely on session cookies
        const token = getToken();
        const headers = {};
        if (token && !isTokenExpired(token)) {
            headers["Authorization"] = "Bearer " + token;
        }
        // Always send credentials to include session cookies
        // This way, even if JWT is expired, session-based auth will work
        
        const response = await fetch(url, {
            headers: headers,
            credentials: "same-origin"  // Include cookies (session) with the request
        });
        
        const data = await response.json();
        
        if (data.success) {
            displayHistory(data.actions);
        } else {
            // Handle token expiration errors
            if (data.error && (data.error.includes("expired") || data.error.includes("Invalid or expired token"))) {
                // Try to validate and refresh token (only if token exists and is not expired)
                const token = getToken();
                if (token && !isTokenExpired(token)) {
                    try {
                        const validateResponse = await fetch(BASE + "/api/auth/validate-local-token", {
                            method: "POST",
                            headers: {"Content-Type": "application/json"},
                            body: JSON.stringify({token: token}),
                            credentials: "same-origin"  // Include cookies (session) with the request
                        });
                        const validateData = await validateResponse.json();
                        if (validateData.success) {
                            // Token validated, reload page
                            window.location.reload();
                            return;
                        }
                    } catch (e) {
                        // Validation failed, continue to redirect
                    }
                }
                
                // If token is expired or validation failed, clear it and redirect to login
                localStorage.removeItem("token");
                localStorage.removeItem("sellapp_token");
                const currentUrl = encodeURIComponent(window.location.href);
                window.location.href = BASE + "/?redirect=" + currentUrl;
                return;
            }
            
            document.getElementById("historyTable").innerHTML = `
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <p class="text-red-800">Error: ${data.error || "Failed to load history"}</p>
                </div>
            `;
        }
    } catch (error) {
        document.getElementById("historyTable").innerHTML = `
            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                <p class="text-red-800">Error: ${error.message}</p>
            </div>
        `;
    }
}

function displayHistory(actions) {
    // Rename variable to avoid conflict with window.history
    const resetActions = actions;
    
    if (resetActions.length === 0) {
        document.getElementById("historyTable").innerHTML = `
            <div class="text-center py-8 text-gray-500">
                <i class="fas fa-inbox text-4xl mb-2"></i>
                <p>No reset operations found</p>
            </div>
        `;
        return;
    }
    
    let html = `
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Target</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Admin</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Rows</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
    `;
    
    resetActions.forEach(action => {
        const statusColor = {
            "completed": "green",
            "failed": "red",
            "pending": "yellow",
            "running": "blue"
        }[action.status] || "gray";
        
        const rowCounts = action.row_counts || {};
        const totalRows = Object.values(rowCounts).reduce((sum, count) => sum + parseInt(count), 0);
        
        html += `
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 text-sm font-mono">#${action.id}</td>
                <td class="px-4 py-3 text-sm">
                    ${action.action_type === "company_reset" ? 
                        "<span class=\"px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs\">Company</span>" :
                        "<span class=\"px-2 py-1 bg-red-100 text-red-800 rounded text-xs\">System</span>"
                    }
                </td>
                <td class="px-4 py-3 text-sm">
                    ${action.company_name ? action.company_name + " (ID: " + action.target_company_id + ")" : "System-wide"}
                </td>
                <td class="px-4 py-3 text-sm">${action.admin_username || action.admin_full_name || "Unknown"}</td>
                <td class="px-4 py-3 text-sm">
                    <span class="px-2 py-1 bg-${statusColor}-100 text-${statusColor}-800 rounded text-xs font-semibold">
                        ${action.status.toUpperCase()}
                    </span>
                    ${action.dry_run ? "<span class=\"ml-1 text-xs text-gray-500\">(Dry Run)</span>" : ""}
                </td>
                <td class="px-4 py-3 text-sm text-right">${totalRows.toLocaleString()}</td>
                <td class="px-4 py-3 text-sm text-gray-500">${new Date(action.created_at).toLocaleString()}</td>
                <td class="px-4 py-3 text-sm text-center">
                    <a href="${BASE}/dashboard/admin/reset/${action.id}" 
                       class="text-blue-600 hover:text-blue-800">
                        <i class="fas fa-eye"></i> View
                    </a>
                </td>
            </tr>
        `;
    });
    
    html += `
            </tbody>
        </table>
    `;
    
    document.getElementById("historyTable").innerHTML = html;
}

// Load on page load
loadHistory();
</script>
';

// Include layout
include __DIR__ . '/layouts/dashboard.php';

