param(
  [string]$TaskName = "Powersa Batum Stok Sync",
  [int]$IntervalMinutes = 1,
  [switch]$NoStart
)

$ErrorActionPreference = "Stop"

$SyncRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$scriptPath = Join-Path $SyncRoot "run-sync-product-stocks.cmd"

if (-not (Test-Path $scriptPath)) {
  throw "Calistirici bulunamadi: $scriptPath"
}

if ($IntervalMinutes -lt 1) {
  throw "IntervalMinutes en az 1 olmali."
}

$startTime = (Get-Date).AddMinutes(1).ToString("HH:mm")
$taskCommand = 'cmd.exe /c "{0}"' -f $scriptPath

$arguments = @(
  "/Create"
  "/TN", $TaskName
  "/SC", "MINUTE"
  "/MO", "$IntervalMinutes"
  "/ST", $startTime
  "/TR", $taskCommand
  "/RU", "SYSTEM"
  "/RL", "HIGHEST"
  "/F"
)

& schtasks.exe @arguments

if ($LASTEXITCODE -ne 0) {
  throw "Stok task kaydi olusturulamadi. schtasks exit code: $LASTEXITCODE"
}

Write-Host "[logo-sync-task] task kaydedildi: $TaskName (product-stocks / $IntervalMinutes dk / baslangic $startTime)"

if (-not $NoStart) {
  & schtasks.exe /Run /TN $TaskName

  if ($LASTEXITCODE -ne 0) {
    throw "Stok task baslatilamadi. schtasks exit code: $LASTEXITCODE"
  }

  Write-Host "[logo-sync-task] task baslatildi: $TaskName"
}
