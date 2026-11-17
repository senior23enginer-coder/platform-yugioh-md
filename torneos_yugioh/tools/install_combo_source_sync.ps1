param([string]$Python = 'C:\Python314\pythonw.exe')
$ErrorActionPreference = 'Stop'
$repo = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$script = (Resolve-Path (Join-Path $PSScriptRoot 'combo_source_sync.py')).Path
if (-not (Test-Path -LiteralPath $Python)) { throw 'No se encontró Python para la sincronización.' }
$arguments = '"' + $script + '" --watch --interval 10'
$user = [System.Security.Principal.WindowsIdentity]::GetCurrent().Name
$action = New-ScheduledTaskAction -Execute $Python -Argument $arguments -WorkingDirectory $repo
$trigger = New-ScheduledTaskTrigger -AtLogOn -User $user
$principal = New-ScheduledTaskPrincipal -UserId $user -LogonType Interactive -RunLevel Limited
$settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -ExecutionTimeLimit ([TimeSpan]::Zero) -MultipleInstances IgnoreNew -RestartCount 3 -RestartInterval (New-TimeSpan -Minutes 1)
Register-ScheduledTask -TaskName 'StationX-ComboSourceSync' -Action $action -Trigger $trigger -Principal $principal -Settings $settings -Description 'Sincroniza revisiones de combos con los documentos locales de Station X.' -Force | Out-Null
Start-ScheduledTask -TaskName 'StationX-ComboSourceSync'
Get-ScheduledTask -TaskName 'StationX-ComboSourceSync' | Select-Object TaskName,State
