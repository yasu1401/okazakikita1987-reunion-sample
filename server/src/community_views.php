<?php
declare(strict_types=1);
const INQUIRY_STATUS=['new'=>'未対応','in_progress'=>'対応中','closed'=>'対応済み'];
const OUTBOX_STATUS=['development'=>'開発用・未送信','queued'=>'送信待ち','sending'=>'送信処理中・再送前に要確認','accepted'=>'メールサーバー受付済・到達は未確認','failed'=>'送信失敗'];
function inquiryClassLabel(mixed $value):string {return $value===null?'未登録（以前の問い合わせ）':((int)$value===0?'クラスを忘れた':'3年'.(int)$value.'組');}
function contactView(?array $u):string {
    $_SESSION['contact_nonce']??=bin2hex(random_bytes(16));
    $registration=$u?registrationFor((int)$u['id']):null;$selected=$registration['class_no']??'';
    $classSelect='<label class="field">卒業時のクラス<select name="class" required><option value="">選択してください</option>';
    for($cl=1;$cl<=10;$cl++)$classSelect.='<option value="'.$cl.'"'.((string)$selected===(string)$cl?' selected':'').'>3年'.$cl.'組</option>';
    $classSelect.='<option value="0">クラスを忘れた</option></select></label>';
    $content='<div class="grid-2">'.input('name','お名前',$u['name']??'').input('email','返信用メールアドレス',$u['email']??'','email').'</div>'.$classSelect.input('subject','件名').'<label class="field">お問い合わせ内容<textarea name="body" maxlength="10000" rows="6" required></textarea></label><label class="honeypot" aria-hidden="true">空欄にしてください<input name="website" tabindex="-1" autocomplete="off"></label>'.hidden('submission',$_SESSION['contact_nonce']).'<p class="mini-note">ログインしていなくても送信できます。内容は一般公開せず、幹事が確認します。現在は開発用のため実際の個人情報を入力しないでください。</p>'.button('問い合わせを受け付ける');
    return '<section class="section" id="contact"><div class="section-head"><div><div class="eyebrow">CONTACT US</div><h2>気になること、お気軽に。</h2><p>参加のこと、申込のこと。幹事へお寄せください。</p></div></div><div class="panel">'.form('contact',$content).'</div></section>';
}
function rosterView():string {
    $cl=(int)($_GET['class']??0);if($cl<0||$cl>10)$cl=0;
    $rows=all("SELECT r.class_no,r.data_json,u.role,EXISTS(SELECT 1 FROM class_contacts c WHERE c.user_id=r.user_id AND c.class_no=r.class_no) AS is_contact FROM registrations r JOIN users u ON u.id=r.user_id WHERE r.status='active' ORDER BY r.class_no,r.id");$counts=array_fill(0,11,0);foreach($rows as $r)$counts[(int)$r['class_no']]++;
    $html='<div class="page-title"><div class="eyebrow">CLASSMATES</div><h1>'.($cl?'3年'.$cl.'組の、懐かしい顔ぶれ。':'みんなの参加予定').'</h1><p>現在の氏名はすべて架空の見本です。掲載に同意された表示名を掲載します。住所・電話・メールは表示しません。</p></div><div class="panel"><div class="section-head"><h2>'.($cl?'3年'.$cl.'組':'全クラス').'の参加予定 <span class="amount">'.($cl?$counts[$cl]:count($rows)).'</span> 人</h2><p>全体の参加予定：'.count($rows).'人</p></div><div class="class-tabs"><a href="?page=classes" class="'.(!$cl?'active':'').'">全クラス</a>';
    for($i=1;$i<=10;$i++)$html.='<a href="?page=classes&amp;class='.$i.'" class="'.($cl===$i?'active':'').'">3年'.$i.'組 <small>'.$counts[$i].'人</small></a>';
    $html.='</div><div class="roster">';$shown=0;
    foreach($rows as $r){$d=obj($r['data_json']);if(!$d['public']||($cl&&(int)$r['class_no']!==$cl))continue;$shown++;$labels=[];if(in_array($r['role'],['owner','organizer'],true))$labels[]='幹事';if($r['is_contact'])$labels[]='クラス幹事';$badges='<div class="name-roles">'.implode('',array_map(fn($label)=>'<small class="name-role">'.h($label).'</small>',$labels)).'</div>';$html.='<div class="name-card"><small>'.h(registrationClassLabel((int)$r['class_no'])).'</small><strong>'.h($d['display']).'</strong>'.$badges.'</div>';}
    if(!$shown)$html.='<p class="empty">掲載中の参加予定者はいません。</p>';
    return $html.'</div><p class="section-note">人数には名前を掲載しない方も含みます。欠席申請中・取消済みの方は参加予定人数から除いています。</p></div>';
}
function contactsForPerson(array $p):string {
    $cl=(int)$p['class_no'];if($cl===0)return '<section class="panel"><h2>卒業時のクラスを確認中です</h2><p>幹事が確認後に修正します。心当たりがあれば<a href="?page=home#contact">問い合わせフォーム</a>でお知らせください。</p></section>';$contacts=classContacts($cl);$names=$contacts?implode('',array_map(fn($c)=>'<div class="name-card"><small>3年'.$cl.'組 クラス幹事</small><strong>'.h($c['name']).'</strong></div>',$contacts)):'<p>クラス幹事は決まり次第ご案内します。</p>';
    return '<section class="panel"><div class="eyebrow">YOUR CLASS ORGANIZERS</div><h2>3年'.$cl.'組のクラス幹事</h2><div class="roster">'.$names.'</div><p>ご連絡は<a href="?page=home#contact">問い合わせフォーム</a>をご利用ください。</p></section>';
}
function contactsAdminView():string {
    $cl=(int)($_GET['class']??1);$cl=max(1,min(10,$cl));$html='<div class="page-title"><div class="eyebrow">CLASS ORGANIZERS</div><h1>クラス幹事を、ご案内。</h1><p>管理者が各クラス最大3名を指定します。表示の指定だけでは管理権限は増えません。</p></div><div class="class-tabs">';
    for($i=1;$i<=10;$i++)$html.='<a href="?page=admin_contacts&amp;class='.$i.'" class="'.($cl===$i?'active':'').'">3年'.$i.'組</a>';
    $html.='</div><div class="panel"><h2>3年'.$cl.'組</h2>';
    foreach(all('SELECT c.*,u.name FROM class_contacts c JOIN users u ON u.id=c.user_id WHERE c.class_no=? ORDER BY c.position_no,c.id',[$cl]) as $c)$html.='<div class="contact-assignment"><strong>'.h($c['name']).'</strong> ／ 表示順 '.$c['position_no'].form('contact_remove',hidden('id',$c['id']).reasons().button('表示指定を解除')).'</div>';
    $select='<label class="field">同じクラスの参加予定者<select name="user_id" required><option value="">選択してください</option>';
    foreach(all("SELECT u.id,u.name FROM users u JOIN registrations r ON r.user_id=u.id WHERE r.class_no=? AND r.status='active' ORDER BY u.id",[$cl]) as $r)$select.='<option value="'.$r['id'].'">'.h($r['name']).'</option>';
    return $html.'<h3>クラス幹事を追加</h3>'.form('contact_add',hidden('class',$cl).$select.'</select></label>'.input('position','表示順（1〜3）',1,'number').reasons().button('クラス幹事として表示')).'</div>';
}
function inquiryAdminView():string {
    $id=(int)($_GET['id']??0);$filter=(string)($_GET['status']??'');$rows=all('SELECT i.*,u.name AS assigned_name FROM inquiries i LEFT JOIN users u ON u.id=i.assignee_id ORDER BY i.id DESC');$counts=array_fill_keys(array_keys(INQUIRY_STATUS),0);foreach($rows as $r)$counts[$r['status']]++;
    $html='<div class="page-title"><div class="eyebrow">INQUIRIES</div><h1>お問い合わせに、お返事を。</h1></div>'.grid([['未対応',$counts['new'].'件'],['対応中',$counts['in_progress'].'件'],['対応済み',$counts['closed'].'件'],['受付累計',count($rows).'件']]);
    if(!$id){$html.='<div class="class-tabs"><a href="?page=admin_inquiries">すべて</a>';foreach(INQUIRY_STATUS as $s=>$name)$html.='<a href="?page=admin_inquiries&amp;status='.$s.'">'.$name.'</a>';$html.='</div>';return $html.tabler(['受付番号・日時','お名前・件名','返信先','対応状況','担当'],array_map(fn($i)=>['<a href="?page=admin_inquiries&amp;id='.$i['id'].'">Q87-'.str_pad((string)$i['id'],5,'0',STR_PAD_LEFT).'</a><br>'.h($i['created']),h($i['name']).'<br>'.h(inquiryClassLabel($i['class_no'])).'<br>'.h($i['subject']),h($i['email']),h(INQUIRY_STATUS[$i['status']]),h($i['assigned_name']??'未割当')],array_values(array_filter($rows,fn($i)=>!isset(INQUIRY_STATUS[$filter])||$i['status']===$filter))));}
    $i=one('SELECT * FROM inquiries WHERE id=?',[$id]);requirev((bool)$i,'問い合わせがありません。');
    $html.='<a class="text-link" href="?page=admin_inquiries">← 一覧へ</a><section class="panel"><h2>'.h($i['subject']).'</h2><p>'.h($i['name']).' さん ／ '.h(inquiryClassLabel($i['class_no'])).' ／ 返信先：'.h($i['email']).'</p><pre class="message-body">'.h($i['body']).'</pre><p>受付：'.h($i['created']).' ／ '.($i['user_id']?'登録者からの問い合わせ':'ログイン前の問い合わせ').'</p></section>';
    $status='<label class="field">対応状況<select name="status">';foreach(INQUIRY_STATUS as $s=>$name)$status.='<option value="'.$s.'"'.($s===$i['status']?' selected':'').'>'.$name.'</option>';$status.='</select></label>';
    $assignee='<label class="field">担当幹事<select name="assignee"><option value="0">未割当</option>';foreach(all("SELECT id,name FROM users WHERE role IN ('organizer','owner') ORDER BY id") as $a)$assignee.='<option value="'.$a['id'].'"'.((int)$i['assignee_id']===(int)$a['id']?' selected':'').'>'.h($a['name']).'</option>';
    $html.='<section class="panel"><h3>担当・状況</h3>'.form('inquiry_status',hidden('id',$id).$status.$assignee.'</select></label>'.reasons().button('対応状況を記録')).'</section>';
    $html.='<section class="panel"><h3>返信履歴</h3>';foreach(all('SELECT r.*,u.name,m.status AS mail_status FROM inquiry_replies r JOIN users u ON u.id=r.actor_id LEFT JOIN mail_outbox m ON m.reply_id=r.id WHERE r.inquiry_id=? ORDER BY r.id',[$id]) as $r)$html.='<article class="history-item"><small>'.h($r['created']).' ／ '.h($r['name']).' ／ '.h(OUTBOX_STATUS[$r['mail_status']]??$r['mail_status']).'</small><pre class="message-body">'.h($r['body']).'</pre></article>';
    $_SESSION['reply_nonce']??=bin2hex(random_bytes(16));global $config;$willSend=$config['mode']==='live'&&!empty($config['mail_enabled']);
    return $html.'<h3>返信を作成</h3><p>宛先は問い合わせに入力された <strong>'.h($i['email']).'</strong> です。</p>'.form('inquiry_reply',hidden('id',$id).hidden('reply_key',$_SESSION['reply_nonce']).'<label class="field">回答内容<textarea name="body" maxlength="10000" rows="7" required></textarea></label><p class="notice">'.($willSend?'保存後に送信キューへ登録し、設定済みの送信処理が実行します。':'開発モードのためメールは実送信しません。宛先・本文は「開発メール」で確認できます。').'</p>'.button($willSend?'返信を送信待ちに登録':'返信を保存（開発メールへ）')).'</section>';
}
function albumSelect(int $selected=1):string {
    $s='<label class="field">アルバムの種類<select name="album_id" required>';foreach(all('SELECT * FROM albums ORDER BY position_no,id') as $a)$s.='<option value="'.$a['id'].'"'.((int)$a['id']===$selected?' selected':'').'>'.h($a['name'].(!$a['visible']?'（非表示）':'')).'</option>';return $s.'</select></label>';
}
function galleryView(array $u):string {
 $aid=(int)($_GET['album']??0);$albums=all('SELECT * FROM albums WHERE visible=1 ORDER BY position_no,id');$html='<div class="page-title"><div class="eyebrow">PHOTO ALBUM</div><h1>みんなの写真を、ここに。</h1><p>写真をタップして大きく表示。左右のボタンやスワイプで続きを楽しめます。</p></div><div class="button-row"><a class="btn" href="?page=my#photo-post">写真を投稿する ↗</a>'.($aid?'<a class="btn btn-outline" href="?page=gallery">アルバム一覧へ</a>':'').'</div>';
 if(!$aid)$html.='<div class="album-cover-grid">';$found=false;
 foreach($albums as $a){if($aid&&(int)$a['id']!==$aid)continue;$found=true;$photos=array_values(array_filter(all("SELECT * FROM private_files WHERE kind='photo' AND published=1 AND deleted=0 AND album_id=? ORDER BY sort_order,created DESC,id DESC",[$a['id']]),fn($f)=>canFile($u,$f)));$count=count($photos);
  if(!$aid){$html.='<a class="album-cover" href="?page=gallery&amp;album='.$a['id'].'">'.($count?'<img src="?page=file&amp;inline=1&amp;id='.$photos[0]['id'].'" alt="'.h($a['name']).'の表紙" loading="lazy">':'<div class="album-cover-empty">写真はこれから</div>').'<div><h2>'.h($a['name']).'</h2><span>'.$count.'枚</span><p>'.h($a['description']).'</p></div></a>';continue;}
  $html.='<section class="section"><div class="section-head"><div><h2>'.h($a['name']).'</h2><p>'.h($a['description']).'</p></div><strong>'.$count.'枚</strong></div><div class="photo-mosaic">';
  foreach($photos as $f){$title=$f['photo_title']?:$f['original_name'];$src='?page=file&amp;inline=1&amp;id='.$f['id'];$download='?page=file&amp;id='.$f['id'];$caption=($f['caption']??'').($f['author_label']?' ／ 投稿：'.$f['author_label']:'');$html.='<article class="photo-tile"><a href="'.$src.'" data-photo-view data-photo-group="'.$a['id'].'" data-title="'.h($title).'" data-caption="'.h($caption).'" data-download="'.$download.'" aria-label="'.h($title).'を大きく表示"><img src="'.$src.'" alt="'.h($title).'" loading="lazy"></a><h3>'.h($title).'</h3>'.photoCaptionView($f).'<a class="text-link" href="'.$download.'">写真をダウンロード</a></article>';}
  $html.='</div>'.(!$count?'<p class="empty">写真は公開準備中です。</p>':'').'</section>';
 }
 return $html.(!$aid?'</div>':'').(!$found?'<p class="empty">公開中のアルバムはありません。</p>':'');
}
function photosAdminView():string {
    $html='<div class="page-title"><div class="eyebrow">ALBUM SETTINGS</div><h1>アルバムに、好きな名前を。</h1><p>アルバム数や名前は固定ではありません。ここで自由に追加・改名できます。アルバムを非表示にすると参加者は画像URLからも取得できません。</p></div><section class="panel"><h2>アルバムを追加</h2>'.form('album_save',input('name','アルバム名').input('description','説明','','text',false).input('position','表示順',1,'number').'<label class="field">参加者への表示<select name="visible"><option value="yes">表示する</option><option value="no">非表示</option></select></label>'.reasons().button('アルバムを追加')).'</section>';
    foreach(all('SELECT * FROM albums ORDER BY position_no,id') as $a)$html.='<details class="panel" open><summary>'.h($a['name']).' ／ '.($a['visible']?'表示':'非表示').'</summary>'.form('album_save',hidden('id',$a['id']).input('name','アルバム名',$a['name']).input('description','説明',$a['description'],'text',false).input('position','表示順',$a['position_no'],'number').'<label class="field">参加者への表示<select name="visible"><option value="yes">表示する</option><option value="no"'.(!$a['visible']?' selected':'').'>非表示</option></select></label>'.reasons().button('アルバム設定を保存')).'</details>';
    $html.='<section class="panel"><h2>写真を非公開で登録</h2>'.form('photo',albumSelect().'<label class="file-drop">JPEG/PNG（5MB・2000万画素以下）<input type="file" name="photo" accept="image/jpeg,image/png" required></label>'.button('選択したアルバムへ登録'),true).'</section>';
    foreach(all("SELECT * FROM private_files WHERE kind='photo' AND deleted=0 ORDER BY id DESC") as $f)$html.='<section class="panel"><a href="?page=file&amp;id='.$f['id'].'">'.h($f['original_name']).'</a> ／ '.($f['published']?'写真は公開':'写真は非公開').form('photo_move',hidden('id',$f['id']).albumSelect((int)$f['album_id']).reasons().button('アルバムを変更')).form('publish',hidden('id',$f['id']).reasons().button($f['published']?'写真を非公開にする':'写真を公開する')).'</section>';
    return $html;
}
function outboxView():string {
    global $config;$html='<div class="page-title"><div class="eyebrow">DEVELOPMENT MAIL</div><h1>返信メールの記録</h1><p>送信元：'.h($config['mail_from']??'未設定').'。開発モードでは外部へ送信しません。</p></div>';
    foreach(all('SELECT m.*,r.inquiry_id FROM mail_outbox m JOIN inquiry_replies r ON r.id=m.reply_id ORDER BY m.id DESC') as $m)$html.='<section class="panel"><h2>'.h($m['subject']).'</h2><p>宛先：'.h($m['to_email']).'<br>状態：'.h(OUTBOX_STATUS[$m['status']]??$m['status']).'<br>日時：'.h($m['created']).'</p><pre class="message-body">'.h($m['body']).'</pre><p>'.h($m['error_text']).'</p><a href="?page=admin_inquiries&amp;id='.$m['inquiry_id'].'">問い合わせへ戻る</a></section>';
    return $html;
}
