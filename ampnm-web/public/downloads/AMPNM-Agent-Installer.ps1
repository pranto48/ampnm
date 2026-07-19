#Requires -RunAsAdministrator
<#
.SYNOPSIS
    AMPNM Windows Monitoring Agent Installer
    Installs a Windows Service that reports system metrics to the AMPNM Docker server.

.DESCRIPTION
    This script:
    1. Creates the monitoring script
    2. Registers it as a Windows Service using NSSM (Non-Sucking Service Manager)
    3. Configures automatic startup and recovery

.PARAMETER ServerUrl
    The full URL to the AMPNM metrics endpoint (e.g., http://192.168.1.100:2266/api/agent/metrics)

.PARAMETER AgentToken
    The authentication token from the AMPNM Agent Tokens management page

.PARAMETER Interval
    Collection interval in seconds (default: 60)

.PARAMETER Uninstall
    Remove the agent service and files

.EXAMPLE
    .\AMPNM-Agent-Installer.ps1 -ServerUrl "http://192.168.1.100:2266/api/agent/metrics" -AgentToken "your-token-here"

.EXAMPLE
    .\AMPNM-Agent-Installer.ps1 -Uninstall
#>

param(
    [Parameter(Mandatory=$false)]
    [string]$ServerUrl,
    
    [Parameter(Mandatory=$false)]
    [string]$AgentToken,
    
    [Parameter(Mandatory=$false)]
    [int]$Interval = 60,
    
    [Parameter(Mandatory=$false)]
    [switch]$Uninstall
)

$ServiceName = "AMPNM-Agent"
$InstallPath = "$env:ProgramData\AMPNM-Agent"
$LogPath = "$InstallPath\logs"
$ScriptPath = "$InstallPath\AMPNM-Monitor.ps1"
$ConfigPath = "$InstallPath\config.json"
$NssmPath = "$InstallPath\nssm.exe"
$NssmUrls = @(
    "https://github.com/homebridge/nssm/releases/download/2.24-101-g897c7ad/nssm_x64.exe",
    "https://nssm.cc/release/nssm-2.24.zip",
    "https://github.com/nssm/nssm/releases/download/2.24/nssm-2.24.zip"
)
$LocalNssmPath = "$PSScriptRoot\nssm.exe"

function Write-Status {
    param([string]$Message, [string]$Type = "Info")
    $color = switch($Type) {
        "Success" { "Green" }
        "Error" { "Red" }
        "Warning" { "Yellow" }
        default { "Cyan" }
    }
    Write-Host "[$Type] $Message" -ForegroundColor $color
}

function Install-NSSM {
    if (Test-Path $NssmPath) {
        Write-Status "NSSM already installed" "Success"
        return $true
    }

    if (Test-Path $LocalNssmPath) {
        Copy-Item $LocalNssmPath $NssmPath -Force
        Write-Status "NSSM copied from local installer bundle" "Success"
        return $true
    }

    Write-Status "NSSM not found locally. Trying online download..." "Warning"
    $zipPath = "$InstallPath\nssm.zip"
    [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

    foreach ($nssmUrl in $NssmUrls) {
        try {
            Write-Status "Trying NSSM source: $nssmUrl"
            if ($nssmUrl -like "*.exe") {
                Invoke-WebRequest -Uri $nssmUrl -OutFile $NssmPath -UseBasicParsing
                if (Test-Path $NssmPath) {
                    Write-Status "NSSM installed successfully from direct executable" "Success"
                    return $true
                }
                throw "Downloaded direct executable but file was not found at $NssmPath"
            }

            Invoke-WebRequest -Uri $nssmUrl -OutFile $zipPath -UseBasicParsing

            Expand-Archive -Path $zipPath -DestinationPath "$InstallPath\nssm-temp" -Force

            $arch = if ([Environment]::Is64BitOperatingSystem) { "win64" } else { "win32" }
            $nssmExe = Get-ChildItem -Path "$InstallPath\nssm-temp" -Recurse -Filter "nssm.exe" |
                       Where-Object { $_.FullName -like "*$arch*" } |
                       Select-Object -First 1

            if ($nssmExe) {
                Copy-Item $nssmExe.FullName $NssmPath -Force
                Write-Status "NSSM installed successfully" "Success"
                Remove-Item $zipPath -Force -ErrorAction SilentlyContinue
                Remove-Item "$InstallPath\nssm-temp" -Recurse -Force -ErrorAction SilentlyContinue
                return $true
            }

            throw "Downloaded archive but could not find nssm.exe"
        }
        catch {
            Write-Status "NSSM source failed: $nssmUrl ($($_.Exception.Message))" "Warning"
            Remove-Item $zipPath -Force -ErrorAction SilentlyContinue
            Remove-Item "$InstallPath\nssm-temp" -Recurse -Force -ErrorAction SilentlyContinue
        }
    }

    Write-Status "Falling back to native Windows service installation (New-Service)." "Warning"
    return $false
}

function Create-MonitorScript {
    $scriptContent = @'
# AMPNM Windows Monitoring Agent
# This script collects system metrics and sends them to the AMPNM server

param(
    [string]$ConfigPath = "$env:ProgramData\AMPNM-Agent\config.json"
)

# Load configuration
$config = Get-Content $ConfigPath | ConvertFrom-Json
$ServerUrl = $config.ServerUrl
$AgentToken = $config.AgentToken
$Interval = $config.Interval
$CurrentInterval = [math]::Max(15, [int]$Interval)
$LogDir = "$env:ProgramData\AMPNM-Agent\logs"
$LogFile = "$LogDir\agent.log"

if (-not (Test-Path $LogDir)) { New-Item -ItemType Directory -Path $LogDir -Force | Out-Null }

function Write-Log {
    param([string]$Message)
    $line = "$(Get-Date -Format yyyy-MM-dd HH:mm:ss) - $Message"
    Add-Content -Path $LogFile -Value $line
}

function Get-SystemMetrics {
    $metrics = @{
        host_name = $env:COMPUTERNAME
        host_ip = $null
        cpu_percent = $null
        memory_percent = $null
        memory_total_gb = $null
        memory_free_gb = $null
        disk_total_gb = $null
        disk_free_gb = $null
        network_in_mbps = $null
        network_out_mbps = $null
        gpu_percent = $null
    }
    
    try {
        # Get primary IP address
        $ip = Get-NetIPAddress -AddressFamily IPv4 | 
              Where-Object { $_.IPAddress -notlike '169.*' -and $_.IPAddress -ne '127.0.0.1' -and $_.PrefixOrigin -ne 'WellKnown' } |
              Select-Object -First 1 -ExpandProperty IPAddress
        $metrics.host_ip = $ip
    } catch { }
    
    try {
        # CPU Usage (average over 5 samples with 500ms sleep = 2.5s window)
        $samples = @()
        for ($i = 0; $i -lt 5; $i++) {
            $cpuSample = Get-Counter '\Processor(_Total)\% Processor Time' -ErrorAction SilentlyContinue
            if ($cpuSample.CounterSamples) {
                $samples += $cpuSample.CounterSamples[0].CookedValue
            }
            Start-Sleep -Milliseconds 500
        }
        if ($samples.Count -gt 0) {
            $cpuAvg = ($samples | Measure-Object -Average).Average
            $metrics.cpu_percent = [math]::Round($cpuAvg, 2)
        } else {
            $metrics.cpu_percent = 0.0
        }
    } catch { }
    
    try {
        # Memory
        $os = Get-CimInstance Win32_OperatingSystem
        $totalMemGB = [math]::Round($os.TotalVisibleMemorySize / 1MB, 2)
        $freeMemGB = [math]::Round($os.FreePhysicalMemory / 1MB, 2)
        $metrics.memory_total_gb = $totalMemGB
        $metrics.memory_free_gb = $freeMemGB
        if ($totalMemGB -gt 0) {
            $metrics.memory_percent = [math]::Round((1 - ($freeMemGB / $totalMemGB)) * 100, 2)
        }
    } catch { }
    
    try {
        # Disk (aggregated local fixed drives)
        $disks = Get-CimInstance Win32_LogicalDisk -Filter "DriveType=3"
        $totalSize = 0
        $freeSpace = 0
        foreach ($d in $disks) {
            if ($d.Size -gt 0) {
                $totalSize += $d.Size
                $freeSpace += $d.FreeSpace
            }
        }
        if ($totalSize -gt 0) {
            $metrics.disk_total_gb = [math]::Round($totalSize / 1GB, 2)
            $metrics.disk_free_gb = [math]::Round($freeSpace / 1GB, 2)
        }
    } catch { }
    
    try {
        # Network throughput
        $net1 = Get-NetAdapterStatistics | Select-Object -First 1
        Start-Sleep -Milliseconds 500
        $net2 = Get-NetAdapterStatistics | Select-Object -First 1
        
        if ($net1 -and $net2) {
            $metrics.network_in_mbps = [math]::Round((($net2.ReceivedBytes - $net1.ReceivedBytes) * 8 * 2) / 1MB, 2)
            $metrics.network_out_mbps = [math]::Round((($net2.SentBytes - $net1.SentBytes) * 8 * 2) / 1MB, 2)
        }
    } catch { }
    
    try {
        # GPU (NVIDIA-SMI query fallback to performance counters)
        $gpuPercent = $null
        if (Get-Command nvidia-smi -ErrorAction SilentlyContinue) {
            $gpuRaw = nvidia-smi --query-gpu=utilization.gpu --format=csv,noheader,nounits 2>$null
            $parsedVal = 0.0
            if ($gpuRaw -and [double]::TryParse($gpuRaw.Trim(), [ref]$parsedVal)) {
                $gpuPercent = $parsedVal
            }
        }
        if ($gpuPercent -eq $null) {
            $gpuCounters = Get-Counter -Counter '\GPU Engine(*engtype_3D)\Utilization Percentage' -ErrorAction SilentlyContinue
            if ($gpuCounters.CounterSamples) {
                $gpuAvg = ($gpuCounters.CounterSamples | Measure-Object CookedValue -Average).Average
                $gpuPercent = [math]::Round($gpuAvg, 2)
            }
        }
        $metrics.gpu_percent = $gpuPercent
    } catch { }
    
    return $metrics
}

function Send-Metrics {
    param($Metrics)
    
    try {
        $headers = @{
            'Content-Type' = 'application/json'
            'X-Agent-Token' = $AgentToken
        }
        
        $body = $Metrics | ConvertTo-Json -Compress
        
        $response = Invoke-RestMethod -Uri $ServerUrl -Method Post -Headers $headers -Body $body -TimeoutSec 30
        
        if ($response.success) {
            $deviceMatched = $response.device_matched
            if (-not $deviceMatched) {
                $deviceMatched = 'Not linked'
            }
            if ($response.sync_interval_seconds) {
                $suggested = [int]$response.sync_interval_seconds
                if ($suggested -ge 15 -and $suggested -le 3600) {
                    $script:CurrentInterval = $suggested
                }
            }
            Write-Log "Metrics sent successfully. Device: $deviceMatched. Next sync: $script:CurrentInterval sec"
        } else {
            Write-Log "Server returned: $($response | ConvertTo-Json -Compress)"
        }
    }
    catch {
        Write-Log "Error sending metrics: $_"
    }
}

function Sync-DeviceMapping {
    param($Metrics)

    if (-not $Metrics.host_ip) { return }

    try {
        $headers = @{ 'X-Agent-Token' = $AgentToken }
        $hostIpEscaped = [uri]::EscapeDataString([string]$Metrics.host_ip)
        $hostNameEscaped = [uri]::EscapeDataString([string]$Metrics.host_name)
        $syncUrl = "$ServerUrl/device-by-ip?host_ip=$hostIpEscaped&host_name=$hostNameEscaped"
        $sync = Invoke-RestMethod -Uri $syncUrl -Method Get -Headers $headers -TimeoutSec 15

        if ($sync.sync_interval_seconds) {
            $suggested = [int]$sync.sync_interval_seconds
            if ($suggested -ge 15 -and $suggested -le 3600) {
                $script:CurrentInterval = $suggested
            }
        }
    } catch {
        Write-Log "Device sync warning: $_"
    }
}

# Main loop
Write-Log "AMPNM Agent started. Collecting metrics every $Interval seconds..."
Write-Log "Server: $ServerUrl"

while ($true) {
    try {
        $metrics = Get-SystemMetrics
        Sync-DeviceMapping -Metrics $metrics
        Send-Metrics -Metrics $metrics
    }
    catch {
        Write-Log "Collection error: $_"
    }
    
    Start-Sleep -Seconds $script:CurrentInterval
}
'@
    
    Set-Content -Path $ScriptPath -Value $scriptContent -Force
    Write-Status "Monitor script created at $ScriptPath" "Success"
}

function Create-Config {
    $config = @{
        ServerUrl = $ServerUrl
        AgentToken = $AgentToken
        Interval = $Interval
    }
    
    $config | ConvertTo-Json | Set-Content -Path $ConfigPath -Force
    Write-Status "Configuration saved to $ConfigPath" "Success"
}

function Install-Service {
    $existingService = Get-Service -Name $ServiceName -ErrorAction SilentlyContinue
    if ($existingService) {
        Write-Status "Removing existing service..."
        try { Stop-Service -Name $ServiceName -Force -ErrorAction SilentlyContinue } catch {}
        sc.exe delete $ServiceName | Out-Null
        Start-Sleep -Seconds 2
    }

    $nssmAvailable = Install-NSSM

    if ($nssmAvailable -and (Test-Path $NssmPath)) {
        Write-Status "Installing Windows Service with NSSM..."
        & $NssmPath install $ServiceName powershell.exe "-ExecutionPolicy Bypass -NoProfile -File `"$ScriptPath`" -ConfigPath `"$ConfigPath`""
        & $NssmPath set $ServiceName DisplayName "AMPNM Monitoring Agent"
        & $NssmPath set $ServiceName Description "Sends system metrics to AMPNM network monitor"
        & $NssmPath set $ServiceName Start SERVICE_AUTO_START
        & $NssmPath set $ServiceName AppStdout "$LogPath\agent.log"
        & $NssmPath set $ServiceName AppStderr "$LogPath\agent-error.log"
        & $NssmPath set $ServiceName AppRotateFiles 1
        & $NssmPath set $ServiceName AppRotateBytes 1048576
        & $NssmPath set $ServiceName AppExit Default Restart
        & $NssmPath set $ServiceName AppRestartDelay 10000
        & $NssmPath start $ServiceName
    }
    else {
        Write-Status "Installing Windows Service with native New-Service..."
        $binaryPathName = "`"$PSHOME\powershell.exe`" -ExecutionPolicy Bypass -NoProfile -File `"$ScriptPath`" -ConfigPath `"$ConfigPath`""

        try {
            New-Service -Name $ServiceName -BinaryPathName $binaryPathName -DisplayName "AMPNM Monitoring Agent" -Description "Sends system metrics to AMPNM network monitor" -StartupType Automatic -ErrorAction Stop | Out-Null
            sc.exe failure $ServiceName reset= 86400 actions= restart/10000/restart/10000/restart/10000 | Out-Null
            Start-Service -Name $ServiceName -ErrorAction Stop
        }
        catch {
            Write-Status "Native service install/start failed: $($_.Exception.Message)" "Warning"
        }
    }

    Start-Sleep -Seconds 3
    $service = Get-Service -Name $ServiceName -ErrorAction SilentlyContinue
    if ($service -and $service.Status -eq 'Running') {
        Write-Status "Service installed and running successfully!" "Success"
        return $true
    }

    if ($service) {
        Write-Status "Current service state: $($service.Status)" "Warning"
        try {
            $svcDetail = sc.exe queryex $ServiceName
            if ($svcDetail) {
                Write-Host ($svcDetail | Out-String)
            }
        } catch { }
    }

    Write-Status "Service installed but may not be running. Check logs at $LogPath" "Warning"
    return $false
}

function Uninstall-Agent {
    Write-Status "Uninstalling AMPNM Agent..."
    
    # Stop and remove service
    try { Stop-Service -Name $ServiceName -Force -ErrorAction SilentlyContinue } catch {}
    if (Test-Path $NssmPath) {
        & $NssmPath remove $ServiceName confirm 2>$null
    }
    sc.exe delete $ServiceName 2>$null | Out-Null
    Write-Status "Service removed" "Success"
    
    # Remove files
    if (Test-Path $InstallPath) {
        Start-Sleep -Seconds 2
        Remove-Item $InstallPath -Recurse -Force -ErrorAction SilentlyContinue
        Write-Status "Files removed from $InstallPath" "Success"
    }
    
    Write-Status "AMPNM Agent uninstalled successfully!" "Success"
}

# Main execution
Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  AMPNM Windows Monitoring Agent" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

if ($Uninstall) {
    Uninstall-Agent
    exit 0
}

# Validate parameters for installation
if (-not $ServerUrl) {
    Write-Status "ServerUrl is required for installation" "Error"
    Write-Host ""
    Write-Host "Usage:" -ForegroundColor Yellow
    Write-Host "  Install: .\AMPNM-Agent-Installer.ps1 -ServerUrl 'http://your-server:2266/api/agent/metrics' -AgentToken 'your-token'" -ForegroundColor Gray
    Write-Host "  Uninstall: .\AMPNM-Agent-Installer.ps1 -Uninstall" -ForegroundColor Gray
    exit 1
}

if (-not $AgentToken) {
    Write-Status "AgentToken is required for installation" "Error"
    Write-Host "Get your token from the AMPNM web interface: Settings > Agent Tokens" -ForegroundColor Yellow
    exit 1
}

# Create directories
New-Item -ItemType Directory -Path $InstallPath -Force | Out-Null
New-Item -ItemType Directory -Path $LogPath -Force | Out-Null

# Create files
Create-Config
Create-MonitorScript

# Install service
if (Install-Service) {
    Write-Host ""
    Write-Host "========================================" -ForegroundColor Green
    Write-Host "  Installation Complete!" -ForegroundColor Green
    Write-Host "========================================" -ForegroundColor Green
    Write-Host ""
    Write-Host "The AMPNM Agent is now running as a Windows Service." -ForegroundColor White
    Write-Host "It will automatically start when Windows boots." -ForegroundColor White
    Write-Host ""
    Write-Host "Logs: $LogPath" -ForegroundColor Gray
    Write-Host "Config: $ConfigPath" -ForegroundColor Gray
    Write-Host ""
    Write-Host "To check status: Get-Service AMPNM-Agent" -ForegroundColor Yellow
    Write-Host "To view logs: Get-Content $LogPath\agent.log -Tail 50" -ForegroundColor Yellow
    Write-Host "To uninstall: .\AMPNM-Agent-Installer.ps1 -Uninstall" -ForegroundColor Yellow
} else {
    Write-Status "Installation completed with warnings (service is not running)." "Warning"
    Write-Host "Try running manually to inspect errors:" -ForegroundColor Yellow
    Write-Host "  powershell -ExecutionPolicy Bypass -NoProfile -File `"$ScriptPath`" -ConfigPath `"$ConfigPath`"" -ForegroundColor Gray
}
