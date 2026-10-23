# Empaqueta descargas\ en varios .zip (cada uno de maximo 400 MB) para subirlos por el Administrador
# de archivos de Hostinger. Al extraerlos dentro de la carpeta privada se recrea  descargas\...
# Uso:  powershell -ExecutionPolicy Bypass -File tools\pack-for-upload.ps1
# Salida: carpeta  subir\  (no se sube a GitHub).
$ErrorActionPreference = "Stop"
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$MaxMB = 400
$root = Split-Path $PSScriptRoot -Parent
$src = Join-Path $root "descargas"
$outDir = Join-Path $root "subir"
if (-not (Test-Path -LiteralPath $src)) { throw "No existe descargas\. Ejecuta primero tools\build-catalog.ps1" }
New-Item -ItemType Directory -Force -Path $outDir | Out-Null
Get-ChildItem -LiteralPath $outDir -Filter "*.zip" -File | ForEach-Object { [System.IO.File]::Delete($_.FullName) }

$files = Get-ChildItem -LiteralPath $src -Recurse -File | Sort-Object FullName
$limit = $MaxMB * 1MB
$packs = New-Object System.Collections.Generic.List[object]
$cur = New-Object System.Collections.Generic.List[object]; $curSize = 0
foreach ($f in $files) {
  if ($cur.Count -gt 0 -and ($curSize + $f.Length) -gt $limit) { $packs.Add($cur); $cur = New-Object System.Collections.Generic.List[object]; $curSize = 0 }
  $cur.Add($f); $curSize += $f.Length
}
if ($cur.Count -gt 0) { $packs.Add($cur) }

for ($i = 0; $i -lt $packs.Count; $i++) {
  $name = "mixmix-descargas-parte-" + ($i + 1) + "-de-" + $packs.Count + ".zip"
  $zp = Join-Path $outDir $name
  $fs = [System.IO.File]::Open($zp, [System.IO.FileMode]::Create)
  $zip = New-Object System.IO.Compression.ZipArchive($fs, [System.IO.Compression.ZipArchiveMode]::Create)
  foreach ($f in $packs[$i]) {
    $rel = $f.FullName.Substring($root.Length + 1).Replace("\", "/")     # descargas/manuales/xxx.pdf
    [void][System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $f.FullName, $rel, [System.IO.Compression.CompressionLevel]::NoCompression)
  }
  $zip.Dispose(); $fs.Dispose()
  $mb = [math]::Round((Get-Item -LiteralPath $zp).Length / 1MB, 1)
  Write-Host ($name + "  " + @($packs[$i]).Count + " archivos  " + $mb + " MB")
}
Write-Host ("Listo: " + $packs.Count + " paquetes en " + $outDir)
