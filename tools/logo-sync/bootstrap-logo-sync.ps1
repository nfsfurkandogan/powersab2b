param(
  [switch]$SkipDoctor,
  [switch]$SkipNpmInstall
)

$ErrorActionPreference = "Stop"

$SyncRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $SyncRoot

Write-Host "[logo-sync-bootstrap] root=$SyncRoot"

function Ensure-Command {
  param([string]$Name)

  $command = Get-Command $Name -ErrorAction SilentlyContinue
  if ($null -ne $command) {
    return $true
  }

  return $false
}

function Sync-EnvTemplate {
  param(
    [string]$TemplatePath,
    [string]$EnvPath
  )

  if (-not (Test-Path $TemplatePath) -or -not (Test-Path $EnvPath)) {
    return
  }

  $existingKeys = @{}
  foreach ($line in Get-Content $EnvPath) {
    if ($line -match '^\s*([A-Za-z_][A-Za-z0-9_]*)=') {
      $existingKeys[$Matches[1]] = $true
    }
  }

  $missingLines = New-Object System.Collections.Generic.List[string]
  foreach ($line in Get-Content $TemplatePath) {
    if ($line -match '^\s*([A-Za-z_][A-Za-z0-9_]*)=') {
      $key = $Matches[1]
      if (-not $existingKeys.ContainsKey($key)) {
        $missingLines.Add($line)
      }
    }
  }

  if ($missingLines.Count -eq 0) {
    Write-Host "[logo-sync-bootstrap] .env guncel; eksik anahtar yok."
    return
  }

  Add-Content -Path $EnvPath -Value ""
  Add-Content -Path $EnvPath -Value "# Added by logo-sync bootstrap from .env.example"
  Add-Content -Path $EnvPath -Value $missingLines
  Write-Host "[logo-sync-bootstrap] .env icine $($missingLines.Count) eksik anahtar eklendi; mevcut degerler korunuyor."
}

if (-not (Ensure-Command "node")) {
  if (Ensure-Command "winget") {
    Write-Host "[logo-sync-bootstrap] node bulunamadi, Node.js LTS kuruluyor..."
    winget install OpenJS.NodeJS.LTS --silent --accept-package-agreements --accept-source-agreements
    $env:Path = [System.Environment]::GetEnvironmentVariable("Path", "Machine") + ";" + [System.Environment]::GetEnvironmentVariable("Path", "User")
  }
}

if (-not (Ensure-Command "node")) {
  throw "Node.js bulunamadi. Once Node.js LTS kurun, sonra scripti tekrar calistirin."
}

if (-not (Ensure-Command "npm")) {
  throw "npm bulunamadi. Node.js kurulumu eksik gorunuyor."
}

if (-not (Test-Path ".env")) {
  Copy-Item ".env.example" ".env"
  Write-Host "[logo-sync-bootstrap] .env olusturuldu. SQL bilgilerinin doldurulmasi gerekiyor."
}

Sync-EnvTemplate ".env.example" ".env"

if (-not $SkipNpmInstall) {
  if (-not (Test-Path "node_modules")) {
    Write-Host "[logo-sync-bootstrap] node_modules yok, npm ci calisiyor..."
    npm ci --omit=dev
  } else {
    Write-Host "[logo-sync-bootstrap] node_modules mevcut, npm install atlandi."
  }
}

if (-not $SkipDoctor) {
  Write-Host "[logo-sync-bootstrap] health check calisiyor..."
  node ".\logo-sync-doctor.mjs"
}

Write-Host "[logo-sync-bootstrap] tamamlandi."
