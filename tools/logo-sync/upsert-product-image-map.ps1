param(
    [Parameter(Mandatory = $true)]
    [string]$Sku,

    [Parameter(Mandatory = $true)]
    [string]$ImagePath,

    [string]$MapFile = "$PSScriptRoot\product-image-map.csv"
)

$ErrorActionPreference = "Stop"

if ([string]::IsNullOrWhiteSpace($Sku)) {
    throw "Sku is required."
}

if ([string]::IsNullOrWhiteSpace($ImagePath)) {
    throw "ImagePath is required."
}

$normalizedSku = $Sku.Trim()
$normalizedImagePath = $ImagePath.Trim()
$mapDirectory = Split-Path -Parent $MapFile

if (-not [string]::IsNullOrWhiteSpace($mapDirectory) -and -not (Test-Path -LiteralPath $mapDirectory)) {
    New-Item -ItemType Directory -Path $mapDirectory | Out-Null
}

$rows = @()

if (Test-Path -LiteralPath $MapFile) {
    $rows = Import-Csv -LiteralPath $MapFile
}

$updatedRows = @(
    $rows |
        Where-Object { $_.sku -and $_.sku.Trim().ToLowerInvariant() -ne $normalizedSku.ToLowerInvariant() }
)

$updatedRows += [pscustomobject]@{
    sku = $normalizedSku
    image_path = $normalizedImagePath
}

$updatedRows |
    Sort-Object sku |
    Export-Csv -LiteralPath $MapFile -NoTypeInformation -Encoding UTF8

Write-Host "Updated image map:" $MapFile
Write-Host "  sku       =" $normalizedSku
Write-Host "  image_path=" $normalizedImagePath
