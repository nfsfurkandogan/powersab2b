param(
  [string]$ProjectDir = "C:\PowersaB2B"
)

$ErrorActionPreference = "Stop"

$envPath = Join-Path $ProjectDir ".env"
if (-not (Test-Path $envPath)) {
  throw ".env not found: $envPath"
}

$updates = [ordered]@{
  "LOGO_WAREHOUSE_NAME_MAP" = "0=ERZURUM POINT;1=ERZURUM DEPO;2=TRABZON DEPO;3=SAMSUN DEPO;4=BATUM DEPO"
  "LOGO_WAREHOUSE_RAF_KEY_MAP" = "0=25;1=61;2=55;3=250;4=995"
}

$backupPath = "$envPath.before-read-env-$(Get-Date -Format 'yyyyMMdd-HHmmss')"
Copy-Item -Path $envPath -Destination $backupPath

$lines = Get-Content -Path $envPath
foreach ($key in $updates.Keys) {
  $value = $updates[$key]
  $pattern = "^\s*$([regex]::Escape($key))\s*="
  $replacement = "$key=$value"

  $found = $false
  $lines = $lines | ForEach-Object {
    if ($_ -match $pattern) {
      $found = $true
      $replacement
    } else {
      $_
    }
  }

  if (-not $found) {
    $lines += $replacement
  }
}

Set-Content -Path $envPath -Value $lines -Encoding UTF8

Write-Host "Updated $envPath"
Write-Host "Backup: $backupPath"
Write-Host "Run next:"
Write-Host "  cd $ProjectDir"
Write-Host "  npm run sync:products"
