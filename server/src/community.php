<?php
declare(strict_types=1);

function emailValue(string $v):string {
    $v=strtolower(trim($v));
    requirev(strlen($v)<=254&&!str_contains($v,"\r")&&!str_contains($v,"\n")&&filter_var($v,FILTER_VALIDATE_EMAIL)!==false,'返信用メールアドレスを確認してください。');
    return $v;
}
function textValue(array $d,string $key,int $max):string {
    $value=trim((string)($d[$key]??''));
    requirev($value!==''&&strlen($value)<=$max,'必須項目を入力し、長すぎる内容を短くしてください。');
    return $value;
}
function submitInquiry(?array $u,array $d,string $bucket,string $submission):int {
    global $db;
    requirev(($d['website']??'')==='','問い合わせ内容を確認してください。');
    requirev((bool)preg_match('/^[a-f0-9]{32}$/D',$submission),'送信画面を再読み込みしてください。');
    $key=hash('sha256',$submission);
    $old=one('SELECT id FROM inquiries WHERE submission_key=? AND request_bucket=?',[$key,$bucket]);
    if($old)return (int)$old['id'];
    $since=date('Y-m-d H:i:s',time()-3600);
    requirev((int)one('SELECT COUNT(*) n FROM inquiries WHERE request_bucket=? AND created>?',[$bucket,$since])['n']<10,'送信回数が多いため、時間をおいてお試しください。');
    $class=(string)($d['class']??'');requirev((bool)preg_match('/^(?:[0-9]|10)$/D',$class),'クラスを選択してください。');
    $name=textValue($d,'name',600);$email=emailValue((string)($d['email']??''));$subject=textValue($d,'subject',600);$body=textValue($d,'body',30000);
    // Database VARCHAR bounds are characters; byte limits above also cap multibyte inputs.
    requirev(mb_strlen($name)<=200&&mb_strlen($subject)<=200,'氏名・件名は200文字以内です。');
    q('INSERT INTO inquiries (user_id,name,email,subject,body,status,assignee_id,submission_key,request_bucket,created,class_no) VALUES (?,?,?,?,?,?,?,?,?,?,?)',[$u['id']??null,$name,$email,$subject,$body,'new',null,$key,$bucket,nowv(),(int)$class]);
    $id=(int)$db->lastInsertId();logv(isset($u['id'])?(int)$u['id']:null,'問い合わせ受付','inquiry:'.$id,null,['status'=>'new'],'公開フォームから受付');return $id;
}
function adminInquiry(array $u):void {requirev(in_array($u['role'],['organizer','owner'],true),'幹事権限が必要です。');}
function updateInquiry(array $u,array $d):void {
    adminInquiry($u);$id=(int)($d['id']??0);$i=one('SELECT * FROM inquiries WHERE id=?',[$id]);requirev((bool)$i,'問い合わせがありません。');
    $status=(string)($d['status']??'');requirev(in_array($status,['new','in_progress','closed'],true),'対応状況を選択してください。');
    $assignee=(int)($d['assignee']??0);if($assignee){$a=publicUser($assignee);requirev($a&&in_array($a['role'],['organizer','owner'],true),'担当者は幹事から選択してください。');}
    q('UPDATE inquiries SET status=?,assignee_id=? WHERE id=?',[$status,$assignee?:null,$id]);
    logv((int)$u['id'],'問い合わせ対応状況変更','inquiry:'.$id,['status'=>$i['status'],'assignee'=>$i['assignee_id']],['status'=>$status,'assignee'=>$assignee?:null],reasonv($d));
}
function replyInquiry(array $u,array $d):int {
    global $db,$config;adminInquiry($u);$id=(int)($d['id']??0);$i=one('SELECT * FROM inquiries WHERE id=?',[$id]);requirev((bool)$i,'問い合わせがありません。');
    $body=textValue($d,'body',30000);$nonce=(string)($d['reply_key']??'');requirev((bool)preg_match('/^[a-f0-9]{32}$/D',$nonce),'返信画面を再読み込みしてください。');$key=hash('sha256',$nonce);
    $old=one('SELECT id,inquiry_id,actor_id FROM inquiry_replies WHERE reply_key=?',[$key]);if($old){requirev((int)$old['inquiry_id']===$id&&(int)$old['actor_id']===(int)$u['id'],'返信画面を再読み込みしてください。');return (int)$old['id'];}
    q('INSERT INTO inquiry_replies (inquiry_id,actor_id,body,reply_key,created) VALUES (?,?,?,?,?)',[$id,$u['id'],$body,$key,nowv()]);$rid=(int)$db->lastInsertId();
    $status=$config['mode']==='live'&&!empty($config['mail_enabled'])?'queued':'development';
    // Recipient always comes from the submitted inquiry, never from a hidden admin form field.
    q('INSERT INTO mail_outbox (reply_id,to_email,subject,body,status,error_text,created,updated) VALUES (?,?,?,?,?,?,?,?)',[$rid,$i['email'],'【岡崎北1987同窓会】お問い合わせへの回答 #'.$id,$body,$status,'',nowv(),nowv()]);
    q("UPDATE inquiries SET status='in_progress',assignee_id=? WHERE id=?",[$u['id'],$id]);
    logv((int)$u['id'],'問い合わせ返信を記録','inquiry:'.$id,null,['reply_id'=>$rid,'outbox_status'=>$status],'問い合わせに入力された宛先への返信');return $rid;
}
function setClassContact(array $u,array $d):void {
    requirev($u['role']==='owner','クラス幹事の指定は代表幹事が行います。');$cl=(int)($d['class']??0);$uid=(int)($d['user_id']??0);$position=(int)($d['position']??1);
    requirev($cl>=1&&$cl<=10&&$position>=1&&$position<=3,'クラスと表示順（1〜3）を確認してください。');
    $r=registrationFor($uid);requirev($r&&(int)$r['class_no']===$cl&&$r['status']==='active','同じクラスの参加予定者から選んでください。');
    requirev(!one('SELECT id FROM class_contacts WHERE class_no=? AND user_id=?',[$cl,$uid]),'すでに指定されています。');
    requirev((int)one('SELECT COUNT(*) n FROM class_contacts WHERE class_no=?',[$cl])['n']<3,'1クラスの表示は3名までです。先に指定を解除してください。');
    q('INSERT INTO class_contacts (user_id,class_no,position_no) VALUES (?,?,?)',[$uid,$cl,$position]);
    logv((int)$u['id'],'クラス幹事表示を指定','class:'.$cl,null,['user_id'=>$uid,'position'=>$position],reasonv($d));
}
function removeClassContact(array $u,array $d):void {
    requirev($u['role']==='owner','代表幹事だけが解除できます。');$c=one('SELECT * FROM class_contacts WHERE id=?',[(int)($d['id']??0)]);requirev((bool)$c,'指定がありません。');
    q('DELETE FROM class_contacts WHERE id=?',[$c['id']]);logv((int)$u['id'],'クラス幹事表示を解除','class:'.$c['class_no'],$c,null,reasonv($d));
}
function classContacts(int $cl):array {
    return all("SELECT c.*,u.name FROM class_contacts c JOIN users u ON u.id=c.user_id JOIN registrations r ON r.user_id=u.id WHERE c.class_no=? AND r.class_no=c.class_no AND r.status='active' ORDER BY c.position_no,c.id",[$cl]);
}
function saveAlbum(array $u,array $d):int {
    global $db;adminInquiry($u);$id=(int)($d['id']??0);$name=textValue($d,'name',600);$description=trim((string)($d['description']??''));requirev(mb_strlen($name)<=200&&mb_strlen($description)<=1000,'アルバム名・説明が長すぎます。');
    $position=(int)($d['position']??1);requirev($position>=1&&$position<=999,'表示順は1〜999です。');$visible=($d['visible']??'')==='yes'?1:0;
    $before=$id?one('SELECT * FROM albums WHERE id=?',[$id]):null;if($id)requirev((bool)$before,'アルバムがありません。');
    if($id)q('UPDATE albums SET name=?,description=?,position_no=?,visible=? WHERE id=?',[$name,$description,$position,$visible,$id]);
    else {q('INSERT INTO albums (name,description,position_no,visible) VALUES (?,?,?,?)',[$name,$description,$position,$visible]);$id=(int)$db->lastInsertId();}
    logv((int)$u['id'],'アルバム設定','album:'.$id,$before,['name'=>$name,'description'=>$description,'position'=>$position,'visible'=>$visible],reasonv($d));return $id;
}
function movePhoto(array $u,array $d):void {
    adminInquiry($u);$id=(int)($d['id']??0);$album=(int)($d['album_id']??0);$f=one("SELECT * FROM private_files WHERE id=? AND kind='photo'",[$id]);requirev($f&&one('SELECT id FROM albums WHERE id=?',[$album]),'写真と移動先アルバムを確認してください。');
    q('UPDATE private_files SET album_id=? WHERE id=?',[$album,$id]);logv((int)$u['id'],'写真のアルバム移動','file:'.$id,['album_id'=>$f['album_id']],['album_id'=>$album],reasonv($d));
}
