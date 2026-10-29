<?php
require __DIR__.'/photos.php';require APP_ROOT.'/src/home_views.php';
$s=setting();$s['fee']=12345;$s['open']='2027-01-02';$s['registration']='2028-07-31';$s['venue']='<script>会場</script>';$s['dateLabel']='2028年10月31日（火）（仮）';$v=homeOverview($s);
check(str_contains($v,'12,345円')&&str_contains($v,'2027年1月2日')&&str_contains($v,'2028年7月31日')&&str_contains($v,'&lt;script&gt;会場&lt;/script&gt;')&&str_contains($v,'2028年10月31日'),'開催概要は設定値を安全に表示');
$v=homeClassSummary();$count=(int)one("SELECT COUNT(*) n FROM registrations WHERE status='active'")['n'];check(substr_count($v,'class="class-card"')===10&&str_contains($v,'<b>'.$count.'</b>人')&&!str_contains($v,'example.test'),'トップの10クラスと全体人数をDB集計・個人情報なし');
$v=applicationSchedule(setting());check(str_contains($v,'入金期限')&&str_contains($v,'内容変更期限')&&str_contains($v,'キャンセル期限')&&!str_contains(homeOverview(setting()),'入金期限'),'入金・変更・キャンセル期限は申込内だけに掲載');
echo "ALL $n HOME-INCLUSIVE CHECKS PASSED\n";
