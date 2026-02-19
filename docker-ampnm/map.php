<?php
require_once 'includes/auth_check.php';
include 'header.php';

// Get user role from session
$user_role = $_SESSION['user_role'] ?? 'viewer';
$is_admin = ($user_role === 'admin');

// Load device icons library for icon mapping on map
$deviceIconsLibrary = require_once 'includes/device_icons.php';
?>

<main id="app">
    <div class="container mx-auto px-4 py-8">
        <div id="map-selection" class="mb-6">
            <div class="flex items-center justify-between mb-4">
                <h1 class="text-3xl font-bold text-white">Network Map</h1>
                <div class="flex gap-4">
                    <select id="mapSelector" class="bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500"></select>
                    <button id="newMapBtn" class="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700" <?= $is_admin ? '' : 'disabled' ?>><i class="fas fa-plus mr-2"></i>New Map</button>
                    <button id="renameMapBtn" class="px-4 py-2 bg-yellow-600/80 text-white rounded-lg hover:bg-yellow-700" <?= $is_admin ? '' : 'disabled' ?>><i class="fas fa-edit mr-2"></i>Rename Map</button>
                    <button id="deleteMapBtn" class="px-4 py-2 bg-red-600/80 text-white rounded-lg hover:bg-red-700" <?= $is_admin ? '' : 'disabled' ?>><i class="fas fa-trash mr-2"></i>Delete Map</button>
                    <button id="shareMapBtn" class="px-4 py-2 bg-gradient-to-r from-blue-600 to-cyan-600 text-white rounded-lg hover:from-blue-700 hover:to-cyan-700 shadow-lg hover:shadow-xl transition-all duration-200 font-semibold"><i class="fas fa-share-alt mr-2"></i>Share Map</button>
                </div>
            </div>
        </div>

        <div id="map-container" class="hidden">
            <div id="map-controls" class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-4 mb-6">
                <div class="flex items-center justify-between">
                    <h2 id="currentMapName" class="text-xl font-semibold text-white"></h2>
                    <div class="flex items-center gap-2">
                        <button id="scanNetworkBtn" class="px-3 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600" title="Scan Network" <?= $is_admin ? '' : 'disabled' ?>><i class="fas fa-search"></i></button>
                        <button id="refreshStatusBtn" class="px-3 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600" title="Refresh Device Statuses"><i class="fas fa-sync-alt"></i></button>
                        
                        <div class="flex items-center space-x-2 pl-2 ml-2 border-l border-slate-700">
                            <label for="liveRefreshToggle" class="text-sm text-slate-400 select-none cursor-pointer">Live Status</label>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" id="liveRefreshToggle" class="sr-only peer" <?= $is_admin ? '' : 'disabled checked' ?>>
                                <div class="w-11 h-6 bg-slate-600 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-cyan-600"></div>
                            </label>
                            <?php if (!$is_admin): ?>
                                <span class="text-xs text-slate-500">(Always On)</span>
                            <?php endif; ?>
                        </div>

                        <div class="pl-2 ml-2 border-l border-slate-700 flex items-center gap-2">
                            <button id="placeDeviceBtn" class="px-3 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600" title="Place Existing Device" <?= $is_admin ? '' : 'disabled' ?>><i class="fas fa-download"></i></button>
                            <a href="create-device.php" id="addDeviceBtn" class="px-3 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600" title="Add New Device" <?= $is_admin ? '' : 'style="display:none;"' ?>><i class="fas fa-plus"></i></a>
                            <button id="addEdgeBtn" class="px-3 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600" title="Add Connection" <?= $is_admin ? '' : 'disabled' ?>><i class="fas fa-project-diagram"></i></button>
                            <button id="exportBtn" class="px-3 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600" title="Export Map" <?= $is_admin ? '' : 'disabled' ?>><i class="fas fa-file-export"></i></button>
                            <button id="importBtn" class="px-3 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600" title="Import Map" <?= $is_admin ? '' : 'disabled' ?>><i class="fas fa-file-import"></i></button>
                            <input type="file" id="importFile" class="hidden" accept=".json">
                            <button id="mapSettingsBtn" class="px-3 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600" title="Map Settings" <?= $is_admin ? '' : 'disabled' ?>><i class="fas fa-cog"></i></button>
                            <button id="fullscreenBtn" class="px-3 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600" title="Toggle Fullscreen"><i class="fas fa-expand"></i></button>
                        </div>
                    </div>
                </div>
            </div>
            <div id="network-map-wrapper">
                <div id="network-map"></div>
                <div id="context-menu" class="context-menu"></div>
                <div id="status-legend">
                    <!-- Legend items are now generated by map.js -->
                </div>
                <div id="connection-legend" class="absolute bottom-4 right-4 bg-slate-800/90 backdrop-blur-sm border border-slate-700 rounded-lg p-4 shadow-xl hidden">
                    <h3 class="text-sm font-semibold text-white mb-3 flex items-center">
                        <i class="fas fa-project-diagram mr-2 text-cyan-400"></i>
                        Connection Types
                    </h3>
                    <div class="space-y-2 text-xs">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-0.5 rounded-full" style="background-color: #a78bfa; box-shadow: 0 0 6px #a78bfa;"></div>
                            <span class="text-slate-300">🔌 CAT5 Cable</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-0.5 rounded-full" style="background-color: #f97316; box-shadow: 0 0 6px #f97316;"></div>
                            <span class="text-slate-300">💡 Fiber Optic</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-0.5 rounded-full" style="background-color: #38bdf8; box-shadow: 0 0 6px #38bdf8;"></div>
                            <span class="text-slate-300">📡 WiFi</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-0.5 rounded-full" style="background-color: #84cc16; box-shadow: 0 0 6px #84cc16;"></div>
                            <span class="text-slate-300">📻 Radio</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-0.5 rounded-full" style="background-color: #60a5fa; box-shadow: 0 0 6px #60a5fa;"></div>
                            <span class="text-slate-300">🌐 LAN</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-0.5 rounded-full" style="background-color: #c084fc; box-shadow: 0 0 6px #c084fc;"></div>
                            <span class="text-slate-300">🔒 Tunnel</span>
                        </div>
                    </div>
                    <button id="toggleConnectionLegend" class="mt-3 text-xs text-cyan-400 hover:text-cyan-300 transition">
                        <i class="fas fa-eye-slash mr-1"></i>Hide Legend
                    </button>
                </div>
                <button id="showConnectionLegend" class="absolute bottom-4 right-4 bg-slate-800/90 backdrop-blur-sm border border-slate-700 rounded-lg p-2 shadow-xl text-slate-300 hover:text-white hover:bg-slate-700/90 transition">
                    <i class="fas fa-project-diagram mr-1"></i>
                    <span class="text-xs">Connection Types</span>
                </button>
            </div>
        </div>
        
        <div id="no-maps" class="text-center py-16 bg-slate-800 border border-slate-700 rounded-lg hidden">
            <i class="fas fa-map-signs text-slate-600 text-5xl mb-4"></i>
            <h2 class="text-2xl font-bold text-white mb-2">No Network Maps Found</h2>
            <p class="text-slate-400 mb-6">Create a map to start visualizing your network.</p>
            <button id="createFirstMapBtn" class="px-6 py-3 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 text-lg" <?= $is_admin ? '' : 'disabled' ?>>Create Your First Map</button>
        </div>
    </div>

    <!-- Modals -->
    <!-- The old deviceModal HTML is removed as it's replaced by React components -->

    <div id="edgeModal" class="modal-backdrop hidden">
        <div class="modal-panel bg-slate-800 rounded-lg shadow-xl p-6 w-full max-w-md border border-slate-700">
            <h2 class="text-xl font-semibold text-white mb-4"><i class="fas fa-project-diagram mr-2 text-cyan-400"></i>Edit Connection</h2>
            <form id="edgeForm">
                <input type="hidden" id="edgeId">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-300 mb-2">Connection Type</label>
                    <select id="connectionType" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-3 focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 text-white">
                        <option value="" disabled selected>Select a connection type</option>
                        <option value="cat5" data-color="#a78bfa">🔌 CAT5 Cable</option>
                        <option value="fiber" data-color="#f97316">💡 Fiber Optic</option>
                        <option value="wifi" data-color="#38bdf8">📡 WiFi</option>
                        <option value="radio" data-color="#84cc16">📻 Radio</option>
                        <option value="lan" data-color="#60a5fa">🌐 LAN</option>
                        <option value="logical-tunneling" data-color="#c084fc">🔒 Logical Tunneling</option>
                    </select>
                </div>
                <div id="connectionColorPreview" class="mb-4 p-4 bg-slate-900 rounded-lg border border-slate-700 hidden">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-400">Line Color Preview:</span>
                        <div class="flex items-center gap-2">
                            <div id="colorPreviewLine" class="w-16 h-1 rounded-full"></div>
                            <span id="colorPreviewLabel" class="text-xs text-slate-500"></span>
                        </div>
                    </div>
                </div>
                <div class="bg-slate-900/50 p-3 rounded-lg mb-4">
                    <p class="text-xs text-slate-400"><i class="fas fa-info-circle mr-1 text-cyan-400"></i><strong>Tip:</strong> Each connection type has a unique color on the map for easy identification.</p>
                </div>
                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" id="cancelEdgeBtn" class="px-4 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600 transition">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 transition">
                        <i class="fas fa-save mr-2"></i>Save Connection
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="scanModal" class="modal-backdrop hidden">
        <div class="modal-panel bg-slate-800 rounded-lg shadow-xl p-6 w-full max-w-2xl border border-slate-700">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold text-white">Scan Network for Devices</h2>
                <button id="closeScanModal" class="text-slate-400 hover:text-white text-2xl">&times;</button>
            </div>
            <div class="bg-slate-900/50 p-4 rounded-lg border border-slate-700 mb-4">
                <form id="scanForm" class="flex flex-col sm:flex-row gap-4">
                    <input type="text" id="subnetInput" placeholder="e.g., 192.168.1.0/24" value="192.168.1.0/24" class="flex-1 bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                    <button type="submit" id="startScanBtn" class="px-6 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700">
                        <i class="fas fa-search mr-2"></i>Start Scan
                    </button>
                </form>
            </div>
            <div id="scanResultWrapper" class="max-h-96 overflow-y-auto">
                <div id="scanLoader" class="text-center py-8 hidden"><div class="loader mx-auto"></div><p class="mt-2 text-slate-400">Scanning... this may take a moment.</p></div>
                <div id="scanResults"></div>
                <div id="scanInitialMessage" class="text-center py-8 text-slate-500">
                    <i class="fas fa-network-wired text-4xl mb-4"></i>
                    <p>Enter a subnet and start the scan to discover devices.</p>
                    <p class="text-sm mt-2">(Requires <a href="https://nmap.org/" target="_blank" class="text-cyan-400 hover:underline">nmap</a> to be installed on the server)</p>
                </div>
            </div>
        </div>
    </div>

    <div id="mapSettingsModal" class="modal-backdrop hidden">
        <div class="modal-panel bg-slate-800 rounded-lg shadow-xl p-6 w-full max-w-lg border border-slate-700">
            <h2 class="text-xl font-semibold text-white mb-4">Map Appearance Settings</h2>
            <form id="mapSettingsForm">
                <div class="space-y-4">
                    <div>
                        <label for="mapBgColor" class="block text-sm font-medium text-slate-400 mb-1">Background Color</label>
                        <div class="flex items-center gap-2">
                            <input type="color" id="mapBgColor" name="background_color" class="p-1 h-10 w-14 block bg-slate-900 border border-slate-600 cursor-pointer rounded-lg" value="#1e293b">
                            <input type="text" id="mapBgColorHex" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                        </div>
                    </div>
                    <div>
                        <label for="mapBgImageUrl" class="block text-sm font-medium text-slate-400 mb-1">Background Image URL</label>
                        <input type="text" id="mapBgImageUrl" name="background_image_url" placeholder="Leave blank for no image" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                    </div>
                    <div class="text-center text-slate-500 text-sm">OR</div>
                    <div>
                        <label for="mapBgUpload" class="block text-sm font-medium text-slate-400 mb-1">Upload Background Image</label>
                        <input type="file" id="mapBgUpload" accept="image/*" class="w-full text-sm text-slate-400 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-cyan-600/20 file:text-cyan-300 hover:file:bg-cyan-600/40">
                        <div id="mapBgUploadLoader" class="hidden mt-2"><div class="loader inline-block w-4 h-4"></div><span class="ml-2 text-sm">Uploading...</span></div>
                    </div>
                </div>
                <div class="border-t border-slate-700 pt-4 mt-4 space-y-3">
                    <h3 class="text-lg font-semibold text-white">Public View Settings</h3>
                    <div>
                        <label for="publicViewToggle" class="flex items-center text-sm font-medium text-slate-400 cursor-pointer">
                            <input type="checkbox" id="publicViewToggle" name="public_view_enabled" class="h-4 w-4 rounded border-slate-500 bg-slate-700 text-cyan-600 focus:ring-cyan-500">
                            <span class="ml-2">Enable Public View</span>
                        </label>
                        <p class="text-xs text-slate-500 mt-1">Allow anyone with the link to view this map without logging in.</p>
                    </div>
                    <div id="publicViewLinkContainer" class="hidden space-y-2">
                        <label class="block text-sm font-medium text-slate-400">Public Link:</label>
                        <div class="flex items-center gap-2">
                            <input type="text" id="publicViewLink" class="flex-grow bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 text-white text-sm cursor-text" readonly>
                            <button type="button" id="copyPublicLinkBtn" class="px-3 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600 text-sm flex items-center">
                                <i class="fas fa-copy mr-1"></i>Copy
                            </button>
                            <button type="button" id="openPublicLinkBtn" class="px-3 py-2 bg-cyan-700 text-white rounded-lg hover:bg-cyan-600 text-sm flex items-center">
                                <i class="fas fa-external-link-alt mr-1"></i>Open
                            </button>
                        </div>
                    </div>
                </div>
                <div class="flex justify-between items-center mt-6">
                    <button type="button" id="resetMapBgBtn" class="px-4 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600">Reset to Default</button>
                    <div>
                        <button type="button" id="cancelMapSettingsBtn" class="px-4 py-2 bg-slate-600 text-slate-300 rounded-lg hover:bg-slate-500 mr-2">Close</button>
                        <button type="submit" class="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700">Save Changes</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Place Device Modal -->
    <div id="placeDeviceModal" class="modal-backdrop hidden">
        <div class="modal-panel bg-slate-800 rounded-lg shadow-xl p-6 w-full max-w-lg border border-slate-700">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold text-white">Place an Existing Device</h2>
                <button id="closePlaceDeviceModal" class="text-slate-400 hover:text-white text-2xl">&times;</button>
            </div>
            <div id="placeDeviceList" class="max-h-96 overflow-y-auto">
                <!-- Unmapped devices will be listed here -->
            </div>
            <div id="placeDeviceLoader" class="text-center py-8 hidden"><div class="loader mx-auto"></div></div>
        </div>
    </div>
</main>

<!-- Load device icons library for JavaScript icon mapping -->
<script>
    window.deviceIconsLibrary = <?= json_encode($deviceIconsLibrary) ?>;
</script>

<!-- Ensure map refreshes after returning from edit page (bfcache) -->
<script>
    window.addEventListener('pageshow', function (e) {
        if (e && e.persisted) {
            window.location.reload();
        }
    });
</script>

<?php include 'footer.php'; ?>
