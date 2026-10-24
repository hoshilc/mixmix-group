# Genera las partes compartidas de todas las paginas .html y los archivos de SEO.
# Uso (desde cualquier carpeta):  powershell -ExecutionPolicy Bypass -File tools\build-pages.ps1
#
#  - Encabezado y pie: salen de partials\header.html y partials\footer.html (se editan UNA vez).
#  - Meta de redes sociales / icono / canonical: salen del <title> y la meta description de cada pagina.
#  - sitemap.xml y robots.txt.
# Las paginas se marcan con <!--#header-->...<!--/#header-->, <!--#footer-->... y <!--#meta-->...
# (el script las crea la primera vez). Archivo solo ASCII: el texto con acentos vive en partials\.

$ErrorActionPreference = "Stop"
$root = Split-Path $PSScriptRoot -Parent
$utf8 = New-Object System.Text.UTF8Encoding($false)
function ReadU($p) { return [System.IO.File]::ReadAllText($p, [System.Text.Encoding]::UTF8) }
function WriteU($p, $t) { [System.IO.File]::WriteAllText($p, $t, $utf8) }

$cfg = ReadU (Join-Path $root "js\config.js")
$site = [regex]::Match($cfg, 'siteUrl:\s*"([^"]+)"').Groups[1].Value.TrimEnd('/')
$headerTpl = (ReadU (Join-Path $root "partials\header.html")).Trim()
$footerTpl = (ReadU (Join-Path $root "partials\footer.html")).Trim()

# que enlace del menu se marca como pagina actual
$navKey = @{
  "index.html" = "index.html"; "descargables.html" = "descargables.html"; "consultoria.html" = "consultoria.html";
  "asesoria-intensiva.html" = "consultoria.html"; "reingenieria-360.html" = "consultoria.html"; "contacto.html" = "contacto.html"
}
$noindex = @("carrito.html", "cuenta.html", "gracias.html", "pedido.html")
$sitemapPages = @()

function Wrap($html, $name, $pattern, $fresh) {
  $open = "<!--#$name-->"; $close = "<!--/#$name-->"
  if ($html.Contains($open)) { return $html }
  $m = [regex]::Match($html, $pattern)
  if (-not $m.Success) { return $html }
  return $html.Substring(0, $m.Index) + $open + $fresh + $close + $html.Substring($m.Index + $m.Length)
}
function Fill($html, $name, $content) {
  $open = "<!--#$name-->"; $close = "<!--/#$name-->"
  $i = $html.IndexOf($open); $j = $html.IndexOf($close)
  if ($i -lt 0 -or $j -lt 0) { return $html }
  return $html.Substring(0, $i + $open.Length) + "`n" + $content + "`n" + $html.Substring($j)
}
function Esc($s) { return $s.Replace("&", "&amp;").Replace('"', "&quot;").Replace("<", "&lt;") }

foreach ($f in (Get-ChildItem $root -File -Filter *.html)) {
  $name = $f.Name
  $h = ReadU $f.FullName

  # 1) crear marcadores la primera vez
  $h = Wrap $h "header" '(?s)<header class="mm-header">.*?</header>' ""
  $h = Wrap $h "footer" '(?s)<footer class="mm-footer">.*?</footer>' ""
  # el marcador de meta se coloca justo despues de la hoja de estilos
  if (-not $h.Contains("<!--#meta-->")) {
    $css = '<link rel="stylesheet" href="css/site.css">'
    $h = $h.Replace($css, $css + "`n<!--#meta--><!--/#meta-->")
  }

  # 2) encabezado con la pagina actual marcada
  $hdr = $headerTpl
  if ($navKey.ContainsKey($name)) {
    $k = $navKey[$name]
    $hdr = $hdr.Replace('<li><a href="' + $k + '">', '<li><a href="' + $k + '" aria-current="page">')
  }
  $h = Fill $h "header" $hdr
  $h = Fill $h "footer" $footerTpl

  # 3) meta: icono + redes sociales
  $title = [regex]::Match($h, '<title>(.*?)</title>').Groups[1].Value
  $desc = [regex]::Match($h, 'name="description" content="(.*?)"').Groups[1].Value
  $meta = '<link rel="icon" href="img/favicon.svg" type="image/svg+xml">' + "`n" + '<link rel="icon" href="img/favicon-48.png" sizes="48x48" type="image/png">' + "`n" + '<link rel="apple-touch-icon" href="img/apple-touch-icon.png">' + "`n" + '<meta name="theme-color" content="#111110">'
  if ($noindex -notcontains $name) {
    $url = if ($name -eq "index.html") { "$site/" } else { "$site/$name" }
    $sitemapPages += $url
    $meta += "`n" + '<link rel="canonical" href="' + $url + '">'
    $meta += "`n" + '<meta property="og:type" content="website">'
    $meta += "`n" + '<meta property="og:site_name" content="Grupo MixMix">'
    $meta += "`n" + '<meta property="og:locale" content="es_MX">'
    $meta += "`n" + '<meta property="og:title" content="' + $title + '">'
    if ($desc) { $meta += "`n" + '<meta property="og:description" content="' + $desc + '">' }
    $meta += "`n" + '<meta property="og:url" content="' + $url + '">'
    $meta += "`n" + '<meta property="og:image" content="' + $site + '/img/og.png">'
    $meta += "`n" + '<meta name="twitter:card" content="summary_large_image">'
  }
  $h = Fill $h "meta" $meta

  WriteU $f.FullName $h
  Write-Host ("OK  " + $name)
}

# sitemap.xml y robots.txt
$today = (Get-Date).ToString("yyyy-MM-dd")
$sm = New-Object System.Text.StringBuilder
[void]$sm.AppendLine('<?xml version="1.0" encoding="UTF-8"?>')
[void]$sm.AppendLine('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">')
foreach ($u in ($sitemapPages | Sort-Object)) { [void]$sm.AppendLine("  <url><loc>$u</loc><lastmod>$today</lastmod></url>") }
[void]$sm.AppendLine('</urlset>')
WriteU (Join-Path $root "sitemap.xml") $sm.ToString()
$rb = "User-agent: *`nAllow: /`nDisallow: /carrito.html`nDisallow: /cuenta.html`nDisallow: /gracias.html`nDisallow: /pedido.html`nDisallow: /api/`n`nSitemap: $site/sitemap.xml`n"
WriteU (Join-Path $root "robots.txt") $rb
Write-Host ("sitemap.xml y robots.txt  (sitio: " + $site + ")")
