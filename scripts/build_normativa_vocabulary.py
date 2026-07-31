from pathlib import Path
import json
import re

base = Path(__file__).resolve().parents[1]
source = base / 'docs/normativa_vocabulary_source.md'
out = base / 'docs/normativa_vocabulary.json'
text = source.read_text(encoding='utf-8', errors='ignore')

categories = {}
current = None
skip_heads = {'per il vocabolario', 'ogni voce', 'varianti e refusi', 'istruzione pronta'}

for raw in text.splitlines():
    line = raw.strip()
    if not line:
        continue
    line = line.replace('â€”', '—').replace('â†’', '→').replace('â€“', '-').replace('â€™', '’')
    match = re.match(r'^(\d+)\.\s+(.+)$', line)
    if match:
        current = match.group(2).strip()
        categories.setdefault(current, [])
        continue
    lower = line.lower()
    if any(lower.startswith(item) for item in skip_heads):
        continue
    if current is None:
        continue
    if '→' in line:
        left, right = [part.strip() for part in line.split('→', 1)]
        variants = [item.strip() for item in re.split(r',|/', left) if item.strip()]
        canonical = right.split(';')[0].strip()
        categories[current].append({
            'canonical': canonical,
            'variants': variants,
            'category': current,
            'definition': f'Normalizzazione controllata: {canonical}',
            'synonyms': [],
            'links': [],
            'type': 'normalization',
        })
        continue
    if len(line) > 160:
        continue
    if ' — ' in line or '—' in line:
        parts = [part.strip() for part in line.split('—', 1)]
        canonical, definition = parts[0], parts[1]
    else:
        canonical, definition = line, f'Termine di dominio: {current}'
    categories[current].append({
        'canonical': canonical,
        'variants': [],
        'category': current,
        'definition': definition,
        'synonyms': [],
        'links': [],
        'type': 'domain_term',
    })

entries = []
seen = set()
for category, items in categories.items():
    for item in items:
        key = (item['category'].lower(), item['canonical'].lower())
        if key in seen:
            continue
        seen.add(key)
        entries.append(item)

payload = {
    'version': '2026-07-31',
    'description': 'Vocabolario strutturato MyRSU per Normativa Rapida, pratiche e ricerca AI.',
    'entries': entries,
}
out.write_text(json.dumps(payload, ensure_ascii=False, indent=2), encoding='utf-8')
print(len(entries), out)
