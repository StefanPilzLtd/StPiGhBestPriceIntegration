#!/usr/bin/env python3
import pathlib,json,datetime
root=pathlib.Path(__file__).resolve().parents[1];lock=json.loads((root/'composer.lock').read_text());components=[]
for scope,packages in [('runtime',lock.get('packages',[])),('development',lock.get('packages-dev',[]))]:
 for p in packages:components.append({'type':'library','name':p['name'],'version':p['version'],'scope':'required' if scope=='runtime' else 'excluded','licenses':[{'license':{'name':lic}} for lic in p.get('license',[])],'purl':'pkg:composer/'+p['name']+'@'+p['version'].lstrip('v')})
out={'bomFormat':'CycloneDX','specVersion':'1.6','version':1,'metadata':{'timestamp':datetime.datetime.now(datetime.timezone.utc).isoformat(),'component':{'type':'application','name':'StPiGhBestPriceIntegration','version':json.loads((root/'composer.json').read_text())['version']}},'components':components,'properties':[{'name':'stpi:host-runtime','value':'Shopware libraries are host-provided, not bundled; development packages are marked excluded.'}]}
(root/'artifacts').mkdir(exist_ok=True);(root/'artifacts/sbom.json').write_text(json.dumps(out,indent=2)+'\n');print(len(components),'dependency components inventoried')
