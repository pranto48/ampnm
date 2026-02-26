<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Ensure user_role is set, default to 'viewer' if not (e.g., for new sessions after upgrade)
$user_role = $_SESSION['user_role'] ?? 'viewer';
$current_page = basename($_SERVER['PHP_SELF']); // Get current page filename
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AMPNM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    <script src="https://unpkg.com/vis-network/standalone/umd/vis-network.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-slate-900 text-slate-300 min-h-screen">
    <nav class="bg-slate-800/50 backdrop-blur-lg shadow-lg sticky top-0 z-50">
        <div class="container mx-auto px-4">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center">
                    <!-- Mobile menu button -->
                    <button id="mobile-menu-button" class="md:hidden p-2 text-slate-300 hover:text-white focus:outline-none focus:ring-2 focus:ring-inset focus:ring-white">
                        <i class="fas fa-bars h-6 w-6"></i>
                    </button>
                    <a href="index.php" class="flex items-center gap-2 text-white font-bold ml-3 md:ml-0">
                        <i class="fas fa-shield-halved text-cyan-400 text-2xl"></i>
                        <span>AMPNM</span>
                    </a>
                </div>
                
                <!-- Mobile Sidebar / Desktop Navigation -->
                <div id="main-nav-wrapper" class="fixed inset-y-0 left-0 w-64 bg-slate-800/95 backdrop-blur-lg z-50 transform -translate-x-full transition-transform duration-300 ease-in-out md:relative md:w-auto md:bg-transparent md:backdrop-blur-none md:transform-none md:transition-none md:flex md:items-center">
                    <!-- Close button for mobile sidebar -->
                    <div class="flex items-center justify-between p-4 border-b border-slate-700 md:hidden">
                        <a href="index.php" class="flex items-center gap-2 text-white font-bold">
                            <i class="fas fa-shield-halved text-cyan-400 text-2xl"></i>
                            <span>AMPNM</span>
                        </a>
                        <button id="close-mobile-menu-button" class="p-2 text-slate-300 hover:text-white focus:outline-none">
                            <i class="fas fa-times h-6 w-6"></i>
                        </button>
                    </div>
                    <div id="main-nav" class="flex flex-col p-4 space-y-1 md:flex-row md:p-0 md:space-y-0 md:space-x-1 md:ml-10">
                        <a href="index.php" class="nav-link"><i class="fas fa-tachometer-alt fa-fw mr-2"></i>Dashboard</a>

                        <div class="nav-group">
                            <button type="button" class="nav-link nav-group-toggle">
                                <span class="flex items-center"><i class="fas fa-network-wired fa-fw mr-2"></i>Network</span>
                                <i class="fas fa-chevron-down nav-group-caret"></i>
                            </button>
                            <div class="nav-group-items">
                                <a href="map.php" class="nav-link nav-sublink"><i class="fas fa-project-diagram fa-fw mr-2"></i>Map</a>
                                <a href="network_graphs.php" class="nav-link nav-sublink"><i class="fas fa-chart-line fa-fw mr-2"></i>Network Graphs</a>
                            </div>
                        </div>

                        <div class="nav-group">
                            <button type="button" class="nav-link nav-group-toggle">
                                <span class="flex items-center"><i class="fas fa-heartbeat fa-fw mr-2"></i>Monitoring</span>
                                <i class="fas fa-chevron-down nav-group-caret"></i>
                            </button>
                            <div class="nav-group-items">
                                <a href="host_metrics.php" class="nav-link nav-sublink"><i class="fas fa-microchip fa-fw mr-2"></i>Host Metrics</a>
                                <a href="windows_agent.php" class="nav-link nav-sublink"><i class="fas fa-person-chalkboard fa-fw mr-2"></i>Windows Agent Onboarding</a>
                                <a href="download-agent.php" class="nav-link nav-sublink"><i class="fas fa-download fa-fw mr-2"></i>Download Windows Agent</a>
                                <a href="documentation.php#windows-agent" class="nav-link nav-sublink"><i class="fas fa-book-open fa-fw mr-2"></i>Windows Agent Guide</a>
                                <a href="api/agent/windows-metrics/health" class="nav-link nav-sublink" target="_blank" rel="noreferrer"><i class="fas fa-plug-circle-check fa-fw mr-2"></i>Agent API Health</a>
                            </div>
                        </div>

                        <?php if ($user_role === 'admin'): ?>
                            <div class="nav-group">
                                <button type="button" class="nav-link nav-group-toggle">
                                    <span class="flex items-center"><i class="fas fa-cogs fa-fw mr-2"></i>Administration</span>
                                    <i class="fas fa-chevron-down nav-group-caret"></i>
                                </button>
                                <div class="nav-group-items">
                                    <a href="devices.php" class="nav-link nav-sublink"><i class="fas fa-server fa-fw mr-2"></i>Devices</a>
                                    <a href="history.php" class="nav-link nav-sublink"><i class="fas fa-history fa-fw mr-2"></i>History</a>
                                    <a href="status_logs.php" class="nav-link nav-sublink"><i class="fas fa-clipboard-list fa-fw mr-2"></i>Status Logs</a>
                                    <a href="email_notifications.php" class="nav-link nav-sublink"><i class="fas fa-envelope fa-fw mr-2"></i>Email Notifications</a>
                                    <a href="code_updates.php" class="nav-link nav-sublink"><i class="fas fa-cloud-download-alt fa-fw mr-2"></i>Code Updates</a>
                                    <a href="users.php" class="nav-link nav-sublink"><i class="fas fa-users-cog fa-fw mr-2"></i>Users</a>
                                    <a href="license_management.php" class="nav-link nav-sublink"><i class="fas fa-id-card fa-fw mr-2"></i>License</a>
                                </div>
                            </div>
                        <?php endif; ?>

                        <a href="documentation.php" class="nav-link"><i class="fas fa-book fa-fw mr-2"></i>Help</a>
                        <a href="logout.php" class="nav-link"><i class="fas fa-sign-out-alt fa-fw mr-2"></i>Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </nav>
    <div class="page-content">
    <?php 
    // Only show license status if it's not 'unconfigured' OR if we are on the license_management.php page
    // Additionally, for 'active' and 'free' licenses, only show on license_management.php
    if (isset($_SESSION['license_status_code']) && 
        ($_SESSION['license_status_code'] !== 'unconfigured' || $current_page === 'license_management.php') &&
        (($_SESSION['license_status_code'] !== 'active' && $_SESSION['license_status_code'] !== 'free') || $current_page === 'license_management.php')
    ): 
        $license_status_code = $_SESSION['license_status_code'];
        $license_message = $_SESSION['license_message'];
        $max_devices = $_SESSION['license_max_devices'];
        $current_devices = $_SESSION['current_device_count'];
        $expires_at = $_SESSION['license_expires_at'];
        $grace_period_end = $_SESSION['license_grace_period_end'];

        $status_class = '';
        $status_icon = '';
        $display_message = '';
        $show_manage_link = true;

        switch ($license_status_code) {
            case 'active':
            case 'free':
                $status_class = 'bg-green-500/20 text-green-400 border-green-500/30';
                $status_icon = '<i class="fas fa-check-circle mr-1"></i>';
                $display_message = "License Active ({$current_devices}/{$max_devices} devices)";
                if ($expires_at) {
                    $display_message .= " - Expires: " . date('Y-m-d', strtotime($expires_at));
                }
                break;
            case 'grace_period':
                $status_class = 'bg-yellow-500/20 text-yellow-400 border-yellow-500/30';
                $status_icon = '<i class="fas fa-exclamation-triangle mr-1"></i>';
                $display_message = "License Expired! Grace period until " . date('Y-m-d', $grace_period_end) . ".";
                break;
            case 'expired': // Should be caught by grace_period or disabled
            case 'revoked':
            case 'in_use':
            case 'disabled':
                $status_class = 'bg-red-500/20 text-red-400 border-red-500/30';
                $status_icon = '<i class="fas fa-ban mr-1"></i>';
                $display_message = "License Disabled! ({$license_message})";
                break;
            case 'unconfigured':
                $status_class = 'bg-yellow-500/20 text-yellow-400 border-yellow-500/30';
                $status_icon = '<i class="fas fa-exclamation-circle mr-1"></i>';
                $display_message = "License Unconfigured! Please set up your license key.";
                break;
            case 'portal_unreachable':
                $status_class = 'bg-orange-500/20 text-orange-400 border-orange-500/30';
                $status_icon = '<i class="fas fa-cloud-offline mr-1"></i>';
                $display_message = "License Portal Unreachable! ({$license_message})";
                break;
            case 'invalid':
            case 'not_found':
            case 'error':
            default:
                $status_class = 'bg-red-500/20 text-red-400 border-red-500/30';
                $status_icon = '<i class="fas fa-times-circle mr-1"></i>';
                $display_message = "License Invalid! ({$license_message})";
                break;
        }
    ?>
        <div class="container mx-auto px-4 mt-4">
            <div class="p-3 rounded-lg text-sm flex items-center justify-between <?= $status_class ?>">
                <div><?= $status_icon ?> <?= htmlspecialchars($display_message) ?></div>
                <?php if ($show_manage_link && $user_role === 'admin'): ?>
                    <a href="license_management.php" class="text-cyan-400 hover:underline ml-4">Manage License</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>