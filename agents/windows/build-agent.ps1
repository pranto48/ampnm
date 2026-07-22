<#
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License...
 * (Commercial licenses available at https://ampnm.itsupport.com.bd/pricing)
#>
# build-agent.ps1
# Compiles the Go Windows Telemetry Agent and generates the MSI installer
# Run from this folder: .\build-agent.ps1

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

Write-Host ""
Write-Host "+------------------------------------------------------+"
Write-Host "|   AMPNM Windows Telemetry Agent - Build & Package    |"
Write-Host "+------------------------------------------------------+"
Write-Host ""

# Locate WiX Toolset — prefer local bundled binaries, fall back to system install
$WixFound = $false

# 1. Check for bundled WiX binaries next to this script (build\wix\candle.exe)
$LocalWix = Join-Path $PSScriptRoot "build\wix\candle.exe"
if (Test-Path $LocalWix) {
    $wixPath = Split-Path $LocalWix -Parent
    if ($env:PATH -notlike "*$wixPath*") {
        $env:PATH = "$wixPath;$env:PATH"
    }
    $CandleFile = Get-Item $LocalWix
    $WixFound = $true
    Write-Host "[info] Using bundled WiX binaries: $wixPath" -ForegroundColor Green
}

# 2. Fall back: scan Program Files
if (-not $WixFound) {
    $CandleFile = Get-ChildItem -Path "C:\Program Files*" -Filter "candle.exe" -Recurse -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($CandleFile) {
        $wixPath = $CandleFile.DirectoryName
        if ($env:PATH -notlike "*$wixPath*") {
            $env:PATH = "$wixPath;$env:PATH"
        }
        $WixFound = $true
        Write-Host "[info] Auto-detected WiX Toolset: $wixPath" -ForegroundColor Green
    }
}

# 3. Fall back: already on PATH
if (-not $WixFound) {
    try {
        $null = Get-Command candle -ErrorAction Stop
        $CandleFile = $null
        $WixFound = $true
    } catch { }
}

if (-not $WixFound) {
    Write-Host "[warning] WiX Toolset not found. MSI packaging will be skipped." -ForegroundColor Yellow
}



# 1. Create build directory
$BuildDir = Join-Path $PSScriptRoot "build"
if (-not (Test-Path $BuildDir)) {
    New-Item -ItemType Directory -Path $BuildDir | Out-Null
    Write-Host "[info] Created build directory: $BuildDir" -ForegroundColor Green
}

# 2. Convert project license to RTF format
Write-Host "[info] Converting license text to RTF format..." -ForegroundColor Yellow
$LicenseLines = @(
    "AMPNM Windows Telemetry Agent License",
    "Copyright (c) 2026 IT Support BD",
    "",
    "Permission is hereby granted, free of charge, to any person obtaining a copy",
    "of this software and associated documentation files (the 'Software'), to deal",
    "in the Software without restriction, including without limitation the rights",
    "to use, copy, modify, merge, publish, distribute, sublicense, and/or sell",
    "copies of the Software, and to permit persons to whom the Software is",
    "furnished to do so, subject to the following conditions:",
    "",
    "The above copyright notice and this permission notice shall be included in all",
    "copies or substantial portions of the Software.",
    "",
    "THE SOFTWARE IS PROVIDED 'AS IS', WITHOUT WARRANTY OF ANY KIND, EXPRESS OR",
    "IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,",
    "FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE",
    "AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER",
    "LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,",
    "OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE",
    "SOFTWARE."
)
$LicenseText = $LicenseLines -join "`n"

$RtfContent = "{\rtf1\ansi\deff0{\fonttbl{\f0\fnil\fcharset0 Arial;}}\f0\fs20 "
foreach ($line in $LicenseText -split "`n") {
    $RtfContent += $line.Trim() + "\line "
}
$RtfContent += "}"
$RtfPath = Join-Path $PSScriptRoot "LICENSE.rtf"
$RtfContent | Out-File -FilePath $RtfPath -Encoding ascii
Write-Host "[ok] Generated LICENSE.rtf" -ForegroundColor Green

# 3. Compiling Go application
Write-Host "[info] Checking Go compiler..." -ForegroundColor Yellow
$GoAvailable = $false
try {
    $goVer = go version 2>&1
    Write-Host "  [ok] Found Go: $goVer" -ForegroundColor Green
    $GoAvailable = $true
} catch {
    Write-Host "  [warning] Go compiler not found on PATH." -ForegroundColor Yellow
}

if ($GoAvailable) {
    Write-Host "[info] Compiling Go Windows Telemetry Agent..." -ForegroundColor Yellow
    $env:GOOS = "windows"
    $env:GOARCH = "amd64"
    $ExePath = Join-Path $BuildDir "ampnm-agent.exe"
    
    # Run optimized compilation (with windowsgui subsystem to prevent console window popup)
    go build -ldflags="-w -s -H=windowsgui" -o $ExePath .
    
    if (Test-Path $ExePath) {
        Write-Host "  [ok] Go agent compiled successfully: $ExePath" -ForegroundColor Green
    } else {
        Write-Error "Go agent compilation failed. Executable not found."
    }
} else {
    Write-Host "  Skipping compilation step. Run this script in an environment with Go installed to compile." -ForegroundColor Yellow
}

# 4. Packaging with go-msi
Write-Host "[info] Checking go-msi compiler..." -ForegroundColor Yellow
$MsiAvailable = $false
try {
    # Check if go-msi is installed
    $null = Get-Command go-msi -ErrorAction Stop
    $MsiAvailable = $true
    Write-Host "  [ok] Found go-msi" -ForegroundColor Green
} catch {
    Write-Host "  [warning] go-msi not found on PATH." -ForegroundColor Yellow
}

if ($MsiAvailable -and $GoAvailable -and $WixFound) {
    Write-Host "[info] Compiling MSI package using go-msi + WiX..." -ForegroundColor Yellow
    $MsiPath = Join-Path $BuildDir "ampnm-agent-setup.msi"

    $TmpDir = Join-Path $BuildDir "tmp"
    if (-not (Test-Path $TmpDir)) {
        New-Item -ItemType Directory -Path $TmpDir | Out-Null
    }
    Copy-Item -Path $RtfPath -Destination $TmpDir -Force
    Copy-Item -Path $RtfPath -Destination $BuildDir -Force

    # Step 1: Let go-msi generate the .wxs template files and build.bat (but it will fail on candle/light)
    Write-Host "  [info] Generating WiX templates via go-msi..." -ForegroundColor Yellow
    go-msi make --path wix.json --msi $MsiPath --out $TmpDir --keep 2>&1 | Out-Null

    # Step 2: Patch build.bat — replace bare 'candle'/'light' calls with full absolute paths
    $BatchFile = Join-Path $TmpDir "build.bat"
    if (Test-Path $BatchFile) {
        $wixBin = Split-Path $LocalWix -Parent
        if (-not $wixBin -and $CandleFile) { $wixBin = $CandleFile.DirectoryName }
        $candleExe = Join-Path $wixBin "candle.exe"
        $lightExe  = Join-Path $wixBin "light.exe"

        $batContent = Get-Content $BatchFile -Raw
        $batContent = $batContent -replace '(?m)^candle\b', "`"$candleExe`""
        $batContent = $batContent -replace '(?m)^light\b',  "`"$lightExe`""
        Set-Content -Path $BatchFile -Value $batContent -Encoding ASCII
        Write-Host "  [ok] Patched build.bat with full WiX paths" -ForegroundColor Green

        # Step 3: Run the patched batch file directly
        Push-Location $TmpDir
        cmd.exe /c "build.bat" 2>&1
        Pop-Location
    } else {
        Write-Host "  [warning] build.bat not generated by go-msi." -ForegroundColor Yellow
    }

    if (Test-Path $MsiPath) {
        Write-Host "  [ok] MSI package generated successfully: $MsiPath" -ForegroundColor Green
    } else {
        Write-Host "  [error] MSI package generation failed." -ForegroundColor Red
    }
} elseif ($MsiAvailable -and $GoAvailable -and -not $WixFound) {
    Write-Host "  [warning] WiX Toolset not found - skipping MSI packaging." -ForegroundColor Yellow
    Write-Host "  [hint] Download WiX binaries to: $(Join-Path $PSScriptRoot 'build\wix\')" -ForegroundColor Cyan
} else {
    Write-Host "  Skipping MSI packaging (go-msi or Go not available)." -ForegroundColor Yellow
}

Write-Host ""
Write-Host "Done." -ForegroundColor Green
