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
     * Dispatch notification payload to Microsoft Teams Connector / PowerAutomate
     */
    public static function sendMSTeams($webhookUrl, $title, $message, $themeColor = 'EF4444') {
        if (empty($webhookUrl)) return false;

        $payload = [
            '@type' => 'MessageCard',
            '@context' => 'http://schema.org/extensions',
            'themeColor' => $themeColor,
            'summary' => 'AMPNM Alert: ' . $title,
            'sections' => [
                [
                    'activityTitle' => '🛡️ AMPNM Alert: ' . $title,
                    'activitySubtitle' => date('Y-m-d H:i:s T'),
                    'text' => $message,
                    'markdown' => true
                ]
            ]
        ];

        return self::postJson($webhookUrl, $payload);
    }

    /**
     * Dispatch notification payload to PagerDuty Events API v2
     */
    public static function sendPagerDuty($routingKey, $summary, $severity = 'critical') {
        if (empty($routingKey)) return false;

        $payload = [
            'routing_key' => $routingKey,
            'event_action' => 'trigger',
            'payload' => [
                'summary' => 'AMPNM: ' . $summary,
                'source' => 'AMPNM Network Monitor',
                'severity' => $severity,
                'timestamp' => date('c')
            ]
        ];

        return self::postJson('https://events.pagerduty.com/v2/enqueue', $payload);
    }

    /**
     * Dispatch notification payload to Custom Webhook URL
     */
    public static function sendCustomWebhook($webhookUrl, array $data) {
        if (empty($webhookUrl)) return false;
        return self::postJson($webhookUrl, $data);
    }

    /**
     * Test webhook delivery for specific channel
     */
    public static function testChannel($type, $url, $routingKey = '') {
        $testTitle = "Webhook Test Notification";
        $testMessage = "✅ This is a live test notification from AMPNM Network Monitor verifying successful webhook delivery at " . date('Y-m-d H:i:s T') . ".";

        switch (strtolower(trim($type))) {
            case 'slack':
                $res = self::sendSlack($url, $testTitle, $testMessage, '#22c55e');
                break;
            case 'discord':
                $res = self::sendDiscord($url, $testTitle, $testMessage, 2278750);
                break;
            case 'msteams':
                $res = self::sendMSTeams($url, $testTitle, $testMessage, '22C55E');
                break;
            case 'pagerduty':
                $res = self::sendPagerDuty($routingKey ?: $url, $testTitle . ' - ' . $testMessage, 'info');
                break;
            case 'custom':
            default:
                $res = self::sendCustomWebhook($url, [
                    'event' => 'test_notification',
                    'title' => $testTitle,
                    'message' => $testMessage,
                    'timestamp' => date('c'),
                    'platform' => 'AMPNM'
                ]);
                break;
        }

        return $res ? ['success' => true, 'message' => "Test alert dispatched successfully to {$type}!"]
                    : ['success' => false, 'message' => "Failed to dispatch test alert to {$type}. Please verify the URL/Key."];
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
            'Content-Length: ' . strlen($jsonData),
            'User-Agent: AMPNM-Webhook-Dispatcher/1.20'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode >= 200 && $httpCode < 300;
    }
}

