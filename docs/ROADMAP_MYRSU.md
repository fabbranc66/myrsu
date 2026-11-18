# Roadmap generale MyRSU

Memorizzata su richiesta dell'utente il 25 ottobre 2026, a seguito della revisione generale del progetto.

## Indirizzo

Consolidare il gestionale esistente prima di aggiungere altre funzioni. Conservare l'architettura PHP API-first e procedere con interventi piccoli, verificabili e facilmente distribuibili. Non avviare una riscrittura generale o cambiare framework come primo intervento.

La memorizzazione della roadmap non autorizza automaticamente implementazioni, operazioni sui database, deploy, commit o push. Prima di ogni intervento verificare lo stato corrente e concordare le decisioni su permessi e dati.

## Priorita e risultati attesi

| Ordine | Priorita | Area | Risultato atteso |
| --- | --- | --- | --- |
| 1 | Immediata | Utenti, ruoli, sessioni e configurazione sicura | Nessuna elevazione non autorizzata; sospensione efficace; segreti e dettagli tecnici protetti |
| 2 | Immediata prima di votazioni rilevanti | Votazioni | Dati pubblici separati, distribuzione delle credenziali controllata e casi limite verificati |
| 3 | Alta | Permessi documentali indiretti | Spostamenti e collegamenti non aggirano le regole concordate |
| 4 | Alta | Backup e ripristino | Recupero riuscito di database e file su ambiente separato |
| 5 | Alta | Rilascio stabile | Codice versionato, pacchetto riconoscibile, migration esplicite e rollback documentato |
| 6 | Successiva | Versioni documentali e aggiornamento archivi | Storico ricostruibile e configurazioni coerenti nello schema |
| 7 | Successiva | Ricerca, usabilita e PDR | Uso quotidiano piu semplice e calcoli verificabili |
| 8 | Dopo il consolidamento | Nuove funzioni | Evoluzioni scelte in base all'utilizzo reale |

## 1. Utenti, ruoli e sessioni

Primo sottopasso implementato esclusivamente in locale il 25 ottobre 2026: modifica degli account e dei ruoli riservata agli admin, controlli UI coerenti e consultazioni/profilo personale preservati. Verifica automatica prima/dopo riuscita su 78 file, 24 tabelle documentali e 28 risposte API, con 20 controlli amministrativi. Dettagli e collaudo manuale in `docs/TEST_SICUREZZA_LOCALE.md`. Restano da realizzare gli altri sottopassi elencati sotto; nessun deploy autorizzato.

Secondo sottopasso implementato solo in locale il 25 ottobre 2026: protezione transazionale dell'ultimo amministratore attivo contro sospensione, eliminazione e rimozione del ruolo. Controlli funzionali su dati in memoria e lock verificati su due connessioni MySQL locali; nessun account importato modificato, nessuna migrazione e nessun deploy. Restano pendenti sessioni, revoche e gli altri interventi.

Terzo sottopasso implementato solo in locale il 25 ottobre 2026: verifica dello stato utente su ogni nuova autenticazione, sospensione e revoca di tutti i token API nella stessa transazione, riattivazione con nuovo login e controllo atomico dello stato all'emissione dei token. Test funzionali in memoria, lock MySQL e confronto documentale prima/dopo superati; account importati e sessioni preesistenti invariati. Nessuna migrazione o modifica al flusso documentale, nessun deploy. Restano da definire scadenze e revoche al cambio password/ruoli.

Quarto sottopasso implementato solo in locale il 25 ottobre 2026: password e revoca di tutte le sessioni del destinatario atomiche, sia dal Profilo sia dal reset admin. Nuovo login obbligatorio; l'admin che resetta un altro utente resta collegato. Controllo dell'hash corrente all'emissione per evitare login con credenziali superate durante il reset. Test dei controller su dati in memoria, UI, confronto binario/lock MySQL e regressione documentale superati senza modificare password o sessioni importate. Nessuna migrazione e nessun deploy.

Quinto sottopasso confermato e implementato solo in locale il 25 ottobre 2026: scadenza dopo 2 ore senza richieste autenticate, limite assoluto 24 ore e revoca atomica al cambio effettivo dei ruoli, mantenendo valide le sessioni se i ruoli restano gli stessi. Confini temporali, touch, rollback, ultimo admin e UI testati. Nessuna migrazione. Durante il collaudo l'utente ha cambiato password e rifatto login: baseline storica non sovrascritta; confronto documentale esplicito `--verify-documents` conserva i controlli durante il test. Dettagli in `docs/TEST_SICUREZZA_LOCALE.md`.

Sesto sottopasso implementato solo in locale il 25 ottobre 2026: gestore comune degli errori server con messaggio generico e `error_id`, dettagli e stack solo nei log PHP senza argomenti delle funzioni. Coperti dispatcher, eccezioni prima del run e messaggio parziale del modulo Fondi; 4xx e status originali conservati. Debug non espone dettagli e warning PHP non contaminano le risposte. 91 controlli isolati, verifiche HTTP e confronto documentale prima/dopo superati. Nessuna migrazione, variazione dei flussi o deploy; accesso web ai percorsi interni e configurazione PHP dell'hosting restano da verificare separatamente.

Settimo sottopasso implementato solo in locale il 25 ottobre 2026: protezione `.htaccess` di file nascosti/metadati, backend, configurazioni, database, script, documentazione tecnica, vendor backend, artefatti e backup/log. 72 controlli HTTP GET/HEAD superati, UI/API/PDF autorizzati conservati. L'utente ha confermato una nuova cartella pubblica che spiegava le differenze iniziali nelle sole cartelle/permessi: conservata, prima baseline intatta e nuovo riferimento `baseline_20261025_215230.json` verificato integralmente. Nessuna migrazione o deploy; verifiche e possibili rotazioni dei segreti restano separate.

Ottavo sottopasso implementato solo in locale il 25 ottobre 2026: rimosso il valore di ripiego della password da `config/database.php`, ora `DB_PASSWORD` e obbligatoria nell'ambiente. Poiche mancava nel locale, il valore effettivo e stato trasferito nel solo `.env` ignorato, senza cambiare credenziali DB/account o isolamento SMTP/IMAP/hosting. 14 controlli di configurazione, errori API, blocchi web e regressione operativa superati. Prima di un futuro deploy verificare la variabile sull'hosting, senza copiare il `.env` locale. Nessuna migrazione, rotazione, riscrittura della cronologia Git o modifica alle chiavi di firma.

Successivamente l'utente ha preparato manualmente l'hosting con soli tre interventi: blocco dei file nascosti nel `.htaccess`, variabile `DB_PASSWORD` nel `.env` remoto e sostituzione della sola riga password nel config remoto. Confermati 403/200, login e Documenti funzionanti. Non considerare distribuiti gli altri sottopassi della roadmap; dettagli in `docs/TEST_SICUREZZA_LOCALE.md`.

Nono sottopasso implementato solo in locale il 25 ottobre 2026: ripiego deterministico della firma eliminato, variabile `MYRSU_SIGNING_SECRET` obbligatoria e controlli su valore vuoto/corto o vecchio default pubblico. La chiave locale esplicita gia presente e mantenuta, senza rotazioni, rigenerazioni o migrazioni. Template senza segreto di esempio utilizzabile. 25 controlli sintetici, gestione errori e regressione documentale completa superati; vecchi PDF, QR e metadati invariati. Prima di un futuro deploy verificare separatamente la configurazione di firma nell'hosting.

Rilascio confermato dall'utente: firma pubblicata mantenendo la chiave hosting e collaudata su preesistente e nuovo PDF da immagine. Caricati anche app/, ui/ e index.php, completato .htaccess; file interni 403, health 200, login e Documenti funzionanti. Nessun SQL o copia del .env tra ambienti. Il PDF locale di prova n.42 e una variazione attesa successiva alla baseline precedente, da considerare nel prossimo controllo iniziale senza cancellazioni o sovrascritture.

Aggiornamento del 26 ottobre 2026, successivo agli stati storici sopra: l'utente ha confermato sull'hosting i blocchi UI/API amministrativi per non admin, sospensione e riattivazione, revoche al reset/cambio password e al cambio effettivo dei ruoli usando Topo. Topo e tornato Delegato dopo il test Membro; menu Membro corretto e confermato (solo Profilo in Amministrazione, niente Incontri). Dettagli in docs/TEST_SICUREZZA_LOCALE.md. Restano le verifiche di mancata disconnessione al salvataggio senza cambio ruoli/password, altre combinazioni, configurazione PHP/log e valutazione separata delle esposizioni/rotazioni. Durate 2h/24h e ultimo admin verificati nei test locali, non provati manualmente sull'hosting. La priorita 1 non e ancora interamente chiusa; nessun ulteriore deploy o intervento sui dati automatico.

- Assegnazione dei ruoli e modifica degli account ora richiedono anche il ruolo admin, oltre ai permessi esistenti. I vecchi permessi DB non sono stati rimossi: non confonderli con la possibilita di superare la nuova guardia. Rifiuto 403 dei POST non admin confermato sull'hosting il 26 ottobre 2026.
- Operazioni amministrative e protezione ultimo admin implementate localmente come sopra; restano il collaudo manuale dell'utente e l'eventuale decisione su deleghe limitate.
- Sospensione, rifiuto delle sessioni precedenti e riattivazione con nuovo login confermati manualmente sull'hosting con Topo; restano ulteriori combinazioni e sessioni multiple.
- Scadenze 2h/24h testate localmente; revoche al cambio password/ruoli confermate anche sull'hosting. Restano salvataggi senza cambiamento e ulteriori aspetti di configurazione sicura; nessun nuovo deploy automatico.
- Valore di ripiego della password DB eliminato anche nel config remoto, con variabile hosting predisposta dall'utente. Restano valutazione di eventuali esposizioni/rotazioni; non riportare credenziali nei documenti o nei messaggi.
- Ripiego deterministico della chiave di firma eliminato e distribuito mantenendo la chiave hosting esistente; verifica di vecchio e nuovo PDF confermata. Eventuali rotazioni restano una decisione separata: non rigenerare automaticamente PDF o metadati.
- Dettagli delle eccezioni e protezione web dei percorsi interni implementati e testati localmente come sopra. Restano verifica delle regole/file/log effettivi e configurazione PHP dell'hosting, senza presumere che il provider li blocchi e senza deploy automatico.

Riferimenti principali: `app/Controllers/Api/RoleController.php`, `app/Controllers/Api/UserController.php`, `app/Core/Auth.php`, `app/Repositories/TokenRepository.php`, `database/schema.sql`, `app/Core/Application.php`.

## 2. Votazioni

- Separare il dettaglio pubblico da quello amministrativo: attualmente `withDetails()` include i token ed e usato anche dai percorsi pubblici.
- Riesaminare la distribuzione pubblica dei token inutilizzati.
- Definire e verificare il modello di avente diritto e il vincolo di voto unico: l'identificativo locale facoltativo non garantisce da solo una persona/un voto.
- Distinguere identificazione dell'avente diritto, distribuzione delle credenziali e contenuto del voto.
- Collaudare doppio invio, concorrenza, revoca, apertura/chiusura e riconciliazione del voto misto.
- Non descrivere il modulo come garanzia tecnica di voto segreto e univoco senza revisione dedicata.

Riferimento principale: `app/Controllers/Api/VotingController.php`.

## 3. Permessi documentali indiretti

La modifica diretta della visibilita propria e gia limitata ad admin e creatore; l'utente ne ha confermato il funzionamento sull'hosting con riserva.

- Nella revisione e stato riprodotto esclusivamente su mock uno spostamento da parte di un non creatore, con `can_set_visibility=false`, che cambia la visibilita effettiva da `public` a `rsu` tramite la cartella di destinazione.
- Concordare se gli altri utenti possano riordinare documenti solo quando il pubblico autorizzato non cambia.
- Riesaminare anche collegamenti e scollegamenti dalle pratiche, per gli effetti sulle cartelle automatiche e per l'accesso all'oggetto collegato.
- Verificare ampliamenti e restrizioni, admin, creatore, non creatore, Delegato + RLS e contenuti condivisi tra configurazioni.
- Non considerare la sola conferma dello spostamento equivalente all'autorizzazione a cambiare i destinatari.

Riferimenti principali: `app/Services/DocumentFolderService.php`, `app/Services/DocumentFolderPolicyService.php`, `app/Controllers/Api/PracticeController.php`.

## 4. Backup e ripristino

- Conservare il dump preventivo gia presente nei trasferimenti, ma non confonderlo con un ciclo completo di backup.
- Coordinare database, originali, PDF e altri allegati; verificare consistenza e completezza della copia.
- Prevedere una copia separata dall'installazione, controllo dell'esito, conservazione e pulizia.
- Eseguire un ripristino autorizzato su ambiente separato e documentarne il risultato.
- Distinguere configurazione modificabile, condivisione per riferimento e backup di ripristino.

Riferimenti: `app/Services/ConfigurationBackupService.php`, `docs/TODO_BACKUP.md`, `docs/SAVED_CONFIGURATIONS.md`.

## 5. Rilasci e test

- Consolidare le modifiche nel repository solo dopo verifica e autorizzazione dell'utente; non includere segreti o artefatti riservati.
- Identificare ogni rilascio con versione, elenco dei file/cartelle, eventuali migration, test minimi e procedura di rollback.
- Evitare interfaccia nuova e backend precedente nello stesso rilascio.
- Riallineare il README alle funzioni e al deploy manuale effettivi: la revisione non ha trovato il workflow automatico citato nel README.
- Preparare una procedura unica per i test isolati e una separata per i test di integrazione su database dedicato, mai automaticamente sui dati operativi.
- I test rieseguiti nella revisione comprendevano 115 verifiche documenti/cartelle, 94 verifiche pratiche, eliminazione utenti e sette script JavaScript. Non equivalgono al collaudo completo di tutti i moduli.

## 6. Documenti, protocollo e configurazioni

- Distinguere bozza, versione emessa, revisione successiva e annullamento; conservare le versioni documentali rilevanti invece della sola riscrittura dell'originale e del PDF.
- Introdurre una versione dello schema e una procedura controllata di aggiornamento delle configurazioni: il controllo dei soli nomi delle tabelle non verifica colonne e vincoli.
- Mantenere condivisioni e trasferimenti senza duplicazioni inutili e con origine sempre identificata.
- Collaudare numerazione, concorrenza e ricostruzione dello storico di protocollo.
- Non riscrivere ora l'architettura degli archivi e non rendere immutabili le configurazioni di lavoro, che l'utente vuole poter modificare.

## 7. Usabilita, manutenzione e PDR

- Concentrarsi sul nucleo pratiche, documenti, protocollo e comunicazioni collegate.
- Introdurre ricerca, filtri e paginazione lato server senza alterare i controlli di accesso.
- Ridurre gradualmente i file piu grandi separando calcolo, interfaccia e chiamate API; evitare un refactor generale.
- Nel PDR distinguere profilo pubblicato e versionato dell'accordo da simulazione personale locale.
- Chiarire che lo storage del browser non equivale alla sincronizzazione nell'account.
- Aggiungere test numerici per baseline, arrotondamenti, soglie, periodi e combinazioni del premio, senza cambiare le regole concordate.

## 8. Evoluzioni successive

Valutare nuove funzioni, reporting, automazioni e annotazioni del video esterno solo dopo il consolidamento e sulla base delle esigenze effettive. Per le annotazioni conservare il PDF originale; il relativo promemoria resta in `docs/TODO_VIDEO_ANNOTATIONS.md`.

## Limiti della revisione

Analisi del codice locale e test isolati senza connessioni reali ai database. Non sono state applicate correzioni, eseguiti exploit sull'hosting o modificati permessi reali durante la revisione. Le verifiche sull'ambiente operativo richiedono passaggi esplicitamente autorizzati.
