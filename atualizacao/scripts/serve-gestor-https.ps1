# HTTPS local para testar PWA no celular (Chrome exige origem segura).
# Uso: com o ERP já em http://0.0.0.0:8000, rode este script e abra https://SEU_IP:8443/gestor/

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
if (-not (Test-Path (Join-Path $root 'artisan'))) {
    $root = 'c:\Projetos\unitec-erp-web'
}
$certDir = Join-Path $root 'storage\certs'
$openssl = 'C:\Program Files\Git\usr\bin\openssl.exe'
$npx = 'C:\Program Files\nodejs\npx.cmd'
$key = Join-Path $certDir 'local.key'
$crt = Join-Path $certDir 'local.crt'
$cnf = Join-Path $certDir 'local.cnf'

New-Item -ItemType Directory -Force -Path $certDir | Out-Null

$lanIp = (Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue |
    Where-Object { $_.IPAddress -like '192.168.*' } |
    Select-Object -First 1 -ExpandProperty IPAddress)
if (-not $lanIp) { $lanIp = '127.0.0.1' }

$cnfText = @"
[req]
default_bits = 2048
prompt = no
default_md = sha256
req_extensions = req_ext
distinguished_name = dn
[dn]
CN = Unitec ERP Local
O = Unitec
C = BR
[req_ext]
subjectAltName = @alt_names
[alt_names]
DNS.1 = localhost
IP.1 = 127.0.0.1
IP.2 = $lanIp
"@
Set-Content -Path $cnf -Value $cnfText -Encoding ASCII

if (-not (Test-Path $key) -or -not (Test-Path $crt)) {
    & $openssl req -x509 -nodes -newkey rsa:2048 -keyout $key -out $crt -days 825 -config $cnf -extensions req_ext 2>$null
}

Get-NetTCPConnection -LocalPort 8443 -ErrorAction SilentlyContinue |
    Select-Object -ExpandProperty OwningProcess -Unique |
    ForEach-Object { Stop-Process -Id $_ -Force -ErrorAction SilentlyContinue }

Start-Process -FilePath $npx -ArgumentList @(
    '--yes', 'local-ssl-proxy',
    '--source', '8443',
    '--target', '8000',
    '--cert', $crt,
    '--key', $key
) -WorkingDirectory $root -WindowStyle Minimized

Write-Host ""
Write-Host "HTTPS ativo. No celular (mesma Wi-Fi) abra:"
Write-Host "  https://${lanIp}:8443/gestor/"
Write-Host ""
Write-Host "1) Aceite o aviso do certificado (Avançado → Continuar)"
Write-Host "2) Login → Mais → Instalar app"
Write-Host ""
