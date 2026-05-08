<?php
require_once 'includes/auth_check.php';
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

$graphUrl = trim($data['url'] ?? '');

if ($graphUrl === '' || !filter_var($graphUrl, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    echo json_encode(['error' => 'A valid graph URL is required.']);
    exit;
}

$response = null;

// Prefer cURL for better TLS/certificate compatibility.
if (function_exists('curl_init')) {
    $ch = curl_init($graphUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 6,
        CURLOPT_USERAGENT => 'DockerGraphProbe/1.0',
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
}

// Fallback to file_get_contents if cURL is unavailable or failed.
if ($response === null || $response === false) {
    $context = stream_context_create([
        'http' => [
            'timeout' => 6,
            'follow_location' => 1,
            'header' => "User-Agent: DockerGraphProbe/1.0\r\n",
        ],
        'https' => [
            'timeout' => 6,
            'follow_location' => 1,
            'header' => "User-Agent: DockerGraphProbe/1.0\r\n",
        ],
    ]);

    $response = @file_get_contents($graphUrl, false, $context);
}

if ($response === false || $response === null) {
    http_response_code(502);
    echo json_encode(['error' => 'Unable to reach the graph endpoint.']);
    exit;
}

$extractStat = function (string $needle) use ($response) {
    // Strip tags to handle Mikrotik HTML output, then search for the label.
    $plain = strip_tags($response);
    $pattern = sprintf('/%s\s*:?\s*([^;\n]+)/i', preg_quote($needle, '/'));

    if (preg_match($pattern, $plain, $matches)) {
        return trim($matches[1]);
    }
    return null;
};

$currentIn = $extractStat('Current In');
$currentOut = $extractStat('Current Out');

if ($currentIn === null && $currentOut === null) {
    http_response_code(204);
    exit;
}

echo json_encode([
    'current_in' => $currentIn,
    'current_out' => $currentOut,
]);
