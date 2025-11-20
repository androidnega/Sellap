<div class="p-6">
  <!-- Page Header -->
  <div class="mb-6">
    <div class="flex items-center justify-between mb-4">
      <div>
        <h2 class="text-3xl font-bold text-gray-800">
          <i class="fas fa-history mr-2 text-blue-600"></i>
          Restore Points
        </h2>
        <p class="text-gray-600">Company: <span class="font-semibold"><?= htmlspecialchars($company['name']) ?></span> (ID: <?= $companyId ?>)</p>
      </div>
      <div class="flex gap-2">
        <a href="<?= BASE_URL_PATH ?>/dashboard/companies/view/<?= $companyId ?>" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition text-sm font-medium">
          <i class="fas fa-arrow-left mr-2"></i>Back to Company
        </a>
      </div>
    </div>
  </div>

  <!-- Statistics -->
  <?php if ($stats): ?>
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4">
      <div class="text-sm text-gray-600">Total Restore Points</div>
      <div class="text-2xl font-bold text-gray-900"><?= $stats['total_restore_points'] ?? 0 ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
      <div class="text-sm text-gray-600">Total Restores</div>
      <div class="text-2xl font-bold text-gray-900"><?= $stats['total_restores'] ?? 0 ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
      <div class="text-sm text-gray-600">Latest Restore Point</div>
      <div class="text-sm font-semibold text-gray-900">
        <?= $stats['latest_restore_point'] ? date('M j, Y g:i A', strtotime($stats['latest_restore_point'])) : 'None' ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Create Restore Point Section -->
  <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">
      <i class="fas fa-plus-circle mr-2 text-green-600"></i>Create New Restore Point
    </h3>
    
    <form id="createRestorePointForm" class="space-y-4">
      <input type="hidden" id="companyId" value="<?= $companyId ?>">
      
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Restore Point Name *</label>
        <input type="text" id="restorePointName" required
               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
               placeholder="e.g., Before Major Update - <?= date('M j, Y') ?>">
      </div>
      
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Description (Optional)</label>
        <textarea id="restorePointDescription" rows="2"
                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  placeholder="Describe what this restore point contains..."></textarea>
      </div>
      
      <div>
        <div class="flex items-center justify-between mb-2">
          <label class="block text-sm font-medium text-gray-700">Select Backup *</label>
          <button type="button" id="refreshBackupsBtn" 
                  class="text-sm text-blue-600 hover:text-blue-800 flex items-center"
                  title="Refresh backup list">
            <i class="fas fa-sync-alt mr-1"></i>Refresh
          </button>
        </div>
        <select id="backupSelect" required
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
          <option value="">-- Select a backup --</option>
          <?php foreach ($backups as $backup): ?>
          <option value="<?= $backup['id'] ?>" 
                  data-file="<?= htmlspecialchars($backup['file_name']) ?>" 
                  data-size="<?= $backup['file_size'] ?? 0 ?>"
                  data-date="<?= $backup['created_at'] ?>">
            <?= htmlspecialchars($backup['file_name']) ?> 
            (<?= $backup['file_size'] ? number_format($backup['file_size'] / 1024, 2) . ' KB' : 'N/A' ?>)
            - <?= date('M j, Y g:i A', strtotime($backup['created_at'])) ?>
          </option>
          <?php endforeach; ?>
        </select>
        <div id="backupStatus" class="text-sm text-gray-500 mt-2">
          <?php if (empty($backups)): ?>
          <p>
            <i class="fas fa-info-circle mr-1"></i>
            No backups available. <a href="<?= BASE_URL_PATH ?>/dashboard/backup" class="text-blue-600 hover:underline">Create a backup first</a>.
          </p>
          <?php else: ?>
          <p>
            <i class="fas fa-info-circle mr-1"></i>
            Showing backups for this company only. <a href="<?= BASE_URL_PATH ?>/dashboard/backup" class="text-blue-600 hover:underline">Create new backup</a>
          </p>
          <?php endif; ?>
        </div>
      </div>
      
      <div>
        <button type="submit" id="createRestorePointBtn"
                class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg font-semibold transition"
                <?= empty($backups) ? 'disabled' : '' ?>>
          <i class="fas fa-save mr-2"></i>Create Restore Point
        </button>
      </div>
    </form>
  </div>

  <!-- Restore Points List -->
  <div class="bg-white rounded-lg shadow-lg p-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">
      <i class="fas fa-list mr-2 text-blue-600"></i>Restore Points
    </h3>
    
    <?php if (empty($restorePoints)): ?>
    <div class="text-center py-8 text-gray-500">
      <i class="fas fa-history text-4xl mb-4"></i>
      <p>No restore points created yet.</p>
      <p class="text-sm">Create a restore point above to save the current state of your company data.</p>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Records</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Restored</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200" id="restorePointsTableBody">
          <?php foreach ($restorePoints as $rp): ?>
          <tr>
            <td class="px-4 py-3">
              <div class="font-medium text-gray-900"><?= htmlspecialchars($rp['name']) ?></div>
              <?php if ($rp['description']): ?>
              <div class="text-sm text-gray-500"><?= htmlspecialchars($rp['description']) ?></div>
              <?php endif; ?>
            </td>
            <td class="px-4 py-3 text-sm text-gray-600">
              <?= date('M j, Y g:i A', strtotime($rp['created_at'])) ?>
              <?php if ($rp['created_by_name']): ?>
              <div class="text-xs text-gray-500">by <?= htmlspecialchars($rp['created_by_name']) ?></div>
              <?php endif; ?>
            </td>
            <td class="px-4 py-3 text-sm text-gray-600">
              <?= $rp['total_records'] ? number_format($rp['total_records']) : 'N/A' ?>
            </td>
            <td class="px-4 py-3 text-sm text-gray-600">
              <div><?= $rp['restore_count'] ?> time(s)</div>
              <?php if ($rp['restored_at']): ?>
              <div class="text-xs text-gray-500">Last: <?= date('M j, Y g:i A', strtotime($rp['restored_at'])) ?></div>
              <?php endif; ?>
            </td>
            <td class="px-4 py-3">
              <div class="flex gap-2">
                <button onclick="showRestoreModal(<?= $rp['id'] ?>, '<?= htmlspecialchars($rp['name'], ENT_QUOTES) ?>')"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm font-medium">
                  <i class="fas fa-undo mr-1"></i>Restore
                </button>
                <button onclick="deleteRestorePoint(<?= $rp['id'] ?>, '<?= htmlspecialchars($rp['name'], ENT_QUOTES) ?>')"
                        class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-sm font-medium">
                  <i class="fas fa-trash mr-1"></i>Delete
                </button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Restore Modal -->
<div id="restoreModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 z-50 flex items-center justify-center">
  <div class="bg-white rounded-lg shadow-xl p-6 max-w-md w-full mx-4">
    <h3 class="text-xl font-semibold text-gray-900 mb-4">Restore from Point</h3>
    
    <div class="mb-4">
      <p class="text-gray-700 mb-2">Restore Point: <strong id="restorePointNameDisplay"></strong></p>
      <p class="text-sm text-gray-600 mb-4">This will restore your company data to the state saved in this restore point.</p>
    </div>
    
    <div class="mb-4">
      <label class="block text-sm font-medium text-gray-700 mb-2">Restore Type</label>
      <select id="restoreType" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
        <option value="overwrite">Overwrite Current Data (Recommended)</option>
        <option value="merge">Merge with Current Data</option>
      </select>
      <p class="text-xs text-gray-500 mt-1">
        <strong>Overwrite:</strong> Replaces all current data with restore point data.<br>
        <strong>Merge:</strong> Adds restore point data to existing data (may create duplicates).
      </p>
    </div>
    
    <div class="mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded">
      <p class="text-sm text-yellow-800">
        <i class="fas fa-exclamation-triangle mr-1"></i>
        <strong>Warning:</strong> This action will modify your company data. A backup of current state will be created automatically.
      </p>
    </div>
    
    <div class="flex justify-end space-x-3">
      <button onclick="closeRestoreModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400">
        Cancel
      </button>
      <button onclick="confirmRestore()" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
        <i class="fas fa-undo mr-2"></i>Confirm Restore
      </button>
    </div>
  </div>
</div>

<script>
// BASE is already declared in the layout
// const BASE = window.APP_BASE_PATH || ""; // Removed - already in layout
const companyId = <?= $companyId ?>;
let currentRestorePointId = null;

function getToken() {
    return localStorage.getItem("token") || localStorage.getItem("sellapp_token") || "";
}

// Create restore point
document.getElementById("createRestorePointForm")?.addEventListener("submit", async function(e) {
    e.preventDefault();
    
    const btn = document.getElementById("createRestorePointBtn");
    const name = document.getElementById("restorePointName").value.trim();
    const description = document.getElementById("restorePointDescription").value.trim();
    const backupId = document.getElementById("backupSelect").value;
    
    if (!name || !backupId) {
        alert("Please fill in all required fields");
        return;
    }
    
    btn.disabled = true;
    btn.innerHTML = "<i class=\"fas fa-spinner fa-spin mr-2\"></i>Creating...";
    
    try {
        const response = await fetch(BASE + "/api/restore-points/create", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Authorization": "Bearer " + getToken()
            },
            credentials: "same-origin",
            body: JSON.stringify({
                company_id: companyId,
                backup_id: backupId,
                name: name,
                description: description
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert("Restore point created successfully!");
            window.location.reload();
        } else {
            alert("Error: " + (data.error || "Failed to create restore point"));
        }
    } catch (error) {
        console.error("Error:", error);
        alert("Error creating restore point: " + error.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = "<i class=\"fas fa-save mr-2\"></i>Create Restore Point";
    }
});

// Show restore modal
function showRestoreModal(restorePointId, name) {
    currentRestorePointId = restorePointId;
    document.getElementById("restorePointNameDisplay").textContent = name;
    document.getElementById("restoreModal").classList.remove("hidden");
}

function closeRestoreModal() {
    document.getElementById("restoreModal").classList.add("hidden");
    currentRestorePointId = null;
}

// Confirm restore
async function confirmRestore() {
    if (!currentRestorePointId) return;
    
    if (!confirm("Are you absolutely sure you want to restore from this restore point? This action cannot be undone!")) {
        return;
    }
    
    const restoreType = document.getElementById("restoreType").value;
    const btn = document.querySelector("#restoreModal button:last-child");
    
    btn.disabled = true;
    btn.innerHTML = "<i class=\"fas fa-spinner fa-spin mr-2\"></i>Restoring...";
    
    try {
        const response = await fetch(BASE + "/api/restore-points/restore", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Authorization": "Bearer " + getToken()
            },
            credentials: "same-origin",
            body: JSON.stringify({
                restore_point_id: currentRestorePointId,
                company_id: companyId,
                restore_type: restoreType
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert(`Restore completed successfully!\n\nRecords restored: ${data.records_restored || 0}\nTables restored: ${data.tables_restored || 0}`);
            window.location.reload();
        } else {
            alert("Error: " + (data.error || "Failed to restore"));
        }
    } catch (error) {
        console.error("Error:", error);
        alert("Error restoring: " + error.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = "<i class=\"fas fa-undo mr-2\"></i>Confirm Restore";
        closeRestoreModal();
    }
}

// Delete restore point
async function deleteRestorePoint(restorePointId, name) {
    if (!confirm(`Are you sure you want to delete restore point "${name}"? This action cannot be undone.`)) {
        return;
    }
    
    try {
        const response = await fetch(BASE + "/api/restore-points/delete", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Authorization": "Bearer " + getToken()
            },
            credentials: "same-origin",
            body: JSON.stringify({
                restore_point_id: restorePointId,
                company_id: companyId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert("Restore point deleted successfully!");
            window.location.reload();
        } else {
            alert("Error: " + (data.error || "Failed to delete restore point"));
        }
    } catch (error) {
        console.error("Error:", error);
        alert("Error deleting restore point: " + error.message);
    }
}

// Refresh backups list
async function refreshBackups() {
    const btn = document.getElementById("refreshBackupsBtn");
    const select = document.getElementById("backupSelect");
    const status = document.getElementById("backupStatus");
    const originalHtml = btn.innerHTML;
    
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Refreshing...';
    
    try {
        const response = await fetch(BASE + `/api/company/${companyId}/backups?limit=100`, {
            method: "GET",
            headers: {
                "Content-Type": "application/json",
                "Authorization": "Bearer " + getToken()
            },
            credentials: "same-origin"
        });
        
        const data = await response.json();
        
        if (data.success && data.backups) {
            // Clear existing options except the first one
            select.innerHTML = '<option value="">-- Select a backup --</option>';
            
            if (data.backups.length > 0) {
                // Add new backup options
                data.backups.forEach(backup => {
                    const option = document.createElement('option');
                    option.value = backup.id;
                    option.setAttribute('data-file', backup.file_name || '');
                    option.setAttribute('data-size', backup.file_size || 0);
                    option.setAttribute('data-date', backup.created_at || '');
                    
                    const fileSize = backup.file_size ? 
                        (backup.file_size / 1024).toFixed(2) + ' KB' : 'N/A';
                    const date = backup.created_at ? 
                        new Date(backup.created_at).toLocaleString('en-US', {
                            month: 'short',
                            day: 'numeric',
                            year: 'numeric',
                            hour: 'numeric',
                            minute: '2-digit',
                            hour12: true
                        }) : '';
                    
                    option.textContent = `${backup.file_name || 'Unknown'} (${fileSize}) - ${date}`;
                    select.appendChild(option);
                });
                
                status.innerHTML = `
                    <p class="text-green-600">
                        <i class="fas fa-check-circle mr-1"></i>
                        Loaded ${data.backups.length} backup(s) for this company. 
                        <a href="${BASE}/dashboard/backup" class="text-blue-600 hover:underline">Create new backup</a>
                    </p>
                `;
                // Enable submit button if it was disabled
                const submitBtn = document.getElementById("createRestorePointBtn");
                if (submitBtn) {
                    submitBtn.disabled = false;
                }
            } else {
                status.innerHTML = `
                    <p class="text-gray-500">
                        <i class="fas fa-info-circle mr-1"></i>
                        No backups available. <a href="${BASE}/dashboard/backup" class="text-blue-600 hover:underline">Create a backup first</a>.
                    </p>
                `;
                // Disable submit button if no backups
                const submitBtn = document.getElementById("createRestorePointBtn");
                if (submitBtn) {
                    submitBtn.disabled = true;
                }
            }
        } else {
            throw new Error(data.error || "Failed to load backups");
        }
    } catch (error) {
        console.error("Error refreshing backups:", error);
        status.innerHTML = `
            <p class="text-red-600">
                <i class="fas fa-exclamation-circle mr-1"></i>
                Error loading backups: ${error.message}
            </p>
        `;
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    }
}

// Attach refresh button event
document.getElementById("refreshBackupsBtn")?.addEventListener("click", refreshBackups);
</script>

