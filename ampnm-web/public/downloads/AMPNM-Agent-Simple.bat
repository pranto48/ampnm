@echo off
REM ============================================================
REM AMPNM Windows Monitoring Agent (Simple Batch Version)
REM ============================================================
REM This is a lightweight alternative to the full PowerShell installer.
REM It collects system metrics and sends them to the AMPNM server.
REM 
REM To use:
REM 1. Edit the SERVER_URL and AGENT_TOKEN below
REM 2. Run this script manually or schedule it with Task Scheduler
REM
REM For a full Windows Service installation, use AMPNM-Agent-Installer.ps1
REM ============================================================

REM === CONFIGURATION (EDIT THESE) ===
set SERVER_URL=http://YOUR-SERVER-IP:2266/docker-ampnm/api/agent/windows-metrics
set AGENT_TOKEN=YOUR-TOKEN-HERE
set INTERVAL_SECONDS=60

:LOOP
echo [%date% %time%] Collecting metrics...

powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "$ErrorActionPreference = 'SilentlyContinue'; ^
   $cpu = (Get-Counter '\Processor(_Total)\% Processor Time' -SampleInterval 1 -MaxSamples 1).CounterSamples[0].CookedValue; ^
   $os = Get-CimInstance Win32_OperatingSystem; ^
   $totalMemGB = [math]::Round($os.TotalVisibleMemorySize/1MB,2); ^
   $freeMemGB = [math]::Round($os.FreePhysicalMemory/1MB,2); ^
   $memPercent = if ($totalMemGB -gt 0) { [math]::Round((1 - ($freeMemGB / $totalMemGB))*100,2) } else { $null }; ^
   $disk = Get-CimInstance Win32_LogicalDisk -Filter 'DriveType=3' | Select-Object -First 1; ^
   $diskTotal = if ($disk) { [math]::Round($disk.Size/1GB,2) } else { $null }; ^
   $diskFree = if ($disk) { [math]::Round($disk.FreeSpace/1GB,2) } else { $null }; ^
   $net1 = Get-NetAdapterStatistics | Select-Object -First 1; ^
   Start-Sleep -Milliseconds 500; ^
   $net2 = Get-NetAdapterStatistics | Select-Object -First 1; ^
   $inMbps = if ($net1 -and $net2) { [math]::Round((($net2.ReceivedBytes - $net1.ReceivedBytes)*8*2)/1MB,2) } else { $null }; ^
   $outMbps = if ($net1 -and $net2) { [math]::Round((($net2.SentBytes - $net1.SentBytes)*8*2)/1MB,2) } else { $null }; ^
   $gpuCounters = Get-Counter -Counter '\GPU Engine(*engtype_3D)\Utilization Percentage' -ErrorAction SilentlyContinue; ^
   $gpuAvg = if ($gpuCounters.CounterSamples) { [math]::Round(($gpuCounters.CounterSamples | Measure-Object CookedValue -Average).Average,2) } else { $null }; ^
   $ip = (Get-NetIPAddress -AddressFamily IPv4 | Where-Object { $_.IPAddress -notlike '169.*' -and $_.IPAddress -ne '127.0.0.1' -and $_.PrefixOrigin -ne 'WellKnown' } | Select-Object -First 1 -ExpandProperty IPAddress); ^
   $payload = @{ ^
     host_name = $env:COMPUTERNAME; ^
     host_ip = $ip; ^
     cpu_percent = [math]::Round($cpu,2); ^
     memory_percent = $memPercent; ^
     memory_total_gb = $totalMemGB; ^
     memory_free_gb = $freeMemGB; ^
     disk_total_gb = $diskTotal; ^
     disk_free_gb = $diskFree; ^
     network_in_mbps = $inMbps; ^
     network_out_mbps = $outMbps; ^
     gpu_percent = $gpuAvg ^
   }; ^
   try { ^
     Invoke-RestMethod -Method Post -Uri '%SERVER_URL%' -Headers @{ 'X-Agent-Token'='%AGENT_TOKEN%' } -Body ($payload | ConvertTo-Json -Compress) -ContentType 'application/json' -TimeoutSec 30; ^
     Write-Host '[OK] Metrics sent successfully' -ForegroundColor Green; ^
   } catch { ^
     Write-Host '[ERROR] Failed to send metrics:' $_.Exception.Message -ForegroundColor Red; ^
   }"

echo Waiting %INTERVAL_SECONDS% seconds before next collection...
timeout /t %INTERVAL_SECONDS% /nobreak >nul
goto LOOP
