param(
  [switch]$Apply,
  [string]$RunAs = "SYSTEM",
  [string]$SyncRoot = "C:\PowersaB2B\tools\logo-sync"
)

$ErrorActionPreference = "Stop"

# Phase 1 is intentionally conservative:
# - no full product sync
# - no product image sync
# - no sync:all
# - no daemon
#
# Default run mode is dry-run. Use -Apply to create/update tasks.
#
# Account note:
# SYSTEM may work on this server. If Logo SQL or network shares require an
# interactive/network identity, run the tasks as Administrator or a dedicated
# service user that can reach Logo SQL and the B2B API. Do not store passwords
# in this file.

$Tasks = @(
  @{
    Name = "Powersa Logo Product Stocks Fast"
    Wrapper = "run-sync-product-stocks-fast.cmd"
    IntervalMinutes = 5
    StopAfterMinutes = 20
    LogFile = "sync-product-stocks-fast.log"
    Description = "Phase 1 Logo -> B2B fast incremental product stock sync. Runs every 5 minutes. Does not run full product, full repair stock or image sync."
  },
  @{
    Name = "Powersa Logo Product Catalog Fast"
    Wrapper = "run-sync-product-catalog-fast.cmd"
    IntervalMinutes = 5
    StopAfterMinutes = 30
    LogFile = "sync-product-catalog-fast.log"
    Description = "Phase 1 Logo -> B2B fast product card catalog sync. Runs every 5 minutes for recently created/modified product cards. Does not run image sync or full repair sync."
  },
  @{
    Name = "Powersa Logo Customers Sync"
    Wrapper = "run-sync-customers.cmd"
    IntervalMinutes = 1
    StopAfterMinutes = 60
    LogFile = "sync-customers.log"
    Description = "Phase 1 Logo -> B2B incremental customer sync. Runs every 1 minute."
  },
  @{
    Name = "Powersa Logo Customers Export"
    Wrapper = "run-sync-customers-export.cmd"
    IntervalMinutes = 1
    StopAfterMinutes = 60
    LogFile = "sync-customers-export.log"
    Description = "Phase 1 B2B -> Logo customer export. Runs every 1 minute."
  },
  @{
    Name = "Powersa Logo Ledger Sync"
    Wrapper = "run-sync-ledger.cmd"
    IntervalMinutes = 5
    StopAfterMinutes = 60
    LogFile = "sync-ledger.log"
    Description = "Phase 1 Logo -> B2B ledger sync. Runs every 5 minutes."
  },
  @{
    Name = "Powersa Logo Collections Export"
    Wrapper = "run-sync-collections.cmd"
    IntervalMinutes = 1
    StopAfterMinutes = 60
    LogFile = "sync-collections.log"
    Description = "Phase 1 B2B -> Logo collections export. Runs every 1 minute."
  },
  @{
    Name = "Powersa Logo POS Sales Export"
    Wrapper = "run-sync-pos-sales.cmd"
    IntervalMinutes = 1
    StopAfterMinutes = 60
    LogFile = "sync-pos-sales.log"
    Description = "Phase 1 B2B -> Logo POS sales export. Runs every 1 minute."
  },
  @{
    Name = "Powersa Logo POS Expenses Export"
    Wrapper = "run-sync-pos-expenses.cmd"
    IntervalMinutes = 1
    StopAfterMinutes = 60
    LogFile = "sync-pos-expenses.log"
    Description = "Phase 1 B2B -> Logo POS expenses export. Runs every 1 minute."
  },
  @{
    Name = "Powersa Logo Documents Export"
    Wrapper = "run-sync-documents-export.cmd"
    IntervalMinutes = 1
    StopAfterMinutes = 60
    LogFile = "sync-documents-export.log"
    Description = "Phase 1 B2B -> Logo orders/shipments/returns export. Runs every 1 minute."
  }
)

function New-RepeatingTrigger {
  param([int]$IntervalMinutes)

  New-ScheduledTaskTrigger `
    -Once `
    -At (Get-Date).AddMinutes(1) `
    -RepetitionInterval (New-TimeSpan -Minutes $IntervalMinutes) `
    -RepetitionDuration (New-TimeSpan -Days 3650)
}

function New-Phase1Action {
  param([string]$Wrapper)

  $scriptPath = Join-Path $SyncRoot $Wrapper

  New-ScheduledTaskAction `
    -Execute "cmd.exe" `
    -Argument ('/d /c "{0}"' -f $scriptPath) `
    -WorkingDirectory $SyncRoot
}

function New-Phase1Settings {
  param([int]$StopAfterMinutes)

  New-ScheduledTaskSettingsSet `
    -MultipleInstances IgnoreNew `
    -StartWhenAvailable `
    -RunOnlyIfNetworkAvailable `
    -ExecutionTimeLimit (New-TimeSpan -Minutes $StopAfterMinutes)
}

Write-Host ""
Write-Host "Powersa Logo Sync Task Scheduler Phase 1 installer"
Write-Host "Sync root: $SyncRoot"
Write-Host "Run as: $RunAs"
Write-Host ""
Write-Host "WARNING: This phase installs only lightweight production jobs."
Write-Host "WARNING: Full product sync, image sync, sync:all and daemon are intentionally excluded."
Write-Host "WARNING: Default mode is dry-run. Use -Apply to create/update Windows Scheduled Tasks."
Write-Host ""

if (-not (Test-Path $SyncRoot)) {
  throw "Sync root not found: $SyncRoot"
}

foreach ($task in $Tasks) {
  $wrapperPath = Join-Path $SyncRoot $task.Wrapper

  if (-not (Test-Path $wrapperPath)) {
    throw "Wrapper not found for task '$($task.Name)': $wrapperPath"
  }
}

Write-Host "Tasks in this phase:"
foreach ($task in $Tasks) {
  $wrapperPath = Join-Path $SyncRoot $task.Wrapper
  Write-Host ("- {0}: every {1} min, stop after {2} min, log {3}" -f $task.Name, $task.IntervalMinutes, $task.StopAfterMinutes, $task.LogFile)
  Write-Host ("  cmd.exe /d /c ""{0}""" -f $wrapperPath)
}

Write-Host ""

if (-not $Apply) {
  Write-Host "DRY-RUN ONLY: no Scheduled Tasks were created or changed."
  Write-Host "To apply: powershell -ExecutionPolicy Bypass -File .\install-task-scheduler-phase1.ps1 -Apply"
  exit 0
}

Write-Host "APPLY MODE: creating/updating Scheduled Tasks..."

foreach ($task in $Tasks) {
  $action = New-Phase1Action -Wrapper $task.Wrapper
  $trigger = New-RepeatingTrigger -IntervalMinutes $task.IntervalMinutes
  $settings = New-Phase1Settings -StopAfterMinutes $task.StopAfterMinutes

  Register-ScheduledTask `
    -TaskName $task.Name `
    -Action $action `
    -Trigger $trigger `
    -Settings $settings `
    -Description $task.Description `
    -User $RunAs `
    -RunLevel Highest `
    -Force | Out-Null

  Write-Host ("created/updated: {0}" -f $task.Name)
}

Write-Host ""
Write-Host "Phase 1 Scheduled Tasks installed."
Write-Host "No full product, image, sync:all or daemon task was installed."
Write-Host "Use .\check-task-scheduler-phase1.ps1 to inspect task state and logs."
