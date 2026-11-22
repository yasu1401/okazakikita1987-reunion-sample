/* The published sample stores check-in data in this browser only. */
(() => {
  const baseRoute = route;
  const baseMy = myPage;
  const baseShell = adminShell;
  let stopScanner = () => {};
  let search = '';
  let selected = null;

  function initialState(s) {
    const sampleStaff = s.people.find(p => p.email === 'sample2@example.test');
    return {
      staff: sampleStaff ? [sampleStaff.id] : [],
      passes: {}, attendance: {}, consent: {},
      start: '', end: '', clock: ''
    };
  }

  const read = s => s.checkin || initialState(s);
  const setup = s => s.checkin || (s.checkin = initialState(s));
  const allowed = (s = state) => owner() || (organizer() && read(s).staff.includes(current().id));

  function eventDay(s) {
    const match = s.settings.dateLabel.match(/(\d{4})年(\d{1,2})月(\d{1,2})日/);
    return match ? match[1] + '-' + match[2].padStart(2,'0') + '-' + match[3].padStart(2,'0') : '2028-10-29';
  }

  function windowFor(s) {
    const c = read(s), day = eventDay(s);
    return {start:c.start || day+'T00:00', end:c.end || day+'T23:59', clock:c.clock || s.settings.clock+'T12:00'};
  }
  const openNow = s => {
    const {start,end,clock} = windowFor(s);
    return start <= clock && clock <= end;
  };
  const showRosterAttendance = s => !!current() && windowFor(s).clock.slice(0,10) === eventDay(s);
  const stamp = value => value.replace('T',' ');
  const record = (s,p) => read(s).attendance[p.id];

  myPage = function () {
    const html = baseMy(), p = current();
    if (!p || !R.active(p)) return html;
    const checkedIn = record(state,p);
    const panel = `<section class="panel" id="checkin-ticket">
      <h2>当日の受付用QRコード</h2>
      <p>${checkedIn ? '受付済み（' + esc(stamp(checkedIn.at)) + '）' : '当日、受付でこのQRコードをご提示ください。画像の保存や印刷もできます。'}</p>
      <button type="button" class="btn" data-checkin-action="ticket">受付用QRコードを表示</button>
      ${p.public ? `<p><label><input type="checkbox" data-checkin-consent ${read(state).consent[p.id]?'checked':''}> ログインした方の名簿に、自分の「受付済み」を表示する</label></p>` : ''}
      <p class="mini-note">このサンプルの受付記録は、同じブラウザー内で確認できます。</p>
    </section>`;
    const box = document.createElement('div');
    box.innerHTML = html;
    const personPanel = box.querySelector('#my-person-heading')?.closest('.panel');
    if (personPanel) personPanel.insertAdjacentHTML('afterend',panel);
    else box.insertAdjacentHTML('beforeend',frame(panel));
    return box.innerHTML;
  };

  function showTicket() {
    const p = current();
    if (!p || !R.active(p)) return;
    const token = read(state).passes[p.id] || change(s => {
      return setup(s).passes[p.id] = 'KITA87:' + crypto.randomUUID();
    });
    const qr = qrcode(0,'M');
    qr.addData(token);
    qr.make();
    const size = qr.getModuleCount(), scale = 8, margin = 4;
    const canvas = document.createElement('canvas');
    canvas.width = canvas.height = (size+margin*2)*scale;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#fff';
    ctx.fillRect(0,0,canvas.width,canvas.height);
    ctx.fillStyle = '#000';
    for (let y=0;y<size;y++) for (let x=0;x<size;x++) {
      if (qr.isDark(y,x)) ctx.fillRect((x+margin)*scale,(y+margin)*scale,scale,scale);
    }
    const image = canvas.toDataURL();
    openModal(`<h2>受付用QRコード（見本）</h2><p>${esc(p.name)} さん ／ ${esc(R.classLabel(p.class))} ／ 申込番号 ${esc(p.number)}</p>
      <img class="checkin-qr" src="${image}" alt="本人の受付用QRコード">
      <p><a class="btn btn-outline" href="${image}" download="受付QR_${esc(p.number)}.png">QR画像を保存</a></p>`);
  }

  const baseRoster = Community.rosterLabels;
  Community.rosterLabels = function (s,p) {
    const labels = baseRoster(s,p);
    if (showRosterAttendance(s) && p.public && read(s).consent[p.id] && record(s,p)) return [...labels,'受付済み'];
    return labels;
  };

  adminShell = function (section,body) {
    const box = document.createElement('div');
    box.innerHTML = baseShell(section,body);
    const nav = box.querySelector('.admin-tabs');
    if (allowed()) nav?.insertAdjacentHTML('beforeend',`<a href="#/admin/checkin" class="${section==='checkin'?'active':''}">当日受付</a>`);
    if (owner()) nav?.insertAdjacentHTML('beforeend',`<a href="#/admin/checkin-settings" class="${section==='checkin-settings'?'active':''}">受付設定</a>`);
    return box.innerHTML;
  };

  function attendanceCounts(s) {
    const byClass = Array(11).fill(0);
    let total = 0;
    const attendance = read(s).attendance;
    for (const p of s.people) {
      if (!R.active(p) || !attendance[p.id]) continue;
      total++;
      byClass[p.class]++;
    }
    return {total,byClass};
  }

  function rosterTotals(path) {
    const match = path.match(/^\/classes(?:\/(10|[0-9]))?$/);
    if (!match) return;
    const cl = Number(match[1] || 0);
    const tabs = main.querySelector('.class-tabs');
    const all = tabs?.querySelector('a[href="#/classes/0"]');
    if (!all) return;

    const counts = R.counts(state);
    const checked = showRosterAttendance(state) ? attendanceCounts(state) : null;
    const allCount = checked && cl === 0
      ? `${counts.total}人中 受付済み ${checked.total}人`
      : `参加予定 ${counts.total}人`;
    all.innerHTML = `<strong>全クラス</strong><small> ${allCount}</small>`;
    const summary = document.createElement('div');
    summary.className = 'class-tabs roster-all-row';
    tabs.before(summary);
    summary.append(all);

    if (checked && cl > 0) {
      main.querySelector('#class-roster-heading')?.insertAdjacentHTML('afterend',
        `<p class="notice roster-selected-checkin">${esc(R.classLabel(cl))} ${counts.classes[cl-1]}人中 受付済み ${checked.byClass[cl]}人</p>`);
    }
  }

  function deskTotals() {
    const counts = R.counts(state);
    const checked = attendanceCounts(state);
    const rows = counts.classes.map((count,index)=>[
      esc(R.classLabel(index+1)), `${count}人中 ${checked.byClass[index+1]}人`
    ]);
    const unassigned = counts.total - counts.classes.reduce((sum,n)=>sum+n,0);
    if (unassigned > 0) rows.push(['クラス未確認',`${unassigned}人中 ${checked.byClass[0]}人`]);
    return `<section class="panel" id="checkin-totals">
      <h2>受付人数</h2>
      <p class="notice"><strong>全クラス ${counts.total}人中 受付済み ${checked.total}人</strong></p>
      ${table(['クラス','受付済み'],rows)}
    </section>`;
  }

  function normalized(s) {
    return String(s||'').normalize('NFKC').toLowerCase().replace(/\s/g,'')
      .replace(/[ぁ-ゖ]/g,c=>String.fromCharCode(c.charCodeAt(0)+96));
  }

  function results() {
    const q = normalized(search);
    const people = q ? state.people.filter(p => [
      p.name,p.display,p.kana,p.number,R.classLabel(p.class)
    ].some(v=>normalized(v).includes(q))) : [];
    return people.length ? `<p>${people.length}件${people.length>30?'（先頭30件を表示）':''}</p><ul class="checkin-results">${people.slice(0,30).map(p =>
      `<li><button type="button" class="btn btn-outline" data-checkin-action="select" data-person="${p.id}">${esc(p.number)} ／ ${esc(p.name)} ／ ${esc(R.classLabel(p.class))}${record(state,p)?' ／ 受付済み':''}</button></li>`
    ).join('')}</ul>` : q ? '<p>該当する申込がありません。</p>' : '';
  }

  function personCard() {
    const p = state.people.find(p=>p.id===selected?.id);
    if (!p) return '';
    const rec = record(state,p), due = R.due(state,p);
    return `<section class="panel" id="checkin-confirm" tabindex="-1"><h2>${esc(p.name)} さん</h2>
      <p>申込番号 ${esc(p.number)} ／ ${esc(R.classLabel(p.class))} ／ ${pill(p.status)}</p>
      <p>${due>0 ? '未入金残額 ' + yen(due) + '。必要に応じて当日対応を確認してください。' : '会費の未入金残額なし'}</p>
      ${rec ? `<p class="notice"><strong>受付済み</strong>（${esc(stamp(rec.at))} ／ ${esc(rec.method)}）</p>
      <form id="checkin-undo">${field('reason','取り消す理由')}<button class="btn btn-outline">受付を取り消す</button></form>`
      : R.active(p) && openNow(state) ? '<button type="button" class="btn" data-checkin-action="confirm">受付する</button>'
      : '<p>参加状態または受付時間を確認してください。</p>'}</section>`;
  }

  function desk() {
    const settings = windowFor(state);
    return adminShell('checkin',`<section class="panel"><h2>当日受付</h2>
      <p>受付日時（見本・日本時間） ${esc(stamp(settings.clock))} ／ ${openNow(state)?'受付中':'受付時間外'}</p>
      <p class="mini-note">同じブラウザーで参加者のQR画像を保存し、幹事に切り替えて読み取れます。別の端末には受付状況は共有されません。</p>
      <div class="actions"><button type="button" class="btn" data-checkin-action="camera">カメラでQRを読む</button></div>
      <label class="field">保存したQR画像を読み取る<input type="file" id="checkin-image" accept="image/*"></label>
      <video id="checkin-video" playsinline muted hidden style="width:100%;max-width:28rem"></video>
      <button type="button" id="checkin-camera-stop" class="btn btn-outline" data-checkin-action="stop" hidden>カメラを停止</button>
      <p id="checkin-message" role="status"></p></section>
      <section class="panel"><h2>名簿から探す</h2>
      <form id="checkin-search">${field('q','氏名・ふりがな・クラス・申込番号',search)}<button class="btn btn-outline">検索</button></form>
      <div id="checkin-results" aria-live="polite">${results()}</div></section>
      <div id="checkin-selection">${personCard()}</div>
      ${deskTotals()}
      <section class="panel"><h2>受付操作履歴</h2>${historyList()}</section>`);
  }

  function historyList() {
    const rows = state.logs.filter(l=>String(l.entity).startsWith('checkin:')).slice().reverse();
    if (!rows.length) return '<p>受付操作はまだありません。</p>';
    return '<ul>'+ rows.slice(0,30).map(l=>{
      const p = state.people.find(p=>p.id===Number(l.entity.split(':')[1]));
      return `<li>${esc(p?.number)} ${esc(p?.name)} ／ ${esc(l.action)} ／ ${esc(l.after?.at ? stamp(l.after.at) : date(l.at))} ／ ${esc(l.reason)} ／ ${esc(l.actor)}</li>`;
    }).join('')+'</ul>';
  }

  function settingsPage() {
    const v = windowFor(state);
    const staff = state.people.filter(p=>p.role==='organizer');
    return adminShell('checkin-settings',`<section class="panel"><h2>受付時間と担当者</h2>
      <p>日時は日本時間。名簿の受付状況は、見本の現在日時が開催日と同じ日のみ、ログインした方に表示します。</p>
      <form id="checkin-settings-form">
      ${field('start','受付開始',v.start,'datetime-local')}
      ${field('end','受付終了',v.end,'datetime-local')}
      ${field('clock','見本の現在日時',v.clock,'datetime-local')}
      <p><button type="button" class="btn btn-outline" data-checkin-action="event-clock">開催当日の日時を入力</button></p>
      <fieldset><legend>受付を許可する管理担当</legend>
      ${staff.map(p=>`<p><label><input type="checkbox" name="staff" value="${p.id}" ${read(state).staff.includes(p.id)?'checked':''}> ${esc(p.name)}（${esc(p.number)}）</label></p>`).join('') || '<p>管理担当はまだいません。</p>'}
      </fieldset><p>管理者は受付操作もできます。</p><button class="btn">受付設定を保存</button>
      </form></section>`);
  }

  route = function () {
    stopScanner();
    const path = (location.hash.slice(1)||'/').split('?')[0];
    baseRoute();
    if (current() && organizer()) {
      if (path==='/admin/checkin') main.innerHTML = allowed() ? desk() : frame(title('ACCESS','受付権限が必要です。'));
      if (path==='/admin/checkin-settings') main.innerHTML = owner() ? settingsPage() : frame(title('ACCESS','管理者だけが変更できます。'));
    }
    if (path!=='/admin/checkin' || !allowed()) { selected = null; search = ''; }
    rosterTotals(path);
  };

  function message(value) {
    const box = document.querySelector('#checkin-message');
    if (box) box.textContent = value;
  }

  function pick(id,method) {
    selected = {id,method};
    const target = document.querySelector('#checkin-selection');
    if (!target) return;
    target.innerHTML = personCard();
    const card = target.firstElementChild;
    if (card) {
      card.scrollIntoView({block:'start'});
      card.focus({preventScroll:true});
    }
  }

  function decode(source,width,height) {
    if (!width || !height) return null;
    const canvas = document.createElement('canvas');
    const scale = Math.min(1,1280/Math.max(width,height));
    canvas.width = Math.max(1,Math.round(width*scale));
    canvas.height = Math.max(1,Math.round(height*scale));
    const ctx = canvas.getContext('2d',{willReadFrequently:true});
    ctx.drawImage(source,0,0,canvas.width,canvas.height);
    const data = ctx.getImageData(0,0,canvas.width,canvas.height);
    return jsQR(data.data,data.width,data.height,{inversionAttempts:'dontInvert'})?.data;
  }

  function scanned(code) {
    if (!allowed() || !document.querySelector('#checkin-video')) return;
    const selection = document.querySelector('#checkin-selection');
    selected = null;
    if (selection) selection.innerHTML = '';
    const pass = code && Object.entries(read(state).passes).find(([,token])=>token===code);
    if (!pass) return message(code ? 'このブラウザーの受付用QRコードと一致しません。名簿からも検索できます。' : 'QRコードを読み取れませんでした。画像を変えるか名簿から検索してください。');
    message('QRコードを読み取りました。氏名とクラスを確認してください。');
    pick(Number(pass[0]),'QR');
  }

  async function camera() {
    stopScanner();
    const video = document.querySelector('#checkin-video');
    const stop = document.querySelector('#checkin-camera-stop');
    let stream, timeout, stopped = false;
    stopScanner = () => {
      stopped = true;
      clearTimeout(timeout);
      stream?.getTracks().forEach(track=>track.stop());
      video.srcObject = null;
      video.hidden = stop.hidden = true;
    };
    stop.hidden = false;
    message('カメラの使用を許可し、受付用QRコードを映してください。');
    try {
      stream = await navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:'environment'}},audio:false});
      if (stopped || !video.isConnected) { stream.getTracks().forEach(t=>t.stop()); return; }
      video.srcObject = stream;
      video.hidden = false;
      await video.play();
      function tick() {
        if (stopped) return;
        try {
          const code = decode(video,video.videoWidth,video.videoHeight);
          if (code) { stopScanner(); scanned(code); return; }
          timeout = setTimeout(tick,250);
        } catch {
          stopScanner();
          message('カメラで読み取れませんでした。QR画像か名簿をご利用ください。');
        }
      }
      tick();
    } catch {
      if (stopped) return;
      stopScanner();
      message('カメラを使用できません。QR画像か名簿をご利用ください。');
    }
  }

  function saveAttendance(undo,reason='') {
    if (!allowed() || !selected) return;
    change(s=>{
      const p = s.people.find(p=>p.id===selected.id), c = setup(s);
      if (!p) throw new Error('申込が見つかりません。');
      const before = c.attendance[p.id] || null;
      if (undo) {
        if (!before || !reason.trim()) throw new Error('取り消す理由を入力してください。');
        delete c.attendance[p.id];
      } else {
        if (!R.active(p) || !openNow(s)) throw new Error('参加状態または受付時間を確認してください。');
        if (before) throw new Error('すでに受付済みです。');
        c.attendance[p.id] = {at:windowFor(s).clock,method:selected.method};
      }
      R.audit(s,current().name,undo?'受付取消':'当日受付','checkin:'+p.id,before,c.attendance[p.id]||null,undo?reason.trim():selected.method);
    });
    route();
    pick(selected.id,selected.method);
    toast(undo?'受付を取り消しました。':'受付済みにしました。');
  }

  document.addEventListener('click',ev=>{
    const button = ev.target.closest('[data-checkin-action]');
    if (!button) return;
    try {
      const action = button.dataset.checkinAction;
      if (action==='ticket') return showTicket();
      if (action==='event-clock' && owner()) {
        const input = button.form?.elements.namedItem('clock');
        if (input) {
          input.value = eventDay(state)+'T12:00';
          input.focus();
        }
        return;
      }
      if (!allowed()) return;
      if (action==='select') pick(Number(button.dataset.person),'手動');
      if (action==='confirm') saveAttendance(false);
      if (action==='stop') { stopScanner(); message('カメラを停止しました。'); }
      if (action==='camera') camera();
    } catch (e) { toast(e.message); }
  });

  document.addEventListener('submit',ev=>{
    const f = ev.target;
    if (!['checkin-search','checkin-undo','checkin-settings-form'].includes(f.id)) return;
    ev.preventDefault();
    const d = new FormData(f);
    try {
      if (f.id==='checkin-search' && allowed()) {
        search = String(d.get('q') || '').trim();
        selected = null;
        document.querySelector('#checkin-selection').innerHTML = '';
        document.querySelector('#checkin-results').innerHTML = results();
      }
      if (f.id==='checkin-undo' && allowed()) saveAttendance(true,String(d.get('reason')||''));
      if (f.id==='checkin-settings-form' && owner()) {
        const start = String(d.get('start')), end = String(d.get('end')), clock = String(d.get('clock'));
        const valid = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/;
        if (![start,end,clock].every(v=>valid.test(v)) || start>end) throw new Error('有効な日時を入力し、受付開始を受付終了以前にしてください。');
        change(s=>{
          const c = setup(s);
          Object.assign(c,{start,end,clock,staff:d.getAll('staff').map(Number).filter(id=>s.people.some(p=>p.id===id&&p.role==='organizer'))});
        });
        route();
        toast('受付設定を保存しました。');
      }
    } catch(e) { toast(e.message); }
  });

  document.addEventListener('change',async ev=>{
    const field = ev.target;
    if (field.matches('[data-checkin-consent]') && current()?.public && R.active(current())) {
      try { change(s=>{setup(s).consent[current().id] = field.checked;}); }
      catch(e) { field.checked = !field.checked; toast(e.message); }
    }
    if (field.id!=='checkin-image' || !allowed() || !field.files?.[0]) return;
    stopScanner();
    const file = field.files[0];
    field.value = '';
    if (file.size>15*1024*1024) return message('15MB以下のQR画像を選んでください。');
    const url = URL.createObjectURL(file);
    try {
      const img = new Image();
      img.src = url;
      await img.decode();
      if (field.isConnected) scanned(decode(img,img.naturalWidth,img.naturalHeight));
    } catch { if (field.isConnected) message('画像を開けませんでした。'); }
    finally { URL.revokeObjectURL(url); }
  });

  window.addEventListener('pagehide',()=>stopScanner());
})();
