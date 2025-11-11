<div class="p-4 md:p-6">
  <!-- Success/Error Messages -->
  <?php if (isset($_SESSION['success_message'])): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4 flex items-center">
      <i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($_SESSION['success_message']) ?>
    </div>
    <?php unset($_SESSION['success_message']); ?>
  <?php endif; ?>
  
  <?php if (isset($_SESSION['error_message'])): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 flex items-center">
      <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($_SESSION['error_message']) ?>
    </div>
    <?php unset($_SESSION['error_message']); ?>
  <?php endif; ?>
  
  <?php if (isset($_SESSION['warning_message'])): ?>
    <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4 flex items-center">
      <i class="fas fa-exclamation-triangle mr-2"></i><?= htmlspecialchars($_SESSION['warning_message']) ?>
    </div>
    <?php unset($_SESSION['warning_message']); ?>
  <?php endif; ?>

  <!-- Header Section -->
  <div class="mb-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-4">
      <div>
        <h2 class="text-2xl md:text-3xl font-bold text-gray-800">Company Management</h2>
        <p class="text-gray-600 text-sm md:text-base mt-1">Create, update, and manage all companies</p>
      </div>
      <a href="<?= BASE_URL_PATH ?>/dashboard/companies/create" 
         class="mt-4 md:mt-0 inline-flex items-center justify-center bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors text-sm md:text-base font-medium shadow-md hover:shadow-lg">
        <i class="fas fa-plus mr-2"></i>New Company
      </a>
    </div>
  </div>

  <!-- Desktop Table View -->
  <div class="hidden md:block overflow-x-auto bg-white rounded-lg shadow">
    <table class="w-full text-sm">
      <thead class="bg-gray-100 text-gray-600 uppercase text-xs">
        <tr>
          <th class="p-4 text-left font-semibold">Name</th>
          <th class="p-4 text-left font-semibold">Email</th>
          <th class="p-4 text-left font-semibold">Phone</th>
          <th class="p-4 text-left font-semibold">Contact</th>
          <th class="p-4 text-left font-semibold">Status</th>
          <th class="p-4 text-left font-semibold">Created</th>
          <th class="p-4 text-right font-semibold">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($companies as $c): ?>
          <tr class="border-b hover:bg-gray-50 transition-colors">
            <td class="p-4 font-medium text-gray-900"><?= htmlspecialchars($c['name'] ?? '') ?></td>
            <td class="p-4 text-gray-600"><?= htmlspecialchars($c['email'] ?? '') ?></td>
            <td class="p-4 text-gray-600"><?= htmlspecialchars($c['phone_number'] ?? '') ?></td>
            <td class="p-4 text-gray-600"><?= htmlspecialchars($c['contact_person'] ?? '') ?></td>
            <td class="p-4">
              <span class="px-3 py-1 rounded-full text-xs font-medium <?= ($c['status'] ?? 'inactive')=='active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                <?= ucfirst($c['status'] ?? 'inactive') ?>
              </span>
            </td>
            <td class="p-4 text-gray-600"><?= $c['created_at'] ? date('M j, Y', strtotime($c['created_at'])) : 'N/A' ?></td>
            <td class="p-4">
              <div class="flex items-center justify-end gap-2 flex-wrap">
                <a href="<?= BASE_URL_PATH ?>/dashboard/companies/view/<?= $c['id'] ?>" 
                   class="inline-flex items-center px-3 py-1.5 bg-green-50 text-green-700 rounded-md hover:bg-green-100 text-xs font-medium transition-colors"
                   title="View Details">
                  <i class="fas fa-eye mr-1.5"></i>View
                </a>
                <a href="<?= BASE_URL_PATH ?>/dashboard/companies/<?= $c['id'] ?>/modules" 
                   class="inline-flex items-center px-3 py-1.5 bg-purple-50 text-purple-700 rounded-md hover:bg-purple-100 text-xs font-medium transition-colors"
                   title="Manage Modules">
                  <i class="fas fa-puzzle-piece mr-1.5"></i>Modules
                </a>
                <a href="<?= BASE_URL_PATH ?>/dashboard/companies/edit/<?= $c['id'] ?>" 
                   class="inline-flex items-center px-3 py-1.5 bg-blue-50 text-blue-700 rounded-md hover:bg-blue-100 text-xs font-medium transition-colors"
                   title="Edit Company">
                  <i class="fas fa-edit mr-1.5"></i>Edit
                </a>
                <button onclick="viewCompanyUsers(<?= $c['id'] ?>, '<?= htmlspecialchars($c['name'], ENT_QUOTES) ?>')" 
                        class="inline-flex items-center px-3 py-1.5 bg-indigo-50 text-indigo-700 rounded-md hover:bg-indigo-100 text-xs font-medium transition-colors"
                        title="View Users">
                  <i class="fas fa-users mr-1.5"></i>Users
                </button>
                <a href="<?= BASE_URL_PATH ?>/dashboard/companies/<?= $c['id'] ?>/reset-password" 
                   class="inline-flex items-center px-3 py-1.5 bg-yellow-50 text-yellow-700 rounded-md hover:bg-yellow-100 text-xs font-medium transition-colors"
                   title="Reset Manager Password"
                   onclick="return confirm('Reset the manager password for <?= htmlspecialchars($c['name']) ?>? The new password will be sent via SMS to the manager.')">
                  <i class="fas fa-key mr-1.5"></i>Reset PW
                </a>
                <a href="<?= BASE_URL_PATH ?>/dashboard/companies/<?= $c['id'] ?>/reset" 
                   class="inline-flex items-center px-3 py-1.5 bg-orange-50 text-orange-700 rounded-md hover:bg-orange-100 text-xs font-medium transition-colors"
                   title="Reset Company Data">
                  <i class="fas fa-redo mr-1.5"></i>Reset
                </a>
                <button onclick="deleteCompany(<?= $c['id'] ?>, '<?= htmlspecialchars($c['name'], ENT_QUOTES) ?>')" 
                        class="inline-flex items-center px-3 py-1.5 bg-red-50 text-red-700 rounded-md hover:bg-red-100 text-xs font-medium transition-colors"
                        title="Delete Company">
                  <i class="fas fa-trash mr-1.5"></i>Delete
                </button>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($companies)): ?>
          <tr>
            <td colspan="7" class="p-8 text-center text-gray-500">
              <i class="fas fa-building text-4xl mb-3 text-gray-300"></i>
              <p class="text-lg">No companies found</p>
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Mobile Card View -->
  <div class="md:hidden space-y-4">
    <?php foreach ($companies as $c): ?>
      <div class="bg-white rounded-lg shadow p-4 border border-gray-200">
        <div class="flex items-start justify-between mb-3">
          <div class="flex-1">
            <h3 class="font-semibold text-gray-900 text-lg mb-1"><?= htmlspecialchars($c['name'] ?? '') ?></h3>
            <span class="inline-block px-3 py-1 rounded-full text-xs font-medium <?= ($c['status'] ?? 'inactive')=='active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
              <?= ucfirst($c['status'] ?? 'inactive') ?>
            </span>
          </div>
        </div>
        
        <div class="space-y-2 mb-4 text-sm">
          <div class="flex items-center text-gray-600">
            <i class="fas fa-envelope w-5 text-gray-400"></i>
            <span class="ml-2"><?= htmlspecialchars($c['email'] ?? 'N/A') ?></span>
          </div>
          <div class="flex items-center text-gray-600">
            <i class="fas fa-phone w-5 text-gray-400"></i>
            <span class="ml-2"><?= htmlspecialchars($c['phone_number'] ?? 'N/A') ?></span>
          </div>
          <div class="flex items-center text-gray-600">
            <i class="fas fa-user w-5 text-gray-400"></i>
            <span class="ml-2"><?= htmlspecialchars($c['contact_person'] ?? 'N/A') ?></span>
          </div>
          <div class="flex items-center text-gray-600">
            <i class="fas fa-calendar w-5 text-gray-400"></i>
            <span class="ml-2"><?= $c['created_at'] ? date('M j, Y', strtotime($c['created_at'])) : 'N/A' ?></span>
          </div>
        </div>

        <!-- Mobile Actions -->
        <div class="border-t pt-3 mt-3">
          <div class="grid grid-cols-2 gap-2">
            <a href="<?= BASE_URL_PATH ?>/dashboard/companies/view/<?= $c['id'] ?>" 
               class="flex items-center justify-center px-3 py-2 bg-green-50 text-green-700 rounded-md hover:bg-green-100 text-xs font-medium transition-colors">
              <i class="fas fa-eye mr-1.5"></i>View
            </a>
            <a href="<?= BASE_URL_PATH ?>/dashboard/companies/edit/<?= $c['id'] ?>" 
               class="flex items-center justify-center px-3 py-2 bg-blue-50 text-blue-700 rounded-md hover:bg-blue-100 text-xs font-medium transition-colors">
              <i class="fas fa-edit mr-1.5"></i>Edit
            </a>
            <a href="<?= BASE_URL_PATH ?>/dashboard/companies/<?= $c['id'] ?>/modules" 
               class="flex items-center justify-center px-3 py-2 bg-purple-50 text-purple-700 rounded-md hover:bg-purple-100 text-xs font-medium transition-colors">
              <i class="fas fa-puzzle-piece mr-1.5"></i>Modules
            </a>
            <button onclick="viewCompanyUsers(<?= $c['id'] ?>, '<?= htmlspecialchars($c['name'], ENT_QUOTES) ?>')" 
                    class="flex items-center justify-center px-3 py-2 bg-indigo-50 text-indigo-700 rounded-md hover:bg-indigo-100 text-xs font-medium transition-colors">
              <i class="fas fa-users mr-1.5"></i>Users
            </button>
            <a href="<?= BASE_URL_PATH ?>/dashboard/companies/<?= $c['id'] ?>/reset-password" 
               class="flex items-center justify-center px-3 py-2 bg-yellow-50 text-yellow-700 rounded-md hover:bg-yellow-100 text-xs font-medium transition-colors"
               onclick="return confirm('Reset the manager password for <?= htmlspecialchars($c['name']) ?>? The new password will be sent via SMS to the manager.')">
              <i class="fas fa-key mr-1.5"></i>Reset PW
            </a>
            <a href="<?= BASE_URL_PATH ?>/dashboard/companies/<?= $c['id'] ?>/reset" 
               class="flex items-center justify-center px-3 py-2 bg-orange-50 text-orange-700 rounded-md hover:bg-orange-100 text-xs font-medium transition-colors">
              <i class="fas fa-redo mr-1.5"></i>Reset
            </a>
            <button onclick="deleteCompany(<?= $c['id'] ?>, '<?= htmlspecialchars($c['name'], ENT_QUOTES) ?>')" 
                    class="flex items-center justify-center px-3 py-2 bg-red-50 text-red-700 rounded-md hover:bg-red-100 text-xs font-medium transition-colors">
              <i class="fas fa-trash mr-1.5"></i>Delete
            </button>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
    
    <?php if (empty($companies)): ?>
      <div class="bg-white rounded-lg shadow p-8 text-center border border-gray-200">
        <i class="fas fa-building text-5xl mb-3 text-gray-300"></i>
        <p class="text-lg text-gray-500">No companies found</p>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Delete Company Modal -->
<div id="deleteCompanyModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
  <div class="relative top-10 md:top-20 mx-auto p-5 border w-11/12 md:w-96 shadow-lg rounded-md bg-white">
    <div class="mt-3">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-medium text-gray-900">Delete Company</h3>
        <button onclick="closeDeleteModal()" class="text-gray-400 hover:text-gray-600">
          <i class="fas fa-times text-xl"></i>
        </button>
      </div>
      
      <div id="deleteWarning" class="mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded">
        <p class="text-sm text-yellow-800">
          <i class="fas fa-exclamation-triangle mr-2"></i>
          <strong>Warning:</strong> This company has data associated with it. Deleting will permanently remove:
        </p>
        <ul id="dataSummary" class="mt-2 text-xs text-yellow-700 list-disc list-inside"></ul>
      </div>
      
      <div id="noDataWarning" class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded hidden">
        <p class="text-sm text-blue-800">
          <i class="fas fa-info-circle mr-2"></i>
          This company has no data. It can be safely deleted.
        </p>
      </div>
      
      <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700 mb-2">Enter your password to confirm:</label>
        <input type="password" id="deletePassword" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500" placeholder="Your password">
      </div>
      
      <div class="flex flex-col sm:flex-row justify-end gap-3">
        <button onclick="closeDeleteModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400 transition-colors font-medium">Cancel</button>
        <button onclick="confirmDeleteCompany()" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition-colors font-medium">
          <i class="fas fa-trash mr-2"></i>Delete Company
        </button>
      </div>
    </div>
  </div>
</div>

<!-- View Users Modal -->
<div id="usersModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
  <div class="relative top-10 mx-auto p-5 border w-11/12 md:w-4/5 max-w-4xl shadow-lg rounded-md bg-white">
    <div class="mt-3">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-medium text-gray-900">
          <i class="fas fa-users mr-2"></i>Users for <span id="usersCompanyName"></span>
        </h3>
        <button onclick="closeUsersModal()" class="text-gray-400 hover:text-gray-600">
          <i class="fas fa-times text-xl"></i>
        </button>
      </div>
      
      <div id="usersList" class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-100 text-gray-600 uppercase text-xs">
            <tr>
              <th class="p-3 text-left">Name</th>
              <th class="p-3 text-left">Email</th>
              <th class="p-3 text-left">Role</th>
              <th class="p-3 text-left">Status</th>
              <th class="p-3 text-left">Created</th>
              <th class="p-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody id="usersTableBody">
            <tr><td colspan="6" class="p-3 text-center text-gray-500">Loading users...</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
let currentDeleteCompanyId = null;

function deleteCompany(companyId, companyName) {
  currentDeleteCompanyId = companyId;
  
  // Check if company has data
  fetch(BASE + '/api/companies/' + companyId + '/check-data', {
    headers: {
      'Authorization': 'Bearer ' + (localStorage.getItem('token') || localStorage.getItem('sellapp_token') || '')
    }
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      const modal = document.getElementById('deleteCompanyModal');
      const warningDiv = document.getElementById('deleteWarning');
      const noDataDiv = document.getElementById('noDataWarning');
      const dataSummary = document.getElementById('dataSummary');
      
      if (data.has_data) {
        // Show data summary
        warningDiv.classList.remove('hidden');
        noDataDiv.classList.add('hidden');
        
        let summaryHtml = '';
        if (data.data.has_users) {
          summaryHtml += '<li>All users under this company</li>';
        }
        if (data.data.has_customers) {
          summaryHtml += '<li>All customers</li>';
        }
        if (data.data.has_sales) {
          summaryHtml += '<li>All sales records</li>';
        }
        if (data.data.has_products) {
          summaryHtml += '<li>All products</li>';
        }
        if (data.data.has_repairs) {
          summaryHtml += '<li>All repair records</li>';
        }
        if (data.data.has_swaps) {
          summaryHtml += '<li>All swap records</li>';
        }
        summaryHtml += '<li class="font-semibold">Total: ' + data.data.total_count + ' records</li>';
        dataSummary.innerHTML = summaryHtml;
      } else {
        // No data, hide warning
        warningDiv.classList.add('hidden');
        noDataDiv.classList.remove('hidden');
      }
      
      modal.classList.remove('hidden');
      document.getElementById('deletePassword').value = '';
    } else {
      alert('Error: ' + (data.error || 'Failed to check company data'));
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('Error checking company data');
  });
}

function closeDeleteModal() {
  document.getElementById('deleteCompanyModal').classList.add('hidden');
  currentDeleteCompanyId = null;
  document.getElementById('deletePassword').value = '';
}

function confirmDeleteCompany() {
  if (!currentDeleteCompanyId) return;
  
  const password = document.getElementById('deletePassword').value;
  if (!password) {
    alert('Please enter your password to confirm deletion');
    return;
  }
  
  if (!confirm('Are you absolutely sure you want to delete this company? This action cannot be undone!')) {
    return;
  }
  
  fetch(BASE + '/api/companies/' + currentDeleteCompanyId + '/delete', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': 'Bearer ' + (localStorage.getItem('token') || localStorage.getItem('sellapp_token') || '')
    },
    body: JSON.stringify({
      password: password
    })
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      alert('Company deleted successfully');
      window.location.reload();
    } else {
      alert('Error: ' + (data.error || 'Failed to delete company'));
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('Error deleting company');
  });
}

function viewCompanyUsers(companyId, companyName) {
  document.getElementById('usersCompanyName').textContent = companyName;
  document.getElementById('usersTableBody').innerHTML = '<tr><td colspan="6" class="p-3 text-center text-gray-500">Loading users...</td></tr>';
  
  const modal = document.getElementById('usersModal');
  modal.classList.remove('hidden');
  
  fetch(BASE + '/api/companies/' + companyId + '/users', {
    headers: {
      'Authorization': 'Bearer ' + (localStorage.getItem('token') || localStorage.getItem('sellapp_token') || '')
    }
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      const tbody = document.getElementById('usersTableBody');
      if (data.data && data.data.length > 0) {
        tbody.innerHTML = data.data.map(user => `
          <tr class="border-b hover:bg-gray-50">
            <td class="p-3">${user.full_name || 'N/A'}</td>
            <td class="p-3">${user.email || 'N/A'}</td>
            <td class="p-3">
              <span class="px-2 py-1 rounded text-xs bg-blue-100 text-blue-700">
                ${user.role || 'N/A'}
              </span>
            </td>
            <td class="p-3">
              <span class="px-2 py-1 rounded text-xs ${user.is_active == 1 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}">
                ${user.is_active == 1 ? 'Active' : 'Inactive'}
              </span>
            </td>
            <td class="p-3">${user.created_at ? new Date(user.created_at).toLocaleDateString() : 'N/A'}</td>
            <td class="p-3 text-right">
              <button onclick="deleteUser(${user.id}, ${companyId}, '${user.full_name || user.email}')" class="text-red-600 hover:underline" title="Delete User">
                <i class="fas fa-trash"></i>
              </button>
            </td>
          </tr>
        `).join('');
      } else {
        tbody.innerHTML = '<tr><td colspan="6" class="p-3 text-center text-gray-500">No users found for this company</td></tr>';
      }
    } else {
      document.getElementById('usersTableBody').innerHTML = '<tr><td colspan="6" class="p-3 text-center text-red-500">Error: ' + (data.error || 'Failed to load users') + '</td></tr>';
    }
  })
  .catch(error => {
    console.error('Error:', error);
    document.getElementById('usersTableBody').innerHTML = '<tr><td colspan="6" class="p-3 text-center text-red-500">Error loading users</td></tr>';
  });
}

function closeUsersModal() {
  document.getElementById('usersModal').classList.add('hidden');
}

function deleteUser(userId, companyId, userName) {
  if (!confirm('Delete user "' + userName + '"? This action cannot be undone.')) {
    return;
  }
  
  fetch(BASE + '/api/users/' + userId + '/delete', {
    method: 'DELETE',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': 'Bearer ' + (localStorage.getItem('token') || localStorage.getItem('sellapp_token') || '')
    },
    body: JSON.stringify({
      company_id: companyId
    })
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      alert('User deleted successfully');
      viewCompanyUsers(companyId, document.getElementById('usersCompanyName').textContent);
    } else {
      alert('Error: ' + (data.error || 'Failed to delete user'));
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('Error deleting user');
  });
}
</script>
