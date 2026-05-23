# build-agent-windows.ps1
# Builds the AMPNM Windows Usage Agent installer (MSI + NSIS EXE)
# Run from the repo root: .\scripts\build-agent-windows.ps1

[CmdletBinding()]
param(
    [switch]$SkipChecks
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

$AppDir = Join-Path $PSScriptRoot ".." "apps" "windows-agent"
$AppDir = (Resolve-Path $AppDir).Path

Write-Host ""
Write-Host "╔══════════════════════════════════════════════════════╗" -ForegroundColor Cyan
Write-Host "║   AMPNM Windows Usage Agent — Production Build       ║" -ForegroundColor Cyan
Write-Host "╚══════════════════════════════════════════════════════╝" -ForegroundColor Cyan
Write-Host ""

# ── Toolchain checks ──────────────────────────────────────────────────
if (-not $SkipChecks) {
    Write-Host "▶ Checking toolchain..." -ForegroundColor Yellow

    # Node.js
    try { $nodeVer = node --version 2>&1; Write-Host "  ✓ Node.js $nodeVer" -ForegroundColor Green }
    catch { Write-Error "Node.js not found. Install from https://nodejs.org" }

    # npm
    try { $npmVer = npm --version 2>&1; Write-Host "  ✓ npm $npmVer" -ForegroundColor Green }
    catch { Write-Error "npm not found." }

    # Rust / cargo
    try { $rustVer = rustc --version 2>&1; Write-Host "  ✓ $rustVer" -ForegroundColor Green }
    catch { Write-Error "Rust not found. Install from https://rustup.rs" }

    # Tauri CLI
    try {
        $tauriVer = & cargo tauri --version 2>&1
        Write-Host "  ✓ $tauriVer" -ForegroundColor Green
    } catch {
        Write-Host "  ⚠ Tauri CLI not found — installing..." -ForegroundColor Yellow
        cargo install tauri-cli --version "^2"
    }

    Write-Host ""
}

# ── Install frontend dependencies ─────────────────────────────────────
Write-Host "▶ Installing npm dependencies..." -ForegroundColor Yellow
Set-Location $AppDir
npm install --prefer-offline
Write-Host "  ✓ Dependencies installed" -ForegroundColor Green
Write-Host ""

# ── Tauri build (MSI + NSIS) ──────────────────────────────────────────
Write-Host "▶ Building Tauri app (MSI + NSIS EXE)..." -ForegroundColor Yellow
Write-Host "  This may take several minutes on first build." -ForegroundColor DarkGray
Write-Host ""

cargo tauri build

# ── Locate output files ───────────────────────────────────────────────
$BundleDir = Join-Path $AppDir "src-tauri" "target" "release" "bundle"

Write-Host ""
Write-Host "▶ Build complete! Installer files:" -ForegroundColor Green

Get-ChildItem -Recurse $BundleDir -Include "*.msi","*.exe","*.nsis" | ForEach-Object {
    Write-Host "  📦 $($_.FullName)" -ForegroundColor Cyan
}

Write-Host ""
Write-Host "Done. Distribute any of the above installers to your Windows users." -ForegroundColor Green
