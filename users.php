<?php
require_once 'includes/auth_check.php';
include 'header.php';
?>

<style>
    .strength-bar { height: 4px; border-radius: 2px; transition: width 0.3s, background 0.3s; }
    .user-card-enter { animation: fadeSlideIn 0.3s ease forwards; }
    @keyframes fadeSlideIn {
        from { opacity: 0; transform: translateY(6px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.65); display: flex; align-items: center; justify-content: center; z-index: 100; backdrop-filter: blur(2px); }
    .modal-panel { max-height: 90vh; overflow-y: auto; }
</style>

<main id="app">
    <div class="container mx-auto px-4 py-8 max-w-7xl">

        <!-- Page Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
            <div>
                <h1 class="text-3xl font-bold text-white flex items-center gap-3">
                    <span class="w-10 h-10 rounded-xl bg-cyan-500/20 flex items-center justify-center">
                        <i class="fas fa-users text-cyan-400"></i>
                    </span>
                    User Management
                </h1>
                <p class="text-slate-400 text-sm mt-1">Create, edit, and manage system users and their permissions.</p>
            </div>
            <div class="flex items-center gap-3">
                <button onclick="loadUsers()" class="px-4 py-2 rounded-lg bg-slate-700 hover:bg-slate-600 text-slate-300 text-sm transition-colors flex items-center gap-2">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <!-- ── Create User Form ── -->
            <div class="lg:col-span-1">
                <div class="bg-slate-800 border border-slate-700 rounded-xl shadow-xl p-6 sticky top-24">
                    <h2 class="text-lg font-semibold text-white mb-5 flex items-center gap-2">
                        <i class="fas fa-user-plus text-cyan-400"></i> Create New User
                    </h2>

                    <!-- Inline error -->
                    <div id="createUserError" class="hidden mb-4 px-4 py-3 rounded-lg bg-red-900/40 border border-red-700/60 text-red-300 text-sm flex items-start gap-2">
                        <i class="fas fa-exclamation-circle mt-0.5 shrink-0"></i>
                        <span id="createUserErrorMsg"></span>
                    </div>

                    <form id="createUserForm" class="space-y-4" autocomplete="off">
                        <!-- Username -->
                        <div>
                            <label for="new_username" class="block text-sm font-medium text-slate-300 mb-1">
                                Username <span class="text-red-400">*</span>
                            </label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"><i class="fas fa-user text-xs"></i></span>
                                <input type="text" id="new_username" name="username" required autocomplete="off"
                                    class="w-full pl-9 pr-4 py-2.5 bg-slate-900 border border-slate-600 rounded-lg text-white placeholder-slate-500 focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 outline-none transition text-sm"
                                    placeholder="e.g. john.doe">
                            </div>
                        </div>

                        <!-- Password -->
                        <div>
                            <label for="new_password" class="block text-sm font-medium text-slate-300 mb-1">
                                Password <span class="text-red-400">*</span>
                            </label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"><i class="fas fa-lock text-xs"></i></span>
                                <input type="password" id="new_password" name="password" required autocomplete="new-password"
                                    class="w-full pl-9 pr-10 py-2.5 bg-slate-900 border border-slate-600 rounded-lg text-white placeholder-slate-500 focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 outline-none transition text-sm"
                                    placeholder="Min. 6 characters" oninput="checkPasswordStrength(this.value)">
                                <button type="button" onclick="togglePwd('new_password')" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-white">
                                    <i class="fas fa-eye text-xs"></i>
                                </button>
                            </div>
                            <!-- Strength bar -->
                            <div class="mt-1.5 flex gap-1">
                                <div class="strength-bar flex-1 bg-slate-700" id="bar1"></div>
                                <div class="strength-bar flex-1 bg-slate-700" id="bar2"></div>
                                <div class="strength-bar flex-1 bg-slate-700" id="bar3"></div>
                                <div class="strength-bar flex-1 bg-slate-700" id="bar4"></div>
                            </div>
                            <p id="strengthLabel" class="text-xs text-slate-500 mt-1"></p>
                        </div>

                        <!-- Confirm Password -->
                        <div>
                            <label for="new_confirm_password" class="block text-sm font-medium text-slate-300 mb-1">
                                Confirm Password <span class="text-red-400">*</span>
                            </label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"><i class="fas fa-lock text-xs"></i></span>
                                <input type="password" id="new_confirm_password" name="confirm_password" required autocomplete="new-password"
                                    class="w-full pl-9 pr-10 py-2.5 bg-slate-900 border border-slate-600 rounded-lg text-white placeholder-slate-500 focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 outline-none transition text-sm"
                                    placeholder="Re-enter password">
                                <button type="button" onclick="togglePwd('new_confirm_password')" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-white">
                                    <i class="fas fa-eye text-xs"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Role -->
                        <div>
                            <label for="new_role" class="block text-sm font-medium text-slate-300 mb-1">Role</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"><i class="fas fa-shield-alt text-xs"></i></span>
                                <select id="new_role" name="role"
                                    class="w-full pl-9 pr-4 py-2.5 bg-slate-900 border border-slate-600 rounded-lg text-white focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 outline-none transition text-sm appearance-none">
                                    <option value="viewer">Viewer — read-only access</option>
                                    <option value="admin">Admin — full access</option>
                                </select>
                            </div>
                        </div>

                        <!-- User Group -->
                        <div>
                            <label for="new_user_group" class="block text-sm font-medium text-slate-300 mb-1">
                                User Group
                                <span class="ml-1 text-slate-500 text-xs font-normal">(isolates device visibility)</span>
                            </label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"><i class="fas fa-layer-group text-xs"></i></span>
                                <input type="text" id="new_user_group" name="user_group" value="default_group"
                                    class="w-full pl-9 pr-4 py-2.5 bg-slate-900 border border-slate-600 rounded-lg text-white placeholder-slate-500 focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 outline-none transition text-sm font-mono"
                                    placeholder="default_group">
                            </div>
                            <p class="text-xs text-slate-500 mt-1">Users in the same group share the same device pool.</p>
                        </div>

                        <button type="submit" class="w-full px-6 py-2.5 bg-cyan-600 hover:bg-cyan-500 text-white rounded-lg font-semibold transition-colors flex items-center justify-center gap-2 text-sm mt-2">
                            <i class="fas fa-user-plus"></i> Create User
                        </button>
                    </form>
                </div>
            </div>

            <!-- ── User List ── -->
            <div class="lg:col-span-2">
                <div class="bg-slate-800 border border-slate-700 rounded-xl shadow-xl overflow-hidden">
                    <!-- Table Header -->
                    <div class="p-5 border-b border-slate-700 flex flex-col sm:flex-row sm:items-center gap-3">
                        <h2 class="text-lg font-semibold text-white flex items-center gap-2 shrink-0">
                            <i class="fas fa-list text-slate-400"></i> All Users
                            <span id="userCount" class="ml-1 px-2 py-0.5 rounded-full bg-slate-700 text-slate-300 text-xs font-mono"></span>
                        </h2>
                        <!-- Search -->
                        <div class="relative flex-1">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"><i class="fas fa-search"></i></span>
                            <input type="text" id="userSearchInput" placeholder="Search by username, role, or group…"
                                class="w-full pl-8 pr-4 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm placeholder-slate-500 focus:ring-2 focus:ring-cyan-500 outline-none transition">
                        </div>
                    </div>

                    <!-- Loading -->
                    <div id="usersLoader" class="hidden text-center py-10">
                        <i class="fas fa-spinner fa-spin text-2xl text-cyan-400"></i>
                        <p class="text-slate-400 text-sm mt-2">Loading users…</p>
                    </div>

                    <!-- Table -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-slate-900/60">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">User</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Role</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Group</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Created</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="usersTableBody">
                                <!-- Populated by JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- ── Edit Role/Group Modal ── -->
    <div id="editRoleModal" class="modal-backdrop hidden">
        <div class="modal-panel bg-slate-800 rounded-xl shadow-2xl p-6 w-full max-w-md border border-slate-700 m-4">
            <div class="flex items-center justify-between mb-5">
                <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                    <i class="fas fa-user-tag text-yellow-400"></i> Edit User
                </h2>
                <button id="closeEditRoleModal" class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-white hover:bg-slate-700 transition-colors text-lg">&times;</button>
            </div>
            <form id="editRoleForm" class="space-y-4">
                <input type="hidden" id="edit_user_id">
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Username</label>
                    <input type="text" id="edit_username_display" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-4 py-2.5 text-slate-400 cursor-not-allowed text-sm" readonly>
                </div>
                <div>
                    <label for="edit_role" class="block text-sm font-medium text-slate-300 mb-1">Role</label>
                    <select id="edit_role" name="role" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-white focus:ring-2 focus:ring-cyan-500 outline-none transition text-sm">
                        <option value="viewer">Viewer — read-only access</option>
                        <option value="admin">Admin — full access</option>
                    </select>
                </div>
                <div>
                    <label for="edit_group" class="block text-sm font-medium text-slate-300 mb-1">User Group</label>
                    <input type="text" id="edit_group" name="user_group"
                        class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-white font-mono focus:ring-2 focus:ring-cyan-500 outline-none transition text-sm"
                        placeholder="default_group">
                    <p class="text-xs text-slate-500 mt-1">Changing the group moves the user's device access to the new group.</p>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" id="cancelEditRoleBtn" class="px-4 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600 transition-colors text-sm">Cancel</button>
                    <button type="submit" class="px-5 py-2 bg-cyan-600 hover:bg-cyan-500 text-white rounded-lg font-semibold transition-colors text-sm">
                        <i class="fas fa-save mr-1"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── Change Password Modal ── -->
    <div id="changePasswordModal" class="modal-backdrop hidden">
        <div class="modal-panel bg-slate-800 rounded-xl shadow-2xl p-6 w-full max-w-md border border-slate-700 m-4">
            <div class="flex items-center justify-between mb-5">
                <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                    <i class="fas fa-key text-blue-400"></i>
                    Change Password: <span id="change_password_username_display" class="text-cyan-400 ml-1"></span>
                </h2>
                <button id="closeChangePasswordModal" class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-white hover:bg-slate-700 transition-colors text-lg">&times;</button>
            </div>
            <form id="changePasswordForm" class="space-y-4" autocomplete="off">
                <input type="hidden" id="change_password_user_id">
                <div>
                    <label for="new_password_input" class="block text-sm font-medium text-slate-300 mb-1">New Password</label>
                    <div class="relative">
                        <input type="password" id="new_password_input" name="new_password" required autocomplete="new-password"
                            class="w-full pr-10 px-4 py-2.5 bg-slate-900 border border-slate-600 rounded-lg text-white focus:ring-2 focus:ring-cyan-500 outline-none transition text-sm"
                            placeholder="Min. 6 characters">
                        <button type="button" onclick="togglePwd('new_password_input')" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-white">
                            <i class="fas fa-eye text-xs"></i>
                        </button>
                    </div>
                </div>
                <div>
                    <label for="confirm_new_password_input" class="block text-sm font-medium text-slate-300 mb-1">Confirm Password</label>
                    <div class="relative">
                        <input type="password" id="confirm_new_password_input" name="confirm_new_password" required autocomplete="new-password"
                            class="w-full pr-10 px-4 py-2.5 bg-slate-900 border border-slate-600 rounded-lg text-white focus:ring-2 focus:ring-cyan-500 outline-none transition text-sm"
                            placeholder="Re-enter new password">
                        <button type="button" onclick="togglePwd('confirm_new_password_input')" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-white">
                            <i class="fas fa-eye text-xs"></i>
                        </button>
                    </div>
                </div>
                <div id="changePasswordError" class="hidden px-4 py-2.5 rounded-lg bg-red-900/40 border border-red-700/60 text-red-300 text-sm"></div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" id="cancelChangePasswordBtn" class="px-4 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600 transition-colors text-sm">Cancel</button>
                    <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-lg font-semibold transition-colors text-sm">
                        <i class="fas fa-save mr-1"></i> Update Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
// Password visibility toggle
function togglePwd(id) {
    const input = document.getElementById(id);
    if (!input) return;
    input.type = input.type === 'password' ? 'text' : 'password';
}

// Password strength checker
function checkPasswordStrength(password) {
    const bars = ['bar1','bar2','bar3','bar4'];
    const label = document.getElementById('strengthLabel');
    let score = 0;
    if (password.length >= 6) score++;
    if (password.length >= 10) score++;
    if (/[A-Z]/.test(password) && /[a-z]/.test(password)) score++;
    if (/[0-9]/.test(password) && /[^A-Za-z0-9]/.test(password)) score++;

    const colors = ['', '#ef4444', '#f59e0b', '#3b82f6', '#22c55e'];
    const labels = ['', 'Weak', 'Fair', 'Good', 'Strong'];
    bars.forEach((id, i) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.style.backgroundColor = i < score ? colors[score] : '#334155';
    });
    if (label) {
        label.textContent = password.length > 0 ? labels[score] : '';
        label.className = 'text-xs mt-1 ' + (['','text-red-400','text-yellow-400','text-blue-400','text-green-400'][score] || '');
    }
}

// Expose loadUsers globally so the refresh button can call it
var loadUsers;
</script>

<?php include 'footer.php'; ?>