/**
 * Dashboard Widgets - Additional dashboard functionality
 */

// Load top hosts by resource usage
async function loadTopHostsWidget() {
    const container = document.getElementById('topHostsWidget');
    if (!container) return;
    
    try {
        const response = await fetch('api.php?action=get_all_hosts');
        const hosts = await response.json();
        
        if (!hosts || hosts.length === 0) {
            container.innerHTML = `
                <div class="text-center py-4 text-slate-500">
                    <i class="fas fa-desktop text-2xl mb-2"></i>
                    <p class="text-sm">No monitored hosts</p>
                    <a href="host_metrics.php" class="text-cyan-400 text-xs hover:underline">Set up Windows agents</a>
                </div>
            `;
            return;
        }
        
        // Sort by CPU usage (highest first)
        const sortedByCpu = [...hosts].sort((a, b) => (b.cpu_percent || 0) - (a.cpu_percent || 0));
        const top5 = sortedByCpu.slice(0, 5);
        
        let html = '<div class="space-y-3">';
        
        top5.forEach(host => {
            const isRecent = host.created_at && (Date.now() - new Date(host.created_at).getTime()) < 300000;
            const statusClass = isRecent ? 'bg-green-500' : 'bg-red-500';
            
            const cpuPercent = host.cpu_percent !== null ? host.cpu_percent : 0;
            const memPercent = host.memory_percent !== null ? host.memory_percent : 0;
            
            // Determine alert level
            let alertClass = '';
            if (cpuPercent > 90 || memPercent > 90) {
                alertClass = 'border-red-500/50 bg-red-500/5';
            } else if (cpuPercent > 75 || memPercent > 75) {
                alertClass = 'border-yellow-500/50 bg-yellow-500/5';
            }
            
            html += `
                <div class="flex items-center gap-3 p-3 rounded-lg border border-slate-700 hover:border-slate-600 transition-colors ${alertClass}">
                    <div class="flex-shrink-0">
                        <span class="w-2 h-2 rounded-full ${statusClass} inline-block"></span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <p class="text-sm font-medium text-white truncate">${host.host_name || host.host_ip}</p>
                            ${host.device_name ? `<span class="text-xs text-cyan-400"><i class="fas fa-link"></i></span>` : ''}
                        </div>
                        <p class="text-xs text-slate-500">${host.host_ip}</p>
                    </div>
                    <div class="flex-shrink-0 text-right">
                        <div class="flex items-center gap-3">
                            <div class="text-center">
                                <p class="text-xs text-slate-500">CPU</p>
                                <p class="text-sm font-medium ${cpuPercent > 80 ? 'text-red-400' : cpuPercent > 60 ? 'text-yellow-400' : 'text-cyan-400'}">
                                    ${cpuPercent !== null ? cpuPercent + '%' : '--'}
                                </p>
                            </div>
                            <div class="text-center">
                                <p class="text-xs text-slate-500">MEM</p>
                                <p class="text-sm font-medium ${memPercent > 80 ? 'text-red-400' : memPercent > 60 ? 'text-yellow-400' : 'text-purple-400'}">
                                    ${memPercent !== null ? memPercent + '%' : '--'}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        container.innerHTML = html;
        
    } catch (error) {
        console.error('Failed to load top hosts:', error);
        container.innerHTML = `
            <div class="text-center py-4 text-slate-500">
                <i class="fas fa-exclamation-triangle text-red-400 mb-2"></i>
                <p class="text-sm">Failed to load hosts</p>
            </div>
        `;
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    // Only load if we're on the dashboard
    if (document.getElementById('topHostsWidget')) {
        loadTopHostsWidget();
        
        // Refresh every 30 seconds
        setInterval(loadTopHostsWidget, 30000);
    }
});
