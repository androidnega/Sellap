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
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
      <?php foreach ($companies as $company): ?>
        <div class="bg-white rounded-lg shadow-lg border border-gray-200 p-4 sm:p-6 hover:shadow-xl transition overflow-hidden">
          <div class="flex items-start justify-between mb-4 min-w-0">
            <div class="flex-1 min-w-0 pr-2">
              <h3 class="text-base sm:text-lg font-semibold text-gray-800 mb-1 truncate" title="<?= htmlspecialchars($company['name']) ?>"><?= htmlspecialchars($company['name']) ?></h3>
              <p class="text-xs text-gray-500">ID: #<?= $company['id'] ?></p>
            </div>
            <div class="p-2 bg-blue-100 rounded-lg flex-shrink-0">
              <i class="fas fa-building text-blue-600"></i>
            </div>
          </div>
          
          <div class="space-y-2 mb-4 text-xs sm:text-sm">
            <div class="flex items-center justify-between gap-2 min-w-0">
              <span class="text-gray-600 flex-shrink-0">Email:</span>
              <span class="text-gray-800 truncate text-right" title="<?= htmlspecialchars($company['email'] ?? 'N/A') ?>"><?= htmlspecialchars($company['email'] ?? 'N/A') ?></span>
            </div>
            <div class="flex items-center justify-between gap-2">
              <span class="text-gray-600 flex-shrink-0">Status:</span>
              <span class="px-2 py-1 rounded text-xs whitespace-nowrap <?= ($company['status'] ?? 'inactive') == 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                <?= ucfirst($company['status'] ?? 'inactive') ?>
              </span>
            </div>
          </div>
          
          <a 
            href="<?= BASE_URL_PATH ?>/dashboard/companies/<?= $company['id'] ?>/modules" 
            class="block w-full text-center bg-blue-600 text-white px-3 sm:px-4 py-2 rounded-lg hover:bg-blue-700 transition text-xs sm:text-sm font-medium whitespace-nowrap overflow-hidden"
          >
            <i class="fas fa-puzzle-piece mr-2"></i>Manage Modules
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

