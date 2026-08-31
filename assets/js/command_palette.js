/*
 * Copyright (c) IT Support BD. All rights reserved.
 * AMPNM Global Command Palette & Quick Action Speed-Dial
 * Shortcuts: Ctrl + K, Cmd + K, or clicking the search bar
 */

window.AMPNM = window.AMPNM || {};

AMPNM.CommandPalette = {
    isOpen: false,
    devicesCache: [],
    staticPages: [
        { title: 'Dashboard', url: 'index.php', icon: 'fas fa-tachometer-alt', category: 'Navigation' },
        { title: 'Topology Map', url: 'map.php', icon: 'fas fa-project-diagram', category: 'Network' },
        { title: 'Network Auto-Discovery', url: 'network_scanner.php', icon: 'fas fa-radar text-cyan-400', category: 'Network' },
        { title: 'IPAM Subnets & Pools', url: 'ipam.php', icon: 'fas fa-network-wired text-emerald-400', category: 'Network' },
        { title: 'Rack Elevation & Units', url: 'rack_elevation.php', icon: 'fas fa-server text-amber-400', category: 'Network' },
        { title: 'Device Config Vault', url: 'device_config_backups.php', icon: 'fas fa-file-code text-indigo-400', category: 'Network' },
        { title: 'Config Compliance Audit', url: 'compliance_audit.php', icon: 'fas fa-shield-check text-emerald-400', category: 'Network' },
        { title: 'Floor Plan Visualizer', url: 'floor_plan.php', icon: 'fas fa-building', category: 'Network' },
        { title: 'Network Graphs & Traffic', url: 'network_graphs.php', icon: 'fas fa-chart-line', category: 'Network' },
        { title: 'Host Resource Metrics', url: 'host_metrics.php', icon: 'fas fa-microchip', category: 'Monitoring' },
        { title: 'VoIP & IP SLA QoS (MOS)', url: 'voip_qos_monitor.php', icon: 'fas fa-headset text-indigo-400', category: 'Monitoring' },
        { title: 'SSL Certificate Expiry Tracker', url: 'ssl_monitors.php', icon: 'fas fa-lock text-cyan-400', category: 'Monitoring' },
        { title: 'Windows Monitoring Agents', url: 'agent_devices.php', icon: 'fas fa-desktop', category: 'Monitoring' },
        { title: 'Windows Agent Onboarding', url: 'windows_agent.php', icon: 'fas fa-person-chalkboard', category: 'Monitoring' },
        { title: 'Download Agent Binaries', url: 'download-agent.php', icon: 'fas fa-download', category: 'Monitoring' },
        { title: 'Alert Escalation & Webhooks', url: 'alert_escalation.php', icon: 'fas fa-layer-group text-cyan-400', category: 'Monitoring' },
        { title: 'AIOps Root Cause Analysis (RCA)', url: 'root_cause_analysis.php', icon: 'fas fa-brain text-pink-400', category: 'Monitoring' },
        { title: 'Predictive Capacity & Anomaly AI', url: 'predictive_ai.php', icon: 'fas fa-chart-area text-cyan-400', category: 'Monitoring' },
        { title: 'Auto-Remediation Engine', url: 'auto_remediation.php', icon: 'fas fa-magic text-amber-400', category: 'Monitoring' },
        { title: 'Synthetic Monitors', url: 'synthetic_monitors.php', icon: 'fas fa-satellite-dish text-indigo-400', category: 'Monitoring' },
        { title: 'Maintenance Windows', url: 'maintenance_windows.php', icon: 'fas fa-tools text-amber-400', category: 'Monitoring' },
        { title: 'Public Status Page & Incidents', url: 'status_page_settings.php', icon: 'fas fa-bullhorn text-emerald-400', category: 'Monitoring' },
        { title: 'Device Management', url: 'devices.php', icon: 'fas fa-server', category: 'Administration' },
        { title: 'Zero-Trust Security & Vault', url: 'security_audit.php', icon: 'fas fa-shield-halved text-cyan-400', category: 'Administration' },
        { title: 'SLA Performance Reports', url: 'sla_reports.php', icon: 'fas fa-file-contract text-cyan-400', category: 'Administration' },
        { title: 'System Backup & Restore', url: 'system_backup.php', icon: 'fas fa-database', category: 'Administration' },
        { title: 'Notification Settings', url: 'alert_settings.php', icon: 'fas fa-bell', category: 'Administration' },
        { title: 'Update Center (Git & Docker)', url: 'update_status.php', icon: 'fas fa-cloud-download-alt text-cyan-400', category: 'Administration' },
        { title: 'User Access Control', url: 'users.php', icon: 'fas fa-users-cog', category: 'Administration' },
        { title: 'License Management', url: 'license_management.php', icon: 'fas fa-id-card', category: 'Administration' },
        { title: 'Documentation & Guide', url: 'documentation.php', icon: 'fas fa-book', category: 'Help' }
    ],

    init: function() {
        this.createDom();
        this.bindEvents();
        this.fetchDevices();
    },

    createDom: function() {
        // 1. Search Trigger Button in Navbar (if element exists)
        const navContainer = document.querySelector('nav .container .flex.items-center');
        if (navContainer && !document.getElementById('navQuickSearchBtn')) {
            const btn = document.createElement('button');
            btn.id = 'navQuickSearchBtn';
            btn.type = 'button';
            btn.className = 'hidden sm:flex items-center gap-2 px-3 py-1.5 ml-4 bg-slate-900/60 hover:bg-slate-900 border border-slate-700/80 hover:border-cyan-500/50 text-slate-400 hover:text-slate-200 rounded-xl text-xs transition-all shadow-inner group';
            btn.innerHTML = `
                <i class="fas fa-search text-slate-500 group-hover:text-cyan-400 transition-colors"></i>
                <span class="text-xs font-medium">Quick Search / Action</span>
                <kbd class="ml-2 px-1.5 py-0.5 bg-slate-800 text-slate-400 border border-slate-700 rounded text-[10px] font-mono group-hover:border-cyan-500/40">Ctrl K</kbd>
            `;
            btn.onclick = () => AMPNM.CommandPalette.open();
            navContainer.appendChild(btn);
        }

        // 2. Floating Action Dock (Bottom-Right)
        if (!document.getElementById('ampnmFloatingDock')) {
            const dock = document.createElement('div');
            dock.id = 'ampnmFloatingDock';
            dock.className = 'fixed bottom-6 right-6 z-40 flex flex-col items-end gap-2.5 select-none print:hidden';
            dock.innerHTML = `
                <!-- Unfolded Menu Items -->
                <div id="dockMenu" class="hidden flex-col items-end gap-2 transition-all duration-300 transform scale-95 opacity-0">
                    <button onclick="window.location.href='devices.php?action=create'" class="flex items-center gap-2 px-3 py-2 bg-slate-900/90 hover:bg-cyan-600 border border-slate-700 hover:border-cyan-400 text-white rounded-xl text-xs shadow-2xl backdrop-blur-md transition-all group">
                        <span class="font-medium">Quick Add Device</span>
                        <div class="w-7 h-7 rounded-lg bg-cyan-500/20 text-cyan-400 flex items-center justify-center group-hover:bg-white group-hover:text-cyan-600"><i class="fas fa-plus"></i></div>
                    </button>
                    <button onclick="window.location.href='network_scanner.php'" class="flex items-center gap-2 px-3 py-2 bg-slate-900/90 hover:bg-emerald-600 border border-slate-700 hover:border-emerald-400 text-white rounded-xl text-xs shadow-2xl backdrop-blur-md transition-all group">
                        <span class="font-medium">Scan Network Subnet</span>
                        <div class="w-7 h-7 rounded-lg bg-emerald-500/20 text-emerald-400 flex items-center justify-center group-hover:bg-white group-hover:text-emerald-600"><i class="fas fa-radar"></i></div>
                    </button>
                    <button onclick="window.location.href='map.php'" class="flex items-center gap-2 px-3 py-2 bg-slate-900/90 hover:bg-indigo-600 border border-slate-700 hover:border-indigo-400 text-white rounded-xl text-xs shadow-2xl backdrop-blur-md transition-all group">
                        <span class="font-medium">Live Topology Map</span>
                        <div class="w-7 h-7 rounded-lg bg-indigo-500/20 text-indigo-400 flex items-center justify-center group-hover:bg-white group-hover:text-indigo-600"><i class="fas fa-project-diagram"></i></div>
                    </button>
                    <button onclick="AMPNM.CommandPalette.open()" class="flex items-center gap-2 px-3 py-2 bg-slate-900/90 hover:bg-amber-600 border border-slate-700 hover:border-amber-400 text-white rounded-xl text-xs shadow-2xl backdrop-blur-md transition-all group">
                        <span class="font-medium">Command Palette</span>
                        <div class="w-7 h-7 rounded-lg bg-amber-500/20 text-amber-400 flex items-center justify-center group-hover:bg-white group-hover:text-amber-600"><i class="fas fa-terminal"></i></div>
                    </button>
                </div>
                <!-- Main Dock Toggle Trigger -->
                <button id="dockTriggerBtn" type="button" class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-cyan-600 to-blue-600 hover:from-cyan-500 hover:to-blue-500 text-white shadow-xl shadow-cyan-600/30 flex items-center justify-center text-lg hover:scale-105 active:scale-95 transition-all duration-300 group">
                    <i id="dockTriggerIcon" class="fas fa-bolt group-hover:rotate-12 transition-transform"></i>
                </button>
            `;
            document.body.appendChild(dock);

            const triggerBtn = document.getElementById('dockTriggerBtn');
            triggerBtn.onclick = (e) => {
                e.stopPropagation();
                AMPNM.CommandPalette.toggleDock();
            };
            document.addEventListener('click', (e) => {
                if (!dock.contains(e.target)) {
                    AMPNM.CommandPalette.closeDock();
                }
            });
        }

        // 3. Command Palette Modal
        if (!document.getElementById('cmdPaletteModal')) {
            const modal = document.createElement('div');
            modal.id = 'cmdPaletteModal';
            modal.className = 'fixed inset-0 bg-black/75 backdrop-blur-md z-50 flex items-start justify-center p-4 sm:p-6 md:pt-20 hidden transition-all duration-200';
            modal.innerHTML = `
                <div class="bg-slate-900/95 border border-slate-700/80 rounded-2xl max-w-2xl w-full shadow-2xl overflow-hidden flex flex-col max-h-[80vh] animate-in fade-in zoom-in-95 duration-150">
                    <!-- Search Input Box -->
                    <div class="p-4 border-b border-slate-800 flex items-center gap-3 bg-slate-950/60">
                        <i class="fas fa-search text-cyan-400 text-base"></i>
                        <input id="cmdPaletteInput" type="text" placeholder="Search devices, IPs, maps, tools, or type action..." class="w-full bg-transparent border-0 text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-0">
                        <kbd class="px-2 py-0.5 bg-slate-800 text-slate-400 border border-slate-700 rounded text-3xs font-mono">ESC</kbd>
                    </div>
                    
                    <!-- Search Results Stream -->
                    <div id="cmdPaletteResults" class="p-3 overflow-y-auto space-y-1 divide-y divide-slate-800/40 text-xs">
                        <!-- Items rendered dynamically -->
                    </div>

                    <!-- Footer Shortcuts Bar -->
                    <div class="px-4 py-2.5 bg-slate-950/80 border-t border-slate-800 flex items-center justify-between text-3xs text-slate-400">
                        <div class="flex items-center gap-3">
                            <span><kbd class="px-1 py-0.5 bg-slate-800 border border-slate-700 rounded font-mono">↑</kbd> <kbd class="px-1 py-0.5 bg-slate-800 border border-slate-700 rounded font-mono">↓</kbd> Navigate</span>
                            <span><kbd class="px-1 py-0.5 bg-slate-800 border border-slate-700 rounded font-mono">↵</kbd> Select</span>
                        </div>
                        <span class="text-cyan-400 font-medium">AMPNM Command Intelligence</span>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);

            modal.onclick = (e) => {
                if (e.target === modal) AMPNM.CommandPalette.close();
            };

            const input = document.getElementById('cmdPaletteInput');
            input.oninput = () => AMPNM.CommandPalette.renderResults(input.value);
            input.onkeydown = (e) => AMPNM.CommandPalette.handleKeyDown(e);
        }
    },

    toggleDock: function() {
        const menu = document.getElementById('dockMenu');
        const icon = document.getElementById('dockTriggerIcon');
        if (!menu || !icon) return;

        const isHidden = menu.classList.contains('hidden');
        if (isHidden) {
            menu.classList.remove('hidden');
            setTimeout(() => {
                menu.classList.remove('scale-95', 'opacity-0');
                menu.classList.add('scale-100', 'opacity-100');
            }, 10);
            icon.className = 'fas fa-times';
        } else {
            this.closeDock();
        }
    },

    closeDock: function() {
        const menu = document.getElementById('dockMenu');
        const icon = document.getElementById('dockTriggerIcon');
        if (!menu || !icon) return;
        menu.classList.remove('scale-100', 'opacity-100');
        menu.classList.add('scale-95', 'opacity-0');
        setTimeout(() => menu.classList.add('hidden'), 200);
        icon.className = 'fas fa-bolt';
    },

    bindEvents: function() {
        document.addEventListener('keydown', (e) => {
            // Check for Ctrl+K or Cmd+K
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                this.toggle();
            } else if (e.key === 'Escape' && this.isOpen) {
                this.close();
            }
        });
    },

    toggle: function() {
        if (this.isOpen) this.close();
        else this.open();
    },

    open: function() {
        const modal = document.getElementById('cmdPaletteModal');
        const input = document.getElementById('cmdPaletteInput');
        if (!modal || !input) return;
        modal.classList.remove('hidden');
        this.isOpen = true;
        input.value = '';
        input.focus();
        this.renderResults('');
    },

    close: function() {
        const modal = document.getElementById('cmdPaletteModal');
        if (!modal) return;
        modal.classList.add('hidden');
        this.isOpen = false;
    },

    fetchDevices: async function() {
        try {
            const res = await fetch('api.php?action=get_devices');
            if (res.ok) {
                this.devicesCache = await res.json();
            }
        } catch (e) {
            this.devicesCache = [];
        }
    },

    renderResults: function(query) {
        const container = document.getElementById('cmdPaletteResults');
        if (!container) return;

        const q = query.toLowerCase().trim();
        let items = [];

        // Match Devices
        if (this.devicesCache && this.devicesCache.length > 0) {
            this.devicesCache.forEach(dev => {
                const nameMatch = dev.name && dev.name.toLowerCase().includes(q);
                const ipMatch = dev.ip && dev.ip.toLowerCase().includes(q);
                const typeMatch = dev.type && dev.type.toLowerCase().includes(q);
                if (q === '' || nameMatch || ipMatch || typeMatch) {
                    const statusColor = dev.status === 'online' ? 'text-emerald-400' : (dev.status === 'offline' ? 'text-rose-400' : 'text-amber-400');
                    items.push({
                        title: dev.name,
                        subtitle: `${dev.ip || 'No IP'} • Status: ${dev.status || 'unknown'} • Type: ${dev.type || 'device'}`,
                        url: dev.agent_device_id ? `agent_device_view.php?id=${dev.id}` : `devices.php?search=${encodeURIComponent(dev.ip || dev.name)}`,
                        icon: `fas fa-circle text-xs ${statusColor}`,
                        category: 'Managed Devices'
                    });
                }
            });
        }

        // Match Pages & Tools
        this.staticPages.forEach(p => {
            if (q === '' || p.title.toLowerCase().includes(q) || p.category.toLowerCase().includes(q)) {
                items.push({
                    title: p.title,
                    subtitle: `Page Navigation • ${p.category}`,
                    url: p.url,
                    icon: p.icon,
                    category: p.category
                });
            }
        });

        // Limit results to top 15
        const displayItems = items.slice(0, 15);

        if (displayItems.length === 0) {
            container.innerHTML = `
                <div class="p-6 text-center text-slate-400">
                    <i class="fas fa-ghost text-2xl text-slate-600 mb-2"></i>
                    <p class="text-xs">No matching devices, pages, or tools found for "<span class="text-white">${query}</span>"</p>
                </div>
            `;
            return;
        }

        container.innerHTML = displayItems.map((item, idx) => `
            <div onclick="window.location.href='${item.url}'" data-index="${idx}" class="cmd-item p-2.5 rounded-xl hover:bg-cyan-600/20 hover:border-cyan-500/40 border border-transparent flex items-center justify-between cursor-pointer transition-all group ${idx === 0 ? 'bg-slate-800/80 border-slate-700/60' : ''}">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-slate-800 flex items-center justify-center text-slate-300 group-hover:text-cyan-400 group-hover:bg-slate-700/80 transition-colors">
                        <i class="${item.icon}"></i>
                    </div>
                    <div>
                        <h4 class="text-white font-semibold text-xs group-hover:text-cyan-300 transition-colors">${item.title}</h4>
                        <p class="text-slate-400 text-3xs">${item.subtitle}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-3xs text-slate-500 uppercase tracking-wider font-mono">${item.category}</span>
                    <i class="fas fa-chevron-right text-slate-600 text-3xs group-hover:text-cyan-400 group-hover:translate-x-0.5 transition-all"></i>
                </div>
            </div>
        `).join('');
    },

    handleKeyDown: function(e) {
        const items = Array.from(document.querySelectorAll('.cmd-item'));
        if (items.length === 0) return;

        let activeIdx = items.findIndex(el => el.classList.contains('bg-slate-800/80'));

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (activeIdx < items.length - 1) {
                items.forEach(el => el.classList.remove('bg-slate-800/80', 'border-slate-700/60'));
                items[activeIdx + 1].classList.add('bg-slate-800/80', 'border-slate-700/60');
                items[activeIdx + 1].scrollIntoView({ block: 'nearest' });
            }
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (activeIdx > 0) {
                items.forEach(el => el.classList.remove('bg-slate-800/80', 'border-slate-700/60'));
                items[activeIdx - 1].classList.add('bg-slate-800/80', 'border-slate-700/60');
                items[activeIdx - 1].scrollIntoView({ block: 'nearest' });
            }
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (activeIdx >= 0 && items[activeIdx]) {
                items[activeIdx].click();
            }
        }
    }
};

document.addEventListener('DOMContentLoaded', () => {
    AMPNM.CommandPalette.init();
});
