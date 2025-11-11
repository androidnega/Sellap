<div class="p-6">
  <div class="mb-6">
    <h2 class="text-3xl font-bold text-gray-800">User Details</h2>
    <p class="text-gray-600">View and manage user information</p>
  </div>

  <div class="bg-white rounded-lg shadow p-6">
  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    
    <!-- User Information -->
    <div class="space-y-4">
      <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">User Information</h3>
      
      <div>
        <label class="text-sm font-medium text-gray-600">Full Name</label>
        <p class="text-gray-900 font-semibold"><?= htmlspecialchars($user['full_name'] ?? '') ?></p>
      </div>
      
      <div>
        <label class="text-sm font-medium text-gray-600">Username</label>
        <p class="text-gray-900">@<?= htmlspecialchars($user['username'] ?? '') ?></p>
      </div>
      
      <div>
        <label class="text-sm font-medium text-gray-600">Email Address</label>
        <p class="text-gray-900"><?= htmlspecialchars($user['email'] ?? '') ?></p>
      </div>
      
      <div>
        <label class="text-sm font-medium text-gray-600">Phone Number</label>
        <p class="text-gray-900"><?= htmlspecialchars($user['phone_number'] ?? 'N/A') ?></p>
      </div>
      
      <div>
        <label class="text-sm font-medium text-gray-600">Role</label>
        <span class="inline-block px-3 py-1 rounded-full text-sm font-medium <?= getRoleBadgeClass($user['role'] ?? '') ?>">
          <?= ucfirst($user['role'] ?? '') ?>
        </span>
      </div>
      
      <div>
        <label class="text-sm font-medium text-gray-600">Status</label>
        <span class="inline-block px-3 py-1 rounded-full text-sm font-medium <?= ($user['is_active'] ?? 0) ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
          <?= ($user['is_active'] ?? 0) ? 'Active' : 'Inactive' ?>
        </span>
      </div>
    </div>
    
    <!-- Additional Information -->
    <div class="space-y-4">
      <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">Additional Information</h3>
      
      <div>
        <label class="text-sm font-medium text-gray-600">Company</label>
        <p class="text-gray-900">
          <?php if ($company): ?>
            <a href="<?= BASE_URL_PATH ?>/dashboard/companies/view/<?= $company['id'] ?>" class="text-blue-600 hover:underline">
              <?= htmlspecialchars($company['name']) ?>
            </a>
          <?php else: ?>
            <span class="text-gray-400 italic">No Company Assigned</span>
          <?php endif; ?>
        </p>
      </div>
      
      <div>
        <label class="text-sm font-medium text-gray-600">User ID</label>
        <p class="text-gray-900 font-mono text-sm">#<?= $user['id'] ?></p>
      </div>
      
      <div>
        <label class="text-sm font-medium text-gray-600">Unique ID</label>
        <p class="text-gray-900 font-mono text-sm"><?= htmlspecialchars($user['unique_id'] ?? 'N/A') ?></p>
      </div>
      
      <div>
        <label class="text-sm font-medium text-gray-600">Created Date</label>
        <p class="text-gray-900"><?= $user['created_at'] ? date('F j, Y \a\t g:i A', strtotime($user['created_at'])) : 'N/A' ?></p>
      </div>
      
      <div>
        <label class="text-sm font-medium text-gray-600">Last Login</label>
        <p class="text-gray-900"><?= isset($user['last_login']) && $user['last_login'] ? date('F j, Y \a\t g:i A', strtotime($user['last_login'])) : 'Never' ?></p>
      </div>
    </div>
  </div>
  
  <!-- Action Buttons -->
  <div class="mt-8 pt-6 border-t border-gray-200 flex justify-between items-center">
    <div class="flex space-x-3">
      <a href="<?= BASE_URL_PATH ?>/dashboard/users" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 transition">
        <i class="fas fa-arrow-left mr-2"></i>Back to Users
      </a>
    </div>
    
    <div class="flex space-x-3">
      <a href="<?= BASE_URL_PATH ?>/dashboard/users/edit/<?= $user['id'] ?>" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
        <i class="fas fa-edit mr-2"></i>Edit User
      </a>
      <a href="<?= BASE_URL_PATH ?>/dashboard/users/reset-password/<?= $user['id'] ?>" class="bg-yellow-600 text-white px-4 py-2 rounded hover:bg-yellow-700 transition" onclick="return confirm('Reset password for this user?')">
        <i class="fas fa-key mr-2"></i>Reset Password
      </a>
      <a href="<?= BASE_URL_PATH ?>/dashboard/users/delete/<?= $user['id'] ?>" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 transition" onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.')">
        <i class="fas fa-trash mr-2"></i>Delete User
      </a>
    </div>
  </div>
</div>

<!-- User Activity (placeholder for future) -->
<div class="mt-6 bg-white rounded-lg shadow p-6">
  <h3 class="text-lg font-semibold text-gray-800 mb-4">User Activity</h3>
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="text-center p-4 bg-blue-50 rounded-lg">
      <div class="text-2xl font-bold text-blue-600">0</div>
      <div class="text-sm text-gray-600">Total Logins</div>
    </div>
    <div class="text-center p-4 bg-green-50 rounded-lg">
      <div class="text-2xl font-bold text-green-600">0</div>
      <div class="text-sm text-gray-600">Actions Today</div>
    </div>
    <div class="text-center p-4 bg-yellow-50 rounded-lg">
      <div class="text-2xl font-bold text-yellow-600">0</div>
      <div class="text-sm text-gray-600">Last Activity</div>
    </div>
  </div>
  </div>
</div>

<?php
// Helper function for role badge styling
function getRoleBadgeClass($role) {
    switch ($role) {
        case 'system_admin':
            return 'bg-purple-100 text-purple-700';
        case 'manager':
            return 'bg-blue-100 text-blue-700';
        case 'salesperson':
            return 'bg-green-100 text-green-700';
        case 'technician':
            return 'bg-yellow-100 text-yellow-700';
        default:
            return 'bg-gray-100 text-gray-700';
    }
}
?>
