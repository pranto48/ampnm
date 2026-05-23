<?php

function getUpdateStatePath(): string
{
    return getenv('AMPNM_UPDATE_STATE_FILE') ?: (__DIR__ . '/../storage/update_state.json');
}

function readUpdateStateFile(?string $path = null): array
{
    $targetPath = $path ?: getUpdateStatePath();
    if (!is_file($targetPath)) {
        return [];
    }

    $raw = file_get_contents($targetPath);
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function getFormattedVersion(string $repoPath, string $ref): string
{
    $refEscaped = escapeshellarg($ref);
    $pathEscaped = escapeshellarg($repoPath);
    $commitCount = trim(shell_exec("cd {$pathEscaped} && git rev-list --count {$refEscaped} 2>/dev/null") ?? '');
    $commitHash = trim(shell_exec("cd {$pathEscaped} && git rev-parse --short {$refEscaped} 2>/dev/null") ?? '');
    
    if (is_numeric($commitCount) && $commitCount !== '') {
        $version = 'V' . sprintf("%.2f", (int)$commitCount / 100);
        if ($commitHash !== '') {
            $version .= ' (' . $commitHash . ')';
        }
        return $version;
    }
    
    if ($commitHash !== '' && !str_starts_with($commitHash, 'fatal:')) {
        return $commitHash;
    }
    
    return 'unknown';
}

