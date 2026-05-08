<?php
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

        <!-- Backup Schedules -->
        <div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-6 mt-8">
            <h2 class="text-xl font-semibold text-white mb-4">Log Backup Schedules (FTP / SMB / Email)</h2>
            <form id="backupScheduleForm" class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
                <input type="hidden" id="backupScheduleId">
                <input id="backupName" placeholder="Schedule name" class="bg-slate-900 border border-slate-600 rounded-lg px-3 py-2">
                <select id="backupTargetType" class="bg-slate-900 border border-slate-600 rounded-lg px-3 py-2">
                    <option value="ftp">FTP</option>
                    <option value="smb">SMB</option>
                    <option value="email">Email</option>
                </select>
                <select id="backupPeriodScope" class="bg-slate-900 border border-slate-600 rounded-lg px-3 py-2">
                    <option value="day">Day Logs</option>
                    <option value="month">Month Logs</option>
                    <option value="year">Year Logs</option>
                </select>
                <select id="backupScheduleType" class="bg-slate-900 border border-slate-600 rounded-lg px-3 py-2">
                    <option value="daily">Daily</option>
                    <option value="weekly">Weekly</option>
                    <option value="monthly">Monthly</option>
                </select>
                <input id="backupScheduleTime" type="time" value="00:15" class="bg-slate-900 border border-slate-600 rounded-lg px-3 py-2">
                <input id="backupDayOfWeek" type="number" min="1" max="7" placeholder="Day of week (1-7)" class="bg-slate-900 border border-slate-600 rounded-lg px-3 py-2">
                <input id="backupDayOfMonth" type="number" min="1" max="28" placeholder="Day of month (1-28)" class="bg-slate-900 border border-slate-600 rounded-lg px-3 py-2">
                <label class="flex items-center gap-2 text-sm text-slate-300"><input id="backupEnabled" type="checkbox" checked>Enabled</label>
                <textarea id="backupTargetConfig" rows="3" placeholder='Target JSON: {"host":"ftp.example.com","username":"u","password":"p","remote_path":"/backups"} | {"mount_path":"/mnt/smb_backups"} | {"recipient_email":"ops@example.com"}' class="md:col-span-3 bg-slate-900 border border-slate-600 rounded-lg px-3 py-2"></textarea>
                <div class="md:col-span-3 flex gap-2">
                    <button type="submit" class="px-4 py-2 bg-cyan-600 rounded-lg text-white">Save Schedule</button>
                    <button type="button" id="backupScheduleReset" class="px-4 py-2 bg-slate-700 rounded-lg text-slate-300">Reset</button>
                </div>
            </form>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead><tr class="border-b border-slate-700"><th class="px-3 py-2 text-left text-slate-400">Name</th><th class="px-3 py-2 text-left text-slate-400">Target</th><th class="px-3 py-2 text-left text-slate-400">Scope</th><th class="px-3 py-2 text-left text-slate-400">Schedule</th><th class="px-3 py-2 text-left text-slate-400">Last Run</th><th class="px-3 py-2 text-left text-slate-400">Actions</th></tr></thead>
                    <tbody id="backupSchedulesTable"></tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>
