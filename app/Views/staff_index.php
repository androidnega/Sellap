<!-- Staff Management Index -->
<div class="p-6">
    <div class="mb-6">
        <h2 class="text-3xl font-bold text-gray-800">Staff Management</h2>
        <p class="text-gray-600">Add, update, and manage staff members within your company</p>
    </div>

    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
            <?= $_SESSION['success_message'] ?>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
            <?= htmlspecialchars($_SESSION['error_message']) ?>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

<div class="flex justify-between items-center mb-6">
    <div class="flex items-center space-x-4">
        <input 
            type="text" 
            id="searchInput" 
            placeholder="Search staff by name or email..." 
            class="border border-gray-300 rounded px-4 py-2 w-64 focus:outline-none focus:border-blue-500"
        />
        <select id="roleFilter" class="border border-gray-300 rounded px-4 py-2 focus:outline-none focus:border-blue-500">
            <option value="">All Roles</option>
            <option value="salesperson">Salesperson</option>
            <option value="technician">Technician</option>
        </select>
    </div>
    <a 
        href="<?php echo BASE_URL_PATH; ?>/dashboard/staff/create" 
        class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm font-medium inline-flex items-center"
    >
        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
        </svg>
        Add Staff
    </a>
</div>

<div class="bg-white rounded shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm" id="staffTable">
            <thead class="bg-gray-100 text-gray-600 uppercase text-xs">
                <tr>
                    <th class="p-3 text-left">Name</th>
                    <th class="p-3 text-left">Email</th>
                    <th class="p-3 text-left">Phone</th>
                    <th class="p-3 text-left">Role</th>
                    <th class="p-3 text-left">Total Sales</th>
                    <th class="p-3 text-left">Avg. Sale</th>
                    <th class="p-3 text-left">Status</th>
                    <th class="p-3 text-left">Created</th>
                    <th class="p-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($staff)): ?>
                    <?php foreach ($staff as $s): ?>
                        <tr class="border-b hover:bg-gray-50 staff-row" 
                            data-name="<?php echo htmlspecialchars(strtolower($s['full_name'])); ?>"
                            data-email="<?php echo htmlspecialchars(strtolower($s['email'])); ?>"
                            data-role="<?php echo htmlspecialchars($s['role']); ?>">
                            <td class="p-3 font-medium text-gray-900">
                                <?php echo htmlspecialchars($s['full_name']); ?>
                            </td>
                            <td class="p-3 text-gray-600">
                                <?php echo htmlspecialchars($s['email']); ?>
                            </td>
                            <td class="p-3 text-gray-600">
                                <?php echo htmlspecialchars($s['phone_number'] ?? 'N/A'); ?>
                            </td>
                            <td class="p-3">
                                <span class="px-2 py-1 rounded text-xs font-medium
                                    <?php echo $s['role'] == 'salesperson' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700'; ?>">
                                    <?php echo ucfirst($s['role']); ?>
                                </span>
                            </td>
                            <td class="p-3 text-gray-900">
                                <div class="font-medium">₵<?php echo number_format($s['total_revenue'] ?? 0, 2); ?></div>
                                <div class="text-xs text-gray-500"><?php echo $s['sales_count'] ?? 0; ?> sales</div>
                            </td>
                            <td class="p-3 text-gray-900">
                                ₵<?php echo number_format($s['average_sale'] ?? 0, 2); ?>
                            </td>
                            <td class="p-3">
                                <span class="px-2 py-1 rounded text-xs font-medium
                                    <?php echo $s['status'] == 1 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                                    <?php echo $s['status'] == 1 ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td class="p-3 text-gray-600 text-xs">
                                <?php echo date('M d, Y', strtotime($s['created_at'])); ?>
                            </td>
                            <td class="p-3 text-right space-x-2">
                                <a 
                                    href="<?php echo BASE_URL_PATH; ?>/dashboard/staff/view/<?php echo $s['id']; ?>" 
                                    class="text-green-600 hover:text-green-800 font-medium"
                                    title="View Profile"
                                >
                                    View
                                </a>
                                <a 
                                    href="<?php echo BASE_URL_PATH; ?>/dashboard/staff/edit/<?php echo $s['id']; ?>" 
                                    class="text-blue-600 hover:text-blue-800 font-medium"
                                >
                                    Edit
                                </a>
                                <a 
                                    href="<?php echo BASE_URL_PATH; ?>/dashboard/staff/reset-password/<?php echo $s['id']; ?>" 
                                    class="text-orange-600 hover:text-orange-800 font-medium"
                                    onclick="return confirm('Are you sure you want to reset the password for <?php echo htmlspecialchars($s['full_name']); ?>? A new password will be generated.');"
                                    title="Reset Password"
                                >
                                    Reset Password
                                </a>
                                <a 
                                    href="<?php echo BASE_URL_PATH; ?>/dashboard/staff/delete/<?php echo $s['id']; ?>" 
                                    class="text-red-600 hover:text-red-800 font-medium"
                                    onclick="return confirm('Are you sure you want to delete this staff member? This action cannot be undone.');"
                                >
                                    Delete
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="p-8 text-center text-gray-500">
                            <svg class="w-12 h-12 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                            <p class="text-lg font-medium">No staff members found</p>
                            <p class="text-sm mt-2">Add your first staff member to get started</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Client-side filtering script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const roleFilter = document.getElementById('roleFilter');
    const staffRows = document.querySelectorAll('.staff-row');

    function filterStaff() {
        const searchTerm = searchInput.value.toLowerCase();
        const selectedRole = roleFilter.value;

        staffRows.forEach(row => {
            const name = row.dataset.name;
            const email = row.dataset.email;
            const role = row.dataset.role;

            const matchesSearch = name.includes(searchTerm) || email.includes(searchTerm);
            const matchesRole = !selectedRole || role === selectedRole;

            if (matchesSearch && matchesRole) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    searchInput.addEventListener('input', filterStaff);
    roleFilter.addEventListener('change', filterStaff);
});
</script>
</div>

