<?php
require __DIR__.'/../src/payment_guidance.php';
$cases=[
 ['active',0,8000,'2026-10-31','waiting','申込受付済み','あと8,000円'],
 ['active',3500,4500,'2026-10-31','waiting','一部入金確認済み','あと4,500円'],
 ['active',8000,0,'2026-10-31','complete','入金確認済み','0円'],
 ['active',9000,-1000,'2026-10-31','review','入金確認済み','1,000円'],
 ['cancel_requested',0,8000,'2026-10-31','review','欠席申請受付済み','幹事に確認'],
 ['cancelled',8000,-8000,'2026-10-31','review','欠席への変更完了','幹事に確認'],
 ['active',0,8000,'2029-01-01','review','申込受付済み','あと8,000円'],
 ['active',0,0,'2026-10-31','complete','申込受付済み','0円'],
];
foreach($cases as [$status,$paid,$balance,$today,$tone,$label,$value]){
 $g=registrationGuide(['status'=>$status],$paid,$balance,$today,'2028-09-15');
 if($g['tone']!==$tone||$g['label']!==$label||$g['balanceValue']!==$value)throw new RuntimeException(json_encode($g));
 if(in_array($status,['cancel_requested','cancelled'],true)&&str_contains($g['title'],'振り込みをお願いします'))throw new RuntimeException('Cancellation asks for payment');
 if($balance===0&&$paid>0&&!str_contains($g['message'],'追加のお振り込みは不要'))throw new RuntimeException('Paid guidance missing');
}
echo count($cases)," payment guidance scenarios passed
";
