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
# Host de bind do servidor web. 0.0.0.0 expõe o ERP na rede local (necessário
# para terminais e para o app Força de Vendas). A porta 8765 ja e liberada no firewall.
$script:UnitecServeBindHost = '0.0.0.0'
$script:UnitecServePort = 8765
# Numero de workers do servidor embutido do PHP (atende varios aparelhos/terminais
# simultaneos, ex.: app Força de Vendas sincronizando). PHP 8+.
$script:UnitecServeWorkers = 8
$script:UnitecDefaultAppUrl = 'http://127.0.0.1:8765'
$script:UnitecServePidFileName = '.unitec-serve.pid'
$script:UnitecDefaultDbName = 'unitec_erp'
$script:UnitecDefaultDbUser = 'root'
$script:UnitecDefaultDbPassword = 'rua@2050bc'
$script:UnitecMinDiskSpaceMb = 2048
$script:UnitecInstallerAssetNames = @('mariadb-win.zip', 'php-8.4-win.zip', 'vc_redist.x64.exe', 'HeidiSQL_12.18.0.7304_Setup.exe', 'unitec-erp.ico', 'cacert.pem')

function Get-UnitecDefaultAppPath {
    return $script:UnitecDefaultAppPath
}

function Get-UnitecDefaultAppUrl {
    return $script:UnitecDefaultAppUrl
}

function Get-UnitecDefaultDbPassword {
    return $script:UnitecDefaultDbPassword
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
max_connections=50
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

    $tempDir = Join-Path $env:TEMP ("unitec-mysql-{0}" -f [Guid]::NewGuid().ToString('N'))
    Ensure-Directory $tempDir

    try {
        Expand-Archive -Path $ZipPath -DestinationPath $tempDir -Force

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
        Remove-Item $tempDir -Recurse -Force -ErrorAction SilentlyContinue
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

    $tempDir = Join-Path $env:TEMP ('unitec-php-' + [Guid]::NewGuid().ToString('N'))
    Ensure-Directory $tempDir

    try {
        Expand-Archive -Path $ZipPath -DestinationPath $tempDir -Force

        if (Test-Path (Join-Path $tempDir 'php.exe')) {
            Get-ChildItem $tempDir -Force | Move-Item -Destination $phpRoot -Force
        } else {
            $extracted = Get-ChildItem $tempDir -Directory | Select-Object -First 1
            if (-not $extracted -or -not (Test-Path (Join-Path $extracted.FullName 'php.exe'))) {
                throw 'Pacote PHP invalido (php.exe nao encontrado).'
            }
            Get-ChildItem $extracted.FullName -Force | Move-Item -Destination $phpRoot -Force
        }

        Configure-LaragonPhpIni -PhpDirectory $phpRoot -SourceRoot $AppPath -DisableOpcache
        Write-Ok 'PHP instalado em tools\php.'
        return $phpRoot
    } finally {
        Remove-Item $tempDir -Recurse -Force -ErrorAction SilentlyContinue
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
        Configure-LaragonPhpIni -PhpDirectory $phpDir -SourceRoot $SourceRoot -DisableOpcache
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

    if ($updated -eq $content) {
        return $false
    }

    Set-Content -Path $iniPath -Value $updated -Encoding ASCII
    return $true
}

function Register-UnitecMariaDbFirewallRule {
    $ruleName = 'Unitec ERP MariaDB (porta 3306)'
    $existing = & netsh advfirewall firewall show rule name="$ruleName" 2>$null
    if ($LASTEXITCODE -eq 0) {
        return
    }

    & netsh advfirewall firewall add rule name="$ruleName" dir=in action=allow protocol=TCP localport=3306 | Out-Null
    Write-Ok 'Regra de firewall MariaDB (3306) configurada.'
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

function Stop-UnitecApplicationServer {
    param([string]$AppPath)

    $pidFile = Get-UnitecServePidFilePath -AppPath $AppPath
    if (Test-Path $pidFile) {
        $raw = (Get-Content $pidFile -Raw -ErrorAction SilentlyContinue)
        if ($raw -match '(\d+)') {
            Stop-Process -Id ([int]$matches[1]) -Force -ErrorAction SilentlyContinue
        }
        Remove-Item $pidFile -Force -ErrorAction SilentlyContinue
    }
}

function Test-UnitecApplicationServerRunning {
    param(
        [string]$AppPath = '',
        [string]$AppUrl = ''
    )

    if (-not (Test-UnitecTcpPortOpen -Port $script:UnitecServePort -HostName $script:UnitecServeHost)) {
        return $false
    }

    return (Wait-UnitecApplicationReady -AppUrl $AppUrl -MaxAttempts 1 -Quiet)
}

function Start-UnitecApplicationServer {
    param(
        [string]$AppPath,
        [switch]$Restart
    )

    if ($Restart) {
        Stop-UnitecApplicationServer -AppPath $AppPath
    }

    if (Test-UnitecApplicationServerRunning -AppPath $AppPath) {
        Write-Ok ('Unitec ERP ja esta ativo em {0}' -f (Get-UnitecDefaultAppUrl))
        return $true
    }

    Stop-UnitecApplicationServer -AppPath $AppPath

    if (-not (Test-Path (Join-Path $AppPath 'vendor\autoload.php'))) {
        throw 'Sistema incompleto: pasta vendor/ ausente. Reinstale o Unitec ERP.'
    }

    Initialize-UnitecRuntimePath -AppPath $AppPath

    Push-Location $AppPath
    try {
        Write-Host 'Iniciando Unitec ERP (servidor integrado)...' -ForegroundColor White

        $configCached = Test-Path (Join-Path $AppPath 'bootstrap\cache\config.php')
        if (-not $configCached) {
            Invoke-UnitecArtisan -AppPath $AppPath -Arguments @('config:cache') -AllowFailure | Out-Null
        }

        $phpExe = Get-UnitecPhpExecutable -AppPath $AppPath

        # Habilita multiplos workers no servidor embutido do PHP para suportar
        # acessos simultaneos (terminais + app Força de Vendas).
        $env:PHP_CLI_SERVER_WORKERS = "$($script:UnitecServeWorkers)"

        $proc = Start-Process -FilePath $phpExe -ArgumentList @(
            'artisan', 'serve',
            "--host=$($script:UnitecServeBindHost)",
            "--port=$($script:UnitecServePort)"
        ) -WorkingDirectory $AppPath -WindowStyle Hidden -PassThru

        if ($null -eq $proc) {
            throw 'Nao foi possivel iniciar o servidor do Unitec ERP.'
        }

        Set-Content -Path (Get-UnitecServePidFilePath -AppPath $AppPath) -Value $proc.Id -Encoding ASCII

        if (-not (Wait-UnitecApplicationReady -MaxAttempts 20 -DelaySeconds 2 -Quiet)) {
            throw 'O Unitec ERP nao iniciou a tempo. Consulte instalacao.log'
        }

        Write-Ok ('Unitec ERP ativo em {0}' -f (Get-UnitecDefaultAppUrl))
        return $true
    } finally {
        Pop-Location
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
                if (Test-UnitecHttpStatusReachable -StatusCode $response.StatusCode) {
                    return $true
                }
            } catch {
                $webResponse = $_.Exception.Response
                if ($null -ne $webResponse) {
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
    if (Sync-UnitecEnvPerformanceSettings -AppPath $AppPath) {
        Invoke-UnitecArtisan -AppPath $AppPath -Arguments @('config:cache') -AllowFailure | Out-Null
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

    if (-not $Force -and (Test-UnitecMigrationSignatureCurrent -AppPath $AppPath)) {
        return
    }

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

    foreach ($name in @(
        'INFORSYSTEM Retaguarda.lnk',
        'INFORSYSTEM PDV.lnk',
        'INFORSYSTEM Pre-venda.lnk',
        'Unitec ERP.lnk'
    )) {
        $path = Join-Path $desktop $name
        if (Test-Path $path) {
            Remove-Item $path -Force -ErrorAction SilentlyContinue
        }
    }
}

function New-UnitecLeigoWelcomeCard {
    param([string]$AppPath = $script:UnitecDefaultAppPath)

    $appUrl = Get-UnitecDefaultAppUrl
    $urls = Get-UnitecAppUrls -AppUrl $appUrl
    $desktop = [Environment]::GetFolderPath('Desktop')
    $cardPath = Join-Path $desktop 'COMO USAR - Unitec ERP.txt'

    $content = @"
========================================
  Unitec ERP - COMO USAR
========================================

1) Para abrir o sistema
   Duplo clique no atalho "Unitec ERP" na Area de Trabalho.

2) Login
   E-mail: usuario@unitecnologia.local
   Senha:  01
   (Troque a senha depois do primeiro acesso.)

3) Enderecos uteis
   Retaguarda: $($urls.Retaguarda)
   PDV:        $($urls.Pdv)
   Pre-venda:  $($urls.PreVenda)

4) Se nao abrir
   - Reinicie o computador e tente de novo.
   - Pasta do sistema: $AppPath
   - Suporte: https://unitecnologiasc.com.br/

========================================
"@

    Set-Content -Path $cardPath -Value $content -Encoding UTF8
    Write-Ok 'Cartao "COMO USAR" criado na Area de Trabalho.'
}

function Register-UnitecFirewallRule {
    $ruleName = 'Unitec ERP (porta 8765)'
    $existing = & netsh advfirewall firewall show rule name="$ruleName" 2>$null
    if ($LASTEXITCODE -eq 0) {
        return
    }

    & netsh advfirewall firewall add rule name="$ruleName" dir=in action=allow protocol=TCP localport=$script:UnitecServePort | Out-Null
    Write-Ok 'Regra de firewall configurada.'
}

function Register-UnitecLogonStartup {
    param([string]$AppPath = $script:UnitecDefaultAppPath)

    $taskName = 'UnitecERP_IniciarComWindows'
    $scriptPath = Join-Path $AppPath 'scripts\start-unitec-background.ps1'

    if (-not (Test-Path $scriptPath)) {
        return
    }

    $action = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument "-WindowStyle Hidden -NoProfile -ExecutionPolicy Bypass -File `"$scriptPath`" -AppPath `"$AppPath`"" -WorkingDirectory $AppPath
    $trigger = New-ScheduledTaskTrigger -AtLogOn -User $env:USERNAME
    $settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable
    $principal = New-ScheduledTaskPrincipal -UserId $env:USERNAME -LogonType Interactive -RunLevel Limited

    try {
        Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Settings $settings -Principal $principal -Force | Out-Null
        Write-Ok 'Sistema configurado para iniciar com o Windows.'
    } catch {
        Write-Warn 'Nao foi possivel criar tarefa de inicio automatico (ignorado).'
    }
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

    $launcher = Join-Path $AppPath 'Unitec ERP.bat'
    if (-not (Test-Path $launcher)) {
        Write-Warn 'Unitec ERP.bat nao encontrado — atalho nao criado.'
        return
    }

    $desktop = [Environment]::GetFolderPath('Desktop')
    $shell = New-Object -ComObject WScript.Shell
    $lnkPath = Join-Path $desktop 'Unitec ERP.lnk'
    $shortcut = $shell.CreateShortcut($lnkPath)
    $shortcut.TargetPath = $launcher
    $shortcut.WorkingDirectory = $AppPath
    $shortcut.Description = 'Abrir Unitec ERP'
    Set-UnitecShortcutIcon -Shortcut $shortcut -AppPath $AppPath
    $shortcut.Save()

    Write-Ok 'Atalho "Unitec ERP" criado na Area de Trabalho.'
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
        Write-Warn 'HeidiSQL ignorado — .env ainda nao existe.'
        return $false
    }

    try {
        $exe = Resolve-HeidiSqlExecutable -AppPath $AppPath -AllowInstall
    } catch {
        Write-Warn $_.Exception.Message
        Write-Warn 'HeidiSQL nao instalado — o ERP funciona normalmente; suporte pode instalar depois.'
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
        [switch]$ExcludeTools
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
        $excludeDirs += 'storage'
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

    $projectArgs += '/XF'
    $projectArgs += '.env'
    $projectArgs += '.env.backup'
    $projectArgs += '.env.production'

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

    & robocopy @vendorArgs | Out-Null
    if ($LASTEXITCODE -ge 8) {
        throw "robocopy vendor falhou (codigo $LASTEXITCODE)."
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

    $stderrFile = Join-Path $env:TEMP ("unitec-php-err-{0}.txt" -f [Guid]::NewGuid().ToString('N'))
    try {
        $output = & $PhpExe -r 'echo PHP_VERSION;' 2> $stderrFile
        $stderr = ''
        if (Test-Path $stderrFile) {
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
        Remove-Item $stderrFile -Force -ErrorAction SilentlyContinue
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
    }
    $candidates += (Join-Path $PSScriptRoot '..\installer\assets\vc_redist.x64.exe')

    foreach ($path in $candidates) {
        if ([string]::IsNullOrWhiteSpace($path)) {
            continue
        }

        $full = [System.IO.Path]::GetFullPath($path)
        if (Test-UnitecPathExists $full) {
            return $full
        }
    }

    $downloaded = Join-Path $env:TEMP 'unitec-vc-redist-x64.exe'
    if (Test-Path $downloaded) {
        return $downloaded
    }

    Write-Host 'Baixando Visual C++ Redistributable x64 (~25 MB)...' -ForegroundColor Yellow
    try {
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
        Write-Warn 'Visual C++ instalado — reinicio do Windows recomendado (continuando testes).'
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

    $zipPath = Resolve-Php84ZipPath -SourceRoot $SourceRoot
    $tempDir = Join-Path $env:TEMP ("unitec-php-test-{0}" -f [Guid]::NewGuid().ToString('N'))
    New-Item -ItemType Directory -Path $tempDir -Force | Out-Null

    try {
        Expand-Archive -Path $zipPath -DestinationPath $tempDir -Force

        $phpExe = Join-Path $tempDir 'php.exe'
        if (-not (Test-Path $phpExe)) {
            $subdir = Get-ChildItem $tempDir -Directory -ErrorAction SilentlyContinue | Select-Object -First 1
            if ($subdir) {
                $phpExe = Join-Path $subdir.FullName 'php.exe'
            }
        }

        if (-not (Test-Path $phpExe)) {
            throw 'php.exe nao encontrado no pacote PHP 8.4 embutido.'
        }

        return Repair-PhpExecutableRuntime -SourceRoot $SourceRoot -PhpExe $phpExe -AllowFix:$AllowFix
    } finally {
        Remove-Item $tempDir -Recurse -Force -ErrorAction SilentlyContinue
    }
}

function Invoke-UnitecSystemRequirementsCheck {
    param(
        [string]$SourceRoot = '',
        [switch]$FixVcRuntime,
        [switch]$Quiet
    )

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
        $results += @{ Name = 'Porta 80 (HTTP)'; Ok = $true; Detail = 'AVISO: em uso — pode conflitar com Apache.' }
    } else {
        $results += @{ Name = 'Porta 80 (HTTP)'; Ok = $true; Detail = 'Livre' }
    }

    if (Test-UnitecTcpPortInUse -Port 3306) {
        $results += @{ Name = 'Porta 3306 (MySQL)'; Ok = $true; Detail = 'AVISO: em uso — outro MySQL pode conflitar.' }
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
                Write-Ok ('{0} — {1}' -f $item.Name, $item.Detail)
            } else {
                Write-Err ('{0} — {1}' -f $item.Name, $item.Detail)
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

function Get-UnitecMegadlAssetPath {
    param([string]$SourceRoot)

    return Join-Path $SourceRoot 'installer\assets\megatools\megadl.exe'
}

function Sync-UnitecMegatoolsToStaging {
    param(
        [string]$ProjectRoot,
        [string]$StagingDir
    )

    $sourceMegadl = Get-UnitecMegadlAssetPath -SourceRoot $ProjectRoot
    if (-not (Test-Path $sourceMegadl)) {
        return
    }

    $targetDir = Join-Path $StagingDir 'installer\assets\megatools'
    Ensure-Directory $targetDir
    Copy-Item $sourceMegadl (Join-Path $targetDir 'megadl.exe') -Force

    $readme = Join-Path (Split-Path $sourceMegadl -Parent) 'README.txt'
    if (Test-Path $readme) {
        Copy-Item $readme (Join-Path $targetDir 'README.txt') -Force
    }
}

function Ensure-UnitecMegadlAsset {
    param(
        [string]$SourceRoot,
        [switch]$SkipDownload
    )

    $megadlPath = Get-UnitecMegadlAssetPath -SourceRoot $SourceRoot
    if (Test-Path $megadlPath) {
        return $megadlPath
    }

    Write-Warn 'megadl.exe nao e mais necessario (atualizacao via HTTPS/Dropbox). Ignorado.'
    return $null
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

    $pattern = '^\s*' + [regex]::Escape($ExtensionName) + '\s*$'
    return [bool](& $PhpExe -m 2>$null | Select-String -Pattern $pattern -Quiet)
}

function Assert-UnitecPhpDatabaseReady {
    param([string]$AppPath = '')

    $phpExe = Get-UnitecPhpExecutable -AppPath $AppPath
    if (-not (Test-PhpExtensionEnabled -ExtensionName 'pdo_mysql' -PhpExe $phpExe)) {
        throw 'Extensao PHP pdo_mysql nao esta ativa. Reinstale o Unitec ERP ou execute o instalador como administrador.'
    }
}

function Assert-UnitecPhpIntlReady {
    param([string]$AppPath = '')

    $phpExe = Get-UnitecPhpExecutable -AppPath $AppPath
    if (-not (Test-PhpExtensionEnabled -ExtensionName 'intl' -PhpExe $phpExe)) {
        throw 'Extensao PHP intl nao esta ativa (obrigatoria para listagens paginadas). Reinstale o Unitec ERP ou execute o instalador como administrador.'
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
    }

    $lines = @(Get-Content $envFile -Encoding UTF8)
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

        $migrateCommand = if ($FreshInstall) { 'migrate:fresh' } else { 'migrate' }
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

function Repair-UnitecMysqlDataIfCorrupt {
    param([string]$DataDir)

    if (-not (Test-Path $DataDir)) {
        return
    }

    if ((Test-Path (Join-Path $DataDir 'ibdata1')) -and -not (Test-UnitecMysqlSystemTablesReady -DataDir $DataDir)) {
        Write-Warn 'Pasta data do MariaDB incompleta (init anterior falhou). Recriando...'
        Remove-Item $DataDir -Recurse -Force -ErrorAction Stop
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
        if (-not [string]::IsNullOrWhiteSpace($AppPath)) {
            Write-InstallLog -AppPath $AppPath -Message 'Inicializando MariaDB (mariadb-install-db)...'
        }
    }

    if (Test-MysqlDataInitialized -DataDir $dataDir) {
        return
    }

    Ensure-Directory $dataDir
    Write-Host 'Inicializando dados do MariaDB (primeira execucao)...' -ForegroundColor White

    if ($useEmbeddedMariaDb) {
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
        return $true
    }

    $prefix = Get-UnitecEnvValue -AppPath $AppPath -Key 'DB_PREFIX'
    if ([string]::IsNullOrWhiteSpace($prefix)) {
        $prefix = 'unitec_'
    }

    $table = "${prefix}users"
    $escapedEmail = Escape-MySqlStringLiteral -Value 'usuario@unitecnologia.local'
    $sql = "SELECT COUNT(*) FROM ``$table`` WHERE email = '$escapedEmail'"
    $hosts = Get-UnitecDatabaseConnectionHostsFromEnv -AppPath $AppPath

    foreach ($hostName in $hosts) {
        $scalar = Test-UnitecSqlScalarViaPhp -AppPath $AppPath -User $db.DbUser -Password $db.DbPassword -Database $db.DbName -MysqlHost $hostName -Port $db.DbPort -Sql $sql
        if ($null -ne $scalar) {
            return ([int]$scalar -eq 0)
        }

        $defaultsFile = $null
        try {
            $defaultsFile = New-UnitecMysqlDefaultsFile -User $db.DbUser -Password $db.DbPassword -MysqlHost $hostName -Port $db.DbPort
            $mysqlExe = Get-MysqlExecutable -LaragonPath $LaragonPath -AppPath $AppPath
            if (-not $mysqlExe) {
                continue
            }

            $output = & $mysqlExe "--defaults-extra-file=$defaultsFile" $db.DbName '-N' '-e' $sql 2>$null
            if ($LASTEXITCODE -eq 0) {
                $count = 0
                if ($null -ne $output -and "$output".Trim() -ne '') {
                    $count = [int]([string]$output).Trim()
                }

                return ($count -eq 0)
            }
        } catch {
            continue
        } finally {
            if ($defaultsFile) {
                Remove-Item $defaultsFile -Force -ErrorAction SilentlyContinue
            }
        }
    }

    return $true
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

    if (-not (Test-Path (Join-Path $AppPath 'artisan'))) {
        return $false
    }

    if (-not $Foreground -and (Test-UnitecWebServerListening -Port $Port)) {
        return $true
    }

    Initialize-UnitecRuntimePath -AppPath $AppPath
    Ensure-UnitecPhpIniForWindowsDev -AppPath $AppPath | Out-Null

    $phpExe = Get-UnitecPhpExecutable -AppPath $AppPath
    if (-not (Test-Path $phpExe)) {
        throw "PHP nao encontrado: $phpExe"
    }

    # Multiplos workers para suportar acessos simultaneos (terminais + app Força de Vendas).
    $env:PHP_CLI_SERVER_WORKERS = "$($script:UnitecServeWorkers)"

    Push-Location $AppPath
    try {
        if ($Foreground) {
            Write-Host ''
            Write-Host "Servidor em http://${BindHost}:$Port - Ctrl+C para parar." -ForegroundColor Green
            Write-Host ''

            & $phpExe artisan serve "--host=$BindHost" "--port=$Port"

            return $true
        }

        Write-Host "Iniciando servidor PHP embutido (artisan serve) na porta $Port..." -ForegroundColor White

        Start-UnitecHiddenProcess -FilePath $phpExe -ArgumentList @(
            'artisan', 'serve',
            "--host=$BindHost",
            "--port=$Port"
        ) -WorkingDirectory $AppPath

        $deadline = (Get-Date).AddSeconds(20)
        while ((Get-Date) -lt $deadline) {
            if (Test-UnitecWebServerListening -Port $Port) {
                Write-Ok "Servidor PHP ativo em ${BindHost}:$Port"
                return $true
            }
            Start-Sleep -Seconds 2
        }
    } finally {
        Pop-Location
    }

    return $false
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
        Start-UnitecPhpArtisanServer -AppPath $AppPath | Out-Null
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

    Configure-LaragonPhpIni -PhpDirectory $phpDir -SourceRoot $AppPath -DisableOpcache

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

    if ($content -notmatch '(?m)^extension_dir\s*=') {
        $content += [Environment]::NewLine + 'extension_dir="ext"' + [Environment]::NewLine
    }

    $opcacheSettings = if ($DisableOpcache) {
        @(
            'opcache.enable=0',
            'opcache.enable_cli=0'
        )
    } else {
        @(
            'opcache.enable=1',
            'opcache.enable_cli=0',
            'opcache.memory_consumption=128',
            'opcache.interned_strings_buffer=16',
            'opcache.max_accelerated_files=10000',
            'opcache.validate_timestamps=1',
            'opcache.revalidate_freq=60'
        )
    }

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

    try {
        $caPath = Ensure-UnitecPhpSslCaBundle -PhpDirectory $PhpDirectory -SourceRoot $SourceRoot
        $content = Set-PhpIniSslCaSettings -Content $content -CaPath $caPath
    } catch {
        Write-Warn ("Nao foi possivel configurar SSL CA do PHP: {0}" -f $_.Exception.Message)
    }

    Set-Content -Path $iniPath -Value $content -Encoding UTF8 -NoNewline
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

    $tempDir = Join-Path $env:TEMP ('unitec-php-' + [Guid]::NewGuid().ToString('N'))
    New-Item -ItemType Directory -Path $tempDir -Force | Out-Null

    try {
        Expand-Archive -Path $ZipPath -DestinationPath $tempDir -Force

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

Peça ao suporte da Unitecnologia ou renomeie a pasta C:\laragon para C:\laragon_antigo e instale novamente.
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
