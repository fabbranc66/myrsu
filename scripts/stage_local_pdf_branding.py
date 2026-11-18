import argparse
import hashlib
import json
import shutil
from collections import Counter
from pathlib import Path

from pypdf import PdfReader, PdfWriter
from pypdf.generic import ContentStream, NameObject


ROOT = Path(__file__).resolve().parents[1]


def digest(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def text_of(operations):
    return [str(arguments[0]) for arguments, operator in operations if operator == b"Tj"]


def strip_branding(page, reader, allow_unbranded=False):
    stream = ContentStream(page.get_contents(), reader)
    operations = stream.operations
    remove = set()
    stack = []
    counts = Counter()
    for index, (arguments, operator) in enumerate(operations):
        if operator == b"q":
            stack.append(index)
        elif operator == b"Q":
            start = stack.pop()
            group = operations[start:index + 1]
            words = text_of(group)
            if words in (["RSU", "Sitem Canegrate"], ["RSU"], ["Sitem Canegrate"]) and any(op == b"cm" for _, op in group):
                remove.update(range(start, index + 1))
                counts["watermarks"] += 1
    if stack:
        raise ValueError("Stato grafico non bilanciato")
    start = None
    for index, (arguments, operator) in enumerate(operations):
        if operator == b"BT":
            start = index
        elif operator == b"ET" and start is not None:
            group = operations[start:index + 1]
            words = text_of(group)
            positions = [values for values, op in group if op == b"Td"]
            if positions and not any(position in remove for position in range(start, index + 1)):
                horizontal, vertical = map(float, positions[-1])
                role = None
                if words == ["RSU"] and abs(horizontal - 42) < 1 and abs(vertical - 774) < 1:
                    role = "header"
                elif words == ["Canegrate"] and abs(horizontal - 44) < 1 and abs(vertical - 755) < 1:
                    role = "subtitle"
                elif words == ["RSU Sitem Canegrate"] and vertical < 60:
                    role = "footer"
                if role:
                    remove.update(range(start, index + 1))
                    counts[role] += 1
            start = None
    if not (allow_unbranded and not counts) and (counts["watermarks"] not in (2, 4) or counts["footer"] != 1 or counts["header"] != counts["subtitle"]):
        raise ValueError(f"Formato inatteso: {dict(counts)}")
    stream.operations = [operation for index, operation in enumerate(operations) if index not in remove]
    return stream, counts


def links(page):
    result = []
    for reference in page.get("/Annots", []):
        annotation = reference.get_object()
        result.append((str(annotation.get("/Subtype")), tuple(map(float, annotation.get("/Rect", []))), str(annotation.get("/A", {}).get("/URI", ""))))
    return result


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("inventory", type=Path)
    parser.add_argument("--write", action="store_true")
    parser.add_argument("--normalize-originals", action="store_true")
    options = parser.parse_args()
    inventory = json.loads(options.inventory.read_text(encoding="utf-8"))
    staging = options.inventory.parent / "staged"
    if options.normalize_originals:
        normalized = staging / "normalized"
        normalized.mkdir(parents=True, exist_ok=True)
        for entry in inventory["documents"]:
            if entry["pdf_hash"] != entry["original_hash"]:
                continue
            source = Path(entry["original_path"])
            if digest(source) != entry["original_hash"]:
                raise ValueError("Originale modificato")
            writer = PdfWriter()
            writer.clone_document_from_reader(PdfReader(source))
            with (normalized / f"cfg-{entry['configuration_id']}-{entry['document']['id']}.pdf").open("wb") as output:
                writer.write(output)
        print("Originali compatibili preparati senza alterare i file sorgente.")
        return
    if options.write:
        staging.mkdir(exist_ok=True)
    manifest = {"inventory": str(options.inventory.resolve()), "documents": []}
    totals = Counter()
    overlays = {name: PdfReader(ROOT / "storage/private/branding/fixtures" / name) for name in ("overlay.pdf", "watermark-only.pdf")} if options.write else {}
    rebuilt = json.loads((staging / "rebuilt.json").read_text()) if options.write else {}
    for entry in inventory["documents"]:
        source = Path(entry["pdf_path"])
        original = Path(entry["original_path"])
        if digest(source) != entry["pdf_hash"] or digest(original) != entry["original_hash"]:
            raise ValueError("Documento modificato dopo inventario")
        reader = PdfReader(source, strict=True)
        if reader.get_fields() or reader.trailer["/Root"].get("/Perms"):
            raise ValueError("Documento con campi o firma PDF: controllo manuale richiesto")
        writer = PdfWriter()
        writer.clone_document_from_reader(reader)
        page_counts = []
        preserved_streams = []
        bare_original = entry["pdf_hash"] == entry["original_hash"]
        for index, page in enumerate(writer.pages):
            try:
                stream, counts = strip_branding(page, writer, entry["pdf_hash"] == entry["original_hash"])
            except ValueError as error:
                raise ValueError(f"Configurazione {entry['configuration_id']}, documento {entry['document']['id']}, pagina {index + 1}: {error}") from error
            page_counts.append(dict(counts))
            totals.update(counts)
            preserved_streams.append(stream.get_data())
            if options.write and not bare_original:
                page[NameObject("/Contents")] = stream
                preserved_data = stream.get_data()
                overlay_name = "overlay.pdf" if counts["header"] else "watermark-only.pdf"
                overlay = overlays[overlay_name]
                page.merge_page(overlay.pages[0], over=True)
                if preserved_data not in page.get_contents().get_data():
                    raise ValueError("Contenuto originale modificato")
                if links(page) != links(reader.pages[index]):
                    raise ValueError("Collegamenti modificati")
        result = {"configuration_id": entry["configuration_id"], "id": entry["document"]["id"], "pages": len(reader.pages), "branding": page_counts}
        if options.write:
            destination = staging / f"cfg-{entry['configuration_id']}-{entry['document']['id']}.pdf"
            if bare_original:
                shutil.copyfile(rebuilt[f"{entry['configuration_id']}:{entry['document']['id']}"], destination)
            else:
                with destination.open("wb") as output:
                    writer.write(output)
            check = PdfReader(destination, strict=True)
            if len(check.pages) != len(reader.pages) + int(bare_original):
                raise ValueError("Numero pagine modificato")
            for page_index, (before, after) in enumerate(zip(reader.pages, check.pages)):
                if not bare_original and links(before) != links(after):
                    raise ValueError("Annotazioni modificate")
                if not bare_original and preserved_streams[page_index] not in after.get_contents().get_data():
                    raise ValueError("Contenuto modificato dopo salvataggio")
                text = after.extract_text()
                if text.count("Del. FIM-CISL") < 3 or "RSU Sitem Canegrate" in text:
                    raise ValueError("Filigrana non aggiornata")
            result.update({"staged_path": str(destination.resolve()), "checksum": digest(destination), "size": destination.stat().st_size})
            result["pages"] = len(check.pages)
        manifest["documents"].append(result)
    if options.write:
        (staging / "manifest.json").write_text(json.dumps(manifest, indent=2), encoding="utf-8")
    print(json.dumps({"documents": len(manifest["documents"]), "pages": sum(item["pages"] for item in manifest["documents"]), "branding": dict(totals), "staged": options.write}))


if __name__ == "__main__":
    main()
