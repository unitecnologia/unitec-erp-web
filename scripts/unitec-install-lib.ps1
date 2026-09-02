$script:UnitecMinPhpVersionId = 80400
$script:UnitecMariaDbDownloadUrl = 'https://archive.mariadb.org/mariadb-11.4.5/winx64-packages/mariadb-11.4.5-winx64.zip'
$script:UnitecPhp84DownloadUrl = 'https://windows.php.net/downloads/releases/archives/php-8.4.12-Win32-vs17-x64.zip'
$script:UnitecVcRedistDownloadUrl = 'https://aka.ms/vs/17/release/vc_redist.x64.exe'
$script:UnitecCaCertDownloadUrl = 'https://curl.se/ca/cacert.pem'
$script:UnitecCaCertAssetName = 'cacert.pem'
$script:UnitecHeidiSqlSetupAssetName = 'HeidiSQL_12.18.0.7304_Setup.exe'
$script:UnitecHeidiSqlDownloadPageUrl = 'https://www.heidisql.com/download.php'
$script:UnitecPhp84FolderName = 'php-8.4.12-Win32-vs17-x64'
$script:UnitecNodeDownloadUrl = 'https://nodejs.org/dist/v20.19.2/node-v20.19.2-win-x64.zip'
$script:UnitecNodeFolderName = 'node-v20.19.2-win-x64'
$script:UnitecMariaDbFolderName = 'mariadb-11.4.5-winx64'
$script:UnitecToolsFolderName = 'tools'
$script:UnitecDefaultAppPath = 'C:\UNITECNOLOGIA_WEB'
$script:UnitecServeHost = '127.0.0.1'
# Host de bind do servidor web. 0.0.0.0 expÃµe o ERP na rede local (necessÃ¡rio
# para terminais e para o app ForÃ§a de Vendas). A porta 8765 ja e liberada no firewall.
$script:UnitecServeBindHost = '0.0.0.0'
$script:UnitecServePort = 8765
# Threads do FrankenPHP (HTTP). PHP CLI continua separado para artisan.
$script:UnitecServeWorkers = 8
$script:UnitecDefaultAppUrl = 'http://127.0.0.1:8765'
$script:UnitecServePidFileName = '.unitec-serve.pid'
$script:UnitecDefaultDbName = 'unitec_erp'
$script:UnitecDefaultDbUser = 'root'
$script:UnitecDefaultDbPassword = 'rua@2050bc'
$script:UnitecMinDiskSpaceMb = 2048
$script:UnitecInstallerAssetNames = @('mariadb-win.zip', 'php-8.4-win.zip', 'vc_redist.x64.exe', 'HeidiSQL_12.18.0.7304_Setup.exe', 'unitec-erp.ico', 'cacert.pem', 'cloudflare-install.env')
$script:UnitecCloudflareInstallEnvAssetName = 'cloudflare-install.env'

function Get-UnitecDefaultAppPath {
    return $script:UnitecDefaultAppPath
}

function Get-UnitecDefaultAppUrl {
    return $script:UnitecDefaultAppUrl
}

function Get-UnitecDefaultDbPassword {
    return $script:UnitecDefaultDbPassword
}

<#
.SYNOPSIS
    TEMP seguro para instalacao (evita caminho 8.3 quebrado tipo C:\Users\XXX~1).
    Expand-Archive e redirecoes usam $env:TEMP por baixo — precisa apontar para pasta fixa.
#>
function Initialize-UnitecSafeTempEnvironment {
    param([string]$AppPath = '')

    if ([string]::IsNullOrWhiteSpace($AppPath)) {
        $AppPath = $script:UnitecDefaultAppPath
    } else {
        $AppPath = Resolve-UnitecAppPath -Path $AppPath
    }

    $safeTemp = Join-Path $AppPath 'tools\_tmp'
    New-Item -ItemType Directory -Path $safeTemp -Force | Out-Null
    $safeTemp = (Get-Item -LiteralPath $safeTemp).FullName

    $env:TEMP = $safeTemp
    $env:TMP = $safeTemp

    return $safeTemp
}

function New-UnitecInstallTempDir {
    param(
        [string]$AppPath = '',
        [string]$Prefix = 'unitec'
    )

    $root = Initialize-UnitecSafeTempEnvironment -AppPath $AppPath
    $dir = Join-Path $root ("{0}-{1}" -f $Prefix, [Guid]::NewGuid().ToString('N'))
    New-Item -ItemType Directory -Path $dir -Force | Out-Null

    return (Get-Item -LiteralPath $dir).FullName
}

function Get-UnitecDefaultDatabaseSettings {
    return @{
        DbHost     = '127.0.0.1'
        DbPort     = '3306'
        DbName     = $script:UnitecDefaultDbName
        DbUser     = $script:UnitecDefaultDbUser
        DbPassword = $script:UnitecDefaultDbPassword
    }
}

function Get-UnitecCloudflareInstallEnvCandidates {
    param([string]$AppPath = '')

    $candidates = @()

    if (-not [string]::IsNullOrWhiteSpace($AppPath)) {
        $candidates += Join-Path (Resolve-UnitecAppPath -Path $AppPath) ("installer\assets\{0}" -f $script:UnitecCloudflareInstallEnvAssetName)
    }

    if (-not [string]::IsNullOrWhiteSpace($PSScriptRoot)) {
        $candidates += Join-Path $PSScriptRoot ("..\installer\assets\{0}" -f $script:UnitecCloudflareInstallEnvAssetName)
    }

    return @($candidates | Select-Object -Unique)
}

function Read-UnitecCloudflareInstallEnvFile {
    param([string]$AppPath = '')

    $defaults = @{
        CLOUDFLARE_API_TOKEN     = ''
        CLOUDFLARE_ACCOUNT_ID    = '28103ae19943f8c0654a17b56e75b5da'
        CLOUDFLARE_ZONE_ID       = 'a68a06560133f1b620e063cd0b0113ff'
        CLOUDFLARE_BASE_DOMAIN   = 'unierp.uk'
        CLOUDFLARE_LOCAL_SERVICE = 'http://127.0.0.1:8765'
    }

    foreach ($path in (Get-UnitecCloudflareInstallEnvCandidates -AppPath $AppPath)) {
        if (-not (Test-Path -LiteralPath $path)) {
            continue
        }

        foreach ($line in (Get-Content -LiteralPath $path -Encoding UTF8 -ErrorAction SilentlyContinue)) {
            $trimmed = $line.Trim()
            if ($trimmed -eq '' -or $trimmed.StartsWith('#')) {
                continue
            }

            $eq = $trimmed.IndexOf('=')
            if ($eq -lt 1) {
                continue
            }

            $key = $trimmed.Substring(0, $eq).Trim()
            $value = $trimmed.Substring($eq + 1).Trim()
            if ($value.StartsWith('"') -and $value.EndsWith('"') -and $value.Length -ge 2) {
                $value = $value.Substring(1, $value.Length - 2)
            }

            if ($defaults.ContainsKey($key) -and $value -ne '') {
                $defaults[$key] = $value
            }
        }

        break
    }

    return $defaults
}

function Get-UnitecFreshInstallEnvReplacements {
    param(
        [string]$AppPath,
        [string]$AppUrl,
        [string]$DbHost,
        [string]$DbPort,
        [string]$DbName,
        [string]$DbUser,
        [string]$DbPassword
    )

    $cloudflare = Read-UnitecCloudflareInstallEnvFile -AppPath $AppPath

    return @{
        '__APP_URL__'                = $AppUrl
        '__DB_HOST__'                = $DbHost
        '__DB_PORT__'                = $DbPort
        '__DB_DATABASE__'            = $DbName
        '__DB_USERNAME__'            = $DbUser
        '__DB_PASSWORD__'            = (Format-EnvValue $DbPassword)
        '__CLOUDFLARE_API_TOKEN__'     = (Format-EnvValue $cloudflare.CLOUDFLARE_API_TOKEN)
        '__CLOUDFLARE_ACCOUNT_ID__'    = $cloudflare.CLOUDFLARE_ACCOUNT_ID
        '__CLOUDFLARE_ZONE_ID__'       = $cloudflare.CLOUDFLARE_ZONE_ID
        '__CLOUDFLARE_BASE_DOMAIN__'   = $cloudflare.CLOUDFLARE_BASE_DOMAIN
        '__CLOUDFLARE_LOCAL_SERVICE__' = $cloudflare.CLOUDFLARE_LOCAL_SERVICE
    }
}

function Get-UnitecToolsPath {
    param([string]$AppPath)

    return Join-Path (Resolve-UnitecAppPath -Path $AppPath) $script:UnitecToolsFolderName
}

function Get-UnitecPhpDirectory {
    param([string]$AppPath)

    $phpRoot = Join-Path (Get-UnitecToolsPath $AppPath) 'php'
    if (Test-Path (Join-Path $phpRoot 'php.exe')) {
        return $phpRoot
    }

    $nested = Get-ChildItem $phpRoot -Directory -ErrorAction SilentlyContinue |
        Where-Object { Test-Path (Join-Path $_.FullName 'php.exe') } |
        Sort-Object { Get-PhpVersionIdFromFolderName $_.Name } -Descending |
        Select-Object -First 1

    if ($nested) {
        return $nested.FullName
    }

    return $null
}

function Get-UnitecMysqlRoot {
    param([string]$AppPath)

    return Join-Path (Get-UnitecToolsPath $AppPath) 'mysql'
}

function Test-UnitecEmbeddedRuntimeInstalled {
    param([string]$AppPath)

    $AppPath = Resolve-UnitecAppPath -Path $AppPath
    return ($null -ne (Get-UnitecPhpDirectory -AppPath $AppPath)) -and
        (Test-UnitecEmbeddedMysqlReady -AppPath $AppPath)
}

function Test-UnitecEmbeddedMysqlReady {
    param([string]$AppPath)

    $mysqlBin = Join-Path (Get-UnitecMysqlRoot -AppPath $AppPath) 'bin'
    $mysqld = Join-Path $mysqlBin 'mysqld.exe'

    if (-not (Test-Path $mysqld)) {
        return $false
    }

    return $null -ne (Get-MariadbInstallDbExecutable -MysqlBin $mysqlBin)
}

function Test-UnitecMariaDbZipValid {
    param([string]$ZipPath)

    if (-not (Test-Path $ZipPath)) {
        return $false
    }

    if ((Get-Item $ZipPath).Length -lt 50MB) {
        return $false
    }

    try {
        Add-Type -AssemblyName System.IO.Compression.FileSystem
        $zip = [System.IO.Compression.ZipFile]::OpenRead($ZipPath)
        try {
            $hasMysqld = $false
            $hasInstallDb = $false

            foreach ($entry in $zip.Entries) {
                $name = ($entry.FullName -replace '\\', '/').TrimStart('/')
                if ($name -match '(?i)(^|/)bin/mysqld\.exe$') {
                    $hasMysqld = $true
                }
                if ($name -match '(?i)(^|/)bin/(mariadb-install-db|mysql_install_db)\.exe$') {
                    $hasInstallDb = $true
                }
            }

            return ($hasMysqld -and $hasInstallDb)
        } finally {
            $zip.Dispose()
        }
    } catch {
        return $false
    }
}

function Assert-UnitecMariaDbZipAsset {
    param([string]$ZipPath)

    if (Test-UnitecMariaDbZipValid -ZipPath $ZipPath) {
        return
    }

    $sizeMb = if (Test-Path $ZipPath) {
        [math]::Round((Get-Item $ZipPath).Length / 1MB, 1)
    } else {
        0
    }

    throw @"
Pacote MariaDB invalido ou incompleto: $ZipPath (${sizeMb} MB).
Baixe o ZIP oficial winx64 (nao use o MSI):
  $($script:UnitecMariaDbDownloadUrl)
Salve como installer\assets\mariadb-win.zip
O ZIP deve conter bin\mysqld.exe e bin\mariadb-install-db.exe
"@
}

function Resolve-MariaDbZipPath {
    param([string]$SourceRoot)

    $candidates = @()
    if (-not [string]::IsNullOrWhiteSpace($SourceRoot)) {
        $candidates += (Join-Path $SourceRoot 'installer\assets\mariadb-win.zip')
        $candidates += (Join-Path $SourceRoot "installer\assets\$($script:UnitecMariaDbFolderName).zip")
    }
    $candidates += (Join-Path $PSScriptRoot '..\installer\assets\mariadb-win.zip')

    foreach ($path in $candidates) {
        if ([string]::IsNullOrWhiteSpace($path)) {
            continue
        }

        $full = [System.IO.Path]::GetFullPath($path)
        if (Test-UnitecPathExists $full) {
            return $full
        }
    }

    $downloaded = Join-Path $env:TEMP 'unitec-mariadb-win.zip'
    if (Test-Path $downloaded) {
        return $downloaded
    }

    Write-Host 'Baixando MariaDB (~80 MB)...' -ForegroundColor Yellow
    Invoke-WebRequest -Uri $script:UnitecMariaDbDownloadUrl -OutFile $downloaded -UseBasicParsing
    return $downloaded
}

function New-UnitecMysqlIniFile {
    param(
        [string]$AppPath,
        [string]$MysqlRoot
    )

    $dataDir = Join-Path $MysqlRoot 'data'
    Ensure-Directory $dataDir

    $basedir = ($MysqlRoot -replace '\\', '/')
    $datadir = ($dataDir -replace '\\', '/')
    $content = @"
[client]
port=3306
default-character-set=utf8mb4

[mysqld]
basedir=$basedir
datadir=$datadir
port=3306
bind-address=0.0.0.0
skip-name-resolve
character-set-server=utf8mb4
collation-server=utf8mb4_unicode_ci
innodb_buffer_pool_size=256M
innodb_log_buffer_size=8M
max_connections=200
wait_timeout=120
interactive_timeout=120
key_buffer_size=8M
"@

    $iniPath = Join-Path $MysqlRoot 'my.ini'
    Set-Content -Path $iniPath -Value $content -Encoding ASCII
    return $iniPath
}

function Stop-UnitecEmbeddedMysql {
    param([string]$AppPath = '')

    $targetRoot = $null
    if (-not [string]::IsNullOrWhiteSpace($AppPath)) {
        try {
            $targetRoot = (Get-UnitecMysqlRoot -AppPath (Resolve-UnitecAppPath -Path $AppPath)).TrimEnd('\').ToLowerInvariant()
        } catch {
            $targetRoot = $null
        }
    }

    $stopped = $false
    $processes = @(Get-Process mysqld -ErrorAction SilentlyContinue)

    foreach ($proc in $processes) {
        $procPath = ''
        try {
            if ($null -ne $proc.Path) {
                $procPath = ([string]$proc.Path).ToLowerInvariant()
            }
        } catch {
            $procPath = ''
        }

        $shouldStop = $true
        if ($targetRoot -and $procPath -ne '') {
            $shouldStop = $procPath.StartsWith($targetRoot)
        }

        if ($shouldStop) {
            try {
                $proc | Stop-Process -Force -ErrorAction Stop
                $stopped = $true
            } catch {
                # ignora; sera tratado no retry de remocao
            }
        }
    }

    if ($stopped) {
        Start-Sleep -Seconds 2
    }
}

function Remove-UnitecRuntimeDirectory {
    param(
        [string]$Path,
        [string]$AppPath = ''
    )

    if ([string]::IsNullOrWhiteSpace($Path) -or -not (Test-Path $Path)) {
        return
    }

    for ($attempt = 1; $attempt -le 3; $attempt++) {
        try {
            Remove-Item $Path -Recurse -Force -ErrorAction Stop
            return
        } catch {
            if ($attempt -eq 1) {
                Stop-UnitecEmbeddedMysql -AppPath $AppPath
            } else {
                Start-Sleep -Seconds 2
            }

            if ($attempt -eq 3) {
                throw "Nao foi possivel substituir o runtime em $Path. Feche o sistema (mysqld.exe/php.exe) ou reinicie o computador e tente novamente. Detalhe: $($_.Exception.Message)"
            }
        }
    }
}

function Install-UnitecMysqlFromZip {
    param(
        [string]$AppPath,
        [string]$ZipPath
    )

    if ([string]::IsNullOrWhiteSpace($ZipPath) -or -not (Test-UnitecPathExists $ZipPath)) {
        throw "Pacote MariaDB nao encontrado: $ZipPath"
    }

    $AppPath = Resolve-UnitecAppPath -Path $AppPath
    $targetRoot = Get-UnitecMysqlRoot -AppPath $AppPath
    Ensure-Directory (Split-Path $targetRoot)

    Stop-UnitecEmbeddedMysql -AppPath $AppPath
    Remove-UnitecRuntimeDirectory -Path $targetRoot -AppPath $AppPath

    $tempDir = New-UnitecInstallTempDir -AppPath $AppPath -Prefix 'mysql-extract'
    try {
        Expand-Archive -LiteralPath $ZipPath -DestinationPath $tempDir -Force

        $extracted = Get-ChildItem $tempDir -Directory -ErrorAction SilentlyContinue |
            Where-Object { Test-Path (Join-Path $_.FullName 'bin\mysqld.exe') } |
            Select-Object -First 1

        if (-not $extracted -and (Test-Path (Join-Path $tempDir 'bin\mysqld.exe'))) {
            $extracted = Get-Item $tempDir
        }

        if (-not $extracted) {
            throw 'Pacote MariaDB invalido (mysqld.exe nao encontrado).'
        }

        Move-Item $extracted.FullName $targetRoot -Force
        New-UnitecMysqlIniFile -AppPath $AppPath -MysqlRoot $targetRoot | Out-Null

        $mysqlBin = Join-Path $targetRoot 'bin'
        if (-not (Test-UnitecEmbeddedMysqlReady -AppPath $AppPath)) {
            throw @"
Pacote MariaDB extraido sem binarios completos em tools\mysql\bin.
Verifique installer\assets\mariadb-win.zip (deve ser o ZIP winx64 oficial).
"@
        }

        Write-Ok 'MariaDB instalado em tools\mysql.'
    } finally {
        Remove-Item -LiteralPath $tempDir -Recurse -Force -ErrorAction SilentlyContinue
    }
}

function Install-UnitecPhpFromZip {
    param(
        [string]$AppPath,
        [string]$ZipPath,
        [string]$ExpectedFolderName = $script:UnitecPhp84FolderName
    )

    if ([string]::IsNullOrWhiteSpace($ZipPath) -or -not (Test-UnitecPathExists $ZipPath)) {
        throw "Pacote PHP nao encontrado: $ZipPath"
    }

    $AppPath = Resolve-UnitecAppPath -Path $AppPath
    $phpRoot = Join-Path (Get-UnitecToolsPath $AppPath) 'php'
    Ensure-Directory (Split-Path $phpRoot)

    if (Test-Path $phpRoot) {
        Remove-Item $phpRoot -Recurse -Force
    }

    Ensure-Directory $phpRoot

    $tempDir = New-UnitecInstallTempDir -AppPath $AppPath -Prefix 'php-extract'
    try {
        Expand-Archive -LiteralPath $ZipPath -DestinationPath $tempDir -Force

        if (Test-Path (Join-Path $tempDir 'php.exe')) {
            Get-ChildItem $tempDir -Force | Move-Item -Destination $phpRoot -Force
        } else {
            $extracted = Get-ChildItem $tempDir -Directory | Select-Object -First 1
            if (-not $extracted -or -not (Test-Path (Join-Path $extracted.FullName 'php.exe'))) {
                throw 'Pacote PHP invalido (php.exe nao encontrado).'
            }
            Get-ChildItem $extracted.FullName -Force | Move-Item -Destination $phpRoot -Force
        }

        Configure-LaragonPhpIni -PhpDirectory $phpRoot -SourceRoot $AppPath
        Write-Ok 'PHP instalado em tools\php.'
        return $phpRoot
    } finally {
        Remove-Item -LiteralPath $tempDir -Recurse -Force -ErrorAction SilentlyContinue
    }
}

function Ensure-UnitecPhp84 {
    param(
        [string]$AppPath,
        [string]$SourceRoot = ''
    )

    $AppPath = Resolve-UnitecAppPath -Path $AppPath
    if ([string]::IsNullOrWhiteSpace($SourceRoot)) {
        $SourceRoot = $AppPath
    }

    $phpDir = Get-UnitecPhpDirectory -AppPath $AppPath
    if ($phpDir) {
        Configure-LaragonPhpIni -PhpDirectory $phpDir -SourceRoot $SourceRoot
        $phpExe = Join-Path $phpDir 'php.exe'
        if (Test-Path $phpExe) {
            $phpTest = Repair-PhpExecutableRuntime -SourceRoot $SourceRoot -PhpExe $phpExe -AllowFix
            if ($phpTest.Ok) {
                Write-Ok ('PHP {0} ativo' -f $phpTest.Version)
            } elseif (-not [string]::IsNullOrWhiteSpace($phpTest.Error)) {
                Write-Warn $phpTest.Error
            }
        }
        return $phpDir
    }

    Write-Host 'Instalando PHP 8.4 (requerido pelo Unitec ERP)...' -ForegroundColor White
    $zipPath = Resolve-Php84ZipPath -SourceRoot $SourceRoot
    $phpDir = Install-UnitecPhpFromZip -AppPath $AppPath -ZipPath $zipPath
    $phpExe = Join-Path $phpDir 'php.exe'

    if (Test-Path $phpExe) {
        $phpTest = Repair-PhpExecutableRuntime -SourceRoot $SourceRoot -PhpExe $phpExe -AllowFix
        if ($phpTest.Ok) {
            Write-Ok ('PHP {0} instalado.' -f $phpTest.Version)
        } else {
            throw ('PHP instalado mas nao executa: {0}' -f $phpTest.Error)
        }
    }

    return $phpDir
}

function Test-UnitecRemoteDatabaseHost {
    param([string]$HostName = '')

    if ([string]::IsNullOrWhiteSpace($HostName)) {
        return $false
    }

    $normalized = $HostName.Trim().Trim('"').Trim("'").ToLowerInvariant()

    return $normalized -notin @('127.0.0.1', 'localhost', '::1')
}

function Test-UnitecLocalDatabaseHost {
    param([string]$HostName = '127.0.0.1')

    return -not (Test-UnitecRemoteDatabaseHost -HostName $HostName)
}

function Update-UnitecMysqlIniForNetworkAccess {
    param([string]$AppPath)

    $AppPath = Resolve-UnitecAppPath -Path $AppPath
    $mysqlRoot = Get-UnitecMysqlRoot -AppPath $AppPath
    $iniPath = Join-Path $mysqlRoot 'my.ini'

    if (-not (Test-Path $iniPath)) {
        return $false
    }

    $content = Get-Content $iniPath -Raw -Encoding ASCII
    if ($content -match '(?m)^\s*bind-address\s*=\s*0\.0\.0\.0\s*$') {
        return $false
    }

    $updated = $content -replace '(?m)^\s*bind-address\s*=\s*127\.0\.0\.1\s*$', 'bind-address=0.0.0.0'
    if ($updated -eq $content -and $content -notmatch '(?m)^\s*bind-address\s*=') {
        $updated = $content.TrimEnd() + [Environment]::NewLine + 'bind-address=0.0.0.0' + [Environment]::NewLine
    }

    if ($updated -eq $content) {
        return $false
    }

    Set-Content -Path $iniPath -Value $updated -Encoding ASCII
    return $true
}

function Update-UnitecMysqlIniPerformance {
    param([string]$AppPath)

    $AppPath = Resolve-UnitecAppPath -Path $AppPath
    $iniPath = Join-Path (Get-UnitecMysqlRoot -AppPath $AppPath) 'my.ini'

    if (-not (Test-Path $iniPath)) {
        return $false
    }

    $content = Get-Content $iniPath -Raw -Encoding ASCII
    $updated = $content

    if ($updated -match '(?m)^innodb_buffer_pool_size\s*=') {
        $updated = $updated -replace '(?m)^innodb_buffer_pool_size\s*=\s*\d+[MmGg]?\s*$', 'innodb_buffer_pool_size=256M'
    } else {
        $updated = $updated.TrimEnd() + [Environment]::NewLine + 'innodb_buffer_pool_size=256M' + [Environment]::NewLine
    }

    if ($updated -match '(?m)^max_connections\s*=') {
        $updated = $updated -replace '(?m)^max_connections\s*=\s*\d+\s*$', 'max_connections=200'
    } else {
        $updated = $updated.TrimEnd() + [Environment]::NewLine + 'max_connections=200' + [Environment]::NewLine
    }

    if ($updated -match '(?m)^wait_timeout\s*=') {
        $updated = $updated -replace '(?m)^wait_timeout\s*=\s*\d+\s*$', 'wait_timeout=120'
    } else {
        $updated = $updated.TrimEnd() + [Environment]::NewLine + 'wait_timeout=120' + [Environment]::NewLine
    }

    if ($updated -match '(?m)^interactive_timeout\s*=') {
        $updated = $updated -replace '(?m)^interactive_timeout\s*=\s*\d+\s*$', 'interactive_timeout=120'
    } else {
        $updated = $updated.TrimEnd() + [Environment]::NewLine + 'interactive_timeout=120' + [Environment]::NewLine
    }

    if ($updated -eq $content) {
        return $false
    }

    Set-Content -Path $iniPath -Value $updated -Encoding ASCII
    return $true
}

function Test-UnitecIsAdministrator {
    try {
        $identity = [Security.Principal.WindowsIdentity]::GetCurrent()
        $principal = New-Object Security.Principal.WindowsPrincipal($identity)
        return $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
    } catch {
        return $false
    }
}

function Get-UnitecLanIPv4Address {
    try {
        $ip = Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue |
            Where-Object {
                $_.IPAddress -match '^(192\.168\.|10\.|172\.(1[6-9]|2[0-9]|3[0-1])\.)' -and
                $_.PrefixOrigin -ne 'WellKnown'
            } |
            Sort-Object -Property InterfaceMetric, IPAddress |
            Select-Object -First 1 -ExpandProperty IPAddress

        if (-not [string]::IsNullOrWhiteSpace($ip)) {
            return [string]$ip
        }
    } catch {}

    try {
        $ip = (Get-CimInstance Win32_NetworkAdapterConfiguration -ErrorAction SilentlyContinue |
            Where-Object { $_.IPEnabled -and $_.IPAddress } |
            ForEach-Object { $_.IPAddress } |
            Where-Object { $_ -match '^(192\.168\.|10\.|172\.(1[6-9]|2[0-9]|3[0-1])\.)' } |
            Select-Object -First 1)

        if (-not [string]::IsNullOrWhiteSpace($ip)) {
            return [string]$ip
        }
    } catch {}

    return $null
}

function Ensure-UnitecFirewallTcpRule {
    param(
        [Parameter(Mandatory = $true)][string]$RuleName,
        [Parameter(Mandatory = $true)][int]$Port,
        [string]$Description = '',
        [switch]$Quiet
    )

    if ($Port -lt 1 -or $Port -gt 65535) {
        throw "Porta invalida para firewall: $Port"
    }

    if (-not (Test-UnitecIsAdministrator)) {
        if (-not $Quiet) {
            Write-Warn ("Nao foi possivel liberar a porta {0} no firewall (rode o instalador como Administrador)." -f $Port)
        }
        return $false
    }

    $null = & netsh advfirewall firewall delete rule name="$RuleName" 2>$null

    $args = @(
        'advfirewall', 'firewall', 'add', 'rule',
        "name=$RuleName",
        'dir=in',
        'action=allow',
        'protocol=TCP',
        "localport=$Port",
        'profile=any',
        'enable=yes'
    )

    if (-not [string]::IsNullOrWhiteSpace($Description)) {
        $args += "description=$Description"
    }

    & netsh @args | Out-Null

    if ($LASTEXITCODE -ne 0) {
        if (-not $Quiet) {
            Write-Warn ("Falha ao liberar a porta {0} no firewall do Windows." -f $Port)
        }
        return $false
    }

    if (-not $Quiet) {
        Write-Ok ("Firewall: porta {0} liberada para a rede local ({1})." -f $Port, $RuleName)
    }
    return $true
}

function Register-UnitecMariaDbFirewallRule {
    param([switch]$Quiet)

    Ensure-UnitecFirewallTcpRule `
        -RuleName 'Unitec ERP MariaDB (porta 3306)' `
        -Port 3306 `
        -Description 'Permite terminais/acesso remoto ao MariaDB do Unitec ERP.' `
        -Quiet:$Quiet | Out-Null
}

function Initialize-UnitecNetworkDatabaseServer {
    param(
        [string]$AppPath,
        [switch]$RestartMysql
    )

    $AppPath = Resolve-UnitecAppPath -Path $AppPath
    $iniChanged = Update-UnitecMysqlIniForNetworkAccess -AppPath $AppPath
    $perfChanged = Update-UnitecMysqlIniPerformance -AppPath $AppPath

    if ($iniChanged -or $perfChanged -or $RestartMysql) {
        Get-Process mysqld -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue
        Start-Sleep -Seconds 2
        $null = Ensure-LaragonMysqlRunning -AppPath $AppPath -ThrowOnFailure
    }

    Register-UnitecMariaDbFirewallRule
    Write-Ok 'MariaDB pronto para conexoes na rede local (porta 3306).'
}

function Ensure-UnitecRuntimeInstalled {
    param(
        [string]$AppPath,
        [string]$SourceRoot = '',
        [switch]$SkipMysql
    )

    $AppPath = Resolve-UnitecAppPath -Path $AppPath
    if ([string]::IsNullOrWhiteSpace($SourceRoot)) {
        $SourceRoot = $AppPath
    }

    Ensure-Directory (Get-UnitecToolsPath $AppPath)
    $null = Ensure-UnitecPhp84 -AppPath $AppPath -SourceRoot $SourceRoot

    if ($SkipMysql) {
        return
    }

    if (-not (Test-UnitecEmbeddedMysqlReady -AppPath $AppPath)) {
        $mysqlRoot = Get-UnitecMysqlRoot -AppPath $AppPath
        if (Test-Path $mysqlRoot) {
            Write-Warn 'MariaDB embutido incompleto (faltam binarios). Reinstalando a partir do pacote...'
            Stop-UnitecEmbeddedMysql -AppPath $AppPath
            Remove-UnitecRuntimeDirectory -Path $mysqlRoot -AppPath $AppPath
        }

        Write-Host 'Instalando MySQL (MariaDB)...' -ForegroundColor White
        $zipPath = Resolve-MariaDbZipPath -SourceRoot $SourceRoot
        Assert-UnitecMariaDbZipAsset -ZipPath $zipPath
        Install-UnitecMysqlFromZip -AppPath $AppPath -ZipPath $zipPath

        if (-not (Test-UnitecEmbeddedMysqlReady -AppPath $AppPath)) {
            throw 'MariaDB instalado, mas bin\mysqld.exe ou bin\mariadb-install-db.exe continuam ausentes. Verifique antivirus ou o pacote mariadb-win.zip.'
        }
    }
}

function Resolve-UnitecAppPath {
    param(
        [string]$Path = '',
        [string]$FallbackFromScriptRoot = ''
    )

    if ([string]::IsNullOrWhiteSpace($Path)) {
        if (-not [string]::IsNullOrWhiteSpace($FallbackFromScriptRoot)) {
            $Path = Join-Path $FallbackFromScriptRoot '..'
        } else {
            $Path = Get-UnitecDefaultAppPath
        }
    }

    $Path = $Path.Trim().Trim('"')

    try {
        return [System.IO.Path]::GetFullPath($Path)
    } catch {
        throw "Caminho invalido: $Path"
    }
}

function Ensure-Directory {
    param([string]$Path)

    if ([string]::IsNullOrWhiteSpace($Path)) {
        throw 'Caminho de pasta invalido (vazio).'
    }

    if (-not (Test-Path $Path)) {
        New-Item -ItemType Directory -Path $Path -Force | Out-Null
    }
}

function Set-UnitecUtf8NoBomFile {
    param(
        [string]$Path,
        [string]$Content
    )

    $directory = Split-Path $Path -Parent
    if (-not [string]::IsNullOrWhiteSpace($directory)) {
        Ensure-Directory $directory
    }

    $utf8NoBom = New-Object System.Text.UTF8Encoding $false
    [System.IO.File]::WriteAllText($Path, $Content, $utf8NoBom)
}

function Get-UnitecTrimmedFileContent {
    param([string]$Path)

    if (-not (Test-Path $Path)) {
        return ''
    }

    $raw = Get-Content $Path -Raw -ErrorAction SilentlyContinue
    if ($null -eq $raw) {
        return ''
    }

    return $raw.Trim()
}

function Test-UnitecPhpSourcesWithoutBom {
    param([string]$Root)

    $rootFull = [System.IO.Path]::GetFullPath($Root).TrimEnd('\')
    $appPath = Join-Path $rootFull 'app'
    if (-not (Test-Path $appPath)) {
        return @()
    }

    $invalid = New-Object System.Collections.Generic.List[string]
    Get-ChildItem $appPath -Recurse -Filter '*.php' -File -ErrorAction SilentlyContinue | ForEach-Object {
        $bytes = [System.IO.File]::ReadAllBytes($_.FullName)
        if ($bytes.Length -ge 3 -and $bytes[0] -eq 0xEF -and $bytes[1] -eq 0xBB -and $bytes[2] -eq 0xBF) {
            $invalid.Add($_.FullName.Substring($rootFull.Length).TrimStart('\'))
        }
    }

    return @($invalid)
}

function Get-UnitecPhpExecutable {
    param([string]$AppPath = '')

    if (-not [string]::IsNullOrWhiteSpace($AppPath)) {
        try {
            $phpDir = Get-UnitecPhpDirectory -AppPath (Resolve-UnitecAppPath -Path $AppPath)
            if ($phpDir) {
                $embedded = Join-Path $phpDir 'php.exe'
                if (Test-Path $embedded) {
                    return $embedded
                }
            }
        } catch {
            # fallback abaixo
        }
    }

    $cmd = Get-Command php -ErrorAction SilentlyContinue
    if ($cmd) {
        return $cmd.Source
    }

    return 'php'
}

function Get-UnitecNodeDirectory {
    param([string]$AppPath)

    $nodeRoot = Join-Path (Get-UnitecToolsPath $AppPath) 'node'
    if (Test-Path (Join-Path $nodeRoot 'node.exe')) {
        return $nodeRoot
    }

    $nested = Get-ChildItem $nodeRoot -Directory -ErrorAction SilentlyContinue |
        Where-Object { $_.Name -like 'node-v*-win-x64' -and (Test-Path (Join-Path $_.FullName 'node.exe')) } |
        Sort-Object Name -Descending |
        Select-Object -First 1

    if ($nested) {
        return $nested.FullName
    }

    return $null
}

function Get-UnitecNodeExecutable {
    param([string]$AppPath = '')

    if (-not [string]::IsNullOrWhiteSpace($AppPath)) {
        try {
            $nodeDir = Get-UnitecNodeDirectory -AppPath (Resolve-UnitecAppPath -Path $AppPath)
            if ($nodeDir) {
                $embedded = Join-Path $nodeDir 'node.exe'
                if (Test-Path $embedded) {
                    return $embedded
                }
            }
        } catch {
            # fallback abaixo
        }
    }

    $cmd = Get-Command node -ErrorAction SilentlyContinue
    if ($cmd -and $cmd.Source -and (Test-Path $cmd.Source)) {
        return $cmd.Source
    }

    foreach ($candidate in @(
        (Join-Path ${env:ProgramFiles} 'nodejs\node.exe'),
        (Join-Path ${env:ProgramFiles(x86)} 'nodejs\node.exe'),
        (Join-Path $env:LOCALAPPDATA 'Programs\node\node.exe')
    )) {
        if ($candidate -and (Test-Path $candidate)) {
            return $candidate
        }
    }

    return $null
}

function Get-UnitecNpmExecutable {
    param([string]$AppPath = '')

    $nodeExe = Get-UnitecNodeExecutable -AppPath $AppPath
    if (-not $nodeExe) {
        return $null
    }

    $nodeDir = Split-Path $nodeExe -Parent
    $npmCmd = Join-Path $nodeDir 'npm.cmd'
    if (Test-Path $npmCmd) {
        return $npmCmd
    }

    $cmd = Get-Command npm -ErrorAction SilentlyContinue
    if ($cmd -and $cmd.Source -and (Test-Path $cmd.Source)) {
        return $cmd.Source
    }

    return $null
}

function Test-UnitecNodeRuntimeInstalled {
    param([string]$AppPath)

    return $null -ne (Get-UnitecNodeDirectory -AppPath $AppPath)
}

function Ensure-UnitecNodeRuntime {
    param(
        [string]$AppPath,
        [string]$SourceRoot = ''
    )

    $AppPath = Resolve-UnitecAppPath -Path $AppPath
    if (Test-UnitecNodeRuntimeInstalled -AppPath $AppPath) {
        return (Get-UnitecNodeExecutable -AppPath $AppPath)
    }

    $existing = Get-UnitecNodeExecutable -AppPath $AppPath
    if ($existing) {
        return $existing
    }

    $toolsNode = Join-Path (Get-UnitecToolsPath $AppPath) 'node'
    New-Item -ItemType Directory -Force -Path $toolsNode | Out-Null

    $assetZip = Join-Path $toolsNode 'node-win.zip'
    $sourceRootResolved = if ([string]::IsNullOrWhiteSpace($SourceRoot)) { $AppPath } else { (Resolve-UnitecAppPath -Path $SourceRoot) }
    $bundledZip = Join-Path $sourceRootResolved 'installer\assets\node-win.zip'

    if (Test-Path $bundledZip) {
        Copy-Item -Path $bundledZip -Destination $assetZip -Force
    } elseif (-not (Test-Path $assetZip)) {
        Write-Host ">> Baixando Node.js 20 LTS para tools\node (gateway WhatsApp)..." -ForegroundColor White
        try {
            Invoke-WebRequest -Uri $script:UnitecNodeDownloadUrl -OutFile $assetZip -UseBasicParsing
        } catch {
            Write-Host "Node.js nao instalado e download falhou. Instale em https://nodejs.org ou coloque node-win.zip em installer\assets\" -ForegroundColor Yellow
            return $null
        }
    }

    Write-Host '>> Extraindo Node.js em tools\node...' -ForegroundColor White
    Expand-Archive -Path $assetZip -DestinationPath $toolsNode -Force

    $extractedDir = Join-Path $toolsNode $script:UnitecNodeFolderName
    if ((Test-Path $extractedDir) -and -not (Test-Path (Join-Path $toolsNode 'node.exe'))) {
        Get-ChildItem $extractedDir -Force | ForEach-Object {
            Move-Item -Path $_.FullName -Destination $toolsNode -Force
        }
        Remove-Item $extractedDir -Recurse -Force -ErrorAction SilentlyContinue
    }

    Remove-Item $assetZip -Force -ErrorAction SilentlyContinue

    return Get-UnitecNodeExecutable -AppPath $AppPath
}

function Initialize-UnitecNodePath {
    param([string]$AppPath)

    $nodeExe = Get-UnitecNodeExecutable -AppPath $AppPath
    if (-not $nodeExe) {
        return
    }

    $nodeDir = Split-Path $nodeExe -Parent
    if ($env:Path -notlike "*$nodeDir*") {
        $env:Path = "$nodeDir;$env:Path"
    }
}

function Stop-UnitecWhatsAppGateway {
    param(
        [int]$Port = 8091,
        [switch]$Quiet
    )

    $connections = @(Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue)

    if ($connections.Count -eq 0) {
        if (-not $Quiet) {
            Write-Host "Gateway WhatsApp: nenhum processo na porta $Port." -ForegroundColor DarkGray
        }

        return $false
    }

    $pids = $connections | Select-Object -ExpandProperty OwningProcess -Unique

    $stoppedAny = $false

    foreach ($processId in $pids) {
        try {
            Stop-Process -Id $processId -Force -ErrorAction Stop
            $stoppedAny = $true

            if (-not $Quiet) {
                Write-Host "Gateway WhatsApp: processo $processId encerrado (porta $Port)." -ForegroundColor Yellow
            }
        } catch {
            $taskkill = Start-Process -FilePath 'taskkill.exe' -ArgumentList '/F', '/PID', $processId -Wait -PassThru -NoNewWindow

            if ($taskkill.ExitCode -eq 0) {
                $stoppedAny = $true

                if (-not $Quiet) {
                    Write-Host "Gateway WhatsApp: processo $processId encerrado via taskkill (porta $Port)." -ForegroundColor Yellow
                }
            } elseif (-not $Quiet) {
                Write-Host "Gateway WhatsApp: nao foi possivel encerrar o processo $processId (acesso negado)." -ForegroundColor Red
                Write-Host '  Feche o Node em Gerenciador de Tarefas ou rode este script em um PowerShell normal (fora do Cursor).' -ForegroundColor Yellow
            }
        }
    }

    Start-Sleep -Milliseconds 700

    $stillListening = @(Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue)

    if ($stillListening.Count -gt 0 -and -not $Quiet) {
        $remaining = $stillListening | Select-Object -ExpandProperty OwningProcess -Unique
        Write-Host "Gateway WhatsApp: a porta $Port ainda esta em uso (PID: $($remaining -join ', '))." -ForegroundColor Red
    }

    return $stoppedAny -and $stillListening.Count -eq 0
}

function Restart-UnitecWhatsAppGateway {
    param(
        [string]$AppPath,
        [switch]$Quiet
    )

    $AppPath = Resolve-UnitecAppPath -Path $AppPath

    if (-not $Quiet) {
        Write-Host 'Reiniciando gateway WhatsApp...' -ForegroundColor Cyan
    }

    $stopped = Stop-UnitecWhatsAppGateway -Quiet:$Quiet

    if (-not $stopped) {
        $stillUp = $false

        try {
            $health = Invoke-RestMethod -Uri 'http://127.0.0.1:8091/health' -TimeoutSec 2 -ErrorAction Stop
            $stillUp = [bool]$health.ok
        } catch {
            $stillUp = $false
        }

        if ($stillUp) {
            if (-not $Quiet) {
                Write-Host ''
                Write-Host 'NAO foi possivel reiniciar: o gateway antigo continua rodando.' -ForegroundColor Red
                Write-Host 'Faca assim:' -ForegroundColor Yellow
                Write-Host '  1. Abra o Gerenciador de Tarefas (Ctrl+Shift+Esc)' -ForegroundColor White
                Write-Host '  2. Aba Detalhes -> finalize node.exe que usa a porta 8091' -ForegroundColor White
                Write-Host '  3. Rode de novo: .\scripts\restart-whatsapp-gateway.ps1' -ForegroundColor White
                Write-Host ''
            }

            return $false
        }
    }

    $null = Start-UnitecWhatsAppGateway -AppPath $AppPath -Quiet:$Quiet

    for ($attempt = 0; $attempt -lt 10; $attempt++) {
        try {
            $health = Invoke-RestMethod -Uri 'http://127.0.0.1:8091/health' -TimeoutSec 2 -ErrorAction Stop

            if ($health.ok) {
                if (-not $Quiet) {
                    Write-Host 'Gateway WhatsApp reiniciado com sucesso (porta 8091).' -ForegroundColor Green
                }

                return $true
            }
        } catch {
            Start-Sleep -Milliseconds 400
        }
    }

    if (-not $Quiet) {
        Write-Host 'Gateway WhatsApp nao respondeu apos o reinicio.' -ForegroundColor Red
    }

    return $false
}

function Start-UnitecWhatsAppGateway {
    param(
        [string]$AppPath,
        [switch]$Quiet
    )

    $AppPath = Resolve-UnitecAppPath -Path $AppPath
    $gatewayPath = Join-Path $AppPath 'services\erp-whatsapp-gateway'
    $gatewayIndex = Join-Path $gatewayPath 'index.js'
    $baileysPkg = Join-Path $gatewayPath 'node_modules\@whiskeysockets\baileys\package.json'

    if (-not (Test-Path $gatewayIndex)) {
        if (-not $Quiet) {
            Write-Host 'Gateway WhatsApp: services\erp-whatsapp-gateway\index.js nao encontrado.' -ForegroundColor Yellow
        }

        return $false
    }

    $nodeExe = Ensure-UnitecNodeRuntime -AppPath $AppPath -SourceRoot $AppPath
    if (-not $nodeExe) {
        if (-not $Quiet) {
            Write-Host 'WhatsApp: Node.js nao encontrado. Instale Node 20+ ou deixe o ERP baixar em tools\node na proxima execucao com internet.' -ForegroundColor Yellow
        }

        return $false
    }

    Initialize-UnitecNodePath -AppPath $AppPath
    $npmExe = Get-UnitecNpmExecutable -AppPath $AppPath

    if (-not (Test-Path $baileysPkg)) {
        if (-not $npmExe) {
            if (-not $Quiet) {
                Write-Host 'WhatsApp: npm nao encontrado para instalar dependencias do gateway.' -ForegroundColor Yellow
            }

            return $false
        }

        if (-not $Quiet) {
            Write-Host 'Instalando dependencias do gateway WhatsApp (npm)...' -ForegroundColor White
        }

        Push-Location $gatewayPath
        try {
            & $npmExe install --no-fund --no-audit | Out-Null
        } finally {
            Pop-Location
        }
    }

    if (-not (Test-Path $baileysPkg)) {
        if (-not $Quiet) {
            Write-Host 'WhatsApp: dependencias do gateway nao instaladas.' -ForegroundColor Yellow
        }

        return $false
    }

    $gatewayConfig = Join-Path $AppPath 'storage\app\whatsapp\gateway-config.json'
    if (-not (Test-Path $gatewayConfig)) {
        Invoke-UnitecArtisan -AppPath $AppPath -Arguments @('erp:whatsapp-gateway', '--config-only') -AllowFailure | Out-Null
    }

    try {
        $health = Invoke-RestMethod -Uri 'http://127.0.0.1:8091/health' -TimeoutSec 2 -ErrorAction Stop
        if ($health.ok) {
            if (-not $Quiet) {
                Write-Host 'Gateway WhatsApp interno ja ativo (porta 8091).' -ForegroundColor DarkGray
            }

            return $true
        }
    } catch {
        # segue para iniciar
    }

    if (-not $Quiet) {
        Write-Host 'Gateway WhatsApp interno: porta 8091 (localhost)' -ForegroundColor DarkGray
    }

    $pidFile = Join-Path $AppPath 'storage\app\whatsapp\gateway.pid'
    $pidDir = Split-Path $pidFile -Parent
    if (-not (Test-Path $pidDir)) {
        New-Item -ItemType Directory -Path $pidDir -Force | Out-Null
    }

    $started = Start-Process -FilePath $nodeExe -ArgumentList 'index.js' -WorkingDirectory $gatewayPath -WindowStyle Hidden -PassThru
    if ($started?.Id) {
        Set-Content -Path $pidFile -Value $started.Id -Encoding ASCII
    }

    return $true
}

function Invoke-UnitecArtisan {
    param(
        [string]$AppPath,
        [Parameter(Mandatory = $true)]
        [string[]]$Arguments,
        [switch]$AllowFailure
    )

    $AppPath = Resolve-UnitecAppPath -Path $AppPath
    Initialize-UnitecRuntimePath -AppPath $AppPath
    $phpExe = Get-UnitecPhpExecutable -AppPath $AppPath

    Push-Location $AppPath
    try {
        $stderrFile = Join-Path $env:TEMP ("unitec-artisan-err-{0}.txt" -f [Guid]::NewGuid().ToString('N'))
        $stdout = & $phpExe artisan @Arguments 2> $stderrFile
        $exitCode = $LASTEXITCODE
        $stderr = ''
        if (Test-Path $stderrFile) {
            $stderrRaw = Get-Content $stderrFile -Raw -ErrorAction SilentlyContinue
            if ($null -ne $stderrRaw) {
                $stderr = $stderrRaw.Trim()
            }

            Remove-Item $stderrFile -Force -ErrorAction SilentlyContinue
        }

        $stdoutText = ''
        if ($null -ne $stdout) {
            $stdoutText = ($stdout | Out-String).Trim()
        }
        $detailParts = @()
        if (-not [string]::IsNullOrWhiteSpace($stdoutText)) { $detailParts += $stdoutText }
        if (-not [string]::IsNullOrWhiteSpace($stderr)) { $detailParts += $stderr }
        $detail = $detailParts -join [Environment]::NewLine

        if ($exitCode -ne 0 -and -not $AllowFailure) {
            if ([string]::IsNullOrWhiteSpace($detail)) {
                $detail = "codigo $exitCode"
            }

            throw ("artisan {0} falhou: {1}" -f ($Arguments -join ' '), $detail)
        }

        return @{
            ExitCode = $exitCode
            Output   = $detail
            Success  = ($exitCode -eq 0)
        }
    } finally {
        Pop-Location
    }
}

function Test-UnitecPathExists {
    param([string]$Path)

    if ([string]::IsNullOrWhiteSpace($Path)) {
        return $false
    }

    return Test-Path $Path
}

function Start-UnitecHiddenProcess {
    param(
        [Parameter(Mandatory = $true)]
        [string]$FilePath,
        [string[]]$ArgumentList,
        [string]$WorkingDirectory = '',
        [switch]$Wait,
        [switch]$PassThru
    )

    if (-not (Test-Path $FilePath)) {
        throw "Executavel nao encontrado: $FilePath"
    }

    $params = @{
        FilePath    = $FilePath
        WindowStyle = 'Hidden'
    }

    if ($PSBoundParameters.ContainsKey('ArgumentList') -and $null -ne $ArgumentList) {
        $cleanArgs = @($ArgumentList | Where-Object { $null -ne $_ -and $_ -ne '' })
        if ($cleanArgs.Count -gt 0) {
            $params.ArgumentList = $cleanArgs
        }
    }

    if (-not [string]::IsNullOrWhiteSpace($WorkingDirectory)) {
        $params.WorkingDirectory = $WorkingDirectory
    }

    if ($Wait) {
        $params.Wait = $true
    }

    if ($PassThru) {
        return Start-Process @params
    }

    Start-Process @params | Out-Null
}

function Write-Title($text) {
    Write-Host ''
    Write-Host '========================================' -ForegroundColor Cyan
    Write-Host "  $text" -ForegroundColor Cyan
    Write-Host '========================================' -ForegroundColor Cyan
}

function Write-Ok($text) { Write-Host "[OK] $text" -ForegroundColor Green }
function Write-Warn($text) { Write-Host "[!] $text" -ForegroundColor Yellow }
function Write-Err($text) { Write-Host "[ERRO] $text" -ForegroundColor Red }

function Get-UnitecAppUrls {
    param([string]$AppUrl = $script:UnitecDefaultAppUrl)

    $base = $AppUrl.TrimEnd('/')

    return @{
        Base       = $base
        Retaguarda = "$base/admin"
        Pdv        = "$base/admin/pdv"
        PreVenda   = "$base/admin/orcamentos"
    }
}

function Get-InstallLogPath {
    param([string]$AppPath = $script:UnitecDefaultAppPath)

    return Join-Path $AppPath 'instalacao.log'
}

function Start-InstallLog {
    param([string]$AppPath = $script:UnitecDefaultAppPath)

    Ensure-Directory $AppPath

    $logFile = Get-InstallLogPath -AppPath $AppPath
    $header = "=== Unitec ERP instalacao $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss') ==="
    Set-Content -Path $logFile -Value $header -Encoding UTF8

    return $logFile
}

function Write-InstallLog {
    param(
        [string]$Message,
        [string]$AppPath = $script:UnitecDefaultAppPath
    )

    $logFile = Get-InstallLogPath -AppPath $AppPath
    $line = "[$(Get-Date -Format 'HH:mm:ss')] $Message"

    try {
        Add-Content -Path $logFile -Value $line -Encoding UTF8
    } catch {
        # ignore log failures
    }
}

function Assert-HostsEntry {
    param(
        [string]$Hostname,
        [string]$Ip = '127.0.0.1'
    )

    if (-not (Test-HostsEntry -Hostname $Hostname -Ip $Ip)) {
        throw "Nao foi possivel registrar $Hostname em C:\Windows\System32\drivers\etc\hosts. Execute como administrador."
    }
}

function Test-UnitecHttpStatusReachable {
    param([int]$StatusCode)

    return ($StatusCode -ge 200 -and $StatusCode -lt 400)
}

function Get-UnitecServePidFilePath {
    param([string]$AppPath)

    return Join-Path $AppPath $script:UnitecServePidFileName
}

function Get-UnitecServeRuntimeMarkerPath {
    param([string]$AppPath)

    return Join-Path $AppPath '.unitec-serve.runtime'
}

function Get-UnitecFrankenPhpExe {
    param([string]$AppPath)

    $direct = Join-Path $AppPath 'tools\frankenphp\frankenphp.exe'
    if (Test-Path -LiteralPath $direct) {
        return $direct
    }

    return $null
}

function Ensure-UnitecFrankenPhpIni {
    param([string]$AppPath)

    $frankenDir = Join-Path $AppPath 'tools\frankenphp'
    if (-not (Test-Path -LiteralPath $frankenDir)) {
        return $false
    }

    $opcacheDir = Join-Path $frankenDir 'opcache'
    if (-not (Test-Path -LiteralPath $opcacheDir)) {
        New-Item -ItemType Directory -Path $opcacheDir -Force | Out-Null
    }

    $opcachePosix = ($opcacheDir -replace '\\', '/')
    $targetIni = Join-Path $frankenDir 'php.ini'

    # Nao copiar tools\php\php.ini (PHP 8.4) no FrankenPHP (PHP embutido 8.5):
    # no Windows o OPcache interno exige file_cache por causa do ASLR.
    $ini = @"
; Unitec ERP — php.ini do FrankenPHP (ASLR-safe). Gerado por Ensure-UnitecFrankenPhpIni.
extension_dir = "ext"

zend_extension=opcache
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=32
opcache.max_accelerated_files=20000
opcache.validate_timestamps=1
opcache.revalidate_freq=0
opcache.file_cache=$opcachePosix
opcache.file_cache_fallback=1
opcache.jit=0

extension=curl
extension=fileinfo
extension=gd
extension=intl
extension=mbstring
extension=mysqli
extension=openssl
extension=pdo_mysql
extension=pdo_sqlite
extension=sqlite3
extension=zip

memory_limit=512M
max_execution_time=300
upload_max_filesize=64M
post_max_size=64M
date.timezone=America/Sao_Paulo
"@

    Set-Content -LiteralPath $targetIni -Value $ini -Encoding ASCII
    return $true
}

function Get-UnitecFrankenPhpCaddyfile {
    param(
        [string]$AppPath,
        [int]$Port = 0
    )

    if ($Port -le 0) {
        $Port = [int]$script:UnitecServePort
    }

    $storageDir = Join-Path $AppPath 'storage\app'
    if (-not (Test-Path -LiteralPath $storageDir)) {
        New-Item -ItemType Directory -Path $storageDir -Force | Out-Null
    }

    $target = Join-Path $storageDir ("unitec-erp-frankenphp-{0}.caddyfile" -f $Port)
    $template = Join-Path $AppPath 'tools\frankenphp\Caddyfile.template'
    if (-not (Test-Path -LiteralPath $template)) {
        throw "FrankenPHP nao iniciou: Caddyfile.template ausente em tools\frankenphp."
    }

    Copy-Item -LiteralPath $template -Destination $target -Force
    return $target
}

function Get-UnitecFrankenPhpThreads {
    param([string]$AppPath)

    $threads = [string]$script:UnitecServeWorkers
    $envFile = Join-Path $AppPath '.env'
    if (Test-Path -LiteralPath $envFile) {
        $envRaw = Get-Content $envFile -Raw -ErrorAction SilentlyContinue
        if ($envRaw -and ($envRaw -match '(?m)^\s*FRANKENPHP_NUM_THREADS\s*=\s*(\d+)')) {
            $threads = $Matches[1]
        }
    }

    if ([int]$threads -lt 2) {
        $threads = '8'
    }

    return $threads
}

function Test-UnitecFrankenPhpRunning {
    param(
        [string]$AppPath,
        [int]$Port = 0
    )

    if ($Port -le 0) {
        $Port = [int]$script:UnitecServePort
    }

    $frankenDir = Join-Path $AppPath 'tools\frankenphp'
    if (-not (Test-Path -LiteralPath $frankenDir)) {
        return $false
    }

    $frankenFull = (Get-Item -LiteralPath $frankenDir).FullName.TrimEnd('\')
    $procs = @(Get-CimInstance Win32_Process -Filter "Name='frankenphp.exe'" -ErrorAction SilentlyContinue)
    foreach ($p in $procs) {
        $exe = [string]$p.ExecutablePath
        if ([string]::IsNullOrWhiteSpace($exe)) {
            $exe = [string]$p.CommandLine
        }
        if ($exe -and $exe.IndexOf($frankenFull, [System.StringComparison]::OrdinalIgnoreCase) -ge 0) {
            return $true
        }
        $cmd = [string]$p.CommandLine
        if ($cmd -and (
                $cmd -match 'unitec-erp-frankenphp' -or
                $cmd -match [regex]::Escape($AppPath)
            )) {
            return $true
        }
    }

    return $false
}

function Test-UnitecInvalidPhpBuiltinHttpServer {
    param(
        [string]$AppPath = '',
        [int]$Port = 0
    )

    if ($Port -le 0) {
        $Port = [int]$script:UnitecServePort
    }

    # Runtime invalido apenas se php -S / artisan serve estiver na porta do ERP.
    $phpProcs = @(Get-CimInstance Win32_Process -Filter "Name='php.exe'" -ErrorAction SilentlyContinue)
    foreach ($p in $phpProcs) {
        $cmd = [string]$p.CommandLine
        if ([string]::IsNullOrWhiteSpace($cmd)) {
            continue
        }
        $matchPort = ($cmd -match ("(?i)(-S\s+\S+:{0}\b|--port={0}\b|--port\s+{0}\b)" -f $Port))
        if (-not $matchPort) {
            continue
        }
        if ($cmd -match '(?i)\s-S\s' -or $cmd -match '(?i)artisan\s+serve') {
            return $true
        }
    }

    return $false
}

function Start-UnitecFrankenPhpServer {
    param(
        [string]$AppPath,
        [int]$Port = 0,
        [string]$BindHost = '',
        [switch]$Foreground,
        [int]$WaitSeconds = 25
    )

    $AppPath = Resolve-UnitecAppPath -Path $AppPath
    if ($Port -le 0) {
        $Port = [int]$script:UnitecServePort
    }
    if ([string]::IsNullOrWhiteSpace($BindHost)) {
        $BindHost = [string]$script:UnitecServeBindHost
    }

    $franken = Get-UnitecFrankenPhpExe -AppPath $AppPath
    if (-not $franken) {
        throw "FrankenPHP nao iniciou: binario ausente em tools\frankenphp\frankenphp.exe. O ERP exige FrankenPHP (sem fallback para php -S / artisan serve)."
    }

    if (-not (Test-Path (Join-Path $AppPath 'vendor\autoload.php'))) {
        throw 'Sistema incompleto: pasta vendor/ ausente.'
    }

    if (-not (Test-Path (Join-Path $AppPath 'public\index.php'))) {
        throw 'public\index.php ausente.'
    }

    Ensure-UnitecFrankenPhpIni -AppPath $AppPath | Out-Null
    $threads = Get-UnitecFrankenPhpThreads -AppPath $AppPath
    $caddy = Get-UnitecFrankenPhpCaddyfile -AppPath $AppPath -Port $Port
    $frankenIni = Join-Path (Split-Path $franken -Parent) 'php.ini'

    $httpHost = "127.0.0.1:$Port"

    Write-Host ("Runtime HTTP: FrankenPHP | Porta: {0}" -f $Port) -ForegroundColor Cyan
    Write-Host ("FRANKENPHP_NUM_THREADS={0}" -f $threads) -ForegroundColor DarkGray

    $prevBind = $env:UNITEC_BIND
    $prevPort = $env:UNITEC_PORT
    $prevPublic = $env:UNITEC_PUBLIC
    $prevHost = $env:UNITEC_HTTP_HOST
    $prevThreads = $env:FRANKENPHP_NUM_THREADS
    $prevPhpRc = $env:PHPRC

    $env:UNITEC_BIND = $BindHost
    $env:UNITEC_PORT = "$Port"
    $env:UNITEC_PUBLIC = 'public/'
    $env:UNITEC_HTTP_HOST = $httpHost
    $env:FRANKENPHP_NUM_THREADS = $threads
    if (Test-Path -LiteralPath $frankenIni) {
        $env:PHPRC = $frankenIni
    }

    try {
        if ($Foreground) {
            Write-Host ''
            Write-Host "FrankenPHP em http://${httpHost} - Ctrl+C para parar." -ForegroundColor Green
            Write-Host ''
            Set-Content -Path (Get-UnitecServeRuntimeMarkerPath -AppPath $AppPath) -Value 'frankenphp' -Encoding ASCII
            & $franken run --config $caddy
            return $true
        }

        # UseShellExecute=$false para o filho herdar UNITEC_PORT / FRANKENPHP_NUM_THREADS.
        $psi = New-Object System.Diagnostics.ProcessStartInfo
        $psi.FileName = $franken
        $psi.Arguments = "run --config `"$caddy`""
        $psi.WorkingDirectory = $AppPath
        $psi.UseShellExecute = $false
        $psi.CreateNoWindow = $true
        $psi.WindowStyle = [System.Diagnostics.ProcessWindowStyle]::Hidden
        $psi.Environment['UNITEC_BIND'] = $BindHost
        $psi.Environment['UNITEC_PORT'] = "$Port"
        $psi.Environment['UNITEC_PUBLIC'] = 'public/'
        $psi.Environment['UNITEC_HTTP_HOST'] = $httpHost
        $psi.Environment['FRANKENPHP_NUM_THREADS'] = $threads
        if (Test-Path -LiteralPath $frankenIni) {
            $psi.Environment['PHPRC'] = $frankenIni
        }

        $proc = [System.Diagnostics.Process]::Start($psi)
    } finally {
        if ($null -eq $prevBind) { Remove-Item Env:UNITEC_BIND -ErrorAction SilentlyContinue } else { $env:UNITEC_BIND = $prevBind }
        if ($null -eq $prevPort) { Remove-Item Env:UNITEC_PORT -ErrorAction SilentlyContinue } else { $env:UNITEC_PORT = $prevPort }
        if ($null -eq $prevPublic) { Remove-Item Env:UNITEC_PUBLIC -ErrorAction SilentlyContinue } else { $env:UNITEC_PUBLIC = $prevPublic }
        if ($null -eq $prevHost) { Remove-Item Env:UNITEC_HTTP_HOST -ErrorAction SilentlyContinue } else { $env:UNITEC_HTTP_HOST = $prevHost }
        if ($null -eq $prevThreads) { Remove-Item Env:FRANKENPHP_NUM_THREADS -ErrorAction SilentlyContinue } else { $env:FRANKENPHP_NUM_THREADS = $prevThreads }
        if ($null -eq $prevPhpRc) { Remove-Item Env:PHPRC -ErrorAction SilentlyContinue } else { $env:PHPRC = $prevPhpRc }
    }

    if ($Foreground) {
        return $true
    }

    if ($null -eq $proc) {
        throw 'FrankenPHP nao iniciou: falha ao criar processo.'
    }

    Set-Content -Path (Get-UnitecServePidFilePath -AppPath $AppPath) -Value $proc.Id -Encoding ASCII
    Set-Content -Path (Get-UnitecServeRuntimeMarkerPath -AppPath $AppPath) -Value 'frankenphp' -Encoding ASCII

    $deadline = (Get-Date).AddSeconds([Math]::Max(5, $WaitSeconds))
    while ((Get-Date) -lt $deadline) {
        if ($proc.HasExited) {
            throw ("FrankenPHP nao iniciou (encerrou com exit {0})." -f $proc.ExitCode)
        }

        if (Test-UnitecInvalidPhpBuiltinHttpServer -AppPath $AppPath -Port $Port) {
            Stop-UnitecApplicationServer -AppPath $AppPath
            throw 'RUNTIME INVALIDO: detectado php -S / artisan serve — abortando.'
        }

        $probeBase = "http://127.0.0.1:$Port"
        if (Wait-UnitecApplicationReady -AppUrl $probeBase -MaxAttempts 1 -DelaySeconds 0 -Quiet) {
            Write-Ok ("FrankenPHP ativo em {0}" -f $probeBase)
            return $true
        }

        if (Test-UnitecWebServerListening -Port $Port) {
            Write-Ok ("FrankenPHP escutando em {0}:{1}" -f $BindHost, $Port)
            return $true
        }

        Start-Sleep -Milliseconds 400
    }

    throw "FrankenPHP nao iniciou a tempo na porta $Port."
}

function Stop-UnitecProcessTree {
    param(
        [Parameter(Mandatory = $true)][int]$ProcessId
    )

    if ($ProcessId -le 0) {
        return $false
    }

    # NUNCA usar "& taskkill.exe ... 2>$null" com $ErrorActionPreference = 'Stop':
    # no Windows PT-BR o stderr vira erro terminante
    # ("ERRO: o processo '123' nao foi encontrado.") e aborta o instalador.
    try {
        if ($null -eq (Get-Process -Id $ProcessId -ErrorAction SilentlyContinue)) {
            return $false
        }
    } catch {
        return $false
    }

    try {
        Stop-Process -Id $ProcessId -Force -ErrorAction SilentlyContinue
    } catch {}

    Start-Sleep -Milliseconds 120

    if ($null -eq (Get-Process -Id $ProcessId -ErrorAction SilentlyContinue)) {
        return $true
    }

    try {
        $taskkill = Start-Process -FilePath 'taskkill.exe' `
            -ArgumentList @('/F', '/T', '/PID', "$ProcessId") `
            -Wait -PassThru -NoNewWindow -WindowStyle Hidden -ErrorAction SilentlyContinue

        return ($null -ne $taskkill -and [int]$taskkill.ExitCode -eq 0)
    } catch {
        return $false
    }
}

function Stop-UnitecApplicationServer {
    param([string]$AppPath)

    try {
        $AppPath = Resolve-UnitecAppPath -Path $AppPath
        $appPathNorm = $AppPath.TrimEnd('\')
        $killed = @{}

        $pidFile = Get-UnitecServePidFilePath -AppPath $AppPath
        if (Test-Path $pidFile) {
            $raw = (Get-Content $pidFile -Raw -ErrorAction SilentlyContinue)
            if ($raw -match '(\d+)') {
                $procId = [int]$Matches[1]
                Stop-UnitecProcessTree -ProcessId $procId | Out-Null
                $killed[$procId] = $true
            }
            Remove-Item $pidFile -Force -ErrorAction SilentlyContinue
        }

        # Sem .unitec-serve.pid o PHP antigo fica zumbi com OPcache em memoria
        # (update "conclui" mas a tela continua na versao velha). Mata pelo path/porta.
        $port = [int]$script:UnitecServePort
        try {
            $envUrl = Get-UnitecEnvValue -AppPath $AppPath -Key 'APP_URL'
            if ($envUrl -match ':(\d+)') {
                $port = [int]$Matches[1]
            }
        } catch {}

        # 1) Mata legado php -S / artisan serve nesta instalacao/porta.
        Get-CimInstance Win32_Process -Filter "Name='php.exe'" -ErrorAction SilentlyContinue | ForEach-Object {
            $cmd = [string]$_.CommandLine
            if ([string]::IsNullOrWhiteSpace($cmd)) {
                return
            }

            $matchPath = $cmd.IndexOf($appPathNorm, [System.StringComparison]::OrdinalIgnoreCase) -ge 0
            $matchPort = ($cmd -match ("(?i)(-S\s+\S+:{0}\b|--port={0}\b)" -f $port))

            if ($matchPath -or $matchPort) {
                $procId = [int]$_.ProcessId
                if (-not $killed.ContainsKey($procId)) {
                    Stop-UnitecProcessTree -ProcessId $procId | Out-Null
                    $killed[$procId] = $true
                }
            }
        }

        # 1b) Mata FrankenPHP desta instalacao.
        Get-CimInstance Win32_Process -Filter "Name='frankenphp.exe'" -ErrorAction SilentlyContinue | ForEach-Object {
            $cmd = [string]$_.CommandLine
            $exe = [string]$_.ExecutablePath
            $match = $false
            if ($cmd -and (
                    $cmd.IndexOf($appPathNorm, [System.StringComparison]::OrdinalIgnoreCase) -ge 0 -or
                    $cmd -match 'unitec-erp-frankenphp' -or
                    $cmd -match ("\:{0}\b" -f $port)
                )) {
                $match = $true
            }
            if (-not $match -and $exe -and $exe.IndexOf((Join-Path $appPathNorm 'tools\frankenphp'), [System.StringComparison]::OrdinalIgnoreCase) -ge 0) {
                $match = $true
            }
            if ($match) {
                $procId = [int]$_.ProcessId
                if (-not $killed.ContainsKey($procId)) {
                    Stop-UnitecProcessTree -ProcessId $procId | Out-Null
                    $killed[$procId] = $true
                }
            }
        }

        Remove-Item (Get-UnitecServeRuntimeMarkerPath -AppPath $AppPath) -Force -ErrorAction SilentlyContinue

        # 2) Mata o que ainda segura a porta (LISTEN / ESTABLISHED) — cobre PID sem CommandLine.
        try {
            $portPids = Get-NetTCPConnection -LocalPort $port -ErrorAction SilentlyContinue |
                Select-Object -ExpandProperty OwningProcess -Unique |
                Where-Object { $_ -and $_ -gt 0 }

            foreach ($procId in $portPids) {
                if (-not $killed.ContainsKey([int]$procId)) {
                    Stop-UnitecProcessTree -ProcessId ([int]$procId) | Out-Null
                    $killed[[int]$procId] = $true
                }
            }
        } catch {}

        # Aguarda a porta liberar de verdade (evita subir 2 listeners / zumbi).
        $deadline = (Get-Date).AddSeconds(8)
        while ((Get-Date) -lt $deadline) {
            $stillListen = $false
            try {
                $stillListen = [bool](Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue)
            } catch {}

            if (-not $stillListen) {
                break
            }

            try {
                Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue |
                    Select-Object -ExpandProperty OwningProcess -Unique |
                    ForEach-Object {
                        Stop-UnitecProcessTree -ProcessId ([int]$_) | Out-Null
                    }
            } catch {}

            Start-Sleep -Milliseconds 400
        }

        Start-Sleep -Milliseconds 300
    } catch {
        # Encerrar servidor antigo nunca deve abortar instalacao/abertura.
    }
}

function Test-UnitecApplicationServerRunning {
    param(
        [string]$AppPath = '',
        [string]$AppUrl = ''
    )

    # Nao confiar so em TCP: porta "zumbi" (LISTEN com PID morto) aceita connect
    # mas nao responde HTTP. Tambem rejeita php -S / artisan serve (runtime invalido).
    if (-not [string]::IsNullOrWhiteSpace($AppPath)) {
        $AppPath = Resolve-UnitecAppPath -Path $AppPath
        $port = [int]$script:UnitecServePort
        try {
            $envUrl = Get-UnitecEnvValue -AppPath $AppPath -Key 'APP_URL'
            if ($envUrl -match ':(\d+)') {
                $port = [int]$Matches[1]
            }
        } catch {}

        if (Test-UnitecInvalidPhpBuiltinHttpServer -AppPath $AppPath -Port $port) {
            return $false
        }
    }

    if ([string]::IsNullOrWhiteSpace($AppUrl)) {
        $AppUrl = Get-UnitecDefaultAppUrl
    }

    return (Wait-UnitecApplicationReady -AppUrl $AppUrl -MaxAttempts 1 -DelaySeconds 0 -Quiet)
}

function Ensure-UnitecStorageStructure {
    param([string]$AppPath)

    $AppPath = Resolve-UnitecAppPath -Path $AppPath
    $dirs = @(
        'storage\framework\sessions',
        'storage\framework\views',
        'storage\framework\cache',
        'storage\framework\cache\data',
        'storage\framework\testing',
        'storage\logs',
        'storage\app\private',
        'storage\app\private\updates',
        'storage\app\public',
        'bootstrap\cache'
    )

    foreach ($relative in $dirs) {
        Ensure-Directory (Join-Path $AppPath $relative)
    }

    foreach ($relative in @('storage\framework\sessions', 'storage\framework\views', 'storage\logs', 'bootstrap\cache')) {
        $ignore = Join-Path $AppPath (Join-Path $relative '.gitignore')
        if (-not (Test-Path $ignore)) {
            Set-Content -Path $ignore -Value "*`r`n!.gitignore`r`n" -Encoding ASCII
        }
    }
}

function Start-UnitecApplicationServer {
    param(
        [string]$AppPath,
        [switch]$Restart
    )

    # Se estiver como Administrador, reforça a liberacao da porta web para estacoes.
    Register-UnitecFirewallRule -Quiet

    if ($Restart) {
        Stop-UnitecApplicationServer -AppPath $AppPath
    }

    if (Test-UnitecInvalidPhpBuiltinHttpServer -AppPath $AppPath) {
        Write-Host 'RUNTIME INVALIDO: php -S / artisan serve detectado — encerrando antes de subir FrankenPHP.' -ForegroundColor Yellow
        Stop-UnitecApplicationServer -AppPath $AppPath
    }

    # Porta LISTEN com HTTP morto = zumbi. Nao "retorna ok" — forca kill + sobe de novo.
    if ((Test-UnitecApplicationServerRunning -AppPath $AppPath) -and (Test-UnitecFrankenPhpRunning -AppPath $AppPath)) {
        Write-Ok ('Unitec ERP ja esta ativo em {0} (FrankenPHP)' -f (Get-UnitecDefaultAppUrl))
        return $true
    }

    Stop-UnitecApplicationServer -AppPath $AppPath

    if (-not (Test-Path (Join-Path $AppPath 'vendor\autoload.php'))) {
        throw 'Sistema incompleto: pasta vendor/ ausente. Reinstale o Unitec ERP.'
    }

    Ensure-UnitecStorageStructure -AppPath $AppPath
    Initialize-UnitecRuntimePath -AppPath $AppPath
    Ensure-UnitecPhpIniForWindowsDev -AppPath $AppPath | Out-Null
    $null = Sync-UnitecEnvPerformanceSettings -AppPath $AppPath

    Push-Location $AppPath
    try {
        Write-Host 'Iniciando Unitec ERP (FrankenPHP obrigatorio)...' -ForegroundColor White

        $configCached = Test-Path (Join-Path $AppPath 'bootstrap\cache\config.php')
        if (-not $configCached) {
            Invoke-UnitecArtisan -AppPath $AppPath -Arguments @('config:cache') -AllowFailure | Out-Null
        }

        Start-UnitecFrankenPhpServer -AppPath $AppPath -Port ([int]$script:UnitecServePort) -BindHost ([string]$script:UnitecServeBindHost) -WaitSeconds 40 | Out-Null

        if (-not (Wait-UnitecApplicationReady -MaxAttempts 10 -DelaySeconds 1 -Quiet)) {
            throw 'FrankenPHP nao iniciou a tempo. Consulte instalacao.log / storage\logs'
        }

        # Primeiro clique do usuario nao deve "pagar" a compilacao do OPcache.
        Warm-UnitecApplicationCache -AppPath $AppPath

        Write-Ok ('Unitec ERP ativo em {0} (Runtime HTTP: FrankenPHP | Porta: {1})' -f (Get-UnitecDefaultAppUrl), $script:UnitecServePort)
        return $true
    } finally {
        Pop-Location
    }
}

function Warm-UnitecApplicationCache {
    param(
        [string]$AppPath = '',
        [string]$AppUrl = '',
        [int]$Hits = 4
    )

    if (-not [string]::IsNullOrWhiteSpace($AppPath)) {
        $AppPath = Resolve-UnitecAppPath -Path $AppPath
    }

    if ([string]::IsNullOrWhiteSpace($AppUrl)) {
        $AppUrl = Get-UnitecDefaultAppUrl
    }

    $base = $AppUrl.TrimEnd('/')
    $urls = @(
        "$base/api/health",
        "$base/admin/login"
    )

    Write-Host 'Aquecendo ERP (health check + telas do menu em segundo plano)...' -ForegroundColor DarkGray

    $jobs = @()
    for ($i = 0; $i -lt [Math]::Max(2, $Hits); $i++) {
        $url = $urls[$i % $urls.Count]
        $jobs += Start-Job -ScriptBlock {
            param($Target)
            try {
                Invoke-WebRequest -Uri $Target -UseBasicParsing -TimeoutSec 20 | Out-Null
            } catch {
                # login/up podem responder 302/404 — ainda aquecem o PHP
            }
        } -ArgumentList $url
    }

    try {
        $null = Wait-Job -Job $jobs -Timeout 20
    } finally {
        $jobs | Remove-Job -Force -ErrorAction SilentlyContinue
    }

    if (-not [string]::IsNullOrWhiteSpace($AppPath) -and (Test-Path (Join-Path $AppPath 'artisan'))) {
        try {
            Initialize-UnitecRuntimePath -AppPath $AppPath
            $phpExe = Get-UnitecPhpExecutable -AppPath $AppPath
            Start-UnitecHiddenProcess -FilePath $phpExe -ArgumentList @(
                'artisan', 'unitec:warm', '-q'
            ) -WorkingDirectory $AppPath | Out-Null
            Write-Host 'Aquecimento completo (unitec:warm) iniciado em segundo plano.' -ForegroundColor DarkGray
        } catch {
            Write-Warn 'Nao foi possivel iniciar unitec:warm em segundo plano (ignorado).'
        }
    }
}

function Wait-UnitecApplicationReady {
    param(
        [string]$AppUrl = '',
        [int]$MaxAttempts = 15,
        [int]$DelaySeconds = 2,
        [switch]$ShowProgress,
        [switch]$Quiet
    )

    if ([string]::IsNullOrWhiteSpace($AppUrl)) {
        $AppUrl = Get-UnitecDefaultAppUrl
    }

    $base = $AppUrl.TrimEnd('/')
    $probeUrls = @(
        "$base/api/health",
        "$base/admin/login",
        "$base/admin",
        $base
    )

    for ($attempt = 1; $attempt -le $MaxAttempts; $attempt++) {
        if ($ShowProgress -and -not $Quiet) {
            Write-Host ('Aguardando Unitec ERP ({0}/{1})...' -f $attempt, $MaxAttempts) -ForegroundColor Gray
        }

        foreach ($probe in $probeUrls) {
            try {
                $response = Invoke-WebRequest -Uri $probe -UseBasicParsing -TimeoutSec 5 -MaximumRedirection 5
                if ($probe -like '*/api/health') {
                    if ($response.StatusCode -ge 200 -and $response.StatusCode -lt 300) {
                        $body = [string]$response.Content
                        if ($body -match '"status"\s*:\s*"ok"') {
                            return $true
                        }
                    }

                    continue
                }

                if (Test-UnitecHttpStatusReachable -StatusCode $response.StatusCode) {
                    return $true
                }
            } catch {
                $webResponse = $_.Exception.Response
                if ($null -ne $webResponse -and $probe -notlike '*/api/health') {
                    $statusCode = [int]$webResponse.StatusCode
                    if (Test-UnitecHttpStatusReachable -StatusCode $statusCode) {
                        return $true
                    }
                }
            }
        }

        Start-Sleep -Seconds $DelaySeconds
    }

    return $false
}

function Start-UnitecStack {
    param(
        [string]$AppPath,
        [string]$LaragonPath = 'C:\laragon',
        [int]$WaitSeconds = 20,
        [switch]$SkipDatabase
    )

    $AppPath = Resolve-UnitecAppPath -Path $AppPath
    Ensure-UnitecEnvFile -AppPath $AppPath | Out-Null
    if (Sync-UnitecEnvPerformanceSettings -AppPath $AppPath) {
        Invoke-UnitecArtisan -AppPath $AppPath -Arguments @('config:cache') -AllowFailure | Out-Null
    }

    # Sempre tenta deixar PHP/pdo_mysql ok ao abrir (igual instalacao: php.ini + VC++).
    $phpReady = Ensure-UnitecPhpExtensionsReady -AppPath $AppPath -AllowVcFix
    if (-not $phpReady.Ok) {
        throw $phpReady.Message
    }

    if (Test-UnitecApplicationServerRunning -AppPath $AppPath) {
        $null = Start-UnitecApplicationServer -AppPath $AppPath
        return
    }

    $db = Get-UnitecDatabaseSettingsFromEnv -AppPath $AppPath
    $remoteDb = Test-UnitecRemoteDatabaseHost -HostName $db.DbHost
    $useLegacyLaragon = (Test-Path (Join-Path $LaragonPath 'laragon.exe')) -and
        -not (Test-UnitecEmbeddedRuntimeInstalled -AppPath $AppPath)

    if ($remoteDb) {
        Ensure-UnitecRuntimeInstalled -AppPath $AppPath -SourceRoot $AppPath -SkipMysql
        Initialize-UnitecRuntimePath -AppPath $AppPath
    } elseif ($useLegacyLaragon) {
        $null = Ensure-LaragonPhp84 -LaragonPath $LaragonPath -SourceRoot $AppPath
        Initialize-UnitecRuntimePath -LaragonPath $LaragonPath
        $null = Ensure-LaragonMysqlRunning -AppPath $AppPath -LaragonPath $LaragonPath -MaxWaitSeconds $WaitSeconds -ThrowOnFailure
    } else {
        Ensure-UnitecRuntimeInstalled -AppPath $AppPath -SourceRoot $AppPath
        Initialize-UnitecRuntimePath -AppPath $AppPath
        Update-UnitecMysqlIniPerformance -AppPath $AppPath | Out-Null
        $null = Ensure-LaragonMysqlRunning -AppPath $AppPath -MaxWaitSeconds $WaitSeconds -ThrowOnFailure
    }

    if (-not $SkipDatabase -and (Test-Path (Join-Path $AppPath '.env'))) {
        if (-not $remoteDb) {
            Ensure-UnitecDatabaseFromEnv -AppPath $AppPath -LaragonPath $LaragonPath
        } elseif (-not (Test-UnitecDatabaseConnectionFromEnv -AppPath $AppPath -LaragonPath $LaragonPath)) {
            $detail = Get-UnitecDatabaseConnectionFailureDetails -AppPath $AppPath -LaragonPath $LaragonPath
            $message = ('Nao foi possivel conectar ao banco remoto em {0}:{1}.' -f $db.DbHost, $db.DbPort)
            if (-not [string]::IsNullOrWhiteSpace($detail)) {
                $message += " $detail"
            } else {
                $message += ' Verifique IP, usuario, senha (DB_* no .env) e se o MariaDB do servidor esta ativo.'
            }

            throw $message
        }

        Ensure-UnitecApplicationSchema -AppPath $AppPath
    }

    Start-UnitecApplicationServer -AppPath $AppPath
}

function Sync-UnitecEnvAppUrl {
    param(
        [string]$AppPath,
        [string]$AppUrl = ''
    )

    if ([string]::IsNullOrWhiteSpace($AppUrl)) {
        $AppUrl = Get-UnitecDefaultAppUrl
    }

    $envFile = Join-Path $AppPath '.env'
    if (-not (Test-Path $envFile)) {
        return $false
    }

    $lines = @(Get-Content $envFile -Encoding UTF8)
    $updated = $false
    $found = $false

    for ($i = 0; $i -lt $lines.Count; $i++) {
        if ($lines[$i] -match '^\s*APP_URL\s*=') {
            $lines[$i] = "APP_URL=$AppUrl"
            $found = $true
            $updated = $true
            break
        }
    }

    if (-not $found) {
        $lines += "APP_URL=$AppUrl"
        $updated = $true
    }

    if ($updated) {
        Set-UnitecUtf8NoBomFile -Path $envFile -Content ($lines -join [Environment]::NewLine)
    }

    return $updated
}

function Wait-UnitecSiteReachable {
    param(
        [string]$Url,
        [int]$MaxAttempts = 15,
        [int]$DelaySeconds = 2,
        [int]$TimeoutSec = 5,
        [switch]$ShowProgress
    )

    return (Wait-UnitecApplicationReady -AppUrl $Url -MaxAttempts $MaxAttempts -DelaySeconds $DelaySeconds -ShowProgress:$ShowProgress)
}

function Get-UnitecMigrationSignature {
    param([string]$AppPath)

    $AppPath = Resolve-UnitecAppPath -Path $AppPath
    $dir = Join-Path $AppPath 'database\migrations'

    if (-not (Test-Path $dir)) {
        return ''
    }

    $files = Get-ChildItem $dir -Filter '*.php' -File | Sort-Object Name
    if ($files.Count -eq 0) {
        return ''
    }

    $payload = ($files | ForEach-Object { '{0}:{1}' -f $_.Name, $_.Length }) -join ';'
    $sha = [System.Security.Cryptography.SHA256]::Create()
    $hash = $sha.ComputeHash([System.Text.Encoding]::UTF8.GetBytes($payload))

    return [Convert]::ToBase64String($hash)
}

function Get-UnitecMigrationSignatureFilePath {
    param([string]$AppPath)

    return Join-Path (Resolve-UnitecAppPath -Path $AppPath) 'storage\framework\unitec-migrations.sig'
}

function Test-UnitecMigrationSignatureCurrent {
    param([string]$AppPath)

    $sigFile = Get-UnitecMigrationSignatureFilePath -AppPath $AppPath
    if (-not (Test-Path $sigFile)) {
        return $false
    }

    $stored = Get-UnitecTrimmedFileContent -Path $sigFile
    $current = Get-UnitecMigrationSignature -AppPath $AppPath

    return ($stored -ne '') -and ($stored -eq $current)
}

function Save-UnitecMigrationSignature {
    param([string]$AppPath)

    $AppPath = Resolve-UnitecAppPath -Path $AppPath
    Ensure-Directory (Join-Path $AppPath 'storage\framework')
    $sig = Get-UnitecMigrationSignature -AppPath $AppPath
    Set-Content -Path (Get-UnitecMigrationSignatureFilePath -AppPath $AppPath) -Value $sig -Encoding ASCII -NoNewline
}

function Ensure-UnitecApplicationSchema {
    param(
        [string]$AppPath,
        [switch]$Force
    )

    if (-not (Test-Path (Join-Path $AppPath '.env'))) {
        return
    }

    if (-not (Test-Path (Join-Path $AppPath 'vendor\autoload.php'))) {
        return
    }

    # Sempre aplica migrate --force (idempotente). A assinatura de arquivos sozinha
    # nao detecta restore de dump antigo com o mesmo codigo — schema fica atras.
    Push-Location $AppPath
    try {
        Write-Host 'Criando/atualizando tabelas (migrate)...' -ForegroundColor White
        Invoke-UnitecDatabaseMigrate -AppPath $AppPath
        Save-UnitecMigrationSignature -AppPath $AppPath
        Write-Ok 'Tabelas do sistema prontas.'
    } finally {
        Pop-Location
    }
}

function Initialize-UnitecRuntime {
    param(
        [string]$AppPath,
        [string]$AppUrl = $script:UnitecDefaultAppUrl,
        [int]$WaitSeconds = 20
    )

    Sync-UnitecEnvAppUrl -AppPath $AppPath -AppUrl $AppUrl | Out-Null
    Start-UnitecStack -AppPath $AppPath -WaitSeconds $WaitSeconds
}

function Show-UnitecLeigoMessage {
    param(
        [string]$Title = 'Unitec ERP',
        [string]$Message,
        [ValidateSet('Information', 'Warning', 'Error')]
        [string]$Icon = 'Information'
    )

    try {
        Add-Type -AssemblyName System.Windows.Forms -ErrorAction Stop
        $iconEnum = [System.Windows.Forms.MessageBoxIcon]::$Icon
        [System.Windows.Forms.MessageBox]::Show(
            $Message,
            $Title,
            [System.Windows.Forms.MessageBoxButtons]::OK,
            $iconEnum
        ) | Out-Null
    } catch {
        Write-Host $Message
    }
}

function Start-UnitecLeigoProgress {
    param(
        [string]$FormTitle = 'Instalando Unitec ERP',
        [string]$Heading = 'Aguarde, estamos instalando o sistema...',
        [string]$InitialStatus = 'Preparando instalacao...',
        [string]$Hint = 'Pode demorar ate 20 minutos. Nao feche esta janela.'
    )

    Add-Type -AssemblyName System.Windows.Forms
    Add-Type -AssemblyName System.Drawing

    $form = New-Object System.Windows.Forms.Form
    $form.Text = $FormTitle
    $form.Size = New-Object System.Drawing.Size(520, 200)
    $form.StartPosition = 'CenterScreen'
    $form.FormBorderStyle = 'FixedDialog'
    $form.MaximizeBox = $false
    $form.MinimizeBox = $false
    $form.TopMost = $true
    $form.ControlBox = $false

    $titleLabel = New-Object System.Windows.Forms.Label
    $titleLabel.Location = New-Object System.Drawing.Point(20, 20)
    $titleLabel.Size = New-Object System.Drawing.Size(460, 30)
    $titleLabel.Font = New-Object System.Drawing.Font('Segoe UI', 12, [System.Drawing.FontStyle]::Bold)
    $titleLabel.Text = $Heading

    $statusLabel = New-Object System.Windows.Forms.Label
    $statusLabel.Location = New-Object System.Drawing.Point(20, 60)
    $statusLabel.Size = New-Object System.Drawing.Size(460, 40)
    $statusLabel.Font = New-Object System.Drawing.Font('Segoe UI', 10)
    $statusLabel.Text = $InitialStatus

    $progressBar = New-Object System.Windows.Forms.ProgressBar
    $progressBar.Location = New-Object System.Drawing.Point(20, 110)
    $progressBar.Size = New-Object System.Drawing.Size(460, 24)
    $progressBar.Style = 'Continuous'
    $progressBar.Minimum = 0
    $progressBar.Maximum = 100
    $progressBar.Value = 5

    $hintLabel = New-Object System.Windows.Forms.Label
    $hintLabel.Location = New-Object System.Drawing.Point(20, 145)
    $hintLabel.Size = New-Object System.Drawing.Size(460, 20)
    $hintLabel.Font = New-Object System.Drawing.Font('Segoe UI', 9)
    $hintLabel.ForeColor = [System.Drawing.Color]::DimGray
    $hintLabel.Text = $Hint

    $form.Controls.AddRange(@($titleLabel, $statusLabel, $progressBar, $hintLabel))
    $form.Show() | Out-Null
    [System.Windows.Forms.Application]::DoEvents()

    return @{
        Form   = $form
        Status = $statusLabel
        Bar    = $progressBar
    }
}

function Update-UnitecLeigoProgress {
    param(
        $Context,
        [string]$Message,
        [int]$Percent = 0
    )

    if ($null -eq $Context) {
        return
    }

    $Context.Status.Text = $Message
    $Context.Bar.Value = [Math]::Min(100, [Math]::Max(0, $Percent))
    [System.Windows.Forms.Application]::DoEvents()
}

function Stop-UnitecLeigoProgress {
    param($Context)

    if ($null -eq $Context) {
        return
    }

    try {
        $Context.Form.Close()
        $Context.Form.Dispose()
    } catch {
        # ignore
    }
}

function Remove-LegacyUnitecDesktopShortcuts {
    $desktop = [Environment]::GetFolderPath('Desktop')
    $commonDesktop = [Environment]::GetFolderPath('CommonDesktopDirectory')

    foreach ($folder in @($desktop, $commonDesktop)) {
        if ([string]::IsNullOrWhiteSpace($folder) -or -not (Test-Path $folder)) {
            continue
        }

        foreach ($name in @(
            'INFORSYSTEM Retaguarda.lnk',
            'INFORSYSTEM PDV.lnk',
            'INFORSYSTEM Pre-venda.lnk',
            'UNISISTEMA Retaguarda.lnk',
            'UNISISTEMA PDV.lnk',
            'UNISISTEMA Pre-venda.lnk',
            'Unitec ERP.lnk',
            'UNI SISTEMAS 3.0.lnk'
        )) {
            $path = Join-Path $folder $name
            if (Test-Path $path) {
                Remove-Item $path -Force -ErrorAction SilentlyContinue
            }
        }
    }
}

function Get-UnitecOpenAppShortcutTarget {
    param([string]$AppPath)

    $AppPath = Resolve-UnitecAppPath -Path $AppPath

    # Caminho oficial: somente Unitec ERP.exe (sem PowerShell / open-unitec-app).
    $launcher = Join-Path $AppPath 'bin\Unitec ERP.exe'
    if (-not (Test-Path $launcher)) {
        return $null
    }

    return @{
        TargetPath       = $launcher
        Arguments        = ("--app `"{0}`"" -f $AppPath)
        WorkingDirectory = $AppPath
    }
}

function New-UnitecLeigoWelcomeCard {
    param([string]$AppPath = $script:UnitecDefaultAppPath)

    $appUrl = Get-UnitecDefaultAppUrl
    $lanIp = Get-UnitecLanIPv4Address
    $lanUrl = if (-not [string]::IsNullOrWhiteSpace($lanIp)) {
        "http://${lanIp}:$($script:UnitecServePort)/admin/login"
    } else {
        "http://IP-DO-SERVIDOR:$($script:UnitecServePort)/admin/login"
    }
    $desktop = [Environment]::GetFolderPath('Desktop')
    $cardPath = Join-Path $desktop 'COMO USAR - Unitec ERP.txt'

    $content = @"
========================================
  Unitec ERP - COMO USAR
========================================

1) Para abrir o sistema
   Duplo clique no atalho "Unitec ERP" na Area de Trabalho.
   Abre em janela de aplicativo (Chrome/Edge --app).

2) Login
   Usuario: USUARIO
   Senha:  01
   (Troque a senha depois do primeiro acesso.)

3) Endereco local
   $appUrl/admin/login

4) Outros computadores da loja (mesma rede)
   Use: $lanUrl
   Exemplo: http://192.168.0.52:$($script:UnitecServePort)/admin/login

5) Se nao abrir
 - Reinicie o computador servidor e tente de novo.
 - Pasta do sistema: $AppPath
 - Suporte: https://unitecnologiasc.com.br/

========================================
"@

    Set-Content -Path $cardPath -Value $content -Encoding UTF8
    Write-Ok 'Cartao "COMO USAR" criado na Area de Trabalho.'
}

function Register-UnitecFirewallRule {
    param(
        [int]$Port = 0,
        [switch]$Quiet
    )

    if ($Port -le 0) {
        $Port = [int]$script:UnitecServePort
    }

    Ensure-UnitecFirewallTcpRule `
        -RuleName ("Unitec ERP (porta {0})" -f $Port) `
        -Port $Port `
        -Description 'Permite acesso do ERP pelas estacoes e pelo app Forca de Vendas na rede local.' `
        -Quiet:$Quiet | Out-Null
}

function Register-UnitecNetworkFirewallRules {
    param(
        [int]$WebPort = 0,
        [switch]$IncludeMariaDb
    )

    if ($WebPort -le 0) {
        $WebPort = [int]$script:UnitecServePort
    }

    Register-UnitecFirewallRule -Port $WebPort

    if ($IncludeMariaDb) {
        Register-UnitecMariaDbFirewallRule
    }

    $lanIp = Get-UnitecLanIPv4Address
    if (-not [string]::IsNullOrWhiteSpace($lanIp)) {
        Write-Ok ("Estacoes na rede: http://{0}:{1}/admin/login" -f $lanIp, $WebPort)
    }
}

function Unregister-UnitecLogonStartup {
    param([string]$AppPath = $script:UnitecDefaultAppPath)

    $taskName = 'UnitecERP_IniciarComWindows'
    try {
        $existing = Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue
        if ($null -ne $existing) {
            Unregister-ScheduledTask -TaskName $taskName -Confirm:$false -ErrorAction Stop
            Write-Ok 'Tarefa legada de logon (UnitecERP_IniciarComWindows) removida — use o servico UnitecErpServer.'
        }
    } catch {
        Write-Warn 'Nao foi possivel remover tarefa de logon legada (ignorado).'
    }
}

function Register-UnitecLogonStartup {
    param([string]$AppPath = $script:UnitecDefaultAppPath)

    # Legado desativado: auto-start oficial e o servico Windows UnitecErpServer (Automatic).
    Unregister-UnitecLogonStartup -AppPath $AppPath
}

function Resolve-UnitecAppIconPath {
    param(
        [string]$AppPath = '',
        [string]$SourceRoot = ''
    )

    $candidates = @()
    if (-not [string]::IsNullOrWhiteSpace($AppPath)) {
        $candidates += (Join-Path $AppPath 'installer\assets\unitec-erp.ico')
    }
    if (-not [string]::IsNullOrWhiteSpace($SourceRoot)) {
        $candidates += (Join-Path $SourceRoot 'installer\assets\unitec-erp.ico')
    }
    $candidates += (Join-Path $PSScriptRoot '..\installer\assets\unitec-erp.ico')

    foreach ($path in ($candidates | Where-Object { -not [string]::IsNullOrWhiteSpace($_) } | Select-Object -Unique)) {
        $full = [System.IO.Path]::GetFullPath($path)
        if (Test-UnitecPathExists $full) {
            return $full
        }
    }

    return $null
}

function Set-UnitecShortcutIcon {
    param(
        $Shortcut,
        [string]$AppPath = $script:UnitecDefaultAppPath
    )

    $icon = Resolve-UnitecAppIconPath -AppPath $AppPath
    if ($icon) {
        $Shortcut.IconLocation = ('{0},0' -f $icon)
    }
}

function New-UnitecDesktopShortcuts {
    param([string]$AppPath = $script:UnitecDefaultAppPath)

    $AppPath = Resolve-UnitecAppPath -Path $AppPath
    Remove-LegacyUnitecDesktopShortcuts

    $target = Get-UnitecOpenAppShortcutTarget -AppPath $AppPath
    if ($null -eq $target) {
        Write-Warn 'bin\Unitec ERP.exe nao encontrado - atalho nao criado. Rode scripts\build-erp-desktop.ps1.'
        return
    }

    $desktop = [Environment]::GetFolderPath('Desktop')
    $shell = New-Object -ComObject WScript.Shell
    $lnkPath = Join-Path $desktop 'Unitec ERP.lnk'
    $shortcut = $shell.CreateShortcut($lnkPath)
    $shortcut.TargetPath = $target.TargetPath
    $shortcut.Arguments = $target.Arguments
    $shortcut.WorkingDirectory = $target.WorkingDirectory
    $shortcut.WindowStyle = 7
    $shortcut.Description = 'Abrir Unitec ERP (aplicativo)'
    Set-UnitecShortcutIcon -Shortcut $shortcut -AppPath $AppPath
    $shortcut.Save()

    Write-Ok 'Atalho "Unitec ERP" criado na Area de Trabalho (janela Chrome/Edge --app).'
    New-UnitecLeigoWelcomeCard -AppPath $AppPath
}

function Resolve-HeidiSqlSetupPath {
    param([string]$SourceRoot = '')

    $candidates = @()
    if (-not [string]::IsNullOrWhiteSpace($SourceRoot)) {
        $assetsDir = Join-Path $SourceRoot 'installer\assets'
        $candidates += (Join-Path $assetsDir $script:UnitecHeidiSqlSetupAssetName)
        if (Test-Path $assetsDir) {
            $candidates += @(Get-ChildItem $assetsDir -Filter 'HeidiSQL_*_Setup.exe' -File -ErrorAction SilentlyContinue |
                Sort-Object Name -Descending |
                Select-Object -ExpandProperty FullName)
        }
    }

    $candidates += (Join-Path $PSScriptRoot ('..\installer\assets\' + $script:UnitecHeidiSqlSetupAssetName))
    $scriptAssetsDir = Join-Path $PSScriptRoot '..\installer\assets'
    if (Test-Path $scriptAssetsDir) {
        $candidates += @(Get-ChildItem $scriptAssetsDir -Filter 'HeidiSQL_*_Setup.exe' -File -ErrorAction SilentlyContinue |
            Sort-Object Name -Descending |
            Select-Object -ExpandProperty FullName)
    }

    foreach ($path in ($candidates | Where-Object { -not [string]::IsNullOrWhiteSpace($_) } | Select-Object -Unique)) {
        $full = [System.IO.Path]::GetFullPath($path)
        if (Test-UnitecPathExists $full) {
            return $full
        }
    }

    return $null
}

function Find-HeidiSqlExecutable {
    param([string[]]$SearchRoots)

    foreach ($root in ($SearchRoots | Where-Object { -not [string]::IsNullOrWhiteSpace($_) } | Select-Object -Unique)) {
        if (-not (Test-Path $root)) {
            continue
        }

        $found = Get-ChildItem -Path $root -Filter 'heidisql.exe' -Recurse -ErrorAction SilentlyContinue |
            Sort-Object FullName |
            Select-Object -First 1

        if ($found) {
            return $found.FullName
        }
    }

    return $null
}

function Install-UnitecHeidiSql {
    param([string]$AppPath)

    $setupPath = Resolve-HeidiSqlSetupPath -SourceRoot $AppPath
    if (-not $setupPath) {
        return $null
    }

    $targetRoot = Join-Path $AppPath 'tools\heidisql'
    $existing = Find-HeidiSqlExecutable -SearchRoots @($targetRoot)
    if ($existing) {
        return $existing
    }

    Ensure-Directory $targetRoot

    Write-Host 'Instalando HeidiSQL 12.18 (suporte)...' -ForegroundColor White

    $installArgs = @(
        '/VERYSILENT',
        '/SUPPRESSMSGBOXES',
        '/NORESTART',
        ('/DIR={0}' -f $targetRoot)
    )

    $proc = Start-Process -FilePath $setupPath -ArgumentList $installArgs -Wait -PassThru
    if ($proc.ExitCode -ne 0) {
        throw ('Instalador HeidiSQL falhou (codigo {0}).' -f $proc.ExitCode)
    }

    Start-Sleep -Seconds 2

    $installed = Find-HeidiSqlExecutable -SearchRoots @($targetRoot)
    if (-not $installed) {
        throw 'HeidiSQL instalado, mas heidisql.exe nao encontrado em tools\heidisql.'
    }

    Write-Ok 'HeidiSQL instalado em tools\heidisql.'
    return $installed
}

function Resolve-HeidiSqlExecutable {
    param(
        [string]$AppPath,
        [switch]$AllowInstall
    )

    $searchRoots = @(
        (Join-Path $AppPath 'tools\heidisql')
    )

    $exe = Find-HeidiSqlExecutable -SearchRoots $searchRoots
    if ($exe) {
        return $exe
    }

    if ($AllowInstall) {
        return Install-UnitecHeidiSql -AppPath $AppPath
    }

    return $null
}

function New-UnitecHeidiSqlSupportShortcut {
    param([string]$AppPath = $script:UnitecDefaultAppPath)

    $supportDir = Join-Path $AppPath 'suporte'
    $launcherBat = Join-Path $supportDir 'HeidiSQL - Unitec ERP.bat'

    if (-not (Test-Path $launcherBat)) {
        Write-Warn 'HeidiSQL - Unitec ERP.bat nao encontrado em suporte\.'
        return $false
    }

    Ensure-Directory $supportDir

    $shell = New-Object -ComObject WScript.Shell
    $lnkPath = Join-Path $supportDir 'HeidiSQL - Unitec ERP.lnk'
    $shortcut = $shell.CreateShortcut($lnkPath)
    $shortcut.TargetPath = $launcherBat
    $shortcut.WorkingDirectory = $AppPath
    $shortcut.Description = 'Gerenciar banco MySQL do Unitec ERP (suporte)'
    $shortcut.Save()

    return $true
}

function Install-UnitecHeidiSqlSupport {
    param(
        [string]$AppPath = $script:UnitecDefaultAppPath
    )

    $AppPath = Resolve-UnitecAppPath -Path $AppPath

    if (-not (Test-Path (Join-Path $AppPath '.env'))) {
        Write-Warn 'HeidiSQL ignorado - .env ainda nao existe.'
        return $false
    }

    try {
        $exe = Resolve-HeidiSqlExecutable -AppPath $AppPath -AllowInstall
    } catch {
        Write-Warn $_.Exception.Message
        Write-Warn 'HeidiSQL nao instalado - o ERP funciona normalmente; suporte pode instalar depois.'
        return $false
    }

    if (-not $exe) {
        Write-Warn 'HeidiSQL nao disponivel. Coloque HeidiSQL_*_Setup.exe em installer\assets\ e reinstale.'
        return $false
    }

    if (New-UnitecHeidiSqlSupportShortcut -AppPath $AppPath) {
        Write-Ok 'Atalho HeidiSQL criado em suporte\ (ferramenta de suporte).'
    }

    return $true
}

function Remove-PublicStorageLink {
    param([string]$Root)

    $storageLink = Join-Path $Root 'public\storage'

    if (-not (Test-Path $storageLink)) {
        return
    }

    try {
        $item = Get-Item $storageLink -Force

        if ($item.Attributes -band [IO.FileAttributes]::ReparsePoint) {
            $item.Delete()
        } else {
            Remove-Item $storageLink -Force -Recurse
        }
    } catch {
        Write-Warn ('Nao foi possivel remover public\storage em {0} (ignorado).' -f $Root)
    }
}

function Copy-UnitecProjectTree {
    param(
        [string]$SourceRoot,
        [string]$TargetRoot,
        [switch]$Quiet,
        [switch]$UpdateMode,
        [switch]$ExcludeTools,
        # Update rotineiro: nao embute dist self-contained (~170 MB).
        # Use -IncludeDeviceService quando o EXE da balanca/impressao mudar.
        [switch]$IncludeDeviceService
    )

    $sourceFull = [System.IO.Path]::GetFullPath($SourceRoot).TrimEnd('\')
    $targetFull = [System.IO.Path]::GetFullPath($TargetRoot).TrimEnd('\')

    if ($sourceFull -eq $targetFull) {
        return
    }

    Ensure-Directory $targetFull

    $excludeDirs = @(
        'node_modules',
        '.git',
        'dist',
        '.cursor',
        '.idea',
        '.vscode',
        '.codex',
        '.phpunit.cache',
        'vendor',
        'public\storage'
    )

    if ($UpdateMode -or $ExcludeTools) {
        $excludeDirs += 'tools'
    }

    if ($UpdateMode) {
        # Pacote de atualizacao: cliente ja tem runtime (tools/) e instalador.
        # bin/ contem o servico Windows em execucao e nao pode ser substituido
        # pelo apply do login. Atualizacao do bin e feita separadamente.
        $excludeDirs += @(
            'bin',
            'storage',
            'installer',
            'tests',
            'docs',
            'suporte',
            'staging',
            'atualizacao',
            'importar',
            'apps',
            # Device Service: src/tests sao sujeira de build/dev.
            'services\unitec-device-service\src',
            'services\unitec-device-service\tests'
        )

        if (-not $IncludeDeviceService) {
            # Pacote ERP slim: cliente ja tem o Dist; nao reenviar runtime .NET a cada update.
            $excludeDirs += 'services\unitec-device-service\dist'
        }
    }

    $projectArgs = @(
        $sourceFull,
        $targetFull,
        '/MIR', '/MT:8', '/R:2', '/W:2',
        '/NFL', '/NDL', '/NJH', '/NJS', '/NC', '/NS'
    )

    foreach ($dir in $excludeDirs) {
        $full = Join-Path $sourceFull $dir
        if (Test-Path $full) {
            $projectArgs += '/XD'
            $projectArgs += $full
        }
    }

    # Exclui QUALQUER pasta node_modules (inclui services/.../node_modules).
    $projectArgs += '/XD'
    $projectArgs += 'node_modules'

    $projectArgs += '/XF'
    $projectArgs += '.env'
    $projectArgs += '.env.backup'
    $projectArgs += '.env.production'

    if ($UpdateMode) {
        $projectArgs += '/XF'
        $projectArgs += 'composer.phar'
        $projectArgs += 'composer-setup.php'
        $projectArgs += 'vc_redist.x64.exe'
        $projectArgs += 'vc_redist.x64.exe.bak'
        $projectArgs += 'Desenvolver.bat'
        $projectArgs += 'dev-windows.ps1'
        $projectArgs += 'phpunit.xml'
        $projectArgs += '.phpunit.result.cache'
        $projectArgs += 'instalacao.log'
        $projectArgs += 'tmp-print-props.txt'
        $projectArgs += '_tmp_parse_ps1.ps1'
        $projectArgs += '*.bak'
        $projectArgs += '_tmp_*.ps1'
        $projectArgs += 'Gerar Instalador*.bat'
        $projectArgs += 'Gerar Pacote*.bat'
        $projectArgs += 'Gerar Staging*.bat'
        $projectArgs += 'Publicar Update*.bat'
        $projectArgs += '.env.appurl.local.bak'
        $projectArgs += '.env*.local*'
        # OpenSSL provider DLL fica carregada pelo PHP embutido; apply falha no Windows.
        # Mantem o .cnf da pasta resources\ssl\openssl no pacote.
        $projectArgs += 'legacy.dll'
        $projectArgs += 'fips.dll'
        $projectArgs += 'legacy.so'
        $projectArgs += 'legacy.dylib'
        # Emuladores de balanca (so DEV).
        $projectArgs += 'Balanca Teste Portable.exe'
        $projectArgs += 'Balanca TesteRS232 Full.exe'
    }

    if (-not $Quiet) {
        Write-Host ">> Copiando projeto para $targetFull" -ForegroundColor White
    }

    & robocopy @projectArgs | Out-Null
    if ($LASTEXITCODE -ge 8) {
        throw "robocopy projeto falhou (codigo $LASTEXITCODE)."
    }

    if (-not (Test-Path (Join-Path $sourceFull 'vendor\autoload.php'))) {
        throw 'vendor/autoload.php ausente na origem.'
    }

    if (-not $Quiet) {
        Write-Host '>> Copiando vendor/ (pode demorar alguns minutos)' -ForegroundColor White
    }

    $vendorArgs = @(
        (Join-Path $sourceFull 'vendor'),
        (Join-Path $targetFull 'vendor'),
        '/E', '/MT:8', '/R:2', '/W:2',
        '/NFL', '/NDL', '/NJH', '/NJS', '/NC', '/NS'
    )

    if ($UpdateMode) {
        # Dev-only: nao precisa no cliente.
        $pintDir = Join-Path $sourceFull 'vendor\laravel\pint'
        if (Test-Path $pintDir) {
            $vendorArgs += '/XD'
            $vendorArgs += $pintDir
        }
    }

    & robocopy @vendorArgs | Out-Null
    if ($LASTEXITCODE -ge 8) {
        throw "robocopy vendor falhou (codigo $LASTEXITCODE)."
    }

    if ($UpdateMode) {
        foreach ($extra in @(
            (Join-Path $targetFull 'vendor\laravel\pint'),
            (Join-Path $targetFull 'vendor\bin\pint'),
            (Join-Path $targetFull 'vendor\bin\pint.bat'),
            (Join-Path $targetFull 'composer.phar'),
            (Join-Path $targetFull 'composer-setup.php'),
            (Join-Path $targetFull 'vc_redist.x64.exe'),
            (Join-Path $targetFull 'Desenvolver.bat'),
            (Join-Path $targetFull 'phpunit.xml'),
            (Join-Path $targetFull '.phpunit.result.cache'),
            (Join-Path $targetFull 'instalacao.log'),
            (Join-Path $targetFull 'tmp-print-props.txt'),
            (Join-Path $targetFull '_tmp_parse_ps1.ps1'),
            (Join-Path $targetFull '.env.appurl.local.bak'),
            (Join-Path $targetFull 'scripts\dev-windows.ps1'),
            (Join-Path $targetFull 'importar')
        )) {
            if (Test-Path $extra) {
                Remove-Item $extra -Recurse -Force -ErrorAction SilentlyContinue
            }
        }

        # Residuos de build/dev que nao devem ir ao cliente.
        Get-ChildItem -Path $targetFull -File -Force -ErrorAction SilentlyContinue |
            Where-Object {
                $_.Name -like '*.bak' -or
                $_.Name -like '_tmp_*' -or
                $_.Name -like 'Gerar Instalador*.bat' -or
                $_.Name -like 'Gerar Pacote*.bat' -or
                $_.Name -like 'Gerar Staging*.bat' -or
                $_.Name -like 'Publicar Update*.bat' -or
                $_.Name -like '.env*.local*' -or
                $_.Name -like 'Balanca Teste*.exe'
            } |
            Remove-Item -Force -ErrorAction SilentlyContinue

        # Device Service: remove src/tests (e dist, se nao foi pedido neste release).
        $deviceDevOnly = @(
            (Join-Path $targetFull 'services\unitec-device-service\src'),
            (Join-Path $targetFull 'services\unitec-device-service\tests')
        )
        if (-not $IncludeDeviceService) {
            $deviceDevOnly += (Join-Path $targetFull 'services\unitec-device-service\dist')
        }
        foreach ($devOnly in $deviceDevOnly) {
            if (Test-Path $devOnly) {
                Remove-Item $devOnly -Recurse -Force -ErrorAction SilentlyContinue
            }
        }

        # Qualquer bin/obj sob services/ (artefato de compilacao .NET).
        $servicesRoot = Join-Path $targetFull 'services'
        if (Test-Path $servicesRoot) {
            Get-ChildItem -Path $servicesRoot -Directory -Recurse -Force -ErrorAction SilentlyContinue |
                Where-Object { $_.Name -eq 'bin' -or $_.Name -eq 'obj' } |
                Remove-Item -Recurse -Force -ErrorAction SilentlyContinue
        }

        if ($IncludeDeviceService) {
            $deviceDistExe = Join-Path $targetFull 'services\unitec-device-service\dist\Unitec.DeviceService.exe'
            if (-not (Test-Path $deviceDistExe)) {
                throw 'Pacote invalido: -IncludeDeviceService exige services\unitec-device-service\dist\Unitec.DeviceService.exe.'
            }
        }
    }

    Remove-PublicStorageLink -Root $targetFull
}

function Get-UnitecStagingRequiredPaths {
    return @(
        'artisan',
        'vendor\autoload.php',
        'scripts\instalar-tudo.ps1',
        'scripts\setup-prerequisites.ps1',
        'scripts\unitec-install-lib.ps1',
        'scripts\verificar-pc.ps1',
        'public\build',
        'installer\assets\mariadb-win.zip',
        'installer\assets\php-8.4-win.zip',
        'installer\assets\vc_redist.x64.exe',
        'installer\assets\cacert.pem'
    )
}

function Get-UnitecStagingOptionalPaths {
    return @(
        'installer\assets\HeidiSQL_12.18.0.7304_Setup.exe',
        'installer\assets\unitec-erp.ico'
    )
}

function Ensure-UnitecAppIconAsset {
    param([string]$TargetPath)

    if (Test-Path $TargetPath) {
        return
    }

    Ensure-Directory (Split-Path $TargetPath -Parent)

    try {
        Add-Type -AssemblyName System.Drawing
        $icon = [System.Drawing.SystemIcons]::Application
        $stream = [System.IO.File]::Create($TargetPath)
        try {
            $icon.Save($stream)
        } finally {
            $stream.Close()
        }
        Write-Warn "icone padrao gerado em $TargetPath (substitua por unitec-erp.ico da marca)."
    } catch {
        throw "icone do instalador ausente: $TargetPath"
    }
}

function Test-UnitecStagingReady {
    param(
        [string]$Root,
        [int]$MinFileCount = 1000
    )

    foreach ($rel in (Get-UnitecStagingRequiredPaths)) {
        if (-not (Test-Path (Join-Path $Root $rel))) {
            return $false
        }
    }

    $fileCount = (Get-ChildItem $Root -Recurse -File -ErrorAction SilentlyContinue | Measure-Object).Count
    return ($fileCount -ge $MinFileCount)
}

function Assert-UnitecStagingReady {
    param(
        [string]$Root,
        [int]$MinFileCount = 1000
    )

    $missing = @()
    foreach ($rel in (Get-UnitecStagingRequiredPaths)) {
        $full = Join-Path $Root $rel
        if (-not (Test-Path $full)) {
            $missing += $rel
        }
    }

    if ($missing.Count -gt 0) {
        throw ('Staging incompleto em {0}. Faltando: {1}' -f $Root, ($missing -join ', '))
    }

    Assert-UnitecMariaDbZipAsset -ZipPath (Join-Path $Root 'installer\assets\mariadb-win.zip')

    $stagingTools = Join-Path $Root 'tools'
    if (Test-Path $stagingTools) {
        throw ('Staging nao deve incluir tools\ (runtime e extraido na instalacao). Remova: {0}' -f $stagingTools)
    }

    $fileCount = (Get-ChildItem $Root -Recurse -File -ErrorAction SilentlyContinue | Measure-Object).Count
    if ($fileCount -lt $MinFileCount) {
        throw ('Staging incompleto em {0}. Apenas {1} arquivos (esperado >= {2}).' -f $Root, $fileCount, $MinFileCount)
    }

    $bomFiles = Test-UnitecPhpSourcesWithoutBom -Root $Root
    if ($bomFiles.Count -gt 0) {
        $sample = ($bomFiles | Select-Object -First 5) -join ', '
        throw ('Arquivos PHP invalidos (UTF-8 BOM) em {0}: {1}' -f $Root, $sample)
    }
}

function Test-UnitecIsAdministrator {
    $identity = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = New-Object Security.Principal.WindowsPrincipal($identity)
    return $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
}

function Test-UnitecWindows64 {
    return [Environment]::Is64BitOperatingSystem
}

function Test-UnitecDiskSpace {
    param(
        [string]$DriveRoot = 'C:\',
        [long]$MinFreeMb = $script:UnitecMinDiskSpaceMb
    )

    $root = if ($DriveRoot.Length -ge 2) { $DriveRoot.Substring(0, 2) } else { 'C:' }
    $info = New-Object System.IO.DriveInfo($root)
    $freeMb = [math]::Round($info.AvailableFreeSpace / 1MB)

    return @{
        Ok     = ($freeMb -ge $MinFreeMb)
        FreeMb = $freeMb
        MinMb  = $MinFreeMb
    }
}

function Test-UnitecTcpPortInUse {
    param([int]$Port)

    try {
        $listeners = @(Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue)
        if ($listeners.Count -gt 0) {
            return $true
        }
    } catch {
        # fallback abaixo
    }

    $pattern = "(:|\.)$Port\s"
    $matches = netstat -an | Select-String -Pattern $pattern
    return ($null -ne $matches -and @($matches).Count -gt 0)
}

function Test-PhpVersionString {
    param([string]$Text)

    return ($Text -match '^\d+\.\d+\.\d+$')
}

function Test-PhpVcRuntimeIssue {
    param(
        [hashtable]$Result,
        [switch]$TreatGenericFailure
    )

    if ($Result.VcRuntimeIssue) {
        return $true
    }

    $haystack = ($Result.Error + ' ' + $Result.Version) -replace '\s+', ' '
    if ($haystack -match 'VCRUNTIME140|not compatible with this PHP build|Visual C\+\+') {
        return $true
    }

    if ($TreatGenericFailure -and -not $Result.Ok) {
        if ($haystack -match 'nao retornou versao|Redistributable') {
            return $true
        }
    }

    return $false
}

function Invoke-PhpExecutableTest {
    param([string]$PhpExe)

    if (-not (Test-UnitecPathExists $PhpExe)) {
        return @{
            Ok             = $false
            Version        = ''
            Error          = if ([string]::IsNullOrWhiteSpace($PhpExe)) {
                'php.exe nao informado.'
            } else {
                "php.exe nao encontrado: $PhpExe"
            }
            VcRuntimeIssue = $false
        }
    }

    # stderr ao lado do php.exe — evita $env:TEMP (caminho 8.3 quebrado em alguns PCs).
    $phpDir = Split-Path -Parent $PhpExe
    $stderrFile = Join-Path $phpDir 'unitec-php-err.txt'
    try {
        $output = & $PhpExe -r 'echo PHP_VERSION;' 2> $stderrFile
        $stderr = ''
        if (Test-Path -LiteralPath $stderrFile) {
            $stderr = Get-UnitecTrimmedFileContent -Path $stderrFile
        }

        $stdoutText = ''
        if ($null -ne $output) {
            $stdoutText = ($output | Out-String).Trim()
        }
        $combined = ($stdoutText + ' ' + $stderr).Trim()
        $vcIssue = ($combined -match 'VCRUNTIME140|not compatible with this PHP build|Visual C\+\+')

        if (Test-PhpVersionString $stdoutText) {
            return @{
                Ok             = $true
                Version        = $stdoutText
                Error          = ''
                VcRuntimeIssue = $false
            }
        }

        if ($vcIssue) {
            $errorText = if ([string]::IsNullOrWhiteSpace($combined)) {
                'Visual C++ Redistributable x64 desatualizado ou ausente.'
            } else {
                $combined.Trim()
            }

            return @{
                Ok             = $false
                Version        = ''
                Error          = $errorText
                VcRuntimeIssue = $true
            }
        }

        $errorText = if ([string]::IsNullOrWhiteSpace($stderr)) {
            if ([string]::IsNullOrWhiteSpace($stdoutText)) {
                'PHP nao retornou versao (verifique Visual C++ Redistributable x64).'
            } else {
                $stdoutText
            }
        } else {
            $stderr
        }

        return @{
            Ok             = $false
            Version        = ''
            Error          = $errorText
            VcRuntimeIssue = $false
        }
    } finally {
        Remove-Item -LiteralPath $stderrFile -Force -ErrorAction SilentlyContinue
    }
}

function Get-PhpVersionFromExe {
    param(
        [string]$PhpExe = 'php',
        [string]$SourceRoot = '',
        [switch]$AllowFix
    )

    if ($PhpExe -eq 'php') {
        $cmd = Get-Command php -ErrorAction SilentlyContinue
        if ($cmd) {
            $PhpExe = $cmd.Source
        }
    }

    if ($AllowFix -and -not [string]::IsNullOrWhiteSpace($SourceRoot)) {
        $result = Repair-PhpExecutableRuntime -SourceRoot $SourceRoot -PhpExe $PhpExe -AllowFix
    } else {
        $result = Invoke-PhpExecutableTest -PhpExe $PhpExe
    }

    if (-not $result.Ok) {
        throw $result.Error
    }

    return $result.Version
}

function Resolve-VcRedistributablePath {
    param([string]$SourceRoot)

    $candidates = @()
    if (-not [string]::IsNullOrWhiteSpace($SourceRoot)) {
        $candidates += (Join-Path $SourceRoot 'installer\assets\vc_redist.x64.exe')
        $candidates += (Join-Path $SourceRoot 'tools\vc_redist.x64.exe')
        $candidates += (Join-Path $SourceRoot 'vc_redist.x64.exe')
    }
    $candidates += (Join-Path $PSScriptRoot '..\installer\assets\vc_redist.x64.exe')
    $candidates += (Join-Path $PSScriptRoot '..\vc_redist.x64.exe')
    $candidates += (Join-Path $env:TEMP 'unitec-vc-redist-x64.exe')
    $candidates += (Join-Path $env:TEMP 'vc_redist.x64.exe')

    foreach ($path in $candidates) {
        if ([string]::IsNullOrWhiteSpace($path)) {
            continue
        }

        try {
            $full = [System.IO.Path]::GetFullPath($path)
        } catch {
            continue
        }

        if (Test-UnitecPathExists $full) {
            return $full
        }
    }

    $downloaded = Join-Path $env:TEMP 'unitec-vc-redist-x64.exe'
    Write-Host 'Baixando Visual C++ Redistributable x64 (~25 MB)...' -ForegroundColor Yellow
    try {
        [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
        Invoke-WebRequest -Uri $script:UnitecVcRedistDownloadUrl -OutFile $downloaded -UseBasicParsing
        return $downloaded
    } catch {
        throw @"
Visual C++ Redistributable ausente e download falhou.
Baixe manualmente de: $($script:UnitecVcRedistDownloadUrl)
Salve como: installer\assets\vc_redist.x64.exe
"@
    }
}

function Install-VcRedistributable {
    param(
        [string]$SourceRoot,
        [ValidateSet('install', 'repair')]
        [string]$Mode = 'install'
    )

    $vcPath = Resolve-VcRedistributablePath -SourceRoot $SourceRoot
    $assetTarget = Join-Path $SourceRoot 'installer\assets\vc_redist.x64.exe'

    if ($SourceRoot -and (Test-Path (Split-Path $assetTarget -Parent)) -and -not (Test-Path $assetTarget)) {
        try {
            Copy-Item $vcPath $assetTarget -Force
        } catch {
            Write-Warn 'Nao foi possivel copiar vc_redist.x64.exe para installer\assets (ignorado).'
        }
    }

    $label = if ($Mode -eq 'repair') { 'Reparando' } else { 'Instalando/atualizando' }
    Write-Host "$label Visual C++ Redistributable x64 (requerido pelo PHP)..." -ForegroundColor White

    $proc = Start-UnitecHiddenProcess -FilePath $vcPath -ArgumentList @("/$Mode", '/quiet', '/norestart') -Wait -PassThru

    if ($null -eq $proc) {
        throw 'Nao foi possivel iniciar o instalador do Visual C++ Redistributable.'
    }

    $okCodes = @(0, 1638, 3010, 5100)
    if ($okCodes -notcontains $proc.ExitCode) {
        throw "Visual C++ Redistributable falhou no modo $Mode (codigo $($proc.ExitCode))."
    }

    if ($proc.ExitCode -eq 3010) {
        Write-Warn 'Visual C++ instalado - reinicio do Windows recomendado (continuando testes).'
    } else {
        Write-Ok 'Visual C++ Redistributable instalado/atualizado.'
    }

    return $proc.ExitCode
}

function Invoke-PhpRuntimeRepair {
    param(
        [string]$SourceRoot,
        [scriptblock]$Retest
    )

    Write-Host ''
    Write-Host 'Corrigindo Visual C++ Redistributable (PHP 8.4)...' -ForegroundColor Cyan

    $modes = @('install', 'repair')
    foreach ($mode in $modes) {
        Install-VcRedistributable -SourceRoot $SourceRoot -Mode $mode
        Start-Sleep -Seconds 3

        for ($attempt = 1; $attempt -le 4; $attempt++) {
            $result = & $Retest
            if ($result.Ok) {
                Write-Ok ('PHP OK apos correcao do Visual C++ (tentativa {0}).' -f $attempt)
                return $result
            }

            if (-not (Test-PhpVcRuntimeIssue -Result $result -TreatGenericFailure)) {
                return $result
            }

            Start-Sleep -Seconds 2
        }
    }

    $last = & $Retest
    if (-not $last.Ok -and (Test-PhpVcRuntimeIssue -Result $last -TreatGenericFailure)) {
        throw @"
Visual C++ Redistributable foi instalado, mas o PHP ainda nao executa neste PC.

Reinicie o Windows e execute o instalador novamente.
Se persistir, instale manualmente:
$($script:UnitecVcRedistDownloadUrl)
"@
    }

    return $last
}

function Repair-PhpExecutableRuntime {
    param(
        [string]$SourceRoot,
        [string]$PhpExe,
        [switch]$AllowFix
    )

    $result = Invoke-PhpExecutableTest -PhpExe $PhpExe
    if ($result.Ok) {
        return $result
    }

    if (-not $AllowFix) {
        return $result
    }

    if (-not (Test-PhpVcRuntimeIssue -Result $result -TreatGenericFailure)) {
        return $result
    }

    return Invoke-PhpRuntimeRepair -SourceRoot $SourceRoot -Retest {
        Invoke-PhpExecutableTest -PhpExe $PhpExe
    }
}

function Test-BundledPhpRuntime {
    param(
        [string]$SourceRoot,
        [switch]$AllowFix
    )

    Initialize-UnitecSafeTempEnvironment -AppPath $script:UnitecDefaultAppPath | Out-Null

    $zipPath = Resolve-Php84ZipPath -SourceRoot $SourceRoot

    # Caminho fixo (sem TEMP do perfil do usuario).
    $tempDir = New-UnitecInstallTempDir -AppPath $script:UnitecDefaultAppPath -Prefix 'php-test'

    try {
        Expand-Archive -LiteralPath $zipPath -DestinationPath $tempDir -Force

        $phpExe = Join-Path $tempDir 'php.exe'
        if (-not (Test-Path -LiteralPath $phpExe)) {
            $subdir = Get-ChildItem -LiteralPath $tempDir -Directory -ErrorAction SilentlyContinue | Select-Object -First 1
            if ($subdir) {
                $phpExe = Join-Path $subdir.FullName 'php.exe'
            }
        }

        if (-not (Test-Path -LiteralPath $phpExe)) {
            throw 'php.exe nao encontrado no pacote PHP 8.4 embutido.'
        }

        return Repair-PhpExecutableRuntime -SourceRoot $SourceRoot -PhpExe $phpExe -AllowFix:$AllowFix
    } finally {
        Remove-Item -LiteralPath $tempDir -Recurse -Force -ErrorAction SilentlyContinue
    }
}

function Invoke-UnitecSystemRequirementsCheck {
    param(
        [string]$SourceRoot = '',
        [switch]$FixVcRuntime,
        [switch]$Quiet
    )

    Initialize-UnitecSafeTempEnvironment -AppPath $script:UnitecDefaultAppPath | Out-Null

    $results = @()

    if (-not (Test-UnitecIsAdministrator)) {
        $results += @{ Name = 'Administrador'; Ok = $false; Detail = 'Execute como administrador.' }
    } else {
        $results += @{ Name = 'Administrador'; Ok = $true; Detail = 'OK' }
    }

    if (-not (Test-UnitecWindows64)) {
        $results += @{ Name = 'Windows 64 bits'; Ok = $false; Detail = 'Requer Windows 64 bits.' }
    } else {
        $results += @{ Name = 'Windows 64 bits'; Ok = $true; Detail = 'OK' }
    }

    $disk = Test-UnitecDiskSpace
    if (-not $disk.Ok) {
        $results += @{
            Name   = 'Espaco em disco (C:)'
            Ok     = $false
            Detail = ('Livre: {0} MB (minimo {1} MB)' -f $disk.FreeMb, $disk.MinMb)
        }
    } else {
        $results += @{
            Name   = 'Espaco em disco (C:)'
            Ok     = $true
            Detail = ('{0} MB livres' -f $disk.FreeMb)
        }
    }

    if (Test-UnitecTcpPortInUse -Port 80) {
        $results += @{ Name = 'Porta 80 (HTTP)'; Ok = $true; Detail = 'AVISO: em uso - pode conflitar com Apache.' }
    } else {
        $results += @{ Name = 'Porta 80 (HTTP)'; Ok = $true; Detail = 'Livre' }
    }

    if (Test-UnitecTcpPortInUse -Port 3306) {
        $results += @{ Name = 'Porta 3306 (MySQL)'; Ok = $true; Detail = 'AVISO: em uso - outro MySQL pode conflitar.' }
    } else {
        $results += @{ Name = 'Porta 3306 (MySQL)'; Ok = $true; Detail = 'Livre' }
    }

    if (-not [string]::IsNullOrWhiteSpace($SourceRoot)) {
        $SourceRoot = Resolve-UnitecAppPath -Path $SourceRoot
    }

    if (-not [string]::IsNullOrWhiteSpace($SourceRoot) -and (Test-Path $SourceRoot)) {
        foreach ($rel in @(
            'vendor\autoload.php',
            'public\build',
            'installer\assets\mariadb-win.zip',
            'installer\assets\php-8.4-win.zip',
            'installer\assets\vc_redist.x64.exe',
            'installer\assets\HeidiSQL_12.18.0.7304_Setup.exe'
        )) {
            $full = Join-Path $SourceRoot $rel
            if (-not (Test-Path $full)) {
                $results += @{ Name = "Pacote: $rel"; Ok = $false; Detail = 'Ausente no instalador.' }
            } else {
                $results += @{ Name = "Pacote: $rel"; Ok = $true; Detail = 'OK' }
            }
        }

        if ($FixVcRuntime) {
            try {
                Write-Host 'Atualizando Visual C++ Redistributable (preventivo)...' -ForegroundColor Gray
                Install-VcRedistributable -SourceRoot $SourceRoot -Mode 'install'
                Start-Sleep -Seconds 2
            } catch {
                Write-Warn ('Nao foi possivel atualizar Visual C++ preventivamente: {0}' -f $_.Exception.Message)
            }
        }

        try {
            $phpTest = Test-BundledPhpRuntime -SourceRoot $SourceRoot -AllowFix:$FixVcRuntime
            if ($phpTest.Ok) {
                $results += @{ Name = 'PHP 8.4 (teste real)'; Ok = $true; Detail = ('Versao {0}' -f $phpTest.Version) }
            } else {
                $results += @{ Name = 'PHP 8.4 (teste real)'; Ok = $false; Detail = $phpTest.Error }
            }
        } catch {
            $results += @{ Name = 'PHP 8.4 (teste real)'; Ok = $false; Detail = $_.Exception.Message }
        }
    }

    if (-not $Quiet) {
        Write-Title 'Checklist do PC'
        foreach ($item in $results) {
            if ($item.Ok) {
                Write-Ok ('{0} - {1}' -f $item.Name, $item.Detail)
            } else {
                Write-Err ('{0} - {1}' -f $item.Name, $item.Detail)
            }
        }
    }

    return $results
}

function Assert-UnitecSystemRequirements {
    param(
        [string]$SourceRoot,
        [switch]$FixVcRuntime
    )

    $results = Invoke-UnitecSystemRequirementsCheck -SourceRoot $SourceRoot -FixVcRuntime:$FixVcRuntime
    $failed = @($results | Where-Object { -not $_.Ok })

    if ($failed.Count -gt 0) {
        $lines = ($failed | ForEach-Object { '- {0}: {1}' -f $_.Name, $_.Detail }) -join [Environment]::NewLine
        throw @"
Este PC nao atende aos requisitos para instalar o Unitec ERP:

$lines

Consulte instalacao.log ou docs\INSTALACAO-CLIENTE.md
"@
    }
}

function Sync-InstallerAssetsToStaging {
    param(
        [string]$ProjectRoot,
        [string]$StagingDir
    )

    $targetDir = Join-Path $StagingDir 'installer\assets'
    Ensure-Directory $targetDir

    foreach ($name in $script:UnitecInstallerAssetNames) {
        $source = Join-Path $ProjectRoot "installer\assets\$name"

        if (Test-Path $source) {
            Copy-Item $source (Join-Path $targetDir $name) -Force
        }
    }
}

function Initialize-LaragonPath {
    param(
        [string]$LaragonPath = 'C:\laragon',
        [string]$AppPath = ''
    )

    Initialize-UnitecRuntimePath -AppPath $AppPath -LaragonPath $LaragonPath
}

function Initialize-UnitecRuntimePath {
    param(
        [string]$AppPath = '',
        [string]$LaragonPath = 'C:\laragon'
    )

    $paths = @()

    if (-not [string]::IsNullOrWhiteSpace($AppPath)) {
        try {
            $resolvedApp = Resolve-UnitecAppPath -Path $AppPath
            $phpDir = Get-UnitecPhpDirectory -AppPath $resolvedApp
            if ($phpDir) {
                $paths += $phpDir
            }

            $mysqlBin = Join-Path (Get-UnitecMysqlRoot -AppPath $resolvedApp) 'bin'
            if (Test-Path $mysqlBin) {
                $paths += $mysqlBin
            }
        } catch {
            # AppPath invalido; tentar Laragon legado abaixo
        }
    }

    if ($paths.Count -eq 0 -and (Test-Path $LaragonPath)) {
        $phpRoot = Join-Path $LaragonPath 'bin\php'
        $phpDir = $null

        $laragonIni = Join-Path $LaragonPath 'usr\laragon.ini'
        if (Test-Path $laragonIni) {
            $inPhp = $false
            foreach ($line in (Get-Content $laragonIni -Encoding UTF8)) {
                if ($line -match '^\[php\]') {
                    $inPhp = $true
                    continue
                }
                if ($inPhp -and $line -match '^\[') {
                    break
                }
                if ($inPhp -and $line -match '^Version=(.+)$') {
                    $candidate = Join-Path $phpRoot $matches[1].Trim()
                    if (Test-Path $candidate) {
                        $phpDir = Get-Item $candidate
                    }
                    break
                }
            }
        }

        if (-not $phpDir) {
            $preferred = Find-LaragonPhpFolder -LaragonPath $LaragonPath
            if ($preferred) {
                $phpDir = Get-Item (Join-Path $phpRoot $preferred)
            } else {
                $phpDir = Get-ChildItem $phpRoot -Directory -ErrorAction SilentlyContinue |
                    Sort-Object { Get-PhpVersionIdFromFolderName $_.Name } -Descending |
                    Select-Object -First 1
            }
        }
        if ($phpDir) {
            $paths += $phpDir.FullName
        }

        $mysqlExe = Get-ChildItem "$LaragonPath\bin\mysql" -Filter mysql.exe -Recurse -ErrorAction SilentlyContinue |
            Select-Object -First 1
        if ($mysqlExe) {
            $paths += Split-Path $mysqlExe.FullName
        }
    }

    foreach ($path in ($paths | Select-Object -Unique)) {
        if ($env:Path -notlike "*$path*") {
            $env:Path = "$path;$env:Path"
        }
    }
}

function Test-Tool($name, $versionArgs = @('--version')) {
    $cmd = Get-Command $name -ErrorAction SilentlyContinue
    if (-not $cmd) {
        return $false
    }

    try {
        & $name @versionArgs 2>$null | Out-Null
        return $true
    } catch {
        return $false
    }
}

function Invoke-Step($label, [scriptblock]$action) {
    Write-Host ''
    Write-Host ">> $label" -ForegroundColor White
    & $action
}

function Read-Default($prompt, $default) {
    $value = Read-Host "$prompt [$default]"
    if ([string]::IsNullOrWhiteSpace($value)) {
        return $default
    }

    return $value.Trim()
}

function Read-SecretDefault($prompt, $default = '') {
    if ($default -ne '') {
        $value = Read-Host "$prompt (Enter = padrao Laragon vazio)"
    } else {
        $value = Read-Host $prompt
    }

    if ([string]::IsNullOrWhiteSpace($value)) {
        return $default
    }

    return $value
}

function Format-EnvValue($value) {
    if ($null -eq $value) {
        return ''
    }

    if ($value -eq '' -or $value -notmatch '[#\s"\\=@]') {
        return $value
    }

    $escaped = $value -replace '\\', '\\\\' -replace '"', '\"'
    return "`"$escaped`""
}

function Test-PhpExtensionEnabled {
    param(
        [string]$ExtensionName,
        [string]$PhpExe = ''
    )

    if ([string]::IsNullOrWhiteSpace($ExtensionName)) {
        return $false
    }

    if ([string]::IsNullOrWhiteSpace($PhpExe)) {
        $cmd = Get-Command php -ErrorAction SilentlyContinue
        $PhpExe = if ($cmd) { $cmd.Source } else { 'php' }
    }

    if (-not (Test-Path -LiteralPath $PhpExe)) {
        return $false
    }

    # Evita falha silenciosa: em alguns PCs o stderr do PHP vira ErrorRecord no PowerShell.
    # Testa com cwd = pasta do php.exe para extension_dir="ext" relativo funcionar.
    $prevEap = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    $phpDir = Split-Path -Parent $PhpExe
    $pushOk = $false
    try {
        if (Test-Path -LiteralPath $phpDir) {
            Push-Location $phpDir
            $pushOk = $true
        }
        $output = & $PhpExe -m 2>&1
    } catch {
        $output = @()
    } finally {
        if ($pushOk) {
            Pop-Location
        }
        $ErrorActionPreference = $prevEap
    }

    $text = ($output | ForEach-Object { "$_" }) -join [Environment]::NewLine
    return [bool]([regex]::IsMatch($text, ('(?im)^\s*{0}\s*$' -f [regex]::Escape($ExtensionName))))
}

function Get-UnitecUpdatePendingFinishPath {
    param([string]$AppPath)

    return Join-Path (Resolve-UnitecAppPath -Path $AppPath) 'storage\app\private\erp-update-pending-finish.json'
}

function Write-UnitecUpdatePendingFinish {
    param(
        [string]$AppPath,
        [string]$Reason = 'pdo_mysql'
    )

    $AppPath = Resolve-UnitecAppPath -Path $AppPath
    Ensure-UnitecStorageStructure -AppPath $AppPath
    $path = Get-UnitecUpdatePendingFinishPath -AppPath $AppPath
    $payload = @{
        pending     = $true
        reason      = $Reason
        created_at  = (Get-Date).ToString('o')
        message     = 'Arquivos da atualizacao ja aplicados. Falta migrate/cache apos PHP/VC++ ok.'
    } | ConvertTo-Json -Compress

    Set-Content -Path $path -Value $payload -Encoding ASCII
    Write-InstallLog -AppPath $AppPath -Message ('Update pendente de finalizacao: {0}' -f $Reason)
}

function Clear-UnitecUpdatePendingFinish {
    param([string]$AppPath)

    $path = Get-UnitecUpdatePendingFinishPath -AppPath $AppPath
    if (Test-Path $path) {
        Remove-Item $path -Force -ErrorAction SilentlyContinue
    }
}

function Test-UnitecUpdatePendingFinish {
    param([string]$AppPath)

    return (Test-Path (Get-UnitecUpdatePendingFinishPath -AppPath $AppPath))
}

function Complete-UnitecPendingUpdate {
    param([string]$AppPath)

    $AppPath = Resolve-UnitecAppPath -Path $AppPath
    if (-not (Test-UnitecUpdatePendingFinish -AppPath $AppPath)) {
        return $false
    }

    Write-Host 'Finalizando atualizacao pendente...' -ForegroundColor Cyan
    Write-InstallLog -AppPath $AppPath -Message 'Finalizando update pendente apos reboot/reparo PHP.'

    $ready = Ensure-UnitecPhpExtensionsReady -AppPath $AppPath -AllowVcFix
    if (-not $ready.Ok) {
        throw $ready.Message
    }

    # Limpa caches velhos do update interrompido.
    $bootstrapCache = Join-Path $AppPath 'bootstrap\cache'
    if (Test-Path $bootstrapCache) {
        Get-ChildItem $bootstrapCache -Filter '*.php' -File -ErrorAction SilentlyContinue |
            Remove-Item -Force -ErrorAction SilentlyContinue
    }

    Invoke-UnitecDatabaseMigrate -AppPath $AppPath
    Invoke-UnitecArtisan -AppPath $AppPath -Arguments @('view:clear') -AllowFailure | Out-Null
    Invoke-UnitecArtisan -AppPath $AppPath -Arguments @('config:cache') -AllowFailure | Out-Null
    Clear-UnitecUpdatePendingFinish -AppPath $AppPath
    Write-InstallLog -AppPath $AppPath -Message 'Update pendente finalizado com sucesso.'
    Write-Ok 'Atualizacao pendente concluida.'

    return $true
}

<#
.SYNOPSIS
    Garante php.ini + pdo_mysql/intl. Se falhar, instala VC++ (como a instalacao faz).
#>
function Ensure-UnitecPhpExtensionsReady {
    param(
        [string]$AppPath,
        [switch]$AllowVcFix
    )

    $AppPath = Resolve-UnitecAppPath -Path $AppPath
    $phpDir = Get-UnitecPhpDirectory -AppPath $AppPath
    if (-not $phpDir) {
        return @{
            Ok          = $false
            NeedsReboot = $false
            Message     = 'PHP embutido ausente em tools\php. Reinstale o Unitec ERP.'
        }
    }

    $phpExe = Join-Path $phpDir 'php.exe'
    Configure-LaragonPhpIni -PhpDirectory $phpDir -SourceRoot $AppPath
    $null = Repair-PhpExecutableRuntime -SourceRoot $AppPath -PhpExe $phpExe -AllowFix:$AllowVcFix

    $pdoOk = Test-PhpExtensionEnabled -ExtensionName 'pdo_mysql' -PhpExe $phpExe
    $intlOk = Test-PhpExtensionEnabled -ExtensionName 'intl' -PhpExe $phpExe

    if ($pdoOk -and $intlOk) {
        return @{ Ok = $true; NeedsReboot = $false; Message = 'PHP OK (pdo_mysql + intl).' }
    }

    if (-not $AllowVcFix) {
        return @{
            Ok          = $false
            NeedsReboot = $false
            Message     = 'Extensoes PHP ausentes (pdo_mysql/intl).'
        }
    }

    Write-Host 'PHP sem pdo_mysql/intl - instalando Visual C++ (mesmo da instalacao)...' -ForegroundColor Yellow
    Write-InstallLog -AppPath $AppPath -Message 'Auto-reparo: instalando VC++ por falta de pdo_mysql/intl.'

    $vcExit = 0
    try {
        $vcExit = Install-VcRedistributable -SourceRoot $AppPath -Mode 'install'
        Start-Sleep -Seconds 3
        try {
            $null = Install-VcRedistributable -SourceRoot $AppPath -Mode 'repair'
            Start-Sleep -Seconds 2
        } catch {
            # repair opcional
        }
    } catch {
        return @{
            Ok          = $false
            NeedsReboot = $false
            Message     = ('Falha ao instalar Visual C++: {0}' -f $_.Exception.Message)
        }
    }

    Configure-LaragonPhpIni -PhpDirectory $phpDir -SourceRoot $AppPath
    $pdoOk = Test-PhpExtensionEnabled -ExtensionName 'pdo_mysql' -PhpExe $phpExe
    $intlOk = Test-PhpExtensionEnabled -ExtensionName 'intl' -PhpExe $phpExe

    if ($pdoOk -and $intlOk) {
        Write-Ok 'Extensoes PHP OK apos Visual C++.'
        return @{ Ok = $true; NeedsReboot = $false; Message = 'PHP OK apos VC++.' }
    }

    $needsReboot = ($vcExit -eq 3010) -or (-not $pdoOk)
    return @{
        Ok          = $false
        NeedsReboot = $needsReboot
        Message     = 'Visual C++ instalado, mas o PHP ainda nao carrega pdo_mysql. Reinicie o Windows e abra o Unitec ERP de novo (a atualizacao continua sozinha).'
    }
}

function Assert-UnitecPhpDatabaseReady {
    param([string]$AppPath = '')

    $ready = Ensure-UnitecPhpExtensionsReady -AppPath $AppPath -AllowVcFix
    if ($ready.Ok) {
        return
    }

    if ($ready.NeedsReboot -and -not [string]::IsNullOrWhiteSpace($AppPath)) {
        Write-UnitecUpdatePendingFinish -AppPath $AppPath -Reason 'vc_reboot_pdo_mysql'
    }

    throw $ready.Message
}

function Assert-UnitecPhpIntlReady {
    param([string]$AppPath = '')

    # intl ja e validado em Ensure-UnitecPhpExtensionsReady / Assert-UnitecPhpDatabaseReady.
    $phpExe = Get-UnitecPhpExecutable -AppPath $AppPath
    if (-not (Test-PhpExtensionEnabled -ExtensionName 'intl' -PhpExe $phpExe)) {
        $ready = Ensure-UnitecPhpExtensionsReady -AppPath $AppPath -AllowVcFix
        if (-not $ready.Ok) {
            throw $ready.Message
        }
    }
}

function Assert-UnitecPhpRuntimeReady {
    param([string]$AppPath = '')

    Assert-UnitecPhpDatabaseReady -AppPath $AppPath
    Assert-UnitecPhpIntlReady -AppPath $AppPath
}

function Test-MysqlDatabaseAccessViaPhp {
    param(
        [string]$AppPath = '',
        [string]$User = $script:UnitecDefaultDbUser,
        [string]$Password = '',
        [string]$Database = $script:UnitecDefaultDbName,
        [string]$MysqlHost = '127.0.0.1',
        [string]$Port = '3306'
    )

    if ([string]::IsNullOrWhiteSpace($Database)) {
        return @{
            Ok    = $false
            Error = 'Banco de dados nao configurado.'
        }
    }

    $AppPath = Resolve-UnitecAppPath -Path $AppPath
    Initialize-UnitecRuntimePath -AppPath $AppPath
    $phpExe = Get-UnitecPhpExecutable -AppPath $AppPath

    if (-not (Test-PhpExtensionEnabled -ExtensionName 'pdo_mysql' -PhpExe $phpExe)) {
        return @{
            Ok    = $false
            Error = 'Extensao PHP pdo_mysql nao esta ativa.'
        }
    }

    $scriptFile = Join-Path $env:TEMP ("unitec-db-test-{0}.php" -f [Guid]::NewGuid().ToString('N'))
    $stderrFile = Join-Path $env:TEMP ("unitec-db-test-err-{0}.txt" -f [Guid]::NewGuid().ToString('N'))
    $phpContent = @'
<?php
declare(strict_types=1);

$host = getenv('UNITEC_DB_HOST') ?: '127.0.0.1';
$port = getenv('UNITEC_DB_PORT') ?: '3306';
$db = getenv('UNITEC_DB_NAME') ?: '';
$user = getenv('UNITEC_DB_USER') ?: 'root';
$pass = getenv('UNITEC_DB_PASSWORD') ?: '';

if ($db === '') {
    fwrite(STDERR, 'Banco de dados nao configurado.');
    exit(2);
}

try {
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $db);
    new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 10,
    ]);
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage());
    exit(1);
}
'@

    $previousHost = $env:UNITEC_DB_HOST
    $previousPort = $env:UNITEC_DB_PORT
    $previousName = $env:UNITEC_DB_NAME
    $previousUser = $env:UNITEC_DB_USER
    $previousPassword = $env:UNITEC_DB_PASSWORD

    try {
        Set-Content -Path $scriptFile -Value $phpContent -Encoding ASCII
        $env:UNITEC_DB_HOST = $MysqlHost
        $env:UNITEC_DB_PORT = $Port
        $env:UNITEC_DB_NAME = $Database
        $env:UNITEC_DB_USER = $User
        $env:UNITEC_DB_PASSWORD = $Password

        & $phpExe $scriptFile 2> $stderrFile | Out-Null
        $exitCode = $LASTEXITCODE
        $stderr = ''
        if (Test-Path $stderrFile) {
            $stderrRaw = Get-Content $stderrFile -Raw -ErrorAction SilentlyContinue
            if ($null -ne $stderrRaw) {
                $stderr = $stderrRaw.Trim()
            }
        }

        return @{
            Ok    = ($exitCode -eq 0)
            Error = $stderr
        }
    } finally {
        Remove-Item $scriptFile -Force -ErrorAction SilentlyContinue
        Remove-Item $stderrFile -Force -ErrorAction SilentlyContinue

        if ($null -ne $previousHost) { $env:UNITEC_DB_HOST = $previousHost } else { Remove-Item Env:UNITEC_DB_HOST -ErrorAction SilentlyContinue }
        if ($null -ne $previousPort) { $env:UNITEC_DB_PORT = $previousPort } else { Remove-Item Env:UNITEC_DB_PORT -ErrorAction SilentlyContinue }
        if ($null -ne $previousName) { $env:UNITEC_DB_NAME = $previousName } else { Remove-Item Env:UNITEC_DB_NAME -ErrorAction SilentlyContinue }
        if ($null -ne $previousUser) { $env:UNITEC_DB_USER = $previousUser } else { Remove-Item Env:UNITEC_DB_USER -ErrorAction SilentlyContinue }
        if ($null -ne $previousPassword) { $env:UNITEC_DB_PASSWORD = $previousPassword } else { Remove-Item Env:UNITEC_DB_PASSWORD -ErrorAction SilentlyContinue }
    }
}

function Get-UnitecDatabaseConnectionHostsFromEnv {
    param([string]$AppPath)

    $db = Get-UnitecDatabaseSettingsFromEnv -AppPath $AppPath

    if (Test-UnitecRemoteDatabaseHost -HostName $db.DbHost) {
        return @($db.DbHost)
    }

    return @($db.DbHost, '127.0.0.1', 'localhost') |
        Where-Object { -not [string]::IsNullOrWhiteSpace($_) } |
        Select-Object -Unique
}

function Get-UnitecDatabaseConnectionFailureDetails {
    param(
        [string]$AppPath,
        [string]$LaragonPath = 'C:\laragon'
    )

    $db = Get-UnitecDatabaseSettingsFromEnv -AppPath $AppPath
    $hosts = Get-UnitecDatabaseConnectionHostsFromEnv -AppPath $AppPath
    $lastError = ''

    foreach ($hostName in $hosts) {
        $mysqlExe = Get-MysqlExecutable -LaragonPath $LaragonPath -AppPath $AppPath
        if ($mysqlExe) {
            $result = Invoke-MysqlClient -LaragonPath $LaragonPath -AppPath $AppPath -Arguments @('-e', 'SELECT 1') -ClientUser $db.DbUser -ClientPassword $db.DbPassword -ClientHost $hostName -ClientPort $db.DbPort -ClientDatabase $db.DbName
            if ($result.Ok) {
                return ''
            }

            if (-not [string]::IsNullOrWhiteSpace($result.Error)) {
                $lastError = $result.Error
            }
        }

        $phpResult = Test-MysqlDatabaseAccessViaPhp -AppPath $AppPath -User $db.DbUser -Password $db.DbPassword -Database $db.DbName -MysqlHost $hostName -Port $db.DbPort
        if ($phpResult.Ok) {
            return ''
        }

        if (-not [string]::IsNullOrWhiteSpace($phpResult.Error)) {
            $lastError = $phpResult.Error
        }
    }

    return $lastError
}

function Test-UnitecDatabaseConnectionFromEnv {
    param(
        [string]$AppPath,
        [string]$LaragonPath = 'C:\laragon'
    )

    $db = Get-UnitecDatabaseSettingsFromEnv -AppPath $AppPath
    $hosts = Get-UnitecDatabaseConnectionHostsFromEnv -AppPath $AppPath

    foreach ($hostName in $hosts) {
        if (Test-MysqlDatabaseAccess -LaragonPath $LaragonPath -AppPath $AppPath -User $db.DbUser -Password $db.DbPassword -Database $db.DbName -MysqlHost $hostName -Port $db.DbPort) {
            return $true
        }
    }

    return $false
}

function Sync-UnitecEnvDatabaseCredentials {
    param(
        [string]$AppPath,
        [string]$DbHost = '127.0.0.1',
        [string]$DbPort = '3306',
        [string]$DbName = 'unitec_erp',
        [string]$DbUser = 'root',
        [string]$DbPassword = ''
    )

    if ([string]::IsNullOrWhiteSpace($DbPassword)) {
        $DbPassword = Get-UnitecDefaultDbPassword
    }

    $envFile = Join-Path $AppPath '.env'
    if (-not (Test-Path $envFile)) {
        return $false
    }

    $values = @{
        DB_HOST     = $DbHost
        DB_PORT     = $DbPort
        DB_DATABASE = $DbName
        DB_USERNAME = $DbUser
        DB_PASSWORD = (Format-EnvValue $DbPassword)
    }

    $lines = @(Get-Content $envFile -Encoding UTF8)
    $updated = $false

    foreach ($key in $values.Keys) {
        $found = $false
        $formatted = $values[$key]

        for ($i = 0; $i -lt $lines.Count; $i++) {
            if ($lines[$i] -match ('^\s*{0}\s*=' -f [regex]::Escape($key))) {
                $lines[$i] = "$key=$formatted"
                $found = $true
                $updated = $true
                break
            }
        }

        if (-not $found) {
            $lines += "$key=$formatted"
            $updated = $true
        }
    }

    if ($updated) {
        Set-UnitecUtf8NoBomFile -Path $envFile -Content ($lines -join [Environment]::NewLine)
        Write-Ok 'Credenciais MySQL sincronizadas no arquivo .env.'
    }

    return $updated
}

function Sync-UnitecEnvPerformanceSettings {
    param([string]$AppPath)

    $envFile = Join-Path (Resolve-UnitecAppPath -Path $AppPath) '.env'
    if (-not (Test-Path $envFile)) {
        return $false
    }

    $values = @{
        SESSION_DRIVER     = 'file'
        CACHE_STORE        = 'file'
        QUEUE_CONNECTION   = 'sync'
        # FrankenPHP: threads no .env. (Legado PHP_CLI_SERVER_WORKERS nao e mais usado no HTTP.)
        FRANKENPHP_NUM_THREADS = '8'
    }

    $lines = @(Get-Content $envFile -Encoding UTF8)
    $isLocalDev = ($lines | Where-Object { $_ -match '^\s*APP_ENV\s*=\s*local\b' }).Count -gt 0 -or
        ($lines | Where-Object { $_ -match '^\s*DEV_RUNTIME\s*=\s*windows\b' }).Count -gt 0

    if ($isLocalDev) {
        $values['LOG_LEVEL'] = 'warning'
        $values['BCRYPT_ROUNDS'] = '4'
        $values['FRANKENPHP_NUM_THREADS'] = '4'
    }

    $updated = $false

    foreach ($key in $values.Keys) {
        $found = $false
        $formatted = $values[$key]

        for ($i = 0; $i -lt $lines.Count; $i++) {
            if ($lines[$i] -match ('^\s*{0}\s*=' -f [regex]::Escape($key))) {
                if ($lines[$i] -ne "$key=$formatted") {
                    $lines[$i] = "$key=$formatted"
                    $updated = $true
                }
                $found = $true
                break
            }
        }

        if (-not $found) {
            $lines += "$key=$formatted"
            $updated = $true
        }
    }

    if (-not $updated) {
        return $false
    }

    Set-UnitecUtf8NoBomFile -Path $envFile -Content ($lines -join [Environment]::NewLine)
    return $true
}

function Invoke-UnitecDatabaseMigrate {
    param(
        [string]$AppPath,
        [string]$LaragonPath = 'C:\laragon',
        [switch]$LogToInstallFile,
        [switch]$FreshInstall
    )

    $db = Get-UnitecDatabaseSettingsFromEnv -AppPath $AppPath
    $remoteDb = Test-UnitecRemoteDatabaseHost -HostName $db.DbHost

    if ($remoteDb) {
        Ensure-UnitecRuntimeInstalled -AppPath $AppPath -SourceRoot $AppPath -SkipMysql
        Initialize-UnitecRuntimePath -AppPath $AppPath
    } else {
        Ensure-UnitecRuntimeInstalled -AppPath $AppPath -SourceRoot $AppPath
        Initialize-UnitecRuntimePath -AppPath $AppPath
        Assert-UnitecPhpRuntimeReady -AppPath $AppPath
        $null = Ensure-LaragonMysqlRunning -AppPath $AppPath -LaragonPath $LaragonPath -ThrowOnFailure
    }

    if (-not (Test-UnitecDatabaseConnectionFromEnv -AppPath $AppPath -LaragonPath $LaragonPath)) {
        $detail = Get-UnitecDatabaseConnectionFailureDetails -AppPath $AppPath -LaragonPath $LaragonPath

        if ($remoteDb) {
            $message = ('Nao foi possivel acessar o banco remoto {0}:{1} com os dados do .env.' -f $db.DbHost, $db.DbPort)
            if (-not [string]::IsNullOrWhiteSpace($detail)) {
                $message += " $detail"
            }

            throw $message
        }

        $message = 'Nao foi possivel acessar o banco unitec_erp via 127.0.0.1 com os dados do .env. Permissoes MySQL incompletas.'
        if (-not [string]::IsNullOrWhiteSpace($detail)) {
            $message += " $detail"
        }

        throw $message
    }

    Push-Location $AppPath
    try {
        Invoke-UnitecArtisan -AppPath $AppPath -Arguments @('config:clear') -AllowFailure | Out-Null

        $useFresh = [bool]$FreshInstall
        if ($useFresh) {
            $looksEmpty = Test-UnitecDatabaseLooksEmpty -AppPath $AppPath -LaragonPath $LaragonPath
            if (-not $looksEmpty) {
                Write-Warn 'migrate:fresh BLOQUEADO: banco ja possui dados. Usando migrate incremental.'
                Write-InstallLog -AppPath $AppPath -Message 'SEGURANCA: migrate:fresh bloqueado — banco com dados detectado.'
                $useFresh = $false
            }
        }

        $migrateCommand = if ($useFresh) { 'migrate:fresh' } else { 'migrate' }
        $result = Invoke-UnitecArtisan -AppPath $AppPath -Arguments @($migrateCommand, '--force')

        if ($LogToInstallFile -and $result.Output) {
            foreach ($line in ($result.Output -split "\r?\n")) {
                if (-not [string]::IsNullOrWhiteSpace($line)) {
                    Write-InstallLog -AppPath $AppPath -Message ('migrate: ' + $line.Trim())
                }
            }
        }

        Invoke-UnitecArtisan -AppPath $AppPath -Arguments @('config:cache') | Out-Null
        Save-UnitecMigrationSignature -AppPath $AppPath
    } finally {
        Pop-Location
    }
}

function Test-UnitecTcpPortOpen {
    param(
        [int]$Port,
        [string]$HostName = '127.0.0.1',
        [int]$TimeoutMs = 800
    )

    $client = $null

    try {
        $client = New-Object System.Net.Sockets.TcpClient
        $connect = $client.BeginConnect($HostName, $Port, $null, $null)
        if (-not $connect.AsyncWaitHandle.WaitOne($TimeoutMs)) {
            return $false
        }

        $client.EndConnect($connect)
        return $client.Connected
    } catch {
        return $false
    } finally {
        if ($null -ne $client) {
            $client.Close()
        }
    }
}

function Get-LaragonMysqldExecutable {
    param(
        [string]$LaragonPath = 'C:\laragon',
        [string]$AppPath = ''
    )

    if (-not [string]::IsNullOrWhiteSpace($AppPath)) {
        $mysqlRoot = Get-UnitecMysqlRoot -AppPath $AppPath
        $embedded = Join-Path $mysqlRoot 'bin\mysqld.exe'
        if (Test-Path $embedded) {
            return Get-Item $embedded
        }

        return Get-ChildItem $mysqlRoot -Filter mysqld.exe -Recurse -ErrorAction SilentlyContinue |
            Sort-Object FullName -Descending |
            Select-Object -First 1
    }

    return Get-ChildItem (Join-Path $LaragonPath 'bin\mysql') -Filter mysqld.exe -Recurse -ErrorAction SilentlyContinue |
        Sort-Object FullName -Descending |
        Select-Object -First 1
}

function Get-LaragonMysqlIniPath {
    param(
        [string]$LaragonPath = 'C:\laragon',
        [string]$MysqlHome = '',
        [string]$AppPath = ''
    )

    $candidates = @()

    if (-not [string]::IsNullOrWhiteSpace($AppPath)) {
        $candidates += (Join-Path (Get-UnitecMysqlRoot -AppPath $AppPath) 'my.ini')
    }

    $candidates += @(
        (Join-Path $LaragonPath 'etc\mysql\my.ini')
    )

    if (-not [string]::IsNullOrWhiteSpace($MysqlHome)) {
        $candidates += (Join-Path $MysqlHome 'my.ini')
    }

    $candidates += (Join-Path $LaragonPath 'bin\mysql\my.ini')

    foreach ($ini in $candidates) {
        if (Test-Path $ini) {
            return $ini
        }
    }

    return $null
}

function Get-MysqlDataDirectoryFromIni {
    param(
        [string]$IniPath,
        [string]$MysqlHome
    )

    if (Test-UnitecPathExists $IniPath) {
        foreach ($line in Get-Content $IniPath -Encoding UTF8 -ErrorAction SilentlyContinue) {
            if ($line -match '^\s*datadir\s*=\s*(.+)$') {
                $dir = $matches[1].Trim().Trim('"').Trim("'")
                if (-not [string]::IsNullOrWhiteSpace($dir)) {
                    return $dir
                }
            }
        }
    }

    if (-not [string]::IsNullOrWhiteSpace($MysqlHome)) {
        return Join-Path $MysqlHome 'data'
    }

    return ''
}

function Test-UnitecMysqlSystemTablesReady {
    param([string]$DataDir)

    if (-not (Test-UnitecPathExists $DataDir)) {
        return $false
    }

    $mysqlDir = Join-Path $DataDir 'mysql'
    if (-not (Test-Path $mysqlDir)) {
        return $false
    }

    foreach ($marker in @('db.frm', 'db.MAD', 'db.ibd', 'global_priv.MAD', 'user.frm', 'user.MAD')) {
        if (Test-Path (Join-Path $mysqlDir $marker)) {
            return $true
        }
    }

    return $false
}

function Test-MysqlDataInitialized {
    param([string]$DataDir)

    return (Test-UnitecMysqlSystemTablesReady -DataDir $DataDir)
}

function Test-UnitecErpDataPresent {
    param([string]$DataDir)

    $erpDir = Join-Path $DataDir 'unitec_erp'
    if (-not (Test-Path $erpDir)) {
        return $false
    }

    $tableFiles = @(Get-ChildItem -Path $erpDir -File -ErrorAction SilentlyContinue |
        Where-Object { $_.Name -match '\.(frm|ibd|MAD|MAI)$' -and $_.Name -ne 'db.opt' })

    return ($tableFiles.Count -gt 5)
}

<#
.SYNOPSIS
    True se a pasta ja e uma instalacao Unitec (modo recupera — nao zerar banco).
.DESCRIPTION
    Detecta sinais duraveis: .env, backup de .env ou datadir do MariaDB embutido.
    Nao usa so public/index.php (o Setup sempre copia isso antes do pos-install).
#>
function Test-UnitecExistingInstall {
    param([string]$AppPath)

    $AppPath = Resolve-UnitecAppPath -Path $AppPath
    if (-not (Test-Path $AppPath)) {
        return $false
    }

    foreach ($name in @('.env', '.env.backup', '.env.production')) {
        if (Test-Path (Join-Path $AppPath $name)) {
            return $true
        }
    }

    $dataDir = Join-Path (Get-UnitecMysqlRoot -AppPath $AppPath) 'data'
    if (Test-Path (Join-Path $dataDir 'ibdata1')) {
        return $true
    }

    if (Test-UnitecErpDataPresent -DataDir $dataDir) {
        return $true
    }

    $backupEnv = Find-UnitecEnvBackupCandidate -AppPath $AppPath
    if ($backupEnv.Count -gt 0) {
        return $true
    }

    return $false
}

function Invoke-UnitecPreUpdateBackup {
    param([string]$AppPath)

    $AppPath = Resolve-UnitecAppPath -Path $AppPath
    Write-InstallLog -AppPath $AppPath -Message 'Backup pre-update: iniciando (banco + .env).'

    # Preferir o mesmo caminho PHP do ERP (mysqldump + copia .env).
    try {
        Invoke-UnitecArtisan -AppPath $AppPath -Arguments @('erp:backup', '--pre-update') | Out-Null
        Write-InstallLog -AppPath $AppPath -Message 'Backup pre-update OK (artisan erp:backup --pre-update).'
        Write-Ok 'Backup de seguranca (banco + .env) gerado antes da atualizacao.'
        return
    } catch {
        Write-InstallLog -AppPath $AppPath -Message ("Backup pre-update via artisan falhou: {0}" -f $_.Exception.Message)
    }

    # Fallback nativo: mysqldump + copia .env
    $db = Get-UnitecDatabaseSettingsFromEnv -AppPath $AppPath
    $mysqlBin = Join-Path (Get-UnitecMysqlRoot -AppPath $AppPath) 'bin'
    $dumpExe = $null
    foreach ($name in @('mariadb-dump.exe', 'mysqldump.exe')) {
        $candidate = Join-Path $mysqlBin $name
        if (Test-Path $candidate) {
            $dumpExe = $candidate
            break
        }
    }

    if (-not $dumpExe) {
        throw 'Atualizacao abortada: backup de seguranca falhou (mysqldump nao encontrado).'
    }

    $backupDir = Join-Path $AppPath 'storage\app\backups'
    Ensure-Directory $backupDir
    $stamp = Get-Date -Format 'yyyy-MM-dd_HH-mm-ss'
    $sqlPath = Join-Path $backupDir ("unitec_erp_preupdate_{0}.sql" -f $stamp)
    $envSrc = Join-Path $AppPath '.env'
    $envDst = Join-Path $backupDir ("unitec_erp_preupdate_{0}.env" -f $stamp)

    $host = $db.DbHost
    if ($host -eq 'localhost' -or $host -eq '::1' -or [string]::IsNullOrWhiteSpace($host)) {
        $host = '127.0.0.1'
    }

    $args = @(
        ("--host={0}" -f $host),
        ("--port={0}" -f $db.DbPort),
        ("--user={0}" -f $db.DbUser),
        ("--password={0}" -f $db.DbPassword),
        '--protocol=TCP',
        '--single-transaction',
        '--routines',
        '--triggers',
        '--events',
        '--hex-blob',
        '--default-character-set=utf8mb4',
        ("--result-file={0}" -f $sqlPath),
        $db.DbName
    )

    $previous = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try {
        & $dumpExe @args 2>&1 | Out-Null
        $exitCode = $LASTEXITCODE
    } finally {
        $ErrorActionPreference = $previous
    }

    if ($exitCode -ne 0 -or -not (Test-Path $sqlPath) -or ((Get-Item $sqlPath).Length -lt 32)) {
        Remove-Item $sqlPath -Force -ErrorAction SilentlyContinue
        throw 'Atualizacao abortada: falha ao gerar dump do banco antes da atualizacao.'
    }

    if (Test-Path $envSrc) {
        Copy-Item $envSrc $envDst -Force
    }

    Write-InstallLog -AppPath $AppPath -Message ("Backup pre-update OK: {0}" -f $sqlPath)
    Write-Ok 'Backup de seguranca (banco + .env) gerado antes da atualizacao.'
}

function Repair-UnitecMysqlDataIfCorrupt {
    param([string]$DataDir)

    if (-not (Test-Path $DataDir)) {
        return
    }

    # NUNCA apagar datadir se ja existem tabelas do ERP (perda total de dados do cliente).
    if (Test-UnitecErpDataPresent -DataDir $DataDir) {
        Write-Host 'MariaDB: dados do ERP detectados — nao sera reinicializado.' -ForegroundColor Gray
        return
    }

    # Protecao absoluta: se ja existe ibdata1 / arquivos InnoDB, NUNCA apagar automaticamente.
    # Antes o script recriava a pasta e zera o banco do cliente. Isso nao pode voltar a acontecer.
    $ibdata = Join-Path $DataDir 'ibdata1'
    if (Test-Path $ibdata) {
        Write-Warn 'MariaDB: pasta data incompleta, mas ibdata1 existe. NAO sera apagada automaticamente (protecao de dados).'
        Write-Warn 'Se o MySQL nao subir, restaure um backup (sql/.env) ou contate o suporte. Nunca delete tools\mysql\data manualmente sem backup.'
        return
    }

    $anyTableFiles = @(Get-ChildItem -Path $DataDir -Recurse -File -ErrorAction SilentlyContinue |
        Where-Object { $_.Name -match '\.(frm|ibd|MAD|MAI)$' } |
        Select-Object -First 1)
    if ($anyTableFiles.Count -gt 0) {
        Write-Warn 'MariaDB: arquivos de tabela detectados em data\. NAO sera apagada automaticamente.'
        return
    }
}

function Get-MariadbInstallDbExecutable {
    param([string]$MysqlBin)

    foreach ($name in @('mariadb-install-db.exe', 'mysql_install_db.exe')) {
        $path = Join-Path $MysqlBin $name
        if (Test-Path $path) {
            return $path
        }
    }

    return $null
}

function Invoke-UnitecMariaDbInstallDb {
    param(
        [string]$MysqlBin,
        [string]$DataDir,
        [string]$Port = '3306',
        [string]$Password = '',
        [string]$AppPath = ''
    )

    $installDb = Get-MariadbInstallDbExecutable -MysqlBin $MysqlBin
    if (-not $installDb) {
        throw @"
mariadb-install-db.exe nao encontrado em tools\mysql\bin.
O MariaDB embutido esta incompleto. Apague a pasta tools\mysql e reinstale.
Confira se installer\assets\mariadb-win.zip e o ZIP oficial winx64:
  $($script:UnitecMariaDbDownloadUrl)
"@
    }

    if ([string]::IsNullOrWhiteSpace($Password)) {
        $Password = Get-UnitecDefaultDbPassword
    }

    Ensure-Directory $DataDir

    $logFile = Join-Path $env:TEMP ("unitec-mariadb-install-{0}.log" -f [Guid]::NewGuid().ToString('N'))
    $args = @(
        "--datadir=$DataDir",
        "--port=$Port",
        "--password=$Password",
        '-o'
    )

    $previousErrorAction = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'

    Push-Location $MysqlBin
    try {
        Write-Host "Executando $(Split-Path $installDb -Leaf)..." -ForegroundColor Gray
        & $installDb @args 2>&1 | Tee-Object -FilePath $logFile | Out-Null
        $exitCode = $LASTEXITCODE
        $output = ''
        if (Test-Path $logFile) {
            $output = Get-UnitecTrimmedFileContent -Path $logFile
        }

        if ($exitCode -ne 0 -or -not (Test-UnitecMysqlSystemTablesReady -DataDir $DataDir)) {
            $detail = if ($output) { $output } else { "codigo $exitCode" }
            if (-not [string]::IsNullOrWhiteSpace($AppPath)) {
                Write-InstallLog -AppPath $AppPath -Message ("mariadb-install-db falhou: {0}" -f $detail)
            }
            throw "Falha ao inicializar MariaDB: $detail"
        }

        if (-not [string]::IsNullOrWhiteSpace($AppPath)) {
            Write-InstallLog -AppPath $AppPath -Message 'MariaDB inicializado (mariadb-install-db).'
        }

        Write-Ok 'Dados do MariaDB inicializados.'
    } finally {
        Pop-Location
        $ErrorActionPreference = $previousErrorAction
        Remove-Item $logFile -Force -ErrorAction SilentlyContinue
    }
}

function Get-MysqlExecutable {
    param(
        [string]$LaragonPath = 'C:\laragon',
        [string]$AppPath = ''
    )

    if (-not [string]::IsNullOrWhiteSpace($AppPath)) {
        $mysqlRoot = Get-UnitecMysqlRoot -AppPath $AppPath
        $embedded = Join-Path $mysqlRoot 'bin\mysql.exe'
        if (Test-Path $embedded) {
            return $embedded
        }

        $found = Get-ChildItem -Path $mysqlRoot -Filter mysql.exe -Recurse -ErrorAction SilentlyContinue |
            Sort-Object FullName -Descending |
            Select-Object -First 1

        if ($found) {
            return $found.FullName
        }
    }

    $laragonRoot = Join-Path $LaragonPath 'bin\mysql'
    if (Test-Path $laragonRoot) {
        $found = Get-ChildItem -Path $laragonRoot -Filter mysql.exe -Recurse -ErrorAction SilentlyContinue |
            Sort-Object FullName -Descending |
            Select-Object -First 1

        if ($found) {
            return $found.FullName
        }
    }

    $cmd = Get-Command mysql -ErrorAction SilentlyContinue
    if ($cmd) {
        return $cmd.Source
    }

    return $null
}

function Format-MySqlCnfValue {
    param([string]$Value)

    if ($null -eq $Value) {
        return '""'
    }

    if ($Value -match '[#\s"\\=@]') {
        $escaped = $Value -replace '\\', '\\\\' -replace '"', '\"'
        return "`"$escaped`""
    }

    return $Value
}

function New-UnitecMysqlDefaultsFile {
    param(
        [string]$User = 'root',
        [string]$Password = '',
        [string]$MysqlHost = '127.0.0.1',
        [string]$Port = '3306'
    )

    $path = Join-Path $env:TEMP ("unitec-mysql-{0}.cnf" -f [Guid]::NewGuid().ToString('N'))
    $lines = @(
        '[client]',
        "user=$User",
        "host=$MysqlHost",
        "port=$Port"
    )

    if ($Password -ne '') {
        $lines += "password=$(Format-MySqlCnfValue -Value $Password)"
    }

    Set-Content -Path $path -Value ($lines -join [Environment]::NewLine) -Encoding ASCII
    return $path
}

function Remove-BenignMysqlClientOutput {
    param([string]$Text)

    if ([string]::IsNullOrWhiteSpace($Text)) {
        return ''
    }

    $filtered = ($Text -split "\r?\n" | Where-Object {
        $_ -and ($_ -notmatch 'Using a password on the command line interface can be insecure')
    }) -join [Environment]::NewLine

    return $filtered.Trim()
}

function Invoke-MysqlClient {
    param(
        [string]$LaragonPath = 'C:\laragon',
        [string]$AppPath = '',
        [string[]]$Arguments,
        [string]$ClientUser = '',
        [string]$ClientPassword = '',
        [string]$ClientHost = '',
        [string]$ClientPort = '',
        [string]$ClientDatabase = ''
    )

    $mysqlExe = Get-MysqlExecutable -LaragonPath $LaragonPath -AppPath $AppPath
    if (-not $mysqlExe) {
        return @{
            Ok       = $false
            ExitCode = -1
            Error    = 'Comando mysql nao encontrado em tools\mysql.'
        }
    }

    $defaultsFile = $null
    $args = @()

    if ($ClientUser -or $ClientPassword -or $ClientHost -or $ClientPort) {
        $defaultsFile = New-UnitecMysqlDefaultsFile -User $(if ($ClientUser) { $ClientUser } else { 'root' }) -Password $ClientPassword -MysqlHost $(if ($ClientHost) { $ClientHost } else { '127.0.0.1' }) -Port $(if ($ClientPort) { $ClientPort } else { '3306' })
        $args += "--defaults-extra-file=$defaultsFile"
    }

    if (-not [string]::IsNullOrWhiteSpace($ClientDatabase)) {
        $args += $ClientDatabase
    }

    if ($Arguments) {
        $args += $Arguments
    }

    $stderrFile = Join-Path $env:TEMP ("unitec-mysql-err-{0}.txt" -f [Guid]::NewGuid().ToString('N'))
    $previousErrorAction = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'

    try {
        & $mysqlExe @args 2> $stderrFile | Out-Null
        $exitCode = $LASTEXITCODE
        $stderr = ''
        if (Test-Path $stderrFile) {
            $stderr = Remove-BenignMysqlClientOutput -Text (Get-Content $stderrFile -Raw -ErrorAction SilentlyContinue)
        }

        return @{
            Ok       = ($exitCode -eq 0)
            ExitCode = $exitCode
            Error    = $stderr
        }
    } finally {
        $ErrorActionPreference = $previousErrorAction
        Remove-Item $stderrFile -Force -ErrorAction SilentlyContinue
        if ($defaultsFile) {
            Remove-Item $defaultsFile -Force -ErrorAction SilentlyContinue
        }
    }
}

function Stop-LaragonMysqlProcess {
    param([string]$LaragonPath = 'C:\laragon')

    Get-Process mysqld -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue
    Start-Sleep -Seconds 2
}

function Initialize-LaragonMysqlDataIfNeeded {
    param(
        [string]$LaragonPath = 'C:\laragon',
        [string]$AppPath = '',
        [System.IO.FileInfo]$Mysqld,
        [string]$IniPath
    )

    $mysqlBin = Split-Path $Mysqld.FullName
    $mysqlHome = Split-Path $mysqlBin
    $dataDir = Get-MysqlDataDirectoryFromIni -IniPath $IniPath -MysqlHome $mysqlHome

    if ([string]::IsNullOrWhiteSpace($dataDir)) {
        throw 'Nao foi possivel determinar a pasta data do MySQL. Verifique tools\mysql\my.ini.'
    }

    $useEmbeddedMariaDb = -not [string]::IsNullOrWhiteSpace($AppPath)
    if ($useEmbeddedMariaDb) {
        Repair-UnitecMysqlDataIfCorrupt -DataDir $dataDir
    }

    if (Test-MysqlDataInitialized -DataDir $dataDir) {
        return
    }

    # Se o ERP ja tem tabelas, nao rodar install-db (evita banco vazio).
    if (Test-UnitecErpDataPresent -DataDir $dataDir) {
        Write-Warn 'MariaDB sem marcadores de sistema, mas dados do ERP existem. Mantendo pasta data.'
        return
    }

    Ensure-Directory $dataDir
    Write-Host 'Inicializando dados do MariaDB (primeira execucao)...' -ForegroundColor White

    if ($useEmbeddedMariaDb) {
        if (-not [string]::IsNullOrWhiteSpace($AppPath)) {
            Write-InstallLog -AppPath $AppPath -Message 'Inicializando MariaDB (mariadb-install-db)...'
        }
        Invoke-UnitecMariaDbInstallDb -MysqlBin $mysqlBin -DataDir $dataDir -AppPath $AppPath
        return
    }

    $args = @()
    if ($IniPath) {
        $args += "--defaults-file=$IniPath"
    }
    $args += '--initialize-insecure'

    Push-Location $mysqlBin
    try {
        & $Mysqld.FullName @args 2>&1 | Out-Null
        if ($LASTEXITCODE -ne 0 -or -not (Test-MysqlDataInitialized -DataDir $dataDir)) {
            throw 'Nao foi possivel inicializar automaticamente a pasta data do MySQL (Laragon legado).'
        }

        Write-Ok 'Dados do MySQL inicializados.'
    } finally {
        Pop-Location
    }
}

function Test-MysqlClientAuth {
    param(
        [string]$LaragonPath = 'C:\laragon',
        [string]$AppPath = '',
        [string]$User = $script:UnitecDefaultDbUser,
        [string]$Password = '',
        [string[]]$Hosts = @('127.0.0.1', 'localhost')
    )

    foreach ($hostName in $Hosts) {
        $result = Invoke-MysqlClient -LaragonPath $LaragonPath -AppPath $AppPath -Arguments @('-e', 'SELECT 1') -ClientUser $User -ClientPassword $Password -ClientHost $hostName -ClientPort '3306'
        if ($result.Ok) {
            return $true
        }
    }

    return $false
}

function Test-MysqlDatabaseAccess {
    param(
        [string]$LaragonPath = 'C:\laragon',
        [string]$AppPath = '',
        [string]$User = $script:UnitecDefaultDbUser,
        [string]$Password = '',
        [string]$Database = $script:UnitecDefaultDbName,
        [string]$MysqlHost = '127.0.0.1',
        [string]$Port = '3306'
    )

    if ([string]::IsNullOrWhiteSpace($Database)) {
        return $false
    }

    $mysqlExe = Get-MysqlExecutable -LaragonPath $LaragonPath -AppPath $AppPath
    if ($mysqlExe) {
        $result = Invoke-MysqlClient -LaragonPath $LaragonPath -AppPath $AppPath -Arguments @('-e', 'SELECT 1') -ClientUser $User -ClientPassword $Password -ClientHost $MysqlHost -ClientPort $Port -ClientDatabase $Database
        if ($result.Ok) {
            return $true
        }
    }

    return (Test-MysqlDatabaseAccessViaPhp -AppPath $AppPath -User $User -Password $Password -Database $Database -MysqlHost $MysqlHost -Port $Port).Ok
}

function Get-LaragonMysqlRootAccountSql {
    param(
        [string]$User = $script:UnitecDefaultDbUser,
        [string]$Password = $script:UnitecDefaultDbPassword
    )

    $escaped = Escape-MySqlStringLiteral -Value $Password

    return (@(
        "CREATE USER IF NOT EXISTS '$User'@'localhost' IDENTIFIED BY '$escaped';",
        "ALTER USER '$User'@'localhost' IDENTIFIED BY '$escaped';",
        "CREATE USER IF NOT EXISTS '$User'@'127.0.0.1' IDENTIFIED BY '$escaped';",
        "ALTER USER '$User'@'127.0.0.1' IDENTIFIED BY '$escaped';",
        "CREATE USER IF NOT EXISTS '$User'@'%' IDENTIFIED BY '$escaped';",
        "ALTER USER '$User'@'%' IDENTIFIED BY '$escaped';",
        "GRANT ALL PRIVILEGES ON *.* TO '$User'@'localhost' WITH GRANT OPTION;",
        "GRANT ALL PRIVILEGES ON *.* TO '$User'@'127.0.0.1' WITH GRANT OPTION;",
        "GRANT ALL PRIVILEGES ON *.* TO '$User'@'%' WITH GRANT OPTION;",
        'FLUSH PRIVILEGES;'
    ) -join ' ')
}

function Grant-UnitecMysqlDatabasePrivileges {
    param(
        [string]$Database,
        [string]$User = $script:UnitecDefaultDbUser,
        [string]$Password = $script:UnitecDefaultDbPassword,
        [string]$LaragonPath = 'C:\laragon',
        [string]$AppPath = '',
        [string]$Port = '3306',
        [switch]$ThrowOnFailure
    )

    if ([string]::IsNullOrWhiteSpace($Database)) {
        return $false
    }

    if ([string]::IsNullOrWhiteSpace($Password)) {
        $Password = Get-UnitecDefaultDbPassword
    }

    $escapedDb = $Database -replace '`', '``'
    $sql = (@(
        "GRANT ALL PRIVILEGES ON ``$escapedDb``.* TO '$User'@'localhost';",
        "GRANT ALL PRIVILEGES ON ``$escapedDb``.* TO '$User'@'127.0.0.1';",
        "GRANT ALL PRIVILEGES ON ``$escapedDb``.* TO '$User'@'%';",
        'FLUSH PRIVILEGES;'
    ) -join ' ')

    $adminHost = $null
    $adminPassword = $null

    foreach ($candidatePassword in @($Password, '')) {
        foreach ($hostName in @('localhost', '127.0.0.1')) {
            if (Test-MysqlClientAuth -LaragonPath $LaragonPath -AppPath $AppPath -User $User -Password $candidatePassword -Hosts @($hostName)) {
                $adminHost = $hostName
                $adminPassword = $candidatePassword
                break
            }
        }

        if ($adminHost) {
            break
        }
    }

    if (-not $adminHost) {
        $message = "Nao foi possivel conceder permissoes no banco '$Database' (sem autenticacao admin)."
        if ($ThrowOnFailure) {
            throw $message
        }

        Write-Warn $message
        return $false
    }

    $result = Invoke-MysqlClient -LaragonPath $LaragonPath -AppPath $AppPath -Arguments @('-e', $sql) -ClientUser $User -ClientPassword $adminPassword -ClientHost $adminHost -ClientPort $Port
    if (-not $result.Ok) {
        $detail = if ([string]::IsNullOrWhiteSpace($result.Error)) {
            "codigo $($result.ExitCode)"
        } else {
            $result.Error
        }

        $message = "Nao foi possivel conceder permissoes no banco '$Database': $detail"
        if ($ThrowOnFailure) {
            throw $message
        }

        Write-Warn $message
        return $false
    }

    if (-not (Test-MysqlDatabaseAccess -LaragonPath $LaragonPath -AppPath $AppPath -User $User -Password $Password -Database $Database -MysqlHost '127.0.0.1' -Port $Port)) {
        $message = "Permissoes aplicadas, mas o usuario '$User'@'127.0.0.1' ainda nao acessa '$Database'."
        if ($ThrowOnFailure) {
            throw $message
        }

        Write-Warn $message
        return $false
    }

    Write-Ok "Permissoes do banco '$Database' configuradas (localhost + rede)."
    return $true
}

function Test-MysqlRunning {
    param(
        [string]$LaragonPath = 'C:\laragon',
        [string]$AppPath = '',
        [string]$User = $script:UnitecDefaultDbUser,
        [string[]]$PasswordCandidates = @()
    )

    if (-not (Test-UnitecTcpPortOpen -Port 3306)) {
        return $false
    }

    if ($PasswordCandidates.Count -eq 0) {
        $PasswordCandidates = @($script:UnitecDefaultDbPassword, '')
    }

    foreach ($password in ($PasswordCandidates | Select-Object -Unique)) {
        if (Test-MysqlClientAuth -LaragonPath $LaragonPath -AppPath $AppPath -User $User -Password $password) {
            return $true
        }
    }

    return $false
}

function Start-LaragonMysql {
    param(
        [string]$LaragonPath = 'C:\laragon',
        [string]$AppPath = ''
    )

    if (Test-MysqlRunning -LaragonPath $LaragonPath -AppPath $AppPath) {
        return $true
    }

    $mysqld = Get-LaragonMysqldExecutable -LaragonPath $LaragonPath -AppPath $AppPath
    if (-not $mysqld) {
        return $false
    }

    $mysqlBin = Split-Path $mysqld.FullName
    $mysqlHome = Split-Path $mysqlBin
    $iniPath = Get-LaragonMysqlIniPath -LaragonPath $LaragonPath -MysqlHome $mysqlHome -AppPath $AppPath

    if (-not $iniPath -and -not [string]::IsNullOrWhiteSpace($AppPath)) {
        $iniPath = New-UnitecMysqlIniFile -AppPath $AppPath -MysqlRoot $mysqlHome
    }

    Initialize-LaragonMysqlDataIfNeeded -LaragonPath $LaragonPath -AppPath $AppPath -Mysqld $mysqld -IniPath $iniPath

    $args = @()
    if ($iniPath) {
        $args += "--defaults-file=$iniPath"
    }

    $logFile = Join-Path $env:TEMP 'unitec-mysqld.log'
    $args += "--log-error=$logFile"

    Start-UnitecHiddenProcess -FilePath $mysqld.FullName -ArgumentList $args -WorkingDirectory $mysqlBin
    return $true
}

function Invoke-LaragonMysqlStartFallback {
    param([string]$LaragonPath = 'C:\laragon')

    $laragonExe = Join-Path $LaragonPath 'laragon.exe'
    if (-not (Test-Path $laragonExe)) {
        return $false
    }

    foreach ($argSet in @(@('start'), @('start', 'all'))) {
        try {
            Start-UnitecHiddenProcess -FilePath $laragonExe -ArgumentList $argSet
            Start-Sleep -Seconds 8
            if (Test-MysqlRunning -LaragonPath $LaragonPath) {
                return $true
            }
        } catch {
            continue
        }
    }

    try {
        Start-UnitecHiddenProcess -FilePath $laragonExe
        Start-Sleep -Seconds 8
        return (Test-MysqlRunning -LaragonPath $LaragonPath)
    } catch {
        return $false
    }
}

function Ensure-LaragonMysqlRunning {
    param(
        [string]$LaragonPath = 'C:\laragon',
        [string]$AppPath = '',
        [int]$MaxWaitSeconds = 60,
        [switch]$ThrowOnFailure
    )

    if (Test-MysqlRunning -LaragonPath $LaragonPath -AppPath $AppPath) {
        Write-Ok 'MySQL respondendo.'
        return $true
    }

    Write-Host 'Iniciando MySQL...' -ForegroundColor White

    for ($round = 1; $round -le 3; $round++) {
        if ($round -gt 1) {
            Write-Host "Reiniciando MySQL (tentativa $round/3)..." -ForegroundColor Yellow
            Stop-LaragonMysqlProcess -LaragonPath $LaragonPath
            if (-not [string]::IsNullOrWhiteSpace($AppPath)) {
                $dataDir = Join-Path (Get-UnitecMysqlRoot -AppPath $AppPath) 'data'
                Repair-UnitecMysqlDataIfCorrupt -DataDir $dataDir
            }
        }

        if (-not (Start-LaragonMysql -LaragonPath $LaragonPath -AppPath $AppPath)) {
            $mysqlHint = if ($AppPath) { 'tools\mysql' } else { 'C:\laragon\bin\mysql' }
            Write-Warn "MySQL (mysqld.exe) nao encontrado em $mysqlHint."
            break
        }

        $deadline = (Get-Date).AddSeconds($MaxWaitSeconds)
        while ((Get-Date) -lt $deadline) {
            if (Test-MysqlRunning -LaragonPath $LaragonPath -AppPath $AppPath) {
                Write-Ok 'MySQL respondendo.'
                return $true
            }
            Start-Sleep -Seconds 2
        }
    }

    if (-not [string]::IsNullOrWhiteSpace($AppPath)) {
        $errHint = ''
        try {
            $dataDir = Join-Path (Get-UnitecMysqlRoot -AppPath $AppPath) 'data'
            $errFile = Get-ChildItem $dataDir -Filter '*.err' -ErrorAction SilentlyContinue |
                Sort-Object LastWriteTime -Descending |
                Select-Object -First 1
            if ($errFile) {
                $lastLine = (Get-Content $errFile.FullName -Tail 5 -ErrorAction SilentlyContinue) -join ' '
                if (-not [string]::IsNullOrWhiteSpace($lastLine)) {
                    $errHint = "`nDetalhe: $lastLine"
                }
            }
        } catch {
            # ignorar
        }

        $message = @"
MySQL nao iniciou em localhost:3306.

Consulte C:\UNITECNOLOGIA_WEB\instalacao.log e o arquivo tools\mysql\data\*.err.$errHint
Se persistir, apague tools\mysql\data e reinstale o Unitec ERP.
"@
    } else {
        if (Invoke-LaragonMysqlStartFallback -LaragonPath $LaragonPath) {
            Write-Ok 'MySQL respondendo (via Laragon legado).'
            return $true
        }

        $message = @"
MySQL nao iniciou em localhost:3306.

Consulte C:\UNITECNOLOGIA_WEB\instalacao.log e reinstale o Unitec ERP.
"@
    }

    if ($ThrowOnFailure) {
        throw $message
    }

    Write-Warn $message
    return $false
}

function Escape-MySqlStringLiteral {
    param([string]$Value)

    if ($null -eq $Value) {
        return ''
    }

    return ($Value -replace '\\', '\\\\' -replace "'", "''")
}

function Ensure-LaragonMysqlRootPassword {
    param(
        [string]$LaragonPath = 'C:\laragon',
        [string]$AppPath = '',
        [string]$User = $script:UnitecDefaultDbUser,
        [string]$Password = $script:UnitecDefaultDbPassword,
        [switch]$ThrowOnFailure
    )

    if ([string]::IsNullOrWhiteSpace($Password)) {
        return $true
    }

    $adminHost = $null
    $adminPassword = $null

    foreach ($candidatePassword in @($Password, '')) {
        foreach ($hostName in @('localhost', '127.0.0.1')) {
            if (Test-MysqlClientAuth -LaragonPath $LaragonPath -AppPath $AppPath -User $User -Password $candidatePassword -Hosts @($hostName)) {
                $adminHost = $hostName
                $adminPassword = $candidatePassword
                break
            }
        }

        if ($adminHost) {
            break
        }
    }

    if (-not $adminHost) {
        $message = 'Nao foi possivel autenticar no MySQL para configurar o usuario root.'
        if ($ThrowOnFailure) {
            throw $message
        }

        Write-Warn $message
        return $false
    }

    if ($adminPassword -ne $Password) {
        Write-Host 'Definindo senha padrao do MySQL (root)...' -ForegroundColor White
    } else {
        Write-Host 'Sincronizando contas root do MySQL (localhost + 127.0.0.1)...' -ForegroundColor White
    }

    $sql = Get-LaragonMysqlRootAccountSql -User $User -Password $Password
    $setResult = Invoke-MysqlClient -LaragonPath $LaragonPath -AppPath $AppPath -Arguments @('-e', $sql) -ClientUser $User -ClientPassword $adminPassword -ClientHost $adminHost -ClientPort '3306'
    if (-not $setResult.Ok) {
        $setResult = Invoke-MysqlClient -LaragonPath $LaragonPath -AppPath $AppPath -Arguments @('-e', $sql) -ClientUser $User -ClientHost $adminHost -ClientPort '3306'
    }

    if (-not $setResult.Ok) {
        $message = "Nao foi possivel configurar o usuario root do MySQL: $($setResult.Error)"
        if ($ThrowOnFailure) {
            throw $message
        }

        Write-Warn $message
        return $false
    }

    Start-Sleep -Seconds 1

    if (-not (Test-MysqlClientAuth -LaragonPath $LaragonPath -AppPath $AppPath -User $User -Password $Password -Hosts @('127.0.0.1', 'localhost'))) {
        $message = 'Conta root configurada, mas a autenticacao em 127.0.0.1 falhou.'
        if ($ThrowOnFailure) {
            throw $message
        }

        Write-Warn $message
        return $false
    }

    Write-Ok 'Contas root do MySQL configuradas (localhost + 127.0.0.1).'
    return $true
}

function Get-UnitecBundledSeedDir {
    param([string]$AppPath)

    return Join-Path (Resolve-UnitecAppPath -Path $AppPath) 'installer\seed'
}

function Test-UnitecBundledSeedPresent {
    param([string]$AppPath)

    $seedDir = Get-UnitecBundledSeedDir -AppPath $AppPath
    $flag = Join-Path $seedDir 'INCLUDE_DEV_DATA.flag'
    $sql = Join-Path $seedDir 'unitec_erp.sql'

    return (Test-Path $flag) -and (Test-Path $sql) -and ((Get-Item $sql).Length -gt 1024)
}

function Get-UnitecBundledSeedSqlPath {
    param([string]$AppPath)

    return Join-Path (Get-UnitecBundledSeedDir -AppPath $AppPath) 'unitec_erp.sql'
}

function Resolve-UnitecMysqlDumpExe {
    param(
        [string]$AppPath = '',
        [string]$PreferredBin = ''
    )

    $candidates = @()

    if (-not [string]::IsNullOrWhiteSpace($PreferredBin) -and (Test-Path $PreferredBin)) {
        $candidates += $PreferredBin
    }

    if (-not [string]::IsNullOrWhiteSpace($AppPath)) {
        $bin = Join-Path (Get-UnitecMysqlRoot -AppPath $AppPath) 'bin'
        foreach ($name in @('mariadb-dump.exe', 'mysqldump.exe')) {
            $candidates += (Join-Path $bin $name)
        }
    }

    foreach ($extra in @(
        'C:\Projetos\unitec-erp-web\tools\mysql\bin\mysqldump.exe',
        'C:\Projetos\unitec-erp-web\tools\mysql\bin\mariadb-dump.exe',
        'C:\UNITECNOLOGIA_WEB\tools\mysql\bin\mysqldump.exe',
        'C:\UNITECNOLOGIA_WEB\tools\mysql\bin\mariadb-dump.exe'
    )) {
        $candidates += $extra
    }

    foreach ($candidate in $candidates) {
        if (Test-Path $candidate) {
            return $candidate
        }
    }

    return $null
}

function Resolve-UnitecMysqlClientExe {
    param(
        [string]$AppPath = '',
        [string]$PreferredBin = ''
    )

    $candidates = @()

    if (-not [string]::IsNullOrWhiteSpace($PreferredBin) -and (Test-Path $PreferredBin)) {
        $candidates += $PreferredBin
    }

    if (-not [string]::IsNullOrWhiteSpace($AppPath)) {
        $bin = Join-Path (Get-UnitecMysqlRoot -AppPath $AppPath) 'bin'
        foreach ($name in @('mariadb.exe', 'mysql.exe')) {
            $candidates += (Join-Path $bin $name)
        }
    }

    foreach ($extra in @(
        'C:\Projetos\unitec-erp-web\tools\mysql\bin\mysql.exe',
        'C:\Projetos\unitec-erp-web\tools\mysql\bin\mariadb.exe',
        'C:\UNITECNOLOGIA_WEB\tools\mysql\bin\mysql.exe',
        'C:\UNITECNOLOGIA_WEB\tools\mysql\bin\mariadb.exe'
    )) {
        $candidates += $extra
    }

    foreach ($candidate in $candidates) {
        if (Test-Path $candidate) {
            return $candidate
        }
    }

    return $null
}

function Export-UnitecDatabaseDump {
    param(
        [string]$OutputPath,
        [string]$DbHost = '127.0.0.1',
        [string]$DbPort = '3306',
        [string]$DbUser = $script:UnitecDefaultDbUser,
        [string]$DbPassword = '',
        [string]$DbName = $script:UnitecDefaultDbName,
        [string]$AppPath = '',
        [string]$DumpExe = ''
    )

    if ([string]::IsNullOrWhiteSpace($DbPassword)) {
        $DbPassword = Get-UnitecDefaultDbPassword
    }

    if ([string]::IsNullOrWhiteSpace($DumpExe)) {
        $DumpExe = Resolve-UnitecMysqlDumpExe -AppPath $AppPath
    }

    if (-not $DumpExe -or -not (Test-Path $DumpExe)) {
        throw 'mysqldump/mariadb-dump nao encontrado para gerar o dump do banco.'
    }

    $parent = Split-Path $OutputPath -Parent
    Ensure-Directory $parent

    if (Test-Path $OutputPath) {
        Remove-Item $OutputPath -Force
    }

    if ($DbHost -eq 'localhost' -or $DbHost -eq '::1' -or [string]::IsNullOrWhiteSpace($DbHost)) {
        $DbHost = '127.0.0.1'
    }

    $args = @(
        ("--host={0}" -f $DbHost),
        ("--port={0}" -f $DbPort),
        ("--user={0}" -f $DbUser),
        ("--password={0}" -f $DbPassword),
        '--protocol=TCP',
        '--single-transaction',
        '--routines',
        '--triggers',
        '--events',
        '--hex-blob',
        '--default-character-set=utf8mb4',
        '--databases',
        $DbName,
        ("--result-file={0}" -f $OutputPath)
    )

    & $DumpExe @args
    if ($LASTEXITCODE -ne 0 -or -not (Test-Path $OutputPath) -or ((Get-Item $OutputPath).Length -lt 1024)) {
        throw ("Falha ao gerar dump do banco {0} (exit={1})." -f $DbName, $LASTEXITCODE)
    }

    return $OutputPath
}

function Import-UnitecBundledSeedDatabase {
    param(
        [string]$AppPath,
        [string]$DbHost = '127.0.0.1',
        [string]$DbPort = '3306',
        [string]$DbUser = $script:UnitecDefaultDbUser,
        [string]$DbPassword = '',
        [string]$DbName = $script:UnitecDefaultDbName
    )

    $AppPath = Resolve-UnitecAppPath -Path $AppPath

    if (-not (Test-UnitecBundledSeedPresent -AppPath $AppPath)) {
        throw 'Pacote de seed ausente (installer\seed\unitec_erp.sql + INCLUDE_DEV_DATA.flag).'
    }

    if ([string]::IsNullOrWhiteSpace($DbPassword)) {
        $DbPassword = Get-UnitecDefaultDbPassword
    }

    $sqlPath = Get-UnitecBundledSeedSqlPath -AppPath $AppPath
    $mysqlExe = Resolve-UnitecMysqlClientExe -AppPath $AppPath

    if (-not $mysqlExe) {
        throw 'mysql/mariadb client nao encontrado para restaurar o seed.'
    }

    if ($DbHost -eq 'localhost' -or $DbHost -eq '::1' -or [string]::IsNullOrWhiteSpace($DbHost)) {
        $DbHost = '127.0.0.1'
    }

    Write-Host (">> Restaurando banco embutido ({0})..." -f ([math]::Round((Get-Item $sqlPath).Length / 1MB, 1))) -ForegroundColor White

    $dropArgs = @(
        ("--host={0}" -f $DbHost),
        ("--port={0}" -f $DbPort),
        ("--user={0}" -f $DbUser),
        ("--password={0}" -f $DbPassword),
        '--protocol=TCP',
        '--default-character-set=utf8mb4',
        '-e',
        ("DROP DATABASE IF EXISTS `{0}`; CREATE DATABASE `{0}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" -f $DbName)
    )

    & $mysqlExe @dropArgs
    if ($LASTEXITCODE -ne 0) {
        throw ("Falha ao recriar o banco {0} antes do seed." -f $DbName)
    }

    $importArgs = @(
        ("--host={0}" -f $DbHost),
        ("--port={0}" -f $DbPort),
        ("--user={0}" -f $DbUser),
        ("--password={0}" -f $DbPassword),
        '--protocol=TCP',
        '--default-character-set=utf8mb4',
        '--max_allowed_packet=512M'
    )

    $proc = Start-Process -FilePath $mysqlExe `
        -ArgumentList $importArgs `
        -RedirectStandardInput $sqlPath `
        -NoNewWindow `
        -Wait `
        -PassThru

    if ($proc.ExitCode -ne 0) {
        throw ("Falha ao importar seed SQL ({0}) exit={1}." -f $sqlPath, $proc.ExitCode)
    }

    Write-Ok 'Banco embutido (seed de desenvolvimento) restaurado.'
}

function Ensure-UnitecDatabaseSetup {
    param(
        [string]$LaragonPath = 'C:\laragon',
        [string]$AppPath = '',
        [string]$MysqlHost = '127.0.0.1',
        [string]$Port = '3306',
        [string]$User = $script:UnitecDefaultDbUser,
        [string]$Password = $script:UnitecDefaultDbPassword,
        [string]$Database = $script:UnitecDefaultDbName,
        [switch]$ThrowOnFailure
    )

    if ([string]::IsNullOrWhiteSpace($Password)) {
        $Password = Get-UnitecDefaultDbPassword
    }

    if (Test-UnitecRemoteDatabaseHost -HostName $MysqlHost) {
        if (-not (Test-MysqlDatabaseAccess -LaragonPath $LaragonPath -AppPath $AppPath -User $User -Password $Password -Database $Database -MysqlHost $MysqlHost -Port $Port)) {
            $message = "Nao foi possivel conectar ao banco remoto em ${MysqlHost}:${Port}."
            $detail = (Test-MysqlDatabaseAccessViaPhp -AppPath $AppPath -User $User -Password $Password -Database $Database -MysqlHost $MysqlHost -Port $Port).Error
            if (-not [string]::IsNullOrWhiteSpace($detail)) {
                $message += " $detail"
            } else {
                $message += ' Verifique IP, usuario, senha e se o MariaDB do servidor esta ativo.'
            }

            if ($ThrowOnFailure) {
                throw $message
            }

            Write-Warn $message
            return
        }

        Write-Ok ("Conexao com banco remoto em {0} OK." -f $MysqlHost)
        return
    }

    $null = Ensure-LaragonMysqlRunning -AppPath $AppPath -LaragonPath $LaragonPath -ThrowOnFailure:$ThrowOnFailure
    $null = Ensure-LaragonMysqlRootPassword -AppPath $AppPath -LaragonPath $LaragonPath -User $User -Password $Password -ThrowOnFailure:$ThrowOnFailure
    $null = Try-CreateMysqlDatabase -AppPath $AppPath -MysqlHost $MysqlHost -Port $Port -User $User -Password $Password -Database $Database -LaragonPath $LaragonPath -ThrowOnFailure:$ThrowOnFailure
    Initialize-UnitecNetworkDatabaseServer -AppPath $AppPath
}

function Get-UnitecEnvValue {
    param(
        [string]$AppPath,
        [string]$Key
    )

    $envFile = Join-Path $AppPath '.env'
    if (-not (Test-Path $envFile)) {
        return ''
    }

    foreach ($line in (Get-Content $envFile -Encoding UTF8 -ErrorAction SilentlyContinue)) {
        if ($line -match ('^\s*{0}\s*=\s*(.*)$' -f [regex]::Escape($Key))) {
            return $matches[1].Trim().Trim('"').Trim("'")
        }
    }

    return ''
}

function Test-UnitecEnvMissingAppKey {
    param([string]$AppPath)

    $value = Get-UnitecEnvValue -AppPath $AppPath -Key 'APP_KEY'
    return [string]::IsNullOrWhiteSpace($value)
}

function Sync-UnitecEnvDatabasePassword {
    param(
        [string]$AppPath,
        [string]$Password
    )

    if ([string]::IsNullOrWhiteSpace($Password)) {
        $Password = Get-UnitecDefaultDbPassword
    }

    $current = Get-UnitecEnvValue -AppPath $AppPath -Key 'DB_PASSWORD'
    if (-not [string]::IsNullOrWhiteSpace($current)) {
        return $false
    }

    $envFile = Join-Path $AppPath '.env'
    if (-not (Test-Path $envFile)) {
        return $false
    }

    $lines = @(Get-Content $envFile -Encoding UTF8)
    $updated = $false
    $found = $false
    $formatted = Format-EnvValue $Password

    for ($i = 0; $i -lt $lines.Count; $i++) {
        if ($lines[$i] -match '^\s*DB_PASSWORD\s*=') {
            $lines[$i] = "DB_PASSWORD=$formatted"
            $found = $true
            $updated = $true
            break
        }
    }

    if (-not $found) {
        $lines += "DB_PASSWORD=$formatted"
        $updated = $true
    }

    if ($updated) {
        Set-UnitecUtf8NoBomFile -Path $envFile -Content ($lines -join [Environment]::NewLine)
        Write-Ok 'Senha MySQL atualizada no arquivo .env.'
    }

    return $updated
}

function Get-UnitecDatabaseSettingsFromEnv {
    param([string]$AppPath)

    $defaults = Get-UnitecDefaultDatabaseSettings

    $envFile = Join-Path $AppPath '.env'
    if (-not (Test-Path $envFile)) {
        return $defaults
    }

    foreach ($line in (Get-Content $envFile -Encoding UTF8 -ErrorAction SilentlyContinue)) {
        if ($line -match '^\s*DB_HOST\s*=\s*(.+)$') {
            $defaults.DbHost = $matches[1].Trim().Trim('"').Trim("'")
        } elseif ($line -match '^\s*DB_PORT\s*=\s*(.+)$') {
            $defaults.DbPort = $matches[1].Trim().Trim('"').Trim("'")
        } elseif ($line -match '^\s*DB_DATABASE\s*=\s*(.+)$') {
            $defaults.DbName = $matches[1].Trim().Trim('"').Trim("'")
        } elseif ($line -match '^\s*DB_USERNAME\s*=\s*(.+)$') {
            $defaults.DbUser = $matches[1].Trim().Trim('"').Trim("'")
        } elseif ($line -match '^\s*DB_PASSWORD\s*=\s*(.*)$') {
            $defaults.DbPassword = $matches[1].Trim().Trim('"').Trim("'")
        }
    }

    if ([string]::IsNullOrWhiteSpace($defaults.DbPassword)) {
        $defaults.DbPassword = Get-UnitecDefaultDbPassword
    }

    return $defaults
}

function Test-UnitecSqlScalarViaPhp {
    param(
        [string]$AppPath = '',
        [string]$User = $script:UnitecDefaultDbUser,
        [string]$Password = '',
        [string]$Database = $script:UnitecDefaultDbName,
        [string]$MysqlHost = '127.0.0.1',
        [string]$Port = '3306',
        [Parameter(Mandatory = $true)]
        [string]$Sql
    )

    if ([string]::IsNullOrWhiteSpace($Database) -or [string]::IsNullOrWhiteSpace($Sql)) {
        return $null
    }

    $AppPath = Resolve-UnitecAppPath -Path $AppPath
    Initialize-UnitecRuntimePath -AppPath $AppPath
    $phpExe = Get-UnitecPhpExecutable -AppPath $AppPath

    if (-not (Test-PhpExtensionEnabled -ExtensionName 'pdo_mysql' -PhpExe $phpExe)) {
        return $null
    }

    $scriptFile = Join-Path $env:TEMP ("unitec-sql-scalar-{0}.php" -f [Guid]::NewGuid().ToString('N'))
    $stderrFile = Join-Path $env:TEMP ("unitec-sql-scalar-err-{0}.txt" -f [Guid]::NewGuid().ToString('N'))
    $phpContent = @'
<?php
declare(strict_types=1);

$host = getenv('UNITEC_DB_HOST') ?: '127.0.0.1';
$port = getenv('UNITEC_DB_PORT') ?: '3306';
$db = getenv('UNITEC_DB_NAME') ?: '';
$user = getenv('UNITEC_DB_USER') ?: 'root';
$pass = getenv('UNITEC_DB_PASSWORD') ?: '';
$sql = getenv('UNITEC_DB_SQL') ?: '';

if ($db === '' || $sql === '') {
    exit(2);
}

try {
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $db);
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 10,
    ]);
    $value = $pdo->query($sql)->fetchColumn();
    if ($value === false) {
        exit(3);
    }
    echo (string) $value;
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage());
    exit(1);
}
'@

    $previousHost = $env:UNITEC_DB_HOST
    $previousPort = $env:UNITEC_DB_PORT
    $previousName = $env:UNITEC_DB_NAME
    $previousUser = $env:UNITEC_DB_USER
    $previousPassword = $env:UNITEC_DB_PASSWORD
    $previousSql = $env:UNITEC_DB_SQL

    try {
        Set-Content -Path $scriptFile -Value $phpContent -Encoding ASCII
        $env:UNITEC_DB_HOST = $MysqlHost
        $env:UNITEC_DB_PORT = $Port
        $env:UNITEC_DB_NAME = $Database
        $env:UNITEC_DB_USER = $User
        $env:UNITEC_DB_PASSWORD = $Password
        $env:UNITEC_DB_SQL = $Sql

        $stdout = & $phpExe $scriptFile 2> $stderrFile
        if ($LASTEXITCODE -ne 0) {
            return $null
        }

        if ($null -eq $stdout) {
            return ''
        }

        return ([string]$stdout).Trim()
    } finally {
        Remove-Item $scriptFile -Force -ErrorAction SilentlyContinue
        Remove-Item $stderrFile -Force -ErrorAction SilentlyContinue

        if ($null -ne $previousHost) { $env:UNITEC_DB_HOST = $previousHost } else { Remove-Item Env:UNITEC_DB_HOST -ErrorAction SilentlyContinue }
        if ($null -ne $previousPort) { $env:UNITEC_DB_PORT = $previousPort } else { Remove-Item Env:UNITEC_DB_PORT -ErrorAction SilentlyContinue }
        if ($null -ne $previousName) { $env:UNITEC_DB_NAME = $previousName } else { Remove-Item Env:UNITEC_DB_NAME -ErrorAction SilentlyContinue }
        if ($null -ne $previousUser) { $env:UNITEC_DB_USER = $previousUser } else { Remove-Item Env:UNITEC_DB_USER -ErrorAction SilentlyContinue }
        if ($null -ne $previousPassword) { $env:UNITEC_DB_PASSWORD = $previousPassword } else { Remove-Item Env:UNITEC_DB_PASSWORD -ErrorAction SilentlyContinue }
        if ($null -ne $previousSql) { $env:UNITEC_DB_SQL = $previousSql } else { Remove-Item Env:UNITEC_DB_SQL -ErrorAction SilentlyContinue }
    }
}

function Get-UnitecSqlScalarFromEnv {
    param(
        [string]$AppPath,
        [string]$LaragonPath = 'C:\laragon',
        [Parameter(Mandatory = $true)]
        [string]$Sql
    )

    $db = Get-UnitecDatabaseSettingsFromEnv -AppPath $AppPath
    if ([string]::IsNullOrWhiteSpace($db.DbName)) {
        return $null
    }

    $hosts = Get-UnitecDatabaseConnectionHostsFromEnv -AppPath $AppPath
    foreach ($hostName in $hosts) {
        $scalar = Test-UnitecSqlScalarViaPhp -AppPath $AppPath -User $db.DbUser -Password $db.DbPassword -Database $db.DbName -MysqlHost $hostName -Port $db.DbPort -Sql $Sql
        if ($null -ne $scalar) {
            return $scalar
        }

        $defaultsFile = $null
        try {
            $defaultsFile = New-UnitecMysqlDefaultsFile -User $db.DbUser -Password $db.DbPassword -MysqlHost $hostName -Port $db.DbPort
            $mysqlExe = Get-MysqlExecutable -LaragonPath $LaragonPath -AppPath $AppPath
            if (-not $mysqlExe) {
                continue
            }

            $output = & $mysqlExe "--defaults-extra-file=$defaultsFile" $db.DbName '-N' '-e' $Sql 2>$null
            if ($LASTEXITCODE -eq 0) {
                if ($null -eq $output -or "$output".Trim() -eq '') {
                    return 0
                }

                return ([string]$output).Trim()
            }
        } catch {
            continue
        } finally {
            if ($defaultsFile) {
                Remove-Item $defaultsFile -Force -ErrorAction SilentlyContinue
            }
        }
    }

    return $null
}

function Test-UnitecDatabaseLooksEmpty {
    param(
        [string]$AppPath,
        [string]$LaragonPath = 'C:\laragon'
    )

    # true  = banco vazio (pode migrate:fresh com seguranca)
    # false = ha dados OU nao foi possivel confirmar (NUNCA apagar)

    if (-not (Test-Path (Join-Path $AppPath '.env'))) {
        return $true
    }

    $prefix = Get-UnitecEnvValue -AppPath $AppPath -Key 'DB_PREFIX'
    if ([string]::IsNullOrWhiteSpace($prefix)) {
        $prefix = 'unitec_'
    }

    $migrationsTable = "${prefix}migrations"
    $usersTable = "${prefix}users"
    $empresasTable = "${prefix}empresas"

    $migrationCount = Get-UnitecSqlScalarFromEnv -AppPath $AppPath -LaragonPath $LaragonPath -Sql "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '$migrationsTable'"
    if ($null -eq $migrationCount) {
        return $false
    }

    if ([int]$migrationCount -gt 0) {
        $rows = Get-UnitecSqlScalarFromEnv -AppPath $AppPath -LaragonPath $LaragonPath -Sql "SELECT COUNT(*) FROM ``$migrationsTable``"
        if ($null -eq $rows) {
            return $false
        }
        if ([int]$rows -gt 0) {
            return $false
        }
    }

    $usersExist = Get-UnitecSqlScalarFromEnv -AppPath $AppPath -LaragonPath $LaragonPath -Sql "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '$usersTable'"
    if ($null -ne $usersExist -and [int]$usersExist -gt 0) {
        $userCount = Get-UnitecSqlScalarFromEnv -AppPath $AppPath -LaragonPath $LaragonPath -Sql "SELECT COUNT(*) FROM ``$usersTable``"
        if ($null -eq $userCount) {
            return $false
        }
        if ([int]$userCount -gt 0) {
            return $false
        }
    }

    $empresasExist = Get-UnitecSqlScalarFromEnv -AppPath $AppPath -LaragonPath $LaragonPath -Sql "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '$empresasTable'"
    if ($null -ne $empresasExist -and [int]$empresasExist -gt 0) {
        $empresaCount = Get-UnitecSqlScalarFromEnv -AppPath $AppPath -LaragonPath $LaragonPath -Sql "SELECT COUNT(*) FROM ``$empresasTable``"
        if ($null -eq $empresaCount) {
            return $false
        }
        if ([int]$empresaCount -gt 0) {
            return $false
        }
    }

    return $true
}

function Test-UnitecNeedsInitialSeed {
    param(
        [string]$AppPath,
        [string]$LaragonPath = 'C:\laragon'
    )

    if (-not (Test-Path (Join-Path $AppPath '.env'))) {
        return $false
    }

    if (-not (Test-Path (Join-Path $AppPath 'vendor\autoload.php'))) {
        return $false
    }

    $db = Get-UnitecDatabaseSettingsFromEnv -AppPath $AppPath
    if ([string]::IsNullOrWhiteSpace($db.DbName)) {
        # Sem nome de banco nao assumir vazio — evita seed/fresh indevido.
        return $false
    }

    $prefix = Get-UnitecEnvValue -AppPath $AppPath -Key 'DB_PREFIX'
    if ([string]::IsNullOrWhiteSpace($prefix)) {
        $prefix = 'unitec_'
    }

    # Seed so se a tabela users existir e estiver VAZIA (qualquer usuario conta).
    # Antes olhava so o nome "USUARIO" — cliente que renomeou o admin era tratado
    # como instalacao incompleta e podia cair em migrate:fresh.
    $table = "${prefix}users"
    $tableExists = Get-UnitecSqlScalarFromEnv -AppPath $AppPath -LaragonPath $LaragonPath -Sql "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '$table'"
    if ($null -eq $tableExists) {
        return $false
    }
    if ([int]$tableExists -eq 0) {
        return $true
    }

    $userCount = Get-UnitecSqlScalarFromEnv -AppPath $AppPath -LaragonPath $LaragonPath -Sql "SELECT COUNT(*) FROM ``$table``"
    if ($null -eq $userCount) {
        return $false
    }

    return ([int]$userCount -eq 0)
}

function Ensure-UnitecDatabaseFromEnv {
    param(
        [string]$AppPath,
        [string]$LaragonPath = 'C:\laragon'
    )

    $db = Get-UnitecDatabaseSettingsFromEnv -AppPath $AppPath
    Ensure-UnitecDatabaseSetup -AppPath $AppPath -LaragonPath $LaragonPath -MysqlHost $db.DbHost -Port $db.DbPort -User $db.DbUser -Password $db.DbPassword -Database $db.DbName -ThrowOnFailure
}

function Try-CreateMysqlDatabase {
    param(
        [string]$MysqlHost,
        [string]$Port,
        [string]$User,
        [string]$Password,
        [string]$Database,
        [string]$LaragonPath = 'C:\laragon',
        [string]$AppPath = '',
        [switch]$ThrowOnFailure
    )

    if ([string]::IsNullOrWhiteSpace($Database)) {
        return
    }

    $null = Ensure-LaragonMysqlRunning -AppPath $AppPath -LaragonPath $LaragonPath

    $sql = "CREATE DATABASE IF NOT EXISTS ``$Database`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    $hosts = @($MysqlHost, '127.0.0.1', 'localhost') | Select-Object -Unique
    $result = @{ Ok = $false; Error = '' }

    foreach ($hostName in $hosts) {
        $result = Invoke-MysqlClient -LaragonPath $LaragonPath -AppPath $AppPath -Arguments @('-e', $sql) -ClientUser $User -ClientPassword $Password -ClientHost $hostName -ClientPort $Port
        if ($result.Ok) {
            break
        }
    }

    if ($result.Ok) {
        $null = Grant-UnitecMysqlDatabasePrivileges -AppPath $AppPath -Database $Database -User $User -Password $Password -LaragonPath $LaragonPath -Port $Port -ThrowOnFailure:$ThrowOnFailure
        Write-Ok "Banco '$Database' verificado/criado no MySQL."
        return
    }

    $detail = if ([string]::IsNullOrWhiteSpace($result.Error)) {
        "codigo $($result.ExitCode)"
    } else {
        $result.Error
    }

    $message = "Nao foi possivel criar/verificar o banco '$Database' no MySQL: $detail"

    if ($ThrowOnFailure) {
        throw $message
    }

    Write-Warn $message
}

function Write-EnvFile($path, $templatePath, $replacements) {
    if ([string]::IsNullOrWhiteSpace($path)) {
        throw 'Caminho do arquivo .env nao informado.'
    }

    if ([string]::IsNullOrWhiteSpace($templatePath)) {
        throw 'Arquivo modelo .env nao informado.'
    }

    if (-not (Test-UnitecPathExists $templatePath)) {
        throw "Arquivo modelo nao encontrado: $templatePath"
    }

    $content = Get-Content -Path $templatePath -Raw -Encoding UTF8
    if ($null -eq $content) {
        $content = ''
    }

    foreach ($key in $replacements.Keys) {
        $content = $content.Replace($key, $replacements[$key])
    }

    Set-UnitecUtf8NoBomFile -Path $path -Content $content
}

function Find-UnitecEnvBackupCandidate {
    param([string]$AppPath)

    $AppPath = Resolve-UnitecAppPath -Path $AppPath
    $candidates = @()

    foreach ($name in @('.env.backup', '.env.production')) {
        $path = Join-Path $AppPath $name
        if (Test-Path $path) {
            $candidates += Get-Item -LiteralPath $path
        }
    }

    $searchRoots = @(
        (Join-Path $AppPath 'storage\app\backups'),
        (Join-Path $AppPath 'storage\app\private')
    )

    foreach ($root in $searchRoots) {
        if (-not (Test-Path $root)) {
            continue
        }

        $candidates += @(Get-ChildItem -Path $root -Recurse -File -ErrorAction SilentlyContinue |
            Where-Object {
                $_.Name -eq '.env' -or
                $_.Name -like '*.env' -or
                $_.Name -like '*preupdate*.env' -or
                $_.Name -like 'unitec_erp_preupdate_*.env'
            })
    }

    return @($candidates |
        Where-Object { $_.Length -gt 50 } |
        Sort-Object LastWriteTime -Descending)
}

<#
.SYNOPSIS
    Garante que o .env exista ao abrir o sistema (atalho/EXE).
.DESCRIPTION
    Se o .env sumiu: restaura o backup mais recente; se nao houver, recria a partir
    de .env.mysql.example com os padroes do instalador e gera APP_KEY.
#>
function Ensure-UnitecEnvFile {
    param(
        [string]$AppPath,
        [string]$AppUrl = ''
    )

    $AppPath = Resolve-UnitecAppPath -Path $AppPath
    $envFile = Join-Path $AppPath '.env'

    if (Test-Path $envFile) {
        return @{ Created = $false; Restored = $false; Path = $envFile }
    }

    if ([string]::IsNullOrWhiteSpace($AppUrl)) {
        $AppUrl = Get-UnitecDefaultAppUrl
    }

    $backups = Find-UnitecEnvBackupCandidate -AppPath $AppPath
    if ($backups.Count -gt 0) {
        $source = $backups[0]
        Copy-Item -LiteralPath $source.FullName -Destination $envFile -Force
        Sync-UnitecEnvAppUrl -AppPath $AppPath -AppUrl $AppUrl | Out-Null
        Write-InstallLog -AppPath $AppPath -Message ("Arquivo .env restaurado de {0}" -f $source.FullName)

        if (Test-UnitecEnvMissingAppKey -AppPath $AppPath) {
            $configCache = Join-Path $AppPath 'bootstrap\cache\config.php'
            if (Test-Path $configCache) {
                Remove-Item $configCache -Force -ErrorAction SilentlyContinue
            }
            Invoke-UnitecArtisan -AppPath $AppPath -Arguments @('key:generate', '--force') -AllowFailure | Out-Null
        }

        return @{ Created = $true; Restored = $true; Path = $envFile; Source = $source.FullName }
    }

    $template = Join-Path $AppPath '.env.mysql.example'
    if (-not (Test-Path $template)) {
        $template = Join-Path $AppPath '.env.example'
    }
    if (-not (Test-Path $template)) {
        throw 'Arquivo .env ausente e nao ha modelo (.env.mysql.example) para recriar. Reinstale o Unitec ERP.'
    }

    $db = Get-UnitecDefaultDatabaseSettings
    Write-EnvFile -path $envFile -templatePath $template -replacements (Get-UnitecFreshInstallEnvReplacements -AppPath $AppPath -AppUrl $AppUrl -DbHost $db.DbHost -DbPort $db.DbPort -DbName $db.DbName -DbUser $db.DbUser -DbPassword $db.DbPassword)

    $configCache = Join-Path $AppPath 'bootstrap\cache\config.php'
    if (Test-Path $configCache) {
        Remove-Item $configCache -Force -ErrorAction SilentlyContinue
    }
    Invoke-UnitecArtisan -AppPath $AppPath -Arguments @('key:generate', '--force') | Out-Null
    Write-InstallLog -AppPath $AppPath -Message 'Arquivo .env recriado com padroes do instalador (APP_KEY nova).'

    return @{ Created = $true; Restored = $false; Path = $envFile; Source = $template }
}

function Test-OfflineBundleReady {
    param([string]$ProjectRoot)

    $vendorOk = Test-Path (Join-Path $ProjectRoot 'vendor\autoload.php')
    $buildPath = Join-Path $ProjectRoot 'public\build'
    $buildOk = (Test-Path $buildPath) -and (
        (Get-ChildItem $buildPath -ErrorAction SilentlyContinue | Measure-Object).Count -gt 0
    )

    return ($vendorOk -and $buildOk)
}

function Get-DefaultAppUrl {
    param([string]$ProjectRoot)

    return Get-UnitecDefaultAppUrl
}

function Get-PhpVersionIdFromFolderName {
    param([string]$Name)

    if ($Name -match 'php-(\d+)\.(\d+)\.(\d+)') {
        return ([int]$matches[1] * 10000) + ([int]$matches[2] * 100) + [int]$matches[3]
    }

    if ($Name -match 'php-(\d+)\.(\d+)') {
        return ([int]$matches[1] * 10000) + ([int]$matches[2] * 100)
    }

    return 0
}

function Get-LaragonPhpFolders {
    param([string]$LaragonPath = 'C:\laragon')

    $phpRoot = Join-Path $LaragonPath 'bin\php'
    if (-not (Test-Path $phpRoot)) {
        return @()
    }

    return Get-ChildItem $phpRoot -Directory -ErrorAction SilentlyContinue |
        Sort-Object { Get-PhpVersionIdFromFolderName $_.Name } -Descending
}

function Find-LaragonPhpFolder {
    param(
        [string]$LaragonPath = 'C:\laragon',
        [int]$MinVersionId = $script:UnitecMinPhpVersionId
    )

    foreach ($folder in (Get-LaragonPhpFolders -LaragonPath $LaragonPath)) {
        if ((Get-PhpVersionIdFromFolderName $folder.Name) -ge $MinVersionId) {
            return $folder.Name
        }
    }

    return $null
}

function Get-LaragonHttpdExecutable {
    param([string]$LaragonPath = 'C:\laragon')

    $candidates = @()

    $apacheRoot = Join-Path $LaragonPath 'bin\apache'
    if (Test-Path $apacheRoot) {
        $candidates += Get-ChildItem $apacheRoot -Filter httpd.exe -Recurse -ErrorAction SilentlyContinue
    }

    if ($candidates.Count -eq 0) {
        $candidates += Get-ChildItem $LaragonPath -Filter httpd.exe -Recurse -ErrorAction SilentlyContinue |
            Where-Object { $_.FullName -match '\\apache\\' }
    }

    return $candidates |
        Sort-Object { $_.FullName.Length } -Descending |
        Select-Object -First 1
}

function Get-LaragonNginxExecutable {
    param([string]$LaragonPath = 'C:\laragon')

    $candidates = @()

    $nginxRoot = Join-Path $LaragonPath 'bin\nginx'
    if (Test-Path $nginxRoot) {
        $candidates += Get-ChildItem $nginxRoot -Filter nginx.exe -Recurse -ErrorAction SilentlyContinue
    }

    if ($candidates.Count -eq 0) {
        $candidates += Get-ChildItem $LaragonPath -Filter nginx.exe -Recurse -ErrorAction SilentlyContinue |
            Where-Object { $_.FullName -match '\\nginx\\' }
    }

    return $candidates |
        Sort-Object { $_.FullName.Length } -Descending |
        Select-Object -First 1
}

function Test-LaragonWebStackInstalled {
    param([string]$LaragonPath = 'C:\laragon')

    return ($null -ne (Get-LaragonHttpdExecutable -LaragonPath $LaragonPath)) -or
        ($null -ne (Get-LaragonNginxExecutable -LaragonPath $LaragonPath))
}

function Install-LaragonFromExe {
    param(
        [string]$InstallerPath,
        [string]$TargetPath
    )

    if (-not (Test-Path $InstallerPath)) {
        throw "Instalador Laragon nao encontrado: $InstallerPath"
    }

    Write-Title 'Instalando Laragon'
    Write-Host "Pacote: $InstallerPath"
    Write-Host "Destino: $TargetPath"
    Write-Host ''

    $installerArgs = @(
        '/SP-',
        '/VERYSILENT',
        '/SUPPRESSMSGBOXES',
        '/NORESTART',
        "/DIR=$TargetPath"
    )

    $proc = Start-Process -FilePath $InstallerPath -ArgumentList $installerArgs -Wait -PassThru

    if ($null -eq $proc) {
        throw 'Instalador Laragon nao retornou processo (Start-Process).'
    }

    if ($proc.ExitCode -ne 0) {
        throw "Instalacao do Laragon falhou (codigo $($proc.ExitCode))."
    }

    if (-not (Test-Path (Join-Path $TargetPath 'laragon.exe'))) {
        throw 'Laragon nao foi encontrado apos a instalacao.'
    }

    Write-Ok 'Laragon instalado.'
}

function Ensure-LaragonWebStackInstalled {
    param(
        [string]$LaragonPath = 'C:\laragon',
        [string]$LaragonInstaller = ''
    )

    if (Test-LaragonWebStackInstalled -LaragonPath $LaragonPath) {
        return
    }

    if ([string]::IsNullOrWhiteSpace($LaragonInstaller) -or -not (Test-Path $LaragonInstaller)) {
        throw @"
Apache/Nginx nao encontrado em $LaragonPath.

Remova ou renomeie a pasta C:\laragon e execute o instalador novamente.
"@
    }

    Write-Warn 'Apache/Nginx ausente no Laragon atual. Instalando componentes WAMP...'
    Install-LaragonFromExe -InstallerPath $LaragonInstaller -TargetPath $LaragonPath

    if (-not (Test-LaragonWebStackInstalled -LaragonPath $LaragonPath)) {
        throw 'Laragon instalado, mas Apache/Nginx ainda nao foi encontrado. Reinstale o Unitec ERP.'
    }

    Write-Ok 'Componentes web do Laragon instalados.'
}

function Set-LaragonIniValue {
    param(
        [string]$IniPath,
        [string]$Section,
        [string]$Key,
        [string]$Value
    )

    if (-not (Test-Path $IniPath)) {
        return
    }

    $lines = @(Get-Content $IniPath -Encoding UTF8 -ErrorAction SilentlyContinue)
    $inSection = $false
    $updated = $false

    for ($i = 0; $i -lt $lines.Count; $i++) {
        if ($lines[$i] -match '^\s*\[(.+)\]\s*$') {
            $inSection = ($matches[1].Trim() -eq $Section)
            continue
        }

        if ($inSection -and ($lines[$i] -match ('^\s*{0}\s*=' -f [regex]::Escape($Key)))) {
            $lines[$i] = "$Key=$Value"
            $updated = $true
            break
        }
    }

    if (-not $updated) {
        return
    }

    Set-Content -Path $IniPath -Value ($lines -join [Environment]::NewLine) -Encoding UTF8
}

function Configure-LaragonForUnitec {
    param([string]$LaragonPath = 'C:\laragon')

    $iniPath = Join-Path $LaragonPath 'usr\laragon.ini'
    if (-not (Test-Path $iniPath)) {
        return
    }

    Set-LaragonIniValue -IniPath $iniPath -Section 'preferences' -Key 'AutoStart' -Value '1'

    if ($null -ne (Get-LaragonHttpdExecutable -LaragonPath $LaragonPath)) {
        Set-LaragonIniValue -IniPath $iniPath -Section 'apache' -Key 'Use' -Value '1'
    }

    if ($null -ne (Get-LaragonNginxExecutable -LaragonPath $LaragonPath)) {
        Set-LaragonIniValue -IniPath $iniPath -Section 'nginx' -Key 'Use' -Value '1'
    }
}

function Test-UnitecWebServerListening {
    param([int]$Port = 80)

    return Test-UnitecTcpPortOpen -Port $Port
}

function Invoke-LaragonStartAll {
    param(
        [string]$LaragonPath = 'C:\laragon',
        [int]$WaitSeconds = 12
    )

    $laragonExe = Join-Path $LaragonPath 'laragon.exe'
    if (-not (Test-Path $laragonExe)) {
        return $false
    }

    Configure-LaragonForUnitec -LaragonPath $LaragonPath

    foreach ($argSet in @(@('start', 'all'), @('start'), @('restart'))) {
        try {
            Start-UnitecHiddenProcess -FilePath $laragonExe -ArgumentList $argSet
            Start-Sleep -Seconds $WaitSeconds
            if (Test-UnitecWebServerListening) {
                return $true
            }
        } catch {
            continue
        }
    }

    try {
        Start-UnitecHiddenProcess -FilePath $laragonExe
        Start-Sleep -Seconds $WaitSeconds
        return (Test-UnitecWebServerListening)
    } catch {
        return $false
    }
}

function Start-UnitecPhpArtisanServer {
    param(
        [string]$AppPath,
        [int]$Port = 80,
        [string]$BindHost = '127.0.0.1',
        [switch]$Foreground
    )

    # Nome legado: HTTP do ERP e sempre FrankenPHP (nunca artisan serve / php -S).
    if (-not (Test-Path (Join-Path $AppPath 'artisan'))) {
        return $false
    }

    if (-not $Foreground) {
        if (Test-UnitecInvalidPhpBuiltinHttpServer -AppPath $AppPath -Port $Port) {
            Stop-UnitecApplicationServer -AppPath $AppPath
        }

        if ((Wait-UnitecApplicationReady -AppUrl "http://127.0.0.1:$Port" -MaxAttempts 1 -DelaySeconds 0 -Quiet) `
                -and (Test-UnitecFrankenPhpRunning -AppPath $AppPath -Port $Port)) {
            return $true
        }
    }

    Initialize-UnitecRuntimePath -AppPath $AppPath
    Ensure-UnitecPhpIniForWindowsDev -AppPath $AppPath | Out-Null

    if (-not $Foreground) {
        Stop-UnitecApplicationServer -AppPath $AppPath
    }

    return (Start-UnitecFrankenPhpServer -AppPath $AppPath -Port $Port -BindHost $BindHost -Foreground:$Foreground -WaitSeconds 25)
}

function Ensure-LaragonWebServerRunning {
    param(
        [string]$LaragonPath = 'C:\laragon',
        [string]$AppPath = '',
        [int]$WaitSeconds = 20,
        [switch]$ThrowOnFailure
    )

    if (Test-UnitecWebServerListening) {
        Write-Ok 'Servidor web respondendo na porta 80.'
        return $true
    }

    Configure-LaragonForUnitec -LaragonPath $LaragonPath

    if (Start-LaragonWebServer -LaragonPath $LaragonPath) {
        Start-Sleep -Seconds 3
    }

    if (-not (Test-UnitecWebServerListening)) {
        Write-Host 'Iniciando servicos web via Laragon...' -ForegroundColor White
        Invoke-LaragonStartAll -LaragonPath $LaragonPath -WaitSeconds $WaitSeconds | Out-Null
    }

    if (-not (Test-UnitecWebServerListening)) {
        Invoke-LaragonReload -LaragonPath $LaragonPath
        Start-Sleep -Seconds 3
        Start-LaragonWebServer -LaragonPath $LaragonPath | Out-Null
        Start-Sleep -Seconds 3
    }

    if (-not (Test-UnitecWebServerListening) -and -not [string]::IsNullOrWhiteSpace($AppPath)) {
        Write-Host 'Laragon porta 80 indisponivel — subindo FrankenPHP do ERP (porta padrao do stack)...' -ForegroundColor Yellow
        Start-UnitecApplicationServer -AppPath $AppPath | Out-Null
    }

    $webProcs = @(Get-Process httpd, nginx -ErrorAction SilentlyContinue)
    if ($webProcs.Count -gt 0) {
        Write-Ok 'Servidor web (Apache/Nginx) em execucao.'
    }

    if (Test-UnitecWebServerListening) {
        Write-Ok 'Servidor web respondendo na porta 80.'
        return $true
    }

    $message = @'
Servidor web nao respondeu na porta 80.

Verifique se outro programa (IIS, Skype) usa a porta 80.
Consulte C:\UNITECNOLOGIA_WEB\instalacao.log
'@

    if ($ThrowOnFailure) {
        throw $message
    }

    Write-Warn $message
    return $false
}

function Get-LaragonHttpdConfigPath {
    param([string]$LaragonPath = 'C:\laragon')

    foreach ($conf in @(
        (Join-Path $LaragonPath 'etc\apache2\httpd.conf'),
        (Join-Path $LaragonPath 'etc\apache2\Apache-2.4\httpd.conf'),
        (Join-Path $LaragonPath 'bin\apache\httpd.conf')
    )) {
        if (Test-Path $conf) {
            return $conf
        }
    }

    $found = Get-ChildItem (Join-Path $LaragonPath 'bin\apache') -Filter httpd.conf -Recurse -ErrorAction SilentlyContinue |
        Sort-Object FullName -Descending |
        Select-Object -First 1

    if ($found) {
        return $found.FullName
    }

    return $null
}

function Get-UnitecCaCertAssetPath {
    param([string]$SourceRoot = '')

    $candidates = @()
    if (-not [string]::IsNullOrWhiteSpace($SourceRoot)) {
        $candidates += Join-Path $SourceRoot "installer\assets\$($script:UnitecCaCertAssetName)"
    }
    $candidates += Join-Path $PSScriptRoot "..\installer\assets\$($script:UnitecCaCertAssetName)"

    foreach ($path in $candidates) {
        if ([string]::IsNullOrWhiteSpace($path)) {
            continue
        }

        if (Test-Path $path) {
            return (Resolve-Path $path).Path
        }
    }

    return $null
}

function Ensure-UnitecCaCertAsset {
    param(
        [string]$SourceRoot = '',
        [switch]$SkipDownload
    )

    $existing = Get-UnitecCaCertAssetPath -SourceRoot $SourceRoot
    if ($existing) {
        return $existing
    }

    $targetDir = if (-not [string]::IsNullOrWhiteSpace($SourceRoot)) {
        Join-Path $SourceRoot 'installer\assets'
    } else {
        Join-Path $PSScriptRoot '..\installer\assets'
    }

    Ensure-Directory $targetDir
    $targetPath = Join-Path $targetDir $script:UnitecCaCertAssetName

    if ($SkipDownload) {
        throw "Coloque $($script:UnitecCaCertAssetName) em installer\assets\ ou remova -SkipRuntimeDownload."
    }

    Write-Host ">> Baixando CA bundle SSL (~220 KB): $($script:UnitecCaCertDownloadUrl)" -ForegroundColor White
    try {
        Invoke-WebRequest -Uri $script:UnitecCaCertDownloadUrl -OutFile $targetPath -UseBasicParsing
    } catch {
        throw "Falha ao baixar cacert.pem. Baixe manualmente de $($script:UnitecCaCertDownloadUrl) e salve em installer\assets\$($script:UnitecCaCertAssetName)"
    }

    if (-not (Test-Path $targetPath) -or ((Get-Item $targetPath).Length -lt 1024)) {
        throw "Arquivo cacert.pem invalido em installer\assets\$($script:UnitecCaCertAssetName)"
    }

    return (Resolve-Path $targetPath).Path
}

function Ensure-UnitecCloudflaredAsset {
    param(
        [string]$SourceRoot = '',
        [switch]$SkipDownload
    )

    $root = if (-not [string]::IsNullOrWhiteSpace($SourceRoot)) {
        $SourceRoot
    } else {
        Join-Path $PSScriptRoot '..'
    }
    $root = [System.IO.Path]::GetFullPath($root)

    $targetDir = Join-Path $root 'resources\cloudflared'
    $targetPath = Join-Path $targetDir 'cloudflared.exe'

    if ((Test-Path $targetPath) -and ((Get-Item $targetPath).Length -gt 1000000)) {
        return (Resolve-Path $targetPath).Path
    }

    if ($SkipDownload) {
        throw "Coloque cloudflared.exe em resources\cloudflared\ ou remova -SkipRuntimeDownload."
    }

    Ensure-Directory $targetDir
    $url = 'https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-windows-amd64.exe'
    $tmp = Join-Path $env:TEMP ('unitec-cloudflared-' + [guid]::NewGuid().ToString('N') + '.exe')

    Write-Host ">> Baixando cloudflared Windows amd64 (~60 MB): $url" -ForegroundColor White
    try {
        Invoke-WebRequest -Uri $url -OutFile $tmp -UseBasicParsing
        Copy-Item -Force $tmp $targetPath
    } catch {
        throw "Falha ao baixar cloudflared.exe. Baixe manualmente de $url e salve em resources\cloudflared\cloudflared.exe"
    } finally {
        Remove-Item -Force $tmp -ErrorAction SilentlyContinue
    }

    if (-not (Test-Path $targetPath) -or ((Get-Item $targetPath).Length -lt 1000000)) {
        throw 'Arquivo cloudflared.exe invalido em resources\cloudflared\'
    }

    return (Resolve-Path $targetPath).Path
}

function Ensure-UnitecPhpSslCaBundle {
    param(
        [string]$PhpDirectory,
        [string]$SourceRoot = '',
        [switch]$SkipDownload
    )

    $targetDir = Join-Path $PhpDirectory 'extras\ssl'
    Ensure-Directory $targetDir
    $targetPath = Join-Path $targetDir $script:UnitecCaCertAssetName

    if (-not ((Test-Path $targetPath) -and ((Get-Item $targetPath).Length -gt 1024))) {
        $sourcePath = Get-UnitecCaCertAssetPath -SourceRoot $SourceRoot
        if (-not $sourcePath) {
            $sourcePath = Ensure-UnitecCaCertAsset -SourceRoot $SourceRoot -SkipDownload:$SkipDownload
        }

        Copy-Item $sourcePath $targetPath -Force
    }

    return (Resolve-Path $targetPath).Path
}

function Set-PhpIniSslCaSettings {
    param(
        [string]$Content,
        [string]$CaPath
    )

    $iniPath = ($CaPath -replace '\\', '/')
    $quotedPath = "`"$iniPath`""

    foreach ($key in @('curl.cainfo', 'openssl.cafile')) {
        $setting = "$key = $quotedPath"
        $enabledPattern = "(?m)^\s*$([regex]::Escape($key))\s*=.*$"
        $commentedPattern = "(?m)^\s*;\s*$([regex]::Escape($key))\s*=.*$"

        if ($Content -match $enabledPattern) {
            $Content = $Content -replace $enabledPattern, $setting
        } elseif ($Content -match $commentedPattern) {
            $Content = $Content -replace $commentedPattern, $setting
        } else {
            $Content += [Environment]::NewLine + $setting + [Environment]::NewLine
        }
    }

    return $Content
}

function Ensure-UnitecPhpIniForWindowsDev {
    param([string]$AppPath)

    $phpDir = Get-UnitecPhpDirectory -AppPath (Resolve-UnitecAppPath -Path $AppPath)
    if (-not $phpDir) {
        return $false
    }

    # OPcache ligado no dev acelera muito o Filament/Laravel; validate_timestamps mantem hot-reload.
    Configure-LaragonPhpIni -PhpDirectory $phpDir -SourceRoot $AppPath

    return $true
}

function Configure-LaragonPhpIni {
    param(
        [string]$PhpDirectory,
        [string]$SourceRoot = '',
        [switch]$DisableOpcache
    )

    $iniPath = Join-Path $PhpDirectory 'php.ini'
    $devIni = Join-Path $PhpDirectory 'php.ini-development'

    if (-not (Test-Path $iniPath) -and (Test-Path $devIni)) {
        Copy-Item $devIni $iniPath -Force
    }

    if (-not (Test-Path $iniPath)) {
        return
    }

    $content = Get-Content $iniPath -Raw -Encoding UTF8
    $extensions = @(
        'curl',
        'fileinfo',
        'gd',
        'intl',
        'mbstring',
        'mysqli',
        'openssl',
        'pdo_mysql',
        'zip'
    )

    foreach ($ext in $extensions) {
        $enabledPattern = "(?m)^\s*extension\s*=\s*$([regex]::Escape($ext))(\s|$)"
        if ($content -match $enabledPattern) {
            continue
        }

        $pattern = "(?m)^;\s*extension\s*=\s*$([regex]::Escape($ext))(\s|$)"
        if ($content -match $pattern) {
            $content = $content -replace $pattern, "extension=$ext"
        } else {
            $content += "`nextension=$ext`n"
        }
    }

    $lines = $content -split "\r?\n"
    $seenExtensions = @{}
    $dedupedLines = foreach ($line in $lines) {
        if ($line -match '^\s*extension\s*=\s*(\w+)') {
            $extName = $matches[1].ToLowerInvariant()
            if ($seenExtensions.ContainsKey($extName)) {
                continue
            }
            $seenExtensions[$extName] = $true
        }
        $line
    }
    $content = ($dedupedLines -join [Environment]::NewLine)

    # Caminho absoluto: extension_dir="ext" relativo falha quando o PHP roda
    # com cwd != tools\php (ex.: artisan migrate / atualizar-sistema).
    $extDir = Join-Path $PhpDirectory 'ext'
    $extDirIni = ($extDir -replace '\\', '/')
    $extDirLine = 'extension_dir="{0}"' -f $extDirIni
    # Remove todas as linhas extension_dir (comentadas ou nao) e grava uma so.
    $content = [regex]::Replace($content, '(?m)^\s*;?\s*extension_dir\s*=.*\r?\n?', '')
    $content = $content.TrimEnd() + [Environment]::NewLine + $extDirLine + [Environment]::NewLine

    $opcacheSettings = if ($DisableOpcache) {
        @(
            'opcache.enable=0',
            'opcache.enable_cli=0',
            'max_execution_time=300',
            'memory_limit=256M'
        )
    } else {
        # OPcache no PHP embutido (CLI artisan + FrankenPHP usam o mesmo php.ini quando sincronizado).
        $opcacheDir = ((Join-Path $AppPath 'tools\php\opcache') -replace '\\', '/')
        Ensure-Directory (Join-Path $AppPath 'tools\php\opcache')

        @(
            'opcache.enable=1',
            'opcache.enable_cli=1',
            'opcache.memory_consumption=256',
            'opcache.interned_strings_buffer=32',
            'opcache.max_accelerated_files=20000',
            # Sempre 1: update aplica sem matar o PHP (evita porta zumbi apos update).
            'opcache.validate_timestamps=1',
            'opcache.revalidate_freq=2',
            ('opcache.file_cache="' + $opcacheDir + '"'),
            'opcache.file_cache_only=0',
            'opcache.file_cache_fallback=1',
            'max_execution_time=300',
            'memory_limit=256M'
        )
    }

    $devTuning = @(
        'realpath_cache_size=4096k',
        'realpath_cache_ttl=600',
        'memory_limit=256M',
        'max_execution_time=300'
    )

    if ($DisableOpcache) {
        $content = $content -replace '(?m)^\s*zend_extension\s*=\s*opcache\s*$', ';zend_extension=opcache'
    } elseif ($content -notmatch '(?m)^\s*zend_extension\s*=\s*opcache') {
        if ($content -match '(?m)^;\s*zend_extension\s*=\s*opcache') {
            $content = $content -replace '(?m)^;\s*zend_extension\s*=\s*opcache', 'zend_extension=opcache'
        } else {
            $content += [Environment]::NewLine + 'zend_extension=opcache' + [Environment]::NewLine
        }
    }

    foreach ($setting in $opcacheSettings) {
        $key = ($setting -split '=', 2)[0]
        if ($content -match ('(?m)^\s*{0}\s*=' -f [regex]::Escape($key))) {
            $content = $content -replace ('(?m)^\s*{0}\s*=.*$' -f [regex]::Escape($key)), $setting
        } else {
            $content += [Environment]::NewLine + $setting
        }
    }

    if (-not $DisableOpcache) {
        foreach ($setting in $devTuning) {
            $key = ($setting -split '=', 2)[0]
            if ($content -match ('(?m)^\s*{0}\s*=' -f [regex]::Escape($key))) {
                $content = $content -replace ('(?m)^\s*{0}\s*=.*$' -f [regex]::Escape($key)), $setting
            } else {
                $content += [Environment]::NewLine + $setting
            }
        }
    }

    try {
        $caPath = Ensure-UnitecPhpSslCaBundle -PhpDirectory $PhpDirectory -SourceRoot $SourceRoot
        $content = Set-PhpIniSslCaSettings -Content $content -CaPath $caPath
    } catch {
        Write-Warn ("Nao foi possivel configurar SSL CA do PHP: {0}" -f $_.Exception.Message)
    }

    # UTF-8 sem BOM (BOM quebra parse do php.ini em alguns PCs Windows).
    $utf8NoBom = New-Object System.Text.UTF8Encoding $false
    [System.IO.File]::WriteAllText($iniPath, $content, $utf8NoBom)
}

function Set-LaragonPhpVersion {
    param(
        [string]$LaragonPath = 'C:\laragon',
        [string]$PhpFolderName
    )

    if ([string]::IsNullOrWhiteSpace($PhpFolderName)) {
        throw 'Versao PHP do Laragon nao configurada.'
    }

    $phpDirectory = Join-Path (Join-Path $LaragonPath 'bin\php') $PhpFolderName
    if (-not (Test-Path $phpDirectory)) {
        throw "Pasta PHP nao encontrada: $phpDirectory"
    }

    Configure-LaragonPhpIni -PhpDirectory $phpDirectory

    $usrDir = Join-Path $LaragonPath 'usr'
    if (-not (Test-Path $usrDir)) {
        New-Item -ItemType Directory -Path $usrDir -Force | Out-Null
    }

    $laragonIni = Join-Path $usrDir 'laragon.ini'
    $phpPathForward = ($phpDirectory -replace '\\', '/')

    if (Test-Path $laragonIni) {
        $lines = Get-Content $laragonIni -Encoding UTF8
        $inPhp = $false
        $updated = $false

        for ($i = 0; $i -lt $lines.Count; $i++) {
            if ($lines[$i] -match '^\[php\]') {
                $inPhp = $true
                continue
            }

            if ($inPhp -and $lines[$i] -match '^\[') {
                if (-not $updated) {
                    $lines = $lines[0..($i - 1)] + "Version=$PhpFolderName" + $lines[$i..($lines.Count - 1)]
                    $updated = $true
                    $inPhp = $false
                }
                continue
            }

            if ($inPhp -and $lines[$i] -match '^Version=') {
                $lines[$i] = "Version=$PhpFolderName"
                $updated = $true
            }
        }

        if (-not $updated) {
            $lines += ''
            $lines += '[php]'
            $lines += "Version=$PhpFolderName"
        }

        Set-Content -Path $laragonIni -Value $lines -Encoding UTF8
    } else {
        @(
            '[preferences]',
            'FirstRun=0',
            '',
            '[php]',
            "Version=$PhpFolderName",
            '',
            '[apache]',
            'Use=-1',
            '',
            '[mysql]',
            'Use=-1'
        ) | Set-Content -Path $laragonIni -Encoding UTF8
    }

    $fcgidConf = Join-Path $LaragonPath 'etc\apache2\fcgid.conf'
    if (Test-Path $fcgidConf) {
        $fcgid = Get-Content $fcgidConf -Raw -Encoding UTF8
        $fcgid = [regex]::Replace(
            $fcgid,
            'C:/laragon/bin/php/[^\r\n;]+',
            $phpPathForward,
            [System.Text.RegularExpressions.RegexOptions]::IgnoreCase
        )
        Set-Content -Path $fcgidConf -Value $fcgid -Encoding UTF8 -NoNewline
    }

    $phpBin = Join-Path $phpDirectory 'bin'
    if (Test-Path (Join-Path $phpDirectory 'php.exe')) {
        if ($env:Path -notlike "*$phpDirectory*") {
            $env:Path = "$phpDirectory;$env:Path"
        }
    } elseif (Test-Path $phpBin) {
        if ($env:Path -notlike "*$phpBin*") {
            $env:Path = "$phpBin;$env:Path"
        }
    }

    Write-Ok "PHP ativo no Laragon: $PhpFolderName"
}

function Install-LaragonPhpFromZip {
    param(
        [string]$LaragonPath = 'C:\laragon',
        [string]$ZipPath,
        [string]$ExpectedFolderName = $script:UnitecPhp84FolderName
    )

    if ([string]::IsNullOrWhiteSpace($ZipPath)) {
        throw 'Pacote PHP 8.4 nao informado.'
    }

    if (-not (Test-UnitecPathExists $ZipPath)) {
        throw "Pacote PHP nao encontrado: $ZipPath"
    }

    $phpRoot = Join-Path $LaragonPath 'bin\php'
    if (-not (Test-Path $phpRoot)) {
        New-Item -ItemType Directory -Path $phpRoot -Force | Out-Null
    }

    $tempDir = New-UnitecInstallTempDir -AppPath $script:UnitecDefaultAppPath -Prefix 'laragon-php'
    try {
        Expand-Archive -LiteralPath $ZipPath -DestinationPath $tempDir -Force

        $target = Join-Path $phpRoot $ExpectedFolderName
        if (Test-Path $target) {
            Remove-Item $target -Recurse -Force
        }
        New-Item -ItemType Directory -Path $target -Force | Out-Null

        if (Test-Path (Join-Path $tempDir 'php.exe')) {
            Get-ChildItem $tempDir -Force | Move-Item -Destination $target -Force
        } else {
            $extracted = Get-ChildItem $tempDir -Directory | Select-Object -First 1
            if (-not $extracted) {
                throw 'Pacote PHP invalido (php.exe nao encontrado).'
            }
            Get-ChildItem $extracted.FullName -Force | Move-Item -Destination $target -Force
        }

        Configure-LaragonPhpIni -PhpDirectory $target
        return $ExpectedFolderName
    } finally {
        Remove-Item $tempDir -Recurse -Force -ErrorAction SilentlyContinue
    }
}

function Resolve-Php84ZipPath {
    param([string]$SourceRoot)

    $candidates = @()
    if (-not [string]::IsNullOrWhiteSpace($SourceRoot)) {
        $candidates += (Join-Path $SourceRoot 'installer\assets\php-8.4-win.zip')
        $candidates += (Join-Path $SourceRoot 'installer\assets\php-8.4.12-Win32-vs17-x64.zip')
    }
    $candidates += (Join-Path $PSScriptRoot '..\installer\assets\php-8.4-win.zip')

    foreach ($path in $candidates) {
        if ([string]::IsNullOrWhiteSpace($path)) {
            continue
        }

        $full = [System.IO.Path]::GetFullPath($path)
        if (Test-UnitecPathExists $full) {
            return $full
        }
    }

    $downloaded = Join-Path $env:TEMP 'unitec-php-8.4-win.zip'
    if (Test-Path $downloaded) {
        return $downloaded
    }

    Write-Host 'Baixando PHP 8.4 (~30 MB)...' -ForegroundColor Yellow
    Invoke-WebRequest -Uri $script:UnitecPhp84DownloadUrl -OutFile $downloaded -UseBasicParsing
    return $downloaded
}

function Get-LaragonInstalledVersion {
    param([string]$LaragonPath = 'C:\laragon')

    $laragonExe = Join-Path $LaragonPath 'laragon.exe'
    if (-not (Test-Path $laragonExe)) {
        return $null
    }

    return (Get-Item $laragonExe).VersionInfo.ProductVersion
}

function Repair-LaragonIfOutdated {
    param(
        [string]$LaragonPath = 'C:\laragon',
        [string]$LaragonInstaller = '',
        [string]$SourceRoot = ''
    )

    if (-not (Test-Path (Join-Path $LaragonPath 'laragon.exe'))) {
        return
    }

    $version = Get-LaragonInstalledVersion -LaragonPath $LaragonPath
    if ([string]::IsNullOrWhiteSpace($version) -or $version -notmatch '^(\d+)') {
        return
    }

    $major = [int]$matches[1]
    if ($major -ge 8) {
        return
    }

    Write-Host 'Encontramos um Laragon antigo no computador.' -ForegroundColor Yellow
    Write-Host 'O instalador vai atualizar automaticamente. Aguarde...' -ForegroundColor White

    if (-not [string]::IsNullOrWhiteSpace($LaragonInstaller) -and (Test-Path $LaragonInstaller)) {
        Install-LaragonFromExe -InstallerPath $LaragonInstaller -TargetPath $LaragonPath
        Write-Ok 'Laragon atualizado.'
        return
    }

    Assert-LaragonInstallCompatible -LaragonPath $LaragonPath -SourceRoot $SourceRoot
}

function Assert-LaragonInstallCompatible {
    param(
        [string]$LaragonPath = 'C:\laragon',
        [string]$SourceRoot = ''
    )

    $version = Get-LaragonInstalledVersion -LaragonPath $LaragonPath
    if ([string]::IsNullOrWhiteSpace($version)) {
        return
    }

    if ($version -notmatch '^(\d+)') {
        return
    }

    $major = [int]$matches[1]
    if ($major -ge 8) {
        return
    }

    $phpFolder = Find-LaragonPhpFolder -LaragonPath $LaragonPath
    $hasPhpZip = $false

    if (-not [string]::IsNullOrWhiteSpace($SourceRoot)) {
        try {
            Resolve-Php84ZipPath -SourceRoot $SourceRoot | Out-Null
            $hasPhpZip = $true
        } catch {
            $hasPhpZip = $false
        }
    }

    if ($phpFolder -and $hasPhpZip) {
        Write-Warn "Laragon $version detectado (recomendado 8.6). PHP 8.4 sera configurado automaticamente."
        return
    }

    throw @"
Encontramos um Laragon antigo ($version) que nao e compativel.

PeÃ§a ao suporte da Unitecnologia ou renomeie a pasta C:\laragon para C:\laragon_antigo e instale novamente.
"@
}

function Ensure-LaragonPhp84 {
    param(
        [string]$LaragonPath = 'C:\laragon',
        [string]$SourceRoot = ''
    )

    if (-not [string]::IsNullOrWhiteSpace($SourceRoot)) {
        try {
            $resolved = Resolve-UnitecAppPath -Path $SourceRoot
            if (Test-Path (Join-Path $resolved 'artisan')) {
                return Ensure-UnitecPhp84 -AppPath $resolved -SourceRoot $SourceRoot
            }
        } catch {
            # SourceRoot nao e pasta da aplicacao; continuar com Laragon legado
        }
    }

    $existing = Find-LaragonPhpFolder -LaragonPath $LaragonPath
    if ($existing) {
        Set-LaragonPhpVersion -LaragonPath $LaragonPath -PhpFolderName $existing
        $phpExe = Join-Path (Join-Path (Join-Path $LaragonPath 'bin\php') $existing) 'php.exe'
        if (Test-Path $phpExe) {
            $allowFix = -not [string]::IsNullOrWhiteSpace($SourceRoot)
            $phpTest = Repair-PhpExecutableRuntime -SourceRoot $SourceRoot -PhpExe $phpExe -AllowFix:$allowFix
            if ($phpTest.Ok) {
                Write-Ok ('PHP {0} ativo no Laragon' -f $phpTest.Version)
            } elseif (-not [string]::IsNullOrWhiteSpace($phpTest.Error)) {
                Write-Warn $phpTest.Error
            }
        }
        return $existing
    }

    Write-Host 'Instalando PHP 8.4 (requerido pelo Unitec ERP)...' -ForegroundColor White

    if ([string]::IsNullOrWhiteSpace($SourceRoot)) {
        $SourceRoot = Split-Path (Split-Path $PSScriptRoot -Parent) -Parent
        if (-not (Test-Path (Join-Path $SourceRoot 'composer.json'))) {
            $SourceRoot = Split-Path $PSScriptRoot -Parent
        }
    }

    $zipPath = Resolve-Php84ZipPath -SourceRoot $SourceRoot
    $folderName = Install-LaragonPhpFromZip -LaragonPath $LaragonPath -ZipPath $zipPath
    Set-LaragonPhpVersion -LaragonPath $LaragonPath -PhpFolderName $folderName

    $phpExe = Join-Path (Join-Path (Join-Path $LaragonPath 'bin\php') $folderName) 'php.exe'
    if (Test-Path $phpExe) {
        $phpTest = Repair-PhpExecutableRuntime -SourceRoot $SourceRoot -PhpExe $phpExe -AllowFix
        if ($phpTest.Ok) {
            Write-Ok ('PHP {0} instalado.' -f $phpTest.Version)
        } else {
            throw ('PHP instalado mas nao executa: {0}' -f $phpTest.Error)
        }
    }

    return $folderName
}

function Stop-LaragonWebServer {
    param([string]$LaragonPath = 'C:\laragon')

    $httpd = Get-LaragonHttpdExecutable -LaragonPath $LaragonPath
    $httpdConf = Get-LaragonHttpdConfigPath -LaragonPath $LaragonPath

    if (-not $httpd) {
        return
    }

    if (-not (Get-Process httpd -ErrorAction SilentlyContinue)) {
        return
    }

    $httpdBin = Split-Path $httpd.FullName
    $previousErrorAction = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'

    try {
        Push-Location $httpdBin
        if ($httpdConf) {
            & $httpd.FullName '-f' $httpdConf '-k' 'stop' 2>$null | Out-Null
        } else {
            & $httpd.FullName '-k' 'stop' 2>$null | Out-Null
        }
        Start-Sleep -Seconds 2
    } finally {
        Pop-Location
        $ErrorActionPreference = $previousErrorAction
    }

    Get-Process httpd -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue
    Start-Sleep -Seconds 1
}

function Start-LaragonWebServer {
    param([string]$LaragonPath = 'C:\laragon')

    if (Test-UnitecWebServerListening) {
        return $true
    }

    if (Get-Process httpd -ErrorAction SilentlyContinue) {
        return $true
    }

    $httpd = Get-LaragonHttpdExecutable -LaragonPath $LaragonPath
    $httpdConf = Get-LaragonHttpdConfigPath -LaragonPath $LaragonPath

    if ($httpd) {
        $httpdBin = Split-Path $httpd.FullName
        $previousErrorAction = $ErrorActionPreference
        $ErrorActionPreference = 'Continue'

        try {
            Push-Location $httpdBin
            if ($httpdConf) {
                & $httpd.FullName '-f' $httpdConf '-k' 'start' 2>$null | Out-Null
                if ($LASTEXITCODE -ne 0) {
                    Start-UnitecHiddenProcess -FilePath $httpd.FullName -ArgumentList @('-f', $httpdConf) -WorkingDirectory $httpdBin
                }
            } else {
                Start-UnitecHiddenProcess -FilePath $httpd.FullName -WorkingDirectory $httpdBin
            }
        } finally {
            Pop-Location
            $ErrorActionPreference = $previousErrorAction
        }

        Start-Sleep -Seconds 3
        if (Test-UnitecWebServerListening -or (Get-Process httpd -ErrorAction SilentlyContinue)) {
            return $true
        }
    }

    if (Get-Process nginx -ErrorAction SilentlyContinue) {
        return $true
    }

    $nginx = Get-LaragonNginxExecutable -LaragonPath $LaragonPath
    if ($nginx) {
        $nginxDir = Split-Path $nginx.FullName
        Start-UnitecHiddenProcess -FilePath $nginx.FullName -ArgumentList @('-p', $nginxDir) -WorkingDirectory $nginxDir
        Start-Sleep -Seconds 3
        return (Test-UnitecWebServerListening -or ($null -ne (Get-Process nginx -ErrorAction SilentlyContinue)))
    }

    return $false
}

function Restart-LaragonWebServer {
    param([string]$LaragonPath = 'C:\laragon')

    $httpd = Get-LaragonHttpdExecutable -LaragonPath $LaragonPath
    $httpdConf = Get-LaragonHttpdConfigPath -LaragonPath $LaragonPath
    $previousErrorAction = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'

    try {
        if ($httpd) {
            $httpdBin = Split-Path $httpd.FullName

            if (Get-Process httpd -ErrorAction SilentlyContinue) {
                Push-Location $httpdBin
                try {
                    if ($httpdConf) {
                        & $httpd.FullName '-f' $httpdConf '-k' 'graceful' 2>$null | Out-Null
                    } else {
                        & $httpd.FullName '-k' 'graceful' 2>$null | Out-Null
                    }

                    if ($LASTEXITCODE -eq 0) {
                        return
                    }
                } finally {
                    Pop-Location
                }
            }

            Stop-LaragonWebServer -LaragonPath $LaragonPath
            Start-LaragonWebServer -LaragonPath $LaragonPath | Out-Null
            return
        }

        $nginx = Get-LaragonNginxExecutable -LaragonPath $LaragonPath

        if ($nginx) {
            $nginxDir = Split-Path $nginx.FullName
            & $nginx.FullName -p $nginxDir -s reload 2>$null | Out-Null
        }
    } finally {
        $ErrorActionPreference = $previousErrorAction
    }
}

function Invoke-LaragonReload {
    param([string]$LaragonPath = 'C:\laragon')

    $laragonExe = Join-Path $LaragonPath 'laragon.exe'
    if (-not (Test-Path $laragonExe)) {
        return
    }

    foreach ($service in @('apache', 'nginx')) {
        try {
            Start-UnitecHiddenProcess -FilePath $laragonExe -ArgumentList @('reload', $service) -Wait
        } catch {
            # reload so funciona em todas as versoes; ignorar
        }
    }
}

function Start-LaragonServices {
    param(
        [string]$LaragonPath = 'C:\laragon',
        [int]$WaitSeconds = 20,
        [string]$SourceRoot = '',
        [string]$AppPath = ''
    )

    if ([string]::IsNullOrWhiteSpace($AppPath)) {
        $AppPath = $SourceRoot
    }

    if ([string]::IsNullOrWhiteSpace($AppPath)) {
        throw 'Caminho do sistema nao informado ao iniciar PHP e MySQL.'
    }

    $AppPath = Resolve-UnitecAppPath -Path $AppPath
    Ensure-UnitecRuntimeInstalled -AppPath $AppPath -SourceRoot $AppPath
    Initialize-UnitecRuntimePath -AppPath $AppPath

    Ensure-LaragonMysqlRunning -AppPath $AppPath -LaragonPath $LaragonPath -MaxWaitSeconds $WaitSeconds | Out-Null

    Write-Ok 'MySQL pronto.'
    Write-Ok ('O Unitec ERP abrira em {0}' -f (Get-UnitecDefaultAppUrl))
}

function Get-HostnameFromUrl {
    param([string]$Url)

    try {
        return ([Uri]$Url).Host
    } catch {
        return 'unitec-erp-web.test'
    }
}

function Test-HostsEntry {
    param(
        [string]$Hostname,
        [string]$Ip = '127.0.0.1'
    )

    $hostsPath = Join-Path $env:SystemRoot 'System32\drivers\etc\hosts'
    if (-not (Test-Path $hostsPath)) {
        return $false
    }

    $pattern = "(?m)^\s*$([regex]::Escape($Ip))\s+$([regex]::Escape($Hostname))(\s|$)"
    $content = Get-Content $hostsPath -Raw -ErrorAction SilentlyContinue
    return ($content -match $pattern)
}

function Add-HostsEntry {
    param(
        [string]$Hostname,
        [string]$Ip = '127.0.0.1'
    )

    if (Test-HostsEntry -Hostname $Hostname -Ip $Ip) {
        return
    }

    $hostsPath = Join-Path $env:SystemRoot 'System32\drivers\etc\hosts'
    $entry = "$Ip`t$Hostname"
    Add-Content -Path $hostsPath -Value $entry -Encoding ASCII
}

function Write-LaragonApacheVhost {
    param(
        [string]$LaragonPath,
        [string]$Hostname,
        [string]$DocumentRoot
    )

    $sitesDir = Join-Path $LaragonPath 'etc\apache2\sites-enabled'
    if (-not (Test-Path $sitesDir)) {
        New-Item -ItemType Directory -Path $sitesDir -Force | Out-Null
    }

    $docRoot = $DocumentRoot -replace '\\', '/'
    $confPath = Join-Path $sitesDir 'unitec-erp-web.test.conf'
    $content = @"
# Unitec ERP - gerado pelo instalador
<VirtualHost *:80>
    DocumentRoot "$docRoot"
    ServerName $Hostname
    ServerAlias *.$Hostname
    <Directory "$docRoot">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
"@

    Set-Content -Path $confPath -Value $content -Encoding UTF8
}

function Write-LaragonNginxVhost {
    param(
        [string]$LaragonPath,
        [string]$Hostname,
        [string]$DocumentRoot
    )

    $sitesDir = Join-Path $LaragonPath 'etc\nginx\sites-enabled'
    if (-not (Test-Path $sitesDir)) {
        return
    }

    $docRoot = $DocumentRoot -replace '\\', '/'
    $confPath = Join-Path $sitesDir 'unitec-erp-web.test.conf'
    $content = @"
# Unitec ERP - gerado pelo instalador
server {
    listen 80;
    server_name $Hostname *.$Hostname;
    root "$docRoot";
    index index.php index.html;
    location / {
        try_files `$uri `$uri/ /index.php?`$query_string;
    }
    location ~ \.php`$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass php_upstream;
    }
}
"@

    Set-Content -Path $confPath -Value $content -Encoding UTF8
}

function Register-UnitecLocalSite {
    param(
        [string]$LaragonPath = 'C:\laragon',
        [string]$AppUrl = 'http://unitec-erp-web.test',
        [string]$PublicPath = ''
    )

    if ([string]::IsNullOrWhiteSpace($PublicPath)) {
        $PublicPath = Join-Path $script:UnitecDefaultAppPath 'public'
    }

    $hostname = Get-HostnameFromUrl -Url $AppUrl

    if (-not (Test-Path $PublicPath)) {
        throw "Pasta public nao encontrada: $PublicPath"
    }

    Add-HostsEntry -Hostname $hostname
    Assert-HostsEntry -Hostname $hostname
    Write-Ok "Dominio registrado no hosts: $hostname -> 127.0.0.1"

    Write-LaragonApacheVhost -LaragonPath $LaragonPath -Hostname $hostname -DocumentRoot $PublicPath
    Write-Ok "Virtual host Apache criado para $hostname"

    $nginxSitesDir = Join-Path $LaragonPath 'etc\nginx\sites-enabled'
    if (Test-Path $nginxSitesDir) {
        Write-LaragonNginxVhost -LaragonPath $LaragonPath -Hostname $hostname -DocumentRoot $PublicPath
        Write-Ok "Virtual host Nginx criado para $hostname"
    }

    ipconfig /flushdns 2>$null | Out-Null
}
