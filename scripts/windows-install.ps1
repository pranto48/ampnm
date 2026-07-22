<#
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License...
 * (Commercial licenses available at https://ampnm.itsupport.com.bd/pricing)
#>
# AMPNM - Windows Installer Script
# Works on Windows 11 with PowerShell 5.1+

Clear-Host
Write-Host "=========================================================" -ForegroundColor Cyan
Write-Host "    AMPNM Local Windows Installer (Docker-First)" -ForegroundColor Cyan
Write-Host "=========================================================" -ForegroundColor Cyan
Write-Host ""

# 1. Check if Docker is installed
Write-Host "→ Checking if Docker is installed..." -ForegroundColor White
$dockerVer = $null
try {
    $dockerVer = & docker --version *>$null
} catch {}

if ($LASTEXITCODE -ne 0 -or $null -eq $dockerVer) {
    Write-Host "✗ Error: Docker is not installed or not in PATH." -ForegroundColor Red
    Write-Host "  Please install Docker Desktop for Windows from:" -ForegroundColor Yellow
    Write-Host "  https://www.docker.com/products/docker-desktop/" -ForegroundColor Yellow
    exit 1
}
Write-Host "✓ Docker version: $(docker --version)" -ForegroundColor Green

# 2. Check if Docker Compose works
Write-Host "→ Checking if Docker Compose is available..." -ForegroundColor White
$composeVer = $null
try {
    $composeVer = & docker compose version *>$null
} catch {}

if ($LASTEXITCODE -ne 0 -or $null -eq $composeVer) {
    Write-Host "✗ Error: Docker Compose is not working." -ForegroundColor Red
    Write-Host "  Please ensure Docker Desktop is properly installed." -ForegroundColor Yellow
    exit 1
}
Write-Host "✓ Docker Compose version: $(docker compose version)" -ForegroundColor Green

# 3. Check if Docker Desktop engine is running
Write-Host "→ Checking if Docker engine is running..." -ForegroundColor White
$dockerInfo = $null
try {
    # Run docker info and capture error/exit code
    $dockerInfo = & docker info 2>&1
} catch {}

if ($LASTEXITCODE -ne 0 -or $dockerInfo -match "error during connect") {
    Write-Host ""
    Write-Host "┌────────────────────────────────────────────────────────┐" -ForegroundColor Yellow
    Write-Host "│ Please start Docker Desktop, wait until it is running,  │" -ForegroundColor Yellow
    Write-Host "│ then run this script again.                            │" -ForegroundColor Yellow
    Write-Host "└────────────────────────────────────────────────────────┘" -ForegroundColor Yellow
    Write-Host ""
    exit 1
}
Write-Host "✓ Docker engine is running." -ForegroundColor Green

# 4. Copy .env.example to .env if it doesn't exist
if (-not (Test-Path ".env")) {
    Write-Host "→ Copying .env.example to .env..." -ForegroundColor White
    Copy-Item ".env.example" ".env"
    Write-Host "✓ Created .env file. You can customize settings in it." -ForegroundColor Green
} else {
    Write-Host "✓ Existing .env file found." -ForegroundColor Cyan
}

# 5. Parse APACHE_PORT from .env if customized
$port = "2266"
if (Test-Path ".env") {
    $envContent = Get-Content ".env"
    foreach ($line in $envContent) {
        if ($line -match "^APACHE_PORT=(\d+)$") {
            $port = $Matches[1].Trim()
        }
    }
}

# 6. Start AMPNM services
Write-Host "→ Pulling latest images..." -ForegroundColor White
docker compose pull

Write-Host "→ Building and starting container services..." -ForegroundColor White
docker compose up --build --progress=plain -d

if ($LASTEXITCODE -ne 0) {
    Write-Host "✗ Error: Docker Compose failed to start services." -ForegroundColor Red
    exit 1
}

# 7. Final Success Messages
Write-Host ""
Write-Host "=========================================================" -ForegroundColor Green
Write-Host "🎉 SUCCESS: AMPNM is running at http://localhost:$port" -ForegroundColor Green
Write-Host "=========================================================" -ForegroundColor Green
Write-Host "Default Login:" -ForegroundColor White
Write-Host "  Username: admin" -ForegroundColor Yellow
Write-Host "  Password: password" -ForegroundColor Yellow
Write-Host ""
Write-Host "⚠️  IMPORTANT:" -ForegroundColor Yellow
Write-Host "  Please change the default admin password (ADMIN_PASSWORD)" -ForegroundColor Yellow
Write-Host "  and database passwords inside your '.env' file for security," -ForegroundColor Yellow
Write-Host "  then run this script again to apply those changes." -ForegroundColor Yellow
Write-Host "=========================================================" -ForegroundColor Green
Write-Host ""
