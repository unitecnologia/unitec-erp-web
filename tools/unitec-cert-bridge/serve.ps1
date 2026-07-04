$ErrorActionPreference = 'Stop'

$Port = 18765
$Prefix = "http://127.0.0.1:$Port/"

function Write-CorsHeaders {
    param([System.Net.HttpListenerResponse]$Response)

    $Response.Headers['Access-Control-Allow-Origin'] = '*'
    $Response.Headers['Access-Control-Allow-Methods'] = 'GET, OPTIONS'
    $Response.Headers['Access-Control-Allow-Headers'] = 'Accept, Content-Type'
    $Response.ContentType = 'application/json; charset=utf-8'
}

function Format-CertName {
    param([string]$Dn)

    if ($Dn -match 'CN=([^,]+)') {
        return $Matches[1]
    }

    return $Dn
}

function Get-CertSerialHex {
    param([System.Security.Cryptography.X509Certificates.X509Certificate2]$Certificate)

    $bytes = $Certificate.GetSerialNumber()

    if ($null -eq $bytes -or $bytes.Length -eq 0) {
        return ($Certificate.SerialNumber -replace '\s', '').ToUpperInvariant()
    }

    return (($bytes | ForEach-Object { '{0:X2}' -f $_ }) -join '').ToUpperInvariant()
}

function Get-InstalledCertificates {
    $stores = @(
        @{ Path = 'Cert:\CurrentUser\My'; Origem = 'CurrentUser' },
        @{ Path = 'Cert:\LocalMachine\My'; Origem = 'LocalMachine' }
    )

    $items = @{}
    $now = Get-Date

    foreach ($store in $stores) {
        if (-not (Test-Path $store.Path)) {
            continue
        }

        Get-ChildItem -Path $store.Path -ErrorAction SilentlyContinue | ForEach-Object {
            if (-not $_.HasPrivateKey) {
                return
            }

            if ($_.NotAfter -lt $now) {
                return
            }

            $thumbprint = ($_.Thumbprint -replace '\s', '').ToUpperInvariant()

            if ($items.ContainsKey($thumbprint)) {
                return
            }

            $items[$thumbprint] = [ordered]@{
                titulo = Format-CertName -Dn $_.Subject
                emissor = Format-CertName -Dn $_.Issuer
                validade_inicio = $_.NotBefore.ToString('dd/MM/yyyy')
                validade = $_.NotAfter.ToString('dd/MM/yyyy')
                numero_serie = Get-CertSerialHex -Certificate $_
                thumbprint = $thumbprint
                origem = $store.Origem
            }
        }
    }

    return @($items.Values | Sort-Object titulo)
}

function Send-Json {
    param(
        [System.Net.HttpListenerResponse]$Response,
        [object]$Payload,
        [int]$StatusCode = 200
    )

    Write-CorsHeaders -Response $Response
    $Response.StatusCode = $StatusCode
    $json = $Payload | ConvertTo-Json -Depth 5 -Compress
    $buffer = [System.Text.Encoding]::UTF8.GetBytes($json)
    $Response.ContentLength64 = $buffer.Length
    $Response.OutputStream.Write($buffer, 0, $buffer.Length)
    $Response.OutputStream.Close()
}

Write-Host "Unitec Cert Bridge em $Prefix"
Write-Host 'Pressione Ctrl+C para encerrar.'

$listener = New-Object System.Net.HttpListener
$listener.Prefixes.Add($Prefix)

try {
    $listener.Start()
}
catch {
    Write-Host ''
    Write-Host 'Nao foi possivel iniciar a ponte na porta 18765.' -ForegroundColor Red
    Write-Host 'Execute uma vez como Administrador:' -ForegroundColor Yellow
    Write-Host "  netsh http add urlacl url=$Prefix user=Everyone"
    Write-Host ''
    throw
}

try {
    while ($listener.IsListening) {
        $context = $listener.GetContext()
        $request = $context.Request
        $response = $context.Response

        if ($request.HttpMethod -eq 'OPTIONS') {
            Write-CorsHeaders -Response $response
            $response.StatusCode = 204
            $response.OutputStream.Close()
            continue
        }

        if ($request.Url.AbsolutePath -ne '/certificados') {
            Send-Json -Response $response -Payload @{ ok = $false; message = 'Rota nao encontrada.' } -StatusCode 404
            continue
        }

        try {
            $certificados = Get-InstalledCertificates
            Send-Json -Response $response -Payload @{ ok = $true; certificados = $certificados }
        }
        catch {
            Send-Json -Response $response -Payload @{ ok = $false; message = $_.Exception.Message } -StatusCode 500
        }
    }
}
finally {
    $listener.Stop()
    $listener.Close()
}
