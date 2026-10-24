$OutputDir = "C:\Users\fabbr\Desktop"
$BaseName = "PDR_Benchmark_metalmeccanici_comparati_FORMATTATO"
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

$benchmarks = @(
    @("Corcos Industriale","Italia","2011-2013","Efficienza, produttivita, qualita, assenteismo individuale","Disponibilita, prestazione, pezzi buoni/pezzi totali; premio per quadrimestre","Separa efficienza, produttivita e qualita; presenza solo individuale; alcune fermate neutralizzate","Da usare come riferimento per separare KPI e non trasformare assenteismo in KPI collettivo"),
    @("Exide Technologies","Italia","2013-2016","Quota fissa + quota variabile: produttivita, scarti, tempo cambio tipo","Obiettivi annuali da budget, verifica trimestrale, premio crescente","Molto adatto a produzione seriale, scarti e attrezzaggi/cambi produzione","Trasporre tempo cambio tipo in cambio stampo/cambio attrezzaggio/cambio produzione"),
    @("Ferrari","Italia","2012-2014","Volumi, risultato economico, qualita/affidabilita, presenza","Qualita come moltiplicatore; presenza come correttivo individuale successivo","Utile per separare risultato aziendale, qualita e presenza individuale","Evitare la logica dei moltiplicatori, che puo amplificare penalizzazioni"),
    @("Prodotti Baumann","Italia","2010-2012","Qualita, redditivita, efficienza","Qualita su azioni correttive maggiori; redditivita EBIT; efficienza valore prodotto/costo lavoro","Lega efficienza a valore creato rispetto al costo lavoro","Per Sitem rischia di essere troppo contabile e poco leggibile agli operatori"),
    @("Same Deutz-Fahr Italia","Italia","2011-2013","Indicatori industriali, continuita lavorativa, redditivita","Magazzino/incompleti, qualita uscente, delibere di flusso, audit sicurezza, EBITDA","Indicatori industriali concreti, qualita verificata, presenza individuale, redditivita separata","Non usare numero infortuni come KPI sicurezza; preferire indicatori preventivi"),
    @("TenarisDalmine","Italia","2012-2014","PQP, PRA, professionalita, prevenzione sicurezza, assiduita","Qualita/produttivita di processo, risultato ante imposte depurato, sicurezza preventiva","Molto vicino alla struttura Sitem: KPI industriali, competenze, sicurezza, presenza, redditivita","Utile come impianto concettuale, attenzione alle valutazioni individuali soggettive"),
    @("Bosch Sistemas de Frenado","Spagna","2012-2015","Assenteismo individuale","Calcolo trimestrale; fasce da 100% a 0%; fondo redistributivo","Conferma che la presenza puo essere individuale e separata dalla performance collettiva","Meccanismo aggressivo: non adottare azzeramento totale per sola presenza"),
    @("Gerdau Aceros Especiales Europa","Spagna","2013-2016","Produttivita, sicurezza, assenteismo, clima","Premio di salvaguardia; soglie 50/100/130; pesi differenziati","Utile per fasce progressive e pesi espliciti","Non copiare la logica di salvaguardia della retribuzione"),
    @("Gerdau Forjanor Villalba","Spagna","2013-2016","Produttivita, sicurezza, assenteismo, clima","Analogo a Gerdau Aceros; quota variabile 5%-7%","Conferma la matrice KPI ponderata con soglia, target, eccellenza","Usare solo il metodo, non la logica salariale di salvaguardia"),
    @("Iveco Espana","Spagna","2013-2016","Risultato collettivo/aziendale + contributo individuale","Obiettivi definiti ex ante; informazioni per dimostrare raggiungibilita; probabilita attesa 85%","Principio forte: KPI raggiungibili con risorse disponibili","Inserire clausola Sitem su raggiungibilita dimostrabile"),
    @("Seat","Spagna","2011-2015","Risultato operativo, produttivita, qualita","Distingue anni con risultato operativo negativo e positivo; premia miglioramento rispetto allo scenario","Utile in congiuntura negativa: mercato negativo non significa cattiva performance operaia","Prevedere premio anche se il risultato economico e negativo ma migliore del previsto"),
    @("ThyssenKrupp Elevator Manufacturing Spain","Spagna","2012-2013","Premio individuale continuita/presenza","20,01 euro mensili; limiti su ritardi e ore assenza; escluse assenze autorizzate","Conferma presenza autonoma, individuale e verificabile","Coerente con quota presenza separata dai KPI collettivi"),
    @("Marelli","Italia","Benchmark aggiuntivo","4 KPI: costi diretti/vendite, scarti/vendite, PPM, risultato economico","Pesi 25% ciascuno; 75% indicatori industriali, 25% economico","Semplice, comunicabile, con prevalenza di KPI industriali","Risultato economico non dominante"),
    @("Leonardo","Italia","Benchmark aggiuntivo","FOCF, EBITA, ordini, DJSI","Ogni KPI ha target, consuntivo e payout","Metodo utile: KPI -> peso -> target -> consuntivo -> payout","Non copiare i KPI, copiare la chiarezza del metodo")
)

$ricorrenze = @(
    @("KPI produttivo","Corcos, Exide, Tenaris, Seat, Marelli","Elemento centrale da mantenere"),
    @("KPI qualita/scarti","Corcos, Exide, Ferrari, Baumann, Same, Tenaris, Seat, Marelli","Indicatore industriale ricorrente e forte"),
    @("KPI organizzativo/processo","Exide, Same, Tenaris","Utile per cambi produzione, flusso, tempi possibili/consuntivi"),
    @("KPI economico","Ferrari, Baumann, Same, Tenaris, Seat, Marelli, Leonardo","Ammissibile, ma non deve dominare o azzerare le altre quote"),
    @("Presenza individuale","Corcos, Ferrari, Bosch, Tenaris, Iveco, ThyssenKrupp","Da tenere individuale e separata"),
    @("Sicurezza","Same, Tenaris, Gerdau","Usare indicatori preventivi, non mancata denuncia eventi"),
    @("Competenze/professionalita","Tenaris","Possibile, ma evitare valutazioni discrezionali")
)

$sitem = @(
    @("Efficienza produttiva","25%","Coerente con Corcos, Exide, Tenaris, Marelli","Da calcolare su ore disponibili e cause governabili"),
    @("Qualita / scarti","20%","Coerente con quasi tutti i benchmark industriali","Serve classificazione cause"),
    @("Organizzazione / saturazione / fermi","15%","Coerente con Exide, Same, Tenaris","Neutralizzare cause aziendali non governabili"),
    @("Competenze / cross-training","10%","Supportato soprattutto da Tenaris","No valutazioni soggettive pure"),
    @("Andamento economico / fatturato","15%","Peso piu basso di molti benchmark","Scelta corretta per limitata controllabilita operaia"),
    @("Presenza individuale","15%","Coerente con molti modelli","Non deve essere moltiplicatore dell'intero PDR")
)

$principi = @(
    @("A","Ogni KPI deve avere peso, baseline, soglia minima, target 100%, soglia di eccellenza, fonte dati, periodicita di verifica e formula di payout."),
    @("B","Gli standard devono essere concordati prima dell'inizio del periodo di misurazione."),
    @("C","Gli obiettivi devono essere realisticamente raggiungibili con personale, impianti, mix e capacita produttiva effettivamente disponibili."),
    @("D","Le cause non imputabili ai lavoratori devono essere neutralizzate."),
    @("E","La RSU deve ricevere dati periodici sufficienti a ricostruire il calcolo."),
    @("F","La presenza deve essere calcolata individualmente."),
    @("G","Gli indicatori economici non devono annullare le quote maturate sui KPI produttivi."),
    @("H","La sicurezza non deve essere misurata attraverso la mancata segnalazione di eventi; preferire audit, azioni correttive, formazione, analisi rischi, ispezioni, ordine e pulizia, interventi tecnici e chiusura anomalie."),
    @("I","Per un accordo triennale serve verifica tecnica annuale senza modifica unilaterale di pesi, formule o baseline.")
)

$html = @"
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<title>PDR - Benchmark metalmeccanici comparati</title>
<style>
@page { size: A4 portrait; margin: 1.2cm; }
body { font-family: Arial, Helvetica, sans-serif; font-size: 8.6pt; line-height: 1.22; color: #111; }
h1 { font-size: 17pt; margin: 0 0 4px; color: #1f2937; }
h2 { font-size: 11.5pt; margin: 14px 0 5px; color: #1f2937; border-bottom: 1px solid #9aa4b2; padding-bottom: 2px; }
p { margin: 4px 0 8px; }
table { border-collapse: collapse; width: 100%; margin: 5px 0 12px; table-layout: fixed; }
th, td { border: 0.7pt solid #7b8491; padding: 2.5px 4px; vertical-align: top; word-wrap: break-word; overflow-wrap: anywhere; }
th { background: #dbe5f1; color: #111; font-weight: bold; text-align: center; }
td { background: #fff; }
td:first-child { font-weight: bold; background: #f5f7fa; }
.intro, .box { background: #f6f7f9; border-left: 4px solid #5276a7; padding: 7px 9px; }
.warning { background: #fff7e6; border-left: 4px solid #c47f00; padding: 7px 9px; }
</style>
</head>
<body>
<h1>PDR - Benchmark metalmeccanici comparati</h1>
<p class="intro"><strong>Casi studio storici / benchmark contrattuali.</strong> Fonte principale indicata nel documento originale: ADAPT University Press, e-Book n. 59/2016. Gli accordi descritti non sono presentati come vigenti oggi: servono come riferimenti di metodo per costruire e valutare il PDR Sitem 2026-2029.</p>
"@

$html += TableHtml "1. Tabella comparativa dei benchmark" @("Accordo","Paese","Vigenza","Struttura/KPI","Meccanismo","Punto utile per Sitem","Attenzione operativa") $benchmarks
$html += TableHtml "2. Elementi ricorrenti nei benchmark utili" @("Elemento","Benchmark in cui ricorre","Lettura per Sitem") $ricorrenze
$html += TableHtml "3. Struttura Sitem di confronto" @("Voce","Peso","Coerenza benchmark","Cautela RSU") $sitem
$html += TableHtml "4. Principi da inserire nella proposta Sitem" @("Punto","Principio operativo") $principi

$html += @"
<h2>5. Conclusione operativa</h2>
<p class="box">Il confronto conferma che il modello Sitem piu solido deve avere pochi KPI chiari, pesi espliciti, baseline dichiarate, target verificabili, payout progressivo e accesso RSU ai dati. La presenza va mantenuta individuale e separata; il risultato economico puo esistere, ma non deve dominare ne azzerare il premio maturato sui KPI industriali.</p>
<p class="warning">Per Sitem il punto negoziale piu importante resta la neutralizzazione delle cause non imputabili ai lavoratori: fermi impianto, materiali mancanti, attrezzature, cambi non programmabili, mix produttivo, decisioni organizzative e carenze di risorse non possono diventare penalizzazioni automatiche sul PDR.</p>
</body>
</html>
"@

Set-Content -Path $HtmlPath -Value $html -Encoding UTF8
if (Test-Path $OdtPath) { Remove-Item -LiteralPath $OdtPath -Force }
& $Soffice --headless --convert-to odt --outdir $OutputDir $HtmlPath | Out-Null

Write-Output $OdtPath
