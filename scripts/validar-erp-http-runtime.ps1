#Requires -Version 5.1
<#
.SYNOPSIS
    Anti-regressao: falha se caminhos de inicializacao do ERP voltarem a usar
    artisan serve / php -S como servidor HTTP.
.DESCRIPTION
    Ignora documentacao (docs/), comentarios meramente textuais em app/ e o
    proprio validador. Falha em scripts bat/ps1, C# desktop e composer.json
    quando ha invocacao executavel.
#>
param(
    [string]$AppPath = ''
)

$ErrorActionPreference = 'Stop'

if ([string]::IsNullOrWhiteSpace($AppPath)) {
    $AppPath = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
} else {
    $AppPath = (Resolve-Path $AppPath).Path
}

$scanRoots = @(
    (Join-Path $AppPath 'scripts'),
    (Join-Path $AppPath 'services\unitec-erp-desktop'),
    (Join-Path $AppPath 'installer')
)

$extraFiles = @(
    (Join-Path $AppPath 'ABRIR-AGORA.bat'),
    (Join-Path $AppPath 'Desenvolver.bat'),
    (Join-Path $AppPath 'composer.json')
)

$includeExt = @('.ps1', '.bat', '.cmd', '.cs', '.json')
$excludeNameHints = @(
    'validar-erp-http-runtime.ps1'
)

# Padroes de INVOCACAO (nao mencao em comentario de kill/doc).
$executablePatterns = @(
    @{ Name = 'artisan serve'; Regex = '(?i)(?<![\w-])(?:php\s+)?artisan\s+serve\b' },
    @{ Name = "artisan', 'serve"; Regex = "(?i)['\`"]artisan['\`"]\s*,\s*['\`"]serve['\`"]" },
    @{ Name = 'php -S'; Regex = '(?i)\bphp(?:\.exe)?\s+-S\s' },
    @{ Name = ' -S 127.0.0.1:'; Regex = '(?i)(?<![/\w])-S\s+(?:127\.0\.0\.1|0\.0\.0\.0|localhost):' }
)

function Test-IsCommentOrDocLine {
    param([string]$Line, [string]$Ext)

    $t = $Line.Trim()
    if ([string]::IsNullOrWhiteSpace($t)) { return $true }

    if ($Ext -eq '.ps1' -or $Ext -eq '.bat' -or $Ext -eq '.cmd') {
        if ($t.StartsWith('#') -or $t.StartsWith('REM ', [StringComparison]::OrdinalIgnoreCase) -or $t.StartsWith('::')) {
            return $true
        }
        # Comentario inline PowerShell apos codigo de kill/doc — ainda conta se houver Start-Process.
    }

    if ($Ext -eq '.cs') {
        if ($t.StartsWith('//') -or $t.StartsWith('///') -or $t.StartsWith('*')) {
            return $true
        }
    }

    if ($Ext -eq '.json') {
        # composer.json scripts: qualquer match e executavel.
        return $false
    }

    # Linhas que so documentam o que matar / rejeitar.
    if ($t -match '(?i)(mata|kill|legado|invalido|sem fallback|nunca|proibido|rejeita)') {
        if ($t -notmatch '(?i)(Start-Process|ArgumentList|&\s+\$|ProcessStartInfo|FileName\s*=)') {
            return $true
        }
    }

    return $false
}

$violations = New-Object System.Collections.Generic.List[string]

$files = New-Object System.Collections.Generic.List[string]
foreach ($root in $scanRoots) {
    if (-not (Test-Path -LiteralPath $root)) { continue }
    Get-ChildItem -LiteralPath $root -Recurse -File -ErrorAction SilentlyContinue |
        Where-Object { $includeExt -contains $_.Extension.ToLowerInvariant() } |
        ForEach-Object { [void]$files.Add($_.FullName) }
}
foreach ($f in $extraFiles) {
    if (Test-Path -LiteralPath $f) {
        [void]$files.Add($f)
    }
}

foreach ($file in ($files | Select-Object -Unique)) {
    $name = [IO.Path]::GetFileName($file)
    if ($excludeNameHints | Where-Object { $name -eq $_ }) {
        continue
    }

    $ext = [IO.Path]::GetExtension($file).ToLowerInvariant()
    $lines = Get-Content -LiteralPath $file -ErrorAction SilentlyContinue
    if (-not $lines) { continue }

    for ($i = 0; $i -lt $lines.Count; $i++) {
        $line = [string]$lines[$i]
        if (Test-IsCommentOrDocLine -Line $line -Ext $ext) {
            continue
        }

        foreach ($pat in $executablePatterns) {
            if ($line -match $pat.Regex) {
                $rel = $file.Substring($AppPath.Length).TrimStart('\', '/')
                [void]$violations.Add(("{0}:{1}: {2} => {3}" -f $rel, ($i + 1), $pat.Name, $line.Trim()))
            }
        }
    }
}

Write-Host 'Validacao runtime HTTP ERP (FrankenPHP obrigatorio)...' -ForegroundColor Cyan

if ($violations.Count -gt 0) {
    Write-Host 'FALHOU: referencias executaveis a artisan serve / php -S:' -ForegroundColor Red
    $violations | ForEach-Object { Write-Host "  - $_" -ForegroundColor Red }
    exit 1
}

# Binario FrankenPHP deve existir no repo de desenvolvimento/pacote.
$franken = Join-Path $AppPath 'tools\frankenphp\frankenphp.exe'
$template = Join-Path $AppPath 'tools\frankenphp\Caddyfile.template'
if (-not (Test-Path -LiteralPath $franken)) {
    Write-Host "FALHOU: ausente $franken" -ForegroundColor Red
    exit 1
}
if (-not (Test-Path -LiteralPath $template)) {
    Write-Host "FALHOU: ausente $template" -ForegroundColor Red
    exit 1
}

Write-Host 'OK: nenhum caminho executavel de artisan serve / php -S; FrankenPHP presente.' -ForegroundColor Green
exit 0
