/* Keep the context of an operation across client renders and PHP redirects. */
(function(){'use strict';
const root=()=>document.querySelector('main'),spa=typeof route==='function',storageKey='kita-navigation-context-v1';
let rendered=spa?'':location.href,pending=null,modalOrigin=null,sequence=0;
const filterIds=['peopleQuery','peopleClass','peopleStatus','bankState','logQuery','logActor','logFrom','logTo'];
const text=el=>(el?.textContent||'').replace(/\s+/g,' ').trim();
function formAction(f){return f?.querySelector('[name="action"]')?.value||'';}
function formKey(f){return {id:f.id||'',action:formAction(f),data:Object.fromEntries(Object.entries(f.dataset).filter(([k])=>['photo','lifeForm','dutyForm','community','id'].includes(k))),fields:Object.fromEntries(['id','class','registration_id','bank_id','expense_id','refund_id'].map(k=>[k,f.querySelector('input[type="hidden"][name="'+k+'"]')?.value]).filter(x=>x[1]!=null))};}
function matchForm(k){return Array.from(root()?.querySelectorAll('form')||[]).find(f=>(k.id?f.id===k.id:formAction(f)===k.action&&Object.entries(k.data).every(([n,v])=>f.dataset[n]===v)&&Object.entries(k.fields).every(([n,v])=>f.querySelector('[name="'+n+'"]')?.value===v)));}
function decorate(){const r=root();if(!r)return;
 r.querySelectorAll('form').forEach(f=>{const action=formAction(f),id=f.dataset.id||f.querySelector('[name="id"]')?.value;
  const photo=f.dataset.photo||['photo_edit','photo_remove','photo_restore'].includes(action),refund=f.dataset.lifeForm&&id&&id!=='0'||/^refund_(record|cancel|undo|link)$/.test(action);
  if(photo&&id)f.closest('article,.panel')?.setAttribute('data-nav-record','photo:'+id);
  else if(refund&&id)f.closest('section.panel')?.setAttribute('data-nav-record','refund:'+id);
  if(f.id.startsWith('communityAlbum-'))f.closest('details,.panel')?.setAttribute('data-nav-record','album:'+f.dataset.id);
 });
}
function locate(el){if(!el||!root()?.contains(el))return null;
 const record=el.closest('[data-nav-record]');if(record)return {record:record.dataset.navRecord};
 const row=el.closest('tr');if(row)return {row:Array.from(row.cells).slice(0,2).map(text),table:Array.from(row.closest('table').querySelectorAll('thead th')).slice(0,2).map(text)};
 const form=el.closest('form');if(form)return {form:formKey(form)};
 if(el.id)return {id:el.id};
 const region=el.closest('article,section,.panel,details');if(region){if(region.id)return {id:region.id};const h=region.querySelector('h1,h2,h3,summary');if(h)return {heading:text(h)};}
 return null;
}
function resolve(k){if(!k)return null;const r=root();if(k.id)return document.getElementById(k.id);if(k.record)return Array.from(r.querySelectorAll('[data-nav-record]')).find(x=>x.dataset.navRecord===k.record);if(k.form)return matchForm(k.form);if(k.row){return Array.from(r.querySelectorAll('tbody tr')).find(x=>JSON.stringify(Array.from(x.cells).slice(0,2).map(text))===JSON.stringify(k.row)&&JSON.stringify(Array.from(x.closest('table').querySelectorAll('thead th')).slice(0,2).map(text))===JSON.stringify(k.table));}if(k.heading)return Array.from(r.querySelectorAll('h1,h2,h3,summary')).find(x=>text(x)===k.heading)?.closest('article,section,.panel,details');return null;}
function capture(el){const key=locate(el),anchor=resolve(key);return {key,offset:anchor?.getBoundingClientRect().top??0,x:scrollX,y:scrollY,filters:Object.fromEntries(filterIds.map(id=>[id,document.getElementById(id)?.value]).filter(x=>x[1]!=null)),details:Array.from(root()?.querySelectorAll('details')||[]).map(d=>({key:locate(d),summary:text(d.querySelector('summary')),open:d.open})),at:Date.now()};}
function focusAt(el){if(!el)return false;const target=el.matches('form,article,section,.panel,details,tr')?el.querySelector('h1,h2,h3,summary')||el:el;if(!target.hasAttribute('tabindex'))target.setAttribute('tabindex','-1');target.style.scrollMarginTop='20px';target.focus({preventScroll:true});target.scrollIntoView({block:'start',behavior:'instant'});return true;}
function restore(c){if(!c)return;for(const [id,v]of Object.entries(c.filters||{})){const el=document.getElementById(id);if(el&&(el.tagName==='INPUT'||Array.from(el.options||[]).some(o=>o.value===v)))el.value=v;}
 if(spa){if(document.getElementById('peopleTable'))renderPeople();if(document.getElementById('bankTable'))renderBank();if(document.getElementById('logTable'))renderLogs();}
 for(const d of c.details||[]){const el=resolve(d.key);const target=el?.matches('details')?el:Array.from(root().querySelectorAll('details')).find(x=>text(x.querySelector('summary'))===d.summary);if(target)target.open=d.open;}
 const anchor=resolve(c.key);if(anchor)window.scrollTo({left:c.x,top:Math.max(0,scrollY+anchor.getBoundingClientRect().top-c.offset),behavior:'instant'});else window.scrollTo({left:c.x,top:c.y,behavior:'instant'});
}
function routeInfo(){if(spa){const u=new URL(location.hash.slice(1)||'/',location.href);return {path:u.pathname,params:u.searchParams};}const u=new URL(location.href),p=u.searchParams.get('page')||'home',id=u.searchParams.get('id')||(p==='admin_refunds'?u.searchParams.get('registration_id'):null),cl=u.searchParams.get('class'),album=u.searchParams.get('album');return {path:p==='home'?'/':p==='classes'?'/classes'+(cl?'/'+cl:''):p==='gallery'?'/gallery'+(album?'/'+album:''):p==='edit'?'/register/edit':p==='admin'?'/admin':p.startsWith('admin_')?'/admin/'+p.slice(6)+(id?'/'+id:''):'/'+p,params:u.searchParams};}
function headingNamed(pattern){return Array.from(root()?.querySelectorAll('h1,h2,h3,h4')||[]).find(x=>pattern.test(text(x)));}
function firstAdminContent(){const nav=root()?.querySelector('.admin-tabs');let el=nav?.nextElementSibling;while(el&&el.matches('p,.notice,.button-row'))el=el.nextElementSibling;return el?.querySelector('h1,h2,h3')||el;}
function destination(){const {path,params}=routeInfo();if(!spa&&location.hash&&document.getElementById(location.hash.slice(1)))return document.getElementById(location.hash.slice(1));
 if(document.getElementById('demoLogin')||document.querySelector('form [name="action"][value="login"]'))return headingNamed(/ログイン/);
 if(path==='/')return null;
 if(path==='/my/photos')return document.getElementById('own-photo-'+Number(params.get('photo')))||document.getElementById('my-photo-posts');
 if(path==='/my/photo-post')return document.getElementById('photo-post');
 if(/^\/classes\/\d+/.test(path))return document.getElementById('class-roster-heading');
 if(/^\/gallery\/\d+/.test(path))return root().querySelector('.section h2');
 if(path==='/admin/contacts'){
  if(params.get('mode')==='classes')return document.getElementById('class-contact-editor');
  if(params.has('new')||params.has('edit'))return document.getElementById('duty-editor');
  if(params.has('detail')||params.has('person'))return document.getElementById('duty-detail')||headingNamed(/担当の詳細/);
  if(params.has('view')||params.has('role')||params.has('q')||params.has('mine')||params.has('kind'))return document.getElementById('duty-results');
 }
 if(/^\/admin\/refunds\/\d+/.test(path))return headingNamed(/返金予定を登録/);
 if(/^\/admin\/(inquiries|broadcast)\/\d+/.test(path))return root().querySelector('.message-body')?.closest('.panel');
 if(path==='/admin'&&['query','class_filter','status_filter','payment_filter'].some(k=>params.has(k)))return root().querySelector('[role="status"]')||root().querySelector('.table-wrap');
 if(path==='/admin/inquiries'&&params.has('status'))return root().querySelector('.table-wrap');
 if(path.startsWith('/admin'))return firstAdminContent();
 if(path==='/my')return document.getElementById('my-person-heading')||root().querySelector('.registration-guide')||root().querySelector('h1');
 return root()?.querySelector('.page-title h1,h1,h2');
}
function successfulTarget(intent){if(!intent||!spa)return null;
 if(intent.href==='#/admin/contacts'&&/一覧/.test(intent.label||''))return document.getElementById('duty-results');
 if(intent.href==='#/admin/contacts'&&intent.label==='担当を検索・管理する')return headingNamed(/担当を探す/);
 if(intent.kind==='import')return headingNamed(/取込結果/);
 if(intent.kind==='photo-upload')return headingNamed(/写真・投稿コメントの編集/);
 if(intent.kind==='duty-save'){
  const id=intent.id||Math.max(0,...state.duties.map(x=>x.id));return Array.from(root().querySelectorAll('a')).find(a=>a.hash==='#/admin/contacts?edit='+id)?.closest('tr');
 }
 if(intent.kind==='album-new'){const id=Math.max(0,...state.albums.map(x=>x.id));return document.getElementById('communityAlbum-'+id)?.closest('details');}
 if(intent.kind==='refund-plan'){const id=Math.max(0,...state.refunds.map(x=>x.id));return resolve({record:'refund:'+id});}
 return null;
}
function remember(ev){const el=ev.target.closest('button,a,input,select,form');if(!el)return;
 if(ev.type==='click'&&(ev.button!==0||ev.metaKey||ev.ctrlKey||ev.altKey||ev.shiftKey))return;
 const inDialog=!!el.closest('dialog'),f=ev.type==='submit'?ev.target:el.closest('form');
 const c=inDialog?(modalOrigin||capture(null)):capture(el);
 pending={...c,from:location.hash,href:el.closest('a')?.getAttribute('href')||'',label:text(el),kind:'operation',action:formAction(f)};
 if(ev.type==='click'&&el.closest('header #mainNav,header .brand'))pending.kind='main-menu';
 if(el.matches('[data-action="person-detail"],[data-action="transaction-detail"]'))modalOrigin=c;
 if(el.matches('[data-action="import-fixture"]')||el.id==='bankFile')pending.kind='import';
 if(el.id==='photoFiles')pending.kind='photo-upload';
 if(ev.type==='submit'&&f){
  if(f.dataset.dutyForm==='save'){pending.kind='duty-save';pending.id=Number(f.elements.id?.value||0);}
  if(f.id==='communityAlbum-0')pending.kind='album-new';
  if(f.dataset.lifeForm==='plan')pending.kind='refund-plan';
 }
 if(!spa){pending.url=location.href;pending.page=new URL(location.href).searchParams.get('page')||'home';try{sessionStorage.setItem(storageKey,JSON.stringify(pending));}catch{}}
}
window.addEventListener('click',remember,true);window.addEventListener('submit',remember,true);window.addEventListener('change',remember,true);
// Native validation should reveal the first invalid field without changing pages.
let invalidScheduled=false;window.addEventListener('invalid',ev=>{if(invalidScheduled)return;const form=ev.target.form;if(!form)return;invalidScheduled=true;queueMicrotask(()=>{invalidScheduled=false;const field=form.querySelector('input:invalid,select:invalid,textarea:invalid');if(field){field.focus({preventScroll:true});field.scrollIntoView({block:'center',behavior:'instant'});}});},true);
if(spa){if('scrollRestoration' in history)history.scrollRestoration='manual';const base=route;route=function(){const hash=location.hash||'#/',different=hash!==rendered,fresh=pending&&Date.now()-pending.at<180000?pending:null,c=fresh||capture(document.activeElement),token=++sequence;pending=null;base();decorate();const success=successfulTarget(fresh);
 if(fresh?.kind==='main-menu')window.scrollTo({top:0,left:0,behavior:'instant'});else if(success)focusAt(success);else if(different||fresh?.href===hash){const target=destination();if(target)focusAt(target);else if(routeInfo().path==='/')window.scrollTo({top:0,left:0,behavior:'instant'});}else restore(c);
 rendered=hash;const dlg=document.querySelector('dialog[open]');if(dlg)window.scrollTo({left:c.x,top:c.y,behavior:'instant'});
 // An immediate route() followed by hashchange must not lose the restored context.
 queueMicrotask(()=>{if(token!==sequence)return;const error=root()?.querySelector('.error-text:not(:empty)');if(error&&fresh&&fresh.kind==='operation'&&error.getBoundingClientRect().height)focusAt(error);});
 };window.removeEventListener('hashchange',base);window.addEventListener('hashchange',route);route();
 // Re-selecting the current page still opens its actual content.
 window.addEventListener('click',ev=>{const a=ev.target.closest('a');if(!a||ev.defaultPrevented||ev.button!==0||ev.metaKey||ev.ctrlKey||ev.shiftKey||ev.altKey||a.getAttribute('href')?.startsWith('#/')!==true||a.hash!==location.hash)return;ev.preventDefault();if(a.closest('header #mainNav,header .brand')){pending=null;window.scrollTo({top:0,left:0,behavior:'instant'});}else focusAt(destination());});
}else{
 decorate();let saved=null;try{saved=JSON.parse(sessionStorage.getItem(storageKey)||'null');sessionStorage.removeItem(storageKey);}catch{}
 const page=new URL(location.href).searchParams.get('page')||'home',fromMainMenu=saved?.kind==='main-menu'&&Date.now()-saved.at<180000;if(saved&&(Date.now()-saved.at>=180000||saved.page!==page))saved=null;let operationResult=null;
 if(saved?.action==='import'){operationResult=Array.from(root().querySelectorAll('summary')).find(x=>/^取込 #/.test(text(x)));if(operationResult)operationResult.closest('details').open=true;}
 else if(saved?.action==='photo')operationResult=headingNamed(/写真・投稿コメントの編集/);
 else if(saved?.action==='broadcast_preview')operationResult=destination();
 const explicit=location.hash&&document.getElementById(location.hash.slice(1));if(fromMainMenu)window.scrollTo({top:0,left:0,behavior:'instant'});else if(explicit)focusAt(explicit);else if(operationResult)focusAt(operationResult);else if(saved&&Date.now()-saved.at<180000&&saved.page===page&&saved.action){restore(saved);}else focusAt(destination());
 if(saved){const error=root()?.querySelector('.error-text,.notice[role="alert"]');if(error&&text(error))focusAt(error);}
}
// Inline filters redraw only their results; keep text searches steady while typing.
window.addEventListener('change',ev=>{const targets={peopleClass:'peopleTable',peopleStatus:'peopleTable',bankState:'bankTable',logActor:'logTable',logFrom:'logTable',logTo:'logTable'};if(targets[ev.target.id])queueMicrotask(()=>focusAt(document.getElementById(targets[ev.target.id])));});
})();
