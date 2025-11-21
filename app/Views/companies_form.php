<h2 class="text-2xl font-semibold mb-4"><?= isset($company) ? 'Edit Company' : 'Add New Company' ?></h2>

<?php if (isset($_SESSION['error_message'])): ?>
  <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
    <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($_SESSION['error_message']) ?>
  </div>
  <?php unset($_SESSION['error_message']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['success_message'])): ?>
  <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
    <i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($_SESSION['success_message']) ?>
  </div>
  <?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<form method="POST" action="<?= isset($company) ? BASE_URL_PATH.'/dashboard/companies/update/'.$company['id'] : BASE_URL_PATH.'/dashboard/companies/store' ?>" class="bg-white p-5 rounded shadow space-y-4">
  <div class="grid grid-cols-2 gap-4">
    <div>
      <label class="text-sm text-gray-700">Company Name</label>
      <input name="name" value="<?= htmlspecialchars($company['name'] ?? '') ?>" required class="w-full border p-2 rounded" />
    </div>
    <div>
      <label class="text-sm text-gray-700">Email</label>
      <input name="email" type="email" value="<?= htmlspecialchars($company['email'] ?? '') ?>" required class="w-full border p-2 rounded" />
    </div>
  </div>

  <div class="grid grid-cols-2 gap-4">
    <div>
      <label class="text-sm text-gray-700">Phone</label>
      <input name="phone" value="<?= htmlspecialchars($company['phone_number'] ?? '') ?>" class="w-full border p-2 rounded" />
    </div>
    <div>
      <label class="text-sm text-gray-700">Manager</label>
      <input name="contact_person" value="<?= htmlspecialchars($company['contact_person'] ?? '') ?>" class="w-full border p-2 rounded" />
    </div>
  </div>

  <div>
    <label class="text-sm text-gray-700">Address</label>
    <textarea name="address" class="w-full border p-2 rounded"><?= htmlspecialchars($company['address'] ?? '') ?></textarea>
  </div>

  <div>
    <label class="text-sm text-gray-700">Status</label>
    <select name="status" class="w-full border p-2 rounded">
      <option value="active" <?= (isset($company['status']) && $company['status']=='active') ? 'selected' : '' ?>>Active</option>
      <option value="inactive" <?= (isset($company['status']) && $company['status']=='inactive') ? 'selected' : '' ?>>Inactive</option>
    </select>
  </div>

  <div class="flex justify-end gap-3">
    <a href="<?= BASE_URL_PATH ?>/dashboard/companies" class="text-gray-600 hover:underline">Cancel</a>
    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"><?= isset($company) ? 'Update' : 'Create' ?></button>
  </div>
</form>
