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
        <h1 class="text-3xl font-bold text-white mb-6">Status Event Logs</h1>

        <!-- Filters -->
        <div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-4 mb-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                <div>
                    <label for="mapSelector" class="block text-sm font-medium text-slate-400 mb-1">Map</label>
                    <select id="mapSelector" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500"></select>
                </div>
                <div>
                    <label for="deviceSelector" class="block text-sm font-medium text-slate-400 mb-1">Device</label>
                    <select id="deviceSelector" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                        <option value="">All Devices</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Time Period</label>
                    <div id="periodSelector" class="flex rounded-lg bg-slate-900 border border-slate-600 p-1">
                        <button data-period="live" class="flex-1 px-3 py-1 text-sm rounded-md text-slate-300 hover:bg-slate-700">Live</button>
                        <button data-period="24h" class="flex-1 px-3 py-1 text-sm rounded-md text-slate-300 hover:bg-slate-700 bg-slate-700 text-white">24 Hours</button>
                        <button data-period="7d" class="flex-1 px-3 py-1 text-sm rounded-md text-slate-300 hover:bg-slate-700">7 Days</button>
                        <button data-period="30d" class="flex-1 px-3 py-1 text-sm rounded-md text-slate-300 hover:bg-slate-700">30 Days</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chart Container -->
        <div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-6">
            <h2 id="chartTitle" class="text-xl font-semibold text-white mb-4">Status Events in the Last 24 Hours</h2>
            <div id="chartLoader" class="text-center py-16"><div class="loader mx-auto"></div></div>
            <div class="h-96 hidden" id="chartContainer">
                <canvas id="statusLogChart"></canvas>
            </div>
            <div id="noDataMessage" class="text-center py-16 hidden">
                <i class="fas fa-chart-bar text-slate-600 text-4xl mb-4"></i>
                <p class="text-slate-500">No status event data found for the selected period.</p>
            </div>
        </div>

        <!-- Downtime + Offline Logs -->
        <div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-6 mt-8">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                <h2 class="text-xl font-semibold text-white">Downtime & Offline Logs</h2>
                <select id="downtimeScope" class="bg-slate-900 border border-slate-600 rounded-lg px-3 py-2 text-sm">
                    <option value="day">Day</option>
                    <option value="month">Month</option>
                    <option value="year">Year</option>
                </select>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div>
                    <h3 class="text-sm font-semibold text-slate-300 mb-2">Downtime Summary</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead><tr class="border-b border-slate-700"><th class="px-3 py-2 text-left text-slate-400">Bucket</th><th class="px-3 py-2 text-left text-slate-400">Device</th><th class="px-3 py-2 text-left text-slate-400">Offline Events</th></tr></thead>
                            <tbody id="downtimeSummaryTable"></tbody>
                        </table>
                    </div>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-slate-300 mb-2">Offline Event Logs</h3>
                    <div class="overflow-x-auto max-h-80">
                        <table class="min-w-full text-sm">
                            <thead><tr class="border-b border-slate-700"><th class="px-3 py-2 text-left text-slate-400">Time</th><th class="px-3 py-2 text-left text-slate-400">Device</th><th class="px-3 py-2 text-left text-slate-400">Details</th></tr></thead>
                            <tbody id="offlineLogsTable"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Log Backup Schedules ─────────────────────────────────────── -->
        <div class="bg-slate-800 border border-slate-700 rounded-xl shadow-xl mt-8 overflow-hidden">
            <div class="px-6 py-5 border-b border-slate-700 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold text-white flex items-center gap-2">
                        <i class="fas fa-archive text-cyan-400"></i> Log Backup Schedules
                    </h2>
                    <p class="text-slate-400 text-xs mt-0.5">Automate CSV log exports to NAS, FTP, or Email on a schedule.</p>
                </div>
                <button id="showBackupFormBtn" class="flex items-center gap-2 px-4 py-2 bg-cyan-600 hover:bg-cyan-500 text-white text-sm font-semibold rounded-lg transition-colors">
                    <i class="fas fa-plus"></i> New Schedule
                </button>
            </div>

            <!-- Form panel (hidden by default, toggled by button) -->
            <div id="backupFormPanel" class="hidden border-b border-slate-700 bg-slate-900/40">
                <div class="p-6">
                    <h3 class="text-base font-semibold text-white mb-5 flex items-center gap-2">
                        <i class="fas fa-edit text-cyan-400"></i>
                        <span id="backupFormTitle">Create Backup Schedule</span>
                    </h3>
                    <form id="backupScheduleForm" class="space-y-4">
                        <input type="hidden" id="backupScheduleId">

                        <!-- Row 1: Name + Target Type + Scope -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Schedule Name <span class="text-red-400">*</span></label>
                                <input id="backupName" placeholder="e.g. Daily NAS Log Backup" required
                                    class="w-full bg-slate-900 border border-slate-600 rounded-lg px-3 py-2.5 text-white text-sm focus:ring-2 focus:ring-cyan-500 outline-none transition">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Destination Type</label>
                                <select id="backupTargetType" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-3 py-2.5 text-white text-sm focus:ring-2 focus:ring-cyan-500 outline-none transition">
                                    <option value="nas">NAS (Network / Mounted Volume)</option>
                                    <option value="ftp">FTP Server</option>
                                    <option value="email">Email (SMTP)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Log Scope</label>
                                <select id="backupPeriodScope" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-3 py-2.5 text-white text-sm focus:ring-2 focus:ring-cyan-500 outline-none transition">
                                    <option value="day">Last Day</option>
                                    <option value="month">Last Month</option>
                                    <option value="year">Last Year</option>
                                </select>
                            </div>
                        </div>

                        <!-- NAS Config Group -->
                        <div id="lbNasGroup" class="space-y-3 bg-slate-900/60 border border-slate-700 rounded-xl p-4">
                            <div class="flex items-center gap-2 mb-1">
                                <i class="fas fa-server text-cyan-400 text-sm"></i>
                                <span class="text-sm font-semibold text-slate-300">NAS Configuration</span>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                <div class="md:col-span-2">
                                    <label class="block text-xs font-medium text-slate-400 mb-1">NAS Server IP / Hostname <span class="text-slate-500">(optional — informational)</span></label>
                                    <input id="lbNasIp" placeholder="e.g. 192.168.1.100 or nas.local"
                                        class="w-full bg-slate-900 border border-slate-600 rounded-lg px-3 py-2 text-white text-sm font-mono focus:ring-2 focus:ring-cyan-500 outline-none transition">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-400 mb-1">Protocol Port <span class="text-slate-500">(SMB=445, NFS=2049)</span></label>
                                    <input id="lbNasPort" type="number" placeholder="445"
                                        class="w-full bg-slate-900 border border-slate-600 rounded-lg px-3 py-2 text-white text-sm font-mono focus:ring-2 focus:ring-cyan-500 outline-none transition">
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-slate-400 mb-1">NAS Username <span class="text-slate-500">(optional)</span></label>
                                    <input id="lbNasUsername" placeholder="backup_user"
                                        class="w-full bg-slate-900 border border-slate-600 rounded-lg px-3 py-2 text-white text-sm focus:ring-2 focus:ring-cyan-500 outline-none transition">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-400 mb-1">NAS Password <span class="text-slate-500">(optional)</span></label>
                                    <input id="lbNasPassword" type="password" placeholder="••••••••"
                                        class="w-full bg-slate-900 border border-slate-600 rounded-lg px-3 py-2 text-white text-sm focus:ring-2 focus:ring-cyan-500 outline-none transition">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-400 mb-1">
                                    Destination Path (inside Docker container) <span class="text-red-400">*</span>
                                </label>
                                <div class="flex gap-2">
                                    <input id="lbNasMountPath" placeholder="/mnt/nas/logs or /backups/logs" required
                                        class="flex-1 bg-slate-900 border border-slate-600 rounded-lg px-3 py-2 text-white text-sm font-mono focus:ring-2 focus:ring-cyan-500 outline-none transition">
                                    <button type="button" id="lbNasTestBtn"
                                        class="px-3 py-2 bg-indigo-700 hover:bg-indigo-600 text-white text-xs font-semibold rounded-lg flex items-center gap-1.5 transition-colors whitespace-nowrap">
                                        <i class="fas fa-plug"></i> Test Connection
                                    </button>
                                </div>
                                <p class="text-xs text-slate-500 mt-1">Must match a Docker <code class="text-cyan-400">-v /your/nas/share:/mnt/nas/logs</code> bind-mount.</p>
                            </div>
                            <!-- Test results panel -->
                            <div id="lbNasTestResults" class="hidden rounded-lg overflow-hidden border border-slate-600"></div>
                        </div>

                        <!-- FTP Config Group -->
                        <div id="lbFtpGroup" class="hidden space-y-3 bg-slate-900/60 border border-slate-700 rounded-xl p-4">
                            <div class="flex items-center gap-2 mb-1">
                                <i class="fas fa-upload text-cyan-400 text-sm"></i>
                                <span class="text-sm font-semibold text-slate-300">FTP Configuration</span>
                            </div>
                            <div class="grid grid-cols-3 gap-3">
                                <div class="col-span-2">
                                    <label class="block text-xs font-medium text-slate-400 mb-1">FTP Host / IP <span class="text-red-400">*</span></label>
                                    <input id="lbFtpHost" placeholder="ftp.example.com or 192.168.1.10"
                                        class="w-full bg-slate-900 border border-slate-600 rounded-lg px-3 py-2 text-white text-sm font-mono focus:ring-2 focus:ring-cyan-500 outline-none transition">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-400 mb-1">Port</label>
                                    <input id="lbFtpPort" type="number" value="21" min="1" max="65535"
                                        class="w-full bg-slate-900 border border-slate-600 rounded-lg px-3 py-2 text-white text-sm font-mono focus:ring-2 focus:ring-cyan-500 outline-none transition">
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-slate-400 mb-1">Username <span class="text-red-400">*</span></label>
                                    <input id="lbFtpUser" placeholder="backup_user"
                                        class="w-full bg-slate-900 border border-slate-600 rounded-lg px-3 py-2 text-white text-sm focus:ring-2 focus:ring-cyan-500 outline-none transition">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-400 mb-1">Password</label>
                                    <input id="lbFtpPass" type="password" placeholder="••••••••"
                                        class="w-full bg-slate-900 border border-slate-600 rounded-lg px-3 py-2 text-white text-sm focus:ring-2 focus:ring-cyan-500 outline-none transition">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-400 mb-1">Remote Directory Path</label>
                                <input id="lbFtpPath" value="/backups/logs" placeholder="/backups/logs"
                                    class="w-full bg-slate-900 border border-slate-600 rounded-lg px-3 py-2 text-white text-sm font-mono focus:ring-2 focus:ring-cyan-500 outline-none transition">
                            </div>
                        </div>

                        <!-- Email Config Group -->
                        <div id="lbEmailGroup" class="hidden space-y-3 bg-slate-900/60 border border-slate-700 rounded-xl p-4">
                            <div class="flex items-center gap-2 mb-1">
                                <i class="fas fa-envelope text-cyan-400 text-sm"></i>
                                <span class="text-sm font-semibold text-slate-300">Email Configuration</span>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-400 mb-1">Recipient Email <span class="text-red-400">*</span></label>
                                <input id="lbEmailRecipient" type="email" placeholder="ops@example.com"
                                    class="w-full bg-slate-900 border border-slate-600 rounded-lg px-3 py-2 text-white text-sm focus:ring-2 focus:ring-cyan-500 outline-none transition">
                                <p class="text-xs text-slate-500 mt-1">SMTP settings must be configured in Notifications → Email Settings.</p>
                            </div>
                        </div>

                        <!-- Schedule Settings -->
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Recurrence</label>
                                <select id="backupScheduleType" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-3 py-2.5 text-white text-sm focus:ring-2 focus:ring-cyan-500 outline-none transition">
                                    <option value="daily">Daily</option>
                                    <option value="weekly">Weekly</option>
                                    <option value="monthly">Monthly</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Time</label>
                                <input id="backupScheduleTime" type="time" value="00:15"
                                    class="w-full bg-slate-900 border border-slate-600 rounded-lg px-3 py-2.5 text-white text-sm focus:ring-2 focus:ring-cyan-500 outline-none transition">
                            </div>
                            <div id="lbWeeklyGroup">
                                <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Day of Week</label>
                                <select id="backupDayOfWeek" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-3 py-2.5 text-white text-sm focus:ring-2 focus:ring-cyan-500 outline-none transition">
                                    <option value="1">Monday</option><option value="2">Tuesday</option><option value="3">Wednesday</option>
                                    <option value="4">Thursday</option><option value="5">Friday</option><option value="6">Saturday</option><option value="7">Sunday</option>
                                </select>
                            </div>
                            <div id="lbMonthlyGroup" class="hidden">
                                <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Day of Month</label>
                                <input id="backupDayOfMonth" type="number" min="1" max="28" value="1"
                                    class="w-full bg-slate-900 border border-slate-600 rounded-lg px-3 py-2.5 text-white text-sm focus:ring-2 focus:ring-cyan-500 outline-none transition">
                            </div>
                        </div>

                        <div class="flex items-center justify-between">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input id="backupEnabled" type="checkbox" checked class="h-4 w-4 bg-slate-900 border-slate-600 rounded text-cyan-500 focus:ring-cyan-500">
                                <span class="text-sm text-slate-300 font-medium">Enabled</span>
                            </label>
                            <div class="flex gap-2">
                                <button type="button" id="backupScheduleReset"
                                    class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-slate-300 rounded-lg text-sm transition-colors">
                                    Cancel
                                </button>
                                <button type="submit"
                                    class="px-5 py-2 bg-cyan-600 hover:bg-cyan-500 text-white rounded-lg text-sm font-semibold transition-colors flex items-center gap-2">
                                    <i class="fas fa-save"></i> Save Schedule
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Schedules Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-900/60">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase">Name</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase">Target</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase">Scope</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase">Schedule</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase">Last Run</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase">Status</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-400 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="backupSchedulesTable" class="divide-y divide-slate-700/50">
                        <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500 italic">Loading schedules…</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>

