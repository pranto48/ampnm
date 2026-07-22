<#
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License...
 * (Commercial licenses available at https://ampnm.itsupport.com.bd/pricing)
#>
# AMPNM - Windows Stop Script
# Works on Windows 11 with PowerShell 5.1+

Clear-Host
Write-Host "=========================================================" -ForegroundColor Yellow
Write-Host "    Stopping AMPNM Local Services..." -ForegroundColor Yellow
Write-Host "=========================================================" -ForegroundColor Yellow
Write-Host ""

docker compose down

if ($LASTEXITCODE -eq 0) {
    Write-Host ""
    Write-Host "✓ AMPNM has been successfully stopped." -ForegroundColor Green
} else {
    Write-Host ""
    Write-Host "✗ Error: Failed to stop AMPNM services properly." -ForegroundColor Red
}
Write-Host "=========================================================" -ForegroundColor Yellow
Write-Host ""
