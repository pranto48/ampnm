function initUsers() {
    const API_URL = 'api.php';
    const usersTableBody = document.getElementById('usersTableBody');
    const usersLoader = document.getElementById('usersLoader');
    const userSearchInput = document.getElementById('userSearchInput');
    let allUsers = [];
    const createUserForm = document.getElementById('createUserForm');

    // Edit Role Modal elements
    const editRoleModal = document.getElementById('editRoleModal');
    const closeEditRoleModal = document.getElementById('closeEditRoleModal');
    const cancelEditRoleBtn = document.getElementById('cancelEditRoleBtn');
    const editRoleForm = document.getElementById('editRoleForm');
    const editUserId = document.getElementById('edit_user_id');
    const editUsernameDisplay = document.getElementById('edit_username_display');
    const editRoleSelect = document.getElementById('edit_role');

    // Change Password Modal elements
    const changePasswordModal = document.getElementById('changePasswordModal');
    const closeChangePasswordModal = document.getElementById('closeChangePasswordModal');
    const cancelChangePasswordBtn = document.getElementById('cancelChangePasswordBtn');
    const changePasswordForm = document.getElementById('changePasswordForm');
    const changePasswordUserId = document.getElementById('change_password_user_id');
    const changePasswordUsernameDisplay = document.getElementById('change_password_username_display');
    const newPasswordInput = document.getElementById('new_password_input');
    const confirmNewPasswordInput = document.getElementById('confirm_new_password_input');

    const api = {
        get: (action) => fetch(`${API_URL}?action=${action}`).then(res => res.json()),
        post: (action, body) => fetch(`${API_URL}?action=${action}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        }).then(res => res.json())
    };

    const renderUsers = (users) => {
        if (!users || users.length === 0) {
            usersTableBody.innerHTML = `
                <tr><td colspan="6" class="px-6 py-10 text-center text-slate-400">
                    <i class="fas fa-users mb-2 text-2xl block"></i>No users found.
                </td></tr>`;
            return;
        }
        usersTableBody.innerHTML = users.map(user => {
            const isDefaultAdmin = user.username === 'admin';
            const isCurrentUser = String(user.id) === String(window.currentLoggedInUserId);
            const deleteDisabled = isDefaultAdmin || isCurrentUser ? 'disabled' : '';
            const deleteClass = deleteDisabled ? 'opacity-40 cursor-not-allowed' : 'hover:text-red-400';
            const roleBadge = user.role === 'admin'
                ? '<span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-cyan-900/60 text-cyan-300 border border-cyan-700">Admin</span>'
                : '<span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-700 text-slate-300 border border-slate-600">Viewer</span>';
            const createdAt = user.created_at ? new Date(user.created_at).toLocaleString() : '—';
            return `
                <tr class="border-b border-slate-700/60 hover:bg-slate-700/30 transition-colors">
                    <td class="px-4 py-3 whitespace-nowrap">
                        <div class="flex items-center gap-2">
                            <span class="w-8 h-8 rounded-full bg-slate-600 flex items-center justify-center text-sm font-bold text-white">${user.username.charAt(0).toUpperCase()}</span>
                            <span class="text-white font-medium">${user.username}</span>
                            ${isCurrentUser ? '<span class="text-xs text-slate-400">(you)</span>' : ''}
                        </div>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">${roleBadge}</td>
                    <td class="px-4 py-3 whitespace-nowrap text-slate-400 font-mono text-xs">${user.user_group || 'default_group'}</td>
                    <td class="px-4 py-3 whitespace-nowrap text-slate-400 text-xs">${createdAt}</td>
                    <td class="px-4 py-3 whitespace-nowrap space-x-1">
                        <button class="edit-role-btn inline-flex items-center gap-1 px-2.5 py-1.5 text-xs rounded-md bg-yellow-900/40 text-yellow-300 border border-yellow-700/50 hover:bg-yellow-800/50 transition-colors"
                            data-id="${user.id}" data-username="${user.username}" data-role="${user.role}" data-group="${user.user_group || 'default_group'}">
                            <i class="fas fa-user-tag"></i> Edit
                        </button>
                        <button class="change-password-btn inline-flex items-center gap-1 px-2.5 py-1.5 text-xs rounded-md bg-blue-900/40 text-blue-300 border border-blue-700/50 hover:bg-blue-800/50 transition-colors"
                            data-id="${user.id}" data-username="${user.username}">
                            <i class="fas fa-key"></i> Password
                        </button>
                        <button class="delete-user-btn inline-flex items-center gap-1 px-2.5 py-1.5 text-xs rounded-md bg-red-900/30 text-red-400 border border-red-800/40 ${deleteClass} transition-colors"
                            data-id="${user.id}" data-username="${user.username}" ${deleteDisabled}>
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </td>
                </tr>`;
        }).join('');
    };

    const loadUsers = async () => {
        usersLoader.classList.remove('hidden');
        usersTableBody.innerHTML = '';
        try {
            const users = await api.get('get_users');
            allUsers = Array.isArray(users) ? users : [];
            renderUsers(allUsers);
            // Update count badge
            const countEl = document.getElementById('userCount');
            if (countEl) countEl.textContent = allUsers.length;
        } catch (error) {
            console.error('Failed to load users:', error);
            usersTableBody.innerHTML = `<tr><td colspan="6" class="px-6 py-8 text-center text-red-400"><i class="fas fa-exclamation-circle mr-2"></i>Failed to load users. Check console for details.</td></tr>`;
        } finally {
            usersLoader.classList.add('hidden');
        }
    };

    // Expose globally for the refresh button
    window.loadUsers = loadUsers;

    // Search / filter
    if (userSearchInput) {
        userSearchInput.addEventListener('input', () => {
            const q = userSearchInput.value.trim().toLowerCase();
            if (!q) { renderUsers(allUsers); return; }
            renderUsers(allUsers.filter(u =>
                u.username.toLowerCase().includes(q) || (u.user_group || '').toLowerCase().includes(q) || u.role.toLowerCase().includes(q)
            ));
        });
    }



    createUserForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const username = e.target.username.value.trim();
        const password = e.target.password.value;
        const confirmPassword = e.target.confirm_password ? e.target.confirm_password.value : password;
        const role = e.target.role.value;
        const user_group = (e.target.user_group.value || 'default_group').trim();

        // Client-side validation
        const formError = document.getElementById('createUserError');
        const showError = (msg) => { if (formError) { formError.textContent = msg; formError.classList.remove('hidden'); } else { window.notyf.error(msg); } };
        const clearError = () => { if (formError) formError.classList.add('hidden'); };
        clearError();

        if (!username) { showError('Username is required.'); return; }
        if (!password) { showError('Password is required.'); return; }
        if (password.length < 6) { showError('Password must be at least 6 characters.'); return; }
        if (password !== confirmPassword) { showError('Passwords do not match.'); return; }

        const button = createUserForm.querySelector('button[type="submit"]');
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Creating...';

        try {
            const result = await api.post('create_user', { username, password, role, user_group });
            if (result.success) {
                window.notyf.success(`User "${username}" created successfully.`);
                createUserForm.reset();
                clearError();
                const groupInput = document.getElementById('new_user_group');
                if (groupInput) groupInput.value = 'default_group';
                await loadUsers();
            } else {
                showError(result.error || 'Failed to create user.');
            }
        } catch (error) {
            showError('An unexpected error occurred. Check console.');
            console.error(error);
        } finally {
            button.disabled = false;
            button.innerHTML = '<i class="fas fa-user-plus mr-2"></i>Create User';
        }
    });



    usersTableBody.addEventListener('click', async (e) => {
        const deleteButton = e.target.closest('.delete-user-btn');
        const editRoleButton = e.target.closest('.edit-role-btn');
        const changePasswordButton = e.target.closest('.change-password-btn');

        if (deleteButton && !deleteButton.disabled) { // Check if button is not disabled
            const { id, username } = deleteButton.dataset;
            if (confirm(`Are you sure you want to delete user "${username}"? This action cannot be undone.`)) {
                try {
                    const result = await api.post('delete_user', { id });
                    if (result.success) {
                        window.notyf.success(`User "${username}" deleted.`);
                        await loadUsers();
                    } else {
                        window.notyf.error(`Error: ${result.error}`);
                    }
                } catch (error) {
                    window.notyf.error('An unexpected error occurred.');
                    console.error(error);
                }
            }
        } else if (editRoleButton) {
            const { id, username, role, group } = editRoleButton.dataset;
            editUserId.value = id;
            editUsernameDisplay.value = username;
            editRoleSelect.value = role;
            if (document.getElementById('edit_group')) {
                document.getElementById('edit_group').value = group || 'default_group';
            }
            openModal('editRoleModal');
        } else if (changePasswordButton) {
            const { id, username } = changePasswordButton.dataset;
            changePasswordUserId.value = id;
            changePasswordUsernameDisplay.textContent = username;
            newPasswordInput.value = '';
            confirmNewPasswordInput.value = '';
            openModal('changePasswordModal');
        }
    });

    closeEditRoleModal.addEventListener('click', () => closeModal('editRoleModal'));
    cancelEditRoleBtn.addEventListener('click', () => closeModal('editRoleModal'));

    editRoleForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = editUserId.value;
        const role = editRoleSelect.value;
        const user_group = document.getElementById('edit_group') ? document.getElementById('edit_group').value : 'default_group';

        const button = editRoleForm.querySelector('button[type="submit"]');
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';

        try {
            const result = await api.post('update_user_role', { id, role, user_group });
            if (result.success) {
                window.notyf.success('User updated successfully.');
                closeModal('editRoleModal');
                await loadUsers();
            } else {
                window.notyf.error(`Error: ${result.error}`);
            }
        } catch (error) {
            window.notyf.error('An unexpected error occurred.');
            console.error(error);
        } finally {
            button.disabled = false;
            button.innerHTML = 'Save Changes';
        }
    });

    closeChangePasswordModal.addEventListener('click', () => closeModal('changePasswordModal'));
    cancelChangePasswordBtn.addEventListener('click', () => closeModal('changePasswordModal'));

    changePasswordForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = changePasswordUserId.value;
        const newPassword = newPasswordInput.value;
        const confirmNewPassword = confirmNewPasswordInput.value;

        const errEl = document.getElementById('changePasswordError');
        const showErr = (msg) => { if (errEl) { errEl.textContent = msg; errEl.classList.remove('hidden'); } else { window.notyf.error(msg); } };
        const clearErr = () => { if (errEl) errEl.classList.add('hidden'); };
        clearErr();

        if (newPassword.length < 6) { showErr('Password must be at least 6 characters long.'); return; }
        if (newPassword !== confirmNewPassword) { showErr('Passwords do not match.'); return; }

        const button = changePasswordForm.querySelector('button[type="submit"]');
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Updating...';

        try {
            const result = await api.post('update_user_password', { id, new_password: newPassword });
            if (result.success) {
                window.notyf.success(result.message || 'Password updated successfully.');
                closeModal('changePasswordModal');
                await loadUsers();
            } else {
                showErr(result.error || 'Failed to update password.');
            }
        } catch (error) {
            showErr('An unexpected error occurred. Check console.');
            console.error(error);
        } finally {
            button.disabled = false;
            button.innerHTML = '<i class="fas fa-save mr-1"></i>Update Password';
        }
    });



    loadUsers();
}