#Requires -Version 5.1
<#
.SYNOPSIS
  Reparo focado: HTTP 500 (PailServiceProvider) e views rename code:5.

.NOTES
  - Nao altera banco / nao roda migrate.
  - Nao mata PHP de outros sistemas (somente processos desta pasta Unitec).
  - Nao apaga caches em massa (so packages.php / services.php e views).
  - Compativel com instalacao 6.4.1.267 sem codigo novo do ERP.
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string]$AppPath,

    [Parameter(Mandatory = $true)]
    [ValidateSet('stop', 'views', 'pail', 'health', 'all')]
    [string]$Phase,

    [string]$PhpExe = ''
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Resolve-UnitecRepairApp {
    param([string]$Path)
    $p = $Path.Trim().TrimEnd('\', '/')
    if (-not (Test-Path -LiteralPath (Join-Path $p 'artisan'))) {
        throw "artisan nao encontrado em: $p"
    }
    return $p
}

function Get-UnitecRepairPort {
    param([string]$AppPath)
    $port = 8765
    $envFile = Join-Path $AppPath '.env'
    if (Test-Path -LiteralPath $envFile) {
        $raw = [IO.File]::ReadAllText($envFile)
        if ($raw -match '(?m)^APP_URL\s*=\s*.*:(\d+)') {
            $port = [int]$Matches[1]
        }
    }
    return $port
}

function Stop-UnitecScopedProcesses {
    param([string]$AppPath)

    $port = Get-UnitecRepairPort -AppPath $AppPath
    $killed = @{}

    $pidFile = Join-Path $AppPath 'storage\framework\.unitec-serve.pid'
    if (Test-Path -LiteralPath $pidFile) {
        $raw = Get-Content -LiteralPath $pidFile -Raw -ErrorAction SilentlyContinue
        if ($raw -match '(\d+)') {
            $procId = [int]$Matches[1]
            try { Stop-Process -Id $procId -Force -ErrorAction SilentlyContinue } catch {}
            $killed[$procId] = $true
        }
        Remove-Item -LiteralPath $pidFile -Force -ErrorAction SilentlyContinue
    }

    foreach ($name in @('php.exe', 'frankenphp.exe', 'UnitecErpServer.exe')) {
        Get-CimInstance Win32_Process -Filter ("Name='$name'") -ErrorAction SilentlyContinue | ForEach-Object {
            $cmd = [string]$_.CommandLine
            $exe = [string]$_.ExecutablePath
            $match = $false
            if ($cmd -and $cmd.IndexOf($AppPath, [StringComparison]::OrdinalIgnoreCase) -ge 0) {
                $match = $true
            }
            if (-not $match -and $exe -and $exe.IndexOf($AppPath, [StringComparison]::OrdinalIgnoreCase) -ge 0) {
                $match = $true
            }
            if (-not $match -and $name -eq 'frankenphp.exe' -and $cmd -and (
                    $cmd -match 'unitec-erp-frankenphp' -or
                    $cmd -match (":$port\b")
                )) {
                $match = $true
            }
            if ($match) {
                $procId = [int]$_.ProcessId
                if (-not $killed.ContainsKey($procId)) {
                    try { Stop-Process -Id $procId -Force -ErrorAction SilentlyContinue } catch {}
                    $killed[$procId] = $true
                }
            }
        }
    }

    try {
        Get-NetTCPConnection -LocalPort $port -ErrorAction SilentlyContinue |
            Select-Object -ExpandProperty OwningProcess -Unique |
            Where-Object { $_ -and $_ -gt 0 } |
            ForEach-Object {
                $procId = [int]$_
                $p = Get-CimInstance Win32_Process -Filter ("ProcessId=$procId") -ErrorAction SilentlyContinue
                if ($null -eq $p) { return }

                $cmd = [string]$p.CommandLine
                $exe = [string]$p.ExecutablePath
                $name = [string]$p.Name
                $owned =
                    ($cmd -and $cmd.IndexOf($AppPath, [StringComparison]::OrdinalIgnoreCase) -ge 0) -or
                    ($exe -and $exe.IndexOf($AppPath, [StringComparison]::OrdinalIgnoreCase) -ge 0) -or
                    ($name -match '^(frankenphp|UnitecErpServer|php)\.exe$')

                if ($owned -and -not $killed.ContainsKey($procId)) {
                    try { Stop-Process -Id $procId -Force -ErrorAction SilentlyContinue } catch {}
                    $killed[$procId] = $true
                }
            }
    } catch {}

    Write-Host ("Processos Unitec encerrados: {0} (porta {1})" -f $killed.Count, $port)
}

function Repair-UnitecViews {
    param([string]$AppPath)

    $framework = Join-Path $AppPath 'storage\framework'
    $views = Join-Path $framework 'views'

    New-Item -ItemType Directory -Force -Path (Join-Path $framework 'sessions') | Out-Null
    New-Item -ItemType Directory -Force -Path (Join-Path $framework 'cache\data') | Out-Null
    New-Item -ItemType Directory -Force -Path (Join-Path $AppPath 'storage\logs') | Out-Null
    New-Item -ItemType Directory -Force -Path (Join-Path $AppPath 'bootstrap\cache') | Out-Null

    if (Test-Path -LiteralPath $views) {
        $trashName = 'views_trash_' + [guid]::NewGuid().ToString('N').Substring(0, 8)
        try {
            Rename-Item -LiteralPath $views -NewName $trashName -ErrorAction Stop
            Write-Host 'Pasta views rotacionada (evita rename code:5).'
        } catch {
            Write-Host ('Aviso ao rotacionar views: ' + $_.Exception.Message)
        }
    }

    New-Item -ItemType Directory -Force -Path $views | Out-Null

    Get-ChildItem -LiteralPath $framework -Directory -Filter 'views_trash_*' -ErrorAction SilentlyContinue |
        ForEach-Object {
            Remove-Item -LiteralPath $_.FullName -Recurse -Force -ErrorAction SilentlyContinue
        }
}

function Test-ManifestHasPail {
    param([string]$Path)
    if (-not (Test-Path -LiteralPath $Path)) {
        return $false
    }
    $raw = [IO.File]::ReadAllText($Path)
    return (
        $raw -match 'PailServiceProvider' -or
        $raw -match 'laravel/pail' -or
        $raw -match 'Laravel\\\\Pail' -or
        $raw -match 'Laravel\\Pail'
    )
}

function Repair-UnitecPailCaches {
    param([string]$AppPath)

    # So remove o pacote que causa o HTTP 500 em producao (PailServiceProvider ausente).
    # Nao apagar pao/collision aqui: em DEV o Composer ainda referencia no autoload.
    $vendorDev = @(
        'vendor\laravel\pail'
    )

    foreach ($rel in $vendorDev) {
        $p = Join-Path $AppPath $rel
        if (Test-Path -LiteralPath $p) {
            Remove-Item -LiteralPath $p -Recurse -Force -ErrorAction SilentlyContinue
            Write-Host ("Removido residuo DEV: $rel")
        }
    }

    $cache = Join-Path $AppPath 'bootstrap\cache'
    $packages = Join-Path $cache 'packages.php'
    $services = Join-Path $cache 'services.php'

    # Alvo cirurgico: so estes dois manifests (nao apaga config.php/routes.php/filament).
    if (Test-Path -LiteralPath $packages) {
        Remove-Item -LiteralPath $packages -Force -ErrorAction SilentlyContinue
        Write-Host 'Removido bootstrap/cache/packages.php'
    }
    if (Test-Path -LiteralPath $services) {
        Remove-Item -LiteralPath $services -Force -ErrorAction SilentlyContinue
        Write-Host 'Removido bootstrap/cache/services.php'
    }
}

function Invoke-UnitecPackageDiscover {
    param(
        [string]$AppPath,
        [string]$PhpExe
    )

    if ([string]::IsNullOrWhiteSpace($PhpExe)) {
        $PhpExe = Join-Path $AppPath 'tools\php\php.exe'
    }
    if (-not (Test-Path -LiteralPath $PhpExe)) {
        throw "PHP nao encontrado: $PhpExe"
    }

    Push-Location -LiteralPath $AppPath
    try {
        Write-Host ("cwd={0}" -f (Get-Location).Path)
        Write-Host ("php={0}" -f $PhpExe)
        & $PhpExe artisan package:discover
        if ($LASTEXITCODE -ne 0) {
            throw "package:discover exit=$LASTEXITCODE"
        }
    } finally {
        Pop-Location
    }

    $packages = Join-Path $AppPath 'bootstrap\cache\packages.php'
    $services = Join-Path $AppPath 'bootstrap\cache\services.php'

    if (Test-ManifestHasPail -Path $packages) {
        Write-Host '[AVISO] packages.php ainda cita Pail - nova limpeza...'
        $pail = Join-Path $AppPath 'vendor\laravel\pail'
        if (Test-Path -LiteralPath $pail) {
            Remove-Item -LiteralPath $pail -Recurse -Force -ErrorAction SilentlyContinue
        }
        if (Test-Path -LiteralPath $packages) {
            Remove-Item -LiteralPath $packages -Force -ErrorAction SilentlyContinue
        }
        if (Test-Path -LiteralPath $services) {
            Remove-Item -LiteralPath $services -Force -ErrorAction SilentlyContinue
        }

        Push-Location -LiteralPath $AppPath
        try {
            & $PhpExe artisan package:discover
            if ($LASTEXITCODE -ne 0) {
                throw "package:discover (2a tentativa) exit=$LASTEXITCODE"
            }
        } finally {
            Pop-Location
        }
    }

    if (Test-ManifestHasPail -Path $packages) {
        throw 'packages.php continua com Pail apos rediscover.'
    }

    if (Test-ManifestHasPail -Path $services) {
        Write-Host '[AVISO] services.php ainda cita Pail - removendo para rebuild no boot...'
        Remove-Item -LiteralPath $services -Force -ErrorAction SilentlyContinue
    }

    Write-Host '[OK] Manifesto sem Pail.'
}

function Test-UnitecHealth {
    param([string]$AppPath)

    $port = Get-UnitecRepairPort -AppPath $AppPath
    $urls = @(
        ("http://127.0.0.1:{0}/api/health" -f $port),
        ("http://127.0.0.1:{0}/admin/login" -f $port)
    )

    foreach ($url in $urls) {
        try {
            $r = Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec 20
            $code = [int]$r.StatusCode
            Write-Host ("HTTP {0} {1}" -f $code, $url)
            if ($code -ge 500) { return 2 }
            if ($r.Content -match 'PailServiceProvider|Server Error|Whoops') { return 2 }
            if ($url -like '*/api/health' -and $r.Content -match '"status"\s*:\s*"ok"') { return 0 }
            if ($url -like '*/admin/login' -and $code -ge 200 -and $code -lt 400) { return 0 }
        } catch {
            $code = 0
            if ($_.Exception.Response) {
                $code = [int]$_.Exception.Response.StatusCode
            }
            Write-Host ("HTTP {0} {1} - {2}" -f $code, $url, $_.Exception.Message)
            if ($code -ge 500) { return 2 }
        }
    }

    return 1
}

function Start-UnitecRepairStack {
    param([string]$AppPath)

    $lib = Join-Path $AppPath 'scripts\unitec-install-lib.ps1'
    if (Test-Path -LiteralPath $lib) {
        Set-Location -LiteralPath $AppPath
        . $lib
        Ensure-UnitecStorageStructure -AppPath $AppPath
        Start-UnitecStack -AppPath $AppPath -WaitSeconds 30
        return
    }

    $launcher = Join-Path $AppPath 'Unitec ERP.bat'
    if (Test-Path -LiteralPath $launcher) {
        Write-Host 'Biblioteca ausente - iniciando Unitec ERP.bat...'
        Start-Process -FilePath $launcher
        Start-Sleep -Seconds 15
        return
    }

    throw 'Nao achei scripts\unitec-install-lib.ps1 nem Unitec ERP.bat'
}

$AppPath = Resolve-UnitecRepairApp -Path $AppPath

switch ($Phase) {
    'stop' { Stop-UnitecScopedProcesses -AppPath $AppPath }
    'views' { Repair-UnitecViews -AppPath $AppPath }
    'pail' { Repair-UnitecPailCaches -AppPath $AppPath }
    'health' { exit (Test-UnitecHealth -AppPath $AppPath) }
    'all' {
        Stop-UnitecScopedProcesses -AppPath $AppPath
        Start-Sleep -Seconds 2
        Repair-UnitecViews -AppPath $AppPath
        Repair-UnitecPailCaches -AppPath $AppPath
        Invoke-UnitecPackageDiscover -AppPath $AppPath -PhpExe $PhpExe
        Start-UnitecRepairStack -AppPath $AppPath
        Start-Sleep -Seconds 2
        $hc = Test-UnitecHealth -AppPath $AppPath
        if ($hc -ne 0) {
            exit $hc
        }
        $port = Get-UnitecRepairPort -AppPath $AppPath
        Write-Host ''
        Write-Host 'SISTEMA OK'
        Write-Host ("Login: http://127.0.0.1:{0}/admin/login" -f $port)
        exit 0
    }
}
