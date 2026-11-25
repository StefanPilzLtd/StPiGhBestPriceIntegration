#!/usr/bin/env python3
"""One weekly request per official documentation URL; never fetch products/offers."""
import json,pathlib,hashlib,urllib.request,os
sources={'bestprice-feed':'https://merchants.bestprice.gr/xml-specs/','bestprice-360':'https://raw.githubusercontent.com/TheBestCo/bestprice-360/master/README.md','bestprice-terms':'https://www.bestprice.gr/policies/terms','bestprice-mcp-docs':'https://www.bestprice.gr/mcp','bestprice-partners':'https://www.bestprice.gr/synergies','shopware-quality':'https://developer.shopware.com/docs/guides/development/testing/store/quality-guidelines.html','shopware-iap':'https://developer.shopware.com/docs/guides/plugins/plugins/in-app-purchases.html','shopware-release':'https://api.github.com/repos/shopware/shopware/releases/latest','php-security':'https://www.php.net/releases/feed.php','symfony-security':'https://symfony.com/blog/category/security.rss'}
root=pathlib.Path(__file__).resolve().parents[1];baseline=root/'artifacts/source-baseline.json';old=json.loads(baseline.read_text()) if baseline.exists() else {};new={};changes=[]
for key,url in sources.items():
 try:
  with urllib.request.urlopen(urllib.request.Request(url,headers={'User-Agent':'StPiGhBestPriceIntegration documentation monitor'}),timeout=20) as response:payload=response.read(2_000_000)
  digest=hashlib.sha256(payload).hexdigest();new[key]=digest
  if key in old and old[key]!=digest:changes.append((key,url,digest))
 except Exception as e:
  new[key]=old.get(key);print(key,'unavailable',type(e).__name__)
repo=os.getenv('GITHUB_REPOSITORY');token=os.getenv('GITHUB_TOKEN')
if changes and repo and token:
 headers={'Authorization':'Bearer '+token,'Accept':'application/vnd.github+json','X-GitHub-Api-Version':'2022-11-28'}
 with urllib.request.urlopen(urllib.request.Request('https://api.github.com/repos/'+repo+'/issues?state=open&per_page=100',headers=headers)) as r:issues=json.load(r)
 for key,url,digest in changes:
  title='[Source monitor] '+key+' changed'
  if any(i['title']==title for i in issues):continue
  body={'title':title,'body':'Official documentation content changed: '+url+'\nSHA256: '+digest+'\nReview semantic changes manually. HTML changes can be cosmetic. No productive pricing, tracking or entitlement change is applied automatically.'}
  req=urllib.request.Request('https://api.github.com/repos/'+repo+'/issues',headers=headers,data=json.dumps(body).encode(),method='POST')
  with urllib.request.urlopen(req) as response:response.read()
baseline.parent.mkdir(exist_ok=True);baseline.write_text(json.dumps(new,indent=2)+'\n');print('Changed sources:',len(changes))
