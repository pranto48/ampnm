<?php
require_once __DIR__ . '/includes/bootstrap.php';

$page_title = "Documentation - AMPNM User Manual";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .doc-section {
            scroll-margin-top: 80px;
        }
        .doc-nav a:hover {
            background-color: rgba(6, 182, 212, 0.1);
        }
        .doc-nav a.active {
            background-color: rgba(6, 182, 212, 0.2);
            border-left: 3px solid #06b6d4;
        }
        .code-block {
            background: #1e293b;
            border-radius: 8px;
            padding: 1rem;
            overflow-x: auto;
        }
        .code-block code {
            color: #e2e8f0;
            font-family: 'Courier New', monospace;
        }
    </style>
</head>
<body class="bg-slate-900 text-slate-100">
    
    <!-- Header -->
    <header class="bg-slate-800 shadow-lg sticky top-0 z-50">
        <div class="container mx-auto px-4 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <i class="fas fa-book text-cyan-400 text-2xl"></i>
                    <h1 class="text-xl font-bold">AMPNM Documentation</h1>
                </div>
                <a href="index.php" class="px-4 py-2 bg-cyan-600 hover:bg-cyan-700 rounded-lg transition">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </header>

    <div class="container mx-auto px-4 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            
            <!-- Sidebar Navigation -->
            <aside class="lg:col-span-1">
                <div class="bg-slate-800 rounded-lg p-4 sticky top-24 doc-nav">
                    <h2 class="font-bold text-lg mb-4 text-cyan-400">Contents</h2>
                    <nav class="space-y-2">
                        <a href="#introduction" class="block px-3 py-2 rounded transition">
                            <i class="fas fa-info-circle mr-2"></i>Introduction
                        </a>
                        <a href="#getting-started" class="block px-3 py-2 rounded transition">
                            <i class="fas fa-play-circle mr-2"></i>Getting Started
                        </a>
                        <a href="#dashboard" class="block px-3 py-2 rounded transition">
                            <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
                        </a>
                        <a href="#devices" class="block px-3 py-2 rounded transition">
                            <i class="fas fa-server mr-2"></i>Managing Devices
                        </a>
                        <a href="#network-map" class="block px-3 py-2 rounded transition">
                            <i class="fas fa-map-marked-alt mr-2"></i>Network Map
                        </a>
                        <a href="#monitoring" class="block px-3 py-2 rounded transition">
                            <i class="fas fa-heartbeat mr-2"></i>Monitoring
                        </a>
                        <a href="#windows-agent" class="block px-3 py-2 rounded transition">
                            <i class="fas fa-microchip mr-2"></i>Windows Agent
                        </a>
                        <a href="#notifications" class="block px-3 py-2 rounded transition">
                            <i class="fas fa-bell mr-2"></i>Notifications
                        </a>
                        <a href="#mikrotik" class="block px-3 py-2 rounded transition">
                            <i class="fas fa-network-wired mr-2"></i>MikroTik Traffic Monitor
                        </a>
                        <a href="#license" class="block px-3 py-2 rounded transition">
                            <i class="fas fa-key mr-2"></i>License Management
                        </a>
                        <a href="#troubleshooting" class="block px-3 py-2 rounded transition">
                            <i class="fas fa-tools mr-2"></i>Troubleshooting
                        </a>
                        <a href="#faq" class="block px-3 py-2 rounded transition">
                            <i class="fas fa-question-circle mr-2"></i>FAQ
                        </a>
                    </nav>
                </div>
            </aside>

            <!-- Main Content -->
            <main class="lg:col-span-3">
                <div class="bg-slate-800 rounded-lg p-8 space-y-12">

                    <!-- Introduction -->
                    <section id="introduction" class="doc-section">
                        <h2 class="text-3xl font-bold text-cyan-400 mb-4">
                            <i class="fas fa-info-circle mr-2"></i>Introduction
                        </h2>
                        <p class="text-lg mb-4">
                            Welcome to <strong>AMPNM</strong> (Advanced Multi-Protocol Network Monitor) - a powerful, real-time network monitoring solution designed to keep your infrastructure healthy and operational.
                        </p>
                        <div class="bg-slate-700 rounded-lg p-6">
                            <h3 class="font-bold text-xl mb-3">Key Features:</h3>
                            <ul class="space-y-2">
                                <li><i class="fas fa-check text-green-400 mr-2"></i>Real-time device monitoring (ICMP ping, TCP ports, HTTP/HTTPS)</li>
                                <li><i class="fas fa-check text-green-400 mr-2"></i>Visual network topology mapping</li>
                                <li><i class="fas fa-check text-green-400 mr-2"></i>Customizable alert thresholds (latency, packet loss)</li>
                                <li><i class="fas fa-check text-green-400 mr-2"></i>Email notifications on status changes</li>
                                <li><i class="fas fa-check text-green-400 mr-2"></i>Historical data and performance tracking</li>
                                <li><i class="fas fa-check text-green-400 mr-2"></i>Multi-user support with role-based access</li>
                                <li><i class="fas fa-check text-green-400 mr-2"></i>Offline mode support (up to 30 days)</li>
                            </ul>
                        </div>
                    </section>

                    <!-- Getting Started -->
                    <section id="getting-started" class="doc-section">
                        <h2 class="text-3xl font-bold text-cyan-400 mb-4">
                            <i class="fas fa-play-circle mr-2"></i>Getting Started
                        </h2>
                        
                        <h3 class="text-2xl font-semibold mb-3">First Time Setup</h3>
                        <ol class="list-decimal list-inside space-y-3 mb-6">
                            <li><strong>Access the Application</strong>
                                <div class="code-block mt-2">
                                    <code>http://localhost:2266</code>
                                </div>
                            </li>
                            <li><strong>Login with Default Credentials</strong>
                                <ul class="ml-8 mt-2">
                                    <li>Username: <code class="bg-slate-700 px-2 py-1 rounded">admin</code></li>
                                    <li>Password: <code class="bg-slate-700 px-2 py-1 rounded">password</code></li>
                                </ul>
                                <div class="bg-yellow-900/30 border-l-4 border-yellow-500 p-4 mt-2">
                                    <p class="text-yellow-300"><i class="fas fa-exclamation-triangle mr-2"></i><strong>Security:</strong> Change the default password immediately after first login!</p>
                                </div>
                            </li>
                            <li><strong>Configure License</strong>
                                <p class="ml-8 mt-2">Enter your license key from <a href="https://portal.itsupport.com.bd" class="text-cyan-400 hover:underline">portal.itsupport.com.bd</a></p>
                            </li>
                        </ol>

                        <h3 class="text-2xl font-semibold mb-3">User Interface Overview</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="bg-slate-700 p-4 rounded">
                                <h4 class="font-bold mb-2"><i class="fas fa-tachometer-alt text-cyan-400 mr-2"></i>Dashboard</h4>
                                <p class="text-sm">Real-time overview of all monitored devices with status counters.</p>
                            </div>
                            <div class="bg-slate-700 p-4 rounded">
                                <h4 class="font-bold mb-2"><i class="fas fa-server text-cyan-400 mr-2"></i>Devices</h4>
                                <p class="text-sm">Manage your network devices, add/edit/delete entries.</p>
                            </div>
                            <div class="bg-slate-700 p-4 rounded">
                                <h4 class="font-bold mb-2"><i class="fas fa-map-marked-alt text-cyan-400 mr-2"></i>Map</h4>
                                <p class="text-sm">Visual topology view with drag-and-drop positioning.</p>
                            </div>
                            <div class="bg-slate-700 p-4 rounded">
                                <h4 class="font-bold mb-2"><i class="fas fa-history text-cyan-400 mr-2"></i>History</h4>
                                <p class="text-sm">Historical ping data and performance graphs.</p>
                            </div>
                        </div>
                    </section>

                    <!-- Dashboard -->
                    <section id="dashboard" class="doc-section">
                        <h2 class="text-3xl font-bold text-cyan-400 mb-4">
                            <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
                        </h2>
                        <p class="mb-4">The dashboard provides a real-time overview of your network infrastructure.</p>
                        
                        <h3 class="text-xl font-semibold mb-3">Status Indicators:</h3>
                        <div class="space-y-3">
                            <div class="flex items-center space-x-3 bg-slate-700 p-3 rounded">
                                <span class="w-4 h-4 bg-green-500 rounded-full"></span>
                                <strong>Online:</strong>
                                <span>Device responding normally, all thresholds met</span>
                            </div>
                            <div class="flex items-center space-x-3 bg-slate-700 p-3 rounded">
                                <span class="w-4 h-4 bg-yellow-500 rounded-full"></span>
                                <strong>Warning:</strong>
                                <span>Latency or packet loss exceeds warning threshold</span>
                            </div>
                            <div class="flex items-center space-x-3 bg-slate-700 p-3 rounded">
                                <span class="w-4 h-4 bg-red-500 rounded-full"></span>
                                <strong>Critical:</strong>
                                <span>Severe latency/packet loss, immediate attention needed</span>
                            </div>
                            <div class="flex items-center space-x-3 bg-slate-700 p-3 rounded">
                                <span class="w-4 h-4 bg-gray-500 rounded-full"></span>
                                <strong>Offline:</strong>
                                <span>Device not responding to pings</span>
                            </div>
                        </div>

                        <div class="bg-blue-900/30 border-l-4 border-blue-500 p-4 mt-6">
                            <p><i class="fas fa-info-circle mr-2 text-blue-400"></i><strong>Tip:</strong> Status updates automatically every 60 seconds (default). You can customize check intervals per device.</p>
                        </div>
                    </section>

                    <!-- Devices -->
                    <section id="devices" class="doc-section">
                        <h2 class="text-3xl font-bold text-cyan-400 mb-4">
                            <i class="fas fa-server mr-2"></i>Managing Devices
                        </h2>

                        <h3 class="text-2xl font-semibold mb-3">Adding a New Device</h3>
                        <ol class="list-decimal list-inside space-y-2 mb-6">
                            <li>Navigate to <strong>Devices</strong> → <strong>Add Device</strong></li>
                            <li>Fill in required information:
                                <ul class="ml-8 mt-2 space-y-1">
                                    <li><strong>Name:</strong> Friendly name (e.g., "Main Router")</li>
                                    <li><strong>IP Address:</strong> Device IP or hostname</li>
                                    <li><strong>Device Type:</strong> Router, Switch, Server, etc.</li>
                                    <li><strong>Check Interval:</strong> How often to ping (seconds)</li>
                                </ul>
                            </li>
                            <li>Configure optional thresholds (see below)</li>
                            <li>Click <strong>Save Device</strong></li>
                        </ol>

                        <h3 class="text-2xl font-semibold mb-3">Monitoring Methods</h3>
                        <div class="space-y-4">
                            <div class="bg-slate-700 p-4 rounded">
                                <h4 class="font-bold text-lg mb-2"><i class="fas fa-signal mr-2 text-green-400"></i>ICMP Ping (Default)</h4>
                                <p class="mb-2">Standard network reachability test using ping.</p>
                                <ul class="list-disc list-inside ml-4 text-sm">
                                    <li>Measures latency (milliseconds)</li>
                                    <li>Detects packet loss (%)</li>
                                    <li>Records TTL values</li>
                                    <li>Lowest overhead</li>
                                </ul>
                            </div>

                            <div class="bg-slate-700 p-4 rounded">
                                <h4 class="font-bold text-lg mb-2"><i class="fas fa-plug mr-2 text-blue-400"></i>TCP Port Check</h4>
                                <p class="mb-2">Test specific service availability by checking if port is open.</p>
                                <ul class="list-disc list-inside ml-4 text-sm">
                                    <li>Specify port number (e.g., 80, 443, 22)</li>
                                    <li>Tests actual service availability</li>
                                    <li>Measures connection time</li>
                                    <li>Example: Port 80 for web servers</li>
                                </ul>
                            </div>
                        </div>

                        <h3 class="text-2xl font-semibold mb-3 mt-6">Alert Thresholds</h3>
                        <div class="bg-slate-700 p-4 rounded">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-slate-600">
                                        <th class="text-left py-2">Metric</th>
                                        <th class="text-left py-2">Warning</th>
                                        <th class="text-left py-2">Critical</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="border-b border-slate-600">
                                        <td class="py-2">Latency</td>
                                        <td>> 100ms</td>
                                        <td>> 300ms</td>
                                    </tr>
                                    <tr>
                                        <td class="py-2">Packet Loss</td>
                                        <td>> 10%</td>
                                        <td>> 50%</td>
                                    </tr>
                                </tbody>
                            </table>
                            <p class="text-xs mt-3 text-slate-400">* Default values. Customize per device during setup.</p>
                        </div>
                    </section>

                    <!-- Network Map -->
                    <section id="network-map" class="doc-section">
                        <h2 class="text-3xl font-bold text-cyan-400 mb-4">
                            <i class="fas fa-map-marked-alt mr-2"></i>Network Map
                        </h2>
                        <p class="mb-4">Visualize your network topology with an interactive, drag-and-drop map.</p>

                        <h3 class="text-xl font-semibold mb-3">Features:</h3>
                        <ul class="space-y-2 mb-6">
                            <li><i class="fas fa-arrows-alt text-cyan-400 mr-2"></i><strong>Drag & Drop:</strong> Position devices anywhere on the canvas</li>
                            <li><i class="fas fa-palette text-cyan-400 mr-2"></i><strong>Color-Coded:</strong> Device colors match their current status</li>
                            <li><i class="fas fa-link text-cyan-400 mr-2"></i><strong>Connections:</strong> Draw lines between connected devices</li>
                            <li><i class="fas fa-eye text-cyan-400 mr-2"></i><strong>Public Map:</strong> Share read-only view with stakeholders</li>
                            <li><i class="fas fa-image text-cyan-400 mr-2"></i><strong>Custom Icons:</strong> Upload device-specific icons</li>
                        </ul>

                        <div class="bg-blue-900/30 border-l-4 border-blue-500 p-4">
                            <p><i class="fas fa-lightbulb mr-2 text-blue-400"></i><strong>Pro Tip:</strong> Use the public map URL to display network status on NOC dashboards or TV screens without requiring login.</p>
                        </div>
                    </section>

                    <!-- Monitoring -->
                    <section id="monitoring" class="doc-section">
                        <h2 class="text-3xl font-bold text-cyan-400 mb-4">
                            <i class="fas fa-heartbeat mr-2"></i>Monitoring Features
                        </h2>

                        <h3 class="text-2xl font-semibold mb-3">Automatic Monitoring</h3>
                        <p class="mb-4">Once a device is added, AMPNM automatically:</p>
                        <ol class="list-decimal list-inside space-y-2 mb-6">
                            <li>Pings device at specified interval</li>
                            <li>Records response time and packet loss</li>
                            <li>Compares results against thresholds</li>
                            <li>Updates device status (Online/Warning/Critical/Offline)</li>
                            <li>Logs status changes to history</li>
                            <li>Triggers notifications if configured</li>
                        </ol>

                        <h3 class="text-2xl font-semibold mb-3">Manual Testing</h3>
                        <div class="bg-slate-700 p-4 rounded">
                            <p class="mb-3">Test device connectivity on-demand:</p>
                            <ul class="space-y-2">
                                <li><i class="fas fa-network-wired text-cyan-400 mr-2"></i><strong>Single Ping:</strong> Test one device immediately</li>
                                <li><i class="fas fa-scanner text-cyan-400 mr-2"></i><strong>Network Scan:</strong> Discover devices on subnet (requires nmap)</li>
                                <li><i class="fas fa-chart-line text-cyan-400 mr-2"></i><strong>Ping History:</strong> View historical latency graph</li>
                            </ul>
                        </div>

                        <h3 class="text-2xl font-semibold mb-3 mt-6">Status Logs</h3>
                        <p>Every status change is recorded with:</p>
                        <ul class="list-disc list-inside ml-4 space-y-1">
                            <li>Timestamp</li>
                            <li>Device name and IP</li>
                            <li>Old status → New status</li>
                            <li>Details (latency, packet loss, error message)</li>
                        </ul>
                    </section>

                    <!-- Windows Agent -->
                    <section id="windows-agent" class="doc-section">
                        <h2 class="text-3xl font-bold text-cyan-400 mb-4">
                            <i class="fas fa-microchip mr-2"></i>Windows Usage Agent
                        </h2>

                        <p class="mb-4">Collect CPU, memory, disk, network, and GPU utilization from Windows Server/PCs and push it into AMPNM over HTTPS.</p>

                        <div class="bg-slate-700 p-4 rounded mb-4">
                            <h3 class="font-semibold text-lg mb-2"><i class="fas fa-key mr-2 text-cyan-400"></i>Secure the agent endpoint</h3>
                            <ol class="list-decimal list-inside space-y-2">
                                <li>Set an agent token on the Docker server: <code class="bg-slate-800 px-2 py-1 rounded">export WINDOWS_AGENT_TOKEN=&lt;strong-random-secret&gt;</code></li>
                                <li>Restart the AMPNM container/service so the API uses the new token.</li>
                                <li>Use the same token inside your Windows script when posting metrics.</li>
                            </ol>
                            <p class="text-sm text-slate-300 mt-2">Endpoints (all require the <code>X-Agent-Token</code> header):</p>
                            <ul class="list-disc list-inside ml-4 text-sm space-y-1">
                                <li><code>POST /api/agent/windows-metrics</code> — ingest a new snapshot</li>
                                <li><code>GET /api/agent/windows-metrics/recent?limit=50</code> — review the latest submissions</li>
                                <li><code>GET /api/agent/windows-metrics/&lt;HOSTNAME&gt;/latest</code> — fetch the newest entry for one host</li>
                            </ul>
                        </div>

                        <div class="grid md:grid-cols-2 gap-4">
                            <div class="bg-slate-700 p-4 rounded">
                                <h3 class="font-semibold text-lg mb-2"><i class="fas fa-terminal mr-2 text-cyan-400"></i>Drop-in .bat helper</h3>
                                <p class="text-sm mb-3">Copy the included batch file to your Windows host, edit the server URL and token, then run it or schedule it in Task Scheduler.</p>
                                <div class="code-block text-xs">
                                    <code>@echo off
set SERVER_URL=http://YOUR_DOCKER_HOST:3001/api/agent/windows-metrics
set AGENT_TOKEN=&lt;token&gt;

powershell -NoProfile -Command "# Collect CPU/memory/disk/network/GPU and POST JSON with X-Agent-Token header"</code>
                                </div>
                                <p class="text-xs text-slate-300 mt-2">File: <code>assets/windows-monitor-agent.bat</code></p>
                            </div>
                            <div class="bg-slate-700 p-4 rounded">
                                <h3 class="font-semibold text-lg mb-2"><i class="fas fa-tasks mr-2 text-cyan-400"></i>Scheduling tips</h3>
                                <ul class="list-disc list-inside space-y-2 text-sm">
                                    <li>Create a Task Scheduler job that runs the .bat every 5–15 minutes using a service account.</li>
                                    <li>Remove the final <code>pause</code> line in the .bat when running unattended.</li>
                                    <li>Use HTTPS if the AMPNM UI is exposed on TLS; include the full port in <code>SERVER_URL</code>.</li>
                                    <li>Verify ingestion with <code>curl -H "X-Agent-Token: &lt;token&gt;" http://HOST:3001/api/agent/windows-metrics/recent</code>.</li>
                                </ul>
                            </div>
                        </div>

                        <div class="bg-slate-700 p-4 rounded mt-4">
                            <h3 class="font-semibold text-lg mb-2"><i class="fas fa-project-diagram mr-2 text-cyan-400"></i>Show Windows status on the Docker map</h3>
                            <ol class="list-decimal list-inside space-y-2 text-sm">
                                <li>Add the Windows host in AMPNM (Topology tab) with the same <strong>Host Name</strong> or IP that the .bat script reports.</li>
                                <li>When the agent posts metrics, the map ring turns <span class="text-amber-300">amber</span> if the feed is stale and <span class="text-green-300">green</span> when fresh, and the CPU/RAM/Disk/Net values render under the node.</li>
                                <li>If you haven’t created the host yet, the newest agent report still appears as a virtual Windows node so you can spot it on the map.</li>
                                <li>Keep the <code>WINDOWS_AGENT_TOKEN</code> the same across your Windows hosts and Docker server; the UI automatically fetches the latest per-host metrics every time you open the map.</li>
                            </ol>
                        </div>
                    </section>

                    <!-- Notifications -->
                    <section id="notifications" class="doc-section">
                        <h2 class="text-3xl font-bold text-cyan-400 mb-4">
                            <i class="fas fa-bell mr-2"></i>Email Notifications
                        </h2>

                        <h3 class="text-2xl font-semibold mb-3">Setup SMTP</h3>
                        <ol class="list-decimal list-inside space-y-2 mb-6">
                            <li>Navigate to <strong>Email Notifications</strong></li>
                            <li>Configure SMTP settings:
                                <ul class="ml-8 mt-2 space-y-1">
                                    <li>Host: smtp.gmail.com (or your provider)</li>
                                    <li>Port: 587 (TLS) or 465 (SSL)</li>
                                    <li>Username: your-email@domain.com</li>
                                    <li>Password: your SMTP password/app password</li>
                                </ul>
                            </li>
                            <li>Test connection</li>
                            <li>Subscribe recipients to device notifications</li>
                        </ol>

                        <h3 class="text-2xl font-semibold mb-3">Notification Triggers</h3>
                        <p class="mb-3">Choose which events trigger emails:</p>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="bg-green-900/30 p-3 rounded border-l-4 border-green-500">
                                <i class="fas fa-check-circle text-green-400 mr-2"></i><strong>Device Online</strong>
                            </div>
                            <div class="bg-gray-900/30 p-3 rounded border-l-4 border-gray-500">
                                <i class="fas fa-times-circle text-gray-400 mr-2"></i><strong>Device Offline</strong>
                            </div>
                            <div class="bg-yellow-900/30 p-3 rounded border-l-4 border-yellow-500">
                                <i class="fas fa-exclamation-triangle text-yellow-400 mr-2"></i><strong>Warning Status</strong>
                            </div>
                            <div class="bg-red-900/30 p-3 rounded border-l-4 border-red-500">
                                <i class="fas fa-exclamation-circle text-red-400 mr-2"></i><strong>Critical Status</strong>
                            </div>
                        </div>
                    </section>

                    <!-- MikroTik Traffic Monitor -->
                    <section id="mikrotik" class="doc-section">
                        <h2 class="text-3xl font-bold text-cyan-400 mb-4">
                            <i class="fas fa-network-wired mr-2"></i>MikroTik Traffic Monitor
                        </h2>

                        <p class="mb-4">Use a dedicated, least-privileged RouterOS account to let the AMPNM Docker server read interface traffic via the RouterOS API.</p>

                        <div class="bg-slate-700 p-4 rounded mb-4">
                            <h3 class="text-2xl font-semibold mb-3">One-time setup (run on the router)</h3>
                            <p class="mb-3 text-sm text-slate-200">Update the <code class="bg-slate-800 px-2 py-1 rounded">ampnmServer</code>, <code class="bg-slate-800 px-2 py-1 rounded">ampnmUser</code>, and <code class="bg-slate-800 px-2 py-1 rounded">ampnmPass</code> values, then paste the block into a MikroTik terminal.</p>
                            <div class="code-block mb-3 text-sm">
<code>:local ampnmServer "192.0.2.10"
:local ampnmUser "ampnm-monitor"
:local ampnmPass "Str0ngLongPassword!"

# Enable and lock down the API service (port 8728) to the AMPNM host
/ip service set api disabled=no address=$ampnmServer/32

# Create a read-only group for traffic monitoring
/user group add name=ampnm-monitor policy=read,api,!local,!telnet,!ssh,!ftp,!reboot,!write

# Create the monitoring user bound to that group
/user add name=$ampnmUser password=$ampnmPass group=ampnm-monitor comment="AMPNM traffic monitor"

# Allow API traffic only from the AMPNM Docker server
/ip firewall address-list add list=ampnm-servers address=$ampnmServer comment="AMPNM Docker server"
/ip firewall filter add chain=input src-address-list=ampnm-servers protocol=tcp dst-port=8728 action=accept comment="Allow AMPNM API"
/ip firewall filter add chain=input protocol=tcp dst-port=8728 action=drop comment="Drop other API attempts"</code>
                            </div>
                            <ul class="list-disc list-inside space-y-2 text-sm">
                                <li>Re-use the existing <code>ampnm-monitor</code> group if it already exists to avoid duplicates.</li>
                                <li>If you already limit <strong>API</strong> with specific <em>allowed-addresses</em>, merge the AMPNM server IP instead of overriding existing values.</li>
                                <li>Leave SSH/Winbox fully disabled for this user; only <strong>read</strong> and <strong>api</strong> permissions are needed.</li>
                            </ul>
                        </div>

                        <div class="bg-slate-700 p-4 rounded mb-4">
                            <h3 class="text-2xl font-semibold mb-3">Add the router in AMPNM</h3>
                            <ol class="list-decimal list-inside space-y-2 text-sm">
                                <li>Open <strong>Devices → Add Device</strong> and choose a <em>Router</em> icon for clarity.</li>
                                <li>Set the router's management IP as the <strong>Host/IP</strong>.</li>
                                <li>Enter the MikroTik API credentials:<ul class="ml-6 mt-1 space-y-1 list-disc list-inside">
                                        <li><strong>Username:</strong> <code class="bg-slate-800 px-2 py-1 rounded">ampnm-monitor</code> (or your custom name)</li>
                                        <li><strong>Password:</strong> the value of <code class="bg-slate-800 px-2 py-1 rounded">ampnmPass</code></li>
                                        <li><strong>Port:</strong> <code class="bg-slate-800 px-2 py-1 rounded">8728</code> (default RouterOS API)</li>
                                    </ul>
                                </li>
                                <li>Save the device; AMPNM will poll interface traffic through the API user you created.</li>
                            </ol>
                        </div>

                        <div class="bg-blue-900/40 border-l-4 border-blue-400 p-4 rounded">
                            <h4 class="font-bold text-lg mb-2"><i class="fas fa-shield-alt text-blue-300 mr-2"></i>Hardening tips</h4>
                            <ul class="list-disc list-inside space-y-1 text-sm">
                                <li>Use a strong, unique password and rotate it periodically.</li>
                                <li>Keep the <strong>API</strong> service limited to your AMPNM server IP only.</li>
                                <li>Review <code>/ip firewall filter</code> counters to ensure only expected API hits arrive.</li>
                                <li>Disable the account if you pause monitoring for an extended period.</li>
                            </ul>
                        </div>
                    </section>

                    <!-- License -->
                    <section id="license" class="doc-section">
                        <h2 class="text-3xl font-bold text-cyan-400 mb-4">
                            <i class="fas fa-key mr-2"></i>License Management
                        </h2>

                        <h3 class="text-2xl font-semibold mb-3">License Activation</h3>
                        <p class="mb-4">AMPNM requires a valid license from <a href="https://portal.itsupport.com.bd" class="text-cyan-400 hover:underline">portal.itsupport.com.bd</a></p>

                        <div class="bg-slate-700 p-4 rounded mb-6">
                            <h4 class="font-bold mb-2">To activate:</h4>
                            <ol class="list-decimal list-inside space-y-1 ml-4">
                                <li>Purchase license from portal</li>
                                <li>Copy your license key</li>
                                <li>Enter in License Setup page</li>
                                <li>Application validates with portal</li>
                            </ol>
                        </div>

                        <h3 class="text-2xl font-semibold mb-3">License Tiers</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                            <div class="bg-gradient-to-br from-gray-700 to-gray-800 p-4 rounded-lg">
                                <h4 class="font-bold text-lg mb-2">Starter</h4>
                                <p class="text-2xl font-bold text-cyan-400 mb-2">10 Devices</p>
                                <p class="text-sm">Perfect for small offices</p>
                            </div>
                            <div class="bg-gradient-to-br from-blue-700 to-blue-800 p-4 rounded-lg">
                                <h4 class="font-bold text-lg mb-2">Professional</h4>
                                <p class="text-2xl font-bold text-cyan-400 mb-2">50 Devices</p>
                                <p class="text-sm">Ideal for growing networks</p>
                            </div>
                            <div class="bg-gradient-to-br from-purple-700 to-purple-800 p-4 rounded-lg">
                                <h4 class="font-bold text-lg mb-2">Enterprise</h4>
                                <p class="text-2xl font-bold text-cyan-400 mb-2">200 Devices</p>
                                <p class="text-sm">For large infrastructure</p>
                            </div>
                        </div>

                        <h3 class="text-2xl font-semibold mb-3">Offline Mode</h3>
                        <div class="bg-blue-900/30 border-l-4 border-blue-500 p-4 mb-4">
                            <p class="mb-2"><i class="fas fa-wifi-slash mr-2 text-blue-400"></i><strong>AMPNM supports offline operation for up to 30 days.</strong></p>
                            <ul class="space-y-1 ml-6 text-sm">
                                <li><strong>Days 0-9:</strong> Normal operation, silent offline mode</li>
                                <li><strong>Days 9-30:</strong> Warning displayed, full functionality maintained</li>
                                <li><strong>Day 30+:</strong> Application disabled until portal connection restored</li>
                            </ul>
                        </div>

                        <div class="bg-yellow-900/30 border-l-4 border-yellow-500 p-4">
                            <p><i class="fas fa-info-circle mr-2 text-yellow-400"></i><strong>Note:</strong> License verification attempts every 5 minutes when online. The 30-day period only counts when portal cannot be reached.</p>
                        </div>
                    </section>

                    <!-- Troubleshooting -->
                    <section id="troubleshooting" class="doc-section">
                        <h2 class="text-3xl font-bold text-cyan-400 mb-4">
                            <i class="fas fa-tools mr-2"></i>Troubleshooting
                        </h2>

                        <div class="space-y-6">
                            <div class="bg-slate-700 p-4 rounded">
                                <h3 class="font-bold text-lg mb-2"><i class="fas fa-exclamation-triangle text-yellow-400 mr-2"></i>All Devices Show Offline</h3>
                                <p class="mb-2"><strong>Possible Causes:</strong></p>
                                <ul class="list-disc list-inside ml-4 space-y-1 text-sm">
                                    <li>Firewall blocking ICMP packets</li>
                                    <li>Network connectivity issues</li>
                                    <li>Incorrect IP addresses</li>
                                    <li>Docker network configuration</li>
                                </ul>
                                <p class="mt-3 mb-1"><strong>Solutions:</strong></p>
                                <div class="code-block">
                                    <code>docker-compose exec app ping -c 3 8.8.8.8</code>
                                </div>
                            </div>

                            <div class="bg-slate-700 p-4 rounded">
                                <h3 class="font-bold text-lg mb-2"><i class="fas fa-envelope text-red-400 mr-2"></i>Email Notifications Not Working</h3>
                                <p class="mb-2"><strong>Check:</strong></p>
                                <ul class="list-disc list-inside ml-4 space-y-1 text-sm">
                                    <li>SMTP credentials are correct</li>
                                    <li>Port is not blocked by firewall</li>
                                    <li>Using app password (for Gmail/2FA accounts)</li>
                                    <li>Recipients are subscribed to device</li>
                                    <li>Notification triggers are enabled</li>
                                </ul>
                            </div>

                            <div class="bg-slate-700 p-4 rounded">
                                <h3 class="font-bold text-lg mb-2"><i class="fas fa-key text-purple-400 mr-2"></i>License Verification Failed</h3>
                                <p class="mb-2"><strong>Steps to Resolve:</strong></p>
                                <ol class="list-decimal list-inside ml-4 space-y-1 text-sm">
                                    <li>Verify internet connection</li>
                                    <li>Check firewall allows HTTPS to portal.itsupport.com.bd</li>
                                    <li>Confirm license key is correct</li>
                                    <li>Check license hasn't expired</li>
                                    <li>View logs: <code class="bg-slate-800 px-2 py-1 rounded text-xs">docker-compose logs app | grep LICENSE</code></li>
                                </ol>
                            </div>

                            <div class="bg-slate-700 p-4 rounded">
                                <h3 class="font-bold text-lg mb-2"><i class="fas fa-server text-cyan-400 mr-2"></i>Container Won't Start</h3>
                                <div class="code-block">
                                    <code># Check logs<br>
docker-compose logs app<br><br>
# Restart services<br>
docker-compose restart<br><br>
# Rebuild if needed<br>
docker-compose build --no-cache<br>
docker-compose up -d</code>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- FAQ -->
                    <section id="faq" class="doc-section">
                        <h2 class="text-3xl font-bold text-cyan-400 mb-4">
                            <i class="fas fa-question-circle mr-2"></i>Frequently Asked Questions
                        </h2>

                        <div class="space-y-4">
                            <div class="bg-slate-700 p-4 rounded">
                                <h3 class="font-bold mb-2">Q: How many devices can I monitor?</h3>
                                <p class="text-sm">A: Depends on your license tier: Starter (10), Professional (50), Enterprise (200 devices).</p>
                            </div>

                            <div class="bg-slate-700 p-4 rounded">
                                <h3 class="font-bold mb-2">Q: What is the minimum check interval?</h3>
                                <p class="text-sm">A: 10 seconds minimum. However, we recommend 60 seconds (1 minute) for most use cases to avoid network flooding.</p>
                            </div>

                            <div class="bg-slate-700 p-4 rounded">
                                <h3 class="font-bold mb-2">Q: Can I monitor devices on different networks/VLANs?</h3>
                                <p class="text-sm">A: Yes, as long as the AMPNM server can reach the devices via network routing. Ensure firewall rules allow ICMP/TCP from the Docker host.</p>
                            </div>

                            <div class="bg-slate-700 p-4 rounded">
                                <h3 class="font-bold mb-2">Q: Does AMPNM work offline?</h3>
                                <p class="text-sm">A: Yes! AMPNM can operate offline for up to 30 days. After 9 days, a warning appears. After 30 days, the application requires portal connection.</p>
                            </div>

                            <div class="bg-slate-700 p-4 rounded">
                                <h3 class="font-bold mb-2">Q: How do I backup my configuration?</h3>
                                <p class="text-sm">A: Backup the MySQL database and uploads folder:
                                    <div class="code-block mt-2">
                                        <code>docker-compose exec mysql mysqldump -u root -p network_monitor > backup.sql<br>
docker cp ampnm-app:/var/www/html/uploads ./uploads-backup</code>
                                    </div>
                                </p>
                            </div>

                            <div class="bg-slate-700 p-4 rounded">
                                <h3 class="font-bold mb-2">Q: Can multiple users access AMPNM simultaneously?</h3>
                                <p class="text-sm">A: Yes! AMPNM supports multi-user access with role-based permissions (Admin and Viewer roles).</p>
                            </div>

                            <div class="bg-slate-700 p-4 rounded">
                                <h3 class="font-bold mb-2">Q: What ports need to be open?</h3>
                                <p class="text-sm">A: 
                                    <ul class="list-disc list-inside ml-4 mt-2">
                                        <li>Port 2266 (or custom): Web interface</li>
                                        <li>ICMP: For ping monitoring (outbound)</li>
                                        <li>HTTPS (443): License verification to portal.itsupport.com.bd</li>
                                        <li>SMTP port: If using email notifications (usually 587 or 465)</li>
                                    </ul>
                                </p>
                            </div>

                            <div class="bg-slate-700 p-4 rounded">
                                <h3 class="font-bold mb-2">Q: How long is historical data retained?</h3>
                                <p class="text-sm">A: Ping results and status logs are retained indefinitely (until you delete them). You can export historical data or clear old records from the History page.</p>
                            </div>
                        </div>
                    </section>

                    <!-- Support -->
                    <section id="support" class="doc-section">
                        <h2 class="text-3xl font-bold text-cyan-400 mb-4">
                            <i class="fas fa-headset mr-2"></i>Getting Support
                        </h2>
                        <div class="bg-gradient-to-br from-cyan-900/30 to-blue-900/30 border border-cyan-700 rounded-lg p-6">
                            <p class="text-lg mb-4">Need help? We're here for you!</p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <h4 class="font-bold mb-2"><i class="fas fa-globe text-cyan-400 mr-2"></i>Portal</h4>
                                    <a href="https://portal.itsupport.com.bd" class="text-cyan-400 hover:underline">portal.itsupport.com.bd</a>
                                </div>
                                <div>
                                    <h4 class="font-bold mb-2"><i class="fas fa-envelope text-cyan-400 mr-2"></i>Email Support</h4>
                                    <a href="mailto:support@itsupport.com.bd" class="text-cyan-400 hover:underline">support@itsupport.com.bd</a>
                                </div>
                                <div>
                                    <h4 class="font-bold mb-2"><i class="fas fa-ticket-alt text-cyan-400 mr-2"></i>Support Tickets</h4>
                                    <a href="https://portal.itsupport.com.bd/support.php" class="text-cyan-400 hover:underline">Open a Ticket</a>
                                </div>
                                <div>
                                    <h4 class="font-bold mb-2"><i class="fas fa-file-alt text-cyan-400 mr-2"></i>Documentation</h4>
                                    <span>README.md & SECURITY.md in installation folder</span>
                                </div>
                            </div>
                        </div>
                    </section>

                </div>
            </main>

        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-slate-800 mt-12 py-6">
        <div class="container mx-auto px-4 text-center text-slate-400">
            <p><strong>AMPNM</strong> - Advanced Multi-Protocol Network Monitor</p>
            <p class="text-sm mt-2">Made with <i class="fas fa-heart text-red-500"></i> by IT Support Bangladesh</p>
            <p class="text-xs mt-2">Version 1.0 | <a href="https://portal.itsupport.com.bd" class="text-cyan-400 hover:underline">portal.itsupport.com.bd</a></p>
        </div>
    </footer>

    <script>
        // Smooth scrolling for navigation links
        document.querySelectorAll('.doc-nav a').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('href');
                const targetElement = document.querySelector(targetId);
                
                if (targetElement) {
                    targetElement.scrollIntoView({ behavior: 'smooth' });
                    
                    // Update active state
                    document.querySelectorAll('.doc-nav a').forEach(l => l.classList.remove('active'));
                    this.classList.add('active');
                }
            });
        });

        // Update active link on scroll
        window.addEventListener('scroll', () => {
            let current = '';
            document.querySelectorAll('.doc-section').forEach(section => {
                const sectionTop = section.offsetTop;
                if (pageYOffset >= sectionTop - 100) {
                    current = section.getAttribute('id');
                }
            });

            document.querySelectorAll('.doc-nav a').forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href') === '#' + current) {
                    link.classList.add('active');
                }
            });
        });
    </script>

</body>
</html>
