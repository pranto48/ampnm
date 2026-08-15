<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * AMPNM Real-time Telemetry & Event Broadcaster
 */
header('Content-Type: application/json');

class AMPNM_WebSocketBroadcaster {
    private static $instance = null;
    private $logFile;

    private function __construct() {
        $this->logFile = __DIR__ . '/../storage/logs/websocket_events.log';
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Broadcast an event to connected clients/services
     */
    public function broadcastEvent($eventType, array $payload) {
        $event = [
            'event' => $eventType,
            'timestamp' => date('c'),
            'data' => $payload
        ];

        // Format as SSE / JSON payload
        $jsonPayload = json_encode($event);
        
        // Log telemetry event to storage stream
        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents($this->logFile, $jsonPayload . PHP_EOL, FILE_APPEND | LOCK_EX);

        return true;
    }
}
