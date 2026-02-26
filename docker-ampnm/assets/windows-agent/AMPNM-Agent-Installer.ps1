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
    The full URL to the AMPNM metrics endpoint (e.g., http://192.168.1.100:2266/docker-ampnm/api/agent/windows-metrics)

.PARAMETER AgentToken
    The authentication token from the AMPNM Agent Tokens management page

.PARAMETER Interval
    Collection interval in seconds (default: 60)

.PARAMETER Uninstall
    Remove the agent service and files

.EXAMPLE
    .\AMPNM-Agent-Installer.ps1 -ServerUrl "http://192.168.1.100:2266/docker-ampnm/api/agent/windows-metrics" -AgentToken "your-token-here"

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
$NssmUrl = "https://nssm.cc/release/nssm-2.24.zip"

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
    
    Write-Status "Downloading NSSM (service manager)..."
    $zipPath = "$InstallPath\nssm.zip"
    
    try {
        [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
        Invoke-WebRequest -Uri $NssmUrl -OutFile $zipPath -UseBasicParsing
        
        Expand-Archive -Path $zipPath -DestinationPath "$InstallPath\nssm-temp" -Force
        
        # Find the correct nssm.exe based on architecture
        $arch = if ([Environment]::Is64BitOperatingSystem) { "win64" } else { "win32" }
        $nssmExe = Get-ChildItem -Path "$InstallPath\nssm-temp" -Recurse -Filter "nssm.exe" | 
                   Where-Object { $_.FullName -like "*$arch*" } | 
                   Select-Object -First 1
        
        if ($nssmExe) {
            Copy-Item $nssmExe.FullName $NssmPath -Force
            Write-Status "NSSM installed successfully" "Success"
        } else {
            throw "Could not find nssm.exe"
        }
        
        # Cleanup
        Remove-Item $zipPath -Force -ErrorAction SilentlyContinue
        Remove-Item "$InstallPath\nssm-temp" -Recurse -Force -ErrorAction SilentlyContinue
        
        return $true
    }
    catch {
        Write-Status "Failed to install NSSM: $_" "Error"
        return $false
    }
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
        # CPU Usage (average over 1 second)
        $cpu = Get-Counter '\Processor(_Total)\% Processor Time' -SampleInterval 1 -MaxSamples 1
        $metrics.cpu_percent = [math]::Round($cpu.CounterSamples[0].CookedValue, 2)
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
        # Disk (primary drive)
        $disk = Get-CimInstance Win32_LogicalDisk -Filter "DriveType=3" | 
                Sort-Object -Property Size -Descending | 
                Select-Object -First 1
        if ($disk) {
            $metrics.disk_total_gb = [math]::Round($disk.Size / 1GB, 2)
            $metrics.disk_free_gb = [math]::Round($disk.FreeSpace / 1GB, 2)
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
        # GPU (if available)
        $gpuCounters = Get-Counter -Counter '\GPU Engine(*engtype_3D)\Utilization Percentage' -ErrorAction SilentlyContinue
        if ($gpuCounters.CounterSamples) {
            $gpuAvg = ($gpuCounters.CounterSamples | Measure-Object CookedValue -Average).Average
            $metrics.gpu_percent = [math]::Round($gpuAvg, 2)
        }
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
            Write-Output "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss') - Metrics sent successfully. Device: $deviceMatched"
        } else {
            Write-Output "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss') - Server returned: $($response | ConvertTo-Json -Compress)"
        }
    }
    catch {
        Write-Output "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss') - Error sending metrics: $_"
    }
}

# Main loop
Write-Output "AMPNM Agent started. Collecting metrics every $Interval seconds..."
Write-Output "Server: $ServerUrl"

while ($true) {
    try {
        $metrics = Get-SystemMetrics
        Send-Metrics -Metrics $metrics
    }
    catch {
        Write-Output "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss') - Collection error: $_"
    }
    
    Start-Sleep -Seconds $Interval
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
    # Check if service exists
    $existingService = Get-Service -Name $ServiceName -ErrorAction SilentlyContinue
    if ($existingService) {
        Write-Status "Removing existing service..."
        & $NssmPath stop $ServiceName 2>$null
        & $NssmPath remove $ServiceName confirm 2>$null
        Start-Sleep -Seconds 2
    }
    
    # Install service using NSSM
    Write-Status "Installing Windows Service..."
    & $NssmPath install $ServiceName powershell.exe "-ExecutionPolicy Bypass -NoProfile -File `"$ScriptPath`" -ConfigPath `"$ConfigPath`""
    
    # Configure service
    & $NssmPath set $ServiceName DisplayName "AMPNM Monitoring Agent"
    & $NssmPath set $ServiceName Description "Sends system metrics to AMPNM network monitor"
    & $NssmPath set $ServiceName Start SERVICE_AUTO_START
    & $NssmPath set $ServiceName AppStdout "$LogPath\agent.log"
    & $NssmPath set $ServiceName AppStderr "$LogPath\agent-error.log"
    & $NssmPath set $ServiceName AppRotateFiles 1
    & $NssmPath set $ServiceName AppRotateBytes 1048576
    
    # Set recovery options (restart on failure)
    & $NssmPath set $ServiceName AppExit Default Restart
    & $NssmPath set $ServiceName AppRestartDelay 10000
    
    # Start the service
    Write-Status "Starting service..."
    & $NssmPath start $ServiceName
    
    Start-Sleep -Seconds 3
    $service = Get-Service -Name $ServiceName -ErrorAction SilentlyContinue
    if ($service -and $service.Status -eq 'Running') {
        Write-Status "Service installed and running successfully!" "Success"
        return $true
    } else {
        Write-Status "Service installed but may not be running. Check logs at $LogPath" "Warning"
        return $true
    }
}

function Uninstall-Agent {
    Write-Status "Uninstalling AMPNM Agent..."
    
    # Stop and remove service
    if (Test-Path $NssmPath) {
        & $NssmPath stop $ServiceName 2>$null
        & $NssmPath remove $ServiceName confirm 2>$null
        Write-Status "Service removed" "Success"
    } else {
        # Try with sc.exe
        sc.exe stop $ServiceName 2>$null
        sc.exe delete $ServiceName 2>$null
    }
    
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
    Write-Host "  Install: .\AMPNM-Agent-Installer.ps1 -ServerUrl 'http://your-server:2266/docker-ampnm/api/agent/windows-metrics' -AgentToken 'your-token'" -ForegroundColor Gray
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

# Install NSSM
if (-not (Install-NSSM)) {
    Write-Status "Failed to install NSSM. Cannot continue." "Error"
    exit 1
}

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
    Write-Status "Installation completed with warnings" "Warning"
}
