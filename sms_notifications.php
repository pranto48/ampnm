<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License...
 * (Commercial licenses available at https://ampnm.itsupport.com.bd/pricing)
 */
require_once 'includes/auth_check.php';
include 'header.php';
?>

<main id="app">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-white mb-6">SMS Notifications</h1>

        <!-- SMS Settings Card -->
        <div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-6 mb-8">
            <h2 class="text-xl font-semibold text-white mb-4">Alpha SMS Gateway Settings (alphasms.biz)</h2>
            <form id="smsSettingsForm" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="smsUsername" class="block text-sm font-medium text-slate-400 mb-1">API Username</label>
                        <input type="text" id="smsUsername" name="username" required class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500 text-white">
                    </div>
                    <div>
                        <label for="smsApiKey" class="block text-sm font-medium text-slate-400 mb-1">API Token / Hash (API Key)</label>
                        <input type="password" id="smsApiKey" name="api_key" placeholder="Leave blank to keep current" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500 text-white">
                    </div>
                    <div>
                        <label for="smsSenderId" class="block text-sm font-medium text-slate-400 mb-1">Sender ID / Masking (Optional)</label>
                        <input type="text" id="smsSenderId" name="sender_id" placeholder="e.g. ITSupport" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500 text-white">
                    </div>
                    <div>
                        <label for="smsCooldown" class="block text-sm font-medium text-slate-400 mb-1">Alert Cooldown (Minutes)</label>
                        <input type="number" id="smsCooldown" name="cooldown_minutes" min="0" required class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500 text-white">
                    </div>
                    <div class="flex items-center md:col-span-2 mt-2">
                        <label class="flex items-center text-sm font-medium text-slate-400">
                            <input type="checkbox" id="smsEnabled" name="enabled" class="h-4 w-4 rounded border-slate-500 bg-slate-700 text-cyan-600 focus:ring-cyan-500">
                            <span class="ml-2">Enable SMS Notifications</span>
                        </label>
                    </div>
                </div>
                <div class="flex justify-end mt-4">
                    <div class="w-full md:w-auto flex flex-col md:flex-row gap-2">
                        <input type="text" id="testRecipientPhone" placeholder="e.g. 01712345678" class="w-full md:w-72 bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500 text-white" aria-label="Test recipient phone">
                        <button type="button" id="sendTestSmsBtn" class="px-6 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition">
                            <i class="fas fa-paper-plane mr-2"></i>Send Test SMS
                        </button>
                        <button type="submit" id="saveSmsBtn" class="px-6 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 transition">
                            <i class="fas fa-save mr-2"></i>Save Settings
                        </button>
                    </div>
                </div>
            </form>
            <div id="smsLoader" class="text-center py-4 hidden"><div class="loader mx-auto"></div></div>
        </div>

        <!-- Device Subscriptions Card -->
        <div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-6">
            <h2 class="text-xl font-semibold text-white mb-4">Device SMS Subscriptions</h2>
            <div class="mb-4">
                <label for="deviceSelect" class="block text-sm font-medium text-slate-400 mb-1">Select Device</label>
                <select id="deviceSelect" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500 text-slate-300">
                    <option value="">-- Select a device --</option>
                </select>
            </div>

            <div id="subscriptionFormContainer" class="hidden border border-slate-700 rounded-lg p-4 mt-4">
                <h3 class="text-lg font-semibold text-white mb-3">Add/Edit SMS Subscription for <span id="selectedDeviceName" class="text-cyan-400"></span></h3>
                <form id="deviceSubscriptionForm" class="space-y-3">
                    <input type="hidden" id="subscriptionId" name="id">
                    <input type="hidden" id="subscriptionDeviceId" name="device_id">
                    <div>
                        <label for="recipientPhone" class="block text-sm font-medium text-slate-400 mb-1">Recipient Phone Number</label>
                        <input type="text" id="recipientPhone" name="recipient_phone" required placeholder="e.g. 01712345678" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500 text-white">
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 py-2">
                        <label class="flex items-center text-sm font-medium text-slate-400">
                            <input type="checkbox" id="notifyOnline" name="notify_on_online" class="h-4 w-4 rounded border-slate-500 bg-slate-700 text-cyan-600 focus:ring-cyan-500">
                            <span class="ml-2">Notify on Online</span>
                        </label>
                        <label class="flex items-center text-sm font-medium text-slate-400">
                            <input type="checkbox" id="notifyOffline" name="notify_on_offline" class="h-4 w-4 rounded border-slate-500 bg-slate-700 text-cyan-600 focus:ring-cyan-500">
                            <span class="ml-2">Notify on Offline</span>
                        </label>
                        <label class="flex items-center text-sm font-medium text-slate-400">
                            <input type="checkbox" id="notifyWarning" name="notify_on_warning" class="h-4 w-4 rounded border-slate-500 bg-slate-700 text-cyan-600 focus:ring-cyan-500">
                            <span class="ml-2">Notify on Warning</span>
                        </label>
                        <label class="flex items-center text-sm font-medium text-slate-400">
                            <input type="checkbox" id="notifyCritical" name="notify_on_critical" class="h-4 w-4 rounded border-slate-500 bg-slate-700 text-cyan-600 focus:ring-cyan-500">
                            <span class="ml-2">Notify on Critical</span>
                        </label>
                    </div>
                    <div class="flex justify-end gap-2 mt-4">
                        <button type="button" id="cancelSubscriptionBtn" class="px-4 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600 transition">Cancel</button>
                        <button type="submit" id="saveSubscriptionBtn" class="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 transition">Save Subscription</button>
                    </div>
                </form>
            </div>

            <div id="subscriptionsList" class="mt-6">
                <h3 class="text-lg font-semibold text-white mb-3">Active SMS Subscriptions</h3>
                <div id="subscriptionsTableBody" class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="border-b border-slate-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Device</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Recipient Phone</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Triggers</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="subscriptionsTable" class="divide-y divide-slate-800">
                            <!-- Subscriptions will be loaded here by JS -->
                        </tbody>
                    </table>
                </div>
                <div id="subscriptionsLoader" class="text-center py-8 hidden"><div class="loader mx-auto"></div></div>
                <div id="noSubscriptionsMessage" class="text-center py-8 hidden">
                    <i class="fas fa-bell-slash text-slate-600 text-4xl mb-4"></i>
                    <p class="text-slate-500">No SMS subscriptions for this device yet.</p>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>
