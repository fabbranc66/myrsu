# Procedura GDPR

## Principio

I documenti marcati come GDPR protected non devono essere conservati in chiaro.

La cifratura riguarda:
- originale caricato;
- PDF RSU generato dal sistema.

Il documento viene decifrato solo al momento dell'utilizzo da parte di un utente autorizzato.

## Upload

1. Il membro carica il documento.
2. Il membro attiva `GDPR protected`.
3. Il membro sceglie gli autorizzati:
   - solo membro;
   - delegato scelto;
   - tutta RSU.
4. Il membro conferma liberatoria GDPR.
5. Il sistema salva:
   - originale cifrato in cartella GDPR originali;
   - PDF RSU cifrato in cartella GDPR documenti.

## Accesso

Prima della decifratura il sistema verifica:
- autenticazione;
- autorizzazione scelta dal membro;
- liberatoria GDPR attiva;
- permessi applicativi.

Ogni accesso viene loggato.

## Revoca Liberatoria

Se la liberatoria GDPR viene revocata:
- i file restano cifrati;
- la decifratura viene bloccata;
- il blocco viene loggato.

## Fine Pratica / Archiviazione

Ad archiviazione pratica:
- originale cifrato eliminato;
- PDF RSU cifrato eliminato;
- metadati aggiornati o marcati come eliminati;
- operazione loggata.

## Regola Finale

Il file fisico sottratto da hosting, FTP o filesystem non deve essere leggibile senza passare da MyRSU.
