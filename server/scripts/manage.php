<?php
declare(strict_types=1);
require __DIR__.'/../src/bootstrap.php';require APP_ROOT.'/src/domain.php';require APP_ROOT.'/src/community.php';
$cmd=$argv[1]??'help';
if(in_array($cmd,['init','migrate'],true)){schema();echo "スキーマ初期化完了\n";}
elseif($cmd==='seed'){requirev($config['mode']==='development','開発環境専用です。');schema();requirev(!one('SELECT id FROM users'),'空のDBでのみ実行できます。');atomic(function(){global $db;for($i=1;$i<=30;$i++){$d=['email'=>'sample'.$i.'@example.test','password'=>'Sample1987!','name'=>'見本 同窓生'.$i,'kana'=>'ミホン','class'=>($i-1)%10+1,'address'=>'架空市見本町','phone'=>'000-0000-0000','payer'=>'ミホン'.$i,'display'=>'見本'.$i,'public'=>$i%4?'yes':'no'];$uid=registerPerson($d);if($i<=2)q('UPDATE users SET role=? WHERE id=?',[$i===1?'owner':'organizer',$uid]);} });echo "架空30件。sample1=代表、sample2=幹事、sample3=参加者。@example.test / Sample1987!\n";}
elseif($cmd==='seed-community'){
 requirev($config['mode']==='development','開発環境専用です。');schema();
 foreach(all('SELECT email FROM users') as $u)requirev(str_ends_with($u['email'],'@example.test'),'見本アカウントだけのDBで実行してください。');
 $owner=one("SELECT id,email,role,name FROM users WHERE role='owner' ORDER BY id LIMIT 1");requirev((bool)$owner,'先にseedしてください。');
 atomic(function()use($owner){for($cl=1;$cl<=10;$cl++){if(classContacts($cl))continue;foreach(array_slice(all("SELECT user_id FROM registrations WHERE class_no=? AND status='active' ORDER BY id",[$cl]),0,2) as $j=>$p)setClassContact($owner,['class'=>$cl,'user_id'=>$p['user_id'],'position'=>$j+1,'reason'=>'架空のクラス幹事表示見本']);}});echo "架空のクラス幹事を各組2名設定しました。管理権限は変更していません。\n";
}
elseif($cmd==='owner'){schema();$email=$argv[2]??'';requirev(filter_var($email,FILTER_VALIDATE_EMAIL)!==false,'メールアドレスを指定してください。');fwrite(STDOUT,"代表幹事パスワードを標準入力から入力（チャットやコマンド引数に記載しない）: ");$pw=trim(fgets(STDIN));passwordv($pw);atomic(function()use($email,$pw){global $db;requirev(!one('SELECT id FROM users WHERE email=?',[$email]),'登録済みです。');q('INSERT INTO users (email,password_hash,role,name,created) VALUES (?,?,?,?,?)',[$email,password_hash($pw,PASSWORD_DEFAULT),'owner','代表幹事',nowv()]);logv((int)$db->lastInsertId(),'代表幹事初期登録','setup',null,null,'CLI初期化');});echo "登録完了\n";}
else echo "php scripts/manage.php init | seed | owner メールアドレス\n";
