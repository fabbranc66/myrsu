# Passaggio da myrsu a miodel

Nome visibile: **Mio Delegato**. Cartella locale prevista: `C:\xampp2\htdocs\miodel`. Indirizzo hosting previsto: `https://www.kr-solutions.it/miodel/`.

## Cosa non cambia

- Database locale `myrsu` e hosting `Sql1874742_5`.
- Utenti, ruoli, sessioni, pratiche, configurazioni, protocollo e permessi.
- Originali, PDF, firme, checksum e chiave `MYRSU_SIGNING_SECRET`.
- Chiavi browser `myrsu_*`, oggetti JavaScript `MyRsu*` e marcatori degli originali: sono identificatori tecnici da mantenere per compatibilita.
- Cronologia Git e modifiche non ancora salvate in commit.

Non viene registrato o configurato un dominio da questa procedura. L'eventuale dominio `miodel.it` e un passaggio separato.

## Rinomina locale

1. Avviare Apache e MySQL in XAMPP per il collaudo.
2. Salvare il riferimento di file e database con `php scripts/test_project_relocation.php --capture`, dopo l'aggiornamento del codice.
3. Fermare Apache da XAMPP e chiudere Codex e i terminali aperti nella cartella del progetto. MySQL puo rimanere attivo. Da una finestra PowerShell esterna, con cartella corrente `C:\xampp2\htdocs`, eseguire `powershell -NoProfile -ExecutionPolicy Bypass -File .\myrsu\scripts\rename_project_to_miodel.ps1 -Apply`. Lo script non sovrascrive una destinazione esistente.
4. Verificare il riferimento dal nuovo progetto con `php scripts/test_project_relocation.php --verify storage/private/relocation/before-....json`.
5. Riavviare Apache da XAMPP e controllare `http://localhost/miodel/api/v1/health` e i vecchi link sotto `/myrsu/`.
6. Riaprire in Codex la cartella `C:\xampp2\htdocs\miodel`. La vecchia cartella `myrsu` contiene soltanto il reindirizzamento, non una seconda copia del progetto.

La rinomina non va sostituita con una copia sopra una cartella `miodel` gia esistente. Se Windows segnala file/cartella in uso, chiudere terminali o applicazioni che trattengono il vecchio percorso prima di riprovare.

## Hosting: operazioni manuali, dopo il collaudo locale

1. Conservare un backup aggiornato dell'hosting. Non importare il database locale sul database online.
2. Caricare il codice aggiornato, senza copiare `.env` locale, database o archivi documentali. I percorsi delle API e della UI sono relativi alla cartella dell'applicazione.
3. Rinominare la cartella hosting `myrsu` in `miodel`, mantenendo al suo interno tutti i file esistenti, compresi `.env`, originali e configurazioni private.
4. Ricreare la cartella `myrsu` e caricarvi soltanto il file `scripts/deployment/legacy-myrsu/.htaccess` del progetto, con il nome esatto `.htaccess`.
5. Nel solo `.env` dell'hosting aggiornare `APP_URL` a `https://www.kr-solutions.it/miodel`. Aggiornare anche eventuali integrazioni attive configurate esplicitamente con il vecchio URL, senza cambiare password, token o chiave di firma.
6. Verificare login, documenti, anteprime mobili, collegamenti, PDF vecchi e nuovi e verifica QR. I vecchi URL devono rimandare a `/miodel/` conservando percorso e parametri.

Il reindirizzamento usa HTTP 308, non 301/302, per conservare metodo e corpo delle richieste API. I percorsi interni e i file sensibili rimangono bloccati anche nella cartella di compatibilita.

## Collaudo e riferimenti storici

Gli inventari precedenti possono contenere percorsi assoluti della vecchia cartella: sono riferimenti storici e non vanno riscritti. Dopo la rinomina, creare un nuovo inventario con `scripts/inventory_local_document_branding.php` e passarlo a `scripts/test_local_pdf_branding_verification.php` come argomento per collaudare i PDF correnti.

Prima del passaggio verificare l'integrita dei dati e dei file; dopo il passaggio gli stessi byte dei PDF garantiscono che il checksum resti invariato. Non rigenerare PDF soltanto per modificare il prefisso dell'indirizzo: il reindirizzamento mantiene utilizzabili i QR esistenti.

## Moduli esterni video e tavolo

Gli indirizzi `/video/` (proiezione incontri) e `/tavolo/` (tavoli e partecipanti esterni) sono usati dalle API e restano esterni al prefisso `/miodel/`. Non sono cartelle di backup.

In locale `C:\xampp2\htdocs\video` e `C:\xampp2\htdocs\tavolo` sono junction Windows verso le rispettive cartelle `C:\xampp2\htdocs\miodel\standalone\video` e `C:\xampp2\htdocs\miodel\standalone\tavolo`. Il 18 novembre 2026 sono state corrette le destinazioni rimaste sotto `myrsu` e allineati a `/miodel/` i riferimenti interni ad API, PDF.js e risorse. Verificate pagine e risorse con HTTP 200, senza un collaudo completo con token di incontro/tavolo.

Su hosting mantenere disponibili gli stessi indirizzi: se non si usano mapping del server, pubblicare i contenuti di `standalone/video` in `/video/` e quelli di `standalone/tavolo` in `/tavolo/`. Non trattare le junction Windows come cartelle da caricare via FTP. Nessun aggiornamento remoto eseguito durante questa correzione locale.

## Proiezione di documenti riservati: collaudo del 18 novembre 2026

L'invio al video esterno era respinto con HTTP 403: il controller ammetteva solo documenti visibili agli ospiti e il controllo generale dei file bloccava comunque il PDF riservato dopo la selezione. Su autorizzazione esplicita dell'utente, Documenti e Operativa incontro richiedono ora una conferma prima di proiettare un documento riservato. Ruoli dell'operatore e accesso al documento restano verificati; la visibilita dell'archivio non viene modificata. La conferma viene registrata nel log della pubblicazione.

Il controllo finale dei file ammette esclusivamente il PDF attualmente selezionato per un token valido di una proiezione attiva. Gli altri endpoint mantengono i controlli ordinari; token assente, errato o sessione chiusa non autorizzano il file. Il visualizzatore ritenta il caricamento dopo un errore, evitando di restare vuoto con lo stesso documento selezionato.

Verificati il PDF realmente proiettato con HTTP 200, il rifiuto del token errato con 410 e l'anteprima dell'archivio senza login con 401. Test isolati a 320 e 1366 px superati per disegno delle pagine, recupero dopo 403, scorrimento regia/ospite, cambio documento e chiusura. Test riutilizzabili: `scripts/test_projection_actions.mjs` e `scripts/test_projection_permissions.php`, senza database reale. Nessuna migrazione SQL o modifica remota.

Corretti anche i vecchi percorsi del modulo CCNL. La scansione di app, config, routes, ui (escluse librerie vendor), standalone, ccnl, index.php e .env.example non rileva piu percorsi operativi `/myrsu/`. Non rinominare per questo gli identificatori tecnici di compatibilita.

## Puntatore condiviso del 18 novembre 2026

Su conferma dell'utente sono state aggiunte `pointer_page`, `pointer_x` e `pointer_y` alle tabelle locali `meeting_projection_sessions` e `cfg_2_meeting_projection_sessions`. Verificata l'invarianza delle righe e dei valori precedenti all'aggiunta; conservati documento selezionato e token. Le coordinate sono normalizzate sulla pagina PDF, non sullo schermo.

La regia muove un puntatore rosso persistente con mouse o tocco. Gli spettatori vedono lo stesso punto anche con dimensioni e zoom diversi, ma non possono modificarlo. Possono scorrere e ingrandire liberamente: il ritorno al punto della regia avviene solo al nuovo movimento del puntatore o al cambio documento, non a ogni aggiornamento periodico. L'inseguimento degli spettatori agisce soltanto sullo scroll verticale Y: la traslazione orizzontale X resta manuale anche quando il puntatore esce lateralmente dall'area visibile. Le richieste sono accodate senza sovrapposizioni e gli aggiornamenti relativi a un documento precedente sono respinti.

Prima di caricare questo aggiornamento sull'hosting applicare `database/migrations/20261118_projection_pointer.sql` e, se presente, `database/updates/20261118_projection_pointer_cfg_2.sql`. Eventuali altre tabelle `cfg_N_meeting_projection_sessions` preesistenti richiedono le stesse tre colonne; nuove configurazioni clonate dal database aggiornato le ereditano. Non eseguire nuovamente gli ALTER su tabelle gia aggiornate. Hosting non modificato dall'assistente.

Test: `node scripts/test_projection_pointer.mjs` prova regia a 320 px con spettatore a 1366 px e viceversa, movimento mouse/tocco, consultazione libera, ritorno al puntatore, persistenza, zoom, seconda pagina, cambio documento e chiusura, usando solo API simulate. `php scripts/test_projection_permissions.php` include validazione coordinate, compatibilita della regia precedente e blocco di spettatori e messaggi obsoleti. Verificate anche le colonne reali e le query con ID inesistente (zero righe modificate).

## Zoom documento ed evidenziazione condivisa del 18 novembre 2026

Lo zoom riguarda soltanto il PDF: Ctrl + rotella sopra il documento, oppure gesto a due dita su smartphone, dal 75% al 1000%. Il punto sotto il mouse o al centro delle dita rimane fermo; restano disponibili scorrimento verticale e orizzontale. Il disegno viene ricalcolato dalla pagina PDF nella sola area visibile, senza ingrandire una miniatura o tutta l'interfaccia.

La regia puo premere il tasto sinistro e trascinare sulla pagina per evidenziare temporaneamente un'area di testo, anche su PDF da immagini. L'area gialla e visibile agli spettatori con le stesse proporzioni, indipendentemente da schermo e zoom. Dal rilascio del sinistro il rettangolo rimane fermo e protetto per cinque secondi, mentre il puntatore continua a muoversi anche sugli schermi collegati. Trascorsi i cinque secondi, il successivo movimento normale del puntatore cancella l'area; i movimenti intermedi non riavviano il conteggio. Una nuova selezione puo sostituirla subito. Non modifica il PDF, la firma o la visibilita in archivio; gli spettatori non possono modificarla. Cambio documento e chiusura azzerano l'evidenziazione.

Su autorizzazione dell'utente aggiunte nel solo DB locale `highlight_x` e `highlight_y`, nullable, alle tabelle `meeting_projection_sessions` e `cfg_2_meeting_projection_sessions`. Prima del deploy manuale applicare, dopo le migration del puntatore, `database/migrations/20261118_projection_highlight.sql` e `database/updates/20261118_projection_highlight_cfg_2.sql`, senza ripetere gli ALTER gia eseguiti. Eventuali altre configurazioni preesistenti richiedono le stesse colonne. L'hosting non e stato modificato.

Su ulteriore conferma dell'utente aggiunte nel DB locale anche `highlight_page`, `highlight_end_x` e `highlight_end_y`: distinguono il rettangolo rilasciato dal puntatore mobile. Per il deploy applicare successivamente `database/migrations/20261118_projection_highlight_hold.sql` e, se presente la configurazione 2, `database/updates/20261118_projection_highlight_hold_cfg_2.sql`. Valgono le stesse regole sulle altre configurazioni e sugli ALTER da non ripetere. Nessuna modifica al DB hosting.

Caricare il backend aggiornato e i contenuti di `standalone/video` nella cartella pubblica `/video/`, secondo il mapping descritto sopra; ricaricare regia e spettatori. Test isolati a 320 e 1366 px: `scripts/test_projection_pointer.mjs` verifica anche condivisione, proporzioni, rilascio del sinistro, rettangolo fermo con puntatore libero per cinque secondi e cancellazione al movimento successivo; `scripts/test_projection_zoom.mjs` verifica Ctrl + rotella, fulcro, pinch e ridisegno. `scripts/test_projection_permissions.php` controlla anche coordinate nullable, limiti, spettatori, documenti obsoleti e reset. Le prove automatiche usano API e database simulati, senza alterare la proiezione reale.

## Futuro hosting dedicato MioDel

Il 16 novembre 2026 l'utente ha indicato che, in caso di elezione, utilizzera un hosting dedicato a MioDel e vorra reinizializzare il sistema prima dell'avvio operativo. Non si tratta di un reset o di un trasferimento autorizzato ora.

Prima di procedere concordare dominio e ambiente, nuova configurazione e credenziali, account iniziali e quali dati/documenti conservare o trasferire. Salvare l'ambiente attuale e non cancellare automaticamente utenti, archivi o documenti. Verificare separatamente firme, vecchi QR e relativi reindirizzamenti prima di modificare chiavi o dismettere il vecchio indirizzo. Il passaggio dovra essere collaudato prima dell'uso operativo.
