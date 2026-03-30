<?php
http_response_code(200);
header('Content-Type: application/json');
echo json_encode([
    'status' => 'ok',
    'service' => 'ampnm-web',
    'time' => gmdate('c'),
]);
