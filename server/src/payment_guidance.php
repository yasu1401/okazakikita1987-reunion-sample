<?php
declare(strict_types=1);
function registrationGuide(array $p,int $paid,int $balance,string $today,string $paymentDate):array {
 $g=['tone'=>'waiting','label'=>'申込受付済み','title'=>'会費のお振り込みをお願いします','message'=>'下記の振込先・入金期限をご確認のうえ、会費をお振り込みください。','balanceLabel'=>'これからお振り込みいただく金額','balanceValue'=>($balance>0?'あと':'').number_format(max(0,$balance)).'円','note'=>'すでにお振り込みの場合は、幹事の確認・画面への反映をお待ちください。重ねて振り込む必要はありません。'];
 if($p['status']==='cancel_requested'||$p['status']==='cancelled') {
  $g['tone']='review';$g['label']=$p['status']==='cancel_requested'?'欠席申請受付済み':'欠席への変更完了';$g['title']=$p['status']==='cancel_requested'?'幹事が欠席の申請を確認しています':'欠席への変更が完了しました';
  $g['message']=$paid>0?'お支払い済みの会費について、返金の有無・金額・手続きを幹事が確認します。':'今後のお支払いについては、幹事からの案内をご確認ください。';
  $g['balanceLabel']='お支払い・返金のご案内';$g['balanceValue']='幹事に確認';$g['note']='追加のお振り込みはせず、ご不明な点は問い合わせフォームからご連絡ください。';return $g;
 }
 if($balance<=0){$g['tone']='complete';$g['label']=$paid>0?'入金確認済み':'申込受付済み';$g['title']=$paid>0?'会費の入金を確認しました':'お支払いはありません';$g['message']='追加のお振り込みは不要です。開催に関するご案内は、登録メールアドレスとサイトでご確認ください。';$g['note']='申込内容に変更がある場合は、変更期限内にこの画面からお手続きください。';
  if($balance<0){$g['tone']='review';$g['balanceLabel']='多くお預かりしている金額';$g['balanceValue']=number_format(-$balance).'円';$g['message']='会費より多い金額の入金を確認しています。差額の取り扱いは幹事が確認します。追加のお振り込みは不要です。';}
 }elseif($paid>0){$g['label']='一部入金確認済み';$g['title']='残りの会費のお振り込みをお願いします';$g['message']='会費の一部の入金を確認しました。下記の残額と入金期限をご確認ください。';}
 if($balance>0&&$today>$paymentDate){$g['tone']='review';$g['title']='入金期限を過ぎています。幹事へご連絡ください';$g['message']='申込は自動で取り消されていません。お振り込み前に、問い合わせフォームから幹事へご相談ください。';}
 return $g;
}
function registrationGuideView(array $g):string{return '<section class="registration-guide guide-'.h($g['tone']).'" aria-label="申込状況と次のお手続き"><p class="guide-status">'.h($g['label']).'</p><h2>'.h($g['title']).'</h2><p>'.h($g['message']).'</p><p class="guide-note">'.h($g['note']).'</p></section>';}
