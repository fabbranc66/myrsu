# RAPPRESENTANZA SINDACALE — PACCHETTO PER CODEX

## Obiettivo
Completare un corpus separato dal CCNL, destinato a MyRSU / Normativa Rapida, denominato provvisoriamente:

**Rappresentanza sindacale — Testo coordinato degli accordi interconfederali e della disciplina RSU Metalmeccanici**

Aggiornamento di lavoro: **luglio 2026**.

## Stato del lavoro già svolto
Non ripartire da zero.

Sono già stati:
- separati concettualmente CCNL e disciplina della rappresentanza;
- individuata la catena normativa di lavoro 1993 → 2011 → 2013 → TU 2014 → 2017 → disciplina metalmeccanici → verifiche 2025/2026;
- estratto dal CCNL 2021 l'**Allegato 3 — Accordo 5 luglio 2017 per la costituzione delle RSU**;
- predisposto un dossier fonti;
- predisposta una prima mappa coordinata per temi;
- predisposto JSON preliminare per l'importazione strutturata in MyRSU.

## File del pacchetto
1. `01_Allegato_3_RSU_Metalmeccanici_5_luglio_2017.pdf`
   - Fonte settoriale già acquisita.
   - Integra e specifica il TU 10 gennaio 2014.
   - Decorrenza indicata nel testo: 1 settembre 2017.

2. `02_Dossier_Fonti_v1.pdf`
   - Mappa delle fonti da reperire/verificare.

3. `03_Dossier_Fonti_v1.json`
   - Versione strutturata della mappa fonti.

4. `04_Testo_Coordinato_Provvisorio_v1.pdf`
   - Prima ricostruzione comparativa/operativa.
   - NON è ancora un testo unico normativo definitivo.

5. `05_Testo_Coordinato_Provvisorio_v1.json`
   - Struttura dati preliminare per MyRSU.

## Fonti da acquisire in versione integrale e verificare
Usare preferibilmente fonti ufficiali / originali delle parti firmatarie.

- Accordo interconfederale 20 dicembre 1993 sulle RSU.
- Accordo interconfederale 28 giugno 2011.
- Protocollo 31 maggio 2013.
- Testo Unico sulla Rappresentanza 10 gennaio 2014.
- Accordo del 4 luglio 2017 relativo a misurazione/certificazione della rappresentanza.
- Patto per la Fabbrica 9 marzo 2018, solo per le parti effettivamente pertinenti alla rappresentanza.
- Eventuali ulteriori accordi interconfederali successivi che modifichino o integrino il TU 2014.
- CCNL Metalmeccanici 5 febbraio 2021, nelle sole parti relative a diritti sindacali e rinvii al TU/Allegato 3.
- Rinnovo CCNL Metalmeccanici 22 novembre 2025, per verificare modifiche alla disciplina delle relazioni sindacali e l'eventuale impatto sull'Allegato 3.
- Atti 2026: distinguere rigorosamente tra accordi firmati/vigenti e piattaforme, bozze, negoziati o documenti politici non ancora normativi.

## Metodo obbligatorio di consolidamento
Per ogni istituto, costruire la catena cronologica e indicare chiaramente:

`fonte_originaria → modifica → integrazione → sostituzione → stato_vigente`

Non assumere che la fonte più recente cancelli integralmente la precedente.
Una disposizione precedente resta utilizzabile solo se non incompatibile, non sostituita e non superata da una fonte successiva applicabile.

### Materie da coordinare almeno
- costituzione della RSU;
- soggetti legittimati a indire le elezioni;
- durata e decorrenza del mandato;
- scadenza e decadenza;
- composizione e numero componenti;
- collegi elettorali;
- elettorato attivo e passivo;
- presentazione liste;
- raccolta firme;
- Commissione elettorale;
- seggi e scrutinio;
- quorum;
- attribuzione dei seggi;
- preferenze;
- verbali;
- proclamazione degli eletti;
- comunicazioni successive all'azienda e agli organismi interessati;
- ricorsi e organismi di garanzia;
- sostituzioni e dimissioni;
- cambio di appartenenza sindacale;
- decadenza della RSU;
- RSA/RSU e clausole di salvaguardia;
- titolarità della contrattazione aziendale;
- efficacia degli accordi aziendali;
- misurazione e certificazione della rappresentanza;
- permessi e prerogative sindacali, tenendole distinte dalla procedura elettorale;
- collegamento con disciplina RLS senza confondere RSU e RLS.

## Punto particolarmente importante per MyRSU
Separare sempre:

1. **atto elettorale / proclamazione**;
2. **comunicazioni amministrative successive**;
3. **decorrenza del mandato**.

Non dedurre automaticamente che una comunicazione successiva dell'associazione datoriale faccia decorrere il mandato RSU. La questione deve essere ricostruita solo sulle fonti applicabili e sui verbali della specifica elezione.

## Output richiesti a Codex

### 1. Report preliminare
Prima di modificare codice o Knowledge Base, produrre un report con:
- fonti trovate;
- fonti mancanti;
- eventuali contraddizioni;
- norme sicuramente sostituite;
- norme ancora vigenti;
- punti dubbi da non consolidare senza verifica.

### 2. Testo coordinato operativo
Creare un testo unico di consultazione personale, NON qualificato come fonte ufficiale, organizzato per materia e con indicazione della fonte vigente di ogni regola.

### 3. Storico modifiche
Per ogni voce mostrare, ove utile:
- testo/regola 1993;
- modifica 2011/2013;
- TU 2014;
- aggiornamento 2017;
- specifica metalmeccanici;
- successive modifiche.

### 4. Struttura MyRSU / Normativa Rapida
Usare una struttura compatibile con:

`DOCUMENTO -> MACRO -> SEZIONE -> CAPITOLO -> ARGOMENTO -> ARTICOLO/VOCE`

Campi minimi consigliati:
- `id`
- `documento`
- `titolo`
- `tipo_voce`
- `parent_id`
- `testo_vigente`
- `testo_storico`
- `fonte_originaria`
- `fonte_modificativa`
- `data_fonte`
- `decorrenza`
- `stato` = VIGENTE / STORICO / SUPERATO / DA_VERIFICARE
- `settore_applicazione`
- `pagine_fonte`
- `tag`
- `note_coordinamento`

## Regola di affidabilità
Non inventare e non completare per analogia passaggi mancanti.
Quando non è possibile stabilire con certezza il testo vigente, classificare la voce **DA VERIFICARE** e riportare il problema nel report.

## Nota sul file coordinato v1
Il PDF `04_Testo_Coordinato_Provvisorio_v1.pdf` è una **mappa di lavoro**, non deve essere trattato come fonte primaria né come prova definitiva della vigenza di una disposizione. Le fonti originarie firmate hanno priorità.

