#!/usr/bin/env python3
"""Remove only the two misplaced core Storefront manifests emitted by CLI 0.21.0."""
from pathlib import Path
import json
root = Path(__file__).resolve().parents[1]
folder = root / 'src/Storefront/Resources/public/administration/.vite'
for name in ['entrypoints.json', 'manifest.json']:
    p = folder / name
    if not p.exists():
        continue
    data = json.loads(p.read_text())
    if name == 'entrypoints.json':
        assert data.get('base') == '/bundles/storefront/administration/', 'Unexpected entrypoints; inspect instead of removing'
    else:
        assert data.get('main.js', {}).get('name') == 'storefront' and data['main.js'].get('file') == 'js/storefront.js', 'Unexpected manifest; inspect instead of removing'
    p.unlink()
for p in [folder, *folder.parents]:
    if p == root / 'src':
        break
    if p.is_dir() and not any(p.iterdir()):
        p.rmdir()
