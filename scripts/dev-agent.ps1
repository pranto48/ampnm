# dev-agent.ps1
# Starts the AMPNM Windows Usage Agent in hot-reload development mode
# Run from the repo root: .\scripts\dev-agent.ps1

[CmdletBinding()]
param(
    [string]$ServerUrl = "http://192.168.20.5:2266",
    [switch]$SkipChecks
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

$AppDir = Join-Path $PSScriptRoot ".." "apps" "windows-agent"
$AppDir = (Resolve-Path $AppDir).Path

Write-Host ""
Write-Host "╔══════════════════════════════════════════════════════╗" -ForegroundColor Magenta
Write-Host "║   AMPNM Windows Usage Agent — Dev Mode               ║" -ForegroundColor Magenta
Write-Host "╚══════════════════════════════════════════════════════╝" -ForegroundColor Magenta
Write-Host ""
Write-Host "  Server URL : $ServerUrl" -ForegroundColor DarkGray
Write-Host "  App Dir    : $AppDir" -ForegroundColor DarkGray
Write-Host ""

# ── Quick toolchain check ─────────────────────────────────────────────
if (-not $SkipChecks) {
    Write-Host "▶ Checking toolchain..." -ForegroundColor Yellow

    foreach ($cmd in @("node", "npm", "rustc", "cargo")) {
        try {
            $ver = & $cmd --version 2>&1
            Write-Host "  ✓ $cmd $ver" -ForegroundColor Green
        } catch {
            Write-Error "$cmd not found. Please install it and re-run."
        }
    }
    Write-Host ""
}

# ── Install dependencies if missing ───────────────────────────────────
$NodeModules = Join-Path $AppDir "node_modules"
if (-not (Test-Path $NodeModules)) {
    Write-Host "▶ Installing npm dependencies (first run)..." -ForegroundColor Yellow
    Set-Location $AppDir
    npm install
    Write-Host "  ✓ Done" -ForegroundColor Green
    Write-Host ""
}

# ── Inject dev server URL via env var ────────────────────────────────
$env:AMPNM_DEV_SERVER_URL = $ServerUrl
Write-Host "▶ Starting Tauri dev server (hot-reload)..." -ForegroundColor Yellow
Write-Host "  Press Ctrl+C to stop." -ForegroundColor DarkGray
Write-Host ""

Set-Location $AppDir
cargo tauri dev
