<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Tokyo');
const APP_ROOT=__DIR__.'/..';
$config=require is_file(APP_ROOT.'/config.local.php')?APP_ROOT.'/config.local.php':APP_ROOT.'/config.example.php';
if(getenv('REUNION_TEST_DSN'))$config['dsn']=getenv('REUNION_TEST_DSN');
if(getenv('REUNION_TEST_STORAGE'))$config['storage']=getenv('REUNION_TEST_STORAGE');
if($config['mode']==='live'&&!is_file(APP_ROOT.'/config.local.php'))throw new RuntimeException('Live requires a local config.');
$storage=rtrim($config['storage'],'/');if(!is_dir($storage))mkdir($storage,0700,true);
$docroot=realpath(!empty($_SERVER['DOCUMENT_ROOT'])?$_SERVER['DOCUMENT_ROOT']:APP_ROOT.'/public');
$privatePath=realpath($storage)?:$storage;
if($config['mode']==='live'&&$docroot&&($privatePath===$docroot||str_starts_with($privatePath,$docroot.'/')))throw new RuntimeException('Storage must be outside the document root.');
$db=new PDO($config['dsn'],$config['db_user'],$config['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
if($db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'){$db->exec('PRAGMA foreign_keys=ON');$db->exec('PRAGMA busy_timeout=10000');}
function q(string $sql,array $args=[]):PDOStatement{global $db;$s=$db->prepare($sql);$s->execute($args);return $s;}
function one(string $sql,array $args=[]):?array{return q($sql,$args)->fetch()?:null;}
function all(string $sql,array $args=[]):array{return q($sql,$args)->fetchAll();}
function jsonv(mixed $x):string{return json_encode($x,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);}
function obj(string $x):array{return json_decode($x,true,512,JSON_THROW_ON_ERROR);}
function nowv():string{return date('Y-m-d H:i:s');}
function h(mixed $v):string{return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function yen(int $v):string{return number_format($v).'円';}
function requirev(bool $test,string $message):void{if(!$test)throw new DomainException($message);}
function schema():void{global $db;$mysql=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';$id=$mysql?'INTEGER PRIMARY KEY AUTO_INCREMENT':'INTEGER PRIMARY KEY AUTOINCREMENT';$text=$mysql?'LONGTEXT':'TEXT';$defs=[
 'app_lock'=>'id INTEGER PRIMARY KEY, version INTEGER NOT NULL',
 'settings'=>"id INTEGER PRIMARY KEY, data_json $text NOT NULL",
 'users'=>"id $id, email VARCHAR(254) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, role VARCHAR(20) NOT NULL, name VARCHAR(200) NOT NULL, created VARCHAR(30) NOT NULL",
 'registrations'=>"id $id, user_id INTEGER NOT NULL UNIQUE, class_no INTEGER NOT NULL, data_json $text NOT NULL, amount INTEGER NOT NULL, status VARCHAR(30) NOT NULL, revision INTEGER NOT NULL, created VARCHAR(30) NOT NULL",
 'bank_imports'=>"id $id, source_hash VARCHAR(64) NOT NULL UNIQUE, filename VARCHAR(200) NOT NULL, source_text $text NOT NULL, report_json $text NOT NULL, actor_id INTEGER NOT NULL, created VARCHAR(30) NOT NULL",
 'bank_transactions'=>"id $id, import_id INTEGER NOT NULL, row_no INTEGER NOT NULL, row_hash VARCHAR(64) NOT NULL, raw_json $text NOT NULL, txn_date VARCHAR(10) NOT NULL, memo VARCHAR(1000) NOT NULL, credit INTEGER NOT NULL, debit INTEGER NOT NULL, balance INTEGER NOT NULL, state VARCHAR(20) NOT NULL, review VARCHAR(1000) NOT NULL",
 'allocations'=>"id $id, bank_id INTEGER NOT NULL, registration_id INTEGER, expense_id INTEGER, amount INTEGER NOT NULL, active INTEGER NOT NULL, reason VARCHAR(1000) NOT NULL, actor_id INTEGER NOT NULL, created VARCHAR(30) NOT NULL",
 'expenses'=>"id $id, user_id INTEGER NOT NULL, expense_date VARCHAR(10) NOT NULL, category VARCHAR(100) NOT NULL, payee VARCHAR(200) NOT NULL, purpose VARCHAR(1000) NOT NULL, amount INTEGER NOT NULL, status VARCHAR(30) NOT NULL, created VARCHAR(30) NOT NULL",
 'private_files'=>"id $id, owner_id INTEGER NOT NULL, expense_id INTEGER, kind VARCHAR(20) NOT NULL, class_no INTEGER NOT NULL, storage_key VARCHAR(64) NOT NULL UNIQUE, mime VARCHAR(100) NOT NULL, original_name VARCHAR(200) NOT NULL, published INTEGER NOT NULL, created VARCHAR(30) NOT NULL",
 'audit_logs'=>"id $id, actor_id INTEGER, action VARCHAR(100) NOT NULL, entity VARCHAR(100) NOT NULL, before_json $text NOT NULL, after_json $text NOT NULL, reason VARCHAR(1000) NOT NULL, created VARCHAR(30) NOT NULL",
 'login_attempts'=>"id $id, bucket VARCHAR(64) NOT NULL, succeeded INTEGER NOT NULL, attempted INTEGER NOT NULL",
 'inquiries'=>"id $id, user_id INTEGER, name VARCHAR(200) NOT NULL, email VARCHAR(254) NOT NULL, subject VARCHAR(200) NOT NULL, body $text NOT NULL, status VARCHAR(20) NOT NULL, assignee_id INTEGER, submission_key VARCHAR(64) NOT NULL UNIQUE, request_bucket VARCHAR(64) NOT NULL, created VARCHAR(30) NOT NULL",
 'inquiry_replies'=>"id $id, inquiry_id INTEGER NOT NULL, actor_id INTEGER NOT NULL, body $text NOT NULL, reply_key VARCHAR(64) NOT NULL UNIQUE, created VARCHAR(30) NOT NULL",
 'mail_outbox'=>"id $id, reply_id INTEGER NOT NULL UNIQUE, to_email VARCHAR(254) NOT NULL, subject VARCHAR(200) NOT NULL, body $text NOT NULL, status VARCHAR(20) NOT NULL, error_text $text NOT NULL, created VARCHAR(30) NOT NULL, updated VARCHAR(30) NOT NULL",
 'class_contacts'=>"id $id, user_id INTEGER NOT NULL, class_no INTEGER NOT NULL, position_no INTEGER NOT NULL, UNIQUE (class_no,user_id)",
 'albums'=>"id $id, name VARCHAR(200) NOT NULL, description VARCHAR(1000) NOT NULL, position_no INTEGER NOT NULL, visible INTEGER NOT NULL",
 'broadcasts'=>"id $id, actor_id INTEGER NOT NULL, subject VARCHAR(200) NOT NULL, body $text NOT NULL, target VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, audience_json $text NOT NULL, created VARCHAR(30) NOT NULL, queued_at VARCHAR(30) NOT NULL",
 'broadcast_messages'=>"id $id, broadcast_id INTEGER NOT NULL, user_id INTEGER NOT NULL, recipient_name VARCHAR(200) NOT NULL, to_email VARCHAR(254) NOT NULL, status VARCHAR(20) NOT NULL, error_text $text NOT NULL, created VARCHAR(30) NOT NULL, updated VARCHAR(30) NOT NULL, UNIQUE (broadcast_id,to_email)",
 'reset_tokens'=>"id $id, user_id INTEGER NOT NULL, token_hash VARCHAR(64) NOT NULL UNIQUE, expires INTEGER NOT NULL, used INTEGER NOT NULL"
 ];foreach($defs as $table=>$def)$db->exec("CREATE TABLE IF NOT EXISTS $table ($def)".($mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4':''));if(!one('SELECT id FROM app_lock WHERE id=1'))q('INSERT INTO app_lock VALUES (1,0)');if(!one('SELECT id FROM settings WHERE id=1'))q('INSERT INTO settings VALUES (1,?)',[jsonv(['dateLabel'=>'2028年10月31日（火）（仮）','venue'=>'岡崎ニューグランドホテル','fee'=>8000,'capacity'=>500,'classSize'=>47,'open'=>'2026-10-01','registration'=>'2028-07-31','payment'=>'2028-08-31','change'=>'2028-09-30','cancel'=>'2028-09-30','photoEnd'=>'2029-03-31','opening'=>0])]);$columns=$mysql?array_column(all('SHOW COLUMNS FROM private_files'),'Field'):array_column(all('PRAGMA table_info(private_files)'),'name');
 if(!in_array('album_id',$columns,true))$db->exec('ALTER TABLE private_files ADD COLUMN album_id INTEGER NOT NULL DEFAULT 1');
 $inquiryColumns=$mysql?array_column(all('SHOW COLUMNS FROM inquiries'),'Field'):array_column(all('PRAGMA table_info(inquiries)'),'name');
 if(!in_array('class_no',$inquiryColumns,true))$db->exec('ALTER TABLE inquiries ADD COLUMN class_no INTEGER NULL');
 $fileFields=['photo_title'=>"VARCHAR(200) NOT NULL DEFAULT ''",'caption'=>"TEXT",'author_label'=>"VARCHAR(100) NOT NULL DEFAULT ''",'sort_order'=>'INTEGER NOT NULL DEFAULT 0','deleted'=>'INTEGER NOT NULL DEFAULT 0','submitted'=>'INTEGER NOT NULL DEFAULT 0','post_key'=>'VARCHAR(64) NULL'];
 foreach($fileFields as $column=>$definition)if(!in_array($column,$columns,true))$db->exec("ALTER TABLE private_files ADD COLUMN $column $definition");
 $albumColumns=$mysql?array_column(all('SHOW COLUMNS FROM albums'),'Field'):array_column(all('PRAGMA table_info(albums)'),'name');
 if(!in_array('purpose',$albumColumns,true))$db->exec("ALTER TABLE albums ADD COLUMN purpose VARCHAR(30) NOT NULL DEFAULT ''");
 q("UPDATE albums SET name='フォトアルバム' WHERE name='思い出アルバム'");
 if(!one('SELECT id FROM albums LIMIT 1'))foreach(['卒業アルバム'] as $i=>$name)q('INSERT INTO albums (name,description,position_no,visible) VALUES (?,?,?,1)',[$name,'',$i+1]);
 if(!one("SELECT id FROM albums WHERE purpose='submissions'"))q("INSERT INTO albums (name,description,position_no,visible,purpose) VALUES (?,?,?,1,'submissions')",['みんなの投稿写真','最近の一枚や、身近な風景。みんなの写真とコメントをどうぞ。',50]);
}
function atomic(callable $fn):mixed{global $db;$db->beginTransaction();try{q('UPDATE app_lock SET version=version+1 WHERE id=1');$r=$fn();$db->commit();return $r;}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}}
function logv(?int $actor,string $action,string $entity,mixed $before,mixed $after,string $reason):void{q('INSERT INTO audit_logs (actor_id,action,entity,before_json,after_json,reason,created) VALUES (?,?,?,?,?,?,?)',[$actor,$action,$entity,jsonv($before),jsonv($after),$reason,nowv()]);}
function setting():array{return obj(one('SELECT data_json FROM settings WHERE id=1')['data_json']);}
function deadline(string $key):void{$s=setting();requirev(date('Y-m-d')<=$s[$key],'操作期限を過ぎています。幹事へご連絡ください。');if($key==='registration')requirev(date('Y-m-d')>=$s['open'],'受付開始前です。');}
function actor():?array{return isset($_SESSION['uid'])?one('SELECT id,email,role,name FROM users WHERE id=?',[(int)$_SESSION['uid']]):null;}
function auth(bool $admin=false,bool $owner=false):array{$u=actor();requirev((bool)$u,'ログインが必要です。');if($admin)requirev(in_array($u['role'],['organizer','owner'],true),'幹事権限が必要です。');if($owner)requirev($u['role']==='owner','管理者の権限が必要です。');return $u;}
function passwordv(string $pw,bool $sample=false):void{global $config;if($sample&&$config['mode']==='development'&&$pw==='111111')return;requirev((bool)preg_match('/^.{8,}$/us',$pw)&&strlen($pw)<=72,'パスワードは8文字以上、UTF-8で72バイト以内にしてください。');}
function reasonv(array $d):string{$r=trim((string)($d['reason']??''));requirev($r!==''&&strlen($r)<=3000,'処理理由を入力してください。');return $r;}
function moneyv(mixed $v):int{$s=str_replace([',','円',' '],'',(string)$v);requirev((bool)preg_match('/^[0-9]+$/D',$s)&&strlen($s)<=9,'金額は0〜100,000,000円の整数にしてください。');$n=(int)$s;requirev($n<=100000000,'金額が上限を超えています。');return $n;}
function datev(string $v):string{if(preg_match('/^(\d{4})[\/.-](\d{1,2})[\/.-](\d{1,2})$/D',$v,$m))[, $y,$mo,$d]=$m;elseif(preg_match('/^\d{8}$/D',$v)){$y=substr($v,0,4);$mo=substr($v,4,2);$d=substr($v,6,2);}elseif(preg_match('/^\d{6}$/D',$v)){$y='20'.substr($v,0,2);$mo=substr($v,2,2);$d=substr($v,4,2);}else throw new DomainException('日付の形式が不正です。');requirev(checkdate((int)$mo,(int)$d,(int)$y),'存在しない日付です。');return sprintf('%04d-%02d-%02d',$y,$mo,$d);}
function publicUser(int $id):?array{return one('SELECT id,email,role,name FROM users WHERE id=?',[$id]);}
