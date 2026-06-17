# MyRSU Development Rules

## Generazione Codice

1. Risposte brevi: descrizione max 3 righe, poi codice.
2. Una cosa alla volta: niente funzionalita extra.
3. Niente assunzioni: non inventare campi o tabelle.
4. API-first: backend JSON, niente HTML nel backend.
5. Backend controlla tutto: validazioni e permessi server-side.
6. Sicurezza base: input validation, bcrypt, auth, ruoli.
7. Naming inglese: users, practices, adhesions, documents.
8. No overengineering: soluzione semplice e funzionante.
9. Commenti minimi: solo dove servono.
10. Errori JSON coerenti: 400, 401, 403, 500.
11. Testing a ogni step: lint PHP e endpoint coinvolti.

## Struttura Codice

1. No file monolitici.
2. Single responsibility obbligatoria.
3. Controller: request/response.
4. Service: logica applicativa.
5. Repository/Model: accesso database.
6. Middleware: sicurezza e permessi.
7. No codice duplicato.
8. Nomi chiari e descrittivi.
9. Max 300 righe per file come soft limit.
10. Import puliti e niente dipendenze circolari.

## JavaScript

1. Separare per funzione o modulo.
2. Non creare un unico file grande.
3. Usare helper comuni per API quando opportuno.

## CSS

1. Separare per modulo.
2. Usare layout condiviso solo per elementi comuni.

## Principio Chiave

Ogni file deve avere una responsabilita chiara, leggibile e isolata.
