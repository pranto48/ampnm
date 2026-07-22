<#
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License...
 * (Commercial licenses available at https://ampnm.itsupport.com.bd/pricing)
#>
# AMPNM - Build and Push Docker Image
Clear-Host
Write-Host "=========================================================" -ForegroundColor Cyan
Write-Host "    Building and Pushing AMPNM to Docker Hub..." -ForegroundColor Cyan
Write-Host "=========================================================" -ForegroundColor Cyan
Write-Host ""

# 1. Build image locally
Write-Host "→ Building local image ampnm-app:latest..." -ForegroundColor White
# Using BuildKit=0 environment variable if standard buildkit fails
$env:DOCKER_BUILDKIT=0
docker compose build --no-cache
if ($LASTEXITCODE -ne 0) {
    Write-Host "✗ Error: Docker build failed." -ForegroundColor Red
    exit 1
}
Write-Host "✓ Local image built successfully." -ForegroundColor Green

# 2. Tag image
Write-Host "→ Tagging image as arifmahmudpranto/ampnm:latest..." -ForegroundColor White
docker tag ampnm-app:latest arifmahmudpranto/ampnm:latest
if ($LASTEXITCODE -ne 0) {
    Write-Host "✗ Error: Failed to tag image." -ForegroundColor Red
    exit 1
}
Write-Host "✓ Image tagged successfully." -ForegroundColor Green

# 3. Push to Docker Hub
Write-Host "→ Pushing image arifmahmudpranto/ampnm:latest to Docker Hub..." -ForegroundColor White
docker push arifmahmudpranto/ampnm:latest
if ($LASTEXITCODE -ne 0) {
    Write-Host "✗ Error: Failed to push to Docker Hub. Make sure you are logged in via 'docker login'." -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "=========================================================" -ForegroundColor Green
Write-Host "🎉 SUCCESS: Image pushed to arifmahmudpranto/ampnm:latest" -ForegroundColor Green
Write-Host "=========================================================" -ForegroundColor Green
Write-Host ""
