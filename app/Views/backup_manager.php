<?php
require_once __DIR__ . '/../Helpers/DashboardAuth.php';

$title = 'Data Backup & Restore';
$GLOBALS['pageTitle'] = $title;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$user = $_SESSION['user'] ?? null;
$companyId = $user['company_id'] ?? null;
?>

<div class="p-4 md:p-6">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Data Backup Management</h1>
    <p class="text-gray-600 mb-6">Export and manage your company data backups</p>

    <!-- Manual Export Backup Section (Optional - Auto-backup is recommended) -->
    <?php if ($user['role'] === 'system_admin'): ?>
    <div class="bg-yellow-50 border border-yellow-200 rounded-lg shadow p-6 mb-6">
        <div class="flex items-start mb-4">
            <i class="fas fa-info-circle text-yellow-600 mr-2 mt-1"></i>
            <div>
                <h2 class="text-xl font-semibold text-gray-800">
                    Manual Backup (Optional)
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    <strong>Note:</strong> Auto-backup is recommended and configured below. Use manual backup only for emergency situations.
                </p>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Select Company</label>
                <select id="selectCompany" class="border border-gray-300 rounded px-4 py-2 w-full">
                    <option value="">-- All Companies (System Backup) --</option>
                    <?php foreach ($GLOBALS['companies'] ?? [] as $company): ?>
                        <option value="<?= $company['id'] ?>"><?= htmlspecialchars($company['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 mb-1">Format</label>
                <select id="exportFormat" class="border border-gray-300 rounded px-4 py-2 w-full" disabled>
                    <option value="json" selected>JSON (ZIP) - Compatible with Restore Points</option>
                </select>
                <p class="text-xs text-gray-500 mt-1">Backups are created as ZIP files containing JSON data, compatible with the restore points system.</p>
            </div>
            <button id="btnExportBackup" class="bg-yellow-600 hover:bg-yellow-700 text-white rounded px-6 py-2">
                <i class="fas fa-download mr-2"></i> Create Manual Backup
            </button>
        </div>
        <div id="exportProgress" class="hidden mt-4">
            <div class="bg-blue-50 border border-blue-200 rounded p-4">
                <div class="flex items-center">
                    <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-blue-600 mr-3"></div>
                    <span class="text-blue-700">Creating backup... This may take a few moments.</span>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <!-- For non-admin users, show a simpler manual backup option -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">
            <i class="fas fa-download text-blue-500 mr-2"></i> Export Backup
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-end">
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 mb-1">Format</label>
                <select id="exportFormat" class="border border-gray-300 rounded px-4 py-2 w-full" disabled>
                    <option value="json" selected>JSON (ZIP) - Compatible with Restore Points</option>
                </select>
                <p class="text-xs text-gray-500 mt-1">Backups are created as ZIP files containing JSON data, compatible with the restore points system.</p>
            </div>
            <button id="btnExportBackup" class="bg-blue-600 hover:bg-blue-700 text-white rounded px-6 py-2">
                <i class="fas fa-download mr-2"></i> Create Backup
            </button>
        </div>
        <div id="exportProgress" class="hidden mt-4">
            <div class="bg-blue-50 border border-blue-200 rounded p-4">
                <div class="flex items-center">
                    <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-blue-600 mr-3"></div>
                    <span class="text-blue-700">Creating backup... This may take a few moments.</span>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($user['role'] === 'system_admin'): ?>
    <!-- Auto-Backup Configuration Section (Admin Only) -->
    <div class="bg-white rounded-lg shadow-lg p-6 mb-6 border border-gray-200">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-2xl font-bold text-gray-800 mb-2">
                    <i class="fas fa-cog text-blue-500 mr-2"></i> Auto-Backup Configuration
                </h2>
                <p class="text-gray-600">Configure automatic backups for companies. Backups will run automatically at the specified time and be sent to your chosen destination.</p>
            </div>
        </div>
        
        <!-- Loading State -->
        <div id="backupSettingsLoading" class="text-center py-8">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
            <p class="mt-2 text-gray-600">Loading backup settings...</p>
        </div>
        
        <!-- Error State -->
        <div id="backupSettingsError" class="hidden bg-red-50 border border-red-200 rounded p-4 mb-4">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle text-red-600 mr-2"></i>
                <p class="text-red-800" id="backupSettingsErrorMessage">Failed to load backup settings</p>
            </div>
        </div>
        
        <!-- Settings Table -->
        <div id="backupSettingsTable" class="hidden overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 border border-gray-200 rounded-lg">
                <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Company Name</th>
                        <th class="px-6 py-4 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider">Auto-Backup Enabled</th>
                        <th class="px-6 py-4 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider">Backup Time</th>
                        <th class="px-6 py-4 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider">Destination</th>
                        <th class="px-6 py-4 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody id="backupSettingsBody" class="bg-white divide-y divide-gray-200">
                    <!-- Settings will be loaded here -->
                </tbody>
            </table>
        </div>
        
        <!-- Empty State -->
        <div id="backupSettingsEmpty" class="hidden text-center py-12">
            <i class="fas fa-building text-gray-400 text-5xl mb-4"></i>
            <p class="text-gray-600 text-lg font-medium mb-2">No companies found</p>
            <p class="text-gray-500 text-sm">Companies will appear here once they are created in the system.</p>
        </div>
    </div>

    <!-- Automatic Backups Section (Admin Only) -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">
            <i class="fas fa-clock text-purple-500 mr-2"></i> Automatic Backups
        </h2>
        <div class="mb-4 p-4 bg-purple-50 border border-purple-200 rounded">
            <p class="text-sm text-purple-800 mb-2">
                <strong>Automatic backups</strong> are created daily at the configured time for companies with auto-backup enabled.
                They are automatically deleted after 30 days to save disk space.
            </p>
            <button id="btnRunScheduled" class="bg-purple-600 hover:bg-purple-700 text-white rounded px-4 py-2 text-sm">
                <i class="fas fa-play mr-2"></i> Run Scheduled Backups Now
            </button>
        </div>
        <div id="automaticBackupsStats" class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
            <!-- Stats will be loaded here -->
        </div>
        <div id="automaticBackupsTable">
            <!-- Automatic backups will be loaded here -->
        </div>
    </div>
    <?php endif; ?>

    <!-- Backup History -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4">
            <h2 class="text-xl font-semibold text-gray-800 mb-4 sm:mb-0">
                <i class="fas fa-history text-gray-500 mr-2"></i> Backup History
            </h2>
            <div class="flex gap-2">
                <button id="btnBulkDelete" class="hidden bg-red-600 hover:bg-red-700 text-white rounded px-4 py-2 text-sm" onclick="bulkDeleteBackups()">
                    <i class="fas fa-trash mr-2"></i> Delete Selected
                </button>
            </div>
        </div>
        
        <!-- Filters and Search -->
        <div class="mb-4 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Search -->
                <div class="lg:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                    <input type="text" id="searchInput" placeholder="Search by filename or company..." 
                           class="border border-gray-300 rounded px-4 py-2 w-full">
                </div>
                
                <?php if ($user['role'] === 'system_admin'): ?>
                <!-- Company Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Company</label>
                    <select id="filterCompany" class="border border-gray-300 rounded px-4 py-2 w-full">
                        <option value="">-- All Companies --</option>
                        <?php foreach ($GLOBALS['companies'] ?? [] as $company): ?>
                            <option value="<?= $company['id'] ?>"><?= htmlspecialchars($company['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <!-- Backup Type Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                    <select id="filterType" class="border border-gray-300 rounded px-4 py-2 w-full">
                        <option value="">All Types</option>
                        <option value="manual">Manual</option>
                        <option value="automatic">Automatic</option>
                    </select>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Status Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select id="filterStatus" class="border border-gray-300 rounded px-4 py-2 w-full">
                        <option value="">All Statuses</option>
                        <option value="completed">Completed</option>
                        <option value="failed">Failed</option>
                        <option value="in_progress">In Progress</option>
                    </select>
                </div>
                
                <!-- Date From -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                    <input type="date" id="filterDateFrom" class="border border-gray-300 rounded px-4 py-2 w-full">
                </div>
                
                <!-- Date To -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                    <input type="date" id="filterDateTo" class="border border-gray-300 rounded px-4 py-2 w-full">
                </div>
            </div>
            
            <div class="flex gap-2">
                <button onclick="applyFilters()" class="bg-blue-600 hover:bg-blue-700 text-white rounded px-4 py-2 text-sm">
                    <i class="fas fa-filter mr-2"></i> Apply Filters
                </button>
                <button onclick="clearFilters()" class="bg-gray-300 hover:bg-gray-400 text-gray-700 rounded px-4 py-2 text-sm">
                    <i class="fas fa-times mr-2"></i> Clear
                </button>
            </div>
        </div>
        
        <div id="backupsTable">
            <!-- Backups will be loaded here -->
        </div>
        
        <!-- Pagination -->
        <div id="paginationContainer" class="mt-4 flex items-center justify-between">
            <!-- Pagination will be loaded here -->
        </div>
    </div>
</div>

<script>
    // BASE is already declared in simple_layout.php, don't redeclare it
    const COMPANY_ID = <?= $companyId ?? 'null' ?>;

    const IS_ADMIN = <?= ($user['role'] === 'system_admin') ? 'true' : 'false' ?>;

    let currentPage = 1;
    let currentLimit = 20;
    let currentFilters = {};

    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('btnExportBackup').addEventListener('click', exportBackup);
        
        // Search with debounce
        const searchInput = document.getElementById('searchInput');
        let searchTimeout;
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    currentPage = 1; // Reset to first page on search
                    loadBackups();
                }, 500);
            });
        }
        
        // Filter change handlers
        const filterCompany = document.getElementById('filterCompany');
        if (filterCompany) {
            filterCompany.addEventListener('change', function() {
                currentPage = 1;
                loadBackups();
            });
        }
        
        const filterType = document.getElementById('filterType');
        if (filterType) {
            filterType.addEventListener('change', function() {
                currentPage = 1;
                loadBackups();
            });
        }
        
        const filterStatus = document.getElementById('filterStatus');
        if (filterStatus) {
            filterStatus.addEventListener('change', function() {
                currentPage = 1;
                loadBackups();
            });
        }
        
        const filterDateFrom = document.getElementById('filterDateFrom');
        if (filterDateFrom) {
            filterDateFrom.addEventListener('change', function() {
                currentPage = 1;
                loadBackups();
            });
        }
        
        const filterDateTo = document.getElementById('filterDateTo');
        if (filterDateTo) {
            filterDateTo.addEventListener('change', function() {
                currentPage = 1;
                loadBackups();
            });
        }
        
        if (IS_ADMIN) {
            document.getElementById('btnRunScheduled').addEventListener('click', runScheduledBackups);
            loadAutomaticBackups();
            loadBackupStats();
            loadBackupSettings();
        }
        
        // Load initial backups
        loadBackups();
    });

    // Load backup settings for all companies
    async function loadBackupSettings() {
        const loadingEl = document.getElementById('backupSettingsLoading');
        const errorEl = document.getElementById('backupSettingsError');
        const errorMsgEl = document.getElementById('backupSettingsErrorMessage');
        const tableEl = document.getElementById('backupSettingsTable');
        const emptyEl = document.getElementById('backupSettingsEmpty');
        
        // Show loading, hide others
        if (loadingEl) loadingEl.classList.remove('hidden');
        if (errorEl) errorEl.classList.add('hidden');
        if (tableEl) tableEl.classList.add('hidden');
        if (emptyEl) emptyEl.classList.add('hidden');
        
        try {
            // Get companies from PHP
            let companies = <?= json_encode($GLOBALS['companies'] ?? []) ?>;
            
            // If no companies from PHP, try to fetch from API
            if (!companies || companies.length === 0) {
                try {
                    const companiesResponse = await fetch(`${BASE}/api/companies`);
                    if (companiesResponse.ok) {
                        const companiesData = await companiesResponse.json();
                        if (companiesData.success && companiesData.companies) {
                            companies = companiesData.companies;
                        }
                    }
                } catch (e) {
                    console.warn('Could not fetch companies from API:', e);
                }
            }
            
            if (!companies || companies.length === 0) {
                if (loadingEl) loadingEl.classList.add('hidden');
                if (emptyEl) emptyEl.classList.remove('hidden');
                return;
            }
            
            // Fetch backup settings
            const response = await fetch(`${BASE}/api/backup-settings`);
            const data = await response.json();
            
            if (loadingEl) loadingEl.classList.add('hidden');
            
            if (data.success) {
                displayBackupSettings(companies, data.settings || []);
                if (tableEl) tableEl.classList.remove('hidden');
            } else {
                if (errorMsgEl) errorMsgEl.textContent = data.error || 'Failed to load backup settings';
                if (errorEl) errorEl.classList.remove('hidden');
            }
        } catch (error) {
            console.error('Error loading backup settings:', error);
            if (loadingEl) loadingEl.classList.add('hidden');
            if (errorMsgEl) errorMsgEl.textContent = 'Error loading backup settings: ' + (error.message || 'Unknown error');
            if (errorEl) errorEl.classList.remove('hidden');
        }
    }

    // Display backup settings in table
    function displayBackupSettings(companies, settings) {
        const tbody = document.getElementById('backupSettingsBody');
        
        if (!companies || companies.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">No companies found</td></tr>';
            return;
        }
        
        // Create a map of company_id to settings
        const settingsMap = {};
        if (settings && settings.length > 0) {
            settings.forEach(s => {
                if (s.company_id) {
                    settingsMap[s.company_id] = s;
                }
            });
        }
        
        tbody.innerHTML = companies.map(company => {
            const setting = settingsMap[company.id] || {
                company_id: company.id,
                auto_backup_enabled: 0,
                backup_time: '02:00:00',
                backup_destination: 'email'
            };
            
            const isEnabled = setting.auto_backup_enabled == 1 || setting.auto_backup_enabled === true;
            const backupTime = setting.backup_time || '02:00:00';
            const destination = setting.backup_destination || 'email';
            
            return `
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <i class="fas fa-building text-blue-500 mr-2"></i>
                            <span class="text-sm font-semibold text-gray-900">${escapeHtml(company.name)}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                        <label class="inline-flex items-center cursor-pointer">
                            <input type="checkbox" 
                                   class="backup-enabled-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500 focus:ring-2" 
                                   data-company-id="${company.id}"
                                   ${isEnabled ? 'checked' : ''}
                                   onchange="updateBackupSetting(${company.id}, 'auto_backup_enabled', this.checked ? 1 : 0)">
                            <span class="ml-2 text-sm ${isEnabled ? 'text-green-700 font-medium' : 'text-gray-500'}">
                                ${isEnabled ? 'Enabled' : 'Disabled'}
                            </span>
                        </label>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                        <input type="time" 
                               class="backup-time-input border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               data-company-id="${company.id}"
                               value="${backupTime}"
                               onchange="updateBackupSetting(${company.id}, 'backup_time', this.value)">
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                        <select class="backup-destination-select border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white min-w-[140px]"
                                data-company-id="${company.id}"
                                onchange="updateBackupSetting(${company.id}, 'backup_destination', this.value)">
                            <option value="email" ${destination === 'email' ? 'selected' : ''}>📧 Email</option>
                            <option value="cloudinary" ${destination === 'cloudinary' ? 'selected' : ''}>☁️ Cloudinary</option>
                            <option value="both" ${destination === 'both' ? 'selected' : ''}>📧☁️ Both</option>
                        </select>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                        <button onclick="saveBackupSettings(${company.id})" 
                                class="bg-blue-600 hover:bg-blue-700 text-white rounded-md px-4 py-2 text-sm font-medium transition-colors shadow-sm hover:shadow-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                            <i class="fas fa-save mr-1"></i> Save
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
    }

    // Update a single backup setting
    async function updateBackupSetting(companyId, field, value) {
        // This will be called on change, but we'll batch save on button click
        // Store the change in a temporary object
        if (!window.pendingBackupSettings) {
            window.pendingBackupSettings = {};
        }
        if (!window.pendingBackupSettings[companyId]) {
            window.pendingBackupSettings[companyId] = {};
        }
        window.pendingBackupSettings[companyId][field] = value;
    }

    // Save backup settings for a company
    async function saveBackupSettings(companyId) {
        try {
            // Get current form values
            const row = document.querySelector(`tr:has([data-company-id="${companyId}"])`);
            if (!row) {
                alert('Could not find company settings row');
                return;
            }
            
            const enabledCheckbox = row.querySelector('.backup-enabled-checkbox');
            const timeInput = row.querySelector('.backup-time-input');
            const destinationSelect = row.querySelector('.backup-destination-select');
            const saveButton = row.querySelector('button[onclick*="saveBackupSettings"]');
            
            if (!enabledCheckbox || !timeInput || !destinationSelect) {
                alert('Could not find all required form fields');
                return;
            }
            
            // Disable button during save
            const originalButtonText = saveButton.innerHTML;
            saveButton.disabled = true;
            saveButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Saving...';
            
            const settings = {
                auto_backup_enabled: enabledCheckbox.checked ? 1 : 0,
                backup_time: timeInput.value,
                backup_destination: destinationSelect.value
            };
            
            // Send update
            const updateResponse = await fetch(`${BASE}/api/company/${companyId}/backup-settings`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(settings)
            });
            
            const updateData = await updateResponse.json();
            
            if (updateData.success) {
                // Show success message
                saveButton.innerHTML = '<i class="fas fa-check mr-1"></i> Saved!';
                saveButton.classList.remove('bg-blue-600', 'hover:bg-blue-700');
                saveButton.classList.add('bg-green-600', 'hover:bg-green-700');
                
                // Clear pending changes
                if (window.pendingBackupSettings && window.pendingBackupSettings[companyId]) {
                    delete window.pendingBackupSettings[companyId];
                }
                
                // Reset button after 2 seconds
                setTimeout(() => {
                    saveButton.disabled = false;
                    saveButton.innerHTML = originalButtonText;
                    saveButton.classList.remove('bg-green-600', 'hover:bg-green-700');
                    saveButton.classList.add('bg-blue-600', 'hover:bg-blue-700');
                }, 2000);
                
                // Update enabled text
                const enabledText = row.querySelector('span[class*="text-"]');
                if (enabledText) {
                    if (settings.auto_backup_enabled) {
                        enabledText.textContent = 'Enabled';
                        enabledText.classList.remove('text-gray-500');
                        enabledText.classList.add('text-green-700', 'font-medium');
                    } else {
                        enabledText.textContent = 'Disabled';
                        enabledText.classList.remove('text-green-700', 'font-medium');
                        enabledText.classList.add('text-gray-500');
                    }
                }
            } else {
                saveButton.disabled = false;
                saveButton.innerHTML = originalButtonText;
                alert('Failed to save backup settings: ' + (updateData.error || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error saving backup settings:', error);
            const row = document.querySelector(`tr:has([data-company-id="${companyId}"])`);
            if (row) {
                const saveButton = row.querySelector('button[onclick*="saveBackupSettings"]');
                if (saveButton) {
                    saveButton.disabled = false;
                    saveButton.innerHTML = '<i class="fas fa-save mr-1"></i> Save';
                }
            }
            alert('Error saving backup settings: ' + error.message);
        }
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    async function exportBackup() {
        const btn = document.getElementById('btnExportBackup');
        const progress = document.getElementById('exportProgress');
        // Always use JSON format (which gets zipped) for compatibility with restore points
        const format = 'json';

        btn.disabled = true;
        progress.classList.remove('hidden');

        try {
            const formData = new FormData();
            formData.append('format', format);
            
            // For system_admin, include selected company_id
            if (IS_ADMIN) {
                const selectCompany = document.getElementById('selectCompany');
                if (selectCompany && selectCompany.value) {
                    formData.append('company_id', selectCompany.value);
                }
            }

            const response = await fetch(`${BASE}/dashboard/backup/export`, {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            if (data.success) {
                const backupType = data.backup_type === 'system' ? 'System Backup' : 'Company Backup';
                const message = `${backupType} created successfully!\n\nFilename: ${data.filename}\nSize: ${formatFileSize(data.size)}\nRecords: ${data.record_count.toLocaleString()}`;
                alert(message);
                // Add a small delay to ensure database commit is complete before refreshing
                setTimeout(() => {
                    loadBackups();
                    if (IS_ADMIN) {
                        loadBackupStats();
                        loadAutomaticBackups();
                    }
                }, 500);
            } else {
                alert('Failed to create backup: ' + (data.error || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error exporting backup:', error);
            alert('Error creating backup');
        } finally {
            btn.disabled = false;
            progress.classList.add('hidden');
        }
    }

    function getFilters() {
        const filters = {};
        
        const search = document.getElementById('searchInput')?.value.trim();
        if (search) filters.search = search;
        
        const filterType = document.getElementById('filterType')?.value;
        if (filterType) filters.backup_type = filterType;
        
        const filterStatus = document.getElementById('filterStatus')?.value;
        if (filterStatus) filters.status = filterStatus;
        
        const filterDateFrom = document.getElementById('filterDateFrom')?.value;
        if (filterDateFrom) filters.date_from = filterDateFrom;
        
        const filterDateTo = document.getElementById('filterDateTo')?.value;
        if (filterDateTo) filters.date_to = filterDateTo;
        
        return filters;
    }
    
    function applyFilters() {
        currentPage = 1;
        currentFilters = getFilters();
        loadBackups();
    }
    
    function clearFilters() {
        document.getElementById('searchInput').value = '';
        const filterCompany = document.getElementById('filterCompany');
        if (filterCompany) filterCompany.value = '';
        document.getElementById('filterType').value = '';
        document.getElementById('filterStatus').value = '';
        document.getElementById('filterDateFrom').value = '';
        document.getElementById('filterDateTo').value = '';
        currentPage = 1;
        currentFilters = {};
        loadBackups();
    }
    
    async function loadBackups() {
        try {
            currentFilters = getFilters();
            
            let url;
            if (IS_ADMIN) {
                const filterCompany = document.getElementById('filterCompany');
                const companyId = filterCompany ? filterCompany.value : null;
                const params = new URLSearchParams({
                    page: currentPage,
                    limit: currentLimit,
                    ...currentFilters
                });
                if (companyId) params.append('company_id', companyId);
                url = `${BASE}/api/backups?${params.toString()}`;
            } else {
                if (!COMPANY_ID) {
                    const panel = document.getElementById('backupsTable');
                    panel.innerHTML = '<p class="text-gray-500 text-sm">No company association found. Please contact your administrator.</p>';
                    return;
                }
                const params = new URLSearchParams({
                    page: currentPage,
                    limit: currentLimit,
                    ...currentFilters
                });
                url = `${BASE}/api/company/${COMPANY_ID}/backups?${params.toString()}`;
            }
            
            const response = await fetch(url);
            const data = await response.json();

            if (data.success) {
                displayBackups(data.backups || [], data);
                displayPagination(data);
            } else {
                console.error('Failed to load backups:', data.error);
                const panel = document.getElementById('backupsTable');
                panel.innerHTML = '<p class="text-red-500 text-sm">Error loading backups: ' + (data.error || 'Unknown error') + '</p>';
            }
        } catch (error) {
            console.error('Error loading backups:', error);
            const panel = document.getElementById('backupsTable');
            panel.innerHTML = '<p class="text-red-500 text-sm">Error loading backups. Please refresh the page.</p>';
        }
    }
    
    function goToPage(page) {
        currentPage = page;
        loadBackups();
    }
    
    function changeLimit(limit) {
        currentLimit = limit;
        currentPage = 1;
        loadBackups();
    }

    function displayBackups(backups, paginationData = {}) {
        const panel = document.getElementById('backupsTable');
        
        if (backups.length === 0) {
            panel.innerHTML = '<p class="text-gray-500 text-sm py-4">No backups found. Create your first backup above.</p>';
            return;
        }

        panel.innerHTML = `
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                <input type="checkbox" id="selectAll" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" onchange="toggleSelectAll(this)">
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Filename</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Company</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Size</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Records</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created By</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cloudinary</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        ${backups.map(backup => {
                            const isAutomatic = backup.backup_type === 'automatic' || (backup.description && backup.description.includes('AUTOMATIC'));
                            return `
                            <tr>
                                <td class="px-4 py-3 text-sm">
                                    <input type="checkbox" class="backup-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500" 
                                           value="${backup.id}" onchange="updateBulkDeleteButton()">
                                </td>
                                <td class="px-4 py-3 text-sm font-medium">${backup.file_name}</td>
                                <td class="px-4 py-3 text-sm">${backup.company_name || (backup.company_id ? `Company #${backup.company_id}` : 'System')}</td>
                                <td class="px-4 py-3 text-sm">
                                    <span class="px-2 py-1 rounded text-xs font-medium ${
                                        isAutomatic 
                                            ? 'bg-purple-100 text-purple-800' 
                                            : 'bg-blue-100 text-blue-800'
                                    }">
                                        ${isAutomatic ? 'Automatic' : 'Manual'}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm">${formatFileSize(backup.size || backup.file_size || 0)}</td>
                                <td class="px-4 py-3 text-sm">${(backup.record_count || 0).toLocaleString()}</td>
                                <td class="px-4 py-3 text-sm">${backup.created_by_name || backup.created_by_full_name || (isAutomatic ? 'System' : '-')}</td>
                                <td class="px-4 py-3 text-sm">${new Date(backup.created_at).toLocaleString()}</td>
                                <td class="px-4 py-3 text-sm">
                                    <span class="px-2 py-1 rounded text-xs font-medium ${
                                        backup.status === 'completed' ? 'bg-green-100 text-green-800' :
                                        backup.status === 'failed' ? 'bg-red-100 text-red-800' :
                                        'bg-yellow-100 text-yellow-800'
                                    }">${backup.status}</span>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    ${backup.cloudinary_url ? `
                                        <a href="${backup.cloudinary_url}" target="_blank" 
                                           class="text-green-600 hover:text-green-800 inline-flex items-center" 
                                           title="View on Cloudinary">
                                            <i class="fas fa-cloud mr-1"></i>
                                            <span class="text-xs">Uploaded</span>
                                        </a>
                                    ` : `
                                        <span class="text-gray-400 text-xs" title="Not uploaded to Cloudinary">
                                            <i class="fas fa-cloud-slash mr-1"></i>Not uploaded
                                        </span>
                                    `}
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <div class="flex items-center gap-2">
                                        ${backup.file_exists !== false ? `
                                            <a href="${BASE}/dashboard/backup/download/${backup.id}" class="text-blue-600 hover:text-blue-800 inline-flex items-center" title="Download">
                                                <i class="fas fa-download"></i>
                                            </a>
                                        ` : '<span class="text-gray-400" title="File missing">-</span>'}
                                        ${backup.cloudinary_url ? `
                                            <a href="${backup.cloudinary_url}" target="_blank" 
                                               class="text-green-600 hover:text-green-800 inline-flex items-center" 
                                               title="View on Cloudinary">
                                                <i class="fas fa-cloud"></i>
                                            </a>
                                        ` : ''}
                                        <button onclick="deleteBackup(${backup.id}, '${backup.file_name}')" 
                                                class="text-red-600 hover:text-red-800 inline-flex items-center" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        `;
                        }).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }
    
    function displayPagination(data) {
        const container = document.getElementById('paginationContainer');
        if (!container) return;
        
        const total = data.total || 0;
        const page = data.page || 1;
        const totalPages = data.total_pages || 1;
        
        if (totalPages <= 1) {
            container.innerHTML = `
                <div class="text-sm text-gray-600">
                    Showing ${total} backup${total !== 1 ? 's' : ''}
                </div>
                <div class="flex items-center gap-2">
                    <label class="text-sm text-gray-600">Per page:</label>
                    <select onchange="changeLimit(this.value)" class="border border-gray-300 rounded px-2 py-1 text-sm">
                        <option value="10" ${currentLimit === 10 ? 'selected' : ''}>10</option>
                        <option value="20" ${currentLimit === 20 ? 'selected' : ''}>20</option>
                        <option value="50" ${currentLimit === 50 ? 'selected' : ''}>50</option>
                        <option value="100" ${currentLimit === 100 ? 'selected' : ''}>100</option>
                    </select>
                </div>
            `;
            return;
        }
        
        const start = ((page - 1) * currentLimit) + 1;
        const end = Math.min(page * currentLimit, total);
        
        let paginationHTML = `
            <div class="text-sm text-gray-600">
                Showing ${start}-${end} of ${total} backup${total !== 1 ? 's' : ''}
            </div>
            <div class="flex items-center gap-2">
                <button onclick="goToPage(${page - 1})" ${page <= 1 ? 'disabled' : ''} 
                        class="px-3 py-1 border border-gray-300 rounded text-sm ${page <= 1 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-50'}">
                    Previous
                </button>
                <span class="text-sm text-gray-600">
                    Page ${page} of ${totalPages}
                </span>
                <button onclick="goToPage(${page + 1})" ${page >= totalPages ? 'disabled' : ''} 
                        class="px-3 py-1 border border-gray-300 rounded text-sm ${page >= totalPages ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-50'}">
                    Next
                </button>
                <label class="text-sm text-gray-600 ml-4">Per page:</label>
                <select onchange="changeLimit(this.value)" class="border border-gray-300 rounded px-2 py-1 text-sm">
                    <option value="10" ${currentLimit === 10 ? 'selected' : ''}>10</option>
                    <option value="20" ${currentLimit === 20 ? 'selected' : ''}>20</option>
                    <option value="50" ${currentLimit === 50 ? 'selected' : ''}>50</option>
                    <option value="100" ${currentLimit === 100 ? 'selected' : ''}>100</option>
                </select>
            </div>
        `;
        
        container.innerHTML = paginationHTML;
    }
    
    function toggleSelectAll(checkbox) {
        const checkboxes = document.querySelectorAll('.backup-checkbox');
        checkboxes.forEach(cb => cb.checked = checkbox.checked);
        updateBulkDeleteButton();
    }
    
    function updateBulkDeleteButton() {
        const selected = document.querySelectorAll('.backup-checkbox:checked');
        const btn = document.getElementById('btnBulkDelete');
        if (btn) {
            if (selected.length > 0) {
                btn.classList.remove('hidden');
                btn.innerHTML = `<i class="fas fa-trash mr-2"></i> Delete Selected (${selected.length})`;
            } else {
                btn.classList.add('hidden');
            }
        }
    }
    
    async function deleteBackup(backupId, filename) {
        if (!confirm(`Are you sure you want to delete backup "${filename}"?\n\nThis action cannot be undone.`)) {
            return;
        }
        
        try {
            // Try DELETE first, fallback to POST if needed
            let response = await fetch(`${BASE}/api/backups/${backupId}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json'
                }
            });
            
            // If DELETE fails, try POST fallback
            if (!response.ok && response.status === 404) {
                response = await fetch(`${BASE}/api/backups/${backupId}/delete`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });
            }
            
            const data = await response.json();
            
            if (data.success) {
                alert('Backup deleted successfully');
                loadBackups();
            } else {
                alert('Failed to delete backup: ' + (data.error || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error deleting backup:', error);
            alert('Error deleting backup');
        }
    }
    
    async function bulkDeleteBackups() {
        const selected = Array.from(document.querySelectorAll('.backup-checkbox:checked')).map(cb => parseInt(cb.value));
        
        if (selected.length === 0) {
            alert('Please select at least one backup to delete');
            return;
        }
        
        if (!confirm(`Are you sure you want to delete ${selected.length} backup(s)?\n\nThis action cannot be undone.`)) {
            return;
        }
        
        try {
            const response = await fetch(`${BASE}/api/backups/bulk-delete`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ backup_ids: selected })
            });
            
            const data = await response.json();
            
            if (data.success) {
                const message = data.errors && data.errors.length > 0
                    ? `Deleted ${data.deleted} of ${data.total} backups.\n\nErrors:\n${data.errors.join('\n')}`
                    : `Successfully deleted ${data.deleted} backup(s)`;
                alert(message);
                loadBackups();
                updateBulkDeleteButton();
            } else {
                alert('Failed to delete backups: ' + (data.error || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error bulk deleting backups:', error);
            alert('Error deleting backups');
        }
    }

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }

    <?php if ($user['role'] === 'system_admin'): ?>
    async function runScheduledBackups() {
        const btn = document.getElementById('btnRunScheduled');
        const originalText = btn.innerHTML;
        
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Running...';
        
        try {
            const response = await fetch(`${BASE}/api/admin/backups/run-scheduled`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'same-origin'
            });
            
            const data = await response.json();
            
            if (data.success) {
                const results = data.results;
                let message = `Scheduled backups completed!\n\n`;
                message += `Companies: ${results.companies.filter(c => c.success).length}/${results.companies.length}\n`;
                if (results.system && results.system.success) {
                    message += `System backup: Created (ID: ${results.system.backup_id})\n`;
                }
                if (results.errors && results.errors.length > 0) {
                    message += `\nErrors: ${results.errors.length}`;
                }
                alert(message);
                loadAutomaticBackups();
                loadBackupStats();
            } else {
                alert('Failed to run scheduled backups: ' + (data.error || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error running scheduled backups:', error);
            alert('Error running scheduled backups');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }

    async function loadBackupStats() {
        try {
            const response = await fetch(`${BASE}/api/admin/backups/stats`);
            const data = await response.json();
            
            if (data.success && data.stats) {
                const stats = data.stats;
                const statsPanel = document.getElementById('automaticBackupsStats');
                
                statsPanel.innerHTML = `
                    <div class="bg-blue-50 rounded p-4">
                        <div class="text-sm text-gray-600">Total Automatic</div>
                        <div class="text-2xl font-bold text-gray-900">${stats.total_automatic || 0}</div>
                    </div>
                    <div class="bg-green-50 rounded p-4">
                        <div class="text-sm text-gray-600">System Backups</div>
                        <div class="text-2xl font-bold text-gray-900">${stats.system_backups || 0}</div>
                    </div>
                    <div class="bg-purple-50 rounded p-4">
                        <div class="text-sm text-gray-600">Total Size</div>
                        <div class="text-2xl font-bold text-gray-900">${formatFileSize(stats.total_size || 0)}</div>
                    </div>
                    <div class="bg-yellow-50 rounded p-4">
                        <div class="text-sm text-gray-600">Companies</div>
                        <div class="text-2xl font-bold text-gray-900">${stats.by_company ? stats.by_company.length : 0}</div>
                    </div>
                `;
            }
        } catch (error) {
            console.error('Error loading backup stats:', error);
        }
    }

    async function loadAutomaticBackups() {
        try {
            // Load all automatic backups (for admin view)
            const response = await fetch(`${BASE}/api/admin/backups/stats`);
            const data = await response.json();
            
            // For now, we'll show stats. In a full implementation, you'd want a separate endpoint
            // to list all automatic backups with pagination
            const panel = document.getElementById('automaticBackupsTable');
            panel.innerHTML = `
                <div class="text-sm text-gray-600">
                    <p>Automatic backups are managed by the daily scheduler.</p>
                    <p class="mt-2">Use the backup history below to view and download automatic backups.</p>
                </div>
            `;
        } catch (error) {
            console.error('Error loading automatic backups:', error);
        }
    }
    <?php endif; ?>

    async function downloadBackup(filename) {
        try {
            const response = await fetch(`${BASE}/api/company/${COMPANY_ID}/backups`);
            const data = await response.json();
            
            if (data.success) {
                const backup = data.backups.find(b => b.file_name === filename);
                if (backup && backup.file_path) {
                    // Extract relative path from absolute path
                    const relativePath = backup.file_path.replace(/^.*storage\/backups\//, '');
                    const downloadUrl = `${BASE}/storage/backups/${relativePath}`;
                    
                    // Create download link
                    const link = document.createElement('a');
                    link.href = downloadUrl;
                    link.download = filename;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                } else {
                    alert('Backup file not found');
                }
            }
        } catch (error) {
            console.error('Error downloading backup:', error);
            alert('Error downloading backup');
        }
    }
</script>

