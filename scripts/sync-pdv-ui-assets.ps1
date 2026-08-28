# Sincroniza assets visuais do PDV ERP -> PDV offline (paridade pixel-perfect).
$ErrorActionPreference = 'Stop'
$ErpRoot = Split-Path $PSScriptRoot -Parent
$PdvRoot = Join-Path (Split-Path $ErpRoot -Parent) 'unitec-pdv-web'

if (-not (Test-Path $PdvRoot)) {
    Write-Error "Pasta pdv-web nao encontrada: $PdvRoot"
}

$Pairs = @(
    @('public/css/erp-pdv.css', 'public/css/erp-pdv.css'),
    @('public/css/erp-tokens.css', 'public/css/erp-tokens.css'),
    @('public/js/erp-pdv.js', 'public/js/erp-pdv.js'),
    @('public/js/erp-silent-print.js', 'public/js/erp-silent-print.js')
)

foreach ($pair in $Pairs) {
    $src = Join-Path $ErpRoot $pair[0]
    $dst = Join-Path $PdvRoot $pair[1]
    if (-not (Test-Path $src)) {
        Write-Warning "Origem ausente (ignorado): $src"
        continue
    }
    $dir = Split-Path $dst -Parent
    if (-not (Test-Path $dir)) { New-Item -ItemType Directory -Path $dir -Force | Out-Null }
    Copy-Item -Path $src -Destination $dst -Force
    Write-Host "OK $($pair[0])"
}

$pngSrc = Join-Path $ErpRoot 'public/img/erp/pdv-tools'
$pngDst = Join-Path $PdvRoot 'public/img/erp/pdv-tools'
if (Test-Path $pngSrc) {
    if (-not (Test-Path $pngDst)) { New-Item -ItemType Directory -Path $pngDst -Force | Out-Null }
    Copy-Item -Path (Join-Path $pngSrc '*.png') -Destination $pngDst -Force
    Write-Host "OK public/img/erp/pdv-tools/*.png"
}

Write-Host "Sync concluido -> $PdvRoot"
