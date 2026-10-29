<?php
require __DIR__.'/home.php';require APP_ROOT.'/src/broadcast.php';
$all=broadcastAudience('all');$active=broadcastAudience('active');
check(count($all['recipients'])===count(all('SELECT * FROM registrations'))&&count($active['recipients'])<=count($all['recipients']),'全申込者と参加予定者の対象を分ける');
$d=['subject'=>'開催予定の変更 <script>','body'=>'架空のお知らせです。','target'=>'all'];
denied(fn()=>atomic(fn()=>previewBroadcast($p,$d)),'参加者の一斉メール作成を拒否');
denied(fn()=>atomic(fn()=>previewBroadcast($a,[...$d,'subject'=>"件名\r\nBcc: other@example.test"])),'件名のヘッダー注入を拒否');
$id=atomic(fn()=>previewBroadcast($b,$d));
check((int)one('SELECT COUNT(*) n FROM broadcast_messages WHERE broadcast_id=?',[$id])['n']===count($all['recipients']),'下書きに宛先ごとの情報を固定して保存');
denied(fn()=>atomic(fn()=>queueBroadcast($b,['id'=>$id])),'内容確認なしの登録を拒否');
q('UPDATE users SET name=? WHERE id=3',['新しい見本名']);
denied(fn()=>atomic(fn()=>queueBroadcast($b,['id'=>$id,'confirm'=>'yes'])),'対象情報が変われば再確認を要求');
$id2=atomic(fn()=>previewBroadcast($b,$d));atomic(fn()=>queueBroadcast($b,['id'=>$id2,'confirm'=>'yes']));atomic(fn()=>queueBroadcast($b,['id'=>$id2,'confirm'=>'yes']));
check((int)one("SELECT COUNT(*) n FROM broadcast_messages WHERE broadcast_id=? AND status='development'",[$id2])['n']===count($all['recipients']),'二重登録しても宛先が増えず開発用未送信のまま');
check(one('SELECT subject,body FROM broadcasts WHERE id=?',[$id2])['body']===$d['body'],'登録後も確認済み本文を保持');
atomic(fn()=>cancelBroadcast($a,['id'=>$id2,'reason'=>'見本の停止']));
check((int)one("SELECT COUNT(*) n FROM broadcast_messages WHERE broadcast_id=? AND status<>'cancelled'",[$id2])['n']===0,'停止理由と宛先別停止状態を保存');
denied(fn()=>atomic(fn()=>queueBroadcast($b,['id'=>$id2,'confirm'=>'yes'])),'停止済みのメールを再登録しない');
echo "ALL $n BROADCAST-INCLUSIVE CHECKS PASSED\n";
