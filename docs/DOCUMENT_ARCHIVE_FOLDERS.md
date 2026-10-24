# Cartelle dell'archivio documenti

## Uso
- Nella radice: **Nuova cartella**. Dentro una cartella manuale: **Nuova sottocartella**.
- La cartella **Sicurezza** è predisposta vuota: nessuna classificazione per parole chiave.
- Si apre una cartella cliccando icona o nome, senza pulsante Apri.
- Il caricamento salva direttamente nella cartella manuale o sottocartella aperta, senza trascinamenti successivi. Nella radice salva fuori cartella; dopo il caricamento resta aperta la posizione corrente. Le cartelle in sola lettura non accettano caricamenti.
- Trascinare un documento su una cartella di destinazione o usare **Sposta in cartella**, disponibile anche da tastiera e telefono.
- Per riportarlo fuori dalle cartelle manuali, scegliere Archivio documenti come destinazione o trascinarlo sul percorso.
- Le cartelle automatiche delle pratiche restano governate dai collegamenti esistenti. Non sono destinazioni manuali: nessuna modifica alle pratiche.

## Permessi
- Le cartelle nuove ereditano gli accessi della cartella superiore; alla radice restano validi gli accessi MyRSU preesistenti.
- I permessi personalizzati, per ruoli già esistenti, distinguono lettura e gestione. Nessuna nuova assegnazione di ruoli agli utenti.
- Una sottocartella non aggira un divieto della cartella superiore. Gli amministratori mantengono accesso al livello cartella.
- Gestione richiede anche il permesso MyRSU documents.update. Modificare le assegnazioni richiede inoltre roles.manage e gestione della cartella.
- La cartella aggiunge un vincolo: non concede i permessi originari mancanti sul documento.
- Anche **Documenti pratiche** e ogni singola pratica hanno il pulsante **Permessi**, comprese le pratiche senza documenti. Di default non aggiungono restrizioni: restano validi i permessi attuali dei file.
- I vincoli delle pratiche si applicano ai documenti collegati anche quando vengono aperti da un altro elenco o da una cartella manuale. Un documento collegato a più pratiche deve rispettare i vincoli di tutte.
- Il controllo vale per elenco, scheda, anteprime, miniature, download, verifica QR e accesso ai file tramite altri endpoint. I documenti riservati sono esclusi dalla bacheca pubblica e dalla proiezione pubblica.
- La regola .htaccess sui PDF è necessaria per controllare i link diretti. L'accesso HTTP diretto a storage/private è negato; gli originali restano accessibili dagli endpoint autorizzati.
- La protezione non revoca copie già scaricate o esportate.

## Database e deploy manuale
Sul database locale la migrazione è già applicata. Su hosting eseguire **prima** database/migrations/20261024_document_folders.sql nel database previsto per MyRSU, dopo il normale backup. È ripetibile e aggiunge solo tre tabelle; non modifica documenti, firme, protocolli, utenti, ruoli o collegamenti esistenti.

Per estendere i permessi alle cartelle automatiche, eseguire anche **database/migrations/20261024_document_practice_folder_permissions.sql**, prima di caricare app/ e ui/ aggiornate. Chi ha già applicato la prima SQL deve eseguire soltanto questa integrazione. Aggiunge una chiave tecnica alle cartelle e le voci automatiche; non assegna nuovi ruoli e non modifica i documenti o i collegamenti.

Non eseguire database/schema.sql sul database esistente: è lo schema per nuove installazioni.

Caricare insieme:
- .htaccess
- app/Core/Application.php e app/Core/FileResponse.php
- app/Repositories/DocumentRepository.php e app/Repositories/DocumentFolderRepository.php
- app/Services/DocumentFolderAccessService.php e app/Services/DocumentFolderService.php
- app/Controllers/Api/DocumentFolderController.php, DocumentController.php, DocumentVerificationController.php, DocumentCommentController.php, ComunicatoController.php, MeetingProjectionController.php e EmailController.php
- routes/api.php
- ui/documents.html, ui/documents.js e ui/icons.js
- ui/document-archive.js, ui/document-archive-model.js e ui/document-archive.css
- ui/document-folder-actions.js e ui/document-folder-permissions.js
- ui/document-verify.html e ui/document-verify.js

I file fisici non cambiano posizione. Gli accessi precedenti restano invariati per i documenti senza nuove restrizioni.

## Verifiche
- node scripts/test_document_archive.mjs
- php scripts/test_document_folders.php (solo database locale: fixture temporanee con pulizia finale, utenti/ruoli esistenti non modificati)
- Lint PHP dei file modificati.
