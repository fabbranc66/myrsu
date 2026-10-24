$OutputDir = "C:\Users\fabbr\Desktop"
$BaseName = "PDR_riepilogo_dati_grafici_pressofusione_2026"
$HtmlPath = Join-Path $OutputDir "$BaseName.html"
$OdtPath = Join-Path $OutputDir "$BaseName.odt"
$Soffice = "C:\Program Files\LibreOffice\program\soffice.exe"

function TableHtml([string]$Title, [string[]]$Headers, [array]$Rows) {
    $html = "<h2>$Title</h2><table><thead><tr>"
    foreach ($h in $Headers) { $html += "<th>$h</th>" }
    $html += "</tr></thead><tbody>"
    foreach ($row in $Rows) {
        $html += "<tr>"
        foreach ($cell in $row) { $html += "<td>$cell</td>" }
        $html += "</tr>"
    }
    return $html + "</tbody></table>"
}

$produzione = @(
    @("Gennaio","172.044","214.084","Dato leggibile da grafico Mensile Generale"),
    @("Febbraio","173.638","212.149","Dato leggibile"),
    @("Marzo","202.993","234.355","Dato leggibile"),
    @("Aprile","159.502","185.659","Dato leggibile"),
    @("Maggio","176.633","225.012","Dato leggibile"),
    @("Giugno","173.895","235.064","Dato leggibile"),
    @("Totale gen-giu","1.058.705","1.306.323","Somma dei valori letti"),
    @("Media mensile","176.451","217.721","Media semplice gen-giu")
)

$produttivita = @(
    @("Gennaio","2.801","61,43","76,44"),
    @("Febbraio","2.817","61,64","75,31"),
    @("Marzo","3.380","60,06","69,34"),
    @("Aprile","2.598","61,40","71,47"),
    @("Maggio","3.030","58,29","74,26"),
    @("Giugno","2.991","58,13","78,58"),
    @("Totale / media","17.617","60,16 media semplice","74,23 media semplice")
)

$fermi = @(
    @("Gennaio","3.741","77","45","179","2.801"),
    @("Febbraio","4.325","53","103","263","2.817"),
    @("Marzo","4.585","93","51","172","3.380"),
    @("Aprile","4.154","91","113","60","2.598"),
    @("Maggio","3.948","82","52","89","3.030"),
    @("Giugno","4.014","78","38","80","2.991"),
    @("Totale gen-giu","24.767","474","402","843","17.617")
)

$storico = @(
    @("2008","73,10","93,82"), @("2009","72,60","96,84"), @("2010","73,40","98,33"),
    @("2011","73,40","95,60"), @("2012","71,80","91,39"), @("2013","72,50","88,78"),
    @("2014","72,80","95,66"), @("2015","72,20","91,55"), @("2016","69,00","90,29"),
    @("2017","68,00","89,61"), @("2018","62,72","91,08"), @("2019","69,25","85,98"),
    @("2020","66,13","85,40"), @("2021","65,94","83,82"), @("2022","64,13","81,60"),
    @("2023","63,61","81,08"), @("2024","63,66","86,02"), @("2025","62,06","77,55"),
    @("2026","59,73","74,19")
)

$reclami = @(
    @("Gennaio","0"), @("Febbraio","2"), @("Marzo","4"), @("Aprile","3"),
    @("Maggio","0"), @("Giugno","1"), @("Luglio","0"), @("Totale gen-lug","10")
)

$html = @"
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<title>Riepilogo dati grafici pubblici pressofusione Sitem 2026</title>
<style>
@page { size: A4 landscape; margin: 1.2cm; }
body {
  font-family: Arial, Helvetica, sans-serif;
  font-size: 9.5pt;
  line-height: 1.28;
  color: #111;
}
h1 {
  font-size: 17pt;
  margin: 0 0 4px;
  color: #1f2937;
}
h2 {
  font-size: 11.5pt;
  margin: 14px 0 5px;
  color: #1f2937;
  border-bottom: 1px solid #9aa4b2;
  padding-bottom: 2px;
}
p { margin: 4px 0 8px; }
table {
  border-collapse: collapse;
  width: 100%;
  margin: 5px 0 12px;
  table-layout: fixed;
}
th, td {
  border: 0.7pt solid #7b8491;
  padding: 3px 5px;
  vertical-align: top;
  word-wrap: break-word;
}
th {
  background: #dbe5f1;
  color: #111;
  font-weight: bold;
  text-align: center;
}
td {
  background: #fff;
}
td:first-child {
  font-weight: bold;
  background: #f5f7fa;
}
td:not(:first-child) {
  text-align: right;
}
td:last-child {
  text-align: left;
}
.note {
  background: #f6f7f9;
  padding: 8px 10px;
  border: 0.8pt solid #9aa4b2;
}
.intro {
  font-size: 9.5pt;
  background: #f6f7f9;
  border-left: 4px solid #5276a7;
  padding: 7px 9px;
}
</style>
</head>
<body>
<h1>Riepilogo dati grafici pubblici pressofusione Sitem 2026</h1>
<p class="intro"><strong>Documento di lavoro RSU.</strong> Dati ricavati dalle fotografie dei grafici esposti. Prima di usarli come baseline o target contrattuali occorre verificarli sui file sorgente aziendali.</p>
"@

$html += TableHtml "1. Produzione mensile generale" @("Mese","Stampate","Kg prodotti","Nota") $produzione
$html += TableHtml "2. Ore lavorate e produttivita" @("Mese","Ore lavorate","ST/ore","Kg/ore") $produttivita
$html += TableHtml "3. Fermi macchina per causa" @("Mese","Struttura","Impianti","Attrezzature","Cambio stampo","Ore lavorate") $fermi
$html += TableHtml "4. Storico ST/KG dal 2008 al 2026" @("Anno","ST/ore","Kg/ore") $storico
$html += TableHtml "5. Reclami pressofusione getti 2026" @("Mese","Numero reclami") $reclami

$html += @"
<h2>6. Lettura operativa per PDR</h2>
<div class="note">
<p>I grafici mostrano per il 2026 una produttivita media pressofusione intorno a <strong>59,73 ST/ore</strong> e <strong>74,19 Kg/ore</strong>, inferiore ai livelli storici migliori. Eventuali target PDR superiori ai valori recenti devono quindi essere motivati con dati ufficiali, risorse disponibili, mix produttivo, stato impianti e neutralizzazione delle cause non imputabili ai lavoratori.</p>
<p>La voce <strong>Struttura</strong> nei fermi macchina risulta molto rilevante rispetto alle ore lavorate: per un KPI efficienza equo occorre distinguere chiaramente fermi governabili dai lavoratori e fermi dovuti a organizzazione, impianti, materiali, attrezzature, cambi stampo, prove o decisioni aziendali.</p>
<p>I reclami risultano contenuti nel periodo gennaio-luglio, ma devono essere valutati solo dopo classificazione delle cause e verifica dell'effettiva imputabilita al reparto.</p>
</div>
</body>
</html>
"@

Set-Content -Path $HtmlPath -Value $html -Encoding UTF8

if (Test-Path $OdtPath) { Remove-Item -LiteralPath $OdtPath -Force }
& $Soffice --headless --convert-to odt --outdir $OutputDir $HtmlPath | Out-Null

Write-Output $OdtPath
