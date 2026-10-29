<?php
declare(strict_types=1);
function submissionAlbum():?array{return one("SELECT * FROM albums WHERE purpose='submissions' ORDER BY id LIMIT 1");}
function photoMember(array $u):void {
 $p=registrationFor((int)$u['id']);requirev($p&&$p['status']==='active','参加予定として登録している方が投稿できます。');
 requirev(date('Y-m-d')<=setting()['photoEnd'],'写真の公開・投稿期間が終了しています。');
 $a=submissionAlbum();requirev($a&&(int)$a['visible']===1,'現在、投稿アルバムの受付を停止しています。');
}
function photoText(array $d):array {
 $title=trim((string)($d['title']??''));$caption=trim((string)($d['caption']??''));$author=trim((string)($d['author']??''));
 requirev($title!==''&&mb_strlen($title)<=200,'写真のタイトルは1〜200文字です。');
 requirev(mb_strlen($caption)<=1000,'写真のコメントは1000文字以内です。');
 requirev($author!==''&&mb_strlen($author)<=100,'アルバムに表示する投稿者名は1〜100文字です。');
 return [$title,$caption,$author];
}
function photoSnapshot(array $f):array{return array_intersect_key($f,array_flip(['id','owner_id','album_id','photo_title','caption','author_label','sort_order','deleted','published','submitted']));}
function submitPhoto(array $u,array $d,array $file):int {
 photoMember($u);[$title,$caption,$author]=photoText($d);requirev(($d['consent']??'')==='yes','写真とコメントを参加者へ掲載する確認にチェックしてください。');
 $nonce=(string)($d['post_key']??'');requirev((bool)preg_match('/^[a-f0-9]{32}$/D',$nonce),'投稿画面を再読み込みしてください。');$key=hash('sha256',$nonce);
 $old=one('SELECT id,owner_id FROM private_files WHERE post_key=?',[$key]);if($old){requirev((int)$old['owner_id']===(int)$u['id'],'投稿画面を再読み込みしてください。');return (int)$old['id'];}
 requirev((int)one("SELECT COUNT(*) n FROM private_files WHERE owner_id=? AND submitted=1",[$u['id']])['n']<50,'1人50枚まで投稿できます。追加は幹事へご相談ください。');
 $a=submissionAlbum();$id=storeUpload($u,$file,'photo',null,0,(int)$a['id']);
 q('UPDATE private_files SET photo_title=?,caption=?,author_label=?,submitted=1,published=1,post_key=? WHERE id=?',[$title,$caption,$author,$key,$id]);
 logv((int)$u['id'],'参加者の写真投稿','file:'.$id,null,['album_id'=>(int)$a['id'],'title'=>$title,'caption'=>$caption,'author'=>$author,'published'=>1],'本人が掲載に同意し、自動掲載');return $id;
}
function moderatePhoto(array $u,array $d):void {
 adminInquiry($u);$f=one("SELECT * FROM private_files WHERE id=? AND kind='photo'",[(int)($d['id']??0)]);requirev($f&&!(int)$f['deleted'],'掲載写真を選択してください。');
 [$title,$caption,$author]=photoText($d);$album=(int)($d['album_id']??0);requirev((bool)one('SELECT id FROM albums WHERE id=?',[$album]),'アルバムを選択してください。');
 $order=(string)($d['sort_order']??'');requirev((bool)preg_match('/^[0-9]{1,6}$/D',$order),'表示順は0〜999999です。');$published=($d['published']??'')==='yes'?1:0;$reason=reasonv($d);
 q('UPDATE private_files SET photo_title=?,caption=?,author_label=?,album_id=?,sort_order=?,published=? WHERE id=?',[$title,$caption,$author,$album,(int)$order,$published,$f['id']]);
 logv((int)$u['id'],'写真・コメントを編集','file:'.$f['id'],photoSnapshot($f),photoSnapshot(one('SELECT * FROM private_files WHERE id=?',[$f['id']])),$reason);
}
function removePhoto(array $u,array $d):void {
 adminInquiry($u);$f=one("SELECT * FROM private_files WHERE id=? AND kind='photo'",[(int)($d['id']??0)]);requirev($f&&!(int)$f['deleted'],'有効な写真がありません。');$reason=reasonv($d);
 q('UPDATE private_files SET deleted=1,published=0 WHERE id=?',[$f['id']]);logv((int)$u['id'],'写真を削除（取り下げ）','file:'.$f['id'],photoSnapshot($f),['deleted'=>1,'published'=>0],$reason);
}
function restorePhoto(array $u,array $d):void {
 adminInquiry($u);$f=one("SELECT * FROM private_files WHERE id=? AND kind='photo'",[(int)($d['id']??0)]);requirev($f&&(int)$f['deleted']===1,'削除済みの写真がありません。');$reason=reasonv($d);
 q('UPDATE private_files SET deleted=0,published=0 WHERE id=?',[$f['id']]);logv((int)$u['id'],'写真を非公開で復元','file:'.$f['id'],photoSnapshot($f),['deleted'=>0,'published'=>0],$reason);
}
