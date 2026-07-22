<#
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License...
 * (Commercial licenses available at https://ampnm.itsupport.com.bd/pricing)
#>
# AMPNM - Windows Backup Script
# Works on Windows 11 with PowerShell 5.1+

Clear-Host
Write-Host "=========================================================" -ForegroundColor Cyan
Write-Host "    AMPNM Database Backup System" -ForegroundColor Cyan
Write-Host "=========================================================" -ForegroundColor Cyan
Write-Host ""

# 1. Ensure backups folder exists
if (-not (Test-Path "backups")) {
    Write-Host "→ Creating 'backups' directory..." -ForegroundColor White
    New-Item -ItemType Directory -Path "backups" | Out-Null
    Write-Host "✓ Directory created." -ForegroundColor Green
}

# 2. Check if db container is running
Write-Host "→ Checking database container status..." -ForegroundColor White
$runningServices = @()
try {
    $runningServices = docker compose ps --services --filter "status=running"
} catch {}

if ($runningServices -notcontains "db") {
    Write-Host "✗ Error: The database service is not currently running." -ForegroundColor Red
    Write-Host "  Please start the AMPNM system before performing backups:" -ForegroundColor Yellow
    Write-Host "  PowerShell: .\scripts\windows-install.ps1" -ForegroundColor Yellow
    exit 1
}
Write-Host "✓ Database container is active." -ForegroundColor Green

# 3. Perform database dump
$timestamp = Get-Date -Format "yyyy-MM-dd-HH-mm"
$backupFile = "backups/ampnm-backup-$timestamp.sql"
Write-Host "→ Generating database backup file: $backupFile..." -ForegroundColor White

# We use cmd.exe /c to stream the output directly to the file at OS level.
# This prevents PowerShell from converting the stream to UTF-16 strings, 
# keeping the file UTF-8 and avoiding corruption.
$cmd = 'docker compose exec -T db sh -c "mysqldump -uroot -p`$MYSQL_ROOT_PASSWORD `$MYSQL_DATABASE"'
$process = Start-Process -FilePath "cmd.exe" -ArgumentList "/c $cmd > `"$backupFile`"" -NoNewWindow -PassThru -Wait

# 4. Verify Success
if ($process.ExitCode -eq 0 -and (Test-Path $backupFile) -and (Get-Item $backupFile).Length -gt 100) {
    $fileSize = [Math]::Round((Get-Item $backupFile).Length / 1KB, 2)
    Write-Host ""
    Write-Host "=========================================================" -ForegroundColor Green
    Write-Host "🎉 SUCCESS: Database backup completed!" -ForegroundColor Green
    Write-Host "  File: $backupFile" -ForegroundColor Green
    Write-Host "  Size: $fileSize KB" -ForegroundColor Green
    Write-Host "=========================================================" -ForegroundColor Green
} else {
    Write-Host ""
    Write-Host "✗ Error: Backup failed during database dump execution." -ForegroundColor Red
    if (Test-Path $backupFile) {
        Remove-Item $backupFile -Force | Out-Null
    }
    exit 1
}
Write-Host ""
