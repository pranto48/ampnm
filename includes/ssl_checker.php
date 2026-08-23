<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * SSL / TLS Certificate Expiry & Health Checker Engine
 */

class SSLChecker {
    /**
     * Checks SSL/TLS certificate for a domain or IP:Port
     */
    public static function checkCertificate(string $domain, int $port = 443, int $timeoutSeconds = 5): array {
        $domain = trim($domain);
        // Remove scheme if passed (e.g. https://example.com -> example.com)
        $domain = preg_replace('#^https?://#i', '', $domain);
        $domain = rtrim($domain, '/');
        
        // Extract host and port if host:port format
        if (str_contains($domain, ':')) {
            $parts = explode(':', $domain, 2);
            $domain = $parts[0];
            $port = (int)$parts[1];
        }

        if (empty($domain)) {
            return [
                'success' => false,
                'error' => 'Domain name or host is required',
                'status' => 'error'
            ];
        }

        $g = stream_context_create([
            "ssl" => [
                "capture_peer_cert" => true,
                "verify_peer" => false,
                "verify_peer_name" => false,
                "allow_self_signed" => true,
                "SNI_enabled" => true,
                "peer_name" => $domain
            ]
        ]);

        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client(
            "ssl://{$domain}:{$port}",
            $errno,
            $errstr,
            $timeoutSeconds,
            STREAM_CLIENT_CONNECT,
            $g
        );

        if (!$socket) {
            return [
                'success' => false,
                'domain' => $domain,
                'port' => $port,
                'error' => "Could not connect to {$domain}:{$port} ($errstr)",
                'status' => 'unreachable'
            ];
        }

        $params = stream_context_get_params($socket);
        fclose($socket);

        if (empty($params['options']['ssl']['peer_certificate'])) {
            return [
                'success' => false,
                'domain' => $domain,
                'port' => $port,
                'error' => 'No SSL certificate presented by remote host',
                'status' => 'no_cert'
            ];
        }

        $cert = openssl_x509_parse($params['options']['ssl']['peer_certificate']);
        if (!$cert) {
            return [
                'success' => false,
                'domain' => $domain,
                'port' => $port,
                'error' => 'Failed to parse SSL certificate',
                'status' => 'invalid_cert'
            ];
        }

        $validFrom = $cert['validFrom_time_t'] ?? 0;
        $validTo = $cert['validTo_time_t'] ?? 0;
        $now = time();
        $daysRemaining = (int)floor(($validTo - $now) / 86400);

        // Subject & SANs
        $cn = $cert['subject']['CN'] ?? ($cert['subject']['commonName'] ?? $domain);
        $issuer = $cert['issuer']['O'] ?? ($cert['issuer']['CN'] ?? 'Unknown Issuer');
        $sans = [];
        if (!empty($cert['extensions']['subjectAltName'])) {
            $rawSans = explode(',', $cert['extensions']['subjectAltName']);
            foreach ($rawSans as $s) {
                $sans[] = trim(str_replace('DNS:', '', trim($s)));
            }
        }

        $status = 'valid';
        if ($daysRemaining < 0) {
            $status = 'expired';
        } elseif ($daysRemaining <= 7) {
            $status = 'critical';
        } elseif ($daysRemaining <= 30) {
            $status = 'expiring_soon';
        }

        return [
            'success' => true,
            'domain' => $domain,
            'port' => $port,
            'common_name' => $cn,
            'issuer' => $issuer,
            'sans' => $sans,
            'valid_from' => date('Y-m-d H:i:s', $validFrom),
            'valid_to' => date('Y-m-d H:i:s', $validTo),
            'days_remaining' => $daysRemaining,
            'is_expired' => $daysRemaining < 0,
            'status' => $status,
            'signature_type' => $cert['signatureTypeLN'] ?? ($cert['signatureTypeSN'] ?? 'RSA-SHA256')
        ];
    }
}
