<#
.SYNOPSIS
    AMPNM Docker Topology Agent — Windows (PowerShell)
    Collects container, network, and volume data from Docker Desktop
    and POSTs it to the AMPNM docker-sync edge function.

.USAGE
    $env:AMPNM_SYNC_URL = "https://<project-id>.supabase.co/functions/v1/docker-sync"
    $env:AMPNM_ANON_KEY  = "<your-anon-key>"
    .\AMPNM-Docker-Agent.ps1                    # run once
    .\AMPNM-Docker-Agent.ps1 -PollInterval 60   # loop every 60s
#>

param(
    [int]$PollInterval = 0   # 0 = run once
)

$ErrorActionPreference = "Stop"

# ── Config ──────────────────────────────────────────────────
$SyncUrl  = $env:AMPNM_SYNC_URL
$AnonKey  = $env:AMPNM_ANON_KEY

if (-not $SyncUrl) { throw "Set AMPNM_SYNC_URL environment variable (e.g. https://<id>.supabase.co/functions/v1/docker-sync)" }
if (-not $AnonKey) { throw "Set AMPNM_ANON_KEY environment variable (Supabase anon key)" }

# ── Verify Docker ──────────────────────────────────────────
try { docker version | Out-Null } catch { throw "Docker is not available. Make sure Docker Desktop is running." }

function Collect-And-Push {
    Write-Host "[$(Get-Date -Format 'o')] Collecting Docker topology..."

    $Hostname_Val = $env:COMPUTERNAME
    $HostIP = (Get-NetIPAddress -AddressFamily IPv4 | Where-Object { $_.InterfaceAlias -notmatch "Loopback" -and $_.IPAddress -ne "127.0.0.1" } | Select-Object -First 1).IPAddress
    if (-not $HostIP) { $HostIP = "" }

    $DockerVersion = try { (docker version --format '{{.Server.Version}}') } catch { "unknown" }

    # ── Containers ──────────────────────────────────────────
    $RawContainers = docker ps -a --format "{{json .}}" | ConvertFrom-Json
    $Containers = @()
    foreach ($c in $RawContainers) {
        $InternalIP = try {
            docker inspect --format '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' $c.ID
        } catch { "" }

        # Parse ports string
        $PortsList = @()
        if ($c.Ports) {
            $PortEntries = $c.Ports -split ", "
            foreach ($pe in $PortEntries) {
                if ($pe -match "(?:(\d+\.\d+\.\d+\.\d+):)?(\d+)->(\d+)/(\w+)") {
                    $PortsList += @{
                        external = [int]$Matches[2]
                        internal = [int]$Matches[3]
                        protocol = $Matches[4]
                    }
                }
            }
        }

        $Networks = @()
        if ($c.Networks) { $Networks = $c.Networks -split "," }

        $Containers += @{
            id       = $c.ID
            name     = $c.Names
            image    = $c.Image
            state    = $c.State
            status   = $c.Status
            ports    = $PortsList
            networks = $Networks
            ip       = $InternalIP
        }
    }

    # ── Networks ────────────────────────────────────────────
    $RawNetworks = docker network ls --format "{{json .}}" | ConvertFrom-Json
    $Networks = @()
    foreach ($n in $RawNetworks) {
        $NetInspect = docker network inspect $n.Name 2>$null | ConvertFrom-Json
        $Subnet   = ""
        $Gateway  = ""
        $NetCtrs  = @()
        if ($NetInspect -and $NetInspect[0]) {
            if ($NetInspect[0].IPAM.Config -and $NetInspect[0].IPAM.Config.Count -gt 0) {
                $Subnet  = $NetInspect[0].IPAM.Config[0].Subnet
                $Gateway = $NetInspect[0].IPAM.Config[0].Gateway
            }
            if ($NetInspect[0].Containers) {
                $NetCtrs = @($NetInspect[0].Containers.PSObject.Properties | ForEach-Object { $_.Value.Name })
            }
        }
        $Networks += @{
            name       = $n.Name
            driver     = $n.Driver
            scope      = $n.Scope
            subnet     = $Subnet
            gateway    = $Gateway
            containers = $NetCtrs
        }
    }

    # ── Orphaned Volumes ────────────────────────────────────
    $OrphanedVols = @(docker volume ls -qf dangling=true 2>$null).Count

    # ── Build Payload ───────────────────────────────────────
    $Payload = @{
        hostname         = $Hostname_Val
        ip               = $HostIP
        docker_version   = $DockerVersion
        containers       = $Containers
        networks         = $Networks
        volumes_orphaned = $OrphanedVols
    } | ConvertTo-Json -Depth 10 -Compress

    Write-Host "[$(Get-Date -Format 'o')] Pushing topology for $Hostname_Val ($($Containers.Count) containers)..."

    $Headers = @{
        "Content-Type"  = "application/json"
        "apikey"        = $AnonKey
        "Authorization" = "Bearer $AnonKey"
    }

    try {
        $Response = Invoke-RestMethod -Uri $SyncUrl -Method POST -Headers $Headers -Body $Payload
        Write-Host "[$(Get-Date -Format 'o')] ✅ Sync OK — host_id: $($Response.host_id)"
    } catch {
        Write-Host "[$(Get-Date -Format 'o')] ❌ Sync FAILED: $_" -ForegroundColor Red
    }
}

# ── Main Loop ───────────────────────────────────────────────
if ($PollInterval -gt 0) {
    Write-Host "AMPNM Docker Agent starting (poll every ${PollInterval}s)..."
    while ($true) {
        try { Collect-And-Push } catch { Write-Host "Error: $_" -ForegroundColor Red }
        Start-Sleep -Seconds $PollInterval
    }
} else {
    Collect-And-Push
}
