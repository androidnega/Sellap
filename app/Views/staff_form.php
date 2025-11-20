<!-- Staff Form (Add/Edit) -->
<div class="p-6">
    <div class="mb-6">
        <a href="<?php echo BASE_URL_PATH; ?>/dashboard/staff" class="text-blue-600 hover:text-blue-800 text-sm font-medium inline-flex items-center mb-4">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
            Back to Staff List
        </a>
        <h2 class="text-3xl font-bold text-gray-800">
            <?php echo isset($staff) ? 'Edit Staff Member' : 'Add New Staff Member'; ?>
        </h2>
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

<form 
    method="POST" 
    action="<?php echo isset($staff) ? BASE_URL_PATH . '/dashboard/staff/update/' . $staff['id'] : BASE_URL_PATH . '/dashboard/staff/store'; ?>" 
    class="bg-white p-6 rounded shadow space-y-6"
>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Full Name -->
        <div>
            <label for="full_name" class="block text-sm font-medium text-gray-700 mb-2">
                Full Name <span class="text-red-500">*</span>
            </label>
            <input 
                type="text" 
                id="full_name"
                name="full_name" 
                value="<?php echo isset($staff) ? htmlspecialchars($staff['full_name']) : ''; ?>" 
                required 
                class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:border-blue-500"
                placeholder="Enter full name"
            />
        </div>

        <!-- Username -->
        <div>
            <label for="username" class="block text-sm font-medium text-gray-700 mb-2">
                Username <span class="text-red-500">*</span>
            </label>
            <input 
                type="text" 
                id="username"
                name="username" 
                value="<?php echo isset($staff) ? htmlspecialchars($staff['username']) : ''; ?>" 
                required 
                class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:border-blue-500"
                placeholder="Enter username (e.g., john.doe or john.doe@example.com)"
            />
            <p class="text-xs text-gray-500 mt-1">Username can be a name or email address</p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Email -->
        <div>
            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                Email Address <span class="text-red-500">*</span>
            </label>
            <input 
                type="email" 
                id="email"
                name="email" 
                value="<?php echo isset($staff) ? htmlspecialchars($staff['email']) : ''; ?>" 
                required 
                class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:border-blue-500"
                placeholder="staff@example.com"
            />
        </div>

        <!-- Phone Number -->
        <div>
            <label for="phone_number" class="block text-sm font-medium text-gray-700 mb-2">
                Phone Number
            </label>
            <input 
                type="tel" 
                id="phone_number"
                name="phone_number" 
                value="<?php echo isset($staff) ? htmlspecialchars($staff['phone_number'] ?? '') : ''; ?>" 
                class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:border-blue-500"
                placeholder="+233 000 000 000"
            />
        </div>

        <!-- Role -->
        <div>
            <label for="role" class="block text-sm font-medium text-gray-700 mb-2">
                Role <span class="text-red-500">*</span>
            </label>
            <select 
                id="role"
                name="role" 
                required 
                class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:border-blue-500"
            >
                <option value="">Select role...</option>
                <option value="salesperson" <?php echo (isset($staff) && $staff['role'] == 'salesperson') ? 'selected' : ''; ?>>
                    Salesperson
                </option>
                <option value="technician" <?php echo (isset($staff) && $staff['role'] == 'technician') ? 'selected' : ''; ?>>
                    Technician
                </option>
            </select>
            <p class="text-xs text-gray-500 mt-1">Note: Managers can only create salespeople or technicians</p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Status -->
        <div>
            <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                Status <span class="text-red-500">*</span>
            </label>
            <select 
                id="status"
                name="status" 
                class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:border-blue-500"
            >
                <option value="active" <?php echo (!isset($staff) || $staff['status'] == 1) ? 'selected' : ''; ?>>
                    Active
                </option>
                <option value="inactive" <?php echo (isset($staff) && $staff['status'] == 0) ? 'selected' : ''; ?>>
                    Inactive
                </option>
            </select>
        </div>

        <!-- Password (only for new staff) -->
        <?php if (!isset($staff)): ?>
        <div>
            <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                Password
            </label>
            <input 
                type="password" 
                id="password"
                name="password" 
                class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:border-blue-500"
                placeholder="Leave blank for default: password123"
            />
            <p class="text-xs text-gray-500 mt-1">Default password: <code class="bg-gray-100 px-1 rounded">password123</code></p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Info Box -->
    <?php if (isset($staff)): ?>
    <div class="bg-blue-50 border border-blue-200 rounded p-4">
        <div class="flex items-start">
            <svg class="w-5 h-5 text-blue-600 mt-0.5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <div class="text-sm text-blue-800">
                <p class="font-medium">Editing Staff Member</p>
                <p class="mt-1">Username: <code class="bg-blue-100 px-1 rounded"><?php echo htmlspecialchars($staff['username']); ?></code></p>
                <p class="mt-1">Created: <?php echo date('M d, Y', strtotime($staff['created_at'])); ?></p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Form Actions -->
    <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
        <a 
            href="<?php echo BASE_URL_PATH; ?>/dashboard/staff" 
            class="px-4 py-2 border border-gray-300 rounded text-gray-700 hover:bg-gray-50 font-medium"
        >
            Cancel
        </a>
        <button 
            type="submit" 
            class="px-6 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 font-medium"
        >
            <?php echo isset($staff) ? 'Update Staff' : 'Add Staff'; ?>
        </button>
    </div>
</form>
</div>

