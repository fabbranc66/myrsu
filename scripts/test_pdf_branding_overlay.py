import argparse
import json
from pathlib import Path

import fitz
from pypdf import PdfReader
from pypdf.generic import ContentStream


ROOT = Path(__file__).resolve().parents[1]
DIRECTORY = ROOT / "storage/private/branding"


def check_overlay(path):
    reader = PdfReader(path, strict=True)
    for page_number, page in enumerate(reader.pages, 1):
        resources = page["/Resources"].get("/XObject", {})
        marks = []
        underlays = []
        colors = []
        illustrations = []
        alpha = 1.0
        stack = []
        for index, (arguments, operator) in enumerate(ContentStream(page.get_contents(), reader).operations):
            if operator == b"q":
                stack.append(alpha)
            elif operator == b"Q":
                alpha = stack.pop()
            elif operator == b"gs":
                alpha = float(page["/Resources"]["/ExtGState"][arguments[0]].get("/ca", alpha))
            if operator != b"Do":
                continue
            resource = resources[arguments[0]].get_object()
            dimensions = (resource.get("/Width"), resource.get("/Height"))
            if dimensions == (452, 437):
                marks.append(index)
                data = resource.get_data()
                if data == b"\xff" * len(data):
                    if abs(alpha - 0.32) > 0.001 or "/SMask" not in resource:
                        raise ValueError(f"Contrasto non trasparente: {path.name}, pagina {page_number}")
                    underlays.append(index)
                else:
                    colors.append(index)
            elif dimensions != (2172, 724):
                illustrations.append(index)
        if len(marks) != 4 or len(underlays) != 2 or len(colors) != 2 or any(base >= color for base, color in zip(underlays, colors)) or illustrations and min(marks) < max(illustrations):
            raise ValueError(f"Filigrana sotto immagine: {path.name}, pagina {page_number}")
    return len(reader.pages)


parser = argparse.ArgumentParser()
parser.add_argument("--manifest", type=Path)
options = parser.parse_args()
applied = json.loads((options.manifest or DIRECTORY / "applied.json").read_text())
inventory = json.loads(Path(applied["inventory"]).read_text())
staged_paths = {(entry["configuration_id"], entry["id"]): Path(entry["staged_path"]) for entry in applied["documents"]} if options.manifest else {}


def document_path(entry):
    return staged_paths.get((entry["configuration_id"], entry["document"]["id"]), Path(entry["pdf_path"]))


existing_pages = sum(check_overlay(document_path(entry)) for entry in inventory["documents"])
amina = next(entry for entry in inventory["documents"] if entry["configuration_id"] == 1 and entry["document"]["original_name"] == "amina.png")
amina_page = PdfReader(document_path(amina)).pages[0]
assert "/BrandSymbolBase" in amina_page["/Resources"]["/XObject"], "Amina: contrasto sulle fotografie mancante"
assert float(amina_page["/Resources"]["/ExtGState"]["/BrandUnderlayAlpha"]["/ca"]) == 0.32
assert amina_page["/Resources"]["/XObject"]["/BrandSymbolBase"]["/SMask"].get_data() == amina_page["/Resources"]["/XObject"]["/BrandSymbol"]["/SMask"].get_data(), "Amina: fondo non trasparente"
fixture_pages = sum(check_overlay(DIRECTORY / "fixtures" / name) for name in ("image.pdf", "uploaded.pdf", "report.pdf", "comunicato.pdf", "permit.pdf"))
for name, page_index in (("image", 0), ("report", 1)):
    with fitz.open(DIRECTORY / "fixtures" / f"{name}.pdf") as document:
        document[page_index].get_pixmap(matrix=fitz.Matrix(1, 1)).save(DIRECTORY / "fixtures" / f"{name}-overlay.png")
print(f"OK: filigrana sopra immagini e contrasto trasparente verificati su tutte le {existing_pages} pagine PDF e {fixture_pages} pagine di prova.")
