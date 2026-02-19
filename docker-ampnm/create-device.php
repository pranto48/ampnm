<?php
require_once 'includes/functions.php'; // Include functions early for DB connection and license checks
require_once 'includes/auth_check.php'; // Auth check also needs to be early

$pdo = getDbConnection();
$current_user_id = $_SESSION['user_id'];
$message = '';
$monitor_method = 'ping';

function dbColumnExists(PDO $pdo, string $table, string $column): bool {
    try {
        $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
        if (!$dbName) return false;
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $stmt->execute([$dbName, $table, $column]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

// Load device icons library
$deviceIconsLibrary = require_once 'includes/device_icons.php';

// Handle form submission BEFORE any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $ip = trim($_POST['ip'] ?? '');
    $monitor_method = $_POST['monitor_method'] ?? 'ping';
    $check_port = $_POST['check_port'] ?? null;
    $type = $_POST['type'] ?? 'server';
    $subchoice = $_POST['subchoice'] ?? 0;
    $description = trim($_POST['description'] ?? '');
    $map_id = $_POST['map_id'] ?? null;
    $ping_interval = $_POST['ping_interval'] ?? null;
    $icon_size = $_POST['icon_size'] ?? 50;
    $name_text_size = $_POST['name_text_size'] ?? 14;
    $icon_url = trim($_POST['icon_url'] ?? '');
    $warning_latency_threshold = $_POST['warning_latency_threshold'] ?? null;
    $warning_packetloss_threshold = $_POST['warning_packetloss_threshold'] ?? null;
    $critical_latency_threshold = $_POST['critical_latency_threshold'] ?? null;
    $critical_packetloss_threshold = $_POST['critical_packetloss_threshold'] ?? null;
    $show_live_ping = isset($_POST['show_live_ping']) ? 1 : 0;

    // Basic validation
    if (empty($name)) {
        $message = '<div class="bg-red-500/20 border border-red-500/30 text-red-300 text-sm rounded-lg p-3 text-center">Device name is required.</div>';
    } else {
        try {
            // License check for max devices
            $max_devices = $_SESSION['license_max_devices'] ?? 0;
            $current_devices = $_SESSION['current_device_count'] ?? 0;

            if ($max_devices > 0 && $current_devices >= $max_devices) {
                $message = '<div class="bg-red-500/20 border border-red-500/30 text-red-300 text-sm rounded-lg p-3 text-center">License limit reached. You cannot add more than ' . $max_devices . ' devices.</div>';
            } else {
                $hasSubchoice = dbColumnExists($pdo, 'devices', 'subchoice');
                if ($hasSubchoice) {
                    $sql = "INSERT INTO devices (user_id, name, ip, check_port, monitor_method, type, subchoice, description, map_id, x, y, ping_interval, icon_size, name_text_size, icon_url, warning_latency_threshold, warning_packetloss_threshold, critical_latency_threshold, critical_packetloss_threshold, show_live_ping) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        $current_user_id,
                        $name,
                        empty($ip) ? null : $ip,
                        empty($check_port) ? null : $check_port,
                        $monitor_method,
                        $type,
                        is_numeric($subchoice) ? (int)$subchoice : 0,
                        empty($description) ? null : $description,
                        empty($map_id) ? null : $map_id,
                        100, 100, // Default X, Y positions for new devices
                        empty($ping_interval) ? null : $ping_interval, $icon_size, $name_text_size, empty($icon_url) ? null : $icon_url,
                        empty($warning_latency_threshold) ? null : $warning_latency_threshold, empty($warning_packetloss_threshold) ? null : $warning_packetloss_threshold,
                        empty($critical_latency_threshold) ? null : $critical_latency_threshold, empty($critical_packetloss_threshold) ? null : $critical_packetloss_threshold,
                        $show_live_ping
                    ]);
                } else {
                    // Graceful fallback for older/fresh DB missing devices.subchoice
                    $sql = "INSERT INTO devices (user_id, name, ip, check_port, monitor_method, type, description, map_id, x, y, ping_interval, icon_size, name_text_size, icon_url, warning_latency_threshold, warning_packetloss_threshold, critical_latency_threshold, critical_packetloss_threshold, show_live_ping) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        $current_user_id,
                        $name,
                        empty($ip) ? null : $ip,
                        empty($check_port) ? null : $check_port,
                        $monitor_method,
                        $type,
                        empty($description) ? null : $description,
                        empty($map_id) ? null : $map_id,
                        100, 100,
                        empty($ping_interval) ? null : $ping_interval, $icon_size, $name_text_size, empty($icon_url) ? null : $icon_url,
                        empty($warning_latency_threshold) ? null : $warning_latency_threshold, empty($warning_packetloss_threshold) ? null : $warning_packetloss_threshold,
                        empty($critical_latency_threshold) ? null : $critical_latency_threshold, empty($critical_packetloss_threshold) ? null : $critical_packetloss_threshold,
                        $show_live_ping
                    ]);
                }
                // Redirect to map.php with the map_id
                if ($map_id) {
                    header('Location: map.php?map_id=' . urlencode($map_id));
                } else {
                    header('Location: map.php'); // If no map_id, go to map page without specific map
                }
                exit; // Important to exit after redirect
            }
        } catch (PDOException $e) {
            $message = '<div class="bg-red-500/20 border border-red-500/30 text-red-300 text-sm rounded-lg p-3 text-center">Error adding device: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

// Now include header.php, after all potential redirects
include 'header.php';
?>

<main id="app">
    <div class="container mx-auto px-4 py-8">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-3xl font-bold text-white">Add New Device</h1>
            <a href="map.php" class="px-4 py-2 bg-slate-600 text-white rounded-lg hover:bg-slate-500"><i class="fas fa-arrow-left mr-2"></i>Back to Map</a>
        </div>

        <div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-6 max-w-4xl mx-auto">
            <?= $message ?>
            <form method="POST" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="name" class="block text-sm font-medium text-slate-400 mb-1">Device Name</label>
                        <input type="text" id="name" name="name" placeholder="e.g., Main Router" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                    </div>
                    <div>
                        <label for="ip" class="block text-sm font-medium text-slate-400 mb-1">IP Address (Optional)</label>
                        <input type="text" id="ip" name="ip" placeholder="e.g., 192.168.1.1" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500" value="<?= htmlspecialchars($_POST['ip'] ?? '') ?>">
                    </div>
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-slate-400 mb-1">Description (Optional)</label>
                    <textarea id="description" name="description" rows="2" placeholder="Optional notes about the device" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                </div>

                <div>
                    <label for="type" class="block text-sm font-medium text-slate-400 mb-1">Device Type & Icon</label>
                    <select id="type" name="type" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500 mb-4">
                        <?php
                        foreach ($deviceIconsLibrary as $value => $typeData) {
                            $selected = (($_POST['type'] ?? 'server') === $value) ? 'selected' : '';
                            $iconCount = count($typeData['icons'] ?? []);
                            echo "<option value=\"$value\" $selected>{$typeData['label']} ($iconCount variants)</option>";
                        }
                        ?>
                    </select>

                    <!-- Icon variant index (0-based) selected from the icon picker -->
                    <input type="hidden" id="subchoice" name="subchoice" value="<?= htmlspecialchars($_POST['subchoice'] ?? 0) ?>">

                    <!-- Current selection preview (kept in sync by assets/icon-picker.js) -->
                    <div id="selectedIconPreview" class="flex items-center gap-3 mb-4 px-3 py-2 rounded-lg bg-slate-900 border border-slate-700">
                        <i id="selectedIconPreviewIcon" class="fas fa-circle text-slate-200"></i>
                        <div class="leading-tight">
                            <div id="selectedIconPreviewTitle" class="text-sm font-semibold text-white"></div>
                            <div id="selectedIconPreviewSubtitle" class="text-xs text-slate-400"></div>
                        </div>
                    </div>
                    
                    <!-- Enhanced Icon Picker Container -->
                    <link rel="stylesheet" href="assets/icon-picker.css">
                    <div id="iconPickerContainer" class="icon-picker-container"></div>
                </div>

                <div>
                    <label for="map_id" class="block text-sm font-medium text-slate-400 mb-1">Map Assignment (Optional)</label>
                    <select id="map_id" name="map_id" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                        <option value="">Unassigned</option>
                        <?php foreach ($maps as $map): ?>
                            <option value="<?= htmlspecialchars($map['id']) ?>" <?= (($_POST['map_id'] ?? '') == $map['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($map['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="monitor_method" class="block text-sm font-medium text-slate-400 mb-1">Monitoring Method</label>
                        <select id="monitor_method" name="monitor_method" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                            <option value="ping" <?= (($monitor_method ?? 'ping') === 'ping') ? 'selected' : '' ?>>IP Ping (ICMP)</option>
                            <option value="port" <?= (($monitor_method ?? 'ping') === 'port') ? 'selected' : '' ?>>Service Port Check</option>
                        </select>
                        <p class="text-xs text-slate-500 mt-1">Choose how availability is checked for this device.</p>
                    </div>
                    <div>
                        <label for="check_port" class="block text-sm font-medium text-slate-400 mb-1">Service Port (Optional)</label>
                        <input type="number" id="check_port" name="check_port" placeholder="e.g., 80 for HTTP" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500" value="<?= htmlspecialchars($_POST['check_port'] ?? '') ?>">
                        <p class="text-xs text-slate-500 mt-1">For port checks, provide the port to probe; leave blank for pure ping.</p>
                    </div>
                </div>

                <div>
                    <label for="ping_interval" class="block text-sm font-medium text-slate-400 mb-1">Ping Interval (seconds)</label>
                    <input type="number" id="ping_interval" name="ping_interval" placeholder="e.g., 60 (leave blank for no auto ping)" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500" value="<?= htmlspecialchars($_POST['ping_interval'] ?? '') ?>">
                </div>

                <fieldset class="border border-slate-600 rounded-lg p-4">
                    <legend class="text-sm font-medium text-slate-400 px-2">Custom Icon (Optional)</legend>
                    <div class="space-y-3">
                        <div>
                            <label for="icon_url" class="block text-sm font-medium text-slate-400 mb-1">Icon URL</label>
                            <input type="text" id="icon_url" name="icon_url" placeholder="Leave blank to use default icon" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-sm" value="<?= htmlspecialchars($_POST['icon_url'] ?? '') ?>">
                        </div>
                    </div>
                </fieldset>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="icon_size" class="block text-sm font-medium text-slate-400 mb-1">Icon Size</label>
                        <input type="number" id="icon_size" name="icon_size" placeholder="e.g., 50" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500" value="<?= htmlspecialchars($_POST['icon_size'] ?? '50') ?>">
                    </div>
                    <div>
                        <label for="name_text_size" class="block text-sm font-medium text-slate-400 mb-1">Name Text Size</label>
                        <input type="number" id="name_text_size" name="name_text_size" placeholder="e.g., 14" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500" value="<?= htmlspecialchars($_POST['name_text_size'] ?? '14') ?>">
                    </div>
                </div>

                <fieldset class="border border-slate-600 rounded-lg p-4">
                    <legend class="text-sm font-medium text-slate-400 px-2">Status Thresholds (Optional)</legend>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="warning_latency_threshold" class="block text-xs text-slate-400 mb-1">Warn Latency (ms)</label>
                            <input type="number" id="warning_latency_threshold" name="warning_latency_threshold" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-sm" value="<?= htmlspecialchars($_POST['warning_latency_threshold'] ?? '') ?>">
                        </div>
                        <div>
                            <label for="warning_packetloss_threshold" class="block text-xs text-slate-400 mb-1">Warn Packet Loss (%)</label>
                            <input type="number" id="warning_packetloss_threshold" name="warning_packetloss_threshold" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-sm" value="<?= htmlspecialchars($_POST['warning_packetloss_threshold'] ?? '') ?>">
                        </div>
                        <div>
                            <label for="critical_latency_threshold" class="block text-xs text-slate-400 mb-1">Critical Latency (ms)</label>
                            <input type="number" id="critical_latency_threshold" name="critical_latency_threshold" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-sm" value="<?= htmlspecialchars($_POST['critical_latency_threshold'] ?? '') ?>">
                        </div>
                        <div>
                            <label for="critical_packetloss_threshold" class="block text-xs text-slate-400 mb-1">Critical Packet Loss (%)</label>
                            <input type="number" id="critical_packetloss_threshold" name="critical_packetloss_threshold" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-sm" value="<?= htmlspecialchars($_POST['critical_packetloss_threshold'] ?? '') ?>">
                        </div>
                    </div>
                </fieldset>

                <div>
                    <label for="show_live_ping" class="flex items-center text-sm font-medium text-slate-400">
                        <input type="checkbox" id="show_live_ping" name="show_live_ping" class="h-4 w-4 rounded border-slate-500 bg-slate-700 text-cyan-600 focus:ring-cyan-500" <?= isset($_POST['show_live_ping']) ? 'checked' : '' ?>>
                        <span class="ml-2">Show live ping status on map</span>
                    </label>
                </div>

                <div class="flex justify-end gap-4 mt-6">
                    <button type="submit" class="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700">
                        <i class="fas fa-plus mr-2"></i>Add Device
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

<!-- Load device icons library to JavaScript -->
<script>
    window.deviceIconsLibrary = <?= json_encode($deviceIconsLibrary, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?>;
</script>

<!-- Load enhanced icon picker -->
<script src="assets/icon-picker.js"></script>

<?php include 'footer.php'; ?>