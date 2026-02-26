function initUsers() {
    const API_URL = 'api.php';
    const usersTableBody = document.getElementById('usersTableBody');
    const usersLoader = document.getElementById('usersLoader');
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

    const loadUsers = async () => {
        usersLoader.classList.remove('hidden');
        usersTableBody.innerHTML = '';
        try {
            const users = await api.get('get_users');
            usersTableBody.innerHTML = users.map(user => {
                const isDefaultAdmin = user.username === 'admin';
                const isCurrentUser = user.id == window.currentLoggedInUserId; // Compare with exposed ID

                // Delete button should be disabled for default admin and the current logged-in user
                const deleteDisabled = isDefaultAdmin || isCurrentUser ? 'disabled' : '';
                const deleteClass = deleteDisabled ? 'opacity-50 cursor-not-allowed' : '';

                return `
                    <tr class="border-b border-slate-700">
                        <td class="px-6 py-4 whitespace-nowrap text-white">${user.username}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-slate-400 capitalize">${user.role}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-slate-400">${new Date(user.created_at).toLocaleString()}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <button class="edit-role-btn text-yellow-400 hover:text-yellow-300 mr-3" data-id="${user.id}" data-username="${user.username}" data-role="${user.role}"><i class="fas fa-user-tag mr-2"></i>Edit Role</button>
                            <button class="change-password-btn text-blue-400 hover:text-blue-300 mr-3" data-id="${user.id}" data-username="${user.username}"><i class="fas fa-key mr-2"></i>Change Password</button>
                            <button class="delete-user-btn text-red-500 hover:text-red-400 ${deleteClass}" data-id="${user.id}" data-username="${user.username}" ${deleteDisabled}><i class="fas fa-trash mr-2"></i>Delete</button>
                        </td>
                    </tr>
                `;
            }).join('');
        } catch (error) {
            console.error('Failed to load users:', error);
            window.notyf.error('Failed to load users.');
        } finally {
            usersLoader.classList.add('hidden');
        }
    };

    createUserForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const username = e.target.username.value;
        const password = e.target.password.value;
        const role = e.target.role.value;
        if (!username || !password) return;

        const button = createUserForm.querySelector('button[type="submit"]');
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Creating...';

        try {
            const result = await api.post('create_user', { username, password, role });
            if (result.success) {
                window.notyf.success('User created successfully.');
                createUserForm.reset();
                await loadUsers();
            } else {
                window.notyf.error(`Error: ${result.error}`);
            }
        } catch (error) {
            window.notyf.error('An unexpected error occurred.');
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
            const { id, username, role } = editRoleButton.dataset;
            editUserId.value = id;
            editUsernameDisplay.value = username;
            editRoleSelect.value = role;
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

        const button = editRoleForm.querySelector('button[type="submit"]');
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';

        try {
            const result = await api.post('update_user_role', { id, role });
            if (result.success) {
                window.notyf.success('User role updated successfully.');
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

        if (newPassword !== confirmNewPassword) {
            window.notyf.error('New passwords do not match.');
            return;
        }
        if (newPassword.length < 6) {
            window.notyf.error('Password must be at least 6 characters long.');
            return;
        }

        const button = changePasswordForm.querySelector('button[type="submit"]');
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';

        try {
            const result = await api.post('update_user_password', { id, new_password: newPassword });
            if (result.success) {
                window.notyf.success('User password updated successfully.');
                closeModal('changePasswordModal');
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

    loadUsers();
}