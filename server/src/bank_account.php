<?php
declare(strict_types=1);
function saveBankAccount(array $u,array $d):void {
 requirev($u['role']==='owner','振込先口座は管理者だけが変更できます。');$account=[];
 foreach(['bankName','branchName','accountType','accountNumber','accountHolder'] as $k){$account[$k]=trim((string)($d[$k]??''));requirev(mb_strlen($account[$k])<=100&&!preg_match('/[\r\n\x00-\x1f]/u',$account[$k]),'振込先は各項目100文字以内・改行なしで入力してください。');}
 $account['accountNumber']=mb_convert_kana($account['accountNumber'],'n','UTF-8');
 if($account['bankName']===''&&$account['branchName']===''&&$account['accountNumber']===''&&$account['accountHolder']==='')$account=[];
 else{foreach(['bankName','branchName','accountHolder'] as $k)requirev($account[$k]!=='','銀行名・支店名・口座名義を入力してください。');requirev(in_array($account['accountType'],['普通','当座'],true),'口座種別を選択してください。');requirev((bool)preg_match('/^[0-9]{7}$/D',$account['accountNumber']),'口座番号は7桁の数字で入力してください（先頭の0も入力）。');}
 $s=setting();$before=$s['bankAccount']??[];$s['bankAccount']=$account;q('UPDATE settings SET data_json=? WHERE id=1',[jsonv($s)]);logv((int)$u['id'],'振込先口座の設定変更','settings:bank-account',$before,$account,reasonv($d));
}
function bankAccountGuide(array $s,bool $withDeadlines=false,bool $showTransfer=true):string {
 $b=$s['bankAccount']??[];$html='<section class="bank-guide"><h3>'.($showTransfer?'会費の振込先':'内容変更・キャンセルの期限').'</h3>';
 if($showTransfer){
 if(empty($b['bankName'])||empty($b['accountNumber']))$html.='<p>振込先は準備中です。口座情報の案内があるまでお振り込みをお待ちください。</p>';
 else{$html.='<dl class="overview-dates">';foreach(['bankName'=>'銀行名','branchName'=>'支店名','accountType'=>'口座種別','accountNumber'=>'口座番号','accountHolder'=>'口座名義'] as $k=>$label)$html.='<div><dt>'.$label.'</dt><dd>'.h($b[$k]??'').'</dd></div>';$html.='</dl><p class="mini-note">振込名義は申込時に入力したカタカナ名義にそろえてください。開発プレビューでは実際の振込は行わないでください。</p>';}
 }
 if($withDeadlines){$html.='<div class="bank-deadlines">'.($showTransfer?'<h4>お手続きの期限</h4>':'').'<dl class="overview-dates">';$deadlines=$showTransfer?['registration'=>'申込期限','payment'=>'入金期限（振込期限）','change'=>'内容変更期限','cancel'=>'キャンセル期限']:['change'=>'内容変更期限','cancel'=>'キャンセル期限'];foreach($deadlines as $k=>$label)$html.='<div><dt>'.$label.'</dt><dd>'.h(homeDate($s[$k])).'</dd></div>';$html.='</dl></div>';}
 return $html.'</section>';
}
function bankAccountSettings(array $u):string {
 $b=setting()['bankAccount']??[];$html='<section class="panel"><h2>会費の振込先口座</h2><p>保存すると参加申込の「会費・お申し込みの期限」に表示します。変更は管理者だけが行えます。</p>';
 if($u['role']!=='owner')return $html.bankAccountGuide(setting()).'<p class="mini-note">幹事は閲覧のみです。変更は管理者に依頼してください。</p></section>';
 $fields='';foreach(['bankName'=>'銀行名','branchName'=>'支店名','accountNumber'=>'口座番号（7桁・先頭の0も入力）','accountHolder'=>'口座名義（カタカナ）'] as $k=>$label)$fields.=input($k,$label,$b[$k]??'','text',false);
 $fields.='<label class="field">口座種別<select name="accountType"><option value="普通">普通</option><option value="当座"'.(($b['accountType']??'')==='当座'?' selected':'').'>当座</option></select></label>';
 return $html.form('bank_account','<div class="grid-2">'.$fields.'</div><p class="mini-note">開発中は架空口座のみ入力してください。口座番号は数字7桁です。銀行名・支店名・番号・名義をすべて空欄にすると案内を取り下げます。</p>'.reasons().button('振込先口座を保存')).'</section>';
}
