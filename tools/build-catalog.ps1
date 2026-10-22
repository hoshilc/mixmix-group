# Regenera descargas/ (PDF y zips) y js/catalog.js desde la carpeta oficial de documentos.
# Uso:  powershell -ExecutionPolicy Bypass -File tools\build-catalog.ps1 [-SrcRoot "C:\ruta\Descargables  MIXMIX 2026"]
# Los precios, titulos, categorias y paquetes se editan en tools\catalog-config.json.
param([string]$SrcRoot = "")
$ErrorActionPreference = "Stop"
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

if (-not $SrcRoot) { $SrcRoot = Join-Path $env:USERPROFILE "Downloads\Descargables  MIXMIX 2026" }
$srcRoot = $SrcRoot
$proj = Split-Path $PSScriptRoot -Parent
$out = Join-Path $proj "descargas"
$cfg = [System.IO.File]::ReadAllText((Join-Path $PSScriptRoot "catalog-config.json"), [System.Text.Encoding]::UTF8) | ConvertFrom-Json

function StripAccents($t) {
  $n = $t.Normalize([System.Text.NormalizationForm]::FormD)
  $sb = New-Object System.Text.StringBuilder
  foreach ($ch in $n.ToCharArray()) {
    if ([System.Globalization.CharUnicodeInfo]::GetUnicodeCategory($ch) -ne [System.Globalization.UnicodeCategory]::NonSpacingMark) { [void]$sb.Append($ch) }
  }
  return $sb.ToString()
}
function Slugify($t) {
  $s = (StripAccents $t).ToLowerInvariant()
  $s = [regex]::Replace($s, '[^a-z0-9]+', '-').Trim([char]'-')
  if ($s.Length -gt 42) {
    $cut = $s.Substring(0, 42)
    $i = $cut.LastIndexOf('-')
    if ($i -gt 20) { $cut = $cut.Substring(0, $i) }
    $s = $cut.Trim([char]'-')
  }
  return $s
}
function ApplyAccents($t) {
  foreach ($p in $cfg.accents.PSObject.Properties) {
    $t = [regex]::Replace($t, '\b' + [regex]::Escape($p.Name) + '\b', $p.Value)
  }
  foreach ($p in $cfg.fixes.PSObject.Properties) { $t = $t.Replace($p.Name, $p.Value) }
  return $t
}
# nombre de archivo al descargar / dentro de los zip: "MixMix - Titulo.pdf" (sin repetir si ya empieza con Mix)
function ZipName($t) {
  $n = SafeEntry $t
  if ($n -notmatch '(?i)mix') { $n = "MixMix - " + $n }
  return $n + ".pdf"
}
function CleanShort($name) {
  $t = [System.IO.Path]::GetFileNameWithoutExtension($name)
  $t = [regex]::Replace($t, '^\d+\s*-\s*', '')
  $t = $t.Replace(' (Mix Mix)', '').Replace('(Mix Mix)', '').Trim()
  $t = [regex]::Replace($t, '^(Descripcion de Puesto|Formato Maestro|Formato|Checklist|Cuadro de Mando|Mystery Shopper)\s*-\s*', '')
  return (ApplyAccents $t.Trim())
}
function Md5($p) { return (Get-FileHash -LiteralPath $p -Algorithm MD5).Hash }
function SafeEntry($t) {
  $s = $t
  $s = [regex]::Replace($s, '[\\/:*?"<>|]+', ' - ')
  $s = [regex]::Replace($s, '\s+', ' ').Trim()
  return $s
}

$items = New-Object System.Collections.Generic.List[object]
$usedDst = @{}
function AddItem($cat, $group, $title, $price, $slug, $srcPath, $zipDir) {
  $dir = Join-Path $out $cat
  New-Item -ItemType Directory -Force -Path $dir | Out-Null
  $base = $slug; $k = 2
  $dst = Join-Path $dir ($slug + ".pdf")
  while ($script:usedDst.ContainsKey($dst.ToLowerInvariant())) { $slug = "$base-$k"; $k++; $dst = Join-Path $dir ($slug + ".pdf") }
  $script:usedDst[$dst.ToLowerInvariant()] = $true
  $same = (Test-Path -LiteralPath $dst) -and ((Get-Item -LiteralPath $dst).Length -eq (Get-Item -LiteralPath $srcPath).Length)
  if (-not $same) { Copy-Item -LiteralPath $srcPath -Destination $dst -Force }
  $script:items.Add([PSCustomObject]@{
    id = ("{0}-{1}" -f $cat, $slug); cat = $cat; g = $group; title = $title; price = $price
    file = ("descargas/{0}/{1}.pdf" -f $cat, $slug); stripe = ""
    _src = $dst; _zip = $zipDir; _files = @(@{ t = $title; src = $dst })
  })
}
$PR = $cfg.prices
$pManual = [int]$PR.manual
$pCorto = [int]$PR.corto

# ---------- MANUALES ----------
$dirM = Get-ChildItem -LiteralPath $srcRoot -Directory | Where-Object { $_.Name -match '^Manuales' }
$man = Get-ChildItem -LiteralPath $dirM.FullName -File | ForEach-Object {
  $n = [regex]::Match($_.Name, '^(\d+)_').Groups[1].Value
  [PSCustomObject]@{ Num = [int]$n; File = $_ }
} | Sort-Object Num
foreach ($m in $man) {
  $e = $cfg.manuales.PSObject.Properties[[string]$m.Num]
  if (-not $e) { Write-Host ("SIN CONFIG para manual " + $m.Num + ": " + $m.File.Name); continue }
  AddItem "manuales" $e.Value.g $e.Value.t $pManual (Slugify $e.Value.t) $m.File.FullName "Manuales"
}

# ---------- DESCRIPCIONES DE PUESTO (incluye Secuencia de Servicio, como en la carpeta oficial) ----------
$dirP = Get-ChildItem -LiteralPath $srcRoot -Directory | Where-Object { $_.Name -match '^Descripciones' }
$seen = @{}
foreach ($area in (Get-ChildItem -LiteralPath $dirP.FullName -Directory | Sort-Object Name)) {
  $aid = switch -regex ($area.Name) { '^Sal' { "salon" } '^Cocina' { "cocina" } '^Bar' { "bar" } '^Admin' { "admin" } '^Alma' { "admin" } '^Mark' { "mkt" } '^RRHH' { "rrhh" } default { "salon" } }
  foreach ($f in (Get-ChildItem -LiteralPath $area.FullName -File | Sort-Object Name)) {
    $h = Md5 $f.FullName
    if ($seen.ContainsKey($h)) { continue }
    $seen[$h] = $true
    if ($f.Name -match '^Secuencia de Servicio') {
      AddItem "puestos" $aid "Secuencia de Servicio: Meseros" $pCorto "secuencia-de-servicio-meseros" $f.FullName "Descripciones de puesto"
      continue
    }
    $t = CleanShort $f.Name
    AddItem "puestos" $aid $t $pCorto (Slugify $t) $f.FullName "Descripciones de puesto"
  }
}

# ---------- CONTROLES IMPRIMIBLES ----------
$dirC = Get-ChildItem -LiteralPath $srcRoot -Directory | Where-Object { $_.Name -match '^Controles' }
foreach ($f in (Get-ChildItem -LiteralPath $dirC.FullName -File | Sort-Object Name)) {
  $t = CleanShort $f.Name
  AddItem "controles" "" $t $pCorto (Slugify $t) $f.FullName "Controles imprimibles"
}

# ---------- KPIs ----------
$dirK = Get-ChildItem -LiteralPath $srcRoot -Directory | Where-Object { $_.Name -match '^KPIs' }
$kpiHash = @{}
foreach ($f in (Get-ChildItem -LiteralPath $dirK.FullName -File | Sort-Object Name)) {
  $kpiHash[(Md5 $f.FullName)] = $true
  $t = CleanShort $f.Name
  AddItem "kpis" "" ("Cuadro de Mando: " + $t) $pCorto (Slugify $t) $f.FullName "KPIs de gerencia"
}

# ---------- MYSTERY SHOPPER ----------
$dirY = Get-ChildItem -LiteralPath $srcRoot -Directory | Where-Object { $_.Name -match '^Mystery' }
foreach ($f in (Get-ChildItem -LiteralPath $dirY.FullName -File | Sort-Object Name)) {
  if ($kpiHash.ContainsKey((Md5 $f.FullName))) { Write-Host ("OMITIDO (Cuadro de Mando repetido): " + $f.Name); continue }
  $t = CleanShort $f.Name
  AddItem "mystery" "" ("Mystery Shopper: " + $t) $pCorto (Slugify $t) $f.FullName "Mystery Shopper"
}

# ---------- JUEGOS ----------
$dirJ = Get-ChildItem -LiteralPath $srcRoot -Directory | Where-Object { $_.Name -match '^Juegos' }
$jFiles = @(Get-ChildItem -LiteralPath $dirJ.FullName -Recurse -File | Sort-Object Name)
Write-Host ("Juegos en carpeta oficial: " + $jFiles.Count)
$usedJ = @{}
foreach ($g in $cfg.games) {
  $gfiles = @()
  foreach ($m in $g.files) {
    $f = $jFiles | Where-Object { $_.Name.Contains($m) } | Select-Object -First 1
    if (-not $f) { Write-Host ("JUEGO " + $g.id + ": no se encontro archivo para '" + $m + "'"); continue }
    $usedJ[$f.FullName] = $true
    $ft = $f.Name.Substring(0, $f.Name.Length - $f.Extension.Length).Replace(" (Mix Mix)", "").Trim()
    $slug = Slugify $ft
    $dir = Join-Path $out "juegos"
    New-Item -ItemType Directory -Force -Path $dir | Out-Null
    $dst = Join-Path $dir ($slug + ".pdf")
    $usedDst[$dst.ToLowerInvariant()] = $true
    $same = (Test-Path -LiteralPath $dst) -and ((Get-Item -LiteralPath $dst).Length -eq $f.Length)
    if (-not $same) { Copy-Item -LiteralPath $f.FullName -Destination $dst -Force }
    $gfiles += @{ t = $ft; src = $dst; web = ("descargas/juegos/" + $slug + ".pdf") }
  }
  $web = @($gfiles | ForEach-Object { [PSCustomObject]@{ t = $_.t; file = $_.web } })
  $one = ""
  if ($gfiles.Count -eq 1) { $one = $gfiles[0].web }
  $items.Add([PSCustomObject]@{
    id = ("juegos-" + $g.id); cat = "juegos"; g = ""; title = $g.title; price = [int]$g.price
    file = $one; files = $web; desc = $g.desc; stripe = ""
    _src = $gfiles[0].src; _zip = "Juegos"; _files = $gfiles
  })
}
foreach ($f in $jFiles) { if (-not $usedJ.ContainsKey($f.FullName)) { Write-Host ("JUEGO SIN ASIGNAR: " + $f.Name) } }

# ---------- borrar archivos viejos que ya no pertenecen al catalogo ----------
foreach ($c in @("manuales", "puestos", "controles", "kpis", "mystery", "juegos")) {
  $d = Join-Path $out $c
  if (Test-Path -LiteralPath $d) {
    Get-ChildItem -LiteralPath $d -File | ForEach-Object {
      if (-not $usedDst.ContainsKey($_.FullName.ToLowerInvariant())) { Write-Host ("borrando sobrante: " + $_.Name); [System.IO.File]::Delete($_.FullName) }
    }
  }
}

# ---------- ZIPS (ninguno pasa de 450 MB: si un grupo pesa mas, se parte en -1, -2...) ----------
$MaxZipMB = 450
function New-Zip($zipPath, $list) {
  if (Test-Path -LiteralPath $zipPath) { [System.IO.File]::Delete($zipPath) }
  $fs = [System.IO.File]::Open($zipPath, [System.IO.FileMode]::Create)
  $zip = New-Object System.IO.Compression.ZipArchive($fs, [System.IO.Compression.ZipArchiveMode]::Create)
  foreach ($it in $list) {
    foreach ($fl in $it._files) {
      $name = ZipName $fl.t
      if ($it._zip) { $name = $it._zip + "/" + $name }
      [void][System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $fl.src, $name, [System.IO.Compression.CompressionLevel]::NoCompression)
    }
  }
  $zip.Dispose(); $fs.Dispose()
}
# crea uno o varios zips para $list; devuelve @{ t; file } por cada parte
function New-ZipParts($baseName, $title, $list) {
  $limit = $MaxZipMB * 1MB
  $parts = New-Object System.Collections.Generic.List[object]
  $cur = New-Object System.Collections.Generic.List[object]; $curSize = 0
  foreach ($it in $list) {
    $sz = 0; foreach ($fl in $it._files) { $sz += (Get-Item -LiteralPath $fl.src).Length }
    if ($cur.Count -gt 0 -and ($curSize + $sz) -gt $limit) { $parts.Add($cur); $cur = New-Object System.Collections.Generic.List[object]; $curSize = 0 }
    $cur.Add($it); $curSize += $sz
  }
  if ($cur.Count -gt 0) { $parts.Add($cur) }
  $outParts = @()
  for ($i = 0; $i -lt $parts.Count; $i++) {
    $nm = if ($parts.Count -eq 1) { $baseName + ".zip" } else { $baseName + "-" + ($i + 1) + ".zip" }
    $ti = if ($parts.Count -eq 1) { $title } else { $title + " (parte " + ($i + 1) + " de " + $parts.Count + ")" }
    New-Zip (Join-Path $out $nm) @($parts[$i])
    $mb = [math]::Round((Get-Item -LiteralPath (Join-Path $out $nm)).Length / 1MB, 1)
    Write-Host ("zip " + $nm + " -> " + @($parts[$i]).Count + " documentos, " + $mb + " MB")
    $outParts += [PSCustomObject]@{ t = $ti; file = ("descargas/" + $nm) }
  }
  return $outParts
}
# zips que ya no se venden o que se reemplazaron por zips por categoria
foreach ($old in @("kit-1000.zip", "kit-360.zip", "manuales.zip")) {
  $op = Join-Path $out $old
  if (Test-Path -LiteralPath $op) { [System.IO.File]::Delete($op); Write-Host ("borrado zip antiguo: " + $old) }
}
# y los zips por categoria/tipo anteriores (se rehacen completos)
Get-ChildItem -LiteralPath $out -Filter "*.zip" -File -ErrorAction SilentlyContinue | ForEach-Object { [System.IO.File]::Delete($_.FullName) }

$bundleOut = New-Object System.Collections.Generic.List[object]
$zipsCat = @()

# Manuales: un zip por categoria (cada uno muy por debajo del limite)
foreach ($t in $cfg.themes) {
  $list = @($items | Where-Object { $_.cat -eq "manuales" -and $_.g -eq $t.id })
  if ($list.Count -eq 0) { continue }
  $zipsCat += @(New-ZipParts ("manuales-" + $t.id) ("Manuales: " + $t.name) $list)
}

# Tipos de documento: un zip por tipo (puestos, controles, kpis, mystery, juegos)
$typeZips = @(
  @{ id = "bundle-puestos";   title = "Paquete: Descripciones de Puesto"; cat = "puestos";   zip = "puestos";   label = "Descripciones de Puesto" },
  @{ id = "bundle-controles"; title = "Paquete: Controles Imprimibles";   cat = "controles"; zip = "controles"; label = "Controles Imprimibles" },
  @{ id = "bundle-kpis";      title = "Paquete: KPIs de Gerencia";        cat = "kpis";      zip = "kpis";      label = "KPIs de Gerencia" },
  @{ id = "bundle-mystery";   title = "Paquete: Mystery Shopper";         cat = "mystery";   zip = "mystery";   label = "Mystery Shopper" },
  @{ id = "bundle-juegos";    title = "Paquete: Juegos MixMix";           cat = "juegos";    zip = "juegos";    label = "Juegos MixMix" }
)
$typeParts = @{}
foreach ($z in $typeZips) {
  $list = @($items | Where-Object { $_.cat -eq $z.cat })
  $parts = @(New-ZipParts $z.zip $z.label $list)
  $typeParts[$z.id] = $parts
  if ($z.cat -ne "juegos") { $zipsCat += $parts }
}

# Catalogo completo = ancla: manuales + puestos + controles + kpis + mystery (sin juegos); se entrega en varios zips
$bundleOut.Add([PSCustomObject]@{ id = "catalogo"; title = ("Cat" + [char]0x00E1 + "logo completo"); cats = @("manuales","puestos","controles","kpis","mystery"); price = [int]$PR.catalogo; zips = @($zipsCat); stripe = "" })
foreach ($z in $typeZips) {
  $bp = if ($z.cat -eq "juegos") { [int]$PR.juegos } else { [int]$PR.types.($z.cat) }
  $pp = @($typeParts[$z.id])
  if ($pp.Count -eq 1) { $bundleOut.Add([PSCustomObject]@{ id = $z.id; title = $z.title; cats = @($z.cat); price = $bp; file = $pp[0].file; stripe = "" }) }
  else { $bundleOut.Add([PSCustomObject]@{ id = $z.id; title = $z.title; cats = @($z.cat); price = $bp; zips = $pp; stripe = "" }) }
}
# comprobacion final: ningun zip debe pasar de 500 MB
$big = Get-ChildItem -LiteralPath $out -Filter "*.zip" -File | Where-Object { $_.Length -gt 500MB }
if ($big) { throw ("Hay zips de mas de 500 MB: " + (($big | ForEach-Object { $_.Name }) -join ", ")) }
$maxZip = (Get-ChildItem -LiteralPath $out -Filter "*.zip" -File | Measure-Object Length -Maximum).Maximum
Write-Host ("zip mas grande: " + [math]::Round($maxZip / 1MB, 1) + " MB (limite 500 MB)")
# una categoria de manuales = un paquete (precio de la tabla, sin zip)
foreach ($t in $cfg.themes) {
  $ids = @($items | Where-Object { $_.cat -eq "manuales" -and $_.g -eq $t.id } | ForEach-Object { $_.id })
  $bundleOut.Add([PSCustomObject]@{ id = ("bundle-m-" + $t.id); title = ("Categor" + [char]0x00ED + "a: " + $t.name); theme = $t.id; items = $ids; price = [int]$PR.themes.($t.id); stripe = "" })
}

# ---------- PAQUETES POR PERFIL (sin zip: se entregan archivo por archivo) ----------
function SelectPart($part) {
  $r = $items | Where-Object { $_.cat -eq $part.cat }
  if ($part.g) { $r = $r | Where-Object { $_.g -eq $part.g } }
  if ($part.titles) { $r = $r | Where-Object { $part.titles -contains $_.title } }
  if ($part.exceptTitles) { $r = $r | Where-Object { $part.exceptTitles -notcontains $_.title } }
  return @($r)
}
$pkgOut = New-Object System.Collections.Generic.List[object]
foreach ($p in $cfg.packages) {
  $base = New-Object System.Collections.Generic.List[string]
  foreach ($part in $p.parts) {
    $sel = SelectPart $part
    if (@($sel).Count -eq 0) { Write-Host ("PAQUETE " + $p.id + ": una parte no encontro archivos") }
    foreach ($s in $sel) { if (-not $base.Contains($s.id)) { $base.Add($s.id) } }
  }
  if ($p.choose) {
    $giros = SelectPart $p.choose
    $variantIds = @()
    foreach ($gi in $giros) {
      $vid = $p.id + "-" + ($gi.id -replace '^manuales-', '')
      $variantIds += $vid
      $ids = @($base) + @($gi.id)
      $pkgOut.Add([PSCustomObject]@{ id = $vid; title = ($p.title + ": " + $gi.title); price = $p.price; items = $ids; stripe = ""; parent = $p.id; giro = $gi.id })
    }
    $pkgOut.Add([PSCustomObject]@{ id = $p.id; title = $p.title; who = $p.who; price = $p.price; items = @($base); stripe = ""; choose = $variantIds })
  } else {
    $pkgOut.Add([PSCustomObject]@{ id = $p.id; title = $p.title; who = $p.who; price = $p.price; items = @($base); stripe = "" })
  }
}

# ---------- catalog.js ----------
$cats = @(
  [PSCustomObject]@{ id = "manuales";  name = ("Manuales T" + [char]0x00E1 + "cticos"); unit = "manuales";          price = $pManual; soon = $false },
  [PSCustomObject]@{ id = "puestos";   name = "Descripciones de Puesto";     unit = "documentos";        price = $pCorto; soon = $false },
  [PSCustomObject]@{ id = "controles"; name = "Controles Imprimibles";       unit = "documentos";        price = $pCorto; soon = $false },
  [PSCustomObject]@{ id = "kpis";      name = "KPIs de Gerencia";            unit = "cuadros de mando";  price = $pCorto; soon = $false },
  [PSCustomObject]@{ id = "mystery";   name = "Mystery Shopper";             unit = "formatos";          price = $pCorto; soon = $false },
  [PSCustomObject]@{ id = "juegos";    name = "Juegos MixMix";               unit = "juegos";            price = 0;  soon = $false }
)
$clean = $items | ForEach-Object {
  $o = [ordered]@{ id = $_.id; cat = $_.cat; g = $_.g; title = $_.title; price = $_.price; file = $_.file }
  if ($_.files) { $o.files = @($_.files); $o.desc = $_.desc }
  $o.stripe = $_.stripe
  [PSCustomObject]$o
}
$sb = New-Object System.Text.StringBuilder
[void]$sb.AppendLine("/* Catalogo MixMix - generado desde 'Descargables  MIXMIX 2026'.")
[void]$sb.AppendLine("   Para activar Stripe en un producto, pega su Payment Link en el campo ""stripe"" de ese producto. */")
[void]$sb.AppendLine("window.MM_CATALOG = {")
[void]$sb.AppendLine("  ""themes"": " + (ConvertTo-Json -InputObject @($cfg.themes) -Compress) + ",")
[void]$sb.AppendLine("  ""areas"": " + (ConvertTo-Json -InputObject @($cfg.areas) -Compress) + ",")
[void]$sb.AppendLine("  ""categories"": " + (ConvertTo-Json -InputObject @($cats) -Compress) + ",")
[void]$sb.AppendLine("  ""volume"": " + (ConvertTo-Json -InputObject $cfg.prices.volume -Compress -Depth 5) + ",")
[void]$sb.AppendLine("  ""bundles"": [")
[void]$sb.AppendLine((($bundleOut | ForEach-Object { "    " + (ConvertTo-Json -InputObject $_ -Compress -Depth 5) }) -join ",`n"))
[void]$sb.AppendLine("  ],")
[void]$sb.AppendLine("  ""packages"": [")
[void]$sb.AppendLine((($pkgOut | ForEach-Object { "    " + (ConvertTo-Json -InputObject $_ -Compress -Depth 4) }) -join ",`n"))
[void]$sb.AppendLine("  ],")
[void]$sb.AppendLine("  ""items"": [")
[void]$sb.AppendLine((($clean | ForEach-Object { "    " + (ConvertTo-Json -InputObject $_ -Compress -Depth 5) }) -join ",`n"))
[void]$sb.AppendLine("  ]")
[void]$sb.AppendLine("};")
New-Item -ItemType Directory -Force -Path (Join-Path $proj "js") | Out-Null
[System.IO.File]::WriteAllText((Join-Path $proj "js\catalog.js"), $sb.ToString(), (New-Object System.Text.UTF8Encoding($false)))

Write-Host ""
Write-Host "RESUMEN"
$items | Group-Object cat | ForEach-Object { Write-Host ("  {0}: {1}" -f $_.Name, $_.Count) }
Write-Host ("  TOTAL: " + $items.Count)
foreach ($p in $pkgOut) {
  if ($p.parent) { continue }
  $sum = 0
  foreach ($id in $p.items) { $sum += ($items | Where-Object { $_.id -eq $id } | Select-Object -First 1).price }
  $extra = ""
  if ($p.choose) { $extra = " (+1 giro a elegir, " + @($p.choose).Count + " variantes)" }
  Write-Host ("  {0}: {1} docs base, suelto base `${2} -> paquete `${3}{4}" -f $p.id, @($p.items).Count, $sum, $p.price, $extra)
}
$maxLen = ($items | ForEach-Object { $_._src.Length } | Measure-Object -Maximum).Maximum
Write-Host ("Ruta mas larga (caracteres): " + $maxLen)
