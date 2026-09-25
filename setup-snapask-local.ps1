# Dựng tên miền http://snapask.local cho môi trường dev.
#
# Chạy bằng PowerShell **quyền Administrator**: sửa file hosts và khởi động lại
# Apache đều cần quyền đó.
#
#   Bấm Start > gõ "powershell" > chuột phải > Run as administrator
#   cd C:\xampp\htdocs\snapask
#   .\setup-snapask-local.ps1

$ErrorActionPreference = 'Stop'

$identity = [Security.Principal.WindowsIdentity]::GetCurrent()
$principal = New-Object Security.Principal.WindowsPrincipal($identity)

if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    Write-Host "Script nay can quyen Administrator." -ForegroundColor Red
    Write-Host "Mo lai PowerShell bang 'Run as administrator' roi chay lai."
    exit 1
}

# --- 1. hosts ---
$hosts = "$env:SystemRoot\System32\drivers\etc\hosts"
$line = "127.0.0.1 snapask.local"

if (Select-String -Path $hosts -Pattern '^\s*127\.0\.0\.1\s+snapask\.local\s*$' -Quiet) {
    Write-Host "hosts: da co snapask.local" -ForegroundColor Yellow
} else {
    Copy-Item $hosts "$hosts.bak-$(Get-Date -Format yyyyMMdd-HHmmss)"
    Add-Content -Path $hosts -Value $line -Encoding ASCII
    Write-Host "hosts: da them $line" -ForegroundColor Green
}

# --- 2. vhost (da them san, chi kiem tra) ---
$vhosts = 'C:\xampp\apache\conf\extra\httpd-vhosts.conf'

if (Select-String -Path $vhosts -Pattern 'ServerName\s+snapask\.local' -Quiet) {
    Write-Host "vhost: da co snapask.local" -ForegroundColor Yellow
} else {
    Write-Host "vhost: THIEU cau hinh snapask.local trong $vhosts" -ForegroundColor Red
    exit 1
}

# --- 3. kiem tra cu phap truoc khi khoi dong lai ---
$check = & 'C:\xampp\apache\bin\httpd.exe' -t 2>&1

if ($check -notmatch 'Syntax OK') {
    Write-Host "Apache config loi, khong khoi dong lai:" -ForegroundColor Red
    Write-Host $check
    exit 1
}

Write-Host "apache: Syntax OK" -ForegroundColor Green

# --- 4. khoi dong lai Apache ---
Get-Process -Name httpd -ErrorAction SilentlyContinue | Stop-Process -Force
Start-Sleep -Seconds 3
Start-Process -FilePath 'C:\xampp\apache\bin\httpd.exe' -WorkingDirectory 'C:\xampp\apache' -WindowStyle Hidden
Start-Sleep -Seconds 5

if (Get-Process -Name httpd -ErrorAction SilentlyContinue) {
    Write-Host "apache: da khoi dong lai" -ForegroundColor Green
} else {
    Write-Host "apache: khong len duoc, mo XAMPP Control Panel bam Start" -ForegroundColor Red
    exit 1
}

# --- 5. thu that ---
try {
    $res = Invoke-WebRequest -Uri 'http://snapask.local/login' -UseBasicParsing -TimeoutSec 10
    Write-Host ""
    Write-Host "http://snapask.local  ->  HTTP $($res.StatusCode)" -ForegroundColor Green
    Write-Host "Dang nhap: qa@1office.vn / snapask123"
} catch {
    Write-Host "Chua goi duoc http://snapask.local — $($_.Exception.Message)" -ForegroundColor Red
}
