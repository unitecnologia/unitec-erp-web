#Requires -Version 5.1
<#
.SYNOPSIS
    [LEGADO / SUPORTE] Abre o ERP via PowerShell + navegador.
.DESCRIPTION
    Caminho oficial do cliente: bin\Unitec ERP.exe
    Este script permanece para suporte interno e ferramentas antigas.
#>

param(
    [string]$AppPath = '',
    [string]$AppUrl = '',
    [string]$RelativePath = '/admin',
    [switch]$SkipBrowser,
    [switch]$LeigoMode,
    [switch]$Kiosk,
    # Janela tipo aplicativo (Chrome/Edge --app). Padrao ao abrir pelo atalho do cliente.
    [switch]$AppMode
)

$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

. (Join-Path $PSScriptRoot 'unitec-install-lib.ps1')

# Redireciona para o launcher oficial quando disponivel (exceto SkipBrowser).
if (-not $SkipBrowser) {
    $resolvedApp = Resolve-UnitecAppPath -Path $AppPath -FallbackFromScriptRoot $PSScriptRoot
    $launcher = Join-Path $resolvedApp 'bin\Unitec ERP.exe'
    if (Test-Path -LiteralPath $launcher) {
        Start-Process -FilePath $launcher -ArgumentList @('--app', $resolvedApp) -WorkingDirectory $resolvedApp | Out-Null
        exit 0
    }
}

function Get-UnitecBrowserExe {
    $candidates = @(
        (Join-Path $env:ProgramFiles 'Google\Chrome\Application\chrome.exe'),
        (Join-Path ${env:ProgramFiles(x86)} 'Google\Chrome\Application\chrome.exe'),
        (Join-Path $env:LOCALAPPDATA 'Google\Chrome\Application\chrome.exe'),
        (Join-Path ${env:ProgramFiles(x86)} 'Microsoft\Edge\Application\msedge.exe'),
        (Join-Path $env:ProgramFiles 'Microsoft\Edge\Application\msedge.exe')
    )

    foreach ($path in $candidates) {
        if (-not [string]::IsNullOrWhiteSpace($path) -and (Test-Path $path)) {
            return $path
        }
    }

    return $null
}

function Get-UnitecBrowserProfileDir {
    param([string]$AppPath)

    $dir = Join-Path $env:LOCALAPPDATA 'UnitecERP\browser-profile'
    if (-not (Test-Path -LiteralPath $dir)) {
        New-Item -ItemType Directory -Force -Path $dir | Out-Null
    }

    # Marca de instalação: se o ERP pediu reset, limpa o perfil do app (cookies do Unitec só).
    $resetMarker = Join-Path $AppPath '.unitec-browser-reset'
    $wipeMarker = Join-Path $dir '.wipe-once'
    if ((Test-Path -LiteralPath $resetMarker) -or (Test-Path -LiteralPath $wipeMarker)) {
        Get-ChildItem -LiteralPath $dir -Force -ErrorAction SilentlyContinue |
            Where-Object { $_.Name -ne '.wipe-once' } |
            Remove-Item -Recurse -Force -ErrorAction SilentlyContinue
        Remove-Item -LiteralPath $wipeMarker -Force -ErrorAction SilentlyContinue
    }

    return $dir
}

function Get-UnitecBrowserAppProcess {
    param([Parameter(Mandatory = $true)][string]$ProfileDir)

    $escapedProfile = [regex]::Escape($ProfileDir)

    return Get-CimInstance Win32_Process -Filter "Name='chrome.exe' OR Name='msedge.exe'" -ErrorAction SilentlyContinue |
        Where-Object {
            $_.CommandLine -match '--app=' -and $_.CommandLine -match $escapedProfile
        } |
        Select-Object -First 1
}

function Focus-UnitecBrowserApp {
    param($Process)

    if ($null -eq $Process) {
        return $false
    }

    try {
        $native = @'
using System;
using System.Runtime.InteropServices;
public static class UnitecWindowFocus {
    [DllImport("user32.dll")]
    public static extern bool ShowWindowAsync(IntPtr hWnd, int nCmdShow);
    [DllImport("user32.dll")]
    public static extern bool SetForegroundWindow(IntPtr hWnd);
}
'@
        if (-not ('UnitecWindowFocus' -as [type])) {
            Add-Type -TypeDefinition $native -ErrorAction Stop
        }

        $running = Get-Process -Id $Process.ProcessId -ErrorAction Stop
        if ($running.MainWindowHandle -eq [IntPtr]::Zero) {
            return $false
        }

        [UnitecWindowFocus]::ShowWindowAsync($running.MainWindowHandle, 9) | Out-Null
        [UnitecWindowFocus]::SetForegroundWindow($running.MainWindowHandle) | Out-Null

        return $true
    } catch {
        return $false
    }
}

function Open-UnitecBrowser {
    param(
        [Parameter(Mandatory = $true)][string]$Url,
        [Parameter(Mandatory = $true)][string]$AppPath,
        [switch]$Kiosk,
        [switch]$AppMode
    )

    $browser = Get-UnitecBrowserExe

    if ([string]::IsNullOrWhiteSpace($browser)) {
        Start-Process $Url
        return
    }

    $arguments = @()
    if ($AppMode) {
        # Perfil isolado = app de verdade. Nao herda cookie do Chrome pessoal do cliente
        # (evita ERR_TOO_MANY_REDIRECTS apos reinstalar).
        $profileDir = Get-UnitecBrowserProfileDir -AppPath $AppPath
        $existingApp = Get-UnitecBrowserAppProcess -ProfileDir $profileDir
        if ($existingApp -and (Focus-UnitecBrowserApp -Process $existingApp)) {
            return
        }

        $arguments += ('--app={0}' -f $Url)
        $arguments += ('--user-data-dir={0}' -f $profileDir)
        $arguments += '--start-maximized'
        $arguments += '--no-first-run'
        $arguments += '--no-default-browser-check'
    } elseif ($Kiosk) {
        $profileDir = Get-UnitecBrowserProfileDir -AppPath $AppPath
        $arguments += '--kiosk'
        $arguments += ('--user-data-dir={0}' -f $profileDir)
        $arguments += '--no-first-run'
        $arguments += $Url
    } else {
        $arguments += $Url
    }

    Start-Process -FilePath $browser -ArgumentList $arguments
}

$AppPath = Resolve-UnitecAppPath -Path $AppPath -FallbackFromScriptRoot $PSScriptRoot

if ([string]::IsNullOrWhiteSpace($AppUrl)) {
    $AppUrl = Get-UnitecDefaultAppUrl
}

$relative = if ($RelativePath.StartsWith('/')) { $RelativePath } else { "/$RelativePath" }
# Login alinhado ao atalho do cliente (--app).
if ($relative -eq '/admin') {
    $relative = '/admin/login'
}

# Após instalar/reinstalar: abre rota que limpa cookies do navegador (sem intervenção do usuário).
$browserResetMarker = Join-Path $AppPath '.unitec-browser-reset'
if (Test-Path -LiteralPath $browserResetMarker) {
    $relative = '/admin/sessao-limpa'
}

$targetUrl = $AppUrl.TrimEnd('/') + $relative

# Atalho leigo / instalador: sempre janela de aplicativo.
if ($LeigoMode -and -not $PSBoundParameters.ContainsKey('AppMode')) {
    $AppMode = $true
}

try {
    if (-not (Test-Path (Join-Path $AppPath 'public\index.php'))) {
        throw 'O sistema nao foi encontrado. Reinstale o Unitec ERP.'
    }

    if (-not $LeigoMode) {
        Write-Title 'Abrindo Unitec ERP'
    }

    $envEnsure = Ensure-UnitecEnvFile -AppPath $AppPath -AppUrl $AppUrl
    if ($envEnsure.Created -and -not $LeigoMode) {
        if ($envEnsure.Restored) {
            Write-Ok 'Arquivo .env restaurado de backup.'
        } else {
            Write-Warn 'Arquivo .env estava ausente e foi recriado com padroes do instalador.'
        }
    }

    # .env novo = instalação/recuperação: forçar limpeza de sessão no navegador na próxima abertura.
    if ($envEnsure.Created) {
        Set-Content -LiteralPath $browserResetMarker -Value ((Get-Date).ToString('o')) -Encoding ASCII
        $relative = '/admin/sessao-limpa'
        $targetUrl = $AppUrl.TrimEnd('/') + $relative
    }

    Sync-UnitecEnvAppUrl -AppPath $AppPath -AppUrl $AppUrl | Out-Null

    # Se um update anterior parou no PHP/VC++, termina agora (apos reboot).
    try {
        if (Test-UnitecUpdatePendingFinish -AppPath $AppPath) {
            if (-not $LeigoMode) {
                Write-Host 'Detectada atualizacao pendente - finalizando...' -ForegroundColor Cyan
            }
            Complete-UnitecPendingUpdate -AppPath $AppPath | Out-Null
        }
    } catch {
        Write-InstallLog -AppPath $AppPath -Message ('Falha ao finalizar update pendente: {0}' -f $_.Exception.Message)
        throw ('Atualizacao pendente nao concluida: {0}' -f $_.Exception.Message)
    }

    Start-UnitecStack -AppPath $AppPath -WaitSeconds 15

    if (-not $SkipBrowser) {
        Open-UnitecBrowser -Url $targetUrl -AppPath $AppPath -Kiosk:$Kiosk -AppMode:$AppMode
    }

    if (Test-Path -LiteralPath $browserResetMarker) {
        Remove-Item -LiteralPath $browserResetMarker -Force -ErrorAction SilentlyContinue
    }

    exit 0
} catch {
    Write-InstallLog -AppPath $AppPath -Message $_.Exception.Message

    Show-UnitecLeigoMessage -Title 'Unitec ERP' -Message @"
Nao foi possivel abrir o sistema.

$($_.Exception.Message)

Abra o arquivo COMO USAR na Area de Trabalho
ou ligue para o suporte da Unitecnologia.
"@ -Icon Error

    exit 1
}
