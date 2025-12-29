<?php
/**
 * Agent Download Handler
 * Forces proper download of PowerShell and Batch files
 */

$file = $_GET['file'] ?? 'AMPNM-Agent-Installer.ps1';
$allowedFiles = [
    'AMPNM-Agent-Installer.ps1',
    'AMPNM-Agent-Simple.bat'
];

// Validate file name
if (!in_array($file, $allowedFiles)) {
    http_response_code(404);
    echo "File not found";
    exit;
}

$filePath = __DIR__ . '/assets/windows-agent/' . $file;

if (!file_exists($filePath)) {
    http_response_code(404);
    echo "File not found: $file";
    exit;
}

$serverUrl = trim($_GET['server_url'] ?? '');
$agentToken = trim($_GET['agent_token'] ?? '');
$customizedContent = null;

if ($file === 'AMPNM-Agent-Installer.ps1' && ($serverUrl !== '' || $agentToken !== '')) {
    $customizedContent = file_get_contents($filePath);

    if ($customizedContent === false) {
        http_response_code(500);
        echo "Unable to read agent installer";
        exit;
    }

    $escapePowerShell = function ($value) {
        $escaped = str_replace('`', '``', $value);
        return str_replace('"', '`"', $escaped);
    };

    if ($serverUrl !== '') {
        $escapedServerUrl = $escapePowerShell($serverUrl);
        $customizedContent = str_replace(
            '[string]$ServerUrl,',
            '[string]$ServerUrl = "' . $escapedServerUrl . '",',
            $customizedContent
        );
    }

    if ($agentToken !== '') {
        $escapedAgentToken = $escapePowerShell($agentToken);
        $customizedContent = str_replace(
            '[string]$AgentToken,',
            '[string]$AgentToken = "' . $escapedAgentToken . '",',
            $customizedContent
        );
    }
}

// Get file extension
$ext = pathinfo($file, PATHINFO_EXTENSION);
$mimeTypes = [
    'ps1' => 'application/octet-stream',
    'bat' => 'application/octet-stream'
];

$contentType = $mimeTypes[$ext] ?? 'application/octet-stream';

// Set headers for download
header('Content-Type: ' . $contentType);
header('Content-Disposition: attachment; filename="' . $file . '"');
header('Content-Length: ' . ($customizedContent !== null ? strlen($customizedContent) : filesize($filePath)));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Output file
if ($customizedContent !== null) {
    echo $customizedContent;
} else {
    readfile($filePath);
}
exit;
