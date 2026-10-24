$OutputDir = "C:\Users\fabbr\Desktop"
$BaseName = "PDR_Sitem_2026_2029_Modello_Marelli_PRESENZA_MINIMO_500"
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
    @("2. Qualità / scarti / reclami","25%","375 euro","450 euro"),
    @("3. Presenza individuale","20%","300 euro","360 euro"),
    @("4. Risultato economico aziendale","25%","375 euro","450 euro"),
    @("Totale","100%","1.500 euro","1.800 euro")
)

$coefficienti = @(
    @("Sotto soglia minima","0,00","Obiettivo non raggiunto"),
    @("Minimo operativo","0,45","Risultato basso ma utile alla maturazione parziale"),
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

$presenza = @(
    @("0 giornate rilevanti","1,20","360 euro"),
    @("1-7 giornate rilevanti","1,10","330 euro"),
    @("8-13 giornate rilevanti","1,00","300 euro"),
    @("14-18 giornate rilevanti","0,80","240 euro"),
    @("19-22 giornate rilevanti","0,70","210 euro"),
    @("23-30 giornate rilevanti","0,45","135 euro"),
    @("Oltre 30 giornate rilevanti","0,30","90 euro")
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
    @("Scenario basso","0,50","0,75","0,70","0,50","903,75 euro"),
    @("Scenario realistico","0,75","1,00","1,00","0,75","1.293,75 euro"),
    @("Scenario target","1,00","1,00","1,00","1,00","1.500,00 euro"),
    @("Scenario massimo","1,20","1,20","1,20","1,20","1.800,00 euro")
)

$motivazioni = @(
    @("Efficienza produttiva","30%","KPI più pesante perché misura il cuore industriale dell'attività: produzione conforme rispetto alle ore realmente disponibili.","Usare baseline recente 2024-2026; non usare i migliori anni storici senza dimostrare risorse, impianti, mix e capacità effettiva."),
    @("Qualità / scarti / reclami","25%","Misura se la produzione è buona, non solo se è tanta. È coerente con il benchmark Marelli e con i principali PDR industriali.","Considerare solo scarti, rilavorazioni e reclami imputabili; classificare le cause."),
    @("Presenza individuale","20%","Valorizza la continuità della prestazione senza trasformarla in moltiplicatore dell'intero premio.","Escludere assenze tutelate e neutralizzate; evitare doppia penalizzazione."),
    @("Risultato economico aziendale","25%","Collega il premio all'andamento d'impresa senza rendere il risultato economico dominante.","Il finanziario incide solo sulla propria quota e non può azzerare i KPI industriali.")
)

$html = @"
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<title>PDR Sitem 2026-2029 - Modello Marelli con presenza</title>
<style>
@page { size: A4 portrait; margin: 1.2cm; }
body { font-family: Arial, Helvetica, sans-serif; font-size: 8.8pt; line-height: 1.24; color: #111; }
h1 { font-size: 17pt; margin: 0 0 4px; color: #1f2937; }
h2 { font-size: 11.5pt; margin: 14px 0 5px; color: #1f2937; border-bottom: 1px solid #9aa4b2; padding-bottom: 2px; }
p { margin: 4px 0 8px; }
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
<h1>PDR Sitem 2026-2029 - Modello Marelli con presenza</h1>
<p class="intro"><strong>Bozza RSU di lavoro.</strong> Modello ispirato al benchmark Marelli: due KPI operativi industriali, un KPI presenza individuale e un KPI finanziario. Il premio resta a quote autonome, con payout progressivo e senza soglie killer.</p>

<h2>1. Impostazione generale</h2>
<p>Il Premio di Risultato è collegato a indicatori oggettivi, misurabili e verificabili: efficienza produttiva, qualità, presenza individuale e andamento economico aziendale.</p>
<p><strong>PDR obiettivo annuo:</strong> 1.500 euro lordi. <strong>PDR massimo annuo:</strong> 1.800 euro lordi.</p>
<p>Ogni KPI produce la propria quota di premio. Il mancato raggiungimento di un indicatore non azzera le quote maturate sugli altri indicatori.</p>
<p class="warning"><strong>Minimo operativo parametrico:</strong> non viene previsto un minimo garantito fisso. La struttura dei coefficienti è però costruita affinché, con raggiungimento minimo dei tre KPI non finanziari e KPI finanziario nullo, il premio maturi intorno a 500 euro.</p>
"@

$html += TableHtml "2. Struttura del premio" @("KPI","Peso","Quota target su 1.500 euro","Quota massima") $struttura
$html += TableHtml "2.1 Sintesi motivazioni e cautele" @("KPI","Peso","Motivazione","Performance/cautele da considerare") $motivazioni

$html += @"
<h2>3. Formula generale</h2>
<p class="formula">PDR = 1.500 x [(0,30 x E) + (0,25 x Q) + (0,20 x P) + (0,25 x F)]</p>
<p>Dove E = efficienza produttiva, Q = qualità/scarti/reclami, P = presenza individuale, F = risultato finanziario. Il coefficiente massimo complessivo è 1,20, corrispondente a 1.800 euro.</p>
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
<p><strong>Motivazione del peso:</strong> il 30% è giustificato perché l'efficienza è centrale in un PDR metalmeccanico ed è coerente con i benchmark Marelli, Corcos, Exide e Tenaris. Il peso è alto, ma deve essere protetto da regole di neutralizzazione perché molti fattori dipendono dall'organizzazione aziendale.</p>
<p><strong>Performance da considerare:</strong> baseline prudente sugli anni 2024-2026; target realistico vicino ai livelli recenti, salvo investimenti o recuperi dimostrati; target massimo non superiore ai livelli storici recenti senza prova tecnica.</p>

<h2>6. KPI 2 - Qualità / scarti / reclami (25%)</h2>
<p>Misura proposta: riduzione di scarti, rilavorazioni e reclami imputabili rispetto a baseline concordata. Il KPI può includere scarti interni imputabili, rilavorazioni, reclami cliente confermati, PPM o difettosità su pezzi spediti.</p>
"@

$html += TableHtml "6.1 Reclami pressofusione getti 2026" @("Mese","Numero reclami") $reclami

$html += @"
<p>Devono essere esclusi dal calcolo i difetti dovuti a materia prima, progetto, specifiche cliente errate o variate, lavorazioni precedenti o successive, mancanza istruzioni, strumenti o controlli non disponibili.</p>
<p><strong>Motivazione del peso:</strong> il 25% è corretto perché il PDR non deve premiare solo la quantità prodotta. La qualità è uno dei riferimenti più ricorrenti nei benchmark industriali e nel modello Marelli.</p>
<p><strong>Performance da considerare:</strong> baseline su scarti, rilavorazioni e reclami degli ultimi anni o almeno sul periodo 2024-2026; target di riduzione o contenimento realistico; massimo solo in presenza di miglioramento significativo e verificabile.</p>

<h2>7. KPI 3 - Presenza individuale (20%)</h2>
<p>La presenza è una quota autonoma individuale. Non è un moltiplicatore dell'intero premio e non riduce le quote maturate su efficienza, qualità o risultato economico.</p>
<p>Devono essere escluse dal conteggio almeno le assenze tutelate o neutralizzate dall'accordo: infortuni, maternità/paternità, Legge 104, donazione sangue, ricoveri e terapie salvavita, permessi sindacali, ferie e PAR autorizzati e ulteriori assenze protette concordate.</p>
"@

$html += TableHtml "7.1 Scala proposta per il KPI presenza" @("Giornate individuali rilevanti","Coefficiente P","Quota su 300 euro") $presenza

$html += @"
<p><strong>Motivazione del peso:</strong> il 20% valorizza la continuità della prestazione, ma corregge il difetto del PDR 2024: la presenza non moltiplica tutto il premio e non può cancellare il risultato maturato sugli altri KPI.</p>
<p><strong>Performance da considerare:</strong> la fascia target è 8-13 giornate rilevanti, pari a coefficiente 1,00 e quota 300 euro. La fascia minima operativa è costruita per contribuire allo scenario da circa 500 euro senza diventare soglia garantita.</p>

<h2>8. KPI 4 - Risultato economico aziendale (25%)</h2>
<p>Misura proposta: EBITDA/MOL o risultato operativo rispetto a budget condiviso. Il KPI finanziario incide solo sulla propria quota e non può essere soglia di accesso generale al PDR.</p>
"@

$html += TableHtml "8.1 Scala proposta per il KPI finanziario" @("Scostamento rispetto al target economico","Coefficiente F") $finanziario

$html += @"
<p><strong>Motivazione del peso:</strong> il 25% mantiene il collegamento con l'andamento dell'impresa ed è coerente con il benchmark Marelli, ma impedisce al dato finanziario di diventare dominante.</p>
<p><strong>Dati da chiedere:</strong> budget economico iniziale, consuntivo, criteri di calcolo MOL/EBITDA, elementi inclusi ed esclusi, componenti straordinarie, perimetro aziendale e modalità di verifica RSU.</p>

<h2>9. Clausola dati e verifica RSU</h2>
<p>L'azienda deve fornire alla Commissione PDR, almeno trimestralmente, dati produzione, ore lavorate, ore disponibili, fermi per causa, scarti, rilavorazioni, reclami, dati presenza individuale rilevanti, criteri di rettifica, dato economico consuntivo e calcolo del premio maturato.</p>
<p>La semplice esposizione in bacheca non è sufficiente: i dati devono permettere alla RSU di ricostruire il calcolo.</p>

<h2>10. Clausola generale di neutralizzazione</h2>
<p>Ai fini del calcolo dei KPI operativi non sono considerate penalizzanti le cause non imputabili ai lavoratori, tra cui guasti impianto, fermi tecnici, mancanza materiali, attrezzature o stampi non disponibili, manutenzioni, prove, campionature, modifiche cliente, cambi programma, carenza ordini, decisioni organizzative aziendali e carenza personale non dipendente dai lavoratori.</p>
"@

$html += TableHtml "11. Simulazioni economiche" @("Scenario","E","Q","P","F","PDR risultante") $simulazioni

$html += @"
<h2>12. Valutazione RSU</h2>
<p class="box">Il modello è più equilibrato del PDR 2024 perché non usa la presenza come moltiplicatore, non lega tutto al fatturato e non azzera il premio se un solo parametro va male. La linea negoziale consigliata è: 55% KPI industriali, 20% presenza individuale, 25% KPI finanziario, dati verificabili, baseline recente 2024-2026, target realistici e neutralizzazione scritta delle cause non imputabili.</p>
</body>
</html>
"@

Set-Content -Path $HtmlPath -Value $html -Encoding UTF8
if (Test-Path $OdtPath) { Remove-Item -LiteralPath $OdtPath -Force }
& $Soffice --headless --convert-to odt --outdir $OutputDir $HtmlPath | Out-Null

Write-Output $OdtPath
