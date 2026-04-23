$xamppPath = if ($env:XAMPP_HOME) { $env:XAMPP_HOME } else { 'C:\xampp' }
$startScript = Join-Path $xamppPath 'mysql_start.bat'

if (Get-Process -Name 'mysqld' -ErrorAction SilentlyContinue) {
    Write-Host 'MySQL already running.'
    exit 0
}

if (-not (Test-Path $startScript)) {
    Write-Warning "Skipped MySQL auto-start. Set XAMPP_HOME or place XAMPP at $xamppPath."
    exit 0
}

Start-Process -FilePath $startScript -WindowStyle Hidden
Start-Sleep -Seconds 2

if (Get-Process -Name 'mysqld' -ErrorAction SilentlyContinue) {
    Write-Host "Started MySQL from $startScript."
    exit 0
}

Write-Warning "Tried to start MySQL from $startScript, but mysqld is still not running."
exit 0
