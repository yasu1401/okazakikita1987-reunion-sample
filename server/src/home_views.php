<?php
declare(strict_types=1);require_once __DIR__.'/bank_account.php';
function homeDate(string $date):string {
 if(preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D',$date,$m))return $m[1].'年'.(int)$m[2].'月'.(int)$m[3].'日';
 return $date;
}
function homeOverview(array $s):string {
 return '<section class="event-overview" aria-label="開催概要・受付案内"><p class="overview-note">開催日・会費・受付日程は、表示確認用の仮設定です。正式なご案内まで実際の受付・お振込はできません。</p><div class="overview-row"><div class="overview-label">WHEN<span>開催日（仮）</span></div><div class="overview-content"><strong>'.h($s['dateLabel']).'</strong><p>開催時刻は決まり次第ご案内します。</p></div></div><div class="overview-row"><div class="overview-label">WHERE<span>会場</span></div><div class="overview-content"><strong>'.h($s['venue']).'</strong><p>懐かしい岡崎で、再会のひとときを。</p></div></div><div class="overview-row"><div class="overview-label">PARTICIPATION<span>会費・お申し込み（仮）</span></div><div class="overview-content"><p class="registration-label">申込受付期間</p><div class="registration-period"><span>'.h(homeDate($s['open'])).'</span><span>〜 '.h(homeDate($s['registration'])).'</span></div><p class="participation-fee">会費 <strong>'.h(yen((int)$s['fee'])).'</strong><small>（仮）</small></p></div></div></section>';
}
function homeClassSummary():string {
 $counts=array_fill(0,11,0);foreach(all("SELECT class_no,COUNT(*) n FROM registrations WHERE status='active' GROUP BY class_no") as $r)$counts[(int)$r['class_no']]=(int)$r['n'];$total=array_sum($counts);$size=(int)(setting()['classSize']??47);
 $html='<section class="section" id="classmates"><div class="section-head"><div><div class="eyebrow">FIND YOUR CLASSMATES</div><h2>みんな、集まってきています。</h2><p>あなたのクラスは何人？懐かしい名前を探してみてください。</p></div><div class="total-badge">全体の参加予定 <b>'.$total.'</b>人<span>（架空サンプル）</span></div></div><div class="classes-grid">';
 foreach(array_slice($counts,1,null,true) as $cl=>$count)$html.='<a class="class-card" href="?page=classes&amp;class='.$cl.'"><span class="label">3年'.$cl.'組</span><span class="arrow">↗</span><strong>'.$count.'<small>人</small></strong><progress class="class-progress" max="'.$size.'" value="'.min($size,$count).'" aria-label="3年'.$cl.'組の参加状況（目安）"></progress></a>';
 return $html.'</div>'.($counts[0]?'<p>クラス確認中：'.$counts[0].'人（全体の参加人数に含みます）</p>':'').'<p class="section-note">※ 人物・人数は架空のサンプルです。名前を掲載しない方も人数には含みます。欠席申請中・取消済みの方は参加予定人数から除きます。</p></section>';
}

function applicationSchedule(array $s):string {
 $html='<section class="application-schedule"><div class="eyebrow">APPLICATION GUIDE</div><h2>会費・お申し込みの期限</h2><p>以下は表示確認用の仮設定です。</p><dl class="overview-dates"><div><dt>会費（仮）</dt><dd>'.h(yen((int)$s['fee'])).'</dd></div>';
 foreach(['registration'=>'申込期限','payment'=>'入金期限','change'=>'内容変更期限','cancel'=>'キャンセル期限'] as $key=>$label)$html.='<div><dt>'.$label.'</dt><dd>'.h(homeDate($s[$key])).'</dd></div>';
 return $html.'</dl>'.bankAccountGuide($s).'<p class="mini-note">内容変更・欠席の申請はログイン後の「申込確認」から行えます。各期限は幹事の設定により変更される場合があります。</p></section>';
}
