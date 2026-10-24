$OutputDir = "C:\Users\fabbr\Desktop"
$BaseName = "PDR_Sitem_2024_commento_operativo_AGGIORNATO_dati_pressofusione_2026"
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
    @("Gennaio","172.044","214.084","2.801","61,43","76,44"),
    @("Febbraio","173.638","212.149","2.817","61,64","75,31"),
    @("Marzo","202.993","234.355","3.380","60,06","69,34"),
    @("Aprile","159.502","185.659","2.598","61,40","71,47"),
    @("Maggio","176.633","225.012","3.030","58,29","74,26"),
    @("Giugno","173.895","235.064","2.991","58,13","78,58"),
    @("Totale / media","1.058.705","1.306.323","17.617","60,16 media semplice","74,23 media semplice")
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
    @("2008","73,10","93,82"),
    @("2024","63,66","86,02"),
    @("2025","62,06","77,55"),
    @("2026","59,73","74,19")
)

$assenze = @(
    @("Nessuna","130%","1.560 euro"),
    @("Da 1 a 7","120%","1.440 euro"),
    @("Da 8 a 13","100%","1.200 euro"),
    @("Da 14 a 18","80%","960 euro"),
    @("Da 19 a 22","70%","840 euro"),
    @("Da 23 a 30","50%","600 euro"),
    @("Oltre 30","30%","360 euro")
)

$html = @"
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<title>PDR Sitem 2024 - Commento operativo aggiornato</title>
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
h3 {
  font-size: 10.5pt;
  margin: 10px 0 4px;
  color: #1f2937;
}
p { margin: 4px 0 8px; }
ul { margin: 4px 0 8px 18px; padding: 0; }
li { margin: 2px 0; }
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
td { background: #fff; }
td:first-child {
  font-weight: bold;
  background: #f5f7fa;
}
td:not(:first-child) { text-align: right; }
td:last-child { text-align: left; }
.intro, .box {
  background: #f6f7f9;
  border-left: 4px solid #5276a7;
  padding: 7px 9px;
}
.warning {
  background: #fff7e6;
  border-left: 4px solid #c47f00;
  padding: 7px 9px;
}
.formula {
  font-family: Arial, Helvetica, sans-serif;
  font-weight: bold;
  background: #eef3f8;
  border: 1px solid #9aa4b2;
  padding: 6px 8px;
}
</style>
</head>
<body>
<h1>PDR Sitem 2024 - Commento e valutazione operativa aggiornata</h1>
<p class="intro"><strong>Documento di lavoro RSU.</strong> La presente versione aggiorna il commento operativo alla bozza PDR Sitem 2024 alla luce dei dati pubblici pressofusione 2026 ricavati dai grafici esposti. I dati fotografici sono utili per la valutazione sindacale, ma devono essere verificati sui file sorgente aziendali prima di essere usati come baseline o target contrattuali.</p>

<h2>1. Funzionamento del premio 2024</h2>
<p>La formula implicita del PDR 2024 sembra essere:</p>
<p class="formula">Premio individuale = importo da incremento fatturato per dipendente x coefficiente efficienza reparti x coefficiente individuale presenza</p>
<p>Esempio operativo: con incremento fatturato per dipendente pari a 7.000 euro, importo tabellare 850 euro, efficienza media reparti 82% e assenze individuali pari a 10 giornate, il premio sarebbe 850 x 120% x 100% = 1.020 euro lordi. Con lo stesso risultato aziendale e nessuna assenza diventerebbe 850 x 120% x 130% = 1.326 euro lordi.</p>

<h2>2. Valutazione sintetica</h2>
<p>La bozza 2024 ha una struttura semplice da spiegare, ma non appare prudente sottoscriverla senza correzioni. Il sistema usa indicatori in larga parte determinati dall'organizzazione aziendale e applica moltiplicatori che possono amplificare molto gli effetti negativi sul singolo lavoratore.</p>
<ul>
<li>Il fatturato per dipendente non è definito con precisione contabile.</li>
<li>L'efficienza dipende anche da impianti, stampi, attrezzature, materiali, programma produttivo e fermi non imputabili ai lavoratori.</li>
<li>La presenza individuale è un moltiplicatore dell'intero premio, non una quota autonoma.</li>
<li>La RSU non ha garanzie sufficienti di accesso ai dati sorgente.</li>
<li>Il massimo matematico risulta 1.820 euro, mentre il testo indica un tetto di 1.800 euro.</li>
</ul>

<h2>3. Dati pubblici pressofusione 2026 utili alla valutazione</h2>
<p>I grafici pubblici della pressofusione 2026 rafforzano le criticità già rilevate nel commento al PDR 2024. In particolare, mostrano un calo della produttività rispetto allo storico e un peso molto elevato delle ore classificate come "Struttura".</p>
"@

$html += TableHtml "3.1 Produzione, ore e produttivita gen-giu 2026" @("Mese","Stampate","Kg prodotti","Ore lavorate","ST/ore","Kg/ore") $produzione
$html += TableHtml "3.2 Fermi macchina per causa gen-giu 2026" @("Mese","Struttura","Impianti","Attrezzature","Cambio stampo","Ore lavorate") $fermi
$html += TableHtml "3.3 Confronto storico essenziale" @("Anno","ST/ore","Kg/ore") $storico

$html += @"
<p class="warning"><strong>Lettura RSU:</strong> se l'efficienza viene usata come moltiplicatore generale del premio senza rettifiche, il PDR rischia di misurare problemi organizzativi aziendali invece del contributo dei lavoratori. La voce "Struttura", pari a 24.767 ore nel periodo gennaio-giugno 2026, è superiore alle ore lavorate indicate nello stesso periodo, pari a 17.617. Questo dato impone una scheda tecnica chiara sulle cause da neutralizzare.</p>

<h2>4. Incoerenza dell'importo massimo</h2>
<p>Il massimo matematicamente prodotto dalle tre tabelle è 1.000 x 140% x 130% = 1.820 euro. Il punto 8 stabilisce invece un massimo di 1.800 euro.</p>
<p>È necessario inserire una clausola esplicita: il premio risultante dall'applicazione delle tabelle non potrà comunque superare l'importo massimo di 1.800 euro lordi per lavoratore. In alternativa, percentuali e importi devono essere corretti in modo che il risultato matematico coincida con il massimo dichiarato.</p>

<h2>5. Fatturato per dipendente non sufficientemente definito</h2>
<p>La bozza non chiarisce quale fatturato debba essere utilizzato, quale sia il perimetro aziendale, quale anno costituisca la base di confronto, come venga calcolato il numero medio dei dipendenti e come vengano trattati somministrati, resi, sconti, infragruppo, componenti straordinarie, inflazione e variazioni di perimetro.</p>
<p>Senza una formula contabile precisa, l'indicatore non è pienamente verificabile dalla RSU. L'accordo dovrebbe allegare una scheda tecnica con fonte contabile, periodo di confronto, criterio di calcolo dei dipendenti medi, elementi inclusi/esclusi ed esempi numerici.</p>

<h2>6. Efficienza condizionata dall'organizzazione aziendale</h2>
<p>Il rapporto tra produzione reale e produzione teorica può dipendere da eventi non controllabili dai lavoratori: guasti, manutenzioni, mancanza materiali, carenza personale, stampi, attrezzature, cambi produzione, prove, campionature, scarti da materiali o progettazione, programmazione produttiva e disponibilità ordini.</p>
<p>I dati pressofusione 2026 rendono questa criticità concreta: la produttività risulta pari a circa 59,73 ST/ore e 74,19 Kg/ore, molto sotto i livelli storici migliori. Non è quindi accettabile fissare target basati sugli anni migliori senza dimostrare che impianti, mix, personale, materiali e organizzazione rendano quei livelli realisticamente raggiungibili.</p>
<p>Correzione proposta: il tempo teorico e la produzione teorica devono essere rettificati neutralizzando tutte le cause non imputabili ai lavoratori. La commissione paritetica deve verificare mensilmente le rettifiche sui dati sorgente.</p>

<h2>7. Media tra tranciatura e pressofusione</h2>
<p>La bozza parla di media tra tranciatura e pressofusione, ma non precisa se sia media semplice, ponderata sulle ore lavorate, sul numero dei dipendenti o sui volumi produttivi. Una media semplice può penalizzare un reparto per problemi verificatisi nell'altro.</p>
<p>Correzione proposta: calcolare separatamente il risultato dei due reparti oppure definire una ponderazione oggettiva basata sulle ore produttive effettive e rettificate.</p>

<h2>8. Fascia di efficienza inferiore al 70%</h2>
<p>La tabella 2024 sembra riconoscere l'80% del premio anche sotto il 70% di efficienza. Questo è favorevole ai lavoratori, ma tecnicamente incompleto: un risultato al 69%, al 40% o prossimo allo zero produrrebbe formalmente lo stesso coefficiente.</p>
<p>Occorre chiarire se l'80% sia un minimo garantito oppure introdurre ulteriori fasce e una soglia minima esplicita.</p>

<h2>9. Effetto del correttivo presenza</h2>
"@

$html += TableHtml "Esempio con premio aziendale pari a 1.200 euro" @("Giornate di assenza","Percentuale individuale","Premio risultante") $assenze

$html += @"
<p>La differenza tra nessuna assenza e oltre trenta giornate arriva a 1.200 euro. La presenza non è quindi un correttivo secondario: diventa uno degli elementi principali del premio. È preferibile trasformarla in quota autonoma e non in moltiplicatore dell'intero PDR.</p>

<h2>10. Assenze conteggiate e riferimento alla L. 151</h2>
<p>Il riferimento sintetico alla "L. 151" è ambiguo e potenzialmente critico. L'accordo deve elencare espressamente il trattamento di maternità, paternità, congedi parentali, Legge 104, infortuni, malattie professionali, ricoveri, terapie salvavita, donazioni sangue/midollo, permessi sindacali, assemblee, aspettative e assenze ingiustificate.</p>
<p>Non è consigliabile accettare formule generiche che possano produrre interpretazioni successive sfavorevoli ai lavoratori.</p>

<h2>11. Possibile doppia penalizzazione</h2>
<p>Un'assenza può già ridurre la produzione e quindi l'efficienza collettiva. Se la stessa assenza riduce poi anche il premio individuale, si genera una doppia penalizzazione. Lo stesso rischio vale per fermi e problemi organizzativi: possono abbassare la produzione e poi ridurre il premio attraverso il coefficiente di efficienza.</p>

<h2>12. Dimissioni, cessazioni e categorie non disciplinate</h2>
<p>La bozza stabilisce che chi si dimette non maturi il premio, ma questo appare incoerente con la maturazione dal 1 gennaio al 31 dicembre rapportata ai mesi di effettiva prestazione. Il premio dovrebbe maturare in proporzione ai mesi di servizio, indipendentemente dalla presenza in azienda alla data di erogazione, salvo ipotesi espressamente previste.</p>
<p>Vanno inoltre disciplinati assunti in corso d'anno, tempi determinati, cessazioni prima del pagamento, trasferimenti di reparto, passaggi full-time/part-time, cassa integrazione, aspettative, congedi e lavoratori somministrati.</p>

<h2>13. Commissione paritetica e accesso ai dati</h2>
<p>La comunicazione mensile in bacheca non basta. La RSU deve poter ricostruire il calcolo del premio partendo dai dati originali.</p>
<ul>
<li>Composizione e frequenza della commissione devono essere definite.</li>
<li>Devono essere consegnati prospetti mensili di calcolo.</li>
<li>La RSU deve accedere ai dati sorgente di fatturato, efficienza, ore, fermi, produzione teorica e consuntiva.</li>
<li>Le riunioni devono essere verbalizzate.</li>
<li>Devono essere previsti termini per contestazioni, rettifiche e risposta aziendale.</li>
</ul>

<h2>14. Riservatezza</h2>
<p>La clausola di riservatezza è troppo generica se consente all'azienda di qualificare unilateralmente come riservate le informazioni. Non deve impedire alla RSU di comunicare ai lavoratori andamento degli indicatori, percentuale di premio maturata, problemi riscontrati, contestazioni aperte e risultato finale.</p>

<h2>15. Welfare</h2>
<p>L'incentivo welfare del 10% è interessante, ma devono essere precisati termine della scelta, modalità, revocabilità, trattamento degli importi non utilizzati, scadenza del credito, effetti in caso di cessazione, beni e servizi disponibili, costi e limiti della piattaforma.</p>

<h2>16. Errori e richiami da correggere</h2>
<p>Nel testo 2024 risultano da verificare: legge n. 208 indicata come 2018 invece che 2015, legge 208/2016, richiamo al comma 86, denominazione "Commissione PDP", rinvio all'articolo 2 anziché al punto 1, espressione "sessioni di formazioni" e uso non uniforme di premio di risultato, premio di produttività e premio di partecipazione.</p>

<h2>17. Valutazione operativa complessiva aggiornata</h2>
<p>La proposta 2024 non è necessariamente negativa, perché consente di superare il valore iniziale attraverso coefficienti di efficienza e presenza. Tuttavia, i dati pressofusione 2026 mostrano che l'efficienza non può essere trattata come dato neutro: è fortemente condizionata da cause tecniche e organizzative.</p>
<p>Prima di approvare un PDR costruito su questo schema sono indispensabili almeno queste modifiche: formula matematica completa, definizione certificabile del fatturato per dipendente, neutralizzazione di guasti e cause non imputabili, elenco puntuale delle assenze, accesso RSU ai dati sorgente, coerenza tra maturazione e pagamento, correzione dei riferimenti normativi e chiarimento del tetto massimo.</p>

<h2>18. Conclusione da utilizzare in assemblea</h2>
<p class="box">Un premio di risultato corretto deve essere comprensibile, raggiungibile e verificabile. I grafici pubblici della pressofusione 2026 confermano che i lavoratori non devono essere penalizzati per cali produttivi legati a struttura, impianti, attrezzature, cambi stampo, materiali, programmazione o decisioni aziendali. Se l'azienda vuole usare l'efficienza nel calcolo del PDR, deve fornire dati sorgente, baseline recenti, target realistici e regole scritte di neutralizzazione.</p>

<h2>Avvertenza</h2>
<p>Il presente documento costituisce una valutazione operativa e sindacale della bozza trascritta. Non sostituisce il controllo definitivo di un consulente del lavoro, di un esperto fiscale o dell'ufficio legale dell'organizzazione sindacale prima della sottoscrizione.</p>
</body>
</html>
"@

Set-Content -Path $HtmlPath -Value $html -Encoding UTF8

if (Test-Path $OdtPath) { Remove-Item -LiteralPath $OdtPath -Force }
& $Soffice --headless --convert-to odt --outdir $OutputDir $HtmlPath | Out-Null

Write-Output $OdtPath
