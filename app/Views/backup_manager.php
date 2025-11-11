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

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Data Backup & Restore</h1>
    <p class="text-gray-600 mb-6">Export and restore your company data safely</p>

    <!-- Export Backup Section -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">
            <i class="fas fa-download text-blue-500 mr-2"></i> Export Backup
        </h2>
        <div class="flex gap-4 items-end">
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 mb-1">Format</label>
                <select id="exportFormat" class="border border-gray-300 rounded px-4 py-2 w-full">
                    <option value="json">JSON (Recommended)</option>
                    <option value="sql">SQL</option>
                </select>
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

    <!-- Import Backup Section -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">
            <i class="fas fa-upload text-green-500 mr-2"></i> Import Backup
        </h2>
        <div class="bg-yellow-50 border border-yellow-200 rounded p-4 mb-4">
            <div class="flex">
                <i class="fas fa-exclamation-triangle text-yellow-600 mr-2"></i>
                <div class="text-sm text-yellow-800">
                    <strong>Warning:</strong> Importing a backup will replace your current data. This action cannot be undone. 
                    Make sure to create a backup before importing.
                </div>
            </div>
        </div>
        <form id="importBackupForm" enctype="multipart/form-data">
            <div class="flex gap-4 items-end">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Backup File</label>
                    <input type="file" id="backupFile" name="backup_file" accept=".zip,.json" class="border border-gray-300 rounded px-4 py-2 w-full" required>
                </div>
                <button type="submit" id="btnImportBackup" class="bg-green-600 hover:bg-green-700 text-white rounded px-6 py-2">
                    <i class="fas fa-upload mr-2"></i> Import Backup
                </button>
            </div>
        </form>
        <div id="importProgress" class="hidden mt-4">
            <div class="bg-green-50 border border-green-200 rounded p-4">
                <div class="flex items-center">
                    <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-green-600 mr-3"></div>
                    <span class="text-green-700">Importing backup... This may take several minutes.</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Backup History -->
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">
            <i class="fas fa-history text-gray-500 mr-2"></i> Backup History
        </h2>
        <div id="backupsTable">
            <!-- Backups will be loaded here -->
        </div>
    </div>
</div>

<script>
    const BASE = '<?= BASE_URL_PATH ?>';
    const COMPANY_ID = <?= $companyId ?? 'null' ?>;

    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('btnExportBackup').addEventListener('click', exportBackup);
        document.getElementById('importBackupForm').addEventListener('submit', importBackup);
        loadBackups();
    });

    async function exportBackup() {
        const btn = document.getElementById('btnExportBackup');
        const progress = document.getElementById('exportProgress');
        const format = document.getElementById('exportFormat').value;

        btn.disabled = true;
        progress.classList.remove('hidden');

        try {
            const formData = new FormData();
            formData.append('format', format);

            const response = await fetch(`${BASE}/dashboard/backup/export`, {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            if (data.success) {
                alert(`Backup created successfully!\n\nFilename: ${data.filename}\nSize: ${formatFileSize(data.size)}\nRecords: ${data.record_count.toLocaleString()}`);
                loadBackups();
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

    async function importBackup(e) {
        e.preventDefault();
        
        const form = document.getElementById('importBackupForm');
        const btn = document.getElementById('btnImportBackup');
        const progress = document.getElementById('importProgress');
        const fileInput = document.getElementById('backupFile');

        if (!fileInput.files || !fileInput.files[0]) {
            alert('Please select a backup file');
            return;
        }

        if (!confirm('Are you sure you want to import this backup? This will replace your current data!')) {
            return;
        }

        btn.disabled = true;
        progress.classList.remove('hidden');

        try {
            const formData = new FormData(form);

            const response = await fetch(`${BASE}/dashboard/backup/import`, {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            if (data.success) {
                alert(`Backup imported successfully!\n\nRecords imported: ${data.record_count.toLocaleString()}\nTables: ${data.tables_imported}`);
                loadBackups();
            } else {
                alert('Failed to import backup: ' + (data.error || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error importing backup:', error);
            alert('Error importing backup');
        } finally {
            btn.disabled = false;
            progress.classList.add('hidden');
            fileInput.value = '';
        }
    }

    async function loadBackups() {
        try {
            const response = await fetch(`${BASE}/api/company/${COMPANY_ID}/backups`);
            const data = await response.json();

            if (data.success && data.backups) {
                displayBackups(data.backups);
            }
        } catch (error) {
            console.error('Error loading backups:', error);
        }
    }

    function displayBackups(backups) {
        const panel = document.getElementById('backupsTable');
        
        if (backups.length === 0) {
            panel.innerHTML = '<p class="text-gray-500 text-sm">No backups available. Create your first backup above.</p>';
            return;
        }

        panel.innerHTML = `
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Filename</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Size</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Records</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created By</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        ${backups.map(backup => `
                            <tr>
                                <td class="px-4 py-3 text-sm">${backup.file_name}</td>
                                <td class="px-4 py-3 text-sm">${formatFileSize(backup.size || backup.file_size || 0)}</td>
                                <td class="px-4 py-3 text-sm">${(backup.record_count || 0).toLocaleString()}</td>
                                <td class="px-4 py-3 text-sm">${backup.created_by_name || backup.created_by_full_name || '-'}</td>
                                <td class="px-4 py-3 text-sm">${new Date(backup.created_at).toLocaleString()}</td>
                                <td class="px-4 py-3 text-sm">
                                    <span class="px-2 py-1 rounded text-xs font-medium ${
                                        backup.status === 'completed' ? 'bg-green-100 text-green-800' :
                                        backup.status === 'failed' ? 'bg-red-100 text-red-800' :
                                        'bg-yellow-100 text-yellow-800'
                                    }">${backup.status}</span>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    ${backup.file_exists !== false ? `
                                        <a href="${BASE}/dashboard/backup/download/${backup.id}" class="text-blue-600 hover:text-blue-800 mr-3">
                                            <i class="fas fa-download mr-1"></i> Download
                                        </a>
                                    ` : '<span class="text-gray-400">File missing</span>'}
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }

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

<?php
require __DIR__ . '/simple_layout.php';
?>

