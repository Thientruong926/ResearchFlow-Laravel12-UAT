# Chay tren Windows PowerShell SAU KHI da tao .env rieng, composer install va PHP co SQLite test driver.
# KHONG tu dong migrate/seed hoac ghi vao co so du lieu live.
$ErrorActionPreference = 'Stop'
if (-not (Test-Path '.env')) { throw 'Thieu .env. Tao tu .env.example va cau hinh DB thu rieng.' }
$settings = Get-Content '.env'
foreach ($key in @('RESEARCH_COLLECTION_OPEN','RESEARCH_MINOR_FLOW','RESEARCH_AUDIO_APPROVED')) {
    $line = @($settings | Where-Object { $_ -match "^$key=" } | Select-Object -First 1)
    if ($line.Count -ne 1 -or $line[0] -ne "$key=false") { throw "An toan: $key phai false truoc khi kiem thu." }
}
Write-Host 'PASS: 3 co an toan dang false'
php -v
composer check-platform-reqs
if ($LASTEXITCODE -ne 0) { throw 'Composer platform requirements failed.' }
php artisan route:list
if ($LASTEXITCODE -ne 0) { throw 'Route-list failed.' }
# PHPUnit xml phai dung sqlite :memory:. Kiem tra de tranh tac dong DB thuc.
[xml]$xml = Get-Content 'phpunit.xml'
$db = $xml.phpunit.php.env | Where-Object { $_.name -eq 'DB_CONNECTION' }
$database = $xml.phpunit.php.env | Where-Object { $_.name -eq 'DB_DATABASE' }
if ($db.value -ne 'sqlite' -or $database.value -ne ':memory:') { throw 'Tests must use sqlite in-memory database.' }
php artisan test
if ($LASTEXITCODE -ne 0) { throw 'Laravel test suite failed.' }
Write-Host 'PASS: Commands completed. PHAN UAT THUC TE VAN CAN THUC HIEN.'
