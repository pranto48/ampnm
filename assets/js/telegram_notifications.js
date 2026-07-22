/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License...
 * (Commercial licenses available at https://ampnm.itsupport.com.bd/pricing)
 */
function initTelegramNotifications() {
    const API_URL = 'api.php';

    const els = {
        telegramSettingsForm: document.getElementById('telegramSettingsForm'),
        telegramBotToken: document.getElementById('telegramBotToken'),
        toggleTokenVisibility: document.getElementById('toggleTokenVisibility'),
        telegramEnabled: document.getElementById('telegramEnabled'),
        registerWebhookBtn: document.getElementById('registerWebhookBtn'),
        testChatId: document.getElementById('testChatId'),
        sendTestTelegramBtn: document.getElementById('sendTestTelegramBtn'),
        saveTelegramBtn: document.getElementById('saveTelegramBtn'),
        telegramLoader: document.getElementById('telegramLoader'),

        deviceSelect: document.getElementById('deviceSelect'),
        subscriptionFormContainer: document.getElementById('subscriptionFormContainer'),
        selectedDeviceName: document.getElementById('selectedDeviceName'),
        deviceSubscriptionForm: document.getElementById('deviceSubscriptionForm'),
        subscriptionId: document.getElementById('subscriptionId'),
        subscriptionDeviceId: document.getElementById('subscriptionDeviceId'),
        chatId: document.getElementById('chatId'),
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

    // Toggle Bot Token Password Visibility
    els.toggleTokenVisibility?.addEventListener('click', () => {
        const isPassword = els.telegramBotToken.getAttribute('type') === 'password';
        els.telegramBotToken.setAttribute('type', isPassword ? 'text' : 'password');
        els.toggleTokenVisibility.innerHTML = isPassword ? '<i class="fas fa-eye-slash"></i>' : '<i class="fas fa-eye"></i>';
    });

    // --- Telegram Settings Logic ---
    const loadTelegramSettings = async () => {
        els.telegramLoader.classList.remove('hidden');
        try {
            const settings = await api.get('get_telegram_settings');
            if (settings) {
                els.telegramBotToken.value = settings.bot_token || ''; // Masked as '********'
                els.telegramEnabled.checked = Number(settings.enabled || 0) === 1;
            }
        } catch (error) {
            console.error('Failed to load Telegram settings:', error);
            window.notyf.error('Failed to load Telegram settings.');
        } finally {
            els.telegramLoader.classList.add('hidden');
        }
    };

    // Only admin can configure settings
    if (window.userRole === 'admin') {
        els.telegramSettingsForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            els.saveTelegramBtn.disabled = true;
            els.saveTelegramBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';

            const formData = new FormData(els.telegramSettingsForm);
            const data = Object.fromEntries(formData.entries());
            data.enabled = els.telegramEnabled.checked;

            try {
                const result = await api.post('save_telegram_settings', data);
                if (result.success) {
                    window.notyf.success(result.message);
                    await loadTelegramSettings();
                } else {
                    window.notyf.error(`Error: ${result.error}`);
                }
            } catch (error) {
                window.notyf.error('An unexpected error occurred while saving Telegram settings.');
                console.error(error);
            } finally {
                els.saveTelegramBtn.disabled = false;
                els.saveTelegramBtn.innerHTML = '<i class="fas fa-save mr-2"></i>Save Settings';
            }
        });

        // Webhook registration
        els.registerWebhookBtn?.addEventListener('click', async () => {
            const botToken = els.telegramBotToken.value.trim();
            if (!botToken) {
                window.notyf.error('Enter a Telegram Bot Token first.');
                return;
            }

            const originalHtml = els.registerWebhookBtn.innerHTML;
            els.registerWebhookBtn.disabled = true;
            els.registerWebhookBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Registering...';

            try {
                const result = await api.post('register_telegram_webhook', { bot_token: botToken });
                if (result.success) {
                    window.notyf.success(result.message);
                } else {
                    const details = result.details ? ` (${result.details})` : '';
                    window.notyf.error(`Error: ${result.error || 'Webhook registration failed.'}${details}`);
                }
            } catch (error) {
                window.notyf.error('An unexpected error occurred during webhook registration.');
                console.error(error);
            } finally {
                els.registerWebhookBtn.disabled = false;
                els.registerWebhookBtn.innerHTML = originalHtml;
            }
        });

        els.sendTestTelegramBtn?.addEventListener('click', async () => {
            const chatId = els.testChatId?.value?.trim();
            if (!chatId) {
                window.notyf.error('Enter a recipient Chat ID for the test Telegram message.');
                return;
            }

            const originalHtml = els.sendTestTelegramBtn.innerHTML;
            els.sendTestTelegramBtn.disabled = true;
            els.sendTestTelegramBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Sending...';

            try {
                const result = await api.post('send_test_telegram', { chat_id: chatId });
                if (result.success) {
                    window.notyf.success(result.message || 'Test Telegram alert sent.');
                } else {
                    const details = result.details ? ` (${result.details})` : '';
                    window.notyf.error(`Error: ${result.error || 'Failed to send test alert.'}${details}`);
                }
            } catch (error) {
                window.notyf.error('An unexpected error occurred while sending test Telegram alert.');
                console.error(error);
            } finally {
                els.sendTestTelegramBtn.disabled = false;
                els.sendTestTelegramBtn.innerHTML = originalHtml;
            }
        });
    } else {
        // Viewers read-only
        if (els.telegramSettingsForm) {
            els.telegramSettingsForm.querySelectorAll('input, button').forEach(el => el.disabled = true);
            els.telegramSettingsForm.insertAdjacentHTML('afterend', '<p class="text-red-400 text-sm mt-2">You do not have permission to manage Telegram settings.</p>');
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
            const subscriptions = await api.get('get_device_telegram_subscriptions', { device_id: deviceId });
            if (!Array.isArray(subscriptions)) {
                throw new Error(subscriptions?.error || 'Invalid subscriptions response');
            }
            if (subscriptions.length > 0) {
                els.subscriptionsTable.innerHTML = subscriptions.map(sub => `
                    <tr class="border-b border-slate-700 hover:bg-slate-800/20">
                        <td class="px-6 py-4 whitespace-nowrap text-slate-300">${currentSelectedDeviceLabel || 'Selected device'}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-white font-mono">${sub.chat_id}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-slate-400">
                            ${sub.notify_on_online ? '<span class="inline-block bg-green-500/20 text-green-400 px-2 py-1 rounded-full text-xs mr-1 mb-1 font-semibold">Online</span>' : ''}
                            ${sub.notify_on_offline ? '<span class="inline-block bg-red-500/20 text-red-400 px-2 py-1 rounded-full text-xs mr-1 mb-1 font-semibold">Offline</span>' : ''}
                            ${sub.notify_on_warning ? '<span class="inline-block bg-yellow-500/20 text-yellow-400 px-2 py-1 rounded-full text-xs mr-1 mb-1 font-semibold">Warning</span>' : ''}
                            ${sub.notify_on_critical ? '<span class="inline-block bg-red-700/20 text-red-600 px-2 py-1 rounded-full text-xs mr-1 mb-1 font-semibold">Critical</span>' : ''}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            ${window.userRole === 'admin' ? `
                                <button class="edit-subscription-btn text-yellow-400 hover:text-yellow-300 mr-3 transition" data-id="${sub.id}" data-device-id="${deviceId}" data-chat="${sub.chat_id}" data-online="${sub.notify_on_online}" data-offline="${sub.notify_on_offline}" data-warning="${sub.notify_on_warning}" data-critical="${sub.notify_on_critical}"><i class="fas fa-edit mr-1"></i>Edit</button>
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
        els.chatId.value = '';
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
                chat_id: els.chatId.value,
                notify_on_online: els.notifyOnline.checked,
                notify_on_offline: els.notifyOffline.checked,
                notify_on_warning: els.notifyWarning.checked,
                notify_on_critical: els.notifyCritical.checked,
            };

            try {
                const result = await api.post('save_device_telegram_subscription', data);
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
                els.chatId.value = editButton.dataset.chat;
                els.notifyOnline.checked = editButton.dataset.online === '1';
                els.notifyOffline.checked = editButton.dataset.offline === '1';
                els.notifyWarning.checked = editButton.dataset.warning === '1';
                els.notifyCritical.checked = editButton.dataset.critical === '1';
                els.saveSubscriptionBtn.innerHTML = 'Update Subscription';
            } else if (deleteButton) {
                const subscriptionId = deleteButton.dataset.id;
                if (confirm('Are you sure you want to delete this Telegram subscription?')) {
                    try {
                        const result = await api.post('delete_device_telegram_subscription', { id: subscriptionId });
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

    loadTelegramSettings();
    populateDeviceSelect();
}
