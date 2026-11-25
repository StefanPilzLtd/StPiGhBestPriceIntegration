#!/usr/bin/env python3
"""Enforce the requested 90% statement coverage for critical production logic."""
import sys, pathlib, xml.etree.ElementTree as ET, json
critical = {'Feed', 'Catalog', 'Configuration', 'Entitlements', 'Pricing', 'Analytics', 'Security'}
p = pathlib.Path(sys.argv[1])
if not p.is_file():
    raise SystemExit('Coverage evidence missing; run PHPUnit with PCOV/Xdebug first')
root = ET.parse(p).getroot()
counts = {}
for f in root.iter('file'):
    parts = pathlib.PurePosixPath(f.attrib['name']).parts
    if 'src' not in parts:
        continue
    folder = parts[parts.index('src') + 1]
    if folder not in critical:
        continue
    lines = [line for line in f.findall('line') if line.attrib.get('type') == 'stmt']
    total, covered = counts.get(folder, (0, 0))
    counts[folder] = (total + len(lines), covered + sum(int(line.attrib.get('count', 0)) > 0 for line in lines))
failures = []
for folder in sorted(critical):
    total, covered = counts.get(folder, (0, 0))
    percent = 100 * covered / total if total else 0
    print(f'{folder}: {covered}/{total} statements, {percent:.2f}%')
    if not total or percent < 90:
        failures.append(folder)
if failures:
    raise SystemExit('90% critical-logic coverage gate not met: ' + ', '.join(failures))
