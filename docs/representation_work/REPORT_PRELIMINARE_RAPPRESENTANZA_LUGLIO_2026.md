# Report preliminare - Rappresentanza sindacale

Aggiornamento: luglio 2026

## Premessa

Questo report precede la costruzione del testo coordinato operativo MyRSU.

Il file `04_Testo_Coordinato_Provvisorio_v1.pdf` e il relativo JSON sono trattati come mappa di lavoro, non come fonte primaria.

## File pacchetto analizzati

- `00_README_PER_CODEX.md`: istruzioni operative e metodo obbligatorio.
- `01_Allegato_3_RSU_Metalmeccanici_5_luglio_2017.pdf`: fonte settoriale acquisita.
- `02_Dossier_Fonti_v1.pdf`: dossier fonti, mappa di lavoro.
- `03_Dossier_Fonti_v1.json`: elenco fonti e stati preliminari.
- `04_Testo_Coordinato_Provvisorio_v1.pdf`: testo coordinato provvisorio, non fonte primaria.
- `05_Testo_Coordinato_Provvisorio_v1.json`: struttura preliminare per MyRSU.

## Fonti reperite o da usare come base

| Fonte | Stato | Uso MyRSU |
|---|---|---|
| Accordo interconfederale 20 dicembre 1993 | Da verificare su copia primaria/firmata | Base storica RSU |
| Accordo interconfederale 28 giugno 2011 | Reperita copia sindacale | Rappresentanza e contrattazione |
| Protocollo 31 maggio 2013 | Reperita copia sindacale | Misurazione rappresentanza |
| Testo Unico rappresentanza 10 gennaio 2014 | Reperite copie pubbliche sindacali/datoriali | Fonte centrale |
| Accordo 4 luglio 2017 | Reperita copia sindacale | Misurazione/certificazione |
| Accordo metalmeccanici 5 luglio 2017 | Acquisito nel pacchetto | Specifica settoriale metalmeccanici |
| Patto per la Fabbrica 9 marzo 2018 | Reperita copia pubblica | Solo parti pertinenti |
| CCNL Metalmeccanici 2021 | Già presente nel corpus CCNL MyRSU | Rinvii e Allegato 3 |
| Rinnovo CCNL 22 novembre 2025 | Reperita documentazione di rinnovo | Verifica impatti, non riscrittura automatica Allegato 3 |

## Fonti ancora mancanti o da rafforzare

- Copia primaria firmata dell'Accordo interconfederale 20 dicembre 1993.
- Eventuali accordi interconfederali successivi al 2018 che modifichino espressamente TU 2014 o disciplina RSU.
- Testi 2026: al momento vanno distinti da piattaforme, ipotesi o documenti politici non normativi.
- Verifica puntuale se il rinnovo metalmeccanici 2025 incide direttamente su Allegato 3 o solo su materie collegate.

## Norme sicuramente superate o sostituite

- Sistema 1993 `2/3 eletti + 1/3 riservato`: superato dal TU 2014, che prevede elezione a suffragio universale e scrutinio segreto.
- Regole 1993 incompatibili con misurazione/certificazione 2011/2013/2014: da mantenere solo come storico.
- Ogni regola settoriale precedente incompatibile con Accordo metalmeccanici 5 luglio 2017: da classificare superata o integrata.

## Norme o principi ancora vigenti da coordinare

- Soglia unità produttiva superiore a 15 dipendenti per costituzione RSU.
- Durata triennale della RSU e decadenza automatica alla scadenza.
- Validità elezioni con partecipazione superiore alla metà degli aventi diritto.
- Presentazione liste e ruolo della Commissione elettorale.
- Comunicazione dei risultati e gestione ricorsi.
- Titolarità della contrattazione aziendale secondo TU 2014 e disciplina settoriale applicabile.
- Specifiche metalmeccaniche 2017 su collegi, moduli, garanzie e disciplina operativa.

## Contraddizioni o punti da non consolidare senza verifica

- Decorrenza mandato RSU: non va dedotta automaticamente dalla comunicazione successiva all'azienda o all'associazione datoriale.
- Rapporto tra verbale elettorale, proclamazione e comunicazioni successive: da separare in MyRSU.
- Rinnovo CCNL 2025: non trattarlo come riscrittura dell'Allegato 3 senza testo espresso.
- RLS: collegare, ma non confondere con RSU; permessi e prerogative RLS restano corpus distinto.
- Patto per la Fabbrica 2018: includere solo disposizioni effettivamente incidenti sulla rappresentanza.

## Indicazione per il testo coordinato

Il testo operativo dovrà essere organizzato per materia:

- costituzione RSU;
- soggetti legittimati;
- durata, scadenza, decadenza;
- composizione e numero componenti;
- collegi elettorali;
- elettorato attivo/passivo;
- liste e firme;
- Commissione elettorale;
- seggi, scrutinio, quorum;
- attribuzione seggi e preferenze;
- verbali e proclamazione;
- comunicazioni successive;
- ricorsi e garanzie;
- sostituzioni, dimissioni, cambio sigla;
- contrattazione aziendale;
- efficacia accordi;
- misurazione/certificazione rappresentanza;
- permessi/prerogative sindacali;
- raccordo RLS separato.

## Struttura MyRSU proposta

`DOCUMENTO -> MACRO -> SEZIONE -> CAPITOLO -> ARGOMENTO -> VOCE`

Campi minimi:

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
- `stato`
- `settore_applicazione`
- `pagine_fonte`
- `tag`
- `note_coordinamento`

## Esito preliminare

Si può procedere alla costruzione del corpus MyRSU, ma marcando come `DA_VERIFICARE` tutte le voci che dipendono da fonti non ancora acquisite in copia primaria o da interpretazioni non testuali.
