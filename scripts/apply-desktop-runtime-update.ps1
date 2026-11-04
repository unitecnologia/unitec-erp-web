#Requires -Version 5.1
<#
.SYNOPSIS
    Aplica binarios pendentes do UnitecErpServer e FrankenPHP apos o apply do ZIP.

.DESCRIPTION
    Chamado pelo apply da atualizacao quando o pacote traz runtime-update.
    Para o servico, aplica tools\frankenphp e bin\ a partir de
    storage\app\unitec-desktop-pending, sobe de novo e grava status persistente em
    storage\framework\desktop-runtime-status.json (fora do pending).
    So remove o pending apos gravar o status final. Nunca sobrescreve runtime
    ja mais novo que o manifesto pendente. Apenas ASCII (Windows PowerShell 5.1).
#>

param(
    [Parameter(Mandatory = $true)]
    [string]$AppPath,

    [Parameter(Mandatory = $false)]
    [string]$ExpectedVersion = '',

    [Parameter(Mandatory = $false)]
    [string]$RunId = ''
)

$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

$AppPath = [System.IO.Path]::GetFullPath($AppPath).TrimEnd('\')
$serviceName = 'UnitecErpServer'
$pendingRoot = Join-Path $AppPath 'storage\app\unitec-desktop-pending'
$pendingBin = Join-Path $pendingRoot 'bin'
$pendingFranken = Join-Path $pendingRoot 'frankenphp'
$manifestPath = Join-Path $pendingRoot 'manifest.json'
$readySignalPath = Join-Path $pendingRoot 'ready.signal'
# Status persistente: NAO fica dentro do pending (evita corrida com Remove-Item).
$statusPath = Join-Path $AppPath 'storage\framework\desktop-runtime-status.json'
$runtimeMarkerPath = Join-Path $AppPath 'storage\app\unitec-desktop-runtime.json'
$targetBin = Join-Path $AppPath 'bin'
$targetFranken = Join-Path $AppPath 'tools\frankenphp'
$serverExe = Join-Path $targetBin 'UnitecErpServer.exe'
$logPath = Join-Path $AppPath 'storage\logs\desktop-runtime-update.log'

function Write-Log([string]$Message) {
    $line = '{0} {1}' -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $Message
    try {
        $dir = Split-Path $logPath
        if (-not (Test-Path $dir)) {
            New-Item -ItemType Directory -Path $dir -Force | Out-Null
        }
        Add-Content -Path $logPath -Value $line -Encoding UTF8
    } catch { }
    Write-Host $line
}

function Write-Status {
    param(
        [Parameter(Mandatory = $true)][string]$State,
        [string]$Message = '',
        [string]$HealthVersion = ''
    )

    try {
        $dir = Split-Path $statusPath
        if (-not (Test-Path $dir)) {
            New-Item -ItemType Directory -Path $dir -Force | Out-Null
        }
        $payload = [ordered]@{
            state             = $State
            message           = $Message
            expected_version  = $ExpectedVersion
            health_version    = $HealthVersion
            run_id            = $RunId
            updated_at        = (Get-Date).ToUniversalTime().ToString('o')
        }
        $json = $payload | ConvertTo-Json -Compress
        $utf8NoBom = New-Object System.Text.UTF8Encoding $false
        [System.IO.File]::WriteAllText($statusPath, $json, $utf8NoBom)
    } catch {
        Write-Log ("falha ao gravar desktop-runtime-status.json: {0}" -f $_.Exception.Message)
    }
}

function Get-VersionParts([string]$Version) {
    if ([string]::IsNullOrWhiteSpace($Version)) {
        return @()
    }
    return @($Version.Trim().Split('.') | ForEach-Object {
            $n = 0
            if ([int]::TryParse($_, [ref]$n)) { $n } else { 0 }
        })
}

function Compare-UnitecVersions([string]$A, [string]$B) {
    $pa = @(Get-VersionParts $A)
    $pb = @(Get-VersionParts $B)
    $len = [Math]::Max($pa.Count, $pb.Count)
    for ($i = 0; $i -lt $len; $i++) {
        $va = if ($i -lt $pa.Count) { [int]$pa[$i] } else { 0 }
        $vb = if ($i -lt $pb.Count) { [int]$pb[$i] } else { 0 }
        if ($va -lt $vb) { return -1 }
        if ($va -gt $vb) { return 1 }
    }
    return 0
}

function Read-JsonFile([string]$Path) {
    if (-not (Test-Path $Path)) {
        return $null
    }
    try {
        return Get-Content -LiteralPath $Path -Raw -Encoding UTF8 | ConvertFrom-Json
    } catch {
        return $null
    }
}

function Get-PendingErpVersion {
    $manifest = Read-JsonFile $manifestPath
    if ($manifest -and $manifest.erp_version) {
        return [string]$manifest.erp_version
    }
    return $ExpectedVersion
}

function Get-InstalledRuntimeVersion {
    $marker = Read-JsonFile $runtimeMarkerPath
    if ($marker -and $marker.erp_version) {
        return [string]$marker.erp_version
    }
    return ''
}

function Write-RuntimeMarker([string]$Version) {
    try {
        $dir = Split-Path $runtimeMarkerPath
        if (-not (Test-Path $dir)) {
            New-Item -ItemType Directory -Path $dir -Force | Out-Null
        }
        $payload = [ordered]@{
            erp_version = $Version
            applied_at  = (Get-Date).ToUniversalTime().ToString('o')
            run_id      = $RunId
        }
        $json = $payload | ConvertTo-Json -Compress
        $utf8NoBom = New-Object System.Text.UTF8Encoding $false
        [System.IO.File]::WriteAllText($runtimeMarkerPath, $json, $utf8NoBom)
    } catch {
        Write-Log ("aviso: nao gravou unitec-desktop-runtime.json: {0}" -f $_.Exception.Message)
    }
}

function Test-HealthVersion([string]$Body, [string]$Expected) {
    if ([string]::IsNullOrWhiteSpace($Expected)) {
        return $true
    }
    try {
        $parsed = $Body | ConvertFrom-Json
        $reported = [string]$parsed.version
        if ([string]::IsNullOrWhiteSpace($reported)) {
            return $false
        }
        return ((Compare-UnitecVersions $reported $Expected) -eq 0)
    } catch {
        return $false
    }
}

function Clear-PendingFolder {
    try {
        if (Test-Path $pendingRoot) {
            Remove-Item -LiteralPath $pendingRoot -Recurse -Force -ErrorAction SilentlyContinue
        }
    } catch { }
}

try {
    Write-Log ("inicio AppPath=$AppPath ExpectedVersion=$ExpectedVersion RunId=$RunId")
    Write-Status -State 'running' -Message 'Iniciando aplicacao do runtime desktop.'

    if (-not (Test-Path (Join-Path $AppPath 'artisan'))) {
        throw "Instalacao do ERP nao encontrada em $AppPath."
    }

    if (-not (Test-Path (Join-Path $pendingBin 'UnitecErpServer.exe'))) {
        Write-Log "nada a fazer: pending sem UnitecErpServer.exe ($pendingBin)"
        Write-Status -State 'completed' -Message 'Sem runtime pendente.'
        exit 0
    }

    $pendingVersion = Get-PendingErpVersion
    if (-not [string]::IsNullOrWhiteSpace($ExpectedVersion) -and -not [string]::IsNullOrWhiteSpace($pendingVersion)) {
        if ((Compare-UnitecVersions $pendingVersion $ExpectedVersion) -ne 0) {
            throw ("Manifesto pendente ($pendingVersion) difere da versao esperada ($ExpectedVersion).")
        }
    }
    if ([string]::IsNullOrWhiteSpace($ExpectedVersion) -and -not [string]::IsNullOrWhiteSpace($pendingVersion)) {
        $ExpectedVersion = $pendingVersion
    }

    $installedRuntime = Get-InstalledRuntimeVersion
    if (-not [string]::IsNullOrWhiteSpace($installedRuntime) -and -not [string]::IsNullOrWhiteSpace($pendingVersion)) {
        if ((Compare-UnitecVersions $installedRuntime $pendingVersion) -gt 0) {
            Write-Log ("runtime instalado $installedRuntime > pendente $pendingVersion - nao sobrescreve")
            Write-Status -State 'skipped_newer' -Message ("Runtime instalado ($installedRuntime) ja e mais novo que o pendente ($pendingVersion).")
            Clear-PendingFolder
            exit 0
        }
    }

    # Aguarda sinal do apply PHP (caches prontos) antes de derrubar o servico.
    $waited = 0
    while (-not (Test-Path $readySignalPath) -and $waited -lt 120) {
        Start-Sleep -Seconds 1
        $waited++
    }
    if (-not (Test-Path $readySignalPath)) {
        Write-Log 'ready.signal ausente apos 120s - seguindo mesmo assim'
    } else {
        Write-Log ("ready.signal ok apos {0}s" -f $waited)
    }
    Start-Sleep -Seconds 2

    Write-Status -State 'running' -Message 'Parando UnitecErpServer para trocar binarios.'

    $service = Get-Service -Name $serviceName -ErrorAction SilentlyContinue
    if ($service -and $service.Status -ne 'Stopped') {
        Write-Log 'parando UnitecErpServer...'
        try {
            Stop-Service -Name $serviceName -Force -ErrorAction Stop
            $service.WaitForStatus('Stopped', [TimeSpan]::FromSeconds(45))
        } catch {
            Write-Log ("Stop-Service falhou: {0} - tentando sc stop" -f $_.Exception.Message)
            & sc.exe stop $serviceName | Out-Null
            Start-Sleep -Seconds 5
        }
    }

    Get-Process -Name 'Unitec ERP','UnitecErpServer','frankenphp' -ErrorAction SilentlyContinue |
        Stop-Process -Force -ErrorAction SilentlyContinue

    Get-CimInstance Win32_Process -Filter "Name='php.exe'" -ErrorAction SilentlyContinue |
        Where-Object { $_.CommandLine -and ($_.CommandLine -match '127\.0\.0\.1:8765|-S\s+0\.0\.0\.0:8765|-S\s+8765') } |
        ForEach-Object {
            Write-Log ("matando php legado PID={0}" -f $_.ProcessId)
            Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue
        }

    Start-Sleep -Seconds 2

    $backupDir = Join-Path $AppPath ('storage\backups\desktop-service-' + (Get-Date -Format 'yyyyMMdd-HHmmss'))
    if (Test-Path $targetBin) {
        New-Item -ItemType Directory -Path $backupDir -Force | Out-Null
        Copy-Item (Join-Path $targetBin '*') (Join-Path $backupDir 'bin') -Force -Recurse -ErrorAction SilentlyContinue
        Write-Log "backup bin -> $backupDir\bin"
    }
    if (Test-Path $targetFranken) {
        New-Item -ItemType Directory -Path $backupDir -Force | Out-Null
        Copy-Item (Join-Path $targetFranken '*') (Join-Path $backupDir 'frankenphp') -Force -Recurse -ErrorAction SilentlyContinue
        Write-Log "backup frankenphp -> $backupDir\frankenphp"
    }

    Write-Status -State 'running' -Message 'Copiando FrankenPHP e binarios pendentes.'

    if (Test-Path (Join-Path $pendingFranken 'frankenphp.exe')) {
        New-Item -ItemType Directory -Path $targetFranken -Force | Out-Null
        Copy-Item (Join-Path $pendingFranken '*') $targetFranken -Force -Recurse
        Write-Log "frankenphp aplicado de $pendingFranken"
    } else {
        Write-Log 'frankenphp pendente ausente - mantendo tools\frankenphp atual'
    }

    New-Item -ItemType Directory -Path $targetBin -Force | Out-Null
    Copy-Item (Join-Path $pendingBin '*') $targetBin -Force -Recurse
    Write-Log "binarios aplicados de $pendingBin"

    if (-not (Test-Path $serverExe)) {
        throw "Falha ao instalar $serverExe."
    }

    $binPath = "`"$serverExe`""
    if ($service) {
        & sc.exe config $serviceName binPath= $binPath start= auto | Out-Null
    } else {
        & sc.exe create $serviceName binPath= $binPath start= auto DisplayName= 'Unitec ERP Server' | Out-Null
        & sc.exe description $serviceName 'Mantem MariaDB embutido e Laravel (porta 8765) do Unitec ERP.' | Out-Null
    }

    $reg = "HKLM:\SYSTEM\CurrentControlSet\Services\$serviceName"
    if (Test-Path $reg) {
        New-ItemProperty -Path $reg -Name 'Environment' -PropertyType MultiString `
            -Value @("UNITEC_APP_PATH=$AppPath") -Force | Out-Null
    }

    Write-Status -State 'running' -Message 'Iniciando UnitecErpServer e aguardando health da versao alvo.'
    Write-Log 'iniciando UnitecErpServer...'
    Start-Service -Name $serviceName -ErrorAction SilentlyContinue
    & sc.exe start $serviceName | Out-Null

    $healthy = $false
    $healthBody = ''
    $healthVersion = ''
    for ($i = 0; $i -lt 90; $i++) {
        try {
            $response = Invoke-WebRequest -Uri 'http://127.0.0.1:8765/api/health' `
                -UseBasicParsing -TimeoutSec 2
            if ($response.StatusCode -eq 200) {
                $healthBody = [string]$response.Content
                if (Test-HealthVersion -Body $healthBody -Expected $ExpectedVersion) {
                    $healthy = $true
                    try {
                        $healthVersion = [string](($healthBody | ConvertFrom-Json).version)
                    } catch {
                        $healthVersion = $ExpectedVersion
                    }
                    Write-Log ("health OK: {0}" -f $healthBody)
                    break
                }
                Write-Log ("health 200 mas versao ainda nao confere (esperado=$ExpectedVersion): $healthBody")
            }
        } catch {
            # Servico ainda subindo apos reinicio.
        }
        Start-Sleep -Seconds 2
    }

    $listen = netstat -ano | Select-String ':8765' | Select-String 'LISTENING'
    Write-Log ("listener: {0}" -f (($listen | ForEach-Object { $_.Line.Trim() }) -join ' | '))

    if (-not $healthy) {
        throw 'Health nao confirmou a versao alvo apos troca do desktop. Pending preservado.'
    }

    if (-not [string]::IsNullOrWhiteSpace($ExpectedVersion)) {
        Write-RuntimeMarker -Version $ExpectedVersion
    } elseif (-not [string]::IsNullOrWhiteSpace($pendingVersion)) {
        Write-RuntimeMarker -Version $pendingVersion
    }

    # Grava status FINAL ANTES de apagar o pending (status fica em framework/).
    Write-Status -State 'completed' -Message 'Runtime desktop aplicado.' -HealthVersion $healthVersion
    Clear-PendingFolder

    Write-Log 'concluido'
    exit 0
} catch {
    $msg = $_.Exception.Message
    Write-Log ("FALHA: {0}" -f $msg)
    Write-Status -State 'failed' -Message $msg
    exit 1
}
