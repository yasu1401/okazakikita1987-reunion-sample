<?php
declare(strict_types=1);
function participantAdminView():string {
 auth(true);$all=all('SELECT r.*,u.name,u.email,u.role FROM registrations r JOIN users u ON u.id=r.user_id ORDER BY r.id');
 $query=mb_substr(trim((string)($_GET['query']??'')),0,200);$cl=(string)($_GET['class_filter']??'');if(!preg_match('/^(?:[0-9]|10)$/D',$cl))$cl='';
 if(($_GET['class_pending']??'')==='1')$cl='0';
 $status=(string)($_GET['status_filter']??'');if(!in_array($status,['active','cancel_requested','cancelled'],true))$status='';
 $payment=(string)($_GET['payment_filter']??'');if(!in_array($payment,['unpaid','partial','paid','overpaid'],true))$payment='';
 $pending=count(array_filter($all,fn($p)=>(int)$p['class_no']===0));$rows=[];
 foreach($all as $p){$data=obj($p['data_json']);$paid=paidRegistration((int)$p['id']);$billed=$p['status']==='cancelled'?0:(int)$p['amount'];
  $hay=implode(' ',[$p['name'],$data['oldname']??'',$data['kana']??'',$p['email'],$data['payer']??'','K87-'.str_pad((string)$p['id'],5,'0',STR_PAD_LEFT)]);
  if($query!==''&&!str_contains(mb_strtolower(mb_convert_kana($hay,'KVas','UTF-8')),mb_strtolower(mb_convert_kana($query,'KVas','UTF-8'))))continue;
  if($cl!==''&&(int)$p['class_no']!==(int)$cl)continue;if($status!==''&&$p['status']!==$status)continue;
  if($payment!==''&&!match($payment){'unpaid'=>$billed>0&&$paid===0,'partial'=>$paid>0&&$paid<$billed,'paid'=>$billed>0&&$paid===$billed,'overpaid'=>$paid>$billed})continue;
  $buttons=$p['status']==='cancel_requested'?form('cancel_decide',hidden('id',$p['id']).'<select name="decision"><option value="approve">欠席を承認</option><option value="reject">参加へ戻す</option></select>'.reasons().button('記録')):'';
  $rows[]=['K87-'.str_pad((string)$p['id'],5,'0',STR_PAD_LEFT),h($p['name']).'<br>'.h(registrationClassLabel((int)$p['class_no'])).classCorrectionForm($p),h($p['email']).'<br>'.h($data['address']).'<br>'.h($data['phone']),h(['active'=>'参加予定','cancel_requested'=>'欠席申請中','cancelled'=>'取消済'][$p['status']]??$p['status']),yen((int)$p['amount']),yen($paid),$buttons];
 }
 $select=function(string $name,string $label,array $items,string $value):string{$html='<label class="field">'.$label.'<select name="'.$name.'">';foreach($items as $v=>$text)$html.='<option value="'.h($v).'"'.((string)$v===$value?' selected':'').'>'.h($text).'</option>';return $html.'</select></label>';};
 $classes=[''=>'全クラス','0'=>'クラス未確認'];for($i=1;$i<=10;$i++)$classes[(string)$i]='3年'.$i.'組';
 $html='<h1>参加者と入金状況</h1><p>クラス未確認：'.$pending.'件　<a href="?page=admin&amp;class_filter=0">未確認だけ表示</a></p><section class="panel"><form method="get"><input type="hidden" name="page" value="admin"><label class="field">名前・旧姓・ふりがな・申込番号・メール・振込名義で検索<input name="query" value="'.h($query).'" maxlength="200" placeholder="例：ヤマダ、K87-00001"></label><div class="grid-2">'.$select('class_filter','クラス',$classes,$cl).$select('status_filter','参加状況',[''=>'すべての参加状況','active'=>'参加予定','cancel_requested'=>'欠席申請中','cancelled'=>'取消済'],$status).$select('payment_filter','入金状況',[''=>'すべての入金状況','unpaid'=>'未入金のみ','partial'=>'一部入金','paid'=>'入金済','overpaid'=>'過払い・返金確認'],$payment).'</div><div class="button-row"><button class="btn">検索・絞り込み</button><a class="btn btn-outline" href="?page=admin">条件をクリア</a></div></form></section><p role="status">表示：'.count($rows).'件 ／ 全申込 '.count($all);
 return $html.'件</p><a class="btn btn-outline" href="?page=export&amp;kind=people">参加者CSV（全件）</a>'.($rows?tabler(['番号','氏名・クラス','連絡先（幹事のみ）','状態','当初請求','入金割当','欠席申請'],$rows):'<p class="empty">条件に一致する申込はありません。検索条件を変えるか、条件をクリアしてください。</p>');
}
