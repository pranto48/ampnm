# AMPNM Desktop Windows App Build Script
# IT Support BD

Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "  Building AMPNM Desktop Windows App" -ForegroundColor Cyan
Write-Host "==========================================" -ForegroundColor Cyan

$AppDir = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $AppDir

Write-Host "`n[1/3] Resolving Flutter packages..." -ForegroundColor Yellow
flutter pub get
if ($LASTEXITCODE -ne 0) {
    Write-Host "Error: flutter pub get failed!" -ForegroundColor Red
    exit 1
}

Write-Host "`n[2/3] Compiling Release Windows Application..." -ForegroundColor Yellow
flutter build windows --release
if ($LASTEXITCODE -ne 0) {
    Write-Host "Error: flutter build windows failed!" -ForegroundColor Red
    exit 1
}

$ReleaseDir = Join-Path $AppDir "build\windows\x64\runner\Release"
$DistDir = Join-Path $AppDir "dist\AMPNM-Desktop"

Write-Host "`n[3/3] Packaging Standalone Distribution..." -ForegroundColor Yellow
if (Test-Path $DistDir) {
    Remove-Item -Path $DistDir -Recurse -Force
}
New-Item -ItemType Directory -Path $DistDir -Force | Out-Null
Copy-Item -Path "$ReleaseDir\*" -Destination $DistDir -Recurse -Force

# Create ZIP archive
$ZipPath = Join-Path $AppDir "dist\AMPNM-Desktop-Windows-x64.zip"
if (Test-Path $ZipPath) {
    Remove-Item -Path $ZipPath -Force
}
Compress-Archive -Path "$DistDir\*" -DestinationPath $ZipPath -Force

Write-Host "`n==========================================" -ForegroundColor Green
Write-Host "  BUILD SUCCESSFUL!" -ForegroundColor Green
Write-Host "  Standalone Folder: $DistDir" -ForegroundColor Green
Write-Host "  ZIP Package: $ZipPath" -ForegroundColor Green
Write-Host "  Executable: $DistDir\ampnm_app.exe" -ForegroundColor Green
Write-Host "==========================================" -ForegroundColor Green
