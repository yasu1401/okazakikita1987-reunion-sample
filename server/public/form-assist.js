/* Shared registration helpers; no network or external service. */
(function(root){'use strict';
function fold(value){return String(value??'').normalize('NFKC').replace(/[ぁ-ゖ]/g,c=>String.fromCharCode(c.charCodeAt(0)+0x60)).replace(/[‐‑‒–—―−]/g,'-').replace(/\s+/g,' ').trim();}
function payer(value){const v=fold(value);if(!v||v.length>200||!/^[-ァ-ヺー・0-9() .]+$/u.test(v))throw new Error('振込名義はカタカナで入力してください（数字・空白・括弧・ハイフンも使用できます）。');return v;}
const API={fold,payer};root.FormAssist=API;if(typeof module!=='undefined'&&module.exports)module.exports=API;
if(typeof document==='undefined')return;
const states=new WeakMap();
function init(form){if(states.has(form))return states.get(form);const name=form.querySelector('[name=name]'),display=form.querySelector('[name=display]'),pay=form.querySelector('[name=payer]');if(!name||!display||!pay)return null;const state={name,display,pay,auto:!display.value||display.value===name.value,last:name.value};states.set(form,state);if(!display.value&&name.value)display.value=name.value;pay.lang='ja';pay.setAttribute('spellcheck','false');pay.setAttribute('autocomplete','off');pay.placeholder='例：ヤマダ タロウ';return state;}
function scan(){document.querySelectorAll('form').forEach(init);}
function normalize(state){try{state.pay.value=payer(state.pay.value);state.pay.setCustomValidity('');return true;}catch(e){state.pay.setCustomValidity(e.message);return false;}}
document.addEventListener('input',ev=>{const s=ev.target.form&&init(ev.target.form);if(!s)return;if(ev.target===s.display){s.auto=s.display.value===s.name.value;return;}if(ev.target===s.name&&(s.auto||s.display.value===s.last)){s.display.value=s.name.value;s.auto=true;s.last=s.name.value;}if(ev.target===s.pay)s.pay.setCustomValidity('');});
document.addEventListener('blur',ev=>{const s=ev.target.form&&init(ev.target.form);if(s&&ev.target===s.pay&&s.pay.value)normalize(s);},true);
document.addEventListener('submit',ev=>{const s=init(ev.target);if(!s)return;if(!s.display.value.trim())s.display.value=s.name.value.trim();if(!normalize(s)){ev.preventDefault();ev.stopImmediatePropagation();s.pay.reportValidity();}},true);
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',scan);else scan();new MutationObserver(scan).observe(document.documentElement,{childList:true,subtree:true});
})(typeof globalThis!=='undefined'?globalThis:this);
