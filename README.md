# MyRSU API

Backend API-first custom PHP per gestione utenti, ruoli, permessi, auth token e consensi GDPR.

## Principi

- API REST JSON sotto `/api/v1`
- autenticazione con token `Bearer`
- ruoli e permessi separati
- controller sottili, service applicativi, repository per il database
- database unico `myrsu` per locale e hosting
- nessuna vista HTML nel backend

## Primo avvio

1. Crea il database importando `database/schema.sql`.
2. Apri `http://localhost/myrsu/api/v1/health`.
3. Login iniziale:

```json
{
  "email": "admin@myrsu.local",
  "password": "admin123"
}
```

Endpoint:

- `POST /api/v1/auth/login`
- `POST /api/v1/auth/logout`
- `GET /api/v1/me`
- `GET /api/v1/users`
- `POST /api/v1/users`
- `GET /api/v1/users/{id}`
- `PATCH /api/v1/users/{id}`
- `DELETE /api/v1/users/{id}`
- `GET /api/v1/roles`
- `GET /api/v1/permissions`
- `POST /api/v1/users/{id}/roles`
- `GET /api/v1/gdpr/consents`
- `POST /api/v1/gdpr/consents`
