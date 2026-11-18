# MioDelegato: documenti locali

Aggiornamento del 14 novembre 2026, autorizzato dall'utente soltanto in locale. Nessun deploy, commit, push o modifica dello schema DB.

## Identita documentale

- Intestazione con logo trasparente MioDelegato; filigrana con il solo simbolo e la dicitura `Del. FIM-CISL`.
- Due simboli inclinati di 28 gradi, larghi 170 punti; trasparenza 11% per il simbolo e 19% per la dicitura.
- La copia digitale e la pagina di verifica indicano il delegato RSU FIM-CISL, senza attribuire la comunicazione all'intera RSU. La verifica applicativa non viene presentata come firma digitale qualificata.
- I nomi tecnici, i percorsi `/myrsu`, i codici e le regole di accesso restano invariati.

## PDF esistenti

Aggiornati 40 PDF nelle due configurazioni locali: 36 nella configurazione operativa con tabelle senza prefisso e 4 in `cfg_2_`. La configurazione operativa non e stata cambiata.

Conservati originali, contenuti, ID, nomi e percorsi, protocolli, collegamenti, codici di verifica, date di firma, autori e permessi. Nelle tabelle documentali cambiano soltanto `size_bytes`, `checksum_sha256`, `pdf_size_bytes` e `pdf_checksum_sha256`.

39 copie mantengono numero di pagine e annotazioni; il contenuto non relativo al vecchio marchio viene controllato anche come sequenza di istruzioni PDF. Il documento 16 era ancora una copia diretta dell'originale, senza intestazione: ricomposto dall'originale, con una pagina di verifica aggiuntiva. Totale finale: 189 pagine. La normalizzazione necessaria per il parser FPDI riguarda solo una copia transitoria, non l'originale.

Aggiornati i checksum di 9 metadati di verifica. Sei metadati erano gia riferiti a nomi, percorsi e codici diversi dai rispettivi documenti: riallineati alle righe DB corrette, senza cambiare le firme registrate nei documenti.

Nessuna versione precedente dei PDF generati viene conservata. Le copie scaricate prima dell'aggiornamento non corrispondono piu al checksum corrente.

## Overlay immagini

La filigrana deve essere disegnata dopo le immagini, mai sotto. `ReportPdfService` usa lo stesso ordinamento del writer comune: prima le immagini, poi filigrana e testi. FPDI applica la filigrana dopo la pagina importata.

La verifica automatica riguarda le 189 pagine esistenti e 10 pagine sintetiche, comprese immagini interamente opache. Le prove grafiche non contengono dati degli utenti.

## Verifiche eseguite

- Lint PHP completo e controlli mirati sui file successivamente modificati.
- `scripts/test_pdf_branding.php`: trasparenza, simbolo e cinque generatori PDF.
- `scripts/test_pdf_branding_overlay.py`: ordine effettivo delle immagini e delle filigrane.
- `scripts/test_local_pdf_branding_verification.php`: 48 richieste HTTP locali; checksum, codice corretto/errato e confronto di copie caricate. Token di prova rimosso.
- `scripts/test_document_signatures.php`: 25 controlli sull'algoritmo preesistente, senza rotazione della chiave.
- `scripts/test_branding_layout.mjs`: 30 layout, inclusa la verifica da 320 pixel; API intercettate e nessun account modificato.
- `scripts/test_member_menu.mjs`: menu e ruoli invariati.
- Controllo immediatamente successivo alla sostituzione: originali invariati e altre 109 tabelle invariate. Durante il lavoro la sessione dell'utente ha prodotto normali letture di anteprime e aggiornamenti di `last_used_at`; non sono stati annullati.

Inventari, manifest e rapporti tecnici sono sotto `storage/private/branding`. Non caricarli sull'hosting e non copiare `.env` tra ambienti. Gli script di sostituzione sono strumenti CLI locali e non sono endpoint dell'applicazione.

Riaprire la finestra PDF per vedere la copia aggiornata: una finestra gia aperta puo continuare a mostrare la precedente. L'hosting non e stato aggiornato.

## Controllo specifico Amina

Su richiesta successiva dell'utente e stato aperto e controllato proprio `amina-61f8070310d8.pdf`, documento 42 della configurazione 1. L'ordine dei livelli era sopra l'immagine, ma il simbolo scuro all'11% quasi scompariva sulla foto colorata: il controllo del solo ordine non garantiva una resa visivamente adeguata.

Aggiunta una sagoma chiara al 32% sotto i soli tratti del simbolo e della dicitura, seguita dai colori gia approvati. Il fondo rimane trasparente, senza riquadro bianco; la resa su carta bianca rimane sostanzialmente invariata. La regola e comune ai nuovi PDF, comprese immagini e pagine FPDI.

Rigenerata soltanto la copia Amina, dall'immagine originale: due pagine, stesso percorso, codice, data, permessi e collegamenti. Aggiornati soltanto dimensioni e checksum. Verificate le altre 110 tabelle prima/dopo e ripetute le 48 verifiche HTTP. Gli altri 39 PDF non sono stati rigenerati per questa successiva correzione di contrasto. Manifest e inventario del singolo intervento sono in `storage/private/branding/staged/amina-contrast`.

## Estensione del contrasto a tutti i PDF

Su successiva richiesta esplicita dell'utente, la medesima correzione e stata applicata a tutti i 40 PDF locali delle due configurazioni, per un totale di 189 pagine. Questo intervento supera la precedente limitazione alla sola Amina.

Sostituite le due filigrane di ogni pagina, senza aggiungerne altre e conservando byte per byte le istruzioni PDF non relative alla filigrana. Numero e formato delle pagine, intestazioni, contenuti, annotazioni e collegamenti invariati. Aggiornati dimensioni e checksum nelle due tabelle documentali e nei 9 metadati di verifica; originali, codici, protocolli e permessi preservati. Le altre 109 tabelle risultano invariate al controllo immediato prima/dopo.

`scripts/stage_local_watermark_contrast.py` prepara e controlla l'intero intervento. Il test `scripts/test_pdf_branding_overlay.py` ora richiede su ogni pagina sia l'overlay sopra le immagini sia le due sagome chiare trasparenti al 32%: il solo ordine dei livelli non e piu sufficiente per superarlo. Verificati tutte le 189 pagine e 10 pagine sintetiche, rendering completo, esempi su immagini scure e testi, oltre alle 48 verifiche HTTP di autenticita.

Rapporto e manifest in `storage/private/branding/staged/contrast-all-20261114`. Nessuna vecchia versione PDF conservata; nessun aggiornamento dell'hosting.
