# Istruzioni Codex per MyRSU

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
Invoke-RestMethod -Method Get -Uri "http://localhost/myrsu/api/v1/health"
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
