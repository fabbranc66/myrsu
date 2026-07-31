# TODO Backup MyRSU

## Obiettivo

Implementare un modulo backup visibile solo ad admin.

## Voce Menu

`Amministrazione -> Backup`

## Backup Database

- Export SQL del database corrente.
- Nome file:
  - `myrsu-db-YYYYMMDD-HHMM.sql`
- Escludere `.env` e segreti.

## Backup Documentale

Creare archivio dei percorsi:
- `storage/private`
- `public/documents`

Nome file:
- `myrsu-files-YYYYMMDD-HHMM.zip`

## Backup Completo

Pacchetto unico con:
- `database.sql`
- `storage/private`
- `public/documents`
- `manifest.json`

Nome file:
- `myrsu-backup-YYYYMMDD-HHMM.zip`

## Sicurezza

- Solo admin.
- Download controllato da API.
- Nessun backup permanente in cartella pubblica.
- Log obbligatorio:
  - utente;
  - data/ora;
  - tipo backup.

## Restore

Da progettare dopo.

Non implementare restore insieme al primo modulo backup.
