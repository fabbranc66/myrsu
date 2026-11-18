# Visibilità dei documenti e delle cartelle

| Scelta | Valore salvato | Accesso aggiuntivo rispetto ad admin e creatore |
| --- | --- | --- |
| Creatore | `creator` | Nessuno |
| Delegati | `rsu` | Ruolo `delegato`, non il solo ruolo `rls` |
| Membri | `members` | Tutti gli utenti autenticati |
| Ospiti | `public` | Anche i visitatori senza login |

Il creatore è l'utente `uploaded_by` che carica o genera il documento. Admin e creatore mantengono l'accesso. La consultazione non concede automaticamente permessi amministrativi di modifica, pubblicazione o spostamento.

## Eredità dinamica

- Nessuna regola lungo il percorso: vale la visibilità propria del documento.
- Una cartella con una regola esplicita determina la visibilità effettiva dei documenti, anche quando è più ampia di quella originaria.
- Le sottocartelle ereditano dal genitore finché non impostano una regola esplicita diversa. «Mantieni la visibilità di ogni documento» interrompe questa eredità e ripristina la visibilità propria.
- La visibilità propria non viene sovrascritta dagli spostamenti o dalle regole della cartella.
- Restano disponibili i permessi personalizzati per ruolo già esistenti, con distinzione tra lettura e gestione. Questi mantengono i vincoli cumulativi dei genitori: il deploy non amplia automaticamente i vecchi accessi. Le vecchie cartelle riservate senza ruoli non diventano pubbliche: conservano la restrizione. Le nuove scelte esplicite di visibilità possono invece sostituire il genitore, previa conferma quando ampliano gli accessi.
- Gli eventuali vincoli espliciti delle cartelle automatiche a cui il documento è collegato continuano ad applicarsi. Quando più cartelle impongono regole, serve soddisfarle tutte. La sola visibilità della pratica, invece, non limita il documento.
- La cartella «Creatore del documento» può essere attraversata da utenti autenticati, ma ciascuno vede soltanto i propri file, salvo le altre autorizzazioni applicabili. Il creatore della cartella non diventa proprietario dei documenti altrui.

La colonna visibilità mostra il valore effettivo, con l'indicazione «cartella» se ereditato. La modifica del documento conserva e mostra separatamente la sua visibilità propria.

## Conferme e riservatezza

Solo gli utenti con ruolo `admin` possono modificare i permessi delle cartelle. Delegati, RLS, utenti con entrambi i ruoli e creatori non vedono i controlli di modifica e ricevono un errore 403 anche tentando direttamente l'API, indipendentemente dagli altri permessi di gestione. Lettura, creazione di cartelle e spostamenti conservano le regole esistenti.

La visibilità propria di un documento è modificabile solo da admin e dal creatore (`uploaded_by`), anche quando altri utenti hanno permessi globali di aggiornamento o gestione della cartella. Le API di elenco e dettaglio espongono `can_set_visibility`: l'interfaccia nasconde la modifica dei PDF agli altri utenti e disabilita la visibilità anche accedendo direttamente alla scheda o all'editor delle bozze. Gli altri eventuali permessi di modifica dei contenuti dei comunicati restano invariati; tali salvataggi conservano la visibilità esistente. Le regole di ereditarietà delle cartelle restano valide.

Gli spostamenti che ampliano l'accesso richiedono una conferma esplicita, anche via drag & drop. Il server non sposta nulla prima della conferma. La stessa protezione si applica alle modifiche dei permessi di cartella che rendono più visibili file già presenti o contenuti nelle sottocartelle.

Un documento condiviso non rende pubblici il titolo, gli identificativi, le note o la cronologia della pratica riservata. I documenti riservati al creatore non sono mostrati nella cronologia a chi può vedere la pratica ma non il documento. Anteprima, download, verifica e archivi applicano gli stessi controlli.

I documenti autorizzati restano consultabili nell'archivio Documenti anche se la pratica non è visibile, comprese le pratiche condivise o trasferite da altre configurazioni. Se la cartella automatica della pratica è riservata, il documento locale compare nella radice dell'archivio, senza riferimenti alla pratica. I file originari di altri archivi restano nella sezione dei documenti condivisi, senza copie.

## Aggiornamento hosting

1. Eseguire un backup del database `Sql1874742_5`.
2. Nel database selezionato, eseguire `database/updates/20261025_document_visibility.sql`. Aggiunge `creator` all'ENUM dei documenti e una visibilità opzionale alle cartelle, includendo le tabelle di tutte le configurazioni salvate. Non cambia la visibilità propria dei documenti o gli assegnatari delle pratiche.
3. Se l'hosting non consente le procedure SQL, eseguire `database/updates/20261025_document_visibility_commands.sql`: produce i singoli `ALTER TABLE` necessari. Copiare ed eseguire le righe restituite nello stesso database. Il solo generatore non modifica lo schema.
4. Caricare `app/` e `ui/` aggiornate e ricaricare la pagina.

L'aggiornamento dello schema non viene eseguito automaticamente dalle pagine. Gli script sono rieseguibili e non cancellano documenti o cartelle. Non è stata eseguita alcuna modifica ai database durante lo sviluppo.
