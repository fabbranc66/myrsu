# Istruzioni Codex per MioDel

## Modo di lavorare

- Rispondi in italiano, in modo breve e operativo.
- Fai una cosa alla volta: niente funzionalita extra non richieste.
- Prima leggi il codice e le regole esistenti, poi modifica.
- Non inventare campi, tabelle, ruoli o flussi: verifica in `database/schema.sql`, nelle migration e nei repository/service gia presenti.
- Mantieni le modifiche piccole, leggibili e facilmente deployabili manualmente su hosting.
- Non cancellare o sovrascrivere modifiche non tue.

## Architettura progetto

- Backend custom PHP API-first, senza viste HTML lato backend.
- API JSON sotto `/api/v1`.
- Routing principale in `routes/api.php`.
- Controller in `app/Controllers/Api`: gestiscono request/response, validazione d'ingresso e status code.
- Service in `app/Services`: logica applicativa, generazione documenti/PDF, workflow.
- Repository/Model dove presenti: accesso dati.
- UI operative/test in `ui`: HTML, JS e CSS separati per modulo.
- Database locale: `myrsu`.
- Hosting reale: deploy manuale dei file modificati, database `Sql1874742_5`.

## Regole codice

- Usa `declare(strict_types=1);` nei file PHP nuovi.
- Mantieni responsabilita singola per file.
- Evita file monolitici; soft limit circa 300 righe per file.
- Nomi tecnici in inglese: `users`, `documents`, `practices`, `votings`, ecc.
- Messaggi utente/API in italiano quando il progetto lo fa gia.
- Errori JSON coerenti: 400, 401, 403, 422, 500 secondo il caso.
- Validazioni e permessi sempre lato server.
- Commenti minimi, solo quando chiariscono logica non ovvia.
- Niente overengineering: preferisci soluzione semplice e funzionante.

## UI

- Mobile first obbligatorio: progettare e verificare prima il layout su smartphone, a partire da 320 px, con testi leggibili, controlli utilizzabili al tocco e nessuna sovrapposizione; adattare poi a tablet e desktop.
- La UI e' uno strumento operativo collegato alle API, non una landing page.
- Ogni pagina deve mostrare o aggiornare la risposta JSON quando il pattern esiste gia.
- JS separato per pagina/funzione; usa helper comuni solo quando riducono duplicazione reale.
- CSS separato per modulo; condividi solo layout o componenti comuni.
- Mantieni controlli chiari, densi e pratici per uso ripetuto.

## Dati e sicurezza

- Non cancellare mai durante reset operativi:
  - `users`
  - `roles`
  - `permissions`
  - `role_user`
  - `permission_role`
  - `gdpr_consents`
  - `institutional_contacts`
- Proteggi endpoint con auth/ruoli coerenti con le feature esistenti.
- Usa bcrypt/token/auth gia presenti; non introdurre meccanismi paralleli senza richiesta esplicita.
- Non committare segreti o valori reali di `.env`.

## Verifica minima

- Dopo modifiche PHP, esegui almeno il lint:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object {
  php -l $_.FullName
}
```

- Quando tocchi un endpoint, verifica anche l'endpoint coinvolto se l'ambiente locale lo consente.
- Health check utile:

```powershell
Invoke-RestMethod -Method Get -Uri "http://localhost/miodel/api/v1/health"
```

- Per reset operativo usa solo:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\reset_operational_db.ps1
```

## Comunicazione

- Prima di modificare file, spiega brevemente cosa stai cambiando.
- A fine lavoro riassumi:
  - cosa e' stato cambiato;
  - quali verifiche sono state fatte;
  - eventuali limiti o passaggi manuali rimasti.
- Se la richiesta e' ambigua ma si puo' procedere in sicurezza, scegli l'opzione piu semplice e dichiarala.
- Se serve una decisione su schema dati, permessi o cancellazione dati, chiedi prima.

## Prossimi passi da ricordare

Alla prossima sessione di lavoro su MyRSU, prevista per il 26 ottobre 2026, ricorda all'utente lo stato di questi punti, senza applicare automaticamente quelli ancora da fare:

1. RISOLTO, confermato dall'utente il 25 ottobre 2026: documenti Delegati delle pratiche riservate visibili a Nando. La causa era il solo ruolo `rls` associato al suo account, che deve essere anche `delegato`. La UI consente ora la scelta `Delegato + RLS` da elenco e modifica utente, conservando entrambi i ruoli. L'utente prevede un solo RLS; non estendere l'accesso ai documenti Delegati al solo ruolo RLS. Non riaprire questo punto senza nuove segnalazioni.
2. RISOLTO: permessi delle cartelle modificabili solo da admin. API protetta nei test locali; pulsanti nascosti ai non admin anche su hosting, confermato dall'utente il 25 ottobre 2026 dopo il caricamento di `app/Controllers/Api/DocumentFolderController.php`. Non riaprire senza nuove segnalazioni.
3. OK CON RISERVA SU HOSTING, confermato dall'utente il 25 ottobre 2026: visibilita propria dei file modificabile solo da admin e creatore (`uploaded_by`). API con controllo di proprieta, controllo UI `can_set_visibility`, modifica dei PDF nascosta agli altri, visibilita disabilitata anche nelle schede e bozze. Conservati lettura, ereditarieta e diritti esistenti sui contenuti dei comunicati. L'utente deve ancora testare altre combinazioni: non considerare questo punto definitivamente chiuso fino alla sua conferma finale.

## Pulizia locale del 16 novembre 2026

Su richiesta esplicita dell'utente sono stati eliminati temporanei, output e documenti PDR di lavoro (cancellazione confermata), prove grafiche, estrazioni normative intermedie, script PDR usa-e-getta, dump importati, vecchi backup SQL locali e rapporti di collaudo sotto `storage/private/relocation` e `storage/private/security-tests`. Non usare i riferimenti storici a questi rapporti come se i file esistessero ancora: prima di nuovi confronti acquisire un riferimento aggiornato senza cancellare dati operativi.

Codice, configurazioni e chiavi, documenti operativi, originali, archivi delle configurazioni e risorse normative `docs/*_work/clean` sono stati preservati e confrontati tramite hash. Conservati anche test di regressione, relative risorse e utility di manutenzione. Nessuna operazione sul database o sull'hosting; nessun commit/push. Apache e MySQL risultavano spenti durante la pulizia: nessun collaudo HTTP successivo dimostrato.

## Passaggio a MioDel

Il nome visibile resta Mio Delegato; la cartella prevista e `C:\xampp2\htdocs\miodel`, con URL hosting `/miodel/`. Il database resta `myrsu` in locale e `Sql1874742_5` sull'hosting. Non cambiare chiavi di firma, identificatori tecnici `MyRsu*` o chiavi browser `myrsu_*`: servono alla compatibilita.

Procedura in `docs/PASSAGGIO_MIODEL.md`; rinomina protetta con `scripts/rename_project_to_miodel.ps1`. I tentativi del 16 novembre 2026 sono stati bloccati da Windows per cartella in uso: non presumere la rinomina effettuata senza controllare il percorso corrente. Per completarla vanno chiusi i processi che usano la cartella; non forzare o duplicare il progetto. Il vecchio `/myrsu/` deve restare come sola cartella di reindirizzamento 308 per PDF e QR gia emessi. Non riscrivere inventari storici con percorsi assoluti.

Prima/dopo il passaggio usare `scripts/test_project_relocation.php` con il riferimento piu recente sotto `storage/private/relocation`. Dopo la rinomina generare un nuovo inventario documentale e passarlo esplicitamente a `scripts/test_local_pdf_branding_verification.php`. Hosting da gestire manualmente dopo il collaudo locale, senza importare il database locale o copiare `.env` tra ambienti. Nessun commit/push automatico.

## Roadmap generale memorizzata

Consultare `docs/ROADMAP_MYRSU.md`, salvata su richiesta dell'utente il 25 ottobre 2026, prima di proporre le prossime evoluzioni. Priorita: utenti/ruoli/sessioni e configurazione sicura; votazioni prima di impieghi rilevanti; permessi documentali indiretti; backup e ripristino; rilasci; versioni documentali e schema delle configurazioni; usabilita e PDR; nuove funzioni solo dopo il consolidamento.

La roadmap non autorizza l'esecuzione automatica degli interventi. Conservare gli esiti gia confermati sopra e concordare le decisioni sui dati e sui permessi prima di modificare.

## Lavoro sulla sicurezza: implementazione locale e collaudo hosting

L'utente ha richiesto di preservare il flusso documentale e collaudare prima/dopo esclusivamente in locale. Il clone e allineato: 110 tabelle, 5 utenti, 39 documenti nelle due configurazioni. Upload hosting, SMTP e IMAP sono disabilitati tramite i primi valori vuoti nel solo `.env` locale; non riabilitarli ne copiarlo sull'hosting senza richiesta.

Primo sottopasso della roadmap implementato in locale: modifica utenti/ruoli solo admin; consultazione e profilo personale invariati. Baseline e test descritti in `docs/TEST_SICUREZZA_LOCALE.md`. Test automatico di regressione `scripts/test_local_admin_security.php --verify storage/private/security-tests/baseline_20261025_170355.json`: controlla file, dati, letture e 20 richieste amministrative innocue, usando soltanto propri token temporanei e normali log locali. Non modifica account o documenti importati.

Secondo sottopasso implementato solo in locale il 25 ottobre 2026: ultimo admin attivo protetto da sospensione, eliminazione e rimozione del ruolo. Guardia transazionale condivisa in `AdminAccountProtectionRepository`, senza migrazioni o modifiche documentali. Test `scripts/test_last_active_admin.php` su dati in memoria e `scripts/test_last_admin_local_locks.php` su due connessioni locali, soli lock e ID inesistente; account importati invariati.

Terzo sottopasso implementato solo in locale il 25 ottobre 2026: `Auth` rifiuta utenti non attivi a ogni nuova richiesta; sospensione e revoca di tutti i token API sono atomiche in `UserRepository`. La riattivazione richiede nuovo login e invalida anche eventuali token legacy; salvare un utente gia attivo non lo disconnette. `TokenRepository::create` ricontrolla lo stato con lock nell'inserimento per evitare login concorrenti alla sospensione. Test `scripts/test_session_suspension.php` su dati in memoria e `scripts/test_session_local_locks.php` su due connessioni locali con solo lock e token proprio poi rimosso. Regressione documentale invariata; nessuna migrazione o modifica di account/sessioni importati. Il blocco vale dalla richiesta successiva, non cancella dati gia consultati.

Quarto sottopasso implementato solo in locale il 25 ottobre 2026: cambio password non vuoto e revoca di tutti i token API atomici in `UserRepository`, dal Profilo e tramite reset admin. La UI torna al login se cambia la password propria; il reset di un altro account conserva la sessione admin. Dati solo anagrafici e password vuota/omessa non disconnettono. `AuthService` passa a `TokenRepository::create` l'hash verificato, confrontato in modo binario nell'inserimento sotto lock contro login concorrenti al reset. Test sessioni estesi a 64 controlli, UI in `scripts/test_password_sessions.mjs` e `scripts/test_user_roles.mjs`, lock/confronto binario MySQL e regressione documentale superati; password/account/sessioni importati invariati. Nessuna migrazione o deploy.

Quinto sottopasso confermato e implementato solo in locale il 25 ottobre 2026: 2 ore di inattivita API e durata massima 24 ore, su `created_at`/`last_used_at` esistenti. `TokenRepository` applica la stessa condizione a lettura e touch; `Auth` rifiuta il touch se nel frattempo il token e scaduto/revocato. Revoca atomica dei token al cambio effettivo dei ruoli, anche assegnazione diretta, con lock utente e rollback; stessi ruoli risalvati non disconnettono. UI del cambio ruolo proprio torna al login e previene operazioni combinate incompatibili. Test `scripts/test_session_policy.php`: 49 controlli; scadenze/touch verificati anche su MySQL locale solo sul token di prova. Nessuna migrazione.

Durante il collaudo l'utente ha confermato un proprio cambio password e nuovo login: la differenza negli account/sessioni non e una regressione da annullare. Non ripristinare password o sovrascrivere la baseline originale. La modalita `--verify-documents` esclude solo il confronto storico account, mantenendo l'invarianza durante il test e i controlli documentali; sulla prima baseline rileva ora correttamente anche la successiva cartella pubblica aggiunta dall'utente. Per il riferimento corrente usare il nuovo confronto completo indicato al settimo sottopasso sotto.

Sesto sottopasso implementato solo in locale il 25 ottobre 2026: `ApiErrorHandler` centralizza gli errori server con messaggio generico e `error_id`; nessun dettaglio/source/stack al client anche con debug attivo. Tipo, messaggio, file, riga e trace senza argomenti nei log PHP correlati, JSON su una riga. `Application::run` e gestore globale in `index.php` condividono il renderer; display degli errori PHP disabilitato durante la richiesta, logging abilitato. Anche il messaggio parziale in `FundController::protocolContractSafe` e sicuro senza cambiare il flusso di protocollazione. Messaggi 4xx e status conservati. `scripts/test_api_errors.php`: 91 controlli senza DB; HTTP locali e regressione documentale superati. Nessuna migrazione o deploy. Restano protezione web di file/log/percorsi interni e configurazione PHP del server per guasti precedenti all'esecuzione di index.

Settimo sottopasso implementato solo in locale il 25 ottobre 2026: `.htaccess` blocca accesso HTTP a file nascosti/metadati, directory backend/config/database/docs/routes/scripts/vendor/artifacts, manifest interni, SQL/log/backup e mantiene storage privato. Restano Authorization, routing API, PDF autorizzati, `ui/vendor` e `.well-known`. `scripts/test_local_web_protection.php`: 72 controlli HTTP superati; nessuna lettura di contenuti sensibili o modifica documentale. Prima dei blocchi alcuni file risultavano HTTP 200 in locale; hosting non verificato, nessuna conclusione automatica su compromissioni o rotazioni di chiavi/password.

L'utente ha confermato una cartella pubblica aggiunta durante i test: differenze iniziali solo in `cfg_2_document_folders`, `cfg_2_document_folder_permissions` e tre risposte di elenco cartelle. Nessun ripristino. Prima baseline conservata; nuovo controllo iniziale e riferimento completo `storage/private/security-tests/baseline_20261025_215230.json`. Riferimento storico precedente al successivo documento di prova 42: `scripts/test_local_admin_security.php --verify storage/private/security-tests/baseline_20261025_215230.json`: verificati 78 file, 24 tabelle, 28 risposte e 20 protezioni amministrative, account compresi. Non sovrascrivere baseline o ignorare nuove differenze non spiegate. Il test ora riporta soli riferimenti/campi modificati senza contenuti sensibili.

Ottavo sottopasso implementato solo in locale il 25 ottobre 2026: `config/database.php` non contiene piu il valore di ripiego della password; `DB_PASSWORD` e obbligatoria nell'ambiente (vuota solo se impostata esplicitamente). Nel `.env` locale la variabile mancava: trasferito lo stesso valore effettivo dal codice, senza mostrarlo o cambiare password DB/account. Il `.env` resta ignorato da Git e non va caricato sull'hosting; prime quattro impostazioni vuote per isolamento esterno preservate. `.env.example` gia completo e privo della credenziale locale, non modificato. `scripts/test_database_config.php`: 14 controlli; errori API, blocchi HTTP e regressione completa sul riferimento corrente superati. Nessuna migrazione, rotazione o modifica alle chiavi di firma. Prima di un futuro deploy verificare `DB_PASSWORD` nell'ambiente hosting: non presumere che sia gia impostata. Il vecchio valore puo restare nella cronologia Git, non riscritta.

Preparazione hosting manuale confermata dall'utente il 25 ottobre 2026: ha aggiunto al `.htaccess` remoto la sola regola per file nascosti con eccezione `.well-known` (verificati `.env` 403 e health 200), inserito `DB_PASSWORD` nel `.env` remoto con la credenziale gia in uso sull'hosting, sostituito solo la riga password nel `config/database.php` remoto eliminando il ripiego. Login e Documenti funzionanti confermati. Non ha caricato il resto della roadmap; nessun accesso/deploy remoto dell'assistente. Non copiare `.env` tra ambienti e non sovrascrivere configurazioni remote specifiche senza verificarle. Il blocco web completo locale delle directory backend non e ancora applicato sull'hosting.

Nono sottopasso implementato solo in locale il 25 ottobre 2026, autorizzato dall'utente anche considerando i dati demo: `config/signing.php` richiede `MYRSU_SIGNING_SECRET`; `DocumentSignatureService` rifiuta vuoto, chiavi inferiori a 32 caratteri e vecchio default pubblico, senza ripieghi automatici. Chiave locale gia esplicita mantenuta invariata e non mostrata; `.env` non modificato in questo sottopasso. `.env.example` lascia il campo vuoto invece di `change-me`. Algoritmo/payload/formato invariati. La verifica API attuale confronta firma registrata e checksum; non e stata ridisegnata. Nessuna rotazione, rigenerazione o migrazione; originali/PDF/QR/metadati preservati. `scripts/test_document_signatures.php`: 25 controlli, errori API e regressione completa sulla baseline corrente superati. Successivamente l'utente ha caricato i due file della firma sull'hosting, mantenendo la sua chiave esistente; login, verifica del preesistente e nuova emissione da immagine confermati funzionanti.

Stato hosting confermato dall'utente: caricati app/ e ui/ complete e index.php; .htaccess remoto completato mantenendo le regole esistenti. Verificati .env/config/database.php/database/schema.sql HTTP 403 e health 200, login, Documenti e verifica PDF funzionanti. Nuovo PDF da immagine n.42 verificato sia in locale sia in hosting: valid=true e checksum_ok=true, record distinti nei due ambienti. Nessun deploy remoto diretto dell'assistente, nessuna migrazione SQL o copia del .env locale; configurazioni remote specifiche e chiavi esistenti mantenute.

Il PDF di prova locale n.42 e successivo alla baseline_20261025_215230.json: il riferimento resta storico e puo rilevare questa variazione attesa. Non eliminare il documento o ignorare altre differenze; prima dei prossimi confronti completi controllare i cambiamenti e salvare un nuovo riferimento, conservando i precedenti.

Collaudo hosting confermato dall'utente il 26 ottobre 2026 sull'account demo Topo: menu Utenti assente e modifica diretta utente bloccata per non admin; POST amministrativi innocui su /users e /users/2147483647/roles rispondono 403. Sospensione invalida la sessione e impedisce il login, riattivazione permette un nuovo login. Reset password da admin e cambio dal Profilo invalidano le sessioni del destinatario, rifiutano la vecchia password e ammettono la nuova; l'admin resta collegato. Cambi Delegato -> Membro -> Delegato invalidano la sessione precedente, confermato anche al ripristino. Topo e tornato Delegato; il passaggio a Membro ha rimosso le associazioni di sigla/logo, da ripristinare solo con valori forniti dall'utente.

Menu Membro corretto il 26 ottobre 2026 e confermato dall'utente sull'hosting: niente Incontri; in Amministrazione solo Profilo. Modificati ui/app/index.html, ui/app/js/app.js e ui/auth-menu.js; test isolato scripts/test_member_menu.mjs e regressione ruoli superati. Nessun cambiamento ai permessi API o ai flussi documentali.

Ancora da fare: risalvataggio degli stessi ruoli e modifiche solo anagrafiche senza disconnessione nel collaudo manuale, ulteriori combinazioni/sessioni, restante collaudo documentale con riserva, configurazione PHP/log dell'hosting e valutazione esposizioni/rotazioni. Scadenze 2h/24h e ultimo admin sono testati localmente, non dimostrati da questi riscontri manuali sull'hosting. La prima priorita non e interamente chiusa. Non effettuare altri deploy o operazioni sui dati senza autorizzazione.

Chiarimento dell'utente: menu Utenti solo admin. Per i non admin la modifica dei dati personali passa esclusivamente da Profilo. Non aggiungere il menu Utenti a delegati/RLS e non suggerire l'elenco utenti come percorso di collaudo per Nando. La correzione del menu Membro sopra non estende l'accesso alla gestione utenti.
