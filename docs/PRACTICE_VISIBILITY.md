# Visibilità delle pratiche

La visibilità viene verificata lato server nell'elenco, nel dettaglio, nelle modifiche, nelle note, nei collegamenti e negli archivi condivisi.

| Visibilità | Valore esistente nel database | Accesso |
| --- | --- | --- |
| Solo assegnatario | `authorized` | Admin e utente assegnatario |
| Delegati | `operators` | Admin e utenti con ruolo `delegato` |

Il ruolo `rls` da solo non concede accesso alle pratiche dei delegati. Un RLS può consultare una pratica riservata quando ne è l'assegnatario. Le pratiche riservate senza assegnatario sono visibili soltanto agli admin. Il creatore non conserva un accesso automatico se non è assegnatario.

I valori precedenti `public_summary` non concedono accesso ai non admin: un admin deve scegliere una delle due visibilità attuali. Non esiste una sintesi pubblica della pratica.

Le cartelle automatiche delle pratiche rispettano la visibilità della pratica quando se ne consulta il contenuto come fascicolo. I documenti hanno invece visibilità propria e possono ereditare regole esplicite dalle cartelle, indipendentemente dalla visibilità della pratica: vedere un documento non concede accesso alla pratica collegata. Le condivisioni tra configurazioni non aggirano i controlli sul fascicolo. Telefonate ed email collegate non sono consultabili da chi non può vedere la pratica; le comunicazioni senza collegamento mantengono i permessi esistenti. Per i dettagli vedere `DOCUMENT_VISIBILITY.md`.

## Aggiornamento hosting

1. Caricare le modifiche delle cartelle `app/` e `ui/`.
2. Nel database hosting `Sql1874742_5`, dopo il backup, eseguire `database/updates/20261025_rsu_2026_practice_visibility.sql`.
3. Lo script individua la configurazione pronta chiamata esattamente `RSU 2026` e imposta soltanto le sue pratiche su `authorized`. Non modifica assegnatari, documenti, utenti o altre configurazioni. Può essere rieseguito senza creare duplicati.
4. Ricaricare la pagina e verificare con un admin, un assegnatario, un altro delegato e un RLS.

Non sono necessarie modifiche allo schema del database. L'aggiornamento dei dati non viene eseguito automaticamente caricando il codice.

## Test senza database

`php scripts/test_practice_visibility.php` verifica la matrice dei ruoli, gli elenchi aperti/completi, il blocco dei link diretti e delle modifiche, le condivisioni, le cartelle e i documenti riservati usando soltanto dati simulati in memoria.
