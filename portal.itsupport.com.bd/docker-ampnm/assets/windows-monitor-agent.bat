@echo off
REM AMPNM lightweight Windows metrics agent
REM Customize the Docker server URL (include port if not 80) and the shared token
set SERVER_URL=http://localhost:3001/api/agent/windows-metrics
set AGENT_TOKEN=CHANGE_ME

echo Collecting Windows metrics and sending to %SERVER_URL%

powershell -NoProfile -Command ^
  "$cpu = (Get-Counter '\\Processor(_Total)\\% Processor Time').CounterSamples[0].CookedValue; ^
   $os = Get-CimInstance Win32_OperatingSystem; ^
   $totalMemGB = [math]::Round($os.TotalVisibleMemorySize/1MB,2); ^
   $freeMemGB = [math]::Round($os.FreePhysicalMemory/1MB,2); ^
   $memPercent = if ($totalMemGB -eq 0) { $null } else { [math]::Round((1 - ($freeMemGB / $totalMemGB))*100,2) }; ^
   $disk = Get-CimInstance Win32_LogicalDisk -Filter \"DriveType=3\" | Select-Object -First 1; ^
   $diskTotal = if ($disk) { [math]::Round($disk.Size/1GB,2) } else { $null }; ^
   $diskFree = if ($disk) { [math]::Round($disk.FreeSpace/1GB,2) } else { $null }; ^
   $net1 = Get-NetAdapterStatistics | Select-Object -First 1; ^
   Start-Sleep -Seconds 1; ^
   $net2 = Get-NetAdapterStatistics | Select-Object -First 1; ^
   $inMbps = if ($net1 -and $net2) { [math]::Round((($net2.ReceivedBytes - $net1.ReceivedBytes)*8)/1MB,2) } else { $null }; ^
   $outMbps = if ($net1 -and $net2) { [math]::Round((($net2.SentBytes - $net1.SentBytes)*8)/1MB,2) } else { $null }; ^
   $gpuCounters = Get-Counter -Counter '\\GPU Engine(*)\\Utilization Percentage' -ErrorAction SilentlyContinue; ^
   $gpuAvg = if ($gpuCounters.CounterSamples) { [math]::Round(($gpuCounters.CounterSamples | Measure-Object CookedValue -Average).Average,2) } else { $null }; ^
   $ip = (Get-NetIPAddress -AddressFamily IPv4 | Where-Object { $_.IPAddress -notlike '169.*' -and $_.IPAddress -ne '127.0.0.1' } | Select-Object -First 1 -ExpandProperty IPAddress); ^
   $payload = @{ host_name=$env:COMPUTERNAME; host_ip=$ip; cpu_percent=[math]::Round($cpu,2); memory_percent=$memPercent; disk_free_gb=$diskFree; disk_total_gb=$diskTotal; network_in_mbps=$inMbps; network_out_mbps=$outMbps; gpu_percent=$gpuAvg }; ^
   Invoke-RestMethod -Method Post -Uri '%SERVER_URL%' -Headers @{ 'X-Agent-Token'='%AGENT_TOKEN%' } -Body ($payload | ConvertTo-Json) -ContentType 'application/json' -ErrorAction Stop"

if %ERRORLEVEL% EQU 0 (
  echo Metrics sent successfully.
) else (
  echo Failed to send metrics. Exit code: %ERRORLEVEL%
)

REM Comment the next line if scheduling through Task Scheduler
pause
