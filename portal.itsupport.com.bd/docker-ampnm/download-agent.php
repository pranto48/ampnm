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
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Output file
readfile($filePath);
exit;
