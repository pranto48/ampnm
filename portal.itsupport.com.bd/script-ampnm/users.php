<?php
require_once 'includes/auth_check.php';
include 'header.php';
?>

<main id="app">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-white mb-6">User Management</h1>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <!-- Create User Form -->
            <div class="md:col-span-1">
                <div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-6">
                    <h2 class="text-xl font-semibold text-white mb-4">Create New User</h2>
                    <form id="createUserForm" class="space-y-4">
                        <div>
                            <label for="new_username" class="block text-sm font-medium text-slate-300 mb-1">Username</label>
                            <input type="text" id="new_username" name="username" required class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                        </div>
                        <div>
                            <label for="new_password" class="block text-sm font-medium text-slate-300 mb-1">Password</label>
                            <input type="password" id="new_password" name="password" required class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                        </div>
                        <div>
                            <label for="new_role" class="block text-sm font-medium text-slate-300 mb-1">Role</label>
                            <select id="new_role" name="role" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                                <option value="viewer">Viewer</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <button type="submit" class="w-full px-6 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700">
                            <i class="fas fa-user-plus mr-2"></i>Create User
                        </button>
                    </form>
                </div>
            </div>

            <!-- User List -->
            <div class="md:col-span-2">
                <div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-6">
                    <h2 class="text-xl font-semibold text-white mb-4">Existing Users</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="border-b border-slate-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Username</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Role</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Created At</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="usersTableBody">
                                <!-- User rows will be inserted here by JavaScript -->
                            </tbody>
                        </table>
                        <div id="usersLoader" class="text-center py-8 hidden"><div class="loader mx-auto"></div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Role Modal -->
    <div id="editRoleModal" class="modal-backdrop hidden">
        <div class="modal-panel bg-slate-800 rounded-lg shadow-xl p-6 w-full max-w-sm border border-slate-700">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold text-white">Edit User Role</h2>
                <button id="closeEditRoleModal" class="text-slate-400 hover:text-white text-2xl">&times;</button>
            </div>
            <form id="editRoleForm" class="space-y-4">
                <input type="hidden" id="edit_user_id">
                <div>
                    <label for="edit_username_display" class="block text-sm font-medium text-slate-400 mb-1">Username</label>
                    <input type="text" id="edit_username_display" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 text-white cursor-not-allowed" readonly>
                </div>
                <div>
                    <label for="edit_role" class="block text-sm font-medium text-slate-400 mb-1">Role</label>
                    <select id="edit_role" name="role" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                        <option value="viewer">Viewer</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="flex justify-end gap-4 mt-6">
                    <button type="button" id="cancelEditRoleBtn" class="px-4 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div id="changePasswordModal" class="modal-backdrop hidden">
        <div class="modal-panel bg-slate-800 rounded-lg shadow-xl p-6 w-full max-w-sm border border-slate-700">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold text-white">Change Password for <span id="change_password_username_display" class="text-cyan-400"></span></h2>
                <button id="closeChangePasswordModal" class="text-slate-400 hover:text-white text-2xl">&times;</button>
            </div>
            <form id="changePasswordForm" class="space-y-4">
                <input type="hidden" id="change_password_user_id">
                <div>
                    <label for="new_password_input" class="block text-sm font-medium text-slate-400 mb-1">New Password</label>
                    <input type="password" id="new_password_input" name="new_password" required class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                </div>
                <div>
                    <label for="confirm_new_password_input" class="block text-sm font-medium text-slate-400 mb-1">Confirm New Password</label>
                    <input type="password" id="confirm_new_password_input" name="confirm_new_password" required class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                </div>
                <div class="flex justify-end gap-4 mt-6">
                    <button type="button" id="cancelChangePasswordBtn" class="px-4 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>