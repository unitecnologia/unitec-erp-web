# Porta classes de suporte PDV do ERP para o pdv-web (namespace EmpresaConfig).
$ErrorActionPreference = 'Stop'
$erpRoot = Split-Path $PSScriptRoot -Parent
$pdvRoot = Join-Path (Split-Path $erpRoot -Parent) 'unitec-pdv-web'
$dest = Join-Path $pdvRoot 'app\Support\Pdv'

$files = @(
    @{ Src = 'app\Support\Erp\Pdv\PdvDavCupomLayout.php'; Dest = 'PdvDavCupomLayout.php' }
)

foreach ($f in $files) {
    $srcPath = Join-Path $erpRoot $f.Src
    $text = Get-Content -LiteralPath $srcPath -Raw -Encoding UTF8
    $text = $text -replace 'App\\Support\\Erp\\Pdv', 'App\Support\Pdv'
    $text = $text -replace 'use App\\Models\\Empresa;', 'use App\Models\EmpresaConfig;'
    $text = $text -replace '\?Empresa ', '?EmpresaConfig '
    $text = $text -replace 'function formatEndereco\(\?Empresa ', 'function formatEndereco(?EmpresaConfig '
    $text = $text -replace 'function formatCidadeLinha\(\?Empresa ', 'function formatCidadeLinha(?EmpresaConfig '
    $text = $text -replace '\$item->preco_unitario', '(float) ($item->preco_unitario ?? $item->preco ?? 0)'
    $text = $text -replace '\$venda->vendedor\?->nome', 'null'
    $text = $text -replace '\$person\?->nome_razao', '(string) ($venda->cliente_nome ?? $person?->nome_razao ?? '')'
    $destPath = Join-Path $dest $f.Dest
    [System.IO.File]::WriteAllText($destPath, $text, [System.Text.UTF8Encoding]::new($false))
    Write-Host "OK $($f.Dest)"
}

Write-Host 'Concluido.'
