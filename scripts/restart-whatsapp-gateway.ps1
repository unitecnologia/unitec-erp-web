#Requires -Version 5.1
<#
.SYNOPSIS
    Reinicia o gateway WhatsApp interno (Node/Baileys na porta 8091).

.DESCRIPTION
    O dev-windows.ps1 nao reinicia o gateway se a porta 8091 ja estiver em uso.
    Use este script apos alterar services/erp-whatsapp-gateway/index.js.

.EXAMPLE
    .\scripts\restart-whatsapp-gateway.ps1
#>

$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

Set-Location (Join-Path $PSScriptRoot '..')
$AppPath = (Get-Location).Path

. (Join-Path $PSScriptRoot 'unitec-install-lib.ps1')

$ok = Restart-UnitecWhatsAppGateway -AppPath $AppPath

if (-not $ok) {
    exit 1
}

exit 0
