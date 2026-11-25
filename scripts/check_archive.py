#!/usr/bin/env python3
import zipfile,sys,pathlib,hashlib
p=pathlib.Path(sys.argv[1]);z=zipfile.ZipFile(p);names=z.namelist();root='StPiGhBestPriceIntegration/'
assert names and all(n.startswith(root) and '..' not in pathlib.PurePosixPath(n).parts for n in names),'Invalid ZIP root/path'
for n in names:
 parts=pathlib.PurePosixPath(n).parts
 assert not any(v in parts for v in ['vendor','node_modules','tests','dev','artifacts','.git','.github','.phpunit.cache','docs','store','marketing','scripts']),'Forbidden archive content: '+n
 assert pathlib.PurePosixPath(n).name not in {'.env','.env.local','auth.json'},'Secret file in archive'
assert root+'composer.json' in names
assert root+'src/Resources/config/plugin.png' in names
assert any('/administration/assets/' in n and n.endswith('.js') for n in names),'Missing compiled administration'
checksum=hashlib.sha256(p.read_bytes()).hexdigest();p.with_suffix(p.suffix+'.sha256').write_text(checksum+'  '+p.name+'\n');print('Archive structure validated; SHA256',checksum)
