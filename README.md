# MyRSU API

Backend API-first custom PHP per gestione RSU.

## Stato

- Auth token Bearer
- Users CRUD
- Roles e permissions
- Profile management
- GDPR consents
- Activity logs
- Protocol register IN/OUT
- UI test collegate alle API

## Principi

- API REST JSON sotto `/api/v1`
- backend senza viste HTML
- UI solo per test operativo
- database unico `myrsu`
- controller, service e repository separati
- validazioni e permessi lato server
- no file monolitici

## Primo avvio

1. Importa `database/schema.sql`.
2. Apri `http://localhost/myrsu/api/v1/health`.
3. Login admin:

```json
{
  "email": "admin@myrsu.local",
  "password": "admin123"
}
```

## UI Test

- Users: `http://localhost/myrsu/ui/users.html`
- User edit: `http://localhost/myrsu/ui/user-edit.html?id=1`
- Profile: `http://localhost/myrsu/ui/profile-test.html`
- Protocol: `http://localhost/myrsu/ui/protocol-test.html`

Ogni UI test mostra anche la risposta JSON dell'ultima chiamata API.

## Endpoint

### Auth

- `POST /api/v1/auth/login`
- `POST /api/v1/auth/logout`
- `GET /api/v1/me`

### Profile

- `GET /api/v1/profile`
- `PATCH /api/v1/profile`
- `PATCH /api/v1/profile/password`

### Users

- `GET /api/v1/users`
- `POST /api/v1/users`
- `GET /api/v1/users/{id}`
- `PATCH /api/v1/users/{id}`
- `DELETE /api/v1/users/{id}`
- `POST /api/v1/users/{id}/roles`

### Roles

- `GET /api/v1/roles`
- `GET /api/v1/permissions`

### GDPR

- `GET /api/v1/gdpr/consents`
- `POST /api/v1/gdpr/consents`
- `GET /api/v1/users/{id}/gdpr/consents`

### Activity

- `GET /api/v1/users/{id}/activity`

### Protocol

- `GET /api/v1/protocol`
- `POST /api/v1/protocol`
- `GET /api/v1/protocol/{id}`
- `PATCH /api/v1/protocol/{id}`
- `DELETE /api/v1/protocol/{id}`

Protocol number:

```text
RSU-IN-DOC-2026-0001
RSU-OUT-DOC-2026-0001
```

## Roles

- `admin`
- `delegato`
- `rls`
- `membro`

Protocol register access:

- `admin`
- `delegato`

## Development Rules

Regole complete: `DEVELOPMENT_RULES.md`

- Una cosa alla volta
- API-first JSON
- Backend controlla validazioni e permessi
- No file monolitici
- Single responsibility per file
- Controller: request/response
- Service: logica applicativa
- Repository: database
- JS separato per modulo/funzione
- CSS separato per modulo
- Max 300 righe per file come soft limit
- Test minimo a ogni step

## Testing

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object {
  php -l $_.FullName
}
```

```powershell
Invoke-RestMethod `
  -Method Get `
  -Uri "http://localhost/myrsu/api/v1/health"
```
