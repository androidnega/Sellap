<div class="p-6">
  <div class="mb-6">
    <h2 class="text-3xl font-bold text-gray-800">User Management</h2>
    <p class="text-gray-600">Manage all users across the system</p>
  </div>

  <div class="flex justify-between items-center mb-4">
    <p class="text-gray-600">View and manage user accounts and permissions</p>
    <a href="<?= BASE_URL_PATH ?>/dashboard/users/create" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm font-medium">+ New User</a>
  </div>

  <!-- Password Reset Success Message -->
  <?php if (isset($_GET['password_reset']) && $_GET['password_reset'] == '1'): ?>
    <div class="mb-4 p-3 bg-green-100 border border-green-400 text-green-700 rounded">
      <strong>Password Reset Successful!</strong> 
      New temporary password: <code class="bg-green-200 px-1 rounded"><?= htmlspecialchars($_GET['new_password'] ?? '') ?></code>
      <br><small>Please share this password with the user securely.</small>
    </div>
  <?php endif; ?>

  <div class="overflow-x-auto bg-white rounded-lg shadow">
  <table class="w-full text-sm">
    <thead class="bg-gray-100 text-gray-600 uppercase text-xs">
      <tr>
        <th class="p-3 text-left">Name</th>
        <th class="p-3 text-left">Email</th>
        <th class="p-3 text-left">Role</th>
        <th class="p-3 text-left">Company</th>
        <th class="p-3 text-left">Status</th>
        <th class="p-3 text-left">Created</th>
        <th class="p-3 text-right">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $u): ?>
        <tr class="border-b hover:bg-gray-50">
          <td class="p-3">
            <div>
              <div class="font-semibold"><?= htmlspecialchars($u['full_name'] ?? '') ?></div>
              <div class="text-xs text-gray-500">@<?= htmlspecialchars($u['username'] ?? '') ?></div>
            </div>
          </td>
          <td class="p-3"><?= htmlspecialchars($u['email'] ?? '') ?></td>
          <td class="p-3">
            <span class="px-2 py-1 rounded text-xs font-medium <?= getRoleBadgeClass($u['role'] ?? '') ?>">
              <?= ucfirst($u['role'] ?? '') ?>
            </span>
          </td>
          <td class="p-3">
            <?php if ($u['company_name']): ?>
              <span class="text-gray-900"><?= htmlspecialchars($u['company_name']) ?></span>
            <?php else: ?>
              <span class="text-gray-400 italic">No Company</span>
            <?php endif; ?>
          </td>
          <td class="p-3">
            <span class="px-2 py-1 rounded text-xs <?= ($u['is_active'] ?? 0) ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
              <?= ($u['is_active'] ?? 0) ? 'Active' : 'Inactive' ?>
            </span>
          </td>
          <td class="p-3"><?= $u['created_at'] ? date('M j, Y', strtotime($u['created_at'])) : 'N/A' ?></td>
          <td class="p-3 text-right space-x-2">
            <a href="<?= BASE_URL_PATH ?>/dashboard/users/view/<?= $u['id'] ?>" class="text-green-600 hover:underline">View</a>
            <a href="<?= BASE_URL_PATH ?>/dashboard/users/edit/<?= $u['id'] ?>" class="text-blue-600 hover:underline">Edit</a>
            <a href="<?= BASE_URL_PATH ?>/dashboard/users/reset-password/<?= $u['id'] ?>" class="text-yellow-600 hover:underline" onclick="return confirm('Reset password for this user?')">Reset</a>
            <a href="<?= BASE_URL_PATH ?>/dashboard/users/delete/<?= $u['id'] ?>" class="text-red-600 hover:underline" onclick="return confirm('Delete this user? This action cannot be undone.')">Delete</a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($users)): ?>
        <tr><td colspan="7" class="p-3 text-center text-gray-500">No users found</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
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
