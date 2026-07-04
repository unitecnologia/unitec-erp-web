#Requires -Version 5.1
<#
.SYNOPSIS
    Agente local para disparar atualizacao do ERP no Windows.
.DESCRIPTION
    Escuta POST em http://127.0.0.1:9876/launch e executa atualizar-sistema.ps1 no Windows.
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

        $script = Join-Path $targetAppPath 'scripts\atualizar-sistema.ps1'
        if (-not (Test-Path $script)) {
            throw "Script nao encontrado: $script"
        }

        $arguments = @(
            '-Sta',
            '-NoProfile',
            '-ExecutionPolicy', 'Bypass',
            '-File', $script,
            '-AppPath', $targetAppPath,
            '-LeigoMode'
        )

        Start-Process -FilePath 'powershell.exe' -ArgumentList $arguments -WindowStyle Minimized | Out-Null

        $json = '{"message":"Atualizacao iniciada. Acompanhe a janela de progresso na tela."}'
        $bytes = [System.Text.Encoding]::UTF8.GetBytes($json)
        $response.StatusCode = 200
        $response.ContentType = 'application/json; charset=utf-8'
        $response.OutputStream.Write($bytes, 0, $bytes.Length)
        Write-Ok ('Atualizacao disparada para {0}' -f $targetAppPath)
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
