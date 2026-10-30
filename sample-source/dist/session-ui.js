/* The same visible logout location for participants, organizers and administrators. */
const sessionBaseRoute=route;
const sessionBar=document.createElement('div');sessionBar.className='session-bar';sessionBar.setAttribute('aria-label','ログイン中のアカウント');sessionBar.hidden=true;
document.querySelector('header').insertAdjacentElement('afterend',sessionBar);
function renderSessionBar(){const p=current();sessionBar.hidden=!p;if(!p){sessionBar.replaceChildren();return;}const role={owner:'管理者',organizer:'幹事',participant:'参加者'}[p.role]||'参加者';sessionBar.innerHTML='<div class="session-content"><div class="session-account"><small>ログイン中</small><span><strong>'+esc(p.name)+'</strong><span class="session-role">'+role+'</span></span></div><button type="button" class="btn btn-outline session-logout" data-feature="logout">ログアウト</button></div>';}
route=function(){renderSessionBar();sessionBaseRoute();main.querySelectorAll('[data-feature="logout"]').forEach(b=>b.remove());};
window.removeEventListener('hashchange',sessionBaseRoute);window.addEventListener('hashchange',route);route();
