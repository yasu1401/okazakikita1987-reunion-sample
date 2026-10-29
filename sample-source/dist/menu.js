/* Shared mobile navigation for the sample and PHP preview. */
(function(){'use strict';
const button=document.getElementById('menuToggle'),nav=document.getElementById('mainNav');
if(!button||!nav)return;
function set(open){nav.classList.toggle('open',open);button.setAttribute('aria-expanded',String(open));button.querySelector('.menu-symbol').textContent=open?'×':'☰';button.querySelector('.menu-label').textContent=open?'閉じる':'メニュー';}
button.addEventListener('click',()=>set(button.getAttribute('aria-expanded')!=='true'));
nav.addEventListener('click',ev=>{if(ev.target.closest('a'))set(false);});
document.addEventListener('keydown',ev=>{if(ev.key==='Escape'&&button.getAttribute('aria-expanded')==='true'){set(false);button.focus();}});
window.ReunionMenu={set};
})();
