#Requires -Version 5.1
<#
.SYNOPSIS
    Verifica o instalador e o runtime antes de gerar/publicar o EXE.
#>

param(
    [string]$AppPath = '',
    [switch]$IncludeArtisan,
    [switch]$IncludeMigrateFresh
)

$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

$ProjectRoot = Split-Path -Parent $PSScriptRoot
. (Join-Path $ProjectRoot 'scripts\unitec-install-lib.ps1')

if ([string]::IsNullOrWhiteSpace($AppPath)) {
    $AppPath = Join-Path $ProjectRoot 'dist\staging\unitec-erp-web'
    if (-not (Test-Path (Join-Path $AppPath 'vendor\autoload.php'))) {
        $AppPath = $ProjectRoot
    }
}

$AppPath = Resolve-UnitecAppPath -Path $AppPath
$failures = New-Object System.Collections.Generic.List[string]
$warnings = New-Object System.Collections.Generic.List[string]

function Add-Failure([string]$Message) {
    $failures.Add($Message)
    Write-Host "[FALHA] $Message" -ForegroundColor Red
}

function Add-Warning([string]$Message) {
    $warnings.Add($Message)
    Write-Host "[AVISO] $Message" -ForegroundColor Yellow
}

function Add-Pass([string]$Message) {
    Write-Host "[OK] $Message" -ForegroundColor Green
}

Write-Host ''
Write-Host 'Verificacao do instalador Unitec ERP' -ForegroundColor Cyan
Write-Host "Pasta: $AppPath" -ForegroundColor Gray
Write-Host ''

foreach ($rel in (Get-UnitecStagingRequiredPaths)) {
    if (-not (Test-Path (Join-Path $AppPath $rel))) {
        Add-Failure "Arquivo/pasta obrigatorio ausente: $rel"
    }
}

if ($failures.Count -eq 0) {
    Add-Pass 'Arquivos obrigatorios presentes.'
}

$bomFiles = Test-UnitecPhpSourcesWithoutBom -Root $AppPath
if ($bomFiles.Count -gt 0) {
    Add-Failure ("PHP com BOM UTF-8 ({0} arquivo(s)): {1}" -f $bomFiles.Count, (($bomFiles | Select-Object -First 3) -join ', '))
} else {
    Add-Pass 'Nenhum PHP com BOM em app/.'
}

try {
    Assert-UnitecStagingReady -Root $AppPath -MinFileCount 100
    Add-Pass 'Staging/pacote atende Assert-UnitecStagingReady.'
} catch {
    Add-Failure $_.Exception.Message
}

$emptyFile = Join-Path $env:TEMP ("unitec-empty-{0}.txt" -f [Guid]::NewGuid().ToString('N'))
Set-Content -Path $emptyFile -Value '' -NoNewline
try {
    $trimmed = Get-UnitecTrimmedFileContent -Path $emptyFile
    if ($trimmed -ne '') {
        Add-Failure 'Get-UnitecTrimmedFileContent deveria retornar vazio para arquivo vazio.'
    } else {
        Add-Pass 'Get-UnitecTrimmedFileContent seguro para arquivo vazio.'
    }
} finally {
    Remove-Item $emptyFile -Force -ErrorAction SilentlyContinue
}

if (Test-Path (Join-Path $AppPath '.env')) {
    try {
        Sync-UnitecEnvPerformanceSettings -AppPath $AppPath | Out-Null
        Sync-UnitecEnvPerformanceSettings -AppPath $AppPath | Out-Null
        Add-Pass 'Sync-UnitecEnvPerformanceSettings idempotente.'
    } catch {
        Add-Failure ("Sync-UnitecEnvPerformanceSettings: {0}" -f $_.Exception.Message)
    }
} else {
    Add-Warning '.env ausente — pulando teste de performance do .env.'
}

Initialize-UnitecRuntimePath -AppPath $AppPath
$phpExe = Get-UnitecPhpExecutable -AppPath $AppPath
if (-not (Test-Path $phpExe)) {
    Add-Warning "PHP embutido nao encontrado em $AppPath (testes artisan pulados)."
} else {
    $phpTest = Invoke-PhpExecutableTest -PhpExe $phpExe
    if (-not $phpTest.Ok) {
        Add-Failure ("PHP nao executa: {0}" -f $phpTest.Error)
    } else {
        Add-Pass ("PHP {0} executavel." -f $phpTest.Version)
    }

    if (Test-Path $phpExe) {
        if (Test-PhpExtensionEnabled -ExtensionName 'intl' -PhpExe $phpExe) {
            Add-Pass 'Extensao PHP intl ativa.'
        } else {
            Add-Failure 'Extensao PHP intl ausente (listagens paginadas do Filament exigem intl).'
        }
    }

    if ($IncludeArtisan -and (Test-Path (Join-Path $AppPath '.env')) -and (Test-Path (Join-Path $AppPath 'vendor\autoload.php'))) {
        try {
            $result = Invoke-UnitecArtisan -AppPath $AppPath -Arguments @('key:generate', '--show')
            if (-not $result.Success) {
                Add-Failure ("artisan key:generate --show retornou codigo {0}" -f $result.ExitCode)
            } else {
                Add-Pass 'artisan key:generate --show'
            }
        } catch {
            Add-Failure ("artisan key:generate --show: {0}" -f $_.Exception.Message)
        }

        $db = Get-UnitecDatabaseSettingsFromEnv -AppPath $AppPath
        $dbReachable = Test-MysqlDatabaseAccess -AppPath $AppPath -User $db.DbUser -Password $db.DbPassword -Database $db.DbName -MysqlHost $db.DbHost -Port $db.DbPort

        if ($dbReachable) {
            try {
                $result = Invoke-UnitecArtisan -AppPath $AppPath -Arguments @('migrate:status')
                if (-not $result.Success) {
                    Add-Failure ("artisan migrate:status retornou codigo {0}" -f $result.ExitCode)
                } else {
                    Add-Pass 'artisan migrate:status'
                }
            } catch {
                Add-Failure ("artisan migrate:status: {0}" -f $_.Exception.Message)
            }

            if ($IncludeMigrateFresh) {
                try {
                    $result = Invoke-UnitecArtisan -AppPath $AppPath -Arguments @('migrate:fresh', '--force')
                    if (-not $result.Success) {
                        Add-Failure ("artisan migrate:fresh --force retornou codigo {0}" -f $result.ExitCode)
                    } else {
                        Add-Pass 'artisan migrate:fresh --force'
                    }
                } catch {
                    Add-Failure ("artisan migrate:fresh --force: {0}" -f $_.Exception.Message)
                }
            }
        } else {
            Add-Warning 'Banco indisponivel — migrate:status nao testado.'
        }

        foreach ($command in @(
            @('config:cache'),
            @('view:cache')
        )) {
            try {
                $result = Invoke-UnitecArtisan -AppPath $AppPath -Arguments $command
                if (-not $result.Success) {
                    Add-Failure ("artisan {0} retornou codigo {1}" -f ($command -join ' '), $result.ExitCode)
                } else {
                    Add-Pass ("artisan {0}" -f ($command -join ' '))
                }
            } catch {
                Add-Failure ("artisan {0}: {1}" -f ($command -join ' '), $_.Exception.Message)
            }
        }
    } elseif (-not $IncludeArtisan) {
        Add-Warning 'Use -IncludeArtisan para testar key/migrate/config/view (requer .env e banco).'
    } elseif ($IncludeArtisan -and -not $IncludeMigrateFresh) {
        Add-Warning 'Use -IncludeMigrateFresh com -IncludeArtisan para simular instalacao limpa (migrate:fresh).'
    }
}

Write-Host ''
Write-Host ("Resumo: {0} falha(s), {1} aviso(s)." -f $failures.Count, $warnings.Count) -ForegroundColor Cyan

if ($failures.Count -gt 0) {
    exit 1
}

exit 0
