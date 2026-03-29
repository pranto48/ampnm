<?php

function smtpReadResponse($socket): string {
    $response = '';
    while (!feof($socket)) {
        $line = fgets($socket, 515);
        if ($line === false) break;
        $response .= $line;
        if (strlen($line) >= 4 && $line[3] === ' ') break;
    }
    return $response;
}

function smtpExpectCode($socket, array $validCodes, ?string &$error = null): bool {
    $response = smtpReadResponse($socket);
    $code = (int)substr(trim($response), 0, 3);
    if (!in_array($code, $validCodes, true)) {
        $error = trim($response);
        return false;
    }
    return true;
}

function smtpCommand($socket, string $command, array $validCodes, ?string &$error = null): bool {
    fwrite($socket, $command . "\r\n");
    return smtpExpectCode($socket, $validCodes, $error);
}

function smtp_send_mail(array $settings, string $toEmail, string $subject, string $body, ?string &$error = null): bool {
    $host = trim((string)($settings['host'] ?? ''));
    $port = (int)($settings['port'] ?? 0);
    $username = trim((string)($settings['username'] ?? ''));
    $password = (string)($settings['password'] ?? '');
    $encryption = strtolower(trim((string)($settings['encryption'] ?? 'tls')));
    $fromEmail = trim((string)($settings['from_email'] ?? $username));
    $fromName = trim((string)($settings['from_name'] ?? 'AMPNM'));

    if ($host === '' || $port <= 0 || $username === '' || $password === '' || $fromEmail === '' || $toEmail === '') {
        $error = 'Missing SMTP configuration fields';
        return false;
    }

    $transport = $encryption === 'ssl' ? "ssl://{$host}:{$port}" : "tcp://{$host}:{$port}";
    $socket = @stream_socket_client($transport, $errno, $errstr, 20, STREAM_CLIENT_CONNECT);
    if (!$socket) {
        $error = "SMTP connection failed: {$errstr} ({$errno})";
        return false;
    }

    stream_set_timeout($socket, 20);

    if (!smtpExpectCode($socket, [220], $error)) { fclose($socket); return false; }
    if (!smtpCommand($socket, 'EHLO ampnm.local', [250], $error)) { fclose($socket); return false; }

    if ($encryption === 'tls') {
        if (!smtpCommand($socket, 'STARTTLS', [220], $error)) { fclose($socket); return false; }
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            $error = 'Unable to start TLS encryption';
            fclose($socket);
            return false;
        }
        if (!smtpCommand($socket, 'EHLO ampnm.local', [250], $error)) { fclose($socket); return false; }
    }

    if (!smtpCommand($socket, 'AUTH LOGIN', [334], $error)) { fclose($socket); return false; }
    if (!smtpCommand($socket, base64_encode($username), [334], $error)) { fclose($socket); return false; }
    if (!smtpCommand($socket, base64_encode($password), [235], $error)) { fclose($socket); return false; }

    if (!smtpCommand($socket, "MAIL FROM:<{$fromEmail}>", [250], $error)) { fclose($socket); return false; }
    if (!smtpCommand($socket, "RCPT TO:<{$toEmail}>", [250, 251], $error)) { fclose($socket); return false; }
    if (!smtpCommand($socket, 'DATA', [354], $error)) { fclose($socket); return false; }

    $safeFromName = str_replace(["\r", "\n"], '', $fromName);
    $safeSubject = str_replace(["\r", "\n"], '', $subject);
    $message = "From: {$safeFromName} <{$fromEmail}>\r\n";
    $message .= "To: <{$toEmail}>\r\n";
    $message .= "Subject: {$safeSubject}\r\n";
    $message .= "MIME-Version: 1.0\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $message .= "\r\n{$body}\r\n.";

    fwrite($socket, $message . "\r\n");
    if (!smtpExpectCode($socket, [250], $error)) { fclose($socket); return false; }

    smtpCommand($socket, 'QUIT', [221], $error);
    fclose($socket);
    return true;
}

