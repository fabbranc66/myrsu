$OutputDir = "C:\Users\fabbr\Desktop"
$BaseName = "PDR_Sitem_2026_2029_Modello_Marelli_adattato"
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

$struttura = @(
    @("1. Efficienza produttiva","30%","450 euro","540 euro"),
    @("2. Qualita / scarti / reclami","25%","375 euro","450 euro"),
    @("3. Consegne / rispetto programma","20%","300 euro","360 euro"),
    @("4. Risultato economico aziendale","25%","375 euro","450 euro"),
    @("Totale","100%","1.500 euro","1.800 euro")
)

$coefficienti = @(
    @("Sotto soglia minima","0,00","Obiettivo non raggiunto"),
    @("Minimo operativo","0,45","Risultato basso ma utile ai fini della maturazione parziale"),
    @("Intermedio","0,75","Avvicinamento al target"),
    @("Target","1,00","Obiettivo raggiunto"),
    @("Superiore","1,10","Risultato sopra target"),
    @("Massimo","1,20","Risultato eccellente")
)

$datiPress = @(
    @("2026","59,73 ST/ore","74,19 Kg/ore","Dato pubblico da grafici pressofusione"),
    @("2025","62,06 ST/ore","77,55 Kg/ore","Storico recente"),
    @("2024","63,66 ST/ore","86,02 Kg/ore","Storico recente"),
    @("2008","73,10 ST/ore","93,82 Kg/ore","Livello storico alto, non utilizzabile da solo come target")
)

$reclami = @(
    @("Gennaio","0"), @("Febbraio","2"), @("Marzo","4"), @("Aprile","3"),
    @("Maggio","0"), @("Giugno","1"), @("Luglio","0"), @("Totale gen-lug","10")
)

$finanziario = @(
    @("Molto sotto target","0,00"),
    @("Circa -10%","0,50"),
    @("Circa -5%","0,75"),
    @("Target","1,00"),
    @("Circa +5%","1,10"),
    @("Oltre +10%","1,20")
)

$simulazioni = @(
    @("Scenario minimo operativo","0,45","0,45","0,45","0,00","506,25 euro"),
    @("Scenario basso","0,50","0,75","0,50","0,50","843,75 euro"),
    @("Scenario realistico","0,75","1,00","0,75","0,75","1.218,75 euro"),
    @("Scenario target","1,00","1,00","1,00","1,00","1.500,00 euro"),
    @("Scenario massimo","1,20","1,20","1,20","1,20","1.800,00 euro")
)

$html = @"
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<title>PDR Sitem 2026-2029 - Modello Marelli adattato</title>
<style>
@page { size: A4 portrait; margin: 1.2cm; }
body { font-family: Arial, Helvetica, sans-serif; font-size: 8.8pt; line-height: 1.24; color: #111; }
h1 { font-size: 17pt; margin: 0 0 4px; color: #1f2937; }
h2 { font-size: 11.5pt; margin: 14px 0 5px; color: #1f2937; border-bottom: 1px solid #9aa4b2; padding-bottom: 2px; }
p { margin: 4px 0 8px; }
ul { margin: 4px 0 8px 18px; padding: 0; }
li { margin: 2px 0; }
table { border-collapse: collapse; width: 100%; margin: 5px 0 12px; table-layout: fixed; }
th, td { border: 0.7pt solid #7b8491; padding: 2.5px 4px; vertical-align: top; word-wrap: break-word; overflow-wrap: anywhere; }
th { background: #dbe5f1; color: #111; font-weight: bold; text-align: center; }
td { background: #fff; }
td:first-child { font-weight: bold; background: #f5f7fa; }
td:not(:first-child) { text-align: right; }
td:last-child { text-align: left; }
.intro, .box { background: #f6f7f9; border-left: 4px solid #5276a7; padding: 7px 9px; }
.warning { background: #fff7e6; border-left: 4px solid #c47f00; padding: 7px 9px; }
.formula { font-weight: bold; background: #eef3f8; border: 1px solid #9aa4b2; padding: 6px 8px; }
</style>
</head>
<body>
<h1>PDR Sitem 2026-2029 - Modello Marelli adattato</h1>
<p class="intro"><strong>Bozza RSU di lavoro.</strong> Modello ispirato al benchmark Marelli: tre KPI operativi e un KPI finanziario, con pesi espliciti, quote autonome, payout progressivo e nessuna soglia killer. Il premio deve essere comprensibile, raggiungibile e verificabile dalla RSU.</p>

<h2>1. Impostazione generale</h2>
<p>Il Premio di Risultato è collegato a indicatori oggettivi, misurabili e verificabili, finalizzati a valorizzare miglioramenti di produttività, qualità, efficienza industriale e andamento economico dell'impresa.</p>
<p><strong>PDR obiettivo annuo:</strong> 1.500 euro lordi. <strong>PDR massimo annuo:</strong> 1.800 euro lordi.</p>
<p>Ogni KPI produce la propria quota di premio. Il mancato raggiungimento di un indicatore non azzera le quote maturate sugli altri indicatori.</p>
<p class="warning"><strong>Impostazione del minimo operativo:</strong> non viene previsto un minimo garantito fisso. La struttura dei coefficienti è però costruita affinché, in presenza del raggiungimento minimo dei tre KPI operativi anche con KPI finanziario nullo, il premio maturi intorno a 500 euro.</p>
"@

$html += TableHtml "2. Struttura del premio" @("KPI","Peso","Quota target su 1.500 euro","Quota massima") $struttura

$html += @"
<h2>3. Formula generale</h2>
<p class="formula">PDR = 1.500 x [(0,30 x E) + (0,25 x Q) + (0,20 x C) + (0,25 x F)]</p>
<p>Dove E = efficienza produttiva, Q = qualità/scarti/reclami, C = consegne/rispetto programma, F = risultato finanziario. Il coefficiente massimo complessivo è 1,20, corrispondente a 1.800 euro.</p>
<p class="formula">Scenario minimo operativo: 1.500 x [(0,30 x 0,45) + (0,25 x 0,45) + (0,20 x 0,45) + (0,25 x 0,00)] = 506,25 euro</p>
"@

$html += TableHtml "4. Scala di maturazione dei KPI" @("Livello","Coefficiente","Significato") $coefficienti

$html += @"
<h2>5. KPI 1 - Efficienza produttiva (30%)</h2>
<p>Misura proposta: produzione conforme rapportata alle ore realmente disponibili e governabili. Per la pressofusione il dato può essere espresso come Kg conformi prodotti / ore lavorate rettificate oppure stampate conformi / ore lavorate rettificate.</p>
<p class="warning">I target non devono essere costruiti sui migliori anni storici se non vengono dimostrate risorse, impianti, mix produttivo e capacità effettiva coerenti con quei livelli.</p>
"@

$html += TableHtml "5.1 Dati pubblici pressofusione da considerare" @("Anno","ST/ore","Kg/ore","Lettura") $datiPress

$html += @"
<p>Devono essere neutralizzati: fermi impianto, mancanza materiali, attrezzature non disponibili, cambi stampo non programmabili, prove, campionature, carenze organizzative, decisioni aziendali, variazioni cliente e mix produttivo anomalo.</p>

<h2>6. KPI 2 - Qualita / scarti / reclami (25%)</h2>
<p>Misura proposta: riduzione di scarti, rilavorazioni e reclami imputabili rispetto a baseline concordata. Il KPI può includere scarti interni imputabili, rilavorazioni, reclami cliente confermati, PPM o difettosità su pezzi spediti.</p>
"@

$html += TableHtml "6.1 Reclami pressofusione getti 2026" @("Mese","Numero reclami") $reclami

$html += @"
<p>Devono essere esclusi dal calcolo i difetti dovuti a materia prima, progetto, specifiche cliente errate o variate, lavorazioni precedenti o successive, mancanza istruzioni, strumenti o controlli non disponibili.</p>

<h2>7. KPI 3 - Consegne / rispetto programma (20%)</h2>
<p>Misura proposta: ordini completati nei tempi concordati / ordini programmati, oppure lavorazioni completate secondo programma congelato / lavorazioni programmate.</p>
<p>Il programma produttivo utilizzato per il calcolo deve essere congelato con anticipo definito e verificabile. Le modifiche successive non possono penalizzare il KPI del reparto.</p>
<p>Devono essere neutralizzati ritardi cliente, materiali mancanti, modifiche ordine, urgenze commerciali, fermi impianto, programmazione modificata dopo il congelamento, ritardi fornitori o trasportatori e carenza personale decisa dall'azienda.</p>

<h2>8. KPI 4 - Risultato economico aziendale (25%)</h2>
<p>Misura proposta: EBITDA/MOL o risultato operativo rispetto a budget condiviso. Il KPI finanziario incide solo sulla propria quota e non può essere soglia di accesso generale al PDR.</p>
"@

$html += TableHtml "8.1 Scala proposta per il KPI finanziario" @("Scostamento rispetto al target economico","Coefficiente F") $finanziario

$html += @"
<h2>9. Clausola dati e verifica RSU</h2>
<p>L'azienda deve fornire alla Commissione PDR, almeno trimestralmente, dati produzione, ore lavorate, ore disponibili, fermi per causa, scarti, rilavorazioni, reclami, consegne/programmi, criteri di rettifica, dato economico consuntivo e calcolo del premio maturato.</p>
<p>La semplice esposizione in bacheca non è sufficiente: i dati devono permettere alla RSU di ricostruire il calcolo.</p>

<h2>10. Clausola generale di neutralizzazione</h2>
<p>Ai fini del calcolo dei KPI operativi non sono considerate penalizzanti le cause non imputabili ai lavoratori, tra cui guasti impianto, fermi tecnici, mancanza materiali, attrezzature o stampi non disponibili, manutenzioni, prove, campionature, modifiche cliente, cambi programma, carenza ordini, decisioni organizzative aziendali e carenza personale non dipendente dai lavoratori.</p>
"@

$html += TableHtml "11. Simulazioni economiche" @("Scenario","E","Q","C","F","PDR risultante") $simulazioni

$html += @"
<h2>12. Valutazione RSU</h2>
<p class="box">Il modello è più equilibrato del PDR 2024 perché non usa la presenza come moltiplicatore, non lega tutto al fatturato, non azzera il premio se un solo parametro va male, dà prevalenza ai KPI industriali e mantiene il finanziario entro una quota definita. La linea negoziale consigliata è: 75% KPI operativi, 25% KPI finanziario, dati verificabili, baseline recente 2024-2026, target realistici e neutralizzazione scritta delle cause non imputabili.</p>
</body>
</html>
"@

Set-Content -Path $HtmlPath -Value $html -Encoding UTF8
if (Test-Path $OdtPath) { Remove-Item -LiteralPath $OdtPath -Force }
& $Soffice --headless --convert-to odt --outdir $OutputDir $HtmlPath | Out-Null

Write-Output $OdtPath
