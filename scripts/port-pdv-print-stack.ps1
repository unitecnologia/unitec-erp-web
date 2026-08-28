$ErrorActionPreference = 'Stop'
$Erp = Split-Path $PSScriptRoot -Parent
$Pdv = Join-Path (Split-Path $Erp -Parent) 'unitec-pdv-web'

$srcPrint = Join-Path $Erp 'app\Support\Erp\Printing'
$dstPrint = Join-Path $Pdv 'app\Support\Pdv\Printing'
if (Test-Path $dstPrint) { Remove-Item $dstPrint -Recurse -Force }
Copy-Item $srcPrint $dstPrint -Recurse -Force

$files = @(
    'app\Support\Erp\Pdv\PdvCaixaResumoBobinaBuilder.php',
    'app\Support\Erp\Pdv\PdvMovimentoCaixaBobinaBuilder.php',
    'app\Support\Erp\Pdv\PdvCaixaResumoMovimentos.php',
    'app\Support\Erp\Orcamento\OrcamentoBobinaFormatter.php'
)

foreach ($f in $files) {
    $src = Join-Path $Erp $f
    $name = Split-Path $f -Leaf
    if ($name -eq 'OrcamentoBobinaFormatter.php') { $name = 'PdvBobinaFormatter.php' }
    $dst = Join-Path $Pdv ("app\Support\Pdv\" + $name)
    Copy-Item $src $dst -Force
}

Get-ChildItem -Path (Join-Path $Pdv 'app\Support\Pdv') -Recurse -Filter '*.php' | ForEach-Object {
    $c = Get-Content $_.FullName -Raw -Encoding UTF8
    $c = $c -replace 'namespace App\\Support\\Erp\\Printing', 'namespace App\Support\Pdv\Printing'
    $c = $c -replace 'namespace App\\Support\\Erp\\Pdv', 'namespace App\Support\Pdv'
    $c = $c -replace 'namespace App\\Support\\Erp\\Orcamento', 'namespace App\Support\Pdv'
    $c = $c -replace 'class OrcamentoBobinaFormatter', 'class PdvBobinaFormatter'
    $c = $c -replace 'App\\Support\\Erp\\Printing', 'App\Support\Pdv\Printing'
    $c = $c -replace 'App\\Support\\Erp\\Pdv', 'App\Support\Pdv'
    $c = $c -replace 'App\\Support\\Erp\\Orcamento\\OrcamentoBobinaFormatter', 'App\Support\Pdv\PdvBobinaFormatter'
    $c = $c -replace 'App\\Support\\Erp\\ErpMoney', 'App\Support\Pdv\PdvMoney'
    $c = $c -replace 'App\\Support\\Erp\\ErpTimezone', 'App\Support\Pdv\PdvTimezone'
    $c = $c -replace 'ErpMoney::', 'PdvMoney::'
    $c = $c -replace 'ErpTimezone::', 'PdvTimezone::'
    $c = $c -replace 'use App\\Models\\Empresa;', 'use App\\Models\\EmpresaConfig;'
    $c = $c -replace '\?Empresa ', '?EmpresaConfig '
    $c = $c -replace "route\('erp\.print", "route('pdv.print"
    $c = $c -replace "route\('erp\.reports", "route('pdv.reports"
    [System.IO.File]::WriteAllText($_.FullName, $c)
}

Write-Host "Print stack portado -> $Pdv"
