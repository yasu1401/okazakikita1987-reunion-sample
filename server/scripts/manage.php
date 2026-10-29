<?php
declare(strict_types=1);
require __DIR__.'/../src/bootstrap.php';require APP_ROOT.'/src/domain.php';
$cmd=$argv[1]??'help';
if($cmd==='init'){schema();echo "スキーマ初期化完了\n";}
elseif($cmd==='seed'){requirev($config['mode']==='development','開発環境専用です。');schema();requirev(!one('SELECT id FROM users'),'空のDBでのみ実行できます。');atomic(function(){global $db;for($i=1;$i<=30;$i++){$d=['email'=>'sample'.$i.'@example.test','password'=>'Sample1987!','name'=>'見本 同窓生'.$i,'kana'=>'ミホン','class'=>($i-1)%10+1,'address'=>'架空市見本町','phone'=>'000-0000-0000','payer'=>'ミホン'.$i,'display'=>'見本'.$i,'public'=>$i%4?'yes':'no'];$uid=registerPerson($d);if($i<=2)q('UPDATE users SET role=? WHERE id=?',[$i===1?'owner':'organizer',$uid]);} });echo "架空30件。sample1=代表、sample2=幹事、sample3=参加者。@example.test / Sample1987!\n";}
elseif($cmd==='owner'){schema();$email=$argv[2]??'';requirev(filter_var($email,FILTER_VALIDATE_EMAIL)!==false,'メールアドレスを指定してください。');fwrite(STDOUT,"代表幹事パスワードを標準入力から入力（チャットやコマンド引数に記載しない）: ");$pw=trim(fgets(STDIN));passwordv($pw);atomic(function()use($email,$pw){global $db;requirev(!one('SELECT id FROM users WHERE email=?',[$email]),'登録済みです。');q('INSERT INTO users (email,password_hash,role,name,created) VALUES (?,?,?,?,?)',[$email,password_hash($pw,PASSWORD_DEFAULT),'owner','代表幹事',nowv()]);logv((int)$db->lastInsertId(),'代表幹事初期登録','setup',null,null,'CLI初期化');});echo "登録完了\n";}
else echo "php scripts/manage.php init | seed | owner メールアドレス\n";
