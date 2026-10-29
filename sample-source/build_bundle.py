"""Build the public sample. No runtime DB, logs, credentials or private uploads enter it."""
from pathlib import Path
import re,base64,zipfile
root=Path(__file__).resolve().parent
src=root/'dist';out=root/'github-pages';out.mkdir(exist_ok=True)
s=(src/'index.html').read_text()
s=s.replace('<link rel="stylesheet" href="style.css">','<style>'+(src/'style.css').read_text()+'</style>')
s=s.replace('<link rel="stylesheet" href="home-hero.css?v=20261030">','<style>'+(src/'home-hero.css').read_text()+'</style>')
for name in ['form-assist.js','core.js','app.js','finance.js','features.js','community-core.js','community.js','photos-core.js','photos.js','broadcast-core.js','broadcast.js']:
    code=(src/name).read_text().replace('</script','<\\/script')
    s=s.replace('<script src="'+name+'"></script>','<script>'+code+'</script>')
for p in (src/'assets').iterdir():
    mime={'.svg':'image/svg+xml','.jpg':'image/jpeg'}.get(p.suffix)
    if mime:s=s.replace('assets/'+p.name,'data:'+mime+';base64,'+base64.b64encode(p.read_bytes()).decode())
s=s.replace('/assets/social-preview.png','/social-preview.png')
(out/'index.html').write_text(s)
(out/'social-preview.png').write_bytes((src/'assets/social-preview.png').read_bytes())
(out/'.nojekyll').touch()
server=root.parent/'server'
with zipfile.ZipFile(out/'server-source.zip','w',zipfile.ZIP_DEFLATED) as z:
    for p in server.rglob('*'):
        if not p.is_file() or any(x in ('var','vendor','__pycache__') for x in p.relative_to(server).parts) or p.name=='config.local.php' or p.suffix in ('.log','.sqlite'):continue
        z.write(p,Path('server')/p.relative_to(server))
print('public HTML:',len(s.encode()),'bytes; server source archive excludes runtime data')
