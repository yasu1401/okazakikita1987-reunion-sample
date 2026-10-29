<?php
declare(strict_types=1);
function photoCaptionView(array $f):string {
 $caption=(string)($f['caption']??'');$author=(string)($f['author_label']??'');
 return ($caption!==''?'<p class="photo-caption">'.h($caption).'</p>':'').($author!==''?'<p class="mini-note">投稿：'.h($author).' ／ '.h(substr($f['created'],0,10)).'</p>':'');
}
function photoPostView(array $u):string {
 try{photoMember($u);}catch(DomainException $e){return '<section class="panel"><h2>写真を投稿する</h2><p>'.h($e->getMessage()).'</p></section>';}
 $_SESSION['post_nonce']??=bin2hex(random_bytes(16));$a=submissionAlbum();
 return '<section class="panel" id="photo-post"><div class="eyebrow">SHARE YOUR PHOTOS</div><h2>最近の一枚、みんなに届けよう。</h2><p>写真とコメントは「'.h($a['name']).'」へ自動掲載され、ログインした参加者が閲覧・ダウンロードできます。</p>'.form('post_photo',hidden('post_key',$_SESSION['post_nonce']).input('title','写真のタイトル').input('author','アルバムに表示する投稿者名（ニックネーム可）').'<label class="field">写真についてのコメント<textarea name="caption" maxlength="1000" rows="4"></textarea></label><label class="file-drop">写真を選択（JPEG／PNG、5MB・2000万画素以下）<input type="file" name="photo" accept="image/jpeg,image/png" required></label><label class="photo-consent"><input type="checkbox" name="consent" value="yes" required> 掲載できる写真であることを確認し、写真・コメント・投稿者名の参加者への公開に同意します。</label><p class="mini-note">開発プレビューでは架空のテスト画像のみ使用してください。幹事が内容の修正・掲載取り下げをすることがあります。1人50枚まで。</p>'.button('写真とコメントを投稿する'),true).'<p><a href="?page=gallery&amp;album='.$a['id'].'">みんなの投稿写真を見る ↗</a></p></section>';
}
function photoManagementView():string {
 // Reuse album settings and organizer-only upload; replace the old photograph list.
 $html=photosAdminView();$marker='<section class="panel"><a href="?page=file';$at=strpos($html,$marker);if($at!==false)$html=substr($html,0,$at);
 $html.='<section class="section"><h2>写真・投稿コメントの編集</h2><p>数字が小さい順に表示し、同じ数字なら新しい投稿を先に表示します。削除は一覧と画像配信から取り下げ、履歴を残します。</p>';
 foreach(all("SELECT f.*,u.name AS account_name FROM private_files f JOIN users u ON u.id=f.owner_id WHERE f.kind='photo' ORDER BY f.deleted,f.sort_order,f.created DESC,f.id DESC") as $f){
  $title=$f['photo_title']?:$f['original_name'];$html.='<article class="panel"><h3>#'.$f['id'].' '.h($title).'</h3><p>登録者（幹事のみ表示）：'.h($f['account_name']).' ／ '.h($f['created']).' ／ '.((int)$f['submitted']?'参加者の投稿':'幹事の登録').'</p>';
  if((int)$f['deleted']){$html.='<p>削除済み・配信停止</p>'.form('photo_restore',hidden('id',$f['id']).reasons().button('非公開で復元')).'</article>';continue;}
  $html.='<img class="protected-photo" src="?page=file&amp;inline=1&amp;id='.$f['id'].'" alt="'.h($title).'">'.form('photo_edit',hidden('id',$f['id']).input('title','写真のタイトル',$title).input('author','投稿者名（アルバム表示用）',$f['author_label']?:'幹事会').'<label class="field">写真についてのコメント<textarea name="caption" maxlength="1000" rows="4">'.h($f['caption']??'').'</textarea></label>'.albumSelect((int)$f['album_id']).input('sort_order','表示順（小さい順／同順は新しい投稿が先）',$f['sort_order'],'number').'<label class="field">写真の公開<select name="published"><option value="yes">公開</option><option value="no"'.(!$f['published']?' selected':'').'>非公開</option></select></label>'.reasons().button('写真・コメント・表示順を保存')).form('photo_remove',hidden('id',$f['id']).reasons().button('写真を削除（掲載取り下げ）')).'</article>';
 }
 return $html.'</section>';
}
