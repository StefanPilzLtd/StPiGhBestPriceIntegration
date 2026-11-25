#!/usr/bin/env python3
"""Conservative repository scan. Not a substitute for a provider secret scanner."""
import pathlib,re,subprocess,sys
root=pathlib.Path(__file__).resolve().parents[1]
tracked=subprocess.run(['git','ls-files','--cached','--others','--exclude-standard'],cwd=root,capture_output=True,text=True,check=True).stdout.splitlines()
patterns=[re.compile(r'gh[pousr]_[A-Za-z0-9]{25,}'),re.compile(r'-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----'),re.compile(r'AKIA[0-9A-Z]{16}')]
findings=[]
for name in tracked:
 p=root/name
 if not p.is_file() or p.suffix in {'.png','.jpg','.zip'}:continue
 text=p.read_text(errors='replace')
 if any(pattern.search(text) for pattern in patterns):findings.append(name)
 if p.name in {'auth.json','.env','.env.local'}:findings.append(name)
if findings:
 print('Potential secret files (content suppressed):',','.join(sorted(set(findings))));sys.exit(1)
print('No configured secret patterns found; manual review and provider scanning still required.')
