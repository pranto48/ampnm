<#
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License...
 * (Commercial licenses available at https://ampnm.itsupport.com.bd/pricing)
#>
# AMPNM - Windows Update Script
# Works on Windows 11 with PowerShell 5.1+

Clear-Host
Write-Host "=========================================================" -ForegroundColor Cyan
Write-Host "    Updating AMPNM Local Services..." -ForegroundColor Cyan
Write-Host "=========================================================" -ForegroundColor Cyan
Write-Host ""

# 1. Check if git repository
if (-not (Test-Path ".git")) {
    Write-Host "⚠️  Warning: This directory does not appear to be a Git repository." -ForegroundColor Yellow
    Write-Host "  Skipping git pull update step." -ForegroundColor Yellow
} else {
    Write-Host "→ Pulling latest source code via Git..." -ForegroundColor White
    git pull
    if ($LASTEXITCODE -ne 0) {
        Write-Host "✗ Error: git pull failed. Please check your connection or repository state." -ForegroundColor Red
        exit 1
    }
    Write-Host "✓ Source code updated." -ForegroundColor Green
}

# 2. Pull updated Docker images
Write-Host "→ Pulling latest Docker images..." -ForegroundColor White
docker compose pull
if ($LASTEXITCODE -ne 0) {
    Write-Host "✗ Error: docker compose pull failed." -ForegroundColor Red
    exit 1
}

# 3. Build and restart containers
Write-Host "→ Rebuilding and starting updated containers..." -ForegroundColor White
docker compose up --build --progress=plain -d
if ($LASTEXITCODE -ne 0) {
    Write-Host "✗ Error: Failed to restart containers." -ForegroundColor Red
    exit 1
}

# 4. Get customized port from .env
$port = "2266"
if (Test-Path ".env") {
    $envContent = Get-Content ".env"
    foreach ($line in $envContent) {
        if ($line -match "^APACHE_PORT=(\d+)$") {
            $port = $Matches[1].Trim()
        }
    }
}

# 5. Success messaging
Write-Host ""
Write-Host "=========================================================" -ForegroundColor Green
Write-Host "🎉 UPDATE COMPLETE: AMPNM is running at http://localhost:$port" -ForegroundColor Green
Write-Host "=========================================================" -ForegroundColor Green
Write-Host ""
