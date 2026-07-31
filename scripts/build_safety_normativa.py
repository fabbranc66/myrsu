from pathlib import Path
import re
from pypdf import PdfReader


BASE = Path(__file__).resolve().parents[1]
SOURCE = BASE / "docs/safety_work/clean/01_D_LGS_81_2008_TESTO_VIGENTE_RICERCABILE.md"
OUTPUT = BASE / "docs/safety_work/clean/01_D_LGS_81_2008_TESTO_VIGENTE_PULITO.md"
FOCUS = BASE / "docs/safety_work/clean/03_BLOCCO_OPERATIVO_RSU_RLS_SICUREZZA_81_08.md"
MAP = BASE / "docs/safety_work/clean/04_MAPPA_ARTICOLI_TEMI_SICUREZZA_81_08.md"
ACCORDO_2025_PDF = BASE / "docs/safety_work/source/accordo_stato_regioni_59_csr_17_04_2025.pdf"
ACCORDO_2025 = BASE / "docs/safety_work/clean/13_ACCORDO_STATO_REGIONI_FORMAZIONE_59_CSR_2025.md"
LINKED_ACTS = BASE / "docs/safety_work/clean/14_CONTENUTI_COLLEGATI_81_08_ACCORDI_INTERPELLI_CIRCOLARI.md"


def clean_line(line: str) -> str:
    line = line.replace("\u00a0", " ")
    line = re.sub(r"\s+", " ", line).strip()
    line = re.sub(r"\s+([’'])", r"\1", line)
    line = re.sub(r"([LlDdNnAa])\s+’", r"\1’", line)
    line = re.sub(r"\b([Dd]ecreto|[Dd]\.?[Ll]\.?|[Dd]ecreto-[Ll]egge)\s+-\s+([Ll]egge)", r"\1-\2", line)
    line = re.sub(r"\s+([,.;:])", r"\1", line)
    line = re.sub(r"\(\s*N\s*\)", "", line)
    return line


def is_noise(line: str) -> bool:
    if not line:
        return False
    if line.startswith("<!-- pagina"):
        return True
    if re.search(r"Pagina\s+[0-9IVXLCDM]+\s+di\s+[0-9IVXLCDM]+", line, re.I):
        return True
    if line == "D.lgs. 09 aprile 2008 n. 81":
        return True
    if "D.lgs. 09 aprile 2008 n. 81" in line and "Pagina" in line:
        return True
    if re.match(r"^(TITOLO|CAPO)\b.*D\.lgs\. 09 aprile 2008 n\. 81$", line):
        return True
    return False


def extract_article(text: str, number: str) -> str:
    pattern = re.compile(
        rf"(^Articolo\s+{re.escape(number)}(?:\s|-)[\s\S]*?)(?=^Articolo\s+\d|\nTITOLO\s+[IVXLCDM]+|\nALLEGATO\s+|\Z)",
        re.M,
    )
    match = pattern.search(text)
    return match.group(1).strip() if match else f"## Articolo {number}\n\nNon trovato."


THEMATIC_BLOCKS = [
    (
        "05_BLOCCO_RLS_CONSULTAZIONE_ACCESSO_DOCUMENTI.md",
        "RLS, consultazione e accesso documenti",
        ["47", "48", "49", "50", "51", "52"],
        ["rls", "rappresentante lavoratori sicurezza", "consultazione", "accesso dvr", "riunione periodica"],
    ),
    (
        "06_BLOCCO_DVR_VALUTAZIONE_RISCHI.md",
        "DVR e valutazione dei rischi",
        ["17", "28", "29", "30"],
        ["dvr", "valutazione rischi", "documento valutazione rischi", "rischi aziendali"],
    ),
    (
        "07_BLOCCO_FORMAZIONE_INFORMAZIONE_ADDESTRAMENTO.md",
        "Formazione, informazione e addestramento",
        ["36", "37"],
        ["formazione", "informazione", "addestramento", "accordo stato regioni", "aggiornamento"],
    ),
    (
        "08_BLOCCO_PREPOSTO_OBBLIGHI_RESPONSABILITA.md",
        "Preposto, obblighi e responsabilità",
        ["18", "19", "20", "55", "56", "59", "299"],
        ["preposto", "datore lavoro", "dirigente", "lavoratore", "responsabilità", "sanzioni"],
    ),
    (
        "09_BLOCCO_APPALTI_DUVRI_INTERFERENZE.md",
        "Appalti, DUVRI e interferenze",
        ["26"],
        ["appalto", "duvri", "interferenze", "contratto appalto", "idoneità tecnico professionale"],
    ),
    (
        "10_BLOCCO_SORVEGLIANZA_SANITARIA_MEDICO_COMPETENTE.md",
        "Sorveglianza sanitaria e medico competente",
        ["25", "38", "39", "40", "41", "42"],
        ["sorveglianza sanitaria", "medico competente", "visita medica", "idoneità", "malattia professionale"],
    ),
    (
        "11_BLOCCO_VIGILANZA_SOSPENSIONE_ORGANI_CONTROLLO.md",
        "Vigilanza, sospensione e organi di controllo",
        ["13", "14", "14-bis"],
        ["vigilanza", "sospensione attività", "asl", "ispettorato", "violazioni gravi"],
    ),
    (
        "12_BLOCCO_EMERGENZE_ANTINCENDIO_PRIMO_SOCCORSO.md",
        "Emergenze, antincendio e primo soccorso",
        ["43", "44", "45", "46"],
        ["emergenza", "pericolo grave", "primo soccorso", "antincendio", "evacuazione"],
    ),
]


def build_thematic_blocks(text: str) -> None:
    map_rows = [
        "# Sicurezza 81/08 - Mappa articoli e temi",
        "",
        "| Tema | Articoli | Parole chiave | File |",
        "| --- | --- | --- | --- |",
    ]
    for filename, title, articles, keywords in THEMATIC_BLOCKS:
        chunks = [f"# {title}", "", "Estratto tematico dal D.Lgs. 81/2008 per Normativa Rapida.", ""]
        for number in articles:
            chunks.append(extract_article(text, number))
            chunks.append("")
        (BASE / "docs/safety_work/clean" / filename).write_text("\n".join(chunks).strip() + "\n", encoding="utf-8")
        map_rows.append(f"| {title} | {', '.join(articles)} | {', '.join(keywords)} | `{filename}` |")
    MAP.write_text("\n".join(map_rows) + "\n", encoding="utf-8")


def clean_pdf_text(text: str) -> str:
    rows = []
    for line in text.replace("\r", "\n").splitlines():
      line = clean_line(line)
      if line:
          rows.append(line)
    return "\n".join(rows)


def build_accordo_2025() -> None:
    if not ACCORDO_2025_PDF.exists():
        return
    reader = PdfReader(str(ACCORDO_2025_PDF))
    chunks = []
    for index, page in enumerate(reader.pages, 1):
        text = clean_pdf_text(page.extract_text() or "")
        if text:
            chunks.append(f"\n\n## Pagina {index}\n\n{text}")
    content = (
        "# Accordo Stato-Regioni formazione 59/CSR del 17 aprile 2025\n\n"
        "**Fonte:** Conferenza Stato-Regioni, Rep. atti n. 59/CSR del 17 aprile 2025.\n\n"
        "**Ambito:** formazione salute e sicurezza ex articolo 37 D.Lgs. 81/2008.\n"
        + "\n".join(chunks).strip()
        + "\n"
    )
    ACCORDO_2025.write_text(content, encoding="utf-8")


def extract_linked_items(text: str, label: str) -> list[str]:
    pattern = re.compile(rf"{label}\s+•\s+([\s\S]*?)(?=\n(?:Note all|Richiami all|Articolo\s+\d+|TITOLO\s+|CAPO\s+|Sanzioni|DECRETI ATTUATIVI|CIRCOLARI|LETTERE CIRCOLARI|INTERPELLI|ALTRI PROVVEDIMENTI)\b|\Z)", re.I)
    items = []
    for match in pattern.finditer(text):
        chunk = re.sub(r"\s+", " ", match.group(1)).strip()
        if chunk:
            parts = [item.strip(" •") for item in re.split(r"\s+•\s+", chunk) if item.strip(" •")]
            items.extend(parts)
    return items


def build_linked_acts(text: str) -> None:
    labels = ["DECRETI ATTUATIVI", "CIRCOLARI", "LETTERE CIRCOLARI", "INTERPELLI", "ALTRI PROVVEDIMENTI"]
    rows = [
        "# Contenuti collegati al D.Lgs. 81/2008\n",
        "Catalogo operativo estratto dal testo INL 81/08 gennaio 2026.",
        "",
    ]
    for label in labels:
        items = sorted(set(extract_linked_items(text, label)))
        rows.append(f"## {label.title()}")
        rows.append("")
        if not items:
            rows.append("- Nessun elemento estratto.")
        else:
            rows.extend(f"- {item}" for item in items)
        rows.append("")
    LINKED_ACTS.write_text("\n".join(rows).strip() + "\n", encoding="utf-8")


def build_focus(text: str) -> None:
    groups = [
        ("Vigilanza e sospensione", ["13", "14", "14-bis"]),
        ("Obblighi aziendali e preposto", ["15", "16", "17", "18", "19", "20"]),
        ("Appalti e DUVRI", ["26"]),
        ("DVR e valutazione rischi", ["28", "29", "30"]),
        ("Riunione, informazione, formazione", ["35", "36", "37"]),
        ("RLS", ["47", "48", "49", "50"]),
        ("Sanzioni principali", ["55", "56", "59"]),
        ("Responsabilità di fatto", ["299"]),
    ]
    chunks = [
        "# Sicurezza 81/08 - Blocco operativo RSU/RLS\n",
        "Estratti rapidi dal D.Lgs. 81/2008 per pratiche MyRSU.\n",
    ]
    for title, numbers in groups:
        chunks.append(f"\n## {title}\n")
        for number in numbers:
            article = extract_article(text, number)
            chunks.append(article)
            chunks.append("")
    FOCUS.write_text("\n".join(chunks).strip() + "\n", encoding="utf-8")


def starts_new_block(line: str) -> bool:
    return bool(re.match(r"^(TITOLO|CAPO|SEZIONE|Articolo|ALLEGATO)\b", line, re.I))


def build() -> None:
    raw = SOURCE.read_text(encoding="utf-8", errors="ignore")
    start = raw.find("Emana il seguente decreto legislativo:")
    if start == -1:
        start = raw.find("TITOLO I - PRINCIPI COMUNI", 4000)
    body = raw[start:]

    lines = [clean_line(line) for line in body.splitlines()]
    lines = [line for line in lines if not is_noise(line)]

    merged: list[str] = []
    index = 0
    while index < len(lines):
        line = lines[index]
        if not line:
            if merged and merged[-1]:
                merged.append("")
            index += 1
            continue

        while line.endswith("-") and index + 1 < len(lines):
            next_line = lines[index + 1]
            if next_line and next_line[0].islower():
                line = line[:-1] + next_line
                index += 1
            else:
                break

        if merged and merged[-1] and not starts_new_block(line):
            previous = merged[-1]
            if (
                not starts_new_block(previous)
                and not previous.endswith((".", ";", ":", "!", "?", "»"))
                and not re.match(r"^[a-z]\)|^[0-9]+\.", line)
            ):
                merged[-1] = previous + " " + line
                index += 1
                continue

        merged.append(line)
        index += 1

    text = "\n".join(merged)
    text = re.sub(r"\n{3,}", "\n\n", text)
    text = re.sub(r"([^\n])\n(Articolo\s+\d+[^\n]*)", r"\1\n\n\2", text)
    text = re.sub(r"([^\n])\n(TITOLO\s+[IVXLCDM]+[^\n]*)", r"\1\n\n\2", text)
    text = re.sub(r"([^\n])\n(CAPO\s+[IVXLCDM]+[^\n]*)", r"\1\n\n\2", text)

    header = (
        "# D.Lgs. 81/2008 - Testo vigente pulito\n\n"
        "**Fonte:** Ispettorato Nazionale del Lavoro, edizione gennaio 2026.\n\n"
        "**Uso MyRSU:** testo ricercabile per Normativa Rapida.\n\n"
    )
    OUTPUT.write_text(header + text.strip() + "\n", encoding="utf-8")
    build_focus(header + text.strip() + "\n")
    build_thematic_blocks(header + text.strip() + "\n")
    build_accordo_2025()
    build_linked_acts(header + text.strip() + "\n")


if __name__ == "__main__":
    build()
