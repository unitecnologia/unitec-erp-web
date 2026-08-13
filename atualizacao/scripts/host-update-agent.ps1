#Requires -Version 5.1
<#
.SYNOPSIS
    [LEGADO / SUPORTE] Agente HTTP local — NAO e o pipeline oficial de atualizacao.
.DESCRIPTION
    Preferir: bin\Unitec Atualizador.exe ou atualizacao pela tela do ERP.
    Este agente agora dispara Unitec Atualizador.exe (nao atualizar-sistema.ps1).
#>

param(
    [string]$AppPath = '',
    [int]$Port = 9876
)

$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

. (Join-Path $PSScriptRoot 'unitec-install-lib.ps1')

if ([string]::IsNullOrWhiteSpace($AppPath)) {
    $AppPath = Resolve-UnitecAppPath -Path '' -FallbackFromScriptRoot $PSScriptRoot
}

Write-Warn 'host-update-agent e legado. Pipeline oficial: bin\Unitec Atualizador.exe'

$listener = New-Object System.Net.HttpListener
$prefix = "http://127.0.0.1:$Port/launch/"
$listener.Prefixes.Add($prefix)

try {
    $listener.Start()
} catch {
    Write-Err "Nao foi possivel iniciar o agente em $prefix"
    Write-Err $_.Exception.Message
    Write-Host ''
    Write-Host 'Execute como administrador ou escolha outra porta com -Port.' -ForegroundColor Yellow
    exit 1
}

Write-Title 'Agente de Atualizacao Unitec ERP'
Write-Host "Escutando em $prefix" -ForegroundColor Green
Write-Host "AppPath: $AppPath" -ForegroundColor Gray
Write-Host ''
Write-Host 'Mantenha esta janela aberta enquanto o ERP estiver em uso.' -ForegroundColor White
Write-Host 'Pressione Ctrl+C para encerrar.' -ForegroundColor White
Write-Host ''

while ($listener.IsListening) {
    $context = $listener.GetContext()
    $request = $context.Request
    $response = $context.Response

    try {
        if ($request.HttpMethod -ne 'POST') {
            throw 'Use POST /launch'
        }

        $targetAppPath = $AppPath
        $reader = New-Object System.IO.StreamReader($request.InputStream, $request.ContentEncoding)
        $rawBody = $reader.ReadToEnd()
        $reader.Close()

        if (-not [string]::IsNullOrWhiteSpace($rawBody)) {
            $payload = $rawBody | ConvertFrom-Json -ErrorAction SilentlyContinue
            if ($null -ne $payload -and -not [string]::IsNullOrWhiteSpace([string]$payload.app_path)) {
                $targetAppPath = [string]$payload.app_path
            }
        }

        $updater = Join-Path $targetAppPath 'bin\Unitec Atualizador.exe'
        if (-not (Test-Path $updater)) {
            throw "Atualizador oficial nao encontrado: $updater"
        }

        Start-Process -FilePath $updater -ArgumentList @('--app', $targetAppPath, '--quiet') -WorkingDirectory $targetAppPath | Out-Null

        $json = '{"message":"Atualizacao iniciada via Unitec Atualizador.exe."}'
        $bytes = [System.Text.Encoding]::UTF8.GetBytes($json)
        $response.StatusCode = 200
        $response.ContentType = 'application/json; charset=utf-8'
        $response.OutputStream.Write($bytes, 0, $bytes.Length)
        Write-Ok ('Atualizacao disparada (Unitec Atualizador) para {0}' -f $targetAppPath)
    } catch {
        $message = ($_.Exception.Message -replace '"', '\"')
        $json = "{`"message`":`"$message`"}"
        $bytes = [System.Text.Encoding]::UTF8.GetBytes($json)
        $response.StatusCode = 422
        $response.ContentType = 'application/json; charset=utf-8'
        $response.OutputStream.Write($bytes, 0, $bytes.Length)
        Write-Warn $_.Exception.Message
    } finally {
        $response.OutputStream.Close()
    }
}
