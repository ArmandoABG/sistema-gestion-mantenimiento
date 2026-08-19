$ErrorActionPreference = 'Stop'
$Raiz = Split-Path -Parent $PSScriptRoot

Write-Host "Validando PHP..." -ForegroundColor Cyan
$ArchivosPhp = Get-ChildItem -Path $Raiz -Recurse -File -Filter *.php
foreach ($Archivo in $ArchivosPhp) {
    & php -l $Archivo.FullName | Out-Null
    if ($LASTEXITCODE -ne 0) {
        throw "Error de sintaxis PHP en: $($Archivo.FullName)"
    }
}

Write-Host "Buscando dependencias externas de alertas..." -ForegroundColor Cyan
$Coincidencias = Get-ChildItem -Path $Raiz -Recurse -File |
    Where-Object { $_.Extension -in '.php', '.js', '.css', '.html' } |
    Select-String -Pattern 'cdn\.jsdelivr.*sweetalert|sweetalert2@|unpkg.*sweetalert'

if ($Coincidencias) {
    $Coincidencias | ForEach-Object { Write-Host $_ -ForegroundColor Red }
    throw 'Todavía existen referencias externas de SweetAlert.'
}

$Requeridos = @(
    'css\sistema-maestro.css',
    'js\sistema-ui.js',
    'inc\alertas.php'
)
foreach ($Relativo in $Requeridos) {
    $Ruta = Join-Path $Raiz $Relativo
    if (-not (Test-Path $Ruta)) {
        throw "Falta el recurso requerido: $Relativo"
    }
}

Write-Host "Validación terminada correctamente." -ForegroundColor Green
Write-Host "Archivos PHP revisados: $($ArchivosPhp.Count)" -ForegroundColor Green
