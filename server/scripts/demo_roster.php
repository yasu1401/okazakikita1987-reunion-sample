<?php
declare(strict_types=1);
function demoProfile(int $class,int $position):array{static $profiles=null;$profiles??=json_decode(file_get_contents(APP_ROOT.'/fixtures/demo_roster.json'),true,512,JSON_THROW_ON_ERROR);return $profiles[(string)$class][$position%30];}
function demoInsert(int $number,int $class,int $position,string $hash,string $status='active'):int {
 global $db;$profile=demoProfile($class,$position);$d=['name'=>$profile['name'],'kana'=>$profile['kana'],'class'=>$class,'address'=>'愛知県架空市見本町1-2-3','postal'=>'000-0000','phone'=>'000-0000-0000','payer'=>'サンプル'.$number,'display'=>$profile['name'],'public'=>$position===0?'no':'yes','note'=>'架空データ・実在の卒業生とは関係ありません'];$data=participantData($d);
 q('INSERT INTO users (email,password_hash,role,name,created) VALUES (?,?,?,?,?)',['sample'.$number.'@example.test',$hash,$number===1?'owner':($number===2?'organizer':'participant'),$profile['name'],nowv()]);$uid=(int)$db->lastInsertId();
 q('INSERT INTO registrations (user_id,class_no,data_json,amount,status,revision,created) VALUES (?,?,?,?,?,?,?)',[$uid,$class,jsonv($data),setting()['fee'],$status,1,nowv()]);
 logv(null,'架空の見本参加者を追加','registration:'.$db->lastInsertId(),null,['class'=>$class,'status'=>$status,'fictional'=>true],'開発用seed。実在の卒業生・実際の参加登録ではありません。');return $uid;
}
function expandDemoRoster():void {
 global $config;requirev($config['mode']==='development','開発環境専用です。');schema();foreach(all('SELECT email FROM users') as $u)requirev(str_ends_with($u['email'],'@example.test'),'架空の見本アカウントのみのDBで実行してください。');
 $hash=password_hash('111111',PASSWORD_DEFAULT);
 atomic(function()use($hash){$updated=0;$added=0;$next=1;foreach(all('SELECT email FROM users') as $u)if(preg_match('/^sample([0-9]+)@example\.test$/D',$u['email'],$m))$next=max($next,(int)$m[1]+1);
  for($class=1;$class<=10;$class++){$rows=all("SELECT r.*,u.name,u.email FROM registrations r JOIN users u ON u.id=r.user_id WHERE r.class_no=? ORDER BY CASE WHEN r.status='active' THEN 0 ELSE 1 END,r.id",[$class]);$position=0;
   foreach($rows as $r){$profile=demoProfile($class,$position++);if(!preg_match('/^sample[0-9]+@example\.test$/D',$r['email'])||!preg_match('/^見本 同窓生[0-9]+$/u',$r['name']))continue;$data=obj($r['data_json']);$before=$data;$data['name']=$profile['name'];$data['kana']=$profile['kana'];if(preg_match('/^見本[0-9]+$/u',$data['display']))$data['display']=$profile['name'];q('UPDATE users SET name=? WHERE id=?',[$data['name'],$r['user_id']]);q('UPDATE registrations SET data_json=? WHERE id=?',[jsonv($data),$r['id']]);logv(null,'架空の見本氏名を更新','registration:'.$r['id'],$before,$data,'例示用の実在しそうな名前へ変更。履歴・申込状態は保持。');$updated++;}
   $active=(int)one("SELECT COUNT(*) n FROM registrations WHERE class_no=? AND status='active'",[$class])['n'];while($active<16){demoInsert($next++,$class,$position++,$hash);$active++;$added++;}
  }echo "架空氏名の更新 {$updated}件、追加 {$added}件。既存の独自編集・入金・投稿・幹事指定は保持。\n";
 });
}

/** Explicit development fixture migration, never run for live registrations. */
function varyDemoRoster():void {
 global $config;requirev($config['mode']==='development','開発環境専用です。');schema();foreach(all('SELECT email FROM users') as $u)requirev((bool)preg_match('/^sample[0-9]+@example\.test$/D',$u['email']),'見本アカウントだけのDBで実行してください。');
 atomic(function(){
  $s=setting();if(($s['demoRosterVarietyVersion']??0)===1)return;$targets=[18,23,16,21,26,19,24,17,22,20];$next=1;foreach(all('SELECT email FROM users') as $u)if(preg_match('/^sample([0-9]+)@example\.test$/D',$u['email'],$m))$next=max($next,(int)$m[1]+1);$hash=password_hash('111111',PASSWORD_DEFAULT);$added=0;$organizers=[];
  for($class=1;$class<=10;$class++){$rows=all('SELECT r.*,u.email,u.role FROM registrations r JOIN users u ON u.id=r.user_id WHERE r.class_no=? ORDER BY r.id',[$class]);$position=count($rows);$active=count(array_filter($rows,fn($r)=>$r['status']==='active'));while($active<$targets[$class-1]){demoInsert($next++,$class,$position++,$hash);$active++;$added++;}
   if(!one("SELECT id FROM audit_logs WHERE entity=? AND action IN ('クラス幹事表示を指定','クラス幹事表示を解除')",['class:'.$class])){q('DELETE FROM class_contacts WHERE class_no=?',[$class]);$candidates=all("SELECT r.*,u.role FROM registrations r JOIN users u ON u.id=r.user_id WHERE r.class_no=? AND r.status='active' ORDER BY r.id",[$class]);$j=0;foreach($candidates as $r){$d=obj($r['data_json']);if(empty($d['public']))continue;q('INSERT INTO class_contacts (user_id,class_no,position_no) VALUES (?,?,?)',[$r['user_id'],$class,++$j]);if($j>=($class%2===0?3:2))break;}}
  }
  foreach([2,5,7,10] as $class){foreach(all("SELECT u.*,r.id registration_id,r.data_json FROM users u JOIN registrations r ON r.user_id=u.id WHERE r.class_no=? AND r.status='active' AND u.role='participant' AND r.revision=1 ORDER BY r.id",[$class]) as $u){$d=obj($u['data_json']);if(empty($d['public'])||one('SELECT id FROM class_contacts WHERE user_id=?',[$u['id']]))continue;if(one("SELECT id FROM audit_logs WHERE entity=? AND action LIKE '%権限%'",['user:'.$u['id']]))continue;q("UPDATE users SET role='organizer' WHERE id=?",[$u['id']]);logv(null,'架空の管理担当を指定','user:'.$u['id'],['role'=>'participant'],['role'=>'organizer'],'クラス幹事を兼ねない幹事表示の見本');$organizers[]=$u['id'];break;}}
  $s['demoRosterVarietyVersion']=1;q('UPDATE settings SET data_json=? WHERE id=1',[jsonv($s)]);logv(null,'クラス別の人数と幹事見本を更新','demo',null,['added'=>$added,'counts'=>$targets,'organizers'=>$organizers],'開発専用の架空データ変更。既存入金と投稿は保持');
 });
}
