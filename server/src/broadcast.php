<?php
declare(strict_types=1);
function broadcastAudience(string $target):array {
 requirev(in_array($target,['all','active'],true),'送信対象を選択してください。');$recipients=[];$excluded=[];$seen=[];
 foreach(all('SELECT u.id,u.name,u.email,r.status FROM users u JOIN registrations r ON r.user_id=u.id ORDER BY u.id') as $u){if($target==='active'&&$u['status']!=='active')continue;$email=strtolower(trim($u['email']));if(!filter_var($email,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$email)){$excluded[]=['user_id'=>$u['id'],'reason'=>'メールアドレス不正'];continue;}if(isset($seen[$email])){$excluded[]=['user_id'=>$u['id'],'reason'=>'同じ宛先を1件に集約'];continue;}$seen[$email]=true;$recipients[]=['user_id'=>(int)$u['id'],'name'=>$u['name'],'email'=>$email,'registration_status'=>$u['status']];}
 return ['recipients'=>$recipients,'excluded'=>$excluded];
}
function previewBroadcast(array $u,array $d):int {
 global $db;adminInquiry($u);$subject=textValue($d,'subject',600);requirev(mb_strlen($subject)<=200&&!preg_match('/[\r\n]/',$subject),'件名は改行なし200文字以内です。');$body=textValue($d,'body',30000);requirev(mb_strlen($body)<=10000,'本文は10000文字以内です。');$target=(string)($d['target']??'all');$audience=broadcastAudience($target);requirev(count($audience['recipients'])>0,'送信対象がありません。');
 q('INSERT INTO broadcasts (actor_id,subject,body,target,status,audience_json,created,queued_at) VALUES (?,?,?,?,?,?,?,?)',[$u['id'],$subject,$body,$target,'draft',jsonv($audience),nowv(),'']);$id=(int)$db->lastInsertId();
 foreach($audience['recipients'] as $r)q('INSERT INTO broadcast_messages (broadcast_id,user_id,recipient_name,to_email,status,error_text,created,updated) VALUES (?,?,?,?,?,?,?,?)',[$id,$r['user_id'],$r['name'],$r['email'],'draft','',nowv(),nowv()]);
 logv((int)$u['id'],'一斉メールの確認用下書き','broadcast:'.$id,null,['target'=>$target,'count'=>count($audience['recipients']),'excluded'=>count($audience['excluded'])],'件名・本文・送信対象を保存。送信は未確定。');return $id;
}
function queueBroadcast(array $u,array $d):int {
 global $config;adminInquiry($u);$id=(int)($d['id']??0);$b=one('SELECT * FROM broadcasts WHERE id=?',[$id]);requirev((bool)$b,'一斉メールがありません。');
 if(in_array($b['status'],['queued','development'],true))return $id;
 requirev($b['status']==='draft'&&($d['confirm']??'')==='yes','対象人数と内容を確認し、チェックしてください。');requirev(jsonv(broadcastAudience($b['target']))===$b['audience_json'],'申込者やメールアドレスに変更がありました。下書きから対象を確認し直してください。');
 $status=$config['mode']==='live'&&!empty($config['mail_enabled'])?'queued':'development';q('UPDATE broadcast_messages SET status=?,updated=? WHERE broadcast_id=? AND status=?',[$status,nowv(),$id,'draft']);q('UPDATE broadcasts SET status=?,queued_at=? WHERE id=?',[$status,nowv(),$id]);
 logv((int)$u['id'],'一斉メールを登録','broadcast:'.$id,['status'=>'draft'],['status'=>$status,'count'=>count(obj($b['audience_json'])['recipients'])],'宛先ごとに個別配信。開発環境では送信しません。');return $id;
}
function cancelBroadcast(array $u,array $d):void {
 adminInquiry($u);$id=(int)($d['id']??0);$b=one('SELECT * FROM broadcasts WHERE id=?',[$id]);requirev($b&&$b['status']!=='cancelled','停止できる一斉メールがありません。');$reason=reasonv($d);
 $n=q("UPDATE broadcast_messages SET status='cancelled',updated=? WHERE broadcast_id=? AND status IN ('draft','queued','development')",[nowv(),$id])->rowCount();q("UPDATE broadcasts SET status='cancelled' WHERE id=?",[$id]);logv((int)$u['id'],'一斉メールの未処理分を停止','broadcast:'.$id,['status'=>$b['status']],['status'=>'cancelled','stopped'=>$n],$reason);
}
