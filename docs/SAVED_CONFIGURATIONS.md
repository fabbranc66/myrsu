# Configurazioni salvate MyRSU

## Gestione

La pagina `ui/configurations.html`, disponibile agli admin nel menu Amministrazione e nel banner, permette di mantenere situazioni modificabili separate.

- La situazione iniziale contiene tutti i dati già presenti. L'installazione non chiude pratiche e non elimina segnalazioni, commenti o documenti.
- **Salva configurazione corrente** aggiorna nome e riferimento del salvataggio nella stessa configurazione. Le modifiche dei normali moduli sono già persistenti: non vengono create versioni automatiche.
- **Nuova configurazione vuota** avvia una situazione senza pratiche, segnalazioni, commenti o documenti precedenti; conserva le categorie dei Tavoli come configurazione iniziale.
- **Copia della situazione corrente** è un'operazione esplicita: copia dati, collegamenti, stati e file con verifica dei checksum dei documenti. Se la copia non riesce non viene pubblicata una configurazione parziale.
- **Apri** seleziona una situazione soltanto per il token della sessione admin. Un altro accesso dello stesso admin mantiene la propria selezione.
- **Rendi operativa** cambia, dopo conferma del nome, la situazione predefinita di utenti e visitatori. Le altre situazioni rimangono disponibili agli admin.
- **Torna all'Operativo** rimuove la selezione personale.

Percorso consigliato per terminare il demo: rinominare la situazione iniziale in **Demo**, creare **Operativo** vuota e renderla operativa. Le pratiche aperte nel Demo restano aperte e possono essere lavorate in quell'ambiente. Non vengono cancellati dati: segnalazioni e commenti demo non sono presenti nel nuovo Operativo.

## Separazione e limiti

Il catalogo e le selezioni sono memorizzati in `saved_configurations` e `configuration_sessions`. I dati di una nuova situazione risiedono in tabelle con prefisso interno `cfg_<id>_`, nello stesso database: non occorre creare ulteriori database. I repository continuano a usare le query e i flussi esistenti; il collegamento PDO applica il prefisso ai soli identificatori delle tabelle operative, lasciando invariati valori, parametri e stringhe. Le chiavi esterne interne vengono ricostruite verso la stessa configurazione.

Gli ID dei record, i protocolli e le firme esistenti vengono conservati all'interno di ciascuna situazione. La numerazione di una situazione non è una numerazione unificata tra ambienti.

I file sono separati sotto `storage/private/configurations/<id>/`, con gli stessi percorsi relativi originali. Non sono serviti direttamente dal web: anteprime e download passano dalle API. La situazione iniziale continua a usare i percorsi precedenti. Database e file devono sempre essere inclusi insieme nei backup.

Utenti, autenticazione, ruoli, permessi, consensi, contatti istituzionali e normativa sono comuni; non vengono copiati o cancellati. Nelle configurazioni non operative le modifiche alle impostazioni comuni, gli invii e-mail, la sincronizzazione IMAP, l'invio di inviti e la pubblicazione verso hosting sono bloccati per evitare effetti sull'attività reale. Le normali API senza selezione personale usano sempre l'Operativo. La selezione non si trasferisce ai link esterni o agli ospiti.

Impostazioni del server, credenziali, codice applicativo e preferenze conservate soltanto nel browser (compresi i profili locali del calcolatore PDR) non fanno parte delle configurazioni operative salvate.

Un banner indica all'admin in quale situazione sta lavorando. Il client invia un controllo di contesto: se un'altra scheda cambia selezione, le richieste della vecchia scheda vengono rifiutate anziché scrivere nell'ambiente sbagliato. Ricaricare la pagina per proseguire.

Se dopo un aggiornamento vengono aggiunte tabelle operative, una situazione con schema precedente viene bloccata: niente letture o scritture silenziose nelle tabelle dell'Operativo. La pagina Configurazioni rimane disponibile per tornare all'Operativo. Le migration future devono essere applicate anche agli schemi delle configurazioni interessate prima di riaprirle.

Non sono previsti ripristini distruttivi del database, cancellazione di configurazioni né trasferimenti automatici di singole pratiche fra situazioni. La condivisione descritta sotto rende disponibili gli originali senza trasferirli.

## Archivio generale e condivisioni

`ui/archive.html`, accessibile soltanto agli admin dal menu Amministrazione, dal banner e dalla pagina Configurazioni, elenca pratiche e documenti di tutte le configurazioni, compresi gli elementi aperti, chiusi e archiviati. Si possono filtrare origine, tipo e testo.

- Aprire la configurazione destinataria e attivare **Condividi qui** sul contenuto desiderato. Il flag vale solo per quella configurazione ed è revocabile; gli elementi già appartenenti alla configurazione non richiedono condivisione.
- L'originale rimane unico nella configurazione d'origine. Non vengono copiati dati o file, modificati stati o riaperti automaticamente elementi chiusi.
- Condividere una pratica rende consultabili i documenti collegati direttamente e quelli dei protocolli collegati. I permessi delle cartelle originali continuano a essere verificati a ogni richiesta. Non vengono condivisi automaticamente segnalazioni, commenti o altri oggetti operativi della situazione demo.
- Condividere un documento singolo non rende visibile la pratica che lo contiene. Una condivisione diretta può mantenere disponibile un documento anche dopo la revoca della pratica: rimuovere anche quel flag per revocare entrambi gli accessi.
- Le pagine Pratiche e Documenti mostrano una sezione separata dei contenuti condivisi, con l'origine sempre indicata. La consultazione richiede i ruoli/permessi già previsti dal modulo: non pubblica contenuti agli ospiti e non aggira le restrizioni originali.
- La scheda originale è di sola consultazione. **Apri originale per modificare**, riservato admin, cambia esplicitamente la sua sessione verso l'origine; le normali modifiche si riflettono dove l'elemento è condiviso. Per tornare usare il banner Configurazioni.
- Il registro mantiene la numerazione della configurazione attiva. I protocolli dei contenuti condivisi sono riportati separatamente come riferimenti originali di sola consultazione, senza duplicarli o rinumerarli. Nessun riferimento entra nel contatore attivo.

I riferimenti sono conservati nella tabella comune `archive_shares`, distinti per configurazione destinataria, origine, tipo e ID. ID uguali in due configurazioni non identificano lo stesso oggetto. La copia esplicita di una configurazione conserva anche i suoi riferimenti condivisi; una nuova configurazione vuota non li eredita. La condivisione non è una fotografia immutabile: le modifiche e i nuovi documenti collegati all'originale diventano visibili con gli stessi controlli di accesso.

## Spostare la gestione di una pratica senza spostare documenti

1. Aprire la configurazione destinataria come admin.
2. In **Archivio e condivisioni** cercare la pratica nell'altra configurazione e premere **Sposta qui** direttamente sulla riga, senza doverla condividere o aprire.
3. Confermare scrivendo il codice mostrato. L'elenco si aggiorna nella stessa pagina e offre il collegamento **Apri pratica** nella destinazione.

La pratica diventa una pratica ordinaria modificabile nella destinazione, con stato, assegnazione, date e contenuti invariati. Se il codice è occupato, viene assegnato il successivo codice disponibile e la corrispondenza viene conservata nel registro del trasferimento. Le altre condivisioni della pratica vengono aggiornate automaticamente; non resta una seconda pratica operativa nell'origine.

Il fascicolo precedente segue la pratica tramite riferimenti agli originali: documenti e commenti, segnalazioni e risposte, allegati, note, riferimenti normativi, telefonate, e-mail, incontri e assemblee già collegate. Non vengono copiati o cancellati file né svuotati contenuti usati da altre pratiche. Nella timeline gli elementi precedenti riportano l'origine; per modificare un oggetto collegato l'admin apre esplicitamente la sua configurazione originale. Le nuove note e le successive modifiche della pratica si registrano nella destinazione.

I documenti del fascicolo si aprono direttamente dalla cronologia con l'icona e il visualizzatore PDF abituali, senza passare per la pagina Archivio. L'origine viene mantenuta nelle richieste di anteprima e verifica, anche in presenza di ID uguali in configurazioni diverse; il QR e la verifica della copia caricata usano lo stesso originale.

All'origine rimane una **traccia tecnica non operativa**, esclusa da elenchi, apertura e modifica delle pratiche. Conserva i legami necessari al fascicolo, i permessi originali delle cartelle e i riferimenti di protocollo: non è un'altra pratica lavorabile. I protocolli rimangono riferimenti originali, senza nuova numerazione nella destinazione. Successivi trasferimenti e copie esplicite delle configurazioni mantengono l'intera catena.

Ogni spostamento crea prima un dump completo del database in `storage/private/backups`, protetto dall'accesso web. Nessun file operativo viene spostato, quindi database e file originali vanno conservati insieme per un eventuale ripristino. Tutte le scritture del trasferimento usano una sola transazione: un errore lascia la pratica nell'origine e non pubblica una destinazione parziale. Il dump preventivo può restare disponibile anche in caso di operazione fallita.

Le tabelle comuni `practice_transfers` e `practice_archive_links` richiedono la migration `database/migrations/20261025_practice_transfers.sql`, da applicare dopo quelle delle configurazioni e delle condivisioni. In locale: `php scripts/setup_saved_configurations.php 20261025_practice_transfers.sql`. Nessuna pratica reale viene trasferita automaticamente dall'installazione.

## Installazione locale e deploy manuale

`php scripts/setup_saved_configurations.php` verifica che il database sia **myrsu locale**, salva un dump di sicurezza nella cartella privata `storage/private/backups` e applica la migration. Non contatta l'hosting. Il dump contiene dati riservati, non deve essere pubblicato né inserito nel repository.

Per un'installazione esistente su hosting applicare esclusivamente `database/migrations/20261024_saved_configurations.sql`, non l'intero schema. Richiede permessi CREATE/ALTER per generare gli schemi delle configurazioni e spazio sufficiente per le copie esplicite. Se l'hosting non li consente, la creazione deve fallire senza modificare la situazione esistente.

Per le condivisioni applicare successivamente `database/migrations/20261024_archive_shares.sql`. È una tabella comune: non deve essere duplicata con prefisso nelle configurazioni. In locale `php scripts/setup_saved_configurations.php 20261024_archive_shares.sql` effettua prima il backup e accetta soltanto le migration previste. L'installazione non abilita automaticamente condivisioni sui contenuti reali.

Upload manuale: `app/`, `routes/`, `ui/`. Conservare le protezioni `.htaccess` esistenti, in particolare il divieto di accesso diretto a `storage/private/`. Prima del deploy fare backup del database hosting e dei file. Non è stato eseguito alcun deploy automatico.

## Verifiche

- `php scripts/test_saved_configurations.php`: solo locale; usa utenti esistenti e fixture temporanee, verifica separazione API, sessioni, file, chiavi esterne, salvataggio nella stessa configurazione e ritorno all'Operativo; rimuove le proprie fixture e confronta gli hash delle tabelle originali.
- `php scripts/test_document_folders.php`
- `node scripts/test_document_archive.mjs`
- `node scripts/test_document_mobile_qr.mjs`
- `node scripts/test_configuration_context.mjs`
- `php scripts/test_archive_shares.php`: API admin/lettura, revoca, permessi originari, documenti via protocollo, ID uguali in origini diverse, copie esplicite e integrità dei dati preesistenti.
- `node scripts/test_archive_shares.mjs`
- `node scripts/test_practice_archive_preview.mjs`

I test non inviano e-mail e non eseguono operazioni su hosting.
