import argparse
import hashlib
import json
from pathlib import Path

from pypdf import PdfReader, PdfWriter
from pypdf.generic import ContentStream, NameObject


ROOT = Path(__file__).resolve().parents[1]
PRIVATE = ROOT / "storage/private/branding"


def digest(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def watermark_spans(page, reader):
    operations = ContentStream(page.get_contents(), reader).operations
    resources = page["/Resources"].get("/XObject", {})
    stack = []
    spans = []
    for index, (arguments, operator) in enumerate(operations):
        if operator == b"q":
            stack.append(index)
        elif operator == b"Q":
            if not stack:
                raise ValueError("Stato grafico non bilanciato")
            start = stack.pop()
            group = operations[start:index + 1]
            words = [str(values[0]) for values, operation in group if operation == b"Tj"]
            images = [resources[values[0]].get_object() for values, operation in group if operation == b"Do"]
            if words and set(words) == {"Del. FIM-CISL"} and len(images) in (1, 2) and all((image.get("/Width"), image.get("/Height")) == (452, 437) for image in images):
                spans.append((start, index + 1))
    if stack or len(spans) != 2:
        raise ValueError(f"Attese due filigrane, trovate {len(spans)}")
    if spans[0][1] > spans[1][0]:
        raise ValueError("Filigrane sovrapposte nella struttura PDF")
    return operations, spans


def annotations(page):
    return [(str(item.get_object().get("/Subtype")), tuple(map(float, item.get_object().get("/Rect", []))), str(item.get_object().get("/A", {}).get("/URI", ""))) for item in page.get("/Annots", [])]


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("inventory", type=Path)
    parser.add_argument("destination", type=Path)
    options = parser.parse_args()
    destination = options.destination.resolve()
    if not destination.is_relative_to((PRIVATE / "staged").resolve()):
        raise ValueError("Usare soltanto lo staging locale protetto")
    inventory = json.loads(options.inventory.read_text(encoding="utf-8"))
    destination.mkdir(parents=True, exist_ok=True)
    inventory_path = destination / "inventory.json"
    if (destination / "applied.json").exists():
        raise ValueError("Intervento gia applicato: usare una nuova cartella")
    overlay_reader = PdfReader(PRIVATE / "fixtures/overlay.pdf", strict=True)
    overlay = overlay_reader.pages[0]
    if float(overlay["/Resources"]["/ExtGState"]["/BrandUnderlayAlpha"]["/ca"]) != 0.32 or "/BrandSymbolBase" not in overlay["/Resources"]["/XObject"]:
        raise ValueError("Modello di contrasto non aggiornato")
    operations, spans = watermark_spans(overlay, overlay_reader)
    stream = ContentStream(overlay.get_contents(), overlay_reader)
    stream.operations = [operation for start, end in spans for operation in operations[start:end]]
    overlay[NameObject("/Contents")] = stream
    manifest = {"inventory": str(inventory_path), "documents": []}
    total_pages = 0
    for entry in inventory["documents"]:
        source = Path(entry["pdf_path"])
        if entry["document"]["conversion_status"] != "ready" or digest(source) != entry["pdf_hash"] or digest(Path(entry["original_path"])) != entry["original_hash"]:
            raise ValueError("Documento non pronto o modificato dopo inventario")
        reader = PdfReader(source, strict=True)
        if reader.get_fields() or reader.trailer["/Root"].get("/Perms"):
            raise ValueError("Documento con campi o firma PDF: controllo manuale richiesto")
        writer = PdfWriter()
        writer.clone_document_from_reader(reader)
        preserved = []
        for page_number, page in enumerate(writer.pages, 1):
            try:
                operations, spans = watermark_spans(page, writer)
            except ValueError as error:
                raise ValueError(f"Configurazione {entry['configuration_id']}, documento {entry['document']['id']}, pagina {page_number}: {error}") from error
            excluded = {index for start, end in spans for index in range(start, end)}
            content = ContentStream(page.get_contents(), writer)
            content.operations = [operation for index, operation in enumerate(operations) if index not in excluded]
            preserved.append(content.get_data())
            page[NameObject("/Contents")] = content
            page.merge_page(overlay, over=True)
        target = destination / f"cfg-{entry['configuration_id']}-{entry['document']['id']}.pdf"
        with target.open("wb") as output:
            writer.write(output)
        check = PdfReader(target, strict=True)
        if len(reader.pages) != len(check.pages):
            raise ValueError("Numero di pagine modificato")
        for page_index, (before, after) in enumerate(zip(reader.pages, check.pages)):
            if list(before.mediabox) != list(after.mediabox) or annotations(before) != annotations(after) or preserved[page_index] not in after.get_contents().get_data():
                raise ValueError("Contenuto o collegamenti modificati")
            watermark_spans(after, check)
        manifest["documents"].append({"configuration_id": entry["configuration_id"], "id": entry["document"]["id"], "staged_path": str(target), "checksum": digest(target), "size": target.stat().st_size, "pages": len(check.pages)})
        total_pages += len(check.pages)
    inventory_path.write_text(json.dumps(inventory, indent=2), encoding="utf-8")
    (destination / "manifest.json").write_text(json.dumps(manifest, indent=2), encoding="utf-8")
    print(json.dumps({"documents": len(manifest["documents"]), "pages": total_pages, "content_and_links_preserved": True, "destination": str(destination)}))


if __name__ == "__main__":
    main()
