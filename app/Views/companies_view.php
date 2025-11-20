<div class="p-6">
  <!-- Page Header -->
  <div class="mb-6">
    <div class="flex items-center justify-between mb-4">
      <div>
        <h2 class="text-3xl font-bold text-gray-800"><?= htmlspecialchars($company['name'] ?? 'Company') ?></h2>
        <p class="text-gray-600">Company ID: #<?= $company['id'] ?></p>
      </div>
      <div class="flex gap-2">
        <a href="<?= BASE_URL_PATH ?>/dashboard/companies/<?= $company['id'] ?>/restore-points" class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition text-sm font-medium">
          <i class="fas fa-history mr-2"></i>Restore Points
        </a>
        <a href="<?= BASE_URL_PATH ?>/dashboard/companies/edit/<?= $company['id'] ?>" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition text-sm font-medium">
          <i class="fas fa-edit mr-2"></i>Edit Company
        </a>
        <a href="<?= BASE_URL_PATH ?>/dashboard/companies" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition text-sm font-medium">
          <i class="fas fa-arrow-left mr-2"></i>Back to List
        </a>
      </div>
    </div>
  </div>
  
  <!-- Company Information Section -->
  <div id="company-info-section" class="content-section bg-white rounded-lg shadow p-6 mb-6">
      <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b pb-2">Company Information</h3>
      
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Company Information -->
        <div class="space-y-4">
          <div>
            <label class="text-sm font-medium text-gray-600">Company Name</label>
            <p class="text-gray-900 font-semibold"><?= htmlspecialchars($company['name'] ?? '') ?></p>
          </div>
          
          <div>
            <label class="text-sm font-medium text-gray-600">Email Address</label>
            <p class="text-gray-900"><?= htmlspecialchars($company['email'] ?? '') ?></p>
          </div>
          
          <div>
            <label class="text-sm font-medium text-gray-600">Phone Number</label>
            <p class="text-gray-900"><?= htmlspecialchars($company['phone_number'] ?? 'N/A') ?></p>
          </div>
          
          <div>
            <label class="text-sm font-medium text-gray-600">Contact Person</label>
            <p class="text-gray-900"><?= htmlspecialchars($company['contact_person'] ?? 'N/A') ?></p>
          </div>
          
          <div>
            <label class="text-sm font-medium text-gray-600">Status</label>
            <span class="inline-block px-3 py-1 rounded-full text-sm font-medium <?= ($company['status'] ?? 'inactive') == 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
              <?= ucfirst($company['status'] ?? 'inactive') ?>
            </span>
          </div>
        </div>
        
        <!-- Additional Information -->
        <div class="space-y-4">
          <div>
            <label class="text-sm font-medium text-gray-600">Address</label>
            <p class="text-gray-900 whitespace-pre-line"><?= htmlspecialchars($company['address'] ?? 'N/A') ?></p>
          </div>
          
          <div>
            <label class="text-sm font-medium text-gray-600">Created Date</label>
            <p class="text-gray-900"><?= $company['created_at'] ? date('F j, Y \a\t g:i A', strtotime($company['created_at'])) : 'N/A' ?></p>
          </div>
          
          <div>
            <label class="text-sm font-medium text-gray-600">Last Updated</label>
            <p class="text-gray-900"><?= $company['updated_at'] ? date('F j, Y \a\t g:i A', strtotime($company['updated_at'])) : 'N/A' ?></p>
          </div>
          
          <div>
            <label class="text-sm font-medium text-gray-600">Company ID</label>
            <p class="text-gray-900 font-mono text-sm">#<?= $company['id'] ?></p>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Company Settings Section -->
    <div id="company-settings-section" class="content-section bg-white rounded-lg shadow p-6">
      <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b pb-2 flex items-center">
        <i class="fas fa-cog text-gray-600 mr-2"></i>
        Company Settings
      </h3>
      <div class="space-y-4">
        <div class="p-4 border border-gray-200 rounded-lg">
          <div class="flex items-center justify-between">
            <div>
              <p class="font-medium text-gray-700">Delete Company</p>
              <p class="text-sm text-gray-500">Permanently delete this company and all associated data</p>
            </div>
            <button onclick="deleteCompany(<?= $company['id'] ?>, '<?= htmlspecialchars($company['name'], ENT_QUOTES) ?>')" 
               class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition">
              <i class="fas fa-trash mr-2"></i>Delete
            </button>
          </div>
        </div>
      </div>
    </div>
</div>

<!-- Delete Company Modal -->
<div id="deleteCompanyModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
  <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
    <div class="mt-3">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-medium text-gray-900">Delete Company</h3>
        <button onclick="closeDeleteModal()" class="text-gray-400 hover:text-gray-600">
          <i class="fas fa-times"></i>
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
      
      <div class="flex justify-end space-x-3">
        <button onclick="closeDeleteModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400">Cancel</button>
        <button onclick="confirmDeleteCompany()" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">
          <i class="fas fa-trash mr-2"></i>Delete Company
        </button>
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
      window.location.href = BASE + '/dashboard/companies';
    } else {
      alert('Error: ' + (data.error || 'Failed to delete company'));
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('Error deleting company');
  });
}
</script>
