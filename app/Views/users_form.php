<h2 class="text-2xl font-semibold mb-4"><?= isset($user) ? 'Edit User' : 'Add New User' ?></h2>

<form method="POST" action="<?= isset($user) ? BASE_URL_PATH.'/dashboard/users/update/'.$user['id'] : BASE_URL_PATH.'/dashboard/users/store' ?>" class="bg-white p-5 rounded shadow space-y-4">
  <div class="grid grid-cols-2 gap-4">
    <div>
      <label class="text-sm text-gray-700">Full Name</label>
      <input name="full_name" value="<?= htmlspecialchars($user['full_name'] ?? '') ?>" required class="w-full border p-2 rounded" />
    </div>
    <div>
      <label class="text-sm text-gray-700">Username</label>
      <input name="username" value="<?= htmlspecialchars($user['username'] ?? '') ?>" required class="w-full border p-2 rounded" />
    </div>
  </div>

  <div class="grid grid-cols-2 gap-4">
    <div>
      <label class="text-sm text-gray-700">Email Address</label>
      <input name="email" type="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required class="w-full border p-2 rounded" />
    </div>
    <div>
      <label class="text-sm text-gray-700">Phone Number</label>
      <input name="phone_number" value="<?= htmlspecialchars($user['phone_number'] ?? '') ?>" class="w-full border p-2 rounded" />
    </div>
  </div>

  <div class="grid grid-cols-2 gap-4">
    <div>
      <label class="text-sm text-gray-700">Role</label>
      <select name="role" required class="w-full border p-2 rounded">
        <option value="system_admin" <?= (isset($user['role']) && $user['role']=='system_admin') ? 'selected' : '' ?>>System Admin</option>
        <option value="manager" <?= (isset($user['role']) && $user['role']=='manager') ? 'selected' : '' ?>>Manager</option>
        <option value="salesperson" <?= (isset($user['role']) && $user['role']=='salesperson') ? 'selected' : '' ?>>Salesperson</option>
        <option value="technician" <?= (isset($user['role']) && $user['role']=='technician') ? 'selected' : '' ?>>Technician</option>
      </select>
    </div>
    <div>
      <label class="text-sm text-gray-700">Company</label>
      <select name="company_id" class="w-full border p-2 rounded">
        <option value="">No Company</option>
        <?php foreach ($companies as $company): ?>
          <option value="<?= $company['id'] ?>" <?= (isset($user['company_id']) && $user['company_id']==$company['id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($company['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <div>
    <label class="text-sm text-gray-700">Password</label>
    <input name="password" type="password" <?= !isset($user) ? 'required' : '' ?> class="w-full border p-2 rounded" placeholder="<?= isset($user) ? 'Leave blank to keep current password' : 'Enter password' ?>" />
    <?php if (isset($user)): ?>
      <p class="text-xs text-gray-500 mt-1">Leave blank to keep current password</p>
    <?php endif; ?>
  </div>

  <div>
    <label class="flex items-center">
      <input type="checkbox" name="is_active" value="1" <?= (!isset($user) || $user['is_active']) ? 'checked' : '' ?> class="mr-2" />
      <span class="text-sm text-gray-700">Active User</span>
    </label>
    <p class="text-xs text-gray-500 mt-1">Inactive users cannot log in to the system</p>
  </div>

  <div class="flex justify-end gap-3">
    <a href="<?= BASE_URL_PATH ?>/dashboard/users" class="text-gray-600 hover:underline">Cancel</a>
    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"><?= isset($user) ? 'Update User' : 'Create User' ?></button>
  </div>
</form>
