/* Shared registration helpers; no network or external service. */
(function(root){'use strict';
function fold(value){return String(value??'').normalize('NFKC').replace(/[ぁ-ゖ]/g,c=>String.fromCharCode(c.charCodeAt(0)+0x60)).replace(/[‐‑‒–—―−]/g,'-').replace(/\s+/g,' ').trim();}
function payer(value){const v=fold(value);if(!v||v.length>200||!/^[-ァ-ヺー・0-9() .]+$/u.test(v))throw new Error('振込名義はカタカナで入力してください（数字・空白・括弧・ハイフンも使用できます）。');return v;}
const API={fold,payer};root.FormAssist=API;if(typeof module!=='undefined'&&module.exports)module.exports=API;
if(typeof document==='undefined')return;
const states=new WeakMap();
function init(form){if(states.has(form))return states.get(form);const name=form.querySelector('[name=name]'),display=form.querySelector('[name=display]'),pay=form.querySelector('[name=payer]');if(!name||!display||!pay)return null;const state={name,display,pay,auto:!display.value||display.value===name.value,last:name.value};states.set(form,state);if(!display.value&&name.value)display.value=name.value;pay.lang='ja';pay.setAttribute('spellcheck','false');pay.setAttribute('autocomplete','off');pay.placeholder='例：ヤマダ タロウ';
const examples={name:'例：山田 太郎',kana:'例：やまだ たろう',oldname:'例：鈴木',postal:'例：444-0000',phone:'例：090-1234-5678',display:'例：山田 太郎'};
for(const [key,example] of Object.entries(examples)){const el=form.querySelector('[name='+key+']');if(el)el.placeholder=example;}
for(const [key,text] of [['name','姓と名の間を1文字空けてください。'],['kana','ひらがなで、姓と名の間を1文字空けてください。']]){const el=form.querySelector('[name='+key+']');if(el){const hint=document.createElement('small');hint.className='input-example-note';hint.textContent=text;el.insertAdjacentElement('afterend',hint);}}
return state;}
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
