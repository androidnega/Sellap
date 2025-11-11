<div class="p-6">
  <div class="mb-6">
    <h2 class="text-3xl font-bold text-gray-800">Company Modules Management</h2>
    <p class="text-gray-600">Select a company to manage its enabled modules</p>
  </div>

  <!-- Companies List -->
  <?php if (empty($companies)): ?>
    <div class="bg-white rounded-lg shadow p-8 text-center">
      <i class="fas fa-building text-gray-400 text-5xl mb-4"></i>
      <p class="text-gray-600 text-lg mb-2">No companies found</p>
      <p class="text-gray-500 text-sm">Create a company first to manage modules.</p>
      <a href="<?= BASE_URL_PATH ?>/dashboard/companies/create" class="inline-block mt-4 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
        <i class="fas fa-plus mr-2"></i>Create Company
      </a>
    </div>
  <?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <?php foreach ($companies as $company): ?>
        <div class="bg-white rounded-lg shadow-lg border border-gray-200 p-6 hover:shadow-xl transition">
          <div class="flex items-start justify-between mb-4">
            <div class="flex-1">
              <h3 class="text-lg font-semibold text-gray-800 mb-1"><?= htmlspecialchars($company['name']) ?></h3>
              <p class="text-xs text-gray-500">ID: #<?= $company['id'] ?></p>
            </div>
            <div class="p-2 bg-blue-100 rounded-lg">
              <i class="fas fa-building text-blue-600"></i>
            </div>
          </div>
          
          <div class="space-y-2 mb-4 text-sm">
            <div class="flex items-center justify-between">
              <span class="text-gray-600">Email:</span>
              <span class="text-gray-800"><?= htmlspecialchars($company['email'] ?? 'N/A') ?></span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-gray-600">Status:</span>
              <span class="px-2 py-1 rounded text-xs <?= ($company['status'] ?? 'inactive') == 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                <?= ucfirst($company['status'] ?? 'inactive') ?>
              </span>
            </div>
          </div>
          
          <a 
            href="<?= BASE_URL_PATH ?>/dashboard/companies/<?= $company['id'] ?>/modules" 
            class="block w-full text-center bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition text-sm font-medium"
          >
            <i class="fas fa-puzzle-piece mr-2"></i>Manage Modules
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

