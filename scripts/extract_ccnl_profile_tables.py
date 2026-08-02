from __future__ import annotations

import re
import sys
from pathlib import Path

import pdfplumber


LEVELS_LEFT = ["D1", "D2", "C1", "C2"]
LEVELS_RIGHT = ["C3", "B1", "B2", "B3", "A1"]
COLUMN_BOUNDS = [56, 125, 195, 264, 333, 410]
CRITERIA = [
    "Autonomia, responsabilità gerarchico-funzionale",
    "Competenza tecnico-specifica",
    "Competenze trasversali",
    "Polivalenza",
    "Polifunzionalità",
    "Miglioramento continuo e innovazione",
]
SECOND_CRITERION_WORDS = ["competenza", "polivalenza", "miglioramento"]
GROUP_START_PAGES = [108, 114, 120, 126, 132, 138, 144]


def clean_text(value: str) -> str:
    replacements = {
        "opiù": "o più",
        "Tecnicodi": "Tecnico di",
        "Assisten za": "Assistenza",
        "Speciali sta": "Specialista",
        "informati vo": "informativo",
        "di Specialista": "Specialista",
    }
    for source, target in replacements.items():
        value = value.replace(source, target)
    value = value.replace("CCNL 05-02-2021", "")
    value = re.sub(r"\s+([,.;:])", r"\1", value)
    return re.sub(r"\s+", " ", value).strip()


def box_text(page, x0: float, top: float, x1: float, bottom: float) -> str:
    words = [
        word for word in page.extract_words(use_text_flow=False)
        if x0 <= (word["x0"] + word["x1"]) / 2 < x1 and top <= word["top"] < bottom
    ]
    lines: dict[float, list[dict]] = {}
    for word in words:
        lines.setdefault(round(word["top"], 1), []).append(word)
    parts: list[str] = []
    for position in sorted(lines):
        line = " ".join(word["text"] for word in sorted(lines[position], key=lambda item: item["x0"])).strip()
        if not line:
            continue
        if parts and parts[-1].endswith("-"):
            parts[-1] = parts[-1][:-1] + line
        else:
            parts.append(line)
    return clean_text(" ".join(parts))


def area_title(page) -> str:
    text = page.extract_text() or ""
    for line in text.splitlines():
        if "ESEMPLIFICAZIONE PROFILI" in line:
            return clean_text(line)
    return "ESEMPLIFICAZIONE PROFILI PROFESSIONALI"


def profiles(left_page, right_page) -> list[tuple[str, str]]:
    result: list[tuple[str, str]] = []
    for page, levels, start_column in (
        (left_page, LEVELS_LEFT, 1),
        (right_page, LEVELS_RIGHT, 0),
    ):
        for index, level in enumerate(levels):
            column = index + start_column
            profile = box_text(page, COLUMN_BOUNDS[column], 100, COLUMN_BOUNDS[column + 1], 148)
            profile = re.sub(rf"\s+{level}$", "", profile).strip()
            result.append((level, profile))
    return result


def descriptions(left_page, right_page, top: float, bottom: float) -> dict[str, str]:
    result: dict[str, str] = {}
    for page, levels, start_column in (
        (left_page, LEVELS_LEFT, 1),
        (right_page, LEVELS_RIGHT, 0),
    ):
        for index, level in enumerate(levels):
            column = index + start_column
            result[level] = box_text(page, COLUMN_BOUNDS[column], top, COLUMN_BOUNDS[column + 1], bottom)
    return result


def row_ranges(page, pair_index: int) -> list[tuple[float, float]]:
    marker = SECOND_CRITERION_WORDS[pair_index]
    marker_tops = [
        word["top"] for word in page.extract_words(use_text_flow=False)
        if word["x0"] < COLUMN_BOUNDS[1] and word["text"].lower().startswith(marker)
    ]
    if not marker_tops:
        raise RuntimeError(f"Separatore criterio non trovato: {marker}")
    split = min(marker_tops)
    return [(145, split), (split, 570)]


def build(pdf_path: Path) -> str:
    sections = ["# Esemplificazioni profili professionali CCNL 2021", ""]
    with pdfplumber.open(pdf_path) as document:
        for start_page in GROUP_START_PAGES:
            first_left = document.pages[start_page - 1]
            first_right = document.pages[start_page]
            group_profiles = profiles(first_left, first_right)
            profile_range = f"{group_profiles[0][1]} - {group_profiles[-1][1]}"
            sections.extend([f"## {area_title(first_left)} - {profile_range}", ""])
            criterion_index = 0
            for pair_offset in range(0, 6, 2):
                left_page = document.pages[start_page + pair_offset - 1]
                right_page = document.pages[start_page + pair_offset]
                for top, bottom in row_ranges(left_page, pair_offset // 2):
                    values = descriptions(left_page, right_page, top, bottom)
                    sections.extend([
                        f"### {CRITERIA[criterion_index]}",
                        "",
                        "| Livello | Profilo | Descrizione |",
                        "|---|---|---|",
                    ])
                    for level, profile in group_profiles:
                        sections.append(f"| {level} | {profile} | {values[level].replace('|', '/')} |")
                    sections.append("")
                    criterion_index += 1
    return "\n".join(sections).strip() + "\n"


if __name__ == "__main__":
    source = Path(sys.argv[1]).resolve()
    destination = Path(sys.argv[2]).resolve()
    destination.write_text(build(source), encoding="utf-8")
    print(destination)
