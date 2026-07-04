#Requires -Version 5.1

<#
.SYNOPSIS
    Extrai PHP + MariaDB em tools\ e inicia MySQL.
#>

param(
    [string]$AppPath = '',
    [string]$SourceRoot = '',
    [int]$ServiceWaitSeconds = 20,
    [switch]$SkipMysql
)

$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

. (Join-Path $PSScriptRoot 'unitec-install-lib.ps1')

Write-Title 'Pre-requisitos Unitec ERP'

if ([string]::IsNullOrWhiteSpace($AppPath)) {
    $AppPath = $SourceRoot
}

if ([string]::IsNullOrWhiteSpace($AppPath)) {
    throw 'Caminho da aplicacao nao informado.'
}

$AppPath = Resolve-UnitecAppPath -Path $AppPath

Write-Title 'Instalando PHP e MariaDB (runtime embutido)'
Ensure-UnitecRuntimeInstalled -AppPath $AppPath -SourceRoot $AppPath -SkipMysql:$SkipMysql
Initialize-UnitecRuntimePath -AppPath $AppPath

if (-not $SkipMysql) {
    Write-Title 'Iniciando MariaDB'
    $mysqlOk = Ensure-LaragonMysqlRunning -AppPath $AppPath -MaxWaitSeconds $ServiceWaitSeconds -ThrowOnFailure
    if (-not $mysqlOk) {
        throw 'MariaDB nao iniciou durante a instalacao dos pre-requisitos.'
    }
    Write-Ok 'MySQL pronto.'
} else {
    Write-Ok 'Terminal remoto — MariaDB local nao sera instalado; conexao ao servidor sera testada via PHP (pdo_mysql).'
}

Write-Ok 'Pre-requisitos concluidos.'
