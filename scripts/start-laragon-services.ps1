#Requires -Version 5.1

param(

    [string]$LaragonPath = 'C:\laragon',

    [string]$AppUrl = '',

    [string]$AppPath = '',

    [int]$WaitSeconds = 15

)



$ErrorActionPreference = 'Stop'

. (Join-Path $PSScriptRoot 'unitec-install-lib.ps1')



if ([string]::IsNullOrWhiteSpace($AppPath)) {

    $AppPath = Get-UnitecDefaultAppPath

}



if ([string]::IsNullOrWhiteSpace($AppUrl)) {

    $AppUrl = Get-UnitecDefaultAppUrl

}



Initialize-UnitecRuntime -AppPath $AppPath -AppUrl $AppUrl -LaragonPath $LaragonPath -WaitSeconds $WaitSeconds



Write-Host 'Dominio e servicos corrigidos.' -ForegroundColor Green

Write-Host "Acesse: $AppUrl" -ForegroundColor Green


