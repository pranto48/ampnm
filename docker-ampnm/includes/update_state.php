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
