# Sicurezza amministrativa: sottopassi locali

Stato del 25 ottobre 2026: implementato e verificato automaticamente in locale; nessun deploy autorizzato.

Aggiornamento del 26 ottobre 2026: l'utente ha successivamente distribuito il codice e svolto il collaudo hosting descritto sotto. Le indicazioni solo locali restano valide per gli script e per la checklist originaria, non descrivono piu lo stato del rilascio.

## Ambito

- Creazione, modifica, sospensione, cancellazione degli account, modifica dei ruoli e caricamento del logo di altri utenti richiedono il ruolo admin, oltre ai permessi esistenti.
- Il menu Utenti resta riservato agli admin, come gia previsto dalla navigazione. Delegati, RLS e Delegato + RLS usano Profilo per modificare i propri dati; non aggiungere loro la voce Utenti.
- La scheda di modifica utente e protetta anche accedendo direttamente all'URL.
- La modifica dei dati anagrafici personali e il flusso documentale rimangono invariati.
- Non sono stati rimossi i vecchi permessi dalle associazioni del database: il blocco lato server funziona anche con quelle concessioni ancora presenti.
- L'ultimo account attivo con ruolo admin non puo essere sospeso, eliminato o privato del ruolo admin: il server risponde 409 e chiede di attivare prima un altro amministratore. Un admin sospeso non conta come alternativa. Resta anche il divieto preesistente di cancellare il proprio account.
- La verifica e la modifica sono atomiche: sospensioni, eliminazioni e sostituzioni dei ruoli usano lo stesso lock transazionale sulla riga del ruolo admin. Anche una sostituzione che mantiene admin acquisisce il lock. Errori e rifiuti provocano rollback; un'eventuale transazione esterna resta sotto il controllo del chiamante.
- Nessuna migrazione SQL, modifica dei menu o variazione del flusso documentale per questa protezione.
- Ogni nuova richiesta autenticata accetta soltanto utenti ancora attivi. La sospensione revoca tutti i loro token API nella stessa transazione della modifica di stato; un errore di revoca annulla anche la sospensione. La protezione dell'ultimo admin resta valida.
- Dopo riattivazione serve un nuovo login: non si riabilitano i token revocati. La transizione da sospeso ad attivo invalida anche eventuali token legacy non revocati; salvare un account gia attivo non disconnette le sue sessioni.
- L'emissione del token al login controlla e blocca la riga utente nella stessa istruzione SQL di inserimento, evitando che un controllo iniziale ormai superato crei una sessione dopo la sospensione.
- Un cambio password non vuoto, dal Profilo o tramite reset admin, revoca tutti i token API dell'utente nella stessa transazione dell'aggiornamento della password. Se la revoca fallisce, anche la nuova password viene annullata. Password omessa o vuota nel salvataggio amministrativo e modifiche solo anagrafiche non disconnettono.
- La UI del cambio personale elimina solo il token dai due storage locali e torna al login. Il reset di un altro account non disconnette l'admin; il reset della password propria dalla gestione utenti richiede invece un nuovo login. Simulazioni PDR e bozze locali non vengono cancellate.
- Il login confronta anche l'hash password verificato inizialmente con quello corrente, con confronto binario e lock nell'inserimento: un login iniziato con le vecchie credenziali non puo emettere un token dopo il reset.
- Regole confermate dall'utente il 25 ottobre 2026: scadenza dopo 2 ore senza richieste API autenticate e durata massima di 24 ore dall'emissione, anche in presenza di attivita. Le soglie esatte sono gia scadute. Se non c'e ancora `last_used_at`, si usa `created_at`; l'attivita non cambia mai la data di emissione. Il tempo e quello del database, senza modificare l'orologio di sistema.
- L'inattivita e assenza di richieste autenticate, non assenza di tastiera/mouse: anche richieste automatiche autenticate aggiornano l'attivita. Non sono introdotti timer del browser che interrompano il flusso documentale; il server rifiuta il token scaduto con 401 alla richiesta protetta successiva. Un touch non puo riattivare token scaduti o revocati, e richieste valide nello stesso secondo restano ammesse.
- Un cambio effettivo dei ruoli revoca tutti i token dell'utente nella stessa transazione, anche nell'assegnazione diretta. Ordine diverso, duplicati o risalvataggio degli stessi ruoli non disconnettono. La riga utente viene bloccata per serializzare la modifica con l'emissione dei token; un errore di revoca annulla anche la variazione dei ruoli.
- L'admin che cambia il proprio ruolo torna al login. Nella scheda utente i dati vengono salvati prima di applicare il nuovo ruolo proprio; password, sospensione e nuovi dati sindacali vanno modificati separatamente dal proprio cambio ruolo, con avviso prima di effettuare richieste incompatibili. Gli altri account si modificano come prima.
- Gli errori server rispondono con messaggio generico e `error_id`, senza `detail`, `source`, SQL, percorsi o stack, anche se `APP_DEBUG` e attivo. Lo status HTTP originale resta invariato, compresi 502/503. I messaggi operativi 4xx di validazione, autenticazione, permessi e conflitti restano gli stessi.
- `ApiErrorHandler` registra nei log PHP il medesimo identificativo, tipo, messaggio, file, riga e stack senza argomenti delle funzioni. Il record tecnico e JSON su una riga; non vengono aggiunti corpo della richiesta, header, token o password al log. I dettagli eventualmente presenti nel messaggio originale dell'eccezione restano solo nei log del server, che devono essere protetti.
- `index.php` disabilita la visualizzazione degli errori PHP e abilita il logging durante la richiesta. Un gestore comune copre anche le eccezioni di avvio precedenti a `Application::run`. Nel modulo Fondi il messaggio di errore parziale della protocollazione mantiene il tipo stringa e il comportamento non bloccante, ma i dettagli tecnici sono sostituiti da messaggio generico e codice errore.
- `.htaccess` blocca l'accesso web diretto a file nascosti/metadati, directory backend e di sviluppo (`app`, `config`, `database`, `docs`, `routes`, `scripts`, `vendor`, `artifacts`), manifest interni, dump SQL, log e copie di backup. La protezione e case-insensitive e conserva il blocco di `storage/private`. Restano invariati inoltro Authorization, riscrittura dei PDF autorizzati e routing API; il vendor JavaScript sotto `ui/vendor` e l'eccezione `.well-known` restano accessibili.
- `config/database.php` non contiene piu la password DB di ripiego: richiede `DB_PASSWORD` nell'ambiente. La variabile assente produce un errore di configurazione prima della connessione; un valore vuoto esplicito resta ammesso per installazioni che lo prevedono, senza inventare password o cambiare il metodo di autenticazione del database.
- Anche la firma interna richiede una configurazione esplicita: `MYRSU_SIGNING_SECRET` obbligatoria, chiave di almeno 32 caratteri dopo trim, niente valore automatico o vecchio ripiego pubblico. Il template `.env.example` non fornisce piu `change-me`: per una nuova installazione va scelta e conservata una chiave privata casuale. La lunghezza minima non costituisce da sola una garanzia di imprevedibilita.

Il blocco opera alla richiesta API successiva: non cancella dati gia visualizzati o scaricati e non interrompe retroattivamente richieste gia autorizzate. Le pagine pubbliche restano pubbliche. Restano collaudo manuale, segreti e verifica delle protezioni sul server reale. La gestione runtime degli errori non sostituisce la configurazione PHP del server per guasti che precedono l'esecuzione dello stesso `index.php`; non considerare completata tutta la prima fase della roadmap.

## Isolamento

Il database e `myrsu` su loopback. Nel solo `.env` locale sono presenti all'inizio le impostazioni vuote `HOSTING_DOCUMENTS_ENDPOINT`, `HOSTING_DOCUMENTS_TOKEN`, `EMAIL_SMTP_HOST` e `EMAIL_IMAP_HOST`.

Il caricatore prende la prima occorrenza di ciascuna variabile: non spostare questi valori durante i test e non caricare questo `.env` sull'hosting. Credenziali preesistenti e configurazioni remote non sono state pubblicate o modificate. Il test automatico si rifiuta di partire se le uscite non sono disabilitate.

Prima della rimozione del ripiego, il locale non aveva `DB_PASSWORD` e usava il valore nel codice. Lo stesso valore effettivo e stato trasferito nel solo `.env` locale ignorato da Git, preservando il blocco iniziale delle quattro impostazioni di isolamento. Il confronto prima/dopo ha confermato la stessa credenziale senza mostrarla; nessun cambio password sul server DB o sugli account MyRSU. `.env.example` aveva gia la variabile e non riporta la credenziale locale, quindi non e stato modificato.

Prima di un eventuale futuro deploy di `config/database.php`, verificare che l'ambiente hosting abbia `DB_PASSWORD` impostata alle proprie credenziali. Non copiare il `.env` locale. Il valore rimosso puo restare nella cronologia Git: non sono state riscritte revisioni o effettuate rotazioni, che richiedono una valutazione separata. Le chiavi di firma non sono state toccate.

### Preparazione hosting effettuata manualmente dall'utente

Il 25 ottobre 2026, dopo l'ottavo sottopasso, l'utente ha eseguito solo questi tre interventi guidati sui file remoti, con copie precedenti conservate sul PC fuori dalla cartella web:

1. Nel `.htaccess` remoto ha aggiunto la regola di blocco dei file nascosti con eccezione `.well-known`; confermati `.env` 403 e health 200. Non equivale all'intero blocco locale delle directory backend.
2. Ha aggiunto `DB_PASSWORD` al `.env` remoto usando la credenziale gia in uso sull'hosting, non il `.env` locale.
3. Ha sostituito soltanto la riga password del `config/database.php` remoto, eliminando il ripiego senza sovrascrivere host/database/username. Login e apertura Documenti confermati funzionanti dopo entrambe le modifiche di configurazione.

Successivamente l'utente ha caricato config/signing.php e app/Services/DocumentSignatureService.php mantenendo la chiave hosting; confermati login, verifica del preesistente e nuovo PDF da immagine n.42 valido con checksum corretto. Ha poi confermato caricamento di app/, ui/ e index.php e completamento di .htaccess: file interni 403, health 200, login e verifica PDF funzionanti. Nessuna migrazione o copia del .env locale; configurazioni remote specifiche mantenute. L'assistente non ha effettuato direttamente accessi/deploy remoti. Rimane il collaudo mirato di permessi amministrativi e revoche, non dimostrato dal solo accesso e dalla verifica PDF.

### Collaudo hosting confermato il 26 ottobre 2026

Prove manuali dell'utente con account demo Topo e sessione admin separata; nessun accesso remoto diretto dell'assistente:

- Come non admin: menu Utenti assente, Profilo accessibile, URL diretto di modifica utente bloccato. POST vuoti su /users e /users/2147483647/roles restituiscono 403, senza modifiche ad account o ruoli.
- Sospensione: la sessione precedente viene rifiutata e il nuovo login segnala utente non attivo. Dopo riattivazione funziona un nuovo login.
- Reset password da admin e cambio dal Profilo: sessione del destinatario invalidata, vecchia password rifiutata, nuova accettata; admin non disconnesso.
- Cambio Delegato -> Membro -> Delegato: invalidazione della sessione precedente confermata anche al ritorno a Delegato. Il passaggio a Membro elimina le associazioni di sigla/logo; non sono stati inventati o ripristinati valori sconosciuti.
- Correzione menu Membro caricata e verificata: Incontri assente; Amministrazione contiene solo Profilo. Test isolati JavaScript del menu e dei ruoli superati; nessuna modifica ai permessi API o al flusso documentale.

Restano da verificare manualmente il salvataggio degli stessi ruoli e delle sole informazioni anagrafiche senza disconnessione, altre combinazioni e sessioni multiple. Le prove non dimostrano il decorso reale delle 2h/24h o la protezione dell'ultimo admin sull'hosting: questi casi restano coperti dai test locali, senza modificare orologio o unico admin operativo. Restano anche il collaudo documentale con riserva e la verifica della configurazione PHP/log del provider. Non dichiarare completata l'intera priorita 1.

### Chiave di firma locale

Il 25 ottobre 2026 l'utente ha autorizzato l'eliminazione del ripiego, chiarendo che i documenti sono demo e gli originali disponibili. La verifica ha trovato una chiave locale gia esplicita e compatibile: e stata mantenuta, senza mostrarla, modificarla o rigenerare file. Config e servizio rifiutano ora chiave mancante, vuota, troppo corta e il vecchio valore pubblico; nessun algoritmo, payload, formato, URL QR o processo di verifica e cambiato.

La verifica API attuale confronta firma registrata e checksum del PDF: non e stata riscritta per ricalcolare automaticamente con chiavi diverse. Una futura rotazione resta una scelta separata, non effettuata in questo intervento. Conservare la chiave attuale insieme ai backup appropriati e non generarla a ogni avvio.

Collaudo manuale dell'utente: documento locale preesistente n.3 valido con checksum corretto; nuovo PDF locale n.42 da immagine valido, come il nuovo PDF hosting n.42. I due record n.42 appartengono ad ambienti distinti, non sono prova di sincronizzazione. Il nuovo documento locale e successivo alla baseline_20261025_215230.json: il comando storico sotto puo segnalare differenze attese. Prima del prossimo confronto completo verificare i cambiamenti e salvare un nuovo riferimento, senza sovrascrivere i precedenti o cancellare documenti per far passare i test.

## Test automatici ripetibili

```powershell
php scripts/test_local_admin_security.php --verify storage/private/security-tests/baseline_20261025_215230.json
php scripts/test_local_web_protection.php
php scripts/test_last_active_admin.php
php scripts/test_last_admin_local_locks.php
php scripts/test_session_suspension.php
php scripts/test_session_policy.php
php scripts/test_api_errors.php
php scripts/test_database_config.php
php scripts/test_document_signatures.php
php scripts/test_session_local_locks.php
php scripts/test_user_deletion.php
php scripts/test_document_visibility.php
php scripts/test_practice_visibility.php
node scripts/test_user_roles.mjs
node scripts/test_member_menu.mjs
node scripts/test_password_sessions.mjs
node scripts/test_document_visibility.mjs
node scripts/test_document_archive.mjs
```

La prima baseline `baseline_20261025_170355.json` e stata salvata prima delle modifiche ai controlli amministrativi, sul clone locale gia allineato: 78 file originali/PDF dei 39 documenti, 24 tabelle documentali, account e associazioni dei ruoli, 28 risposte API per ospite, admin, delegato e doppio ruolo. Il riferimento attuale e `baseline_20261025_215230.json`, che include i test manuali confermati dall'utente e consente di nuovo il confronto storico completo degli account.

Il confronto copre documenti, pratiche, cartelle, protocollo, riferimenti condivisi, anteprima, download e verifica di un PDF rappresentativo. In aggiunta verifica 20 risposte amministrative con richieste non valide o ID inesistenti: nessuna prova cambia account o ruoli reali.

Il test crea e rimuove soltanto propri token temporanei. Le letture generano i normali log locali di consultazione, che vengono conservati. Non riscrive PDF, originali, pratiche, protocolli o utenti esistenti. Non usa credenziali di login dell'utente e non contatta l'hosting.

La protezione dell'ultimo admin e collaudata con 130 controlli SQLite in memoria, senza usare gli account importati: ultimo admin, secondo admin attivo o sospeso, tutte le combinazioni di sospensione/eliminazione/rimozione ruoli, ruoli vuoti, doppio ruolo, profilo, errori SQL e transazioni esterne. SQLite verifica i casi funzionali, non i lock MySQL.

Il test dei lock usa invece due connessioni a `myrsu` locale e la riga admin esistente, tenendola bloccata per circa quattro secondi: verifica che quattro percorsi concorrenti attendano lo stesso lock e annullino correttamente la transazione al timeout. Usa solo un ID utente verificato come inesistente e confronta account/ruoli prima e dopo; non crea tabelle, non modifica account importati e non usa l'hosting. Il collaudo non sospende o rimuove amministratori reali.

Il test delle sessioni verifica 64 casi su SQLite in memoria, compresi i controller utenti, profilo e me: piu sessioni, token in query, login, sospensione, riattivazione, password personale e reset admin, token legacy, rollback, isolamento degli altri utenti e ultimo admin. Comprende il login concorrente a sospensione o cambio password. Il test MySQL dedicato blocca per circa due secondi una riga utente senza modificarla e verifica l'attesa dell'emissione del token, il rilascio e il confronto binario dell'hash; crea e rimuove solo un proprio token di prova, confrontando tutte le sessioni preesistenti prima/dopo. Anche la regressione documentale usa l'emissione ordinaria dei token di test, non un inserimento SQL parallelo. I test JS verificano il ritorno al login dal Profilo e dalla gestione della password propria, l'assenza di logout in caso di errore e la conservazione della sessione admin quando reimposta un altro account.

Se cambiano deliberatamente dati o documenti, non sovrascrivere la baseline solo per far passare il test: analizzare prima le differenze. Una nuova baseline richiede un nuovo controllo iniziale.

Il 25 ottobre 2026 l'utente ha confermato un cambio password e nuovo login eseguiti durante il collaudo. Questo spiega la variazione di account/sessioni rilevata dal test concorrente; nessuna password e stata ripristinata. La baseline originale e conservata. Per confrontare ancora file, tabelle documentali e le stesse 28 risposte API, escludendo esplicitamente soltanto il confronto storico degli account gia cambiati, usare:

```powershell
php scripts/test_local_admin_security.php --verify-documents storage/private/security-tests/baseline_20261025_170355.json
```

Anche questa modalita continua a verificare che account, ruoli, file e tabelle non cambino durante il test e mantiene i 20 controlli amministrativi. Non usarla per nascondere modifiche anagrafiche non spiegate; `--verify` resta il confronto storico completo.

Successivamente l'utente ha confermato di avere aggiunto una cartella pubblica. Prima dell'intervento sulle regole web il confronto ha rilevato soltanto `cfg_2_document_folders`, `cfg_2_document_folder_permissions` e le tre risposte di elenco cartelle; file e altre tabelle erano invariati. La cartella e stata conservata. Dopo il nuovo controllo iniziale e stato salvato `baseline_20261025_215230.json`, senza sovrascrivere il precedente: usare ora il comando completo `--verify` indicato sopra. Il confronto con la prima baseline continua correttamente a rilevare la cartella aggiunta, anche con `--verify-documents`.

La nuova policy e verificata separatamente con 49 controlli SQLite a orologio simulato, senza attendere ore o cambiare il tempo reale: confini 2h/24h, scadenza fra lettura e touch, rifiuto del rinnovo, revoca al cambio ruolo, no-op, token in query, endpoint ruoli, rollback e ultimo admin. Su MySQL locale si verificano anche touch ripetuto nello stesso secondo e limiti esatti, modificando esclusivamente il token di prova poi rimosso. I test JS coprono cambio del ruolo proprio dalla lista e dalla scheda, ritorno al login e blocco preventivo delle operazioni combinate incompatibili.

La gestione degli errori e verificata con 91 controlli senza connessione DB: 4xx invariati, 500/502/503 senza dettagli, cinque valori di debug, identificativi correlati, stack senza argomenti, messaggi su una riga, dispatcher reale su rotta health sintetica e guasto di avvio in un processo PHP isolato. Il caso Fondi usa solo un repository fittizio che fallisce prima di qualsiasi protocollazione. Script e log di prova sono temporanei e rimossi; nessuna rotta di test viene installata nell'applicazione web. Le verifiche HTTP locali hanno inoltre confermato health 200, me anonimo 401, endpoint inesistente 404 e login non valido 422 con JSON pulito anche in presenza di un warning PHP. Il confronto documentale prima/dopo e rimasto invariato.

La protezione web e verificata con 72 controlli HTTP locali GET/HEAD: file interni 403, comprese varianti maiuscole e `.env` codificato, UI e librerie JS operative 200, health 200, eccezione `.well-known` conservata. Prima della modifica HEAD restituiva 200 per `.env`, metadati Git, schema e altri file interni: sono stati letti solo gli status, non i contenuti sensibili. Il test scarta i corpi delle risposte e non modifica file operativi. Il confronto completo sulla nuova baseline ha confermato 78 file, 24 tabelle, 28 risposte e 20 protezioni amministrative invariati.

La configurazione DB e verificata con 14 controlli isolati senza connessione: variabile assente, valori espliciti inclusi vuoto e stringa zero, struttura dell'array invariata e nessuna alterazione delle variabili del chiamante. Le connessioni operative sono verificate dalla regressione HTTP sul database locale, con credenziale effettiva preservata, senza mostrare valori o modificarli nel DB. Errori API e blocchi web sono stati ricollaudati dopo il trasferimento.

La firma e verificata con 25 controlli sintetici: configurazioni rifiutate, minimo esplicito, algoritmo e payload originali, normalizzazione, checksum, metadati e firme alterati. Nessuna connessione DB, scrittura di PDF o modifica dei metadati nei test unitari. Il confronto completo sulla baseline corrente conferma invariati 78 file, 24 tabelle, 28 risposte API e 20 protezioni amministrative, compresa la verifica di un PDF rappresentativo gia emesso. Nessun bisogno di reimportare originali o rigenerare QR in questo intervento.

Queste verifiche valgono esclusivamente per Apache locale. L'hosting non e stato contattato o aggiornato: verificare separatamente configurazione PHP, log e regole `.htaccess` reali prima di un eventuale deploy autorizzato, conservando personalizzazioni gia presenti. Non presumere compromissioni o accessi precedenti ai segreti e non ruotare password/chiavi automaticamente; tale valutazione e separata.

## Checklist manuale, esclusivamente locale

1. Aprire `http://localhost/myrsu/ui/app/index.html`, non il dominio dell'hosting.
2. Entrare come admin: nella pagina Utenti devono comparire creazione e controlli di modifica/ruoli. Verificare che la scelta Delegato + RLS resti disponibile, senza cambiare utenti reali solo per prova.
3. Entrare come delegato e come Nando: la voce Utenti non deve comparire nel menu, mentre Profilo resta disponibile per i propri dati. Non proporre la consultazione dell'elenco utenti come percorso operativo. L'URL diretto `user-edit.html?id=1` non deve consentire modifiche e le API amministrative devono rispondere 403.
4. Verificare che il proprio profilo resti accessibile. Non cambiare password reali durante il confronto della baseline.
5. Verificare i documenti autorizzati: elenco/autore, anteprima, download, verifica locale, collegamenti alla pratica e contenuti degli archivi. Le pratiche riservate devono restare nascoste agli altri.

I seguenti test di scrittura sono aggiuntivi e vanno eseguiti con documenti e pratiche nuovi riconoscibili come test, senza alterare quelli importati. Dopo queste scritture il confronto integrale con la vecchia baseline rilevera correttamente differenze.

6. Caricare un nuovo documento di prova, aprirlo, scaricarlo e verificarlo.
7. Verificare la visibilita propria del nuovo file come admin, creatore e non creatore; non cambiare le regole delle cartelle operative per questa prova.
8. Collegare il nuovo documento a una pratica di prova e controllare la cronologia. Se si prova la protocollazione, usare esclusivamente il clone locale: il numero generato resta nel registro locale e non si deve rinumerare il documento importato.
9. Come admin creare un account locale riconoscibile come test, con indirizzo di prova e nessun dato personale reale; verificarne modifica, cambio ruolo e sospensione. Non usare l'account amministratore o quello di un collega come bersaglio. Anche questo test cambia deliberatamente i dati rispetto alla baseline e va eseguito dopo il confronto automatico.
10. Aprire due sessioni dell'account di prova in browser distinti, quindi sospenderlo come admin. Alla richiesta autenticata successiva entrambe devono essere rifiutate (401); un nuovo login durante la sospensione deve essere rifiutato (403). Riattivarlo: i vecchi token devono restare invalidi e deve funzionare un nuovo login. Le altre sessioni utente devono restare valide.
11. Con il solo account di prova, cambiare password da Profilo: la sessione corrente deve tornare al login e le altre sessioni dello stesso account devono ricevere 401 alla richiesta successiva. Rientrare con la nuova password, poi reimpostarla come admin da Utenti: anche questa volta le sessioni del destinatario devono essere invalidate, mentre l'admin resta collegato. Non usare password o account reali per questi test.
12. Su account di prova, modificare effettivamente i ruoli: le vecchie sessioni devono ricevere 401 e il nuovo login deve mostrare i nuovi ruoli. Risalvare gli stessi ruoli non deve disconnettere. Non modificare il proprio unico admin per questa prova. Le scadenze 2h/24h sono gia coperte dai test a tempo simulato; non cambiare il clock di sistema o i token degli utenti reali per provarle.

I QR dei PDF importati possono contenere ancora il dominio dell'hosting. Per questi test usare le verifiche locali senza rigenerare il PDF per cambiare l'URL.

Annotare profilo, azione, risultato atteso e risultato osservato. Qualsiasi regressione documentale blocca il passaggio al sottopasso successivo.
