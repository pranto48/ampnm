function initWhatsappNotifications() {
    const API_URL = 'api.php';

    const els = {
        whatsappSettingsForm: document.getElementById('whatsappSettingsForm'),
        whatsappProvider: document.getElementById('whatsappProvider'),
        whatsappApiUrl: document.getElementById('whatsappApiUrl'),
        whatsappToken: document.getElementById('whatsappToken'),
        toggleTokenVisibility: document.getElementById('toggleTokenVisibility'),
        whatsappPhoneNumber: document.getElementById('whatsappPhoneNumber'),
        whatsappCooldown: document.getElementById('whatsappCooldown'),
        whatsappEnabled: document.getElementById('whatsappEnabled'),
        testRecipientPhone: document.getElementById('testRecipientPhone'),
        sendTestWhatsappBtn: document.getElementById('sendTestWhatsappBtn'),
        saveWhatsappBtn: document.getElementById('saveWhatsappBtn'),
        whatsappLoader: document.getElementById('whatsappLoader'),

        labelApiUrl: document.getElementById('labelApiUrl'),
        helpApiUrl: document.getElementById('helpApiUrl'),
        labelToken: document.getElementById('labelToken'),
        helpToken: document.getElementById('helpToken'),
        labelPhoneNumber: document.getElementById('labelPhoneNumber'),
        helpPhoneNumber: document.getElementById('helpPhoneNumber'),

        deviceSelect: document.getElementById('deviceSelect'),
        subscriptionFormContainer: document.getElementById('subscriptionFormContainer'),
        selectedDeviceName: document.getElementById('selectedDeviceName'),
        deviceSubscriptionForm: document.getElementById('deviceSubscriptionForm'),
        subscriptionId: document.getElementById('subscriptionId'),
        subscriptionDeviceId: document.getElementById('subscriptionDeviceId'),
        recipientPhone: document.getElementById('recipientPhone'),
        notifyOnline: document.getElementById('notifyOnline'),
        notifyOffline: document.getElementById('notifyOffline'),
        notifyWarning: document.getElementById('notifyWarning'),
        notifyCritical: document.getElementById('notifyCritical'),
        saveSubscriptionBtn: document.getElementById('saveSubscriptionBtn'),
        cancelSubscriptionBtn: document.getElementById('cancelSubscriptionBtn'),
        
        subscriptionsTable: document.getElementById('subscriptionsTable'),
        subscriptionsLoader: document.getElementById('subscriptionsLoader'),
        noSubscriptionsMessage: document.getElementById('noSubscriptionsMessage'),
    };

    let currentSelectedDeviceId = null;
    let currentSelectedDeviceLabel = '';

    const api = {
        get: (action, params = {}) => fetch(`${API_URL}?action=${action}&${new URLSearchParams(params)}`).then(res => res.json()),
        post: (action, body = {}) => fetch(`${API_URL}?action=${action}`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) }).then(res => res.json())
    };

    // Toggle Token Password Visibility
    els.toggleTokenVisibility?.addEventListener('click', () => {
        const isPassword = els.whatsappToken.getAttribute('type') === 'password';
        els.whatsappToken.setAttribute('type', isPassword ? 'text' : 'password');
        els.toggleTokenVisibility.innerHTML = isPassword ? '<i class="fas fa-eye-slash"></i>' : '<i class="fas fa-eye"></i>';
    });

    // Dynamic field labels based on provider choice
    const adjustFieldLabels = (provider) => {
        if (provider === 'twilio') {
            els.labelApiUrl.textContent = 'Account SID';
            els.helpApiUrl.textContent = 'Your Twilio Account SID.';
            els.labelToken.textContent = 'Auth Token';
            els.helpToken.textContent = 'Your Twilio Auth Token.';
            els.labelPhoneNumber.textContent = 'Sender Phone Number';
            els.helpPhoneNumber.innerHTML = 'Twilio Sandbox or approved sender number (must start with <code>whatsapp:</code> e.g. <code>whatsapp:+14155238886</code>).';
            if (els.whatsappPhoneNumber.value === '') {
                els.whatsappPhoneNumber.placeholder = 'whatsapp:+14155238886';
            }
        } else if (provider === 'ultramsg') {
            els.labelApiUrl.textContent = 'Instance ID / Custom URL';
            els.helpApiUrl.textContent = 'Your Ultramsg Instance ID (e.g. instance12345) or your custom proxy URL.';
            els.labelToken.textContent = 'Instance Token';
            els.helpToken.textContent = 'Your Ultramsg API/Instance Token.';
            els.labelPhoneNumber.textContent = 'Sender Phone Number';
            els.helpPhoneNumber.innerHTML = 'Your registered Ultramsg sender phone number (e.g. <code>+8801712345678</code>).';
            els.whatsappPhoneNumber.placeholder = '+8801712345678';
        }
    };

    els.whatsappProvider?.addEventListener('change', (e) => {
        adjustFieldLabels(e.target.value);
    });

    // --- WhatsApp Settings Logic ---
    const loadWhatsappSettings = async () => {
        els.whatsappLoader.classList.remove('hidden');
        try {
            const settings = await api.get('get_whatsapp_settings');
            if (settings) {
                els.whatsappProvider.value = settings.provider || 'twilio';
                els.whatsappApiUrl.value = settings.api_url || '';
                els.whatsappToken.value = settings.token || ''; // Masked
                els.whatsappPhoneNumber.value = settings.phone_number || '';
                els.whatsappCooldown.value = settings.cooldown_minutes !== undefined ? settings.cooldown_minutes : 30;
                els.whatsappEnabled.checked = Number(settings.enabled || 0) === 1;
                adjustFieldLabels(els.whatsappProvider.value);
            }
        } catch (error) {
            console.error('Failed to load WhatsApp settings:', error);
            window.notyf.error('Failed to load WhatsApp settings.');
        } finally {
            els.whatsappLoader.classList.add('hidden');
        }
    };

    if (window.userRole === 'admin') {
        els.whatsappSettingsForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            els.saveWhatsappBtn.disabled = true;
            els.saveWhatsappBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';

            const formData = new FormData(els.whatsappSettingsForm);
            const data = Object.fromEntries(formData.entries());
            data.enabled = els.whatsappEnabled.checked;

            try {
                const result = await api.post('save_whatsapp_settings', data);
                if (result.success) {
                    window.notyf.success(result.message);
                    await loadWhatsappSettings();
                } else {
                    window.notyf.error(`Error: ${result.error}`);
                }
            } catch (error) {
                window.notyf.error('An unexpected error occurred while saving WhatsApp settings.');
                console.error(error);
            } finally {
                els.saveWhatsappBtn.disabled = false;
                els.saveWhatsappBtn.innerHTML = '<i class="fas fa-save mr-2"></i>Save Settings';
            }
        });

        els.sendTestWhatsappBtn?.addEventListener('click', async () => {
            const recipientPhone = els.testRecipientPhone?.value?.trim();
            if (!recipientPhone) {
                window.notyf.error('Enter a recipient phone number for the test WhatsApp.');
                return;
            }

            const originalHtml = els.sendTestWhatsappBtn.innerHTML;
            els.sendTestWhatsappBtn.disabled = true;
            els.sendTestWhatsappBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Sending...';

            try {
                const result = await api.post('send_test_whatsapp', { recipient_phone: recipientPhone });
                if (result.success) {
                    window.notyf.success(result.message || 'Test WhatsApp alert sent.');
                } else {
                    const details = result.details ? ` (${result.details})` : '';
                    window.notyf.error(`Error: ${result.error || 'Failed to send test WhatsApp.'}${details}`);
                }
            } catch (error) {
                window.notyf.error('An unexpected error occurred while sending test WhatsApp.');
                console.error(error);
            } finally {
                els.sendTestWhatsappBtn.disabled = false;
                els.sendTestWhatsappBtn.innerHTML = originalHtml;
            }
        });
    } else {
        if (els.whatsappSettingsForm) {
            els.whatsappSettingsForm.querySelectorAll('input, select, button').forEach(el => el.disabled = true);
            els.whatsappSettingsForm.insertAdjacentHTML('afterend', '<p class="text-red-400 text-sm mt-2">You do not have permission to manage WhatsApp settings.</p>');
        }
    }

    // --- Device Subscriptions Logic ---
    const populateDeviceSelect = async () => {
        try {
            const devices = await api.get('get_all_devices_for_subscriptions');
            if (!Array.isArray(devices)) {
                throw new Error(devices?.error || 'Invalid device list response');
            }
            els.deviceSelect.innerHTML = '<option value="">-- Select a device --</option>' + 
                devices.map(d => `<option value="${d.id}">${d.name} (${d.ip || 'No IP'}) ${d.map_name ? `[${d.map_name}]` : ''}</option>`).join('');
        } catch (error) {
            console.error('Failed to load devices for subscriptions:', error);
            window.notyf.error('Failed to load devices.');
        }
    };

    const loadDeviceSubscriptions = async (deviceId) => {
        els.subscriptionsLoader.classList.remove('hidden');
        els.subscriptionsTable.innerHTML = '';
        els.noSubscriptionsMessage.classList.add('hidden');

        try {
            const subscriptions = await api.get('get_device_whatsapp_subscriptions', { device_id: deviceId });
            if (!Array.isArray(subscriptions)) {
                throw new Error(subscriptions?.error || 'Invalid subscriptions response');
            }
            if (subscriptions.length > 0) {
                els.subscriptionsTable.innerHTML = subscriptions.map(sub => `
                    <tr class="border-b border-slate-700 hover:bg-slate-800/20">
                        <td class="px-6 py-4 whitespace-nowrap text-slate-300">${currentSelectedDeviceLabel || 'Selected device'}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-white font-mono">${sub.recipient_phone}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-slate-400">
                            ${sub.notify_on_online ? '<span class="inline-block bg-green-500/20 text-green-400 px-2 py-1 rounded-full text-xs mr-1 mb-1 font-semibold">Online</span>' : ''}
                            ${sub.notify_on_offline ? '<span class="inline-block bg-red-500/20 text-red-400 px-2 py-1 rounded-full text-xs mr-1 mb-1 font-semibold">Offline</span>' : ''}
                            ${sub.notify_on_warning ? '<span class="inline-block bg-yellow-500/20 text-yellow-400 px-2 py-1 rounded-full text-xs mr-1 mb-1 font-semibold">Warning</span>' : ''}
                            ${sub.notify_on_critical ? '<span class="inline-block bg-red-700/20 text-red-600 px-2 py-1 rounded-full text-xs mr-1 mb-1 font-semibold">Critical</span>' : ''}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            ${window.userRole === 'admin' ? `
                                <button class="edit-subscription-btn text-yellow-400 hover:text-yellow-300 mr-3 transition" data-id="${sub.id}" data-device-id="${deviceId}" data-phone="${sub.recipient_phone}" data-online="${sub.notify_on_online}" data-offline="${sub.notify_on_offline}" data-warning="${sub.notify_on_warning}" data-critical="${sub.notify_on_critical}"><i class="fas fa-edit mr-1"></i>Edit</button>
                                <button class="delete-subscription-btn text-red-500 hover:text-red-400 transition" data-id="${sub.id}"><i class="fas fa-trash mr-1"></i>Delete</button>
                            ` : '<span class="text-slate-500">No actions</span>'}
                        </td>
                    </tr>
                `).join('');
            } else {
                els.noSubscriptionsMessage.classList.remove('hidden');
            }
        } catch (error) {
            console.error('Failed to load device subscriptions:', error);
            window.notyf.error('Failed to load subscriptions.');
        } finally {
            els.subscriptionsLoader.classList.add('hidden');
        }
    };

    const resetSubscriptionForm = () => {
        els.subscriptionId.value = '';
        els.recipientPhone.value = '';
        els.notifyOnline.checked = true;
        els.notifyOffline.checked = true;
        els.notifyWarning.checked = false;
        els.notifyCritical.checked = false;
        els.saveSubscriptionBtn.innerHTML = 'Save Subscription';
    };

    els.deviceSelect.addEventListener('change', (e) => {
        currentSelectedDeviceId = e.target.value;
        if (currentSelectedDeviceId) {
            els.subscriptionDeviceId.value = currentSelectedDeviceId;
            currentSelectedDeviceLabel = els.deviceSelect.options[els.deviceSelect.selectedIndex].text;
            els.selectedDeviceName.textContent = currentSelectedDeviceLabel;
            els.subscriptionFormContainer.classList.remove('hidden');
            resetSubscriptionForm();
            loadDeviceSubscriptions(currentSelectedDeviceId);
        } else {
            currentSelectedDeviceLabel = '';
            els.subscriptionFormContainer.classList.add('hidden');
            els.subscriptionsTable.innerHTML = '';
            els.noSubscriptionsMessage.classList.add('hidden');
        }
    });

    if (window.userRole === 'admin') {
        els.deviceSubscriptionForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            els.saveSubscriptionBtn.disabled = true;
            els.saveSubscriptionBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';

            const data = {
                id: els.subscriptionId.value || null,
                device_id: els.subscriptionDeviceId.value,
                recipient_phone: els.recipientPhone.value,
                notify_on_online: els.notifyOnline.checked,
                notify_on_offline: els.notifyOffline.checked,
                notify_on_warning: els.notifyWarning.checked,
                notify_on_critical: els.notifyCritical.checked,
            };

            try {
                const result = await api.post('save_device_whatsapp_subscription', data);
                if (result.success) {
                    window.notyf.success(result.message);
                    resetSubscriptionForm();
                    loadDeviceSubscriptions(currentSelectedDeviceId);
                } else {
                    window.notyf.error(`Error: ${result.error}`);
                }
            } catch (error) {
                window.notyf.error('An unexpected error occurred while saving subscription.');
                console.error(error);
            } finally {
                els.saveSubscriptionBtn.disabled = false;
                els.saveSubscriptionBtn.innerHTML = 'Save Subscription';
            }
        });

        els.cancelSubscriptionBtn.addEventListener('click', resetSubscriptionForm);

        els.subscriptionsTable.addEventListener('click', async (e) => {
            const editButton = e.target.closest('.edit-subscription-btn');
            const deleteButton = e.target.closest('.delete-subscription-btn');

            if (editButton) {
                els.subscriptionId.value = editButton.dataset.id;
                els.recipientPhone.value = editButton.dataset.phone;
                els.notifyOnline.checked = editButton.dataset.online === '1';
                els.notifyOffline.checked = editButton.dataset.offline === '1';
                els.notifyWarning.checked = editButton.dataset.warning === '1';
                els.notifyCritical.checked = editButton.dataset.critical === '1';
                els.saveSubscriptionBtn.innerHTML = 'Update Subscription';
            } else if (deleteButton) {
                const subscriptionId = deleteButton.dataset.id;
                if (confirm('Are you sure you want to delete this WhatsApp subscription?')) {
                    try {
                        const result = await api.post('delete_device_whatsapp_subscription', { id: subscriptionId });
                        if (result.success) {
                            window.notyf.success(result.message);
                            loadDeviceSubscriptions(currentSelectedDeviceId);
                        } else {
                            window.notyf.error(`Error: ${result.error}`);
                        }
                    } catch (error) {
                        window.notyf.error('An unexpected error occurred while deleting subscription.');
                        console.error(error);
                    }
                }
            }
        });
    } else {
        if (els.deviceSubscriptionForm) {
            els.deviceSubscriptionForm.querySelectorAll('input, button').forEach(el => el.disabled = true);
            els.deviceSubscriptionForm.insertAdjacentHTML('afterend', '<p class="text-red-400 text-sm mt-2">You do not have permission to manage subscriptions.</p>');
        }
    }

    loadWhatsappSettings();
    populateDeviceSelect();
}
