<?php
require __DIR__.'/community.php';require APP_ROOT.'/src/photos.php';
$a=publicUser(1);$p=publicUser(3);$album=submissionAlbum();check($album&&$album['name']==='みんなの投稿写真','専用投稿アルバムを作成');
q("INSERT INTO private_files (owner_id,expense_id,kind,class_no,storage_key,mime,original_name,published,created,album_id,photo_title,caption,author_label,submitted) VALUES (3,NULL,'photo',0,'photo-test','image/jpeg','test.jpg',1,?,?,'近所の景色','元のコメント','見本投稿者',1)",[nowv(),$album['id']]);$fid=(int)$GLOBALS['db']->lastInsertId();
$edit=['id'=>$fid,'title'=>'修正したタイトル','caption'=>'修正後 <script>','author'=>'ニックネーム','album_id'=>$album['id'],'sort_order'=>'4','published'=>'yes','reason'=>'個人情報を含む部分を修正'];
denied(fn()=>atomic(fn()=>moderatePhoto($p,$edit)),'参加者によるコメント編集を拒否');
atomic(fn()=>moderatePhoto($a,$edit));$f=one('SELECT * FROM private_files WHERE id=?',[$fid]);check($f['caption']==='修正後 <script>'&&(int)$f['sort_order']===4,'幹事がコメントと順序を保存');
$log=one("SELECT * FROM audit_logs WHERE action='写真・コメントを編集' ORDER BY id DESC LIMIT 1");check(str_contains($log['before_json'],'元のコメント')&&str_contains($log['after_json'],'修正後'),'修正前後を履歴に保持');
denied(fn()=>atomic(fn()=>moderatePhoto($a,[...$edit,'sort_order'=>'-1'])),'不正な並び順を拒否');
atomic(fn()=>removePhoto($a,['id'=>$fid,'reason'=>'不適切な投稿の取下げ']));$f=one('SELECT * FROM private_files WHERE id=?',[$fid]);check(!canFile($p,$f)&&!canFile($a,$f),'削除写真の直接URLを参加者・幹事とも拒否');
atomic(fn()=>restorePhoto($a,['id'=>$fid,'reason'=>'削除内容を再確認']));$f=one('SELECT * FROM private_files WHERE id=?',[$fid]);check(!(int)$f['deleted']&&!(int)$f['published'],'復元は非公開・自動再掲載しない');
denied(fn()=>submitPhoto($p,['title'=>'test','author'=>'見本','caption'=>str_repeat('あ',1001),'consent'=>'yes','post_key'=>str_repeat('a',32)],[]),'1000文字を超える投稿コメントを拒否');
q("UPDATE registrations SET status='cancelled' WHERE user_id=3");denied(fn()=>photoMember($p),'取消済み参加者の投稿を拒否');q("UPDATE registrations SET status='active' WHERE user_id=3");
q('UPDATE albums SET visible=0 WHERE id=?',[$album['id']]);denied(fn()=>photoMember($p),'投稿アルバムの非表示中は投稿受付停止');
schema();check((int)one("SELECT COUNT(*) n FROM albums WHERE purpose='submissions'")['n']===1,'繰り返し移行でも投稿アルバムを二重作成しない');
echo "ALL $n PHOTO-INCLUSIVE CHECKS PASSED\n";
