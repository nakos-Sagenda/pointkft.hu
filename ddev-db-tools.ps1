# ddev-db-tools.ps1

param (
    [Parameter(Mandatory = $true)]
    [ValidateSet("export", "import")]
    [string]$Action
)

# Mentés helye
$BackupDir = "db_dumps"
$DateStamp = Get-Date -Format "yyyy-MM-dd_HH-mm-ss"
$BackupFile = "$BackupDir\backup_$DateStamp.sql"

# Ellenőrizd, hogy ddev projektben vagy-e
if (-not (Test-Path ".ddev\config.yaml")) {
    Write-Error "Ez nem tűnik érvényes DDEV projektnek. Lépj be a projekt mappájába!"
    exit 1
}

# Létrehozza a mentési mappát, ha nincs
if (-not (Test-Path $BackupDir)) {
    New-Item -ItemType Directory -Path $BackupDir | Out-Null
}

if ($Action -eq "export") {
    Write-Host "`n💾 Adatbázis exportálása: $BackupFile" -ForegroundColor Cyan
    ddev export-db > $BackupFile
    Write-Host "✅ Export kész: $BackupFile" -ForegroundColor Green
}
elseif ($Action -eq "import") {
    # Legutóbbi mentés fájl kiválasztása
    $LastBackup = Get-ChildItem "$BackupDir\*.sql" | Sort-Object LastWriteTime -Descending | Select-Object -First 1

    if (-not $LastBackup) {
        Write-Error "❌ Nincs elérhető mentés a '$BackupDir' mappában."
        exit 1
    }

    Write-Host "`n♻️  Adatbázis visszatöltése: $($LastBackup.FullName)" -ForegroundColor Yellow
    ddev import-db --file="$($LastBackup.FullName)"
    Write-Host "✅ Visszatöltés kész!" -ForegroundColor Green
}
