<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License...
 * (Commercial licenses available at https://ampnm.itsupport.com.bd/pricing)
 */
require_once 'includes/bootstrap.php';
require_once 'includes/auth_check.php';

// Only admins can manage notification settings
if (($_SESSION['user_role'] ?? 'viewer') !== 'admin') {
    header('Location: index.php');
    exit;
}

include 'header.php';
?>

<main id="app">
    <div class="container mx-auto px-4 py-8 max-w-6xl">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-white flex items-center gap-3">
                    <i class="fab fa-telegram text-cyan-400"></i> Telegram Notifications
                </h1>
                <p class="text-slate-400 text-sm mt-1">Receive device status alerts directly on Telegram, and query metrics via chatbot.</p>
            </div>
            <a href="index.php" class="text-cyan-400 hover:text-cyan-300 text-sm flex items-center gap-1">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <!-- Telegram Settings Card -->
        <div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-6 mb-8">
            <h2 class="text-xl font-semibold text-white mb-4 flex items-center gap-2">
                <i class="fas fa-robot text-cyan-400"></i> Bot Configuration
            </h2>
            <form id="telegramSettingsForm" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label for="telegramBotToken" class="block text-sm font-medium text-slate-400 mb-1">Telegram Bot Token</label>
                        <div class="relative">
                            <input type="password" id="telegramBotToken" name="bot_token" required placeholder="Paste bot token from @BotFather" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500 text-white pr-10">
                            <button type="button" id="toggleTokenVisibility" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-white" aria-label="Toggle token visibility">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <p class="text-xs text-slate-500 mt-1">To get a token, message <a href="https://t.me/BotFather" target="_blank" class="text-cyan-400 hover:underline">@BotFather</a> on Telegram and use the <code>/newbot</code> command.</p>
                    </div>
                    
                    <div class="flex items-center mt-2">
                        <label class="flex items-center text-sm font-medium text-slate-400 cursor-pointer">
                            <input type="checkbox" id="telegramEnabled" name="enabled" class="h-4 w-4 rounded border-slate-500 bg-slate-700 text-cyan-600 focus:ring-cyan-500 cursor-pointer">
                            <span class="ml-2">Enable Telegram Alerts</span>
                        </label>
                    </div>
                </div>

                <div class="bg-slate-900/50 border border-slate-700/50 rounded-lg p-4 space-y-3">
                    <h3 class="text-sm font-semibold text-slate-300 flex items-center gap-2">
                        <i class="fas fa-link text-cyan-400"></i> Webhook Registration
                    </h3>
                    <p class="text-xs text-slate-400">
                        To receive status query commands (like <code>/status</code>, <code>/device</code>) in your Telegram Bot, register this server's webhook URL with Telegram.
                    </p>
                    <div class="flex flex-col md:flex-row gap-2 items-stretch md:items-center">
                        <input type="text" id="telegramWebhookUrl" readonly value="<?php 
                            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
                            $host = $_SERVER['HTTP_HOST'];
                            echo htmlspecialchars($protocol . "://" . $host . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . "/api.php?action=telegram_webhook");
                        ?>" class="flex-1 bg-slate-950 border border-slate-800 rounded-lg px-3 py-1.5 text-xs text-slate-400 font-mono select-all focus:outline-none" aria-label="Webhook URL">
                        
                        <button type="button" id="registerWebhookBtn" class="px-4 py-1.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-xs font-semibold flex items-center justify-center gap-1 transition">
                            <i class="fas fa-cloud-upload-alt"></i> Register Webhook
                        </button>
                    </div>
                </div>

                <div class="flex justify-end pt-2">
                    <div class="w-full md:w-auto flex flex-col md:flex-row gap-2">
                        <input type="text" id="testChatId" placeholder="Telegram Chat ID (e.g. 12345678)" class="w-full md:w-72 bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500 text-white" aria-label="Test chat ID">
                        <button type="button" id="sendTestTelegramBtn" class="px-6 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition">
                            <i class="fas fa-paper-plane mr-2"></i>Send Test Alert
                        </button>
                        <button type="submit" id="saveTelegramBtn" class="px-6 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 transition">
                            <i class="fas fa-save mr-2"></i>Save Settings
                        </button>
                    </div>
                </div>
            </form>
            <div id="telegramLoader" class="text-center py-4 hidden"><div class="loader mx-auto"></div></div>
        </div>

        <!-- Telegram Subscriptions Card -->
        <div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-6">
            <h2 class="text-xl font-semibold text-white mb-4 flex items-center gap-2">
                <i class="fas fa-bell text-cyan-400"></i> Device Telegram Subscriptions
            </h2>
            <div class="mb-4">
                <label for="deviceSelect" class="block text-sm font-medium text-slate-400 mb-1">Select Device</label>
                <select id="deviceSelect" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500 text-slate-300">
                    <option value="">-- Select a device --</option>
                </select>
            </div>

            <!-- Subscription Form (hidden until device selected) -->
            <div id="subscriptionFormContainer" class="hidden border border-slate-700 rounded-lg p-4 mt-4">
                <h3 class="text-lg font-semibold text-white mb-3">Add/Edit Telegram Subscription for <span id="selectedDeviceName" class="text-cyan-400"></span></h3>
                <form id="deviceSubscriptionForm" class="space-y-3">
                    <input type="hidden" id="subscriptionId" name="id">
                    <input type="hidden" id="subscriptionDeviceId" name="device_id">
                    <div>
                        <label for="chatId" class="block text-sm font-medium text-slate-400 mb-1">Recipient Telegram Chat ID</label>
                        <input type="text" id="chatId" name="chat_id" required placeholder="e.g. 12345678" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500 text-white font-mono">
                        <p class="text-xs text-slate-500 mt-1">To find your Chat ID, search and message <code>/start</code> to your bot.</p>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 py-2">
                        <label class="flex items-center text-sm font-medium text-slate-400 cursor-pointer">
                            <input type="checkbox" id="notifyOnline" name="notify_on_online" class="h-4 w-4 rounded border-slate-500 bg-slate-700 text-cyan-600 focus:ring-cyan-500 cursor-pointer">
                            <span class="ml-2">Notify on Online</span>
                        </label>
                        <label class="flex items-center text-sm font-medium text-slate-400 cursor-pointer">
                            <input type="checkbox" id="notifyOffline" name="notify_on_offline" class="h-4 w-4 rounded border-slate-500 bg-slate-700 text-cyan-600 focus:ring-cyan-500 cursor-pointer">
                            <span class="ml-2">Notify on Offline</span>
                        </label>
                        <label class="flex items-center text-sm font-medium text-slate-400 cursor-pointer">
                            <input type="checkbox" id="notifyWarning" name="notify_on_warning" class="h-4 w-4 rounded border-slate-500 bg-slate-700 text-cyan-600 focus:ring-cyan-500 cursor-pointer">
                            <span class="ml-2">Notify on Warning</span>
                        </label>
                        <label class="flex items-center text-sm font-medium text-slate-400 cursor-pointer">
                            <input type="checkbox" id="notifyCritical" name="notify_on_critical" class="h-4 w-4 rounded border-slate-500 bg-slate-700 text-cyan-600 focus:ring-cyan-500 cursor-pointer">
                            <span class="ml-2">Notify on Critical</span>
                        </label>
                    </div>
                    <div class="flex justify-end gap-2 mt-4">
                        <button type="button" id="cancelSubscriptionBtn" class="px-4 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600 transition">Cancel</button>
                        <button type="submit" id="saveSubscriptionBtn" class="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 transition">Save Subscription</button>
                    </div>
                </form>
            </div>

            <!-- Active Subscriptions List -->
            <div id="subscriptionsList" class="mt-6">
                <h3 class="text-lg font-semibold text-white mb-3">Active Telegram Subscriptions</h3>
                <div id="subscriptionsTableBody" class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="border-b border-slate-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Device</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Chat ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Triggers</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="subscriptionsTable" class="divide-y divide-slate-800">
                            <!-- Loaded via JS -->
                        </tbody>
                    </table>
                </div>
                <div id="subscriptionsLoader" class="text-center py-8 hidden"><div class="loader mx-auto"></div></div>
                <div id="noSubscriptionsMessage" class="text-center py-8 hidden">
                    <i class="fas fa-bell-slash text-slate-600 text-4xl mb-4"></i>
                    <p class="text-slate-500">No Telegram subscriptions for this device yet.</p>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>
