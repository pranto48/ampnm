<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * AMPNM Webhook Notification Dispatcher (Slack, Discord, MS Teams, PagerDuty, Custom)
 */

class AMPNM_WebhookDispatcher {
    
    /**
     * Dispatch notification payload to Slack Webhook
     */
    public static function sendSlack($webhookUrl, $title, $message, $color = '#ef4444') {
        if (empty($webhookUrl)) return false;
        
        $payload = [
            'attachments' => [
                [
                    'color' => $color,
                    'title' => '🛡️ AMPNM Alert: ' . $title,
                    'text' => $message,
                    'ts' => time()
                ]
            ]
        ];

        return self::postJson($webhookUrl, $payload);
    }

    /**
     * Dispatch notification payload to Discord Webhook
     */
    public static function sendDiscord($webhookUrl, $title, $message, $colorHex = 15158332) {
        if (empty($webhookUrl)) return false;

        $payload = [
            'embeds' => [
                [
                    'title' => '🛡️ AMPNM Alert: ' . $title,
                    'description' => $message,
                    'color' => $colorHex,
                    'timestamp' => date('c')
                ]
            ]
        ];

        return self::postJson($webhookUrl, $payload);
    }

    /**
     * Dispatch notification payload to PagerDuty
     */
    public static function sendPagerDuty($routingKey, $summary, $severity = 'critical') {
        if (empty($routingKey)) return false;

        $payload = [
            'routing_key' => $routingKey,
            'event_action' => 'trigger',
            'payload' => [
                'summary' => 'AMPNM: ' . $summary,
                'source' => 'AMPNM Core',
                'severity' => $severity,
                'timestamp' => date('c')
            ]
        ];

        return self::postJson('https://events.pagerduty.com/v2/enqueue', $payload);
    }

    /**
     * Helper to perform cURL POST request
     */
    private static function postJson($url, array $data) {
        $ch = curl_init($url);
        $jsonData = json_encode($data);

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($jsonData)
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode >= 200 && $httpCode < 300;
    }
}
