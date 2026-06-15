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
    
    # Run optimized compilation
    go build -ldflags="-w -s" -o $ExePath main.go
    
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

if ($MsiAvailable -and $GoAvailable) {
    Write-Host "[info] Compiling MSI package using go-msi make..." -ForegroundColor Yellow
    $MsiPath = Join-Path $BuildDir "ampnm-agent-setup.msi"
    
    # Generate the installer
    go-msi make --path wix.json --binary build/ampnm-agent.exe --output $MsiPath
    
    if (Test-Path $MsiPath) {
        Write-Host "  [ok] MSI package generated successfully: $MsiPath" -ForegroundColor Green
    } else {
        Write-Host "  [warning] MSI package generation failed." -ForegroundColor Red
    }
} else {
    Write-Host "  Skipping MSI installer packaging. Run this script with both Go and go-msi installed to bundle the installer." -ForegroundColor Yellow
}

Write-Host ""
Write-Host "Done." -ForegroundColor Green
