<?php
require_once 'includes/auth_check.php';
include 'header.php';
?>

<main id="app">
    <div class="container mx-auto px-4 py-8">
        <div class="flex flex-col sm:flex-row items-center justify-between mb-6 gap-4">
            <h1 class="text-3xl font-bold text-white">Device Inventory</h1>
            <div class="flex items-center gap-2">
                <input type="file" id="importDevicesFile" class="hidden" accept=".amp">
                <button id="importDevicesBtn" class="px-4 py-2 bg-slate-600 text-white rounded-lg hover:bg-slate-500"><i class="fas fa-file-import mr-2"></i>Import</button>
                <button id="exportDevicesBtn" class="px-4 py-2 bg-slate-600 text-white rounded-lg hover:bg-slate-500"><i class="fas fa-file-export mr-2"></i>Export All</button>
                <a href="create-device.php" class="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700"><i class="fas fa-plus mr-2"></i>Create New Device</a>
            </div>
        </div>

        <div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-6">
            <div class="flex flex-col md:flex-row items-center justify-between mb-4 gap-4">
                <h2 class="text-xl font-semibold text-white">All Devices</h2>
                <div class="w-full md:w-auto flex items-center gap-4">
                    <div class="relative flex-grow">
                        <input type="search" id="deviceSearchInput" placeholder="Search devices..." class="w-full pl-10 pr-4 py-2 bg-slate-900 border border-slate-600 rounded-lg focus:ring-2 focus:ring-cyan-500">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-500"></i>
                    </div>
                    <button id="bulkCheckBtn" class="px-4 py-2 bg-green-600/50 text-green-300 rounded-lg hover:bg-green-600/80 flex-shrink-0" title="Check All Device Statuses">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="border-b border-slate-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Device</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">IP Address</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Map</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Last Seen</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="devicesTableBody"></tbody>
                </table>
                <div id="tableLoader" class="text-center py-8 hidden"><div class="loader mx-auto"></div></div>
                <div id="noDevicesMessage" class="text-center py-8 hidden">
                    <i class="fas fa-server text-slate-600 text-4xl mb-4"></i>
                    <p class="text-slate-500">No devices found. Create one to get started.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Device Details Modal -->
    <div id="detailsModal" class="modal-backdrop hidden">
        <div class="modal-panel bg-slate-800 rounded-lg shadow-xl p-6 w-full max-w-3xl border border-slate-700">
            <div class="flex items-center justify-between mb-4">
                <h2 id="detailsModalTitle" class="text-2xl font-semibold text-white"></h2>
                <button id="closeDetailsModal" class="text-slate-400 hover:text-white text-2xl">&times;</button>
            </div>
            <div id="detailsModalContent" class="hidden"></div>
            <div id="detailsModalLoader" class="text-center py-16"><div class="loader mx-auto"></div></div>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>