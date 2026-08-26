@echo off
REM Copyright (c) IT Support BD. All rights reserved.
REM This file is part of AMPNM.
REM 
REM This program is free software: you can redistribute it and/or modify
REM it under the terms of the GNU Affero General Public License...
REM (Commercial licenses available at https://ampnm.itsupport.com.bd/pricing)

REM ============================================================
REM AMPNM Windows Monitoring Agent (Simple Batch Version)
REM ============================================================

REM === Auto-Elevate to Administrator if not already elevated ===
net session >nul 2>&1
if %errorLevel% neq 0 (
    echo [UAC] Administrative permissions required. Requesting UAC elevation...
    powershell -NoProfile -ExecutionPolicy Bypass -Command "Start-Process cmd -ArgumentList '/c \"\"%~f0\"\"' -Verb RunAs"
    exit /b
)

REM === CONFIGURATION (EDIT THESE) ===
set SERVER_URL=http://YOUR-SERVER-IP:2266/docker-ampnm/api/agent/windows-metrics
set AGENT_TOKEN=YOUR-TOKEN-HERE
set INTERVAL_SECONDS=60

:LOOP
echo [%date% %time%] Collecting metrics...

powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "$ErrorActionPreference = 'SilentlyContinue'; ^
   $samples = @(); ^
   for ($i = 0; $i -lt 5; $i++) { ^
     $cpuSample = Get-Counter '\Processor(_Total)\% Processor Time' -ErrorAction SilentlyContinue; ^
     if ($cpuSample.CounterSamples) { $samples += $cpuSample.CounterSamples[0].CookedValue } ^
     Start-Sleep -Milliseconds 500; ^
   } ^
   $cpu = if ($samples.Count -gt 0) { ($samples | Measure-Object -Average).Average } else { 0.0 }; ^
   $os = Get-CimInstance Win32_OperatingSystem; ^
   $totalMemGB = [math]::Round($os.TotalVisibleMemorySize/1MB,2); ^
   $freeMemGB = [math]::Round($os.FreePhysicalMemory/1MB,2); ^
   $memPercent = if ($totalMemGB -gt 0) { [math]::Round((1 - ($freeMemGB / $totalMemGB))*100,2) } else { $null }; ^
   $disks = Get-CimInstance Win32_LogicalDisk -Filter 'DriveType=3'; ^
   $totalSize = 0; $freeSpace = 0; ^
   foreach ($d in $disks) { if ($d.Size -gt 0) { $totalSize += $d.Size; $freeSpace += $d.FreeSpace } }; ^
   $diskTotal = if ($totalSize -gt 0) { [math]::Round($totalSize/1GB,2) } else { $null }; ^
   $diskFree = if ($totalSize -gt 0) { [math]::Round($freeSpace/1GB,2) } else { $null }; ^
   $net1 = Get-NetAdapterStatistics | Select-Object -First 1; ^
   Start-Sleep -Milliseconds 500; ^
   $net2 = Get-NetAdapterStatistics | Select-Object -First 1; ^
   $inMbps = if ($net1 -and $net2) { [math]::Round((($net2.ReceivedBytes - $net1.ReceivedBytes)*8*2)/1MB,2) } else { $null }; ^
   $outMbps = if ($net1 -and $net2) { [math]::Round((($net2.SentBytes - $net1.SentBytes)*8*2)/1MB,2) } else { $null }; ^
   $gpuPercent = $null; ^
   if (Get-Command nvidia-smi -ErrorAction SilentlyContinue) { ^
     $gpuRaw = nvidia-smi --query-gpu=utilization.gpu --format=csv,noheader,nounits 2>$null; ^
     $parsedVal = 0.0; ^
     if ($gpuRaw -and [double]::TryParse($gpuRaw.Trim(), [ref]$parsedVal)) { $gpuPercent = $parsedVal } ^
   } ^
   if ($gpuPercent -eq $null) { ^
     $gpuCounters = Get-Counter -Counter '\GPU Engine(*engtype_3D)\Utilization Percentage' -ErrorAction SilentlyContinue; ^
     if ($gpuCounters.CounterSamples) { $gpuPercent = [math]::Round(($gpuCounters.CounterSamples | Measure-Object CookedValue -Average).Average,2) } ^
   } ^
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
     gpu_percent = $gpuPercent ^
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
