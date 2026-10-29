<?php
/** Explicit, opt-in live sender. No email is sent without both config and --send. */
declare(strict_types=1);
require __DIR__.'/../src/bootstrap.php';
$count=(int)one("SELECT COUNT(*) n FROM mail_outbox WHERE status='queued'")['n'];
$bulkCount=(int)one("SELECT COUNT(*) n FROM broadcast_messages WHERE status='queued'")['n'];
$count+=$bulkCount;
if(($argv[1]??'')!=='--send'){echo "送信待ち {$count} 件。表示のみ・実送信なし。\n";exit;}
requirev($config['mode']==='live'&&!empty($config['mail_enabled']),'実送信はliveかつmail_enabled=trueの場合だけ許可します。');
$from=(string)($config['mail_from']??'');$name=(string)($config['mail_from_name']??'');
requirev(filter_var($from,FILTER_VALIDATE_EMAIL)!==false&&!preg_match('/[\r\n]/',$from.$name)&&!str_ends_with($from,'.test'),'管理下の実在する送信元アドレスを設定してください。');
for($i=0;$i<20;$i++){
    $message=atomic(function(){ $table='mail_outbox';$m=one("SELECT * FROM mail_outbox WHERE status='queued' ORDER BY id LIMIT 1");if(!$m){$table='broadcast_messages';$m=one("SELECT m.*,b.subject,b.body FROM broadcast_messages m JOIN broadcasts b ON b.id=m.broadcast_id WHERE m.status='queued' AND b.status='queued' ORDER BY m.id LIMIT 1");}if($m){q("UPDATE {$table} SET status='sending',updated=? WHERE id=?",[nowv(),$m['id']]);$m['queue_table']=$table;}return $m; });
    if(!$message)break;
    try{
        requirev(filter_var($message['to_email'],FILTER_VALIDATE_EMAIL)!==false&&!preg_match('/[\r\n]/',$message['to_email']),'宛先が不正です。');
        $headers=['From'=>'=?UTF-8?B?'.base64_encode($name).'?= <'.$from.'>','Reply-To'=>$from,'MIME-Version'=>'1.0','Content-Type'=>'text/plain; charset=UTF-8','Content-Transfer-Encoding'=>'base64'];
        $ok=mail($message['to_email'],'=?UTF-8?B?'.base64_encode($message['subject']).'?=',chunk_split(base64_encode($message['body'])),$headers);
        $state=$ok?'accepted':'failed';$error=$ok?'':'mail()が送信要求を受け付けませんでした。';
    }catch(Throwable $e){$state='failed';$error='送信エラー。サーバーログを確認してください。';error_log($e->getMessage());}
    atomic(function()use($message,$state,$error){q("UPDATE {$message['queue_table']} SET status=?,error_text=?,updated=? WHERE id=?",[$state,$error,nowv(),$message['id']]);logv(null,'メール送信処理',$message['queue_table'].':'.$message['id'],['status'=>'sending'],['status'=>$state],$error?:'サーバー受付済・宛先への到達は別途確認');});
}
// A process interrupted after claiming stays "sending". Never retry it automatically:
// verify mail logs first to avoid sending an already delivered reply twice.
