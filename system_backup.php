<?php
require_once 'includes/auth_check.php';
include 'header.php';
?>

<main id="app">
    <div class="container mx-auto px-4 py-8">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-3xl font-bold text-white flex items-center gap-2">
                    <i class="fas fa-database text-cyan-400"></i>
                    System Backup Settings
                </h1>
                <p class="text-slate-400 text-sm mt-1">Configure automated FTP or NAS full backups of database and application uploads.</p>
            </div>
            
            <button id="runManualBackupBtn" class="flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-cyan-600 to-indigo-600 hover:from-cyan-500 hover:to-indigo-500 text-white font-bold text-sm rounded-lg shadow-lg hover:shadow-cyan-500/20 transition-all active:scale-95">
                <i class="fas fa-play"></i>
                Run Full Backup Now
            </button>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Backup Configuration Form -->
            <div class="lg:col-span-1 bg-slate-800/80 border border-slate-700/80 rounded-2xl shadow-xl p-6 backdrop-blur-md">
                <h2 class="text-xl font-bold text-white mb-4 flex items-center gap-2">
                    <i class="fas fa-clock text-cyan-400"></i>
                    <span id="formTitle">Create Backup Schedule</span>
                </h2>
                
                <form id="systemBackupForm" class="space-y-4">
                    <input type="hidden" id="scheduleId" value="">
                    
                    <div>
                        <label for="backupName" class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Schedule Name</label>
                        <input id="backupName" required placeholder="e.g., Daily NAS Backup" class="w-full bg-slate-900/60 border border-slate-650 rounded-lg px-4 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors">
                    </div>

                    <div>
                        <label for="targetType" class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Backup Target Destination</label>
                        <select id="targetType" class="w-full bg-slate-900/60 border border-slate-650 rounded-lg px-4 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors">
                            <option value="local">Local Storage (/uploads/backups)</option>
                            <option value="nas">NAS (Local Folder / Mounted Share)</option>
                            <option value="ftp">FTP Server</option>
                        </select>
                    </div>

                    <!-- Target Configurations (NAS / Mounted volume) -->
                    <div id="nasConfigGroup" class="space-y-3">
                        <div>
                            <label for="nasMountPath" class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">NAS Destination Path (Inside Container)</label>
                            <input id="nasMountPath" placeholder="e.g., /backups/nas" class="w-full bg-slate-900/60 border border-slate-650 rounded-lg px-4 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors">
                            <span class="text-[10px] text-slate-500 mt-1 block">Specify the target mount path configured in your docker volumes.</span>
                        </div>
                    </div>

                    <!-- Target Configurations (FTP) -->
                    <div id="ftpConfigGroup" class="space-y-3 hidden">
                        <div class="grid grid-cols-3 gap-2">
                            <div class="col-span-2">
                                <label for="ftpHost" class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">FTP Host</label>
                                <input id="ftpHost" placeholder="ftp.example.com" class="w-full bg-slate-900/60 border border-slate-650 rounded-lg px-4 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors">
                            </div>
                            <div>
                                <label for="ftpPort" class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Port</label>
                                <input id="ftpPort" type="number" value="21" class="w-full bg-slate-900/60 border border-slate-650 rounded-lg px-4 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label for="ftpUser" class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">FTP Username</label>
                                <input id="ftpUser" placeholder="backup_user" class="w-full bg-slate-900/60 border border-slate-650 rounded-lg px-4 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors">
                            </div>
                            <div>
                                <label for="ftpPass" class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">FTP Password</label>
                                <input id="ftpPass" type="password" placeholder="••••••••" class="w-full bg-slate-900/60 border border-slate-650 rounded-lg px-4 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors">
                            </div>
                        </div>
                        <div>
                            <label for="ftpPath" class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Remote Directory Path</label>
                            <input id="ftpPath" value="/backups" placeholder="/backups" class="w-full bg-slate-900/60 border border-slate-650 rounded-lg px-4 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors">
                        </div>
                    </div>

                    <!-- Trigger Rules / Time Interval -->
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label for="scheduleType" class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Recurrence</label>
                            <select id="scheduleType" class="w-full bg-slate-900/60 border border-slate-650 rounded-lg px-4 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors">
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                            </select>
                        </div>
                        <div>
                            <label for="scheduleTime" class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Time</label>
                            <input id="scheduleTime" type="time" value="02:00" class="w-full bg-slate-900/60 border border-slate-650 rounded-lg px-4 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors">
                        </div>
                    </div>

                    <div id="weeklyDayGroup" class="hidden">
                        <label for="dayOfWeek" class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Day of Week</label>
                        <select id="dayOfWeek" class="w-full bg-slate-900/60 border border-slate-650 rounded-lg px-4 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors">
                            <option value="1">Monday</option>
                            <option value="2">Tuesday</option>
                            <option value="3">Wednesday</option>
                            <option value="4">Thursday</option>
                            <option value="5">Friday</option>
                            <option value="6">Saturday</option>
                            <option value="7">Sunday</option>
                        </select>
                    </div>

                    <div id="monthlyDayGroup" class="hidden">
                        <label for="dayOfMonth" class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Day of Month</label>
                        <input id="dayOfMonth" type="number" min="1" max="28" value="1" class="w-full bg-slate-900/60 border border-slate-650 rounded-lg px-4 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors">
                    </div>

                    <div class="flex items-center gap-2">
                        <input id="enabled" type="checkbox" checked class="h-4 w-4 bg-slate-900 border-slate-600 rounded text-cyan-500 focus:ring-cyan-500">
                        <label for="enabled" class="text-sm font-semibold text-slate-300">Enabled</label>
                    </div>

                    <div class="flex gap-2 pt-2">
                        <button type="submit" class="flex-1 py-2.5 bg-cyan-600 hover:bg-cyan-500 text-white rounded-lg text-sm font-bold shadow-md hover:shadow-cyan-500/10 transition-colors">Save Schedule</button>
                        <button type="button" id="resetBtn" class="px-4 py-2.5 bg-slate-700 hover:bg-slate-600 text-slate-300 rounded-lg text-sm font-bold transition-colors">Reset</button>
                    </div>
                </form>
            </div>

            <!-- Schedules & Run History -->
            <div class="lg:col-span-2 space-y-8">
                <!-- Backup Schedules Table -->
                <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl shadow-xl p-6 backdrop-blur-md">
                    <h2 class="text-xl font-bold text-white mb-4 flex items-center gap-2">
                        <i class="fas fa-list text-cyan-400"></i>
                        Active Schedules
                    </h2>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm border-collapse min-w-[500px]">
                            <thead>
                                <tr class="border-b border-slate-750 text-slate-400 font-semibold">
                                    <th class="py-3 px-2">Name</th>
                                    <th class="py-3 px-2">Destination</th>
                                    <th class="py-3 px-2">Trigger Interval</th>
                                    <th class="py-3 px-2">Next Scheduled Run</th>
                                    <th class="py-3 px-2 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="schedulesTableBody" class="divide-y divide-slate-750 text-slate-300">
                                <tr>
                                    <td colspan="5" class="py-6 text-center text-slate-500 italic">Loading schedules...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Backup Runs History Table -->
                <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl shadow-xl p-6 backdrop-blur-md">
                    <h2 class="text-xl font-bold text-white mb-4 flex items-center gap-2">
                        <i class="fas fa-history text-cyan-400"></i>
                        Backup Execution Logs
                    </h2>
                    
                    <div class="overflow-x-auto max-h-96">
                        <table class="w-full text-left text-sm border-collapse min-w-[600px]">
                            <thead>
                                <tr class="border-b border-slate-750 text-slate-400 font-semibold">
                                    <th class="py-3 px-2">Date/Time</th>
                                    <th class="py-3 px-2">File Info</th>
                                    <th class="py-3 px-2">Type</th>
                                    <th class="py-3 px-2">Status</th>
                                    <th class="py-3 px-2 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="runsTableBody" class="divide-y divide-slate-750 text-slate-300">
                                <tr>
                                    <td colspan="5" class="py-6 text-center text-slate-500 italic">Loading run logs...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="assets/js/system_backup.js?v=<?= time() ?>"></script>

<?php include 'footer.php'; ?>
