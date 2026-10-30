/* Shared registration helpers. Postal lookup sends only a seven-digit code to zipcloud. */
(function(root){'use strict';
function fold(value){return String(value??'').normalize('NFKC').replace(/[ぁ-ゖ]/g,c=>String.fromCharCode(c.charCodeAt(0)+0x60)).replace(/[‐‑‒–—―−]/g,'-').replace(/\s+/g,' ').trim();}
function payer(value){const v=fold(value);if(!v||v.length>200||!/^[-ァ-ヺー・0-9() .]+$/u.test(v))throw new Error('振込名義はカタカナで入力してください（数字・空白・括弧・ハイフンも使用できます）。');return v;}
function postalDigits(value){return String(value??'').normalize('NFKC').replace(/[-‐‑‒–—―−\s]/g,'');}
function formatPostal(value){const code=postalDigits(value);return /^\d{7}$/.test(code)?code.slice(0,3)+'-'+code.slice(3):String(value??'');}
async function postalAddresses(code,fetcher=root.fetch){
 if(!/^\d{7}$/.test(code))throw new Error('郵便番号は数字7桁で入力してください。');
 const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),8000);
 try{const response=await fetcher('https://zipcloud.ibsnet.co.jp/api/search?zipcode='+code+'&limit=100',{signal:controller.signal,credentials:'omit',referrerPolicy:'no-referrer'});if(!response.ok)throw new Error('通信エラー');const data=await response.json();if(data.status!==200)throw new Error('検索エラー');return [...new Set((data.results||[]).filter(r=>r.zipcode===code).map(r=>[r.address1,r.address2,r.address3].filter(Boolean).join('')).filter(Boolean))];}finally{clearTimeout(timer);}
}
const API={fold,payer,postalDigits,formatPostal,postalAddresses};root.FormAssist=API;if(typeof module!=='undefined'&&module.exports)module.exports=API;
if(typeof document==='undefined')return;
const states=new WeakMap();
function init(form){if(states.has(form))return states.get(form);const name=form.querySelector('[name=name]'),display=form.querySelector('[name=display]'),pay=form.querySelector('[name=payer]');if(!name||!display||!pay)return null;const state={name,display,pay,auto:!display.value||display.value===name.value,last:name.value};states.set(form,state);if(!display.value&&name.value)display.value=name.value;pay.lang='ja';pay.setAttribute('spellcheck','false');pay.setAttribute('autocomplete','off');pay.placeholder='例：ヤマダ ハナコ';
const examples={name:'例：山田 花子',kana:'例：やまだ はなこ',oldname:'例：鈴木（卒業時の姓）',postal:'例：444-0000',address:'例：愛知県岡崎市○○町1-2-3',phone:'例：09012345678',display:'例：山田 花子'};
for(const [key,example] of Object.entries(examples)){const el=form.querySelector('[name='+key+']');if(el)el.placeholder=example;}
for(const [key,text] of [['name','姓と名の間を1文字空けてください。'],['kana','ひらがなで、姓と名の間を1文字空けてください。'],['oldname','姓が変わった方は、卒業時の姓（旧姓）を入力してください。'],['address','都道府県から入力してください。']]){const el=form.querySelector('[name='+key+']');if(el){const hint=document.createElement('small');hint.className='input-example-note';hint.textContent=text;el.insertAdjacentElement('afterend',hint);}}
initPostal(form,state);return state;}
function initPostal(form,state){
 const zip=form.querySelector('[name=postal]'),address=form.querySelector('[name=address]');if(!zip||!address)return;
 zip.setAttribute('inputmode','numeric');zip.autocomplete='postal-code';
 const help=document.createElement('small');help.textContent='数字7桁で住所を補完します（郵便番号のみzipcloudへ送信）。手入力もできます。';
 const status=document.createElement('small');status.setAttribute('role','status');status.setAttribute('aria-live','polite');
 const button=document.createElement('button');button.type='button';button.className='btn btn-outline btn-small';button.textContent='郵便番号から住所を検索';
 const choices=document.createElement('select');choices.className='postal-choices';choices.hidden=true;choices.setAttribute('aria-label','郵便番号に対応する住所を選択');
 zip.parentElement.append(help,button,status,choices);let seq=0,timer,proposals=[];
 function clearChoices(){proposals=[];choices.hidden=true;choices.replaceChildren();}
 function apply(value){address.value=value;address.dispatchEvent(new Event('input',{bubbles:true}));status.textContent='住所を入力しました。必要に応じて続きの住所を入力してください。';clearChoices();}
 async function lookup(){clearTimeout(timer);const code=postalDigits(zip.value);if(!/^\d{7}$/.test(code)){status.textContent='郵便番号は数字7桁で入力してください。';return;}
 zip.value=formatPostal(zip.value);const id=++seq,before=address.value;clearChoices();status.textContent='住所を検索しています…';
 try{const found=await postalAddresses(code);if(id!==seq||postalDigits(zip.value)!==code||!form.isConnected)return;
 if(!found.length){status.textContent='該当する住所が見つかりませんでした。住所を直接入力してください。';return;}
 if(found.length===1&&address.value.trim()===''&&address.value===before){apply(found[0]);return;}
 proposals=found;const empty=document.createElement('option');empty.value='';empty.textContent='住所の候補を選択';choices.append(empty);found.forEach((value,i)=>{const option=document.createElement('option');option.value=String(i);option.textContent=value;choices.append(option);});choices.hidden=false;status.textContent=address.value?'入力済みの住所は保持しています。変更する場合は候補を選んでください。':'複数の住所が見つかりました。候補を選んでください。';
 }catch(e){if(id===seq&&postalDigits(zip.value)===code&&form.isConnected)status.textContent='住所を検索できませんでした。住所を直接入力するか、再検索してください。';}}
 function schedule(ev){if(ev.isComposing)return;++seq;clearTimeout(timer);clearChoices();status.textContent='';const code=postalDigits(zip.value);if(/^\d{7}$/.test(code)){zip.value=formatPostal(zip.value);timer=setTimeout(lookup,400);}}
 zip.addEventListener('input',schedule);zip.addEventListener('compositionend',schedule);button.addEventListener('click',ev=>{ev.preventDefault();lookup();});choices.addEventListener('change',()=>{if(choices.value!==''&&proposals[Number(choices.value)])apply(proposals[Number(choices.value)]);});
}
function scan(){document.querySelectorAll('form').forEach(form=>{
init(form);
// Input hints only: do not change stored values or validation rules.
for(const el of form.querySelectorAll('input')){
 if(el.name==='phone'){el.type='tel';el.setAttribute('inputmode','tel');el.autocomplete='tel';}
 if(el.name==='postal')el.autocomplete='postal-code';
 if(el.type==='email'){el.autocomplete='email';el.setAttribute('autocapitalize','none');el.setAttribute('spellcheck','false');}
}
});}
function normalize(state){try{state.pay.value=payer(state.pay.value);state.pay.setCustomValidity('');return true;}catch(e){state.pay.setCustomValidity(e.message);return false;}}
document.addEventListener('input',ev=>{const s=ev.target.form&&init(ev.target.form);if(!s)return;if(ev.target===s.display){s.auto=s.display.value===s.name.value;return;}if(ev.target===s.name&&(s.auto||s.display.value===s.last)){s.display.value=s.name.value;s.auto=true;s.last=s.name.value;}if(ev.target===s.pay)s.pay.setCustomValidity('');});
document.addEventListener('blur',ev=>{const s=ev.target.form&&init(ev.target.form);if(s&&ev.target===s.pay&&s.pay.value)normalize(s);},true);
document.addEventListener('submit',ev=>{const s=init(ev.target);if(!s)return;if(!s.display.value.trim())s.display.value=s.name.value.trim();if(!normalize(s)){ev.preventDefault();ev.stopImmediatePropagation();s.pay.reportValidity();}},true);
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',scan);else scan();new MutationObserver(scan).observe(document.documentElement,{childList:true,subtree:true});
})(typeof globalThis!=='undefined'?globalThis:this);
