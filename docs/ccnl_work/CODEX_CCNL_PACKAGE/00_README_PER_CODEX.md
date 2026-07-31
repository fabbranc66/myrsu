# CONSEGNA A CODEX — CCNL METALMECCANICI INDUSTRIA

## Obiettivo
Completare il **testo coordinato e consolidato del CCNL Metalmeccanici Industria Federmeccanica-Assistal**, aggiornato a luglio 2026, partendo dal lavoro già svolto. Non ripartire da zero e non sostituire le fonti con testi editoriali terzi.

Il risultato deve essere un **testo unico operativo**, articolo per articolo, ottenuto fondendo:
1. CCNL completo 5 febbraio 2021;
2. accordo firmato di rinnovo 22 novembre 2025;
3. aggiornamenti successivi effettivamente applicabili nel 2026, in particolare conferma minimi/IPCA giugno 2026 e scioglimento della riserva dopo la consultazione certificata.

Il prodotto è per consultazione personale/MyRSU e va qualificato come **testo coordinato provvisorio, non testo ufficiale delle parti stipulanti**, fino alla pubblicazione del coordinato ufficiale.

---

## File da usare

### Fonti primarie di base
- `01_CCNL_05-02-2021_COMPLETO_288p.pdf`
  - testo integrale base;
  - 288 pagine;
  - NON usare il vecchio `document(21).pdf` come base: quello era solo l'accordo firmato 2021 di 59 pagine.
- `02_RINNOVO_22-11-2025_FIRMATO_51p.pdf`
  - accordo firmato con aggiunte, barrature e numerosi “Omissis”;
  - deve essere letto anche VISIVAMENTE: barrature e impaginazione sono giuridicamente rilevanti.
- `CCNL_2021_testo_estratto.txt`
  - estrazione testuale del 2021 per ricerca veloce;
  - il PDF resta la fonte da verificare quando il testo estratto è ambiguo.

### Lavoro già svolto
- `03_Mappa_integrazione_CCNL_2021_2025.docx`
  - mappa preliminare delle aree modificate.
- `04_Registro_Omissis_CCNL_2025_v1.pdf`
- `04_Registro_Omissis_CCNL_2025_v1.json`
  - registro degli omissis già individuati e prima ricostruzione dei raccordi 2021.
- `05_CCNL_Coordinato_Provvisorio_MyRSU_v1.pdf`
  - contiene mappa + CCNL 2021 integrale + rinnovo 2025 integrale;
  - è una BASE DI LAVORO, non il testo consolidato finale.
- `05_CCNL_Coordinato_Provvisorio_MyRSU_v1_mapping.json`
  - prima struttura dati per l'importazione in MyRSU.
- `06_FONTI_2026_E_VERIFICHE.md`
  - aggiornamenti e fonti web 2026 da verificare/recepire.
- `07_STATO_LAVORO_E_REGOLE_DI_CONSOLIDAMENTO.md`
  - regole vincolanti per terminare il lavoro.

---

## Regola fondamentale sugli OMISSIS
Nel rinnovo 22/11/2025 **“Omissis” non significa cancellazione**.

Procedura obbligatoria per ogni omissis:
1. individua il testo immediatamente precedente e successivo nell'accordo 2025;
2. trova il blocco corrispondente nel CCNL 2021;
3. verifica numerazione, titolo, lettera, comma e continuità;
4. mantieni dal 2021 solo il testo non modificato;
5. elimina il testo che nel 2025 è espressamente barrato/soppresso;
6. inserisci le aggiunte e sostituzioni 2025 nella posizione corretta;
7. se il raccordo è ambiguo, NON inventare: marca `DA_VERIFICARE` e inseriscilo nel report anomalie.

---

## Cosa deve fare Codex

### Fase 1 — analisi completa
Prima di modificare/generare il consolidato, produrre un report con:
- elenco completo di tutte le modifiche 2025;
- elenco completo di tutti gli omissis;
- per ciascun omissis: pagina 2025, articolo, posizione 2021, esito `TROVATO / AMBIGUO / NON TROVATO`;
- tutte le barrature 2025;
- tutte le rinumerazioni/spostamenti di lettere/articoli;
- tutte le decorrenze differite (2026/2027/2028);
- eventuali conflitti fra mappa esistente e documenti originali.

### Fase 2 — consolidamento
Creare un nuovo testo strutturato mantenendo l'ordine del CCNL 2021:
- testo invariato 2021;
- sostituzioni 2025 già fuse;
- aggiunte 2025 già fuse;
- disposizioni barrate eliminate dal testo vigente;
- rinumerazioni corrette;
- note di decorrenza solo dove necessarie;
- tabelle economiche aggiornate.

Non deve essere necessario consultare separatamente il rinnovo 2025 per capire la disposizione vigente.

### Fase 3 — controllo 2026
Integrare solo aggiornamenti 2026 documentati e realmente applicabili.
In particolare:
- scioglimento della riserva dopo la consultazione certificata febbraio 2026;
- minimi tabellari dal 1 giugno 2026;
- trasferta e reperibilità aggiornate a giugno 2026, se risultanti dal documento unitario;
- non confondere con CCNL Unionmeccanica/Confapi, Confimi, cooperative, artigiani, CCSL o altri contratti.

### Fase 4 — output
Produrre:
1. `CCNL_Metalmeccanici_Industria_Coordinato_Luglio_2026.pdf`
2. versione strutturata `json` o `jsonl` per Knowledge Base MyRSU;
3. `Report_modifiche_e_anomalie.md/pdf`;
4. registro fonti e provenienza di ogni modifica.

---

## Modello dati minimo per MyRSU
Ogni nodo/articolo deve poter contenere almeno:
- `documento`
- `sezione`
- `titolo`
- `articolo`
- `sottovoce/lettera/comma`
- `testo_vigente`
- `fonte_base`
- `fonte_modifica`
- `data_decorrenza`
- `stato`: `VIGENTE | STORICO | SUPERATO | DA_VERIFICARE`
- `pagina_fonte_2021`
- `pagina_fonte_2025`
- `tags`
- `note_coordinamento`

Conservare anche lo storico del testo sostituito, ma NON mostrarlo come vigente.

---

## Vincoli di affidabilità
- Non ricostruire per intuito.
- Non usare un testo editoriale online come fonte sostitutiva degli originali.
- Eventuali testi consolidati editoriali possono essere usati solo come controllo incrociato.
- Per barrature, tabelle o grafica ambigua, verificare direttamente il PDF firmato 2025.
- Non modificare il significato giuridico per “rendere più leggibile”.
- Non correggere tacitamente refusi dell'originale: segnalarli nelle note.
- Distinguere sempre il testo vigente dalle note redazionali MyRSU.

---

## Punti già verificati da non rifare da zero
Sono già stati individuati/raccordati, almeno in prima analisi:
- tipologie contrattuali / tempo determinato;
- vecchia stabilizzazione e nota a verbale 5/2/2021;
- somministrazione e nuova stabilizzazione dal 2026;
- passaggio temporaneo a mansioni superiori;
- orario plurisettimanale;
- PAR/turnisti;
- lavoro straordinario;
- ferie;
- salute e sicurezza / quasi infortuni / analisi post-incidente;
- malattia e tutele per disabilità;
- formazione continua / MetApprendo;
- apprendistato.

Questi raccordi vanno VERIFICATI, non ignorati o ricostruiti da capo senza confronto con i registri già forniti.
